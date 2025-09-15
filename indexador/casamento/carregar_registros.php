<?php
include(__DIR__ . '/db_connection.php');
header('Content-Type: application/json; charset=utf-8');

/** Converte DD/MM/AAAA -> AAAA-MM-DD (ou retorna null) */
function brToISO($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) return null;
    $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
    if (!checkdate($mo, $d, $y)) return null;
    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/** Busca parâmetro tanto em POST quanto em GET */
function req_param($key, $alts = []) {
    if (isset($_POST[$key])) return trim((string)$_POST[$key]);
    if (isset($_GET[$key]))  return trim((string)$_GET[$key]);
    foreach ($alts as $k) {
        if (isset($_POST[$k])) return trim((string)$_POST[$k]);
        if (isset($_GET[$k]))  return trim((string)$_GET[$k]);
    }
    return '';
}

/** Inteiro opcional */
function req_int($key, $alts = []) {
    $v = req_param($key, $alts);
    if ($v === '' || !is_numeric($v)) return null;
    return (int)$v;
}

$q             = req_param('q', ['conjuge','conjuge1','conjuge2']);
$qCasado       = req_param('qCasado', ['conjugeCasado','conjuge_casado','conjuge1_nome_casado','conjuge2_nome_casado']);
$termo         = req_int('termo');
$livro         = req_int('livro');
$folha         = req_int('folha');
$tipo          = req_param('tipo', ['tipo_casamento']);
$regime        = req_param('regime', ['regime_bens']);
$dataCasamento = req_param('dataCasamento', ['data_casamento','dataCasamentoISO']);
$dataRegistro  = req_param('dataRegistro',  ['data_registro','dataRegistroISO']);
$limit         = req_int('limit');

// Converte datas de filtro: aceita DD/MM/AAAA e também ISO direto
if ($dataCasamento !== '') {
    $dataCasamento = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataCasamento)) ? $dataCasamento : (brToISO($dataCasamento) ?? '');
}
if ($dataRegistro !== '') {
    $dataRegistro = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRegistro)) ? $dataRegistro : (brToISO($dataRegistro) ?? '');
}

$sql = "SELECT id, termo, livro, folha, tipo_casamento,
               conjuge1_nome, conjuge1_nome_casado, conjuge1_sexo,
               conjuge2_nome, conjuge2_nome_casado, conjuge2_sexo,
               regime_bens, data_casamento, data_registro, matricula
        FROM indexador_casamento
        WHERE status='ativo' ";

$params = []; $types = '';

if ($qCasado !== '') {
    $sql .= " AND (conjuge1_nome_casado LIKE ? OR conjuge2_nome_casado LIKE ?) ";
    $likeCas = '%'.$qCasado.'%'; $params[] = $likeCas; $params[] = $likeCas; $types .= 'ss';
}
if (!is_null($termo)) { $sql .= " AND termo = ? "; $params[] = $termo; $types .= 'i'; }
if (!is_null($livro)) { $sql .= " AND livro = ? "; $params[] = $livro; $types .= 'i'; }
if (!is_null($folha)) { $sql .= " AND folha = ? "; $params[] = $folha; $types .= 'i'; }
if ($tipo !== '')     { $sql .= " AND tipo_casamento = ? "; $params[] = $tipo; $types .= 's'; }
if ($regime !== '')   { $sql .= " AND regime_bens = ? "; $params[] = $regime; $types .= 's'; }
if ($dataCasamento !== '') { $sql .= " AND data_casamento = ? "; $params[] = $dataCasamento; $types .= 's'; }
if ($dataRegistro !== '')  { $sql .= " AND data_registro = ? ";  $params[] = $dataRegistro;  $types .= 's'; }

$sql .= " ORDER BY termo DESC ";
if (!is_null($limit) && $limit > 0) { $sql .= " LIMIT ".intval($limit); }

$stmt = $conn->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while ($r = $res->fetch_assoc()) { $out[] = $r; }
echo json_encode($out);

$stmt->close(); $conn->close();
