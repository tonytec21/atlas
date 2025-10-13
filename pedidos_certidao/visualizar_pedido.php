<?php  
// pedidos_certidao/visualizar_pedido.php  
include(__DIR__ . '/../os/session_check.php');  
checkSession();  
include(__DIR__ . '/../os/db_connection.php');  
date_default_timezone_set('America/Sao_Paulo');  

/* Sessão segura */  
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }  
if (empty($_SESSION['csrf_pedidos'])) { $_SESSION['csrf_pedidos'] = bin2hex(random_bytes(32)); }  
$csrf = $_SESSION['csrf_pedidos'];  

/* ID do pedido */  
$id = (int)($_GET['id'] ?? 0);  
if ($id <= 0) { die('ID inválido'); }  

/* Migração mínima das tabelas necessárias */  
function ensureSchema(PDO $conn){  
  $conn->exec("CREATE TABLE IF NOT EXISTS pedidos_certidao_status_log (  
    id INT AUTO_INCREMENT PRIMARY KEY,  
    pedido_id INT NOT NULL,  
    status_anterior ENUM('pendente','em_andamento','emitida','entregue','cancelada') NULL,  
    novo_status ENUM('pendente','em_andamento','emitida','entregue','cancelada') NOT NULL,  
    observacao VARCHAR(500) NULL,  
    usuario VARCHAR(255) NOT NULL,  
    ip VARCHAR(45) NULL,  
    user_agent VARCHAR(255) NULL,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    INDEX idx_pedido (pedido_id)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");  

  $conn->exec("CREATE TABLE IF NOT EXISTS api_outbox (  
    id BIGINT AUTO_INCREMENT PRIMARY KEY,  
    topic ENUM('pedido_criado','status_atualizado') NOT NULL,  
    protocolo VARCHAR(32) NOT NULL,  
    token_publico CHAR(40) NOT NULL,  
    payload_json JSON NOT NULL,  
    api_key VARCHAR(120) NULL,  
    signature VARCHAR(256) NULL,  
    timestamp_utc BIGINT NOT NULL,  
    request_id VARCHAR(64) NOT NULL,  
    delivered_at DATETIME NULL,  
    retries INT NOT NULL DEFAULT 0,  
    last_error VARCHAR(1000) NULL,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    INDEX idx_topic (topic),  
    INDEX idx_protocolo (protocolo),  
    INDEX idx_token (token_publico)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");  

  $conn->exec("CREATE TABLE IF NOT EXISTS pedido_anexos (  
    id BIGINT AUTO_INCREMENT PRIMARY KEY,  
    pedido_id INT NOT NULL,  
    original_filename VARCHAR(255) NOT NULL,  
    mime_type VARCHAR(100) NOT NULL,  
    ext VARCHAR(10) NOT NULL,  
    path VARCHAR(600) NOT NULL,  
    size_bytes BIGINT NOT NULL DEFAULT 0,  
    paginas_pdf INT NULL,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    INDEX idx_pedido (pedido_id),  
    CONSTRAINT fk_pedido_anexo FOREIGN KEY (pedido_id)  
      REFERENCES pedidos_certidao(id) ON DELETE CASCADE  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");  

  $conn->exec("CREATE TABLE IF NOT EXISTS pedido_anexo_imagens (  
    id BIGINT AUTO_INCREMENT PRIMARY KEY,  
    anexo_id BIGINT NOT NULL,  
    page_number INT NOT NULL,  
    path VARCHAR(600) NOT NULL,  
    width INT NULL,  
    height INT NULL,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    INDEX idx_anexo (anexo_id),  
    INDEX idx_pagina (anexo_id, page_number),  
    CONSTRAINT fk_anexo_img FOREIGN KEY (anexo_id)  
      REFERENCES pedido_anexos(id) ON DELETE CASCADE  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");  
}  

$conn = getDatabaseConnection(); ensureSchema($conn);  

/* Carrega pedido */  
$stmt = $conn->prepare("SELECT * FROM pedidos_certidao WHERE id=?");  
$stmt->execute([$id]);  
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);  
if (!$pedido) { die('Pedido não encontrado'); }  

/* Normaliza status */  
$pedidoStatus = trim((string)($pedido['status'] ?? ''));  
if ($pedidoStatus === '') { $pedidoStatus = 'pendente'; }  

/* Logs */  
$logsStmt = $conn->prepare("SELECT * FROM pedidos_certidao_status_log WHERE pedido_id=? ORDER BY id DESC");  
$logsStmt->execute([$id]);  
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);  

/* Dados da O.S. vinculada */
$osId     = (int)($pedido['ordem_servico_id'] ?? 0);
$osStatus = null;
$totalOS  = (float)($pedido['total_os'] ?? 0);
$totalPag = 0.0;
$totalDev = 0.0;
$isento   = false;

if ($osId > 0) {
  $q = $conn->prepare("SELECT status, total_os, base_de_calculo FROM ordens_de_servico WHERE id=? LIMIT 1");
  $q->execute([$osId]);
  if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    $osStatus = $row['status'] ?? null;
    if (isset($row['total_os'])) $totalOS = (float)$row['total_os'];
  }

  // Total pago
  $q = $conn->prepare("SELECT SUM(total_pagamento) FROM pagamento_os WHERE ordem_de_servico_id=?");
  $q->execute([$osId]);
  $totalPag = (float)($q->fetchColumn() ?: 0);

  // Verifica se há pagamento do tipo ISENTO
  $q = $conn->prepare("
    SELECT COUNT(*) 
    FROM pagamento_os 
    WHERE ordem_de_servico_id=? 
      AND (forma_de_pagamento = 'Isento de Pagamento' OR forma_de_pagamento = 'Isento')
  ");
  $q->execute([$osId]);
  $isento = ((int)$q->fetchColumn() > 0);

  // Total devolvido
  $q = $conn->prepare("SELECT SUM(total_devolucao) FROM devolucao_os WHERE ordem_de_servico_id=?");
  $q->execute([$osId]);
  $totalDev = (float)($q->fetchColumn() ?: 0);
}

/* Badge de situação (inclui ISENTO) */
$osBadgeText  = '';
$osBadgeClass = '';

if ($osStatus === 'Cancelado' || $osStatus === 'Cancelada') {
  $osBadgeText  = 'Cancelada';
  $osBadgeClass = 'situacao-cancelado';
} elseif ($isento) {
  $osBadgeText  = 'Isento de Pagamento'; // ou apenas 'Isento'
  $osBadgeClass = 'situacao-isento';
} elseif ($totalPag > 0) {
  $osBadgeText  = 'Pago (Depósito Prévio)';
  $osBadgeClass = 'situacao-pago';
} else {
  $osBadgeText  = 'Ativa (Pendente de Pagamento)';
  $osBadgeClass = 'situacao-ativo';
}


/* Auto-cancelamento se O.S. cancelada */  
$fezAutoCancelamento = false;  
try {  
  if ($osStatus && (strcasecmp($osStatus,'Cancelado')===0 || strcasecmp($osStatus,'Cancelada')===0)) {  
    if ($pedidoStatus !== 'cancelada') {  
      $conn->beginTransaction();  
      $username = $_SESSION['username'] ?? 'sistema';  

      $conn->prepare("UPDATE pedidos_certidao SET status='cancelada', cancelado_motivo=COALESCE(cancelado_motivo,'Cancelamento de O.S'), atualizado_por=:user, atualizado_em=NOW() WHERE id=:id")->execute([':user'=>$username, ':id'=>$id]);  

      $ip = $_SERVER['REMOTE_ADDR'] ?? null;  
      $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;  
      $conn->prepare("INSERT INTO pedidos_certidao_status_log (pedido_id, status_anterior, novo_status, observacao, usuario, ip, user_agent) VALUES (?,?,?,?,?,?,?)")->execute([$id, $pedidoStatus, 'cancelada', 'Cancelamento de O.S', $username, $ip, $ua]);  

      $apiConfig   = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];  
      $API_KEY     = $apiConfig['api_key']     ?? null;  
      $HMAC_SECRET = $apiConfig['hmac_secret'] ?? null;  

      $payload = ['protocolo'=>$pedido['protocolo'],'token_publico'=>$pedido['token_publico'],'status'=>'cancelada','atualizado_em'=>date('c'),'observacao'=>true];  
      $timestamp = (int) round(microtime(true) * 1000);  
      $requestId = bin2hex(random_bytes(12));  
      $signature = null;  
      if ($HMAC_SECRET) {  
        $base = $timestamp . json_encode($payload, JSON_UNESCAPED_UNICODE);  
        $signature = hash_hmac('sha256', $base, $HMAC_SECRET);  
      }  
      $conn->prepare("INSERT INTO api_outbox (topic, protocolo, token_publico, payload_json, api_key, signature, timestamp_utc, request_id) VALUES ('status_atualizado',?,?,?,?,?,?,?)")->execute([$pedido['protocolo'],$pedido['token_publico'],json_encode($payload, JSON_UNESCAPED_UNICODE),$API_KEY,$signature,$timestamp,$requestId]);  

      $conn->commit();  
      $fezAutoCancelamento = true;  

      $stmt = $conn->prepare("SELECT * FROM pedidos_certidao WHERE id=?");  
      $stmt->execute([$id]);  
      $pedido = $stmt->fetch(PDO::FETCH_ASSOC);  
      $pedidoStatus = $pedido['status'] ?? 'cancelada';  

      $logsStmt = $conn->prepare("SELECT * FROM pedidos_certidao_status_log WHERE pedido_id=? ORDER BY id DESC");  
      $logsStmt->execute([$id]);  
      $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);  
    }  
  }  
} catch (Throwable $e) {}  

