<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!asg_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $prep = $_POST['prep_token'] ?? '';
    $b64 = preg_replace('~\s+~', '', $_POST['signed_b64'] ?? '');
    $pdf = base64_decode($b64, true);
    if ($pdf === false || $pdf === '') throw new RuntimeException('PDF assinado inválido.');
    $rec = asg_salvar_assinado_serpro($u, $prep, $pdf);
    // limpa upload original
    $token = preg_replace('~[^a-f0-9]~', '', $_POST['token'] ?? '');
    if ($token) { @unlink(asg_dir_tmp() . '/' . $token . '.pdf'); @unlink(asg_dir_tmp() . '/' . $token . '.nome'); }
    echo json_encode(['success' => true, 'doc' => $rec], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
