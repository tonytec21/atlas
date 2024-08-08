<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    // Print de depuração inicial
    error_log("Valor recebido de total_em_caixa: " . $_POST['total_em_caixa']);

    // Remove 'R$', pontos e substitui vírgula por ponto para converter para decimal
    $totalEmCaixa = str_replace(['R$', '.', ','], ['', '.', ''], $_POST['total_em_caixa']);

    // Print de depuração após substituições
    error_log("Valor após substituições: " . $totalEmCaixa);

    // Converte para número decimal formatado
    $totalEmCaixa = number_format((float)$totalEmCaixa, 2, '.', '');

    // Print de depuração após formatação
    error_log("Valor após formatação: " . $totalEmCaixa);

    $dataCaixa = $_POST['data_caixa'];
    $funcionario = $_POST['funcionario'];

    // Print de depuração dos outros valores recebidos
    error_log("Data Caixa: " . $dataCaixa);
    error_log("Funcionário: " . $funcionario);

    $conn = getDatabaseConnection();

    // Insert na tabela transporte_saldo_caixa
    $sql = 'INSERT INTO transporte_saldo_caixa (data_caixa, data_transporte, valor_transportado, funcionario, status) 
            VALUES (:data_caixa, NOW(), :valor_transportado, :funcionario, "em aberto")';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':data_caixa', $dataCaixa);
    $stmt->bindParam(':valor_transportado', $totalEmCaixa, PDO::PARAM_STR);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();

    // Pega o id do transporte inserido
    $idTransporte = $conn->lastInsertId();

    // Update na tabela caixa para fechar apenas o caixa do funcionário logado
    $sql = 'UPDATE caixa SET status = "fechado" WHERE DATE(data_caixa) = :data_caixa AND funcionario = :funcionario';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':data_caixa', $dataCaixa);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
