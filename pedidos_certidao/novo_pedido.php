<?php
// pedidos_certidao/novo_pedido.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/* ------- Leitura de configs recicladas do módulo O.S. ------- */
$issConfig     = json_decode(@file_get_contents(__DIR__ . '/../os/iss_config.json'), true) ?: [];
$issAtivo      = !empty($issConfig['ativo']);
$issPercentual = isset($issConfig['percentual']) ? (float)$issConfig['percentual'] : 0.0;
$issDescricao  = isset($issConfig['descricao'])   ? $issConfig['descricao']         : 'ISS sobre Emolumentos';

$atosSemValor = json_decode(@file_get_contents(__DIR__ . '/../os/atos_valor_zero.json'), true) ?: [];

/* ------- CSRF ------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (empty($_SESSION['csrf_pedidos'])) {
  $_SESSION['csrf_pedidos'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_pedidos'];

/* ============================================================
   MIGRAÇÃO local (garante tabelas)
   ============================================================ */
function ensureSchema(PDO $conn) {
    $sqls = [];

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

    foreach ($sqls as $sql) { $conn->exec($sql); }
}
try { $conn = getDatabaseConnection(); ensureSchema($conn); } catch(Throwable $e){}

/* ------- JSON de mapeamento de campos dinâmicos ------- */
$MAPEAMENTO = [
  "Registro Civil" => [
    "2ª de Nascimento" => ["livro","folha","termo","ano","nome_registrado","filiacao","data_evento","cartorio_origem"],
    "Inteiro Teor de Nascimento" => ["livro","folha","termo","ano","nome_registrado","filiacao","data_evento","cartorio_origem"],
    "2ª de Casamento"  => ["livro","folha","termo","ano","nome_noivo","nome_noiva","data_evento","cartorio_origem"],
    "Inteiro Teor de Casamento"  => ["livro","folha","termo","ano","nome_noivo","nome_noiva","data_evento","cartorio_origem"],
    "2ª de Óbito"      => ["livro","folha","termo","ano","nome_falecido","filiacao","data_evento","cartorio_origem"],
    "Inteiro Teor de Óbito"      => ["livro","folha","termo","ano","nome_falecido","filiacao","data_evento","cartorio_origem"]
  ],
  "Pessoas Jurídicas" => [
    "Estatuto"   => ["livro_ficha","numero_registro","partes"],
    "Atas"       => ["livro_ficha","numero_registro","partes"],
    "Outros"     => ["livro_ficha","numero_registro","partes"]
  ],
  "Títulos e Documentos" => [
    "Contratos"  => ["livro_ficha","numero_registro","partes"],
    "Cédulas"    => ["livro_ficha","numero_registro","partes"],
    "Outros"     => ["livro_ficha","numero_registro","partes"]
  ],
  "Registro de Imóveis" => [
    "Matrícula Livro 2"         => ["matricula","proprietario","imovel"],
    "Registro Livro 3"          => ["livro_transcricao","numero_registro","proprietario","imovel"],
    "Ônus"                      => ["matricula","proprietario","descricao_onus"],
    "Penhor"                    => ["matricula","proprietario","descricao_penhor"],
    "Negativa"                  => ["proprietario","criterio_busca"],
    "Situação Jurídica"         => ["matricula","proprietario","imovel"]
  ],
  "Notas" => [
    "Escrituras"   => ["livro","folhas","partes","data_ato"],
    "Testamentos"  => ["livro","folhas","partes","data_ato"],
    "Procurações"  => ["livro","folhas","partes","data_ato"],
    "Ata Notarial" => ["livro","folhas","partes","data_ato"]
  ]
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Novo Pedido de Certidão</title>

<!-- Fontes -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

<!-- Estilos -->
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
<link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
<?php if (file_exists(__DIR__ . '/../style/sweetalert2.min.css')): ?>
<link rel="stylesheet" href="../style/sweetalert2.min.css">
<?php else: ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<?php endif; ?>

<style>
/* ===================== CSS VARIABLES ===================== */
:root {
  /* Colors */
  --brand-primary: #6366f1;
  --brand-primary-light: #818cf8;
  --brand-primary-dark: #4f46e5;
  --brand-success: #10b981;
  --brand-warning: #f59e0b;
  --brand-error: #ef4444;
  --brand-info: #3b82f6;

  /* Backgrounds */
  --bg-primary: #ffffff;
  --bg-secondary: #f8fafc;
  --bg-tertiary: #f1f5f9;
  --bg-elevated: #ffffff;
  
  /* Text */
  --text-primary: #1e293b;
  --text-secondary: #64748b;
  --text-tertiary: #94a3b8;
  --text-inverse: #ffffff;
  
  /* Borders */
  --border-primary: #e2e8f0;
  --border-secondary: #cbd5e1;
  --border-focus: var(--brand-primary);
  
  /* Shadows */
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  
  /* Surfaces */
  --surface-hover: rgba(99, 102, 241, 0.04);
  --surface-active: rgba(99, 102, 241, 0.08);
  
  /* Spacing */
  --space-xs: 4px;
  --space-sm: 8px;
  --space-md: 16px;
  --space-lg: 24px;
  --space-xl: 32px;
  --space-2xl: 48px;
  
  /* Border radius */
  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 14px;
  --radius-xl: 20px;
  --radius-2xl: 28px;
  
  /* Typography */
  --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --font-mono: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
  
  /* Gradients */
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

/* Dark mode variables */
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

.page-hero .hero-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-lg);
}

.page-hero .title-row {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.page-hero .title-icon {
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

.form-check-input{
  margin: 6px!important;
  margin-bottom: 12px!important;
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

.page-hero .hero-title h1 {
  font-size: 32px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--text-primary);
  margin: 0;
  line-height: 1.2;
}

.page-hero .hero-title small {
  font-size: 15px;
  color: var(--text-secondary);
  font-weight: 500;
  margin-top: var(--space-xs);
}

.btn-hero {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  padding: 12px 24px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 15px;
  background: var(--bg-elevated);
  border: 2px solid var(--border-primary);
  color: var(--text-primary);
  box-shadow: var(--shadow-md);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  text-decoration: none;
}

.btn-hero:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-xl);
  border-color: var(--brand-primary);
  color: var(--brand-primary);
  text-decoration: none;
}

.btn-hero i {
  font-size: 16px;
}

@media (max-width: 768px) {
  .page-hero {
    padding: var(--space-xl) 0;
  }
  
  .page-hero .hero-header {
    flex-direction: column;
    align-items: stretch;
    gap: var(--space-md);
  }
  
  .page-hero .title-row {
    flex-direction: column;
    text-align: center;
  }
  
  .page-hero .title-icon {
    width: 56px;
    height: 56px;
  }
  
  .page-hero .hero-title h1 {
    font-size: 24px;
  }
  
  .btn-hero {
    width: 100%;
    justify-content: center;
  }
}

