<?php
// pedidos_certidao/alterar_status.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');
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

/* ===== Helpers de IP/UA ===== */

/**
 * Trunca string com sufixo, garantindo comprimento máximo.
 */
function str_truncate(string $s, int $limit, string $suffix = '…'): string {
  if ($limit <= 0) return '';
  if (mb_strlen($s, 'UTF-8') <= $limit) return $s;
  $cut = max(0, $limit - mb_strlen($suffix, 'UTF-8'));
  return mb_substr($s, 0, $cut, 'UTF-8') . $suffix;
}

/**
 * Extrai o melhor IP do cliente considerando cabeçalhos de proxy/CDN.
 * Aceita IPv4/IPv6 e mantém IPs privados/locais (requisito do usuário).
 */
function get_client_ip(): ?string {
  $candidates = [];

  // Cloudflare
  if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
  }

  // X-Forwarded-For pode trazer lista (cliente, proxies...)
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    // quebra em vírgulas e varre do início (primeiro = cliente original)
    $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
    foreach ($parts as $p) {
      $candidates[] = $p;
    }
  }

  // Outros cabeçalhos comuns
  if (!empty($_SERVER['HTTP_CLIENT_IP']))        $candidates[] = $_SERVER['HTTP_CLIENT_IP'];
  if (!empty($_SERVER['HTTP_X_REAL_IP']))        $candidates[] = $_SERVER['HTTP_X_REAL_IP'];
  if (!empty($_SERVER['REMOTE_ADDR']))           $candidates[] = $_SERVER['REMOTE_ADDR'];

  // Normaliza e valida
  foreach ($candidates as $ip) {
    if (!$ip) continue;

    // remove “::ffff:127.0.0.1” (IPv6 mapeado p/ IPv4)
    if (stripos($ip, '::ffff:') === 0) {
      $maybeV4 = substr($ip, 7);
      if (filter_var($maybeV4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $maybeV4;
      }
    }

    // aceita IPv4 ou IPv6 (público/privado/local)
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      return $ip;
    }
  }

  return null;
}

/**
 * Se for localhost, registra forma(s) úteis.
 * - ::1 => "127.0.0.1 / ::1"
 * - 127.0.0.1 => "127.0.0.1 / ::1"
 * Caso contrário, retorna o IP original.
 */
function normalize_localhost_ip_display(?string $ip): ?string {
  if (!$ip) return null;
  if ($ip === '::1') return '127.0.0.1 / ::1';
  if ($ip === '127.0.0.1') return '127.0.0.1 / ::1';
  return $ip;
}

/**
 * Gera um resumo legível de User-Agent: "Desktop • Windows 11 • Chrome 124.0"
 * e anexa " | UA: <original>" truncado para caber no VARCHAR(255).
 */
function summarize_user_agent(?string $uaRaw): string {
  $ua = (string)($uaRaw ?? '');

  $device = 'Desktop';
  if (preg_match('/Mobile|Android|iPhone|iPod/i', $ua)) $device = 'Mobile';
  if (preg_match('/Tablet|iPad/i', $ua)) $device = 'Tablet';
  if (preg_match('/Bot|Crawler|Spider|Googlebot|Bingbot|DuckDuckBot|Slurp/i', $ua)) $device = 'Bot';

  // SO
  $os = 'Desconhecido';
  if (preg_match('/Windows NT 10\.0; Win64; x64|Windows NT 10\.0/i', $ua)) $os = 'Windows 10/11';
  elseif (preg_match('/Windows NT 6\.3/i', $ua)) $os = 'Windows 8.1';
  elseif (preg_match('/Windows NT 6\.2/i', $ua)) $os = 'Windows 8';
  elseif (preg_match('/Windows NT 6\.1/i', $ua)) $os = 'Windows 7';
  elseif (preg_match('/Mac OS X ([0-9_\.]+)/i', $ua, $m)) $os = 'macOS ' . str_replace('_', '.', $m[1]);
  elseif (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $m)) $os = 'iOS ' . str_replace('_', '.', $m[1]);
  elseif (preg_match('/iPad; CPU OS ([0-9_]+)/i', $ua, $m)) $os = 'iPadOS ' . str_replace('_', '.', $m[1]);
  elseif (preg_match('/Android ([0-9\.]+)/i', $ua, $m)) $os = 'Android ' . $m[1];
  elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';

  // Navegador
  $browser = 'Navegador';
  if (preg_match('/Edg\/([0-9\.]+)/', $ua, $m)) {
    $browser = 'Edge ' . $m[1];
  } elseif (preg_match('/OPR\/([0-9\.]+)/', $ua, $m) || preg_match('/Opera\/([0-9\.]+)/', $ua, $m)) {
    $browser = 'Opera ' . $m[1];
  } elseif (preg_match('/Chrome\/([0-9\.]+)/', $ua, $m) && !preg_match('/Chromium/i', $ua)) {
    $browser = 'Chrome ' . $m[1];
  } elseif (preg_match('/Firefox\/([0-9\.]+)/', $ua, $m)) {
    $browser = 'Firefox ' . $m[1];
  } elseif (preg_match('/Version\/([0-9\.]+).*Safari\//', $ua, $m)) {
    $browser = 'Safari ' . $m[1];
  } elseif (preg_match('/MSIE ([0-9\.]+)/', $ua, $m) || preg_match('/Trident\/.*rv:([0-9\.]+)/', $ua, $m)) {
    $browser = 'Internet Explorer ' . $m[1];
  } elseif (preg_match('/Chromium\/([0-9\.]+)/', $ua, $m)) {
    $browser = 'Chromium ' . $m[1];
  }

  $summary = "{$device} • {$os} • {$browser}";
  // Monta string final para caber em 255 chars
  $final = $summary . ' | UA: ' . $ua;
  return str_truncate($final, 255);
}

