<style>
/* ============================================
   ATLAS - MÓDULO NOTA DEVOLUTIVA
   Sistema de Design Responsivo & UI/UX
   ============================================ */

/* CSS Variables para consistência */
:root {
    --primary-color: #2563eb;
    --primary-hover: #1d4ed8;
    --secondary-color: #64748b;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #3b82f6;
    --purple-color: #8b5cf6;
    --orange-color: #f97316;
    --bg-light: #f8fafc;
    --bg-card: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --text-muted: #94a3b8;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 16px;
    --radius-full: 9999px;
    --transition-fast: 150ms ease;
    --transition-normal: 200ms ease;
    --transition-slow: 300ms ease;
}

body.dark-mode {
    --bg-light: #0f172a;
    --bg-card: #1e293b;
    --border-color: #334155;
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --text-muted: #64748b;
}

hr:not([size]) { height: 0; border-top: 1px solid var(--border-color); background-color: transparent !important; margin: 1rem 0; }
* { box-sizing: border-box; }

/* CONTAINER & LAYOUT */
.main-content { padding: 1rem; min-height: calc(100vh - 60px); }
.main-content .container { max-width: 100%; padding: 0 0.5rem; }
@media (min-width: 768px) { .main-content { padding: 1.5rem; } .main-content .container { padding: 0 1rem; } }
@media (min-width: 1200px) { .main-content .container { max-width: 1400px; margin: 0 auto; } }

/* FORMULÁRIO DE PESQUISA */
#searchForm .row { margin: 0 -0.5rem; }
#searchForm .row > [class*="col-"] { padding: 0 0.5rem; margin-bottom: 1rem; }
#searchForm label { display: block; font-size: 0.8125rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.025em; }
#searchForm .form-control { width: 100%; padding: 0.625rem 0.875rem; font-size: 0.9375rem; border: 1.5px solid var(--border-color); border-radius: var(--radius-md); background-color: var(--bg-card); color: var(--text-primary); transition: all var(--transition-normal); }
#searchForm .form-control:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgb(37 99 235 / 0.15); }

@media (max-width: 575.98px) { #searchForm .row > [class*="col-"] { flex: 0 0 100%; max-width: 100%; } }
@media (min-width: 576px) and (max-width: 767.98px) { 
    #searchForm .col-md-2, #searchForm .col-md-3 { flex: 0 0 50%; max-width: 50%; } 
    #searchForm .col-md-4, #searchForm .col-md-6 { flex: 0 0 100%; max-width: 100%; } 
}

#searchForm .btn { padding: 0.75rem 1.25rem; font-size: 0.9375rem; font-weight: 600; border-radius: var(--radius-md); transition: all var(--transition-normal); display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; }
#searchForm .btn-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); border: none; color: white; }
#searchForm .btn-primary:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
#searchForm .btn-success { background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%); border: none; color: white; width: auto; height: auto; }
#searchForm .btn-success:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }

/* TABELA DE RESULTADOS */
.table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.table-responsive h5 { font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary-color); display: inline-block; }

#tabelaResultados { width: 100% !important; zoom: 1 !important; border-collapse: separate; border-spacing: 0; background: var(--bg-card); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-md); table-layout: fixed; }
#tabelaResultados thead th { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.875rem 0.5rem; border: none; white-space: nowrap; position: sticky; top: 0; z-index: 10; }
body.dark-mode #tabelaResultados thead th { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
#tabelaResultados tbody tr { transition: all var(--transition-fast); }
#tabelaResultados tbody tr:hover { background-color: rgb(37 99 235 / 0.04); }
#tabelaResultados tbody td { padding: 0.75rem 0.5rem; border-bottom: 1px solid var(--border-color); color: var(--text-primary); font-size: 0.85rem; vertical-align: middle; white-space: normal; word-wrap: break-word; overflow-wrap: break-word; }

