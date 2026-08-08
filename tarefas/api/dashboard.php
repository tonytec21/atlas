<?php
/**
 * Atlas · Tarefas — indicadores do painel.
 *
 * Devolve os números que aparecem no topo da tela e as séries dos gráficos.
 * Respeita a mesma regra de visibilidade da listagem: quem não tem acesso
 * total só conta as próprias tarefas.
 */

require_once __DIR__ . '/../core/bootstrap.php';
api_iniciar();

$vis = filtro_visibilidade('t');
$w   = $vis['sql'];
$p   = $vis['params'];

$encerrados = tarefas_status_encerrados();
$inEnc = implode(',', array_fill(0, count($encerrados), '?'));

/* ------------------------- Cartões ------------------------------- */

$abertas = (int) db_valor(
    "SELECT COUNT(*) FROM tarefas t WHERE t.status NOT IN ($inEnc) $w",
    array_merge($encerrados, $p),
    0
);

$vencidas = (int) db_valor(
    "SELECT COUNT(*) FROM tarefas t
      WHERE t.data_limite < NOW() AND t.status NOT IN ($inEnc) $w",
    array_merge($encerrados, $p),
    0
);

$hoje = (int) db_valor(
    "SELECT COUNT(*) FROM tarefas t
      WHERE DATE(t.data_limite) = CURDATE() AND t.status NOT IN ($inEnc) $w",
    array_merge($encerrados, $p),
    0
);

$semana = (int) db_valor(
    "SELECT COUNT(*) FROM tarefas t
      WHERE t.data_limite BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        AND t.status NOT IN ($inEnc) $w",
    array_merge($encerrados, $p),
    0
);

$concluidasMes = (int) db_valor(
    "SELECT COUNT(*) FROM tarefas t
      WHERE t.data_conclusao >= DATE_FORMAT(CURDATE(), '%Y-%m-01') $w",
    $p,
    0
);

$u = usuario_atual();
$minhas = (int) db_valor(
    "SELECT COUNT(*) FROM tarefas t
      WHERE (t.funcionario_responsavel = ? OR t.revisor = ?)
        AND t.status NOT IN ($inEnc)",
    array_merge(array($u['nome'], $u['nome']), $encerrados),
    0
);

$aguardandoRetirada = (int) db_valor(
    "SELECT COUNT(*) FROM tarefas t WHERE t.status = 'Aguardando Retirada' $w",
    $p,
    0
);

/* --------------------- Tempo médio de conclusão ------------------ */
$tempoMedio = db_valor(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, t.data_criacao, t.data_conclusao))
       FROM tarefas t
      WHERE t.data_conclusao IS NOT NULL
        AND t.data_criacao IS NOT NULL
        AND t.data_conclusao >= DATE_SUB(NOW(), INTERVAL 90 DAY) $w",
    $p,
    null
);
$tempoMedioHoras = $tempoMedio === null ? null : round((float) $tempoMedio, 1);

/* ------------------- Cumprimento de prazo ------------------------ */
$noPrazo = (int) db_valor(
    "SELECT COUNT(*) FROM tarefas t
      WHERE t.data_conclusao IS NOT NULL AND t.data_limite IS NOT NULL
        AND t.data_conclusao <= t.data_limite
        AND t.data_conclusao >= DATE_SUB(NOW(), INTERVAL 90 DAY) $w",
    $p,
    0
);
$totalConcluidas90 = (int) db_valor(
    "SELECT COUNT(*) FROM tarefas t
      WHERE t.data_conclusao IS NOT NULL AND t.data_limite IS NOT NULL
        AND t.data_conclusao >= DATE_SUB(NOW(), INTERVAL 90 DAY) $w",
    $p,
    0
);
$taxaPrazo = $totalConcluidas90 > 0 ? round($noPrazo * 100 / $totalConcluidas90) : null;

/* ------------------------- Séries -------------------------------- */

$porStatus = db_all(
    "SELECT t.status, COUNT(*) AS total
       FROM tarefas t
      WHERE t.status NOT IN ($inEnc) $w
      GROUP BY t.status
      ORDER BY total DESC",
    array_merge($encerrados, $p)
);
foreach ($porStatus as &$s) {
    $s['total'] = (int) $s['total'];
    $s['cor']   = cor_status($s['status']);
}
unset($s);

