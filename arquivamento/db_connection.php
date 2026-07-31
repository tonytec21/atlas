<?php
/**
 * Compatibilidade: expõe $conn (mysqli) para arquivos antigos deste módulo.
 * Código novo deve usar arq_db() (PDO com prepared statements).
 * As credenciais vêm de config.php / config.local.php — nunca daqui.
 */
require_once __DIR__ . '/config.php';

$conn = new mysqli(ARQ_DB_HOST, ARQ_DB_USER, ARQ_DB_PASS, ARQ_DB_NAME);
if ($conn->connect_error) {
    error_log('[arquivamento] Falha de conexao mysqli: ' . $conn->connect_error);
    if (!headers_sent()) { http_response_code(503); }
    die('Banco de dados indisponivel no momento.');
}
$conn->set_charset('utf8mb4');
