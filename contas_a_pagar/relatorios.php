<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard();
require_once __DIR__ . '/config.php';
cap_ensure_schema();
$conn = cap_db();
$CATS = cap_categorias();
function hr($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* filtros */
$de   = $_GET['de']   ?? date('Y-m-01');
$ate  = $_GET['ate']  ?? date('Y-m-t');
$baseSel = $_GET['base'] ?? 'ambos'; // ambos | vencimento | pagamento
$cat  = trim((string)($_GET['categoria'] ?? ''));
$stat = trim((string)($_GET['status'] ?? 'todas'));
if (!strtotime($de)) $de = date('Y-m-01');
if (!strtotime($ate)) $ate = date('Y-m-t');
$de = date('Y-m-d', strtotime($de)); $ate = date('Y-m-d', strtotime($ate));

$where = []; $types=''; $vals=[]; $orderCol='data_vencimento';
if ($baseSel === 'vencimento')      { $where[]="data_vencimento BETWEEN ? AND ?"; $types.='ss'; array_push($vals,$de,$ate); $orderCol='data_vencimento'; }
elseif ($baseSel === 'pagamento')   { $where[]="data_pagamento BETWEEN ? AND ?"; $types.='ss'; array_push($vals,$de,$ate); $orderCol='data_pagamento'; }
else                                { $where[]="((data_vencimento BETWEEN ? AND ?) OR (data_pagamento BETWEEN ? AND ?))"; $types.='ssss'; array_push($vals,$de,$ate,$de,$ate); }
if ($cat !== '') { $where[]="categoria=?"; $types.='s'; $vals[]=$cat; }
switch ($stat) {
    case 'aberto':   $where[]="status='Pendente'"; break;
    case 'vencidas': $where[]="status='Pendente' AND data_vencimento<CURDATE()"; break;
    case 'pago':     $where[]="status='Pago'"; break;
}
$W = implode(' AND ', $where);
$stmt = $conn->prepare("SELECT * FROM contas_a_pagar WHERE $W ORDER BY $orderCol ASC, id DESC");
$stmt->bind_param($types, ...$vals); $stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

/* Exportação CSV */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_contas_'.$de.'_'.$ate.'.csv"');
    echo "\xEF\xBB\xBF"; // BOM p/ Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Vencimento','Título','Categoria','Fornecedor','Valor','Recorrência','Situação','Pagamento','Forma','Conta'], ';');
    foreach ($rows as $c) {
        fputcsv($out, [
            date('d/m/Y', strtotime($c['data_vencimento'])), $c['titulo'], $c['categoria'], $c['fornecedor'],
            number_format((float)$c['valor'],2,',','.'), $c['recorrencia'], cap_status_efetivo($c),
            $c['data_pagamento'] ? date('d/m/Y', strtotime($c['data_pagamento'])) : '',
            $c['forma_pagamento'] ?? '', $c['conta_origem'] ? cap_nome_conta($c['conta_origem']) : ''
        ], ';');
    }
    fclose($out); exit;
}

