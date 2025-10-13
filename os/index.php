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
    <title>Pesquisar Ordens de Serviço</title>  

    <!-- Fontes modernas -->  
    <link rel="preconnect" href="https://fonts.googleapis.com">  
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>  
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">  

    <link rel="icon" href="../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../style/css/style.css">  

    <?php  
    $mdiCssLocal = __DIR__ . '/../style/css/materialdesignicons.min.css';  
    $mdiWoff2    = __DIR__ . '/../style/fonts/materialdesignicons-webfont.woff2';  
    if (file_exists($mdiCssLocal) && file_exists($mdiWoff2)) {  
      echo '<link rel="stylesheet" href="../style/css/materialdesignicons.min.css">' . PHP_EOL;  
    } else {  
      echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">' . PHP_EOL;  
    }  
    ?>  

    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">  

    <style>  
/* ===================== CSS VARIABLES ===================== */  
:root {  
  --brand-primary: #6366f1;  
  --brand-primary-light: #818cf8;  
  --brand-primary-dark: #4f46e5;  
  --brand-success: #10b981;  
  --brand-warning: #f59e0b;  
  --brand-error: #ef4444;  
  --brand-info: #3b82f6;  

  --bg-primary: #ffffff;  
  --bg-secondary: #f8fafc;  
  --bg-tertiary: #f1f5f9;  
  --bg-elevated: #ffffff;  
  
  --text-primary: #1e293b;  
  --text-secondary: #64748b;  
  --text-tertiary: #94a3b8;  
  --text-inverse: #ffffff;  
  
  --border-primary: #e2e8f0;  
  --border-secondary: #cbd5e1;  
  --border-focus: var(--brand-primary);  
  
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);  
  --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);  
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);  
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);  
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);  
  --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);  
  
  --surface-hover: rgba(99, 102, 241, 0.04);  
  --surface-active: rgba(99, 102, 241, 0.08);  
  
  --space-xs: 4px;  
  --space-sm: 8px;  
  --space-md: 16px;  
  --space-lg: 24px;  
  --space-xl: 32px;  
  --space-2xl: 48px;  
  
  --radius-sm: 6px;  
  --radius-md: 10px;  
  --radius-lg: 14px;  
  --radius-xl: 20px;  
  --radius-2xl: 28px;  
  
  --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;  
  --font-mono: 'JetBrains Mono', 'Fira Code', Consolas, monospace;  
  
  --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);  
  --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);  
  --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);  
  --gradient-error: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);  
  --gradient-mesh: radial-gradient(at 40% 20%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),  
                   radial-gradient(at 80% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),  
                   radial-gradient(at 0% 50%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),  
                   radial-gradient(at 80% 50%, rgba(236, 72, 153, 0.15) 0px, transparent 50%),  
                   radial-gradient(at 0% 100%, rgba(16, 185, 129, 0.15) 0px, transparent 50%),  
                   radial-gradient(at 80% 100%, rgba(245, 158, 11, 0.15) 0px, transparent 50%);  
}  

.dark-mode {  
  --bg-primary: #0f172a;  
  --bg-secondary: #1e293b;  
  --bg-tertiary: #334155;  
  --bg-elevated: #1e293b;  
  
  --text-primary: #f1f5f9;  
  --text-secondary: #cbd5e1;  
  --text-tertiary: #94a3b8;  
  
  --border-primary: #334155;  
  --border-secondary: #475569;  
  
  --surface-hover: rgba(99, 102, 241, 0.08);  
  --surface-active: rgba(99, 102, 241, 0.12);  
}  

/* ===================== GLOBAL STYLES ===================== */  
body {  
  font-family: var(--font-primary) !important;  
  background: var(--bg-primary) !important;  
  color: var(--text-primary) !important;  
  transition: background-color 0.3s ease, color 0.3s ease;  
  margin: 0 !important;  
  padding: 0 !important;  
  min-height: 100vh !important;  
  display: flex !important;  
  flex-direction: column !important;  
}  

.main-content {  
  position: relative;  
  min-height: auto;  
  flex: 1;  
}  

.main-content::before {  
  content: '';  
  position: fixed;  
  top: 0;  
  left: 0;  
  right: 0;  
  bottom: 0;  
  background: var(--gradient-mesh);  
  pointer-events: none;  
  z-index: 0;  
  opacity: 0.4;  
}  

.container {  
  position: relative;  
  z-index: 1;  
  padding-bottom: var(--space-xl);  
}  

/* ===================== PAGE HERO ===================== */  
.page-hero {  
  padding: var(--space-2xl) 0;  
  margin-bottom: var(--space-xl);  
}  

.title-row {  
  display: flex;  
  align-items: center;  
  gap: var(--space-md);  
}  

.title-icon {  
  width: 64px;  
  height: 64px;  
  background: var(--gradient-primary);  
  border-radius: var(--radius-xl);  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  box-shadow: var(--shadow-lg);  
  flex-shrink: 0;  
}  

.title-icon i {  
  font-size: 32px;  
  color: var(--text-primary);  
  position: relative;  
  z-index: 1;  
}  

.dark-mode .title-icon {
  color: white; 
}

.page-hero h1 {  
  font-size: 28px;  
  font-weight: 800;  
  letter-spacing: -0.02em;  
  color: var(--text-primary);  
  margin: 0;  
  line-height: 1.2;  
}  

.subtitle {  
  font-size: 14px;  
  color: var(--text-secondary);  
  margin-top: var(--space-xs);  
}  

/* ===================== TOP ACTIONS ===================== */  
.top-actions {  
  margin-bottom: var(--space-lg);  
  gap: var(--space-sm);  
}  

.top-actions .btn {  
  border-radius: var(--radius-md);  
  font-weight: 700;  
  padding: 10px 20px;  
  font-size: 14px;  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  display: inline-flex;  
  align-items: center;  
  gap: var(--space-sm);  
  border: none;  
}  

.top-actions .btn i {  
  font-size: 16px;  
}  

.top-actions .btn:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-lg);  
}  

.btn-info2 {  
  background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);  
  color: #fff;  
}  

.btn-info2:hover {  
  opacity: 0.95;  
  color: #fff;  
}  

/* ===================== FILTER CARD ===================== */  
.filter-card {  
  background: var(--bg-elevated);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-lg);  
  padding: var(--space-lg);  
  box-shadow: var(--shadow-md);  
  margin-bottom: var(--space-lg);  
  transition: all 0.3s ease;  
}  

.filter-card:hover {  
  box-shadow: var(--shadow-xl);  
  border-color: var(--border-secondary);  
}  

.filter-card .form-group {  
  margin-bottom: var(--space-md);  
}  

.filter-card label {  
  font-weight: 600;  
  font-size: 13px;  
  color: var(--text-secondary);  
  margin-bottom: var(--space-sm);  
  letter-spacing: 0.01em;  
}  

.filter-card .form-control,  
.filter-card select.form-control {  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-md);  
  padding: 10px 14px;  
  font-size: 14px;  
  color: var(--text-primary);  
  transition: all 0.3s ease;  
  font-family: var(--font-primary);  
}  

.filter-card .form-control:focus,  
.filter-card select.form-control:focus {  
  background: var(--bg-elevated);  
  border-color: var(--brand-primary);  
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);  
  outline: none;  
}  

/* ===================== TABLE WRAP ===================== */  
.table-wrap {  
  background: var(--bg-elevated);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-lg);  
  padding: var(--space-lg);  
  box-shadow: var(--shadow-md);  
  transition: all 0.3s ease;  
}  

.table-wrap:hover {  
  box-shadow: var(--shadow-xl);  
}  

.table-wrap h5 {  
  font-weight: 700;  
  font-size: 18px;  
  color: var(--text-primary);  
  margin-bottom: var(--space-md);  
}  

table.dataTable {  
  border-collapse: separate;  
  border-spacing: 0;  
  width: 100%;  
}  

table.dataTable thead th {  
  background: var(--bg-tertiary);  
  color: var(--text-primary);  
  font-weight: 700;  
  font-size: 13px;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  padding: 12px 10px;  
  border: none;  
  white-space: nowrap;  
}  

table.dataTable tbody td {  
  padding: 12px 10px;  
  border-bottom: 1px solid var(--border-primary);  
  color: var(--text-primary);  
  font-size: 13px;  
}  

table.dataTable tbody tr {  
  transition: background-color 0.2s ease;  
}  

table.dataTable tbody tr:hover {  
  background: var(--surface-hover);  
}  

/* ===================== BADGES DE SITUAÇÃO ===================== */  
.situacao-pago,  
.situacao-ativo,  
.situacao-cancelado,
.situacao-isento {  
  color: #fff;  
  width: 90px;  
  text-align: center;  
  padding: 6px 10px;  
  border-radius: var(--radius-sm);  
  display: inline-block;  
  font-size: 13px;  
  font-weight: 700;  
  letter-spacing: 0.02em;  
}  

.situacao-pago {  
  background: var(--gradient-success);  
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);  
}  

.situacao-ativo {  
  background: var(--gradient-warning);  
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25);  
}  

.situacao-cancelado {  
  background: var(--gradient-error);  
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25);  
}

.situacao-isento {
  background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
  box-shadow: 0 2px 8px rgba(6, 182, 212, 0.25);
}


.status-label {  
  padding: 6px 10px;  
  border-radius: var(--radius-sm);  
  color: #fff;  
  display: inline-block;  
  font-weight: 700;  
  font-size: 13px;  
  letter-spacing: 0.02em;  
}  

.status-pendente {  
  background: var(--gradient-error);  
  min-width: 80px;  
  text-align: center;  
}  

.status-parcialmente {  
  background: var(--gradient-warning);  
  min-width: 80px;  
  text-align: center;  
}  

.status-liquidado {  
  background: var(--gradient-success);  
  min-width: 80px;  
  text-align: center;  
}  

