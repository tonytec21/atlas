<?php
error_reporting(0); @ini_set('display_errors','0'); @set_time_limit(0); @ini_set('memory_limit','512M');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!forja_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $ups = forja_salvar_uploads(false, true);
    $paths = array_column($ups, 'path');
    $r = forja_imagens_para_pdf($paths, $_POST['modo'] ?? 'imagem');
    $token = forja_registrar_saida($r['path'], 'imagens_para_pdf.pdf');
    echo json_encode(['status' => 'success', 'token' => $token, 'paginas' => $r['paginas'], 'tamanho' => filesize($r['path'])]);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
