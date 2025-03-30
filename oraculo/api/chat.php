<?php  
// Configuração para capturar erros em formato JSON  
ini_set('display_errors', 0);  
ini_set('log_errors', 1);  
error_reporting(E_ALL);  

// Garantir que a saída seja sempre JSON  
header('Content-Type: application/json');  

// Configuração de CORS  
header('Access-Control-Allow-Origin: *');  
header('Access-Control-Allow-Methods: POST, OPTIONS');  
header('Access-Control-Allow-Headers: Content-Type');  

// Tratamento de preflight requests  
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {  
    http_response_code(200);  
    exit;  
}  

// Log function  
function logDebug($message, $data = null) {  
    $logDir = __DIR__ . '/../logs';  
    if (!is_dir($logDir)) {  
        mkdir($logDir, 0755, true);  
    }  
    
    $logFile = $logDir . '/api.log';  
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;  
    if ($data !== null) {  
        $logMessage .= ' - ' . json_encode($data);  
    }  
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);  
}  

// Tratamento de exceções para sempre retornar JSON  
set_exception_handler(function($e) {  
    logDebug("Exception: " . $e->getMessage());  
    http_response_code(500);  
    echo json_encode([  
        'error' => 'Erro interno do servidor',  
        'message' => $e->getMessage(),  
        'file' => $e->getFile(),  
        'line' => $e->getLine()  
    ]);  
    exit;  
});  

try {  
    // Incluir funções auxiliares  
    require_once __DIR__ . '/../includes/functions.php';  

    // Verificar método  
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {  
        http_response_code(405);  
        echo json_encode(['error' => 'Método não permitido']);  
        exit;  
    }  

    // Obter e validar dados de entrada  
    $rawInput = file_get_contents('php://input');  
    logDebug("Raw input received", substr($rawInput, 0, 1000));  
    
    $input = json_decode($rawInput, true);  
    if ($input === null) {  
        http_response_code(400);  
        echo json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]);  
        exit;  
    }  

    if (!isset($input['messages']) || empty($input['messages'])) {  
        http_response_code(400);  
        echo json_encode(['error' => 'Campo "messages" não fornecido ou vazio']);  
        exit;  
    }  

    // Obter a chave de API  
    $apiConfig = getConfig();  
    if (empty($apiConfig['openai_api_key'])) {  
        http_response_code(500);  
        echo json_encode(['error' => 'Chave de API OpenAI não configurada']);  
        exit;  
    }  
    $apiKey = $apiConfig['openai_api_key'];  

    // Preparar configurações de API  
    $config = [  
        'model' => 'gpt-3.5-turbo', // Modelo padrão (fallback)  
        'messages' => $input['messages']  
    ];  

    // Adicionar opções de pesquisa na web se solicitado  
    if (isset($input['search_context_size'])) {  
        // Usar modelo com suporte a pesquisa  
        $config['model'] = 'gpt-4o-mini-search-preview';  
        
        // Adicionar contexto de pesquisa  
        $config['web_search_options'] = [  
            'search_context_size' => $input['search_context_size']  
        ];  
        
        // Adicionar localização do usuário se disponível  
        if (isset($input['user_location']) && is_array($input['user_location'])) {  
            $location = [];  
            
            if (!empty($input['user_location']['country'])) {  
                $location['country'] = $input['user_location']['country'];  
            }  
            
            if (!empty($input['user_location']['city'])) {  
                $location['city'] = $input['user_location']['city'];  
            }  
            
            if (!empty($input['user_location']['region'])) {  
                $location['region'] = $input['user_location']['region'];  
            }  
            
            if (!empty($location)) {  
                $config['web_search_options']['user_location'] = [  
                    'approximate' => $location  
                ];  
            }  
        }  
    }  

    // Log dados da requisição  
    logDebug("Prepared request data", $config);  

    // Chamar a API da OpenAI  
    $ch = curl_init('https://api.openai.com/v1/chat/completions');  
    curl_setopt_array($ch, [  
        CURLOPT_RETURNTRANSFER => true,  
        CURLOPT_POST => true,  
        CURLOPT_HTTPHEADER => [  
            'Content-Type: application/json',  
            'Authorization: Bearer ' . $apiKey  
        ],  
        CURLOPT_POSTFIELDS => json_encode($config),  
        CURLOPT_TIMEOUT => 60,  
        CURLOPT_SSL_VERIFYPEER => true  
    ]);  

    $response = curl_exec($ch);  
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);  
    $error = curl_error($ch);  
    curl_close($ch);  

    // Log da resposta da API  
    logDebug("API response (HTTP $httpCode)", substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''));  

    // Verificar erros de cURL  
    if ($error) {  
        http_response_code(500);  
        echo json_encode(['error' => 'Erro de conexão: ' . $error]);  
        exit;  
    }  

    // Verificar código HTTP  
    if ($httpCode !== 200) {  
        // Processar erro da API  
        $errorData = json_decode($response, true);  
        $errorMessage = isset($errorData['error']['message'])   
            ? $errorData['error']['message']   
            : "Erro na API: HTTP $httpCode";  
            
        http_response_code($httpCode);  
        echo json_encode([  
            'error' => $errorMessage,  
            'details' => $errorData ?? null  
        ]);  
        exit;  
    }  

    // Processar resposta bem-sucedida  
    $data = json_decode($response, true);  
    if (!$data || !isset($data['choices'][0]['message']['content'])) {  
        http_response_code(500);  
        echo json_encode(['error' => 'Resposta inválida da API']);  
        exit;  
    }  

    // Extrair dados relevantes  
    $result = [  
        'message' => $data['choices'][0]['message']['content'],  
        'usage' => $data['usage'] ?? []  
    ];  

    // Adicionar anotações para citações, se disponíveis  
    if (isset($data['choices'][0]['message']['annotations'])) {  
        $result['annotations'] = $data['choices'][0]['message']['annotations'];  
        $result['used_search'] = true;  
    } else {  
        $result['annotations'] = [];  
        $result['used_search'] = false;  
    }  

    // Enviar resposta de sucesso  
    echo json_encode($result);  

} catch (Exception $e) {  
    logDebug("Exception", $e->getMessage());  
    http_response_code(500);  
    echo json_encode([  
        'error' => 'Erro interno do servidor',  
        'message' => $e->getMessage()  
    ]);  
}  
?>