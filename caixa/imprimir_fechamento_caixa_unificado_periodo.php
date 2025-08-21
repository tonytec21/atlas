<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$dini = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : null;
$dfim = isset($_GET['data_final'])   ? $_GET['data_final']   : null;
$func = isset($_GET['funcionario'])  ? $_GET['funcionario']  : 'todos';

if (!$dini || !$dfim) {
    echo 'Parâmetros "data_inicial" e "data_final" são obrigatórios.';
    exit;
}

$conn = getDatabaseConnection();
$fmt = function($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); };
function dtBR($d){ return date('d/m/Y', strtotime($d)); }

function sumBetween(PDO $conn, string $sqlBase, array $binds, ?string $func){
    $sql = $sqlBase;
    if ($func && $func !== 'todos') {
        $sql .= ' AND funcionario = :func';
        $binds[':func'] = $func;
    }
    $st=$conn->prepare($sql);
    foreach($binds as $k=>$v) $st->bindValue($k,$v);
    $st->execute();
    $v=$st->fetchColumn();
    return $v? (float)$v : 0.0;
}

$totalAtos = sumBetween($conn, 'SELECT SUM(total) FROM atos_liquidados WHERE DATE(data) BETWEEN :dini AND :dfim', [':dini'=>$dini, ':dfim'=>$dfim], $func);
$totalAtosManuais = sumBetween($conn, 'SELECT SUM(total) FROM atos_manuais_liquidados WHERE DATE(data) BETWEEN :dini AND :dfim', [':dini'=>$dini, ':dfim'=>$dfim], $func);
$totalDevolucoes = sumBetween($conn, 'SELECT SUM(total_devolucao) FROM devolucao_os WHERE DATE(data_devolucao) BETWEEN :dini AND :dfim', [':dini'=>$dini, ':dfim'=>$dfim], $func);
$totalSaidas = sumBetween($conn, 'SELECT SUM(valor_saida) FROM saidas_despesas WHERE status="ativo" AND DATE(data) BETWEEN :dini AND :dfim', [':dini'=>$dini, ':dfim'=>$dfim], $func);
$totalDepositos = sumBetween($conn, 'SELECT SUM(valor_do_deposito) FROM deposito_caixa WHERE status="ativo" AND DATE(data_caixa) BETWEEN :dini AND :dfim', [':dini'=>$dini, ':dfim'=>$dfim], $func);
$totalSaldoTransportadoAberto = sumBetween($conn, "SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE status = 'aberto' AND DATE(data_caixa) BETWEEN :dini AND :dfim", [':dini'=>$dini, ':dfim'=>$dfim], $func);

// Saldo Inicial do 1º dia = saldo_inicial (caixa) + saldo transportado "usado" no 1º dia (status != 'aberto')
if ($func && $func !== 'todos') {
    $st = $conn->prepare('SELECT SUM(saldo_inicial) FROM caixa WHERE DATE(data_caixa) = :dini AND funcionario = :func');
    $st->execute([':dini'=>$dini, ':func'=>$func]);
} else {
    $st = $conn->prepare('SELECT SUM(saldo_inicial) FROM caixa WHERE DATE(data_caixa) = :dini');
    $st->execute([':dini'=>$dini]);
}
$saldoInicialCaixa = (float)($st->fetchColumn() ?: 0);

if ($func && $func !== 'todos') {
    $st = $conn->prepare("SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :dini AND funcionario = :func AND status <> 'aberto'");
    $st->execute([':dini'=>$dini, ':func'=>$func]);
} else {
    $st = $conn->prepare("SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :dini AND status <> 'aberto'");
    $st->execute([':dini'=>$dini]);
}
$saldoTransUsadoPrimeiroDia = (float)($st->fetchColumn() ?: 0);

$saldoInicial = $saldoInicialCaixa + $saldoTransUsadoPrimeiroDia;

