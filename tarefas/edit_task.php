<?php
/**
 * Atlas · Tarefas — edição de tarefa.
 *
 * Mantém tudo que a tela anterior fazia (dados, anexos com exclusão, linha do
 * tempo de comentários com edição e remoção, botão de protocolo geral) num
 * layout novo e com as consultas preparadas.
 *
 * Acesso: administradores, ou o responsável, o revisor e quem criou.
 */

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/gemini.php';
exigir_login();

$usuario = usuario_atual();
$taskId  = entrada_int('id');

$tarefa = $taskId > 0
    ? db_one('SELECT * FROM tarefas WHERE id = ? LIMIT 1', array($taskId))
    : null;

if (!$tarefa) {
    include(__DIR__ . '/../menu.php');
    echo '<div style="font-family:sans-serif;padding:60px;text-align:center">'
       . '<h2>Tarefa não encontrada</h2>'
       . '<p><a href="index.php">Voltar ao Controle de Tarefas</a></p></div>';
    exit;
}

$podeEditar = usuario_ve_tudo()
    || $tarefa['funcionario_responsavel'] === $usuario['nome']
    || $tarefa['revisor'] === $usuario['nome']
    || $tarefa['criado_por'] === $usuario['usuario'];

if (!$podeEditar) {
    include(__DIR__ . '/../menu.php');
    echo '<div style="font-family:sans-serif;padding:60px;text-align:center">'
       . '<h2>Sem permissão</h2>'
       . '<p>Você não é responsável, revisor nem autor desta tarefa.</p>'
       . '<p><a href="index.php">Voltar</a></p></div>';
    exit;
}

$token        = $tarefa['token'];
$categorias   = listar_categorias();
$origens      = listar_origens();
$funcionarios = listar_funcionarios();
$prioridades  = array_keys(tarefas_prioridades());
$statusLista  = array_keys(tarefas_status_catalogo());

/* Categoria/origem podem ter sido desativadas depois: mantemos na lista. */
$idsCategoria = array_column($categorias, 'id');
if ($tarefa['categoria'] !== null && $tarefa['categoria'] !== ''
    && !in_array($tarefa['categoria'], $idsCategoria)) {
    $extra = db_one('SELECT id, titulo FROM categorias WHERE id = ?', array($tarefa['categoria']));
    if ($extra) {
        $extra['titulo'] .= ' (inativa)';
        $categorias[] = $extra;
    }
}
$idsOrigem = array_column($origens, 'id');
if ($tarefa['origem'] !== null && $tarefa['origem'] !== ''
    && !in_array($tarefa['origem'], $idsOrigem)) {
    $extra = db_one('SELECT id, titulo FROM origem WHERE id = ?', array($tarefa['origem']));
    if ($extra) {
        $extra['titulo'] .= ' (inativa)';
        $origens[] = $extra;
    }
}

$prazoInput = '';
if (!empty($tarefa['data_limite'])) {
    $ts = strtotime((string) $tarefa['data_limite']);
    if ($ts !== false) { $prazoInput = date('Y-m-d\TH:i', $ts); }
}

$anexos = anexos_lista($tarefa['caminho_anexo']);