/* ===================== BUTTONS ===================== */  
.btn {  
  border-radius: var(--radius-md);  
  padding: 10px 20px;  
  font-weight: 700;  
  font-size: 14px;  
  letter-spacing: 0.01em;  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  border: none;  
  display: inline-flex;  
  align-items: center;  
  justify-content: center;  
  gap: var(--space-sm);  
  font-family: var(--font-primary);  
}  

.btn i {  
  font-size: 16px;  
}  

.btn-primary {  
  background: var(--gradient-primary);  
  color: white;  
  box-shadow: var(--shadow-md);  
}  

.btn-primary:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-xl);  
  opacity: 0.95;  
  color: white;  
}  

.btn-success {  
  background: var(--gradient-success);  
  color: white;  
  box-shadow: var(--shadow-md);  
}  

.btn-success:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-xl);  
  opacity: 0.95;  
  color: white;  
}  

.btn-secondary {  
  background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);  
  color: white;  
  box-shadow: var(--shadow-md);  
}  

.btn-secondary:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-xl);  
  opacity: 0.95;  
  color: white;  
}  

.btn-warning {  
  background: var(--gradient-warning);  
  color: white;  
  box-shadow: var(--shadow-md);  
}  

.btn-warning:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-xl);  
  opacity: 0.95;  
  color: white;  
}  

.btn-delete {  
  background: var(--gradient-error);  
  color: white;  
  border: none;  
}  

.btn-delete:hover {  
  opacity: 0.9;  
  color: white;  
}  

/* ===================== ACTION BUTTONS ===================== */  
.action-btn {  
  margin-bottom: 5px;  
  font-size: 20px;  
  width: 40px;  
  height: 40px;  
  border-radius: var(--radius-sm);  
  border: none;  
  display: inline-flex;  
  align-items: center;  
  justify-content: center;  
  transition: all 0.3s ease;  
  cursor: pointer;  
}  

.action-btn:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-lg);  
}  

.btn-info2.action-btn {  
  background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);  
  color: white;  
}  

.btn-success.action-btn {  
  background: var(--gradient-success);  
  color: white;  
}  

.btn-primary.action-btn {  
  background: var(--gradient-primary);  
  color: white;  
}  

.btn-secondary.action-btn {  
  background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);  
  color: white;  
}  

.btn-secondary.action-btn.btn-danger {  
  background: var(--gradient-error);  
  color: white;  
}  

/* ===================== MODALS ===================== */  
.modal-modern .modal-content {  
  border-radius: var(--radius-lg);  
  border: 2px solid var(--border-primary);  
  background: var(--bg-elevated);  
  color: var(--text-primary);  
  box-shadow: var(--shadow-2xl);  
  display: flex;  
  flex-direction: column;  
  max-height: min(90vh, 100dvh);  
}  

.modal-modern .modal-header {  
  background: var(--gradient-primary);  
  color: #fff;  
  border-top-left-radius: calc(var(--radius-lg) - 2px);  
  border-top-right-radius: calc(var(--radius-lg) - 2px);  
  border-bottom: 0;  
  display: flex;  
  justify-content: space-between;  
  align-items: center;  
  flex: 0 0 auto;  
  padding: var(--space-md) var(--space-lg);  
}  

.modal-modern .modal-title {  
  font-weight: 700;  
  font-size: 18px;  
  margin: 0;  
}  

.modal-modern .modal-body {  
  overflow: auto;  
  padding: var(--space-lg);  
  flex: 1;  
}  

.modal-modern .modal-footer {  
  border-top: 2px solid var(--border-primary);  
  flex: 0 0 auto;  
  padding: var(--space-md) var(--space-lg);  
}  

.btn-close {  
  outline: none;  
  border: none;  
  background: none;  
  padding: 0;  
  font-size: 1.6rem;  
  cursor: pointer;  
  transition: transform 0.2s ease;  
  color: #fff;  
  font-weight: 300;  
}  

.btn-close:hover {  
  transform: scale(1.15);  
}  

/* Modais responsivos */  
.modal-dialog {  
  margin: 1.25rem auto;  
}  

#pagamentoModal .modal-dialog {  
  max-width: 900px;  
}  

#devolucaoModal .modal-dialog {  
  max-width: 520px;  
}  

#anexoModal .modal-dialog {  
  max-width: 700px;  
}  

#mensagemModal .modal-dialog {  
  max-width: 520px;  
}  

@media (max-width: 992px) {  
  #pagamentoModal .modal-dialog {  
    max-width: 95vw;  
  }  
  #anexoModal .modal-dialog {  
    max-width: 95vw;  
  }  
}  

@media (max-width: 576px) {  
  .modal-dialog {  
    max-width: 100vw !important;  
    width: 100vw !important;  
    margin: 0 !important;  
    height: 100dvh;  
  }  

  .modal-content {  
    border-radius: 0 !important;  
    height: 100dvh;  
    max-height: 100dvh;  
  }  

  .modal-modern .modal-body {  
    padding: 12px;  
  }  

  .action-btn {  
    width: 36px;  
    height: 36px;  
    font-size: 18px;  
  }  

  .page-hero {  
    padding: var(--space-xl) 0;  
  }  

  .title-row {  
    flex-direction: column;  
    text-align: center;  
  }  

  .title-icon {  
    width: 56px;  
    height: 56px;  
  }  

  .page-hero h1 {  
    font-size: 22px;  
  }  

  .top-actions {  
    flex-direction: column;  
  }  

  .top-actions .btn {  
    width: 100%;  
    justify-content: center;  
  }  
}  

/* ===================== DROPZONE ===================== */  
.dropzone {  
  border: 3px dashed var(--brand-primary);  
  background: rgba(99, 102, 241, 0.04);  
  border-radius: var(--radius-lg);  
  padding: var(--space-xl);  
  text-align: center;  
  cursor: pointer;  
  transition: all 0.3s ease;  
}  

.dropzone:hover {  
  background: rgba(99, 102, 241, 0.08);  
  border-color: var(--brand-primary-light);  
  transform: translateY(-2px);  
}  

.dropzone.dragover {  
  background: rgba(99, 102, 241, 0.12);  
  border-color: var(--brand-primary);  
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.08) inset;  
}  

.dropzone .dz-icon {  
  width: 46px;  
  height: 46px;  
  border-radius: var(--radius-md);  
  background: rgba(99, 102, 241, 0.1);  
  color: var(--brand-primary);  
  display: inline-flex;  
  align-items: center;  
  justify-content: center;  
  font-size: 22px;  
  margin-bottom: var(--space-sm);  
}  

.dropzone .dz-title {  
  font-weight: 700;  
  color: var(--text-primary);  
  margin-bottom: var(--space-xs);  
}  

.dropzone .dz-sub {  
  color: var(--text-tertiary);  
  font-size: 14px;  
}  

.file-list {  
  margin-top: var(--space-md);  
  text-align: left;  
}  

.file-list .file-item {  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  gap: var(--space-sm);  
  padding: var(--space-sm) var(--space-md);  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-md);  
  margin-bottom: var(--space-sm);  
  word-break: break-all;  
  transition: all 0.3s ease;  
}  

.file-list .file-item:hover {  
  border-color: var(--brand-primary);  
  transform: translateX(4px);  
}  

.file-name {  
  color: var(--text-primary);  
  font-weight: 600;  
  font-size: 14px;  
  display: flex;  
  align-items: center;  
  gap: var(--space-xs);  
}  

.file-size {  
  color: var(--text-tertiary);  
  font-size: 13px;  
}  

.dark-mode .dropzone {  
  border-color: rgba(147, 197, 253, 0.6);  
  background: rgba(147, 197, 253, 0.08);  
}  

.dark-mode .dropzone .dz-icon {  
  background: rgba(147, 197, 253, 0.15);  
  color: #93c5fd;  
}  

.dark-mode .dropzone .dz-title {  
  color: #cfe5ff;  
}  

/* ===================== TOASTERS ===================== */  
.toast {  
  min-width: 250px;  
  margin-top: 0;  
  border-radius: var(--radius-md);  
  box-shadow: var(--shadow-xl);  
}  

.toast .toast-header {  
  color: #fff;  
  border-radius: var(--radius-md) var(--radius-md) 0 0;  
  font-weight: 700;  
}  

.toast .bg-success {  
  background: var(--gradient-success) !important;  
}  

.toast .bg-danger {  
  background: var(--gradient-error) !important;  
}  

/* ===================== VIEWER MODAL ===================== */  
#viewerModal {  
  z-index: 1060;  
}  

#viewerModal .modal-dialog {  
  max-width: 90vw;  
  width: 90vw;  
}  

#viewerModal .modal-content {  
  height: 90vh;  
  max-height: min(90vh, 100dvh);  
  display: flex;  
  flex-direction: column;  
}  

#viewerModal .viewer-body {  
  flex: 1;  
  display: flex;  
  min-height: 0;  
  padding: 0;  
  background: var(--bg-primary);  
}  

.viewer-frame {  
  border: 0;  
  width: 100%;  
  height: 100%;  
}  

.viewer-img {  
  width: 100%;  
  height: 100%;  
  object-fit: contain;  
  background: #000;  
}  

.viewer-fallback {  
  padding: var(--space-md);  
  color: var(--text-secondary);  
  font-size: 14px;  
}  

@media (max-width: 768px) {  
  #viewerModal .modal-dialog {  
    max-width: 100vw;  
    width: 100vw;  
    margin: 0;  
    height: 100dvh;  
  }  

  #viewerModal .modal-content {  
    height: 100dvh;  
    max-height: 100dvh;  
    border-radius: 0;  
  }  
}  

/* ===================== ANIMATIONS ===================== */  
@keyframes fadeInUp {  
  from {  
    opacity: 0;  
    transform: translateY(20px);  
  }  
  to {  
    opacity: 1;  
    transform: translateY(0);  
  }  
}  

.filter-card,  
.table-wrap {  
  animation: fadeInUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) backwards;  
}  

.filter-card {  
  animation-delay: 0.1s;  
}  

