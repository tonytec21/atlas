<?php
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_nota_config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!nd_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('ID inválido.');
    $conn = nd_db();
    $stmt = $conn->prepare("SELECT arquivo FROM nota_anexos WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id); $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) throw new RuntimeException('Anexo não encontrado.');
    $rel = $res->fetch_assoc()['arquivo']; $stmt->close();

    $base = realpath(nd_dir_anexos());
    $path = realpath(__DIR__ . '/' . $rel);
    if ($path !== false && strncmp($path, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) === 0 && is_file($path)) @unlink($path);

    $del = $conn->prepare("DELETE FROM nota_anexos WHERE id = ?");
    $del->bind_param('i', $id); $del->execute(); $del->close();
    echo json_encode(['status'=>'success'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
