<?php
/* ======================================================================
 * ATLAS • Guarda de rotas — 1.0.2
 * ----------------------------------------------------------------------
 * Carregado no começo de toda requisição do Atlas (pela auditoria, que já
 * está em todos os módulos, e pela primeira linha dos scripts avulsos).
 *
 *  1. Login obrigatório em todas as rotas, menos as de entrada (login,
 *     2FA) e as que têm credencial própria (API da O.S., estações TCloud).
 *  2. Pedidos de fora do sistema são recusados: tudo o que grava (POST,
 *     PUT, DELETE…) precisa do token da sessão, que só as telas do Atlas
 *     recebem, e precisa vir da mesma origem. No modo estrito, consultas
 *     feitas por ferramentas sem navegador também precisam do token.
 *  3. Moldura: as telas abrem dentro de um quadro, e a barra de endereço
 *     mostra só o endereço inicial do sistema.
 *  4. As telas recebem o protecao.js, que coloca o token nos pedidos e
 *     esconde o endereço dos links no canto da tela.
 *
 * Configuração: página do suporte, seguranca/suporte.php (ou seguranca/
 * config.local.php, que não vai para o git). Desligar na hora: crie o
 * arquivo seguranca/dados/DESLIGAR.
 * ====================================================================== */

if (defined('ATLAS_GUARDA')) {
    return;
}
define('ATLAS_GUARDA', '1.0.2');

if (PHP_SAPI === 'cli' || is_file(__DIR__ . '/dados/DESLIGAR')) {
    return;
}

(function () {
    set_error_handler(function () { return true; });   // nenhum aviso do guarda pode sair na página
    try {
        atlas_guarda__executar();
    } catch (Throwable $e) {
        error_log('[atlas-guarda] ' . $e->getMessage());
    }
    restore_error_handler();
})();

/* ---------------------------------------------------------------------- */

function atlas_guarda_config(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = [
        'modo'          => 'bloquear',  // bloquear | observar (só registra) | desligado
        'estrito'       => true,        // até GET sem cabeçalhos de navegador precisa do token
        'moldura'       => true,
        'ocultar_links' => true,
        'livres'        => [],          // rotas extras sem nenhuma verificação (ex.: 'modulo/api/*')
        'entrada'       => [],          // rotas extras que dispensam login (continuam exigindo mesma origem)
        'sem_moldura'   => [],          // rotas extras que nunca abrem na moldura
    ];
    foreach ([__DIR__ . '/config.php', __DIR__ . '/config.local.php'] as $f) {
        if (is_file($f)) {
            $v = include $f;
            if (is_array($v)) {
                $c = array_merge($c, $v);
            }
        }
    }
    // escolhas feitas na página do suporte (seguranca/suporte.php) valem por último
    $p = atlas_guarda_config_painel();
    foreach (['modo', 'estrito', 'moldura', 'ocultar_links'] as $k) {
        if (array_key_exists($k, $p)) {
            $c[$k] = $p[$k];
        }
    }
    return $c;
}

function atlas_guarda_config_painel(): array
{
    $f = __DIR__ . '/dados/config_painel.php';
    if (!is_file($f)) {
        return [];
    }
    $t = (string) @file_get_contents($f);
    $p = strpos($t, "\n");
    $j = $p === false ? null : json_decode(substr($t, $p + 1), true);
    return is_array($j) ? $j : [];
}

