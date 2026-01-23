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
    <title>Atlas - Indexador XLSX</title>  
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../../style/css/style.css">  
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">  
    <link rel="stylesheet" href="../../style/css/dataTables.bootstrap4.min.css">  
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">  
    <!-- Dropzone CSS -->  
    <link href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" rel="stylesheet" type="text/css">  
    <script src="../../script/jquery-3.6.0.min.js"></script>  
    <script src="../../script/jquery.dataTables.min.js"></script>  
    <script src="../../script/dataTables.bootstrap4.min.js"></script>  
    <script src="../../script/bootstrap.bundle.min.js"></script>  
    <!-- Dropzone JS -->  
    <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>  
    <!-- SweetAlert2 -->  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
    <?php include(__DIR__ . '/style.php');?>  

    <style>
/* ===================== CSS VARIABLES ===================== */  
:root {  
  /* Typography */  
  --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;  
  --font-mono: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace;  
  
  /* Spacing Scale */  
  --space-xs: 4px;  
  --space-sm: 8px;  
  --space-md: 16px;  
  --space-lg: 24px;  
  --space-xl: 32px;  
  --space-2xl: 48px;  
  --space-3xl: 64px;  
  
  /* Border Radius */  
  --radius-xs: 6px;  
  --radius-sm: 10px;  
  --radius-md: 14px;  
  --radius-lg: 20px;  
  --radius-xl: 28px;  
  --radius-2xl: 36px;  
  --radius-full: 9999px;  
  
  /* Shadows */  
  --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.04);  
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06), 0 1px 4px rgba(0, 0, 0, 0.04);  
  --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.06);  
  --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.12), 0 4px 16px rgba(0, 0, 0, 0.08);  
  --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.16), 0 8px 24px rgba(0, 0, 0, 0.12);  
  --shadow-2xl: 0 24px 64px rgba(0, 0, 0, 0.20), 0 12px 32px rgba(0, 0, 0, 0.16);  
  
  /* Light Theme */  
  --bg-primary: #fafbfc;  
  --bg-secondary: #f4f6f8;  
  --bg-tertiary: #ffffff;  
  --text-primary: #0d1117;  
  --text-secondary: #424a53;  
  --text-tertiary: #656d76;  
  --text-quaternary: #8b949e;  
  --border-primary: rgba(13, 17, 23, 0.08);  
  --border-secondary: rgba(13, 17, 23, 0.12);  
  --surface: rgba(255, 255, 255, 0.92);  
  --surface-hover: rgba(248, 250, 252, 0.96);  
  
  /* Brand Colors */  
  --brand-primary: #6366f1;  
  --brand-primary-light: #818cf8;  
  --brand-primary-dark: #4f46e5;  
  --brand-secondary: #8b5cf6;  
  --brand-accent: #06b6d4;  
  --brand-success: #10b981;  
  --brand-warning: #f59e0b;  
  --brand-error: #ef4444;  
  
  /* Status Colors */  
  --status-pendente: #fbbf24;  
  --status-pendente-text: #d97706;  
  --status-pendente-bg: rgba(251, 191, 36, 0.15);  
  --status-pendente-border: rgba(251, 191, 36, 0.4);  
  
  --status-em-andamento: #06b6d4;  
  --status-em-andamento-text: #0891b2;  
  --status-em-andamento-bg: rgba(6, 182, 212, 0.15);  
  --status-em-andamento-border: rgba(6, 182, 212, 0.4);  
  
  --status-emitida: #10b981;  
  --status-emitida-text: #059669;  
  --status-emitida-bg: rgba(16, 185, 129, 0.15);  
  --status-emitida-border: rgba(16, 185, 129, 0.4);  
  
  --status-entregue: #8b5cf6;  
  --status-entregue-text: #7c3aed;  
  --status-entregue-bg: rgba(139, 92, 246, 0.15);  
  --status-entregue-border: rgba(139, 92, 246, 0.4);  
  
  --status-cancelada: #ef4444;  
  --status-cancelada-text: #dc2626;  
  --status-cancelada-bg: rgba(239, 68, 68, 0.15);  
  --status-cancelada-border: rgba(239, 68, 68, 0.4);  
  
  /* Gradients */  
  --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);  
  --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);  
  --gradient-success: linear-gradient(135deg, #4ade80 0%, #22d3ee 100%);  
  --gradient-warning: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);  
  --gradient-surface: linear-gradient(145deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 251, 252, 0.98) 100%);  
  --gradient-mesh:   
    radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.08) 0px, transparent 50%),  
    radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.08) 0px, transparent 50%),  
    radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.06) 0px, transparent 50%),  
    radial-gradient(at 0% 100%, rgba(244, 114, 182, 0.06) 0px, transparent 50%);  
}  

