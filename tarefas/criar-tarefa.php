<?php
/**
 * Atlas · Tarefas — cadastro de nova tarefa.
 *
 * O formulário continua enviando para save_task.php, com os mesmos nomes de
 * campo do módulo anterior. O que mudou: layout novo, área de anexos com
 * arrastar e soltar, e o botão "Sugerir classificação", que usa a IA para
 * propor categoria, origem, prioridade, prazo e etiquetas a partir do título
 * e da descrição digitados. A sugestão preenche os campos, mas quem decide é
 * sempre o usuário.
 */

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/gemini.php';
exigir_login();

$usuario      = usuario_atual();
$categorias   = listar_categorias();
$origens      = listar_origens();
$funcionarios = listar_funcionarios();
$prioridades  = array_keys(tarefas_prioridades());

$iaAtiva = db_tem_tabela('tarefas_ia_modelos') && ia_disponivel();
$temColunaTags = db_tem_coluna('tarefas', 'tags');
$temColunaApresentante = db_tem_coluna('tarefas', 'apresentante');

/* Prazo padrão sugerido: próximo dia útil às 17h. */
$prazoPadrao = date('Y-m-d\TH:i', strtotime('+3 weekday 17:00'));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas · Nova Tarefa</title>

    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="stylesheet" href="../style/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/tarefas.css?v=2.0.9">

    <script src="../script/jquery-3.5.1.min.js"></script>
</head>

<body class="light-mode">

