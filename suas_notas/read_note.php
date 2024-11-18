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
    $filePath = $userDirectory . '/' . basename($filename);

    // Verifica se o arquivo da nota existe
    if (file_exists($filePath)) {
        // Lê todo o conteúdo do arquivo como uma string
        $content = file_get_contents($filePath);

        if ($content !== false) {
            $title = '';
            $bodyContent = '';

            // Processa o conteúdo para encontrar "Título:" e "Conteúdo:"
            $lines = explode("\n", $content); // Divide o arquivo em linhas

            foreach ($lines as $line) {
                if (stripos($line, 'Título:') === 0) {
                    $title = trim(substr($line, strlen('Título:')));
                } elseif (stripos($line, 'Conteúdo:') === 0) {
                    // Tudo após "Conteúdo:" é parte do conteúdo
                    $bodyContent = trim(implode("\n", array_slice($lines, array_search($line, $lines) + 1)));
                    break;
                }
            }

            // Verifica se título e conteúdo foram extraídos
            if (empty($title) || empty($bodyContent)) {
                echo json_encode(['status' => 'error', 'message' => 'Formato do arquivo inválido.']);
                exit;
            }

            // Define o caminho do arquivo JSON da cor da nota
            $noteId = basename($filename, '.txt'); // ID da nota é o nome do arquivo sem extensão
            $noteColorFile = $userDirectory . '/' . $noteId . '.json';

            // Busca a cor no arquivo JSON da nota ou usa a cor padrão
            $color = '#FFF9C4'; // Cor padrão
            if (file_exists($noteColorFile)) {
                $noteColorData = json_decode(file_get_contents($noteColorFile), true);

                // Valida se o JSON foi lido corretamente e contém a chave "cor"
                if (json_last_error() === JSON_ERROR_NONE && isset($noteColorData['cor'])) {
                    $color = $noteColorData['cor'];
                } else {
                    error_log("Erro ao interpretar o arquivo JSON: " . json_last_error_msg());
                }
            } else {
                // Cria o arquivo JSON com a cor padrão, se ainda não existir
                $noteColorData = [
                    'id' => $noteId,
                    'cor' => $color
                ];
                file_put_contents($noteColorFile, json_encode($noteColorData, JSON_PRETTY_PRINT));
            }

            echo json_encode([
                'status' => 'success',
                'title' => $title,
                'content' => $bodyContent,
                'color' => $color // Retorna a cor associada ao lembrete
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
