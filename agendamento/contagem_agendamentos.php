<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

/* ------------ filtros comuns ----------------- */
$clauses = [];

if (!empty($_GET['nome'])) {
    $v = $conn->real_escape_string($_GET['nome']);
    $clauses[] = "nome_solicitante LIKE '%$v%'";
}
if (!empty($_GET['servico'])) {
    $v = $conn->real_escape_string($_GET['servico']);
    $clauses[] = "servico LIKE '%$v%'";
}
if (!empty($_GET['status'])) {
    $v = $conn->real_escape_string($_GET['status']);
    $clauses[] = "status = '$v'";
}

/* ------------ período opcional ---------------- */
$inicio = $_GET['inicio'] ?? '';
$fim    = $_GET['fim']    ?? '';
if ($inicio && $fim) {
    $inicio = $conn->real_escape_string($inicio);
    $fim    = $conn->real_escape_string($fim);
    $clauses[] = "DATE(data_hora) BETWEEN '$inicio' AND '$fim'";
}

$whereSql = $clauses ? 'WHERE '.implode(' AND ', $clauses) : '';

/* ------------ totais gerais ------------------- */
$q = "SELECT
        COUNT(*)                                        AS total,
        SUM(status='concluido')                         AS concluidos,
        SUM(status IN ('ativo','reagendado'))           AS pendentes
      FROM agendamentos $whereSql";
$geral = $conn->query($q)->fetch_assoc();

/* ------------ auxiliares para mês / semana ----- */
$prefix = $whereSql ? $whereSql.' AND ' : 'WHERE ';

if ($inicio && $fim) {
    // quando há período, usamos o mesmo total
    $mes = $geral['total'];
    $semana = $geral['total'];
} else {
    $mesSql = "SELECT COUNT(*) AS c FROM agendamentos {$prefix}
               YEAR(data_hora)=YEAR(CURDATE()) AND MONTH(data_hora)=MONTH(CURDATE())";
    $mes = $conn->query($mesSql)->fetch_assoc()['c'] ?? 0;

    $semSql = "SELECT COUNT(*) AS c FROM agendamentos {$prefix}
               YEARWEEK(data_hora,1)=YEARWEEK(CURDATE(),1)";
    $semana = $conn->query($semSql)->fetch_assoc()['c'] ?? 0;
}

/* ------------ resposta ------------------------- */
echo json_encode([
    'mes'        => (int)$mes,
    'semana'     => (int)$semana,
    'concluidos' => (int)($geral['concluidos'] ?? 0),
    'pendentes'  => (int)($geral['pendentes']  ?? 0)
]);