/* ===================== DARK MODE VARIABLES ===================== */  
.dark-mode {  
  --bg-primary: #0d1117;  
  --bg-secondary: #161b22;  
  --bg-tertiary: #21262d;  
  --text-primary: #f0f6fc;  
  --text-secondary: #c9d1d9;  
  --text-tertiary: #8b949e;  
  --text-quaternary: #6e7681;  
  --border-primary: rgba(240, 246, 252, 0.10);  
  --border-secondary: rgba(240, 246, 252, 0.14);  
  --surface: rgba(33, 38, 45, 0.92);  
  --surface-hover: rgba(48, 54, 61, 0.96);  
  
  --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.6);  
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.7), 0 1px 4px rgba(0, 0, 0, 0.6);  
  --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.8), 0 2px 8px rgba(0, 0, 0, 0.7);  
  --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.85), 0 4px 16px rgba(0, 0, 0, 0.8);  
  --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.9), 0 8px 24px rgba(0, 0, 0, 0.85);  
  --shadow-2xl: 0 24px 64px rgba(0, 0, 0, 0.95), 0 12px 32px rgba(0, 0, 0, 0.9);  
  
  /* Status colors for dark mode */  
  --status-pendente-text: #fbbf24;  
  --status-pendente-bg: rgba(251, 191, 36, 0.2);  
  --status-pendente-border: rgba(251, 191, 36, 0.5);  
  
  --status-em-andamento-text: #22d3ee;  
  --status-em-andamento-bg: rgba(6, 182, 212, 0.2);  
  --status-em-andamento-border: rgba(6, 182, 212, 0.5);  
  
  --status-emitida-text: #34d399;  
  --status-emitida-bg: rgba(16, 185, 129, 0.2);  
  --status-emitida-border: rgba(16, 185, 129, 0.5);  
  
  --status-entregue-text: #a78bfa;  
  --status-entregue-bg: rgba(139, 92, 246, 0.2);  
  --status-entregue-border: rgba(139, 92, 246, 0.5);  
  
  --status-cancelada-text: #f87171;  
  --status-cancelada-bg: rgba(239, 68, 68, 0.2);  
  --status-cancelada-border: rgba(239, 68, 68, 0.5);  
  
  --gradient-surface: linear-gradient(145deg, rgba(33, 38, 45, 0.95) 0%, rgba(22, 27, 34, 0.98) 100%);  
  --gradient-mesh:   
    radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),  
    radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),  
    radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.12) 0px, transparent 50%),  
    radial-gradient(at 0% 100%, rgba(244, 114, 182, 0.12) 0px, transparent 50%);  
}  

/* ===================== BASE OVERRIDES ===================== */  
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
  min-height: auto; /* ✅ ALTERADO */
  flex: 1; /* ✅ ADICIONADO */
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
  padding-bottom: var(--space-xl); /* ✅ ADICIONADO */
      padding: 1rem!important;
    margin-top: -15px!important;
}

/* ===================== HERO SECTION ===================== */  
.page-hero {  
  position: relative;  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  padding: var(--space-2xl);  
  margin-bottom: var(--space-xl);  
  box-shadow: var(--shadow-xl);  
  overflow: hidden;  
  animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);  
}  

.page-hero::before {  
  content: '';  
  position: absolute;  
  top: 0;  
  left: 0;  
  right: 0;  
  height: 5px;  
  background: var(--gradient-primary);  
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;  
}  

@keyframes fadeInUp {  
  from {  
    opacity: 0;  
    transform: translateY(30px);  
  }  
  to {  
    opacity: 1;  
    transform: translateY(0);  
  }  
}  

.title-row {  
  display: flex;  
  align-items: center;  
  gap: var(--space-lg);  
  margin-bottom: var(--space-lg);  
}  

.title-icon {  
  position: relative;  
  width: 64px;  
  height: 64px;  
  flex-shrink: 0;  
  border-radius: var(--radius-lg);  
  background: var(--gradient-primary);  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  box-shadow: var(--shadow-xl);  
  overflow: hidden;  
  transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);  
}  

.title-icon::before {  
  content: '';  
  position: absolute;  
  inset: -50%;  
  background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);  
  animation: iconShine 3s ease-in-out infinite;  
}  

@keyframes iconShine {  
  0%, 100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }  
  50% { transform: translateX(100%) translateY(100%) rotate(45deg); }  
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
  font-size: 32px;  
  font-weight: 800;  
  letter-spacing: -0.03em;  
  color: var(--text-primary) !important;  
  margin: 0;  
  line-height: 1.2;  
}  

/* ===================== STATS CARDS ===================== */  
.stats-grid {  
  display: grid;  
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));  
  gap: var(--space-md);  
  margin-bottom: var(--space-xl);  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.2s backwards;  
}  

@keyframes fadeIn {  
  from { opacity: 0; }  
  to { opacity: 1; }  
}  

.stat-card {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-lg);  
  padding: var(--space-lg);  
  box-shadow: var(--shadow-md);  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  cursor: pointer;  
}  

.stat-card:hover {  
  transform: translateY(-4px);  
  box-shadow: var(--shadow-xl);  
  border-color: var(--border-secondary);  
}  

.stat-card-header {  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  margin-bottom: var(--space-sm);  
}  

.stat-card-label {  
  font-size: 13px;  
  font-weight: 600;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  color: var(--text-tertiary);  
}  

.stat-card-icon {  
  width: 36px;  
  height: 36px;  
  border-radius: var(--radius-sm);  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  font-size: 18px;  
  color: white;  
}  

.stat-card-value {  
  font-size: 36px;  
  font-weight: 800;  
  line-height: 1;  
  color: var(--text-primary);  
}  

