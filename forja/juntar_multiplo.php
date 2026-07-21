<?php
error_reporting(0); @ini_set('display_errors','0'); @set_time_limit(0); @ini_set('memory_limit','512M');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!forja_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $a = forja_salvar_uploads(true, false, false, 'ladoA');
    $b = forja_salvar_uploads(true, false, false, 'ladoB');
    $pos = ($_POST['posicao'] ?? 'antes') === 'depois' ? 'depois' : 'antes';
    $r = forja_juntar_multiplo(array_column($a, 'path'), $b, $pos);
    $token = forja_registrar_saida($r['zip'], 'uniao_multipla.zip');
    echo json_encode(['status' => 'success', 'token' => $token, 'total' => $r['total']]);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
