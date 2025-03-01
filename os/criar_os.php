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
    <title>Criar Ordem de Serviço</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/sweetalert2.min.css">
    <style>
        .btn-adicionar-manual {
            /* height: 38px; mesma altura do botão Buscar Ato */
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
    
        <!-- Centraliza os botões -->
        <div class="d-flex justify-content-center align-items-center text-center mb-3">
            <button id="add-button" type="button" class="btn btn-secondary mx-2" onclick="window.open('tabela_de_emolumentos.php')">
                <i class="fa fa-table" aria-hidden="true"></i> Tabela de Emolumentos
            </button>

            <a href="index.php" class="btn btn-secondary mx-2">
                <i class="fa fa-search" aria-hidden="true"></i> Ordens de Serviço
            </a>
        </div>
        <hr> 

        <!-- Centraliza o título e o select -->
        <div class="text-center">
            <h3 class="mb-3">Criar Ordem de Serviço</h3>
            <div class="form-group">
                <label for="modelo_orcamento">Carregar Modelo de O.S:</label>
                <select id="modelo_orcamento" class="form-control w-50 mx-auto" onchange="carregarModeloSelecionado()">
                    <option value="">Selecione um modelo...</option>
                </select>
            </div>
        </div>

        <hr>
        <form id="osForm" method="POST">
            <div class="form-row">
                <div class="form-group col-md-5">
                    <label for="cliente">Apresentante:</label>
                    <input type="text" class="form-control" id="cliente" name="cliente" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="cpf_cliente">CPF/CNPJ do Apresentante:</label>
                    <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente">
                </div>
                <div class="form-group col-md-2">
                    <label for="base_calculo">Base de Cálculo:</label>
                    <input type="text" class="form-control" id="base_calculo" name="base_calculo">
                </div>
                <div class="form-group col-md-2">
                    <label for="total_os">Valor Total da OS:</label>
                    <input type="text" class="form-control" id="total_os" name="total_os" readonly>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="descricao_os">Título da OS:</label>
                    <input type="text" class="form-control" id="descricao_os" name="descricao_os">
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
                    <button type="submit" style="width: 100%" class="btn btn-success"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar à OS</button>
                </div>
            </div>
        </form>
        <div id="osItens" class="mt-4">
            <h4>Itens da Ordem de Serviço</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ato</th>
                        <th>Quantidade</th>
                        <th>Desconto Legal (%)</th>
                        <th>Descrição</th>
                        <th>Emolumentos</th>
                        <th>FERC</th>
                        <th>FADEP</th>
                        <th>FEMP</th>
                        <th>Total</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="itensTable">
                    <!-- Itens adicionados vão aqui -->
                </tbody>
            </table>
        </div>
        <button type="button" style="width: 100%;" class="btn btn btn-secondary btn-block" onclick="adicionarISS()"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar ISS</button>
        <hr>
        <div class="form-group">
            <label for="observacoes">Observações:</label>
            <textarea class="form-control" id="observacoes" name="observacoes" rows="4"></textarea>
        </div>
        <button type="button" class="btn btn-primary btn-block" onclick="salvarOS()"><i class="fa fa-floppy-o" aria-hidden="true"></i> SALVAR OS</button>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="alertModal" tabindex="-1" role="dialog" aria-labelledby="alertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" id="alertModalHeader">
                <h5 class="modal-title" id="alertModalLabel">Alerta</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
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
<script src="../script/jquery-ui.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/bootstrap.min.js"></script> 
<script src="../script/sweetalert2.js"></script>

<script>
    $(document).ready(function() {
    // Inicializa a funcionalidade de arrastar e soltar na tabela de itens
    $("#itensTable").sortable({
        placeholder: "ui-state-highlight",
        update: function(event, ui) {
            atualizarOrdemExibicao(); // Função para atualizar a ordem
        }
    });

    $("#itensTable").disableSelection(); // Impede que o texto dentro da tabela seja selecionado durante o arraste

    // Função para atualizar a ordem de exibição
    function atualizarOrdemExibicao() {
        // Atualiza a ordem de exibição na tabela
        $('#itensTable tr').each(function(index) {
            // Atualiza a coluna de ordem de exibição
            $(this).find('td:first').text(index + 1); // Primeira coluna é o número da ordem
        });


        // Aqui você pode enviar via AJAX a nova ordem para o servidor, se necessário
        var ordem = [];
        $('#itensTable tr').each(function(index) {
            var itemId = $(this).data('item-id'); // Supondo que cada item tem um ID único
            ordem.push({ id: itemId, ordem: index + 1 });
        });

        $.ajax({
            url: 'atualizar_ordem_itens.php', // URL para atualizar a ordem no servidor
            type: 'POST',
            data: { ordem: ordem },
            success: function(response) {
                console.log('Ordem de exibição atualizada com sucesso!');
            },
            error: function(xhr, status, error) {
                console.log('Erro ao atualizar a ordem de exibição: ' + error);
            }
        });
    }

// Carregar lista de modelos no select
$.ajax({
        url: 'listar_todos_modelos.php', // um novo script para retornar JSON
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.modelos) {
                response.modelos.forEach(function(modelo) {
                    $('#modelo_orcamento').append(
                        $('<option>', {
                            value: modelo.id,
                            text: modelo.nome_modelo
                        })
                    );
                });
            }
        },
        error: function() {
            console.log('Erro ao carregar modelos');
        }
    });
});

