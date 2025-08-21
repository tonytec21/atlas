<?php
include(__DIR__ . '/session_check.php');
checkSession();

header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/order_indexador.json';

if (!file_exists($file)) {
    echo json_encode(['order' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$json = file_get_contents($file);
$data = json_decode($json, true);

if (!is_array($data) || !isset($data['order']) || !is_array($data['order'])) {
    echo json_encode(['order' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['order' => array_values($data['order'])], JSON_UNESCAPED_UNICODE);
