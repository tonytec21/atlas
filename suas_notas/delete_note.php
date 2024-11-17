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

    // Caminho completo do arquivo da nota
    $filePath = $userDirectory . '/' . basename($filename);

    // Verifica se o arquivo existe
    if (file_exists($filePath)) {
        // Move o arquivo da nota para a lixeira
        $lixeiraDirectory = 'lixeira/' . $username;
        if (!file_exists($lixeiraDirectory)) {
            mkdir($lixeiraDirectory, 0777, true);
        }

        $newPath = $lixeiraDirectory . '/' . basename($filename);
        if (rename($filePath, $newPath)) {
            // Exclui o arquivo JSON da cor associado à nota
            $noteId = basename($filename, '.txt'); // ID da nota (nome do arquivo sem extensão)
            $noteColorFile = $userDirectory . '/' . $noteId . '.json';

            if (file_exists($noteColorFile)) {
                unlink($noteColorFile); // Remove o arquivo de cor
            }

            echo json_encode(['status' => 'success', 'message' => 'Nota excluída e cor associada removida com sucesso.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao mover a nota para a lixeira.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nota não encontrada.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método de solicitação inválido.']);
}
?>
