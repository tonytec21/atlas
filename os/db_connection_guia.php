<?php
require_once __DIR__ . '/atlas_tempo.php';

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

// Alinha a sessão do MySQL ao fuso da serventia (-03:00).
atlas_alinhar_fuso($conn);
?>
