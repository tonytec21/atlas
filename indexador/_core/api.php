<?php
/**
 * ============================================================================
 *  API unificada dos indexadores (Nascimento / Casamento / Óbito)
 *  Uso: cada pasta tem um api.php que define IX_TIPO e inclui este arquivo.
 *
 *  Ações (parâmetro "action"):
 *   GET  list      listagem paginada com filtros e ordenação
 *   GET  get       um registro + anexos
 *   GET  livros    livros distintos
 *   GET  stats     indicadores do topo
 *   GET  users     usuários (filtro "Cadastrado por")
 *   GET  next      sugestão de próximo termo/folha para o livro
 *   GET  audit     pendências CRC (regras + XSD) dos registros filtrados
 *   POST check     validação ao vivo do formulário (regras + XSD)
 *   POST save      inclusão/alteração (valida antes de gravar)
 *   POST delete    remoção lógica (administrador)
 *   GET  anexos    anexos de um registro
 *   POST anexo_upload / anexo_remove
 * ============================================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/crc.php';

@ini_set('display_errors', '0');
ob_start();

set_exception_handler(function (Throwable $e) {
    error_log('[indexador] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    ix_fail('Erro interno: ' . $e->getMessage(), 500);
});

if (!ix_logged()) ix_fail('Sessão expirada. Faça login novamente.', 401);
if (!defined('IX_TIPO')) ix_fail('Tipo não definido.', 500);

$T = ix_tipo(IX_TIPO);
$db = ix_db();
$action = (string)($_REQUEST['action'] ?? '');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

/* ------------------------------------------------------------------------ */
/*  Helpers SQL                                                             */
/* ------------------------------------------------------------------------ */
function api_q(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $st = $db->prepare($sql);
    if (!$st) throw new RuntimeException('Falha ao preparar consulta: ' . $db->error);
    if ($params) {
        $types = '';
        foreach ($params as $p) $types .= is_int($p) ? 'i' : 's';
        $st->bind_param($types, ...$params);
    }
    if (!$st->execute()) throw new RuntimeException('Falha na consulta: ' . $st->error);
    return $st;
}

function api_all(mysqli $db, string $sql, array $params = []): array
{
    $st = api_q($db, $sql, $params);
    $rs = $st->get_result();
    $rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
    return $rows;
}

function api_one(mysqli $db, string $sql, array $params = []): ?array
{
    $r = api_all($db, $sql, $params);
    return $r[0] ?? null;
}

function api_columns(mysqli $db, string $table): array
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $cache[$table] = [];
        $rs = $db->query("SHOW COLUMNS FROM `$table`");
        while ($rs && ($c = $rs->fetch_assoc())) $cache[$table][$c['Field']] = $c;
    }
    return $cache[$table];
}

/** Monta WHERE a partir dos filtros definidos no tipo. */
function api_where(array $T, array $req): array
{
    $w = ['status = ?'];
    $p = [$T['status_ativo']];
    foreach ($T['filters'] as $f) {
        $k = $f['key'];
        switch ($f['type']) {
            case 'text':
                $v = trim((string)($req[$k] ?? ''));
                if ($v === '') break;
                $or = [];
                foreach ($f['cols'] as $c) { $or[] = "`$c` LIKE ?"; $p[] = '%' . $v . '%'; }
                $w[] = '(' . implode(' OR ', $or) . ')';
                break;
            case 'int':
            case 'livro':
                $v = ix_digits((string)($req[$k] ?? ''));
                if ($v === '') break;
                $w[] = "CAST(`{$f['col']}` AS UNSIGNED) = ?";
                $p[] = (int)$v;
                break;
            case 'select':
            case 'user':
                $v = trim((string)($req[$k] ?? ''));
                if ($v === '') break;
                $w[] = "`{$f['col']}` = ?";
                $p[] = $v;
                break;
            case 'daterange':
                $de = ix_parse_date($req[$k . '_de'] ?? '');
                $ate = ix_parse_date($req[$k . '_ate'] ?? '');
                if ($de)  { $w[] = "`{$f['col']}` >= ?"; $p[] = $de; }
                if ($ate) { $w[] = "`{$f['col']}` <= ?"; $p[] = $ate; }
                break;
        }
    }
    // Busca por IDs (usada pela auditoria/exportação)
    if (!empty($req['ids'])) {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$req['ids']))));
        if ($ids) $w[] = 'id IN (' . implode(',', $ids) . ')';
    }
    return [implode(' AND ', $w), $p];
}