// Função para exibir modal de alerta
function showAlert(message, type) {
    let iconType = type === 'error' ? 'error' : 'success';

    Swal.fire({
        icon: iconType,
        title: type === 'error' ? 'Erro!' : 'Sucesso!',
        text: message,
        confirmButtonText: 'OK'
    });
}


$(document).ready(function() {
    // Carregar o modo do usuário
    $.ajax({
        url: '../load_mode.php',
        method: 'GET',
        success: function(mode) {
            $('body').removeClass('light-mode dark-mode').addClass(mode);
        }
    });

    // Função para alternar modos claro e escuro
    $('.mode-switch').on('click', function() {
        var body = $('body');
        body.toggleClass('dark-mode light-mode');

        var mode = body.hasClass('dark-mode') ? 'dark-mode' : 'light-mode';
        $.ajax({
            url: '../save_mode.php',
            method: 'POST',
            data: {
                mode: mode
            },
            success: function(response) {
                console.log(response);
            }
        });
    });

    // Máscara do CPF/CNPJ ao perder o foco
    $('#cpf_cliente').on('blur', function() {
        var cpfCnpj = $(this).val().replace(/\D/g, '');
        if (cpfCnpj.length === 11) {
            $(this).mask('000.000.000-00', {reverse: true});
            if (!validarCPF($(this).val())) {
                showAlert('CPF inválido!', 'error');
                $(this).val('');
            }
        } else if (cpfCnpj.length === 14) {
            $(this).mask('00.000.000/0000-00', {reverse: true});
            if (!validarCNPJ($(this).val())) {
                showAlert('CNPJ inválido!', 'error');
                $(this).val('');
            }
        } else {
            showAlert('CPF ou CNPJ inválido!', 'error');
            $(this).val('');
        }
    });

    $('#base_calculo, #emolumentos, #ferc, #fadep, #femp, #total').mask('#.##0,00', {reverse: true});

    $('#osForm').on('submit', function(e) {
        e.preventDefault();
        
        var ato = $('#ato').val();
        var quantidade = parseInt($('#quantidade').val(), 10);
        var descontoLegal = $('#desconto_legal').val();
        var descricao = $('#descricao').val();
        var emolumentos = parseFloat($('#emolumentos').val().replace(/\./g, '').replace(',', '.')); // Corrigir o valor para formato numérico
        var ferc = parseFloat($('#ferc').val().replace(/\./g, '').replace(',', '.')) || 0;
        var fadep = parseFloat($('#fadep').val().replace(/\./g, '').replace(',', '.')) || 0;
        var femp = parseFloat($('#femp').val().replace(/\./g, '').replace(',', '.')) || 0;
        var total = parseFloat($('#total').val().replace(/\./g, '').replace(',', '.')) || 0;

        // Validação de emolumentos para garantir que é um número válido
        if (isNaN(emolumentos)) {
            showAlert('O valor dos emolumentos deve ser um número válido.', 'error');
            return;
        }

        // Contar quantos itens existem na tabela e usar esse valor como ordem
        var ordemExibicao = $('#itensTable tr').length + 1;

        if (isNaN(total) || total <= 0) {
            showAlert("Por favor, clique em 'Buscar Ato' antes de adicionar à OS.", 'error');
            return;
        }
        
        var item = '<tr>' +
            '<td>' + ordemExibicao + '</td>' + // Adiciona a ordem de exibição
            '<td>' + ato + '</td>' +
            '<td>' + quantidade + '</td>' +
            '<td>' + descontoLegal + '%</td>' +
            '<td>' + descricao + '</td>' +
            '<td>' + emolumentos.toFixed(2).replace('.', ',') + '</td>' +
            '<td>' + ferc.toFixed(2).replace('.', ',') + '</td>' +
            '<td>' + fadep.toFixed(2).replace('.', ',') + '</td>' +
            '<td>' + femp.toFixed(2).replace('.', ',') + '</td>' +
            '<td>' + total.toFixed(2).replace('.', ',') + '</td>' +
            '<td><button type="button" title="Remover" class="btn btn-delete btn-sm" onclick="removerItem(this)"><i class="fa fa-trash" aria-hidden="true"></i></button></td>' +
            '</tr>';
            
        $('#itensTable').append(item);
        
        var totalOS = parseFloat($('#total_os').val().replace(/\./g, '').replace(',', '.')) || 0;
        totalOS += total;
        $('#total_os').val(totalOS.toFixed(2).replace('.', ','));

        $('#ato').val('');
        $('#quantidade').val('1');
        $('#desconto_legal').val('0');
        $('#descricao').val('');
        $('#emolumentos').val('');
        $('#ferc').val('');
        $('#fadep').val('');
        $('#femp').val('');
        $('#total').val('');

        $('#descricao').prop('readonly', true);
        $('#emolumentos').prop('readonly', true);
        $('#ferc').prop('readonly', true);
        $('#fadep').prop('readonly', true);
        $('#femp').prop('readonly', true);
        $('#total').prop('readonly', true);
    });



    $('#ato').on('input', function() {
        this.value = this.value.replace(/[^0-9a-ab-bc-cd-d.]/g, '');
    });

    $('#quantidade, #desconto_legal').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});

