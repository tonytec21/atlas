<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard('json');
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!cap_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $email = trim((string)($_POST['email_notificacao'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('E-mail de notificação inválido.');
    // mantém a senha atual se vier vazia
    $cur = cap_settings_get();
    $pass = ($_POST['smtp_pass'] ?? '') !== '' ? $_POST['smtp_pass'] : ($cur['smtp_pass'] ?? '');
    cap_settings_save([
        'email_notificacao' => $email ?: null,
        'dias_aviso' => (int)($_POST['dias_aviso'] ?? 3),
        'notif_ativo' => isset($_POST['notif_ativo']) ? 1 : 0,
        'smtp_host' => trim((string)($_POST['smtp_host'] ?? '')) ?: null,
        'smtp_port' => $_POST['smtp_port'] ?? '',
        'smtp_secure' => trim((string)($_POST['smtp_secure'] ?? '')) ?: null,
        'smtp_user' => trim((string)($_POST['smtp_user'] ?? '')) ?: null,
        'smtp_pass' => $pass ?: null,
        'smtp_from_email' => trim((string)($_POST['smtp_from_email'] ?? '')) ?: null,
        'smtp_from_name' => trim((string)($_POST['smtp_from_name'] ?? '')) ?: null,
    ]);
    echo json_encode(['success'=>true,'message'=>'Configurações salvas.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