/* CONFIG DA API */
$apiConfig   = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];
$BASE_URL    = $apiConfig['base_url']    ?? 'https://consultapedido.sistemaatlas.com.br';
$INGEST_URL  = $apiConfig['ingest_url']  ?? (rtrim($BASE_URL,'/').'/api/ingest.php');
$API_KEY     = $apiConfig['api_key']     ?? null;
$HMAC_SECRET = $apiConfig['hmac_secret'] ?? null;
$VERIFY_SSL  = array_key_exists('verify_ssl',$apiConfig) ? (bool)$apiConfig['verify_ssl'] : true;

/* Helpers / Migrações mínimas */
function ensureSchema(PDO $conn){
  // Log de status
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

  // Outbox
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

  // Tabelas de anexos/imagens (para salvar a certidão emitida também aqui)
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

function post_json_signed(string $url, string $apiKey, string $hmacSecret, array $body, string $requestId, int $timestampMs, bool $verifySsl): array {
  $json = json_encode($body, JSON_UNESCAPED_UNICODE);
  $sig  = hash_hmac('sha256', $timestampMs . $json, $hmacSecret);

  // Remove "Expect: 100-continue" e força fechamento da conexão
  $headers = [
    'Content-Type: application/json; charset=utf-8',
    'X-Api-Key: '      . $apiKey,
    'X-Timestamp-Ms: ' . $timestampMs,
    'X-Request-Id: '   . $requestId,
    'X-Signature: '    . $sig,
    'Expect:',
    'Connection: close'
  ];

  // 1 tentativa + 2 retries com backoff
  $attempts = 3;
  $delayMs  = [250, 800];

  $last = ['ok'=>false,'http_code'=>0,'response'=>null,'error'=>null,'signature'=>$sig];

  for ($i=0; $i<$attempts; $i++) {
    $resp = null; $http=0; $err=null;

    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $json,
        // timeouts mais folgados
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 25,
        // SSL conforme config
        CURLOPT_SSL_VERIFYPEER => $verifySsl ? 1 : 0,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        // rede mais estável
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_NOSIGNAL       => 1,
        CURLOPT_FORBID_REUSE   => 1,
        CURLOPT_FRESH_CONNECT  => 1,
      ]);
      $resp = curl_exec($ch);
      if ($resp === false) {
        $errno = curl_errno($ch);
        $err = 'cURL('.$errno.'): '.curl_error($ch);
      }
      $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
    } else {
      // Fallback sem cURL
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

    // Considera sucesso somente quando a API responde success=true
    $ok = false;
    if ($resp !== null && $http >= 200 && $http < 300) {
      $j = json_decode($resp, true);
      $ok = is_array($j) && !empty($j['success']);
    }

    $last = ['ok'=>$ok,'http_code'=>$http,'response'=>$resp,'error'=>$err,'signature'=>$sig];

    // Sucesso ou erro 4xx/5xx — não retenta
    if ($ok || ($http >= 400 && $http < 600)) break;

    // Falha de transporte (HTTP 0 ou erro cURL) — retenta com backoff
    if ($http === 0 || $err) {
      if ($i < $attempts - 1) {
        $sleep = $delayMs[min($i, count($delayMs)-1)];
        usleep($sleep * 1000);
        continue;
      }
    }
    break;
  }

  return $last;
}

