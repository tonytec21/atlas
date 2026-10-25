<?php
/* atlas-auditoria */ if (is_file(__DIR__ . '/../auditoria/hook.php')) { include_once __DIR__ . '/../auditoria/hook.php'; }
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "atlas";

$conn = new mysqli($servername, $username, $password, $dbname); /* atlas-auditoria */ if (is_file(__DIR__ . '/../auditoria/hook.php')) { include_once __DIR__ . '/../auditoria/hook.php'; atlas_aud_conexao($conn); }

// Verifique se a conexão falhou
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define a codificação para UTF-8
$conn->set_charset("utf8mb4");
?>
