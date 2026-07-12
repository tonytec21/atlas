<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!notas_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $cor = notas_cor_valida($_POST['color'] ?? 'amarelo');
    if ($title === '' && $content === '') throw new RuntimeException('Escreva um título ou conteúdo.');
    $id = (string)round(microtime(true) * 1000); // id único (ms)
    if (!notas_gravar($u, $id, $title, $content)) throw new RuntimeException('Falha ao salvar.');
    notas_set_cor($u, $id, $cor);
    $n = notas_ler($u, $id);
    echo json_encode(['success' => true, 'note' => $n], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
