<?php
/**
 * ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional
 * Download do XML autorizado da NFS-e (guarda obrigatória do emissor).
 */
include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/nfse_lib.php';

$notaId = (int) ($_GET['nota_id'] ?? 0);

try {
    $st = nfse_pdo()->prepare("SELECT chave_acesso, xml_nfse FROM nfse_notas WHERE id = ?");
    $st->execute([$notaId]);
    $nota = $st->fetch(PDO::FETCH_ASSOC);

    if (!$nota || empty($nota['xml_nfse'])) {
        http_response_code(404);
        exit('XML indisponível. Use "Sincronizar" para buscá-lo no Ambiente Nacional.');
    }

    $nome = ($nota['chave_acesso'] ?: 'nfse-' . $notaId) . '.xml';

    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Content-Length: ' . strlen($nota['xml_nfse']));
    echo $nota['xml_nfse'];
} catch (Throwable $e) {
    http_response_code(500);
    exit('Erro: ' . $e->getMessage());
}
