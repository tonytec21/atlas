<?php
// Inclui os arquivos de conexão e sessão
include 'db_connection.php';
include 'session_check.php';
checkSession();

// Obtém o nome do usuário da sessão
$username = $_SESSION['username'];

// Diretório do usuário
$userDirectory = 'lembretes/' . $username;

// Verifica se o diretório do usuário existe
if (!file_exists($userDirectory)) {
    mkdir($userDirectory, 0777, true);
}

// Caminho do arquivo JSON para salvar as cores
$jsonFile = $userDirectory . '/colors.json';

// Carrega o conteúdo atual do JSON (se existir)
$colorsData = [];
if (file_exists($jsonFile)) {
    $colorsData = json_decode(file_get_contents($jsonFile), true);
}

// Verifica se a requisição é POST e obtém os dados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';
    $color = $_POST['color'] ?? '#FFFACD'; // Cor padrão caso não seja enviada

    if (!empty($filename)) {
        // Atualiza ou adiciona a cor no array de dados
        $colorsData[$filename] = $color;

        // Salva os dados no arquivo JSON
        if (file_put_contents($jsonFile, json_encode($colorsData, JSON_PRETTY_PRINT))) {
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