function atlas_guarda_salvar_config_painel(array $v): bool
{
    $atual = atlas_guarda_config_painel();
    foreach (['modo', 'estrito', 'moldura', 'ocultar_links'] as $k) {
        if (array_key_exists($k, $v)) {
            $atual[$k] = $v[$k];
        }
    }
    $atual['alterado_em'] = atlas_guarda__agora('Y-m-d H:i:s');
    return @file_put_contents(atlas_guarda_dados() . '/config_painel.php',
        "<?php http_response_code(404); exit; ?>\n" . json_encode($atual, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

/** Bloqueios registrados nos últimos $dias (mais recentes primeiro). */
function atlas_guarda_bloqueios(int $dias = 30, int $limite = 5000): array
{
    $lim = atlas_guarda__agora('Y-m-d H:i:s', time() - $dias * 86400);
    $out = [];
    $arqs = glob(atlas_guarda_dados() . '/bloqueios-*.php') ?: [];
    rsort($arqs);
    foreach ($arqs as $f) {
        if (basename($f) < 'bloqueios-' . substr($lim, 0, 7)) {
            break;
        }
        $linhas = @file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        for ($i = count($linhas) - 1; $i >= 1; $i--) {
            $e = json_decode($linhas[$i], true);
            if (!is_array($e) || ($e['em'] ?? '') < $lim) {
                continue;
            }
            $out[] = $e;
            if (count($out) >= $limite) {
                return $out;
            }
        }
    }
    return $out;
}

/** Data/hora no fuso do cartório (o mesmo da auditoria), independente do fuso do PHP. */
function atlas_guarda__agora(string $fmt, ?int $ts = null): string
{
    $d = new DateTime('@' . ($ts ?? time()));
    $d->setTimezone(new DateTimeZone('America/Fortaleza'));
    return $d->format($fmt);
}

/** Pasta de dados (segredo e registro de bloqueios), protegida contra acesso pela web. */
function atlas_guarda_dados(): string
{
    $d = __DIR__ . '/dados';
    if (!is_dir($d)) {
        @mkdir($d, 0770, true);
    }
    if (!is_file($d . '/.htaccess')) {
        @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (!is_file($d . '/index.html')) {
        @file_put_contents($d . '/index.html', '');
    }
    return $d;
}

/** Segredo do servidor (gerado no primeiro uso). Fica num .php que encerra na 1ª linha. */
function atlas_guarda_segredo(): string
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $f = atlas_guarda_dados() . '/segredo.php';
    $t = is_file($f) ? (string) @file_get_contents($f) : '';
    if (preg_match('/^([a-f0-9]{64})$/m', $t, $m)) {
        return $s = $m[1];
    }
    $novo = bin2hex(random_bytes(32));
    if (@file_put_contents($f, "<?php http_response_code(404); exit; ?>\n" . $novo . "\n", LOCK_EX) !== false) {
        return $s = $novo;
    }
    // Sem permissão de gravar em seguranca/dados: usa um valor fixo desta instalação (senão cada
    // requisição teria um segredo diferente e todo envio seria recusado) e avisa no log do PHP.
    error_log('[atlas-guarda] Não foi possível gravar ' . $f . ' — dê permissão de escrita à pasta seguranca/dados.');
    return $s = hash('sha256', 'atlas-guarda-reserva|' . __DIR__ . '|' . php_uname('n') . '|' . (string) @filemtime(__FILE__));
}

/** O endereço aponta para este próprio servidor? (para mandar o cabeçalho interno só para ele) */
function atlas_guarda_url_local(string $url): bool
{
    $h = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
    if ($h === '') {
        return false;
    }
    $locais = ['localhost', '127.0.0.1', '::1', strtolower((string) ($_SERVER['SERVER_ADDR'] ?? '')), strtolower((string) ($_SERVER['LOCAL_ADDR'] ?? ''))];
    if (in_array($h, $locais, true) || strpos($h, '127.') === 0) {
        return true;
    }
    if (!filter_var($h, FILTER_VALIDATE_IP) && strlen($h) < 200) {
        $ip = @gethostbyname($h);   // nome da máquina na rede: vale se resolver para este servidor
        return $ip !== $h && (in_array($ip, $locais, true) || strpos($ip, '127.') === 0);
    }
    return false;
}

/** Token da sessão atual (vazio se ainda não há sessão). */
function atlas_guarda_token(?string $sid = null): string
{
    if ($sid === null) {
        $sid = session_status() === PHP_SESSION_ACTIVE ? session_id() : (string) ($_COOKIE[session_name()] ?? '');
    }
    if ($sid === '' || !preg_match('/^[A-Za-z0-9,-]{16,128}$/', $sid)) {
        return '';
    }
    return substr(hash_hmac('sha256', 'atlas-token|' . $sid, atlas_guarda_segredo()), 0, 48);
}

/**
 * Cabeçalho para o próprio servidor chamar páginas do Atlas (ex.: gerar o PDF de um ofício).
 * Uso: $headers[] = atlas_guarda_cabecalho_interno();   Vale por poucos minutos.
 */
function atlas_guarda_cabecalho_interno(): string
{
    return 'X-Atlas-Interno: ' . atlas_guarda__assinatura_interna((int) floor(time() / 300));
}

function atlas_guarda__assinatura_interna(int $janela): string
{
    return substr(hash_hmac('sha256', 'atlas-interno|' . $janela, atlas_guarda_segredo()), 0, 48);
}

function atlas_guarda__interno_valido(): bool
{
    $h = (string) ($_SERVER['HTTP_X_ATLAS_INTERNO'] ?? '');
    if ($h === '') {
        return false;
    }
    $j = (int) floor(time() / 300);
    return hash_equals(atlas_guarda__assinatura_interna($j), $h) || hash_equals(atlas_guarda__assinatura_interna($j - 1), $h);
}

/**
 * Login da sessão. Só vale a chave "username" de primeiro nível — a mesma que as páginas
 * conferem. (O login ainda aguardando o código 2FA fica em tfa_pending e NÃO conta.)
 */
function atlas_guarda__usuario(): ?string
{
    $de = function ($s) {
        return is_array($s) && isset($s['username']) && is_string($s['username']) && $s['username'] !== '' ? $s['username'] : null;
    };
    if (session_status() === PHP_SESSION_ACTIVE) {
        return $de($_SESSION ?? null);
    }
    $sid = (string) ($_COOKIE[session_name()] ?? '');
    if (!preg_match('/^[A-Za-z0-9,-]{16,128}$/', $sid)) {
        return null;
    }
    // Caminho normal: o próprio PHP lê a sessão (espera a gravação de outra requisição terminar,
    // entende qualquer formato/handler) e fecha na hora. A página abre a sessão dela depois.
    if (!headers_sent()) {
        $limitador = session_cache_limiter();
        session_cache_limiter('');   // não mexe nos cabeçalhos de cache da resposta
        $ok = @session_start(['read_and_close' => true]);
        session_cache_limiter($limitador);
        $u = $ok ? $de($_SESSION ?? null) : null;
        unset($_SESSION);            // a página encontra o mesmo estado de antes (sem $_SESSION)
        return $u;
    }
    // Guarda carregado depois de a página já ter impresso algo: lê o arquivo da sessão e decodifica.
    if (strtolower((string) ini_get('session.save_handler')) !== 'files') {
        return null;
    }
    $dir = (string) session_save_path();
    if (strpos($dir, ';') !== false) {
        $dir = substr($dir, strrpos($dir, ';') + 1);
    }
    $f = rtrim($dir !== '' ? $dir : sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sid;
    $dados = is_file($f) ? (string) @file_get_contents($f, false, null, 0, 1048576) : '';
    return $dados === '' ? null : $de(atlas_guarda__decodificar_sessao($dados));
}

/** Decodifica o arquivo de sessão (formatos "php" e "php_serialize"). Qualquer dúvida: null. */
function atlas_guarda__decodificar_sessao(string $d): ?array
{
    if (strtolower((string) ini_get('session.serialize_handler')) === 'php_serialize') {
        $v = @unserialize($d, ['allowed_classes' => false]);
        return is_array($v) ? $v : null;
    }
    $out = [];
    $p = 0;
    $n = strlen($d);
    while ($p < $n) {
        $barra = strpos($d, '|', $p);
        if ($barra === false) {
            return null;
        }
        $k = substr($d, $p, $barra - $p);
        $p = $barra + 1;
        $v = @unserialize(substr($d, $p), ['allowed_classes' => false]);
        $s = serialize($v);
        if (substr($d, $p, strlen($s)) !== $s) {
            return null;   // não reconheceu o valor com segurança
        }
        $out[$k] = $v;
        $p += strlen($s);
    }
    return $out;
}

/** Caminho do script relativo à raiz do Atlas (ex.: "os/index.php") e endereço-base ("/atlas/"). */
function atlas_guarda__rota(): ?array
{
    $raiz = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/';
    $sf = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    $script = str_replace('\\', '/', (string) (realpath($sf) ?: $sf));
    $win = PHP_OS_FAMILY === 'Windows';
    $comeca = $win ? strncasecmp($script, $raiz, strlen($raiz)) === 0 : strncmp($script, $raiz, strlen($raiz)) === 0;
    if (!$comeca) {
        return null;
    }
    $rel = substr($script, strlen($raiz));
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $fim = substr($sn, -strlen($rel));
    $base = ($fim !== false && strcasecmp($fim, $rel) === 0) ? substr($sn, 0, strlen($sn) - strlen($rel)) : '/';
    if ($base === '' || substr($base, -1) !== '/') {
        $base .= '/';
    }
    return [$rel, $base];
}

function atlas_guarda__casa(string $rel, array $padroes): bool
{
    foreach ($padroes as $p) {
        if (fnmatch($p, $rel, FNM_CASEFOLD)) {
            return true;
        }
    }
    return false;
}

function atlas_guarda__mesmo_host(string $url): bool
{
    $h = parse_url($url, PHP_URL_HOST);
    $p = parse_url($url, PHP_URL_PORT);
    if (!$h) {
        return false;
    }
    $alvo = strtolower($h . ($p ? ':' . $p : ''));
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    // porta padrão pode vir ou não no Host
    return $alvo === $host || preg_replace('/:(80|443)$/', '', $alvo) === preg_replace('/:(80|443)$/', '', $host);
}

/* ---------------------------------------------------------------------- */

function atlas_guarda__executar(): void
{
    $cfg = atlas_guarda_config();
    if (($cfg['modo'] ?? 'bloquear') === 'desligado') {
        return;
    }
    $r = atlas_guarda__rota();
    if ($r === null) {
        return;
    }
    [$rel, $base] = $r;
    $GLOBALS['__atlas_guarda'] = ['rel' => $rel, 'base' => $base, 'motivo' => null];

    /* 1) rotas com credencial própria: não mexe em nada */
    $livres = array_merge([
        'os/api/*',                    // API da O.S. (token do sistema integrador)
        'arquivamento/api/scanner.php',// TCloud Scanner (token do pedido)
        'signum/tcloud_estacao.php',   // TCloud Assinador, modo local (ticket)
    ], (array) $cfg['livres']);
    if (atlas_guarda__casa($rel, $livres)) {
        $GLOBALS['__atlas_guarda']['livre'] = true;
        return;
    }

    $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $site = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    $modo = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? ''));
    $dest = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $ajax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $grava = !in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true);
    $interno = atlas_guarda__interno_valido();

    // o token nunca chega aos scripts (alguns montam SQL com todos os campos recebidos)
    $tokenRecebido = (string) ($_SERVER['HTTP_X_ATLAS_TOKEN'] ?? ($_POST['_atlas_token'] ?? ($_GET['_atlas_token'] ?? '')));
    unset($_POST['_atlas_token'], $_GET['_atlas_token'], $_REQUEST['_atlas_token']);
    $semMoldura = isset($_GET['_atlas_sem_moldura']);
    unset($_GET['_atlas_sem_moldura'], $_REQUEST['_atlas_sem_moldura']);

    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');   // outro site não consegue embutir o Atlas num quadro
    }
    if ($interno) {
        return;   // o próprio servidor pedindo uma página (gerar PDF etc.)
    }

    $entrada = atlas_guarda__casa($rel, array_merge([
        'login.php', 'check_login.php', 'logout.php', 'verificar-2fa.php', '2fa/*', 'oauth2callback.php',
        'seguranca/suporte.php',   // página da equipe de suporte: tem login próprio
    ], (array) $cfg['entrada']));

    /* 2) de outro site: recusa (o retorno do login do Google é a única exceção) */
    if ($site === 'cross-site' && $rel !== 'oauth2callback.php') {
        atlas_guarda__negar('origem', 'Pedido vindo de outro site.', $base);
        return;
    }
    if ($origin !== '' && $origin !== 'null' && !atlas_guarda__mesmo_host($origin)) {
        atlas_guarda__negar('origem', 'Pedido vindo de outro endereço.', $base);
        return;
    }
    if ($origin === 'null' && $grava) {
        atlas_guarda__negar('origem', 'Pedido de origem desconhecida.', $base);
        return;
    }
    if ($grava && $origin === '' && ($referer === '' || !atlas_guarda__mesmo_host($referer))) {
        // navegadores sempre mandam Origin ou Referer ao enviar dados; ferramentas externas não
        atlas_guarda__negar('origem', 'Envio de dados sem origem conhecida.', $base);
        return;
    }

    /* 3) login */
    $usuario = $entrada ? null : atlas_guarda__usuario();
    if (!$entrada && $usuario === null) {
        atlas_guarda__negar('sessao', 'Sua sessão terminou. Entre novamente no sistema.', $base);
        return;
    }
    $GLOBALS['__atlas_guarda']['usuario'] = $usuario;

    /* 4) token: obrigatório para tudo o que grava. No modo estrito, também para consultas feitas
          sem navegador (ferramentas não mandam os cabeçalhos Sec-Fetch que o navegador sempre manda).
          Consultas do próprio navegador, a partir das telas, seguem só com login e mesma origem —
          isso inclui páginas estáticas como o visualizador de PDF, que não recebem o token. */
    if (!$entrada) {
        $semNavegador = $site === '' && $modo === '';
        $exige = $grava || (!empty($cfg['estrito']) && $semNavegador);
        if ($exige) {
            $esperado = atlas_guarda_token();
            if ($esperado === '' || $tokenRecebido === '' || !hash_equals($esperado, $tokenRecebido)) {
                atlas_guarda__negar('token', 'Pedido recusado: use as telas do sistema.', $base);
                return;
            }
        }
    }

    /* 5) moldura: navegação principal abre a página dentro do quadro */
    if (!empty($cfg['moldura']) && !$semMoldura && !headers_sent() && atlas_guarda__nada_impresso() && $metodo === 'GET' && $dest === 'document' && $modo === 'navigate' && !$ajax
        && !atlas_guarda__casa($rel, array_merge(['oauth2callback.php', 'logout.php', 'seguranca/suporte.php'], (array) $cfg['sem_moldura']))
        && !preg_match('/pdf|imprim|impress|print|download|baixar|export|xls|csv|zip|recibo|comprovante|etiqueta|capa|espelho|carne|boleto|guia_de_impressao|view[-_]?oficio|ver[-_]oficio|visualizar_arquivo|^arquivo\.php|^anexo|^gerar_|_pdf|^api|ajax|json|ping/i', basename($rel))) {
        $GLOBALS['__atlas_guarda']['moldura'] = true;
        atlas_guarda__moldura($base);
        exit;
    }

    /* 6) telas: recebem o protecao.js — só quando o navegador está abrindo uma página (não em
          HTML buscado por script, como modelos de documento, que poderiam guardar a tag) */
    $abrindoTela = in_array($dest, ['document', 'iframe', 'frame'], true)
        || ($dest === '' && !$ajax && strpos(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'text/html') !== false);
    if ($abrindoTela) {
        atlas_guarda__injetar($base, !empty($cfg['ocultar_links']));
    }
}

