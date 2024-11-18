<?php
// Inclui os arquivos de conexão e sessão
include 'db_connection.php';
include 'session_check.php';
checkSession();

// Obtém o nome do usuário da sessão
$username = $_SESSION['username'];
$userDirectory = 'lembretes/' . $username;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    // Validações iniciais
    if (empty($filename)) {
        echo json_encode(['status' => 'error', 'message' => 'O arquivo não foi especificado.']);
        exit;
    }

    if (empty($title)) {
        echo json_encode(['status' => 'error', 'message' => 'O título não pode estar vazio.']);
        exit;
    }

    if (empty($content)) {
        echo json_encode(['status' => 'error', 'message' => 'O conteúdo não pode estar vazio.']);
        exit;
    }

    // Caminho completo do arquivo
    $filePath = $userDirectory . '/' . basename($filename);

    // Verifica se o arquivo existe
    if (file_exists($filePath)) {
        // Prepara o conteúdo a ser salvo
        $fileContent = "Título: " . $title . "\n\nConteúdo:\n" . $content;

        // Salva o conteúdo no arquivo
        if (file_put_contents($filePath, $fileContent) !== false) {
            echo json_encode(['status' => 'success', 'message' => 'Nota salva com sucesso.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar o arquivo.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Arquivo não encontrado.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método de solicitação inválido.']);
}
?>
