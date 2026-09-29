<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_iris.php';
iris_ensure_schema();
$CSRF = iris_csrf();
$isAdmin = iris_is_admin();
$v = rawurlencode(IRIS_VERSAO);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas Iris · Histórico</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="stylesheet" href="assets/iris.css?v=<?php echo $v; ?>">
<style>
#main .container.ir-container{ max-width:1200px; }
.fl{ display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; gap:10px; align-items:end; }
@media(max-width:900px){ .fl{ grid-template-columns:1fr 1fr; } }
.fl label{ display:block; font-size:.75rem; font-weight:700; color:var(--ir-muted); margin-bottom:4px; text-transform:uppercase; }
.hi{ display:flex; gap:14px; align-items:flex-start; padding:14px 4px; border-bottom:1px solid var(--ir-border); }
.hi:last-child{ border-bottom:0; }
.hi-ic{ width:42px; height:42px; border-radius:11px; background:rgba(192,38,211,.12); color:var(--ir-primary); display:flex; align-items:center; justify-content:center; font-size:1.15rem; flex:0 0 auto; }
.hi-main{ flex:1; min-width:0; }
.hi-nome{ font-weight:700; color:var(--ir-text); word-break:break-word; text-decoration:none!important; }
.hi-nome:hover{ color:var(--ir-primary); }
.hi-meta{ display:flex; gap:6px; flex-wrap:wrap; margin-top:4px; align-items:center; }
.hi-trecho{ font-family:Georgia,serif; font-size:.85rem; color:var(--ir-muted); margin-top:6px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.hi-acts{ display:flex; gap:6px; flex:0 0 auto; }
.pag{ display:flex; gap:6px; justify-content:center; align-items:center; margin-top:14px; font-size:.85rem; color:var(--ir-muted); }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container ir-container">
    <section class="ir-hero">
      <div class="ir-title-row">
        <div class="ir-ic"><i class="fa fa-history"></i></div>
        <div style="min-width:0"><h1>Histórico · Atlas Iris</h1><div class="ir-sub"><?php echo $isAdmin ? 'Todas as extrações da serventia.' : 'Suas extrações.'; ?> Busque pelo nome do arquivo, pelo texto ou pelos dados extraídos.</div></div>
        <div class="ir-actions"><a class="ir-pill ir-pri" href="index.php"><i class="fa fa-plus"></i> Nova extração</a></div>
      </div>
    </section>

    <div class="ir-card">
      <div class="fl">
        <div><label for="fBusca">Buscar</label><input class="inp" id="fBusca" placeholder="Nome, CPF, trecho do texto…"></div>
        <div><label for="fModo">Modo</label><select class="inp" id="fModo"><option value="">Todos</option><option value="transcricao">Texto</option><option value="dados">Dados</option><option value="completo">Texto + dados</option></select></div>
        <div><label for="fDe">De</label><input class="inp" type="date" id="fDe"></div>
        <div><label for="fAte">Até</label><input class="inp" type="date" id="fAte"></div>
        <label class="ck" style="padding-bottom:10px"><input type="checkbox" id="fDuv"> Com dúvidas</label>
      </div>
      <?php if ($isAdmin): ?><div style="margin-top:10px;max-width:260px"><input class="inp" id="fUsuario" placeholder="Filtrar por usuário (opcional)"></div><?php endif; ?>
    </div>

    <div class="ir-card">
      <div class="muted" id="hTotal" style="margin-bottom:6px"></div>
      <div id="hLista"><div class="vazio"><i class="fa fa-spinner fa-spin"></i> Carregando…</div></div>
      <div class="pag" id="hPag"></div>
    </div>
  </div>
</div>
<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>
(function () {
  'use strict';
  var CSRF = <?php echo json_encode($CSRF); ?>, ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
  var pagina = 1, timer = 0;
  function el(id) { return document.getElementById(id); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function dataBR(s) { var m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/); return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : s; }
  function humano(n) { n = +n || 0; if (n < 1024) return n + ' B'; if (n < 1048576) return (n / 1024).toFixed(1) + ' KB'; return (n / 1048576).toFixed(1) + ' MB'; }
  function api(acao, dados) {
    var fd = new FormData(); Object.keys(dados || {}).forEach(function (k) { fd.append(k, dados[k]); });
    fd.append('acao', acao); fd.append('csrf', CSRF);
    return fetch('api.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (j.status !== 'success') throw new Error(j.message || 'Falha.'); return j; });
  }
  var MODO = { transcricao: 'Texto', dados: 'Dados', completo: 'Texto + dados' };
  function carregar() {
    var f = { pagina: pagina, busca: el('fBusca').value, modo: el('fModo').value, de: el('fDe').value, ate: el('fAte').value, duvidas: el('fDuv').checked ? '1' : '' };
    if (ADMIN && el('fUsuario')) f.usuario = el('fUsuario').value.trim();
    api('hist_listar', f).then(function (j) {
      el('hTotal').textContent = j.total + (j.total === 1 ? ' extração' : ' extrações');
      if (!j.itens.length) { el('hLista').innerHTML = '<div class="vazio">Nenhuma extração encontrada.</div>'; el('hPag').innerHTML = ''; return; }
      el('hLista').innerHTML = j.itens.map(function (it) {
        var ic = /pdf/.test(it.arquivo_mime || '') ? 'fa-file-pdf-o' : 'fa-file-image-o';
        return '<div class="hi" data-id="' + it.id + '"><div class="hi-ic"><i class="fa ' + ic + '"></i></div><div class="hi-main">'
          + '<a class="hi-nome" href="index.php?abrir=' + it.id + '">' + esc(it.arquivo_nome) + '</a>'
          + '<div class="hi-meta"><span class="muted">' + esc(dataBR(it.criado_em)) + (ADMIN ? ' · ' + esc(it.usuario) : '') + ' · ' + it.paginas + ' pág.' + (it.arquivo_tamanho ? ' · ' + humano(it.arquivo_tamanho) : '') + '</span>'
          + '<span class="bdg info">' + esc(MODO[it.modo] || it.modo) + '</span>'
          + (it.tipo_nome ? '<span class="bdg info">' + esc(it.tipo_nome) + '</span>' : '')
          + (it.precisao === 'maxima' ? '<span class="bdg info"><i class="fa fa-pencil"></i> máxima</span>' : '')
          + (+it.duvidas ? '<span class="bdg warn">' + it.duvidas + ' dúvida(s)</span>' : '<span class="bdg ok"><i class="fa fa-check"></i> sem dúvidas</span>')
          + (it.modelo ? '<span class="muted">' + esc(it.modelo) + '</span>' : '') + '</div>'
          + (it.trecho ? '<div class="hi-trecho">' + esc(it.trecho) + '</div>' : '') + '</div>'
          + '<div class="hi-acts"><a class="ib" href="index.php?abrir=' + it.id + '" title="Abrir para revisar"><i class="fa fa-folder-open-o"></i></a>'
          + (it.tem_arquivo ? '<a class="ib" href="api.php?acao=hist_arquivo&baixar=1&id=' + it.id + '&csrf=' + CSRF + '" title="Baixar o arquivo original"><i class="fa fa-download"></i></a>' : '')
          + '<button class="ib js-del" title="Excluir"><i class="fa fa-trash-o"></i></button></div></div>';
      }).join('');
      var h = '';
      if (j.paginas > 1) {
        h += '<button class="tbtn" ' + (pagina <= 1 ? 'disabled' : '') + ' data-p="' + (pagina - 1) + '"><i class="fa fa-chevron-left"></i></button>';
        h += '<span>Página ' + pagina + ' de ' + j.paginas + '</span>';
        h += '<button class="tbtn" ' + (pagina >= j.paginas ? 'disabled' : '') + ' data-p="' + (pagina + 1) + '"><i class="fa fa-chevron-right"></i></button>';
      }
      el('hPag').innerHTML = h;
    }).catch(function (e) { el('hLista').innerHTML = '<div class="vazio">' + esc(e.message) + '</div>'; });
  }
  function agendar() { clearTimeout(timer); timer = setTimeout(function () { pagina = 1; carregar(); }, 300); }
  ['fBusca', 'fUsuario'].forEach(function (id) { if (el(id)) el(id).addEventListener('input', agendar); });
  ['fModo', 'fDe', 'fAte', 'fDuv'].forEach(function (id) { el(id).addEventListener('change', agendar); });
  el('hPag').addEventListener('click', function (e) { var b = e.target.closest('[data-p]'); if (b && !b.disabled) { pagina = +b.dataset.p; carregar(); window.scrollTo(0, 0); } });
  el('hLista').addEventListener('click', function (e) {
    var b = e.target.closest('.js-del'); if (!b) return;
    var id = b.closest('.hi').dataset.id;
    Swal.fire({ icon: 'warning', title: 'Excluir esta extração?', text: 'O texto, os dados e o arquivo original guardado serão apagados.', showCancelButton: true, confirmButtonText: 'Excluir', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626' })
      .then(function (r) { if (!r.isConfirmed) return; api('hist_excluir', { id: id }).then(carregar).catch(function (e) { Swal.fire('Erro', e.message, 'error'); }); });
  });
  carregar();
})();
</script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
