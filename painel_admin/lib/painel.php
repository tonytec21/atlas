<?php
/**
 * =====================================================================
 * painel_admin/lib/painel.php — Painel do Administrador (Atlas)
 * ---------------------------------------------------------------------
 * Conexão, permissões, períodos e os indicadores de faturamento, equipe
 * e serventia. Tudo é calculado direto das tabelas dos módulos; nada é
 * duplicado. As colunas/tabelas opcionais são testadas antes do uso, para
 * o painel funcionar em serventias com módulos em versões diferentes.
 * =====================================================================
 */

require_once __DIR__ . '/../versao.php';
require_once __DIR__ . '/../../auditoria/lib.php';
if (is_file(__DIR__ . '/../../auditoria/hook.php')) {
    include_once __DIR__ . '/../../auditoria/hook.php';
}

date_default_timezone_set('America/Fortaleza');

/* ============================ Conexão e permissão ============================ */

function pa_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = aud_cfg_conexao();
    $pdo = new PDO('mysql:host=' . $c['host'] . ';dbname=' . $c['banco'] . ';charset=utf8mb4', $c['usuario'], $c['senha'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '-03:00', SESSION group_concat_max_len = 1048576");
    if (function_exists('atlas_aud_conexao')) {
        atlas_aud_conexao($pdo);
    }
    return $pdo;
}

function pa_usuario(): array
{
    static $u = null;
    if ($u !== null) {
        return $u;
    }
    $login = $_SESSION['username'] ?? '';
    $u = ['usuario' => $login, 'nome' => $login, 'nivel' => '', 'cargo' => ''];
    if ($login === '') {
        return $u;
    }
    $st = pa_db()->prepare('SELECT usuario, nome_completo, nivel_de_acesso, cargo FROM funcionarios WHERE usuario = ? LIMIT 1');
    $st->execute([$login]);
    if ($r = $st->fetch()) {
        $u = ['usuario' => $r['usuario'], 'nome' => pa_utf8($r['nome_completo']), 'nivel' => $r['nivel_de_acesso'], 'cargo' => pa_utf8($r['cargo'])];
    }
    return $u;
}

function pa_eh_admin(): bool
{
    return strtolower(trim(pa_usuario()['nivel'])) === 'administrador';
}

/* ============================ Utilidades ============================ */

function pa_utf8($s): string
{
    $s = (string) $s;
    return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
}

function pa_all(string $sql, array $p = []): array
{
    $st = pa_db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

function pa_one(string $sql, array $p = []): ?array
{
    $st = pa_db()->prepare($sql);
    $st->execute($p);
    $r = $st->fetch();
    return $r ?: null;
}

function pa_val(string $sql, array $p = [])
{
    $st = pa_db()->prepare($sql);
    $st->execute($p);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function pa_tabela_existe(string $t): bool
{
    static $cache = [];
    if (!isset($cache[$t])) {
        $cache[$t] = (bool) pa_val('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$t]);
    }
    return $cache[$t];
}

function pa_coluna_existe(string $t, string $c): bool
{
    static $cache = [];
    $k = "$t.$c";
    if (!isset($cache[$k])) {
        $cache[$k] = (bool) pa_val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c]);
    }
    return $cache[$k];
}

function pa_num($v): float
{
    return round((float) $v, 2);
}

function pa_variacao(float $atual, float $anterior): ?float
{
    if (abs($anterior) < 0.0001) {
        return $atual > 0 ? null : 0.0;
    }
    return round(($atual - $anterior) / abs($anterior) * 100, 1);
}

/* ============================ Período ============================ */

/**
 * Período pedido + período anterior equivalente (mesmo número de dias).
 * Granularidade dos gráficos: dia (até 62 dias), semana (até 180) ou mês.
 */
function pa_periodo(?string $ini, ?string $fim): array
{
    $hoje = new DateTimeImmutable('today');
    $i = $ini && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini) ? new DateTimeImmutable($ini) : $hoje->modify('first day of this month');
    $f = $fim && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim) ? new DateTimeImmutable($fim) : $hoje;
    if ($f < $i) {
        [$i, $f] = [$f, $i];
    }
    $dias = (int) $i->diff($f)->days + 1;
    if ($dias > 1100) {
        $i = $f->modify('-1099 days');
        $dias = 1100;
    }
    $pf = $i->modify('-1 day');
    $pi = $pf->modify('-' . ($dias - 1) . ' days');
    $gran = $dias <= 62 ? 'dia' : ($dias <= 180 ? 'semana' : 'mes');
    return [
        'ini' => $i->format('Y-m-d'), 'fim' => $f->format('Y-m-d'),
        'ini_dt' => $i->format('Y-m-d 00:00:00'), 'fim_dt' => $f->format('Y-m-d 23:59:59'),
        'ant_ini' => $pi->format('Y-m-d'), 'ant_fim' => $pf->format('Y-m-d'),
        'ant_ini_dt' => $pi->format('Y-m-d 00:00:00'), 'ant_fim_dt' => $pf->format('Y-m-d 23:59:59'),
        'dias' => $dias, 'granularidade' => $gran,
    ];
}

/** Expressão SQL de agrupamento + lista completa de rótulos do eixo X. */
function pa_eixo(array $per, string $col): array
{
    $i = new DateTimeImmutable($per['ini']);
    $f = new DateTimeImmutable($per['fim']);
    $rot = [];
    if ($per['granularidade'] === 'dia') {
        $expr = "DATE($col)";
        for ($d = $i; $d <= $f; $d = $d->modify('+1 day')) {
            $rot[$d->format('Y-m-d')] = $d->format('d/m');
        }
    } elseif ($per['granularidade'] === 'semana') {
        $expr = "DATE_SUB(DATE($col), INTERVAL WEEKDAY($col) DAY)";
        $d = $i->modify('-' . ((int) $i->format('N') - 1) . ' days');
        for (; $d <= $f; $d = $d->modify('+7 days')) {
            $rot[$d->format('Y-m-d')] = 'sem. ' . $d->format('d/m');
        }
    } else {
        $expr = "DATE_FORMAT($col, '%Y-%m-01')";
        $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        for ($d = $i->modify('first day of this month'); $d <= $f; $d = $d->modify('+1 month')) {
            $rot[$d->format('Y-m-01')] = $meses[(int) $d->format('n') - 1] . '/' . $d->format('y');
        }
    }
    return [$expr, $rot];
}

