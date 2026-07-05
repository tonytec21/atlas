<?php /* complementos_index/anexos_modal.php — modal de anexos (upload + lista + viewer) */ ?>
<style>
    /* ---- Ações padronizadas ---- */
    .acoes-wrap{ display:flex; gap:6px; flex-wrap:wrap; align-items:center; justify-content:center; }
    .acoes-wrap .btn-icon{ width:34px !important; height:32px !important; min-width:34px; padding:0 !important; margin:0 !important;
        display:inline-flex !important; align-items:center; justify-content:center; line-height:1; vertical-align:middle; border-radius:8px; box-sizing:border-box; }
    .acoes-wrap .btn-icon > i{ line-height:1; font-size:.95rem; }
    .acao-assinada{ background:#16a34a !important; color:#fff !important; cursor:default; border:0; }

    /* ---- Modal anexos ---- */
    #anexosModal .modal-content{ border:0; border-radius:16px; overflow:hidden; }
    #anexosModal .modal-header{ background:linear-gradient(135deg,#2563eb,#4f46e5); color:#fff; border:0; padding:16px 20px; align-items:center; }
    .ax-head-l{ display:flex; align-items:center; gap:12px; min-width:0; }
    .ax-head-ic{ width:40px;height:40px;border-radius:11px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:1.05rem; flex:0 0 auto; }
    .ax-head-t{ font-weight:800; font-size:1.06rem; line-height:1.1; } .ax-head-s{ font-size:.82rem; opacity:.92; }
    .ax-close{ border:0; background:rgba(255,255,255,.18); color:#fff; width:36px;height:36px;border-radius:10px; display:flex;align-items:center;justify-content:center; cursor:pointer; transition:.15s; font-size:1rem; flex:0 0 auto; }
    .ax-close:hover{ background:rgba(255,255,255,.34); transform:rotate(90deg); }
    .ax-dz{ border:2.5px dashed #c7d2fe; border-radius:16px; background:linear-gradient(180deg,#f8faff,#eef3ff); padding:26px 18px; text-align:center; transition:.2s; cursor:pointer; }
    .ax-dz:hover{ border-color:#2563eb; background:#eef3ff; }
    .ax-dz.drag{ border-color:#2563eb; background:#e0e9ff; transform:scale(1.01); box-shadow:0 10px 24px rgba(37,99,235,.18); }
    .ax-dz .ic{ width:56px;height:56px;border-radius:50%; background:#dbeafe; color:#2563eb; display:flex;align-items:center;justify-content:center; font-size:1.5rem; margin:0 auto 10px; }
    .ax-dz .t{ font-weight:700; color:#1e293b; } .ax-dz .s{ color:#64748b; font-size:.84rem; margin-top:3px; }
    .ax-queue{ margin-top:12px; display:flex; flex-direction:column; gap:8px; }
    .ax-qitem{ display:flex; align-items:center; gap:12px; padding:9px 12px; border:1px solid #eef1f6; border-radius:12px; }
    .ax-qitem .fi{ width:36px;height:36px;border-radius:9px; display:flex;align-items:center;justify-content:center; color:#fff; flex:0 0 auto; }
    .ax-qitem .nm{ font-weight:600; font-size:.9rem; word-break:break-all; } .ax-qitem .mt{ color:#94a3b8; font-size:.78rem; }
    .ax-qitem .bar{ height:6px; border-radius:99px; background:#e2e8f0; overflow:hidden; margin-top:5px; } .ax-qitem .bar>i{ display:block; height:100%; width:0; background:linear-gradient(90deg,#2563eb,#4f46e5); transition:width .2s; }
    .ax-list{ display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:10px; }
    .ax-item{ display:flex; align-items:center; gap:11px; padding:11px; border:1px solid #eef1f6; border-radius:14px; transition:.15s; background:#fff; }
    .ax-item:hover{ box-shadow:0 8px 20px rgba(15,23,42,.08); transform:translateY(-1px); }
    .ax-item .fi{ width:42px;height:42px;border-radius:11px; display:flex;align-items:center;justify-content:center; color:#fff; font-size:1.05rem; flex:0 0 auto; }
    .ax-item .meta{ min-width:0; flex:1; } .ax-item .meta .nm{ font-weight:700; font-size:.88rem; word-break:break-word; } .ax-item .meta .sub{ color:#94a3b8; font-size:.76rem; }
    .ax-item .acts{ display:flex; gap:5px; } .ax-item .acts button{ border:0; background:#eef2f7; color:#334155; width:32px;height:32px;border-radius:9px; display:flex;align-items:center;justify-content:center; cursor:pointer; }
    .ax-item .acts .v:hover{ background:#dbeafe; color:#2563eb; } .ax-item .acts .o:hover{ background:#dcfce7; color:#16a34a; } .ax-item .acts .d:hover{ background:#dbeafe; color:#2563eb; } .ax-item .acts .x:hover{ background:#fee2e2; color:#b91c1c; }
    .ax-empty{ text-align:center; color:#94a3b8; padding:22px; }
    #axViewerBody{ background:#f4f6fa; border-radius:12px; overflow:hidden; }
    #axViewerBody iframe, #axViewerBody img{ width:100%; height:70vh; border:0; display:block; object-fit:contain; background:#f4f6fa; }
    @media (max-width:576px){ #axViewerBody iframe, #axViewerBody img{ height:62vh; } }
    body.dark-mode .ax-dz{ background:#1b1e21; border-color:#334155; } body.dark-mode .ax-item,body.dark-mode .ax-qitem{ background:#23272a; border-color:rgba(255,255,255,.07); }
</style>

<div class="modal fade" id="anexosModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
    <div class="modal-content">
      <div class="modal-header">
        <div class="ax-head-l">
          <span class="ax-head-ic"><i class="fa fa-paperclip"></i></span>
          <div style="min-width:0">
            <div class="ax-head-t">Anexos</div>
            <div class="ax-head-s">Nota <span id="axNum"></span></div>
          </div>
        </div>
        <button type="button" class="ax-close" data-bs-dismiss="modal" aria-label="Fechar"><i class="fa fa-times"></i></button>
      </div>
      <div class="modal-body">
        <!-- Tela: upload + lista -->
        <div id="axScreenList">
          <div id="axDz" class="ax-dz">
            <div class="ic"><i class="fa fa-cloud-upload"></i></div>
            <div class="t">Arraste comprovantes aqui ou clique para selecionar</div>
            <div class="s">PDF, imagens, Word, Excel, TXT, ZIP, XML — até 20 MB por arquivo</div>
            <input id="axFile" type="file" multiple hidden>
          </div>
          <input id="axDesc" class="form-control mt-3" placeholder="Descrição (opcional) — ex.: Comprovante de pagamento">
          <div id="axQueue" class="ax-queue"></div>
          <hr>
          <div id="axList" class="ax-list"><div class="ax-empty">Carregando…</div></div>
        </div>
        <!-- Tela: viewer -->
        <div id="axScreenView" style="display:none">
          <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="axBack"><i class="fa fa-arrow-left"></i> Voltar</button>
            <span class="fw-bold text-truncate" id="axViewName" style="max-width:45%"></span>
            <span class="ms-auto d-flex gap-2">
              <a class="btn btn-outline-primary btn-sm" id="axOpenTab" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Nova aba</a>
              <a class="btn btn-primary btn-sm" id="axDownload"><i class="fa fa-download"></i> Baixar</a>
            </span>
          </div>
          <div id="axViewerBody"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
    "use strict";
    var CSRF = <?php echo json_encode(nd_csrf_token()); ?>;
    var numero = null, modal = null;
    function el(id){ return document.getElementById(id); }
    function fmtSize(b){ if(!b) return '0 B'; var u=['B','KB','MB','GB'],i=Math.floor(Math.log(b)/Math.log(1024)); return (b/Math.pow(1024,i)).toFixed(i?1:0)+' '+u[i]; }
    function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }
    function meta(name,mime){
        var n=((name||'')+' '+(mime||'')).toLowerCase(), c='#64748b', ic='fa-file', k='other';
        if(/\.pdf($|\s)|pdf/.test(n)){ c='#ef4444'; ic='fa-file-pdf-o'; k='pdf'; }
        else if(/\.(png|jpe?g|gif|webp)($|\s)|image\//.test(n)){ c='#0ea5e9'; ic='fa-file-image-o'; k='image'; }
        else if(/\.(docx?|)($|\s)|word/.test(n) && /doc|word/.test(n)){ c='#2563eb'; ic='fa-file-word-o'; }
        else if(/xls|sheet|excel|csv/.test(n)){ c='#16a34a'; ic='fa-file-excel-o'; }
        else if(/zip/.test(n)){ c='#a16207'; ic='fa-file-archive-o'; }
        else if(/xml|p7s/.test(n)){ c='#7c3aed'; ic='fa-file-code-o'; }
        else if(/txt|text/.test(n)){ c='#475569'; ic='fa-file-text-o'; }
        return {c:c, ic:ic, kind:k};
    }

    /* screens */
    function showList(){ el('axScreenList').style.display=''; el('axScreenView').style.display='none'; }
    function showView(){ el('axScreenList').style.display='none'; el('axScreenView').style.display=''; }
    el('axBack').addEventListener('click', showList);

    /* abrir modal */
    window.abrirAnexos = function(n){
        numero=n; el('axNum').textContent=n;
        el('axQueue').innerHTML=''; el('axDesc').value=''; showList();
        el('axList').innerHTML='<div class="ax-empty">Carregando…</div>';
        if(!modal) modal=new bootstrap.Modal(el('anexosModal'));
        modal.show(); carregar();
    };

    /* dropzone */
    var dz=el('axDz'), file=el('axFile');
    dz.addEventListener('click', function(e){ if(e.target.tagName!=='INPUT') file.click(); });
    ['dragenter','dragover'].forEach(function(ev){ dz.addEventListener(ev,function(e){ e.preventDefault(); dz.classList.add('drag'); }); });
    ['dragleave','drop'].forEach(function(ev){ dz.addEventListener(ev,function(e){ e.preventDefault(); dz.classList.remove('drag'); }); });
    dz.addEventListener('drop', function(e){ if(e.dataTransfer&&e.dataTransfer.files) handle(e.dataTransfer.files); });
    file.addEventListener('change', function(){ handle(this.files); this.value=''; });
    function handle(files){ for(var i=0;i<files.length;i++) uploadOne(files[i]); }

    function uploadOne(f){
        var mi=meta(f.name,f.type);
        var row=document.createElement('div'); row.className='ax-qitem';
        row.innerHTML='<div class="fi" style="background:'+mi.c+'"><i class="fa '+mi.ic+'"></i></div>'+
            '<div style="flex:1;min-width:0"><div class="nm">'+esc(f.name)+'</div><div class="mt"><span class="st">Enviando…</span> · '+fmtSize(f.size)+'</div><div class="bar"><i></i></div></div>';
        el('axQueue').prepend(row);
        var fill=row.querySelector('.bar>i'), st=row.querySelector('.st');
        var fd=new FormData(); fd.append('csrf',CSRF); fd.append('numero',numero); fd.append('descricao',el('axDesc').value||''); fd.append('arquivos[]',f);
        var xhr=new XMLHttpRequest(); xhr.open('POST','anexos_upload.php',true);
        xhr.upload.onprogress=function(e){ if(e.lengthComputable) fill.style.width=Math.round(e.loaded/e.total*100)+'%'; };
        xhr.onload=function(){
            var r; try{ r=JSON.parse(xhr.responseText); }catch(e){ r={status:'error',message:'Resposta inválida'}; }
            if(r.status==='success'){ st.textContent='Enviado'; fill.style.width='100%'; fill.style.background='#16a34a'; setTimeout(function(){ row.remove(); },900); carregar(); }
            else { st.textContent='Erro: '+(r.message||'falha'); st.style.color='#b91c1c'; fill.style.background='#ef4444'; }
        };
        xhr.onerror=function(){ st.textContent='Falha de rede'; st.style.color='#b91c1c'; };
        xhr.send(fd);
    }

    /* lista */
    async function carregar(){
        try{
            var r=await fetch('anexos_listar.php?numero='+encodeURIComponent(numero),{credentials:'same-origin'});
            var j=await r.json();
            var box=el('axList');
            if(j.status!=='success'){ box.innerHTML='<div class="ax-empty">Erro ao listar.</div>'; return; }
            if(!j.anexos.length){ box.innerHTML='<div class="ax-empty"><i class="fa fa-inbox" style="font-size:1.5rem"></i><br>Nenhum anexo ainda.</div>'; return; }
            box.innerHTML='';
            j.anexos.forEach(function(a){
                var mi=meta(a.nome_original,a.mime), previa=(mi.kind==='pdf'||mi.kind==='image');
                var acts='';
                if(previa){
                    acts+='<button class="v" title="Visualizar"><i class="fa fa-eye"></i></button>';
                    acts+='<button class="o" title="Abrir em nova aba"><i class="fa fa-external-link"></i></button>';
                } else {
                    acts+='<button class="d" title="Baixar"><i class="fa fa-download"></i></button>';
                }
                acts+='<button class="x" title="Excluir"><i class="fa fa-trash"></i></button>';
                var item=document.createElement('div'); item.className='ax-item';
                item.innerHTML='<div class="fi" style="background:'+mi.c+'"><i class="fa '+mi.ic+'"></i></div>'+
                    '<div class="meta"><div class="nm">'+esc(a.nome_original)+'</div><div class="sub">'+fmtSize(a.tamanho)+' · '+esc(a.enviado_em)+(a.descricao?(' · '+esc(a.descricao)):'')+'</div></div>'+
                    '<div class="acts">'+acts+'</div>';
                var v=item.querySelector('.v'), o=item.querySelector('.o'), d=item.querySelector('.d'), x=item.querySelector('.x');
                if(v) v.addEventListener('click',function(){ visualizar(a, mi.kind); });
                if(o) o.addEventListener('click',function(){ window.open('anexos_baixar.php?id='+a.id+'&inline=1','_blank','noopener'); });
                if(d) d.addEventListener('click',function(){ window.location='anexos_baixar.php?id='+a.id; });
                x.addEventListener('click',function(){ excluir(a.id); });
                box.appendChild(item);
            });
        }catch(e){ el('axList').innerHTML='<div class="ax-empty">Erro ao listar.</div>'; }
    }

    /* viewer interno */
    function visualizar(a, kind){
        el('axViewName').textContent=a.nome_original;
        el('axOpenTab').href='anexos_baixar.php?id='+a.id+'&inline=1';
        el('axDownload').href='anexos_baixar.php?id='+a.id;
        var body=el('axViewerBody'); body.innerHTML='';
        var url='anexos_baixar.php?id='+a.id+'&inline=1';
        if(kind==='image'){ var img=document.createElement('img'); img.src=url; img.alt=a.nome_original; body.appendChild(img); }
        else { var ifr=document.createElement('iframe'); ifr.src=url; ifr.title=a.nome_original; body.appendChild(ifr); }
        showView();
    }

    async function excluir(id){
        var ok = window.Swal ? (await Swal.fire({icon:'warning',title:'Excluir anexo?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc3545'})).isConfirmed : confirm('Excluir anexo?');
        if(!ok) return;
        var body=new URLSearchParams({csrf:CSRF,id:id}).toString();
        var r=await fetch('anexos_excluir.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'});
        var j=await r.json();
        if(j.status==='success'){ carregar(); } else if(window.Swal){ Swal.fire('Erro', j.message||'Falha ao excluir','error'); }
    }
})();
</script>
