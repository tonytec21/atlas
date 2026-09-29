<?php
/**
 * Atlas Iris — histórico de extrações, registro de chamadas à IA e estatísticas.
 */

function iris_historico_schema()
{
    $conn = iris_db();
    $conn->query("CREATE TABLE IF NOT EXISTS iris_extracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(80) NOT NULL,
        arquivo_nome VARCHAR(255) NOT NULL,
        arquivo_hash CHAR(64) NULL,
        arquivo_mime VARCHAR(80) NULL,
        arquivo_tamanho INT NULL,
        arquivo_path VARCHAR(255) NULL,
        paginas INT NOT NULL DEFAULT 1,
        modo VARCHAR(20) NOT NULL DEFAULT 'transcricao',
        precisao VARCHAR(20) NOT NULL DEFAULT 'padrao',
        tipo_slug VARCHAR(60) NULL,
        tipo_nome VARCHAR(160) NULL,
        modelo VARCHAR(120) NULL,
        texto LONGTEXT NULL,
        dados LONGTEXT NULL,
        duvidas INT NOT NULL DEFAULT 0,
        tokens_entrada INT NOT NULL DEFAULT 0,
        tokens_saida INT NOT NULL DEFAULT 0,
        duracao_ms INT NOT NULL DEFAULT 0,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        KEY idx_usuario (usuario),
        KEY idx_criado (criado_em),
        KEY idx_hash (arquivo_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS iris_chamadas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(80) NULL,
        acao VARCHAR(30) NOT NULL,
        modelo VARCHAR(120) NULL,
        tokens_entrada INT NOT NULL DEFAULT 0,
        tokens_saida INT NOT NULL DEFAULT 0,
        tokens_pensamento INT NOT NULL DEFAULT 0,
        duracao_ms INT NOT NULL DEFAULT 0,
        ok TINYINT(1) NOT NULL DEFAULT 1,
        erro VARCHAR(255) NULL,
        criado_em DATETIME NOT NULL,
        KEY idx_criado (criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Conta marcações de dúvida ([?...?] e [ilegível]) em um texto. */
function iris_contar_duvidas($texto)
{
    return preg_match_all('~\[\?[^\]\n]*?\?\]|\[ileg[ií]vel\]~iu', (string)$texto);
}

/** Registra uma chamada à IA (sucesso ou falha) para estatísticas. */
function iris_registrar_chamada($acao, $modelo, $r = null, $erro = null)
{
    try {
        $u = iris_usuario(); $agora = date('Y-m-d H:i:s');
        $te = (int)($r['uso']['entrada'] ?? 0); $ts = (int)($r['uso']['saida'] ?? 0); $tp = (int)($r['uso']['pensamento'] ?? 0);
        $ms = (int)($r['ms'] ?? 0); $ok = $erro === null ? 1 : 0; $err = $erro === null ? null : mb_substr($erro, 0, 250);
        $mod = $r['modelo'] ?? $modelo;
        $st = iris_db()->prepare("INSERT INTO iris_chamadas (usuario, acao, modelo, tokens_entrada, tokens_saida, tokens_pensamento, duracao_ms, ok, erro, criado_em) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('sssiiiiiss', $u, $acao, $mod, $te, $ts, $tp, $ms, $ok, $err, $agora);
        $st->execute(); $st->close();
    } catch (Throwable $e) { /* estatística nunca derruba a extração */ }
}

function iris_hist_pode_ver(array $row) { return iris_is_admin() || $row['usuario'] === iris_usuario(); }

function iris_hist_obter($id, $comConteudo = true)
{
    $cols = $comConteudo ? '*' : 'id, usuario, arquivo_nome, arquivo_path, arquivo_mime';
    $st = iris_db()->prepare("SELECT $cols FROM iris_extracoes WHERE id=? LIMIT 1");
    $st->bind_param('i', $id); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    if (!$row || !iris_hist_pode_ver($row)) throw new RuntimeException('Extração não encontrada.');
    if ($comConteudo) {
        $row['dados'] = $row['dados'] ? json_decode($row['dados'], true) : null;
        $row['tem_arquivo'] = !empty($row['arquivo_path']) && is_file(iris_dir_arquivos() . '/' . $row['arquivo_path']);
        unset($row['arquivo_path']);
    }
    return $row;
}

/**
 * Cria ou atualiza uma extração.
 * @param array $d id?, arquivo_nome, arquivo_hash, arquivo_mime, arquivo_tamanho, paginas, modo, precisao,
 *                 tipo_slug, tipo_nome, modelo, texto, dados(json string|array), tokens_entrada, tokens_saida, duracao_ms
 * @param array|null $upload item de $_FILES com o original (opcional)
 */
function iris_hist_salvar(array $d, $upload = null)
{
    iris_ensure_schema();
    $conn = iris_db(); $agora = date('Y-m-d H:i:s');
    $texto = (string)($d['texto'] ?? '');
    $dados = $d['dados'] ?? null;
    if (is_array($dados)) $dados = json_encode($dados, JSON_UNESCAPED_UNICODE);
    if ($dados !== null && $dados !== '' && json_decode($dados) === null) throw new RuntimeException('Dados estruturados inválidos.');
    if ($dados === '') $dados = null;
    $duv = iris_contar_duvidas($texto);
    $id = (int)($d['id'] ?? 0);

    if ($id > 0) {
        iris_hist_obter($id, false); // valida permissão
        $tipoSlug = ($d['tipo_slug'] ?? '') !== '' ? mb_substr($d['tipo_slug'], 0, 60) : null;
        $tipoNome = ($d['tipo_nome'] ?? '') !== '' ? mb_substr($d['tipo_nome'], 0, 160) : null;
        $modo = in_array($d['modo'] ?? '', ['transcricao', 'dados', 'completo'], true) ? $d['modo'] : null;
        if ($modo && $modo !== 'completo' && trim($texto) !== '' && $dados) $modo = 'completo';
        $prec = in_array($d['precisao'] ?? '', ['padrao', 'maxima'], true) ? $d['precisao'] : null;
        $modelo = ($d['modelo'] ?? '') !== '' ? mb_substr($d['modelo'], 0, 120) : null;
        $st = $conn->prepare("UPDATE iris_extracoes SET texto=?, dados=?, duvidas=?, tipo_slug=COALESCE(?, tipo_slug), tipo_nome=COALESCE(?, tipo_nome),
                              modo=COALESCE(?, modo), precisao=COALESCE(?, precisao), modelo=COALESCE(?, modelo),
                              tokens_entrada=tokens_entrada+?, tokens_saida=tokens_saida+?, atualizado_em=? WHERE id=?");
        $te = (int)($d['tokens_entrada'] ?? 0); $ts = (int)($d['tokens_saida'] ?? 0);
        $st->bind_param('ssisssssiisi', $texto, $dados, $duv, $tipoSlug, $tipoNome, $modo, $prec, $modelo, $te, $ts, $agora, $id);
        $st->execute(); $st->close();
        return $id;
    }

    $u = iris_usuario();
    $nome = mb_substr(trim((string)($d['arquivo_nome'] ?? 'documento')), 0, 250) ?: 'documento';
    $hash = preg_match('~^[a-f0-9]{64}$~', (string)($d['arquivo_hash'] ?? '')) ? $d['arquivo_hash'] : null;
    $mime = mb_substr((string)($d['arquivo_mime'] ?? ''), 0, 80);
    $tam = (int)($d['arquivo_tamanho'] ?? 0); $pag = max(1, (int)($d['paginas'] ?? 1));
    $modo = in_array($d['modo'] ?? '', ['transcricao', 'dados', 'completo'], true) ? $d['modo'] : 'transcricao';
    $prec = ($d['precisao'] ?? '') === 'maxima' ? 'maxima' : 'padrao';
    $tipoSlug = ($d['tipo_slug'] ?? '') !== '' ? mb_substr($d['tipo_slug'], 0, 60) : null;
    $tipoNome = ($d['tipo_nome'] ?? '') !== '' ? mb_substr($d['tipo_nome'], 0, 160) : null;
    $modelo = mb_substr((string)($d['modelo'] ?? ''), 0, 120);
    $te = (int)($d['tokens_entrada'] ?? 0); $ts = (int)($d['tokens_saida'] ?? 0); $ms = (int)($d['duracao_ms'] ?? 0);

    // Arquivo original (opcional)
    $path = null;
    if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int)(iris_config()['guardar_arquivos'] ?? 1) === 1) {
        $bytesMime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $ext = iris_mimes_originais()[$bytesMime] ?? null;
        if ($ext) {
            $sub = date('Y/m');
            $dir = iris_dir_arquivos() . '/' . $sub;
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $arq = bin2hex(random_bytes(16)) . '.' . $ext;
            if (@move_uploaded_file($upload['tmp_name'], $dir . '/' . $arq) || @rename($upload['tmp_name'], $dir . '/' . $arq)) {
                $path = $sub . '/' . $arq;
                $mime = $bytesMime; $tam = (int)filesize($dir . '/' . $arq);
                if (!$hash) $hash = hash_file('sha256', $dir . '/' . $arq);
            }
        }
    }

    $st = $conn->prepare("INSERT INTO iris_extracoes (usuario, arquivo_nome, arquivo_hash, arquivo_mime, arquivo_tamanho, arquivo_path, paginas, modo, precisao,
                          tipo_slug, tipo_nome, modelo, texto, dados, duvidas, tokens_entrada, tokens_saida, duracao_ms, criado_em, atualizado_em)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $st->bind_param('ssssisisssssssiiiiss', $u, $nome, $hash, $mime, $tam, $path, $pag, $modo, $prec, $tipoSlug, $tipoNome, $modelo,
                    $texto, $dados, $duv, $te, $ts, $ms, $agora, $agora);
    $st->execute(); $novo = $st->insert_id; $st->close();
    iris_limpeza_talvez();
    return $novo;
}

function iris_hist_excluir($id)
{
    $row = iris_hist_obter($id, false);
    if (!empty($row['arquivo_path'])) @unlink(iris_dir_arquivos() . '/' . $row['arquivo_path']);
    $st = iris_db()->prepare("DELETE FROM iris_extracoes WHERE id=?");
    $st->bind_param('i', $id); $st->execute(); $st->close();
}

/** Busca extração anterior do mesmo arquivo (mesmo hash), visível ao usuário. */
function iris_hist_por_hash($hash)
{
    if (!preg_match('~^[a-f0-9]{64}$~', (string)$hash)) return null;
    $sql = "SELECT id, arquivo_nome, modo, tipo_nome, modelo, criado_em, usuario FROM iris_extracoes WHERE arquivo_hash=?"
         . (iris_is_admin() ? "" : " AND usuario=?") . " ORDER BY id DESC LIMIT 1";
    $st = iris_db()->prepare($sql);
    if (iris_is_admin()) $st->bind_param('s', $hash); else { $u = iris_usuario(); $st->bind_param('ss', $hash, $u); }
    $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close();
    return $r ?: null;
}

/**
 * Lista o histórico com filtros.
 * $f: busca, modo, usuario (admin), de, ate (Y-m-d), pagina, por_pagina
 */
function iris_hist_listar(array $f)
{
    iris_ensure_schema();
    $w = []; $v = []; $t = '';
    if (!iris_is_admin()) { $w[] = 'usuario=?'; $v[] = iris_usuario(); $t .= 's'; }
    elseif (!empty($f['usuario'])) { $w[] = 'usuario=?'; $v[] = $f['usuario']; $t .= 's'; }
    if (!empty($f['busca'])) {
        $b = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($f['busca'])) . '%';
        $w[] = '(arquivo_nome LIKE ? OR texto LIKE ? OR dados LIKE ? OR tipo_nome LIKE ?)';
        array_push($v, $b, $b, $b, $b); $t .= 'ssss';
    }
    if (!empty($f['modo']) && in_array($f['modo'], ['transcricao', 'dados', 'completo'], true)) { $w[] = 'modo=?'; $v[] = $f['modo']; $t .= 's'; }
    if (!empty($f['de']) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $f['de'])) { $w[] = 'criado_em>=?'; $v[] = $f['de'] . ' 00:00:00'; $t .= 's'; }
    if (!empty($f['ate']) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $f['ate'])) { $w[] = 'criado_em<=?'; $v[] = $f['ate'] . ' 23:59:59'; $t .= 's'; }
    if (!empty($f['duvidas'])) $w[] = 'duvidas>0';
    $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
    $pp = max(5, min(100, (int)($f['por_pagina'] ?? 20))); $pg = max(1, (int)($f['pagina'] ?? 1)); $off = ($pg - 1) * $pp;

    $conn = iris_db();
    $st = $conn->prepare("SELECT COUNT(*) AS n FROM iris_extracoes" . $where);
    if ($t) $st->bind_param($t, ...$v);
    $st->execute(); $total = (int)$st->get_result()->fetch_assoc()['n']; $st->close();

    $st = $conn->prepare("SELECT id, usuario, arquivo_nome, arquivo_mime, arquivo_tamanho, (arquivo_path IS NOT NULL) AS tem_arquivo, paginas, modo, precisao,
                          tipo_nome, modelo, duvidas, tokens_entrada, tokens_saida, duracao_ms, criado_em, atualizado_em,
                          SUBSTRING(texto, 1, 220) AS trecho
                          FROM iris_extracoes" . $where . " ORDER BY id DESC LIMIT $pp OFFSET $off");
    if ($t) $st->bind_param($t, ...$v);
    $st->execute(); $res = $st->get_result(); $itens = [];
    while ($row = $res->fetch_assoc()) { $row['tem_arquivo'] = (int)$row['tem_arquivo'] === 1; $itens[] = $row; }
    $st->close();
    return ['itens' => $itens, 'total' => $total, 'pagina' => $pg, 'por_pagina' => $pp, 'paginas' => max(1, (int)ceil($total / $pp))];
}

/** Remove arquivos originais além do prazo de retenção (roda no máximo 1x por dia). */
function iris_limpeza_talvez($forcar = false)
{
    $cfg = iris_config();
    $dias = (int)($cfg['retencao_dias'] ?? 180);
    $ult = $cfg['ultima_limpeza'] ?? null;
    if (!$forcar && $ult && strtotime($ult) > time() - 86400) return 0;
    iris_config_set(['ultima_limpeza' => date('Y-m-d H:i:s')]);
    if ($dias <= 0) return 0;
    $limite = date('Y-m-d H:i:s', time() - $dias * 86400);
    $st = iris_db()->prepare("SELECT id, arquivo_path FROM iris_extracoes WHERE arquivo_path IS NOT NULL AND criado_em < ?");
    $st->bind_param('s', $limite); $st->execute(); $res = $st->get_result(); $n = 0;
    $up = iris_db()->prepare("UPDATE iris_extracoes SET arquivo_path=NULL WHERE id=?");
    while ($row = $res->fetch_assoc()) {
        @unlink(iris_dir_arquivos() . '/' . $row['arquivo_path']);
        $id = (int)$row['id']; $up->bind_param('i', $id); $up->execute(); $n++;
    }
    $st->close(); $up->close();
    return $n;
}

/** Estatísticas de uso (admin). */
function iris_estatisticas($dias = 30)
{
    iris_ensure_schema();
    $dias = max(1, min(365, (int)$dias));
    $desde = date('Y-m-d 00:00:00', time() - ($dias - 1) * 86400);
    $conn = iris_db();
    $st = $conn->prepare("SELECT COUNT(*) AS extracoes, COALESCE(SUM(paginas),0) AS paginas, COALESCE(SUM(duvidas),0) AS duvidas,
                          COUNT(DISTINCT usuario) AS usuarios FROM iris_extracoes WHERE criado_em>=?");
    $st->bind_param('s', $desde); $st->execute(); $geral = $st->get_result()->fetch_assoc(); $st->close();

    $st = $conn->prepare("SELECT modelo, COUNT(*) AS chamadas, SUM(ok=0) AS falhas, SUM(tokens_entrada) AS entrada, SUM(tokens_saida) AS saida,
                          SUM(tokens_pensamento) AS pensamento, ROUND(AVG(NULLIF(duracao_ms,0))) AS ms_medio
                          FROM iris_chamadas WHERE criado_em>=? GROUP BY modelo ORDER BY chamadas DESC");
    $st->bind_param('s', $desde); $st->execute(); $res = $st->get_result(); $porModelo = [];
    while ($r = $res->fetch_assoc()) $porModelo[] = $r;
    $st->close();

    $st = $conn->prepare("SELECT DATE(criado_em) AS dia, COUNT(*) AS n, COALESCE(SUM(paginas),0) AS paginas FROM iris_extracoes WHERE criado_em>=? GROUP BY DATE(criado_em) ORDER BY dia");
    $st->bind_param('s', $desde); $st->execute(); $res = $st->get_result(); $mapa = [];
    while ($r = $res->fetch_assoc()) $mapa[$r['dia']] = (int)$r['paginas'];
    $st->close();
    $serie = [];
    for ($i = $dias - 1; $i >= 0; $i--) { $d = date('Y-m-d', time() - $i * 86400); $serie[] = ['dia' => $d, 'paginas' => $mapa[$d] ?? 0]; }

    $st = $conn->prepare("SELECT usuario, COUNT(*) AS extracoes, COALESCE(SUM(paginas),0) AS paginas FROM iris_extracoes WHERE criado_em>=? GROUP BY usuario ORDER BY paginas DESC LIMIT 10");
    $st->bind_param('s', $desde); $st->execute(); $res = $st->get_result(); $porUsuario = [];
    while ($r = $res->fetch_assoc()) $porUsuario[] = $r;
    $st->close();

    return ['dias' => $dias, 'geral' => $geral, 'por_modelo' => $porModelo, 'serie' => $serie, 'por_usuario' => $porUsuario];
}
