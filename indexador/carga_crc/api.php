<?php
/**
 * API da tela de exportação de carga CRC.
 *   GET  action=list     (tipo + filtros) -> todos os registros que atendem (até 50.000)
 *   POST action=validate (tipo + ids)     -> regras + XSD por registro
 */
declare(strict_types=1);

require_once __DIR__ . '/../_core/crc.php';
@ini_set('display_errors', '0');
ob_start();
set_exception_handler(function (Throwable $e) { error_log('[indexador/carga] ' . $e->getMessage()); ix_fail('Erro interno: ' . $e->getMessage(), 500); });

if (!ix_logged()) ix_fail('Sessão expirada. Faça login novamente.', 401);
$tipo = (string)($_REQUEST['tipo'] ?? '');
if (!in_array($tipo, ['nascimento', 'casamento', 'obito'], true)) ix_fail('Tipo inválido.');
$T = ix_tipo($tipo);
$db = ix_db();
$R = $_REQUEST;

function cg_rows(mysqli $db, string $sql, array $p): array
{
    $st = $db->prepare($sql);
    if (!$st) throw new RuntimeException($db->error);
    if ($p) $st->bind_param(str_repeat('s', count($p)), ...array_map('strval', $p));
    $st->execute();
    $r = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $r;
}

function cg_nome(string $tipo, array $r): string
{
    if ($tipo === 'casamento') return trim(ix_decode($r['conjuge1_nome'] ?? '') . ' & ' . ix_decode($r['conjuge2_nome'] ?? ''), ' &');
    return ix_decode($r['nome_registrado'] ?? '');
}

switch ($R['action'] ?? '') {
    case 'list': {
        $w = ['status = ?']; $p = [$T['status_ativo']];
        $range = function (string $col, string $k) use (&$w, &$p, $R) {
            if ($d = ix_parse_date($R[$k . '_de'] ?? '')) { $w[] = "`$col` >= ?"; $p[] = $d; }
            if ($d = ix_parse_date($R[$k . '_ate'] ?? '')) { $w[] = "`$col` <= ?"; $p[] = $d; }
        };
        $range('data_registro', 'reg');
        if ($d = ix_parse_date($R['cad_de'] ?? '')) { $w[] = "`{$T['col_cadastro']}` >= ?"; $p[] = $d . ' 00:00:00'; }
        if ($d = ix_parse_date($R['cad_ate'] ?? '')) { $w[] = "`{$T['col_cadastro']}` <= ?"; $p[] = $d . ' 23:59:59'; }
        if ($tipo === 'casamento') $range('data_casamento', 'ato');
        if ($tipo === 'obito') $range('data_obito', 'ato');
        if ($tipo === 'nascimento') $range('data_nascimento', 'ato');

        if (($v = ix_digits($R['livro'] ?? '')) !== '') { $w[] = 'CAST(livro AS UNSIGNED) = ?'; $p[] = (int)$v; }
        if (($v = ix_digits($R['folha'] ?? '')) !== '') { $w[] = 'CAST(folha AS UNSIGNED) = ?'; $p[] = (int)$v; }
        $ti = ix_digits($R['termo_de'] ?? ''); $tf = ix_digits($R['termo_ate'] ?? '');
        if ($ti !== '' && $tf !== '') { $w[] = 'CAST(termo AS UNSIGNED) BETWEEN ? AND ?'; $p[] = (int)$ti; $p[] = (int)$tf; }
        elseif ($ti !== '' || $tf !== '') { $w[] = 'CAST(termo AS UNSIGNED) = ?'; $p[] = (int)($ti !== '' ? $ti : $tf); }
        if (($v = trim((string)($R['matricula'] ?? ''))) !== '') { $w[] = 'matricula LIKE ?'; $p[] = '%' . ix_digits($v) . '%'; }
        if (($v = trim((string)($R['nome'] ?? ''))) !== '') {
            $like = '%' . $v . '%';
            if ($tipo === 'casamento') { $w[] = '(conjuge1_nome LIKE ? OR conjuge2_nome LIKE ?)'; $p[] = $like; $p[] = $like; }
            else { $w[] = 'nome_registrado LIKE ?'; $p[] = $like; }
        }
        if (($v = trim((string)($R['func'] ?? ''))) !== '') { $w[] = 'funcionario = ?'; $p[] = $v; }

        $ato = ['nascimento' => 'data_nascimento', 'casamento' => 'data_casamento', 'obito' => 'data_obito'][$tipo];
        $cols = "id, termo, livro, folha, matricula, data_registro, `$ato` AS data_ato, " . ($tipo === 'casamento' ? 'conjuge1_nome, conjuge2_nome' : 'nome_registrado');
        $limit = 50000;
        $rows = cg_rows($db, "SELECT $cols FROM `{$T['table']}` WHERE " . implode(' AND ', $w) . " ORDER BY CAST(livro AS UNSIGNED), CAST(termo AS UNSIGNED), id LIMIT $limit", $p);
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'], 'termo' => $r['termo'], 'livro' => $r['livro'], 'folha' => $r['folha'],
            'matricula' => $r['matricula'] ?? '', 'data_registro' => $r['data_registro'], 'data_ato' => $r['data_ato'], 'nome' => cg_nome($tipo, $r),
        ], $rows);
        ix_json(['ok' => true, 'rows' => $out, 'limited' => count($rows) >= $limit]);
    }

    case 'validate': {
        @set_time_limit(600);
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)($R['ids'] ?? '')))));
        if (!$ids) ix_fail('Nenhum registro informado.');
        $rows = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $rows = array_merge($rows, cg_rows($db, "SELECT * FROM `{$T['table']}` WHERE status = ? AND id IN (" . implode(',', $chunk) . ")", [$T['status_ativo']]));
        }
        $res = crc_audit_rows($T, $rows);
        $out = [];
        foreach ($res as $id => $x) {
            $out[$id] = ['e' => array_column($x['errors'], 'msg'), 'w' => array_column($x['warnings'], 'msg')];
        }
        ix_json(['ok' => true, 'result' => $out]);
    }
}
ix_fail('Ação inválida.');
