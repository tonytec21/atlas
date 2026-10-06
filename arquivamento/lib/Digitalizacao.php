<?php
/**
 * Atlas · Arquivamento Digital
 * Digitalização direta do scanner (TWAIN) pelo TCloud Scanner.
 *
 * O navegador não fala com TWAIN — quem fala é o TCloud Scanner instalado na
 * estação. O caminho entre os dois passa pelo próprio Atlas, no mesmo modelo
 * do modo local do TCloud Assinador:
 *
 *   1. A tela de cadastro pede um "pedido de digitalização" (api/digitalizacao.php).
 *      O servidor gera um token aleatório de 256 bits, válido por algumas horas.
 *   2. O navegador abre o link tcloudscan://digitalizar/?u=<endpoint>&t=<token>.
 *      O Windows entrega o link ao TCloud Scanner.
 *   3. O TCloud Scanner digitaliza pelo driver TWAIN e envia cada página para
 *      api/scanner.php, autenticando-se só com o token (a estação não tem a
 *      sessão do usuário).
 *   4. A tela de cadastro acompanha o pedido, mostra as páginas conforme chegam,
 *      deixa girar/excluir/reordenar e monta o PDF no navegador com a pdf-lib.
 *      O PDF entra na fila de anexos como se o usuário o tivesse escolhido.
 *
 * Por que não um serviço em http://127.0.0.1 na estação: o Chrome 142+ (Local
 * Network Access) bloqueia página servida por IP de rede chamando o loopback,
 * e a permissão só pode ser pedida por página HTTPS. O Atlas roda em HTTP na
 * maioria das serventias. O link de protocolo não tem essa restrição.
 *
 * Este arquivo NÃO depende de sessão: é usado tanto pela API do navegador
 * quanto pela API da estação.
 */

defined('ARQ_DIG_VALIDADE_MIN')      or define('ARQ_DIG_VALIDADE_MIN', 180);
defined('ARQ_DIG_MAX_PAGINAS')       or define('ARQ_DIG_MAX_PAGINAS', 400);
defined('ARQ_DIG_MAX_BYTES_PAGINA')  or define('ARQ_DIG_MAX_BYTES_PAGINA', 40 * 1024 * 1024);

/** Diretório dos pedidos. Criado sob demanda, com a mesma proteção de arquivos/. */
function arq_dig_dir()
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'digitalizacoes';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess',
            "# Bloqueio total: páginas digitalizadas só saem por api/digitalizacao.php\r\n" .
            "<IfModule mod_authz_core.c>\r\n  Require all denied\r\n</IfModule>\r\n" .
            "<IfModule !mod_authz_core.c>\r\n  Order allow,deny\r\n  Deny from all\r\n</IfModule>\r\n" .
            "<IfModule mod_php.c>\r\n  php_flag engine off\r\n</IfModule>\r\n" .
            "<IfModule mod_php7.c>\r\n  php_flag engine off\r\n</IfModule>\r\n" .
            "Options -ExecCGI -Indexes\r\n");
    }
    if (!is_file($dir . '/index.php')) {
        @file_put_contents($dir . '/index.php', '<?php header("HTTP/1.1 403 Forbidden"); exit;');
    }
    return $dir;
}

/** Token: 64 caracteres hexadecimais. Retorna '' se inválido. */
function arq_dig_token_valido($t)
{
    $t = strtolower(trim((string) $t));
    return preg_match('/^[a-f0-9]{64}$/', $t) ? $t : '';
}

function arq_dig_pasta($token)
{
    $token = arq_dig_token_valido($token);
    if ($token === '') { return false; }
    return arq_dig_dir() . DIRECTORY_SEPARATOR . $token;
}

/** Normaliza as opções vindas do navegador para valores que a estação entende. */
function arq_dig_opcoes($o)
{
    $o = is_array($o) ? $o : [];
    $dpi = isset($o['dpi']) ? (int) $o['dpi'] : 300;
    if (!in_array($dpi, [100, 150, 200, 240, 300, 400, 600], true)) { $dpi = 300; }

    $cor = isset($o['cor']) ? (string) $o['cor'] : 'cinza';
    if (!in_array($cor, ['cor', 'cinza', 'pb'], true)) { $cor = 'cinza'; }

    $fonte = isset($o['alimentacao']) ? (string) $o['alimentacao'] : 'auto';
    if (!in_array($fonte, ['auto', 'alimentador', 'mesa'], true)) { $fonte = 'auto'; }

    return [
        'dpi'         => $dpi,
        'cor'         => $cor,
        'duplex'      => !empty($o['duplex']),
        'alimentacao' => $fonte,
        'interface'   => !empty($o['interface']),
    ];
}

