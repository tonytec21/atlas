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
    if ($id === '') throw new RuntimeException('Nota inválida.');
    notas_ensure_schema(); $conn = notas_db();

    if ($owner === $u) {
        // move .txt p/ lixeira, remove .json e todos os compartilhamentos
        $dir = notas_user_dir($u); $txt = $dir . '/' . $id . '.txt';
        $lix = __DIR__ . '/lixeira/' . preg_replace('~[^A-Za-z0-9_\-\.]~','_',$u);
        if (!is_dir($lix)) @mkdir($lix, 0775, true);
        if (is_file($txt)) @rename($txt, $lix . '/' . $id . '.txt');
        @unlink($dir . '/' . $id . '.json');
        $st = $conn->prepare("DELETE FROM notas_compartilhadas WHERE owner=? AND note_id=?");
        $st->bind_param('ss', $u, $id); $st->execute(); $st->close();
        echo json_encode(['success' => true, 'removed' => 'own'], JSON_UNESCAPED_UNICODE);
    } else {
        // destinatário: remove apenas o compartilhamento dele
        $st = $conn->prepare("DELETE FROM notas_compartilhadas WHERE owner=? AND note_id=? AND shared_with=?");
        $st->bind_param('sss', $owner, $id, $u); $st->execute(); $st->close();
        echo json_encode(['success' => true, 'removed' => 'share'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
