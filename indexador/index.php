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
/* ============================================================
   Interface (padrão visual do Indexador — ver _core/ui.php)
   ============================================================ */
require_once __DIR__ . '/_core/ui.php';
$__tot = ['nascimento' => 0, 'casamento' => 0, 'obito' => 0];
$__mes = $__tot;
try {
    foreach (['nascimento', 'casamento', 'obito'] as $__k) {
        $__T = ix_tipo($__k);
        $__st = ix_db()->prepare("SELECT COUNT(*) AS n, SUM(`{$__T['col_cadastro']}` >= ?) AS m FROM `{$__T['table']}` WHERE status = ?");
        $__ini = date('Y-m-01');
        $__st->bind_param('ss', $__ini, $__T['status_ativo']);
        $__st->execute();
        $__r = $__st->get_result()->fetch_assoc();
        $__tot[$__k] = (int)$__r['n']; $__mes[$__k] = (int)$__r['m'];
        $__st->close();
    }
} catch (Throwable $e) {}

ix_page_start(['title' => 'Indexador', 'base' => '', 'atlas' => '../', 'accent' => 'carga',
    'head' => '<style>
    .hub-types{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:14px}
    @media(max-width:980px){.hub-types{grid-template-columns:1fr}}
    .hub-type{position:relative;padding:18px 18px 16px 22px;display:grid;gap:12px;overflow:hidden}
    .hub-type::before{content:"";position:absolute;left:0;top:0;bottom:0;width:5px;background:var(--ix-type)}
    .hub-type-top{display:flex;align-items:center;gap:12px}
    .hub-type-top h2{font-family:var(--ix-serif);font-weight:600;font-size:22px;margin:0;color:var(--ix-ink)}
    .hub-type-top .ix-title-mark{width:40px;height:40px;border-radius:11px}
    .hub-drag{margin-left:auto;cursor:grab;color:var(--ix-muted);border:0;background:none;padding:4px;border-radius:6px}
    .hub-drag:hover{background:var(--ix-sunken)}
    .hub-num{display:flex;gap:22px;color:var(--ix-muted);font-size:14px}
    .hub-num b{display:block;font-size:26px;line-height:1.1;color:var(--ix-ink);font-weight:600}
    .hub-type-actions{display:flex;gap:8px;flex-wrap:wrap}
    .hub-type.is-dragging{opacity:.5}
    .hub-tools{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:28px}
    @media(max-width:980px){.hub-tools{grid-template-columns:1fr}}
    .hub-tool{display:flex;gap:14px;align-items:flex-start;padding:16px;color:var(--ix-ink)!important;text-decoration:none!important;transition:border-color .15s}
    .hub-tool:hover{border-color:var(--ix-primary)}
    .hub-tool .ix-i{width:22px;height:22px;color:var(--ix-primary);margin-top:2px}
    .hub-tool b{display:block;font-weight:600;margin-bottom:2px}
    .hub-tool span{font-size:13.5px;color:var(--ix-muted)}
    .hub-h2{font-family:var(--ix-serif);font-weight:600;font-size:22px;margin:0 0 4px;color:var(--ix-ink)}
    .hub-prod{padding:18px}
    .hub-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border:1px solid var(--ix-line);border-radius:var(--ix-r-m);margin:16px 0;overflow:hidden}
    .hub-kpis div{padding:12px 16px}
    .hub-kpis div+div{border-left:1px solid var(--ix-line)}
    .hub-kpis small{display:flex;align-items:center;gap:6px;color:var(--ix-muted);font-size:13px}
    .hub-kpis small i{width:9px;height:9px;border-radius:50%;background:var(--c)}
    .hub-kpis b{font-size:26px;font-weight:600}
    @media(max-width:700px){.hub-kpis{grid-template-columns:1fr 1fr}.hub-kpis div:nth-child(3){border-left:0}.hub-kpis div:nth-child(n+3){border-top:1px solid var(--ix-line)}}
    .hub-charts{display:grid;grid-template-columns:1fr 2fr;gap:14px}
    @media(max-width:980px){.hub-charts{grid-template-columns:1fr}}
    .hub-chart{border:1px solid var(--ix-line);border-radius:var(--ix-r-m);padding:14px}
    .hub-chart h3{font-size:14px;font-weight:600;margin:0 0 10px;color:var(--ix-ink)}
    .hub-chart .wrap{position:relative;height:320px}
    .hub-chips{display:flex;flex-wrap:wrap;gap:6px}
    .hub-chips button{height:30px;padding:0 12px;border-radius:999px;border:1px solid var(--ix-line-strong);background:var(--ix-surface);color:var(--ix-ink-2);font:500 13px var(--ix-font);cursor:pointer}
    .hub-chips button.is-active{background:var(--ix-primary);border-color:var(--ix-primary);color:var(--ix-primary-ink)}
    </style>',
]);
ix_tabs('inicio', '');
$__types = [
    'nascimento' => ['Nascimento', 'nasc', 'baby'],
    'casamento'  => ['Casamento', 'casa', 'rings'],
    'obito'      => ['Óbito', 'obito', 'cross'],
];
?>
    <header class="ix-head">
        <div>
            <div class="ix-title">
                <span class="ix-title-mark"><?= ix_icon('book') ?></span>
                <h1>Indexador do Registro Civil</h1>
            </div>
            <div class="ix-stats"><span><b><?= number_format(array_sum($__tot), 0, ',', '.') ?></b>registros ativos</span><span><b><?= number_format(array_sum($__mes), 0, ',', '.') ?></b>indexados este mês</span></div>
        </div>
    </header>

    <div class="hub-types" id="sortable-cards">
        <?php foreach ($__types as $__k => [$__lbl, $__acc, $__ic]): ?>
        <section class="ix-panel hub-type ix-t-<?= $__acc ?>" id="card-<?= $__k ?>" draggable="false">
            <div class="hub-type-top">
                <span class="ix-title-mark"><?= ix_icon($__ic) ?></span>
                <h2><?= $__lbl ?></h2>
                <button type="button" class="hub-drag" title="Arraste para reordenar" aria-label="Reordenar"><?= ix_icon('grip') ?></button>
            </div>
            <div class="hub-num">
                <div><b><?= number_format($__tot[$__k], 0, ',', '.') ?></b>ativos</div>
                <div><b><?= number_format($__mes[$__k], 0, ',', '.') ?></b>este mês</div>
            </div>
            <div class="hub-type-actions">
                <a class="ix-btn ix-btn-type" href="<?= $__k ?>/index.php"><?= ix_icon('search') ?>Pesquisar</a>
                <a class="ix-btn" href="<?= $__k ?>/index.php?novo=1"><?= ix_icon('plus') ?>Novo registro</a>
                <a class="ix-btn ix-btn-ghost" href="carga_crc/index.php?tipo=<?= $__k ?>"><?= ix_icon('download') ?>Carga</a>
            </div>
        </section>
        <?php endforeach; ?>
    </div>

    <div class="hub-tools">
        <a class="ix-panel hub-tool" href="carga_crc/index.php"><?= ix_icon('download') ?><div><b>Exportar carga CRC</b><span>Selecione, valide contra o XSD e gere o XML de nascimento, casamento ou óbito.</span></div></a>
        <a class="ix-panel hub-tool" href="validar_xml/index.php"><?= ix_icon('shield') ?><div><b>Validar XML da CRC</b><span>Confira um arquivo de carga antes de enviar e veja os erros por registro.</span></div></a>
        <a class="ix-panel hub-tool" href="relatorio_detalhado.php"><?= ix_icon('chart') ?><div><b>Relatório detalhado</b><span>Produção diária por funcionário e tipo de ato.</span></div></a>
    </div>

    <section class="ix-panel hub-prod" aria-labelledby="hub-prod-t">
        <h2 class="hub-h2" id="hub-prod-t">Produção da equipe</h2>
        <div class="ix-grid" style="margin-top:12px">
            <div class="ix-col ix-col-3 ix-field"><label class="ix-label" for="fStart">Data inicial</label><input type="date" id="fStart" class="ix-input"></div>
            <div class="ix-col ix-col-3 ix-field"><label class="ix-label" for="fEnd">Data final</label><input type="date" id="fEnd" class="ix-input"></div>
            <div class="ix-col ix-col-2 ix-field"><label class="ix-label" for="fBasis">Contar pela</label>
                <select id="fBasis" class="ix-input"><option value="cadastro">Data de cadastro</option><option value="registro">Data do registro</option></select></div>
            <div class="ix-col ix-col-2 ix-field"><label class="ix-label" for="fStatus">Situação</label>
                <select id="fStatus" class="ix-input"><option value="ativos">Somente ativos</option><option value="todos">Todas</option></select></div>
            <div class="ix-col ix-col-2 ix-field"><label class="ix-label" for="fUser">Usuário</label>
                <select id="fUser" class="ix-input"><option value="">Todos</option>
                    <?php foreach ($USERS_LIST as $u): ?><option value="<?= htmlspecialchars($u['usuario']) ?>"><?= htmlspecialchars($u['nome']) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="ix-filters-bar">
            <div class="hub-chips" role="group" aria-label="Períodos rápidos">
                <button type="button" class="chip" data-range="7">7 dias</button>
                <button type="button" class="chip" data-range="15">15 dias</button>
                <button type="button" class="chip is-active" data-range="30">30 dias</button>
                <button type="button" class="chip" data-range="this_month">Este mês</button>
                <button type="button" class="chip" data-range="last_month">Mês passado</button>
                <button type="button" class="chip" data-range="ytd">Ano atual</button>
            </div>
            <span class="ix-spacer"></span>
            <button type="button" id="btnReset" class="ix-btn ix-btn-ghost"><?= ix_icon('refresh') ?>Restaurar</button>
            <button type="button" id="btnApply" class="ix-btn ix-btn-primary"><?= ix_icon('chart') ?>Aplicar</button>
        </div>

        <div class="hub-kpis">
            <div><small><i style="--c:#0e7c66"></i>Nascimentos</small><b id="kpiNascimento">0</b></div>
            <div><small><i style="--c:#9c2f55"></i>Casamentos</small><b id="kpiCasamento">0</b></div>
            <div><small><i style="--c:#4e5592"></i>Óbitos</small><b id="kpiObito">0</b></div>
            <div><small>Total no período</small><b id="kpiTotal">0</b></div>
        </div>
        <div class="hub-charts">
            <div class="hub-chart"><h3>Por tipo de ato</h3><div class="wrap"><canvas id="chartTipos" aria-label="Gráfico por tipo de ato" role="img"></canvas></div></div>
            <div class="hub-chart"><h3>Por funcionário</h3><div class="wrap"><canvas id="chartFuncionarios" aria-label="Gráfico por funcionário" role="img"></canvas></div></div>
        </div>
    </section>