/* Larguras colunas desktop */
@media (min-width: 992px) {
    #tabelaResultados th:nth-child(1), #tabelaResultados td:nth-child(1) { width: 7%; }
    #tabelaResultados th:nth-child(2), #tabelaResultados td:nth-child(2) { width: 9%; }
    #tabelaResultados th:nth-child(3), #tabelaResultados td:nth-child(3) { width: 20%; }
    #tabelaResultados th:nth-child(4), #tabelaResultados td:nth-child(4) { width: 16%; }
    #tabelaResultados th:nth-child(5), #tabelaResultados td:nth-child(5) { width: 12%; }
    #tabelaResultados th:nth-child(6), #tabelaResultados td:nth-child(6) { width: 9%; }
    #tabelaResultados th:nth-child(7), #tabelaResultados td:nth-child(7) { width: 15%; }
    #tabelaResultados th:nth-child(8), #tabelaResultados td:nth-child(8) { width: 12%; text-align: center; }
}

#tabelaResultados td:nth-child(3), #tabelaResultados td:nth-child(4) { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Tablet */
@media (max-width: 991.98px) { 
    #tabelaResultados { min-width: 750px; } 
    #tabelaResultados thead th, #tabelaResultados tbody td { padding: 0.625rem 0.4rem; font-size: 0.8rem; } 
}

