<?php
/* atlas-auditoria */ if (is_file(__DIR__ . '/../auditoria/hook.php')) { include_once __DIR__ . '/../auditoria/hook.php'; }
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "atlas";

$conn = new mysqli($servername, $username, $password, $dbname); /* atlas-auditoria */ if (is_file(__DIR__ . '/../auditoria/hook.php')) { include_once __DIR__ . '/../auditoria/hook.php'; atlas_aud_conexao($conn); }

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Definindo o charset para garantir que os dados sejam salvos corretamente
$conn->set_charset("utf8mb4");
?>
