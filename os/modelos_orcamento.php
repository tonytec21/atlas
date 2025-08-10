<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Modelos de Orçamento</title>
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
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
    <!-- HERO / TÍTULO -->
    <section class="page-hero">
    <div class="title-row">
        <div class="title-icon"><i class="fa fa-file-text-o"></i></div>
        <div class="title-texts">
        <h1>Modelos de O.S</h1>
        <div class="subtitle muted">Catálogo e gestão dos modelos de ordem de serviço.</div>
        </div>
        <div class="title-actions ml-auto">
        <a href="index.php" class="btn btn-secondary">
            <i class="fa fa-search" aria-hidden="true"></i> Ordens de Serviço
        </a>
        </div>
    </div>
    </section>


    <!-- Formulário para criar/editar modelo -->
    <div class="card mb-4">
        <div class="card-header">
            Criar Novo Modelo
        </div>
        <div class="card-body">
            <form id="formModelo">
                <!-- Campo oculto para identificar se estamos editando um modelo existente -->
                <input type="hidden" id="modelo_id_edit" name="modelo_id_edit" value="">

                <div class="form-group">
                    <label for="nome_modelo">Nome do Modelo</label>
                    <input type="text" class="form-control" id="nome_modelo" name="nome_modelo" required>
                </div>

                <div class="form-group">
                    <label for="descricao_modelo">Descrição (opcional)</label>
                    <textarea class="form-control" id="descricao_modelo" name="descricao_modelo" rows="3"></textarea>
                </div>

                <!-- Campos para adicionar itens ao modelo -->
                <h5>Adicionar Itens ao Modelo</h5>
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="ato">Código do Ato:</label>
                        <input type="text" class="form-control" id="ato" name="ato" pattern="[0-9A-Za-z.]+" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="quantidade">Quantidade:</label>
                        <input type="number" class="form-control" id="quantidade" name="quantidade" value="1" min="1">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="desconto_legal">Desconto Legal (%):</label>
                        <input type="number" class="form-control" id="desconto_legal" name="desconto_legal" value="0" min="0" max="100">
                    </div>
                    <div class="form-group col-md-5">
                        <label>&nbsp;</label>
                        <div>
                            <button type="button" class="btn btn-primary" onclick="buscarAto()">
                                <i class="fa fa-search" aria-hidden="true"></i> Buscar Ato
                            </button>
                            <button type="button" class="btn btn-secondary btn-adicionar-manual" onclick="adicionarAtoManual()">
                                <i class="fa fa-i-cursor" aria-hidden="true"></i> Adicionar Ato Manualmente
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="descricao_item">Descrição:</label>
                        <input type="text" class="form-control" id="descricao_item" name="descricao_item" readonly>
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
                        <button type="button" class="btn btn-success btn-block" onclick="adicionarItemTabela()">
                            <i class="fa fa-plus" aria-hidden="true"></i> Adicionar
                        </button>
                    </div>
                </div>

                <hr>
                <!-- Tabela de itens do modelo -->
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ato</th>
                            <th>Qtd</th>
                            <th>Desc. (%)</th>
                            <th>Descrição</th>
                            <th>Emolumentos</th>
                            <th>FERC</th>
                            <th>FADEP</th>
                            <th>FEMP</th>
                            <th>Total</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaItensModelo">
                    </tbody>
                </table>

                <button type="button" class="btn btn-primary btn-block" onclick="salvarModelo()">
                    <i class="fa fa-floppy-o" aria-hidden="true"></i> Salvar Modelo
                </button>
            </form>
        </div>
    </div>

    <!-- Listagem de modelos já cadastrados -->
    <div class="card">
        <div class="card-header">
            Modelos Existentes
        </div>
        <div class="card-body" id="listaModelos">
            <!-- Aqui serão listados os modelos via AJAX -->
        </div>
    </div>
    </div>
</div>

<!-- Modal para Visualizar Itens do Modelo -->
<div class="modal fade" id="modalVisualizarModelo" tabindex="-1" aria-labelledby="modalVisualizarModeloLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalVisualizarModeloLabel">Itens do Modelo</h5>
        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered" id="tabelaItensVisualizar" style="zoom: 90%">
          <thead>
            <tr>
              <th>Ato</th>
              <th>Qtd</th>
              <th>Desc (%)</th>
              <th>Descrição</th>
              <th>Emolumentos</th>
              <th>FERC</th>
              <th>FADEP</th>
              <th>FEMP</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            
          </tbody>
        </table>
      </div>
    </div>
</div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/sweetalert2.js"></script>

<script>

function showAlert(message, type) {
    let iconType = (type === 'error') ? 'error' : 'success';
    Swal.fire({
        icon: iconType,
        title: type === 'error' ? 'Erro!' : 'Sucesso!',
        text: message,
        confirmButtonText: 'OK'
    });
}

