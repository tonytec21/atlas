<?php

declare(strict_types=1);

/**
 * pesquisa_os_lib.php — Montagem da consulta filtrada da tela de pesquisa.
 *
 * Ficou fora do index.php porque a lógica cresceu: são 18 filtros, alguns
 * deles com subconsulta. Misturados ao HTML, ninguém consegue conferir se
 * um filtro está certo.
 *
 * REGRA AO ADICIONAR FILTRO: cada placeholder :nome pode aparecer UMA vez
 * no SQL. A conexão do Atlas usa PDO::ATTR_EMULATE_PREPARES => false, e com
 * prepares nativos o MySQL recusa nome repetido com "Invalid parameter
 * number". Precisando do mesmo valor em duas comparações, crie dois nomes.
 *
 * Compatibilidade: os nomes dos parâmetros antigos (os_id, cliente,
 * cpf_cliente, total_os, data_inicial, data_final, funcionario, situacao,
 * descricao_os, observacoes) continuam valendo — links e favoritos que já
 * existem seguem funcionando.
 */

/**
 * Lê os filtros da URL, já normalizados.
 *
 * @return array<string,mixed>
 */
function pos_filtros(): array
{
    $t = static fn(string $k): string => trim((string) ($_GET[$k] ?? ''));

    $atos = $_GET['ato'] ?? [];
    if (is_string($atos)) {
        $atos = [$atos];
    }
    $atos = array_values(array_filter(array_map('trim', (array) $atos), static fn($a) => $a !== ''));

    return [
        'q'            => $t('q'),
        'os_id'        => $t('os_id'),
        'cliente'      => $t('cliente'),
        'cpf_cliente'  => $t('cpf_cliente'),
        'descricao_os' => $t('descricao_os'),
        'observacoes'  => $t('observacoes'),
        'funcionario'  => $t('funcionario'),
        'situacao'     => $t('situacao'),
        'pagamento'    => $t('pagamento'),
        'total_os'     => $t('total_os'),
        'valor_min'    => $t('valor_min'),
        'valor_max'    => $t('valor_max'),
        'data_inicial' => $t('data_inicial'),
        'data_final'   => $t('data_final'),
        'periodo'      => $t('periodo'),
        'atos'         => $atos,
        'ato_modo'     => $t('ato_modo') === 'todos' ? 'todos' : 'qualquer',
        'ord'          => $t('ord') !== '' ? $t('ord') : 'recentes',
        // pp vazio = sem seleção: 100 últimas sem filtro / tudo (até POS_LIMITE_SEM_PAGINACAO) com filtro
        'pp'           => ($t('pp') === '' ? null : max(10, min(200, (int) $t('pp')))),
        'p'            => max(1, (int) ($_GET['p'] ?? 1)),
    ];
}

/** Teto de linhas quando "Resultados por página" fica sem seleção e há filtro. */
if (!defined('POS_LIMITE_SEM_PAGINACAO')) {
    define('POS_LIMITE_SEM_PAGINACAO', 1000);
}

/** Quantidade padrão ao entrar na tela sem filtro e sem "Resultados por página". */
if (!defined('POS_PADRAO_SEM_FILTRO')) {
    define('POS_PADRAO_SEM_FILTRO', 100);
}

/**
 * Interpreta o campo "Nº da O.S." aceitando:
 *   120            → uma O.S.
 *   100-150        → intervalo (também "100 a 150", "100..150", "100 até 150")
 *   10;25;40       → lista (separador ; ou ,)
 *   10;20-30;45    → lista e intervalos misturados
 *
 * @return array{ids:int[], faixas:array<int,array{0:int,1:int}>}
 */
