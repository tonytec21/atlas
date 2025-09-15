<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/* ========= Descoberta da conexão (MySQLi ou PDO) ========= */
function __atlas_classify_connection($c) {
    if ($c instanceof mysqli) return ['driver' => 'mysqli', 'conn' => $c];
    if ($c instanceof PDO)    return ['driver' => 'pdo',    'conn' => $c];
    throw new Error('Tipo de conexão não suportado. Use MySQLi ou PDO.');
}
function atlasDb() {
    if (function_exists('getDatabaseConnection')) {
        $c = getDatabaseConnection();
        if ($c) return __atlas_classify_connection($c);
    }
    foreach (['conn','mysqli','db','pdo','cnx','conexao'] as $name) {
        if (isset($GLOBALS[$name]) && $GLOBALS[$name]) {
            return __atlas_classify_connection($GLOBALS[$name]);
        }
    }
    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
        $m = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($m && !$m->connect_errno) {
            return ['driver' => 'mysqli', 'conn' => $m];
        }
    }
    throw new Error('Conexão não encontrada. Defina getDatabaseConnection() OU uma variável $conn/$pdo no db_connection.php.');
}

/* ============================================================
   ENDPOINT AJAX: /index.php?action=stats
   PHP 8+ — retorna JSON (ok:true|false).
   Parâmetros:
     - start  (YYYY-MM-DD)
     - end    (YYYY-MM-DD)
     - basis  ('cadastro' | 'registro')  -> padrão: cadastro
     - status ('ativos' | 'todos')       -> padrão: ativos
     - user   (usuario em funcionarios)  -> opcional
   ============================================================ */
