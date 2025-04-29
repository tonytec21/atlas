<?php  
header('Content-Type: application/json');  

// Verificar se o ID do trabalho foi fornecido  
if (!isset($_GET['job_id'])) {  
    echo json_encode(['error' => 'ID de trabalho não fornecido']);  
    exit;  
}  

$jobId = $_GET['job_id'];  
$uploadDir = __DIR__ . '/uploads/';  
$progressFile = $uploadDir . $jobId . '_progress.json';  
$outputFile = $uploadDir . $jobId . '_output.zip';  
$logFile = $uploadDir . $jobId . '_log.txt';  

// Verificar se o arquivo de progresso existe  
if (!file_exists($progressFile)) {  
    echo json_encode([  
        'error' => 'Arquivo de progresso não encontrado',  
        'status' => 'error',  
        'message' => 'O trabalho especificado não existe ou foi removido'  
    ]);  
    exit;  
}  

// Ler o conteúdo do arquivo de progresso  
$progressContent = file_get_contents($progressFile);  
$progress = json_decode($progressContent, true);  

// Verificar se o formato é válido  
if (!$progress || !is_array($progress)) {  
    echo json_encode([  
        'error' => 'Formato de progresso inválido',  
        'status' => 'error',  
        'message' => 'O arquivo de progresso está corrompido'  
    ]);  
    exit;  
}  

// Se o processamento estiver concluído, adicionar URL de download  
if (isset($progress['status']) && $progress['status'] === 'completed' && file_exists($outputFile)) {  
    $progress['download_url'] = 'download.php?file=' . $jobId . '_output.zip';  
}  

// Se o processamento estiver demorando muito (mais de 30 segundos) e sem progresso  
$startTime = filemtime($progressFile);  
$elapsedTime = time() - $startTime;  
if ($elapsedTime > 30 && isset($progress['percentage']) && $progress['percentage'] == 0) {  
    // Verificar o log para diagnosticar problemas  
    if (file_exists($logFile)) {  
        $logContent = file_get_contents($logFile);  
        if (strpos($logContent, 'Error') !== false || strpos($logContent, 'Exception') !== false) {  
            $progress['status'] = 'error';  
            $progress['message'] = 'Erro detectado no processamento. Verifique o log para mais detalhes.';  
        } else {  
            $progress['status'] = 'warning';  
            $progress['message'] = 'O processamento está demorando mais do que o esperado...';  
        }  
    }  
}  

// Retornar informações de progresso  
echo json_encode($progress);  
?>