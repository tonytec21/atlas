<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "atlas";

// Criar conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Configurar a conexão para usar UTF-8
$conn->set_charset("utf8");

// Definir o timezone usando UTC-3
$conn->query("SET time_zone = '-03:00'");
?>
