<?php
/** Todos os selos vinculados (formato usado pelo front antigo). */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['selos' => arq_selos(isset($_GET['id']) ? $_GET['id'] : '')], JSON_UNESCAPED_UNICODE);
