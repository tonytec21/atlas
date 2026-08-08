<?php
/**
 * Atlas · Tarefas — configurações da integração com o Gemini.
 *
 * Três blocos:
 *   1. Conexão — chave da API, modelo padrão, liga/desliga e teste.
 *   2. Catálogo de modelos — cadastrar, ativar, favoritar e excluir.
 *   3. Consumo — chamadas e tokens dos últimos 30 dias.
 *
 * Sobre os modelos semeados pela migração: são os identificadores válidos da
 * linha Gemini 3.x. Atenção a um detalhe que costuma gerar erro 404 — o
 * Gemini 3.1 Pro é publicado na Developer API como `gemini-3.1-pro-preview`,
 * e não como `gemini-3.1-pro`. O botão "Sincronizar" resolve isso de vez:
 * ele lê a lista real de modelos que a sua chave enxerga.
 */

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/gemini.php';
exigir_login();

if (!usuario_ve_tudo()) {
    http_response_code(403);
    echo '<p style="font-family:sans-serif;padding:40px">Acesso restrito aos administradores do sistema.</p>';
    exit;
}

$migracaoPendente = !db_tem_tabela('tarefas_ia_modelos');
$usuario = usuario_atual();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas · Configurações da IA</title>

    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="stylesheet" href="../style/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/tarefas.css?v=2.0.8">

    <script src="../script/jquery-3.5.1.min.js"></script>
</head>

<body class="light-mode">

