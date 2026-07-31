<?php
/** Legado: detalhe de um arquivamento. */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_avisar('load_ato.php');

$id  = arq_id_valido(isset($_GET['id']) ? $_GET['id'] : '');
$ato = $id !== '' ? arq_obter($id) : null;

header('Content-Type: application/json; charset=utf-8');
if (!$ato) { echo json_encode(['error' => 'Ato nao encontrado']); exit; }

foreach ($ato['anexos'] as $i => $a) {
    $ato['anexos'][$i] = 'arquivo.php?id=' . rawurlencode($id) . '&a=' . $i;
}
echo json_encode($ato, JSON_UNESCAPED_UNICODE);
