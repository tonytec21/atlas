<?php
/**
 * tcloud_local.php — TCloud Assinador em MODO LOCAL (tcloudsign://local), sem serviço na VM.
 *
 * Implementa, no próprio Signum, o lado "sistema" do contrato 3.6 do guia do TCloud Assinador 2.4.0:
 *  - o Signum guarda o pedido e gera o link tcloudsign://local?u=<endpoint>&t=<ticket>;
 *  - o app da estação chama tcloud_estacao.php?acao=ticket_* (sem sessão; a credencial é o ticket);
 *  - a assinatura inteira é feita na estação, na versão instalada lá; o Signum recebe o arquivo pronto.
 *
 * Regras do contrato garantidas aqui:
 *  - ticket de 256 bits, guardado só como hash (SHA-256), de uso único (um único ticket_abrir);
 *  - ticket_abrir só do IP de quem criou o pedido; ele grava esse IP e as demais ações só desse IP;
 *  - 3 minutos para abrir, 15 para concluir; depois, expirado;
 *  - erros em JSON {ok:false, erro, codigo} com HTTP 4xx.
 * Além disso, o Signum confere o PDF devolvido (assinatura cobrindo o arquivo inteiro) e lê o titular
 * do certificado dentro da própria assinatura.
 */

if (!defined('ASG_TCL_MIN_ABRIR'))    define('ASG_TCL_MIN_ABRIR', 3);
if (!defined('ASG_TCL_MIN_CONCLUIR')) define('ASG_TCL_MIN_CONCLUIR', 15);
// Conferência do IP entre o navegador que clicou e o TCloud Assinador que abre o link:
//   'rede'    (padrão) mesmo IP, OU IPs diferentes da rede interna / IPv4×IPv6 do mesmo computador (registra no log)
//   'estrito' exige exatamente o mesmo IP
//   'livre'   não confere (o ticket continua de uso único e com prazo curto)
if (!defined('ASG_TCL_IP_MODO')) define('ASG_TCL_IP_MODO', (defined('ASG_TCL_EXIGIR_MESMO_IP') && !ASG_TCL_EXIGIR_MESMO_IP) ? 'livre' : 'rede');
if (!defined('ASG_TCL_MAX_BYTES'))    define('ASG_TCL_MAX_BYTES', 80 * 1024 * 1024);   // arquivo assinado devolvido

class AsgTclErro extends RuntimeException
{
    public $codigo; public $http;
    public function __construct($msg, $codigo = 'erro', $http = 400)
    { parent::__construct($msg); $this->codigo = $codigo; $this->http = (int)$http; }
}

/* ------------------------------------------------------------------ arquivos */

function asg_tcl_arquivo($id)      { return asg_dir_tmp() . '/tcl_' . asg_tc_id($id) . '.json'; }
function asg_tcl_indice($hash)     { return asg_dir_tmp() . '/tclt_' . preg_replace('~[^a-f0-9]~', '', $hash) . '.txt'; }
function asg_tcl_assinado_tmp($id, $i) { return asg_dir_tmp() . '/tcl_' . asg_tc_id($id) . '_' . (int)$i . '.bin'; }
function asg_tcl_existe($id)       { return is_file(asg_tcl_arquivo($id)); }

/** Abre o estado do pedido com trava exclusiva e devolve [handle, estado]. */
function asg_tcl_travar($id)
{
    $f = asg_tcl_arquivo($id);
    if (!is_file($f)) throw new AsgTclErro('Pedido de assinatura não encontrado.', 'nao_encontrado', 404);
    $fh = fopen($f, 'c+');
    if (!$fh) throw new AsgTclErro('Falha ao abrir o pedido.', 'erro', 500);
    flock($fh, LOCK_EX);
    $st = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($st)) { flock($fh, LOCK_UN); fclose($fh); throw new AsgTclErro('Pedido inválido.', 'erro', 500); }
    return [$fh, $st];
}
function asg_tcl_gravar($fh, $st)
{
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, json_encode($st, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); fflush($fh);
}
function asg_tcl_soltar($fh) { if ($fh) { flock($fh, LOCK_UN); fclose($fh); } }

/** Limpeza de pedidos locais antigos (> 6 h). */
function asg_tcl_limpar()
{
    $lim = time() - 6 * 3600;
    foreach ((array)@glob(asg_dir_tmp() . '/tcl*_*') as $f) if (is_file($f) && filemtime($f) < $lim) @unlink($f);
    $limSonda = time() - 3600;   // sondas de verificação vivem pouco
    foreach ((array)@glob(asg_dir_tmp() . '/tclp*_*') as $f) if (is_file($f) && filemtime($f) < $limSonda) @unlink($f);
}

/** Corta texto em N caracteres (UTF-8), com ou sem mbstring. */
function asg_tcl_corta($t, $n)
{
    return function_exists('mb_substr') ? mb_substr($t, 0, $n, 'UTF-8') : (preg_match('~^.{0,' . (int)$n . '}~us', $t, $m) ? $m[0] : substr($t, 0, $n));
}

