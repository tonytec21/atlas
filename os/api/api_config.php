<?php
/**
 * =====================================================================
 * api_config.php — Núcleo da API do módulo O.S. (Atlas)
 * ---------------------------------------------------------------------
 * ATLAS-OS-API-BUILD: 2026-07-31-v1
 *
 * Bootstrap, conexão, envelope de resposta e migração do schema próprio
 * da API. Não usa sessão: a autenticação é por token (ver api_auth.php).
 *
 * O schema da API vive em tabelas próprias (prefixo api_). Nenhuma tabela
 * do módulo O.S. é alterada — a integração se apoia nas existentes
 * (ordens_de_servico, ordens_de_servico_itens, pagamento_os,
 * devolucao_os, atos_liquidados, atos_manuais_liquidados).
 * =====================================================================
 */

if (!defined('ATLAS_OS_API')) {
    define('ATLAS_OS_API', '1.0.0');
}

/* Erros nunca podem vazar como HTML no meio de um JSON. */
@ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/../atlas_tempo.php';
require_once __DIR__ . '/../db_connection.php';

/* --------------------------------------------------------------------
 * Conexão
 * ------------------------------------------------------------------ */

function api_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = getDatabaseConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

/* --------------------------------------------------------------------
 * Envelope de resposta
 * --------------------------------------------------------------------
 * Sucesso: { "sucesso": true,  "dados": {...} }
 * Erro:    { "sucesso": false, "erro": { "codigo": "...", "mensagem": "...", ... } }
 *
 * O código de erro é uma string estável — o integrador deve programar
 * contra ela, nunca contra o texto da mensagem.
 * ------------------------------------------------------------------ */

function api_responder(array $payload, int $http = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Atlas-Api-Versao: ' . ATLAS_OS_API);
        http_response_code($http);
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    /* Registra o desfecho antes de encerrar (o logger é tolerante a falhas). */
    if (function_exists('api_log_finalizar')) {
        api_log_finalizar($http, $payload);
    }

    exit;
}

function api_ok($dados = [], int $http = 200): void
{
    api_responder(['sucesso' => true, 'dados' => $dados], $http);
}

/**
 * @param array $extra Campos adicionais dentro de "erro" (ex.: saldo, falta).
 */
function api_erro(string $codigo, string $mensagem, int $http = 400, array $extra = []): void
{
    api_responder([
        'sucesso' => false,
        'erro'    => array_merge(['codigo' => $codigo, 'mensagem' => $mensagem], $extra),
    ], $http);
}

/* --------------------------------------------------------------------
 * Entrada
 * ------------------------------------------------------------------ */

/**
 * Corpo da requisição como array. Aceita JSON e form-urlencoded.
 */
function api_corpo(): array
{
    static $corpo = null;
    if ($corpo !== null) {
        return $corpo;
    }

    $raw  = file_get_contents('php://input');
    $tipo = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));

    if ($raw === '' || $raw === false) {
        $corpo = $_POST ?: [];
        return $corpo;
    }

    if (strpos($tipo, 'application/json') !== false || (isset($raw[0]) && ($raw[0] === '{' || $raw[0] === '['))) {
        $dec = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            api_erro('json_invalido', 'O corpo da requisição não é um JSON válido: ' . json_last_error_msg(), 400);
        }
        $corpo = is_array($dec) ? $dec : [];
        return $corpo;
    }

    parse_str($raw, $form);
    $corpo = $form ?: ($_POST ?: []);
    return $corpo;
}

function api_campo(array $corpo, string $nome, $padrao = null)
{
    return array_key_exists($nome, $corpo) ? $corpo[$nome] : $padrao;
}

function api_exigir(array $corpo, string $nome)
{
    $v = api_campo($corpo, $nome);
    if ($v === null || $v === '' || (is_array($v) && !$v)) {
        api_erro('campo_obrigatorio', 'O campo "' . $nome . '" é obrigatório.', 422, ['campo' => $nome]);
    }
    return $v;
}

/**
 * Converte "1.234,56", "1234.56", 1234.56 -> float.
 */
function api_valor($v): float
{
    if (is_float($v) || is_int($v)) {
        return (float) $v;
    }

    $s = trim((string) $v);
    if ($s === '') {
        return 0.0;
    }

    /* Formato brasileiro: a vírgula é o separador decimal. */
    if (strpos($s, ',') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }

    return (float) $s;
}

