<?php
include 'session_check.php';
checkSession();

// Obtém o nome do usuário da sessão
$username = $_SESSION['username'];
$userDirectory = 'lembretes/' . $username;
$orderFile = $userDirectory . '/order.json';

// Recebe a nova ordem dos lembretes
if (isset($_POST['order'])) {
    $order = json_decode($_POST['order'], true);

    // Salva a ordem no arquivo JSON
    if (file_put_contents($orderFile, json_encode($order, JSON_PRETTY_PRINT))) {
        echo 'success';
    } else {
        echo 'error';
    }
} else {
    echo 'no_order';
}
