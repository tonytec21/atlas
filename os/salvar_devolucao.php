<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

// Definir timezone corretamente
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($conn)) {
        die(json_encode(['error' => 'Erro ao conectar ao banco de dados']));
    }

    // Captura dos dados
    $os_id            = intval($_POST['os_id']);
    $cliente          = trim($_POST['cliente']);
    $total_os         = floatval($_POST['total_os']);
    $total_devolucao  = floatval($_POST['total_devolucao']);
    $forma_devolucao  = trim($_POST['forma_devolucao']);
    $funcionario      = trim($_POST['funcionario']);
    $status           = 'Devolvido';
    $data_devolucao   = date('Y-m-d H:i:s');

    // Query preparada
    $query = $conn->prepare("
        INSERT INTO devolucao_os 
        (ordem_de_servico_id, cliente, total_os, total_devolucao, forma_devolucao, data_devolucao, funcionario, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$query) {
        echo json_encode(['error' => 'Erro na preparação da query: ' . $conn->error]);
        exit;
    }

    $query->bind_param(
        "issdssss",
        $os_id,
        $cliente,
        $total_os,
        $total_devolucao,
        $forma_devolucao,
        $data_devolucao,
        $funcionario,
        $status
    );

    // Execução
    if ($query->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Erro ao salvar devolução: ' . $query->error]);
    }

    // Encerramento
    $query->close();
    $conn->close();
}
?>
