<?php
/** transferencia_excluir.php — estorna (remove) uma transferência entre contas virtuais. */
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard('json');
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!cap_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    cap_ensure_schema();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('ID inválido.');
    $conn = cap_db();
    $st = $conn->prepare("DELETE FROM conta_transferencias WHERE id=?");
    $st->bind_param('i', $id); $st->execute();
    if ($st->affected_rows === 0) { $st->close(); throw new RuntimeException('Transferência não encontrada.'); }
    $st->close();
    cap_log("Transferência #$id estornada");
    echo json_encode(['success'=>true,'message'=>'Transferência estornada.','saldos'=>cap_saldos()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