/* Status-specific colors */  
.stat-card[data-status="total"] .stat-card-icon {  
  background: linear-gradient(135deg, #6366f1, #8b5cf6);  
}  

.stat-card[data-status="pendente"] .stat-card-icon {  
  background: linear-gradient(135deg, #fbbf24, #f59e0b);  
}  

.stat-card[data-status="em_andamento"] .stat-card-icon {  
  background: linear-gradient(135deg, #06b6d4, #0891b2);  
}  

.stat-card[data-status="emitida"] .stat-card-icon {  
  background: linear-gradient(135deg, #10b981, #059669);  
}  

.stat-card[data-status="entregue"] .stat-card-icon {  
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);  
}  

.stat-card[data-status="cancelada"] .stat-card-icon {  
  background: linear-gradient(135deg, #ef4444, #dc2626);  
}  

/* ===================== FILTER SECTION ===================== */  
.filter-card {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  padding: var(--space-xl);  
  margin-bottom: var(--space-xl);  
  box-shadow: var(--shadow-lg);  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.3s backwards;  
}  

.filter-header {  
  display: flex;  
  align-items: center;  
  gap: var(--space-md);  
  margin-bottom: var(--space-lg);  
  padding-bottom: var(--space-md);  
  border-bottom: 2px solid var(--border-primary);  
}  

.filter-header i {  
  font-size: 24px;  
  color: var(--brand-primary);  
}  

.filter-header h3 {  
  font-size: 18px;  
  font-weight: 700;  
  margin: 0;  
  color: var(--text-primary);  
}  

.form-label {  
  font-size: 13px;  
  font-weight: 700;  
  color: var(--text-secondary) !important;  
  margin-bottom: var(--space-sm);  
  letter-spacing: -0.01em;  
  text-transform: uppercase;  
}  

.form-control,  
.custom-select {  
  background: var(--bg-tertiary) !important;  
  border: 2px solid var(--border-primary) !important;  
  border-radius: var(--radius-md) !important;  
  padding: 6px 16px;  
  font-size: 15px;  
  color: var(--text-primary) !important;  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
}  

.form-control::placeholder {  
  color: var(--text-quaternary) !important;  
  opacity: 1;  
}  

.form-control:focus,  
.custom-select:focus {  
  outline: none !important;  
  border-color: var(--brand-primary) !important;  
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15) !important;  
  background: var(--surface) !important;  
  color: var(--text-primary) !important;  
}  

.form-control:hover,  
.custom-select:hover {  
  border-color: var(--border-secondary) !important;  
}  

/* Custom select arrow for dark mode */  
/* .custom-select {  
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23656d76' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;  
}   */

/* .dark-mode .custom-select {  
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%238b949e' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;  
}   */

.filter-actions {  
  display: flex;  
  gap: var(--space-md);  
  align-items: center;  
  flex-wrap: wrap;  
}  

.btn {  
  position: relative;  
  display: inline-flex;  
  align-items: center;  
  justify-content: center;  
  gap: var(--space-sm);  
  padding: 12px 24px;  
  font-family: var(--font-primary);  
  font-size: 15px;  
  font-weight: 700;  
  line-height: 1;  
  border: none;  
  border-radius: var(--radius-md) !important;  
  cursor: pointer;  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  white-space: nowrap;  
}  

.btn i {  
  font-size: 16px;  
  transition: transform 0.3s ease;  
}  

.btn-primary {  
  background: var(--gradient-primary) !important;  
  color: white !important;  
  box-shadow: var(--shadow-md), inset 0 1px 0 rgba(255, 255, 255, 0.1);  
}  

.btn-primary:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-xl), inset 0 1px 0 rgba(255, 255, 255, 0.1);  
  color: white !important;  
}  

.btn-outline-primary {  
  background: transparent !important;  
  color: var(--brand-primary) !important;  
  border: 2px solid var(--brand-primary) !important;  
}  

.btn-outline-primary:hover {  
  background: var(--brand-primary) !important;  
  color: white !important;  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
}  

.btn-outline-secondary {  
  background: transparent !important;  
  color: var(--text-tertiary) !important;  
  border: 2px solid var(--border-secondary) !important;  
}  

.btn-outline-secondary:hover {  
  background: var(--surface) !important;  
  color: var(--text-primary) !important;  
  border-color: var(--text-tertiary) !important;  
}  

.btn:active {  
  transform: translateY(0) !important;  
}  

.btn:disabled {  
  opacity: 0.6;  
  cursor: not-allowed;  
}  

/* ===================== TABLE CONTROLS ===================== */  
.table-controls {  
  display: flex;  
  justify-content: space-between;  
  align-items: center;  
  margin-bottom: var(--space-lg);  
  flex-wrap: wrap;  
  gap: var(--space-md);  
}  

.page-length {  
  display: flex;  
  align-items: center;  
  gap: var(--space-sm);  
  font-size: 14px;  
  color: var(--text-tertiary);  
}  

.page-length .custom-select {  
  width: 80px;  
  padding: 8px 12px;  
}  

.search-box {  
  display: flex;  
  align-items: center;  
  gap: var(--space-sm);  
}  

.search-box label {  
  font-size: 14px;  
  font-weight: 600;  
  color: var(--text-tertiary);  
  margin: 0;  
}  

.search-box .form-control {  
  min-width: 280px;  
  padding: 10px 16px;  
}  

/* ===================== TABLE STYLES ===================== */  
.desktop-table {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  padding: var(--space-lg);  
  box-shadow: var(--shadow-lg);  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.4s backwards;  
}  

.table-responsive {  
  border-radius: var(--radius-lg);  
  overflow: hidden;  
}  

.table {  
  margin-bottom: 0;  
  color: var(--text-primary) !important;  
}  

.table thead th {  
  background: var(--bg-secondary) !important;  
  color: var(--text-primary) !important;  
  font-weight: 700;  
  font-size: 13px;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  padding: 16px 12px;  
  border-bottom: 2px solid var(--border-primary) !important;  
  white-space: nowrap;  
}  

.table tbody td {  
  padding: 16px 12px;  
  vertical-align: middle;  
  border-bottom: 1px solid var(--border-primary) !important;  
  font-size: 14px;  
  color: var(--text-secondary) !important;  
  background: transparent !important;  
}  

.table tbody tr {  
  transition: all 0.2s ease;  
  background: transparent !important;  
}  

.table-hover tbody tr:hover {  
  background: var(--surface-hover) !important;  
  transform: scale(1.01);  
}  

.table-striped tbody tr:nth-of-type(odd) {  
  background: transparent !important;  
}  

.nowrap {  
  white-space: nowrap;  
}  

/* ===================== STATUS BADGES ===================== */  
.badge-status {  
  display: inline-flex;  
  align-items: center;  
  padding: 6px 12px;  
  font-size: 12px;  
  font-weight: 700;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  border-radius: var(--radius-md);  
  white-space: nowrap;  
}  

.status-pendente {  
  background: var(--status-pendente-bg);  
  color: var(--status-pendente-text);  
  border: 1.5px solid var(--status-pendente-border);  
}  

.status-em_andamento {  
  background: var(--status-em-andamento-bg);  
  color: var(--status-em-andamento-text);  
  border: 1.5px solid var(--status-em-andamento-border);  
}  

.status-emitida {  
  background: var(--status-emitida-bg);  
  color: var(--status-emitida-text);  
  border: 1.5px solid var(--status-emitida-border);  
}  

.status-entregue {  
  background: var(--status-entregue-bg);  
  color: var(--status-entregue-text);  
  border: 1.5px solid var(--status-entregue-border);  
}  

.status-cancelada {  
  background: var(--status-cancelada-bg);  
  color: var(--status-cancelada-text);  
  border: 1.5px solid var(--status-cancelada-border);  
}  

/* ===================== API BADGES ===================== */  
.badge-api-ok {  
  background: var(--status-emitida-bg);  
  color: var(--status-emitida-text);  
  border: 1.5px solid var(--status-emitida-border);  
  padding: 4px 10px;  
  font-size: 11px;  
  font-weight: 700;  
  border-radius: var(--radius-sm);  
}  

/* ===================== ISENTO BADGE ===================== */
.badge-isento {
  background: rgba(99, 102, 241, 0.12); /* roxinho leve */
  color: var(--brand-primary);
  border: 1.5px solid rgba(99, 102, 241, 0.35);
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 700;
  border-radius: var(--radius-sm);
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.badge-isento::before {
  content: '●';
  font-size: 10px;
  line-height: 1;
}

.badge-api-warn {  
  background: var(--status-pendente-bg);  
  color: var(--status-pendente-text);  
  border: 1.5px solid var(--status-pendente-border);  
  padding: 4px 10px;  
  font-size: 11px;  
  font-weight: 700;  
  border-radius: var(--radius-sm);  
}  

.badge-api-err {  
  background: var(--status-cancelada-bg);  
  color: var(--status-cancelada-text);  
  border: 1.5px solid var(--status-cancelada-border);  
  padding: 4px 10px;  
  font-size: 11px;  
  font-weight: 700;  
  border-radius: var(--radius-sm);  
}  

/* ===================== ACTION BUTTONS ===================== */  
.actions {  
  display: inline-flex;  
  flex-wrap: wrap;  
  gap: var(--space-sm);  
}  

.btn-sm {  
  padding: 8px 14px !important;  
  font-size: 13px !important;  
  font-weight: 600 !important;  
}  

.btn-info2 {  
  background: linear-gradient(135deg, #06b6d4, #0891b2) !important;  
  color: white !important;  
  box-shadow: var(--shadow-sm);  
  border: none !important;  
}  

.btn-info2:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
  color: white !important;  
}  

.btn-secondary {  
  background: linear-gradient(135deg, #64748b, #475569) !important;  
  color: white !important;  
  box-shadow: var(--shadow-sm);  
  border: none !important;  
}  

.btn-secondary:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
  color: white !important;  
}  

.btn-warning {  
  background: linear-gradient(135deg, #fbbf24, #f59e0b) !important;  
  color: #1a1a1a !important;  
  box-shadow: var(--shadow-sm);  
  border: none !important;  
}  

.btn-warning:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
  color: #1a1a1a !important;  
}  

.small-muted {  
  font-size: 12px;  
  color: var(--text-quaternary);  
  margin-top: var(--space-xs);  
  line-height: 1.4;  
}  

.text-wrap-anywhere {  
  word-wrap: anywhere;  
  word-break: break-word;  
}  

/* ===================== MOBILE CARDS ===================== */  
.mobile-cards {  
  display: none;  
}  

.card-pedido {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  margin-bottom: var(--space-lg);  
  box-shadow: var(--shadow-lg);  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  overflow: hidden;  
}  

.card-pedido:hover {  
  transform: translateY(-4px);  
  box-shadow: var(--shadow-2xl);  
}  

.card-pedido .card-body {  
  padding: var(--space-lg);  
}  

.card-pedido h5 {  
  font-size: 18px;  
  font-weight: 700;  
  color: var(--text-primary);  
  margin-bottom: var(--space-sm);  
}  

.card-pedido p {  
  font-size: 14px;  
  color: var(--text-secondary);  
  margin-bottom: var(--space-sm);  
  line-height: 1.6;  
}  

.card-pedido strong {  
  color: var(--text-primary);  
}  

.card-pedido .d-grid {  
  display: grid;  
  gap: var(--space-sm);  
}  

/* ===================== DATATABLE CUSTOM STYLING ===================== */  
.dataTables_wrapper {  
  padding: 0;  
  color: var(--text-primary) !important;  
}  

.dataTables_info,  
.dataTables_paginate {  
  padding: var(--space-md) 0 0;  
  font-size: 14px;  
  color: var(--text-tertiary) !important;  
}  

.dataTables_paginate .pagination {  
  margin: 0;  
}  

.page-item .page-link {  
  background: var(--bg-tertiary) !important;  
  border: 1px solid var(--border-primary) !important;  
  color: var(--text-primary) !important;  
  padding: 8px 14px;  
  margin: 0 2px;  
  border-radius: var(--radius-sm) !important;  
  transition: all 0.2s ease;  
}  

.page-item .page-link:hover {  
  background: var(--surface-hover) !important;  
  border-color: var(--brand-primary) !important;  
  color: var(--brand-primary) !important;  
}  

.page-item.active .page-link {  
  background: var(--gradient-primary) !important;  
  border-color: var(--brand-primary) !important;  
  color: white !important;  
  box-shadow: var(--shadow-sm);  
}  

.page-item.disabled .page-link {  
  background: var(--bg-secondary) !important;  
  border-color: var(--border-primary) !important;  
  color: var(--text-quaternary) !important;  
  opacity: 0.5;  
}  

/* DataTables sorting icons */  
.table thead th.sorting,  
.table thead th.sorting_asc,  
.table thead th.sorting_desc {  
  cursor: pointer;  
  position: relative;  
  padding-right: 26px !important;  
}  

.table thead th.sorting::after,  
.table thead th.sorting_asc::after,  
.table thead th.sorting_desc::after {  
  position: absolute;  
  right: 8px;  
  top: 50%;  
  transform: translateY(-50%);  
  opacity: 0.5;  
}  

/* ===================== RESPONSIVE ===================== */  
@media (min-width: 992px) {  
  .desktop-table { display: block; }  
  .mobile-cards { display: none; }  
}  

@media (max-width: 991.98px) {  
  .desktop-table { display: none !important; }  
  .mobile-cards { display: block; }  
  
  .page-hero {  
    padding: var(--space-xl);  
  }  
  
  .page-hero h1 {  
    font-size: 24px;  
  }  
  
  .title-icon {  
    width: 56px;  
    height: 56px;  
  }  
  
  .title-icon i {  
    font-size: 28px;  
  }  
  
  .stats-grid {  
    grid-template-columns: repeat(2, 1fr);  
  }  
  
  .filter-card {  
    padding: var(--space-lg);  
  }  
  
  .filter-actions {  
    width: 100%;  
  }  
  
  .filter-actions .btn {  
    flex: 1;  
  }  
  
  .table-controls {  
    flex-direction: column;  
    align-items: stretch;  
  }  
  
  .page-length,  
  .search-box {  
    width: 100%;  
    justify-content: space-between;  
  }  
  
  .search-box .form-control {  
    min-width: 0;  
    flex: 1;  
  }  
}  

@media (max-width: 640px) {  
  .stats-grid {  
    grid-template-columns: 1fr;  
  }  
  
  .stat-card-value {  
    font-size: 28px;  
  }  
}  

/* ===================== SCROLL TO TOP BUTTON ===================== */  
#scrollTop {  
  position: fixed;  
  bottom: 30px;  
  right: 30px;  
  width: 50px;  
  height: 50px;  
  border-radius: 50%;  
  background: var(--gradient-primary) !important;  
  color: white !important;  
  border: none;  
  box-shadow: var(--shadow-xl);  
  cursor: pointer;  
  opacity: 0;  
  transition: all 0.3s ease;  
  z-index: 1000;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
}  

#scrollTop:hover {  
  transform: translateY(-4px);  
  box-shadow: var(--shadow-2xl);  
}  

