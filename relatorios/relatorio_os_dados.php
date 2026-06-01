<?php
/**
 * relatorio_os_dados.php
 * Backend AJAX (JSON) do Relatório de Ordens de Serviço.
 *
 * Recebe os filtros via GET e devolve todas as agregações já calculadas
 * no servidor (KPIs, faturamento por atribuição, desempenho por funcionário,
 * ranking de atos mais liquidados e a série temporal).
 *
 * Fonte de dados: atos_liquidados + atos_manuais_liquidados (atos efetivamente
 * liquidados, que é o que gera faturamento). A "atribuição" é derivada dos
 * 2 primeiros dígitos do código do ato (tabela de emolumentos):
 *   13 -> Notas | 14 -> Registro Civil | 15 -> RTD e RCPJ
 *   16 -> Registro de Imóveis | 17 -> Protesto | 18 -> Contratos Marítimos
 */

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=utf-8');

/* ----------------------------------------------------------------------
 * Controle de acesso (versão JSON do checar_acesso_de_administrador.php)
 * -------------------------------------------------------------------- */
$username = $_SESSION['username'];
$stmtAcc = $conn->prepare("SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ?");
$stmtAcc->bind_param("s", $username);
$stmtAcc->execute();
$accUser = $stmtAcc->get_result()->fetch_assoc();
$stmtAcc->close();

$nivel = $accUser['nivel_de_acesso'] ?? '';
$adicional = $accUser['acesso_adicional'] ?? '';
$temFluxo = in_array('Fluxo de Caixa', array_map('trim', explode(',', $adicional)));
if ($nivel !== 'administrador' && !$temFluxo) {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado.']);
    exit;
}

/* ----------------------------------------------------------------------
 * Leitura e normalização dos filtros
 * -------------------------------------------------------------------- */
$preset        = $_GET['preset']        ?? 'mes';
$data_inicio   = $_GET['data_inicio']   ?? '';
$data_fim      = $_GET['data_fim']      ?? '';
$status        = $_GET['status']        ?? 'todos';
$atribuicao    = $_GET['atribuicao']    ?? 'todas';
$funcionario   = $_GET['funcionario']   ?? 'todos';
$ato_filtro    = trim($_GET['ato']      ?? '');
$granularidade = $_GET['granularidade'] ?? 'dia';
$dep_periodo   = ($_GET['dep_usar_periodo'] ?? '0') === '1'; // aplicar período à data de criação da O.S.

/* Resolve o intervalo de datas a partir do preset. Retorna [inicio, fim]
 * (Y-m-d) ou [null, null] quando não há filtro de data. */
function resolverPeriodo($preset, $data_inicio, $data_fim) {
    $hoje = new DateTime('today');
    switch ($preset) {
        case 'hoje':
            return [$hoje->format('Y-m-d'), $hoje->format('Y-m-d')];
        case 'ontem':
            $o = (clone $hoje)->modify('-1 day');
            return [$o->format('Y-m-d'), $o->format('Y-m-d')];
        case '7dias':
            $i = (clone $hoje)->modify('-6 days');
            return [$i->format('Y-m-d'), $hoje->format('Y-m-d')];
        case 'semana': // semana atual (segunda a domingo)
            $i = (clone $hoje)->modify('monday this week');
            $f = (clone $i)->modify('+6 days');
            return [$i->format('Y-m-d'), $f->format('Y-m-d')];
        case 'mes': // mês atual
            $i = new DateTime('first day of this month');
            $f = new DateTime('last day of this month');
            return [$i->format('Y-m-d'), $f->format('Y-m-d')];
        case 'mes_passado':
            $i = new DateTime('first day of last month');
            $f = new DateTime('last day of last month');
            return [$i->format('Y-m-d'), $f->format('Y-m-d')];
        case 'ano':
            return [$hoje->format('Y') . '-01-01', $hoje->format('Y') . '-12-31'];
        case 'todos': // sem filtro de data (por status independente da data)
            return [null, null];
        case 'custom':
        default:
            $i = ($data_inicio !== '') ? $data_inicio : null;
            $f = ($data_fim !== '')    ? $data_fim    : null;
            return [$i, $f];
    }
}

list($ini, $fim) = resolverPeriodo($preset, $data_inicio, $data_fim);

/* Prefixos de atribuição */
$mapaAtribuicao = [
    'notas'     => '13',
    'civil'     => '14',
    'rtd'       => '15',
    'imoveis'   => '16',
    'protesto'  => '17',
    'maritimos' => '18',
];

