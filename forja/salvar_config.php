<?php
error_reporting(0); @ini_set('display_errors','0');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!forja_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    forja_require_admin();
    $campos = [];
    if (isset($_POST['gs_path']))      $campos['gs_path'] = trim($_POST['gs_path']);
    if (isset($_POST['magick_path']))  $campos['magick_path'] = trim($_POST['magick_path']);
    if (isset($_POST['lo_path']))      $campos['lo_path'] = trim($_POST['lo_path']);
    if (isset($_POST['retencao_horas']))  $campos['retencao_horas']  = max(1, min(72, (int)$_POST['retencao_horas']));
    if (isset($_POST['limite_upload_mb'])) $campos['limite_upload_mb'] = max(10, min(4096, (int)$_POST['limite_upload_mb']));
    if (isset($_POST['img2pdf_max_px']))  $campos['img2pdf_max_px']  = max(600, min(6000, (int)$_POST['img2pdf_max_px']));
    $campos['forja_ativo'] = (($_POST['forja_ativo'] ?? '') === 'S') ? 'S' : 'N';
    forja_config_set($campos);
    echo json_encode(['status' => 'success', 'message' => 'Configurações salvas.']);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
