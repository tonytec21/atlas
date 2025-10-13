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

/* ======================== Config da API ======================== */
$apiConfig   = @json_decode(@file_get_contents(__DIR__ . '/api_secrets.json'), true) ?: [];
$BASE_URL    = $apiConfig['base_url']    ?? 'https://consultapedido.sistemaatlas.com.br';
$INGEST_URL  = $apiConfig['ingest_url']  ?? (rtrim($BASE_URL,'/').'/api/ingest.php');
$API_KEY     = $apiConfig['api_key']     ?? null;
$HMAC_SECRET = $apiConfig['hmac_secret'] ?? null;
$VERIFY_SSL  = array_key_exists('verify_ssl',$apiConfig) ? (bool)$apiConfig['verify_ssl'] : true;

/* ======================== Helpers / Schema existentes ======================== */
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

/* ======================== SCHEMA distribuição (NOVO) ======================== */
function ensureSchemaDistribuicao(PDO $conn) {
  $conn->exec("CREATE TABLE IF NOT EXISTS equipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    descricao VARCHAR(500) NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_equipe_nome (nome),
    INDEX idx_ativa (ativa)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

  $conn->exec("CREATE TABLE IF NOT EXISTS equipe_membros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipe_id INT NOT NULL,
    funcionario_id INT NOT NULL,
    papel VARCHAR(60) NULL,
    ordem INT NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    carga_maxima_diaria INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_membro_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE CASCADE,
    CONSTRAINT fk_membro_func FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_equipe_func (equipe_id, funcionario_id),
    INDEX idx_equipe_ativo (equipe_id, ativo),
    INDEX idx_ordem (ordem)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

  $conn->exec("CREATE TABLE IF NOT EXISTS equipe_regras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipe_id INT NOT NULL,
    atribuicao VARCHAR(50) NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    prioridade INT NOT NULL DEFAULT 10,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_regra_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE CASCADE,
    INDEX idx_match (atribuicao, tipo, ativa, prioridade),
    INDEX idx_equipe (equipe_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

  $conn->exec("CREATE TABLE IF NOT EXISTS tarefas_pedido (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    equipe_id INT NOT NULL,
    funcionario_id INT NULL,
    status ENUM('pendente','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'pendente',
    observacao VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tarefa_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tarefa_func FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE SET NULL,
    INDEX idx_pedido (pedido_id),
    INDEX idx_func_status (funcionario_id, status),
    INDEX idx_equipe (equipe_id),
    INDEX idx_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/* Conversores */
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

/* ======================== POST JSON assinado (seu original) ======================== */
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
      if ($resp === false) { $errno = curl_errno($ch); $err = 'cURL('.$errno.'): '.curl_error($ch); }
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
    if ($resp !== null && $http === 200) {
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

/* ======================== Helpers de normalização (NOVO) ======================== */
/** Gera candidatos de atribuição aceitando sinônimos/abreviações usadas nas regras. */
function candidatosAtribuicao(string $atr): array {
  $a = trim($atr);
  $norm = mb_strtolower($a, 'UTF-8');

  $out = [$a];

  if (strpos($norm, 'registro civil') !== false) {
    $out[] = 'RCPN';
    $out[] = 'Registro Civil';
  }
  if (strpos($norm, 'título') !== false || strpos($norm, 'titul') !== false || strpos($norm, 'document') !== false ||
      strpos($norm, 'pessoa jurídica') !== false || strpos($norm, 'pessoas jurídicas') !== false) {
    $out[] = 'RTD/RTDPJ';
    $out[] = 'Títulos e Documentos';
    $out[] = 'Pessoas Jurídicas';
  }
  if (strpos($norm, 'imóv') !== false || strpos($norm, 'imov') !== false) {
    $out[] = 'RI';
    $out[] = 'Registro de Imóveis';
  }
  if (strpos($norm, 'nota') !== false) {
    $out[] = 'Notas';
  }

  return array_values(array_unique($out));
}

/**
 * Extrai variações úteis do 'tipo' para casar com regras:
 * - o texto completo do formulário
 * - uma forma simplificada (sem “2ª”, “Inteiro Teor de”, etc.)
 * - uma palavra-chave (Nascimento, Casamento, Óbito, Escrituras, etc.)
 */
function candidatosTipo(string $tipo): array {
  $t = trim($tipo);
  $out = [$t];

  // Simplificações comuns
  $simp = $t;
  $simp = preg_replace('~^\s*(\d+ª|\d+a)\s+(de|da)\s+~iu', '', $simp);      // remove "2ª de", "2a de"
  $simp = preg_replace('~^\s*inteiro\s+teor\s+(de|da)\s+~iu', '', $simp);   // remove "Inteiro Teor de"
  $simp = preg_replace('~\s+livro\s*\d*$~iu', '', $simp);                   // ruídos ocasionais
  $simp = trim($simp);
  if ($simp !== '' && $simp !== $t) $out[] = $simp;

  // palavra-chave
  $kw = $simp !== '' ? $simp : $t;
  $map = ['Ó'=>'O','ó'=>'o','ã'=>'a','â'=>'a','á'=>'a','à'=>'a','ê'=>'e','é'=>'e','í'=>'i','î'=>'i','õ'=>'o','ô'=>'o','ú'=>'u','ç'=>'c'];
  $kwn = strtr(mb_strtolower($kw,'UTF-8'), $map);
  if (preg_match('~(nascimento|casamento|obito|óbito|escritura|escrituras|procura(c|ç)ao|procura(c|ç)oes|ata|testamento|oner|onus|penhor|negativa|matr(i|í)cula)~iu', $kwn, $m)) {
    $key = $m[0];
    $replacements = [
      'obito' => 'Óbito',
      'procuracao' => 'Procuração', 'procuracoes' => 'Procurações',
      'matricula' => 'Matrícula', 'onus' => 'Ônus',
    ];
    $keyShow = $replacements[$key] ?? ucfirst($key);
    $out[] = $keyShow;
  }

  return array_values(array_unique($out));
}

/* ======================== DISTRIBUIÇÃO (NOVO) ======================== */
/**
 * Encontra a equipe pela regra EXATA (atribuicao == tipo ==) sem curinga e sem LIKE.
 * Respeita prioridade ASC e somente equipes/regras ativas.
 */
function encontrarEquipePorRegra(PDO $conn, string $atribuicao, string $tipo): ?array {
  $sql = "SELECT r.*, e.nome AS equipe_nome
          FROM equipe_regras r
          JOIN equipes e ON e.id = r.equipe_id AND e.ativa = 1
          WHERE r.ativa = 1
            AND r.atribuicao = :a
            AND r.tipo = :t
          ORDER BY r.prioridade ASC, r.id ASC
          LIMIT 1";
  $st = $conn->prepare($sql);
  $st->execute([':a' => $atribuicao, ':t' => $tipo]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

/**
 * Escolhe o membro da equipe com menor carga atual (tarefas pendentes/em_andamento).
 * Empate: menor 'ordem' e, por fim, menor ID. Respeita 'carga_maxima_diaria' se preenchida.
 */
function escolherMembroParaEquipe(PDO $conn, int $equipeId): ?array {
  $membros = $conn->prepare("SELECT m.id, m.funcionario_id, m.ordem, m.carga_maxima_diaria,
                                    f.nome_completo, f.usuario
                             FROM equipe_membros m
                             JOIN funcionarios f ON f.id = m.funcionario_id
                             WHERE m.equipe_id=? AND m.ativo=1
                             ORDER BY m.ordem ASC, m.id ASC");
  $membros->execute([$equipeId]);
  $arr = $membros->fetchAll(PDO::FETCH_ASSOC);
  if (!$arr) return null;

  $calc = $conn->prepare("SELECT COUNT(*) FROM tarefas_pedido
                          WHERE funcionario_id=? AND status IN ('pendente','em_andamento')
                            AND DATE(criado_em)=CURRENT_DATE()");
  $geral = $conn->prepare("SELECT COUNT(*) FROM tarefas_pedido
                           WHERE funcionario_id=? AND status IN ('pendente','em_andamento')");

  $melhor = null; $melhorCarga = null;
  foreach ($arr as $m) {
    $geral->execute([$m['funcionario_id']]);
    $cargaTotal = (int)$geral->fetchColumn();

    $calc->execute([$m['funcionario_id']]);
    $cargaHoje = (int)$calc->fetchColumn();

    if (!is_null($m['carga_maxima_diaria']) && $m['carga_maxima_diaria'] >= 0 && $cargaHoje >= (int)$m['carga_maxima_diaria']) {
      continue;
    }

    if ($melhor===null || $cargaTotal < $melhorCarga) {
      $melhor = $m;
      $melhorCarga = $cargaTotal;
    }
  }

  if ($melhor===null) {
    foreach ($arr as $m) {
      $geral->execute([$m['funcionario_id']]);
      $cargaTotal = (int)$geral->fetchColumn();
      if ($melhor===null || $cargaTotal < $melhorCarga) { $melhor=$m; $melhorCarga=$cargaTotal; }
    }
  }
  return $melhor;
}

/** Cria a tarefa vinculada ao pedido para a equipe/membro selecionados. */
function criarTarefaParaPedido(PDO $conn, int $pedidoId, int $equipeId, ?int $funcionarioId): int {
  $st = $conn->prepare("INSERT INTO tarefas_pedido (pedido_id, equipe_id, funcionario_id, status)
                        VALUES (?,?,?, 'pendente')");
  $st->execute([$pedidoId, $equipeId, $funcionarioId]);
  return (int)$conn->lastInsertId();
}

/* ======================== Execução principal ======================== */
try {
  $conn = getDatabaseConnection();
  ensureSchema($conn);
  ensureSchemaDistribuicao($conn); // <<< garante tabelas de distribuição

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

  // Razão social (se disponível)
  $razao_social = null;
  try {
    $stRS = $conn->query("SELECT razao_social FROM cadastro_serventia LIMIT 1");
    if ($stRS && ($rowRS = $stRS->fetch(PDO::FETCH_ASSOC))) {
      $razao_social = trim((string)($rowRS['razao_social'] ?? '')) ?: null;
    }
  } catch (Throwable $eRS) { $razao_social = null; }

  // Transação principal
  $conn->beginTransaction();

  $os_id = null;
  if ($isento_ato) {
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
  $pedido_id = (int)$conn->lastInsertId();

  // 3) Log inicial
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
  $conn->prepare("INSERT INTO pedidos_certidao_status_log (pedido_id,status_anterior,novo_status,observacao,usuario,ip,user_agent)
                  VALUES (?,?,?,?,?,?,?)")
       ->execute([$pedido_id,null,'pendente','Pedido criado',$username,$ip,$ua]);

  // 4) QR
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

  // 5) OUTBOX + envio imediato
  $body = [
    'topic'            => 'pedido_criado',
    'protocolo'        => $protocolo,
    'token_publico'    => $token_publico,
    'atribuicao'       => $atribuicao,
    'tipo'             => $tipo,
    'status'           => 'pendente',
    'pedido_id'        => $pedido_id,
    'ordem_servico_id' => $os_id === null ? null : (int)$os_id,
    'isento_ato'       => $isento_ato ? true : false,
    'criado_em'        => date('c'),
    'resumo' => [
      'requerente' => mb_substr($requerente_nome,0,1,'UTF-8').'***',
      'total_os'   => (float)$total_os
    ],
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

  /* ======================== DISTRIBUIR TAREFA (NOVO) ======================== */
  $equipeRegra  = encontrarEquipePorRegra($conn, $atribuicao, $tipo);
  $tarefa_info  = null;
  if ($equipeRegra) {
    $equipeId   = (int)$equipeRegra['equipe_id'];
    $membro     = escolherMembroParaEquipe($conn, $equipeId);
    $funcId     = $membro ? (int)$membro['funcionario_id'] : null;
    $tarefaId   = criarTarefaParaPedido($conn, $pedido_id, $equipeId, $funcId);

    $tarefa_info = [
      'tarefa_id'          => $tarefaId,
      'equipe_id'          => $equipeId,
      'equipe_nome'        => $equipeRegra['equipe_nome'] ?? null,
      'funcionario_id'     => $funcId,
      'funcionario_nome'   => $membro['nome_completo'] ?? null,
      'funcionario_usuario'=> $membro['usuario'] ?? null,
    ];
  }

  if (ob_get_length()) ob_clean();
  echo json_encode([
    'success'=>true,
    'id'=>$pedido_id,
    'api_delivery'=>$apiDelivery,
    'url_publica'=>$urlPublica,
    'distribuicao'=>$tarefa_info ?: ['mensagem'=>'Nenhuma equipe/regra encontrada para esta combinação.']
  ]);

} catch(Throwable $e){
  if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
  if (ob_get_length()) ob_clean();
  echo json_encode(['error'=>'Erro ao salvar o pedido: '.$e->getMessage()]);
}
