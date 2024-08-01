<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if (!isset($_GET['id'])) {
    die('ID da OS não fornecido');
}

$id = $_GET['id'];
$usuario = $_SESSION['username'];

// Buscar dados da OS e copiar para as tabelas de log
try {
    $conn = getDatabaseConnection();
    
    // Iniciar transação
    $conn->beginTransaction();

    // Buscar dados da OS
    $stmt = $conn->prepare("SELECT * FROM ordens_de_servico WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $os = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$os) {
        die('OS não encontrada');
    }

    // Inserir dados da OS na tabela de logs
    $stmt = $conn->prepare("INSERT INTO logs_ordens_de_servico (ordem_de_servico_id, cliente, cpf_cliente, total_os, descricao_os, observacoes, criado_por, editado_por, data_edicao) VALUES (:ordem_de_servico_id, :cliente, :cpf_cliente, :total_os, :descricao_os, :observacoes, :criado_por, :editado_por, NOW())");
    $stmt->bindParam(':ordem_de_servico_id', $id);
    $stmt->bindParam(':cliente', $os['cliente']);
    $stmt->bindParam(':cpf_cliente', $os['cpf_cliente']);
    $stmt->bindParam(':total_os', $os['total_os']);
    $stmt->bindParam(':descricao_os', $os['descricao_os']);
    $stmt->bindParam(':observacoes', $os['observacoes']);
    $stmt->bindParam(':criado_por', $os['criado_por']);
    $stmt->bindParam(':editado_por', $usuario);
    $stmt->execute();
    
    $log_os_id = $conn->lastInsertId();

    // Buscar itens da OS
    $stmt = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Inserir itens da OS na tabela de logs
    $stmt = $conn->prepare("INSERT INTO logs_ordens_de_servico_itens (ordem_servico_id, ato, quantidade, desconto_legal, descricao, emolumentos, ferc, fadep, femp, total, quantidade_liquidada, status) VALUES (:ordem_servico_id, :ato, :quantidade, :desconto_legal, :descricao, :emolumentos, :ferc, :fadep, :femp, :total, :quantidade_liquidada, :status)");
    
    foreach ($itens as $item) {
        $stmt->bindParam(':ordem_servico_id', $log_os_id);
        $stmt->bindParam(':ato', $item['ato']);
        $stmt->bindParam(':quantidade', $item['quantidade']);
        $stmt->bindParam(':desconto_legal', $item['desconto_legal']);
        $stmt->bindParam(':descricao', $item['descricao']);
        $stmt->bindParam(':emolumentos', $item['emolumentos']);
        $stmt->bindParam(':ferc', $item['ferc']);
        $stmt->bindParam(':fadep', $item['fadep']);
        $stmt->bindParam(':femp', $item['femp']);
        $stmt->bindParam(':total', $item['total']);
        $stmt->bindParam(':quantidade_liquidada', $item['quantidade_liquidada']);
        $stmt->bindParam(':status', $item['status']);
        $stmt->execute();
    }

    // Confirmar transação
    $conn->commit();
} catch (PDOException $e) {
    // Reverter transação em caso de erro
    $conn->rollBack();
    die('Erro ao buscar dados da OS: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Ordem de Serviço</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <style>
        .btn-adicionar-manual {
            height: 38px; /* mesma altura do botão Buscar Ato */
            line-height: 24px; /* para alinhar o texto verticalmente */
            margin-left: 10px; /* espaço entre os botões */
        }
        .btn-adicionar-iss {
            background-color: #28a745; /* cor verde para o botão Adicionar ISS */
            border-color: #28a745;
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
    </style>
</head>
<body>
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Editar Ordem de Serviço</h3>
        <hr>
        <form id="osForm" method="POST">
            <input type="hidden" id="os_id" name="os_id" value="<?php echo $id; ?>">
            <div class="form-row">
                <div class="form-group col-md-5">
                    <label for="cliente">Cliente:</label>
                    <input type="text" class="form-control" id="cliente" name="cliente" value="<?php echo $os['cliente']; ?>" required>
                </div>
                <div class="form-group col-md-2">
                    <label for="cpf_cliente">CPF/CNPJ do Cliente:</label>
                    <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente" value="<?php echo $os['cpf_cliente']; ?>">
                </div>
                <div class="form-group col-md-2">
                    <label for="base_calculo">Base de Cálculo:</label>
                    <input type="text" class="form-control" id="base_calculo" name="base_calculo" value="<?php echo number_format($os['base_de_calculo'], 2, ',', '.'); ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="total_os">Total OS:</label>
                    <input type="text" class="form-control" id="total_os" name="total_os" value="<?php echo number_format($os['total_os'], 2, ',', '.'); ?>" readonly>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="descricao_os">Título da OS:</label>
                    <input type="text" class="form-control" id="descricao_os" name="descricao_os" value="<?php echo $os['descricao_os']; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="ato">Código do Ato:</label>
                    <input type="text" class="form-control" id="ato" name="ato" required pattern="[0-9.]+">
                </div>
                <div class="form-group col-md-2">
                    <label for="quantidade">Quantidade:</label>
                    <input type="number" class="form-control" id="quantidade" name="quantidade" value="1" required min="1">
                </div>
                <div class="form-group col-md-2">
                    <label for="desconto_legal">Desconto Legal (%):</label>
                    <input type="number" class="form-control" id="desconto_legal" name="desconto_legal" value="0" required min="0" max="100">
                </div>
                <div class="form-group col-md-5" style="display: flex; align-items: center; margin-top: 32px;">
                    <button type="button" style="width: 35%" class="btn btn-primary" onclick="buscarAto()"><i class="fa fa-search" aria-hidden="true"></i> Buscar Ato</button>
                    <button type="button" style="width: 65%" class="btn btn-secondary btn-adicionar-manual" onclick="adicionarAtoManual()"><i class="fa fa-i-cursor" aria-hidden="true"></i> Adicionar Ato Manualmente</button>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="descricao">Descrição:</label>
                    <input type="text" class="form-control" id="descricao" name="descricao" readonly>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-2">
                    <label for="emolumentos">Emolumentos:</label>
                    <input type="text" class="form-control" id="emolumentos" name="emolumentos" readonly>
                </div>
                <div class="form-group col-md-2">
                    <label for="ferc">FERC:</label>
                    <input type="text" class="form-control" id="ferc" name="ferc" readonly>
                </div>
                <div class="form-group col-md-2">
                    <label for="fadep">FADEP:</label>
                    <input type="text" class="form-control" id="fadep" name="fadep" readonly>
                </div>
                <div class="form-group col-md-2">
                    <label for="femp">FEMP:</label>
                    <input type="text" class="form-control" id="femp" name="femp" readonly>
                </div>
                <div class="form-group col-md-2">
                    <label for="total">Total:</label>
                    <input type="text" class="form-control" id="total" name="total" readonly>
                </div>
                <div class="form-group col-md-2" style="margin-top: 32px;">
                    <button type="button" style="width: 100%" class="btn btn-success" onclick="adicionarItem()"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar à OS</button>
                </div>
            </div>
        </form>
        <div id="osItens" class="mt-4">
            <h4>Itens da Ordem de Serviço</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Ato</th>
                        <th>Quantidade</th>
                        <th>Desconto Legal (%)</th>
                        <th>Descrição</th>
                        <th>Emolumentos</th>
                        <th>FERC</th>
                        <th>FADEP</th>
                        <th>FEMP</th>
                        <th>Total</th>
                        <th>Qtd Liquidada</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="itensTable">
                    <!-- Itens existentes -->
                    <?php foreach ($itens as $item): ?>
                        <tr data-item-id="<?php echo $item['id']; ?>" data-quantidade-liquidada="<?php echo $item['quantidade_liquidada']; ?>" data-status="<?php echo $item['status']; ?>">
                            <td><?php echo $item['ato']; ?></td>
                            <td><?php echo $item['quantidade']; ?></td>
                            <td><?php echo $item['desconto_legal']; ?>%</td>
                            <td><?php echo $item['descricao']; ?></td>
                            <td><?php echo number_format($item['emolumentos'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['ferc'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['fadep'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['femp'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['total'], 2, ',', '.'); ?></td>
                            <td><?php echo $item['quantidade_liquidada']; ?></td>
                            <td>
                            <?php if ($item['status'] === 'liquidado'): ?>
                                <!-- Se o item estiver liquidado, nenhum botão será mostrado -->
                            <?php elseif ($item['status'] === 'liquidado parcialmente'): ?>
                                <button type="button" class="btn btn-edit btn-sm" onclick="alterarQuantidade(this)"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                            <?php else: ?>
                                <button type="button" class="btn btn-edit btn-sm" onclick="alterarQuantidade(this)"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                <?php if ($item['status'] === null): ?>
                                    <button type="button" class="btn btn-delete btn-sm" onclick="removerItem(this)"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                <?php endif; ?>
                            <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" style="width: 100%;" class="btn btn btn-secondary btn-block" onclick="adicionarISS()"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar ISS</button>
        <hr>
        <div class="form-group">
            <label for="observacoes">Observações:</label>
            <textarea class="form-control" id="observacoes" name="observacoes" rows="4"><?php echo $os['observacoes']; ?></textarea>
        </div>
        <button type="button" class="btn btn-primary btn-block" onclick="salvarOS()"><i class="fa fa-floppy-o" aria-hidden="true"></i> SALVAR OS</button>
    </div>
</div>

<!-- Modal para alterar quantidade -->
<div class="modal fade" id="alterarQuantidadeModal" tabindex="-1" role="dialog" aria-labelledby="alterarQuantidadeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="alterarQuantidadeModalLabel">Alterar Quantidade</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="alterarQuantidadeForm">
                    <div class="form-group">
                        <label for="novaQuantidade">Nova Quantidade:</label>
                        <input type="number" class="form-control" id="novaQuantidade" name="novaQuantidade" min="1" required>
                        <input type="hidden" id="quantidadeLiquidada" name="quantidadeLiquidada">
                        <input type="hidden" id="statusItem" name="statusItem">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="salvarNovaQuantidade()">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para alerta -->
<div class="modal fade" id="alertModal" tabindex="-1" role="dialog" aria-labelledby="alertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" id="alertModalHeader">
                <h5 class="modal-title" id="alertModalLabel">Alerta</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="alertModalBody">
                <!-- Alerta vai aqui -->
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
// Função para exibir modal de alerta
function showAlert(message, type, reload = false) {
    $('#alertModalBody').text(message);
    if (type === 'error') {
        $('#alertModalHeader').removeClass('success').addClass('error');
    } else if (type === 'success') {
        $('#alertModalHeader').removeClass('error').addClass('success');
    }
    $('#alertModal').modal('show');

    if (reload) {
        $('#alertModal').on('hidden.bs.modal', function () {
            location.reload();
        });
    }
}

$(document).ready(function() {
    // Máscaras e configurações iniciais
    $('#cpf_cliente').on('blur', function() {
        var cpfCnpj = $(this).val().replace(/\D/g, '');
        if (cpfCnpj.length === 11) {
            $(this).mask('000.000.000-00', {reverse: true});
        } else if (cpfCnpj.length === 14) {
            $(this).mask('00.000.000/0000-00', {reverse: true});
        }
    }).blur(); // Chamar a função quando o campo perde o foco

    $('#base_calculo, #emolumentos, #ferc, #fadep, #femp, #total').mask('#.##0,00', {reverse: true});
});

function buscarAtoPorQuantidade(ato, quantidade, descontoLegal, callback) {
    $.ajax({
        url: 'buscar_ato.php',
        type: 'GET',
        data: { ato: ato },
        success: function(response) {
            if (response.error) {
                showAlert(response.error, 'error');
            } else {
                var emolumentos = response.EMOLUMENTOS * quantidade;
                var ferc = response.FERC * quantidade;
                var fadep = response.FADEP * quantidade;
                var femp = response.FEMP * quantidade;

                var desconto = descontoLegal / 100;
                emolumentos = emolumentos * (1 - desconto);
                ferc = ferc * (1 - desconto);
                fadep = fadep * (1 - desconto);
                femp = femp * (1 - desconto);

                callback({
                    descricao: response.DESCRICAO,
                    emolumentos: emolumentos.toFixed(2).replace('.', ','),
                    ferc: ferc.toFixed(2).replace('.', ','),
                    fadep: fadep.toFixed(2).replace('.', ','),
                    femp: femp.toFixed(2).replace('.', ','),
                    total: (emolumentos + ferc + fadep + femp).toFixed(2).replace('.', ',')
                });
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao buscar o ato', 'error');
        }
    });
}

function buscarAto() {
    var ato = $('#ato').val();
    var quantidade = $('#quantidade').val();
    var descontoLegal = $('#desconto_legal').val();

    buscarAtoPorQuantidade(ato, quantidade, descontoLegal, function(values) {
        $('#descricao').val(values.descricao);
        $('#emolumentos').val(values.emolumentos);
        $('#ferc').val(values.ferc);
        $('#fadep').val(values.fadep);
        $('#femp').val(values.femp);
        $('#total').val(values.total);
    });
}

function adicionarISS() {
    var totalEmolumentos = 0;
    $('#itensTable tr').each(function() {
        var emolumentos = parseFloat($(this).find('td').eq(4).text().replace(/\./g, '').replace(',', '.')) || 0;
        totalEmolumentos += emolumentos;
    });

    var baseISS = totalEmolumentos * 0.88; 
    var valorISS = baseISS * 0.05;

    var os_id = $('#os_id').val();
    var ato = 'ISS';
    var quantidade = 1;
    var desconto_legal = 0;
    var descricao = 'ISS sobre Emolumentos';
    var emolumentos = valorISS;
    var ferc = 0;
    var fadep = 0;
    var femp = 0;
    var total = valorISS;

    $.ajax({
        url: 'adicionar_item.php',
        type: 'POST',
        data: {
            os_id: os_id,
            ato: ato,
            quantidade: quantidade,
            desconto_legal: desconto_legal,
            descricao: descricao,
            emolumentos: emolumentos,
            ferc: ferc,
            fadep: fadep,
            femp: femp,
            total: total
        },
        success: function(response) {
            try {
                var res = JSON.parse(response);
                if (res.error) {
                    showAlert(res.error, 'error');
                } else {
                    var item = '<tr>' +
                        '<td>' + ato + '</td>' +
                        '<td>' + quantidade + '</td>' +
                        '<td>' + desconto_legal + '%</td>' +
                        '<td>' + descricao + '</td>' +
                        '<td>' + valorISS.toFixed(2).replace('.', ',') + '</td>' +
                        '<td>0,00</td>' +
                        '<td>0,00</td>' +
                        '<td>0,00</td>' +
                        '<td>' + valorISS.toFixed(2).replace('.', ',') + '</td>' +
                        '<td>0</td>' +
                        '<td><button type="button" class="btn btn-delete btn-sm" onclick="removerItem(this)"><i class="fa fa-trash-o" aria-hidden="true"></i></button></td>' +
                        '</tr>';

                    $('#itensTable').append(item);

                    var totalOS = parseFloat($('#total_os').val().replace(/\./g, '').replace(',', '.')) || 0;
                    totalOS += valorISS;
                    $('#total_os').val(totalOS.toFixed(2).replace('.', ','));
                    showAlert('ISS adicionado com sucesso!', 'success', true);  // Adicionar a opção de recarregar a página
                }
            } catch (e) {
                console.log('Erro ao processar a resposta: ', e);
                showAlert('Erro ao processar a resposta do servidor.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao adicionar o ISS', 'error');
        }
    });
}

function adicionarAtoManual() {
    $('#descricao').prop('readonly', false);
    $('#emolumentos').prop('readonly', false);
    $('#ferc').prop('readonly', false);
    $('#fadep').prop('readonly', false);
    $('#femp').prop('readonly', false);
    $('#total').prop('readonly', false);
}

function adicionarItem() {
    var os_id = $('#os_id').val();
    var ato = $('#ato').val();
    var quantidade = $('#quantidade').val();
    var descontoLegal = $('#desconto_legal').val();
    var descricao = $('#descricao').val();
    var emolumentos = parseFloat($('#emolumentos').val().replace(/\./g, '').replace(',', '.')) || 0;
    var ferc = parseFloat($('#ferc').val().replace(/\./g, '').replace(',', '.')) || 0;
    var fadep = parseFloat($('#fadep').val().replace(/\./g, '').replace(',', '.')) || 0;
    var femp = parseFloat($('#femp').val().replace(/\./g, '').replace(',', '.')) || 0;
    var total = parseFloat($('#total').val().replace(/\./g, '').replace(',', '.')) || 0;

    if (isNaN(total) || total <= 0) {
        showAlert("Por favor, clique em 'Buscar Ato' antes de adicionar à OS.", 'error');
        return;
    }

    $.ajax({
        url: 'adicionar_item.php',
        type: 'POST',
        data: {
            os_id: os_id,
            ato: ato,
            quantidade: quantidade,
            desconto_legal: descontoLegal,
            descricao: descricao,
            emolumentos: emolumentos,
            ferc: ferc,
            fadep: fadep,
            femp: femp,
            total: total
        },
        success: function(response) {
            try {
                var res = JSON.parse(response);
                if (res.error) {
                    showAlert(res.error, 'error');
                } else {
                    adicionarItemAosItensTable(ato, quantidade, descontoLegal, descricao, emolumentos.toFixed(2).replace('.', ','), ferc.toFixed(2).replace('.', ','), fadep.toFixed(2).replace('.', ','), femp.toFixed(2).replace('.', ','), total.toFixed(2).replace('.', ','));
                    atualizarTotalOS(os_id); // Atualizar o total OS
                    showAlert('Item adicionado com sucesso!', 'success', true);
                }
            } catch (e) {
                console.log('Erro ao processar a resposta: ', e);
                showAlert('Erro ao processar a resposta do servidor.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao adicionar o item', 'error');
        }
    });
}

function alterarQuantidade(button) {
    var row = $(button).closest('tr');
    var quantidadeLiquidada = parseInt(row.data('quantidade-liquidada'));
    var status = row.data('status');

    $('#novaQuantidade').val(row.find('td').eq(1).text());
    $('#quantidadeLiquidada').val(quantidadeLiquidada);
    $('#statusItem').val(status);
    $('#alterarQuantidadeModal').modal('show');
    $('#alterarQuantidadeForm').data('row', row);
}

function salvarNovaQuantidade() {
    var novaQuantidade = parseInt($('#novaQuantidade').val());
    var quantidadeLiquidada = parseInt($('#quantidadeLiquidada').val());
    var statusItem = $('#statusItem').val();

    if (statusItem === 'liquidado') {
        showAlert('Não é permitido alterar a quantidade de um ato liquidado.', 'error');
        return;
    }

    if (novaQuantidade < quantidadeLiquidada) {
        showAlert('A nova quantidade não pode ser menor que a quantidade liquidada.', 'error');
        return;
    }

    var row = $('#alterarQuantidadeForm').data('row');
    var item_id = row.data('item-id');
    var ato = row.find('td').eq(0).text();
    var descontoLegal = parseFloat(row.find('td').eq(2).text().replace('%', ''));

    buscarAtoPorQuantidade(ato, novaQuantidade, descontoLegal, function(values) {
        $.ajax({
            url: 'atualizar_quantidade_item.php',
            type: 'POST',
            data: {
                item_id: item_id,
                quantidade: novaQuantidade,
                emolumentos: parseFloat(values.emolumentos.replace(',', '.')),
                ferc: parseFloat(values.ferc.replace(',', '.')),
                fadep: parseFloat(values.fadep.replace(',', '.')),
                femp: parseFloat(values.femp.replace(',', '.')),
                total: parseFloat(values.total.replace(',', '.'))
            },
            success: function(response) {
                try {
                    var res = JSON.parse(response);
                    if (res.error) {
                        showAlert(res.error, 'error');
                    } else {
                        row.find('td').eq(1).text(novaQuantidade);
                        row.find('td').eq(3).text(values.descricao);
                        row.find('td').eq(4).text(values.emolumentos);
                        row.find('td').eq(5).text(values.ferc);
                        row.find('td').eq(6).text(values.fadep);
                        row.find('td').eq(7).text(values.femp);
                        row.find('td').eq(8).text(values.total);

                        if (novaQuantidade === quantidadeLiquidada && statusItem === 'liquidado parcialmente') {
                            row.data('status', 'liquidado');
                            row.find('td').eq(10).text('liquidado');
                            $.ajax({
                                url: 'atualizar_status_item.php',
                                type: 'POST',
                                data: {
                                    item_id: row.data('item-id'),
                                    status: 'liquidado'
                                },
                                success: function(response) {
                                    try {
                                        var res = JSON.parse(response);
                                        if (res.error) {
                                            showAlert(res.error, 'error');
                                        }
                                    } catch (e) {
                                        console.log('Erro ao processar a resposta: ', e);
                                        showAlert('Erro ao processar a resposta do servidor.', 'error');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.log('Erro:', error);
                                    console.log('Resposta do servidor:', xhr.responseText);
                                    showAlert('Erro ao atualizar o status do item', 'error');
                                }
                            });
                        }

                        calcularTotalOS();
                        $('#alterarQuantidadeModal').modal('hide');
                        showAlert('Quantidade atualizada com sucesso!', 'success');
                    }
                } catch (e) {
                    console.log('Erro ao processar a resposta: ', e);
                    showAlert('Erro ao processar a resposta do servidor.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('Erro:', error);
                console.log('Resposta do servidor:', xhr.responseText);
                showAlert('Erro ao atualizar a quantidade do item', 'error');
            }
        });
    });
}

function removerItem(button) {
    var row = $(button).closest('tr');
    var itemId = row.data('item-id');
    var status = row.data('status');

    if (status === 'liquidado parcialmente') {
        showAlert('Não é permitido remover um item parcialmente liquidado.', 'error');
        return;
    }

    if (confirm('Você tem certeza que deseja remover este item?')) {
        $.ajax({
            url: 'remover_item.php',
            type: 'POST',
            data: {
                item_id: itemId
            },
            success: function(response) {
                try {
                    var res = JSON.parse(response);
                    if (res.error) {
                        showAlert(res.error, 'error');
                    } else {
                        showAlert('Item removido com sucesso!', 'success');
                        row.remove();
                        calcularTotalOS();
                    }
                } catch (e) {
                    console.log('Erro ao processar a resposta: ', e);
                    showAlert('Erro ao processar a resposta do servidor.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('Erro:', error);
                console.log('Resposta do servidor:', xhr.responseText);
                showAlert('Erro ao remover o item', 'error');
            }
        });
    }
}

function calcularTotalOS() {
    var totalOS = 0;
    $('#itensTable tr').each(function() {
        var total = parseFloat($(this).find('td').eq(8).text().replace(/\./g, '').replace(',', '.')) || 0;
        totalOS += total;
    });
    $('#total_os').val(totalOS.toFixed(2).replace('.', ','));
}

function adicionarItemAosItensTable(ato, quantidade, descontoLegal, descricao, emolumentos, ferc, fadep, femp, total) {
    var item = '<tr>' +
        '<td>' + ato + '</td>' +
        '<td>' + quantidade + '</td>' +
        '<td>' + descontoLegal + '%</td>' +
        '<td>' + descricao + '</td>' +
        '<td>' + emolumentos + '</td>' +
        '<td>' + ferc + '</td>' +
        '<td>' + fadep + '</td>' +
        '<td>' + femp + '</td>' +
        '<td>' + total + '</td>' +
        '<td>0</td>' + // Quantidade liquidada é 0 para novos itens
        '<td>' +
            '<button type="button" class="btn btn-edit btn-sm" onclick="alterarQuantidade(this)"><i class="fa fa-pencil" aria-hidden="true"></i></button>' +
            '<button type="button" class="btn btn-danger btn-sm" onclick="removerItem(this)">Remover</button>' +
        '</td>' +
        '</tr>';
    
    $('#itensTable').append(item);
    calcularTotalOS(); // Recalcula o total da OS após adição
}

function atualizarTotalOS(os_id) {
    var totalOS = parseFloat($('#total_os').val().replace(/\./g, '').replace(',', '.')) || 0;

    $.ajax({
        url: 'atualizar_total_os.php',
        type: 'POST',
        data: {
            os_id: os_id,
            total_os: totalOS
        },
        success: function(response) {
            try {
                var res = JSON.parse(response);
                if (res.error) {
                    showAlert(res.error, 'error');
                }
            } catch (e) {
                console.log('Erro ao processar a resposta: ', e);
                showAlert('Erro ao processar a resposta do servidor.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao atualizar o total da OS', 'error');
        }
    });
}

function salvarOS() {
    var os_id = $('#os_id').val();
    var cliente = $('#cliente').val();
    var cpf_cliente = $('#cpf_cliente').val();
    var total_os = $('#total_os').val().replace(/\./g, '').replace(',', '.');
    var descricao_os = $('#descricao_os').val();
    var observacoes = $('#observacoes').val();
    var base_calculo = $('#base_calculo').val().replace(/\./g, '').replace(',', '.');

    $.ajax({
        url: 'atualizar_os.php',
        type: 'POST',
        dataType: 'json',
        data: {
            os_id: os_id,
            cliente: cliente,
            cpf_cliente: cpf_cliente,
            total_os: total_os,
            descricao_os: descricao_os,
            observacoes: observacoes,
            base_calculo: base_calculo
        },
        success: function(response) {
            console.log(response);
            if (response.error) {
                showAlert(response.error, 'error');
            } else {
                showAlert('Ordem de Serviço atualizada com sucesso!', 'success');
                setTimeout(function() {
                    window.location.href = 'visualizar_os.php?id=' + os_id;
                }, 2000);
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao atualizar a Ordem de Serviço', 'error');
        }
    });
}
</script>
<script>
    function alterarQuantidade(button) {
        var row = $(button).closest('tr');
        var quantidadeLiquidada = parseInt(row.data('quantidade-liquidada'));
        var status = row.data('status');

        $('#novaQuantidade').val(row.find('td').eq(1).text());
        $('#quantidadeLiquidada').val(quantidadeLiquidada);
        $('#statusItem').val(status);
        $('#alterarQuantidadeModal').modal('show');
        $('#alterarQuantidadeForm').data('row', row);
    }

    function salvarNovaQuantidade() {
        var novaQuantidade = parseInt($('#novaQuantidade').val());
        var quantidadeLiquidada = parseInt($('#quantidadeLiquidada').val());
        var statusItem = $('#statusItem').val();

        if (statusItem === 'liquidado') {
            showAlert('Não é permitido alterar a quantidade de um ato liquidado.', 'error');
            return;
        }

        if (novaQuantidade < quantidadeLiquidada) {
            showAlert('A nova quantidade não pode ser menor que a quantidade liquidada.', 'error');
            return;
        }

        var row = $('#alterarQuantidadeForm').data('row');
        var item_id = row.data('item-id');
        var ato = row.find('td').eq(0).text();
        var descontoLegal = parseFloat(row.find('td').eq(2).text().replace('%', ''));

        buscarAtoPorQuantidade(ato, novaQuantidade, descontoLegal, function(values) {
            $.ajax({
                url: 'atualizar_quantidade_item.php',
                type: 'POST',
                data: {
                    item_id: item_id,
                    quantidade: novaQuantidade,
                    emolumentos: parseFloat(values.emolumentos.replace(',', '.')),
                    ferc: parseFloat(values.ferc.replace(',', '.')),
                    fadep: parseFloat(values.fadep.replace(',', '.')),
                    femp: parseFloat(values.femp.replace(',', '.')),
                    total: parseFloat(values.total.replace(',', '.'))
                },
                success: function(response) {
                    try {
                        var res = JSON.parse(response);
                        if (res.error) {
                            showAlert(res.error, 'error');
                        } else {
                            row.find('td').eq(1).text(novaQuantidade);
                            row.find('td').eq(3).text(values.descricao);
                            row.find('td').eq(4).text(values.emolumentos);
                            row.find('td').eq(5).text(values.ferc);
                            row.find('td').eq(6).text(values.fadep);
                            row.find('td').eq(7).text(values.femp);
                            row.find('td').eq(8).text(values.total);

                            if (novaQuantidade === quantidadeLiquidada) {
                                row.data('status', 'liquidado');
                                row.find('td').eq(10).text('liquidado');
                                $.ajax({
                                    url: 'atualizar_status_item.php',
                                    type: 'POST',
                                    data: {
                                        item_id: item_id,
                                        quantidade: novaQuantidade
                                    },
                                    success: function(response) {
                                        try {
                                            var res = JSON.parse(response);
                                            if (res.error) {
                                                showAlert(res.error, 'error');
                                            }
                                        } catch (e) {
                                            console.log('Erro ao processar a resposta: ', e);
                                            showAlert('Erro ao processar a resposta do servidor.', 'error');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.log('Erro:', error);
                                        console.log('Resposta do servidor:', xhr.responseText);
                                        showAlert('Erro ao atualizar o status do item', 'error');
                                    }
                                });
                            }

                            calcularTotalOS();
                            $('#alterarQuantidadeModal').modal('hide');
                            showAlert('Quantidade atualizada com sucesso!', 'success');
                        }
                    } catch (e) {
                        console.log('Erro ao processar a resposta: ', e);
                        showAlert('Erro ao processar a resposta do servidor.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Erro:', error);
                    console.log('Resposta do servidor:', xhr.responseText);
                    showAlert('Erro ao atualizar a quantidade do item', 'error');
                }
            });
        });
    }
</script>

<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
