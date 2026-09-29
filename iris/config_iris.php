<?php
/**
 * Atlas Iris — Extração de texto e dados de imagens/PDF (OCR via Gemini)
 * Núcleo: conexão, schema/migrações, CSRF, perfil, chave de API (criptografada),
 * configurações e CRUD de modelos. As demais partes ficam em lib/.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Fortaleza');

define('IRIS_VERSAO', '2.0.1');

if (!function_exists('array_is_list')) {   // PHP < 8.1
    function array_is_list(array $a) { $i = 0; foreach ($a as $k => $_) { if ($k !== $i++) return false; } return true; }
}

// Ajustes locais opcionais (não versionados): ex. define('IRIS_GEMINI_BASE', ...)
if (is_file(__DIR__ . '/config_local.php')) require_once __DIR__ . '/config_local.php';
if (!defined('IRIS_GEMINI_BASE')) define('IRIS_GEMINI_BASE', 'https://generativelanguage.googleapis.com/v1beta');

require_once __DIR__ . '/lib/gemini.php';
require_once __DIR__ . '/lib/prompts.php';
require_once __DIR__ . '/lib/validadores.php';
require_once __DIR__ . '/lib/tipos.php';
require_once __DIR__ . '/lib/historico.php';

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
function iris_dir_protegido($nome)
{
    $d = __DIR__ . '/' . $nome;
    if (!is_dir($d)) @mkdir($d, 0775, true);
    $ht = $d . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    return $d;
}
function iris_dir_base() { return __DIR__; }
function iris_dir_tmp() { return iris_dir_protegido('tmp'); }
function iris_dir_seg() { return iris_dir_protegido('seguranca'); }
function iris_dir_arquivos() { return iris_dir_protegido('arquivos'); }

/* ============================ Schema / migrações ============================ */
function iris_coluna_existe($tabela, $coluna)
{
    $t = iris_db()->real_escape_string($tabela);
    $c = iris_db()->real_escape_string($coluna);
    $r = iris_db()->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && $r->num_rows > 0;
}

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

    // v2.0 — novas colunas de configuração (guardas SHOW COLUMNS)
    $novas = [
        'modelo_verificacao'      => "VARCHAR(120) NULL",
        'modelo_reserva'          => "VARCHAR(120) NULL",
        'vocabulario'             => "TEXT NULL",
        'precisao_padrao'         => "VARCHAR(20) NOT NULL DEFAULT 'padrao'",
        'qualidade_px'            => "INT NOT NULL DEFAULT 2400",
        'permitir_escolha_modelo' => "TINYINT(1) NOT NULL DEFAULT 1",
        'guardar_arquivos'        => "TINYINT(1) NOT NULL DEFAULT 1",
        'retencao_dias'           => "INT NOT NULL DEFAULT 180",
        'paginas_simultaneas'     => "TINYINT NOT NULL DEFAULT 2",
        'ultima_limpeza'          => "DATETIME NULL",
    ];
    foreach ($novas as $col => $def)
        if (!iris_coluna_existe('iris_config', $col)) $conn->query("ALTER TABLE iris_config ADD COLUMN `$col` $def");

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
            ['gemini-3.1-flash-lite',  'Gemini 3.1 Flash Lite', 'Rápido e econômico — impressos e formulários', 1],
            ['gemini-3.5-flash',       'Gemini 3.5 Flash',      'Equilíbrio entre velocidade e qualidade', 0],
            ['gemini-3.1-pro-preview', 'Gemini 3.1 Pro',        'Máxima precisão — manuscritos e livros antigos', 0],
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
    // v2.0 — o Pro exige o sufixo -preview na API
    $r = $conn->query("SELECT id FROM iris_modelos WHERE identificador='gemini-3.1-pro-preview'");
    if ($r && $r->num_rows === 0) {
        $conn->query("UPDATE iris_modelos SET identificador='gemini-3.1-pro-preview' WHERE identificador='gemini-3.1-pro'");
        $conn->query("UPDATE iris_config SET modelo_padrao='gemini-3.1-pro-preview' WHERE modelo_padrao='gemini-3.1-pro'");
    }
    // modelo de verificação padrão (usado no modo Máxima precisão)
    $cfg = $conn->query("SELECT modelo_verificacao FROM iris_config WHERE id=1")->fetch_assoc();
    if (empty($cfg['modelo_verificacao'])) {
        $r = $conn->query("SELECT identificador FROM iris_modelos WHERE ativo=1 AND identificador LIKE '%pro%' ORDER BY id LIMIT 1");
        $row = $r ? $r->fetch_assoc() : null;
        if ($row) {
            $st = $conn->prepare("UPDATE iris_config SET modelo_verificacao=? WHERE id=1");
            $st->bind_param('s', $row['identificador']); $st->execute(); $st->close();
        }
    }

    iris_tipos_schema();
    iris_historico_schema();
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
function iris_usuario() { return (string)($_SESSION['username'] ?? ''); }
function iris_nivel_acesso()
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $u = iris_usuario();
    if ($u === '') return $cache = '';
    try {
        $st = iris_db()->prepare("SELECT nivel_de_acesso FROM funcionarios WHERE usuario=? LIMIT 1");
        $st->bind_param('s', $u); $st->execute();
        $r = $st->get_result()->fetch_assoc(); $st->close();
        return $cache = (string)($r['nivel_de_acesso'] ?? '');
    } catch (Throwable $e) { return $cache = ''; }
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
function iris_config($recarregar = false)
{
    static $cfg = null;
    if ($cfg !== null && !$recarregar) return $cfg;
    iris_ensure_schema();
    $r = iris_db()->query("SELECT * FROM iris_config WHERE id=1 LIMIT 1");
    return $cfg = ($r ? ($r->fetch_assoc() ?: []) : []);
}
function iris_config_set($campos)
{
    iris_ensure_schema();
    $permitidos = ['api_key_enc', 'modelo_padrao', 'prompt_extra', 'modelo_verificacao', 'modelo_reserva', 'vocabulario',
                   'precisao_padrao', 'qualidade_px', 'permitir_escolha_modelo', 'guardar_arquivos', 'retencao_dias',
                   'paginas_simultaneas', 'ultima_limpeza'];
    $sets = []; $vals = []; $types = '';
    foreach ($campos as $k => $v) {
        if (!in_array($k, $permitidos, true)) continue;
        $sets[] = "`$k`=?"; $vals[] = $v; $types .= 's';
    }
    if (!$sets) return;
    $sets[] = "atualizado_em=?"; $vals[] = date('Y-m-d H:i:s'); $types .= 's';
    $st = iris_db()->prepare("UPDATE iris_config SET " . implode(',', $sets) . " WHERE id=1");
    $st->bind_param($types, ...$vals); $st->execute(); $st->close();
    iris_config(true);
}
function iris_api_key() { return iris_dec(iris_config()['api_key_enc'] ?? ''); }
function iris_tem_chave() { return iris_api_key() !== ''; }

