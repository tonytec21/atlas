<?php
/**
 * Atlas · Tarefas — cadastro de categorias e origens.
 *
 * Continua usando os endpoints originais (save_category.php,
 * update_category.php e delete_category.php), que não foram alterados. O que
 * mudou é a tela: as duas listas ficam lado a lado, com contagem de uso, e a
 * exclusão avisa quantas tarefas ficariam órfãs antes de confirmar.
 */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

if (!usuario_ve_tudo()) {
    http_response_code(403);
    echo '<p style="font-family:sans-serif;padding:40px">Acesso restrito aos administradores do sistema.</p>';
    exit;
}

/** Lista categorias ou origens já com a contagem de tarefas vinculadas. */
function listar_com_uso($tabela, $coluna)
{
    return db_all(
        "SELECT x.id, x.titulo, x.status, COUNT(t.id) AS em_uso
           FROM `$tabela` x
           LEFT JOIN tarefas t ON t.`$coluna` = x.id
          GROUP BY x.id, x.titulo, x.status
          ORDER BY x.titulo"
    );
}

$categorias = listar_com_uso('categorias', 'categoria');
$origens    = listar_com_uso('origem', 'origem');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas · Categorias e Origens</title>

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
            <div class="tf-topo-icone"><i class="fa fa-tags"></i></div>
            <div>
                <h1>Categorias e origens</h1>
                <div class="tf-sub">Classificações usadas no cadastro das tarefas</div>
            </div>
            <div class="tf-topo-acoes">
                <a href="index.php" class="tf-btn">
                    <i class="fa fa-arrow-left"></i> Voltar às tarefas
                </a>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:16px">

            <?php
            $blocos = array(
                array('tipo' => 'categoria', 'titulo' => 'Categorias', 'icone' => 'fa-folder-o', 'dados' => $categorias),
                array('tipo' => 'origem',    'titulo' => 'Origens',    'icone' => 'fa-sign-in',  'dados' => $origens),
            );

            foreach ($blocos as $b): ?>
            <div class="tf-painel" style="padding:20px">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap">
                    <h3 style="font-size:1rem;font-weight:650;margin:0">
                        <i class="fa <?php echo $b['icone']; ?>"></i> <?php echo $b['titulo']; ?>
                    </h3>
                    <span class="tf-selo tf-selo-contorno"><?php echo count($b['dados']); ?></span>
                    <button class="tf-btn tf-btn-sm tf-btn-primario" style="margin-left:auto"
                            data-novo="<?php echo $b['tipo']; ?>">
                        <i class="fa fa-plus"></i> Adicionar
                    </button>
                </div>

                <div class="tf-anexos">
                    <?php if (!$b['dados']): ?>
                        <p class="tf-mudo tf-mini" style="margin:0">Nada cadastrado ainda.</p>
                    <?php else: foreach ($b['dados'] as $item):
                        $ativo = (strtolower((string) $item['status']) === 'ativo'); ?>
                        <div class="tf-anexo" data-tipo="<?php echo $b['tipo']; ?>"
                             data-id="<?php echo e($item['id']); ?>"
                             data-titulo="<?php echo e($item['titulo']); ?>"
                             data-status="<?php echo e($item['status']); ?>">
                            <div class="tf-anexo-icone">
                                <i class="fa <?php echo $b['icone']; ?>"></i>
                            </div>
                            <div class="tf-anexo-nome">
                                <?php echo e($item['titulo']); ?>
                                <small>
                                    <?php echo (int) $item['em_uso']; ?> tarefa(s) ·
                                    <?php echo $ativo ? 'ativo' : 'inativo'; ?>
                                </small>
                            </div>
                            <div class="tf-anexo-acoes">
                                <button class="tf-btn tf-btn-sm" data-editar title="Editar">
                                    <i class="fa fa-pencil"></i>
                                </button>
                                <button class="tf-btn tf-btn-sm tf-btn-perigo" data-excluir
                                        data-uso="<?php echo (int) $item['em_uso']; ?>" title="Excluir">
                                    <i class="fa fa-trash-o"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        </div>

        <p class="tf-mini tf-mudo" style="margin-top:16px">
            <i class="fa fa-info-circle"></i>
            Itens em uso não devem ser excluídos: as tarefas antigas passariam a exibir a classificação
            em branco. Para tirar de circulação sem afetar o acervo, marque como <strong>inativo</strong> —
            o item deixa de aparecer nos formulários novos e continua sendo exibido nas tarefas que já o usam.
        </p>

    </div>
