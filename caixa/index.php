<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesquisar Fluxo de Caixa</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <style>
        .btn-adicionar {
            height: 38px;
            line-height: 24px;
            margin-left: 10px;
        }

        .modal-content {
            border-radius: 10px;
        }

        .modal-dialog {
            max-width: 80%;
            margin: 1.75rem auto;
        }

        .modal-header {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .modal-footer {
            border-top: none;
        }

        .modal-header.error {
            background-color: #dc3545;
            color: white;
        }

        .modal-header.success {
            background-color: #28a745;
            color: white;
        }

        .custom-file-input ~ .custom-file-label::after {
            content: "Escolher";
        }

        .custom-file-label {
            border-radius: 0.25rem;
            padding: 0.5rem 1rem;
            background-color: #fff;
            color: #777;
            cursor: pointer;
        }

        .custom-file-input:focus ~ .custom-file-label {
            outline: -webkit-focus-ring-color auto 1px;
            outline-offset: -2px;
        }

        .toast {
            min-width: 250px;
            margin-top: 0px;
        }

        .toast .toast-header {
            color: #fff;
        }

        .toast .bg-success {
            background-color: #28a745 !important;
        }

        .toast .bg-danger {
            background-color: #dc3545 !important;
        }

        .btn-delete {
            margin-bottom: 5px!important;
        }

        .status-label {
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            display: inline-block;
        }

        .status-pendente {
            background-color: #dc3545;
            width: 75px;
            text-align: center;
        }

        .status-parcialmente {
            background-color: #ffc107;
            width: 75px;
            text-align: center;
        }

        .status-liquidado {
            background-color: #28a745;
            width: 75px;
            text-align: center;
        }

        .total-label {
            font-weight: bold;
            text-align: center;
        }

        .table-title {
            /* text-align: center;
            font-weight: bold; */
        }

        .card-title {
            font-size: 1.25rem;
        }

        .card-title2 {
            font-size: 1.1rem;
        }

        .bg-warning {
            background-color: #ff8e07 !important;
        }

        .modal-deposito-caixa {
            max-width: 60%;
            margin: auto;
        }

        .modal-deposito-caixa-unificado {
            max-width: 60%;
            margin: auto;
        }
        
        .modal-abrir-caixa {
            max-width: 25%;
            margin: auto;
        }

        .modal-saidas {
            max-width: 60%;
            margin: auto;
        }

        .btn-success {
            width: 40px;
            height: 40px;
            margin-bottom: 5px;  
        }
        
        .btn-success:hover {
            color: #212529;
        } 
        
        /* Estilo para o modo dark */
        body.dark-mode .card.bg-dark {
            background-color: #f8f9fa !important;
            color: #777 !important;
        }

        body.dark-mode .card.bg-dark .card-header,
        body.dark-mode .card.bg-dark .card-body,
        body.dark-mode .card.bg-dark .card-title {
            color: #777 !important;
        }

    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');

    $conn = getDatabaseConnection();
    $stmt = $conn->prepare('SELECT nivel_de_acesso, status, usuario FROM funcionarios WHERE usuario = :usuario');
    $stmt->bindParam(':usuario', $_SESSION['username']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user['status'] !== 'ativo') {
        echo "<script>alert('O usuário não tem acesso à página.'); window.location.href='../index.php';</script>";
        exit;
    }
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisar Controle de Caixa</h3>
            <hr>
            <form id="pesquisarForm" method="GET">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="funcionario">Funcionário:</label>
                        <select class="form-control" id="funcionario" name="funcionario" <?php echo $user['nivel_de_acesso'] === 'usuario' ? 'disabled' : ''; ?>>
                            <?php if ($user['nivel_de_acesso'] === 'administrador') { ?>
                                <option value="todos">Todos</option>
                                <!-- <option value="caixa_unificado">Caixa Unificado</option> -->
                            <?php } ?>
                            <?php
                            $query = $user['nivel_de_acesso'] === 'administrador' ? "SELECT usuario, nome_completo FROM funcionarios WHERE status = 'ativo'" : "SELECT usuario, nome_completo FROM funcionarios WHERE usuario = :usuario";
                            $stmt = $conn->prepare($query);
                            if ($user['nivel_de_acesso'] !== 'administrador') {
                                $stmt->bindParam(':usuario', $user['usuario']);
                            }
                            $stmt->execute();
                            $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($funcionarios as $funcionario) {
                                echo '<option value="' . $funcionario['usuario'] . '">' . $funcionario['nome_completo'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="data_inicial">Data Inicial:</label>
                        <input type="date" class="form-control" id="data_inicial" name="data_inicial">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="data_final">Data Final:</label>
                        <input type="date" class="form-control" id="data_final" name="data_final">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <button type="submit" style="width: 100%;" class="btn btn-primary"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar</button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" style="width: 100%;" class="btn btn-secondary" onclick="window.location.href='../os/index.php'"><i class="fa fa-search" aria-hidden="true"></i> Pesquisar OS</button>
                    </div>
                </div>
            </form>
            <hr>
            <div id="resultados">
                <h5>Resultados da Pesquisa</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 80%">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Data</th>
                            <th>Atos Liquidados</th>
                            <th>Recebido em Conta</th>
                            <th>Recebido em Espécie</th>
                            <th>Devoluções</th>
                            <th>Saídas e Despesas</th>
                            <th>Depósito do Caixa</th>
                            <th>Total em Caixa</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conditions = [];
                        $params = [];
                        $filtered = false;
                        $isUnificado = false;

                        if (isset($_GET['funcionario']) && $_GET['funcionario'] !== 'todos' && $_GET['funcionario'] !== 'caixa_unificado') {
                            $conditions[] = 'funcionario = :funcionario';
                            $params[':funcionario'] = $_GET['funcionario'];
                            $filtered = true;
                        } elseif ($user['nivel_de_acesso'] === 'usuario') {
                            $conditions[] = 'funcionario = :funcionario';
                            $params[':funcionario'] = $user['usuario'];
                            $filtered = true;
                        } elseif (isset($_GET['funcionario']) && $_GET['funcionario'] === 'caixa_unificado') {
                            $isUnificado = true;
                        }

                        if (!empty($_GET['data_inicial']) && !empty($_GET['data_final'])) {
                            $conditions[] = 'DATE(data) BETWEEN :data_inicial AND :data_final';
                            $params[':data_inicial'] = $_GET['data_inicial'];
                            $params[':data_final'] = $_GET['data_final'];
                            $filtered = true;
                        } elseif (!empty($_GET['data_inicial'])) {
                            $conditions[] = 'DATE(data) >= :data_inicial';
                            $params[':data_inicial'] = $_GET['data_inicial'];
                            $filtered = true;
                        } elseif (!empty($_GET['data_final'])) {
                            $conditions[] = 'DATE(data) <= :data_final';
                            $params[':data_final'] = $_GET['data_final'];
                            $filtered = true;
                        }

                        if ($isUnificado) {
                            $sql = 'SELECT 
                                        GROUP_CONCAT(DISTINCT funcionario SEPARATOR ", ") as funcionarios, 
                                        DATE(data) as data,
                                        SUM(CASE WHEN tipo = "ato" THEN total ELSE 0 END) as total_atos,
                                        SUM(CASE WHEN tipo = "pagamento" THEN total ELSE 0 END) as total_pagamentos,
                                        SUM(CASE WHEN tipo = "devolucao" THEN total ELSE 0 END) as total_devolucoes,
                                        SUM(CASE WHEN tipo = "saida" THEN total ELSE 0 END) as total_saidas
                                    FROM (
                                        SELECT funcionario, data, "ato" as tipo, total 
                                        FROM atos_liquidados
                                        UNION ALL
                                        SELECT funcionario, data_pagamento as data, "pagamento" as tipo, total_pagamento as total
                                        FROM pagamento_os
                                        UNION ALL
                                        SELECT funcionario, data_devolucao as data, "devolucao" as tipo, total_devolucao as total
                                        FROM devolucao_os
                                        UNION ALL
                                        SELECT funcionario, data, "saida" as tipo, valor_saida as total
                                        FROM saidas_despesas WHERE status = "ativo"
                                    ) as fluxos';
                            if ($conditions) {
                                $sql .= ' WHERE ' . implode(' AND ', $conditions);
                            }
                            $sql .= ' GROUP BY DATE(data)';
                        } else {
                            $sql = 'SELECT 
                                        funcionario, 
                                        DATE(data) as data,
                                        SUM(CASE WHEN tipo = "ato" THEN total ELSE 0 END) as total_atos,
                                        SUM(CASE WHEN tipo = "pagamento" THEN total ELSE 0 END) as total_pagamentos,
                                        SUM(CASE WHEN tipo = "devolucao" THEN total ELSE 0 END) as total_devolucoes,
                                        SUM(CASE WHEN tipo = "saida" THEN total ELSE 0 END) as total_saidas
                                    FROM (
                                        SELECT funcionario, data, "ato" as tipo, total 
                                        FROM atos_liquidados
                                        UNION ALL
                                        SELECT funcionario, data_pagamento as data, "pagamento" as tipo, total_pagamento as total
                                        FROM pagamento_os
                                        UNION ALL
                                        SELECT funcionario, data_devolucao as data, "devolucao" as tipo, total_devolucao as total
                                        FROM devolucao_os
                                        UNION ALL
                                        SELECT funcionario, data, "saida" as tipo, valor_saida as total
                                        FROM saidas_despesas WHERE status = "ativo"
                                    ) as fluxos';
                            if ($conditions) {
                                $sql .= ' WHERE ' . implode(' AND ', $conditions);
                            }
                            $sql .= ' GROUP BY funcionario, DATE(data)';
                        }

                        $stmt = $conn->prepare($sql);
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        $stmt->execute();
                        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($resultados as $resultado) {
                            $funcionarios = $isUnificado ? $resultado['funcionarios'] : $resultado['funcionario'];
                            $data = $resultado['data'];
                            $total_atos = $resultado['total_atos'];
                            $total_pagamentos = $resultado['total_pagamentos'];
                            $total_devolucoes = $resultado['total_devolucoes'];
                            $total_saidas = $resultado['total_saidas'];

                            // Calculando valores
                            $totalRecebidoConta = 0;
                            $totalRecebidoEspecie = 0;
                            $totalDevolvidoEspecie = 0;

                            // Recebido em Conta e Espécie
                            $stmt = $conn->prepare('SELECT forma_de_pagamento, total_pagamento FROM pagamento_os WHERE ' . ($isUnificado ? '' : 'funcionario = :funcionario AND ') . 'DATE(data_pagamento) = :data');
                            if (!$isUnificado) {
                                $stmt->bindParam(':funcionario', $funcionarios);
                            }
                            $stmt->bindParam(':data', $data);
                            $stmt->execute();
                            $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($pagamentos as $pagamento) {
                                if (in_array($pagamento['forma_de_pagamento'], ['PIX', 'Transferência Bancária', 'Crédito', 'Débito'])) {
                                    $totalRecebidoConta += $pagamento['total_pagamento'];
                                } else if ($pagamento['forma_de_pagamento'] === 'Espécie') {
                                    $totalRecebidoEspecie += $pagamento['total_pagamento'];
                                }
                            }

                            // Devolvido em Espécie
                            $stmt = $conn->prepare('SELECT forma_devolucao, total_devolucao FROM devolucao_os WHERE ' . ($isUnificado ? '' : 'funcionario = :funcionario AND ') . 'DATE(data_devolucao) = :data');
                            if (!$isUnificado) {
                                $stmt->bindParam(':funcionario', $funcionarios);
                            }
                            $stmt->bindParam(':data', $data);
                            $stmt->execute();
                            $devolucoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($devolucoes as $devolucao) {
                                if ($devolucao['forma_devolucao'] === 'Espécie') {
                                    $totalDevolvidoEspecie += $devolucao['total_devolucao'];
                                }
                            }

                            // Depósitos do Caixa
                            $stmt = $conn->prepare('SELECT valor_do_deposito FROM deposito_caixa WHERE ' . ($isUnificado ? '' : 'funcionario = :funcionario AND ') . 'DATE(data_caixa) = :data AND status = "ativo"');
                            if (!$isUnificado) {
                                $stmt->bindParam(':funcionario', $funcionarios);
                            }
                            $stmt->bindParam(':data', $data);
                            $stmt->execute();
                            $depositos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            $totalDepositoCaixa = array_reduce($depositos, function($carry, $item) {
                                return $carry + $item['valor_do_deposito'];
                            }, 0);

                            // Saldo Transportado individualizado por funcionário
                            $stmt = $conn->prepare('SELECT valor_transportado FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :data AND funcionario = :funcionario');
                            $stmt->bindParam(':data', $data);
                            $stmt->bindParam(':funcionario', $funcionarios);
                            $stmt->execute();
                            $transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            $totalSaldoTransportado = array_reduce($transportes, function($carry, $item) {
                                return $carry + floatval($item['valor_transportado']);
                            }, 0);

                            // Saldo Inicial individualizado por funcionário
                            $stmt = $conn->prepare('SELECT saldo_inicial FROM caixa WHERE DATE(data_caixa) = :data' . ($isUnificado ? '' : ' AND funcionario = :funcionario'));
                            if (!$isUnificado) {
                                $stmt->bindParam(':funcionario', $funcionarios);
                            }
                            $stmt->bindParam(':data', $data);
                            $stmt->execute();
                            $caixa = $stmt->fetch(PDO::FETCH_ASSOC);
                            $saldoInicial = $caixa ? floatval($caixa['saldo_inicial']) : 0.0;

                            // Total em Caixa
                            $totalEmCaixa = $saldoInicial + $totalRecebidoEspecie - $totalDevolvidoEspecie - $total_saidas - $totalDepositoCaixa - $totalSaldoTransportado;

                            ?>
                            <tr>
                                <td><?php echo $funcionarios; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($data)); ?></td>
                                <td><?php echo 'R$ ' . number_format($total_atos, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($totalRecebidoConta, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($totalRecebidoEspecie, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($total_devolucoes, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($total_saidas, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($totalDepositoCaixa, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($totalEmCaixa, 2, ',', '.'); ?></td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm" onclick="verDetalhes('<?php echo $funcionarios; ?>', '<?php echo $data; ?>', '<?php echo $isUnificado ? 'unificado' : 'individual'; ?>')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <?php if (!$isUnificado) { ?>
                                    <button title="Saídas e Despesas" class="btn btn-delete btn-sm" onclick="cadastrarSaida('<?php echo $funcionarios; ?>', '<?php echo $data; ?>')"><i class="fa fa-sign-out" aria-hidden="true"></i></button>
                                    <button title="Depósito do Caixa" class="btn btn-success btn-sm" onclick="cadastrarDeposito('<?php echo $funcionarios; ?>', '<?php echo $data; ?>')"><i class="fa fa-university" aria-hidden="true"></i></button>
                                    <?php } else { ?>
                                    <button title="Ver Depósitos do Caixa" class="btn btn-success btn-sm" onclick="verDepositosCaixa('<?php echo $data; ?>')"><i class="fa fa-list" aria-hidden="true"></i></button>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- Modal de Detalhes -->
    <div class="modal fade" id="detalhesModal" tabindex="-1" role="dialog" aria-labelledby="detalhesModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalhesModalLabel">Detalhes do Fluxo de Caixa</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card text-white bg-primary mb-3" style="background-color: #005d15 !important">
                                <div class="card-header">Saldo Inicial</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardSaldoInicial">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-primary mb-3">
                                <div class="card-header">Atos Liquidados</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardTotalAtos">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-warning mb-3">
                                <div class="card-header">Recebido em Conta</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardTotalRecebidoConta">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-success mb-3">
                                <div class="card-header">Recebido em Espécie</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardTotalRecebidoEspecie">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-secondary mb-3">
                                <div class="card-header">Devoluções</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardTotalDevolucoes">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-danger mb-3">
                                <div class="card-header">Saídas e Despesas</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardSaidasDespesas">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-info mb-3">
                                <div class="card-header">Depósito do Caixa</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardDepositoCaixa">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-dark mb-3">
                                <div class="card-header">Total em Caixa</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardTotalEmCaixa">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="card mb-3">
                        <div class="card-header table-title">ATOS LIQUIDADOS</div>
                        <div class="card-body">
                            <table id="tabelaAtos" class="table table-striped table-bordered" style="zoom: 80%">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Nº OS</th>
                                        <th>Cliente</th>
                                        <th>Ato</th>
                                        <th>Descrição</th>
                                        <th>Quantidade</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody id="detalhesAtos">
                                    <!-- Detalhes dos atos serão carregados aqui -->
                                </tbody>
                            </table>
                            <h6 class="total-label">Total Atos Liquidados: <span id="totalAtos"></span></h6>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header table-title">PAGAMENTOS</div>
                        <div class="card-body">
                            <table id="tabelaPagamentos" class="table table-striped table-bordered" style="zoom: 80%">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>    
                                        <th>Nº OS</th>
                                        <th>Cliente</th>
                                        <th>Forma de Pagamento</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody id="detalhesPagamentos">
                                    <!-- Detalhes dos pagamentos serão carregados aqui -->
                                </tbody>
                            </table>
                            <h6 class="total-label">Total Pagamentos: <span id="totalPagamentos"></span></h6>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header table-title">TOTAL POR TIPO DE PAGAMENTO</div>
                        <div class="card-body">
                            <table id="tabelaTotalPorTipo" class="table table-striped table-bordered" style="zoom: 80%">
                                <thead>
                                    <tr>
                                        <th>Forma de Pagamento</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody id="detalhesTotalPorTipo">
                                    <!-- Totais por tipo de pagamento serão carregados aqui -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header table-title">DEVOLUÇÕES</div>
                        <div class="card-body">
                            <table id="tabelaDevolucoes" class="table table-striped table-bordered" style="zoom: 80%">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Nº OS</th>
                                        <th>Cliente</th>
                                        <th>Forma de Devolução</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody id="detalhesDevolucoes">
                                    <!-- Detalhes das devoluções serão carregados aqui -->
                                </tbody>
                            </table>
                            <h6 class="total-label">Total Devoluções: <span id="totalDevolucoes"></span></h6>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header table-title">SAÍDAS E DESPESAS</div>
                        <div class="card-body">
                            <table id="tabelaSaidas" class="table table-striped table-bordered" style="zoom: 80%">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Título</th>
                                        <th>Valor</th>
                                        <th>Forma de Saída</th>
                                        <th>Data do Caixa</th>
                                        <th>Data Cadastro</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="detalhesSaidas">
                                    <!-- Detalhes das saídas serão carregados aqui -->
                                </tbody>
                            </table>
                            <h6 class="total-label">Total Saídas: <span id="totalSaidas"></span></h6>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header table-title">DEPÓSITOS</div>
                        <div class="card-body">
                            <table id="tabelaDepositos" class="table table-striped table-bordered" style="zoom: 80%">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Data do Caixa</th>
                                        <th>Data Cadastro</th>
                                        <th>Valor</th>
                                        <th>Tipo</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="detalhesDepositos">
                                    <!-- Detalhes dos depósitos serão carregados aqui -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header table-title">SALDO TRANSPORTADO</div>
                        <div class="card-body">
                            <table id="tabelaSaldoTransportado" class="table table-striped table-bordered" style="zoom: 80%">
                                <thead>
                                    <tr>
                                        <th>Data Caixa</th>
                                        <th>Data Transporte</th>
                                        <th>Valor Transportado</th>
                                        <th>Funcionário</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="detalhesSaldoTransportado">
                                    <!-- Detalhes do saldo transportado serão carregados aqui -->
                                </tbody>
                            </table>
                            <h6 class="total-label">Total Saldo Transportado: <span id="totalSaldoTransportado"></span></h6>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Cadastro de Saídas -->
    <div class="modal fade" id="cadastroSaidaModal" tabindex="-1" role="dialog" aria-labelledby="cadastroSaidaModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content modal-saidas">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cadastroSaidaModalLabel">Cadastrar Saída/Despesa</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formCadastroSaida" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="titulo">Título</label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required>
                            </div>
                            <div class="form-group">
                                <label for="valor_saida">Valor da Saída</label>
                                <input type="text" class="form-control" id="valor_saida" name="valor_saida" required>
                            </div>
                            <div class="form-group" style="display: none;">
                                <label for="forma_de_saida">Forma de Saída</label>
                                <select class="form-control" id="forma_de_saida" name="forma_de_saida" required>
                                    <option value="Espécie">Espécie</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="anexo">Anexo</label>
                                <input type="file" class="form-control-file" id="anexo" name="anexo" required>
                            </div>
                            <input type="hidden" id="data_saida" name="data_saida">
                            <input type="hidden" id="data_caixa_saida" name="data_caixa_saida">
                            <input type="hidden" id="funcionario_saida" name="funcionario_saida">
                            <button type="submit" style="width: 100%" class="btn btn-primary">Adicionar</button>
                        </form>
                        <hr>
                        <h5>Saídas/Despesas Cadastradas</h5>
                        <table id="tabelaSaidasCadastradas" class="table table-striped table-bordered" style="zoom: 80%">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <th>Título</th>
                                    <th>Valor</th>
                                    <th>Forma de Saída</th>
                                    <th>Anexo</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="detalhesSaidasCadastradas">
                                <!-- Detalhes das saídas serão carregados aqui -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Cadastro de Depósito -->
    <div class="modal fade" id="cadastroDepositoModal" tabindex="-1" role="dialog" aria-labelledby="cadastroDepositoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content modal-deposito-caixa">
                <div class="modal-header">
                    <h5 class="modal-title" id="cadastroDepositoModalLabel">Cadastrar Depósito do Caixa</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title2">Total em Caixa:</h5>
                                    <p class="card-text" id="total_em_caixa" style="font-size: 1.5em;">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title2">Depósitos:</h5>
                                    <p class="card-text" id="total_depositos" style="font-size: 1.5em;">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title2">Saldo Transportado:</h5>
                                    <p class="card-text" id="saldo_transportado" style="font-size: 1.5em;">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="button" id="btnTransportarSaldo" style="width: 100%" class="btn btn-danger" onclick="transportarSaldoFecharCaixa()"><i class="fa fa-lock" aria-hidden="true"></i> Fechar Caixa e Transportar Saldo <i class="fa fa-share" aria-hidden="true"></i></button>
                    </div>
                    <hr>
                    <form id="formCadastroDeposito" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="valor_deposito">Valor do Depósito</label>
                                <input type="text" class="form-control" id="valor_deposito" name="valor_deposito" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="tipo_deposito">Tipo de Depósito</label>
                                <select class="form-control" id="tipo_deposito" name="tipo_deposito" required>
                                    <option value="Depósito Bancário">Depósito Bancário</option>
                                    <option value="Espécie">Espécie</option>
                                    <option value="Transferência">Transferência</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="comprovante_deposito">Comprovante de Depósito</label>
                            <input type="file" class="form-control-file" id="comprovante_deposito" name="comprovante_deposito" required>
                        </div>
                        <input type="hidden" id="data_caixa_deposito" name="data_caixa_deposito">
                        <input type="hidden" id="funcionario_deposito" name="funcionario_deposito">
                        <button type="submit" id="btnAdicionarDeposito" style="width: 100%" class="btn btn-primary"><i class="fa fa-plus-circle" aria-hidden="true"></i> Adicionar</button>
                    </form>
                    <hr>
                    <h5>Depósitos Registrados</h5>
                    <table id="tabelaDepositosRegistrados" class="table table-striped table-bordered" style="zoom: 80%">
                        <thead>
                            <tr>
                                <th>Funcionário</th>
                                <th>Data do Caixa</th>
                                <th>Data Cadastro</th>
                                <th>Valor</th>
                                <th>Tipo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="detalhesDepositosRegistrados">
                            <!-- Detalhes dos depósitos serão carregados aqui -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Listagem de Depósitos do Caixa Unificado -->
    <div class="modal fade" id="verDepositosCaixaModal" tabindex="-1" role="dialog" aria-labelledby="verDepositosCaixaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content modal-deposito-caixa-unificado">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="verDepositosCaixaModalLabel">Depósitos do Caixa Unificado</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <table id="tabelaDepositosCaixaUnificado" class="table table-striped table-bordered" style="zoom: 80%">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <th>Data do Caixa</th>
                                    <th>Data Cadastro</th>
                                    <th>Valor</th>
                                    <th>Tipo</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="detalhesDepositosCaixaUnificado">
                                <!-- Detalhes dos depósitos serão carregados aqui -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Abertura de Caixa -->
    <div class="modal fade" id="abrirCaixaModal" tabindex="-1" role="dialog" aria-labelledby="abrirCaixaModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog" role="document">
            <div class="modal-content modal-abrir-caixa">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="abrirCaixaModalLabel">Abrir Caixa do Dia</h5>
                    </div>
                    <div class="modal-body">
                        <form id="formAbrirCaixa">
                            <div class="form-group">
                                <label for="saldo_inicial">Saldo Inicial</label>
                                <input type="text" class="form-control" id="saldo_inicial" name="saldo_inicial" required>
                            </div>
                            <button type="submit" style="width: 100%" class="btn btn-primary">Abrir Caixa</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            $('#tabelaResultados').DataTable({
                "language": {
                    "url": "../style/Portuguese-Brasil.json"
                },
                "pageLength": 10
            });

            // Inicializar máscara de dinheiro
            $('#valor_saida').mask('#.##0,00', {reverse: true});
            $('#valor_deposito').mask('#.##0,00', {reverse: true});
            $('#saldo_inicial').mask('#.##0,00', {reverse: true});

            // Evento de submissão do formulário de saída
            $('#formCadastroSaida').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                $.ajax({
                    url: 'salvar_saida.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Saída cadastrada com sucesso!');
                            $('#cadastroSaidaModal').modal('hide');
                            location.reload();
                        } else {
                            alert('Erro ao cadastrar saída: ' + response.error);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert('Erro ao cadastrar saída: ' + textStatus + ' - ' + errorThrown);
                    }
                });
            });

            // Evento de submissão do formulário de depósito
            $('#formCadastroDeposito').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                $.ajax({
                    url: 'salvar_deposito.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Depósito cadastrado com sucesso!');
                            $('#cadastroDepositoModal').modal('hide');
                            location.reload();
                        } else {
                            alert('Erro ao cadastrar depósito: ' + response.error);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert('Erro ao cadastrar depósito: ' + textStatus + ' - ' + errorThrown);
                    }
                });
            });

            // Carregar Saídas/Despesas no Modal
            $('#cadastroSaidaModal').on('shown.bs.modal', function () {
                var funcionario = $('#funcionario_saida').val();
                var data_caixa = $('#data_caixa_saida').val();

                $.ajax({
                    url: 'listar_saidas.php',
                    type: 'GET',
                    data: {
                        funcionario: funcionario,
                        data_caixa: data_caixa
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            alert('Erro: ' + response.error);
                            return;
                        }

                        var saidas = response.saidas;
                        $('#detalhesSaidasCadastradas').empty();
                        saidas.forEach(function(saida) {
                            var anexo = saida.caminho_anexo ? `<button title="Visualizar" class="btn btn-info btn-sm" onclick="visualizarAnexoSaida('${saida.caminho_anexo}', '${saida.funcionario}', '${saida.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>` : '';
                            $('#detalhesSaidasCadastradas').append(`
                                <tr>
                                    <td>${saida.funcionario}</td>
                                    <td>${saida.titulo}</td>
                                    <td>${formatCurrency(saida.valor_saida)}</td>
                                    <td>${saida.forma_de_saida}</td>
                                    <td>${anexo}</td>
                                    <td>
                                        <button title="Remover" class="btn btn-delete btn-sm" onclick="removerSaida(${saida.id})"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                    </td>
                                </tr>
                            `);
                        });

                        // Inicializar DataTable
                        $('#tabelaSaidasCadastradas').DataTable({
                            "language": {
                                "url": "../style/Portuguese-Brasil.json"
                            },
                            "destroy": true,
                            "pageLength": 10
                        });
                    },
                    error: function() {
                        alert('Erro ao obter saídas.');
                    }
                });
            });

            // Carregar modal de abertura de caixa ao carregar a página
            abrirCaixaModal();

            function abrirCaixaModal() {
                $.ajax({
                    url: 'verificar_caixa_aberto.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.aberto) {
                            return;
                        } else {
                            $('#abrirCaixaModal').modal('show');
                            $('#saldo_inicial').val(response.saldo_transportado ? response.saldo_transportado.toFixed(2).replace('.', ',') : '');
                        }
                    },
                    error: function() {
                        alert('Erro ao verificar caixa.');
                    }
                });
            }

            // Evento de submissão do formulário de abertura de caixa
            $('#formAbrirCaixa').on('submit', function(e) {
                e.preventDefault();

                var saldoInicial = $('#saldo_inicial').val().replace('.', '').replace(',', '.');

                $.ajax({
                    url: 'abrir_caixa.php',
                    type: 'POST',
                    data: {
                        saldo_inicial: saldoInicial
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Caixa aberto com sucesso!');
                            $('#abrirCaixaModal').modal('hide');
                            location.reload();
                        } else {
                            alert('Erro ao abrir caixa: ' + response.error);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert('Erro ao abrir caixa: ' + textStatus + ' - ' + errorThrown);
                    }
                });
            });
        });

        function abrirCaixaModal() {
            $.ajax({
                url: 'verificar_caixa_aberto.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.aberto) {
                        return;
                    } else {
                        $('#abrirCaixaModal').modal('show');
                        $('#saldo_inicial').val(response.saldo_transportado.replace('.', ','));
                    }
                },
                error: function() {
                    alert('Erro ao verificar caixa.');
                }
            });
        }

        function verDetalhes(funcionarios, data, tipo) {
            $.ajax({
                url: 'detalhes_fluxo_caixa.php',
                type: 'GET',
                data: {
                    funcionarios: funcionarios,
                    data: data,
                    tipo: tipo
                },
                success: function(response) {
                    if (response.error) {
                        alert('Erro: ' + response.error);
                        return;
                    }

                    var detalhes = response;

                    // Atualiza os cards no topo do modal
                    $('#cardTotalAtos').text(formatCurrency(detalhes.totalAtos));
                    $('#cardTotalRecebidoConta').text(formatCurrency(detalhes.totalRecebidoConta));
                    $('#cardTotalRecebidoEspecie').text(formatCurrency(detalhes.totalRecebidoEspecie));
                    $('#cardTotalDevolucoes').text(formatCurrency(detalhes.totalDevolucoes));
                    $('#cardTotalEmCaixa').text(formatCurrency(detalhes.totalEmCaixa));
                    $('#cardSaidasDespesas').text(formatCurrency(detalhes.totalSaidasDespesas));
                    $('#cardDepositoCaixa').text(formatCurrency(detalhes.totalDepositoCaixa));
                    $('#cardSaldoInicial').text(formatCurrency(detalhes.saldoInicial));

                    // Debugging logs
                    console.log("Saldo Inicial: " + detalhes.saldoInicial);
                    console.log("Total Recebido em Espécie: " + detalhes.totalRecebidoEspecie);
                    console.log("Total Devolvido em Espécie: " + detalhes.totalDevolvidoEspecie);
                    console.log("Total Saídas e Despesas: " + detalhes.totalSaidasDespesas);
                    console.log("Total Depósito do Caixa: " + detalhes.totalDepositoCaixa);
                    console.log("Total Saldo Transportado: " + detalhes.totalSaldoTransportado);

                    // Atos Liquidados
                    var totalAtos = 0;
                    $('#detalhesAtos').empty();
                    detalhes.atos.forEach(function(ato) {
                        totalAtos += parseFloat(ato.total);
                        $('#detalhesAtos').append(`
                            <tr>
                                <td>${ato.funcionario}</td>    
                                <td>${ato.ordem_servico_id}</td>
                                <td>${ato.cliente}</td>
                                <td>${ato.ato}</td>
                                <td>${ato.descricao}</td>
                                <td>${ato.quantidade_liquidada}</td>
                                <td>${formatCurrency(ato.total)}</td>
                            </tr>
                        `);
                    });
                    $('#totalAtos').text(formatCurrency(totalAtos));

                    // Pagamentos
                    var totalPagamentos = 0;
                    var totalPorTipo = {};
                    $('#detalhesPagamentos').empty();
                    detalhes.pagamentos.forEach(function(pagamento) {
                        totalPagamentos += parseFloat(pagamento.total_pagamento);
                        if (!totalPorTipo[pagamento.forma_de_pagamento]) {
                            totalPorTipo[pagamento.forma_de_pagamento] = 0;
                        }
                        totalPorTipo[pagamento.forma_de_pagamento] += parseFloat(pagamento.total_pagamento);
                        $('#detalhesPagamentos').append(`
                            <tr>
                                <td>${pagamento.funcionario}</td>    
                                <td>${pagamento.ordem_de_servico_id}</td>
                                <td>${pagamento.cliente}</td>
                                <td>${pagamento.forma_de_pagamento}</td>
                                <td>${formatCurrency(pagamento.total_pagamento)}</td>
                            </tr>
                        `);
                    });
                    $('#totalPagamentos').text(formatCurrency(totalPagamentos));

                    // Total por Tipo de Pagamento
                    var totalRecebidoConta = 0;
                    var totalRecebidoEspecie = 0;
                    $('#detalhesTotalPorTipo').empty();
                    for (var tipo in totalPorTipo) {
                        $('#detalhesTotalPorTipo').append(`
                            <tr>
                                <td>${tipo}</td>
                                <td>${formatCurrency(totalPorTipo[tipo])}</td>
                            </tr>
                        `);
                        if (['PIX', 'Transferência Bancária', 'Crédito', 'Débito'].includes(tipo)) {
                            totalRecebidoConta += totalPorTipo[tipo];
                        } else if (tipo === 'Espécie') {
                        totalRecebidoEspecie += totalPorTipo[tipo];
                    }
                    }
                    $('#cardTotalRecebidoConta').text(formatCurrency(totalRecebidoConta));
                    $('#cardTotalRecebidoEspecie').text(formatCurrency(totalRecebidoEspecie));

                    // Devoluções
                    var totalDevolucoes = 0;
                    var totalDevolvidoEspecie = 0;
                    $('#detalhesDevolucoes').empty();
                    detalhes.devolucoes.forEach(function(devolucao) {
                        totalDevolucoes += parseFloat(devolucao.total_devolucao);
                        if (devolucao.forma_devolucao === 'Espécie') {
                            totalDevolvidoEspecie += parseFloat(devolucao.total_devolucao);
                        }
                        $('#detalhesDevolucoes').append(`
                            <tr>
                                <td>${devolucao.funcionario}</td>    
                                <td>${devolucao.ordem_de_servico_id}</td>
                                <td>${devolucao.cliente}</td>
                                <td>${devolucao.forma_devolucao}</td>
                                <td>${formatCurrency(devolucao.total_devolucao)}</td>
                            </tr>
                        `);
                    });
                    $('#totalDevolucoes').text(formatCurrency(totalDevolucoes));

                    // Saídas e Despesas
                    var totalSaidas = 0;
                    $('#detalhesSaidas').empty();
                    detalhes.saidas.forEach(function(saida) {
                        totalSaidas += parseFloat(saida.valor_saida);

                        var dataCaixaFormatada = formatDateForDisplay(saida.data_caixa);
                        var dataCadastroFormatada = formatDateForDisplay(saida.data);

                        $('#detalhesSaidas').append(`
                            <tr>
                                <td>${saida.funcionario}</td>    
                                <td>${saida.titulo}</td>
                                <td>${formatCurrency(saida.valor_saida)}</td>
                                <td>${saida.forma_de_saida}</td>
                                <td>${dataCaixaFormatada}</td>
                                <td>${dataCadastroFormatada}</td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm" onclick="visualizarAnexoSaida('${saida.caminho_anexo}', '${saida.funcionario}', '${saida.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                </td>
                            </tr>
                        `);
                    });
                    $('#totalSaidas').text(formatCurrency(totalSaidas));

                    // Depósitos
                    var totalDepositos = 0;
                    $('#detalhesDepositos').empty();
                    detalhes.depositos.forEach(function(deposito) {
                        totalDepositos += parseFloat(deposito.valor_do_deposito);
                        $('#detalhesDepositos').append(`
                            <tr>
                                <td>${deposito.funcionario}</td>
                                <td>${formatDateForDisplay(deposito.data_caixa)}</td>
                                <td>${formatDateForDisplay2(deposito.data_cadastro)}</td>
                                <td>${formatCurrency(deposito.valor_do_deposito)}</td>
                                <td>${deposito.tipo_deposito}</td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm" onclick="visualizarComprovante('${deposito.caminho_anexo}', '${deposito.funcionario}', '${deposito.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                </td>
                            </tr>
                        `);
                    });
                    $('#totalDepositos').text(formatCurrency(totalDepositos));

                    // Saldo Transportado
                    var totalSaldoTransportado = 0;
                    $('#detalhesSaldoTransportado').empty();
                    detalhes.saldoTransportado.forEach(function(transporte) {
                        totalSaldoTransportado += parseFloat(transporte.valor_transportado);
                        $('#detalhesSaldoTransportado').append(`
                            <tr>
                                <td>${formatDateForDisplay(transporte.data_caixa)}</td>
                                <td>${formatDateForDisplay(transporte.data_transporte)}</td>
                                <td>${formatCurrency(transporte.valor_transportado)}</td>
                                <td>${transporte.funcionario}</td>
                                <td>${transporte.status}</td>
                            </tr>
                        `);
                    });
                    $('#totalSaldoTransportado').text(formatCurrency(totalSaldoTransportado));

                    $('#detalhesModal').modal('show');

                    // Inicializar DataTables
                    $('#tabelaAtos').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true,
                        "pageLength": 10
                    });
                    $('#tabelaPagamentos').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true,
                        "pageLength": 10
                    });
                    $('#tabelaTotalPorTipo').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true,
                        "pageLength": 10
                    });
                    $('#tabelaDevolucoes').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true,
                        "pageLength": 10
                    });
                    $('#tabelaSaidas').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true,
                        "pageLength": 10
                    });
                    $('#tabelaDepositos').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true,
                        "pageLength": 10
                    });
                    $('#tabelaSaldoTransportado').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true,
                        "pageLength": 10
                    });
                },
                error: function() {
                    alert('Erro ao obter detalhes.');
                }
            });
        }

        function cadastrarSaida(funcionarios, data) {
            $('#data_saida').val(data);
            $('#data_caixa_saida').val(data);
            $('#funcionario_saida').val(funcionarios);
            $('#cadastroSaidaModal').modal('show');
        }

        function cadastrarDeposito(funcionarios, data) {
            $('#data_caixa_deposito').val(data);
            $('#funcionario_deposito').val(funcionarios);
            carregarDepositos(funcionarios, data);
            $('#cadastroDepositoModal').modal('show');
        }

        function carregarDepositos(funcionarios, data) {
            $.ajax({
                url: 'listar_depositos.php',
                type: 'GET',
                data: {
                    funcionarios: funcionarios,
                    data: data
                },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        alert('Erro: ' + response.error);
                        return;
                    }

                    if (!Array.isArray(response.depositos)) {
                        alert('Erro: Dados de depósitos inválidos');
                        return;
                    }

                    var depositos = response.depositos;
                    $('#detalhesDepositosRegistrados').empty();
                    depositos.forEach(function(deposito) {
                        var dataCadastroFormatada = formatDateForDisplay2(deposito.data_cadastro);
                        var dataCaixaFormatada = formatDateForDisplay(deposito.data_caixa);
                        $('#detalhesDepositosRegistrados').append(`
                            <tr>
                                <td>${deposito.funcionario}</td>
                                <td>${dataCaixaFormatada}</td>
                                <td>${dataCadastroFormatada}</td>
                                <td>${formatCurrency(deposito.valor_do_deposito)}</td>
                                <td>${deposito.tipo_deposito}</td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm" onclick="visualizarComprovante('${deposito.caminho_anexo}', '${deposito.funcionario}', '${deposito.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <button title="Remover" style="margin-bottom: 5px !important;" class="btn btn-delete btn-sm" onclick="removerDeposito(${deposito.id})"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                </td>
                            </tr>
                        `);
                    });

                    var saldoInicial = parseFloat(response.saldoInicial);
                    var totalRecebidoEspecie = parseFloat(response.totalRecebidoEspecie);
                    var totalDevolvidoEspecie = parseFloat(response.totalDevolvidoEspecie);
                    var totalSaidasDespesas = parseFloat(response.totalSaidasDespesas);
                    var totalDepositoCaixa = parseFloat(response.totalDepositoCaixa);
                    var totalSaldoTransportado = parseFloat(response.totalSaldoTransportado);

                    // Calcula o total em caixa levando em consideração o saldo transportado para a data e funcionário específicos
                    var totalEmCaixa = saldoInicial + totalRecebidoEspecie - totalDevolvidoEspecie - totalSaidasDespesas - totalDepositoCaixa;

                    // Subtrai o saldo transportado apenas se ele for para o mesmo funcionário e a mesma data do caixa
                    if (response.data_caixa === data && response.funcionario === funcionarios) {
                        totalEmCaixa -= totalSaldoTransportado;
                    }

                    // Atualiza os valores dos cards
                    $('#total_em_caixa').text(formatCurrency(totalEmCaixa));
                    $('#total_depositos').text(formatCurrency(totalDepositoCaixa));
                    $('#saldo_transportado').text(formatCurrency(totalSaldoTransportado));

                    // Desabilitar botões se o total em caixa for zero
                    if (totalEmCaixa === 0) {
                        $('#btnTransportarSaldo').prop('disabled', true);
                        $('#btnAdicionarDeposito').prop('disabled', true);
                    }

                    // Inicializar DataTable
                    $('#tabelaDepositosRegistrados').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true,
                        "pageLength": 10
                    });
                },
                error: function() {
                    alert('Erro ao obter depósitos.');
                }
            });
        }

        function carregarDepositosCaixaUnificado(data) {
            $.ajax({
                url: 'listar_depositos_unificado.php',
                type: 'GET',
                data: {
                    data: data
                },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        alert('Erro: ' + response.error);
                        return;
                    }

                    if (!Array.isArray(response.depositos)) {
                        alert('Erro: Dados de depósitos inválidos');
                        return;
                    }

                    var depositos = response.depositos;
                    $('#detalhesDepositosCaixaUnificado').empty();
                    depositos.forEach(function(deposito) {
                        var dataCadastroFormatada = formatDateForDisplay2(deposito.data_cadastro);
                        var dataCaixaFormatada = formatDateForDisplay(deposito.data_caixa);
                        $('#detalhesDepositosCaixaUnificado').append(`
                            <tr>
                                <td>${deposito.funcionario}</td>
                                <td>${dataCaixaFormatada}</td>
                                <td>${dataCadastroFormatada}</td>
                                <td>${formatCurrency(deposito.valor_do_deposito)}</td>
                                <td>${deposito.tipo_deposito}</td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm" onclick="visualizarComprovanteCaixaUnificado('${deposito.caminho_anexo}', '${deposito.funcionario}', '${deposito.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <button title="Remover" style="margin-bottom: 5px !important;" class="btn btn-delete btn-sm" onclick="removerDeposito(${deposito.id})"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                </td>
                            </tr>
                        `);
                    });

                    // Inicializar DataTable
                    $('#tabelaDepositosCaixaUnificado').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true,
                        "pageLength": 10
                    });
                },
                error: function() {
                    alert('Erro ao obter depósitos.');
                }
            });
        }

        function formatDateForDisplay(date) {
            var d = new Date(date + 'T00:00:00'); // Adiciona o horário para evitar problemas de fuso horário
            var day = ('0' + d.getUTCDate()).slice(-2);
            var month = ('0' + (d.getUTCMonth() + 1)).slice(-2);
            var year = d.getUTCFullYear();
            return `${day}/${month}/${year}`;
        }

        function formatDateForDisplay2(date) {
            var d = new Date(date);
            var day = ('0' + d.getDate()).slice(-2);
            var month = ('0' + (d.getMonth() + 1)).slice(-2);
            var year = d.getFullYear();
            return `${day}/${month}/${year}`;
        }

        function visualizarComprovante(caminho, funcionario, data_caixa) {
            var dir = `anexos/${formatDateForDir(data_caixa)}/${funcionario}/`;
            window.open(dir + caminho, '_blank');
        }

        function visualizarComprovanteCaixaUnificado(caminho, funcionario, data_caixa) {
            var dir = `anexos/${formatDateForDir(data_caixa)}/${funcionario}/`;
            window.open(dir + caminho, '_blank');
        }

        function visualizarAnexoSaida(caminho, funcionario, data_caixa) {
            var dir = `anexos/${data_caixa}/${funcionario}/saidas/`;
            window.open(dir + caminho, '_blank');
        }

        function formatDateForDir(date) {
            var d = new Date(date + 'T00:00:00'); // Adiciona o horário para evitar problemas de fuso horário
            var day = ('0' + d.getUTCDate()).slice(-2);
            var month = ('0' + (d.getUTCMonth() + 1)).slice(-2);
            var year = d.getUTCFullYear().toString().slice(-2);
            return `${day}-${month}-${year}`;
        }

        function removerDeposito(id) {
            if (confirm('Deseja realmente remover este depósito?')) {
                $.post('remover_deposito.php', { id: id }, function(response) {
                    if (response.success) {
                        alert('Depósito removido com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao remover depósito.');
                    }
                }, 'json');
            }
        }

        function removerSaida(id) {
            if (confirm('Deseja realmente remover esta saída/despesa?')) {
                $.post('update_saida.php', { id: id, status: 'removido' }, function(response) {
                    if (response.success) {
                        alert('Saída/Despesa removida com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao remover saída/despesa.');
                    }
                }, 'json');
            }
        }

        function verDepositosCaixa(data) {
            carregarDepositosCaixaUnificado(data);
            $('#verDepositosCaixaModal').modal('show');
        }

        function formatCurrency(value) {
            if (isNaN(value)) return 'R$ 0,00';
            return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',').replace(/\d(?=(\d{3})+,)/g, '$&.');
        }

        function transportarSaldoFecharCaixa() {
            var totalEmCaixa = $('#total_em_caixa').text().replace('.', '').replace(',', '.');
            var dataCaixa = $('#data_caixa_deposito').val();
            var funcionario = $('#funcionario_deposito').val();

            $.ajax({
                url: 'transportar_saldo_fechar_caixa.php',
                type: 'POST',
                data: {
                    total_em_caixa: totalEmCaixa,
                    data_caixa: dataCaixa,
                    funcionario: funcionario
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Saldo transportado e caixa fechado com sucesso!');
                        $('#cadastroDepositoModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Erro ao transportar saldo e fechar caixa: ' + response.error);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('Erro ao transportar saldo e fechar caixa: ' + textStatus + ' - ' + errorThrown);
                }
            });
        }

        // Adicionar evento para recarregar a página ao fechar os modais
        $('#detalhesModal').on('hidden.bs.modal', function () {
            location.reload();
        });

        $('#cadastroSaidaModal').on('hidden.bs.modal', function () {
            location.reload();
        });

        $('#cadastroDepositoModal').on('hidden.bs.modal', function () {
            location.reload();
        });

        $('#verDepositosCaixaModal').on('hidden.bs.modal', function () {
            location.reload();
        });
    </script>


</body>
</html>
