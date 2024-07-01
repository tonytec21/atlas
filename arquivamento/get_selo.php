<?php
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $atoId = $_GET['id'];
    
    $stmt = $conn->prepare("SELECT numero_selo FROM selos WHERE ato_id = ?");
    $stmt->bind_param("i", $atoId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $selo = $result->fetch_assoc();
        echo json_encode($selo);
    } else {
        echo json_encode(null);
    }

    $stmt->close();
    $conn->close();
}
?>
