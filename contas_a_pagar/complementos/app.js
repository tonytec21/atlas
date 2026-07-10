/* complementos/app.js — Contas a Pagar (Atlas) */
(function(){
  "use strict";
  var CAP = window.CAP || {}, CSRF = CAP.csrf;
  var contaModal, anexosModal, configModal;
  function $(s){ return document.querySelector(s); }
  function bsModal(id){ return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }
  function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }
  function fmtSize(b){ if(!b) return '0 B'; var u=['B','KB','MB','GB'],i=Math.floor(Math.log(b)/Math.log(1024)); return (b/Math.pow(1024,i)).toFixed(i?1:0)+' '+u[i]; }

  async function postForm(url, data){
    data.csrf = CSRF;
    var r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(data).toString(), credentials:'same-origin' });
    var t = await r.text(); var j;
    try{ j = JSON.parse(t); }catch(e){ throw new Error('Resposta inválida: '+t.slice(0,150)); }
    if (j && j.acesso_negado) {
      if (window.Swal) Swal.fire({icon:'error',title:'Acesso negado',text:j.message||'Sem permissão.'}).then(function(){ location.href='index.php'; });
      throw new Error(j.message || 'Acesso negado.');
    }
    return j;
  }
  function toast(icon, title){ if(window.Swal) Swal.fire({icon:icon,title:title,toast:true,position:'top-end',showConfirmButton:false,timer:2200}); }

  /* ---------------- máscara de moeda (BRL) ---------------- */
  function maskMoney(el){
    function fmt(){ var v=el.value.replace(/\D/g,''); v=(parseInt(v||'0',10)/100).toFixed(2); v=v.replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); el.value=v; }
    el.addEventListener('input', fmt);
  }
  function setMoney(el, num){ var v=(parseFloat(num||0)).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); el.value=v; }

  /* ---------------- gráficos ---------------- */
  function initCharts(){
    if(typeof Chart==='undefined') return;
    Chart.defaults.font.family="Inter, system-ui, sans-serif";
    var s=CAP.chartStatus||{aVencer:0,vencidas:0};
    new Chart($('#chartStatus'),{ type:'doughnut',
      data:{ labels:['A vencer','Vencidas'], datasets:[{ data:[s.aVencer,s.vencidas], backgroundColor:['#4f46e5','#ef4444'], borderWidth:0 }] },
      options:{ plugins:{legend:{position:'bottom'}}, cutout:'62%' } });
    var c=CAP.chartCat||{labels:[],vals:[]};
    new Chart($('#chartCat'),{ type:'bar',
      data:{ labels:c.labels, datasets:[{ label:'Em aberto', data:c.vals, backgroundColor:'#2563eb', borderRadius:6 }] },
      options:{ plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback:function(v){ return 'R$ '+v.toLocaleString('pt-BR'); } } } } } });
    var e=CAP.chartEvol||{labels:[],vals:[]};
    new Chart($('#chartEvol'),{ type:'line',
      data:{ labels:e.labels, datasets:[{ label:'Pago', data:e.vals, borderColor:'#16a34a', backgroundColor:'rgba(22,163,74,.15)', fill:true, tension:.35, pointRadius:3 }] },
      options:{ plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback:function(v){ return 'R$ '+v.toLocaleString('pt-BR'); } } } } } });
  }

  /* ---------------- DataTable ---------------- */
  function initTable(){
    if(!window.jQuery || !jQuery.fn.DataTable) return;
    jQuery('#tabelaContas').DataTable({
      language:{ url:'../style/Portuguese-Brasil.json' },
      order:[[0,'asc']], autoWidth:false, pageLength:25,
      lengthMenu:[[10,25,50,100,-1],[10,25,50,100,'Todos']]
    });
  }

  /* ---------------- conta: novo/editar/salvar ---------------- */
  window.capNovaConta = function(){
    $('#contaForm').reset(); $('#c_id').value=''; $('#c_valor').value='';
    $('#contaModalTitle').textContent='Nova conta';
    $('#c_recorrencia').value='Nenhuma';
  };
  window.capEditar = async function(id){
    try{
      var r = await fetch('get_conta.php?id='+id, {credentials:'same-origin'}); var j = await r.json();
      if(!j.success) throw new Error(j.message||'Falha ao carregar.');
      var c=j.conta;
      $('#c_id').value=c.id; $('#c_titulo').value=c.titulo||''; setMoney($('#c_valor'), c.valor);
      $('#c_venc').value=(c.data_vencimento||'').substr(0,10);
      $('#c_categoria').value=c.categoria||''; $('#c_recorrencia').value=c.recorrencia||'Nenhuma';
      $('#c_fornecedor').value=c.fornecedor||''; $('#c_descricao').value=c.descricao||'';
      $('#contaModalTitle').textContent='Editar conta';
      bsModal('contaModal').show();
    }catch(e){ if(window.Swal) Swal.fire('Erro', e.message,'error'); }
  };
  window.capSalvarConta = async function(){
    var form=$('#contaForm');
    if(!form.reportValidity()) return;
    var id=$('#c_id').value.trim();
    var data={ id:id, titulo:$('#c_titulo').value, valor:$('#c_valor').value, data_vencimento:$('#c_venc').value,
               categoria:$('#c_categoria').value, recorrencia:$('#c_recorrencia').value, fornecedor:$('#c_fornecedor').value, descricao:$('#c_descricao').value };
    var btn=$('#contaSalvarBtn'); btn.disabled=true;
    try{
      var r=await postForm(id? 'atualizar_conta.php':'salvar_conta.php', data);
      if(!r.success) throw new Error(r.message||'Falha ao salvar.');
      bsModal('contaModal').hide(); toast('success', r.message||'Salvo'); setTimeout(function(){ location.reload(); }, 700);
    }catch(e){ if(window.Swal) Swal.fire('Erro', e.message,'error'); btn.disabled=false; }
  };

  /* ---------------- pagamento (contas virtuais) ---------------- */
  function brl(v){ return 'R$ ' + Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2, maximumFractionDigits:2}); }
  function contaDaForma(){ var o=$('#pg_forma').options[$('#pg_forma').selectedIndex]; return o ? (o.getAttribute('data-conta')||'') : ''; }
  function atualizaSaldoBox(){
    var box=$('#pg_saldo_box'), txt=$('#pg_saldo_txt'), conta=contaDaForma(), valor=parseFloat($('#pg_valor').value||'0');
    box.classList.remove('ok','neg');
    if(!conta){ txt.textContent='Esta forma não movimenta as contas virtuais.'; return; }
    var s=(CAP.saldos&&CAP.saldos[conta]) ? CAP.saldos[conta].saldo : 0;
    var nome=(CAP.contasNome&&CAP.contasNome[conta])||conta;
    var depois = s - valor;
    txt.textContent = nome + ': ' + brl(s) + ' → após o pagamento: ' + brl(depois);
    box.classList.add(depois < 0 ? 'neg' : 'ok');
  }
  window.capPagar = function(id, titulo, valor){
    $('#pg_id').value=parseInt(id,10)||0; $('#pg_valor').value=parseFloat(valor)||0;
    $('#pg_titulo').textContent=titulo||'';
    $('#pg_valor_fmt').textContent=brl(valor);
    $('#pg_data').value=CAP.hoje||'';
    $('#pg_forma').selectedIndex=0;
    atualizaSaldoBox();
    bsModal('pagarModal').show();
  };
  window.capConfirmarPagamento = async function(forcar){
    var btn=$('#pgConfirmBtn'); btn.disabled=true;
    var data={ id:$('#pg_id').value, forma_pagamento:$('#pg_forma').value, data_pagamento:$('#pg_data').value };
    if(forcar===true) data.forcar='1';
    try{
      var r=await postForm('definir_pago.php', data);
      if(!r.success && r.saldo_insuficiente){
        btn.disabled=false;
        var ok = window.Swal ? (await Swal.fire({icon:'warning',title:'Saldo insuficiente',
              html:'Conta <b>'+r.conta_nome+'</b><br>Disponível: <b>'+r.saldo_fmt+'</b><br>Valor: <b>'+r.valor_fmt+'</b><br><br>Deseja registrar mesmo assim (saldo ficará negativo)?',
              showCancelButton:true, confirmButtonText:'Registrar assim mesmo', cancelButtonText:'Cancelar', confirmButtonColor:'#d97706'})).isConfirmed
            : confirm(r.message + ' Registrar mesmo assim?');
        if(ok) return capConfirmarPagamento(true);
        return;
      }
      if(!r.success) throw new Error(r.message||'Falha.');
      bsModal('pagarModal').hide();
      if(window.Swal) Swal.fire({icon:'success',title:'Pagamento registrado',text:r.message,timer:2600,showConfirmButton:false});
      setTimeout(function(){ location.reload(); }, 1200);
    }catch(e){ if(window.Swal) Swal.fire('Erro', e.message,'error'); btn.disabled=false; }
  };

  window.capExcluir = async function(id){
    var ok = window.Swal ? (await Swal.fire({icon:'warning',title:'Excluir conta?',text:'Esta ação não pode ser desfeita.',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc3545'})).isConfirmed : confirm('Excluir conta?');
    if(!ok) return;
    try{ var r=await postForm('excluir_conta.php',{id:id}); if(!r.success) throw new Error(r.message||'Falha.'); toast('success','Excluída'); setTimeout(function(){ location.reload(); },600); }
    catch(e){ if(window.Swal) Swal.fire('Erro', e.message,'error'); }
  };

  /* ---------------- configurações ---------------- */
  window.capSalvarConfig = async function(){
    var f=$('#configForm'), data={};
    new FormData(f).forEach(function(v,k){ data[k]=v; });
    data.notif_ativo = $('#cfg_ativo').checked ? '1' : '';
    try{ var r=await postForm('config_salvar.php', data); if(!r.success) throw new Error(r.message||'Falha.'); toast('success','Configurações salvas'); bsModal('configModal').hide(); }
    catch(e){ if(window.Swal) Swal.fire('Erro', e.message,'error'); }
  };
  window.capTestarAlerta = async function(){
    var btn=$('#cfgTestBtn'); btn.disabled=true; var old=btn.innerHTML; btn.innerHTML='<i class="fa fa-spinner fa-spin"></i> Enviando…';
    try{
      // salva antes para usar as configs atuais
      var f=$('#configForm'), data={}; new FormData(f).forEach(function(v,k){ data[k]=v; }); data.notif_ativo=$('#cfg_ativo').checked?'1':'';
      await postForm('config_salvar.php', data);
      var r=await postForm('alertas_testar.php', {});
      if(window.Swal) Swal.fire(r.success?'OK':'Aviso', r.message||'Concluído', r.success?'success':'info');
    }catch(e){ if(window.Swal) Swal.fire('Erro', e.message,'error'); }
    finally{ btn.disabled=false; btn.innerHTML=old; }
  };

  /* ---------------- anexos ---------------- */
  var axConta=null;
  function metaFile(name,mime){
    var n=((name||'')+' '+(mime||'')).toLowerCase(), c='#64748b', ic='fa-file', k='other';
    if(/pdf/.test(n)){ c='#ef4444'; ic='fa-file-pdf-o'; k='pdf'; }
    else if(/(png|jpe?g|gif|webp|image\/)/.test(n)){ c='#0ea5e9'; ic='fa-file-image-o'; k='image'; }
    else if(/(doc|word)/.test(n)){ c='#2563eb'; ic='fa-file-word-o'; }
    else if(/(xls|sheet|excel|csv)/.test(n)){ c='#16a34a'; ic='fa-file-excel-o'; }
    else if(/zip/.test(n)){ c='#a16207'; ic='fa-file-archive-o'; }
    else if(/(xml|ofx)/.test(n)){ c='#7c3aed'; ic='fa-file-code-o'; }
    else if(/txt/.test(n)){ c='#475569'; ic='fa-file-text-o'; }
    return {c:c,ic:ic,kind:k};
  }
  function axShowList(){ $('#axScreenList').style.display=''; $('#axScreenView').style.display='none'; }
  function axShowView(){ $('#axScreenList').style.display='none'; $('#axScreenView').style.display=''; }

  window.capAnexos = function(id, titulo){
    axConta=id; $('#axSub').textContent = titulo ? ('Conta: '+titulo) : 'Comprovantes e documentos';
    $('#axQueue').innerHTML=''; $('#axDesc').value=''; axShowList();
    $('#axList').innerHTML='<div class="text-center text-muted py-3">Carregando…</div>';
    bsModal('anexosModal').show(); axCarregar();
  };
  async function axCarregar(){
    try{
      var r=await fetch('anexos_listar.php?conta_id='+encodeURIComponent(axConta), {credentials:'same-origin'}); var j=await r.json();
      var box=$('#axList');
      if(j.status!=='success'){ box.innerHTML='<div class="text-center text-muted py-3">Erro ao listar.</div>'; return; }
      if(!j.anexos.length){ box.innerHTML='<div class="text-center text-muted py-4"><i class="fa fa-inbox fa-2x"></i><br>Nenhum anexo ainda.</div>'; return; }
      box.innerHTML='';
      j.anexos.forEach(function(a){
        var mi=metaFile(a.nome_original,a.mime), previa=(mi.kind==='pdf'||mi.kind==='image'), acts='';
        if(previa){ acts+='<button class="v" title="Visualizar"><i class="fa fa-eye"></i></button><button class="o" title="Nova aba"><i class="fa fa-external-link"></i></button>'; }
        else { acts+='<button class="d" title="Baixar"><i class="fa fa-download"></i></button>'; }
        acts+='<button class="x" title="Excluir" style="color:#b91c1c"><i class="fa fa-trash"></i></button>';
        var it=document.createElement('div'); it.className='ax-item';
        it.innerHTML='<div class="fi" style="background:'+mi.c+'"><i class="fa '+mi.ic+'"></i></div>'+
          '<div style="min-width:0;flex:1"><div class="nm">'+esc(a.nome_original)+'</div><div class="sub">'+fmtSize(a.tamanho)+' · '+esc(a.enviado_em)+(a.descricao?(' · '+esc(a.descricao)):'')+'</div></div>'+
          '<div class="acts">'+acts+'</div>';
        var v=it.querySelector('.v'),o=it.querySelector('.o'),d=it.querySelector('.d'),x=it.querySelector('.x');
        if(v) v.onclick=function(){ axVer(a, mi.kind); };
        if(o) o.onclick=function(){ window.open('anexos_baixar.php?id='+a.id+'&inline=1','_blank','noopener'); };
        if(d) d.onclick=function(){ window.location='anexos_baixar.php?id='+a.id; };
        x.onclick=function(){ axExcluir(a.id); };
        box.appendChild(it);
      });
    }catch(e){ $('#axList').innerHTML='<div class="text-center text-muted py-3">Erro ao listar.</div>'; }
  }
  function axVer(a, kind){
    $('#axViewName').textContent=a.nome_original;
    $('#axOpenTab').href='anexos_baixar.php?id='+a.id+'&inline=1';
    $('#axDownload').href='anexos_baixar.php?id='+a.id;
    var body=$('#axViewerBody'), url='anexos_baixar.php?id='+a.id+'&inline=1'; body.innerHTML='';
    if(kind==='image'){ var img=document.createElement('img'); img.src=url; img.alt=a.nome_original; body.appendChild(img); }
    else { var ifr=document.createElement('iframe'); ifr.src=url; body.appendChild(ifr); }
    axShowView();
  }
  async function axExcluir(id){
    var ok = window.Swal ? (await Swal.fire({icon:'warning',title:'Excluir anexo?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc3545'})).isConfirmed : confirm('Excluir anexo?');
    if(!ok) return;
    var r=await postForm('anexos_excluir.php',{id:id}); if(r.status==='success') axCarregar(); else if(window.Swal) Swal.fire('Erro', r.message||'Falha','error');
  }
  function axUpload(file){
    var mi=metaFile(file.name,file.type);
    var row=document.createElement('div'); row.className='q';
    row.innerHTML='<div class="fi" style="width:34px;height:34px;border-radius:8px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;color:#fff;background:'+mi.c+'"><i class="fa '+mi.ic+'"></i></div>'+
      '<div style="flex:1;min-width:0"><div style="font-size:.86rem;font-weight:600">'+esc(file.name)+'</div><div class="text-muted" style="font-size:.76rem"><span class="st">Enviando…</span> · '+fmtSize(file.size)+'</div><div class="bar"><i></i></div></div>';
    $('#axQueue').prepend(row);
    var fill=row.querySelector('.bar>i'), st=row.querySelector('.st');
    var fd=new FormData(); fd.append('csrf',CSRF); fd.append('conta_id',axConta); fd.append('descricao',$('#axDesc').value||''); fd.append('arquivos[]',file);
    var xhr=new XMLHttpRequest(); xhr.open('POST','anexos_upload.php',true);
    xhr.upload.onprogress=function(e){ if(e.lengthComputable) fill.style.width=Math.round(e.loaded/e.total*100)+'%'; };
    xhr.onload=function(){ var r; try{ r=JSON.parse(xhr.responseText); }catch(e){ r={status:'error',message:'Resposta inválida'}; }
      if(r.status==='success'){ st.textContent='Enviado'; fill.style.width='100%'; fill.style.background='#16a34a'; setTimeout(function(){ row.remove(); },900); axCarregar(); }
      else { st.textContent='Erro: '+(r.message||'falha'); st.style.color='#b91c1c'; fill.style.background='#ef4444'; } };
    xhr.onerror=function(){ st.textContent='Falha de rede'; st.style.color='#b91c1c'; };
    xhr.send(fd);
  }
  function initAnexosDz(){
    var dz=$('#axDz'), file=$('#axFile'); if(!dz) return;
    dz.addEventListener('click', function(e){ if(e.target.tagName!=='INPUT') file.click(); });
    ['dragenter','dragover'].forEach(function(ev){ dz.addEventListener(ev,function(e){ e.preventDefault(); dz.classList.add('drag'); }); });
    ['dragleave','drop'].forEach(function(ev){ dz.addEventListener(ev,function(e){ e.preventDefault(); dz.classList.remove('drag'); }); });
    dz.addEventListener('drop', function(e){ if(e.dataTransfer&&e.dataTransfer.files) for(var i=0;i<e.dataTransfer.files.length;i++) axUpload(e.dataTransfer.files[i]); });
    file.addEventListener('change', function(){ for(var i=0;i<this.files.length;i++) axUpload(this.files[i]); this.value=''; });
    $('#axBack').addEventListener('click', axShowList);
  }

  /* ---------------- ligação dos botões da tabela (delegação) ---------------- */
  function initAcoes(){
    var tabela = document.getElementById('tabelaContas');
    if(!tabela) return;
    tabela.addEventListener('click', function(ev){
      var b = ev.target.closest('button'); if(!b) return;
      if(b.classList.contains('js-pagar'))   return capPagar(b.dataset.id, b.dataset.titulo, parseFloat(b.dataset.valor||'0'));
      if(b.classList.contains('js-editar'))  return capEditar(b.dataset.id);
      if(b.classList.contains('js-anexos'))  return capAnexos(b.dataset.id, b.dataset.titulo);
      if(b.classList.contains('js-excluir')) return capExcluir(b.dataset.id);
    });
  }

  /* ---------------- init ---------------- */
  function safe(nome, fn){ try{ fn(); }catch(e){ console.error('[contas] falha em '+nome+':', e); } }
  document.addEventListener('DOMContentLoaded', function(){
    // as ações (pagar/editar/anexos/excluir) são registradas primeiro e de forma isolada,
    // para que uma falha em gráficos/tabela nunca deixe os botões sem efeito.
    safe('acoes', initAcoes);
    safe('pagamento', function(){
      var pf=$('#pg_forma'); if(pf) pf.addEventListener('change', atualizaSaldoBox);
      var v=$('#c_valor'); if(v) maskMoney(v);
    });
    safe('anexos', initAnexosDz);
    safe('tabela', initTable);
    safe('graficos', initCharts);
  });
})();
