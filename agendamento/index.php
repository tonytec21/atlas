<?php
include(__DIR__.'/session_check.php');  checkSession();
include(__DIR__.'/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');
$minNow = date('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Agendamento de Serviços</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../style/css/bootstrap.min.css">
  <link rel="stylesheet" href="../style/css/all.min.css">
  <link rel="stylesheet" href="../style/css/style.css">
  <link rel="icon" href="../style/img/favicon.png" type="image/png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone@5/dist/min/dropzone.min.css">
  <style>
    /* ---------- VARIÁVEIS GERAIS ---------- */
    :root{
        /* núcleo da marca/site */
        --primary:      #0d6efd;

        /* modo claro */
        --bg-body:      #f8f9fa;
        --bg-card:      #ffffff;
        --bg-card-alt:  #f1f5f9;
        --border-card:  #e9ecef;
        --text-body:    #2c3e50;

        /* cores cartões-resumo (claro) */
        --sum-mes:      #e0f2fe;      /* azul clarinho   */
        --sum-semana:   #e0f7fa;      /* ciano claro     */
        --sum-done:     #e7f6ee;      /* verde clarinho  */
        --sum-pend:     #fff7e0;      /* amarelo claro   */
    }

    /* ---------- DARK MODE --------------- */
    body.dark-mode{
        --bg-body:      #0f172a;
        --bg-card:      #1e293b;
        --bg-card-alt:  #243447;
        --border-card:  #334155;
        --text-body:    #e2e8f0;

        /* cartões-resumo (escuro) */
        --sum-mes:      #1e3a8a;
        --sum-semana:   #155e75;
        --sum-done:     #166534;
        --sum-pend:     #854d0e;
    }

    /* ---------- APLICAÇÃO GLOBAL ---------- */
    body{
        background: var(--bg-body);
        color: var(--text-body);
    }

    /* ---------- CARTÕES GENÉRICOS ---------- */
    .card{
        background: var(--bg-card);
        border:1px solid var(--border-card);
    }

    .summary-card{
        border-radius:.75rem;
        box-shadow:0 2px 6px rgba(0,0,0,.06);
    }
    .summary-card .card-body{
        padding:.9rem .5rem;
    }
    .summary-card h2{
        margin:0;
        font-weight:700;
    }

    /* cores individuais */
    .card-month   {background:var(--sum-mes);}
    .card-week    {background:var(--sum-semana);}
    .card-done    {background:var(--sum-done);}
    .card-pending {background:var(--sum-pend);}

    /* ---------- CARD AGENDAMENTO ---------- */
    .card-agendamento{
        border-left:4px solid var(--primary);
        border-radius:.75rem;
        background:var(--bg-card-alt);
        box-shadow:0 2px 6px rgba(0,0,0,.06);
        cursor:pointer;
        transition:.25s;
    }
    .card-agendamento:hover{
        transform:translateY(-4px);
        box-shadow:0 6px 18px rgba(0,0,0,.12);
    }

    /* ---------- FILTRO (FORM) ---------- */
    .filter-card{
        background:var(--bg-card);
        border:1px solid var(--border-card);
        border-radius:.75rem;
    }
    .filter-card .card-header{
        background:var(--primary);
        color:#fff;
        border-radius:.75rem .75rem 0 0;
    }
    .filter-card .form-control,
    .filter-card .form-select{
        background:var(--bg-card-alt);
        color:var(--text-body);
        border-color:var(--border-card);
    }
    .filter-card .form-control::placeholder{
        color:var(--text-body);
        opacity:.6;
    }

    /* especial: botão Filtrar */
    #btnFiltrar.btn{
        min-width:110px;
    }
    @media (min-width:768px){
        /* fica alinhado à direita em telas ≥ md */
        #btnFiltrar{
            width:auto!important;
        }
    }

    /* ---------- BADGES & OUTROS ---------- */
    .badge-status{
        font-size:.75rem;
        padding:.45em .7em;
        border-radius:.5rem;
        cursor:pointer;
    }
    .badge-locked{cursor:not-allowed;opacity:.65;}

    .acoes{
        display:flex;
        gap:.5rem;
        flex-wrap:wrap;
        justify-content:end;
    }
    @media (max-width:576px){
        .acoes{justify-content:flex-start}
    }

  </style>
