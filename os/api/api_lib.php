<?php
/**
 * =====================================================================
 * api_lib.php — Regras de negócio da API do módulo O.S.
 * ---------------------------------------------------------------------
 * ATLAS-OS-API-BUILD: 2026-07-31-v1
 *
 * MODELO FINANCEIRO (idêntico ao de visualizar_os.php)
 * ---------------------------------------------------
 *   pago_liquido     = SUM(pagamento_os.total_pagamento)
 *                    - SUM(devolucao_os.total_devolucao)
 *   total_liquidado  = SUM(atos_liquidados.total)
 *                    + SUM(atos_manuais_liquidados.total)
 *   saldo_liquidacao = pago_liquido - total_liquidado
 *
 * "saldo_liquidacao" é o que responde à pergunta do sistema de selagem:
 * quanto ainda posso liquidar sem liquidar ato que o cliente não pagou.
 * Pagamentos e devoluções são apagados de fato (DELETE) pelo módulo, por
 * isso a soma não filtra status — é o mesmo critério da tela.
 *
 * RATEIO DA LIQUIDAÇÃO
 * --------------------
 * Os valores gravados em ordens_de_servico_itens já estão com o desconto
 * legal aplicado e arredondados como saem no orçamento. A liquidação
 * NUNCA recalcula pela tabela de emolumentos — deriva dos próprios
 * campos do item, com rateio cumulativo por quantidade. É o que evita a
 * diferença de 1 centavo corrigida em liquidar_ato.php, e o que garante
 * que liquidações parciais somem exatamente o total do item.
 * =====================================================================
 */

require_once __DIR__ . '/api_config.php';
require_once __DIR__ . '/api_auth.php';
require_once __DIR__ . '/../base_calculo_lib.php';

/* --------------------------------------------------------------------
 * Formatação
 * ------------------------------------------------------------------ */

function api_dinheiro($v): float
{
    return round((float) $v, 2);
}

function api_data($v): ?string
{
    if (empty($v) || $v === '0000-00-00 00:00:00') {
        return null;
    }
    $ts = strtotime((string) $v);
    return $ts ? date('c', $ts) : null;
}

/* --------------------------------------------------------------------
 * Ordem de Serviço
 * ------------------------------------------------------------------ */

function api_os_buscar(int $osId): array
{
    $st = api_pdo()->prepare("SELECT * FROM ordens_de_servico WHERE id = ?");
    $st->execute([$osId]);
    $os = $st->fetch();

    if (!$os) {
        api_erro('os_nao_encontrada', 'Ordem de Serviço ' . $osId . ' não localizada.', 404, ['os' => $osId]);
    }

    return $os;
}

function api_os_cancelada(array $os): bool
{
    return strcasecmp(trim((string) ($os['status'] ?? '')), 'Cancelado') === 0;
}

/**
 * Base de cálculo do NÍVEL DA O.S. — CAMPO LEGADO.
 *
 * Até julho/2026 havia uma única base por O.S. Isso não representava um
 * orçamento com duas escrituras de valores diferentes, e o sistema de
 * lavratura não tinha como saber a qual ato a base pertencia. Agora a
 * base é registrada NO ATO (ver `base_de_calculo` dentro de cada item).
 *
 * Este campo continua sendo devolvido, com o nome `base_de_calculo_os`,
 * apenas para as O.S. lançadas antes da mudança — elas têm a base só
 * aqui. Em O.S. novas ele vem NULL.
 *
 * NÃO use este campo para escolher a faixa do selo. Use o
 * `base_de_calculo` do item.
 *
 * @return float|null
 */
function api_base_calculo_os(array $os)
{
    $v = (float) ($os['base_de_calculo'] ?? 0);

    return $v >= 0.001 ? api_dinheiro($v) : null;
}

/* --------------------------------------------------------------------
 * Situação financeira
 * ------------------------------------------------------------------ */

/**
 * @return array Posição financeira completa da O.S.
 */