function buscarAto() {
    var ato = $('#ato').val();
    var quantidade = $('#quantidade').val();
    var descontoLegal = $('#desconto_legal').val();

    $.ajax({
        url: 'buscar_ato.php',
        type: 'GET',
        dataType: 'json', // Força a resposta a ser tratada como JSON
        data: { ato: ato },
        success: function(response) {
            console.log('Resposta do servidor:', response); // Log da resposta do servidor

            if (response.error) {
                showAlert(response.error, 'error');
            } else {
                try {
                    var emolumentos = parseFloat(response.EMOLUMENTOS) * quantidade;
                    var ferc = parseFloat(response.FERC) * quantidade;
                    var fadep = parseFloat(response.FADEP) * quantidade;
                    var femp = parseFloat(response.FEMP) * quantidade;

                    var desconto = descontoLegal / 100;
                    emolumentos = emolumentos * (1 - desconto);
                    ferc = ferc * (1 - desconto);
                    fadep = fadep * (1 - desconto);
                    femp = femp * (1 - desconto);

                    $('#descricao').val(response.DESCRICAO);
                    $('#emolumentos').val(emolumentos.toFixed(2).replace('.', ','));
                    $('#ferc').val(ferc.toFixed(2).replace('.', ','));
                    $('#fadep').val(fadep.toFixed(2).replace('.', ','));
                    $('#femp').val(femp.toFixed(2).replace('.', ','));
                    $('#total').val((emolumentos + ferc + fadep + femp).toFixed(2).replace('.', ','));
                } catch (e) {
                    console.log('Erro ao processar os dados do ato:', e);
                    showAlert('Erro ao processar os dados do ato.', 'error');
                }
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao buscar o ato', 'error');
        }
    });
}

function adicionarISS() {
    var totalEmolumentos = 0;
    $('#itensTable tr').each(function() {
        var emolumentos = parseFloat($(this).find('td').eq(5).text().replace(/\./g, '').replace(',', '.')) || 0;
        totalEmolumentos += emolumentos;
    });

    var baseISS = totalEmolumentos * 0.88; 
    var valorISS = baseISS * 0.05;

    var item = '<tr>' +
        '<td>#</td>' +
        '<td>ISS</td>' +
        '<td>1</td>' +
        '<td>0%</td>' +
        '<td>ISS sobre Emolumentos</td>' +
        '<td>' + valorISS.toFixed(2).replace('.', ',') + '</td>' +
        '<td>0,00</td>' +
        '<td>0,00</td>' +
        '<td>0,00</td>' +
        '<td>' + valorISS.toFixed(2).replace('.', ',') + '</td>' +
        '<td><button type="button" title="Remover" class="btn btn-delete btn-sm" onclick="removerItem(this)"><i class="fa fa-trash" aria-hidden="true"></i></button></td>' +
        '</tr>';

    $('#itensTable').append(item);

    var totalOS = parseFloat($('#total_os').val().replace(/\./g, '').replace(',', '.')) || 0;
    totalOS += valorISS;
    $('#total_os').val(totalOS.toFixed(2).replace('.', ','));
}

function adicionarAtoManual() {
    $('#descricao').prop('readonly', false);
    $('#emolumentos').prop('readonly', false);
    $('#ferc').prop('readonly', false);
    $('#fadep').prop('readonly', false);
    $('#femp').prop('readonly', false);
    $('#total').prop('readonly', false);
}

function removerItem(button) {
    var row = $(button).closest('tr');
    var totalItem = parseFloat(row.find('td').eq(9).text().replace(/\./g, '').replace(',', '.')) || 0;

    var totalOS = parseFloat($('#total_os').val().replace(/\./g, '').replace(',', '.')) || 0;
    totalOS -= totalItem;
    $('#total_os').val(totalOS.toFixed(2).replace('.', ','));

    row.remove();

    // Atualiza a ordem de exibição após a remoção de um item
    atualizarOrdemExibicao();
}

function salvarOS() {
    var cliente = $('#cliente').val();
    var cpf_cliente = $('#cpf_cliente').val();
    var total_os = $('#total_os').val().replace(/\./g, '').replace(',', '.');
    var descricao_os = $('#descricao_os').val();
    var observacoes = $('#observacoes').val();
    var base_calculo = $('#base_calculo').val().replace(/\./g, '').replace(',', '.');
    var itens = [];

    $('#itensTable tr').each(function(index) {
        var ato = $(this).find('td').eq(1).text();
        var quantidade = $(this).find('td').eq(2).text();
        var desconto_legal = $(this).find('td').eq(3).text().replace('%', '');
        var descricao = $(this).find('td').eq(4).text();
        var emolumentos = $(this).find('td').eq(5).text().replace(/\./g, '').replace(',', '.');
        var ferc = $(this).find('td').eq(6).text().replace(/\./g, '').replace(',', '.');
        var fadep = $(this).find('td').eq(7).text().replace(/\./g, '').replace(',', '.');
        var femp = $(this).find('td').eq(8).text().replace(/\./g, '').replace(',', '.');
        var total = $(this).find('td').eq(9).text().replace(/\./g, '').replace(',', '.');
        var ordem_exibicao = index + 1; // Define a ordem correta na tabela

        itens.push({
            ato: ato,
            quantidade: quantidade,
            desconto_legal: desconto_legal,
            descricao: descricao,
            emolumentos: emolumentos,
            ferc: ferc,
            fadep: fadep,
            femp: femp,
            total: total,
            ordem_exibicao: ordem_exibicao // Adiciona a ordem de exibição ao objeto
        });
    });

    $.ajax({
        url: 'salvar_os.php',
        type: 'POST',
        data: {
            cliente: cliente,
            cpf_cliente: cpf_cliente,
            total_os: total_os,
            descricao_os: descricao_os,
            observacoes: observacoes,
            base_calculo: base_calculo,
            itens: itens
        },
        success: function(response) {
            console.log(response); // Adicionando log de depuração para verificar a resposta do servidor
            try {
                var res = JSON.parse(response);
                if (res.error) {
                    showAlert(res.error, 'error');
                } else {
                    showAlert('Ordem de Serviço salva com sucesso!', 'success');
                    setTimeout(function() {
                        window.location.href = 'visualizar_os.php?id=' + res.id;
                    }, 2000);
                }
            } catch (e) {
                console.log('Erro ao processar a resposta: ', e);
                showAlert('Erro ao processar a resposta do servidor.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao salvar a Ordem de Serviço', 'error');
        }
    });
}


// Funções para validar CPF e CNPJ
function validarCPF(cpf) {
    cpf = cpf.replace(/[^\d]+/g, '');
    if (cpf.length !== 11) return false;
    let soma = 0;
    let resto;
    if (cpf === "00000000000") return false;
    for (let i = 1; i <= 9; i++) soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
    resto = (soma * 10) % 11;
    if ((resto === 10) || (resto === 11)) resto = 0;
    if (resto !== parseInt(cpf.substring(9, 10))) return false;
    soma = 0;
    for (let i = 1; i <= 10; i++) soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
    resto = (soma * 10) % 11;
    if ((resto === 10) || (resto === 11)) resto = 0;
    if (resto !== parseInt(cpf.substring(10, 11))) return false;
    return true;
}

function validarCNPJ(cnpj) {
    cnpj = cnpj.replace(/[^\d]+/g, '');
    if (cnpj.length !== 14) return false;
    if (cnpj === "00000000000000") return false;
    let tamanho = cnpj.length - 2;
    let numeros = cnpj.substring(0, tamanho);
    let digitos = cnpj.substring(tamanho);
    let soma = 0;
    let pos = tamanho - 7;
    for (let i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
    }
    let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado !== parseInt(digitos.charAt(0))) return false;
    tamanho = tamanho + 1;
    numeros = cnpj.substring(0, tamanho);
    soma = 0;
    pos = tamanho - 7;
    for (let i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
    }
    resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado !== parseInt(digitos.charAt(1))) return false;
    return true;
}