/* Status "visual" da O.S. */  
$osBadgeText  = '';
$osBadgeClass = '';

if ($osStatus === 'Cancelado' || $osStatus === 'Cancelada') {
  $osBadgeText  = 'Cancelada';
  $osBadgeClass = 'situacao-cancelado';
} elseif (!empty($isento)) {
  $osBadgeText  = 'Isento de Pagamento';
  $osBadgeClass = 'situacao-isento';
} elseif ($totalPag > 0) {
  $osBadgeText  = 'Pago (Depósito Prévio)';
  $osBadgeClass = 'situacao-pago';
} else {
  $osBadgeText  = 'Ativa (Pendente de Pagamento)';
  $osBadgeClass = 'situacao-ativo';
}

/* Bloqueios e mensagem do bloqueio */  
$bloquearAlteracao = false;  
$bloqueioMsg = '';  

if ($pedidoStatus === 'entregue') {  
  $bloquearAlteracao = true;  
  $bloqueioMsg = 'Pedido já ENTREGUE.';  
} elseif ($pedidoStatus === 'cancelada') {  
  $bloquearAlteracao = true;  
  $bloqueioMsg = 'Pedido CANCELADO.';  
} elseif ($osStatus === 'Cancelado' || $osStatus === 'Cancelada') {  
  $bloquearAlteracao = true;  
  $bloqueioMsg = 'O.S. cancelada. Pedido cancelado automaticamente.';  
} elseif ($osId > 0 && !($totalPag > 0) && !$isento && !(strcasecmp(trim((string)$osStatus),'Isento de Pagamento')===0)) {
  // Exige pagamento SOMENTE se não houver isenção (pagamento "Isento de Pagamento")
  $bloquearAlteracao = true;
  $bloqueioMsg = 'Para alterar o status do pedido é necessário que a O.S. esteja com Depósito Prévio pago ou marcada como Isento de Pagamento.';
}  

/* QR / URL pública */  
$qrPath     = __DIR__."/qrcodes/pedido_{$id}.png";  
$qrExists   = file_exists($qrPath);  
$apiConfig  = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];  
$baseUrl    = $apiConfig['base_url'] ?? 'https://consultapedido.sistemaatlas.com.br';  
$urlPublica = !empty($pedido['token_publico'])  
  ? rtrim($baseUrl,'/').'/v1/rastreio/'.$pedido['token_publico']  
  : '#' ;  

/* ===== Integração / Outbox: status do envio para API ===== */  
$outboxRows = [];  
$pendingCount = 0;  
$hasFailures  = false;  
$lastErr      = null;  
$lastTopic    = null;  
$lastId       = null;  

try{  
  $st = $conn->prepare("SELECT id, topic, delivered_at, retries, last_error, timestamp_utc, payload_json, criado_em FROM api_outbox WHERE token_publico = ? ORDER BY id DESC LIMIT 50");  
  $st->execute([$pedido['token_publico']]);  
  $outboxRows = $st->fetchAll(PDO::FETCH_ASSOC);  

  foreach($outboxRows as $row){  
    if (empty($row['delivered_at'])) {  
      $pendingCount++;  
      if (!$lastErr && !empty($row['last_error'])) { $lastErr = $row['last_error']; }  
      if ($row['retries'] > 0 || !empty($row['last_error'])) { $hasFailures = true; }  
      if ($lastId === null) { $lastId = (int)$row['id']; $lastTopic = $row['topic']; }  
    }  
  }  
} catch(Throwable $e){}  

$integrationStatus = 'ok';  
if ($pendingCount > 0) {  
  $integrationStatus = $hasFailures ? 'error' : 'pending';  
}  
?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
<meta charset="UTF-8">  
<meta name="viewport" content="width=device-width, initial-scale=1.0">  
<title>Pedido #<?=htmlspecialchars($pedido['protocolo'])?></title>  

<!-- Fontes -->  
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
$swalCssLocal = __DIR__ . '/../style/sweetalert2.min.css';  
if (file_exists($swalCssLocal)) {  
  echo '<link rel="stylesheet" href="../style/sweetalert2.min.css">' . PHP_EOL;  
} else {  
  echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">' . PHP_EOL;  
}  
?>  

<!-- Dropzone -->  
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone@5/dist/min/dropzone.min.css">  

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

/* ===================== STATUS PILLS & BADGES ===================== */  
.status-pill {  
  display: inline-flex;  
  align-items: center;  
  gap: var(--space-sm);  
  margin-top: var(--space-sm);  
}  

.dot {  
  width: 10px;  
  height: 10px;  
  border-radius: 50%;  
  display: inline-block;  
}  