/* ===================== LOADING OVERLAY ===================== */  
.loading-overlay {  
  position: fixed;  
  inset: 0;  
  background: rgba(0, 0, 0, 0.6);  
  backdrop-filter: blur(8px);  
  z-index: 9999;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
}  

.dark-mode .loading-overlay {  
  background: rgba(0, 0, 0, 0.8);  
}  

.spinner {  
  width: 50px;  
  height: 50px;  
  border: 4px solid rgba(255, 255, 255, 0.3);  
  border-top-color: #fff;  
  border-radius: 50%;  
  animation: spin 0.8s linear infinite;  
}  

@keyframes spin {  
  to { transform: rotate(360deg); }  
}  

/* ===================== UTILITIES ===================== */  
.d-grid {  
  display: grid;  
}  

.gap-2 {  
  gap: var(--space-sm);  
}  

.d-flex {  
  display: flex !important;  
}  

.justify-content-between {  
  justify-content: space-between !important;  
}  

.align-items-center {  
  align-items: center !important;  
}  

.mb-0 { margin-bottom: 0 !important; }  
.mb-1 { margin-bottom: 0.25rem !important; }  
.mb-2 { margin-bottom: 0.5rem !important; }  
.mb-3 { margin-bottom: 1rem !important; }  
.mt-1 { margin-top: 0.25rem !important; }  
.mt-3 { margin-top: 1rem !important; }  

