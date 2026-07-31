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
    die("Conexão falhou: " . $conn->connect_error);
}

// Alinha a sessão do MySQL ao fuso da serventia (-03:00).
atlas_alinhar_fuso($conn);
?>
