<?php
/** Legado: todos os arquivamentos, sem filtro. */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_avisar('get_atos.php');
header('Content-Type: application/json; charset=utf-8');
echo json_encode(arq_indice(), JSON_UNESCAPED_UNICODE);