/* ===================== REDUCED MOTION ===================== */  
@media (prefers-reduced-motion: reduce) {  
  *,  
  *::before,  
  *::after {  
    animation-duration: 0.01ms !important;  
    animation-iteration-count: 1 !important;  
    transition-duration: 0.01ms !important;  
  }  
}  

/* ===================== DARK MODE SPECIFIC ADJUSTMENTS ===================== */  
.dark-mode .page-hero,  
.dark-mode .filter-card,  
.dark-mode .stat-card,  
.dark-mode .desktop-table,  
.dark-mode .card-pedido {  
  background: var(--gradient-surface);  
}  

.dark-mode .table tbody td strong,  
.dark-mode .card-pedido strong {  
  color: var(--text-primary) !important;  
}  

/* Bootstrap overrides for dark mode */  
.dark-mode .table-striped tbody tr:nth-of-type(odd),  
.dark-mode .table-hover tbody tr:hover {  
  background-color: transparent !important;  
}  

.dark-mode .table-hover tbody tr:hover {  
  background: var(--surface-hover) !important;  
}  

/* ===================== FOOTER COMPATIBILITY ===================== */
footer {
  position: relative !important;
  z-index: 10 !important;
  margin-top: auto !important;
  width: 100% !important;
}

/* Garante que o footer seja visível no dark mode */
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

