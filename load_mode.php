<?php
session_start();

if (!isset($_SESSION['username'])) {
    exit('Usuário não autenticado.');
}

$username = $_SESSION['username'];
$jsonFile = 'user_modes.json';

$mode = 'light-mode'; // Valor padrão

if (file_exists($jsonFile)) {
    $jsonData = file_get_contents($jsonFile);
    $data = json_decode($jsonData, true);
    if (isset($data[$username])) {
        $mode = $data[$username];
    }
}

echo $mode;
?>