/** Lê o pedido. Retorna array ou null. */
function arq_dig_ler($token)
{
    $pasta = arq_dig_pasta($token);
    if ($pasta === false || !is_file($pasta . '/pedido.json')) { return null; }
    $j = json_decode((string) @file_get_contents($pasta . '/pedido.json'), true);
    return is_array($j) ? $j : null;
}

/**
 * Altera o pedido sob trava exclusiva. $fn recebe o pedido por referência e
 * pode devolver false para abortar sem gravar.
 */
function arq_dig_alterar($token, $fn)
{
    $pasta = arq_dig_pasta($token);
    if ($pasta === false || !is_file($pasta . '/pedido.json')) { return null; }

    $trava = @fopen($pasta . '/.trava', 'c');
    if ($trava) { flock($trava, LOCK_EX); }
    try {
        $p = json_decode((string) @file_get_contents($pasta . '/pedido.json'), true);
        if (!is_array($p)) { return null; }
        $r = $fn($p);
        if ($r === false) { return $p; }
        $tmp = $pasta . '/pedido.json.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        if (!@rename($tmp, $pasta . '/pedido.json')) {
            // Windows não troca arquivo aberto por rename em algumas versões do PHP.
            @copy($tmp, $pasta . '/pedido.json');
            @unlink($tmp);
        }
        return $p;
    } finally {
        if ($trava) { flock($trava, LOCK_UN); fclose($trava); }
    }
}

/** O pedido ainda aceita a estação? */
function arq_dig_vigente($p)
{
    return is_array($p)
        && isset($p['expira']) && (int) $p['expira'] >= time()
        && (!isset($p['estado']) || $p['estado'] !== 'descartado');
}

/** Cria o pedido e devolve o token. */
function arq_dig_criar($usuario, $opcoes)
{
    arq_dig_limpar_expirados();

    $token = bin2hex(random_bytes(32));
    $pasta = arq_dig_dir() . DIRECTORY_SEPARATOR . $token;
    if (!@mkdir($pasta, 0770, true)) { return ''; }

    $agora = time();
    $pedido = [
        'token'    => $token,
        'usuario'  => (string) $usuario,
        'criado'   => $agora,
        'expira'   => $agora + ARQ_DIG_VALIDADE_MIN * 60,
        'estado'   => 'aguardando',   // aguardando | digitalizando | concluido | cancelado | erro | descartado
        'mensagem' => '',
        'contato'  => 0,              // última vez que a estação falou com o servidor
        'opcoes'   => arq_dig_opcoes($opcoes),
        'estacao'  => ['nome' => '', 'versao' => '', 'scanner' => ''],
        'seq'      => 0,
        'paginas'  => [],
    ];
    file_put_contents($pasta . '/pedido.json', json_encode($pedido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    return $token;
}

/** Apaga o pedido e as páginas. */
function arq_dig_excluir($token)
{
    $pasta = arq_dig_pasta($token);
    if ($pasta === false || !is_dir($pasta)) { return; }
    foreach ((array) @scandir($pasta) as $f) {
        if ($f === '.' || $f === '..') { continue; }
        if (is_file($pasta . DIRECTORY_SEPARATOR . $f)) { @unlink($pasta . DIRECTORY_SEPARATOR . $f); }
    }
    @rmdir($pasta);
}

/** Remove pedidos vencidos (rodado a cada criação, custo baixo). */
function arq_dig_limpar_expirados()
{
    $dir = arq_dig_dir();
    $limite = time() - 3600; // uma hora de folga depois de expirar
    foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) as $pasta) {
        $token = basename($pasta);
        if (arq_dig_token_valido($token) === '') { continue; }
        $p = arq_dig_ler($token);
        $expira = $p ? (int) $p['expira'] : @filemtime($pasta);
        if ($expira < $limite) { arq_dig_excluir($token); }
    }
}

