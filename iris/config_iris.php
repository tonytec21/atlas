<?php
/**
 * Atlas Iris — Extração de dados de imagem/PDF para texto (OCR via Gemini)
 * Núcleo: conexão, schema, CSRF, chave de API (criptografada), CRUD de modelos
 * e a chamada de extração ao Gemini.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Fortaleza');

/* ============================ Conexão ============================ */
function iris_db()
{
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;
    require __DIR__ . '/db_connection.php';   // define $conn (base atlas)
    $conn->set_charset('utf8mb4');
    return $conn;
}

/* ============================ Diretórios protegidos ============================ */
function iris_dir_base() { return __DIR__; }
function iris_dir_tmp()
{
    $d = __DIR__ . '/tmp';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    $ht = $d . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    return $d;
}
function iris_dir_seg()
{
    $d = __DIR__ . '/seguranca';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    $ht = $d . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    return $d;
}

/* ============================ Schema ============================ */
function iris_ensure_schema()
{
    static $ok = false;
    if ($ok) return;
    $conn = iris_db();

    $conn->query("CREATE TABLE IF NOT EXISTS iris_config (
        id TINYINT PRIMARY KEY DEFAULT 1,
        api_key_enc TEXT NULL,
        modelo_padrao VARCHAR(120) NULL,
        prompt_extra TEXT NULL,
        atualizado_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $r = $conn->query("SELECT id FROM iris_config WHERE id=1");
    if ($r && $r->num_rows === 0) $conn->query("INSERT INTO iris_config (id) VALUES (1)");

    $conn->query("CREATE TABLE IF NOT EXISTS iris_modelos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identificador VARCHAR(120) NOT NULL UNIQUE,
        rotulo VARCHAR(160) NOT NULL,
        descricao VARCHAR(255) NULL,
        padrao TINYINT(1) NOT NULL DEFAULT 0,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Semeia os modelos padrão (uma vez). O identificador é o nome de API do Gemini.
    $c = $conn->query("SELECT COUNT(*) AS n FROM iris_modelos")->fetch_assoc();
    if ((int)$c['n'] === 0) {
        $seed = [
            ['gemini-3.1-flash-lite', 'Gemini 3.1 Flash Lite', 'Rápido e econômico — padrão de extração', 1],
            ['gemini-3.5-flash',      'Gemini 3.5 Flash',      'Equilíbrio entre velocidade e qualidade', 0],
            ['gemini-3.1-pro',        'Gemini 3.1 Pro',        'Máxima precisão para documentos difíceis', 0],
        ];
        $st = $conn->prepare("INSERT INTO iris_modelos (identificador, rotulo, descricao, padrao, ativo, criado_em) VALUES (?,?,?,?,1,?)");
        foreach ($seed as $m) {
            $agora = date('Y-m-d H:i:s');
            $st->bind_param('sssis', $m[0], $m[1], $m[2], $m[3], $agora);
            $st->execute();
        }
        $st->close();
        $conn->query("UPDATE iris_config SET modelo_padrao='gemini-3.1-flash-lite' WHERE id=1");
    }
    $ok = true;
}

/* ============================ CSRF ============================ */
function iris_csrf()
{
    if (empty($_SESSION['iris_csrf'])) $_SESSION['iris_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['iris_csrf'];
}
function iris_csrf_check($t) { return is_string($t) && !empty($_SESSION['iris_csrf']) && hash_equals($_SESSION['iris_csrf'], $t); }

/* ============================ Perfil / Administrador ============================ */
function iris_nivel_acesso()
{
    $u = $_SESSION['username'] ?? '';
    if ($u === '') return '';
    try {
        $st = iris_db()->prepare("SELECT nivel_de_acesso FROM funcionarios WHERE usuario=? LIMIT 1");
        $st->bind_param('s', $u); $st->execute();
        $r = $st->get_result()->fetch_assoc(); $st->close();
        return $r['nivel_de_acesso'] ?? '';
    } catch (Throwable $e) { return ''; }
}
function iris_is_admin()
{
    $n = mb_strtolower(trim(iris_nivel_acesso()));
    return in_array($n, ['administrador', 'admin', 'adm', 'administrator', 'master', 'root'], true);
}
function iris_require_admin()
{
    if (!iris_is_admin()) throw new RuntimeException('Acesso restrito ao administrador.');
}

/* ============================ Chave de API (AES-256-GCM) ============================ */
function iris_key()
{
    $f = iris_dir_seg() . '/.masterkey';
    if (!is_file($f)) @file_put_contents($f, base64_encode(random_bytes(32)));
    return base64_decode(trim(file_get_contents($f)));
}
function iris_enc($plain)
{
    if ($plain === '' || $plain === null) return null;
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', iris_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ct);
}
function iris_dec($blob)
{
    if (!$blob) return '';
    $raw = base64_decode($blob, true); if ($raw === false || strlen($raw) < 28) return '';
    $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $ct = substr($raw, 28);
    $pt = openssl_decrypt($ct, 'aes-256-gcm', iris_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $pt === false ? '' : $pt;
}

/* ============================ Config ============================ */
function iris_config()
{
    iris_ensure_schema();
    $r = iris_db()->query("SELECT * FROM iris_config WHERE id=1 LIMIT 1");
    return $r ? $r->fetch_assoc() : [];
}
function iris_config_set($campos)
{
    iris_ensure_schema();
    $conn = iris_db();
    $sets = []; $vals = []; $types = '';
    foreach ($campos as $k => $v) { $sets[] = "`$k`=?"; $vals[] = $v; $types .= 's'; }
    $sets[] = "atualizado_em=?"; $vals[] = date('Y-m-d H:i:s'); $types .= 's';
    $st = $conn->prepare("UPDATE iris_config SET " . implode(',', $sets) . " WHERE id=1");
    $st->bind_param($types, ...$vals); $st->execute(); $st->close();
}
function iris_api_key() { return iris_dec(iris_config()['api_key_enc'] ?? ''); }
function iris_tem_chave() { return iris_api_key() !== ''; }

/* ============================ Modelos ============================ */
function iris_modelos($somenteAtivos = false)
{
    iris_ensure_schema();
    $sql = "SELECT * FROM iris_modelos" . ($somenteAtivos ? " WHERE ativo=1" : "") . " ORDER BY padrao DESC, rotulo ASC";
    $out = []; $r = iris_db()->query($sql);
    while ($r && $row = $r->fetch_assoc()) $out[] = $row;
    return $out;
}
function iris_modelo_padrao()
{
    $cfg = iris_config();
    $id = $cfg['modelo_padrao'] ?? '';
    if ($id !== '') {
        $st = iris_db()->prepare("SELECT * FROM iris_modelos WHERE identificador=? AND ativo=1 LIMIT 1");
        $st->bind_param('s', $id); $st->execute();
        $m = $st->get_result()->fetch_assoc(); $st->close();
        if ($m) return $m;
    }
    $r = iris_db()->query("SELECT * FROM iris_modelos WHERE ativo=1 ORDER BY padrao DESC, id ASC LIMIT 1");
    return $r ? $r->fetch_assoc() : null;
}
function iris_modelo_por_id($identificador)
{
    $st = iris_db()->prepare("SELECT * FROM iris_modelos WHERE identificador=? AND ativo=1 LIMIT 1");
    $st->bind_param('s', $identificador); $st->execute();
    $m = $st->get_result()->fetch_assoc(); $st->close();
    return $m ?: null;
}
function iris_modelo_add($identificador, $rotulo, $descricao)
{
    iris_ensure_schema();
    $identificador = trim($identificador); $rotulo = trim($rotulo);
    if ($identificador === '') throw new RuntimeException('Informe o identificador do modelo (ex.: gemini-3.1-pro).');
    if (!preg_match('~^[A-Za-z0-9._\-]+$~', $identificador)) throw new RuntimeException('Identificador inválido. Use letras, números, ponto, hífen ou underline.');
    if ($rotulo === '') $rotulo = $identificador;
    $conn = iris_db();
    $ja = $conn->prepare("SELECT id FROM iris_modelos WHERE identificador=? LIMIT 1");
    $ja->bind_param('s', $identificador); $ja->execute();
    if ($ja->get_result()->num_rows > 0) { $ja->close(); throw new RuntimeException('Já existe um modelo com esse identificador.'); }
    $ja->close();
    $agora = date('Y-m-d H:i:s');
    $st = $conn->prepare("INSERT INTO iris_modelos (identificador, rotulo, descricao, padrao, ativo, criado_em) VALUES (?,?,?,0,1,?)");
    $st->bind_param('ssss', $identificador, $rotulo, $descricao, $agora);
    $st->execute(); $id = $st->insert_id; $st->close();
    return $id;
}
function iris_modelo_update($id, $rotulo, $descricao)
{
    $conn = iris_db();
    $st = $conn->prepare("UPDATE iris_modelos SET rotulo=?, descricao=? WHERE id=?");
    $rotulo = trim($rotulo);
    $st->bind_param('ssi', $rotulo, $descricao, $id); $st->execute(); $st->close();
}
function iris_modelo_del($id)
{
    $conn = iris_db();
    $st = $conn->prepare("SELECT identificador, padrao FROM iris_modelos WHERE id=? LIMIT 1");
    $st->bind_param('i', $id); $st->execute();
    $m = $st->get_result()->fetch_assoc(); $st->close();
    if (!$m) throw new RuntimeException('Modelo não encontrado.');
    $tot = $conn->query("SELECT COUNT(*) AS n FROM iris_modelos")->fetch_assoc();
    if ((int)$tot['n'] <= 1) throw new RuntimeException('É necessário manter ao menos um modelo cadastrado.');
    $st = $conn->prepare("DELETE FROM iris_modelos WHERE id=?");
    $st->bind_param('i', $id); $st->execute(); $st->close();
    // se era o padrão, elege outro
    if ((int)$m['padrao'] === 1) {
        $novo = $conn->query("SELECT identificador FROM iris_modelos WHERE ativo=1 ORDER BY id ASC LIMIT 1");
        $row = $novo ? $novo->fetch_assoc() : null;
        if ($row) { iris_set_padrao_id_por_identificador($row['identificador']); }
    }
}
function iris_set_padrao($id)
{
    $conn = iris_db();
    $st = $conn->prepare("SELECT identificador FROM iris_modelos WHERE id=? AND ativo=1 LIMIT 1");
    $st->bind_param('i', $id); $st->execute();
    $m = $st->get_result()->fetch_assoc(); $st->close();
    if (!$m) throw new RuntimeException('Modelo não encontrado.');
    iris_set_padrao_id_por_identificador($m['identificador']);
}
function iris_set_padrao_id_por_identificador($identificador)
{
    $conn = iris_db();
    $conn->query("UPDATE iris_modelos SET padrao=0");
    $st = $conn->prepare("UPDATE iris_modelos SET padrao=1 WHERE identificador=?");
    $st->bind_param('s', $identificador); $st->execute(); $st->close();
    iris_config_set(['modelo_padrao' => $identificador]);
}

/* ============================ Extração (Gemini) ============================ */
function iris_prompt_padrao()
{
    return
        "Você é um sistema de OCR e transcrição de altíssima precisão. Sua tarefa é transcrever, "
      . "na ÍNTEGRA e de forma absolutamente fiel, TODO o texto contido no arquivo anexado (imagem ou PDF), "
      . "SEM OMITIR NENHUMA PARTE.\n\n"
      . "Transcreva TODOS os tipos de texto, incluindo:\n"
      . "- Texto impresso (digitado);\n"
      . "- Texto MANUSCRITO (escrito à mão) — leia com atenção e transcreva, mesmo quando a caligrafia for difícil;\n"
      . "- Campos de formulário preenchidos à mão;\n"
      . "- Carimbos, selos e assinaturas (transcreva o que estiver legível);\n"
      . "- Anotações nas margens, cabeçalhos, rodapés, números de página e qualquer texto pequeno.\n\n"
      . "Regras obrigatórias:\n"
      . "1. Não resuma, não interprete, não corrija e não comente — apenas transcreva o que está escrito.\n"
      . "2. Preserve a ordem de leitura, as quebras de linha e a separação de parágrafos.\n"
      . "3. Mantenha acentuação, pontuação, números, símbolos e maiúsculas/minúsculas como no original.\n"
      . "4. Em documentos com várias páginas, transcreva TODAS, na ordem, sem pular nenhuma.\n"
      . "5. Examine o documento inteiro, inclusive bordas e áreas menos nítidas — não deixe nada de fora.\n"
      . "6. Só escreva [ilegível] se for realmente impossível decifrar, e apenas na palavra específica — nunca descarte trechos inteiros.\n"
      . "7. NÃO reproduza elementos gráficos que não são texto: linhas horizontais, sublinhados de campos, tracejados, pontilhados de preenchimento, bordas de tabela ou molduras. NÃO use sequências de hífens (---), underscores (___) ou pontos (...) para representar linhas ou campos em branco — se um campo estiver em branco, simplesmente não escreva nada ali.\n\n"
      . "Responda SOMENTE com o texto transcrito, sem nenhuma introdução, observação ou formatação adicional.";
}


/** Localiza um CA bundle (cacert.pem) para o cURL — necessário no XAMPP/Windows. */
function iris_cacert()
{
    $env = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($env && is_file($env)) return $env;
    foreach ([
        'C:/xampp/apache/bin/curl-ca-bundle.crt',
        'C:/xampp/php/extras/ssl/cacert.pem',
        'C:/xampp/perl/vendor/lib/Mozilla/CA/cacert.pem',
        __DIR__ . '/cacert.pem',
        '/etc/ssl/certs/ca-certificates.crt',
    ] as $c) if (@is_file($c)) return $c;
    return null;
}

/**
 * Chama o Gemini generateContent enviando o arquivo inline (base64).
 * Robusto: pensamento reduzido + saída ampla (evita respostas vazias nos
 * modelos 3.x com "thinking") e SSL tolerante ao XAMPP.
 * @return array ['texto'=>..., 'truncado'=>bool]
 */
function iris_gemini_ocr($apiKey, $modelo, $bytes, $mime, $prompt)
{
    if ($apiKey === '') throw new RuntimeException('Configure a chave da API do Gemini em "Configurar".');
    if (!function_exists('curl_init')) throw new RuntimeException('A extensão cURL do PHP não está habilitada.');

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($modelo) . ':generateContent?key=' . urlencode($apiKey);
    $partes = [
        ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
        ['text' => $prompt],
    ];
    // Alta resolução de mídia (lê manuscrito/texto fino) + raciocínio alto + saída ampla.
    // Fallbacks progressivos caso o modelo rejeite algum parâmetro.
    $configs = [
        ['maxOutputTokens' => 65536, 'thinkingConfig' => ['thinkingLevel' => 'high'], 'mediaResolution' => 'MEDIA_RESOLUTION_HIGH'],
        ['maxOutputTokens' => 32768, 'mediaResolution' => 'MEDIA_RESOLUTION_HIGH'],
        ['maxOutputTokens' => 8192],
    ];

    $ca = iris_cacert();
    $ultimoErro = '';
    foreach ($configs as $i => $gen) {
        $payload = ['contents' => [['parts' => $partes]], 'generationConfig' => $gen];
        $ch = curl_init($url);
        $opts = [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 240,
            CURLOPT_CONNECTTIMEOUT => 20,
        ];
        if ($ca) { $opts[CURLOPT_SSL_VERIFYPEER] = true; $opts[CURLOPT_CAINFO] = $ca; }
        else { $opts[CURLOPT_SSL_VERIFYPEER] = false; $opts[CURLOPT_SSL_VERIFYHOST] = 0; }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) throw new RuntimeException('Falha de conexão com a API Gemini: ' . $cerr);
        $j = json_decode($resp, true);

        if ($code === 400 && $i < count($configs) - 1) { $ultimoErro = $j['error']['message'] ?? 'HTTP 400'; continue; }
        if ($code !== 200) {
            $msg = $j['error']['message'] ?? ('Erro HTTP ' . $code);
            if ($code === 400 && stripos($msg, 'API key') !== false) $msg = 'Chave da API inválida.';
            if ($code === 403) $msg = 'Acesso negado (verifique a chave e as permissões da API).';
            if ($code === 404) $msg = 'Modelo "' . $modelo . '" não encontrado. Ajuste o identificador em Configurar.';
            if ($code === 429) $msg = 'Limite de uso da API atingido. Aguarde e tente de novo.';
            throw new RuntimeException('Gemini: ' . $msg);
        }
        if (isset($j['promptFeedback']['blockReason']))
            throw new RuntimeException('A extração foi bloqueada pela política do Gemini (' . $j['promptFeedback']['blockReason'] . ').');

        $text = '';
        foreach ($j['candidates'][0]['content']['parts'] ?? [] as $p)
            if (isset($p['text'])) $text .= $p['text'];
        $finish = $j['candidates'][0]['finishReason'] ?? '';

        if ($text === '' && $finish === 'MAX_TOKENS')
            throw new RuntimeException('O documento é extenso demais para uma única resposta. Divida em partes menores e extraia separadamente.');
        if ($text === '') throw new RuntimeException('A API não retornou texto' . ($finish ? ' (motivo: ' . $finish . ')' : '') . '. Tente outro modelo em Configurar.');

        return ['texto' => $text, 'truncado' => ($finish === 'MAX_TOKENS')];
    }
    throw new RuntimeException('Gemini: ' . ($ultimoErro ?: 'não foi possível processar a requisição.'));
}

/** Mimes aceitos para extração. */
function iris_mimes_aceitos()
{
    return ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg',
            'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif'];
}
function iris_human($n) { $n = (int)$n; if ($n < 1024) return $n . ' B'; if ($n < 1048576) return round($n / 1024, 1) . ' KB'; return round($n / 1048576, 1) . ' MB'; }
