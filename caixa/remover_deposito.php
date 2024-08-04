<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDatabaseConnection();

    $id = $_POST['id'];

    $stmt = $conn->prepare('UPDATE deposito_caixa SET status = "removido" WHERE id = :id');
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        $response['success'] = true;
    } else {
        $response['error'] = "Falha ao remover depósito.";
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
