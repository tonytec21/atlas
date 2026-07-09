<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config.php';
cap_ensure_schema();
$conn = cap_db();
function he($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$conta = ($_GET['conta'] ?? 'especie') === 'banco' ? 'banco' : 'especie';
$de  = $_GET['de']  ?? date('Y-m-01');
$ate = $_GET['ate'] ?? date('Y-m-t');
if (!strtotime($de))  $de  = date('Y-m-01');
if (!strtotime($ate)) $ate = date('Y-m-t');
$de = date('Y-m-d', strtotime($de)); $ate = date('Y-m-d', strtotime($ate));

$meta   = cap_contas_virtuais()[$conta];
$saldos = cap_saldos();
$temCaixa = cap_tem_deposito_caixa();

/* Entradas: depósitos do módulo Caixa */
$mov = [];
$entPeriodo = 0.0;
if ($temCaixa) {
    $in = "'" . implode("','", array_map([$conn,'real_escape_string'], $meta['tipos'])) . "'";
    $st = $conn->prepare("SELECT data_caixa, valor_do_deposito, tipo_deposito, funcionario FROM deposito_caixa WHERE tipo_deposito IN ($in) AND data_caixa BETWEEN ? AND ? ORDER BY data_caixa");
    $st->bind_param('ss', $de, $ate); $st->execute();
    $r = $st->get_result();
    while ($x = $r->fetch_assoc()) {
        $entPeriodo += (float)$x['valor_do_deposito'];
        $mov[] = ['data'=>$x['data_caixa'],'tipo'=>'entrada','desc'=>'Depósito · '.$x['tipo_deposito'],
                  'obs'=>$x['funcionario'] ?? '', 'valor'=>(float)$x['valor_do_deposito']];
    }
    $st->close();
}
/* Saídas: contas pagas debitadas nesta conta */
$saiPeriodo = 0.0;
$st2 = $conn->prepare("SELECT data_pagamento, titulo, categoria, forma_pagamento, valor FROM contas_a_pagar WHERE status='Pago' AND conta_origem=? AND data_pagamento BETWEEN ? AND ? ORDER BY data_pagamento");
$st2->bind_param('sss', $conta, $de, $ate); $st2->execute();
$r2 = $st2->get_result();
while ($x = $r2->fetch_assoc()) {
    $saiPeriodo += (float)$x['valor'];
    $mov[] = ['data'=>$x['data_pagamento'],'tipo'=>'saida','desc'=>'Pagamento · '.$x['titulo'],
              'obs'=>trim(($x['categoria']??'').' '.($x['forma_pagamento']?('· '.$x['forma_pagamento']):'')), 'valor'=>-(float)$x['valor']];
}
$st2->close();
usort($mov, function($a,$b){ return strcmp($a['data'],$b['data']); });
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas - Extrato da conta virtual</title>
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
    .input-chip select,.input-chip input{ border:none;outline:none;width:100%;background:transparent;color:inherit; }
    .tabs-conta .btn{ border-radius:999px; }
    .mv-ent{ color:#16a34a;font-weight:700; } .mv-sai{ color:#b91c1c;font-weight:700; }
    body.dark-mode .kpi{ background:#23272a;border-color:rgba(255,255,255,.07); }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container">
    <section class="page-hero">
      <div class="title-row">
        <div class="title-icon"><i class="fa <?php echo he($meta['icone']); ?>"></i></div>
        <div style="flex:1;min-width:0"><h1>Extrato · <?php echo he($meta['nome']); ?></h1>
          <div class="subtitle muted">Entradas vindas dos depósitos do Controle de Caixa e saídas pelos pagamentos de contas.</div></div>
        <a class="btn btn-soft btn-pill" href="index.php"><i class="fa fa-arrow-left"></i> Voltar</a>
      </div>
    </section>

    <?php if (!$temCaixa): ?>
      <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> A tabela <code>deposito_caixa</code> (módulo Controle de Caixa) não foi encontrada — as entradas aparecem zeradas.</div>
    <?php endif; ?>

    <div class="d-flex gap-2 mb-3 tabs-conta">
      <?php foreach (cap_contas_virtuais() as $cod=>$m): ?>
        <a class="btn <?php echo $cod===$conta?'btn-primary':'btn-soft'; ?> btn-pill" href="extrato.php?conta=<?php echo $cod; ?>&de=<?php echo he($de); ?>&ate=<?php echo he($ate); ?>">
          <i class="fa <?php echo he($m['icone']); ?>"></i> <?php echo he($m['nome']); ?></a>
      <?php endforeach; ?>
    </div>

    <div class="kpi-grid">
      <div class="kpi"><div class="lb">Saldo atual (total)</div><div class="vl" style="color:<?php echo $saldos[$conta]['saldo']<0?'#b91c1c':'#16a34a'; ?>"><?php echo cap_money($saldos[$conta]['saldo']); ?></div></div>
      <div class="kpi"><div class="lb">Entradas no período</div><div class="vl" style="color:#16a34a"><?php echo cap_money($entPeriodo); ?></div></div>
      <div class="kpi"><div class="lb">Saídas no período</div><div class="vl" style="color:#b91c1c"><?php echo cap_money($saiPeriodo); ?></div></div>
      <div class="kpi"><div class="lb">Resultado do período</div><div class="vl"><?php echo cap_money($entPeriodo - $saiPeriodo); ?></div></div>
    </div>

    <form method="GET" class="filter-card">
      <input type="hidden" name="conta" value="<?php echo he($conta); ?>">
      <div class="section-title">Período</div>
      <div class="row">
        <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">De</label>
          <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" name="de" value="<?php echo he($de); ?>"></div></div>
        <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">Até</label>
          <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" name="ate" value="<?php echo he($ate); ?>"></div></div>
      </div>
      <div class="filter-actions"><button class="btn btn-primary btn-pill"><i class="fa fa-filter"></i> Filtrar</button></div>
    </form>

    <div class="table-responsive table-wrap mt-3">
      <h5 class="mb-2">Movimentações</h5>
      <table id="tabelaContas" class="table table-striped table-bordered data-layout" style="width:100%">
        <thead><tr><th>Data</th><th>Descrição</th><th>Observação</th><th>Valor</th></tr></thead>
        <tbody>
        <?php if (!$mov): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma movimentação no período.</td></tr>
        <?php else: foreach ($mov as $m): ?>
          <tr>
            <td data-label="Data"><?php echo date('d/m/Y', strtotime($m['data'])); ?></td>
            <td data-label="Descrição"><?php echo he($m['desc']); ?></td>
            <td data-label="Observação"><?php echo he($m['obs']); ?></td>
            <td data-label="Valor" class="<?php echo $m['tipo']==='entrada'?'mv-ent':'mv-sai'; ?>">
              <?php echo ($m['tipo']==='entrada'?'+ ':'− ') . cap_money(abs($m['valor'])); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