function api_so_digitos($v): string
{
    return preg_replace('/\D+/', '', (string) $v);
}

function api_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* --------------------------------------------------------------------
 * Migração do schema da API (idempotente)
 * ------------------------------------------------------------------ */

function api_migrar(?PDO $pdo = null): void
{
    static $feito = false;
    if ($feito) {
        return;
    }
    $feito = true;

    $pdo = $pdo ?: api_pdo();

    /* Sistemas integradores homologados. */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_sistemas (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            nome             VARCHAR(150)  NOT NULL,
            responsavel      VARCHAR(150)  NULL,
            email            VARCHAR(150)  NULL,
            documento        VARCHAR(20)   NULL,
            client_id        VARCHAR(40)   NOT NULL,
            token_hash       CHAR(64)      NOT NULL,
            token_prefixo    VARCHAR(24)   NOT NULL,
            ambiente         VARCHAR(12)   NOT NULL DEFAULT 'homologacao',
            status           VARCHAR(12)   NOT NULL DEFAULT 'pendente',
            escopos          VARCHAR(255)  NOT NULL DEFAULT 'os:ler,os:criar,pagamento:criar,ato:liquidar',
            ips_permitidos   VARCHAR(500)  NULL,
            observacoes      TEXT          NULL,
            criado_em        DATETIME      NULL,
            criado_por       VARCHAR(100)  NULL,
            homologado_em    DATETIME      NULL,
            homologado_por   VARCHAR(100)  NULL,
            ultimo_acesso_em DATETIME      NULL,
            ultimo_acesso_ip VARCHAR(45)   NULL,
            total_requisicoes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uk_client (client_id),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /* Trilha de auditoria: toda requisição autenticada (e as recusadas). */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_log (
            id             BIGINT AUTO_INCREMENT PRIMARY KEY,
            sistema_id     INT           NULL,
            client_id      VARCHAR(40)   NULL,
            metodo         VARCHAR(10)   NOT NULL,
            rota           VARCHAR(255)  NOT NULL,
            os_id          INT           NULL,
            status_http    SMALLINT      NULL,
            codigo_erro    VARCHAR(60)   NULL,
            mensagem       VARCHAR(500)  NULL,
            ip             VARCHAR(45)   NULL,
            idempotencia   VARCHAR(80)   NULL,
            corpo          TEXT          NULL,
            duracao_ms     INT           NULL,
            criado_em      DATETIME      NULL,
            KEY idx_sistema (sistema_id),
            KEY idx_os (os_id),
            KEY idx_data (criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /* Idempotência: repetir a mesma chave devolve a resposta original,
       sem executar a operação de novo. Essencial para liquidação. */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_idempotencia (
            id           BIGINT AUTO_INCREMENT PRIMARY KEY,
            sistema_id   INT          NOT NULL,
            chave        VARCHAR(80)  NOT NULL,
            rota         VARCHAR(255) NOT NULL,
            corpo_hash   CHAR(64)     NOT NULL,
            status_http  SMALLINT     NOT NULL,
            resposta     MEDIUMTEXT   NOT NULL,
            criado_em    DATETIME     NULL,
            UNIQUE KEY uk_chave (sistema_id, chave),
            KEY idx_data (criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /* Vínculo O.S. <-> sistema/ambiente. Isola o que foi criado em
       homologação do acervo real. */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_os_vinculo (
            os_id      INT          NOT NULL PRIMARY KEY,
            sistema_id INT          NOT NULL,
            ambiente   VARCHAR(12)  NOT NULL,
            criado_em  DATETIME     NULL,
            KEY idx_sistema (sistema_id),
            KEY idx_ambiente (ambiente)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /* Selos informados na liquidação pelo sistema de lavratura. */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_selos (
            id             BIGINT AUTO_INCREMENT PRIMARY KEY,
            os_id          INT          NOT NULL,
            item_id        INT          NULL,
            liquidacao_id  INT          NULL,
            tabela_origem  VARCHAR(40)  NULL,
            ato            VARCHAR(60)  NULL,
            selo           VARCHAR(80)  NOT NULL,
            quantidade     INT          NOT NULL DEFAULT 1,
            sistema_id     INT          NULL,
            protocolo      VARCHAR(80)  NULL,
            criado_em      DATETIME     NULL,
            KEY idx_os (os_id),
            KEY idx_selo (selo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}
