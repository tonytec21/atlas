<?php
// pedidos_certidao/alterar_status_auto.php
// Atualiza status de pedido pela O.S. sem exigir upload de anexo (uso automático)
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php'); // PDO
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json');

// Evita ruído de saída que corrompa o JSON
if (function_exists('ob_start')) { ob_start(); }

// Sessão
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Método e CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  if (ob_get_length()) ob_clean(); echo json_encode(['error'=>'Método inválido']); exit;
}
if (empty($_POST['csrf']) || empty($_SESSION['csrf_pedidos']) || !hash_equals($_SESSION['csrf_pedidos'], $_POST['csrf'])) {
  if (ob_get_length()) ob_clean(); echo json_encode(['error'=>'Falha de CSRF']); exit;
}

// ===== Helpers (copiados do alterar_status.php) =====
function str_truncate(string $s, int $limit, string $suffix = '…'): string {
  if ($limit <= 0) return '';
  if (mb_strlen($s, 'UTF-8') <= $limit) return $s;
  $cut = max(0, $limit - mb_strlen($suffix, 'UTF-8'));
  return mb_substr($s, 0, $cut, 'UTF-8') . $suffix;
}
function get_client_ip(): ?string {
  $candidates = [];
  if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
    foreach ($parts as $p) $candidates[] = $p;
  }
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) $candidates[] = $_SERVER['HTTP_CLIENT_IP'];
  if (!empty($_SERVER['HTTP_X_REAL_IP'])) $candidates[] = $_SERVER['HTTP_X_REAL_IP'];
  if (!empty($_SERVER['REMOTE_ADDR'])) $candidates[] = $_SERVER['REMOTE_ADDR'];
  foreach ($candidates as $ip) {
    if (!$ip) continue;
    if (stripos($ip, '::ffff:') === 0) {
      $maybeV4 = substr($ip, 7);
      if (filter_var($maybeV4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $maybeV4;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      return $ip;
    }
  }
  return null;
}
function normalize_localhost_ip_display(?string $ip): ?string {
  if (!$ip) return null;
  if ($ip === '::1') return '127.0.0.1 / ::1';
  if ($ip === '127.0.0.1') return '127.0.0.1 / ::1';
  return $ip;
}
function summarize_user_agent(?string $uaRaw): string {
  $ua = (string)($uaRaw ?? '');
  $device = 'Desktop';
  if (preg_match('/Mobile|Android|iPhone|iPod/i', $ua)) $device = 'Mobile';
  if (preg_match('/Tablet|iPad/i', $ua)) $device = 'Tablet';
  if (preg_match('/Bot|Crawler|Spider|Googlebot|Bingbot|DuckDuckBot|Slurp/i', $ua)) $device = 'Bot';
  $os = 'Desconhecido';
  if (preg_match('/Windows NT 10\.0/i', $ua)) $os = 'Windows 10/11';
  elseif (preg_match('/Windows NT 6\.3/i', $ua)) $os = 'Windows 8.1';
  elseif (preg_match('/Windows NT 6\.2/i', $ua)) $os = 'Windows 8';
  elseif (preg_match('/Windows NT 6\.1/i', $ua)) $os = 'Windows 7';
  elseif (preg_match('/Mac OS X ([0-9_\.]+)/i', $ua, $m)) $os = 'macOS ' . str_replace('_', '.', $m[1]);
  elseif (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $m)) $os = 'iOS ' . str_replace('_', '.', $m[1]);
  elseif (preg_match('/iPad; CPU OS ([0-9_]+)/i', $ua, $m)) $os = 'iPadOS ' . str_replace('_', '.', $m[1]);
  elseif (preg_match('/Android ([0-9\.]+)/i', $ua, $m)) $os = 'Android ' . $m[1];
  elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';
  $browser = 'Navegador';
  if (preg_match('/Edg\/([0-9\.]+)/', $ua, $m)) $browser = 'Edge ' . $m[1];
  elseif (preg_match('/OPR\/([0-9\.]+)/', $ua, $m) || preg_match('/Opera\/([0-9\.]+)/', $ua, $m)) $browser = 'Opera ' . $m[1];
  elseif (preg_match('/Chrome\/([0-9\.]+)/', $ua, $m) && !preg_match('/Chromium/i', $ua)) $browser = 'Chrome ' . $m[1];
  elseif (preg_match('/Firefox\/([0-9\.]+)/', $ua, $m)) $browser = 'Firefox ' . $m[1];
  elseif (preg_match('/Version\/([0-9\.]+).*Safari\//', $ua, $m)) $browser = 'Safari ' . $m[1];
  elseif (preg_match('/MSIE ([0-9\.]+)/', $ua, $m) || preg_match('/Trident\/.*rv:([0-9\.]+)/', $ua, $m)) $browser = 'Internet Explorer ' . $m[1];
  elseif (preg_match('/Chromium\/([0-9\.]+)/', $ua, $m)) $browser = 'Chromium ' . $m[1];
  $summary = "{$device} • {$os} • {$browser}";
  $final = $summary . ' | UA: ' . $ua;
  return str_truncate($final, 255);
}

