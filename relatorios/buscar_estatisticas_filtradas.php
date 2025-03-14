<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Função para obter estatísticas filtradas  
function obterEstatisticasFiltradas($conn, $filtros) {  
    // Preparar condições de filtro  
    $condicoes = [];  
    $params = [];  
    
    if (!empty($filtros['data_inicial']) && !empty($filtros['data_final'])) {  
        $condicoes[] = "data_criacao BETWEEN ? AND ?";  
        $params[] = $filtros['data_inicial'] . ' 00:00:00';  
        $params[] = $filtros['data_final'] . ' 23:59:59';  
    }  
    
    if (!empty($filtros['status'])) {  
        $condicoes[] = "status = ?";  
        $params[] = $filtros['status'];  
    }  
    
    if (!empty($filtros['situacao'])) {  
        if ($filtros['situacao'] == 'Paga') {  
            $condicoes[] = "EXISTS (SELECT 1 FROM pagamento_os WHERE pagamento_os.ordem_de_servico_id = ordens_de_servico.id)";  
        } else if ($filtros['situacao'] == 'Pendente de Pagamento') {  
            $condicoes[] = "NOT EXISTS (SELECT 1 FROM pagamento_os WHERE pagamento_os.ordem_de_servico_id = ordens_de_servico.id) AND status != 'Cancelado'";  
        } else if ($filtros['situacao'] == 'Cancelada') {  
            $condicoes[] = "status = 'Cancelado'";  
        }  
    }  
    
    if (!empty($filtros['funcionario'])) {  
        $condicoes[] = "criado_por = ?";  
        $params[] = $filtros['funcionario'];  
    }  
    
    // Construir a cláusula WHERE  
    $where = '';  
    if (!empty($condicoes)) {  
        $where = "WHERE " . implode(' AND ', $condicoes);  
    }  

    // Estatísticas filtradas  
    $stats = [];  
    
    // Total de OS  
    $query = "SELECT COUNT(*) as total FROM ordens_de_servico $where";  
    $stmt = $conn->prepare($query);  
    if (!empty($params)) {  
        $types = str_repeat('s', count($params));  
        $stmt->bind_param($types, ...$params);  
    }  
    $stmt->execute();  
    $result = $stmt->get_result();  
    $row = $result->fetch_assoc();  
    $stats['total_os'] = $row['total'];  
    
    // OS Canceladas  
    $canceladas_where = $where ? $where . " AND status = 'Cancelado'" : "WHERE status = 'Cancelado'";  
    $query = "SELECT COUNT(*) as total FROM ordens_de_servico $canceladas_where";  
    $stmt = $conn->prepare($query);  
    if (!empty($params)) {  
        $types = str_repeat('s', count($params));  
        $stmt->bind_param($types, ...$params);  
    }  
    $stmt->execute();  
    $result = $stmt->get_result();  
    $row = $result->fetch_assoc();  
    $stats['canceladas'] = $row['total'];  
    
    // OS Pagas  
    $pagas_where = $where ? $where : "";  
    $query = "SELECT COUNT(DISTINCT ordem_de_servico_id) as total FROM pagamento_os   
              JOIN ordens_de_servico ON pagamento_os.ordem_de_servico_id = ordens_de_servico.id   
              $pagas_where";  
    $stmt = $conn->prepare($query);  
    if (!empty($params)) {  
        $types = str_repeat('s', count($params));  
        $stmt->bind_param($types, ...$params);  
    }  
    $stmt->execute();  
    $result = $stmt->get_result();  
    $row = $result->fetch_assoc();  
    $stats['os_pagas'] = $row['total'];  
    
    // OS Pendentes de Pagamento  
    $pendentes_where = $where ? $where . " AND status != 'Cancelado'" : "WHERE status != 'Cancelado'";  
    $query = "SELECT COUNT(DISTINCT os.id) as total   
              FROM ordens_de_servico os  
              LEFT JOIN pagamento_os po ON os.id = po.ordem_de_servico_id  
              $pendentes_where  
              AND po.ordem_de_servico_id IS NULL";  
    $stmt = $conn->prepare($query);  
    if (!empty($params)) {  
        $types = str_repeat('s', count($params));  
        $stmt->bind_param($types, ...$params);  
    }  
    $stmt->execute();  
    $result = $stmt->get_result();  
    $row = $result->fetch_assoc();  
    $stats['os_pendentes_pagamento'] = $row['total'];  
    
    // Consulta para status de liquidação (necessita de lógica específica)  
    // Esta parte é mais complexa e pode precisar de ajustes específicos  
    $liquidadas = 0;  
    $parcialmente_liquidadas = 0;  
    $pendentes = 0;  
    
    // Esta é uma simplificação - a lógica completa dependeria da estrutura exata do banco  
    $query = "SELECT   
                os.id as ordem_servico_id,  
                SUM(CASE WHEN osi.status = 'liquidado' THEN 1 ELSE 0 END) AS total_liquidado,  
                SUM(CASE WHEN osi.status = 'parcialmente liquidado' THEN 1 ELSE 0 END) AS total_parcial,  
                SUM(CASE WHEN osi.status IS NULL OR osi.status = '' OR osi.status = 'pendente' THEN 1 ELSE 0 END) AS total_pendente,  
                COUNT(osi.id) AS total_itens  
              FROM ordens_de_servico os  
              LEFT JOIN ordens_de_servico_itens osi ON os.id = osi.ordem_servico_id  
              $where  
              GROUP BY os.id";  
              
    $stmt = $conn->prepare($query);  
    if (!empty($params)) {  
        $types = str_repeat('s', count($params));  
        $stmt->bind_param($types, ...$params);  
    }  
    $stmt->execute();  
    $result = $stmt->get_result();  
    
    while ($row = $result->fetch_assoc()) {  
        if ($row['total_liquidado'] == $row['total_itens']) {  
            $liquidadas++;  
        } elseif ($row['total_parcial'] > 0 || ($row['total_liquidado'] > 0 && $row['total_pendente'] > 0)) {  
            $parcialmente_liquidadas++;  
        } elseif ($row['total_pendente'] == $row['total_itens']) {  
            $pendentes++;  
        }  
    }  
    
    $stats['liquidadas'] = $liquidadas;  
    $stats['parcialmente_liquidadas'] = $parcialmente_liquidadas;  
    $stats['pendentes'] = $pendentes;  
    
    return $stats;  
}  

// Processar requisição AJAX  
if ($_SERVER['REQUEST_METHOD'] === 'GET') {  
    $filtros = [  
        'data_inicial' => isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '',  
        'data_final' => isset($_GET['data_final']) ? $_GET['data_final'] : '',  
        'status' => isset($_GET['status']) ? $_GET['status'] : '',  
        'situacao' => isset($_GET['situacao']) ? $_GET['situacao'] : '',  
        'funcionario' => isset($_GET['funcionario']) ? $_GET['funcionario'] : ''  
    ];  
    
    $stats = obterEstatisticasFiltradas($conn, $filtros);  
    
    header('Content-Type: application/json');  
    echo json_encode($stats);  
    exit;  
}  
?>