/* totais + agregações */
$total=0; $qtdPago=0; $vlPago=0; $vlAberto=0; $vlVenc=0; $porCat=[]; $porMes=[];
foreach ($rows as $c) {
    $v=(float)$c['valor']; $total+=$v;
    $st=cap_status_efetivo($c);
    if ($st==='Pago'){ $qtdPago++; $vlPago+=$v; } elseif ($st==='Atrasado'){ $vlVenc+=$v; } else { $vlAberto+=$v; }
    $k = $c['categoria'] ?: 'Sem categoria'; $porCat[$k]=($porCat[$k]??0)+$v;
    $ref = ($c['status']==='Pago' && !empty($c['data_pagamento'])) ? $c['data_pagamento'] : $c['data_vencimento'];
    $m = date('m/Y', strtotime($ref)); $porMes[$m]=($porMes[$m]??0)+$v;
}
arsort($porCat);
$catLabels=array_keys($porCat); $catVals=array_values($porCat);
$mesLabels=array_keys($porMes); $mesVals=array_values($porMes);
$qs = http_build_query(['de'=>$de,'ate'=>$ate,'base'=>$baseSel,'categoria'=>$cat,'status'=>$stat]);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas - Relatórios de Contas</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php include(__DIR__ . '/complementos/style_padrao.php'); ?>
<style>
    #main .container{ padding-bottom:120px; }
    .kpi-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:14px; margin:6px 0 16px; }
    .kpi{ background:#fff;border:1px solid #e5e9f0;border-radius:16px;padding:14px 16px;box-shadow:0 8px 22px rgba(15,23,42,.05); }
    .kpi .lb{ color:#64748b;font-size:.8rem;font-weight:600; } .kpi .vl{ font-size:1.3rem;font-weight:800; }
    .chart-card{ background:#fff;border:1px solid #e5e9f0;border-radius:16px;padding:16px;box-shadow:0 8px 22px rgba(15,23,42,.05); }
    .chart-card h6{ font-weight:800;font-size:.9rem;margin:0 0 10px; } .chart-card canvas{ max-height:260px; }
    .input-chip select,.input-chip input{ border:none;outline:none;width:100%;background:transparent;color:inherit; }
    body.dark-mode .kpi,body.dark-mode .chart-card{ background:#23272a;border-color:rgba(255,255,255,.07); }
    .st-badge{ display:inline-block;padding:3px 10px;border-radius:999px;font-size:.76rem;font-weight:700; }
    .st-Pendente{ background:#dbeafe;color:#1d4ed8; } .st-Atrasado{ background:#fee2e2;color:#b91c1c; } .st-Pago{ background:#dcfce7;color:#166534; }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
    <div class="container">
        <section class="page-hero">
            <div class="title-row">
                <div class="title-icon"><i class="fa fa-bar-chart"></i></div>
                <div style="flex:1;min-width:0"><h1>Relatórios de Contas</h1>
                    <div class="subtitle muted">Análise de despesas por período, categoria e situação.</div></div>
                <div class="d-flex gap-2">
                    <a class="btn btn-success btn-pill" href="relatorios.php?<?php echo hr($qs); ?>&export=csv"><i class="fa fa-file-excel-o"></i> Exportar CSV</a>
                    <a class="btn btn-soft btn-pill" href="index.php"><i class="fa fa-arrow-left"></i> Voltar</a>
                </div>
            </div>
        </section>

        <form method="GET" class="filter-card">
            <div class="section-title">Período & filtros</div>
            <div class="row">
                <div class="col-6 col-md-2 mb-3"><label class="form-label small text-muted mb-1">De</label>
                    <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" name="de" value="<?php echo hr($de); ?>"></div></div>
                <div class="col-6 col-md-2 mb-3"><label class="form-label small text-muted mb-1">Até</label>
                    <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" name="ate" value="<?php echo hr($ate); ?>"></div></div>
                <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">Base da data</label>
                    <div class="input-chip"><i class="fa fa-clock-o"></i><select name="base">
                        <option value="ambos" <?php echo $baseSel==='ambos'?'selected':''; ?>>Vencimento ou pagamento</option>
                        <option value="vencimento" <?php echo $baseSel==='vencimento'?'selected':''; ?>>Vencimento</option>
                        <option value="pagamento" <?php echo $baseSel==='pagamento'?'selected':''; ?>>Pagamento</option>
                    </select></div></div>
                <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">Categoria</label>
                    <div class="input-chip"><i class="fa fa-tag"></i><select name="categoria"><option value="">Todas</option>
                        <?php foreach($CATS as $c): ?><option value="<?php echo hr($c); ?>" <?php echo $cat===$c?'selected':''; ?>><?php echo hr($c); ?></option><?php endforeach; ?>
                    </select></div></div>
                <div class="col-6 col-md-2 mb-3"><label class="form-label small text-muted mb-1">Situação</label>
                    <div class="input-chip"><i class="fa fa-flag"></i><select name="status">
                        <?php foreach(['todas'=>'Todas','aberto'=>'Em aberto','vencidas'=>'Vencidas','pago'=>'Pagas'] as $k=>$v): ?>
                            <option value="<?php echo $k; ?>" <?php echo $stat===$k?'selected':''; ?>><?php echo $v; ?></option><?php endforeach; ?>
                    </select></div></div>
            </div>
            <div class="filter-actions"><button class="btn btn-primary btn-pill"><i class="fa fa-filter"></i> Gerar</button></div>
        </form>

        <div class="kpi-grid">
            <div class="kpi"><div class="lb">Total no período</div><div class="vl"><?php echo cap_money($total); ?></div><div class="text-muted" style="font-size:.76rem"><?php echo count($rows); ?> conta(s)</div></div>
            <div class="kpi"><div class="lb">Pago</div><div class="vl" style="color:#16a34a"><?php echo cap_money($vlPago); ?></div><div class="text-muted" style="font-size:.76rem"><?php echo $qtdPago; ?> conta(s)</div></div>
            <div class="kpi"><div class="lb">Em aberto</div><div class="vl" style="color:#2563eb"><?php echo cap_money($vlAberto); ?></div></div>
            <div class="kpi"><div class="lb">Vencidas</div><div class="vl" style="color:#b91c1c"><?php echo cap_money($vlVenc); ?></div></div>
        </div>

        <div class="row g-3 mb-1">
            <div class="col-12 col-lg-6"><div class="chart-card"><h6><i class="fa fa-tags text-primary"></i> Por categoria</h6><canvas id="chartCat"></canvas></div></div>
            <div class="col-12 col-lg-6"><div class="chart-card"><h6><i class="fa fa-line-chart text-primary"></i> Por mês</h6><canvas id="chartMes"></canvas></div></div>
        </div>

        <div class="table-responsive table-wrap mt-3">
            <h5 class="mb-2">Detalhamento</h5>
            <table id="tabelaContas" class="table table-striped table-bordered data-layout" style="width:100%">
                <thead><tr><th>Vencimento</th><th>Título</th><th>Categoria</th><th>Fornecedor</th><th>Valor</th><th>Situação</th><th>Pagamento</th><th>Forma</th></tr></thead>
                <tbody>
                <?php foreach($rows as $c): $st=cap_status_efetivo($c); ?>
                    <tr>
                        <td data-label="Vencimento" data-order="<?php echo date('Y-m-d',strtotime($c['data_vencimento'])); ?>"><?php echo date('d/m/Y',strtotime($c['data_vencimento'])); ?></td>
                        <td data-label="Título"><?php echo hr($c['titulo']); ?></td>
                        <td data-label="Categoria"><?php echo hr($c['categoria'] ?? ''); ?></td>
                        <td data-label="Fornecedor"><?php echo hr($c['fornecedor'] ?? ''); ?></td>
                        <td data-label="Valor" data-order="<?php echo (float)$c['valor']; ?>"><?php echo cap_money($c['valor']); ?></td>
                        <td data-label="Situação"><span class="st-badge st-<?php echo $st; ?>"><?php echo $st; ?></span></td>
                        <td data-label="Pagamento"><?php echo $c['data_pagamento']?date('d/m/Y',strtotime($c['data_pagamento'])):'—'; ?></td>
                        <td data-label="Forma"><?php echo $c['forma_pagamento']?hr($c['forma_pagamento']):'—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
<script>
(function(){
  if(typeof Chart==='undefined') return;
  Chart.defaults.font.family="Inter, system-ui, sans-serif";
  var brl=function(v){ return 'R$ '+Number(v).toLocaleString('pt-BR'); };
  new Chart(document.getElementById('chartCat'),{ type:'bar',
    data:{ labels:<?php echo json_encode($catLabels, JSON_UNESCAPED_UNICODE); ?>, datasets:[{ data:<?php echo json_encode(array_map(fn($v)=>round($v,2),$catVals)); ?>, backgroundColor:'#4f46e5', borderRadius:6 }] },
    options:{ plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback:brl } } } } });
  new Chart(document.getElementById('chartMes'),{ type:'line',
    data:{ labels:<?php echo json_encode($mesLabels); ?>, datasets:[{ data:<?php echo json_encode(array_map(fn($v)=>round($v,2),$mesVals)); ?>, borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.15)', fill:true, tension:.35 }] },
    options:{ plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback:brl } } } } });
})();
</script>
</body>
</html>