</head>
<body class="light-mode">
<?php include(__DIR__.'/../menu.php'); ?>

<div id="main" class="main-content">
  <h2 class="page-title text-center mb-4">Agendamento de Serviços e Pesquisas</h2>

  <!-- cards resumo -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card summary-card card-month border-primary text-center shadow-sm"><div class="card-body"><h6 class="small text-muted">No mês</h6><h2 id="cntMes">0</h2></div></div></div>
    <div class="col-6 col-md-3"><div class="card summary-card card-week border-info text-center shadow-sm"><div class="card-body"><h6 class="small text-muted">Na semana</h6><h2 id="cntSemana">0</h2></div></div></div>
    <div class="col-6 col-md-3"><div class="card summary-card card-done border-success text-center shadow-sm"><div class="card-body"><h6 class="small text-muted">Concluídos</h6><h2 id="cntConcluidos">0</h2></div></div></div>
    <div class="col-6 col-md-3"><div class="card summary-card card-pending border-warning text-center shadow-sm"><div class="card-body"><h6 class="small text-muted">Pendentes</h6><h2 id="cntPendentes">0</h2></div></div></div>
  </div>

  <!-- filtros -->
  <div class="filter-card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-search me-2"></i>Filtros</span>
      <button class="btn btn-sm btn-light d-md-none" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
        <i class="fas fa-sliders-h"></i>
      </button>
    </div>
    <div id="filtrosCollapse" class="collapse show">
      <div class="card-body">
        <form id="filtro-form" class="row g-3 align-items-end">
          <div class="col-12 col-md-6"><label class="form-label" for="filtro_nome">Nome</label><input id="filtro_nome" class="form-control" placeholder="Nome"></div>
          <div class="col-12 col-md-6"><label class="form-label" for="filtro_servico">Serviço</label><input id="filtro_servico" class="form-control" placeholder="Serviço"></div>
          <div class="col-6 col-md-3"><label class="form-label" for="filtro_inicio">Início</label><input type="date" id="filtro_inicio" class="form-control"></div>
          <div class="col-6 col-md-3"><label class="form-label" for="filtro_fim">Fim</label><input type="date" id="filtro_fim" class="form-control"></div>
          <div class="col-12 col-md-3"><label class="form-label" for="filtro_status">Status</label><br>
            <select id="filtro_status" class="form-select" style="height:calc(2.8rem + 2px);width: 100%;border-radius:8px;border-color:#ced4da;">
              <option value="">Todos</option><option value="ativo">Ativo</option><option value="reagendado">Reagendado</option><option value="cancelado">Cancelado</option><option value="concluido">Concluído</option>
            </select>
          </div>
          <div class="col-12 col-md-3 d-grid"><button type="button" id="btnFiltrar" class="btn btn-secondary w-100"><i class="fas fa-filter"></i></button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="mb-3 text-end">
    <button id="btnNovoAgendamento" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Novo Agendamento</button>
  </div>

  <div id="cards-agendamentos" class="row g-4"></div>
</div>


<?php include(__DIR__.'/modais_agendamento.php'); ?>

<script src="../script/jquery-3.6.0.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dropzone@5/dist/min/dropzone.min.js"></script>
<script>
Dropzone.autoDiscover=false;