// Config API
$apiConfig   = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];
$BASE_URL    = $apiConfig['base_url']    ?? 'https://consultapedido.sistemaatlas.com.br';
$INGEST_URL  = $apiConfig['ingest_url']  ?? (rtrim($BASE_URL,'/').'/api/ingest.php');
$API_KEY     = $apiConfig['api_key']     ?? null;
$HMAC_SECRET = $apiConfig['hmac_secret'] ?? null;
$VERIFY_SSL  = array_key_exists('verify_ssl',$apiConfig) ? (bool)$apiConfig['verify_ssl'] : true;

// Schema mínimo
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
}

function post_json_signed(string $url, string $apiKey, string $hmacSecret, array $body, string $requestId, int $timestampMs, bool $verifySsl): array {
  $json = json_encode($body, JSON_UNESCAPED_UNICODE);
  $sig  = hash_hmac('sha256', $timestampMs . $json, $hmacSecret);
  $headers = [
    'Content-Type: application/json; charset=utf-8',
    'X-Api-Key: '      . $apiKey,
    'X-Timestamp-Ms: ' . $timestampMs,
    'X-Request-Id: '   . $requestId,
    'X-Signature: '    . $sig,
    'Expect:',
    'Connection: close'
  ];
  $attempts = 3; $delayMs  = [250, 800];
  $last = ['ok'=>false,'http_code'=>0,'response'=>null,'error'=>null,'signature'=>$sig];
  for ($i=0;$i<$attempts;$i++){
    $resp=null; $http=0; $err=null;
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => $verifySsl ? 1 : 0,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_NOSIGNAL       => 1,
        CURLOPT_FORBID_REUSE   => 1,
        CURLOPT_FRESH_CONNECT  => 1,
      ]);
      $resp = curl_exec($ch);
      if ($resp === false) { $errno=curl_errno($ch); $err='cURL('.$errno.'): '.curl_error($ch); }
      $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
    } else {
      $context = stream_context_create([
        'http' => [
          'method'  => 'POST',
          'header'  => implode("\r\n", $headers),
          'content' => $json,
          'timeout' => 25,
        ]
      ]);
      $resp = @file_get_contents($url, false, $context);
      if ($resp === false) { $err = 'HTTP stream falhou'; }
      if (isset($http_response_header) && is_array($http_response_header)) {
        foreach($http_response_header as $h){
          if (preg_match('#HTTP/\S+\s+(\d{3})#',$h,$m)){ $http = (int)$m[1]; break; }
        }
      }
    }
    $ok = false;
    if ($resp !== null && $http >= 200 && $http < 300) {
      $j = json_decode($resp, true);
      $ok = is_array($j) && !empty($j['success']);
    }
    $last = ['ok'=>$ok,'http_code'=>$http,'response'=>$resp,'error'=>$err,'signature'=>$sig];
    if ($ok || ($http >= 400 && $http < 600)) break;
    if ($http === 0 || $err) {
      if ($i < $attempts - 1) { usleep(($delayMs[min($i, count($delayMs)-1)]) * 1000); continue; }
    }
    break;
  }
  return $last;
}

// ===== Entrada =====
$protocolo  = trim((string)($_POST['protocolo']  ?? ''));
$novo       = trim((string)($_POST['novo_status'] ?? ''));
$observacao = trim((string)($_POST['observacao'] ?? ''));
$username   = $_SESSION['username'] ?? 'sistema';

if ($protocolo === '') { if (ob_get_length()) ob_clean(); echo json_encode(['error'=>'Protocolo ausente']); exit; }

$validos = ['pendente','em_andamento','emitida','entregue','cancelada'];
if (!in_array($novo, $validos, true)) {
  if (ob_get_length()) ob_clean(); echo json_encode(['error'=>'Status inválido']); exit;
}

