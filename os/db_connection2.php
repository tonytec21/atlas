<?php
require_once __DIR__ . '/atlas_tempo.php';

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

// Alinha a sessão do MySQL ao fuso da serventia (-03:00): NOW(),
// CURRENT_TIMESTAMP e os DEFAULT das colunas passam a gravar a hora correta.
atlas_alinhar_fuso($conn);
?>
