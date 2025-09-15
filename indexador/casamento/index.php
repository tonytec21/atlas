<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Atlas - Indexador - Casamento</title>

  <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
  <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
  <link rel="stylesheet" href="../../style/css/style.css">
  <link rel="icon" href="../../style/img/favicon.png" type="image/png">
  <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="../../style/css/dataTables.bootstrap4.min.css">

  <style>
    /* =====================================================================
       TOKENS / THEME (respeita body.light-mode / body.dark-mode do sistema)
    ====================================================================== */
    :root{
      --brand: #1F7AE0;
      --brand-2: #0E56AE;

      --bg: #f6f8fb;
      --card: #ffffff;
      --text: #202531;
      --muted: #6b7280;
      --border: #e5e7eb;

      --chip-bg: #eef4ff;
      --chip-border: #cfe0ff;

      --table-head: #f3f4f6;
      --table-row: #ffffff;

      --shadow: 0 10px 26px rgba(0,0,0,.10);
      --soft-shadow: 0 6px 20px rgba(0,0,0,.08);

      --radius: 14px;
      --radius-lg: 16px;
    }

    body.light-mode{
      background: var(--bg);
      color: var(--text);
    }
    body.dark-mode{
      --bg: #0f141a;
      --card: #1b222b;
      --text: #e5e7eb;
      --muted: #9aa6b2;
      --border: #2a3440;

      --chip-bg: #202a36;
      --chip-border: #2e3a49;

      --table-head: #223043;
      --table-row: #1a2129;

      --shadow: 0 14px 32px rgba(0,0,0,.50);
      --soft-shadow: 0 10px 28px rgba(0,0,0,.45);
    }

    /* utilidades */
    .muted{ color: var(--muted)!important; }
    .soft-divider{ height:1px; background:var(--border); margin:1rem 0; }

    /* =====================================================================
       HERO
    ====================================================================== */
    .page-hero{
      background: linear-gradient(180deg, rgba(31,122,224,.12), rgba(31,122,224,0));
      border-radius: 18px;
      padding: 18px;
      margin: 20px 0 14px;
      box-shadow: var(--soft-shadow);
      border: 1px solid var(--border);
    }
    .page-hero .title-row{ display:flex; align-items:center; gap:12px; }
    .title-icon{
      width:44px; height:44px; border-radius:12px;
      background:#E7F1FF; color:#0E56AE;
      display:flex; align-items:center; justify-content:center; font-size:20px;
    }
    body.dark-mode .title-icon{ background:#152536; color:#99c3ff; }
    .page-hero h1{ font-weight:800; margin:0; letter-spacing:.2px; }
    .page-hero .subtitle{ font-size:.95rem; }
    .page-hero .ph-actions{ display:flex; gap:8px; flex-wrap:wrap; margin-left:auto; }
    .btn-soft{
      background: var(--chip-bg); color: var(--brand); border:1px solid var(--chip-border);
      padding: .5rem .8rem; border-radius: 12px; font-weight:700;
    }
    .btn-soft:hover{ filter: brightness(.98); }

    /* =====================================================================
       INPUTS / BOTÕES
    ====================================================================== */
    .form-label{ font-size:.85rem; color:var(--muted); margin-bottom:.25rem; }
    .form-control-modern{
      display:block; width:100%; padding:.6rem .9rem; font-size:1rem;
      color:var(--text); background:var(--card);
      border:1px solid var(--border); border-radius:12px;
      transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .form-control-modern:focus{
      border-color:#86b7fe; outline:0; box-shadow:0 0 0 .2rem rgba(13,110,253,.15);
      background:var(--card);
    }

    .btn-modern{
      width:100%; padding:12px 20px; font-weight:700; letter-spacing:.2px; color:#fff;
      background: linear-gradient(45deg, var(--brand), var(--brand-2));
      border: none; border-radius: 12px; transition: .25s all;
      box-shadow: 0 8px 22px rgba(32,117,216,.28);
    }
    .btn-modern:hover{ transform: translateY(-1px); box-shadow: 0 12px 28px rgba(32,117,216,.38); }
    .btn-modern:active{ transform: translateY(0); }

    .btn-action{
      width:40px; height:40px; margin-left:6px; border-radius:10px;
      display:inline-flex; align-items:center; justify-content:center;
      background: var(--chip-bg); color: var(--brand); border:1px solid var(--chip-border);
    }
    .btn-action:hover{ filter: brightness(.98); }

    /* =====================================================================
       TABELA / CARDS
    ====================================================================== */
    .table-responsive{ border-radius:14px; background:var(--card); box-shadow:var(--shadow); padding:10px; border: 1px solid var(--border); }
    .table thead th{ background: var(--table-head); color: var(--text); font-weight:700; border-color: var(--border)!important; }
    .table td, .table th{ vertical-align: middle; border-color: var(--border)!important; }
    .dataTables_wrapper .dataTables_filter label{ color: var(--muted); }
    .dataTables_wrapper .dataTables_length label{ color: var(--muted); }
    .dataTables_wrapper .dataTables_paginate .paginate_button{ color: var(--text)!important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current{ background: var(--chip-bg)!important; border-color: var(--chip-border)!important; }

    .table-bordered {border: none;}
    /* Cards (mobile) */
    .mobile-cards{ display:block; }
    .desktop-table{ display:none; }
    @media (min-width: 768px){
      .mobile-cards{ display:none; }
      .desktop-table{ display:block; }
    }
    .result-card{
      background: var(--card);
      border:1px solid var(--border);
      border-radius:16px; padding:16px; margin-bottom:14px;
      box-shadow: var(--soft-shadow);
    }
    .result-card .rc-top{ display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .result-card .badge{
      background: var(--chip-bg); color: var(--brand); border:1px solid var(--chip-border);
      padding:.35rem .55rem; font-size:.75rem; border-radius:10px;
    }
    .rc-title{ font-weight:800; font-size:1rem; margin:2px 0 6px; }
    .rc-meta{ font-size:.9rem; color:var(--muted); }
    .rc-actions{ margin-top:10px; display:flex; gap:8px; }

    /* =====================================================================
       DROPZONE (cadastro/edição)
    ====================================================================== */
    .dropzone{
      border:2px dashed var(--chip-border); background:linear-gradient(180deg, rgba(148,163,184,.08), rgba(148,163,184,0));
      border-radius:16px; padding:18px; text-align:center; transition:.2s ease; cursor:pointer;
    }
    .dropzone .dz-icon{
      width:48px; height:48px; border-radius:12px; background: var(--chip-bg);
      display:inline-flex; align-items:center; justify-content:center; margin-bottom:.5rem;
    }
    .dropzone .dz-icon i{ color: var(--brand); font-size: 1.25rem; }
    .dropzone:hover, .dropzone.dropzone--over{ background: rgba(99, 132, 255, .06); border-color:#99B7FF; }
    .dropzone small{ color:var(--muted); display:block; }

    /* =====================================================================
       MODAIS (visualização com design premium)
    ====================================================================== */
    .modal-dialog{ max-width: 90%; }
    .modal-content{
      background: var(--card);
      border:1px solid var(--border);
      border-radius: 18px;
      box-shadow: var(--shadow);
      overflow: hidden;
    }
    .modal-header{
      background: linear-gradient(135deg, rgba(31,122,224,.18), rgba(31,122,224,.06));
      border-bottom: 1px solid var(--border);
      padding: 14px 16px;
    }
    .modal-title{
      font-weight:900; letter-spacing:.2px; display:flex; align-items:center; gap:10px;
    }
    .modal-title .ticon{
      width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;
      background: var(--chip-bg); color: var(--brand);
    }
    .btn-close{ outline:none; border:0; background:transparent; font-size:1.6rem; line-height:1; color:var(--muted); }
    .btn-close:hover{ color: var(--text); transform: scale(1.05); }

    /* Chips resumo no topo do modal de Visualização */
    .summary-bar{ display:flex; flex-wrap:wrap; gap:8px; margin: 2px 0 10px; }
    .summary-pill{
      display:inline-flex; align-items:center; gap:8px; padding:.45rem .7rem; border-radius:999px;
      background: var(--chip-bg); color: var(--text); border:1px solid var(--chip-border); font-weight:800;
    }
    .summary-pill i{ opacity:.85; color: var(--brand); }

    /* Inputs readonly elegantes (visualização) */
    .readbox{
      width:100%; background: var(--card); color: var(--text);
      border:1px solid var(--border); border-radius: 12px; padding: .6rem .9rem;
    }

    /* Anexos no modal de visualização */
    .attach-grid{ display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:.75rem; }
    .attach-item{
      position:relative; border:1px solid var(--border); border-radius:12px; padding: .9rem .8rem;
      background:linear-gradient(180deg, rgba(148,163,184,.08), rgba(148,163,184,0)); color:var(--text);
      text-align:center; cursor:pointer; transition: transform .15s, background .15s, box-shadow .15s;
      box-shadow: var(--soft-shadow);
    }
    .attach-item:hover{ transform: translateY(-3px); background:linear-gradient(180deg, rgba(148,163,184,.12), rgba(148,163,184,.02)); }
    .attach-item.active{ border-color: var(--brand); box-shadow: 0 0 0 2px rgba(31,122,224,.15), var(--soft-shadow); }
    .attach-icon{ font-size:1.75rem; margin-bottom:.35rem; opacity:.9; color: var(--brand); }
    .attach-name{ font-size:.85rem; word-break: break-word; }
    .attach-chip{
      position:absolute; top:8px; right:8px; font-size:.7rem; padding:.2rem .45rem; border-radius:999px;
      background: var(--chip-bg); color: var(--brand); border:1px solid var(--chip-border);
    }

    /* Pré-visualização de anexo (mesmo estilo para view e edição) */
    .preview-frame{
      width:100%;
      height:70vh;
      border:1px solid var(--border);
      border-radius:12px;
      background: var(--card);
      overflow:hidden;
      box-shadow: var(--soft-shadow);
    }
    .preview-frame iframe,
    .preview-frame img{
      width:100%;
      height:100%;
      border:0;
      display:block;
      object-fit:contain;
      background:#0b0f14;
    }

    /* Ajuste z-index backdrop */
    .modal-backdrop.show{ z-index: 1039; backdrop-filter: blur(4px); background-color: rgba(0,0,0,.45); }

    /* =======================================================================
       FILTROS
    ======================================================================= */
    .filter-card{
      background:var(--card); border:1px solid var(--border);
      border-radius: 16px; padding:16px 16px 10px; box-shadow: var(--shadow);
      margin-bottom: 15px;
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

    /* =====================================================================
       FULLSCREEN MODALS (MOBILE)
       Ocupa 100% da tela em dispositivos <= 768px, com header fixo e body rolável
    ====================================================================== */
    @media (max-width: 767.98px){
      #modalCadastro .modal-dialog,
      #modalEditar .modal-dialog,
      #modalView   .modal-dialog{
        max-width: 100% !important;
        width: 100%;
        margin: 0;
        height: 100%;
      }
      #modalCadastro .modal-content,
      #modalEditar .modal-content,
      #modalView   .modal-content{
        height: 100%;
        border-radius: 0; /* tela cheia sem cantos arredondados no mobile */
      }
      #modalCadastro .modal-header,
      #modalEditar .modal-header,
      #modalView   .modal-header{
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--card);
        border-bottom: 1px solid var(--border);
      }
      #modalCadastro .modal-body,
      #modalEditar .modal-body,
      #modalView   .modal-body{
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 14px;
      }
      /* Ajusta a altura do preview para caber melhor em tela cheia no mobile */
      .preview-frame{
        height: calc(100vh - 220px);
      }
    }
  </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">

    <!-- HERO -->
    <section class="page-hero">
      <div class="title-row justify-content-between flex-wrap">
        <div class="d-flex align-items-center gap-2">
          <div class="title-icon"><i class="fa fa-ring"></i></div>
          <div>
            <h1>Indexador de Casamento</h1>
            <div class="subtitle muted">Cadastro, consulta e gestão com anexos e validações.</div>
          </div>
        </div>
        <div class="ph-actions">
          <a href="../index.php" class="btn btn-soft"><i class="fa fa-home"></i> Central de Acesso</a>
          <a href="../carga_crc/exportar_carga_casamento.php" class="btn btn-soft"><i class="fa fa-file-export"></i> Exportar carga CRC</a>
          <button class="btn btn-success" data-toggle="modal" data-target="#modalCadastro"><i class="fa fa-plus"></i> Novo Registro</button>
        </div>
      </div>
    </section>

    <!-- FILTROS -->
    <div class="filter-card">
    <div class="row mb-3">
      <div class="col-md-6 col-lg-4 mb-2">
        <label for="filtro-conjuge" class="form-label">Nome do Cônjuge (1 ou 2)</label>
        <input type="text" id="filtro-conjuge" class="form-control-modern" placeholder="Ex: MARIA SILVA">
      </div>
        <div class="col-md-6 col-lg-4 mb-2">
        <label for="filtro-conjuge-casado" class="form-label">Nome de Casado (1 ou 2)</label>
        <input type="text" id="filtro-conjuge-casado" class="form-control-modern" placeholder="Ex: MARIA SILVA DE SOUZA">
      </div>

      <div class="col-6 col-lg-2 mb-2">
        <label for="filtro-termo" class="form-label">Termo</label>
        <input type="number" id="filtro-termo" class="form-control-modern" min="0" placeholder="Termo">
      </div>
      <div class="col-6 col-lg-2 mb-2">
        <label for="filtro-livro" class="form-label">Livro</label>
        <select id="filtro-livro" class="form-control-modern">
          <option value="">Selecione</option>
        </select>
      </div>
      <div class="col-6 col-lg-2 mb-2">
        <label for="filtro-folha" class="form-label">Folha</label>
        <input type="number" id="filtro-folha" class="form-control-modern" min="0" placeholder="Folha">
      </div>
      <div class="col-6 col-lg-2 mb-2">
        <label for="filtro-tipo" class="form-label">Tipo</label>
        <select id="filtro-tipo" class="form-control-modern">
          <option value="">Todos</option>
          <option value="CIVIL">Civil</option>
          <option value="RELIGIOSO">Religioso</option>
        </select>
      </div>
      <div class="col-6 col-lg-3 mb-2">
        <label for="filtro-regime" class="form-label">Regime de Bens</label>
        <select id="filtro-regime" class="form-control-modern">
          <option value="">Todos</option>
          <option value="COMUNHAO_PARCIAL">Comunhão Parcial</option>
          <option value="COMUNHAO_UNIVERSAL">Comunhão Universal</option>
          <option value="PARTICIPACAO_FINAL_AQUESTOS">Participação Final nos Aqüestros</option>
          <option value="SEPARACAO_BENS">Separação de Bens</option>
        </select>
      </div>
      <div class="col-6 col-lg-3 mb-2">
        <label for="filtro-data-casamento" class="form-label">Data do Casamento</label>
        <input type="text" id="filtro-data-casamento" class="form-control-modern" placeholder="DD/MM/AAAA">
      </div>
      <div class="col-6 col-lg-3 mb-2">
        <label for="filtro-data-registro" class="form-label">Data de Registro</label>
        <input type="text" id="filtro-data-registro" class="form-control-modern" placeholder="DD/MM/AAAA">
      </div>
      <div class="col-6 col-lg-3 mb-2 d-flex gap-2">
        <button id="btn-filtrar" class="btn btn-primary w-100">
          <i class="fa fa-filter"></i> Filtrar
        </button>
      </div>
    </div>
    </div>

    <!-- TABELA (desktop) -->
    <div class="table-responsive desktop-table">
      <table id="tabelaResultados" class="table table-striped table-bordered mb-0">
        <thead>
        <tr>
          <th>Termo</th>
          <th>Livro</th>
          <th>Folha</th>
          <th>Tipo</th>
          <th>Cônjuges</th>
          <th>Casado (1/2)</th>
          <th>Regime</th>
          <th>Data Casamento</th>
          <th>Data Registro</th>
          <!-- <th>Matrícula</th> -->
          <th style="width:110px">Ações</th>
        </tr>
        </thead>
        <tbody id="tbody-registros"></tbody>
      </table>
    </div>

    <!-- CARDS (mobile) -->
    <div id="cardsContainer" class="mobile-cards"></div>

    <!-- MODAL CADASTRO -->
    <div class="modal fade" id="modalCadastro" tabindex="-1" role="dialog" aria-labelledby="modalCadastroLabel" aria-hidden="true">
      <div class="modal-dialog modal-custom modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalCadastroLabel">
              <span class="ticon"><i class="fa fa-ring"></i></span> Adicionar Registro de Casamento
            </h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
          </div>
          <div class="modal-body">
            <form id="form-cadastro">
              <div class="form-row">
                <div class="form-group col-6 col-md-2">
                <label class="form-label">Livro</label>
                <input type="number" class="form-control-modern numeric-only"
                        name="livro" id="c_livro" min="1" step="1" inputmode="numeric" required>
                </div>

                <div class="form-group col-6 col-md-2">
                <label class="form-label">Folha</label>
                <input type="number" class="form-control-modern numeric-only"
                        name="folha" id="c_folha" min="1" max="999" step="1" inputmode="numeric" data-maxlen="3" required>
                </div>

                <div class="form-group col-6 col-md-2">
                <label class="form-label">Termo</label>
                <input type="number" class="form-control-modern numeric-only"
                        name="termo" id="c_termo" min="1" step="1" inputmode="numeric" required>
                </div>

                <div class="form-group col-6 col-md-3">
                  <label class="form-label">Tipo de Casamento</label>
                  <select class="form-control-modern" name="tipo_casamento" id="c_tipo_casamento" required>
                    <option value="" disabled selected>Selecione</option>
                    <option value="CIVIL">Civil</option>
                    <option value="RELIGIOSO">Religioso</option>
                  </select>
                </div>
                <div class="form-group col-6 col-md-3">
                  <label class="form-label">Data do Registro</label>
                  <input type="text" class="form-control-modern" name="data_registro" id="c_data_registro" placeholder="DD/MM/AAAA" required>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group col-12 col-md-4">
                  <label class="form-label">1º Cônjuge</label>
                  <input type="text" class="form-control-modern" name="conjuge1_nome" id="c_conjuge1_nome" required>
                </div>
                <div class="form-group col-12 col-md-2">
                  <label class="form-label">Sexo</label>
                  <select class="form-control-modern" name="conjuge1_sexo" id="c_conjuge1_sexo" required>
                    <option value="" disabled selected>-</option>
                    <option value="M">Masculino</option>
                    <option value="F">Feminino</option>
                    <option value="I">Ignorado</option>
                  </select>
                </div>

                <div class="form-group col-12 col-md-4">
                  <label class="form-label">2º Cônjuge</label>
                  <input type="text" class="form-control-modern" name="conjuge2_nome" id="c_conjuge2_nome" required>
                </div>
                <div class="form-group col-12 col-md-2">
                  <label class="form-label">Sexo</label>
                  <select class="form-control-modern" name="conjuge2_sexo" id="c_conjuge2_sexo" required>
                    <option value="" disabled selected>-</option>
                    <option value="M">Masculino</option>
                    <option value="F">Feminino</option>
                    <option value="I">Ignorado</option>
                  </select>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group col-12 col-md-6">
                    <label class="form-label">Nome de Casado - 1º Cônjuge (opcional)</label>
                    <input type="text" class="form-control-modern" name="conjuge1_nome_casado" id="c_conjuge1_nome_casado">
                </div>
                <div class="form-group col-12 col-md-6">
                    <label class="form-label">Nome de Casado - 2º Cônjuge (opcional)</label>
                    <input type="text" class="form-control-modern" name="conjuge2_nome_casado" id="c_conjuge2_nome_casado">
                </div>
              </div>


              <div class="form-row">
                <div class="form-group col-12 col-md-5">
                  <label class="form-label">Regime de Bens</label>
                  <select class="form-control-modern" name="regime_bens" id="c_regime_bens" required>
                    <option value="" disabled selected>Selecione</option>
                    <option value="COMUNHAO_PARCIAL">Comunhão Parcial</option>
                    <option value="COMUNHAO_UNIVERSAL">Comunhão Universal</option>
                    <option value="PARTICIPACAO_FINAL_AQUESTOS">Participação Final nos Aqüestros</option>
                    <option value="SEPARACAO_BENS">Separação de Bens</option>
                  </select>
                </div>
                <div class="form-group col-12 col-md-4">
                  <label class="form-label">Data do Casamento</label>
                  <input type="text" class="form-control-modern" name="data_casamento" id="c_data_casamento" placeholder="DD/MM/AAAA" required>
                </div>
              </div>

              <!-- Anexos -->
              <div class="form-group">
                <label class="form-label">Anexo (PDF/Imagem)</label>
                <div id="dropzone-cadastro" class="dropzone">
                  <div class="dz-icon"><i class="fa fa-cloud-upload"></i></div>
                  <div><strong>Arraste e solte</strong> o arquivo aqui ou <u>clique</u></div>
                  <small>PDF, PNG, JPG, JPEG, WEBP</small>
                </div>
                <input type="file" id="c_pdf_file" accept="application/pdf,image/*" class="d-none">
                <button type="button" id="btn-add-anexo" class="btn btn-secondary w-100 mt-2"><i class="fa fa-paperclip"></i> Adicionar Anexo</button>
              </div>
              <div class="mt-3">
                <h6 class="mb-2">Anexos (pendentes de salvar)</h6>
                <div class="table-responsive">
                  <table class="table table-bordered mb-0">
                    <thead><tr><th>Arquivo</th><th style="width:120px">Ações</th></tr></thead>
                    <tbody id="tb-anexos-temp"></tbody>
                  </table>
                </div>
              </div>

              <button type="submit" class="btn-modern mt-3">
                <i class="fa fa-save"></i> Salvar
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL EDIÇÃO -->
    <div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" style="max-width: 80%;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><span class="ticon"><i class="fa fa-edit"></i></span> Editar Registro</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
          </div>
          <div class="modal-body">
            <form id="form-editar">
              <input type="hidden" id="e_id" name="id">
              <div class="row g-3">
                <div class="col-md-2">
                  <label class="form-label">Livro</label>
                  <input type="number" class="form-control-modern" id="e_livro" name="livro" min="1" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Folha</label>
                  <input type="number" class="form-control-modern" id="e_folha" name="folha" min="1" max="999" inputmode="numeric" data-maxlen="3" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Termo</label>
                  <input type="number" class="form-control-modern" id="e_termo" name="termo" min="1" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Tipo de Casamento</label>
                  <select class="form-control-modern" id="e_tipo_casamento" name="tipo_casamento" required>
                    <option value="CIVIL">Civil</option>
                    <option value="RELIGIOSO">Religioso</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Data do Registro</label>
                  <input type="text" class="form-control-modern" id="e_data_registro" name="data_registro" placeholder="DD/MM/AAAA" required>
                </div>

                <div class="col-md-4">
                  <label class="form-label">1º Cônjuge</label>
                  <input type="text" class="form-control-modern" id="e_conjuge1_nome" name="conjuge1_nome" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Sexo</label>
                  <select class="form-control-modern" id="e_conjuge1_sexo" name="conjuge1_sexo" required>
                    <option value="M">Masculino</option>
                    <option value="F">Feminino</option>
                    <option value="I">Ignorado</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">2º Cônjuge</label>
                  <input type="text" class="form-control-modern" id="e_conjuge2_nome" name="conjuge2_nome" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Sexo</label>
                  <select class="form-control-modern" id="e_conjuge2_sexo" name="conjuge2_sexo" required>
                    <option value="M">Masculino</option>
                    <option value="F">Feminino</option>
                    <option value="I">Ignorado</option>
                  </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nome de Casado - 1º Cônjuge (opcional)</label>
                    <input type="text" class="form-control-modern" id="e_conjuge1_nome_casado" name="conjuge1_nome_casado">
                    </div>
                    <div class="col-md-6">
                    <label class="form-label">Nome de Casado - 2º Cônjuge (opcional)</label>
                    <input type="text" class="form-control-modern" id="e_conjuge2_nome_casado" name="conjuge2_nome_casado">
                </div>

                <div class="col-md-5">
                  <label class="form-label">Regime de Bens</label>
                  <select class="form-control-modern" id="e_regime_bens" name="regime_bens" required>
                    <option value="COMUNHAO_PARCIAL">Comunhão Parcial</option>
                    <option value="COMUNHAO_UNIVERSAL">Comunhão Universal</option>
                    <option value="PARTICIPACAO_FINAL_AQUESTOS">Participação Final nos Aqüestros</option>
                    <option value="SEPARACAO_BENS">Separação de Bens</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Data do Casamento</label>
                  <input type="text" class="form-control-modern" id="e_data_casamento" name="data_casamento" placeholder="DD/MM/AAAA" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Matrícula</label>
                  <input type="text" class="form-control-modern" id="e_matricula" name="matricula" readonly>
                </div>
              </div>

              <!-- PRÉ-VISUALIZAÇÃO DENTRO DO MODAL (EDIÇÃO) -->
              <div class="mt-4">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-2">Visualização Rápida do Anexo</h6>
                </div>
                <div id="e_preview_body" class="preview-frame" style="display:none;"></div>
              </div>

              <div class="mt-3 p-3" style="border:1px solid var(--border); border-radius:14px; background:var(--card);">
                <div class="row align-items-center">
                  <div class="col-md-8"><h6 class="mb-2">Anexos</h6></div>
                  <div class="col-md-4">
                    <div id="dropzone-edicao" class="dropzone">
                      <div class="dz-icon"><i class="fa fa-cloud-upload"></i></div>
                      <div><strong>Arraste e solte</strong> aqui ou <u>clique</u></div>
                      <small>PDF, PNG, JPG, JPEG, WEBP</small>
                    </div>
                    <input type="file" id="e_pdf_file" class="d-none" accept="application/pdf,image/*">
                  </div>
                </div>
                <div class="table-responsive mt-2">
                  <table class="table table-bordered mb-0">
                    <thead><tr><th>Arquivo</th><th style="width:260px">Ações</th></tr></thead>
                    <tbody id="e_tb_anexos"></tbody>
                  </table>
                </div>
              </div>

              <div class="mt-4">
                <button type="submit" class="btn-modern"><i class="fa fa-save"></i> Salvar Alterações</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL VISUALIZAÇÃO -->
    <div class="modal fade" id="modalView" tabindex="-1" aria-labelledby="modalViewLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" style="max-width: 85%;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <span class="ticon"><i class="fa fa-file-text-o"></i></span> Visualização do Registro
            </h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
          </div>
          <div class="modal-body">
            <!-- chips resumo -->
            <div id="view-summary" class="summary-bar"></div>

            <div class="row g-4">
              <div class="col-md-4">
                <label class="form-label">1º Cônjuge</label>
                <input type="text" class="readbox" id="v_conjuge1" readonly>
              </div>
              <div class="col-md-2">
                <label class="form-label">Sexo</label>
                <input type="text" class="readbox" id="v_conjuge1_sexo" readonly>
              </div>

              <div class="col-md-4">
                <label class="form-label">2º Cônjuge</label>
                <input type="text" class="readbox" id="v_conjuge2" readonly>
              </div>
              <div class="col-md-2">
                <label class="form-label">Sexo</label>
                <input type="text" class="readbox" id="v_conjuge2_sexo" readonly>
              </div>

              <div class="col-md-6">
                <label class="form-label">Nome de Casado (1º Cônjuge)</label>
                <input type="text" class="readbox" id="v_conjuge1_nome_casado" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Nome de Casado (2º Cônjuge)</label>
                <input type="text" class="readbox" id="v_conjuge2_nome_casado" readonly>
              </div>

              <div class="col-md-4">
                <label class="form-label">Regime de Bens</label>
                <input type="text" class="readbox" id="v_regime" readonly>
              </div>
              <div class="col-md-3">
                <label class="form-label">Data Casamento</label>
                <input type="text" class="readbox" id="v_data_casamento" readonly>
              </div>
              <div class="col-md-5">
                <label class="form-label">Matrícula</label>
                <input type="text" class="readbox" id="v_matricula" readonly>
              </div>
            </div>

            <div class="soft-divider"></div>
            <!-- PRÉ-VISUALIZAÇÃO DENTRO DO MODAL (VISUALIZAÇÃO) -->
            <div id="anexoPreviewContainer" class="mt-3" style="display:none;">
              <div class="d-flex justify-content-between align-items-center">
                <h6 id="anexoPreviewTitle" class="mb-2">Visualização do Anexo</h6>
              </div>
              <div id="anexoPreviewBody" class="preview-frame"></div>
            </div>

          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="../../script/jquery-3.5.1.min.js"></script>
<script src="../../script/bootstrap.min.js"></script>
<script src="../../script/bootstrap.bundle.min.js"></script>
<script src="../../script/jquery.mask.min.js"></script>
<script src="../../script/jquery.dataTables.min.js"></script>
<script src="../../script/dataTables.bootstrap4.min.js"></script>
<script src="../../script/sweetalert2.js"></script>

<script>
  /* ============================================================
     Caminho do viewer do PDF.js
  ============================================================ */
  const PDFJS_VIEWER_PATH = '../../provimentos/pdfjs/web/viewer.html';

  // =================== Helpers de Data (DD/MM/AAAA) ===================
  function isDateBR(str){ return /^(\d{2})\/(\d{2})\/(\d{4})$/.test(String(str || "").trim()); }
  function formatDateBR(iso){
    if(!iso) return "";
    var s = String(iso).slice(0,10);
    var parts = s.split("-");
    if(parts.length !== 3) return "";
    var y = parts[0], m = ("0"+parts[1]).slice(-2), d = ("0"+parts[2]).slice(-2);
    return d + "/" + m + "/" + y;
  }
  function parseBRtoDateObj(str){
    if(!isDateBR(str)) return null;
    var p = str.split("/");
    var d = parseInt(p[0],10), m = parseInt(p[1],10), y = parseInt(p[2],10);
    var dt = new Date(y, m-1, d);
    if(dt.getFullYear() !== y || dt.getMonth() !== (m-1) || dt.getDate() !== d) return null;
    return dt;
  }
  function toISOFromBR(str){
    var d = parseBRtoDateObj(str); if(!d) return "";
    var y = d.getFullYear();
    var m = ("0"+(d.getMonth()+1)).slice(-2);
    var da = ("0"+d.getDate()).slice(-2);
    return y + "-" + m + "-" + da;
  }

  // =================== Sanitização (cliente) ===================
  function isAsciiLetter(ch){
    if(!ch) return false;
    var base = String(ch).normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    return /[A-Za-z]/.test(base);
  }
  function sanitizeTextClient(s){
    if(!s) return s;
    s = String(s).replace(/\r\n|\r|\n/g,' ').trim();
    s = s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    s = s.replace(/'/g, function(match, offset, str){
      var prev = str.charAt(offset-1), next = str.charAt(offset+1);
      if(isAsciiLetter(prev) && isAsciiLetter(next)) return "'";
      return '&#39;';
    });
    return s;
  }
  function escapeAttr(s){ return String(s||'').replace(/"/g,'&quot;'); }

  // Mapeia o código de sexo para rótulo (usado no modal de visualização)
  function sexoLabel(val){
    switch (String(val || '').toUpperCase()) {
      case 'M': return 'Masculino';
      case 'F': return 'Feminino';
      case 'I': return 'Ignorado';
      default:  return String(val || '');
    }
  }

  // == Converte caminho relativo em URL absoluta (para o PDF.js não quebrar) ==
  function toAbsoluteURL(p){
    try{
      // Se já for absoluta (http/https/data/blob), devolve como está
      if(/^https?:\/\//i.test(p) || /^data:|^blob:/i.test(p)) return p;
      // Resolve relativo ao documento atual
      const u = new URL(p, window.location.href);
      return u.href;
    }catch(e){
      return p;
    }
  }

  // =================== UI Render: Tabela e Cards ===================
  function renderCards(rows){
    var $c = $('#cardsContainer').empty();
    (Array.isArray(rows) ? rows : []).forEach(function(r){
      var tipo = r.tipo_casamento || '';
      var regime = r.regime_bens || '';
      var conj = (r.conjuge1_nome||'') + ' (' + (r.conjuge1_sexo||'') + ') • ' + (r.conjuge2_nome||'') + ' (' + (r.conjuge2_sexo||'') + ')';
      var casados = (r.conjuge1_nome_casado || '-') + ' • ' + (r.conjuge2_nome_casado || '-');
      var html = ''
        + '<div class="result-card">'
        + '  <div class="rc-top">'
        + '    <div class="badge">'+ tipo +'</div>'
        + '    <div class="text-monospace small">'+ (r.matricula||'') +'</div>'
        + '  </div>'
        + '  <div class="rc-title">Termo '+ r.termo +' • Livro '+ r.livro +' • Folha '+ r.folha +'</div>'
        + '  <div class="rc-meta">'+ conj +'</div>'
        + '  <div class="rc-meta">Casado: <strong>'+ casados +'</strong></div>'
        + '  <div class="rc-meta">Regime: <strong>'+ regime +'</strong></div>'
        + '  <div class="rc-meta">Casamento: <strong>'+ formatDateBR(r.data_casamento||'') +'</strong> &nbsp; | &nbsp; Registro: <strong>'+ formatDateBR(r.data_registro||'') +'</strong></div>'
        + '  <div class="rc-actions">'
        + '    <button class="btn btn-info btn-action btn-view" title="Visualizar" data-id="'+ r.id +'"><i class="fa fa-eye"></i></button>'
        + '    <button class="btn btn-primary btn-action btn-edit" title="Editar" data-id="'+ r.id +'"><i class="fa fa-pencil"></i></button>'
        + '  </div>'
        + '</div>';
      $c.append(html);
    });
  }

  function renderTable(rows){
    if ($.fn.DataTable.isDataTable('#tabelaResultados')) {
      $('#tabelaResultados').DataTable().clear().destroy();
    }

    var $tb = $('#tbody-registros').empty();
    (Array.isArray(rows) ? rows : []).forEach(function(r){
      var conjs = (r.conjuge1_nome||'') + ' (' + (r.conjuge1_sexo||'') + ') & ' + (r.conjuge2_nome||'') + ' (' + (r.conjuge2_sexo||'') + ')';
      var casados = (r.conjuge1_nome_casado || '-') + ' & ' + (r.conjuge2_nome_casado || '-');
      var tr = ''
        + '<tr>'
        + '  <td>'+ r.termo +'</td>'
        + '  <td>'+ r.livro +'</td>'
        + '  <td>'+ r.folha +'</td>'
        + '  <td>'+ (r.tipo_casamento||'') +'</td>'
        + '  <td>'+ conjs +'</td>'
        + '  <td>'+ casados +'</td>'
        + '  <td>'+ (r.regime_bens||'') +'</td>'
        + '  <td data-order="'+ (r.data_casamento||'') +'">'+ formatDateBR(r.data_casamento||'') +'</td>'
        + '  <td data-order="'+ (r.data_registro||'') +'">'+ formatDateBR(r.data_registro||'') +'</td>'
        // + '  <td>'+ (r.matricula||'') +'</td>'
        + '  <td>'
        + '    <button class="btn btn-info btn-action btn-view" title="Visualizar" data-id="'+ r.id +'"><i class="fa fa-eye"></i></button>'
        + '    <button class="btn btn-primary btn-action btn-edit" title="Editar" data-id="'+ r.id +'"><i class="fa fa-pencil"></i></button>'
        + '  </td>'
        + '</tr>';
      $tb.append(tr);
    });

    $('#tabelaResultados').DataTable({
      language: { url: "../../style/Portuguese-Brasil.json" },
      pageLength: 10,
      order: [[0,'desc']],
      destroy: true
    });
  }

  function renderAll(rows){
    renderTable(rows);
    renderCards(rows);
  }

  // =================== Filtros / Carregamento ===================
  function carregarLivros(){
    $.ajax({
      url: 'carregar_livros.php',
      method: 'GET',
      dataType: 'json',
      cache: false,
      success: function(data){
        var $sel = $('#filtro-livro');
        $sel.find('option:not(:first)').remove();
        if(!Array.isArray(data)) return;
        data.forEach(function(v){
          var val = parseInt(v, 10);
          if(!isNaN(val)){
            $sel.append($('<option>').val(val).text('Livro ' + val));
          }
        });
      }
    });
  }

  function buildFilterPayload(){
    var payload = {};
    var conjuge        = String($('#filtro-conjuge').val() || '').trim();
    var conjugeCasado  = String($('#filtro-conjuge-casado').val() || '').trim();
    var termo          = String($('#filtro-termo').val() || '').trim();
    var livro          = String($('#filtro-livro').val() || '').trim();
    var folha          = String($('#filtro-folha').val() || '').trim();
    var tipo           = String($('#filtro-tipo').val() || '');
    var regime         = String($('#filtro-regime').val() || '');
    var dtCas          = String($('#filtro-data-casamento').val() || '').trim();
    var dtReg          = String($('#filtro-data-registro').val() || '').trim();

    // Valida datas (se preenchidas)
    if(dtCas && !isDateBR(dtCas)){
        Swal.fire('Data inválida', 'Use DD/MM/AAAA para Data do Casamento.', 'warning');
        return null;
    }
    if(dtReg && !isDateBR(dtReg)){
        Swal.fire('Data inválida', 'Use DD/MM/AAAA para Data de Registro.', 'warning');
        return null;
    }

    // Apenas adiciona se houver valor
    if(conjuge){       payload.q = conjuge; }
    if(conjugeCasado){ payload.qCasado = conjugeCasado; }
    if(termo){         payload.termo = termo; }
    if(livro){         payload.livro = livro; }
    if(folha){         payload.folha = folha; }
    if(tipo){          payload.tipo = tipo; }
    if(regime){        payload.regime = regime; }
    if(dtCas){         payload.dataCasamento = dtCas; }
    if(dtReg){         payload.dataRegistro  = dtReg; }

    // Se não houver nenhum filtro, limite padrão
    var has = Object.keys(payload).length > 0;
    if(!has){ payload.limit = 20; } else { payload.hasFilter = 1; }

    return payload;
  }


  function carregarRegistros(){
    var payload = buildFilterPayload(); if(payload === null) return;
    $.ajax({
      url: 'carregar_registros.php',
      method: 'POST',               // POST para garantir leitura correta no servidor
      data: payload,
      dataType: 'json',
      cache: false,
      success: function(resp){
        renderAll(resp);
      },
      error: function(){
        Swal.fire('Erro','Falha ao carregar registros.','error');
      }
    });
  }

  // =================== Drag & Drop util ===================
  function bindDropzone(zoneEl, inputEl, onFiles){
    var $zone = $(zoneEl), $input = $(inputEl);
    var openPicker = function(){ $input.trigger('click'); };
    $zone.on('click', openPicker);
    $zone.on('dragover', function(e){ e.preventDefault(); e.stopPropagation(); $zone.addClass('dropzone--over'); });
    $zone.on('dragleave dragend', function(e){ e.preventDefault(); e.stopPropagation(); $zone.removeClass('dropzone--over'); });
    $zone.on('drop', function(e){
      e.preventDefault(); e.stopPropagation(); $zone.removeClass('dropzone--over');
      var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
      if(files && files.length){ onFiles(files); }
    });
    $input.on('change', function(){
      var files = this.files;
      if(files && files.length){ onFiles(files); this.value = ''; }
    });
  }

  // =================== Upload temporário (cadastro) ===================
  var anexosTemp = [];
  function uploadCadastroFiles(files){
    Array.prototype.slice.call(files).forEach(function(file){
      var fd = new FormData(); fd.append('arquivo_pdf', file);
      $.ajax({
        type: 'POST',
        url: 'upload_temp_anexo.php',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(r){
          if(r && r.success){
            anexosTemp.push(r.file_path);
            $('#tb-anexos-temp').append(
              '<tr><td>'+ file.name +'</td><td>'
              + '<button type="button" class="btn btn-danger btn-sm btn-rem-anexo"><i class="fa fa-trash"></i> Remover</button>'
              + '</td></tr>'
            );
          } else {
            Swal.fire('Erro', (r && r.error) ? r.error : 'Falha no upload temporário.','error');
          }
        },
        error: function(){ Swal.fire('Erro','Falha no upload.','error'); }
      });
    });
  }

  $('#btn-add-anexo').on('click', function(){ $('#c_pdf_file').trigger('click'); });
  $(document).on('click','.btn-rem-anexo', function(){
    var idx = $(this).closest('tr').index();
    if(idx > -1){ anexosTemp.splice(idx,1); }
    $(this).closest('tr').remove();
  });

  // =================== Cadastro ===================
  $('#form-cadastro').on('submit', function(e){
    e.preventDefault();
    var drStr = String($('#c_data_registro').val()||'').trim();
    var dcStr = String($('#c_data_casamento').val()||'').trim();
    if(!isDateBR(drStr) || !isDateBR(dcStr)) return Swal.fire('Data inválida','Use DD/MM/AAAA.','warning');

    var dr = parseBRtoDateObj(drStr), dc = parseBRtoDateObj(dcStr);
    if(!dr || !dc) return Swal.fire('Data inválida','Verifique as datas.','warning');

    var hoje = new Date(); hoje.setHours(0,0,0,0);
    if(dc.getTime() > dr.getTime()) return Swal.fire('Regra de Data','Data do casamento deve ser menor ou igual à data do registro.','warning');
    if(dr.getTime() > hoje.getTime()) return Swal.fire('Regra de Data','Data de registro deve ser menor ou igual à data atual.','warning');

    $('#c_conjuge1_nome').val(sanitizeTextClient($('#c_conjuge1_nome').val()));
    $('#c_conjuge2_nome').val(sanitizeTextClient($('#c_conjuge2_nome').val()));
    $('#c_conjuge1_nome_casado').val(sanitizeTextClient($('#c_conjuge1_nome_casado').val()));
    $('#c_conjuge2_nome_casado').val(sanitizeTextClient($('#c_conjuge2_nome_casado').val()));

    var fd = new FormData(this);
    anexosTemp.forEach(function(p){ fd.append('arquivo_pdf_paths[]', p); });

    $.ajax({
      type: 'POST',
      url: 'salvar_registro.php',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(r){
        if(r && r.status === 'duplicate'){
          Swal.fire({
            title:'Registro Duplicado!',
            text:'Já existe registro com mesmos livro/folha/termo/data. Deseja continuar?',
            icon:'warning', showCancelButton:true, confirmButtonText:'Sim, continuar', cancelButtonText:'Cancelar'
          }).then(function(ok){
            if(ok.isConfirmed){
              fd.append('forcar','1');
              $.ajax({
                type:'POST', url:'salvar_registro.php', data:fd, processData:false, contentType:false, dataType:'json',
                success: function(r2){
                  if(r2 && r2.status === 'success'){
                    Swal.fire('Sucesso','Registro salvo. Matrícula: '+ r2.matricula,'success').then(function(){
                      $('#modalCadastro').modal('hide'); anexosTemp=[]; $('#tb-anexos-temp').empty(); $('#form-cadastro')[0].reset(); carregarRegistros();
                    });
                  } else {
                    Swal.fire('Erro', (r2 && r2.message) ? r2.message : 'Falha ao salvar.','error');
                  }
                }
              });
            }
          });
        } else if(r && r.status === 'success'){
          Swal.fire('Sucesso','Registro salvo. Matrícula: '+ r.matricula,'success').then(function(){
            $('#modalCadastro').modal('hide'); anexosTemp=[]; $('#tb-anexos-temp').empty(); $('#form-cadastro')[0].reset(); carregarRegistros();
          });
        } else {
          Swal.fire('Erro', (r && r.message) ? r.message : 'Falha ao salvar.','error');
        }
      }
    });
  });

  // =================== Visualizar ===================
  function buildSummaryChips(r){
    var $sum = $('#view-summary').empty();
    var pill = function(icon, text){ return '<span class="summary-pill"><i class="fa '+icon+'"></i>'+ text +'</span>'; };
    $sum.append(pill('fa-list-ol', 'Termo: ' + (r.termo || '-')));
    $sum.append(pill('fa-book', 'Livro: ' + (r.livro || '-')));
    $sum.append(pill('fa-file-text-o', 'Folha: ' + (r.folha || '-')));
    $sum.append(pill('fa-tag', (r.tipo_casamento || '-')));

    function fmtBRDate(s){
      if(!s) return '-';
      const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);
      return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
    }
    $sum.append(pill('fa-calendar', 'Data Registro: ' + fmtBRDate(r.data_registro)));
    $sum.append(pill('fa-list-ol', 'Matricula: ' + (r.matricula || '-')));
  }

  function iconByExt(ext){
    switch(ext){
      case 'pdf': return 'fa-file-pdf-o';
      case 'jpg': case 'jpeg': case 'png': case 'gif': case 'webp': case 'bmp': return 'fa-file-image-o';
      case 'doc': case 'docx': return 'fa-file-word-o';
      case 'xls': case 'xlsx': return 'fa-file-excel-o';
      default: return 'fa-file-o';
    }
  }
  function fileExt(path){
    var parts = String(path||'').split('.');
    return parts.length ? parts.pop().toLowerCase() : '';
  }

  // --- Pré-visualização dentro do modal View ---
  function previewAnexo(path, fileName){
    if(!path) return;
    var abs = toAbsoluteURL(path);
    var ext = fileExt(abs);
    var $container = $('#anexoPreviewContainer');
    var $body = $('#anexoPreviewBody');

    // Mostrar botões de ação quando houver anexo
    $('#btnsAnexoActions').show();
    $('#btnOpenNewTab').attr('href', abs);
    $('#btnDownload').attr('href', abs).attr('download', (fileName || abs.split('/').pop()));

    $('#anexoPreviewTitle').text('Visualização do Anexo: ' + (fileName || abs.split('/').pop()));

    $body.empty();
    if(ext === 'pdf'){
      var viewerURL = PDFJS_VIEWER_PATH + '?file=' + encodeURIComponent(abs) + '#locale=pt-br';
      $body.html('<iframe src="'+ viewerURL +'" allowfullscreen style="border:none;"></iframe>');
    } else if(['jpg','jpeg','png','gif','webp','bmp'].includes(ext)){
      $body.html('<img src="'+ abs +'" alt="Anexo">');
    } else {
      $body.html('<div class="p-3">Pré-visualização não suportada. <a target="_blank" href="'+abs+'">Abrir em nova guia</a></div>');
    }
    $container.show();
  }

  $(document).on('click','.btn-view', function(){
    var id = $(this).data('id');
    // reset preview área
    $('#anexoPreviewContainer').hide();
    $('#anexoPreviewBody').empty();
    $('#btnsAnexoActions').hide();

    $.ajax({
      url: 'get_registro.php',
      method: 'GET',
      data: { id: id },
      dataType: 'json',
      cache: false,
      success: function(r){
        if(!r || !r.id){ return; }
        $('#v_livro').val(r.livro);
        $('#v_folha').val(r.folha);
        $('#v_termo').val(r.termo);
        $('#v_tipo').val(r.tipo_casamento);
        $('#v_data_registro').val(formatDateBR(r.data_registro||''));
        $('#v_matricula').val(r.matricula||'');
        $('#v_conjuge1').val(r.conjuge1_nome||'');
        $('#v_conjuge1_sexo').val(sexoLabel(r.conjuge1_sexo));
        $('#v_conjuge2').val(r.conjuge2_nome||'');
        $('#v_conjuge2_sexo').val(sexoLabel(r.conjuge2_sexo));
        $('#v_conjuge1_nome_casado').val(r.conjuge1_nome_casado || '');
        $('#v_conjuge2_nome_casado').val(r.conjuge2_nome_casado || '');
        $('#v_regime').val(r.regime_bens||'');
        $('#v_data_casamento').val(formatDateBR(r.data_casamento||''));

        buildSummaryChips(r);

        // anexos
        $('#view-anexos').empty();
        $.ajax({
          url: 'get_anexos.php',
          method: 'GET',
          data: { id_casamento: id },
          dataType: 'json',
          cache: false,
          success: function(a){
            var arr = Array.isArray(a) ? a : [];
            if(arr.length === 0){
              $('#anexoPreviewTitle').text('Sem anexo para visualizar');
              $('#anexoPreviewBody').html('<div class="p-3">Este registro não possui anexos.</div>');
              $('#btnsAnexoActions').hide();
              $('#anexoPreviewContainer').show();
              return;
            }
            var firstPath = String(arr[0].caminho_anexo || '');
            var firstName = firstPath.split('/').pop();
            previewAnexo(firstPath, firstName);
          }
        });

        $('#modalView').modal('show');
      }
    });
  });

  // =================== Edição ===================
  $(document).on('click','.btn-edit', function(){
    var id = $(this).data('id');
    // reset preview área da edição
    $('#e_preview_body').hide().empty();
    $('#e_btnOpenNewTab').attr('href','#').hide();
    $('#e_btnDownload').attr('href','#').removeAttr('download').hide();

    $.ajax({
      url: 'get_registro.php',
      method: 'GET',
      data: { id: id },
      dataType: 'json',
      cache: false,
      success: function(r){
        if(!r || !r.id) return;

        $('#e_id').val(r.id);
        $('#e_livro').val(r.livro);
        $('#e_folha').val(r.folha);
        $('#e_termo').val(r.termo);
        $('#e_tipo_casamento').val(r.tipo_casamento);
        $('#e_data_registro').val(formatDateBR(r.data_registro||''));
        $('#e_conjuge1_nome').val(r.conjuge1_nome||'');
        $('#e_conjuge1_sexo').val(r.conjuge1_sexo||'');
        $('#e_conjuge2_nome').val(r.conjuge2_nome||'');
        $('#e_conjuge2_sexo').val(r.conjuge2_sexo||'');
        $('#e_conjuge1_nome_casado').val(r.conjuge1_nome_casado || '');
        $('#e_conjuge2_nome_casado').val(r.conjuge2_nome_casado || '');
        $('#e_regime_bens').val(r.regime_bens||'');
        $('#e_data_casamento').val(formatDateBR(r.data_casamento||''));
        $('#e_matricula').val(r.matricula||'');

        carregarAnexosEdicao(id);
        $('#modalEditar').modal('show');
      }
    });
  });

  function carregarAnexosEdicao(id){
    $.ajax({
      url: 'get_anexos.php',
      method: 'GET',
      data: { id_casamento: id },
      dataType: 'json',
      cache: false,
      success: function(a){
        var $tb = $('#e_tb_anexos').empty();
        var arr = Array.isArray(a) ? a : [];
        arr.forEach(function(x){
          var nome = String(x.caminho_anexo||'').split('/').pop();
          $tb.append(
            '<tr>'
            + '<td>'+ nome +'</td>'
            + '<td>'
            + '  <a class="btn btn-info btn-sm" title="Abrir em nova guia" target="_blank" href="'+ toAbsoluteURL(x.caminho_anexo) +'"><i class="fa fa-external-link"></i></a> '
            + '  <a class="btn btn-secondary btn-sm" title="Baixar" href="'+ toAbsoluteURL(x.caminho_anexo) +'" download="'+ nome +'"><i class="fa fa-download"></i></a> '
            + '  <button type="button" class="btn btn-primary btn-sm btn-preview-anexo" data-path="'+ x.caminho_anexo +'"><i class="fa fa-eye"></i> Pré-visualizar</button> '
            + '  <button type="button" class="btn btn-danger btn-sm btn-del-anexo" data-id="'+ x.id +'"><i class="fa fa-trash"></i></button>'
            + '</td>'
            + '</tr>'
          );
        });
      }
    });
  }

  // Pré-visualização dentro do modal de edição
  function previewAnexoEdicao(path){
    if(!path) return;
    var abs = toAbsoluteURL(path);
    var ext = fileExt(abs);
    var $body = $('#e_preview_body');
    $('#e_btnOpenNewTab').attr('href', abs).show();
    $('#e_btnDownload').attr('href', abs).attr('download', abs.split('/').pop()).show();

    $body.empty();
    if(ext === 'pdf'){
      var viewerURL = PDFJS_VIEWER_PATH + '?file=' + encodeURIComponent(abs) + '#locale=pt-br';
      $body.html('<iframe src="'+ viewerURL +'" allowfullscreen style="border:none;"></iframe>');
    } else if(['jpg','jpeg','png','gif','webp','bmp'].includes(ext)){
      $body.html('<img src="'+ abs +'" alt="Anexo">');
    } else {
      $body.html('<div class="p-3">Pré-visualização não suportada. <a target="_blank" href="'+abs+'">Abrir em nova guia</a></div>');
    }
    $body.show();
  }

  $(document).on('click','.btn-preview-anexo', function(){
    var p = $(this).data('path');
    previewAnexoEdicao(p);
  });

  $(document).on('click','.btn-del-anexo', function(){
    var anexoId = $(this).data('id');
    var regId = $('#e_id').val();
    Swal.fire({
      title:'Tem certeza?',
      text:'Deseja remover este anexo?',
      icon:'warning',
      showCancelButton:true,
      confirmButtonText:'Remover',
      cancelButtonText:'Cancelar'
    }).then(function(ok){
      if(!ok.isConfirmed) return;
      $.ajax({
        type:'POST',
        url:'remover_anexo.php',
        data:{ id: anexoId },
        dataType:'json',
        cache: false,
        success:function(r){
          if(r && r.success){
            Swal.fire('Removido','Anexo removido.','success');
            carregarAnexosEdicao(regId);
            $('#e_preview_body').hide().empty();
            $('#e_btnOpenNewTab, #e_btnDownload').hide();
          } else {
            Swal.fire('Erro', (r && r.message) ? r.message : 'Falha ao remover.','error');
          }
        }
      });
    });
  });

  function uploadEdicaoFiles(files){
    var regId = $('#e_id').val();
    Array.prototype.slice.call(files).forEach(function(file){
      var fd = new FormData();
      fd.append('arquivo_pdf', file);
      fd.append('id_casamento', regId);
      $.ajax({
        type: 'POST',
        url: 'salvar_anexo.php',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(r){
          if(r && r.success){
            carregarAnexosEdicao(regId);
          } else {
            Swal.fire('Erro', (r && r.message) ? r.message : 'Falha ao adicionar anexo.','error');
          }
        },
        error: function(){ Swal.fire('Erro','Falha no upload.','error'); }
      });
    });
  }

  // =================== Salvar Edição ===================
  $('#form-editar').on('submit', function(e){
    e.preventDefault();
    var drStr = String($('#e_data_registro').val()||'').trim();
    var dcStr = String($('#e_data_casamento').val()||'').trim();
    if(!isDateBR(drStr) || !isDateBR(dcStr)) return Swal.fire('Data inválida','Use DD/MM/AAAA.','warning');

    var dr = parseBRtoDateObj(drStr), dc = parseBRtoDateObj(dcStr);
    if(!dr || !dc) return Swal.fire('Data inválida','Verifique as datas.','warning');

    var hoje = new Date(); hoje.setHours(0,0,0,0);
    if(dc.getTime() > dr.getTime()) return Swal.fire('Regra de Data','Data do casamento deve ser menor ou igual à data do registro.','warning');
    if(dr.getTime() > hoje.getTime()) return Swal.fire('Regra de Data','Data de registro deve ser menor ou igual à data atual.','warning');

    $('#e_conjuge1_nome').val(sanitizeTextClient($('#e_conjuge1_nome').val()));
    $('#e_conjuge2_nome').val(sanitizeTextClient($('#e_conjuge2_nome').val()));
    $('#e_conjuge1_nome_casado').val(sanitizeTextClient($('#e_conjuge1_nome_casado').val()));
    $('#e_conjuge2_nome_casado').val(sanitizeTextClient($('#e_conjuge2_nome_casado').val()));

    var fd = new FormData(this);
    $.ajax({
      type: 'POST',
      url: 'atualizar_registro.php',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(r){
        if(r && r.status === 'success'){
          Swal.fire('Sucesso','Registro atualizado. Matrícula: '+ (r.matricula || ''),'success').then(function(){
            $('#modalEditar').modal('hide'); carregarRegistros();
          });
        } else if(r && r.status === 'duplicate'){
          Swal.fire('Registro Duplicado','Já existe registro com mesmos livro/folha/termo/data.','warning');
        } else {
          Swal.fire('Erro', (r && r.message) ? r.message : 'Falha ao atualizar.','error');
        }
      }
    });
  });

   document.querySelectorAll('.numeric-only').forEach((el) => {
        el.addEventListener('keydown', (e) => {
            const bloqueados = ['e','E','+','-','.'];
            if (bloqueados.includes(e.key)) e.preventDefault();
        });
        el.addEventListener('input', function () {
            this.value = this.value.replace(/\D+/g, '');
        });
    });

    document.querySelectorAll('.numeric-only').forEach((el) => {
        el.addEventListener('keydown', (e) => {
            const bloqueados = ['e','E','+','-','.'];
            if (bloqueados.includes(e.key)) e.preventDefault();
        });
        el.addEventListener('input', function () {
            // mantém só dígitos
            this.value = this.value.replace(/\D+/g, '');
            // aplica corte por tamanho se data-maxlen estiver definido
            const maxlen = parseInt(this.dataset.maxlen || 0, 10);
            if (maxlen) this.value = this.value.slice(0, maxlen);
        });
    });

    (function () {
        const el = document.getElementById('e_folha');
        if (!el) return;

        el.addEventListener('keydown', (e) => {
            const bloqueados = ['e','E','+','-','.'];
            if (bloqueados.includes(e.key)) e.preventDefault();
        });

        el.addEventListener('input', function () {
            this.value = this.value.replace(/\D+/g, '').slice(0, 3);
            // opcional: força o máximo numérico
            if (this.value !== '' && Number(this.value) > 999) this.value = '999';
        });
    })();

  // =================== Inicialização ===================
  $(document).ready(function(){
    // máscaras (se plugin disponível)
    if($.fn.mask){
      $('#c_data_registro, #c_data_casamento, #filtro-data-casamento, #filtro-data-registro, #e_data_registro, #e_data_casamento').mask('00/00/0000');
    }

    carregarLivros();
    carregarRegistros();

    // filtrar
    $('#btn-filtrar').on('click', carregarRegistros);
    $('#filtro-conjuge, #filtro-termo, #filtro-folha, #filtro-data-casamento, #filtro-data-registro').on('keypress', function(e){
      if(e.which === 13){ carregarRegistros(); }
    });

    // Dropzones
    bindDropzone('#dropzone-cadastro', '#c_pdf_file', uploadCadastroFiles);
    bindDropzone('#dropzone-edicao', '#e_pdf_file', uploadEdicaoFiles);
    $('#dropzone-edicao').on('click', function(){ $('#e_pdf_file').trigger('click'); });
  });

  // Ajuste de sobreposição de modais (múltiplos abertos)
  $(document).on('show.bs.modal', '.modal', function () {
    var z = 1040 + (10 * $('.modal:visible').length);
    $(this).css('z-index', z);
    setTimeout(function(){
      $('.modal-backdrop').not('.modal-stack').css('z-index', z-1).addClass('modal-stack');
    }, 0);
  });
</script>

<?php include(__DIR__ . '/../../rodape.php'); ?>
</body>
</html>