/* ===================== FIELDSET STYLES ===================== */
fieldset {
  background: var(--bg-elevated);
  border: 2px solid var(--border-primary);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  margin-bottom: var(--space-xl);
  box-shadow: var(--shadow-sm);
  transition: all 0.3s ease;
}

fieldset:hover {
  box-shadow: var(--shadow-md);
  border-color: var(--border-secondary);
}

legend {
  padding: 0 var(--space-md);
  font-weight: 700;
  font-size: 18px;
  color: var(--text-primary);
  letter-spacing: -0.01em;
  background: var(--gradient-primary);
  background-clip: text;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  width: auto;
  margin-bottom: 0;
}

/* ===================== FORM CONTROLS ===================== */
.form-control,
.custom-select {
  background: var(--bg-tertiary);
  border: 2px solid var(--border-primary);
  border-radius: var(--radius-md);
  padding: 12px 16px;
  font-size: 15px;
  color: var(--text-primary);
  transition: all 0.3s ease;
  font-family: var(--font-primary);
}

.form-control:focus,
.custom-select:focus {
  background: var(--bg-elevated);
  border-color: var(--brand-primary);
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
  outline: none;
}

.form-control::placeholder {
  color: var(--text-tertiary);
}

.form-control[readonly] {
  background: var(--bg-secondary);
  cursor: not-allowed;
  opacity: 0.7;
}

select.form-control,
.custom-select {
  cursor: pointer;
  background-repeat: no-repeat;
  background-position: right 12px center;
  background-size: 16px 12px;
  padding-right: 40px;
}

/* ===================== LABELS ===================== */
.form-group label {
  font-weight: 600;
  font-size: 14px;
  color: var(--text-secondary);
  margin-bottom: var(--space-sm);
  letter-spacing: 0.01em;
  text-transform: uppercase;
  font-size: 12px;
}

