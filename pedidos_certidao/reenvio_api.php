<?php
// pedidos_certidao/reenvio_api.php
// Reenvia para a API mensagens pendentes (api_outbox) por outbox_id, pedido_id ou protocolo/token.
// Retorna JSON com sucesso/falha por mensagem.

include(__DIR__ . '/../os/session_check.php');
checkSession(); // somente logado
include(__DIR__ . '/../os/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json');

// Evita ruído no JSON
if (function_exists('ob_start')) { ob_start(); }

// Sessão
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  if (ob_get_length()) ob_clean();
  echo json_encode(['error' => 'Método inválido.']); exit;
}

/* ============ Config da API (../api_secrets.json) ============ */
$apiConfig   = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];
$BASE_URL    = $apiConfig['base_url']    ?? 'https://consultapedido.sistemaatlas.com.br';
$INGEST_URL  = $apiConfig['ingest_url']  ?? (rtrim($BASE_URL,'/').'/api/ingest.php');
$API_KEY     = $apiConfig['api_key']     ?? null;
$HMAC_SECRET = $apiConfig['hmac_secret'] ?? null;
$VERIFY_SSL  = array_key_exists('verify_ssl',$apiConfig) ? (bool)$apiConfig['verify_ssl'] : true;

// Ajustes de robustez (podem ser sobrescritos em api_secrets.json)
$FORCE_HTTP11     = array_key_exists('force_http11', $apiConfig) ? (bool)$apiConfig['force_http11'] : true;
$PREFER_IPV4      = array_key_exists('prefer_ipv4',  $apiConfig) ? (bool)$apiConfig['prefer_ipv4']  : true;
$MAX_RETRIES      = isset($apiConfig['retries']) ? max(0, (int)$apiConfig['retries']) : 2; // tentativas adicionais além da primeira
$CONNECT_TIMEOUT  = isset($apiConfig['connect_timeout']) ? (int)$apiConfig['connect_timeout'] : 7;
$REQUEST_TIMEOUT  = isset($apiConfig['request_timeout']) ? (int)$apiConfig['request_timeout'] : 20;
$RETRY_BASE_MS    = isset($apiConfig['retry_base_ms']) ? (int)$apiConfig['retry_base_ms'] : 300;

if (!$INGEST_URL || !$API_KEY || !$HMAC_SECRET) {
  if (ob_get_length()) ob_clean();
  echo json_encode(['error' => 'Configuração da API ausente/incompleta (ingest_url, api_key, hmac_secret).']); exit;
}