$porPrioridade = db_all(
    "SELECT t.nivel_de_prioridade AS prioridade, COUNT(*) AS total
       FROM tarefas t
      WHERE t.status NOT IN ($inEnc) $w
      GROUP BY t.nivel_de_prioridade",
    array_merge($encerrados, $p)
);
$prio = tarefas_prioridades();
foreach ($porPrioridade as &$s) {
    $s['total'] = (int) $s['total'];
    $s['cor']   = isset($prio[$s['prioridade']]) ? $prio[$s['prioridade']]['cor'] : '#94a3b8';
}
unset($s);

$porResponsavel = db_all(
    "SELECT COALESCE(NULLIF(t.funcionario_responsavel,''), 'Sem responsável') AS responsavel,
            COUNT(*) AS total,
            SUM(CASE WHEN t.data_limite < NOW() THEN 1 ELSE 0 END) AS vencidas
       FROM tarefas t
      WHERE t.status NOT IN ($inEnc) $w
      GROUP BY responsavel
      ORDER BY total DESC
      LIMIT 12",
    array_merge($encerrados, $p)
);
foreach ($porResponsavel as &$s) {
    $s['total']    = (int) $s['total'];
    $s['vencidas'] = (int) $s['vencidas'];
}
unset($s);

$porCategoria = db_all(
    "SELECT COALESCE(c.titulo, 'Sem categoria') AS categoria, COUNT(*) AS total
       FROM tarefas t
       LEFT JOIN categorias c ON t.categoria = c.id
      WHERE t.status NOT IN ($inEnc) $w
      GROUP BY categoria
      ORDER BY total DESC
      LIMIT 10",
    array_merge($encerrados, $p)
);
foreach ($porCategoria as &$s) {
    $s['total'] = (int) $s['total'];
}
unset($s);

/* Movimentação dos últimos 30 dias: criadas x concluídas por dia. */
$criadas = db_all(
    "SELECT DATE(t.data_criacao) AS dia, COUNT(*) AS total
       FROM tarefas t
      WHERE t.data_criacao >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) $w
      GROUP BY dia",
    $p
);
$concluidas = db_all(
    "SELECT DATE(t.data_conclusao) AS dia, COUNT(*) AS total
       FROM tarefas t
      WHERE t.data_conclusao >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) $w
      GROUP BY dia",
    $p
);

$mapaCriadas = array();
foreach ($criadas as $l) {
    $mapaCriadas[$l['dia']] = (int) $l['total'];
}
$mapaConcluidas = array();
foreach ($concluidas as $l) {
    $mapaConcluidas[$l['dia']] = (int) $l['total'];
}

$serie = array();
for ($i = 29; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i day"));
    $serie[] = array(
        'dia'        => $dia,
        'rotulo'     => date('d/m', strtotime($dia)),
        'criadas'    => isset($mapaCriadas[$dia]) ? $mapaCriadas[$dia] : 0,
        'concluidas' => isset($mapaConcluidas[$dia]) ? $mapaConcluidas[$dia] : 0,
    );
}

/* ------------------- Tarefas que pedem atenção ------------------- */
$atencao = db_all(
    "SELECT t.id, t.token, t.titulo, t.status, t.nivel_de_prioridade,
            t.data_limite, t.funcionario_responsavel
       FROM tarefas t
      WHERE t.status NOT IN ($inEnc)
        AND t.data_limite IS NOT NULL
        AND t.data_limite <= DATE_ADD(NOW(), INTERVAL 3 DAY) $w
      ORDER BY t.data_limite ASC
      LIMIT 8",
    array_merge($encerrados, $p)
);
foreach ($atencao as &$t) {
    $sit = situacao_prazo($t['data_limite'], $t['status']);
    $t['situacao']        = $sit['codigo'];
    $t['situacao_rotulo'] = $sit['rotulo'];
    $t['data_limite_br']  = data_br($t['data_limite']);
}
unset($t);

responder_ok(array(
    'cartoes' => array(
        'abertas'             => $abertas,
        'vencidas'            => $vencidas,
        'hoje'                => $hoje,
        'semana'              => $semana,
        'minhas'              => $minhas,
        'concluidas_mes'      => $concluidasMes,
        'aguardando_retirada' => $aguardandoRetirada,
        'tempo_medio_horas'   => $tempoMedioHoras,
        'taxa_prazo'          => $taxaPrazo,
    ),
    'por_status'      => $porStatus,
    'por_prioridade'  => $porPrioridade,
    'por_responsavel' => $porResponsavel,
    'por_categoria'   => $porCategoria,
    'movimentacao'    => $serie,
    'atencao'         => $atencao,
    'usuario'         => array('nome' => $u['nome'], 've_tudo' => usuario_ve_tudo()),
));
