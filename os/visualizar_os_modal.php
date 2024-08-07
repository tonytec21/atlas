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
foreach ($ordem_servico_itens as $item) {
    if ($item['status'] == 'liquidado') {
        $has_liquidated = true;
        break;
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

// Calcular valor líquido pago
$valor_pago_liquido = $total_pagamentos - $total_devolucoes;

// Calcular saldo
$saldo = $valor_pago_liquido - $ordem_servico['total_os'];
?>

<div class="d-flex justify-content-between align-items-center">
    <h3>Visualizar Ordem de Serviço</h3>
    <div>
        <button type="button" class="btn btn-primary btn-print" onclick="imprimirOS()"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Imprimir OS</button>
        <button type="button" class="btn btn-success btn-payment" data-toggle="modal" data-target="#pagamentoModal"><i class="fa fa-money" aria-hidden="true"></i> Efetuar pagamento</button>
        <button style="width: 100px; height: 38px!important; margin-bottom: 0px!important; margin-left: 10px;" type="button" class="btn btn-edit btn-sm" onclick="editarOS()"><i class="fa fa-pencil" aria-hidden="true"></i> Editar OS</button>
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
        <div class="form-group col-md-3">
            <label for="valor_liquidado">Valor Liquidado:</label>
            <input type="text" class="form-control" id="valor_liquidado" name="valor_liquidado" value="<?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?>" readonly>
        </div>
        <div class="form-group col-md-3">
            <label for="valor_devolvido">Valor Devolvido:</label>
            <input type="text" class="form-control" id="valor_devolvido" name="valor_devolvido" value="<?php echo 'R$ ' . number_format($total_devolucoes, 2, ',', '.'); ?>" readonly>
        </div>
        <div class="form-group col-md-3">
            <label for="saldo">Saldo:</label>
            <input type="text" class="form-control" id="saldo" name="saldo" value="<?php echo 'R$ ' . number_format($saldo, 2, ',', '.'); ?>" readonly>
        </div>
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
                <button type="button" class="btn btn-primary" onclick="adicionarPagamento()">Adicionar</button>
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
                                <td><button type="button" class="btn btn-danger btn-sm" onclick="removerPagamento(<?php echo $pagamento['id']; ?>)" <?php echo $has_liquidated ? 'disabled' : ''; ?>>Remover</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-group">
                    <label for="total_pagamento">Valor Pago</label>
                    <input type="text" class="form-control" id="valor_liquidado_modal" value="<?php echo 'R$ ' . number_format($total_pagamentos, 2, ',', '.'); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="valor_liquidado_modal">Valor Liquidado</label>
                    <input type="text" class="form-control" id="valor_liquidado_modal" value="<?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="saldo_modal">Saldo</label>
                    <input type="text" class="form-control" id="saldo_modal" value="<?php echo 'R$ ' . number_format($saldo, 2, ',', '.'); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="valor_devolvido_modal">Valor Devolvido</label>
                    <input type="text" class="form-control" id="valor_devolvido_modal" value="<?php echo 'R$ ' . number_format($total_devolucoes, 2, ',', '.'); ?>" readonly>
                </div>
                <?php if ($saldo > 0.1): ?>
                <button type="button" class="btn btn-warning" onclick="abrirDevolucaoModal()">Devolver valores</button>
                <?php endif; ?>
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
                    <input type="text" class="form-control" id="valor_devolucao">
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

<script>
    var pagamentos = <?php echo json_encode($pagamentos); ?>;
    var liquidacaoItemId = null;
    var quantidadeTotal = 0;
    var quantidadeLiquidada = 0;

    $(document).ready(function() {
        $('#valor_pagamento').mask('#.##0,00', { reverse: true });
        $('#valor_devolucao').mask('#.##0,00', { reverse: true });

        atualizarTabelaPagamentos(); // Chamada para exibir os pagamentos existentes ao carregar a página
    });

    function imprimirOS() {
        window.open('gerar_pdf.php?id=<?php echo $os_id; ?>', '_blank');
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
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="removerPagamento(${index})" <?php echo $has_liquidated ? 'disabled' : ''; ?>>Remover</button></td>
                </tr>
            `);
        });

        $('#total_pagamento').val('R$ ' + total.toFixed(2).replace('.', ','));
    }

    // Função para remover pagamento
    function removerPagamento(index) {
        if (<?php echo $has_liquidated ? 'true' : 'false'; ?>) {
            exibirMensagem('Não é possível remover pagamentos após a liquidação de atos.', 'error');
            return;
        }
        
        pagamentos.splice(index, 1);
        atualizarTabelaPagamentos();
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

        if (isNaN(valorDevolucao) || valorDevolucao <= 0 || valorDevolucao > valorMaximoDevolucao) {
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
</script>
