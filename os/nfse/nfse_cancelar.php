<?php
/** ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nfse_json(['ok' => false, 'mensagem' => 'Método inválido.'], 405);
}

$notaId  = (int) ($_POST['nota_id'] ?? 0);
$cMotivo = trim((string) ($_POST['c_motivo'] ?? ''));
$xMotivo = trim((string) ($_POST['x_motivo'] ?? ''));

if ($notaId <= 0) {
    nfse_json(['ok' => false, 'mensagem' => 'NFS-e não informada.']);
}

try {
    nfse_cancelar($notaId, $cMotivo, $xMotivo);
    nfse_json(['ok' => true, 'mensagem' => 'NFS-e cancelada com sucesso.']);
} catch (Throwable $e) {
    error_log('[nfse_cancelar] ' . $e->getMessage());
    nfse_json(['ok' => false, 'mensagem' => $e->getMessage()]);
}