function pos_parse_numeros_os(string $texto): array
{
    $ids = [];
    $faixas = [];

    $texto = mb_strtolower(trim($texto), 'UTF-8');
    if ($texto === '') {
        return ['ids' => [], 'faixas' => []];
    }

    // Normaliza os jeitos de escrever intervalo para "-"
    $texto = preg_replace('/\s*(?:\.\.|até|ate|a|-|–|—)\s*/u', '-', $texto) ?? $texto;

    foreach (preg_split('/[;,\s]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $peca) {
        if (preg_match('/^(\d+)-(\d+)$/', $peca, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            if ($a > $b) {
                [$a, $b] = [$b, $a];
            }
            if ($a === $b) {
                $ids[] = $a;
            } else {
                $faixas[] = [$a, $b];
            }
        } elseif (ctype_digit($peca)) {
            $ids[] = (int) $peca;
        }
        // qualquer outra coisa é ignorada
    }

    $ids = array_values(array_unique(array_filter($ids, static fn(int $i) => $i > 0)));
    sort($ids);

    return ['ids' => $ids, 'faixas' => $faixas];
}

/** Rótulo curto do que foi pedido em "Nº da O.S." (para o chip de filtro ativo). */
function pos_rotulo_numeros_os(array $n): string
{
    $partes = array_map('strval', $n['ids']);
    foreach ($n['faixas'] as [$a, $b]) {
        $partes[] = $a . '–' . $b;
    }
    if (count($partes) > 6) {
        $partes = array_merge(array_slice($partes, 0, 6), ['… (+' . (count($partes) - 6) . ')']);
    }

    return 'O.S. nº ' . implode('; ', $partes);
}

/**
 * Monta a condição SQL para números/intervalos de O.S.
 * Placeholders únicos (prefixo) — ver regra no topo do arquivo.
 *
 * @param array{ids:int[], faixas:array<int,array{0:int,1:int}>} $n
 * @param array<string,mixed> $par  recebe os parâmetros
 * @return string condição pronta (já entre parênteses) ou '' se não houver nada
 */
function pos_cond_numeros_os(array $n, array &$par, string $prefixo = 'nos'): string
{
    $partes = [];

    if ($n['ids'] !== []) {
        $marc = [];
        foreach ($n['ids'] as $i => $id) {
            $k = ':' . $prefixo . '_i' . $i;
            $marc[] = $k;
            $par[$k] = $id;
        }
        $partes[] = 'o.id IN (' . implode(', ', $marc) . ')';
    }

    foreach ($n['faixas'] as $i => [$a, $b]) {
        $ka = ':' . $prefixo . '_a' . $i;
        $kb = ':' . $prefixo . '_b' . $i;
        $partes[] = "o.id BETWEEN {$ka} AND {$kb}";
        $par[$ka] = $a;
        $par[$kb] = $b;
    }

    return $partes ? '(' . implode(' OR ', $partes) . ')' : '';
}

/** Converte "1.234,56" ou "1234.56" em float. Devolve null se não for número. */
function pos_valor(string $v): ?float
{
    $v = trim($v);

    if ($v === '') {
        return null;
    }

    $v = preg_replace('/[^\d,.\-]/', '', $v) ?? '';

    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }

    return is_numeric($v) ? (float) $v : null;
}

/**
 * Resolve os atalhos de período em duas datas.
 *
 * @return array{0:string,1:string} [inicial, final] no formato Y-m-d
 */
function pos_periodo(string $atalho): array
{
    $hoje = new DateTimeImmutable('today');

    return match ($atalho) {
        'hoje'     => [$hoje->format('Y-m-d'), $hoje->format('Y-m-d')],
        'ontem'    => [$hoje->modify('-1 day')->format('Y-m-d'), $hoje->modify('-1 day')->format('Y-m-d')],
        'semana'   => [$hoje->modify('monday this week')->format('Y-m-d'), $hoje->format('Y-m-d')],
        'mes'      => [$hoje->modify('first day of this month')->format('Y-m-d'), $hoje->format('Y-m-d')],
        'mes_ant'  => [
            $hoje->modify('first day of last month')->format('Y-m-d'),
            $hoje->modify('last day of last month')->format('Y-m-d'),
        ],
        'ano'      => [$hoje->format('Y') . '-01-01', $hoje->format('Y-m-d')],
        default    => ['', ''],
    };
}

/**
 * Monta WHERE e parâmetros a partir dos filtros.
 *
 * @param array<string,mixed> $f
 * @return array{where:string, params:array<string,mixed>, ativos:array<int,array{rot:string,chave:string}>}
 */
function pos_montar_where(array $f): array
{
    $cond = [];
    $par = [];
    $ativos = [];

    $marcar = static function (string $rotulo, string $chave) use (&$ativos): void {
        $ativos[] = ['rot' => $rotulo, 'chave' => $chave];
    };

    /* Busca rápida: um campo só, que decide onde procurar pelo formato do
       termo. Só dígitos e curto é número de O.S.; com pontuação de documento
       é CPF/CNPJ; o resto é nome, título ou observação. Quem quiser precisão
       usa os campos específicos abaixo. */
    if ($f['q'] !== '') {
        $q = $f['q'];
        $digitos = preg_replace('/\D/', '', $q) ?? '';

        /* ATENÇÃO: cada placeholder aparece UMA única vez.
           A conexão do Atlas usa PDO::ATTR_EMULATE_PREPARES => false, e com
           prepares nativos o MySQL recusa o mesmo :nome repetido no mesmo
           comando — o erro é "Invalid parameter number". Por isso os nomes
           são numerados em vez de reaproveitados. */
        if (preg_match('/^\d+\s*(?:[;,]|-|–|—|\.\.|\s+a\s+|\s+at[ée]\s+)/u', $q)
                && ($nq = pos_parse_numeros_os($q)) && ($nq['ids'] !== [] || $nq['faixas'] !== [])) {
            // "10;25;40" ou "100-150" na busca rápida = números de O.S.
            $cond[] = pos_cond_numeros_os($nq, $par, 'qn');
        } elseif (ctype_digit($q) && strlen($q) <= 8) {
            $cond[] = '(o.id = :q_id OR o.cliente LIKE :q_a OR o.descricao_os LIKE :q_b)';
            $par[':q_id'] = (int) $q;
            $par[':q_a'] = '%' . $q . '%';
            $par[':q_b'] = '%' . $q . '%';
        } elseif (strlen($digitos) >= 11 && strlen($digitos) <= 14) {
            $cond[] = "REPLACE(REPLACE(REPLACE(o.cpf_cliente, '.', ''), '-', ''), '/', '') LIKE :q_doc";
            $par[':q_doc'] = '%' . $digitos . '%';
        } else {
            $cond[] = '(o.cliente LIKE :q_a OR o.descricao_os LIKE :q_b'
                    . ' OR o.observacoes LIKE :q_c OR o.cpf_cliente LIKE :q_d)';
            $par[':q_a'] = '%' . $q . '%';
            $par[':q_b'] = '%' . $q . '%';
            $par[':q_c'] = '%' . $q . '%';
            $par[':q_d'] = '%' . $q . '%';
        }

        $marcar('Busca: ' . $q, 'q');
    }

    if ($f['os_id'] !== '') {
        /* Aceita um número, intervalo (100-150) ou lista separada por ;
           (10;25;40), inclusive misturados (10;20-30;45). */
        $n = pos_parse_numeros_os($f['os_id']);
        $c = pos_cond_numeros_os($n, $par, 'nos');
        if ($c !== '') {
            $cond[] = $c;
            $marcar(pos_rotulo_numeros_os($n), 'os_id');
        }
    }

    if ($f['cliente'] !== '') {
        $cond[] = 'o.cliente LIKE :cliente';
        $par[':cliente'] = '%' . $f['cliente'] . '%';
        $marcar('Apresentante: ' . $f['cliente'], 'cliente');
    }

    if ($f['cpf_cliente'] !== '') {
        // Compara só os dígitos: o banco guarda com e sem pontuação.
        $dig = preg_replace('/\D/', '', $f['cpf_cliente']) ?? '';
        $cond[] = "REPLACE(REPLACE(REPLACE(o.cpf_cliente, '.', ''), '-', ''), '/', '') LIKE :cpf";
        $par[':cpf'] = '%' . $dig . '%';
        $marcar('CPF/CNPJ: ' . $f['cpf_cliente'], 'cpf_cliente');
    }

    if ($f['descricao_os'] !== '') {
        $cond[] = 'o.descricao_os LIKE :descricao_os';
        $par[':descricao_os'] = '%' . $f['descricao_os'] . '%';
        $marcar('Título: ' . $f['descricao_os'], 'descricao_os');
    }

    if ($f['observacoes'] !== '') {
        $cond[] = 'o.observacoes LIKE :observacoes';
        $par[':observacoes'] = '%' . $f['observacoes'] . '%';
        $marcar('Observações: ' . $f['observacoes'], 'observacoes');
    }

    if ($f['funcionario'] !== '') {
        $cond[] = 'o.criado_por = :funcionario';
        $par[':funcionario'] = $f['funcionario'];
        $marcar('Funcionário: ' . $f['funcionario'], 'funcionario');
    }

    if ($f['situacao'] !== '') {
        $cond[] = 'o.status = :situacao';
        $par[':situacao'] = $f['situacao'];
        $marcar('Situação: ' . $f['situacao'], 'situacao');
    }

    // ---- Valores ----

    if ($f['total_os'] !== '' && ($v = pos_valor($f['total_os'])) !== null) {
        $cond[] = 'o.total_os = :total_os';
        $par[':total_os'] = $v;
        $marcar('Valor exato: ' . number_format($v, 2, ',', '.'), 'total_os');
    }

    if ($f['valor_min'] !== '' && ($v = pos_valor($f['valor_min'])) !== null) {
        $cond[] = 'o.total_os >= :valor_min';
        $par[':valor_min'] = $v;
        $marcar('A partir de R$ ' . number_format($v, 2, ',', '.'), 'valor_min');
    }

    if ($f['valor_max'] !== '' && ($v = pos_valor($f['valor_max'])) !== null) {
        $cond[] = 'o.total_os <= :valor_max';
        $par[':valor_max'] = $v;
        $marcar('Até R$ ' . number_format($v, 2, ',', '.'), 'valor_max');
    }

    // ---- Datas ----

    $di = $f['data_inicial'];
    $df = $f['data_final'];

    if ($f['periodo'] !== '') {
        [$pi, $pf] = pos_periodo($f['periodo']);

        if ($pi !== '') {
            $di = $pi;
            $df = $pf;
            $rotulos = [
                'hoje' => 'Hoje', 'ontem' => 'Ontem', 'semana' => 'Esta semana',
                'mes' => 'Este mês', 'mes_ant' => 'Mês passado', 'ano' => 'Este ano',
            ];
            $marcar($rotulos[$f['periodo']] ?? 'Período', 'periodo');
        }
    }

    if ($di !== '' && $df !== '') {
        $cond[] = 'DATE(o.data_criacao) BETWEEN :data_inicial AND :data_final';
        $par[':data_inicial'] = $di;
        $par[':data_final'] = $df;

        if ($f['periodo'] === '') {
            $marcar('De ' . pos_data_br($di) . ' a ' . pos_data_br($df), 'data_inicial');
        }
    } elseif ($di !== '') {
        $cond[] = 'DATE(o.data_criacao) >= :data_inicial';
        $par[':data_inicial'] = $di;
        $marcar('A partir de ' . pos_data_br($di), 'data_inicial');
    } elseif ($df !== '') {
        $cond[] = 'DATE(o.data_criacao) <= :data_final';
        $par[':data_final'] = $df;
        $marcar('Até ' . pos_data_br($df), 'data_final');
    }

    // ---- Atos praticados ----

    /* O ato mora em ordens_de_servico_itens, não na O.S. Usamos EXISTS por
       ato: com "todos", cada ato vira uma condição própria, e a O.S. só
       entra se satisfizer todas. Um IN simples devolveria O.S. que têm
       qualquer um deles, o que não é a mesma pergunta. */
    if ($f['atos'] !== []) {
        if ($f['ato_modo'] === 'todos') {
            foreach (array_values($f['atos']) as $i => $ato) {
                $chave = ':ato_t' . $i;
                $cond[] = "EXISTS (SELECT 1 FROM ordens_de_servico_itens i{$i}
                                    WHERE i{$i}.ordem_servico_id = o.id AND i{$i}.ato = {$chave})";
                $par[$chave] = $ato;
            }
        } else {
            $marcadores = [];

            foreach (array_values($f['atos']) as $i => $ato) {
                $chave = ':ato_q' . $i;
                $marcadores[] = $chave;
                $par[$chave] = $ato;
            }

            $cond[] = 'EXISTS (SELECT 1 FROM ordens_de_servico_itens ia
                                WHERE ia.ordem_servico_id = o.id
                                  AND ia.ato IN (' . implode(', ', $marcadores) . '))';
        }

        $marcar(
            (count($f['atos']) > 1 ? 'Atos: ' : 'Ato: ') . implode(', ', $f['atos'])
            . (count($f['atos']) > 1 ? ($f['ato_modo'] === 'todos' ? ' (todos)' : ' (qualquer)') : ''),
            'ato'
        );
    }

    // ---- Situação de pagamento ----

    /* Derivada de pagamento_os e devolucao_os. Ficam como subconsultas para
       não multiplicar linhas com JOIN. */
    if ($f['pagamento'] !== '') {
        $pago = "(SELECT COALESCE(SUM(pg.total_pagamento), 0) FROM pagamento_os pg
                   WHERE pg.ordem_de_servico_id = o.id)";
        $devolvido = "(SELECT COALESCE(SUM(dv.total_devolucao), 0) FROM devolucao_os dv
                        WHERE dv.ordem_de_servico_id = o.id)";
        $liquido = "({$pago} - {$devolvido})";

        $rotulos = [
            'sem'      => 'Sem pagamento',
            'parcial'  => 'Pagamento parcial',
            'quitada'  => 'Quitada',
            'credito'  => 'Com crédito',
        ];

        switch ($f['pagamento']) {
            case 'sem':
                $cond[] = "{$liquido} <= 0";
                break;
            case 'parcial':
                $cond[] = "{$liquido} > 0 AND {$liquido} < o.total_os";
                break;
            case 'quitada':
                $cond[] = "{$liquido} >= o.total_os AND o.total_os > 0";
                break;
            case 'credito':
                $cond[] = "{$liquido} > o.total_os";
                break;
        }

        if (isset($rotulos[$f['pagamento']])) {
            $marcar($rotulos[$f['pagamento']], 'pagamento');
        }
    }

    return [
        'where'  => $cond ? ' WHERE ' . implode(' AND ', $cond) : '',
        'params' => $par,
        'ativos' => $ativos,
    ];
}

