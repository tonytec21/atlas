<?php
require_once __DIR__ . '/_compat.php';
arq_exige_login();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(arq_categorias(), JSON_UNESCAPED_UNICODE);