/* ----------------------------------------------------------------------
 * Construção do WHERE compartilhado (mesma ordem de binds em toda query)
 * -------------------------------------------------------------------- */
$where  = [];
$types  = '';
$params = [];

if ($ini !== null) {
    $where[] = 'al.data >= ?';
    $types  .= 's';
    $params[] = $ini . ' 00:00:00';
}
if ($fim !== null) {
    // fim + 1 dia para incluir o dia inteiro
    $fimMais1 = (new DateTime($fim))->modify('+1 day')->format('Y-m-d');
    $where[] = 'al.data < ?';
    $types  .= 's';
    $params[] = $fimMais1 . ' 00:00:00';
}
if ($status !== 'todos' && $status !== '') {
    $where[] = 'al.status = ?';
    $types  .= 's';
    $params[] = $status;
}
if ($atribuicao !== 'todas' && isset($mapaAtribuicao[$atribuicao])) {
    $where[] = 'LEFT(al.ato, 2) = ?';
    $types  .= 's';
    $params[] = $mapaAtribuicao[$atribuicao];
}
if ($funcionario !== 'todos' && $funcionario !== '') {
    $where[] = 'TRIM(al.funcionario) = ?';
    $types  .= 's';
    $params[] = trim($funcionario);
}
if ($ato_filtro !== '') {
    $where[] = 'al.ato LIKE ?';
    $types  .= 's';
    $params[] = $ato_filtro . '%';
}

$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

/* Subconsulta unificada dos atos liquidados (automáticos + manuais) */
$fonte = "
    (
        SELECT ato, quantidade_liquidada, emolumentos, total, funcionario, status, data, ordem_servico_id
        FROM atos_liquidados
        UNION ALL
        SELECT ato, quantidade_liquidada, emolumentos, total, funcionario, status, data, ordem_servico_id
        FROM atos_manuais_liquidados
    ) AS al
";

/* Expressão CASE da atribuição (reaproveitada em vários SELECTs) */
$attrCase = "
    CASE LEFT(al.ato, 2)
        WHEN '13' THEN 'Notas'
        WHEN '14' THEN 'Registro Civil'
        WHEN '15' THEN 'RTD e RCPJ'
        WHEN '16' THEN 'Registro de Imóveis'
        WHEN '17' THEN 'Protesto'
        WHEN '18' THEN 'Contratos Marítimos'
        ELSE 'Outros'
    END
";

