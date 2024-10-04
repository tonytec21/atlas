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
    $filePath = $userDirectory . '/' . basename($filename);

    // Verifica se o arquivo existe
    if (file_exists($filePath)) {
        $content = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Lê o arquivo em um array de linhas

        if ($content !== false) {
            $title = '';
            $bodyContent = '';

            // Processa cada linha do arquivo para encontrar "Título:" e "Conteúdo:"
            foreach ($content as $line) {
                if (stripos($line, 'Título:') === 0) {
                    // Extrai o texto após "Título:"
                    $title = trim(substr($line, strlen('Título:')));
                } elseif (stripos($line, 'Conteúdo:') === 0) {
                    // Extrai o texto após "Conteúdo:"
                    $bodyContent .= trim(substr($line, strlen('Conteúdo:'))) . "\n";
                } else {
                    // Se for parte do conteúdo, adiciona à variável $bodyContent
                    $bodyContent .= $line . "\n";
                }
            }

            echo json_encode([
                'status' => 'success',
                'title' => $title,
                'content' => trim($bodyContent)
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Falha ao ler o conteúdo do arquivo.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Arquivo não encontrado: ' . $filePath]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método de solicitação inválido.']);
}
?>
