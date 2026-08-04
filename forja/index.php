<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';

$CSRF = forja_csrf();
$isAdmin = forja_is_admin();
$temEngine = forja_tem_pdf_engine();
$LIM = forja_limites_php();
function eh($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<?php include(__DIR__ . '/../os/guia/guia.php'); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas Forja · Ferramentas de PDF</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<style>
:root{ --fj-primary:#ea580c; --fj-primary2:#c2410c; --fj-bg:#f1f5f9; --fj-text:#0f172a; --fj-muted:#64748b; --fj-card:#fff; --fj-border:#e5e9f0; }
body.dark-mode{ --fj-bg:#0f1216; --fj-text:#e5e7eb; --fj-muted:#9aa4b2; --fj-card:#1c2126; --fj-border:rgba(255,255,255,.08); }
#main .container{ max-width:1000px; padding-bottom:120px; }
.fj-hero{ background:var(--fj-card); border:1px solid var(--fj-border); border-radius:20px; padding:22px 24px; box-shadow:0 12px 34px rgba(15,23,42,.06); margin:6px 0 18px; }
.fj-title-row{ display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.fj-ic{ width:56px; height:56px; border-radius:16px; flex:0 0 auto; background:linear-gradient(135deg,var(--fj-primary),var(--fj-primary2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 10px 24px rgba(234,88,12,.30); }
.fj-hero h1{ font-size:1.5rem; font-weight:800; margin:0; color:var(--fj-text); }
.fj-sub{ color:var(--fj-muted); font-size:.92rem; margin-top:2px; }
.fj-actions{ margin-left:auto; display:flex; gap:10px; align-items:center; }
.fj-pill{ border-radius:999px; font-weight:600; padding:9px 16px; border:0; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:.16s; text-decoration:none; font-size:.9rem; }
.fj-soft{ background:var(--fj-bg); color:var(--fj-text); border:1px solid var(--fj-border); } .fj-soft:hover{ background:var(--fj-border); color:var(--fj-text); }
.fj-pri{ background:linear-gradient(135deg,var(--fj-primary),var(--fj-primary2)); color:#fff; box-shadow:0 10px 24px rgba(234,88,12,.26); }
.fj-pri:hover{ transform:translateY(-2px); color:#fff; } .fj-pri:disabled{ opacity:.55; cursor:not-allowed; transform:none; }
.chip{ display:inline-flex; align-items:center; gap:7px; font-size:.8rem; font-weight:600; padding:6px 12px; border-radius:999px; background:var(--fj-bg); color:var(--fj-muted); }
.chip.on{ background:rgba(234,88,12,.14); color:var(--fj-primary); } .chip.warn{ background:#fef3c7; color:#92400e; }
/* tabs */
.tabs{ display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.tab{ border:1px solid var(--fj-border); background:var(--fj-card); color:var(--fj-text); border-radius:12px; padding:11px 16px; font-weight:700; font-size:.9rem; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:.14s; }
.tab:hover{ border-color:var(--fj-primary); }
.tab.active{ background:linear-gradient(135deg,var(--fj-primary),var(--fj-primary2)); color:#fff; border-color:transparent; }
.panel{ display:none; } .panel.active{ display:block; }
.fj-card{ background:var(--fj-card); border:1px solid var(--fj-border); border-radius:18px; padding:22px; box-shadow:0 8px 24px rgba(15,23,42,.05); }
.dz{ border:2px dashed var(--fj-border); border-radius:16px; background:var(--fj-bg); padding:34px 20px; text-align:center; cursor:pointer; transition:.16s; }
.dz:hover,.dz.drag{ border-color:var(--fj-primary); background:rgba(234,88,12,.05); }
.dz-ic{ width:60px; height:60px; margin:0 auto 12px; border-radius:50%; background:linear-gradient(135deg,var(--fj-primary),var(--fj-primary2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
.dz-t{ font-weight:700; color:var(--fj-text); } .dz-s{ color:var(--fj-muted); font-size:.86rem; margin-top:3px; }
.opts{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin:16px 0; }
.opts label{ font-size:.84rem; font-weight:700; color:var(--fj-muted); }
.inp{ border:1px solid var(--fj-border); border-radius:10px; padding:9px 12px; font-size:.9rem; color:var(--fj-text); background:var(--fj-card); outline:none; }
.inp:focus{ border-color:var(--fj-primary); }
.flist{ margin:14px 0; display:flex; flex-direction:column; gap:8px; }
.fitem{ display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px solid var(--fj-border); border-radius:12px; background:var(--fj-bg); }
.fitem .fic{ width:34px;height:34px;border-radius:8px;background:rgba(234,88,12,.12);color:var(--fj-primary);display:flex;align-items:center;justify-content:center;flex:0 0 auto; }
.fitem .fn{ font-weight:600; color:var(--fj-text); font-size:.88rem; word-break:break-word; flex:1; } .fitem .fm{ color:var(--fj-muted); font-size:.78rem; }
.fitem button{ width:30px;height:30px;border:1px solid var(--fj-border);background:var(--fj-card);border-radius:8px;cursor:pointer;color:var(--fj-muted); }
.fitem button:hover{ border-color:var(--fj-primary); color:var(--fj-primary); }
.result{ display:none; margin-top:16px; padding:16px; border-radius:14px; border:1px solid #86efac; background:#f0fdf4; }
body.dark-mode .result{ background:rgba(34,197,94,.1); }
.result .r-row{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.result .r-ic{ width:44px;height:44px;border-radius:11px;background:#22c55e;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto; }
.result b{ color:var(--fj-text); } .result small{ color:var(--fj-muted); display:block; }
.spin{ display:none; text-align:center; padding:24px; } .spin .s{ width:42px;height:42px;border:4px solid var(--fj-border);border-top-color:var(--fj-primary);border-radius:50%;animation:fjspin 1s linear infinite;margin:0 auto 10px; }
@keyframes fjspin{ to{ transform:rotate(360deg); } }
.spin .fjtxt{ font-weight:700; color:var(--fj-text); margin-bottom:10px; }
.fjbar{ height:10px; border-radius:999px; background:var(--fj-border); overflow:hidden; max-width:420px; margin:0 auto; }
.fjbar > i{ display:block; height:100%; width:0; border-radius:999px; background:var(--fj-primary); transition:width .3s ease; }
.spin .fjpct{ margin-top:8px; font-size:.86rem; font-weight:800; color:var(--fj-muted); letter-spacing:.02em; }
.hint{ font-size:.82rem; color:var(--fj-muted); background:var(--fj-bg); border:1px dashed var(--fj-border); border-radius:10px; padding:9px 12px; margin:-4px 0 6px; }
.hint b{ color:var(--fj-text); }
.badges{ display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
.bdg{ font-size:.74rem; font-weight:700; padding:3px 9px; border-radius:999px; background:var(--fj-bg); color:var(--fj-muted); border:1px solid var(--fj-border); }
.bdg.ok{ background:rgba(34,197,94,.14); color:#15803d; border-color:transparent; }
.prev{ margin-top:14px; border-top:1px dashed var(--fj-border); padding-top:12px; }
.prev-bar{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:10px; }
.prev-bar .fj-pill{ font-size:.8rem; padding:6px 12px; }
.prev-bar .fj-pill.sel{ background:linear-gradient(135deg,var(--fj-primary),var(--fj-primary2)); color:#fff; border-color:transparent; }
.pdfv{ display:grid; gap:10px; }
.pdfv.dois{ grid-template-columns:1fr 1fr; }
.pdfv .pane{ border:1px solid var(--fj-border); border-radius:12px; overflow:hidden; background:var(--fj-card); }
.pdfv .pane h6{ margin:0; padding:8px 12px; font-size:.76rem; font-weight:800; color:var(--fj-muted); background:var(--fj-bg); border-bottom:1px solid var(--fj-border); display:flex; align-items:center; justify-content:space-between; gap:10px; text-transform:uppercase; letter-spacing:.03em; }
.pdfv .pane h6 span{ font-weight:700; text-transform:none; letter-spacing:0; }
.pdfv iframe{ width:100%; height:560px; border:0; display:block; background:#fff; }
.pdfv img{ width:100%; display:block; }
.prev-nota{ font-size:.78rem; color:var(--fj-muted); margin-top:8px; }
@media (max-width:900px){ .pdfv.dois{ grid-template-columns:1fr; } .pdfv iframe{ height:420px; } }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container">

    <section class="fj-hero">
      <div class="fj-title-row">
        <div class="fj-ic"><i class="fa fa-wrench"></i></div>
        <div style="min-width:0">
          <h1>Atlas Forja</h1>
          <div class="fj-sub">Comprima PDFs, converta PDF em imagens, junte imagens ou PDFs — tudo em um lugar.</div>
        </div>
        <div class="fj-actions">
          <span class="chip <?php echo $temEngine?'on':'warn'; ?>"><i class="fa fa-cogs"></i> <?php echo $temEngine?'Ferramentas OK':'Configurar ferramentas'; ?></span>
          <?php if ($isAdmin): ?><a class="fj-pill fj-soft" href="configurar.php"><i class="fa fa-cog"></i> Configurar</a><?php endif; ?>
        </div>
      </div>
    </section>

    <?php if (!$temEngine): ?>
    <div class="fj-card" style="border-color:#f59e0b;background:#fffbeb;margin-bottom:18px">
      <div style="display:flex;align-items:center;gap:12px;color:#92400e">
        <i class="fa fa-exclamation-triangle" style="font-size:1.3rem"></i>
        <div>Para <b>comprimir</b> e <b>PDF → imagens</b> é preciso o Ghostscript (ou ImageMagick). <?php echo $isAdmin?'<a href="configurar.php" style="color:#b45309;font-weight:700">Configurar agora</a>.':'Peça ao administrador para configurar.'; ?> As opções <b>Imagens → PDF</b> e <b>Juntar PDFs</b> já funcionam.</div>
      </div>
    </div>
    <?php endif; ?>

    <div class="tabs">
      <button class="tab active" data-tab="comprimir"><i class="fa fa-compress"></i> Comprimir PDF</button>
      <button class="tab" data-tab="pdf2img"><i class="fa fa-file-image-o"></i> PDF → Imagens</button>
      <button class="tab" data-tab="img2pdf"><i class="fa fa-file-pdf-o"></i> Imagens → PDF</button>
      <button class="tab" data-tab="juntar"><i class="fa fa-object-group"></i> Juntar PDFs</button>
      <button class="tab" data-tab="multiplo"><i class="fa fa-clone"></i> União múltipla</button>
      <button class="tab" data-tab="dividir"><i class="fa fa-scissors"></i> Dividir PDF</button>
      <button class="tab" data-tab="word2pdf"><i class="fa fa-file-word-o"></i> Word → PDF</button>
      <button class="tab" data-tab="pdf2word"><i class="fa fa-file-word-o"></i> PDF → Word</button>
    </div>

    <!-- COMPRIMIR -->
    <div class="panel active" id="panel-comprimir">
      <div class="fj-card">
        <div class="dz" data-dz="comprimir"><div class="dz-ic"><i class="fa fa-compress"></i></div><div class="dz-t">Selecione um PDF para comprimir</div><div class="dz-s">arraste ou clique · máx. <?php echo eh(forja_human($LIM['efetivo'])); ?></div><input type="file" accept="application/pdf" hidden></div>
        <div class="flist" data-list="comprimir"></div>
        <div class="opts">
          <label>Nível:</label>
          <select class="inp" id="nivelComp">
            <option value="alta">Alta qualidade &mdash; 300 dpi</option>
            <option value="recomendado" selected>Recomendada &mdash; equilíbrio (200 dpi)</option>
            <option value="forte">Máxima compressão legível &mdash; 150 dpi</option>
            <option value="extrema">Compressão extrema &mdash; 120 dpi</option>
          </select>
          <label>Cores:</label>
          <select class="inp" id="cinzaComp">
            <option value="auto" selected>Automático</option>
            <option value="sim">Converter em tons de cinza</option>
            <option value="nao">Preservar as cores</option>
          </select>
          <button class="fj-pill fj-pri" id="btnComprimir" disabled style="margin-left:auto"><i class="fa fa-bolt"></i> Comprimir</button>
        </div>
        <div class="hint" id="hintComp"></div>
        <div class="spin" data-spin="comprimir"><div class="s"></div><div style="font-weight:700;color:var(--fj-text)">Comprimindo…</div></div>
        <div class="result" data-result="comprimir"></div>
      </div>
    </div>

    <!-- PDF -> IMAGENS -->
    <div class="panel" id="panel-pdf2img">
      <div class="fj-card">
        <div class="dz" data-dz="pdf2img"><div class="dz-ic"><i class="fa fa-file-image-o"></i></div><div class="dz-t">Selecione um PDF para converter em imagens</div><div class="dz-s">arraste ou clique · uma imagem por página</div><input type="file" accept="application/pdf" hidden></div>
        <div class="flist" data-list="pdf2img"></div>
        <div class="opts">
          <label>Formato:</label>
          <select class="inp" id="fmtImg"><option value="png" selected>PNG (sem perdas)</option><option value="jpg">JPG (menor)</option></select>
          <label>Resolução:</label>
          <select class="inp" id="dpiImg"><option value="100">100 DPI</option><option value="150" selected>150 DPI</option><option value="300">300 DPI (alta)</option></select>
          <button class="fj-pill fj-pri" id="btnPdf2Img" disabled style="margin-left:auto"><i class="fa fa-bolt"></i> Converter</button>
        </div>
        <div class="spin" data-spin="pdf2img"><div class="s"></div><div style="font-weight:700;color:var(--fj-text)">Convertendo páginas…</div></div>
        <div class="result" data-result="pdf2img"></div>
      </div>
    </div>

    <!-- IMAGENS -> PDF -->
    <div class="panel" id="panel-img2pdf">
      <div class="fj-card">
        <div class="dz" data-dz="img2pdf"><div class="dz-ic"><i class="fa fa-file-pdf-o"></i></div><div class="dz-t">Selecione as imagens (PNG/JPG)</div><div class="dz-s">arraste ou clique · pode escolher várias · ordene abaixo</div><input type="file" accept="image/png,image/jpeg" multiple hidden></div>
        <div class="flist" data-list="img2pdf"></div>
        <div class="opts">
          <label>Página:</label>
          <select class="inp" id="modoImg2Pdf"><option value="imagem" selected>Tamanho da imagem</option><option value="a4">Ajustar em A4</option></select>
          <button class="fj-pill fj-pri" id="btnImg2Pdf" disabled style="margin-left:auto"><i class="fa fa-bolt"></i> Gerar PDF</button>
        </div>
        <div class="spin" data-spin="img2pdf"><div class="s"></div><div style="font-weight:700;color:var(--fj-text)">Gerando PDF…</div></div>
        <div class="result" data-result="img2pdf"></div>
      </div>
    </div>

    <!-- JUNTAR -->
    <div class="panel" id="panel-juntar">
      <div class="fj-card">
        <div class="dz" data-dz="juntar"><div class="dz-ic"><i class="fa fa-object-group"></i></div><div class="dz-t">Selecione os PDFs para juntar</div><div class="dz-s">arraste ou clique · pode escolher vários · ordene abaixo</div><input type="file" accept="application/pdf" multiple hidden></div>
        <div class="flist" data-list="juntar"></div>
        <div class="opts">
          <button class="fj-pill fj-pri" id="btnJuntar" disabled style="margin-left:auto"><i class="fa fa-bolt"></i> Juntar</button>
        </div>
        <div class="spin" data-spin="juntar"><div class="s"></div><div style="font-weight:700;color:var(--fj-text)">Juntando…</div></div>
        <div class="result" data-result="juntar"></div>
      </div>
    </div>

    <!-- UNIÃO MÚLTIPLA (LOTE) -->
    <div class="panel" id="panel-multiplo">
      <div class="fj-card">
        <p style="color:var(--fj-muted);font-size:.88rem;margin:0 0 14px">Combine um <b>Lado A</b> (comum a todos, ex.: a portaria) com cada arquivo do <b>Lado B</b>. Gera um PDF por item do Lado B — cada um = Lado A + aquele arquivo — tudo num ZIP.</p>
        <label style="font-weight:700;color:var(--fj-text);font-size:.9rem">Lado A — comum a todos</label>
        <div class="dz" data-dz="ladoA" style="margin:8px 0 4px"><div class="dz-ic"><i class="fa fa-thumb-tack"></i></div><div class="dz-t">PDF(s) do Lado A</div><div class="dz-s">ex.: a portaria · pode ter mais de um</div><input type="file" accept="application/pdf" multiple hidden></div>
        <div class="flist" data-list="ladoA"></div>
        <label style="font-weight:700;color:var(--fj-text);font-size:.9rem;margin-top:6px;display:block">Lado B — um resultado por arquivo</label>
        <div class="dz" data-dz="ladoB" style="margin:8px 0 4px"><div class="dz-ic"><i class="fa fa-files-o"></i></div><div class="dz-t">PDFs do Lado B</div><div class="dz-s">cada um vira: Lado A + este arquivo</div><input type="file" accept="application/pdf" multiple hidden></div>
        <div class="flist" data-list="ladoB"></div>
        <div class="opts">
          <label>Ordem:</label>
          <select class="inp" id="posMultiplo"><option value="antes" selected>Lado A antes</option><option value="depois">Lado A depois</option></select>
          <button class="fj-pill fj-pri" id="btnMultiplo" disabled style="margin-left:auto"><i class="fa fa-bolt"></i> Gerar em lote</button>
        </div>
        <div class="spin" data-spin="multiplo"><div class="s"></div><div style="font-weight:700;color:var(--fj-text)">Gerando os PDFs…</div></div>
        <div class="result" data-result="multiplo"></div>
      </div>
    </div>

    <!-- DIVIDIR -->
    <div class="panel" id="panel-dividir">
      <div class="fj-card">
        <div class="dz" data-dz="dividir"><div class="dz-ic"><i class="fa fa-scissors"></i></div><div class="dz-t">Selecione um PDF para dividir</div><div class="dz-s">arraste ou clique · gera um ZIP com as partes</div><input type="file" accept="application/pdf" hidden></div>
        <div class="flist" data-list="dividir"></div>
        <div class="opts">
          <label>Dividir por:</label>
          <select class="inp" id="modoDividir"><option value="partes" selected>Número de partes</option><option value="paginas">Páginas por parte</option></select>
          <label id="labelValor">Quantidade de partes:</label>
          <input type="number" class="inp" id="valDividir" min="2" max="500" value="2" style="width:110px">
          <button class="fj-pill fj-pri" id="btnDividir" disabled style="margin-left:auto"><i class="fa fa-bolt"></i> Dividir</button>
        </div>
        <div class="spin" data-spin="dividir"><div class="s"></div><div style="font-weight:700;color:var(--fj-text)">Dividindo…</div></div>
        <div class="result" data-result="dividir"></div>
      </div>
    </div>

    <!-- WORD -> PDF -->
    <div class="panel" id="panel-word2pdf">
      <div class="fj-card">
        <div class="dz" data-dz="word2pdf"><div class="dz-ic"><i class="fa fa-file-word-o"></i></div><div class="dz-t">Selecione um documento Word</div><div class="dz-s">.docx, .doc, .odt, .rtf ou .txt · vira PDF</div><input type="file" accept=".docx,.doc,.odt,.rtf,.txt" hidden></div>
        <div class="flist" data-list="word2pdf"></div>
        <div class="opts"><button class="fj-pill fj-pri" id="btnWord2Pdf" disabled style="margin-left:auto"><i class="fa fa-bolt"></i> Converter para PDF</button></div>
        <div class="spin" data-spin="word2pdf"><div class="s"></div><div style="font-weight:700;color:var(--fj-text)">Convertendo…</div></div>
        <div class="result" data-result="word2pdf"></div>
      </div>
    </div>

    <!-- PDF -> WORD -->
    <div class="panel" id="panel-pdf2word">
      <div class="fj-card">
        <div class="dz" data-dz="pdf2word"><div class="dz-ic"><i class="fa fa-file-word-o"></i></div><div class="dz-t">Selecione um PDF</div><div class="dz-s">vira Word (.docx) editável — <b>Fiel</b> mantém imagens/layout; <b>Texto fluido</b> dá texto limpo sem imagens</div><input type="file" accept="application/pdf" hidden></div>
        <div class="flist" data-list="pdf2word"></div>
        <div class="opts">
          <label>Modo:</label>
          <select class="inp" id="modoPdf2Word"><option value="layout" selected>Fiel — mantém imagens e layout</option><option value="formatado">Texto fluido — editável, sem imagens</option><option value="simples">Texto simples</option></select>
          <button class="fj-pill fj-pri" id="btnPdf2Word" disabled style="margin-left:auto"><i class="fa fa-bolt"></i> Converter para Word</button>
        </div>
        <div class="spin" data-spin="pdf2word"><div class="s"></div><div style="font-weight:700;color:var(--fj-text)">Convertendo…</div></div>
        <div class="result" data-result="pdf2word"></div>
      </div>
    </div>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>
(function(){
  "use strict";
  var CSRF=<?php echo json_encode($CSRF); ?>;
  var LIMITE=<?php echo (int)$LIM['efetivo']; ?>;
  var LIMITE_MOTIVO=<?php echo json_encode($LIM['efetivo'] < $LIM['config']
      ? 'O php.ini está limitando o envio (upload_max_filesize=' . forja_human($LIM['upload'])
        . ', post_max_size=' . forja_human($LIM['post']) . '). Ajuste os dois e reinicie o Apache.'
      : ''); ?>;
  var arquivos={comprimir:[],pdf2img:[],img2pdf:[],juntar:[],dividir:[],word2pdf:[],pdf2word:[],ladoA:[],ladoB:[]};
  var multi={comprimir:false,pdf2img:false,img2pdf:true,juntar:true,dividir:false,word2pdf:false,pdf2word:false,ladoA:true,ladoB:true};
  function $(s,c){ return (c||document).querySelector(s); }
  function humano(n){ n=+n; if(n<1024)return n+' B'; if(n<1048576)return (n/1024).toFixed(1)+' KB'; return (n/1048576).toFixed(1)+' MB'; }
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

  // Tabs
  document.querySelectorAll('.tab').forEach(function(t){
    t.addEventListener('click',function(){
      document.querySelectorAll('.tab').forEach(function(x){x.classList.remove('active');});
      document.querySelectorAll('.panel').forEach(function(x){x.classList.remove('active');});
      t.classList.add('active'); document.getElementById('panel-'+t.dataset.tab).classList.add('active');
    });
  });

  // Dropzones
  document.querySelectorAll('.dz').forEach(function(dz){
    var key=dz.dataset.dz, inp=dz.querySelector('input');
    dz.addEventListener('click',function(){ inp.click(); });
    ['dragover','dragenter'].forEach(function(e){ dz.addEventListener(e,function(ev){ev.preventDefault();dz.classList.add('drag');}); });
    ['dragleave','drop'].forEach(function(e){ dz.addEventListener(e,function(ev){ev.preventDefault();dz.classList.remove('drag');}); });
    dz.addEventListener('drop',function(ev){ addFiles(key, ev.dataTransfer.files); });
    inp.addEventListener('change',function(){ addFiles(key, inp.files); inp.value=''; });
  });

  function addFiles(key, fileList){
    var arr=Array.prototype.slice.call(fileList), recusados=[];
    if(LIMITE>0){
      arr=arr.filter(function(f){ if(f.size>LIMITE){ recusados.push(f.name+' ('+humano(f.size)+')'); return false; } return true; });
    }
    if(recusados.length){
      Swal.fire({icon:'warning',title:'Arquivo acima do limite',
        html:'Limite atual: <b>'+humano(LIMITE)+'</b><br><small>'+recusados.map(esc).join('<br>')+'</small>'
             +(LIMITE_MOTIVO?'<div class="hint" style="margin-top:10px;text-align:left">'+esc(LIMITE_MOTIVO)+'</div>':'')});
    }
    if(!arr.length){ return; }
    if(!multi[key]){ arquivos[key]=arr.slice(0,1); }
    else { arr.forEach(function(f){ arquivos[key].push(f); }); }
    renderList(key); atualizarBotoes();
  }
  function renderList(key){
    var box=document.querySelector('[data-list="'+key+'"]'); box.innerHTML='';
    arquivos[key].forEach(function(f,i){
      var ehImg=/^image\//.test(f.type);
      var it=document.createElement('div'); it.className='fitem';
      it.innerHTML='<span class="fic"><i class="fa '+(ehImg?'fa-file-image-o':'fa-file-pdf-o')+'"></i></span>'
        +'<div class="fn">'+esc(f.name)+'<span class="fm"> · '+humano(f.size)+'</span></div>'
        +(multi[key]?'<button data-a="up" title="Subir"><i class="fa fa-arrow-up"></i></button>'
                    +'<button data-a="down" title="Descer"><i class="fa fa-arrow-down"></i></button>':'')
        +'<button data-a="rm" title="Remover"><i class="fa fa-times"></i></button>';
      it.querySelectorAll('button').forEach(function(b){
        b.addEventListener('click',function(){
          var a=b.dataset.a;
          if(a==='rm') arquivos[key].splice(i,1);
          else if(a==='up'&&i>0){ var t=arquivos[key][i-1]; arquivos[key][i-1]=arquivos[key][i]; arquivos[key][i]=t; }
          else if(a==='down'&&i<arquivos[key].length-1){ var t2=arquivos[key][i+1]; arquivos[key][i+1]=arquivos[key][i]; arquivos[key][i]=t2; }
          renderList(key); atualizarBotoes();
        });
      });
      box.appendChild(it);
    });
  }
  function atualizarBotoes(){
    $('#btnComprimir').disabled = arquivos.comprimir.length===0;
    $('#btnPdf2Img').disabled  = arquivos.pdf2img.length===0;
    $('#btnImg2Pdf').disabled  = arquivos.img2pdf.length===0;
    $('#btnJuntar').disabled   = arquivos.juntar.length<2;
    $('#btnDividir').disabled  = arquivos.dividir.length===0;
    $('#btnWord2Pdf').disabled = arquivos.word2pdf.length===0;
    $('#btnPdf2Word').disabled = arquivos.pdf2word.length===0;
    $('#btnMultiplo').disabled = arquivos.ladoA.length===0 || arquivos.ladoB.length===0;
  }
  function showResult(key,html){ var r=document.querySelector('[data-result="'+key+'"]'); r.innerHTML=html; r.style.display='block'; }

  /* ============ Andamento: 0–30% envio (XHR) · 30–100% servidor (polling) ============ */
  (function montarBarras(){
    Array.prototype.forEach.call(document.querySelectorAll('.spin[data-spin]'), function(w){
      var lbl=w.querySelector('div:last-child'), base=lbl?lbl.textContent.trim():'Processando…';
      if(lbl) lbl.style.display='none';
      w.insertAdjacentHTML('beforeend','<div class="fjtxt"></div><div class="fjbar"><i></i></div><div class="fjpct">0%</div>');
      w.querySelector('.fjtxt').textContent=base;
      w.dataset.rotulo=base;
    });
  })();

  function novoJob(){ return 'j'+Date.now().toString(36)+Math.random().toString(36).slice(2,10); }
  function caixa(key){ return document.querySelector('[data-spin="'+key+'"]'); }
  function setProg(key,pct,txt){
    var w=caixa(key); if(!w) return;
    var i=w.querySelector('.fjbar > i'), p=w.querySelector('.fjpct'), l=w.querySelector('.fjtxt');
    if(pct!=null && i){ pct=Math.max(0,Math.min(100,pct)); i.style.width=pct+'%'; if(p) p.textContent=Math.round(pct)+'%'; }
    if(txt && l) l.textContent=txt;
  }
  function spin(key,on){
    var w=caixa(key); if(!w) return;
    w.style.display=on?'block':'none';
    if(on) setProg(key,0,w.dataset.rotulo||'Processando…');
  }
  function pollProgresso(key,job){
    var vivo=true;
    var t=setInterval(function(){
      if(!vivo) return;
      fetch('progresso.php?job='+encodeURIComponent(job)+'&_='+Date.now(),{credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(j){ if(vivo && j && j.status==='success') setProg(key, 30+(j.pct*0.70), j.texto||'Processando…'); })
        .catch(function(){});
    },700);
    return function(){ vivo=false; clearInterval(t); };
  }
  function enviar(url, fd, key, job){
    return new Promise(function(resolve,reject){
      var xhr=new XMLHttpRequest(), parar=null;
      xhr.open('POST',url,true); xhr.withCredentials=true;
      xhr.upload.onprogress=function(ev){
        if(!ev.lengthComputable) return;
        var f=ev.loaded/ev.total;
        setProg(key, f*30, 'Enviando arquivos… '+humano(ev.loaded)+' de '+humano(ev.total));
      };
      xhr.upload.onload=function(){
        setProg(key,30,'Processando no servidor…');
        if(!parar) parar=pollProgresso(key,job);
      };
      xhr.onload=function(){
        if(parar) parar();
        var t=xhr.responseText||'', j=null;
        try{ j=JSON.parse(t); }catch(e){
          reject(new Error(xhr.status!==200
            ? ('O servidor respondeu '+xhr.status+'. '+t.slice(0,140))
            : ('Resposta inválida: '+t.slice(0,160))));
          return;
        }
        resolve(j);
      };
      xhr.onerror=function(){ if(parar) parar(); reject(new Error('Falha de conexão com o servidor.')); };
      xhr.onabort=function(){ if(parar) parar(); reject(new Error('Envio cancelado.')); };
      xhr.send(fd);
    });
  }

  async function processar(key, url, extra, btn){
    var job=novoJob();
    var fd=new FormData(); fd.append('csrf',CSRF); fd.append('job',job);
    arquivos[key].forEach(function(f){ fd.append('arquivo[]', f); });
    Object.keys(extra||{}).forEach(function(k){ fd.append(k, extra[k]); });
    btn.disabled=true; spin(key,true); document.querySelector('[data-result="'+key+'"]').style.display='none';
    try{
      var j=await enviar(url,fd,key,job);
      if(j.status!=='success') throw new Error(j.message||'Falha no processamento.');
      setProg(key,100,'Concluído');
      return j;
    } finally { spin(key,false); btn.disabled=false; atualizarBotoes(); }
  }
  function baixarHtml(token, rotulo, extra){
    return '<div class="r-row"><span class="r-ic"><i class="fa fa-check"></i></span>'
      +'<div style="flex:1"><b>'+esc(rotulo)+'</b>'+(extra?'<small>'+esc(extra)+'</small>':'')+'</div>'
      +'<a class="fj-pill fj-pri" href="baixar.php?token='+encodeURIComponent(token)+'"><i class="fa fa-download"></i> Baixar</a></div>';
  }

  var DICAS={
    alta:'Reamostra imagens para <b>300 dpi</b> com JPEG de alta qualidade. Indicado para documentos que ainda serão impressos ou reconhecidos por OCR.',
    recomendado:'Reamostra para <b>200 dpi</b>. Mantém o texto nítido na tela e na impressão comum — é a melhor escolha para digitalizações de cartório.',
    forte:'Reamostra para <b>150 dpi</b> com reamostragem bicúbica. Costuma reduzir de <b>85% a 90%</b> e o texto continua perfeitamente legível.',
    extrema:'Reamostra para <b>120 dpi</b>. Reduz cerca de <b>90% a 95%</b> — use para anexar em e-mail/protocolo. Ainda legível, mas não recomendado para reimpressão.'
  };
  function atualizarDica(){
    var n=$('#nivelComp').value, c=$('#cinzaComp').value, t=DICAS[n]||'';
    if(c==='sim') t+=' <b>Tons de cinza</b> ativado: reduz mais 30&ndash;60% em digitalizações coloridas.';
    else if(c==='auto') t+=' No modo <b>automático</b>, páginas sem cor real são convertidas para cinza.';
    $('#hintComp').innerHTML=t;
  }
  $('#nivelComp').addEventListener('change',atualizarDica);
  $('#cinzaComp').addEventListener('change',atualizarDica);
  atualizarDica();

  function paneHtml(id,titulo,tam){
    return '<div class="pane"><h6>'+esc(titulo)+'<span>'+(tam?humano(tam):'')+'</span></h6>'
         + '<iframe id="'+id+'" title="'+esc(titulo)+'" referrerpolicy="no-referrer"></iframe></div>';
  }
  function previaHtml(j){
    if(!j.token) return '';
    var h='<div class="prev"><div class="prev-bar">'
      + '<b style="font-size:.84rem;color:var(--fj-text)">Prévia da qualidade:</b>'
      + '<button class="fj-pill fj-soft sel" data-pv="novo"><i class="fa fa-compress"></i> Comprimido</button>';
    if(j.token_orig) h+='<button class="fj-pill fj-soft" data-pv="orig"><i class="fa fa-file-pdf-o"></i> Original</button>'
                      +'<button class="fj-pill fj-soft" data-pv="dois"><i class="fa fa-columns"></i> Lado a lado</button>';
    if((j.paginas||0)>1) h+='<span style="font-size:.8rem;color:var(--fj-muted)">pág. <input class="inp" id="pvPag" type="number" min="1" max="'+j.paginas+'" value="1" style="width:74px;padding:5px 8px"></span>';
    h+='<a class="fj-pill fj-soft" id="pvAba" target="_blank" rel="noopener" href="#"><i class="fa fa-external-link"></i> Abrir em nova aba</a>'
      +'</div><div class="pdfv" id="pvBox"></div>'
      +'<div class="prev-nota">O documento é exibido pelo visualizador de PDF do próprio navegador &mdash; use o zoom dele para conferir o texto no tamanho real. '
      +'<a href="#" id="pvImg" style="color:var(--fj-primary);font-weight:700">Não carregou? Ver como imagem</a></div></div>';
    return h;
  }

  function montarPrevia(j){
    var box=document.getElementById('pvBox'); if(!box) return;
    var modo='novo', comoImagem=false;

    function pagina(){ var e=document.getElementById('pvPag'); return Math.max(1, parseInt(e&&e.value||'1',10)||1); }
    function urlPdf(tk){ return 'ver.php?token='+encodeURIComponent(tk)+'#page='+pagina()+'&view=FitH&zoom=page-width'; }
    function urlImg(tk){ return 'previa.php?token='+encodeURIComponent(tk)+'&pg='+pagina()+'&dpi=150&t='+Date.now(); }

    function desenhar(){
      var dois = (modo==='dois' && j.token_orig);
      box.className = 'pdfv'+(dois?' dois':'');
      var rotNovo='Comprimido'+(j.dpi?(' · '+j.dpi+' dpi'):''), rotOrig='Original';
      if(comoImagem){
        var im=function(tk,rot,tam){
          return '<div class="pane"><h6>'+esc(rot)+'<span>'+(tam?humano(tam):'')+'</span></h6>'
               + '<img src="'+urlImg(tk)+'" alt="'+esc(rot)+'"></div>';
        };
        box.innerHTML = dois ? (im(j.token_orig,rotOrig,j.orig)+im(j.token,rotNovo,j.novo))
                     : (modo==='orig' ? im(j.token_orig,rotOrig,j.orig) : im(j.token,rotNovo,j.novo));
        return;
      }
      if(dois){
        box.innerHTML = paneHtml('pvA',rotOrig,j.orig)+paneHtml('pvB',rotNovo,j.novo);
        document.getElementById('pvA').src=urlPdf(j.token_orig);
        document.getElementById('pvB').src=urlPdf(j.token);
      } else if(modo==='orig'){
        box.innerHTML = paneHtml('pvA',rotOrig,j.orig);
        document.getElementById('pvA').src=urlPdf(j.token_orig);
      } else {
        box.innerHTML = paneHtml('pvB',rotNovo,j.novo);
        document.getElementById('pvB').src=urlPdf(j.token);
      }
      var aba=document.getElementById('pvAba');
      if(aba) aba.href = urlPdf(modo==='orig'&&j.token_orig ? j.token_orig : j.token);
    }

    document.querySelectorAll('[data-pv]').forEach(function(b){
      b.addEventListener('click',function(){
        document.querySelectorAll('[data-pv]').forEach(function(x){x.classList.remove('sel');});
        b.classList.add('sel'); modo=b.dataset.pv; desenhar();
      });
    });
    var inp=document.getElementById('pvPag'); if(inp) inp.addEventListener('change',desenhar);
    var lnk=document.getElementById('pvImg');
    if(lnk) lnk.addEventListener('click',function(ev){
      ev.preventDefault(); comoImagem=!comoImagem;
      lnk.textContent = comoImagem ? 'Voltar para o PDF' : 'Não carregou? Ver como imagem';
      desenhar();
    });
    desenhar();
  }

  $('#btnComprimir').addEventListener('click',async function(){
    try{
      var j=await processar('comprimir','comprimir.php',{nivel:$('#nivelComp').value,cinza:$('#cinzaComp').value},this);
      var pct=(typeof j.reducao==='number')?j.reducao:(j.orig>0?Math.round((1-j.novo/j.orig)*100):0);
      var resumo=humano(j.orig)+' → '+humano(j.novo)+(pct>0?(' · '+pct+'% menor'):' · nada a ganhar sem perder qualidade');
      var badges='<div class="badges">'
        + (pct>0?'<span class="bdg ok">'+pct+'% menor</span>':'')
        + (j.rotulo?'<span class="bdg">'+esc(j.rotulo)+'</span>':'')
        + (j.dpi?'<span class="bdg">'+j.dpi+' dpi</span>':'')
        + (j.cinza?'<span class="bdg">tons de cinza'+(j.cinza_auto?' (auto)':'')+'</span>':'')
        + (j.paginas?'<span class="bdg">'+j.paginas+' pág.</span>':'')
        + '</div>';
      var extra='';
      if(j.ja_otimizado) extra='<div class="hint" style="margin:10px 0 0">Este PDF já está otimizado para o nível escolhido — devolvemos o arquivo original em vez de um maior. Para reduzir mais, escolha <b>Máxima compressão legível</b> ou <b>Compressão extrema</b>.</div>';
      else if((j.avisos||[]).length) extra='<div class="hint" style="margin:10px 0 0">'+esc(j.avisos.join(' '))+'</div>';
      showResult('comprimir', baixarHtml(j.token,'PDF comprimido',resumo)+badges+extra+previaHtml(j));
      montarPrevia(j);
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });
  $('#btnPdf2Img').addEventListener('click',async function(){
    try{ var j=await processar('pdf2img','pdf_para_imagens.php',{formato:$('#fmtImg').value,dpi:$('#dpiImg').value},this);
      showResult('pdf2img', baixarHtml(j.token,'Imagens (ZIP)', j.paginas+' página(s) · '+$('#fmtImg').value.toUpperCase()));
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });
  $('#btnImg2Pdf').addEventListener('click',async function(){
    try{ var j=await processar('img2pdf','imagens_para_pdf.php',{modo:$('#modoImg2Pdf').value},this);
      showResult('img2pdf', baixarHtml(j.token,'PDF gerado', j.paginas+' página(s) · '+humano(j.tamanho)));
      if(j.aviso) Swal.fire('Concluído com avisos', esc(j.aviso), 'warning');
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });
  $('#btnJuntar').addEventListener('click',async function(){
    try{ var j=await processar('juntar','juntar_pdf.php',{},this);
      showResult('juntar', baixarHtml(j.token,'PDF único', j.paginas+' página(s) · '+humano(j.tamanho)));
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });
  $('#btnMultiplo').addEventListener('click',async function(){
    var btn=this, job=novoJob(), fd=new FormData();
    fd.append('csrf',CSRF); fd.append('job',job); fd.append('posicao',document.getElementById('posMultiplo').value);
    arquivos.ladoA.forEach(function(f){ fd.append('ladoA[]', f); });
    arquivos.ladoB.forEach(function(f){ fd.append('ladoB[]', f); });
    btn.disabled=true; spin('multiplo',true); document.querySelector('[data-result="multiplo"]').style.display='none';
    try{
      var j=await enviar('juntar_multiplo.php',fd,'multiplo',job);
      if(j.status!=='success') throw new Error(j.message||'Falha ao gerar.');
      setProg('multiplo',100,'Concluído');
      showResult('multiplo', baixarHtml(j.token,'Resultados (ZIP)', j.total+' PDF(s) gerado(s)'));
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
    finally{ spin('multiplo',false); btn.disabled=false; atualizarBotoes(); }
  });
  var modoDiv=document.getElementById('modoDividir');
  if(modoDiv) modoDiv.addEventListener('change',function(){
    var lbl=document.getElementById('labelValor'), inp=document.getElementById('valDividir');
    if(this.value==='paginas'){ lbl.textContent='Páginas por parte:'; inp.min=1; if(+inp.value<1)inp.value=1; }
    else { lbl.textContent='Quantidade de partes:'; inp.min=2; if(+inp.value<2)inp.value=2; }
  });
  $('#btnDividir').addEventListener('click',async function(){
    try{ var j=await processar('dividir','dividir_pdf.php',{modo:$('#modoDividir').value,valor:$('#valDividir').value},this);
      showResult('dividir', baixarHtml(j.token,'Partes (ZIP)', j.partes+' parte(s) · '+j.total_paginas+' páginas no total'));
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });
  $('#btnWord2Pdf').addEventListener('click',async function(){
    try{ var j=await processar('word2pdf','word_para_pdf.php',{},this);
      showResult('word2pdf', baixarHtml(j.token,'PDF gerado', humano(j.tamanho)));
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });
  $('#btnPdf2Word').addEventListener('click',async function(){
    try{ var j=await processar('pdf2word','pdf_para_word.php',{modo:document.getElementById('modoPdf2Word').value},this);
      showResult('pdf2word', baixarHtml(j.token,'Word gerado (.docx)', humano(j.tamanho)));
    }catch(e){ Swal.fire('Erro',e.message,'error'); }
  });
})();
</script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
