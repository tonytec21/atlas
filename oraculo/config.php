<?php  
require_once __DIR__ . '/db_connection.php';  

function getApiKey() {  
    try {  
        $pdo = getDatabaseConnection();  
        $stmt = $pdo->query("SELECT chave FROM chave_api_oraculo WHERE ativo = 1 ORDER BY id DESC LIMIT 1");  
        $result = $stmt->fetch(PDO::FETCH_ASSOC);  
        
        if ($result && isset($result['chave'])) {  
            return $result['chave'];  
        } else {  
            error_log('Nenhuma chave de API ativa encontrada no banco de dados.');  
            return null;  
        }  
    } catch (\PDOException $e) {  
        error_log('Erro ao buscar chave de API: ' . $e->getMessage());  
        return null;  
    }  
}  

return [  
    'openai_api_key' => getApiKey(),   
    'model' => 'gpt-4o-mini-search-preview',        
    'max_tokens' => 4000,  
    'temperature' => 0.7,  
    'base_path' => '/atlas/oraculo',                 
    'default_search_context_size' => 'medium',       
    'default_user_location' => [                   
        'country' => 'BR',                         
        'city' => 'São Paulo',                     
        'region' => 'São Paulo'                    
    ]  
];  
?>