<?php
/** ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional */
include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/nfse_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nfse_json(['ok' => false, 'mensagem' => 'Método inválido.'], 405);
}

$osId   = (int) ($_POST['os_id'] ?? 0);
$forcar = ($_POST['forcar'] ?? '0') === '1';

if ($osId <= 0) {
    nfse_json(['ok' => false, 'mensagem' => 'Ordem de Serviço não informada.']);
}

try {
    $r = nfse_emitir_os($osId, $forcar);
    nfse_json([
        'ok'       => $r['ok'],
        'mensagem' => $r['mensagem'],
        'notas'    => $r['notas'],
    ]);
} catch (Throwable $e) {
    error_log('[nfse_emitir] ' . $e->getMessage());
    nfse_log('emissao', $e->getMessage(), 'error', $osId);
    nfse_json(['ok' => false, 'mensagem' => $e->getMessage()], 500);
}
