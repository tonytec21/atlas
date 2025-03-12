<?php  
ob_start();  
if (session_status() === PHP_SESSION_NONE) {  
    session_start();  
}  
?>  
<!DOCTYPE html>  
<html lang="pt-BR">  
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
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">  
    <style>  
        .swal2-styled {
            margin-top: 10px!important;
        }
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
        .ui-state-highlight {  
            height: 45px;  
            background: #f8f9fa;  
            border: 1px dashed #007bff;  
            border-radius: 5px;  
        }  
        .item-list li {  
            cursor: move;  
        }  
        .item-group-header {  
            background-color: #e9ecef;  
            font-weight: bold;  
            border-left: 4px solid #007bff;  
        }  
        .item-text {  
            cursor: pointer;  
            padding: 2px 5px;  
            border-radius: 3px;  
            transition: background-color 0.2s;  
            flex-grow: 1;  
            user-select: text;  
            -webkit-user-select: text;  
            -moz-user-select: text;  
        }  
        .item-text:hover {  
            background-color: #f5f5f5;  
        }  
        .item-text.editing {  
            background-color: #fff;  
            border: 1px solid #ced4da;  
            padding: 5px;  
            min-height: 2.5em;  
            outline: none;  
            display: block;  
            width: 100%;  
            cursor: text;  
            line-height: 1.5;  
            white-space: pre-wrap;  
            word-break: break-word;  
        }  
        .list-group-item.editing {  
            background-color: #f8f9fa;  
            border-color: #007bff;  
        }  
        [contenteditable=true] {  
            cursor: text;  
            -webkit-user-select: text;  
            user-select: text;  
        }  
        .item-text.editing::selection {  
            background-color: #b2d7ff;  
        }  
        .grupo-container {  
            margin-bottom: 20px;  
            border: 1px solid #dee2e6;  
            border-radius: 8px;  
            overflow: hidden;  
        }  
        .grupo-header {  
            background-color: #e9ecef;  
            border-left: 4px solid #007bff;  
            padding: 10px 15px;  
            display: flex;  
            justify-content: space-between;  
            align-items: center;  
        }  
        .grupo-titulo {  
            font-weight: bold;  
            font-size: 1.1rem;  
            cursor: pointer;  
            flex-grow: 1;  
            padding: 2px 5px;  
            border-radius: 3px;  
            user-select: text;  
            -webkit-user-select: text;  
            -moz-user-select: text;  
        }  
        .grupo-titulo:hover {  
            background-color: #f5f5f5;  
        }  
        .grupo-titulo.editing {  
            background-color: #fff;  
            border: 1px solid #ced4da;  
            padding: 5px;  
            outline: none;  
        }  
        .grupo-info {  
            flex-grow: 1;  
        }  
        .grupo-obs {  
            font-size: 0.85rem;  
            color: #6c757d;  
            margin-top: 5px;  
            padding: 2px 5px;  
            border-radius: 3px;  
            width: 100%;  
            user-select: text;  
            -webkit-user-select: text;  
            -moz-user-select: text;  
        }  
        .grupo-obs:hover {  
            background-color: #f5f5f5;  
        }  
        .grupo-obs.editing {  
            background-color: #fff;  
            border: 1px solid #ced4da;  
            padding: 5px;  
            outline: none;  
        }  
        .grupo-edit-buttons {  
            display: flex;  
            gap: 5px;  
        }  
        .grupo-items {  
            padding: 10px 15px;  
        }  
        .sem-grupo {  
            margin-bottom: 20px;  
        }  
        .sem-grupo-header {  
            font-weight: bold;  
            margin-bottom: 10px;  
            padding: 10px;  
            background-color: #f8f9fa;  
            border-radius: 5px;  
        }  
        .add-grupo-btn {  
            margin: 15px 0;  
        }  
        .grupo-handle {  
            cursor: move;  
            color: #6c757d;  
            padding: 0 10px;  
        }  
    </style>  
</head>  
<body>  
<?php include(__DIR__ . '/../menu.php'); ?>  

