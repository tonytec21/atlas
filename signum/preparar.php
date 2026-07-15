<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!asg_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $token = preg_replace('~[^a-f0-9]~', '', $_POST['token'] ?? '');
    $src = asg_dir_tmp() . '/' . $token . '.pdf';
    if ($token === '' || !is_file($src)) throw new RuntimeException('Envie o PDF novamente.');
    $nome = @file_get_contents(asg_dir_tmp() . '/' . $token . '.nome') ?: 'documento.pdf';
    $titular = trim($_POST['titular'] ?? '');
    $pos = ['pagina' => (int)($_POST['pagina'] ?? 1),
            'x' => isset($_POST['x']) ? (float)$_POST['x'] : null,
            'y' => isset($_POST['y']) ? (float)$_POST['y'] : null,
            'w' => isset($_POST['w']) ? (float)$_POST['w'] : 0.30];
    $r = asg_preparar_a3($u, $src, $nome, $pos, $titular);
    // não apaga o upload ainda (só após finalizar); guarda referência para limpeza
    echo json_encode(['success' => true] + $r, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
