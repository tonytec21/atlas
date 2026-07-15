<?php
error_reporting(0); @ini_set('display_errors','0'); @set_time_limit(0); @ini_set('memory_limit','512M');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!forja_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $ups = forja_salvar_uploads(false, false, true);   // aceita Word/ODT/RTF/TXT
    $out = forja_word_para_pdf($ups[0]['path']);
    $nome = preg_replace('~[^A-Za-z0-9_\-]~', '_', pathinfo($ups[0]['nome'], PATHINFO_FILENAME)) . '.pdf';
    $token = forja_registrar_saida($out, $nome);
    echo json_encode(['status' => 'success', 'token' => $token, 'tamanho' => filesize($out)]);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
