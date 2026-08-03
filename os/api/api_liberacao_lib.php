<?php
/**
 * =====================================================================
 * api_liberacao_lib.php — Liberação de atos liquidados (desfazer)
 * ---------------------------------------------------------------------
 * ATLAS-OS-API-LIBERACAO-BUILD: 2026-08-01-v1
 *
 * Espelha a regra da tela `liberar_os.php`, com granularidade maior.
 *
 * REGRA CENTRAL (idêntica à da tela)
 * ----------------------------------
 * Só se desfaz o que foi liquidado HOJE. Havendo qualquer ato liquidado
 * em data anterior, a O.S. inteira fica bloqueada — mexer em movimento
 * de dia fechado desmancharia o caixa e o fechamento já impressos.
 *
 * TRÊS GRANULARIDADES
 * -------------------
 *   liquidacao_id  -> desfaz UMA liquidação específica (inequívoco)
 *   item_id        -> desfaz as liquidações de hoje daquele item
 *   nenhum dos dois-> desfaz a O.S. inteira (igual ao botão da tela)
 *
 * POR QUE `liquidacao_id` EXISTE
 * ------------------------------
 * `atos_liquidados` não guarda o `item_id` — o vínculo com o item é
 * feito pelo código do ato. Quando a O.S. tem dois itens do MESMO ato
 * (acontece: duas certidões com descrições diferentes), casar por ato é
 * ambíguo. Nesse caso a API recusa o `item_id` e exige o
 * `liquidacao_id`, que vem de GET /v1/os/{n}/liquidacoes.
 *
 * NFS-e
 * -----
 * Se a O.S. tem NFS-e autorizada, a liberação é recusada. Desfazer a
 * liquidação de um ato já faturado deixaria a nota sem lastro. Cancele
 * a NFS-e primeiro.
 * =====================================================================
 */

require_once __DIR__ . '/api_config.php';
require_once __DIR__ . '/api_auth.php';
require_once __DIR__ . '/api_lib.php';

/* --------------------------------------------------------------------
 * Schema
 * ------------------------------------------------------------------ */

/**
 * Tabela de log compartilhada com a tela `liberar_os.php`, mais as
 * colunas que identificam a origem pela API. Idempotente.
 */
function api_liberacao_migrar(): void
{
    static $feito = false;
    if ($feito) {
        return;
    }
    $feito = true;

    $pdo = api_pdo();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS os_liberacao_log (
            id                   INT AUTO_INCREMENT PRIMARY KEY,
            ordem_servico_id     INT NOT NULL,
            usuario_id           INT NULL,
            usuario_nome         VARCHAR(255) NULL,
            ip                   VARCHAR(45)  NULL,
            user_agent           VARCHAR(255) NULL,
            antes_liquidados     INT NOT NULL DEFAULT 0,
            antes_manuais        INT NOT NULL DEFAULT 0,
            antes_itens_afetados INT NOT NULL DEFAULT 0,
            deletados_liquidados INT NOT NULL DEFAULT 0,
            deletados_manuais    INT NOT NULL DEFAULT 0,
            itens_atualizados    INT NOT NULL DEFAULT 0,
            criado_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_os (ordem_servico_id),
            INDEX idx_criado_em (criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    /* Colunas próprias da liberação via API. */
    $extras = [
        'origem'      => "VARCHAR(20) NULL",
        'sistema_id'  => "INT NULL",
        'escopo'      => "VARCHAR(20) NULL",   // os | item | liquidacao
        'item_id'     => "INT NULL",
        'motivo'      => "VARCHAR(255) NULL",
        'detalhe'     => "TEXT NULL",
    ];
    foreach ($extras as $nome => $ddl) {
        try {
            $existe = (int) $pdo->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'os_liberacao_log'
                    AND COLUMN_NAME = " . $pdo->quote($nome)
            )->fetchColumn();
            if ($existe === 0) {
                $pdo->exec("ALTER TABLE os_liberacao_log ADD COLUMN `$nome` $ddl");
            }
        } catch (Throwable $e) {
            error_log('[api_liberacao_migrar] ' . $e->getMessage());
        }
    }

    /* Selo liberado não é apagado — é marcado. A trilha do que foi
       selado e depois desfeito precisa sobreviver. */
    try {
        $existe = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_selos'
                AND COLUMN_NAME = 'liberado_em'"
        )->fetchColumn();
        if ($existe === 0) {
            $pdo->exec("ALTER TABLE api_selos ADD COLUMN `liberado_em` DATETIME NULL");
        }
    } catch (Throwable $e) {
        error_log('[api_liberacao_migrar/selos] ' . $e->getMessage());
    }
}

