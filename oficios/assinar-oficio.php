<?php
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_config.php';
assin_ensure_schema();
// TCloud Assinador centralizado no Atlas Signum (1.7.0+). Sem a ponte do Signum, a página não quebra:
// segue pelo Assinador SERPRO e avisa para atualizar o Signum.
$USA_TC = false; $TC_FALTA_SIGNUM = false;
if (is_file(__DIR__ . '/../signum/inc/tcloud_modulo.php')) {
    require_once __DIR__ . '/../signum/inc/tcloud_modulo.php';
    $USA_TC = asg_tcm_usa_tcloud();   // escolha do usuário no Configurar do Signum (padrão: TCloud Assinador)
} else {
    $TC_FALTA_SIGNUM = true;
}

$numero = isset($_GET['numero']) ? trim((string)$_GET['numero']) : '';
if ($numero === '') { header('Location: index.php'); exit; }

$conn = assin_db();
$stmt = $conn->prepare("SELECT numero, assunto, destinatario, data, assinante, cargo_assinante, status, assinado FROM oficios WHERE numero = ? LIMIT 1");
$stmt->bind_param('s', $numero);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { http_response_code(404); echo 'Ofício não encontrado.'; exit; }
$oficio = $res->fetch_assoc();
$stmt->close();

$jaAssinado  = ((int)$oficio['assinado'] === 1);
$assinadoUrl = $jaAssinado ? ('view_signed_oficio.php?numero=' . rawurlencode($numero)) : '';
$recemOk     = isset($_GET['ok']);
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas - Assinar ofício <?php echo h($numero); ?></title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
<style>
    :root{ --sig-azul:#2563eb; --sig-azul-d:#1d4ed8; --sig-azul-l:#dbeafe; --sig-ok:#16a34a; }

    /* espaço p/ a barra de navegação inferior fixa do menu.php não cobrir o botão */
    #main .container{ padding-bottom:110px; }

    .sig-chip{ display:inline-flex; align-items:center; gap:7px; }
    .sig-chip .fa{ font-size:.6rem; color:#eab308; }
    .sig-chip.on .fa{ color:var(--sig-ok); } .sig-chip.off .fa{ color:#ef4444; }

    .sig-card{ background:#fff; border:1px solid #e5e9f0; border-radius:16px; box-shadow:0 10px 30px rgba(15,23,42,.06); margin-bottom:18px; }
    .sig-card .hd{ padding:14px 18px; border-bottom:1px solid #eef1f6; font-weight:700; display:flex; align-items:center; gap:9px; }
    .sig-card .hd .fa{ color:var(--sig-azul); }
    .sig-card .bd{ padding:16px 18px; }
    body.dark-mode .sig-card{ background:#23272a; border-color:rgba(255,255,255,.07); }
    body.dark-mode .sig-card .hd{ border-bottom-color:rgba(255,255,255,.07); }

    /* status */
    .sig-astat{ display:flex; align-items:center; gap:12px; }
    .sig-astat .lamp{ width:12px;height:12px;border-radius:50%; background:#eab308; box-shadow:0 0 0 4px rgba(234,179,8,.18); flex:0 0 auto; }
    .sig-astat.on .lamp{ background:var(--sig-ok); box-shadow:0 0 0 4px rgba(22,163,74,.18); }
    .sig-astat.off .lamp{ background:#ef4444; box-shadow:0 0 0 4px rgba(239,68,68,.18); }
    .sig-astat .st{ font-weight:700; } .sig-astat .hl{ color:#64748b; font-size:.82rem; }

    /* steps */
    .sig-steps{ display:flex; flex-direction:column; gap:2px; }
    .sig-step{ display:flex; gap:12px; align-items:flex-start; padding:9px 8px; border-radius:10px; transition:.2s; }
    .sig-step .n{ width:26px;height:26px;border-radius:50%; background:#eef2f7; color:#64748b; font-weight:700; font-size:.82rem; display:flex;align-items:center;justify-content:center; flex:0 0 auto; }
    .sig-step .t{ font-size:.9rem; padding-top:2px; } .sig-step .t small{ display:block; color:#64748b; font-size:.78rem; }
    .sig-step.active{ background:var(--sig-azul-l); } .sig-step.active .n{ background:var(--sig-azul); color:#fff; }
    .sig-step.done .n{ background:var(--sig-ok); color:#fff; }
    body.dark-mode .sig-step .n{ background:rgba(255,255,255,.08); } body.dark-mode .sig-step.active{ background:rgba(37,99,235,.18); }

    .sig-signer{ display:flex; gap:12px; align-items:center; }
    .sig-signer .av{ width:42px;height:42px;border-radius:50%; background:linear-gradient(135deg,#4f46e5,#2563eb); color:#fff; display:flex;align-items:center;justify-content:center; font-weight:700; flex:0 0 auto; }
    .sig-signer .nm{ font-weight:700; } .sig-signer .cg{ color:#64748b; font-size:.84rem; }
    .sig-range{ width:100%; accent-color:var(--sig-azul); }

    .sig-btn-sign{ width:100%; padding:13px; font-weight:800; border:0; border-radius:12px; color:#fff; cursor:pointer;
        background:linear-gradient(135deg,var(--sig-azul),#4f46e5); box-shadow:0 8px 20px rgba(37,99,235,.32); display:flex; align-items:center; justify-content:center; gap:9px; transition:.15s; }
    .sig-btn-sign:hover:not(:disabled){ transform:translateY(-1px); box-shadow:0 12px 24px rgba(37,99,235,.4); }
    .sig-btn-sign:disabled{ opacity:.5; cursor:not-allowed; box-shadow:none; }

    /* preview */
    .sig-stage .hd .h{ color:#64748b; font-weight:400; font-size:.84rem; margin-left:auto; }
    .sig-canvas{ background:#f4f6fa; padding:20px; max-height:72vh; overflow:auto; border-radius:0 0 16px 16px; }
    body.dark-mode .sig-canvas{ background:#1b1e21; }
    #pages{ display:flex; flex-direction:column; gap:20px; align-items:center; }
    .pagewrap{ position:relative; width:100%; max-width:760px; background:#fff; border-radius:6px; box-shadow:0 6px 24px rgba(15,23,42,.14); overflow:hidden; }
    .pagewrap canvas{ display:block; width:100%; height:auto; }
    .overlay{ position:absolute; inset:0; cursor:crosshair; }
    .hint-place{ position:absolute; left:50%; top:14px; transform:translateX(-50%); background:rgba(37,99,235,.95); color:#fff; font-size:.8rem; font-weight:600; padding:7px 14px; border-radius:999px; pointer-events:none; animation:sigbob 1.6s ease-in-out infinite; z-index:3; }
    @keyframes sigbob{ 0%,100%{ transform:translateX(-50%) translateY(0);} 50%{ transform:translateX(-50%) translateY(-4px);} }

    .sealbox{ position:absolute; background:rgba(255,255,255,.96); border:1px solid var(--sig-azul); border-radius:5px; box-shadow:0 6px 18px rgba(37,99,235,.28); display:flex; overflow:hidden; cursor:move; user-select:none; touch-action:none; }
    .sealbox .s-bar{ width:6%; min-width:5px; background:var(--sig-azul); }
    .sealbox .s-body{ padding:5% 7%; display:flex; flex-direction:column; justify-content:center; min-width:0; width:100%; }
    .sealbox .s-title{ color:var(--sig-azul); font-weight:800; line-height:1.1; }
    .sealbox .s-name{ color:#111827; font-weight:800; line-height:1.15; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sealbox .s-role{ color:#374151; line-height:1.15; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sealbox .s-foot{ color:#6b7280; line-height:1.1; margin-top:2%; }
    .sealbox .grip{ position:absolute; right:3px; bottom:2px; width:9px;height:9px; border-right:2px solid var(--sig-azul); border-bottom:2px solid var(--sig-azul); opacity:.6; }

    .sig-status-line{ font-size:.85rem; color:#64748b; padding:10px 18px; border-top:1px solid #eef1f6; min-height:1.2em; }
    body.dark-mode .sig-status-line{ border-top-color:rgba(255,255,255,.07); }

    /* busy */
    .sig-busy{ position:fixed; inset:0; background:rgba(241,245,249,.72); backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; flex-direction:column; gap:16px; z-index:9999; }
    .sig-busy.show{ display:flex; }
    .sig-spin{ width:48px;height:48px;border:4px solid #cbd5e1;border-top-color:var(--sig-azul);border-radius:50%;animation:sigspin .9s linear infinite; }
    @keyframes sigspin{ to{ transform:rotate(360deg);} }
    .sig-busy .m{ font-weight:600; color:#334155; }

    /* success */
    .sig-ok-badge{ width:76px;height:76px;border-radius:50%; background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; display:flex;align-items:center;justify-content:center; font-size:2.3rem; margin:4px auto 10px; box-shadow:0 10px 24px rgba(22,163,74,.4); animation:sigpop .4s ease; }
    @keyframes sigpop{ 0%{ transform:scale(.6); opacity:0;} 100%{ transform:scale(1); opacity:1;} }
    iframe.sig-signed{ width:100%; height:74vh; border:1px solid #e5e9f0; border-radius:12px; margin-top:14px; }
    body.dark-mode iframe.sig-signed{ border-color:rgba(255,255,255,.07); }

    /* ---- responsividade (v1.7.0) ---- */
    .title-row{ flex-wrap:wrap; }
    .pagewrap canvas{ max-width:100%; }
    #statusLine{ overflow-wrap:anywhere; }
    @media(min-width:992px){ .sig-side{ position:sticky; top:84px; align-self:flex-start; } }
    @media(max-width:991.98px){ .sig-canvas{ max-height:none; padding:10px; } #pages{ gap:12px; } }
    @media(max-width:575.98px){
        .page-hero{ padding:14px 14px 8px; } .page-hero h1{ font-size:1.2rem; }
        .sig-card .hd{ padding:12px 14px; } .sig-card .bd{ padding:14px; }
        iframe.sig-signed{ height:62vh; }
        .sealbox .grip{ width:16px; height:16px; }
        .hint-place{ font-size:.72rem; padding:6px 10px; white-space:nowrap; }
    }
</style>
<?php if (function_exists('asg_tcm_css')) echo asg_tcm_css(); ?>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">

        <section class="page-hero">
            <div class="title-row">
                <div class="title-icon"><i class="fa fa-pencil-square-o"></i></div>
                <div>
                    <h1>Assinar ofício <?php echo h($numero); ?></h1>
                    <div class="subtitle muted"><?php echo h($oficio['assunto'] ?? ''); ?></div>
                    <div class="mt-2">
                        <span id="serproChip" class="chip sig-chip"><i class="fa fa-circle"></i> <span id="serproChipTxt"><?php echo $USA_TC ? 'TCloud Assinador' : 'Verificando Assinador…'; ?></span></span>
                    </div>
                </div>
                <div class="ml-auto">
                    <a href="index.php" class="btn btn-soft btn-pill btn-sm"><i class="fa fa-arrow-left"></i> Voltar</a>
                </div>
            </div>
        </section>

<?php if ($TC_FALTA_SIGNUM): ?>
<div style="background:#fef3c7;border:1px solid #fcd34d;color:#92400e;border-radius:12px;padding:10px 14px;margin:0 0 14px;font-size:.88rem"><b>TCloud Assinador indisponível neste módulo:</b> falta o Atlas Signum 1.7.0 ou mais novo (pasta <code>signum/inc</code>). Por enquanto a assinatura segue pelo Assinador SERPRO.</div>
<?php endif; ?>
<?php if ($jaAssinado): ?>
        <div class="sig-card">
            <div class="bd text-center">
                <?php if ($recemOk): ?>
                    <div class="sig-ok-badge"><i class="fa fa-check"></i></div>
                    <h3 style="font-weight:800;margin:0">Ofício assinado com sucesso</h3>
                <?php else: ?>
                    <h3 style="font-weight:800;margin:0">Este ofício já está assinado</h3>
                <?php endif; ?>
                <p class="muted" style="margin:8px 0 14px">Assinatura digital ICP-Brasil (PAdES) com certificado A3.</p>
                <a class="btn btn-success btn-pill" href="<?php echo h($assinadoUrl); ?>" target="_blank"><i class="fa fa-external-link"></i> Abrir PDF em nova aba</a>
                <iframe class="sig-signed" src="<?php echo h($assinadoUrl); ?>"></iframe>
            </div>
        </div>
<?php else: ?>
        <?php if ($USA_TC) echo asg_tcm_banner_html(); ?>
        <div class="row">
            <!-- Sidebar (direita no desktop) -->
            <div class="col-12 col-lg-4 order-2 mt-3 mt-lg-0 sig-side">
                <?php if ($USA_TC): echo asg_tcm_card_html(['card' => 'sig-card', 'hd' => 'hd', 'bd' => 'bd']); else: ?>
                <div class="sig-card">
                    <div class="hd"><i class="fa fa-usb"></i> Assinador SERPRO</div>
                    <div class="bd">
                        <div id="sAstat" class="sig-astat">
                            <span class="lamp"></span>
                            <div><div class="st" id="sState">Verificando…</div><div class="hl" id="sHelp">Aguarde a conexão com o token.</div></div>
                        </div>
                        <div class="mt-3 d-flex flex-wrap" style="gap:8px">
                            <button id="btnReconnect" class="btn btn-soft btn-pill btn-sm" type="button"><i class="fa fa-refresh"></i> Reconectar</button>
                            <a class="btn btn-soft btn-pill btn-sm" href="http://127.0.0.1:65056/" target="_blank" rel="noopener"><i class="fa fa-unlock-alt"></i> Autorizar</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="sig-card">
                    <div class="hd"><i class="fa fa-list-ol"></i> Como assinar</div>
                    <div class="bd">
                        <div class="sig-steps">
                        <?php if ($USA_TC): ?>
                            <div class="sig-step" id="st1"><div class="n">1</div><div class="t">Posicionar o selo<small>Toque no documento e arraste para ajustar</small></div></div>
                            <div class="sig-step" id="st2"><div class="n">2</div><div class="t">Escolher o certificado<small>O TCloud Assinador abre neste computador; digite o PIN</small></div></div>
                            <div class="sig-step" id="st3"><div class="n">3</div><div class="t">Pronto<small>Ofício assinado e gravado</small></div></div>
                        <?php else: ?>
                            <div class="sig-step" id="st1"><div class="n">1</div><div class="t">Conectar o token<small>Assinador aberto e autorizado</small></div></div>
                            <div class="sig-step" id="st2"><div class="n">2</div><div class="t">Posicionar o selo<small>Clique no documento e arraste para ajustar</small></div></div>
                            <div class="sig-step" id="st3"><div class="n">3</div><div class="t">Assinar com o PIN<small>Confirme no token</small></div></div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="sig-card">
                    <div class="hd"><i class="fa fa-certificate"></i> Selo</div>
                    <div class="bd">
                        <div class="sig-signer">
                            <div class="av" id="avInit">?</div>
                            <div><div class="nm"><?php echo h($oficio['assinante'] ?? '—'); ?></div><div class="cg"><?php echo h($oficio['cargo_assinante'] ?? ''); ?></div></div>
                        </div>
                        <div class="mt-3">
                            <label class="d-flex justify-content-between mb-1" style="font-size:.84rem;font-weight:600">Largura do selo <span id="wVal" style="color:var(--sig-azul);font-weight:700">24%</span></label>
                            <input id="sealW" class="sig-range" type="range" min="0.20" max="0.55" step="0.01" value="0.24">
                        </div>
                    </div>
                </div>

                <button id="btnAssinar" class="sig-btn-sign" disabled>
                    <i class="fa fa-lock"></i> Assinar com o token
                </button>
            </div>

            <!-- Preview (esquerda no desktop) -->
            <div class="col-12 col-lg-8 order-1">
                <div class="sig-card sig-stage">
                    <div class="hd"><i class="fa fa-file-pdf-o"></i> Pré-visualização <span class="h">clique onde o selo deve aparecer</span></div>
                    <div class="sig-canvas"><div id="pages"></div></div>
                    <div id="statusLine" class="sig-status-line">Carregando documento…</div>
                    <div class="tcm-mbar"><button id="btnAssinarM" type="button" class="sig-btn-sign" disabled><i class="fa fa-lock"></i> Assinar com o token</button></div>
                </div>
            </div>
        </div>
<?php endif; ?>
    </div>
</div>

<div id="busy" class="sig-busy"><div class="sig-spin"></div><div id="busyMsg" class="m">Processando…</div></div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<?php if (!$jaAssinado): ?>
<script src="pdfjs/pdf.min.js"></script>
<?php if ($USA_TC): echo asg_tcm_scripts('tcloud_iniciar.php'); else: ?>
<script src="serpro/serpro-signer-promise.js"></script>
<script src="serpro/serpro-signer-client.js"></script>
<?php endif; ?>
<script>
(function(){
    "use strict";
    var NUMERO = <?php echo json_encode($numero); ?>;
    var NOME   = <?php echo json_encode($oficio['assinante'] ?? ''); ?>;
    var CARGO  = <?php echo json_encode($oficio['cargo_assinante'] ?? ''); ?>;
    var TC     = <?php echo $USA_TC ? 'true' : 'false'; ?>;   // TCloud Assinador (Signum) ou Assinador SERPRO
    var C = window.SerproSignerClient;
    var serproOnline = false;
    var seal = { page:null, xn:null, yn:null, wn:0.24 };

    if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = 'pdfjs/pdf.worker.min.js';

    function el(id){ return document.getElementById(id); }
    function status(m){ el('statusLine').textContent = m || ''; }
    function busy(on,m){ el('busy').classList.toggle('show',!!on); if(m) el('busyMsg').textContent=m; }
    function b64ToU8(b64){ var bin=atob(b64),a=new Uint8Array(bin.length); for(var i=0;i<bin.length;i++)a[i]=bin.charCodeAt(i); return a; }
    async function postForm(url,data){
        var body=new URLSearchParams(data).toString();
        var r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'});
        var t=await r.text(); try{ return JSON.parse(t); }catch(e){ throw new Error('Resposta inválida do servidor: '+t.slice(0,180)); }
    }
    (function(){ el('avInit').textContent=(NOME||'?').trim().charAt(0).toUpperCase()||'?'; })();

    function setStep(id,cls){ var e=el(id); e.classList.remove('active','done'); if(cls) e.classList.add(cls); }
    function updateSteps(){
        if(TC){ setStep('st1', seal.page==null?'active':'done'); setStep('st2', seal.page==null?'':'active'); setStep('st3',''); return; }
        if(!serproOnline){ setStep('st1','active'); setStep('st2',''); setStep('st3',''); return; }
        setStep('st1','done');
        if(seal.page==null){ setStep('st2','active'); setStep('st3',''); }
        else { setStep('st2','done'); setStep('st3','active'); }
    }

    function setConn(state,label,help){
        var chip=el('serproChip'), astat=el('sAstat');
        chip.className='chip sig-chip '+(state||''); astat.className='sig-astat '+(state||'');
        el('serproChipTxt').textContent=label; el('sState').textContent=label;
        if(help) el('sHelp').textContent=help;
        el('btnAssinar').disabled=(state!=='on');
        updateSteps();
    }
    function verifyAndConnect(){
        setConn('','Verificando Assinador…','Procurando o Assinador…');
        C.verifyIsInstalledAndRunning().success(connect).error(function(){
            serproOnline=false; setConn('off','Não está em execução','Abra o Assinador SERPRO e clique em Reconectar.');
        });
    }
    function connect(){
        try{ C.connect(
            function(){ serproOnline=true; setConn('on','Assinador conectado','Token pronto para assinar.'); },
            function(){ serproOnline=false; setConn('off','Conexão encerrada','Clique em Reconectar.'); },
            function(){ serproOnline=false; setConn('','Autorização pendente','Clique em “Autorizar” e reconecte.'); }
        ); }catch(e){ setConn('off','Falha ao conectar',''); }
    }
    var rb=el('btnReconnect'); if(rb) rb.addEventListener('click',verifyAndConnect);

    async function loadPreview(){
        status('Gerando pré-visualização…');
        var r=await postForm('prepare_signed_pdf.php',{numero:NUMERO});
        if(r.status!=='success') throw new Error(r.message||'Falha ao gerar a pré-visualização.');
        var pdf=await pdfjsLib.getDocument({data:b64ToU8(r.pdf_base64)}).promise;
        var box=el('pages'); box.innerHTML='';
        for(var p=1;p<=pdf.numPages;p++){
            var page=await pdf.getPage(p);
            var vp1=page.getViewport({scale:1}), cssW=Math.min(760, box.clientWidth||760);var vp=page.getViewport({scale:Math.max(1,Math.min(3,cssW*Math.min(2,window.devicePixelRatio||1)/vp1.width))});
            var wrap=document.createElement('div'); wrap.className='pagewrap'; wrap.dataset.page=p;
            var cv=document.createElement('canvas'); cv.width=Math.floor(vp.width); cv.height=Math.floor(vp.height);
            var ov=document.createElement('div'); ov.className='overlay';
            wrap.appendChild(cv); wrap.appendChild(ov); box.appendChild(wrap);
            if(p===1){ var hp=document.createElement('div'); hp.className='hint-place'; hp.id='hintPlace'; hp.textContent='Clique para posicionar o selo'; wrap.appendChild(hp); }
            await page.render({canvasContext:cv.getContext('2d'),viewport:vp}).promise;
            bindOverlay(p,ov);
        }
        status('Clique no documento para posicionar o selo.');
    }
    function bindOverlay(p,ov){
        // 'click' (e não pointerdown): no celular, arrastar o dedo para rolar não reposiciona o selo
        ov.addEventListener('click',function(ev){ if(ev.target.closest('.sealbox')) return; place(p,ov,ev); });
    }
    function pageOverlay(p){ var w=document.querySelector('.pagewrap[data-page="'+p+'"]'); return w?w.querySelector('.overlay'):null; }
    function place(p,ov,ev){
        var r=ov.getBoundingClientRect();
        seal.page=p;
        seal.xn=Math.min(0.98,Math.max(0,(ev.clientX-r.left)/r.width));
        seal.yn=Math.min(0.98,Math.max(0,(ev.clientY-r.top)/r.height));
        var hp=el('hintPlace'); if(hp) hp.style.display='none';
        drawSeal(); updateSteps(); if(TC) el('btnAssinar').disabled=false;
        status('Selo na página '+p+'. Arraste para ajustar ou clique em Assinar.');
    }
    function drawSeal(){
        document.querySelectorAll('.sealbox').forEach(function(b){ b.remove(); });
        if(seal.page==null) return;
        var ov=pageOverlay(seal.page); if(!ov) return;
        var W=ov.clientWidth, H=ov.clientHeight;
        var w=seal.wn*W, hgt=w*0.42;
        var left=Math.max(2,Math.min(seal.xn*W, W-w-2));
        var top =Math.max(2,Math.min(seal.yn*H, H-hgt-2));
        var box=document.createElement('div'); box.className='sealbox';
        box.style.left=left+'px'; box.style.top=top+'px'; box.style.width=w+'px'; box.style.height=hgt+'px';
        var bar=document.createElement('div'); bar.className='s-bar';
        var body=document.createElement('div'); body.className='s-body';
        var t=document.createElement('div'); t.className='s-title'; t.textContent='ASSINADO DIGITALMENTE';
        var nm=document.createElement('div'); nm.className='s-name'; nm.textContent=NOME||'';
        body.appendChild(t); body.appendChild(nm);
        if(CARGO){ var cg=document.createElement('div'); cg.className='s-role'; cg.textContent=CARGO; body.appendChild(cg); }
        var ft=document.createElement('div'); ft.className='s-foot'; ft.textContent=TC?'ICP-Brasil · PAdES · TCloud Assinador':'ICP-Brasil · PAdES · SERPRO'; body.appendChild(ft);
        var grip=document.createElement('div'); grip.className='grip';
        box.appendChild(bar); box.appendChild(body); box.appendChild(grip);
        t.style.fontSize=(w*0.052)+'px'; nm.style.fontSize=(w*0.060)+'px';
        if(CARGO) box.querySelector('.s-role').style.fontSize=(w*0.048)+'px';
        ft.style.fontSize=(w*0.042)+'px';
        enableDrag(box, ov);
        ov.appendChild(box);
    }
    function enableDrag(box, ov){
        box.addEventListener('pointerdown',function(ev){
            ev.preventDefault(); ev.stopPropagation();
            var bx=box.getBoundingClientRect(); var offX=ev.clientX-bx.left, offY=ev.clientY-bx.top;
            box.setPointerCapture(ev.pointerId);
            function move(e){
                var r=ov.getBoundingClientRect(); var W=r.width,H=r.height,w=seal.wn*W,hgt=w*0.42;
                seal.xn=Math.min(0.98,Math.max(0,(e.clientX-r.left-offX)/W));
                seal.yn=Math.min(0.98,Math.max(0,(e.clientY-r.top-offY)/H));
                box.style.left=Math.max(2,Math.min(seal.xn*W,W-w-2))+'px';
                box.style.top =Math.max(2,Math.min(seal.yn*H,H-hgt-2))+'px';
            }
            function up(){ try{box.releasePointerCapture(ev.pointerId);}catch(_){} box.removeEventListener('pointermove',move); box.removeEventListener('pointerup',up); box.removeEventListener('pointercancel',up); }
            box.addEventListener('pointermove',move); box.addEventListener('pointerup',up); box.addEventListener('pointercancel',up);
        });
    }
    el('sealW').addEventListener('input',function(){ seal.wn=parseFloat(this.value); el('wVal').textContent=Math.round(seal.wn*100)+'%'; drawSeal(); });

    function serproSignHash(x){
        return new Promise(function(resolve,reject){
            try{ C.sign('hash',x).success(function(r){
                if(r&&r.actionCanceled) return reject(new Error('Assinatura cancelada no token.'));
                if(r&&r.hasError) return reject(new Error(r.errorMessage||r.message||'Erro no Assinador.'));
                resolve(r);
            }).error(function(){ reject(new Error('Falha ao assinar no token.')); });
            }catch(e){ reject(e); }
        });
    }
    async function assinar(){
        if(seal.page==null){ status('Clique no documento para posicionar o selo.'); return; }
        if(TC){
            // TCloud Assinador (Atlas Signum): mesma instalação, teste e acompanhamento do Signum
            var doc=await TcModulo.assinar({numero:NUMERO,page:seal.page,xn:seal.xn,yn:seal.yn,wn:seal.wn});
            if(doc){ location.href='assinar-oficio.php?numero='+encodeURIComponent(NUMERO)+'&ok=1'; }
            else status('Assinatura não concluída. Você pode tentar de novo.');
            return;
        }
        if(!serproOnline){ status('Assinador SERPRO não conectado.'); return; }
        try{
            busy(true,'Preparando o documento…');
            var prep=await postForm('pades_prepare.php',{numero:NUMERO,page:seal.page,xn:seal.xn,yn:seal.yn,wn:seal.wn});
            if(prep.status!=='success') throw new Error(prep.message||'Falha ao preparar.');
            busy(true,'Aguardando o token — informe o PIN…');
            var resp=await serproSignHash(prep.to_sign);
            var cms=resp.signature, subject=(resp.by&&resp.by.subject)||'';
            if(!cms) throw new Error('O Assinador não retornou a assinatura.');
            busy(true,'Finalizando a assinatura…');
            var fin=await postForm('pades_finalize.php',{session:prep.session,signature_b64:cms,cert_subject:subject});
            if(fin.status!=='success') throw new Error(fin.message||'Falha ao finalizar.');
            busy(false);
            location.href='assinar-oficio.php?numero='+encodeURIComponent(NUMERO)+'&ok=1';
        }catch(e){
            busy(false); status('Erro: '+e.message);
            if(window.Swal){ Swal.fire({icon:'error',title:'Não foi possível assinar',text:e.message}); } else { alert(e.message); }
        }
    }
    el('btnAssinar').addEventListener('click',assinar);

    // botão "Assinar" também embaixo do documento no celular (espelha o da lateral)
    (function(){
        var b=el('btnAssinar'), m=el('btnAssinarM'); if(!b||!m) return;
        var sync=function(){ m.disabled=b.disabled; }; sync();
        new MutationObserver(sync).observe(b,{attributes:true,attributeFilter:['disabled']});
        m.addEventListener('click',function(){ if(!b.disabled) b.click(); });
    })();

    if(TC){ updateSteps(); el('btnAssinar').disabled=true; }
    else { setConn('','Verificando Assinador…','Procurando o Assinador…'); verifyAndConnect(); }
    loadPreview().catch(function(e){ status('Erro na pré-visualização: '+e.message); });
    window.addEventListener('resize', function(){ if(seal.page!=null) drawSeal(); });
    if(window.ResizeObserver){ var roT=null; new ResizeObserver(function(){ clearTimeout(roT); roT=setTimeout(function(){ if(seal.page!=null) drawSeal(); },60); }).observe(el('pages')); }
})();
</script>
<?php endif; ?>
</body>
</html>
