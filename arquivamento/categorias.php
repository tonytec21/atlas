<?php
/**
 * Atlas · Arquivamento Digital — categorias.
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
<title>Atlas · Categorias de arquivamento</title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="stylesheet" href="assets/css/arquivamento.css?v=8">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container arq" style="max-width:900px">

    <header class="arq-topo">
      <div class="arq-titulo-linha">
        <div class="arq-icone-titulo"><i class="fa fa-tags"></i></div>
        <div>
          <div class="arq-sobretitulo">Acervo digital</div>
          <h1>Categorias</h1>
          <p>Como os arquivamentos são classificados. Renomear reclassifica automaticamente todos os registros que usam o nome antigo.</p>
        </div>
      </div>
      <a class="arq-btn" href="index.php"><i class="fa fa-arrow-left"></i> Voltar ao acervo</a>
    </header>

    <section class="arq-cartao" style="margin-bottom:18px">
      <h2>Nova categoria</h2>
      <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
        <input type="text" id="nova" placeholder="Ex.: Escritura de inventário" maxlength="120" style="flex:1;min-width:220px">
        <button class="arq-btn arq-btn-p" id="criar"><i class="fa fa-plus"></i> Criar</button>
      </div>
    </section>

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

  function $(s) { return document.querySelector(s); }
  function esc(v) {
    return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function aviso(i, t, x) { return ArqDlg.aviso(i, t, x); }

  function post(dados) {
    var fd = new FormData();
    Object.keys(dados).forEach(function (k) { fd.append(k, dados[k]); });
    return fetch('api/categorias.php', {
      method: 'POST', credentials: 'same-origin', body: fd,
      headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) { throw new Error(j.erro || 'Operação recusada.'); }
      return j;
    });
  }

  function carregar() {
    $('#lista').innerHTML = '<div class="arq-esqueleto" style="height:200px"></div>';
    fetch('api/categorias.php?acao=listar', {
      credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) { throw new Error(j.erro); }
      if (!j.categorias.length) {
        $('#lista').innerHTML = '<div class="arq-vazio"><i class="fa fa-tags"></i>' +
          '<h3>Nenhuma categoria</h3><p>Crie a primeira acima para começar a classificar o acervo.</p></div>';
        return;
      }
      $('#lista').innerHTML =
        '<div class="arq-tabela-caixa"><table class="arq-tabela"><thead><tr>' +
          '<th>Categoria</th><th class="col-min">Em uso</th><th class="col-min"></th>' +
        '</tr></thead><tbody>' +
        j.categorias.map(function (c) {
          return '<tr>' +
            '<td><b>' + esc(c.nome) + '</b></td>' +
            '<td class="col-min">' + (c.uso
              ? '<a href="index.php?categoria=' + encodeURIComponent(c.nome) + '&periodo=tudo">' + c.uso + ' registro(s)</a>'
              : '<span style="color:var(--arq-suave)">—</span>') + '</td>' +
            '<td class="col-min">' +
              '<button class="arq-btn arq-btn-sm" data-renomear="' + esc(c.nome) + '"><i class="fa fa-pencil"></i> Renomear</button> ' +
              '<button class="arq-btn arq-btn-sm arq-btn-ic arq-btn-perigo" data-excluir="' + esc(c.nome) + '" ' +
                (c.uso ? 'disabled title="Categoria em uso"' : 'title="Excluir"') + '><i class="fa fa-trash-o"></i></button>' +
            '</td></tr>';
        }).join('') + '</tbody></table></div>';
    }).catch(function (e) {
      $('#lista').innerHTML = '<div class="arq-vazio"><i class="fa fa-exclamation-triangle"></i>' +
        '<h3>Erro ao carregar</h3><p>' + esc(e.message) + '</p></div>';
    });
  }

  $('#criar').addEventListener('click', function () {
    var nome = $('#nova').value.trim();
    if (!nome) { $('#nova').focus(); return; }
    post({ acao: 'criar', nome: nome }).then(function () {
      $('#nova').value = '';
      carregar();
    }).catch(function (e) { aviso('error', 'Não foi possível criar', e.message); });
  });
  $('#nova').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); $('#criar').click(); }
  });

  document.addEventListener('click', function (e) {
    var el;

    if ((el = e.target.closest('[data-renomear]'))) {
      var antigo = el.dataset.renomear;
      var aplicar = function (novo) {
        post({ acao: 'renomear', antigo: antigo, nome: novo }).then(function (j) {
          aviso('success', 'Categoria renomeada',
            j.registros_atualizados ? j.registros_atualizados + ' arquivamento(s) foram reclassificados.' : '');
          carregar();
        }).catch(function (err) { aviso('error', 'Erro', err.message); });
      };
      ArqDlg.perguntar('Renomear categoria', { valor: antigo, botao: 'Renomear' })
        .then(function (v) {
          if (v && v.trim() && v.trim() !== antigo) { aplicar(v.trim()); }
        });
      return;
    }

    if ((el = e.target.closest('[data-excluir]'))) {
      if (el.disabled) { return; }
      var nome = el.dataset.excluir;
      var remover = function () {
        post({ acao: 'excluir', nome: nome }).then(carregar)
          .catch(function (err) { aviso('error', 'Não foi possível excluir', err.message); });
      };
      ArqDlg.confirmar('Excluir categoria?', esc(nome), 'Excluir', true)
        .then(function (ok) { if (ok) { remover(); } });
    }
  });

  carregar();
})();
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
