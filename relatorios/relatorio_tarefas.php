<?php
/**
 * relatorio_tarefas.php (recriado)
 * Relatório de Tarefas — unifica tarefas internas + pedidos de certidão.
 * Dados sob demanda via relatorio_tarefas_dados.php (JSON).
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$username = $_SESSION['username'];
$mode = 'light-mode';
$mq = $conn->prepare("SELECT modo FROM modo_usuario WHERE usuario = ?");
$mq->bind_param("s", $username); $mq->execute();
$mr = $mq->get_result();
if ($mr->num_rows > 0) { $mode = $mr->fetch_assoc()['modo']; }
$mq->close();

$funcionarios = [];
$rf = $conn->query("SELECT usuario, nome_completo FROM funcionarios WHERE status='ativo' ORDER BY nome_completo");
if ($rf) { while ($r = $rf->fetch_assoc()) { $funcionarios[] = $r; } }

/* Responsáveis REAIS presentes nos dados — garante que o filtro case com o que está
   gravado em tarefas.funcionario_responsavel e em pedidos_certidao (atualizado_por/criado_por). */
$responsaveis = [];
$rr = $conn->query("SELECT DISTINCT TRIM(funcionario_responsavel) AS r FROM tarefas WHERE funcionario_responsavel IS NOT NULL AND TRIM(funcionario_responsavel) <> ''");
if ($rr) { while ($x = $rr->fetch_assoc()) { $responsaveis[$x['r']] = true; } }
$rr2 = $conn->query("SELECT DISTINCT TRIM(COALESCE(NULLIF(atualizado_por,''), criado_por)) AS r FROM pedidos_certidao WHERE TRIM(COALESCE(NULLIF(atualizado_por,''), criado_por)) <> ''");
if ($rr2) { while ($x = $rr2->fetch_assoc()) { if (!empty($x['r'])) $responsaveis[$x['r']] = true; } }
$responsaveis = array_keys($responsaveis);
usort($responsaveis, function($a, $b){ return strcasecmp($a, $b); });

