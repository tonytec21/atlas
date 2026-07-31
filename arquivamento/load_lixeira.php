<?php
/** Legado: itens da lixeira. */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_avisar('load_lixeira.php');
header('Content-Type: application/json; charset=utf-8');
echo json_encode(arq_listar_lixeira(), JSON_UNESCAPED_UNICODE);