function api_os_saldo(int $osId, ?array $os = null): array
{
    $pdo = api_pdo();
    $os  = $os ?: api_os_buscar($osId);

    $soma = static function (string $sql) use ($pdo, $osId): float {
        try {
            $st = $pdo->prepare($sql);
            $st->execute([$osId]);
            return (float) ($st->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('[api_os_saldo] ' . $e->getMessage());
            return 0.0;
        }
    };

    $pago = $soma("SELECT COALESCE(SUM(total_pagamento),0) FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $dev  = $soma("SELECT COALESCE(SUM(total_devolucao),0) FROM devolucao_os WHERE ordem_de_servico_id = ?");
    $liqN = $soma("SELECT COALESCE(SUM(total),0) FROM atos_liquidados WHERE ordem_servico_id = ?");
    $liqM = $soma("SELECT COALESCE(SUM(total),0) FROM atos_manuais_liquidados WHERE ordem_servico_id = ?");

    $pagoLiquido    = api_dinheiro($pago - $dev);
    $totalLiquidado = api_dinheiro($liqN + $liqM);
    $totalOs        = api_dinheiro($os['total_os'] ?? 0);

    /* O.S. marcada como isenta: existe pagamento com forma de isenção.
       Nesse caso a liquidação não exige saldo. */
    $isenta = false;
    try {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM pagamento_os
              WHERE ordem_de_servico_id = ?
                AND (forma_de_pagamento = 'Isento de Pagamento' OR forma_de_pagamento = 'Ato Isento')"
        );
        $st->execute([$osId]);
        $isenta = ((int) $st->fetchColumn()) > 0;
    } catch (Throwable $e) {
        error_log('[api_os_saldo/isenta] ' . $e->getMessage());
    }

    return [
        'total_os'            => $totalOs,
        'total_pago'          => api_dinheiro($pago),
        'total_devolvido'     => api_dinheiro($dev),
        'pago_liquido'        => $pagoLiquido,
        'total_liquidado'     => $totalLiquidado,
        'saldo_liquidacao'    => api_dinheiro($pagoLiquido - $totalLiquidado),
        'saldo_a_pagar'       => api_dinheiro(max(0, $totalOs - $pagoLiquido)),
        'quitada'             => ($pagoLiquido + 0.001) >= $totalOs,
        'isenta_de_pagamento' => $isenta,
    ];
}

/* --------------------------------------------------------------------
 * Itens
 * ------------------------------------------------------------------ */

function api_item_isento(array $item): bool
{
    return stripos((string) $item['ato'], '(isento)') !== false;
}

/**
 * Tabela de destino da liquidação, no mesmo critério de liquidar_ato.php.
 */
function api_tabela_liquidacao(array $item): string
{
    $ato = trim((string) $item['ato']);

    if (!api_item_isento($item) && $ato !== '' && !in_array($ato, ['0', '00', '9999', 'ISS'], true)) {
        return 'atos_liquidados';
    }

    return 'atos_manuais_liquidados';
}

/**
 * Rateio cumulativo por quantidade — mesma fórmula de liquidar_ato.php.
 */
function api_rateio(float $valorTotalItem, float $qtdTotal, float $qtdJaLiquidada, float $qtdNova): float
{
    if ($qtdTotal <= 0) {
        return round($valorTotalItem, 2);
    }

    $acumuladoAgora = round($valorTotalItem * $qtdNova / $qtdTotal, 2);
    $jaLiquidado    = round($valorTotalItem * $qtdJaLiquidada / $qtdTotal, 2);

    return round($acumuladoAgora - $jaLiquidado, 2);
}

/**
 * Valor de uma liquidação de N unidades do item, sem executá-la.
 */
function api_valor_liquidacao(array $item, int $quantidade): array
{
    $qtdTotal = (float) $item['quantidade'];
    $qtdJa    = (float) $item['quantidade_liquidada'];
    $qtdNova  = $qtdJa + $quantidade;

    $r = static fn($campo) => api_rateio((float) ($item[$campo] ?? 0), $qtdTotal, $qtdJa, $qtdNova);

    return [
        'emolumentos' => $r('emolumentos'),
        'ferc'        => $r('ferc'),
        'fadep'       => $r('fadep'),
        'femp'        => $r('femp'),
        'ferrfis'     => $r('ferrfis'),
        'total'       => $r('total'),
    ];
}

/**
 * Itens da O.S. com a situação de liquidação de cada um.
 */
function api_os_itens(int $osId): array
{
    $st = api_pdo()->prepare(
        "SELECT * FROM ordens_de_servico_itens
          WHERE ordem_servico_id = ?
          ORDER BY COALESCE(ordem_exibicao, id), id"
    );
    $st->execute([$osId]);

    $itens = [];
    foreach ($st->fetchAll() as $i) {
        $qtd  = (int) $i['quantidade'];
        $liq  = (int) $i['quantidade_liquidada'];
        $rest = max(0, $qtd - $liq);

        $situacao = $rest === 0 ? 'liquidado' : ($liq > 0 ? 'parcialmente_liquidado' : 'pendente');

        /* Valor de uma unidade ainda pendente — é o que o sistema de
           selagem precisa para saber se cabe no saldo. */
        $valorRestante = $rest > 0 ? api_valor_liquidacao($i, $rest)['total'] : 0.0;
        $valorUnitario = $rest > 0 ? api_valor_liquidacao($i, 1)['total'] : 0.0;

        /* Faixa de valor declarado lida da descrição do ato. Quando existe,
           o selo é escolhido por ela e a base é obrigatória. */
        $faixa = bc_extrair_faixa($i['descricao']);

        $itens[] = [
            'item_id'               => (int) $i['id'],
            'ato'                   => $i['ato'],
            'descricao'             => $i['descricao'],
            'isento'                => api_item_isento($i),
            'base_de_calculo'       => bc_base_item($i),
            'exige_base_de_calculo' => $faixa !== null,
            'faixa_de_valor'        => $faixa ? [
                'tipo'   => $faixa['tipo'],
                'minimo' => $faixa['minimo'],
                'maximo' => $faixa['maximo'],
                'rotulo' => $faixa['rotulo'],
            ] : null,
            'desconto_legal'        => (float) $i['desconto_legal'],
            'quantidade'            => $qtd,
            'quantidade_liquidada'  => $liq,
            'quantidade_disponivel' => $rest,
            'situacao'              => $situacao,
            'valores_do_item'       => [
                'emolumentos' => api_dinheiro($i['emolumentos']),
                'ferc'        => api_dinheiro($i['ferc']),
                'fadep'       => api_dinheiro($i['fadep']),
                'femp'        => api_dinheiro($i['femp']),
                'ferrfis'     => api_dinheiro($i['ferrfis'] ?? 0),
                'total'       => api_dinheiro($i['total']),
            ],
            'valor_unitario_liquidacao' => $valorUnitario,
            'valor_restante_liquidacao' => $valorRestante,
        ];
    }

    return $itens;
}

/**
 * Atos já liquidados (as duas tabelas, unificadas).
 */
function api_os_liquidacoes(int $osId): array
{
    $pdo = api_pdo();
    $out = [];

    foreach (['atos_liquidados', 'atos_manuais_liquidados'] as $tabela) {
        try {
            $st = $pdo->prepare("SELECT * FROM `$tabela` WHERE ordem_servico_id = ? ORDER BY id");
            $st->execute([$osId]);
        } catch (Throwable $e) {
            error_log('[api_os_liquidacoes] ' . $e->getMessage());
            continue;
        }

        foreach ($st->fetchAll() as $r) {
            $out[] = [
                'liquidacao_id'        => (int) $r['id'],
                'tabela'               => $tabela,
                'ato'                  => $r['ato'],
                'descricao'            => $r['descricao'],
                'quantidade_liquidada' => (int) $r['quantidade_liquidada'],
                'desconto_legal'       => (float) ($r['desconto_legal'] ?? 0),
                'base_de_calculo'      => bc_base_item($r),
                'emolumentos'          => api_dinheiro($r['emolumentos']),
                'ferc'                 => api_dinheiro($r['ferc']),
                'fadep'                => api_dinheiro($r['fadep']),
                'femp'                 => api_dinheiro($r['femp']),
                'ferrfis'              => api_dinheiro($r['ferrfis'] ?? 0),
                'total'                => api_dinheiro($r['total']),
                'funcionario'          => $r['funcionario'] ?? null,
                'status'               => $r['status'] ?? null,
                'data_liquidacao'      => api_data($r['data_liquidacao'] ?? $r['data'] ?? null),
            ];
        }
    }

    usort($out, static fn($a, $b) => strcmp((string) $a['data_liquidacao'], (string) $b['data_liquidacao']));

    return $out;
}

function api_os_pagamentos(int $osId): array
{
    $st = api_pdo()->prepare(
        "SELECT * FROM pagamento_os WHERE ordem_de_servico_id = ? ORDER BY id"
    );
    $st->execute([$osId]);

    $out = [];
    foreach ($st->fetchAll() as $p) {
        $out[] = [
            'pagamento_id'       => (int) $p['id'],
            'valor'              => api_dinheiro($p['total_pagamento']),
            'forma_de_pagamento' => $p['forma_de_pagamento'],
            'funcionario'        => $p['funcionario'] ?? null,
            'status'             => $p['status'] ?? null,
            'data_pagamento'     => api_data($p['data_pagamento'] ?? null),
        ];
    }

    return $out;
}

/**
 * Selos registrados na liquidação via API.
 */
function api_os_selos(int $osId): array
{
    try {
        $st = api_pdo()->prepare("SELECT * FROM api_selos WHERE os_id = ? ORDER BY id");
        $st->execute([$osId]);
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    foreach ($st->fetchAll() as $s) {
        $out[] = [
            'selo'          => $s['selo'],
            'ato'           => $s['ato'],
            'item_id'       => $s['item_id'] !== null ? (int) $s['item_id'] : null,
            'liquidacao_id' => $s['liquidacao_id'] !== null ? (int) $s['liquidacao_id'] : null,
            'quantidade'    => (int) $s['quantidade'],
            'protocolo'     => $s['protocolo'],
            'registrado_em' => api_data($s['criado_em']),
        ];
    }

    return $out;
}

/* --------------------------------------------------------------------
 * Retrato completo da O.S.
 * ------------------------------------------------------------------ */

function api_os_retrato(int $osId, bool $completo = true): array
{
    $os    = api_os_buscar($osId);
    $saldo = api_os_saldo($osId, $os);
    $itens = api_os_itens($osId);

    $pendentes = array_values(array_filter($itens, static fn($i) => $i['quantidade_disponivel'] > 0));

    $dados = [
        'os' => [
            'numero'          => (int) $os['id'],
            'cliente'         => $os['cliente'],
            'cpf_cliente'     => $os['cpf_cliente'],
            'descricao'       => $os['descricao_os'],
            'observacoes'     => $os['observacoes'],
            'status'          => $os['status'] ?? null,
            'cancelada'       => api_os_cancelada($os),
            'criado_por'      => $os['criado_por'] ?? null,
            'data_criacao'    => api_data($os['data_criacao'] ?? null),
            'total_os'        => api_dinheiro($os['total_os']),
            /* LEGADO: base única da O.S., só preenchida nas O.S. antigas.
               A base que vale para o selo está em cada item, no campo
               `base_de_calculo` de `itens`. */
            'base_de_calculo_os' => api_base_calculo_os($os),
        ],
        'financeiro' => $saldo,
        'resumo_atos' => [
            'total_de_itens'        => count($itens),
            'itens_pendentes'       => count($pendentes),
            'unidades_pendentes'    => array_sum(array_column($pendentes, 'quantidade_disponivel')),
            'totalmente_liquidada'  => count($pendentes) === 0 && count($itens) > 0,
        ],
    ];

    if ($completo) {
        $dados['itens']        = $itens;
        $dados['liquidacoes']  = api_os_liquidacoes($osId);
        $dados['pagamentos']   = api_os_pagamentos($osId);
        $dados['selos']        = api_os_selos($osId);
    }

    return $dados;
}

/* --------------------------------------------------------------------
 * Verificação de saldo (consulta prévia à selagem)
 * --------------------------------------------------------------------
 * Resposta advisory: o veredito final é dado dentro da trava, no momento
 * da liquidação. Serve para o sistema de selagem decidir se prossegue.
 * ------------------------------------------------------------------ */

function api_verificar_liquidacao(int $osId, int $itemId, int $quantidade): array
{
    $os = api_os_buscar($osId);

    $st = api_pdo()->prepare("SELECT * FROM ordens_de_servico_itens WHERE id = ? AND ordem_servico_id = ?");
    $st->execute([$itemId, $osId]);
    $item = $st->fetch();

    if (!$item) {
        api_erro(
            'item_nao_encontrado',
            'O item ' . $itemId . ' não pertence à O.S. ' . $osId . '.',
            404,
            ['os' => $osId, 'item_id' => $itemId]
        );
    }

    $impedimentos = [];

    if (api_os_cancelada($os)) {
        $impedimentos[] = ['codigo' => 'os_cancelada', 'mensagem' => 'A O.S. está cancelada.'];
    }

    /* Ato de faixa de valor sem base informada não pode ser selado: o
       selo é escolhido pela faixa, e sem a base não há como escolher. */
    $faixaItem = bc_extrair_faixa($item['descricao']);
    $baseItem  = bc_base_item($item);

    if ($faixaItem && !api_item_isento($item)) {
        $vb = bc_validar((float) $baseItem, $faixaItem);
        if (!$vb['ok']) {
            $impedimentos[] = ['codigo' => $vb['codigo'], 'mensagem' => $vb['mensagem']];
        }
    }

    $disponivel = max(0, (int) $item['quantidade'] - (int) $item['quantidade_liquidada']);

    if ($quantidade < 1) {
        $impedimentos[] = ['codigo' => 'quantidade_invalida', 'mensagem' => 'A quantidade deve ser pelo menos 1.'];
    } elseif ($quantidade > $disponivel) {
        $impedimentos[] = [
            'codigo'   => 'quantidade_indisponivel',
            'mensagem' => $disponivel === 0
                ? 'Este ato já está totalmente liquidado.'
                : 'Restam apenas ' . $disponivel . ' unidade(s) a liquidar neste item.',
        ];
    }

    /* Quantidade viável para efeito de cálculo do valor. */
    $qtdCalculo = ($quantidade >= 1 && $quantidade <= $disponivel) ? $quantidade : max(1, min($quantidade, max(1, $disponivel)));

    $valores = api_valor_liquidacao($item, $qtdCalculo);
    $saldo   = api_os_saldo($osId, $os);
    $isento  = api_item_isento($item) || api_dinheiro($valores['total']) <= 0;

    /* A regra central: sem saldo pago, não se liquida ato oneroso. */
    $exigeSaldo = !$isento && !$saldo['isenta_de_pagamento'];
    $temSaldo   = !$exigeSaldo || ($saldo['saldo_liquidacao'] + 0.001) >= $valores['total'];
    $falta      = $temSaldo ? 0.0 : api_dinheiro($valores['total'] - $saldo['saldo_liquidacao']);

    /* Só faz sentido falar em saldo quando a quantidade pedida é válida —
       senão o ato já liquidado apareceria também como "sem saldo". */
    if (!$temSaldo && !$impedimentos) {
        $impedimentos[] = [
            'codigo'   => 'saldo_insuficiente',
            'mensagem' => 'Saldo pago insuficiente para liquidar este ato. Faltam R$ '
                        . number_format($falta, 2, ',', '.') . '.',
        ];
    }

    return [
        'pode_liquidar' => count($impedimentos) === 0,
        'impedimentos'  => $impedimentos,
        'os'            => (int) $osId,
        /* Base DO ATO que está sendo verificado — é a que define a faixa
           do selo. A base antiga, de nível de O.S., vem em
           `base_de_calculo_os` e só existe nas O.S. anteriores. */
        'base_de_calculo'    => $baseItem,
        'base_de_calculo_os' => api_base_calculo_os($os),
        'item' => [
            'item_id'               => (int) $item['id'],
            'ato'                   => $item['ato'],
            'descricao'             => $item['descricao'],
            'isento'                => api_item_isento($item),
            'desconto_legal'        => (float) $item['desconto_legal'],
            'base_de_calculo'       => $baseItem,
            'exige_base_de_calculo' => $faixaItem !== null,
            'faixa_de_valor'        => $faixaItem ? [
                'tipo'   => $faixaItem['tipo'],
                'minimo' => $faixaItem['minimo'],
                'maximo' => $faixaItem['maximo'],
                'rotulo' => $faixaItem['rotulo'],
            ] : null,
            'quantidade'            => (int) $item['quantidade'],
            'quantidade_liquidada'  => (int) $item['quantidade_liquidada'],
            'quantidade_disponivel' => $disponivel,
        ],
        'quantidade_solicitada' => $quantidade,
        'valor_da_liquidacao'   => $valores,
        'financeiro'            => $saldo,
        'exige_saldo'           => $exigeSaldo,
        'saldo_suficiente'      => $temSaldo,
        'falta'                 => $falta,
    ];
}

/* --------------------------------------------------------------------
 * Liquidação
 * ------------------------------------------------------------------ */

/**
 * Liquida N unidades de um item. Atômica e com trava por O.S.
 *
 * A verificação de saldo é refeita DENTRO da trava — a consulta prévia
 * é orientativa, esta é a que vale. Sem isso, duas selagens simultâneas
 * poderiam liquidar mais do que o cliente pagou.
 */
function api_liquidar(int $osId, int $itemId, int $quantidade, array $opcoes = []): array
{
    $pdo      = api_pdo();
    $lockNome = 'atlas_os_api_liq_' . $osId;

    $obteve = (int) $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockNome) . ", 10)")->fetchColumn();
    if ($obteve !== 1) {
        api_erro(
            'os_ocupada',
            'Há outra liquidação em andamento para esta O.S. Tente novamente em instantes.',
            409,
            ['os' => $osId]
        );
    }

    try {
        $ver = api_verificar_liquidacao($osId, $itemId, $quantidade);

        if (!$ver['pode_liquidar']) {
            $primeiro = $ver['impedimentos'][0];

            /* O erro de saldo carrega os números, para o sistema de
               selagem mostrar ao operador o que falta receber. */
            if ($primeiro['codigo'] === 'saldo_insuficiente') {
                api_erro('saldo_insuficiente', $primeiro['mensagem'], 409, [
                    'os'                  => $osId,
                    'item_id'             => $itemId,
                    'valor_necessario'    => $ver['valor_da_liquidacao']['total'],
                    'saldo_disponivel'    => $ver['financeiro']['saldo_liquidacao'],
                    'falta'               => $ver['falta'],
                    'total_os'            => $ver['financeiro']['total_os'],
                    'pago_liquido'        => $ver['financeiro']['pago_liquido'],
                    'total_liquidado'     => $ver['financeiro']['total_liquidado'],
                ]);
            }

            /* Base ausente ou fora da faixa: devolve a faixa esperada, para o
               sistema de selagem mostrar ao operador exatamente o que falta. */
            if (in_array($primeiro['codigo'], ['base_obrigatoria', 'base_fora_da_faixa'], true)) {
                api_erro($primeiro['codigo'], $primeiro['mensagem'], 422, [
                    'os'              => $osId,
                    'item_id'         => $itemId,
                    'ato'             => $ver['item']['ato'],
                    'base_de_calculo' => $ver['item']['base_de_calculo'],
                    'faixa_de_valor'  => $ver['item']['faixa_de_valor'],
                ]);
            }

            $http = in_array($primeiro['codigo'], ['quantidade_indisponivel', 'os_cancelada'], true) ? 409 : 422;
            api_erro($primeiro['codigo'], $primeiro['mensagem'], $http, [
                'os'           => $osId,
                'item_id'      => $itemId,
                'impedimentos' => $ver['impedimentos'],
            ]);
        }

        $st = $pdo->prepare("SELECT * FROM ordens_de_servico_itens WHERE id = ? AND ordem_servico_id = ?");
        $st->execute([$itemId, $osId]);
        $item = $st->fetch();

        $novaQtdLiquidada = (int) $item['quantidade_liquidada'] + $quantidade;
        $statusItem = ($novaQtdLiquidada >= (int) $item['quantidade']) ? 'liquidado' : 'parcialmente liquidado';

        $v      = api_valor_liquidacao($item, $quantidade);
        $tabela = api_tabela_liquidacao($item);
        $oper   = api_operador($opcoes['operador'] ?? null);

        $pdo->beginTransaction();

        /* A base fica registrada JUNTO do ato liquidado. É o dado que
           sustenta o selo emitido: se alguém editar a O.S. depois, a base
           do que já foi selado não muda. */
        bc_migrar($pdo);

        $ins = $pdo->prepare(
            "INSERT INTO `$tabela`
             (ordem_servico_id, ato, quantidade_liquidada, desconto_legal, descricao,
              emolumentos, ferc, fadep, femp, ferrfis, total, funcionario, status, base_de_calculo)
             VALUES (:os, :ato, :qtd, :desc_legal, :descricao,
                     :emol, :ferc, :fadep, :femp, :ferrfis, :total, :func, :st, :base_item)"
        );
        $ins->execute([
            ':os'         => $osId,
            ':ato'        => $item['ato'],
            ':qtd'        => $quantidade,
            ':desc_legal' => $item['desconto_legal'],
            ':descricao'  => $item['descricao'],
            ':emol'       => $v['emolumentos'],
            ':ferc'       => $v['ferc'],
            ':fadep'      => $v['fadep'],
            ':femp'       => $v['femp'],
            ':ferrfis'    => $v['ferrfis'],
            ':total'      => $v['total'],
            ':func'       => $oper,
            ':st'         => $statusItem,
            ':base_item'  => bc_base_item($item),
        ]);
        $liquidacaoId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            "UPDATE ordens_de_servico_itens
                SET quantidade_liquidada = :q, status = :st
              WHERE id = :id"
        )->execute([':q' => $novaQtdLiquidada, ':st' => $statusItem, ':id' => $itemId]);

        /* Selo informado pelo sistema de lavratura. */
        $selo = trim((string) ($opcoes['selo'] ?? ''));
        if ($selo !== '') {
            $pdo->prepare(
                "INSERT INTO api_selos
                 (os_id, item_id, liquidacao_id, tabela_origem, ato, selo, quantidade, sistema_id, protocolo, criado_em)
                 VALUES (:os, :item, :liq, :tab, :ato, :selo, :qtd, :sis, :proto, NOW())"
            )->execute([
                ':os'    => $osId,
                ':item'  => $itemId,
                ':liq'   => $liquidacaoId,
                ':tab'   => $tabela,
                ':ato'   => $item['ato'],
                ':selo'  => mb_substr($selo, 0, 80),
                ':qtd'   => $quantidade,
                ':sis'   => api_sistema_id(),
                ':proto' => isset($opcoes['protocolo']) ? mb_substr((string) $opcoes['protocolo'], 0, 80) : null,
            ]);
        }

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockNome) . ")");
        throw $e;
    }

    $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockNome) . ")");

    /* Efeitos colaterais do módulo, no mesmo padrão de liquidar_ato.php:
       best-effort, nunca derrubam a liquidação já confirmada. */
    api_pos_liquidacao($osId);

    $saldo = api_os_saldo($osId);

    return [
        'liquidado'     => true,
        'os'            => $osId,
        'liquidacao_id' => $liquidacaoId,
        'tabela'        => $tabela,
        /* Base DO ATO liquidado — a mesma que ficou gravada junto do ato. */
        'base_de_calculo'    => bc_base_item($item),
        'base_de_calculo_os' => api_base_calculo_os(api_os_buscar($osId)),
        'item' => [
            'item_id'               => $itemId,
            'ato'                   => $item['ato'],
            'descricao'             => $item['descricao'],
            'desconto_legal'        => (float) $item['desconto_legal'],
            'base_de_calculo'       => bc_base_item($item),
            'quantidade'            => (int) $item['quantidade'],
            'quantidade_liquidada'  => $novaQtdLiquidada,
            'quantidade_disponivel' => max(0, (int) $item['quantidade'] - $novaQtdLiquidada),
            'situacao'              => $statusItem === 'liquidado' ? 'liquidado' : 'parcialmente_liquidado',
        ],
        'quantidade_liquidada_agora' => $quantidade,
        'valores'                    => $v,
        'selo'                       => $selo !== '' ? $selo : null,
        'financeiro'                 => $saldo,
    ];
}