<div id="main" class="main-content">  
    <div class="container">  
        <div class="d-flex justify-content-between align-items-center">  
        <h3>Gerenciar Checklists</h3>  
            <a href="../guia_de_recebimento/index.php" class="btn btn-secondary">  
                <i class="fa fa-search" aria-hidden="true"></i> Guias de Recebimento  
            </a>  
        </div>  
    <hr>  

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

                <div class="form-group">  
                    <label for="observacoes">Observações</label>  
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="2"></textarea>  
                </div>  

                <button type="button" class="btn btn-outline-primary add-grupo-btn" onclick="adicionarNovoGrupo()">  
                    <i class="fa fa-folder"></i> Adicionar Novo Grupo  
                </button>  

                <div id="grupos-container">  
                </div>  

                <div id="itens-sem-grupo-container" class="sem-grupo">  
                    <div class="sem-grupo-header">Itens sem grupo</div>  
                    <ul class="list-group item-list" id="itens-sem-grupo"></ul>  
                </div>  

                <button type="button" class="btn btn-primary btn-block mt-3" onclick="salvarChecklist()">  
                    <i class="fa fa-save"></i> Salvar Checklist  
                </button>  
            </form>  
        </div>  
    </div>  

    <div class="card">  
        <div class="card-header">Checklists Salvos</div>  
        <div class="card-body" id="listaChecklists">  
        </div>  
    </div>  
</div>  
</div>  

<div class="modal fade" id="modalVisualizarChecklist" tabindex="-1" aria-labelledby="modalVisualizarChecklistLabel" aria-hidden="true">  
    <div class="modal-dialog modal-xxl">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="modalVisualizarChecklistLabel">  
                    <i class="fa fa-eye"></i> Visualizar Checklist  
                </h5>  
                <button type="button" class="btn-close text-white" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">  
                    &times;  
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

<div class="modal fade" id="modalGrupoItens" tabindex="-1" aria-labelledby="modalGrupoItensLabel" aria-hidden="true">  
    <div class="modal-dialog">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="modalGrupoItensLabel">Editar Grupo</h5>  
                <button type="button" class="btn-close text-white" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">  
                    &times;  
                </button>  
            </div>  
            <div class="modal-body">  
                <input type="hidden" id="grupo_id" value="">  
                
                <div class="form-group">  
                    <label for="grupo_titulo">Título do Grupo</label>  
                    <input type="text" class="form-control" id="grupo_titulo" required>  
                </div>  
                
                <div class="form-group">  
                    <label for="grupo_observacoes">Observações</label>  
                    <textarea class="form-control" id="grupo_observacoes" rows="3"></textarea>  
                </div>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>  
                <button type="button" class="btn btn-primary" onclick="salvarGrupo()">Salvar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<div class="modal fade" id="modalSelecionarGrupo" tabindex="-1" aria-labelledby="modalSelecionarGrupoLabel" aria-hidden="true">  
    <div class="modal-dialog">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="modalSelecionarGrupoLabel">Selecionar Grupo</h5>  
                <button type="button" class="btn-close text-white" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">  
                    &times;  
                </button>  
            </div>  
            <div class="modal-body">  
                <p>Selecione um grupo para adicionar os itens ou crie um novo grupo:</p>  
                <div id="lista-grupos-modal"></div>  
                <hr>  
                <div class="form-group">  
                    <label for="novo_grupo_titulo">Ou crie um novo grupo:</label>  
                    <input type="text" class="form-control" id="novo_grupo_titulo" placeholder="Título do novo grupo">  
                </div>  
                <div class="form-group">  
                    <label for="novo_grupo_obs">Observações (opcional):</label>  
                    <textarea class="form-control" id="novo_grupo_obs" rows="2"></textarea>  
                </div>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>  
                <button type="button" class="btn btn-primary" onclick="confirmarSelecaoGrupo()">Confirmar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<script src="../script/jquery-3.5.1.min.js"></script>  
<script src="../script/bootstrap.bundle.min.js"></script>  
<script src="../script/jquery.mask.min.js"></script>  
<script src="../script/sweetalert2.js"></script>  
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>  

