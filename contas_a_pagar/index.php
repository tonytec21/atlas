<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard();
require_once __DIR__ . '/config.php';
cap_ensure_schema();

$conn = cap_db();
$hoje = date('Y-m-d');
$cfg = cap_settings_get();
$CSRF = cap_csrf_token();
$CATS = cap_categorias();
$RECS = cap_recorrencias();
function hh($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ---------------- Filtros ---------------- */
$f_texto = trim((string)($_GET['texto'] ?? ''));
$f_cat   = trim((string)($_GET['categoria'] ?? ''));
$f_rec   = trim((string)($_GET['recorrencia'] ?? ''));
$f_mes   = trim((string)($_GET['mes'] ?? ''));            // formato YYYY-MM
$f_status= isset($_GET['status']) ? trim((string)$_GET['status']) : 'aberto'; // padrão: em aberto
$hasFilter = ($f_texto!=='' || $f_cat!=='' || $f_rec!=='' || $f_mes!=='' || (isset($_GET['status']) && $f_status!=='aberto'));

$where = []; $types = ''; $vals = [];
if ($f_texto !== '') { $where[] = "(titulo LIKE ? OR fornecedor LIKE ? OR descricao LIKE ?)"; $like = "%$f_texto%"; $types.='sss'; array_push($vals,$like,$like,$like); }
if ($f_cat !== '')   { $where[] = "categoria = ?"; $types.='s'; $vals[]=$f_cat; }
if ($f_rec !== '')   { $where[] = "recorrencia = ?"; $types.='s'; $vals[]=$f_rec; }
if ($f_mes !== '' && preg_match('~^\d{4}-\d{2}$~',$f_mes)) { $where[] = "DATE_FORMAT(data_vencimento,'%Y-%m') = ?"; $types.='s'; $vals[]=$f_mes; }
switch ($f_status) {
    case 'aberto':   $where[] = "status='Pendente'"; break;
    case 'vencidas': $where[] = "status='Pendente' AND data_vencimento < CURDATE()"; break;
    case 'pago':     $where[] = "status='Pago'"; break;
    case 'todas': default: break;
}
$sql = "SELECT * FROM contas_a_pagar" . (count($where) ? (" WHERE " . implode(' AND ', $where)) : "") . " ORDER BY data_vencimento ASC, id DESC";
$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$vals);
$stmt->execute();
$contas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ---------------- KPIs ---------------- */
function cap_scalar($conn,$sql){ $r=$conn->query($sql); $row=$r?$r->fetch_row():[0]; return $row?$row[0]:0; }
$kpi_aberto_val = (float)cap_scalar($conn, "SELECT COALESCE(SUM(valor),0) FROM contas_a_pagar WHERE status='Pendente'");
$kpi_aberto_qtd = (int)cap_scalar($conn, "SELECT COUNT(*) FROM contas_a_pagar WHERE status='Pendente'");
$kpi_venc_val   = (float)cap_scalar($conn, "SELECT COALESCE(SUM(valor),0) FROM contas_a_pagar WHERE status='Pendente' AND data_vencimento < CURDATE()");
$kpi_venc_qtd   = (int)cap_scalar($conn, "SELECT COUNT(*) FROM contas_a_pagar WHERE status='Pendente' AND data_vencimento < CURDATE()");
$diasAviso = max(1,(int)($cfg['dias_aviso'] ?? 7));
$kpi_prox_val   = (float)cap_scalar($conn, "SELECT COALESCE(SUM(valor),0) FROM contas_a_pagar WHERE status='Pendente' AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $diasAviso DAY)");
$kpi_prox_qtd   = (int)cap_scalar($conn, "SELECT COUNT(*) FROM contas_a_pagar WHERE status='Pendente' AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $diasAviso DAY)");
$kpi_pago_mes   = (float)cap_scalar($conn, "SELECT COALESCE(SUM(valor),0) FROM contas_a_pagar WHERE status='Pago' AND data_pagamento IS NOT NULL AND DATE_FORMAT(data_pagamento,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')");