/**
 * Rastreio e NFS-e após liquidar — espelha liquidar_ato.php.
 */
function api_pos_liquidacao(int $osId): void
{
    try {
        require_once __DIR__ . '/../../pedidos_certidao/os_rastreio_lib.php';
        if (function_exists('os_rastreio_sync_liquidacao')) {
            os_rastreio_sync_liquidacao(os_rastreio_pdo(), $osId, api_operador());
        }
    } catch (Throwable $e) {
        error_log('[api][rastreio] ' . $e->getMessage());
    }

    try {
        if (PHP_VERSION_ID < 80100) {
            throw new Exception('PHP < 8.1: NFS-e desabilitada.');
        }
        require_once __DIR__ . '/../nfse/nfse_lib.php';
        if (function_exists('nfse_hook_pos_liquidacao')) {
            nfse_hook_pos_liquidacao($osId);
        }
    } catch (Throwable $e) {
        error_log('[api][nfse] ' . $e->getMessage());
    }
}

/* --------------------------------------------------------------------
 * Pagamentos
 * ------------------------------------------------------------------ */

function api_formas_pagamento(): array
{
    return ['Espécie', 'PIX', 'Débito', 'Crédito', 'Transferência Bancária',
            'Depósito Bancário', 'Ato Isento', 'Isento de Pagamento'];
}