/* ============ Helpers ============ */
function ensureSchema(PDO $conn){
  // api_outbox – garantia mínima
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

/**
 * POST JSON assinado, com medidas contra "Recv failure: Connection was reset"
 * - Força HTTP/1.1 (evita h2 POST com alguns servidores)
 * - Desativa "Expect: 100-continue" e chunked (define Content-Length)
 * - Fecha conexão (Connection: close), fresh_connect e forbid_reuse
 * - Preferência por IPv4 (evita resets em pilhas IPv6)
 * - Retries com backoff exponencial (erros transitórios)
 */
function post_json_signed(string $url, string $apiKey, string $hmacSecret, array $body, string $requestId, int $timestampMs, bool $verifySsl, array $opts = []): array {
  $json = json_encode($body, JSON_UNESCAPED_UNICODE);
  $sig  = hash_hmac('sha256', $timestampMs . $json, $hmacSecret);

  $headers = [
    'Content-Type: application/json; charset=utf-8',
    'Content-Length: ' . strlen($json),     // evita chunked
    'Connection: close',                    // fecha a cada requisição
    'Expect:',                              // desativa 100-continue
    'X-Api-Key: '      . $apiKey,
    'X-Timestamp-Ms: ' . $timestampMs,
    'X-Request-Id: '   . $requestId,
    'X-Signature: '    . $sig
  ];

  $FORCE_HTTP11    = $opts['force_http11']   ?? true;
  $PREFER_IPV4     = $opts['prefer_ipv4']    ?? true;
  $CONNECT_TIMEOUT = $opts['connect_timeout']?? 7;
  $REQUEST_TIMEOUT = $opts['request_timeout']?? 20;
  $MAX_RETRIES     = $opts['max_retries']    ?? 2;
  $RETRY_BASE_MS   = $opts['retry_base_ms']  ?? 300;

  $attempt = 0;
  $last = ['ok'=>false,'http_code'=>0,'response'=>null,'error'=>null,'signature'=>$sig];

  do {
    $attempt++;

    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      $curlOpts = [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_CONNECTTIMEOUT => $CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT        => $REQUEST_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => $verifySsl ? 1 : 0,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_NOSIGNAL       => 1,                 // evita sinais em timeouts
        CURLOPT_FRESH_CONNECT  => true,              // nova conexão
        CURLOPT_FORBID_REUSE   => true,              // não reutiliza
        CURLOPT_ENCODING       => '',                // aceita gzip/deflate/br
      ];
      if ($FORCE_HTTP11 && defined('CURL_HTTP_VERSION_1_1')) {
        $curlOpts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
      }
      if ($PREFER_IPV4 && defined('CURL_IPRESOLVE_V4')) {
        $curlOpts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
      }

      curl_setopt_array($ch, $curlOpts);
      $resp = curl_exec($ch);
      $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $err  = ($resp === false) ? ('cURL: '.curl_error($ch)) : null;
      curl_close($ch);
    } else {
      $context = stream_context_create([
        'http' => [
          'method'  => 'POST',
          'header'  => implode("\r\n", $headers),
          'content' => $json,
          'timeout' => $REQUEST_TIMEOUT,
        ]
      ]);
      $resp = @file_get_contents($url, false, $context);
      $http = 0;
      if (isset($http_response_header) && is_array($http_response_header)) {
        foreach($http_response_header as $h){
          if (preg_match('#HTTP/\S+\s+(\d{3})#',$h,$m)){ $http = (int)$m[1]; break; }
        }
      }
      $err = ($resp === false) ? 'HTTP stream falhou' : null;
    }

    $ok = false;
    if ($resp !== null && $http === 200) {
      $j = json_decode($resp, true);
      $ok = is_array($j) && !empty($j['success']);
    }

    $last = ['ok'=>$ok, 'http_code'=>$http, 'response'=>$resp, 'error'=>$err, 'signature'=>$sig];

    // Sai se OK; tenta novamente se erro de transporte/HTTP instável
    $transient = (!$ok) && (
      $http === 0 ||           // sem resposta
      $http >= 500 ||          // 5xx
      ($err && stripos($err,'Recv failure')!==false) ||   // Connection reset
      ($err && stripos($err,'Connection')!==false)
    );

    if ($ok || !$transient || $attempt > ($MAX_RETRIES+1)) {
      break;
    }

    // Backoff exponencial (com jitter)
    $waitMs = (int)($RETRY_BASE_MS * pow(2, $attempt-1));
    $waitMs = min($waitMs, 3000);
    usleep(($waitMs + rand(0,150)) * 1000);

  } while (true);

  return $last;
}

/* ============ Entrada ============ */
$pedidoId  = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
$outboxId  = isset($_POST['outbox_id']) ? (int)$_POST['outbox_id'] : 0;
$onlyTopic = isset($_POST['topic']) ? trim($_POST['topic']) : ''; // opcional: 'pedido_criado' ou 'status_atualizado'

