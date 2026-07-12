<?php
/**
 * helpers.php — Núcleo do módulo Anotações (Atlas)
 * Notas continuam em arquivos: lembretes/{usuario}/{id}.txt (+ {id}.json p/ cor).
 * Compartilhamento é registrado na tabela notas_compartilhadas.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
@ini_set('display_errors', 0);
date_default_timezone_set('America/Fortaleza');

function notas_db()
{
    static $c = null;
    if ($c instanceof mysqli && @$c->ping()) return $c;
    $c = new mysqli('localhost', 'root', '', 'atlas');
    if ($c->connect_error) throw new RuntimeException('Falha na conexão com o banco.');
    $c->set_charset('utf8mb4');
    return $c;
}

function notas_ensure_schema()
{
    try {
        notas_db()->query("CREATE TABLE IF NOT EXISTS notas_compartilhadas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner VARCHAR(120) NOT NULL,
            note_id VARCHAR(64) NOT NULL,
            shared_with VARCHAR(120) NOT NULL,
            can_edit TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            UNIQUE KEY uniq_share (owner, note_id, shared_with),
            INDEX idx_shared_with (shared_with),
            INDEX idx_owner_note (owner, note_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { /* não bloqueia */ }
}

/* ------------------------------------------------ HTML seguro (negrito/itálico/sublinhado) */
/** Mantém apenas <b><strong><i><em><u><br>; converte blocos em <br>; remove atributos. */
function notas_sanitize_html($html)
{
    $html = (string)$html;
    if ($html === '') return '';
    // normaliza quebras
    $html = preg_replace('~<\s*br\s*/?\s*>~i', "<br>", $html);
    // blocos viram quebras
    $html = preg_replace('~<\s*/(div|p)\s*>~i', "<br>", $html);
    $html = preg_replace('~<\s*(div|p)[^>]*>~i', "", $html);
    // mantém só as tags permitidas
    $html = strip_tags($html, '<b><strong><i><em><u><br>');
    // remove atributos das tags permitidas
    $html = preg_replace('~<\s*(b|strong|i|em|u)\b[^>]*>~i', '<$1>', $html);
    // normaliza strong/em -> b/i (opcional, mantém consistência)
    $html = preg_replace(['~<\s*strong\s*>~i','~<\s*/\s*strong\s*>~i','~<\s*em\s*>~i','~<\s*/\s*em\s*>~i'],
                         ['<b>','</b>','<i>','</i>'], $html);
    // colapsa 3+ <br> em no máximo 2
    $html = preg_replace('~(<br>\s*){3,}~', '<br><br>', $html);
    // limite de tamanho
    if (mb_strlen($html) > 20000) $html = mb_substr($html, 0, 20000);
    return trim($html);
}

/** Converte o conteúdo armazenado (antigo texto puro OU HTML) em HTML seguro p/ exibir. */
function notas_conteudo_html($stored)
{
    $stored = (string)$stored;
    // se não tem tags, é texto puro antigo -> escapa e transforma \n em <br>
    if (!preg_match('~<(b|strong|i|em|u|br)\b~i', $stored)) {
        return nl2br(htmlspecialchars($stored, ENT_QUOTES, 'UTF-8'));
    }
    return notas_sanitize_html($stored);
}

