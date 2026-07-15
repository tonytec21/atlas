<?php
error_reporting(0); @ini_set('display_errors','0'); @set_time_limit(0); @ini_set('memory_limit','512M');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!forja_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $ups = forja_salvar_uploads(true, false);          // entrada é PDF
    $modo = $_POST['modo'] ?? 'layout';
    if (!in_array($modo, ['formatado', 'simples', 'layout'], true)) $modo = 'formatado';
    $out = forja_pdf_para_word($ups[0]['path'], $modo);
    $nome = preg_replace('~[^A-Za-z0-9_\-]~', '_', pathinfo($ups[0]['nome'], PATHINFO_FILENAME)) . '.docx';
    $token = forja_registrar_saida($out, $nome);
    echo json_encode(['status' => 'success', 'token' => $token, 'tamanho' => filesize($out)]);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
