<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Iniciar captura de saída para garantir apenas JSON na resposta  
ob_start();  

// Hash para potencial uso de cache  
$filterHash = md5(json_encode($_GET));  
$cacheFile = __DIR__ . '/cache/estatisticas_' . $filterHash . '.json';  
$cacheTime = 300; // 5 minutos  

// Verificar se há cache disponível  
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {  
    ob_end_clean();  
    header('Content-Type: application/json');  
    header('X-Cache: HIT');  
    echo file_get_contents($cacheFile);  
    exit;  
}  

// Parâmetros de filtro  
$whereClauses = [];  
$params = [];  

// Filtros de data  
if (!empty($_GET['data_inicial'])) {  
    $whereClauses[] = "DATE(os.data_criacao) >= ?";  
    $params[] = $_GET['data_inicial'];  
}  

if (!empty($_GET['data_final'])) {  
    $whereClauses[] = "DATE(os.data_criacao) <= ?";  
    $params[] = $_GET['data_final'];  
}  

// Filtro de funcionário  
if (!empty($_GET['funcionario'])) {  
    $whereClauses[] = "os.criado_por = ?";  
    $params[] = $_GET['funcionario'];  
}  

// Construir a cláusula WHERE  
$whereClause = "";  
if (!empty($whereClauses)) {  
    $whereClause = "WHERE " . implode(" AND ", $whereClauses);  
}  

// Função para preparar e executar consultas parametrizadas  
function executeQuery($conn, $query, $params = []) {  
    $stmt = $conn->prepare($query);  
    
    if (!empty($params)) {  
        $types = str_repeat('s', count($params));  
        $stmt->bind_param($types, ...$params);  
    }  
    
    $stmt->execute();  
    return $stmt->get_result();  
}  

// Consulta para obter estatísticas  
$query = "  
    SELECT  
        COUNT(*) as total_os,  
        SUM(CASE WHEN EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id) THEN 1 ELSE 0 END) as total_pagas,  
        SUM(CASE WHEN os.status = 'Cancelado' THEN 1 ELSE 0 END) as total_canceladas,  
        SUM(CASE   
            WHEN EXISTS (  
                SELECT 1 FROM (  
                    SELECT   
                        ordem_servico_id,  
                        SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) = COUNT(*) as all_liquidated  
                    FROM ordens_de_servico_itens  
                    GROUP BY ordem_servico_id  
                ) temp   
                WHERE temp.ordem_servico_id = os.id AND temp.all_liquidated = 1  
            ) THEN 1 ELSE 0 END) as total_liquidadas,  
        SUM(CASE   
            WHEN EXISTS (  
                SELECT 1 FROM (  
                    SELECT   
                        ordem_servico_id,  
                        SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) as liquidated,  
                        SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) as pending,  
                        SUM(CASE WHEN status = 'parcialmente liquidado' THEN 1 ELSE 0 END) as partial,  
                        COUNT(*) as total  
                    FROM ordens_de_servico_itens  
                    GROUP BY ordem_servico_id  
                ) temp   
                WHERE temp.ordem_servico_id = os.id AND   
                ((temp.liquidated > 0 AND temp.pending > 0) OR temp.partial > 0)  
            ) THEN 1 ELSE 0 END) as total_parcialmente_liquidadas,  
        SUM(CASE   
            WHEN EXISTS (  
                SELECT 1 FROM (  
                    SELECT   
                        ordem_servico_id,  
                        SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) = COUNT(*) as all_pending  
                    FROM ordens_de_servico_itens  
                    GROUP BY ordem_servico_id  
                ) temp   
                WHERE temp.ordem_servico_id = os.id AND temp.all_pending = 1  
            ) AND os.status != 'Cancelado' THEN 1 ELSE 0 END) as total_pendentes_liquidacao,  
        SUM(CASE   
            WHEN NOT EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)   
            AND os.status != 'Cancelado' THEN 1 ELSE 0 END) as total_pendentes_pagamento  
    FROM ordens_de_servico os  
    $whereClause  
";  

$result = executeQuery($conn, $query, $params);  
$estatisticas = $result->fetch_assoc();  

// Formatar os números  
$estatisticas = array_map(function($value) {  
    return intval($value);  
}, $estatisticas);  

// Salvar cache  
if (!is_dir(__DIR__ . '/cache')) {  
    mkdir(__DIR__ . '/cache', 0755, true);  
}  
file_put_contents($cacheFile, json_encode($estatisticas));  

// Limpar qualquer saída anterior  
ob_end_clean();  

// Retornar resposta JSON  
header('Content-Type: application/json');  
header('X-Cache: MISS');  
echo json_encode($estatisticas);