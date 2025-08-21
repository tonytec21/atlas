<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$conn = getDatabaseConnection();

/** Permissões (iguais ao seu index.php) */
$stmt = $conn->prepare('SELECT nivel_de_acesso, status, usuario, nome_completo, acesso_adicional FROM funcionarios WHERE usuario = :usuario');
$stmt->bindParam(':usuario', $_SESSION['username']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'ativo') {
    echo "<script>alert('O usuário não tem acesso à página.'); window.location.href='../index.php';</script>";
    exit;
}

/** Verifica se possui acesso ao Fluxo de Caixa */
$temAcessoFluxo = false;
if ($user['nivel_de_acesso'] === 'administrador') {
    $temAcessoFluxo = true;
} else {
    $acessosAdicionais = array_map('trim', explode(',', (string)($user['acesso_adicional'] ?? '')));
    if (in_array('Fluxo de Caixa', $acessosAdicionais, true)) $temAcessoFluxo = true;
}
if (!$temAcessoFluxo) {
    echo "<script>alert('Você não tem acesso ao Caixa Unificado (Período).'); window.location.href='../index.php';</script>";
    exit;
}

/** Funcionário (unificado/todos ou específico) */
$funcionarioSelected = isset($_GET['funcionario']) ? $_GET['funcionario'] : 'todos';

/** Carrega lista de funcionários (ativos) quando permitido */
$listaFuncionarios = [];
if ($user['nivel_de_acesso'] === 'administrador' || $temAcessoFluxo) {
    $stF = $conn->prepare("SELECT usuario, nome_completo FROM funcionarios WHERE status = 'ativo' ORDER BY nome_completo");
    $stF->execute();
    $listaFuncionarios = $stF->fetchAll(PDO::FETCH_ASSOC);
} else {
    $funcionarioSelected = $user['usuario'];
}

/** Período rápido (default: hoje) */
$selectedPeriodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'hoje';
$hoje     = date('Y-m-d');
$ultimo7  = date('Y-m-d', strtotime('-6 days'));
$ultimo30 = date('Y-m-d', strtotime('-30 days'));

/** Datas do formulário */
$data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';
$data_final   = isset($_GET['data_final'])   ? $_GET['data_final']   : '';

/** Se não vier intervalo manual, aplica período rápido no backend também (para o card de “prévia”) */
$useManual = (!empty($data_inicial) || !empty($data_final));
if (!$useManual) {
    if ($selectedPeriodo === 'hoje') {
        $data_inicial = $hoje;
        $data_final   = $hoje;
    } elseif ($selectedPeriodo === 'ultimos7') {
        $data_inicial = $ultimo7;
        $data_final   = $hoje;
    } elseif ($selectedPeriodo === 'ultimoMes') {
        $data_inicial = $ultimo30;
        $data_final   = $hoje;
    } else {
        $data_inicial = $ultimo30;
        $data_final   = $hoje;
    }
}

/** Helpers de somatório com/sem filtro de funcionário */
function sumBetween(PDO $conn, string $sqlBase, array $binds, ?string $func)
{
    $sql = $sqlBase;
    if ($func && $func !== 'todos') {
        $sql .= ' AND funcionario = :func';
        $binds[':func'] = $func;
    }
    $st = $conn->prepare($sql);
    foreach ($binds as $k => $v) $st->bindValue($k, $v);
    $st->execute();
    $v = $st->fetchColumn();
    return $v ? (float)$v : 0.0;
}

// Intervalo corrente
$dini = $data_inicial;
$dfim = $data_final;

// === Saldo Inicial do PERÍODO (somente 1º dia) ===
// 1) saldo_inicial da tabela caixa no 1º dia
if ($funcionarioSelected === 'todos') {
    $st = $conn->prepare('SELECT SUM(saldo_inicial) FROM caixa WHERE DATE(data_caixa) = :dini');
    $st->execute([':dini' => $dini]);
} else {
    $st = $conn->prepare('SELECT SUM(saldo_inicial) FROM caixa WHERE DATE(data_caixa) = :dini AND funcionario = :func');
    $st->execute([':dini' => $dini, ':func' => $funcionarioSelected]);
}
$saldoInicialCaixa = (float)($st->fetchColumn() ?: 0);

// 2) saldo transportado "usado" no 1º dia (status ≠ 'aberto' passa a ser saldo inicial)
if ($funcionarioSelected === 'todos') {
    $st = $conn->prepare("SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :dini AND status <> 'aberto'");
    $st->execute([':dini' => $dini]);
} else {
    $st = $conn->prepare("SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :dini AND funcionario = :func AND status <> 'aberto'");
    $st->execute([':dini' => $dini, ':func' => $funcionarioSelected]);
}
$saldoTransUsadoPrimeiroDia = (float)($st->fetchColumn() ?: 0);

$saldoInicial = $saldoInicialCaixa + $saldoTransUsadoPrimeiroDia;

/** Atos e Atos Manuais (intervalo) */
$totalAtos = sumBetween(
    $conn,
    'SELECT SUM(total) FROM atos_liquidados WHERE DATE(data) BETWEEN :dini AND :dfim',
    [':dini' => $dini, ':dfim' => $dfim],
    $funcionarioSelected
);
$totalAtosManuais = sumBetween(
    $conn,
    'SELECT SUM(total) FROM atos_manuais_liquidados WHERE DATE(data) BETWEEN :dini AND :dfim',
    [':dini' => $dini, ':dfim' => $dfim],
    $funcionarioSelected
);

