<?php
require_once __DIR__ . '/_compat.php';
arq_exige_login();
$selos = arq_selos(isset($_GET['arquivo_id']) ? $_GET['arquivo_id'] : '');
header('Content-Type: application/json; charset=utf-8');
echo json_encode($selos
    ? ['status' => 'success', 'selo' => $selos[0], 'selos' => $selos]
    : ['status' => 'error', 'message' => 'Nenhum selo encontrado para este arquivo.'],
    JSON_UNESCAPED_UNICODE);
