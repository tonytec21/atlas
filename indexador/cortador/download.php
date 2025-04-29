<?php  
// Verificar se o nome do arquivo foi fornecido  
if (!isset($_GET['file'])) {  
    header('HTTP/1.1 400 Bad Request');  
    echo 'Nome do arquivo não fornecido';  
    exit;  
}  

$fileName = $_GET['file'];  
$uploadDir = __DIR__ . '/uploads/';  
$filePath = $uploadDir . $fileName;  

// Validar nome do arquivo para segurança  
if (preg_match('/[\/\\\\]/', $fileName) || strpos($fileName, '..') !== false) {  
    header('HTTP/1.1 400 Bad Request');  
    echo 'Nome de arquivo inválido';  
    exit;  
}  

// Verificar se o arquivo existe  
if (!file_exists($filePath)) {  
    header('HTTP/1.1 404 Not Found');  
    echo 'Arquivo não encontrado';  
    exit;  
}  

// Configurar cabeçalhos para download  
header('Content-Description: File Transfer');  
header('Content-Type: application/octet-stream');  
header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');  
header('Expires: 0');  
header('Cache-Control: must-revalidate');  
header('Pragma: public');  
header('Content-Length: ' . filesize($filePath));  

// Enviar o arquivo  
readfile($filePath);  
exit;  
?>