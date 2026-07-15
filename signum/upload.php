<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!asg_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Selecione um PDF.');
    $f = $_FILES['pdf'];
    if ($f['size'] > 30 * 1024 * 1024) throw new RuntimeException('PDF acima de 30MB.');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $fh = fopen($f['tmp_name'], 'rb'); $head = fread($fh, 5); fclose($fh);
    if ($ext !== 'pdf' || strpos($head, '%PDF') !== 0) throw new RuntimeException('O arquivo não é um PDF válido.');
    $token = bin2hex(random_bytes(10));
    $dest = asg_dir_tmp() . '/' . $token . '.pdf';
    if (!move_uploaded_file($f['tmp_name'], $dest)) throw new RuntimeException('Falha ao receber o arquivo.');
    // guarda o nome original ao lado
    file_put_contents(asg_dir_tmp() . '/' . $token . '.nome', $f['name']);
    echo json_encode(['success' => true, 'token' => $token, 'nome' => $f['name']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