/* ===================== BUTTONS ===================== */
.btn {
  border-radius: var(--radius-md);
  padding: 12px 24px;
  font-weight: 700;
  font-size: 15px;
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

.btn-secondary {
  background: var(--bg-tertiary);
  color: var(--text-primary);
  border: 2px solid var(--border-primary);
}

.btn-secondary:hover {
  background: var(--surface-hover);
  border-color: var(--brand-primary);
  color: var(--brand-primary);
  transform: translateY(-1px);
}

.btn-lg {
  padding: 16px 32px;
  font-size: 16px;
  border-radius: var(--radius-lg);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none !important;
}

.btn-adicionar-manual {
  line-height: 24px;
  margin-left: 10px;
}

/* ===================== TABLE STYLES ===================== */
.table-responsive {
  background: var(--bg-elevated);
  border-radius: var(--radius-lg);
  padding: var(--space-md);
  box-shadow: var(--shadow-sm);
  margin-top: var(--space-lg);
}

.table {
  margin: 0;
  color: var(--text-primary);
}

.table thead th {
  background: var(--bg-tertiary);
  color: var(--text-secondary);
  font-weight: 700;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border: none;
  padding: var(--space-md);
}

.table tbody td {
  padding: var(--space-md);
  vertical-align: middle;
  border-top: 1px solid var(--border-primary);
  font-size: 14px;
}

.table tbody tr:hover {
  background: var(--surface-hover);
}

/* ===================== BADGE STYLES ===================== */
.badge-status {
  font-size: 13px;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  letter-spacing: 0.02em;
}

/* ===================== RESPONSIVE ===================== */
@media (max-width: 575.98px) {
  .stack-sm .form-group {
    margin-bottom: var(--space-md);
  }
  
  fieldset {
    padding: var(--space-lg);
  }
  
  .btn {
    width: 100%;
  }
  
  .form-row {
    flex-direction: column;
  }
  
  .form-row .form-group {
    width: 100% !important;
    flex: none !important;
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

fieldset {
  animation: fadeInUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) backwards;
}

fieldset:nth-child(1) { animation-delay: 0.1s; }
fieldset:nth-child(2) { animation-delay: 0.2s; }
fieldset:nth-child(3) { animation-delay: 0.3s; }

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

    <section class="page-hero">
      <div class="hero-header">
        <div class="title-row">
          <div class="title-icon">
            <i class="fa fa-file-text-o" aria-hidden="true"></i>
          </div>
          <div class="hero-title">
            <h1>Novo Pedido de Certidão</h1>
            <small class="d-block">Preencha os dados e gere a O.S. no final</small>
          </div>
        </div>

        <div class="hero-actions">
          <a href="index.php" class="btn btn-hero">
            <i class="fa fa-list" aria-hidden="true"></i>
            Listar Pedidos
          </a>
        </div>
      </div>
    </section>

    <form id="formPedido" method="post" novalidate>
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">

      <!-- A) ATRIBUIÇÃO / TIPO -->
      <fieldset>
        <legend>Tipo do Pedido</legend>
        <div class="row">
          <div class="form-group col-md-4">
            <label for="atribuicao">Atribuição:</label>
            <select id="atribuicao" name="atribuicao" class="form-control" required>
              <option value="">Selecione...</option>
              <option value="Registro Civil">RCPN (Registro Civil das Pessoas Naturais)</option>
              <option value="Pessoas Jurídicas">RCPJ (Registro Civil das Pessoas Jurídicas)</option>
              <option value="Títulos e Documentos">RTD (Registro de Títulos e Documentos)</option>
              <option value="Registro de Imóveis">RI (Registro de Imóveis)</option>
              <option value="Notas">Notas</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label for="tipo">Tipo:</label>
            <select id="tipo" name="tipo" class="form-control" required disabled>
              <option value="">Selecione a atribuição primeiro</option>
            </select>
          </div>
        </div>

        <!-- Campos dinâmicos -->
        <div id="camposDinamicos" class="row stack-sm"></div>

        <!-- Observação do pedido/registrado -->
        <div class="row">
          <div class="form-group col-12">
            <label for="observacao_pedido">Observação (opcional):</label>
            <textarea id="observacao_pedido" class="form-control" rows="3" placeholder="Inclua detalhes importantes ou instruções adicionais"></textarea>
          </div>
        </div>
      </fieldset>

      <!-- B) DADOS DO REQUERENTE / PORTADOR -->
      <fieldset>
        <legend>Requerente & Portador</legend>
        <div class="row">
          <div class="form-group col-md-6">
            <label for="requerente_nome">Requerente (nome completo):</label>
            <input type="text" class="form-control" id="requerente_nome" name="requerente_nome" required>
          </div>
          <div class="form-group col-md-3">
            <label for="requerente_doc">CPF/CNPJ (requerente):</label>
            <input type="text" class="form-control" id="requerente_doc" name="requerente_doc" placeholder="CPF ou CNPJ">
          </div>
          <div class="form-group col-md-3">
            <label for="requerente_tel">Telefone (celular):</label>
            <input type="text" class="form-control" id="requerente_tel" name="requerente_tel" placeholder="(00) 00000-0000">
          </div>
        </div>
        <div class="row">
          <div class="form-group col-md-6">
            <label for="requerente_email">E-mail:</label>
            <input type="email" class="form-control" id="requerente_email" name="requerente_email">
          </div>
          <div class="form-group col-md-4">
            <label for="portador_nome">Portador (registrado/partes):</label>
            <input type="text" class="form-control" id="portador_nome" name="portador_nome" placeholder="Preenchido automaticamente">
          </div>
          <div class="form-group col-md-2">
            <label for="portador_doc">Doc. Portador:</label>
            <input type="text" class="form-control" id="portador_doc" name="portador_doc">
          </div>
        </div>
      </fieldset>

      <!-- C) O.S. acoplada (com recursos principais) -->
      <fieldset>
        <legend>Ordem de Serviço (Emolumentos)</legend>

        <!-- Marcação de isenção (sem orçamento) -->
        <div class="form-row mb-2">
          <div class="form-group col-md-12">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="isento_ato">
              <label class="form-check-label" for="isento_ato">
                Ato isento (sem orçamento) — não gerar Ordem de Serviço
              </label>
            </div>
          </div>
        </div>

        <!-- Seleção de modelo de OS -->
        <div class="form-row">
          <div class="form-group col-md-12">
            <label for="modelo_orcamento">Carregar Modelo de O.S:</label>
            <select id="modelo_orcamento" class="form-control">
              <option value="">Selecione um modelo...</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-5">
            <label for="descricao_os">Título da OS:</label>
            <input type="text" class="form-control" id="descricao_os" name="descricao_os" placeholder="Será preenchido automaticamente">
          </div>
          <div class="form-group col-md-3">
            <label for="total_os">Valor Total da OS:</label>
            <input type="text" class="form-control" id="total_os" name="total_os" readonly>
          </div>
          <div class="form-group col-md-4 d-flex align-items-end">
            <button type="button" class="btn btn-secondary w-100" onclick="window.open('../os/tabela_de_emolumentos.php')">
              <i class="fa fa-table"></i> Tabela de Emolumentos
            </button>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-3">
            <label for="ato">Código do Ato:</label>
            <input type="text" class="form-control" id="ato" name="ato" pattern="[0-9.]+" placeholder="ex: 14.5.1">
          </div>
          <div class="form-group col-md-2">
            <label for="quantidade">Quantidade:</label>
            <input type="number" class="form-control" id="quantidade" name="quantidade" value="1" min="1">
          </div>
          <div class="form-group col-md-2">
            <label for="desconto_legal">Desconto Legal (%):</label>
            <input type="number" class="form-control" id="desconto_legal" name="desconto_legal" value="0" min="0" max="100">
          </div>
          <div class="form-group col-md-5 d-flex align-items-end">
            <button type="button" class="btn btn-primary me-2 w-50" onclick="buscarAto()">
              <i class="fa fa-search"></i> Buscar Ato
            </button>
            <button type="button" class="btn btn-secondary btn-adicionar-manual w-50" onclick="adicionarAtoManual()">
              <i class="fa fa-i-cursor"></i> Adicionar Manualmente
            </button>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-12">
            <label for="descricao">Descrição:</label>
            <input type="text" class="form-control" id="descricao" name="descricao" readonly>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-2">
            <label for="emolumentos">Emolumentos:</label>
            <input type="text" class="form-control" id="emolumentos" name="emolumentos" readonly>
          </div>
          <div class="form-group col-md-2">
            <label for="ferc">FERC:</label>
            <input type="text" class="form-control" id="ferc" name="ferc" readonly>
          </div>
          <div class="form-group col-md-2">
            <label for="fadep">FADEP:</label>
            <input type="text" class="form-control" id="fadep" name="fadep" readonly>
          </div>
          <div class="form-group col-md-2">
            <label for="femp">FEMP:</label>
            <input type="text" class="form-control" id="femp" name="femp" readonly>
          </div>
          <div class="form-group col-md-2">
            <label for="total">Total:</label>
            <input type="text" class="form-control" id="total" name="total" readonly>
          </div>
          <div class="form-group col-md-2 d-flex align-items-end">
            <button type="button" class="btn btn-success w-100" onclick="adicionarItemOS()">
              <i class="fa fa-plus"></i> Adicionar à OS
            </button>
          </div>
        </div>

        <div class="mt-3">
          <h5 style="color: var(--text-primary); font-weight: 700; margin-bottom: var(--space-md);">Itens da O.S.</h5>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Ato</th>
                  <th>Qtd</th>
                  <th>Desc.(%)</th>
                  <th>Descrição</th>
                  <th>Emol.</th>
                  <th>FERC</th>
                  <th>FADEP</th>
                  <th>FEMP</th>
                  <th>Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="itensTable"></tbody>
            </table>
          </div>
        </div>
      </fieldset>

      <!-- D) AÇÕES -->
      <div class="d-grid gap-2">
        <button id="btnSalvar" type="submit" class="btn btn-primary btn-lg w-100">
          <i class="fa fa-save"></i> Salvar Pedido
        </button>
      </div>
    </form>

  </div>
</main>


<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/jquery-ui.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>
// Fallback do JS do SweetAlert2 se o arquivo local não existir
if (typeof Swal === 'undefined') {
  var s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js';
  document.head.appendChild(s);
}

const ISS_CONFIG = {
  ativo: <?php echo $issAtivo ? 'true':'false'; ?>,
  percentual: <?php echo $issPercentual; ?>,
  descricao: "<?php echo addslashes($issDescricao); ?>"
};
const ATOS_SEM_VALOR = <?php echo json_encode($atosSemValor, JSON_UNESCAPED_UNICODE); ?>;
const MAPEAMENTO = <?php echo json_encode($MAPEAMENTO, JSON_UNESCAPED_UNICODE); ?>;

function toast(type, text){
  Swal.fire({icon:type, title: (type==='error'?'Erro':'Sucesso'), text});
}

