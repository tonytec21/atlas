<?php
/**
 * Atlas · Arquivamento Digital — lixeira.
 */
require_once __DIR__ . '/bootstrap.php';
arq_exige_login();

$csrf = arq_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Atlas · Lixeira do arquivamento</title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="stylesheet" href="assets/css/arquivamento.css?v=8">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container arq">

    <header class="arq-topo">
      <div class="arq-titulo-linha">
        <div class="arq-icone-titulo"><i class="fa fa-trash-o"></i></div>
        <div>
        <div class="arq-sobretitulo">Acervo digital</div>
        <h1>Lixeira</h1>
        <p>Registros retirados do acervo ativo. Ficam aqui por <?= (int) ARQ_LIXEIRA_DIAS ?> dias e podem ser restaurados a qualquer momento nesse prazo.</p>
        </div>
      </div>
      <a class="arq-btn" href="index.php"><i class="fa fa-arrow-left"></i> Voltar ao acervo</a>
    </header>

    <div class="arq-comando" style="margin-bottom:18px">
      <div class="arq-busca">
        <i class="fa fa-search" aria-hidden="true"></i>
        <input type="search" id="busca" placeholder="Filtrar por parte, categoria ou número…" aria-label="Filtrar lixeira" autocomplete="off">
      </div>
    </div>

    <div id="lista"></div>
  </div>
</div>

<script src="../script/jquery-3.6.0.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="assets/js/dialogos.js?v=6"></script>
<script>
(function () {
  'use strict';
  var CSRF = <?= json_encode($csrf) ?>;
  var itens = [], podeExpurgar = false;

  function $(s) { return document.querySelector(s); }
  function esc(v) {
    return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function fmtData(d) {
    if (!d) { return '—'; }
    var p = String(d).slice(0, 10).split('-');
    return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d;
  }
  function aviso(i, t, x) { return ArqDlg.aviso(i, t, x); }
  function normalizar(t) {
    return String(t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  }

  function post(dados) {
    var fd = new FormData();
    Object.keys(dados).forEach(function (k) { fd.append(k, dados[k]); });
    return fetch('api/lixeira.php', {
      method: 'POST', credentials: 'same-origin', body: fd,
      headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) { throw new Error(j.erro || 'Operação recusada.'); }
      return j;
    });
  }

  function pintar() {
    var q = normalizar($('#busca').value.trim());
    var lista = itens.filter(function (i) {
      if (!q) { return true; }
      return normalizar([i.id, i.categoria, i.atribuicao, (i.partes || []).join(' ')].join(' ')).indexOf(q) >= 0;
    });

    if (!lista.length) {
      $('#lista').innerHTML =
        '<div class="arq-vazio"><i class="fa fa-trash-o"></i><h3>Lixeira vazia</h3>' +
        '<p>Nada foi excluído do acervo' + (q ? ' com esse termo' : '') + '.</p></div>';
      return;
    }

    $('#lista').innerHTML =
      '<div class="arq-tabela-caixa"><table class="arq-tabela"><thead><tr>' +
        '<th>Nº</th><th>Data do ato</th><th>Atribuição</th><th>Categoria</th><th>Partes</th>' +
        '<th>Anexos</th><th>Excluído por</th><th>Prazo</th><th></th>' +
      '</tr></thead><tbody>' +
      lista.map(function (i) {
        var prazo = i.dias_restantes == null ? '—'
          : (i.dias_restantes > 0
              ? '<span style="color:var(--arq-suave)">' + i.dias_restantes + ' dia(s)</span>'
              : '<b style="color:var(--arq-perigo)">vencido</b>');
        return '<tr>' +
          '<td class="col-min arq-mono" style="font-size:.78rem">' + esc(i.id) + '</td>' +
          '<td class="col-min">' + fmtData(i.data_ato) + '</td>' +
          '<td>' + esc(i.atribuicao) + '</td>' +
          '<td>' + esc(i.categoria) + '</td>' +
          '<td style="max-width:260px">' + esc((i.partes || []).join(' · ')) + '</td>' +
          '<td class="col-min">' + (i.anexos_qtd || 0) + '</td>' +
          '<td class="col-min">' + esc(i.excluido_por || '—') + '<div style="font-size:.74rem;color:var(--arq-suave)">' + fmtData(i.data_exclusao) + '</div></td>' +
          '<td class="col-min">' + prazo + '</td>' +
          '<td class="col-min">' +
            '<button class="arq-btn arq-btn-sm" data-restaurar="' + esc(i.id) + '"><i class="fa fa-undo"></i> Restaurar</button>' +
            (podeExpurgar ? ' <button class="arq-btn arq-btn-sm arq-btn-ic arq-btn-perigo" data-expurgar="' + esc(i.id) + '" title="Excluir definitivamente"><i class="fa fa-times"></i></button>' : '') +
          '</td></tr>';
      }).join('') +
      '</tbody></table></div>';
  }

  function carregar() {
    $('#lista').innerHTML = '<div class="arq-esqueleto" style="height:200px"></div>';
    fetch('api/lixeira.php?acao=listar', {
      credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) { throw new Error(j.erro); }
      itens = j.itens;
      podeExpurgar = j.pode_expurgar;
      pintar();
    }).catch(function (e) {
      $('#lista').innerHTML = '<div class="arq-vazio"><i class="fa fa-exclamation-triangle"></i>' +
        '<h3>Não foi possível abrir a lixeira</h3><p>' + esc(e.message) + '</p></div>';
    });
  }

  $('#busca').addEventListener('input', pintar);

  document.addEventListener('click', function (e) {
    var el;

    if ((el = e.target.closest('[data-restaurar]'))) {
      var id = el.dataset.restaurar;
      post({ acao: 'restaurar', id: id }).then(function () {
        aviso('success', 'Restaurado', 'O arquivamento voltou para o acervo ativo.');
        carregar();
      }).catch(function (err) { aviso('error', 'Erro', err.message); });
      return;
    }

    if ((el = e.target.closest('[data-expurgar]'))) {
      var alvo = el.dataset.expurgar;
      var executar = function (conf) {
        post({ acao: 'expurgar', id: alvo, confirmacao: conf }).then(function () {
          aviso('success', 'Excluído', 'O arquivamento e seus anexos foram apagados do servidor.');
          carregar();
        }).catch(function (err) { aviso('error', 'Erro', err.message); });
      };
      ArqDlg.perguntar('Excluir definitivamente?', {
        html: 'Esta ação apaga o registro <b>e todos os anexos</b> do servidor, sem volta.<br><br>' +
              'Digite <b>' + esc(alvo) + '</b> para confirmar:',
        exemplo: alvo,
        botao: 'Excluir para sempre',
        perigo: true
      }).then(function (v) { if (v !== null) { executar(v); } });
    }
  });

  carregar();
})();
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
