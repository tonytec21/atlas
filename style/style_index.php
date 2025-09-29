<style>
/* ======================= BASE DA PÁGINA ======================= */
body {
  background-color: #f8f9fa;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.main-container {
  max-width: 1400px;
  margin: 30px auto;
  padding: 0 20px;
}

.page-title {
  font-size: 2.0rem;
  font-weight: 700;
  color: #34495e;
  margin-bottom: 2rem;
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 1px;
}

body.dark-mode .page-title { color: #fff; }

/* divisor abaixo do título */
.title-divider {
  height: 4px;
  width: 120px;
  background-color: #0d6efd;
  margin: 0 auto 30px auto;
  border-radius: 2px;
}

/* ======================= BUSCA / GRID ======================= */
.search-container { margin-bottom: 30px; }

.search-box {
  width: 100%;
  max-width: 800px;
  padding: 12px 20px;
  border-radius: 100px;
  border: 1px solid #e0e0e0;
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
  font-size: 16px;
  background-image: url('style/img/search-icon.png');
  background-repeat: no-repeat;
  background-position: 15px center;
  background-size: 16px;
  padding-left: 45px;
  display: block;
  margin: 0 auto;
}
.search-box:focus {
  outline: none;
  border-color: #0d6efd;
  box-shadow: 0 2px 8px rgba(13,110,253,0.15);
}
body.dark-mode .search-box {
  background-color: #22272e;
  border-color: #2f3a46;
  color: #e0e0e0;
  box-shadow: none;
}

#sortable-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}
@media (max-width: 1200px) {
  #sortable-cards { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 992px) {
  #sortable-cards { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  #sortable-cards { grid-template-columns: 1fr; }
  .main-container { padding: 0 15px; margin: 20px auto; }
}

/* Placeholder do jQuery UI sortable */
.ui-state-highlight {
  height: 240px;
  background-color: #f8f9fa;
  border: 2px dashed #dee2e6;
  border-radius: 12px;
}

/* ======================= CARDS DOS MÓDULOS ======================= */
.module-card {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.05);
  padding: 20px;
  transition: all 0.3s ease;
  height: 100%;
  display: flex;
  flex-direction: column;
  border: none;
}
.module-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

