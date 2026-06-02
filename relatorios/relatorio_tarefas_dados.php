<?php
/**
 * relatorio_tarefas_dados.php
 * Backend AJAX (JSON) do Relatório de Tarefas — unifica DUAS fontes:
 *   1) tarefas            (sistema interno de tarefas)
 *   2) pedidos_certidao   (pedidos de certidão — também são tarefas)
 *
 * Normaliza os status num esquema comum (Pendente / Em andamento /
 * Concluída / Cancelada) e devolve KPIs, distribuições e a lista unificada.
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

/* ----------------------------- Filtros ----------------------------- */
$preset        = $_GET['preset']        ?? 'mes';
$data_inicio   = $_GET['data_inicio']   ?? '';
$data_fim      = $_GET['data_fim']      ?? '';
$fonte         = $_GET['fonte']         ?? 'todas';   // todas | tarefas | pedidos
$status        = $_GET['status']        ?? 'todos';   // todos | pendente | andamento | concluida | cancelada
$responsavel   = trim($_GET['responsavel'] ?? 'todos');
$prioridade    = $_GET['prioridade']    ?? 'todas';
$granularidade = $_GET['granularidade'] ?? 'dia';

function resolverPeriodo($preset, $di, $df) {
    $hoje = new DateTime('today');
    switch ($preset) {
        case 'hoje':        return [$hoje->format('Y-m-d'), $hoje->format('Y-m-d')];
        case 'ontem':       $o=(clone $hoje)->modify('-1 day'); return [$o->format('Y-m-d'),$o->format('Y-m-d')];
        case '7dias':       $i=(clone $hoje)->modify('-6 days'); return [$i->format('Y-m-d'),$hoje->format('Y-m-d')];
        case 'semana':      $i=(clone $hoje)->modify('monday this week'); $f=(clone $i)->modify('+6 days'); return [$i->format('Y-m-d'),$f->format('Y-m-d')];
        case 'mes':         return [(new DateTime('first day of this month'))->format('Y-m-d'),(new DateTime('last day of this month'))->format('Y-m-d')];
        case 'mes_passado': return [(new DateTime('first day of last month'))->format('Y-m-d'),(new DateTime('last day of last month'))->format('Y-m-d')];
        case 'ano':         return [$hoje->format('Y').'-01-01', $hoje->format('Y').'-12-31'];
        case 'todos':       return [null, null];
        case 'custom':
        default:            return [$di !== '' ? $di : null, $df !== '' ? $df : null];
    }
}
list($ini, $fim) = resolverPeriodo($preset, $data_inicio, $data_fim);
$fimMais1 = $fim !== null ? (new DateTime($fim))->modify('+1 day')->format('Y-m-d') : null;

/* Tradução / normalização de status */
function statusLabel($fonte, $orig) {
    $o = strtolower(trim($orig));
    if ($fonte === 'Tarefa') {
        if ($o === 'concluida' || $o === 'concluída') return 'Concluída';
        if ($o === 'cancelada') return 'Cancelada';
        if ($o === 'pendente')  return 'Pendente';
        return ucfirst($o ?: 'Pendente');
    }
    // Pedido de Certidão
    switch ($o) {
        case 'pendente':     return 'Pendente';
        case 'em_andamento': return 'Em andamento';
        case 'emitida':      return 'Emitida';
        case 'entregue':     return 'Entregue';
        case 'cancelada':    return 'Cancelada';
    }
    return ucfirst($o);
}
function statusNorm($label) {
    // agrupa para KPIs/gráficos
    $l = mb_strtolower(trim($label));
    if (in_array($label, ['Concluída','Emitida','Entregue'])) return 'Concluída';
    if (strpos($l, 'finaliz') !== false) return 'Concluída'; // ex.: "Finalizado sem prática do ato"
    if ($label === 'Em andamento') return 'Em andamento';
    if ($label === 'Cancelada')    return 'Cancelada';
    return 'Pendente';
}

$linhas = [];
$agora = new DateTime('now');

