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
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($id === '') throw new RuntimeException('Nota inválida.');

    // dono edita sempre; destinatário só se can_edit=1
    $podeEditar = ($owner === $u);
    if (!$podeEditar) {
        foreach (notas_compartilhamentos($owner, $id) as $sh)
            if ($sh['shared_with'] === $u && (int)$sh['can_edit'] === 1) { $podeEditar = true; break; }
    }
    if (!$podeEditar) throw new RuntimeException('Você não tem permissão para editar esta nota.');
    if ($title === '' && $content === '') throw new RuntimeException('Escreva um título ou conteúdo.');
    if (!notas_gravar($owner, $id, $title, $content)) throw new RuntimeException('Falha ao salvar.');
    echo json_encode(['success' => true, 'note' => notas_ler($owner, $id)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
