<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "atlas";

// Criar conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Definir o timezone usando UTC-3
$conn->query("SET time_zone = '-03:00'");
?>