.dot.pendente { background: #ffc107; box-shadow: 0 0 8px rgba(255, 193, 7, 0.5); }  
.dot.em_andamento { background: #17a2b8; box-shadow: 0 0 8px rgba(23, 162, 184, 0.5); }  
.dot.emitida { background: #28a745; box-shadow: 0 0 8px rgba(40, 167, 69, 0.5); }  
.dot.entregue { background: #6f42c1; box-shadow: 0 0 8px rgba(111, 66, 193, 0.5); }  
.dot.cancelada { background: #dc3545; box-shadow: 0 0 8px rgba(220, 53, 69, 0.5); }  

.badge-status {  
  font-size: 13px;  
  font-weight: 700;  
  padding: 8px 16px;  
  border-radius: var(--radius-md);  
  letter-spacing: 0.05em;  
  text-transform: uppercase;  
}  

.badge.pendente { background: #ffd65a; color: #3b3b3b; }  
.badge.em_andamento { background: #17a2b8; color: #fff; }  
.badge.emitida { background: #28a745; color: #fff; }  
.badge.entregue { background: #6f42c1; color: #fff; }  
.badge.cancelada { background: #dc3545; color: #fff; }  

.timeline-status {  
  text-transform: uppercase;  
  font-weight: 700;  
  font-size: 14px;  
}  

/* ===================== SITUAÇÃO OS ===================== */  
.situacao-pago {  
  background-color: #28a745;  
  color: #fff;  
  padding: 6px 12px;  
  border-radius: var(--radius-sm);  
  font-size: 13px;  
  font-weight: 600;  
}  

.situacao-ativo {  
  background-color: #ffa907;  
  color: #fff;  
  padding: 6px 12px;  
  border-radius: var(--radius-sm);  
  font-size: 13px;  
  font-weight: 600;  
}  

.situacao-cancelado {  
  background-color: #dc3545;  
  color: #fff;  
  padding: 6px 12px;  
  border-radius: var(--radius-sm);  
  font-size: 13px;  
  font-weight: 600;  
}  

/* ===================== CARDS ===================== */  
.card {  
  background: var(--bg-elevated);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-lg);  
  box-shadow: var(--shadow-md);  
  transition: all 0.3s ease;  
  margin-bottom: var(--space-lg);  
}  

.card:hover {  
  box-shadow: var(--shadow-xl);  
  border-color: var(--border-secondary);  
}  

.card-header {  
  background: var(--bg-tertiary);  
  border-bottom: 2px solid var(--border-primary);  
  border-top-left-radius: calc(var(--radius-lg) - 2px);  
  border-top-right-radius: calc(var(--radius-lg) - 2px);  
  padding: var(--space-md) var(--space-lg);  
  font-weight: 700;  
  font-size: 16px;  
  color: var(--text-primary);  
}  

.card-body {  
  padding: var(--space-lg);  
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

.btn-sm {  
  padding: 6px 14px;  
  font-size: 13px;  
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
}  

.btn-outline-primary {  
  background: transparent;  
  color: var(--brand-primary);  
  border: 2px solid var(--brand-primary);  
}  

.btn-outline-primary:hover {  
  background: var(--brand-primary);  
  color: white;  
  transform: translateY(-1px);  
}  

.btn-outline-secondary {  
  background: transparent;  
  color: var(--text-secondary);  
  border: 2px solid var(--border-secondary);  
}  

.btn-outline-secondary:hover {  
  background: var(--surface-hover);  
  border-color: var(--brand-primary);  
  color: var(--brand-primary);  
  transform: translateY(-1px);  
}  

.btn-outline-dark {  
  background: transparent;  
  color: var(--text-primary);  
  border: 2px solid var(--border-primary);  
}  

.btn-outline-dark:hover {  
  background: #444749;  
  border-color: #444749;  
  transform: translateY(-1px);  
}  

.btn-light {  
  background: var(--bg-secondary);  
  color: var(--text-primary);  
  border: 2px solid var(--border-primary);  
}  

.btn-light:hover {  
  background: var(--surface-hover);  
  border-color: var(--brand-primary);  
}  

.dark-mode .btn-outline-primary {  
  color: #93c5fd;  
  border-color: #93c5fd;  
}  

.dark-mode .btn-outline-primary:hover {  
  background: #93c5fd;  
  color: #0b1220;  
}  

.dark-mode .btn-outline-secondary {  
  color: #e5e7eb;  
  border-color: #9aa4b2;  
}  

.dark-mode .btn-outline-secondary:hover {  
  background: #9aa4b2;  
  color: #0b1220;  
}  

.dark-mode .btn-outline-dark {  
  color: #e5e7eb;  
  border-color: #cbd5e1;  
}  

.dark-mode .btn-outline-dark:hover {  
  background: #cbd5e1;  
  color: #0b1220;  
}  

.dark-mode .btn-success {  
  background: #16a34a;  
  border-color: #16a34a;  
}  

/* ===================== TIMELINE ===================== */  
.step {  
  display: flex;  
  align-items: flex-start;  
  gap: var(--space-md);  
  margin-bottom: var(--space-md);  
  padding: var(--space-md);  
  background: var(--bg-tertiary);  
  border-radius: var(--radius-md);  
  transition: all 0.3s ease;  
}  

.step:hover {  
  background: var(--surface-hover);  
  transform: translateX(4px);  
}  

.step .dot {  
  margin-top: 6px;  
}  

/* ===================== REFERÊNCIAS ===================== */  
.ref-grid {  
  display: grid;  
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));  
  gap: var(--space-md);  
  margin-top: var(--space-md);  
}  

.ref-item {  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-md);  
  padding: var(--space-md);  
  transition: all 0.3s ease;  
}  

.ref-item:hover {  
  border-color: var(--brand-primary);  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
}  

.ref-key {  
  display: block;  
  font-size: 11px;  
  letter-spacing: 0.05em;  
  color: var(--text-tertiary);  
  text-transform: uppercase;  
  margin-bottom: var(--space-xs);  
  font-weight: 700;  
}  

.ref-value {  
  font-weight: 600;  
  word-break: break-word;  
  color: var(--text-primary);  
}  

/* ===================== API STATUS ===================== */  
.api-status {  
  border-radius: var(--radius-lg);  
  transition: all 0.3s ease;  
}  

.api-status.ok {  
  background: rgba(16, 185, 129, 0.08);  
  border: 2px solid rgba(16, 185, 129, 0.25);  
}  

.api-status.pending {  
  background: rgba(245, 158, 11, 0.08);  
  border: 2px solid rgba(245, 158, 11, 0.25);  
}  

.api-status.error {  
  background: rgba(239, 68, 68, 0.08);  
  border: 2px solid rgba(239, 68, 68, 0.25);  
}  

.api-status .badge {  
  border-radius: var(--radius-sm);  
  color: #fff;  
  font-weight: 700;  
}  

.api-mini {  
  font-size: 14px;  
}  

.api-details {  
  font-size: 13px;  
  color: var(--text-secondary);  
}  

/* ===================== FORM CONTROLS ===================== */  
.form-control,  
select.form-control {  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-md);  
  padding: 10px 14px;  
  font-size: 14px;  
  color: var(--text-primary);  
  transition: all 0.3s ease;  
  font-family: var(--font-primary);  
}  

.form-control:focus,  
select.form-control:focus {  
  background: var(--bg-elevated);  
  border-color: var(--brand-primary);  
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);  
  outline: none;  
}  

.form-control::placeholder {  
  color: var(--text-tertiary);  
}   

label {  
  font-weight: 600;  
  font-size: 13px;  
  color: var(--text-secondary);  
  margin-bottom: var(--space-sm);  
  letter-spacing: 0.01em;  
}  

/* ===================== ALERTS ===================== */  
.alert {  
  border-radius: var(--radius-md);  
  padding: var(--space-md);  
  border: 2px solid;  
  font-size: 14px;  
}  

.block-alert {  
  margin-bottom: var(--space-md);  
}  

/* ===================== DROPZONE ===================== */  
.dropzone {  
  border: 3px dashed var(--brand-primary);  
  border-radius: var(--radius-lg);  
  background: rgba(99, 102, 241, 0.04);  
  padding: var(--space-xl);  
  transition: all 0.3s ease;  
  cursor: pointer;  
}  

.dropzone:hover {  
  background: rgba(99, 102, 241, 0.08);  
  border-color: var(--brand-primary-light);  
  transform: translateY(-2px);  
}  

.dropzone .dz-message {  
  color: var(--brand-primary);  
  font-weight: 700;  
  font-size: 15px;  
  margin: 0;  
}  

.dz-success-mark,  
.dz-error-mark {  
  display: none;  
}  

.dark-mode .dropzone {  
  border-color: rgba(147, 197, 253, 0.6);  
  background: rgba(147, 197, 253, 0.08);  
}  

.dark-mode .dropzone .dz-message {  
  color: #cfe5ff;  
}  

/* ===================== ANEXOS ===================== */  
.attach-grid {  
  display: grid;  
  gap: var(--space-md);  
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));  
}  

.attach-card {  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-md);  
  padding: var(--space-md);  
  background: var(--bg-elevated);  
  display: flex;  
  flex-direction: column;  
  gap: var(--space-sm);  
  transition: all 0.3s ease;  
}  

.attach-card:hover {  
  border-color: var(--brand-primary);  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-lg);  
}  

.attach-title {  
  font-weight: 700;  
  word-break: break-word;  
  font-size: 14px;  
  color: var(--text-primary);  
}  

.attach-meta {  
  font-size: 12px;  
  color: var(--text-tertiary);  
  display: flex;  
  gap: var(--space-sm);  
  flex-wrap: wrap;  
}  

.attach-actions {  
  display: flex;  
  gap: var(--space-xs);  
  flex-wrap: wrap;  
}  

.attach-select {  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  gap: var(--space-sm);  
}  

.attach-empty {  
  border: 2px dashed var(--border-secondary);  
  border-radius: var(--radius-md);  
  padding: var(--space-xl);  
  text-align: center;  
  color: var(--text-tertiary);  
}  

.attach-toolbar {  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  gap: var(--space-md);  
  flex-wrap: wrap;  
}  

.attach-toolbar .btn-group {  
  flex-wrap: wrap;  
  gap: var(--space-sm);  
}  

#btnUpload {  
  background: var(--gradient-success);  
  color: white;  
  font-weight: 700;  
  border: none;  
  padding: 10px 20px;  
  border-radius: var(--radius-md);  
  box-shadow: var(--shadow-md);  
}  

