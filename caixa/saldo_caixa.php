<?php
/**
 * Saldo em espécie do caixa (funcionário + data) — usado pelos modais
 * de saída, depósito e fechamento para exibir o valor disponível.
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';

try {
    $funcionario = trim($_GET['funcionario'] ?? '');
    $data        = $_GET['data_caixa'] ?? ($_GET['data'] ?? '');

    if ($funcionario === '' || !cx_data_valida($data)) {
        cx_json(['error' => 'Parâmetros inválidos.']);
    }

    $conn  = getDatabaseConnection();
    $saldo = cx_saldo_caixa($conn, $funcionario, $data);
    $saldo['disponivel_br'] = cx_moeda($saldo['disponivel']);

    cx_json(['success' => true, 'saldo' => $saldo]);
} catch (Exception $e) {
    cx_json(['error' => $e->getMessage()]);
}
