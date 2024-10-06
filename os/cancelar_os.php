<?php
include(__DIR__ . '/session_check.php');
include(__DIR__ . '/db_connection2.php');

// Verifique se a conexão está definida
if (!isset($conn)) {
    die(json_encode(['success' => false, 'error' => 'Erro ao conectar ao banco de dados']));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];

    // Atualizar o status da ordem de serviço para "Cancelado"
    $sql = "UPDATE ordens_de_servico SET status = 'Cancelado' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $os_id);
    if (!$stmt->execute()) {
        die(json_encode(['success' => false, 'error' => 'Erro ao atualizar status da ordem de serviço: ' . $stmt->error]));
    }

    // Atualizar o status dos itens relacionados na tabela "ordens_de_servico_itens"
    $sql = "UPDATE ordens_de_servico_itens SET status = 'Cancelado' WHERE ordem_servico_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $os_id);
    if (!$stmt->execute()) {
        die(json_encode(['success' => false, 'error' => 'Erro ao atualizar status dos itens da ordem de serviço: ' . $stmt->error]));
    }

    echo json_encode(['success' => true]);
    $stmt->close();
    $conn->close();
}
?>
