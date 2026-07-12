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
    $canEdit = !empty($_POST['can_edit']) ? 1 : 0;
    if ($id === '') throw new RuntimeException('Nota inválida.');
    if ($com === '') throw new RuntimeException('Escolha um usuário.');
    if ($com === $u) throw new RuntimeException('Você já é o dono desta nota.');
    if (!notas_ler($u, $id)) throw new RuntimeException('Nota não encontrada ou você não é o dono.');

    notas_ensure_schema(); $conn = notas_db();
    $agora = date('Y-m-d H:i:s');
    $st = $conn->prepare("INSERT INTO notas_compartilhadas (owner, note_id, shared_with, can_edit, created_at)
                          VALUES (?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE can_edit=VALUES(can_edit)");
    $st->bind_param('sssis', $u, $id, $com, $canEdit, $agora);
    $st->execute(); $st->close();
    echo json_encode(['success' => true, 'message' => 'Compartilhada com ' . notas_nome_usuario($com) . '.',
        'compartilhamentos' => notas_compartilhamentos($u, $id)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