try {
  $conn = getDatabaseConnection();
  ensureSchema($conn);

  // Seleciona mensagens pendentes
  if ($outboxId > 0) {
    $sql = "SELECT * FROM api_outbox WHERE id=? AND delivered_at IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$outboxId]);
  } elseif ($pedidoId > 0) {
  // Localiza protocolo/token do pedido
    $pstmt = $conn->prepare("SELECT protocolo, token_publico FROM pedidos_certidao WHERE id=?");
    $pstmt->execute([$pedidoId]);
    $row = $pstmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { throw new Exception('Pedido não encontrado.'); }

    if ($onlyTopic) {
      $sql = "SELECT * FROM api_outbox WHERE protocolo=? AND token_publico=? AND delivered_at IS NULL AND topic=?";
      $stmt = $conn->prepare($sql);
      $stmt->execute([$row['protocolo'], $row['token_publico'], $onlyTopic]);
    } else {
      $sql = "SELECT * FROM api_outbox WHERE protocolo=? AND token_publico=? AND delivered_at IS NULL";
      $stmt = $conn->prepare($sql);
      $stmt->execute([$row['protocolo'], $row['token_publico']]);
    }
  } else {
  // Fallback: reenvia por protocolo/token direto
    $protocolo = trim($_POST['protocolo'] ?? '');
    $token     = trim($_POST['token_publico'] ?? '');
    if ($protocolo && $token) {
      $sql = "SELECT * FROM api_outbox WHERE protocolo=? AND token_publico=? AND delivered_at IS NULL";
      $stmt = $conn->prepare($sql);
      $stmt->execute([$protocolo, $token]);
    } else {
      if (ob_get_length()) ob_clean();
      echo json_encode(['error' => 'Parâmetros ausentes. Informe outbox_id ou pedido_id.']); exit;
    }
  }

  $pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$pendentes) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success'=>true,'retries'=>0,'delivered'=>0,'message'=>'Nenhuma mensagem pendente.']); exit;
  }

  $entregues = 0;
  $falhas    = 0;
  $detalhes  = [];

  foreach ($pendentes as $msg) {
  // Corpo (payload) já está armazenado no banco
    $body = json_decode($msg['payload_json'], true);
    if (!is_array($body)) { $body = []; }

    $timestamp = (int) round(microtime(true) * 1000);
    $requestId = bin2hex(random_bytes(12));

    $res = post_json_signed(
      $INGEST_URL,
      $API_KEY,
      $HMAC_SECRET,
      $body,
      $requestId,
      $timestamp,
      $VERIFY_SSL,
      [
        'force_http11'   => $FORCE_HTTP11,
        'prefer_ipv4'    => $PREFER_IPV4,
        'connect_timeout'=> $CONNECT_TIMEOUT,
        'request_timeout'=> $REQUEST_TIMEOUT,
        'max_retries'    => $MAX_RETRIES,
        'retry_base_ms'  => $RETRY_BASE_MS
      ]
    );

    if ($res['ok']) {
      $entregues++;
      $upd = $conn->prepare("UPDATE api_outbox SET delivered_at=NOW(), last_error=NULL WHERE id=?");
      $upd->execute([$msg['id']]);
      $detalhes[] = [
        'id'        => (int)$msg['id'],
        'status'    => 'ok',
        'http_code' => (int)$res['http_code']
      ];
    } else {
      $falhas++;
    // Incrementa retries e registra último erro (limita tamanho para evitar estourar campo)
      $err = trim(($res['error'] ?? '') . ' ' . substr((string)$res['response'],0,600));
      $upd = $conn->prepare("UPDATE api_outbox SET retries=retries+1, last_error=? WHERE id=?");
      $upd->execute([ mb_substr($err ?: 'falha desconhecida', 0, 1000, 'UTF-8'), $msg['id'] ]);
      $detalhes[] = [
        'id'         => (int)$msg['id'],
        'status'     => 'erro',
        'http_code'  => (int)$res['http_code'],
        'last_error' => $err
      ];
    }
  }

  if (ob_get_length()) ob_clean();
  echo json_encode([
    'success'   => ($falhas === 0),
    'delivered' => $entregues,
    'failed'    => $falhas,
    'details'   => $detalhes
  ]);

} catch (Throwable $e) {
  if (ob_get_length()) ob_clean();
  echo json_encode(['error' => $e->getMessage()]);
}