/* --------------------------------------------------------------------
 * Leitura
 * ------------------------------------------------------------------ */

/**
 * Liquidações da O.S., separadas entre hoje e datas anteriores.
 */
function api_liberacao_liquidacoes(int $osId): array
{
    $pdo = api_pdo();
    $out = ['hoje' => [], 'anteriores' => []];

    foreach (['atos_liquidados', 'atos_manuais_liquidados'] as $tabela) {
        try {
            $st = $pdo->prepare("SELECT * FROM `$tabela` WHERE ordem_servico_id = ? ORDER BY id");
            $st->execute([$osId]);
        } catch (Throwable $e) {
            error_log('[api_liberacao_liquidacoes] ' . $e->getMessage());
            continue;
        }

        foreach ($st->fetchAll() as $r) {
            $quando = $r['data'] ?? $r['data_liquidacao'] ?? null;
            $ehHoje = $quando && date('Y-m-d', strtotime($quando)) === date('Y-m-d');

            $reg = [
                'liquidacao_id'        => (int) $r['id'],
                'tabela'               => $tabela,
                'ato'                  => $r['ato'],
                'descricao'            => $r['descricao'],
                'quantidade_liquidada' => (int) $r['quantidade_liquidada'],
                'total'                => api_dinheiro($r['total']),
                'funcionario'          => $r['funcionario'] ?? null,
                'data'                 => api_data($quando),
            ];

            $out[$ehHoje ? 'hoje' : 'anteriores'][] = $reg;
        }
    }

    return $out;
}

/**
 * Retrato do que pode (ou não) ser liberado.
 */
function api_liberacao_resumo(int $osId): array
{
    api_liberacao_migrar();

    $os  = api_os_buscar($osId);
    $liq = api_liberacao_liquidacoes($osId);

    $impedimentos = [];

    if ($liq['anteriores']) {
        $impedimentos[] = [
            'codigo'   => 'liquidacao_de_dia_anterior',
            'mensagem' => 'Esta O.S. tem ' . count($liq['anteriores']) . ' ato(s) liquidado(s) em data '
                        . 'anterior a hoje. Não é permitido desfazer — o movimento daquele dia já foi '
                        . 'fechado no caixa.',
        ];
    }

    if (!$liq['hoje']) {
        $impedimentos[] = [
            'codigo'   => 'nada_a_liberar',
            'mensagem' => 'Não há atos liquidados hoje nesta O.S.',
        ];
    }

    /* NFS-e autorizada trava a liberação. */
    $nfse = api_liberacao_nfse($osId);
    if ($nfse) {
        $impedimentos[] = [
            'codigo'   => 'nfse_ativa',
            'mensagem' => 'Esta O.S. possui NFS-e ' . $nfse['status'] . ' (' . $nfse['chave']
                        . '). Cancele a nota antes de desfazer a liquidação — senão a nota fica sem lastro.',
        ];
    }

    return [
        'os'            => $osId,
        'cliente'       => $os['cliente'],
        'cancelada'     => api_os_cancelada($os),
        'pode_liberar'  => count($impedimentos) === 0,
        'impedimentos'  => $impedimentos,
        'data_de_hoje'  => date('Y-m-d'),
        'contagem'      => [
            'liquidados_hoje'       => count(array_filter($liq['hoje'], fn($l) => $l['tabela'] === 'atos_liquidados')),
            'manuais_hoje'          => count(array_filter($liq['hoje'], fn($l) => $l['tabela'] === 'atos_manuais_liquidados')),
            'liquidados_anteriores' => count(array_filter($liq['anteriores'], fn($l) => $l['tabela'] === 'atos_liquidados')),
            'manuais_anteriores'    => count(array_filter($liq['anteriores'], fn($l) => $l['tabela'] === 'atos_manuais_liquidados')),
            'total_hoje'            => count($liq['hoje']),
            'valor_hoje'            => api_dinheiro(array_sum(array_column($liq['hoje'], 'total'))),
        ],
        'liquidacoes_de_hoje'  => $liq['hoje'],
        'liquidacoes_anteriores' => $liq['anteriores'],
        'nfse'                 => $nfse,
        'financeiro'           => api_os_saldo($osId, $os),
    ];
}

/**
 * NFS-e viva (autorizada ou processando) da O.S., se houver.
 */
