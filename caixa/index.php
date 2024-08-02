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
            margin-bottom: 0px!important;
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
            text-align: center;
            font-weight: bold;
        }

        .card-title {
            font-size: 1.25rem;
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
            <h3>Pesquisar Fluxo de Caixa</h3>
            <hr>
            <form id="pesquisarForm" method="GET">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="funcionario">Funcionário:</label>
                        <select class="form-control" id="funcionario" name="funcionario" <?php echo $user['nivel_de_acesso'] === 'usuario' ? 'disabled' : ''; ?>>
                            <?php if ($user['nivel_de_acesso'] === 'administrador') { ?>
                                <option value="todos">Todos</option>
                                <option value="caixa_unificado">Caixa Unificado</option>
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
                        <button type="button" style="width: 100%;" class="btn btn-success" onclick="window.location.href='criar_os.php'"><i class="fa fa-plus" aria-hidden="true"></i> Criar OS</button>
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
                            <th>Total Atos Liquidados</th>
                            <th>Total Pagamentos</th>
                            <th>Total Devoluções</th>
                            <th>Saídas</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conditions = [];
                        $params = [];
                        $filtered = false;
                        $isUnificado = false;
                        $funcionariosConcatenados = '';

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
                                        FROM saidas_despesas
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
                                        FROM saidas_despesas
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
                            ?>
                            <tr>
                                <td><?php echo $funcionarios; ?></td>
                                <td><?php echo 'R$ ' . number_format($total_atos, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($total_pagamentos, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($total_devolucoes, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($total_saidas, 2, ',', '.'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($data)); ?></td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm" onclick="verDetalhes('<?php echo $funcionarios; ?>', '<?php echo $data; ?>', '<?php echo $isUnificado ? 'unificado' : 'individual'; ?>')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <?php if (!$isUnificado) { ?>
                                    <button title="Cadastrar Saída" class="btn btn-edit btn-sm" onclick="cadastrarSaida('<?php echo $funcionarios; ?>', '<?php echo $data; ?>')"><i class="fa fa-sign-out" aria-hidden="true"></i></button>
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
                        <div class="col-md-2">
                            <div class="card text-white bg-primary mb-3">
                                <div class="card-header">Total Atos Liquidados</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardTotalAtos">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-white bg-warning mb-3">
                                <div class="card-header">Total Recebido em Conta</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardTotalRecebidoConta">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-white bg-success mb-3">
                                <div class="card-header">Total Recebido em Espécie</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardTotalRecebidoEspecie">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-white bg-danger mb-3">
                                <div class="card-header">Total Devoluções</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardTotalDevolucoes">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-white bg-secondary mb-3">
                                <div class="card-header">Saídas e Despesas</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardSaidasDespesas">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-white bg-info mb-3">
                                <div class="card-header">Total em Caixa</div>
                                <div class="card-body">
                                    <h5 class="card-title" id="cardTotalEmCaixa">R$ 0,00</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h5 class="table-title">Atos Liquidados</h5>
                    <table id="tabelaAtos" class="table table-striped table-bordered" style="zoom: 80%">
                        <thead>
                            <tr>
                                <th>Ordem de Serviço</th>
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
                    <hr>
                    <h5 class="table-title">Pagamentos</h5>
                    <table id="tabelaPagamentos" class="table table-striped table-bordered" style="zoom: 80%">
                        <thead>
                            <tr>
                                <th>Ordem de Serviço</th>
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
                    <hr>
                    <h5 class="table-title">Total por Tipo de Pagamento</h5>
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
                    <hr>
                    <h5 class="table-title">Devoluções</h5>
                    <table id="tabelaDevolucoes" class="table table-striped table-bordered" style="zoom: 80%">
                        <thead>
                            <tr>
                                <th>Ordem de Serviço</th>
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
                    <hr>
                    <h5 class="table-title">Saídas e Despesas</h5>
                    <table id="tabelaSaidas" class="table table-striped table-bordered" style="zoom: 80%">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Valor</th>
                                <th>Forma de Saída</th>
                            </tr>
                        </thead>
                        <tbody id="detalhesSaidas">
                            <!-- Detalhes das saídas serão carregados aqui -->
                        </tbody>
                    </table>
                    <h6 class="total-label">Total Saídas: <span id="totalSaidas"></span></h6>
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
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cadastroSaidaModalLabel">Cadastrar Saída/Despesa</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formCadastroSaida">
                        <div class="form-group">
                            <label for="titulo">Título</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" required>
                        </div>
                        <div class="form-group">
                            <label for="valor_saida">Valor da Saída</label>
                            <input type="text" class="form-control" id="valor_saida" name="valor_saida" required>
                        </div>
                        <div class="form-group">
                            <label for="forma_de_saida">Forma de Saída</label>
                            <select class="form-control" id="forma_de_saida" name="forma_de_saida" required>
                                <option value="PIX">PIX</option>
                                <option value="Transferência Bancária">Transferência Bancária</option>
                                <option value="Espécie">Espécie</option>
                            </select>
                        </div>
                        <input type="hidden" id="data_saida" name="data_saida">
                        <input type="hidden" id="funcionario_saida" name="funcionario_saida">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
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
                }
            });

            // Inicializar máscara de dinheiro
            $('#valor_saida').mask('#.##0,00', {reverse: true});

            // Evento de submissão do formulário de saída
            $('#formCadastroSaida').on('submit', function(e) {
                e.preventDefault();

                var dados = $(this).serialize();
                $.post('salvar_saida.php', dados, function(response) {
                    if (response.success) {
                        alert('Saída cadastrada com sucesso!');
                        $('#cadastroSaidaModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Erro ao cadastrar saída.');
                    }
                }, 'json');
            });
        });

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

                    // Atos Liquidados
                    var totalAtos = 0;
                    $('#detalhesAtos').empty();
                    detalhes.atos.forEach(function(ato) {
                        totalAtos += parseFloat(ato.total);
                        $('#detalhesAtos').append(`
                            <tr>
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
                                <td>${devolucao.ordem_servico_id}</td>
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
                        $('#detalhesSaidas').append(`
                            <tr>
                                <td>${saida.titulo}</td>
                                <td>${formatCurrency(saida.valor_saida)}</td>
                                <td>${saida.forma_de_saida}</td>
                            </tr>
                        `);
                    });
                    $('#totalSaidas').text(formatCurrency(totalSaidas));

                    // Total em Caixa
                    var totalEmCaixa = totalRecebidoEspecie - totalDevolvidoEspecie - totalSaidas;
                    $('#cardTotalEmCaixa').text(formatCurrency(totalEmCaixa));

                    $('#detalhesModal').modal('show');

                    // Inicializar DataTables
                    $('#tabelaAtos').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true
                    });
                    $('#tabelaPagamentos').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true
                    });
                    $('#tabelaTotalPorTipo').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true
                    });
                    $('#tabelaDevolucoes').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true
                    });
                    $('#tabelaSaidas').DataTable({
                        "language": {
                            "url": "../style/Portuguese-Brasil.json"
                        },
                        "destroy": true
                    });
                },
                error: function() {
                    alert('Erro ao obter detalhes.');
                }
            });
        }

        function cadastrarSaida(funcionarios, data) {
            $('#data_saida').val(data);
            $('#funcionario_saida').val(funcionarios);
            $('#cadastroSaidaModal').modal('show');
        }

        function formatCurrency(value) {
            return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',').replace(/\d(?=(\d{3})+,)/g, '$&.');
        }
    </script>
</body>
</html>
