<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';

date_default_timezone_set('America/Sao_Paulo');

try {
    $funcionario = $_SESSION['username'];
    $dataCaixa   = date('Y-m-d');

    $conn = getDatabaseConnection();
    cx_garantir_estrutura($conn);

    // Já existe caixa hoje para o funcionário (qualquer status)?
    $st = $conn->prepare('SELECT id FROM caixa WHERE funcionario = :f AND DATE(data_caixa) = :d LIMIT 1');
    $st->execute([':f' => $funcionario, ':d' => $dataCaixa]);
    if ($st->fetch(PDO::FETCH_ASSOC)) {
        cx_json(['aberto' => true]);
    }

    // Saldo transportado ainda não utilizado
    $st = $conn->prepare("SELECT COALESCE(SUM(valor_transportado),0) FROM transporte_saldo_caixa WHERE status = 'em aberto' AND funcionario = :f");
    $st->execute([':f' => $funcionario]);
    $saldoTransportado = round((float) $st->fetchColumn(), 2);

    // Caixas anteriores ainda abertos e com dinheiro: bloqueiam a abertura
    $pendentes = cx_caixas_abertos_com_saldo($conn, $funcionario, $dataCaixa);
    foreach ($pendentes as &$p) {
        $p['data_br']       = cx_data_br($p['data']);
        $p['disponivel_br'] = cx_moeda($p['disponivel']);
    }
    unset($p);

    cx_json([
        'aberto'             => false,
        'saldo_transportado' => $saldoTransportado,
        'bloqueado'          => count($pendentes) > 0,
        'pendentes'          => $pendentes,
        'is_admin'           => cx_eh_admin($conn, $funcionario),
        'funcionario'        => $funcionario,
    ]);
} catch (Exception $e) {
    cx_json(['error' => $e->getMessage()]);
}
