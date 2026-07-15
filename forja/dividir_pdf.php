<?php
error_reporting(0); @ini_set('display_errors','0'); @set_time_limit(0); @ini_set('memory_limit','512M');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!forja_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $ups = forja_salvar_uploads(true, false);
    $modo = ($_POST['modo'] ?? 'partes') === 'paginas' ? 'paginas' : 'partes';
    $valor = (int)($_POST['valor'] ?? 2);
    if ($valor < 2 && $modo === 'partes') throw new RuntimeException('Informe pelo menos 2 partes.');
    if ($valor < 1) throw new RuntimeException('Valor inválido.');
    $r = forja_dividir_pdf($ups[0]['path'], $modo, $valor);
    $nome = 'partes_' . preg_replace('~[^A-Za-z0-9_\-]~', '_', pathinfo($ups[0]['nome'], PATHINFO_FILENAME)) . '.zip';
    $token = forja_registrar_saida($r['zip'], $nome);
    echo json_encode(['status' => 'success', 'token' => $token, 'partes' => $r['partes'], 'total_paginas' => $r['total_paginas']]);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
