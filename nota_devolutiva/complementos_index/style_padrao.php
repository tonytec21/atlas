<?php /* complementos_index/style_padrao.php — visual padronizado (igual ao módulo de ofícios) */ ?>
    <style>
        /* =======================================================================
           HERO
        ======================================================================= */
        .page-hero .title-row{ display:flex; align-items:center; gap:12px; }
        .page-hero{
          background: linear-gradient(180deg, rgba(79,70,229,.10), rgba(79,70,229,0));
          border-radius: 18px; padding: 18px 18px 10px; margin: 20px 0 12px; box-shadow: var(--soft-shadow, 0 8px 22px rgba(0,0,0,.06));
        }
        .title-icon{
          width:44px;height:44px;border-radius:12px;background:#EEF2FF;color:#3730A3;display:flex;align-items:center;justify-content:center;font-size:20px;
        }
        body.dark-mode .title-icon{ background:#262f3b;color:#c7d2fe; }
        .page-hero h1{ font-weight:800; margin:0; }
        .page-hero .subtitle{ font-size:.95rem; opacity:.9; margin-top:2px;}
        .chip{
            display:inline-flex; align-items:center; gap:8px;
            background: rgba(99,102,241,.12); color:#3730A3;
            padding:6px 10px; border-radius:999px; font-weight:600; font-size:.85rem;
        }
        body.dark-mode .chip{ background: rgba(99,102,241,.18); color:#c7d2fe; }
        .chip .fa{ font-size:.9rem; }

        /* ===== Botão fechar (X) no modal ===== */
        .btn-close {
            outline: none;
            border: none; 
            background: none;
            padding: 0; 
            font-size: 1.5rem;
            cursor: pointer; 
            transition: transform 0.2s ease;
            line-height: 1;
        }
        .btn {margin-top: 5px}
        .btn-close:hover { transform: scale(1.15); }
        .btn-close:focus { outline: none; }

        .table-bordered {border-radius: 15px;}

        /* ===== Modal de Visualização – versão slim e ampla ===== */
        #viewOficioModal .modal-dialog {
            max-width: min(96vw, 1400px);
            margin: 0.55rem auto;
        }
        #viewOficioModal .modal-content {
            border: 1px solid rgba(0,0,0,.08);
            border-radius: 14px;
            box-shadow: 0 8px 28px rgba(0,0,0,.18);
            overflow: hidden;
            transition: background-color .3s ease, box-shadow .3s ease, border-color .3s ease;
        }
        #viewOficioModal .modal-header {
            background: rgba(255,255,255,.9);
            backdrop-filter: saturate(1.2) blur(8px);
            border-bottom: 1px solid rgba(0,0,0,.06);
            padding: 0rem 1.1rem;
        }
        #viewOficioModal .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
        }
        #viewOficioModal .modal-body {
            padding: 0rem 0rem 0rem 0rem;
            background: #fafafa;
        }
        #viewOficioModal .toolbar {
            display: flex;
            gap: .5rem;
            align-items: center;
            margin-left: auto;
        }
        /* iFrame ocupa o máximo de área */
        #viewOficioModal iframe#oficioPDF {
            width: 100%;
            height: calc(100vh - 120px); /* cabeçalho+rodapé */
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 10px;
            background: #f3f4f6;
        }
        #viewOficioModal .modal-footer {
            border-top: 1px solid rgba(0,0,0,.06);
            background: rgba(250,250,250,.9);
            backdrop-filter: saturate(1.2) blur(8px);
            padding: .0rem .0rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ===== Botões modernos (pílula) ===== */
        .btn-pill {
            border-radius: 10px !important;
            padding: .55rem 1rem;
            font-weight: 600;
            border: 1px solid transparent;
            transition: transform .08s ease, box-shadow .2s ease, background-color .2s ease, border-color .2s ease;
        }
        .btn-pill:focus { box-shadow: 0 0 0 .2rem rgba(0,123,255,.15); outline: none; }
        .btn-pill:hover { transform: translateY(-1px); }

        .btn-ghost {
            background: transparent;
            border-color: var(--line, rgba(0,0,0,.12));
        }

        /* Reforço de cores para temas */
        .btn-primary.btn-pill { box-shadow: 0 4px 10px rgba(0,123,255,.18); }
        .btn-success.btn-pill { box-shadow: 0 4px 10px rgba(40,167,69,.18); }
        .btn-info.btn-pill    { box-shadow: 0 4px 10px rgba(23,162,184,.18); }
        .btn-secondary.btn-pill { box-shadow: 0 4px 10px rgba(108,117,125,.18); }
        .btn-info { margin-bottom: 0px!important; }

        /* ===== Dark Mode do Modal de Visualização ===== */
        body.dark-mode #viewOficioModal .modal-content {
            border-color: rgba(255,255,255,.08);
            box-shadow: 0 8px 28px rgba(0,0,0,.55);
        }
        body.dark-mode #viewOficioModal .modal-header {
            background: rgba(35,39,42,.8);
            border-bottom-color: rgba(255,255,255,.06);
        }
        body.dark-mode #viewOficioModal .modal-title { color: #fff; }
        body.dark-mode #viewOficioModal .modal-body { background: #1f2326; }
        body.dark-mode #viewOficioModal iframe#oficioPDF { background: #2a2f34; border-color: rgba(255,255,255,.06); }
        body.dark-mode #viewOficioModal .modal-footer { background: rgba(35,39,42,.8); border-top-color: rgba(255,255,255,.06); }
        body.dark-mode .btn-ghost { border-color: rgba(255,255,255,.18); color: #e8e8e8; }
        body.dark-mode .btn-ghost:hover { background: rgba(255,255,255,.04); }

        /* ======= FORM DE FILTRO (UI/UX MODERNO) ======= */
        .filter-card{
            background: linear-gradient(180deg, rgba(15,23,42,.02), rgba(15,23,42,0));
            border:1px solid rgba(0,0,0,.06);
            border-radius:16px; padding:16px; box-shadow: 0 6px 18px rgba(0,0,0,.06);
        }
        body.dark-mode .filter-card{
            background: linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,0));
            border-color: rgba(255,255,255,.10); box-shadow: 0 8px 26px rgba(0,0,0,.45);
        }
        .filter-card .section-title{
            font-weight:800; font-size:1.05rem; margin-bottom:.35rem;
        }
        .filter-card .section-sub{
            font-size:.92rem; opacity:.85; margin-bottom:.75rem;
        }
        .input-chip{
            display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid rgba(0,0,0,.08); border-radius:12px; background:#fff;
        }
        .input-chip input{ border:none; outline:none; width:100%; }
        .input-chip .fa{ opacity:.7; }
        body.dark-mode .input-chip{ background:#1f2326; border-color: rgba(255,255,255,.12); color:#e9ecef;}
        .filter-actions{
            display:flex; gap:10px; flex-wrap:wrap;
        }
        .btn-soft{
            background: #f3f4f6; border:1px solid rgba(0,0,0,.08); color:#111827;
        }
        .btn-soft:hover{ background:#e9eaee; }
        body.dark-mode .btn-soft{ background:#2a2f34; border-color:rgba(255,255,255,.10); color:#f3f4f6; }
        body.dark-mode .btn-soft:hover{ background:#32373d; }

        .hint{
            font-size:.85rem; opacity:.85;
        }

        /* ===== Tabela -> Cards (Mobile) ===== */
        .table-wrap { width: 100%; }
        table.data-layout {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        /* Cabeçalho fixo no desktop */
        @media (min-width: 768px) {
            table.data-layout thead th {
                position: sticky;
                top: 0;
                z-index: 1;
                background: var(--thead-bg, #fff);
            }
            body.dark-mode table.data-layout thead th { --thead-bg: #2c2f33; color: #fff; }
        }

        /* Aparência padrão da tabela */
        table.data-layout thead th {
            font-weight: 700;
            border-bottom: 1px solid rgba(0,0,0,.08);
        }
        body.dark-mode table.data-layout thead th {
            border-bottom-color: rgba(255,255,255,.08);
        }

        /* LINHAS como cards no mobile */
        @media (max-width: 767.98px) {
            table.data-layout thead { display: none; }
            table.data-layout, 
            table.data-layout tbody, 
            table.data-layout tr, 
            table.data-layout td { display: block; width: 100%; }
            table.data-layout tr {
                background: rgba(255,255,255,.9);
                border: 1px solid rgba(0,0,0,.06);
                border-radius: 14px;
                box-shadow: 0 8px 20px rgba(0,0,0,.06);
                padding: .75rem .9rem;
                margin-bottom: .9rem;
            }
            body.dark-mode table.data-layout tr {
                background: rgba(35,39,42,.85);
                border-color: rgba(255,255,255,.08);
                box-shadow: 0 8px 24px rgba(0,0,0,.45);
            }
            table.data-layout td {
                border: 0;
                padding: .25rem 0;
                position: relative;
            }
            table.data-layout td::before {
                content: attr(data-label);
                display: block;
                font-size: .78rem;
                opacity: .8;
                margin-bottom: .1rem;
                text-transform: uppercase;
                letter-spacing: .02em;
            }
            /* Ações em grid */
            td[data-cell="acoes"] {
                display: grid;
                grid-template-columns: repeat(3, minmax(40px, 1fr));
                gap: .4rem;
                margin-top: .35rem;
            }
        }

        /* Botões de ação na tabela */
        .btn-table {
            width: 40px; height: 40px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,.08);
            transition: transform .08s ease, box-shadow .2s ease, background-color .2s ease, border-color .2s ease;
        }
        .btn-table:hover { transform: translateY(-1px); }
        body.dark-mode .btn-table { border-color: rgba(255,255,255,.12); }

        /* Ajuste do DataTables para caber melhor */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 999px; padding: .4rem .8rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 10px !important;
            margin: 0 .1rem;
        }

        /* ===== Modal de Anexos – Estética moderna ===== */
        #viewAttachmentsModal .modal-dialog {
            max-width: min(95vw, 1200px);
        }
        #viewAttachmentsModal .modal-content {
            border-radius: 14px;
            border: 1px solid rgba(0,0,0,.08);
            box-shadow: 0 8px 28px rgba(0,0,0,.18);
            overflow: hidden;
        }
        #viewAttachmentsModal .modal-header {
            background: rgba(255,255,255,.9);
            backdrop-filter: saturate(1.2) blur(8px);
            border-bottom: 1px solid rgba(0,0,0,.06);
            padding: .9rem 1.1rem;
        }
        #viewAttachmentsModal .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
        }
        #viewAttachmentsModal .modal-body {
            background: #fafafa;
        }
        #viewAttachmentsModal .modal-footer {
            background: rgba(250,250,250,.9);
            backdrop-filter: saturate(1.2) blur(8px);
            border-top: 1px solid rgba(0,0,0,.06);
        }

        body.dark-mode #viewAttachmentsModal .modal-content { border-color: rgba(255,255,255,.08); box-shadow: 0 8px 28px rgba(0,0,0,.55); }
        body.dark-mode #viewAttachmentsModal .modal-header { background: rgba(35,39,42,.8); border-bottom-color: rgba(255,255,255,.06); }
        body.dark-mode #viewAttachmentsModal .modal-title { color: #fff; }
        body.dark-mode #viewAttachmentsModal .modal-body { background: #1f2326; }
        body.dark-mode #viewAttachmentsModal .modal-footer { background: rgba(35,39,42,.8); border-top-color: rgba(255,255,255,.06); }

        /* ===== Dropzone custom (sem lib externa) ===== */
        .dropzone {
            border: 2px dashed rgba(0,0,0,.15);
            border-radius: 14px;
            background: #fff;
            padding: 20px;
            display: grid;
            place-items: center;
            text-align: center;
            transition: border-color .2s ease, background-color .2s ease, box-shadow .2s ease;
            cursor: pointer;
        }
        .dropzone:hover { border-color: rgba(0,0,0,.25); }
        .dropzone.dragover {
            background: #f3f7ff;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,.15) inset;
        }
        .dropzone .dz-icon {
            font-size: 36px;
            margin-bottom: 8px;
            opacity: .9;
        }
        .dropzone .dz-text {
            font-weight: 600;
        }
        .dropzone .dz-hint {
            font-size: .9rem;
            opacity: .8;
        }
        .hidden-input {
            display: none;
        }
        .upload-progress {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: rgba(0,0,0,.08);
            overflow: hidden;
            margin-top: 10px;
        }
        .upload-progress > div {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #0d6efd, #17a2b8);
            transition: width .15s ease;
        }
        .upload-status {
            font-size: .9rem;
            margin-top: 6px;
            opacity: .85;
        }

        body.dark-mode .dropzone { background: #23272a; border-color: rgba(255,255,255,.15); }
        body.dark-mode .dropzone:hover { border-color: rgba(255,255,255,.25); }
        body.dark-mode .dropzone.dragover {
            background: #1e2a3d;
            border-color: #66b0ff;
            box-shadow: 0 0 0 3px rgba(102,176,255,.15) inset;
        }
        body.dark-mode .upload-progress { background: rgba(255,255,255,.12); }
    </style>
<style>
/* Padroniza a tabela de notas com o visual de ofícios */
#tabelaNotas{ width:100% !important; table-layout:auto !important; min-width:0 !important; box-shadow:none; background:transparent; border-radius:0; border-collapse:separate; border-spacing:0; }
/* Cabeçalho CLARO (vence o gradiente escuro de ".table thead th !important") */
#tabelaNotas thead th{ position:relative !important; cursor:pointer; background:#fff !important; color:#334155 !important; text-transform:none !important; letter-spacing:normal !important; font-weight:700 !important; font-size:.9rem !important; white-space:normal !important; border:none !important; border-bottom:1px solid rgba(0,0,0,.08) !important; padding:.75rem 26px .75rem .6rem !important; }
body.dark-mode #tabelaNotas thead th{ background:#2c2f33 !important; color:#fff !important; border-bottom-color:rgba(255,255,255,.08) !important; }
#tabelaNotas tbody td{ vertical-align:middle; }
/* Ações sempre por cima (garante clique) + em linha no desktop */
#tabelaNotas td[data-cell="acoes"]{ white-space:nowrap; text-align:center; position:relative; z-index:3; }
#tabelaNotas td[data-cell="acoes"] .btn-table{ margin:2px; }
.acao-assinada{ background:#e8f5ee; color:#16a34a; border-color:rgba(22,163,74,.25) !important; cursor:default; }
body.dark-mode .acao-assinada{ background:rgba(22,163,74,.15); color:#4ade80; }

/* Setas de ordenação (estilo DataTables) — garante que apareçam em todas as colunas */
#tabelaNotas thead th.sorting::before,
#tabelaNotas thead th.sorting_asc::before,
#tabelaNotas thead th.sorting_desc::before{ content:"\2191"; position:absolute; right:13px; bottom:.5em; font-size:.8em; line-height:1; opacity:.3; }
#tabelaNotas thead th.sorting::after,
#tabelaNotas thead th.sorting_asc::after,
#tabelaNotas thead th.sorting_desc::after{ content:"\2193"; position:absolute; right:6px; bottom:.5em; font-size:.8em; line-height:1; opacity:.3; }
#tabelaNotas thead th.sorting_asc::before{ opacity:1; color:#2563eb; }
#tabelaNotas thead th.sorting_desc::after{ opacity:1; color:#2563eb; }
body.dark-mode #tabelaNotas thead th.sorting_asc::before,
body.dark-mode #tabelaNotas thead th.sorting_desc::after{ color:#60a5fa; }
</style>