function carregarModeloSelecionado() {
    var idModelo = $('#modelo_orcamento').val();
    if (!idModelo) return;

    $.ajax({
        url: 'carregar_modelo_orcamento.php',
        type: 'GET',
        data: { id: idModelo },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                showAlert(response.error, 'error');
            } else if (response.itens) {
                // Para cada item, inserir na tabela de itens da OS
                response.itens.forEach(function(item) {
                    // Precisamos converter strings para formato numérico
                    let emolumentos = parseFloat((item.emolumentos || "0").replace(',', '.'));
                    let ferc       = parseFloat((item.ferc        || "0").replace(',', '.'));
                    let fadep      = parseFloat((item.fadep       || "0").replace(',', '.'));
                    let femp       = parseFloat((item.femp        || "0").replace(',', '.'));
                    let total      = parseFloat((item.total       || "0").replace(',', '.'));

                    // Descobrimos qual a ordem do próximo item
                    var ordemExibicao = $('#itensTable tr').length + 1;

                    // Montamos a linha
                    var row = `
                    <tr>
                        <td>${ordemExibicao}</td>
                        <td>${item.ato}</td>
                        <td>${item.quantidade}</td>
                        <td>${item.desconto_legal}%</td>
                        <td>${item.descricao}</td>
                        <td>${emolumentos.toFixed(2).replace('.', ',')}</td>
                        <td>${ferc.toFixed(2).replace('.', ',')}</td>
                        <td>${fadep.toFixed(2).replace('.', ',')}</td>
                        <td>${femp.toFixed(2).replace('.', ',')}</td>
                        <td>${total.toFixed(2).replace('.', ',')}</td>
                        <td>
                            <button type="button" title="Remover" class="btn btn-delete btn-sm" onclick="removerItem(this)">
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>
                    `;
                    $('#itensTable').append(row);

                    // Atualizar o total da OS
                    var valorOS = parseFloat($('#total_os').val().replace(/\./g, '').replace(',', '.')) || 0;
                    valorOS += total;
                    $('#total_os').val(valorOS.toFixed(2).replace('.', ','));
                });
            }
        },
        error: function(xhr) {
            console.log(xhr.responseText);
            showAlert('Erro ao carregar o modelo selecionado.', 'error');
        }
    });
}

</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
