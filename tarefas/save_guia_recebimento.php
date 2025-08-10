<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

// Verifica se todos os campos necessários foram enviados
if (isset($_POST['task_id'], $_POST['cliente'], $_POST['dataRecebimento'], $_POST['documentosRecebidos'], $_POST['funcionario'])) {
    // Dados do formulário
    $task_id = $_POST['task_id'];
    $cliente = $_POST['cliente'];
    $dataRecebimento = $_POST['dataRecebimento'];
    $documentosRecebidos = $_POST['documentosRecebidos'];
    $funcionario = $_POST['funcionario']; // Nome do funcionário do formulário
    $observacoes = isset($_POST['observacoes']) ? $_POST['observacoes'] : '';

    // Inserir dados na tabela `guia_de_recebimento`
    $stmt = $conn->prepare("INSERT INTO guia_de_recebimento (task_id, cliente, funcionario, data_recebimento, documentos_recebidos, observacoes) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssss", $task_id, $cliente, $funcionario, $dataRecebimento, $documentosRecebidos, $observacoes);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
}

$conn->close();
?>
