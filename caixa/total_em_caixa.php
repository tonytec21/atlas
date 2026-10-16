<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';

try {
    $funcionario = trim($_GET['funcionario'] ?? '');
    $data_caixa  = $_GET['data_caixa'] ?? '';

    if ($funcionario === '' || !cx_data_valida($data_caixa)) {
        cx_json(['error' => 'Parâmetros inválidos.']);
    }

    $conn  = getDatabaseConnection();
    $saldo = cx_saldo_caixa($conn, $funcionario, $data_caixa);

    cx_json(['totalEmCaixa' => $saldo['disponivel']]);
} catch (Exception $e) {
    cx_json(['error' => $e->getMessage()]);
}
