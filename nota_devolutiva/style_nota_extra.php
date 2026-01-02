<style>
/* STATUS CONTROLS NO MODAL */
.status-controls { display: flex; align-items: center; gap: 0.75rem; background: var(--bg-light); border-radius: var(--radius-full); padding: 0.5rem 0.75rem 0.5rem 1rem; box-shadow: inset 0 1px 3px rgb(0 0 0 / 0.1); width: 100%; margin-top: 0.75rem; }
@media (min-width: 768px) { .status-controls { width: auto; min-width: 320px; max-width: 400px; margin-top: 0; } }
.select-status-wrapper { flex: 1; min-width: 0; }
#statusSelect { width: 100%; padding: 0.5rem 2rem 0.5rem 0.875rem; font-size: 0.8125rem; font-weight: 700; border: none; border-radius: var(--radius-full); background-color: var(--warning-color); color: white; cursor: pointer; transition: all var(--transition-normal); appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='6' fill='white'%3E%3Cpath d='M0 0l6 6 6-6z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.75rem center; text-transform: uppercase; letter-spacing: 0.03em; }
#statusSelect:focus { outline: none; box-shadow: 0 0 0 3px rgb(37 99 235 / 0.25); }
#statusSelect option { color: var(--text-primary); background: var(--bg-card); padding: 0.5rem; font-weight: 500; }
#statusSelect.select-pendente { background-color: #f59e0b; }
#statusSelect.select-exigencia-cumprida { background-color: #10b981; }
#statusSelect.select-exigencia-nao-cumprida { background-color: #ef4444; }
#statusSelect.select-prazo-expirado { background-color: #f97316; }
#statusSelect.select-em-analise { background-color: #3b82f6; }
#statusSelect.select-cancelada { background-color: #64748b; }
#statusSelect.select-aguardando-documentacao { background-color: #8b5cf6; }
#btnUpdateStatus { flex-shrink: 0; padding: 0.5rem 1rem; font-size: 0.8125rem; font-weight: 700; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); color: white; border: none; border-radius: var(--radius-full); cursor: pointer; transition: all var(--transition-normal); white-space: nowrap; text-transform: uppercase; letter-spacing: 0.03em; }
#btnUpdateStatus:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgb(37 99 235 / 0.4); }
@media (max-width: 575.98px) { .status-controls { flex-direction: column; border-radius: var(--radius-lg); padding: 0.75rem; } .select-status-wrapper { width: 100%; } #btnUpdateStatus { width: 100%; padding: 0.75rem 1rem; } }

/* CONTEÚDO DA NOTA */
.nota-content { max-height: 55vh; overflow-y: auto; padding: 1.25rem; background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
body.dark-mode .nota-content { background: #1e293b; border-color: #334155; }
.nota-metadata { margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
.nota-metadata p { margin-bottom: 0.375rem; font-size: 0.9375rem; color: var(--text-secondary); }
.nota-metadata p strong { color: var(--text-primary); }
.nota-body { font-size: 1rem; line-height: 1.7; color: var(--text-primary); }
.section-title { font-weight: 700; font-size: 1.0625rem; color: var(--text-primary); margin-top: 1.25rem; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary-color); display: inline-block; }
.nota-prazo-cumprimento { margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); }

/* DARK MODE MODAL */
body.dark-mode .modal-content { background: var(--bg-card) !important; }
body.dark-mode .modal-header { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-color: var(--border-color) !important; }
body.dark-mode .modal-header h5, body.dark-mode #viewNotaModal .modal-header h5 { color: var(--text-primary) !important; }
body.dark-mode .modal-body { background: var(--bg-light); }
body.dark-mode .modal-footer { background: var(--bg-card); border-color: var(--border-color) !important; }
body.dark-mode .btn-close { background: #334155 !important; color: #94a3b8 !important; }
body.dark-mode .btn-close:hover { background: var(--danger-color) !important; color: white !important; }
body.dark-mode .status-controls { background: #334155; }
body.dark-mode #statusSelect option { background: #1e293b; color: #f1f5f9; }

/* FORMULÁRIOS */
.form-row { display: flex; flex-wrap: wrap; margin: 0 -0.5rem; }
.form-row > .form-group { padding: 0 0.5rem; margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.8125rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.025em; }
.form-group .form-control { width: 100%; padding: 0.625rem 0.875rem; font-size: 0.9375rem; border: 1.5px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary); transition: all var(--transition-normal); }
.form-group .form-control:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgb(37 99 235 / 0.15); }
.form-group textarea.form-control { min-height: 120px; resize: vertical; }
.input-group { display: flex; width: 100%; }
.input-group .form-control { flex: 1; border-top-right-radius: 0; border-bottom-right-radius: 0; }
.input-group-append { display: flex; }
.input-group-append .btn, .input-group-append .input-group-text { border-top-left-radius: 0; border-bottom-left-radius: 0; border-left: none; }
.input-group-text { display: flex; align-items: center; padding: 0.625rem 0.875rem; background: var(--bg-light); border: 1.5px solid var(--border-color); border-radius: var(--radius-md); }
@media (max-width: 575.98px) { .form-row > .form-group[class*="col-"] { flex: 0 0 100%; max-width: 100%; } }
@media (min-width: 576px) and (max-width: 767.98px) { .form-row > .form-group.col-md-2, .form-row > .form-group.col-md-3, .form-row > .form-group.col-md-4 { flex: 0 0 50%; max-width: 50%; } .form-row > .form-group.col-md-6 { flex: 0 0 100%; max-width: 100%; } }

/* HEADER */
.main-content h3 { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin: 0; }
@media (max-width: 767.98px) { .main-content h3 { font-size: 1.25rem; } .main-content .d-flex.justify-content-between { flex-direction: column; gap: 1rem; } .main-content .d-flex.justify-content-between > div { display: flex; flex-wrap: wrap; gap: 0.5rem; } .main-content .d-flex.justify-content-between .btn { flex: 1; min-width: 140px; } }

#notaForm button[type="submit"], #notaForm .btn-primary { padding: 0.875rem 2rem; font-size: 1rem; font-weight: 700; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); color: white; border: none; border-radius: var(--radius-md); transition: all var(--transition-normal); text-transform: uppercase; letter-spacing: 0.05em; }
#notaForm button[type="submit"]:hover, #notaForm .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgb(37 99 235 / 0.35); }

/* MODAL NOTAS ANTERIORES */
#notasAnterioresModal .modal-dialog { max-width: 95%; }
@media (min-width: 992px) { #notasAnterioresModal .modal-dialog { max-width: 1100px; } }
#notasTable { width: 100% !important; zoom: 1 !important; }
.col-numero { width: 10% !important; }
.col-data { width: 12% !important; }
.col-apresentante { width: 28% !important; }
.col-titulo { width: 35% !important; }
.col-acoes { width: 15% !important; text-align: center !important; }
.action-buttons { display: flex !important; gap: 0.5rem !important; justify-content: center !important; flex-wrap: wrap; }
.btn-action { width: 36px !important; height: 36px !important; padding: 0 !important; border: none !important; border-radius: var(--radius-md) !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; transition: all var(--transition-normal) !important; font-size: 0.875rem; }
.btn-view { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%) !important; color: var(--primary-color) !important; }
.btn-view:hover { background: var(--primary-color) !important; color: white !important; transform: translateY(-2px) !important; box-shadow: 0 4px 12px rgb(37 99 235 / 0.3) !important; }
.btn-use { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%) !important; color: var(--success-color) !important; }
.btn-use:hover { background: var(--success-color) !important; color: white !important; transform: translateY(-2px) !important; box-shadow: 0 4px 12px rgb(16 185 129 / 0.3) !important; }
.cell-content { max-width: 100% !important; overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; }

/* TABELA GERAL */
.table { width: 100% !important; border-collapse: collapse !important; margin: 0 !important; }
.table thead th { background: linear-gradient(135deg, #1e293b 0%, #334155 100%) !important; color: white !important; padding: 0.875rem 1rem !important; font-weight: 600 !important; font-size: 0.8125rem !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; border: none !important; white-space: nowrap; }
body.dark-mode .table thead th { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important; }
.table td { padding: 0.75rem 1rem !important; border-bottom: 1px solid var(--border-color) !important; vertical-align: middle !important; font-size: 0.9rem; color: var(--text-primary); }
.table tbody tr:hover { background-color: rgb(37 99 235 / 0.04); }

/* DATATABLE */
.dataTables_wrapper { padding: 0; }
.dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { margin-bottom: 1rem; }
.dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input { padding: 0.5rem 0.75rem; border: 1.5px solid var(--border-color); border-radius: var(--radius-md); font-size: 0.875rem; }
.dataTables_wrapper .dataTables_filter input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgb(37 99 235 / 0.15); }
.dataTables_wrapper .dataTables_paginate .paginate_button { padding: 0.5rem 0.75rem; margin: 0 0.125rem; border-radius: var(--radius-md); border: 1px solid var(--border-color) !important; background: var(--bg-card) !important; color: var(--text-primary) !important; transition: all var(--transition-normal); }
.dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: var(--primary-color) !important; color: white !important; border-color: var(--primary-color) !important; }
.dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--primary-color) !important; color: white !important; border-color: var(--primary-color) !important; }
.dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.5; cursor: not-allowed; }
@media (max-width: 767.98px) { .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { text-align: center; width: 100%; } .dataTables_wrapper .dataTables_filter input { width: 100%; max-width: 300px; } .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { text-align: center; margin-top: 1rem; } }