<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container-fluid tf-app" style="max-width:1100px">

        <div class="tf-topo">
            <div class="tf-topo-icone" style="background:linear-gradient(135deg,#7c3aed,#4f46e5)">
                <i class="fa fa-magic"></i>
            </div>
            <div>
                <h1>Assistente de IA</h1>
                <div class="tf-sub">Integração do módulo de Tarefas com a API Gemini</div>
            </div>
            <div class="tf-topo-acoes">
                <a href="index.php" class="tf-btn">
                    <i class="fa fa-arrow-left"></i> Voltar às tarefas
                </a>
            </div>
        </div>

        <?php if ($migracaoPendente): ?>
        <div class="tf-painel" style="border-color:#f59e0b;background:rgba(245,158,11,.08);padding:18px">
            <strong><i class="fa fa-database"></i> Migração pendente.</strong>
            As tabelas da IA ainda não existem no banco.
            <a href="migracao_v2.php" class="tf-btn tf-btn-sm tf-btn-primario" style="margin-left:10px">
                <i class="fa fa-play"></i> Executar migração
            </a>
        </div>
        <?php else: ?>

        <!-- ======================= CONEXÃO ======================= -->
        <div class="tf-painel" style="padding:20px;margin-bottom:16px">
            <h3 style="font-size:1rem;font-weight:650;margin:0 0 4px">
                <i class="fa fa-plug"></i> Conexão
            </h3>
            <p class="tf-mini tf-mudo" style="margin-bottom:18px">
                A chave é gravada no banco da serventia e nunca é devolvida inteira para o navegador.
                Obtenha a sua em <strong>aistudio.google.com</strong> → Get API key.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
                <div style="grid-column:1/-1">
                    <label class="tf-rotulo">Chave da API</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <input type="password" class="tf-input" id="cfgChave"
                               placeholder="Cole aqui a chave da API" style="flex:1;min-width:240px">
                        <button class="tf-btn" id="btnVerChave" type="button" title="Mostrar/ocultar">
                            <i class="fa fa-eye"></i>
                        </button>
                        <button class="tf-btn tf-btn-perigo" id="btnRemoverChave" type="button">
                            <i class="fa fa-trash-o"></i> Remover
                        </button>
                    </div>
                    <p class="tf-mini tf-mudo" id="cfgChaveAtual" style="margin:6px 0 0"></p>
                </div>

                <div>
                    <label class="tf-rotulo">Modelo padrão</label>
                    <select class="tf-select" id="cfgModeloPadrao"></select>
                    <p class="tf-mini tf-mudo" style="margin:6px 0 0">
                        Usado quando o recurso não indica outro modelo.
                    </p>
                </div>

                <div>
                    <label class="tf-rotulo">Integração</label>
                    <select class="tf-select" id="cfgAtivo">
                        <option value="0">Desativada</option>
                        <option value="1">Ativada</option>
                    </select>
                    <p class="tf-mini tf-mudo" style="margin:6px 0 0">
                        Desativada, os botões de IA somem da tela de tarefas.
                    </p>
                </div>

                <div>
                    <label class="tf-rotulo">Temperatura</label>
                    <input type="number" class="tf-input" id="cfgTemperatura" min="0" max="2" step="0.1">
                    <p class="tf-mini tf-mudo" style="margin:6px 0 0">
                        Mais baixa = respostas mais previsíveis. Sugerido: 0,4.
                    </p>
                </div>

                <div>
                    <label class="tf-rotulo">Limite de tokens na resposta</label>
                    <input type="number" class="tf-input" id="cfgMaxTokens" min="256" max="32000" step="256">
                </div>

                <div>
                    <label class="tf-rotulo">Tempo limite (segundos)</label>
                    <input type="number" class="tf-input" id="cfgTimeout" min="10" max="300" step="5">
                </div>

                <div style="grid-column:1/-1">
                    <label class="tf-rotulo">Contexto da serventia</label>
                    <textarea class="tf-textarea" id="cfgContexto" rows="4"
                              placeholder="Informações fixas que a IA deve considerar em toda resposta."></textarea>
                    <p class="tf-mini tf-mudo" style="margin:6px 0 0">
                        Ex.: atribuições da serventia, comarca, normas locais que devem ser observadas.
                        Não coloque dados pessoais de partes aqui.
                    </p>
                </div>
            </div>

            <div style="display:flex;gap:8px;margin-top:18px;flex-wrap:wrap">
                <button class="tf-btn tf-btn-primario" id="btnSalvarConfig">
                    <i class="fa fa-save"></i> Salvar configuração
                </button>
                <button class="tf-btn" id="btnTestar">
                    <i class="fa fa-bolt"></i> Testar conexão
                </button>
                <button class="tf-btn" id="btnSincronizar">
                    <i class="fa fa-refresh"></i> Sincronizar modelos
                </button>
            </div>

            <div id="cfgResultadoTeste" style="margin-top:14px"></div>
        </div>

        <!-- ======================= MODELOS ======================= -->
        <div class="tf-painel" style="padding:20px;margin-bottom:16px">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;flex-wrap:wrap">
                <h3 style="font-size:1rem;font-weight:650;margin:0">
                    <i class="fa fa-cubes"></i> Catálogo de modelos
                </h3>
                <button class="tf-btn tf-btn-sm tf-btn-primario" id="btnNovoModelo" style="margin-left:auto">
                    <i class="fa fa-plus"></i> Cadastrar modelo
                </button>
            </div>

            <p class="tf-mini tf-mudo" style="margin-bottom:16px">
                O catálogo é livre: cadastre, desative ou exclua modelos conforme o Google atualizar a
                família Gemini. O modelo definido como padrão não pode ser excluído nem desativado —
                é a trava que impede o módulo de ficar sem modelo utilizável.
                <strong>Sincronizar</strong> consulta a API e marca quais identificadores a sua chave
                realmente enxerga.
            </p>

            <div class="tf-tabela-caixa">
                <table class="tf-tabela" id="tabelaModelos">
                    <thead>
                        <tr>
                            <th class="tf-sem-ordem" style="width:34px"></th>
                            <th class="tf-sem-ordem">Modelo</th>
                            <th class="tf-sem-ordem">Identificador de API</th>
                            <th class="tf-sem-ordem">Origem</th>
                            <th class="tf-sem-ordem">Disponível</th>
                            <th class="tf-sem-ordem">Situação</th>
                            <th class="tf-sem-ordem" style="width:150px">Ações</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- ======================= CONSUMO ======================= -->
        <div class="tf-painel" style="padding:20px">
            <h3 style="font-size:1rem;font-weight:650;margin:0 0 16px">
                <i class="fa fa-bar-chart"></i> Consumo nos últimos 30 dias
            </h3>
            <div class="tf-kpis" id="cfgEstatisticas"></div>
            <div id="cfgPorRecurso" style="margin-top:16px"></div>
        </div>

        <?php endif; ?>
    </div>