$(function(){

  /* -------- helpers -------- */
  const filtrosObj=()=>({
      nome:$('#filtro_nome').val(),
      servico:$('#filtro_servico').val(),
      status:$('#filtro_status').val(),
      inicio:$('#filtro_inicio').val(),
      fim:$('#filtro_fim').val()
  });

  /* -------- carregar lista -------- */
  function carregar(){
      const f=filtrosObj();
      const semFiltros = !f.nome && !f.servico && !f.status && !f.inicio && !f.fim;
      if(semFiltros) f.baseline=1;
      $.get('listar_agendamentos.php',f,d=>$('#cards-agendamentos').html(d));
  }

  /* -------- contagens -------- */
  function contagens(){
      const f=filtrosObj();
      const semFiltros = !f.nome && !f.servico && !f.status && !f.inicio && !f.fim;
      if(semFiltros) f.baseline=1;
      $.getJSON('contagem_agendamentos.php',f,r=>{
          $('#cntMes').text(r.mes);
          $('#cntSemana').text(r.semana);
          $('#cntConcluidos').text(r.concluidos);
          $('#cntPendentes').text(r.pendentes);
      });
  }

  $('#btnFiltrar').on('click',()=>{carregar();contagens();});

  /* -------- Dropzone -------- */
  let dz;
  function initDropzone(id){
      if(dz){ dz.destroy(); $('#dropAnexos').empty(); }
      dz=new Dropzone('#dropAnexos',{
          url:'upload_anexo_agendamento.php',
          paramName:'arquivo',
          maxFilesize:10,
          acceptedFiles:'.pdf,.jpg,.jpeg,.png',
          addRemoveLinks:true,
          dictDefaultMessage:'Solte os arquivos aqui ou clique para enviar',
          dictRemoveFile:'<i class="fas fa-trash-alt text-danger"></i>',
          autoProcessQueue:false,
          init(){
              this.on('sending',(f,xhr,fd)=>fd.append('agendamento_id',id));
              this.on('queuecomplete',()=>{if(id!=='temp') carregarAnexos(id);});
          }
      });
  }

  function carregarAnexos(id){
      $('#listaAnexos').load('listar_anexos.php?id='+id+'&edit=1',function(){
          $('.ver-anexo').on('click',function(){
              $('#frameViewer').attr('src',$(this).data('file'));
              $('#modalViewer').modal('show');
          });
      });
  }

  /* remover anexo */
  $(document).on('click','.rem-anexo',function(){
      const anexoId=$(this).data('anexo'), linha=$(this).closest('div');
      Swal.fire({title:'Remover anexo?',icon:'warning',showCancelButton:true,confirmButtonText:'Sim, remover'})
      .then(r=>{
          if(!r.isConfirmed) return;
          $.post('excluir_anexo.php',{id:anexoId},resp=>{
              if(resp.trim()==='ok'){linha.remove();}
              else Swal.fire('Erro',resp,'error');
          }).fail(()=>Swal.fire('Erro','Falha na requisição','error'));
      });
  });

  /* novo */
  $('#btnNovoAgendamento').on('click',()=>{
      $('#formAgendamento')[0].reset();
      $('#agendamento_id').val('');
      $('#modalAgendamentoLabel').text('Novo Agendamento');
      $('#listaAnexos').empty();
      $('#anexosWrapper').hide();
      $('#status').hide();
      $('#grp_reagendamento').hide();
      $('#data_reagendamento').val('').prop('required',false);
      initDropzone('temp');
      $('#modalAgendamento').modal('show');
  });

  /* salvar */
  $('#formAgendamento').on('submit',e=>{
      e.preventDefault();
      const dados=$(e.target).serialize();
      $.post('salvar_agendamento.php',dados,id=>{
          dz.options.params={agendamento_id:id};
          dz.processQueue();
          $('#modalAgendamento').modal('hide');
          carregar();contagens();
      });
  });

  /* editar */
  $(document).on('click','.btn-editar',function(){
      const d=$(this).data();
      $('#modalAgendamentoLabel').text('Editar Agendamento');
      $('#agendamento_id').val(d.id);
      $('#nome').val(d.nome); $('#servico').val(d.servico);
      $('#data_hora').val(d.hora); $('#status').val(d.status);
      $('#observacoes').val(d.obs);

      if(d.status==='reagendado'){
          $('#grp_reagendamento').show();
          $('#data_reagendamento').val(d.reag).prop('required',true);
      }else{
          $('#grp_reagendamento').hide();
          $('#data_reagendamento').val('').prop('required',false);
      }

      $('#anexosWrapper').show();
      $('#status').show();
      initDropzone(d.id); carregarAnexos(d.id);
      $('#modalAgendamento').modal('show');
  });

  /* status change (mostra/oculta campo reagendamento) */
  $('#status').on('change',function(){
      if(this.value==='reagendado'){
          $('#grp_reagendamento').show();
          $('#data_reagendamento').prop('required',true);
      }else{
          $('#grp_reagendamento').hide();
          $('#data_reagendamento').val('').prop('required',false);
      }
  });

  /* visualizar */
  function preencher(d){
      $('#view_nome').text(d.nome);
      $('#view_servico').text(d.servico);
      $('#view_datahora').text(d.hora_formatada);
      $('#view_status').text(d.status_formatado);
      $('#view_obs').text(d.obs);
      $('#view_anexos').html('Carregando...');
      $('#modalVisualizar').modal('show');
      $('#view_anexos').load('listar_anexos.php?id='+d.id,function(h){
          if(!h.trim()) $('#view_anexos').html('<span class="text-muted">Nenhum anexo</span>');
          $('.ver-anexo').on('click',function(){
              $('#frameViewer').attr('src',$(this).data('file'));
              $('#modalViewer').modal('show');
          });
      });
  }
  $(document).on('click','.card-agendamento',function(e){
      if($(e.target).closest('button,a,.badge-status').length) return;
      preencher($(this).data());
  });
  $(document).on('click','.btn-visualizar',function(){preencher($(this).data());});

  /* badge status */
  $(document).on('click','.badge-status',function(e){
      e.stopPropagation();
      const b=$(this);
      if(b.hasClass('badge-locked')) return;
      const id=b.data('id'), atual=b.data('status');
      Swal.fire({
          title:'Alterar status',input:'select',
          inputOptions:{ativo:'Ativo',reagendado:'Reagendado',cancelado:'Cancelado',concluido:'Concluído'},
          inputValue:atual,showCancelButton:true,confirmButtonText:'Continuar',
          inputValidator:v=>!v&&'Selecione um status'
      }).then(res=>{
          if(!res.isConfirmed) return;
          const novo=res.value; if(novo===atual) return;

          const prosseguir=dt=>{
              $.post('atualizar_status.php',{id,status:novo,nova_data:dt},r=>{
                  if(r.trim()==='ok'){
                      carregar();contagens();
                      Swal.fire({toast:true,icon:'success',title:'Status atualizado',position:'top-end',timer:1800,showConfirmButton:false});
                  }else Swal.fire('Erro',r,'error');
              }).fail(()=>Swal.fire('Erro','Falha na requisição','error'));
          };

          if(novo==='reagendado'){
              Swal.fire({
                  title:'Nova data e hora',
                  html:'<input type="datetime-local" id="novaData" class="swal2-input" min="'+new Date().toISOString().slice(0,16)+'">',
                  showCancelButton:true,focusConfirm:false,
                  preConfirm:()=>{const v=document.getElementById('novaData').value;if(!v) return Swal.showValidationMessage('Informe data e hora');return v;}
              }).then(r=>{ if(r.isConfirmed) prosseguir(r.value);});
          }else{
              Swal.fire({title:'Confirmar alteração?',icon:'question',showCancelButton:true})
                   .then(r=>{if(r.isConfirmed) prosseguir(null);});
          }
      });
  });

  /* inicial */
  carregar(); contagens();
});
</script>
<?php include(__DIR__.'/../rodape.php'); ?>
</body>
</html>
