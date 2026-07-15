<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_iris.php';
iris_ensure_schema();

$CSRF = iris_csrf();
$modelos = iris_modelos(true);
$padrao = iris_modelo_padrao();
$temChave = iris_tem_chave();
$isAdmin = iris_is_admin();
function eh($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas Iris · Extração de Texto</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<style>
:root{ --ir-primary:#c026d3; --ir-primary2:#7e22ce; --ir-bg:#f1f5f9; --ir-text:#0f172a; --ir-muted:#64748b; --ir-card:#fff; --ir-border:#e5e9f0; }
body.dark-mode{ --ir-bg:#0f1216; --ir-text:#e5e7eb; --ir-muted:#9aa4b2; --ir-card:#1c2126; --ir-border:rgba(255,255,255,.08); }
#main .container{ max-width:1100px; padding-bottom:120px; }
.ir-hero{ background:var(--ir-card); border:1px solid var(--ir-border); border-radius:20px; padding:22px 24px; box-shadow:0 12px 34px rgba(15,23,42,.06); margin:6px 0 18px; }
.ir-title-row{ display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.ir-ic{ width:56px; height:56px; border-radius:16px; flex:0 0 auto; background:linear-gradient(135deg,var(--ir-primary),var(--ir-primary2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 10px 24px rgba(192,38,211,.30); }
.ir-hero h1{ font-size:1.5rem; font-weight:800; margin:0; color:var(--ir-text); }
.ir-sub{ color:var(--ir-muted); font-size:.92rem; margin-top:2px; }
.ir-actions{ margin-left:auto; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.ir-pill{ border-radius:999px; font-weight:600; padding:9px 16px; border:0; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:.16s; text-decoration:none; font-size:.9rem; }
.ir-pri{ background:linear-gradient(135deg,var(--ir-primary),var(--ir-primary2)); color:#fff; box-shadow:0 10px 24px rgba(192,38,211,.26); }
.ir-pri:hover{ transform:translateY(-2px); color:#fff; } .ir-pri:disabled{ opacity:.55; cursor:not-allowed; transform:none; }
.ir-soft{ background:var(--ir-bg); color:var(--ir-text); border:1px solid var(--ir-border); }
.ir-soft:hover{ background:var(--ir-border); color:var(--ir-text); }
.ir-card{ background:var(--ir-card); border:1px solid var(--ir-border); border-radius:18px; padding:20px; box-shadow:0 8px 24px rgba(15,23,42,.05); margin-bottom:18px; }
.chip{ display:inline-flex; align-items:center; gap:7px; font-size:.8rem; font-weight:600; padding:6px 12px; border-radius:999px; background:var(--ir-bg); color:var(--ir-muted); }
.chip.on{ background:rgba(192,38,211,.14); color:var(--ir-primary); } .chip.warn{ background:#fef3c7; color:#92400e; }
.dz{ border:2px dashed var(--ir-border); border-radius:16px; background:var(--ir-bg); padding:40px 20px; text-align:center; cursor:pointer; transition:.16s; }
.dz:hover,.dz.drag{ border-color:var(--ir-primary); background:rgba(192,38,211,.05); }
.dz-ic{ width:64px; height:64px; margin:0 auto 12px; border-radius:50%; background:linear-gradient(135deg,var(--ir-primary),var(--ir-primary2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.7rem; }
.dz-t{ font-weight:700; color:var(--ir-text); } .dz-s{ color:var(--ir-muted); font-size:.88rem; margin-top:3px; }
.arqbar{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.arq-ic{ width:44px; height:44px; border-radius:11px; background:rgba(192,38,211,.12); color:var(--ir-primary); display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex:0 0 auto; }
.arq-nome{ font-weight:700; color:var(--ir-text); word-break:break-word; } .arq-meta{ color:var(--ir-muted); font-size:.82rem; }
.selrow{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-left:auto; }
.inp{ border:1px solid var(--ir-border); border-radius:12px; padding:10px 12px; font-size:.9rem; color:var(--ir-text); background:var(--ir-card); outline:none; }
.inp:focus{ border-color:var(--ir-primary); box-shadow:0 0 0 3px rgba(192,38,211,.14); }
.thumb{ max-width:120px; max-height:120px; border-radius:10px; border:1px solid var(--ir-border); }
/* editor */
#editorCard{ display:none; }
.ed-toolbar{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
.ed-toolbar .sep{ flex:1; }
.ed-info{ font-size:.82rem; color:var(--ir-muted); }
.editor{ width:100%; min-height:52vh; border:1px solid var(--ir-border); border-radius:14px; padding:18px 20px; font-size:.98rem; line-height:1.6; color:var(--ir-text); background:var(--ir-card); outline:none; resize:vertical; font-family:'Georgia','Times New Roman',serif; white-space:pre-wrap; }
.editor:focus{ border-color:var(--ir-primary); box-shadow:0 0 0 3px rgba(192,38,211,.12); }
.editor.mono{ font-family:'Consolas','Courier New',monospace; font-size:.9rem; }
.tbtn{ border:1px solid var(--ir-border); background:var(--ir-card); border-radius:10px; cursor:pointer; color:var(--ir-text); display:inline-flex; align-items:center; gap:7px; padding:8px 12px; font-size:.84rem; font-weight:600; transition:.14s; }
.tbtn:hover{ border-color:var(--ir-primary); color:var(--ir-primary); }
.tbtn.ativo{ background:var(--ir-primary); color:#fff; border-color:transparent; }
.tbtn.ativo:hover{ color:#fff; }
.progress-wrap{ display:none; text-align:center; padding:34px 10px; }
.spinner{ width:46px; height:46px; border:4px solid var(--ir-border); border-top-color:var(--ir-primary); border-radius:50%; animation:irspin 1s linear infinite; margin:0 auto 14px; }
@keyframes irspin{ to{ transform:rotate(360deg); } }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container">

    <section class="ir-hero">
      <div class="ir-title-row">
        <div class="ir-ic"><i class="fa fa-eye"></i></div>
        <div style="min-width:0">
          <h1>Atlas Iris</h1>
          <div class="ir-sub">Extraia o texto de imagens e PDFs na íntegra — fiel ao original — e revise em um editor.</div>
        </div>
        <div class="ir-actions">
          <span class="chip <?php echo $temChave?'on':'warn'; ?>"><i class="fa fa-key"></i> <?php echo $temChave?'Pronto para extrair':($isAdmin?'Configure a API':'API não configurada'); ?></span>
          <?php if ($isAdmin): ?><a class="ir-pill ir-soft" href="configurar.php"><i class="fa fa-cog"></i> Configurar</a><?php endif; ?>
        </div>
      </div>
    </section>

    <?php if (!$temChave): ?>
    <div class="ir-card" style="border-color:#f59e0b;background:#fffbeb">
      <div style="display:flex;align-items:center;gap:12px;color:#92400e">
        <i class="fa fa-exclamation-triangle" style="font-size:1.4rem"></i>
        <div><?php if ($isAdmin): ?>É preciso cadastrar a <b>chave da API do Gemini</b> antes de extrair. <a href="configurar.php" style="color:#b45309;font-weight:700">Ir para Configurar</a>.<?php else: ?>A extração ainda não está disponível: peça ao <b>administrador</b> para configurar a chave da API.<?php endif; ?></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Upload -->
    <div class="ir-card" id="uploadCard">
      <div class="dz" id="dz">
        <div class="dz-ic"><i class="fa fa-cloud-upload"></i></div>
        <div class="dz-t">Arraste uma imagem ou PDF aqui, ou clique para escolher</div>
        <div class="dz-s">PDF, PNG, JPG ou WEBP · máx. 20 MB</div>
        <input type="file" id="fileInput" accept="application/pdf,image/png,image/jpeg,image/webp" hidden>
      </div>
    </div>

    <!-- Arquivo pronto -->
    <div class="ir-card" id="fileCard" style="display:none">
      <div class="arqbar">
        <div class="arq-ic" id="arqIc"><i class="fa fa-file-o"></i></div>
        <div style="min-width:0">
          <div class="arq-nome" id="arqNome"></div>
          <div class="arq-meta" id="arqMeta"></div>
        </div>
        <img id="thumb" class="thumb" style="display:none" alt="">
        <div class="selrow">
          <button class="ir-pill ir-soft" id="btnTrocar" type="button" style="padding:8px 14px"><i class="fa fa-times"></i> Trocar</button>
          <button class="ir-pill ir-pri" id="btnExtrair" type="button"><i class="fa fa-magic"></i> Extrair texto</button>
        </div>
      </div>
      <div class="progress-wrap" id="prog">
        <div class="spinner"></div>
        <div style="font-weight:700;color:var(--ir-text)" id="progMsg">Extraindo o texto…</div>
        <div class="arq-meta">Isso pode levar alguns segundos, conforme o tamanho do documento.</div>
      </div>
    </div>

    <!-- Editor de revisão -->
    <div class="ir-card" id="editorCard">
      <div class="ed-toolbar">
        <strong style="color:var(--ir-text)"><i class="fa fa-file-text-o" style="color:var(--ir-primary)"></i> Texto extraído</strong>
        <span class="ed-info" id="edInfo"></span>
        <span class="sep"></span>
        <button class="tbtn" id="btnUnir" title="Remover quebras de linha simples (mantém parágrafos)"><i class="fa fa-align-left"></i> Unir linhas</button>
        <button class="tbtn" id="btnMono" title="Fonte monoespaçada"><i class="fa fa-font"></i> Mono</button>
        <button class="tbtn" id="btnCopiar"><i class="fa fa-clipboard"></i> Copiar</button>
        <button class="tbtn" id="btnBaixar"><i class="fa fa-download"></i> Baixar .txt</button>
        <button class="tbtn" id="btnNova"><i class="fa fa-refresh"></i> Nova extração</button>
      </div>
      <div id="truncAviso" style="display:none;background:#fef3c7;color:#92400e;border-radius:10px;padding:10px 12px;font-size:.85rem;margin-bottom:10px">
        <i class="fa fa-exclamation-triangle"></i> A resposta pode ter sido cortada por limite de tamanho. Para documentos muito longos, divida em partes.
      </div>
      <textarea class="editor" id="editor" spellcheck="true" placeholder="O texto extraído aparecerá aqui para revisão…"></textarea>
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
  var TEM_CHAVE=<?php echo $temChave?'true':'false'; ?>;
  var arquivo=null, ultimoNome='extracao';
  var unido=false, snapshotAntesUniao='';
  function el(id){ return document.getElementById(id); }

  /* ---------- Upload ---------- */
  var dz=el('dz'), fi=el('fileInput');
  dz.addEventListener('click',function(){ fi.click(); });
  ['dragover','dragenter'].forEach(function(e){ dz.addEventListener(e,function(ev){ ev.preventDefault(); dz.classList.add('drag'); }); });
  ['dragleave','drop'].forEach(function(e){ dz.addEventListener(e,function(ev){ ev.preventDefault(); dz.classList.remove('drag'); }); });
  dz.addEventListener('drop',function(ev){ if(ev.dataTransfer.files[0]) selecionar(ev.dataTransfer.files[0]); });
  fi.addEventListener('change',function(){ if(fi.files[0]) selecionar(fi.files[0]); });

  var OK_TIPOS=['application/pdf','image/png','image/jpeg','image/webp'];
  function selecionar(file){
    if(!TEM_CHAVE){ Swal.fire('Configuração necessária','Cadastre a chave da API do Gemini em "Configurar".','warning'); return; }
    if(OK_TIPOS.indexOf(file.type)===-1){ Swal.fire('Formato não suportado','Use PDF, PNG, JPG ou WEBP.','warning'); return; }
    if(file.size>20*1024*1024){ Swal.fire('Arquivo grande','O limite é 20 MB. Divida o PDF ou reduza a imagem.','warning'); return; }
    arquivo=file;
    ultimoNome=(file.name||'extracao').replace(/\.[^.]+$/,'');
    el('arqNome').textContent=file.name;
    el('arqMeta').textContent=(file.type.replace('application/','').replace('image/','').toUpperCase())+' · '+humano(file.size);
    var ehPdf=file.type==='application/pdf';
    el('arqIc').innerHTML='<i class="fa '+(ehPdf?'fa-file-pdf-o':'fa-file-image-o')+'"></i>';
    var th=el('thumb');
    if(!ehPdf){ th.src=URL.createObjectURL(file); th.style.display='block'; } else { th.style.display='none'; }
    el('uploadCard').style.display='none';
    el('fileCard').style.display='block';
    el('editorCard').style.display='none';
    el('prog').style.display='none';
  }
  function humano(n){ n=+n; if(n<1024)return n+' B'; if(n<1048576)return (n/1024).toFixed(1)+' KB'; return (n/1048576).toFixed(1)+' MB'; }

  el('btnTrocar').addEventListener('click',resetar);
  el('btnNova').addEventListener('click',resetar);
  function resetar(){
    arquivo=null; fi.value='';
    el('fileCard').style.display='none';
    el('editorCard').style.display='none';
    el('uploadCard').style.display='block';
  }

  /* ---------- Extrair ---------- */
  el('btnExtrair').addEventListener('click',async function(){
    if(!arquivo){ return; }
    var fd=new FormData();
    fd.append('csrf',CSRF); fd.append('arquivo',arquivo);
    el('prog').style.display='block'; el('btnExtrair').disabled=true; el('btnTrocar').disabled=true;
    try{
      var r=await fetch('extrair.php',{method:'POST',body:fd,credentials:'same-origin'});
      var j=await r.json();
      if(j.status!=='success') throw new Error(j.message||'Falha na extração.');
      snapshotAntesUniao=limparTracos(j.texto);      // versão limpa com quebras (para "Desfazer união")
      el('editor').value=unirLinhasTexto(j.texto);    // por padrão já entra com as linhas unidas
      unido=true; setBtnUnir();
      atualizarInfo();
      el('truncAviso').style.display=j.truncado?'block':'none';
      el('editorCard').style.display='block';
      el('editorCard').scrollIntoView({behavior:'smooth'});
    }catch(e){
      Swal.fire('Não foi possível extrair', e.message, 'error');
    }finally{
      el('prog').style.display='none'; el('btnExtrair').disabled=false; el('btnTrocar').disabled=false;
    }
  });

  /* ---------- Editor ---------- */
  function atualizarInfo(){
    var t=el('editor').value;
    var palavras=t.trim()? t.trim().split(/\s+/).length : 0;
    el('edInfo').textContent=t.length.toLocaleString('pt-BR')+' caracteres · '+palavras.toLocaleString('pt-BR')+' palavras';
  }
  el('editor').addEventListener('input',atualizarInfo);

  /* Remove traços/underscores que o modelo usa para linhas em branco de formulário. Mantém as quebras. */
  function limparTracos(t){
    return t.replace(/\r\n/g,'\n')
            .replace(/^[ \t]*[-_=.·•*–—]{3,}[ \t]*$/gm,'')   // linhas só de traços
            .replace(/[_\-–—]{3,}/g,' ')                      // runs inline de traços/underscores
            .replace(/[ \t]{2,}/g,' ')                        // espaços múltiplos
            .replace(/[ \t]+\n/g,'\n')                        // espaço no fim da linha
            .replace(/^[ \t]+/gm,'')                          // espaço no início da linha
            .replace(/\n{3,}/g,'\n\n')                        // no máx. 1 linha em branco
            .replace(/^\n+|\n+$/g,'');                        // pontas
  }
  /* Remove quebras de linha simples (viram espaço), reúne palavras hifenizadas e mantém parágrafos. */
  function unirLinhasTexto(t){
    return limparTracos(t)
            .replace(/([A-Za-zÀ-ÿ])-\n([a-zà-ÿ])/g,'$1$2')    // "Tei-\nxeira" -> "Teixeira"
            .replace(/[ \t]+\n/g,'\n').replace(/\n[ \t]+/g,'\n')
            .split(/\n{2,}/)                                   // parágrafos = linha em branco
            .map(function(p){ return p.replace(/\n+/g,' ').replace(/[ \t]{2,}/g,' ').trim(); })
            .filter(function(p){ return p.length>0; })
            .join('\n\n');
  }
  function setBtnUnir(){
    var b=el('btnUnir');
    if(unido){ b.innerHTML='<i class="fa fa-undo"></i> Desfazer união'; b.classList.add('ativo'); }
    else { b.innerHTML='<i class="fa fa-align-left"></i> Unir linhas'; b.classList.remove('ativo'); }
  }
  el('btnUnir').addEventListener('click',function(){
    if(!unido){
      snapshotAntesUniao=el('editor').value;               // guarda para desfazer
      el('editor').value=unirLinhasTexto(el('editor').value);
      unido=true;
    } else {
      el('editor').value=snapshotAntesUniao;               // restaura o texto anterior
      unido=false;
    }
    setBtnUnir(); atualizarInfo();
  });

  el('btnMono').addEventListener('click',function(){ el('editor').classList.toggle('mono'); });
  el('btnCopiar').addEventListener('click',async function(){
    var t=el('editor').value;
    try{ await navigator.clipboard.writeText(t); }
    catch(e){ el('editor').select(); document.execCommand('copy'); }
    var b=this; var h=b.innerHTML; b.innerHTML='<i class="fa fa-check"></i> Copiado'; setTimeout(function(){ b.innerHTML=h; },1400);
  });
  el('btnBaixar').addEventListener('click',function(){
    var blob=new Blob([el('editor').value],{type:'text/plain;charset=utf-8'});
    var a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=(ultimoNome||'extracao')+'.txt';
    document.body.appendChild(a); a.click(); a.remove();
  });
})();
</script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