/** IP da rede interna? (loopback, privados, link-local, CGNAT/Tailscale 100.64/10, Radmin 26/8, IPv6 local) */
function asg_tcl_ip_interno($ip)
{
    $ip = asg_tc_ip($ip);
    if ($ip === '') return false;
    if (strpos($ip, ':') !== false) {                                   // IPv6
        return $ip === '::1' || preg_match('~^(fe[89ab][0-9a-f]|f[cd][0-9a-f]{2}):~i', $ip) === 1;
    }
    $n = ip2long($ip);
    if ($n === false) return false;
    foreach ([['127.0.0.0', 8], ['10.0.0.0', 8], ['172.16.0.0', 12], ['192.168.0.0', 16], ['169.254.0.0', 16],
              ['100.64.0.0', 10], ['26.0.0.0', 8]] as $r) {
        $mask = -1 << (32 - $r[1]);
        if (($n & $mask) === (ip2long($r[0]) & $mask)) return true;
    }
    return false;
}

/**
 * O TCloud Assinador que abriu o link (IP $ipApp) pode atender o pedido criado pelo navegador de $ipPedido?
 * Devolve true/false; divergências aceitas vão para o log do PHP (auditoria).
 */
function asg_tcl_ip_aceito($ipPedido, $ipApp)
{
    $a = asg_tc_ip($ipPedido); $b = asg_tc_ip($ipApp);
    if ($a === '' || $a === $b || ASG_TCL_IP_MODO === 'livre') return true;
    if (ASG_TCL_IP_MODO === 'estrito') return false;
    $v6a = strpos($a, ':') !== false; $v6b = strpos($b, ':') !== false;
    $ok = ($v6a !== $v6b)                                                // mesmo computador por IPv4 num lado e IPv6 no outro
       || (asg_tcl_ip_interno($a) && asg_tcl_ip_interno($b));           // ambos na rede interna (proxy, VPN, loopback…)
    if ($ok) error_log("Atlas Signum/TCloud: link aberto de $b (pedido criado por $a) — aceito no modo 'rede'.");
    return $ok;
}

/* ------------------------------------------------------------------ prazos */

/** Aplica os prazos do contrato; devolve true se mudou o estado. */
function asg_tcl_prazos(&$st)
{
    if (!empty($st['finalizado'])) return false;
    $agora = time();
    if (empty($st['aberto_em']) && $agora - (int)$st['criado'] > ASG_TCL_MIN_ABRIR * 60) {
        $st['estado'] = 'expirado'; $st['finalizado'] = true;
        $st['mensagem'] = 'O TCloud Assinador não abriu a tempo. Confira se ele está instalado neste computador (versão 2.4.0 ou mais nova) e tente de novo.';
        return true;
    }
    if (!empty($st['aberto_em']) && $agora - (int)$st['aberto_em'] > ASG_TCL_MIN_CONCLUIR * 60) {
        $st['estado'] = 'expirado'; $st['finalizado'] = true;
        $st['mensagem'] = 'O tempo para concluir a assinatura acabou. Tente de novo.';
        return true;
    }
    return false;
}

/* ------------------------------------------------------------------ URL do endpoint */

/** URL de tcloud_estacao.php pelo mesmo endereço que o navegador usou para abrir o Atlas. */
function asg_tcl_url_endpoint($httpHost)
{
    $host = strtolower(trim((string)$httpHost));
    if (!preg_match('~^(\[[0-9a-f:.]+\]|[a-z0-9]([a-z0-9.\-]{0,251}[a-z0-9])?)(:\d{1,5})?$~', $host))
        throw new RuntimeException('Endereço do servidor inválido para gerar o link de assinatura.');
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
          || (($_SERVER['REQUEST_SCHEME'] ?? '') === 'https') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    // pedido criado por um módulo irmão (…/oficios, …/os): o endpoint da estação é sempre o do Signum
    $pastaSignum = basename(dirname(__DIR__));
    if (basename($dir) !== $pastaSignum) $dir = rtrim(dirname($dir), '/') . '/' . $pastaSignum;
    $dir = implode('/', array_map('rawurlencode', explode('/', $dir)));
    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/tcloud_estacao.php';
}

/* ------------------------------------------------------------------ lado do sistema */

/**
 * Cria o pedido local. $op traz as opções do pedido (aparencia, motivo, local, nivel…).
 * Devolve id, estado, modo e link — mesma forma do modo via serviço.
 */
