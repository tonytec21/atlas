<?php
session_start();
include(__DIR__ . '/db_connection.php');

if (!isset($_SESSION['username'])) {
    exit('Usuário não autenticado.');
}

$username = $_SESSION['username'];
$mode = $_POST['mode'];

// Atualizar ou inserir o modo no banco de dados
$stmt = $conn->prepare("REPLACE INTO modo_usuario (usuario, modo) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $mode);
$stmt->execute();
$stmt->close();

echo 'Modo salvo com sucesso.';
?>
