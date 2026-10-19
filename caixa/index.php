<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_funcionario.php';
require_once __DIR__ . '/caixa_saldo.php';
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesquisar Fluxo de Caixa</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <?php include(__DIR__ . '/../style/style_caixa.php'); ?>
    <?php include(__DIR__ . '/caixa_botoes.php'); ?>
    <style>
        /* ===================== Pesquisa do Fluxo de Caixa ===================== */
        .cxf-panel{ background:#fff; border:1px solid #e5e9f0; border-radius:16px; padding:18px 18px 10px; box-shadow:0 1px 3px rgba(15,23,42,.05); margin:14px 0 18px; }
        .cxf-panel-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
        .cxf-panel-title{ font-weight:700; font-size:1rem; color:#0f172a; display:flex; align-items:center; gap:8px; margin:0; }
        .cxf-panel-title i{ color:#2563eb; }
        .cxf-label{ display:block; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; margin-bottom:4px; }
        .cxf-pills{ display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; }
        .cxf-pill{ border:1px solid #d6dde8; background:#f8fafc; color:#334155; border-radius:999px; padding:5px 13px; font-size:.82rem; font-weight:600; cursor:pointer; transition:all .15s ease; line-height:1.4; }
        .cxf-pill:hover{ border-color:#2563eb; color:#2563eb; }
        .cxf-pill.active{ background:#2563eb; border-color:#2563eb; color:#fff; box-shadow:0 2px 6px rgba(37,99,235,.25); }
        .cxf-panel .form-control{ border-radius:10px; }
        .cxf-adv-toggle{ font-size:.85rem; font-weight:600; color:#2563eb; cursor:pointer; user-select:none; background:none; border:0; padding:0; }
        .cxf-adv-toggle .fa{ transition:transform .2s ease; }
        .cxf-adv-toggle[aria-expanded="true"] .fa-chevron-down{ transform:rotate(180deg); }
        .cxf-adv{ border-top:1px dashed #e2e8f0; margin-top:6px; padding-top:12px; }
        .cxf-checks{ display:flex; flex-wrap:wrap; gap:6px; }
        .cxf-check{ position:relative; margin:0; }
        .cxf-check input{ position:absolute; opacity:0; pointer-events:none; }
        .cxf-check span{ display:inline-flex; align-items:center; gap:6px; border:1px solid #d6dde8; border-radius:10px; padding:5px 10px; font-size:.82rem; color:#334155; cursor:pointer; background:#fff; }
        .cxf-check input:checked + span{ background:#ecfdf5; border-color:#10b981; color:#047857; font-weight:600; }
        .cxf-check input:focus-visible + span{ outline:2px solid #2563eb; outline-offset:1px; }
        .cxf-actions{ display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin:12px 0 6px; }
        .cxf-actions-spacer{ flex:1 1 auto; }
        .cxf-view{ display:inline-flex; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; }
        .cxf-view-btn{ display:inline-flex; align-items:center; justify-content:center; width:38px; height:34px; padding:0;
                       border:0; background:#fff; color:#475569; cursor:pointer; }
        .cxf-view-btn + .cxf-view-btn{ border-left:1px solid #cbd5e1; }
        .cxf-view-btn.active{ background:#2563eb; color:#fff; }
        .cxf-active{ display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin:0 0 12px; }
        .cxf-chip{ display:inline-flex; align-items:center; gap:6px; background:#eef2ff; color:#3730a3; border-radius:999px; padding:3px 10px; font-size:.76rem; font-weight:600; }
        .cxf-kpis{ display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; margin-bottom:14px; }
        .cxf-kpi{ background:#fff; border:1px solid #e5e9f0; border-left:4px solid #94a3b8; border-radius:12px; padding:10px 12px; }
        .cxf-kpi .lb{ font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
        .cxf-kpi .vl{ font-size:1.08rem; font-weight:700; color:#0f172a; white-space:nowrap; }
        .cxf-kpi .sb{ font-size:.72rem; color:#94a3b8; }
        .cxf-kpi.k-qtd{ border-left-color:#6366f1; } .cxf-kpi.k-atos{ border-left-color:#2563eb; }
        .cxf-kpi.k-esp{ border-left-color:#16a34a; } .cxf-kpi.k-conta{ border-left-color:#f59e0b; }
        .cxf-kpi.k-said{ border-left-color:#dc2626; } .cxf-kpi.k-dep{ border-left-color:#0891b2; }
        .cxf-kpi.k-cx{ border-left-color:#0f172a; }
        .cxf-toolbar{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; margin-bottom:12px; }
        .cxf-toolbar h5{ margin:0; font-weight:700; }
        .cxf-toolbar .cxf-count{ font-size:.82rem; color:#64748b; font-weight:600; margin-left:6px; }
        .cxf-tools{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .cxf-search{ position:relative; }
        .cxf-search input{ padding-left:30px; border-radius:10px; min-width:220px; }
        .cxf-search .fa{ position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8; }
        .cxf-pend{ display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; }
        .cxf-tag{ display:inline-flex; align-items:center; gap:4px; font-size:.68rem; font-weight:700; border-radius:6px; padding:2px 7px; }
        .cxf-tag.warn{ background:#fff7ed; color:#c2410c; }
        .cxf-tag.info{ background:#eff6ff; color:#1d4ed8; }
        .cxf-tag.danger{ background:#fef2f2; color:#b91c1c; }
        .cxf-tag.ok{ background:#ecfdf5; color:#047857; }
        .cxf-table-wrap{ background:#fff; border:1px solid #e5e9f0; border-radius:14px; overflow:auto; }
        .cxf-table{ margin:0; font-size:.84rem; }
        .cxf-table thead th{ position:sticky; top:0; background:#f8fafc; z-index:1; font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; color:#475569; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
        .cxf-table td{ vertical-align:middle; white-space:nowrap; }
        .cxf-table td.num, .cxf-table th.num{ text-align:right; font-variant-numeric:tabular-nums; }
        .cxf-table tbody tr{ cursor:pointer; }
        .cxf-table tbody tr:hover{ background:#f1f5f9; }
        .cxf-table tfoot td{ font-weight:700; background:#f8fafc; }
        .cxf-table .card-actions{ display:flex; gap:4px; flex-wrap:nowrap; }
        .cxf-empty{ text-align:center; padding:40px 16px; color:#64748b; border:1px dashed #cbd5e1; border-radius:14px; background:#fff; }
        .cxf-empty .fa{ font-size:2rem; color:#cbd5e1; margin-bottom:8px; display:block; }
        .cxf-nome{ font-size:.75rem; color:#64748b; margin-top:-2px; }
        .cxf-versao{ text-align:right; font-size:.72rem; color:#94a3b8; margin:18px 0 6px; }
        .cxf-hidden{ display:none !important; }
        /* Abertura do caixa */
        .cxf-pendentes{ border:1px solid #fecaca; background:#fef2f2; border-radius:12px; padding:12px; margin-bottom:12px; }
        .cxf-pendentes h6{ color:#b91c1c; font-weight:700; margin-bottom:6px; }
        .cxf-pendentes ul{ margin:0; padding-left:0; list-style:none; }
        .cxf-pendentes li{ display:flex; justify-content:space-between; align-items:center; gap:8px; padding:6px 0; border-top:1px solid #fee2e2; font-size:.88rem; }
        .cxf-pendentes li:first-child{ border-top:0; }
        .cxf-disp{ display:flex; align-items:center; justify-content:space-between; gap:10px; border-radius:12px; padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; margin-bottom:14px; }
        .cxf-disp .lb{ font-size:.75rem; font-weight:700; text-transform:uppercase; color:#15803d; }
        .cxf-disp .vl{ font-size:1.25rem; font-weight:800; color:#14532d; }
        .cxf-disp.zero{ background:#fef2f2; border-color:#fecaca; }
        .cxf-disp.zero .lb, .cxf-disp.zero .vl{ color:#b91c1c; }
        .btn-saldo-admin{ background:#7c3aed; border-color:#7c3aed; color:#fff; }
        .btn-saldo-admin:hover{ background:#6d28d9; color:#fff; }
        .cxf-btn-saldo-card{ display:flex; width:100%; align-items:center; justify-content:center; gap:6px; margin:8px 0 4px; font-weight:600; border-radius:8px; }
        .cxf-table .cxf-btn-saldo-card{ width:auto; display:inline-flex; margin:6px 0 0; }
        .cxf-saldo-admin-aviso{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
                                background:#f5f3ff; border:1px solid #c4b5fd; color:#4c1d95; border-radius:10px; padding:10px 14px; margin-bottom:14px; font-size:.9rem; }
        body.dark-mode .cxf-saldo-admin-aviso{ background:#2e1065; border-color:#6d28d9; color:#ede9fe; }
        /* Modal do saldo inicial aberto por cima do detalhe do caixa */
        #saldoInicialAdminModal{ z-index:1065; }
        @media (max-width: 576px){
            .cxf-search input{ min-width:0; width:100%; }
            .cxf-search, .cxf-tools{ width:100%; }
            .cxf-actions .cxf-btn{ flex:1 1 100%; }
            .cxf-actions-spacer{ display:none; }
        }
        /* Tema escuro do Atlas */
        body.dark-mode .cxf-panel, body.dark-mode .cxf-kpi, body.dark-mode .cxf-table-wrap, body.dark-mode .cxf-empty{ background:#1e293b; border-color:#334155; }
        body.dark-mode .cxf-panel-title, body.dark-mode .cxf-kpi .vl{ color:#f1f5f9; }
        body.dark-mode .cxf-pill, body.dark-mode .cxf-check span{ background:#0f172a; border-color:#334155; color:#cbd5e1; }
        body.dark-mode .cxf-pill.active{ background:#2563eb; color:#fff; border-color:#2563eb; }
        body.dark-mode .cxf-table thead th, body.dark-mode .cxf-table tfoot td{ background:#0f172a; color:#cbd5e1; border-color:#334155; }
        body.dark-mode .cxf-table td{ color:#e2e8f0; border-color:#334155; }
        body.dark-mode .cxf-table tbody tr:hover{ background:#273449; }
        body.dark-mode .cxf-chip{ background:#312e81; color:#e0e7ff; }
        body.dark-mode .cxf-btn-light, body.dark-mode .cxf-btn-dark, body.dark-mode .cxf-btn-green-outline,
        body.dark-mode .cxf-view-btn{ background:#0f172a !important; color:#e2e8f0 !important; border-color:#475569 !important; }
        body.dark-mode .cxf-view-btn.active{ background:#2563eb !important; color:#fff !important; }
    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');

    // ============================ DB: tabela (auto-criação) ============================
    try {
        $conn = getDatabaseConnection();

        // Estrutura e migrações do módulo analítico. Rodam automaticamente ao abrir
        // a página e apenas uma vez cada (controle na tabela atlas_migracoes).
        $__mig = __DIR__ . '/../analitico/migracoes_analitico.php';
        if (is_file($__mig)) {
            require_once $__mig;
            atlas_migrar_analitico_seguro($conn);
        }
    } catch (Exception $e) {
        // Silencioso
    }

    $conn = getDatabaseConnection();
    cx_garantir_estrutura($conn);

    $stmt = $conn->prepare('SELECT nivel_de_acesso, status, usuario FROM funcionarios WHERE usuario = :usuario');
    $stmt->bindParam(':usuario', $_SESSION['username']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || cx_norm($user['status']) !== 'ativo') {
        echo "<script>alert('O usuário não tem acesso à página.'); window.location.href='../index.php';</script>";
        exit;
    }

    $query = "SELECT usuario, nome_completo, nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = :usuario";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':usuario', $_SESSION['username']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $temAcessoFluxoDeCaixa = false;

    // Verifica se o nível de acesso é "usuario" e se tem o acesso adicional "Fluxo de Caixa"
    if (cx_norm($user['nivel_de_acesso']) === 'usuario' && !empty($user['acesso_adicional'])) {
        $acessosAdicionais = array_map('trim', explode(',', $user['acesso_adicional']));
        if (in_array('Fluxo de Caixa', $acessosAdicionais)) {
            $temAcessoFluxoDeCaixa = true;
        }
    }

    $isAdmin           = cx_nivel_admin($user['nivel_de_acesso']);
    $temAcessoCompleto = ($isAdmin || $temAcessoFluxoDeCaixa);

    // ============================ Helpers de saída ============================
    $h   = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
    $js  = function ($v) { return htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); };
    $fmt = function ($v) { return 'R$ ' . number_format((float) $v, 2, ',', '.'); };
    $gp  = function ($k, $padrao = '') {
        return (isset($_GET[$k]) && !is_array($_GET[$k])) ? trim((string) $_GET[$k]) : $padrao;
    };

    // ============================ Parâmetros da pesquisa ============================
    $hoje = date('Y-m-d');

    $periodos = [
        'hoje'           => 'Hoje',
        'ontem'          => 'Ontem',
        'ultimos7'       => 'Últimos 7 dias',
        'semanal'        => 'Esta semana',
        'semana_passada' => 'Semana passada',
        'mensal'         => 'Este mês',
        'mes_passado'    => 'Mês passado',
        'ultimos30'      => 'Últimos 30 dias',
        'anual'          => 'Este ano',
        'todos'          => 'Todos',
        'personalizado'  => 'Personalizado',
    ];

    $dataIniGet = $gp('data_inicial');
    $dataFimGet = $gp('data_final');
    if ($dataIniGet !== '' && !cx_data_valida($dataIniGet)) { $dataIniGet = ''; }
    if ($dataFimGet !== '' && !cx_data_valida($dataFimGet)) { $dataFimGet = ''; }
    $temDatas = ($dataIniGet !== '' || $dataFimGet !== '');

    $selectedPeriodo = $gp('periodo', $temDatas ? 'personalizado' : 'hoje');
    if ($selectedPeriodo === 'ultimoMes') { $selectedPeriodo = 'ultimos30'; } // links antigos
    if (!isset($periodos[$selectedPeriodo])) { $selectedPeriodo = 'hoje'; }

    /** Intervalo [inicio, fim] de um período rápido (null = sem restrição). */
    $intervaloPeriodo = function ($p) use ($hoje) {
        switch ($p) {
            case 'hoje':           return [$hoje, $hoje];
            case 'ontem':          $d = date('Y-m-d', strtotime('-1 day')); return [$d, $d];
            case 'ultimos7':       return [date('Y-m-d', strtotime('-6 days')), $hoje];
            case 'semanal':        return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))];
            case 'semana_passada': return [date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week'))];
            case 'mensal':         return [date('Y-m-01'), date('Y-m-t')];
            case 'mes_passado':    return [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))];
            case 'ultimos30':      return [date('Y-m-d', strtotime('-29 days')), $hoje];
            case 'anual':          return [date('Y-01-01'), date('Y-12-31')];
            default:               return null;
        }
    };

    // Datas efetivas
    if ($selectedPeriodo === 'personalizado') {
        $periodoIni = $dataIniGet !== '' ? $dataIniGet : null;
        $periodoFim = $dataFimGet !== '' ? $dataFimGet : null;
    } elseif ($selectedPeriodo === 'todos') {
        $periodoIni = $periodoFim = null;
    } else {
        list($periodoIni, $periodoFim) = $intervaloPeriodo($selectedPeriodo);
    }
    if ($periodoIni && $periodoFim && $periodoIni > $periodoFim) {
        list($periodoIni, $periodoFim) = [$periodoFim, $periodoIni];
    }

    // Funcionário
    $funcionarioSel = $temAcessoCompleto ? $gp('funcionario', 'todos') : $user['usuario'];
    if ($funcionarioSel === '') { $funcionarioSel = 'todos'; }

    // Filtros avançados
    $situacoes = [
        'todas'     => 'Todas as situações',
        'aberto'    => 'Abertos (com saldo em caixa)',
        'fechado'   => 'Fechados / zerados',
        'zerado'    => 'Abertos sem saldo inicial',
        'sem_caixa' => 'Movimento sem abertura de caixa',
        'negativo'  => 'Saldo negativo (inconsistência)',
    ];
    $situacao = $gp('situacao', 'todas');
    if (!isset($situacoes[$situacao])) { $situacao = 'todas'; }

    $possuiOpcoes = [
        'atos'            => ['Atos liquidados', 'fa-gavel'],
        'especie'         => ['Recebimento em espécie', 'fa-money'],
        'conta'           => ['Recebimento em conta', 'fa-credit-card'],
        'devolucoes'      => ['Devoluções', 'fa-undo'],
        'saidas'          => ['Saídas e despesas', 'fa-sign-out'],
        'depositos'       => ['Depósitos', 'fa-university'],
        'sem_comprovante' => ['Depósito sem comprovante', 'fa-exclamation-triangle'],
        'transporte'      => ['Saldo transportado', 'fa-share'],
    ];
    $possui = [];
    if (isset($_GET['possui']) && is_array($_GET['possui'])) {
        foreach ($_GET['possui'] as $p) {
            if (is_string($p) && isset($possuiOpcoes[$p])) { $possui[] = $p; }
        }
        $possui = array_values(array_unique($possui));
    }

    $valorMinTxt = $gp('valor_min');
    $valorMaxTxt = $gp('valor_max');
    $valorMin = $valorMinTxt !== '' ? cx_parse_valor($valorMinTxt) : null;
    $valorMax = $valorMaxTxt !== '' ? cx_parse_valor($valorMaxTxt) : null;

    $ordenacoes = [
        'data_desc'    => 'Data (mais recente)',
        'data_asc'     => 'Data (mais antiga)',
        'caixa_desc'   => 'Total em caixa (maior)',
        'caixa_asc'    => 'Total em caixa (menor)',
        'atos_desc'    => 'Atos liquidados (maior)',
        'especie_desc' => 'Recebido em espécie (maior)',
        'func_asc'     => 'Funcionário (A–Z)',
    ];
    $ordenar = $gp('ordenar', 'data_desc');
    if (!isset($ordenacoes[$ordenar])) { $ordenar = 'data_desc'; }

    $avancadoAtivo = ($situacao !== 'todas' || $possui || $valorMin !== null || $valorMax !== null || $ordenar !== 'data_desc');

    // Lista de funcionários (select + nomes nos cards)
    $queryFuncionarios = $temAcessoCompleto
        ? "SELECT usuario, nome_completo FROM funcionarios WHERE status = 'ativo' ORDER BY nome_completo"
        : "SELECT usuario, nome_completo FROM funcionarios WHERE usuario = :usuario";
    $stmt = $conn->prepare($queryFuncionarios);
    if (!$temAcessoCompleto) {
        $stmt->bindParam(':usuario', $user['usuario']);
    }
    $stmt->execute();
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $nomesFuncionarios = [];
    foreach ($conn->query('SELECT usuario, nome_completo FROM funcionarios')->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $nomesFuncionarios[$f['usuario']] = $f['nome_completo'];
    }
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <section class="page-hero">
                <div class="title-row">
                    <div class="title-icon"><i class="fa fa-university" aria-hidden="true"></i></div>
                    <div class="title-texts">
                        <h1>Pesquisar Fluxo de Caixa</h1>
                        <div class="subtitle muted">Consulte os caixas por funcionário, período, situação e movimentação — e faça saídas, depósitos e fechamentos.</div>
                    </div>
                </div>
            </section>

            <!-- ============================ FILTROS ============================ -->
            <form id="pesquisarForm" method="GET" class="cxf-panel" autocomplete="off">
                <div class="cxf-panel-head">
                    <h2 class="cxf-panel-title"><i class="fa fa-sliders" aria-hidden="true"></i> Filtros da pesquisa</h2>
                    <button type="button" class="cxf-adv-toggle" data-toggle="collapse" data-target="#cxfAvancado"
                            aria-expanded="<?php echo $avancadoAtivo ? 'true' : 'false'; ?>" aria-controls="cxfAvancado">
                        Filtros avançados <i class="fa fa-chevron-down" aria-hidden="true"></i>
                    </button>
                </div>

                <input type="hidden" id="periodo" name="periodo" value="<?php echo $h($selectedPeriodo); ?>">

                <span class="cxf-label">Período</span>
                <div class="cxf-pills" role="group" aria-label="Período">
                    <?php foreach ($periodos as $chave => $rotulo): ?>
                        <button type="button" class="cxf-pill<?php echo $selectedPeriodo === $chave ? ' active' : ''; ?>"
                                data-periodo="<?php echo $h($chave); ?>"><?php echo $h($rotulo); ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="form-row">
                    <div class="form-group col-12 col-md-4">
                        <label class="cxf-label" for="funcionario">Funcionário</label>
                        <select class="form-control" id="funcionario" name="funcionario" <?php echo $temAcessoCompleto ? '' : 'disabled'; ?>>
                            <?php if ($temAcessoCompleto): ?>
                                <option value="todos" <?php echo $funcionarioSel === 'todos' ? 'selected' : ''; ?>>Todos os funcionários</option>
                                <option value="caixa_unificado" <?php echo $funcionarioSel === 'caixa_unificado' ? 'selected' : ''; ?>>Caixa Unificado (por dia)</option>
                            <?php endif; ?>
                            <?php foreach ($funcionarios as $f): ?>
                                <option value="<?php echo $h($f['usuario']); ?>" <?php echo $funcionarioSel === $f['usuario'] ? 'selected' : ''; ?>>
                                    <?php echo $h($f['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-6 col-md-2">
                        <label class="cxf-label" for="data_inicial">Data inicial</label>
                        <input type="date" class="form-control" id="data_inicial" name="data_inicial" max="<?php echo $h(date('Y-12-31')); ?>"
                               value="<?php echo $h($periodoIni ?? ''); ?>">
                    </div>
                    <div class="form-group col-6 col-md-2">
                        <label class="cxf-label" for="data_final">Data final</label>
                        <input type="date" class="form-control" id="data_final" name="data_final" max="<?php echo $h(date('Y-12-31')); ?>"
                               value="<?php echo $h($periodoFim ?? ''); ?>">
                    </div>
                    <div class="form-group col-12 col-md-4">
                        <label class="cxf-label" for="situacao">Situação do caixa</label>
                        <select class="form-control" id="situacao" name="situacao">
                            <?php foreach ($situacoes as $chave => $rotulo): ?>
                                <option value="<?php echo $h($chave); ?>" <?php echo $situacao === $chave ? 'selected' : ''; ?>><?php echo $h($rotulo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="cxfAvancado" class="collapse<?php echo $avancadoAtivo ? ' show' : ''; ?>">
                    <div class="cxf-adv">
                        <span class="cxf-label">Somente caixas com</span>
                        <div class="cxf-checks mb-3">
                            <?php foreach ($possuiOpcoes as $chave => $op): ?>
                                <label class="cxf-check">
                                    <input type="checkbox" name="possui[]" value="<?php echo $h($chave); ?>" <?php echo in_array($chave, $possui, true) ? 'checked' : ''; ?>>
                                    <span><i class="fa <?php echo $h($op[1]); ?>" aria-hidden="true"></i> <?php echo $h($op[0]); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-6 col-md-3">
                                <label class="cxf-label" for="valor_min">Total em caixa — mínimo</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">R$</span></div>
                                    <input type="text" class="form-control cxf-money" id="valor_min" name="valor_min" placeholder="0,00" value="<?php echo $h($valorMinTxt); ?>">
                                </div>
                            </div>
                            <div class="form-group col-6 col-md-3">
                                <label class="cxf-label" for="valor_max">Total em caixa — máximo</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">R$</span></div>
                                    <input type="text" class="form-control cxf-money" id="valor_max" name="valor_max" placeholder="0,00" value="<?php echo $h($valorMaxTxt); ?>">
                                </div>
                            </div>
                            <div class="form-group col-12 col-md-6">
                                <label class="cxf-label" for="ordenar">Ordenar por</label>
                                <select class="form-control" id="ordenar" name="ordenar">
                                    <?php foreach ($ordenacoes as $chave => $rotulo): ?>
                                        <option value="<?php echo $h($chave); ?>" <?php echo $ordenar === $chave ? 'selected' : ''; ?>><?php echo $h($rotulo); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <?php if ($temAcessoCompleto): ?>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="modo_visualizacao" name="modo_visualizacao" value="1" <?php echo (isset($_GET['modo_visualizacao']) && $_GET['modo_visualizacao'] == '1') ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="modo_visualizacao">
                                <strong>Modo de visualização</strong> — consolida as datas do período em um único caixa (com "Todos os funcionários", unifica também os funcionários). Apenas consulta: sem depósitos, saídas ou fechamentos.
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cxf-actions">
                    <button type="submit" class="cxf-btn cxf-btn-primary">
                        <i class="fa fa-search" aria-hidden="true"></i> Pesquisar
                    </button>
                    <a href="index.php" class="cxf-btn cxf-btn-light">
                        <i class="fa fa-eraser" aria-hidden="true"></i> Limpar filtros
                    </a>
                    <span class="cxf-actions-spacer"></span>
                    <?php if ($isAdmin): ?>
                    <a href="auditoria.php" class="cxf-btn cxf-btn-dark" title="Auditoria completa do caixa (somente administrador)">
                        <i class="fa fa-shield" aria-hidden="true"></i> Auditoria
                    </a>
                    <?php endif; ?>
                    <a href="../analitico/analiticos.php" class="cxf-btn cxf-btn-green">
                        <i class="fa fa-file-excel-o" aria-hidden="true"></i> Relatório Analítico (XLSX)
                    </a>
                </div>
            </form>

            <?php
            // ============================ CONSULTA ============================
            $conditions = [];
            $params = [];
            $filtered = false;
            $isUnificado = ($temAcessoCompleto && $funcionarioSel === 'caixa_unificado');

            if ($funcionarioSel !== 'todos' && $funcionarioSel !== 'caixa_unificado') {
                $conditions[] = 'funcionario = :funcionario';
                $params[':funcionario'] = $funcionarioSel;
                $filtered = true;
            }

            if ($periodoIni && $periodoFim) {
                $conditions[] = 'DATE(data) BETWEEN :data_inicial AND :data_final';
                $params[':data_inicial'] = $periodoIni;
                $params[':data_final']   = $periodoFim;
                $filtered = true;
            } elseif ($periodoIni) {
                $conditions[] = 'DATE(data) >= :data_inicial';
                $params[':data_inicial'] = $periodoIni;
                $filtered = true;
            } elseif ($periodoFim) {
                $conditions[] = 'DATE(data) <= :data_final';
                $params[':data_final'] = $periodoFim;
                $filtered = true;
            }

            // Datas do relatório unificado (modo de visualização)
            $relIni = $periodoIni;
            $relFim = $periodoFim;
            if ($relIni && !$relFim) { $relFim = $hoje; }
            if ($relFim && !$relIni) { $relIni = $relFim; }

            // ===== Modo de visualização (redireciona para o Caixa Unificado do Período) =====
            $modoVisualizacao = (isset($_GET['modo_visualizacao']) && $_GET['modo_visualizacao'] == '1') && $temAcessoCompleto;
            $redirecionarPeriodo = false;
            $avisoTopo = '';

            if ($modoVisualizacao) {
                echo '<script>window.MODO_VISUALIZACAO = true; window.__skipAbrirCaixa = true;'
                    . 'document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("btnTransportarSaldo"); if(b){b.style.display="none";}});'
                    . '</script>';
            }

            if ($modoVisualizacao && $relIni && $relFim) {
                $funcParam = ($funcionarioSel !== 'caixa_unificado' && $funcionarioSel !== 'todos') ? $funcionarioSel : 'todos';
                $urlPeriodo = 'unificado_periodo.php?funcionario=' . urlencode($funcParam)
                            . '&data_inicial=' . urlencode($relIni) . '&data_final=' . urlencode($relFim);
                $avisoTopo = '<div class="alert alert-info"><i class="fa fa-eye" aria-hidden="true"></i> '
                    . 'Abrindo o <strong>Caixa Unificado do Período</strong> (consolida as datas dos caixas)…</div>'
                    . '<script>window.location.replace(' . json_encode($urlPeriodo) . ');</script>';
                $redirecionarPeriodo = true;
            } elseif ($modoVisualizacao) {
                $avisoTopo = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> '
                    . 'Para o modo de visualização, selecione um período (semana, mês, ano) ou um intervalo de datas.</div>';
            }

            $linhas = [];
            $limitado = false;

            if (!$redirecionarPeriodo) {
                $uniao = '
                    SELECT ' . cx_func_sql('funcionario') . ' AS funcionario, data, "ato" as tipo, total
                    FROM atos_liquidados
                    UNION ALL
                    SELECT ' . cx_func_sql('funcionario') . ' AS funcionario, data_pagamento as data, "pagamento" as tipo, total_pagamento as total
                    FROM pagamento_os
                    UNION ALL
                    SELECT ' . cx_func_sql('funcionario') . ' AS funcionario, data_devolucao as data, "devolucao" as tipo, total_devolucao as total
                    FROM devolucao_os
                    UNION ALL
                    SELECT funcionario, data, "saida" as tipo, valor_saida as total
                    FROM saidas_despesas WHERE status = "ativo"
                    UNION ALL
                    SELECT funcionario, data_caixa as data, "deposito" as tipo, valor_do_deposito as total
                    FROM deposito_caixa WHERE status = "ativo"
                    UNION ALL
                    SELECT funcionario, data_caixa as data, "caixa" as tipo, saldo_inicial as total
                    FROM caixa WHERE status = "aberto"';

                $campos = '
                    DATE(data) as data,
                    SUM(CASE WHEN tipo = "ato" THEN total ELSE 0 END) as total_atos,
                    SUM(CASE WHEN tipo = "pagamento" THEN total ELSE 0 END) as total_pagamentos,
                    SUM(CASE WHEN tipo = "devolucao" THEN total ELSE 0 END) as total_devolucoes,
                    SUM(CASE WHEN tipo = "saida" THEN total ELSE 0 END) as total_saidas,
                    SUM(CASE WHEN tipo = "deposito" THEN total ELSE 0 END) as total_depositos';

                if ($isUnificado) {
                    $sql = 'SELECT GROUP_CONCAT(DISTINCT funcionario SEPARATOR ", ") as funcionarios,' . $campos . ' FROM (' . $uniao . ') as fluxos';
                } else {
                    $sql = 'SELECT funcionario,' . $campos . ' FROM (' . $uniao . ') as fluxos';
                }
                if ($conditions) {
                    $sql .= ' WHERE ' . implode(' AND ', $conditions);
                }
                $sql .= $isUnificado ? ' GROUP BY DATE(data)' : ' GROUP BY funcionario, DATE(data)';
                $sql .= ' ORDER BY DATE(data) DESC';
                if (!$filtered) {
                    $sql .= ' LIMIT 50';
                    $limitado = true;
                }

                $stmt = $conn->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($resultados as $r) {
                    $data = $r['data'];

                    if ($isUnificado) {
                        // ---------- Caixa unificado do dia ----------
                        $st = $conn->prepare('SELECT forma_de_pagamento, SUM(total_pagamento) AS t FROM pagamento_os WHERE DATE(data_pagamento) = :d GROUP BY forma_de_pagamento');
                        $st->execute([':d' => $data]);
                        $conta = 0.0; $especie = 0.0;
                        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $pg) {
                            if ($pg['forma_de_pagamento'] === 'Espécie') { $especie += (float) $pg['t']; }
                            elseif (in_array($pg['forma_de_pagamento'], CX_FORMAS_CONTA, true)) { $conta += (float) $pg['t']; }
                        }
                        $st = $conn->prepare("SELECT COALESCE(SUM(total_devolucao),0) FROM devolucao_os WHERE DATE(data_devolucao) = :d AND forma_devolucao = 'Espécie'");
                        $st->execute([':d' => $data]);
                        $devEspecie = (float) $st->fetchColumn();

                        $st = $conn->prepare("SELECT COALESCE(SUM(valor_do_deposito),0) AS t,
                                                     COALESCE(SUM(CASE WHEN caminho_anexo IS NULL OR caminho_anexo = '' OR caminho_anexo LIKE 'sem_comprovante_%' THEN 1 ELSE 0 END),0) AS sc
                                                FROM deposito_caixa WHERE DATE(data_caixa) = :d AND status = 'ativo'");
                        $st->execute([':d' => $data]);
                        $dp = $st->fetch(PDO::FETCH_ASSOC);

                        $st = $conn->prepare('SELECT COALESCE(SUM(saldo_inicial),0) FROM caixa WHERE DATE(data_caixa) = :d');
                        $st->execute([':d' => $data]);
                        $saldoIni = (float) $st->fetchColumn();

                        $st = $conn->prepare('SELECT COALESCE(SUM(valor_transportado),0) FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :d');
                        $st->execute([':d' => $data]);
                        $transp = (float) $st->fetchColumn();

                        $emCaixa = round($saldoIni + $especie - $devEspecie - (float) $r['total_saidas'] - (float) $dp['t'] - $transp, 2);

                        $linhas[] = [
                            'tipo' => 'unificado', 'funcionario' => (string) $r['funcionarios'], 'nome' => 'Caixa Unificado',
                            'data' => $data, 'saldo_inicial' => $saldoIni, 'atos' => (float) $r['total_atos'],
                            'conta' => $conta, 'especie' => $especie, 'devolucoes' => (float) $r['total_devolucoes'],
                            'saidas' => (float) $r['total_saidas'], 'depositos' => (float) $dp['t'], 'transportado' => $transp,
                            'em_caixa' => $emCaixa, 'caixa_id' => null, 'caixa_status' => null, 'origem' => null,
                            'sem_comprovante' => (int) $dp['sc'], 'pode_lancar_saldo' => false, 'ultimo_caixa' => false,
                        ];
                    } else {
                        // ---------- Caixa individual: mesmo cálculo das validações ----------
                        $func = (string) $r['funcionario'];
                        $s = cx_saldo_caixa($conn, $func, $data);
                        $linhas[] = [
                            'tipo' => 'individual', 'funcionario' => $func, 'nome' => $nomesFuncionarios[$func] ?? '',
                            'data' => $data, 'saldo_inicial' => $s['saldo_inicial'], 'atos' => (float) $r['total_atos'],
                            'conta' => $s['recebido_conta'], 'especie' => $s['recebido_especie'], 'devolucoes' => $s['total_devolucoes'],
                            'saidas' => $s['saidas'], 'depositos' => $s['depositos'], 'transportado' => $s['transportado'],
                            'em_caixa' => $s['disponivel'], 'caixa_id' => $s['caixa_id'], 'caixa_status' => $s['caixa_status'],
                            'origem' => $s['origem_saldo'], 'sem_comprovante' => $s['depositos_sem_comprovante'],
                            'pode_lancar_saldo' => cx_pode_lancar_saldo_inicial($s), 'ultimo_caixa' => $s['ultimo_caixa'],
                        ];
                    }
                }

                // ===== Situação e filtros pós-cálculo =====
                foreach ($linhas as &$l) {
                    // Zerado = fechado; exceto o caixa atual do funcionário, que segue aberto até o fechamento
                    $caixaCorrente = ($l['tipo'] === 'individual' && $l['caixa_status'] === 'aberto' && $l['ultimo_caixa']);
                    $l['fechado'] = (cx_centavos($l['em_caixa']) === 0) && !$caixaCorrente;
                }
                unset($l);

                $linhas = array_values(array_filter($linhas, function ($l) use ($situacao, $possui, $valorMin, $valorMax) {
                    switch ($situacao) {
                        case 'aberto':    if ($l['fechado']) return false; break;
                        case 'fechado':   if (!$l['fechado']) return false; break;
                        case 'zerado':    if (!($l['tipo'] === 'individual' && $l['pode_lancar_saldo'] && $l['origem'] === 'zerado')) return false; break;
                        case 'sem_caixa': if (!($l['tipo'] === 'individual' && $l['caixa_id'] === null)) return false; break;
                        case 'negativo':  if (cx_centavos($l['em_caixa']) >= 0) return false; break;
                    }
                    $mapa = [
                        'atos' => 'atos', 'especie' => 'especie', 'conta' => 'conta', 'devolucoes' => 'devolucoes',
                        'saidas' => 'saidas', 'depositos' => 'depositos', 'sem_comprovante' => 'sem_comprovante',
                        'transporte' => 'transportado',
                    ];
                    foreach ($possui as $p) {
                        if (cx_centavos($l[$mapa[$p]]) <= 0) return false;
                    }
                    if ($valorMin !== null && cx_centavos($l['em_caixa']) < cx_centavos($valorMin)) return false;
                    if ($valorMax !== null && cx_centavos($l['em_caixa']) > cx_centavos($valorMax)) return false;
                    return true;
                }));

                // ===== Ordenação =====
                usort($linhas, function ($a, $b) use ($ordenar) {
                    switch ($ordenar) {
                        case 'data_asc':     return strcmp($a['data'], $b['data']) ?: strcmp($a['funcionario'], $b['funcionario']);
                        case 'caixa_desc':   return $b['em_caixa'] <=> $a['em_caixa'];
                        case 'caixa_asc':    return $a['em_caixa'] <=> $b['em_caixa'];
                        case 'atos_desc':    return $b['atos'] <=> $a['atos'];
                        case 'especie_desc': return $b['especie'] <=> $a['especie'];
                        case 'func_asc':     return strcasecmp($a['funcionario'], $b['funcionario']) ?: strcmp($b['data'], $a['data']);
                        default:             return strcmp($b['data'], $a['data']) ?: strcmp($a['funcionario'], $b['funcionario']);
                    }
                });
            }

            // ===== Indicadores =====
            $kpi = ['qtd' => count($linhas), 'abertos' => 0, 'fechados' => 0, 'atos' => 0.0, 'especie' => 0.0,
                    'conta' => 0.0, 'saidas' => 0.0, 'depositos' => 0.0, 'em_caixa' => 0.0];
            foreach ($linhas as $l) {
                $l['fechado'] ? $kpi['fechados']++ : $kpi['abertos']++;
                foreach (['atos', 'especie', 'conta', 'saidas', 'depositos', 'em_caixa'] as $k) {
                    $kpi[$k] += $l[$k];
                }
            }

            // ===== Filtros ativos (chips) =====
            $chips = [];
            if ($funcionarioSel === 'caixa_unificado') {
                $chips[] = 'Caixa Unificado';
            } elseif ($funcionarioSel !== 'todos') {
                $chips[] = 'Funcionário: ' . ($nomesFuncionarios[$funcionarioSel] ?? $funcionarioSel);
            }
            if ($periodoIni || $periodoFim) {
                $txt = ($periodoIni && $periodoFim && $periodoIni === $periodoFim)
                    ? cx_data_br($periodoIni)
                    : (($periodoIni ? cx_data_br($periodoIni) : '…') . ' a ' . ($periodoFim ? cx_data_br($periodoFim) : '…'));
                $chips[] = ($selectedPeriodo !== 'personalizado' ? $periodos[$selectedPeriodo] . ': ' : 'Período: ') . $txt;
            } else {
                $chips[] = 'Período: todos';
            }
            if ($situacao !== 'todas') { $chips[] = $situacoes[$situacao]; }
            foreach ($possui as $p) { $chips[] = 'Com ' . mb_strtolower($possuiOpcoes[$p][0], 'UTF-8'); }
            if ($valorMin !== null) { $chips[] = 'Em caixa ≥ ' . $fmt($valorMin); }
            if ($valorMax !== null) { $chips[] = 'Em caixa ≤ ' . $fmt($valorMax); }

            // ===== Renderização das ações (cards e tabela) =====
            $renderAcoes = function (array $l) use ($modoVisualizacao, $isAdmin, $h, $js) {
                $html = '';
                if ($l['tipo'] === 'individual') {
                    $f = $l['funcionario']; $d = $l['data'];
                    if (!$modoVisualizacao) {
                        $html .= '<button type="button" title="Saídas e Despesas" class="btn btn-delete btn-sm btn-icon" onclick="event.stopPropagation(); cadastrarSaida(' . $js($f) . ', ' . $js($d) . ')"><i class="fa fa-sign-out" aria-hidden="true"></i></button>';
                        $html .= '<button type="button" title="Depósito do Caixa" class="btn btn-success btn-sm btn-icon" onclick="event.stopPropagation(); cadastrarDeposito(' . $js($f) . ', ' . $js($d) . ')"><i class="fa fa-university" aria-hidden="true"></i></button>';
                        if ($isAdmin && $l['pode_lancar_saldo']) {
                            $html .= '<button type="button" title="Lançar saldo inicial (administrador)" class="btn btn-saldo-admin btn-sm btn-icon" onclick="event.stopPropagation(); abrirSaldoInicialAdmin('
                                . $js($l['caixa_id']) . ', ' . $js($f) . ', ' . $js($d) . ', ' . $js($l['saldo_inicial']) . ')"><i class="fa fa-plus-square" aria-hidden="true"></i></button>';
                        }
                    }
                    $printUrl = $l['caixa_id']
                        ? 'imprimir_fechamento_caixa.php?id=' . urlencode((string) $l['caixa_id'])
                        : 'imprimir_fechamento_caixa.php?funcionario=' . urlencode($f) . '&data=' . urlencode($d);
                    if ($isAdmin) {
                        $audUrl = 'auditoria.php?' . http_build_query(['funcionario_caixa' => $f, 'data_caixa' => $d]);
                        $html .= '<a href="' . $h($audUrl) . '" title="Auditoria deste caixa" class="btn btn-dark btn-sm btn-icon" onclick="event.stopPropagation();"><i class="fa fa-history" aria-hidden="true"></i></a>';
                    }
                    $html .= '<a href="' . $h($printUrl) . '" target="_blank" title="Imprimir Fechamento" class="btn btn-primary btn-sm btn-icon" onclick="event.stopPropagation();"><i class="fa fa-file-pdf-o"></i></a>';
                } else {
                    $html .= '<button type="button" title="Ver Depósitos do Caixa" class="btn btn-success btn-sm btn-icon" onclick="event.stopPropagation(); verDepositosCaixa(' . $js($l['data']) . ')"><i class="fa fa-list" aria-hidden="true"></i></button>';
                    $html .= '<a href="imprimir_fechamento_caixa_unificado.php?data=' . $h(urlencode($l['data'])) . '" target="_blank" title="Imprimir Fechamento Caixa Unificado" class="btn btn-primary btn-sm btn-icon" onclick="event.stopPropagation();"><i class="fa fa-file-pdf-o"></i></a>';
                }
                return $html;
            };

            $renderLock = function (array $l) use ($modoVisualizacao, $js) {
                if ($l['tipo'] !== 'individual' || $modoVisualizacao) {
                    return '';
                }
                // Fecha com saldo positivo, ou caixa aberto já zerado (sem valor a transportar)
                $podeFechar = cx_centavos($l['em_caixa']) > 0
                    || ($l['caixa_status'] === 'aberto' && $l['ultimo_caixa'] && cx_centavos($l['em_caixa']) === 0);
                return '<button type="button" class="btn btn-lock btn-sm btn-icon" ' . ($podeFechar ? '' : 'disabled') . ' title="Fechar caixa" onclick="event.stopPropagation(); fecharCaixaRapido('
                    . $js($l['funcionario']) . ', ' . $js($l['data']) . ')"><i class="fa fa-lock"></i></button>';
            };

            // Botão com texto (além do ícone) para o administrador lançar o saldo inicial
            $renderBotaoSaldo = function (array $l) use ($modoVisualizacao, $isAdmin, $js) {
                if ($l['tipo'] !== 'individual' || $modoVisualizacao || !$isAdmin || !$l['pode_lancar_saldo']) {
                    return '';
                }
                $rotulo = cx_centavos($l['saldo_inicial']) > 0 ? 'Ajustar saldo inicial' : 'Lançar saldo inicial';
                return '<button type="button" class="btn btn-saldo-admin btn-sm cxf-btn-saldo-card" onclick="event.stopPropagation(); abrirSaldoInicialAdmin('
                    . $js($l['caixa_id']) . ', ' . $js($l['funcionario']) . ', ' . $js($l['data']) . ', ' . $js($l['saldo_inicial'])
                    . ')"><i class="fa fa-plus-square" aria-hidden="true"></i> ' . $rotulo . '</button>';
            };

            $renderPendencias = function (array $l) use ($h) {
                $tags = [];
                if ($l['tipo'] === 'individual') {
                    if ($l['caixa_id'] === null) {
                        $tags[] = '<span class="cxf-tag info" title="Houve movimento neste dia sem abertura de caixa"><i class="fa fa-info-circle"></i> Sem abertura</span>';
                    } elseif ($l['pode_lancar_saldo'] && $l['origem'] === 'zerado') {
                        $tags[] = '<span class="cxf-tag warn" title="Caixa aberto sem saldo inicial — o administrador pode lançar o saldo"><i class="fa fa-hourglass-half"></i> Aberto zerado</span>';
                    } elseif ($l['origem'] === 'administrador') {
                        $tags[] = '<span class="cxf-tag ok" title="Saldo inicial lançado pelo administrador"><i class="fa fa-user-secret"></i> Saldo pelo admin</span>';
                    }
                    if ($l['caixa_status'] === 'fechado') {
                        $tags[] = '<span class="cxf-tag ok"><i class="fa fa-check"></i> Fechamento registrado</span>';
                    }
                }
                if ($l['sem_comprovante'] > 0) {
                    $tags[] = '<span class="cxf-tag warn"><i class="fa fa-paperclip"></i> ' . (int) $l['sem_comprovante'] . ' depósito(s) sem comprovante</span>';
                }
                if (cx_centavos($l['em_caixa']) < 0) {
                    $tags[] = '<span class="cxf-tag danger"><i class="fa fa-exclamation-triangle"></i> Saldo negativo</span>';
                }
                return $tags ? '<div class="cxf-pend">' . implode('', $tags) . '</div>' : '';
            };
            ?>

            <?php echo $avisoTopo; ?>

            <?php if (!$redirecionarPeriodo): ?>
            <!-- ============================ RESULTADOS ============================ -->
            <div class="cxf-active">
                <?php foreach ($chips as $c): ?>
                    <span class="cxf-chip"><i class="fa fa-filter" aria-hidden="true"></i> <?php echo $h($c); ?></span>
                <?php endforeach; ?>
                <?php if ($limitado): ?>
                    <span class="cxf-chip" style="background:#fff7ed;color:#c2410c;"><i class="fa fa-info-circle"></i> Exibindo os 50 dias mais recentes — use um período para ver mais</span>
                <?php endif; ?>
            </div>

            <div class="cxf-kpis">
                <div class="cxf-kpi k-qtd"><div class="lb">Caixas</div><div class="vl"><?php echo (int) $kpi['qtd']; ?></div><div class="sb"><?php echo (int) $kpi['abertos']; ?> abertos · <?php echo (int) $kpi['fechados']; ?> fechados</div></div>
                <div class="cxf-kpi k-atos"><div class="lb">Atos liquidados</div><div class="vl"><?php echo $fmt($kpi['atos']); ?></div></div>
                <div class="cxf-kpi k-esp"><div class="lb">Recebido em espécie</div><div class="vl"><?php echo $fmt($kpi['especie']); ?></div></div>
                <div class="cxf-kpi k-conta"><div class="lb">Recebido em conta</div><div class="vl"><?php echo $fmt($kpi['conta']); ?></div></div>
                <div class="cxf-kpi k-said"><div class="lb">Saídas e despesas</div><div class="vl"><?php echo $fmt($kpi['saidas']); ?></div></div>
                <div class="cxf-kpi k-dep"><div class="lb">Depósitos</div><div class="vl"><?php echo $fmt($kpi['depositos']); ?></div></div>
                <div class="cxf-kpi k-cx"><div class="lb">Total em caixa</div><div class="vl"><?php echo $fmt($kpi['em_caixa']); ?></div></div>
            </div>

            <div class="cxf-toolbar">
                <h5>Resultados <span class="cxf-count" id="cxfCount"><?php echo (int) $kpi['qtd']; ?> caixa(s)</span></h5>
                <div class="cxf-tools">
                    <div class="cxf-search">
                        <i class="fa fa-search" aria-hidden="true"></i>
                        <input type="search" id="cxfBusca" class="form-control form-control-sm" placeholder="Buscar funcionário ou data na lista…" aria-label="Buscar na lista">
                    </div>
                    <div class="cxf-view" role="group" aria-label="Modo de exibição">
                        <button type="button" class="cxf-view-btn active" data-view="cards" title="Cartões"><i class="fa fa-th-large"></i></button>
                        <button type="button" class="cxf-view-btn" data-view="tabela" title="Tabela"><i class="fa fa-table"></i></button>
                    </div>
                    <button type="button" class="cxf-btn cxf-btn-sm cxf-btn-green-outline" id="cxfExportar" <?php echo $linhas ? '' : 'disabled'; ?>>
                        <i class="fa fa-download" aria-hidden="true"></i> Exportar CSV
                    </button>
                </div>
            </div>

            <?php if (!$linhas): ?>
                <div class="cxf-empty">
                    <i class="fa fa-inbox" aria-hidden="true"></i>
                    Nenhum caixa encontrado com os filtros atuais.<br>
                    <small>Tente outro período ou limpe os filtros avançados.</small>
                </div>
            <?php endif; ?>

            <div id="cardsResultados" class="row cards-wrap">
                <?php foreach ($linhas as $l):
                    $isClosed    = $l['fechado'];
                    $cardBgClass = $isClosed ? 'pastel-closed' : 'pastel-open';
                    $badgeClass  = $isClosed ? 'badge-closed' : 'badge-open';
                    $statusLabel = $isClosed ? 'Fechado' : 'Aberto';
                    $statusIcon  = $isClosed ? 'fa-lock' : 'fa-unlock-alt';
                    $busca       = mb_strtolower($l['funcionario'] . ' ' . $l['nome'] . ' ' . cx_data_br($l['data']), 'UTF-8');
                ?>
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3 caixa-col cxf-item" data-busca="<?php echo $h($busca); ?>">
                        <div class="card caixa-card <?php echo $cardBgClass; ?>" onclick="verDetalhes(<?php echo $js($l['funcionario']); ?>, <?php echo $js($l['data']); ?>, <?php echo $js($l['tipo']); ?>)">
                            <div class="card-body">
                                <div class="header-block topline">
                                    <div class="title-strong"><?php echo $h($l['tipo'] === 'unificado' ? 'Caixa Unificado' : $l['funcionario']); ?></div>
                                    <span class="badge-status <?php echo $badgeClass; ?>"><i class="fa <?php echo $statusIcon; ?>"></i> <?php echo $statusLabel; ?></span>
                                </div>
                                <?php if ($l['tipo'] === 'individual' && $l['nome'] !== ''): ?>
                                    <div class="cxf-nome"><?php echo $h($l['nome']); ?></div>
                                <?php endif; ?>
                                <div class="muted">Data: <?php echo $h(cx_data_br($l['data'])); ?></div>
                                <?php echo $renderPendencias($l) . $renderBotaoSaldo($l); ?>

                                <div class="metrics">
                                    <div class="metric"><span class="chip chip-saldo">Saldo Inicial</span><div class="k"><?php echo $fmt($l['saldo_inicial']); ?></div></div>
                                    <div class="metric"><span class="chip chip-atos">Atos Liquidados</span><div class="k"><?php echo $fmt($l['atos']); ?></div></div>
                                    <div class="metric"><span class="chip chip-conta">Recebido em Conta</span><div class="k"><?php echo $fmt($l['conta']); ?></div></div>
                                    <div class="metric"><span class="chip chip-especie">Recebido em Espécie</span><div class="k"><?php echo $fmt($l['especie']); ?></div></div>
                                    <div class="metric"><span class="chip chip-devolucoes">Devoluções</span><div class="k"><?php echo $fmt($l['devolucoes']); ?></div></div>
                                    <div class="metric"><span class="chip chip-saidas">Saídas e Despesas</span><div class="k"><?php echo $fmt($l['saidas']); ?></div></div>
                                    <div class="metric"><span class="chip chip-deposito">Depósito do Caixa</span><div class="k"><?php echo $fmt($l['depositos']); ?></div></div>
                                    <div class="metric"><span class="chip chip-total">Total em Caixa</span><div class="k"><?php echo $fmt($l['em_caixa']); ?></div></div>
                                </div>

                                <div class="card-footer-eq">
                                    <div class="card-actions"><?php echo $renderAcoes($l); ?></div>
                                    <?php echo $renderLock($l); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div><!-- /cardsResultados -->

            <div id="tabelaResultadosWrap" class="cxf-table-wrap cxf-hidden">
                <table class="table cxf-table" id="tabelaResultados">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Data</th>
                            <th>Situação</th>
                            <th class="num">Saldo inicial</th>
                            <th class="num">Atos</th>
                            <th class="num">Conta</th>
                            <th class="num">Espécie</th>
                            <th class="num">Devoluções</th>
                            <th class="num">Saídas</th>
                            <th class="num">Depósitos</th>
                            <th class="num">Em caixa</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhas as $l):
                            $busca = mb_strtolower($l['funcionario'] . ' ' . $l['nome'] . ' ' . cx_data_br($l['data']), 'UTF-8');
                        ?>
                            <tr class="cxf-item" data-busca="<?php echo $h($busca); ?>" onclick="verDetalhes(<?php echo $js($l['funcionario']); ?>, <?php echo $js($l['data']); ?>, <?php echo $js($l['tipo']); ?>)">
                                <td>
                                    <strong><?php echo $h($l['tipo'] === 'unificado' ? 'Caixa Unificado' : $l['funcionario']); ?></strong>
                                    <?php if ($l['tipo'] === 'individual' && $l['nome'] !== ''): ?><div class="cxf-nome"><?php echo $h($l['nome']); ?></div><?php endif; ?>
                                </td>
                                <td><?php echo $h(cx_data_br($l['data'])); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $l['fechado'] ? 'badge-closed' : 'badge-open'; ?>">
                                        <i class="fa <?php echo $l['fechado'] ? 'fa-lock' : 'fa-unlock-alt'; ?>"></i> <?php echo $l['fechado'] ? 'Fechado' : 'Aberto'; ?>
                                    </span>
                                    <?php echo $renderPendencias($l) . $renderBotaoSaldo($l); ?>
                                </td>
                                <td class="num"><?php echo $fmt($l['saldo_inicial']); ?></td>
                                <td class="num"><?php echo $fmt($l['atos']); ?></td>
                                <td class="num"><?php echo $fmt($l['conta']); ?></td>
                                <td class="num"><?php echo $fmt($l['especie']); ?></td>
                                <td class="num"><?php echo $fmt($l['devolucoes']); ?></td>
                                <td class="num"><?php echo $fmt($l['saidas']); ?></td>
                                <td class="num"><?php echo $fmt($l['depositos']); ?></td>
                                <td class="num"><strong><?php echo $fmt($l['em_caixa']); ?></strong></td>
                                <td><div class="card-actions"><?php echo $renderAcoes($l) . $renderLock($l); ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if ($linhas): ?>
                    <tfoot>
                        <tr>
                            <td colspan="4">Totais (<?php echo (int) $kpi['qtd']; ?>)</td>
                            <td class="num"><?php echo $fmt($kpi['atos']); ?></td>
                            <td class="num"><?php echo $fmt($kpi['conta']); ?></td>
                            <td class="num"><?php echo $fmt($kpi['especie']); ?></td>
                            <td class="num"><?php echo $fmt(array_sum(array_column($linhas, 'devolucoes'))); ?></td>
                            <td class="num"><?php echo $fmt($kpi['saidas']); ?></td>
                            <td class="num"><?php echo $fmt($kpi['depositos']); ?></td>
                            <td class="num"><?php echo $fmt($kpi['em_caixa']); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <div id="cxfSemResultadoBusca" class="cxf-empty cxf-hidden" style="margin-top:10px;">
                <i class="fa fa-search" aria-hidden="true"></i> Nenhum caixa corresponde à busca.
            </div>

            <script>
                window.CX_LINHAS = <?php echo json_encode(array_map(function ($l) {
                    return [
                        'funcionario' => $l['tipo'] === 'unificado' ? 'Caixa Unificado' : $l['funcionario'],
                        'nome' => $l['nome'], 'data' => $l['data'], 'situacao' => $l['fechado'] ? 'Fechado' : 'Aberto',
                        'saldo_inicial' => $l['saldo_inicial'], 'atos' => $l['atos'], 'conta' => $l['conta'],
                        'especie' => $l['especie'], 'devolucoes' => $l['devolucoes'], 'saidas' => $l['saidas'],
                        'depositos' => $l['depositos'], 'transportado' => $l['transportado'], 'em_caixa' => $l['em_caixa'],
                        'sem_comprovante' => $l['sem_comprovante'],
                    ];
                }, $linhas), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
            </script>
            <?php endif; ?>

            <div class="cxf-versao">Fluxo de Caixa · versão <?php echo $h(ATLAS_CAIXA_VERSAO); ?></div>
        </div>
    </div>
    <script>window.CX_IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>; window.CX_USUARIO = <?php echo json_encode($user['usuario'], JSON_HEX_TAG); ?>;</script>

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="detalhesModal" tabindex="-1" role="dialog" aria-labelledby="detalhesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center justify-content-center position-relative">
                    <h5 class="modal-title text-center mb-0" id="detalhesModalLabel"></h5>
                    <div id="modalStatusPill" class="modal-status-pill"></div>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" style="position:absolute; right:12px; top:8px;">&times;</button>
                </div>
                <div class="modal-body">
                    <!-- Administrador: caixa aberto zerado aguardando o saldo inicial -->
                    <div id="detalhesSaldoAdmin" class="cxf-saldo-admin-aviso" style="display:none;">
                        <span><i class="fa fa-hourglass-half" aria-hidden="true"></i> <span id="detalhesSaldoAdminTexto"></span></span>
                        <button type="button" class="btn btn-saldo-admin btn-sm" id="detalhesSaldoAdminBtn"><i class="fa fa-plus-square" aria-hidden="true"></i> Lançar saldo inicial</button>
                    </div>
                    <div class="row">
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-primary mb-3" style="background-color: #005d15 !important">
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
                            <div class="card text-white" style="background-color: #6f42c1;">
                                <div class="card-header">Atos Manuais</div>
                                <div class="card-body"><h5 class="card-title" id="cardTotalAtosManuais">R$ 0,00</h5></div>
                            </div>
                        </div>
                        <!-- NOVO: Atos Isentos -->
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white" style="background-color: #17a2b8;">
                                <div class="card-header">Atos Isentos</div>
                                <div class="card-body"><h5 class="card-title" id="cardAtosIsentos">R$ 0,00</h5></div>
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
                                <div class="card-header">Depósito do Caixa</div>
                                <div class="card-body"><h5 class="card-title" id="cardDepositoCaixa">R$ 0,00</h5></div>
                            </div>
                        </div>

                        <!-- NOVO: Total em Selos (à vista + diferidos) -->
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-primary mb-3">
                                <div class="card-header">Total em Selos</div>
                                <div class="card-body"><h5 class="card-title" id="cardTotalSelos">R$ 0,00</h5></div>
                            </div>
                        </div>

                        <!-- NOVO: Selos Cancelados (deduzidos do Total em Selos) -->
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white mb-3" style="background-color:#c0392b;">
                                <div class="card-header">Selos Cancelados</div>
                                <div class="card-body"><h5 class="card-title" id="cardSelosCancelados">R$ 0,00</h5></div>
                            </div>
                        </div>

                        <!-- NOVO: Repasse a Credores (Protesto) -->
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white mb-3" style="background-color:#64748b;">
                                <div class="card-header">Repasse a Credores</div>
                                <div class="card-body"><h5 class="card-title" id="cardRepasseCredor">R$ 0,00</h5></div>
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

                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>ATOS LIQUIDADOS</b></div>
                        <div class="card-body">
                            <!-- NOVO: Toolbar de filtros -->
                            <div class="row no-gutters align-items-end mb-3" id="filtrosAtosLiquidados">
                                <div class="col-12 col-md-3 mb-2" id="filtroAtosFuncCol" style="display:none;">
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
                                    <button class="btn btn-outline-secondary btn-sm" id="btnLimparFiltrosAtos">
                                        <i class="fa fa-eraser"></i> Limpar filtros
                                    </button>
                                </div>
                            </div>
                            <!-- /Toolbar de filtros -->

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

                            <!-- NOVO: total com quantidade dinâmica -->
                            <h6 class="total-label d-flex flex-wrap align-items-center">
                                <span class="mr-3">Qtd.: <span id="qtdAtos">0</span></span>
                                <span>Total Atos Liquidados: <span id="totalAtos"></span></span>
                            </h6>
                        </div>
                    </div>

                    <!-- ATOS ISENTOS -->
                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>ATOS ISENTOS</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaAtosIsentos" class="table table-striped table-bordered">
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
                                    <tbody id="detalhesAtosIsentos"></tbody>
                                </table>
                            </div>

                            <h6 class="total-label d-flex flex-wrap align-items-center">
                                <span class="mr-3">Qtd.: <span id="qtdAtosIsentos">0</span></span>
                                <span>Total Atos Isentos: <span id="totalAtosIsentos">R$ 0,00</span></span>
                            </h6>
                        </div>
                    </div>

                    <!-- SELOS -->
                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>SELOS</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaSelos" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Nº Selo</th>
                                            <th>Ato</th>
                                            <th>Tipo</th>
                                            <th>Selagem</th>
                                            <th>Emolumentos</th>
                                            <th>FERJ</th>
                                            <th>FADEP</th>
                                            <th>FERC</th>
                                            <th>FEMP</th>
                                            <th>FERRFIS</th>
                                            <th>TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesSelos"></tbody>
                                </table>
                            </div>
                            <h6 class="total-label">Subtotal dos Selos: <span id="totalSelos">R$ 0,00</span></h6>
                        </div>
                    </div>

                    <!-- NOVO: SELOS CANCELADOS — só aparece quando houver -->
                    <div class="card mb-3" id="listaSelosCanceladosWrap" style="display:none;">
                        <div class="card-header table-title text-center"><b>SELOS CANCELADOS</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaSelosCancelados" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Nº Selo</th>
                                            <th>Ato</th>
                                            <th>Tipo</th>
                                            <th>Selagem</th>
                                            <th>Emolumentos</th>
                                            <th>FERJ</th>
                                            <th>FADEP</th>
                                            <th>FERC</th>
                                            <th>FEMP</th>
                                            <th>FERRFIS</th>
                                            <th>TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesSelosCancelados"></tbody>
                                </table>
                            </div>
                            <h6 class="total-label">Total em Selos Cancelados: <span id="totalSelosCancelados">R$ 0,00</span></h6>
                        </div>
                    </div>

                    <!-- NOVO: REPASSE A CREDORES (PROTESTO) — só aparece quando houver -->
                    <div class="card mb-3" id="listaRepasseCredorWrap" style="display:none;">
                        <div class="card-header table-title text-center"><b>REPASSE A CREDORES (PROTESTO)</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaRepasseCredor" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Nº OS</th>
                                            <th>Cliente</th>
                                            <th>Data</th>
                                            <th>Forma</th>
                                            <th>Valor repassado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesRepasseCredor"></tbody>
                                </table>
                            </div>
                            <h6 class="total-label">Total Repasse a Credores: <span id="totalRepasseCredor">R$ 0,00</span></h6>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>ATOS MANUAIS</b></div>
                        <div class="card-body">
                            <!-- NOVO: Toolbar de filtros -->
                            <div class="row no-gutters align-items-end mb-3" id="filtrosAtosManuais">
                                <div class="col-12 col-md-3 mb-2" id="filtroManuaisFuncCol" style="display:none;">
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
                                    <button class="btn btn-outline-secondary btn-sm" id="btnLimparFiltrosManuais">
                                        <i class="fa fa-eraser"></i> Limpar filtros
                                    </button>
                                </div>
                            </div>
                            <!-- /Toolbar de filtros -->

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

                            <!-- NOVO: total com quantidade dinâmica -->
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
                                            <th>Observação</th>
                                            <th>Comprovante</th>
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
                                        <tr>
                                            <th>Forma de Pagamento</th>
                                            <th>Total</th>
                                        </tr>
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

    <!-- Modal de Cadastro de Saídas -->
    <div class="modal fade" id="cadastroSaidaModal" tabindex="-1" role="dialog" aria-labelledby="cadastroSaidaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-responsive modal-modern" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cadastroSaidaModalLabel">Cadastrar Saída/Despesa</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="cxf-disp" id="saidaDisponivelBox">
                        <div>
                            <div class="lb">Disponível em caixa</div>
                            <small class="text-muted">A saída não pode ultrapassar este valor.</small>
                        </div>
                        <div class="vl" id="saidaDisponivel">R$ 0,00</div>
                    </div>
                    <form id="formCadastroSaida" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group col-12 col-md-8">
                                <label class="input-label" for="titulo">Título</label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required placeholder="Ex.: Combustível, Material de escritório...">
                            </div>
                            <div class="form-group col-12 col-md-4">
                                <label class="input-label" for="valor_saida">Valor da Saída</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">R$</span></div>
                                    <input type="text" class="form-control" id="valor_saida" name="valor_saida" required placeholder="0,00">
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="display:none;">
                            <label class="input-label" for="forma_de_saida">Forma de Saída</label>
                            <select class="form-control" id="forma_de_saida" name="forma_de_saida" required>
                                <option value="Espécie">Espécie</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="input-label d-block" for="anexo">Anexo</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="anexo" name="anexo" required>
                                <label class="custom-file-label" for="anexo">Selecione um arquivo...</label>
                            </div>
                            <small class="input-hint">Formatos aceitos: PDF, JPG, PNG (máx. 10MB).</small>
                        </div>

                        <input type="hidden" id="data_saida" name="data_saida">
                        <input type="hidden" id="data_caixa_saida" name="data_caixa_saida">
                        <input type="hidden" id="funcionario_saida" name="funcionario_saida">

                        <button type="submit" id="btnAdicionarSaida" class="btn btn-primary btn-block">
                            <i class="fa fa-plus-circle" aria-hidden="true"></i> Adicionar
                        </button>
                    </form>

                    <hr>
                    <h5>Saídas/Despesas Cadastradas</h5>
                    <div class="table-responsive">
                        <table id="tabelaSaidasCadastradas" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <th>Título</th>
                                    <th>Valor</th>
                                    <th>Forma de Saída</th>
                                    <th>Anexo</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="detalhesSaidasCadastradas"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Cadastro de Depósito -->
    <div class="modal fade" id="cadastroDepositoModal" tabindex="-1" role="dialog" aria-labelledby="cadastroDepositoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-responsive modal-modern" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cadastroDepositoModalLabel">Cadastrar Depósito do Caixa</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row stats-card">
                        <div class="col-12 col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title2">Disponível em Caixa</h5>
                                    <p class="card-text" id="total_em_caixa" style="font-size:1.5em;">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title2">Depósitos</h5>
                                    <p class="card-text" id="total_depositos" style="font-size:1.5em;">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title2">Saldo Transportado</h5>
                                    <p class="card-text" id="saldo_transportado" style="font-size:1.5em;">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>

                    <form id="formCadastroDeposito" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group col-12 col-md-6">
                                <label class="input-label" for="valor_deposito">Valor do Depósito</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">R$</span></div>
                                    <input type="text" class="form-control" id="valor_deposito" name="valor_deposito" required placeholder="0,00">
                                </div>
                            </div>
                            <div class="form-group col-12 col-md-6">
                                <label class="input-label" for="tipo_deposito">Tipo de Depósito</label>
                                <select class="form-control" id="tipo_deposito" name="tipo_deposito" required>
                                    <option value="" disabled selected>Selecione</option>
                                    <option value="Depósito Bancário">Depósito Bancário</option>
                                    <option value="Espécie">Espécie</option>
                                    <option value="Transferência">Transferência</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row align-items-end">
                            <div class="form-group col-12 col-md-6">
                                <label class="input-label d-block" for="comprovante_deposito">Comprovante de Depósito</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="comprovante_deposito" name="comprovante_deposito" required>
                                    <label class="custom-file-label" for="comprovante_deposito">Selecione um arquivo...</label>
                                </div>
                                <small class="input-hint">PDF, JPG, PNG (máx. 10MB).</small>
                            </div>

                            <div class="form-group col-12 col-md-6" id="sem-comprovante-group" style="display:none;">
                                <div class="custom-control custom-checkbox mt-4">
                                    <input type="checkbox" class="custom-control-input" id="sem_comprovante" name="sem_comprovante">
                                    <label class="custom-control-label" for="sem_comprovante">Sem comprovante</label>
                                </div>
                                <small class="input-hint">Use apenas quando o depósito em espécie não gerar comprovante.</small>
                            </div>
                        </div>

                        <small class="input-hint d-block mb-2"><i class="fa fa-shield" aria-hidden="true"></i> O depósito não pode ultrapassar o valor disponível em caixa.</small>
                        <input type="hidden" id="data_caixa_deposito" name="data_caixa_deposito">
                        <input type="hidden" id="funcionario_deposito" name="funcionario_deposito">

                        <button type="submit" id="btnAdicionarDeposito" class="btn btn-primary btn-block">
                            <i class="fa fa-plus-circle" aria-hidden="true"></i> Adicionar
                        </button>
                    </form>

                    <hr>
                    <h5>Depósitos Registrados</h5>
                    <div class="table-responsive">
                        <table id="tabelaDepositosRegistrados" class="table table-striped table-bordered">
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
                            <tbody id="detalhesDepositosRegistrados"></tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="form-group">
                        <button type="button" id="btnTransportarSaldo" style="width: 100%" class="btn btn-danger" onclick="transportarSaldoFecharCaixa()">
                            <i class="fa fa-lock" aria-hidden="true"></i> Fechar Caixa e Transportar Saldo <i class="fa fa-share" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Anexar Comprovante de Depósito -->
    <div class="modal fade" id="anexarComprovanteModal" tabindex="-1" role="dialog" aria-labelledby="anexarComprovanteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-responsive" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="anexarComprovanteModalLabel">Anexar Comprovante</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formAnexarComprovante" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="input-label d-block" for="arquivo_comprovante">Comprovante</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="arquivo_comprovante" name="arquivo_comprovante" required>
                                <label class="custom-file-label" for="arquivo_comprovante">Selecione um arquivo...</label>
                            </div>
                            <small class="input-hint">PDF, JPG, PNG (máx. 10MB).</small>
                        </div>

                        <input type="hidden" id="deposito_id_anexo" name="deposito_id_anexo">
                        <input type="hidden" id="funcionario_anexo" name="funcionario_anexo">
                        <input type="hidden" id="data_caixa_anexo" name="data_caixa_anexo">

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-upload" aria-hidden="true"></i> Enviar Comprovante
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Listagem de Depósitos do Caixa Unificado -->
    <div class="modal fade" id="verDepositosCaixaModal" tabindex="-1" role="dialog" aria-labelledby="verDepositosCaixaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-deposito-caixa-unificado modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verDepositosCaixaModalLabel">Depósitos do Caixa Unificado</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table id="tabelaDepositosCaixaUnificado" class="table table-striped table-bordered">
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
                            <tbody id="detalhesDepositosCaixaUnificado"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal de Abertura de Caixa -->
    <div class="modal fade" id="abrirCaixaModal" tabindex="-1" role="dialog" aria-labelledby="abrirCaixaModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content modal-abrir-caixa">
                <div class="modal-header">
                    <h5 class="modal-title" id="abrirCaixaModalLabel"><i class="fa fa-unlock-alt" aria-hidden="true"></i> Abrir Caixa do Dia</h5>
                </div>
                <div class="modal-body">
                    <!-- Caixas anteriores abertos com saldo: impedem a abertura -->
                    <div class="cxf-pendentes" id="abrirCaixaPendentes" style="display:none;">
                        <h6><i class="fa fa-ban" aria-hidden="true"></i> Não é possível abrir um novo caixa</h6>
                        <p class="mb-2" style="font-size:.88rem;">Você tem caixa aberto com saldo. Feche-o e transporte o saldo antes de abrir o caixa de hoje.</p>
                        <ul id="abrirCaixaPendentesLista"></ul>
                    </div>

                    <form id="formAbrirCaixa">
                        <div class="form-group">
                            <label for="saldo_inicial">Saldo Inicial</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">R$</span></div>
                                <input type="text" class="form-control" id="saldo_inicial" name="saldo_inicial" readonly>
                            </div>
                            <small class="form-text text-muted" id="abrirCaixaHint"></small>
                        </div>
                        <button type="submit" id="btnAbrirCaixa" style="width: 100%" class="btn btn-primary">Abrir Caixa</button>
                    </form>

                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-outline-secondary" style="width:100%" onclick="pularAberturaCaixa()">
                            Entrar sem abrir agora
                        </button>
                        <small class="form-text text-muted mt-2">
                            Você poderá abrir o caixa a qualquer momento.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Saldo inicial lançado pelo administrador -->
    <div class="modal fade" id="saldoInicialAdminModal" tabindex="-1" role="dialog" aria-labelledby="saldoInicialAdminLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="saldoInicialAdminLabel"><i class="fa fa-plus-square" aria-hidden="true"></i> Lançar saldo inicial</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" style="font-size:.9rem;">
                        Caixa de <strong id="siaFuncionario"></strong> em <strong id="siaData"></strong>.<br>
                        <span class="text-muted">Disponível apenas para caixas abertos zerados. Somente o administrador pode lançar este valor; a operação fica registrada.</span>
                    </p>
                    <form id="formSaldoInicialAdmin">
                        <input type="hidden" id="sia_caixa_id" name="caixa_id">
                        <div class="form-group">
                            <label class="input-label" for="sia_valor">Saldo inicial</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">R$</span></div>
                                <input type="text" class="form-control" id="sia_valor" name="saldo_inicial" required placeholder="0,00">
                            </div>
                            <small class="input-hint" id="siaAtual"></small>
                        </div>
                        <div class="form-group">
                            <label class="input-label" for="sia_motivo">Motivo / observação</label>
                            <textarea class="form-control" id="sia_motivo" name="motivo" rows="2" maxlength="500" placeholder="Ex.: troco inicial entregue pela gerência"></textarea>
                        </div>
                        <button type="submit" class="btn btn-saldo-admin btn-block"><i class="fa fa-check" aria-hidden="true"></i> Salvar saldo inicial</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script>
        $(document).ready(function() {
            // ===== Período rápido (pílulas) =====
            function isoLocal(d){
                return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
            }
            function intervaloPeriodo(p){
                const hoje = new Date(); hoje.setHours(0,0,0,0);
                const d = (y,m,dd) => new Date(y,m,dd);
                const y = hoje.getFullYear(), m = hoje.getMonth();
                const dow = (hoje.getDay() + 6) % 7; // 0 = segunda
                const seg = new Date(hoje); seg.setDate(hoje.getDate() - dow);
                const dom = new Date(seg); dom.setDate(seg.getDate() + 6);
                switch (p) {
                    case 'hoje':   return [hoje, hoje];
                    case 'ontem':  { const o = new Date(hoje); o.setDate(o.getDate() - 1); return [o, o]; }
                    case 'ultimos7':  { const a = new Date(hoje); a.setDate(a.getDate() - 6); return [a, hoje]; }
                    case 'semanal':   return [seg, dom];
                    case 'semana_passada': { const a = new Date(seg); a.setDate(a.getDate() - 7); const b = new Date(seg); b.setDate(b.getDate() - 1); return [a, b]; }
                    case 'mensal':    return [d(y,m,1), d(y,m+1,0)];
                    case 'mes_passado': return [d(y,m-1,1), d(y,m,0)];
                    case 'ultimos30': { const a = new Date(hoje); a.setDate(a.getDate() - 29); return [a, hoje]; }
                    case 'anual':     return [d(y,0,1), d(y,11,31)];
                    default: return null;
                }
            }
            $('.cxf-pill').on('click', function(){
                const p = $(this).data('periodo');
                $('#periodo').val(p);
                $('.cxf-pill').removeClass('active');
                $(this).addClass('active');
                if (p === 'personalizado') { $('#data_inicial').trigger('focus'); return; }
                const r = intervaloPeriodo(p);
                $('#data_inicial').val(r ? isoLocal(r[0]) : '');
                $('#data_final').val(r ? isoLocal(r[1]) : '');
                $('#pesquisarForm').trigger('submit');
            });
            // Editar uma data muda o período para "Personalizado"
            $('#data_inicial, #data_final').on('input change', function(){
                if ($('#periodo').val() !== 'personalizado') {
                    $('#periodo').val('personalizado');
                    $('.cxf-pill').removeClass('active');
                    $('.cxf-pill[data-periodo="personalizado"]').addClass('active');
                }
            });
            $('#pesquisarForm').on('submit', function(){
                // Não envia campos vazios (URL mais limpa)
                $(this).find('input[type=text], input[type=date]').each(function(){
                    if (!$(this).val()) { $(this).prop('disabled', true); }
                });
                if ($('#situacao').val() === 'todas') { $('#situacao').prop('disabled', true); }
                if ($('#ordenar').val() === 'data_desc') { $('#ordenar').prop('disabled', true); }
            });

            // Voltar pelo navegador não deixa campos desabilitados
            $(window).on('pageshow', function(){ $('#pesquisarForm').find(':disabled').not('#funcionario').prop('disabled', false); });

            // ===== Cartões x tabela (preferência guardada neste navegador) =====
            function aplicarVisao(v){
                const tabela = (v === 'tabela');
                $('#cardsResultados').toggleClass('cxf-hidden', tabela);
                $('#tabelaResultadosWrap').toggleClass('cxf-hidden', !tabela);
                $('.cxf-view .cxf-view-btn').removeClass('active');
                $('.cxf-view .cxf-view-btn[data-view="' + (tabela ? 'tabela' : 'cards') + '"]').addClass('active');
            }
            let visaoSalva = 'cards';
            try { visaoSalva = localStorage.getItem('atlas_caixa_visao') || 'cards'; } catch (e) {}
            aplicarVisao(visaoSalva);
            $('.cxf-view .cxf-view-btn').on('click', function(){
                const v = $(this).data('view');
                aplicarVisao(v);
                try { localStorage.setItem('atlas_caixa_visao', v); } catch (e) {}
            });

            // ===== Busca rápida na lista =====
            $('#cxfBusca').on('input', function(){
                const termo = $(this).val().toLowerCase().trim();
                let visiveis = 0;
                $('#cardsResultados .cxf-item').each(function(){
                    const ok = !termo || String($(this).data('busca')).indexOf(termo) !== -1;
                    $(this).toggleClass('cxf-hidden', !ok);
                    if (ok) visiveis++;
                });
                $('#tabelaResultados tbody .cxf-item').each(function(){
                    const ok = !termo || String($(this).data('busca')).indexOf(termo) !== -1;
                    $(this).toggleClass('cxf-hidden', !ok);
                });
                $('#cxfCount').text(visiveis + ' caixa(s)');
                $('#cxfSemResultadoBusca').toggleClass('cxf-hidden', visiveis > 0 || !(window.CX_LINHAS || []).length);
            });

            // ===== Exportar CSV (abre no Excel) =====
            $('#cxfExportar').on('click', function(){
                const linhas = window.CX_LINHAS || [];
                if (!linhas.length) return;
                const num = v => (parseFloat(v) || 0).toFixed(2).replace('.', ',');
                const esc = v => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
                const cab = ['Funcionário','Nome','Data','Situação','Saldo inicial','Atos liquidados','Recebido em conta','Recebido em espécie','Devoluções','Saídas e despesas','Depósitos','Saldo transportado','Total em caixa','Depósitos sem comprovante'];
                const corpo = linhas.map(l => [
                    esc(l.funcionario), esc(l.nome), esc(formatDateForDisplay(l.data)), esc(l.situacao),
                    num(l.saldo_inicial), num(l.atos), num(l.conta), num(l.especie), num(l.devolucoes),
                    num(l.saidas), num(l.depositos), num(l.transportado), num(l.em_caixa), l.sem_comprovante
                ].join(';'));
                const csv = '\uFEFF' + cab.map(esc).join(';') + '\r\n' + corpo.join('\r\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'fluxo_de_caixa_' + isoLocal(new Date()) + '.csv';
                document.body.appendChild(a); a.click();
                setTimeout(function(){ URL.revokeObjectURL(a.href); a.remove(); }, 500);
            });

            // Máscaras
            $('#valor_saida').mask('#.##0,00', {reverse: true});
            $('#valor_deposito').mask('#.##0,00', {reverse: true});
            $('#saldo_inicial').mask('#.##0,00', {reverse: true});
            $('#sia_valor').mask('#.##0,00', {reverse: true});
            $('.cxf-money').mask('#.##0,00', {reverse: true});

            // Envio de Saída
            $('#formCadastroSaida').on('submit', function(e) {
                e.preventDefault();

                var valorSaida = parseBRMoney($('#valor_saida').val());
                if (valorSaida <= 0) {
                    Swal.fire({ icon: 'warning', title: 'Valor inválido', text: 'Informe o valor da saída.', confirmButtonText: 'OK' });
                    return;
                }
                if (typeof window.__saidaDisponivel === 'number' && Math.round(valorSaida * 100) > Math.round(window.__saidaDisponivel * 100)) {
                    Swal.fire({ icon: 'error', title: 'Saldo insuficiente', html: 'A saída de <b>' + formatCurrency(valorSaida) + '</b> é maior que o disponível em caixa (<b>' + formatCurrency(Math.max(0, window.__saidaDisponivel)) + '</b>).', confirmButtonText: 'OK' });
                    return;
                }

                var formData = new FormData(this);
                $.ajax({
                    url: 'salvar_saida.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Saída cadastrada com sucesso!', confirmButtonText: 'OK' })
                            .then(() => { $('#cadastroSaidaModal').modal('hide'); location.reload(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao cadastrar saída: ' + response.error, confirmButtonText: 'OK' });
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao cadastrar saída: ' + textStatus + ' - ' + errorThrown, confirmButtonText: 'OK' });
                    }
                });
            });

            // Atualiza label do custom-file ao selecionar arquivo (Saída + Depósito)
            $(document).on('change', '.custom-file-input', function () {
                var fileName = $(this).val().split('\\').pop();
                $(this).siblings('.custom-file-label').addClass('selected').text(fileName || 'Selecione um arquivo...');
            });

            // Depósito: comportamento do tipo (ajustado para "Espécie" + "Sem comprovante")
            $('#tipo_deposito').on('change', function() {
                var tipoDeposito = $(this).val();
                if (tipoDeposito === 'Espécie') {
                    $('#sem-comprovante-group').show();
                    // Se o usuário marcou "Sem comprovante", o anexo NÃO é obrigatório
                    var semComprovanteMarcado = $('#sem_comprovante').is(':checked');
                    $('#comprovante_deposito').prop('required', !semComprovanteMarcado);
                } else {
                    $('#sem-comprovante-group').hide();
                    $('#sem_comprovante').prop('checked', false);
                    // Para outros tipos, o anexo é sempre obrigatório
                    $('#comprovante_deposito').prop('required', true);
                }
            });

            // Marcar/Desmarcar "Sem comprovante" atualiza a obrigatoriedade do anexo
            $('#sem_comprovante').on('change', function() {
                var isChecked = $(this).is(':checked');
                var tipoDeposito = $('#tipo_deposito').val();
                if (tipoDeposito === 'Espécie') {
                    $('#comprovante_deposito').prop('required', !isChecked);
                    if (isChecked) {
                        // limpa o input para evitar o "required" em alguns navegadores
                        $('#comprovante_deposito').val('');
                    }
                }
            });

            // Garante estado inicial consistente
            $('#tipo_deposito').trigger('change');

            // Envio Depósito (com validação do total em caixa)
            $('#formCadastroDeposito').on('submit', function(e) {
                e.preventDefault();

                var totalEmCaixa = (typeof window.__cxSaldoAtual === 'number') ? window.__cxSaldoAtual : parseBRMoney($('#total_em_caixa').text());
                var valorDeposito = parseBRMoney($('#valor_deposito').val());

                if (valorDeposito <= 0) {
                    Swal.fire({ icon: 'warning', title: 'Valor inválido', text: 'Informe o valor do depósito.', confirmButtonText: 'OK' });
                    return;
                }
                if (Math.round(valorDeposito * 100) > Math.round(totalEmCaixa * 100)) {
                    Swal.fire({ icon: 'error', title: 'Saldo insuficiente', html: 'O depósito de <b>' + formatCurrency(valorDeposito) + '</b> é maior que o disponível em caixa (<b>' + formatCurrency(Math.max(0, totalEmCaixa)) + '</b>).', confirmButtonText: 'OK' });
                    return;
                }

                Swal.fire({
                    title: 'Você tem certeza?',
                    text: `Deseja realmente inserir o depósito de R$ ${valorDeposito.toFixed(2).replace('.', ',')}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sim, inserir',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        var formData = new FormData($('#formCadastroDeposito')[0]);
                        $.ajax({
                            url: 'salvar_deposito.php',
                            type: 'POST',
                            data: formData,
                            contentType: false,
                            processData: false,
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Depósito cadastrado com sucesso!', confirmButtonText: 'OK' })
                                    .then(() => { $('#cadastroDepositoModal').modal('hide'); location.reload(); });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao cadastrar depósito: ' + response.error, confirmButtonText: 'OK' });
                                }
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao cadastrar depósito: ' + textStatus + ' - ' + errorThrown, confirmButtonText: 'OK' });
                            }
                        });
                    }
                });
            });

            // Carregar Saídas no modal
            $('#cadastroSaidaModal').on('shown.bs.modal', function () {
                var funcionario = $('#funcionario_saida').val();
                var data_caixa = $('#data_caixa_saida').val();

                carregarSaldoCaixa(funcionario, data_caixa, function(saldo){
                    window.__saidaDisponivel = parseFloat(saldo.disponivel) || 0;
                    $('#saidaDisponivel').text(formatCurrency(window.__saidaDisponivel));
                    var semSaldo = Math.round(window.__saidaDisponivel * 100) <= 0;
                    var fechado = saldo.caixa_status === 'fechado';
                    $('#saidaDisponivelBox').toggleClass('zero', semSaldo || fechado);
                    $('#btnAdicionarSaida').prop('disabled', semSaldo || fechado)
                        .html(fechado ? '<i class="fa fa-lock"></i> Caixa fechado'
                              : (semSaldo ? '<i class="fa fa-ban"></i> Sem saldo em caixa' : '<i class="fa fa-plus-circle" aria-hidden="true"></i> Adicionar'));
                });

                $.ajax({
                    url: 'listar_saidas.php',
                    type: 'GET',
                    data: { funcionario: funcionario, data_caixa: data_caixa },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) { alert('Erro: ' + response.error); return; }

                        var saidas = response.saidas;
                        $('#detalhesSaidasCadastradas').empty();
                        saidas.forEach(function(saida) {
                            var anexo = saida.caminho_anexo ? `<button title="Visualizar" class="btn btn-info btn-sm btn-icon" onclick="visualizarAnexoSaida('${saida.caminho_anexo}', '${saida.funcionario}', '${saida.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>` : '';
                            const podeExcluirSaida = (saida.pode_excluir === true || saida.pode_excluir === 1 || saida.pode_excluir === '1');
                            const deleteBtnSaida = podeExcluirSaida
                                ? `<button title="Remover" class="btn btn-delete btn-sm btn-icon" onclick="removerSaida(${saida.id})"><i class="fa fa-trash" aria-hidden="true"></i></button>`
                                : '';

                            $('#detalhesSaidasCadastradas').append(`
                                <tr>
                                    <td>${saida.funcionario}</td>
                                    <td>${saida.titulo}</td>
                                    <td>${formatCurrency(saida.valor_saida)}</td>
                                    <td>${saida.forma_de_saida}</td>
                                    <td>${anexo}</td>
                                    <td>${deleteBtnSaida}</td>
                                </tr>
                            `);
                        });

                        $('#tabelaSaidasCadastradas').DataTable({
                            "language": { "url": "../style/Portuguese-Brasil.json" },
                            "destroy": true,
                            "pageLength": 10,
                            "order": [],
                        });
                    },
                    error: function() { alert('Erro ao obter saídas.'); }
                });
            });

            // Abrir Caixa ao carregar (se necessário)
            abrirCaixaModal();

            // Validação de data (filtros)
            var currentYear = new Date().getFullYear();
            function validateDate(input) {
                var selectedDate = new Date($(input).val());
                if (selectedDate.getFullYear() > currentYear) {
                    Swal.fire({ icon: 'warning', title: 'Data inválida', text: 'O ano não pode ser maior que o ano atual.', confirmButtonText: 'Ok' });
                    $(input).val('');
                }
            }
            $('#data_inicial, #data_final').on('change', function() {
                if ($(this).val()) { validateDate(this); }
            });
        });

        function abrirCaixaModal() {
            if (window.__skipAbrirCaixa === true) return;
            try { if (new URLSearchParams(window.location.search).get('pular_abertura') === '1') return; } catch (e) {}

            $.ajax({
                url: 'verificar_caixa_aberto.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.error) { console.error(response.error); return; }
                    if (response.aberto) return;

                    var transportado = parseFloat(response.saldo_transportado) || 0;
                    var isAdmin = !!response.is_admin;

                    // Caixas anteriores abertos com saldo
                    var $lista = $('#abrirCaixaPendentesLista').empty();
                    (response.pendentes || []).forEach(function(p){
                        var url = 'index.php?funcionario=' + encodeURIComponent(response.funcionario || '')
                                + '&periodo=personalizado&data_inicial=' + encodeURIComponent(p.data) + '&data_final=' + encodeURIComponent(p.data) + '&pular_abertura=1';
                        $lista.append(
                            '<li><span><i class="fa fa-calendar"></i> ' + escaparHtmlCx(p.data_br) + ' &middot; <b>' + escaparHtmlCx(p.disponivel_br) + '</b></span>' +
                            '<a class="btn btn-sm btn-danger" href="' + url + '" onclick="window.__skipAbrirCaixa=true;"><i class="fa fa-lock"></i> Ir para o caixa</a></li>'
                        );
                    });
                    $('#abrirCaixaPendentes').toggle(!!response.bloqueado);
                    $('#btnAbrirCaixa').prop('disabled', !!response.bloqueado);
                    $('#formAbrirCaixa .form-group').toggle(!response.bloqueado);

                    // Saldo inicial: transporte, ou digitado apenas pelo administrador
                    var $saldo = $('#saldo_inicial');
                    $saldo.val(transportado.toFixed(2).replace('.', ','));
                    $saldo.closest('.input-group').show();
                    if (transportado > 0) {
                        $saldo.prop('readonly', true);
                        $('#abrirCaixaHint').html('<i class="fa fa-share"></i> Saldo transportado do caixa anterior.');
                    } else if (isAdmin) {
                        $saldo.prop('readonly', false);
                        $('#abrirCaixaHint').html('<i class="fa fa-user-secret"></i> Como administrador, você pode informar o saldo inicial.');
                    } else {
                        $saldo.prop('readonly', true).closest('.input-group').hide();
                        $('#abrirCaixaHint').html('<i class="fa fa-info-circle"></i> Não há saldo transportado: o caixa será aberto <b>zerado</b>. O saldo inicial (troco) é lançado pelo administrador, que verá este caixa como <b>Aberto zerado</b>.');
                    }
                    window.__abrirCaixaAdminLivre = (isAdmin && transportado <= 0);

                    $('#abrirCaixaModal').modal('show');
                },
                error: function() { console.error('Erro ao verificar caixa.'); }
            });
        }

        function pularAberturaCaixa(){
            window.__skipAbrirCaixa = true;
            $('#abrirCaixaModal').modal('hide');
        }

        function verDetalhes(funcionarios, data, tipo) {
            $.ajax({
                url: 'detalhes_fluxo_caixa.php',
                type: 'GET',
                data: { funcionarios: funcionarios, data: data, tipo: tipo },
                success: function(response) {
                    if (response.error) { alert('Erro: ' + response.error); return; }

                    var detalhes = response;

                    var dataFormatada = formatDateForDisplay(data);
                    $('#detalhesModalLabel').html(`CAIXA DO DIA ${dataFormatada} - FUNCIONÁRIO: ${funcionarios}`);

                    // Status (com cadeado) no cabeçalho do modal
                    var zerado = Math.round((parseFloat(detalhes.totalEmCaixa) || 0) * 100) === 0;
                    var fechado = detalhes.caixaStatus === 'fechado'
                        || (zerado && !(detalhes.caixaStatus === 'aberto' && detalhes.ultimoCaixa));
                    var statusText = fechado ? 'Fechado' : 'Aberto';
                    var statusClass = fechado ? 'badge-closed' : 'badge-open';
                    var icon = fechado ? 'fa-lock' : 'fa-unlock-alt';
                    $('#modalStatusPill').html(`<span class="badge-status ${statusClass}"><i class="fa ${icon}"></i> ${statusText}</span>`);

                    // NOVO: guardar tipo atual (individual | unificado)
                    window.currentTipoCaixa = (tipo || 'individual');

                    // Cards topo
                    toggleCard('#cardTotalAtos', detalhes.totalAtos);
                    toggleCard('#cardTotalAtosManuais', detalhes.totalAtosManuais);
                    toggleCard('#cardTotalRecebidoConta', detalhes.totalRecebidoConta);
                    toggleCard('#cardTotalRecebidoEspecie', detalhes.totalRecebidoEspecie);
                    toggleCard('#cardTotalDevolucoes', detalhes.totalDevolucoes);
                    toggleCard('#cardTotalEmCaixa', detalhes.totalEmCaixa);
                    toggleCard('#cardSaidasDespesas', detalhes.totalSaidasDespesas);
                    toggleCard('#cardDepositoCaixa', detalhes.totalDepositoCaixa);

                    // NOVO: Card Total em Selos (à vista + diferidos)
                    toggleCard('#cardTotalSelos', detalhes.totalSelos);

                    // NOVO: Card Selos Cancelados (só aparece quando houver)
                    toggleCard('#cardSelosCancelados', detalhes.totalSelosCancelados);

                    // NOVO: Card Repasse a Credores (só aparece quando houver)
                    toggleCard('#cardRepasseCredor', detalhes.totalRepasseCredor);

                    toggleCard('#cardSaldoTransportado', detalhes.totalSaldoTransportado);
                    toggleCard('#cardSaldoInicial', detalhes.saldoInicial);

                    // Administrador: lançar/ajustar o saldo inicial direto do detalhe do caixa
                    if (window.CX_IS_ADMIN && detalhes.podeLancarSaldo && detalhes.caixaId) {
                        var si = parseFloat(detalhes.saldoInicial) || 0;
                        $('#detalhesSaldoAdminTexto').text(si > 0
                            ? 'Saldo inicial lançado pelo administrador: ' + formatCurrency(si) + '. Você pode ajustá-lo enquanto o caixa estiver aberto.'
                            : 'Caixa aberto zerado: o saldo inicial ainda não foi lançado.');
                        $('#detalhesSaldoAdminBtn').html('<i class="fa fa-plus-square" aria-hidden="true"></i> ' + (si > 0 ? 'Ajustar saldo inicial' : 'Lançar saldo inicial'))
                            .off('click').on('click', function(){ abrirSaldoInicialAdmin(detalhes.caixaId, funcionarios, data, si); });
                        $('#detalhesSaldoAdmin').css('display', 'flex');
                    } else {
                        $('#detalhesSaldoAdmin').hide();
                    }


                    // Atos Liquidados
                    var totalAtos = 0;
                    $('#detalhesAtos').empty();
                    detalhes.atos.forEach(function(ato) {
                        totalAtos += parseFloat(ato.total);
                        $('#detalhesAtos').append(`
                            <tr>
                                <td>${ato.funcionario}</td>    
                                <td>${ato.ordem_servico_id}</td>
                                <td>${ato.cliente}</td>
                                <td>${ato.ato}</td>
                                <td>${ato.descricao}</td>
                                <td>${ato.quantidade_liquidada}</td>
                                <td>${formatCurrency(ato.total)}</td>
                            </tr>
                        `);
                    });
                    $('#totalAtos').text(formatCurrency(totalAtos));

                    // NOVO: prepara filtros da seção ATOS LIQUIDADOS
                    setupAtosFilterUI(detalhes, window.currentTipoCaixa);

                    // Atos Manuais
                    // Atos Manuais -> separar NÃO ISENTOS e ISENTOS (ato contém "(isento)")
                    const isIsento = (s) => String(s || '').toLowerCase().includes('(isento)');

                    // 2.1) NÃO ISENTOS (preenche a tabela "ATOS MANUAIS")
                    const atosManuaisNaoIsentos = (detalhes.atosManuais || []).filter(a => !isIsento(a.ato));
                    var totalAtosManuais = 0;
                    $('#detalhesAtosManuais').empty();
                    atosManuaisNaoIsentos.forEach(function(atoManual) {
                        totalAtosManuais += parseFloat(atoManual.total || 0);
                        $('#detalhesAtosManuais').append(`
                            <tr>
                                <td>${atoManual.funcionario}</td>    
                                <td>${atoManual.ordem_servico_id}</td>
                                <td>${atoManual.cliente}</td>
                                <td>${atoManual.ato}</td>
                                <td>${atoManual.descricao}</td>
                                <td>${atoManual.quantidade_liquidada}</td>
                                <td>${formatCurrency(atoManual.total)}</td>
                            </tr>
                        `);
                    });
                    $('#totalAtosManuais').text(formatCurrency(totalAtosManuais));
                    // Filtros devem considerar APENAS os não isentos
                    setupManuaisFilterUI({ atosManuais: atosManuaisNaoIsentos }, window.currentTipoCaixa);

                    // 2.2) ISENTOS (preenche o novo bloco "ATOS ISENTOS")
                    const atosIsentos = (detalhes.atosManuais || []).filter(a => isIsento(a.ato));
                    var totalAtosIsentos = 0;
                    $('#detalhesAtosIsentos').empty();
                    atosIsentos.forEach(function(atoManual) {
                        totalAtosIsentos += parseFloat(atoManual.total || 0);
                        $('#detalhesAtosIsentos').append(`
                            <tr>
                                <td>${atoManual.funcionario}</td>    
                                <td>${atoManual.ordem_servico_id}</td>
                                <td>${atoManual.cliente}</td>
                                <td>${atoManual.ato}</td>
                                <td>${atoManual.descricao}</td>
                                <td>${atoManual.quantidade_liquidada}</td>
                                <td>${formatCurrency(atoManual.total)}</td>
                            </tr>
                        `);
                    });
                    $('#totalAtosIsentos').text(formatCurrency(totalAtosIsentos));
                    $('#qtdAtosIsentos').text(
                        atosIsentos.reduce((acc, it) => acc + (parseFloat(it.quantidade_liquidada || 0) || 0), 0)
                    );

                    // Pagamentos
                    var totalPagamentos = 0;
                    var totalPorTipo = {};
                    $('#detalhesPagamentos').empty();
                    detalhes.pagamentos.forEach(function(pagamento) {
                        totalPagamentos += parseFloat(pagamento.total_pagamento);
                        if (!totalPorTipo[pagamento.forma_de_pagamento]) totalPorTipo[pagamento.forma_de_pagamento] = 0;
                        totalPorTipo[pagamento.forma_de_pagamento] += parseFloat(pagamento.total_pagamento);
                        $('#detalhesPagamentos').append(`
                            <tr>
                                <td>${pagamento.funcionario}</td>    
                                <td>${pagamento.ordem_de_servico_id}</td>
                                <td>${pagamento.cliente}</td>
                                <td>${pagamento.forma_de_pagamento}</td>
                                <td>${formatCurrency(pagamento.total_pagamento)}</td>
                                <td class="cx-obs-cell">${formatObservacaoPagamento(pagamento.observacao)}</td>
                                <td>${((parseInt(pagamento.anexos_count,10) > 0 || parseInt(pagamento.os_anexos_count,10) > 0)) ? `<button title="Visualizar comprovante" class="btn btn-info btn-sm btn-icon" onclick="verComprovantePagamento(${pagamento.pagamento_id || 0}, ${pagamento.ordem_de_servico_id || 0})"><i class="fa fa-paperclip" aria-hidden="true"></i></button>` : ''}</td>
                            </tr>
                        `);
                    });
                    $('#totalPagamentos').text(formatCurrency(totalPagamentos));

                    // Total por Tipo
                    var totalRecebidoConta = 0;
                    var totalRecebidoEspecie = 0;
                    $('#detalhesTotalPorTipo').empty();
                    for (var tipo in totalPorTipo) {
                        $('#detalhesTotalPorTipo').append(`
                            <tr>
                                <td>${tipo}</td>
                                <td>${formatCurrency(totalPorTipo[tipo])}</td>
                            </tr>
                        `);
                        if (['PIX', 'Centrais Eletrônicas', 'Boleto', 'Transferência Bancária', 'Depósito Bancário', 'Crédito', 'Débito'].includes(tipo)) {
                            totalRecebidoConta += totalPorTipo[tipo];
                        } else if (tipo === 'Espécie') {
                            totalRecebidoEspecie += totalPorTipo[tipo];
                        }
                    }
                    $('#cardTotalRecebidoConta').text(formatCurrency(totalRecebidoConta));
                    $('#cardTotalRecebidoEspecie').text(formatCurrency(totalRecebidoEspecie));
                    $('#cardTotalRecebido').text(formatCurrency(totalRecebidoConta + totalRecebidoEspecie));

                    // >>> NOVO: Card "Atos Isentos" vem do total por tipo de pagamento "Ato Isento"
                    var totalAtoIsento = totalPorTipo['Ato Isento'] || 0;
                    toggleCard('#cardAtosIsentos', totalAtoIsento);

                    // Devoluções
                    var totalDevolucoes = 0;
                    $('#detalhesDevolucoes').empty();
                    detalhes.devolucoes.forEach(function(devolucao) {
                        totalDevolucoes += parseFloat(devolucao.total_devolucao);
                        $('#detalhesDevolucoes').append(`
                            <tr>
                                <td>${devolucao.funcionario}</td>    
                                <td>${devolucao.ordem_de_servico_id}</td>
                                <td>${devolucao.cliente}</td>
                                <td>${devolucao.forma_devolucao}</td>
                                <td>${formatCurrency(devolucao.total_devolucao)}</td>
                            </tr>
                        `);
                    });
                    $('#totalDevolucoes').text(formatCurrency(totalDevolucoes));

                    // Saídas
                    var totalSaidas = 0;
                    $('#detalhesSaidas').empty();
                    detalhes.saidas.forEach(function(saida) {
                        totalSaidas += parseFloat(saida.valor_saida);
                        var dataCaixaFormatada = formatDateForDisplay(saida.data_caixa);
                        var dataCadastroFormatada = formatDateForDisplay(saida.data);
                        $('#detalhesSaidas').append(`
                            <tr>
                                <td>${saida.funcionario}</td>    
                                <td>${saida.titulo}</td>
                                <td>${formatCurrency(saida.valor_saida)}</td>
                                <td>${saida.forma_de_saida}</td>
                                <td>${dataCaixaFormatada}</td>
                                <td>${dataCadastroFormatada}</td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm btn-icon" onclick="visualizarAnexoSaida('${saida.caminho_anexo}', '${saida.funcionario}', '${saida.data_caixa}')">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                    $('#totalSaidas').text(formatCurrency(totalSaidas));

                    // Depósitos
                    var totalDepositos = 0;
                    $('#detalhesDepositos').empty();
                    detalhes.depositos.forEach(function(deposito) {
                        totalDepositos += parseFloat(deposito.valor_do_deposito);
                        $('#detalhesDepositos').append(`
                            <tr>
                                <td>${deposito.funcionario}</td>
                                <td>${formatDateForDisplay(deposito.data_caixa)}</td>
                                <td>${formatDateForDisplay2(deposito.data_cadastro)}</td>
                                <td>${formatCurrency(deposito.valor_do_deposito)}</td>
                                <td>${deposito.tipo_deposito}</td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm btn-icon" onclick="visualizarComprovante('${deposito.caminho_anexo}', '${deposito.funcionario}', '${deposito.data_caixa}')">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                    $('#totalDepositos').text(formatCurrency(totalDepositos));

                    /* NOVO: SELOS */
                    var totalSelos = 0;
                    $('#detalhesSelos').empty();
                    (detalhes.selos || []).forEach(function(s) {
                        totalSelos += parseFloat(s.total || 0);
                        $('#detalhesSelos').append(`
                            <tr>
                                <td>${s.funcionario}</td>
                                <td>${s.numero_selo}</td>
                                <td>${s.ato || ''}</td>
                                <td>${s.tipo || ''}</td>
                                <td>${s.selagem ? formatDateForDisplay(s.selagem) : ''}</td>
                                <td>${formatCurrency(s.emolumentos)}</td>
                                <td>${formatCurrency(s.ferj)}</td>
                                <td>${formatCurrency(s.fadep)}</td>
                                <td>${formatCurrency(s.ferc)}</td>
                                <td>${formatCurrency(s.femp)}</td>
                                <td>${formatCurrency(s.ferrfis)}</td>
                                <td>${formatCurrency(s.total)}</td>
                            </tr>
                        `);
                    });
                    $('#totalSelos').text(formatCurrency(totalSelos));

                    /* NOVO: SELOS CANCELADOS — lista própria; o valor é DEDUZIDO do Total em Selos */
                    var totalSelosCancelados = 0;
                    $('#detalhesSelosCancelados').empty();
                    var selosCancelados = detalhes.selosCancelados || [];
                    selosCancelados.forEach(function(s) {
                        totalSelosCancelados += Math.abs(parseFloat(s.total || 0));
                        $('#detalhesSelosCancelados').append(`
                            <tr>
                                <td>${s.funcionario}</td>
                                <td>${s.numero_selo}</td>
                                <td>${s.ato || ''}</td>
                                <td>${s.tipo || ''}</td>
                                <td>${s.selagem ? formatDateForDisplay(s.selagem) : ''}</td>
                                <td>${formatCurrency(s.emolumentos)}</td>
                                <td>${formatCurrency(s.ferj)}</td>
                                <td>${formatCurrency(s.fadep)}</td>
                                <td>${formatCurrency(s.ferc)}</td>
                                <td>${formatCurrency(s.femp)}</td>
                                <td>${formatCurrency(s.ferrfis)}</td>
                                <td>${formatCurrency(s.total)}</td>
                            </tr>
                        `);
                    });
                    $('#totalSelosCancelados').text(formatCurrency(totalSelosCancelados));
                    $('#listaSelosCanceladosWrap').toggle(selosCancelados.length > 0);

                    // mantém os cards em sincronia (caso a tabela seja paginada/filtrada no futuro)
                    $('#cardTotalSelos').text(formatCurrency(totalSelos - totalSelosCancelados));
                    $('#cardSelosCancelados').text(formatCurrency(totalSelosCancelados));

                    // NOVO: Repasse a Credores (Protesto) — lista só aparece quando houver
                    var totalRepasseCredor = 0;
                    $('#detalhesRepasseCredor').empty();
                    var repasseLista = detalhes.repasseCredor || [];
                    repasseLista.forEach(function(rc) {
                        totalRepasseCredor += parseFloat(rc.total_repasse || 0);
                        $('#detalhesRepasseCredor').append(`
                            <tr>
                                <td>${rc.funcionario || ''}</td>
                                <td>${rc.ordem_de_servico_id}</td>
                                <td>${rc.cliente || ''}</td>
                                <td>${rc.data_repasse ? formatDateForDisplay(String(rc.data_repasse).split(' ')[0]) : ''}</td>
                                <td>${rc.forma_repasse || ''}</td>
                                <td>${formatCurrency(rc.total_repasse)}</td>
                            </tr>
                        `);
                    });
                    $('#totalRepasseCredor').text(formatCurrency(totalRepasseCredor));
                    $('#cardRepasseCredor').text(formatCurrency(totalRepasseCredor));
                    $('#listaRepasseCredorWrap').toggle(repasseLista.length > 0);

                    // Saldo Transportado
                    var totalSaldoTransportado = 0;
                    $('#detalhesSaldoTransportado').empty();
                    detalhes.saldoTransportado.forEach(function(transporte) {
                        totalSaldoTransportado += parseFloat(transporte.valor_transportado);
                        $('#detalhesSaldoTransportado').append(`
                            <tr>
                                <td>${formatDateForDisplay(transporte.data_caixa)}</td>
                                <td>${formatDateForDisplay(transporte.data_transporte)}</td>
                                <td>${formatCurrency(transporte.valor_transportado)}</td>
                                <td>${transporte.funcionario}</td>
                                <td>${transporte.status}</td>
                            </tr>
                        `);
                    });
                    $('#totalSaldoTransportado').text(formatCurrency(totalSaldoTransportado));

                    function toggleCard(selector, value) {
                        const cardElement = $(selector).closest('.col-md-3');
                        if (parseFloat(value) > 0) {
                            $(selector).text(formatCurrency(value));
                            cardElement.show();
                        } else {
                            cardElement.hide();
                        }
                    }

                    $('#detalhesModal').modal('show');

                    // DataTables
                    // NOVO: manter referências globais para recálculo dinâmico
                    if (window.dtAtos) window.dtAtos.destroy();
                    window.dtAtos = $('#tabelaAtos').DataTable({
                        language: { url: "../style/Portuguese-Brasil.json" },
                        destroy: true,
                        pageLength: 10,
                        order: []
                    }).on('draw', function(){ recalcAtosTotals(); });

                    if (window.dtAtosManuais) window.dtAtosManuais.destroy();
                    window.dtAtosManuais = $('#tabelaAtosManuais').DataTable({
                        language: { url: "../style/Portuguese-Brasil.json" },
                        destroy: true,
                        pageLength: 10,
                        order: []
                    }).on('draw', function(){ recalcManuaisTotals(); });

                    // NOVO: DataTable para ATOS ISENTOS
                    if (window.dtAtosIsentos) window.dtAtosIsentos.destroy();
                    window.dtAtosIsentos = $('#tabelaAtosIsentos').DataTable({
                        language: { url: "../style/Portuguese-Brasil.json" },
                        destroy: true,
                        pageLength: 10,
                        order: []
                    }).on('draw', function(){ recalcIsentosTotals(); });

                    $('#tabelaPagamentos').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });
                    $('#tabelaTotalPorTipo').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });
                    $('#tabelaDevolucoes').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });
                    $('#tabelaSaidas').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });
                    $('#tabelaDepositos').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });

                    // NOVO: tabela de Selos
                    $('#tabelaSelos').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });

                    // NOVO: tabela de Selos Cancelados
                    $('#tabelaSelosCancelados').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });

                    // NOVO: tabela de Repasse a Credores
                    $('#tabelaRepasseCredor').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });

                    $('#tabelaSaldoTransportado').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });
                },
                error: function() { alert('Erro ao obter detalhes.'); }
            });
        }

        // Escapa regex para buscas exatas nas colunas
        function escapeRegex(s){ return String(s || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

        function parseBRMoney(str){
            if (str == null) return 0;
            const s = String(str).replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');
            const v = parseFloat(s);
            return isNaN(v) ? 0 : v;
        }

        // Monta lista única e ordenada a partir de um campo
        function distinctFrom(list, key){
            const set = new Set();
            (list || []).forEach(it=>{
                const v = (it && it[key] != null) ? String(it[key]).trim() : '';
                if (v) set.add(v);
            });
            return Array.from(set).sort((a,b)=> a.localeCompare(b,'pt-BR',{numeric:true, sensitivity:'base'}));
        }

        // Preenche um <select> com placeholder + valores
        function fillSelect(selectId, values, placeholder){
            const $sel = $(selectId);
            $sel.empty().append(`<option value="">${placeholder}</option>`);
            values.forEach(v => $sel.append(`<option value="${$('<div>').text(v).html()}">${v}</option>`));
        }

        // ---------- ATOS LIQUIDADOS ----------
        function setupAtosFilterUI(detalhes, tipo){
            const isUni = (tipo === 'unificado');
            // Mostra/oculta filtro por funcionário
            $('#filtroAtosFuncCol').toggle(isUni);

            // Popula selects com base nos dados recebidos do back-end
            fillSelect('#filtroAtosAto',          distinctFrom(detalhes.atos, 'ato'),                 'Todos os Atos');
            fillSelect('#filtroAtosApresentante', distinctFrom(detalhes.atos, 'cliente'),             'Todos os Apresentantes');
            fillSelect('#filtroAtosOS',           distinctFrom(detalhes.atos, 'ordem_servico_id'),    'Todas as O.S');
            if (isUni){
                fillSelect('#filtroAtosFuncionario', distinctFrom(detalhes.atos, 'funcionario'),      'Todos os Funcionários');
            } else {
                $('#filtroAtosFuncionario').empty().append('<option value=""></option>');
            }

            // Eventos
            $('#filtroAtosAto, #filtroAtosApresentante, #filtroAtosOS, #filtroAtosFuncionario')
                .off('change').on('change', applyAtosFilters);

            $('#btnLimparFiltrosAtos').off('click').on('click', function(){
                $('#filtrosAtosLiquidados select').val('');
                applyAtosFilters();
            });

            // Inicializa totais coerentes com a tabela carregada
            recalcAtosTotals();
        }

        function applyAtosFilters(){
            if (!window.dtAtos) return;
            const func  = $('#filtroAtosFuncionario').val() || '';
            const ato   = $('#filtroAtosAto').val() || '';
            const apr   = $('#filtroAtosApresentante').val() || '';
            const os    = $('#filtroAtosOS').val() || '';

            // Mapeamento de colunas: 0=Funcionário, 1=OS, 2=Apresentante, 3=Ato
            window.dtAtos.column(0).search(func ? '^' + escapeRegex(func) + '$' : '', true, false);
            window.dtAtos.column(1).search(os   ? '^' + escapeRegex(os)   + '$' : '', true, false);
            window.dtAtos.column(2).search(apr  ? escapeRegex(apr) : '', true, false);  // contém
            window.dtAtos.column(3).search(ato  ? '^' + escapeRegex(ato)  + '$' : '', true, false);

            window.dtAtos.draw();
            recalcAtosTotals();
        }

        function recalcAtosTotals(){
            if (!window.dtAtos) return;
            let soma = 0, qtd = 0;
            window.dtAtos.rows({search:'applied'}).every(function(){
                const row = this.data(); // array de células
                // col[5]=Quantidade, col[6]=Total
                qtd += parseFloat(String(row[5]).replace(/[^\d,.-]/g,'').replace('.','').replace(',','.')) || 0;
                soma += parseBRMoney(row[6]);
            });
            $('#qtdAtos').text(qtd);
            $('#totalAtos').text(formatCurrency(soma));
            // Atualiza card topo
            $('#cardTotalAtos').text(formatCurrency(soma));
        }

        // ---------- ATOS MANUAIS ----------
        function setupManuaisFilterUI(detalhes, tipo){
            const isUni = (tipo === 'unificado');
            $('#filtroManuaisFuncCol').toggle(isUni);

            fillSelect('#filtroManuaisAto',          distinctFrom(detalhes.atosManuais, 'ato'),                 'Todos os Atos');
            fillSelect('#filtroManuaisApresentante', distinctFrom(detalhes.atosManuais, 'cliente'),             'Todos os Apresentantes');
            fillSelect('#filtroManuaisOS',           distinctFrom(detalhes.atosManuais, 'ordem_servico_id'),    'Todas as O.S');
            if (isUni){
                fillSelect('#filtroManuaisFuncionario', distinctFrom(detalhes.atosManuais, 'funcionario'),      'Todos os Funcionários');
            } else {
                $('#filtroManuaisFuncionario').empty().append('<option value=""></option>');
            }

            $('#filtroManuaisAto, #filtroManuaisApresentante, #filtroManuaisOS, #filtroManuaisFuncionario')
                .off('change').on('change', applyManuaisFilters);

            $('#btnLimparFiltrosManuais').off('click').on('click', function(){
                $('#filtrosAtosManuais select').val('');
                applyManuaisFilters();
            });

            recalcManuaisTotals();
        }

        function applyManuaisFilters(){
            if (!window.dtAtosManuais) return;
            const func  = $('#filtroManuaisFuncionario').val() || '';
            const ato   = $('#filtroManuaisAto').val() || '';
            const apr   = $('#filtroManuaisApresentante').val() || '';
            const os    = $('#filtroManuaisOS').val() || '';

            // Mapeamento igual: 0=Funcionário, 1=OS, 2=Apresentante, 3=Ato
            window.dtAtosManuais.column(0).search(func ? '^' + escapeRegex(func) + '$' : '', true, false);
            window.dtAtosManuais.column(1).search(os   ? '^' + escapeRegex(os)   + '$' : '', true, false);
            window.dtAtosManuais.column(2).search(apr  ? escapeRegex(apr) : '', true, false);
            window.dtAtosManuais.column(3).search(ato  ? '^' + escapeRegex(ato)  + '$' : '', true, false);

            window.dtAtosManuais.draw();
            recalcManuaisTotals();
        }

        function recalcManuaisTotals(){
            if (!window.dtAtosManuais) return;
            let soma = 0, qtd = 0;
            window.dtAtosManuais.rows({search:'applied'}).every(function(){
                const row = this.data();
                qtd += parseFloat(String(row[5]).replace(/[^\d,.-]/g,'').replace('.','').replace(',','.')) || 0;
                soma += parseBRMoney(row[6]);
            });
            $('#qtdAtosManuais').text(qtd);
            $('#totalAtosManuais').text(formatCurrency(soma));
            // Atualiza card topo
            $('#cardTotalAtosManuais').text(formatCurrency(soma));
        }

        // NOVO: totalização dinâmica para a tabela de ATOS ISENTOS
        function recalcIsentosTotals(){
            if (!window.dtAtosIsentos) return;
            let soma = 0, qtd = 0;
            window.dtAtosIsentos.rows({search:'applied'}).every(function(){
                const row = this.data(); // col[5]=Quantidade, col[6]=Total
                qtd  += parseFloat(String(row[5]).replace(/[^\d,.-]/g,'').replace('.','').replace(',','.')) || 0;
                soma += parseBRMoney(row[6]);
            });
            $('#qtdAtosIsentos').text(qtd);
            $('#totalAtosIsentos').text(formatCurrency(soma));
        }

        // ============ /FIM NOVO BLOCO ============ 


        function cadastrarSaida(funcionarios, data) {
            $('#data_saida').val(data);
            $('#data_caixa_saida').val(data);
            $('#funcionario_saida').val(funcionarios);
            $('#cadastroSaidaModal').modal('show');
        }

        function cadastrarDeposito(funcionarios, data) {
            $('#data_caixa_deposito').val(data);
            $('#funcionario_deposito').val(funcionarios);
            carregarDepositos(funcionarios, data);
            $('#cadastroDepositoModal').modal('show');
        }

        // ADAPTADO: aceitar callback opcional após carregar depósitos (usado no fechamento rápido)
        function carregarDepositos(funcionarios, data, callback) {
            $.ajax({
                url: 'listar_depositos.php',
                type: 'GET',
                data: { funcionarios: funcionarios, data: data },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        Swal.fire({ icon: 'error', title: 'Erro!', text: response.error, confirmButtonText: 'OK' });
                        return;
                    }
                    if (!Array.isArray(response.depositos)) {
                        Swal.fire({ icon: 'error', title: 'Erro!', text: 'Dados de depósitos inválidos', confirmButtonText: 'OK' });
                        return;
                    }

                    var depositos = response.depositos;
                    $('#detalhesDepositosRegistrados').empty();
                    depositos.forEach(function(deposito) {
                        var dataCadastroFormatada = formatDateForDisplay2(deposito.data_cadastro);
                        var dataCaixaFormatada = formatDateForDisplay(deposito.data_caixa);
                        const podeExcluirDeposito = (deposito.pode_excluir === true || deposito.pode_excluir === 1 || deposito.pode_excluir === '1');
                        const deleteBtnDeposito = podeExcluirDeposito
                            ? `<button title="Remover" style="margin-bottom: 5px !important;" class="btn btn-delete btn-sm btn-icon" data-id="${deposito.id ? deposito.id : 'undefined'}" onclick="removerDeposito(this)"><i class="fa fa-trash" aria-hidden="true"></i></button>`
                            : '';

                        const temAnexo = deposito.caminho_anexo && String(deposito.caminho_anexo).trim() !== '';
                        const visualizarBtn = temAnexo
                            ? `<button title="Visualizar" class="btn btn-info btn-sm btn-icon" onclick="visualizarComprovante('${deposito.caminho_anexo}', '${deposito.funcionario}', '${deposito.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>`
                            : '';

                        const anexarBtn = !temAnexo
                            ? `<button title="Anexar Comprovante" class="btn btn-primary btn-sm btn-icon" onclick="abrirModalAnexarComprovante(${deposito.id}, '${deposito.funcionario}', '${deposito.data_caixa}')"><i class="fa fa-paperclip" aria-hidden="true"></i></button>`
                            : '';

                        $('#detalhesDepositosRegistrados').append(`
                            <tr>
                                <td>${deposito.funcionario}</td>
                                <td>${dataCaixaFormatada}</td>
                                <td>${dataCadastroFormatada}</td>
                                <td>${formatCurrency(deposito.valor_do_deposito)}</td>
                                <td>${deposito.tipo_deposito}</td>
                                <td>
                                    ${visualizarBtn}
                                    ${anexarBtn}
                                    ${deleteBtnDeposito}
                                </td>
                            </tr>
                        `);
                    });

                    var saldoInicial = parseFloat(response.saldoInicial) || 0;
                    var totalRecebidoEspecie = parseFloat(response.totalRecebidoEspecie) || 0;
                    var totalDevolvidoEspecie = parseFloat(response.totalDevolvidoEspecie) || 0;
                    var totalSaidasDespesas = parseFloat(response.totalSaidasDespesas) || 0;
                    var totalDepositoCaixa = parseFloat(response.totalDepositoCaixa) || 0;
                    var totalSaldoTransportado = parseFloat(response.totalSaldoTransportado) || 0;

                    // Saldo calculado no servidor (mesma regra das validações)
                    var totalEmCaixa = (response.saldoDisponivel !== undefined && response.saldoDisponivel !== null)
                        ? (parseFloat(response.saldoDisponivel) || 0)
                        : (saldoInicial + totalRecebidoEspecie - totalDevolvidoEspecie - totalSaidasDespesas - totalDepositoCaixa - totalSaldoTransportado);
                    totalEmCaixa = Math.round(totalEmCaixa * 100) / 100;
                    window.__cxSaldoAtual = totalEmCaixa;
                    window.__cxCaixaStatus = response.caixaStatus || null;

                    $('#total_em_caixa').text(formatCurrency(totalEmCaixa));
                    $('#total_depositos').text(formatCurrency(totalDepositoCaixa));
                    $('#saldo_transportado').text(formatCurrency(totalSaldoTransportado));

                    var centavos = Math.round(totalEmCaixa * 100);
                    var fechado = (response.caixaStatus === 'fechado');
                    var podeFechar = !fechado && (centavos > 0 || (centavos === 0 && response.caixaStatus === 'aberto'));
                    $('#btnAdicionarDeposito').prop('disabled', fechado || centavos <= 0);
                    $('#btnTransportarSaldo').prop('disabled', !podeFechar);
                    if (centavos > 0) {
                        $('#btnTransportarSaldo').html('<i class="fa fa-lock" aria-hidden="true"></i> Fechar Caixa e Transportar Saldo <i class="fa fa-share" aria-hidden="true"></i>');
                    } else {
                        $('#btnTransportarSaldo').html('<i class="fa fa-lock" aria-hidden="true"></i> ' + (fechado ? 'Caixa fechado' : 'Fechar Caixa (sem saldo a transportar)'));
                    }

                    $('#tabelaDepositosRegistrados').DataTable({
                        "language": { "url": "../style/Portuguese-Brasil.json" },
                        "destroy": true,
                        "pageLength": 10
                    });

                    if (typeof callback === 'function') {
                        callback();
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao obter depósitos.', confirmButtonText: 'OK' });
                }
            });
        }

        function carregarDepositosCaixaUnificado(data) {
            $.ajax({
                url: 'listar_depositos_unificado.php',
                type: 'GET',
                data: { data: data },
                dataType: 'json',
                success: function(response) {
                    if (response.error) { alert('Erro: ' + response.error); return; }
                    if (!Array.isArray(response.depositos)) { alert('Erro: Dados de depósitos inválidos'); return; }

                    var depositos = response.depositos;
                    $('#detalhesDepositosCaixaUnificado').empty();
                    depositos.forEach(function(deposito) {
                        var dataCadastroFormatada = formatDateForDisplay2(deposito.data_cadastro);
                        var dataCaixaFormatada = formatDateForDisplay(deposito.data_caixa);
                        const podeExcluirUnificado = (deposito.pode_excluir === true || deposito.pode_excluir === 1 || deposito.pode_excluir === '1');
                        const deleteBtnUnificado = podeExcluirUnificado
                            ? `<button title="Remover" style="margin-bottom: 5px !important;" class="btn btn-delete btn-sm btn-icon" onclick="removerDeposito(${deposito.id})"><i class="fa fa-trash" aria-hidden="true"></i></button>`
                            : '';

                        $('#detalhesDepositosCaixaUnificado').append(`
                            <tr>
                                <td>${deposito.funcionario}</td>
                                <td>${dataCaixaFormatada}</td>
                                <td>${dataCadastroFormatada}</td>
                                <td>${formatCurrency(deposito.valor_do_deposito)}</td>
                                <td>${deposito.tipo_deposito}</td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm btn-icon" onclick="visualizarComprovanteCaixaUnificado('${deposito.caminho_anexo}', '${deposito.funcionario}', '${deposito.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    ${deleteBtnUnificado}
                                </td>
                            </tr>
                        `);
                    });

                    $('#tabelaDepositosCaixaUnificado').DataTable({
                        language: { url: "../style/Portuguese-Brasil.json" },
                        destroy: true,
                        pageLength: 10,
                        order: [],
                        autoWidth: false,
                        scrollX: true
                    });
                },
                error: function() { alert('Erro ao obter depósitos.'); }
            });
        }

        function formatDateForDisplay(date) {
            var d = new Date(date + 'T00:00:00');
            var day = ('0' + d.getUTCDate()).slice(-2);
            var month = ('0' + (d.getUTCMonth() + 1)).slice(-2);
            var year = d.getUTCFullYear();
            return `${day}/${month}/${year}`;
        }
        function formatDateForDisplay2(date) {
            var d = new Date(date);
            var day = ('0' + d.getDate()).slice(-2);
            var month = ('0' + (d.getMonth() + 1)).slice(-2);
            var year = d.getFullYear();
            return `${day}/${month}/${year}`;
        }

        function visualizarComprovante(caminho, funcionario, data_caixa) {
            var dir = `anexos/${formatDateForDir(data_caixa)}/${funcionario}/`;
            window.open(dir + caminho, '_blank');
        }
        function visualizarComprovanteCaixaUnificado(caminho, funcionario, data_caixa) {
            var dir = `anexos/${formatDateForDir(data_caixa)}/${funcionario}/`;
            window.open(dir + caminho, '_blank');
        }
        function visualizarAnexoSaida(caminho, funcionario, data_caixa) {
            var dir = `anexos/${data_caixa}/${funcionario}/saidas/`;
            window.open(dir + caminho, '_blank');
        }

        function formatDateForDir(date) {
            var d = new Date(date + 'T00:00:00');
            var day = ('0' + d.getUTCDate()).slice(-2);
            var month = ('0' + (d.getUTCMonth() + 1)).slice(-2);
            var year = d.getUTCFullYear().toString().slice(-2);
            return `${day}-${month}-${year}`;
        }

        function removerDeposito(button) {
            var id = (typeof button === 'object') ? $(button).attr('data-id') : button;
            if (!id || id === 'undefined') { console.error('ID do depósito a ser removido é indefinido.'); return; }

            Swal.fire({
                title: 'Deseja realmente remover este depósito?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, remover!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('remover_deposito.php', { id: id }, function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Depósito removido com sucesso!', confirmButtonText: 'OK' })
                            .then(() => { location.reload(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Erro!', text: response.error || 'Erro ao remover depósito.', confirmButtonText: 'OK' });
                        }
                    }, 'json');
                }
            });
        }

        function removerSaida(id) {
            Swal.fire({
                title: 'Deseja realmente remover esta saída/despesa?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, remover!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('update_saida.php', { id: id, status: 'removido' }, function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Saída/Despesa removida com sucesso!', confirmButtonText: 'OK' })
                            .then(() => { location.reload(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Erro!', text: response.error || 'Erro ao remover saída/despesa.', confirmButtonText: 'OK' });
                        }
                    }, 'json');
                }
            });
        }

        function verDepositosCaixa(data) {
            carregarDepositosCaixaUnificado(data);
            $('#verDepositosCaixaModal').modal('show');
            // Ajusta largura das colunas quando o modal terminar de abrir
            $('#verDepositosCaixaModal').one('shown.bs.modal', function () {
                if ($.fn.DataTable.isDataTable('#tabelaDepositosCaixaUnificado')) {
                    $('#tabelaDepositosCaixaUnificado').DataTable().columns.adjust();
                }
            });
        }

        function formatCurrency(value) {
            if (isNaN(value)) return 'R$ 0,00';
            return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',').replace(/\d(?=(\d{3})+,)/g, '$&.');
        }

        /* ===== Observação da linha de pagamento (lançada no módulo de O.S.) ===== */
        function escaparHtmlCx(texto) {
            return String(texto == null ? '' : texto)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatObservacaoPagamento(texto) {
            var t = (texto == null) ? '' : String(texto).trim();
            if (t === '') return '<span class="cx-obs-vazia">&mdash;</span>';
            return '<span class="cx-obs" title="' + escaparHtmlCx(t) + '">' + escaparHtmlCx(t).replace(/\n/g, '<br>') + '</span>';
        }

        function transportarSaldoFecharCaixa() {
            var saldo = (typeof window.__cxSaldoAtual === 'number') ? window.__cxSaldoAtual : parseBRMoney($('#total_em_caixa').text());
            var centavos = Math.round(saldo * 100);
            var totalEmCaixa = (centavos / 100).toFixed(2);
            var totalEmCaixaFormatado = formatCurrency(centavos / 100);

            if (window.__cxCaixaStatus === 'fechado') {
                Swal.fire({ icon: 'info', title: 'Caixa fechado', text: 'Este caixa já está fechado.', confirmButtonText: 'OK' });
                return;
            }
            if (centavos < 0) {
                Swal.fire({ icon: 'error', title: 'Saldo negativo', text: 'O saldo deste caixa está negativo (' + totalEmCaixaFormatado + '). Revise as saídas e depósitos antes de fechar.', confirmButtonText: 'OK' });
                return;
            }

            Swal.fire({
                title: 'Tem certeza?',
                text: centavos > 0
                    ? `Você realmente deseja fechar o caixa e transportar o saldo de ${totalEmCaixaFormatado} para o caixa seguinte?`
                    : 'O caixa não tem saldo a transportar. Deseja fechá-lo?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, fechar caixa',
                cancelButtonText: 'Não, cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    var dataCaixa = $('#data_caixa_deposito').val();
                    var funcionario = $('#funcionario_deposito').val();

                    $.ajax({
                        url: 'transportar_saldo_fechar_caixa.php',
                        type: 'POST',
                        data: {
                            total_em_caixa: totalEmCaixa,
                            data_caixa: dataCaixa,
                            funcionario: funcionario
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Sucesso!', 'Saldo transportado e caixa fechado com sucesso!', 'success');
                                $('#cadastroDepositoModal').modal('hide');
                                location.reload();
                            } else {
                                Swal.fire('Erro!', 'Erro ao transportar saldo e fechar caixa: ' + response.error, 'error');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            Swal.fire('Erro!', 'Erro ao transportar saldo e fechar caixa: ' + textStatus + ' - ' + errorThrown, 'error');
                        }
                    });
                }
            });
        }

        // Saldo do caixa (funcionário + data) calculado no servidor
        function carregarSaldoCaixa(funcionario, data, callback) {
            $.getJSON('saldo_caixa.php', { funcionario: funcionario, data_caixa: data })
                .done(function(r){
                    if (r && r.success && r.saldo) { callback(r.saldo); }
                    else { Swal.fire({ icon: 'error', title: 'Erro!', text: (r && r.error) || 'Não foi possível obter o saldo do caixa.' }); }
                })
                .fail(function(){ Swal.fire({ icon: 'error', title: 'Erro!', text: 'Não foi possível obter o saldo do caixa.' }); });
        }

        // Saldo inicial lançado pelo administrador (caixas abertos zerados)
        function abrirSaldoInicialAdmin(caixaId, funcionario, data, saldoAtual) {
            if (!window.CX_IS_ADMIN) {
                Swal.fire({ icon: 'warning', title: 'Acesso restrito', text: 'Somente o administrador pode lançar o saldo inicial.' });
                return;
            }
            $('#sia_caixa_id').val(caixaId);
            $('#siaFuncionario').text(funcionario);
            $('#siaData').text(formatDateForDisplay(data));
            var atual = parseFloat(saldoAtual) || 0;
            $('#sia_valor').val(atual > 0 ? atual.toFixed(2).replace('.', ',') : '').trigger('input');
            $('#sia_motivo').val('');
            $('#siaAtual').text('Saldo inicial atual: ' + formatCurrency(atual));
            $('#saldoInicialAdminModal').modal('show');
        }

        // Empilhamento sobre o modal de detalhes
        $('#saldoInicialAdminModal')
            .on('shown.bs.modal', function(){ $('.modal-backdrop').last().css('z-index', 1062); })
            .on('hidden.bs.modal', function(){ if ($('.modal.show').length) { $('body').addClass('modal-open'); } });

        $('#formSaldoInicialAdmin').on('submit', function(e){
            e.preventDefault();
            var valor = parseBRMoney($('#sia_valor').val());
            if (valor < 0 || $('#sia_valor').val().trim() === '') {
                Swal.fire({ icon: 'warning', title: 'Valor inválido', text: 'Informe o saldo inicial.' });
                return;
            }
            Swal.fire({
                title: 'Confirmar saldo inicial?',
                html: 'O caixa de <b>' + escaparHtmlCx($('#siaFuncionario').text()) + '</b> (' + escaparHtmlCx($('#siaData').text()) + ') passará a ter saldo inicial de <b>' + formatCurrency(valor) + '</b>.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, salvar',
                cancelButtonText: 'Cancelar'
            }).then(function(res){
                if (!res.isConfirmed) return;
                $.post('definir_saldo_inicial.php', {
                    caixa_id: $('#sia_caixa_id').val(),
                    saldo_inicial: valor.toFixed(2),
                    motivo: $('#sia_motivo').val()
                }, function(r){
                    if (r && r.success) {
                        Swal.fire({ icon: 'success', title: 'Saldo inicial lançado!', text: 'Disponível em caixa: ' + formatCurrency(r.disponivel), confirmButtonText: 'OK' })
                            .then(function(){ location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Não foi possível salvar', text: (r && r.error) || 'Erro ao lançar o saldo inicial.' });
                    }
                }, 'json').fail(function(x, t, er){
                    Swal.fire({ icon: 'error', title: 'Erro!', text: 'Falha ao lançar o saldo inicial: ' + t + ' - ' + er });
                });
            });
        });

        // NOVO: Fechamento rápido via botão com cadeado dourado no card
        function fecharCaixaRapido(funcionario, data) {
            $('#data_caixa_deposito').val(data);
            $('#funcionario_deposito').val(funcionario);
            carregarDepositos(funcionario, data, function(){
                transportarSaldoFecharCaixa();
            });
        }

        // NOVO: Abrir modal para anexar comprovante posteriormente
        function abrirModalAnexarComprovante(idDeposito, funcionario, data_caixa) {
            $('#deposito_id_anexo').val(idDeposito);
            $('#funcionario_anexo').val(funcionario);
            $('#data_caixa_anexo').val(data_caixa);
            $('#arquivo_comprovante').val('');
            $('#anexarComprovanteModal').modal('show');
        }

        // NOVO: Submit do formulário de anexo de comprovante
        $('#formAnexarComprovante').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            $.ajax({
                url: 'anexar_comprovante_deposito.php',   // endpoint para salvar o anexo (BACK-END)
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Comprovante anexado com sucesso!', confirmButtonText: 'OK' })
                        .then(() => { $('#anexarComprovanteModal').modal('hide'); location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro!', text: response.error || 'Falha ao anexar comprovante.', confirmButtonText: 'OK' });
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    Swal.fire({ icon: 'error', title: 'Erro!', text: 'Falha ao anexar comprovante: ' + textStatus + ' - ' + errorThrown, confirmButtonText: 'OK' });
                }
            });
        });

        // Recarregar ao fechar modais
        $('#detalhesModal').on('hidden.bs.modal', function () { location.reload(); });
        $('#cadastroSaidaModal').on('hidden.bs.modal', function () { location.reload(); });
        $('#cadastroDepositoModal').on('hidden.bs.modal', function () { location.reload(); });
        $('#verDepositosCaixaModal').on('hidden.bs.modal', function () { location.reload(); });

        // Abertura do caixa (submit)
        $('#formAbrirCaixa').on('submit', function(e) {
            e.preventDefault();

            var dadosAbertura = {};
            if (window.__abrirCaixaAdminLivre) {
                dadosAbertura.saldo_inicial = parseBRMoney($('#saldo_inicial').val()).toFixed(2);
            }

            $.ajax({
                url: 'abrir_caixa.php',
                type: 'POST',
                data: dadosAbertura,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Caixa aberto com sucesso!', text: response.zerado ? 'O caixa foi aberto zerado.' : ('Saldo inicial: ' + formatCurrency(response.saldo_inicial)), showConfirmButton: true, confirmButtonText: 'OK' })
                        .then((result) => {
                            if (result.isConfirmed) {
                                $('#abrirCaixaModal').modal('hide');
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao abrir caixa: ' + response.error, showConfirmButton: true, confirmButtonText: 'OK' });
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao abrir caixa: ' + textStatus + ' - ' + errorThrown, showConfirmButton: true, confirmButtonText: 'OK' });
                }
            });
        });
    </script>
    <?php include(__DIR__ . '/../rodape.php'); ?>

<!-- ===== Comprovantes de pagamento (Caixa) ===== -->
<div class="modal fade" id="caixaAnexoEscolhaModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff">
        <h5 class="modal-title"><i class="fa fa-paperclip"></i> Comprovantes do pagamento</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity:.9"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="text-muted" style="font-size:.9rem">Este pagamento tem mais de um comprovante. Escolha qual visualizar:</p>
        <div id="caixaAnexoLista" class="ca-lista"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="caixaAnexoViewerModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background:#0f172a;color:#fff">
        <h5 class="modal-title" id="caixaViewerTitle"><i class="fa fa-file"></i> Comprovante</h5>
        <div>
          <a id="caixaViewerDownload" class="btn btn-light btn-sm" href="#" target="_blank" title="Abrir em nova aba"><i class="fa fa-external-link"></i></a>
          <button type="button" class="close text-white ml-2" data-dismiss="modal" aria-label="Close" style="opacity:.9"><span aria-hidden="true">&times;</span></button>
        </div>
      </div>
      <div class="modal-body p-0">
        <div id="caixaViewerBody" class="ca-viewer"></div>
      </div>
    </div>
  </div>
</div>

<style>
  .ca-lista{ display:flex; flex-direction:column; gap:8px; }
  .ca-item{ display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid #e5e9f0; border-radius:12px; background:#fff; }
  .ca-item .ic{ width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.1rem; flex:0 0 auto; }
  .ca-item .ic.pdf{ background:#dc2626; } .ca-item .ic.img{ background:#0891b2; }
  .ca-item .meta{ flex:1; min-width:0; }
  .ca-item .nm{ font-weight:600; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .ca-item .sub{ font-size:.76rem; color:#94a3b8; }
  .ca-item .ca-view-btn{ flex:0 0 auto; width:38px; height:38px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; }
  .ca-item .ca-view-btn i{ font-size:15px; }
  .ca-viewer{ width:100%; height:78vh; background:#0f172a; display:flex; align-items:center; justify-content:center; }
  .ca-viewer iframe{ width:100%; height:100%; border:0; background:#fff; }
  .ca-viewer img{ max-width:100%; max-height:100%; object-fit:contain; }
  /* desfoque do modal de trás quando outro abre por cima */
  .modal .modal-dialog{ transition: filter .2s ease, opacity .2s ease; }
  .modal.is-behind .modal-dialog{ filter: blur(4px); opacity:.85; pointer-events:none; }
  /* ===== Observação da linha de pagamento ===== */
  .cx-obs-cell{ max-width:320px; }
  .cx-obs{ display:inline-block; font-size:.82rem; white-space:normal; word-break:break-word; }
  .cx-obs-vazia{ color:#9aa3af; }
</style>

<script>
(function(){
  function humanSize(n){ n=+n||0; if(n<1024) return n+' B'; if(n<1048576) return (n/1024).toFixed(1)+' KB'; return (n/1048576).toFixed(1)+' MB'; }
  function isPdfName(nm){ return /\.pdf$/i.test(String(nm||'')); }

  window.verComprovantePagamento = async function(pid, osId){
    try{
      var itens = [];
      // 1) comprovantes vinculados diretamente ao pagamento
      if (pid && parseInt(pid,10) > 0) {
        try{
          var r = await fetch('../os/pa_listar.php?pagamento_id=' + encodeURIComponent(pid), {credentials:'same-origin'});
          var j = await r.json();
          if (j.success && j.itens && j.itens.length) {
            itens = j.itens.map(function(it){
              return { nome: it.nome, is_pdf: it.is_pdf, enviado_em: it.enviado_em, tamanho: it.tamanho,
                       viewUrl: '../os/pa_ver.php?id=' + encodeURIComponent(it.id) };
            });
          }
        }catch(e){ /* segue para o fallback */ }
      }
      // 2) fallback: anexos gerais da O.S. (modal "Anexos")
      if (!itens.length && osId && parseInt(osId,10) > 0) {
        var fd = new URLSearchParams(); fd.append('os_id', osId);
        var r2 = await fetch('../os/obter_anexos.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd.toString(), credentials:'same-origin'});
        var j2 = await r2.json();
        if (j2.success && j2.anexos && j2.anexos.length) {
          itens = j2.anexos.map(function(a){
            var nome = a.caminho_anexo;
            return { nome: nome, is_pdf: isPdfName(nome),
                     enviado_em: a.data ? String(a.data).substring(0,16).replace('T',' ') : '',
                     tamanho: null,
                     viewUrl: '../os/anexos/' + encodeURIComponent(osId) + '/' + encodeURIComponent(nome) };
          });
        }
      }

      if (!itens.length){
        if(window.Swal) Swal.fire('Sem comprovante','Nenhum comprovante encontrado para este pagamento.','info'); else alert('Nenhum comprovante.');
        return;
      }
      if (itens.length === 1){ abrirVisualizadorCaixa(itens[0]); }
      else { mostrarEscolhaAnexos(itens); }
    }catch(e){ if(window.Swal) Swal.fire('Erro', e.message, 'error'); else alert('Erro: ' + e.message); }
  };

  function mostrarEscolhaAnexos(itens){
    var box = document.getElementById('caixaAnexoLista'); box.innerHTML = '';
    itens.forEach(function(it){
      var row = document.createElement('div'); row.className = 'ca-item';
      var ic = document.createElement('div'); ic.className = 'ic ' + (it.is_pdf ? 'pdf' : 'img');
      ic.innerHTML = '<i class="fa fa-' + (it.is_pdf ? 'file-pdf-o' : 'file-image-o') + '"></i>';
      var meta = document.createElement('div'); meta.className = 'meta';
      var nm = document.createElement('div'); nm.className = 'nm'; nm.textContent = it.nome;
      var sub = document.createElement('div'); sub.className = 'sub';
      sub.textContent = (it.enviado_em || '') + (it.tamanho ? (' · ' + humanSize(it.tamanho)) : '');
      meta.appendChild(nm); meta.appendChild(sub);
      var btn = document.createElement('button'); btn.type='button'; btn.className='btn btn-info btn-sm ca-view-btn'; btn.title='Visualizar'; btn.innerHTML='<i class="fa fa-eye"></i>';
      btn.addEventListener('click', function(){ $('#caixaAnexoEscolhaModal').modal('hide'); setTimeout(function(){ abrirVisualizadorCaixa(it); }, 250); });
      row.appendChild(ic); row.appendChild(meta); row.appendChild(btn);
      box.appendChild(row);
    });
    $('#caixaAnexoEscolhaModal').modal('show');
  }

  function abrirVisualizadorCaixa(it){
    document.getElementById('caixaViewerTitle').innerHTML = '<i class="fa fa-file"></i> ' + (it.nome || 'Comprovante');
    document.getElementById('caixaViewerDownload').href = it.viewUrl;
    var body = document.getElementById('caixaViewerBody'); body.innerHTML = '';
    if(it.is_pdf){ var ifr = document.createElement('iframe'); ifr.src = it.viewUrl; body.appendChild(ifr); }
    else { var img = document.createElement('img'); img.src = it.viewUrl; img.alt = it.nome || ''; body.appendChild(img); }
    $('#caixaAnexoViewerModal').modal('show');
  }

  // Modais empilhados: mantém o scroll e desfoca o(s) modal(is) de trás.
  function caRefreshModalStack(){
    var vis = $('.modal.show, .modal.in').filter(':visible').toArray();
    $('.modal').removeClass('is-behind');
    if (vis.length > 1) {
      $('body').addClass('modal-open');
      var top = vis.reduce(function(a,b){
        return (parseInt($(b).css('z-index'),10)||0) >= (parseInt($(a).css('z-index'),10)||0) ? b : a;
      });
      vis.forEach(function(m){ if (m !== top) $(m).addClass('is-behind'); });
    } else if (vis.length === 1) {
      $('body').addClass('modal-open');
    }
  }
  $(document).on('shown.bs.modal hidden.bs.modal', '.modal', caRefreshModalStack);
})();
</script>
</body>
</html>