/** Prepara a linha para a interface (decodifica legados, campos de exibição). */
function api_present(array $T, array $r): array
{
    foreach ($r as $k => $v) {
        if (is_string($v)) $r[$k] = ix_decode($v);
    }
    if (isset($r['hora_obito']) && $r['hora_obito']) $r['hora_obito'] = substr($r['hora_obito'], 0, 5);
    switch ($T['key']) {
        case 'nascimento':
        case 'obito':
            $pai = trim((string)($r['nome_pai'] ?? '')); $mae = trim((string)($r['nome_mae'] ?? ''));
            $r['filiacao'] = $pai && $mae ? "$pai e $mae" : ($pai ?: $mae);
            $r['_nome'] = $r['nome_registrado'] ?? '';
            break;
        case 'casamento':
            $r['conjuges'] = trim(($r['conjuge1_nome'] ?? '') . ' & ' . ($r['conjuge2_nome'] ?? ''), ' &');
            $c1 = $r['conjuge1_nome_casado'] ?? ''; $c2 = $r['conjuge2_nome_casado'] ?? '';
            $r['casados'] = ($c1 || $c2) ? 'Casados: ' . ($c1 ?: '—') . ' & ' . ($c2 ?: '—') : '';
            $r['_nome'] = $r['conjuges'];
            break;
    }
    return $r;
}

function api_anexos(mysqli $db, array $T, int $id): array
{
    $A = $T['anexos'];
    $rows = api_all($db, "SELECT * FROM `{$A['table']}` WHERE `{$A['fk']}` = ? AND status = ? ORDER BY id ASC", [$id, $A['ativo']]);
    $out = [];
    foreach ($rows as $a) {
        $path = (string)$a['caminho_anexo'];
        // caminhos absolutos antigos -> relativos à pasta do tipo
        $rel = preg_replace('#^.*?/(anexos/.*)$#', '$1', str_replace('\\', '/', $path));
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $out[] = [
            'id'   => (int)$a['id'],
            'url'  => $rel,
            'nome' => preg_replace('/^[0-9a-f]{13}_/', '', basename($rel)),
            'ext'  => $ext,
            'data' => $a['data'] ?? null,
            'existe' => is_file(IX_ROOT . '/' . $T['key'] . '/' . $rel),
        ];
    }
    return $out;
}

/** Salva arquivos enviados no campo $field para o registro $id. */
function api_store_files(mysqli $db, array $T, int $id, string $field = 'anexos'): array
{
    $A = $T['anexos'];
    $saved = []; $rejected = [];
    if (empty($_FILES[$field])) return [$saved, $rejected];
    $F = $_FILES[$field];
    $names = (array)$F['name']; $tmps = (array)$F['tmp_name']; $errs = (array)$F['error'];
    $relDir = str_replace('{id}', (string)$id, $A['dir']);
    $absDir = IX_ROOT . '/' . $T['key'] . '/' . $relDir;
    $permitidas = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

    foreach ($names as $i => $orig) {
        if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            if (($errs[$i] ?? 4) !== UPLOAD_ERR_NO_FILE) $rejected[] = "$orig (falha no envio)";
            continue;
        }
        $ext = strtolower(pathinfo((string)$orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $permitidas, true)) { $rejected[] = "$orig (tipo não permitido)"; continue; }
        if (!is_dir($absDir)) @mkdir($absDir, 0777, true);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo((string)$orig, PATHINFO_FILENAME));
        $base = trim(substr((string)$base, 0, 80), '._') ?: 'anexo';
        $fname = uniqid() . '_' . $base . '.' . $ext;
        if (!move_uploaded_file($tmps[$i], $absDir . $fname)) { $rejected[] = "$orig (não foi possível gravar)"; continue; }
        api_q($db, "INSERT INTO `{$A['table']}` (`{$A['fk']}`, caminho_anexo, funcionario, status) VALUES (?, ?, ?, ?)",
            [$id, $relDir . $fname, ix_user(), $A['ativo']])->close();
        $saved[] = $orig;
    }
    return [$saved, $rejected];
}

