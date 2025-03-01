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
    <title>Gerenciar Checklists</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .btn-actions {
            display: flex;
            gap: 5px;
        }
        .modal-body .list-group-item {
            border: none;
            padding-left: 0;
        }
        .modal-content {
            border-radius: 12px;
        }
        .modal-header {
            background-color: #007bff;
            color: white;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        .modal-footer {
            border-top: none;
        }
        .card-checklist {
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
    <h3>Gerenciar Checklists</h3>
    <hr>

    <!-- Formulário para Criar/Editar Checklist -->
    <div class="card mb-4">
        <div class="card-header">Criar Novo Checklist</div>
        <div class="card-body">
            <form id="formChecklist">
                <input type="hidden" id="checklist_id" name="checklist_id">

                <div class="form-group">
                    <label for="titulo">Título do Checklist</label>
                    <input type="text" class="form-control" id="titulo" name="titulo" required>
                </div>

                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="novo_item">Adicionar Item</label>
                    <div class="input-group mb-3">
                        <textarea type="text" class="form-control" id="novo_item" placeholder="Separe os itens com ; (ex: RG; CPF; Certidão de Nascimento)"></textarea>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-success" onclick="adicionarItem()">
                                <i class="fa fa-plus"></i> Adicionar
                            </button>
                        </div>
                    </div>
                </div>

                <ul class="list-group mb-3" id="listaItens"></ul>

                <button type="button" class="btn btn-primary btn-block" onclick="salvarChecklist()">
                    <i class="fa fa-save"></i> Salvar Checklist
                </button>
            </form>
        </div>
    </div>

    <!-- Listagem de Checklists -->
    <div class="card">
        <div class="card-header">Checklists Salvos</div>
        <div class="card-body" id="listaChecklists">
            <!-- Checklists serão carregados via AJAX -->
        </div>
    </div>
</div>
</div>

<!-- Modal para Visualizar Checklist -->
<div class="modal fade" id="modalVisualizarChecklist" tabindex="-1" aria-labelledby="modalVisualizarChecklistLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg"> <!-- Aumentando um pouco o tamanho do modal -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVisualizarChecklistLabel">
                    <i class="fa fa-eye"></i> Visualizar Checklist
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="card-checklist">
                    <h4 class="text-primary font-weight-bold" id="tituloChecklist"></h4>

                    <h5 class="mt-3 font-weight-bold"><i class="fa fa-list-ul"></i> Itens do Checklist:</h5>
                    <ul class="list-group" id="listaVisualizarItens"></ul>

                    <div id="observacoesChecklistContainer" class="mt-2">
                        <h5 class="text-dark font-weight-bold"><i class="fa fa-sticky-note"></i> Observações:</h5>
                        <p id="observacoesChecklist" class="text-black"></p>
                    </div>

                </div>

                <button class="btn btn-secondary mt-3" onclick="imprimirChecklistTCPDF()">
                    <i class="fa fa-print"></i> Imprimir (PDF)
                </button>
            </div>
        </div>
    </div>
</div>


<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/sweetalert2.js"></script>

<script>
// Variável para armazenar o ID do checklist visualizado atualmente
let currentChecklistId = null;

// Adicionar item ao checklist
function adicionarItem() {
    let input = $('#novo_item').val().trim();

    if (input === "") {
        Swal.fire('Aviso', 'Informe um item válido!', 'warning');
        return;
    }

    let itens = input.split(';').map(item => item.trim()).filter(item => item !== "");

    if (itens.length === 0) {
        Swal.fire('Aviso', 'Nenhum item válido foi inserido!', 'warning');
        return;
    }

    itens.forEach(function(item) {
        $('#listaItens').append(`
            <li class="list-group-item d-flex justify-content-between align-items-center">
                ${item} 
                <button class="btn btn-delete btn-sm" onclick="removerItem(this)">
                    <i class="fa fa-trash"></i>
                </button>
            </li>
        `);
    });

    $('#novo_item').val('');
}

// Remover item do checklist
function removerItem(button) {
    $(button).closest('li').remove();
}

// Salvar checklist (Criar ou Editar)
function salvarChecklist() {
    let id = $('#checklist_id').val();
    let titulo = $('#titulo').val().trim();
    let observacoes = $('#observacoes').val().trim();
    let itens = [];

    $('#listaItens li').each(function() {
        itens.push($(this).text().trim());
    });

    if (titulo === "" || itens.length === 0) {
        Swal.fire('Erro', 'Preencha o título e adicione pelo menos um item!', 'error');
        return;
    }

    $.ajax({
        url: id ? 'editar_checklist.php' : 'salvar_checklist.php',
        type: 'POST',
        data: {
            id: id,
            titulo: titulo,
            observacoes: observacoes,
            itens: JSON.stringify(itens)
        },
        dataType: 'json',
        success: function(response) {
            Swal.fire('Sucesso', response.message, 'success');
            $('#formChecklist')[0].reset();
            $('#listaItens').empty();
            $('#checklist_id').val('');
            carregarChecklists();
        }
    });
}

// Carregar checklists com botões de edição
function carregarChecklists() {
    $.ajax({
        url: 'listar_checklists.php',
        type: 'GET',
        success: function(response) {
            $('#listaChecklists').html(response);
        }
    });
}


function visualizarChecklist(id) {
    $.ajax({
        url: 'carregar_checklist.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (!response || response.error) {
                Swal.fire('Erro', 'Não foi possível carregar o checklist.', 'error');
                return;
            }

            // Armazena o ID do checklist atual
            currentChecklistId = id;

            // Exibir título em caixa alta
            $('#tituloChecklist').text(response.titulo.toUpperCase());

            // Limpa e adiciona os itens com ícones ao lado
            $('#listaVisualizarItens').empty();
            response.itens.forEach(function(item) {
                $('#listaVisualizarItens').append(`
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fa fa-check-circle text-success mr-2"></i> ${item}
                    </li>
                `);
            });

            // Exibir observação (mesmo que esteja vazia)
            let observacaoTexto = response.observacoes && response.observacoes.trim() !== '' 
                ? response.observacoes 
                : 'Nenhuma observação adicionada.';
            
            $('#observacoesChecklist').text(observacaoTexto);
            $('#observacoesChecklistContainer').show(); // Sempre exibe a área da observação

            // Exibir modal
            $('#modalVisualizarChecklist').modal('show');
        },
        error: function() {
            Swal.fire('Erro', 'Erro ao buscar checklist.', 'error');
        }
    });
}



