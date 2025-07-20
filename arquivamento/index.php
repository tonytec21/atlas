<?php
include(__DIR__ . '/session_check.php');
checkSession();
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Atlas - Arquivamentos</title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<style>
/* ---------- cards ---------- */
.card-ato{cursor:pointer;transition:.2s;border-radius:.75rem;min-height:210px}
.card-ato:hover{transform:translateY(-4px)}
.card-icon{font-size:2rem;color:#444;opacity:.55}
.card-ato .btn{margin-right:.35rem}
.readonly-field{background:#f8f9fa}
.d-none-important{display:none!important}

/* ---------- cores por atribuição ---------- */
/* LIGHT */
body.light-mode .rc     {background:#ffe5e0}
body.light-mode .ri     {background:#e0f7fa}
body.light-mode .rtd    {background:#e8e0ff}
body.light-mode .rcpj   {background:#e0ffe5}
body.light-mode .notas  {background:#fff5cc}
body.light-mode .protes {background:#fde6ff}
body.light-mode .cmar   {background:#e0f0ff}

/* DARK – tons mais fechados mantendo “pastel” */
body.dark-mode .rc     {background:#664b47;color:#f8f9fa}
body.dark-mode .ri     {background:#295d66;color:#f8f9fa}
body.dark-mode .rtd    {background:#4d4566;color:#f8f9fa}
body.dark-mode .rcpj   {background:#2d6640;color:#f8f9fa}
body.dark-mode .notas  {background:#665c2d;color:#f8f9fa}
body.dark-mode .protes {background:#663a66;color:#f8f9fa}
body.dark-mode .cmar   {background:#2d5266;color:#f8f9fa}

/* ---- anexos ---- */
.attach-grid{display:flex;flex-wrap:wrap;gap:.5rem}
body.light-mode .attach-item{
  width:120px;border:1px solid #dee2e6;border-radius:.6rem;padding:.6rem;
  text-align:center;background:#f8f9fa;cursor:pointer;transition:background .15s}
body.light-mode .attach-item:hover{background:#e9ecef}

body.dark-mode .attach-item{
  width:120px;border:1px solid #495057;border-radius:.6rem;padding:.6rem;
  text-align:center;background:#343a40;color:#f8f9fa;cursor:pointer;transition:background .15s}
body.dark-mode .attach-item:hover{background:#495057}

.attach-icon{font-size:2rem;margin-bottom:.25rem}
.attach-name{font-size:.75rem;word-break:break-word}
.modal-dialog{max-width:90%}
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
<div class="container">
<h3>Arquivamentos Cadastrados – Consulta e Gestão</h3><hr>

<!-- filtros ----------------------------------------------------------------- -->
<div class="row mb-3">
  <div class="col-md-4">
    <label for="atribuicao">Atribuição:</label>
    <select id="atribuicao" class="form-control">
      <option value="">Selecione</option>
      <option>Registro Civil</option><option>Registro de Imóveis</option>
      <option>Registro de Títulos e Documentos</option>
      <option>Registro Civil das Pessoas Jurídicas</option>
      <option>Notas</option><option>Protesto</option><option>Contratos Marítimos</option>
    </select>
  </div>
  <div class="col-md-4">
    <label for="categoria">Categoria</label>
    <select id="categoria" class="form-control"><option value="">Selecione</option></select>
  </div>
  <div class="col-md-4"><label for="cpf-cnpj">CPF/CNPJ</label><input id="cpf-cnpj" class="form-control" maxlength="14" inputmode="numeric"></div>
  <div class="col-md-6"><label for="nome">Nome</label><input id="nome" class="form-control"></div>
  <div class="col-md-2"><label for="livro">Livro</label><input id="livro" class="form-control"></div>
  <div class="col-md-2"><label for="folha">Folha</label><input id="folha" class="form-control"></div>
  <div class="col-md-2"><label for="termo">Termo/Ordem</label><input id="termo" class="form-control"></div>
  <div class="col-md-2"><label for="protocolo">Protocolo</label><input id="protocolo" class="form-control"></div>
  <div class="col-md-2"><label for="matricula">Matrícula</label><input id="matricula" class="form-control"></div>
  <div class="col-md-2"><label for="data-ato">Data do Ato</label><input id="data-ato" type="date" class="form-control"></div>
  <div class="col-md-6"><label for="descricao">Descrição e Detalhes</label><input id="descricao" class="form-control"></div>
  <div class="col-md-12">
    <button style="width:49.8%;margin-top:10px" id="filter-button" class="btn btn-primary"><i class="fa fa-filter"></i> Filtrar</button>
    <button style="width:49.8%;margin-top:10px" class="btn btn-success" onclick="location.href='cadastro.php'"><i class="fa fa-plus"></i> Adicionar</button>
  </div>
</div><hr>

<h5>Resultados da Pesquisa</h5>
<div id="cards-container" class="row"></div>
</div></div>

<!-- modal ------------------------------------------------------------------- -->
<div class="modal fade" id="anexosModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header">
  <h5 class="modal-title flex-grow-1">Dados do Ato</h5>
  <button id="generate-pdf-button" class="btn btn-primary"><i class="fa fa-print"></i> Capa de arquivamento</button>
  <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body">
  <dl class="row" id="meta-dl"></dl>
  <h5>Anexos</h5>
  <div id="view-anexos-list" class="attach-grid"></div><hr>
  <small class="text-muted">Cadastrado por: <span id="view-cadastrado-por"></span><br>Data de Cadastro: <span id="view-data-cadastro"></span></small><hr>
  <h6>Histórico de Modificações</h6>
  <ul id="view-modificacoes" class="list-unstyled mb-0"></ul>
</div>
</div></div></div>

<script src="../script/jquery-3.6.0.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/sweetalert2.js"></script>

<script>
/* ---------- helpers ---------- */
const nrm=t=>typeof t!=='string'?'':t.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
const fDate =d=>new Date(d+'T00:00:00').toLocaleDateString('pt-BR');
const fDate2=d=>new Date(d).toLocaleDateString('pt-BR');
const fDate3=d=>d.split(' ')[0].split('-').reverse().join('/');
const trunc =(str,len=60)=>str.length>len?str.slice(0,len-1)+'…':str;
/* mapeia atribuição → classe ------------------------------------ */
const attrClass={
 'Registro Civil':'rc',
 'Registro de Imóveis':'ri',
 'Registro de Títulos e Documentos':'rtd',
 'Registro Civil das Pessoas Jurídicas':'rcpj',
 'Notas':'notas',
 'Protesto':'protes',
 'Contratos Marítimos':'cmar'
};
function iconByExt(e){switch(e){
 case'pdf':return'fa-file-pdf-o';case'jpg':case'jpeg':case'png':case'gif':return'fa-file-image-o';
 case'doc':case'docx':return'fa-file-word-o';case'xls':case'xlsx':return'fa-file-excel-o';default:return'fa-file-o';}}
/* ---------- cards ---------- */
function renderCards(atos){
  const c=$('#cards-container').empty();
  atos.forEach(a=>{
    const nomes=a.partes_envolvidas.map(p=>p.nome).join(', ');
    const nomesRes=trunc(nomes,60);
    const cls=attrClass[a.atribuicao]||'';
    c.append(`<div class="col-sm-6 col-lg-4 mb-3">
      <div class="card card-ato shadow-sm ${cls}" data-id="${a.id}">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="mb-1">${a.atribuicao}</h6>
              <h5 class="mb-1">${a.categoria}</h5>
            </div>
            <i class="fa fa-folder card-icon"></i>
          </div>
          <p class="mb-1"><strong>Nome:</strong> ${nomesRes}</p>
          <p class="mb-1"><strong>Data:</strong> ${fDate(a.data_ato)}</p>
          <p class="mb-2"><strong>Livro/Folha:</strong> ${a.livro||'-'} / ${a.folha||'-'}</p>
          <button class="btn btn-warning btn-sm editar-ato" data-id="${a.id}"><i class="fa fa-pencil"></i></button>
          <button class="btn btn-danger btn-sm excluir-ato" data-id="${a.id}"><i class="fa fa-trash"></i></button>
        </div>
      </div></div>`);
  });
}
/* ---------- carrega ---------- */
function loadAtos(cb){$.get('load_atos.php',r=>{try{cb(JSON.parse(r));}catch(e){console.error(e,r);}});}
/* ---------- modal ---------- */
function openModal(id){
  $('#generate-pdf-button').data('id',id);
  $.get('load_ato.php',{id},r=>{
    const a=JSON.parse(r);
    const dl=$('#meta-dl').empty();
    const meta=[['Atribuição',a.atribuicao],['Categoria',a.categoria],['Data do Ato',fDate(a.data_ato)],
      ['Livro',a.livro],['Folha',a.folha],['Termo/Ordem',a.termo],['Protocolo',a.protocolo],['Matrícula',a.matricula],
      ['Selo de Arquivamento','']];
    meta.forEach(([l,v])=>{if(v)dl.append(`<dt class="col-md-4 font-weight-bold">${l}</dt><dd class="col-md-8">${v}</dd>`);});
    $.get('get_selo_modal.php',{id},s=>{try{const n=JSON.parse(s).numero_selo;
      if(n)dl.find('dt:contains("Selo de Arquivamento")').next().text(n);}catch{}});
    const partes=a.partes_envolvidas.map(p=>`${p.cpf} – ${p.nome}`).join(', ');
    if(partes)dl.append(`<dt class="col-md-4 font-weight-bold">Partes Envolvidas</dt><dd class="col-md-8">${partes}</dd>`);
    dl.append(`<dt class="col-md-4 font-weight-bold">Descrição</dt><dd class="col-md-8">${a.descricao}</dd>`);
    $('#view-cadastrado-por').text(a.cadastrado_por);$('#view-data-cadastro').text(fDate2(a.data_cadastro));
    const mod=$('#view-modificacoes').empty();(a.modificacoes||[]).forEach(m=>mod.append(`<li>${m.usuario} - ${fDate3(m.data_hora)}</li>`));
    const anex=$('#view-anexos-list').empty();
    a.anexos.forEach(f=>{const file=f.split('/').pop(),ext=file.split('.').pop().toLowerCase();
      anex.append(`<div class="attach-item visualizar-anexo" data-file="${f}"><i class="fa ${iconByExt(ext)} attach-icon"></i><div class="attach-name">${file}</div></div>`);});
    $('#anexosModal').modal('show');
  });
}
/* ---------- ready ---------- */
$(function(){
  $.getJSON('categorias/categorias.json',d=>d.forEach(c=>$('#categoria').append($('<option>').val(c).text(c))));
  loadAtos(renderCards);
  /* filtros */
  $('#filter-button').click(()=>{
    const s={atr:$('#atribuicao').val(),cat:$('#categoria').val(),cpf:$('#cpf-cnpj').val(),nome:nrm($('#nome').val()),
      livro:$('#livro').val(),folha:$('#folha').val(),termo:$('#termo').val(),prot:$('#protocolo').val(),
      mat:$('#matricula').val(),data:$('#data-ato').val(),desc:nrm($('#descricao').val())};
    $.get('load_atos.php',r=>{
      try{
        const f=JSON.parse(r).filter(a=>{
          const nm=nrm(a.partes_envolvidas.map(p=>p.nome).join(', '));
          const ds=nrm(a.descricao);
          return(!s.atr||a.atribuicao.includes(s.atr))&&(!s.cat||a.categoria===s.cat)&&
          (!s.cpf||a.partes_envolvidas.some(p=>p.cpf.includes(s.cpf)))&&(!s.nome||nm.includes(s.nome))&&
          (!s.livro||a.livro.includes(s.livro))&&(!s.folha||a.folha.includes(s.folha))&&(!s.termo||a.termo.includes(s.termo))&&
          (!s.prot||a.protocolo.includes(s.prot))&&(!s.mat||a.matricula.includes(s.mat))&&
          (!s.data||a.data_ato===s.data)&&(!s.desc||ds.includes(s.desc));
        });renderCards(f);
      }catch(e){console.error(e,r);}
    });
  });
  $('#cpf-cnpj')
  .on('focus', function () {
      $(this).unmask();
      $(this).val($(this).val().replace(/\D/g, ''));
  })
  .on('input', function () {
      const digits = $(this).val().replace(/\D/g, '').slice(0, 14);
      $(this).val(digits);
  })
  .on('blur', function () {
      const digits = $(this).val();
      if (!digits) return;
      const mask = digits.length <= 11
                   ? '000.000.000-00'      
                   : '00.000.000/0000-00'; 
      $(this).mask(mask, { reverse: true });
  });
  $(document).on('click','.card-ato',function(e){if($(e.target).closest('.btn').length)return;openModal($(this).data('id'));});
  $(document).on('click','.editar-ato',e=>location.href='edit_ato.php?id='+$(e.currentTarget).data('id'));
  $(document).on('click','.excluir-ato',function(){const id=$(this).data('id');
    Swal.fire({title:'Tem certeza?',text:'Excluir?',icon:'warning',showCancelButton:true,confirmButtonText:'Sim'}).then(r=>{
      if(r.isConfirmed){$.post('delete_ato.php',{id},()=>Swal.fire('Excluído','Ato movido','success').then(()=>location.reload()))
        .fail(()=>Swal.fire('Erro','Não foi possível excluir','error'));}});});
  $(document).on('click','.visualizar-anexo',e=>window.open($(e.currentTarget).data('file'),'_blank'));
  $('#generate-pdf-button').click(function(){const id=$(this).data('id');
    $.getJSON('../style/configuracao.json',cfg=>{window.open((cfg.timbrado==='S'?'capa_arquivamento.php?id=':'capa-arquivamento.php?id=')+id,'_blank');})
      .fail(()=>alert('Erro ao carregar configuração.'));});
});
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
