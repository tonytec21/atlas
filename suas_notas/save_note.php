<?php
// Inclui os arquivos de conexão e sessão
include 'db_connection.php';
include 'session_check.php';
checkSession();

// Obtém o nome do usuário da sessão
$username = $_SESSION['username'];
$userDirectory = 'lembretes/' . $username;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'];
    $title = $_POST['title'];
    $content = $_POST['content'];

    // Caminho completo do arquivo
    $filePath = $userDirectory . '/' . basename($filename);

    // Verifica se o arquivo existe
    if (file_exists($filePath)) {
        // Abre o arquivo para escrita e inclui "Título:" e "Conteúdo:"
        $file = fopen($filePath, 'w');

        if ($file) {
            // Salva o título e o conteúdo com as quebras de linha adequadas
            fwrite($file, "Título: " . $title . "\n\n"); // Título na mesma linha e depois duas quebras de linha
            fwrite($file, "Conteúdo:\n" . $content); // Conteúdo em nova linha após "Conteúdo:"
            fclose($file);

            echo 'success';
        } else {
            echo 'error';
        }
    } else {
        echo 'error';
    }
} else {
    echo 'error';
}
?>
