<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

// Suprimir avisos de erros
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

// Consulta das Ordens de Serviço
$sql = "SELECT id, cliente, cpf_cliente, total_os, data_criacao FROM ordens_de_servico";
$result = $conn->query($sql);

$ordens = [];
while ($os = $result->fetch_assoc()) {
    $os_id = $os['id'];

    // Consulta dos Pagamentos
    $pagamento_query = $conn->prepare("SELECT total_pagamento, forma_de_pagamento, data_pagamento FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $pagamento_query->bind_param("i", $os_id);
    $pagamento_query->execute();
    $pagamento_result = $pagamento_query->get_result();

    $pagamentos = [];
    while ($pagamento = $pagamento_result->fetch_assoc()) {
        $pagamentos[] = $pagamento;
    }

    // Consulta dos Atos Praticados
    $atos_query = $conn->prepare("SELECT ato, quantidade_liquidada, total, data FROM atos_liquidados WHERE ordem_servico_id = ?");
    $atos_query->bind_param("i", $os_id);
    $atos_query->execute();
    $atos_result = $atos_query->get_result();

    $atos = [];
    while ($ato = $atos_result->fetch_assoc()) {
        $atos[] = $ato;
    }

    // Consulta das Devoluções
    $devolucao_query = $conn->prepare("SELECT total_devolucao, forma_devolucao, data_devolucao FROM devolucao_os WHERE ordem_de_servico_id = ?");
    $devolucao_query->bind_param("i", $os_id);
    $devolucao_query->execute();
    $devolucao_result = $devolucao_query->get_result();

    $devolucoes = [];
    while ($devolucao = $devolucao_result->fetch_assoc()) {
        $devolucoes[] = $devolucao;
    }

    // Adicionar a ordem de serviço no array
    $ordens[] = [
        'id' => $os['id'],
        'cliente' => $os['cliente'],
        'cpf_cnpj' => $os['cpf_cliente'] ?: '---',
        'total_os' => $os['total_os'],
        'data_os' => $os['data_criacao'],
        'pagamentos' => $pagamentos,
        'atos' => $atos,
        'devolucoes' => $devolucoes
    ];
}

// Retornar os dados como JSON
echo json_encode($ordens);
?>
