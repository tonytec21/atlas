<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!notas_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $id = notas_safe_id($_POST['id'] ?? '');
    $com = trim($_POST['usuario'] ?? '');
    if ($id === '' || $com === '') throw new RuntimeException('Parâmetros inválidos.');
    notas_ensure_schema(); $conn = notas_db();
    $st = $conn->prepare("DELETE FROM notas_compartilhadas WHERE owner=? AND note_id=? AND shared_with=?");
    $st->bind_param('sss', $u, $id, $com); $st->execute(); $st->close();
    echo json_encode(['success' => true, 'compartilhamentos' => notas_compartilhamentos($u, $id)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
