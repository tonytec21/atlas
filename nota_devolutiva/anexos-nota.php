<?php
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_nota_config.php';
nd_ensure_schema();

$numero = isset($_GET['numero']) ? trim((string)$_GET['numero']) : '';
if ($numero === '') { header('Location: index.php'); exit; }

$conn = nd_db();
$stmt = $conn->prepare("SELECT numero, titulo, apresentante, protocolo FROM notas_devolutivas WHERE numero = ? LIMIT 1");
$stmt->bind_param('s', $numero); $stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { http_response_code(404); echo 'Nota não encontrada.'; exit; }
$nota = $res->fetch_assoc(); $stmt->close();
$CSRF = nd_csrf_token();
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas - Anexos da nota <?php echo h($numero); ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root{ --az:#2563eb; --az2:#4f46e5; }
    #main .container{ padding-bottom:120px; }
    .ax-hero{ display:flex; align-items:center; gap:14px; margin:6px 0 18px; }
    .ax-hero .ic{ width:46px;height:46px;border-radius:13px;background:linear-gradient(135deg,var(--az),var(--az2));color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 18px rgba(37,99,235,.35);font-size:1.1rem; }
    .ax-hero h3{ margin:0;font-weight:800; } .ax-hero .sub{ color:#64748b;font-size:.88rem; }
    .ax-card{ background:#fff; border:1px solid #e5e9f0; border-radius:16px; box-shadow:0 10px 30px rgba(15,23,42,.06); margin-bottom:18px; }
    .ax-card .bd{ padding:18px; }
    body.dark-mode .ax-card{ background:#23272a; border-color:rgba(255,255,255,.07); }

    .dropzone{ border:2.5px dashed #c7d2fe; border-radius:16px; background:linear-gradient(180deg,#f8faff,#eef3ff); padding:34px 20px; text-align:center; transition:.2s; cursor:pointer; }
    .dropzone:hover{ border-color:var(--az); background:#eef3ff; }
    .dropzone.drag{ border-color:var(--az); background:#e0e9ff; transform:scale(1.01); box-shadow:0 10px 24px rgba(37,99,235,.18); }
    .dropzone .dz-ic{ width:64px;height:64px;border-radius:50%; background:#dbeafe; color:var(--az); display:flex;align-items:center;justify-content:center; font-size:1.8rem; margin:0 auto 12px; }
    .dropzone .dz-t{ font-weight:700; color:#1e293b; } .dropzone .dz-s{ color:#64748b; font-size:.86rem; margin-top:4px; }
    .dropzone .dz-btn{ margin-top:14px; }
    body.dark-mode .dropzone{ background:#1b1e21; border-color:#334155; }

    .queue{ margin-top:14px; display:flex; flex-direction:column; gap:8px; }
    .qitem{ display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid #eef1f6; border-radius:12px; background:#fff; }
    .qitem .fi{ width:38px;height:38px;border-radius:9px; display:flex;align-items:center;justify-content:center; color:#fff; flex:0 0 auto; }
    .qitem .nm{ font-weight:600; font-size:.9rem; word-break:break-all; } .qitem .mt{ color:#94a3b8; font-size:.78rem; }
    .qitem .bar{ height:6px; border-radius:99px; background:#e2e8f0; overflow:hidden; margin-top:5px; }
    .qitem .bar > i{ display:block; height:100%; width:0; background:linear-gradient(90deg,var(--az),var(--az2)); transition:width .2s; }
    body.dark-mode .qitem{ background:#23272a; border-color:rgba(255,255,255,.07); }

    .alist{ display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:12px; }
    .anexo{ display:flex; align-items:center; gap:12px; padding:12px; border:1px solid #eef1f6; border-radius:14px; background:#fff; transition:.15s; }
    .anexo:hover{ box-shadow:0 8px 20px rgba(15,23,42,.08); transform:translateY(-1px); }
    .anexo .fi{ width:44px;height:44px;border-radius:11px; display:flex;align-items:center;justify-content:center; color:#fff; font-size:1.1rem; flex:0 0 auto; }
    .anexo .meta{ min-width:0; flex:1; } .anexo .meta .nm{ font-weight:700; font-size:.9rem; word-break:break-word; }
    .anexo .meta .sub{ color:#94a3b8; font-size:.78rem; } .anexo .acts{ display:flex; gap:6px; }
    .anexo .acts a,.anexo .acts button{ border:0; background:#eef2f7; color:#334155; width:34px;height:34px;border-radius:9px; display:flex;align-items:center;justify-content:center; cursor:pointer; text-decoration:none; }
    .anexo .acts .del:hover{ background:#fee2e2; color:#b91c1c; } .anexo .acts .dl:hover{ background:#dbeafe; color:var(--az); }
    body.dark-mode .anexo{ background:#23272a; border-color:rgba(255,255,255,.07); }
    .empty{ text-align:center; color:#94a3b8; padding:26px; }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
    <div class="container">
        <div class="ax-hero">
            <div class="ic"><i class="fa fa-paperclip"></i></div>
            <div style="flex:1;min-width:0">
                <h3>Anexos da nota <?php echo h($numero); ?></h3>
                <div class="sub"><?php echo h($nota['titulo'] ?? ''); ?><?php echo $nota['protocolo'] ? ' — Protocolo ' . h($nota['protocolo']) : ''; ?></div>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left"></i> Voltar</a>
        </div>

        <div class="ax-card"><div class="bd">
            <div id="dz" class="dropzone">
                <div class="dz-ic"><i class="fa fa-cloud-upload"></i></div>
                <div class="dz-t">Arraste comprovantes aqui ou clique para selecionar</div>
                <div class="dz-s">PDF, imagens, Word, Excel, TXT, ZIP, XML — até 20 MB por arquivo</div>
                <button type="button" class="btn btn-primary dz-btn"><i class="fa fa-folder-open"></i> Escolher arquivos</button>
                <input id="fileInput" type="file" multiple hidden>
            </div>
            <div class="mt-3">
                <input id="descricao" class="form-control" placeholder="Descrição (opcional) — ex.: Comprovante de pagamento">
            </div>
            <div id="queue" class="queue"></div>
        </div></div>

        <div class="ax-card"><div class="bd">
            <h5 style="font-weight:800;margin-bottom:14px"><i class="fa fa-folder-open text-primary"></i> Anexos existentes</h5>
            <div id="lista" class="alist"><div class="empty">Carregando…</div></div>
        </div></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
<script>
(function(){
    "use strict";
    var NUMERO=<?php echo json_encode($numero); ?>, CSRF=<?php echo json_encode($CSRF); ?>;
    var dz=document.getElementById('dz'), input=document.getElementById('fileInput'), queue=document.getElementById('queue'), lista=document.getElementById('lista');

    function fmtSize(b){ if(!b)return '0 B'; var u=['B','KB','MB','GB'],i=Math.floor(Math.log(b)/Math.log(1024)); return (b/Math.pow(1024,i)).toFixed(i?1:0)+' '+u[i]; }
    function iconFor(nameOrMime){
        var n=(nameOrMime||'').toLowerCase(), c='#64748b', ic='fa-file';
        if(/pdf/.test(n)){c='#ef4444';ic='fa-file-pdf-o';}
        else if(/(png|jpg|jpeg|gif|webp|image)/.test(n)){c='#0ea5e9';ic='fa-file-image-o';}
        else if(/(doc|word)/.test(n)){c='#2563eb';ic='fa-file-word-o';}
        else if(/(xls|sheet|excel|csv)/.test(n)){c='#16a34a';ic='fa-file-excel-o';}
        else if(/zip/.test(n)){c='#a16207';ic='fa-file-archive-o';}
        else if(/(xml|p7s)/.test(n)){c='#7c3aed';ic='fa-file-code-o';}
        else if(/txt/.test(n)){c='#475569';ic='fa-file-text-o';}
        return {c:c,ic:ic};
    }
    function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
    function previewable(name,mime){var n=((name||'')+' '+(mime||'')).toLowerCase();return /\.(pdf|png|jpe?g|gif|webp)($|\s)|pdf|image\//.test(n);}

    // ---- dropzone ----
    dz.addEventListener('click',function(e){ if(e.target.tagName!=='INPUT') input.click(); });
    dz.querySelector('.dz-btn').addEventListener('click',function(e){e.stopPropagation();input.click();});
    ['dragenter','dragover'].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.add('drag');});});
    ['dragleave','drop'].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.remove('drag');});});
    dz.addEventListener('drop',function(e){ if(e.dataTransfer&&e.dataTransfer.files) handleFiles(e.dataTransfer.files); });
    input.addEventListener('change',function(){ handleFiles(this.files); this.value=''; });

    function handleFiles(files){ for(var i=0;i<files.length;i++) uploadOne(files[i]); }

    function uploadOne(file){
        var info=iconFor(file.name);
        var row=document.createElement('div'); row.className='qitem';
        row.innerHTML='<div class="fi" style="background:'+info.c+'"><i class="fa '+info.ic+'"></i></div>'+
            '<div style="flex:1;min-width:0"><div class="nm">'+esc(file.name)+'</div><div class="mt"><span class="st">Enviando…</span> · '+fmtSize(file.size)+'</div>'+
            '<div class="bar"><i></i></div></div>';
        queue.prepend(row);
        var barFill=row.querySelector('.bar > i'), st=row.querySelector('.st');

        var fd=new FormData();
        fd.append('csrf',CSRF); fd.append('numero',NUMERO);
        fd.append('descricao',document.getElementById('descricao').value||'');
        fd.append('arquivos[]',file);

        var xhr=new XMLHttpRequest();
        xhr.open('POST','anexos_upload.php',true);
        xhr.upload.onprogress=function(e){ if(e.lengthComputable){ barFill.style.width=Math.round(e.loaded/e.total*100)+'%'; } };
        xhr.onload=function(){
            var r; try{ r=JSON.parse(xhr.responseText); }catch(e){ r={status:'error',message:'Resposta inválida'}; }
            if(r.status==='success'){ st.textContent='Enviado'; barFill.style.width='100%'; barFill.style.background='#16a34a'; setTimeout(function(){row.remove();},900); carregar(); }
            else { st.textContent='Erro: '+(r.message||'falha'); st.style.color='#b91c1c'; barFill.style.background='#ef4444'; }
        };
        xhr.onerror=function(){ st.textContent='Falha de rede'; st.style.color='#b91c1c'; };
        xhr.send(fd);
    }

    // ---- listar ----
    async function carregar(){
        try{
            var r=await fetch('anexos_listar.php?numero='+encodeURIComponent(NUMERO),{credentials:'same-origin'});
            var j=await r.json();
            if(j.status!=='success'){ lista.innerHTML='<div class="empty">Erro ao listar.</div>'; return; }
            if(!j.anexos.length){ lista.innerHTML='<div class="empty"><i class="fa fa-inbox" style="font-size:1.6rem"></i><br>Nenhum anexo ainda.</div>'; return; }
            lista.innerHTML='';
            j.anexos.forEach(function(a){
                var info=iconFor(a.nome_original+' '+(a.mime||''));
                var card=document.createElement('div'); card.className='anexo';
                card.innerHTML='<div class="fi" style="background:'+info.c+'"><i class="fa '+info.ic+'"></i></div>'+
                    '<div class="meta"><div class="nm">'+esc(a.nome_original)+'</div>'+
                    '<div class="sub">'+fmtSize(a.tamanho)+' · '+esc(a.enviado_em)+(a.descricao?(' · '+esc(a.descricao)):'')+'</div></div>'+
                    '<div class="acts">'+
                    (previewable(a.nome_original,a.mime)
                        ? '<a class="dl" title="Visualizar em nova aba" target="_blank" rel="noopener" href="anexos_baixar.php?id='+a.id+'&inline=1"><i class="fa fa-eye"></i></a>'
                        : '<a class="dl" title="Baixar" href="anexos_baixar.php?id='+a.id+'"><i class="fa fa-download"></i></a>')+
                    '<button class="del" title="Excluir" data-id="'+a.id+'"><i class="fa fa-trash"></i></button></div>';
                lista.appendChild(card);
            });
            lista.querySelectorAll('.del').forEach(function(b){ b.addEventListener('click',function(){ excluir(this.dataset.id); }); });
        }catch(e){ lista.innerHTML='<div class="empty">Erro ao listar.</div>'; }
    }
    async function excluir(id){
        var ok = window.Swal ? (await Swal.fire({icon:'warning',title:'Excluir anexo?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc3545'})).isConfirmed : confirm('Excluir anexo?');
        if(!ok) return;
        var body=new URLSearchParams({csrf:CSRF,id:id}).toString();
        var r=await fetch('anexos_excluir.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'});
        var j=await r.json();
        if(j.status==='success'){ carregar(); } else { if(window.Swal)Swal.fire('Erro',j.message||'Falha ao excluir','error'); }
    }

    carregar();
})();
</script>
</body>
</html>
