<?php
include(__DIR__ . '/db_connection.php');

// Captura dos filtros via GET
$dia = $_GET['dia'] ?? '';
$mes = $_GET['mes'] ?? '';
$ano = $_GET['ano'] ?? '';
$statusFiltro = $_GET['status'] ?? '';
$situacaoFiltro = $_GET['situacao'] ?? '';
$funcionario = $_GET['funcionario'] ?? '';

// Construindo a query para buscar as OS
$query = "
    SELECT os.id, os.cliente, os.cpf_cliente, os.total_os, os.data_criacao, 
           os.criado_por
    FROM ordens_de_servico os
    WHERE 1=1
";

if ($dia) {
    $query .= " AND DATE(os.data_criacao) = '$dia'";
}
if ($mes) {
    $query .= " AND MONTH(os.data_criacao) = MONTH('$mes-01')";
}
if ($ano) {
    $query .= " AND YEAR(os.data_criacao) = $ano";
}
if ($funcionario) {
    $query .= " AND os.criado_por LIKE '%$funcionario%'";
}


$result = $conn->query($query);
$html = '';

while ($os = $result->fetch_assoc()) {
    $os_id = $os['id'];
    $cliente = $os['cliente'];
    $cpf_cnpj = $os['cpf_cliente'] ?: '---';
    $total_os = 'R$ ' . number_format($os['total_os'], 2, ',', '.');
    $data_os = date('d/m/Y', strtotime($os['data_criacao']));
    $funcionario = $os['criado_por'] ?: 'Desconhecido';

    // Calcular o status dinamicamente
    $status = obterStatusOS($conn, $os_id);

    // Verificar se o status corresponde ao filtro
    if ($statusFiltro && $status !== $statusFiltro) {
        continue; // Ignora se não corresponder
    }

    // Definir a situação da OS
    $situacao = ($status === 'Cancelada') 
        ? 'Cancelada' 
        : (temPagamento($conn, $os_id) ? 'Paga' : 'Pendente de Pagamento');

    // Verificar se a situação corresponde ao filtro
    if ($situacaoFiltro && $situacao !== $situacaoFiltro) {
        continue; // Ignora se não corresponder
    }

    $deposito_previo = calcularDeposito($conn, $os_id);
    $atos_praticados = calcularAtos($conn, $os_id);

    // Montar a linha da tabela
    $html .= "
        <tr style='zoom: 90%'>
            <td>{$os_id}</td>
            <td>{$cliente}</td>
            <td>{$cpf_cnpj}</td>
            <td>{$total_os}</td>
            <td>{$data_os}</td>
            <td>{$funcionario}</td>
            <td>{$situacao}</td>
            <td>{$status}</td>
            <td>{$deposito_previo}</td>
            <td>{$atos_praticados}</td>
        </tr>
    ";
}

echo $html;

// Função para obter o status da OS dinamicamente
function obterStatusOS($conn, $os_id) {
    $query = $conn->prepare("
        SELECT 
            SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) AS total_liquidado,
            SUM(CASE WHEN status = 'parcialmente liquidado' THEN 1 ELSE 0 END) AS total_parcial,
            SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) AS total_pendente,
            SUM(CASE WHEN status = 'Cancelado' THEN 1 ELSE 0 END) AS total_cancelado,
            COUNT(*) AS total_itens
        FROM ordens_de_servico_itens
        WHERE ordem_servico_id = ?
    ");
    $query->bind_param("i", $os_id);
    $query->execute();
    $query->bind_result($total_liquidado, $total_parcial, $total_pendente, $total_cancelado, $total_itens);
    $query->fetch();
    $query->close();

    if ($total_cancelado == $total_itens) return 'Cancelada';
    if ($total_liquidado > 0 && $total_pendente == 0 && $total_parcial == 0) return 'Liquidada';
    if ($total_parcial > 0 || ($total_liquidado > 0 && $total_pendente > 0)) return 'Parcialmente Liquidada';
    return 'Pendente de Liquidação';
}

// Outras funções auxiliares
function temPagamento($conn, $os_id) {
    $query = $conn->prepare("SELECT COUNT(*) FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $query->bind_param("i", $os_id);
    $query->execute();
    $query->bind_result($count);
    $query->fetch();
    $query->close();
    return $count > 0;
}

function calcularDeposito($conn, $os_id) {
    $query = $conn->prepare("
        SELECT SUM(total_pagamento) AS total_deposito
        FROM pagamento_os
        WHERE ordem_de_servico_id = ?
    ");
    $query->bind_param("i", $os_id);
    $query->execute();
    $query->bind_result($total_deposito);
    $query->fetch();
    $query->close();
    return $total_deposito ? 'R$ ' . number_format($total_deposito, 2, ',', '.') : '---';
}

function calcularAtos($conn, $os_id) {
    $query = $conn->prepare("
        SELECT 
            (SELECT IFNULL(SUM(total), 0) FROM atos_liquidados WHERE ordem_servico_id = ?) +
            (SELECT IFNULL(SUM(total), 0) FROM atos_manuais_liquidados WHERE ordem_servico_id = ?)
        AS total_atos
    ");
    $query->bind_param("ii", $os_id, $os_id);
    $query->execute();
    $query->bind_result($total_atos);
    $query->fetch();
    $query->close();
    return 'R$ ' . number_format($total_atos, 2, ',', '.');
}
?>
