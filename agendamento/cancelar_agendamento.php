<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$id = $_POST['id'] ?? null;

if ($id) {
    $stmt = $conn->prepare("UPDATE agendamentos SET status = 'cancelado' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}