/* Helper: executa uma query preparada com os binds compartilhados */
function runQuery($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro SQL: ' . $conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/* Expressão de granularidade da série temporal (whitelist) */
switch ($granularidade) {
    case 'mes':
        $grpExpr = "DATE_FORMAT(al.data, '%Y-%m')";
        break;
    case 'semana':
        $grpExpr = "DATE_FORMAT(al.data, '%x-S%v')"; // ano-Ssemana ISO
        break;
    case 'dia':
    default:
        $grpExpr = "DATE_FORMAT(al.data, '%Y-%m-%d')";
        break;
}

try {
    /* ---------------- KPIs gerais ---------------- */
    $sqlKpis = "
        SELECT
            COALESCE(SUM(al.quantidade_liquidada), 0) AS qtd_atos,
            COALESCE(SUM(al.emolumentos), 0)          AS emolumentos,
            COALESCE(SUM(al.total), 0)                AS total,
            COUNT(DISTINCT TRIM(al.funcionario))      AS qtd_funcionarios,
            COUNT(DISTINCT al.ordem_servico_id)       AS qtd_os
        FROM $fonte
        $whereSql
    ";
    $kpis = runQuery($conn, $sqlKpis, $types, $params);
    $kpis = $kpis[0] ?? ['qtd_atos'=>0,'emolumentos'=>0,'total'=>0,'qtd_funcionarios'=>0,'qtd_os'=>0];

    /* ---------------- Faturamento por atribuição ---------------- */
    $sqlAtrib = "
        SELECT
            $attrCase AS atribuicao,
            COALESCE(SUM(al.quantidade_liquidada), 0) AS qtd,
            COALESCE(SUM(al.emolumentos), 0)          AS emolumentos,
            COALESCE(SUM(al.total), 0)                AS total
        FROM $fonte
        $whereSql
        GROUP BY $attrCase
        ORDER BY emolumentos DESC
    ";
    $porAtribuicao = runQuery($conn, $sqlAtrib, $types, $params);

    /* ---------------- Desempenho por funcionário ---------------- */
    $sqlFunc = "
        SELECT
            TRIM(al.funcionario) AS usuario,
            COALESCE(NULLIF(f.nome_completo, ''), TRIM(al.funcionario)) AS nome,
            COALESCE(SUM(al.quantidade_liquidada), 0) AS qtd,
            COALESCE(SUM(al.emolumentos), 0)          AS emolumentos,
            COALESCE(SUM(al.total), 0)                AS total
        FROM $fonte
        LEFT JOIN funcionarios f ON f.usuario = TRIM(al.funcionario)
        $whereSql
        GROUP BY usuario, nome
        ORDER BY emolumentos DESC
    ";
    $porFuncionario = runQuery($conn, $sqlFunc, $types, $params);

    /* ---------------- Atos mais liquidados (ranking por código) ---------------- */
    $sqlAtos = "
        SELECT
            al.ato AS ato,
            COALESCE(NULLIF(te.DESCRICAO, ''), '(sem descrição)') AS descricao,
            $attrCase AS atribuicao,
            COALESCE(SUM(al.quantidade_liquidada), 0) AS qtd,
            COALESCE(SUM(al.emolumentos), 0)          AS emolumentos,
            COALESCE(SUM(al.total), 0)                AS total
        FROM $fonte
        LEFT JOIN tabela_emolumentos te ON te.ATO = al.ato
        $whereSql
        GROUP BY al.ato, descricao, atribuicao
        ORDER BY qtd DESC
    ";
    $porAto = runQuery($conn, $sqlAtos, $types, $params);

    /* ---------------- Série temporal ---------------- */
    $sqlSerie = "
        SELECT
            $grpExpr AS periodo,
            COALESCE(SUM(al.quantidade_liquidada), 0) AS qtd,
            COALESCE(SUM(al.emolumentos), 0)          AS emolumentos,
            COALESCE(SUM(al.total), 0)                AS total
        FROM $fonte
        $whereSql
        GROUP BY periodo
        ORDER BY periodo ASC
    ";
    $serie = runQuery($conn, $sqlSerie, $types, $params);

    /* ---------------- Depósito prévio (saldo por O.S. ainda não consumido) ----------------
       Saldo (cumulativo, de toda a vida da O.S.) =
            SUM(pagamentos)  -  SUM(atos liquidados auto + manuais)  -  SUM(devoluções)
       Calculado SEM recorte de data por padrão (senão o consumo ficaria parcial e o
       saldo, incorreto). Opcionalmente filtra pela data de CRIAÇÃO da O.S. */
    $whereDep   = ["os.status <> 'Cancelado'"];
    $typesDep   = '';
    $paramsDep  = [];
    if ($dep_periodo && $ini !== null) {
        $whereDep[] = 'os.data_criacao >= ?';
        $typesDep  .= 's';
        $paramsDep[] = $ini . ' 00:00:00';
    }
    if ($dep_periodo && $fim !== null) {
        $fimMais1Dep = (new DateTime($fim))->modify('+1 day')->format('Y-m-d');
        $whereDep[] = 'os.data_criacao < ?';
        $typesDep  .= 's';
        $paramsDep[] = $fimMais1Dep . ' 00:00:00';
    }
    $whereDepSql = 'WHERE ' . implode(' AND ', $whereDep);

    $sqlDep = "
        SELECT * FROM (
            SELECT
                os.id, os.cliente, os.cpf_cliente, os.data_criacao, os.total_os, os.status,
                COALESCE(p.dep, 0)                              AS depositado,
                COALESCE(a.cons, 0) + COALESCE(am.cons, 0)      AS consumido,
                COALESCE(d.dev, 0)                              AS devolvido,
                COALESCE(p.dep, 0)
                  - (COALESCE(a.cons, 0) + COALESCE(am.cons, 0))
                  - COALESCE(d.dev, 0)                          AS saldo
            FROM ordens_de_servico os
            INNER JOIN (
                SELECT ordem_de_servico_id, SUM(total_pagamento) AS dep
                FROM pagamento_os GROUP BY ordem_de_servico_id
            ) p  ON p.ordem_de_servico_id = os.id
            LEFT JOIN (
                SELECT ordem_servico_id, SUM(total) AS cons
                FROM atos_liquidados GROUP BY ordem_servico_id
            ) a  ON a.ordem_servico_id = os.id
            LEFT JOIN (
                SELECT ordem_servico_id, SUM(total) AS cons
                FROM atos_manuais_liquidados GROUP BY ordem_servico_id
            ) am ON am.ordem_servico_id = os.id
            LEFT JOIN (
                SELECT ordem_de_servico_id, SUM(total_devolucao) AS dev
                FROM devolucao_os GROUP BY ordem_de_servico_id
            ) d  ON d.ordem_de_servico_id = os.id
            $whereDepSql
        ) t
        WHERE t.saldo > 0.009
        ORDER BY t.saldo DESC
    ";
    $listaDep = runQuery($conn, $sqlDep, $typesDep, $paramsDep);

    // Totais do depósito prévio
    $depTotais = ['total_saldo'=>0,'total_depositado'=>0,'total_consumido'=>0,'total_devolvido'=>0,'qtd_os'=>0];
    foreach ($listaDep as $row) {
        $depTotais['total_saldo']       += (float)$row['saldo'];
        $depTotais['total_depositado']  += (float)$row['depositado'];
        $depTotais['total_consumido']   += (float)$row['consumido'];
        $depTotais['total_devolvido']   += (float)$row['devolvido'];
        $depTotais['qtd_os']++;
    }

    /* ---------------- Repasse a credores (Protesto) ----------------
       Valores recebidos e repassados DIRETAMENTE ao credor (protesto).
       NÃO compõem o faturamento da serventia — tabela própria repasse_credor.
       Filtro de data sobre data_repasse; respeita o funcionário selecionado. */
    $whereRep  = ["rc.status = 'ativo'"];
    $typesRep  = '';
    $paramsRep = [];
    if ($ini !== null) {
        $whereRep[] = 'rc.data_repasse >= ?';
        $typesRep  .= 's';
        $paramsRep[] = $ini . ' 00:00:00';
    }
    if ($fim !== null) {
        $fimMais1Rep = (new DateTime($fim))->modify('+1 day')->format('Y-m-d');
        $whereRep[] = 'rc.data_repasse < ?';
        $typesRep  .= 's';
        $paramsRep[] = $fimMais1Rep . ' 00:00:00';
    }
    if ($funcionario !== 'todos' && $funcionario !== '') {
        $whereRep[] = 'TRIM(rc.funcionario) = ?';
        $typesRep  .= 's';
        $paramsRep[] = trim($funcionario);
    }
    $whereRepSql = 'WHERE ' . implode(' AND ', $whereRep);

    $sqlRep = "
        SELECT rc.id, rc.ordem_de_servico_id, rc.cliente,
               TRIM(rc.funcionario) AS funcionario,
               rc.data_repasse, rc.total_repasse, rc.forma_repasse
        FROM repasse_credor rc
        $whereRepSql
        ORDER BY rc.data_repasse DESC
    ";
    $listaRep = runQuery($conn, $sqlRep, $typesRep, $paramsRep);

    $repTotais = ['total'=>0,'qtd'=>0];
    $repForma = []; $repFunc = [];
    foreach ($listaRep as $row) {
        $v = (float)$row['total_repasse'];
        $repTotais['total'] += $v;
        $repTotais['qtd']++;
        $fk = $row['forma_repasse'] ?: '—';
        $repForma[$fk] = ($repForma[$fk] ?? 0) + $v;
        $rfk = $row['funcionario'] ?: '—';
        $repFunc[$rfk] = ($repFunc[$rfk] ?? 0) + $v;
    }
    $repFormaArr = [];
    foreach ($repForma as $k=>$v) $repFormaArr[] = ['forma'=>$k,'total'=>$v];
    $repFuncArr = [];
    foreach ($repFunc as $k=>$v) $repFuncArr[] = ['funcionario'=>$k,'total'=>$v];
    usort($repFuncArr, function($a,$b){ return ($b['total'] <=> $a['total']); });

    /* ---------------- Resposta ---------------- */
    echo json_encode([
        'ok'            => true,
        'periodo'       => ['inicio' => $ini, 'fim' => $fim, 'preset' => $preset],
        'kpis'          => $kpis,
        'porAtribuicao' => $porAtribuicao,
        'porFuncionario'=> $porFuncionario,
        'porAto'        => $porAto,
        'serie'         => $serie,
        'depositoPrevio'=> [
            'usa_periodo' => $dep_periodo,
            'totais'      => $depTotais,
            'lista'       => $listaDep,
        ],
        'repasseCredor' => [
            'totais'        => $repTotais,
            'porForma'      => $repFormaArr,
            'porFuncionario'=> $repFuncArr,
            'lista'         => $listaRep,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
