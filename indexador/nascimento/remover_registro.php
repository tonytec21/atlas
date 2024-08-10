<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include(__DIR__ . '/db_connection.php');

    $id = $_POST['id'];
    $status = 'removido';

    // Atualizar status do registro para "removido"
    $stmt = $conn->prepare("UPDATE indexador_nascimento SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        echo json_encode(array('status' => 'success'));
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Erro ao remover o registro'));
    }

    $stmt->close();
    $conn->close();
}
?>