<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container-fluid tf-app" style="max-width:1000px">

        <div class="tf-topo">
            <div class="tf-topo-icone"><i class="fa fa-plus"></i></div>
            <div>
                <h1>Nova tarefa</h1>
                <div class="tf-sub">O número do protocolo geral é gerado automaticamente ao salvar</div>
            </div>
            <div class="tf-topo-acoes">
                <a href="index.php" class="tf-btn">
                    <i class="fa fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <form id="formTarefa" enctype="multipart/form-data">
            <input type="hidden" name="createdBy" value="<?php echo e($usuario['usuario']); ?>">

            <div class="tf-painel" style="padding:22px;margin-bottom:16px">

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px">

                    <div style="grid-column:1/-1">
                        <label class="tf-rotulo">Título da tarefa <span style="color:var(--tf-perigo)">*</span></label>
                        <input type="text" class="tf-input" id="title" name="title" required
                               placeholder="Ex.: Registro de escritura de compra e venda — Lote 12, Quadra B">
                    </div>

                    <div style="grid-column:1/-1">
                        <label class="tf-rotulo">Descrição</label>
                        <textarea class="tf-textarea" id="description" name="description" rows="5"
                                  placeholder="Detalhe o que precisa ser feito, documentos envolvidos e observações."></textarea>
                    </div>

                    <?php if ($iaAtiva): ?>
                    <div style="grid-column:1/-1">
                        <div class="tf-ia-caixa">
                            <div class="tf-ia-titulo">
                                <i class="fa fa-magic"></i> Classificação assistida
                            </div>
                            <p class="tf-mini tf-mudo" style="margin:0 0 10px">
                                A IA lê o título e a descrição e sugere categoria, origem, prioridade,
                                prazo e etiquetas. Você pode alterar tudo depois.
                            </p>
                            <button type="button" class="tf-btn tf-btn-sm tf-btn-ia" id="btnSugerir">
                                <i class="fa fa-magic"></i> Sugerir classificação
                            </button>
                            <div class="tf-ia-saida" id="saidaSugestao" style="margin-top:12px"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="tf-rotulo">Categoria <span style="color:var(--tf-perigo)">*</span></label>
                        <select class="tf-select" id="category" name="category" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?php echo e($c['id']); ?>"><?php echo e($c['titulo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="tf-rotulo">Origem <span style="color:var(--tf-perigo)">*</span></label>
                        <select class="tf-select" id="origin" name="origin" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($origens as $o): ?>
                                <option value="<?php echo e($o['id']); ?>"><?php echo e($o['titulo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="tf-rotulo">Data limite <span style="color:var(--tf-perigo)">*</span></label>
                        <input type="datetime-local" class="tf-input" id="deadline" name="deadline"
                               value="<?php echo e($prazoPadrao); ?>" required>
                    </div>

                    <div>
                        <label class="tf-rotulo">Prioridade <span style="color:var(--tf-perigo)">*</span></label>
                        <select class="tf-select" id="priority" name="priority" required>
                            <?php foreach ($prioridades as $p): ?>
                                <option value="<?php echo e($p); ?>"<?php echo $p === 'Média' ? ' selected' : ''; ?>>
                                    <?php echo e($p); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="tf-rotulo">Funcionário responsável <span style="color:var(--tf-perigo)">*</span></label>
                        <select class="tf-select" id="employee" name="employee" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($funcionarios as $f): ?>
                                <option value="<?php echo e($f['nome_completo']); ?>"
                                    <?php echo $f['nome_completo'] === $usuario['nome'] ? ' selected' : ''; ?>>
                                    <?php echo e($f['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="tf-rotulo">Revisor</label>
                        <select class="tf-select" id="reviewer" name="reviewer">
                            <option value="">Nenhum</option>
                            <?php foreach ($funcionarios as $f): ?>
                                <option value="<?php echo e($f['nome_completo']); ?>"><?php echo e($f['nome_completo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($temColunaApresentante): ?>
                    <div>
                        <label class="tf-rotulo">Apresentante</label>
                        <input type="text" class="tf-input" id="apresentante" name="apresentante"
                               placeholder="Quem trouxe o título/documento">
                    </div>
                    <?php endif; ?>

                    <?php if ($temColunaTags): ?>
                    <div>
                        <label class="tf-rotulo">Etiquetas</label>
                        <input type="text" class="tf-input" id="tags" name="tags"
                               placeholder="separadas por vírgula">
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Anexos -->
            <div class="tf-painel" style="padding:22px;margin-bottom:16px">
                <h3 style="font-size:.95rem;font-weight:650;margin:0 0 14px">
                    <i class="fa fa-paperclip"></i> Anexos
                </h3>

                <div class="tf-zona-upload" id="zonaUpload">
                    <i class="fa fa-cloud-upload"></i>
                    <strong>Arraste arquivos aqui</strong> ou clique para selecionar
                    <div class="tf-mini tf-mudo" style="margin-top:6px">
                        PDF, imagens, Office, ZIP e afins — até <?php echo TAREFAS_UPLOAD_MAX_MB; ?> MB por arquivo
                    </div>
                    <input type="file" id="attachments" name="attachments[]" multiple style="display:none">
                </div>

                <div class="tf-anexos" id="listaArquivos" style="margin-top:14px"></div>
            </div>

            <?php if ($iaAtiva && db_tem_tabela('tarefas_checklist')): ?>
            <div class="tf-painel" style="padding:16px 22px;margin-bottom:16px">
                <label style="display:flex;gap:10px;align-items:center;margin:0;cursor:pointer">
                    <input type="checkbox" id="gerarChecklist" name="gerar_checklist" value="1"
                           style="width:17px;height:17px;accent-color:var(--tf-roxo)">
                    <span>
                        <strong>Gerar checklist de conferência com a IA</strong>
                        <div class="tf-mini tf-mudo">
                            Cria automaticamente as etapas de conferência da tarefa. Você pode editar depois.
                        </div>
                    </span>
                </label>
            </div>
            <?php endif; ?>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-bottom:30px;flex-wrap:wrap">
                <a href="index.php" class="tf-btn"><i class="fa fa-times"></i> Cancelar</a>
                <button type="submit" class="tf-btn tf-btn-primario" id="btnSalvar">
                    <i class="fa fa-save"></i> Salvar tarefa
                </button>
            </div>
        </form>

    </div>
</div>

<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="../script/toastr.min.js"></script>

<script>
jQuery(function ($) {
    'use strict';

    var CSRF = <?php echo json_encode(csrf_token()); ?>;
    var arquivos = [];

    /** Substitui $.trim, removido no jQuery 4. */
    function txt(v) {
        return (v === null || v === undefined) ? '' : String(v).trim();
    }

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function tamanho(bytes) {
        if (!bytes) { return '—'; }
        var u = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(i ? 1 : 0) + ' ' + u[i];
    }

    /* ---------------------- Anexos ---------------------- */

    function desenharArquivos() {
        var $l = $('#listaArquivos').empty();
        arquivos.forEach(function (f, i) {
            $l.append('<div class="tf-anexo">'
                + '<div class="tf-anexo-icone"><i class="fa fa-file-o"></i></div>'
                + '<div class="tf-anexo-nome">' + esc(f.name)
                + '<small>' + tamanho(f.size) + '</small></div>'
                + '<button type="button" class="tf-btn tf-btn-sm" data-remover="' + i + '">'
                + '<i class="fa fa-times"></i></button></div>');
        });
    }

    function adicionar(lista) {
        var limite = <?php echo TAREFAS_UPLOAD_MAX_MB; ?> * 1024 * 1024;
        var recusados = [];

        Array.prototype.forEach.call(lista, function (f) {
            if (f.size > limite) { recusados.push(f.name); return; }
            arquivos.push(f);
        });

        if (recusados.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Arquivo muito grande',
                text: recusados.join(', ') + ' — o limite é <?php echo TAREFAS_UPLOAD_MAX_MB; ?> MB por arquivo.'
            });
        }
        desenharArquivos();
    }

    /*
     * O <input type=file> fica DENTRO da zona clicável. Sem ignorar o clique
     * que vem dele, o clique no input sobe para a zona, que dispara outro
     * clique no input, e assim por diante — é o "Maximum call stack size
     * exceeded" que aparecia no console ao anexar arquivos.
     */
    $('#zonaUpload').on('click', function (e) {
        if (e.target === document.getElementById('attachments')) { return; }
        $('#attachments').trigger('click');
    });
    $('#attachments').on('change', function () { adicionar(this.files); this.value = ''; });

    $('#zonaUpload')
        .on('dragover', function (e) { e.preventDefault(); $(this).addClass('tf-sobre'); })
        .on('dragleave', function () { $(this).removeClass('tf-sobre'); })
        .on('drop', function (e) {
            e.preventDefault();
            $(this).removeClass('tf-sobre');
            adicionar(e.originalEvent.dataTransfer.files);
        });

    $('#listaArquivos').on('click', '[data-remover]', function () {
        arquivos.splice(parseInt($(this).data('remover'), 10), 1);
        desenharArquivos();
    });

    /* ---------------------- Sugestão da IA ---------------------- */

    $('#btnSugerir').on('click', function () {
        var titulo = txt($('#title').val());
        var descricao = txt($('#description').val());

        if (!titulo && !descricao) {
            Swal.fire({ icon: 'info', title: 'Escreva primeiro',
                text: 'Preencha ao menos o título para a IA sugerir a classificação.' });
            return;
        }

        var $b = $(this).prop('disabled', true);
        $('#saidaSugestao').html('<i class="fa fa-circle-o-notch tf-girando"></i> Analisando…');

        $.ajax({
            url: 'api/ia.php',
            type: 'POST',
            dataType: 'json',
            data: { _csrf: CSRF, recurso: 'classificar', titulo: titulo, descricao: descricao }
        })
            .done(function (r) {
                $b.prop('disabled', false);
                if (!r.success) {
                    $('#saidaSugestao').html('<span style="color:var(--tf-perigo)">' + esc(r.error) + '</span>');
                    return;
                }

                var d = r.dados || {};
                var aplicados = [];

                if (d.categoria_id) { $('#category').val(d.categoria_id); aplicados.push('categoria'); }
                if (d.origem_id)    { $('#origin').val(d.origem_id);      aplicados.push('origem'); }
                if (d.prioridade)   { $('#priority').val(d.prioridade);   aplicados.push('prioridade'); }
                if (d.prazo_sugerido) { $('#deadline').val(d.prazo_sugerido); aplicados.push('prazo'); }
                if (d.tags && d.tags.length && $('#tags').length) {
                    $('#tags').val(d.tags.join(', '));
                    aplicados.push('etiquetas');
                }

                var html = '';
                if (aplicados.length) {
                    html += '<strong>Campos preenchidos:</strong> ' + aplicados.join(', ') + '.<br>';
                } else {
                    html += '<strong>A IA não conseguiu casar as sugestões com as opções cadastradas.</strong><br>';
                }
                if (d.justificativa) {
                    html += '<span class="tf-mudo">' + esc(d.justificativa) + '</span>';
                }
                if (d.prazo_sugerido_br) {
                    html += '<br><span class="tf-mini tf-mudo">Prazo sugerido: ' + esc(d.prazo_sugerido_br) + '</span>';
                }
                html += '<div class="tf-mini tf-mudo" style="margin-top:8px">'
                     + '<i class="fa fa-info-circle"></i> Confira e ajuste antes de salvar.</div>';

                $('#saidaSugestao').html(html);
            })
            .fail(function (xhr) {
                $b.prop('disabled', false);
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Não foi possível consultar a IA.';
                $('#saidaSugestao').html('<span style="color:var(--tf-perigo)">' + esc(msg) + '</span>');
            });
    });

    /* ---------------------- Envio ---------------------- */

    $('#formTarefa').on('submit', function (e) {
        e.preventDefault();

        var $b = $('#btnSalvar').prop('disabled', true)
            .html('<i class="fa fa-circle-o-notch tf-girando"></i> Salvando…');

        var fd = new FormData();
        fd.append('_csrf', CSRF);
        fd.append('acao', 'criar');

        $(this).find('input[type=text], input[type=hidden], input[type=datetime-local], select, textarea')
            .each(function () {
                var n = $(this).attr('name');
                if (n) { fd.append(n, $(this).val()); }
            });

        if ($('#gerarChecklist').is(':checked')) {
            fd.append('gerar_checklist', '1');
        }

        arquivos.forEach(function (f) { fd.append('attachments[]', f); });

        $.ajax({
            url: 'api/acoes.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
            .done(function (r) {
                if (!r.success) {
                    $b.prop('disabled', false).html('<i class="fa fa-save"></i> Salvar tarefa');
                    Swal.fire({ icon: 'error', title: 'Erro', text: r.error });
                    return;
                }

                var aviso = (r.avisos && r.avisos.length)
                    ? '\n\nAtenção: ' + r.avisos.join(' ') : '';

                Swal.fire({
                    icon: 'success',
                    title: 'Tarefa criada',
                    text: 'Protocolo geral nº ' + r.id + '.' + aviso,
                    confirmButtonText: 'Abrir tarefa',
                    showCancelButton: true,
                    cancelButtonText: 'Criar outra',
                    reverseButtons: true
                }).then(function (res) {
                    if (res.isConfirmed) {
                        window.location.href = 'index.php?token=' + encodeURIComponent(r.token);
                    } else {
                        window.location.reload();
                    }
                });
            })
            .fail(function (xhr) {
                $b.prop('disabled', false).html('<i class="fa fa-save"></i> Salvar tarefa');
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Não foi possível salvar a tarefa.';
                Swal.fire({ icon: 'error', title: 'Erro', text: msg });
            });
    });
});
</script>

</body>
</html>
