<?php
// pedidos_certidao/visualizar_pedido.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/* Sessão segura (evita notice) */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (empty($_SESSION['csrf_pedidos'])) { $_SESSION['csrf_pedidos'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_pedidos'];

/* ID do pedido */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('ID inválido'); }

/* Migração mínima das tabelas necessárias */
function ensureSchema(PDO $conn){
  // logs/outbox (já existentes neste módulo)
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

  // ===== Novas tabelas de anexos/imagens =====
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

if ($osId > 0) {
  $q = $conn->prepare("SELECT status, total_os, base_de_calculo FROM ordens_de_servico WHERE id=? LIMIT 1");
  $q->execute([$osId]);
  if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
    $osStatus = $row['status'] ?? null;
    if (isset($row['total_os'])) $totalOS = (float)$row['total_os'];
  }

  $q = $conn->prepare("SELECT SUM(total_pagamento) AS t FROM pagamento_os WHERE ordem_de_servico_id=?");
  $q->execute([$osId]);
  $totalPag = (float)($q->fetchColumn() ?: 0);

  $q = $conn->prepare("SELECT SUM(total_devolucao) AS t FROM devolucao_os WHERE ordem_de_servico_id=?");
  $q->execute([$osId]);
  $totalDev = (float)($q->fetchColumn() ?: 0);
}

/* Auto-cancelamento se O.S. cancelada */
$fezAutoCancelamento = false;
try {
  if ($osStatus && (strcasecmp($osStatus,'Cancelado')===0 || strcasecmp($osStatus,'Cancelada')===0)) {
    if ($pedidoStatus !== 'cancelada') {
      $conn->beginTransaction();
      $username = $_SESSION['username'] ?? 'sistema';

      // Atualiza pedido
      $conn->prepare("
        UPDATE pedidos_certidao
           SET status='cancelada',
               cancelado_motivo=COALESCE(cancelado_motivo,'Cancelamento de O.S'),
               atualizado_por=:user,
               atualizado_em=NOW()
         WHERE id=:id
      ")->execute([':user'=>$username, ':id'=>$id]);

      // Log
      $ip = $_SERVER['REMOTE_ADDR'] ?? null;
      $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
      $conn->prepare("
        INSERT INTO pedidos_certidao_status_log (pedido_id, status_anterior, novo_status, observacao, usuario, ip, user_agent)
        VALUES (?,?,?,?,?,?,?)
      ")->execute([$id, $pedidoStatus, 'cancelada', 'Cancelamento de O.S', $username, $ip, $ua]);

      // Outbox
      $apiConfig   = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];
      $API_KEY     = $apiConfig['api_key']     ?? null;
      $HMAC_SECRET = $apiConfig['hmac_secret'] ?? null;

      $payload = [
        'protocolo'     => $pedido['protocolo'],
        'token_publico' => $pedido['token_publico'],
        'status'        => 'cancelada',
        'atualizado_em' => date('c'),
        'observacao'    => true
      ];
      $timestamp = (int) round(microtime(true) * 1000);
      $requestId = bin2hex(random_bytes(12));
      $signature = null;
      if ($HMAC_SECRET) {
        $base = $timestamp . json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $base, $HMAC_SECRET);
      }
      $conn->prepare("
        INSERT INTO api_outbox (topic, protocolo, token_publico, payload_json, api_key, signature, timestamp_utc, request_id)
        VALUES ('status_atualizado',?,?,?,?,?,?,?)
      ")->execute([
        $pedido['protocolo'],
        $pedido['token_publico'],
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        $API_KEY,
        $signature,
        $timestamp,
        $requestId
      ]);

      $conn->commit();
      $fezAutoCancelamento = true;

      // Recarrega pedido e logs
      $stmt = $conn->prepare("SELECT * FROM pedidos_certidao WHERE id=?");
      $stmt->execute([$id]);
      $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
      $pedidoStatus = $pedido['status'] ?? 'cancelada';

      $logsStmt = $conn->prepare("SELECT * FROM pedidos_certidao_status_log WHERE pedido_id=? ORDER BY id DESC");
      $logsStmt->execute([$id]);
      $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
  }
} catch (Throwable $e) {
  // silencioso
}

