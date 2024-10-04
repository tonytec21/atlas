<?php
include 'session_check.php';
checkSession();

// Obtém o nome do usuário da sessão
$username = $_SESSION['username'];

// Diretórios do usuário e lixeira
$userDirectory = 'lembretes/' . $username;
$lixeiraDirectory = 'lixeira/' . $username;

// Verifica se o diretório da lixeira existe, se não, cria
if (!file_exists($lixeiraDirectory)) {
    mkdir($lixeiraDirectory, 0777, true);
}

// Recebe o nome do arquivo a ser movido para a lixeira
if (isset($_POST['filename'])) {
    $filename = $_POST['filename'];
    $filePath = $userDirectory . '/' . $filename;
    $trashPath = $lixeiraDirectory . '/' . $filename;

    // Move o arquivo para a lixeira
    if (file_exists($filePath)) {
        if (rename($filePath, $trashPath)) {
            echo 'success';
        } else {
            echo 'error';
        }
    } else {
        echo 'file_not_found';
    }
} else {
    echo 'no_filename';
}
?>