/* ====== ImageMagick helpers (mesmo comportamento do upload geral) ====== */
define('IM_CONVERT_WIN', 'C:\\Program Files\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe');
define('IM_CONVERT_LINUX', 'convert');
function im_has_convert(): bool {
  if (stripos(PHP_OS, 'WIN') === 0 && file_exists(IM_CONVERT_WIN)) return true;
  $which = @trim((string)@shell_exec('which ' . IM_CONVERT_LINUX));
  return $which !== '';
}
function im_cmd(string $args): string {
  if (stripos(PHP_OS, 'WIN') === 0 && file_exists(IM_CONVERT_WIN)) {
    return '"' . IM_CONVERT_WIN . "\" $args";
  }
  return IM_CONVERT_LINUX . " $args";
}

/**
 * Salva um arquivo (PDF ou JPG) na mesma estrutura de anexos usada em anexos_upload.php.
 * Retorna [success, anexo_id].
 */
function anexar_certidao(PDO $conn, int $pedidoId, array $file): array {
  if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
    return ['success'=>false, 'error' => 'Falha no upload (código '.$file['error'].')'];
  }
  if (!is_uploaded_file($file['tmp_name'])) {
    return ['success'=>false, 'error' => 'Upload inválido'];
  }

  // Somente PDF ou JPG conforme solicitado
  $allowed = ['application/pdf','image/jpeg'];
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']) ?: mime_content_type($file['tmp_name']);
  if (!in_array($mime, $allowed, true)) {
    return ['success'=>false, 'error' => 'Tipo inválido. Envie PDF ou JPG.'];
  }

  $baseDir = __DIR__ . '/uploads/' . $pedidoId;
  if (!is_dir($baseDir)) { @mkdir($baseDir, 0777, true); }

  $origName = $file['name'];
  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
  if ($ext === 'jpeg') $ext = 'jpg';
  if ($mime === 'application/pdf') $ext = 'pdf';
  if ($mime === 'image/jpeg') $ext = 'jpg';

  $saveName = uniqid('anx_', true) . '.' . $ext;
  $savePath = $baseDir . '/' . $saveName;
  if (!@move_uploaded_file($file['tmp_name'], $savePath)) {
    return ['success'=>false, 'error' => 'Falha ao salvar arquivo'];
  }

  // Persistir
  $stmt = $conn->prepare("INSERT INTO pedido_anexos (pedido_id, original_filename, mime_type, ext, path, size_bytes)
                          VALUES (?,?,?,?,?,?)");
  $stmt->execute([$pedidoId, $origName, $mime, $ext, $savePath, @filesize($savePath)]);
  $anexoId = (int)$conn->lastInsertId();
  $paginas = null;

  // Se PDF, converter páginas em JPG
  if ($ext === 'pdf' && im_has_convert()) {
    $inQuoted = escapeshellarg($savePath);

    // Conta páginas
    $cmdPages = im_cmd('identify -format %n ' . $inQuoted);
    $pagesOutput = @shell_exec($cmdPages);
    $numPages = (int)trim($pagesOutput ?: '0');
    if ($numPages <= 0) $numPages = 1;

    $outPattern = $baseDir . '/pdf_' . $anexoId . '_page_%03d.jpg';
    $cmdConv = im_cmd('-density 200 ' . $inQuoted . ' -quality 90 -background white -alpha remove -alpha off ' . escapeshellarg($outPattern));
    @shell_exec($cmdConv);

    $imgs = glob($baseDir . '/pdf_' . $anexoId . '_page_*.jpg');
    sort($imgs);
    $pag = 0;
    if ($imgs) {
      $insImg = $conn->prepare("INSERT INTO pedido_anexo_imagens (anexo_id, page_number, path, width, height) VALUES (?,?,?,?,?)");
      foreach ($imgs as $i => $imgPath) {
        $size = @getimagesize($imgPath);
        $w = $size ? ($size[0] ?? null) : null;
        $h = $size ? ($size[1] ?? null) : null;
        $insImg->execute([$anexoId, $i+1, $imgPath, $w, $h]);
        $pag++;
      }
    }
    $paginas = $pag;
    $conn->prepare("UPDATE pedido_anexos SET paginas_pdf=? WHERE id=?")->execute([$paginas, $anexoId]);
  }

  return ['success'=>true, 'anexo_id'=>$anexoId];
}