if (isset($_GET['action']) && $_GET['action'] === 'stats') {
    @ini_set('display_errors', 0);
    @ini_set('html_errors', 0);
    header('Content-Type: application/json; charset=utf-8');
    ob_start();

    $today      = date('Y-m-d');
    $firstMonth = date('Y-m-01');

    $start  = (isset($_GET['start'])  && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start'])) ? $_GET['start'] : $firstMonth;
    $end    = (isset($_GET['end'])    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end']))   ? $_GET['end']   : $today;
    $basis  = (isset($_GET['basis'])  && $_GET['basis'] === 'registro') ? 'registro' : 'cadastro'; // padrão: cadastro
    $status = (isset($_GET['status']) && $_GET['status'] === 'todos')   ? 'todos'    : 'ativos';
    $userParam = isset($_GET['user']) ? trim((string)$_GET['user']) : '';
    $applyUser = ($userParam !== '');

    $resp = ['ok' => true];

    try {
        $db = atlasDb();
        $driver = $db['driver'];
        $conn   = $db['conn'];

        if ($driver === 'mysqli') {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            if (method_exists($conn, 'set_charset')) { $conn->set_charset('utf8mb4'); }
        } else { // PDO
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            try { $conn->exec("SET NAMES utf8mb4"); } catch (Throwable $e) {}
        }

        /* ===== Predicados de data =====
           Cadastro (TIMESTAMP): >= start 00:00:00 AND < end(+1 dia) 00:00:00
           Registro (DATE): BETWEEN start AND end

           Tabelas/colunas:
            - indexador_nascimento: data_cadastro (TIMESTAMP) / data_registro (DATE)
            - indexador_obito:      data_cadastro (TIMESTAMP) / data_registro (DATE)
            - indexador_casamento:  criado_em (TIMESTAMP)     / data_registro (DATE)
        */
        if ($basis === 'cadastro') {
            $colNasc = 'data_cadastro';
            $colObit = 'data_cadastro';
            $colCasa = 'criado_em';

            $whereNascDate = "{$colNasc} >= ? AND {$colNasc} < ?";
            $whereObitDate = "{$colObit} >= ? AND {$colObit} < ?";
            $whereCasaDate = "{$colCasa} >= ? AND {$colCasa} < ?";

            $pStart = $start . ' 00:00:00';
            $pEnd   = date('Y-m-d', strtotime($end . ' +1 day')) . ' 00:00:00';
        } else {
            $colNasc = 'data_registro';
            $colObit = 'data_registro';
            $colCasa = 'data_registro';

            $whereNascDate = "{$colNasc} BETWEEN ? AND ?";
            $whereObitDate = "{$colObit} BETWEEN ? AND ?";
            $whereCasaDate = "{$colCasa} BETWEEN ? AND ?";

            $pStart = $start;
            $pEnd   = $end;
        }

        $statusNasc = ($status === 'ativos') ? " AND status = 'ativo' " : "";
        $statusObit = ($status === 'ativos') ? " AND status = 'A' "     : "";
        $statusCasa = ($status === 'ativos') ? " AND status = 'ativo' " : "";

        $userFilterNasc = $applyUser ? " AND funcionario = ? " : "";
        $userFilterObit = $applyUser ? " AND funcionario = ? " : "";
        $userFilterCasa = $applyUser ? " AND funcionario = ? " : "";

        // ---------- Helpers dinâmicos ----------
        $readCount = function(string $sql, array $params) use ($driver, $conn) {
            if ($driver === 'mysqli') {
                $stmt = $conn->prepare($sql);
                if (!empty($params)) { $types = str_repeat('s', count($params)); $stmt->bind_param($types, ...$params); }
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                return (int)($row['total'] ?? 0);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int)($row['total'] ?? 0);
            }
        };
        $readGroup = function(string $sql, array $params) use ($driver, $conn) {
            if ($driver === 'mysqli') {
                $stmt = $conn->prepare($sql);
                if (!empty($params)) { $types = str_repeat('s', count($params)); $stmt->bind_param($types, ...$params); }
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = [];
                while ($r = $res->fetch_assoc()) {
                    $rows[] = ['label' => $r['label'], 'qtd' => (int)$r['qtd']];
                }
                $stmt->close();
                return $rows;
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                return array_map(fn($r)=>['label'=>$r['label'],'qtd'=>(int)$r['qtd']], $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
        };

        // ---------- Mapa usuario -> nome_completo ----------
        $userMap = [];
        try {
            $sqlUsers = "SELECT usuario, COALESCE(NULLIF(TRIM(nome_completo),''), usuario) AS nome FROM funcionarios";
            if ($driver === 'mysqli') {
                $rs = $conn->query($sqlUsers);
                while ($r = $rs->fetch_assoc()) { $userMap[(string)$r['usuario']] = (string)$r['nome']; }
                if ($rs instanceof mysqli_result) { $rs->free(); }
            } else {
                $rs = $conn->query($sqlUsers);
                foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) { $userMap[(string)$r['usuario']] = (string)$r['nome']; }
            }
        } catch (Throwable $e) { /* ignora */ }

        // ---------- Totais ----------
        $sqlNasc = "SELECT COUNT(*) AS total
                    FROM indexador_nascimento
                    WHERE {$whereNascDate} {$statusNasc} {$userFilterNasc}";
        $paramsNasc = [$pStart, $pEnd];
        if ($applyUser) { $paramsNasc[] = $userParam; }
        $totNasc = $readCount($sqlNasc, $paramsNasc);

        $sqlObit = "SELECT COUNT(*) AS total
                    FROM indexador_obito
                    WHERE {$whereObitDate} {$statusObit} {$userFilterObit}";
        $paramsObit = [$pStart, $pEnd];
        if ($applyUser) { $paramsObit[] = $userParam; }
        $totObit = $readCount($sqlObit, $paramsObit);

        $sqlCasa = "SELECT COUNT(*) AS total
                    FROM indexador_casamento
                    WHERE {$whereCasaDate} {$statusCasa} {$userFilterCasa}";
        $paramsCasa = [$pStart, $pEnd];
        if ($applyUser) { $paramsCasa[] = $userParam; }
        $totCasa = $readCount($sqlCasa, $paramsCasa);

        // ---------- Por funcionário ----------
        $funcExpr = "COALESCE(NULLIF(TRIM(funcionario),''),'Não informado')";

        $sqlGN = "SELECT {$funcExpr} AS label, COUNT(*) AS qtd
                  FROM indexador_nascimento
                  WHERE {$whereNascDate} {$statusNasc} {$userFilterNasc}
                  GROUP BY {$funcExpr}
                  ORDER BY qtd DESC";
        $paramsGN = [$pStart, $pEnd];
        if ($applyUser) { $paramsGN[] = $userParam; }
        $rowsN = $readGroup($sqlGN, $paramsGN);

        $sqlGO = "SELECT {$funcExpr} AS label, COUNT(*) AS qtd
                  FROM indexador_obito
                  WHERE {$whereObitDate} {$statusObit} {$userFilterObit}
                  GROUP BY {$funcExpr}
                  ORDER BY qtd DESC";
        $paramsGO = [$pStart, $pEnd];
        if ($applyUser) { $paramsGO[] = $userParam; }
        $rowsO = $readGroup($sqlGO, $paramsGO);

        $sqlGC = "SELECT {$funcExpr} AS label, COUNT(*) AS qtd
                  FROM indexador_casamento
                  WHERE {$whereCasaDate} {$statusCasa} {$userFilterCasa}
                  GROUP BY {$funcExpr}
                  ORDER BY qtd DESC";
        $paramsGC = [$pStart, $pEnd];
        if ($applyUser) { $paramsGC[] = $userParam; }
        $rowsC = $readGroup($sqlGC, $paramsGC);

        $agg = [];
        foreach ($rowsN as $r) {
            $f = $r['label'];
            if (!isset($agg[$f])) $agg[$f] = ['nascimento'=>0,'casamento'=>0,'obito'=>0,'total'=>0];
            $agg[$f]['nascimento'] = (int)$r['qtd'];
            $agg[$f]['total']     += (int)$r['qtd'];
        }
        foreach ($rowsC as $r) {
            $f = $r['label'];
            if (!isset($agg[$f])) $agg[$f] = ['nascimento'=>0,'casamento'=>0,'obito'=>0,'total'=>0];
            $agg[$f]['casamento'] = (int)$r['qtd'];
            $agg[$f]['total']    += (int)$r['qtd'];
        }
        foreach ($rowsO as $r) {
            $f = $r['label'];
            if (!isset($agg[$f])) $agg[$f] = ['nascimento'=>0,'casamento'=>0,'obito'=>0,'total'=>0];
            $agg[$f]['obito'] = (int)$r['qtd'];
            $agg[$f]['total']+= (int)$r['qtd'];
        }

        uasort($agg, fn($a,$b)=>$b['total']<=>$a['total']);

        $funcionarios = [];
        foreach ($agg as $usuarioOuNI => $vals) {
            $rotuloGrafico = $usuarioOuNI;
            $funcionarios[] = [
                'usuario'     => ($usuarioOuNI === 'Não informado') ? null : $usuarioOuNI,
                'funcionario' => $rotuloGrafico,
                'nascimento'  => (int)$vals['nascimento'],
                'casamento'   => (int)$vals['casamento'],
                'obito'       => (int)$vals['obito'],
                'total'       => (int)$vals['total'],
            ];
        }

        $resp['filters'] = ['start'=>$start,'end'=>$end,'basis'=>$basis,'status'=>$status,'user'=>$userParam];
        $resp['totals']  = [
            'nascimento'=>$totNasc,
            'casamento' =>$totCasa,
            'obito'     =>$totObit,
            'total'     =>$totNasc + $totCasa + $totObit
        ];
        $resp['by_funcionario'] = $funcionarios;

    } catch (Throwable $e) {
        $resp = ['ok'=>false,'message'=>'Falha ao calcular estatísticas.','error'=>$e->getMessage(),'type'=>get_class($e)];
    }

    $buffer = trim(ob_get_clean());
    if ($buffer !== '') { $resp['debug'] = strip_tags($buffer); }

    echo json_encode($resp);
    exit;
}

/* ========================== Lista de usuários (para o filtro) ========================== */
$USERS_LIST = [];
try {
    $dbL = atlasDb();
    $driverL = $dbL['driver'];
    $connL   = $dbL['conn'];
    $sqlUsersList = "SELECT usuario, COALESCE(NULLIF(TRIM(nome_completo),''), usuario) AS nome
                     FROM funcionarios
                     ORDER BY nome ASC";
    if ($driverL === 'mysqli') {
        $rs = $connL->query($sqlUsersList);
        while ($r = $rs->fetch_assoc()) { $USERS_LIST[] = ['usuario'=>$r['usuario'], 'nome'=>$r['nome']]; }
        if ($rs instanceof mysqli_result) { $rs->free(); }
    } else {
        $rs = $connL->query($sqlUsersList);
        foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) { $USERS_LIST[] = ['usuario'=>$r['usuario'], 'nome'=>$r['nome']]; }
    }
} catch (Throwable $e) {
    $USERS_LIST = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Central de Acesso - Indexador</title>

    <!-- CSS base -->
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">

    <!-- Estilos globais do Hub -->
    <?php include(__DIR__ . '/../style/style_index.php'); ?>

    <style>
        /* ======================= BUSCA / GRID ======================= */
        .search-container { margin-bottom: 30px; }
        .search-box{
            width:100%;max-width:800px;padding:12px 20px;border-radius:100px;
            border:1px solid #e0e0e0;box-shadow:0 2px 5px rgba(0,0,0,.05);
            font-size:16px;background-image:url('../style/img/search-icon.png');
            background-repeat:no-repeat;background-position:15px center;background-size:16px;
            padding-left:45px;display:block;margin:0 auto;
        }
        .search-box:focus{outline:none;border-color:#0d6efd;box-shadow:0 2px 8px rgba(13,110,253,.15);}
        body.dark-mode .search-box{background:#22272e;border-color:#2f3a46;color:#e0e0e0;box-shadow:none;}

        /* ======================= DASHBOARD ======================= */
        .dashboard { margin-top: 10px; margin-bottom: 35px; }
        .dash-card{
            border:1px solid var(--card-border,#e9ecef);
            border-radius:24px;padding:18px;
            background:linear-gradient(180deg,var(--card-bg,#fff) 0%,rgba(255,255,255,.92) 100%);
            box-shadow:0 10px 30px rgba(0,0,0,.08);margin-bottom:16px;
        }
        body.dark-mode .dash-card{
            background:linear-gradient(180deg,#161b22 0%, rgba(22,27,34,.92) 100%);
            border-color:#2f3a46; box-shadow:none;
        }

        /* ==== Filtros ==== */
        .filters-grid{
            display:grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap:16px;
            align-items:start;   /* alinhamento pelo topo para evitar sobreposição visual */
            grid-auto-rows:minmax(0, auto);
        }
        .filter-col{ grid-column: span 3; box-sizing:border-box; }
        .filter-col.user { grid-column: span 3; }
        .filter-col.wide{ grid-column: span 6; }
        .filter-col.actions{ grid-column: span 3; display:flex; gap:10px; align-self:start; }
        @media (max-width:1200px){
            .filter-col{grid-column: span 6;}
            .filter-col.wide{grid-column: span 6;}
            .filter-col.actions{grid-column: span 6;}
        }
        @media (max-width:576px){
            .filter-col, .filter-col.wide, .filter-col.actions{grid-column: span 12;}
        }

        .filter-label{ font-size:.8rem; color:#6c757d; margin-bottom:6px; font-weight:600; display:block; }
        .input-icon{ position:relative; }
        .input-icon > i{
            position:absolute; left:12px; top:50%; transform:translateY(-50%); opacity:.6; pointer-events:none;
        }
        .input-icon .filter-control{
            padding-left:38px; border-radius:14px; height:46px; border:1px solid #e6e6e6; width:100%;
        }
        body.dark-mode .input-icon .filter-control{ background:#0f141a; border-color:#2f3a46; color:#e0e0e0; }

        .quick-ranges{ display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
        .chip{
            border-radius:999px; padding:6px 12px; border:1px solid #e6e6e6; background:#fff;
            font-size:.85rem; cursor:pointer; transition:.15s; white-space:nowrap;
        }
        .chip:hover{ transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.08); }
        .chip.active{ background:#0d6efd; color:#fff; border-color:#0d6efd; }
        body.dark-mode .chip{ background:#0f141a; border-color:#2f3a46; color:#cfd3d7; }
        body.dark-mode .chip.active{ background:#0d6efd; color:#fff; border-color:#0d6efd; }

        .btn-modern{ border-radius:14px; height:46px; display:flex; align-items:center; justify-content:center; gap:8px; font-weight:600; }

        /* ==== KPIs ==== */
        .kpi-grid{ display:grid; grid-template-columns: repeat(4,1fr); grid-gap:12px; margin-top:6px; }
        @media (max-width:992px){ .kpi-grid{ grid-template-columns: repeat(2,1fr); } }
        @media (max-width:576px){ .kpi-grid{ grid-template-columns: 1fr; } }
        .kpi{ border-radius:18px; padding:18px; color:#fff; display:flex; align-items:center; justify-content:space-between; min-height:86px; }
        .kpi .kpi-label{ font-size:14px; opacity:.9; }
        .kpi .kpi-value{ font-size:28px; font-weight:700; }
        .kpi-primary   { background:linear-gradient(135deg,#0d6efd,#4da3ff); }
        .kpi-success   { background:linear-gradient(135deg,#198754,#39c076); }
        .kpi-secondary { background:linear-gradient(135deg,#6c757d,#9aa1a7); }
        .kpi-pink      { background:linear-gradient(135deg,#e3786f,#ff8a80); }

        /* ==== Gráficos ==== */
        .charts-grid{ display:grid; grid-template-columns: 1.1fr 1.9fr; grid-gap:16px; margin-top:16px; }
        @media (max-width:992px){ .charts-grid{ grid-template-columns: 1fr; } }
        .chart-card{ border:1px dashed var(--card-border,#e9ecef); border-radius:20px; padding:16px; background:var(--card-bg,#fff); }
        body.dark-mode .chart-card{ background:#0f141a; border-color:#2f3a46; }
        .chart-title{ font-weight:600; font-size:16px; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
        .chart-wrap{ position:relative; width:100%; height:350px; }

        #sortable-cards{ margin-top: 18px; }
    </style>
</head>
<body class="light-mode">

<?php include(__DIR__ . '/../menu.php'); ?>

<div class="main-container">
    <h1 class="page-title"></h1>
    <div class="title-divider"></div>

    <!-- ===================== DASHBOARD / GRÁFICOS ===================== -->
    <div class="dashboard">
        <div class="dash-card">
            <!-- Filtros -->
            <div class="filters-grid">
                <div class="filter-col">
                    <span class="filter-label">Data inicial</span>
                    <div class="input-icon">
                        <i class="fa fa-calendar-o"></i>
                        <input type="date" id="fStart" class="form-control filter-control">
                    </div>
                </div>

                <div class="filter-col">
                    <span class="filter-label">Data final</span>
                    <div class="input-icon">
                        <i class="fa fa-calendar"></i>
                        <input type="date" id="fEnd" class="form-control filter-control">
                    </div>
                </div>

                <div class="filter-col">
                    <span class="filter-label">Base da data</span>
                    <div class="input-icon">
                        <i class="fa fa-database"></i>
                        <select id="fBasis" class="form-select filter-control" style="padding-left:38px;">
                            <option value="cadastro">Data de Cadastro</option>
                            <option value="registro">Data de Registro</option>
                        </select>
                    </div>
                </div>

                <div class="filter-col">
                    <span class="filter-label">Status</span>
                    <div class="input-icon">
                        <i class="fa fa-filter"></i>
                        <select id="fStatus" class="form-select filter-control" style="padding-left:38px;">
                            <option value="ativos">Somente ativos</option>
                            <option value="todos">Todos os status</option>
                        </select>
                    </div>
                </div>

                <div class="filter-col user">
                    <span class="filter-label">Usuário</span>
                    <div class="input-icon">
                        <i class="fa fa-user"></i>
                        <select id="fUser" class="form-select filter-control" style="padding-left:38px;">
                            <option value="">Todos os usuários</option>
                            <?php foreach ($USERS_LIST as $u): ?>
                                <option value="<?php echo htmlspecialchars($u['usuario']); ?>">
                                    <?php echo htmlspecialchars($u['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-col wide">
                    <span class="filter-label">Períodos rápidos</span>
                    <div class="quick-ranges">
                        <span class="chip" data-range="7">Últimos 7 dias</span>
                        <span class="chip" data-range="15">Últimos 15 dias</span>
                        <span class="chip active" data-range="30">Últimos 30 dias</span>
                        <span class="chip" data-range="this_month">Este mês</span>
                        <span class="chip" data-range="last_month">Mês passado</span>
                        <span class="chip" data-range="ytd">Ano atual</span>
                    </div>
                </div>

                <div class="filter-col actions">
                    <button id="btnApply" class="btn btn-primary btn-modern w-100">
                        <i class="fa fa-line-chart"></i> Aplicar filtros
                    </button>
                    <button id="btnReset" class="btn btn-outline-secondary btn-modern w-100">
                        <i class="fa fa-refresh"></i> Resetar
                    </button>
                </div>
            </div>

            <!-- KPIs -->
            <div class="kpi-grid">
                <div class="kpi kpi-success">
                    <div>
                        <div class="kpi-label">Nascimentos</div>
                        <div class="kpi-value" id="kpiNascimento">0</div>
                    </div>
                    <i class="fa fa-child fa-2x" aria-hidden="true"></i>
                </div>
                <div class="kpi kpi-pink">
                    <div>
                        <div class="kpi-label">Casamentos</div>
                        <div class="kpi-value" id="kpiCasamento">0</div>
                    </div>
                    <i class="fa fa-heart fa-2x" aria-hidden="true"></i>
                </div>
                <div class="kpi kpi-secondary">
                    <div>
                        <div class="kpi-label">Óbitos</div>
                        <div class="kpi-value" id="kpiObito">0</div>
                    </div>
                    <i class="fa fa-book fa-2x" aria-hidden="true"></i>
                </div>
                <div class="kpi kpi-primary">
                    <div>
                        <div class="kpi-label">Total</div>
                        <div class="kpi-value" id="kpiTotal">0</div>
                    </div>
                    <i class="fa fa-bar-chart fa-2x" aria-hidden="true"></i>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-title">
                        <i class="fa fa-pie-chart"></i> Quantitativo por tipo de ato
                    </div>
                    <div class="chart-wrap">
                        <canvas id="chartTipos"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-title">
                        <i class="fa fa-users"></i> Quantitativo por funcionário (Empilhado)
                    </div>
                    <div class="chart-wrap">
                        <canvas id="chartFuncionarios"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- =================== /DASHBOARD / GRÁFICOS =================== -->

    <!-- Busca -->
    <div class="search-container">
        <input type="text" class="search-box" id="searchModules" placeholder="Buscar módulos...">
    </div>

    <div id="sortable-cards">
        <!-- Nascimento -->
        <div class="module-card" id="card-nascimento">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-agenda">
                    <i class="fa fa-child"></i>
                </div>
            </div>
            <h3 class="card-title">Nascimento</h3>
            <p class="card-description">Indexe e pesquise registros de nascimento.</p>
            <button class="card-button btn-anotacoes" onclick="window.location.href='nascimento/index.php'">
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Casamento -->
        <div class="module-card" id="card-casamento">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-contas">
                    <i class="fa fa-heart"></i>
                </div>
            </div>
            <h3 class="card-title">Casamento</h3>
            <p class="card-description">Indexe e pesquise registros de casamento.</p>
            <button class="card-button btn-5" onclick="window.location.href='casamento/index.php'">
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Óbito -->
        <div class="module-card" id="card-obito">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-tarefas">
                    <i class="fa fa-book"></i>
                </div>
            </div>
            <h3 class="card-title">Óbito</h3>
            <p class="card-description">Indexe e pesquise registros de óbito.</p>
            <button class="card-button btn-secondary" onclick="window.location.href='obito/index.php'">
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Notas -->
        <div class="module-card" id="card-notas">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-indexador">
                    <i class="fa fa-file-text-o"></i>
                </div>
            </div>
            <h3 class="card-title">Notas</h3>
            <p class="card-description">Indexe e pesquise atos de notas.</p>
            <button class="card-button btn-indexador" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Imóveis -->
        <div class="module-card" id="card-imoveis">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-os">
                    <i class="fa fa-home"></i>
                </div>
            </div>
            <h3 class="card-title">Imóveis</h3>
            <p class="card-description">Indexe e pesquise matrículas de imóveis.</p>
            <button class="card-button btn-os" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Protesto -->
        <div class="module-card" id="card-protesto">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-caixa">
                    <i class="fa fa-gavel"></i>
                </div>
            </div>
            <h3 class="card-title">Protesto</h3>
            <p class="card-description">Indexe e pesquise títulos de protesto.</p>
            <button class="card-button btn-success" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Títulos e Documentos -->
        <div class="module-card" id="card-titulos-e-documentos">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-guia">
                    <i class="fa fa-briefcase"></i>
                </div>
            </div>
            <h3 class="card-title">Títulos e Documentos</h3>
            <p class="card-description">Indexe e pesquise registros de títulos e documentos.</p>
            <button class="card-button btn-4" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Pessoas Jurídicas -->
        <div class="module-card" id="card-pessoas-juridicas">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-manuais">
                    <i class="fa fa-building"></i>
                </div>
            </div>
            <h3 class="card-title">Pessoas Jurídicas</h3>
            <p class="card-description">Indexe e pesquise registros de pessoas jurídicas.</p>
            <button class="card-button btn-manuais" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>

        <!-- Contratos Marítimos -->
        <div class="module-card" id="card-contratos-maritimos">
            <div class="card-header">
                <span class="card-badge badge-documental">Indexador</span>
                <div class="card-icon icon-arquivamento">
                    <i class="fa fa-ship"></i>
                </div>
            </div>
            <h3 class="card-title">Contratos Marítimos</h3>
            <p class="card-description">Indexe e pesquise registros e contratos marítimos.</p>
            <button class="card-button btn-arquivamento" onclick="window.location.href='index.php'" disabled>
                <i class="fa fa-arrow-right"></i> Acessar
            </button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="../script/jquery-3.6.0.min.js"></script>
<script src="../script/jquery-ui.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(function () {
    const $start  = $('#fStart');
    const $end    = $('#fEnd');
    const $basis  = $('#fBasis');
    const $status = $('#fStatus');
    const $user   = $('#fUser');

    const today      = new Date();
    const yyyy       = today.getFullYear();
    const mm         = String(today.getMonth() + 1).padStart(2, '0');
    const dd         = String(today.getDate()).padStart(2, '0');
    const todayStr   = `${yyyy}-${mm}-${dd}`;

    function addDays(date, days){ const d = new Date(date); d.setDate(d.getDate()+days); return d; }
    function format(d){ const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), da=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${da}`; }

    const start30 = format(addDays(today, -30));
    $start.val(start30);
    $end.val(todayStr);
    $basis.val('cadastro'); // padrão

    const $kNasc = $('#kpiNascimento');
    const $kObit = $('#kpiObito');
    const $kCasa = $('#kpiCasamento');
    const $kTot  = $('#kpiTotal');
    function formatNumber(n){ try { return (n || 0).toLocaleString('pt-BR'); } catch(e){ return n; } }

    let tiposChart = null;
    let funcChart  = null;

    function buildOrUpdateCharts(payload){
        if(payload && payload.ok === false){
            console.error('Endpoint retornou erro:', payload);
            return;
        }
        $kNasc.text(formatNumber(payload.totals.nascimento));
        $kCasa.text(formatNumber(payload.totals.casamento));
        $kObit.text(formatNumber(payload.totals.obito));
        $kTot.text(formatNumber(payload.totals.total));

        const tiposData = {
            labels: ['Nascimento', 'Casamento', 'Óbito'],
            datasets: [{
                label:'Atos',
                data:[
                    payload.totals.nascimento,
                    payload.totals.casamento,
                    payload.totals.obito
                ],
                backgroundColor:['#39c076','#ff8a80','#6c757d'],
                borderWidth:0
            }]
        };
        const tiposOpts = { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' }, tooltip:{ mode:'index', intersect:false } } };
        if (tiposChart) tiposChart.destroy();
        tiposChart = new Chart(document.getElementById('chartTipos').getContext('2d'), { type:'doughnut', data:tiposData, options:tiposOpts });

        const labels = payload.by_funcionario.map(i => (i.funcionario || '').toString().toUpperCase());
        const nasc   = payload.by_funcionario.map(i => i.nascimento);
        const casa   = payload.by_funcionario.map(i => i.casamento);
        const obit   = payload.by_funcionario.map(i => i.obito);

        const funcData = { labels, datasets:[
            { label:'Nascimento', data:nasc, backgroundColor:'#39c076' },
            { label:'Casamento',  data:casa, backgroundColor:'#ff8a80' },
            { label:'Óbito',      data:obit, backgroundColor:'#6c757d' }
        ]};
        const funcOpts = {
            responsive:true, maintainAspectRatio:false,
            scales:{
                x:{ stacked:true, ticks:{ autoSkip:true, maxRotation:45, minRotation:0 } },
                y:{ stacked:true, beginAtZero:true, precision:0 }
            },
            plugins:{
                legend:{ position:'bottom' },
                tooltip:{
                    mode:'index',
                    intersect:false,
                    callbacks:{
                        title: (items) => (items && items.length ? (items[0].label || '').toUpperCase() : '')
                    }
                }
            }
        };
        if (funcChart) funcChart.destroy();
        funcChart = new Chart(document.getElementById('chartFuncionarios').getContext('2d'), { type:'bar', data:funcData, options:funcOpts });

        if (payload.debug) { console.warn('DEBUG servidor:', payload.debug); }
    }

    function fetchStats(){
        const params = {
            action:'stats',
            start:$start.val(),
            end:$end.val(),
            basis:$basis.val(),
            status:$status.val(),
            user:$user.val()
        };
        $('#btnApply').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Carregando...');
        $.ajax({
            url: 'index.php',
            type: 'GET',
            dataType: 'json',
            cache: false,
            data: params,
            success: function(resp){ buildOrUpdateCharts(resp); },
            error: function(xhr){ console.error('Erro AJAX:', xhr.status, xhr.statusText, xhr.responseText); },
            complete: function(){ $('#btnApply').prop('disabled', false).html('<i class="fa fa-line-chart"></i> Aplicar filtros'); }
        });
    }

    // Chips de período — normalizo para string antes de comparar
    $('.chip').on('click', function(){
        $('.chip').removeClass('active');
        $(this).addClass('active');

        const kind = String($(this).data('range'));
        const now  = new Date();

        let s = $start.val(), e = $end.val();

        switch (kind) {
            case '7':
                s = format(addDays(now,-7)); e = format(now); break;
            case '15':
                s = format(addDays(now,-15)); e = format(now); break;
            case '30':
                s = format(addDays(now,-30)); e = format(now); break;
            case 'this_month':
                s = format(new Date(now.getFullYear(), now.getMonth(), 1));
                e = format(new Date(now.getFullYear(), now.getMonth()+1, 0));
                break;
            case 'last_month':
                s = format(new Date(now.getFullYear(), now.getMonth()-1, 1));
                e = format(new Date(now.getFullYear(), now.getMonth(), 0));
                break;
            case 'ytd':
                s = `${now.getFullYear()}-01-01`;
                e = format(now);
                break;
        }

        $start.val(s);
        $end.val(e);
        fetchStats();
    });

    $('#btnApply').on('click', fetchStats);
    $('#btnReset').on('click', function(){
        $('.chip').removeClass('active');
        $('.chip[data-range="30"]').addClass('active');
        $start.val(start30); $end.val(todayStr);
        $basis.val('cadastro'); $status.val('ativos'); $user.val('');
        fetchStats();
    });

    // Primeira carga
    fetchStats();

    // Busca de módulos
    $("#searchModules").on("keyup", function () {
        const value = $(this).val().toLowerCase();
        $("#sortable-cards .module-card").filter(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Sortable de cards
    $("#sortable-cards").sortable({
        placeholder: "ui-state-highlight",
        handle: ".card-header",
        cursor: "move",
        update: function () { saveCardOrder(); }
    });

    function saveCardOrder() {
        let order = [];
        $("#sortable-cards .module-card").each(function () { order.push($(this).attr('id')); });
        $.ajax({
            url: '../save_order.php',
            type: 'POST',
            data: { order: order },
            success: function () { console.log('Ordem salva com sucesso!'); },
            error: function (xhr, status, error) { console.error('Erro ao salvar a ordem:', error); }
        });
    }
    function loadCardOrder() {
        $.ajax({
            url: '../load_order.php',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                if (data && data.order) { $.each(data.order, function (index, cardId) { $("#" + cardId).appendTo("#sortable-cards"); }); }
            },
            error: function (xhr, status, error) { console.error('Erro ao carregar a ordem:', error); }
        });
    }
    loadCardOrder();

    // Alternância de tema
    $('.mode-switch').on('click', function(){ $('body').toggleClass('dark-mode light-mode'); });
});
</script>

<br><br><br>
<?php include(__DIR__ . '/../rodape.php'); ?>

</body>
</html>
