<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

// Verifique se a conexão está definida
if (!isset($conn)) {
    die("Erro ao conectar ao banco de dados");
}

// Buscar dados da OS
$os_id = $_GET['id'];
$os_query = $conn->prepare("SELECT * FROM ordens_de_servico WHERE id = ?");
$os_query->bind_param("i", $os_id);
$os_query->execute();
$os_result = $os_query->get_result();
$ordem_servico = $os_result->fetch_assoc();

// Buscar dados dos itens da OS
$os_items_query = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ?");
$os_items_query->bind_param("i", $os_id);
$os_items_query->execute();
$os_items_result = $os_items_query->get_result();
$ordem_servico_itens = $os_items_result->fetch_all(MYSQLI_ASSOC);

// Buscar dados dos pagamentos
$pagamentos_query = $conn->prepare("SELECT * FROM pagamento_os WHERE ordem_de_servico_id = ?");
$pagamentos_query->bind_param("i", $os_id);
$pagamentos_query->execute();
$pagamentos_result = $pagamentos_query->get_result();
$pagamentos = $pagamentos_result->fetch_all(MYSQLI_ASSOC);

// Buscar dados das devoluções
$devolucoes_query = $conn->prepare("SELECT * FROM devolucao_os WHERE ordem_de_servico_id = ?");
$devolucoes_query->bind_param("i", $os_id);
$devolucoes_query->execute();
$devolucoes_result = $devolucoes_query->get_result();
$devolucoes = $devolucoes_result->fetch_all(MYSQLI_ASSOC);

// Buscar dados dos atos liquidados
$atos_liquidados_query = $conn->prepare("SELECT SUM(total) as total_liquidado FROM atos_liquidados WHERE ordem_servico_id = ?");
$atos_liquidados_query->bind_param("i", $os_id);
$atos_liquidados_query->execute();
$atos_liquidados_result = $atos_liquidados_query->get_result();
$atos_liquidados = $atos_liquidados_result->fetch_assoc();
$total_liquidado = $atos_liquidados['total_liquidado'] ?? 0.0; // Valor padrão 0.0 se nenhum ato for liquidado

// Verificar se há itens liquidados
$has_liquidated = false;
$has_ato_17 = false;
foreach ($ordem_servico_itens as $item) {
    if ($item['status'] == 'liquidado') {
        $has_liquidated = true;
    }
    if (strpos($item['ato'], '17.') === 0) {
        $has_ato_17 = true;
    }
}

// Calcular total dos pagamentos
$total_pagamentos = 0;
foreach ($pagamentos as $pagamento) {
    $total_pagamentos += $pagamento['total_pagamento'];
}

// Calcular total das devoluções
$total_devolucoes = 0;
foreach ($devolucoes as $devolucao) {
    $total_devolucoes += $devolucao['total_devolucao'];
}

// Calcular total dos repasses
$total_repasses = 0;
$repasse_query = $conn->prepare("SELECT total_repasse FROM repasse_credor WHERE ordem_de_servico_id = ?");
$repasse_query->bind_param("i", $os_id);
$repasse_query->execute();
$repasse_result = $repasse_query->get_result();
while ($repasse = $repasse_result->fetch_assoc()) {
    $total_repasses += $repasse['total_repasse'];
}

// Calcular valor líquido pago
$valor_pago_liquido = $total_pagamentos - $total_devolucoes;

// Calcular saldo
$saldo = $valor_pago_liquido - $ordem_servico['total_os'] - $total_repasses;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Ordem de Serviço</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <style>
        .btn-print, .btn-payment, .btn-repasse {
            margin-left: 10px;
        }
        .modal-content {
            border-radius: 10px;
        }
        .modal-dialog {
            max-width: 400px;
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
            background-color: #dc3545; /* cor de fundo vermelha para erros */
            color: white;
        }
        .modal-header.success {
            background-color: #28a745; /* cor de fundo verde para sucessos */
            color: white;
        }
        .status-label {
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            text-align: center;
            white-space: nowrap; /* Adicionado para evitar quebra de linha */
        }
        .status-pendente {
            background-color: #dc3545; /* vermelho */
        }
        .status-liquidado {
            background-color: #28a745; /* verde */
        }
        .status-parcial {
            background-color: #ffc107; /* amarelo */
        }
    </style>
