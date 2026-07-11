<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/pagamento_anexos_config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!pa_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    pa_ensure_schema();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('ID inválido.');
    $conn = pa_db();
    $stmt = $conn->prepare("SELECT * FROM pagamento_os_anexos WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id); $stmt->execute();
    $a = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$a) throw new RuntimeException('Anexo não encontrado.');

    $path = realpath(pa_dir() . '/' . $a['arquivo']);
    $base = realpath(pa_dir());
    if ($path !== false && strncmp($path, $base . DIRECTORY_SEPARATOR, strlen($base)+1) === 0 && is_file($path)) @unlink($path);

    $d = $conn->prepare("DELETE FROM pagamento_os_anexos WHERE id=?");
    $d->bind_param('i', $id); $d->execute(); $d->close();
    echo json_encode(['success'=>true,'total'=>count(pa_lista((int)$a['pagamento_id']))], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
