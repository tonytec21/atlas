<style>
        .btn-4 { background: #34495e; color: #fff; }
        
        .btn-close {
            outline: none; border: none; background: none; padding: 0; font-size: 1.5rem;
            cursor: pointer; transition: transform 0.2s ease;
        }
        .btn-close:hover { transform: scale(2.10); }
        .btn-close:focus { outline: none; }

        .btn-adicionar { height: 38px; line-height: 24px; margin-left: 10px; }
        #detalhesModal.modal.fade.show {padding-right: 0px!important; }

        /* ===== Detalhes (visualização do caixa) – 90% da tela, UI/UX e dark/light ===== */
        #detalhesModal .modal-dialog { width: 98vw; max-width: min(98vw, 1600px); margin: .3rem auto; }
        #detalhesModal .modal-content { border-radius: 18px; border: 0; box-shadow: 0 12px 40px rgba(0,0,0,.18); }
        body.light-mode #detalhesModal .modal-content{ background: #f7fbff !important; color: #0f172a !important; }
        body.dark-mode #detalhesModal .modal-content{ background: #0f172a !important; color: #e5e7eb !important; }
        #detalhesModal .modal-header{
            position: sticky; top: 0; z-index: 2; background: inherit;
            border-bottom: 1px solid rgba(0,0,0,.06); border-top-left-radius: 18px; border-top-right-radius: 18px;
        }
        body.dark-mode #detalhesModal .modal-header{ border-color: #1f2937; }
        #detalhesModal .modal-body{ max-height: calc(110vh - 220px); overflow: auto; padding-bottom: 1rem; }
        @media (max-width: 575.98px){ #detalhesModal .modal-body{ max-height: calc(100vh - 180px); } }

        /* Cartões (dentro do modal) */
        #detalhesModal .card{ border: 0; border-radius: 14px; box-shadow: 0 6px 22px rgba(0,0,0,.08); }
        body.dark-mode #detalhesModal .card{ background: #111827; color: #e5e7eb; border-color: #1f2937; }
        #detalhesModal .card-header{ font-weight: 700; border: 0; background: transparent; }

        /* Tabelas responsivas dentro do modal */
        #detalhesModal .table-responsive{ overflow-x: auto; -webkit-overflow-scrolling: touch; }
        #detalhesModal .table{ font-size: .95rem; }
        #detalhesModal .table th, #detalhesModal .table td{ vertical-align: middle; }

        /* Melhor hit area do botão fechar */
        #detalhesModal .btn-close{ font-size: 1.75rem; line-height: 1; padding: .25rem .5rem; }

        .modal-footer { border-top: none; }
        .modal-header.error { background-color: #dc3545; color: white; }
        .modal-header.success { background-color: #28a745; color: white; }

        .custom-file-input ~ .custom-file-label::after { content: "Escolher"; }
        .custom-file-label{ border-radius: .25rem; padding: .5rem 1rem; background-color: #fff; color: #777; cursor: pointer; }
        .custom-file-input:focus ~ .custom-file-label { outline: -webkit-focus-ring-color auto 1px; outline-offset: -2px; }

        .toast { min-width: 250px; margin-top: 0px; }
        .toast .toast-header { color: #fff; }
        .toast .bg-success { background-color: #28a745 !important; }
        .toast .bg-danger { background-color: #dc3545 !important; }

        .btn-delete { margin-bottom: 5px!important; }

        .status-label { padding: 5px 10px; border-radius: 5px; color: white; display: inline-block; }
        .status-pendente { background-color: #dc3545; width: 75px; text-align: center; }
        .status-parcialmente { background-color: #ffc107; width: 75px; text-align: center; }
        .status-liquidado { background-color: #28a745; width: 75px; text-align: center; }

        .total-label { font-weight: bold; text-align: center; }

        .card-title { font-size: 1.25rem; }
        .card-title2 { font-size: 1.1rem; }

        .bg-warning { background-color: #ff8e07 !important; }

        /* Modal Depósitos Unificado – 100% responsivo */
        .modal-deposito-caixa-unificado.modal-dialog{
            width: auto;
            margin: .5rem auto;
        }
        @media (min-width:576px){ .modal-deposito-caixa-unificado.modal-dialog{ max-width: 640px; } }
        @media (min-width:768px){ .modal-deposito-caixa-unificado.modal-dialog{ max-width: 860px; } }
        @media (min-width:992px){ .modal-deposito-caixa-unificado.modal-dialog{ max-width: 1100px; } }
        @media (min-width:1400px){ .modal-deposito-caixa-unificado.modal-dialog{ max-width: 1320px; } }

        #verDepositosCaixaModal .modal-content{
            border-radius:16px; border:0; box-shadow:0 12px 40px rgba(0,0,0,.18);
        }
        #verDepositosCaixaModal .modal-body{
            max-height: calc(100vh - 220px);
            overflow:auto;
            padding: 8px 12px;
        }
        #verDepositosCaixaModal .table{ font-size:.95rem; }
        #verDepositosCaixaModal .table thead th{
            position: sticky; top: 0; z-index: 1; background:#f8fafc;
        }
        body.dark-mode #verDepositosCaixaModal .table thead th{ background:#111827; color:#e5e7eb; }

        .modal-abrir-caixa { max-width: 50%; margin: auto; }

        /* Diálogo responsivo elegante (use na .modal-dialog) */
        .modal-responsive { width: auto; margin: .5rem auto; }
        @media (min-width:576px){ .modal-responsive{ max-width: 540px; } }
        @media (min-width:768px){ .modal-responsive{ max-width: 720px; } }
        @media (min-width:992px){ .modal-responsive{ max-width: 920px; } }
        @media (min-width:1200px){ .modal-responsive{ max-width: 1100px; } }

        /* Visual moderno para os dois modais */
        .modal-modern .modal-content{ border-radius:16px; border:0; box-shadow: 0 12px 40px rgba(0,0,0,.18); }
        body.light-mode .modal-modern .modal-content{ background-color:#f7fbff!important; }
        .modal-modern .modal-header{ position:sticky; top:0; z-index:2; background:inherit; border-bottom:1px solid rgba(0,0,0,.06); }
        .modal-modern .modal-body{ max-height: calc(100vh - 220px); overflow:auto; padding-bottom:1rem; }
        @media (max-width:575.98px){ .modal-modern .modal-body{ max-height: calc(100vh - 160px); } }

        /* Inputs e toques sutis */
        .input-label{ font-weight:600; }
        .input-hint{ font-size:.85rem; color:#6b7280; }

        /* Cards de totais no modal de Depósito */
        .stats-card .card{ border:0; border-radius:14px; box-shadow: 0 8px 28px rgba(0,0,0,.06); }
        .stats-card .card-title2{ margin-bottom:.25rem; }

        .btn-success { width: 40px; height: 40px; margin-bottom: 5px; }
        .btn-success:hover { color: #212529; }

        /* DARK: card bg-dark ajustado */
        body.dark-mode .card.bg-dark { background-color: #f8f9fa !important; color: #777 !important; }
        body.dark-mode .card.bg-dark .card-header,
        body.dark-mode .card.bg-dark .card-body,
        body.dark-mode .card.bg-dark .card-title { color: #777 !important; }

        /* Azul petróleo */
        .bg-petroleo { background-color: #004d61 !important; color: white; }
        body.dark-mode .bg-petroleo { background-color: #cfe9f1 !important; color: #212529 !important; }

        /* =======================================================================
           HERO / TÍTULO
        ======================================================================= */
        .page-hero{
          background: linear-gradient(180deg, rgba(79,70,229,.12), rgba(79,70,229,0));
          border-radius: 18px;
          padding: 18px 18px 6px 18px;
          margin: 20px 0 12px;
          box-shadow: var(--soft-shadow);
        }
        .page-hero .title-row{ display:flex;align-items:center;gap:14px;flex-wrap:wrap; }
        .page-hero .title-icon{
          width:44px;height:44px;border-radius:12px;background:#EEF2FF;color:#3730A3;
          display:flex;align-items:center;justify-content:center;font-size:20px;
        }
        body.dark-mode .page-hero .title-icon{ background:#262f3b;color:#c7d2fe; }
        .page-hero h1{ font-size: clamp(1.25rem, .9rem + 2vw, 1.75rem); font-weight: 800;margin:0;letter-spacing:.2px; }
        .page-hero .subtitle{ font-size:.95rem;margin-top:2px; }

        /* =======================================================================
           LISTAGEM EM CARDS (RESULTADOS)
        ======================================================================= */
        .cards-wrap { margin-top: 10px; }
        .caixa-col { margin-bottom: 16px; }

        .caixa-card {
            border: 1px solid rgba(0,0,0,.06);      /* borda sutil para separar do fundo */
            border-radius: 16px;
            box-shadow: 0 12px 24px rgba(0,0,0,.06);
            transition: transform .18s ease, box-shadow .18s ease;
            cursor: pointer;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: #ffffff;                     /* fundo do card mais claro que a página */
        }
        body.dark-mode .caixa-card{
            background: #0b1220;
            border-color: rgba(255,255,255,.06);
            box-shadow: 0 12px 28px rgba(0,0,0,.35);
        }
        .caixa-card:hover { transform: translateY(-2px); box-shadow: 0 16px 36px rgba(0,0,0,.12); }

        .caixa-card .card-body { display: flex; flex-direction: column; }
        .caixa-card .topline { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom: 6px; }
        .caixa-card .title-strong { font-weight: 800; font-size: 1.05rem; line-height: 1.2; }
        .caixa-card .muted { color: #64748b; font-size: .9rem; }
        body.dark-mode .caixa-card .muted { color: #9aa5b1; }

        .badge-status {
            font-size: .75rem; padding: .35rem .55rem; border-radius: 999px; font-weight: 700;
            border: 1px solid rgba(0,0,0,.05); display:flex; align-items:center; gap:6px;
        }

        /* Pastéis de status */
        .pastel-open { background: #e8f9f0; }     /* verde bem claro */
        .pastel-closed { background: #f3f5f7; }   /* cinza azulado claro */
        body.dark-mode .pastel-open { background: #14322a; }
        body.dark-mode .pastel-closed { background: #111827; }

        .badge-open { background: #d1fae5; color: #065f46; }
        .badge-closed { background: #e5e7eb; color: #374151; }
        body.dark-mode .badge-open { background: #064e3b; color: #d1fae5; }
        body.dark-mode .badge-closed { background: #374151; color: #e5e7eb; }

        .metrics {
            display:grid; gap: 10px;
            grid-template-columns: repeat(2,minmax(0,1fr));
            margin-top: 10px;
        }
        .metric {
            border: 1px dashed rgba(0,0,0,.06);
            border-radius: 12px;
            padding: 10px 12px;
            min-height: 68px;
            background: #fbfbfd;
        }
        body.dark-mode .metric { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.08); }
        .metric .k { font-weight: 800; font-size: 1rem; }
        .metric .chip { display:inline-block; font-size:.72rem; font-weight:700; border-radius:999px; padding:3px 8px; margin-bottom:6px; border:1px solid rgba(0,0,0,.06); }

        /* Chips (cores pastéis por tipo) */
        .chip-saldo{ background:#E3F2FD; color:#0C4A6E; }        /* azul claro */
        .chip-atos{ background:#F5E8FF; color:#6B21A8; }         /* roxo claro */
        .chip-conta{ background:#FFF8E1; color:#92400E; }        /* âmbar claro */
        .chip-especie{ background:#DCFCE7; color:#065F46; }      /* verde claro */
        .chip-devolucoes{ background:#FEE2E2; color:#7F1D1D; }   /* vermelho claro */
        .chip-saidas{ background:#FFE4E6; color:#9F1239; }       /* rosa claro */
        .chip-deposito{ background:#E0F2FE; color:#075985; }     /* azul-ciano claro */
        .chip-total{ background:#F1F5F9; color:#334155; }        /* cinza claro */

        .card-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 12px; }
        .card-footer-eq { margin-top:auto; display:flex; align-items:center; justify-content:space-between; gap:10px; padding-top: 8px; }

        /* Botões de ação com tamanho padronizado */
        .btn-icon { width: 36px; height: 36px; display:flex; align-items:center; justify-content:center; padding:0; }
        .btn-lock { background:#C9A227; color:#fff; border-color:#C9A227; }
        .btn-lock:hover{ background:#B9921E; color:#fff; border-color:#B9921E; }

        /* Garantir altura semelhante e layout estável */
        .caixa-card .header-block { min-height: 56px; }
        .caixa-card .metrics { min-height: 170px; }

        @media (max-width: 575.98px){
            .metrics{ grid-template-columns: 1fr; }
        }

        /* Status no cabeçalho do modal (com cadeado) */
        .modal-status-pill{
            position:absolute; right:48px; top:10px;
        }
        .modal-status-pill .badge-status { box-shadow: 0 6px 18px rgba(0,0,0,.08); }

        /* ====== SOMENTE OS FILTROS DO MODAL (ATOS LIQUIDADOS / ATOS MANUAIS) ====== */
/* Paleta local por tema (apenas para os filtros) */
body.light-mode #detalhesModal #filtrosAtosLiquidados,
body.light-mode #detalhesModal #filtrosAtosManuais {
  --surface: #ffffff;
  --text: #0f172a;
  --muted: #667085;
  --border: #e6e8f0;
  --focus: rgba(59,130,246,.32);
}
body.dark-mode #detalhesModal #filtrosAtosLiquidados,
body.dark-mode #detalhesModal #filtrosAtosManuais {
  --surface: #0f172a;
  --text: #e5e7eb;
  --muted: #a3b1c2;
  --border: #1f2937;
  --focus: rgba(59,130,246,.45);
}

/* Container dos filtros:
   – volta a ser FLEX (não grid) para não brigar com Bootstrap
   – espaçamentos amplos no desktop
*/
#detalhesModal #filtrosAtosLiquidados,
#detalhesModal #filtrosAtosManuais {
  display: flex !important;
  flex-wrap: wrap;
  align-items: end;
  column-gap: 24px;
  row-gap: 14px;
  padding: 14px 16px;
  margin-bottom: 18px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
}

/* Cada coluna de filtro ganha largura mínima confortável
   (funciona mesmo com .no-gutters) */
#detalhesModal #filtrosAtosLiquidados > [class*="col-"],
#detalhesModal #filtrosAtosManuais > [class*="col-"] {
  min-width: 260px;
  flex: 1 1 260px;
}

/* Labels dos filtros */
#detalhesModal #filtrosAtosLiquidados .input-label,
#detalhesModal #filtrosAtosManuais .input-label {
  display: block;
  margin: 0 0 .4rem 2px;
  color: var(--muted);
  font-weight: 600;
  font-size: .92rem;
}

/* Selects 100% da coluna (corrige selects estreitos) */
#detalhesModal #filtrosAtosLiquidados select.form-control,
#detalhesModal #filtrosAtosManuais select.form-control {
  width: 100% !important;
  max-width: 100%;
  min-width: 0;              /* evita encolhimento estranho em alguns navegadores */
  min-height: 44px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text);
  padding: .5rem .75rem;
  transition: border-color .2s, box-shadow .2s, background .2s;
}
#detalhesModal #filtrosAtosLiquidados select.form-control:focus,
#detalhesModal #filtrosAtosManuais select.form-control:focus {
  border-color: rgba(59,130,246,.55);
  box-shadow: 0 0 0 .2rem var(--focus);
}

.modal {height: 105%;}

/* Botão "Limpar filtros" com respiro e alinhado ao visual */
#btnLimparFiltrosAtos,
#btnLimparFiltrosManuais {
  border-radius: 10px;
  padding: .5rem .9rem;
  border: 1px solid var(--border);
  color: var(--muted);
  background: transparent;
  margin-bottom: 8px;
  height: 45px;
}
#btnLimparFiltrosAtos:hover,
#btnLimparFiltrosManuais:hover {
  background: rgba(148,163,184,.08);
  color: var(--text);
  border-color: rgba(148,163,184,.35);
}
#btnLimparFiltrosAtos .fa,
#btnLimparFiltrosManuais .fa { margin-right: .4rem; }

/* Desktop+: dá mais espaço entre campos */
@media (min-width: 1200px) {
  #detalhesModal #filtrosAtosLiquidados,
  #detalhesModal #filtrosAtosManuais {
    column-gap: 28px;
    row-gap: 16px;
    padding: 16px 18px;
  }
  #detalhesModal #filtrosAtosLiquidados > [class*="col-"],
  #detalhesModal #filtrosAtosManuais > [class*="col-"] {
    min-width: 300px;
    flex-basis: 300px;
  }
}

    </style>