$(document).ready(function() {
    carregarModelos();
    $('#emolumentos, #ferc, #fadep, #femp, #total').mask('#.##0,00', {reverse: true});
    $('#ato').on('input', function() {
        this.value = this.value.replace(/[^0-9a-ab-bc-cd-d.]/g, '');
    });
});


function buscarAto() {
    var ato = $('#ato').val();
    var quantidade = $('#quantidade').val();
    var descontoLegal = $('#desconto_legal').val();

    $.ajax({
        url: 'buscar_ato.php',
        type: 'GET',
        dataType: 'json',
        data: { ato: ato },
        success: function(response) {
            if (response.error) {
                showAlert(response.error, 'error');
            } else {
                try {
                    var emolumentos = parseFloat(response.EMOLUMENTOS) * quantidade;
                    var ferc       = parseFloat(response.FERC)        * quantidade;
                    var fadep      = parseFloat(response.FADEP)       * quantidade;
                    var femp       = parseFloat(response.FEMP)        * quantidade;

                    var desconto = descontoLegal / 100;
                    emolumentos *= (1 - desconto);
                    ferc        *= (1 - desconto);
                    fadep       *= (1 - desconto);
                    femp        *= (1 - desconto);

                    $('#descricao_item').val(response.DESCRICAO);
                    $('#emolumentos').val(emolumentos.toFixed(2).replace('.', ','));
                    $('#ferc').val(ferc.toFixed(2).replace('.', ','));
                    $('#fadep').val(fadep.toFixed(2).replace('.', ','));
                    $('#femp').val(femp.toFixed(2).replace('.', ','));
                    $('#total').val((emolumentos + ferc + fadep + femp).toFixed(2).replace('.', ','));
                } catch (e) {
                    showAlert('Erro ao processar os dados do ato.', 'error');
                }
            }
        },
        error: function() {
            showAlert('Erro ao buscar o ato', 'error');
        }
    });
}

/* Permite preencher manualmente os campos (tira o readonly) */
function adicionarAtoManual() {
    $('#descricao_item').prop('readonly', false);
    $('#emolumentos').prop('readonly', false);
    $('#ferc').prop('readonly', false);
    $('#fadep').prop('readonly', false);
    $('#femp').prop('readonly', false);
    $('#total').prop('readonly', false);
}

/*  Adiciona o item atual do formulário na tabela de itens do modelo */
function adicionarItemTabela() {
    var ato           = $('#ato').val();
    var quantidade    = $('#quantidade').val();
    var descontoLegal = $('#desconto_legal').val();
    var descricao     = $('#descricao_item').val();
    var emolumentos   = $('#emolumentos').val();
    var ferc          = $('#ferc').val();
    var fadep         = $('#fadep').val();
    var femp          = $('#femp').val();
    var total         = $('#total').val();

    // Validações básicas
    if (!ato && !descricao) {
        showAlert('Informe ao menos o Ato ou a Descrição', 'error');
        return;
    }
    if (!quantidade) quantidade = 1;
    if (!descontoLegal) descontoLegal = 0;
    if (!emolumentos) emolumentos = "0,00";
    if (!ferc) ferc = "0,00";
    if (!fadep) fadep = "0,00";
    if (!femp) femp = "0,00";
    if (!total) total = "0,00";

    // Monta a linha da tabela
    var item = `
    <tr>
        <td>${ato}</td>
        <td>${quantidade}</td>
        <td>${descontoLegal}</td>
        <td>${descricao}</td>
        <td>${emolumentos}</td>
        <td>${ferc}</td>
        <td>${fadep}</td>
        <td>${femp}</td>
        <td>${total}</td>
        <td>
            <button type="button" class="btn btn-delete btn-sm" onclick="removerItem(this)">
                <i class="fa fa-trash" aria-hidden="true"></i>
            </button>
        </td>
    </tr>`;

    // Adiciona no <tbody>
    $('#tabelaItensModelo').append(item);

    // Limpa os campos de formulário de item
    $('#ato').val('');
    $('#quantidade').val('1');
    $('#desconto_legal').val('0');
    $('#descricao_item').val('');
    $('#emolumentos').val('');
    $('#ferc').val('');
    $('#fadep').val('');
    $('#femp').val('');
    $('#total').val('');

    // Retorna campos ao readonly
    $('#descricao_item, #emolumentos, #ferc, #fadep, #femp, #total').prop('readonly', true);
}


function removerItem(button) {
    $(button).closest('tr').remove();
}


