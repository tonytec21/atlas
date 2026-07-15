<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!asg_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $sign_token = $_POST['sign_token'] ?? '';
    $cmsB64 = $_POST['cms_b64'] ?? '';
    $der = base64_decode(preg_replace('~\s+~', '', $cmsB64), true);
    if ($der === false || $der === '') throw new RuntimeException('Assinatura (CMS) inválida.');
    $rec = asg_finalizar_a3($u, $sign_token, $der);
    // limpa o upload original
    $token = preg_replace('~[^a-f0-9]~', '', $_POST['token'] ?? '');
    if ($token) { @unlink(asg_dir_tmp() . '/' . $token . '.pdf'); @unlink(asg_dir_tmp() . '/' . $token . '.nome'); }
    echo json_encode(['success' => true, 'doc' => $rec], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