/* Rótulo amigável: se o valor gravado for um usuário conhecido, mostra o nome completo. */
$mapNome = [];
foreach ($funcionarios as $f) { $mapNome[mb_strtolower($f['usuario'])] = $f['nome_completo'] ?: $f['usuario']; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Tarefas</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <?php include(__DIR__ . '/style.php'); ?>
    <style>
        /* ===================== Tokens de tema ===================== */
        :root{
            --rel-bg:        #f3f5f9;
            --rel-panel:     #ffffff;
            --rel-border:    rgba(17,24,39,.07);
            --rel-shadow:    0 1px 3px rgba(16,24,40,.06), 0 6px 22px rgba(16,24,40,.05);
            --rel-text:      #1f2937;
            --rel-muted:     #6b7280;
            --rel-grid:      rgba(17,24,39,.07);
            --rel-thead:     #f8fafc;
            --rel-trow:      #eef1f6;
            --rel-input-bg:  #ffffff;
            --rel-input-bd:  #d7dce4;
            --rel-chart-tx:  #4b5563;
            --rel-accent:    #4e73df;
            --rel-accent-2:  #1cc88a;
            --rel-caret:     url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        }
        body.dark-mode{
            --rel-bg:        #121212;
            --rel-panel:     #1d1d24;
            --rel-border:    rgba(255,255,255,.08);
            --rel-shadow:    0 1px 3px rgba(0,0,0,.4), 0 8px 26px rgba(0,0,0,.45);
            --rel-text:      #e7e8ec;
            --rel-muted:     #9aa1ad;
            --rel-grid:      rgba(255,255,255,.09);
            --rel-thead:     #26262f;
            --rel-trow:      #2b2b35;
            --rel-input-bg:  #262630;
            --rel-input-bd:  #3a3a46;
            --rel-chart-tx:  #c6c9d2;
            --rel-accent:    #6f93ff;
            --rel-accent-2:  #34d399;
            --rel-caret:     url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%239aa1ad' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        }

        body{ background: var(--rel-bg); }
        #main.main-content{ background: var(--rel-bg); }

        .rel-header h3{ color: var(--rel-text); font-weight:700; letter-spacing:-.2px; }

        /* ===================== Painéis ===================== */
        .panel{
            background: var(--rel-panel);
            border: 1px solid var(--rel-border);
            border-radius: 16px;
            box-shadow: var(--rel-shadow);
            padding: 18px 20px;
            height: 100%;
        }
        .secao-titulo{
            display:flex; align-items:center; gap:.55rem;
            font-size: 1rem; font-weight:700; margin:0; color: var(--rel-text);
        }
        .secao-titulo::before{
            content:""; width:6px; height:18px; border-radius:6px;
            background: linear-gradient(180deg, var(--rel-accent), var(--rel-accent-2));
        }
        .secao-sub{ font-size:.78rem; color: var(--rel-muted); }

        /* ===================== Filtros ===================== */
        .filtros-card{
            background: var(--rel-panel);
            border: 1px solid var(--rel-border);
            border-radius: 16px;
            box-shadow: var(--rel-shadow);
        }
        .filtros-head{
            display:flex; align-items:center; gap:.5rem;
            font-weight:700; color: var(--rel-text); font-size:.95rem; margin-bottom:16px;
        }
        .filtros-head i{ color: var(--rel-accent); }

        /* Grid simétrico de colunas fixas (a última linha não estica) */
        .filtros-grid{
            display:grid; gap:16px 16px;
            grid-template-columns: repeat(2, 1fr);
            align-items:end;
        }
        @media (min-width: 768px){  .filtros-grid{ grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 1200px){ .filtros-grid{ grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 419.98px){ .filtros-grid{ grid-template-columns: 1fr; } }

        .campo{ display:flex; flex-direction:column; min-width:0; }
        .campo .form-label{
            font-weight:600; font-size:.72rem; text-transform:uppercase; letter-spacing:.4px;
            color: var(--rel-muted); margin-bottom:.35rem;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }

        /* Campos uniformes (selects + inputs com mesma altura, padding e raio) */
        .filtros-card .form-select,
        .filtros-card .form-control{
            height: 42px;
            padding: .5rem .85rem;
            font-size: .875rem;
            line-height: 1.2;
            background-color: var(--rel-input-bg);
            border: 1px solid var(--rel-input-bd);
            color: var(--rel-text);
            border-radius: 10px;
            box-shadow: none;
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
        }
        /* Seta customizada do select, ciente do tema */
        .filtros-card .form-select{
            -webkit-appearance:none; -moz-appearance:none; appearance:none;
            background-image: var(--rel-caret);
            background-repeat: no-repeat;
            background-position: right .8rem center;
            background-size: 14px 14px;
            padding-right: 2.1rem;
            cursor: pointer;
        }
        .filtros-card .form-select option{ background: var(--rel-input-bg); color: var(--rel-text); }
        .filtros-card .form-select:hover,
        .filtros-card .form-control:hover{ border-color: var(--rel-accent); }
        .filtros-card .form-select:focus,
        .filtros-card .form-control:focus{
            border-color: var(--rel-accent);
            box-shadow: 0 0 0 3px rgba(78,115,223,.18);
            background-color: var(--rel-input-bg);
        }
        .filtros-card .form-control::placeholder{ color: var(--rel-muted); opacity:.8; }
        /* Ícone do seletor de data legível no escuro */
        body.dark-mode .filtros-card input[type="date"]::-webkit-calendar-picker-indicator{
            filter: invert(1) brightness(.85);
        }

        /* Ações: alinhadas à direita no desktop, cheias no mobile */
        .filtros-acoes{ display:flex; gap:10px; justify-content:flex-end; }
        .filtros-acoes .btn{
            height:42px; padding:.5rem 1.3rem; border-radius:10px; font-weight:600;
            display:inline-flex; align-items:center; gap:.45rem;
        }
        #resumoFiltro{ color: var(--rel-muted); }

        /* ===================== KPIs ===================== */
        .card-dashboard{ height:auto; min-height:150px; border-radius:16px; }
        .card-dashboard .card-title{ font-size:.88rem; }
        .card-dashboard .card-value{ font-size: clamp(1.15rem, 3.2vw, 1.65rem); white-space:nowrap; }
        .card-sub{ font-size:.74rem; opacity:.9; color: rgba(255,255,255,.85); }

        /* ===================== Gráficos ===================== */
        .chart-box{ position:relative; height:320px; }
        .chart-box-sm{ position:relative; height:280px; }
        .chart-box-xs{ position:relative; height:260px; }

        /* ===================== Tabela atribuição (não-DataTable) ===================== */
        .tabela-attr{ width:100%; color: var(--rel-text); border-collapse:separate; border-spacing:0; }
        .tabela-attr thead th{
            font-size:.72rem; text-transform:uppercase; letter-spacing:.3px;
            color: var(--rel-muted); border-bottom:1px solid var(--rel-border);
            padding:8px 10px; font-weight:700;
        }
        .tabela-attr tbody td{ padding:9px 10px; border-bottom:1px solid var(--rel-border); font-size:.88rem; }
        .tabela-attr tbody tr:last-child td{ border-bottom:none; }
        .tabela-attr tfoot td{ padding:10px; font-weight:700; border-top:2px solid var(--rel-border); }
        .dot{ display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:7px; vertical-align:middle; }

        /* ===================== Tabelas dos painéis (robusto p/ light+dark) ===================== */
        .panel .table{
            --bs-table-bg: transparent;
            --bs-table-color: var(--rel-text);
            color: var(--rel-text);
            margin-bottom: 0;
        }
        .panel .table > thead > tr > th{
            color: var(--rel-muted);
            font-size:.74rem; text-transform:uppercase; letter-spacing:.3px; font-weight:700;
            border-bottom:1px solid var(--rel-border);
            background: var(--rel-thead);
        }
        .panel .table > tbody > tr > td{
            color: var(--rel-text);
            border-color: var(--rel-border);
        }
        .panel .table.table-hover > tbody > tr:hover > *{
            background: var(--rel-trow);
            color: var(--rel-text);
        }
        /* DataTables: busca, paginação e info no tema */
        .panel .dataTables_wrapper .dataTables_filter input,
        .panel .dataTables_wrapper .dataTables_length select{
            background: var(--rel-input-bg); color: var(--rel-text);
            border:1px solid var(--rel-input-bd); border-radius:8px;
        }
        .panel .dataTables_wrapper .dataTables_info,
        .panel .dataTables_wrapper .dataTables_filter,
        .panel .dataTables_wrapper .dataTables_length,
        .panel .dataTables_wrapper .dataTables_paginate{ color: var(--rel-muted); }
        .panel .dataTables_wrapper .paginate_button.current{
            background: var(--rel-accent) !important; border-color: var(--rel-accent) !important; color:#fff !important;
        }

        .text-money{ font-variant-numeric: tabular-nums; white-space:nowrap; }
        .dt-buttons .btn{ border-radius:8px; }
        .badge-attr-pill{
            display:inline-block; padding:.18rem .5rem; border-radius:999px;
            font-size:.7rem; font-weight:700; color:#fff;
        }

        /* ===================== Depósito prévio ===================== */
        .dep-stat{
            background: var(--rel-thead);
            border:1px solid var(--rel-border);
            border-radius:12px; padding:12px 14px; height:100%;
        }
        .dep-stat .rotulo{ font-size:.72rem; text-transform:uppercase; letter-spacing:.3px; color:var(--rel-muted); font-weight:700; }
        .dep-stat .valor{ font-size: clamp(1.05rem, 2.6vw, 1.35rem); font-weight:700; color:var(--rel-text); white-space:nowrap; }
        .dep-stat.destaque{
            background: linear-gradient(135deg, var(--rel-accent), var(--rel-accent-2));
            border:none; color:#fff;
        }
        .dep-stat.destaque .rotulo{ color: rgba(255,255,255,.9); }
        .dep-stat.destaque .valor{ color:#fff; font-size: clamp(1.3rem, 3.4vw, 1.8rem); }
        .switch-dep{ display:flex; align-items:center; gap:.5rem; }
        .switch-dep label{ font-size:.82rem; color:var(--rel-muted); cursor:pointer; }
        .saldo-pos{ color:#1ca35a; font-weight:700; }
        body.dark-mode .saldo-pos{ color:#34d399; }

        /* ===================== Dark: ajustes finos próprios ===================== */
        body.dark-mode .alert-warning{
            background:#3a3320; color:#f0d98a; border-color:#5a4d24;
        }
        body.dark-mode .btn-outline-secondary{ color:#cfd3db; border-color:#3a3a46; }
        body.dark-mode .btn-outline-secondary:hover{ background:#2b2b35; color:#fff; }

        /* ===================== Responsividade ===================== */
        #avisoSemDados{ display:none; border-radius:12px; }
        @media (max-width: 991.98px){
            .chart-box{ height:300px; }
        }
        @media (max-width: 575.98px){
            #main .container-fluid{ padding-left:.75rem; padding-right:.75rem; }
            .panel{ padding:14px; border-radius:14px; }
            .chart-box{ height:260px; }
            .chart-box-sm{ height:240px; }
            .chart-box-xs{ height:230px; }
            .card-dashboard{ min-height:120px; }
            .filtros-acoes{ flex-direction:row; }
            .filtros-acoes .btn{ flex:1; }
        }

        @media print{
            #menu, .sidebar, .bottom-nav, .filtros-card, .no-print, .dt-buttons { display:none !important; }
            #main{ margin-left:0 !important; }
            body{ background:#fff; }
            .panel{ box-shadow:none; border:1px solid #ddd; break-inside:avoid; }
        }
    </style>

    <style>
        /* Badges específicos do relatório de tarefas */
        .badge-st{ display:inline-block; padding:.24rem .62rem; border-radius:999px; font-size:.72rem; font-weight:700; white-space:nowrap; }
        .st-pendente{ background:#fff3cd; color:#7a5d00; }
        .st-andamento{ background:#dbe4ff; color:#2c4bb5; }
        .st-concluida{ background:#d6f5e3; color:#127a45; }
        .st-cancelada{ background:#e9ecef; color:#5b6470; }
        body.dark-mode .st-pendente{ background:#4a3f1a; color:#f3d27a; }
        body.dark-mode .st-andamento{ background:#26345e; color:#9db4ff; }
        body.dark-mode .st-concluida{ background:#1e4632; color:#6ee7a8; }
        body.dark-mode .st-cancelada{ background:#33373f; color:#aab1bd; }

        .badge-fonte{ display:inline-block; padding:.2rem .55rem; border-radius:8px; font-size:.68rem; font-weight:700; white-space:nowrap; }
        .fonte-tarefa{ background:#e7edff; color:#3754b5; }
        .fonte-pedido{ background:#e7f7f0; color:#0f8a52; }
        body.dark-mode .fonte-tarefa{ background:#2a335c; color:#9db4ff; }
        body.dark-mode .fonte-pedido{ background:#1d4633; color:#6ee7a8; }

        .badge-prio{ display:inline-block; padding:.18rem .5rem; border-radius:6px; font-size:.68rem; font-weight:700; }
        .prio-baixa{ background:#eef0f3; color:#5b6470; }
        .prio-media{ background:#dbe4ff; color:#2c4bb5; }
        .prio-alta{ background:#ffe8d6; color:#a85412; }
        .prio-critica{ background:#ffd9d9; color:#b3261e; }
        body.dark-mode .prio-baixa{ background:#33373f; color:#aab1bd; }
        body.dark-mode .prio-media{ background:#26345e; color:#9db4ff; }
        body.dark-mode .prio-alta{ background:#4a3320; color:#f0b27a; }
        body.dark-mode .prio-critica{ background:#4a2222; color:#f08a8a; }

        #tabelaTarefas tbody tr.linha-atraso > td:first-child{ box-shadow: inset 4px 0 0 #e74a3b; }
        .tag-atraso{ color:#e74a3b; font-weight:700; font-size:.68rem; display:block; }
    </style>
</head>
<body class="<?php echo htmlspecialchars($mode); ?>">
    <div id="loadingOverlay" style="display:none;"><div class="spinner"></div></div>
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container-fluid px-3 px-md-4 py-3">

            <div class="rel-header d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h3 class="mb-0"><i class="fa fa-tasks me-2" style="color:var(--rel-accent)"></i>Relatório de Tarefas</h3>
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm no-print"><i class="fa fa-print"></i> Imprimir</button>
            </div>

            <div class="filtros-card mb-4 no-print">
                <div class="card-body p-3 p-md-4">
                    <div class="filtros-head"><i class="fa fa-sliders"></i> Filtros de pesquisa</div>
                    <div class="filtros-grid">
                        <div class="campo">
                            <label class="form-label">Período</label>
                            <select id="fPreset" class="form-select form-select-sm">
                                <option value="hoje">Hoje</option><option value="ontem">Ontem</option>
                                <option value="7dias">Últimos 7 dias</option><option value="semana">Esta semana</option>
                                <option value="mes" selected>Este mês</option><option value="mes_passado">Mês passado</option>
                                <option value="ano">Este ano</option><option value="custom">Personalizado…</option>
                                <option value="todos">Todo o período</option>
                            </select>
                        </div>
                        <div class="campo d-none" id="boxDataIni"><label class="form-label">De</label><input type="date" id="fDataIni" class="form-control form-control-sm"></div>
                        <div class="campo d-none" id="boxDataFim"><label class="form-label">Até</label><input type="date" id="fDataFim" class="form-control form-control-sm"></div>
                        <div class="campo">
                            <label class="form-label">Fonte</label>
                            <select id="fFonte" class="form-select form-select-sm">
                                <option value="todas">Todas as fontes</option>
                                <option value="tarefas">Tarefas internas</option>
                                <option value="pedidos">Pedidos de certidão</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label class="form-label">Status</label>
                            <select id="fStatus" class="form-select form-select-sm">
                                <option value="todos">Todos</option>
                                <option value="pendente">Pendente</option>
                                <option value="andamento">Em andamento</option>
                                <option value="concluida">Concluída</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label class="form-label">Responsável</label>
                            <select id="fResponsavel" class="form-select form-select-sm">
                                <option value="todos">Todos</option>
                                <?php foreach ($responsaveis as $r): $lbl = $mapNome[mb_strtolower($r)] ?? $r; ?>
                                    <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label class="form-label">Prioridade</label>
                            <select id="fPrioridade" class="form-select form-select-sm">
                                <option value="todas">Todas</option>
                                <option value="Baixa">Baixa</option><option value="Média">Média</option>
                                <option value="Alta">Alta</option><option value="Crítica">Crítica</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label class="form-label">Série por</label>
                            <select id="fGran" class="form-select form-select-sm">
                                <option value="dia" selected>Dia</option><option value="semana">Semana</option><option value="mes">Mês</option>
                            </select>
                        </div>
                    </div>
                    <div class="filtros-acoes mt-3">
                        <button id="btnGerar" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Gerar relatório</button>
                        <button id="btnLimpar" class="btn btn-outline-secondary btn-sm"><i class="fa fa-eraser"></i> Limpar</button>
                    </div>
                    <small class="d-block mt-2" id="resumoFiltro"></small>
                </div>
            </div>

            <div class="alert alert-warning text-center" id="avisoSemDados"><i class="fa fa-info-circle"></i> Nenhuma tarefa encontrada para os filtros selecionados.</div>

            <!-- KPIs -->
            <div class="row row-cols-2 row-cols-lg-6 g-3 mb-4">
                <div class="col"><div class="card card-dashboard bg-blue"><div class="card-body"><h5 class="card-title">Total</h5><div class="card-value" id="kpiTotal">—</div><div class="card-sub">Tarefas + pedidos</div><div class="card-icon"><i class="fa fa-list-alt"></i></div></div></div></div>
                <div class="col"><div class="card card-dashboard bg-orange"><div class="card-body"><h5 class="card-title">Pendentes</h5><div class="card-value" id="kpiPend">—</div><div class="card-sub">Aguardando</div><div class="card-icon"><i class="fa fa-hourglass-half"></i></div></div></div></div>
                <div class="col"><div class="card card-dashboard bg-teal"><div class="card-body"><h5 class="card-title">Em andamento</h5><div class="card-value" id="kpiAnd">—</div><div class="card-sub">Em execução</div><div class="card-icon"><i class="fa fa-spinner"></i></div></div></div></div>
                <div class="col"><div class="card card-dashboard bg-green"><div class="card-body"><h5 class="card-title">Concluídas</h5><div class="card-value" id="kpiConc">—</div><div class="card-sub">Finalizadas</div><div class="card-icon"><i class="fa fa-check-circle"></i></div></div></div></div>
                <div class="col"><div class="card card-dashboard bg-red"><div class="card-body"><h5 class="card-title">Atrasadas</h5><div class="card-value" id="kpiAtr">—</div><div class="card-sub">Prazo vencido</div><div class="card-icon"><i class="fa fa-exclamation-triangle"></i></div></div></div></div>
                <div class="col"><div class="card card-dashboard bg-purple"><div class="card-body"><h5 class="card-title">Conclusão</h5><div class="card-value" id="kpiTaxa">—</div><div class="card-sub">Taxa (excl. canc.)</div><div class="card-icon"><i class="fa fa-percent"></i></div></div></div></div>
            </div>

            <!-- Tarefas em aberto (independente dos filtros) -->
            <div class="panel mb-4" id="painelAberto">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <p class="secao-titulo mb-0">Tarefas em aberto</p>
                    <span class="badge-fonte" style="background:#e7edff;color:#3754b5;">Independente dos filtros</span>
                </div>
                <p class="secao-sub mb-3"><i class="fa fa-info-circle"></i> Todas as tarefas pendentes e em andamento (das duas fontes), para ver o que está pendente sem precisar filtrar.</p>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md">
                        <div class="dep-stat destaque" style="background:linear-gradient(135deg,#f6c23e,#f4a62a);">
                            <div class="rotulo">Em aberto</div><div class="valor" id="abTotal">—</div>
                        </div>
                    </div>
                    <div class="col-6 col-md"><div class="dep-stat"><div class="rotulo">Pendentes</div><div class="valor" id="abPend">—</div></div></div>
                    <div class="col-6 col-md"><div class="dep-stat"><div class="rotulo">Em andamento</div><div class="valor" id="abAnd">—</div></div></div>
                    <div class="col-6 col-md"><div class="dep-stat"><div class="rotulo">Atrasadas</div><div class="valor" id="abAtr" style="color:#e74a3b;">—</div></div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tabelaAberto">
                        <thead><tr>
                            <th>Fonte</th><th>Ref.</th><th>Título</th><th>Categoria/Tipo</th>
                            <th>Responsável</th><th>Prioridade</th><th>Criada em</th><th>Prazo</th><th>Status</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Donut + Série -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-4"><div class="panel"><p class="secao-titulo mb-3">Distribuição por status</p><div class="chart-box-sm"><canvas id="chartStatus"></canvas></div></div></div>
                <div class="col-12 col-lg-8"><div class="panel"><p class="secao-titulo mb-3">Criadas x Concluídas no período</p><div class="chart-box"><canvas id="chartSerie"></canvas></div></div></div>
            </div>

            <!-- Fonte x Status + Responsável -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-6"><div class="panel"><p class="secao-titulo mb-3">Por fonte e status</p><div class="chart-box-sm"><canvas id="chartFonte"></canvas></div></div></div>
                <div class="col-12 col-lg-6"><div class="panel"><p class="secao-titulo mb-3">Produtividade por responsável</p><div class="chart-box-sm"><canvas id="chartResp"></canvas></div></div></div>
            </div>

            <!-- Tabela -->
            <div class="panel mb-5">
                <p class="secao-titulo mb-3">Tarefas (lista unificada)</p>
                <div class="chart-box-xs mb-3"><canvas id="chartCategoria"></canvas></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tabelaTarefas">
                        <thead><tr>
                            <th>Fonte</th><th>Ref.</th><th>Título</th><th>Categoria/Tipo</th>
                            <th>Responsável</th><th>Prioridade</th><th>Criada em</th><th>Prazo</th><th>Status</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/chart.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

    <script>
    const fmtNum = new Intl.NumberFormat('pt-BR');
    const ST_COR = {'Pendente':'#f6c23e','Em andamento':'#4e73df','Concluída':'#1cc88a','Cancelada':'#858796'};
    const DT_LANG = 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json';
    let charts = {}, dtTar = null, dtAberto = null;
    if (window.Chart){ Chart.defaults.font.family = 'Inter, system-ui, -apple-system, sans-serif'; }

    function cssVar(n){ return getComputedStyle(document.body).getPropertyValue(n).trim(); }
    function aplicarTemaCharts(){ if(!window.Chart) return; Chart.defaults.color = cssVar('--rel-chart-tx')||'#4b5563'; Chart.defaults.borderColor = cssVar('--rel-grid')||'rgba(0,0,0,.07)'; }
    function gridColor(){ return cssVar('--rel-grid')||'rgba(0,0,0,.07)'; }
    function destruir(id){ if(charts[id]){ charts[id].destroy(); delete charts[id]; } }
    function escapeHtml(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function brData(ts){ if(!ts) return '—'; const d=String(ts).split(' ')[0].split('-'); return d.length===3?(d[2]+'/'+d[1]+'/'+d[0]):ts; }
    function stClass(n){ return {'Pendente':'st-pendente','Em andamento':'st-andamento','Concluída':'st-concluida','Cancelada':'st-cancelada'}[n]||'st-pendente'; }
    function prioClass(p){ return {'Baixa':'prio-baixa','Média':'prio-media','Alta':'prio-alta','Crítica':'prio-critica'}[p]||''; }

    function lerFiltros(){
        return { preset:$('#fPreset').val(), data_inicio:$('#fDataIni').val(), data_fim:$('#fDataFim').val(),
                 fonte:$('#fFonte').val(), status:$('#fStatus').val(), responsavel:$('#fResponsavel').val(),
                 prioridade:$('#fPrioridade').val(), granularidade:$('#fGran').val() };
    }
    $('#fPreset').on('change', function(){ const c=$(this).val()==='custom'; $('#boxDataIni,#boxDataFim').toggleClass('d-none', !c); });
    $('#btnLimpar').on('click', function(){
        $('#fPreset').val('mes').trigger('change'); $('#fFonte').val('todas'); $('#fStatus').val('todos');
        $('#fResponsavel').val('todos'); $('#fPrioridade').val('todas'); $('#fGran').val('dia'); gerar();
    });
    $('#btnGerar').on('click', gerar);

    function gerar(){
        $('#loadingOverlay').show(); $('#avisoSemDados').hide();
        $.getJSON('relatorio_tarefas_dados.php', lerFiltros())
            .done(function(resp){ if(!resp||!resp.ok){ alert('Erro: '+((resp&&resp.erro)||'desconhecido')); return; } window._ult=resp; render(resp); })
            .fail(function(xhr){ let m='Falha na requisição.'; try{ m=JSON.parse(xhr.responseText).erro||m; }catch(e){} alert(m); })
            .always(function(){ $('#loadingOverlay').hide(); });
    }

    function render(resp){
        aplicarTemaCharts();
        const p=resp.periodo;
        $('#resumoFiltro').html('<i class="fa fa-calendar-o"></i> '+((p.inicio&&p.fim)?('de '+brData(p.inicio)+' até '+brData(p.fim)):'todo o período (sem filtro de data)'));
        const k=resp.kpis;
        $('#kpiTotal').text(fmtNum.format(k.total)); $('#kpiPend').text(fmtNum.format(k.pendentes));
        $('#kpiAnd').text(fmtNum.format(k.em_andamento)); $('#kpiConc').text(fmtNum.format(k.concluidas));
        $('#kpiAtr').text(fmtNum.format(k.atrasadas)); $('#kpiTaxa').text((k.taxa_conclusao||0).toLocaleString('pt-BR')+'%');
        $('#avisoSemDados').toggle(k.total===0);
        renderStatus(resp.porStatus); renderSerie(resp.serie); renderFonte(resp.fonteStatus);
        renderResp(resp.porResponsavel); renderCategoria(resp.porCategoria); renderTabela(resp.lista);
        renderAberto(resp.emAberto);
    }

    function renderStatus(dados){
        destruir('status');
        charts.status = new Chart(document.getElementById('chartStatus'), {
            type:'doughnut',
            data:{ labels:dados.map(d=>d.status), datasets:[{ data:dados.map(d=>d.total),
                    backgroundColor:dados.map(d=>ST_COR[d.status]||'#858796'), borderColor:cssVar('--rel-panel'), borderWidth:2 }] },
            options:{ responsive:true, maintainAspectRatio:false, cutout:'62%',
                plugins:{ legend:{position:'bottom', labels:{boxWidth:12,padding:12,font:{size:11}}},
                          tooltip:{ callbacks:{ label:c=>c.label+': '+fmtNum.format(c.parsed) } } } }
        });
    }
    function renderSerie(serie){
        destruir('serie');
        charts.serie = new Chart(document.getElementById('chartSerie'), {
            data:{ labels:serie.map(s=>s.periodo), datasets:[
                { type:'bar', label:'Criadas', data:serie.map(s=>s.total), backgroundColor:'rgba(78,115,223,.85)', borderRadius:6, maxBarThickness:42 },
                { type:'line', label:'Concluídas', data:serie.map(s=>s.concluidas), borderColor:'#1cc88a', backgroundColor:'#1cc88a', tension:.35, borderWidth:2, pointRadius:3 }
            ]},
            options:{ responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false},
                plugins:{ legend:{position:'bottom'} },
                scales:{ x:{ grid:{color:gridColor()} }, y:{ grid:{color:gridColor()}, ticks:{ callback:v=>fmtNum.format(v), precision:0 } } } }
        });
    }
    function renderFonte(fs){
        destruir('fonte');
        const fontes=['Tarefa','Pedido de Certidão'];
        const sts=['Pendente','Em andamento','Concluída','Cancelada'];
        charts.fonte = new Chart(document.getElementById('chartFonte'), {
            type:'bar',
            data:{ labels:fontes, datasets: sts.map(s=>({ label:s, data:fontes.map(f=>(fs[f]&&fs[f][s])||0), backgroundColor:ST_COR[s], borderRadius:4, stack:'s' })) },
            options:{ responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{position:'bottom'} },
                scales:{ x:{ stacked:true, grid:{display:false} }, y:{ stacked:true, grid:{color:gridColor()}, ticks:{ callback:v=>fmtNum.format(v), precision:0 } } } }
        });
    }
    function renderResp(dados){
        destruir('resp');
        const top=dados.slice(0,12);
        charts.resp = new Chart(document.getElementById('chartResp'), {
            type:'bar',
            data:{ labels:top.map(d=>d.responsavel), datasets:[
                { label:'Total', data:top.map(d=>d.total), backgroundColor:'#4e73df', borderRadius:4 },
                { label:'Concluídas', data:top.map(d=>d.concluidas), backgroundColor:'#1cc88a', borderRadius:4 }
            ]},
            options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{position:'bottom'} },
                scales:{ x:{ grid:{color:gridColor()}, ticks:{ callback:v=>fmtNum.format(v), precision:0 } }, y:{ grid:{display:false} } } }
        });
    }
    function renderCategoria(dados){
        destruir('categoria');
        const top=dados.slice(0,10);
        const PAL=['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#9b59b6','#fd7e14','#20c997','#6610f2','#858796'];
        charts.categoria = new Chart(document.getElementById('chartCategoria'), {
            type:'bar',
            data:{ labels:top.map(d=>d.rotulo), datasets:[{ label:'Tarefas', data:top.map(d=>d.total), backgroundColor:top.map((d,i)=>PAL[i%PAL.length]), borderRadius:5 }] },
            options:{ responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{display:false}, title:{display:true, text:'Por categoria / tipo', color:cssVar('--rel-muted'), font:{size:12} } },
                scales:{ x:{ grid:{display:false} }, y:{ grid:{color:gridColor()}, ticks:{ callback:v=>fmtNum.format(v), precision:0 } } } }
        });
    }
    function renderAberto(ab){
        ab = ab || {totais:{total:0,pendentes:0,em_andamento:0,atrasadas:0}, lista:[]};
        const t = ab.totais || {};
        $('#abTotal').text(fmtNum.format(+t.total||0));
        $('#abPend').text(fmtNum.format(+t.pendentes||0));
        $('#abAnd').text(fmtNum.format(+t.em_andamento||0));
        $('#abAtr').text(fmtNum.format(+t.atrasadas||0));

        if(dtAberto){ dtAberto.destroy(); $('#tabelaAberto tbody').empty(); }
        const tb=$('#tabelaAberto tbody');
        (ab.lista||[]).forEach(function(l){
            const fonteCls = l.fonte==='Tarefa' ? 'fonte-tarefa' : 'fonte-pedido';
            const prio = (l.prioridade && l.prioridade!=='—') ? '<span class="badge-prio '+prioClass(l.prioridade)+'">'+escapeHtml(l.prioridade)+'</span>' : '<span class="text-muted">—</span>';
            const prazo = l.prazo ? (brData(l.prazo) + (l.atrasada?'<span class="tag-atraso">em atraso</span>':'')) : '<span class="text-muted">—</span>';
            tb.append('<tr class="'+(l.atrasada?'linha-atraso':'')+'">' +
                '<td><span class="badge-fonte '+fonteCls+'">'+escapeHtml(l.fonte)+'</span></td>' +
                '<td>'+escapeHtml(l.ref)+'</td>' +
                '<td>'+escapeHtml(l.titulo)+'</td>' +
                '<td>'+escapeHtml(l.categoria)+'</td>' +
                '<td>'+escapeHtml(l.responsavel)+'</td>' +
                '<td>'+prio+'</td>' +
                '<td data-order="'+escapeHtml(l.data_criacao||'')+'">'+brData(l.data_criacao)+'</td>' +
                '<td data-order="'+escapeHtml(l.prazo||'')+'">'+prazo+'</td>' +
                '<td><span class="badge-st '+stClass(l.status_norm)+'">'+escapeHtml(l.status)+'</span></td>' +
                '</tr>');
        });
        dtAberto = $('#tabelaAberto').DataTable({ order:[], pageLength:10, language:{url:DT_LANG},
            dom:'Bfrtip', buttons:['copyHtml5','excelHtml5','pdfHtml5','print'] });
    }

    function renderTabela(lista){
        if(dtTar){ dtTar.destroy(); $('#tabelaTarefas tbody').empty(); }
        const tb=$('#tabelaTarefas tbody');
        (lista||[]).forEach(function(l){
            const fonteCls = l.fonte==='Tarefa' ? 'fonte-tarefa' : 'fonte-pedido';
            const prio = (l.prioridade && l.prioridade!=='—') ? '<span class="badge-prio '+prioClass(l.prioridade)+'">'+escapeHtml(l.prioridade)+'</span>' : '<span class="text-muted">—</span>';
            const prazo = l.prazo ? (brData(l.prazo) + (l.atrasada?'<span class="tag-atraso">em atraso</span>':'')) : '<span class="text-muted">—</span>';
            tb.append('<tr class="'+(l.atrasada?'linha-atraso':'')+'">' +
                '<td><span class="badge-fonte '+fonteCls+'">'+escapeHtml(l.fonte)+'</span></td>' +
                '<td>'+escapeHtml(l.ref)+'</td>' +
                '<td>'+escapeHtml(l.titulo)+'</td>' +
                '<td>'+escapeHtml(l.categoria)+'</td>' +
                '<td>'+escapeHtml(l.responsavel)+'</td>' +
                '<td>'+prio+'</td>' +
                '<td data-order="'+escapeHtml(l.data_criacao||'')+'">'+brData(l.data_criacao)+'</td>' +
                '<td data-order="'+escapeHtml(l.prazo||'')+'">'+prazo+'</td>' +
                '<td><span class="badge-st '+stClass(l.status_norm)+'">'+escapeHtml(l.status)+'</span></td>' +
                '</tr>');
        });
        dtTar = $('#tabelaTarefas').DataTable({ order:[[6,'desc']], pageLength:15, language:{url:DT_LANG},
            dom:'Bfrtip', buttons:['copyHtml5','excelHtml5','pdfHtml5','print'] });
    }

    let _t=null;
    new MutationObserver(function(){ clearTimeout(_t); _t=setTimeout(function(){ if(window._ult) render(window._ult); },120); })
        .observe(document.body,{attributes:true,attributeFilter:['class']});

    $(document).ready(gerar);
    </script>
</body>
</html>
