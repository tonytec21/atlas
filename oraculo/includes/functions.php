<?php  
// Funções auxiliares para o Oráculo Atlas  

/**  
 * Carrega as configurações do arquivo config.php  
 * @return array Configurações da aplicação  
 */  
function getConfig() {  
    static $config = null;  
    
    if ($config === null) {  
        $configPath = __DIR__ . '/../config.php';  
        
        if (!file_exists($configPath)) {  
            // Configurações padrão se o arquivo não existir  
            return [  
                'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',  
                'log_enabled' => true,  
                'log_path' => __DIR__ . '/../logs/app.log'  
            ];  
        }  
        
        $config = require $configPath;  
    }  
    
    return $config;  
}  

/**  
 * Registra erros no arquivo de log  
 * @param string $message Mensagem de erro  
 * @param array $data Dados adicionais  
 */  
function logError($message, $data = []) {  
    $config = getConfig();  
    
    if (!isset($config['log_enabled']) || !$config['log_enabled']) {  
        return;  
    }  
    
    $logPath = isset($config['log_path']) ? $config['log_path'] : __DIR__ . '/../logs/app.log';  
    $logDir = dirname($logPath);  
    
    // Criar diretório de logs se não existir  
    if (!is_dir($logDir)) {  
        if (!mkdir($logDir, 0755, true)) {  
            error_log("Não foi possível criar o diretório de logs: $logDir");  
            return;  
        }  
    }  
    
    $timestamp = date('Y-m-d H:i:s');  
    $dataStr = !empty($data) ? json_encode($data, JSON_PRETTY_PRINT) : '';  
    $logMessage = "[$timestamp] $message" . ($dataStr ? "\nData: $dataStr" : '') . "\n";  
    
    file_put_contents($logPath, $logMessage, FILE_APPEND);  
}  

/**  
 * Formata uma citação da web para exibição  
 * @param array $annotation Dados da anotação  
 * @return string HTML formatado da citação  
 */  
function formatCitation($annotation) {  
    if (!isset($annotation['text']) || !isset($annotation['file_citation'])) {  
        return '';  
    }  
    
    $text = htmlspecialchars($annotation['text']);  
    $citation = $annotation['file_citation']['metadata'];  
    
    $url = htmlspecialchars($citation['url'] ?? '#');  
    $title = htmlspecialchars($citation['title'] ?? 'Fonte desconhecida');  
    
    return "<div class='citation'>  
                <blockquote>$text</blockquote>  
                <a href='$url' target='_blank'>$title</a>  
            </div>";  
}  

/**  
 * Verifica se uma string é uma URL válida  
 * @param string $url URL a ser verificada  
 * @return bool True se for uma URL válida  
 */  
function isValidUrl($url) {  
    return filter_var($url, FILTER_VALIDATE_URL) !== false;  
}  
?>