/* Status “visual” da O.S. */
$osBadgeText  = '';
$osBadgeClass = '';
if ($osStatus === 'Cancelado' || $osStatus === 'Cancelada') {
  $osBadgeText = 'Cancelada';
  $osBadgeClass = 'situacao-cancelado';
} elseif ($totalPag > 0) {
  $osBadgeText = 'Pago (Depósito Prévio)';
  $osBadgeClass = 'situacao-pago';
} else {
  $osBadgeText = 'Ativa (Pendente de Pagamento)';
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
} elseif (!($totalPag > 0)) {
  $bloquearAlteracao = true;
  $bloqueioMsg = 'Para alterar o status do pedido é necessário que a O.S. esteja com Depósito Prévio pago.';
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
  $st = $conn->prepare("
    SELECT id, topic, delivered_at, retries, last_error, timestamp_utc, payload_json, criado_em
     FROM api_outbox
     WHERE token_publico = ?
     ORDER BY id DESC
     LIMIT 50
  ");
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
} catch(Throwable $e){
  // silencioso
}

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
<link rel="icon" href="../style/img/favicon.png" type="image/png">

<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../style/css/style.css">

<?php
// Material Design Icons: local ou CDN
$mdiCssLocal = __DIR__ . '/../style/css/materialdesignicons.min.css';
$mdiWoff2    = __DIR__ . '/../style/fonts/materialdesignicons-webfont.woff2';
if (file_exists($mdiCssLocal) && file_exists($mdiWoff2)) {
  echo '<link rel="stylesheet" href="../style/css/materialdesignicons.min.css">' . PHP_EOL;
} else {
  echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">' . PHP_EOL;
}
// SweetAlert2 CSS: local ou CDN
$swalCssLocal = __DIR__ . '/../style/sweetalert2.min.css';
if (file_exists($swalCssLocal)) {
  echo '<link rel="stylesheet" href="../style/sweetalert2.min.css">' . PHP_EOL;
} else {
  echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">' . PHP_EOL;
}
?>

<!-- Dropzone (drag & drop elegante) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone@5/dist/min/dropzone.min.css">

<style>
/* ====== HERO ====== */
.page-hero{
  background: linear-gradient(135deg, rgba(13,110,253,.08), rgba(0,0,0,0));
  border-radius:16px; padding:18px 20px; margin: 8px 0 22px;
}
.title-row{ display:flex; align-items:center; gap:12px; }
.title-icon{
  width:48px; height:48px; border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  background: rgba(13,110,253,.12);
}
.title-icon i{ font-size:22px; color:#0d6efd; }

/* ====== BADGES DE STATUS ====== */
.badge-status{ font-size:.85rem; border-radius:5px; padding:.45em .65em; letter-spacing:.2px; text-transform: uppercase; }
.timeline-status{ text-transform: uppercase; }
.status-pill{ display:inline-flex; align-items:center; gap:8px; }
.dot{ width:10px; height:10px; border-radius:50%; display:inline-block; }
.dot.pendente{ background:#ffc107; }
.dot.em_andamento{ background:#17a2b8; }
.dot.emitida{ background:#28a745; }
.dot.entregue{ background:#6f42c1; }
.dot.cancelada{ background:#dc3545; }
.badge.pendente{ background:#ffd65a; color:#3b3b3b; }
.badge.em_andamento{ background:#17a2b8; color:#fff; }
.badge.emitida{ background:#28a745; color:#fff; }
.badge.entregue{ background:#6f42c1; color:#fff; }
.badge.cancelada{ background:#dc3545; color:#fff; }

/* ====== INDICADORES DA O.S. ====== */
.situacao-pago { background-color:#28a745; color:#fff; padding:4px 10px; border-radius:5px; font-size:12px; }
.situacao-ativo { background-color:#ffa907; color:#fff; padding:4px 10px; border-radius:5px; font-size:12px; }
.situacao-cancelado { background-color:#dc3545; color:#fff; padding:4px 10px; border-radius:5px; font-size:12px; }

/* ====== CARDS ====== */
.card{ border-radius:14px; box-shadow:0 10px 18px rgba(0,0,0,.04); }
.card-header{ border-top-left-radius:14px; border-top-right-radius:14px; }

/* ====== TIMELINE ====== */
.step{ display:flex; align-items:flex-start; gap:10px; margin-bottom:12px; }
.step .dot{ margin-top:6px; }

/* ====== AVISOS BLOQUEIO ====== */
.block-alert{ margin-bottom:.75rem; }

/* ====== REFERÊNCIAS ====== */
.ref-grid{
  display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap:12px; margin-top:6px;
}
.ref-item{
  background: rgba(0,0,0,.03); border: 1px solid #ebeef2;
  border-radius:12px; padding:10px 12px;
}
.ref-key{
  display:block; font-size:.75rem; letter-spacing:.02em;
  color:#6c757d; text-transform:uppercase; margin-bottom:4px;
}
.ref-value{ font-weight:600; word-break:break-word; }
.dark-mode .ref-item{ background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.12); }
.dark-mode .ref-key{ color:#b9c0c7; }

/* ===== Integração API Card ===== */
.api-status.ok{ background: rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.25); }
.api-status.pending{ background: rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.25); }
.api-status.error{ background: rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); }
.api-status .badge{ border-radius:5px; color: #fff}
.api-mini{ font-size:.92rem; }
.api-details{ font-size:.85rem; color:#6c757d; }

/* ===== Dropzone ===== */
.dropzone {
  border:2px dashed rgba(13,110,253,.4);
  border-radius:14px;
  background:rgba(13,110,253,.03);
  padding:20px;
}
.dropzone .dz-message { color:#0d6efd; font-weight:600; }
.dz-success-mark,.dz-error-mark{ display:none; }
.dark-mode .dropzone{
  border-color: rgba(147,197,253,.6);
  background: rgba(147,197,253,.08);
}
.dark-mode .dropzone .dz-message{ color:#cfe5ff; }

/* ===== Lista de anexos (cards) ===== */
.attach-grid{ display:grid; gap:12px; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
.attach-card{
  border:1px solid #ebeef2; border-radius:12px; padding:12px;
  background:#fff; display:flex; flex-direction:column; gap:10px;
}
.dark-mode .attach-card{ background:#1f2330; border-color:rgba(255,255,255,.12); }
.attach-title{ font-weight:600; word-break:break-word; }
.attach-meta{ font-size:.85rem; color:#6c757d; display:flex; gap:8px; flex-wrap:wrap; }
.dark-mode .attach-meta{ color:#aab3bd; }
.attach-actions{ display:flex; gap:6px; flex-wrap:wrap; }
.attach-select{ display:flex; align-items:center; justify-content:space-between; gap:8px; }
.attach-empty{
  border:1px dashed #e5e7eb; border-radius:12px; padding:18px; text-align:center; color:#6c757d;
}
.dark-mode .attach-empty{ border-color:rgba(255,255,255,.12); color:#aab3bd; }

/* ===== Toolbar do card Anexos ===== */
.attach-toolbar{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.attach-toolbar .btn-group{ flex-wrap:wrap; }

/* ===== Espaçamento dos checkboxes ===== */
.form-check-input{ margin-right:.45rem!important; }
.attach-select .form-check-input{ margin-right:.45rem; }

/* ===== Melhor contraste dos botões no dark ===== */
.dark-mode .btn-outline-primary{ color:#93c5fd; border-color:#93c5fd; }
.dark-mode .btn-outline-primary:hover{ background:#93c5fd; color:#0b1220; }
.dark-mode .btn-outline-secondary{ color:#e5e7eb; border-color:#9aa4b2; }
.dark-mode .btn-outline-secondary:hover{ background:#9aa4b2; color:#0b1220; }
.dark-mode .btn-outline-dark{ color:#e5e7eb; border-color:#cbd5e1; }
.dark-mode .btn-outline-dark:hover{ background:#cbd5e1; color:#0b1220; }
.dark-mode .btn-success{ background:#16a34a; border-color:#16a34a; }

/* ===== Modal 95% ===== */
#viewerModal .modal-dialog{
  max-width:95vw; width:95vw;
}
#viewerModal .modal-body{
  min-height:85vh; background:#0b0b0b0a;
}
.dark-mode #viewerModal .modal-body{
  background:#0b1220;
}
/* Container interno para imagem ocupar tudo sem cortar */
.viewer-img-wrap{
  width:100%; height:calc(95vh - 140px); /* header+footer aprox */
  display:flex; align-items:center; justify-content:center;
  background:transparent;
}
.viewer-img-wrap img{
  max-width:100%; max-height:100%;
  width:auto; height:auto; object-fit:contain; display:block;
}
</style>
</head>
<body>
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">

    <section class="page-hero" aria-labelledby="pedido-title">
      <div class="title-row">
        <div class="title-icon"><i class="fa fa-file-text-o" aria-hidden="true"></i></div>
        <div>
          <h1 id="pedido-title" class="mb-1">
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
        <div class="card mb-3">
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
                <i class="mdi mdi-receipt-text"></i> Abrir Recibo do Pedido
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
        <div class="card mb-3">
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
        <div class="card mb-3">
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
        <div class="card mb-3 api-status <?=htmlspecialchars($integrationStatus)?>">
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
        <div class="card mb-3">
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
              <input type="hidden" id="osPagoFlag" value="<?= ($totalPag > 0 ? '1' : '0') ?>">
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
                    Em “emitida”, anexe o PDF da certidão. Em “entregue”, informe quem retirou. Em “cancelada”, descreva o motivo.
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

        <!-- ANEXOS (AGORA DEPOIS DO ALTERAR STATUS) -->
        <div class="card">
          <div class="card-header">
            <div class="attach-toolbar">
              <strong>Anexos</strong>
              <div class="btn-group btn-group-sm">
                <button id="btnUpload" type="button" class="btn btn-success" disabled>
                  <i class="mdi mdi-upload"></i> Enviar anexo(s)
                </button>
                <button id="btnCompilar" type="button" class="btn btn-outline-primary" disabled>
                  <i class="mdi mdi-file-pdf-box"></i> Baixar PDF (selecionados)
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
</div>

<!-- MODAL DE VISUALIZAÇÃO -->
<div class="modal fade" id="viewerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span id="viewerTitle">Visualizar anexo</span></h5>
        <div class="btn-group me-2">
          <a id="btnOpenNew" href="#" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-external-link"></i> Abrir em nova guia
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
  // Aplica tema salvo (toggle está no menu)
  $.get('../load_mode.php', function(mode){
    $('body').removeClass('light-mode dark-mode').addClass(mode);
  });

  // Copiar link do rastreio
  $('#btnCopy').on('click', function(){
    const url = $(this).data('url');
    if (!navigator.clipboard) {
      const ta = $('<textarea>').val(url).appendTo('body').select();
      try { document.execCommand('copy'); } catch(e){}
      ta.remove();
      Swal.fire({icon:'success', title:'Copiado!', text:'Link copiado para a área de transferência.'});
      return;
    }
    navigator.clipboard.writeText(url).then(function(){
      Swal.fire({icon:'success', title:'Copiado!', text:'Link copiado para a área de transferência.'});
    }, function(){
      Swal.fire({icon:'error', title:'Falha', text:'Não foi possível copiar o link.'});
    });
  });

  // Exibição de campos extras conforme o novo status
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

  // SUBMIT do formulário de status via AJAX (com suporte a arquivo)
  $('#formStatus').on('submit', function(e){
    e.preventDefault();

    const osStatus = $('#osStatusHidden').val();
    if (osStatus && (osStatus.toLowerCase() === 'cancelado' || osStatus.toLowerCase() === 'cancelada')) {
      Swal.fire({icon:'info', title:'Pedido já cancelado', text:'A O.S. está cancelada; o pedido foi/será cancelado automaticamente.'});
      return false;
    }

    // Bloqueia alteração quando não houver depósito prévio pago
    const osPago = $('#osPagoFlag').val() === '1';
    if (!osPago) {
      Swal.fire({
        icon: 'warning',
        title: 'Ação bloqueada',
        text: 'Para alterar o status do pedido a O.S. precisa estar com Depósito Prévio pago.'
      });
      return false;
    }

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
            Swal.fire({icon:'success', title:'Sucesso', text: 'Status atualizado com sucesso.'})
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

  // Reenviar (cartão Integração API)
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

  // ---- Dropzone (limita PDF/JPG/PNG e envia ao clicar) ----
  Dropzone.autoDiscover = false;

  // Reaproveita instância se houver
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

  // ===== LISTA (cards) =====
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

  // Carrega lista
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
            style: 'width:100%;height:calc(95vh - 140px);border:0;',
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

  // Compilar PDF
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