// Total por tipo de pagamento (para tabela)
if ($func && $func !== 'todos') {
    $st = $conn->prepare('
        SELECT forma_de_pagamento, SUM(total_pagamento) AS tot
        FROM pagamento_os
        WHERE DATE(data_pagamento) BETWEEN :dini AND :dfim AND funcionario = :func
        GROUP BY forma_de_pagamento
    ');
    $st->execute([':dini'=>$dini, ':dfim'=>$dfim, ':func'=>$func]);
} else {
    $st = $conn->prepare('
        SELECT forma_de_pagamento, SUM(total_pagamento) AS tot
        FROM pagamento_os
        WHERE DATE(data_pagamento) BETWEEN :dini AND :dfim
        GROUP BY forma_de_pagamento
    ');
    $st->execute([':dini'=>$dini, ':dfim'=>$dfim]);
}
$porTipo = $st->fetchAll(PDO::FETCH_ASSOC);

$totalRecebidoConta = 0.0; $totalRecebidoEspecie = 0.0;
foreach ($porTipo as $r) {
    $fp=$r['forma_de_pagamento']; $s=(float)$r['tot'];
    if (in_array($fp, ['PIX','Centrais Eletrônicas','Boleto','Transferência Bancária','Crédito','Débito'], true)) $totalRecebidoConta += $s;
    elseif ($fp==='Espécie') $totalRecebidoEspecie += $s;
}

// Fórmula final conforme solicitado
$totalEmCaixaPeriodo = $saldoInicial + $totalRecebidoEspecie - $totalDevolucoes - $totalSaidas - $totalDepositos - $totalSaldoTransportadoAberto;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Fechamento - Caixa <?= ($func==='todos'?'Unificado':'(Individual)') ?> (Período)</title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<style>
  body{ background:#fff; color:#000; }
  .tt{ font-weight:700; }
  .table-sm td, .table-sm th { padding: .3rem; }
  @media print{ .no-print{ display:none; } }
</style>
</head>
<body>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Fechamento — Caixa <?= ($func==='todos'?'Unificado':'(Individual)') ?> (Período)</h3>
    <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Imprimir</button>
  </div>
  <p>
    <b>Período:</b> <?= dtBR($dini) ?> a <?= dtBR($dfim) ?>
    &nbsp;—&nbsp;
    <b><?= ($func==='todos'?'Unificado (Todos)':'Funcionário: '.htmlspecialchars($func)) ?></b>
  </p>

  <div class="row">
    <div class="col-md-6">
      <table class="table table-bordered table-sm">
        <tbody>
          <tr><th>Saldo Inicial (do 1º dia)</th><td class="text-right"><?= $fmt($saldoInicial) ?></td></tr>
          <tr><th>Atos Liquidados</th><td class="text-right"><?= $fmt($totalAtos) ?></td></tr>
          <tr><th>Atos Manuais</th><td class="text-right"><?= $fmt($totalAtosManuais) ?></td></tr>
          <tr><th>Recebido em Conta</th><td class="text-right"><?= $fmt($totalRecebidoConta) ?></td></tr>
          <tr><th>Recebido em Espécie</th><td class="text-right"><?= $fmt($totalRecebidoEspecie) ?></td></tr>
          <tr><th>Devoluções</th><td class="text-right"><?= $fmt($totalDevolucoes) ?></td></tr>
          <tr><th>Saídas e Despesas</th><td class="text-right"><?= $fmt($totalSaidas) ?></td></tr>
          <tr><th>Depósitos</th><td class="text-right"><?= $fmt($totalDepositos) ?></td></tr>
          <tr><th>Saldo Transportado (em aberto)</th><td class="text-right"><?= $fmt($totalSaldoTransportadoAberto) ?></td></tr>
          <tr class="tt"><th>Total em Caixa (Período)</th><td class="text-right"><?= $fmt($totalEmCaixaPeriodo) ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="col-md-6">
      <h5>Total por Tipo de Pagamento</h5>
      <table class="table table-bordered table-sm">
        <thead><tr><th>Forma</th><th class="text-right">Total</th></tr></thead>
        <tbody>
          <?php foreach ($porTipo as $linha): ?>
            <tr>
              <td><?= htmlspecialchars($linha['forma_de_pagamento']) ?></td>
              <td class="text-right"><?= $fmt($linha['tot']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <small class="text-muted">Gerado em <?= date('d/m/Y H:i:s') ?></small>
</div>
</body>
</html>
