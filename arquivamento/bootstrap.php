<?php
/**
 * Atlas · Arquivamento Digital
 * Bootstrap: sessão endurecida, CSRF, autenticação, cabeçalhos e helpers.
 *
 * Todo arquivo PHP do módulo deve começar com:
 *   require_once __DIR__ . '/bootstrap.php';   (ou '/../bootstrap.php')
 */

if (defined('ARQ_BOOTSTRAP')) { return; }
define('ARQ_BOOTSTRAP', 1);

require_once __DIR__ . '/config.php';

date_default_timezone_set(ARQ_TIMEZONE);

/* mbstring vem habilitado no XAMPP, mas o módulo não depende dele: em
   instalações enxutas os equivalentes abaixo mantêm tudo funcionando. */
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
} else {
    function mb_internal_encoding($e = null) { return 'UTF-8'; }
    function mb_strtolower($s, $e = null) { return strtolower((string) $s); }
    function mb_strtoupper($s, $e = null) { return strtoupper((string) $s); }
    function mb_strlen($s, $e = null) { return strlen(preg_replace('/[\x80-\xBF]/', '', (string) $s)); }
    function mb_substr($s, $i, $l = null, $e = null) {
        $c = preg_split('//u', (string) $s, -1, PREG_SPLIT_NO_EMPTY);
        if ($c === false) { return $l === null ? substr($s, $i) : substr($s, $i, $l); }
        $r = $l === null ? array_slice($c, $i) : array_slice($c, $i, $l);
        return implode('', $r);
    }
}

/* ------------------------------------------------------------------ *
 * Erros: nunca vazar stack trace para o navegador em produção.
 * ------------------------------------------------------------------ */
if (ARQ_AMBIENTE === 'dev') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}
if (!is_dir(__DIR__ . '/logs')) { @mkdir(__DIR__ . '/logs', 0770, true); }
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php-error.log');

/* ------------------------------------------------------------------ *
 * Polyfills (PHP 7.4)
 * ------------------------------------------------------------------ */
if (!function_exists('str_starts_with')) {
    function str_starts_with($h, $n) { return $n === '' || strncmp($h, $n, strlen($n)) === 0; }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($h, $n) { return $n === '' || substr($h, -strlen($n)) === $n; }
}
if (!function_exists('str_contains')) {
    function str_contains($h, $n) { return $n === '' || strpos($h, $n) !== false; }
}

