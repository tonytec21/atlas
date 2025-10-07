<?php  
// pedidos_certidao/index.php  
include(__DIR__ . '/../os/session_check.php');  
checkSession();  
include(__DIR__ . '/../os/db_connection.php');  
date_default_timezone_set('America/Sao_Paulo');  

/* ============================================================  
   0) MIGRAÇÃO – cria tabelas caso não existam (execução silenciosa)  
   ============================================================ */  
function ensureSchema(PDO $conn) {
    $sqls = [];

    // Tabela principal de pedidos
    $sqls[] = <<<SQL
CREATE TABLE IF NOT EXISTS pedidos_certidao (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  protocolo         VARCHAR(32)  NOT NULL UNIQUE,
  token_publico     CHAR(40)     NOT NULL UNIQUE,
  atribuicao        VARCHAR(20)  NOT NULL,
  tipo              VARCHAR(50)  NOT NULL,
  status            ENUM('pendente','em_andamento','emitida','entregue','cancelada') NOT NULL DEFAULT 'pendente',

  requerente_nome   VARCHAR(255) NOT NULL,
  requerente_doc    VARCHAR(32)  NULL,
  requerente_email  VARCHAR(120) NULL,
  requerente_tel    VARCHAR(30)  NULL,

  portador_nome     VARCHAR(255) NULL,
  portador_doc      VARCHAR(32)  NULL,

  referencias_json  JSON         NULL,
  base_calculo      DECIMAL(12,2) DEFAULT 0,
  total_os          DECIMAL(12,2) DEFAULT 0,
  ordem_servico_id  INT          NULL,

  anexo_pdf_path    VARCHAR(500) NULL,
  retirado_por      VARCHAR(255) NULL,
  cancelado_motivo  VARCHAR(500) NULL,

  criado_por        VARCHAR(120) NOT NULL,
  atualizado_por    VARCHAR(120) NULL,
  criado_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status (status),
  INDEX idx_protocolo (protocolo),
  INDEX idx_token_publico (token_publico),
  INDEX idx_os (ordem_servico_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    // Log de transição de status
    $sqls[] = <<<SQL
CREATE TABLE IF NOT EXISTS pedidos_certidao_status_log (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id      INT NOT NULL,
  status_anterior ENUM('pendente','em_andamento','emitida','entregue','cancelada') NULL,
  novo_status     ENUM('pendente','em_andamento','emitida','entregue','cancelada') NOT NULL,
  observacao      VARCHAR(500) NULL,
  usuario         VARCHAR(255) NOT NULL,
  ip              VARCHAR(45)  NULL,
  user_agent      VARCHAR(255) NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pedido (pedido_id),
  CONSTRAINT fk_pedido_statuslog FOREIGN KEY (pedido_id)
    REFERENCES pedidos_certidao(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    // Outbox para integração online (assíncrona)
    $sqls[] = <<<SQL
CREATE TABLE IF NOT EXISTS api_outbox (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  topic         ENUM('pedido_criado','status_atualizado') NOT NULL,
  protocolo     VARCHAR(32)  NOT NULL,
  token_publico CHAR(40)     NOT NULL,
  payload_json  JSON         NOT NULL,
  api_key       VARCHAR(120) NULL,
  signature     VARCHAR(256) NULL,
  timestamp_utc BIGINT       NOT NULL,
  request_id    VARCHAR(64)  NOT NULL,
  delivered_at  DATETIME     NULL,
  retries       INT          NOT NULL DEFAULT 0,
  last_error    VARCHAR(1000) NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_topic (topic),
  INDEX idx_protocolo (protocolo),
  INDEX idx_token (token_publico)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    foreach ($sqls as $sql) {  
        $conn->exec($sql);  
    }  
}  

try {  
    $conn = getDatabaseConnection();  
    ensureSchema($conn);  
} catch (Throwable $e) {  
    // silencioso  
}  

/* ============================================================  
   1) Busca dataset para listar (já com pendência de API)  
   ============================================================ */  
$stmt = $conn->query("  
  SELECT p.*,  
         (SELECT COUNT(*) FROM pedidos_certidao_status_log s WHERE s.pedido_id = p.id) as logs,  
         (SELECT COUNT(*) FROM api_outbox o  
           WHERE o.protocolo = p.protocolo  
             AND o.token_publico = p.token_publico  
             AND o.delivered_at IS NULL) AS pend_api,  
         (SELECT MAX(o.last_error) FROM api_outbox o  
           WHERE o.protocolo = p.protocolo  
             AND o.token_publico = p.token_publico  
             AND o.delivered_at IS NULL) AS last_api_error  
    FROM pedidos_certidao p  
ORDER BY p.criado_em DESC  
");  
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);  

/* Lista de tipos disponíveis para o filtro (distinct) */  
$tipos = [];  
try {  
    $tq = $conn->query("SELECT DISTINCT tipo FROM pedidos_certidao WHERE tipo IS NOT NULL AND tipo <> '' ORDER BY tipo ASC");  
    $tipos = $tq->fetchAll(PDO::FETCH_COLUMN);  
} catch (Throwable $e) {  
    $tipos = [];  
}  

/* Estatísticas rápidas */  
$stats = [  
    'total' => count($pedidos),  
    'pendente' => 0,  
    'em_andamento' => 0,  
    'emitida' => 0,  
    'entregue' => 0,  
    'cancelada' => 0  
];  
foreach ($pedidos as $p) {  
    if (isset($stats[$p['status']])) {  
        $stats[$p['status']]++;  
    }  
}  
?>  

<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
<meta charset="UTF-8">  
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">  
<title>Pedidos de Certidão — Sistema Premium</title>  
<link rel="preconnect" href="https://fonts.googleapis.com">  
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>  
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">  
<link rel="stylesheet" href="../style/css/bootstrap.min.css">  
<link rel="stylesheet" href="../style/css/font-awesome.min.css">  
<link rel="stylesheet" href="../style/css/style.css">  
<link rel="icon" href="../style/img/favicon.png" type="image/png">  
<link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">  
<?php if (file_exists(__DIR__ . '/../style/sweetalert2.min.css')): ?>  
<link rel="stylesheet" href="../style/sweetalert2.min.css">  
<?php else: ?>  
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">  
<?php endif; ?>  

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
</style>  
</head>  

<body>  
<?php include(__DIR__ . '/../menu.php'); ?>  

<main id="main" class="main-content">
  <div class="container">  
    
    <!-- Hero Section -->  
    <section class="page-hero">  
      <div class="title-row">  
        <div class="title-icon">  
          <i class="fa fa-files-o" aria-hidden="true"></i>  
        </div>  
        <div>  
          <h1>Pedidos de Certidão</h1>  
        </div>  
      </div>  

      <!-- Statistics Cards -->  
      <div class="stats-grid">  
        <div class="stat-card" data-status="total" onclick="filterByStatus('')">  
          <div class="stat-card-header">  
            <span class="stat-card-label">Total</span>  
            <div class="stat-card-icon">  
              <i class="fa fa-files-o"></i>  
            </div>  
          </div>  
          <div class="stat-card-value"><?= $stats['total'] ?></div>  
        </div>  

        <div class="stat-card" data-status="pendente" onclick="filterByStatus('pendente')">  
          <div class="stat-card-header">  
            <span class="stat-card-label">Pendente</span>  
            <div class="stat-card-icon">  
              <i class="fa fa-clock-o"></i>  
            </div>  
          </div>  
          <div class="stat-card-value"><?= $stats['pendente'] ?></div>  
        </div>  

        <div class="stat-card" data-status="em_andamento" onclick="filterByStatus('em_andamento')">  
          <div class="stat-card-header">  
            <span class="stat-card-label">Em andamento</span>  
            <div class="stat-card-icon">  
              <i class="fa fa-spinner"></i>  
            </div>  
          </div>  
          <div class="stat-card-value"><?= $stats['em_andamento'] ?></div>  
        </div>  

        <div class="stat-card" data-status="emitida" onclick="filterByStatus('emitida')">  
          <div class="stat-card-header">  
            <span class="stat-card-label">Emitida</span>  
            <div class="stat-card-icon">  
              <i class="fa fa-check-circle"></i>  
            </div>  
          </div>  
          <div class="stat-card-value"><?= $stats['emitida'] ?></div>  
        </div>  

        <div class="stat-card" data-status="entregue" onclick="filterByStatus('entregue')">  
          <div class="stat-card-header">  
            <span class="stat-card-label">Entregue</span>  
            <div class="stat-card-icon">  
              <i class="fa fa-thumbs-up"></i>  
            </div>  
          </div>  
          <div class="stat-card-value"><?= $stats['entregue'] ?></div>  
        </div>  

        <div class="stat-card" data-status="cancelada" onclick="filterByStatus('cancelada')">  
          <div class="stat-card-header">  
            <span class="stat-card-label">Cancelada</span>  
            <div class="stat-card-icon">  
              <i class="fa fa-times-circle"></i>  
            </div>  
          </div>  
          <div class="stat-card-value"><?= $stats['cancelada'] ?></div>  
        </div>  
      </div>  
    </section>  

    <!-- Filtros -->  
    <div class="filter-card">  
      <div class="filter-header">  
        <i class="fa fa-filter"></i>  
        <h3>Filtros Avançados</h3>  
      </div>  

      <div class="row">  
        <div class="col-lg-3 col-md-6 mb-3">  
          <label class="form-label">Protocolo</label>  
          <input type="text" id="f_protocolo" class="form-control" placeholder="Ex.: ABC123">  
        </div>  

        <div class="col-lg-2 col-md-6 mb-3">  
          <label class="form-label">Status</label>  
          <select id="f_status" class="custom-select">  
            <option value="">Todos</option>  
            <option value="pendente">Pendente</option>  
            <option value="em_andamento">Em andamento</option>  
            <option value="emitida">Emitida</option>  
            <option value="entregue">Entregue</option>  
            <option value="cancelada">Cancelada</option>  
          </select>  
        </div>  

        <div class="col-lg-2 col-md-6 mb-3">  
          <label class="form-label">Atribuição</label>  
          <select id="f_atr" class="custom-select">  
            <option value="">Todas</option>  
            <option value="Notas">Notas</option>  
            <option value="Registro Civil">Registro Civil</option>  
            <option value="RI">Registro de Imóveis</option>  
            <option value="Títulos e Documentos">Títulos e Documentos</option>  
            <option value="Pessoas Jurídicas">Pessoas Jurídicas</option>  
          </select>  
        </div>  

        <div class="col-lg-2 col-md-6 mb-3">  
          <label class="form-label">Tipo</label>  
          <select id="f_tipo" class="custom-select">  
            <option value="">Todos</option>  
            <?php foreach ($tipos as $t): ?>  
              <option value="<?=htmlspecialchars($t)?>"><?=htmlspecialchars($t)?></option>  
            <?php endforeach; ?>  
          </select>  
        </div>  

        <div class="col-lg-3 col-md-6 mb-3">  
          <label class="form-label">Requerente</label>  
          <input type="text" id="f_req" class="form-control" placeholder="Nome / parte">  
        </div>  

        <div class="col-lg-3 col-md-6 mb-3">  
          <label class="form-label">Portador</label>  
          <input type="text" id="f_portador" class="form-control" placeholder="Nome / parte">  
        </div>  

        <div class="col-lg-2 col-md-6 mb-3">  
          <label class="form-label">De</label>  
          <div class="input-group">
            <input type="text" id="f_de" class="form-control datepicker" placeholder="dd/mm/aaaa" autocomplete="off">
            <div class="input-group-append">
              <span class="input-group-text" style="background: var(--bg-tertiary); border: 2px solid var(--border-primary); border-left: none; cursor: pointer;" onclick="$('#f_de').focus();">
                <i class="fa fa-calendar" style="color: var(--text-tertiary);"></i>
              </span>
            </div>
          </div>
        </div>  

        <div class="col-lg-2 col-md-6 mb-3">  
          <label class="form-label">Até</label>  
          <div class="input-group">
            <input type="text" id="f_ate" class="form-control datepicker" placeholder="dd/mm/aaaa" autocomplete="off">
            <div class="input-group-append">
              <span class="input-group-text" style="background: var(--bg-tertiary); border: 2px solid var(--border-primary); border-left: none; cursor: pointer;" onclick="$('#f_ate').focus();">
                <i class="fa fa-calendar" style="color: var(--text-tertiary);"></i>
              </span>
            </div>
          </div>
        </div>  

        <div class="col-lg-5 col-md-12 mb-3 d-flex align-items-end">  
          <div class="filter-actions">  
            <a href="novo_pedido.php" class="btn btn-primary">  
              <i class="fa fa-plus"></i> Novo Pedido  
            </a>  
            <button id="btnAplicar" class="btn btn-outline-primary">  
              <i class="fa fa-filter"></i> Aplicar  
            </button>  
            <button id="btnLimpar" class="btn btn-outline-secondary">  
              <i class="fa fa-times"></i> Limpar  
            </button>  
          </div>  
        </div>  
      </div>  
    </div> 

    <!-- Table Controls -->  
    <div class="table-controls">  
      <div class="page-length">  
        <select id="len" class="custom-select">  
          <option value="10">10</option>  
          <option value="25" selected>25</option>  
          <option value="50">50</option>  
          <option value="100">100</option>  
        </select>  
        <span>resultados por página</span>  
      </div>  

      <div class="search-box">  
        <label>Pesquisar</label>  
        <input id="globalSearch" type="text" class="form-control" placeholder="Digite para buscar...">  
      </div>  
    </div>  

    <!-- DESKTOP: DataTable -->  
    <div class="desktop-table">  
      <div class="table-responsive">  
        <table id="tabela" class="table table-striped table-hover">  
          <thead>  
            <tr>  
              <th>ID</th>  
              <th class="nowrap">Protocolo</th>  
              <th>Status</th>  
              <th>Atribuição / Tipo</th>  
              <th>Requerente</th>  
              <th>Portador</th>  
              <th class="nowrap">Total O.S.</th>  
              <th class="nowrap">Criado em</th>  
              <th class="nowrap">API</th>  
              <th class="nowrap" style="min-width:220px;">Ações</th>  
            </tr>  
          </thead>  
          <tbody>  
          <?php foreach ($pedidos as $p): ?>  
            <?php  
              $pend = (int)($p['pend_api'] ?? 0);  
              $hasErr = $pend > 0 && !empty($p['last_api_error']);  
              $isento = empty($p['ordem_servico_id']) || (int)$p['ordem_servico_id'] === 0;
            ?>  
            <tr data-pedido-id="<?=$p['id']?>">  
              <td class="nowrap"><?=htmlspecialchars($p['id'])?></td>  
              <td class="nowrap"><strong><?=htmlspecialchars($p['protocolo'])?></strong></td>  
              <td>  
                <span class="badge badge-status status-<?=htmlspecialchars($p['status'])?>"><?=str_replace('_',' ',htmlspecialchars($p['status']))?></span>  
              </td>  
              <td><?=htmlspecialchars($p['atribuicao'])?> / <?=htmlspecialchars($p['tipo'])?></td>  
              <td><?=htmlspecialchars($p['requerente_nome'])?></td>  
              <td><?=htmlspecialchars($p['portador_nome'] ?? '-')?></td>  
              <td class="nowrap">
                <strong>R$ <?=number_format((float)$p['total_os'],2,',','.')?></strong>
                <?php if ($isento): ?>
                  <span class="badge-isento" style="margin-left:6px;">Isento</span>
                <?php endif; ?>
              </td>
              <td class="nowrap"><?=date('d/m/Y H:i', strtotime($p['criado_em']))?></td>  
              <td class="nowrap">  
                <?php if ($pend > 0): ?>  
                  <span class="badge <?=$hasErr ? 'badge-api-err':'badge-api-warn'?>" title="<?=htmlspecialchars($p['last_api_error'] ?? '')?>">  
                    Pendente (<?=$pend?>)  
                  </span>  
                <?php else: ?>  
                  <span class="badge badge-api-ok">OK</span>  
                <?php endif; ?>  
              </td>  
              <td>  
                <div class="actions">  
                  <a href="visualizar_pedido.php?id=<?=$p['id']?>"  
                     class="btn btn-sm btn-info2" title="Ver detalhes" aria-label="Ver detalhes">  
                    <i class="fa fa-eye" aria-hidden="true"></i> Ver  
                  </a>  
                  <a href="gerar_recibo_pedido.php?id=<?=$p['id']?>"  
                     target="_blank" class="btn btn-sm btn-secondary" title="Recibo"  
                     aria-label="Recibo">  
                    <i class="fa fa-print" aria-hidden="true"></i> Recibo  
                  </a>  
                  <?php if ($pend > 0): ?>  
                  <button type="button"  
                          class="btn btn-sm btn-warning btn-reenviar-api"  
                          data-id="<?=$p['id']?>"  
                          title="Reenviar mensagens pendentes para a API">  
                    <i class="fa fa-refresh"></i> Reenviar  
                  </button>  
                  <?php endif; ?>  
                </div>  
                <?php if ($hasErr): ?>  
                  <div class="small-muted mt-1 text-wrap-anywhere">  
                    <i class="fa fa-exclamation-circle"></i>  
                    <?=htmlspecialchars(mb_strimwidth($p['last_api_error'],0,120,'…','UTF-8'))?>  
                  </div>  
                <?php endif; ?>  
              </td>  
            </tr>  
          <?php endforeach; ?>  
          </tbody>  
        </table>  
      </div>  
    </div>  

        <!-- MOBILE: Cards -->
    <div class="mobile-cards">
      <?php foreach ($pedidos as $p): ?>
      <?php
        $pend = (int)($p['pend_api'] ?? 0);
        $hasErr = $pend > 0 && !empty($p['last_api_error']);
        $isento = empty($p['ordem_servico_id']) || (int)$p['ordem_servico_id'] === 0;
      ?>
      <div class="card card-pedido" data-pedido-id="<?=$p['id']?>">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Protocolo: <?=htmlspecialchars($p['protocolo'])?></h5>
            <span class="badge badge-status status-<?=htmlspecialchars($p['status'])?>"><?=str_replace('_',' ',htmlspecialchars($p['status']))?></span>
          </div>

          <p class="mb-2"><strong><?=htmlspecialchars($p['atribuicao'])?> / <?=htmlspecialchars($p['tipo'])?></strong></p>
          <p class="mb-2"><strong>Requerente:</strong> <?=htmlspecialchars($p['requerente_nome'])?></p>
          <p class="mb-2"><strong>Portador:</strong> <?=htmlspecialchars($p['portador_nome'] ?? '-')?></p>
          <p class="mb-2">
            <strong>Total O.S.:</strong> R$ <?=number_format((float)$p['total_os'],2,',','.')?>
            <?php if ($isento): ?>
              <span class="badge-isento" style="margin-left:6px;">Isento</span>
            <?php endif; ?>
          </p>
          <p class="mb-3">
            <strong>API:</strong>
            <?php if ($pend > 0): ?>
              <span class="badge <?=$hasErr ? 'badge-api-err':'badge-api-warn'?>">Pendente (<?=$pend?>)</span>
            <?php else: ?>
              <span class="badge badge-api-ok">OK</span>
            <?php endif; ?>
          </p>

          <?php if ($hasErr): ?>
            <div class="small-muted mb-3 text-wrap-anywhere">
              <i class="fa fa-exclamation-circle"></i>
              <?=htmlspecialchars(mb_strimwidth($p['last_api_error'],0,140,'…','UTF-8'))?>
            </div>
          <?php endif; ?>

          <div class="d-grid gap-2">
            <a href="visualizar_pedido.php?id=<?=$p['id']?>" class="btn btn-info2 btn-sm">
              <i class="fa fa-eye"></i> Ver Detalhes
            </a>
            <a href="gerar_recibo_pedido.php?id=<?=$p['id']?>" target="_blank" class="btn btn-secondary btn-sm">
              <i class="fa fa-print"></i> Gerar Recibo
            </a>
            <?php if ($pend > 0): ?>
            <button type="button" class="btn btn-warning btn-sm btn-reenviar-api" data-id="<?=$p['id']?>">
              <i class="fa fa-refresh"></i> Reenviar para API
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</main>

<script src="../script/jquery-3.5.1.min.js"></script>

<!-- jQuery UI (Datepicker) -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- jQuery Mask Plugin (CDN alternativo mais confiável) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js" integrity="sha512-pHVGpX7F/27yZ0ISY+VVjyULApbDlD0/X0rgGbTqCE7WFW5MezNTWG/dnhtbBuICzsd0WQPgpE4REBLv+UqChw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.dataTables.min.js"></script>
<script src="../script/dataTables.bootstrap4.min.js"></script>
<script src="../script/sweetalert2.js"></script>

<script>
$(function(){
  // ===================== THEME MANAGEMENT =====================
  $.get('../load_mode.php', function(mode){
    $('body').removeClass('light-mode dark-mode').addClass(mode);
  });

  // ===================== DATATABLE INITIALIZATION =====================
  let table = null;
  if (window.matchMedia('(min-width: 992px)').matches) {
    table = $('#tabela').DataTable({
      pageLength: 25,
      order:[[0,'desc']],
      language: { url: '../style/Portuguese-Brasil.json' },
      autoWidth: false,
      dom: 't<"d-flex justify-content-between align-items-center mt-3"ip>',
      columnDefs: [
        { targets: [1,6,7,8,9], className: 'nowrap' }
      ]
    });

    // Page length control
    $('#len').on('change', function(){ 
      table.page.len(parseInt(this.value||25,10)).draw(); 
    });
  }

  // ===================== DATE PICKER E MASK =====================
  
  // Verifica se jQuery UI está disponível
  if (typeof $.datepicker !== 'undefined') {
    
    // Configuração do jQuery UI Datepicker em português
    $.datepicker.regional['pt-BR'] = {
      closeText: 'Fechar',
      prevText: '&#x3C;Anterior',
      nextText: 'Próximo&#x3E;',
      currentText: 'Hoje',
      monthNames: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                   'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
      monthNamesShort: ['Jan','Fev','Mar','Abr','Mai','Jun',
                        'Jul','Ago','Set','Out','Nov','Dez'],
      dayNames: ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'],
      dayNamesShort: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
      dayNamesMin: ['D','S','T','Q','Q','S','S'],
      weekHeader: 'Sm',
      dateFormat: 'dd/mm/yy',
      firstDay: 0,
      isRTL: false,
      showMonthAfterYear: false,
      yearSuffix: ''
    };
    $.datepicker.setDefaults($.datepicker.regional['pt-BR']);

    // Aplicar datepicker
    $('#f_de, #f_ate').datepicker({
      changeMonth: true,
      changeYear: true,
      yearRange: "-100:+10",
      showButtonPanel: true,
      dateFormat: 'dd/mm/yy',
      onSelect: function(dateText) {
        $(this).val(dateText).trigger('change');
      }
    });
    
  } else {
    console.warn('⚠️ jQuery UI não carregado. Datepicker desabilitado.');
  }

  // Aplicar máscara de data (funciona independente)
  if (typeof $.fn.mask !== 'undefined') {
    $('#f_de, #f_ate').mask('00/00/0000', {
      placeholder: "dd/mm/aaaa",
      clearIfNotMatch: false
    });
  } else {
    console.warn('⚠️ jQuery Mask não carregado. Máscara desabilitada.');
  }

  // Validação básica ao sair do campo
  $('#f_de, #f_ate').on('blur', function(){
    const val = $(this).val();
    if (val && val.length === 10) {
      const parts = val.split('/');
      if (parts.length === 3) {
        const day = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10);
        const year = parseInt(parts[2], 10);
        
        if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1900) {
          Swal.fire({
            icon: 'warning',
            title: 'Data Inválida',
            text: 'Por favor, insira uma data válida.',
            confirmButtonColor: '#f59e0b',
            customClass: {
              popup: 'swal-premium',
              confirmButton: 'btn btn-primary'
            }
          });
          $(this).val('').focus();
        }
      }
    }
  });

  // ===================== GLOBAL SEARCH =====================
  $('#globalSearch').on('keyup change', function(){
    if (table){ table.search(this.value).draw(); }
  });

  // ===================== FILTER BY STATUS (Stats Cards) =====================
  window.filterByStatus = function(status) {
    $('#f_status').val(status);
    applyFilters();
  };

  // ===================== TEXT NORMALIZATION =====================
  function norm(s){
    return (s||'').toString()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g,'')
      .toLowerCase();
  }

  // ===================== CUSTOM FILTER MANAGEMENT =====================
  function removeCustomFilter(tag){
    $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn){
      return !(fn && fn.__tag === tag);
    });
  }

  function regexEscape(s){ 
    return (s||'').replace(/[.*+?^${}()|[\]\\]/g, '\\__CODE_BLOCK_0__'); 
  }

  // ===================== APPLY FILTERS =====================
  function applyFilters(){
    if (!table) return;

    const protocolo = $('#f_protocolo').val().trim();
    const status    = $('#f_status').val();
    const atr       = $('#f_atr').val().trim();
    const tipo      = $('#f_tipo').val().trim();
    const reqTerm   = $('#f_req').val().trim();
    const portTerm  = $('#f_portador').val().trim();
    const de        = $('#f_de').val().trim();
    const ate       = $('#f_ate').val().trim();

    // Simple column filters
    table.columns(1).search(protocolo, false, false);
    table.columns(2).search(status ? status.replace('_',' ') : '', false, false);

    const tipoR = tipo ? '.*' + regexEscape(tipo) : '';
    const atrTipo = (atr ? regexEscape(atr) : '') + tipoR;
    table.columns(3).search(atrTipo, true, false);

    table.columns(4).search('');
    table.columns(5).search('');

    // Date filter
    removeCustomFilter('date');
    if (de || ate) {
      const dateFilter = function(settings, data){
        const val = (data[7]||'').trim();
        const m = val.match(/(\d{2})\/(\d{2})\/(\d{4})/);
        if (!m) return true;
        const d = new Date(+m[3], +m[2]-1, +m[1], 0,0,0,0).getTime();
        let ok = true;
        if (de){
          const md = de.split('/');
          const dmin = new Date(+md[2], +md[1]-1, +md[0], 0,0,0,0).getTime();
          ok = ok && (d >= dmin);
        }
        if (ate){
          const ma = ate.split('/');
          const dmax = new Date(+ma[2], +ma[1]-1, +ma[0], 23,59,59,999).getTime();
          ok = ok && (d <= dmax);
        }
        return ok;
      };
      dateFilter.__tag = 'date';
      $.fn.dataTable.ext.search.push(dateFilter);
    }

    // People filter (accent-insensitive)
    removeCustomFilter('people');
    if (reqTerm || portTerm){
      const reqN = norm(reqTerm);
      const portN = norm(portTerm);
      const peopleFilter = function(settings, data){
        const req = norm(data[4]||'');   // coluna 4 - Requerente
        const por = norm(data[5]||'');   // coluna 5 - Portador
        let ok = true;
        if (reqN){ ok = ok && req.indexOf(reqN) !== -1; }
        if (portN){ ok = ok && por.indexOf(portN) !== -1; }
        return ok;
      };
      peopleFilter.__tag = 'people';
      $.fn.dataTable.ext.search.push(peopleFilter);
    }

    table.draw();
  }

  // ===================== FILTER BUTTONS =====================
  $('#btnAplicar').on('click', function(){
    const $btn = $(this);
    const originalText = $btn.html();
    
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Aplicando...');
    
    setTimeout(function(){
      applyFilters();
      $btn.prop('disabled', false).html(originalText);
    }, 300);
  });

  $('#btnLimpar').on('click', function(){
    const $btn = $(this);
    const originalText = $btn.html();
    
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Limpando...');
    
    $('#f_protocolo,#f_tipo,#f_req,#f_portador,#f_de,#f_ate').val('');
    $('#f_status,#f_atr').val('');
    
    if (table){
      $('#globalSearch').val('');
      table.search('');
      removeCustomFilter('date');
      removeCustomFilter('people');
      table.columns().search('');
      table.draw();
    }
    
    setTimeout(function(){
      $btn.prop('disabled', false).html(originalText);
    }, 300);
  });

  // ===================== REENVIO API =====================
  $(document).on('click', '.btn-reenviar-api', function(){
    const id = $(this).data('id');
    const $btn = $(this);
    const original = $btn.html();

    Swal.fire({
      title: 'Reenviar para API?',
      text: 'As mensagens pendentes serão reenviadas para integração.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: '<i class="fa fa-refresh"></i> Sim, reenviar',
      cancelButtonText: '<i class="fa fa-times"></i> Cancelar',
      confirmButtonColor: '#6366f1',
      cancelButtonColor: '#6c757d',
      reverseButtons: true,
      customClass: {
        popup: 'swal-premium',
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-outline-secondary'
      }
    }).then((result) => {
      if (!result.isConfirmed) return;

      $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Reenviando...');

      $.post('reenvio_api.php', { pedido_id: id }, function(resp){
        if (!resp || resp.error){
          Swal.fire({
            icon: 'error',
            title: 'Erro ao Reenviar',
            text: (resp && resp.error) ? resp.error : 'Falha ao reenviar mensagens.',
            confirmButtonColor: '#ef4444',
            customClass: {
              popup: 'swal-premium',
              confirmButton: 'btn btn-primary'
            }
          });
          return;
        }

        const ok = (resp.success === true && resp.failed === 0);
        const deliveredCount = resp.delivered || 0;
        const failedCount = resp.failed || 0;
        
        let message = `<strong>${deliveredCount}</strong> mensagem(ns) entregue(s) com sucesso.`;
        if (failedCount > 0) {
          message += `<br><strong>${failedCount}</strong> mensagem(ns) falharam.`;
        }

        Swal.fire({
          icon: ok ? 'success' : 'warning',
          title: ok ? 'Reenviado com Sucesso' : 'Parcialmente Entregue',
          html: message,
          confirmButtonColor: ok ? '#10b981' : '#f59e0b',
          customClass: {
            popup: 'swal-premium',
            confirmButton: 'btn btn-primary'
          }
        }).then(() => {
          location.reload();
        });
      }, 'json')
      .fail(function(xhr){
        console.error('Erro no servidor:', xhr.responseText);
        Swal.fire({
          icon: 'error',
          title: 'Erro de Comunicação',
          text: 'Não foi possível contatar o servidor. Tente novamente.',
          confirmButtonColor: '#ef4444',
          customClass: {
            popup: 'swal-premium',
            confirmButton: 'btn btn-primary'
          }
        });
      })
      .always(function(){
        $btn.prop('disabled', false).html(original);
      });
    });
  });

  // ===================== KEYBOARD SHORTCUTS =====================
  $(document).on('keydown', function(e) {
    // Ctrl/Cmd + K = Focus global search
    if ((e.altKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      $('#globalSearch').focus().select();
    }
    
    // Ctrl/Cmd + N = Novo pedido
    if ((e.altKey || e.metaKey) && e.key === 'n') {
      e.preventDefault();
      window.location.href = 'novo_pedido.php';
    }
    
    // Escape = Clear search
    if (e.key === 'Escape') {
      const $search = $('#globalSearch');
      if ($search.is(':focus')) {
        $search.val('').blur();
        if (table) {
          table.search('').draw();
        }
      }
    }
  });

  // ===================== MOBILE FILTER TOGGLE =====================
  if (window.matchMedia('(max-width: 991.98px)').matches) {
    // No mobile, adicionar botão de toggle para filtros
    const $filterCard = $('.filter-card');
    const $filterContent = $filterCard.find('.row, .filter-actions').wrapAll('<div class="filter-content"></div>').parent();
    
    $filterCard.find('.filter-header').css('cursor', 'pointer').on('click', function(){
      $filterContent.slideToggle(300);
      $(this).find('i').toggleClass('fa-filter fa-times');
    });
    
    // Começa colapsado no mobile
    $filterContent.hide();
  }

  // ===================== TABLE ROW ANIMATIONS =====================
  if (table) {
    table.on('draw.dt', function(){
      $('.table tbody tr').each(function(i){
        $(this).css({
          'animation': `fadeInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1) ${i * 0.03}s backwards`
        });
      });
    });
  }

  // ===================== TOOLTIP INITIALIZATION =====================
  $('[title]').each(function(){
    $(this).attr('data-toggle', 'tooltip');
  });
  
  if (typeof $().tooltip === 'function') {
    $('[data-toggle="tooltip"]').tooltip();
  }

  // ===================== AUTO-REFRESH INDICATOR =====================
  let autoRefreshInterval = null;
  
  function startAutoRefresh() {
    if (autoRefreshInterval) return;
    
    autoRefreshInterval = setInterval(function(){
      // Verifica se há pedidos com API pendente
      const hasPending = $('.badge-api-warn, .badge-api-err').length > 0;
      
      if (hasPending) {
        // Apenas recarrega se houver pendências (silencioso)
        $.get(window.location.href, function(){
          // Poderia atualizar dados sem full reload, mas por simplicidade mantemos reload
          console.log('Auto-refresh: verificando pendências...');
        });
      }
    }, 60000); // 60 segundos
  }
  
  // Inicia auto-refresh apenas se houver pendências
  if ($('.badge-api-warn, .badge-api-err').length > 0) {
    startAutoRefresh();
  }

  // ===================== STAT CARDS ANIMATION =====================
  $('.stat-card').each(function(i){
    $(this).css({
      'animation-delay': `${0.1 + (i * 0.05)}s`
    });
  });

  // ===================== LOADING STATE MANAGEMENT =====================
  function showLoading() {
    $('body').append('<div class="loading-overlay"><div class="spinner"></div></div>');
  }
  
  function hideLoading() {
    $('.loading-overlay').fadeOut(200, function(){ $(this).remove(); });
  }

  // ===================== SMOOTH SCROLL TO TOP =====================
  $(window).scroll(function(){
    if ($(this).scrollTop() > 300) {
      if (!$('#scrollTop').length) {
        $('body').append(`
          <button id="scrollTop">
            <i class="fa fa-arrow-up" style="font-size:20px;"></i>
          </button>
        `);
        
        setTimeout(() => $('#scrollTop').css('opacity', '1'), 100);
        
        $('#scrollTop').on('click', function(){
          $('html, body').animate({ scrollTop: 0 }, 600);
        });
      }
    } else {
      $('#scrollTop').css('opacity', '0');
      setTimeout(() => $('#scrollTop').remove(), 300);
    }
  });

  // ===================== CONSOLE SIGNATURE =====================
  // console.log(
  //   '%c📋 Sistema de Pedidos de Certidão',
  //   'font-size: 20px; font-weight: bold; color: #6366f1; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);'
  // );
  // console.log(
  //   '%cDesenvolvido com excelência • backupcloud.site • ' + new Date().getFullYear(),
  //   'font-size: 12px; color: #8b949e;'
  // );
  // console.log(
  //   '%cAtalhos de teclado:\n' +
  //   '• Alt + K: Buscar\n' +
  //   '• Alt + N: Novo pedido\n' +
  //   '• Escape: Limpar busca',
  //   'font-size: 11px; color: #6c757d; font-family: monospace;'
  // );

  // ===================== PERFORMANCE MONITORING =====================
  // if (window.performance && window.performance.timing) {
  //   const loadTime = window.performance.timing.domContentLoadedEventEnd - window.performance.timing.navigationStart;
  //   console.log(`%c⚡ Página carregada em ${loadTime}ms`, 'color: #10b981; font-weight: bold;');
  // }
});

// ===================== SWEETALERT2 CUSTOM STYLING =====================
const swalStyle = document.createElement('style');
swalStyle.textContent = `
  .swal-premium {
    border-radius: var(--radius-xl) !important;
    backdrop-filter: blur(24px) saturate(180%);
    box-shadow: var(--shadow-2xl) !important;
    background: var(--bg-tertiary) !important;
  }
  
  .swal2-popup .swal2-title {
    font-family: var(--font-primary);
    font-weight: 800;
    font-size: 24px;
    letter-spacing: -0.02em;
    color: var(--text-primary) !important;
  }
  
  .swal2-popup .swal2-html-container {
    font-family: var(--font-primary);
    font-size: 15px;
    line-height: 1.6;
    color: var(--text-secondary) !important;
  }
  
  .swal2-popup .swal2-confirm,
  .swal2-popup .swal2-cancel {
    border-radius: var(--radius-md) !important;
    padding: 12px 24px !important;
    font-weight: 700 !important;
    transition: all 0.3s ease !important;
    font-family: var(--font-primary) !important;
  }
  
  .swal2-popup .swal2-confirm:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg) !important;
  }
  
  .swal2-popup .swal2-icon {
    border-color: var(--border-secondary) !important;
  }
  
  .dark-mode .swal2-popup {
    background: var(--bg-tertiary) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-primary);
  }
  
  .dark-mode .swal2-title {
    color: var(--text-primary) !important;
  }
  
  .dark-mode .swal2-html-container {
    color: var(--text-secondary) !important;
  }
  
  .dark-mode .swal2-icon.swal2-question {
    border-color: var(--brand-primary) !important;
    color: var(--brand-primary) !important;
  }
  
  .dark-mode .swal2-icon.swal2-success [class^='swal2-success-line'] {
    background-color: var(--brand-success) !important;
  }
  
  .dark-mode .swal2-icon.swal2-success .swal2-success-ring {
    border-color: var(--brand-success) !important;
  }
  
  .dark-mode .swal2-icon.swal2-error [class^='swal2-x-mark-line'] {
    background-color: var(--brand-error) !important;
  }
  
  .dark-mode .swal2-icon.swal2-warning {
    border-color: var(--brand-warning) !important;
    color: var(--brand-warning) !important;
  }
`;
document.head.appendChild(swalStyle);
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>

</body>
</html>