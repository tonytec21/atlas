<?php
// pedidos_certidao/salvar_pedido.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json');

// Evita ruído que quebre o JSON
if (function_exists('ob_start')) { ob_start(); }

// Sessão
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Método e CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { if (ob_get_length()) ob_clean(); echo json_encode(['error'=>'Método inválido']); exit; }
if (empty($_POST['csrf']) || empty($_SESSION['csrf_pedidos']) || !hash_equals($_SESSION['csrf_pedidos'], $_POST['csrf'])) {
  if (ob_get_length()) ob_clean(); echo json_encode(['error'=>'Falha de CSRF.']); exit;
}

/* Config da API – ../api_secrets.json */
$apiConfig   = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];
$BASE_URL    = $apiConfig['base_url']    ?? 'https://consultapedido.sistemaatlas.com.br';
$INGEST_URL  = $apiConfig['ingest_url']  ?? (rtrim($BASE_URL,'/').'/api/ingest.php');
$API_KEY     = $apiConfig['api_key']     ?? null;
$HMAC_SECRET = $apiConfig['hmac_secret'] ?? null;
$VERIFY_SSL  = array_key_exists('verify_ssl',$apiConfig) ? (bool)$apiConfig['verify_ssl'] : true;

/* ===== Helpers / Schema ===== */
function ensureSchema(PDO $conn){
  // Pedidos
  $conn->exec("CREATE TABLE IF NOT EXISTS pedidos_certidao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    protocolo VARCHAR(32) NOT NULL UNIQUE,
    token_publico CHAR(40) NOT NULL UNIQUE,
    atribuicao VARCHAR(20) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    status ENUM('pendente','em_andamento','emitida','entregue','cancelada') NOT NULL DEFAULT 'pendente',
    requerente_nome VARCHAR(255) NOT NULL,
    requerente_doc VARCHAR(32) NULL,
    requerente_email VARCHAR(120) NULL,
    requerente_tel VARCHAR(30) NULL,
    portador_nome VARCHAR(255) NULL,
    portador_doc VARCHAR(32) NULL,
    referencias_json JSON NULL,
    base_calculo DECIMAL(12,2) DEFAULT 0,
    total_os DECIMAL(12,2) DEFAULT 0,
    ordem_servico_id INT NULL,
    anexo_pdf_path VARCHAR(500) NULL,
    retirado_por VARCHAR(255) NULL,
    cancelado_motivo VARCHAR(500) NULL,
    criado_por VARCHAR(120) NOT NULL,
    atualizado_por VARCHAR(120) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_protocolo (protocolo),
    INDEX idx_token_publico (token_publico),
    INDEX idx_os (ordem_servico_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

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
    INDEX idx_pedido (pedido_id),
    CONSTRAINT fk_pedido_statuslog FOREIGN KEY (pedido_id)
      REFERENCES pedidos_certidao(id) ON DELETE CASCADE
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

  // O.S. mínimas
  $conn->exec("CREATE TABLE IF NOT EXISTS ordens_de_servico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente VARCHAR(255) NOT NULL,
    cpf_cliente VARCHAR(32) NULL,
    total_os DECIMAL(12,2) NOT NULL DEFAULT 0,
    descricao_os VARCHAR(255) NULL,
    observacoes VARCHAR(1000) NULL,
    criado_por VARCHAR(120) NOT NULL,
    base_de_calculo DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(32) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cliente (cliente)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

  $conn->exec("CREATE TABLE IF NOT EXISTS ordens_de_servico_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordem_servico_id INT NOT NULL,
    ato VARCHAR(50) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    desconto_legal DECIMAL(5,2) NOT NULL DEFAULT 0,
    descricao VARCHAR(500) NULL,
    emolumentos DECIMAL(12,2) NOT NULL DEFAULT 0,
    ferc DECIMAL(12,2) NOT NULL DEFAULT 0,
    fadep DECIMAL(12,2) NOT NULL DEFAULT 0,
    femp DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    ordem_exibicao INT NOT NULL DEFAULT 1,
    INDEX idx_os_item (ordem_servico_id),
    CONSTRAINT fk_os_item FOREIGN KEY (ordem_servico_id)
      REFERENCES ordens_de_servico(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function br_money_to_decimal($v) {
  $v = preg_replace('/[^\d,.\-]/', '', (string)$v);
  $v = str_replace('.', '', $v);
  $v = str_replace(',', '.', $v);
  if ($v === '' || $v === '.' || $v === '-.') return 0.0;
  return (float)$v;
}
function safe_json_array($json) {
  $arr = json_decode($json, true);
  if (!is_array($arr)) $arr = [];
  return $arr;
}
function montar_titulo_os_fallback($atr, $tipo, array $refs) {
  $detalhe = '';
  if ($atr === 'RCPN') {
    if ($tipo === 'Nascimento') {
      $detalhe = $refs['nome_registrado'] ?? '';
    } elseif ($tipo === 'Casamento') {
      $noivo = $refs['nome_noivo'] ?? '';
      $noiva = $refs['nome_noiva'] ?? '';
      $detalhe = trim($noivo . ($noivo && $noiva ? ' & ' : '') . $noiva);
    } elseif ($tipo === 'Óbito') {
      $detalhe = $refs['nome_falecido'] ?? '';
    }
  } elseif ($atr === 'RTD/RTDPJ' || $atr === 'Notas') {
    $detalhe = $refs['partes'] ?? '';
  } elseif ($atr === 'RI') {
    if (!empty($refs['matricula']))      $detalhe = 'Matrícula ' . $refs['matricula'];
    elseif (!empty($refs['imovel']))     $detalhe = 'Imóvel ' . $refs['imovel'];
  }
  $base = "Certidão {$tipo} ({$atr})";
  return $detalhe ? "{$base} – {$detalhe}" : $base;
}
function inferir_portador_nome($atr, $tipo, array $refs){
  if ($atr === 'RCPN') {
    if ($tipo === 'Nascimento') return trim($refs['nome_registrado'] ?? '');
    if ($tipo === 'Casamento') {
      $noivo = trim($refs['nome_noivo'] ?? '');
      $noiva = trim($refs['nome_noiva'] ?? '');
      return trim($noivo . ($noivo && $noiva ? ' e ' : '') . $noiva);
    }
    if ($tipo === 'Óbito') return trim($refs['nome_falecido'] ?? '');
  }
  if ($atr === 'RTD/RTDPJ' || $atr === 'Notas') {
    return trim($refs['partes'] ?? '');
  }
  if ($atr === 'RI') {
    if (!empty($refs['matricula']))  return 'Matrícula ' . trim($refs['matricula']);
    if (!empty($refs['imovel']))     return trim($refs['imovel']);
    if (!empty($refs['circunscricao'])) return 'Circunscrição ' . trim($refs['circunscricao']);
  }
  return '';
}

/**
 * HTTP POST JSON assinado com HMAC + RETRY/backoff e opções de rede robustas.
 * Retenta automaticamente em falhas de transporte (http_code==0 ou erro cURL).
 */
function post_json_signed(string $url, string $apiKey, string $hmacSecret, array $body, string $requestId, int $timestampMs, bool $verifySsl): array {
  $json = json_encode($body, JSON_UNESCAPED_UNICODE);
  $sig  = hash_hmac('sha256', $timestampMs . $json, $hmacSecret);

  // Desativa "Expect: 100-continue" e força encerramento da conexão
  $headers = [
    'Content-Type: application/json; charset=utf-8',
    'X-Api-Key: '      . $apiKey,
    'X-Timestamp-Ms: ' . $timestampMs,
    'X-Request-Id: '   . $requestId,
    'X-Signature: '    . $sig,
    'Expect:',
    'Connection: close'
  ];

  $attempts = 3;              // 1 tentativa + 2 retries
  $delayMs  = [250, 800];     // backoff entre as tentativas

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
        // CURLOPT_POSTFIELDSIZE removido por compatibilidade
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

    $ok = false;
    if ($resp !== null && $http === 200) {
      $j = json_decode($resp, true);
      $ok = is_array($j) && !empty($j['success']);
    }

    $last = ['ok'=>$ok,'http_code'=>$http,'response'=>$resp,'error'=>$err,'signature'=>$sig];

    // Sucesso ou erro de aplicação (HTTP 4xx/5xx com corpo) — não retentar
    if ($ok || ($http >= 400 && $http < 600)) break;

    // Falha de transporte (http 0 ou erro cURL) — retentar com backoff
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

/* ===== Execução ===== */
try {
  $conn = getDatabaseConnection();
  ensureSchema($conn);

  // Sanitização
  $atribuicao = trim($_POST['atribuicao'] ?? '');
  $tipo       = trim($_POST['tipo'] ?? '');
  $base_calc  = br_money_to_decimal($_POST['base_calculo'] ?? '0');

  $requerente_nome  = mb_strtoupper(trim($_POST['requerente_nome'] ?? ''),'UTF-8');
  $requerente_doc   = trim($_POST['requerente_doc'] ?? '');
  $requerente_email = trim($_POST['requerente_email'] ?? '');
  $requerente_tel   = trim($_POST['requerente_tel'] ?? '');
  $portador_nome    = trim($_POST['portador_nome'] ?? '');
  $portador_doc     = trim($_POST['portador_doc'] ?? '');

  $referencias_json = $_POST['referencias_json'] ?? '{}';
  $referencias_arr  = safe_json_array($referencias_json);
  $referencias_json = json_encode($referencias_arr, JSON_UNESCAPED_UNICODE);

  if ($portador_nome === '') {
    $portador_nome = inferir_portador_nome($atribuicao, $tipo, $referencias_arr);
  }

  $descricao_os = trim($_POST['descricao_os'] ?? '');
  if ($descricao_os === '') {
    $descricao_os = montar_titulo_os_fallback($atribuicao, $tipo, $referencias_arr);
  }

  $total_os_str = $_POST['total_os'] ?? '0,00';
  $total_os     = br_money_to_decimal($total_os_str);

  $itens_json   = $_POST['itens'] ?? '[]';
  $itens        = json_decode($itens_json, true);
  $isento_ato   = !empty($_POST['isento_ato']) && $_POST['isento_ato'] !== '0';

  $username     = $_SESSION['username'] ?? 'sistema';

  if (!$atribuicao || !$tipo) { if (ob_get_length()) ob_clean(); echo json_encode(['error'=>'Atribuição e Tipo são obrigatórios.']); exit; }
  if (empty($requerente_nome)) { if (ob_get_length()) ob_clean(); echo json_encode(['error'=>'Informe o nome do requerente.']); exit; }
  if (!$isento_ato && (!$itens || !is_array($itens) || count($itens)==0)) {
    if (ob_get_length()) ob_clean(); echo json_encode(['error'=>'Inclua ao menos um item na O.S. ou marque que o ato é isento.']); exit;
  }

  // PROTOCOLO e TOKEN
  $protocolo     = strtoupper(bin2hex(random_bytes(6)));  // 12 hex
  $token_publico = bin2hex(random_bytes(20));             // 40 chars

  // ====== Razão social do cartório (cadastro_serventia.razao_social)
  $razao_social = null;
  try {
    $stRS = $conn->query("SELECT razao_social FROM cadastro_serventia LIMIT 1");
    if ($stRS && ($rowRS = $stRS->fetch(PDO::FETCH_ASSOC))) {
      $razao_social = trim((string)($rowRS['razao_social'] ?? '')) ?: null;
    }
  } catch (Throwable $eRS) {
    // Silencioso: se a tabela não existir ou erro, segue sem a razão social
    $razao_social = null;
  }

  // Transação
  $conn->beginTransaction();

  $os_id = null;

  if ($isento_ato) {
    // Ato isento: não cria OS e zera total_os
    $total_os = 0.0;
  } else {
    // 1) Inserir OS
    $stmt = $conn->prepare("INSERT INTO ordens_de_servico (cliente, cpf_cliente, total_os, descricao_os, observacoes, criado_por, base_de_calculo)
                            VALUES (:cliente,:cpf,:total,:desc,:obs,:user,:base)");
    $cliente = $requerente_nome;
    $cpf     = $requerente_doc;
    $obs     = 'Pedido de Certidão: '.$atribuicao.' / '.$tipo.' • Protocolo: '.$protocolo;
    $stmt->execute([
      ':cliente'=>$cliente,
      ':cpf'=>$cpf,
      ':total'=>$total_os,
      ':desc'=>$descricao_os,
      ':obs'=>$obs,
      ':user'=>$username,
      ':base'=>$base_calc
    ]);
    $os_id = $conn->lastInsertId();

    $stmtItem = $conn->prepare("INSERT INTO ordens_de_servico_itens
        (ordem_servico_id, ato, quantidade, desconto_legal, descricao, emolumentos, ferc, fadep, femp, total, ordem_exibicao)
         VALUES (:os,:ato,:qtd,:descleg,:descr,:em,:fe,:fa,:fm,:tot,:ordem)");

    foreach($itens as $it){
      $em  = br_money_to_decimal($it['emolumentos'] ?? 0);
      $fe  = br_money_to_decimal($it['ferc'] ?? 0);
      $fa  = br_money_to_decimal($it['fadep'] ?? 0);
      $fm  = br_money_to_decimal($it['femp'] ?? 0);
      $tot = br_money_to_decimal($it['total'] ?? 0);
      $stmtItem->execute([
        ':os'    => $os_id,
        ':ato'   => $it['ato'],
        ':qtd'   => (int)$it['quantidade'],
        ':descleg'=> (float)$it['desconto_legal'],
        ':descr' => $it['descricao'],
        ':em'    => $em,
        ':fe'    => $fe,
        ':fa'    => $fa,
        ':fm'    => $fm,
        ':tot'   => $tot,
        ':ordem' => (int)$it['ordem_exibicao']
      ]);
    }
  }

  // 2) Inserir Pedido
  $stmtP = $conn->prepare("INSERT INTO pedidos_certidao
    (protocolo, token_publico, atribuicao, tipo, status, requerente_nome, requerente_doc, requerente_email, requerente_tel,
     portador_nome, portador_doc, referencias_json, base_calculo, total_os, ordem_servico_id, criado_por)
     VALUES (:prot,:token,:atr,:tipo,'pendente',:rn,:rd,:re,:rt,:pn,:pd,:refs,:base,:tot,:os,:user)");

  $stmtP->execute([
    ':prot'  => $protocolo,
    ':token' => $token_publico,
    ':atr'   => $atribuicao,
    ':tipo'  => $tipo,
    ':rn'    => $requerente_nome,
    ':rd'    => $requerente_doc,
    ':re'    => $requerente_email,
    ':rt'    => $requerente_tel,
    ':pn'    => $portador_nome,
    ':pd'    => $portador_doc,
    ':refs'  => $referencias_json,
    ':base'  => $base_calc,
    ':tot'   => $total_os,
    ':os'    => $os_id, // pode ser NULL quando isento
    ':user'  => $username
  ]);
  $pedido_id = $conn->lastInsertId();

  // 3) Log inicial
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
  $conn->prepare("INSERT INTO pedidos_certidao_status_log (pedido_id,status_anterior,novo_status,observacao,usuario,ip,user_agent)
                  VALUES (?,?,?,?,?,?,?)")
       ->execute([$pedido_id,null,'pendente','Pedido criado',$username,$ip,$ua]);

  // 4) QR da URL pública /v1/rastreio/{token}
  $dirQr = __DIR__.'/qrcodes';
  if (!is_dir($dirQr)) @mkdir($dirQr,0777,true);
  $urlPublica = rtrim($BASE_URL,'/').'/v1/rastreio/'.$token_publico;
  $qrPath = $dirQr."/pedido_{$pedido_id}.png";
  if (!file_exists($qrPath)) {
    if (file_exists(__DIR__ . '/../phpqrcode/qrlib.php')) {
      include_once(__DIR__ . '/../phpqrcode/qrlib.php');
      QRcode::png($urlPublica, $qrPath, QR_ECLEVEL_M, 4);
    } else {
      if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor(800, 160);
        $bg = imagecolorallocate($im, 240,240,240);
        $tx = imagecolorallocate($im, 60,60,60);
        imagefilledrectangle($im,0,0,800,160,$bg);
        imagestring($im, 5, 10, 30, "QR lib ausente. URL de rastreio:", $tx);
        imagestring($im, 3, 10, 80, $urlPublica, $tx);
        imagepng($im, $qrPath); imagedestroy($im);
      } else {
        file_put_contents($qrPath.'.txt', $urlPublica);
      }
    }
  }

  // 5) OUTBOX + envio imediato (topic: pedido_criado | formato FLAT)
  $body = [
    'topic'            => 'pedido_criado',
    'protocolo'        => $protocolo,
    'token_publico'    => $token_publico,
    'atribuicao'       => $atribuicao,
    'tipo'             => $tipo,
    'status'           => 'pendente',
    'pedido_id'        => (int)$pedido_id,
    'ordem_servico_id' => $os_id === null ? null : (int)$os_id,
    'isento_ato'       => $isento_ato ? true : false,
    'criado_em'        => date('c'),
    // Resumo sem dados sensíveis (LGPD)
    'resumo' => [
      'requerente' => mb_substr($requerente_nome,0,1,'UTF-8').'***',
      'total_os'   => (float)$total_os
    ],
    // >>>>>>> Razão social do cartório (inclusão solicitada)
    'razao_social'     => $razao_social,
    'source'           => 'atlas-app',
    'event_time'       => date('c')
  ];

  $timestamp = (int) round(microtime(true) * 1000); // ms
  $requestId = bin2hex(random_bytes(12));
  $signature = $HMAC_SECRET ? hash_hmac('sha256', $timestamp . json_encode($body, JSON_UNESCAPED_UNICODE), $HMAC_SECRET) : null;

  $stmtOut = $conn->prepare("INSERT INTO api_outbox (topic,protocolo,token_publico,payload_json,api_key,signature,timestamp_utc,request_id)
                             VALUES (?,?,?,?,?,?,?,?)");
  $stmtOut->execute([
    'pedido_criado',
    $protocolo,
    $token_publico,
    json_encode($body,JSON_UNESCAPED_UNICODE),
    $API_KEY ?: null,
    $signature,
    $timestamp,
    $requestId
  ]);
  $outbox_id = (int)$conn->lastInsertId();

  $conn->commit();

  // 6) Disparo imediato para a API (mantém pendência em caso de falha)
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
    'success'=>true,
    'id'=>$pedido_id,
    'api_delivery'=>$apiDelivery,
    'url_publica'=>$urlPublica
  ]);

} catch(Throwable $e){
  if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
  if (ob_get_length()) ob_clean();
  echo json_encode(['error'=>'Erro ao salvar o pedido: '.$e->getMessage()]);
}