/* Mobile - Card View */
@media (max-width: 767.98px) {
    #tabelaResultados { min-width: 100%; display: block; border-radius: 0; box-shadow: none; background: transparent; }
    #tabelaResultados thead { display: none; }
    #tabelaResultados tbody { display: flex; flex-direction: column; gap: 1rem; }
    #tabelaResultados tbody tr { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; padding: 1rem; background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border-left: 4px solid var(--primary-color); }
    #tabelaResultados tbody tr:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
    #tabelaResultados tbody td { display: flex; flex-direction: column; padding: 0.375rem 0; border-bottom: none; width: 100% !important; max-width: 100% !important; white-space: normal; overflow: visible; }
    #tabelaResultados tbody td::before { content: attr(data-label); font-weight: 700; font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 0.25rem; }
    #tabelaResultados tbody td:nth-child(3), #tabelaResultados tbody td:nth-child(7) { grid-column: 1 / -1; max-width: none; }
    #tabelaResultados tbody td:nth-child(8) { grid-column: 1 / -1; flex-direction: row; justify-content: center; gap: 0.75rem; padding-top: 0.75rem; margin-top: 0.5rem; border-top: 1px solid var(--border-color); }
    #tabelaResultados tbody td:nth-child(8)::before { display: none; }
}
@media (max-width: 479.98px) { #tabelaResultados tbody tr { grid-template-columns: 1fr; } }

/* STATUS BADGES */
.status-badge { display: inline-flex; align-items: center; padding: 0.375rem 0.75rem; border-radius: var(--radius-full); font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap; box-shadow: var(--shadow-sm); transition: all var(--transition-normal); }
.status-badge:hover { transform: scale(1.02); }
.status-pendente { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #78350f; }
.status-exigencia-cumprida { background: linear-gradient(135deg, #34d399 0%, #10b981 100%); color: white; }
.status-exigencia-nao-cumprida { background: linear-gradient(135deg, #f87171 0%, #ef4444 100%); color: white; }
.status-prazo-expirado { background: linear-gradient(135deg, #fb923c 0%, #f97316 100%); color: white; }
.status-em-analise { background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%); color: white; }
.status-cancelada { background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%); color: white; }
.status-aguardando-documentacao { background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%); color: white; }
@media (max-width: 767.98px) { .status-badge { font-size: 0.625rem; padding: 0.3125rem 0.625rem; } }

/* BOTÕES TABELA */
#tabelaResultados .btn-sm { padding: 0.5rem 0.625rem; font-size: 0.875rem; border-radius: var(--radius-md); border: none; transition: all var(--transition-normal); display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; }
#tabelaResultados .btn-info { background: linear-gradient(135deg, #38bdf8 0%, #0ea5e9 100%); color: white; }
#tabelaResultados .btn-info:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgb(14 165 233 / 0.4); }
#tabelaResultados .btn-edit, .btn-edit { background: linear-gradient(135deg, #fcd34d 0%, #f59e0b 100%); color: #78350f; }
#tabelaResultados .btn-edit:hover, .btn-edit:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgb(245 158 11 / 0.4); color: #78350f; }
@media (max-width: 767.98px) { #tabelaResultados .btn-sm { padding: 0.75rem 1.25rem; min-width: 48px; height: 44px; } }

/* MODAL */
.modal { position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; background: rgba(0, 0, 0, 0.6) !important; backdrop-filter: blur(4px); z-index: 1055 !important; display: none !important; overflow-y: auto; padding: 1rem; }
.modal.show { display: flex !important; align-items: flex-start; justify-content: center; }
.modal * { box-sizing: border-box !important; }
#viewNotaModal .modal-dialog { width: 100%; max-width: 900px; margin: 0 auto; }
.modal-dialog { position: relative !important; width: 100% !important; max-width: 95% !important; margin: 1rem auto !important; }
@media (min-width: 576px) { .modal-dialog { max-width: 90% !important; } }
@media (min-width: 992px) { .modal-dialog { max-width: 80% !important; } #viewNotaModal .modal-dialog { max-width: 900px; } }
@media (min-width: 1200px) { .modal-dialog { max-width: 1200px !important; } }

.modal-content { background: var(--bg-card) !important; border: none !important; border-radius: var(--radius-xl) !important; box-shadow: var(--shadow-xl) !important; display: flex !important; flex-direction: column !important; max-height: calc(100vh - 2rem); overflow: hidden; }
@media (min-width: 768px) { .modal-content { max-height: calc(100vh - 4rem); } }

.modal-header { padding: 1.25rem 1.5rem !important; border-bottom: 1px solid var(--border-color) !important; display: flex !important; flex-wrap: wrap; align-items: center !important; gap: 1rem; background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-light) 100%); }
#viewNotaModal .modal-header { flex-direction: column; align-items: stretch; text-align: left; position: relative; padding-right: 3rem !important; }
@media (min-width: 768px) { #viewNotaModal .modal-header { flex-direction: row; flex-wrap: wrap; align-items: center; padding: 1.5rem !important; } }
.modal-title { font-size: 1.25rem !important; font-weight: 700 !important; color: var(--text-primary) !important; margin: 0 !important; }
#viewNotaModal .modal-header h5 { font-size: 1.375rem; font-weight: 800; color: var(--text-primary); margin: 0; flex: 1; }
@media (max-width: 767.98px) { #viewNotaModal .modal-header h5 { font-size: 1.125rem; } }

.btn-close { position: absolute !important; right: 1rem !important; top: 1rem !important; width: 32px !important; height: 32px !important; padding: 0 !important; background: var(--bg-light) !important; border: none !important; border-radius: var(--radius-full) !important; font-size: 1.25rem !important; color: var(--text-secondary) !important; cursor: pointer !important; transition: all var(--transition-normal) !important; display: flex !important; align-items: center !important; justify-content: center !important; z-index: 10; }
.btn-close:hover { background: var(--danger-color) !important; color: white !important; transform: rotate(90deg) !important; }
.btn-close:focus { outline: none !important; box-shadow: 0 0 0 3px rgb(239 68 68 / 0.2) !important; }

.modal-body { flex: 1 !important; padding: 1.5rem !important; overflow-y: auto !important; background: var(--bg-light); }
#viewNotaModal .modal-body, #notaModalBody { background: var(--bg-light); color: var(--text-primary); }

.modal-footer { padding: 1rem 1.5rem !important; border-top: 1px solid var(--border-color) !important; display: flex !important; flex-wrap: wrap !important; gap: 0.75rem; justify-content: flex-end !important; background: var(--bg-card); }
#viewNotaModal .modal-footer { justify-content: space-between; }
@media (max-width: 575.98px) { .modal-footer { flex-direction: column; } .modal-footer .btn { width: 100%; } #viewNotaModal .modal-footer { flex-direction: column-reverse; } }
.modal-footer .btn { padding: 0.625rem 1.25rem; font-size: 0.9375rem; font-weight: 600; border-radius: var(--radius-md); transition: all var(--transition-normal); display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; border: none; }
.modal-footer .btn-secondary { background: var(--secondary-color); color: white; }
.modal-footer .btn-secondary:hover { background: #475569; transform: translateY(-1px); }
.modal-footer .btn-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); color: white; }
.modal-footer .btn-primary:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
</style>