/** Valida dados do formulário e calcula matrícula/duplicidade. */
function api_check(mysqli $db, array $T, array $input, int $id = 0): array
{
    $row = crc_normalize($T, $input);
    $row['matricula'] = ix_matricula(crc_tipo_livro($T, $row), $row['livro'] ?? '', $row['folha'] ?? '', $row['termo'] ?? '', $row['data_registro'] ?? null) ?? '';
    if ($id > 0) $row['id'] = (string)$id;
    $res = crc_check_one($T, $row);

    // Duplicidade
    $dup = null;
    if (($row['livro'] ?? '') !== '' && ($row['termo'] ?? '') !== '') {
        $cands = api_all($db, "SELECT * FROM `{$T['table']}` WHERE status = ? AND id <> ? AND CAST(livro AS UNSIGNED) = ? AND CAST(termo AS UNSIGNED) = ? LIMIT 5",
            [$T['status_ativo'], $id, (int)$row['livro'], (int)$row['termo']]);
        if ($T['key'] === 'casamento') {
            // livros de casamento civil e religioso são distintos
            $cands = array_values(array_filter($cands, fn($c) => ($c['tipo_casamento'] ?? '') === ($row['tipo_casamento'] ?? '')));
        }
        foreach ($cands as $c) {
            $c = api_present($T, $c);
            $exato = ((int)$c['folha'] === (int)($row['folha'] ?? -1)) && (($c['data_registro'] ?? '') === ($row['data_registro'] ?? ''));
            $info = ['id' => (int)$c['id'], 'nome' => $c['_nome'], 'termo' => $c['termo'], 'livro' => $c['livro'], 'folha' => $c['folha'], 'data_registro' => ix_br_date($c['data_registro'] ?? ''), 'exato' => $exato];
            if ($exato) { $dup = $info; break; }
            $res['warnings'][] = ['field' => 'termo', 'msg' => "Já existe o termo {$c['termo']} no livro {$c['livro']} (folha {$c['folha']}): {$c['_nome']}.", 'source' => 'duplicidade'];
        }
    }
    $res['duplicate'] = $dup;
    $res['matricula'] = $row['matricula'];
    $res['row'] = $row;
    return $res;
}

/** A tabela tem índice único em matricula? (casamento) */
function api_matricula_unica(mysqli $db, string $table): bool
{
    static $c = [];
    if (!isset($c[$table])) {
        $c[$table] = false;
        $rs = $db->query("SHOW INDEX FROM `$table` WHERE Column_name = 'matricula' AND Non_unique = 0");
        if ($rs && $rs->num_rows > 0) $c[$table] = true;
    }
    return $c[$table];
}

function api_status_removido(mysqli $db, array $T, string $table, array $cands): string
{
    $cols = api_columns($db, $table);
    $type = strtolower((string)($cols['status']['Type'] ?? ''));
    if (str_starts_with($type, 'enum(')) {
        foreach ($cands as $c) if (str_contains($type, "'" . strtolower($c) . "'")) return $c;
    }
    return $cands[0];
}

