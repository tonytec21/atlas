<?php
session_start();

if (!isset($_SESSION['username'])) {
    exit('Usuário não autenticado.');
}

$username = $_SESSION['username'];
$mode = $_POST['mode'];

$data = [];
$jsonFile = 'user_modes.json';

if (file_exists($jsonFile)) {
    $jsonData = file_get_contents($jsonFile);
    $data = json_decode($jsonData, true);
}

$data[$username] = $mode;
file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

echo 'Modo salvo com sucesso.';
?>