/* ------------------------------------------------------------------ *
 * Sessão endurecida
 * ------------------------------------------------------------------ */
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $params = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (bool) ARQ_COOKIE_SECURE,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($params);
    } else {
        session_set_cookie_params(
            $params['lifetime'],
            $params['path'] . '; samesite=' . $params['samesite'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_start();
}

/* ------------------------------------------------------------------ *
 * Cabeçalhos de segurança
 * ------------------------------------------------------------------ */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header_remove('X-Powered-By');
}

/* ================================================================== *
 * Helpers gerais
 * ================================================================== */

function arq_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function arq_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

function arq_usuario()
{
    foreach (['username', 'usuario', 'user'] as $k) {
        if (!empty($_SESSION[$k])) { return (string) $_SESSION[$k]; }
    }
    return '';
}

function arq_usuario_nome()
{
    foreach (['nome_completo', 'nome', 'username'] as $k) {
        if (!empty($_SESSION[$k])) { return (string) $_SESSION[$k]; }
    }
    return arq_usuario();
}

function arq_perfil()
{
    static $perfil = null;
    if ($perfil !== null) { return $perfil; }

    // O Atlas guarda o nível de acesso e o cargo na tabela funcionarios —
    // a sessão só tem o usuário. É a mesma consulta que o menu.php faz.
    $perfil = '';
    $pdo = arq_db();
    if ($pdo) {
        try {
            $st = $pdo->prepare('SELECT nivel_de_acesso, cargo FROM funcionarios WHERE usuario = :u LIMIT 1');
            $st->execute([':u' => arq_usuario()]);
            $r = $st->fetch();
            if ($r) {
                $perfil = mb_strtoupper(trim((string) $r['nivel_de_acesso']));
                if ($perfil === '') { $perfil = mb_strtoupper(trim((string) $r['cargo'])); }
            }
        } catch (PDOException $e) {
            error_log('[arquivamento] Falha ao ler o perfil: ' . $e->getMessage());
        }
    }

    // Sessão como reserva, caso algum módulo já a preencha.
    if ($perfil === '') {
        foreach (['perfil', 'nivel', 'cargo', 'tipo_usuario'] as $k) {
            if (!empty($_SESSION[$k])) { $perfil = mb_strtoupper((string) $_SESSION[$k]); break; }
        }
    }
    return $perfil;
}

/** O usuário pode apagar definitivamente registros da lixeira? */
function arq_pode_expurgar()
{
    $perfis = arq_perfis_expurgo();
    if (empty($perfis)) { return true; }
    $perfil = arq_perfil();
    if ($perfil !== '' && in_array($perfil, $perfis, true)) { return true; }
    // Fallback: alguns ambientes do Atlas guardam só o login "ADMIN".
    return in_array(mb_strtoupper(arq_usuario()), $perfis, true);
}

function arq_e_ajax()
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

/* ================================================================== *
 * Respostas JSON
 * ================================================================== */

function arq_json($payload, $status = 200)
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function arq_ok($dados = [])
{
    if (!is_array($dados)) { $dados = ['dados' => $dados]; }
    arq_json(array_merge(['ok' => true], $dados));
}

function arq_erro($mensagem, $status = 400, $extra = [])
{
    arq_json(array_merge(['ok' => false, 'erro' => $mensagem], $extra), $status);
}

/* ================================================================== *
 * Autenticação
 * ================================================================== */

/**
 * Exige sessão válida. Em requisições AJAX devolve 401 JSON;
 * em navegação normal redireciona para o login do Atlas.
 */
function arq_exige_login()
{
    $logado = arq_usuario() !== '';

    // Expiração por inatividade
    if ($logado && ARQ_SESSAO_TIMEOUT_MIN > 0) {
        $agora  = time();
        $limite = ARQ_SESSAO_TIMEOUT_MIN * 60;
        if (isset($_SESSION['arq_ultimo_acesso']) && ($agora - (int) $_SESSION['arq_ultimo_acesso']) > $limite) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
            $logado = false;
        } else {
            $_SESSION['arq_ultimo_acesso'] = $agora;
        }
    }

    // Fixação de sessão: fingerprint do agente
    if ($logado) {
        $fp = hash('sha256', (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''));
        if (empty($_SESSION['arq_fp'])) {
            $_SESSION['arq_fp'] = $fp;
        } elseif (!hash_equals($_SESSION['arq_fp'], $fp)) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
            $logado = false;
        }
    }

    if ($logado) { return; }

    if (arq_e_ajax()) {
        arq_erro('Sessão expirada. Faça login novamente.', 401, ['redirect' => '../login.php']);
    }
    header('Location: ../login.php');
    exit;
}

/* ================================================================== *
 * CSRF
 * ================================================================== */

function arq_csrf_token()
{
    if (empty($_SESSION['arq_csrf'])) {
        $_SESSION['arq_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['arq_csrf'];
}

/** Exige POST + token CSRF válido (header X-CSRF-Token ou campo _csrf). */
function arq_exige_post_seguro()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        arq_erro('Método não permitido.', 405);
    }
    $enviado = '';
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $enviado = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (isset($_POST['_csrf'])) {
        $enviado = (string) $_POST['_csrf'];
    }
    $esperado = isset($_SESSION['arq_csrf']) ? $_SESSION['arq_csrf'] : '';
    if ($esperado === '' || $enviado === '' || !hash_equals($esperado, $enviado)) {
        arq_erro('Token de segurança inválido. Recarregue a página e tente novamente.', 419);
    }
}

/* ================================================================== *
 * Banco de dados (PDO, prepared statements)
 * ================================================================== */

function arq_db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    $dsn = 'mysql:host=' . ARQ_DB_HOST . ';dbname=' . ARQ_DB_NAME . ';charset=' . ARQ_DB_CHARSET;
    try {
        $pdo = new PDO($dsn, ARQ_DB_USER, ARQ_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('[arquivamento] Falha de conexão: ' . $e->getMessage());
        return null; // o módulo continua funcionando sem selos
    }
    return $pdo;
}

/* ================================================================== *
 * Bibliotecas do módulo
 * ================================================================== */
require_once __DIR__ . '/lib/Caminhos.php';
require_once __DIR__ . '/lib/Auditoria.php';
require_once __DIR__ . '/lib/Repositorio.php';
require_once __DIR__ . '/lib/Uploads.php';
require_once __DIR__ . '/lib/Selos.php';
