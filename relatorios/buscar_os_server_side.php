<?php  
// Iniciar captura de saída para evitar qualquer output antes do JSON  
ob_start();  

include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Verificar conexão  
if ($conn->connect_error) {  
    header('Content-Type: application/json');  
    echo json_encode([  
        'draw' => 1,  
        'recordsTotal' => 0,  
        'recordsFiltered' => 0,  
        'data' => [],  
        'error' => 'Falha na conexão com o banco de dados'  
    ]);  
    exit;  
}  

// Parâmetros do DataTables  
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;  
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;  
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;  

// Ordem  
$orderColumn = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;  
$orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'desc';  

// Mapeamento de colunas  
$columns = [  
    0 => 'os.id',  
    1 => 'os.cliente',  
    2 => 'os.cpf_cliente',  
    3 => 'os.total_os',  
    4 => 'os.data_criacao',  
    5 => 'os.criado_por',  
    6 => 'situacao',  
    7 => 'status',  
    8 => 'deposito_previo',  
    9 => 'atos_praticados'  
];  

$orderByColumn = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'os.id';  

// Busca  
$search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';  

// Montar a consulta base  
$baseQuery = "FROM ordens_de_servico os";  

// Condições WHERE  
$whereConditions = [];  
$whereParams = [];  

// Filtros  
if (!empty($_POST['data_inicial'])) {  
    $whereConditions[] = "DATE(os.data_criacao) >= ?";  
    $whereParams[] = $_POST['data_inicial'];  
}  

if (!empty($_POST['data_final'])) {  
    $whereConditions[] = "DATE(os.data_criacao) <= ?";  
    $whereParams[] = $_POST['data_final'];  
}  

if (!empty($_POST['funcionario'])) {  
    $whereConditions[] = "os.criado_por = ?";  
    $whereParams[] = $_POST['funcionario'];  
}  

if (!empty($_POST['situacao'])) {  
    if ($_POST['situacao'] === 'Cancelada') {  
        $whereConditions[] = "os.status = 'Cancelado'";  
    } else if ($_POST['situacao'] === 'Paga') {  
        $whereConditions[] = "EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)";  
    } else if ($_POST['situacao'] === 'Pendente de Pagamento') {  
        $whereConditions[] = "NOT EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id) AND os.status != 'Cancelado'";  
    }  
}  

if (!empty($_POST['status'])) {  
    if ($_POST['status'] === 'Liquidada') {  
        $whereConditions[] = "EXISTS (  
            SELECT 1 FROM (  
                SELECT   
                    ordem_servico_id,  
                    SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) = COUNT(*) as all_liquidated  
                FROM ordens_de_servico_itens  
                GROUP BY ordem_servico_id  
            ) temp   
            WHERE temp.ordem_servico_id = os.id AND temp.all_liquidated = 1  
        )";  
    } else if ($_POST['status'] === 'Parcialmente Liquidada') {  
        $whereConditions[] = "EXISTS (  
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
        )";  
    } else if ($_POST['status'] === 'Pendente de Liquidação') {  
        $whereConditions[] = "EXISTS (  
            SELECT 1 FROM (  
                SELECT   
                    ordem_servico_id,  
                    SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) = COUNT(*) as all_pending  
                FROM ordens_de_servico_itens  
                GROUP BY ordem_servico_id  
            ) temp   
            WHERE temp.ordem_servico_id = os.id AND temp.all_pending = 1  
        ) AND os.status != 'Cancelado'";  
    } else if ($_POST['status'] === 'Cancelada') {  
        $whereConditions[] = "os.status = 'Cancelado'";  
    }  
}  

// Busca global  
if (!empty($search)) {  
    $searchConditions = [  
        "os.id LIKE ?",  
        "os.cliente LIKE ?",  
        "os.cpf_cliente LIKE ?",  
        "os.criado_por LIKE ?"  
    ];  
    
    $whereConditions[] = "(" . implode(" OR ", $searchConditions) . ")";  
    
    $searchTerm = "%{$search}%";  
    $whereParams[] = $searchTerm;  
    $whereParams[] = $searchTerm;  
    $whereParams[] = $searchTerm;  
    $whereParams[] = $searchTerm;  
}  