try {
    /* ----------------------------- TAREFAS ----------------------------- */
    if ($fonte === 'todas' || $fonte === 'tarefas') {
        $cond = []; $types=''; $params=[];
        if ($ini !== null) { $cond[]='t.data_criacao >= ?'; $types.='s'; $params[]=$ini.' 00:00:00'; }
        if ($fim !== null) { $cond[]='t.data_criacao < ?';  $types.='s'; $params[]=$fimMais1.' 00:00:00'; }
        $w = $cond ? ('WHERE '.implode(' AND ',$cond)) : '';
        $sql = "
            SELECT t.id, t.titulo,
                   COALESCE(NULLIF(c.titulo,''), t.categoria) AS categoria,
                   t.funcionario_responsavel AS responsavel,
                   t.status AS status_orig,
                   t.nivel_de_prioridade AS prioridade,
                   t.data_criacao, t.data_limite, t.data_conclusao
            FROM tarefas t
            LEFT JOIN categorias c ON c.id = t.categoria
            $w";
        $stmt = $conn->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $label = statusLabel('Tarefa', $r['status_orig']);
            $norm  = statusNorm($label);
            $atras = false;
            if (in_array($norm, ['Pendente','Em andamento']) && !empty($r['data_limite'])) {
                $atras = (new DateTime($r['data_limite'])) < $agora;
            }
            $linhas[] = [
                'fonte'        => 'Tarefa',
                'ref'          => '#'.$r['id'],
                'titulo'       => $r['titulo'] ?: '(sem título)',
                'categoria'    => $r['categoria'] ?: '—',
                'responsavel'  => trim($r['responsavel']) ?: '—',
                'status'       => $label,
                'status_norm'  => $norm,
                'prioridade'   => $r['prioridade'] ?: '—',
                'data_criacao' => $r['data_criacao'],
                'prazo'        => $r['data_limite'],
                'concluido_em' => $r['data_conclusao'],
                'atrasada'     => $atras,
            ];
        }
        $stmt->close();
    }

    /* ----------------------- PEDIDOS DE CERTIDÃO ----------------------- */
    if ($fonte === 'todas' || $fonte === 'pedidos') {
        $cond = []; $types=''; $params=[];
        if ($ini !== null) { $cond[]='p.criado_em >= ?'; $types.='s'; $params[]=$ini.' 00:00:00'; }
        if ($fim !== null) { $cond[]='p.criado_em < ?';  $types.='s'; $params[]=$fimMais1.' 00:00:00'; }
        $w = $cond ? ('WHERE '.implode(' AND ',$cond)) : '';
        $sql = "
            SELECT p.id, p.protocolo, p.tipo, p.atribuicao, p.requerente_nome,
                   COALESCE(NULLIF(p.atualizado_por,''), p.criado_por) AS responsavel,
                   p.status AS status_orig,
                   p.criado_em, p.atualizado_em
            FROM pedidos_certidao p
            $w";
        $stmt = $conn->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $label = statusLabel('Pedido', $r['status_orig']);
            $norm  = statusNorm($label);
            $titulo = trim(($r['tipo'] ?: 'Certidão').' — '.($r['requerente_nome'] ?: ''));
            $linhas[] = [
                'fonte'        => 'Pedido de Certidão',
                'ref'          => $r['protocolo'] ?: ('#'.$r['id']),
                'titulo'       => $titulo,
                'categoria'    => $r['tipo'] ?: ($r['atribuicao'] ?: '—'),
                'responsavel'  => trim($r['responsavel']) ?: '—',
                'status'       => $label,
                'status_norm'  => $norm,
                'prioridade'   => '—',
                'data_criacao' => $r['criado_em'],
                'prazo'        => null,
                'concluido_em' => ($norm === 'Concluída') ? $r['atualizado_em'] : null,
                'atrasada'     => false,
            ];
        }
        $stmt->close();
    }

    /* --------------------- Filtros pós-normalização --------------------- */
    $mapStatus = ['pendente'=>'Pendente','andamento'=>'Em andamento','concluida'=>'Concluída','cancelada'=>'Cancelada'];
    $linhas = array_values(array_filter($linhas, function($l) use ($status,$responsavel,$prioridade,$mapStatus){
        if ($status !== 'todos' && $status !== '') {
            if (isset($mapStatus[$status])) {
                // grupo amplo -> compara pelo status normalizado
                if ($l['status_norm'] !== $mapStatus[$status]) return false;
            } else {
                // status específico (rótulo exato) -> filtro preciso
                if (mb_strtolower($l['status']) !== mb_strtolower($status)) return false;
            }
        }
        if ($responsavel !== 'todos' && $responsavel !== '' && mb_strtolower($l['responsavel']) !== mb_strtolower($responsavel)) return false;
        if ($prioridade !== 'todas' && $l['prioridade'] !== $prioridade) return false;
        return true;
    }));

    /* ----------------------------- Agregações ----------------------------- */
    $kpis = ['total'=>0,'pendentes'=>0,'em_andamento'=>0,'concluidas'=>0,'canceladas'=>0,'atrasadas'=>0,'taxa_conclusao'=>0];
    $porStatus = ['Pendente'=>0,'Em andamento'=>0,'Concluída'=>0,'Cancelada'=>0];
    $porFonte  = ['Tarefa'=>0,'Pedido de Certidão'=>0];
    $fonteStatus = [
        'Tarefa'             => ['Pendente'=>0,'Em andamento'=>0,'Concluída'=>0,'Cancelada'=>0],
        'Pedido de Certidão' => ['Pendente'=>0,'Em andamento'=>0,'Concluída'=>0,'Cancelada'=>0],
    ];
    $porResp = []; $porCat = []; $serie = [];

    foreach ($linhas as $l) {
        $kpis['total']++;
        $n = $l['status_norm'];
        if ($n === 'Pendente') $kpis['pendentes']++;
        elseif ($n === 'Em andamento') $kpis['em_andamento']++;
        elseif ($n === 'Concluída') $kpis['concluidas']++;
        elseif ($n === 'Cancelada') $kpis['canceladas']++;
        if ($l['atrasada']) $kpis['atrasadas']++;

        $porStatus[$n] = ($porStatus[$n] ?? 0) + 1;
        $porFonte[$l['fonte']] = ($porFonte[$l['fonte']] ?? 0) + 1;
        $fonteStatus[$l['fonte']][$n] = ($fonteStatus[$l['fonte']][$n] ?? 0) + 1;

        $rk = $l['responsavel'];
        if (!isset($porResp[$rk])) $porResp[$rk] = ['responsavel'=>$rk,'total'=>0,'concluidas'=>0];
        $porResp[$rk]['total']++;
        if ($n === 'Concluída') $porResp[$rk]['concluidas']++;

        $ck = $l['categoria'];
        $porCat[$ck] = ($porCat[$ck] ?? 0) + 1;

        if (!empty($l['data_criacao'])) {
            $dt = new DateTime($l['data_criacao']);
            if ($granularidade === 'mes')      $key = $dt->format('Y-m');
            elseif ($granularidade === 'semana') $key = $dt->format('o-\SW');
            else                                $key = $dt->format('Y-m-d');
            if (!isset($serie[$key])) $serie[$key] = ['periodo'=>$key,'total'=>0,'concluidas'=>0];
            $serie[$key]['total']++;
            if ($n === 'Concluída') $serie[$key]['concluidas']++;
        }
    }
    $baseTaxa = $kpis['total'] - $kpis['canceladas'];
    $kpis['taxa_conclusao'] = $baseTaxa > 0 ? round($kpis['concluidas'] / $baseTaxa * 100, 1) : 0;

    // ordenações / formatação
    $porStatusArr = [];
    foreach ($porStatus as $k=>$v) if ($v>0) $porStatusArr[] = ['status'=>$k,'total'=>$v];
    $porFonteArr = [];
    foreach ($porFonte as $k=>$v) $porFonteArr[] = ['fonte'=>$k,'total'=>$v];
    $porRespArr = array_values($porResp);
    usort($porRespArr, function($a,$b){ return $b['total'] - $a['total']; });
    $porCatArr = [];
    foreach ($porCat as $k=>$v) $porCatArr[] = ['rotulo'=>$k,'total'=>$v];
    usort($porCatArr, function($a,$b){ return $b['total'] - $a['total']; });
    ksort($serie);
    $serieArr = array_values($serie);

    /* ------------------- Tarefas em aberto (INDEPENDENTE DOS FILTROS) -------------------
       Sempre lista TODAS as tarefas pendentes / em andamento (não concluídas e não
       canceladas) das duas fontes, sem aplicar nenhum filtro, para dar a visão do que
       está pendente sem precisar filtrar. */
    $emAberto = [];

    // Tarefas internas em aberto (status diferente de concluída/cancelada)
    $resAb = $conn->query("
        SELECT t.id, t.titulo,
               COALESCE(NULLIF(c.titulo,''), t.categoria) AS categoria,
               t.funcionario_responsavel AS responsavel,
               t.status AS status_orig,
               t.nivel_de_prioridade AS prioridade,
               t.data_criacao, t.data_limite, t.data_conclusao
        FROM tarefas t
        LEFT JOIN categorias c ON c.id = t.categoria
        WHERE LOWER(TRIM(t.status)) NOT IN ('concluida','concluída','cancelada')
    ");
    if ($resAb) {
        while ($r = $resAb->fetch_assoc()) {
            $label = statusLabel('Tarefa', $r['status_orig']);
            $norm  = statusNorm($label);
            if (!in_array($norm, ['Pendente','Em andamento'])) continue;
            $atras = (!empty($r['data_limite']) && (new DateTime($r['data_limite'])) < $agora);
            $emAberto[] = [
                'fonte'=>'Tarefa', 'ref'=>'#'.$r['id'], 'titulo'=>$r['titulo'] ?: '(sem título)',
                'categoria'=>$r['categoria'] ?: '—', 'responsavel'=>trim($r['responsavel']) ?: '—',
                'status'=>$label, 'status_norm'=>$norm, 'prioridade'=>$r['prioridade'] ?: '—',
                'data_criacao'=>$r['data_criacao'], 'prazo'=>$r['data_limite'], 'atrasada'=>$atras,
            ];
        }
    }

    // Pedidos de certidão em aberto (pendente / em_andamento)
    $resAb2 = $conn->query("
        SELECT p.id, p.protocolo, p.tipo, p.atribuicao, p.requerente_nome,
               COALESCE(NULLIF(p.atualizado_por,''), p.criado_por) AS responsavel,
               p.status AS status_orig, p.criado_em
        FROM pedidos_certidao p
        WHERE p.status IN ('pendente','em_andamento')
    ");
    if ($resAb2) {
        while ($r = $resAb2->fetch_assoc()) {
            $label = statusLabel('Pedido', $r['status_orig']);
            $norm  = statusNorm($label);
            $titulo = trim(($r['tipo'] ?: 'Certidão').' — '.($r['requerente_nome'] ?: ''));
            $emAberto[] = [
                'fonte'=>'Pedido de Certidão', 'ref'=>$r['protocolo'] ?: ('#'.$r['id']),
                'titulo'=>$titulo, 'categoria'=>$r['tipo'] ?: ($r['atribuicao'] ?: '—'),
                'responsavel'=>trim($r['responsavel']) ?: '—', 'status'=>$label, 'status_norm'=>$norm,
                'prioridade'=>'—', 'data_criacao'=>$r['criado_em'], 'prazo'=>null, 'atrasada'=>false,
            ];
        }
    }

    // Ordena: atrasadas primeiro, depois por prioridade, depois mais antigas primeiro
    $prioRank = ['Crítica'=>0,'Alta'=>1,'Média'=>2,'Baixa'=>3,'—'=>4];
    usort($emAberto, function($a,$b) use ($prioRank){
        if ($a['atrasada'] !== $b['atrasada']) return $a['atrasada'] ? -1 : 1;
        $pa = $prioRank[$a['prioridade']] ?? 4; $pb = $prioRank[$b['prioridade']] ?? 4;
        if ($pa !== $pb) return $pa - $pb;
        return strcmp((string)$a['data_criacao'], (string)$b['data_criacao']);
    });

    $abTotais = ['total'=>0,'pendentes'=>0,'em_andamento'=>0,'atrasadas'=>0];
    foreach ($emAberto as $l) {
        $abTotais['total']++;
        if ($l['status_norm'] === 'Pendente') $abTotais['pendentes']++;
        elseif ($l['status_norm'] === 'Em andamento') $abTotais['em_andamento']++;
        if ($l['atrasada']) $abTotais['atrasadas']++;
    }

    echo json_encode([
        'ok'           => true,
        'periodo'      => ['inicio'=>$ini,'fim'=>$fim,'preset'=>$preset],
        'kpis'         => $kpis,
        'porStatus'    => $porStatusArr,
        'porFonte'     => $porFonteArr,
        'fonteStatus'  => $fonteStatus,
        'porResponsavel'=> $porRespArr,
        'porCategoria' => $porCatArr,
        'serie'        => $serieArr,
        'lista'        => $linhas,
        'emAberto'     => ['totais'=>$abTotais, 'lista'=>$emAberto],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