#btnUpload:hover:not(:disabled) {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-xl);  
  opacity: 0.95;  
}  

#btnUpload:disabled {  
  opacity: 0.5;  
  cursor: not-allowed;  
}  

#btnCompilar {  
  background: transparent;  
  color: var(--brand-primary);  
  border: 2px solid var(--brand-primary);  
  font-weight: 700;  
  padding: 10px 20px;  
  border-radius: var(--radius-md);  
}  

#btnCompilar:hover:not(:disabled) {  
  background: var(--brand-primary);  
  color: white;  
  transform: translateY(-1px);  
  box-shadow: var(--shadow-md);  
}  

#btnCompilar:disabled {  
  opacity: 0.5;  
  cursor: not-allowed;  
}  

.dark-mode #btnCompilar {  
  color: #93c5fd;  
  border-color: #93c5fd;  
}  

.dark-mode #btnCompilar:hover:not(:disabled) {  
  background: #93c5fd;  
  color: #0b1220;  
}  

/* ===================== FORM CHECK ===================== */  
.form-check-input {  
  margin-right: var(--space-sm) !important;  
  cursor: pointer;  
}  

.form-check-label {  
  cursor: pointer;  
}  

/* ===================== MODAL MINIMALISTA ===================== */  
#viewerModal .modal-dialog {  
  max-width: 98vw;  
  width: 98vw;  
  margin: 0.5rem auto;  
}  

#viewerModal .modal-content {  
  border-radius: var(--radius-sm);  
  border: 1px solid rgba(0, 0, 0, 0.1);  
  box-shadow: var(--shadow-2xl);  
  background: #000;  
}  

.dark-mode #viewerModal .modal-content {  
  border-color: rgba(255, 255, 255, 0.1);  
}  

#viewerModal .modal-header {  
  background: rgba(0, 0, 0, 0.9);  
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);  
  padding: var(--space-sm) var(--space-md);  
  backdrop-filter: blur(10px);  
}  

#viewerModal .modal-title {  
  color: white;  
  font-size: 15px;  
  font-weight: 600;  
}  

#viewerModal .btn-close {  
  filter: invert(1);  
  opacity: 0.8;  
}  

#viewerModal .btn-close:hover {  
  opacity: 1;  
}  

#viewerModal .modal-body {  
  min-height: 92vh;  
  background: #000;  
  padding: 0;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  overflow: hidden;  
}  

.dark-mode #viewerModal .modal-body {  
  background: #000;  
}  

.viewer-img-wrap {  
  width: 100%;  
  height: 92vh;  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  background: #000;  
}  

.viewer-img-wrap img {  
  max-width: 100%;  
  max-height: 100%;  
  width: auto;  
  height: auto;  
  object-fit: contain;  
  display: block;  
}  

.viewer-img-wrap iframe {  
  width: 100%;  
  height: 100%;  
  border: none;  
}  

#viewerModal .btn-group .btn {  
  background: rgba(255, 255, 255, 0.1);  
  color: white;  
  border: 1px solid rgba(255, 255, 255, 0.2);  
  font-size: 13px;  
  padding: 6px 12px;  
}  

#viewerModal .btn-group .btn:hover {  
  background: rgba(255, 255, 255, 0.2);  
  border-color: rgba(255, 255, 255, 0.3);  
}  

/* ===================== RESPONSIVE ===================== */  
@media (max-width: 768px) {  
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

  .btn-group {  
    flex-direction: column;  
    width: 100%;  
  }  

  .btn-group .btn {  
    width: 100%;  
    justify-content: center;  
  }  

  .attach-toolbar {  
    flex-direction: column;  
    align-items: stretch;  
  }  

  .attach-grid {  
    grid-template-columns: 1fr;  
  }  

  .ref-grid {  
    grid-template-columns: 1fr;  
  }  

  #viewerModal .modal-dialog {  
    max-width: 100vw;  
    width: 100vw;  
    margin: 0;  
  }  

  .viewer-img-wrap {  
    height: 95vh;  
  }  

  #viewerModal .modal-body {  
    min-height: 95vh;  
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

.card {  
  animation: fadeInUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) backwards;  
}  

.card:nth-child(1) { animation-delay: 0.1s; }  
.card:nth-child(2) { animation-delay: 0.2s; }  
.card:nth-child(3) { animation-delay: 0.3s; }  
.card:nth-child(4) { animation-delay: 0.4s; }  

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

/* ===================== FOOTER COMPATIBILITY ===================== */  
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
</style>  
</head>  

<body>  
<?php include(__DIR__ . '/../menu.php'); ?>  