/* =================== Helpers de saneamento =================== */
function bindLettersOnly($el){
  // Permite letras (com acentos) e espaços; remove demais.
  const re = /[^A-Za-zÀ-ÖØ-öø-ÿ\s]/g;
  $el.on('input', function(){
    const cur = this.selectionStart;
    const val = this.value;
    const cleaned = val.replace(re,'');
    if (val !== cleaned){
      this.value = cleaned;
      this.setSelectionRange(Math.max(cur-1,0), Math.max(cur-1,0));
    }
  });
}
function bindNumbersOnly($el, maxLen){
  $el.on('input', function(){
    this.value = this.value.replace(/\D/g,'');
    if (maxLen){ this.value = this.value.slice(0, maxLen); }
  });
  if (maxLen){ $el.attr('maxlength', String(maxLen)); }
}

/* Máscaras documentos (utilitários básicos) */
function maskCpf($el){
  $el.mask('000.000.000-00', {clearIfNotMatch:false});
}
function maskCnpj($el){
  $el.mask('00.000.000/0000-00', {clearIfNotMatch:false});
}

/* === Estratégia “clássica”: sem máscara ao focar, decide CPF/CNPJ ao sair === */
function attachCpfCnpjSmart($el, opts){
  const options = Object.assign({ required: false, label: 'Documento' }, opts||{});

  // Ao focar: remove máscara e deixa só números (permite digitar 14 dígitos)
  $el.on('focus', function(){
    try { $(this).unmask(); } catch(e) {}
    this.value = (this.value||'').replace(/\D/g,'');
    $(this).attr('maxlength', '14'); // só números, até 14 (CNPJ)
  });

  // Durante a digitação em foco: manter apenas números
  $el.on('input', function(){
    this.value = (this.value||'').replace(/\D/g,'').slice(0,14);
  });

  // Ao sair: aplica a máscara correta e valida
  $el.on('blur', function(){
    const raw = (this.value||'').replace(/\D/g,'');
    if (!raw){
      if (options.required){ showDocError($(this), `${options.label} é obrigatório.`); }
      return;
    }
    if (raw.length === 11){
      maskCpf($(this));
      $(this).val(raw.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/,'$1.$2.$3-$4'));
      if (!validarCPF(raw)) { showDocError($(this), `${options.label} (CPF) inválido.`); }
      return;
    }
    if (raw.length === 14){
      maskCnpj($(this));
      $(this).val(raw.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/,'$1.$2.$3/$4-$5'));
      if (!validarCNPJ(raw)) { showDocError($(this), `${options.label} (CNPJ) inválido.`); }
      return;
    }
    showDocError($(this), `${options.label} deve ser CPF (11 dígitos) ou CNPJ (14 dígitos).`);
  });
}

/* Validações documentos */
function validarCPF(cpf) {
  cpf = String(cpf||'').replace(/[^\d]+/g, '');
  if (cpf.length !== 11) return false;
  if (!!cpf.match(/^(.)\1+$/)) return false;
  let soma = 0, resto;
  for (let i=1;i<=9;i++) soma += parseInt(cpf.substring(i-1,i))* (11-i);
  resto = (soma*10)%11; if (resto===10||resto===11) resto=0;
  if (resto !== parseInt(cpf.substring(9,10))) return false;
  soma=0;
  for (let i=1;i<=10;i++) soma += parseInt(cpf.substring(i-1,i))*(12-i);
  resto = (soma*10)%11; if (resto===10||resto===11) resto=0;
  if (resto !== parseInt(cpf.substring(10,11))) return false;
  return true;
}
function validarCNPJ(cnpj) {
  cnpj = String(cnpj||'').replace(/[^\d]+/g, '');
  if (cnpj.length !== 14) return false;
  if (!!cnpj.match(/^(.)\1+$/)) return false;
  let tamanho = cnpj.length - 2;
  let numeros = cnpj.substring(0, tamanho);
  let digitos = cnpj.substring(tamanho);
  let soma = 0;
  let pos = tamanho - 7;
  for (let i = tamanho; i >= 1; i--) { soma += numeros.charAt(tamanho - i) * pos--; if (pos < 2) pos = 9; }
  let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
  if (resultado !== parseInt(digitos.charAt(0))) return false;
  tamanho = tamanho + 1;
  numeros = cnpj.substring(0, tamanho);
  soma = 0; pos = tamanho - 7;
  for (let i = tamanho; i >= 1; i--) { soma += numeros.charAt(tamanho - i) * pos--; if (pos < 2) pos = 9; }
  resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
  if (resultado !== parseInt(digitos.charAt(1))) return false;
  return true;
}
function validarCpfOuCnpj(raw){
  const v = String(raw||'').replace(/\D/g,'');
  if (v.length === 11) return validarCPF(v);
  if (v.length === 14) return validarCNPJ(v);
  return false;
}
function showDocError($el, msg){
  toast('error', msg);
  try { $el.unmask(); } catch(e) {}
  $el.val('').focus();
}

/* Aplica políticas de caracteres aos campos dinâmicos quando renderizam */
function enforceCharacterPolicies(){
  // Letras apenas
  ['#nome_registrado','#proprietario','#nome_noivo','#nome_noiva','#nome_falecido'].forEach(sel=>{
    if ($(sel).length){ bindLettersOnly($(sel)); }
  });

  // Números apenas
  if ($('#termo').length){ bindNumbersOnly($('#termo')); }
  if ($('#termos').length){ bindNumbersOnly($('#termos')); }
  if ($('#folha').length){ bindNumbersOnly($('#folha'), 3); }
  if ($('#folhas').length){ bindNumbersOnly($('#folhas')); }
}

