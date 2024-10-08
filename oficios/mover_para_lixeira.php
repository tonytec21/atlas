<?php
if (isset($_POST['file']) && isset($_POST['numero'])) {
    // Sanitizar as entradas
    $file = filter_input(INPUT_POST, 'file', FILTER_SANITIZE_STRING);
    $numero = filter_input(INPUT_POST, 'numero', FILTER_SANITIZE_STRING);
    
    // Caminhos dos diretórios
    $anexos_dir = __DIR__ . "/anexos/$numero/";
    $lixeira_dir = __DIR__ . "/lixeira/$numero/";

    // Cria o diretório da Lixeira, se não existir
    if (!is_dir($lixeira_dir)) {
        if (!mkdir($lixeira_dir, 0777, true)) {
            echo json_encode(['status' => 'error', 'message' => 'Falha ao criar o diretório da lixeira.']);
            exit;
        }
    }

    // Caminho completo dos arquivos
    $filePath = realpath($anexos_dir . basename($file));
    $lixeiraPath = $lixeira_dir . basename($file);

    // Verificar se o arquivo existe no diretório de anexos
    if ($filePath && file_exists($filePath) && strpos($filePath, realpath($anexos_dir)) === 0) {
        // Mover o arquivo para a lixeira
        if (rename($filePath, $lixeiraPath)) {
            echo json_encode(['status' => 'success', 'message' => 'Anexo excluído com sucesso.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Falha ao excluir anexo.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Arquivo não encontrado ou caminho inválido.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Parâmetros inválidos.']);
}
?>
