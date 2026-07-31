<?php
/**
 * Atlas · Arquivamento Digital
 * Consulta de selos digitais vinculados a um arquivamento.
 *
 * Tudo por PDO com prepared statements. Se o banco estiver indisponível,
 * as funções devolvem lista vazia em vez de derrubar a página — o acervo
 * documental continua consultável mesmo sem o selador.
 */

/** Todos os selos vinculados ao arquivamento. */
function arq_selos($arquivoId)
{
    $id = arq_id_valido($arquivoId);
    if ($id === '') { return []; }

    $pdo = arq_db();
    if (!$pdo) { return []; }

    try {
        $sql = 'SELECT s.id, s.numero_selo, s.texto_selo, s.qr_code, s.data_geracao,
                       s.valor_qr_code, s.quantidade, s.escrevente, s.ato
                  FROM selos_arquivamentos sa
            INNER JOIN selos s ON s.id = sa.selo_id
                 WHERE sa.arquivo_id = :id
              ORDER BY s.id ASC';
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    } catch (PDOException $e) {
        error_log('[arquivamento] Falha ao consultar selos: ' . $e->getMessage());
        return [];
    }
}

/** Apenas os números, para exibição rápida. */
function arq_numeros_selos($arquivoId)
{
    $nums = [];
    foreach (arq_selos($arquivoId) as $s) {
        if (!empty($s['numero_selo'])) { $nums[] = $s['numero_selo']; }
    }
    return $nums;
}

/** Mapa id => quantidade de selos, para a listagem (uma consulta só). */
function arq_contagem_selos(array $ids)
{
    $ids = array_values(array_filter(array_map('arq_id_valido', $ids), function ($v) { return $v !== ''; }));
    if (empty($ids)) { return []; }

    $pdo = arq_db();
    if (!$pdo) { return []; }

    try {
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT arquivo_id, COUNT(*) AS n FROM selos_arquivamentos
                 WHERE arquivo_id IN ($ph) GROUP BY arquivo_id";
        $st = $pdo->prepare($sql);
        $st->execute($ids);
        $mapa = [];
        foreach ($st->fetchAll() as $r) { $mapa[(string) $r['arquivo_id']] = (int) $r['n']; }
        return $mapa;
    } catch (PDOException $e) {
        error_log('[arquivamento] Falha ao contar selos: ' . $e->getMessage());
        return [];
    }
}