/** Monta uma série alinhada ao eixo a partir de linhas [k => chave, v => valor]. */
function pa_serie(array $rot, array $linhas, string $campo = 'v'): array
{
    $m = [];
    foreach ($linhas as $l) {
        $m[(string) $l['k']] = (float) $l[$campo];
    }
    $out = [];
    foreach (array_keys($rot) as $k) {
        $out[] = round($m[$k] ?? 0, 2);
    }
    return $out;
}

/* ============================ Funcionários ============================ */

/** Funcionários indexados pelo login, com o nome completo e o mapa nome → login. */
function pa_funcionarios(): array
{
    static $f = null;
    if ($f !== null) {
        return $f;
    }
    $f = ['por_login' => [], 'por_nome' => []];
    $cols = 'id, usuario, nome_completo, cargo, nivel_de_acesso, status' . (pa_coluna_existe('funcionarios', 'tfa_enabled') ? ', tfa_enabled' : ', 0 AS tfa_enabled');
    foreach (pa_all("SELECT $cols FROM funcionarios ORDER BY nome_completo") as $r) {
        $login = (string) $r['usuario'];
        $r['nome_completo'] = pa_utf8($r['nome_completo']);
        $r['cargo'] = pa_utf8($r['cargo']);
        $f['por_login'][$login] = $r;
        $f['por_nome'][mb_strtolower(trim($r['nome_completo']))] = $login;
    }
    return $f;
}

/** Resolve um autor gravado como login OU nome completo para o login. */
function pa_login_de($quem): string
{
    $quem = trim(pa_utf8((string) $quem));
    if ($quem === '') {
        return '';
    }
    $f = pa_funcionarios();
    if (isset($f['por_login'][$quem])) {
        return $quem;
    }
    return $f['por_nome'][mb_strtolower($quem)] ?? $quem;
}

function pa_nome_de(string $login): string
{
    $f = pa_funcionarios();
    return $f['por_login'][$login]['nome_completo'] ?? $login;
}

/* ============================ Filtros de status ============================ */

function pa_ok_pagamento(string $a = ''): string
{
    $c = $a ? "$a.status" : 'status';
    return "LOWER(COALESCE($c,'')) NOT IN ('cancelado','removido','estornado','excluido','excluído')";
}

/* ============================ Faturamento ============================ */