.table-wrap {  
  animation-delay: 0.2s;  
}  

/* ===================== SCROLL TO TOP ===================== */  
#scrollTop {  
  position: fixed;  
  bottom: 80px;  
  right: 30px;  
  width: 50px;  
  height: 50px;  
  border-radius: 50%;  
  background: var(--gradient-primary);  
  color: white;  
  border: none;  
  box-shadow: var(--shadow-xl);  
  cursor: pointer;  
  z-index: 1000;  
  opacity: 0;  
  transition: all 0.3s ease;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
}  

#scrollTop:hover {  
  transform: translateY(-4px);  
  box-shadow: var(--shadow-2xl);  
}  

/* ===================== FOOTER ===================== */  
footer {  
  position: relative !important;  
  z-index: 10 !important;  
  margin-top: auto !important;  
  width: 100% !important;  
}  

body.dark-mode footer {  
  background-color: transparent !important;  
}  

body.dark-mode footer .footer-content p {  
  color: var(--text-secondary) !important;  
}  

body.dark-mode footer .footer-content a {  
  color: var(--brand-primary) !important;  
}  

body.dark-mode footer .footer-content a:hover {  
  color: var(--brand-primary-light) !important;  
}  

@media (max-width: 768px) {  
  #scrollTop {  
    bottom: 90px !important;  
  }  
}  

/* ===================== MOBILE CARDS (substituem tabela) ===================== */
.results-cards {
  display: none;
}

@media (max-width: 992px) {
  /* Esconde tabela em mobile */
  .table-responsive {
    display: none !important;
  }
  
  /* Mostra cards em mobile */
  .results-cards {
    display: block;
  }
}

.os-card {
  background: var(--bg-elevated);
  border: 2px solid var(--border-primary);
  border-radius: var(--radius-lg);
  padding: var(--space-md);
  margin-bottom: var(--space-md);
  box-shadow: var(--shadow-md);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.os-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--gradient-primary);
}

.os-card.cancelado::before {
  background: var(--gradient-error);
}

.os-card.liquidado::before {
  background: var(--gradient-success);
}

.os-card.parcial::before {
  background: var(--gradient-warning);
}

.os-card:active {
  transform: scale(0.98);
  box-shadow: var(--shadow);
}

.os-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: var(--space-md);
  padding-bottom: var(--space-sm);
  border-bottom: 1px solid var(--border-primary);
}

.os-card-number {
  font-size: 24px;
  font-weight: 800;
  color: var(--brand-primary);
  line-height: 1;
  letter-spacing: -0.02em;
}

.os-card-badges {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
  align-items: flex-end;
}

.os-card-body {
  display: grid;
  gap: var(--space-sm);
}

.os-card-field {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.os-card-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-tertiary);
}

.os-card-value {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
  word-break: break-word;
}

.os-card-value.highlight {
  font-size: 18px;
  font-weight: 800;
  color: var(--brand-primary);
}

.os-card-value.success {
  color: var(--brand-success);
}

.os-card-value.warning {
  color: var(--brand-warning);
}

.os-card-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--space-sm);
}

.os-card-divider {
  height: 1px;
  background: var(--border-primary);
  margin: var(--space-sm) 0;
}

.os-card-actions {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-sm);
  margin-top: var(--space-md);
  padding-top: var(--space-md);
  border-top: 1px solid var(--border-primary);
}

.os-card-action-btn {
  width: 100%;
  height: 44px;
  border-radius: var(--radius-md);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  transition: all 0.3s ease;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
}

.os-card-action-btn:active {
  transform: scale(0.95);
}

.os-card-action-btn.btn-view {
  background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
  color: white;
}

.os-card-action-btn.btn-payment {
  background: var(--gradient-success);
  color: white;
}

.os-card-action-btn.btn-print {
  background: var(--gradient-primary);
  color: white;
}

.os-card-action-btn.btn-attach {
  background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
  color: white;
}

.os-card-action-btn.btn-attach.alert {
  background: var(--gradient-error);
}

/* Badge mini para cards */
.badge-mini {
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 6px;
  font-weight: 700;
  letter-spacing: 0.02em;
  display: inline-block;
  white-space: nowrap;
}

.badge-pago {
  background: var(--gradient-success);
  color: white;
}

.badge-isento {
  background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
  color: white;
}

.badge-ativo {
  background: var(--gradient-warning);
  color: white;
}

.badge-cancelado {
  background: var(--gradient-error);
  color: white;
}

.badge-pendente {
  background: var(--gradient-error);
  color: white;
}

.badge-parcial {
  background: var(--gradient-warning);
  color: white;
}

.badge-liquidado {
  background: var(--gradient-success);
  color: white;
}

/* Empty state */
.empty-state {
  text-align: center;
  padding: var(--space-2xl);
  color: var(--text-tertiary);
}

.empty-state i {
  font-size: 64px;
  margin-bottom: var(--space-md);
  opacity: 0.3;
}

.empty-state h5 {
  font-weight: 700;
  color: var(--text-secondary);
  margin-bottom: var(--space-sm);
}

/* Loading skeleton */
.skeleton-card {
  background: var(--bg-elevated);
  border: 2px solid var(--border-primary);
  border-radius: var(--radius-lg);
  padding: var(--space-md);
  margin-bottom: var(--space-md);
  animation: skeleton-pulse 1.5s ease-in-out infinite;
}

@keyframes skeleton-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.skeleton-line {
  height: 12px;
  background: var(--bg-tertiary);
  border-radius: 6px;
  margin-bottom: var(--space-sm);
}

.skeleton-line.short {
  width: 60%;
}

.skeleton-line.medium {
  width: 80%;
}
    </style>  
</head>  