function api_pagamento_criar(int $osId, float $valor, string $forma, array $opcoes = []): array
{
    $os = api_os_buscar($osId);

    if (api_os_cancelada($os)) {
        api_erro('os_cancelada', 'Não é possível lançar pagamento em O.S. cancelada.', 409, ['os' => $osId]);
    }

    $isencao = in_array($forma, ['Ato Isento', 'Isento de Pagamento'], true);

    if (!$isencao && $valor <= 0) {
        api_erro('valor_invalido', 'O valor do pagamento deve ser maior que zero.', 422, ['valor' => $valor]);
    }

    if (!in_array($forma, api_formas_pagamento(), true)) {
        api_erro(
            'forma_pagamento_invalida',
            'Forma de pagamento não reconhecida.',
            422,
            ['formas_aceitas' => api_formas_pagamento()]
        );
    }

    $pdo  = api_pdo();
    $oper = api_operador($opcoes['operador'] ?? null);

    $st = $pdo->prepare(
        "INSERT INTO pagamento_os
         (ordem_de_servico_id, cliente, total_os, total_pagamento, forma_de_pagamento,
          data_pagamento, funcionario, status)
         VALUES (:os, :cli, :tot, :vlr, :forma, NOW(), :func, 'pago')"
    );
    $st->execute([
        ':os'    => $osId,
        ':cli'   => $os['cliente'],
        ':tot'   => $os['total_os'],
        ':vlr'   => api_dinheiro($valor),
        ':forma' => $forma,
        ':func'  => $oper,
    ]);

    $pagamentoId = (int) $pdo->lastInsertId();
    $saldo = api_os_saldo($osId);

    return [
        'pagamento_id'       => $pagamentoId,
        'os'                 => $osId,
        'valor'              => api_dinheiro($valor),
        'forma_de_pagamento' => $forma,
        'funcionario'        => $oper,
        'financeiro'         => $saldo,
    ];
}

