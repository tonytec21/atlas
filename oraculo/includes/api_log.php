<?php  
function logApiCall($model, $prompt, $response, $usage = null) {  
    $logEntry = [  
        'timestamp' => date('Y-m-d H:i:s'),  
        'model' => $model,  
        'prompt_tokens' => $usage['prompt_tokens'] ?? 0,  
        'completion_tokens' => $usage['completion_tokens'] ?? 0,  
        'total_tokens' => $usage['total_tokens'] ?? 0,  
        'has_search' => isset($response['tool_calls']) ? 'Sim' : 'Não'  
    ];  
    
    $logFile = __DIR__ . '/../logs/api_usage.log';  
    $dir = dirname($logFile);  
    
    if (!is_dir($dir)) {  
        mkdir($dir, 0755, true);  
    }  
    
    file_put_contents(  
        $logFile,   
        json_encode($logEntry) . "\n",   
        FILE_APPEND  
    );  
}  
?>