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
    <title>Pesquisar Ordens de Serviço</title>
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
            max-width: 30%;
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

    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisar Ordens de Serviço</h3>
            <hr>
            <form id="pesquisarForm" method="GET">
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label for="os_id">Nº OS:</label>
                        <input type="number" class="form-control" id="os_id" name="os_id" min="1">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="cliente">Cliente:</label>
                        <input type="text" class="form-control" id="cliente" name="cliente">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="cpf_cliente">CPF/CNPJ:</label>
                        <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="total_os">Valor Total:</label>
                        <input type="text" class="form-control" id="total_os" name="total_os">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="funcionario">Funcionário:</label>
                        <select class="form-control" id="funcionario" name="funcionario">
                            <option value="">Selecione o Funcionário</option>
                            <?php
                            $conn = getDatabaseConnection();
                            $stmt = $conn->query("SELECT DISTINCT criado_por FROM ordens_de_servico");
                            $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($funcionarios as $funcionario) {
                                echo '<option value="' . $funcionario['criado_por'] . '">' . $funcionario['criado_por'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label for="data_inicial">Data Inicial:</label>
                        <input type="date" class="form-control" id="data_inicial" name="data_inicial">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="data_final">Data Final:</label>
                        <input type="date" class="form-control" id="data_final" name="data_final">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="descricao_os">Título da OS:</label>
                        <input type="text" class="form-control" id="descricao_os" name="descricao_os">
                    </div>
                    <div class="form-group col-md-5">
                        <label for="observacoes">Observações:</label>
                        <input type="text" class="form-control" id="observacoes" name="observacoes">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <button type="submit" style="width: 100%;" class="btn btn-primary"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar</button>
                    </div>
                    <div class="col-md-4">
                        <button id="add-button" type="button" style="width: 100%; color: #fff!important" class="btn btn-info" onclick="window.open('tabela_de_emolumentos.php')"><i class="fa fa-table" aria-hidden="true"></i> Tabela de Emolumentos</button>
                    </div>
                    <div class="col-md-4 text-right">
                        <button id="add-button" type="button" style="width: 100%;" class="btn btn-success" onclick="window.location.href='criar_os.php'"><i class="fa fa-plus" aria-hidden="true"></i> Criar OS</button>
                    </div>
                </div>
            </form>
            <hr>
            <div id="resultados">
                <h5>Resultados da Pesquisa</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 80%">
                    <thead>
                        <tr>
                            <th style="width: 7%;">Nº OS</th>
                            <th style="width: 11%;">Cliente</th>
                            <th style="width: 11%;">CPF/CNPJ</th>
                            <th style="width: 13%;">Título da OS</th>
                            <th style="width: 10%;">Valor Total</th>
                            <th style="width: 10%;">Dep. Prévio</th>
                            <th style="width: 10%;">Liquidado</th>
                            <th style="width: 12%;">Observações</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th style="width: 9%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = getDatabaseConnection();
                        $conditions = [];
                        $params = [];
                        $filtered = false;

                        if (!empty($_GET['os_id'])) {
                            $conditions[] = 'id = :os_id';
                            $params[':os_id'] = $_GET['os_id'];
                            $filtered = true;
                        }
                        if (!empty($_GET['cliente'])) {
                            $conditions[] = 'cliente LIKE :cliente';
                            $params[':cliente'] = '%' . $_GET['cliente'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['cpf_cliente'])) {
                            $conditions[] = 'cpf_cliente LIKE :cpf_cliente';
                            $params[':cpf_cliente'] = '%' . $_GET['cpf_cliente'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['total_os'])) {
                            $conditions[] = 'total_os = :total_os';
                            $params[':total_os'] = str_replace(',', '.', str_replace('.', '', $_GET['total_os']));
                            $filtered = true;
                        }
                        if (!empty($_GET['data_inicial']) && !empty($_GET['data_final'])) {
                            $conditions[] = 'DATE(data_criacao) BETWEEN :data_inicial AND :data_final';
                            $params[':data_inicial'] = $_GET['data_inicial'];
                            $params[':data_final'] = $_GET['data_final'];
                            $filtered = true;
                        } elseif (!empty($_GET['data_inicial'])) {
                            $conditions[] = 'DATE(data_criacao) >= :data_inicial';
                            $params[':data_inicial'] = $_GET['data_inicial'];
                            $filtered = true;
                        } elseif (!empty($_GET['data_final'])) {
                            $conditions[] = 'DATE(data_criacao) <= :data_final';
                            $params[':data_final'] = $_GET['data_final'];
                            $filtered = true;
                        }
                        if (!empty($_GET['funcionario'])) {
                            $conditions[] = 'criado_por LIKE :funcionario';
                            $params[':funcionario'] = $_GET['funcionario'];
                            $filtered = true;
                        }
                        if (!empty($_GET['descricao_os'])) {
                            $conditions[] = 'descricao_os LIKE :descricao_os';
                            $params[':descricao_os'] = '%' . $_GET['descricao_os'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['observacoes'])) {
                            $conditions[] = 'observacoes LIKE :observacoes';
                            $params[':observacoes'] = '%' . $_GET['observacoes'] . '%';
                            $filtered = true;
                        }

                        $sql = 'SELECT * FROM ordens_de_servico';
                        if ($conditions) {
                            $sql .= ' WHERE ' . implode(' AND ', $conditions);
                        }

                        if (!$filtered) {
                            $sql .= ' ORDER BY data_criacao DESC LIMIT 10';
                        }

                        $stmt = $conn->prepare($sql);
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        $stmt->execute();
                        $ordens = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($ordens as $ordem) {
                            // Calcula o depósito prévio
                            $stmt = $conn->prepare('SELECT SUM(total_pagamento) as deposito_previo FROM pagamento_os WHERE ordem_de_servico_id = :os_id');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $deposito_previo = $stmt->fetchColumn() ?: 0;

                            // Calcula o total liquidado
                            $stmt = $conn->prepare('SELECT SUM(total) as total_liquidado FROM atos_liquidados WHERE ordem_servico_id = :os_id');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $total_liquidado = $stmt->fetchColumn() ?: 0;

                            // Calcula o total devolvido
                            $stmt = $conn->prepare('SELECT SUM(total_devolucao) as total_devolvido FROM devolucao_os WHERE ordem_de_servico_id = :os_id');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $total_devolvido = $stmt->fetchColumn() ?: 0;

                            // Verificar status dos atos
                            $stmt = $conn->prepare('SELECT status FROM ordens_de_servico_itens WHERE ordem_servico_id = :os_id');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $atos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            $statusOS = 'Pendente';
                            $statusClasses = ['Pendente' => 'status-pendente', 'Parcial' => 'status-parcialmente', 'Liquidado' => 'status-liquidado'];

                            $allLiquidado = true;
                            $hasParcialmenteLiquidado = false;
                            $allPendente = true;

                            foreach ($atos as $ato) {
                                if ($ato['status'] == 'liquidado') {
                                    $allPendente = false;
                                } elseif ($ato['status'] == 'parcialmente liquidado') {
                                    $hasParcialmenteLiquidado = true;
                                    $allPendente = false;
                                    $allLiquidado = false;
                                } elseif ($ato['status'] == null) {
                                    $allLiquidado = false;
                                }
                            }

                            if ($allLiquidado && count($atos) > 0) {
                                $statusOS = 'Liquidado';
                            } elseif ($hasParcialmenteLiquidado || !$allPendente) {
                                $statusOS = 'Parcial';
                            }

                            // Calcular saldo considerando devoluções
                            $saldo = ($deposito_previo - $total_devolvido) - $ordem['total_os'];
                            ?>
                            <tr>
                                <td><?php echo $ordem['id']; ?></td>
                                <td><?php echo $ordem['cliente']; ?></td>
                                <td><?php echo $ordem['cpf_cliente']; ?></td>
                                <td><?php echo $ordem['descricao_os']; ?></td>
                                <td><?php echo 'R$ ' . number_format($ordem['total_os'], 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($deposito_previo, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?></td>
                                <td><?php echo strlen($ordem['observacoes']) > 100 ? substr($ordem['observacoes'], 0, 100) . '...' : $ordem['observacoes']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($ordem['data_criacao'])); ?></td>
                                <td><span style="font-size: 13px" class="status-label <?php echo $statusClasses[$statusOS]; ?>"><?php echo $statusOS; ?></span></td>
                                <td>
                                    <button class="btn btn-info btn-sm" title="Visualizar OS" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" onclick="window.open('visualizar_os.php?id=<?php echo $ordem['id']; ?>', '_blank')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <button class="btn btn-success btn-sm" title="Pagamentos e Devoluções" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" onclick="abrirPagamentoModal(<?php echo $ordem['id']; ?>, '<?php echo $ordem['cliente']; ?>', <?php echo $ordem['total_os']; ?>, <?php echo $deposito_previo; ?>, <?php echo $total_liquidado; ?>, <?php echo $total_devolvido; ?>, <?php echo $saldo; ?>, '<?php echo $statusOS; ?>')"><i class="fa fa-money" aria-hidden="true"></i></button>
                                    <button type="button" title="Imprimir OS" class="btn btn-primary btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" onclick="window.open('imprimir-os.php?id=<?php echo $ordem['id']; ?>', '_blank')"><i class="fa fa-print" aria-hidden="true"></i></button>
                                    <button class="btn btn-secondary btn-sm" title="Anexos" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" onclick="abrirAnexoModal(<?php echo $ordem['id']; ?>)"><i class="fa fa-paperclip" aria-hidden="true"></i></button>
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

    <!-- Modal de Pagamento -->
    <div class="modal fade" id="pagamentoModal" tabindex="-1" role="dialog" aria-labelledby="pagamentoModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pagamentoModalLabel">Efetuar Pagamento</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="total_os_modal">Valor Total da OS</label>
                        <input type="text" class="form-control" id="total_os_modal" readonly>
                    </div>
                    <div class="form-group">
                        <label for="forma_pagamento">Forma de Pagamento</label>
                        <select class="form-control" id="forma_pagamento">
                            <option value="">Selecione</option>
                            <option value="Espécie">Espécie</option>
                            <option value="Crédito">Crédito</option>
                            <option value="Débito">Débito</option>
                            <option value="PIX">PIX</option>
                            <option value="Transferência Bancária">Transferência Bancária</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="valor_pagamento">Valor do Pagamento</label>
                        <input type="text" class="form-control" id="valor_pagamento">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="adicionarPagamento()">Adicionar</button>
                    <hr>
                    <div id="pagamentosAdicionados">
                        <h5>Pagamentos Adicionados</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 50%;">Forma de Pagamento</th>
                                    <th style="width: 40%;">Valor</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="pagamentosTable">
                                <!-- Pagamentos adicionados serão listados aqui -->
                            </tbody>
                        </table>
                    </div>
                    <div class="form-group">
                        <label for="total_pagamento">Valor Pago</label>
                        <input type="text" class="form-control" id="total_pagamento" readonly>
                    </div>
                    <div class="form-group">
                        <label for="valor_liquidado_modal">Valor Liquidado</label>
                        <input type="text" class="form-control" id="valor_liquidado_modal" readonly>
                    </div>
                    <div class="form-group">
                        <label for="saldo_modal">Saldo</label>
                        <input type="text" class="form-control" id="saldo_modal" readonly>
                    </div>
                    <div class="form-group">
                        <label for="valor_devolvido_modal">Valor Devolvido</label>
                        <input type="text" class="form-control" id="valor_devolvido_modal" readonly>
                    </div>
                    <?php if ($saldo > 0.001): ?>
                        <button type="button" class="btn btn-warning" id="btnDevolver" onclick="abrirDevolucaoModal()">Devolver valores</button>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Devolução -->
    <div class="modal fade" id="devolucaoModal" tabindex="-1" role="dialog" aria-labelledby="devolucaoModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="devolucaoModalLabel">Devolver Valores</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="forma_devolucao">Forma de Devolução</label>
                        <select class="form-control" id="forma_devolucao">
                            <option value="">Selecione</option>
                            <option value="Espécie">Espécie</option>
                            <option value="PIX">PIX</option>
                            <option value="Transferência Bancária">Transferência Bancária</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="valor_devolucao">Valor da Devolução</label>
                        <input type="text" class="form-control" id="valor_devolucao">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="salvarDevolucao()">Salvar</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Anexos -->
    <div class="modal fade" id="anexoModal" tabindex="-1" role="dialog" aria-labelledby="anexoModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="anexoModalLabel">Anexos</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                <form id="formAnexos" enctype="multipart/form-data">
                    <div class="custom-file mb-3">
                        <input type="file" class="custom-file-input" id="novo_anexo" name="novo_anexo[]" multiple>
                        <label class="custom-file-label" for="novo_anexo">Selecione os arquivos para anexar</label>
                    </div>
                    <button type="button" style="width: 100%" class="btn btn-success" onclick="salvarAnexo()"><i class="fa fa-paperclip" aria-hidden="true"></i> Anexar</button>
                </form>

                    <hr>
                    <div id="anexosAdicionados">
                        <h5>Anexos Adicionados</h5>
                        <table class="table" style="zoom: 90%">
                            <thead>
                                <tr>
                                    <th>Nome do Arquivo</th>
                                    <th style="width: 25%">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="anexosTable">
                                <!-- Anexos adicionados serão listados aqui -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Mensagem -->
    <div class="modal fade" id="mensagemModal" tabindex="-1" role="dialog" aria-labelledby="mensagemModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header error">
                    <h5 class="modal-title" id="mensagemModalLabel">Erro</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="mensagemTexto"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <div aria-live="polite" aria-atomic="true" style="position: relative; z-index: 1050;">
        <div id="toastContainer" style="position: absolute; top: -20px; right: 0;"></div>
    </div>


    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#cpf_cliente').mask('000.000.000-00', { reverse: true }).on('blur', function() {
                var cpfCnpj = $(this).val().replace(/\D/g, '');
                if (cpfCnpj.length === 11) {
                    $(this).mask('000.000.000-00', { reverse: true });
                } else if (cpfCnpj.length === 14) {
                    $(this).mask('00.000.000/0000-00', { reverse: true });
                }
            });

            $('#total_os').mask('#.##0,00', { reverse: true });
            $('#valor_pagamento').mask('#.##0,00', { reverse: true });
            $('#valor_devolucao').mask('#.##0,00', { reverse: true });

            // Inicializar DataTable
            $('#tabelaResultados').DataTable({
                "language": {
                    "url": "../style/Portuguese-Brasil.json"
                }
            });
        });

        function abrirPagamentoModal(osId, cliente, totalOs, totalPagamentos, totalLiquidado, totalDevolvido, saldo, statusOS) {
            $('#total_os_modal').val('R$ ' + totalOs.toFixed(2).replace('.', ','));
            $('#valor_liquidado_modal').val('R$ ' + totalLiquidado.toFixed(2).replace('.', ','));
            $('#valor_devolvido_modal').val('R$ ' + totalDevolvido.toFixed(2).replace('.', ','));
            $('#valor_pagamento').val('');
            $('#forma_pagamento').val('');
            $('#pagamentosTable').empty();
            $('#total_pagamento').val('R$ ' + totalPagamentos.toFixed(2).replace('.', ','));
            $('#saldo_modal').val('R$ ' + saldo.toFixed(2).replace('.', ','));

            // Esconder o botão "Devolver Valores" se o saldo for menor ou igual a 0
            if (saldo <= 0) {
                $('#btnDevolver').hide();
            } else {
                $('#btnDevolver').show();
            }

            $('#pagamentoModal').modal('show');

            // Save the OS ID and client for later use
            window.currentOsId = osId;
            window.currentClient = cliente;
            window.statusOS = statusOS;

            // Atualizar tabela de pagamentos existentes
            atualizarTabelaPagamentos();
        }

        function adicionarPagamento() {
            var formaPagamento = $('#forma_pagamento').val();
            var valorPagamento = parseFloat($('#valor_pagamento').val().replace('.', '').replace(',', '.'));

            if (formaPagamento === "") {
                exibirMensagem('Por favor, selecione uma forma de pagamento.', 'error');
                return;
            }

            if (isNaN(valorPagamento) || valorPagamento <= 0) {
                exibirMensagem('Por favor, insira um valor válido para o pagamento.', 'error');
                return;
            }

            $.ajax({
                url: 'salvar_pagamento.php',
                type: 'POST',
                data: {
                    os_id: window.currentOsId,
                    cliente: window.currentClient,
                    total_os: parseFloat($('#total_os_modal').val().replace('R$ ', '').replace('.', '').replace(',', '.')),
                    funcionario: '<?php echo $_SESSION['username']; ?>',
                    forma_pagamento: formaPagamento,
                    valor_pagamento: valorPagamento
                },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            atualizarTabelaPagamentos();
                            $('#valor_pagamento').val('');
                            exibirMensagem('Pagamento adicionado com sucesso!', 'success');
                        } else {
                            exibirMensagem('Erro ao adicionar pagamento.', 'error');
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao adicionar pagamento.', 'error');
                }
            });
        }

        function atualizarTabelaPagamentos() {
            var pagamentosTable = $('#pagamentosTable');
            pagamentosTable.empty();

            $.ajax({
                url: 'obter_pagamentos.php',
                type: 'POST',
                data: {
                    os_id: window.currentOsId
                },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        var total = 0;

                        response.pagamentos.forEach(function(pagamento, index) {
                            total += parseFloat(pagamento.total_pagamento);

                            pagamentosTable.append(`
                                <tr>
                                    <td>${pagamento.forma_de_pagamento}</td>
                                    <td>R$ ${parseFloat(pagamento.total_pagamento).toFixed(2).replace('.', ',')}</td>
                                    <td><button type="button" class="btn btn-delete btn-sm" onclick="removerPagamento(${pagamento.id})" ${window.statusOS !== 'Pendente' ? 'disabled' : ''}><i class="fa fa-trash-o" aria-hidden="true"></i></button></td>
                                </tr>
                            `);
                        });

                        $('#total_pagamento').val('R$ ' + total.toFixed(2).replace('.', ','));
                        var saldo = total - parseFloat($('#total_os_modal').val().replace('R$ ', '').replace('.', '').replace(',', '.')) - parseFloat($('#valor_devolvido_modal').val().replace('R$ ', '').replace('.', '').replace(',', '.'));
                        $('#saldo_modal').val('R$ ' + saldo.toFixed(2).replace('.', ','));

                        if (saldo <= 0) {
                            $('#btnDevolver').hide();
                        } else {
                            $('#btnDevolver').show();
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao atualizar tabela de pagamentos.', 'error');
                }
            });
        }

        function removerPagamento(pagamentoId) {
            $.ajax({
                url: 'remover_pagamento.php',
                type: 'POST',
                data: {
                    pagamento_id: pagamentoId
                },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            atualizarTabelaPagamentos();
                            exibirMensagem('Pagamento removido com sucesso!', 'success');
                        } else {
                            exibirMensagem('Erro ao remover pagamento.', 'error');
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao remover pagamento.', 'error');
                }
            });
        }

        function exibirMensagem(mensagem, tipo) {
            var toastContainer = $('#toastContainer');
            var toastId = 'toast-' + new Date().getTime();
            var toastHTML = `
                <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3000">
                    <div class="toast-header ${tipo === 'success' ? 'bg-success text-white' : 'bg-danger text-white'}">
                        <strong class="mr-auto">${tipo === 'success' ? 'Sucesso' : 'Erro'}</strong>
                        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="toast-body">
                        ${mensagem}
                    </div>
                </div>
            `;
            toastContainer.append(toastHTML);
            $('#' + toastId).toast('show').on('hidden.bs.toast', function () {
                $(this).remove();
            });
        }

        function abrirDevolucaoModal() {
            $('#devolucaoModal').modal('show');
        }

        function salvarDevolucao() {
            var formaDevolucao = $('#forma_devolucao').val();
            var valorDevolucao = parseFloat($('#valor_devolucao').val().replace('.', '').replace(',', '.'));
            var saldoAtual = parseFloat($('#saldo_modal').val().replace('R$ ', '').replace('.', '').replace(',', '.'));

            if (formaDevolucao === "") {
                exibirMensagem('Por favor, selecione uma forma de devolução.', 'error');
                return;
            }

            if (isNaN(valorDevolucao) || valorDevolucao <= 0 || valorDevolucao > saldoAtual) {
                exibirMensagem('Por favor, insira um valor válido para a devolução que não seja maior que o saldo disponível.', 'error');
                return;
            }

            $.ajax({
                url: 'salvar_devolucao.php',
                type: 'POST',
                data: {
                    os_id: window.currentOsId,
                    cliente: window.currentClient,
                    total_os: parseFloat($('#total_os_modal').val().replace('R$ ', '').replace('.', '').replace(',', '.')),
                    total_devolucao: valorDevolucao,
                    forma_devolucao: formaDevolucao,
                    funcionario: '<?php echo $_SESSION['username']; ?>'
                },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            $('#devolucaoModal').modal('hide');
                            exibirMensagem('Devolução salva com sucesso!', 'success');
                            atualizarTabelaPagamentos();
                        } else {
                            exibirMensagem('Erro ao salvar devolução.', 'error');
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao salvar devolução.', 'error');
                }
            });
        }

        function abrirAnexoModal(osId) {
            $('#anexoModal').modal('show');
            window.currentOsId = osId;
            atualizarTabelaAnexos();
        }

        function salvarAnexo() {
            var formData = new FormData($('#formAnexos')[0]);
            formData.append('os_id', window.currentOsId);
            formData.append('funcionario', '<?php echo $_SESSION['username']; ?>');

            $.ajax({
                url: 'salvar_anexo.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            $('#novo_anexo').val('');
                            exibirMensagem('Anexo salvo com sucesso!', 'success');
                            atualizarTabelaAnexos();
                        } else {
                            exibirMensagem('Erro ao salvar anexo.', 'error');
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao salvar anexo.', 'error');
                }
            });
        }

        function atualizarTabelaAnexos() {
            var anexosTable = $('#anexosTable');
            anexosTable.empty();

            $.ajax({
                url: 'obter_anexos.php',
                type: 'POST',
                data: {
                    os_id: window.currentOsId
                },
                success: function(response) {
                    try {
                        response = JSON.parse(response);

                        response.anexos.forEach(function(anexo, index) {
                            var caminhoCompleto = 'anexos/' + window.currentOsId + '/' + anexo.caminho_anexo;
                            anexosTable.append(`
                                <tr>
                                    <td>${anexo.caminho_anexo}</td>
                                    <td>
                                        <button type="button" class="bbtn btn-info btn-sm" onclick="visualizarAnexo('${caminhoCompleto}')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                        <button type="button" class="btn btn-delete btn-sm" onclick="removerAnexo(${anexo.id})"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
                                    </td>
                                </tr>
                            `);
                        });
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao atualizar tabela de anexos.', 'error');
                }
            });
        }

        function visualizarAnexo(caminho) {
            window.open(caminho, '_blank');
        }

        function removerAnexo(anexoId) {
            $.ajax({
                url: 'remover_anexo.php',
                type: 'POST',
                data: {
                    anexo_id: anexoId
                },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            exibirMensagem('Anexo removido com sucesso!', 'success');
                            atualizarTabelaAnexos();
                        } else {
                            exibirMensagem('Erro ao remover anexo.', 'error');
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao remover anexo.', 'error');
                }
            });
        }

    </script>
    <?php
    include(__DIR__ . '/../rodape.php');
    ?>
</body>

</html>
