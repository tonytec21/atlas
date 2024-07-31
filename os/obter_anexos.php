<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];

    $conn = getDatabaseConnection();

    try {
        $stmt = $conn->prepare("SELECT * FROM anexos_os WHERE ordem_servico_id = :os_id AND status = 'ativo'");
        $stmt->bindParam(':os_id', $os_id);
        $stmt->execute();
        $anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'anexos' => $anexos]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
}
?>