/* Renderiza os campos de documentos exigidos por atribuição/tipo */
function renderDocSpecificFields(a, t) {
  const blocks = [];

  if (a === 'Registro Civil') {
    if (/Nascimento/i.test(t) || /Óbito|Obito/i.test(t)) {
      blocks.push(`
        <div class="form-group col-md-4">
          <label for="cpf_registrado">CPF do Registrado:</label>
          <input type="text" class="form-control" id="cpf_registrado" name="ref[cpf_registrado]" placeholder="000.000.000-00">
        </div>
      `);
    } else if (/Casamento/i.test(t)) {
      blocks.push(`
        <div class="form-group col-md-4">
          <label for="cpf_noivo">CPF do Noivo:</label>
          <input type="text" class="form-control" id="cpf_noivo" name="ref[cpf_noivo]" placeholder="000.000.000-00">
        </div>
        <div class="form-group col-md-4">
          <label for="cpf_noiva">CPF da Noiva:</label>
          <input type="text" class="form-control" id="cpf_noiva" name="ref[cpf_noiva]" placeholder="000.000.000-00">
        </div>
      `);
    }
  }

  if (a === 'Registro de Imóveis') {
    blocks.push(`
      <div class="form-group col-md-4">
        <label for="doc_proprietario">CPF/CNPJ do Proprietário:</label>
        <input type="text" class="form-control" id="doc_proprietario" name="ref[doc_proprietario]" placeholder="CPF ou CNPJ">
      </div>
    `);
  }

  if (a === 'Títulos e Documentos') {
    blocks.push(`
      <div class="form-group col-md-4">
        <label for="doc_partes">CPF/CNPJ das Partes:</label>
        <input type="text" class="form-control" id="doc_partes" name="ref[doc_partes]" placeholder="CPF ou CNPJ">
      </div>
    `);
  }

  if (a === 'Pessoas Jurídicas') {
    blocks.push(`
      <div class="form-group col-md-4">
        <label for="cnpj_pj">CNPJ da Pessoa Jurídica:</label>
        <input type="text" class="form-control" id="cnpj_pj" name="ref[cnpj_pj]" placeholder="00.000.000/0000-00">
      </div>
    `);
  }

  if (a === 'Notas') {
    blocks.push(`
      <div class="form-group col-md-4">
        <label for="doc_partes_notas">CPF/CNPJ das Partes:</label>
        <input type="text" class="form-control" id="doc_partes_notas" name="ref[doc_partes_notas]" placeholder="CPF ou CNPJ">
      </div>
    `);
  }

  return blocks.join('');
}

/* Aplica máscaras e validações aos campos de documentos específicos */
function applyDocMasksAndValidation(){
  // Registro Civil – nascimento/óbito (CPF fixo)
  if ($('#cpf_registrado').length){
    maskCpf($('#cpf_registrado'));
    $('#cpf_registrado').on('blur', function(){
      const raw = (this.value||'').replace(/\D/g,'');
      if (this.value && !validarCPF(raw)) {
        showDocError($(this), 'CPF do registrado inválido.');
      }
    });
  }
  // Registro Civil – casamento (CPF fixo)
  if ($('#cpf_noivo').length){
    maskCpf($('#cpf_noivo'));
    $('#cpf_noivo').on('blur', function(){
      const raw = (this.value||'').replace(/\D/g,'');
      if (this.value && !validarCPF(raw)) {
        showDocError($(this), 'CPF do noivo inválido.');
      }
    });
  }
  if ($('#cpf_noiva').length){
    maskCpf($('#cpf_noiva'));
    $('#cpf_noiva').on('blur', function(){
      const raw = (this.value||'').replace(/\D/g,'');
      if (this.value && !validarCPF(raw)) {
        showDocError($(this), 'CPF da noiva inválido.');
      }
    });
  }

  // RI – proprietário (CPF/CNPJ dinâmico)
  if ($('#doc_proprietario').length){
    attachCpfCnpjSmart($('#doc_proprietario'), {label:'Documento do proprietário'});
  }
  // RTD – partes (CPF/CNPJ dinâmico)
  if ($('#doc_partes').length){
    attachCpfCnpjSmart($('#doc_partes'), {label:'Documento das partes'});
  }
  // Pessoas Jurídicas – CNPJ fixo
  if ($('#cnpj_pj').length){
    $('#cnpj_pj').on('focus', function(){ try{$(this).unmask();}catch(e){} this.value = (this.value||'').replace(/\D/g,'').slice(0,14); $(this).attr('maxlength','14'); });
    $('#cnpj_pj').on('input', function(){ this.value = (this.value||'').replace(/\D/g,'').slice(0,14); });
    $('#cnpj_pj').on('blur', function(){
      const raw = (this.value||'').replace(/\D/g,'');
      if (!raw) return;
      maskCnpj($(this));
      if (!validarCNPJ(raw)) { showDocError($(this), 'CNPJ inválido.'); }
      else $(this).val(raw.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/,'$1.$2.$3/$4-$5'));
    });
  }
  // Notas – partes (CPF/CNPJ dinâmico)
  if ($('#doc_partes_notas').length){
    attachCpfCnpjSmart($('#doc_partes_notas'), {label:'Documento das partes'});
  }
}

let isSubmittingPedido = false;

