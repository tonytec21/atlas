<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Construir a cláusula WHERE com base nos filtros  
$whereConditions = [];  

// Filtros  
if (!empty($_GET['dia'])) {  
    $dia = $conn->real_escape_string($_GET['dia']);  
    $whereConditions[] = "DATE(os.data_criacao) = '$dia'";  
}  

if (!empty($_GET['mes'])) {  
    $mes = $conn->real_escape_string($_GET['mes']);  
    $whereConditions[] = "DATE_FORMAT(os.data_criacao, '%Y-%m') = '$mes'";  
}  

if (!empty($_GET['ano'])) {  
    $ano = $conn->real_escape_string($_GET['ano']);  
    $whereConditions[] = "YEAR(os.data_criacao) = '$ano'";  
}  

// Novos filtros de data inicial e final  
if (!empty($_GET['data_inicial'])) {  
    $dataInicial = $conn->real_escape_string($_GET['data_inicial']);  
    $whereConditions[] = "DATE(os.data_criacao) >= '$dataInicial'";  
}  

if (!empty($_GET['data_final'])) {  
    $dataFinal = $conn->real_escape_string($_GET['data_final']);  
    $whereConditions[] = "DATE(os.data_criacao) <= '$dataFinal'";  
}  

if (!empty($_GET['funcionario'])) {  
    $funcionario = $conn->real_escape_string($_GET['funcionario']);  
    $whereConditions[] = "os.criado_por = '$funcionario'";  
}  

if (!empty($_GET['situacao'])) {  
    $situacao = $conn->real_escape_string($_GET['situacao']);  
    if ($situacao === 'Cancelada') {  
        $whereConditions[] = "os.status = 'Cancelado'";  
    } elseif ($situacao === 'Paga') {  
        $whereConditions[] = "EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)";  
    } elseif ($situacao === 'Pendente de Pagamento') {  
        $whereConditions[] = "NOT EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)   
                              AND os.status != 'Cancelado'";  
    }  
}  

if (!empty($_GET['status'])) {  
    $status = $conn->real_escape_string($_GET['status']);  
    if ($status === 'Liquidada') {  
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
    } elseif ($status === 'Parcialmente Liquidada') {  
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
    } elseif ($status === 'Pendente de Liquidação') {  
        $whereConditions[] = "EXISTS (  
            SELECT 1 FROM (  
                SELECT   
                    ordem_servico_id,  
                    SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) = COUNT(*) AS all_pendente  
                FROM ordens_de_servico_itens  
                GROUP BY ordem_servico_id  
            ) liq   
            WHERE liq.ordem_servico_id = os.id AND liq.all_pendente = 1  
        )";  
    } elseif ($status === 'Cancelada') {  
        $whereConditions[] = "os.status = 'Cancelado'";  
    }  
}  

// Montar WHERE  
$whereClause = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";  

// Consulta única otimizada para todos os contadores  
$query = "  
SELECT  
    (  
        SELECT COUNT(*)   
        FROM ordens_de_servico os   
        $whereClause  
    ) AS total_os,  
    (  
        SELECT COUNT(*)   
        FROM ordens_de_servico os   
        WHERE EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)  
        " . ($whereClause ? "AND " . implode(" AND ", array_filter($whereConditions, function($cond) { return strpos($cond, "EXISTS (SELECT 1 FROM pagamento_os") === false; })) : "") . "  
    ) AS os_pagas,  
    (  
        SELECT COUNT(*)   
        FROM ordens_de_servico os   
        WHERE NOT EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)  
        AND os.status != 'Cancelado'  
        " . ($whereClause ? "AND " . implode(" AND ", array_filter($whereConditions, function($cond) { return strpos($cond, "EXISTS (SELECT 1 FROM pagamento_os") === false; })) : "") . "  
    ) AS os_pendentes_pagamento,  
    (  
        SELECT COUNT(*)   
        FROM ordens_de_servico os   
        WHERE os.status = 'Cancelado'  
        " . ($whereClause ? "AND " . implode(" AND ", array_filter($whereConditions, function($cond) { return strpos($cond, "os.status = 'Cancelado'") === false; })) : "") . "  
    ) AS canceladas,  
    (  
        SELECT COUNT(*)   
        FROM ordens_de_servico os   
        WHERE EXISTS (  
            SELECT 1 FROM (  
                SELECT   
                    ordem_servico_id,  
                    SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) = COUNT(*) AS is_liquidada  
                FROM ordens_de_servico_itens  
                GROUP BY ordem_servico_id  
            ) liq   
            WHERE liq.ordem_servico_id = os.id AND liq.is_liquidada = 1  
        )  
        " . ($whereClause ? "AND " . implode(" AND ", array_filter($whereConditions, function($cond) { return strpos($cond, "EXISTS (") === false || strpos($cond, "is_liquidada") === false; })) : "") . "  
    ) AS liquidadas,  
    (  
        SELECT COUNT(*)   
        FROM ordens_de_servico os   
        WHERE EXISTS (  
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
        " . ($whereClause ? "AND " . implode(" AND ", array_filter($whereConditions, function($cond) { return strpos($cond, "EXISTS (") === false || strpos($cond, "has_parcial") === false; })) : "") . "  
    ) AS parcialmente_liquidadas,  
    (  
        SELECT COUNT(*)   
        FROM ordens_de_servico os   
        WHERE EXISTS (  
            SELECT 1 FROM (  
                SELECT   
                    ordem_servico_id,  
                    SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) = COUNT(*) AS all_pendente  
                FROM ordens_de_servico_itens  
                GROUP BY ordem_servico_id  
            ) liq   
            WHERE liq.ordem_servico_id = os.id AND liq.all_pendente = 1  
        )  
        AND os.status != 'Cancelado'  
        " . ($whereClause ? "AND " . implode(" AND ", array_filter($whereConditions, function($cond) { return strpos($cond, "EXISTS (") === false || strpos($cond, "all_pendente") === false; })) : "") . "  
    ) AS pendentes  
";  

$result = $conn->query($query);  

if ($result === false) {  
    // Tratamento de erro  
    $response = [  
        'error' => true,  
        'message' => 'Erro ao executar consulta: ' . $conn->error,  
        'total_os' => 0,  
        'os_pagas' => 0,  
        'os_pendentes_pagamento' => 0,  
        'canceladas' => 0,  
        'liquidadas' => 0,  
        'parcialmente_liquidadas' => 0,  
        'pendentes' => 0  
    ];  
} else {  
    $response = $result->fetch_assoc();  
}  

// Retornar resposta  
header('Content-Type: application/json');  
echo json_encode($response);