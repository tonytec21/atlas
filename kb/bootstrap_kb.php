<?php
/**
 * atlas/kb/bootstrap_kb.php
 * Executa a migracao automaticamente no primeiro acesso.
 *
 * Barato de propósito: se a sessao ja confirmou o schema, nao toca no banco.
 * Se nao confirmou, faz UMA query em information_schema. So roda o DDL de fato
 * quando alguma tabela esta faltando.
 *
 * Incluir DEPOIS de abrir a conexao ($conn disponivel).
 */

require_once __DIR__ . '/schema_kb.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$kbBootstrapLog = array();

if (empty($_SESSION['kb_schema_ok_v3'])) {
    try {
        if (!isset($conn) || !($conn instanceof PDO)) {
            require_once __DIR__ . '/../provimentos/db_connection.php';
            $conn = getDatabaseConnection();
        }

        if (kbSchemaExiste($conn)) {
            $_SESSION['kb_schema_ok_v3'] = true;
        } else {
            $kbBootstrapLog = kbGarantirSchema($conn);
            $_SESSION['kb_schema_ok_v3'] = kbSchemaExiste($conn);

            $erros = array_filter($kbBootstrapLog, function ($l) {
                return strpos($l, '[ERRO]') === 0;
            });
            if ($erros) {
                error_log('[kb/bootstrap] ' . implode(' | ', $erros));
            }
        }
    } catch (Exception $e) {
        // Nunca derruba a pagina por causa da migracao.
        error_log('[kb/bootstrap] ' . $e->getMessage());
    }
}