/* --------------------------------------------------------------------
 * Criação de O.S.
 * --------------------------------------------------------------------
 * Os valores NUNCA vêm do cliente: o servidor busca o ato na tabela de
 * emolumentos vigente e aplica quantidade e desconto legal. Aceitar
 * valores prontos abriria caminho para lançar ato por preço arbitrário.
 * ------------------------------------------------------------------ */

function api_ato_buscar(string $ato): ?array
{
    $st = api_pdo()->prepare(
        "SELECT ATO, DESCRICAO, EMOLUMENTOS, FERC, FADEP, FEMP, FERRFIS, TOTAL
           FROM tabela_emolumentos WHERE ATO = ?"
    );
    $st->execute([$ato]);
    $r = $st->fetch();

    return $r ?: null;
}

/**
 * Calcula um item a partir do ato, quantidade e desconto legal.
 * Reproduz o cálculo do criar_os.php: valor unitário x quantidade x
 * (1 - desconto), arredondado a 2 casas em cada componente.
 */
function api_montar_item(array $entrada, int $ordem): array
{
    $quantidade = (int) api_valor($entrada['quantidade'] ?? 1);
    if ($quantidade < 1) {
        api_erro('quantidade_invalida', 'A quantidade de cada item deve ser pelo menos 1.', 422);
    }

    $desconto = (float) api_valor($entrada['desconto_legal'] ?? 0);
    if ($desconto < 0 || $desconto > 100) {
        api_erro('desconto_invalido', 'O desconto legal deve estar entre 0 e 100.', 422);
    }

    $isento = !empty($entrada['isento']);
    $ato    = trim((string) ($entrada['ato'] ?? ''));

    /* Base de cálculo DO ITEM. Aceita os dois nomes, como na O.S. */
    $baseBruta = $entrada['base_de_calculo'] ?? $entrada['base_calculo'] ?? null;
    $baseItem  = ($baseBruta === null || $baseBruta === '') ? null : api_dinheiro(api_valor($baseBruta));

    if ($baseItem !== null && $baseItem < 0) {
        api_erro('base_calculo_invalida', 'A base de cálculo do item não pode ser negativa.', 422,
                 ['ato' => $ato, 'recebido' => $baseBruta]);
    }

    /* Item manual: sem código de ato, valores informados explicitamente. */
    if ($ato === '' || $ato === '0') {
        $descricao = trim((string) ($entrada['descricao'] ?? ''));
        if ($descricao === '') {
            api_erro('campo_obrigatorio', 'Item manual exige "descricao".', 422);
        }

        $vals = is_array($entrada['valores'] ?? null) ? $entrada['valores'] : [];
        $emol = api_dinheiro(api_valor($vals['emolumentos'] ?? 0));
        $ferc = api_dinheiro(api_valor($vals['ferc'] ?? 0));
        $fade = api_dinheiro(api_valor($vals['fadep'] ?? 0));
        $femp = api_dinheiro(api_valor($vals['femp'] ?? 0));
        $ferr = api_dinheiro(api_valor($vals['ferrfis'] ?? 0));

        if ($isento) {
            $emol = $ferc = $fade = $femp = $ferr = 0.0;
        }

        return [
            'ato'            => $isento ? '0 (isento)' : '0',
            'descricao'      => mb_strtoupper($descricao, 'UTF-8'),
            'quantidade'     => $quantidade,
            'desconto_legal' => $desconto,
            'base_de_calculo'=> $baseItem,
            'emolumentos'    => $emol,
            'ferc'           => $ferc,
            'fadep'          => $fade,
            'femp'           => $femp,
            'ferrfis'        => $ferr,
            'total'          => api_dinheiro($emol + $ferc + $fade + $femp + $ferr),
            'ordem_exibicao' => $ordem,
        ];
    }

    $tab = api_ato_buscar($ato);
    if (!$tab) {
        api_erro(
            'ato_nao_encontrado',
            'O ato "' . $ato . '" não consta na tabela de emolumentos vigente.',
            422,
            ['ato' => $ato]
        );
    }

    $fator = $quantidade * (1 - $desconto / 100);

    $emol = $isento ? 0.0 : api_dinheiro((float) $tab['EMOLUMENTOS'] * $fator);
    $ferc = $isento ? 0.0 : api_dinheiro((float) $tab['FERC'] * $fator);
    $fade = $isento ? 0.0 : api_dinheiro((float) $tab['FADEP'] * $fator);
    $femp = $isento ? 0.0 : api_dinheiro((float) $tab['FEMP'] * $fator);
    $ferr = $isento ? 0.0 : api_dinheiro((float) ($tab['FERRFIS'] ?? 0) * $fator);

    $descricao = trim((string) ($entrada['descricao'] ?? '')) ?: (string) $tab['DESCRICAO'];

    /* Ato cobrado por faixa de valor declarado: a base é obrigatória e
       precisa cair dentro da faixa. Sem isso o sistema de lavratura não
       tem como escolher o selo — e um selo de faixa errada é ato viciado. */
    $faixa = bc_extrair_faixa($tab['DESCRICAO']);
    if ($faixa && !$isento) {
        $v = bc_validar((float) $baseItem, $faixa);
        if (!$v['ok']) {
            api_erro($v['codigo'], $v['mensagem'], 422, [
                'ato'            => $ato,
                'descricao'      => $tab['DESCRICAO'],
                'faixa_de_valor' => [
                    'tipo'   => $faixa['tipo'],
                    'minimo' => $faixa['minimo'],
                    'maximo' => $faixa['maximo'],
                    'rotulo' => $faixa['rotulo'],
                ],
                'base_recebida'  => $baseItem,
            ]);
        }
    }

    return [
        'ato'            => $isento ? $ato . ' (isento)' : $ato,
        'descricao'      => $descricao,
        'quantidade'     => $quantidade,
        'desconto_legal' => $desconto,
        'base_de_calculo'=> $baseItem,
        'emolumentos'    => $emol,
        'ferc'           => $ferc,
        'fadep'          => $fade,
        'femp'           => $femp,
        'ferrfis'        => $ferr,
        'total'          => api_dinheiro($emol + $ferc + $fade + $femp + $ferr),
        'ordem_exibicao' => $ordem,
    ];
}

