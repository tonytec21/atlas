<?php
/**
 * ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional
 * Reconsulta uma NFS-e no Ambiente Nacional e ressincroniza o status local.
 */
include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/nfse_lib.php';

$notaId = (int) ($_REQUEST['nota_id'] ?? 0);

try {
    $pdo = nfse_pdo();
    $st  = $pdo->prepare("SELECT * FROM nfse_notas WHERE id = ?");
    $st->execute([$notaId]);
    $nota = $st->fetch(PDO::FETCH_ASSOC);

    if (!$nota) {
        nfse_json(['ok' => false, 'mensagem' => 'NFS-e não encontrada.']);
    }

    // Sem chave, a nota nunca chegou a ser autorizada. Tenta pela DPS.
    if (empty($nota['chave_acesso'])) {
        $nfse = nfse_cliente();
        $resp = $nfse->contribuinte()->consultarDps($nota['id_dps']);

        if (!empty($resp->chaveAcesso)) {
            $pdo->prepare("UPDATE nfse_notas SET chave_acesso = :c, status = 'autorizada', mensagem = NULL WHERE id = :id")
                ->execute([':c' => $resp->chaveAcesso, ':id' => $notaId]);
            nfse_json(['ok' => true, 'mensagem' => 'NFS-e localizada e sincronizada.', 'chave' => $resp->chaveAcesso]);
        }
        nfse_json(['ok' => false, 'mensagem' => 'A DPS ' . $nota['id_dps'] . ' ainda não gerou NFS-e.']);
    }

    $dados = nfse_consultar_chave($nota['chave_acesso']);
    if (!$dados) {
        nfse_json(['ok' => false, 'mensagem' => 'O Ambiente Nacional não retornou a NFS-e ' . $nota['chave_acesso'] . '.']);
    }

    $pdo->prepare("UPDATE nfse_notas SET xml_nfse = :x WHERE id = :id")
        ->execute([':x' => $dados->nfseXml ?? null, ':id' => $notaId]);

    nfse_json([
        'ok'       => true,
        'mensagem' => 'NFS-e sincronizada.',
        'chave'    => $nota['chave_acesso'],
        'numero'   => $dados->infNfse->numeroNfse ?? null,
    ]);
} catch (Throwable $e) {
    error_log('[nfse_consultar] ' . $e->getMessage());
    nfse_json(['ok' => false, 'mensagem' => $e->getMessage()]);
}