<main id="main" class="main-content">  
  <div class="container">  

    <section class="page-hero" aria-labelledby="pedido-title">  
      <div class="title-row">  
        <div class="title-icon">  
          <i class="fa fa-file-text-o" aria-hidden="true"></i>  
        </div>  
        <div>  
          <h1 id="pedido-title">  
            Pedido: <?=htmlspecialchars($pedido['protocolo'])?>  
          </h1>  
          <div class="status-pill" aria-live="polite">  
            <span class="dot <?=htmlspecialchars($pedidoStatus)?>" aria-hidden="true"></span>  
            <span class="badge badge-status <?=htmlspecialchars($pedidoStatus)?>">  
              <?=str_replace('_',' ',htmlspecialchars($pedidoStatus))?>  
            </span>  
          </div>  
          <?php if ($fezAutoCancelamento): ?>  
            <div class="mt-2 small text-danger">  
              Este pedido foi cancelado automaticamente porque a O.S. está cancelada.  
            </div>  
          <?php endif; ?>  
        </div>  
      </div>  
    </section>  

    <div class="row">  
      <div class="col-lg-8">  
        <!-- DADOS DO PEDIDO -->  
        <div class="card">  
          <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">  
            <strong>Dados do Pedido</strong>  
            <div class="btn-group" role="group" aria-label="Ações">  
              <a class="btn btn-sm btn-outline-secondary" href="index.php">  
                <i class="fa fa-arrow-left"></i> Voltar à lista  
              </a>  
              <?php if ($osId > 0): ?>  
              <a class="btn btn-sm btn-outline-primary" href="../os/visualizar_os.php?id=<?=urlencode($osId)?>" target="_blank" rel="noopener">  
                <i class="mdi mdi-file-document-outline"></i> Abrir O.S.  
              </a>  
              <?php endif; ?>  
              <a class="btn btn-sm btn-outline-dark" href="gerar_recibo_pedido.php?id=<?=urlencode($id)?>" target="_blank" rel="noopener">  
                <i class="mdi mdi-receipt-text"></i> Abrir Recibo  
              </a>  
            </div>  
          </div>  
          <div class="card-body">  
            <p class="mb-2"><strong>Atribuição/Tipo:</strong>  
              <?=htmlspecialchars($pedido['atribuicao'])?> / <?=htmlspecialchars($pedido['tipo'])?></p>  

            <p class="mb-2"><strong>Requerente:</strong>  
              <?=htmlspecialchars($pedido['requerente_nome'])?>  
              <?php if (!empty($pedido['requerente_doc'])): ?>  
                (<?=htmlspecialchars($pedido['requerente_doc'])?>)  
              <?php endif; ?>  
              <?php if (!empty($pedido['requerente_email'])): ?>  
                 – <?=htmlspecialchars($pedido['requerente_email'])?>  
              <?php endif; ?>  
              <?php if (!empty($pedido['requerente_tel'])): ?>  
                 – <?=htmlspecialchars($pedido['requerente_tel'])?>  
              <?php endif; ?>  
            </p>  

            <p class="mb-2"><strong>Portador:</strong>  
              <?=htmlspecialchars($pedido['portador_nome'] ?? '-')?>  
              <?php if (!empty($pedido['portador_doc'])): ?>  
                (<?=htmlspecialchars($pedido['portador_doc'])?>)  
              <?php endif; ?>  
            </p>  

            <p class="mb-2"><strong>Base de Cálculo:</strong>  
              R$ <?=number_format((float)$pedido['base_calculo'],2,',','.')?></p>  

            <p class="mb-2 d-flex align-items-center gap-2 flex-wrap">  
              <strong>Ordem de Serviço:</strong>  
              <span>#<?=htmlspecialchars($osId)?></span>  
              <span>•</span>  
              <span>Total: <strong>R$ <?=number_format((float)$totalOS,2,',','.')?></strong></span>  
              <?php if ($osId > 0): ?>  
                <span class="<?=$osBadgeClass?> ml-2" style="display:inline-block;">  
                  <?=$osBadgeText?>  
                </span>  
                <?php if ($totalPag > 0 || $totalDev > 0): ?>  
                  <small class="text-muted ml-1">  
                    (Pagos: R$ <?=number_format($totalPag,2,',','.')?><?= $totalDev>0 ? ' • Devoluções: R$ '.number_format($totalDev,2,',','.') : '' ?>)  
                  </small>  
                <?php endif; ?>  
              <?php endif; ?>  
            </p>  

            <hr>  
            <h6 class="mb-2">Referências</h6>  
            <?php  
              $refs = json_decode($pedido['referencias_json']??'{}', true);  
              if (!is_array($refs)) { $refs = []; }  

              $labels = [  
                'nome_registrado' => 'Nome do Registrado',  
                'nome_noivo'      => 'Nome do Noivo',  
                'nome_noiva'      => 'Nome da Noiva',  
                'nome_falecido'   => 'Nome do Falecido',  
                'partes'          => 'Partes',  
                'matricula'       => 'Matrícula',  
                'imovel'          => 'Imóvel',  
                'circunscricao'   => 'Circunscrição',  
                'livro'           => 'Livro',  
                'folha'           => 'Folha',  
                'termo'           => 'Termo',  
                'data_registro'   => 'Data do Registro',  
                'cartorio'        => 'Cartório',  
                'cidade'          => 'Cidade',  
                'uf'              => 'UF'  
              ];  

              $fmt = function($v){  
                if (is_bool($v)) return $v ? 'Sim' : 'Não';  
                if (is_array($v)) {  
                  $isAssoc = array_keys($v) !== range(0, count($v) - 1);  
                  if ($isAssoc) {  
                    $pairs = [];  
                    foreach($v as $k=>$val){  
                      $pairs[] = ucfirst(str_replace('_',' ',$k)).': '.(is_scalar($val)?$val:json_encode($val, JSON_UNESCAPED_UNICODE));  
                    }  
                    return implode(' • ', $pairs);  
                  }  
                  return implode(', ', array_map(function($x){ return is_scalar($x)?$x:json_encode($x, JSON_UNESCAPED_UNICODE); }, $v));  
                }  
                return (string)$v;  
              };  

              if (empty($refs)) {  
                echo '<p class="text-muted mb-0">Nenhuma referência informada.</p>';  
              } else {  
                $known = []; $others = [];  
                foreach($refs as $k=>$v){ if (array_key_exists($k, $labels)) $known[$k]=$v; else $others[$k]=$v; }  
                echo '<div class="ref-grid">';  
                foreach($known as $k=>$v){  
                  echo '<div class="ref-item"><span class="ref-key">'.htmlspecialchars($labels[$k]).'</span><div class="ref-value">'.htmlspecialchars($fmt($v)).'</div></div>';  
                }  
                foreach($others as $k=>$v){  
                  $label = ucfirst(str_replace('_',' ',$k));  
                  echo '<div class="ref-item"><span class="ref-key">'.htmlspecialchars($label).'</span><div class="ref-value">'.htmlspecialchars($fmt($v)).'</div></div>';  
                }  
                echo '</div>';  
              }  
            ?>  
          </div>  
        </div>  

        <!-- TIMELINE -->  
        <div class="card">  
          <div class="card-header"><strong>Timeline de Status</strong></div>  
          <div class="card-body">  
            <?php if (count($logs) === 0): ?>  
              <p class="text-muted">Nenhum evento registrado.</p>  
            <?php else: ?>  
              <div role="list" aria-label="Linha do tempo do pedido">  
              <?php foreach($logs as $l): ?>  
                <div class="step" role="listitem">  
                  <span class="dot <?=htmlspecialchars($l['novo_status'])?>" aria-hidden="true"></span>  
                  <div>  
                    <div><strong class="timeline-status"><?=str_replace('_',' ',htmlspecialchars($l['novo_status']))?></strong>  
                      – <?=date('d/m/Y H:i', strtotime($l['criado_em']))?></div>  
                    <?php if(!empty($l['observacao'])): ?>  
                      <div class="text-muted"><?=htmlspecialchars($l['observacao'])?></div>  
                    <?php endif; ?>  
                    <small class="text-muted">  
                      por <?=htmlspecialchars($l['usuario'])?>  
                      <?php if(!empty($l['ip'])): ?> – IP: <?=htmlspecialchars($l['ip'])?><?php endif; ?>  
                      <?php if(!empty($l['user_agent'])): ?> – UA: <?=htmlspecialchars($l['user_agent'])?><?php endif; ?>  
                    </small>  
                  </div>  
                </div>  
              <?php endforeach; ?>  
              </div>  
            <?php endif; ?>  
          </div>  
        </div>  

      </div>  
      <div class="col-lg-4">  

        <!-- RASTREIO -->  
        <div class="card">  
          <div class="card-header d-flex justify-content-between align-items-center">  
            <strong>Rastreio</strong>  
            <div class="btn-group btn-group-sm" role="group" aria-label="Ações de rastreio">  
              <a href="<?=$urlPublica?>" target="_blank" rel="noopener" class="btn btn-light">  
                <i class="fa fa-external-link"></i> Abrir  
              </a>  
              <button type="button" id="btnCopy" class="btn btn-outline-secondary" data-url="https://sistemaatlas.com.br/<?=htmlspecialchars($pedido['protocolo'])?>">  
                <i class="fa fa-clipboard"></i> Copiar link  
              </button>  
            </div>  
          </div>  
          <div class="card-body text-center">  
            <?php if ($qrExists): ?>  
              <img src="<?='qrcodes/pedido_'.$id.'.png'?>" alt="QR code do rastreio" style="max-width:100%;height:auto;">  
            <?php else: ?>  
              <p class="text-muted mb-0">QR não disponível.</p>  
            <?php endif; ?>  
            <div class="small mt-2 text-break">https://sistemaatlas.com.br/<?=htmlspecialchars($pedido['protocolo'])?></div>  
          </div>  
        </div>  

        <!-- INTEGRAÇÃO API -->  
        <div class="card api-status <?=htmlspecialchars($integrationStatus)?>">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Integração com a API</strong>
            <?php if ($integrationStatus === 'ok'): ?>
              <span class="badge bg-success">OK</span>
            <?php elseif ($integrationStatus === 'pending'): ?>
              <span class="badge bg-warning text-dark">Pendente</span>
            <?php else: ?>
              <span class="badge bg-danger">Falha</span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if ($integrationStatus === 'ok'): ?>
              <div class="api-mini text-success d-flex align-items-center gap-2">
                <i class="mdi mdi-check-decagram"></i> Nenhum item pendente de envio.
              </div>
            <?php else: ?>
              <div class="api-mini mb-2">
                <div class="mb-1"><strong>Itens pendentes:</strong> <?=$pendingCount?></div>
                <?php if ($lastErr): ?>
                  <div class="api-details">
                    <div class="mb-1"><strong>Último erro:</strong></div>
                    <div class="text-break"><?=htmlspecialchars($lastErr)?></div>
                  </div>
                <?php else: ?>
                  <div class="api-details">Há itens a enviar. Você pode reenviar agora.</div>
                <?php endif; ?>
              </div>
              <div class="d-grid gap-2">
                <button id="btnReenviarApi" class="btn btn-primary">
                  <i class="fa fa-refresh"></i> Reenviar agora
                </button>
              </div>
              <?php if (!empty($outboxRows)): ?>
                <hr>
                <details>
                  <summary class="small">Ver detalhes técnicos</summary>
                  <div class="api-details mt-2">
                    <?php foreach($outboxRows as $r): ?>
                      <div class="mb-2">
                        <div><strong>#<?=$r['id']?></strong> – <?=$r['topic']?> • <?=!empty($r['delivered_at'])?'Entregue':'Pendente'?><?=($r['retries']>0?' • tentativas: '.(int)$r['retries']:'')?></div>
                        <div class="text-break"><?=!empty($r['last_error'])?htmlspecialchars($r['last_error']):'<span class="text-muted">—</span>'?></div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </details>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- ALTERAR STATUS -->
        <div class="card">
          <div class="card-header"><strong>Alterar Status</strong></div>
          <div class="card-body">
            <?php if ($bloquearAlteracao): ?>
              <div class="alert alert-secondary block-alert" role="alert">
                <i class="mdi mdi-information-outline"></i>
                <?=$bloqueioMsg?>
              </div>
            <?php endif; ?>

            <form id="formStatus" method="post" enctype="multipart/form-data" aria-describedby="ajudaStatus">
              <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">
              <input type="hidden" name="id" value="<?=$id?>">
              <input type="hidden" id="hasOSFlag" value="<?= ($osId > 0 ? '1' : '0') ?>">
              <input type="hidden" id="osPagoFlag" value="<?= ($totalPag > 0 ? '1' : '0') ?>">
              <input type="hidden" id="osIsentoFlag" value="<?= ( ($isento || (strcasecmp(trim((string)$osStatus),'Isento de Pagamento')===0)) ? '1' : '0') ?>">
              <input type="hidden" id="osStatusHidden" value="<?=htmlspecialchars($osStatus ?? '')?>">
              <input type="hidden" id="pedidoStatusHidden" value="<?=htmlspecialchars($pedidoStatus)?>">

              <fieldset <?= $bloquearAlteracao ? 'disabled' : '' ?>>
                <div class="form-group mb-3">
                  <label for="novo_status" class="mb-1">Novo Status</label>
                  <select id="novo_status" name="novo_status" class="form-control" required>
                    <option value="">Selecione...</option>
                    <option value="em_andamento">Em andamento</option>
                    <option value="emitida">Emitida</option>
                    <option value="entregue">Entregue (exige quem retirou)</option>
                    <option value="cancelada">Cancelada (exige motivo)</option>
                  </select>
                  <small id="ajudaStatus" class="form-text text-muted mt-1">
                    Em "emitida", anexe o PDF da certidão. Em "entregue", informe quem retirou. Em "cancelada", descreva o motivo.
                  </small>
                </div>

                <div id="wrap_emitida" class="mb-2" style="display:none;">
                  <label class="mt-2">Anexo (PDF da certidão):</label>
                  <input type="file" name="anexo_pdf" accept="application/pdf" class="form-control">
                </div>

                <div id="wrap_entregue" class="mb-2" style="display:none;">
                  <label class="mt-2">Retirado por:</label>
                  <input type="text" name="retirado_por" class="form-control" maxlength="255">
                </div>

                <div id="wrap_cancelada" class="mb-2" style="display:none;">
                  <label class="mt-2">Motivo do cancelamento:</label>
                  <textarea name="cancelado_motivo" class="form-control" rows="2" maxlength="500"></textarea>
                </div>

                <div class="form-group mt-2">
                  <label>Observação (opcional)</label>
                  <textarea name="observacao" class="form-control" rows="2" maxlength="500"></textarea>
                </div>

                <button class="btn btn-primary w-100 mt-3" type="submit">
                  <i class="fa fa-check"></i> Atualizar
                </button>
              </fieldset>
            </form>
          </div>
        </div>

        <!-- ANEXOS -->
        <div class="card">
          <div class="card-header">
            <div class="attach-toolbar">
              <strong>Anexos</strong>
              <div class="btn-group btn-group-sm">
                <button id="btnUpload" type="button" class="btn btn-success" disabled>
                  <i class="mdi mdi-upload"></i> Enviar
                </button>
                <button id="btnCompilar" type="button" class="btn btn-outline-primary" disabled>
                  <i class="mdi mdi-file-pdf-box"></i> Baixar PDF
                </button>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="checkAll">
                <label class="form-check-label" for="checkAll">Selecionar todos</label>
              </div>
            </div>
          </div>
          <div class="card-body">
            <!-- DROPZONE -->
            <form id="dzForm" class="dropzone"
              action="anexos_upload.php"
              method="post" enctype="multipart/form-data"
              accept=".pdf,.jpg,.jpeg,.png">
              <div class="dz-message">
                Arraste seus arquivos aqui ou clique para enviar<br>
                <small class="text-muted">Aceita: PDF, JPG, PNG</small>
              </div>
            </form>

            <hr>

            <!-- LISTA EM CARDS -->
            <div id="cardsAnexos" class="attach-grid" aria-live="polite"></div>
            <div id="emptyAnexos" class="attach-empty" style="display:none;">
              Nenhum anexo enviado.
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</main>