<script>  
let currentChecklistId = null;  
let itensAdicionarGrupo = [];  
let grupos = [];  
let proximoGrupoId = 1;  

function gerarGrupoId() {  
    return 'grupo_' + proximoGrupoId++;  
}  

function atualizarSortableGrupo() {  
    $('.grupo-items .item-list:empty').each(function() {  
        $(this).html('<li class="placeholder-item" style="height: 35px; border: 1px dashed #ccc; border-radius: 5px; background: transparent; list-style: none; margin: 5px 0;"></li>');  
    });  
    
    if ($('#itens-sem-grupo:empty').length) {  
        $('#itens-sem-grupo').html('<li class="placeholder-item" style="height: 35px; border: 1px dashed #ccc; border-radius: 5px; background: transparent; list-style: none; margin: 5px 0;"></li>');  
    }  
    
    $('.grupo-items .item-list').sortable({  
        placeholder: "ui-state-highlight",  
        connectWith: ".item-list",  
        cancel: ".editing, .item-text",  
        tolerance: "pointer",  
        receive: function(event, ui) {  
            $(this).find('.placeholder-item').remove();  
        },  
        over: function(event, ui) {  
            $(this).find('.placeholder-item').remove();  
        },  
        remove: function(event, ui) {  
            if ($(this).children().not('.placeholder-item').length === 0) {  
                $(this).html('<li class="placeholder-item" style="height: 35px; border: 1px dashed #ccc; border-radius: 5px; background: transparent; list-style: none; margin: 5px 0;"></li>');  
            }  
        },  
        update: function(event, ui) {  
            verificarListasVazias();  
        },  
        start: function(event, ui) {  
            $('.item-text, .grupo-titulo, .grupo-obs').attr('contenteditable', 'false');  
        }  
    });  
    
    $('#itens-sem-grupo').sortable({  
        placeholder: "ui-state-highlight",  
        connectWith: ".item-list",  
        cancel: ".editing, .item-text",  
        tolerance: "pointer",  
        receive: function(event, ui) {  
            $(this).find('.placeholder-item').remove();  
        },  
        over: function(event, ui) {  
            $(this).find('.placeholder-item').remove();  
        },  
        remove: function(event, ui) {  
            if ($(this).children().not('.placeholder-item').length === 0) {  
                $(this).html('<li class="placeholder-item" style="height: 35px; border: 1px dashed #ccc; border-radius: 5px; background: transparent; list-style: none; margin: 5px 0;"></li>');  
            }  
        },  
        update: function(event, ui) {  
            verificarListasVazias();  
        },  
        start: function(event, ui) {  
            $('.item-text, .grupo-titulo, .grupo-obs').attr('contenteditable', 'false');  
        }  
    });  
    
    $('#grupos-container').sortable({  
        placeholder: "ui-state-highlight",  
        handle: ".grupo-handle",  
        cancel: ".editing, .grupo-titulo, .grupo-obs",  
        items: ".grupo-container",  
        tolerance: "pointer"  
    });  
}  

function verificarListasVazias() {  
    $('.grupo-items .item-list').each(function() {  
        if ($(this).children().not('.placeholder-item').length === 0) {  
            if ($(this).find('.placeholder-item').length === 0) {  
                $(this).html('<li class="placeholder-item" style="height: 35px; border: 1px dashed #ccc; border-radius: 5px; background: transparent; list-style: none; margin: 5px 0;"></li>');  
            }  
        }  
    });  
    
    if ($('#itens-sem-grupo').children().not('.placeholder-item').length === 0 &&   
        $('#itens-sem-grupo').find('.placeholder-item').length === 0) {  
        $('#itens-sem-grupo').html('<li class="placeholder-item" style="height: 35px; border: 1px dashed #ccc; border-radius: 5px; background: transparent; list-style: none; margin: 5px 0;"></li>');  
    }  
}  

