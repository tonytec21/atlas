<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Verificar se o arquivo de cache existe e está atualizado  
$cache_file = __DIR__ . '/cache/funcionarios.json';  
$cache_time = 3600; // 1 hora de cache  

if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {  
    // Usar cache se estiver disponível e atualizado  
    header('Content-Type: application/json');  
    echo file_get_contents($cache_file);  
    exit;  
}  

try {  
    // Obter lista distinta de funcionários que criaram OS  
    $query = "  
        SELECT DISTINCT criado_por  
        FROM ordens_de_servico  
        WHERE criado_por IS NOT NULL AND criado_por != ''  
        ORDER BY criado_por ASC  
    ";  
    
    $result = $conn->query($query);  
    
    $funcionarios = [];  
    while ($row = $result->fetch_assoc()) {  
        $funcionarios[] = $row['criado_por'];  
    }  
    
    // Criar diretório de cache se não existir  
    if (!is_dir(__DIR__ . '/cache')) {  
        mkdir(__DIR__ . '/cache', 0755, true);  
    }  
    
    // Salvar em cache  
    file_put_contents($cache_file, json_encode($funcionarios));  
    
    // Retornar resultado  
    header('Content-Type: application/json');  
    echo json_encode($funcionarios);  
    
} catch (Exception $e) {  
    // Log do erro  
    error_log('Erro ao buscar funcionários: ' . $e->getMessage());  
    
    // Retornar erro  
    header('Content-Type: application/json');  
    http_response_code(500);  
    echo json_encode(['error' => 'Erro ao buscar funcionários: ' . $e->getMessage()]);  
}