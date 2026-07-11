<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/assinatura_os_config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $tipo = trim((string)($_POST['tipo'] ?? $_GET['tipo'] ?? ''));
    $osId = (int)($_POST['os_id'] ?? $_GET['os_id'] ?? 0);
    if (!os_tipo_valido($tipo) || $osId <= 0) throw new RuntimeException('Parâmetros inválidos.');
    $bytes = os_generate_pdf_bytes($tipo, $osId);
    echo json_encode(['status'=>'success','pdf_base64'=>base64_encode($bytes)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
