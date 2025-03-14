<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Construir condições WHERE baseadas nos filtros  
$whereConditions = [];  
$params = [];  

// Filtros de data  
if (!empty($_GET['data_inicial'])) {  
    $whereConditions[] = "DATE(os.data_criacao) >= ?";  
    $params[] = $_GET['data_inicial'];  
}  

if (!empty($_GET['data_final'])) {  
    $whereConditions[] = "DATE(os.data_criacao) <= ?";  
    $params[] = $_GET['data_final'];  
}  

// Filtro de funcionário  
if (!empty($_GET['funcionario'])) {  
    $whereConditions[] = "os.criado_por = ?";  
    $params[] = $_GET['funcionario'];  
}  

// Filtro de situação  
if (!empty($_GET['situacao'])) {  
    if ($_GET['situacao'] === 'Cancelada') {  
        $whereConditions[] = "os.status = 'Cancelado'";  
    } else if ($_GET['situacao'] === 'Paga') {  
        $whereConditions[] = "EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)";  
    } else if ($_GET['situacao'] === 'Pendente de Pagamento') {  
        $whereConditions[] = "NOT EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id) AND os.status != 'Cancelado'";  
    }  
}  

// Filtro de status  
if (!empty($_GET['status'])) {  
    if ($_GET['status'] === 'Liquidada') {  
        $whereConditions[] = "EXISTS (  
            SELECT 1 FROM (  
                SELECT   
                    ordem_servico_id,  
                    SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) = COUNT(*) AS is_liquidada  
                FROM ordens_de_servico_itens  
                GROUP BY ordem_servico_id  
            ) liq   
            WHERE liq.ordem_servico_id = os.id AND liq.is_liquidada = 1  
        )";  
    } else if ($_GET['status'] === 'Parcialmente Liquidada') {  
        $whereConditions[] = "EXISTS (  
            SELECT 1 FROM (  
                SELECT   
                    ordem_servico_id,  
                    SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) > 0 AS has_liquidado,  
                    SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) > 0 AS has_pendente,  
                    SUM(CASE WHEN status = 'parcialmente liquidado' THEN 1 ELSE 0 END) > 0 AS has_parcial  
                FROM ordens_de_servico_itens  
                GROUP BY ordem_servico_id  
            ) liq   
            WHERE liq.ordem_servico_id = os.id   
            AND ((liq.has_liquidado = 1 AND liq.has_pendente = 1) OR liq.has_parcial = 1)  
        )";  
    } else if ($_GET['status'] === 'Pendente de Liquidação') {  
        $whereConditions[] = "EXISTS (  
            SELECT 1 FROM (  
                SELECT   
                    ordem_servico_id,  
                    SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) = COUNT(*) AS all_pendente  
                FROM ordens_de_servico_itens  
                GROUP BY ordem_servico_id  
            ) liq   
            WHERE liq.ordem_servico_id = os.id AND liq.all_pendente = 1  
        ) AND os.status != 'Cancelado'";  
    } else if ($_GET['status'] === 'Cancelada') {  
        $whereConditions[] = "os.status = 'Cancelado'";  
    }  
}  

// Construir WHERE  
$whereClause = '';  
if (!empty($whereConditions)) {  
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);  
}  

// Realizar uma única consulta para obter todas as estatísticas  
// Isso é mais eficiente do que fazer uma consulta para cada card  
$query = "  
    SELECT  
        COUNT(*) AS total_os,  
        
        SUM(CASE   
            WHEN EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)   
            THEN 1 ELSE 0   
        END) AS os_pagas,  
        
        SUM(CASE   
            WHEN NOT EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id) AND os.status != 'Cancelado'   
            THEN 1 ELSE 0   
        END) AS os_pendentes_pagamento,  
        
        SUM(CASE   
            WHEN EXISTS (  
                SELECT 1 FROM (  
                    SELECT   
                        ordem_servico_id,  
                        SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) = COUNT(*) AS is_liquidada  
                    FROM ordens_de_servico_itens  
                    GROUP BY ordem_servico_id  
                ) liq   
                WHERE liq.ordem_servico_id = os.id AND liq.is_liquidada = 1  
            )   
            THEN 1 ELSE 0   
        END) AS liquidadas,  
        
        SUM(CASE   
            WHEN EXISTS (  
                SELECT 1 FROM (  
                    SELECT   
                        ordem_servico_id,  
                        SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) > 0 AS has_liquidado,  
                        SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) > 0 AS has_pendente,  
                        SUM(CASE WHEN status = 'parcialmente liquidado' THEN 1 ELSE 0 END) > 0 AS has_parcial  
                    FROM ordens_de_servico_itens  
                    GROUP BY ordem_servico_id  
                ) liq   
                WHERE liq.ordem_servico_id = os.id   
                AND ((liq.has_liquidado = 1 AND liq.has_pendente = 1) OR liq.has_parcial = 1)  
            )   
            THEN 1 ELSE 0   
        END) AS parcialmente_liquidadas,  
        
        SUM(CASE   
            WHEN os.status = 'Cancelado'   
            THEN 1 ELSE 0   
        END) AS canceladas,  
        
        SUM(CASE   
            WHEN EXISTS (  
                SELECT 1 FROM (  
                    SELECT   
                        ordem_servico_id,  
                        SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) = COUNT(*) AS all_pendente  
                    FROM ordens_de_servico_itens  
                    GROUP BY ordem_servico_id  
                ) liq   
                WHERE liq.ordem_servico_id = os.id AND liq.all_pendente = 1  
            ) AND os.status != 'Cancelado'  
            THEN 1 ELSE 0   
        END) AS pendentes  
        
    FROM ordens_de_servico os  
    $whereClause  
";  

try {  
    $stmt = $conn->prepare($query);  
    
    if (!empty($params)) {  
        $stmt->execute($params);  
    } else {  
        $stmt->execute();  
    }  
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);  
    
    // Garantir que todos os campos existam, mesmo que sejam zero  
    $response = [  
        'total_os' => $result['total_os'] ?? 0,  
        'os_pagas' => $result['os_pagas'] ?? 0,  
        'os_pendentes_pagamento' => $result['os_pendentes_pagamento'] ?? 0,  
        'liquidadas' => $result['liquidadas'] ?? 0,  
        'parcialmente_liquidadas' => $result['parcialmente_liquidadas'] ?? 0,  
        'canceladas' => $result['canceladas'] ?? 0,  
        'pendentes' => $result['pendentes'] ?? 0  
    ];  
    
    header('Content-Type: application/json');  
    echo json_encode($response);  
} catch (PDOException $e) {  
    // Log do erro  
    error_log('Erro na consulta de resumo: ' . $e->getMessage());  
    
    // Retornar erro em formato JSON  
    header('Content-Type: application/json');  
    http_response_code(500);  
    echo json_encode(['error' => 'Erro ao processar consulta: ' . $e->getMessage()]);  
}