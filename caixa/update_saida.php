<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $id = $_POST['id'];

    $conn = getDatabaseConnection();
    $stmt = $conn->prepare('UPDATE saidas_despesas SET status = "removido" WHERE id = :id');
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
