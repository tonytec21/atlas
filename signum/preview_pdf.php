<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!asg_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $token = preg_replace('~[^a-f0-9]~', '', $_POST['token'] ?? '');
    $p = asg_dir_tmp() . '/' . $token . '.pdf';
    if ($token === '' || !is_file($p)) throw new RuntimeException('Arquivo não encontrado. Reenvie o PDF.');
    $nome = @file_get_contents(asg_dir_tmp() . '/' . $token . '.nome') ?: 'documento.pdf';
    echo json_encode(['status' => 'success', 'pdf_base64' => base64_encode(file_get_contents($p)), 'nome' => $nome], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
