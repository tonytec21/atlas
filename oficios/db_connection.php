<?php
$servername = "localhost";
$username = "root"; // ou seu usuário do banco de dados
$password = ""; // ou sua senha do banco de dados
$dbname = "oficios_db";

// Criar conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}
?>