function api_liberacao_nfse(int $osId): ?array
{
    try {
        $pdo = api_pdo();
        $existe = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nfse_notas'"
        )->fetchColumn();
        if ($existe === 0) {
            return null;
        }

        $st = $pdo->prepare(
            "SELECT id, status, chave_acesso, numero_nfse FROM nfse_notas
              WHERE ordem_servico_id = ? AND status IN ('autorizada','processando')
              ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$osId]);
        $n = $st->fetch();

        if (!$n) {
            return null;
        }

        return [
            'nota_id' => (int) $n['id'],
            'status'  => $n['status'],
            'chave'   => $n['chave_acesso'] ?: 'sem chave',
            'numero'  => $n['numero_nfse'],
        ];
    } catch (Throwable $e) {
        error_log('[api_liberacao_nfse] ' . $e->getMessage());
        return null;
    }
}

/* --------------------------------------------------------------------
 * Execução
 * ------------------------------------------------------------------ */

/**
 * Desfaz liquidações da O.S.
 *
 * @param array $opcoes liquidacao_id | item_id | motivo | operador
 */
function api_liberar(int $osId, array $opcoes = []): array
{
    api_liberacao_migrar();

    $pdo      = api_pdo();
    $lockNome = 'atlas_os_api_liq_' . $osId;   // mesma trava da liquidação

    $obteve = (int) $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockNome) . ", 10)")->fetchColumn();
    if ($obteve !== 1) {
        api_erro('os_ocupada', 'Há outra operação em andamento para esta O.S. Tente novamente em instantes.',
                 409, ['os' => $osId]);
    }

    try {
        $resumo = api_liberacao_resumo($osId);

        /* Bloqueios que valem para qualquer granularidade. */
        foreach ($resumo['impedimentos'] as $imp) {
            if ($imp['codigo'] === 'nada_a_liberar') {
                continue;   // pode ser que o alvo específico exista mesmo assim
            }
            api_erro($imp['codigo'], $imp['mensagem'], 409, [
                'os'           => $osId,
                'impedimentos' => $resumo['impedimentos'],
                'nfse'         => $resumo['nfse'],
            ]);
        }

        $liquidacaoId = (int) ($opcoes['liquidacao_id'] ?? 0);
        $itemId       = (int) ($opcoes['item_id'] ?? 0);

        /* --- seleciona o que será desfeito --- */
        $alvos = api_liberacao_alvos($osId, $resumo['liquidacoes_de_hoje'], $liquidacaoId, $itemId);

        if (!$alvos) {
            api_erro('nada_a_liberar', 'Nada a desfazer com os critérios informados.', 409, [
                'os' => $osId, 'liquidacao_id' => $liquidacaoId ?: null, 'item_id' => $itemId ?: null,
            ]);
        }

        $escopo = $liquidacaoId ? 'liquidacao' : ($itemId ? 'item' : 'os');

        $pdo->beginTransaction();

        $delNormais = 0;
        $delManuais = 0;
        $itensAtualizados = 0;
        $detalhe = [];

        /* Agrupa a quantidade a devolver por item. */
        $devolver = [];

        foreach ($alvos as $a) {
            $st = $pdo->prepare("DELETE FROM `{$a['tabela']}` WHERE id = ? AND ordem_servico_id = ?");
            $st->execute([$a['liquidacao_id'], $osId]);

            if ($st->rowCount() === 0) {
                continue;
            }

            if ($a['tabela'] === 'atos_liquidados') {
                $delNormais++;
            } else {
                $delManuais++;
            }

            $alvoItem = $a['item_id'] ?? null;
            if ($alvoItem) {
                $devolver[$alvoItem] = ($devolver[$alvoItem] ?? 0) + (int) $a['quantidade_liquidada'];
            }

            $detalhe[] = [
                'liquidacao_id' => $a['liquidacao_id'],
                'tabela'        => $a['tabela'],
                'ato'           => $a['ato'],
                'quantidade'    => (int) $a['quantidade_liquidada'],
                'total'         => $a['total'],
                'item_id'       => $alvoItem,
            ];

            /* Selo do ato desfeito: marcado, nunca apagado. */
            try {
                $pdo->prepare(
                    "UPDATE api_selos SET liberado_em = NOW()
                      WHERE liquidacao_id = ? AND os_id = ? AND liberado_em IS NULL"
                )->execute([$a['liquidacao_id'], $osId]);
            } catch (Throwable $e) {
                error_log('[api_liberar/selos] ' . $e->getMessage());
            }
        }

        /* --- devolve a quantidade aos itens --- */
        if ($escopo === 'os') {
            /* Modo O.S.: espelha a tela — zera todos os itens de uma vez.
               É seguro porque, sem liquidação de dia anterior, tudo que
               está liquidado foi desfeito agora. */
            $st = $pdo->prepare(
                "UPDATE ordens_de_servico_itens
                    SET quantidade_liquidada = NULL, status = NULL
                  WHERE ordem_servico_id = ?
                    AND (quantidade_liquidada IS NOT NULL OR status IS NOT NULL)"
            );
            $st->execute([$osId]);
            $itensAtualizados = $st->rowCount();

        } else {
            /* Modo item/liquidação: decrementa só o que foi desfeito e
               recalcula o status, para não zerar liquidação parcial que
               continua válida. */
            foreach ($devolver as $iid => $qtd) {
                $st = $pdo->prepare("SELECT quantidade, quantidade_liquidada FROM ordens_de_servico_itens WHERE id = ?");
                $st->execute([$iid]);
                $it = $st->fetch();
                if (!$it) {
                    continue;
                }

                $nova = max(0, (int) $it['quantidade_liquidada'] - $qtd);
                $novoStatus = $nova <= 0
                    ? null
                    : ($nova >= (int) $it['quantidade'] ? 'liquidado' : 'parcialmente liquidado');

                $up = $pdo->prepare(
                    "UPDATE ordens_de_servico_itens
                        SET quantidade_liquidada = :q, status = :st
                      WHERE id = :id"
                );
                $up->execute([
                    ':q'  => $nova > 0 ? $nova : null,
                    ':st' => $novoStatus,
                    ':id' => $iid,
                ]);
                $itensAtualizados++;
            }
        }

        /* --- log (mesma tabela da tela) --- */
        $pdo->prepare(
            "INSERT INTO os_liberacao_log
             (ordem_servico_id, usuario_id, usuario_nome, ip, user_agent,
              antes_liquidados, antes_manuais, antes_itens_afetados,
              deletados_liquidados, deletados_manuais, itens_atualizados,
              origem, sistema_id, escopo, item_id, motivo, detalhe)
             VALUES (:os, NULL, :usr, :ip, :ua, :al, :am, :ai, :dl, :dm, :iu,
                     'api', :sis, :escopo, :item, :motivo, :detalhe)"
        )->execute([
            ':os'     => $osId,
            ':usr'    => api_operador($opcoes['operador'] ?? null),
            ':ip'     => api_ip(),
            ':ua'     => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'API'), 0, 250),
            ':al'     => $resumo['contagem']['liquidados_hoje'],
            ':am'     => $resumo['contagem']['manuais_hoje'],
            ':ai'     => $itensAtualizados,
            ':dl'     => $delNormais,
            ':dm'     => $delManuais,
            ':iu'     => $itensAtualizados,
            ':sis'    => api_sistema_id(),
            ':escopo' => $escopo,
            ':item'   => $itemId ?: null,
            ':motivo' => isset($opcoes['motivo']) ? mb_substr((string) $opcoes['motivo'], 0, 255) : null,
            ':detalhe'=> json_encode($detalhe, JSON_UNESCAPED_UNICODE),
        ]);

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockNome) . ")");
        throw $e;
    }

    $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockNome) . ")");

    return [
        'liberado'  => true,
        'os'        => $osId,
        'escopo'    => $escopo,
        'removidos' => [
            'atos_liquidados'         => $delNormais,
            'atos_manuais_liquidados' => $delManuais,
            'total'                   => $delNormais + $delManuais,
            'valor'                   => api_dinheiro(array_sum(array_column($detalhe, 'total'))),
        ],
        'itens_atualizados' => $itensAtualizados,
        'detalhe'           => $detalhe,
        'financeiro'        => api_os_saldo($osId),
    ];
}