/* Entrada */
$id          = (int)($_POST['id'] ?? 0);
$novo        = $_POST['novo_status'] ?? '';
$observacao  = trim($_POST['observacao'] ?? '');
$username    = $_SESSION['username'] ?? 'sistema';

$validos = ['pendente','em_andamento','emitida','entregue','cancelada'];
if (!in_array($novo, $validos, true)) {
  if (ob_get_length()) ob_clean(); echo json_encode(['error'=>'Status inválido']); exit;
}

/* Execução */
try {
  $conn = getDatabaseConnection();
  ensureSchema($conn);
  $conn->beginTransaction();

  // Carrega pedido + status OS (lock)
  $stmt = $conn->prepare("
    SELECT p.*, os.status AS os_status
      FROM pedidos_certidao p
      LEFT JOIN ordens_de_servico os ON os.id = p.ordem_servico_id
     WHERE p.id = ?
     FOR UPDATE
  ");
  $stmt->execute([$id]);
  $p = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$p) { throw new Exception('Pedido não encontrado'); }

  $anterior    = $p['status'];
  $osStatus    = $p['os_status'] ?? null;
  $osCancelada = false;

  // Se O.S. cancelada => força cancelamento do pedido
  if ($osStatus && (strcasecmp($osStatus,'Cancelado')===0 || strcasecmp($osStatus,'Cancelada')===0)) {
    $osCancelada = true;
    $novo = 'cancelada';
    if ($observacao === '') $observacao = 'Cancelamento de O.S';
  }

  // Regras de transição (se não for cancelamento forçado)
  if (!$osCancelada) {
    $permitidas = [
      'pendente'     => ['em_andamento','cancelada'],
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

  // ===== Quando "emitida": anexo é OPCIONAL; se vier, salva =====
  if ($novo === 'emitida' && !$osCancelada) {
    if (!empty($_FILES['anexo_pdf']) && $_FILES['anexo_pdf']['error'] === UPLOAD_ERR_OK) {
      $resAnexo = anexar_certidao($conn, (int)$p['id'], $_FILES['anexo_pdf']);
      if (empty($resAnexo['success'])) {
        throw new Exception($resAnexo['error'] ?? 'Falha ao anexar a certidão.');
      }
    }
  }

  if ($novo === 'entregue' && !$osCancelada) {
    $retirado_por = trim($_POST['retirado_por'] ?? '');
    if ($retirado_por === '') throw new Exception('Informe quem retirou.');
  }

  if ($novo === 'cancelada') {
    if ($osCancelada) {
      $cancelado_motivo = 'Cancelamento de O.S';
    } else {
      $cancelado_motivo = trim($_POST['cancelado_motivo'] ?? '');
      if ($cancelado_motivo === '') throw new Exception('Informe o motivo do cancelamento.');
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

  // ===== IP e User-Agent (dispositivo/navegador) =====
  $clientIpRaw  = get_client_ip();                         // melhor IP detectado
  $clientIpDisp = normalize_localhost_ip_display($clientIpRaw); // normalização p/ localhost
  $uaRaw        = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $uaSummary    = summarize_user_agent($uaRaw);            // resumo + UA bruto (truncado)

  // Log
  $conn->prepare("
    INSERT INTO pedidos_certidao_status_log (pedido_id, status_anterior, novo_status, observacao, usuario, ip, user_agent)
    VALUES (?,?,?,?,?,?,?)
  ")->execute([$id, $anterior, $novo, ($observacao ?: null), $username, $clientIpDisp, $uaSummary]);

  // === Payload FLAT compatível com ingest.php ===
  $body = [
    'topic'          => 'status_atualizado',
    'protocolo'      => $p['protocolo'],
    'token_publico'  => $p['token_publico'],
    'status'         => $novo,
    'atualizado_em'  => date('c'),
    'observacao'     => $observacao ? true : false,
    // campos extras (opcionais)
    'observacao_text'=> $observacao ?: null,
    'pedido_id'      => (int)$p['id'],
    'ordem_servico_id'=> (int)$p['ordem_servico_id']
  ];

  // Grava na outbox
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

  // Envio imediato
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
