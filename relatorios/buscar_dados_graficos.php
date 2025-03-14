<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Iniciar captura de saída para garantir apenas JSON na resposta  
ob_start();  

// Hash para potencial uso de cache  
$filterHash = md5(json_encode($_GET));  
$cacheFile = __DIR__ . '/cache/graficos_' . $filterHash . '.json';  
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

// Filtro de status  
if (!empty($_GET['status'])) {  
    if ($_GET['status'] === 'Liquidada') {  
        $whereClauses[] = "  
            EXISTS (  
                SELECT 1 FROM (  
                    SELECT   
                        ordem_servico_id,  
                        SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) = COUNT(*) as all_liquidated  
                    FROM ordens_de_servico_itens  
                    GROUP BY ordem_servico_id  
                ) temp   
                WHERE temp.ordem_servico_id = os.id AND temp.all_liquidated = 1  
            )  
        ";  
    } else if ($_GET['status'] === 'Parcialmente Liquidada') {  
        $whereClauses[] = "  
            EXISTS (  
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
            )  
        ";  
    } else if ($_GET['status'] === 'Pendente de Liquidação') {  
        $whereClauses[] = "  
            EXISTS (  
                SELECT 1 FROM (  
                    SELECT   
                        ordem_servico_id,  
                        SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) = COUNT(*) as all_pending  
                    FROM ordens_de_servico_itens  
                    GROUP BY ordem_servico_id  
                ) temp   
                WHERE temp.ordem_servico_id = os.id AND temp.all_pending = 1  
            ) AND os.status != 'Cancelado'  
        ";  
    } else if ($_GET['status'] === 'Cancelada') {  
        $whereClauses[] = "os.status = 'Cancelado'";  
    }  
}  