/**
 * Resolve quais liquidações de hoje serão desfeitas.
 *
 * @param array $hoje liquidações de hoje (do resumo)
 */
function api_liberacao_alvos(int $osId, array $hoje, int $liquidacaoId, int $itemId): array
{
    $pdo = api_pdo();

    /* --- por liquidação: inequívoco --- */
    if ($liquidacaoId > 0) {
        foreach ($hoje as $l) {
            if ($l['liquidacao_id'] === $liquidacaoId) {
                $l['item_id'] = api_liberacao_item_da_liquidacao($osId, $l);

                /* Sem item de origem não dá para devolver a quantidade, e
                   apagar a liquidação deixaria o item preso em "liquidado".
                   Melhor recusar do que gravar inconsistência. */
                if (!$l['item_id']) {
                    api_erro(
                        'item_de_origem_nao_identificado',
                        'Não foi possível identificar o item de origem da liquidação ' . $liquidacaoId
                        . ' (ato ' . $l['ato'] . '). Use a liberação da O.S. inteira, sem informar '
                        . '"liquidacao_id".',
                        409,
                        ['os' => $osId, 'liquidacao_id' => $liquidacaoId, 'ato' => $l['ato']]
                    );
                }

                return [$l];
            }
        }
        api_erro(
            'liquidacao_nao_encontrada',
            'A liquidação ' . $liquidacaoId . ' não pertence a esta O.S., ou não foi feita hoje.',
            404,
            ['os' => $osId, 'liquidacao_id' => $liquidacaoId]
        );
    }

    /* --- por item: casa pelo código do ato --- */
    if ($itemId > 0) {
        $st = $pdo->prepare("SELECT * FROM ordens_de_servico_itens WHERE id = ? AND ordem_servico_id = ?");
        $st->execute([$itemId, $osId]);
        $item = $st->fetch();

        if (!$item) {
            api_erro('item_nao_encontrado', 'O item ' . $itemId . ' não pertence à O.S. ' . $osId . '.',
                     404, ['os' => $osId, 'item_id' => $itemId]);
        }

        /* Ambiguidade: dois itens com o mesmo ato na mesma O.S. */
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM ordens_de_servico_itens WHERE ordem_servico_id = ? AND ato = ?"
        );
        $st->execute([$osId, $item['ato']]);

        if (((int) $st->fetchColumn()) > 1) {
            api_erro(
                'item_ambiguo',
                'A O.S. tem mais de um item com o ato "' . $item['ato'] . '", e a tabela de liquidação '
                . 'não guarda o item de origem. Informe o "liquidacao_id" (veja GET /v1/os/'
                . $osId . '/liquidacoes) em vez do "item_id".',
                409,
                ['os' => $osId, 'item_id' => $itemId, 'ato' => $item['ato']]
            );
        }

        $alvos = [];
        foreach ($hoje as $l) {
            if (api_liberacao_mesmo_ato($l['ato'], $item['ato'])) {
                $l['item_id'] = $itemId;
                $alvos[] = $l;
            }
        }
        return $alvos;
    }

    /* --- O.S. inteira --- */
    foreach ($hoje as &$l) {
        $l['item_id'] = api_liberacao_item_da_liquidacao($osId, $l);
    }
    unset($l);

    return $hoje;
}

