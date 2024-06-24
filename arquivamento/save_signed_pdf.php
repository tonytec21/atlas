<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pdfData = $_POST['pdfData'];
    $fileName = $_POST['fileName'];
    
    $decodedData = base64_decode($pdfData);
    $filePath = __DIR__ . '/arquivos-assinados/' . $fileName;

    if (file_put_contents($filePath, $decodedData)) {
        echo json_encode(['message' => 'PDF assinado salvo com sucesso.']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Erro ao salvar o PDF.']);
    }
}
?>
