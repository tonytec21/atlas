<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/* ================================================================
   ENDPOINT AJAX: ?action=daily_stats
   ================================================================ */
if (isset($_GET['action']) && $_GET['action'] === 'daily_stats') {
    @ini_set('display_errors', 0);
    @ini_set('html_errors', 0);
    header('Content-Type: application/json; charset=utf-8');
    ob_start();

    $today      = date('Y-m-d');
    $start  = (isset($_GET['start'])  && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start'])) ? $_GET['start'] : date('Y-m-01');
    $end    = (isset($_GET['end'])    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end']))   ? $_GET['end']   : $today;
    $basis  = (isset($_GET['basis'])  && $_GET['basis'] === 'registro') ? 'registro' : 'cadastro';
    $status = (isset($_GET['status']) && $_GET['status'] === 'todos')   ? 'todos'    : 'ativos';
    $userParam = isset($_GET['user']) ? trim((string)$_GET['user']) : '';
    $applyUser = ($userParam !== '');

    $resp = ['ok' => true];

    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->set_charset('utf8mb4');

        if ($basis === 'cadastro') {
            $dateExprNasc = "DATE(data_cadastro)";
            $dateExprObit = "DATE(data_cadastro)";
            $dateExprCasa = "DATE(criado_em)";
            $whereNasc = "data_cadastro >= ? AND data_cadastro < ?";
            $whereObit = "data_cadastro >= ? AND data_cadastro < ?";
            $whereCasa = "criado_em >= ? AND criado_em < ?";
            $pStart = $start . ' 00:00:00';
            $pEnd   = date('Y-m-d', strtotime($end . ' +1 day')) . ' 00:00:00';
        } else {
            $dateExprNasc = "data_registro";
            $dateExprObit = "data_registro";
            $dateExprCasa = "data_registro";
            $whereNasc = "data_registro BETWEEN ? AND ?";
            $whereObit = "data_registro BETWEEN ? AND ?";
            $whereCasa = "data_registro BETWEEN ? AND ?";
            $pStart = $start;
            $pEnd   = $end;
        }

        $statusNasc = ($status === 'ativos') ? " AND status = 'ativo' " : "";
        $statusObit = ($status === 'ativos') ? " AND status = 'A' "     : "";
        $statusCasa = ($status === 'ativos') ? " AND status = 'ativo' " : "";

        $userFilter = $applyUser ? " AND funcionario = ? " : "";

        $funcExpr = "COALESCE(NULLIF(TRIM(funcionario),''),'Não informado')";

        // Helper query
        $queryDaily = function($dateExpr, $table, $where, $statusF, $userF, $ps, $pe, $up, $au) use ($conn) {
            $sql = "SELECT {$dateExpr} AS dia, COALESCE(NULLIF(TRIM(funcionario),''),'Não informado') AS func, COUNT(*) AS qtd
                    FROM {$table}
                    WHERE {$where} {$statusF} {$userF}
                    GROUP BY dia, func ORDER BY dia";
            $params = [$ps, $pe];
            if ($au) $params[] = $up;
            $types = str_repeat('s', count($params));
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
            $stmt->close();
            return $rows;
        };

        // Montar estrutura: dia -> func -> qtd, ranking, daily por func
        $buildType = function($rawRows) {
            $agg = [];
            foreach ($rawRows as $r) {
                $agg[$r['dia']][$r['func']] = ($agg[$r['dia']][$r['func']] ?? 0) + (int)$r['qtd'];
            }
            ksort($agg);

            $totByFunc = [];
            foreach ($agg as $dia => $funcs) {
                foreach ($funcs as $f => $q) {
                    $totByFunc[$f] = ($totByFunc[$f] ?? 0) + $q;
                }
            }
            arsort($totByFunc);

            $dates = array_keys($agg);
            $ranking = [];
            foreach ($totByFunc as $f => $total) {
                $daily = [];
                foreach ($dates as $dia) {
                    $daily[] = ['dia' => $dia, 'qtd' => $agg[$dia][$f] ?? 0];
                }
                $ranking[] = ['funcionario' => $f, 'total' => $total, 'daily' => $daily];
            }
            return ['dates' => $dates, 'ranking' => $ranking, 'grand_total' => array_sum($totByFunc)];
        };

        $rowsN = $queryDaily($dateExprNasc, 'indexador_nascimento', $whereNasc, $statusNasc, $userFilter, $pStart, $pEnd, $userParam, $applyUser);
        $rowsO = $queryDaily($dateExprObit, 'indexador_obito',      $whereObit, $statusObit, $userFilter, $pStart, $pEnd, $userParam, $applyUser);
        $rowsC = $queryDaily($dateExprCasa, 'indexador_casamento',  $whereCasa, $statusCasa, $userFilter, $pStart, $pEnd, $userParam, $applyUser);

        $resp['nascimento'] = $buildType($rowsN);
        $resp['casamento']  = $buildType($rowsC);
        $resp['obito']      = $buildType($rowsO);

    } catch (Throwable $e) {
        $resp = ['ok' => false, 'error' => $e->getMessage()];
    }

    $buf = trim(ob_get_clean());
    if ($buf !== '') $resp['debug'] = strip_tags($buf);
    echo json_encode($resp);
    exit;
}

/* ========================== Lista de usuários ========================== */
$USERS_LIST = [];
try {
    $rs = $conn->query("SELECT usuario, COALESCE(NULLIF(TRIM(nome_completo),''), usuario) AS nome FROM funcionarios ORDER BY nome ASC");
    while ($r = $rs->fetch_assoc()) { $USERS_LIST[] = $r; }
    $rs->free();
} catch (Throwable $e) { $USERS_LIST = []; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas — Relatório Detalhado</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <?php include(__DIR__ . '/../style/style_index.php'); ?>

    <style>
    /* ===================== LAYOUT ===================== */
    .page-back{ display:inline-flex; align-items:center; gap:8px; color:#6c757d; text-decoration:none; font-size:14px; font-weight:600; margin-bottom:18px; transition:.15s; }
    .page-back:hover{ color:#0d6efd; }
    .rel-title{ font-size:24px; font-weight:800; margin-bottom:6px; }
    .rel-subtitle{ color:#6c757d; font-size:14px; margin-bottom:24px; }

    /* ===================== FILTROS ===================== */
    .f-bar{
        display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;
        background:var(--card-bg,#fff); border:1px solid var(--card-border,#e9ecef);
        border-radius:18px; padding:18px; margin-bottom:24px;
        box-shadow:0 4px 20px rgba(0,0,0,.06);
    }
    body.dark-mode .f-bar{ background:#161b22; border-color:#2f3a46; box-shadow:none; }
    .f-group{ display:flex; flex-direction:column; gap:4px; }
    .f-group label{ font-size:11px; text-transform:uppercase; font-weight:700; color:#6c757d; letter-spacing:.4px; }
    .f-group input, .f-group select{
        height:40px; border-radius:12px; border:1px solid #e0e0e0; padding:0 12px; font-size:13px; min-width:150px;
    }
    body.dark-mode .f-group input, body.dark-mode .f-group select{ background:#0f141a; border-color:#2f3a46; color:#e0e0e0; }
    .f-bar .btn{ height:40px; border-radius:12px; font-weight:600; font-size:13px; display:flex; align-items:center; gap:6px; }

    /* ===================== SEÇÃO ===================== */
    .sec{
        background:var(--card-bg,#fff); border:1px solid var(--card-border,#e9ecef);
        border-radius:20px; padding:0; margin-bottom:22px; overflow:hidden;
        box-shadow:0 4px 20px rgba(0,0,0,.06);
    }
    body.dark-mode .sec{ background:#161b22; border-color:#2f3a46; box-shadow:none; }
    .sec-header{
        display:flex; justify-content:space-between; align-items:center;
        padding:18px 22px; cursor:pointer; user-select:none;
        border-bottom:1px solid transparent; transition:.15s;
    }
    .sec-header:hover{ background:rgba(13,110,253,.03); }
    .sec-header.open{ border-bottom-color:var(--card-border,#e9ecef); }
    body.dark-mode .sec-header.open{ border-bottom-color:#2f3a46; }
    .sec-header-left{ display:flex; align-items:center; gap:14px; }
    .sec-icon{
        width:42px; height:42px; border-radius:14px; display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:18px; flex-shrink:0;
    }
    .sec-label{ font-weight:700; font-size:17px; }
    .sec-badge{
        background:#eef3ff; color:#0d6efd; font-size:13px; font-weight:700;
        padding:4px 14px; border-radius:99px;
    }
    body.dark-mode .sec-badge{ background:#1c2533; }
    .sec-chev{ font-size:14px; color:#6c757d; transition:transform .25s; margin-left:10px; }
    .sec-chev.collapsed{ transform:rotate(-90deg); }
    .sec-body{ padding:20px 22px; }
    .sec-body.hidden{ display:none; }

    /* ===================== RANKING TABLE ===================== */
    .rk-table{ width:100%; border-collapse:separate; border-spacing:0 5px; font-size:14px; }
    .rk-table thead th{
        font-weight:700; color:#6c757d; font-size:11px; text-transform:uppercase;
        letter-spacing:.5px; padding:8px 14px; border:none;
    }
    .rk-table tbody tr{ background:#f9fafb; transition:.15s; }
    .rk-table tbody tr:hover{ background:#eef3ff; }
    body.dark-mode .rk-table tbody tr{ background:#0d1117; }
    body.dark-mode .rk-table tbody tr:hover{ background:#1c2533; }
    .rk-table tbody td{ padding:14px; border:none; vertical-align:middle; }
    .rk-table tbody td:first-child{ border-radius:14px 0 0 14px; }
    .rk-table tbody td:last-child{ border-radius:0 14px 14px 0; }
    .rk-table tfoot td{ padding:14px; font-weight:700; border-top:2px solid #e9ecef; }
    body.dark-mode .rk-table tfoot td{ border-top-color:#2f3a46; }

    .rk-badge{
        width:34px; height:34px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center;
        font-weight:800; font-size:13px; color:#fff;
    }
    .rk-1{ background:linear-gradient(135deg,#f5a623,#f7c948); }
    .rk-2{ background:linear-gradient(135deg,#9e9e9e,#bdbdbd); }
    .rk-3{ background:linear-gradient(135deg,#cd7f32,#e0a05e); }
    .rk-n{ background:#dee2e6; color:#495057; }

    .rk-name{ font-weight:600; }
    .rk-total{ font-weight:700; color:#0d6efd; font-size:16px; }
    .rk-pct{ color:#6c757d; font-weight:600; }

    .pbar{ height:10px; background:#e9ecef; border-radius:99px; overflow:hidden; min-width:100px; }
    body.dark-mode .pbar{ background:#2f3a46; }
    .pbar-fill{ height:100%; border-radius:99px; transition:width .4s ease; }
    .pbar-nasc{ background:linear-gradient(90deg,#198754,#39c076); }
    .pbar-casa{ background:linear-gradient(90deg,#e3786f,#ff8a80); }
    .pbar-obit{ background:linear-gradient(90deg,#6c757d,#9aa1a7); }

    /* botão expandir */
    .btn-exp{
        background:none; border:1px solid #e0e0e0; cursor:pointer; color:#0d6efd; font-size:13px;
        padding:5px 10px; border-radius:10px; transition:.15s; font-weight:600;
    }
    .btn-exp:hover{ background:#eef3ff; border-color:#0d6efd; }
    body.dark-mode .btn-exp{ border-color:#2f3a46; }
    body.dark-mode .btn-exp:hover{ background:#1c2533; }

    /* linha de detalhe diário */
    .daily-row td{ padding:0 !important; }
    .daily-inner{
        padding:14px 18px 18px 62px; border-top:1px dashed #e9ecef;
    }
    body.dark-mode .daily-inner{ border-top-color:#2f3a46; }
    .daily-label{ font-size:12px; font-weight:700; color:#6c757d; margin-bottom:8px; }

    .daily-grid{
        display:grid; grid-template-columns:repeat(auto-fill, minmax(90px, 1fr)); gap:5px;
    }
    .d-chip{
        background:#fff; border:1px solid #eee; border-radius:10px; padding:6px 8px;
        text-align:center; transition:.15s;
    }
    .d-chip:hover{ border-color:#0d6efd; transform:translateY(-1px); box-shadow:0 3px 10px rgba(13,110,253,.1); }
    body.dark-mode .d-chip{ background:#161b22; border-color:#2f3a46; }
    .d-chip .d-date{ font-size:11px; font-weight:700; color:#6c757d; }
    .d-chip .d-val{ font-size:17px; font-weight:800; color:#0d6efd; margin-top:1px; }
    .d-chip .d-val.zero{ color:#d0d0d0; }
    body.dark-mode .d-chip .d-val.zero{ color:#3a3f47; }

    /* gráfico diário */
    .chart-box{
        border:1px dashed var(--card-border,#e9ecef); border-radius:16px; padding:16px;
        margin-top:18px; background:var(--card-bg,#fff);
    }
    body.dark-mode .chart-box{ background:#0d1117; border-color:#2f3a46; }
    .chart-box-title{ font-weight:700; font-size:15px; margin-bottom:10px; display:flex; align-items:center; gap:8px; }

    /* empty state */
    .empty-state{ text-align:center; padding:40px 20px; color:#adb5bd; }
    .empty-state i{ font-size:40px; margin-bottom:10px; display:block; }

    /* loading */
    .loading-overlay{
        display:none; position:fixed; inset:0; background:rgba(255,255,255,.7); z-index:9999;
        justify-content:center; align-items:center;
    }
    body.dark-mode .loading-overlay{ background:rgba(0,0,0,.6); }
    .loading-overlay.show{ display:flex; }
    .loading-spinner{ font-size:36px; color:#0d6efd; animation:spin 1s linear infinite; }
    @keyframes spin{ to{ transform:rotate(360deg); } }
    </style>
</head>
<body class="light-mode">

<?php include(__DIR__ . '/../menu.php'); ?>

<div class="loading-overlay" id="loadingOverlay">
    <i class="fa fa-circle-o-notch loading-spinner"></i>
</div>

<div class="main-container">
    <a href="index.php" class="page-back"><i class="fa fa-arrow-left"></i> Voltar ao painel</a>
    <div class="rel-title">Relatório Detalhado de Desempenho</div>
    <div class="rel-subtitle">Acompanhe a produção diária de cada funcionário por tipo de registro</div>

    <!-- FILTROS -->
    <div class="f-bar">
        <div class="f-group">
            <label>Data inicial</label>
            <input type="date" id="fStart">
        </div>
        <div class="f-group">
            <label>Data final</label>
            <input type="date" id="fEnd">
        </div>
        <div class="f-group">
            <label>Base da data</label>
            <select id="fBasis">
                <option value="cadastro">Data de Cadastro</option>
                <option value="registro">Data de Registro</option>
            </select>
        </div>
        <div class="f-group">
            <label>Status</label>
            <select id="fStatus">
                <option value="ativos">Somente ativos</option>
                <option value="todos">Todos os status</option>
            </select>
        </div>
        <div class="f-group">
            <label>Funcionário</label>
            <select id="fUser">
                <option value="">Todos</option>
                <?php foreach ($USERS_LIST as $u): ?>
                    <option value="<?= htmlspecialchars($u['usuario']) ?>"><?= htmlspecialchars($u['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" id="btnApply"><i class="fa fa-search"></i> Consultar</button>
    </div>

    <!-- NASCIMENTO -->
    <div class="sec" id="secNasc">
        <div class="sec-header open" data-sec="nasc">
            <div class="sec-header-left">
                <div class="sec-icon" style="background:linear-gradient(135deg,#198754,#39c076);"><i class="fa fa-child"></i></div>
                <span class="sec-label">Nascimento</span>
                <span class="sec-badge" id="badgeNasc">0</span>
            </div>
            <i class="fa fa-chevron-down sec-chev" id="chevNasc"></i>
        </div>
        <div class="sec-body" id="bodyNasc">
            <table class="rk-table" id="tblNasc">
                <thead><tr><th style="width:50px">#</th><th>Operador</th><th style="text-align:center">Total</th><th style="text-align:center">%</th><th>Progresso</th><th style="width:80px"></th></tr></thead>
                <tbody></tbody>
                <tfoot><tr><td></td><td><strong>Total Geral</strong></td><td style="text-align:center" class="ft">0</td><td style="text-align:center">100%</td><td></td><td></td></tr></tfoot>
            </table>
            <div class="chart-box">
                <div class="chart-box-title"><i class="fa fa-line-chart" style="color:#39c076"></i> Evolução diária — Nascimento</div>
                <div style="position:relative;height:300px;"><canvas id="cNasc"></canvas></div>
            </div>
        </div>
    </div>

    <!-- CASAMENTO -->
    <div class="sec" id="secCasa">
        <div class="sec-header open" data-sec="casa">
            <div class="sec-header-left">
                <div class="sec-icon" style="background:linear-gradient(135deg,#e3786f,#ff8a80);"><i class="fa fa-heart"></i></div>
                <span class="sec-label">Casamento</span>
                <span class="sec-badge" id="badgeCasa">0</span>
            </div>
            <i class="fa fa-chevron-down sec-chev" id="chevCasa"></i>
        </div>
        <div class="sec-body" id="bodyCasa">
            <table class="rk-table" id="tblCasa">
                <thead><tr><th style="width:50px">#</th><th>Operador</th><th style="text-align:center">Total</th><th style="text-align:center">%</th><th>Progresso</th><th style="width:80px"></th></tr></thead>
                <tbody></tbody>
                <tfoot><tr><td></td><td><strong>Total Geral</strong></td><td style="text-align:center" class="ft">0</td><td style="text-align:center">100%</td><td></td><td></td></tr></tfoot>
            </table>
            <div class="chart-box">
                <div class="chart-box-title"><i class="fa fa-line-chart" style="color:#ff8a80"></i> Evolução diária — Casamento</div>
                <div style="position:relative;height:300px;"><canvas id="cCasa"></canvas></div>
            </div>
        </div>
    </div>

    <!-- ÓBITO -->
    <div class="sec" id="secObit">
        <div class="sec-header open" data-sec="obit">
            <div class="sec-header-left">
                <div class="sec-icon" style="background:linear-gradient(135deg,#6c757d,#9aa1a7);"><i class="fa fa-book"></i></div>
                <span class="sec-label">Óbito</span>
                <span class="sec-badge" id="badgeObit">0</span>
            </div>
            <i class="fa fa-chevron-down sec-chev" id="chevObit"></i>
        </div>
        <div class="sec-body" id="bodyObit">
            <table class="rk-table" id="tblObit">
                <thead><tr><th style="width:50px">#</th><th>Operador</th><th style="text-align:center">Total</th><th style="text-align:center">%</th><th>Progresso</th><th style="width:80px"></th></tr></thead>
                <tbody></tbody>
                <tfoot><tr><td></td><td><strong>Total Geral</strong></td><td style="text-align:center" class="ft">0</td><td style="text-align:center">100%</td><td></td><td></td></tr></tfoot>
            </table>
            <div class="chart-box">
                <div class="chart-box-title"><i class="fa fa-line-chart" style="color:#9aa1a7"></i> Evolução diária — Óbito</div>
                <div style="position:relative;height:300px;"><canvas id="cObit"></canvas></div>
            </div>
        </div>
    </div>
</div>

<br><br>
<?php include(__DIR__ . '/../rodape.php'); ?>

<script src="../script/jquery-3.6.0.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(function(){
    /* ---------- INIT ---------- */
    var now = new Date();
    var fmt = function(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); };
    var d30 = new Date(now); d30.setDate(d30.getDate()-30);
    $('#fStart').val(fmt(d30));
    $('#fEnd').val(fmt(now));

    var palette = ['#0d6efd','#39c076','#ff8a80','#ffc107','#6f42c1','#20c997','#fd7e14','#e83e8c','#17a2b8','#6610f2','#28a745','#dc3545','#795548','#607d8b','#9c27b0','#00bcd4'];
    var fmtN = function(n){ return (n||0).toLocaleString('pt-BR'); };
    var fmtD = function(d){ var p=d.split('-'); return p[2]+'/'+p[1]; };
    var fmtDFull = function(d){ var p=d.split('-'); return p[2]+'/'+p[1]+'/'+p[0]; };

    var charts = { nasc:null, casa:null, obit:null };

    /* ---------- TOGGLE SECTIONS ---------- */
    $(document).on('click','.sec-header',function(){
        var sec = $(this).data('sec');
        var $body = $(this).next('.sec-body');
        var $chev = $(this).find('.sec-chev');
        $body.toggleClass('hidden');
        $chev.toggleClass('collapsed');
        $(this).toggleClass('open');
    });

    /* ---------- TOGGLE DAILY DETAIL ---------- */
    $(document).on('click','.btn-exp',function(e){
        e.stopPropagation();
        var id = $(this).data('uid');
        var $row = $('#dr_'+id);
        var isOpen = $row.is(':visible');
        $row.toggle();
        $(this).html(isOpen ? '<i class="fa fa-calendar-o"></i> Diário' : '<i class="fa fa-times"></i> Fechar');
    });

    /* ---------- BUILD TABLE ---------- */
    function buildTable(tableId, badgeId, data, barClass, prefix){
        var $tb = $(tableId+' tbody').empty();
        var $ft = $(tableId+' .ft');
        var $badge = $(badgeId);

        if(!data || !data.ranking || !data.ranking.length){
            $tb.append('<tr><td colspan="6" class="empty-state"><i class="fa fa-inbox"></i>Nenhum registro no período</td></tr>');
            $ft.text('0'); $badge.text('0');
            return;
        }

        var grand = data.grand_total || 1;
        $ft.html('<strong class="rk-total">'+fmtN(grand)+'</strong>');
        $badge.text(fmtN(grand));

        $.each(data.ranking, function(idx, item){
            var pos = idx+1;
            var pct = ((item.total/grand)*100).toFixed(1);
            var bc = pos===1?'rk-1':pos===2?'rk-2':pos===3?'rk-3':'rk-n';
            var name = (item.funcionario||'N/I').toUpperCase();
            var uid = prefix+'_'+idx;

            var chips = '';
            if(item.daily && item.daily.length){
                chips = '<div class="daily-grid">';
                $.each(item.daily, function(i,d){
                    var vc = d.qtd===0?'d-val zero':'d-val';
                    chips += '<div class="d-chip"><div class="d-date">'+fmtDFull(d.dia)+'</div><div class="'+vc+'">'+d.qtd+'</div></div>';
                });
                chips += '</div>';
            }

            var html = '<tr>'+
                '<td><span class="rk-badge '+bc+'">'+pos+'º</span></td>'+
                '<td class="rk-name">'+name+'</td>'+
                '<td style="text-align:center"><span class="rk-total">'+fmtN(item.total)+'</span></td>'+
                '<td style="text-align:center" class="rk-pct">'+pct+'%</td>'+
                '<td><div class="pbar"><div class="pbar-fill '+barClass+'" style="width:'+pct+'%"></div></div></td>'+
                '<td><button class="btn-exp" data-uid="'+uid+'"><i class="fa fa-calendar-o"></i> Diário</button></td>'+
                '</tr>'+
                '<tr class="daily-row" id="dr_'+uid+'" style="display:none">'+
                '<td colspan="6"><div class="daily-inner">'+
                '<div class="daily-label"><i class="fa fa-calendar"></i> Produção diária — '+name+'</div>'+
                chips+
                '</div></td></tr>';
            $tb.append(html);
        });
    }

    /* ---------- BUILD CHART ---------- */
    function buildChart(canvasId, key, data){
        if(charts[key]){ charts[key].destroy(); charts[key]=null; }
        if(!data || !data.dates || !data.dates.length || !data.ranking || !data.ranking.length) return;

        var labels = data.dates.map(fmtD);
        var datasets = [];
        $.each(data.ranking, function(idx, item){
            datasets.push({
                label: (item.funcionario||'N/I').toUpperCase(),
                data: $.map(item.daily, function(d){ return d.qtd; }),
                borderColor: palette[idx % palette.length],
                backgroundColor: palette[idx % palette.length]+'18',
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 6,
                tension: 0.35,
                fill: false
            });
        });

        charts[key] = new Chart(document.getElementById(canvasId).getContext('2d'), {
            type:'line',
            data:{ labels:labels, datasets:datasets },
            options:{
                responsive:true, maintainAspectRatio:false,
                interaction:{ mode:'index', intersect:false },
                scales:{
                    x:{ ticks:{ autoSkip:true, maxRotation:45, minRotation:0 }, grid:{ display:false } },
                    y:{ beginAtZero:true, ticks:{ precision:0 }, title:{ display:true, text:'Registros' } }
                },
                plugins:{
                    legend:{ position:'bottom', labels:{ usePointStyle:true, padding:14 } },
                    tooltip:{
                        mode:'index', intersect:false,
                        callbacks:{
                            title:function(items){ if(!items.length) return ''; return data.dates[items[0].dataIndex]||''; },
                            footer:function(items){ var s=0; $.each(items,function(i,it){ s+=it.parsed.y||0; }); return 'Total do dia: '+s; }
                        }
                    }
                }
            }
        });
    }

    /* ---------- FETCH ---------- */
    function fetchData(){
        $('#loadingOverlay').addClass('show');
        $.ajax({
            url:'relatorio_detalhado.php',
            type:'GET', dataType:'json', cache:false,
            data:{
                action:'daily_stats',
                start:$('#fStart').val(),
                end:$('#fEnd').val(),
                basis:$('#fBasis').val(),
                status:$('#fStatus').val(),
                user:$('#fUser').val()
            },
            success:function(r){
                if(r.ok===false){ console.error(r); return; }
                buildTable('#tblNasc','#badgeNasc', r.nascimento, 'pbar-nasc','n');
                buildTable('#tblCasa','#badgeCasa', r.casamento,  'pbar-casa','c');
                buildTable('#tblObit','#badgeObit', r.obito,      'pbar-obit','o');
                buildChart('cNasc','nasc', r.nascimento);
                buildChart('cCasa','casa', r.casamento);
                buildChart('cObit','obit', r.obito);
            },
            error:function(xhr){ console.error(xhr.responseText); },
            complete:function(){ $('#loadingOverlay').removeClass('show'); }
        });
    }

    $('#btnApply').on('click', fetchData);
    fetchData();

    $('.mode-switch').on('click',function(){ $('body').toggleClass('dark-mode light-mode'); });
});
</script>
</body>
</html>
