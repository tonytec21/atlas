<?php
/**
 * pe_config.php — Configuração da integração Parcela Express.
 * Acesso restrito a administradores.
 *
 * TEMA: a página não fixa cores. Ela deriva dos tokens que o Atlas já define
 * (--bg-elevated, --text-primary, --border-primary...), com valores de reserva
 * para o caso de a folha do sistema não estar carregada. É por isso que o modo
 * escuro funciona sem uma segunda folha de estilo: quando o body ganha
 * .dark-mode, os tokens mudam e esta tela acompanha.
 *
 * Os controles de ligar/desligar são desenhados aqui, não herdados do
 * Bootstrap. O custom-control do Bootstrap depende de cores próprias e some
 * no tema escuro — foi exatamente o que aconteceu com os rótulos.
 */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/pe_lib.php';

$erroFatal = null;
$cfg = [];

try {
    pe_migrar();
    $cfg = pe_config(true);
} catch (Throwable $e) {
    $erroFatal = $e->getMessage();
}

$pendencias = $cfg ? pe_pendencias($cfg) : ['Banco de dados indisponível'];
$ativo = !empty($cfg['ativo']);
$operante = $ativo && $pendencias === [];

$v = static fn($k, $d = '') => htmlspecialchars((string) ($cfg[$k] ?? $d), ENT_QUOTES, 'UTF-8');
$sel = static fn($k, $val) => (string) ($cfg[$k] ?? '') === (string) $val ? 'selected' : '';
$on = static fn($k) => !empty($cfg[$k]);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Online — Configuração</title>
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../ui-config.css">
</head>
<body>
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <div class="cfg cfg-pagina">

            <section class="cfg-hero">
                <div class="cfg-titulo">
                    <div class="cfg-icone"><i class="fa fa-credit-card" aria-hidden="true"></i></div>
                    <div>
                        <h1>Pagamento Online</h1>
                        <p class="cfg-sub">Cobrança na maquininha e emissão de boletos pela Parcela Express.</p>
                    </div>
                </div>
                <div class="cfg-selos">
                    <?php if ($operante): ?>
                        <span class="cfg-pill cfg-pill-ok"><i class="fa fa-check-circle"></i> Em operação</span>
                    <?php elseif ($ativo): ?>
                        <span class="cfg-pill cfg-pill-warn"><i class="fa fa-exclamation-triangle"></i> Ativo, incompleto</span>
                    <?php else: ?>
                        <span class="cfg-pill cfg-pill-off"><i class="fa fa-power-off"></i> Desativado</span>
                    <?php endif; ?>
                    <span class="cfg-pill cfg-pill-off"><?= ($cfg['ambiente'] ?? 'sandbox') === 'production' ? 'PRODUÇÃO' : 'SANDBOX' ?></span>
                </div>
            </section>

            <?php if ($erroFatal): ?>
                <div class="cfg-msg cfg-msg-erro" style="margin-top:18px">
                    <?= htmlspecialchars($erroFatal, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="cfg-aviso">
                <strong>Com o recurso desligado, a tela de O.S. funciona exatamente como hoje.</strong>
                Nenhum botão novo aparece e nenhum fluxo existente muda. Ao ligar, o modal
                <em>Efetuar Pagamento</em> ganha as abas de maquininha e boleto, ao lado do
                lançamento manual, que continua disponível.
            </div>

            <?php if ($ativo && $pendencias): ?>
                <div class="cfg-msg cfg-msg-warn" style="margin-top:18px">
                    <strong>Marcado como ativo, mas não entra em operação enquanto faltar:</strong>
                    <ul>
                        <?php foreach ($pendencias as $p): ?>
                            <li><?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="peForm" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(pe_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="cfg-card">
                    <header><i class="fa fa-power-off"></i><h2>Habilitação</h2></header>
                    <div class="cfg-corpo">
                        <label class="cfg-switch">
                            <input type="checkbox" id="ativo" name="ativo" value="1" <?= $on('ativo') ? 'checked' : '' ?>>
                            <span class="cfg-trilho"></span>
                            <span style="flex:1">
                                <b>Habilitar pagamento online nesta serventia</b>
                                <span class="cfg-desc">Desligar volta ao comportamento padrão na hora, sem perder configuração nem histórico de vendas.</span>
                            </span>
                        </label>

                        <div class="cfg-grid" style="margin-top:16px">
                            <div class="c6">
                                <label class="cfg-check">
                                    <input type="checkbox" id="habilitar_pos" name="habilitar_pos" value="1" <?= $on('habilitar_pos') ? 'checked' : '' ?>>
                                    <span class="cfg-box"></span>
                                    <span>
                                        <b>Cobrança na maquininha</b>
                                        <span class="cfg-desc">Cartão e PIX no terminal físico, com baixa automática na O.S.</span>
                                    </span>
                                </label>
                            </div>
                            <div class="c6">
                                <label class="cfg-check">
                                    <input type="checkbox" id="habilitar_boleto" name="habilitar_boleto" value="1" <?= $on('habilitar_boleto') ? 'checked' : '' ?>>
                                    <span class="cfg-box"></span>
                                    <span>
                                        <b>Emissão de boleto</b>
                                        <span class="cfg-desc">O pagamento só é lançado quando a compensação for confirmada.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cfg-card">
                    <header><i class="fa fa-key"></i><h2>Credenciais</h2></header>
                    <div class="cfg-corpo">
                        <div class="cfg-grid">
                            <div class="c4">
                                <label class="cfg-rot" for="ambiente">Ambiente</label>
                                <select class="cfg-in" id="ambiente" name="ambiente">
                                    <option value="sandbox" <?= $sel('ambiente', 'sandbox') ?>>Sandbox (homologação)</option>
                                    <option value="production" <?= $sel('ambiente', 'production') ?>>Produção</option>
                                </select>
                                <div class="cfg-dica">Comece sempre em sandbox.</div>
                            </div>
                            <div class="c8">
                                <label class="cfg-rot" for="seller_id">ID do estabelecimento</label>
                                <input type="text" class="cfg-in cfg-mono" id="seller_id" name="seller_id"
                                       value="<?= $v('seller_id') ?>" placeholder="ad9ebef1-e714-48d3-872c-e51911a6d4bf">
                                <div class="cfg-dica">No CartExpress: <em>Perfil › Dados do Estabelecimento › Id da Serventia</em>.</div>
                            </div>
                            <div class="c6">
                                <label class="cfg-rot" for="usuario">Usuário da API</label>
                                <input type="text" class="cfg-in" id="usuario" name="usuario" value="<?= $v('usuario') ?>">
                            </div>
                            <div class="c6">
                                <label class="cfg-rot" for="senha">Senha da API</label>
                                <input type="password" class="cfg-in" id="senha" name="senha"
                                       placeholder="<?= !empty($cfg['senha']) ? '•••••••• (mantida)' : '' ?>">
                                <div class="cfg-dica">Deixe em branco para manter a senha atual.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cfg-card">
                    <header><i class="fa fa-sliders"></i><h2>Ajustes</h2><small>opcional</small></header>
                    <div class="cfg-corpo">
                        <div class="cfg-grid">
                            <div class="c4">
                                <label class="cfg-rot" for="timeout_poll_seg">Intervalo de consulta</label>
                                <input type="number" class="cfg-in" id="timeout_poll_seg" name="timeout_poll_seg"
                                       min="1" max="30" value="<?= $v('timeout_poll_seg', '3') ?>">
                                <div class="cfg-dica">Segundos entre cada verificação de venda no terminal.</div>
                            </div>
                            <div class="c4">
                                <label class="cfg-rot" for="timeout_venda_seg">Prazo no terminal</label>
                                <input type="number" class="cfg-in" id="timeout_venda_seg" name="timeout_venda_seg"
                                       min="60" max="900" value="<?= $v('timeout_venda_seg', '300') ?>">
                                <div class="cfg-dica">Passado esse prazo a cobrança é interrompida.</div>
                            </div>
                            <div class="c4">
                                <label class="cfg-rot" for="base_url">URL base da API</label>
                                <input type="text" class="cfg-in cfg-mono" id="base_url" name="base_url"
                                       value="<?= $v('base_url') ?>" placeholder="padrão do ambiente">
                                <div class="cfg-dica">Só se a Parcela Express indicar outro endereço.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cfg-card">
                    <header><i class="fa fa-stethoscope"></i><h2>Verificação</h2></header>
                    <div class="cfg-corpo">
                        <div class="cfg-ferramentas">
                            <button type="button" class="cfg-btn cfg-btn-marca" id="btnTestar">
                                <i class="fa fa-plug"></i> Testar conexão
                            </button>
                            <a href="pe_diagnostico.php" class="cfg-btn cfg-btn-neutro"><i class="fa fa-list-alt"></i> Verificar instalação</a>
                            <a href="pe_rotas.php" class="cfg-btn cfg-btn-neutro"><i class="fa fa-sitemap"></i> Rotas da API</a>
                            <a href="pe_schema.php" class="cfg-btn cfg-btn-neutro"><i class="fa fa-code"></i> Formato dos envios</a>
                        </div>
                        <div class="cfg-dica" style="margin-top:11px">
                            <em>Testar conexão</em> lista os terminais vinculados.
                            <em>Verificar instalação</em> confere tabelas e colunas — use se o botão não aparecer na O.S.
                            <em>Rotas</em> e <em>Formato</em> resolvem respostas "Cannot POST" e "Bad Request".
                        </div>
                        <div class="cfg-resultado" id="resultadoTeste"></div>
                    </div>
                </div>

                <div class="cfg-fim">
                    <p class="cfg-nota">
                        <?php if (!empty($cfg['atualizado_em'])): ?>
                            Última alteração em <?= $v('atualizado_em') ?> por <?= $v('atualizado_por') ?>.
                        <?php else: ?>
                            Nenhuma alteração registrada ainda.
                        <?php endif; ?>
                    </p>
                    <button type="button" class="cfg-btn cfg-btn-forte cfg-btn-grande cfg-btn-bloco" id="btnSalvar">
                        <i class="fa fa-save"></i> Salvar configuração
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../../style/js/jquery.min.js"></script>
<script src="../../style/js/bootstrap.bundle.min.js"></script>
<script src="../../style/js/sweetalert2.all.min.js"></script>
<script>
/* O jQuery reduz qualquer HTTP >= 400 a falha genérica e descarta o corpo.
   Nossos endpoints devolvem {error: "..."} com status semântico, então
   lemos o responseJSON para mostrar o motivo real. */
function peErroDoServidor(xhr, alternativa) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.error) return xhr.responseJSON.error;

    if (xhr && xhr.responseText) {
        try {
            var j = JSON.parse(xhr.responseText);
            if (j && j.error) return j.error;
        } catch (e) {
            return 'O servidor respondeu em formato inesperado. Verifique o log de erros do PHP.';
        }
    }

    return alternativa || 'Falha de comunicação com o servidor.';
}

