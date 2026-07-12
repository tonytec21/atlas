<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $u = $_SESSION['username'];
    $id = notas_safe_id($_GET['id'] ?? $_POST['id'] ?? '');
    $owner = trim($_GET['owner'] ?? $_POST['owner'] ?? $u);
    if ($id === '') throw new RuntimeException('Nota inválida.');
    if (!notas_pode_ler($u, $owner, $id)) throw new RuntimeException('Sem permissão.');
    $n = notas_ler($owner, $id);
    if (!$n) throw new RuntimeException('Nota não encontrada.');
    echo json_encode(['success' => true, 'note' => $n], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