/* Ajuste para o scroll button não sobrepor o footer */
#scrollTop {
  bottom: 80px !important; /* ✅ Sobe um pouco para não cobrir o footer */
}

@media (max-width: 768px) {
  #scrollTop {
    bottom: 90px !important;
  }
}

/* ===================== DATEPICKER STYLES ===================== */
.ui-datepicker {
  background: var(--bg-tertiary) !important;
  border: 2px solid var(--border-primary) !important;
  border-radius: var(--radius-lg) !important;
  box-shadow: var(--shadow-xl) !important;
  padding: var(--space-md) !important;
  font-family: var(--font-primary) !important;
}

.ui-datepicker-header {
  background: var(--bg-secondary) !important;
  border: none !important;
  border-radius: var(--radius-md) !important;
  color: var(--text-primary) !important;
  padding: var(--space-sm) !important;
  margin-bottom: var(--space-sm) !important;
}

.ui-datepicker-title {
  color: var(--text-primary) !important;
  font-weight: 700 !important;
}

.ui-datepicker-prev,
.ui-datepicker-next {
  cursor: pointer !important;
  color: var(--brand-primary) !important;
}

.ui-datepicker-prev:hover,
.ui-datepicker-next:hover {
  background: var(--surface-hover) !important;
  border-radius: var(--radius-sm) !important;
}

.ui-datepicker-calendar {
  color: var(--text-primary) !important;
}

.ui-datepicker-calendar th {
  color: var(--text-secondary) !important;
  font-weight: 600 !important;
  font-size: 12px !important;
  text-transform: uppercase !important;
  padding: var(--space-xs) !important;
}

.ui-datepicker-calendar td {
  padding: 2px !important;
}

.ui-datepicker-calendar td a,
.ui-datepicker-calendar td span {
  display: block !important;
  padding: var(--space-sm) !important;
  text-align: center !important;
  border-radius: var(--radius-sm) !important;
  color: var(--text-primary) !important;
  text-decoration: none !important;
  transition: all 0.2s ease !important;
}

.ui-datepicker-calendar td a:hover {
  background: var(--surface-hover) !important;
  color: var(--brand-primary) !important;
}

.ui-datepicker-calendar td .ui-state-active {
  background: var(--gradient-primary) !important;
  color: white !important;
  font-weight: 700 !important;
}

.ui-datepicker-calendar td .ui-state-disabled {
  opacity: 0.3 !important;
  cursor: not-allowed !important;
}

.ui-datepicker-today a {
  border: 2px solid var(--brand-primary) !important;
  font-weight: 600 !important;
}

.ui-datepicker select.ui-datepicker-month,
.ui-datepicker select.ui-datepicker-year {
  background: var(--bg-tertiary) !important;
  border: 1px solid var(--border-primary) !important;
  color: var(--text-primary) !important;
  border-radius: var(--radius-sm) !important;
  padding: 4px !important;
  margin: 0 4px !important;
}

/* Input group adjustments */
.input-group {
  display: flex !important;
}

.input-group .form-control {
  border-top-right-radius: 0 !important;
  border-bottom-right-radius: 0 !important;
}

.input-group-append {
  margin-left: -2px;
}

.input-group-text {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px 16px;
  border-top-right-radius: var(--radius-md) !important;
  border-bottom-right-radius: var(--radius-md) !important;
  transition: all 0.3s ease;
}

.input-group-text:hover {
  background: var(--surface-hover) !important;
}

.input-group-text i {
  font-size: 16px;
}

/* Dark mode adjustments */
.dark-mode .ui-datepicker {
  background: var(--bg-tertiary) !important;
  border-color: var(--border-primary) !important;
}