/** Texto puro (sem tags) para busca/prévia. */
function notas_conteudo_texto($stored)
{
    $s = preg_replace('~<br>~i', "\n", (string)$stored);
    return trim(html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8'));
}

/* ------------------------------------------------ categorias / organização (por usuário) */
function notas_org_path($username) { return notas_user_dir($username) . '/_org.json'; }

/** Retorna ['cats'=>[...], 'notes'=>{id=>['cat'=>..,'ord'=>..]}]. */
function notas_org_get($username)
{
    $p = notas_org_path($username);
    // migra categorias da versão antiga (order.json -> _org.json) uma única vez
    if (!is_file($p)) notas_migrar_order_json($username);

    $data = ['cats' => [], 'notes' => []];
    if (is_file($p)) {
        $d = json_decode(file_get_contents($p), true);
        if (is_array($d)) {
            $data['cats']  = isset($d['cats']) && is_array($d['cats']) ? array_values($d['cats']) : [];
            $data['notes'] = isset($d['notes']) && is_array($d['notes']) ? $d['notes'] : [];
        }
    }
    return $data;
}

/**
 * Migra as categorias (grupos) da versão antiga.
 * Antigo: lembretes/{user}/order.json = { "groups": { "Nome": ["id.txt",...], "Novos": [...] } }
 * "Novos" era o grupo padrão (sem categoria).
 */
function notas_migrar_order_json($username)
{
    $p = notas_org_path($username);
    if (is_file($p)) return;                      // já migrado / já existe organização nova
    $old = notas_user_dir($username) . '/order.json';
    if (!is_file($old)) return;

    $data = json_decode(file_get_contents($old), true);
    if (!is_array($data) || empty($data['groups']) || !is_array($data['groups'])) return;

    $cats = []; $notes = []; $ord = 0;
    foreach ($data['groups'] as $groupName => $files) {
        $nome = trim(str_replace(["\r", "\n"], ' ', (string)$groupName));
        $isNovos = ($nome === 'Novos' || $nome === '');
        if (!$isNovos && !in_array($nome, $cats, true)) $cats[] = $nome;
        if (!is_array($files)) continue;
        foreach ($files as $fn) {
            $id = notas_safe_id(basename((string)$fn, '.txt'));
            if ($id === '') continue;
            $notes[$id] = ['cat' => $isNovos ? '' : $nome, 'ord' => $ord++];
        }
    }
    notas_org_save($username, ['cats' => $cats, 'notes' => $notes]);
}

function notas_org_save($username, $data)
{
    $p = notas_org_path($username);
    $out = ['cats' => array_values($data['cats'] ?? []), 'notes' => $data['notes'] ?? []];
    return file_put_contents($p, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

/* ------------------------------------------------ CSRF */
function notas_csrf()
{
    if (empty($_SESSION['notas_csrf'])) $_SESSION['notas_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['notas_csrf'];
}
function notas_csrf_check($t)
{
    return is_string($t) && !empty($_SESSION['notas_csrf']) && hash_equals($_SESSION['notas_csrf'], $t);
}

/* ------------------------------------------------ paths */
function notas_user_dir($username)
{
    $safe = preg_replace('~[^A-Za-z0-9_\-\.]~', '_', (string)$username);
    $d = __DIR__ . '/lembretes/' . $safe;
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}
function notas_safe_id($id) { return preg_replace('~[^A-Za-z0-9_\-]~', '', (string)$id); }

/** Paleta de cores dos post-its (nome => hex). */
function notas_paleta()
{
    return [
        'amarelo'  => '#FEF08A',
        'limao'    => '#D9F99D',
        'menta'    => '#A7F3D0',
        'azul'     => '#BAE6FD',
        'lavanda'  => '#DDD6FE',
        'rosa'     => '#FBCFE8',
        'coral'    => '#FECACA',
        'pessego'  => '#FED7AA',
        'branco'   => '#F8FAFC',
    ];
}
function notas_cor_valida($hex, $fallback = '#FEF08A')
{
    $hex = strtoupper(trim((string)$hex));
    if (preg_match('~^#([0-9A-F]{6})$~', $hex)) return $hex;
    // aceita nome da paleta
    $p = notas_paleta();
    $lk = strtolower((string)$hex);
    if (isset($p[$lk])) return $p[$lk];
    return $fallback;
}

/* ------------------------------------------------ leitura de nota */
/** Lê uma nota do dono. Retorna [id,title,content,color,updated,owner] ou null. */
function notas_ler($owner, $noteId)
{
    $noteId = notas_safe_id($noteId);
    if ($noteId === '') return null;
    $dir = notas_user_dir($owner);
    $txt = $dir . '/' . $noteId . '.txt';
    if (!is_file($txt)) return null;

    $raw = file_get_contents($txt);
    $title = ''; $body = '';
    $linhas = explode("\n", $raw);
    $capturing = false; $bodyLines = [];
    foreach ($linhas as $ln) {
        if (!$capturing && stripos($ln, 'Título:') === 0) { $title = trim(substr($ln, strlen('Título:'))); continue; }
        if (!$capturing && stripos($ln, 'Conteúdo:') === 0) { $capturing = true; $rest = trim(substr($ln, strlen('Conteúdo:'))); if ($rest !== '') $bodyLines[] = $rest; continue; }
        if ($capturing) $bodyLines[] = $ln;
    }
    $body = rtrim(implode("\n", $bodyLines));
    if ($title === '' && $body === '') { $body = trim($raw); }

    $color = '#FEF08A';
    $cj = $dir . '/' . $noteId . '.json';
    if (is_file($cj)) {
        $d = json_decode(file_get_contents($cj), true);
        if (is_array($d) && !empty($d['cor'])) $color = notas_cor_valida($d['cor']);
    }
    return [
        'id' => $noteId, 'owner' => $owner,
        'title' => $title,
        'content' => $body,                              // bruto (pode ser HTML)
        'content_html' => notas_conteudo_html($body),    // seguro p/ exibir/editar
        'content_text' => notas_conteudo_texto($body),   // p/ busca/prévia
        'color' => $color, 'updated' => @filemtime($txt) ?: time(),
    ];
}

/** Grava conteúdo da nota (título texto puro; conteúdo HTML seguro). */
function notas_gravar($owner, $noteId, $title, $content)
{
    $noteId = notas_safe_id($noteId);
    $dir = notas_user_dir($owner);
    $txt = $dir . '/' . $noteId . '.txt';
    $title = trim(strip_tags((string)$title));
    $content = notas_sanitize_html($content);
    $data = "Título: " . $title . "\n\nConteúdo:\n" . $content;
    return file_put_contents($txt, $data) !== false;
}

function notas_set_cor($owner, $noteId, $hex)
{
    $noteId = notas_safe_id($noteId);
    $dir = notas_user_dir($owner);
    $cor = notas_cor_valida($hex);
    return file_put_contents($dir . '/' . $noteId . '.json', json_encode(['id' => $noteId, 'cor' => $cor], JSON_PRETTY_PRINT)) !== false;
}

/* ------------------------------------------------ listagens */
function notas_listar_proprias($username)
{
    $dir = notas_user_dir($username);
    $org = notas_org_get($username);
    $out = [];
    foreach (glob($dir . '/*.txt') as $f) {
        $id = basename($f, '.txt');
        $n = notas_ler($username, $id);
        if (!$n) continue;
        $meta = $org['notes'][$id] ?? [];
        $n['cat'] = isset($meta['cat']) ? (string)$meta['cat'] : '';
        $n['ord'] = isset($meta['ord']) ? (int)$meta['ord'] : PHP_INT_MAX;
        $out[] = $n;
    }
    // ordena por ordem manual (ord) e, empatando, por mais recente
    usort($out, function ($a, $b) {
        if ($a['ord'] !== $b['ord']) return $a['ord'] <=> $b['ord'];
        return $b['updated'] <=> $a['updated'];
    });
    return $out;
}

/** Notas que foram compartilhadas COM este usuário. */
function notas_listar_compartilhadas_comigo($username)
{
    notas_ensure_schema();
    $conn = notas_db();
    $out = [];
    $stmt = $conn->prepare("SELECT owner, note_id, can_edit, created_at FROM notas_compartilhadas WHERE shared_with=? ORDER BY created_at DESC");
    $stmt->bind_param('s', $username); $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        $n = notas_ler($row['owner'], $row['note_id']);
        if ($n) { $n['can_edit'] = (int)$row['can_edit']; $n['shared_by'] = $row['owner']; $out[] = $n; }
    }
    $stmt->close();
    return $out;
}

/** Com quem uma nota está compartilhada. */
function notas_compartilhamentos($owner, $noteId)
{
    notas_ensure_schema();
    $conn = notas_db();
    $noteId = notas_safe_id($noteId);
    $out = [];
    $stmt = $conn->prepare("SELECT shared_with, can_edit FROM notas_compartilhadas WHERE owner=? AND note_id=? ORDER BY shared_with");
    $stmt->bind_param('ss', $owner, $noteId); $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $out[] = $row;
    $stmt->close();
    return $out;
}

/** Acesso de leitura: dono ou destinatário do compartilhamento. */
function notas_pode_ler($username, $owner, $noteId)
{
    if ($username === $owner) return true;
    notas_ensure_schema();
    $conn = notas_db();
    $noteId = notas_safe_id($noteId);
    $stmt = $conn->prepare("SELECT 1 FROM notas_compartilhadas WHERE owner=? AND note_id=? AND shared_with=? LIMIT 1");
    $stmt->bind_param('sss', $owner, $noteId, $username); $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0; $stmt->close();
    return $ok;
}

/* ------------------------------------------------ usuários (para compartilhar) */
function notas_usuarios($excluir = '')
{
    $conn = notas_db();
    $out = [];
    // tenta funcionarios (usuario, nome); cai para o que houver
    try {
        $cols = [];
        $rc = $conn->query("SHOW COLUMNS FROM funcionarios");
        while ($rc && $row = $rc->fetch_assoc()) $cols[strtolower($row['Field'])] = true;
        if (isset($cols['usuario'])) {
            $nomeCol = isset($cols['nome']) ? 'nome' : (isset($cols['nome_completo']) ? 'nome_completo' : 'usuario');
            $q = $conn->query("SELECT usuario, $nomeCol AS nome FROM funcionarios ORDER BY $nomeCol");
            while ($q && $row = $q->fetch_assoc()) {
                if ($row['usuario'] === $excluir) continue;
                $out[] = ['usuario' => $row['usuario'], 'nome' => $row['nome'] ?: $row['usuario']];
            }
        }
    } catch (Throwable $e) { /* ignora */ }
    return $out;
}
function notas_nome_usuario($usuario)
{
    $conn = notas_db();
    try {
        $stmt = $conn->prepare("SELECT * FROM funcionarios WHERE usuario=? LIMIT 1");
        $stmt->bind_param('s', $usuario); $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($u) { foreach (['nome','nome_completo'] as $k) if (!empty($u[$k])) return $u[$k]; }
    } catch (Throwable $e) {}
    return $usuario;
}