function adicionarNovoGrupoComItens(grupoId, titulo, observacoes, itens = []) {  
    const grupoHtml = `  
        <div class="grupo-container" data-grupo-id="${grupoId}">  
            <div class="grupo-header">  
                <div class="grupo-handle"><i class="fa fa-bars"></i></div>  
                <div class="grupo-info">  
                    <div class="grupo-titulo" onclick="editarTextoInline(this)">${titulo}</div>  
                    <div class="grupo-obs" onclick="editarTextoInline(this)">${observacoes || 'Sem observações'}</div>  
                </div>  
                <div class="grupo-edit-buttons">  
                    <button type="button" class="btn btn-sm btn-outline-danger btn-excluir-grupo" data-grupo-id="${grupoId}">  
                        <i class="fa fa-trash"></i>  
                    </button>  
                </div>  
            </div>  
            <div class="grupo-items">  
                <ul class="list-group item-list"></ul>  
            </div>  
        </div>  
    `;   
    
    $('#grupos-container').append(grupoHtml);  
    
    if (!itens || itens.length === 0) {  
        $(`.grupo-container[data-grupo-id="${grupoId}"] .item-list`).html('<li class="placeholder-item" style="height: 35px; border: 1px dashed #ccc; border-radius: 5px; background: transparent; list-style: none; margin: 5px 0;"></li>');  
    } else {  
        adicionarItensAoGrupo(grupoId, itens);  
    }  
}  

function removerItem(button) {  
    const $lista = $(button).closest('.item-list');  
    $(button).closest('li').remove();  
    
    if ($lista.children().length === 0) {  
        $lista.html('<li class="placeholder-item" style="height: 35px; border: 1px dashed #ccc; border-radius: 5px; background: transparent; list-style: none; margin: 5px 0;"></li>');  
    }  
}  

function salvarGrupo() {  
    const grupoId = $('#grupo_id').val() || gerarGrupoId();  
    const titulo = $('#grupo_titulo').val().trim();  
    const observacoes = $('#grupo_observacoes').val().trim();  
    
    if (!titulo) {  
        Swal.fire('Aviso', 'O título do grupo é obrigatório!', 'warning');  
        return;  
    }  
    
    const grupoExistente = grupos.find(g => g.id === grupoId);  
    
    if (grupoExistente) {  
        grupoExistente.titulo = titulo;  
        grupoExistente.observacoes = observacoes;  
        
        $(`.grupo-container[data-grupo-id="${grupoId}"] .grupo-titulo`).text(titulo);  
        $(`.grupo-container[data-grupo-id="${grupoId}"] .grupo-obs`).text(observacoes || 'Sem observações');  
    } else {  
        grupos.push({  
            id: grupoId,  
            titulo: titulo,  
            observacoes: observacoes  
        });  
        
        adicionarNovoGrupoComItens(grupoId, titulo, observacoes, []);  
    }  
    
    $('#modalGrupoItens').modal('hide');  
    
    atualizarSortableGrupo();  
    verificarListasVazias();  
}  

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

    if (itens.length > 1) {  
        itensAdicionarGrupo = itens;  
        preparaModalSelecionarGrupo();  
        $('#modalSelecionarGrupo').modal('show');  
    } else {  
        adicionarItemSemGrupo(itens[0]);  
    }  

    $('#novo_item').val('');  
}  

function preparaModalSelecionarGrupo() {  
    let html = '<div class="list-group">';  
    
    if (grupos.length > 0) {  
        grupos.forEach(grupo => {  
            html += `<a href="#" class="list-group-item list-group-item-action grupo-item"   
                        data-grupo-id="${grupo.id}" onclick="selecionarGrupoExistente('${grupo.id}'); return false;">  
                        <i class="fa fa-folder-open text-primary"></i> ${grupo.titulo}  
                    </a>`;  
        });  
    } else {  
        html += '<p class="text-muted">Nenhum grupo existente.</p>';  
    }  
    
    html += '</div>';  
    
    $('#lista-grupos-modal').html(html);  
    $('#novo_grupo_titulo').val('');  
    $('#novo_grupo_obs').val('');  
}  

function selecionarGrupoExistente(grupoId) {  
    $('.grupo-item').removeClass('active');  
    $(`.grupo-item[data-grupo-id="${grupoId}"]`).addClass('active');  
    $('#novo_grupo_titulo').val('');  
    $('#novo_grupo_obs').val('');  
}  

