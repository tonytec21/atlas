<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verifica se o ID foi enviado via POST
if (isset($_POST['id'])) {
    $id = $_POST['id'];

    // Atualiza o status da conta para "Cancelado"
    $sql = "UPDATE contas_a_pagar SET status = 'Cancelado' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Conta cancelada com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao cancelar a conta.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID da conta não informado.']);
}
?>
