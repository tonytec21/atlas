<?php
include(__DIR__ . '/db_connection.php');

// Função para buscar dados e tratar erros
function buscarDados($query, $conn) {
    $result = $conn->query($query);

    if (!$result) {
        // Retorna um erro no caso de falha na consulta
        return ['labels' => [], 'data' => [], 'error' => $conn->error];
    }

    $labels = [];
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['label'];
        $data[] = $row['value'];
    }

    return ['labels' => $labels, 'data' => $data];
}

// Ajuste nos nomes das colunas, se necessário
$osMes = buscarDados("
    SELECT DATE_FORMAT(data_criacao, '%Y-%m') AS label, COUNT(*) AS value 
    FROM ordens_de_servico 
    WHERE data_criacao IS NOT NULL
    GROUP BY label 
    ORDER BY label ASC", $conn);

$osSemana = buscarDados("
    SELECT CONCAT(YEAR(data_criacao), '-', LPAD(WEEK(data_criacao, 1), 2, '0')) AS label, 
           COUNT(*) AS value 
    FROM ordens_de_servico 
    WHERE data_criacao IS NOT NULL
    GROUP BY label 
    ORDER BY label ASC", $conn);

$faturamentoMes = buscarDados("
    SELECT DATE_FORMAT(data_pagamento, '%Y-%m') AS label, SUM(total_pagamento) AS value 
    FROM pagamento_os 
    WHERE data_pagamento IS NOT NULL
    GROUP BY label 
    ORDER BY label ASC", $conn);

$faturamentoSemana = buscarDados("
    SELECT CONCAT(YEAR(data_pagamento), '-', LPAD(WEEK(data_pagamento, 1), 2, '0')) AS label, 
           SUM(total_pagamento) AS value 
    FROM pagamento_os 
    WHERE data_pagamento IS NOT NULL
    GROUP BY label 
    ORDER BY label ASC", $conn);

$osFuncionario = buscarDados("
    SELECT criado_por AS label, COUNT(*) AS value 
    FROM ordens_de_servico 
    GROUP BY label 
    ORDER BY label ASC", $conn);

$faturamentoFuncionario = buscarDados("
    SELECT criado_por AS label, SUM(total_pagamento) AS value 
    FROM pagamento_os 
    INNER JOIN ordens_de_servico ON pagamento_os.ordem_de_servico_id = ordens_de_servico.id 
    GROUP BY label 
    ORDER BY label ASC", $conn);

// Retorna os dados como JSON com cabeçalho correto
header('Content-Type: application/json');
echo json_encode([
    'osMes' => $osMes,
    'osSemana' => $osSemana,
    'faturamentoMes' => $faturamentoMes,
    'faturamentoSemana' => $faturamentoSemana,
    'osFuncionario' => $osFuncionario,
    'faturamentoFuncionario' => $faturamentoFuncionario
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
