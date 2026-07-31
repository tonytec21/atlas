<?php
/**
 * =====================================================================
 * nfse_reemissao_lib.php — Reemissão de NFS-e rejeitadas
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-07-31-reemissao-em-lote
 *
 * Boa parte das rejeições do Ambiente Nacional é transitória: queda de
 * DNS ("Could not resolve host: sefin.nfse.gov.br"), timeout, indisponi-
 * bilidade momentânea do SEFIN. Nesses casos a DPS sequer chegou ao
 * destino — a consulta devolve E2404 ("Não foi gerada uma NFS-e com o
 * identificador de DPS informado") justamente porque não há o que
 * consultar. O caminho certo é REEMITIR (gerar uma nova DPS), não
 * consultar de novo.
 *
 * Esta biblioteca faz isso pela lista de notas, uma a uma ou em lote,
 * com as travas necessárias:
 *
 *  - a reemissão é sempre por O.S. (mesmo caminho do botão "Emitir NFS-e"
 *    da tela da O.S.), respeitando o GET_LOCK por O.S. do nfse_lib;
 *  - O.S. que já tenha nota autorizada/processando NÃO é reemitida —
 *    as rejeições antigas apenas são encerradas, sem gerar nova DPS;
 *  - cada nota rejeitada é marcada como resolvida (reemitida_em) e
 *    apontada para a nota que a substituiu, de modo que ela nunca é
 *    reprocessada num segundo lote;
 *  - o filtro é sempre pelo ambiente configurado no momento, para não
 *    reemitir em produção uma rejeição que era de homologação.
 * =====================================================================
 */

require_once __DIR__ . '/nfse_lib.php';

/**
 * Colunas de controle da reemissão. Idempotente.
 */
function nfse_reemissao_migrar(): void
{
    static $feito = false;
    if ($feito) {
        return;
    }
    $feito = true;

    $pdo = nfse_pdo();

    $colunas = [
        'reemitida_em'      => "DATETIME NULL",
        'reemitida_por'     => "VARCHAR(100) NULL",
        'reemitida_nota_id' => "INT NULL",
    ];

    foreach ($colunas as $nome => $ddl) {
        $existe = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'nfse_notas'
                AND COLUMN_NAME = " . $pdo->quote($nome)
        )->fetchColumn();

        if ($existe === 0) {
            $pdo->exec("ALTER TABLE nfse_notas ADD COLUMN `$nome` $ddl");
        }
    }
}

/**
 * Notas rejeitadas que ainda merecem uma nova tentativa, agrupadas por O.S.
 *
 * Fica de fora: o que já foi reemitido, e a O.S. que já tenha nota
 * autorizada, processando ou cancelada (nesse caso a rejeição é história).
 *
 * @return array<int, array{os_id:int, notas:int[], qtd:int, ultima:array}>
 */
function nfse_reemissao_pendentes(?string $ambiente = null): array
{
    nfse_migrar();
    nfse_reemissao_migrar();

    $cfg = nfse_config();
    $amb = $ambiente ?? (string) ($cfg['ambiente'] ?? '2');

    $pdo = nfse_pdo();
    $st  = $pdo->prepare(
        "SELECT *
           FROM nfse_notas n
          WHERE n.status = 'rejeitada'
            AND n.reemitida_em IS NULL
            AND n.ambiente = :amb
            AND NOT EXISTS (
                  SELECT 1 FROM nfse_notas v
                   WHERE v.ordem_servico_id = n.ordem_servico_id
                     AND v.ambiente = n.ambiente
                     AND v.status IN ('autorizada','processando','cancelada')
                )
          ORDER BY n.ordem_servico_id DESC, n.id DESC"
    );
    $st->execute([':amb' => $amb]);

    $grupos = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $osId = (int) $n['ordem_servico_id'];

        if (!isset($grupos[$osId])) {
            $grupos[$osId] = ['os_id' => $osId, 'notas' => [], 'qtd' => 0, 'ultima' => $n];
        }
        $grupos[$osId]['notas'][] = (int) $n['id'];
        $grupos[$osId]['qtd']++;
    }

    return array_values($grupos);
}

