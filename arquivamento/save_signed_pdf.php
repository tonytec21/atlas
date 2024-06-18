<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pdfData = $_POST['pdfData'];
    $fileName = $_POST['fileName'];
    $decodedData = base64_decode($pdfData);

    // Cria o diretório se não existir
    $directory = 'arquivos-assinados';
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $filePath = $directory . '/' . $fileName;
    if (file_put_contents($filePath, $decodedData)) {
        echo 'PDF assinado salvo com sucesso.';
    } else {
        http_response_code(500);
        echo 'Erro ao salvar o PDF.';
    }
}
?>