function pos_data_br(string $iso): string
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $iso);

    return $d ? $d->format('d/m/Y') : $iso;
}

/** Cláusula ORDER BY correspondente ao atalho escolhido. */
function pos_ordem(string $ord): string
{
    return match ($ord) {
        'antigas'  => 'o.data_criacao ASC, o.id ASC',
        'maior'    => 'o.total_os DESC, o.id DESC',
        'menor'    => 'o.total_os ASC, o.id ASC',
        'cliente'  => 'o.cliente ASC, o.id DESC',
        'numero'   => 'o.id DESC',
        default    => 'o.data_criacao DESC, o.id DESC',
    };
}

/**
 * Reconstrói a query string preservando os filtros, trocando ou removendo
 * uma chave. Usado pelos chips de filtro ativo e pela paginação.
 *
 * @param array<string,mixed> $trocas
 */
function pos_url(array $trocas = [], ?string $remover = null): string
{
    $qs = $_GET;

    foreach ($trocas as $k => $v) {
        $qs[$k] = $v;
    }

    if ($remover !== null) {
        unset($qs[$remover]);

        // Remover o período também limpa as datas que ele preencheu.
        if ($remover === 'periodo') {
            unset($qs['data_inicial'], $qs['data_final']);
        }

        if ($remover === 'data_inicial') {
            unset($qs['data_final']);
        }
    }

    unset($qs['p']); // trocar um filtro sempre volta à primeira página

    return 'index.php' . ($qs ? '?' . http_build_query($qs) : '');
}
