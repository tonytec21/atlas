<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_iris.php';
iris_ensure_schema();
if (!iris_is_admin()) { header('Location: index.php'); exit; }

$CSRF = iris_csrf();
$cfg = iris_config();
$modelos = iris_modelos();
$ativos = iris_modelos(true);
$tipos = iris_tipos(false);
$temChave = iris_tem_chave();
$pub = iris_config_publica();
function eh($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function sel($a, $b){ return (string)$a === (string)$b ? ' selected' : ''; }
$v = rawurlencode(IRIS_VERSAO);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas Iris · Configurar</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="stylesheet" href="assets/iris.css?v=<?php echo $v; ?>">
<style>
#main .container.ir-container{ max-width:1000px; }
.nav-cfg{ display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
.nav-cfg a{ font-size:.84rem; font-weight:700; color:var(--ir-muted); background:var(--ir-card); border:1px solid var(--ir-border); border-radius:999px; padding:6px 13px; text-decoration:none!important; }
.nav-cfg a:hover{ color:var(--ir-primary); border-color:var(--ir-primary); }
.card-blk h5{ font-weight:800; font-size:1.02rem; color:var(--ir-text); margin:0 0 4px; display:flex; align-items:center; gap:9px; }
.card-blk .hint{ color:var(--ir-muted); font-size:.85rem; margin-bottom:16px; }
.field{ margin-bottom:14px; } .field > label{ font-size:.8rem; font-weight:700; color:var(--ir-muted); margin-bottom:6px; display:block; }
.field .help{ font-size:.78rem; color:var(--ir-muted); margin-top:4px; }
textarea.inp{ resize:vertical; min-height:90px; }
.row2{ display:grid; grid-template-columns:1fr 1fr; gap:14px; } .row3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
@media(max-width:700px){ .row2,.row3{ grid-template-columns:1fr; } }
.acts{ display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
.mdl{ display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid var(--ir-border); border-radius:14px; margin-bottom:8px; flex-wrap:wrap; }
.mdl.padrao{ border-color:var(--ir-primary); background:rgba(192,38,211,.05); }
.mdl.inativo{ opacity:.55; }
.mdl-ic{ width:38px;height:38px;border-radius:10px;background:rgba(192,38,211,.12);color:var(--ir-primary);display:flex;align-items:center;justify-content:center;flex:0 0 auto; }
.mdl-nome{ font-weight:700; color:var(--ir-text); } .mdl-id{ font-size:.78rem; color:var(--ir-muted); font-family:monospace; } .mdl-desc{ font-size:.8rem; color:var(--ir-muted); }
.badge-padrao{ font-size:.68rem; font-weight:700; padding:3px 9px; border-radius:999px; background:var(--ir-primary); color:#fff; vertical-align:middle; }
.mdl-acts{ margin-left:auto; display:flex; gap:6px; }
.ib.rm:hover{ background:#fee2e2;color:#b91c1c;border-color:transparent; }
.addform{ display:grid; grid-template-columns:1.2fr 1fr auto; gap:10px; align-items:end; } @media(max-width:640px){ .addform{ grid-template-columns:1fr; } }
.disp{ margin-top:12px; max-height:260px; overflow:auto; border:1px solid var(--ir-border); border-radius:12px; display:none; }
.disp div{ display:flex; align-items:center; gap:10px; padding:8px 12px; border-bottom:1px solid var(--ir-border); font-size:.85rem; }
.disp div:last-child{ border-bottom:0; } .disp code{ flex:1; color:var(--ir-text); }
.kpis{ display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:14px; } @media(max-width:700px){ .kpis{ grid-template-columns:1fr 1fr; } }
.kpi{ background:var(--ir-bg); border-radius:12px; padding:12px 14px; } .kpi b{ display:block; font-size:1.4rem; color:var(--ir-text); } .kpi span{ font-size:.78rem; color:var(--ir-muted); }
.barras{ display:flex; align-items:flex-end; gap:3px; height:90px; padding:6px 0; border-bottom:1px solid var(--ir-border); margin-bottom:6px; }
.barras div{ flex:1; background:linear-gradient(180deg,var(--ir-primary),var(--ir-primary2)); border-radius:3px 3px 0 0; min-height:2px; opacity:.85; }
.barras div.z{ background:var(--ir-border); }
.tb{ width:100%; font-size:.84rem; border-collapse:collapse; } .tb th{ color:var(--ir-muted); font-size:.74rem; text-transform:uppercase; text-align:left; padding:6px 8px; } .tb td{ padding:7px 8px; border-top:1px solid var(--ir-border); color:var(--ir-text); }
.tb td.n,.tb th.n{ text-align:right; font-variant-numeric:tabular-nums; }
/* modal de tipo */
.mo{ position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:1050; display:none; align-items:flex-start; justify-content:center; padding:30px 12px; overflow:auto; }
.mo.on{ display:flex; }
.mo-box{ background:var(--ir-card); color:var(--ir-text); border-radius:18px; width:100%; max-width:980px; padding:20px; box-shadow:0 30px 60px rgba(0,0,0,.3); }
.mo-head{ display:flex; align-items:center; gap:10px; margin-bottom:14px; } .mo-head h5{ margin:0; font-weight:800; flex:1; }
.cf{ display:grid; grid-template-columns:1.1fr 1.3fr 1fr 1.6fr auto; gap:6px; align-items:start; margin-bottom:6px; }
.cf .inp{ padding:7px 9px; font-size:.84rem; }
.cf-h{ font-size:.72rem; font-weight:700; color:var(--ir-muted); text-transform:uppercase; }
.cf .sub{ grid-column:1 / -2; margin:-2px 0 6px; }
@media(max-width:760px){ .cf{ grid-template-columns:1fr 1fr; } .cf-h{ display:none; } }
.tp-campos{ font-size:.78rem; color:var(--ir-muted); }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container ir-container">

    <section class="ir-hero">
      <div class="ir-title-row">
        <div class="ir-ic"><i class="fa fa-cog"></i></div>
        <div style="min-width:0"><h1>Configurar · Atlas Iris <span class="ir-ver">v<?php echo eh(IRIS_VERSAO); ?></span></h1><div class="ir-sub">API, qualidade de leitura, modelos, tipos de documento, armazenamento e uso.</div></div>
        <div class="ir-actions"><a class="ir-pill ir-soft" href="index.php"><i class="fa fa-arrow-left"></i> Voltar</a></div>
      </div>
    </section>

    <nav class="nav-cfg">
      <a href="#s-api">Chave da API</a><a href="#s-leitura">Leitura e manuscritos</a><a href="#s-modelos">Modelos</a>
      <a href="#s-tipos">Tipos de documento</a><a href="#s-arm">Armazenamento</a><a href="#s-uso">Uso</a>
    </nav>

    <!-- API -->
    <div class="ir-card card-blk" id="s-api">
      <h5><i class="fa fa-key" style="color:var(--ir-primary)"></i> Chave da API do Gemini</h5>
      <div class="hint">Obtenha em <b>Google AI Studio</b> (aistudio.google.com/apikey). A chave é guardada criptografada (AES-256-GCM) e enviada ao Google apenas no cabeçalho das requisições.</div>
      <div class="field">
        <label for="apiKey">Chave da API</label>
        <input class="inp" type="password" id="apiKey" autocomplete="off" placeholder="<?php echo $temChave ? '•••••••••••••••• (em branco para manter a atual)' : 'AIza...'; ?>">
      </div>
      <div class="acts">
        <button class="ir-pill ir-soft" id="btnTestar" type="button"><i class="fa fa-plug"></i> Testar e listar modelos</button>
        <button class="ir-pill ir-pri" id="btnSalvarApi" type="button"><i class="fa fa-check"></i> Salvar chave</button>
      </div>
      <div class="disp" id="dispModelos"></div>
    </div>

    <!-- Leitura -->
    <div class="ir-card card-blk" id="s-leitura">
      <h5><i class="fa fa-pencil" style="color:var(--ir-primary)"></i> Leitura e manuscritos</h5>
      <div class="hint">No modo <b>Máxima precisão</b>, cada página é enviada inteira e em duas ampliações, e depois passa por uma <b>segunda leitura de verificação</b> com o modelo abaixo. A releitura de regiões também usa esse modelo.</div>
      <div class="row3">
        <div class="field"><label for="cPrec">Precisão padrão</label>
          <select class="inp" id="cPrec"><option value="padrao"<?php echo sel($pub['precisao_padrao'], 'padrao'); ?>>Padrão</option><option value="maxima"<?php echo sel($pub['precisao_padrao'], 'maxima'); ?>>Máxima (manuscritos)</option></select></div>
        <div class="field"><label for="cVerif">Modelo de verificação</label>
          <select class="inp" id="cVerif"><option value="">(o mesmo da leitura)</option><?php foreach ($ativos as $m): ?><option value="<?php echo eh($m['identificador']); ?>"<?php echo sel($cfg['modelo_verificacao'] ?? '', $m['identificador']); ?>><?php echo eh($m['rotulo']); ?></option><?php endforeach; ?></select>
          <div class="help">Recomendado: o modelo mais preciso (Pro).</div></div>
        <div class="field"><label for="cReserva">Modelo reserva</label>
          <select class="inp" id="cReserva"><option value="">(nenhum)</option><?php foreach ($ativos as $m): ?><option value="<?php echo eh($m['identificador']); ?>"<?php echo sel($cfg['modelo_reserva'] ?? '', $m['identificador']); ?>><?php echo eh($m['rotulo']); ?></option><?php endforeach; ?></select>
          <div class="help">Usado automaticamente se o principal estiver sobrecarregado ou indisponível.</div></div>
      </div>
      <div class="row2">
        <div class="field"><label for="cQual">Resolução de envio (lado maior)</label>
          <select class="inp" id="cQual"><?php foreach ([1800 => '1800 px — econômica', 2400 => '2400 px — recomendada', 3000 => '3000 px — alta', 3600 => '3600 px — muito alta'] as $px => $r): ?><option value="<?php echo $px; ?>"<?php echo sel($pub['qualidade_px'], $px); ?>><?php echo $r; ?></option><?php endforeach; ?></select>
          <div class="help">No modo Máxima usa-se no mínimo 3000 px.</div></div>
        <div class="field"><label for="cSimult">Páginas em paralelo</label>
          <select class="inp" id="cSimult"><?php for ($i = 1; $i <= 4; $i++): ?><option value="<?php echo $i; ?>"<?php echo sel($pub['paginas_simultaneas'], $i); ?>><?php echo $i; ?></option><?php endfor; ?></select>
          <div class="help">Reduza se aparecer "limite de uso da API".</div></div>
      </div>
      <div class="field">
        <label for="cVoc">Vocabulário da serventia</label>
        <textarea class="inp" id="cVoc" rows="5" placeholder="Um termo por linha ou separados por vírgula. Ex.:&#10;Esperantinópolis, São Roberto, Poção de Pedras&#10;José Ribamar Costa (oficial 1950–1972)&#10;Raimunda, Sebastião, Conceição, Nonato"><?php echo eh($cfg['vocabulario'] ?? ''); ?></textarea>
        <div class="help">Nomes de municípios, povoados, oficiais e escreventes antigos, sobrenomes frequentes e termos locais. Ajuda a IA a <b>desempatar</b> leituras difíceis de manuscritos — ela é instruída a nunca substituir o que está claramente escrito.</div>
      </div>
      <div class="field">
        <label for="cExtra">Instruções adicionais para a extração (opcional)</label>
        <textarea class="inp" id="cExtra" rows="3" placeholder="Ex.: ignorar o cabeçalho impresso do formulário; transcrever o selo digital por completo."><?php echo eh($cfg['prompt_extra'] ?? ''); ?></textarea>
      </div>
      <div class="acts"><button class="ir-pill ir-pri" id="btnSalvarLeitura"><i class="fa fa-check"></i> Salvar</button></div>
    </div>

    <!-- Modelos -->
    <div class="ir-card card-blk" id="s-modelos">
      <h5><i class="fa fa-cubes" style="color:var(--ir-primary)"></i> Modelos de extração</h5>
      <div class="hint">O modelo <b>padrão</b> é o usado em todas as extrações (os usuários não escolhem). O identificador é o nome de API do Gemini (ex.: <code>gemini-3.1-pro-preview</code>). Use “Testar e listar modelos” acima para ver os disponíveis na sua chave.</div>
      <div id="listaModelos">
        <?php foreach ($modelos as $m): ?>
          <div class="mdl <?php echo $m['padrao'] ? 'padrao' : ''; ?>" data-id="<?php echo (int)$m['id']; ?>">
            <div class="mdl-ic"><i class="fa fa-cube"></i></div>
            <div style="min-width:0">
              <div class="mdl-nome"><?php echo eh($m['rotulo']); ?> <?php echo $m['padrao'] ? '<span class="badge-padrao">PADRÃO</span>' : ''; ?>
                <?php if (($cfg['modelo_verificacao'] ?? '') === $m['identificador']): ?><span class="bdg info">verificação</span><?php endif; ?>
                <?php if (($cfg['modelo_reserva'] ?? '') === $m['identificador']): ?><span class="bdg info">reserva</span><?php endif; ?></div>
              <div class="mdl-id"><?php echo eh($m['identificador']); ?></div>
              <?php if (!empty($m['descricao'])): ?><div class="mdl-desc"><?php echo eh($m['descricao']); ?></div><?php endif; ?>
            </div>
            <div class="mdl-acts">
              <?php if (!$m['padrao']): ?><button class="ib js-padrao" title="Definir como padrão"><i class="fa fa-star-o"></i></button><?php endif; ?>
              <button class="ib js-editar" title="Editar" data-rotulo="<?php echo eh($m['rotulo']); ?>" data-desc="<?php echo eh($m['descricao'] ?? ''); ?>"><i class="fa fa-pencil"></i></button>
              <button class="ib rm js-excluir" title="Excluir"><i class="fa fa-trash-o"></i></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="border-top:1px solid var(--ir-border);margin-top:14px;padding-top:14px">
        <div class="field" style="margin-bottom:8px"><label>Cadastrar novo modelo</label></div>
        <div class="addform">
          <div><input class="inp" id="novoId" placeholder="Identificador (ex.: gemini-3.1-pro-preview)"></div>
          <div><input class="inp" id="novoRotulo" placeholder="Nome amigável (ex.: Gemini 3.1 Pro)"></div>
          <button class="ir-pill ir-pri" id="btnAddModelo" type="button" style="justify-content:center"><i class="fa fa-plus"></i> Adicionar</button>
        </div>
        <input class="inp" id="novoDesc" placeholder="Descrição (opcional)" style="margin-top:10px">
      </div>
    </div>

    <!-- Tipos -->
    <div class="ir-card card-blk" id="s-tipos">
      <h5><i class="fa fa-table" style="color:var(--ir-primary)"></i> Tipos de documento</h5>
      <div class="hint">Definem os campos da extração estruturada. Os tipos ativos aparecem para os usuários e são usados na detecção automática. Crie tipos próprios da serventia (requerimentos, formulários, fichas).</div>
      <div id="listaTipos">
        <?php foreach ($tipos as $t): ?>
          <div class="mdl <?php echo $t['ativo'] ? '' : 'inativo'; ?>" data-id="<?php echo (int)$t['id']; ?>">
            <div class="mdl-ic"><i class="fa fa-file-text-o"></i></div>
            <div style="min-width:0;flex:1">
              <div class="mdl-nome"><?php echo eh($t['nome']); ?> <?php if ($t['sistema']): ?><span class="bdg info">de fábrica</span><?php endif; ?><?php if (!$t['ativo']): ?> <span class="bdg warn">inativo</span><?php endif; ?></div>
              <?php if (!empty($t['descricao'])): ?><div class="mdl-desc"><?php echo eh($t['descricao']); ?></div><?php endif; ?>
              <div class="tp-campos"><?php echo count($t['campos']); ?> campos: <?php echo eh(implode(', ', array_slice(array_column($t['campos'], 'rotulo'), 0, 6))); ?><?php echo count($t['campos']) > 6 ? '…' : ''; ?></div>
            </div>
            <div class="mdl-acts">
              <button class="ib js-tipo-ativo" title="<?php echo $t['ativo'] ? 'Desativar' : 'Ativar'; ?>" data-ativo="<?php echo $t['ativo'] ? '0' : '1'; ?>"><i class="fa <?php echo $t['ativo'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i></button>
              <button class="ib js-tipo-editar" title="Editar campos"><i class="fa fa-pencil"></i></button>
              <?php if (!$t['sistema']): ?><button class="ib rm js-tipo-excluir" title="Excluir"><i class="fa fa-trash-o"></i></button><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="acts" style="margin-top:12px">
        <button class="ir-pill ir-soft" id="btnRestaurarTipos"><i class="fa fa-undo"></i> Restaurar tipos de fábrica</button>
        <button class="ir-pill ir-pri" id="btnNovoTipo"><i class="fa fa-plus"></i> Novo tipo</button>
      </div>
    </div>

    <!-- Armazenamento -->
    <div class="ir-card card-blk" id="s-arm">
      <h5><i class="fa fa-database" style="color:var(--ir-primary)"></i> Armazenamento</h5>
      <div class="hint">O texto e os dados de cada extração ficam no histórico. O arquivo original pode ser guardado (na pasta <code>arquivos/</code>, protegida) para reabrir o documento lado a lado com o texto.</div>
      <div class="row2">
        <div class="field"><label>&nbsp;</label><label class="ck"><input type="checkbox" id="cGuardar"<?php echo $pub['guardar_arquivos'] ? ' checked' : ''; ?>> Guardar o arquivo original de cada extração</label>
          <div class="help">Limite atual de envio do PHP: <?php echo iris_human($pub['max_upload']); ?> (upload_max_filesize / post_max_size).</div></div>
        <div class="field"><label for="cRet">Manter os arquivos originais por (dias)</label><input class="inp" type="number" min="0" max="3650" id="cRet" value="<?php echo (int)($cfg['retencao_dias'] ?? 180); ?>">
          <div class="help">0 = para sempre. O texto e os dados não são apagados.</div></div>
      </div>
      <div class="acts">
        <button class="ir-pill ir-soft" id="btnLimpar"><i class="fa fa-eraser"></i> Aplicar retenção agora</button>
        <button class="ir-pill ir-pri" id="btnSalvarArm"><i class="fa fa-check"></i> Salvar</button>
      </div>
    </div>

    <!-- Uso -->
    <div class="ir-card card-blk" id="s-uso">
      <h5><i class="fa fa-bar-chart" style="color:var(--ir-primary)"></i> Uso <select class="inp" id="usoDias" style="width:auto;margin-left:auto;padding:5px 10px;font-size:.84rem"><option value="7">7 dias</option><option value="30" selected>30 dias</option><option value="90">90 dias</option><option value="365">12 meses</option></select></h5>
      <div class="hint">Chamadas à API e tokens consumidos (para acompanhar custos no Google AI Studio).</div>
      <div id="usoCorpo"><div class="vazio"><i class="fa fa-spinner fa-spin"></i></div></div>
    </div>
  </div>
</div>

<!-- Modal de tipo de documento -->
<div class="mo" id="moTipo">
  <div class="mo-box">
    <div class="mo-head"><h5 id="moTitulo">Tipo de documento</h5><button class="ib" id="moFechar"><i class="fa fa-times"></i></button></div>
    <input type="hidden" id="tId">
    <div class="row2">
      <div class="field"><label for="tNome">Nome</label><input class="inp" id="tNome" placeholder="Ex.: Requerimento de 2ª via de certidão"></div>
      <div class="field"><label for="tDesc">Descrição (ajuda na detecção automática)</label><input class="inp" id="tDesc" placeholder="Ex.: formulário da serventia pedindo 2ª via"></div>
    </div>
    <div class="field"><label for="tInstr">Instruções específicas (opcional)</label><textarea class="inp" id="tInstr" rows="2" style="min-height:60px" placeholder="Ex.: o número do protocolo fica no canto superior direito."></textarea></div>
    <div class="field" style="margin-bottom:6px"><label>Campos</label></div>
    <div class="cf"><span class="cf-h">Rótulo</span><span class="cf-h">Dica para a IA (opcional)</span><span class="cf-h">Tipo</span><span class="cf-h">Colunas (só para lista)</span><span></span></div>
    <div id="tCampos"></div>
    <div style="display:flex;gap:8px;justify-content:space-between;flex-wrap:wrap;margin-top:8px">
      <button class="tbtn" id="tAddCampo"><i class="fa fa-plus"></i> Adicionar campo</button>
      <label class="ck"><input type="checkbox" id="tAtivo" checked> Ativo</label>
    </div>
    <div class="help muted" style="margin-top:8px">Colunas de lista: separe por vírgula; para validar, acrescente o tipo após dois-pontos. Ex.: <code>Nome, CPF/CNPJ:cpf_cnpj, Nascimento:data</code></div>
    <div class="acts" style="margin-top:14px"><button class="ir-pill ir-soft" id="moCancelar">Cancelar</button><button class="ir-pill ir-pri" id="moSalvar"><i class="fa fa-check"></i> Salvar tipo</button></div>
  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>
(function () {
  'use strict';
  var CSRF = <?php echo json_encode($CSRF); ?>;
  var TIPOS_CAMPO = <?php echo json_encode(iris_tipos_campo(), JSON_UNESCAPED_UNICODE); ?>;
  function el(id) { return document.getElementById(id); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function num(n) { return (+n || 0).toLocaleString('pt-BR'); }
  function api(acao, dados) {
    var fd = new FormData(); Object.keys(dados || {}).forEach(function (k) { if (dados[k] !== undefined && dados[k] !== null) fd.append(k, dados[k]); });
    fd.append('acao', acao); fd.append('csrf', CSRF);
    return fetch('api.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) {
      var j; try { j = JSON.parse(t); } catch (e) { throw new Error('Resposta inválida: ' + t.slice(0, 160)); }
      if (j.status !== 'success') throw new Error(j.message || 'Falha.'); return j;
    });
  }
  function ok(msg, recarregar) { return Swal.fire({ icon: 'success', title: msg || 'Salvo!', timer: 1300, showConfirmButton: false }).then(function () { if (recarregar) location.reload(); }); }
  function erro(e) { Swal.fire('Erro', e.message, 'error'); }
  function ocupado(btn, f) { var h = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Aguarde…'; return f().finally(function () { btn.disabled = false; btn.innerHTML = h; }); }

  /* API */
  el('btnSalvarApi').onclick = function () {
    var k = el('apiKey').value.trim(); if (!k) { Swal.fire('Atenção', 'Informe a chave.', 'warning'); return; }
    ocupado(this, function () { return api('config_salvar', { api_key: k }).then(function () { return ok('Chave salva', true); }).catch(erro); });
  };
  el('btnTestar').onclick = function () {
    var k = el('apiKey').value.trim();
    ocupado(this, function () {
      return api('testar_chave', { api_key: k }).then(function (j) {
        var box = el('dispModelos'); box.style.display = 'block';
        box.innerHTML = '<div style="background:var(--ir-okbg);color:var(--ir-ok);font-weight:700"><i class="fa fa-check-circle"></i> ' + esc(j.message) + '</div>'
          + j.modelos.map(function (m) {
            return '<div><code>' + esc(m.identificador) + '</code><span class="muted">' + esc(m.rotulo) + '</span>'
              + (m.cadastrado ? '<span class="bdg ok">cadastrado</span>' : '<button class="tbtn js-add-disp" data-id="' + esc(m.identificador) + '" data-rot="' + esc(m.rotulo) + '"><i class="fa fa-plus"></i> Adicionar</button>') + '</div>';
          }).join('');
      }).catch(erro);
    });
  };
  el('dispModelos').addEventListener('click', function (e) {
    var b = e.target.closest('.js-add-disp'); if (!b) return;
    api('modelo_salvar', { identificador: b.dataset.id, rotulo: b.dataset.rot, descricao: '' }).then(function () { b.outerHTML = '<span class="bdg ok">adicionado</span>'; }).catch(erro);
  });

  /* Leitura */
  el('btnSalvarLeitura').onclick = function () {
    var d = { precisao_padrao: el('cPrec').value, modelo_verificacao: el('cVerif').value, modelo_reserva: el('cReserva').value, qualidade_px: el('cQual').value,
              paginas_simultaneas: el('cSimult').value, vocabulario: el('cVoc').value, prompt_extra: el('cExtra').value };
    ocupado(this, function () { return api('config_salvar', d).then(function () { return ok('Configurações salvas', true); }).catch(erro); });
  };

  /* Armazenamento */
  el('btnSalvarArm').onclick = function () {
    ocupado(this, function () { return api('config_salvar', { guardar_arquivos: el('cGuardar').checked ? '1' : '0', retencao_dias: el('cRet').value }).then(function () { return ok('Salvo'); }).catch(erro); });
  };
  el('btnLimpar').onclick = function () {
    ocupado(this, function () { return api('limpar_arquivos', {}).then(function (j) { Swal.fire('Pronto', j.message, 'success'); }).catch(erro); });
  };

  /* Modelos */
  el('btnAddModelo').onclick = function () {
    var id = el('novoId').value.trim(); if (!id) { Swal.fire('Atenção', 'Informe o identificador do modelo.', 'warning'); return; }
    api('modelo_salvar', { identificador: id, rotulo: el('novoRotulo').value.trim(), descricao: el('novoDesc').value.trim() }).then(function () { location.reload(); }).catch(erro);
  };
  el('listaModelos').addEventListener('click', async function (ev) {
    var card = ev.target.closest('.mdl'); if (!card) return; var id = card.dataset.id;
    try {
      if (ev.target.closest('.js-padrao')) { await api('modelo_padrao', { id: id }); location.reload(); }
      else if (ev.target.closest('.js-excluir')) {
        var q = await Swal.fire({ icon: 'warning', title: 'Excluir modelo?', showCancelButton: true, confirmButtonText: 'Excluir', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626' });
        if (!q.isConfirmed) return; await api('modelo_excluir', { id: id }); location.reload();
      } else if (ev.target.closest('.js-editar')) {
        var b = ev.target.closest('.js-editar');
        var res = await Swal.fire({ title: 'Editar modelo', html: '<input id="sw-rot" class="swal2-input" placeholder="Nome amigável" value="' + esc(b.dataset.rotulo) + '"><input id="sw-desc" class="swal2-input" placeholder="Descrição" value="' + esc(b.dataset.desc) + '">',
          showCancelButton: true, confirmButtonText: 'Salvar', cancelButtonText: 'Cancelar', preConfirm: function () { return { rotulo: el('sw-rot').value, descricao: el('sw-desc').value }; } });
        if (!res.isConfirmed) return; await api('modelo_salvar', { id: id, rotulo: res.value.rotulo, descricao: res.value.descricao }); location.reload();
      }
    } catch (e) { erro(e); }
  });

  /* Tipos de documento */
  function linhaCampo(c) {
    c = c || { rotulo: '', tipo: 'texto', dica: '', chave: '' };
    var opts = Object.keys(TIPOS_CAMPO).map(function (k) { return '<option value="' + k + '"' + (k === c.tipo ? ' selected' : '') + '>' + esc(TIPOS_CAMPO[k]) + '</option>'; }).join('');
    var sub = (c.subcampos || []).map(function (s) { return s.rotulo + (s.tipo && s.tipo !== 'texto' ? ':' + s.tipo : ''); }).join(', ');
    return '<div class="cf" data-chave="' + esc(c.chave || '') + '"><input class="inp f-rot" value="' + esc(c.rotulo) + '" placeholder="Rótulo">'
      + '<input class="inp f-dica" value="' + esc(c.dica || '') + '" placeholder="Dica"><select class="inp f-tipo">' + opts + '</select>'
      + '<input class="inp f-sub" value="' + esc(sub) + '"' + (c.tipo === 'lista' ? ' placeholder="Nome, CPF:cpf, Data:data"' : ' disabled') + '>'
      + '<div style="display:flex;gap:4px"><button class="ib js-up" title="Subir"><i class="fa fa-arrow-up"></i></button><button class="ib rm js-rm" title="Remover"><i class="fa fa-times"></i></button></div></div>';
  }
  function abrirTipo(t) {
    el('moTitulo').textContent = t ? 'Editar: ' + t.nome : 'Novo tipo de documento';
    el('tId').value = t ? t.id : ''; el('tNome').value = t ? t.nome : ''; el('tDesc').value = t ? (t.descricao || '') : '';
    el('tInstr').value = t ? (t.instrucoes || '') : ''; el('tAtivo').checked = t ? !!t.ativo : true;
    el('tCampos').innerHTML = (t ? t.campos : [null, null, null]).map(linhaCampo).join('');
    el('moTipo').classList.add('on'); el('tNome').focus();
  }
  function fecharTipo() { el('moTipo').classList.remove('on'); }
  el('moFechar').onclick = fecharTipo; el('moCancelar').onclick = fecharTipo;
  el('tAddCampo').onclick = function () { el('tCampos').insertAdjacentHTML('beforeend', linhaCampo()); var l = el('tCampos').lastElementChild; l.querySelector('.f-rot').focus(); };
  el('tCampos').addEventListener('change', function (e) { if (e.target.classList.contains('f-tipo')) { var s = e.target.closest('.cf').querySelector('.f-sub'); s.disabled = e.target.value !== 'lista'; s.placeholder = s.disabled ? '' : 'Nome, CPF:cpf, Data:data'; if (!s.disabled) s.focus(); } });
  el('tCampos').addEventListener('click', function (e) {
    var l = e.target.closest('.cf'); if (!l) return;
    if (e.target.closest('.js-rm')) l.remove();
    if (e.target.closest('.js-up') && l.previousElementSibling) l.parentNode.insertBefore(l, l.previousElementSibling);
  });
  el('moSalvar').onclick = function () {
    var campos = Array.prototype.map.call(el('tCampos').querySelectorAll('.cf'), function (l) {
      var c = { chave: l.dataset.chave, rotulo: l.querySelector('.f-rot').value.trim(), dica: l.querySelector('.f-dica').value.trim(), tipo: l.querySelector('.f-tipo').value };
      if (c.tipo === 'lista') c.subcampos = l.querySelector('.f-sub').value.split(',').map(function (s) { var p = s.split(':'); return { rotulo: p[0].trim(), tipo: (p[1] || 'texto').trim() }; }).filter(function (s) { return s.rotulo; });
      return c;
    }).filter(function (c) { return c.rotulo; });
    var d = { id: el('tId').value, nome: el('tNome').value.trim(), descricao: el('tDesc').value.trim(), instrucoes: el('tInstr').value.trim(), ativo: el('tAtivo').checked ? '1' : '', campos: JSON.stringify(campos) };
    ocupado(this, function () { return api('tipo_salvar', d).then(function () { fecharTipo(); return ok('Tipo salvo', true); }).catch(erro); });
  };
  el('btnNovoTipo').onclick = function () { abrirTipo(null); };
  el('btnRestaurarTipos').onclick = async function () {
    var q = await Swal.fire({ icon: 'question', title: 'Restaurar tipos de fábrica?', text: 'Os tipos de fábrica voltam aos campos originais (alterações feitas neles serão perdidas). Tipos criados pela serventia não são afetados.', showCancelButton: true, confirmButtonText: 'Restaurar', cancelButtonText: 'Cancelar' });
    if (q.isConfirmed) api('tipos_restaurar', {}).then(function () { location.reload(); }).catch(erro);
  };
  el('listaTipos').addEventListener('click', async function (ev) {
    var card = ev.target.closest('.mdl'); if (!card) return; var id = card.dataset.id;
    try {
      var b;
      if ((b = ev.target.closest('.js-tipo-ativo'))) { await api('tipo_ativar', { id: id, ativo: b.dataset.ativo }); location.reload(); }
      else if (ev.target.closest('.js-tipo-editar')) { var j = await api('tipo_obter', { id: id }); abrirTipo(j.tipo); }
      else if (ev.target.closest('.js-tipo-excluir')) {
        var q = await Swal.fire({ icon: 'warning', title: 'Excluir tipo?', showCancelButton: true, confirmButtonText: 'Excluir', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626' });
        if (!q.isConfirmed) return; await api('tipo_excluir', { id: id }); location.reload();
      }
    } catch (e) { erro(e); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fecharTipo(); });

  /* Uso */
  function carregarUso() {
    api('estatisticas', { dias: el('usoDias').value }).then(function (j) {
      var s = j.stats, g = s.geral, max = Math.max.apply(null, s.serie.map(function (x) { return x.paginas; }).concat([1]));
      var tokE = 0, tokS = 0; s.por_modelo.forEach(function (m) { tokE += +m.entrada; tokS += +m.saida + +m.pensamento; });
      var h = '<div class="kpis"><div class="kpi"><b>' + num(g.extracoes) + '</b><span>extrações</span></div><div class="kpi"><b>' + num(g.paginas) + '</b><span>páginas</span></div>'
        + '<div class="kpi"><b>' + num(tokE) + '</b><span>tokens de entrada</span></div><div class="kpi"><b>' + num(tokS) + '</b><span>tokens de saída + raciocínio</span></div></div>';
      h += '<div class="muted">Páginas por dia</div><div class="barras">' + s.serie.map(function (x) { return '<div class="' + (x.paginas ? '' : 'z') + '" style="height:' + Math.max(2, Math.round(84 * x.paginas / max)) + 'px" title="' + x.dia.split('-').reverse().join('/') + ': ' + x.paginas + ' pág."></div>'; }).join('') + '</div>';
      if (s.por_modelo.length) h += '<table class="tb" style="margin-top:12px"><thead><tr><th>Modelo</th><th class="n">Chamadas</th><th class="n">Falhas</th><th class="n">Entrada</th><th class="n">Saída</th><th class="n">Raciocínio</th><th class="n">Tempo médio</th></tr></thead><tbody>'
        + s.por_modelo.map(function (m) { return '<tr><td><code>' + esc(m.modelo) + '</code></td><td class="n">' + num(m.chamadas) + '</td><td class="n">' + num(m.falhas) + '</td><td class="n">' + num(m.entrada) + '</td><td class="n">' + num(m.saida) + '</td><td class="n">' + num(m.pensamento) + '</td><td class="n">' + (m.ms_medio ? (m.ms_medio / 1000).toFixed(1) + ' s' : '—') + '</td></tr>'; }).join('') + '</tbody></table>';
      if (s.por_usuario.length) h += '<table class="tb" style="margin-top:12px"><thead><tr><th>Usuário</th><th class="n">Extrações</th><th class="n">Páginas</th></tr></thead><tbody>'
        + s.por_usuario.map(function (u) { return '<tr><td>' + esc(u.usuario) + '</td><td class="n">' + num(u.extracoes) + '</td><td class="n">' + num(u.paginas) + '</td></tr>'; }).join('') + '</tbody></table>';
      el('usoCorpo').innerHTML = h;
    }).catch(function (e) { el('usoCorpo').innerHTML = '<div class="vazio">' + esc(e.message) + '</div>'; });
  }
  el('usoDias').onchange = carregarUso;
  carregarUso();
})();
</script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