function editarChecklist(id) {
    $.ajax({
        url: 'carregar_checklist.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (!response || response.error) {
                Swal.fire('Erro', 'Não foi possível carregar o checklist.', 'error');
                return;
            }

            // Preenche o formulário com os dados do checklist
            $('#checklist_id').val(id);
            $('#titulo').val(response.titulo);
            $('#observacoes').val(response.observacoes || '');

            // Limpa a lista e adiciona os itens
            $('#listaItens').empty();
            response.itens.forEach(function(item) {
                $('#listaItens').append(`
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        ${item}
                        <button class="btn btn-danger btn-sm" onclick="removerItem(this)">
                            <i class="fa fa-trash"></i>
                        </button>
                    </li>
                `);
            });

            // Rola até o formulário para edição
            $('html, body').animate({ scrollTop: $('#formChecklist').offset().top }, 'slow');
        },
        error: function() {
            Swal.fire('Erro', 'Erro ao carregar checklist.', 'error');
        }
    });
}

// Função para marcar um checklist como removido
function excluirChecklist(id) {
    Swal.fire({
        title: "Tem certeza?",
        text: "O checklist será excluído e não poderá ser restaurado!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sim, excluir",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'excluir_checklist.php',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire("Excluído!", response.message, "success");
                        carregarChecklists(); // Atualiza a lista de checklists
                    } else {
                        Swal.fire("Erro!", response.error, "error");
                    }
                },
                error: function() {
                    Swal.fire("Erro!", "Não foi possível excluir o checklist.", "error");
                }
            });
        }
    });
}


// Abrir impressão PDF
function imprimirChecklistTCPDF() {
    if (!currentChecklistId) {
        Swal.fire('Aviso', 'Nenhum checklist selecionado!', 'error');
        return;
    }
    window.open('imprimir_checklist.php?id=' + currentChecklistId, '_blank');
}

$(document).ready(function() {
    carregarChecklists();
});
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
