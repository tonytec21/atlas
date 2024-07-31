<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$id = $_POST['id'];
$status = 'removida';

try {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("UPDATE configuracao_os SET status = :status WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':status', $status);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao remover a conta: ' . $e->getMessage()]);
}
