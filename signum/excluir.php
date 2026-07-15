<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!asg_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $id = (int)($_POST['id'] ?? 0);
    $doc = asg_doc($id);
    if (!$doc) throw new RuntimeException('Documento não encontrado.');
    $conn = asg_db();
    $st = $conn->prepare("UPDATE assinatura_documentos SET status='excluido' WHERE id=?");
    $st->bind_param('i', $id); $st->execute(); $st->close();
    @unlink(asg_dir_sig() . '/' . basename($doc['arquivo']));
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
