<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $funcionario = $_SESSION['username'];
    $dataCaixa = date('Y-m-d');

    $conn = getDatabaseConnection();

    // Verifica se já existe um caixa para a data atual, para o funcionário, independentemente do status
    $sql = 'SELECT id FROM caixa WHERE DATE(data_caixa) = :data_caixa AND funcionario = :funcionario LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':data_caixa', $dataCaixa);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();
    $caixaExistente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($caixaExistente) {
        echo json_encode(['aberto' => true]);
    } else {
        // Verifica todos os saldos transportados em aberto para o funcionário e soma seus valores
        $sql = 'SELECT SUM(valor_transportado) as saldo_transportado_total FROM transporte_saldo_caixa WHERE status = "em aberto" AND funcionario = :funcionario';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':funcionario', $funcionario);
        $stmt->execute();
        $transporteSaldo = $stmt->fetch(PDO::FETCH_ASSOC);
        $saldoTransportadoTotal = $transporteSaldo ? floatval($transporteSaldo['saldo_transportado_total']) : 0.0;

        echo json_encode(['aberto' => false, 'saldo_transportado' => $saldoTransportadoTotal]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
