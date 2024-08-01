<?php
session_start();
include(__DIR__ . '/db_connection.php');

if (!isset($_SESSION['username'])) {
    exit('Usuário não autenticado.');
}

$username = $_SESSION['username'];

// Buscar o modo do usuário no banco de dados
$stmt = $conn->prepare("SELECT modo FROM modo_usuario WHERE usuario = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

$mode = 'light-mode'; // Valor padrão
if ($userData) {
    $mode = $userData['modo'];
}

echo $mode;
?>