// Filtro de situação  
if (!empty($_GET['situacao'])) {  
    if ($_GET['situacao'] === 'Cancelada') {  
        $whereClauses[] = "os.status = 'Cancelado'";  
    } else if ($_GET['situacao'] === 'Paga') {  
        $whereClauses[] = "EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)";  
    } else if ($_GET['situacao'] === 'Pendente de Pagamento') {  
        $whereClauses[] = "NOT EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id) AND os.status != 'Cancelado'";  
    }  
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

// 1. Gráfico de OS por mês  
$queryOSPorMes = "  
    SELECT   
        DATE_FORMAT(os.data_criacao, '%Y-%m') AS mes,  
        COUNT(*) AS total  
    FROM ordens_de_servico os  
    $whereClause  
    GROUP BY DATE_FORMAT(os.data_criacao, '%Y-%m')  
    ORDER BY mes ASC  
    LIMIT 12  
";  

$resultOSPorMes = executeQuery($conn, $queryOSPorMes, $params);  
$osMes = ['labels' => [], 'data' => []];  
while ($row = $resultOSPorMes->fetch_assoc()) {  
    $osMes['labels'][] = $row['mes'];  
    $osMes['data'][] = intval($row['total']);  
}  

// 2. Gráfico de OS por semana  
$queryOSPorSemana = "  
    SELECT   
        CONCAT(YEAR(os.data_criacao), '-', WEEK(os.data_criacao)) AS semana,  
        COUNT(*) AS total  
    FROM ordens_de_servico os  
    $whereClause  
    GROUP BY YEAR(os.data_criacao), WEEK(os.data_criacao)  
    ORDER BY YEAR(os.data_criacao) ASC, WEEK(os.data_criacao) ASC  
    LIMIT 12  
";  

$resultOSPorSemana = executeQuery($conn, $queryOSPorSemana, $params);  
$osSemana = ['labels' => [], 'data' => []];  
while ($row = $resultOSPorSemana->fetch_assoc()) {  
    $osSemana['labels'][] = $row['semana'];  
    $osSemana['data'][] = intval($row['total']);  
}  

// 3. Gráfico de faturamento por mês  
$queryFaturamentoPorMes = "  
    SELECT   
        DATE_FORMAT(os.data_criacao, '%Y-%m') AS mes,  
        SUM(os.total_os) AS total  
    FROM ordens_de_servico os  
    $whereClause  
    GROUP BY DATE_FORMAT(os.data_criacao, '%Y-%m')  
    ORDER BY mes ASC  
    LIMIT 12  
";  

$resultFaturamentoPorMes = executeQuery($conn, $queryFaturamentoPorMes, $params);  
$faturamentoMes = ['labels' => [], 'data' => []];  
while ($row = $resultFaturamentoPorMes->fetch_assoc()) {  
    $faturamentoMes['labels'][] = $row['mes'];  
    $faturamentoMes['data'][] = floatval($row['total']);  
}  

// 4. Gráfico de faturamento por semana  
$queryFaturamentoPorSemana = "  
    SELECT   
        CONCAT(YEAR(os.data_criacao), '-', WEEK(os.data_criacao)) AS semana,  
        SUM(os.total_os) AS total  
    FROM ordens_de_servico os  
    $whereClause  
    GROUP BY YEAR(os.data_criacao), WEEK(os.data_criacao)  
    ORDER BY YEAR(os.data_criacao) ASC, WEEK(os.data_criacao) ASC  
    LIMIT 12  
";  

$resultFaturamentoPorSemana = executeQuery($conn, $queryFaturamentoPorSemana, $params);  
$faturamentoSemana = ['labels' => [], 'data' => []];  
while ($row = $resultFaturamentoPorSemana->fetch_assoc()) {  
    $faturamentoSemana['labels'][] = $row['semana'];  
    $faturamentoSemana['data'][] = floatval($row['total']);  
}  

// 5. Gráfico de OS por funcionário  
$queryOSPorFuncionario = "  
    SELECT   
        os.criado_por AS funcionario,  
        COUNT(*) AS total  
    FROM ordens_de_servico os  
    $whereClause  
    GROUP BY os.criado_por  
    ORDER BY total DESC  
    LIMIT 10  
";  

$resultOSPorFuncionario = executeQuery($conn, $queryOSPorFuncionario, $params);  
$osFuncionario = ['labels' => [], 'data' => []];  
while ($row = $resultOSPorFuncionario->fetch_assoc()) {  
    $osFuncionario['labels'][] = $row['funcionario'] ?: 'Não informado';  
    $osFuncionario['data'][] = intval($row['total']);  
}  

// 6. Gráfico de faturamento por funcionário  
$queryFaturamentoPorFuncionario = "  
    SELECT   
        os.criado_por AS funcionario,  
        SUM(os.total_os) AS total  
    FROM ordens_de_servico os  
    $whereClause  
    GROUP BY os.criado_por  
    ORDER BY total DESC  
    LIMIT 10  
";  

$resultFaturamentoPorFuncionario = executeQuery($conn, $queryFaturamentoPorFuncionario, $params);  
$faturamentoFuncionario = ['labels' => [], 'data' => []];  
while ($row = $resultFaturamentoPorFuncionario->fetch_assoc()) {  
    $faturamentoFuncionario['labels'][] = $row['funcionario'] ?: 'Não informado';  
    $faturamentoFuncionario['data'][] = floatval($row['total']);  
}  

// Construir resposta JSON  
$response = [  
    'osMes' => $osMes,  
    'osSemana' => $osSemana,  
    'faturamentoMes' => $faturamentoMes,  
    'faturamentoSemana' => $faturamentoSemana,  
    'osFuncionario' => $osFuncionario,  
    'faturamentoFuncionario' => $faturamentoFuncionario  
];  

// Salvar cache  
if (!is_dir(__DIR__ . '/cache')) {  
    mkdir(__DIR__ . '/cache', 0755, true);  
}  
file_put_contents($cacheFile, json_encode($response));  

// Limpar qualquer saída anterior  
ob_end_clean();  

// Retornar resposta JSON  
header('Content-Type: application/json');  
header('X-Cache: MISS');  
echo json_encode($response);