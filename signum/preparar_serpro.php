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
    $cpf = preg_replace('~\D~', '', $_POST['cpf'] ?? '');
    $pos = ['pagina' => (int)($_POST['page'] ?? $_POST['pagina'] ?? 1),
            'x' => isset($_POST['xn']) ? (float)$_POST['xn'] : (isset($_POST['x']) ? (float)$_POST['x'] : null),
            'y' => isset($_POST['yn']) ? (float)$_POST['yn'] : (isset($_POST['y']) ? (float)$_POST['y'] : null),
            'w' => isset($_POST['wn']) ? (float)$_POST['wn'] : (isset($_POST['w']) ? (float)$_POST['w'] : 0.30)];
    $r = asg_preparar_serpro($u, $src, $nome, $pos, $titular, $cpf);
    echo json_encode(['status' => 'success'] + $r, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
