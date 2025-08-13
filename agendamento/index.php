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

  <!-- SweetAlert & Dropzone -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone@5/dist/min/dropzone.min.css">

  <!-- FullCalendar -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet">

  <style>
    /* ======================================================================
       TOKENS / THEME (alinhado ao exemplo "atualizar_credenciais.php")
    ====================================================================== */
    :root{
      /* base */
      --bg:#f6f7fb; --card:#ffffff; --muted:#6b7280; --text:#1f2937; --border:#e5e7eb;
      --shadow:0 10px 25px rgba(16,24,40,.06); --soft-shadow:0 6px 18px rgba(16,24,40,.08);

      /* brand */
      --brand:#4F46E5;    /* indigo 600 */
      --brand-2:#6366F1;  /* indigo 500 */

      /* semantic */
      --success:#10b981; --warning:#f59e0b; --danger:#ef4444; --info:#0ea5e9;

      /* cards resumo (modo claro) */
      --sum-mes:#e0f2fe;      /* azul clarinho   */
      --sum-semana:#e0f7fa;   /* ciano claro     */
      --sum-done:#e7f6ee;     /* verde clarinho  */
      --sum-pend:#fff7e0;     /* amarelo claro   */
    }
    body.light-mode{ background:var(--bg); color:var(--text); }
    body.dark-mode{
      --bg:#0f141a; --card:#1a2129; --text:#e5e7eb; --muted:#9aa6b2; --border:#2a3440;
      --shadow:0 10px 25px rgba(0,0,0,.35); --soft-shadow:0 6px 18px rgba(0,0,0,.4);

      --sum-mes:#1e3a8a;     /* mesmas do anterior em versão escura */
      --sum-semana:#155e75;
      --sum-done:#166534;
      --sum-pend:#854d0e;
      background:var(--bg); color:var(--text);
    }
    .muted{ color:var(--muted)!important; }

    /* ======================================================================
       HERO (cabeçalho da página)
    ====================================================================== */
    .page-hero{
      background:linear-gradient(180deg, rgba(79,70,229,.10), rgba(79,70,229,0));
      border-radius:18px; padding:18px; margin:20px 0 12px; box-shadow:var(--soft-shadow);
    }
    .page-hero .title-row{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .title-icon{
      width:44px; height:44px; border-radius:12px; background:#EEF2FF; color:#3730A3;
      display:flex; align-items:center; justify-content:center; font-size:20px;
    }
    body.dark-mode .title-icon{ background:#262f3b; color:#c7d2fe; }
    .page-hero h1{ font-weight:800; margin:0; }

    /* ======================================================================
       RESUMO (cards)
    ====================================================================== */
    .summary-card{
      border-radius:16px; background:var(--card); border:1px solid var(--border);
      box-shadow:var(--shadow);
    }
    .summary-card .card-body{ padding:1rem 1rem; }
    .summary-card h2{ margin:0; font-weight:800; }

    .card-month{ background:var(--sum-mes); }
    .card-week{ background:var(--sum-semana); }
    .card-done{ background:var(--sum-done); }
    .card-pending{ background:var(--sum-pend); }

    /* ======================================================================
       FILTROS (card do formulário)
    ====================================================================== */
    .filter-card{
      background:var(--card); border:1px solid var(--border); border-radius:16px;
      box-shadow:var(--shadow);
    }
    .filter-card .card-header{
      background:linear-gradient(135deg, var(--brand), var(--brand-2));
      color:#fff; border-radius:16px 16px 0 0; font-weight:700;
    }
    .filter-card .form-control,.filter-card .form-select{
      background:transparent; color:var(--text); border:1px solid var(--border); border-radius:10px;
    }
    .filter-card .form-control::placeholder{ color:var(--muted); }
    #btnFiltrar.btn{ min-width:140px; border-radius:10px; }
    .btn-primary{ background:var(--brand); border-color:var(--brand); }
    .btn-primary:hover{ filter:brightness(.95); }
    .btn-outline-secondary{ border-radius:10px; }

    /* ======================================================================
       LISTA (cards de agendamento)
    ====================================================================== */
    .card-agendamento{
      border-left:4px solid var(--brand);
      border-radius:16px; background:var(--card);
      box-shadow:var(--soft-shadow); transition:.25s; cursor:pointer; border:1px solid var(--border);
    }
    .card-agendamento:hover{ transform:translateY(-4px); box-shadow:0 10px 25px rgba(0,0,0,.12); }
    .acoes{ display:flex; gap:.5rem; flex-wrap:wrap; justify-content:end; }
    @media (max-width:576px){ .acoes{ justify-content:flex-start } }

    /* badges de status (lista) */
    .badge-status{ font-size:.78rem; padding:.45em .7em; border-radius:.6rem; cursor:pointer; }
    .badge-locked{ cursor:not-allowed; opacity:.65; }

    /* ======================================================================
       TOGGLE CARDS/CALENDÁRIO
    ====================================================================== */
    .view-toggle .btn{ border-radius:12px; }
    .view-toggle .btn.active{ background:var(--brand); color:#fff; border-color:var(--brand); }

    /* ======================================================================
       CALENDÁRIO (FullCalendar) – 100% responsivo e dentro da margem
    ====================================================================== */
    #calendarWrapper{
      background:var(--card); border:1px solid var(--border); border-radius:16px;
      box-shadow:var(--shadow); overflow:hidden;
    }
    #calendario{ padding:12px; }
    .fc{ --fc-border-color: var(--border); --fc-page-bg-color: transparent; }
    .fc .fc-toolbar{ flex-wrap:wrap; row-gap:.5rem; }
    .fc .fc-toolbar-title{ font-size:1.05rem; }
    .fc .fc-button{ border-radius:10px; }
    .fc-event{ border:0; font-size:.82rem; padding:2px 4px; }
    /* cores por status */
    .evt-ativo      { background:#6b7280 !important; color:#fff !important; }
    .evt-reagendado { background:#f59e0b !important; color:#000 !important; }
    .evt-cancelado  { background:#ef4444 !important; color:#fff !important; text-decoration:line-through; }
    .evt-concluido  { background:#10b981 !important; color:#fff !important; }

    /* ======================================================================
       MODAIS
    ====================================================================== */
    .modal-header .btn-print{
      background:#fff; color:#000; border:1px solid var(--border); border-radius:10px;
    }
    .modal-header .btn-print:hover{ opacity:.9; }
  </style>
</head>
<body class="light-mode">
<?php include(__DIR__.'/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">

    <!-- HERO / TÍTULO -->
    <section class="page-hero">
      <div class="title-row">
        <div class="title-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="flex-grow-1">
          <h1>Agendamento de Serviços e Pesquisas</h1>
          <div class="subtitle muted">Visualize por cartões ou calendário, filtre resultados e gerencie anexos.</div>
        </div>

        <!-- Alternância Cards / Calendário -->
        <div class="btn-group view-toggle ms-auto" role="group" aria-label="Alternar visualização">
          <button class="btn btn-outline-secondary active" id="btnViewCards" title="Ver em Cards">
            <i class="fas fa-th me-1"></i> Cards
          </button>
          <button class="btn btn-outline-secondary" id="btnViewCalendar" title="Ver em Calendário">
            <i class="fas fa-calendar-alt me-1"></i> Calendário
          </button>
        </div>
      </div>
    </section>

    <!-- CARDS RESUMO -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card summary-card card-month text-center">
          <div class="card-body">
            <h6 class="small muted mb-1">No mês</h6><h2 id="cntMes">0</h2>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card summary-card card-week text-center">
          <div class="card-body">
            <h6 class="small muted mb-1">Na semana</h6><h2 id="cntSemana">0</h2>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card summary-card card-done text-center">
          <div class="card-body">
            <h6 class="small muted mb-1">Concluídos</h6><h2 id="cntConcluidos">0</h2>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card summary-card card-pending text-center">
          <div class="card-body">
            <h6 class="small muted mb-1">Pendentes</h6><h2 id="cntPendentes">0</h2>
          </div>
        </div>
      </div>
    </div>

    <!-- FILTROS -->
    <div class="filter-card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-search me-2"></i>Filtros</span>
        <button class="btn btn-sm btn-light d-md-none" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
          <i class="fas fa-sliders-h"></i>
        </button>
      </div>
      <div id="filtrosCollapse" class="collapse show">
        <div class="card-body">
          <form id="filtro-form" class="row g-3 align-items-end">
            <div class="col-12 col-md-6">
              <label class="form-label" for="filtro_nome">Nome</label>
              <input id="filtro_nome" class="form-control" placeholder="Nome">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="filtro_servico">Serviço</label>
              <input id="filtro_servico" class="form-control" placeholder="Serviço">
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label" for="filtro_inicio">Início</label>
              <input type="date" id="filtro_inicio" class="form-control">
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label" for="filtro_fim">Fim</label>
              <input type="date" id="filtro_fim" class="form-control">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label" for="filtro_status">Status</label><br>
              <select id="filtro_status" class="form-select" style="height:calc(2.8rem + 2px);width: 100%;border-radius:10px;border-color:var(--border);">
                <option value="">Todos</option>
                <option value="ativo">Ativo</option>
                <option value="reagendado">Reagendado</option>
                <option value="cancelado">Cancelado</option>
                <option value="concluido">Concluído</option>
              </select>
            </div>
            <div class="col-12 col-md-3 d-grid">
              <button type="button" id="btnFiltrar" class="btn btn-outline-secondary w-100">
                <i class="fas fa-filter me-1"></i>Filtrar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- AÇÕES -->
    <div class="mb-3 d-flex justify-content-end">
      <button id="btnNovoAgendamento" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>Novo Agendamento
      </button>
    </div>

    <!-- LISTA (CARDS) -->
    <div id="cards-agendamentos" class="row g-4"></div>

    <!-- CALENDÁRIO -->
    <div id="calendarWrapper" class="p-2" style="display:none;">
      <div id="calendario"></div>
    </div>

  </div> <!-- /.container -->
</div> <!-- /#main -->

<?php include(__DIR__.'/modais_agendamento.php'); ?>

<script src="../script/jquery-3.6.0.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dropzone@5/dist/min/dropzone.min.js"></script>

<!-- FullCalendar -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales-all.global.min.js"></script>

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

  /* -------- carregar lista (cards) -------- */
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

  $('#btnFiltrar').on('click',()=>{carregar();contagens(); if(isCalendarInit) calendar.refetchEvents();});

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
      $('#grp_status').hide();
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
          if(isCalendarInit) calendar.refetchEvents();
      });
  });

  /* editar */
  $(document).on('click','.btn-editar',function(){
      const d=$(this).data();
      $('#modalAgendamentoLabel').text('Editar Agendamento');
      $('#agendamento_id').val(d.id);
      $('#nome').val(d.nome); $('#servico').val(d.servico);
      $('#data_hora').val(d.hora); $('#status_select').val(d.status);
      $('#observacoes').val(d.obs);

      if(d.status==='reagendado'){
          $('#grp_reagendamento').show();
          $('#data_reagendamento').val(d.reag).prop('required',true);
      }else{
          $('#grp_reagendamento').hide();
          $('#data_reagendamento').val('').prop('required',false);
      }

      $('#anexosWrapper').show();
      $('#grp_status').show();
      initDropzone(d.id); carregarAnexos(d.id);
      $('#modalAgendamento').modal('show');
  });

  /* status change (mostra/oculta campo reagendamento) */
  $('#status_select').on('change',function(){
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
      $('#view_id').text(d.id || '');
      $('#view_nome').text(d.nome || '');
      $('#view_servico').text(d.servico || '');
      $('#view_datahora').text(d.hora_formatada || '');
      $('#view_status').text(d.status_formatado || '');
      $('#view_obs').text(d.obs || '');
      $('#view_anexos').html('Carregando...');
      $('#modalVisualizar').data('payload', d); // guarda dados para imprimir
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
                      if(isCalendarInit) calendar.refetchEvents();
                      Swal.fire({toast:true,icon:'success',title:'Status atualizado',position:'top-end',timer:1800,showConfirmButton:false});
                  }else Swal.fire('Erro',r,'error');
              }).fail(()=>Swal.fire('Erro','Falha na requisição','error'));
          };

          if(novo==='reagendado'){
            const _minLocal = new Date(Date.now() - (new Date().getTimezoneOffset()*60000))
                                .toISOString().slice(0,16);
            Swal.fire({
                title:'Nova data e hora',
                html:'<input type="datetime-local" id="novaData" class="swal2-input" min="'+_minLocal+'">',
                showCancelButton:true,focusConfirm:false,
                preConfirm:()=>{const v=document.getElementById('novaData').value;if(!v) return Swal.showValidationMessage('Informe data e hora');return v;}
            }).then(r=>{ if(r.isConfirmed) prosseguir(r.value);});
            }else{
              Swal.fire({title:'Confirmar alteração?',icon:'question',showCancelButton:true})
                   .then(r=>{if(r.isConfirmed) prosseguir(null);});
          }
      });
  });

  /* -------- Calendário (FullCalendar) -------- */
  let calendar=null, isCalendarInit=false;

  function fetchCalendarEvents(info, success, failure){
    const f=filtrosObj();
    const semFiltros = !f.nome && !f.servico && !f.status && !f.inicio && !f.fim;
    if(semFiltros) f.baseline=1;
    // janela visível do calendário
    f.start = info.startStr;
    f.end   = info.endStr;
    f.format = 'fc';
    $.ajax({
      url:'listar_agendamentos.php',
      data:f, dataType:'json',
      success: success,
      error: failure
    });
  }

  function initCalendar(){
    const el=document.getElementById('calendario');
    calendar = new FullCalendar.Calendar(el,{
    locale:'pt-br',
    timeZone:'local', // interpreta strings sem fuso como horário local
    eventTimeFormat:{ hour:'2-digit', minute:'2-digit', meridiem:false }, // 24h
    initialView:'dayGridMonth',
    headerToolbar:{
        left:'prev,next today',
        center:'title',
        right:'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
      },
      buttonText:{
        today:'Hoje', month:'Mês', week:'Semana', day:'Dia', list:'Lista'
      },
      navLinks:true,
      nowIndicator:true,
      selectable:false,
      eventSources:[ fetchCalendarEvents ],
      eventClassNames:function(arg){
        const st = arg.event.extendedProps.status || '';
        return ['evt-'+st]; // aplica classes de cor
      },
      eventClick:function(info){
        const e = info.event;
        const ep = e.extendedProps || {};
        const d = {
          id: e.id,
          nome: ep.nome,
          servico: ep.servico,
          hora_formatada: ep.hora_formatada,
          status_formatado: ep.status_formatado,
          status: ep.status,
          obs: ep.obs || '',
          reag: ep.reag_inp || '',
          hora: ep.hora_inp || ''
        };
        preencher(d);
      },
      height:'auto'
    });
    calendar.render();
    isCalendarInit=true;
  }

  $('#btnViewCalendar').on('click', function(){
    if($(this).hasClass('active')) return;
    $('#btnViewCards').removeClass('active');
    $(this).addClass('active');
    $('#cards-agendamentos').hide();
    $('#calendarWrapper').show();
    if(!isCalendarInit) initCalendar(); else calendar.refetchEvents();
  });

  $('#btnViewCards').on('click', function(){
    if($(this).hasClass('active')) return;
    $('#btnViewCalendar').removeClass('active');
    $(this).addClass('active');
    $('#calendarWrapper').hide();
    $('#cards-agendamentos').show();
  });

  /* -------- Imprimir comprovante -------- */
  $(document).on('click','#btnImprimirComprovante', function(){
    const d = $('#modalVisualizar').data('payload') || {};
    const win = window.open('', '_blank');
    const agora = new Date().toLocaleString('pt-BR');
    const css = `
      <style>
        body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#111;}
        .header{display:flex;align-items:center;gap:12px;margin-bottom:16px;}
        .brand{font-weight:700;font-size:18px;}
        .badge{display:inline-block;padding:4px 8px;border-radius:6px;border:1px solid #ccc;font-size:12px;margin-left:8px;}
        h2{margin:0 0 6px 0;font-size:20px}
        .meta{color:#666;font-size:12px;margin-bottom:16px;}
        dl{display:grid;grid-template-columns:180px 1fr;gap:8px 16px;}
        dt{font-weight:700}
        .footer{margin-top:24px;font-size:12px;color:#555;}
        .print{margin-top:24px;text-align:center;font-size:12px;}
      </style>`;
    const html = `
      <html><head><meta charset="utf-8"><title>Comprovante de Agendamento</title>${css}</head>
      <body>
        <div class="header">
          <div class="brand">Comprovante de Agendamento</div>
          <span class="badge">${d.status_formatado || ''}</span>
        </div>
        <h2>${(d.servico || '')}</h2>
        <div class="meta">Emitido em ${agora}</div>
        <dl>
          <dt>Protocolo</dt><dd>${d.id || ''}</dd>
          <dt>Nome do Solicitante</dt><dd>${d.nome || ''}</dd>
          <dt>Data e Hora</dt><dd>${d.hora_formatada || ''}</dd>
          <dt>Observações</dt><dd>${(d.obs||'').toString().replace(/\n/g,'<br>')}</dd>
        </dl>
        <div class="footer">
          Guarde este comprovante. Apresente no atendimento, se solicitado.
        </div>
        <div class="print">
          <script>window.onload = function(){ window.print(); }<\/script>
        </div>
      </body></html>`;
    win.document.write(html);
    win.document.close();
  });

  /* inicial */
  carregar(); contagens();
});
</script>
<?php include(__DIR__.'/../rodape.php'); ?>
</body>
</html>
