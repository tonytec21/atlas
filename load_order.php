<?php
include(__DIR__ . '/session_check.php');
checkSession();

$username = $_SESSION['username'];

// Caminho do arquivo JSON
$file = "config_ordem/" . $username . "/config.json";

// Ler o arquivo JSON
if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo json_encode(['order' => []]); // Retorna uma ordem vazia se o arquivo não existir
}
?>
