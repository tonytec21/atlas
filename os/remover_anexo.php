<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $anexo_id = $_POST['anexo_id'];

    $conn = getDatabaseConnection();

    try {
        $stmt = $conn->prepare("UPDATE anexos_os SET status = 'removido' WHERE id = :anexo_id");
        $stmt->bindParam(':anexo_id', $anexo_id);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
}
?>
