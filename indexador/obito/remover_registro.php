<?php
session_start();

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['username'])) {
    echo json_encode(array('status' => 'error', 'message' => 'Usuário não autenticado'));
    exit;
}

if (!isset($_SESSION['nivel_de_acesso']) || $_SESSION['nivel_de_acesso'] !== 'administrador') {
    echo json_encode(array('status' => 'error', 'message' => 'Acesso negado. Apenas administradores podem excluir registros.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include(__DIR__ . '/db_connection.php');

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(array('status' => 'error', 'message' => 'ID inválido'));
        exit;
    }

    $status = 'removido';

    // Atualizar status do registro para "removido"
    $stmt = $conn->prepare("UPDATE indexador_obito SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        echo json_encode(array('status' => 'success'));
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Erro ao remover o registro'));
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(array('status' => 'error', 'message' => 'Método inválido'));
}
?>
