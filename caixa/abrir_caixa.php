<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json');

try {
    $funcionario = $_SESSION['username'];
    $dataCaixa = date('Y-m-d');
    $saldoInicialUsuario = isset($_POST['saldo_inicial']) ? floatval(str_replace(',', '.', $_POST['saldo_inicial'])) : 0.0;

    $conn = getDatabaseConnection();

    // Verifica todos os saldos transportados em aberto e soma seus valores por funcionário
    $sql = 'SELECT SUM(valor_transportado) as saldo_transportado_total FROM transporte_saldo_caixa WHERE status = "em aberto" AND funcionario = :funcionario';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();
    $transporteSaldo = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldoTransportadoTotal = $transporteSaldo ? floatval($transporteSaldo['saldo_transportado_total']) : 0.0;

    // Usa o saldo inicial fornecido pelo usuário se não houver saldo transportado
    $saldoInicial = $saldoTransportadoTotal > 0 ? $saldoTransportadoTotal : $saldoInicialUsuario;

    // Verifica se o saldo inicial é maior ou igual ao valor transportado total
    if ($saldoTransportadoTotal > 0) {
        // Atualiza o status do transporte de saldo para usado e define a data de uso para todos os registros do funcionário
        $sql = 'UPDATE transporte_saldo_caixa SET status = "usado", data_caixa_uso = :data_caixa WHERE status = "em aberto" AND funcionario = :funcionario';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data_caixa', $dataCaixa);
        $stmt->bindParam(':funcionario', $funcionario);
        $stmt->execute();
    }

    // Insere o caixa do dia com o saldo inicial apropriado para o funcionário
    $sql = 'INSERT INTO caixa (saldo_inicial, funcionario, data_caixa, status) VALUES (:saldo_inicial, :funcionario, :data_caixa, "aberto")';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':saldo_inicial', $saldoInicial);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':data_caixa', $dataCaixa);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