<!-- MODAL DE VISUALIZAÇÃO -->
<div class="modal fade" id="viewerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span id="viewerTitle">Visualizar anexo</span></h5>
        <div class="btn-group me-2">
          <a id="btnOpenNew" href="#" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-external-link"></i> Nova guia
          </a>
          <a id="btnDownload" href="#" class="btn btn-sm btn-outline-primary">
            <i class="fa fa-download"></i> Baixar
          </a>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body" id="viewerBody">
        <!-- Conteúdo trocado via JS (iframe para pdf / img para imagens) -->
      </div>
    </div>
  </div>
</div>

<!-- SCROLL TO TOP -->
<button id="scrollTop" aria-label="Voltar ao topo">
  <i class="fa fa-chevron-up"></i>
</button>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dropzone@5/dist/min/dropzone.min.js"></script>
<script>
// Desativa a autodescoberta *antes* de qualquer inicialização automática
Dropzone.autoDiscover = false;
</script>
<script>
$(function(){
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

  // ===================== COPIAR LINK =====================
  $('#btnCopy').on('click', function(){
    const url = $(this).data('url');
    if (!navigator.clipboard) {
      const ta = $('<textarea>').val(url).appendTo('body').select();
      try { document.execCommand('copy'); } catch(e){}
      ta.remove();
      Swal.fire({icon:'success', title:'Copiado!', text:'Link copiado para a área de transferência.', timer: 2000, showConfirmButton: false});
      return;
    }
    navigator.clipboard.writeText(url).then(function(){
      Swal.fire({icon:'success', title:'Copiado!', text:'Link copiado para a área de transferência.', timer: 2000, showConfirmButton: false});
    }, function(){
      Swal.fire({icon:'error', title:'Falha', text:'Não foi possível copiar o link.'});
    });
  });

  // ===================== EXIBIÇÃO DE CAMPOS EXTRAS =====================
  const $novo = $('#novo_status');
  const $wrapEmitida = $('#wrap_emitida');
  const $wrapEntregue = $('#wrap_entregue');
  const $wrapCancel = $('#wrap_cancelada');
  const $file = $wrapEmitida.find('input[name="anexo_pdf"]');
  const $retirado = $wrapEntregue.find('input[name="retirado_por"]');
  const $motivo = $wrapCancel.find('textarea[name="cancelado_motivo"]');

  function toggleExtra(){
    const v = $novo.val();
    $wrapEmitida.hide(); $file.prop('required', false);
    $wrapEntregue.hide(); $retirado.prop('required', false);
    $wrapCancel.hide(); $motivo.prop('required', false);

    if (v === 'emitida')   { $wrapEmitida.show(); /* anexo opcional */ }
    if (v === 'entregue')  { $wrapEntregue.show(); $retirado.prop('required', true); }
    if (v === 'cancelada') { $wrapCancel.show(); $motivo.prop('required', true); }
  }
  $novo.on('change', toggleExtra);
  toggleExtra();

  // ===================== SUBMIT DO FORMULÁRIO DE STATUS =====================
  $('#formStatus').on('submit', function(e){
    e.preventDefault();

    const hasOS = $('#hasOSFlag').val() === '1';

    const osStatus = $('#osStatusHidden').val();
    if (hasOS && osStatus && (osStatus.toLowerCase() === 'cancelado' || osStatus.toLowerCase() === 'cancelada')) {
      Swal.fire({icon:'info', title:'Pedido já cancelado', text:'A O.S. está cancelada; o pedido foi/será cancelado automaticamente.'});
      return false;
    }

    if (hasOS) {
      const osPago   = $('#osPagoFlag').val() === '1';
      const osIsento = $('#osIsentoFlag').val() === '1'; // novo
      if (!osPago && !osIsento) {
        Swal.fire({
          icon: 'warning',
          title: 'Ação bloqueada',
          text: 'Para alterar o status do pedido a O.S. precisa estar com Depósito Prévio pago ou marcada como Isento de Pagamento.'
        });
        return false;
      }
    }
    // Se não há O.S., segue sem exigir pagamento (pedido isento)

    const fd = new FormData(this);
    const btn = $(this).find('button[type="submit"]');
    const originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Salvando...');

    $.ajax({
      url: 'alterar_status.php',
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'text',
      success: function(resText){
        let r = null;
        try { r = typeof resText === 'object' ? resText : JSON.parse(resText); }
        catch(e){
          const m = String(resText||'').match(/\{[\s\S]*\}$/);
          if (m){ try{ r = JSON.parse(m[0]); }catch(e2){} }
        }
        if (!r){
          console.error('Resposta do servidor:', resText);
          Swal.fire({icon:'error', title:'Falha', text:'Não foi possível interpretar a resposta do servidor.'});
          return;
        }
        if (r.error){
          Swal.fire({icon:'error', title:'Erro', text:r.error});
          return;
        }
        if (r.success){
          const delivery = r.api_delivery || {};
          if (delivery.attempted && !delivery.delivered){
            Swal.fire({
              icon: 'warning',
              title: 'Status atualizado, mas falhou o envio à API',
              html: `HTTP: <code>${delivery.http_code||0}</code><br>Deseja tentar reenviar agora?`,
              showCancelButton: true,
              confirmButtonText: 'Reenviar agora',
              cancelButtonText: 'OK'
            }).then((res)=>{
              if (res.isConfirmed){
                Swal.fire({title:'Reenviando...', allowOutsideClick:false, didOpen:()=>{ Swal.showLoading(); }});
                $.post('reenvio_api.php', { pedido_id: <?=$id?> }, function(resp){
                  if (resp && resp.success){
                    const ok = (resp.failed === 0);
                    Swal.fire({
                      icon: ok ? 'success' : 'warning',
                      title: ok ? 'Reenviou tudo' : 'Reenviou parcialmente',
                      text: `Entregues: ${resp.delivered||0}${resp.failed>0?` • Falhas: ${resp.failed}`:''}`
                    }).then(()=>{ location.reload(); });
                  } else {
                    Swal.fire({icon:'error', title:'Erro', text:(resp && resp.error) ? resp.error : 'Falha no reenvio.'})
                        .then(()=>{ location.reload(); });
                  }
                }, 'json').fail(function(xhr){
                  console.error(xhr.responseText);
                  Swal.fire({icon:'error', title:'Erro', text:'Não foi possível contatar o servidor.'})
                      .then(()=>{ location.reload(); });
                });
              } else {
                location.reload();
              }
            });
          } else {
            Swal.fire({icon:'success', title:'Sucesso', text: 'Status atualizado com sucesso.', timer: 2000, showConfirmButton: false})
              .then(()=>{ location.reload(); });
          }
        } else {
          Swal.fire({icon:'error', title:'Erro', text:'Resposta inesperada do servidor.'});
        }
      },
      error: function(xhr){
        console.error(xhr.responseText);
        Swal.fire({icon:'error', title:'Falha', text:'Erro na requisição.'});
      },
      complete: function(){
        btn.prop('disabled', false).html(originalHtml);
      }
    });
  });

  // ===================== REENVIAR (CARTÃO INTEGRAÇÃO API) =====================
  $('#btnReenviarApi').on('click', function(){
    Swal.fire({
      icon:'question',
      title:'Reenviar mensagens pendentes?',
      text:'Vamos tentar entregar agora os itens pendentes para a API.',
      showCancelButton:true,
      confirmButtonText:'Sim, reenviar',
      cancelButtonText:'Cancelar'
    }).then(function(result){
      if(!result.isConfirmed) return;
      Swal.fire({title:'Reenviando...', allowOutsideClick:false, didOpen:()=>{ Swal.showLoading(); }});
      $.post('reenvio_api.php', { pedido_id: <?=$id?> }, function(resp){
        if (resp && resp.success){
          const ok = (resp.failed === 0);
          Swal.fire({
            icon: ok ? 'success' : 'warning',
            title: ok ? 'Reenviou tudo' : 'Reenviou parcialmente',
            text: `Entregues: ${resp.delivered||0}${resp.failed>0?` • Falhas: ${resp.failed}`:''}`
          }).then(()=>{ location.reload(); });
        } else {
          Swal.fire({icon:'error', title:'Erro', text:(resp && resp.error) ? resp.error : 'Falha no reenvio.'});
        }
      }, 'json').fail(function(xhr){
        console.error(xhr.responseText);
        Swal.fire({icon:'error', title:'Erro', text:'Não foi possível contatar o servidor.'});
      });
    });
  });

  // ===================== DROPZONE (LIMITA PDF/JPG/PNG E ENVIA AO CLICAR) =====================
  var dzInstance = null;
  if (Array.isArray(Dropzone.instances)) {
    Dropzone.instances.forEach(function (inst) {
      if (inst && inst.element && inst.element.id === 'dzForm') dzInstance = inst;
    });
  }

  var dz = dzInstance || new Dropzone('#dzForm', {
    url: $('#dzForm').attr('action') || 'anexos_upload.php',
    method: 'post',
    paramName: 'arquivo',
    uploadMultiple: true,
    parallelUploads: 5,
    autoQueue: true,
    autoProcessQueue: false,
    maxFilesize: 50,
    acceptedFiles: '.pdf,.jpg,.jpeg,.png',
    dictDefaultMessage: 'Arraste aqui ou clique para selecionar (PDF, JPG, PNG)',
    timeout: 0
  });

  const $btnUpload = $('#btnUpload');

  function pendingCount() {
    return dz.getQueuedFiles().length + dz.getFilesWithStatus(Dropzone.ADDED).length;
  }
  function refreshUploadButton() {
    $btnUpload.prop('disabled', pendingCount() === 0);
  }
  ['addedfile','removedfile','reset','canceled','success','error'].forEach(function (ev) {
    dz.on(ev, refreshUploadButton);
  });

  $btnUpload.on('click', function () {
    if (pendingCount() === 0) return;
    $btnUpload.prop('disabled', true);
    dz.processQueue();
  });

  dz.on('sending', function (file, xhr, formData) {
    formData.append('id', '<?= $id ?>');
    formData.append('csrf', '<?= $csrf ?>');
  });

  dz.on('queuecomplete', function () {
    dz.removeAllFiles(true);
    refreshUploadButton();
    carregarLista();
  });

  dz.on('successmultiple', function () { carregarLista(); });
  dz.on('success', function () { carregarLista(); });

  dz.on('error', function (file, msg) {
    Swal.fire({
      icon: 'error',
      title: 'Falha no upload',
      text: (typeof msg === 'string' ? msg : 'Erro desconhecido')
    });
  });

  refreshUploadButton();

  // ===================== LISTA (CARDS) =====================
  function renderCard(it){
    const paginasTxt = (it.paginas_pdf ? it.paginas_pdf : (it.ext === 'pdf' ? '-' : '1'));
    return `
      <div class="attach-card" data-id="${it.id}">
        <div class="attach-title" title="${it.original_filename}">
          ${it.original_filename}
        </div>
        <div class="attach-meta">
          <span>${(it.ext || '').toUpperCase()}</span>
          ${it.size_human ? `<span>• ${it.size_human}</span>` : ''}
          <span>• Páginas: ${paginasTxt}</span>
        </div>
        <div class="attach-actions">
          <button class="btn btn-sm btn-outline-secondary btnPreview" data-id="${it.id}" data-name="${it.original_filename}" data-ext="${(it.ext||'').toLowerCase()}">
            <i class="mdi mdi-eye-outline"></i> Visualizar
          </button>
          <a class="btn btn-sm btn-outline-primary" href="anexos_stream.php?acao=download&pedido=<?=$id?>&anexo=${it.id}&csrf=<?=$csrf?>">
            <i class="mdi mdi-download"></i> Baixar
          </a>
          <a class="btn btn-sm btn-outline-dark" target="_blank" href="anexos_stream.php?acao=inline&pedido=<?=$id?>&anexo=${it.id}&csrf=<?=$csrf?>">
            <i class="mdi mdi-open-in-new"></i> Abrir guia
          </a>
        </div>
        <div class="attach-select">
          <div class="form-check">
            <input class="form-check-input checkAnexo" type="checkbox" id="chk_${it.id}" data-id="${it.id}">
            <label class="form-check-label" for="chk_${it.id}">Selecionar</label>
          </div>
        </div>
      </div>
    `;
  }

  function updateBtnCompilar(){
    const any = $('.checkAnexo:checked').length > 0;
    $('#btnCompilar').prop('disabled', !any);
  }

  $('#checkAll').on('change', function(){
    const on = $(this).is(':checked');
    $('.checkAnexo').prop('checked', on);
    updateBtnCompilar();
  });

  // ===================== CARREGA LISTA =====================
  function carregarLista(){
    $.getJSON('anexos_listar.php', { id: <?=$id?>, csrf: '<?=$csrf?>' }, function(resp){
      const $grid = $('#cardsAnexos').empty();
      const $empty = $('#emptyAnexos');
      if (!resp || !resp.itens || resp.itens.length===0){
        $empty.show();
        $('#btnCompilar').prop('disabled', true);
        return;
      }
      $empty.hide();
      resp.itens.forEach(function(it){
        $grid.append(renderCard(it));
      });

      // Eventos de seleção
      $('.checkAnexo').off('change').on('change', updateBtnCompilar);
      updateBtnCompilar();

      // Preview (modal)
      $('.btnPreview').off('click').on('click', function(){
        const anexoId = $(this).data('id');
        const name = $(this).data('name');
        const ext = String($(this).data('ext') || '').toLowerCase();
        const viewUrl = 'anexos_stream.php?acao=inline&pedido=<?=$id?>&anexo=' + anexoId + '&csrf=<?=$csrf?>';
        const downUrl = 'anexos_stream.php?acao=download&pedido=<?=$id?>&anexo=' + anexoId + '&csrf=<?=$csrf?>';

        $('#viewerTitle').text(name);
        $('#btnOpenNew').attr('href', viewUrl);
        $('#btnDownload').attr('href', downUrl);

        const $body = $('#viewerBody').empty();

        // Se for imagem, usa <img>; senão, <iframe> (PDF)
        if (['jpg','jpeg','png','gif','webp','bmp'].includes(ext)) {
          const wrap = $('<div>', { 'class':'viewer-img-wrap' });
          const img = $('<img>', { src:viewUrl, alt:name });
          wrap.append(img);
          $body.append(wrap);
        } else {
          const frame = $('<iframe>', {
            src: viewUrl,
            style: 'width:100%;height:100%;border:0;',
            allow: 'fullscreen'
          });
          $body.append(frame);
        }

        const modal = new bootstrap.Modal(document.getElementById('viewerModal'));
        modal.show();
      });
    }).fail(function(xhr){
      console.error(xhr.responseText);
      Swal.fire({icon:'error', title:'Erro', text:'Falha ao carregar anexos.'});
    });
  }
  carregarLista();

  // ===================== COMPILAR PDF =====================
  $('#btnCompilar').on('click', function(){
    const ids = $('.checkAnexo:checked').map(function(){ return $(this).data('id'); }).get();
    if (ids.length===0) return;
    Swal.fire({title:'Gerando PDF...', allowOutsideClick:false, didOpen:()=>{ Swal.showLoading(); }});
    $.ajax({
      url: 'anexos_compilar.php',
      method: 'POST',
      data: { csrf: '<?=$csrf?>', pedido_id: <?=$id?>, anexo_ids: ids.join(',') },
      dataType: 'json'
    }).done(function(resp){
      if (resp && resp.success && resp.download_url){
        Swal.fire({
          icon:'success',
          title:'PDF gerado!',
          html:'<a class="btn btn-primary mt-2" href="'+resp.download_url+'"><i class="mdi mdi-download"></i> Baixar arquivo</a>'
        });
      } else {
        Swal.fire({icon:'error', title:'Falha', text:(resp && resp.error) ? resp.error : 'Não foi possível gerar o PDF.'});
      }
    }).fail(function(xhr){
      console.error(xhr.responseText);
      Swal.fire({icon:'error', title:'Erro', text:'Falha ao gerar o PDF.'});
    });
  });

});
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>