<?php
/**
 * Atlas · Tarefas — compatibilidade: db_connection.php.
 *
 * Vários arquivos de impressão (protocolo, guia, recibo, ofício) ainda fazem
 * include deste arquivo e usam a variável $conn (mysqli) e as variáveis
 * $servername/$username/$password/$dbname. Todas continuam disponíveis, mas
 * agora vêm de core/config.php, que é o único ponto do módulo que conhece as
 * credenciais.
 */

require_once __DIR__ . '/core/bootstrap.php';

$servername = TAREFAS_DB_HOST;
$username   = TAREFAS_DB_USER;
$password   = TAREFAS_DB_PASS;
$dbname     = TAREFAS_DB_NAME;

$conn = db_mysqli();