function confirmarSelecaoGrupo() {  
    const grupoSelecionado = $('.grupo-item.active').data('grupo-id');  
    const novoGrupoTitulo = $('#novo_grupo_titulo').val().trim();  
    const novoGrupoObs = $('#novo_grupo_obs').val().trim();  
    
    if (grupoSelecionado) {  
        adicionarItensAoGrupo(grupoSelecionado, itensAdicionarGrupo);  
    } else if (novoGrupoTitulo) {  
        const novoGrupoId = gerarGrupoId();  
        
        grupos.push({  
            id: novoGrupoId,  
            titulo: novoGrupoTitulo,  
            observacoes: novoGrupoObs  
        });  
        
        adicionarNovoGrupoComItens(novoGrupoId, novoGrupoTitulo, novoGrupoObs, itensAdicionarGrupo);  
    } else {  
        itensAdicionarGrupo.forEach(item => {  
            adicionarItemSemGrupo(item);  
        });  
    }  
    
    $('#modalSelecionarGrupo').modal('hide');  
    itensAdicionarGrupo = [];  
    
    atualizarSortableGrupo();  
    ativarEdicaoInline();  
}  

function adicionarItensAoGrupo(grupoId, itens) {  
    const $lista = $(`.grupo-container[data-grupo-id="${grupoId}"] .item-list`);  
    
    if (!$lista.length) {  
        console.error('Grupo não encontrado:', grupoId);  
        return;  
    }  
    
    itens.forEach(item => {  
        $lista.append(`  
            <li class="list-group-item d-flex justify-content-between align-items-center">  
                <span class="item-text" contenteditable="false">${item}</span>  
                <div class="btn-group">  
                    <button class="btn btn-delete btn-sm" onclick="removerItem(this)">  
                        <i class="fa fa-trash"></i>  
                    </button>  
                </div>  
            </li>  
        `);  
    });  
    
    $lista.find('.placeholder-item').remove();  
}  

function adicionarItemSemGrupo(texto) {  
    if (!texto || texto.trim() === '') {  
        return;  
    }  
    
    $('#itens-sem-grupo').find('.placeholder-item').remove();  
    
    $('#itens-sem-grupo').append(`  
        <li class="list-group-item d-flex justify-content-between align-items-center">  
            <span class="item-text" contenteditable="false">${texto}</span>  
            <div class="btn-group">  
                <button class="btn btn-delete btn-sm" onclick="removerItem(this)">  
                    <i class="fa fa-trash"></i>  
                </button>  
            </div>  
        </li>  
    `);  
    
    ativarEdicaoInline();  
}  

function adicionarNovoGrupo() {  
    $('#grupo_id').val('');  
    $('#grupo_titulo').val('');  
    $('#grupo_observacoes').val('');  
    $('#modalGrupoItensLabel').text('Adicionar Novo Grupo');  
    $('#modalGrupoItens').modal('show');  
}  

function editarTextoInline(element) {  
    const $element = $(element);  
    $('.item-list').sortable('disable');  
    $('#grupos-container').sortable('disable');  
    
    setTimeout(function() {  
        iniciarEdicaoInline($element);  
    }, 10);  
}  

function ativarEdicaoInline() {  
    $('.item-text, .grupo-titulo, .grupo-obs').off('click').on('click', function(e) {  
        if (!$(this).attr('contenteditable') || $(this).attr('contenteditable') === 'false') {  
            $('.item-list').sortable('disable');  
            $('#grupos-container').sortable('disable');  
            
            iniciarEdicaoInline(this);  
            e.stopPropagation();  
        }  
    });  
}  