<body>  
    <?php include(__DIR__ . '/../menu.php'); ?>  

    <div id="main" class="main-content">  
        <div class="container">  

            <!-- HERO / TÍTULO -->  
            <section class="page-hero">  
                <div class="title-row">  
                    <div class="title-icon">  
                        <i class="mdi mdi-clipboard-list-outline" aria-hidden="true"></i>  
                    </div>  
                    <div>  
                        <h1>Pesquisar Ordens de Serviço</h1>  
                        <div class="subtitle muted">Filtre por número, apresentante, CPF/CNPJ, data, valores e status.</div>  
                    </div>  
                </div>  
            </section>  

            <!-- Ações principais -->  
            <div class="d-flex flex-wrap justify-content-center justify-content-md-between align-items-center text-center mb-3 top-actions">  
                <div class="col-md-auto mb-2">  
                    <button id="add-button" type="button" class="btn btn-secondary text-white"  
                            onclick="window.location.href='tabela_de_emolumentos.php'">  
                        <i class="fa fa-table" aria-hidden="true"></i> Tabela de Emolumentos  
                    </button>  
                </div>  
                <div class="col-md-auto mb-2">  
                    <button id="add-button" type="button" class="btn btn-info2 text-white"  
                            onclick="window.location.href='criar_os.php'">  
                        <i class="fa fa-plus" aria-hidden="true"></i> Criar Ordem de Serviço  
                    </button>  
                </div>  
                <div class="col-md-auto mb-2">  
                    <button id="add-button" type="button" class="btn btn-success text-white"  
                            onclick="window.location.href='../caixa/index.php'">  
                        <i class="fa fa-university" aria-hidden="true"></i> Controle de Caixa  
                    </button>  
                </div>  
                <div class="col-md-auto mb-2">  
                    <a href="../liberar_os.php" class="btn btn-secondary">  
                        <i class="fa fa-undo" aria-hidden="true"></i> Desfazer Liquidações  
                    </a>  
                </div>  
                <div class="col-md-auto mb-2">  
                    <a href="modelos_orcamento.php" class="btn btn-primary">  
                        <i class="fa fa-folder-open"></i> Modelos O.S  
                    </a>  
                </div>  
            </div>  

            <!-- Formulário de filtro -->  
            <div class="filter-card">  
                <form id="pesquisarForm" method="GET">  
                    <div class="form-row align-items-end">  
                        <div class="form-group col-md-2">  
                            <label for="os_id">Nº OS:</label>  
                            <input type="number" class="form-control" id="os_id" name="os_id" min="1">  
                        </div>  
                        <div class="form-group col-md-5">  
                            <label for="cliente">Apresentante:</label>  
                            <input type="text" class="form-control" id="cliente" name="cliente">  
                        </div>  
                        <div class="form-group col-md-3">  
                            <label for="cpf_cliente">CPF/CNPJ:</label>  
                            <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente">  
                        </div>  
                        <div class="form-group col-md-2">  
                            <label for="total_os">Valor Total:</label>  
                            <input type="text" class="form-control" id="total_os" name="total_os">  
                        </div>  
                        <div class="form-group col-md-3">  
                            <label for="funcionario">Funcionário:</label>  
                            <select class="form-control" id="funcionario" name="funcionario">  
                                <option value="">Selecione o Funcionário</option>  
                                <?php  
                                $conn = getDatabaseConnection();  
                                $stmt = $conn->query("SELECT DISTINCT criado_por FROM ordens_de_servico");  
                                $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);  
                                foreach ($funcionarios as $funcionario) {  
                                    echo '<option value="' . $funcionario['criado_por'] . '">' . $funcionario['criado_por'] . '</option>';  
                                }  
                                ?>  
                            </select>  
                        </div>  
                        <div class="form-group col-md-3">  
                            <label for="situacao">Situação:</label>  
                            <select class="form-control" id="situacao" name="situacao">  
                                <option value="">Selecione a Situação</option>  
                                <option value="Ativo">Ativo</option>  
                                <option value="Cancelado">Cancelado</option>  
                            </select>  
                        </div>  

                        <div class="form-group col-md-3">  
                            <label for="data_inicial">Data Inicial:</label>  
                            <input type="date" class="form-control" id="data_inicial" name="data_inicial">  
                        </div>  
                        <div class="form-group col-md-3">  
                            <label for="data_final">Data Final:</label>  
                            <input type="date" class="form-control" id="data_final" name="data_final">  
                        </div>  
                        <div class="form-group col-md-4">  
                            <label for="descricao_os">Título da O.S:</label>  
                            <input type="text" class="form-control" id="descricao_os" name="descricao_os">  
                        </div>  
                        <div class="form-group col-md-6">  
                            <label for="observacoes">Observações:</label>  
                            <input type="text" class="form-control" id="observacoes" name="observacoes">  
                        </div>  

                        <div class="form-group col-md-2 d-flex align-items-end">  
                            <button type="submit" class="btn btn-primary w-100 text-white">  
                                <i class="fa fa-filter" aria-hidden="true"></i> Filtrar  
                            </button>  
                        </div>  
                    </div>  
                </form>  
            </div>  

                        <hr style="border-color: var(--border-primary); margin: var(--space-xl) 0;">

            <!-- Resultados -->
            <div class="table-wrap">
                <h5 class="mb-3">Resultados da Pesquisa</h5>
                
                <!-- TABELA DESKTOP -->
                <div class="table-responsive">
                <table id="tabelaResultados" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 7%;">Nº OS</th>
                            <th>Funcionário</th>
                            <th style="width: 11%;">Apresentante</th>
                            <th style="width: 11%;">CPF/CNPJ</th>
                            <th style="width: 13%;">Título da OS</th>
                            <th style="width: 10%;">Valor Total</th>
                            <th style="width: 10%;">Dep. Prévio</th>
                            <th style="width: 10%;">Liquidado</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th style="width: 5%;">Situação</th>
                            <th style="width: 7%!important;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = getDatabaseConnection();
                        $conditions = [];
                        $params = [];
                        $filtered = false;

                        if (!empty($_GET['os_id'])) {
                            $conditions[] = 'id = :os_id';
                            $params[':os_id'] = $_GET['os_id'];
                            $filtered = true;
                        }
                        if (!empty($_GET['cliente'])) {
                            $conditions[] = 'cliente LIKE :cliente';
                            $params[':cliente'] = '%' . $_GET['cliente'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['cpf_cliente'])) {
                            $conditions[] = 'cpf_cliente LIKE :cpf_cliente';
                            $params[':cpf_cliente'] = '%' . $_GET['cpf_cliente'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['total_os'])) {
                            $conditions[] = 'total_os = :total_os';
                            $params[':total_os'] = str_replace(',', '.', str_replace('.', '', $_GET['total_os']));
                            $filtered = true;
                        }
                        if (!empty($_GET['data_inicial']) && !empty($_GET['data_final'])) {
                            $conditions[] = 'DATE(data_criacao) BETWEEN :data_inicial AND :data_final';
                            $params[':data_inicial'] = $_GET['data_inicial'];
                            $params[':data_final'] = $_GET['data_final'];
                            $filtered = true;
                        } elseif (!empty($_GET['data_inicial'])) {
                            $conditions[] = 'DATE(data_criacao) >= :data_inicial';
                            $params[':data_inicial'] = $_GET['data_inicial'];
                            $filtered = true;
                        } elseif (!empty($_GET['data_final'])) {
                            $conditions[] = 'DATE(data_criacao) <= :data_final';
                            $params[':data_final'] = $_GET['data_final'];
                            $filtered = true;
                        }
                        if (!empty($_GET['funcionario'])) {
                            $conditions[] = 'criado_por LIKE :funcionario';
                            $params[':funcionario'] = $_GET['funcionario'];
                            $filtered = true;
                        }
                        if (!empty($_GET['situacao'])) {
                            $conditions[] = 'status = :situacao';
                            $params[':situacao'] = $_GET['situacao'];
                            $filtered = true;
                        }
                        if (!empty($_GET['descricao_os'])) {
                            $conditions[] = 'descricao_os LIKE :descricao_os';
                            $params[':descricao_os'] = '%' . $_GET['descricao_os'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['observacoes'])) {
                            $conditions[] = 'observacoes LIKE :observacoes';
                            $params[':observacoes'] = '%' . $_GET['observacoes'] . '%';
                            $filtered = true;
                        }
                        $sql = 'SELECT * FROM ordens_de_servico';
                        if ($conditions) {
                            $sql .= ' WHERE ' . implode(' AND ', $conditions);
                        }

                        if (!$filtered) {
                            $sql .= ' ORDER BY data_criacao DESC LIMIT 100';
                        }

                        $stmt = $conn->prepare($sql);
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        $stmt->execute();
                        $ordens = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Armazena ordens para renderizar cards também
                        $ordensData = [];

                        foreach ($ordens as $ordem) {
                            // Calcula o depósito prévio
                            $stmt = $conn->prepare('SELECT SUM(total_pagamento) as deposito_previo FROM pagamento_os WHERE ordem_de_servico_id = :os_id');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $deposito_previo = $stmt->fetchColumn() ?: 0;

                            // Total dos atos liquidados
                            $stmt = $conn->prepare('
                            SELECT 
                                COALESCE(SUM(total), 0) AS total_liquidado 
                            FROM atos_liquidados 
                            WHERE ordem_servico_id = :os_id
                            ');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $total_liquidado_1 = $stmt->fetchColumn() ?: 0;

                            // Total dos atos manuais liquidados
                            $stmt = $conn->prepare('
                            SELECT 
                                COALESCE(SUM(total), 0) AS total_liquidado 
                            FROM atos_manuais_liquidados 
                            WHERE ordem_servico_id = :os_id
                            ');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $total_liquidado_2 = $stmt->fetchColumn() ?: 0;

                            // Soma dos atos liquidados em ambas as tabelas
                            $total_liquidado = $total_liquidado_1 + $total_liquidado_2;

                            // Calcula o total devolvido
                            $stmt = $conn->prepare('SELECT SUM(total_devolucao) as total_devolvido FROM devolucao_os WHERE ordem_de_servico_id = :os_id');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $total_devolvido = $stmt->fetchColumn() ?: 0;

                            // Verificar status dos atos
                            $stmt = $conn->prepare('SELECT status FROM ordens_de_servico_itens WHERE ordem_servico_id = :os_id');
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $atos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            $statusOS = 'Pendente';
                            $statusClasses = [
                                'Pendente' => 'status-pendente',
                                'Parcial' => 'status-parcialmente',
                                'Liquidado' => 'status-liquidado',
                                'Cancelado' => 'situacao-cancelado'
                            ];

                            if ($ordem['status'] === 'Cancelado') {
                                $statusOS = 'Cancelado';
                            } else {
                                $allLiquidado = true;
                                $hasParcialmenteLiquidado = false;
                                $allPendente = true;

                                foreach ($atos as $ato) {
                                    if ($ato['status'] == 'liquidado') {
                                        $allPendente = false;
                                    } elseif ($ato['status'] == 'parcialmente liquidado') {
                                        $hasParcialmenteLiquidado = true;
                                        $allPendente = false;
                                        $allLiquidado = false;
                                    } elseif ($ato['status'] == null) {
                                        $allLiquidado = false;
                                    }
                                }

                                if ($allLiquidado && count($atos) > 0) {
                                    $statusOS = 'Liquidado';
                                } elseif ($hasParcialmenteLiquidado || !$allPendente) {
                                    $statusOS = 'Parcial';
                                }
                            }

                            // Verificar pagamentos relevantes e anexos
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM pagamento_os WHERE ordem_de_servico_id = :os_id AND forma_de_pagamento IN ('PIX', 'Transferência Bancária', 'Boleto', 'Cheque')");
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $temPagamentoRelevante = $stmt->fetchColumn() > 0;

                            /* NOVO: verificar isenção registrada */
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM pagamento_os WHERE ordem_de_servico_id = :os_id AND forma_de_pagamento = 'Isento de Pagamento'");
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $temIsencao = $stmt->fetchColumn() > 0;

                            $stmt = $conn->prepare("SELECT COUNT(*) FROM anexos_os WHERE ordem_servico_id = :os_id AND status = 'ativo'");
                            $stmt->bindParam(':os_id', $ordem['id']);
                            $stmt->execute();
                            $temAnexos = $stmt->fetchColumn() > 0;

                            $botaoAnexoClasses = "btn btn-secondary btn-sm action-btn";
                            $botaoAnexoIcone = '<i class="fa fa-paperclip" aria-hidden="true"></i>';
                            if ($temPagamentoRelevante && !$temAnexos) {
                                $botaoAnexoClasses .= " btn-danger";
                                $botaoAnexoIcone = '<i class="fa fa-exclamation-circle" aria-hidden="true"></i>';
                            }

                            // Saldo (considerando devoluções)
                            $saldo = ($deposito_previo - $total_devolvido) - $ordem['total_os'];
                            
                            // Armazena dados para cards
                            $ordensData[] = [
                                'id' => $ordem['id'],
                                'criado_por' => $ordem['criado_por'],
                                'cliente' => $ordem['cliente'],
                                'cpf_cliente' => $ordem['cpf_cliente'],
                                'descricao_os' => $ordem['descricao_os'],
                                'total_os' => $ordem['total_os'],
                                'deposito_previo' => $deposito_previo,
                                'total_liquidado' => $total_liquidado,
                                'total_devolvido' => $total_devolvido,
                                'data_criacao' => $ordem['data_criacao'],
                                'status' => $ordem['status'],
                                'statusOS' => $statusOS,
                                'statusClass' => $statusClasses[$statusOS],
                                'saldo' => $saldo,
                                'temPagamentoRelevante' => $temPagamentoRelevante,
                                'temAnexos' => $temAnexos,
                                'temIsencao' => $temIsencao, // NOVO
                                'botaoAnexoClasses' => $botaoAnexoClasses,
                                'botaoAnexoIcone' => $botaoAnexoIcone
                            ];
                            ?>
                            <tr>
                                <td><?php echo $ordem['id']; ?></td>
                                <td><?php echo $ordem['criado_por']; ?></td>
                                <td><?php echo $ordem['cliente']; ?></td>
                                <td><?php echo $ordem['cpf_cliente']; ?></td>
                                <td><?php echo $ordem['descricao_os']; ?></td>
                                <td><?php echo 'R$ ' . number_format($ordem['total_os'], 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($deposito_previo, 2, ',', '.'); ?></td>
                                <td><?php echo 'R$ ' . number_format($total_liquidado, 2, ',', '.'); ?></td>
                                <td data-order="<?php echo date('Y-m-d', strtotime($ordem['data_criacao'])); ?>"><?php echo date('d/m/Y', strtotime($ordem['data_criacao'])); ?></td>
                                <td><span style="font-size: 13px" class="status-label <?php echo $statusClasses[$statusOS]; ?>"><?php echo $statusOS; ?></span></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';

                                    if ($ordem['status'] === 'Cancelado') {
                                        $statusClass = 'situacao-cancelado';
                                        $statusText = 'Cancelada';
                                    } elseif (!empty($temIsencao) && $temIsencao) {
                                        /* NOVO: Situação Isento */
                                        $statusClass = 'situacao-isento';
                                        $statusText = 'Isento';
                                    } elseif ($deposito_previo > 0) {
                                        $statusClass = 'situacao-pago';
                                        $statusText = 'Pago';
                                    } elseif ($ordem['status'] === 'Ativo') {
                                        $statusClass = 'situacao-ativo';
                                        $statusText = 'Ativa';
                                    }
                                    ?>
                                    <span class="<?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>

                                <td style="width: 7%!important; zoom: 88%">
                                    <button type="button" class="btn btn-info2 btn-sm action-btn" title="Visualizar OS"
                                        onclick="location.href='visualizar_os.php?id=<?php echo $ordem['id']; ?>'">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </button>
                                    <?php if ($ordem['status'] !== 'Cancelado') : ?>
                                    <button class="btn btn-success btn-sm action-btn" title="Pagamentos e Devoluções"
                                        onclick="abrirPagamentoModal(<?php echo $ordem['id']; ?>, '<?php echo addslashes($ordem['cliente']); ?>', <?php echo $ordem['total_os']; ?>, <?php echo $deposito_previo; ?>, <?php echo $total_liquidado; ?>, <?php echo $total_devolvido; ?>, <?php echo $saldo; ?>, '<?php echo $statusOS; ?>')">
                                        <i class="fa fa-money" aria-hidden="true"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" title="Imprimir OS" class="btn btn-primary btn-sm action-btn" onclick="verificarTimbrado(<?php echo $ordem['id']; ?>)"><i class="fa fa-print" aria-hidden="true"></i></button>
                                    <button class="<?php echo $botaoAnexoClasses; ?>" title="Anexos" onclick="abrirAnexoModal(<?php echo $ordem['id']; ?>)">
                                        <?php echo $botaoAnexoIcone; ?>
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
                </div>

                <!-- CARDS MOBILE -->
                <div class="results-cards">
                    <?php if (empty($ordensData)): ?>
                        <div class="empty-state">
                            <i class="mdi mdi-clipboard-text-off-outline"></i>
                            <h5>Nenhuma OS encontrada</h5>
                            <p>Tente ajustar os filtros de pesquisa</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ordensData as $data): ?>
                            <?php
                            $cardClass = 'os-card';
                            if ($data['statusOS'] === 'Cancelado') $cardClass .= ' cancelado';
                            elseif ($data['statusOS'] === 'Liquidado') $cardClass .= ' liquidado';
                            elseif ($data['statusOS'] === 'Parcial') $cardClass .= ' parcial';

                            $situacaoBadge = '';
                            $situacaoText = '';
                            if ($data['status'] === 'Cancelado') {
                                $situacaoBadge = 'badge-cancelado';
                                $situacaoText = 'Cancelada';
                            } elseif (!empty($data['temIsencao']) && $data['temIsencao']) { /* NOVO */
                                $situacaoBadge = 'badge-isento';
                                $situacaoText = 'Isento';
                            } elseif ($data['deposito_previo'] > 0) {
                                $situacaoBadge = 'badge-pago';
                                $situacaoText = 'Pago';
                            } elseif ($data['status'] === 'Ativo') {
                                $situacaoBadge = 'badge-ativo';
                                $situacaoText = 'Ativa';
                            }

                            $statusBadge = '';
                            if ($data['statusOS'] === 'Pendente') $statusBadge = 'badge-pendente';
                            elseif ($data['statusOS'] === 'Parcial') $statusBadge = 'badge-parcial';
                            elseif ($data['statusOS'] === 'Liquidado') $statusBadge = 'badge-liquidado';
                            elseif ($data['statusOS'] === 'Cancelado') $statusBadge = 'badge-cancelado';
                            ?>
                            <div class="<?php echo $cardClass; ?>">
                                <div class="os-card-header">
                                    <div class="os-card-number">#<?php echo $data['id']; ?></div>
                                    <div class="os-card-badges">
                                        <span class="badge-mini <?php echo $situacaoBadge; ?>"><?php echo $situacaoText; ?></span>
                                        <span class="badge-mini <?php echo $statusBadge; ?>"><?php echo $data['statusOS']; ?></span>
                                    </div>
                                </div>

                                <div class="os-card-body">
                                    <div class="os-card-field">
                                        <div class="os-card-label">Apresentante</div>
                                        <div class="os-card-value highlight"><?php echo $data['cliente']; ?></div>
                                    </div>

                                    <div class="os-card-field">
                                        <div class="os-card-label">Título da OS</div>
                                        <div class="os-card-value"><?php echo $data['descricao_os']; ?></div>
                                    </div>

                                    <div class="os-card-grid">
                                        <div class="os-card-field">
                                            <div class="os-card-label">CPF/CNPJ</div>
                                            <div class="os-card-value"><?php echo $data['cpf_cliente']; ?></div>
                                        </div>
                                        <div class="os-card-field">
                                            <div class="os-card-label">Data</div>
                                            <div class="os-card-value"><?php echo date('d/m/Y', strtotime($data['data_criacao'])); ?></div>
                                        </div>
                                    </div>

                                    <div class="os-card-divider"></div>

                                    <div class="os-card-grid">
                                        <div class="os-card-field">
                                            <div class="os-card-label">Valor Total</div>
                                            <div class="os-card-value success">R$ <?php echo number_format($data['total_os'], 2, ',', '.'); ?></div>
                                        </div>
                                        <div class="os-card-field">
                                            <div class="os-card-label">Dep. Prévio</div>
                                            <div class="os-card-value">R$ <?php echo number_format($data['deposito_previo'], 2, ',', '.'); ?></div>
                                        </div>
                                    </div>

                                    <div class="os-card-grid">
                                        <div class="os-card-field">
                                            <div class="os-card-label">Liquidado</div>
                                            <div class="os-card-value">R$ <?php echo number_format($data['total_liquidado'], 2, ',', '.'); ?></div>
                                        </div>
                                        <div class="os-card-field">
                                            <div class="os-card-label">Funcionário</div>
                                            <div class="os-card-value"><?php echo $data['criado_por']; ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="os-card-actions">
                                    <button type="button" class="os-card-action-btn btn-view" title="Visualizar OS"
                                        onclick="location.href='visualizar_os.php?id=<?php echo $data['id']; ?>'">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                    <?php if ($data['status'] !== 'Cancelado'): ?>
                                    <button class="os-card-action-btn btn-payment" title="Pagamentos"
                                        onclick="abrirPagamentoModal(<?php echo $data['id']; ?>, '<?php echo addslashes($data['cliente']); ?>', <?php echo $data['total_os']; ?>, <?php echo $data['deposito_previo']; ?>, <?php echo $data['total_liquidado']; ?>, <?php echo $data['total_devolvido']; ?>, <?php echo $data['saldo']; ?>, '<?php echo $data['statusOS']; ?>')">
                                        <i class="fa fa-money"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="os-card-action-btn btn-payment" disabled style="opacity: 0.3;">
                                        <i class="fa fa-ban"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="os-card-action-btn btn-print" title="Imprimir"
                                        onclick="verificarTimbrado(<?php echo $data['id']; ?>)">
                                        <i class="fa fa-print"></i>
                                    </button>
                                    <button class="os-card-action-btn btn-attach <?php echo ($data['temPagamentoRelevante'] && !$data['temAnexos']) ? 'alert' : ''; ?>" title="Anexos"
                                        onclick="abrirAnexoModal(<?php echo $data['id']; ?>)">
                                        <i class="fa fa-<?php echo ($data['temPagamentoRelevante'] && !$data['temAnexos']) ? 'exclamation-circle' : 'paperclip'; ?>"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>  
        </div>  
    </div>  

    <!-- Modal de Pagamento -->  
    <div class="modal fade modal-modern" id="pagamentoModal" tabindex="-1" role="dialog" aria-labelledby="pagamentoModalLabel" aria-hidden="true">  
        <div class="modal-dialog" role="document">  
            <div class="modal-content">  
                <div class="modal-header">  
                    <h5 class="modal-title mb-0" id="pagamentoModalLabel">Efetuar Pagamento</h5>  
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Fechar">&times;</button>  
                </div>  
                <div class="modal-body">  
                    <div class="form-group">  
                        <label for="total_os_modal">Valor Total da OS</label>  
                        <input type="text" class="form-control" id="total_os_modal" readonly>  
                    </div>  
                    <div class="form-row">  
                        <div class="form-group col-md-6">  
                            <label for="forma_pagamento">Forma de Pagamento</label>  
                            <select class="form-control" id="forma_pagamento">  
                                <option value="">Selecione</option>  
                                <option value="Espécie">Espécie</option>  
                                <option value="Crédito">Crédito</option>  
                                <option value="Débito">Débito</option>  
                                <option value="PIX">PIX</option>  
                                <option value="Transferência Bancária">Transferência Bancária</option>  
                                <option value="Boleto">Boleto</option>  
                                <option value="Cheque">Cheque</option>  
                            </select>  
                        </div>  
                        <div class="form-group col-md-6">  
                            <label for="valor_pagamento">Valor do Pagamento</label>  
                            <input type="text" class="form-control" id="valor_pagamento">  
                        </div>  
                    </div>  
                    <div class="d-grid gap-2">
                      <button id="btnAdicionarPagamento" type="button" class="btn btn-primary w-100" onclick="adicionarPagamento()">Adicionar</button>
                      <button id="btnIsentoPagamento" type="button" class="btn btn-warning w-100" style="display:none" onclick="isentarPagamento()">
                        <i class="fa fa-check-circle" aria-hidden="true"></i> Isento de Pagamento
                      </button>
                    </div>

                    <hr style="border-color: var(--border-primary); margin: var(--space-md) 0;">  
                    <div class="form-row">  
                        <div class="form-group col-md-3">  
                            <label for="total_pagamento">Valor Pago</label>  
                            <input type="text" class="form-control" id="total_pagamento" readonly>  
                        </div>  
                        <div class="form-group col-md-3">  
                            <label for="valor_liquidado_modal">Valor Liquidado</label>  
                            <input type="text" class="form-control" id="valor_liquidado_modal" readonly>  
                        </div>  
                        <div class="form-group col-md-3">  
                            <label for="saldo_modal">Saldo</label>  
                            <input type="text" class="form-control" id="saldo_modal" readonly>  
                        </div>  
                        <div class="form-group col-md-3">  
                            <label for="valor_devolvido_modal">Valor Devolvido</label>  
                            <input type="text" class="form-control" id="valor_devolvido_modal" readonly>  
                        </div>  
                    </div>  

                    <!-- <button type="button" class="btn btn-warning" id="btnDevolver" onclick="abrirDevolucaoModal()">Devolver valores</button>   -->

                    <div id="pagamentosAdicionados" class="mt-3">  
                        <h5 style="font-weight: 700; font-size: 16px; color: var(--text-primary);">Pagamentos Adicionados</h5>  
                        <table class="table">  
                            <thead>  
                                <tr>  
                                    <th style="width: 50%;">Forma de Pagamento</th>  
                                    <th style="width: 40%;">Valor</th>  
                                    <th>Data</th>  
                                </tr>  
                            </thead>  
                            <tbody id="pagamentosTable">  
                                <!-- Pagamentos adicionados serão listados aqui -->  
                            </tbody>  
                        </table>  
                    </div>  
                </div>  
                <div class="modal-footer">  
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>  
                </div>  
            </div>  
        </div>  
    </div>  

    <!-- Modal de Devolução -->  
    <div class="modal fade modal-modern" id="devolucaoModal" tabindex="-1" role="dialog" aria-labelledby="devolucaoModalLabel" aria-hidden="true">  
        <div class="modal-dialog" role="document">  
            <div class="modal-content">  
                <div class="modal-header">  
                    <h5 class="modal-title mb-0" id="devolucaoModalLabel">Devolver Valores</h5>  
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Fechar">&times;</button>  
                </div>  
                <div class="modal-body">  
                    <div class="form-group">  
                        <label for="forma_devolucao">Forma de Devolução</label>  
                        <select class="form-control" id="forma_devolucao">  
                            <option value="">Selecione</option>  
                            <option value="Espécie">Espécie</option>  
                            <option value="PIX">PIX</option>  
                            <option value="Transferência Bancária">Transferência Bancária</option>  
                        </select>  
                    </div>  
                    <div class="form-group">  
                        <label for="valor_devolucao">Valor da Devolução</label>  
                        <input type="text" class="form-control" id="valor_devolucao">  
                    </div>  
                    <button type="button" class="btn btn-primary" onclick="salvarDevolucao()">Salvar</button>  
                </div>  
                <div class="modal-footer">  
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>  
                </div>  
            </div>  
        </div>  
    </div>  

        <!-- Modal de Anexos -->
    <div class="modal fade modal-modern" id="anexoModal" tabindex="-1" role="dialog" aria-labelledby="anexoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0" id="anexoModalLabel">Anexos</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Fechar">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formAnexos" enctype="multipart/form-data">
                        <!-- Input original (oculto) para compatibilidade com backend -->
                        <input type="file" id="novo_anexo" name="novo_anexo[]" multiple style="display:none">

                        <!-- Dropzone elegante -->
                        <div id="dropArea" class="dropzone" tabindex="0">
                            <div class="dz-icon"><i class="mdi mdi-cloud-upload-outline"></i></div>
                            <div class="dz-title">Arraste e solte os arquivos aqui</div>
                            <div class="dz-sub">ou clique para selecionar</div>
                        </div>
                        <div id="fileList" class="file-list" aria-live="polite"></div>

                        <button type="button" class="btn btn-success mt-3 w-100" onclick="salvarAnexo()">
                            <i class="fa fa-paperclip" aria-hidden="true"></i> Anexar
                        </button>
                    </form>

                    <hr style="border-color: var(--border-primary); margin: var(--space-md) 0;">
                    <div id="anexosAdicionados">
                        <h5 style="font-weight: 700; font-size: 16px; color: var(--text-primary);">Anexos Adicionados</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nome do Arquivo</th>
                                    <th style="width: 14%">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="anexosTable">
                                <!-- Anexos adicionados serão listados aqui -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Visualização do Anexo (90% / 100% mobile) -->
    <div class="modal fade modal-modern" id="viewerModal" tabindex="-1" role="dialog" aria-labelledby="viewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mb-0" id="viewerModalLabel">Visualização do Anexo</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Fechar">&times;</button>
                </div>
                <div class="modal-body viewer-body">
                    <div id="viewerContainer" style="flex:1; width:100%; height:100%; display:flex;"></div>
                </div>
                <div class="modal-footer">
                    <a id="viewerDownload" class="btn btn-secondary" href="#" download target="_blank">
                        <i class="fa fa-download" aria-hidden="true"></i> Baixar arquivo
                    </a>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Mensagem -->
    <div class="modal fade modal-modern" id="mensagemModal" tabindex="-1" role="dialog" aria-labelledby="mensagemModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header error" style="background:var(--gradient-error);">
                    <h5 class="modal-title mb-0" id="mensagemModalLabel">Erro</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Fechar">&times;</button>
                </div>
                <div class="modal-body">
                    <p id="mensagemTexto"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toasters -->
    <div aria-live="polite" aria-atomic="true" style="position: relative; z-index: 1050;">
        <div id="toastContainer" style="position: fixed; top: 80px; right: 20px; z-index: 1060;"></div>
    </div>

    <!-- Scroll to Top -->
    <button id="scrollTop" aria-label="Voltar ao topo">
        <i class="fa fa-chevron-up"></i>
    </button>

    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script>
        $(document).ready(function() {
            // ===================== TEMA =====================
            $.get('../load_mode.php', function(mode){
                $('body').removeClass('light-mode dark-mode').addClass(mode);
            });

            // ===================== SCROLL TO TOP =====================
            const $scrollTop = $('#scrollTop');
            $(window).on('scroll', function() {
                if ($(this).scrollTop() > 300) {
                    $scrollTop.css('opacity', '1');
                } else {
                    $scrollTop.css('opacity', '0');
                }
            });

            $scrollTop.on('click', function() {
                $('html, body').animate({ scrollTop: 0 }, 600);
            });

            // ===================== MÁSCARAS =====================
            $('#cpf_cliente').mask('000.000.000-00', { reverse: true }).on('blur', function() {
                var cpfCnpj = $(this).val().replace(/\D/g, '');
                if (cpfCnpj.length === 11) {
                    $(this).mask('000.000.000-00', { reverse: true });
                } else if (cpfCnpj.length === 14) {
                    $(this).mask('00.000.000/0000-00', { reverse: true });
                }
            });
            $('#total_os').mask('#.##0,00', { reverse: true });
            $('#valor_pagamento').mask('#.##0,00', { reverse: true });
            $('#valor_devolucao').mask('#.##0,00', { reverse: true });

            // ===================== DATATABLE =====================
            $('#tabelaResultados').DataTable({
                "language": { "url": "../style/Portuguese-Brasil.json" },
                "order": [[0, 'desc']],
                "pageLength": 10
            });

            // ===================== VALIDAÇÃO DE DATAS =====================
            var currentYear = new Date().getFullYear();
            function validateDate(input) {
                var selectedDate = new Date($(input).val());
                if (selectedDate.getFullYear() > currentYear) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Data inválida',
                        text: 'O ano não pode ser maior que o ano atual.',
                        confirmButtonText: 'Ok'
                    });
                    $(input).val('');
                }
            }
            $('#data_inicial, #data_final').on('change', function() {
                if ($(this).val()) { validateDate(this); }
            });
        });

        // ===================== FUNÇÕES DE PAGAMENTO =====================
        function verificarTimbrado(id) {
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false,
                success: function(data) {
                    var url = '';
                    if (data.timbrado === 'S') {
                        url = 'imprimir_os.php?id=' + id;
                    } else if (data.timbrado === 'N') {
                        url = 'imprimir-os.php?id=' + id;
                    }
                    window.open(url, '_blank');
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao carregar o arquivo de configuração.'
                    });
                }
            });
        }

        function abrirPagamentoModal(osId, cliente, totalOs, totalPagamentos, totalLiquidado, totalDevolvido, saldo, statusOS, pagamentosCount) {
            if (statusOS === 'Cancelado') {
                Swal.fire({ 
                    icon:'warning', 
                    title:'Operação não permitida', 
                    text:'Esta OS está cancelada.' 
                });
                return;
            }

            $('#total_os_modal').val('R$ ' + totalOs.toFixed(2).replace('.', ','));
            $('#valor_liquidado_modal').val('R$ ' + totalLiquidado.toFixed(2).replace('.', ','));
            $('#valor_devolvido_modal').val('R$ ' + totalDevolvido.toFixed(2).replace('.', ','));
            $('#valor_pagamento').val('');
            $('#forma_pagamento').val('');
            $('#pagamentosTable').empty();
            $('#total_pagamento').val('R$ ' + totalPagamentos.toFixed(2).replace('.', ','));
            $('#saldo_modal').val('R$ ' + saldo.toFixed(2).replace('.', ','));

            // RESET VISUAL (evita ficar oculto da OS anterior)
            $('#forma_pagamento').prop('disabled', false).closest('.form-group').show();
            $('#valor_pagamento').prop('disabled', false).closest('.form-group').show();
            $('#btnIsentoPagamento').hide();
            $('#btnAdicionarPagamento').show();

            // Só mostra "Isento" se total OS == 0 E não houver nenhum pagamento (valor inicial; depois o AJAX reafirma)
            if (Number(totalOs) === 0 && Number(pagamentosCount) === 0) {
                $('#forma_pagamento').prop('disabled', true);
                $('#valor_pagamento').prop('disabled', true);
                $('#btnAdicionarPagamento').hide();
                $('#btnIsentoPagamento').show();
            }

            if (saldo <= 0) { 
                $('#btnDevolver').hide(); 
            } else { 
                $('#btnDevolver').show(); 
            }

            $('#pagamentoModal').modal('show');

            window.currentOsId = osId;
            window.currentClient = cliente;
            window.statusOS = statusOS;
            window.currentTotalOs = Number(totalOs);
            window.hasIsencao = false; // será definido em atualizarTabelaPagamentos()

            atualizarTabelaPagamentos();
        }

        function adicionarPagamento() {
            // BLOQUEIO: se houver isenção registrada, não permitir adicionar novos pagamentos
            if (window.hasIsencao) {
                Swal.fire({
                    icon: 'warning',
                    title: 'O.S isenta',
                    text: 'Já existe um registro "Isento de Pagamento" nesta O.S. Não é possível adicionar outros pagamentos.'
                });
                return;
            }

            // Bloquear inclusão se Total OS = 0
            var totalAtual = parseFloat($('#total_os_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.'));
            if (!isNaN(totalAtual) && totalAtual <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'O.S isenta',
                    text: 'Para O.S com valor zero, utilize o botão "Isento de Pagamento".'
                });
                return;
            }

            var formaPagamento = $('#forma_pagamento').val();
            var valorPagamento = parseFloat($('#valor_pagamento').val().replace(/\./g, '').replace(',', '.'));

            if (formaPagamento === "") {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Erro', 
                    text: 'Por favor, selecione uma forma de pagamento.' 
                });
                return;
            }

            if (isNaN(valorPagamento) || valorPagamento <= 0) {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Erro', 
                    text: 'Por favor, insira um valor válido para o pagamento.' 
                });
                return;
            }

            // Validação para Espécie (múltiplos de R$ 0,05)
            if (formaPagamento === 'Espécie') {
                var cents = Math.round((valorPagamento + Number.EPSILON) * 100);
                if (cents % 5 !== 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Valor inválido para espécie',
                        text: 'Para pagamentos em espécie, o valor deve terminar em 0 ou 5 centavos (ex.: 2,05 • 10,50 • 10,55).'
                    });
                    return;
                }
            }

            Swal.fire({
                title: 'Confirmar Pagamento',
                text: `Deseja realmente adicionar o pagamento de R$ ${valorPagamento.toFixed(2).replace('.', ',')} na forma de ${formaPagamento}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim',
                cancelButtonText: 'Não'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'salvar_pagamento.php',
                        type: 'POST',
                        data: {
                            os_id: window.currentOsId,
                            cliente: window.currentClient,
                            total_os: parseFloat($('#total_os_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.')),
                            funcionario: '<?php echo $_SESSION['username']; ?>',
                            forma_pagamento: formaPagamento,
                            valor_pagamento: valorPagamento
                        },
                        success: function(response) {
                            try {
                                response = JSON.parse(response);
                                if (response.success) {
                                    atualizarTabelaPagamentos();
                                    $('#valor_pagamento').val('');
                                    Swal.fire({ 
                                        icon: 'success', 
                                        title: 'Sucesso', 
                                        text: 'Pagamento adicionado com sucesso!' 
                                    });
                                } else {
                                    Swal.fire({ 
                                        icon: 'error', 
                                        title: 'Erro', 
                                        text: 'Erro ao adicionar pagamento.' 
                                    });
                                }
                            } catch (e) {
                                Swal.fire({ 
                                    icon: 'error', 
                                    title: 'Erro', 
                                    text: 'Erro ao processar resposta do servidor.' 
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({ 
                                icon: 'error', 
                                title: 'Erro', 
                                text: 'Erro ao adicionar pagamento.' 
                            });
                        }
                    });
                }
            });
        }


        function atualizarTabelaPagamentos() {
            var pagamentosTable = $('#pagamentosTable');
            pagamentosTable.empty();

            $.ajax({
                url: 'obter_pagamentos.php',
                type: 'POST',
                data: { os_id: window.currentOsId },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        var total = 0;
                        var canDelete = (window.statusOS === 'Pendente');

                        response.pagamentos.forEach(function(pagamento) {
                            total += parseFloat(pagamento.total_pagamento);
                            pagamentosTable.append(`
                                <tr>
                                    <td>${pagamento.forma_de_pagamento}</td>
                                    <td>R$ ${parseFloat(pagamento.total_pagamento).toFixed(2).replace('.', ',')}</td>
                                    <td>${(v=>{if(!v)return'-';const m=String(v).match(/^(\d{4})-(\d{2})-(\d{2})/);return m?`${m[3]}/${m[2]}/${m[1]}`:new Date(v).toLocaleDateString('pt-BR');})(pagamento.data_pagamento)}</td>
                                </tr>
                            `);
                        });

                        $('#total_pagamento').val('R$ ' + total.toFixed(2).replace('.', ','));
                        var saldo = total - parseFloat($('#total_os_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.')) - parseFloat($('#valor_devolvido_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.'));
                        $('#saldo_modal').val('R$ ' + saldo.toFixed(2).replace('.', ','));

                        if (saldo <= 0) { 
                            $('#btnDevolver').hide(); 
                        } else { 
                            $('#btnDevolver').show(); 
                        }

                        // --- REGRAS DE VISUALIZAÇÃO ---
                        var temPagamentos = Array.isArray(response.pagamentos) && response.pagamentos.length > 0;

                        // Existe pagamento "Isento de Pagamento"?
                        var existeIsencao = (response.pagamentos || []).some(function(p){
                            return String(p.forma_de_pagamento).toLowerCase() === 'isento de pagamento';
                        });
                        window.hasIsencao = !!existeIsencao;

                        if (existeIsencao) {
                            // Se há isenção registrada, ocultar opção de adicionar pagamento
                            $('#btnIsentoPagamento').hide();
                            $('#btnAdicionarPagamento').hide();
                            $('#forma_pagamento').prop('disabled', true).closest('.form-group').hide();
                            $('#valor_pagamento').prop('disabled', true).closest('.form-group').hide();
                        } else {
                            // Sem isenção: regra para mostrar/ocultar botão "Isento" quando total OS = 0 e sem pagamentos
                            if (window.currentTotalOs === 0 && !temPagamentos) {
                                $('#forma_pagamento').prop('disabled', true).closest('.form-group').show();
                                $('#valor_pagamento').prop('disabled', true).closest('.form-group').show();
                                $('#btnAdicionarPagamento').hide();
                                $('#btnIsentoPagamento').show();
                            } else {
                                $('#forma_pagamento').prop('disabled', false).closest('.form-group').show();
                                $('#valor_pagamento').prop('disabled', false).closest('.form-group').show();
                                $('#btnIsentoPagamento').hide();
                                $('#btnAdicionarPagamento').show();
                            }
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao atualizar tabela de pagamentos.', 'error');
                }
            });
        }

        function removerPagamento(pagamentoId) {
            $.ajax({
                url: 'remover_pagamento.php',
                type: 'POST',
                data: { pagamento_id: pagamentoId },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            atualizarTabelaPagamentos();
                            exibirMensagem('Pagamento removido com sucesso!', 'success');
                        } else {
                            exibirMensagem('Erro ao remover pagamento.', 'error');
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao remover pagamento.', 'error');
                }
            });
        }

        function exibirMensagem(mensagem, tipo) {
            var toastContainer = $('#toastContainer');
            var toastId = 'toast-' + new Date().getTime();
            var toastHTML = `
                <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3000">
                    <div class="toast-header ${tipo === 'success' ? 'bg-success text-white' : 'bg-danger text-white'}">
                        <strong class="mr-auto">${tipo === 'success' ? 'Sucesso' : 'Erro'}</strong>
                        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="toast-body">
                        ${mensagem}
                    </div>
                </div>
            `;
            toastContainer.append(toastHTML);
            $('#' + toastId).toast('show').on('hidden.bs.toast', function () {
                $(this).remove();
            });
        }

        function abrirDevolucaoModal() {
            $('#devolucaoModal').modal('show');
        }

        function salvarDevolucao() {
            var formaDevolucao = $('#forma_devolucao').val();
            var valorDevolucao = parseFloat($('#valor_devolucao').val().replace(/\./g, '').replace(',', '.'));
            var saldoAtual = parseFloat($('#saldo_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.'));

            if (formaDevolucao === "") {
                exibirMensagem('Por favor, selecione uma forma de devolução.', 'error');
                return;
            }
            if (isNaN(valorDevolucao) || valorDevolucao <= 0 || valorDevolucao > saldoAtual) {
                exibirMensagem('Insira um valor válido que não seja maior que o saldo disponível.', 'error');
                return;
            }

            $.ajax({
                url: 'salvar_devolucao.php',
                type: 'POST',
                data: {
                    os_id: window.currentOsId,
                    cliente: window.currentClient,
                    total_os: parseFloat($('#total_os_modal').val().replace('R$ ', '').replace(/\./g, '').replace(',', '.')),
                    total_devolucao: valorDevolucao,
                    forma_devolucao: formaDevolucao,
                    funcionario: '<?php echo $_SESSION['username']; ?>'
                },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            $('#devolucaoModal').modal('hide');
                            exibirMensagem('Devolução salva com sucesso!', 'success');
                            atualizarTabelaPagamentos();
                        } else {
                            exibirMensagem('Erro ao salvar devolução.', 'error');
                        }
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao salvar devolução.', 'error');
                }
            });
        }

        // ===================== FUNÇÕES DE ANEXOS =====================
        function abrirAnexoModal(osId) {
            $('#anexoModal').modal('show');
            window.currentOsId = osId;
            atualizarTabelaAnexos();
        }

        // DROPZONE: arrastar/soltar + clique
        (function initDropzone(){
            const dropArea = document.getElementById('dropArea');
            const fileInput = document.getElementById('novo_anexo');
            const fileList = document.getElementById('fileList');

            function humanSize(bytes){
                if(bytes === 0) return '0 B';
                const k = 1024, sizes = ['B','KB','MB','GB','TB'];
                const i = Math.floor(Math.log(bytes)/Math.log(k));
                return (bytes/Math.pow(k,i)).toFixed(1)+' '+sizes[i];
            }

            function renderList(files){
                fileList.innerHTML = '';
                Array.from(files).forEach(f=>{
                    const row = document.createElement('div');
                    row.className = 'file-item';
                    row.innerHTML = `<span class="file-name"><i class="mdi mdi-file-outline"></i> ${f.name}</span><span class="file-size">${humanSize(f.size)}</span>`;
                    fileList.appendChild(row);
                });
            }

            function setFiles(files){
                const dt = new DataTransfer();
                Array.from(files).forEach(f => dt.items.add(f));
                fileInput.files = dt.files;
                renderList(fileInput.files);
            }

            dropArea.addEventListener('click', ()=> fileInput.click());
            dropArea.addEventListener('keydown', (e)=>{ 
                if(e.key === 'Enter' || e.key === ' ') { 
                    e.preventDefault(); 
                    fileInput.click(); 
                }
            });
            dropArea.addEventListener('dragover', (e)=>{ 
                e.preventDefault(); 
                dropArea.classList.add('dragover'); 
            });
            dropArea.addEventListener('dragleave', ()=> dropArea.classList.remove('dragover'));
            dropArea.addEventListener('drop', (e)=>{
                e.preventDefault();
                dropArea.classList.remove('dragover');
                if(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length){
                    setFiles(e.dataTransfer.files);
                }
            });
            fileInput.addEventListener('change', ()=> renderList(fileInput.files));
        })();

        function salvarAnexo() {
            var formData = new FormData($('#formAnexos')[0]);
            formData.append('os_id', window.currentOsId);
            formData.append('funcionario', '<?php echo $_SESSION['username']; ?>');

            $.ajax({
                url: 'salvar_anexo.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            $('#novo_anexo').val('');
                            $('#fileList').empty();
                            Swal.fire({ 
                                icon: 'success', 
                                title: 'Sucesso', 
                                text: 'Anexo salvo com sucesso!' 
                            });
                            atualizarTabelaAnexos();
                        } else {
                            Swal.fire({ 
                                icon: 'error', 
                                title: 'Erro', 
                                text: 'Erro ao salvar anexo.' 
                            });
                        }
                    } catch (e) {
                        Swal.fire({ 
                            icon: 'error', 
                            title: 'Erro', 
                            text: 'Erro ao processar resposta do servidor.' 
                        });
                    }
                },
                error: function() {
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Erro', 
                        text: 'Erro ao salvar anexo.' 
                    });
                }
            });
        }

        function atualizarTabelaAnexos() {
            var anexosTable = $('#anexosTable');
            anexosTable.empty();

            $.ajax({
                url: 'obter_anexos.php',
                type: 'POST',
                data: { os_id: window.currentOsId },
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        response.anexos.forEach(function(anexo) {
                            var caminhoCompleto = 'anexos/' + window.currentOsId + '/' + anexo.caminho_anexo;
                            anexosTable.append(`
                                <tr>
                                    <td>${anexo.caminho_anexo}</td>
                                    <td>
                                        <button type="button" class="btn btn-info btn-sm" onclick="visualizarAnexo('${caminhoCompleto}')">
                                            <i class="fa fa-eye" aria-hidden="true"></i>
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });
                    } catch (e) {
                        exibirMensagem('Erro ao processar resposta do servidor.', 'error');
                    }
                },
                error: function() {
                    exibirMensagem('Erro ao atualizar tabela de anexos.', 'error');
                }
            });
        }

        function absoluteUrl(path){
            try{
                return new URL(path, window.location.origin + window.location.pathname).href;
            }catch(e){
                return path;
            }
        }

        function isentarPagamento() {
            Swal.fire({
                title: 'Confirmar Isenção',
                text: 'Registrar esta O.S como "Isento de Pagamento"?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim',
                cancelButtonText: 'Não'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'salvar_pagamento.php',
                        type: 'POST',
                        data: {
                            os_id: window.currentOsId,
                            cliente: window.currentClient,
                            total_os: 0,
                            funcionario: '<?php echo $_SESSION['username']; ?>',
                            forma_pagamento: 'Isento de Pagamento',
                            valor_pagamento: 0
                        },
                        success: function(response) {
                            try {
                                response = JSON.parse(response);
                                if (response.success) {
                                    atualizarTabelaPagamentos();
                                    Swal.fire({ 
                                        icon: 'success', 
                                        title: 'Sucesso', 
                                        text: 'Isenção registrada com sucesso!' 
                                    });
                                } else {
                                    Swal.fire({ 
                                        icon: 'error', 
                                        title: 'Erro', 
                                        text: response.message || 'Erro ao registrar isenção.' 
                                    });
                                }
                            } catch (e) {
                                Swal.fire({ 
                                    icon: 'error', 
                                    title: 'Erro', 
                                    text: 'Erro ao processar resposta do servidor.' 
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({ 
                                icon: 'error', 
                                title: 'Erro', 
                                text: 'Erro ao registrar isenção.' 
                            });
                        }
                    });
                }
            });
        }

        function visualizarAnexo(caminho) {
            const abs = absoluteUrl(caminho);
            const name = caminho.split('/').pop();
            const ext = (name.split('.').pop() || '').toLowerCase();
            const container = document.getElementById('viewerContainer');
            const downloadBtn = document.getElementById('viewerDownload');
            const title = document.getElementById('viewerModalLabel');

            title.textContent = 'Visualizando: ' + name;
            downloadBtn.href = abs;
            container.innerHTML = '';

            if (ext === 'pdf') {
                const viewerUrl = absoluteUrl('../provimentos/pdfjs/web/viewer.html') + '?file=' + encodeURIComponent(abs);
                const iframe = document.createElement('iframe');
                iframe.className = 'viewer-frame';
                iframe.src = viewerUrl;
                iframe.setAttribute('title','Visualizador PDF');
                container.appendChild(iframe);
            } else if (['png','jpg','jpeg','gif','webp','bmp','svg'].includes(ext)) {
                const img = document.createElement('img');
                img.className = 'viewer-img';
                img.src = abs;
                img.alt = name;
                container.appendChild(img);
            } else {
                const iframe = document.createElement('iframe');
                iframe.className = 'viewer-frame';
                iframe.src = abs;
                iframe.setAttribute('title','Visualização do arquivo');
                container.appendChild(iframe);

                const fallback = document.createElement('div');
                fallback.className = 'viewer-fallback';
                fallback.innerHTML = '<small>Se a visualização não carregar, utilize o botão "Baixar arquivo".</small>';
                container.appendChild(fallback);
            }

            $('#viewerModal').modal('show');
        }

        $('#viewerModal').on('shown.bs.modal', function () {
            const backdrops = $('.modal-backdrop');
            backdrops.last().css('z-index', 1055);
            $(this).css('z-index', 1060);
        });

        $('#viewerModal').on('hidden.bs.modal', function () {
            if ($('#anexoModal').hasClass('show')) {
                $('body').addClass('modal-open');
            }
            $('#viewerContainer').empty();
        });

        function removerAnexo(anexoId) {
            Swal.fire({
                title: 'Confirmar Remoção',
                text: 'Deseja realmente remover este anexo?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Não, cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'remover_anexo.php',
                        type: 'POST',
                        data: { anexo_id: anexoId },
                        success: function(response) {
                            try {
                                response = JSON.parse(response);
                                if (response.success) {
                                    Swal.fire({ 
                                        icon: 'success', 
                                        title: 'Sucesso', 
                                        text: 'Anexo removido com sucesso!' 
                                    });
                                    atualizarTabelaAnexos();
                                } else {
                                    Swal.fire({ 
                                        icon: 'error', 
                                        title: 'Erro', 
                                        text: 'Erro ao remover anexo.' 
                                    });
                                }
                            } catch (e) {
                                Swal.fire({ 
                                    icon: 'error', 
                                    title: 'Erro', 
                                    text: 'Erro ao processar resposta do servidor.' 
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({ 
                                icon: 'error', 
                                title: 'Erro', 
                                text: 'Erro ao remover anexo.' 
                            });
                        }
                    });
                }
            });
        }

        document.addEventListener('change', function(e){
            if(e.target && e.target.id === 'novo_anexo'){
                const lbl = document.querySelector('label[for="novo_anexo"]');
                if(!lbl) return;
                const files = e.target.files || [];
                if (files.length === 1) { 
                    lbl.textContent = files[0].name; 
                } else if (files.length > 1) { 
                    lbl.textContent = files.length + ' arquivos selecionados'; 
                } else { 
                    lbl.textContent = 'Selecione os arquivos para anexar'; 
                }
            }
        });

        $('#anexoModal').on('hidden.bs.modal', function () {
            location.reload();
        });
    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>