/** Configuração pública (sem segredos) usada pela interface. */
function iris_config_publica()
{
    $c = iris_config();
    return [
        'precisao_padrao'         => ($c['precisao_padrao'] ?? 'padrao') === 'maxima' ? 'maxima' : 'padrao',
        'qualidade_px'            => max(1200, min(4000, (int)($c['qualidade_px'] ?? 2400))),
        'permitir_escolha_modelo' => (int)($c['permitir_escolha_modelo'] ?? 1) === 1,
        'guardar_arquivos'        => (int)($c['guardar_arquivos'] ?? 1) === 1,
        'paginas_simultaneas'     => max(1, min(4, (int)($c['paginas_simultaneas'] ?? 2))),
        'tem_verificacao'         => !empty($c['modelo_verificacao']),
        'max_upload'              => iris_max_upload(),
    ];
}
function iris_ini_bytes($v)
{
    $v = trim((string)$v); if ($v === '') return 0;
    $n = (float)$v; $u = strtolower(substr($v, -1));
    if ($u === 'g') $n *= 1073741824; elseif ($u === 'm') $n *= 1048576; elseif ($u === 'k') $n *= 1024;
    return (int)$n;
}
/** Maior upload aceito pelo PHP (menor entre upload_max_filesize e post_max_size). */
function iris_max_upload()
{
    $a = iris_ini_bytes(ini_get('upload_max_filesize')); $b = iris_ini_bytes(ini_get('post_max_size'));
    $m = min($a ?: PHP_INT_MAX, $b ?: PHP_INT_MAX);
    return $m === PHP_INT_MAX ? 20 * 1048576 : $m;
}

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
    if ($id !== '') { $m = iris_modelo_por_id($id); if ($m) return $m; }
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
/** Modelo usado na extração: sempre o padrão definido em Configurar (o pedido do cliente é ignorado). */
function iris_modelo_escolhido($pedido = '')
{
    $m = iris_modelo_padrao();
    if (!$m) throw new RuntimeException('Nenhum modelo de extração configurado.');
    return $m['identificador'];
}
function iris_modelo_add($identificador, $rotulo, $descricao)
{
    iris_ensure_schema();
    $identificador = trim($identificador); $rotulo = trim($rotulo);
    if ($identificador === '') throw new RuntimeException('Informe o identificador do modelo (ex.: gemini-3.1-pro-preview).');
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
    $st = iris_db()->prepare("UPDATE iris_modelos SET rotulo=?, descricao=? WHERE id=?");
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
    if ((int)$m['padrao'] === 1) {
        $novo = $conn->query("SELECT identificador FROM iris_modelos WHERE ativo=1 ORDER BY id ASC LIMIT 1");
        $row = $novo ? $novo->fetch_assoc() : null;
        if ($row) iris_set_padrao_id_por_identificador($row['identificador']);
    }
    // limpa referências de verificação/reserva
    $cfg = iris_config(true);
    if (($cfg['modelo_verificacao'] ?? '') === $m['identificador']) iris_config_set(['modelo_verificacao' => null]);
    if (($cfg['modelo_reserva'] ?? '') === $m['identificador']) iris_config_set(['modelo_reserva' => null]);
}
function iris_set_padrao($id)
{
    $st = iris_db()->prepare("SELECT identificador FROM iris_modelos WHERE id=? AND ativo=1 LIMIT 1");
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

/* ============================ Utilidades ============================ */
/** Mimes aceitos para envio à IA. TIFF é convertido no navegador antes do envio. */
function iris_mimes_aceitos()
{
    return ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg',
            'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif'];
}
/** Mimes aceitos para guardar o original no histórico. */
function iris_mimes_originais()
{
    return iris_mimes_aceitos() + ['image/tiff' => 'tif'];
}
function iris_human($n) { $n = (int)$n; if ($n < 1024) return $n . ' B'; if ($n < 1048576) return round($n / 1024, 1) . ' KB'; return round($n / 1048576, 1) . ' MB'; }

/** Mantido por compatibilidade com a v1 (extrair.php antigo). */
function iris_prompt_padrao() { return iris_prompt_transcricao([]); }