/** Totais financeiros de um intervalo (usado no período e no período anterior). */
function pa_financeiro_totais(string $ini, string $fim): array
{
    $t = [];
    $t['recebido'] = (float) pa_val('SELECT COALESCE(SUM(total_pagamento),0) FROM pagamento_os WHERE data_pagamento BETWEEN ? AND ? AND ' . pa_ok_pagamento(), [$ini, $fim]);
    $t['recebimentos'] = (int) pa_val('SELECT COUNT(*) FROM pagamento_os WHERE data_pagamento BETWEEN ? AND ? AND ' . pa_ok_pagamento(), [$ini, $fim]);
    $atos = pa_one('SELECT COALESCE(SUM(total),0) total, COALESCE(SUM(emolumentos),0) emol, COALESCE(SUM(ferc),0) ferc, COALESCE(SUM(fadep),0) fadep,
                           COALESCE(SUM(femp),0) femp, COALESCE(SUM(ferrfis),0) ferrfis, COALESCE(SUM(quantidade_liquidada),0) qtd
                      FROM atos_liquidados WHERE data BETWEEN ? AND ?', [$ini, $fim]);
    $man = pa_tabela_existe('atos_manuais_liquidados') ? pa_one('SELECT COALESCE(SUM(total),0) total, COALESCE(SUM(emolumentos),0) emol, COALESCE(SUM(ferc),0) ferc,
                           COALESCE(SUM(fadep),0) fadep, COALESCE(SUM(femp),0) femp, COALESCE(SUM(ferrfis),0) ferrfis, COALESCE(SUM(quantidade_liquidada),0) qtd
                      FROM atos_manuais_liquidados WHERE data BETWEEN ? AND ?', [$ini, $fim]) : null;
    $soma = function ($k) use ($atos, $man) {
        return (float) ($atos[$k] ?? 0) + (float) ($man[$k] ?? 0);
    };
    $t['praticado'] = $soma('total');
    $t['emolumentos'] = $soma('emol');
    $t['ferc'] = $soma('ferc');
    $t['fadep'] = $soma('fadep');
    $t['femp'] = $soma('femp');
    $t['ferrfis'] = $soma('ferrfis');
    $t['fundos'] = $t['ferc'] + $t['fadep'] + $t['femp'] + $t['ferrfis'];
    $t['atos_qtd'] = (int) $soma('qtd');
    $t['devolvido'] = (float) pa_val("SELECT COALESCE(SUM(total_devolucao),0) FROM devolucao_os WHERE data_devolucao BETWEEN ? AND ? AND " . pa_ok_pagamento(), [$ini, $fim]);
    $t['repasse'] = pa_tabela_existe('repasse_credor') ? (float) pa_val("SELECT COALESCE(SUM(total_repasse),0) FROM repasse_credor WHERE data_repasse BETWEEN ? AND ? AND status = 'ativo'", [$ini, $fim]) : 0.0;
    $t['despesas_caixa'] = pa_tabela_existe('saidas_despesas') ? (float) pa_val("SELECT COALESCE(SUM(valor_saida),0) FROM saidas_despesas WHERE data_caixa BETWEEN ? AND ? AND status = 'ativo'", [substr($ini, 0, 10), substr($fim, 0, 10)]) : 0.0;
    $t['contas_pagas'] = 0.0;
    if (pa_tabela_existe('contas_a_pagar')) {
        $col = pa_coluna_existe('contas_a_pagar', 'data_pagamento') ? 'COALESCE(data_pagamento, data_vencimento)' : 'data_vencimento';
        $t['contas_pagas'] = (float) pa_val("SELECT COALESCE(SUM(valor),0) FROM contas_a_pagar WHERE status = 'Pago' AND $col BETWEEN ? AND ?", [substr($ini, 0, 10), substr($fim, 0, 10)]);
    }
    $os = pa_one("SELECT COUNT(*) n, COALESCE(SUM(total_os),0) v, SUM(status = 'Cancelado') canc FROM ordens_de_servico WHERE data_criacao BETWEEN ? AND ?", [$ini, $fim]);
    $t['os_criadas'] = (int) $os['n'];
    $t['os_valor'] = (float) $os['v'];
    $t['os_canceladas'] = (int) $os['canc'];
    $t['ticket_medio'] = $t['os_criadas'] - $t['os_canceladas'] > 0
        ? (float) pa_val("SELECT AVG(total_os) FROM ordens_de_servico WHERE data_criacao BETWEEN ? AND ? AND status <> 'Cancelado'", [$ini, $fim]) : 0.0;
    $t['saidas'] = $t['devolvido'] + $t['repasse'] + $t['despesas_caixa'] + $t['contas_pagas'];
    $t['resultado'] = $t['recebido'] - $t['saidas'];
    foreach ($t as $k => $v) {
        $t[$k] = is_float($v) ? round($v, 2) : $v;
    }
    return $t;
}

/** Saldo de depósito prévio por O.S. ativa (pago + transferido para ela − atos − devoluções − repasses − transferido dela). */
function pa_deposito_previo(): array
{
    $temTs = pa_tabela_existe('os_transferencias_saldo');
    $temMan = pa_tabela_existe('atos_manuais_liquidados');
    $temRep = pa_tabela_existe('repasse_credor');
    $sql = "SELECT o.id, o.cliente, o.total_os, o.data_criacao,
              COALESCE(p.v,0) pago, COALESCE(a.v,0) + " . ($temMan ? 'COALESCE(m.v,0)' : '0') . " praticado,
              COALESCE(d.v,0) devolvido, " . ($temRep ? 'COALESCE(r.v,0)' : '0') . " repasse,
              " . ($temTs ? 'COALESCE(te.v,0)' : '0') . " transf_entrada, " . ($temTs ? 'COALESCE(ts.v,0)' : '0') . " transf_saida,
              GREATEST(COALESCE(p.ult,'1970-01-01'), COALESCE(a.ult,'1970-01-01')" . ($temMan ? ", COALESCE(m.ult,'1970-01-01')" : '') . ", COALESCE(d.ult,'1970-01-01')) ultimo_mov
            FROM ordens_de_servico o
            LEFT JOIN (SELECT ordem_de_servico_id id, SUM(total_pagamento) v, MAX(data_pagamento) ult FROM pagamento_os WHERE " . pa_ok_pagamento() . " GROUP BY 1) p ON p.id = o.id
            LEFT JOIN (SELECT ordem_servico_id id, SUM(total) v, MAX(data) ult FROM atos_liquidados GROUP BY 1) a ON a.id = o.id
            " . ($temMan ? 'LEFT JOIN (SELECT ordem_servico_id id, SUM(total) v, MAX(data) ult FROM atos_manuais_liquidados GROUP BY 1) m ON m.id = o.id' : '') . "
            LEFT JOIN (SELECT ordem_de_servico_id id, SUM(total_devolucao) v, MAX(data_devolucao) ult FROM devolucao_os WHERE " . pa_ok_pagamento() . " GROUP BY 1) d ON d.id = o.id
            " . ($temRep ? "LEFT JOIN (SELECT ordem_de_servico_id id, SUM(total_repasse) v FROM repasse_credor WHERE status = 'ativo' GROUP BY 1) r ON r.id = o.id" : '') . "
            " . ($temTs ? "LEFT JOIN (SELECT os_destino_id id, SUM(valor) v FROM os_transferencias_saldo WHERE status = 'ativa' GROUP BY 1) te ON te.id = o.id
                           LEFT JOIN (SELECT os_origem_id id, SUM(valor) v FROM os_transferencias_saldo WHERE status = 'ativa' GROUP BY 1) ts ON ts.id = o.id" : '') . "
            WHERE o.status <> 'Cancelado'";
    $linhas = pa_all($sql);
    $total = 0.0;
    $abertas = 0;
    $paradas = [];
    $limite = date('Y-m-d H:i:s', strtotime('-30 days'));
    foreach ($linhas as $l) {
        $saldo = (float) $l['pago'] + (float) $l['transf_entrada'] - (float) $l['praticado'] - (float) $l['devolvido'] - (float) $l['repasse'] - (float) $l['transf_saida'];
        if ($saldo > 0.009) {
            $total += $saldo;
            $abertas++;
            if ($l['ultimo_mov'] < $limite) {
                $paradas[] = [
                    'id' => (int) $l['id'], 'cliente' => pa_utf8($l['cliente']), 'saldo' => round($saldo, 2),
                    'ultimo_mov' => $l['ultimo_mov'] > '1971' ? $l['ultimo_mov'] : $l['data_criacao'],
                    'dias' => (int) floor((time() - strtotime($l['ultimo_mov'] > '1971' ? $l['ultimo_mov'] : $l['data_criacao'])) / 86400),
                ];
            }
        }
    }
    usort($paradas, function ($a, $b) {
        return $b['saldo'] <=> $a['saldo'];
    });
    return [
        'saldo_total' => round($total, 2), 'os_com_saldo' => $abertas,
        'paradas_qtd' => count($paradas), 'paradas_valor' => round(array_sum(array_column($paradas, 'saldo')), 2),
        'paradas' => array_slice($paradas, 0, 12),
    ];
}

function pa_faturamento(array $per): array
{
    $tot = pa_financeiro_totais($per['ini_dt'], $per['fim_dt']);
    $ant = pa_financeiro_totais($per['ant_ini_dt'], $per['ant_fim_dt']);

    [$exP, $rot] = pa_eixo($per, 'data_pagamento');
    $rec = pa_all("SELECT $exP k, SUM(total_pagamento) v FROM pagamento_os WHERE data_pagamento BETWEEN ? AND ? AND " . pa_ok_pagamento() . " GROUP BY k", [$per['ini_dt'], $per['fim_dt']]);
    [$exA] = pa_eixo($per, 'data');
    $prat = pa_all("SELECT k, SUM(v) v, SUM(e) e FROM (
                      SELECT $exA k, total v, emolumentos e FROM atos_liquidados WHERE data BETWEEN ? AND ?
                      " . (pa_tabela_existe('atos_manuais_liquidados') ? "UNION ALL SELECT $exA k, total v, emolumentos e FROM atos_manuais_liquidados WHERE data BETWEEN ? AND ?" : '') . "
                    ) x GROUP BY k", pa_tabela_existe('atos_manuais_liquidados') ? [$per['ini_dt'], $per['fim_dt'], $per['ini_dt'], $per['fim_dt']] : [$per['ini_dt'], $per['fim_dt']]);
    [$exO] = pa_eixo($per, 'data_criacao');
    $os = pa_all("SELECT $exO k, COUNT(*) v FROM ordens_de_servico WHERE data_criacao BETWEEN ? AND ? AND status <> 'Cancelado' GROUP BY k", [$per['ini_dt'], $per['fim_dt']]);

    // Acumulado do período atual × anterior (mesmo dia relativo)
    $acum = [];
    $acumAnt = [];
    $dias = min($per['dias'], 400);
    $diaA = pa_all("SELECT DATEDIFF(DATE(data_pagamento), ?) d, SUM(total_pagamento) v FROM pagamento_os WHERE data_pagamento BETWEEN ? AND ? AND " . pa_ok_pagamento() . " GROUP BY d", [$per['ini'], $per['ini_dt'], $per['fim_dt']]);
    $diaB = pa_all("SELECT DATEDIFF(DATE(data_pagamento), ?) d, SUM(total_pagamento) v FROM pagamento_os WHERE data_pagamento BETWEEN ? AND ? AND " . pa_ok_pagamento() . " GROUP BY d", [$per['ant_ini'], $per['ant_ini_dt'], $per['ant_fim_dt']]);
    $ma = array_column($diaA, 'v', 'd');
    $mb = array_column($diaB, 'v', 'd');
    $sa = 0;
    $sb = 0;
    for ($d = 0; $d < $dias; $d++) {
        $sa += (float) ($ma[$d] ?? 0);
        $sb += (float) ($mb[$d] ?? 0);
        $acum[] = round($sa, 2);
        $acumAnt[] = round($sb, 2);
    }

    $formas = pa_all("SELECT forma_de_pagamento f, COUNT(*) n, SUM(total_pagamento) v FROM pagamento_os
                       WHERE data_pagamento BETWEEN ? AND ? AND " . pa_ok_pagamento() . " GROUP BY forma_de_pagamento ORDER BY v DESC", [$per['ini_dt'], $per['fim_dt']]);
    foreach ($formas as &$f) {
        $f['f'] = pa_utf8($f['f']);
        $f['v'] = pa_num($f['v']);
    }
    unset($f);

    $atosTop = pa_all("SELECT ato, MAX(descricao) descricao, SUM(quantidade_liquidada) qtd, SUM(total) v, SUM(emolumentos) e FROM (
                         SELECT ato, descricao, quantidade_liquidada, total, emolumentos FROM atos_liquidados WHERE data BETWEEN ? AND ?
                         " . (pa_tabela_existe('atos_manuais_liquidados') ? 'UNION ALL SELECT ato, descricao, quantidade_liquidada, total, emolumentos FROM atos_manuais_liquidados WHERE data BETWEEN ? AND ?' : '') . "
                       ) x GROUP BY ato ORDER BY v DESC LIMIT 10", pa_tabela_existe('atos_manuais_liquidados') ? [$per['ini_dt'], $per['fim_dt'], $per['ini_dt'], $per['fim_dt']] : [$per['ini_dt'], $per['fim_dt']]);
    foreach ($atosTop as &$a) {
        $a['descricao'] = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags(pa_utf8($a['descricao'])))), 0, 90);
        $a['v'] = pa_num($a['v']);
        $a['e'] = pa_num($a['e']);
        $a['qtd'] = (int) $a['qtd'];
    }
    unset($a);

    $semana = pa_all("SELECT WEEKDAY(data_pagamento) d, SUM(total_pagamento) v, COUNT(*) n FROM pagamento_os
                       WHERE data_pagamento BETWEEN ? AND ? AND " . pa_ok_pagamento() . " GROUP BY d", [$per['ini_dt'], $per['fim_dt']]);
    $porDia = array_fill(0, 7, 0);
    foreach ($semana as $s) {
        $porDia[(int) $s['d']] = pa_num($s['v']);
    }

    $mesesHist = pa_all("SELECT DATE_FORMAT(data_pagamento,'%Y-%m') k, SUM(total_pagamento) v FROM pagamento_os
                          WHERE data_pagamento >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 11 MONTH) AND " . pa_ok_pagamento() . " GROUP BY k ORDER BY k");

    return [
        'totais' => $tot, 'anterior' => $ant,
        'variacao' => [
            'recebido' => pa_variacao($tot['recebido'], $ant['recebido']),
            'praticado' => pa_variacao($tot['praticado'], $ant['praticado']),
            'emolumentos' => pa_variacao($tot['emolumentos'], $ant['emolumentos']),
            'os_criadas' => pa_variacao($tot['os_criadas'], $ant['os_criadas']),
            'ticket_medio' => pa_variacao($tot['ticket_medio'], $ant['ticket_medio']),
            'resultado' => pa_variacao($tot['resultado'], $ant['resultado']),
            'atos_qtd' => pa_variacao($tot['atos_qtd'], $ant['atos_qtd']),
        ],
        'eixo' => array_values($rot),
        'serie_recebido' => pa_serie($rot, $rec),
        'serie_praticado' => pa_serie($rot, $prat),
        'serie_emolumentos' => pa_serie($rot, $prat, 'e'),
        'serie_os' => pa_serie($rot, $os),
        'acumulado' => $acum, 'acumulado_anterior' => $acumAnt,
        'formas' => $formas,
        'composicao' => [
            ['rotulo' => 'Emolumentos', 'v' => $tot['emolumentos']], ['rotulo' => 'FERC', 'v' => $tot['ferc']],
            ['rotulo' => 'FADEP', 'v' => $tot['fadep']], ['rotulo' => 'FEMP', 'v' => $tot['femp']],
            ['rotulo' => 'FERRFIS', 'v' => $tot['ferrfis']],
        ],
        'atos_top' => $atosTop,
        'dia_semana' => $porDia,
        'historico_meses' => array_map(function ($r) {
            return ['k' => $r['k'], 'v' => pa_num($r['v'])];
        }, $mesesHist),
        'deposito' => pa_deposito_previo(),
        'granularidade' => $per['granularidade'],
    ];
}

/* ============================ Tarefas e pedidos ============================ */

/** Status das tarefas agrupado como no módulo Tarefas. */
function pa_grupo_status_tarefa(string $s): string
{
    $s = mb_strtolower(pa_utf8($s));
    if (preg_match('/conclu|emitid|entregue|aguardando retirada|finaliz/u', $s)) {
        return 'concluida';
    }
    if (preg_match('/cancel/u', $s)) {
        return 'cancelada';
    }
    if (preg_match('/andamento|iniciad|exig[êe]ncia cumprida/u', $s)) {
        return 'andamento';
    }
    return 'pendente';
}

function pa_tarefas(array $per): array
{
    $r = ['criadas' => 0, 'concluidas' => 0, 'concluidas_no_prazo' => 0, 'abertas' => 0, 'atrasadas' => 0, 'por_responsavel' => [], 'por_categoria' => [],
          'tempo_medio_horas' => null, 'pedidos' => ['criados' => 0, 'entregues' => 0, 'pendentes' => 0, 'cancelados' => 0, 'tempo_medio_horas' => null]];
    if (pa_tabela_existe('tarefas')) {
        $agora = date('Y-m-d H:i:s');
        $cat = pa_tabela_existe('categorias') ? 'LEFT JOIN categorias c ON c.id = t.categoria' : '';
        $catCol = $cat ? 'COALESCE(c.titulo, t.categoria)' : 't.categoria';
        $linhas = pa_all("SELECT t.id, t.status, t.data_criacao, t.data_limite, t.data_conclusao, t.funcionario_responsavel resp, $catCol cat
                            FROM tarefas t $cat
                           WHERE (t.data_criacao BETWEEN ? AND ?) OR (t.data_conclusao BETWEEN ? AND ?)
                              OR (t.data_criacao <= ? AND (t.data_conclusao IS NULL))", [$per['ini_dt'], $per['fim_dt'], $per['ini_dt'], $per['fim_dt'], $per['fim_dt']]);
        $horas = [];
        foreach ($linhas as $l) {
            $g = pa_grupo_status_tarefa((string) $l['status']);
            $login = pa_login_de($l['resp']);
            $pr = &$r['por_responsavel'][$login];
            $pr = ($pr ?? []) + ['concluidas' => 0, 'abertas' => 0, 'atrasadas' => 0, 'no_prazo' => 0, 'criadas' => 0];
            $criadaNoPer = $l['data_criacao'] >= $per['ini_dt'] && $l['data_criacao'] <= $per['fim_dt'];
            if ($criadaNoPer) {
                $r['criadas']++;
                $pr['criadas']++;
                $c = pa_utf8((string) $l['cat']) ?: 'Sem categoria';
                $r['por_categoria'][$c] = ($r['por_categoria'][$c] ?? 0) + 1;
            }
            if ($g === 'concluida' && $l['data_conclusao'] && $l['data_conclusao'] >= $per['ini_dt'] && $l['data_conclusao'] <= $per['fim_dt']) {
                $r['concluidas']++;
                $pr['concluidas']++;
                if (!$l['data_limite'] || $l['data_conclusao'] <= $l['data_limite']) {
                    $r['concluidas_no_prazo']++;
                    $pr['no_prazo']++;
                }
                if ($l['data_criacao']) {
                    $horas[] = (strtotime($l['data_conclusao']) - strtotime($l['data_criacao'])) / 3600;
                }
            } elseif ($g === 'pendente' || $g === 'andamento') {
                $r['abertas']++;
                $pr['abertas']++;
                if ($l['data_limite'] && $l['data_limite'] < $agora) {
                    $r['atrasadas']++;
                    $pr['atrasadas']++;
                }
            }
            unset($pr);
        }
        $r['tempo_medio_horas'] = $horas ? round(array_sum($horas) / count($horas), 1) : null;
        arsort($r['por_categoria']);
        $r['por_categoria'] = array_slice($r['por_categoria'], 0, 10, true);
    }
    if (pa_tabela_existe('pedidos_certidao')) {
        $p = pa_one("SELECT COUNT(*) criados, SUM(status IN ('entregue','emitida')) entregues, SUM(status IN ('pendente','em_andamento')) pendentes,
                            SUM(status = 'cancelada') cancelados,
                            AVG(CASE WHEN status IN ('entregue','emitida') AND atualizado_em IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, criado_em, atualizado_em) END) / 60 tm
                       FROM pedidos_certidao WHERE criado_em BETWEEN ? AND ?", [$per['ini_dt'], $per['fim_dt']]);
        $r['pedidos'] = ['criados' => (int) $p['criados'], 'entregues' => (int) $p['entregues'], 'pendentes' => (int) $p['pendentes'],
                         'cancelados' => (int) $p['cancelados'], 'tempo_medio_horas' => $p['tm'] !== null ? round((float) $p['tm'], 1) : null];
        $r['pedidos']['abertos_total'] = (int) pa_val("SELECT COUNT(*) FROM pedidos_certidao WHERE status IN ('pendente','em_andamento')");
    }
    return $r;
}

/* ============================ Equipe ============================ */

function pa_equipe(array $per): array
{
    $f = pa_funcionarios();
    $ini = $per['ini_dt'];
    $fim = $per['fim_dt'];
    $base = function () {
        return ['os_criadas' => 0, 'os_valor' => 0.0, 'os_canceladas' => 0, 'atos_qtd' => 0, 'atos_valor' => 0.0, 'recebimentos' => 0, 'recebido' => 0.0,
                'devolucoes' => 0, 'tarefas_concluidas' => 0, 'tarefas_no_prazo' => 0, 'tarefas_abertas' => 0, 'tarefas_atrasadas' => 0,
                'pedidos' => 0, 'operacoes' => 0, 'exclusoes' => 0, 'falhas' => 0, 'negados' => 0, 'fora_expediente' => 0,
                'logins' => 0, 'dias_ativos' => 0, 'telas' => 0, 'ultimo_acesso' => null];
    };
    $m = [];
    foreach ($f['por_login'] as $login => $r) {
        if (strtolower((string) $r['status']) !== 'ativo') {
            continue;
        }
        $m[$login] = $base();
    }
    $soma = function ($rows, $campoUser, array $mapa) use (&$m, $base, $f) {
        foreach ($rows as $r) {
            $l = pa_login_de($r[$campoUser]);
            if ($l === '' || !isset($f['por_login'][$l])) {
                continue;   // só funcionários cadastrados (rotinas automáticas e contas apagadas ficam de fora)
            }
            if (!isset($m[$l])) {
                $m[$l] = $base();
            }
            foreach ($mapa as $de => $para) {
                $m[$l][$para] += is_numeric($r[$de]) ? $r[$de] + 0 : 0;
            }
        }
    };
    $soma(pa_all("SELECT criado_por u, COUNT(*) n, SUM(total_os) v, SUM(status = 'Cancelado') c FROM ordens_de_servico WHERE data_criacao BETWEEN ? AND ? GROUP BY criado_por", [$ini, $fim]), 'u', ['n' => 'os_criadas', 'v' => 'os_valor', 'c' => 'os_canceladas']);
    $soma(pa_all("SELECT funcionario u, SUM(quantidade_liquidada) n, SUM(total) v FROM atos_liquidados WHERE data BETWEEN ? AND ? GROUP BY funcionario", [$ini, $fim]), 'u', ['n' => 'atos_qtd', 'v' => 'atos_valor']);
    if (pa_tabela_existe('atos_manuais_liquidados')) {
        $soma(pa_all("SELECT funcionario u, SUM(quantidade_liquidada) n, SUM(total) v FROM atos_manuais_liquidados WHERE data BETWEEN ? AND ? GROUP BY funcionario", [$ini, $fim]), 'u', ['n' => 'atos_qtd', 'v' => 'atos_valor']);
    }
    $soma(pa_all("SELECT funcionario u, COUNT(*) n, SUM(total_pagamento) v FROM pagamento_os WHERE data_pagamento BETWEEN ? AND ? AND " . pa_ok_pagamento() . " GROUP BY funcionario", [$ini, $fim]), 'u', ['n' => 'recebimentos', 'v' => 'recebido']);
    $soma(pa_all("SELECT funcionario u, COUNT(*) n FROM devolucao_os WHERE data_devolucao BETWEEN ? AND ? GROUP BY funcionario", [$ini, $fim]), 'u', ['n' => 'devolucoes']);
    if (pa_tabela_existe('pedidos_certidao')) {
        $soma(pa_all("SELECT criado_por u, COUNT(*) n FROM pedidos_certidao WHERE criado_em BETWEEN ? AND ? GROUP BY criado_por", [$ini, $fim]), 'u', ['n' => 'pedidos']);
    }
    $tar = pa_tarefas($per);
    foreach ($tar['por_responsavel'] as $l => $t) {
        if (!isset($m[$l])) {
            if (!isset($f['por_login'][$l])) {
                continue;
            }
            $m[$l] = $base();
        }
        $m[$l]['tarefas_concluidas'] += $t['concluidas'];
        $m[$l]['tarefas_no_prazo'] += $t['no_prazo'];
        $m[$l]['tarefas_abertas'] += $t['abertas'];
        $m[$l]['tarefas_atrasadas'] += $t['atrasadas'];
    }
    // Trilha de auditoria
    try {
        aud_estrutura();
        $soma(pa_all("SELECT usuario u, COUNT(*) n, SUM(categoria IN ('exclusao','estorno','cancelamento')) e, SUM(resultado = 'falha') fa,
                             SUM(resultado = 'negado') ng, SUM(fora_expediente) fe
                        FROM atlas_auditoria WHERE criado_em BETWEEN ? AND ? AND categoria <> 'acesso' GROUP BY usuario", [$ini, $fim]), 'u',
            ['n' => 'operacoes', 'e' => 'exclusoes', 'fa' => 'falhas', 'ng' => 'negados', 'fe' => 'fora_expediente']);
        $soma(pa_all("SELECT usuario u, COUNT(*) n FROM atlas_auditoria_navegacao WHERE criado_em BETWEEN ? AND ? GROUP BY usuario", [$ini, $fim]), 'u', ['n' => 'telas']);
    } catch (Throwable $e) {
    }
    // Acessos: logs_de_acesso (histórico de entradas do sistema)
    $soma(pa_all("SELECT usuario u, COUNT(*) n FROM logs_de_acesso WHERE data_hora BETWEEN ? AND ? GROUP BY usuario", [$ini, $fim]), 'u', ['n' => 'logins']);
    $dias = pa_dias_ativos($per);
    foreach ($dias as $l => $d) {
        if (isset($m[$l])) {
            $m[$l]['dias_ativos'] = $d;
        }
    }
    foreach (pa_all("SELECT usuario u, MAX(data_hora) d FROM logs_de_acesso GROUP BY usuario") as $r) {
        $l = pa_login_de($r['u']);
        if (isset($m[$l])) {
            $m[$l]['ultimo_acesso'] = $r['d'];
        }
    }

    // Pontuação: cada dimensão normalizada pelo maior valor da equipe (0–100)
    $dims = [
        'producao'     => function ($x) { return $x['atos_valor']; },
        'atendimento'  => function ($x) { return $x['os_criadas'] + $x['pedidos']; },
        'financeiro'   => function ($x) { return $x['recebido']; },
        'tarefas'      => function ($x) { return $x['tarefas_concluidas']; },
        'pontualidade' => function ($x) { $t = $x['tarefas_concluidas']; return $t > 0 ? $x['tarefas_no_prazo'] / $t * 100 : ($x['tarefas_atrasadas'] > 0 ? 0 : null); },
        'qualidade'    => function ($x) { $op = max(1, $x['operacoes'] + $x['os_criadas']); return max(0, 100 - ($x['falhas'] + $x['os_canceladas'] + $x['negados']) / $op * 100); },
    ];
    $max = [];
    foreach ($dims as $k => $fn) {
        $max[$k] = 0;
        foreach ($m as $x) {
            $max[$k] = max($max[$k], (float) $fn($x));
        }
    }
    $pesos = ['producao' => 0.28, 'atendimento' => 0.18, 'financeiro' => 0.16, 'tarefas' => 0.16, 'pontualidade' => 0.12, 'qualidade' => 0.10];
    $lista = [];
    foreach ($m as $l => $x) {
        $radar = [];
        $score = 0;
        $pesoUsado = 0;
        foreach ($dims as $k => $fn) {
            $v = $fn($x);
            if ($v === null) {
                $radar[$k] = null;
                continue;
            }
            $n = in_array($k, ['pontualidade', 'qualidade'], true) ? (float) $v : ($max[$k] > 0 ? $v / $max[$k] * 100 : 0);
            $radar[$k] = round($n, 1);
            $score += $n * $pesos[$k];
            $pesoUsado += $pesos[$k];
        }
        $temAtividade = ($x['atos_qtd'] + $x['os_criadas'] + $x['recebimentos'] + $x['pedidos'] + $x['tarefas_concluidas'] + $x['tarefas_abertas']) > 0;
        if (!$temAtividade) {
            $score = 0;
            $pesoUsado = 1;
        }
        $info = $f['por_login'][$l] ?? null;
        $lista[] = $x + [
            'usuario' => $l, 'nome' => $info['nome_completo'] ?? $l, 'cargo' => $info['cargo'] ?? '', 'nivel' => $info['nivel_de_acesso'] ?? '',
            'tfa' => (int) ($info['tfa_enabled'] ?? 0), 'radar' => $radar,
            'pontuacao' => $pesoUsado > 0 ? (int) round($score / $pesoUsado) : 0,
            'os_valor' => pa_num($x['os_valor']), 'atos_valor' => pa_num($x['atos_valor']), 'recebido' => pa_num($x['recebido']),
        ];
    }
    usort($lista, function ($a, $b) {
        return $b['pontuacao'] <=> $a['pontuacao'] ?: $b['atos_valor'] <=> $a['atos_valor'];
    });
    return ['membros' => $lista, 'mapa_calor' => pa_mapa_calor($per)];
}

/** Dias com alguma atividade registrada, por funcionário. */
function pa_dias_ativos(array $per): array
{
    $ini = $per['ini_dt'];
    $fim = $per['fim_dt'];
    $partes = [
        "SELECT criado_por u, DATE(data_criacao) d FROM ordens_de_servico WHERE data_criacao BETWEEN :i1 AND :f1",
        "SELECT funcionario u, DATE(data) d FROM atos_liquidados WHERE data BETWEEN :i2 AND :f2",
        "SELECT funcionario u, DATE(data_pagamento) d FROM pagamento_os WHERE data_pagamento BETWEEN :i3 AND :f3",
        "SELECT usuario u, DATE(data_hora) d FROM logs_de_acesso WHERE data_hora BETWEEN :i4 AND :f4",
    ];
    $p = [':i1' => $ini, ':f1' => $fim, ':i2' => $ini, ':f2' => $fim, ':i3' => $ini, ':f3' => $fim, ':i4' => $ini, ':f4' => $fim];
    try {
        aud_estrutura();
        $partes[] = "SELECT usuario u, DATE(criado_em) d FROM atlas_auditoria WHERE criado_em BETWEEN :i5 AND :f5";
        $partes[] = "SELECT usuario u, DATE(criado_em) d FROM atlas_auditoria_navegacao WHERE criado_em BETWEEN :i6 AND :f6";
        $p += [':i5' => $ini, ':f5' => $fim, ':i6' => $ini, ':f6' => $fim];
    } catch (Throwable $e) {
    }
    $st = pa_db()->prepare('SELECT u, d FROM (' . implode(' UNION ', array_map(function ($s) {
        return "SELECT CONVERT(u USING utf8mb4) COLLATE utf8mb4_unicode_ci u, d FROM ($s) z";
    }, $partes)) . ') x GROUP BY u, d');
    $st->execute($p);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $l = pa_login_de($r['u']);
        $out[$l] = ($out[$l] ?? 0) + 1;
    }
    return $out;
}

/**
 * Atividade da serventia por dia da semana × hora (0–23).
 * Usa a trilha de auditoria e, para o período anterior a ela, os carimbos
 * de data/hora das O.S., atos, pagamentos e entradas no sistema.
 */
function pa_mapa_calor(array $per, ?string $usuario = null): array
{
    $ini = $per['ini_dt'];
    $fim = $per['fim_dt'];
    $grade = array_fill(0, 7, array_fill(0, 24, 0));
    $fontes = [
        ['ordens_de_servico', 'data_criacao', 'criado_por'],
        ['atos_liquidados', 'data', 'funcionario'],
        ['pagamento_os', 'data_pagamento', 'funcionario'],
        ['logs_de_acesso', 'data_hora', 'usuario'],
        ['atlas_auditoria', 'criado_em', 'usuario'],
        ['atlas_auditoria_navegacao', 'criado_em', 'usuario'],
    ];
    foreach ($fontes as [$t, $c, $u]) {
        if (!pa_tabela_existe($t)) {
            continue;
        }
        $where = "$c BETWEEN ? AND ?";
        $p = [$ini, $fim];
        if ($usuario !== null) {
            $where .= " AND ($u = ? OR $u = ?)";
            $p[] = $usuario;
            $p[] = pa_nome_de($usuario);
        }
        foreach (pa_all("SELECT WEEKDAY($c) d, HOUR($c) h, COUNT(*) n FROM $t WHERE $where GROUP BY d, h", $p) as $r) {
            $grade[(int) $r['d']][(int) $r['h']] += (int) $r['n'];
        }
    }
    return $grade;
}

/* ============================ Serventia ============================ */

function pa_serventia(array $per): array
{
    $info = pa_one('SELECT razao_social, cidade, cns FROM cadastro_serventia LIMIT 1') ?: [];
    $ini = $per['ini_dt'];
    $fim = $per['fim_dt'];
    // Tempo entre a criação da O.S. e a primeira liquidação
    $tl = pa_one("SELECT AVG(TIMESTAMPDIFF(HOUR, o.data_criacao, a.primeira)) h, COUNT(*) n
                    FROM ordens_de_servico o
                    JOIN (SELECT ordem_servico_id id, MIN(data) primeira FROM atos_liquidados GROUP BY 1) a ON a.id = o.id
                   WHERE o.data_criacao BETWEEN ? AND ? AND o.status <> 'Cancelado'", [$ini, $fim]);
    $osPer = (int) pa_val("SELECT COUNT(*) FROM ordens_de_servico WHERE data_criacao BETWEEN ? AND ? AND status <> 'Cancelado'", [$ini, $fim]);
    $osLiq = (int) pa_val("SELECT COUNT(DISTINCT o.id) FROM ordens_de_servico o JOIN atos_liquidados a ON a.ordem_servico_id = o.id
                            WHERE o.data_criacao BETWEEN ? AND ? AND o.status <> 'Cancelado'", [$ini, $fim]);
    $osComPag = (int) pa_val("SELECT COUNT(DISTINCT o.id) FROM ordens_de_servico o JOIN pagamento_os p ON p.ordem_de_servico_id = o.id
                              WHERE o.data_criacao BETWEEN ? AND ? AND o.status <> 'Cancelado'", [$ini, $fim]);
    $tar = pa_tarefas($per);

    $uso = [];
    try {
        aud_estrutura();
        foreach (pa_all("SELECT modulo, COUNT(*) n FROM (
                           SELECT modulo FROM atlas_auditoria WHERE criado_em BETWEEN ? AND ? AND categoria <> 'acesso'
                           UNION ALL SELECT modulo FROM atlas_auditoria_navegacao WHERE criado_em BETWEEN ? AND ?
                         ) x GROUP BY modulo ORDER BY n DESC LIMIT 14", [$ini, $fim, $ini, $fim]) as $r) {
            $uso[] = ['modulo' => $r['modulo'], 'rotulo' => aud_modulo_rotulo($r['modulo']), 'n' => (int) $r['n']];
        }
    } catch (Throwable $e) {
    }
    $atribs = pa_tabela_existe('ordens_de_servico_itens') ? pa_all("SELECT LEFT(i.ato, LOCATE('.', CONCAT(i.ato,'.')) - 1) grupo, COUNT(*) n, SUM(i.total) v
                         FROM ordens_de_servico_itens i JOIN ordens_de_servico o ON o.id = i.ordem_servico_id
                        WHERE o.data_criacao BETWEEN ? AND ? AND o.status <> 'Cancelado' GROUP BY grupo ORDER BY v DESC LIMIT 8", [$ini, $fim]) : [];

    return [
        'serventia' => ['nome' => pa_utf8($info['razao_social'] ?? ''), 'cidade' => pa_utf8($info['cidade'] ?? ''), 'cns' => $info['cns'] ?? ''],
        'os' => [
            'criadas' => $osPer, 'com_pagamento' => $osComPag, 'liquidadas' => $osLiq,
            'taxa_pagamento' => $osPer ? round($osComPag / $osPer * 100, 1) : null,
            'taxa_liquidacao' => $osPer ? round($osLiq / $osPer * 100, 1) : null,
            'horas_ate_liquidar' => $tl && $tl['h'] !== null ? round((float) $tl['h'], 1) : null,
        ],
        'tarefas' => $tar,
        'uso_modulos' => $uso,
        'grupos_atos' => array_map(function ($r) {
            return ['grupo' => 'Tabela ' . $r['grupo'], 'n' => (int) $r['n'], 'v' => pa_num($r['v'])];
        }, $atribs),
    ];
}
