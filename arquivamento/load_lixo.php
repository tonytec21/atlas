<?php
/** Legado: detalhe de um item da lixeira. */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_avisar('load_lixo.php');
$id  = arq_id_valido(isset($_GET['id']) ? $_GET['id'] : '');
$ato = $id !== '' ? arq_obter($id, true) : null;
header('Content-Type: application/json; charset=utf-8');
echo json_encode($ato ? $ato : ['error' => 'Ato nao encontrado'], JSON_UNESCAPED_UNICODE);