</div>

<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="../script/toastr.min.js"></script>

<script>
jQuery(function ($) {
    'use strict';

    var CSRF = <?php echo json_encode(csrf_token()); ?>;
    var modelos = [];

    /** Substitui $.trim, removido no jQuery 4. */
    function txt(v) {
        return (v === null || v === undefined) ? '' : String(v).trim();
    }

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function api(dados, metodo) {
        return $.ajax({
            url: 'api/modelos.php',
            type: metodo || 'POST',
            dataType: 'json',
            data: $.extend({ _csrf: CSRF }, dados)
        }).then(null, function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Falha de comunicação com o servidor.';
            return $.Deferred().reject({ error: msg }).promise();
        });
    }

    function erro(texto) { Swal.fire({ icon: 'error', title: 'Erro', text: texto }); }
    function ok(texto)   { if (window.toastr) { toastr.success(texto); } }

    /* ---------------------------------------------------------- */
    /* Carregamento                                                */
    /* ---------------------------------------------------------- */

    function carregar() {
        api({ acao: 'listar' }, 'GET')
            .done(function (r) {
                if (!r.success) { erro(r.error); return; }

                modelos = r.modelos || [];
                preencherConfig(r.config);
                desenharModelos();
                desenharEstatisticas(r.estatisticas);
            })
            .fail(function (e) { erro(e.error); });
    }

    function preencherConfig(c) {
        $('#cfgChaveAtual').html(c.tem_chave
            ? '<i class="fa fa-check-circle" style="color:var(--tf-sucesso)"></i> Chave cadastrada: <code>'
              + esc(c.chave_mascarada) + '</code>'
            : '<i class="fa fa-exclamation-circle" style="color:var(--tf-alerta)"></i> Nenhuma chave cadastrada.');

        $('#cfgAtivo').val(c.ativo ? '1' : '0');
        $('#cfgTemperatura').val(c.temperatura);
        $('#cfgMaxTokens').val(c.max_tokens);
        $('#cfgTimeout').val(c.timeout);
        $('#cfgContexto').val(c.contexto_cartorio);

        var $sel = $('#cfgModeloPadrao').empty();
        modelos.filter(function (m) { return Number(m.ativo) === 1; })
            .forEach(function (m) {
                $sel.append($('<option>').attr('value', m.modelo_id)
                    .prop('selected', m.modelo_id === c.modelo_padrao)
                    .text(m.apelido + ' (' + m.modelo_id + ')'));
            });

        if (!$sel.children().length) {
            $sel.append('<option value="">Nenhum modelo ativo</option>');
        }
    }

    function desenharModelos() {
        var $tb = $('#tabelaModelos tbody').empty();

        if (!modelos.length) {
            $tb.append('<tr><td colspan="7" class="tf-mudo" style="text-align:center;padding:26px">'
                + 'Nenhum modelo cadastrado. Use "Cadastrar modelo" ou "Sincronizar modelos".</td></tr>');
            return;
        }

        modelos.forEach(function (m) {
            var ativo = Number(m.ativo) === 1;
            var favorito = Number(m.favorito) === 1;
            var disponivel = Number(m.disponivel_api) === 1;

            $tb.append(''
                + '<tr data-modelo="' + esc(m.modelo_id) + '">'
                + '<td><i class="fa fa-star' + (favorito ? '' : '-o') + '" data-favoritar'
                + ' style="cursor:pointer;color:' + (favorito ? '#f59e0b' : 'var(--tf-texto-3)') + '"'
                + ' title="Marcar como favorito"></i></td>'
                + '<td><strong>' + esc(m.apelido) + '</strong>'
                + (m.descricao ? '<br><span class="tf-mini tf-mudo">' + esc(m.descricao) + '</span>' : '')
                + '</td>'
                + '<td><code>' + esc(m.modelo_id) + '</code></td>'
                + '<td><span class="tf-selo tf-selo-contorno">' + esc(m.origem) + '</span></td>'
                + '<td>' + (disponivel
                    ? '<span class="tf-selo tf-selo-no-prazo">na API</span>'
                    : '<span class="tf-selo tf-selo-vencida" title="A chave configurada não lista este modelo. '
                      + 'Pode ter sido descontinuado ou renomeado.">não vista</span>') + '</td>'
                + '<td>' + (ativo
                    ? '<span class="tf-selo tf-selo-no-prazo">ativo</span>'
                    : '<span class="tf-selo tf-selo-encerrada">desativado</span>') + '</td>'
                + '<td><div style="display:flex;gap:4px">'
                + '<button class="tf-btn tf-btn-sm" data-testar title="Testar"><i class="fa fa-bolt"></i></button>'
                + '<button class="tf-btn tf-btn-sm" data-editar title="Editar"><i class="fa fa-pencil"></i></button>'
                + '<button class="tf-btn tf-btn-sm" data-alternar title="' + (ativo ? 'Desativar' : 'Ativar') + '">'
                + '<i class="fa fa-power-off"></i></button>'
                + '<button class="tf-btn tf-btn-sm tf-btn-perigo" data-excluir title="Excluir">'
                + '<i class="fa fa-trash-o"></i></button>'
                + '</div></td>'
                + '</tr>');
        });
    }

    function desenharEstatisticas(e) {
        $('#cfgEstatisticas').html(''
            + kpi('#3b82f6', 'fa-exchange', 'Chamadas', (e.chamadas || 0).toLocaleString('pt-BR'))
            + kpi('#7c3aed', 'fa-database', 'Tokens', (e.tokens || 0).toLocaleString('pt-BR'))
            + kpi('#dc2626', 'fa-times-circle', 'Falhas', (e.erros || 0).toLocaleString('pt-BR')));

        if (!e.por_recurso || !e.por_recurso.length) {
            $('#cfgPorRecurso').html('<p class="tf-mini tf-mudo">Nenhum uso registrado no período.</p>');
            return;
        }

        var max = Math.max.apply(null, e.por_recurso.map(function (r) { return Number(r.n); })) || 1;
        $('#cfgPorRecurso').html('<div class="tf-grafico"><h4>Uso por recurso</h4>'
            + e.por_recurso.map(function (r) {
                return '<div class="tf-barra-linha">'
                    + '<span class="tf-barra-nome">' + esc(r.recurso) + '</span>'
                    + '<span class="tf-barra-trilho"><span class="tf-barra-preench" style="width:'
                    + Math.round(Number(r.n) * 100 / max) + '%;--tf-cor:#7c3aed"></span></span>'
                    + '<span class="tf-barra-valor">' + r.n + '</span></div>';
            }).join('') + '</div>');
    }

    function kpi(cor, icone, rotulo, valor) {
        return '<div class="tf-kpi" style="--tf-cor:' + cor + ';cursor:default">'
            + '<div class="tf-kpi-valor">' + valor + '</div>'
            + '<div class="tf-kpi-rotulo"><i class="fa ' + icone + '"></i> ' + rotulo + '</div></div>';
    }

    /* ---------------------------------------------------------- */
    /* Configuração                                                */
    /* ---------------------------------------------------------- */

    $('#btnVerChave').on('click', function () {
        var $i = $('#cfgChave');
        var mostrar = $i.attr('type') === 'password';
        $i.attr('type', mostrar ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    $('#btnSalvarConfig').on('click', function () {
        var $b = $(this).prop('disabled', true);

        api({
            acao: 'config',
            api_key: $('#cfgChave').val(),
            modelo_padrao: $('#cfgModeloPadrao').val(),
            ativo: $('#cfgAtivo').val(),
            temperatura: $('#cfgTemperatura').val(),
            max_tokens: $('#cfgMaxTokens').val(),
            timeout: $('#cfgTimeout').val(),
            contexto_cartorio: $('#cfgContexto').val()
        })
            .done(function (r) {
                $b.prop('disabled', false);
                if (!r.success) { erro(r.error); return; }
                $('#cfgChave').val('');
                ok('Configuração salva.');
                carregar();
            })
            .fail(function (e) { $b.prop('disabled', false); erro(e.error); });
    });

    $('#btnRemoverChave').on('click', function () {
        Swal.fire({
            icon: 'warning',
            title: 'Remover a chave da API?',
            text: 'A integração será desativada e todos os recursos de IA deixarão de funcionar.',
            showCancelButton: true,
            confirmButtonText: 'Remover',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then(function (res) {
            if (!res.isConfirmed) { return; }
            api({ acao: 'config', remover_chave: '1' }).done(function () {
                ok('Chave removida.');
                carregar();
            });
        });
    });

    $('#btnTestar').on('click', function () {
        testar($('#cfgModeloPadrao').val());
    });

    function testar(modeloId) {
        $('#cfgResultadoTeste').html('<p class="tf-mini tf-mudo">'
            + '<i class="fa fa-circle-o-notch tf-girando"></i> Contatando a API…</p>');

        api({ acao: 'testar', modelo_id: modeloId || '' })
            .done(function (r) {
                if (!r.success) {
                    $('#cfgResultadoTeste').html(caixaResultado(false, r.error));
                    return;
                }
                $('#cfgResultadoTeste').html(caixaResultado(true,
                    r.mensagem + ' Tempo de resposta: ' + r.ms + ' ms.',
                    r.resposta));
            })
            .fail(function (e) {
                $('#cfgResultadoTeste').html(caixaResultado(false, e.error));
            });
    }

    function caixaResultado(sucesso, texto, detalhe) {
        var cor = sucesso ? 'var(--tf-sucesso)' : 'var(--tf-perigo)';
        var icone = sucesso ? 'fa-check-circle' : 'fa-times-circle';
        return '<div class="tf-bloco" style="border-color:' + cor + '">'
            + '<strong style="color:' + cor + '"><i class="fa ' + icone + '"></i> ' + esc(texto) + '</strong>'
            + (detalhe ? '<div class="tf-mini tf-mudo" style="margin-top:8px">Resposta do modelo: '
                + esc(detalhe) + '</div>' : '')
            + '</div>';
    }

    $('#btnSincronizar').on('click', function () {
        var $b = $(this).prop('disabled', true);
        $('#cfgResultadoTeste').html('<p class="tf-mini tf-mudo">'
            + '<i class="fa fa-circle-o-notch tf-girando"></i> Consultando a lista de modelos da sua chave…</p>');

        api({ acao: 'sincronizar' })
            .done(function (r) {
                $b.prop('disabled', false);
                if (!r.success) {
                    $('#cfgResultadoTeste').html(caixaResultado(false, r.error));
                    return;
                }
                $('#cfgResultadoTeste').html(caixaResultado(true, r.mensagem));
                carregar();
            })
            .fail(function (e) {
                $b.prop('disabled', false);
                $('#cfgResultadoTeste').html(caixaResultado(false, e.error));
            });
    });

    /* ---------------------------------------------------------- */
    /* Modelos                                                     */
    /* ---------------------------------------------------------- */

    function formModelo(modelo) {
        var editando = !!modelo;

        Swal.fire({
            title: editando ? 'Editar modelo' : 'Cadastrar modelo',
            width: 600,
            html: '<div style="text-align:left">'
                + '<label class="tf-rotulo">Identificador de API</label>'
                + '<input id="mdId" class="tf-input" placeholder="ex.: gemini-3.5-flash"'
                + ' value="' + esc(editando ? modelo.modelo_id : '') + '"'
                + (editando ? ' readonly' : '') + '>'
                + '<p class="tf-mini tf-mudo" style="margin:5px 0 12px">'
                + 'Exatamente como o Google publica. Atenção: o Gemini 3.1 Pro usa '
                + '<code>gemini-3.1-pro-preview</code>.</p>'
                + '<label class="tf-rotulo">Nome de exibição</label>'
                + '<input id="mdApelido" class="tf-input" placeholder="ex.: Gemini 3.5 Flash"'
                + ' value="' + esc(editando ? modelo.apelido : '') + '">'
                + '<label class="tf-rotulo" style="margin-top:12px">Descrição</label>'
                + '<textarea id="mdDesc" class="tf-textarea" rows="2"'
                + ' placeholder="Quando usar este modelo">' + esc(editando ? modelo.descricao : '') + '</textarea>'
                + '<label style="display:flex;gap:8px;align-items:center;margin-top:14px">'
                + '<input type="checkbox" id="mdAtivo"' + (!editando || Number(modelo.ativo) === 1 ? ' checked' : '') + '>'
                + '<span>Disponível para uso</span></label>'
                + '<label style="display:flex;gap:8px;align-items:center;margin-top:8px">'
                + '<input type="checkbox" id="mdArquivos"'
                + (!editando || Number(modelo.suporta_arquivos) === 1 ? ' checked' : '') + '>'
                + '<span>Aceita leitura de PDF e imagem</span></label>'
                + '</div>',
            showCancelButton: true,
            confirmButtonText: 'Salvar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusConfirm: false,
            preConfirm: function () {
                var id = txt($('#mdId').val());
                if (!id) {
                    Swal.showValidationMessage('Informe o identificador do modelo.');
                    return false;
                }
                return {
                    modelo_id: id,
                    apelido: txt($('#mdApelido').val()) || id,
                    descricao: $('#mdDesc').val(),
                    ativo: $('#mdAtivo').is(':checked') ? '1' : '0',
                    suporta_arquivos: $('#mdArquivos').is(':checked') ? '1' : '0'
                };
            }
        }).then(function (res) {
            if (!res.isConfirmed) { return; }

            api($.extend({ acao: 'salvar' }, res.value))
                .done(function (r) {
                    if (!r.success) { erro(r.error); return; }
                    ok('Modelo salvo.');
                    carregar();
                })
                .fail(function (e) { erro(e.error); });
        });
    }

    $('#btnNovoModelo').on('click', function () { formModelo(null); });

    $('#tabelaModelos').on('click', '[data-editar]', function () {
        var id = $(this).closest('tr').data('modelo');
        var m = modelos.filter(function (x) { return x.modelo_id === id; })[0];
        if (m) { formModelo(m); }
    });

    $('#tabelaModelos').on('click', '[data-testar]', function () {
        testar($(this).closest('tr').data('modelo'));
        $('html, body').animate({ scrollTop: $('#cfgResultadoTeste').offset().top - 120 }, 250);
    });

    $('#tabelaModelos').on('click', '[data-alternar]', function () {
        var id = $(this).closest('tr').data('modelo');
        var m = modelos.filter(function (x) { return x.modelo_id === id; })[0];
        if (!m) { return; }

        api({ acao: 'ativar', modelo_id: id, ativo: Number(m.ativo) === 1 ? '0' : '1' })
            .done(function (r) {
                if (!r.success) { erro(r.error); return; }
                carregar();
            })
            .fail(function (e) { erro(e.error); });
    });

    $('#tabelaModelos').on('click', '[data-favoritar]', function () {
        var $tr = $(this).closest('tr');
        var id = $tr.data('modelo');
        var m = modelos.filter(function (x) { return x.modelo_id === id; })[0];
        if (!m) { return; }

        api({ acao: 'favoritar', modelo_id: id, favorito: Number(m.favorito) === 1 ? '0' : '1' })
            .done(carregar);
    });

    $('#tabelaModelos').on('click', '[data-excluir]', function () {
        var id = $(this).closest('tr').data('modelo');

        Swal.fire({
            icon: 'warning',
            title: 'Excluir do catálogo?',
            html: 'O modelo <code>' + esc(id) + '</code> deixará de aparecer nas telas. '
                + 'Isso não afeta o histórico de uso já registrado.',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then(function (res) {
            if (!res.isConfirmed) { return; }

            api({ acao: 'excluir', modelo_id: id })
                .done(function (r) {
                    if (!r.success) { erro(r.error); return; }
                    ok('Modelo excluído.');
                    carregar();
                })
                .fail(function (e) { erro(e.error); });
        });
    });

    if (window.toastr) {
        toastr.options = { positionClass: 'toast-bottom-right', timeOut: 3000, progressBar: true };
    }

    <?php if (!$migracaoPendente): ?>
    carregar();
    <?php endif; ?>
});
</script>

</body>
</html>
