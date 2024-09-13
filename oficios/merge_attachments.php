<?php
if (isset($_POST['files']) && !empty($_POST['files']) && isset($_POST['numero'])) {
    $files = $_POST['files'];
    $numero = $_POST['numero'];
    $anexos_dir = __DIR__ . "/anexos/$numero/";

    // Verifica se os arquivos existem no diretório
    $validFiles = [];
    foreach ($files as $file) {
        $filePath = $anexos_dir . $file;
        if (file_exists($filePath)) {
            $validFiles[] = $filePath;
        } else {
            // Log de erro se o arquivo não for encontrado
            error_log("Arquivo não encontrado: " . $filePath);
        }
    }

    // Verifica se há arquivos válidos
    if (count($validFiles) > 0) {
        // Caminho temporário para salvar o PDF mesclado
        $outputFile = $anexos_dir . "mesclado_$numero.pdf";

        // Comando para mesclar os arquivos PDF usando Ghostscript
        $cmd = "gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=$outputFile " . implode(' ', array_map('escapeshellarg', $validFiles));

        // Log do comando que será executado
        error_log("Comando Ghostscript: " . $cmd);

        // Executa o comando e captura a saída e o retorno
        exec($cmd . ' 2>&1', $output, $return_var);

        // Log do código de retorno e da saída do comando
        error_log("Código de retorno do Ghostscript: " . $return_var);
        error_log("Saída do comando Ghostscript: " . implode("\n", $output));

        if ($return_var === 0) {
            // Sucesso ao mesclar os arquivos
            echo json_encode(['file' => $outputFile]);
        } else {
            // Erro durante a mesclagem
            http_response_code(500);
            error_log("Erro ao executar o comando Ghostscript. Código de retorno: " . $return_var);
            echo json_encode(['error' => 'Erro ao mesclar os arquivos.']);
        }
    } else {
        // Nenhum arquivo válido selecionado
        http_response_code(400);
        error_log("Nenhum arquivo válido selecionado para mesclagem.");
        echo json_encode(['error' => 'Nenhum arquivo válido selecionado.']);
    }
} else {
    // Parâmetros inválidos
    http_response_code(400);
    error_log("Parâmetros inválidos fornecidos para a mesclagem.");
    echo json_encode(['error' => 'Parâmetros inválidos.']);
}
?>