function iniciarEdicaoInline(element) {  
    const $element = $(element);  
    const textoOriginal = $element.text();  
    
    $element.addClass('editing')  
            .attr('contenteditable', 'true');  
    
    if ($element.hasClass('item-text')) {  
        $element.closest('li').addClass('editing');  
    }  
    
    $element.data('original-text', textoOriginal);  
    
    // Foca no elemento sem selecionar todo o texto  
    $element.focus();  
    
    // Adiciona tratamento especial para cliques dentro do elemento editável  
    $element.on('click', function(e) {  
        e.stopPropagation();  
    });  
    
    $element.on('keydown', function(e) {  
        if (e.key === 'Escape') {  
            $element.text(textoOriginal);  
            finalizarEdicaoInline($element);  
            e.preventDefault();  
        } else if (e.key === 'Enter' && !e.shiftKey) {  
            finalizarEdicaoInline($element);  
            e.preventDefault();  
        }  
    });  
    
    $element.on('blur', function() {  
        finalizarEdicaoInline($element);  
    });  
    
    $element.on('mousedown mousemove', function(e) {  
        e.stopPropagation();  
    });  
}

function finalizarEdicaoInline(element) {  
    const $element = $(element);  
    
    // Remove todos os eventos, incluindo o click adicionado  
    $element.off('keydown blur mousedown mousemove click');  
    
    $element.removeClass('editing')  
            .attr('contenteditable', 'false');  
    
    if ($element.hasClass('item-text')) {  
        $element.closest('li').removeClass('editing');  
    }  
    
    if ($element.text().trim() === '') {  
        $element.text($element.data('original-text'));  
    }  
    
    if ($element.hasClass('grupo-titulo') || $element.hasClass('grupo-obs')) {  
        const grupoId = $element.closest('.grupo-container').data('grupo-id');  
        const grupo = grupos.find(g => g.id === grupoId);  
        
        if (grupo) {  
            if ($element.hasClass('grupo-titulo')) {  
                grupo.titulo = $element.text().trim();  
            } else if ($element.hasClass('grupo-obs')) {  
                grupo.observacoes = $element.text().trim();  
            }  
        }  
    }  
    
    setTimeout(function() {  
        $('.item-list').sortable('enable');  
        $('#grupos-container').sortable('enable');  
    }, 100);  
}

