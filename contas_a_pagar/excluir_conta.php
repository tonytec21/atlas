<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!cap_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    cap_ensure_schema();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('ID inválido.');
    $conn = cap_db();
    // remove anexos do disco
    $base = realpath(cap_dir_anexos());
    $st = $conn->prepare("SELECT arquivo FROM conta_anexos WHERE conta_id=?");
    $st->bind_param('i', $id); $st->execute(); $r = $st->get_result();
    while ($a = $r->fetch_assoc()) {
        $p = realpath(__DIR__ . '/' . $a['arquivo']);
        if ($p && strncmp($p, $base . DIRECTORY_SEPARATOR, strlen($base)+1) === 0 && is_file($p)) @unlink($p);
    }
    $st->close();
    $conn->query("DELETE FROM conta_anexos WHERE conta_id=" . (int)$id);
    $d = $conn->prepare("DELETE FROM contas_a_pagar WHERE id=?");
    $d->bind_param('i', $id); $d->execute(); $d->close();
    cap_log("Conta #$id excluída");
    echo json_encode(['success'=>true,'message'=>'Conta excluída.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