try {
  $conn = getDatabaseConnection(); // PDO
  ensureSchema($conn);
  $conn->beginTransaction();

  // Carrega pedido por protocolo (+ status da O.S) com lock
  $stmt = $conn->prepare("
    SELECT p.*, os.status AS os_status
      FROM pedidos_certidao p
      LEFT JOIN ordens_de_servico os ON os.id = p.ordem_servico_id
     WHERE p.protocolo = ?
     FOR UPDATE
  ");
  $stmt->execute([$protocolo]);
  $p = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$p) { throw new Exception('Pedido não encontrado para o protocolo informado'); }

  $id          = (int)$p['id'];
  $anterior    = $p['status'];
  $osStatus    = $p['os_status'] ?? null;
  $osCancelada = false;

  // Se O.S. cancelada => força cancelamento do pedido
  if ($osStatus && (strcasecmp($osStatus,'Cancelado')===0 || strcasecmp($osStatus,'Cancelada')===0)) {
    $osCancelada = true;
    $novo = 'cancelada';
    if ($observacao === '') $observacao = 'Cancelamento de O.S';
  }

  // Regras de transição (iguais às do painel), mas sem exigir anexo quando "emitida"
  if (!$osCancelada) {
    $permitidas = [
      'pendente'     => ['em_andamento','cancelada','emitida'],
      'em_andamento' => ['emitida','cancelada'],
      'emitida'      => ['entregue','cancelada'],
      'entregue'     => [],
      'cancelada'    => []
    ];
    if (!in_array($novo, $permitidas[$anterior] ?? [], true)) {
      throw new Exception("Transição inválida de '$anterior' para '$novo'.");
    }
  }

  // Campos extras
  $retirado_por     = null;
  $cancelado_motivo = null;

  if ($novo === 'entregue' && !$osCancelada) {
    // No fluxo automático não coletamos "retirado_por"; manter NULL
  }

  if ($novo === 'cancelada') {
    if ($osCancelada) {
      $cancelado_motivo = 'Cancelamento de O.S';
    } else {
      $cancelado_motivo = $observacao !== '' ? $observacao : 'Cancelado automaticamente';
    }
  }

  // Atualiza o pedido
  $upd = $conn->prepare("
    UPDATE pedidos_certidao
       SET status = :st,
           atualizado_por = :user,
           atualizado_em = NOW(),
           retirado_por     = COALESCE(:ret, retirado_por),
           cancelado_motivo = COALESCE(:mot, cancelado_motivo)
     WHERE id = :id
  ");
  $upd->execute([
    ':st'   => $novo,
    ':user' => $username,
    ':ret'  => $retirado_por,
    ':mot'  => $cancelado_motivo,
    ':id'   => $id
  ]);

  // IP + User-Agent
  $clientIpRaw  = get_client_ip();
  $clientIpDisp = normalize_localhost_ip_display($clientIpRaw);
  $uaRaw        = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $uaSummary    = summarize_user_agent($uaRaw);

  // Log
  $conn->prepare("
    INSERT INTO pedidos_certidao_status_log (pedido_id, status_anterior, novo_status, observacao, usuario, ip, user_agent)
    VALUES (?,?,?,?,?,?,?)
  ")->execute([$id, $anterior, $novo, ($observacao ?: null), $username, $clientIpDisp, $uaSummary]);

  // Payload para API externa (igual ao painel)
  $body = [
    'topic'          => 'status_atualizado',
    'protocolo'      => $p['protocolo'],
    'token_publico'  => $p['token_publico'],
    'status'         => $novo,
    'atualizado_em'  => date('c'),
    'observacao'     => $observacao ? true : false,
    'observacao_text'=> $observacao ?: null,
    'pedido_id'      => (int)$p['id'],
    'ordem_servico_id'=> (int)$p['ordem_servico_id']
  ];

  // Outbox
  $timestamp = (int) round(microtime(true) * 1000);
  $requestId = bin2hex(random_bytes(12));
  $signature = $HMAC_SECRET ? hash_hmac('sha256', $timestamp . json_encode($body, JSON_UNESCAPED_UNICODE), $HMAC_SECRET) : null;

  $conn->prepare("
    INSERT INTO api_outbox (topic, protocolo, token_publico, payload_json, api_key, signature, timestamp_utc, request_id)
    VALUES (?,?,?,?,?,?,?,?)
  ")->execute([
    'status_atualizado',
    $p['protocolo'],
    $p['token_publico'],
    json_encode($body, JSON_UNESCAPED_UNICODE),
    $API_KEY ?: null,
    $signature,
    $timestamp,
    $requestId
  ]);
  $outbox_id = (int)$conn->lastInsertId();

  $conn->commit();

  // Envio imediato para API (se configurado)
  $apiDelivery = ['attempted'=>false,'delivered'=>false,'http_code'=>0];
  if ($INGEST_URL && $API_KEY && $HMAC_SECRET) {
    $apiDelivery['attempted'] = true;
    $res = post_json_signed($INGEST_URL, $API_KEY, $HMAC_SECRET, $body, $requestId, $timestamp, $VERIFY_SSL);
    if ($res['ok']) {
      $apiDelivery['delivered'] = true;
      $apiDelivery['http_code'] = $res['http_code'];
      $upd = $conn->prepare("UPDATE api_outbox SET delivered_at=NOW(), last_error=NULL WHERE id=?");
      $upd->execute([$outbox_id]);
    } else {
      $apiDelivery['delivered'] = false;
      $apiDelivery['http_code'] = (int)$res['http_code'];
      $err = trim(($res['error'] ?? '') . ' ' . substr((string)$res['response'],0,600));
      $upd = $conn->prepare("UPDATE api_outbox SET retries=retries+1, last_error=? WHERE id=?");
      $upd->execute([$err ?: 'falha desconhecida', $outbox_id]);
    }
  }

  if (ob_get_length()) ob_clean();
  echo json_encode([
    'success' => true,
    'new_status' => $novo,
    'forced_cancel_by_os' => $osCancelada ? true : false,
    'api_delivery' => $apiDelivery
  ]);
} catch (Throwable $e) {
  if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
  if (ob_get_length()) ob_clean();
  echo json_encode(['error' => $e->getMessage()]);
}