.dark-mode .ui-widget-header {
  background: var(--bg-secondary) !important;
  border: none !important;
}

  

        /* === CARD DE UPLOAD / RESTO DA PÁGINA === */
        .card-upload {
            border: none;
            border-radius: 1.2rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            background: var(--gradient-surface);
        }

        body.dark-mode .card-upload {
            box-shadow: 0 22px 45px rgba(0, 0, 0, 0.7);
        }

        .card-upload .card-header {
            background: transparent;
            border-bottom: 0;
            padding-bottom: 0;
        }

        .card-upload .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--color-text-main);
        }

        .card-upload .card-subtitle {
            font-size: 0.85rem;
            color: var(--color-text-muted);
        }

        .tipo-planilha-group label {
            cursor: pointer;
        }

        .dropzone {
            border-radius: 0.9rem;
            border: 2px dashed var(--dropzone-border);
            background: var(--dropzone-bg);
            min-height: 180px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dropzone .icon i {
            font-size: 2.5rem;
            color: var(--dropzone-border);
            margin-bottom: 0.5rem;
        }

        .dropzone .text {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--color-text-main);
        }

        .dropzone .file-info {
            font-size: 0.8rem;
            color: var(--color-text-muted);
        }

        #processBtn {
            margin-top: 0.5rem;
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        /* Ajustes extras para garantir contraste no dark */
        body.dark-mode .card-upload .custom-control-label,
        body.dark-mode .card-upload small {
            color: var(--color-text-muted);
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050;
        }

        body.dark-mode .loading-overlay {
            background: rgba(0, 0, 0, 0.8);
        }

        .progress-container {
            background: var(--gradient-surface);
            padding: 1.75rem 1.75rem 1.5rem;
            border-radius: 1.2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.35);
            text-align: center;
            border: 1px solid var(--color-border-soft);
        }

        .progress-title {
            font-weight: 600;
            font-size: 1rem;
            color: var(--color-text-main);
        }

        .progress-bar-container {
            background: rgba(148, 163, 184, 0.35);
            border-radius: 999px;
            overflow: hidden;
            height: 11px;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #4a90e2, #5cb85c);
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 0.85rem;
            color: var(--color-text-muted);
        }

        @media (max-width: 575.98px) {
            .page-hero {
                padding: 1.25rem 1.25rem 1.1rem;
            }

            .page-hero h1 {
                font-size: 1.35rem;
            }

            .title-icon {
                width: 52px;
                height: 52px;
            }

            .title-icon i {
                font-size: 26px;
            }

            .card-upload {
                border-radius: 1rem;
            }
        }
    </style>
</head>  
<body>  
<?php include(__DIR__ . '/../../menu.php'); ?>  
<main id="main" class="main-content">
  <div class="container">  
    
    <!-- Hero Section -->  
    <section class="page-hero">  
      <div class="title-row">  
        <div class="title-icon">  
                    <i class="fa fa-cloud-upload" aria-hidden="true"></i>
                </div>
                <div>
                    <h1>Indexador XLSX — Nascimento</h1>
                    <p class="hero-subtitle">
                        Importe planilhas <strong>simples</strong> ou <strong>completas</strong> para alimentar o indexador de nascimentos de forma rápida e segura.
                    </p>
                </div>
            </div>
        </section>

        <!-- CARD PRINCIPAL EM LARGURA TOTAL -->  
        <div class="card card-upload mb-3">  
            <div class="card-header border-0">  
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">  
                    <div>  
                        <h5 class="card-title mb-1">Configurações de importação</h5>  
                        <p class="card-subtitle mb-0">Escolha o tipo de planilha e envie seus arquivos XLSX.</p>  
                    </div>  
                    <div class="mt-3 mt-md-0">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center">
                            <i class="fa fa-arrow-left mr-2"></i>
                            <span>Voltar ao Indexador</span>
                        </a>
                    </div>
                </div>  
            </div>  
            <div class="card-body pt-2">  

                <!-- Seleção do tipo de planilha -->  
                <div class="mb-3 tipo-planilha-group">  
                    <label class="font-weight-bold d-block mb-2">Tipo de planilha</label>  
                    <div class="custom-control custom-radio custom-control-inline mb-1">  
                        <input type="radio" id="tipoSimples" name="tipo_planilha" class="custom-control-input" value="simples">  
                        <label class="custom-control-label" for="tipoSimples">Simples</label>  
                    </div>  
                    <div class="custom-control custom-radio custom-control-inline mb-1">  
                        <input type="radio" id="tipoCompleta" name="tipo_planilha" class="custom-control-input" value="completa" checked>  
                        <label class="custom-control-label" for="tipoCompleta">Completa</label>  
                    </div>  
                    <div class="mt-1">  
                        <small id="tipoPlanilhaDescricao" class="text-muted">  
                            Simples: termo, nome_registrado, livro, folha.  
                        </small>  
                    </div>  
                </div>  

                <!-- Área de upload -->  
                <div class="mb-3">  
                    <div id="dropzoneForm" class="dropzone">  
                        <div class="dz-message needsclick text-center">  
                            <div class="icon"><i class="fas fa-cloud-upload-alt"></i></div>  
                            <div class="text">Arraste e solte arquivos XLSX aqui<br><span class="file-info">ou clique para selecionar</span></div>  
                        </div>  
                    </div>  
                </div>  

                <!-- Botão de processamento -->  
                <button type="button" id="processBtn" class="btn btn-primary btn-lg btn-block" disabled>  
                    <i class="fas fa-upload mr-2"></i>  
                    Processar arquivos selecionados  
                </button>  

            </div>  
        </div>  

    </div>  

    <!-- Overlay de progresso -->  
    <div class="loading-overlay">  
        <div class="progress-container">  
            <div class="progress-title">Processando arquivos...</div>  
            <div class="progress-bar-container">  
                <div class="progress-bar"></div>  
            </div>  
            <div class="progress-text">0%</div>  
        </div>  
    </div>  

</main>  

<script>  
// Inicialização do Dropzone  
Dropzone.autoDiscover = false;  