/* ------------------------------------------------------------------------ */
/*  Roteamento                                                              */
/* ------------------------------------------------------------------------ */
switch ($action) {

    /* ---------------------------- LIST ---------------------------------- */
    case 'list': {
        [$where, $p] = api_where($T, $_GET);
        $per = max(5, min(200, (int)($_GET['per_page'] ?? 25)));
        $page = max(1, (int)($_GET['page'] ?? 1));

        $sortable = ['id' => 'id'];
        foreach ($T['columns'] as $c) if (!empty($c['sort'])) $sortable[$c['sort']] = $c['sort'];
        $sort = $sortable[is_string($_GET['sort'] ?? null) ? $_GET['sort'] : 'id'] ?? 'id';
        $dir = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $order = in_array($sort, ['termo', 'livro', 'folha'], true) ? "CAST(`$sort` AS UNSIGNED) $dir" : "`$sort` $dir";
        if ($sort !== 'id') $order .= ', id DESC';

        $total = (int)(api_one($db, "SELECT COUNT(*) AS n FROM `{$T['table']}` WHERE $where", $p)['n'] ?? 0);
        $pages = max(1, (int)ceil($total / $per));
        $page = min($page, $pages);
        $off = ($page - 1) * $per;
        $rows = api_all($db, "SELECT * FROM `{$T['table']}` WHERE $where ORDER BY $order LIMIT $per OFFSET $off", $p);

        // contagem de anexos (uma consulta)
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $cnt = [];
        if ($ids) {
            $A = $T['anexos'];
            $rs = api_all($db, "SELECT `{$A['fk']}` AS rid, COUNT(*) AS n FROM `{$A['table']}` WHERE status = ? AND `{$A['fk']}` IN (" . implode(',', $ids) . ") GROUP BY `{$A['fk']}`", [$A['ativo']]);
            foreach ($rs as $x) $cnt[(int)$x['rid']] = (int)$x['n'];
        }
        $rows = array_map(function ($r) use ($T, $cnt) { $r = api_present($T, $r); $r['_anexos'] = $cnt[(int)$r['id']] ?? 0; return $r; }, $rows);
        ix_json(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $per]);
    }

    /* ---------------------------- GET ----------------------------------- */
    case 'get': {
        $id = (int)($_GET['id'] ?? 0);
        $r = api_one($db, "SELECT * FROM `{$T['table']}` WHERE id = ? AND status = ?", [$id, $T['status_ativo']]);
        if (!$r) ix_fail('Registro não encontrado.', 404);
        $r = api_present($T, $r);
        $r['_anexos_lista'] = api_anexos($db, $T, $id);
        $r['_cadastrado_por'] = $r['funcionario'] ?? '';
        $r['_cadastrado_em'] = $r[$T['col_cadastro']] ?? null;
        ix_json(['ok' => true, 'row' => $r]);
    }

    /* ---------------------------- LIVROS -------------------------------- */
    case 'livros': {
        $rows = api_all($db, "SELECT DISTINCT CAST(livro AS UNSIGNED) AS l FROM `{$T['table']}` WHERE status = ? AND livro <> '' ORDER BY l ASC", [$T['status_ativo']]);
        ix_json(['ok' => true, 'livros' => array_values(array_filter(array_map(fn($r) => (int)$r['l'], $rows)))]);
    }

    /* ---------------------------- USERS --------------------------------- */
    case 'users': {
        $rows = api_all($db, "SELECT DISTINCT funcionario FROM `{$T['table']}` WHERE status = ? AND funcionario IS NOT NULL AND funcionario <> '' ORDER BY funcionario", [$T['status_ativo']]);
        $nomes = [];
        try {
            $rs = $db->query("SELECT usuario, COALESCE(NULLIF(TRIM(nome_completo),''), usuario) AS nome FROM funcionarios");
            while ($rs && ($u = $rs->fetch_assoc())) $nomes[$u['usuario']] = $u['nome'];
        } catch (Throwable $e) {}
        ix_json(['ok' => true, 'users' => array_map(fn($r) => ['usuario' => $r['funcionario'], 'nome' => $nomes[$r['funcionario']] ?? $r['funcionario']], $rows)]);
    }

    /* ---------------------------- STATS --------------------------------- */
    case 'stats': {
        $c = $T['col_cadastro'];
        $hoje = date('Y-m-d'); $amanha = date('Y-m-d', strtotime('+1 day')); $mes = date('Y-m-01');
        $r = api_one($db, "SELECT COUNT(*) AS total,
                SUM(`$c` >= ? AND `$c` < ?) AS hoje,
                SUM(`$c` >= ?) AS mes,
                SUM(`$c` >= ? AND `$c` < ? AND funcionario = ?) AS meus_hoje,
                COUNT(DISTINCT CAST(livro AS UNSIGNED)) AS livros
            FROM `{$T['table']}` WHERE status = ?", [$hoje, $amanha, $mes, $hoje, $amanha, ix_user(), $T['status_ativo']]);
        ix_json(['ok' => true, 'stats' => array_map('intval', $r ?? [])]);
    }

    /* ---------------------------- NEXT ---------------------------------- */
    case 'next': {
        $livro = (int)ix_digits((string)($_GET['livro'] ?? ''));
        if ($livro <= 0) ix_json(['ok' => true, 'next' => null]);
        $extra = ''; $p = [$T['status_ativo'], $livro];
        if ($T['key'] === 'casamento' && !empty($_GET['tipo_casamento'])) { $extra = ' AND tipo_casamento = ?'; $p[] = (string)$_GET['tipo_casamento']; }
        $r = api_one($db, "SELECT termo, folha, data_registro FROM `{$T['table']}` WHERE status = ? AND CAST(livro AS UNSIGNED) = ? $extra ORDER BY CAST(termo AS UNSIGNED) DESC LIMIT 1", $p);
        ix_json(['ok' => true, 'next' => $r ? ['termo' => (int)$r['termo'] + 1, 'ultima_folha' => (int)$r['folha'], 'ultimo_termo' => (int)$r['termo'], 'ultima_data' => $r['data_registro']] : null]);
    }

    /* ---------------------------- CHECK --------------------------------- */
    case 'check': {
        $id = (int)($_POST['id'] ?? 0);
        $res = api_check($db, $T, $_POST, $id);
        unset($res['row']);
        ix_json(['ok' => true] + $res);
    }

    /* ---------------------------- SAVE ---------------------------------- */
    case 'save': {
        if (!$isPost) ix_fail('Método inválido.', 405);
        $id = (int)($_POST['id'] ?? 0);
        $forcar = !empty($_POST['forcar']);
        $confirmWarnings = !empty($_POST['confirmar_alertas']);

        if ($id > 0 && !api_one($db, "SELECT id FROM `{$T['table']}` WHERE id = ? AND status = ?", [$id, $T['status_ativo']])) {
            ix_fail('Registro não encontrado (pode ter sido removido).', 404);
        }

        $res = api_check($db, $T, $_POST, $id);
        $row = $res['row'];
        if (!empty($res['errors'])) {
            ix_json(['ok' => false, 'status' => 'invalid', 'message' => 'O registro tem pendências que impediriam a carga na CRC.', 'errors' => $res['errors'], 'warnings' => $res['warnings'], 'matricula' => $res['matricula']], 422);
        }
        $unica = api_matricula_unica($db, $T['table']);
        if ($res['duplicate']) {
            // Com índice único na matrícula (casamento) não é possível manter dois registros ativos iguais
            $canForce = !($unica && $res['matricula'] !== '' && api_one($db, "SELECT id FROM `{$T['table']}` WHERE matricula = ? AND status = ? AND id <> ?", [$res['matricula'], $T['status_ativo'], $id]));
            if (!$forcar || !$canForce) {
                ix_json(['ok' => false, 'status' => 'duplicate', 'duplicate' => $res['duplicate'], 'can_force' => $canForce, 'warnings' => $res['warnings']], 409);
            }
        }
        if ($res['warnings'] && !$confirmWarnings) {
            $naoDup = array_values(array_filter($res['warnings'], fn($w) => $w['source'] !== 'duplicidade' || !$forcar));
            if ($naoDup) ix_json(['ok' => false, 'status' => 'warnings', 'warnings' => $naoDup, 'matricula' => $res['matricula']], 409);
        }

        // Monta colunas
        $data = [];
        foreach ($T['fields'] as $k => $f) {
            $v = $row[$k] ?? null;
            if (in_array($f['type'], ['date', 'time'], true)) $v = $v ?: null;
            elseif ($f['type'] === 'name' && $v === '' && $T['key'] === 'casamento') $v = null;
            elseif ($v === null) $v = '';
            $data[$k] = $v;
            if ($f['type'] === 'city') $data[$f['ibge']] = $row[$f['ibge']] ?? '';
        }
        $data['matricula'] = $res['matricula'] !== '' ? $res['matricula'] : null;

        $db->begin_transaction();
        try {
            // libera a matrícula presa em registros já excluídos (índice único do casamento)
            if ($unica && $data['matricula']) {
                api_q($db, "UPDATE `{$T['table']}` SET matricula = NULL WHERE matricula = ? AND status <> ? AND id <> ?", [$data['matricula'], $T['status_ativo'], $id])->close();
            }
            if ($id > 0) {
                $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
                $vals = array_values($data); $vals[] = $id;
                api_q($db, "UPDATE `{$T['table']}` SET $sets WHERE id = ?", $vals)->close();
            } else {
                $data['funcionario'] = ix_user();
                $data['status'] = $T['status_ativo'];
                $cols = '`' . implode('`, `', array_keys($data)) . '`';
                $ph = implode(', ', array_fill(0, count($data), '?'));
                $st = api_q($db, "INSERT INTO `{$T['table']}` ($cols) VALUES ($ph)", array_values($data));
                $id = (int)$st->insert_id;
                $st->close();
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate entry') !== false) {
                ix_fail('Já existe um registro ativo com a mesma matrícula (' . $res['matricula'] . '). Verifique livro, folha, termo e data.', 409, ['status' => 'conflict']);
            }
            throw $e;
        }

        [$saved, $rejected] = api_store_files($db, $T, $id, 'anexos');
        ix_json(['ok' => true, 'id' => $id, 'matricula' => $res['matricula'], 'anexos_salvos' => $saved, 'anexos_recusados' => $rejected, 'warnings' => $res['warnings']]);
    }

    /* ---------------------------- DELETE -------------------------------- */
    case 'delete': {
        if (!$isPost) ix_fail('Método inválido.', 405);
        if (!ix_is_admin()) ix_fail('Apenas administradores podem excluir registros.', 403);
        $id = (int)($_POST['id'] ?? 0);
        $status = api_status_removido($db, $T, $T['table'], $T['status_removido']);
        $limpa = api_matricula_unica($db, $T['table']) ? ', matricula = NULL' : '';
        $st = api_q($db, "UPDATE `{$T['table']}` SET status = ?$limpa WHERE id = ? AND status = ?", [$status, $id, $T['status_ativo']]);
        $n = $st->affected_rows; $st->close();
        if ($n < 1) ix_fail('Registro não encontrado.', 404);
        ix_json(['ok' => true]);
    }

    /* ---------------------------- ANEXOS -------------------------------- */
    case 'anexos': {
        ix_json(['ok' => true, 'anexos' => api_anexos($db, $T, (int)($_GET['id'] ?? 0))]);
    }

    case 'anexo_upload': {
        if (!$isPost) ix_fail('Método inválido.', 405);
        $id = (int)($_POST['id'] ?? 0);
        if (!api_one($db, "SELECT id FROM `{$T['table']}` WHERE id = ? AND status = ?", [$id, $T['status_ativo']])) ix_fail('Registro não encontrado.', 404);
        [$saved, $rejected] = api_store_files($db, $T, $id, 'anexos');
        if (!$saved && $rejected) ix_fail('Nenhum arquivo foi anexado: ' . implode('; ', $rejected));
        ix_json(['ok' => true, 'salvos' => $saved, 'recusados' => $rejected, 'anexos' => api_anexos($db, $T, $id)]);
    }

    case 'anexo_remove': {
        if (!$isPost) ix_fail('Método inválido.', 405);
        $A = $T['anexos'];
        $aid = (int)($_POST['anexo_id'] ?? 0);
        $a = api_one($db, "SELECT * FROM `{$A['table']}` WHERE id = ?", [$aid]);
        if (!$a) ix_fail('Anexo não encontrado.', 404);
        if ($A['remove']['mode'] === 'delete') {
            $abs = IX_ROOT . '/' . $T['key'] . '/' . ltrim((string)$a['caminho_anexo'], '/');
            if (is_file($abs)) @unlink($abs);
            api_q($db, "DELETE FROM `{$A['table']}` WHERE id = ?", [$aid])->close();
        } else {
            $cols = api_columns($db, $A['table']);
            $sql = isset($cols['funcionario'])
                ? "UPDATE `{$A['table']}` SET status = ?, funcionario = ? WHERE id = ?"
                : "UPDATE `{$A['table']}` SET status = ? WHERE id = ?";
            $par = isset($cols['funcionario']) ? [$A['remove']['value'], ix_user(), $aid] : [$A['remove']['value'], $aid];
            api_q($db, $sql, $par)->close();
        }
        ix_json(['ok' => true, 'anexos' => api_anexos($db, $T, (int)$a[$A['fk']])]);
    }

    /* ---------------------------- AUDIT --------------------------------- */
    case 'audit': {
        @set_time_limit(300);
        [$where, $p] = api_where($T, $_GET);
        $limit = 20000;
        $rows = api_all($db, "SELECT * FROM `{$T['table']}` WHERE $where ORDER BY CAST(livro AS UNSIGNED), CAST(termo AS UNSIGNED) LIMIT $limit", $p);
        $res = crc_audit_rows($T, $rows);
        $items = []; $nErr = 0; $nWarn = 0;
        foreach ($rows as $r) {
            $x = $res[(int)$r['id']] ?? ['errors' => [], 'warnings' => []];
            if (!$x['errors'] && !$x['warnings']) continue;
            if ($x['errors']) $nErr++; else $nWarn++;
            $pr = api_present($T, $r);
            $items[] = ['id' => (int)$r['id'], 'termo' => $r['termo'], 'livro' => $r['livro'], 'folha' => $r['folha'],
                'data_registro' => ix_br_date($r['data_registro'] ?? ''), 'nome' => $pr['_nome'],
                'errors' => $x['errors'], 'warnings' => $x['warnings']];
        }
        usort($items, fn($a, $b) => (count($b['errors']) <=> count($a['errors'])) ?: ((int)$a['livro'] <=> (int)$b['livro']) ?: ((int)$a['termo'] <=> (int)$b['termo']));
        ix_json(['ok' => true, 'checked' => count($rows), 'limited' => count($rows) >= $limit, 'com_erros' => $nErr, 'com_alertas' => $nWarn, 'items' => $items]);
    }
}

ix_fail('Ação inválida.', 400);