/** Compara códigos de ato ignorando o sufixo "(isento)". */
function api_liberacao_mesmo_ato($a, $b): bool
{
    $limpa = static fn($v) => trim(str_ireplace('(isento)', '', (string) $v));
    return strcasecmp($limpa($a), $limpa($b)) === 0;
}

/**
 * Descobre o item de origem de uma liquidação, em três passos.
 *
 * `atos_liquidados` não guarda o `item_id`, então o vínculo é
 * reconstruído. Sem isso, desfazer uma liquidação apagaria o ato mas
 * deixaria o item marcado como liquidado — inconsistência silenciosa
 * que só apareceria na próxima tentativa de selagem.
 *
 *   1. ato + descrição  — a descrição é copiada do item na liquidação,
 *                         então desambigua dois itens do mesmo ato;
 *   2. ato, entre os itens que ainda têm quantidade liquidada;
 *   3. ato, qualquer um.
 *
 * Devolve null só quando não existe item algum com aquele ato.
 */
function api_liberacao_item_da_liquidacao(int $osId, array $liq): ?int
{
    try {
        $pdo = api_pdo();
        $ato = trim(str_ireplace('(isento)', '', (string) $liq['ato']));

        /* 1. ato + descrição */
        $st = $pdo->prepare(
            "SELECT id FROM ordens_de_servico_itens
              WHERE ordem_servico_id = :os
                AND REPLACE(REPLACE(ato,' (isento)',''),'(isento)','') = :ato
                AND descricao = :desc
              ORDER BY (quantidade_liquidada > 0) DESC, id ASC"
        );
        $st->execute([':os' => $osId, ':ato' => $ato, ':desc' => (string) $liq['descricao']]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids) >= 1) {
            return (int) $ids[0];
        }

        /* 2 e 3. só pelo ato, preferindo quem ainda está liquidado */
        $st = $pdo->prepare(
            "SELECT id FROM ordens_de_servico_itens
              WHERE ordem_servico_id = :os
                AND REPLACE(REPLACE(ato,' (isento)',''),'(isento)','') = :ato
              ORDER BY (quantidade_liquidada > 0) DESC, id ASC"
        );
        $st->execute([':os' => $osId, ':ato' => $ato]);
        $id = $st->fetchColumn();

        return $id ? (int) $id : null;

    } catch (Throwable $e) {
        error_log('[api_liberacao_item] ' . $e->getMessage());
        return null;
    }
}
