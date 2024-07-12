<?php
include(__DIR__ . '/session_check.php');
include(__DIR__ . '/db_connection.php');
checkSession();

$id = $_GET['id'] ?? '';

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM registros_cedulas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cedula = $result->fetch_assoc();
    echo json_encode($cedula);
    $stmt->close();
}

$conn->close();
?>
