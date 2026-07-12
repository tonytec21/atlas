<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!notas_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $id = notas_safe_id($_POST['id'] ?? '');
    $owner = trim($_POST['owner'] ?? $u);
    if ($id === '' || $owner !== $u) throw new RuntimeException('Só o dono pode alterar a cor.');
    if (!notas_set_cor($u, $id, $_POST['color'] ?? '')) throw new RuntimeException('Falha ao salvar cor.');
    echo json_encode(['success' => true, 'color' => notas_cor_valida($_POST['color'] ?? '')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