</div>

<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="../script/toastr.min.js"></script>

<script>
jQuery(function ($) {
    'use strict';

    /** Substitui $.trim, removido no jQuery 4. */
    function txt(v) {
        return (v === null || v === undefined) ? '' : String(v).trim();
    }

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function rotulo(tipo) { return tipo === 'categoria' ? 'categoria' : 'origem'; }

    function formulario(tipo, item) {
        var editando = !!item;
        var ativo = editando && String(item.status).toLowerCase() === 'ativo';

        Swal.fire({
            title: (editando ? 'Editar ' : 'Nova ') + rotulo(tipo),
            width: 520,
            html: '<div style="text-align:left">'
                + '<label class="tf-rotulo">Título</label>'
                + '<input id="ctTitulo" class="tf-input" value="' + esc(editando ? item.titulo : '') + '">'
                + '<label class="tf-rotulo" style="margin-top:12px">Situação</label>'
                + '<select id="ctStatus" class="tf-select">'
                + '<option value="Ativo"' + (!editando || ativo ? ' selected' : '') + '>Ativo</option>'
                + '<option value="Inativo"' + (editando && !ativo ? ' selected' : '') + '>Inativo</option>'
                + '</select></div>',
            showCancelButton: true,
            confirmButtonText: 'Salvar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusConfirm: false,
            preConfirm: function () {
                var t = txt($('#ctTitulo').val());
                if (!t) {
                    Swal.showValidationMessage('Informe o título.');
                    return false;
                }
                return { titulo: t, status: $('#ctStatus').val() };
            }
        }).then(function (res) {
            if (!res.isConfirmed) { return; }

            var dados = {
                tipo: tipo,
                titulo: res.value.titulo,
                status: res.value.status
            };
            if (editando) { dados.id = item.id; }

            $.ajax({
                url: editando ? 'update_category.php' : 'save_category.php',
                type: 'POST',
                data: dados
            })
                .done(function () {
                    if (window.toastr) { toastr.success('Registro salvo.'); }
                    setTimeout(function () { window.location.reload(); }, 600);
                })
                .fail(function () {
                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Não foi possível salvar.' });
                });
        });
    }

    $('[data-novo]').on('click', function () {
        formulario($(this).data('novo'), null);
    });

    $('[data-editar]').on('click', function () {
        var $l = $(this).closest('.tf-anexo');
        formulario($l.data('tipo'), {
            id: $l.data('id'),
            titulo: $l.data('titulo'),
            status: $l.data('status')
        });
    });

    $('[data-excluir]').on('click', function () {
        var $l = $(this).closest('.tf-anexo');
        var uso = parseInt($(this).data('uso'), 10) || 0;
        var tipo = $l.data('tipo');

        var aviso = uso > 0
            ? '<p style="color:var(--tf-perigo)"><strong>' + uso + ' tarefa(s)</strong> usam esta '
              + rotulo(tipo) + '. Depois da exclusão, elas ficarão sem essa classificação.<br>'
              + 'Considere marcar como <strong>inativo</strong> em vez de excluir.</p>'
            : '<p>Nenhuma tarefa usa esta ' + rotulo(tipo) + '. A exclusão é segura.</p>';

        Swal.fire({
            icon: 'warning',
            title: 'Excluir "' + esc($l.data('titulo')) + '"?',
            html: aviso,
            showCancelButton: true,
            confirmButtonText: 'Excluir mesmo assim',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then(function (res) {
            if (!res.isConfirmed) { return; }

            $.ajax({
                url: 'delete_category.php',
                type: 'POST',
                data: { tipo: tipo, id: $l.data('id') }
            })
                .done(function () {
                    if (window.toastr) { toastr.success('Registro excluído.'); }
                    setTimeout(function () { window.location.reload(); }, 600);
                })
                .fail(function () {
                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Não foi possível excluir.' });
                });
        });
    });

    if (window.toastr) {
        toastr.options = { positionClass: 'toast-bottom-right', timeOut: 2600, progressBar: true };
    }
});
</script>

</body>
</html>
