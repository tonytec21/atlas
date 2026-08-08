<?php
/**
 * Atlas · Tarefas — bootstrap do módulo.
 *
 * Carrega configuração, abre a sessão, cria a conexão PDO e disponibiliza
 * as funções usadas por todas as páginas e APIs.
 *
 * Compatibilidade: além do PDO, este arquivo também expõe a variável global
 * $conn (mysqli), porque os arquivos legados de impressão (protocolo, guia,
 * recibo, ofício) ainda a utilizam. Os dois convivem sem conflito.
 */

if (defined('ATLAS_TAREFAS_BOOT')) {
    return;
}
define('ATLAS_TAREFAS_BOOT', true);

require_once __DIR__ . '/config.php';

date_default_timezone_set(TAREFAS_TIMEZONE);

/*
 * O mbstring vem habilitado no XAMPP, mas não em toda instalação de PHP.
 * Como o módulo só o usa para acentuação, seguimos sem ele quando faltar em
 * vez de derrubar a tela com erro fatal.
 */
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
} else {
    /* Substitutos suficientes para o que o módulo faz com essas funções. */
    function mb_strlen($s, $enc = null)
    {
        $r = preg_match_all('/./us', (string) $s);
        return $r === false ? strlen((string) $s) : $r;
    }

    function mb_substr($s, $inicio, $tamanho = null, $enc = null)
    {
        $partes = preg_split('//u', (string) $s, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($partes)) {
            return $tamanho === null ? substr((string) $s, $inicio) : substr((string) $s, $inicio, $tamanho);
        }
        $corte = $tamanho === null
            ? array_slice($partes, $inicio)
            : array_slice($partes, $inicio, $tamanho);
        return implode('', $corte);
    }

    function mb_strtolower($s, $enc = null)
    {
        return function_exists('iconv') ? strtolower((string) $s) : strtolower((string) $s);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================================================================== */
/* Conexão                                                            */
/* ================================================================== */

/**
 * Conexão PDO única do módulo.
 *
 * @return PDO
 */
function db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . TAREFAS_DB_HOST . ';dbname=' . TAREFAS_DB_NAME
         . ';charset=' . TAREFAS_DB_CHARSET;

    try {
        $pdo = new PDO($dsn, TAREFAS_DB_USER, TAREFAS_DB_PASS, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ));
    } catch (PDOException $e) {
        // utf8mb4 pode não existir em bancos MySQL bem antigos; tenta utf8.
        $dsn = 'mysql:host=' . TAREFAS_DB_HOST . ';dbname=' . TAREFAS_DB_NAME . ';charset=utf8';
        $pdo = new PDO($dsn, TAREFAS_DB_USER, TAREFAS_DB_PASS, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ));
    }

    return $pdo;
}

/**
 * Conexão mysqli para os arquivos legados que ainda dependem de $conn.
 *
 * @return mysqli
 */
