<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDatabaseConnection();

    $id = $_POST['id'];
    if (!$id) {
        $response['error'] = 'ID do depósito não fornecido.';
        echo json_encode($response);
        exit;
    }

    // Log do ID recebido
    error_log('ID do depósito recebido: ' . $id);

    $stmt = $conn->prepare('UPDATE deposito_caixa SET status = "removido" WHERE id = :id');
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        $response['success'] = true;
    } else {
        // Log do erro ao executar a consulta
        $errorInfo = $stmt->errorInfo();
        error_log('Erro ao executar a atualização: ' . $errorInfo[2]);
        $response['error'] = "Falha ao remover depósito.";
    }
} else {
    $response['error'] = 'Método de requisição inválido.';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
