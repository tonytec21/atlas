<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/assinatura_os_config.php';
os_ensure_schema();

$tipo = trim((string)($_GET['tipo'] ?? ''));
$osId = (int)($_GET['id'] ?? $_GET['os_id'] ?? 0);
if (!os_tipo_valido($tipo) || $osId <= 0) { header('Location: index.php'); exit; }

$info   = os_doc_info($tipo, $osId);
$jaAssinado = $info && (int)$info['assinado'] === 1;
$recemOk = isset($_GET['ok']);
$resign  = isset($_GET['resign']) && $_GET['resign'] == '1';
$mostrarEditor = !$jaAssinado || $resign;   // mostra preview + posicionamento + botão assinar
$signer = os_signer_info();
$tituloDoc = os_tipos()[$tipo]['titulo'];
$CSRF = os_csrf_token();
$assinadoUrl = 'view_signed_os.php?tipo=' . rawurlencode($tipo) . '&os_id=' . $osId;
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assinar <?php echo h($tituloDoc); ?> · O.S. <?php echo $osId; ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root{ --az:#2563eb; --az2:#4f46e5; --ok:#16a34a; }
    *{ box-sizing:border-box; } body{ font-family:Inter,system-ui,sans-serif; background:#f1f5f9; margin:0; color:#0f172a; }
    .wrap{ max-width:1200px; margin:0 auto; padding:18px 16px 60px; }
    .hero{ display:flex; gap:14px; align-items:center; margin-bottom:16px; }
    .hero .ic{ width:48px;height:48px;border-radius:13px;background:linear-gradient(135deg,var(--az),var(--az2));color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:0 0 auto; }
    .hero h1{ font-size:1.35rem;font-weight:800;margin:0; } .hero .sub{ color:#64748b;font-size:.9rem; }
    .chip{ display:inline-flex;align-items:center;gap:7px;padding:5px 11px;border-radius:999px;background:#eef2f7;color:#334155;font-size:.8rem;font-weight:600; }
    .chip i{ font-size:.6rem;color:#eab308; } .chip.on i{ color:var(--ok); } .chip.off i{ color:#ef4444; }
    .card{ background:#fff;border:1px solid #e5e9f0;border-radius:16px;box-shadow:0 10px 30px rgba(15,23,42,.06);margin-bottom:16px; }
    .card .hd{ padding:13px 18px;border-bottom:1px solid #eef1f6;font-weight:700;display:flex;align-items:center;gap:9px; } .card .hd i{ color:var(--az); }
    .card .bd{ padding:16px 18px; }
    .canvas-wrap{ background:#f4f6fa;padding:18px;max-height:74vh;overflow:auto;border-radius:0 0 16px 16px; }
    #pages{ display:flex;flex-direction:column;gap:18px;align-items:center; }
    .pagewrap{ position:relative;width:100%;max-width:780px;background:#fff;border-radius:6px;box-shadow:0 6px 24px rgba(15,23,42,.14);overflow:hidden; }
    .pagewrap canvas{ display:block;width:100%;height:auto; }
    .overlay{ position:absolute;inset:0;cursor:crosshair; }
    .hint{ position:absolute;left:50%;top:14px;transform:translateX(-50%);background:rgba(37,99,235,.95);color:#fff;font-size:.8rem;font-weight:600;padding:7px 14px;border-radius:999px;pointer-events:none;z-index:3; }
    .sealbox{ position:absolute;background:rgba(255,255,255,.96);border:1px solid var(--az);border-radius:4px;box-shadow:0 6px 18px rgba(37,99,235,.28);display:flex;overflow:hidden;cursor:move;user-select:none;touch-action:none; }
    .sealbox .bar{ width:2.2%;min-width:3px;background:var(--az); }
    .sealbox .body{ padding:0 2.5%;display:flex;flex-direction:row;align-items:center;gap:3%;justify-content:space-between;min-width:0;width:100%; }
    .sealbox .col{ display:flex;flex-direction:column;justify-content:center;min-width:0;flex:1 1 56%;overflow:hidden; }
    .sealbox .colr{ display:flex;flex-direction:column;justify-content:center;align-items:flex-end;text-align:right;min-width:0;flex:0 0 auto;overflow:hidden; }
    .sealbox .t{ color:var(--az);font-weight:800;line-height:1.05;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .sealbox .n{ color:#111827;font-weight:800;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .sealbox .r{ color:#374151;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .sealbox .vr{ color:#6b7280;line-height:1.1;white-space:nowrap; }
    .btn-sign{ width:100%;padding:13px;font-weight:800;border:0;border-radius:12px;color:#fff;cursor:pointer;background:linear-gradient(135deg,var(--az),var(--az2));box-shadow:0 8px 20px rgba(37,99,235,.32);display:flex;align-items:center;justify-content:center;gap:9px; }
    .btn-sign:disabled{ opacity:.5;cursor:not-allowed;box-shadow:none; }
    .astat{ display:flex;align-items:center;gap:12px; } .astat .lamp{ width:12px;height:12px;border-radius:50%;background:#eab308;flex:0 0 auto; } .astat.on .lamp{ background:var(--ok);} .astat.off .lamp{ background:#ef4444; }
    .busy{ position:fixed;inset:0;background:rgba(241,245,249,.72);backdrop-filter:blur(3px);display:none;align-items:center;justify-content:center;flex-direction:column;gap:16px;z-index:9999; }
    .busy.show{ display:flex; } .spin{ width:48px;height:48px;border:4px solid #cbd5e1;border-top-color:var(--az);border-radius:50%;animation:sp .9s linear infinite; } @keyframes sp{ to{ transform:rotate(360deg);} }
    .okbadge{ width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2.3rem;margin:4px auto 10px; }
    iframe.signed{ width:100%;height:74vh;border:1px solid #e5e9f0;border-radius:12px;margin-top:14px; }
    .range{ width:100%;accent-color:var(--az); }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <div class="ic"><i class="fa fa-file-signature"></i></div>
    <div style="flex:1;min-width:0">
      <h1>Assinar <?php echo h($tituloDoc); ?> — O.S. nº <?php echo $osId; ?></h1>
      <div class="sub">Assinatura digital ICP-Brasil (PAdES / AD-RB) via Assinador SERPRO.<?php echo $resign ? ' <b style="color:#b45309">· Reassinatura: substituirá a versão anterior.</b>' : ''; ?></div>
      <div class="mt-1"><span id="serproChip" class="chip"><i class="fa fa-circle"></i> <span id="serproChipTxt">Verificando…</span></span></div>
    </div>
    <a href="javascript:window.close()" class="btn btn-outline-secondary btn-sm"><i class="fa fa-times"></i> Fechar</a>
  </div>

<?php if ($jaAssinado && !$resign): ?>
  <div class="card"><div class="bd text-center">
    <?php if ($recemOk): ?><div class="okbadge"><i class="fa fa-check"></i></div><h4 style="font-weight:800">Documento assinado com sucesso</h4>
    <?php else: ?><h4 style="font-weight:800">Este documento já está assinado</h4><?php endif; ?>
    <p class="text-muted">Assinatura digital ICP-Brasil (PAdES / AD-RB)<?php echo !empty($info['assinado_em']) ? ' · assinado em '.date('d/m/Y H:i', strtotime($info['assinado_em'])) : ''; ?>.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2 mb-2">
      <a class="btn btn-success" href="<?php echo h($assinadoUrl); ?>" target="_blank"><i class="fa fa-external-link-alt"></i> Abrir PDF assinado</a>
      <a class="btn btn-outline-primary" href="assinar-os.php?tipo=<?php echo h($tipo); ?>&id=<?php echo $osId; ?>&resign=1"><i class="fa fa-rotate-right"></i> Assinar novamente</a>
    </div>
    <p class="text-muted" style="font-size:.82rem;max-width:560px;margin:0 auto">Use “Assinar novamente” se a O.S./recibo foi editado após a assinatura — uma nova versão assinada substituirá a anterior.</p>
    <iframe class="signed" src="<?php echo h($assinadoUrl); ?>"></iframe>
  </div></div>
<?php else: ?>
  <div class="row">
    <div class="col-12 col-lg-8 order-1">
      <div class="card">
        <div class="hd"><i class="fa fa-file-pdf"></i> Pré-visualização <span class="text-muted ms-auto" style="font-weight:400;font-size:.84rem">clique onde a assinatura deve aparecer</span></div>
        <div class="canvas-wrap"><div id="pages"></div></div>
        <div id="statusLine" style="font-size:.85rem;color:#64748b;padding:10px 18px;border-top:1px solid #eef1f6;min-height:1.2em">Carregando documento…</div>
      </div>
    </div>
    <div class="col-12 col-lg-4 order-2 mt-3 mt-lg-0">
      <div class="card"><div class="hd"><i class="fa fa-usb"></i> Assinador SERPRO</div>
        <div class="bd">
          <div id="sAstat" class="astat"><span class="lamp"></span><div><div class="fw-bold" id="sState">Verificando…</div><div class="text-muted" id="sHelp" style="font-size:.82rem">Aguarde a conexão.</div></div></div>
          <div class="mt-3 d-flex flex-wrap" style="gap:8px">
            <button id="btnReconnect" class="btn btn-outline-secondary btn-sm" type="button"><i class="fa fa-sync"></i> Reconectar</button>
            <a class="btn btn-outline-secondary btn-sm" href="http://127.0.0.1:65056/" target="_blank" rel="noopener"><i class="fa fa-unlock-alt"></i> Autorizar</a>
          </div>
        </div>
      </div>
      <div class="card"><div class="hd"><i class="fa fa-stamp"></i> Selo</div>
        <div class="bd">
          <div class="fw-bold"><?php echo h($signer['nome'] ?: '—'); ?></div>
          <?php if ($signer['cargo']): ?><div class="text-muted" style="font-size:.84rem"><?php echo h($signer['cargo']); ?></div><?php endif; ?>
          <div class="mt-3">
            <label class="d-flex justify-content-between mb-1" style="font-size:.84rem;font-weight:600">Largura do selo <span id="wVal" style="color:var(--az);font-weight:700">42%</span></label>
            <input id="sealW" class="range" type="range" min="0.28" max="0.72" step="0.01" value="0.42">
          </div>
        </div>
      </div>
      <button id="btnAssinar" class="btn-sign" disabled><i class="fa fa-lock"></i> Assinar com o token</button>
    </div>
  </div>
<?php endif; ?>
</div>

<div id="busy" class="busy"><div class="spin"></div><div id="busyMsg" class="fw-bold text-muted">Processando…</div></div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<?php if ($mostrarEditor): ?>
<script src="../oficios/pdfjs/pdf.min.js"></script>
<script src="../oficios/serpro/serpro-signer-promise.js"></script>
<script src="../oficios/serpro/serpro-signer-client.js"></script>
<script>
(function(){
  "use strict";
  var TIPO=<?php echo json_encode($tipo); ?>, OSID=<?php echo json_encode($osId); ?>, CSRF=<?php echo json_encode($CSRF); ?>;
  var NOME=<?php echo json_encode($signer['nome']); ?>, CARGO=<?php echo json_encode($signer['cargo']); ?>;
  var C=window.SerproSignerClient, serproOnline=false, seal={page:null,xn:null,yn:null,wn:0.42};
  if(window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc='../oficios/pdfjs/pdf.worker.min.js';
  function el(id){return document.getElementById(id);}
  function status(m){el('statusLine').textContent=m||'';}
  function busy(on,m){el('busy').classList.toggle('show',!!on); if(m)el('busyMsg').textContent=m;}
  function b64ToU8(b){var s=atob(b),a=new Uint8Array(s.length);for(var i=0;i<s.length;i++)a[i]=s.charCodeAt(i);return a;}
  async function postForm(url,data){data.csrf=CSRF;var r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data).toString(),credentials:'same-origin'});var t=await r.text();try{return JSON.parse(t);}catch(e){throw new Error('Resposta inválida: '+t.slice(0,160));}}

  function setConn(state,label,help){var chip=el('serproChip'),ast=el('sAstat');chip.className='chip '+(state||'');ast.className='astat '+(state||'');el('serproChipTxt').textContent=label;el('sState').textContent=label;if(help)el('sHelp').textContent=help;el('btnAssinar').disabled=(state!=='on');}
  function verifyAndConnect(){setConn('','Verificando Assinador…','Procurando o Assinador…');C.verifyIsInstalledAndRunning().success(connect).error(function(){serproOnline=false;setConn('off','Não está em execução','Abra o Assinador SERPRO e clique em Reconectar.');});}
  function connect(){try{C.connect(function(){serproOnline=true;setConn('on','Assinador conectado','Token pronto para assinar.');},function(){serproOnline=false;setConn('off','Conexão encerrada','Clique em Reconectar.');},function(){serproOnline=false;setConn('','Autorização pendente','Clique em “Autorizar” e reconecte.');});}catch(e){setConn('off','Falha ao conectar','');}}
  var rb=el('btnReconnect'); if(rb) rb.addEventListener('click',verifyAndConnect);

  async function loadPreview(){
    status('Gerando pré-visualização…');
    var r=await postForm('prepare_os_pdf.php',{tipo:TIPO,os_id:OSID});
    if(r.status!=='success') throw new Error(r.message||'Falha ao gerar a pré-visualização.');
    var pdf=await pdfjsLib.getDocument({data:b64ToU8(r.pdf_base64)}).promise;
    var box=el('pages');box.innerHTML='';
    for(var p=1;p<=pdf.numPages;p++){
      var page=await pdf.getPage(p),vp=page.getViewport({scale:1.6});
      var wrap=document.createElement('div');wrap.className='pagewrap';wrap.dataset.page=p;
      var cv=document.createElement('canvas');cv.width=vp.width;cv.height=vp.height;
      var ov=document.createElement('div');ov.className='overlay';
      wrap.appendChild(cv);wrap.appendChild(ov);box.appendChild(wrap);
      if(p===1){var hp=document.createElement('div');hp.className='hint';hp.id='hint';hp.textContent='Clique para posicionar a assinatura';wrap.appendChild(hp);}
      await page.render({canvasContext:cv.getContext('2d'),viewport:vp}).promise;
      (function(pp,o){o.addEventListener('pointerdown',function(ev){if(ev.target.closest('.sealbox'))return;place(pp,o,ev);});})(p,ov);
    }
    status('Clique no documento para posicionar a assinatura.');
  }
  function pageOverlay(p){var w=document.querySelector('.pagewrap[data-page="'+p+'"]');return w?w.querySelector('.overlay'):null;}
  function place(p,ov,ev){var r=ov.getBoundingClientRect();seal.page=p;seal.xn=Math.min(0.98,Math.max(0,(ev.clientX-r.left)/r.width));seal.yn=Math.min(0.98,Math.max(0,(ev.clientY-r.top)/r.height));var hp=el('hint');if(hp)hp.style.display='none';drawSeal();status('Assinatura na página '+p+'. Arraste para ajustar ou clique em Assinar.');}
  var SEAL_RATIO=0.22;
  function drawSeal(){
    document.querySelectorAll('.sealbox').forEach(function(b){b.remove();});
    if(seal.page==null)return; var ov=pageOverlay(seal.page); if(!ov)return;
    var W=ov.clientWidth,H=ov.clientHeight,w=seal.wn*W,hgt=w*SEAL_RATIO;
    var left=Math.max(2,Math.min(seal.xn*W,W-w-2)),top=Math.max(2,Math.min(seal.yn*H,H-hgt-2));
    var box=document.createElement('div');box.className='sealbox';box.style.left=left+'px';box.style.top=top+'px';box.style.width=w+'px';box.style.height=hgt+'px';
    var bar=document.createElement('div');bar.className='bar';
    var body=document.createElement('div');body.className='body';
    // coluna esquerda (identidade)
    var col=document.createElement('div');col.className='col';
    var t=document.createElement('div');t.className='t';t.textContent='ASSINADO DIGITALMENTE';
    var nm=document.createElement('div');nm.className='n';nm.textContent=NOME||'';
    col.appendChild(t);col.appendChild(nm);
    if(CARGO){var cg=document.createElement('div');cg.className='r';cg.textContent=CARGO;col.appendChild(cg);}
    // coluna direita (validação)
    var colR=document.createElement('div');colR.className='colr';
    var v1=document.createElement('div');v1.className='vr';v1.textContent='ICP-Brasil · PAdES';
    var v2=document.createElement('div');v2.className='vr';v2.textContent='Assinador SERPRO';
    colR.appendChild(v1);colR.appendChild(v2);
    body.appendChild(col);body.appendChild(colR);
    box.appendChild(bar);box.appendChild(body);
    var fs=w*0.030; // fonte proporcional à largura
    t.style.fontSize=(fs*1.05)+'px';nm.style.fontSize=(fs*1.5)+'px';
    if(CARGO)box.querySelector('.r').style.fontSize=(fs*1.12)+'px';
    v1.style.fontSize=(fs*1.02)+'px';v2.style.fontSize=(fs*1.02)+'px';
    if(w<170){ colR.style.display='none'; } // selo estreito: some com a coluna direita
    enableDrag(box,ov);ov.appendChild(box);
  }
  function enableDrag(box,ov){box.addEventListener('pointerdown',function(ev){ev.preventDefault();ev.stopPropagation();var bx=box.getBoundingClientRect(),offX=ev.clientX-bx.left,offY=ev.clientY-bx.top;box.setPointerCapture(ev.pointerId);
    function move(e){var r=ov.getBoundingClientRect(),W=r.width,H=r.height,w=seal.wn*W,hgt=w*0.42;seal.xn=Math.min(0.98,Math.max(0,(e.clientX-r.left-offX)/W));seal.yn=Math.min(0.98,Math.max(0,(e.clientY-r.top-offY)/H));box.style.left=Math.max(2,Math.min(seal.xn*W,W-w-2))+'px';box.style.top=Math.max(2,Math.min(seal.yn*H,H-hgt-2))+'px';}
    function up(){try{box.releasePointerCapture(ev.pointerId);}catch(_){}box.removeEventListener('pointermove',move);box.removeEventListener('pointerup',up);box.removeEventListener('pointercancel',up);}
    box.addEventListener('pointermove',move);box.addEventListener('pointerup',up);box.addEventListener('pointercancel',up);});}
  el('sealW').addEventListener('input',function(){seal.wn=parseFloat(this.value);el('wVal').textContent=Math.round(seal.wn*100)+'%';drawSeal();});

  function serproSignHash(x){return new Promise(function(resolve,reject){try{C.sign('hash',x).success(function(r){if(r&&r.actionCanceled)return reject(new Error('Assinatura cancelada no token.'));if(r&&r.hasError)return reject(new Error(r.errorMessage||r.message||'Erro no Assinador.'));resolve(r);}).error(function(){reject(new Error('Falha ao assinar no token.'));});}catch(e){reject(e);}});}
  async function assinar(){
    if(seal.page==null){status('Clique no documento para posicionar a assinatura.');return;}
    if(!serproOnline){status('Assinador SERPRO não conectado.');return;}
    try{
      busy(true,'Preparando o documento…');
      var prep=await postForm('os_pades_prepare.php',{tipo:TIPO,os_id:OSID,page:seal.page,xn:seal.xn,yn:seal.yn,wn:seal.wn});
      if(prep.status!=='success')throw new Error(prep.message||'Falha ao preparar.');
      busy(true,'Aguardando o token — informe o PIN…');
      var resp=await serproSignHash(prep.to_sign);
      var cms=resp.signature,subject=(resp.by&&resp.by.subject)||'';
      if(!cms)throw new Error('O Assinador não retornou a assinatura.');
      busy(true,'Finalizando a assinatura…');
      var fin=await postForm('os_pades_finalize.php',{session:prep.session,signature_b64:cms,cert_subject:subject});
      if(fin.status!=='success')throw new Error(fin.message||'Falha ao finalizar.');
      busy(false);
      location.href='assinar-os.php?tipo='+encodeURIComponent(TIPO)+'&id='+OSID+'&ok=1';
    }catch(e){busy(false);status('Erro: '+e.message);if(window.Swal)Swal.fire({icon:'error',title:'Não foi possível assinar',text:e.message});}
  }
  el('btnAssinar').addEventListener('click',assinar);

  setConn('','Verificando Assinador…','Procurando o Assinador…');
  verifyAndConnect();
  loadPreview().catch(function(e){status('Erro na pré-visualização: '+e.message);});
  window.addEventListener('resize',function(){if(seal.page!=null)drawSeal();});
})();
</script>
<?php endif; ?>
</body>
</html>