function asg_tcl_iniciar($usuario, $nomeUsuario, $src, $origName, $op, $meta, $ipNavegador, $httpHost)
{
    asg_tcl_limpar();
    if (!is_file($src)) throw new RuntimeException('Envie o PDF novamente.');
    $id     = bin2hex(random_bytes(12));
    $ticket = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');   // 256 bits, base64url
    $hash   = hash('sha256', $ticket);

    $st = [
        'modo' => 'local', 'id' => $id, 'usuario' => $usuario, 'nome' => $nomeUsuario,
        'orig' => $origName, 'src' => $src, 'token' => $meta['token'], 'codigo' => $meta['codigo'], 'nPag' => $meta['nPag'],
        'opcoes' => $op, 'ticket_hash' => $hash, 'ip' => asg_tc_ip($ipNavegador),
        'criado' => time(), 'aberto_em' => 0, 'ip_estacao' => '',
        'estado' => 'aguardando_estacao', 'finalizado' => false, 'mensagem' => '',
        'entregas' => [], 'doc' => null,
    ];
    if (!empty($meta['destino'])) $st['destino'] = $meta['destino'];   // pedido de outro módulo
    file_put_contents(asg_tcl_arquivo($id), json_encode($st, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    file_put_contents(asg_tcl_indice($hash), $id);

    $link = 'tcloudsign://local?u=' . rawurlencode(asg_tcl_url_endpoint($httpHost)) . '&t=' . rawurlencode($ticket);
    return ['id' => $id, 'estado' => 'aguardando_estacao', 'modo' => 'local', 'link' => $link];
}

/**
 * Pedido de OUTRO MÓDULO do Atlas (ofícios, notas devolutivas, O.S.…).
 * O módulo gera o PDF com o próprio selo e passa aqui; o Signum cuida do link, do ticket e do
 * acompanhamento. Ao concluir, o Signum chama o "gravador" do módulo, que salva o PDF assinado e
 * atualiza o banco do jeito do módulo.
 *
 * $destino = ['modulo' => 'oficio', 'gravador' => arquivo PHP do módulo, 'funcao' => nome da função,
 *             'script' => SCRIPT_NAME do módulo (URLs), 'dados' => [...] que a função recebe]
 */
function asg_tcl_iniciar_modulo($usuario, $nomeUsuario, $pdfBytes, $nomeDoc, $op, $destino, $ipNavegador, $httpHost)
{
    if (!is_string($pdfBytes) || strncmp(ltrim($pdfBytes), '%PDF', 4) !== 0) throw new RuntimeException('O módulo não gerou um PDF válido para assinar.');
    $raiz = realpath(dirname(__DIR__, 2));                                   // pasta do Atlas (acima de /signum)
    $grav = realpath((string)($destino['gravador'] ?? ''));
    if (!$grav || strpos($grav, $raiz) !== 0 || !preg_match('~^[a-z_][a-z0-9_]*$~i', (string)($destino['funcao'] ?? '')))
        throw new RuntimeException('Gravador do módulo inválido.');
    $destino['gravador'] = $grav;
    asg_tcl_limpar();
    $src = asg_dir_tmp() . '/tclm_' . bin2hex(random_bytes(10)) . '.pdf';
    file_put_contents($src, ltrim($pdfBytes));
    return asg_tcl_iniciar($usuario, $nomeUsuario, $src, $nomeDoc, $op,
        ['token' => '', 'codigo' => (string)($destino['dados']['codigo'] ?? ''), 'nPag' => 0, 'destino' => $destino],
        $ipNavegador, $httpHost);
}

/** Chama o gravador do módulo com o PDF assinado. Devolve o que a tela recebe em "doc". */
function asg_tcl_gravar_modulo($st, $pdfAssinado, $entrega)
{
    $d = $st['destino'];
    $antes = $_SERVER['SCRIPT_NAME'] ?? null;
    if (!empty($d['script'])) $_SERVER['SCRIPT_NAME'] = $d['script'];   // URLs do módulo saem certas
    try {
        require_once $d['gravador'];
        if (!function_exists($d['funcao'])) throw new RuntimeException('Função de gravação do módulo não encontrada.');
        $r = call_user_func($d['funcao'], (array)($d['dados'] ?? []), $pdfAssinado,
            ['titular' => (string)($entrega['titular'] ?? ''), 'cpf' => (string)($entrega['cpf'] ?? '')], (string)$st['usuario']);
    } finally {
        if ($antes === null) unset($_SERVER['SCRIPT_NAME']); else $_SERVER['SCRIPT_NAME'] = $antes;
    }
    if (!is_array($r)) throw new RuntimeException('O módulo não confirmou a gravação.');
    return $r + ['modulo' => (string)($d['modulo'] ?? ''), 'titular' => (string)($entrega['titular'] ?? ''),
                 'avisos' => (array)($entrega['avisos'] ?? []), 'carimbo' => false, 'ltv' => false];
}

/** Estado do pedido para a tela (mesma forma do modo via serviço). */
function asg_tcl_status($usuario, $id)
{
    list($fh, $st) = asg_tcl_travar($id);
    try {
        if (($st['usuario'] ?? '') !== $usuario) throw new RuntimeException('Pedido inválido.');
        if (asg_tcl_prazos($st)) asg_tcl_gravar($fh, $st);
        $msg = $st['mensagem'];
        if ($msg === '') {
            $msg = ['aguardando_estacao' => 'Aguardando o TCloud Assinador abrir neste computador…',
                    'na_estacao'         => 'Aguardando a confirmação no seu computador.',
                    'assinando'          => 'Assinando o documento…'][$st['estado']] ?? '';
        }
        $out = ['estado' => $st['estado'], 'finalizado' => !empty($st['finalizado']), 'mensagem' => $msg,
                'estacao' => (string)($st['ip_estacao'] ?? ''), 'modo' => 'local'];
        if (!empty($st['doc'])) $out['doc'] = $st['doc'];
        return $out;
    } finally { asg_tcl_soltar($fh); }
}

/** Cancela pela tela. */
function asg_tcl_cancelar($usuario, $id)
{
    list($fh, $st) = asg_tcl_travar($id);
    try {
        if (($st['usuario'] ?? '') !== $usuario) throw new RuntimeException('Pedido inválido.');
        if (!empty($st['doc'])) return ['cancelado' => false, 'doc' => $st['doc']];
        if (empty($st['finalizado'])) {
            $st['estado'] = 'cancelado'; $st['finalizado'] = true; $st['mensagem'] = 'A assinatura foi cancelada no sistema.';
            asg_tcl_gravar($fh, $st);
        }
        return ['cancelado' => true];
    } finally { asg_tcl_soltar($fh); }
}

/* ------------------------------------------------------------------ teste do assinador */
/*  "Verificar/testar": o navegador não enxerga programas instalados, então a tela abre um pedido de
 *  TESTE (tcloudsign://local com ticket próprio). O app abre a janela normal com um pequeno JSON;
 *  o usuário escolhe o certificado e digita o PIN; o app assina e devolve. O Signum confere e
 *  DESCARTA o arquivo assinado — nada é gravado nem entra na lista de documentos.
 *  Ser "detectado" = o app chamou ticket_abrir; "testado" = a assinatura de teste voltou válida.  */

if (!defined('ASG_TCL_SONDA_SEG'))      define('ASG_TCL_SONDA_SEG', 120);    // para o app abrir
if (!defined('ASG_TCL_SONDA_SEG_TOTAL')) define('ASG_TCL_SONDA_SEG_TOTAL', 600); // para concluir o teste

function asg_tcl_sonda_arquivo($id)  { return asg_dir_tmp() . '/tclp_' . asg_tc_id($id) . '.json'; }
function asg_tcl_sonda_indice($hash) { return asg_dir_tmp() . '/tclpt_' . preg_replace('~[^a-f0-9]~', '', $hash) . '.txt'; }

function asg_tcl_sonda_criar($usuario, $ipNavegador, $httpHost)
{
    asg_tcl_limpar();
    $id = bin2hex(random_bytes(12));
    $ticket = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $hash = hash('sha256', $ticket);
    $doc = json_encode([
        'tipo'      => 'teste-do-assinador',
        'sistema'   => 'Atlas Signum',
        'usuario'   => $usuario,
        'gerado_em' => date('c'),
        'aviso'     => 'Documento de teste. A assinatura só confirma que o TCloud Assinador funciona neste computador; nada é gravado.',
        'nonce'     => bin2hex(random_bytes(8)),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $st = ['usuario' => $usuario, 'ip' => asg_tc_ip($ipNavegador), 'hash' => $hash, 'criado' => time(),
           'detectado' => false, 'aberto_em' => 0, 'ip_app' => '', 'outro_ip' => false,
           'estado' => 'aguardando_estacao', 'finalizado' => false, 'mensagem' => '', 'titular' => '', 'doc' => $doc];
    file_put_contents(asg_tcl_sonda_arquivo($id), json_encode($st, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    file_put_contents(asg_tcl_sonda_indice($hash), $id);
    return ['id' => $id, 'link' => 'tcloudsign://local?u=' . rawurlencode(asg_tcl_url_endpoint($httpHost)) . '&t=' . rawurlencode($ticket),
            'segundos' => ASG_TCL_SONDA_SEG];
}

/** Abre a sonda com trava (mesmo esquema dos pedidos). */
function asg_tcl_sonda_travar($id)
{
    $f = asg_tcl_sonda_arquivo($id);
    if (!is_file($f)) throw new AsgTclErro('Teste não encontrado.', 'nao_encontrado', 404);
    $fh = fopen($f, 'c+'); flock($fh, LOCK_EX);
    $st = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($st)) { asg_tcl_soltar($fh); throw new AsgTclErro('Teste inválido.', 'erro', 500); }
    return [$fh, $st];
}

function asg_tcl_sonda_prazos(&$st)
{
    if (!empty($st['finalizado'])) return false;
    if (!$st['detectado'] && time() - (int)$st['criado'] > ASG_TCL_SONDA_SEG) {
        $st['estado'] = 'expirado'; $st['finalizado'] = true; $st['mensagem'] = 'O TCloud Assinador não abriu a tempo.';
        return true;
    }
    if (time() - (int)$st['criado'] > ASG_TCL_SONDA_SEG_TOTAL) {
        $st['estado'] = 'expirado'; $st['finalizado'] = true; $st['mensagem'] = 'O tempo para concluir o teste acabou.';
        return true;
    }
    return false;
}

/** Situação do teste para a tela. */
function asg_tcl_sonda_status($usuario, $id)
{
    list($fh, $st) = asg_tcl_sonda_travar($id);
    try {
        if (($st['usuario'] ?? '') !== $usuario) throw new RuntimeException('Verificação inválida.');
        if (asg_tcl_sonda_prazos($st)) asg_tcl_gravar($fh, $st);
        return ['detectado' => !empty($st['detectado']), 'outro_ip' => !empty($st['outro_ip']),
                'estado' => $st['estado'], 'finalizado' => !empty($st['finalizado']),
                'mensagem' => (string)$st['mensagem'], 'titular' => (string)$st['titular'],
                'expirada' => !$st['detectado'] && $st['estado'] === 'expirado'];
    } finally { asg_tcl_soltar($fh); }
}

/** Cancelado pela tela. */
function asg_tcl_sonda_cancelar($usuario, $id)
{
    list($fh, $st) = asg_tcl_sonda_travar($id);
    try {
        if (($st['usuario'] ?? '') !== $usuario) throw new RuntimeException('Verificação inválida.');
        if (empty($st['finalizado'])) {
            $st['estado'] = 'cancelado'; $st['finalizado'] = true; $st['mensagem'] = 'O teste foi cancelado no sistema.';
            asg_tcl_gravar($fh, $st);
        }
        @unlink(asg_tcl_sonda_indice($st['hash']));
        return ['cancelado' => true];
    } finally { asg_tcl_soltar($fh); }
}

/** Atende as ações ticket_* de um pedido de TESTE (nada é gravado). */
function asg_tcl_sonda_atender($acao, $corpo, $id, $ipRemoto)
{
    list($fh, $st) = asg_tcl_sonda_travar($id);
    try {
        $ip = asg_tc_ip($ipRemoto);
        if (asg_tcl_sonda_prazos($st)) asg_tcl_gravar($fh, $st);
        if (!empty($st['finalizado']) && $acao !== 'ticket_recusar')
            throw new AsgTclErro($st['mensagem'] ?: 'Este teste já foi encerrado.', $st['estado'], 410);

        if ($acao === 'ticket_abrir') {
            if (!empty($st['aberto_em'])) throw new AsgTclErro('Este link de teste já foi usado.', 'usado', 409);
            if (!asg_tcl_ip_aceito($st['ip'], $ip)) {
                $st['outro_ip'] = true; $st['ip_recusado'] = $ip; asg_tcl_gravar($fh, $st);
                throw new AsgTclErro('Este teste foi gerado para outro computador (criado em ' . $st['ip'] . ', aberto de ' . $ip . ').', 'outro_computador', 403);
            }
            $st['detectado'] = true; $st['aberto_em'] = time(); $st['ip_app'] = $ip; $st['estado'] = 'na_estacao';
            asg_tcl_gravar($fh, $st);
            return ['ok' => true, 'pedido' => [
                'id' => 'teste-' . substr($st['hash'], 0, 8), 'sistema' => 'Atlas', 'titulo' => 'Teste do TCloud Assinador (nada será gravado)',
                'usuario' => $st['usuario'], 'nome' => $st['usuario'], 'documentos' => ['teste-do-assinador.json'],
                'opcoes' => ['motivo' => 'Teste do TCloud Assinador', 'nivel' => 'basico'],
                'minutos' => (int)ceil(ASG_TCL_SONDA_SEG_TOTAL / 60)]];
        }
        if (empty($st['aberto_em'])) throw new AsgTclErro('O teste ainda não foi aberto.', 'estado', 409);
        if ($ip !== $st['ip_app']) throw new AsgTclErro('Este teste foi aberto em outro computador.', 'outro_computador', 403);

        switch ($acao) {
            case 'ticket_documento':
                if ((int)($corpo['indice'] ?? 0) !== 0) throw new AsgTclErro('Documento inexistente.', 'documento', 404);
                return ['ok' => true, 'nome' => 'teste-do-assinador.json', 'conteudo_base64' => base64_encode($st['doc'])];

            case 'ticket_entregar':
                if (!empty($corpo['erro'])) {
                    $st['estado'] = 'assinando'; $st['erro_app'] = asg_tcl_corta((string)$corpo['erro'], 500);
                } else {
                    $b64 = (string)($corpo['conteudo_base64'] ?? '');
                    $bin = $b64 !== '' && strlen($b64) < 4 * 1024 * 1024 ? base64_decode($b64, true) : false;
                    if ($bin === false || $bin === '') throw new AsgTclErro('Arquivo de teste assinado inválido.', 'documento', 400);
                    $info = asg_jws_titular($bin, $st['doc']);
                    if (!$info['ok']) throw new AsgTclErro('A assinatura de teste voltou inválida: ' . $info['erro'], 'documento', 422);
                    $st['titular'] = $info['titular']; $st['estado'] = 'assinando'; $st['assinado_ok'] = true;
                    // o conteúdo assinado NÃO é guardado
                }
                asg_tcl_gravar($fh, $st);
                return ['ok' => true];

            case 'ticket_concluir':
                $st['finalizado'] = true;
                if (!empty($st['assinado_ok'])) {
                    $st['estado'] = 'concluido';
                    $st['mensagem'] = 'Teste concluído: a assinatura com o seu certificado funcionou' . ($st['titular'] ? ' (' . $st['titular'] . ')' : '') . '. Nada foi gravado.';
                } else {
                    $st['estado'] = 'erro';
                    $st['mensagem'] = 'A assinatura de teste não foi feita: ' . (($st['erro_app'] ?? '') ?: 'o assinador não devolveu o arquivo.');
                }
                asg_tcl_gravar($fh, $st);
                @unlink(asg_tcl_sonda_indice($st['hash']));
                return ['ok' => true, 'mensagem' => $st['mensagem']];

            case 'ticket_recusar':
                if (empty($st['finalizado'])) {
                    $st['estado'] = 'recusado'; $st['finalizado'] = true;
                    $st['mensagem'] = 'O teste de assinatura foi cancelado no computador (o TCloud Assinador está instalado).';
                    asg_tcl_gravar($fh, $st);
                }
                @unlink(asg_tcl_sonda_indice($st['hash']));
                return ['ok' => true];
        }
        throw new AsgTclErro('Ação desconhecida.', 'acao', 404);
    } finally { asg_tcl_soltar($fh); }
}

/**
 * Confere a assinatura JSON (JAdES/JWS) do teste e lê o titular do certificado (cabeçalho x5c).
 * Aceita JWS compacto ("a.b.c") ou serialização JSON ({payload, protected, signature} ou {signatures:[…]}).
 */
function asg_jws_titular($bin, $original)
{
    $r = ['ok' => false, 'erro' => '', 'titular' => ''];
    $b64u = function ($t) { $t = strtr((string)$t, '-_', '+/'); return base64_decode($t . str_repeat('=', (4 - strlen($t) % 4) % 4), true); };
    $txt = trim($bin);
    $prot = null; $sig = '';
    if (preg_match('~^[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]*\.[A-Za-z0-9_\-]+$~', $txt)) {
        list($h, , $sig) = explode('.', $txt);
        $prot = json_decode((string)$b64u($h), true);
    } else {
        $j = json_decode($txt, true);
        if (!is_array($j)) { $r['erro'] = 'formato desconhecido.'; return $r; }
        if (isset($j['signatures'][0])) $j = array_merge($j, $j['signatures'][0]);
        if (!empty($j['protected'])) $prot = json_decode((string)$b64u($j['protected']), true);
        $sig = (string)($j['signature'] ?? '');
        if (isset($j['header']['x5c']) && is_array($prot) && !isset($prot['x5c'])) $prot['x5c'] = $j['header']['x5c'];
    }
    if (!is_array($prot) || empty($prot['alg'])) { $r['erro'] = 'cabeçalho da assinatura ausente.'; return $r; }
    if ($sig === '' || strlen((string)$b64u($sig)) < 64) { $r['erro'] = 'assinatura vazia.'; return $r; }
    $r['ok'] = true;
    if (!empty($prot['x5c'][0]) && function_exists('openssl_x509_parse')) {
        $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split((string)$prot['x5c'][0], 64, "\n") . "-----END CERTIFICATE-----\n";
        $x = @openssl_x509_parse($pem);
        $cn = $x['subject']['CN'] ?? '';
        if (is_array($cn)) $cn = end($cn);
        $r['titular'] = trim(preg_replace('~:\d{11,14}$~', '', (string)$cn));
    }
    return $r;
}

/* ------------------------------------------------------------------ lado da estação (ticket_*) */

/** Atende uma ação ticket_* do app da estação e devolve o array de resposta (lança AsgTclErro). */
function asg_tcl_atender($acao, $corpo, $ipRemoto)
{
    $ticket = (string)($corpo['ticket'] ?? ($corpo['t'] ?? ''));
    if ($ticket === '' || strlen($ticket) > 200) throw new AsgTclErro('Link de assinatura inválido.', 'ticket', 400);
    $hash = hash('sha256', $ticket);
    $idxSonda = asg_tcl_sonda_indice($hash);                                  // pedido de teste?
    if (is_file($idxSonda)) return asg_tcl_sonda_atender($acao, $corpo, trim((string)file_get_contents($idxSonda)), $ipRemoto);
    $idx = asg_tcl_indice($hash);
    if (!is_file($idx)) throw new AsgTclErro('Este link de assinatura não existe mais. Clique em "Assinar" no sistema de novo.', 'ticket', 404);
    $id = trim((string)file_get_contents($idx));

    list($fh, $st) = asg_tcl_travar($id);
    try {
        if (!hash_equals((string)$st['ticket_hash'], $hash)) throw new AsgTclErro('Link de assinatura inválido.', 'ticket', 404);
        $ip = asg_tc_ip($ipRemoto);
        if (asg_tcl_prazos($st)) asg_tcl_gravar($fh, $st);

        if ($acao === 'ticket_abrir') {
            if (!empty($st['aberto_em'])) throw new AsgTclErro('Este link já foi usado. Clique em "Assinar" no sistema de novo.', 'usado', 409);
            asg_tcl_vivo($st);
            if (!asg_tcl_ip_aceito($st['ip'], $ip))
                throw new AsgTclErro('Este link foi gerado para outro computador (criado em ' . $st['ip'] . ', aberto de ' . $ip
                    . '). Clique em "Assinar" no sistema a partir deste computador.', 'outro_computador', 403);
            $st['aberto_em'] = time(); $st['ip_estacao'] = $ip; $st['estado'] = 'na_estacao'; $st['mensagem'] = '';
            asg_tcl_gravar($fh, $st);
            return ['ok' => true, 'pedido' => [
                'id' => $st['id'], 'sistema' => 'Atlas', 'titulo' => $st['orig'], 'usuario' => $st['usuario'],
                'nome' => $st['nome'], 'documentos' => [$st['orig']], 'opcoes' => $st['opcoes'],
                'minutos' => ASG_TCL_MIN_CONCLUIR]];
        }

        // demais ações: pedido aberto, mesmo IP que abriu
        if (empty($st['aberto_em'])) throw new AsgTclErro('O pedido ainda não foi aberto.', 'estado', 409);
        if ($ip !== $st['ip_estacao']) throw new AsgTclErro('Este pedido foi aberto em outro computador.', 'outro_computador', 403);

        switch ($acao) {
            case 'ticket_documento':
                asg_tcl_vivo($st);
                if ((int)($corpo['indice'] ?? 0) !== 0) throw new AsgTclErro('Documento inexistente.', 'documento', 404);
                $bin = @file_get_contents($st['src']);
                if ($bin === false || $bin === '') throw new AsgTclErro('O documento não está mais disponível. Envie o PDF de novo no sistema.', 'documento', 410);
                return ['ok' => true, 'nome' => $st['orig'], 'conteudo_base64' => base64_encode($bin)];

            case 'ticket_entregar':
                asg_tcl_vivo($st);
                $i = (int)($corpo['indice'] ?? 0);
                if ($i !== 0) throw new AsgTclErro('Documento inexistente.', 'documento', 404);
                if (!empty($corpo['erro'])) {
                    $st['entregas'][$i] = ['erro' => asg_tcl_corta((string)$corpo['erro'], 500)];
                } else {
                    $b64 = (string)($corpo['conteudo_base64'] ?? '');
                    if ($b64 === '' || strlen($b64) > ASG_TCL_MAX_BYTES * 4 / 3 + 16) throw new AsgTclErro('Arquivo assinado ausente ou grande demais.', 'documento', 413);
                    $bin = base64_decode($b64, true);
                    if ($bin === false) throw new AsgTclErro('Arquivo assinado inválido (base64).', 'documento', 400);
                    $info = asg_pdf_assinatura_info($bin);
                    if (!$info['ok']) throw new AsgTclErro('O arquivo devolvido não é um PDF assinado válido: ' . $info['erro'], 'documento', 422);
                    file_put_contents(asg_tcl_assinado_tmp($st['id'], $i), $bin);
                    $st['entregas'][$i] = ['ok' => true, 'titular' => $info['titular'], 'cpf' => $info['cpf'],
                                           'avisos' => array_values(array_map('strval', array_filter((array)($corpo['avisos'] ?? []))))];
                }
                $st['estado'] = 'assinando';
                asg_tcl_gravar($fh, $st);
                return ['ok' => true];

            case 'ticket_concluir':
                asg_tcl_vivo($st);
                $e = $st['entregas'][0] ?? null;
                if ($e && !empty($e['ok']) && is_file(asg_tcl_assinado_tmp($st['id'], 0)) && !empty($st['destino'])) {
                    // pedido de outro módulo: quem grava é o módulo
                    $tmpAss = asg_tcl_assinado_tmp($st['id'], 0);
                    try {
                        $rec = asg_tcl_gravar_modulo($st, (string)file_get_contents($tmpAss), $e);
                    } catch (Throwable $ex) {
                        $st['estado'] = 'erro'; $st['finalizado'] = true;
                        $st['mensagem'] = 'O documento foi assinado, mas não foi possível gravá-lo: ' . $ex->getMessage();
                        asg_tcl_gravar($fh, $st);
                        @unlink($idx);
                        throw new AsgTclErro($st['mensagem'], 'gravacao', 500);
                    }
                    @unlink($tmpAss); @unlink($st['src']);
                    $st['doc'] = $rec; $st['estado'] = 'concluido'; $st['finalizado'] = true; $st['mensagem'] = 'Documento assinado.';
                    asg_tcl_gravar($fh, $st);
                    @unlink($idx);
                    return ['ok' => true, 'mensagem' => 'Documento assinado e devolvido ao sistema.'];
                }
                if ($e && !empty($e['ok']) && is_file(asg_tcl_assinado_tmp($st['id'], 0))) {
                    $final = asg_dir_sig() . '/' . asg_nome_saida($st['orig']);
                    if (!@rename(asg_tcl_assinado_tmp($st['id'], 0), $final)) throw new AsgTclErro('Falha ao gravar o documento assinado.', 'erro', 500);
                    $rec = asg_registrar($st['orig'], $final, $e['titular'] ?: 'Titular do certificado', 'a3',
                                         $st['usuario'], $st['codigo'], (int)$st['nPag'], 'tcloud');
                    $rec['avisos'] = $e['avisos']; $rec['carimbo'] = false; $rec['ltv'] = false; $rec['act'] = '';
                    $st['doc'] = $rec; $st['estado'] = 'concluido'; $st['finalizado'] = true; $st['mensagem'] = 'Documento assinado.';
                    $tk = preg_replace('~[^a-f0-9]~', '', (string)$st['token']);
                    if ($tk !== '') { @unlink(asg_dir_tmp() . '/' . $tk . '.pdf'); @unlink(asg_dir_tmp() . '/' . $tk . '.nome'); }
                    asg_tcl_gravar($fh, $st);
                    @unlink($idx);
                    return ['ok' => true, 'mensagem' => 'Documento assinado e devolvido ao sistema.'];
                }
                $st['estado'] = 'erro'; $st['finalizado'] = true;
                $st['mensagem'] = 'O documento não foi assinado: ' . (($e['erro'] ?? '') ?: 'o assinador não devolveu o arquivo.');
                asg_tcl_gravar($fh, $st);
                @unlink($idx);
                return ['ok' => true, 'mensagem' => $st['mensagem']];

            case 'ticket_recusar':
                if (empty($st['finalizado'])) {
                    $motivo = trim((string)($corpo['motivo'] ?? ''));
                    $st['estado'] = 'recusado'; $st['finalizado'] = true;
                    $st['mensagem'] = 'A assinatura foi recusada no computador' . ($motivo !== '' ? ': ' . asg_tcl_corta($motivo, 300) : '.');
                    asg_tcl_gravar($fh, $st);
                }
                @unlink($idx);
                return ['ok' => true];
        }
        throw new AsgTclErro('Ação desconhecida.', 'acao', 404);
    } finally { asg_tcl_soltar($fh); }
}

/** Recusa ações em pedido já finalizado (cancelado, expirado, recusado…). */
function asg_tcl_vivo($st)
{
    if (empty($st['finalizado'])) return;
    $cod = ['cancelado' => 'cancelado', 'expirado' => 'expirado', 'recusado' => 'recusado', 'concluido' => 'concluido'][$st['estado']] ?? 'estado';
    throw new AsgTclErro($st['mensagem'] ?: 'Este pedido já foi encerrado.', $cod, 410);
}

/* ------------------------------------------------------------------ validação do PDF assinado */

/**
 * Confere o PDF devolvido pela estação e lê o titular do certificado.
 *  - a última /ByteRange precisa cobrir o arquivo inteiro (0 … fim), sem bytes fora da assinatura;
 *  - /Contents precisa ter um CMS (SignedData) legível;
 *  - titular = CN do certificado do assinante (ICP-Brasil: "NOME:CPF").
 */
function asg_pdf_assinatura_info($pdf)
{
    $r = ['ok' => false, 'erro' => '', 'titular' => '', 'cpf' => ''];
    if (strncmp($pdf, '%PDF', 4) !== 0) { $r['erro'] = 'não é PDF.'; return $r; }
    if (!preg_match_all('~/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]~', $pdf, $m, PREG_SET_ORDER)) { $r['erro'] = 'sem assinatura.'; return $r; }
    $ult = end($m);
    list(, $a, $b, $c, $d) = array_map('intval', $ult);
    $tam = strlen($pdf);
    if ($a !== 0 || $c + $d !== $tam || $b <= 0 || $c <= $b) { $r['erro'] = 'a assinatura não cobre o arquivo inteiro.'; return $r; }

    // lê titulares do fim para o começo (a última pode ser um carimbo de documento, sem titular pessoa)
    for ($k = count($m) - 1; $k >= 0 && $r['titular'] === ''; $k--) {
        list(, $a, $b, $c, $d) = array_map('intval', $m[$k]);
        if ($c <= $b || $c > $tam) continue;
        $hex = trim(substr($pdf, $b, $c - $b), "<> \r\n\t");
        $der = asg_der_recortar(@hex2bin(preg_replace('~[^0-9A-Fa-f]~', '', $hex)));
        if ($der === '') continue;
        $t = asg_cms_titular($der);
        if ($t) { $r['titular'] = $t['nome']; $r['cpf'] = $t['cpf']; }
    }
    $r['ok'] = true;
    return $r;
}

/** Corta o preenchimento de zeros depois do DER (o /Contents tem espaço reservado). */
function asg_der_recortar($bin)
{
    if (!is_string($bin) || strlen($bin) < 4 || ord($bin[0]) !== 0x30) return '';
    $l = ord($bin[1]);
    if ($l < 0x80) $len = 2 + $l;
    else {
        $n = $l & 0x7F; if ($n < 1 || $n > 4) return '';
        $v = 0; for ($i = 0; $i < $n; $i++) $v = ($v << 8) | ord($bin[2 + $i]);
        $len = 2 + $n + $v;
    }
    return $len <= strlen($bin) ? substr($bin, 0, $len) : '';
}

/** Titular do certificado do assinante dentro de um CMS/PKCS#7 em DER. */
function asg_cms_titular($der)
{
    if (!function_exists('openssl_pkcs7_read')) return null;
    $pem = "-----BEGIN PKCS7-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PKCS7-----\n";
    $certs = [];
    if (!@openssl_pkcs7_read($pem, $certs) || !$certs) return null;
    $melhor = null;
    foreach ($certs as $c) {
        $x = @openssl_x509_parse($c);
        if (!$x) continue;
        $ca = isset($x['extensions']['basicConstraints']) && stripos($x['extensions']['basicConstraints'], 'CA:TRUE') !== false;
        $tsa = isset($x['extensions']['extendedKeyUsage']) && stripos($x['extensions']['extendedKeyUsage'], 'Time Stamping') !== false;
        if ($ca || $tsa) continue;
        $cn = $x['subject']['CN'] ?? '';
        if (is_array($cn)) $cn = end($cn);
        $cn = (string)$cn;
        if ($cn === '') continue;
        $cpf = '';
        if (preg_match('~^(.*?):(\d{11}|\d{14})$~', $cn, $mm)) { $cn = $mm[1]; $cpf = $mm[2]; }
        $item = ['nome' => trim($cn), 'cpf' => $cpf];
        if ($cpf !== '') return $item;          // e-CPF/e-CNPJ ICP-Brasil: é o assinante
        if (!$melhor) $melhor = $item;
    }
    return $melhor;
}