$(document).ready(function() {  
    // Array para armazenar arquivos  
    let uploadedFiles = [];  

    // Controle do tipo de planilha selecionado  
    let tipoPlanilhaSelecionado = $('input[name="tipo_planilha"]:checked').val() || 'simples';  

    function atualizarDescricaoTipo() {  
        if (tipoPlanilhaSelecionado === 'simples') {  
            $('#tipoPlanilhaDescricao').text('Simples: termo, nome_registrado, livro, folha.');  
        } else {  
            $('#tipoPlanilhaDescricao').text('Completa: termo, livro, folha, data_registro, data_nascimento, nome_registrado, nome_pai, nome_mae, matricula, ibge_naturalidade, sexo.');  
        }  
    }  

    $('input[name="tipo_planilha"]').on('change', function() {  
        tipoPlanilhaSelecionado = $(this).val();  
        atualizarDescricaoTipo();  
    });  

    atualizarDescricaoTipo();  

    let myDropzone = new Dropzone("#dropzoneForm", {  
        url: "#", // Impedimos o upload automático  
        autoProcessQueue: false,  
        addRemoveLinks: true,  
        acceptedFiles: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel",  
        maxFilesize: 10, // MB  
        parallelUploads: 10,  
        dictDefaultMessage: "Arraste e solte arquivos aqui para upload",  
        dictFallbackMessage: "Seu navegador não suporta arrastar e soltar arquivos para upload.",  
        dictFileTooBig: "Arquivo muito grande ({{filesize}}MB). Tamanho máximo: {{maxFilesize}}MB.",  
        dictInvalidFileType: "Este tipo de arquivo não é permitido. Apenas arquivos XLSX são aceitos.",  
        dictRemoveFile: "Remover",  
        dictMaxFilesExceeded: "Não é possível carregar mais arquivos.",  
        init: function() {  
            const dropzone = this;  
            
            // Ativa/desativa o botão de processamento dependendo se há arquivos  
            this.on("addedfile", function(file) {  
                uploadedFiles.push(file);  
                $("#processBtn").prop("disabled", false);  
            });  
            
            this.on("removedfile", function(file) {  
                uploadedFiles = uploadedFiles.filter(f => f !== file);  
                if (uploadedFiles.length === 0) {  
                    $("#processBtn").prop("disabled", true);  
                }  
            });  
        }  
    });  
      
    // Quando o botão de processar for clicado  
    $("#processBtn").on("click", function() {  
        if (uploadedFiles.length === 0) {  
            Swal.fire({  
                icon: 'warning',  
                title: 'Nenhum arquivo',  
                text: 'Por favor, adicione pelo menos um arquivo para processar.',  
                confirmButtonColor: '#2196F3'  
            });  
            return;  
        }  

        $(".loading-overlay").css("display", "flex");  

        // Processar arquivos manualmente  
        let processedCount = 0;  
        let successCount = 0;  
        let errorCount = 0;  
        let successMessages = [];  
        let errorMessages = [];  

        // Função para processar próximo arquivo  
        function processNextFile(index) {  
            if (index >= uploadedFiles.length) {  
                // Todos os arquivos foram processados  
                $(".loading-overlay").css("display", "none");  
                
                let message = '';  
                
                if (successCount > 0) {  
                    message += `<strong>${successCount} arquivo(s) processado(s) com sucesso:</strong><br>`;  
                    successMessages.forEach(msg => {  
                        message += `- ${msg}<br>`;  
                    });  
                }  
                
                if (errorCount > 0) {  
                    if (successCount > 0) message += '<br>';  
                    message += `<strong>${errorCount} arquivo(s) com erro:</strong><br>`;  
                    errorMessages.forEach(msg => {  
                        message += `- ${msg}<br>`;  
                    });  
                }  
                
                const icon = successCount > 0 ? (errorCount > 0 ? 'warning' : 'success') : 'error';  
                
                Swal.fire({  
                    icon: icon,  
                    title: successCount > 0 ? 'Processamento Concluído' : 'Erro no Processamento',  
                    html: message,  
                    confirmButtonColor: successCount > 0 ? '#28a745' : '#dc3545'  
                }).then((result) => {  
                    if (result.isConfirmed) {  
                        // Limpar todos os arquivos  
                        myDropzone.removeAllFiles(true);  
                        uploadedFiles = [];  
                        $("#processBtn").prop("disabled", true);  
                    }  
                });  
                
                return;  
            }  
            
            const file = uploadedFiles[index];  
            const formData = new FormData();  
            formData.append('arquivos[]', file);  
            formData.append('tipo_planilha', tipoPlanilhaSelecionado);  
            
            // Atualizar barra de progresso  
            const progress = Math.round(((index + 1) / uploadedFiles.length) * 100);  
            $(".progress-bar").css("width", progress + "%");  
            $(".progress-text").text(progress + "%");  
            
            $.ajax({  
                url: 'processar_xlsx.php',  
                type: 'POST',  
                data: formData,  
                processData: false,  
                contentType: false,  
                success: function(response) {  
                    processedCount++;  
                    
                    try {  
                        const result = typeof response === 'string' ? JSON.parse(response) : response;  
                        
                        if (result.status === 'success') {  
                            successCount++;  
                            successMessages.push(`${file.name}: ${result.message}`);  
                        } else {  
                            errorCount++;  
                            errorMessages.push(`${file.name}: ${result.message}`);  
                        }  
                    } catch (e) {  
                        console.error('Erro ao analisar resposta:', e);  
                        console.log('Resposta bruta:', response);  
                        errorCount++;  
                        errorMessages.push(`${file.name}: Resposta inválida do servidor`);  
                    }  
                    
                    // Processar próximo arquivo  
                    processNextFile(index + 1);  
                },  
                error: function(xhr, status, error) {  
                    processedCount++;  
                    errorCount++;  
                    errorMessages.push(`${file.name}: ${error || 'Erro ao processar o arquivo'}`);  
                    
                    // Processar próximo arquivo mesmo em caso de erro  
                    processNextFile(index + 1);  
                }  
            });  
        }  
        
        // Iniciar processamento  
        processNextFile(0);  
    });  
});  
</script>  
<?php include(__DIR__ . '/../../rodape.php'); ?>  
</body>  
</html>