/* =================== DOM Ready =================== */
$(function(){
  // aplica o modo salvo (o toggle fica no menu)
  $.get('../load_mode.php', function(mode){
    $('body').removeClass('light-mode dark-mode').addClass(mode);
  });

  // máscaras de moeda (inclui TOTAL)
  $('#base_calculo,#emolumentos,#ferc,#fadep,#femp,#total_os,#total').mask('#.##0,00',{reverse:true});

  // Telefone: sempre celular com nono dígito
  $('#requerente_tel').mask('(00) 00000-0000');

  // Regras de letras apenas no nome do requerente
  bindLettersOnly($('#requerente_nome'));

  // CPF/CNPJ do REQUERENTE – estratégia foco/blur
  attachCpfCnpjSmart($('#requerente_doc'), {label:'Documento do requerente'});

  // Popular tipos conforme atribuição
  $('#atribuicao').on('change', function(){
    const a = $(this).val();
    $('#tipo').prop('disabled', !a).empty().append('<option value="">Selecione...</option>');
    if (MAPEAMENTO[a]) { Object.keys(MAPEAMENTO[a]).forEach(t=>{ $('#tipo').append(new Option(t, t)); }); }
    $('#camposDinamicos').empty();
    updateTituloOS();
    updatePortadorAuto(); // reset/inferir
  });

  // render de campos dinâmicos + documentos + observação (observação já está no DOM)
  $('#tipo').on('change', function(){
    const a = $('#atribuicao').val();
    const t = $(this).val();
    const cont = $('#camposDinamicos').empty();
    if (MAPEAMENTO[a] && MAPEAMENTO[a][t]) {
      MAPEAMENTO[a][t].forEach(campo=>{
        const label = campo.replace(/_/g,' ').replace(/\b\w/g, s=>s.toUpperCase());
        cont.append(`
          <div class="form-group col-md-4">
            <label for="${campo}">${label}:</label>
            <input type="text" class="form-control" id="${campo}" name="ref[${campo}]">
          </div>`);
      });

      // --- Campos de documentos adicionais por atribuição/tipo ---
      cont.append( renderDocSpecificFields(a, t) );

      // aplica máscaras específicas após render
      applyDynamicMasks();
      applyDocMasksAndValidation();

      // aplica políticas de caracteres
      enforceCharacterPolicies();

      // atualizar título e portador quando os campos dinâmicos mudarem
      $('#camposDinamicos').on('input', 'input', function(){
        updateTituloOS();
        updatePortadorAuto();
      });
    }
    updateTituloOS();
    updatePortadorAuto();
  });

  // marca se o usuário mexeu manualmente no portador (não sobrescrever depois)
  $('#portador_nome').on('input', function(){ $(this).data('manual', true); });
  $('#requerente_nome').on('input', updateTituloOS);

  /* =========================== Recursos OS (modelos/drag/ISS) =========================== */
  
  function setOSDisabled(disabled){
  // Desabilita todos os controles da seção de O.S.
  $('#modelo_orcamento, #descricao_os, #total_os, #ato, #quantidade, #desconto_legal, #descricao, #emolumentos, #ferc, #fadep, #femp, #total')
    .prop('disabled', disabled);
  // Botões
  $('button[onclick="buscarAto()"], button[onclick="adicionarAtoManual()"], button[onclick="adicionarItemOS()"]')
    .prop('disabled', disabled);
  // Tabela de itens (limpa se desabilitar)
  if (disabled){
    $('#itensTable').empty();
    atualizarISS();
    renumerar();
    $('#total_os').val('0,00');
  }
}

$('#isento_ato').on('change', function(){
  const isento = $(this).is(':checked');
  setOSDisabled(isento);
});

// Sortable (arrastar para reordenar)
$("#itensTable").sortable({
  placeholder: "ui-state-highlight",
  update: function(){ renumerar(); atualizarISS(); }
}).disableSelection();

// Carregar lista de modelos no select
$.ajax({
  url: '../os/listar_todos_modelos.php',
  method: 'GET',
  dataType: 'json'
}).done(function(resp){
  if (resp && resp.modelos){
    resp.modelos.forEach(function(m){
      $('#modelo_orcamento').append(new Option(m.nome_modelo, m.id));
    });
  }
});

// Estado inicial (por via das dúvidas)
setOSDisabled($('#isento_ato').is(':checked'));

  // Ao escolher um modelo, carrega itens
  $('#modelo_orcamento').on('change', function(){
    const id = $(this).val();
    if (!id) return;
    carregarModeloSelecionado(id);
  });

  // SUBMIT AJAX
  $('#formPedido').on('submit', function(e){
  e.preventDefault();

  // bloqueia cliques/submit duplos
  if (isSubmittingPedido) return;
  isSubmittingPedido = true;

  const payload = gatherFormData();
  if (!payload) { 
    isSubmittingPedido = false; 
    return; 
  }

  const $btn = $('#btnSalvar');
  const originalHtml = $btn.html();
  $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Salvando...');

  $.ajax({
    url: 'salvar_pedido.php',
    method: 'POST',
    data: payload,
    dataType: 'text',
    success: function(resText){
        let r = null;
        try{
          r = typeof resText === 'object' ? resText : JSON.parse(resText);
        }catch(e){
          const m = String(resText||'').match(/\{[\s\S]*\}$/);
          if (m){ try{ r = JSON.parse(m[0]); }catch(e2){} }
        }
        if (!r){
          console.error('Resposta do servidor:', resText);
          toast('error','Falha ao interpretar a resposta do servidor.');
          return;
        }
        if (r.error){
          toast('error', r.error);
          return;
        }
        if (r.success){
          const delivery = r.api_delivery || {};
          const pedidoId = r.id;
          if (delivery.attempted && !delivery.delivered){
            Swal.fire({
              icon: 'warning',
              title: 'Pedido salvo, mas houve falha ao enviar para a API',
              html: `
                <div class="text-start">
                  <p class="mb-1"><strong>Detalhes:</strong></p>
                  <ul class="mb-2"><li>HTTP: <code>${delivery.http_code || 0}</code></li></ul>
                  <p class="mb-0">Deseja tentar reenviar agora?</p>
                </div>`,
              showCancelButton: true,
              confirmButtonText: 'Reenviar agora',
              cancelButtonText: 'Ver pedido'
            }).then((res)=>{
              if (res.isConfirmed){
                Swal.fire({title:'Reenviando...', html:'Tentando entregar mensagens pendentes.', allowOutsideClick:false, didOpen:()=>{ Swal.showLoading(); }});
                $.post('reenvio_api.php', { pedido_id: pedidoId }, function(resp){
                  if (resp && resp.success){
                    const ok = (resp.failed === 0);
                    Swal.fire({icon: ok ? 'success' : 'warning', title: ok ? 'Reenvio concluído!' : 'Reenviado parcialmente', text: `Entregues: ${resp.delivered||0}${resp.failed>0?` • Falhas: ${resp.failed}`:''}`})
                      .then(()=>{ window.location.href = 'visualizar_pedido.php?id=' + pedidoId; });
                  } else {
                    Swal.fire({icon:'error', title:'Erro', text:(resp && resp.error) ? resp.error : 'Falha no reenvio.'})
                      .then(()=>{ window.location.href = 'visualizar_pedido.php?id=' + pedidoId; });
                  }
                }, 'json').fail(function(xhr){
                  console.error(xhr.responseText);
                  Swal.fire({icon:'error', title:'Erro', text:'Não foi possível contatar o servidor.'})
                    .then(()=>{ window.location.href = 'visualizar_pedido.php?id=' + pedidoId; });
                });
              } else {
                window.location.href = 'visualizar_pedido.php?id=' + pedidoId;
              }
            });
          } else if (!delivery.attempted){
            Swal.fire({icon:'info', title:'Pedido salvo', text:'A API não está configurada. Você poderá reenviar depois pela tela do pedido.', confirmButtonText:'Abrir pedido'})
              .then(()=>{ window.location.href = 'visualizar_pedido.php?id=' + pedidoId; });
          } else {
            Swal.fire({icon:'success', title:'Sucesso', text:'Pedido salvo e enviado para a API com sucesso!'})
              .then(()=>{ window.location.href = 'visualizar_pedido.php?id=' + pedidoId; });
          }
        } else {
          toast('error','Resposta inesperada do servidor.');
        }
      },
      error: function(xhr){
        console.error(xhr.responseText);
        toast('error','Falha na requisição.');
      },
      complete: function(){
        // libera novamente só ao finalizar a requisição
        isSubmittingPedido = false;
        $btn.prop('disabled', true).html(originalHtml);
      }
    });
  });
});

/* =================== PORTADOR & CAMPOS DINÂMICOS =================== */

/* Gera automaticamente o Portador (registrado/partes) se o usuário não digitou manualmente */
function updatePortadorAuto(){
  const $p = $('#portador_nome');
  const manual = $p.data('manual') === true;
  if (manual) return;

  const atr  = $('#atribuicao').val() || '';
  const tipo = $('#tipo').val() || '';
  let valor  = '';

  if (atr === 'Registro Civil') {
    if (/Nascimento/i.test(tipo)) {
      valor = $('#nome_registrado').val() || '';
    } else if (/Casamento/i.test(tipo)) {
      const noivo = $('#nome_noivo').val() || '';
      const noiva = $('#nome_noiva').val() || '';
      valor = [noivo, noiva].filter(Boolean).join(' e ');
    } else if (/Óbito|Obito/i.test(tipo)) {
      valor = $('#nome_falecido').val() || '';
    }
  } else if (atr === 'Pessoas Jurídicas' || atr === 'Títulos e Documentos' || atr === 'Notas') {
    valor = $('#partes').val() || '';
    } else if (atr === 'Registro de Imóveis') {
    valor = $('#proprietario').val() || '';
  }

  if (valor) { $p.val(valor); }
}