<?php
ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const $ = s => document.querySelector(s);
    const COLORS = { n: '#0e7c66', c: '#9c2f55', o: '#4e5592' };
    const today = new Date();
    const fmt = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const add = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
    const start30 = fmt(add(today, -30));
    $('#fStart').value = start30; $('#fEnd').value = fmt(today);
    let cT = null, cF = null;
    const nf = n => (+n || 0).toLocaleString('pt-BR');
    const ink = () => getComputedStyle(document.getElementById('ix-app')).getPropertyValue('--ix-muted').trim() || '#5b6878';
    const grid = () => getComputedStyle(document.getElementById('ix-app')).getPropertyValue('--ix-line').trim() || '#d9e0e7';

    function draw(p) {
        if (!p || p.ok === false) { IX.toast((p && p.message) || 'Falha ao calcular estatísticas.', 'err'); return; }
        $('#kpiNascimento').textContent = nf(p.totals.nascimento);
        $('#kpiCasamento').textContent = nf(p.totals.casamento);
        $('#kpiObito').textContent = nf(p.totals.obito);
        $('#kpiTotal').textContent = nf(p.totals.total);
        if (typeof Chart === 'undefined') return;
        Chart.defaults.color = ink(); Chart.defaults.font.family = 'IBM Plex Sans, system-ui, sans-serif';
        if (cT) cT.destroy();
        cT = new Chart($('#chartTipos'), { type: 'doughnut',
            data: { labels: ['Nascimento', 'Casamento', 'Óbito'], datasets: [{ data: [p.totals.nascimento, p.totals.casamento, p.totals.obito], backgroundColor: [COLORS.n, COLORS.c, COLORS.o], borderWidth: 2, borderColor: getComputedStyle(document.getElementById('ix-app')).getPropertyValue('--ix-surface').trim() }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' } } } });
        const f = p.by_funcionario;
        if (cF) cF.destroy();
        cF = new Chart($('#chartFuncionarios'), { type: 'bar',
            data: { labels: f.map(i => String(i.funcionario || '').toUpperCase()),
                datasets: [{ label: 'Nascimento', data: f.map(i => i.nascimento), backgroundColor: COLORS.n, borderRadius: 3 },
                           { label: 'Casamento', data: f.map(i => i.casamento), backgroundColor: COLORS.c, borderRadius: 3 },
                           { label: 'Óbito', data: f.map(i => i.obito), backgroundColor: COLORS.o, borderRadius: 3 }] },
            options: { responsive: true, maintainAspectRatio: false,
                scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid() } } },
                plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } } } });
    }
    function load() {
        const b = $('#btnApply'); b.classList.add('is-loading');
        const q = new URLSearchParams({ action: 'stats', start: $('#fStart').value, end: $('#fEnd').value, basis: $('#fBasis').value, status: $('#fStatus').value, user: $('#fUser').value });
        fetch('index.php?' + q, { credentials: 'same-origin' }).then(r => r.json()).then(draw)
            .catch(() => IX.toast('Não foi possível carregar a produção.', 'err')).finally(() => b.classList.remove('is-loading'));
    }
    document.querySelectorAll('.hub-chips .chip').forEach(c => c.addEventListener('click', () => {
        document.querySelectorAll('.hub-chips .chip').forEach(x => x.classList.remove('is-active')); c.classList.add('is-active');
        const now = new Date(); let s, e = fmt(now);
        switch (c.dataset.range) {
            case '7': s = fmt(add(now, -7)); break;
            case '15': s = fmt(add(now, -15)); break;
            case '30': s = fmt(add(now, -30)); break;
            case 'this_month': s = fmt(new Date(now.getFullYear(), now.getMonth(), 1)); e = fmt(new Date(now.getFullYear(), now.getMonth() + 1, 0)); break;
            case 'last_month': s = fmt(new Date(now.getFullYear(), now.getMonth() - 1, 1)); e = fmt(new Date(now.getFullYear(), now.getMonth(), 0)); break;
            case 'ytd': s = now.getFullYear() + '-01-01'; break;
        }
        $('#fStart').value = s; $('#fEnd').value = e; load();
    }));
    $('#btnApply').addEventListener('click', load);
    ['#fBasis', '#fStatus', '#fUser'].forEach(s => $(s).addEventListener('change', load));
    $('#btnReset').addEventListener('click', () => {
        document.querySelectorAll('.hub-chips .chip').forEach(x => x.classList.toggle('is-active', x.dataset.range === '30'));
        $('#fStart').value = start30; $('#fEnd').value = fmt(today); $('#fBasis').value = 'cadastro'; $('#fStatus').value = 'ativos'; $('#fUser').value = ''; load();
    });
    load();
    // redesenha os gráficos quando o tema do Atlas muda
    new MutationObserver(() => { if (cT) load(); }).observe(document.body, { attributes: true, attributeFilter: ['class'] });

    /* Ordem dos cartões (mesmo armazenamento da versão anterior: save_order/load_order do Atlas) */
    const box = $('#sortable-cards');
    let dragEl = null;
    box.querySelectorAll('.hub-drag').forEach(h => {
        h.addEventListener('mousedown', () => h.closest('.hub-type').setAttribute('draggable', 'true'));
        h.addEventListener('touchstart', () => h.closest('.hub-type').setAttribute('draggable', 'true'), { passive: true });
    });
    box.addEventListener('dragstart', e => { dragEl = e.target.closest('.hub-type'); dragEl.classList.add('is-dragging'); });
    box.addEventListener('dragend', () => { if (!dragEl) return; dragEl.classList.remove('is-dragging'); dragEl.setAttribute('draggable', 'false'); dragEl = null; save(); });
    box.addEventListener('dragover', e => {
        e.preventDefault(); if (!dragEl) return;
        const over = e.target.closest('.hub-type'); if (!over || over === dragEl) return;
        const r = over.getBoundingClientRect();
        const after = (e.clientX - r.left) > r.width / 2 || (e.clientY - r.top) > r.height / 2;
        box.insertBefore(dragEl, after ? over.nextSibling : over);
    });
    function save() {
        const fd = new FormData(); box.querySelectorAll('.hub-type').forEach(c => fd.append('order[]', c.id));
        fetch('../save_order.php', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(() => {});
    }
    fetch('../load_order.php', { credentials: 'same-origin' }).then(r => r.json()).then(d => {
        (d && d.order || []).forEach(id => { const el = document.getElementById(id); if (el && el.parentNode === box) box.appendChild(el); });
    }).catch(() => {});
})();
</script>
<?php
ix_page_end(['base' => '', 'atlas' => '../', 'scripts' => ob_get_clean()]);
