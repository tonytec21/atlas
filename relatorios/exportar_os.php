<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Obter parâmetros de filtro  
$dia = isset($_GET['dia']) ? $_GET['dia'] : '';  
$mes = isset($_GET['mes']) ? $_GET['mes'] : '';  
$ano = isset($_GET['ano']) ? $_GET['ano'] : '';  
$data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';  
$data_final = isset($_GET['data_final']) ? $_GET['data_final'] : '';  
$situacao = isset($_GET['situacao']) ? $_GET['situacao'] : '';  
$status = isset($_GET['status']) ? $_GET['status'] : '';  
$funcionario = isset($_GET['funcionario']) ? $_GET['funcionario'] : '';  
$export_type = isset($_GET['export']) ? $_GET['export'] : 'excel';  

// Verificar a estrutura da tabela  
$table_structure = $conn->query("SHOW COLUMNS FROM ordens_de_servico");  
$columns = [];  
while ($col = $table_structure->fetch_assoc()) {  
    $columns[$col['Field']] = true;  
}  

// Construir a cláusula WHERE com base nos filtros  
$whereConditions = [];  

// Filtros  
if (!empty($dia)) {  
    $dia = $conn->real_escape_string($dia);  
    $whereConditions[] = "DATE(os.data_criacao) = '$dia'";  
}  

if (!empty($mes)) {  
    $mes = $conn->real_escape_string($mes);  
    $whereConditions[] = "DATE_FORMAT(os.data_criacao, '%Y-%m') = '$mes'";  
}  

if (!empty($ano)) {  
    $ano = $conn->real_escape_string($ano);  
    $whereConditions[] = "YEAR(os.data_criacao) = '$ano'";  
}  

// Novos filtros de data inicial e final  
if (!empty($data_inicial)) {  
    $dataInicial = $conn->real_escape_string($data_inicial);  
    $whereConditions[] = "DATE(os.data_criacao) >= '$dataInicial'";  
}  

if (!empty($data_final)) {  
    $dataFinal = $conn->real_escape_string($data_final);  
    $whereConditions[] = "DATE(os.data_criacao) <= '$dataFinal'";  
}  

if (!empty($funcionario)) {  
    $funcionario = $conn->real_escape_string($funcionario);  
    $whereConditions[] = "os.criado_por = '$funcionario'";  
}  

// Condições de situação  
if (!empty($situacao)) {  
    $situacao = $conn->real_escape_string($situacao);  
    if ($situacao === 'Cancelada') {  
        $whereConditions[] = "os.status = 'Cancelado'";  
    } elseif ($situacao === 'Paga') {  
        $whereConditions[] = "EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)";  
    } elseif ($situacao === 'Pendente de Pagamento') {  
        $whereConditions[] = "NOT EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id)   
                              AND os.status != 'Cancelado'";  
    }  
}  

// Condições de status  
if (!empty($status)) {  
    $status = $conn->real_escape_string($status);  
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

// Construir lista de colunas básicas obrigatórias  
$select_columns = "  
    os.id,  
    os.cliente,  
    os.cpf_cliente,  
    os.total_os,  
    os.data_criacao,  
    os.criado_por";  

// Adicionar colunas extras apenas se existirem na tabela  
if (isset($columns['deposito_previo'])) {  
    $select_columns .= ",\n    os.deposito_previo";  
} else {  
    $select_columns .= ",\n    '' AS deposito_previo";  
}  

if (isset($columns['atos_praticados'])) {  
    $select_columns .= ",\n    os.atos_praticados";  
} else {  
    $select_columns .= ",\n    '' AS atos_praticados";  
}  

// Consulta para exportação  
$query = "  
SELECT   
$select_columns,  
    CASE  
        WHEN os.status = 'Cancelado' THEN 'Cancelada'  
        WHEN EXISTS (SELECT 1 FROM pagamento_os po WHERE po.ordem_de_servico_id = os.id) THEN 'Paga'  
        ELSE 'Pendente de Pagamento'  
    END AS situacao,  
    CASE  
        WHEN os.status = 'Cancelado' THEN 'Cancelada'  
        WHEN EXISTS (  
            SELECT 1 FROM (  
                SELECT   
                    ordem_servico_id,  
                    SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) = COUNT(*) AS is_liquidada  
                FROM ordens_de_servico_itens  
                GROUP BY ordem_servico_id  
            ) liq   
            WHERE liq.ordem_servico_id = os.id AND liq.is_liquidada = 1  
        ) THEN 'Liquidada'  
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
        ) THEN 'Parcialmente Liquidada'  
        WHEN EXISTS (  
            SELECT 1 FROM (  
                SELECT   
                    ordem_servico_id,  
                    SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) = COUNT(*) AS all_pendente  
                FROM ordens_de_servico_itens  
                GROUP BY ordem_servico_id  
            ) liq   
            WHERE liq.ordem_servico_id = os.id AND liq.all_pendente = 1  
        ) THEN 'Pendente de Liquidação'  
        ELSE 'Pendente de Liquidação'  
    END AS status_calculado  
