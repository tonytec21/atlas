<?php
/**
 * =====================================================================
 * nfse_reemitir.php — Endpoint de reemissão de NFS-e rejeitadas
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-07-31-reemissao-em-lote
 *
 * acao=listar  (GET)  -> fila de O.S. com rejeição pendente
 * acao=emitir  (POST) -> nova tentativa para UMA O.S.  (os_id ou nota_id)
 *
 * O lote é conduzido pelo navegador, uma O.S. por requisição. É de
 * propósito: cada emissão fala com o SEFIN e pode levar alguns segundos;
 * um laço no PHP estouraria o max_execution_time e deixaria o operador
 * sem saber o que foi ou não emitido.
 * =====================================================================
 */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_reemissao_lib.php';

$acao = $_REQUEST['acao'] ?? 'listar';

try {
    /* ---------------------------------------------------------------- *
     * Fila de reemissão
     * ---------------------------------------------------------------- */
    if ($acao === 'listar') {
        $itens = [];
        foreach (nfse_reemissao_pendentes() as $g) {
            $u = $g['ultima'];
            $itens[] = [
                'os_id'         => $g['os_id'],
                'qtd'           => $g['qtd'],
                'notas'         => $g['notas'],
                'nota_id'       => (int) $u['id'],
                'numero_dps'    => (int) $u['numero_dps'],
                'tomador_nome'  => $u['tomador_nome'],
                'valor_servico' => (float) $u['valor_servico'],
                'mensagem'      => mb_substr((string) $u['mensagem'], 0, 300, 'UTF-8'),
            ];
        }

        nfse_json(['ok' => true, 'total' => count($itens), 'itens' => $itens]);
    }

    /* ---------------------------------------------------------------- *
     * Nova tentativa (uma O.S.)
     * ---------------------------------------------------------------- */
    if ($acao === 'emitir') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            nfse_json(['ok' => false, 'mensagem' => 'Método inválido.'], 405);
        }

        $osId   = (int) ($_POST['os_id'] ?? 0);
        $notaId = (int) ($_POST['nota_id'] ?? 0);

        /* Aceita também o id da nota rejeitada: resolve a O.S. a partir dela. */
        if ($osId <= 0 && $notaId > 0) {
            $st = nfse_pdo()->prepare("SELECT ordem_servico_id FROM nfse_notas WHERE id = ?");
            $st->execute([$notaId]);
            $osId = (int) $st->fetchColumn();
        }

        if ($osId <= 0) {
            nfse_json(['ok' => false, 'mensagem' => 'Ordem de Serviço não identificada.']);
        }

        $r = nfse_reemitir_os($osId);
        nfse_json($r);
    }

    nfse_json(['ok' => false, 'mensagem' => 'Ação desconhecida.'], 400);

} catch (Throwable $e) {
    error_log('[nfse_reemitir] ' . $e->getMessage());
    nfse_json([
        'ok'       => false,
        'os_id'    => (int) ($_POST['os_id'] ?? 0),
        'mensagem' => $e->getMessage(),
    ], 500);
}
