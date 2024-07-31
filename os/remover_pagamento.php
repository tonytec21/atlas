<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pagamento_id = $_POST['pagamento_id'];

    if (!isset($conn)) {
        die(json_encode(['error' => 'Erro ao conectar ao banco de dados']));
    }

    $stmt = $conn->prepare("DELETE FROM pagamento_os WHERE id = ?");
    $stmt->bind_param("i", $pagamento_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Erro ao remover pagamento: ' . $conn->error]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