/** Pagamentos por tipo */
if ($funcionarioSelected === 'todos') {
    $st = $conn->prepare('
        SELECT forma_de_pagamento, SUM(total_pagamento) AS tot
        FROM pagamento_os
        WHERE DATE(data_pagamento) BETWEEN :dini AND :dfim
        GROUP BY forma_de_pagamento
    ');
    $st->execute([':dini' => $dini, ':dfim' => $dfim]);
} else {
    $st = $conn->prepare('
        SELECT forma_de_pagamento, SUM(total_pagamento) AS tot
        FROM pagamento_os
        WHERE DATE(data_pagamento) BETWEEN :dini AND :dfim AND funcionario = :func
        GROUP BY forma_de_pagamento
    ');
    $st->execute([':dini' => $dini, ':dfim' => $dfim, ':func' => $funcionarioSelected]);
}
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$totalRecebidoConta = 0.0;
$totalRecebidoEspecie = 0.0;
foreach ($rows as $r) {
    $fp = $r['forma_de_pagamento'];
    $s  = (float)$r['tot'];
    if (in_array($fp, ['PIX','Centrais Eletrônicas','Boleto','Transferência Bancária','Crédito','Débito'], true)) {
        $totalRecebidoConta += $s;
    } elseif ($fp === 'Espécie') {
        $totalRecebidoEspecie += $s;
    }
}

/** Devoluções (intervalo) — agora usamos o TOTAL, não só espécie */
$totalDevolucoes = sumBetween(
    $conn,
    'SELECT SUM(total_devolucao) FROM devolucao_os WHERE DATE(data_devolucao) BETWEEN :dini AND :dfim',
    [':dini' => $dini, ':dfim' => $dfim],
    $funcionarioSelected
);

/** Saídas/Despesas (ativo) */
$totalSaidas = sumBetween(
    $conn,
    'SELECT SUM(valor_saida) FROM saidas_despesas WHERE status = "ativo" AND DATE(data) BETWEEN :dini AND :dfim',
    [':dini' => $dini, ':dfim' => $dfim],
    $funcionarioSelected
);

/** Depósitos (ativo) */
$totalDepositos = sumBetween(
    $conn,
    'SELECT SUM(valor_do_deposito) FROM deposito_caixa WHERE status = "ativo" AND DATE(data_caixa) BETWEEN :dini AND :dfim',
    [':dini' => $dini, ':dfim' => $dfim],
    $funcionarioSelected
);

/** Saldo transportado "em aberto" no período (somente status = 'aberto') */
$totalSaldoTransportadoAberto = sumBetween(
    $conn,
    "SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE status = 'aberto' AND DATE(data_caixa) BETWEEN :dini AND :dfim",
    [':dini' => $dini, ':dfim' => $dfim],
    $funcionarioSelected
);

/** Total em Caixa (Período) conforme sua regra */
$totalEmCaixaPeriodo = $saldoInicial + $totalRecebidoEspecie - $totalDevolucoes - $totalSaidas - $totalDepositos - $totalSaldoTransportadoAberto;

/** Helpers */
$fmt = function($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); };
function dtBR($d){ return date('d/m/Y', strtotime($d)); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caixa Unificado (Período)</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <?php include(__DIR__ . '/../style/style_caixa.php'); ?>
    <style>
        .page-hero .title-icon{ background:#2b7a78; }
        .caixa-card { cursor:pointer; }
        .pastel-open  { background:#eefbf2; }
        .pastel-closed{ background:#f6f6f6; }
        .badge-open   { background:#2e7d32; color:#fff; }
        .badge-closed { background:#6c757d; color:#fff; }
        .chip { display:inline-block; padding:2px 8px; border-radius:12px; font-size:12px; margin-bottom:4px; background:#e9ecef; }
        .chip-total { background:#222; color:#fff; }
        .modal-status-pill{ position:absolute; right:48px; top:10px; }
        .card-footer-eq .btn { margin-right:6px; }
        .modal-dialog {max-width: 98%;}
    </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <section class="page-hero">
            <div class="title-row">
                <div class="title-icon"><i class="fa fa-university" aria-hidden="true"></i></div>
                <div class="title-texts">
                    <h1>Caixa Unificado (Período)</h1>
                    <div class="subtitle muted">Visualize semana, mês ou intervalo de datas em uma única visão consolidada (por funcionário ou unificado).</div>
                </div>
            </div>
        </section>

        <hr>
        <form method="GET" id="formFiltros">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="funcionario">Funcionário:</label>
                    <select class="form-control" id="funcionario" name="funcionario" <?php
                        echo ($user['nivel_de_acesso'] !== 'administrador' && !$temAcessoFluxo) ? 'disabled' : '';
                    ?>>
                        <?php if ($user['nivel_de_acesso'] === 'administrador' || $temAcessoFluxo): ?>
                            <option value="todos" <?= ($funcionarioSelected==='todos')?'selected':''; ?>>Unificado (Todos)</option>
                            <?php foreach ($listaFuncionarios as $f): ?>
                                <option value="<?= htmlspecialchars($f['usuario']) ?>" <?= ($funcionarioSelected===$f['usuario'])?'selected':''; ?>>
                                    <?= htmlspecialchars($f['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="<?= htmlspecialchars($user['usuario']) ?>" selected><?= htmlspecialchars($user['nome_completo']) ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group col-md-4">
                    <label for="periodo">Período Rápido:</label>
                    <select class="form-control" id="periodo" name="periodo">
                        <option value="hoje"      <?= $selectedPeriodo==='hoje'?'selected':''; ?>>Hoje</option>
                        <option value="ultimos7"  <?= $selectedPeriodo==='ultimos7'?'selected':''; ?>>Últimos 7 dias</option>
                        <option value="ultimoMes" <?= $selectedPeriodo==='ultimoMes'?'selected':''; ?>>Último mês (30 dias)</option>
                        <option value="todos"     <?= $selectedPeriodo==='todos'?'selected':''; ?>>Todos</option>
                    </select>
                    <small class="text-muted">Dica: escolher um período aqui preenche las datas abaixo.</small>
                </div>

                <div class="form-group col-md-2">
                    <label for="data_inicial">Data Inicial:</label>
                    <input type="date" class="form-control" id="data_inicial" name="data_inicial" value="<?= htmlspecialchars($data_inicial); ?>">
                </div>
                <div class="form-group col-md-2">
                    <label for="data_final">Data Final:</label>
                    <input type="date" class="form-control" id="data_final" name="data_final" value="<?= htmlspecialchars($data_final); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-12 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-filter"></i> Filtrar</button>
                </div>
            </div>
        </form>
        <hr>

        <?php
            $statusIcon = (round($totalEmCaixaPeriodo,2)==0.00) ? 'fa-lock' : 'fa-unlock-alt';
            $statusLbl  = (round($totalEmCaixaPeriodo,2)==0.00) ? 'Fechado' : 'Aberto';
            $badgeClass = (round($totalEmCaixaPeriodo,2)==0.00) ? 'badge-closed' : 'badge-open';
            $cardBg     = (round($totalEmCaixaPeriodo,2)==0.00) ? 'pastel-closed' : 'pastel-open';
            $legendFunc = ($funcionarioSelected==='todos') ? 'Unificado' : 'Funcionário: '.$funcionarioSelected;
        ?>

        <h5>Resultado Consolidado do Período</h5>
        <div class="row cards-wrap">
            <div class="col-12 col-sm-8 col-md-6 col-lg-5">
                <div class="card caixa-card <?= $cardBg; ?>" onclick="verDetalhesPeriodo('<?= $data_inicial ?>','<?= $data_final ?>','<?= htmlspecialchars($funcionarioSelected, ENT_QUOTES) ?>')">
                    <div class="card-body">
                        <div class="header-block topline">
                            <div class="title-strong">Caixa <?= ($funcionarioSelected==='todos'?'Unificado':'(Individual)') ?></div>
                            <span class="badge-status <?= $badgeClass; ?>"><i class="fa <?= $statusIcon; ?>"></i> <?= $statusLbl; ?></span>
                        </div>
                        <div class="muted">
                            Período: <?= dtBR($data_inicial); ?> a <?= dtBR($data_final); ?> — <b><?= htmlspecialchars($legendFunc) ?></b>
                        </div>

                        <div class="metrics">
                            <div class="metric">
                                <span class="chip">Saldo Inicial (do 1º dia)</span>
                                <div class="k"><?= $fmt($saldoInicial); ?></div>
                            </div>
                            <div class="metric">
                                <span class="chip">Atos Liquidados</span>
                                <div class="k"><?= $fmt($totalAtos); ?></div>
                            </div>
                            <div class="metric">
                                <span class="chip">Atos Manuais</span>
                                <div class="k"><?= $fmt($totalAtosManuais); ?></div>
                            </div>
                            <div class="metric">
                                <span class="chip">Recebido em Conta</span>
                                <div class="k"><?= $fmt($totalRecebidoConta); ?></div>
                            </div>
                            <div class="metric">
                                <span class="chip">Recebido em Espécie</span>
                                <div class="k"><?= $fmt($totalRecebidoEspecie); ?></div>
                            </div>
                            <div class="metric">
                                <span class="chip">Devoluções</span>
                                <div class="k"><?= $fmt($totalDevolucoes); ?></div>
                            </div>
                            <div class="metric">
                                <span class="chip">Saídas e Despesas</span>
                                <div class="k"><?= $fmt($totalSaidas); ?></div>
                            </div>
                            <div class="metric">
                                <span class="chip">Depósitos</span>
                                <div class="k"><?= $fmt($totalDepositos); ?></div>
                            </div>
                            <div class="metric">
                                <span class="chip">Saldo Transportado</span>
                                <div class="k"><?= $fmt($totalSaldoTransportadoAberto); ?></div>
                            </div>
                            <div class="metric">
                                <span class="chip chip-total">Total em Caixa (Período)</span>
                                <div class="k"><?= $fmt($totalEmCaixaPeriodo); ?></div>
                            </div>
                        </div>

                        <div class="card-footer-eq">
                            <div class="card-actions">
                                <button title="Ver Depósitos do Período" class="btn btn-success btn-sm" onclick="event.stopPropagation(); verDepositosPeriodo('<?= $data_inicial ?>','<?= $data_final ?>','<?= htmlspecialchars($funcionarioSelected, ENT_QUOTES) ?>')">
                                    <i class="fa fa-list" aria-hidden="true"></i> Depósitos do Período
                                </button>
                                <a href="imprimir_fechamento_caixa_unificado_periodo.php?data_inicial=<?= urlencode($data_inicial) ?>&data_final=<?= urlencode($data_final) ?>&funcionario=<?= urlencode($funcionarioSelected) ?>" target="_blank" title="Imprimir Fechamento do Período" class="btn btn-primary btn-sm" onclick="event.stopPropagation();">
                                    <i class="fa fa-file-pdf-o"></i> Imprimir
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div><!-- /col -->
        </div><!-- /row -->
    </div>
</div>

<!-- Modal de Detalhes (Período) -->
<div class="modal fade" id="detalhesModalPeriodo" tabindex="-1" role="dialog" aria-labelledby="detalhesModalPeriodoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header d-flex align-items-center justify-content-center position-relative">
        <h5 class="modal-title text-center mb-0" id="detalhesModalPeriodoLabel"></h5>
        <div id="modalStatusPillPeriodo" class="modal-status-pill"></div>
        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" style="position:absolute; right:12px; top:8px;">&times;</button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white bg-primary mb-3" style="background-color:#005d15 !important">
              <div class="card-header">Saldo Inicial</div>
              <div class="card-body"><h5 class="card-title" id="cardSaldoInicial">R$ 0,00</h5></div>
            </div>
          </div>
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white bg-primary mb-3">
              <div class="card-header">Atos Liquidados</div>
              <div class="card-body"><h5 class="card-title" id="cardTotalAtos">R$ 0,00</h5></div>
            </div>
          </div>
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white" style="background-color:#6f42c1;">
              <div class="card-header">Atos Manuais</div>
              <div class="card-body"><h5 class="card-title" id="cardTotalAtosManuais">R$ 0,00</h5></div>
            </div>
          </div>
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white bg-warning mb-3">
              <div class="card-header">Recebido em Conta</div>
              <div class="card-body"><h5 class="card-title" id="cardTotalRecebidoConta">R$ 0,00</h5></div>
            </div>
          </div>
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white bg-success mb-3">
              <div class="card-header">Recebido em Espécie</div>
              <div class="card-body"><h5 class="card-title" id="cardTotalRecebidoEspecie">R$ 0,00</h5></div>
            </div>
          </div>
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white bg-petroleo mb-3">
              <div class="card-header">Total Recebido</div>
              <div class="card-body"><h5 class="card-title" id="cardTotalRecebido">R$ 0,00</h5></div>
            </div>
          </div>
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white bg-secondary mb-3">
              <div class="card-header">Devoluções</div>
              <div class="card-body"><h5 class="card-title" id="cardTotalDevolucoes">R$ 0,00</h5></div>
            </div>
          </div>
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white bg-danger mb-3">
              <div class="card-header">Saídas e Despesas</div>
              <div class="card-body"><h5 class="card-title" id="cardSaidasDespesas">R$ 0,00</h5></div>
            </div>
          </div>
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white bg-info mb-3">
              <div class="card-header">Depósitos</div>
              <div class="card-body"><h5 class="card-title" id="cardDepositoCaixa">R$ 0,00</h5></div>
            </div>
          </div>
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white btn-4 mb-3">
              <div class="card-header">Saldo Transportado</div>
              <div class="card-body"><h5 class="card-title" id="cardSaldoTransportado">R$ 0,00</h5></div>
            </div>
          </div>
          <div class="col-6 col-sm-6 col-md-3 col-lg-3">
            <div class="card text-white bg-dark mb-3">
              <div class="card-header">Total em Caixa</div>
              <div class="card-body"><h5 class="card-title" id="cardTotalEmCaixa">R$ 0,00</h5></div>
            </div>
          </div>
        </div>
        <hr>

        <!-- (Demais tabelas e conteúdo permanecem idênticos) -->
        <div class="card mb-3">
          <div class="card-header table-title text-center"><b>ATOS LIQUIDADOS</b></div>
          <div class="card-body">
            <div class="row no-gutters align-items-end mb-3" id="filtrosAtosLiquidados">
              <div class="col-12 col-md-3 mb-2">
                <label class="input-label">Funcionário</label>
                <select class="form-control form-control-sm" id="filtroAtosFuncionario"></select>
              </div>
              <div class="col-12 col-md-3 mb-2">
                <label class="input-label">Ato</label>
                <select class="form-control form-control-sm" id="filtroAtosAto"></select>
              </div>
              <div class="col-12 col-md-3 mb-2">
                <label class="input-label">Apresentante</label>
                <select class="form-control form-control-sm" id="filtroAtosApresentante"></select>
              </div>
              <div class="col-12 col-md-3 mb-2">
                <label class="input-label">Nº OS</label>
                <select class="form-control form-control-sm" id="filtroAtosOS"></select>
              </div>
              <div class="col-12 mt-1">
                <button class="btn btn-outline-secondary btn-sm" id="btnLimparFiltrosAtos"><i class="fa fa-eraser"></i> Limpar filtros</button>
              </div>
            </div>
            <div class="table-responsive">
              <table id="tabelaAtos" class="table table-striped table-bordered">
                <thead>
                <tr>
                  <th>Funcionário</th>
                  <th>Nº OS</th>
                  <th>Apresentante</th>
                  <th>Ato</th>
                  <th>Descrição</th>
                  <th>Quantidade</th>
                  <th>Total</th>
                </tr>
                </thead>
                <tbody id="detalhesAtos"></tbody>
              </table>
            </div>
            <h6 class="total-label d-flex flex-wrap align-items-center">
              <span class="mr-3">Qtd.: <span id="qtdAtos">0</span></span>
              <span>Total Atos Liquidados: <span id="totalAtos"></span></span>
            </h6>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header table-title text-center"><b>ATOS MANUAIS</b></div>
          <div class="card-body">
            <div class="row no-gutters align-items-end mb-3" id="filtrosAtosManuais">
              <div class="col-12 col-md-3 mb-2">
                <label class="input-label">Funcionário</label>
                <select class="form-control form-control-sm" id="filtroManuaisFuncionario"></select>
              </div>
              <div class="col-12 col-md-3 mb-2">
                <label class="input-label">Ato</label>
                <select class="form-control form-control-sm" id="filtroManuaisAto"></select>
              </div>
              <div class="col-12 col-md-3 mb-2">
                <label class="input-label">Apresentante</label>
                <select class="form-control form-control-sm" id="filtroManuaisApresentante"></select>
              </div>
              <div class="col-12 col-md-3 mb-2">
                <label class="input-label">Nº OS</label>
                <select class="form-control form-control-sm" id="filtroManuaisOS"></select>
              </div>
              <div class="col-12 mt-1">
                <button class="btn btn-outline-secondary btn-sm" id="btnLimparFiltrosManuais"><i class="fa fa-eraser"></i> Limpar filtros</button>
              </div>
            </div>
            <div class="table-responsive">
              <table id="tabelaAtosManuais" class="table table-striped table-bordered">
                <thead>
                <tr>
                  <th>Funcionário</th>
                  <th>Nº OS</th>
                  <th>Apresentante</th>
                  <th>Ato</th>
                  <th>Descrição</th>
                  <th>Quantidade</th>
                  <th>Total</th>
                </tr>
                </thead>
                <tbody id="detalhesAtosManuais"></tbody>
              </table>
            </div>
            <h6 class="total-label d-flex flex-wrap align-items-center">
              <span class="mr-3">Qtd.: <span id="qtdAtosManuais">0</span></span>
              <span>Total Atos Manuais: <span id="totalAtosManuais"></span></span>
            </h6>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header table-title text-center"><b>PAGAMENTOS</b></div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tabelaPagamentos" class="table table-striped table-bordered">
                <thead>
                <tr>
                  <th>Funcionário</th>
                  <th>Nº OS</th>
                  <th>Apresentante</th>
                  <th>Forma de Pagamento</th>
                  <th>Total</th>
                </tr>
                </thead>
                <tbody id="detalhesPagamentos"></tbody>
              </table>
            </div>
            <h6 class="total-label">Total Pagamentos: <span id="totalPagamentos"></span></h6>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header table-title text-center"><b>TOTAL POR TIPO DE PAGAMENTO</b></div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tabelaTotalPorTipo" class="table table-striped table-bordered">
                <thead>
                <tr><th>Forma de Pagamento</th><th>Total</th></tr>
                </thead>
                <tbody id="detalhesTotalPorTipo"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header table-title text-center"><b>DEVOLUÇÕES</b></div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tabelaDevolucoes" class="table table-striped table-bordered">
                <thead>
                <tr>
                  <th>Funcionário</th>
                  <th>Nº OS</th>
                  <th>Apresentante</th>
                  <th>Forma de Devolução</th>
                  <th>Total</th>
                </tr>
                </thead>
                <tbody id="detalhesDevolucoes"></tbody>
              </table>
            </div>
            <h6 class="total-label">Total Devoluções: <span id="totalDevolucoes"></span></h6>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header table-title text-center"><b>SAÍDAS E DESPESAS</b></div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tabelaSaidas" class="table table-striped table-bordered">
                <thead>
                <tr>
                  <th>Funcionário</th>
                  <th>Título</th>
                  <th>Valor</th>
                  <th>Forma de Saída</th>
                  <th>Data do Caixa</th>
                  <th>Data Cadastro</th>
                  <th>Ações</th>
                </tr>
                </thead>
                <tbody id="detalhesSaidas"></tbody>
              </table>
            </div>
            <h6 class="total-label">Total Saídas: <span id="totalSaidas"></span></h6>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header table-title text-center"><b>DEPÓSITOS</b></div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tabelaDepositos" class="table table-striped table-bordered">
                <thead>
                <tr>
                  <th>Funcionário</th>
                  <th>Data do Caixa</th>
                  <th>Data Cadastro</th>
                  <th>Valor</th>
                  <th>Tipo</th>
                  <th>Ações</th>
                </tr>
                </thead>
                <tbody id="detalhesDepositos"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header table-title text-center"><b>SALDO TRANSPORTADO</b></div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tabelaSaldoTransportado" class="table table-striped table-bordered">
                <thead>
                <tr>
                  <th>Data Caixa</th>
                  <th>Data Transporte</th>
                  <th>Valor Transportado</th>
                  <th>Funcionário</th>
                  <th>Status</th>
                </tr>
                </thead>
                <tbody id="detalhesSaldoTransportado"></tbody>
              </table>
            </div>
            <h6 class="total-label">Total Saldo Transportado: <span id="totalSaldoTransportado"></span></h6>
          </div>
        </div>

      </div><!-- /modal-body -->
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Depósitos do Período -->
<div class="modal fade" id="verDepositosPeriodoModal" tabindex="-1" role="dialog" aria-labelledby="verDepositosPeriodoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-deposito-caixa-unificado modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="verDepositosPeriodoModalLabel">Depósitos do Período</h5>
        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table id="tabelaDepositosPeriodo" class="table table-striped table-bordered">
            <thead>
            <tr>
              <th>Funcionário</th>
              <th>Data do Caixa</th>
              <th>Data Cadastro</th>
              <th>Valor</th>
              <th>Tipo</th>
              <th>Ações</th>
            </tr>
            </thead>
            <tbody id="detalhesDepositosPeriodo"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.dataTables.min.js"></script>
<script src="../script/dataTables.bootstrap4.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>
(function(){
  function toISO(d){ return d.toISOString().slice(0,10); }
  function applyPeriodoToDates() {
    const periodo = $('#periodo').val();
    const hoje = new Date();
    if (periodo === 'hoje') {
      $('#data_inicial').val(toISO(hoje));
      $('#data_final').val(toISO(hoje));
    } else if (periodo === 'ultimos7') {
      const d = new Date(); d.setDate(d.getDate()-6);
      $('#data_inicial').val(toISO(d)); $('#data_final').val(toISO(hoje));
    } else if (periodo === 'ultimoMes') {
      const d = new Date(); d.setDate(d.getDate()-30);
      $('#data_inicial').val(toISO(d)); $('#data_final').val(toISO(hoje));
    } else {
      $('#data_inicial').val(''); $('#data_final').val('');
    }
  }
  <?php if (!isset($_GET['periodo']) && empty($_GET['data_inicial']) && empty($_GET['data_final'])): ?>
    $('#periodo').val('hoje'); applyPeriodoToDates();
  <?php endif; ?>
  $('#periodo').on('change', applyPeriodoToDates);

  function formatCurrency(value){
    if (isNaN(value)) return 'R$ 0,00';
    return 'R$ ' + parseFloat(value).toFixed(2).replace('.',',').replace(/\d(?=(\d{3})+,)/g,'$&.');
  }
  function escapeRegex(s){ return String(s||'').replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }
  function parseBRMoney(str){
    if (str == null) return 0;
    const s = String(str).replace(/[^\d,.-]/g,'').replace(/\./g,'').replace(',','.');
    const v = parseFloat(s);
    return isNaN(v)?0:v;
  }
  function distinctFrom(list, key){
    const set=new Set();
    (list||[]).forEach(it=>{
      const v=(it && it[key]!=null)?String(it[key]).trim():'';
      if(v) set.add(v);
    });
    return Array.from(set).sort((a,b)=>a.localeCompare(b,'pt-BR',{numeric:true,sensitivity:'base'}));
  }
  function fillSelect(selId, values, placeholder){
    const $s=$(selId); $s.empty().append(`<option value="">${placeholder}</option>`);
    values.forEach(v=>$s.append(`<option value="${$('<div>').text(v).html()}">${v}</option>`));
  }
  function formatDateForDisplay(date){
    var d=new Date(date+'T00:00:00');
    var day=('0'+d.getUTCDate()).slice(-2);
    var month=('0'+(d.getUTCMonth()+1)).slice(-2);
    var year=d.getUTCFullYear();
    return `${day}/${month}/${year}`;
  }
  function formatDateForDisplay2(date){
    var d=new Date(date);
    var day=('0'+d.getDate()).slice(-2);
    var month=('0'+(d.getMonth()+1)).slice(-2);
    var year=d.getFullYear();
    return `${day}/${month}/${year}`;
  }

  window.dtAtos=null; window.dtAtosManuais=null;

  window.verDetalhesPeriodo = function(di, df, func){
    $.ajax({
      url:'detalhes_fluxo_caixa_periodo.php',
      type:'GET',
      data:{ data_inicial: di, data_final: df, funcionario: func },
      dataType:'json',
      success:function(d){
        if (d.error){ alert('Erro: '+d.error); return; }

        const legenda = (func==='todos') ? 'UNIFICADO (Todos)' : ('FUNCIONÁRIO: '+func);
        $('#detalhesModalPeriodoLabel').html(`CAIXA — PERÍODO ${formatDateForDisplay(di)} a ${formatDateForDisplay(df)} — ${legenda}`);

        const fechado=(parseFloat(d.totalEmCaixa)||0)===0;
        const statusText=fechado?'Fechado':'Aberto';
        const statusClass=fechado?'badge-closed':'badge-open';
        const icon=fechado?'fa-lock':'fa-unlock-alt';
        $('#modalStatusPillPeriodo').html(`<span class="badge-status ${statusClass}"><i class="fa ${icon}"></i> ${statusText}</span>`);

        function setCard(sel,val){
          const el=$(sel).closest('.col-md-3'); const v=parseFloat(val||0);
          if (v>0){ $(sel).text(formatCurrency(v)); el.show(); } else { $(sel).text('R$ 0,00'); el.hide(); }
        }
        setCard('#cardSaldoInicial', d.saldoInicial);
        setCard('#cardTotalAtos', d.totalAtos);
        setCard('#cardTotalAtosManuais', d.totalAtosManuais);
        setCard('#cardTotalRecebidoConta', d.totalRecebidoConta);
        setCard('#cardTotalRecebidoEspecie', d.totalRecebidoEspecie);
        setCard('#cardTotalDevolucoes', d.totalDevolucoes);
        setCard('#cardSaidasDespesas', d.totalSaidasDespesas);
        setCard('#cardDepositoCaixa', d.totalDepositoCaixa);
        setCard('#cardSaldoTransportado', d.totalSaldoTransportado); // agora é só "em aberto"
        $('#cardTotalRecebido').text(formatCurrency((parseFloat(d.totalRecebidoConta)||0)+(parseFloat(d.totalRecebidoEspecie)||0)));

        // Fórmula final já vem pronta
        $('#cardTotalEmCaixa').text(formatCurrency(d.totalEmCaixa||0));

        // Atos
        let somaAtos=0, qtdAtos=0;
        $('#detalhesAtos').empty();
        (d.atos||[]).forEach(a=>{
          somaAtos+=parseFloat(a.total||0);
          qtdAtos +=parseFloat(a.quantidade_liquidada||0);
          $('#detalhesAtos').append(`
            <tr>
              <td>${a.funcionario}</td>
              <td>${a.ordem_servico_id}</td>
              <td>${a.cliente}</td>
              <td>${a.ato}</td>
              <td>${a.descricao}</td>
              <td>${a.quantidade_liquidada}</td>
              <td>${formatCurrency(a.total)}</td>
            </tr>`);
        });
        $('#qtdAtos').text(qtdAtos);
        $('#totalAtos').text(formatCurrency(somaAtos));

        fillSelect('#filtroAtosAto', distinctFrom(d.atos,'ato'), 'Todos os Atos');
        fillSelect('#filtroAtosApresentante', distinctFrom(d.atos,'cliente'), 'Todos os Apresentantes');
        fillSelect('#filtroAtosOS', distinctFrom(d.atos,'ordem_servico_id'), 'Todas as O.S');
        fillSelect('#filtroAtosFuncionario', distinctFrom(d.atos,'funcionario'), 'Todos os Funcionários');

        if (window.dtAtos) window.dtAtos.destroy();
        window.dtAtos = $('#tabelaAtos').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[]})
          .on('draw', recalcAtosTotals);
        function applyAtosFilters(){
          const func=$('#filtroAtosFuncionario').val()||'';
          const ato =$('#filtroAtosAto').val()||'';
          const apr =$('#filtroAtosApresentante').val()||'';
          const os  =$('#filtroAtosOS').val()||'';
          window.dtAtos.column(0).search(func? '^'+escapeRegex(func)+'$':'', true,false);
          window.dtAtos.column(1).search(os? '^'+escapeRegex(os)+'$':'', true,false);
          window.dtAtos.column(2).search(apr? escapeRegex(apr):'', true,false);
          window.dtAtos.column(3).search(ato? '^'+escapeRegex(ato)+'$':'', true,false);
          window.dtAtos.draw(); recalcAtosTotals();
        }
        function recalcAtosTotals(){
          let soma=0, qtd=0;
          window.dtAtos.rows({search:'applied'}).every(function(){
            const r=this.data();
            qtd += parseFloat(String(r[5]).replace(/[^\d,.-]/g,'').replace('.','').replace(',','.'))||0;
            soma+= parseBRMoney(r[6]);
          });
          $('#qtdAtos').text(qtd);
          $('#totalAtos').text(formatCurrency(soma));
          $('#cardTotalAtos').text(formatCurrency(soma));
        }
        $('#filtroAtosAto,#filtroAtosApresentante,#filtroAtosOS,#filtroAtosFuncionario').off('change').on('change', applyAtosFilters);
        $('#btnLimparFiltrosAtos').off('click').on('click', function(){
          $('#filtrosAtosLiquidados select').val(''); applyAtosFilters();
        });

        // Atos Manuais
        let somaMan=0, qtdMan=0; $('#detalhesAtosManuais').empty();
        (d.atosManuais||[]).forEach(m=>{
          somaMan+=parseFloat(m.total||0);
          qtdMan +=parseFloat(m.quantidade_liquidada||0);
          $('#detalhesAtosManuais').append(`
            <tr>
              <td>${m.funcionario}</td>
              <td>${m.ordem_servico_id}</td>
              <td>${m.cliente}</td>
              <td>${m.ato}</td>
              <td>${m.descricao}</td>
              <td>${m.quantidade_liquidada}</td>
              <td>${formatCurrency(m.total)}</td>
            </tr>`);
        });
        $('#qtdAtosManuais').text(qtdMan);
        $('#totalAtosManuais').text(formatCurrency(somaMan));

        fillSelect('#filtroManuaisAto', distinctFrom(d.atosManuais,'ato'), 'Todos os Atos');
        fillSelect('#filtroManuaisApresentante', distinctFrom(d.atosManuais,'cliente'), 'Todos os Apresentantes');
        fillSelect('#filtroManuaisOS', distinctFrom(d.atosManuais,'ordem_servico_id'), 'Todas as O.S');
        fillSelect('#filtroManuaisFuncionario', distinctFrom(d.atosManuais,'funcionario'), 'Todos os Funcionários');

        if (window.dtAtosManuais) window.dtAtosManuais.destroy();
        window.dtAtosManuais = $('#tabelaAtosManuais').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[]})
          .on('draw', recalcManuaisTotals);
        function applyManuaisFilters(){
          const func=$('#filtroManuaisFuncionario').val()||'';
          const ato =$('#filtroManuaisAto').val()||'';
          const apr =$('#filtroManuaisApresentante').val()||'';
          const os  =$('#filtroManuaisOS').val()||'';
          window.dtAtosManuais.column(0).search(func? '^'+escapeRegex(func)+'$':'', true,false);
          window.dtAtosManuais.column(1).search(os? '^'+escapeRegex(os)+'$':'', true,false);
          window.dtAtosManuais.column(2).search(apr? escapeRegex(apr):'', true,false);
          window.dtAtosManuais.column(3).search(ato? '^'+escapeRegex(ato)+'$':'', true,false);
          window.dtAtosManuais.draw(); recalcManuaisTotals();
        }
        function recalcManuaisTotals(){
          let soma=0, qtd=0;
          window.dtAtosManuais.rows({search:'applied'}).every(function(){
            const r=this.data();
            qtd += parseFloat(String(r[5]).replace(/[^\d,.-]/g,'').replace('.','').replace(',','.'))||0;
            soma+= parseBRMoney(r[6]);
          });
          $('#qtdAtosManuais').text(qtd);
          $('#totalAtosManuais').text(formatCurrency(soma));
          $('#cardTotalAtosManuais').text(formatCurrency(soma));
        }
        $('#filtroManuaisAto,#filtroManuaisApresentante,#filtroManuaisOS,#filtroManuaisFuncionario').off('change').on('change', applyManuaisFilters);
        $('#btnLimparFiltrosManuais').off('click').on('click', function(){
          $('#filtrosAtosManuais select').val(''); applyManuaisFilters();
        });

        // Pagamentos
        let totalPag=0; const totalPorTipo={}; $('#detalhesPagamentos').empty();
        (d.pagamentos||[]).forEach(p=>{
          totalPag+=parseFloat(p.total_pagamento||0);
          totalPorTipo[p.forma_de_pagamento]=(totalPorTipo[p.forma_de_pagamento]||0)+parseFloat(p.total_pagamento||0);
          $('#detalhesPagamentos').append(`
            <tr>
              <td>${p.funcionario}</td>
              <td>${p.ordem_de_servico_id}</td>
              <td>${p.cliente}</td>
              <td>${p.forma_de_pagamento}</td>
              <td>${formatCurrency(p.total_pagamento)}</td>
            </tr>`);
        });
        $('#totalPagamentos').text(formatCurrency(totalPag));

        $('#detalhesTotalPorTipo').empty();
        let totalConta=0, totalEsp=0;
        Object.keys(totalPorTipo).forEach(tp=>{
          $('#detalhesTotalPorTipo').append(`<tr><td>${tp}</td><td>${formatCurrency(totalPorTipo[tp])}</td></tr>`);
          if (['PIX','Centrais Eletrônicas','Boleto','Transferência Bancária','Crédito','Débito'].includes(tp)) totalConta+=totalPorTipo[tp];
          else if (tp==='Espécie') totalEsp+=totalPorTipo[tp];
        });
        $('#cardTotalRecebidoConta').text(formatCurrency(totalConta));
        $('#cardTotalRecebidoEspecie').text(formatCurrency(totalEsp));
        $('#cardTotalRecebido').text(formatCurrency(totalConta+totalEsp));

        // Devoluções
        let totDev=0; $('#detalhesDevolucoes').empty();
        (d.devolucoes||[]).forEach(dev=>{
          totDev+=parseFloat(dev.total_devolucao||0);
          $('#detalhesDevolucoes').append(`
            <tr>
              <td>${dev.funcionario}</td>
              <td>${dev.ordem_de_servico_id}</td>
              <td>${dev.cliente}</td>
              <td>${dev.forma_devolucao}</td>
              <td>${formatCurrency(dev.total_devolucao)}</td>
            </tr>`);
        });
        $('#totalDevolucoes').text(formatCurrency(totDev));

        // Saídas
        let totSaidas=0; $('#detalhesSaidas').empty();
        (d.saidas||[]).forEach(s=>{
          totSaidas+=parseFloat(s.valor_saida||0);
          $('#detalhesSaidas').append(`
            <tr>
              <td>${s.funcionario}</td>
              <td>${s.titulo}</td>
              <td>${formatCurrency(s.valor_saida)}</td>
              <td>${s.forma_de_saida}</td>
              <td>${formatDateForDisplay(s.data_caixa)}</td>
              <td>${formatDateForDisplay2(s.data)}</td>
              <td>
                <button title="Visualizar" class="btn btn-info btn-sm" onclick="visualizarAnexoSaida('${s.caminho_anexo}','${s.funcionario}','${s.data_caixa}')">
                  <i class="fa fa-eye"></i>
                </button>
              </td>
            </tr>`);
        });
        $('#totalSaidas').text(formatCurrency(totSaidas));

        // Depósitos
        $('#detalhesDepositos').empty();
        (d.depositos||[]).forEach(dep=>{
          $('#detalhesDepositos').append(`
            <tr>
              <td>${dep.funcionario}</td>
              <td>${formatDateForDisplay(dep.data_caixa)}</td>
              <td>${formatDateForDisplay2(dep.data_cadastro)}</td>
              <td>${formatCurrency(dep.valor_do_deposito)}</td>
              <td>${dep.tipo_deposito}</td>
              <td>
                <button title="Visualizar" class="btn btn-info btn-sm" onclick="visualizarComprovante('${dep.caminho_anexo}','${dep.funcionario}','${dep.data_caixa}')">
                  <i class="fa fa-eye"></i>
                </button>
              </td>
            </tr>`);
        });

        // Saldo Transportado (listagem informativa - todos os status)
        let totTr=0; $('#detalhesSaldoTransportado').empty();
        (d.saldoTransportado||[]).forEach(t=>{
          totTr+=parseFloat(t.valor_transportado||0);
          const dcaixa = formatDateForDisplay(t.data_caixa);
          const dtrans = formatDateForDisplay(t.data_transporte);
          $('#detalhesSaldoTransportado').append(`
            <tr>
              <td>${dcaixa}</td>
              <td>${dtrans}</td>
              <td>${formatCurrency(t.valor_transportado)}</td>
              <td>${t.funcionario}</td>
              <td>${t.status}</td>
            </tr>`);
        });
        $('#totalSaldoTransportado').text(formatCurrency(totTr));

        // Abre modal + inicializa DataTables
        $('#detalhesModalPeriodo').modal('show');
        if (window.dtAtos) window.dtAtos.destroy();
        window.dtAtos = $('#tabelaAtos').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[]});
        if (window.dtAtosManuais) window.dtAtosManuais.destroy();
        window.dtAtosManuais = $('#tabelaAtosManuais').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[]});
        $('#tabelaPagamentos').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[]});
        $('#tabelaTotalPorTipo').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[]});
        $('#tabelaDevolucoes').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[]});
        $('#tabelaSaidas').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[]});
        $('#tabelaDepositos').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[]});
        $('#tabelaSaldoTransportado').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[]});
      },
      error:function(){ alert('Erro ao obter detalhes do período.'); }
    });
  };

  window.verDepositosPeriodo = function(di, df, func){
    $.ajax({
      url:'listar_depositos_unificado_periodo.php',
      type:'GET',
      data:{ data_inicial: di, data_final: df, funcionario: func },
      dataType:'json',
      success:function(r){
        if (r.error){ alert('Erro: '+r.error); return; }
        const arr=r.depositos||[];
        $('#detalhesDepositosPeriodo').empty();
        arr.forEach(dep=>{
          const dataCad = dep.data_cadastro ? new Date(dep.data_cadastro) : null;
          const dataCadFmt = dataCad ? (('0'+dataCad.getDate()).slice(-2)+'/'+('0'+(dataCad.getMonth()+1)).slice(-2)+'/'+dataCad.getFullYear()) : '';
          const d = new Date(dep.data_caixa+'T00:00:00');
          const dataCxFmt = ('0'+d.getUTCDate()).slice(-2)+'/'+('0'+(d.getUTCMonth()+1)).slice(-2)+'/'+d.getUTCFullYear();
          const podeExcluir = (dep.pode_excluir==1 || dep.pode_excluir===true);
          const delBtn = podeExcluir ? `<button title="Remover" class="btn btn-delete btn-sm" onclick="removerDeposito(${dep.id})"><i class="fa fa-trash"></i></button>` : '';
          const eyeBtn = dep.caminho_anexo ? `<button title="Visualizar" class="btn btn-info btn-sm" onclick="visualizarComprovante('${dep.caminho_anexo}','${dep.funcionario}','${dep.data_caixa}')"><i class="fa fa-eye"></i></button>` : '';
          $('#detalhesDepositosPeriodo').append(`
            <tr>
              <td>${dep.funcionario}</td>
              <td>${dataCxFmt}</td>
              <td>${dataCadFmt}</td>
              <td>${formatCurrency(dep.valor_do_deposito)}</td>
              <td>${dep.tipo_deposito}</td>
              <td>${eyeBtn} ${delBtn}</td>
            </tr>`);
        });
        $('#tabelaDepositosPeriodo').DataTable({language:{url:"../style/Portuguese-Brasil.json"}, destroy:true, pageLength:10, order:[], autoWidth:false, scrollX:true});
        $('#verDepositosPeriodoModal').modal('show');
      },
      error:function(){ alert('Erro ao obter depósitos do período.'); }
    });
  };

  window.visualizarComprovante = function(caminho, funcionario, data_caixa){
    var d = new Date(data_caixa+'T00:00:00');
    var day=('0'+d.getUTCDate()).slice(-2), month=('0'+(d.getUTCMonth()+1)).slice(-2), year=String(d.getUTCFullYear()).slice(-2);
    var dir = `anexos/${day}-${month}-${year}/${funcionario}/`;
    window.open(dir + caminho, '_blank');
  };
  window.visualizarAnexoSaida = function(caminho, funcionario, data_caixa){
    var dir = `anexos/${data_caixa}/${funcionario}/saidas/`;
    window.open(dir + caminho, '_blank');
  };

  window.removerDeposito = function(id){
    Swal.fire({
      title:'Deseja realmente remover este depósito?',
      icon:'warning',
      showCancelButton:true,
      confirmButtonColor:'#3085d6',
      cancelButtonColor:'#d33',
      confirmButtonText:'Sim, remover!',
      cancelButtonText:'Cancelar'
    }).then((res)=>{
      if(res.isConfirmed){
        $.post('remover_deposito.php', { id:id }, function(resp){
          if (resp && resp.success){
            Swal.fire({icon:'success', title:'Sucesso!', text:'Depósito removido com sucesso!', confirmButtonText:'OK'})
              .then(()=>{ location.reload(); });
          } else {
            Swal.fire({icon:'error', title:'Erro!', text:(resp && resp.error)?resp.error:'Erro ao remover depósito.', confirmButtonText:'OK'});
          }
        }, 'json');
      }
    });
  };

})();
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