function excluirGrupo(grupoId) {  
    grupoId = String(grupoId).replace(/['"]/g, '');  
    
    Swal.fire({  
        title: 'Tem certeza?',  
        text: 'O que deseja fazer com os itens deste grupo?',  
        icon: 'warning',  
        showDenyButton: true,  
        showCancelButton: true,  
        confirmButtonText: 'Excluir grupo e itens',  
        denyButtonText: 'Mover itens para "Sem grupo"',  
        cancelButtonText: 'Cancelar'  
    }).then((result) => {  
        if (result.isConfirmed) {  
            grupos = grupos.filter(g => g.id !== grupoId);  
            $(`.grupo-container[data-grupo-id="${grupoId}"]`).remove();  
            console.log('Grupo excluído:', grupoId);  
        } else if (result.isDenied) {  
            const $itens = $(`.grupo-container[data-grupo-id="${grupoId}"] .item-list li`).not('.placeholder-item').detach();  
            $('#itens-sem-grupo').find('.placeholder-item').remove();  
            $('#itens-sem-grupo').append($itens);  
            
            grupos = grupos.filter(g => g.id !== grupoId);  
            $(`.grupo-container[data-grupo-id="${grupoId}"]`).remove();  
            console.log('Grupo removido e itens movidos para "Sem grupo":', grupoId);  
        }  
        verificarListasVazias();  
    });  
}  

function salvarChecklist() {  
    let id = $('#checklist_id').val();  
    let titulo = $('#titulo').val().trim();  
    let observacoes = $('#observacoes').val().trim();  
    let todosItens = [];  
    
    grupos.forEach(grupo => {  
        const $grupoItems = $(`.grupo-container[data-grupo-id="${grupo.id}"] .item-list li`).not('.placeholder-item');  
        
        $grupoItems.each(function() {  
            const itemText = $(this).find('.item-text').text().trim();  
            
            todosItens.push({  
                texto: itemText,  
                titulo: 'sim',  
                tituloId: grupo.id  
            });  
        });  
    });  
    
    $('#itens-sem-grupo li').not('.placeholder-item').each(function() {  
        const itemText = $(this).find('.item-text').text().trim();  
        
        todosItens.push({  
            texto: itemText,  
            titulo: 'não',  
            tituloId: ''  
        });  
    });  

    if (titulo === "" || todosItens.length === 0) {  
        Swal.fire('Erro', 'Preencha o título e adicione pelo menos um item!', 'error');  
        return;  
    }  

    let gruposDados = grupos.map(grupo => ({  
        id: grupo.id,  
        titulo: grupo.titulo,  
        observacoes: grupo.observacoes || ''  
    }));  

    $.ajax({  
        url: id ? 'editar_checklist.php' : 'salvar_checklist.php',  
        type: 'POST',  
        data: {  
            id: id,  
            titulo: titulo,  
            observacoes: observacoes,  
            itens: JSON.stringify(todosItens),  
            grupos: JSON.stringify(gruposDados)  
        },  
        dataType: 'json',  
        success: function(response) {  
            if (response.success) {  
                Swal.fire('Sucesso', response.message, 'success');  
                $('#formChecklist')[0].reset();  
                $('#checklist_id').val('');  
                $('#grupos-container').empty();  
                $('#itens-sem-grupo').empty();  
                grupos = [];  
                proximoGrupoId = 1;  
                carregarChecklists();  
            } else {  
                Swal.fire('Erro', response.error || 'Erro ao salvar checklist', 'error');  
            }  
        },  
        error: function() {  
            Swal.fire('Erro', 'Erro ao comunicar com o servidor', 'error');  
        }  
    });  
}  

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
        url: 'carregar_checklist_completo.php',  
        type: 'GET',  
        data: { id: id },  
        dataType: 'json',  
        success: function(response) {  
            if (!response || response.error) {  
                Swal.fire('Erro', 'Não foi possível carregar o checklist.', 'error');  
                return;  
            }  

            currentChecklistId = id;  
            $('#tituloChecklist').text(response.titulo.toUpperCase());  
            $('#listaVisualizarItens').empty();  
            
            let ultimoGrupoId = null;  
            let gruposMap = {};  
            
            if (response.grupos) {  
                response.grupos.forEach(grupo => {  
                    gruposMap[grupo.id] = grupo;  
                });  
            }  
            
            response.itens.forEach(function(item, index) {  
                if (item.titulo === 'sim' && item.tituloId && gruposMap[item.tituloId] && item.tituloId !== ultimoGrupoId) {  
                    ultimoGrupoId = item.tituloId;  
                    const grupo = gruposMap[item.tituloId];  
                    
                    $('#listaVisualizarItens').append(`  
                        <li class="list-group-item item-group-header">  
                            <div>  
                                <i class="fa fa-folder-open"></i> <strong>${grupo.titulo}</strong>  
                            </div>  
                            ${grupo.observacoes ? `<small class="text-muted">${grupo.observacoes}</small>` : ''}  
                        </li>  
                    `);  
                }   
                else if (item.titulo === 'sim' && (!item.tituloId || !gruposMap[item.tituloId])) {  
                    $('#listaVisualizarItens').append(`  
                        <li class="list-group-item d-flex align-items-center bg-light">  
                            <i class="fa fa-star text-warning mr-2"></i> <strong>${item.texto}</strong>  
                        </li>  
                    `);  
                    return;  
                }  
                
                if (item.titulo !== 'sim' || (item.tituloId && gruposMap[item.tituloId])) {  
                    $('#listaVisualizarItens').append(`  
                        <li class="list-group-item d-flex align-items-center">  
                            <i class="fa fa-check-circle text-success mr-2"></i> ${item.texto}  
                        </li>  
                    `);  
                }  
            });  

            let observacaoTexto = response.observacoes && response.observacoes.trim() !== ''   
                ? response.observacoes   
                : 'Nenhuma observação adicionada.';  
            
            $('#observacoesChecklist').text(observacaoTexto);  
            $('#observacoesChecklistContainer').show();  
            $('#modalVisualizarChecklist').modal('show');  
        },  
        error: function() {  
            Swal.fire('Erro', 'Erro ao buscar checklist.', 'error');  
        }  
    });  
}  

