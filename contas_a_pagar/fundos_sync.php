<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard('json');
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !cap_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $r = cap_sync_fundos_selo(true); // forçado
    if (!$r['ok'] && $r['motivo']) throw new RuntimeException($r['motivo']);
    $msg = 'Fundos sincronizados: ' . $r['criadas'] . ' criadas, ' . $r['atualizadas'] . ' atualizadas'
         . ($r['ignoradas_pagas'] ? (', ' . $r['ignoradas_pagas'] . ' já pagas mantidas') : '') . '.';
    echo json_encode(['success'=>true,'message'=>$msg,'resumo'=>$r], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