$comentarios = db_all(
    'SELECT * FROM comentarios WHERE hash_tarefa = ? ORDER BY data_comentario DESC',
    array($token)
);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas · Editar Tarefa nº <?php echo e($tarefa['id']); ?></title>

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
    <div class="container-fluid tf-app" style="max-width:1080px">

        <div class="tf-topo">
            <div class="tf-topo-icone"><i class="fa fa-pencil"></i></div>
            <div>
                <h1>Protocolo Geral nº <?php echo e($tarefa['id']); ?></h1>
                <div class="tf-sub">
                    Criada por <?php echo e($tarefa['criado_por']); ?>
                    em <?php echo e(data_br($tarefa['data_criacao'])); ?>
                    <?php if ((string) $tarefa['sub_categoria'] === 'Sim'): ?>
                        · subtarefa da tarefa nº <?php echo e($tarefa['id_tarefa_principal']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="tf-topo-acoes">
                <button class="tf-btn" id="protocoloButton">
                    <i class="fa fa-print"></i> Protocolo geral
                </button>
                <a href="index.php?token=<?php echo e($token); ?>" class="tf-btn">
                    <i class="fa fa-eye"></i> Ver tarefa
                </a>
                <a href="index.php" class="tf-btn">
                    <i class="fa fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <!-- ======================= DADOS ======================= -->
        <form id="editTaskForm" enctype="multipart/form-data">
            <input type="hidden" name="taskId" id="taskId" value="<?php echo e($tarefa['id']); ?>">
            <input type="hidden" name="taskToken" id="taskToken" value="<?php echo e($token); ?>">

            <div class="tf-painel" style="padding:22px;margin-bottom:16px">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px">

                    <div style="grid-column:1/-1">
                        <label class="tf-rotulo">Título <span style="color:var(--tf-perigo)">*</span></label>
                        <input type="text" class="tf-input" id="title" name="title" required
                               value="<?php echo e($tarefa['titulo']); ?>">
                    </div>

                    <div style="grid-column:1/-1">
                        <label class="tf-rotulo">Descrição</label>
                        <textarea class="tf-textarea" id="description" name="description"
                                  rows="5"><?php echo e($tarefa['descricao']); ?></textarea>
                    </div>

                    <div>
                        <label class="tf-rotulo">Categoria <span style="color:var(--tf-perigo)">*</span></label>
                        <select class="tf-select" id="category" name="category" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?php echo e($c['id']); ?>"
                                    <?php echo ((string) $c['id'] === (string) $tarefa['categoria']) ? ' selected' : ''; ?>>
                                    <?php echo e($c['titulo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="tf-rotulo">Origem <span style="color:var(--tf-perigo)">*</span></label>
                        <select class="tf-select" id="origin" name="origin" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($origens as $o): ?>
                                <option value="<?php echo e($o['id']); ?>"
                                    <?php echo ((string) $o['id'] === (string) $tarefa['origem']) ? ' selected' : ''; ?>>
                                    <?php echo e($o['titulo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="tf-rotulo">Data limite <span style="color:var(--tf-perigo)">*</span></label>
                        <input type="datetime-local" class="tf-input" id="deadline" name="deadline"
                               value="<?php echo e($prazoInput); ?>" required>
                    </div>

                    <div>
                        <label class="tf-rotulo">Prioridade</label>
                        <select class="tf-select" id="priority" name="priority">
                            <?php foreach ($prioridades as $p): ?>
                                <option value="<?php echo e($p); ?>"
                                    <?php echo ($p === $tarefa['nivel_de_prioridade']) ? ' selected' : ''; ?>>
                                    <?php echo e($p); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="tf-rotulo">Funcionário responsável</label>
                        <select class="tf-select" id="employee" name="employee">
                            <option value="">Sem responsável</option>
                            <?php foreach ($funcionarios as $f): ?>
                                <option value="<?php echo e($f['nome_completo']); ?>"
                                    <?php echo ($f['nome_completo'] === $tarefa['funcionario_responsavel']) ? ' selected' : ''; ?>>
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
                                <option value="<?php echo e($f['nome_completo']); ?>"
                                    <?php echo ($f['nome_completo'] === $tarefa['revisor']) ? ' selected' : ''; ?>>
                                    <?php echo e($f['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="tf-rotulo">Status</label>
                        <select class="tf-select" id="status" name="status">
                            <?php foreach ($statusLista as $s): ?>
                                <option value="<?php echo e($s); ?>"
                                    <?php echo ($s === $tarefa['status']) ? ' selected' : ''; ?>>
                                    <?php echo e($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (db_tem_coluna('tarefas', 'apresentante')): ?>
                    <div>
                        <label class="tf-rotulo">Apresentante</label>
                        <input type="text" class="tf-input" id="apresentante" name="apresentante"
                               value="<?php echo e(isset($tarefa['apresentante']) ? $tarefa['apresentante'] : ''); ?>">
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="tf-rotulo">Nº do ofício vinculado</label>
                        <input type="text" class="tf-input" readonly
                               value="<?php echo e($tarefa['numero_oficio'] !== null && $tarefa['numero_oficio'] !== '' ? $tarefa['numero_oficio'] : '—'); ?>">
                    </div>
                </div>
            </div>

            <!-- ======================= ANEXOS ======================= -->
            <div class="tf-painel" style="padding:22px;margin-bottom:16px">
                <h3 style="font-size:.95rem;font-weight:650;margin:0 0 14px">
                    <i class="fa fa-paperclip"></i> Anexos
                </h3>

                <div id="viewAttachments" class="tf-anexos" style="margin-bottom:14px">
                    <?php if (!$anexos): ?>
                        <p class="tf-mudo tf-mini" style="margin:0">Nenhum anexo nesta tarefa.</p>
                    <?php else: foreach ($anexos as $a): ?>
                        <div class="tf-anexo<?php echo $a['existe'] ? '' : ' tf-sumido'; ?>"
                             data-arquivo="<?php echo e($a['rel']); ?>">
                            <div class="tf-anexo-icone"><i class="fa fa-file-o"></i></div>
                            <div class="tf-anexo-nome">
                                <?php echo e($a['nome']); ?>
                                <small><?php echo $a['existe']
                                    ? e($a['tamanho_br'])
                                    : '<span style="color:var(--tf-perigo)">arquivo não localizado</span>'; ?></small>
                            </div>
                            <div class="tf-anexo-acoes">
                                <?php if ($a['existe']): ?>
                                <a class="tf-btn tf-btn-sm" href="<?php echo e($a['url']); ?>" target="_blank">
                                    <i class="fa fa-external-link"></i>
                                </a>
                                <?php endif; ?>
                                <button type="button" class="tf-btn tf-btn-sm" data-excluir-anexo>
                                    <i class="fa fa-trash-o"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <div class="tf-zona-upload" id="zonaUpload">
                    <i class="fa fa-cloud-upload"></i>
                    <strong>Adicionar mais anexos</strong>
                    <div class="tf-mini tf-mudo" style="margin-top:6px">
                        Os arquivos novos somam-se aos já existentes.
                    </div>
                    <input type="file" id="attachments" name="attachments[]" multiple style="display:none">
                </div>
                <div class="tf-anexos" id="listaNovos" style="margin-top:12px"></div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-bottom:24px;flex-wrap:wrap">
                <a href="index.php" class="tf-btn"><i class="fa fa-times"></i> Cancelar</a>
                <button type="submit" class="tf-btn tf-btn-primario" id="btnSalvar">
                    <i class="fa fa-save"></i> Salvar alterações
                </button>
            </div>
        </form>

        <!-- ======================= COMENTÁRIOS ======================= -->
        <div class="tf-painel" style="padding:22px;margin-bottom:30px">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
                <h3 style="font-size:.95rem;font-weight:650;margin:0">
                    <i class="fa fa-history"></i> Andamento
                </h3>
                <button class="tf-btn tf-btn-sm tf-btn-primario" id="btnNovoComentario" style="margin-left:auto">
                    <i class="fa fa-comment-o"></i> Registrar andamento
                </button>
            </div>

            <div id="commentTimeline" class="tf-tempo">
                <?php if (!$comentarios): ?>
                    <p class="tf-mudo tf-mini">Nenhum registro ainda.</p>
                <?php else: foreach ($comentarios as $c):
                    $anexosCom = anexos_lista($c['caminho_anexo']); ?>
                    <div class="tf-tempo-item" data-comentario="<?php echo e($c['id']); ?>">
                        <div class="tf-tempo-marca"><i class="fa fa-comment"></i></div>
                        <div class="tf-tempo-caixa">
                            <div class="tf-tempo-cabec">
                                <span class="tf-tempo-autor"><?php echo e($c['funcionario']); ?></span>
                                <span><?php echo e(data_br($c['data_comentario'])); ?></span>
                                <?php if (!empty($c['data_atualizacao'])): ?>
                                    <span class="tf-mini">(editado em <?php echo e(data_br($c['data_atualizacao'])); ?>)</span>
                                <?php endif; ?>
                                <span class="tf-tempo-acoes">
                                    <button type="button" class="tf-btn tf-btn-sm" data-editar-com>
                                        <i class="fa fa-pencil"></i>
                                    </button>
                                    <button type="button" class="tf-btn tf-btn-sm" data-excluir-com>
                                        <i class="fa fa-trash-o"></i>
                                    </button>
                                </span>
                            </div>
                            <div class="tf-tempo-texto"><?php echo e($c['comentario']); ?></div>
                            <?php if ($anexosCom): ?>
                            <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
                                <?php foreach ($anexosCom as $a): ?>
                                    <span class="tf-btn tf-btn-sm" data-anexo-com="<?php echo e($a['rel']); ?>">
                                        <a href="<?php echo e($a['url']); ?>" target="_blank"
                                           style="color:inherit"><?php echo e($a['nome']); ?></a>
                                        <i class="fa fa-times" data-excluir-anexo-com style="cursor:pointer"></i>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div>
</div>

<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="../script/toastr.min.js"></script>

<script>
jQuery(function ($) {
    'use strict';

    var CSRF   = <?php echo json_encode(csrf_token()); ?>;
    var TOKEN  = <?php echo json_encode($token); ?>;
    var TASKID = <?php echo (int) $tarefa['id']; ?>;
    var novos  = [];

    /** Substitui $.trim, removido no jQuery 4. */
    function txt(v) {
        return (v === null || v === undefined) ? '' : String(v).trim();
    }

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function api(dados) {
        var opcoes = { url: 'api/acoes.php', type: 'POST', dataType: 'json' };
        if (dados instanceof FormData) {
            dados.append('_csrf', CSRF);
            opcoes.data = dados;
            opcoes.processData = false;
            opcoes.contentType = false;
        } else {
            opcoes.data = $.extend({ _csrf: CSRF }, dados);
        }
        return $.ajax(opcoes).then(null, function (xhr) {
            return $.Deferred().reject({
                error: (xhr.responseJSON && xhr.responseJSON.error) || 'Falha de comunicação.'
            }).promise();
        });
    }

    function erro(t) { Swal.fire({ icon: 'error', title: 'Erro', text: t }); }

    /* ---------------------- Protocolo ---------------------- */

    $('#protocoloButton').on('click', function () {
        $.ajax({ url: '../style/configuracao.json', dataType: 'json', cache: false })
            .done(function (cfg) {
                abrirProtocolo(cfg && cfg.timbrado === 'S');
            })
            .fail(function () { abrirProtocolo(false); });
    });

    function abrirProtocolo(timbrado) {
        // 'S' = papel já timbrado, usa o arquivo com sublinhado;
        // 'N' = o PDF desenha o cabeçalho, usa o arquivo com hífen.
        var arquivo = timbrado ? 'protocolo_geral.php' : 'protocolo-geral.php';
        window.open(arquivo + '?id=' + TASKID, '_blank');
    }

    /* ---------------------- Anexos novos ---------------------- */

    function desenharNovos() {
        var $l = $('#listaNovos').empty();
        novos.forEach(function (f, i) {
            $l.append('<div class="tf-anexo">'
                + '<div class="tf-anexo-icone"><i class="fa fa-file-o"></i></div>'
                + '<div class="tf-anexo-nome">' + esc(f.name) + '<small>a enviar</small></div>'
                + '<button type="button" class="tf-btn tf-btn-sm" data-tirar="' + i + '">'
                + '<i class="fa fa-times"></i></button></div>');
        });
    }

    /* Ignora o clique vindo do próprio input: senão vira recursão infinita. */
    $('#zonaUpload').on('click', function (e) {
        if (e.target === document.getElementById('attachments')) { return; }
        $('#attachments').trigger('click');
    })
        .on('dragover', function (e) { e.preventDefault(); $(this).addClass('tf-sobre'); })
        .on('dragleave', function () { $(this).removeClass('tf-sobre'); })
        .on('drop', function (e) {
            e.preventDefault();
            $(this).removeClass('tf-sobre');
            Array.prototype.push.apply(novos, e.originalEvent.dataTransfer.files);
            desenharNovos();
        });

    $('#attachments').on('change', function () {
        Array.prototype.push.apply(novos, this.files);
        this.value = '';
        desenharNovos();
    });

    $('#listaNovos').on('click', '[data-tirar]', function () {
        novos.splice(parseInt($(this).data('tirar'), 10), 1);
        desenharNovos();
    });

    $('#viewAttachments').on('click', '[data-excluir-anexo]', function () {
        var $bloco = $(this).closest('.tf-anexo');
        var arquivo = $bloco.data('arquivo');

        Swal.fire({
            icon: 'warning',
            title: 'Excluir anexo?',
            text: 'O arquivo será removido do servidor.',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then(function (res) {
            if (!res.isConfirmed) { return; }
            api({ acao: 'excluir_anexo', taskId: TASKID, file: arquivo })
                .done(function (r) {
                    if (!r.success) { erro(r.error); return; }
                    $bloco.fadeOut(160, function () { $(this).remove(); });
                    if (window.toastr) { toastr.success('Anexo excluído.'); }
                })
                .fail(function (e) { erro(e.error); });
        });
    });

    /* ---------------------- Salvar ---------------------- */

    $('#editTaskForm').on('submit', function (e) {
        e.preventDefault();

        var $b = $('#btnSalvar').prop('disabled', true)
            .html('<i class="fa fa-circle-o-notch tf-girando"></i> Salvando…');

        var fd = new FormData();
        fd.append('acao', 'editar');

        $(this).find('input[type=text], input[type=hidden], input[type=datetime-local], select, textarea')
            .each(function () {
                var n = $(this).attr('name');
                if (n) { fd.append(n, $(this).val()); }
            });

        novos.forEach(function (f) { fd.append('attachments[]', f); });

        api(fd)
            .done(function (r) {
                $b.prop('disabled', false).html('<i class="fa fa-save"></i> Salvar alterações');
                if (!r.success) { erro(r.error); return; }

                if (r.avisos && r.avisos.length) {
                    Swal.fire({ icon: 'warning', title: 'Salvo com ressalvas',
                        text: r.avisos.join(' ') }).then(function () { window.location.reload(); });
                    return;
                }

                Swal.fire({
                    icon: 'success', title: 'Alterações salvas',
                    timer: 1400, showConfirmButton: false
                }).then(function () { window.location.reload(); });
            })
            .fail(function (e2) {
                $b.prop('disabled', false).html('<i class="fa fa-save"></i> Salvar alterações');
                erro(e2.error);
            });
    });

    /* ---------------------- Comentários ---------------------- */

    $('#btnNovoComentario').on('click', function () { formComentario(null, ''); });

    $('#commentTimeline').on('click', '[data-editar-com]', function () {
        var $item = $(this).closest('.tf-tempo-item');
        formComentario($item.data('comentario'), $item.find('.tf-tempo-texto').text());
    });

    $('#commentTimeline').on('click', '[data-excluir-com]', function () {
        var $item = $(this).closest('.tf-tempo-item');

        Swal.fire({
            icon: 'warning', title: 'Excluir registro?',
            text: 'Esta ação não pode ser desfeita.',
            showCancelButton: true, confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar', reverseButtons: true
        }).then(function (res) {
            if (!res.isConfirmed) { return; }
            api({ acao: 'excluir_comentario', id: $item.data('comentario') })
                .done(function (r) {
                    if (!r.success) { erro(r.error); return; }
                    $item.fadeOut(160, function () { $(this).remove(); });
                })
                .fail(function (e) { erro(e.error); });
        });
    });

    $('#commentTimeline').on('click', '[data-excluir-anexo-com]', function () {
        var $selo = $(this).closest('[data-anexo-com]');
        var comentarioId = $(this).closest('.tf-tempo-item').data('comentario');

        api({ acao: 'excluir_anexo_comentario', commentId: comentarioId, file: $selo.data('anexo-com') })
            .done(function (r) {
                if (!r.success) { erro(r.error); return; }
                $selo.remove();
            })
            .fail(function (e) { erro(e.error); });
    });

    function formComentario(id, textoAtual) {
        var editando = !!id;

        Swal.fire({
            title: editando ? 'Editar registro' : 'Registrar andamento',
            width: 640,
            html: '<textarea id="cmTexto" class="tf-textarea" rows="6" style="width:100%">'
                + esc(textoAtual) + '</textarea>'
                + '<div style="margin-top:12px;text-align:left">'
                + '<label class="tf-rotulo">Anexos (opcional)</label>'
                + '<input type="file" id="cmArquivos" multiple class="tf-input"></div>',
            showCancelButton: true,
            confirmButtonText: editando ? 'Salvar' : 'Registrar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusConfirm: false,
            preConfirm: function () {
                var t = $('#cmTexto').val();
                var arqs = document.getElementById('cmArquivos').files;
                if (!txt(t) && !arqs.length) {
                    Swal.showValidationMessage('Escreva um texto ou anexe um arquivo.');
                    return false;
                }
                return { texto: t, arquivos: arqs };
            }
        }).then(function (res) {
            if (!res.isConfirmed) { return; }

            var fd = new FormData();
            var i;

            if (editando) {
                fd.append('acao', 'editar_comentario');
                fd.append('commentId', id);
                fd.append('taskToken', TOKEN);
                fd.append('editCommentDescription', res.value.texto);
                for (i = 0; i < res.value.arquivos.length; i++) {
                    fd.append('editCommentAttachments[]', res.value.arquivos[i]);
                }
            } else {
                fd.append('acao', 'comentar');
                fd.append('taskToken', TOKEN);
                fd.append('commentDescription', res.value.texto);
                for (i = 0; i < res.value.arquivos.length; i++) {
                    fd.append('commentAttachments[]', res.value.arquivos[i]);
                }
            }

            api(fd)
                .done(function (r) {
                    if (!r.success) { erro(r.error); return; }
                    window.location.reload();
                })
                .fail(function (e) { erro(e.error); });
        });
    }

    if (window.toastr) {
        toastr.options = { positionClass: 'toast-bottom-right', timeOut: 3000, progressBar: true };
    }
});
</script>

</body>
</html>
