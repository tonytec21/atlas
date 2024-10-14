<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - OCR</title>
</head>
<body>

<h2>Upload de Imagem para Extrair Texto (OCR)</h2>

<!-- Formulário para upload de imagem -->
<form action="index.php" method="post" enctype="multipart/form-data">
    <label for="image">Escolha uma imagem:</label>
    <input type="file" name="image" id="image" accept="image/*" required>
    <br><br>
    <button type="submit" name="submit">Enviar</button>
</form>

<?php
if (isset($_POST['submit'])) {
    // Verifica se a imagem foi enviada corretamente
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $imagePath = $_FILES['image']['tmp_name'];

        // Função para executar o OCR com Tesseract
        function extractTextFromImage($imagePath) {
            // Verifica se o arquivo de imagem existe
            if (!file_exists($imagePath)) {
                die("Imagem não encontrada: " . $imagePath);
            }

            // Define o caminho para onde o resultado temporário será salvo
            $outputFile = tempnam(sys_get_temp_dir(), 'ocr_result');

            // Caminho completo para o executável do Tesseract
            $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';

            // Arquivo para log de erros
            $errorLog = __DIR__ . '/tesseract_error_log.txt';

            // Comando Tesseract OCR com log de erros
            $command = "\"$tesseractPath\" " . escapeshellarg($imagePath) . " " . escapeshellarg($outputFile) . " -l por --dpi 300 2> " . escapeshellarg($errorLog);

            // Executa o comando
            system($command);

            // Verifica se o arquivo de texto foi gerado
            if (file_exists($outputFile . ".txt")) {
                // Lê o conteúdo do arquivo gerado pelo Tesseract
                $ocrResult = file_get_contents($outputFile . ".txt");
                // Remove o arquivo temporário
                unlink($outputFile . ".txt");
                return $ocrResult;
            } else {
                // Se o arquivo de saída não foi gerado, retorna o erro
                return "Erro ao executar o Tesseract. Verifique o log de erros abaixo:<br>" .
                       nl2br(htmlspecialchars(file_get_contents($errorLog)));
            }
        }

        // Extrai o texto da imagem
        $extractedText = extractTextFromImage($imagePath);

        // Exibe o texto extraído
        echo "<h3>Texto extraído da imagem:</h3>";
        echo "<pre>" . nl2br(htmlspecialchars($extractedText)) . "</pre>";
    } else {
        echo "<p>Erro ao enviar a imagem. Tente novamente.</p>";
    }
}
?>

</body>
</html>