/* Aplica máscaras específicas nos campos dinâmicos (datas/ano) */
function applyDynamicMasks(){
  if ($('#data_evento').length){ $('#data_evento').mask('00/00/0000'); }
  if ($('#data_ato').length){ $('#data_ato').mask('00/00/0000'); }
  if ($('#ano').length){ $('#ano').mask('0000'); }
}

/* Monta automaticamente o Título da O.S. */
function updateTituloOS(){
  const atr  = $('#atribuicao').val() || '';
  const tipo = $('#tipo').val() || '';
  if (!atr || !tipo) { $('#descricao_os').val(''); return; }

  let detalhe = '';

  if (atr === 'Registro Civil') {
    if (/Nascimento/i.test(tipo)) {
      detalhe = $('#nome_registrado').val() || '';
    } else if (/Casamento/i.test(tipo)) {
      const noivo = $('#nome_noivo').val() || '';
      const noiva = $('#nome_noiva').val() || '';
      detalhe = [noivo, noiva].filter(Boolean).join(' & ');
    } else if (/Óbito|Obito/i.test(tipo)) {
      detalhe = $('#nome_falecido').val() || '';
    }
  } else if (atr === 'Pessoas Jurídicas' || atr === 'Títulos e Documentos' || atr === 'Notas') {
    detalhe = $('#partes').length ? ($('#partes').val() || '') : '';
  } else if (atr === 'Registro de Imóveis') {
      const m   = $('#matricula').val()    || '';
      const p   = $('#proprietario').val() || '';
      const imv = $('#imovel').val()       || '';

      if (m && p) {
          detalhe = `Matrícula: ${m} – Proprietário: ${p}`;
      } else if (m) {
          detalhe = `Matrícula: ${m}`;
      } else if (p) {
          detalhe = `Proprietário: ${p}`;
      } else if (imv) {
          detalhe = `Imóvel: ${imv}`;
      } else {
          detalhe = '';
      }
  }

  const base   = `Certidão ${tipo} (${atr})`;
  const titulo = detalhe ? `${base} – ${detalhe}` : base;
  $('#descricao_os').val(titulo);
}

/* =================== O.S. – Funções utilitárias =================== */

function buscarAto(){
  const ato = $('#ato').val();
  const quantidade = parseInt($('#quantidade').val()||'1',10);
  const descontoLegal = parseFloat($('#desconto_legal').val()||'0');

  $.get('../os/buscar_ato.php', {ato: ato}, function(resp){
    if (resp.error) return toast('error', resp.error);
    try{
      let emolumentos = parseFloat(resp.EMOLUMENTOS) * quantidade;
      let ferc = parseFloat(resp.FERC) * quantidade;
      let fadep = parseFloat(resp.FADEP) * quantidade;
      let femp = parseFloat(resp.FEMP) * quantidade;

      const fator = (1 - (descontoLegal/100));
      emolumentos*=fator; ferc*=fator; fadep*=fator; femp*=fator;

      if (ATOS_SEM_VALOR.includes(String(ato).trim())) { emolumentos=ferc=fadep=femp=0; }

      const total = emolumentos + ferc + fadep + femp;

      $('#descricao').val(resp.DESCRICAO);
      $('#emolumentos').val(emolumentos.toFixed(2).replace('.',','));
      $('#ferc').val(ferc.toFixed(2).replace('.',','));
      $('#fadep').val(fadep.toFixed(2).replace('.',','));
      $('#femp').val(femp.toFixed(2).replace('.',','));
      $('#total').val(total.toFixed(2).replace('.',','));
    }catch(e){ toast('error','Erro ao processar o ato.'); }
  }, 'json').fail(function(xhr){ console.error(xhr.responseText); toast('error','Erro ao buscar ato.'); });
}

function adicionarAtoManual(){
  $('#ato').val('0');
  $('#descricao').prop('readonly',false).val('');
  $('#emolumentos,#ferc,#fadep,#femp').prop('readonly',false).val('0,00');
  $('#total').prop('readonly',false).val('0,00'); // permitir digitar o total do ato manual
}

function adicionarItemOS(){
  if ($('#isento_ato').is(':checked')){
    return toast('error','Ato isento: não é possível adicionar itens à O.S.');
  }
  const ato = $('#ato').val();
  const qtd = parseInt($('#quantidade').val()||'1',10);
  const desc = parseFloat($('#desconto_legal').val()||'0');
  const descTxt = isNaN(desc)?'0':String(desc);

  const descStr = $('#descricao').val();
  const emol = toFloat($('#emolumentos').val());
  const ferc = toFloat($('#ferc').val());
  const fadep = toFloat($('#fadep').val());
  const femp = toFloat($('#femp').val());
  let totalTyped = toFloat($('#total').val());

  // Se o usuário digitou um TOTAL manual válido, prioriza-o; senão, calcula a partir das partes
  let total = !isNaN(totalTyped) && totalTyped > 0 ? totalTyped : (emol+ferc+fadep+femp);

  const codigoAto = String(ato||'').trim();
  const isExcecao = ATOS_SEM_VALOR.includes(codigoAto);
  if ((isNaN(total) || total <= 0) && !isExcecao) { return toast('error','Informe um Total válido ou preencha os valores do ato.'); }

  const ordem = $('#itensTable tr').length + 1;

  $('#itensTable').append(`
    <tr>
      <td>${ordem}</td>
      <td>${escapeHtml(ato)}</td>
      <td>${qtd}</td>
      <td>${descTxt}%</td>
      <td>${escapeHtml(descStr)}</td>
      <td>${fmt(totalPart(emol))}</td>
      <td>${fmt(totalPart(ferc))}</td>
      <td>${fmt(totalPart(fadep))}</td>
      <td>${fmt(totalPart(femp))}</td>
      <td>${fmt(total)}</td>
      <td><button type="button" class="btn btn-sm btn-danger" onclick="removerItem(this)"><i class="fa fa-trash"></i></button></td>
    </tr>
  `);
  atualizarISS();
  renumerar();
  // limpa campos do topo
  $('#ato').val(''); $('#quantidade').val('1'); $('#desconto_legal').val('0');
  $('#descricao').val('').prop('readonly',true);
  $('#emolumentos,#ferc,#fadep,#femp').val('').prop('readonly',true);
  $('#total').val('').prop('readonly',true);
}

