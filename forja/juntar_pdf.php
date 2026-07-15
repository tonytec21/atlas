<?php
error_reporting(0); @ini_set('display_errors','0'); @set_time_limit(0); @ini_set('memory_limit','512M');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!forja_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $ups = forja_salvar_uploads(true, false);
    if (count($ups) < 2) throw new RuntimeException('Envie ao menos 2 PDFs para juntar.');
    $r = forja_juntar_pdfs(array_column($ups, 'path'));
    $token = forja_registrar_saida($r['path'], 'pdf_unico.pdf');
    echo json_encode(['status' => 'success', 'token' => $token, 'paginas' => $r['paginas'], 'tamanho' => filesize($r['path'])]);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
