<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

// Consulta das Ordens de Serviço com pagamento
$os_query = $conn->query("
    SELECT os.id, os.cliente, os.cpf_cliente, os.total_os, os.data_criacao 
    FROM ordens_de_servico os
    INNER JOIN pagamento_os po ON os.id = po.ordem_de_servico_id
    GROUP BY os.id
");

// Início da Tabela com Cabeçalho e Corpo Integrado
$html = '
    <table id="tabelaOS" class="table table-striped table-bordered" style="width:100%">
        <thead>
            <tr>
                <th>Nº OS</th>
                <th>Apresentante</th>
                <th>CPF/CNPJ</th>
                <th>Total OS (R$)</th>
                <th>Data OS</th>
                <th>Depósito Prévio</th>
                <th>Atos Praticados</th>
            </tr>
        </thead>
        <tbody>
';

while ($os = $os_query->fetch_assoc()) {
    $os_id = $os['id'];
    $cliente = $os['cliente'];
    $cpf_cnpj = $os['cpf_cliente'] ?: '---';
    $total_os = 'R$ ' . number_format($os['total_os'], 2, ',', '.');
    $data_os = date('d/m/Y', strtotime($os['data_criacao']));

    // Consulta dos Pagamentos
    $pagamento_query = $conn->prepare("SELECT total_pagamento, forma_de_pagamento, data_pagamento FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $pagamento_query->bind_param("i", $os_id);
    $pagamento_query->execute();
    $pagamento_result = $pagamento_query->get_result();
    $pagamento_info = '';
    while ($pagamento = $pagamento_result->fetch_assoc()) {
        $valor = 'R$ ' . number_format($pagamento['total_pagamento'], 2, ',', '.');
        $forma = $pagamento['forma_de_pagamento'];
        $data_pagamento = date('d/m/Y', strtotime($pagamento['data_pagamento']));
        $pagamento_info .= "$valor - $forma - $data_pagamento<br/>";
    }

    // Consulta dos Atos Praticados
    $atos_query = $conn->prepare("SELECT ato, quantidade_liquidada, total, data FROM atos_liquidados WHERE ordem_servico_id = ?");
    $atos_query->bind_param("i", $os_id);
    $atos_query->execute();
    $atos_result = $atos_query->get_result();
    $atos_info = '<b>Atos Liquidados:</b><br/>';
    $total_geral_atos = 0;

    while ($ato = $atos_result->fetch_assoc()) {
        $descricao_ato = $ato['ato'];
        $quantidade = $ato['quantidade_liquidada'];
        $total = $ato['total'];
        $data_ato = date('d/m/Y', strtotime($ato['data']));

        // Acumula o total geral dos atos
        $total_geral_atos += $total;

        // Adiciona a informação de cada ato
        $atos_info .= "$descricao_ato - Qtd: $quantidade - Total: R$ " . number_format($total, 2, ',', '.') . " - Data: $data_ato<br/>";
    }

    // Adiciona o somatório total dos atos
    $atos_info .= '<b>Total Geral dos Atos:</b> R$ ' . number_format($total_geral_atos, 2, ',', '.') . '<br/>';

    // Consulta das Devoluções
    $devolucao_query = $conn->prepare("SELECT total_devolucao, forma_devolucao, data_devolucao FROM devolucao_os WHERE ordem_de_servico_id = ?");
    $devolucao_query->bind_param("i", $os_id);
    $devolucao_query->execute();
    $devolucao_result = $devolucao_query->get_result();

    if ($devolucao_result->num_rows > 0) {
        $atos_info .= '<b>Devoluções:</b><br/>';
        while ($devolucao = $devolucao_result->fetch_assoc()) {
            $valor_devolucao = 'R$ ' . number_format($devolucao['total_devolucao'], 2, ',', '.');
            $forma_devolucao = $devolucao['forma_devolucao'];
            $data_devolucao = date('d/m/Y', strtotime($devolucao['data_devolucao']));
            $atos_info .= "$valor_devolucao - $forma_devolucao - $data_devolucao<br/>";
        }
    }

    // Adicionar Linha da OS na Tabela
    $html .= "
        <tr>
            <td>{$os_id}</td>
            <td>{$cliente}</td>
            <td>{$cpf_cnpj}</td>
            <td>{$total_os}</td>
            <td>{$data_os}</td>
            <td>{$pagamento_info}</td>
            <td>{$atos_info}</td>
        </tr>
    ";
}

$html .= '</tbody></table>';
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório e Ordens de Serviço</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
</head>

<body>
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div class="container mt-5">
        <h2 class="text-center">Relatório e Livro de Depósito Prévio</h2>
        <hr>
        <div class="table-responsive">
            <?= $html ?>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>

    <script>
        $(document).ready(function () {
            // Inicializa DataTable com paginação e busca
            $('#tabelaOS').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "pageLength": 10,
                "language": {
                    "lengthMenu": "Mostrar _MENU_ registros por página",
                    "zeroRecords": "Nenhum registro encontrado",
                    "info": "Página _PAGE_ de _PAGES_",
                    "infoEmpty": "Nenhum registro disponível",
                    "infoFiltered": "(filtrado de _MAX_ registros no total)",
                    "search": "Buscar:",
                    "paginate": {
                        "first": "Primeiro",
                        "last": "Último",
                        "next": "Próximo",
                        "previous": "Anterior"
                    }
                }
            });
        });
    </script>
    <br><br><br>
    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>
