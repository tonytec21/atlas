<?php
/**
 * atlas/kb/aria.php
 * Chat de pesquisa normativa sobre o acervo de provimentos e resolucoes.
 */
include(__DIR__ . '/../provimentos/session_check.php');
checkSession();
include(__DIR__ . '/../provimentos/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$conn = getDatabaseConnection();

// Migracao automatica no primeiro acesso (idempotente, ~1s, sem custo).
include(__DIR__ . '/bootstrap_kb.php');

$origens = $conn->query("SELECT DISTINCT origem FROM provimentos WHERE origem IS NOT NULL ORDER BY origem")
                ->fetchAll(PDO::FETCH_COLUMN);
$tipos = $conn->query("SELECT DISTINCT tipo FROM provimentos WHERE tipo IS NOT NULL ORDER BY tipo")
              ->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aria &middot; Pesquisa normativa</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        /* Segue o mecanismo do Atlas: variaveis sob body.light-mode / body.dark-mode.
           Nenhuma cor fixa daqui para baixo -- so referencia as variaveis. */
        body.light-mode {
            --kb-sup:      #ffffff;   /* superficie dos cartoes  */
            --kb-sup2:     #f8fbfb;   /* superficie secundaria   */
            --kb-brd:      #e2e8f0;
            --kb-brd-sutil:#edf2f4;
            --kb-txt:      #2d3748;
            --kb-txt2:     #8a97a5;
            --kb-ac:       #0f6f77;   /* acento em texto e borda */
            --kb-ac-esc:   #0a4f55;
            --kb-ac-bg:    #0f6f77;   /* fundo de chip, texto branco */
            --kb-hover:    #f2f6f7;
            --kb-user-bg:  #e6f2f3;
            --kb-user-txt: #14383d;
            --kb-in-bg:    #ffffff;
            --kb-in-txt:   #2d3748;
            --kb-in-ph:    #a0aec0;
            --kb-idx-bg:   #fffdf5;
            --kb-idx-brd:  #ffe08a;
            --kb-ok-bg:    #f6fdf8;
            --kb-ok-brd:   #c3e6cb;
            --kb-vazio:    #cbd5e0;
        }
        body.dark-mode {
            --kb-sup:      #0b1324;
            --kb-sup2:     #0e1627;
            --kb-brd:      rgba(255,255,255,.10);
            --kb-brd-sutil:rgba(255,255,255,.06);
            --kb-txt:      #e5e7eb;
            --kb-txt2:     #9ca3af;
            --kb-ac:       #5eead4;   /* teal escuro fica ilegivel no escuro */
            --kb-ac-esc:   #99f6e4;
            --kb-ac-bg:    #0f766e;
            --kb-hover:    rgba(255,255,255,.06);
            --kb-user-bg:  #164e4a;
            --kb-user-txt: #d1fae5;
            --kb-in-bg:    #0b1324;
            --kb-in-txt:   #e5e7eb;
            --kb-in-ph:    #6b7280;
            --kb-idx-bg:   #2a2210;
            --kb-idx-brd:  #a16207;
            --kb-ok-bg:    #0f2a1d;
            --kb-ok-brd:   #166534;
            --kb-vazio:    #374151;
        }

        .aria-grid   { display:grid; grid-template-columns:250px 1fr; gap:20px; align-items:start; }
        @media (max-width: 900px) { .aria-grid { grid-template-columns:1fr; } .lateral { display:none; } }

        .lateral     { border:1px solid var(--kb-brd); border-radius:8px; background:var(--kb-sup);
                       padding:14px; position:sticky; top:16px; }
        .conv        { padding:8px 10px; border-radius:6px; cursor:pointer; font-size:.86rem;
                       color:var(--kb-txt); display:flex; justify-content:space-between;
                       align-items:center; gap:6px; }
        .conv:hover  { background:var(--kb-hover); }
        .conv.on     { background:var(--kb-hover); color:var(--kb-ac); font-weight:600; }
        .conv small  { display:block; color:var(--kb-txt2); font-weight:400; font-size:.72rem; }
        .conv .del   { opacity:0; color:#e05252; }
        .conv:hover .del { opacity:.7; }

        .fluxo       { min-height:340px; }
        .msg         { margin-bottom:22px; }
        .msg-user    { background:var(--kb-user-bg); color:var(--kb-user-txt);
                       border-radius:12px 12px 2px 12px; padding:12px 16px;
                       margin-left:auto; max-width:78%; width:fit-content; }
        .msg-aria    { max-width:100%; }
        .bolha       { border:1px solid var(--kb-brd); border-radius:12px 12px 12px 2px;
                       padding:16px 20px; background:var(--kb-sup); }
        .corpo       { font-size:.96rem; line-height:1.72; color:var(--kb-txt); }
        .corpo h2    { font-size:1.05rem; margin:18px 0 8px; color:var(--kb-ac); font-weight:600; }
        .corpo h3    { font-size:.98rem; margin:14px 0 6px; color:var(--kb-ac); font-weight:600; }
        .corpo ul,
        .corpo ol    { padding-left:22px; margin-bottom:10px; }
        .corpo li    { margin-bottom:5px; }
        .corpo p     { margin-bottom:10px; }
        .corpo hr    { border-color:var(--kb-brd-sutil); }
        .corpo table { width:100%; border-collapse:collapse; font-size:.88rem; margin:12px 0; }
        .corpo th    { background:var(--kb-sup2); text-align:left; }
        .corpo th,
        .corpo td    { border:1px solid var(--kb-brd); padding:7px 10px; vertical-align:top; }
        .corpo code  { background:var(--kb-sup2); color:var(--kb-ac); padding:1px 5px;
                       border-radius:3px; font-size:.86em; }

        /* Layout em bloco, nao flex. A caixa fica absoluta no recuo do item,
           entao texto e sublista sao blocos empilhados -- nao ha como a
           sublista transbordar por cima do rotulo.
           A ordem das regras importa: ul.checklist e .tarefa > ul tem a mesma
           especificidade, entao a lista generica vem primeiro e as regras de
           sublista depois, para prevalecerem. */
        ul.checklist  { padding-left:0; list-style:none; margin-bottom:10px; }

        .tarefa       { list-style:none; position:relative; padding-left:25px;
                        margin-bottom:9px; }
        .tarefa > input { position:absolute; left:0; top:5px; width:16px; height:16px;
                        cursor:pointer; accent-color:var(--kb-ac-bg); }
        .tarefa > span  { display:block; }
        .tarefa.feito > span { text-decoration:line-through; color:var(--kb-txt2); }

        /* Sublista: bloco abaixo do texto, com recuo proprio. */
        .tarefa > ul,
        .tarefa > ol  { margin:9px 0 4px; padding-left:12px; }

        /* Item que agrupa outros: rotulo de secao, sem caixa de marcacao. */
        .tarefa.grupo { padding-left:0; margin-top:16px; }
        .tarefa.grupo > span { font-weight:600; color:var(--kb-ac); margin-bottom:2px; }
        .tarefa.grupo > ul,
        .tarefa.grupo > ol { margin-top:6px; padding-left:10px; }

        .cit         { display:inline-block; min-width:20px; padding:0 6px; margin:0 2px;
                       background:var(--kb-ac-bg); color:#fff; border-radius:4px; font-size:.75rem;
                       font-weight:600; cursor:pointer; vertical-align:1px; }
        .cit:hover   { filter:brightness(1.2); }

        .fontes-cab  { font-size:.78rem; color:var(--kb-txt2); cursor:pointer; margin-top:12px;
                       padding-top:10px; border-top:1px solid var(--kb-brd-sutil); }
        .fonte       { border-left:3px solid var(--kb-ac-bg); padding:9px 13px; margin-top:8px;
                       background:var(--kb-sup2); border-radius:0 5px 5px 0; }
        .fonte-cab   { font-weight:600; color:var(--kb-ac); font-size:.85rem; }
        .fonte-txt   { font-size:.82rem; color:var(--kb-txt); opacity:.85; margin-top:5px; }
        .fonte.dest  { box-shadow:0 0 0 2px #f0ad4e; }

        .acoes       { margin-top:10px; display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
        .acoes .btn  { font-size:.78rem; padding:2px 9px; }
        .meta        { font-size:.78rem; color:var(--kb-txt2); }
        .selo-alt    { background:#c2680e; color:#fff; font-size:.68rem; padding:2px 7px; border-radius:9px; }

        .compositor  { position:sticky; bottom:0; background:var(--kb-sup);
                       padding:12px 0 8px; border-top:1px solid var(--kb-brd); }
        #entrada, .compositor select {
                       background:var(--kb-in-bg); color:var(--kb-in-txt);
                       border:1px solid var(--kb-brd); }
        #entrada     { resize:none; overflow-y:auto; max-height:180px; }
        #entrada::placeholder { color:var(--kb-in-ph); }
        #entrada:focus, .compositor select:focus {
                       background:var(--kb-in-bg); color:var(--kb-in-txt);
                       border-color:var(--kb-ac); box-shadow:none; }
        .sugestao    { cursor:pointer; border:1px solid var(--kb-brd); border-radius:16px;
                       padding:6px 14px; font-size:.83rem; color:var(--kb-txt);
                       background:var(--kb-sup); }
        .sugestao:hover { border-color:var(--kb-ac); color:var(--kb-ac); }

        .painel-idx    { border:1px solid var(--kb-idx-brd); background:var(--kb-idx-bg);
                         border-radius:8px; padding:16px 20px; color:var(--kb-txt); }
        .painel-idx.ok { border-color:var(--kb-ok-brd); background:var(--kb-ok-bg); }

        .pensando span { display:inline-block; width:7px; height:7px; margin-right:3px;
                         background:var(--kb-ac); border-radius:50%; animation:pulsa 1.3s infinite; }
        .pensando span:nth-child(2){ animation-delay:.2s } .pensando span:nth-child(3){ animation-delay:.4s }
        @keyframes pulsa { 0%,60%,100%{opacity:.25} 30%{opacity:1} }

        .vazio-ico   { font-size:2.2rem; color:var(--kb-vazio); }
    </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container-fluid" style="max-width:1180px">

    <div class="d-flex justify-content-between align-items-center flex-wrap no-print">
      <h3 class="mb-0">Aria</h3>
      <div class="meta">
        <a href="relacoes.php">Rela&ccedil;&otilde;es entre normas</a> &middot;
        <a href="configurar.php">Configura&ccedil;&otilde;es</a>
        <span id="avisoRel"></span>
      </div>
    </div>
    <p class="meta mb-3">Pesquisa nos provimentos e resolu&ccedil;&otilde;es do CNJ e da CGJ/MA.</p>

    <div id="painelIndexacao" class="painel-idx mb-4 no-print" style="display:none;">
      <div class="d-flex justify-content-between align-items-start flex-wrap">
        <div>
          <h5 class="mb-1"><i class="fa fa-database"></i> <span id="idxTitulo">Base de conhecimento</span></h5>
          <div class="meta" id="idxResumo">Verificando...</div>
        </div>
        <div class="mt-2">
          <button id="btnIndexar" class="btn btn-warning" style="color:#fff!important">
            <i class="fa fa-bolt"></i> Indexar agora
          </button>
          <button id="btnParar" class="btn btn-outline-danger" style="display:none;">
            <i class="fa fa-stop"></i> Parar
          </button>
        </div>
      </div>
      <div id="idxProgresso" style="display:none;" class="mt-3">
        <div class="progress" style="height:22px;">
          <div id="idxBarra" class="progress-bar progress-bar-striped progress-bar-animated"
               style="width:0%; background:#0f6f77;">0%</div>
        </div>
        <div class="meta mt-2" id="idxDetalhe"></div>
      </div>
    </div>

    <div class="aria-grid">
      <div class="lateral no-print">
        <button class="btn btn-sm btn-block btn-primary mb-3" id="btnNova" style="color:#fff!important">
          <i class="fa fa-plus"></i> Nova conversa
        </button>
        <div class="meta mb-1">Conversas recentes</div>
        <div id="listaConversas"></div>
      </div>

      <div>
        <div class="fluxo" id="fluxo"></div>

        <div class="compositor no-print">
          <div id="sugestoes" class="d-flex flex-wrap mb-2" style="gap:7px;"></div>

          <div class="d-flex" style="gap:8px;">
            <textarea class="form-control" id="entrada" rows="2"
              placeholder="Pergunte sobre os provimentos, ou peca um checklist, um roteiro, um comparativo..."></textarea>
            <button class="btn btn-primary" id="btnEnviar" style="color:#fff!important; min-width:52px">
              <i class="fa fa-paper-plane"></i>
            </button>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap" style="gap:8px;">
            <span class="meta">Ctrl+Enter envia</span>
            <div class="d-flex align-items-center" style="gap:6px;">
              <select class="form-control form-control-sm" id="origem" style="width:auto">
                <option value="">Todas as origens</option>
                <?php foreach ($origens as $o): ?>
                  <option value="<?php echo htmlspecialchars($o, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($o, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
              <select class="form-control form-control-sm" id="tipo" style="width:auto">
                <option value="">Todos os tipos</option>
                <?php foreach ($tipos as $t): ?>
                  <option value="<?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
              <select class="form-control form-control-sm" id="ano_min" style="width:auto">
                <option value="">Qualquer ano</option>
                <?php for ($a = date('Y'); $a >= 2000; $a -= 1): ?>
                  <option value="<?php echo $a; ?>"><?php echo $a; ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<?php include(__DIR__ . '/parcial_swal.php'); ?>
<script>
var conversaId = null;
var ocupado = false;

var SUGESTOES = [
  'Checklist de conferência para escritura de compra e venda',
  'Quais os prazos de comunicação à Corregedoria?',
  'Roteiro para reconhecimento de firma por autenticidade',
  'O que mudou nos últimos provimentos sobre indisponibilidade?'
];

function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// ---------------------------------------------------------------------------
// Markdown -> HTML
// Escapa TUDO primeiro e so depois insere marcacao. Nenhum HTML vindo do
// modelo chega ao DOM; o que existe e o que este renderizador cria.
// ---------------------------------------------------------------------------
function inline(t) {
  return t
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[\s(])\*([^*\n]+)\*/g, '$1<em>$2</em>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    // O modelo cita em grupo: "[2, 7]" e tao comum quanto "[2]". Tratar so um
    // numero por colchete fazia a citacao agrupada virar texto morto.
    .replace(/\[(\d+(?:\s*,\s*\d+)*)\]/g, function (_, nums) {
      return nums.split(',').map(function (n) {
        n = n.trim();
        return '<span class="cit" onclick="irFonte(this,' + n + ')">' + n + '</span>';
      }).join('');
    });
}

function renderizar(md) {
  var linhas = esc(md).split('\n');
  var html = '';
  var pilha = [];      // [{tag, cls, indent}] listas abertas, da externa para a interna
  var liAberto = [];   // ha <li> aberto em cada nivel?
  var liTarefa = [];   // o <li> aberto neste nivel e um item de checklist?
  var liInicio = [];   // posicao do <li> aberto dentro de html (para reescrita)
  var ultimoNum = {};  // ultimo ordinal usado por nivel de indentacao
  var tabela = null;

  function fecharUma() {
    var n = pilha.length - 1;
    if (liAberto[n]) { html += '</li>'; }
    html += '</' + pilha[n].tag + '>';
    pilha.pop(); liAberto.pop(); liTarefa.pop(); liInicio.pop();
    // Ao fechar uma lista aninhada, o <li> do pai continua aberto de proposito:
    // a sublista pertence a ele.
  }
  function fecharTudo() { while (pilha.length) { fecharUma(); } }

  function abrir(tag, cls, indent, num) {
    // Um item de checklist que vira pai de sublista e um agrupador, nao uma
    // tarefa: o modelo escreve "- [ ]" nele igual, mas marcar o titulo nao faz
    // sentido. Remove a caixa dele e o trata como rotulo da secao.
    if (pilha.length && liAberto[pilha.length - 1] && liTarefa[pilha.length - 1]) {
      var pos = liInicio[pilha.length - 1];
      var trecho = html.slice(pos)
        .replace(/<input type="checkbox"[^>]*>/, '')
        .replace('<li class="tarefa', '<li class="tarefa grupo');
      html = html.slice(0, pos) + trecho;
      liTarefa[pilha.length - 1] = false;
    }

    var attr = '';
    // Se a numeracao ja passou por aqui antes (uma lista de marcadores
    // interrompeu o fluxo), retoma de onde parou em vez de reiniciar em 1.
    if (tag === 'ol') {
      // O modelo costuma escrever "1." em TODOS os itens; o numero literal so
      // vale para a primeira lista do bloco. Se ja numeramos neste nivel,
      // continuamos a contagem em vez de reiniciar.
      var anterior = ultimoNum[indent] || 0;
      var inicio = anterior > 0 ? anterior + 1 : (num || 1);
      if (inicio > 1) { attr = ' start="' + inicio + '"'; }
    }
    html += '<' + tag + (cls ? ' class="' + cls + '"' : '') + attr + '>';
    pilha.push({ tag: tag, cls: cls, indent: indent });
    liAberto.push(false); liTarefa.push(false); liInicio.push(0);
  }

  function additem(indent, tag, cls, conteudo, num, tarefa, feito) {
    // Recuou: fecha as listas mais internas.
    while (pilha.length && indent < pilha[pilha.length - 1].indent) { fecharUma(); }

    if (!pilha.length || indent > pilha[pilha.length - 1].indent) {
      abrir(tag, cls, indent, num);                       // nova lista (raiz ou aninhada)
    } else if (pilha[pilha.length - 1].tag !== tag
               || pilha[pilha.length - 1].cls !== cls) {
      fecharUma();
      abrir(tag, cls, indent, num);                       // mesmo nivel, outro tipo
    } else if (liAberto[pilha.length - 1]) {
      html += '</li>'; liAberto[pilha.length - 1] = false; // irmao
    }

    var nivel = pilha.length - 1;
    if (tag === 'ol') {
      ultimoNum[indent] = (ultimoNum[indent] || 0) + 1;
    }

    liInicio[nivel] = html.length;
    liTarefa[nivel] = !!tarefa;
    html += '<li' + (tarefa ? ' class="tarefa' + (feito ? ' feito' : '') + '"' : '') + '>';
    if (tarefa) {
      html += '<input type="checkbox"' + (feito ? ' checked' : '')
            + ' onchange="this.closest(\'li\').classList.toggle(\'feito\', this.checked)">'
            + '<span>' + conteudo + '</span>';
    } else {
      html += conteudo;
    }
    liAberto[nivel] = true;
  }

  function larguraIndent(s) {
    var n = 0;
    for (var i = 0; i < s.length; i++) { n += (s[i] === '\t') ? 4 : 1; }
    return n;
  }

  function fecharTabela() {
    if (!tabela) { return; }
    html += '<table><thead><tr>';
    tabela.cab.forEach(function (c) { html += '<th>' + inline(c) + '</th>'; });
    html += '</tr></thead><tbody>';
    tabela.linhas.forEach(function (l) {
      html += '<tr>';
      l.forEach(function (c) { html += '<td>' + inline(c) + '</td>'; });
      html += '</tr>';
    });
    html += '</tbody></table>';
    tabela = null;
  }

  for (var i = 0; i < linhas.length; i++) {
    var l = linhas[i], m;

    // tabela
    if (/^\s*\|.*\|\s*$/.test(l)) {
      if (/^[\s|:-]+$/.test(l)) { continue; }
      fecharTudo();
      var celulas = l.replace(/^\s*\||\|\s*$/g, '').split('|').map(function (c) { return c.trim(); });
      if (!tabela) { tabela = { cab: celulas, linhas: [] }; } else { tabela.linhas.push(celulas); }
      continue;
    }
    fecharTabela();

    // Linha em branco nao fecha lista: o modelo separa itens com linha vazia,
    // e fechar aqui era o que quebrava a numeracao em blocos de "1.".
    if (!l.trim()) { continue; }

    // checklist
    if ((m = l.match(/^([ \t]*)[-*][ \t]*\[([ xX])\][ \t]*(.+)$/))) {
      additem(larguraIndent(m[1]), 'ul', 'checklist', inline(m[3]), null,
              true, m[2].toLowerCase() === 'x');
      continue;
    }
    // lista numerada
    if ((m = l.match(/^([ \t]*)(\d+)[.)][ \t]+(.+)$/))) {
      additem(larguraIndent(m[1]), 'ol', null, inline(m[3]), parseInt(m[2], 10), false, false);
      continue;
    }
    // lista com marcador
    if ((m = l.match(/^([ \t]*)[-*][ \t]+(.+)$/))) {
      additem(larguraIndent(m[1]), 'ul', null, inline(m[2]), null, false, false);
      continue;
    }

    // separador
    if (/^\s*(-{3,}|\*{3,}|_{3,})\s*$/.test(l)) { fecharTudo(); html += '<hr>'; continue; }

    // titulos
    if ((m = l.match(/^\s*###\s+(.+)$/))) { fecharTudo(); ultimoNum = {}; html += '<h3>' + inline(m[1]) + '</h3>'; continue; }
    if ((m = l.match(/^\s*##?\s+(.+)$/)))  { fecharTudo(); ultimoNum = {}; html += '<h2>' + inline(m[1]) + '</h2>'; continue; }

    // paragrafo solto: se houver <li> aberto, o texto pertence a ele
    if (pilha.length && liAberto[pilha.length - 1] && /^[ \t]{2,}\S/.test(l)) {
      html += '<br>' + inline(l.trim());
      continue;
    }
    fecharTudo();
    html += '<p>' + inline(l) + '</p>';
  }

  fecharTudo(); fecharTabela();
  return html;
}

// ---------------------------------------------------------------------------
function irFonte(el, n) {
  var bloco = $(el).closest('.msg');
  bloco.find('.fontes').show();
  var alvo = bloco.find('.fonte').eq(n - 1);
  if (!alvo.length) return;
  $('html, body').animate({ scrollTop: alvo.offset().top - 90 }, 260);
  alvo.addClass('dest');
  setTimeout(function () { alvo.removeClass('dest'); }, 1500);
}

function htmlUsuario(txt) {
  return '<div class="msg"><div class="msg-user">' + esc(txt).replace(/\n/g, '<br>') + '</div></div>';
}

function htmlAria(m) {
  var h = '<div class="msg msg-aria" data-id="' + (m.id || '') + '"><div class="bolha">'
        + '<div class="corpo">' + renderizar(m.conteudo) + '</div>';

  if (m.fontes && m.fontes.length) {
    h += '<div class="fontes-cab" onclick="$(this).next().slideToggle(120)">'
       + '<i class="fa fa-book"></i> ' + m.fontes.length + ' fonte(s) &mdash; clique para ver</div>'
       + '<div class="fontes" style="display:none">';
    m.fontes.forEach(function (f) {
      h += '<div class="fonte"><div class="fonte-cab">[' + f.n + '] ' + esc(f.provimento)
         + ' &middot; ' + esc(f.origem)
         + (f.referencia ? ' &middot; ' + esc(f.referencia) : '')
         + (f.situacao === 'alterado' ? ' <span class="selo-alt">redação alterada</span>' : '')
         + ' <span class="meta">(' + f.data + ')</span></div>'
         + '<div class="fonte-txt">' + esc(f.trecho) + '</div>'
         + '<a class="btn btn-sm btn-outline-info mt-2" target="_blank" href="' + esc(f.anexo)
         + '"><i class="fa fa-file-pdf-o"></i> Abrir documento</a></div>';
    });
    h += '</div>';
  }

  var temChecklist = /^[ \t]*[-*][ \t]*\[[ xX]\][ \t]*\S/m.test(m.conteudo || '');

  h += '<div class="acoes no-print">'
     + (temChecklist && m.id
        ? '<button class="btn btn-outline-primary" onclick="gerarImpresso(' + m.id + ', this)">'
          + '<i class="fa fa-file-pdf-o"></i> Gerar impresso</button>'
        : '')
     + '<button class="btn btn-outline-secondary" onclick="copiar(this)"><i class="fa fa-copy"></i> Copiar</button>'
     + '<button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa fa-print"></i> Imprimir</button>'
     + '<span class="ml-auto"></span>'
     + '<button class="btn btn-outline-success" onclick="avaliar(this,1)"><i class="fa fa-thumbs-o-up"></i></button>'
     + '<button class="btn btn-outline-danger" onclick="avaliar(this,0)"><i class="fa fa-thumbs-o-down"></i></button>'
     + '</div></div></div>';
  return h;
}

function gerarImpresso(msgId, btn) {
  var bolha = $(btn).closest('.bolha');
  var titulo = bolha.find('.corpo h2, .corpo h3').first().text().trim()
               || 'Checklist de conferência';
  var marcados = bolha.find('.tarefa input:checked').length;

  Swal.fire({
    title: 'Gerar impresso',
    html: '<div style="text-align:left;font-size:.92rem">'
        + '<label style="display:block;margin-bottom:4px">Título do impresso</label>'
        + '<input id="tit" class="swal2-input" style="margin:0;width:100%" value="'
        + titulo.replace(/"/g, '&quot;') + '">'
        + '<label style="display:block;margin-top:14px">'
        + '<input type="checkbox" id="tim" checked> Usar papel timbrado</label>'
        + (marcados
            ? '<label style="display:block;margin-top:6px"><input type="checkbox" id="man"> '
              + 'Manter os ' + marcados + ' item(ns) já marcados</label>'
            : '')
        + '</div>',
    showCancelButton: true,
    confirmButtonText: 'Gerar PDF',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#0f6f77',
    preConfirm: function () {
      return {
        titulo:   (document.getElementById('tit') || {}).value || '',
        manter:   !!(document.getElementById('man') || {}).checked,
        timbrado: !!(document.getElementById('tim') || {}).checked
      };
    }
  }).then(function (r) {
    if (!r.isConfirmed) return;
    var v = r.value || {};
    var url = 'checklist_pdf.php?mensagem_id=' + msgId
            + '&titulo=' + encodeURIComponent(v.titulo || '')
            + (v.manter ? '&marcados=1' : '')
            + (v.timbrado ? '' : '&timbrado=0');
    window.open(url, '_blank');
  });
}

function copiar(btn) {
  var texto = $(btn).closest('.bolha').find('.corpo').text();
  var ta = $('<textarea>').val(texto).css({ position: 'fixed', opacity: 0 }).appendTo('body');
  ta[0].select();
  try { document.execCommand('copy'); } catch (e) {}
  ta.remove();
  $(btn).html('<i class="fa fa-check"></i> Copiado');
  setTimeout(function () { $(btn).html('<i class="fa fa-copy"></i> Copiar'); }, 1600);
}

function avaliar(btn, util) {
  var id = $(btn).closest('.msg-aria').data('id');
  if (!id) return;
  $.post('conversar.php', { acao: 'avaliar', mensagem_id: id, util: util });
  $(btn).closest('.acoes').find('.fa-thumbs-o-up,.fa-thumbs-o-down').parent().prop('disabled', true);
  $(btn).addClass(util ? 'btn-success' : 'btn-danger').css('color', '#fff');
}

function rolar() { $('html, body').animate({ scrollTop: $(document).height() }, 200); }

// ---------------------------------------------------------------------------
function enviar(texto) {
  if (ocupado) return;
  texto = (texto || $('#entrada').val()).trim();
  if (texto.length < 2) return;

  ocupado = true;
  $('#entrada').val('').css('height', 'auto');
  $('#sugestoes').empty();
  $('#btnEnviar').prop('disabled', true);
  $('#fluxo').append(htmlUsuario(texto));
  $('#fluxo').append('<div class="msg" id="pensando"><div class="bolha">'
    + '<span class="pensando"><span></span><span></span><span></span></span> '
    + '<span class="meta">consultando o acervo...</span></div></div>');
  rolar();

  $.post('conversar.php', {
    acao: 'enviar',
    mensagem: texto,
    conversa_id: conversaId || 0,
    origem: $('#origem').val(),
    tipo: $('#tipo').val(),
    ano_min: $('#ano_min').val()
  }, null, 'json')
  .done(function (r) {
    $('#pensando').remove();
    if (!r.ok) {
      Swal.fire('Não deu certo', r.mensagem || '', 'error');
      return;
    }
    conversaId = r.conversa_id;
    $('#fluxo').append(htmlAria({ id: r.mensagem_id, conteudo: r.resposta, fontes: r.fontes }));
    if (!r.buscou) {
      $('#fluxo .msg-aria').last().find('.fontes-cab')
        .prepend('<i class="fa fa-refresh"></i> Reaproveitou as fontes anteriores &middot; ');
    }
    carregarConversas();
    rolar();
  })
  .fail(function (xhr) {
    $('#pensando').remove();
    var m = 'Falha de comunicação com o servidor.';
    try { m = JSON.parse(xhr.responseText).mensagem || m; } catch (e) {}
    Swal.fire('Erro', m, 'error');
  })
  .always(function () { ocupado = false; $('#btnEnviar').prop('disabled', false); });
}

function abrirConversa(id) {
  $.post('conversar.php', { acao: 'historico', conversa_id: id }, null, 'json').done(function (r) {
    if (!r.ok) return;
    conversaId = r.conversa_id;
    var h = '';
    r.mensagens.forEach(function (m) {
      h += (m.papel === 'user') ? htmlUsuario(m.conteudo) : htmlAria(m);
    });
    $('#fluxo').html(h);
    $('#sugestoes').empty();
    carregarConversas();
    rolar();
  });
}

function novaConversa() {
  conversaId = null;
  $('#fluxo').html('<div class="text-center meta" style="padding:50px 20px">'
    + '<i class="fa fa-comments-o" class="vazio-ico"></i>'
    + '<p class="mt-3">Pergunte sobre os provimentos, ou peça um checklist,<br>'
    + 'um roteiro de conferência ou um comparativo entre normas.</p></div>');
  var s = '';
  SUGESTOES.forEach(function (t) { s += '<div class="sugestao" onclick="enviar(\'' + t.replace(/'/g, "\\'") + '\')">' + esc(t) + '</div>'; });
  $('#sugestoes').html(s);
  carregarConversas();
}

function carregarConversas() {
  $.post('conversar.php', { acao: 'listar' }, null, 'json').done(function (r) {
    if (!r.ok) return;
    var h = '';
    r.conversas.forEach(function (c) {
      h += '<div class="conv' + (c.id == conversaId ? ' on' : '') + '">'
         + '<div style="flex:1; overflow:hidden" onclick="abrirConversa(' + c.id + ')">'
         + esc(c.titulo) + '<small>' + c.quando + '</small></div>'
         + '<i class="fa fa-trash del" onclick="excluirConversa(' + c.id + ')"></i></div>';
    });
    $('#listaConversas').html(h || '<div class="meta">Nenhuma ainda.</div>');
  });
}

function excluirConversa(id) {
  Swal.fire({
    title: 'Excluir esta conversa?', icon: 'warning', showCancelButton: true,
    confirmButtonText: 'Excluir', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
  }).then(function (r) {
    if (!r.isConfirmed) return;
    $.post('conversar.php', { acao: 'excluir', conversa_id: id }, null, 'json').done(function () {
      if (id == conversaId) novaConversa(); else carregarConversas();
    });
  });
}

$('#btnEnviar').on('click', function () { enviar(); });
$('#btnNova').on('click', novaConversa);
$('#entrada').on('keydown', function (e) {
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); enviar(); }
}).on('input', function () {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 180) + 'px';
});

novaConversa();


// ===================== Indexacao =====================
var idxToken = null, idxRodando = false, idxAlvo = 0;

function atualizarPainel(s) {
    var p = $('#painelIndexacao');
    var falta = s.pendentes_chunk + s.pendentes_embed;

    if (s.rel_sugeridas > 0) {
        $('#avisoRel').html(' &middot; <a href="relacoes.php" style="color:#e67e22">'
            + '<i class="fa fa-exclamation-triangle"></i> '
            + s.rel_sugeridas + ' relação(ões) aguardando revisão</a>');
    } else {
        $('#avisoRel').empty();
    }

    if (falta === 0 && s.chunks_com_vetor > 0) {
        p.addClass('ok');
        $('#idxTitulo').text('Base indexada');
        $('#idxResumo').html(s.chunks_total.toLocaleString('pt-BR') + ' trechos de '
            + s.docs_indexados.toLocaleString('pt-BR') + ' documentos, todos pesquisáveis.');
        $('#btnIndexar').hide();
        if (!idxRodando) p.hide();
        return;
    }

    p.removeClass('ok').show();
    $('#idxTitulo').text('Base ainda não indexada');

    var partes = [];
    if (s.pendentes_chunk) partes.push(s.pendentes_chunk.toLocaleString('pt-BR') + ' documentos a processar');
    if (s.pendentes_embed) partes.push(s.pendentes_embed.toLocaleString('pt-BR') + ' trechos sem embedding');
    var custo = s.pendentes_embed ? ' &middot; custo estimado US$ ' + s.custo_estimado.toFixed(2) : '';

    $('#idxResumo').html(partes.join(' &middot; ') + custo
        + '<br>Até concluir, a busca funciona apenas por palavra-chave.');

    if (!s.tem_chave) {
        $('#idxResumo').append('<br><strong>Chave da API não configurada</strong> &mdash; '
            + 'os trechos serão gerados, mas sem busca semântica. '
            + '<a href="configurar.php"><strong>Configurar agora</strong></a>');
    }
    $('#btnIndexar').show().prop('disabled', s.ocupado && !idxRodando);

    if (s.ocupado && !idxRodando) {
        $('#idxResumo').append('<br>Indexação em andamento por <strong>'
            + esc(s.trava.funcionario) + '</strong>.');
    }
}

function carregarStatus() {
    $.post('indexar.php', { acao: 'status' }, null, 'json').done(atualizarPainel);
}

function progresso(s) {
    var falta = s.pendentes_chunk + s.pendentes_embed;
    var pct = idxAlvo ? Math.min(99, Math.round((idxAlvo - falta) / idxAlvo * 100)) : 0;
    if (pct < 0) pct = 0;

    $('#idxBarra').css('width', pct + '%').text(pct + '%');
    var etapa;
    if (s.pendentes_chunk) {
        etapa = 'Separando trechos &middot; ' + s.pendentes_chunk.toLocaleString('pt-BR') + ' documentos restantes';
    } else if (s.pendentes_embed) {
        etapa = 'Gerando embeddings &middot; ' + s.pendentes_embed.toLocaleString('pt-BR') + ' trechos restantes';
    } else {
        etapa = 'Analisando revogações &middot; ' + s.pendentes_rel.toLocaleString('pt-BR') + ' documentos restantes';
    }
    $('#idxDetalhe').html(etapa + ' &middot; ' + s.chunks_total.toLocaleString('pt-BR') + ' trechos criados');
}

function pararUI(rotulo) {
    idxRodando = false;
    $('#btnParar').hide();
    $('#btnIndexar').show().prop('disabled', false)
        .html('<i class="fa fa-refresh"></i> ' + (rotulo || 'Retomar'));
}

function rodarLote() {
    if (!idxRodando) return;

    $.post('indexar.php', { acao: 'lote', token: idxToken }, null, 'json')
    .done(function (r) {
        if (r.status) progresso(r.status);

        if (r.success === false) {
            pararUI();
            Swal.fire('Indexação interrompida', r.message || '', 'warning');
            carregarStatus();
            return;
        }
        if (r.concluido) {
            idxRodando = false;
            $('#idxBarra').css('width', '100%').text('100%').removeClass('progress-bar-animated');
            $('#btnParar').hide();
            setTimeout(function () { $('#idxProgresso').hide(); carregarStatus(); }, 1500);
            Swal.fire({
                icon: r.aviso ? 'warning' : 'success',
                title: r.aviso ? 'Trechos gerados' : 'Base indexada',
                text: r.message || 'A consulta já está usando o acervo completo.'
            });
            return;
        }
        rodarLote();
    })
    .fail(function () {
        pararUI();
        Swal.fire('Erro de comunicação', 'A indexação parou. Clique em Retomar para continuar.', 'error');
    });
}

$('#btnIndexar').on('click', function () {
    $.post('indexar.php', { acao: 'status' }, null, 'json').done(function (s) {
        var min = Math.max(1, Math.ceil((s.pendentes_chunk / 40 * 2 + s.pendentes_embed / 100 * 3) / 60));
        Swal.fire({
            title: 'Indexar a base?',
            html: 'Serão processados <strong>' + s.pendentes_chunk.toLocaleString('pt-BR') + '</strong> documentos.<br>'
                + (s.tem_chave ? 'Custo estimado na API: <strong>US$ ' + s.custo_estimado.toFixed(2) + '</strong><br>' : '')
                + 'Tempo aproximado: <strong>' + min + ' min</strong>.<br><br>'
                + '<span style="font-size:.9em;color:#6c757d">Mantenha esta aba aberta. '
                + 'Você pode parar e retomar depois sem perder o que já foi feito.</span>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Indexar',
            cancelButtonText: 'Agora não',
            confirmButtonColor: '#0f6f77'
        }).then(function (res) {
            if (!res.isConfirmed) return;

            $.post('indexar.php', { acao: 'iniciar' }, null, 'json').done(function (r) {
                if (r.success === false) {
                    Swal.fire('Não foi possível iniciar', r.message, 'info');
                    return;
                }
                idxToken   = r.token;
                idxRodando = true;
                // O chunking cria trechos que tambem precisarao de embedding, entao
                // o alvo cresce durante a fase 1. Estimativa: ~20 trechos por documento.
                idxAlvo = r.status.pendentes_chunk * 21 + r.status.pendentes_embed
                        + r.status.pendentes_rel;

                $('#btnIndexar').hide();
                $('#btnParar').show();
                $('#idxProgresso').show();
                $('#idxBarra').addClass('progress-bar-animated').css('width', '0%').text('0%');
                rodarLote();
            });
        });
    });
});

$('#btnParar').on('click', function () {
    idxRodando = false;
    $.post('indexar.php', { acao: 'parar', token: idxToken }, null, 'json')
     .always(function () { pararUI(); carregarStatus(); });
});

window.addEventListener('beforeunload', function (e) {
    if (idxRodando) { e.preventDefault(); e.returnValue = ''; }
});

carregarStatus();

</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
