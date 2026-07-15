<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!asg_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $session = $_POST['session'] ?? '';
    $cms = $_POST['signature_b64'] ?? ($_POST['cms_b64'] ?? '');
    $subject = $_POST['cert_subject'] ?? '';
    $rec = asg_finalizar_serpro($u, $session, $cms, $subject);
    $token = preg_replace('~[^a-f0-9]~', '', $_POST['token'] ?? '');
    if ($token) { @unlink(asg_dir_tmp() . '/' . $token . '.pdf'); @unlink(asg_dir_tmp() . '/' . $token . '.nome'); }
    echo json_encode(['status' => 'success', 'doc' => $rec], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
