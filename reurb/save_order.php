<?php
include(__DIR__ . '/session_check.php');
checkSession();

$username = $_SESSION['username'];
$order = $_POST['order'];

// Diretório de configuração
$dir = "config_ordem/" . $username;
if (!file_exists($dir)) {
    mkdir($dir, 0777, true);
}

// Caminho do arquivo JSON
$file = $dir . "/config.json";

// Salvar a ordem dos botões no arquivo JSON
file_put_contents($file, json_encode(['order' => $order]));

echo json_encode(['status' => 'success']);
?>