/**
 * Grava uma página recebida da estação.
 * Só aceita JPEG e PNG — conferidos pelo conteúdo, não pelo cabeçalho HTTP.
 * Retorna ['ok'=>bool, 'erro'=>string, 'n'=>int].
 */
function arq_dig_receber_pagina($token, $bytes, $dpi, $giro = 0)
{
    $tam = strlen($bytes);
    if ($tam < 64) {
        return ['ok' => false, 'erro' => 'Página vazia.'];
    }
    if ($tam > ARQ_DIG_MAX_BYTES_PAGINA) {
        return ['ok' => false, 'erro' => 'Página maior que o limite do servidor.'];
    }

    $info = @getimagesizefromstring($bytes);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        return ['ok' => false, 'erro' => 'A página precisa ser JPEG ou PNG.'];
    }
    $tipo = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
    $dpi  = max(50, min(1200, (int) $dpi));
    $giro = in_array((int) $giro, [0, 90, 180, 270], true) ? (int) $giro : 0;

    $pasta = arq_dig_pasta($token);
    $erro  = '';
    $n     = 0;

    $p = arq_dig_alterar($token, function (&$p) use ($bytes, $tipo, $dpi, $giro, $info, $pasta, $tam, &$erro, &$n) {
        if (!arq_dig_vigente($p)) { $erro = 'Pedido de digitalização expirado ou encerrado.'; return false; }
        if (count($p['paginas']) >= ARQ_DIG_MAX_PAGINAS) {
            $erro = 'Limite de ' . ARQ_DIG_MAX_PAGINAS . ' páginas por digitalização atingido.';
            return false;
        }
        $n = (int) $p['seq'] + 1;
        $arquivo = sprintf('p%04d.%s', $n, $tipo);
        if (file_put_contents($pasta . DIRECTORY_SEPARATOR . $arquivo, $bytes) !== $tam) {
            $erro = 'Falha ao gravar a página no servidor.';
            return false;
        }
        $p['seq'] = $n;
        $p['paginas'][] = [
            'n'       => $n,
            'arquivo' => $arquivo,
            'tipo'    => $tipo,
            'dpi'     => $dpi,
            'giro'    => $giro,
            'largura' => (int) $info[0],
            'altura'  => (int) $info[1],
            'bytes'   => $tam,
        ];
        $p['contato'] = time();
        if ($p['estado'] === 'aguardando') { $p['estado'] = 'digitalizando'; }
        return true;
    });

    if ($p === null) { return ['ok' => false, 'erro' => 'Pedido de digitalização não encontrado.']; }
    if ($erro !== '') { return ['ok' => false, 'erro' => $erro]; }
    return ['ok' => true, 'erro' => '', 'n' => $n];
}

/** Resumo público do pedido (sem caminhos de disco). */
function arq_dig_resumo($p)
{
    $paginas = [];
    foreach ($p['paginas'] as $pg) {
        $paginas[] = [
            'n'       => (int) $pg['n'],
            'tipo'    => $pg['tipo'],
            'dpi'     => (int) $pg['dpi'],
            'giro'    => isset($pg['giro']) ? (int) $pg['giro'] : 0,
            'largura' => (int) $pg['largura'],
            'altura'  => (int) $pg['altura'],
            'bytes'   => (int) $pg['bytes'],
        ];
    }
    return [
        'estado'   => $p['estado'],
        'mensagem' => (string) $p['mensagem'],
        'contato'  => (int) $p['contato'],   // epoch da última chamada da estação (0 = nunca)
        'agora'    => time(),
        'estacao'  => $p['estacao'],
        'opcoes'   => $p['opcoes'],
        'paginas'  => $paginas,
        'expira'   => (int) $p['expira'],
    ];
}

/**
 * URL de api/scanner.php que a estação vai usar. A estação é o mesmo
 * computador do navegador, então o endereço que o navegador usou serve.
 * Atrás de proxy/NAT, fixe ARQ_SCANNER_ENDPOINT no config.local.php.
 */
function arq_dig_endpoint_estacao()
{
    if (defined('ARQ_SCANNER_ENDPOINT') && ARQ_SCANNER_ENDPOINT !== '') {
        return ARQ_SCANNER_ENDPOINT;
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
    // Host só com caracteres de nome/IP/porta — nada de injeção no link.
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) { $host = 'localhost'; }
    $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/arquivamento/api/digitalizacao.php';
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $base . '/scanner.php';
}
