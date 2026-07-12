<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $u = $_SESSION['username'];
    $id = notas_safe_id($_GET['id'] ?? '');
    if ($id === '') throw new RuntimeException('Nota inválida.');
    $lista = notas_compartilhamentos($u, $id);
    foreach ($lista as &$l) $l['nome'] = notas_nome_usuario($l['shared_with']);
    echo json_encode(['success' => true, 'compartilhamentos' => $lista], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
