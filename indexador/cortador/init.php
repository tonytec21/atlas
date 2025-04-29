<?php  
// init.php - Cria um monitoramento robusto de progresso  

// Verifica se foi fornecido um job_id  
$jobId = isset($_GET['job_id']) ? $_GET['job_id'] : null;  

if (!$jobId) {  
    die('ID de trabalho não fornecido');  
}  

$progressFile = __DIR__ . '/uploads/' . $jobId . '_progress.json';  
$monitorFile = __DIR__ . '/uploads/' . $jobId . '_monitor.php';  

// Cria um script PHP específico para este job que monitora o progresso  
$monitorScript = <<<EOT  
<?php  
// Monitor de progresso para job {$jobId}  
header('Content-Type: application/json');  

\$progressFile = '{$progressFile}';  
\$progressData = [  
    'current' => 0,  
    'total' => 0,  
    'percentage' => 0,  
    'status' => 'initializing',  
    'message' => 'Inicializando...',  
    'updated' => time()  
];  

// Criar o arquivo inicial se não existir  
if (!file_exists(\$progressFile)) {  
    file_put_contents(\$progressFile, json_encode(\$progressData));  
}  

// Verificar o arquivo  
clearstatcache(true, \$progressFile);  
if (file_exists(\$progressFile)) {  
    \$content = file_get_contents(\$progressFile);  
    \$data = json_decode(\$content, true);  
    if (\$data) {  
        \$progressData = \$data;  
        \$progressData['file_size'] = filesize(\$progressFile);  
        \$progressData['file_time'] = filemtime(\$progressFile);  
        \$progressData['monitor_time'] = time();  
    } else {  
        \$progressData['status'] = 'error';  
        \$progressData['message'] = 'Erro ao decodificar o arquivo de progresso';  
        \$progressData['raw_content'] = \$content;  
    }  
} else {  
    \$progressData['status'] = 'error';  
    \$progressData['message'] = 'Arquivo de progresso não encontrado';  
}  

echo json_encode(\$progressData);  
EOT;  

// Salvar o script monitor  
file_put_contents($monitorFile, $monitorScript);  
chmod($monitorFile, 0755);  

// Redirecionar para o monitor  
header('Location: uploads/' . basename($monitorFile) . '?t=' . time());