function editarChecklist(id) {  
    $.ajax({  
        url: 'carregar_checklist_completo.php',  
        type: 'GET',  
        data: { id: id },  
        dataType: 'json',  
        success: function(response) {  
            if (!response || response.error) {  
                Swal.fire('Erro', 'Não foi possível carregar o checklist.', 'error');  
                return;  
            }  

            $('#checklist_id').val(id);  
            $('#titulo').val(response.titulo);  
            $('#observacoes').val(response.observacoes || '');  
            
            $('#grupos-container').empty();  
            $('#itens-sem-grupo').empty();  
            grupos = [];  
            proximoGrupoId = 1;  
            
            if (response.grupos && response.grupos.length > 0) {  
                response.grupos.forEach(function(grupo) {  
                    grupos.push({  
                        id: String(grupo.id),  
                        titulo: grupo.titulo,  
                        observacoes: grupo.observacoes || ''  
                    });  
                    
                    adicionarNovoGrupoComItens(String(grupo.id), grupo.titulo, grupo.observacoes || '', []);  
                });  
                
                const maxIdNum = Math.max(...grupos.map(g => {  
                    const idMatch = String(g.id).match(/grupo_(\d+)/);  
                    if (idMatch) {  
                        return parseInt(idMatch[1], 10);  
                    } else {  
                        const num = parseInt(g.id, 10);  
                        return isNaN(num) ? 0 : num;  
                    }  
                }));  
                
                proximoGrupoId = maxIdNum + 1;  
            }  
            
            let itensPorGrupo = {};  
            let itensSemGrupo = [];  
            
            if (response.itens && response.itens.length > 0) {  
                response.itens.forEach(function(item) {  
                    if (item.titulo === 'sim' && item.tituloId) {  
                        if (!itensPorGrupo[item.tituloId]) {  
                            itensPorGrupo[item.tituloId] = [];  
                        }  
                        itensPorGrupo[item.tituloId].push(item.texto);  
                    } else {  
                        itensSemGrupo.push(item.texto);  
                    }  
                });  
            }  
            
            Object.keys(itensPorGrupo).forEach(function(grupoId) {  
                const itensDoGrupo = itensPorGrupo[grupoId];  
                if (itensDoGrupo && itensDoGrupo.length > 0) {  
                    adicionarItensAoGrupo(grupoId, itensDoGrupo);  
                }  
            });  
            
            if (itensSemGrupo.length > 0) {  
                itensSemGrupo.forEach(function(item) {  
                    adicionarItemSemGrupo(item);  
                });  
            }  

            atualizarSortableGrupo();  
            ativarEdicaoInline();  
            $('html, body').animate({ scrollTop: $('#formChecklist').offset().top }, 'slow');  
        },  
        error: function() {  
            Swal.fire('Erro', 'Erro ao carregar checklist.', 'error');  
        }  
    });  
}   

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
                        carregarChecklists();  
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

function imprimirChecklistTCPDF() {  
    if (!currentChecklistId) {  
        Swal.fire('Aviso', 'Nenhum checklist selecionado!', 'error');  
        return;  
    }  
    window.open('imprimir_checklist.php?id=' + currentChecklistId, '_blank');  
}  

$(document).on('sortstop', function(event, ui) {  
    setTimeout(verificarListasVazias, 100);  
    setTimeout(ativarEdicaoInline, 200);  
});  

$(document).on('mousedown', function(e) {  
    const $target = $(e.target);  
    if (!$target.hasClass('editing') && $target.closest('.editing').length === 0) {  
        $('.item-text.editing, .grupo-titulo.editing, .grupo-obs.editing').each(function() {  
            finalizarEdicaoInline(this);  
        });  
    }  
});  

$(document).on('click', '.btn-excluir-grupo', function(e) {  
    e.preventDefault();  
    e.stopPropagation();  
    const grupoId = $(this).data('grupo-id');  
    excluirGrupo(grupoId);  
});  

$(document).ready(function() {  
    carregarChecklists();  
    atualizarSortableGrupo();  
    ativarEdicaoInline();  
});  
</script>  

<?php include(__DIR__ . '/../rodape.php'); ?>  
</body>  
</html>