</head>
<body>
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <h3>Ordem de Serviço nº: <?php echo $ordem_servico['id']; ?></h3>
            <div>
                <button style="margin-bottom: 5px!important;" type="button" class="btn btn-primary btn-print" onclick="imprimirOS()"><i class="fa fa-print" aria-hidden="true"></i> Imprimir OS</button>
                <button style="width: 100px; height: 38px!important; margin-bottom: 5px!important; margin-left: 10px;" type="button" class="btn btn-info btn-print" onclick="imprimirRecibo()"><i class="fa fa-print" aria-hidden="true"></i> Recibo</button>
                <button style="margin-bottom: 5px!important;" type="button" class="btn btn-success btn-payment" data-toggle="modal" data-target="#pagamentoModal"><i class="fa fa-money" aria-hidden="true"></i> Pagamentos</button>
                <button style="width: 100px; height: 38px!important; margin-bottom: 5px!important; margin-left: 10px;" type="button" class="btn btn-edit btn-sm" onclick="editarOS()"><i class="fa fa-pencil" aria-hidden="true"></i> Editar OS</button>
                <button style="width: 120px; height: 38px!important; margin-bottom: 5px!important; margin-left: 10px;" type="button" class="btn btn-secondary btn-sm" onclick="window.location.href='index.php'"><i class="fa fa-search" aria-hidden="true"></i> Pesquisar OS</button>
            </div>
        </div>
        <hr>
        <form id="osForm" method="POST">
            <div class="form-row">
                <div class="form-group col-md-5">
                    <label for="cliente">Cliente:</label>
                    <input type="text" class="form-control" id="cliente" name="cliente" value="<?php echo $ordem_servico['cliente']; ?>" readonly>
                </div>
                <div class="form-group col-md-3">
                    <label for="cpf_cliente">CPF/CNPJ do Cliente:</label>
                    <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente" value="<?php echo $ordem_servico['cpf_cliente']; ?>" readonly>
                </div>
                <div class="form-group col-md-2">
                    <label for="total_os">Base de Cálculo:</label>
                    <input type="text" class="form-control" id="total_os" name="total_os" value="<?php echo 'R$ ' . number_format($ordem_servico['base_de_calculo'], 2, ',', '.'); ?>" readonly>
                </div>
                <div class="form-group col-md-2">
                    <label for="total_os">Valor Total:</label>
                    <input type="text" class="form-control" id="total_os" name="total_os" value="<?php echo 'R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.'); ?>" readonly>
                </div>
                <div class="form-group col-md-3">
                    <label for="deposito_previo">Depósito Prévio:</label>
                    <input type="text" class="form-control" id="deposito_previo" name="deposito_previo" value="<?php echo 'R$ ' . number_format($total_pagamentos, 2, ',', '.'); ?>" readonly>
                </div>
                <?php if ($total_liquidado > 0): ?>
                <div class="form-group col-md-3">
                    <label for="valor_liquidado">Valor Liquidado:</label>
                    <input type="text" class="form-control" id="valor_liquidado" name="valor_liquidado" value="<?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($total_devolucoes > 0): ?>
                <div class="form-group col-md-3">
                    <label for="valor_devolvido">Valor Devolvido:</label>
                    <input type="text" class="form-control" id="valor_devolvido" name="valor_devolvido" value="<?php echo 'R$ ' . number_format($total_devolucoes, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($total_repasses > 0): ?>
                <div class="form-group col-md-3">
                    <label for="total_repasses">Repasse Credor:</label>
                    <input type="text" class="form-control" id="total_repasses" name="total_repasses" value="<?php echo 'R$ ' . number_format($total_repasses, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($saldo > 0): ?>
                <div class="form-group col-md-3">
                    <label for="saldo">Saldo:</label>
                    <input type="text" class="form-control" id="saldo" name="saldo" value="<?php echo 'R$ ' . number_format($saldo, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-row">
                <div class="form-group col-md-10">
                    <label for="descricao_os">Título da OS:</label>
                    <input type="text" class="form-control" id="descricao_os" name="descricao_os" value="<?php echo $ordem_servico['descricao_os']; ?>" readonly>
                </div>
                <div class="form-group col-md-2">
                    <label for="descricao_os">Data da OS:</label>
                    <input type="text" class="form-control" id="descricao_os" name="descricao_os" value="<?php echo date('d/m/Y', strtotime($ordem_servico['data_criacao'])); ?>" readonly>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="observacoes">Observações:</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="4" readonly><?php echo $ordem_servico['observacoes']; ?></textarea>
                </div>
            </div>
        </form>
        <div id="osItens" class="mt-4">
            <h4>Itens da Ordem de Serviço</h4>
            <table class="table" style="padding: 0.50rem!important; zoom: 90%">
                <thead>
                    <tr>
                        <th>Ato</th>
                        <th>Qtd</th>
                        <th>Desconto Legal (%)</th>
                        <th>Descrição</th>
                        <th>Emolumentos</th>
                        <th>FERC</th>
                        <th>FADEP</th>
                        <th>FEMP</th>
                        <th>Total</th>
                        <th>Qtd Liquidada</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="itensTable">
                    <?php foreach ($ordem_servico_itens as $item): ?>
                    <tr>
                        <td><?php echo $item['ato']; ?></td>
                        <td><?php echo $item['quantidade']; ?></td>
                        <td><?php echo $item['desconto_legal']; ?></td>
                        <td><?php echo $item['descricao']; ?></td>
                        <td><?php echo number_format($item['emolumentos'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['ferc'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['fadep'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['femp'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($item['total'], 2, ',', '.'); ?></td>
                        <td><?php echo $item['quantidade_liquidada']; ?></td>
                        <td>
                            <?php if ($item['status'] == 'liquidado'): ?>
                                <span class="status-label status-liquidado">Liquidado</span>
                            <?php elseif ($item['status'] == 'parcialmente liquidado'): ?>
                                <span class="status-label status-parcial">Liq. Parcialmente</span>
                            <?php else: ?>
                                <span class="status-label status-pendente">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($item['status'] != 'liquidado'): ?>
                                <button type="button" class="btn btn-primary btn-sm" onclick="liquidarAto(<?php echo $item['id']; ?>, <?php echo $item['quantidade']; ?>, <?php echo $item['quantidade_liquidada'] !== null ? $item['quantidade_liquidada'] : 0; ?>, '<?php echo $item['status'] !== null ? addslashes($item['status']) : ''; ?>')">Liquidar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
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
                    <input type="text" class="form-control" id="total_os_modal" value="<?php echo 'R$ ' . number_format($ordem_servico['total_os'], 2, ',', '.'); ?>" readonly>
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
                <button type="button" style="width: 100%" class="btn btn-primary" onclick="adicionarPagamento()">Adicionar</button>
                <hr>
                <div id="pagamentosAdicionados">
                    <h5>Pagamentos Adicionados</h5>
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Forma de Pagamento</th>
                                <th>Valor</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="pagamentosTable">
                            <!-- Pagamentos adicionados serão listados aqui -->
                            <?php foreach ($pagamentos as $pagamento): ?>
                            <tr>
                                <td><?php echo $pagamento['forma_de_pagamento']; ?></td>
                                <td><?php echo 'R$ ' . number_format($pagamento['total_pagamento'], 2, ',', '.'); ?></td>
                                <td><button type="button" title="Remover" class="btn btn-delete btn-sm" onclick="confirmarRemocaoPagamento(<?php echo $pagamento['id']; ?>)" <?php echo $has_liquidated ? 'disabled' : ''; ?>><i class="fa fa-trash" aria-hidden="true"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pagamentos > 0): ?>
                <div class="form-group">
                    <label for="total_pagamento">Valor Pago</label>
                    <input type="text" class="form-control" id="total_pagamento_modal" value="<?php echo 'R$ ' . number_format($total_pagamentos, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($total_liquidado > 0): ?>
                <div class="form-group">
                    <label for="valor_liquidado_modal">Valor Liquidado</label>
                    <input type="text" class="form-control" id="valor_liquidado_modal" value="<?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($saldo > 0): ?>
                <div class="form-group">
                    <label for="saldo_modal">Saldo</label>
                    <input type="text" class="form-control" id="saldo_modal" value="<?php echo 'R$ ' . number_format($saldo, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($total_devolucoes > 0): ?>
                <div class="form-group">
                    <label for="valor_devolvido_modal">Valor Devolvido</label>
                    <input type="text" class="form-control" id="valor_devolvido_modal" value="<?php echo 'R$ ' . number_format($total_devolucoes, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($total_repasses > 0): ?>
                <div class="form-group">
                    <label for="total_repasses_modal">Repasse Credor</label>
                    <input type="text" class="form-control" id="total_repasses_modal" value="<?php echo 'R$ ' . number_format($total_repasses, 2, ',', '.'); ?>" readonly>
                </div>
                <?php endif; ?>
                <?php if ($saldo > 0.01 && $has_ato_17): ?>
                <button type="button" class="btn btn-warning btn-repasse" onclick="abrirRepasseModal()">Repasse Credor</button>
                <?php endif; ?>
                <?php if ($saldo > 0.01): ?>
                <button type="button" class="btn btn-warning" onclick="abrirDevolucaoModal()">Devolver valores</button>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Repasse -->
<div class="modal fade" id="repasseModal" tabindex="-1" role="dialog" aria-labelledby="repasseModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="repasseModalLabel">Repasse Credor</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="forma_repasse">Forma de Repasse</label>
                    <select class="form-control" id="forma_repasse">
                        <option value="">Selecione</option>
                        <option value="Espécie">Espécie</option>
                        <option value="PIX">PIX</option>
                        <option value="Transferência Bancária">Transferência Bancária</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="valor_repasse">Valor do Repasse</label>
                    <input type="text" class="form-control" id="valor_repasse" placeholder="0,00">
                </div>
                <button type="button" class="btn btn-primary" onclick="salvarRepasse()">Salvar</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Liquidação -->
<div class="modal fade" id="liquidacaoModal" tabindex="-1" role="dialog" aria-labelledby="liquidacaoModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="liquidacaoModalLabel">Liquidar Ato</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="quantidade_liquidar">Quantidade a Liquidar</label>
                    <input type="number" class="form-control" id="quantidade_liquidar" min="1">
                </div>
                <button type="button" class="btn btn-primary" onclick="confirmarLiquidacao()">Confirmar</button>
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
                    <input type="text" class="form-control" id="valor_devolucao" placeholder="0,00">
                </div>
                <button type="button" class="btn btn-primary" onclick="salvarDevolucao()">Salvar</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
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

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script>
    var pagamentos = <?php echo json_encode($pagamentos); ?>;
    var liquidacaoItemId = null;
    var quantidadeTotal = 0;
    var quantidadeLiquidada = 0;

    $(document).ready(function() {
        $('#valor_pagamento').mask('#.##0,00', { reverse: true });
        $('#valor_devolucao').mask('#.##0,00', { reverse: true });
        $('#valor_repasse').mask('#.##0,00', { reverse: true });

        atualizarTabelaPagamentos(); // Chamada para exibir os pagamentos existentes ao carregar a página
    });

    function imprimirOS() {
        window.open('imprimir-os.php?id=<?php echo $os_id; ?>', '_blank');
    }

    function imprimirRecibo() {
        window.open('recibo.php?id=<?php echo $os_id; ?>', '_blank');
    }

    function editarOS() {
        window.location.href = 'editar_os.php?id=<?php echo $os_id; ?>';
    }

    // Função para adicionar pagamento
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
                os_id: <?php echo $os_id; ?>,
                cliente: '<?php echo $ordem_servico['cliente']; ?>',
                total_os: <?php echo $ordem_servico['total_os']; ?>,
                funcionario: '<?php echo $_SESSION['username']; ?>',
                forma_pagamento: formaPagamento,
                valor_pagamento: valorPagamento
            },
            success: function(response) {
                response = JSON.parse(response);
                if (response.success) {
                    pagamentos.push({ forma_de_pagamento: formaPagamento, total_pagamento: valorPagamento });
                    atualizarTabelaPagamentos();
                    $('#valor_pagamento').val('');
                    exibirMensagem('Pagamento adicionado com sucesso!', 'success');
                } else {
                    exibirMensagem('Erro ao adicionar pagamento.', 'error');
                }
            },
            error: function() {
                exibirMensagem('Erro ao adicionar pagamento.', 'error');
            }
        });
    }

    // Função para atualizar a tabela de pagamentos
    function atualizarTabelaPagamentos() {
        var pagamentosTable = $('#pagamentosTable');
        pagamentosTable.empty();

        var total = 0;

        pagamentos.forEach(function(pagamento, index) {
            total += parseFloat(pagamento.total_pagamento);

            pagamentosTable.append(`
                <tr>
                    <td>${pagamento.forma_de_pagamento}</td>
                    <td>R$ ${parseFloat(pagamento.total_pagamento).toFixed(2).replace('.', ',')}</td>
                    <td><button type="button" title="Remover" class="btn btn-delete btn-sm" onclick="confirmarRemocaoPagamento(${pagamento.id})" <?php echo $has_liquidated ? 'disabled' : ''; ?>><i class="fa fa-trash" aria-hidden="true"></i></button></td>
                </tr>
            `);
        });

        $('#total_pagamento').val('R$ ' + total.toFixed(2).replace('.', ','));
    }

    // Função para remover pagamento
    function confirmarRemocaoPagamento(pagamentoId) {
        if (<?php echo $has_liquidated ? 'true' : 'false'; ?>) {
            exibirMensagem('Não é possível remover pagamentos após a liquidação de atos.', 'error');
            return;
        }

        if (confirm('Tem certeza de que deseja remover este pagamento?')) {
            $.ajax({
                url: 'remover_pagamento.php',
                type: 'POST',
                data: {
                    pagamento_id: pagamentoId
                },
                success: function(response) {
                    response = JSON.parse(response);
                    if (response.success) {
                        pagamentos = pagamentos.filter(pagamento => pagamento.id !== pagamentoId);
                        atualizarTabelaPagamentos();
                        exibirMensagem('Pagamento removido com sucesso!', 'success');
                    } else {
                        exibirMensagem('Erro ao remover pagamento.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao remover pagamento.', 'error');
                }
            });
        }
    }

    // Função para liquidar ato
    function liquidarAto(itemId, quantidade, quantidadeLiquidada, status) {
        liquidacaoItemId = itemId;
        quantidadeTotal = quantidade;
        quantidadeLiquidada = quantidadeLiquidada;

        var quantidadeRestante = quantidadeTotal - quantidadeLiquidada;
        $('#quantidade_liquidar').val(quantidadeRestante);

        if (quantidadeRestante === 1) {
            $('#quantidade_liquidar').prop('readonly', true);
        } else {
            $('#quantidade_liquidar').prop('readonly', false);
        }

        $('#liquidacaoModal').modal('show');
    }

    // Função para confirmar liquidação
    function confirmarLiquidacao() {
        var quantidadeALiquidar = parseInt($('#quantidade_liquidar').val());

        if (quantidadeALiquidar <= 0 || (quantidadeLiquidada + quantidadeALiquidar) > quantidadeTotal) {
            exibirMensagem('Quantidade inválida para liquidação.', 'error');
            return;
        }

        $.ajax({
            url: 'liquidar_ato.php',
            type: 'POST',
            data: {
                item_id: liquidacaoItemId,
                quantidade_liquidar: quantidadeALiquidar
            },
            success: function(response) {
                console.log(response); // Adiciona log para verificar a resposta
                try {
                    response = JSON.parse(response);
                    if (response.success) {
                        $('#liquidacaoModal').modal('hide');
                        window.location.reload();
                    } else {
                        exibirMensagem(response.error || 'Erro ao liquidar ato.', 'error');
                    }
                } catch (e) {
                    console.error('Erro ao analisar resposta JSON:', e);
                    exibirMensagem('Erro ao analisar resposta do servidor.', 'error');
                }
            },
            error: function(xhr, status, error) {
                exibirMensagem('Erro ao liquidar ato: ' + error, 'error');
            }
        });
    }

    // Função para abrir modal de devolução
    function abrirDevolucaoModal() {
        $('#devolucaoModal').modal('show');
    }

    // Função para salvar devolução
    function salvarDevolucao() {
        var formaDevolucao = $('#forma_devolucao').val();
        var valorDevolucao = parseFloat($('#valor_devolucao').val().replace('.', '').replace(',', '.'));
        var valorPago = parseFloat('<?php echo $valor_pago_liquido; ?>');
        var valorMaximoDevolucao = valorPago - parseFloat('<?php echo $total_liquidado; ?>');

        if (formaDevolucao === "") {
            exibirMensagem('Por favor, selecione uma forma de devolução.', 'error');
            return;
        }

        if (isNaN(valorDevolucao) || valorDevolucao <= 0 || valorDevolucao > valorMaximoDevolucao + 0.01) {
            exibirMensagem('Por favor, insira um valor válido para a devolução que não seja maior que o saldo disponível.', 'error');
            return;
        }

        var osId = <?php echo $os_id; ?>;
        var cliente = '<?php echo $ordem_servico['cliente']; ?>';
        var totalOs = '<?php echo $ordem_servico['total_os']; ?>';
        var funcionario = '<?php echo $_SESSION['username']; ?>';

        $.ajax({
            url: 'salvar_devolucao.php',
            type: 'POST',
            data: {
                os_id: osId,
                cliente: cliente,
                total_os: totalOs,
                total_devolucao: valorDevolucao,
                forma_devolucao: formaDevolucao,
                funcionario: funcionario
            },
            success: function(response) {
                alert('Devolução salva com sucesso!');
                $('#devolucaoModal').modal('hide');
                window.location.reload();
            },
            error: function(xhr, status, error) {
                exibirMensagem('Erro ao salvar devolução: ' + error, 'error');
            }
        });
    }

    // Função para abrir modal de repasse
    function abrirRepasseModal() {
        $('#repasseModal').modal('show');
    }

    // Função para salvar repasse
    function salvarRepasse() {
        var formaRepasse = $('#forma_repasse').val();
        var valorRepasse = parseFloat($('#valor_repasse').val().replace('.', '').replace(',', '.'));
        var saldoAtual = parseFloat('<?php echo $saldo; ?>');

        if (formaRepasse === "") {
            exibirMensagem('Por favor, selecione uma forma de repasse.', 'error');
            return;
        }

        if (isNaN(valorRepasse) || valorRepasse <= 0 || valorRepasse > saldoAtual + 0.01) {
            exibirMensagem('Por favor, insira um valor válido para o repasse que não seja maior que o saldo disponível.', 'error');
            return;
        }

        var osId = <?php echo $os_id; ?>;
        var cliente = '<?php echo $ordem_servico['cliente']; ?>';
        var totalOs = '<?php echo $ordem_servico['total_os']; ?>';
        var dataOs = '<?php echo $ordem_servico['data_criacao']; ?>';
        var funcionario = '<?php echo $_SESSION['username']; ?>';

        $.ajax({
            url: 'salvar_repasse.php',
            type: 'POST',
            data: {
                os_id: osId,
                cliente: cliente,
                total_os: totalOs,
                total_repasse: valorRepasse,
                forma_repasse: formaRepasse,
                data_os: dataOs,
                funcionario: funcionario
            },
            success: function(response) {
                console.log("Server response:", response); // Adicione esta linha para verificar a resposta do servidor
                try {
                    // Verifique se a resposta já é um objeto, se for, não tente analisá-la
                    if (typeof response === 'object') {
                        processarResposta(response, saldoAtual, valorRepasse);
                    } else {
                        response = JSON.parse(response);
                        processarResposta(response, saldoAtual, valorRepasse);
                    }
                } catch (e) {
                    console.error('Erro ao analisar resposta JSON:', e);
                    exibirMensagem('Erro ao processar a resposta do servidor: ' + e.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX:', error); // Adicione esta linha para logar erros de AJAX
                exibirMensagem('Erro ao salvar repasse: ' + error, 'error');
            }
        });
    }

    function processarResposta(response, saldoAtual, valorRepasse) {
        if (response.success) {
            alert('Repasse salvo com sucesso!');
            $('#repasseModal').modal('hide');
            // Recalcular o saldo após o repasse
            var novoSaldo = saldoAtual - valorRepasse;
            $('#saldo').val('R$ ' + novoSaldo.toFixed(2).replace('.', ','));
            window.location.reload();
        } else {
            exibirMensagem('Erro ao salvar repasse: ' + response.error, 'error');
        }
    }


    // Função para exibir mensagem
    function exibirMensagem(mensagem, tipo) {
        var modalHeader = $('#mensagemModal .modal-header');
        var mensagemTexto = $('#mensagemTexto');

        if (tipo === 'success') {
            modalHeader.removeClass('error').addClass('success');
            $('#mensagemModalLabel').text('Sucesso');
        } else if (tipo === 'error') {
            modalHeader.removeClass('success').addClass('error');
            $('#mensagemModalLabel').text('Erro');
        }

        mensagemTexto.text(mensagem);
        $('#mensagemModal').modal('show');
    }

    // Adicionar evento para recarregar a página ao fechar os modais
    $('#pagamentoModal').on('hidden.bs.modal', function () {
        location.reload();
    });
</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
