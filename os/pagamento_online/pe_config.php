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
                    <span class="cfg-pill cfg-pill-off">v<?= htmlspecialchars(PE_VERSAO, ENT_QUOTES, 'UTF-8') ?></span>
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
                    <header><i class="fa fa-key"></i><h2>Acesso à Parcela Express</h2></header>
                    <div class="cfg-corpo">
                        <div class="cfg-grid">
                            <div class="c4">
                                <label class="cfg-rot" for="ambiente">Ambiente</label>
                                <select class="cfg-in" id="ambiente" name="ambiente">
                                    <option value="sandbox" <?= $sel('ambiente', 'sandbox') ?>>Sandbox (homologação)</option>
                                    <option value="production" <?= $sel('ambiente', 'production') ?>>Produção</option>
                                </select>
                            </div>
                            <div class="c4">
                                <label class="cfg-rot" for="usuario">E-mail</label>
                                <input type="text" class="cfg-in" id="usuario" name="usuario" value="<?= $v('usuario') ?>" autocomplete="off">
                            </div>
                            <div class="c4">
                                <label class="cfg-rot" for="senha">Senha</label>
                                <input type="password" class="cfg-in" id="senha" name="senha" autocomplete="new-password"
                                       placeholder="<?= !empty($cfg['senha']) ? '•••••••• (mantida)' : '' ?>">
                            </div>
                        </div>
                        <div class="cfg-grid" style="margin-top:12px">
                            <div class="c12">
                                <label class="cfg-rot" for="seller_id">ID do estabelecimento <small>(opcional)</small></label>
                                <input type="text" class="cfg-in cfg-mono" id="seller_id" name="seller_id" value="<?= $v('seller_id') ?>" placeholder="em branco = o estabelecimento informado pelo login">
                            </div>
                        </div>
                        <div class="cfg-dica" style="margin-top:8px">
                            Preencha o ID do estabelecimento só quando a cobrança precisar sair em nome de um estabelecimento diferente do devolvido pelo login.
                            No sandbox, com e-mail e senha em branco, é usada a conta de homologação da Parcela Express.
                            Em produção, informe o e-mail e a senha da serventia. Senha em branco mantém a atual.
                        </div>
                    </div>
                </div>

                <div class="cfg-card">
                    <header><i class="fa fa-credit-card"></i><h2>Maquininhas</h2></header>
                    <div class="cfg-corpo">
                        <textarea class="cfg-in cfg-mono" id="terminais" name="terminais" rows="3" placeholder="serial | nome (uma por linha)&#10;PB0123456789 | Balcão 1"><?= $v('terminais') ?></textarea>
                        <div class="cfg-dica">Aparecem na lista ao cobrar. Uma por linha: <code>serial | nome</code>. O operador também pode digitar o serial na hora.</div>
                    </div>
                </div>

                <div class="cfg-card">
                    <header><i class="fa fa-plug"></i><h2>Verificação</h2></header>
                    <div class="cfg-corpo">
                        <button type="button" class="cfg-btn cfg-btn-marca" id="btnTestar">
                            <i class="fa fa-plug"></i> Testar conexão
                        </button>
                        <a href="pe_teste_boleto.php" class="cfg-btn cfg-btn-neutro"><i class="fa fa-barcode"></i> Boleto de teste</a>
                        <div class="cfg-dica" style="margin-top:11px">Usa a configuração salva: faz o login e mostra a conta e o estabelecimento que vão emitir.</div>
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
        .html('<div class="cfg-msg cfg-msg-warn"><i class="fa fa-spinner fa-spin"></i> Testando… (usa a configuração já salva)</div>');
    var h = function (t) { return $('<div>').text(t == null ? '' : String(t)).html(); };

    $.get('pe_testar_conexao.php', { csrf: $('input[name=csrf]').val() }, function (r) {
        if (!r || !r.success) {
            box.html('<div class="cfg-msg cfg-msg-erro"><strong>Falha no teste.</strong><br>' + h((r && r.error) || 'Falha na consulta.') + '</div>');
            return;
        }

        var html = '<div class="cfg-msg ' + (r.login ? 'cfg-msg-ok' : 'cfg-msg-erro') + '">';
        html += '<strong>' + (r.login ? 'Login OK.' : 'Login recusado.') + '</strong> ' + h(r.login_msg || '');
        if (r.conta) html += '<br>Conta: <b>' + h(r.conta) + '</b>';
        if (r.seller_nome || r.seller_documento) html += '<br>Estabelecimento emissor: <b>' + h(r.seller_nome || '') + '</b>' + (r.seller_documento ? ' — documento ' + h(r.seller_documento) : '');
        if (r.seller) html += '<br>ID do estabelecimento: <code>' + h(r.seller) + '</code> (' + h(r.seller_origem) + ')';
        if (r.aviso_seller) html += '<br><b>Atenção:</b> ' + h(r.aviso_seller);
        html += '</div>';

        if (r.login) {
            if (r.terminais && r.terminais.length) {
                html += '<div class="cfg-msg cfg-msg-ok"><strong>' + r.terminais.length + ' maquininha(s):</strong><ul>';
                r.terminais.forEach(function (t) { html += '<li>' + h(t.name || t.id) + '</li>'; });
                html += '</ul></div>';
            } else {
                html += '<div class="cfg-msg cfg-msg-warn">' + h(r.terminais_msg || 'A conta não listou maquininhas. Cadastre-as em "Maquininhas da serventia" ou digite o serial na hora de cobrar.') + '</div>';
            }
        }

        box.html(html);
    }, 'json')
    .fail(function (xhr) {
        box.html('<div class="cfg-msg cfg-msg-erro"><strong>Falha no teste.</strong><br>' + h(peErroDoServidor(xhr, 'Falha na consulta.')) + '</div>');
    })
    .always(function () { btn.prop('disabled', false); });
});
</script>
</body>
</html>