// Montar a cláusula WHERE  
$whereClause = "";  
if (!empty($whereConditions)) {  
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);  
}  

// Consulta para contar os registros totais (sem filtros)  
$countQuery = "SELECT COUNT(*) as total FROM ordens_de_servico";  

$stmt = $conn->prepare($countQuery);  
$stmt->execute();  
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];  

// Consulta para contar os registros filtrados  
$countFilteredQuery = "SELECT COUNT(*) as total $baseQuery $whereClause";  

$stmt = $conn->prepare($countFilteredQuery);  
if (!empty($whereParams)) {  
    $types = str_repeat('s', count($whereParams));  
    $stmt->bind_param($types, ...$whereParams);  
}  
$stmt->execute();  
$filteredRecords = $stmt->get_result()->fetch_assoc()['total'];  

// Consulta principal  
$mainQuery = "  
    SELECT   
        os.id,  
        os.cliente,  
        os.cpf_cliente,  
        FORMAT(os.total_os, 2, 'de_DE') as total_os,  
        DATE_FORMAT(os.data_criacao, '%d/%m/%Y') as data_criacao,  
        os.criado_por,  
        CASE  
            WHEN os.status = 'Cancelado' THEN 'Cancelada'  
            WHEN EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id) THEN 'Paga'  
            ELSE 'Pendente de Pagamento'  
        END as situacao,  
        CASE  
            WHEN os.status = 'Cancelado' THEN 'Cancelada'  
            WHEN EXISTS (  
                SELECT 1 FROM (  
                    SELECT   
                        ordem_servico_id,  
                        SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) = COUNT(*) as all_liquidated  
                    FROM ordens_de_servico_itens  
                    GROUP BY ordem_servico_id  
                ) temp   
                WHERE temp.ordem_servico_id = os.id AND temp.all_liquidated = 1  
            ) THEN 'Liquidada'  
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
            ) THEN 'Parcialmente Liquidada'  
            ELSE 'Pendente de Liquidação'  
        END as status,  
        IFNULL(  
            (SELECT CONCAT('R$ ', FORMAT(SUM(total_pagamento), 2, 'de_DE')) FROM pagamento_os WHERE ordem_de_servico_id = os.id),  
            '---'  
        ) as deposito_previo,  
        CONCAT('R$ ', FORMAT(  
            IFNULL((SELECT SUM(total) FROM atos_liquidados WHERE ordem_servico_id = os.id), 0) +  
            IFNULL((SELECT SUM(total) FROM atos_manuais_liquidados WHERE ordem_servico_id = os.id), 0)  
        , 2, 'de_DE')) as atos_praticados  
    $baseQuery  
    $whereClause  
    ORDER BY $orderByColumn $orderDir  
    LIMIT ?, ?  
";  

// Preparar e executar a consulta principal  
$stmt = $conn->prepare($mainQuery);  

if (!empty($whereParams)) {  
    $types = str_repeat('s', count($whereParams)) . 'ii';  
    $bindParams = array_merge($whereParams, [$start, $length]);  
    $stmt->bind_param($types, ...$bindParams);  
} else {  
    $stmt->bind_param('ii', $start, $length);  
}  

$stmt->execute();  
$result = $stmt->get_result();  

// Formatar os dados para o DataTables  
$data = [];  
while ($row = $result->fetch_assoc()) {  
    // Formatar os valores monetários  
    $row['total_os'] = 'R$ ' . $row['total_os'];  
    
    $data[] = $row;  
}  

// Remover qualquer saída anterior  
ob_end_clean();  

// Enviar resposta JSON  
header('Content-Type: application/json');  
echo json_encode([  
    'draw' => $draw,  
    'recordsTotal' => $totalRecords,  
    'recordsFiltered' => $filteredRecords,  
    'data' => $data  
]);