function salvarModelo() {
    var nome_modelo = $('#nome_modelo').val();
    var descricao_modelo = $('#descricao_modelo').val();
    var modelo_id_edit = $('#modelo_id_edit').val(); // se estiver preenchido, é edição

    if (!nome_modelo) {
        showAlert('O Nome do Modelo é obrigatório!', 'error');
        return;
    }

    // Monta o array de itens da tabela
    var itens = [];
    $('#tabelaItensModelo tr').each(function() {
        var tds = $(this).find('td');
        var item = {
            ato:            tds.eq(0).text(),
            quantidade:     tds.eq(1).text(),
            desconto_legal: tds.eq(2).text(),
            descricao:      tds.eq(3).text(),
            emolumentos:    tds.eq(4).text(),
            ferc:           tds.eq(5).text(),
            fadep:          tds.eq(6).text(),
            femp:           tds.eq(7).text(),
            total:          tds.eq(8).text()
        };
        itens.push(item);
    });

    // Decide qual arquivo chamar (criação ou atualização)
    var urlAcao = modelo_id_edit ? 'atualizar_modelo_orcamento.php' : 'salvar_modelo_orcamento.php';

    $.ajax({
        url: urlAcao,
        type: 'POST',
        dataType: 'json',
        data: {
            id: modelo_id_edit,
            nome_modelo: nome_modelo,
            descricao_modelo: descricao_modelo,
            itens: itens
        },
        success: function(response) {
            if (response.error) {
                showAlert(response.error, 'error');
            } else {
                var msg = modelo_id_edit 
                          ? 'Modelo atualizado com sucesso!' 
                          : 'Modelo salvo com sucesso!';
                showAlert(msg, 'success');
                
                // Limpar formulário e tabela
                $('#formModelo')[0].reset();
                $('#tabelaItensModelo').empty();
                // Zera o campo hidden, pois finalizamos a edição
                $('#modelo_id_edit').val('');

                // Recarrega a listagem de modelos
                carregarModelos();
            }
        },
        error: function(xhr) {
            showAlert('Erro ao salvar/atualizar o modelo.', 'error');
            console.error(xhr.responseText);
        }
    });
}


function carregarModelos() {
    $.ajax({
        url: 'listar_modelos_orcamento.php',
        type: 'GET',
        dataType: 'html',
        success: function(response) {
            $('#listaModelos').html(response);
        },
        error: function() {
            $('#listaModelos').html('<p>Erro ao carregar os modelos.</p>');
        }
    });
}


function visualizarModelo(id) {
    $.ajax({
        url: 'carregar_modelo_orcamento.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                showAlert(response.error, 'error');
            } else if (response.itens) {
                // Limpar a tabela do modal antes de inserir
                $('#tabelaItensVisualizar tbody').empty();

                response.itens.forEach(function(item) {
                    let row = `
                    <tr>
                    <td>${item.ato}</td>
                    <td>${item.quantidade}</td>
                    <td>${item.desconto_legal}</td>
                    <td>${item.descricao}</td>
                    <td>R$ ${parseFloat(item.emolumentos).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td>R$ ${parseFloat(item.ferc).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td>R$ ${parseFloat(item.fadep).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td>R$ ${parseFloat(item.femp).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td>R$ ${parseFloat(item.total).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    </tr>
                    `;
                    $('#tabelaItensVisualizar tbody').append(row);
                });

                // Exibir o modal
                $('#modalVisualizarModelo').modal('show');
            }
        },
        error: function() {
            showAlert('Erro ao carregar itens do modelo.', 'error');
        }
    });
}


function editarModelo(id) {
    // Define o campo hidden para indicar que estamos em edição
    $('#modelo_id_edit').val(id);

    // Limpa o formulário e a tabela antes de preencher
    $('#formModelo')[0].reset();
    $('#tabelaItensModelo').empty();

    $.ajax({
        url: 'carregar_modelo_orcamento.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                showAlert(response.error, 'error');
            } else if (response.itens) {
                // Preenche nome e descrição do modelo
                if (response.nome_modelo) {
                    $('#nome_modelo').val(response.nome_modelo);
                }
                if (response.descricao_modelo) {
                    $('#descricao_modelo').val(response.descricao_modelo);
                }

                // Inserir itens na tabela
                response.itens.forEach(function(item) {
                    let row = `
                    <tr>
                        <td>${item.ato}</td>
                        <td>${item.quantidade}</td>
                        <td>${item.desconto_legal}</td>
                        <td>${item.descricao}</td>
                        <td>${item.emolumentos}</td>
                        <td>${item.ferc}</td>
                        <td>${item.fadep}</td>
                        <td>${item.femp}</td>
                        <td>${item.total}</td>
                        <td>
                            <button type="button" class="btn btn-delete btn-sm" onclick="removerItem(this)">
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>`;
                    $('#tabelaItensModelo').append(row);
                });

                // Rola a tela até o formulário
                $('html, body').animate({ scrollTop: $('#formModelo').offset().top }, 'slow');
            }
        },
        error: function() {
            showAlert('Erro ao carregar modelo para edição.', 'error');
        }
    });
}

function excluirModelo(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Deseja realmente excluir este modelo?',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'excluir_modelo_orcamento.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        showAlert(response.error, 'error');
                    } else {
                        showAlert('Modelo excluído com sucesso!', 'success');
                        carregarModelos(); // Recarrega a listagem
                    }
                },
                error: function() {
                    showAlert('Erro ao excluir o modelo.', 'error');
                }
            });
        }
    });
}
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
