<?php
/** Legado: primeiro selo vinculado. */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
$nums = arq_numeros_selos(isset($_GET['id']) ? $_GET['id'] : '');
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['numero_selo' => $nums ? $nums[0] : 'Nenhum selo encontrado', 'selos' => $nums], JSON_UNESCAPED_UNICODE);