function removerItem(btn){
  $(btn).closest('tr').remove();
  renumerar();
  atualizarISS();
}

function renumerar(){
  $('#itensTable tr').each(function(i){ $(this).find('td:first').text(i+1); });
}

function totalPart(x){ return isNaN(x)?0:x; }
function toFloat(v){ return parseFloat(String(v).replace(/\./g,'').replace(',','.')); }
function fmt(n){ return (isNaN(n)?0:n).toFixed(2).replace('.',','); }

function atualizarISS(){
  // soma emolumentos de itens não-ISS (coluna 5 = index 5)
  let emolTotal = 0;
  $('#itensTable tr').each(function(){
    const isISS = $(this).attr('id') === 'ISS_ROW';
    if (!isISS) {
      const em = toFloat($(this).find('td').eq(5).text());
      emolTotal += isNaN(em)?0:em;
    }
  });

  if (ISS_CONFIG.ativo) {
    const baseISS = emolTotal * 0.88;
    const valorISS = baseISS * (ISS_CONFIG.percentual/100);
    const linha = $('#ISS_ROW');
    if (linha.length===0) {
      const ordem = $('#itensTable tr').length + 1;
      $('#itensTable').append(`
        <tr id="ISS_ROW" data-tipo="iss">
          <td>${ordem}</td><td>ISS</td><td>1</td><td>0%</td>
          <td>${escapeHtml(ISS_CONFIG.descricao)}</td>
          <td>${fmt(valorISS)}</td><td>0,00</td><td>0,00</td><td>0,00</td>
          <td>${fmt(valorISS)}</td>
          <td><span class="text-muted"><i class="fa fa-lock"></i></span></td>
        </tr>
      `);
    } else {
      linha.find('td').eq(5).text(fmt(valorISS));
      linha.find('td').eq(9).text(fmt(valorISS));
    }
  } else {
    $('#ISS_ROW').remove();
  }

  // total geral (coluna index 9)
  let totalOS = 0;
  $('#itensTable tr').each(function(){
    const t = toFloat($(this).find('td').eq(9).text());
    totalOS += isNaN(t)?0:t;
  });
  $('#total_os').val(fmt(totalOS));
}

function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

/* Carrega um modelo de OS e injeta os itens */
function carregarModeloSelecionado(idModelo){
  if (!idModelo) return;
  $.ajax({
    url: '../os/carregar_modelo_orcamento.php',
    type: 'GET',
    data: { id: idModelo },
    dataType: 'json'
  }).done(function(response){
    if (response.error){ toast('error', response.error); return; }
    if (response.itens){
      response.itens.forEach(function(item){
        const emolumentos = parseFloat((item.emolumentos || '0').replace(',', '.'));
        const ferc        = parseFloat((item.ferc        || '0').replace(',', '.'));
        const fadep       = parseFloat((item.fadep       || '0').replace(',', '.'));
        const femp        = parseFloat((item.femp        || '0').replace(',', '.'));
        const total       = parseFloat((item.total       || '0').replace(',', '.'));
        const ordem       = $('#itensTable tr').length + 1;
        $('#itensTable').append(`
          <tr>
            <td>${ordem}</td>
            <td>${escapeHtml(item.ato)}</td>
            <td>${escapeHtml(item.quantidade)}</td>
            <td>${escapeHtml(item.desconto_legal)}%</td>
            <td>${escapeHtml(item.descricao)}</td>
            <td>${fmt(emolumentos)}</td>
            <td>${fmt(ferc)}</td>
            <td>${fmt(fadep)}</td>
            <td>${fmt(femp)}</td>
            <td>${fmt(total)}</td>
            <td><button type="button" title="Remover" class="btn btn-delete btn-sm" onclick="removerItem(this)"><i class="fa fa-trash"></i></button></td>
          </tr>
        `);
      });
      atualizarISS();
      renumerar();
    }
  }).fail(function(xhr){
    console.error(xhr.responseText);
    toast('error','Erro ao carregar o modelo selecionado.');
  });
}

/* Junta todos os dados para enviar ao servidor */
function gatherFormData(){
  const atribuicao = $('#atribuicao').val();
  const tipo = $('#tipo').val();
  if (!atribuicao || !tipo){ toast('error','Selecione atribuição e tipo.'); return null; }

  // refs dinâmicas (inclui documentos específicos + observação)
  const refs = {};
  $('#camposDinamicos input, #camposDinamicos textarea, #camposDinamicos select').each(function(){
    const id = $(this).attr('id');
    const name = $(this).attr('name') || id;
    // Se já estiver no formato ref[...], apenas pega o valor
    if (name && name.startsWith('ref[')) {
      refs[id] = $(this).val();
    } else if (id) {
      refs[id] = $(this).val();
    }
  });
  const obsExtra = $('#observacao_pedido').val();
  if (obsExtra) refs['observacao'] = obsExtra;

  // OS itens
    const isento = $('#isento_ato').is(':checked');

  // OS itens (somente se NÃO isento)
  const itens = [];
  if (!isento){
    $('#itensTable tr').each(function(idx){
      const tds = $(this).find('td');
      itens.push({
        ato: tds.eq(1).text(),
        quantidade: tds.eq(2).text(),
        desconto_legal: tds.eq(3).text().replace('%',''),
        descricao: tds.eq(4).text(),
        emolumentos: tds.eq(5).text(),
        ferc:        tds.eq(6).text(),
        fadep:       tds.eq(7).text(),
        femp:        tds.eq(8).text(),
        total:       tds.eq(9).text(),
        ordem_exibicao: (idx+1)
      });
    });
    if (itens.length===0){
      toast('error','Adicione ao menos um item na O.S. ou marque "Ato isento".');
      return null;
    }
  }

  const payload = {
    csrf: $('input[name="csrf"]').val(),
    atribuicao, tipo,
    base_calculo: $('#base_calculo').val(),
    requerente_nome: $('#requerente_nome').val(),
    requerente_doc: $('#requerente_doc').val(),
    requerente_email: $('#requerente_email').val(),
    requerente_tel: $('#requerente_tel').val(),
    portador_nome: $('#portador_nome').val(),
    portador_doc: $('#portador_doc').val(),
    referencias_json: JSON.stringify(refs),
    descricao_os: $('#descricao_os').val(),
    total_os: isento ? '0,00' : $('#total_os').val(),
    isento_ato: isento ? '1' : '0',
    itens: JSON.stringify(itens)
  };
  return payload;
}
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