/* ALERTAS */
.alert { padding: 1rem 1.25rem; border-radius: var(--radius-lg); margin-bottom: 1.25rem; border: none; display: flex; align-items: flex-start; gap: 0.75rem; }
.alert-danger { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #991b1b; border-left: 4px solid var(--danger-color); }
.alert-success { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); color: #166534; border-left: 4px solid var(--success-color); }
.alert .btn-close { position: relative !important; right: auto !important; top: auto !important; width: 24px !important; height: 24px !important; font-size: 1rem !important; margin-left: auto; }

/* UTILITÁRIOS */
.btn-success { width: auto; height: auto; border-radius: var(--radius-md); }
.cke_notification_warning { display: none !important; }

/* ANIMAÇÕES */
@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.modal.show .modal-content { animation: slideUp 0.3s ease-out; }
#tabelaResultados tbody tr { animation: fadeIn 0.3s ease-out; animation-fill-mode: both; }
#tabelaResultados tbody tr:nth-child(1) { animation-delay: 0.05s; }
#tabelaResultados tbody tr:nth-child(2) { animation-delay: 0.1s; }
#tabelaResultados tbody tr:nth-child(3) { animation-delay: 0.15s; }
#tabelaResultados tbody tr:nth-child(4) { animation-delay: 0.2s; }
#tabelaResultados tbody tr:nth-child(5) { animation-delay: 0.25s; }

/* SCROLLBAR */
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: var(--bg-light); border-radius: var(--radius-full); }
::-webkit-scrollbar-thumb { background: var(--secondary-color); border-radius: var(--radius-full); }
::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

/* PRINT */
@media print { .modal-footer, .btn-close, .status-controls { display: none !important; } .modal-content { box-shadow: none !important; border: 1px solid #ddd !important; } .nota-content { max-height: none !important; overflow: visible !important; } }
</style>