/* Gráfico: em aberto por categoria */
$catLabels=[]; $catVals=[];
$rc = $conn->query("SELECT COALESCE(NULLIF(categoria,''),'Sem categoria') cat, SUM(valor) t FROM contas_a_pagar WHERE status='Pendente' GROUP BY cat ORDER BY t DESC");
while($rc && $row=$rc->fetch_assoc()){ $catLabels[]=$row['cat']; $catVals[]=(float)$row['t']; }

/* Gráfico: pagamentos últimos 6 meses */
$evLabels=[]; $evVals=[];
for($i=5;$i>=0;$i--){ $m=date('Y-m', strtotime("-$i month")); $evLabels[]=date('m/Y', strtotime($m.'-01'));
   $evVals[] = (float)cap_scalar($conn, "SELECT COALESCE(SUM(valor),0) FROM contas_a_pagar WHERE status='Pago' AND data_pagamento IS NOT NULL AND DATE_FORMAT(data_pagamento,'%Y-%m')='".$conn->real_escape_string($m)."'"); }

/* Doughnut: a vencer vs vencidas (valores) */
$aVencerVal = max(0, $kpi_aberto_val - $kpi_venc_val);

/* Contas virtuais (saldo vindo do módulo Controle de Caixa) */
$SALDOS = cap_saldos();
$temCaixa = cap_tem_deposito_caixa();
$FORMAS = cap_formas_pagamento();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas - Contas a Pagar</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php include(__DIR__ . '/complementos/style_padrao.php'); ?>
<style>
    :root{ --cap:#4f46e5; --cap2:#2563eb; }
    #main .container{ padding-bottom:120px; }
    /* KPIs */
    .kpi-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:14px; margin-bottom:16px; }
    .kpi{ background:#fff; border:1px solid #e5e9f0; border-radius:16px; padding:16px 18px; box-shadow:0 8px 22px rgba(15,23,42,.05); display:flex; gap:14px; align-items:center; }
    .kpi .ic{ width:48px;height:48px;border-radius:13px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;flex:0 0 auto; }
    .kpi .lb{ color:#64748b; font-size:.82rem; font-weight:600; } .kpi .vl{ font-size:1.35rem; font-weight:800; line-height:1.15; }
    .kpi .sub{ color:#94a3b8; font-size:.76rem; }
    .kpi.k-aberto .ic{ background:linear-gradient(135deg,#4f46e5,#2563eb);} .kpi.k-venc .ic{ background:linear-gradient(135deg,#ef4444,#b91c1c);}
    .kpi.k-prox .ic{ background:linear-gradient(135deg,#f59e0b,#d97706);} .kpi.k-pago .ic{ background:linear-gradient(135deg,#16a34a,#15803d);}
    body.dark-mode .kpi{ background:#23272a; border-color:rgba(255,255,255,.07); }
    /* contas virtuais */
    .vconta{ display:flex; gap:14px; align-items:center; background:#fff; border:1px solid #e5e9f0; border-radius:16px; padding:16px 18px; box-shadow:0 8px 22px rgba(15,23,42,.05); height:100%; }
    .vconta .vc-ic{ width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;flex:0 0 auto; }
    .vconta.especie .vc-ic{ background:linear-gradient(135deg,#16a34a,#15803d); } .vconta.banco .vc-ic{ background:linear-gradient(135deg,#2563eb,#4f46e5); }
    .vconta .vc-lb{ color:#64748b;font-size:.8rem;font-weight:700; } .vconta .vc-vl{ font-size:1.5rem;font-weight:800;line-height:1.15; }
    .vconta .vc-sub{ color:#94a3b8;font-size:.76rem; }
    body.dark-mode .vconta{ background:#23272a;border-color:rgba(255,255,255,.07); }
    /* saldo no modal de pagamento */
    .pg-saldo{ display:flex;gap:10px;align-items:center;padding:10px 12px;border-radius:12px;background:#f1f5f9;font-size:.85rem;font-weight:600; }
    .pg-saldo.neg{ background:#fee2e2;color:#b91c1c; } .pg-saldo.ok{ background:#dcfce7;color:#166534; }
    /* cards de gráfico */
    .chart-card{ background:#fff; border:1px solid #e5e9f0; border-radius:16px; padding:16px; box-shadow:0 8px 22px rgba(15,23,42,.05); height:100%; }
    .chart-card h6{ font-weight:800; margin:0 0 10px; font-size:.9rem; } .chart-card canvas{ max-height:240px; }
    body.dark-mode .chart-card{ background:#23272a; border-color:rgba(255,255,255,.07); }
    /* status badges */
    .st-badge{ display:inline-block; padding:3px 10px; border-radius:999px; font-size:.76rem; font-weight:700; }
    .st-Pendente{ background:#dbeafe; color:#1d4ed8; } .st-Atrasado{ background:#fee2e2; color:#b91c1c; } .st-Pago{ background:#dcfce7; color:#166534; }
    /* modal header padrão */
    .cap-modal .modal-header{ background:linear-gradient(135deg,var(--cap),var(--cap2)); color:#fff; border:0; padding:16px 20px; }
    .cap-close{ border:0;background:rgba(255,255,255,.18);color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer; }
    .cap-close:hover{ background:rgba(255,255,255,.34); transform:rotate(90deg); transition:.15s; }
    .cap-modal .modal-content{ border:0; border-radius:16px; overflow:hidden; }
    /* input-chip select nativo */
    .input-chip select, .input-chip input{ border:none;outline:none;width:100%;background:transparent;color:inherit; }
    /* dropzone (anexos) */
    .cap-dz{ border:2.5px dashed #c7d2fe; border-radius:16px; background:linear-gradient(180deg,#f8faff,#eef3ff); padding:26px 18px; text-align:center; cursor:pointer; transition:.2s; }
    .cap-dz:hover{ border-color:var(--cap2); } .cap-dz.drag{ border-color:var(--cap2); background:#e0e9ff; transform:scale(1.01); }
    .cap-dz .ic{ width:56px;height:56px;border-radius:50%;background:#dbeafe;color:var(--cap2);display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin:0 auto 10px; }
    .ax-list{ display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:10px; }
    .ax-item{ display:flex;align-items:center;gap:11px;padding:11px;border:1px solid #eef1f6;border-radius:14px;background:#fff; }
    .ax-item .fi{ width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.05rem;flex:0 0 auto; }
    .ax-item .nm{ font-weight:700;font-size:.86rem;word-break:break-word; } .ax-item .sub{ color:#94a3b8;font-size:.74rem; }
    .ax-item .acts button{ border:0;background:#eef2f7;color:#334155;width:32px;height:32px;border-radius:9px;cursor:pointer; margin-left:4px; }
    .ax-queue .q{ display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid #eef1f6;border-radius:10px;margin-top:8px; }
    .ax-queue .bar{ height:6px;border-radius:99px;background:#e2e8f0;overflow:hidden;margin-top:5px; } .ax-queue .bar>i{ display:block;height:100%;width:0;background:linear-gradient(90deg,var(--cap),var(--cap2)); }
    #axViewerBody iframe, #axViewerBody img{ width:100%;height:66vh;border:0;object-fit:contain;background:#f4f6fa;border-radius:12px; }
    body.dark-mode .ax-item{ background:#23272a;border-color:rgba(255,255,255,.07); }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">

        <!-- HERO -->
        <section class="page-hero">
            <div class="title-row">
                <div class="title-icon"><i class="fa fa-money"></i></div>
                <div style="flex:1;min-width:0">
                    <h1>Contas a Pagar</h1>
                    <div class="subtitle muted">Controle de despesas: cadastre contas recorrentes ou avulsas, acompanhe vencimentos e receba alertas por e-mail.</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary btn-pill" data-bs-toggle="modal" data-bs-target="#contaModal" onclick="capNovaConta()"><i class="fa fa-plus"></i> Nova conta</button>
                    <a class="btn btn-soft btn-pill" href="relatorios.php"><i class="fa fa-bar-chart"></i> Relatórios</a>
                    <a class="btn btn-soft btn-pill" href="extrato.php"><i class="fa fa-list"></i> Extrato</a>
                    <button class="btn btn-soft btn-pill" data-bs-toggle="modal" data-bs-target="#configModal"><i class="fa fa-cog"></i> Configurações</button>
                </div>
            </div>
        </section>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi k-aberto"><div class="ic"><i class="fa fa-wallet"></i></div><div><div class="lb">Em aberto</div><div class="vl"><?php echo cap_money($kpi_aberto_val); ?></div><div class="sub"><?php echo $kpi_aberto_qtd; ?> conta(s)</div></div></div>
            <div class="kpi k-venc"><div class="ic"><i class="fa fa-exclamation-triangle"></i></div><div><div class="lb">Vencidas</div><div class="vl"><?php echo cap_money($kpi_venc_val); ?></div><div class="sub"><?php echo $kpi_venc_qtd; ?> conta(s)</div></div></div>
            <div class="kpi k-prox"><div class="ic"><i class="fa fa-clock-o"></i></div><div><div class="lb">A vencer (<?php echo $diasAviso; ?> dias)</div><div class="vl"><?php echo cap_money($kpi_prox_val); ?></div><div class="sub"><?php echo $kpi_prox_qtd; ?> conta(s)</div></div></div>
            <div class="kpi k-pago"><div class="ic"><i class="fa fa-check-circle"></i></div><div><div class="lb">Pago no mês</div><div class="vl"><?php echo cap_money($kpi_pago_mes); ?></div><div class="sub"><?php echo date('m/Y'); ?></div></div></div>
        </div>

        <!-- CONTAS VIRTUAIS (saldo do cartório) -->
        <div class="row g-3 mb-1">
            <?php foreach (cap_contas_virtuais() as $cod => $m): $s = $SALDOS[$cod]; $neg = $s['saldo'] < 0; ?>
            <div class="col-12 col-md-6">
                <div class="vconta <?php echo $cod; ?>">
                    <div class="vc-ic"><i class="fa <?php echo hh($m['icone']); ?>"></i></div>
                    <div style="flex:1;min-width:0">
                        <div class="vc-lb">Conta virtual · <?php echo hh($m['nome']); ?></div>
                        <div class="vc-vl" style="<?php echo $neg?'color:#b91c1c':''; ?>"><?php echo cap_money($s['saldo']); ?></div>
                        <div class="vc-sub">Entradas <?php echo cap_money($s['entradas']); ?> · Saídas <?php echo cap_money($s['saidas']); ?></div>
                    </div>
                    <a class="btn btn-sm btn-soft btn-pill" href="extrato.php?conta=<?php echo $cod; ?>"><i class="fa fa-list"></i> Extrato</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$temCaixa): ?>
            <div class="alert alert-warning py-2 mt-2" style="font-size:.85rem"><i class="fa fa-exclamation-triangle"></i> Tabela <code>deposito_caixa</code> (Controle de Caixa) não encontrada — os saldos das contas virtuais aparecem zerados.</div>
        <?php endif; ?>
        <div class="row g-3 mb-1">
            <div class="col-12 col-lg-4"><div class="chart-card"><h6><i class="fa fa-pie-chart text-primary"></i> Situação (em aberto)</h6><canvas id="chartStatus"></canvas></div></div>
            <div class="col-12 col-lg-4"><div class="chart-card"><h6><i class="fa fa-tags text-primary"></i> Em aberto por categoria</h6><canvas id="chartCat"></canvas></div></div>
            <div class="col-12 col-lg-4"><div class="chart-card"><h6><i class="fa fa-line-chart text-primary"></i> Pagamentos (6 meses)</h6><canvas id="chartEvol"></canvas></div></div>
        </div>

        <!-- FILTROS -->
        <form id="searchForm" method="GET" class="filter-card mt-3">
            <div class="section-title">Filtros</div>
            <div class="section-sub">Refine por texto, categoria, recorrência, mês de vencimento e situação.</div>
            <div class="row">
                <div class="col-12 col-md-3 mb-3"><label class="form-label small text-muted mb-1">Buscar</label>
                    <div class="input-chip"><i class="fa fa-search"></i><input type="text" name="texto" placeholder="Título, fornecedor…" value="<?php echo hh($f_texto); ?>"></div></div>
                <div class="col-6 col-md-2 mb-3"><label class="form-label small text-muted mb-1">Categoria</label>
                    <div class="input-chip"><i class="fa fa-tag"></i><select name="categoria"><option value="">Todas</option>
                        <?php foreach($CATS as $c): ?><option value="<?php echo hh($c); ?>" <?php echo $f_cat===$c?'selected':''; ?>><?php echo hh($c); ?></option><?php endforeach; ?>
                    </select></div></div>
                <div class="col-6 col-md-2 mb-3"><label class="form-label small text-muted mb-1">Recorrência</label>
                    <div class="input-chip"><i class="fa fa-repeat"></i><select name="recorrencia"><option value="">Todas</option>
                        <?php foreach($RECS as $r): ?><option value="<?php echo hh($r); ?>" <?php echo $f_rec===$r?'selected':''; ?>><?php echo hh($r); ?></option><?php endforeach; ?>
                    </select></div></div>
                <div class="col-6 col-md-2 mb-3"><label class="form-label small text-muted mb-1">Mês (venc.)</label>
                    <div class="input-chip"><i class="fa fa-calendar"></i><input type="month" name="mes" value="<?php echo hh($f_mes); ?>"></div></div>
                <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">Situação</label>
                    <div class="input-chip"><i class="fa fa-flag"></i><select name="status">
                        <option value="aberto" <?php echo $f_status==='aberto'?'selected':''; ?>>Em aberto</option>
                        <option value="vencidas" <?php echo $f_status==='vencidas'?'selected':''; ?>>Vencidas</option>
                        <option value="pago" <?php echo $f_status==='pago'?'selected':''; ?>>Pagas</option>
                        <option value="todas" <?php echo $f_status==='todas'?'selected':''; ?>>Todas</option>
                    </select></div></div>
            </div>
            <div class="filter-actions mt-1">
                <button type="submit" class="btn btn-primary btn-pill"><i class="fa fa-filter"></i> Filtrar</button>
                <button type="button" class="btn btn-success btn-pill" data-bs-toggle="modal" data-bs-target="#contaModal" onclick="capNovaConta()"><i class="fa fa-plus"></i> Nova conta</button>
                <?php if ($hasFilter): ?><a href="index.php" class="btn btn-soft btn-pill"><i class="fa fa-times"></i> Limpar</a><?php endif; ?>
            </div>
        </form>

        <!-- TABELA -->
        <div class="table-responsive table-wrap mt-3">
            <h5 class="mb-2">Contas</h5>
            <table id="tabelaContas" class="table table-striped table-bordered data-layout" style="width:100%">
                <thead><tr>
                    <th>Vencimento</th><th>Título</th><th>Categoria</th><th>Fornecedor</th>
                    <th>Valor</th><th>Recorrência</th><th>Situação</th><th style="width:14%">Ações</th>
                </tr></thead>
                <tbody>
                <?php foreach ($contas as $c):
                    $stt = cap_status_efetivo($c); $pago = ($c['status']==='Pago'); ?>
                    <tr>
                        <td data-label="Vencimento" data-order="<?php echo date('Y-m-d', strtotime($c['data_vencimento'])); ?>"><?php echo date('d/m/Y', strtotime($c['data_vencimento'])); ?></td>
                        <td data-label="Título"><?php echo hh($c['titulo']); ?></td>
                        <td data-label="Categoria"><?php echo hh($c['categoria'] ?? ''); ?></td>
                        <td data-label="Fornecedor"><?php echo hh($c['fornecedor'] ?? ''); ?></td>
                        <td data-label="Valor" data-order="<?php echo (float)$c['valor']; ?>"><?php echo cap_money($c['valor']); ?></td>
                        <td data-label="Recorrência"><?php echo hh($c['recorrencia']); ?></td>
                        <td data-label="Situação"><span class="st-badge st-<?php echo $stt; ?>"><?php echo $stt; ?></span><?php if($pago && $c['forma_pagamento']): ?><div class="text-muted" style="font-size:.72rem;margin-top:2px"><i class="fa fa-<?php echo $c['conta_origem']==='especie'?'money':($c['conta_origem']==='banco'?'university':'circle-o'); ?>"></i> <?php echo hh($c['forma_pagamento']); ?></div><?php endif; ?></td>
                        <td data-cell="acoes">
                            <?php if (!$pago): ?>
                                <button type="button" class="btn btn-success btn-sm btn-table js-pagar" title="Registrar pagamento" data-id="<?php echo (int)$c['id']; ?>" data-titulo="<?php echo hh($c['titulo']); ?>" data-valor="<?php echo (float)$c['valor']; ?>"><i class="fa fa-check"></i></button>
                                <button type="button" class="btn btn-warning btn-sm btn-table js-editar" title="Editar" data-id="<?php echo (int)$c['id']; ?>"><i class="fa fa-pencil"></i></button>
                            <?php else: ?>
                                <span class="btn btn-sm btn-table" style="background:#dcfce7;color:#166534;cursor:default" title="Paga em <?php echo $c['data_pagamento']?date('d/m/Y',strtotime($c['data_pagamento'])):''; ?><?php echo $c['forma_pagamento']?' · '.hh($c['forma_pagamento']):''; ?>"><i class="fa fa-check-circle"></i></span>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary btn-sm btn-table js-anexos" title="Anexos" data-id="<?php echo (int)$c['id']; ?>" data-titulo="<?php echo hh($c['titulo']); ?>"><i class="fa fa-paperclip"></i></button>
                            <button type="button" class="btn btn-danger btn-sm btn-table js-excluir" title="Excluir" data-id="<?php echo (int)$c['id']; ?>"><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/complementos/modais.php'); ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
<script>
window.CAP = {
    csrf: <?php echo json_encode($CSRF); ?>,
    saldos: <?php echo json_encode($SALDOS); ?>,
    formas: <?php echo json_encode($FORMAS, JSON_UNESCAPED_UNICODE); ?>,
    contasNome: <?php echo json_encode(['especie'=>cap_nome_conta('especie'),'banco'=>cap_nome_conta('banco')], JSON_UNESCAPED_UNICODE); ?>,
    hoje: <?php echo json_encode(date('Y-m-d')); ?>,
    chartStatus: { aVencer: <?php echo json_encode(round($aVencerVal,2)); ?>, vencidas: <?php echo json_encode(round($kpi_venc_val,2)); ?> },
    chartCat: { labels: <?php echo json_encode($catLabels, JSON_UNESCAPED_UNICODE); ?>, vals: <?php echo json_encode($catVals); ?> },
    chartEvol: { labels: <?php echo json_encode($evLabels); ?>, vals: <?php echo json_encode($evVals); ?> }
};
</script>
<script src="complementos/app.js"></script>
</body>
</html>
