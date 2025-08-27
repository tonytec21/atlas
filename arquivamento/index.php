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
/* =======================================================================
   TOKENS / THEME
======================================================================= */
:root{
  --bg: #f6f7fb;
  --card: #ffffff;
  --muted: #6b7280;
  --text: #1f2937;
  --border: #e5e7eb;
  --shadow: 0 10px 25px rgba(16,24,40,.06);
  --soft-shadow: 0 6px 18px rgba(16,24,40,.08);
  --brand: #4F46E5;
  --brand-2: #6366F1;
  --success: #10b981;
  /* altura fixa e uniforme para todos os cards */
  --card-h: 320px;
}

body.light-mode{ background:var(--bg); color:var(--text); }
body.dark-mode{
  --bg:#0f141a; --card:#1a2129; --text:#e5e7eb; --muted:#9aa6b2; --border:#2a3440;
  --shadow: 0 10px 25px rgba(0,0,0,.35);
  --soft-shadow: 0 6px 18px rgba(0,0,0,.4);
  background:var(--bg); color:var(--text);
}

/* utilidades */
.muted{ color:var(--muted)!important; }
.soft-divider{ height:1px;background:var(--border);margin:1rem 0; }

/* =======================================================================
   HERO
======================================================================= */
.page-hero .title-row{ display:flex; align-items:center; gap:12px; }
.page-hero{
  background: linear-gradient(180deg, rgba(79,70,229,.10), rgba(79,70,229,0));
  border-radius: 18px; padding: 18px 18px 10px; margin: 20px 0 12px; box-shadow: var(--soft-shadow);
}
.title-icon{
  width:44px;height:44px;border-radius:12px;background:#EEF2FF;color:#3730A3;display:flex;align-items:center;justify-content:center;font-size:20px;
}
body.dark-mode .title-icon{ background:#262f3b;color:#c7d2fe; }
.page-hero h1{ font-weight:800; margin:0; }

/* =======================================================================
   FILTROS
======================================================================= */
.filter-card{
  background:var(--card); border:1px solid var(--border);
  border-radius: 16px; padding:16px 16px 10px; box-shadow: var(--shadow);
}
.filter-card label{
  font-size:.78rem; text-transform:uppercase; letter-spacing:.04em;
  color:var(--muted); margin-bottom:6px; font-weight:700;
}
label{
  margin-bottom: -.2rem;
}
.filter-card .form-control, .filter-card select{
  background: transparent; color: var(--text);
  border:1px solid var(--border); border-radius:10px;
}
.filter-card .form-control:focus, .filter-card select:focus{
  border-color:#a5b4fc; box-shadow:0 0 0 .2rem rgba(99,102,241,.15);
}

/* botões full */
.btn-full{ width:49.8%; margin-top:10px; }

/* Chips de período */
.date-chips{ display:flex; flex-wrap:wrap; gap:10px; }
.chip{
  user-select:none; cursor:pointer; padding:.5rem .9rem; border-radius:999px;
  background:#eef2ff; color:#3730A3; border:1px solid #c7d2fe; font-weight:700; font-size:.9rem;
  transition:.15s;
}
.chip:hover{ filter:brightness(.97); transform:translateY(-1px); }
.chip.active{ background:linear-gradient(135deg,var(--brand),var(--brand-2)); color:#fff; border-color:transparent; box-shadow:0 8px 20px rgba(79,70,229,.25); }

#custom-range{ display:none; }
#apply-custom{ border:1px dashed var(--brand); background:transparent; color:var(--brand); font-weight:800; }
#apply-custom:hover{ background:rgba(79,70,229,.08); }

/* =======================================================================
   CARDS – ARQUIVAMENTOS (simétricos)
======================================================================= */
#cards-container .col-card{ display:flex; } /* coluna vira “container” flex */
.card-ato{
  cursor:pointer; transition:.2s; border-radius:14px;
  width:100%;                   /* ocupa 100% da largura da coluna */
  height:var(--card-h);         /* altura fixa para todos os cards */
  background:var(--card); border:1px solid var(--border); box-shadow:var(--soft-shadow);
  position:relative; overflow:visible; /* mantém o balão para textos longos */
  display:flex; flex-direction:column;
}
.card-ato:hover{ transform:translateY(-4px); }
.card-ato .card-body{
  padding:16px 16px 14px 16px;
  display:flex; flex-direction:column;
  height:100%; width:100%;      /* garante preenchimento total dentro do card */
}

.card-ato::before{
  content:""; position:absolute; inset:0 0 0; width:8px; border-radius:14px 0 0 14px;
  background:var(--brand);
}
.card-ato.rc::before   { background:#ef4444; }
.card-ato.ri::before   { background:#0ea5b7; }
.card-ato.rtd::before  { background:#6366f1; }
.card-ato.rcpj::before { background:#10b981; }
.card-ato.notas::before{ background:#f59e0b; }
.card-ato.protes::before{ background:#a855f7; }
.card-ato.cmar::before { background:#2563eb; }

.badge-soft{
  display:inline-flex; align-items:center; gap:6px;
  background:#eef2ff; color:#3730A3; border:1px solid #c7d2fe;
  padding:6px 10px; border-radius:999px; font-weight:700; font-size:.72rem;
}
body.dark-mode .badge-soft{ background:#263041; color:#cbd5e1; border-color:#344154; }
.card-ato .title-wrap{ display:flex; justify-content:space-between; align-items:flex-start; gap:10px; }
.card-icon{ font-size:22px; color:var(--muted); opacity:.75; }

/* Nome – área rolável dentro do card (substitui o balão) */
.name-block{ display:flex; flex-direction:column; gap:6px; margin-bottom:8px; }
.name-label{ font-weight:700; }
.name-area{
  width:100%; min-height:72px; max-height:110px; overflow:auto;
  border:1px solid var(--border); border-radius:10px; padding:8px 10px;
  background:linear-gradient(180deg, rgba(148,163,184,.06), rgba(148,163,184,0));
  color:var(--text); font-size:.9rem; line-height:1.25; resize:none;
}
.name-area:focus{ outline:none; box-shadow:0 0 0 .2rem rgba(99,102,241,.15); border-color:#a5b4fc; }
body.dark-mode .name-area{ background:#12171d; border-color:#2a3440; }

/* ações do card */
.card-ato .btn{ margin-right:.35rem; margin-top:auto; }

/* tons neutros para dark/light */
body.light-mode .rc, body.light-mode .ri, body.light-mode .rtd,
body.light-mode .rcpj, body.light-mode .notas, body.light-mode .protes, body.light-mode .cmar{ background:var(--card); }
body.dark-mode .rc, body.dark-mode .ri, body.dark-mode .rtd,
body.dark-mode .rcpj, body.dark-mode .notas, body.dark-mode .protes, body-dark-mode .cmar{ background:var(--card); color:var(--text); }

/* =======================================================================
   MODAL – VISUALIZAÇÃO (Dados do ato) – redesign
======================================================================= */
#anexosModal .modal-dialog{ max-width:90vw; }              /* 90% da largura */
#anexosModal .modal-content{
  background:var(--card); border:1px solid var(--border);
  border-radius:18px; box-shadow: var(--shadow);
  height:90vh;                                            /* 90% da altura */
  display:flex; flex-direction:column; overflow:hidden;   /* body rola */
}
#anexosModal .modal-header{
  position:sticky; top:0; z-index:5;
  background:linear-gradient(135deg, rgba(79,70,229,.16), rgba(99,102,241,.10));
  border-bottom:1px solid var(--border); padding:16px 18px;
  display:flex; align-items:center; gap:14px; flex-wrap:wrap;
}
#anexosModal .modal-body{ padding:18px; overflow:auto; }   /* conteúdo rola */

.modal-title{ font-weight:900; letter-spacing:.2px; display:flex; align-items:center; gap:10px; }
.modal-title .ticon{ width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:#EEF2FF; color:#3730A3; }
.close{ background:transparent; border:0; font-size:1.6rem; opacity:.6; line-height:1; }
.close:hover{ opacity:1; }
.modal-body{ padding:16px; }

/* Busca dentro do modal */
.modal-search{ position:relative; width:min(420px,100%); margin-left:auto; }
.modal-search input{
  width:100%; border:1px solid var(--border); background:var(--card); color:var(--text);
  border-radius:999px; padding:8px 12px 8px 36px; font-size:.95rem;
}
.modal-search i{ position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--muted); }

/* Destaque de busca */
mark.mark-search{
  background:#fff3bf; color:inherit; padding:.05rem .2rem; border-radius:.25rem;
}
body.dark-mode mark.mark-search{ background:#6b5b00; }

/* METADADOS – cards responsivos */
.meta-wrap{ margin-top:8px; }
.meta-grid{ display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; }
.meta-card{
  display:flex; align-items:flex-start; gap:10px;
  border:1px solid var(--border);
  background:linear-gradient(180deg, rgba(148,163,184,.06), rgba(148,163,184,0));
  border-radius:12px; padding:12px; box-shadow: var(--soft-shadow);
}
.meta-ico{
  width:28px; height:28px; border-radius:8px;
  display:flex; align-items:center; justify-content:center;
  background:#EEF2FF; color:#3730A3; flex:0 0 28px;
}
.meta-label{
  font-size:.78rem; color:var(--muted); font-weight:800;
  letter-spacing:.04em; margin-bottom:2px; text-transform:uppercase;
}
.meta-value{ font-weight:700; }
@media(max-width: 992px){ .meta-grid{ grid-template-columns: repeat(2,1fr); } }
@media(max-width: 576px){ .meta-grid{ grid-template-columns: 1fr; } }

/* Barra de resumo (chips no topo do body) */
.summary-bar{ display:flex; flex-wrap:wrap; gap:8px; }
.summary-pill{
  display:inline-flex; align-items:center; gap:8px;
  padding:.45rem .7rem; border-radius:999px;
  background:#eef2ff; color:#3730A3; border:1px solid #c7d2fe; font-weight:800;
}
.summary-pill i{ opacity:.8; }

/* Timeline do histórico */
.timeline{ margin:0; padding-left:0; list-style:none; }
.timeline li{ position:relative; padding-left:22px; margin-bottom:8px; }
.timeline li::before{
  content:""; position:absolute; left:6px; top:6px;
  width:8px; height:8px; border-radius:50%; background:var(--brand);
}

/* Selos vinculados */
.seal-section{ margin-top:4px; }
.seal-title{ font-weight:800; letter-spacing:.2px; margin-bottom:8px; }
.seal-chips{ display:flex; flex-wrap:wrap; gap:8px; }
.seal-chip{
  display:inline-flex; align-items:center; gap:8px;
  border:1px solid #c7d2fe; background:#eef2ff; color:#3730A3;
  border-radius:999px; padding:.4rem .65rem; font-weight:700; font-size:.8rem;
}
.seal-chip .copy-selo{ border:0; background:transparent; color:#3730A3; padding:0; line-height:1; }
.seal-chip .copy-selo:hover{ opacity:.8; }

/* =======================================================================
   MODAL – PREVIEW DE ANEXOS (PDF / IMG)
======================================================================= */
#previewModal .modal-dialog{ max-width: min(1400px, 95vw); }
#previewModal .modal-content{ border-radius:18px; border:1px solid var(--border); background:var(--card); position:relative; }
#previewModal .modal-header{
  position:sticky; top:0; background:var(--card); border-bottom:1px solid var(--border); padding:14px 16px; z-index:3;
  display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
}
.preview-toolbar .btn{ border-radius:10px; border:1px solid var(--border); background:transparent; color:var(--text); }
.preview-toolbar .btn:hover{ background:rgba(148,163,184,.15); }
#previewContainer{
  padding:0; margin:0; height: calc(90vh - 64px);
  display:flex; align-items:center; justify-content:center; background:linear-gradient(180deg, rgba(148,163,184,.06), rgba(148,163,184,.02));
}
#previewFrame{ width:100%; height:100%; border:0; display:none; }
#previewImage{ max-width: 100%; max-height: 100%; object-fit:contain; display:none; border-radius: 12px; }

/* Botão flutuante de fechar (melhor UX em smartphones) */
.modal-close-fab{
  position:absolute; top:10px; right:10px; width:44px; height:44px; border-radius:50%;
  display:none; align-items:center; justify-content:center;
  border:1px solid var(--border); background:var(--card); color:var(--text);
  box-shadow:var(--shadow); z-index:5;
}
.modal-close-fab i{ font-size:18px; line-height:1; }
@media(max-width:576px){
  #previewContainer{ height: calc(92vh - 64px); }
  .modal-close-fab{ display:flex; }
}

/* =======================================================================
   ANEXOS – GRID
======================================================================= */
.attach-grid{ display:grid; grid-template-columns: repeat(auto-fit,minmax(140px,1fr)); gap:.75rem; }
.attach-item{
  position:relative;
  border:1px solid var(--border); border-radius:.8rem; padding:.9rem .8rem;
  background:linear-gradient(180deg, rgba(148,163,184,.08), rgba(148,163,184,0));
  color:var(--text); text-align:center; cursor:pointer; transition:transform .15s, background .15s, box-shadow .15s;
  box-shadow: var(--soft-shadow);
}
.attach-item:hover{ transform:translateY(-3px); background:linear-gradient(180deg, rgba(148,163,184,.15), rgba(148,163,184,.02)); }
.attach-icon{ font-size:1.75rem; margin-bottom:.35rem; opacity:.9 }
.attach-name{ font-size:.8rem; word-break:break-word }
.attach-chip{
  position:absolute; top:8px; right:8px; font-size:.7rem;
  padding:.2rem .45rem; border-radius:999px; background:#e0e7ff; color:#3730A3; border:1px solid #c7d2fe;
}
body.dark-mode .attach-chip{ background:#243042; color:#cbd5e1; border-color:#344154; }

/* =======================================================================
   BOTÕES
======================================================================= */
.btn-primary{ background:#4F46E5; border-color:#4F46E5; }
.btn-primary:hover{ filter: brightness(.95); }
.btn-success{ background:#10b981; border-color:#10b981; }
.btn-warning{ background:#f59e0b; border-color:#f59e0b; color:#fff; }
.btn-danger{ background:#ef4444; border-color:#ef4444; }

/* =======================================================================
   OUTROS AJUSTES
======================================================================= */
.modal-dialog{ max-width:90% } /* (mantido do original) */
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">

    <!-- HERO / TÍTULO -->
    <section class="page-hero">
      <div class="title-row">
        <div class="title-icon"><i class="fa fa-archive"></i></div>
        <div>
          <h1>Arquivamentos</h1>
          <div class="subtitle muted">Consulta e gestão dos arquivamentos com filtros rápidos e visualização de anexos.</div>
        </div>
      </div>
    </section>

    <!-- FILTROS ----------------------------------------------------------------- -->
    <div class="filter-card">
      <div class="row g-3">
        <div class="col-md-4">
          <label for="atribuicao">Atribuição</label>
          <select id="atribuicao" class="form-control">
            <option value="">Selecione</option>
            <option>Registro Civil</option><option>Registro de Imóveis</option>
            <option>Registro de Títulos e Documentos</option>
            <option>Registro Civil das Pessoas Jurídicas</option>
            <option>Notas</option><option>Protesto</option><option>Contratos Marítimos</option><option>Administrativo</option>
          </select>
        </div>
        <div class="col-md-4">
          <label for="categoria">Categoria</label>
          <select id="categoria" class="form-control"><option value="">Selecione</option></select>
        </div>
        <div class="col-md-4">
          <label for="cpf-cnpj">CPF/CNPJ</label>
          <input id="cpf-cnpj" class="form-control" maxlength="14" inputmode="numeric">
        </div>

        <div class="col-md-6">
          <label for="nome">Nome</label>
          <input id="nome" class="form-control">
        </div>
        <div class="col-md-2">
          <label for="livro">Livro</label>
          <input id="livro" class="form-control">
        </div>
        <div class="col-md-2">
          <label for="folha">Folha</label>
          <input id="folha" class="form-control">
        </div>
        <div class="col-md-2">
          <label for="termo">Termo/Ordem</label>
          <input id="termo" class="form-control">
        </div>

        <div class="col-md-2">
          <label for="protocolo">Protocolo</label>
          <input id="protocolo" class="form-control">
        </div>
        <div class="col-md-2">
          <label for="matricula">Matrícula</label>
          <input id="matricula" class="form-control">
        </div>

        <div class="col-md-2">
          <label for="data-ato">Data específica</label>
          <input id="data-ato" type="date" class="form-control">
        </div>

        <div class="col-md-6">
          <label for="descricao">Descrição e Detalhes</label>
          <input id="descricao" class="form-control">
        </div>

        <!-- Filtro de período dinâmico -->
        <div class="col-12">
          <label style="display:block; font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); font-weight:700; margin-bottom:6px;">Período</label>
          <div class="date-chips" id="dateChips">
            <span class="chip" data-range="today">Hoje</span>
            <span class="chip" data-range="7d">Últimos 7 dias</span>
            <span class="chip" data-range="30d">Último mês</span>
            <span class="chip" data-range="365d">Último ano</span>
            <span class="chip" data-range="all">Todos</span>
            <span class="chip" data-range="custom">Personalizado</span>
          </div>
        </div>

        <!-- Intervalo personalizado -->
        <div class="col-12" id="custom-range">
          <div class="row g-2 align-items-end">
            <div class="col-sm-4 col-md-3">
              <label for="custom-from">De</label>
              <input type="date" id="custom-from" class="form-control">
            </div>
            <div class="col-sm-4 col-md-3">
              <label for="custom-to">Até</label>
              <input type="date" id="custom-to" class="form-control">
            </div>
            <div class="col-sm-4 col-md-3">
              <button id="apply-custom" class="btn w-100"><i class="fa fa-filter"></i> Aplicar intervalo</button>
            </div>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-between">
          <button id="filter-button" class="btn btn-primary btn-full"><i class="fa fa-filter"></i> Filtrar</button>
          <button class="btn btn-success btn-full" onclick="location.href='cadastro.php'"><i class="fa fa-plus"></i> Adicionar</button>
        </div>
      </div>
    </div>

    <div class="soft-divider"></div>

    <!-- RESULTADOS ----------------------------------------------------------------- -->
    <div class="d-flex align-items-center justify-content-between">
      <h5 style="margin:8px 0 14px;font-weight:800;">Resultados da Pesquisa</h5>
    </div>

    <div id="cards-container" class="row"></div>
  </div>
</div>

<!-- MODAL – DADOS DO ATO ----------------------------------------------------- -->
<div class="modal fade" id="anexosModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <div class="header-left d-flex align-items-center gap-2">
          <div class="modal-title m-0">
            <span class="ticon"><i class="fa fa-file-text-o"></i></span>
            <span>Dados do Ato</span>
          </div>
          <!-- Busca dentro do modal -->
          <div class="modal-search">
            <i class="fa fa-search"></i>
            <input type="search" id="modalSearch" placeholder="Buscar em partes, descrição e anexos..." aria-label="Buscar no modal">
          </div>
        </div>
        <div class="header-actions d-flex align-items-center gap-2">
          <button id="generate-pdf-button" class="btn btn-primary btn-sm">
            <i class="fa fa-print"></i> Capa de arquivamento
          </button>
          <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
        </div>
      </div>

      <div class="modal-body">
        <!-- RESUMO RÁPIDO -->
        <div class="summary-bar" id="summary-bar"></div>

        <div class="soft-divider"></div>

        <!-- METADADOS EM CARDS -->
        <div class="meta-wrap">
          <div class="meta-grid" id="meta-grid"></div>
        </div>

        <div class="soft-divider"></div>

        <!-- SELOS VINCULADOS -->
        <div class="seal-section">
          <div class="seal-title">Selos vinculados</div>
          <div id="selos-list" class="seal-chips"></div>
        </div>

        <div class="soft-divider"></div>

        <!-- ANEXOS -->
        <h5 class="mb-2" style="font-weight:800">Anexos</h5>
        <div id="view-anexos-list" class="attach-grid"></div>

        <div class="soft-divider"></div>

        <!-- INFORMAÇÕES DE RODAPÉ -->
        <div class="d-flex flex-wrap gap-3 align-items-center muted">
          <small>Cadastrado por: <span id="view-cadastrado-por"></span></small>
          <small>•</small>
          <small>Data de Cadastro: <span id="view-data-cadastro"></span></small>
        </div>

        <div class="soft-divider"></div>

        <!-- HISTÓRICO (TIMELINE) -->
        <h6 class="mb-2" style="font-weight:800">Histórico de Modificações</h6>
        <ul id="view-modificacoes" class="timeline mb-0"></ul>
      </div>

    </div>
  </div>
</div>

<!-- MODAL – PREVIEW DE ANEXOS ------------------------------------------------ -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <button type="button" class="modal-close-fab" data-dismiss="modal" aria-label="Fechar pré-visualização"><i class="fa fa-times"></i></button>
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <i class="fa fa-eye" aria-hidden="true"></i>
          <h5 class="modal-title m-0">Visualização do Anexo</h5>
        </div>
        <div class="preview-toolbar d-flex align-items-center gap-2">
          <a id="btnOpenNewTab" class="btn btn-sm" target="_blank" rel="noopener">
            <i class="fa fa-external-link"></i> Abrir em nova aba
          </a>
          <a id="btnDownload" class="btn btn-sm" download>
            <i class="fa fa-download"></i> Baixar
          </a>
          <button type="button" class="close ml-2" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
        </div>
      </div>
      <div id="previewContainer">
        <iframe id="previewFrame"></iframe>
        <img id="previewImage" alt="Pré-visualização do anexo">
      </div>
    </div>
  </div>
</div>

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
const trunc =(str,len=60)=>str && str.length>len?str.slice(0,len-1)+'…':(str||'');
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
 case'pdf':return'fa-file-pdf-o';case'jpg':case'jpeg':case'png':case'gif':case'webp':return'fa-file-image-o';
 case'doc':case'docx':return'fa-file-word-o';case'xls':case'xlsx':return'fa-file-excel-o';default:return'fa-file-o';}}

function isImage(ext){ return ['jpg','jpeg','png','gif','webp'].includes(ext); }
function isPdf(ext){ return ext==='pdf'; }
function fileExt(path){ return (path.split('.').pop()||'').toLowerCase(); }

/* -------- Helpers do viewer PDF.js -------- */
// Converte qualquer caminho/URL (relativo ou absoluto) em URL absoluta
function toAbsoluteUrl(u){
  try { return new URL(u, window.location.href).href; } catch(e){ return u; }
}
// Monta a URL do viewer do pdf.js apontando para o arquivo
function buildPdfViewerUrl(fileUrlAbs){
  const base = '../provimentos/pdfjs/web/viewer.html';
  return base + '?file=' + encodeURIComponent(fileUrlAbs);
}

/* -------- Copiar texto (com fallback para ambientes sem HTTPS) -------- */
async function copyText(text){
  try{
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
    } else {
      // fallback
      const ta=document.createElement('textarea');
      ta.value=text; ta.style.position='fixed'; ta.style.left='-9999px';
      document.body.appendChild(ta); ta.focus(); ta.select();
      document.execCommand('copy'); document.body.removeChild(ta);
    }
    Swal.fire({icon:'success', title:'Copiado!', text:'Conteúdo copiado para a área de transferência.', timer:1500, showConfirmButton:false});
  }catch(e){
    Swal.fire({icon:'error', title:'Erro', text:'Não foi possível copiar.', timer:1800, showConfirmButton:false});
  }
}

/* ========= HIGHLIGHT DE BUSCA ========= */
function escapeRegExp(s){ return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }
function storeOriginals(){
  $('.searchable').each(function(){
    if ($(this).data('orig') === undefined) $(this).data('orig', $(this).html());
  });
}
function restoreOriginals(){
  $('.searchable').each(function(){
    const orig = $(this).data('orig'); if (orig !== undefined) $(this).html(orig);
  });
}
function applyHighlights(term){
  restoreOriginals();
  const q = term.trim();
  // filtrar anexos
  $('.attach-item').each(function(){
    const name = $(this).find('.attach-name').text().toLowerCase();
    $(this).toggle(!q || name.includes(q.toLowerCase()));
  });
  if (!q) return;
  const rx = new RegExp('(' + escapeRegExp(q) + ')','gi');
  $('.searchable').each(function(){
    $(this).html($(this).html().replace(rx,'<mark class="mark-search">$1</mark>'));
  });
}

/* ---------- cards ---------- */
function renderCards(atos){
  const c=$('#cards-container').empty();
  atos.forEach(a=>{
    const nomes = a.partes_envolvidas.map(p=>p.nome).join(', ');
    const cls   = attrClass[a.atribuicao]||'';
    c.append(`<div class="col-12 col-md-6 col-xl-4 mb-3 col-card">
      <div class="card card-ato shadow-sm ${cls}" data-id="${a.id}">
        <div class="card-body">
          <div class="title-wrap">
            <div>
              <div class="badge-soft mb-2"><i class="fa fa-hashtag"></i>${a.atribuicao}</div>
              <h5 class="mb-1" style="font-weight:800">${a.categoria}</h5>
            </div>
            <i class="fa fa-folder card-icon"></i>
          </div>
          <div class="name-block">
            <label class="name-label">Partes:</label>
            <textarea class="name-area" readonly>${nomes || '-'}</textarea>
          </div>
          <p class="mb-1"><strong>Data:</strong> ${fDate(a.data_ato)}</p>
          <p class="mb-2"><strong>Livro/Folha/Termo/Matricula:</strong> ${a.livro||'-'} / ${a.folha||'-'} / ${a.termo||'-'} / ${a.matricula||'-'}</p>
          <div class="mt-auto pt-2">
            <button class="btn btn-warning btn-sm editar-ato" data-id="${a.id}"><i class="fa fa-pencil"></i></button>
            <button class="btn btn-danger btn-sm excluir-ato" data-id="${a.id}"><i class="fa fa-trash"></i></button>
          </div>
        </div>
      </div>
    </div>`);
  });
}

/* ---------- carregar todos os atos (uma vez) ---------- */
let ALL_ATOS = [];
function loadAtos(cb){
  $.get('load_atos.php',r=>{
    try{ ALL_ATOS = JSON.parse(r); cb(ALL_ATOS); }
    catch(e){ console.error(e,r); }
  });
}

/* ---------- range helpers ---------- */
let ACTIVE_RANGE = 'today';
let CUSTOM_FROM = null, CUSTOM_TO = null;

function setActiveChip(range){
  ACTIVE_RANGE = range;
  $('#dateChips .chip').removeClass('active');
  $('#dateChips .chip[data-range="'+range+'"]').addClass('active');
  $('#custom-range').toggle(range==='custom');
}

function getRangeBounds(){
  const today = new Date(); today.setHours(0,0,0,0);
  const endOfToday = new Date(today); endOfToday.setHours(23,59,59,999);
  switch (ACTIVE_RANGE){
    case 'today': return {start: today, end: endOfToday};
    case '7d': {
      const s=new Date(today); s.setDate(s.getDate()-6);
      return {start:s, end:endOfToday};
    }
    case '30d': {
      const s=new Date(today); s.setMonth(s.getMonth()-1); s.setDate(s.getDate()+1);
      return {start:s, end:endOfToday};
    }
    case '365d': {
      const s=new Date(today); s.setFullYear(s.getFullYear()-1); s.setDate(s.getDate()+1);
      return {start:s, end:endOfToday};
    }
    case 'custom': {
      if (!CUSTOM_FROM && !CUSTOM_TO) return {start:null,end:null};
      const s = CUSTOM_FROM? new Date(CUSTOM_FROM+'T00:00:00') : null;
      const e = CUSTOM_TO? new Date(CUSTOM_TO+'T23:59:59') : null;
      return {start:s, end:e};
    }
    case 'all':
    default: return {start:null,end:null};
  }
}

/* ---------- aplicar filtros e renderizar ---------- */
function applyFiltersAndRender(){
  const s={
    atr:$('#atribuicao').val(),
    cat:$('#categoria').val(),
    cpf:$('#cpf-cnpj').val(),
    nome:nrm($('#nome').val()),
    livro:$('#livro').val(),
    folha:$('#folha').val(),
    termo:$('#termo').val(),
    prot:$('#protocolo').val(),
    mat:$('#matricula').val(),
    data:$('#data-ato').val(), // data específica
    desc:nrm($('#descricao').val())
  };

  const bounds = s.data ? // se informar data específica, ignora chips
    { start:new Date(s.data+'T00:00:00'), end:new Date(s.data+'T23:59:59') } :
    getRangeBounds();

  const filtered = (ALL_ATOS||[]).filter(a=>{
    // filtros textuais
    const nm=nrm(a.partes_envolvidas.map(p=>p.nome).join(', '));
    const ds=nrm(a.descricao||'');
    const passText =
      (!s.atr||a.atribuicao.includes(s.atr))&&(!s.cat||a.categoria===s.cat)&&
      (!s.cpf||a.partes_envolvidas.some(p=>p.cpf.includes(s.cpf)))&&(!s.nome||nm.includes(s.nome))&&
      (!s.livro||String(a.livro||'').includes(s.livro))&&(!s.folha||String(a.folha||'').includes(s.folha))&&(!s.termo||String(a.termo||'').includes(s.termo))&&
      (!s.prot||String(a.protocolo||'').includes(s.prot))&&(!s.mat||String(a.matricula||'').includes(s.mat))&&
      (!s.desc||ds.includes(s.desc));

    if (!passText) return false;

    // filtro por período
    if (!bounds.start && !bounds.end) return true;
    const d = new Date(a.data_ato+'T12:00:00'); // TZ safe
    if (bounds.start && d < bounds.start) return false;
    if (bounds.end && d > bounds.end) return false;
    return true;
  });

  renderCards(filtered);
}

/* ---------- modal principal ---------- */
function openModal(id){
  $('#generate-pdf-button').data('id',id);
  // zera lista de selos
  $('#selos-list').empty();

  $.get('load_ato.php',{id},r=>{
    const a=JSON.parse(r);
    /* Barra de resumo no topo do body */
    const summary = $('#summary-bar').empty();
    summary.append(`<span class="summary-pill"><i class="fa fa-hashtag"></i>${a.atribuicao||'-'}</span>`);
    summary.append(`<span class="summary-pill"><i class="fa fa-folder-open-o"></i>${a.categoria||'-'}</span>`);
    summary.append(`<span class="summary-pill"><i class="fa fa-calendar-o"></i>${fDate(a.data_ato)}</span>`);

    /* Metadados em cards */
    const grid = $('#meta-grid').empty();
    const partes = a.partes_envolvidas.map(p=>`${p.cpf} – ${p.nome}`).join(', ');
    const metaItems = [
      {label:'Livro',        value:a.livro,       icon:'fa-book'},
      {label:'Folha',        value:a.folha,       icon:'fa-file-text-o'},
      {label:'Termo/Ordem',  value:a.termo,       icon:'fa-list-ol'},
      {label:'Protocolo',    value:a.protocolo,   icon:'fa-hashtag'},
      {label:'Matrícula',    value:a.matricula,   icon:'fa-address-book-o'},
      {label:'Partes',       value:partes,        icon:'fa-users'},
      {label:'Descrição',    value:a.descricao,   icon:'fa-align-left'}
    ];
    metaItems.forEach(it=>{
      if (it.value!==undefined && it.value!=='') {
        grid.append(`
          <div class="meta-card searchable">
            <div class="meta-ico"><i class="fa ${it.icon}"></i></div>
            <div>
              <div class="meta-label">${it.label}</div>
              <div class="meta-value">${it.value}</div>
            </div>
          </div>
        `);
      }
    });

    $('#view-cadastrado-por').text(a.cadastrado_por||'-');
    $('#view-data-cadastro').text(fDate2(a.data_cadastro||new Date()));

    const mod=$('#view-modificacoes').empty().addClass('timeline');
    (a.modificacoes||[]).forEach(m=>{
      mod.append(`<li class="searchable"><strong>${m.usuario}</strong> — ${fDate3(m.data_hora)}</li>`);
    });

    const anex=$('#view-anexos-list').empty();

    // Anexos do próprio arquivamento
    (a.anexos || []).forEach(f=>{
      const file=f.split('/').pop(), ext=(file.split('.').pop()||'').toLowerCase();
      anex.append(
        `<div class="attach-item visualizar-anexo" data-file="${f}">
          <span class="attach-chip">${ext.toUpperCase()}</span>
          <i class="fa ${iconByExt(ext)} attach-icon"></i>
          <div class="attach-name searchable" title="${file}">${file}</div>
        </div>`
      );
    });

    // Anexos que vieram das tarefas (prefixa ../tarefas/)
    if (Array.isArray(a.anexos_tarefa)) {
      a.anexos_tarefa.forEach(f=>{
        const rel = (f || '').replace(/^\/+/, '');  
        const url = '../tarefas/' + rel;
        const file = rel.split('/').pop();
        const ext  = (file.split('.').pop() || '').toLowerCase();
        anex.append(
          `<div class="attach-item visualizar-anexo" data-file="${url}">
            <span class="attach-chip">${ext.toUpperCase()}</span>
            <i class="fa ${iconByExt(ext)} attach-icon"></i>
            <div class="attach-name searchable" title="${file}">${file}</div>
          </div>`
        );
      });
    }

    // baseline para highlight e limpa a busca
    storeOriginals();
    $('#modalSearch').val('');
    applyHighlights('');

    // Carrega selos vinculados (tenta endpoint múltiplo; se não houver, usa o antigo simples)
    loadSelosVinculados(id);

    $('#anexosModal').modal('show');
  });
}

/* ---------- carregar selos vinculados ---------- */
function renderSelosChips(list){
  const box = $('#selos-list').empty();
  if (!list || !list.length) {
    box.append('<span class="muted">Nenhum selo vinculado.</span>');
    return;
  }
  list.forEach(num=>{
    const safe = (num||'').toString();
    box.append(`<span class="seal-chip">
      <i class="fa fa-certificate"></i> ${safe}
      <button type="button" class="copy-selo" data-copy="${safe}" title="Copiar número"><i class="fa fa-clone"></i></button>
    </span>`);
  });
}

function loadSelosVinculados(id){
  // 1) tenta um endpoint que retorne vários
  $.get('get_selos_modal.php',{id})
   .done(resp=>{
      try{
        const data = JSON.parse(resp);
        // possíveis formatos aceitos
        let list = [];
        if (Array.isArray(data)) list = data;
        else if (Array.isArray(data.selos)) list = data.selos.map(s=>s.numero_selo||s.numero||s);
        else if (Array.isArray(data.numeros)) list = data.numeros;
        else if (data.numero_selo) list = [data.numero_selo];
        renderSelosChips(list.filter(Boolean));
      }catch(e){
        // fallback para o endpoint antigo
        fallbackSingleSelo(id);
      }
   })
   .fail(()=>fallbackSingleSelo(id));
}
function fallbackSingleSelo(id){
  $.get('get_selo_modal.php',{id},s=>{
    try{
      const obj = JSON.parse(s);
      const n = obj.numero_selo || obj.numero;
      renderSelosChips(n?[n]:[]);
    }catch(e){
      renderSelosChips([]);
    }
  });
}

/* ---------- preview de anexos ---------- */
function openPreview(url){
  const ext = fileExt(url);
  const $frame = $('#previewFrame');
  const $img   = $('#previewImage');

  // reset
  $frame.hide().attr('src','');
  $img.hide().attr('src','');

  if (isPdf(ext)) {
    const abs = toAbsoluteUrl(url);
    const viewerUrl = buildPdfViewerUrl(abs);

    $('#btnOpenNewTab').attr('href', viewerUrl); 
    $('#btnDownload').attr('href', abs); 

    $frame.attr('src', viewerUrl).show();
    $('#previewModal').modal('show');
    return;
  }

  if (isImage(ext)) {
    $('#btnOpenNewTab').attr('href', url);
    $('#btnDownload').attr('href', url);
    $img.attr('src', url).show();
    $('#previewModal').modal('show');
    return;
  }

  window.open(toAbsoluteUrl(url), '_blank');
}

/* ---------- multiple modals: z-index + rolagem preservada ---------- */
$(document).on('show.bs.modal', '.modal', function () {
  const zIndex = 1040 + (10 * $('.modal:visible').length);
  $(this).css('z-index', zIndex);
  setTimeout(function() {
    $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
  }, 0);
});
$(document).on('hidden.bs.modal', '#previewModal', function () {
  if ($('.modal.show').length) $('body').addClass('modal-open');
});

/* ---------- ready ---------- */
$(function(){
  // categorias
  $.getJSON('categorias/categorias.json',d=>d.forEach(c=>$('#categoria').append($('<option>').val(c).text(c))));

  // carrega todos e aplica filtro padrão "Hoje"
  loadAtos(()=>{ setActiveChip('today'); applyFiltersAndRender(); });

  /* chips de período */
  $('#dateChips').on('click','.chip',function(){
    const range = $(this).data('range');
    if (range==='custom'){ setActiveChip('custom'); $('#custom-from').focus(); }
    else { setActiveChip(range); applyFiltersAndRender(); }
  });
  $('#apply-custom').on('click', function(e){
    e.preventDefault();
    CUSTOM_FROM = $('#custom-from').val() || null;
    CUSTOM_TO   = $('#custom-to').val() || null;
    if (!CUSTOM_FROM && !CUSTOM_TO){
      Swal.fire({icon:'warning', title:'Informe o intervalo', timer:1500, showConfirmButton:false});
      return;
    }
    setActiveChip('custom');
    applyFiltersAndRender();
  });

  /* botão de filtro (considera também os chips) */
  $('#filter-button').click(applyFiltersAndRender);

  /* máscara CPF/CNPJ */
  $('#cpf-cnpj')
  .on('focus', function () { $(this).unmask(); $(this).val($(this).val().replace(/\D/g, '')); })
  .on('input', function () { const digits = $(this).val().replace(/\D/g, '').slice(0, 14); $(this).val(digits); })
  .on('blur', function () {
      const digits = $(this).val(); if (!digits) return;
      const mask = digits.length <= 11 ? '000.000.000-00' : '00.000.000/0000-00';
      $(this).mask(mask, { reverse: true });
  });

  /* navegação / ações dos cards */
  $(document).on('click','.card-ato',function(e){ if($(e.target).closest('.btn').length) return; openModal($(this).data('id')); });
  $(document).on('click','.editar-ato',e=>{ e.stopPropagation(); location.href='edit_ato.php?id='+$(e.currentTarget).data('id'); });
  $(document).on('click','.excluir-ato',function(e){
    e.stopPropagation();
    const id=$(this).data('id');
    Swal.fire({title:'Tem certeza?',text:'Excluir?',icon:'warning',showCancelButton:true,confirmButtonText:'Sim'}).then(r=>{
      if(r.isConfirmed){
        $.post('delete_ato.php',{id},()=>Swal.fire('Excluído','Ato movido','success').then(()=>location.reload()))
          .fail(()=>Swal.fire('Erro','Não foi possível excluir','error'));
      }
    });
  });

  /* visualizar anexo */
  $(document).on('click','.visualizar-anexo', function(){
     const url = $(this).data('file');
     const ext = fileExt(url);
     if (isPdf(ext) || isImage(ext)) openPreview(url);
     else window.open(url, '_blank');
  });

  /* busca no modal principal (debounce) */
  let timer=null;
  $('#modalSearch').on('input', function(){
    clearTimeout(timer);
    const val = this.value;
    timer = setTimeout(()=>applyHighlights(val), 120);
  });

  /* botão da capa */
  $('#generate-pdf-button').click(function(){
    const id=$(this).data('id');
    $.getJSON('../style/configuracao.json',cfg=>{
      window.open((cfg.timbrado==='S'?'capa_arquivamento.php?id=':'capa-arquivamento.php?id=')+id,'_blank');
    }).fail(()=>alert('Erro ao carregar configuração.'));
  });

  /* copiar selo via chip */
  $(document).on('click','.copy-selo', function(e){
    e.preventDefault();
    e.stopPropagation();
    const txt = $(this).data('copy') || '';
    if (txt) copyText(txt);
  });
});
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
