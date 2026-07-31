<?php
/**
 * Atlas · Arquivamento Digital — cadastro e edição.
 * Sem ?id= cria um novo registro; com ?id= edita o existente.
 */
require_once __DIR__ . '/bootstrap.php';
arq_exige_login();

$csrf = arq_csrf_token();
$id   = arq_id_valido(isset($_GET['id']) ? $_GET['id'] : '');
$ato  = null;

if ($id !== '') {
    $ato = arq_obter($id);
    if (!$ato) {
        header('Location: index.php');
        exit;
    }
    foreach ($ato['anexos'] as $i => $a) {
        $ato['anexos'][$i]['url']             = 'arquivo.php?id=' . rawurlencode($id) . '&a=' . $i;
        $ato['anexos'][$i]['tamanho_legivel'] = arq_formatar_bytes($a['tamanho']);
        $ato['anexos'][$i]['indice']          = $i;
        unset($ato['anexos'][$i]['ref']);
    }
}

$editando    = $ato !== null;
$selos       = $editando ? arq_selos($id) : [];
$temSelo     = !empty($selos);
$categorias  = arq_categorias();
$atribuicoes = arq_atribuicoes();
$extensoes   = ARQ_UPLOAD_ACEITA_TUDO ? [] : array_keys(arq_tipos_permitidos());
?>
<?php include(__DIR__ . '/../os/guia/guia.php'); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Atlas · <?= $editando ? 'Editar arquivamento' : 'Novo arquivamento' ?></title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="stylesheet" href="assets/css/arquivamento.css?v=8">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container arq" style="max-width:960px">

    <header class="arq-topo">
      <div class="arq-titulo-linha">
        <div class="arq-icone-titulo"><i class="fa <?= $editando ? 'fa-pencil' : 'fa-plus' ?>"></i></div>
        <div>
          <div class="arq-sobretitulo"><?= $editando ? 'Registro nº ' . arq_e($id) : 'Novo registro' ?></div>
          <h1><?= $editando ? 'Editar arquivamento' : 'Cadastrar arquivamento' ?></h1>
          <p>Identifique o ato, informe as partes e anexe os documentos digitalizados.</p>
        </div>
      </div>
      <a class="arq-btn" href="index.php"><i class="fa fa-arrow-left"></i> Voltar ao acervo</a>
    </header>

    <form class="arq-form" id="arq-form" novalidate>
      <input type="hidden" name="id" value="<?= arq_e($id) ?>">

      <!-- 1 · Identificação ==================================== -->
      <section class="arq-cartao">
        <h2><span class="arq-passo">01</span> Identificação do ato</h2>
        <p class="arq-ajuda">A atribuição define a cor da lombada no acervo. Livro, folha, termo, protocolo e matrícula são opcionais — preencha o que existir.</p>

        <div class="arq-grade">
          <div class="arq-larg-2">
            <label class="arq-rot" for="atribuicao">Atribuição *</label>
            <select id="atribuicao" name="atribuicao" required>
              <option value="">Selecione…</option>
              <?php foreach ($atribuicoes as $a): ?>
                <option value="<?= arq_e($a) ?>" <?= ($editando && $ato['atribuicao'] === $a) ? 'selected' : '' ?>><?= arq_e($a) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="arq-msg-erro">Escolha a atribuição.</div>
          </div>

          <div class="arq-larg-2">
            <label class="arq-rot" for="categoria">Categoria *</label>
            <div style="display:flex;gap:8px">
              <select id="categoria" name="categoria" required style="flex:1">
                <option value="">Selecione…</option>
                <?php foreach ($categorias as $c): ?>
                  <option value="<?= arq_e($c) ?>" <?= ($editando && $ato['categoria'] === $c) ? 'selected' : '' ?>><?= arq_e($c) ?></option>
                <?php endforeach; ?>
                <?php if ($editando && $ato['categoria'] !== '' && !in_array($ato['categoria'], $categorias, true)): ?>
                  <option value="<?= arq_e($ato['categoria']) ?>" selected><?= arq_e($ato['categoria']) ?></option>
                <?php endif; ?>
              </select>
              <button type="button" class="arq-btn arq-btn-ic" id="nova-categoria" title="Criar categoria"><i class="fa fa-plus"></i></button>
            </div>
            <div class="arq-msg-erro">Escolha ou crie a categoria.</div>
          </div>

          <div>
            <label class="arq-rot" for="data_ato">Data do ato *</label>
            <input type="date" id="data_ato" name="data_ato" required max="<?= date('Y-m-d') ?>"
                   value="<?= arq_e($editando ? $ato['data_ato'] : '') ?>">
            <div class="arq-msg-erro">Informe uma data válida e não futura.</div>
          </div>

          <div>
            <label class="arq-rot" for="livro">Livro</label>
            <input type="text" id="livro" name="livro" class="arq-mono" value="<?= arq_e($editando ? $ato['livro'] : '') ?>">
          </div>
          <div>
            <label class="arq-rot" for="folha">Folha</label>
            <input type="text" id="folha" name="folha" class="arq-mono" value="<?= arq_e($editando ? $ato['folha'] : '') ?>">
          </div>
          <div>
            <label class="arq-rot" for="termo">Termo/Ordem</label>
            <input type="text" id="termo" name="termo" class="arq-mono" value="<?= arq_e($editando ? $ato['termo'] : '') ?>">
          </div>
          <div>
            <label class="arq-rot" for="protocolo">Protocolo</label>
            <input type="text" id="protocolo" name="protocolo" class="arq-mono" value="<?= arq_e($editando ? $ato['protocolo'] : '') ?>">
          </div>
          <div>
            <label class="arq-rot" for="matricula">Matrícula</label>
            <input type="text" id="matricula" name="matricula" class="arq-mono" value="<?= arq_e($editando ? $ato['matricula'] : '') ?>">
          </div>
        </div>

        <div style="margin-top:14px">
          <label class="arq-rot" for="descricao">Descrição e observações</label>
          <textarea id="descricao" name="descricao" rows="3" maxlength="4000"
                    placeholder="Resumo do ato, referências cruzadas, observações da serventia…"><?= arq_e($editando ? $ato['descricao'] : '') ?></textarea>
        </div>
      </section>

      <!-- 2 · Partes ============================================ -->
      <section class="arq-cartao">
        <h2><span class="arq-passo">02</span> Partes envolvidas</h2>
        <p class="arq-ajuda">Ao menos uma parte é obrigatória. O CPF/CNPJ é opcional, mas é ele que permite localizar o arquivamento por documento depois.</p>

        <div class="arq-grade">
          <div>
            <label class="arq-rot" for="p-cpf">CPF/CNPJ</label>
            <input type="text" id="p-cpf" class="arq-mono" inputmode="numeric" maxlength="18" placeholder="000.000.000-00">
          </div>
          <div class="arq-larg-2">
            <label class="arq-rot" for="p-nome">Nome completo</label>
            <input type="text" id="p-nome" placeholder="Nome da parte">
          </div>
          <div>
            <label class="arq-rot" for="p-papel">Qualificação</label>
            <input type="text" id="p-papel" list="papeis" placeholder="Ex.: outorgante">
            <datalist id="papeis">
              <option value="Outorgante"><option value="Outorgado"><option value="Requerente">
              <option value="Adquirente"><option value="Transmitente"><option value="Registrado">
              <option value="Interessado"><option value="Devedor"><option value="Credor">
            </datalist>
          </div>
          <div style="display:flex;align-items:flex-end">
            <button type="button" class="arq-btn arq-btn-p" id="add-parte" style="width:100%"><i class="fa fa-user-plus"></i> Adicionar</button>
          </div>
        </div>

        <table class="arq-tabela-partes">
          <thead><tr><th style="width:170px">CPF/CNPJ</th><th>Nome</th><th style="width:150px">Qualificação</th><th style="width:44px"></th></tr></thead>
          <tbody id="partes"></tbody>
        </table>
        <p id="sem-partes" style="color:var(--arq-suave);font-size:.86rem;margin:12px 0 0">Nenhuma parte adicionada ainda.</p>
      </section>

      <!-- 3 · Anexos ============================================ -->
      <section class="arq-cartao">
        <h2><span class="arq-passo">03</span> Documentos digitalizados</h2>
        <p class="arq-ajuda">
          <?php if (ARQ_UPLOAD_ACEITA_TUDO): ?>
            Qualquer formato é aceito. Até <?= arq_e(arq_formatar_bytes(ARQ_UPLOAD_MAX_BYTES)) ?> por arquivo
            e <?= (int) ARQ_UPLOAD_MAX_ARQUIVOS ?> anexos por arquivamento.
            PDFs e imagens entram no dossiê compilado; os demais formatos saem no pacote ZIP.
          <?php else: ?>
            Aceitos: <?= arq_e(implode(', ', $extensoes)) ?>. Até <?= arq_e(arq_formatar_bytes(ARQ_UPLOAD_MAX_BYTES)) ?> por arquivo
            e <?= (int) ARQ_UPLOAD_MAX_ARQUIVOS ?> anexos por arquivamento. PDFs e imagens entram no dossiê compilado.
          <?php endif; ?>
        </p>

        <?php if ($editando && count($ato['anexos'])): ?>
          <h3 style="font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;color:var(--arq-suave);margin:0 0 10px">Já no acervo</h3>
          <div class="arq-anexos" id="anexos-existentes" style="margin-bottom:18px">
            <?php foreach ($ato['anexos'] as $a): ?>
              <div class="arq-anexo" data-indice="<?= (int) $a['indice'] ?>" data-ext="<?= arq_e($a['ext']) ?>">
                <div class="arq-anexo-ic"><i class="fa fa-file-o"></i></div>
                <div class="arq-anexo-info">
                  <div class="arq-anexo-nome"><?= arq_e($a['nome']) ?></div>
                  <div class="arq-anexo-meta">
                    <?= arq_e($a['tamanho_legivel']) ?>
                    <?= $a['origem'] === 'tarefa' ? ' · vindo de tarefa' : '' ?>
                    <?= $a['disponivel'] ? '' : ' · <b style="color:var(--arq-perigo)">arquivo ausente</b>' ?>
                  </div>
                </div>
                <div class="arq-anexo-acoes">
                  <a class="arq-btn arq-btn-sm arq-btn-ic" href="<?= arq_e($a['url']) ?>" target="_blank" rel="noopener" title="Abrir"><i class="fa fa-eye"></i></a>
                  <button type="button" class="arq-btn arq-btn-sm arq-btn-ic arq-btn-perigo remover-anexo" title="Remover"><i class="fa fa-times"></i></button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="arq-solta" id="solta" tabindex="0" role="button" aria-label="Selecionar arquivos">
          <i class="fa fa-cloud-upload" aria-hidden="true"></i>
          <b>Arraste os arquivos aqui</b>
          <span>ou clique para escolher no computador</span>
          <input type="file" id="file-input" multiple hidden
                 <?= ARQ_UPLOAD_ACEITA_TUDO ? '' : 'accept="' . arq_e('.' . implode(',.', $extensoes)) . '"' ?>>
        </div>

        <div class="arq-anexos" id="fila" style="margin-top:14px"></div>
      </section>

    </form>

    <!-- 4 · Selos ============================================= -->
      <?php if ($editando): ?>
      <section class="arq-cartao" id="bloco-selos">
        <h2>
          <span class="arq-passo">04</span>
          <?= $temSelo ? 'Selos deste arquivamento' : 'Solicitar selo' ?>
          <button type="button" class="arq-btn arq-btn-sm" id="btnAddSelo"
                  style="margin-left:auto<?= $temSelo ? '' : ';display:none' ?>">
            <i class="fa fa-plus"></i> Adicionar mais selo
          </button>
        </h2>
        <p class="arq-ajuda">
          Informe a quantidade e confirme para gerar o selo no Portal do Selo do TJMA.
          Livro, folha e termo vão preenchidos a partir dos dados acima.
        </p>

        <div class="arq-selos" id="selos-container">
          <?php foreach ($selos as $s): ?>
            <div class="arq-selo">
              <?php if (!empty($s['qr_code'])): ?>
                <img src="data:image/png;base64,<?= arq_e($s['qr_code']) ?>" alt="QR Code do selo">
              <?php endif; ?>
              <div style="min-width:0">
                <div class="arq-selo-num">
                  <?= arq_e($s['numero_selo']) ?>
                  <button type="button" class="arq-btn arq-btn-sm arq-btn-ic" data-copiar="<?= arq_e($s['numero_selo']) ?>" title="Copiar número">
                    <i class="fa fa-clone"></i>
                  </button>
                </div>
                <div class="arq-selo-txt"><?= nl2br(arq_e($s['texto_selo'])) ?></div>
                <?php if (!empty($s['escrevente'])): ?>
                  <div class="arq-selo-txt"><b>Funcionário:</b> <?= arq_e($s['escrevente']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <form method="post" id="selo-form" style="margin-top:<?= $temSelo ? '16px' : '0' ?><?= $temSelo ? ';display:none' : '' ?>">
          <input type="hidden" name="numeroControle" value="<?= arq_e($id) ?>">
          <input type="hidden" name="livro" id="livro_selo" value="">
          <input type="hidden" name="folha" id="folha_selo" value="">
          <input type="hidden" name="termo"  id="termo_selo" value="">
          <input type="hidden" name="escrevente" value="<?= arq_e(arq_usuario_nome()) ?>">
          <input type="hidden" name="partes" id="partes_selo" value="">

          <div class="arq-grade">
            <div class="arq-larg-2">
              <label class="arq-rot" for="ato">Ato</label>
              <select id="ato" name="ato" required>
                <option value="13.30">13.30 — Arquivamento, por folha do documento</option>
                <option value="14.12">14.12 — Arquivamento, por folha do documento</option>
                <option value="15.22">15.22 — Arquivamento, por folha do documento</option>
                <option value="16.39">16.39 — Arquivamento, por folha do documento</option>
                <option value="17.9">17.9 — Arquivamento, por folha do documento</option>
                <option value="18.13">18.13 — Arquivamento, por folha do documento</option>
              </select>
            </div>
            <div>
              <label class="arq-rot" for="tabela_custas">Tabela de custas</label>
              <select id="tabela_custas" name="tabela_custas">
                <option value="2026" selected>2026</option>
                <option value="2025">2025</option>
              </select>
            </div>
            <div>
              <label class="arq-rot" for="quantidade">Quantidade de folhas</label>
              <input type="number" id="quantidade" name="quantidade" min="1" placeholder="0" required>
            </div>
            <div>
              <label class="arq-rot" for="isento">Selo isento?</label>
              <label style="display:flex;align-items:center;gap:8px;margin:0;height:40px;cursor:pointer">
                <input type="checkbox" id="isento" name="isento">
                <span style="font-size:.88rem;font-weight:600">Isento de emolumentos</span>
              </label>
            </div>
            <div class="arq-larg-2" id="motivo-wrapper" style="display:none">
              <label class="arq-rot" for="motivo_isencao">Motivo da isenção</label>
              <input type="text" id="motivo_isencao" name="motivo_isencao" placeholder="Fundamento legal da isenção">
            </div>
            <div style="display:flex;align-items:flex-end">
              <button type="submit" class="arq-btn arq-btn-p" id="solicitar-selo-btn" style="width:100%">
                <i class="fa fa-certificate"></i> Solicitar selo
              </button>
            </div>
          </div>
        </form>
      </section>
      <?php endif; ?>

    <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;padding-bottom:40px">
      <a class="arq-btn" href="index.php">Cancelar</a>
      <button type="submit" form="arq-form" class="arq-btn arq-btn-p" id="salvar" style="min-width:200px">
        <i class="fa fa-check"></i> <?= $editando ? 'Salvar alterações' : 'Cadastrar arquivamento' ?>
      </button>
    </div>

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
  var EDITANDO = <?= $editando ? 'true' : 'false' ?>;
  var PARTES_INICIAIS = <?= json_encode($editando ? $ato['partes_envolvidas'] : [], JSON_UNESCAPED_UNICODE) ?>;
  var MAX_BYTES = <?= (int) ARQ_UPLOAD_MAX_BYTES ?>;
  var EXTENSOES = <?= json_encode($extensoes) ?>;

  function $(s) { return document.querySelector(s); }
  function esc(v) {
    return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function aviso(i, t, x) { return ArqDlg.aviso(i, t, x); }
  function bytes(b) {
    var u = ['B', 'KB', 'MB', 'GB'], i = 0;
    while (b >= 1024 && i < 3) { b /= 1024; i++; }
    return b.toFixed(i ? 1 : 0).replace('.', ',') + ' ' + u[i];
  }
  function fmtDoc(v) {
    var d = String(v || '').replace(/\D/g, '');
    if (d.length === 11) { return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4'); }
    if (d.length === 14) { return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5'); }
    return d;
  }

  /* --------- Validação de CPF e CNPJ (evita registro com dígito errado) --- */
  function cpfValido(c) {
    if (!/^\d{11}$/.test(c) || /^(\d)\1{10}$/.test(c)) { return false; }
    var s = 0, i;
    for (i = 0; i < 9; i++) { s += parseInt(c[i], 10) * (10 - i); }
    var d1 = (s * 10) % 11 % 10;
    if (d1 !== parseInt(c[9], 10)) { return false; }
    s = 0;
    for (i = 0; i < 10; i++) { s += parseInt(c[i], 10) * (11 - i); }
    return ((s * 10) % 11 % 10) === parseInt(c[10], 10);
  }
  function cnpjValido(c) {
    if (!/^\d{14}$/.test(c) || /^(\d)\1{13}$/.test(c)) { return false; }
    function dig(base, pesos) {
      var s = 0;
      for (var i = 0; i < pesos.length; i++) { s += parseInt(base[i], 10) * pesos[i]; }
      var r = s % 11;
      return r < 2 ? 0 : 11 - r;
    }
    var p1 = [5,4,3,2,9,8,7,6,5,4,3,2], p2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
    return dig(c, p1) === parseInt(c[12], 10) && dig(c, p2) === parseInt(c[13], 10);
  }

  /* ================= Partes ================= */
  var partes = PARTES_INICIAIS.slice();

  function pintarPartes() {
    var tb = $('#partes');
    tb.innerHTML = partes.map(function (p, i) {
      return '<tr>' +
        '<td class="arq-mono">' + esc(fmtDoc(p.cpf) || '—') + '</td>' +
        '<td>' + esc(p.nome) + '</td>' +
        '<td>' + esc(p.papel || '—') + '</td>' +
        '<td><button type="button" class="arq-btn arq-btn-sm arq-btn-ic arq-btn-perigo" data-parte="' + i + '" title="Remover"><i class="fa fa-times"></i></button></td>' +
        '</tr>';
    }).join('');
    $('#sem-partes').style.display = partes.length ? 'none' : 'block';
  }

  function adicionarParte() {
    var cpf = $('#p-cpf').value.replace(/\D/g, '');
    var nome = $('#p-nome').value.trim();
    var papel = $('#p-papel').value.trim();

    if (!nome && !cpf) { aviso('warning', 'Informe a parte', 'Preencha ao menos o nome.'); return; }
    if (!nome) { aviso('warning', 'Nome obrigatório', 'A parte precisa de um nome.'); return; }
    if (cpf) {
      if (cpf.length !== 11 && cpf.length !== 14) {
        aviso('warning', 'Documento incompleto', 'CPF tem 11 dígitos e CNPJ tem 14.'); return;
      }
      if (cpf.length === 11 && !cpfValido(cpf)) { aviso('error', 'CPF inválido', 'Os dígitos verificadores não conferem.'); return; }
      if (cpf.length === 14 && !cnpjValido(cpf)) { aviso('error', 'CNPJ inválido', 'Os dígitos verificadores não conferem.'); return; }
      if (partes.some(function (p) { return p.cpf === cpf; })) {
        aviso('warning', 'Parte repetida', 'Este documento já está na lista.'); return;
      }
    }

    partes.push({ cpf: cpf, nome: nome.toUpperCase(), papel: papel });
    pintarPartes();
    $('#p-cpf').value = ''; $('#p-nome').value = ''; $('#p-papel').value = '';
    $('#p-cpf').focus();
  }

  $('#add-parte').addEventListener('click', adicionarParte);
  ['#p-cpf', '#p-nome', '#p-papel'].forEach(function (s) {
    $(s).addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); adicionarParte(); }
    });
  });
  $('#p-cpf').addEventListener('input', function () {
    var d = this.value.replace(/\D/g, '').slice(0, 14);
    this.value = d.length > 11 ? fmtDoc(d) : (d.length === 11 ? fmtDoc(d) : d);
  });
  $('#partes').addEventListener('click', function (e) {
    var b = e.target.closest('[data-parte]');
    if (b) { partes.splice(parseInt(b.dataset.parte, 10), 1); pintarPartes(); }
  });
  pintarPartes();

  /* ================= Categoria nova ================= */
  $('#nova-categoria').addEventListener('click', function () {
    var criar = function (nome) {
      var fd = new FormData();
      fd.append('acao', 'criar'); fd.append('nome', nome);
      fetch('api/categorias.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
        headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) { throw new Error(j.erro); }
        var o = document.createElement('option');
        o.value = nome; o.textContent = nome; o.selected = true;
        $('#categoria').appendChild(o);
      }).catch(function (e) { aviso('error', 'Não foi possível criar', e.message); });
    };
    ArqDlg.perguntar('Nova categoria', {
      exemplo: 'Ex.: Escritura de doação', botao: 'Criar'
    }).then(function (v) { if (v && v.trim()) { criar(v.trim()); } });
  });

  /* ================= Anexos ================= */
  var fila = [];
  var removidos = [];
  var saindo = false;   // navegação disparada pelo sistema, não pelo usuário

  function pintarFila() {
    $('#fila').innerHTML = fila.map(function (f, i) {
      return '<div class="arq-anexo">' +
        '<div class="arq-anexo-ic"><i class="fa fa-file-o"></i></div>' +
        '<div class="arq-anexo-info"><div class="arq-anexo-nome">' + esc(f.name) + '</div>' +
        '<div class="arq-anexo-meta">' + bytes(f.size) + ' · aguardando envio</div></div>' +
        '<div class="arq-anexo-acoes"><button type="button" class="arq-btn arq-btn-sm arq-btn-ic arq-btn-perigo" data-fila="' + i + '"><i class="fa fa-times"></i></button></div>' +
        '</div>';
    }).join('');
  }

  function receber(arquivos) {
    Array.prototype.forEach.call(arquivos, function (f) {
      var ext = (f.name.split('.').pop() || '').toLowerCase();
      if (EXTENSOES.length && EXTENSOES.indexOf(ext) < 0) {
        aviso('warning', 'Formato não aceito', f.name + ' (.' + ext + ') não é um tipo permitido.');
        return;
      }
      if (f.size > MAX_BYTES) {
        aviso('warning', 'Arquivo grande demais', f.name + ' tem ' + bytes(f.size) + '. O limite é ' + bytes(MAX_BYTES) + '.');
        return;
      }
      if (fila.some(function (x) { return x.name === f.name && x.size === f.size; })) { return; }
      fila.push(f);
    });
    pintarFila();
  }

  var solta = $('#solta');
  solta.addEventListener('click', function () { $('#file-input').click(); });
  solta.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('#file-input').click(); }
  });
  $('#file-input').addEventListener('change', function () { receber(this.files); this.value = ''; });
  ['dragenter', 'dragover'].forEach(function (ev) {
    solta.addEventListener(ev, function (e) { e.preventDefault(); solta.classList.add('sobre'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    solta.addEventListener(ev, function (e) { e.preventDefault(); solta.classList.remove('sobre'); });
  });
  solta.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files) { receber(e.dataTransfer.files); }
  });
  $('#fila').addEventListener('click', function (e) {
    var b = e.target.closest('[data-fila]');
    if (b) { fila.splice(parseInt(b.dataset.fila, 10), 1); pintarFila(); }
  });

  var existentes = document.getElementById('anexos-existentes');
  if (existentes) {
    existentes.addEventListener('click', function (e) {
      var b = e.target.closest('.remover-anexo');
      if (!b) { return; }
      var linha = b.closest('.arq-anexo');
      var idx = parseInt(linha.dataset.indice, 10);
      if (removidos.indexOf(idx) >= 0) {
        removidos = removidos.filter(function (x) { return x !== idx; });
        linha.style.opacity = '';
        linha.style.textDecoration = '';
        b.innerHTML = '<i class="fa fa-times"></i>';
        b.title = 'Remover';
      } else {
        removidos.push(idx);
        linha.style.opacity = '.45';
        linha.style.textDecoration = 'line-through';
        b.innerHTML = '<i class="fa fa-undo"></i>';
        b.title = 'Manter';
      }
    });
  }

  /* ================= Envio ================= */
  var enviando = false;

  $('#arq-form').addEventListener('submit', function (e) {
    e.preventDefault();
    if (enviando) { return; }

    // validação de campo
    var falhou = false;
    [['atribuicao', function (v) { return v !== ''; }],
     ['categoria', function (v) { return v !== ''; }],
     ['data_ato', function (v) { return v !== '' && v <= new Date().toISOString().slice(0, 10); }]
    ].forEach(function (par) {
      var campo = document.getElementById(par[0]);
      var ok = par[1](campo.value);
      campo.closest('div').classList.toggle('arq-campo-erro', !ok);
      if (!ok) { falhou = true; }
    });
    if (falhou) {
      aviso('warning', 'Campos obrigatórios', 'Revise os campos destacados em vermelho.');
      return;
    }
    if (!partes.length) {
      aviso('warning', 'Sem partes', 'Adicione ao menos uma parte envolvida.');
      document.getElementById('p-nome').focus();
      return;
    }

    var fd = new FormData();
    ['id', 'atribuicao', 'categoria', 'data_ato', 'livro', 'folha', 'termo', 'protocolo', 'matricula', 'descricao']
      .forEach(function (n) {
        var el = document.querySelector('[name="' + n + '"]');
        fd.append(n, el ? el.value : '');
      });
    fd.append('partes_envolvidas', JSON.stringify(partes));
    fd.append('remover', JSON.stringify(removidos));
    fila.forEach(function (f) { fd.append('file-input[]', f); });

    enviando = true;
    var btn = $('#salvar');
    var rotulo = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando…';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/salvar.php');
    xhr.setRequestHeader('X-CSRF-Token', CSRF);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.upload.addEventListener('progress', function (ev) {
      if (ev.lengthComputable && fila.length) {
        var p = Math.round((ev.loaded / ev.total) * 100);
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando ' + p + '%';
      }
    });

    xhr.onload = function () {
      enviando = false;
      btn.disabled = false;
      btn.innerHTML = rotulo;
      var j;
      try { j = JSON.parse(xhr.responseText); } catch (err) { j = null; }

      if (!j || !j.ok) {
        if (xhr.status === 401 && j && j.redirect) {
          aviso('warning', 'Sessão encerrada', 'Faça login novamente.')
            .then(function () { location.href = j.redirect; });
          return;
        }
        aviso('error', 'Não foi possível salvar', (j && j.erro) || 'Erro inesperado no servidor.');
        return;
      }

      var seguir = function () {
        // Deu certo: a fila já foi para o servidor. Sem limpar isso o guarda
        // de saída da página achava que ainda havia anexo pendente e o
        // navegador abria o "Sair do site?" logo após o salvamento.
        fila = [];
        removidos = [];
        saindo = true;
        location.href = 'index.php?abrir=' + encodeURIComponent(j.id);
      };
      if (j.avisos && j.avisos.length) {
        aviso('warning', 'Salvo com ressalvas', 'Alguns anexos não foram aceitos:\n\n' + j.avisos.join('\n')).then(seguir);
      } else if (window.Swal) {
        Swal.fire({
          icon: 'success', title: j.mensagem, timer: 1400, showConfirmButton: false
        }).then(seguir);
      } else {
        seguir();
      }
    };

    xhr.onerror = function () {
      enviando = false;
      btn.disabled = false;
      btn.innerHTML = rotulo;
      aviso('error', 'Falha de conexão', 'Verifique a rede e tente novamente.');
    };

    xhr.send(fd);
  });

  /* ================= Selos ================= */
  var formSelo = document.getElementById('selo-form');
  if (formSelo) {
    document.getElementById('btnAddSelo').addEventListener('click', function () {
      formSelo.style.display = '';
      this.style.display = 'none';
      document.getElementById('quantidade').focus();
    });

    // O motivo só faz sentido quando o selo é isento.
    document.getElementById('isento').addEventListener('change', function () {
      var m = document.getElementById('motivo-wrapper');
      m.style.display = this.checked ? '' : 'none';
      var campo = document.getElementById('motivo_isencao');
      if (this.checked) { campo.setAttribute('required', 'required'); }
      else { campo.removeAttribute('required'); campo.value = ''; }
    });

    function cardSelo(qr, numero, texto) {
      var d = document.createElement('div');
      d.className = 'arq-selo';
      d.innerHTML =
        (qr ? '<img src="data:image/png;base64,' + qr + '" alt="QR Code do selo">' : '') +
        '<div style="min-width:0"><div class="arq-selo-num">' + esc(numero) +
        ' <button type="button" class="arq-btn arq-btn-sm arq-btn-ic" data-copiar="' + esc(numero) +
        '" title="Copiar número"><i class="fa fa-clone"></i></button></div>' +
        '<div class="arq-selo-txt">' + texto + '</div></div>';
      return d;
    }

    // O selador devolve HTML pronto; se vier no formato antigo, extraímos os campos.
    function inserirSelo(htmlServidor) {
      var h = htmlServidor || '';
      var qr  = (h.match(/base64,([^"']+)["']/i) || [])[1] || '';
      var num = (h.match(/Selo:\s*([A-Z0-9.\-]+)/i) || [])[1] || '';
      var txt = (h.match(/<\/strong><\/p>\s*<p[^>]*>([\s\S]*?)<\/p>/i) || [])[1] || '';
      document.getElementById('selos-container').appendChild(cardSelo(qr, num, txt));
    }

    formSelo.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = document.getElementById('solicitar-selo-btn');
      var rotulo = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Solicitando…';

      // Leva os dados do ato que já estão na tela.
      document.getElementById('livro_selo').value = $('#livro').value;
      document.getElementById('folha_selo').value = $('#folha').value;
      document.getElementById('termo_selo').value = $('#termo').value;
      document.getElementById('partes_selo').value =
        partes.map(function (p) { return p.nome; }).join('; ');

      fetch('selos_arquivamentos.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams(new FormData(formSelo))
      }).then(function (r) { return r.text(); }).then(function (texto) {
        var d = {};
        try { d = JSON.parse(texto); } catch (err) { d = {}; }

        if (d.success) {
          inserirSelo(d.html);
          formSelo.reset();
          document.getElementById('motivo-wrapper').style.display = 'none';
          formSelo.style.display = 'none';
          document.getElementById('btnAddSelo').style.display = '';
          aviso('success', 'Selo gerado', d.success);
        } else {
          aviso('error', 'Não foi possível gerar o selo',
                d.error || 'O selador devolveu uma resposta inesperada.');
        }
      }).catch(function () {
        aviso('error', 'Falha de comunicação', 'Não foi possível falar com o selador.');
      }).then(function () {
        btn.disabled = false;
        btn.innerHTML = rotulo;
      });
    });

    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-copiar]');
      if (!b) { return; }
      var n = b.dataset.copiar;
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(n);
      } else {
        var ta = document.createElement('textarea');
        ta.value = n; ta.style.position = 'fixed'; ta.style.left = '-9999px';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (err) {}
        document.body.removeChild(ta);
      }
      aviso('success', 'Número copiado', n);
    });
  }

  /* Guarda de saída da página.
     Só vale para saída acidental (fechar a aba, voltar) com anexo ainda não
     enviado. Navegação feita pelo próprio sistema levanta a flag "saindo",
     senão o aviso apareceria depois de um salvamento bem-sucedido.

     Este diálogo é do navegador e não pode ser trocado por SweetAlert2: a
     especificação do beforeunload não permite conteúdo próprio, justamente
     para páginas não poderem impedir o usuário de sair. */
  window.addEventListener('beforeunload', function (e) {
    if (saindo || !fila.length) { return; }
    e.preventDefault();
    e.returnValue = '';
  });

  // Sair pelos links do próprio módulo não é saída acidental.
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (a && !a.target && a.getAttribute('href').charAt(0) !== '#') { saindo = true; }
  });
})();
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
