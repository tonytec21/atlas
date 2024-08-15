<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare('SELECT *, YEAR(data_provimento) AS ano_provimento FROM provimentos WHERE id = :id');
    $stmt->bindParam(':id', $_GET['id']);
    $stmt->execute();
    $provimento = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($provimento) {
        echo json_encode($provimento);
    } else {
        echo json_encode(['error' => 'Provimento não encontrado']);
    }
}
?>
