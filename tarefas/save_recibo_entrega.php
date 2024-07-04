<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verifica se todos os campos necessários foram enviados
if (isset($_POST['task_id'], $_POST['receptor'], $_POST['dataEntrega'], $_POST['documentos'], $_POST['entregador'])) {
    // Dados do formulário
    $task_id = $_POST['task_id'];
    $receptor = $_POST['receptor'];
    $dataEntrega = $_POST['dataEntrega'];
    $documentos = $_POST['documentos'];
    $entregador = $_POST['entregador']; // Nome do entregador do formulário
    $observacoes = isset($_POST['observacoes']) ? $_POST['observacoes'] : '';

    // Inserir dados na tabela `recibos_de_entrega`
    $stmt = $conn->prepare("INSERT INTO recibos_de_entrega (task_id, receptor, entregador, data_entrega, documentos, observacoes) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssss", $task_id, $receptor, $entregador, $dataEntrega, $documentos, $observacoes);
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