/* Dark mode harmonizado para cards */
.dark-mode .module-card {
  background-color: #1f242d;           /* tom escuro elegante */
  border: 1px solid #2b323d;
  box-shadow: 0 6px 18px rgba(0,0,0,0.35);
}
.dark-mode .card-title { color: #e5e7eb; }
.dark-mode .card-description { color: #b6c0cc; }

.card-header {
  display: flex;
  align-items: flex-start;
  margin-bottom: 15px;
}
.card-badge {
  font-size: 12px;
  font-weight: 500;
  padding: 5px 12px;
  border-radius: 100px;
  margin-right: 10px;
  background: #f3f4f6;
  color: #374151;
}
.dark-mode .card-badge { background: #2b3340; color: #cbd5e1; }

.card-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: auto;
}
.card-title {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 10px;
  color: #333;
}
.card-description {
  font-size: 14px;
  color: #6c757d;
  margin-bottom: 20px;
  flex-grow: 1;
  line-height: 1.5;
}

.card-button {
  border: none;
  border-radius: 8px;
  padding: 10px 15px;
  font-weight: 500;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  cursor: pointer;
  transition: all 0.2s;
  color: white;
}
.card-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.card-button i { margin-right: 8px; }

/* Categorias */
.badge-operacional  { background-color: #e6f4ff; color: #0c4a6e; }
.badge-financeiro   { background-color: #ffecec; color: #9f1239; }
.badge-administrativo { background-color: #eaf7ef; color: #14532d; }
.badge-juridico     { background-color: #fff4d5; color: #92400e; }
.badge-documental   { background-color: #f5eaff; color: #6b21a8; }

/* Ícones (cores preservadas) */
.icon-arquivamento { background-color: #4169E1; color: #fff; }
.icon-os           { background-color: #17a2b8; color: #fff; }
.icon-caixa        { background-color: #006400; color: #fff; }
.icon-tarefas      { background-color: #708090; color: #fff; }
.icon-devolutivas  { background-color: #9c27b0; color: #fff; }
.icon-oficios      { background-color: #ffc107; color: #fff; }
.icon-provimentos  { background-color: #8B0000; color: #fff; }
.icon-guia         { background-color: #34495e; color: #fff; }
.icon-agenda       { background-color: #A7D676; color: #fff; }
.icon-contas       { background-color: #ff8a80; color: #fff; }
.icon-manuais      { background-color: #008B8B; color: #fff; }
.icon-indexador    { background-color: #FF7043; color: #fff; }
.icon-xuxuzinho    { background-color: #2c3e50; color: #fff; }
.icon-anotacao     { background-color: #2F4F4F; color: #fff; }
.icon-relatorios   { background-color: #4A90E2; color: #fff; }

/* Botões (cores preservadas) */
.btn-arquivamento { background-color: #4169E1; }
.btn-os           { background-color: #17a2b8; }
.btn-caixa        { background-color: #006400; }
.btn-tarefas      { background-color: #708090; }
.btn-oficios      { background-color: #FF69B4; }
.btn-provimentos  { background-color: #8B0000; }
.btn-guia         { background-color: #34495e; }
.btn-agenda       { background-color: #A7D676; }
.btn-contas       { background-color: #ff8a80; }
.btn-manuais      { background-color: #008B8B; }
.btn-indexador    { background-color: #FF7043; }
.btn-xuxuzinho    { background-color: #2c3e50; }
.btn-anotacao     { background-color: #2F4F4F; }
.btn-relatorios   { background-color: #4A90E2; }

/* Hover extra */
.btn-devolutivas { background-color: #9c27b0; color: #fff; }
.btn-devolutivas:hover { background-color: rgb(115,26,131); color: #fff; }

/* ======================= MODAL DE TAREFAS (RESPONSIVO) ======================= */
#tarefasModal .modal-dialog {
  /* Largura adaptativa ao viewport (sobrescreve inline) */
  max-width: min(1100px, 96vw) !important;
  width: auto;
  margin: 1rem auto;
}
#tarefasModal .modal-content {
  border-radius: 12px;
  border: none;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}
#tarefasModal .modal-header {
  position: sticky;
  top: 0;
  z-index: 2;
  background-color: #f8f9fa;
  border-bottom: 1px solid #f0f0f0;
  padding: 16px 20px;
}
#tarefasModal .modal-title { font-weight: 600; font-size: 20px; }

#tarefasModal .modal-body {
  padding: 20px;
  /* Altura rolável conforme viewport */
  max-height: calc(100dvh - 220px);
  overflow: auto;
}

/* Seções do modal */
.section-title {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
}
.section-title i { margin-right: 8px; }

.task-list-container {
  background-color: #f9f9f9;
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 20px;
}
.dark-mode .task-list-container { background-color: #252525; }

/* Tabelas do modal */
#tarefasModal .table { font-size: 14px; margin-bottom: 0; }
#tarefasModal .table th { font-weight: 600; color: #495057; }
#tarefasModal .table td, 
#tarefasModal .table th { vertical-align: middle; }

/* ======================= BADGES PASTÉIS (Status e Situação) ======================= */
.soft-badge {
  display: inline-block;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid transparent;
  line-height: 1;
  white-space: nowrap;
}

/* paleta clara */
.soft-blue   { background: #e7f0fe; color: #1e3a8a; border-color: #bfdbfe; }
.soft-amber  { background: #fff3d6; color: #92400e; border-color: #fde68a; }
.soft-indigo { background: #e8ebff; color: #3730a3; border-color: #c7d2fe; }
.soft-green  { background: #e8fbef; color: #065f46; border-color: #bbf7d0; }
.soft-rose   { background: #ffe8ea; color: #9f1239; border-color: #fecdd3; }
.soft-slate  { background: #edf2f7; color: #374151; border-color: #cbd5e1; }
.soft-orange { background: #fff1e6; color: #9a3412; border-color: #fdba74; }
.soft-red    { background: #ffe5e5; color: #991b1b; border-color: #fca5a5; }

/* ======================= LINHAS DESTACADAS POR SITUAÇÃO ======================= */
.row-quase-vencida { background-color: #fff6e6 !important; }
.row-vencida       { background-color: #ffe9ec !important; }

/* Dark mode de tabelas/badges/cores */
.dark-mode #tarefasModal .table th { font-weight: 600; color: #fff; }
.dark-mode #tarefasModal .table { color: #e0e0e0; }
.dark-mode .row-quase-vencida { background-color: #3a2e1f !important; }
.dark-mode .row-vencida       { background-color: #3a2226 !important; }

/* Responsividade do modal */
@media (max-width: 768px) {
  #tarefasModal .modal-dialog { margin: 0.5rem; }
  #tarefasModal .modal-header { padding: 12px 16px; }
  #tarefasModal .modal-body { padding: 12px; max-height: calc(100dvh - 160px); }
  #tarefasModal .table { font-size: 13px; }
}

/* ======================= MODAL ACESSO NEGADO / GENÉRICOS ======================= */
.modal-content { border: none; border-radius: 15px; }
.modal-header  { border-radius: 15px 15px 0 0; padding: 1.25rem; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; }
.modal-title   { letter-spacing: 0.5px; font-weight: 600; color: #333; }
.modal-subtitle{ font-size: 0.9rem; color: #6c757d; margin-top: 0.25rem; }

.modal-body table { width: 100%; margin-bottom: 1rem; background-color: #fff; border-collapse: collapse; }
.modal-body table th, .modal-body table td { padding: 0.75rem; border: 1px solid #dee2e6; }
.modal-body table th { background-color: #f8f9fa; font-weight: 600; }
.modal-body table tr:hover { background-color: #f8f9fa; }

/* Footer do modal padrão */
.modal-footer .btn-secondary {
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  color: #6c757d;
  font-weight: 500;
  padding: 0.5rem 1.5rem;
  border-radius: 6px;
  transition: all 0.2s;
}
.modal-footer .btn-secondary:hover { background-color: #e9ecef; color: #495057; }

/* ======================= MODO ESCURO (modais genéricos) ======================= */
.dark-mode .modal-content { background-color: #1e2124; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
.dark-mode .modal-header  { background-color: #2c2f33!important; border-color: #40444b; }
.dark-mode .modal-footer  { background-color: #2c2f33!important; border-color: #40444b; }
.dark-mode .modal-title   { color: #fff; }
.dark-mode .modal-subtitle{ color: #a0a0a0; }

.dark-mode .modal-body table    { background-color: #2c2f33; color: #fff; }
.dark-mode .modal-body table th { background-color: #40444b; color: #fff; border-color: #40444b; }
.dark-mode .modal-body table td { border-color: #40444b; }
.dark-mode .modal-body table tr:hover { background-color: #34373c; }

.dark-mode .modal-footer .btn-secondary { background-color: #40444b; border-color: #40444b; color: #fff; }
.dark-mode .modal-footer .btn-secondary:hover { background-color: #4a4f57; }

/* ======================= SCROLLBAR DO MODAL ======================= */
.modal-body::-webkit-scrollbar { width: 8px; }
.modal-body::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.modal-body::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
.modal-body::-webkit-scrollbar-thumb:hover { background: #555; }
.dark-mode .modal-body::-webkit-scrollbar-track { background: #2c2f33; }
.dark-mode .modal-body::-webkit-scrollbar-thumb { background: #40444b; }
.dark-mode .modal-body::-webkit-scrollbar-thumb:hover { background: #4a4f57; }

/* ======================= BOTÃO CLOSE ======================= */
.btn-close { opacity: 0.7; transition: all 0.2s; }
.btn-close:hover { opacity: 1; transform: rotate(90deg); }
.dark-mode .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

/* ======================= MODAL ALERTA (GENÉRICO) ======================= */
.modal-alerta .modal-content { border: 2px solid #dc3545; background-color: #f8d7da; color: #721c24; }
.modal-alerta .modal-header  { background-color: #dc3545; color: #fff; }
.modal-alerta .modal-body    { font-weight: bold; }
.modal-alerta .modal-footer  { background-color: #f5c6cb; }
.modal-alerta .btn-close     { background-color: #fff; border: 1px solid #dc3545; }
.modal-alerta .btn-close:hover { background-color: #dc3545; color: #fff; }
.modal-alerta .modal-footer .btn-secondary { background-color: #dc3545; border: none; }
.modal-alerta .modal-footer .btn-secondary:hover { background-color: #c82333; }

/* ======================= MODAL RECORRENTE (FULLSCREEN) ======================= */
.modal-alert-recorrente {
  background: #8B0000; /* vermelho escuro */
  color: #fff;
  border: none;
}
.modal-alert-recorrente .titulo-alerta { font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
.modal-alert-recorrente .icone-alerta { font-size: 80px; line-height: 1; color: #fff; animation: pulsar 1.2s infinite; }
.modal-alert-recorrente .form-check-input:checked { background-color: #fff; border-color: #fff; }
.modal-alert-recorrente .form-check-label { color: #fff; }
.modal-alert-recorrente textarea { background: rgba(255,255,255,0.15); color: #fff; border: 2px solid #fff; }
.modal-alert-recorrente textarea::placeholder { color: #f1f1f1; }
.modal-alert-recorrente .btn-light {
  background:#fff; color:#8B0000; border:none; box-shadow: 0 0 0 3px rgba(255,255,255,0.4);
}
.modal-alert-recorrente .btn-light:hover { filter: brightness(0.9); }
.modal-alert-recorrente .texto-bloqueio { opacity:0.85; }
@keyframes pulsar { 0%,100% { transform: scale(1);} 50% { transform: scale(1.15);} }

/* Blur no fundo enquanto o modal estiver aberto */
.modal-backdrop.show {
  backdrop-filter: blur(6px);
  background-color: rgba(0,0,0,0.4);
}

/* ======================= OUTROS AJUSTES (BOTÕES/TEXTOS) ======================= */
.btn { border-radius: 10px!important; }
.btn-warning { color: #fff!important; }

.text-success { color: #28a745 !important; }
.text-danger  { color: #dc3545 !important; }

.text-tutoriais { color: #1762b8; }
.btn-tutoriais { background: #1762b8; color: #fff; }
.btn-tutoriais:hover { background: #0c52a3; color: #fff; }

.btn-4 { background: #34495e; color: #fff; }
.btn-4:hover { background: #2c3e50; color: #fff; }
.text-4 { color: #34495e; }
.dark-mode .btn-4 { background: #54718e; color: #fff; }
.dark-mode .btn-4:hover { background: #435c74; color: #fff; }
.dark-mode .text-4 { color: #54718e; }

.btn-5 { background: #ff8a80; color: #fff; }
.btn-5:hover { background: #e3786f; color: #fff; }
.text-5 { color: #ff8a80; }

.btn-6 { background: #427b8e; color: #fff; }
.btn-6:hover { background: #366879; color: #fff; }
.text-6 { color: #427b8e; }

.btn-indexador { background: #FF7043; color: #fff; }
.btn-indexador:hover { background: #D64E27; color: #fff; }
.text-indexador { color: #FF7043; }

.btn-anotacoes { background: #A7D676; color: #fff; }
.btn-anotacoes:hover { background: #7CB342; color: #fff; }
.text-anotacoes { color: #A7D676; }

.btn-reurb { background: #FFC8A2; color: #fff; }
.btn-reurb:hover { background: #f7b283; color: #fff; }
.text-reurb { color: #FFC8A2; }

.btn-relatorios { background: #4A90E2; color: #fff; }
.btn-relatorios:hover { background: #357ABD; color: #fff; }
.text-relatorios { color: #4A90E2; }

/* Ícone flutuante genérico */
.btn-info2 {
  background-color: #17a2b8;
  color: #fff;
  margin-bottom: 3px!important;
  width: 40px; height: 40px;
  border-radius: 5px;
  border: none;
}
.btn-info2:hover { color: #fff; }

/* Chart containers de outras páginas (mantidos) */
.chart-container { position: relative; height: 240px; }
.chart-container.full-height { height: 360px; margin-top: 30px; }
@media (max-width: 768px) {
  .chart-container { height: 200px; margin-top: 20px; }
  .chart-container.full-height { height: 300px; margin-top: 20px; margin-bottom: 20px; }
  .card-body { padding: 1rem; }
  .card { margin-bottom: 1rem; }
}

/* Arrastar (drag) */
#sortable-buttons .col-md-4 { cursor: move; }

/* Notificação flutuante (mantida) */
.notification {
  position: fixed; bottom: 20px; right: 20px;
  background-color: #343a40; color: #fff;
  padding: 15px; border-radius: 5px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); z-index: 1000;
}
.notification .close-btn { cursor: pointer; float: right; margin-left: 10px; }
.w-100 { margin-bottom: 5px; }

/* Animação de entrada do modal (suave) */
.modal.fade .modal-dialog { transform: scale(0.96); transition: transform 0.18s ease-out; }
.modal.show .modal-dialog { transform: scale(1); }

/* ===== Pedidos de Certidão – cores e realces ===== */

/* Ícone do card */
.icon-certidao {
  background: linear-gradient(135deg, #34d399, #10b981);
  color: #ffffff;
  /* box-shadow: 0 10px 30px rgba(16, 185, 129, .35); */
  border: 1px solid rgba(5, 150, 105, .25);
  border-radius: 14px;
}

/* Botão de ação do card */
.btn-certidao {
  background: linear-gradient(135deg, #10b981, #059669);
  border: 1px solid rgba(5, 150, 105, .5);
  color: #ffffff;
  font-weight: 600;
  /* transition: transform .08s ease, box-shadow .2s ease, filter .2s ease; */
}
.btn-certidao:hover {
  transform: translateY(-1px);
  /* box-shadow: 0 10px 26px rgba(16, 185, 129, .35); */
  filter: brightness(1.04);
}
.btn-certidao:active {
  transform: translateY(0);
  filter: brightness(.98);
}

/* Borda/realce do card ao passar o mouse */
.dark-mode .icon-certidao {
  /* box-shadow: 0 14px 34px rgba(16, 185, 129, .45); */
  border-color: rgba(34, 197, 94, .35);
}
.dark-mode .btn-certidao {
  border-color: rgba(34, 197, 94, .5);
}

</style>
