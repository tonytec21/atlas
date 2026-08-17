<?php
/**
 * ATLAS-NFSE-BUILD: 2026-08-17-sincronizacao-lote
 *
 * Sincronização em lote das notas rejeitadas.
 *
 * Consulta cada DPS pelo seu Id no Ambiente Nacional e, quando a NFS-e
 * existe lá, corrige o registro local. Nada é emitido: só leitura da
 * SEFIN e correção do que já existe.
 *
 * Serve para o caso em que a emissão deu certo mas foi gravada como
 * rejeitada — por exemplo quando a recepção responde 201 Created e o
 * emissor esperava 200.
 *
 * Ações:
 *   acao=fila   -> devolve as candidatas (não altera nada)
 *   acao=uma    -> sincroniza uma nota (nota_id)
 */
include(__DIR__ . '/../session_check.php');
checkSession();

require_once __DIR__ . '/nfse_lib.php';
require_once __DIR__ . '/nfse_transporte.php';

date_default_timezone_set('America/Sao_Paulo');

$acao = (string) ($_REQUEST['acao'] ?? 'fila');

try {
    nfse_migrar();
    $cfg = nfse_config(true);
    $pdo = nfse_pdo();

    /* ---------------------------------------------------------------
     * Fila: rejeitadas que podem ter gerado NFS-e do outro lado.
     *
     * Ficam de fora as rejeições com código catalogado (E0206, E0207…):
     * essas foram recusadas de verdade e não geraram nota. Consultá-las
     * seria desperdício de requisição.
     * --------------------------------------------------------------- */
    if ($acao === 'fila') {
        $st = $pdo->prepare(
            "SELECT n.id, n.ordem_servico_id, n.numero_dps, n.id_dps, n.http_status,
                    n.criado_em, n.tomador_nome, n.valor_servico
               FROM nfse_notas n
              WHERE n.status = 'rejeitada'
                AND n.ambiente = :amb
                AND n.id_dps IS NOT NULL AND n.id_dps <> ''
                AND (n.chave_acesso IS NULL OR n.chave_acesso = '')
                AND n.sinc_verificada_em IS NULL
                AND (n.mensagem IS NULL OR n.mensagem NOT REGEXP 'E[0-9]{3,4}')
                AND NOT EXISTS (
                    SELECT 1 FROM nfse_notas v
                     WHERE v.ordem_servico_id = n.ordem_servico_id
                       AND v.ambiente = n.ambiente
                       AND v.status = 'autorizada')
              ORDER BY
                CASE WHEN n.http_status >= 200 AND n.http_status < 300 THEN 0 ELSE 1 END,
                n.id DESC
              LIMIT 500"
        );
        $st->execute([':amb' => $cfg['ambiente']]);
        $itens = $st->fetchAll(PDO::FETCH_ASSOC);

        $provaveis = 0;
        foreach ($itens as $i) {
            $h = (int) $i['http_status'];
            if ($h >= 200 && $h < 300) { $provaveis++; }
        }

        nfse_json([
            'ok'        => true,
            'total'     => count($itens),
            'provaveis' => $provaveis,   // com 2xx: quase certamente existem na SEFIN
            'itens'     => $itens,
        ]);
    }

    /* ---------------------------------------------------------------
     * Sincroniza uma nota.
     * --------------------------------------------------------------- */
    if ($acao === 'uma') {
        $notaId = (int) ($_REQUEST['nota_id'] ?? 0);

        $st = $pdo->prepare('SELECT * FROM nfse_notas WHERE id = ?');
        $st->execute([$notaId]);
        $nota = $st->fetch(PDO::FETCH_ASSOC);

        if (!$nota) {
            nfse_json(['ok' => false, 'mensagem' => 'NFS-e não encontrada.']);
        }

        if (empty($nota['id_dps'])) {
            nfse_json(['ok' => false, 'mensagem' => 'Registro sem Id de DPS — não há o que consultar.']);
        }

        $achado = nfse_recuperar_por_dps($cfg, (string) $nota['id_dps'], 25);

        if (!$achado) {
            /* Confirmado que esta DPS não gerou nota: marca para não voltar à
               fila nas próximas rodadas. A rejeição continua rejeitada — o que
               muda é só o fato de já ter sido conferida. */
            $pdo->prepare('UPDATE nfse_notas SET sinc_verificada_em = NOW() WHERE id = :id')
                ->execute([':id' => $notaId]);

            nfse_json([
                'ok'       => false,
                'sem_nota' => true,
                'mensagem' => 'A DPS ' . $nota['numero_dps'] . ' não gerou NFS-e.',
            ]);
        }

        $numero = null; $codVer = null;
        if (!empty($achado['xml'])) {
            if (preg_match('~<nNFSe>([^<]+)</nNFSe>~', $achado['xml'], $m)) { $numero = $m[1]; }
            if (preg_match('~<cVerif>([^<]+)</cVerif>~', $achado['xml'], $m)) { $codVer = $m[1]; }
        }

        $pdo->prepare(
            "UPDATE nfse_notas
                SET status = 'autorizada', chave_acesso = :c, numero_nfse = :n,
                    cod_verificacao = :cv, xml_nfse = :x,
                    sinc_verificada_em = NOW(),
                    mensagem = 'Sincronizada: a NFS-e já existia no Ambiente Nacional.'
              WHERE id = :id"
        )->execute([
            ':c' => $achado['chave'], ':n' => $numero, ':cv' => $codVer,
            ':x' => $achado['xml'], ':id' => $notaId,
        ]);

        nfse_log('emissao', 'Nota sincronizada em lote. Chave: ' . $achado['chave'],
            'info', (int) $nota['ordem_servico_id'], $notaId);

        nfse_json([
            'ok'       => true,
            'chave'    => $achado['chave'],
            'numero'   => $numero,
            'mensagem' => 'NFS-e localizada e sincronizada.',
        ]);
    }

    nfse_json(['ok' => false, 'mensagem' => 'Ação inválida.']);
} catch (Throwable $e) {
    error_log('[nfse_sincronizar_lote] ' . $e->getMessage());
    nfse_json(['ok' => false, 'mensagem' => $e->getMessage()]);
}
