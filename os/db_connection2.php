<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "atlas";

$conn = new mysqli($servername, $username, $password, $dbname);

// Verifique se a conexão falhou
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define a codificação para UTF-8
$conn->set_charset("utf8");

// Definir o timezone usando UTC-3
$conn->query("SET time_zone = '-03:00'");
?>
