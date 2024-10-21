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

// Consulta que calcula o faturamento total (pagamentos - devoluções)

// 1. Faturamento por Mês (considerando devoluções)
$faturamentoMes = buscarDados("
    SELECT DATE_FORMAT(po.data_pagamento, '%Y-%m') AS label, 
           SUM(po.total_pagamento) - IFNULL(SUM(d.total_devolucao), 0) AS value
    FROM pagamento_os po
    LEFT JOIN devolucao_os d ON po.ordem_de_servico_id = d.ordem_de_servico_id 
          AND DATE_FORMAT(po.data_pagamento, '%Y-%m') = DATE_FORMAT(d.data_devolucao, '%Y-%m')
    WHERE po.data_pagamento IS NOT NULL
    GROUP BY label
    ORDER BY label ASC", $conn);

// 2. Faturamento por Semana (considerando devoluções)
$faturamentoSemana = buscarDados("
    SELECT CONCAT(YEAR(po.data_pagamento), '-', LPAD(WEEK(po.data_pagamento, 1), 2, '0')) AS label, 
           SUM(po.total_pagamento) - IFNULL(SUM(d.total_devolucao), 0) AS value
    FROM pagamento_os po
    LEFT JOIN devolucao_os d ON po.ordem_de_servico_id = d.ordem_de_servico_id 
          AND WEEK(po.data_pagamento, 1) = WEEK(d.data_devolucao, 1)
    WHERE po.data_pagamento IS NOT NULL
    GROUP BY label
    ORDER BY label ASC", $conn);

// 3. Faturamento por Funcionário (considerando devoluções)
$faturamentoFuncionario = buscarDados("
    SELECT os.criado_por AS label, 
           SUM(po.total_pagamento) - IFNULL(SUM(d.total_devolucao), 0) AS value
    FROM pagamento_os po
    INNER JOIN ordens_de_servico os ON po.ordem_de_servico_id = os.id
    LEFT JOIN devolucao_os d ON po.ordem_de_servico_id = d.ordem_de_servico_id
    WHERE po.data_pagamento IS NOT NULL
    GROUP BY label
    ORDER BY label ASC", $conn);

// 4. OS por Mês (Sem alteração)
$osMes = buscarDados("
    SELECT DATE_FORMAT(data_criacao, '%Y-%m') AS label, COUNT(*) AS value 
    FROM ordens_de_servico 
    WHERE data_criacao IS NOT NULL
    GROUP BY label 
    ORDER BY label ASC", $conn);

// 5. OS por Semana (Sem alteração)
$osSemana = buscarDados("
    SELECT CONCAT(YEAR(data_criacao), '-', LPAD(WEEK(data_criacao, 1), 2, '0')) AS label, 
           COUNT(*) AS value 
    FROM ordens_de_servico 
    WHERE data_criacao IS NOT NULL
    GROUP BY label 
    ORDER BY label ASC", $conn);

// 6. OS por Funcionário (Sem alteração)
$osFuncionario = buscarDados("
    SELECT criado_por AS label, COUNT(*) AS value 
    FROM ordens_de_servico 
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