function api_os_criar(array $corpo): array
{
    $clienteRaw = (string) api_exigir($corpo, 'cliente');
    $cliente    = mb_strtoupper(trim(preg_replace('/["\'“”‘’]/u', '', $clienteRaw)), 'UTF-8');

    if ($cliente === '') {
        api_erro('campo_obrigatorio', 'O campo "cliente" (apresentante) é obrigatório.', 422, ['campo' => 'cliente']);
    }

    $itensEntrada = api_exigir($corpo, 'itens');
    if (!is_array($itensEntrada) || !$itensEntrada) {
        api_erro('itens_invalidos', 'Envie ao menos um item em "itens".', 422);
    }
    if (count($itensEntrada) > 200) {
        api_erro('itens_invalidos', 'Limite de 200 itens por O.S.', 422);
    }

    /* ---------------------------------------------------------------- *
     * Base de cálculo — valor declarado do negócio jurídico.
     * Aceita 'base_de_calculo' (nome da coluna) ou 'base_calculo' (nome
     * do campo na tela), para o integrador não errar por nomenclatura.
     * Ausente ou vazia grava 0,00, exatamente como o salvar_os.php.
     * ---------------------------------------------------------------- */
    $baseBruta = $corpo['base_de_calculo'] ?? $corpo['base_calculo'] ?? null;
    $base = ($baseBruta === null || $baseBruta === '') ? 0.0 : api_dinheiro(api_valor($baseBruta));

    if ($base < 0) {
        api_erro(
            'base_calculo_invalida',
            'A base de cálculo não pode ser negativa.',
            422,
            ['campo' => 'base_de_calculo', 'recebido' => $baseBruta]
        );
    }

    $itens = [];
    $total = 0.0;
    foreach (array_values($itensEntrada) as $ordem => $entrada) {
        if (!is_array($entrada)) {
            api_erro('itens_invalidos', 'Cada item deve ser um objeto.', 422);
        }
        $item = api_montar_item($entrada, $ordem + 1);
        $total += $item['total'];
        $itens[] = $item;
    }
    $total = api_dinheiro($total);

    $pdo = api_pdo();

    /* Coluna ferrfis pode faltar em instalações antigas (mesmo tratamento
       de salvar_os.php). */
    try {
        if ($pdo->query("SHOW COLUMNS FROM ordens_de_servico_itens LIKE 'ferrfis'")->rowCount() === 0) {
            $pdo->exec("ALTER TABLE ordens_de_servico_itens ADD COLUMN ferrfis DECIMAL(10,2) DEFAULT 0.00 AFTER femp");
        }
    } catch (Throwable $e) {
        error_log('[api_os_criar/ferrfis] ' . $e->getMessage());
    }

    /* Coluna da base por item (idempotente). */
    bc_migrar($pdo);

    $pdo->beginTransaction();

    try {
        $stOs = $pdo->prepare(
            "INSERT INTO ordens_de_servico
             (cliente, cpf_cliente, total_os, descricao_os, observacoes, criado_por, base_de_calculo)
             VALUES (:cli, :cpf, :tot, :desc, :obs, :por, :base)"
        );
        $stOs->execute([
            ':cli'  => $cliente,
            ':cpf'  => api_so_digitos($corpo['cpf_cliente'] ?? ''),
            ':tot'  => $total,
            ':desc' => (string) ($corpo['descricao'] ?? $corpo['descricao_os'] ?? ''),
            ':obs'  => (string) ($corpo['observacoes'] ?? ''),
            ':por'  => api_operador($corpo['operador'] ?? null),
            ':base' => $base,
        ]);

        $osId = (int) $pdo->lastInsertId();

        $stIt = $pdo->prepare(
            "INSERT INTO ordens_de_servico_itens
             (ordem_servico_id, ato, quantidade, desconto_legal, descricao,
              emolumentos, ferc, fadep, femp, ferrfis, total, ordem_exibicao, base_de_calculo)
             VALUES (:os, :ato, :qtd, :desc_legal, :descricao,
                     :emol, :ferc, :fadep, :femp, :ferrfis, :total, :ordem, :base_item)"
        );

        foreach ($itens as $i) {
            $stIt->execute([
                ':os'         => $osId,
                ':ato'        => $i['ato'],
                ':qtd'        => $i['quantidade'],
                ':desc_legal' => $i['desconto_legal'],
                ':descricao'  => $i['descricao'],
                ':emol'       => $i['emolumentos'],
                ':ferc'       => $i['ferc'],
                ':fadep'      => $i['fadep'],
                ':femp'       => $i['femp'],
                ':ferrfis'    => $i['ferrfis'],
                ':total'      => $i['total'],
                ':ordem'      => $i['ordem_exibicao'],
                ':base_item'  => $i['base_de_calculo'],
            ]);
        }

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    /* Marca a origem e o ambiente, isolando a homologação do acervo real. */
    api_vincular_os($osId);

    /* Rastreio (best-effort), como em salvar_os.php. */
    try {
        require_once __DIR__ . '/../../pedidos_certidao/os_rastreio_lib.php';
        if (function_exists('os_rastreio_criar_para_os')) {
            os_rastreio_criar_para_os($pdo, $osId, ['usuario' => api_operador()]);
        }
    } catch (Throwable $e) {
        error_log('[api_os_criar/rastreio] ' . $e->getMessage());
    }

    return api_os_retrato($osId);
}