FROM ordens_de_servico os  
$whereClause  
ORDER BY os.id DESC  
";  

$result = $conn->query($query);  

if (!$result) {  
    die("Erro na consulta: " . $conn->error . "<br>SQL: " . $query);  
}  

$data = [];  
while ($row = $result->fetch_assoc()) {  
    $data[] = $row;  
}  

// Exportar dados de acordo com o tipo solicitado  
header('Content-Type: text/html; charset=utf-8');  

if ($export_type == 'excel') {  
    header('Content-Type: application/vnd.ms-excel');  
    header('Content-Disposition: attachment; filename="relatorio_os.xls"');  
    
    echo '<table border="1">';  
    // Cabeçalhos  
    echo '<tr>  
            <th>Nº OS</th>  
            <th>Apresentante</th>  
            <th>CPF/CNPJ</th>  
            <th>Valor Total</th>  
            <th>Data</th>  
            <th>Funcionário</th>  
            <th>Situação</th>  
            <th>Status</th>  
            <th>Depósito Prévio</th>  
            <th>Atos Praticados</th>  
          </tr>';  
    
    // Dados  
    foreach ($data as $row) {  
        echo '<tr>';  
        echo '<td>' . htmlspecialchars($row['id']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['cliente']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['cpf_cliente']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['total_os']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['data_criacao']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['criado_por']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['situacao']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['status_calculado']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['deposito_previo']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['atos_praticados']) . '</td>';  
        echo '</tr>';  
    }  
    echo '</table>';  
}   
else if ($export_type == 'print') {  
    echo '<!DOCTYPE html>  
    <html>  
    <head>  
        <title>Relatório de Ordens de Serviço</title>  
        <meta charset="UTF-8">  
        <style>  
            body { font-family: Arial, sans-serif; }  
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }  
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }  
            th { background-color: #f2f2f2; }  
        </style>  
        <script>  
            window.onload = function() { window.print(); }  
        </script>  
    </head>  
    <body>  
        <h1>Relatório de Ordens de Serviço</h1>  
        <table>  
            <tr>  
                <th>Nº OS</th>  
                <th>Apresentante</th>  
                <th>CPF/CNPJ</th>  
                <th>Valor Total</th>  
                <th>Data</th>  
                <th>Funcionário</th>  
                <th>Situação</th>  
                <th>Status</th>  
                <th>Depósito Prévio</th>  
                <th>Atos Praticados</th>  
            </tr>';  
    
    foreach ($data as $row) {  
        echo '<tr>';  
        echo '<td>' . htmlspecialchars($row['id']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['cliente']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['cpf_cliente']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['total_os']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['data_criacao']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['criado_por']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['situacao']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['status_calculado']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['deposito_previo']) . '</td>';  
        echo '<td>' . htmlspecialchars($row['atos_praticados']) . '</td>';  
        echo '</tr>';  
    }  
    
    echo '</table>  
    </body>  
    </html>';  
}  
// Você pode adicionar a exportação para PDF aqui se necessário  
?>