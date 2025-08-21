<?php
include(__DIR__ . '/session_check.php');
checkSession();

header('Content-Type: application/json; charset=utf-8');

$order = $_POST['order'] ?? [];

if (is_string($order)) {
    $decoded = json_decode($order, true);
    if (is_array($decoded)) $order = $decoded;
}

if (!is_array($order)) $order = [];

$file = __DIR__ . '/order_indexador.json';
$tmp  = $file . '.tmp';

$data = ['order' => array_values($order)];
$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$ok = file_put_contents($tmp, $json, LOCK_EX) !== false && @rename($tmp, $file);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Falha ao salvar ordem']);
    exit;
}

echo json_encode(['ok' => true]);