/**
 * Quantas O.S. estão aguardando nova tentativa.
 */
function nfse_reemissao_total(?string $ambiente = null): int
{
    try {
        return count(nfse_reemissao_pendentes($ambiente));
    } catch (Throwable $e) {
        error_log('[nfse_reemissao_total] ' . $e->getMessage());
        return 0;
    }
}

/**
 * Encerra as rejeições antigas, apontando-as para a nota que as substituiu.
 *
 * @param int[] $notaIds
 */
function nfse_reemissao_encerrar(array $notaIds, ?int $novaNotaId): void
{
    $notaIds = array_values(array_unique(array_filter(array_map('intval', $notaIds))));
    if (!$notaIds) {
        return;
    }

    nfse_reemissao_migrar();

    $pdo = nfse_pdo();
    $in  = implode(',', $notaIds);

    $pdo->prepare(
        "UPDATE nfse_notas
            SET reemitida_em = NOW(), reemitida_por = :usr, reemitida_nota_id = :nova
          WHERE id IN ($in)"
    )->execute([
        ':usr'  => $_SESSION['username'] ?? 'sistema',
        ':nova' => $novaNotaId ?: null,
    ]);
}

/**
 * Tenta emitir novamente a NFS-e de uma O.S. que ficou rejeitada.
 *
 * @return array{ok:bool, os_id:int, mensagem:string, notas:array, ja_possui:bool}
 */
function nfse_reemitir_os(int $osId): array
{
    nfse_migrar();
    nfse_reemissao_migrar();

    $base = ['ok' => false, 'os_id' => $osId, 'mensagem' => '', 'notas' => [], 'ja_possui' => false];

    if ($osId <= 0) {
        return $base + ['mensagem' => 'Ordem de Serviço não informada.'];
    }

    $cfg = nfse_config();
    $amb = (string) ($cfg['ambiente'] ?? '2');
    $pdo = nfse_pdo();

    /* Rejeições pendentes desta O.S., capturadas ANTES da nova tentativa. */
    $st = $pdo->prepare(
        "SELECT id FROM nfse_notas
          WHERE ordem_servico_id = :os AND ambiente = :amb
            AND status = 'rejeitada' AND reemitida_em IS NULL"
    );
    $st->execute([':os' => $osId, ':amb' => $amb]);
    $antigas = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    /* Já existe nota válida? Nada a reemitir — apenas encerra as rejeições,
       para que a O.S. saia da fila e não gere DPS duplicada. */
    $existente = nfse_nota_existente($osId);
    if ($existente) {
        nfse_reemissao_encerrar($antigas, (int) $existente['id']);

        return [
            'ok'        => true,
            'os_id'     => $osId,
            'ja_possui' => true,
            'notas'     => [$existente],
            'mensagem'  => 'A O.S. já possui NFS-e ' . $existente['status']
                         . ' (' . ($existente['chave_acesso'] ?: 'sem chave') . '). '
                         . 'A rejeição anterior foi encerrada.',
        ];
    }

    $r = nfse_emitir_os($osId, false);

    if (!empty($r['ok']) && !empty($r['notas'])) {
        $novaId = 0;
        foreach ($r['notas'] as $n) {
            if (!empty($n['id'])) {
                $novaId = (int) $n['id'];
            }
        }
        nfse_reemissao_encerrar($antigas, $novaId ?: null);
        nfse_log('reemissao', 'Reemissão bem-sucedida. Notas anteriores encerradas: '
                 . (implode(', ', $antigas) ?: '—'), 'info', $osId, $novaId ?: null);
    } else {
        nfse_log('reemissao', 'Nova tentativa falhou: ' . ($r['mensagem'] ?? ''), 'error', $osId);
    }

    return [
        'ok'        => (bool) ($r['ok'] ?? false),
        'os_id'     => $osId,
        'ja_possui' => false,
        'notas'     => $r['notas'] ?? [],
        'mensagem'  => (string) ($r['mensagem'] ?? 'Falha desconhecida.'),
    ];
}