$('#btnSalvar').on('click', function () {
    var btn = $(this).prop('disabled', true);

    $.post('pe_salvar_config.php', $('#peForm').serialize(), function (r) {
        if (r && r.success) {
            Swal.fire({ icon: 'success', title: 'Configuração salva', text: r.mensagem || '' })
                .then(function () { location.reload(); });
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: (r && r.error) || 'Falha ao salvar.' });
        }
    }, 'json')
    .fail(function (xhr) {
        Swal.fire({ icon: 'error', title: 'Erro', text: peErroDoServidor(xhr, 'Falha ao salvar.') });
    })
    .always(function () { btn.prop('disabled', false); });
});

$('#btnTestar').on('click', function () {
    var btn = $(this).prop('disabled', true);
    var box = $('#resultadoTeste')
        .html('<div class="cfg-msg cfg-msg-warn"><i class="fa fa-spinner fa-spin"></i> Consultando…</div>');

    function falha(msg, detalhe) {
        var html = '<div class="cfg-msg cfg-msg-erro"><strong>Não foi possível consultar os terminais.</strong><br>' +
                   $('<div>').text(msg).html();

        if (detalhe) html += '<br><code>' + $('<div>').text(detalhe).html() + '</code>';

        html += '<br><br>As rotas estão confirmadas contra o spec da Parcela Express. ' +
                'Falha aqui costuma ser credencial recusada, estabelecimento sem POS vinculado ' +
                'ou serviço fora do ar — não caminho errado.</div>';

        box.html(html);
    }

    $.get('pe_terminais.php', { csrf: $('input[name=csrf]').val() }, function (r) {
        if (!r || !r.success) { falha((r && r.error) || 'Falha na consulta.', r && r.detalhe); return; }

        if (!r.terminais || !r.terminais.length) {
            box.html('<div class="cfg-msg cfg-msg-warn">Conexão bem-sucedida, mas nenhum terminal está vinculado a esta conta. ' +
                     'Solicite um terminal de homologação à Parcela Express.</div>');
            return;
        }

        var html = '<div class="cfg-msg cfg-msg-ok"><strong>Conexão OK.</strong> ' +
                   r.terminais.length + ' terminal(is) encontrado(s):<ul>';

        r.terminais.forEach(function (t) {
            html += '<li>' + $('<div>').text(t.name || t.serial_number || t.id).html() + '</li>';
        });

        box.html(html + '</ul></div>');
    }, 'json')
    .fail(function (xhr) {
        falha(peErroDoServidor(xhr, 'Falha na consulta.'),
              xhr && xhr.responseJSON ? xhr.responseJSON.detalhe : null);
    })
    .always(function () { btn.prop('disabled', false); });
});
</script>
</body>
</html>