function db_mysqli()
{
    static $mysqli = null;
    if ($mysqli instanceof mysqli) {
        return $mysqli;
    }
    $mysqli = new mysqli(TAREFAS_DB_HOST, TAREFAS_DB_USER, TAREFAS_DB_PASS, TAREFAS_DB_NAME);
    if ($mysqli->connect_error) {
        throw new RuntimeException('Falha na conexão: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

/**
 * Executa um SELECT e devolve todas as linhas.
 *
 * @return array
 */
function db_all($sql, array $params = array())
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Executa um SELECT e devolve a primeira linha (ou null). */
function db_one($sql, array $params = array())
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $linha = $st->fetch();
    return $linha === false ? null : $linha;
}

/** Executa um SELECT e devolve o primeiro valor da primeira linha. */
function db_valor($sql, array $params = array(), $padrao = null)
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? $padrao : $v;
}

/** Executa INSERT/UPDATE/DELETE e devolve a quantidade de linhas afetadas. */
function db_exec($sql, array $params = array())
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/**
 * A tabela existe no banco?
 * Usado para que recursos novos (checklist, IA, histórico) não derrubem o
 * módulo caso a migração ainda não tenha sido executada.
 */
function db_tem_tabela($tabela)
{
    static $cache = array();
    $tabela = (string) $tabela;
    if (isset($cache[$tabela])) {
        return $cache[$tabela];
    }
    try {
        /*
         * information_schema em vez de "SHOW TABLES LIKE ?": o SHOW não aceita
         * parâmetro em prepared statement nativo do MySQL/MariaDB, e a consulta
         * falharia sempre que a emulação estivesse desligada.
         */
        $st = db()->prepare(
            'SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute(array($tabela));
        $cache[$tabela] = $st->fetch() !== false;
    } catch (Exception $e) {
        $cache[$tabela] = false;
    }
    return $cache[$tabela];
}

/** A coluna existe na tabela? */
function db_tem_coluna($tabela, $coluna)
{
    static $cache = array();
    $chave = $tabela . '.' . $coluna;
    if (isset($cache[$chave])) {
        return $cache[$chave];
    }
    try {
        $st = db()->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute(array($tabela, $coluna));
        $cache[$chave] = $st->fetch() !== false;
    } catch (Exception $e) {
        $cache[$chave] = false;
    }
    return $cache[$chave];
}

/* ================================================================== */
/* Sessão e permissões                                                */
/* ================================================================== */

/** Interrompe o acesso de quem não está logado. */
function exigir_login($json = false)
{
    if (!empty($_SESSION['username'])) {
        return;
    }
    if ($json) {
        responder_json(array('success' => false, 'error' => 'Sessão expirada. Faça login novamente.'), 401);
    }
    header('Location: ../login.php');
    exit;
}

/**
 * Dados do usuário logado, já com o nível de acesso resolvido.
 *
 * Mantém exatamente a regra original: administrador vê tudo; usuário comum
 * com "Controle de Tarefas" no campo acesso_adicional também vê tudo; os
 * demais só enxergam as próprias tarefas (como responsável ou revisor) e as
 * já concluídas.
 *
 * @return array{usuario:string,nome:string,nivel:string,total:bool,acessos:array}
 */
function usuario_atual()
{
    static $u = null;
    if ($u !== null) {
        return $u;
    }

    $login = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    $u = array(
        'usuario' => $login,
        'nome'    => $login,
        'nivel'   => 'usuario',
        'total'   => false,
        'acessos' => array(),
    );

    if ($login === '') {
        return $u;
    }

    $linha = db_one(
        'SELECT nome_completo, nivel_de_acesso, acesso_adicional
           FROM funcionarios
          WHERE usuario = ? AND LOWER(status) = ?
          LIMIT 1',
        array($login, 'ativo')
    );

    if (!$linha) {
        return $u;
    }

    $acessos = array_filter(array_map('trim', explode(',', (string) $linha['acesso_adicional'])));

    $u['nome']    = $linha['nome_completo'];
    $u['nivel']   = (string) $linha['nivel_de_acesso'];
    $u['acessos'] = $acessos;
    $u['total']   = ($u['nivel'] === 'administrador') || in_array('Controle de Tarefas', $acessos, true);

    return $u;
}

/** Atalho: o usuário logado enxerga todas as tarefas? */
function usuario_ve_tudo()
{
    $u = usuario_atual();
    return $u['total'];
}

/* ================================================================== */
/* CSRF                                                               */
/* ================================================================== */

function csrf_token()
{
    if (empty($_SESSION['tarefas_csrf'])) {
        $_SESSION['tarefas_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['tarefas_csrf'];
}

/**
 * Valida o token enviado no POST (campo _csrf ou cabeçalho X-CSRF-Token).
 * Em caso de falha responde 419 e encerra.
 */
function csrf_validar()
{
    $enviado = '';
    if (isset($_POST['_csrf'])) {
        $enviado = (string) $_POST['_csrf'];
    } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $enviado = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    if ($enviado === '' || !hash_equals(csrf_token(), $enviado)) {
        responder_json(array(
            'success' => false,
            'error'   => 'Sessão de segurança expirada. Recarregue a página (F5) e tente novamente.',
        ), 419);
    }
}

/* ================================================================== */
/* Respostas JSON                                                     */
/* ================================================================== */

/**
 * Prepara um endpoint JSON: silencia HTML de erro, converte fatais em JSON
 * e garante que nada suje a resposta.
 *
 * Era exatamente essa contaminação que gerava o clássico
 * "Unexpected token '<'" no navegador.
 */
function api_iniciar($exigirLogin = true)
{
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
            }
            echo json_encode(array(
                'success' => false,
                'error'   => 'Erro interno no módulo de tarefas.',
                'detalhe' => $e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')',
            ), JSON_UNESCAPED_UNICODE);
        }
    });

    ob_start();

    if ($exigirLogin) {
        exigir_login(true);
    }
}

/** Resposta JSON única e limpa. */
function responder_json($dados, $codigo = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($codigo);
    }
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Atalho para respostas de sucesso. */
function responder_ok(array $dados = array())
{
    responder_json(array_merge(array('success' => true), $dados));
}

/** Atalho para respostas de erro. */
function responder_erro($mensagem, $codigo = 400, array $extra = array())
{
    responder_json(array_merge(array('success' => false, 'error' => $mensagem), $extra), $codigo);
}

require_once __DIR__ . '/helpers.php';