/** A página ainda não imprimiu nada (em nenhum nível de buffer)? */
function atlas_guarda__nada_impresso(): bool
{
    foreach (ob_get_status(true) as $nivel) {
        if (!empty($nivel['buffer_used'])) {
            return false;
        }
    }
    return true;
}

/** Recusa (ou só registra, no modo observar). */
function atlas_guarda__negar(string $motivo, string $mensagem, string $base): void
{
    $cfg = atlas_guarda_config();
    $GLOBALS['__atlas_guarda']['motivo'] = $motivo;
    atlas_guarda__registrar($motivo, ($cfg['modo'] ?? '') === 'observar');
    if (($cfg['modo'] ?? '') === 'observar') {
        return;
    }
    if (function_exists('atlas_aud_contexto') && isset($GLOBALS['__atlas_aud'])) {
        $GLOBALS['__atlas_aud']['guarda'] = $motivo;
    }

    $dest = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
    $navega = in_array($dest, ['document', 'iframe', 'frame'], true)
        || ($dest === '' && strpos(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'text/html') !== false
            && strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest');
    $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if ($motivo === 'sessao' && $navega && $metodo === 'GET' && !headers_sent()) {
        header('Location: ' . $base . 'login.php', true, 302);
        exit;
    }
    $status = $motivo === 'sessao' ? 401 : 403;
    if (!headers_sent()) {
        http_response_code($status);
        header('X-Atlas-Guarda: ' . $motivo);
        header('Cache-Control: no-store');
    }
    if ($navega) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        $m = htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8');
        $b = htmlspecialchars($base, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acesso recusado</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font:500 15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#e2e8f0}'
            . 'div{max-width:440px;padding:28px;border:1px solid #334155;border-radius:16px;background:#111c33;text-align:center}h1{font-size:19px;margin:0 0 8px}p{margin:0 0 18px;color:#94a3b8}'
            . 'a{display:inline-block;padding:10px 18px;border-radius:10px;background:#6366f1;color:#fff;text-decoration:none;font-weight:600}</style></head>'
            . '<body><div><h1>Acesso recusado</h1><p>' . $m . '</p><a href="' . $b . '" target="_top">Abrir o sistema</a></div></body></html>';
    } else {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok' => false, 'success' => false, 'sucesso' => false, 'status' => 'erro',
            'erro' => $mensagem, 'error' => $mensagem, 'message' => $mensagem, 'mensagem' => $mensagem, 'msg' => $mensagem,
            'atlas_guarda' => $motivo,
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/** Registro dos bloqueios (seguranca/dados/bloqueios-AAAA-MM.php, uma linha JSON por evento). */
function atlas_guarda__registrar(string $motivo, bool $soObservado): void
{
    $g = $GLOBALS['__atlas_guarda'] ?? [];
    $linha = json_encode([
        'em'      => atlas_guarda__agora('Y-m-d H:i:s'),
        'motivo'  => $motivo,
        'acao'    => $soObservado ? 'observado' : 'bloqueado',
        'usuario' => $g['usuario'] ?? atlas_guarda__usuario(),
        'ip'      => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'metodo'  => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'rota'    => $g['rel'] ?? '',
        'query'   => substr((string) ($_SERVER['QUERY_STRING'] ?? ''), 0, 300),
        'origem'  => substr((string) ($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 200),
        'agente'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
        'fetch'   => trim(($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '-') . ' ' . ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '-') . ' ' . ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '-')),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if (!is_string($linha) || $linha === '') {
        return;
    }
    $f = atlas_guarda_dados() . '/bloqueios-' . atlas_guarda__agora('Y-m') . '.php';
    if (is_file($f) && @filesize($f) > 10485760) {
        return;   // limite de 10 MB por mês: pedidos em massa não enchem o disco
    }
    if (!is_file($f)) {
        @file_put_contents($f, "<?php http_response_code(404); exit; ?>\n", LOCK_EX);
    }
    @file_put_contents($f, $linha . "\n", FILE_APPEND | LOCK_EX);
}

/** Página-moldura: a tela pedida abre num quadro e a barra de endereço fica só com o endereço do sistema. */
function atlas_guarda__moldura(string $base): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $alvo = (string) ($_SERVER['REQUEST_URI'] ?? $base);
    $j = function ($v) { return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); };
    $icone = is_file(dirname(__DIR__) . '/style/img/favicon.png') ? $base . 'style/img/favicon.png' : '';
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Atlas</title>
<?php if ($icone): ?><link rel="icon" href="<?= htmlspecialchars($icone, ENT_QUOTES) ?>"><?php endif; ?>
<style>
html,body{margin:0;height:100%;overflow:hidden;background:#0b1220}
#atlas-quadro{position:fixed;inset:0;width:100%;height:100%;border:0;display:block;background:#fff}
#atlas-aviso{position:fixed;inset:0;display:none;place-items:center;font:500 15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#e2e8f0;z-index:2}
#atlas-aviso div{max-width:420px;padding:26px;border:1px solid #334155;border-radius:16px;background:#111c33;text-align:center}
#atlas-aviso p{color:#94a3b8;margin:6px 0 16px}
#atlas-aviso a{display:inline-block;padding:9px 16px;border-radius:10px;background:#6366f1;color:#fff;text-decoration:none;font-weight:600;cursor:pointer}
</style>
<script>
(function(){
  var BASE = <?= $j($base) ?>, ALVO = <?= $j($alvo) ?>, CHAVE = 'atlas_moldura_atual';
  /* a barra de endereço mostra o endereço do sistema sem a barra final: http://servidor/atlas */
  var LIMPO = BASE.length > 1 ? BASE.replace(/\/+$/, '') : BASE;
  /* janelas abertas por script (impressões etc.) e quadros dentro de quadros: abre a página direto */
  if (window.opener || window.top !== window) {
    location.replace(ALVO + (ALVO.indexOf('?') < 0 ? '?' : '&') + '_atlas_sem_moldura=1');
    return;
  }
  /* F5 no endereço do sistema volta para a última tela aberta */
  try {
    var ultimo = sessionStorage.getItem(CHAVE);
    var raiz = ALVO.replace(/[?#].*$/, '');
    if (ultimo && (raiz === BASE || raiz === LIMPO || raiz === BASE + 'index.php') && ALVO.indexOf('?') < 0) ALVO = ultimo;
  } catch (e) {}
  window.__atlasMolduraAlvo = ALVO;
  try { history.replaceState(null, '', LIMPO); } catch (e) {}
})();
</script>
</head>
<body>
<iframe id="atlas-quadro" title="Atlas" allow="clipboard-read; clipboard-write; camera; microphone; fullscreen; geolocation; usb; serial; hid" allowfullscreen></iframe>
<div id="atlas-aviso" role="status"><div><strong>Download iniciado</strong><p>Se um arquivo foi baixado, confira a pasta de downloads do navegador.</p><a id="atlas-voltar">Voltar ao sistema</a></div></div>
<script>
(function(){
  if (!window.__atlasMolduraAlvo) return;
  var BASE = <?= $j($base) ?>, CHAVE = 'atlas_moldura_atual';
  var q = document.getElementById('atlas-quadro'), aviso = document.getElementById('atlas-aviso'), carregou = false;
  q.addEventListener('load', function(){
    carregou = true; aviso.style.display = 'none';
    try {
      var w = q.contentWindow, d = q.contentDocument;
      if (w.location.href === 'about:blank') return;
      document.title = d.title || 'Atlas';
      var u = w.location.pathname + w.location.search;
      /* guarda a tela para o F5 — nunca endereços que executam ações (não podem rodar de novo) */
      var nome = (w.location.pathname.split('/').pop() || '').toLowerCase();
      if (!/^(login|logout|check_login|verificar-2fa)\.php$/.test(nome)
          && !/(excluir|delete|remov|apagar|salvar|save|update|atualizar|alterar|cancel|estorn|desfaz|liberar|aprovar|enviar|gerar|baixar|marcar|mover|restaur|status|confirm|assinar|finaliz|fechar|abrir_caixa|lancar|registrar|cadastrar_|insert|add_|novo_|criar_)/.test(nome)
          && w.document.contentType === 'text/html') sessionStorage.setItem(CHAVE, u);
      else if (/^(login|logout)\.php$/.test(nome)) sessionStorage.removeItem(CHAVE);
      var ic = d.querySelector('link[rel~="icon"]');
      if (ic) { var l = document.querySelector('link[rel~="icon"]') || document.head.appendChild(document.createElement('link')); l.rel = 'icon'; l.href = ic.href; }
      try { history.replaceState(null, '', BASE.length > 1 ? BASE.replace(/\/+$/, '') : BASE); } catch (e) {}
    } catch (e) {}
  });
  q.src = window.__atlasMolduraAlvo;
  /* se o endereço só baixou um arquivo, o quadro fica vazio: avisa e oferece voltar */
  setTimeout(function(){
    try { if (!carregou && q.contentWindow.location.href === 'about:blank') aviso.style.display = 'grid'; } catch (e) {}
  }, 6000);
  document.getElementById('atlas-voltar').addEventListener('click', function(){
    var u = BASE; try { u = sessionStorage.getItem(CHAVE) || BASE; } catch (e) {}
    aviso.style.display = 'none'; q.src = u;
  });
})();
</script>
</body>
</html>
<?php
}

/** Coloca o protecao.js logo no começo do <head> das telas (sem acumular respostas grandes na memória). */
/**
 * Tag com a configuração e o protecao.js. As telas já recebem automaticamente; o menu também
 * imprime (reserva para telas que montam o <head> de um jeito que o guarda não reconhece).
 */
function atlas_guarda_tag(): string
{
    $g = $GLOBALS['__atlas_guarda'] ?? null;
    if (!$g || ($g['livre'] ?? false)) {
        return '';
    }
    $cfg = atlas_guarda_config();
    $cfgJs = json_encode(['t' => atlas_guarda_token(), 'b' => $g['base'], 'l' => !empty($cfg['ocultar_links']) ? 1 : 0], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    $v = @filemtime(__DIR__ . '/protecao.js') ?: 1;
    return '<script>window.__ATLAS_G=' . $cfgJs . ';</script><script src="' . htmlspecialchars($g['base'], ENT_QUOTES) . 'seguranca/protecao.js?v=' . $v . '"></script>';
}

function atlas_guarda__injetar(string $base, bool $ocultarLinks): void
{
    // Transmissão contínua (o buffer atrasaria o envio): fica de fora.
    $src = (string) @file_get_contents((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''), false, null, 0, 600000);
    if ($src === '' || preg_match('/\b(ob_implicit_flush|flush)\s*\(|text\/event-stream/i', $src)) {
        return;
    }
    // Arquivos (PDF, planilhas, downloads) passam direto: o tipo da resposta é conferido no primeiro pedaço.
    // Script que lê o buffer padrão sem abrir o seu (ob_get_clean sem ob_start): buffer inteiro, sem pedaços.
    $pedaco = (preg_match('/\bob_(get_clean|get_contents|get_flush|get_length)\s*\(/i', $src) && !preg_match('/\bob_start\s*\(/i', $src)) ? 0 : 32768;
    $tag = atlas_guarda_tag();

    $estado = ['decidido' => false, 'acumulado' => ''];
    $decidir = function (string $buf, bool $final) use (&$estado, $tag): ?string {
        // Content-Type já definido e não-HTML, ou tamanho fixo: não mexe
        foreach (headers_list() as $h) {
            $hl = strtolower($h);
            if (strpos($hl, 'content-type:') === 0 && strpos($hl, 'text/html') === false) {
                return $buf;
            }
            if (strpos($hl, 'content-length:') === 0 || strpos($hl, 'content-encoding:') === 0) {
                return $buf;
            }
        }
        $ini = ltrim(substr($buf, 0, 512), "\xEF\xBB\xBF \t\r\n");
        if ($ini === '') {
            return $final ? $buf : null;
        }
        if ($ini[0] !== '<') {
            return $buf;   // JSON, texto, binário
        }
        if (preg_match('/<head\b[^>]*>/i', $buf, $m, PREG_OFFSET_CAPTURE)) {
            $p = $m[0][1] + strlen($m[0][0]);
            return substr($buf, 0, $p) . $tag . substr($buf, $p);
        }
        if (preg_match('/<body\b[^>]*>/i', $buf, $m, PREG_OFFSET_CAPTURE)) {
            $p = $m[0][1] + strlen($m[0][0]);
            return substr($buf, 0, $p) . $tag . substr($buf, $p);
        }
        if (!$final && strlen($buf) < 262144) {
            return null;   // ainda não deu para decidir: espera mais um pedaço
        }
        // fragmento HTML com formulário ou script (ex.: tela carregada sem <head>): coloca no começo
        if (preg_match('/<(form|script)\b/i', $buf)) {
            if (preg_match('/^\s*<!doctype[^>]*>/i', $buf, $m)) {
                return $m[0] . $tag . substr($buf, strlen($m[0]));
            }
            return $tag . $buf;
        }
        return $buf;
    };
    ob_start(function ($buf, $fase) use (&$estado, $decidir) {
        if ($estado['decidido']) {
            return $buf;
        }
        if ($fase & PHP_OUTPUT_HANDLER_CLEAN) {   // a página limpou a saída (ob_clean): descarta e continua esperando
            $estado['acumulado'] = '';
            return '';
        }
        $estado['acumulado'] .= $buf;
        $final = ($fase & PHP_OUTPUT_HANDLER_FINAL) !== 0;
        $r = $decidir($estado['acumulado'], $final);
        if ($r === null) {
            return '';
        }
        $estado['decidido'] = true;
        $estado['acumulado'] = '';
        return $r;
    }, $pedaco);
}
