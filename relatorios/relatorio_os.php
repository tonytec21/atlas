<?php
/**
 * relatorio_os.php  (recriado — v2: estética, responsividade e dark mode)
 * Relatório de Ordens de Serviço — Desempenho, Faturamento e Atos Liquidados.
 *
 * Dados sob demanda via relatorio_os_dados.php (JSON). A página abre carregando
 * apenas o mês atual para não sobrecarregar.
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/checar_acesso_de_administrador.php');
date_default_timezone_set('America/Sao_Paulo');

$username = $_SESSION['username'];

// Tema atual (mesma origem do menu.php)
$mode = 'light-mode';
$mode_query = $conn->prepare("SELECT modo FROM modo_usuario WHERE usuario = ?");
$mode_query->bind_param("s", $username);
$mode_query->execute();
$mode_result = $mode_query->get_result();
if ($mode_result->num_rows > 0) {
    $mode = $mode_result->fetch_assoc()['modo'];
}
$mode_query->close();

// Funcionários ativos para o filtro
$funcionarios = [];
$resFunc = $conn->query("SELECT usuario, nome_completo FROM funcionarios WHERE status = 'ativo' ORDER BY nome_completo");
if ($resFunc) {
    while ($r = $resFunc->fetch_assoc()) { $funcionarios[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Ordens de Serviço</title>
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
        .card-dashboard .card-value{ font-size: clamp(1rem, 2.4vw, 1.42rem); white-space:nowrap; }
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
        /* KPI de repasse (não é faturamento) */
        .bg-slate{ background:linear-gradient(135deg,#64748b,#94a3b8); }
        body.dark-mode .bg-slate{ background:linear-gradient(135deg,#475569,#64748b); }

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
</head>
<body class="<?php echo htmlspecialchars($mode); ?>">
    <div id="loadingOverlay" style="display:none;">
        <div class="spinner"></div>
    </div>

    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container-fluid px-3 px-md-4 py-3">

            <div class="rel-header d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h3 class="mb-0"><i class="fa fa-bar-chart me-2" style="color:var(--rel-accent)"></i>Relatório de Ordens de Serviço</h3>
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm no-print">
                    <i class="fa fa-print"></i> Imprimir
                </button>
            </div>

            <!-- ====================== FILTROS ====================== -->
            <div class="filtros-card mb-4 no-print">
                <div class="card-body p-3 p-md-4">
                    <div class="filtros-head"><i class="fa fa-sliders"></i> Filtros de pesquisa</div>

                    <div class="filtros-grid">
                        <div class="campo">
                            <label class="form-label">Período</label>
                            <select id="fPreset" class="form-select form-select-sm">
                                <option value="hoje">Hoje</option>
                                <option value="ontem">Ontem</option>
                                <option value="7dias">Últimos 7 dias</option>
                                <option value="semana">Esta semana</option>
                                <option value="mes" selected>Este mês</option>
                                <option value="mes_passado">Mês passado</option>
                                <option value="ano">Este ano</option>
                                <option value="custom">Personalizado…</option>
                                <option value="todos">Todo o período</option>
                            </select>
                        </div>
                        <div class="campo d-none" id="boxDataIni">
                            <label class="form-label">De</label>
                            <input type="date" id="fDataIni" class="form-control form-control-sm">
                        </div>
                        <div class="campo d-none" id="boxDataFim">
                            <label class="form-label">Até</label>
                            <input type="date" id="fDataFim" class="form-control form-control-sm">
                        </div>
                        <div class="campo">
                            <label class="form-label">Status</label>
                            <select id="fStatus" class="form-select form-select-sm">
                                <option value="todos">Todos</option>
                                <option value="liquidado">Liquidado</option>
                                <option value="parcialmente liquidado">Parcialmente liquidado</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label class="form-label">Atribuição</label>
                            <select id="fAtribuicao" class="form-select form-select-sm">
                                <option value="todas">Todas</option>
                                <option value="notas">Notas (13)</option>
                                <option value="civil">Registro Civil (14)</option>
                                <option value="rtd">RTD e RCPJ (15)</option>
                                <option value="imoveis">Registro de Imóveis (16)</option>
                                <option value="protesto">Protesto (17)</option>
                                <option value="maritimos">Contratos Marítimos (18)</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label class="form-label">Funcionário</label>
                            <select id="fFuncionario" class="form-select form-select-sm">
                                <option value="todos">Todos</option>
                                <?php foreach ($funcionarios as $f): ?>
                                    <option value="<?= htmlspecialchars($f['usuario']) ?>">
                                        <?= htmlspecialchars($f['nome_completo'] ?: $f['usuario']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label class="form-label">Código do ato</label>
                            <input type="text" id="fAto" class="form-control form-control-sm" placeholder="ex.: 13 ou 13.001">
                        </div>
                        <div class="campo">
                            <label class="form-label">Base do faturamento</label>
                            <select id="fBase" class="form-select form-select-sm">
                                <option value="emolumentos" selected>Emolumentos</option>
                                <option value="total">Valor total</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label class="form-label">Série por</label>
                            <select id="fGran" class="form-select form-select-sm">
                                <option value="dia" selected>Dia</option>
                                <option value="semana">Semana</option>
                                <option value="mes">Mês</option>
                            </select>
                        </div>
                    </div>

                    <div class="filtros-acoes mt-3">
                        <button id="btnGerar" class="btn btn-primary btn-sm">
                            <i class="fa fa-search"></i> Gerar relatório
                        </button>
                        <button id="btnLimpar" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-eraser"></i> Limpar
                        </button>
                    </div>
                    <small class="d-block mt-2" id="resumoFiltro"></small>
                </div>
            </div>

            <div class="alert alert-warning text-center" id="avisoSemDados">
                <i class="fa fa-info-circle"></i> Nenhum ato liquidado encontrado para os filtros selecionados.
            </div>

            <!-- ====================== KPIs ====================== -->
            <div class="row row-cols-2 row-cols-lg-6 g-3 mb-4">
                <div class="col">
                    <div class="card card-dashboard bg-blue"><div class="card-body">
                        <h5 class="card-title">Faturamento</h5>
                        <div class="card-value" id="kpiEmol">—</div>
                        <div class="card-sub">Emolumentos (serventia)</div>
                        <div class="card-icon"><i class="fa fa-money"></i></div>
                    </div></div>
                </div>
                <div class="col">
                    <div class="card card-dashboard bg-teal"><div class="card-body">
                        <h5 class="card-title">Valor total</h5>
                        <div class="card-value" id="kpiTotal">—</div>
                        <div class="card-sub">Emolumentos + fundos</div>
                        <div class="card-icon"><i class="fa fa-database"></i></div>
                    </div></div>
                </div>
                <div class="col" id="kpiRepasseCol">
                    <div class="card card-dashboard bg-slate"><div class="card-body">
                        <h5 class="card-title">Repasse a credores</h5>
                        <div class="card-value" id="kpiRepasse">—</div>
                        <div class="card-sub">Não é faturamento</div>
                        <div class="card-icon"><i class="fa fa-exchange"></i></div>
                    </div></div>
                </div>
                <div class="col">
                    <div class="card card-dashboard bg-green"><div class="card-body">
                        <h5 class="card-title">Atos liquidados</h5>
                        <div class="card-value" id="kpiAtos">—</div>
                        <div class="card-sub">Quantidade total</div>
                        <div class="card-icon"><i class="fa fa-check-circle"></i></div>
                    </div></div>
                </div>
                <div class="col">
                    <div class="card card-dashboard bg-orange"><div class="card-body">
                        <h5 class="card-title">O.S. atendidas</h5>
                        <div class="card-value" id="kpiOS">—</div>
                        <div class="card-sub">Ordens distintas</div>
                        <div class="card-icon"><i class="fa fa-file-text"></i></div>
                    </div></div>
                </div>
                <div class="col">
                    <div class="card card-dashboard bg-purple"><div class="card-body">
                        <h5 class="card-title">Funcionários</h5>
                        <div class="card-value" id="kpiFunc">—</div>
                        <div class="card-sub">Com produção</div>
                        <div class="card-icon"><i class="fa fa-users"></i></div>
                    </div></div>
                </div>
            </div>

            <!-- ====================== Depósito prévio ====================== -->
            <div class="panel mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <p class="secao-titulo mb-0">Depósito prévio — saldo não consumido</p>
                    <div class="switch-dep no-print form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="depPeriodo">
                        <label class="form-check-label" for="depPeriodo">Filtrar pela data de criação da O.S. (usa o período acima)</label>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md">
                        <div class="dep-stat destaque">
                            <div class="rotulo">Saldo disponível</div>
                            <div class="valor" id="depSaldo">—</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="dep-stat">
                            <div class="rotulo">O.S. com saldo</div>
                            <div class="valor" id="depQtd">—</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="dep-stat">
                            <div class="rotulo">Total depositado</div>
                            <div class="valor" id="depDepositado">—</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="dep-stat">
                            <div class="rotulo">Consumido em atos</div>
                            <div class="valor" id="depConsumido">—</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="dep-stat">
                            <div class="rotulo">Repassado ao credor</div>
                            <div class="valor" id="depRepassado">—</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="dep-stat">
                            <div class="rotulo">Devolvido</div>
                            <div class="valor" id="depDevolvido">—</div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tabelaDeposito">
                        <thead><tr>
                            <th>O.S.</th>
                            <th>Cliente</th>
                            <th>CPF/CNPJ</th>
                            <th>Criada em</th>
                            <th class="text-end">Depositado</th>
                            <th class="text-end">Consumido</th>
                            <th class="text-end">Repassado</th>
                            <th class="text-end">Devolvido</th>
                            <th class="text-end">Saldo</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- ====================== Repasse a credores (Protesto) ====================== -->
            <div class="panel mb-4" id="painelRepasse" style="display:none;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <p class="secao-titulo mb-0">Repasse a credores (Protesto)</p>
                </div>
                <p class="secao-sub mb-3"><i class="fa fa-info-circle"></i> Valores recebidos e repassados diretamente ao credor. <strong>Não compõem o faturamento da serventia.</strong></p>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md">
                        <div class="dep-stat destaque" style="background:linear-gradient(135deg,#64748b,#94a3b8);">
                            <div class="rotulo">Total repassado</div>
                            <div class="valor" id="repTotal">—</div>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="dep-stat"><div class="rotulo">Repasses</div><div class="valor" id="repQtd">—</div></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="dep-stat"><div class="rotulo">Por forma de repasse</div><div class="valor" id="repFormas" style="font-size:.92rem; white-space:normal;">—</div></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tabelaRepasse">
                        <thead><tr>
                            <th>O.S.</th>
                            <th>Cliente</th>
                            <th>Funcionário</th>
                            <th>Data</th>
                            <th>Forma</th>
                            <th class="text-end">Valor repassado</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- ====================== Atribuição + Série ====================== -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-5">
                    <div class="panel">
                        <p class="secao-titulo mb-3">Faturamento por atribuição</p>
                        <div class="chart-box-sm"><canvas id="chartAtribuicao"></canvas></div>
                        <div class="table-responsive mt-3">
                            <table class="tabela-attr" id="tabelaAtribuicao">
                                <thead><tr>
                                    <th>Atribuição</th>
                                    <th class="text-end">Atos</th>
                                    <th class="text-end">Emolumentos</th>
                                    <th class="text-end">Total</th>
                                </tr></thead>
                                <tbody></tbody>
                                <tfoot><tr>
                                    <td>Total</td>
                                    <td class="text-end" id="atribFootQtd">—</td>
                                    <td class="text-end" id="atribFootEmol">—</td>
                                    <td class="text-end" id="atribFootTotal">—</td>
                                </tr></tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-7">
                    <div class="panel">
                        <p class="secao-titulo mb-3">Evolução no período</p>
                        <div class="chart-box"><canvas id="chartSerie"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- ====================== Desempenho funcionários ====================== -->
            <div class="panel mb-4">
                <p class="secao-titulo mb-3">Desempenho dos funcionários</p>
                <div class="row g-4 mb-3">
                    <div class="col-12 col-lg-6">
                        <span class="secao-sub">Top 10 por faturamento</span>
                        <div class="chart-box-sm mt-1"><canvas id="chartFuncFat"></canvas></div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <span class="secao-sub">Top 10 por quantidade de atos</span>
                        <div class="chart-box-sm mt-1"><canvas id="chartFuncQtd"></canvas></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tabelaFuncionarios">
                        <thead><tr>
                            <th>Funcionário</th>
                            <th class="text-end">Atos liquidados</th>
                            <th class="text-end">Emolumentos</th>
                            <th class="text-end">Valor total</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- ====================== Atos mais liquidados ====================== -->
            <div class="panel mb-5">
                <p class="secao-titulo mb-3">Atos mais liquidados</p>
                <div class="chart-box-xs mb-3"><canvas id="chartAtosTop"></canvas></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100" id="tabelaAtos">
                        <thead><tr>
                            <th>Código</th>
                            <th>Descrição</th>
                            <th>Atribuição</th>
                            <th class="text-end">Qtd.</th>
                            <th class="text-end">Emolumentos</th>
                            <th class="text-end">Valor total</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- ====================== Dependências JS ====================== -->
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
    const fmtBRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const fmtNum = new Intl.NumberFormat('pt-BR');
    const PALETA = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#9b59b6','#858796','#fd7e14'];

    let charts = {};
    let dtFunc = null, dtAtos = null, dtDep = null, dtRep = null;
    const DT_LANG = 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json';

    if (window.Chart) { Chart.defaults.font.family = 'Inter, system-ui, -apple-system, sans-serif'; }

    function cssVar(name){ return getComputedStyle(document.body).getPropertyValue(name).trim(); }
    function isDark(){ return document.body.classList.contains('dark-mode'); }

    function aplicarTemaCharts(){
        if (!window.Chart) return;
        Chart.defaults.color = cssVar('--rel-chart-tx') || '#4b5563';
        Chart.defaults.borderColor = cssVar('--rel-grid') || 'rgba(0,0,0,.07)';
    }
    function gridColor(){ return cssVar('--rel-grid') || 'rgba(0,0,0,.07)'; }

    function destruirChart(id){ if (charts[id]) { charts[id].destroy(); delete charts[id]; } }

    function lerFiltros(){
        return {
            preset: $('#fPreset').val(), data_inicio: $('#fDataIni').val(), data_fim: $('#fDataFim').val(),
            status: $('#fStatus').val(), atribuicao: $('#fAtribuicao').val(), funcionario: $('#fFuncionario').val(),
            ato: $('#fAto').val().trim(), granularidade: $('#fGran').val(),
            dep_usar_periodo: $('#depPeriodo').is(':checked') ? '1' : '0'
        };
    }

    $('#fPreset').on('change', function(){
        const custom = $(this).val() === 'custom';
        $('#boxDataIni, #boxDataFim').toggleClass('d-none', !custom);
    });

    $('#btnLimpar').on('click', function(){
        $('#fPreset').val('mes').trigger('change');
        $('#fStatus').val('todos'); $('#fAtribuicao').val('todas'); $('#fFuncionario').val('todos');
        $('#fAto').val(''); $('#fBase').val('emolumentos'); $('#fGran').val('dia');
        $('#depPeriodo').prop('checked', false);
        gerarRelatorio();
    });

    $('#fBase').on('change', function(){ if (window._ultimoResultado) renderizar(window._ultimoResultado); });
    $('#depPeriodo').on('change', gerarRelatorio);
    $('#btnGerar').on('click', gerarRelatorio);

    function gerarRelatorio(){
        $('#loadingOverlay').show(); $('#avisoSemDados').hide();
        $.getJSON('relatorio_os_dados.php', lerFiltros())
            .done(function(resp){
                if (!resp || !resp.ok){ alert('Erro ao carregar: ' + ((resp && resp.erro) || 'desconhecido')); return; }
                window._ultimoResultado = resp; renderizar(resp);
            })
            .fail(function(xhr){
                let msg = 'Falha na requisição.';
                try { msg = JSON.parse(xhr.responseText).erro || msg; } catch(e){}
                alert(msg);
            })
            .always(function(){ $('#loadingOverlay').hide(); });
    }

    function baseSel(){ return $('#fBase').val(); }
    function rotuloBase(){ return baseSel() === 'total' ? 'Valor total' : 'Emolumentos'; }
    function brData(ymd){ const p = ymd.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
    function escapeHtml(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function renderizar(resp){
        aplicarTemaCharts();
        const base = baseSel();

        let txt;
        if (resp.periodo.inicio && resp.periodo.fim) txt = 'de ' + brData(resp.periodo.inicio) + ' até ' + brData(resp.periodo.fim);
        else txt = 'todo o período (sem filtro de data)';
        $('#resumoFiltro').html('<i class="fa fa-calendar-o"></i> Exibindo ' + txt + ' • base: <strong>' + rotuloBase() + '</strong>');

        const k = resp.kpis;
        $('#kpiEmol').text(fmtBRL.format(+k.emolumentos));
        $('#kpiTotal').text(fmtBRL.format(+k.total));
        $('#kpiAtos').text(fmtNum.format(+k.qtd_atos));
        $('#kpiOS').text(fmtNum.format(+k.qtd_os));
        $('#kpiFunc').text(fmtNum.format(+k.qtd_funcionarios));
        $('#kpiRepasse').text(fmtBRL.format(+(resp.repasseCredor && resp.repasseCredor.totais ? resp.repasseCredor.totais.total : 0)));
        $('#avisoSemDados').toggle(+k.qtd_atos === 0);

        renderAtribuicao(resp.porAtribuicao, base);
        renderSerie(resp.serie, base);
        renderFuncionarios(resp.porFuncionario, base);
        renderAtos(resp.porAto, base);
        renderDeposito(resp.depositoPrevio);
        renderRepasse(resp.repasseCredor);
    }

    /* ----------------- Repasse a credores ----------------- */
    function renderRepasse(rp){
        const t = (rp && rp.totais) ? rp.totais : {total:0, qtd:0};
        const temRepasse = (+t.qtd > 0);

        // Só exibe o KPI e o painel se houver repasse no período filtrado
        $('#kpiRepasseCol').toggle(temRepasse);
        $('#painelRepasse').toggle(temRepasse);

        if (!temRepasse){
            if (dtRep){ dtRep.destroy(); dtRep = null; $('#tabelaRepasse tbody').empty(); }
            return;
        }

        $('#kpiRepasse').text(fmtBRL.format(+t.total || 0));
        $('#repTotal').text(fmtBRL.format(+t.total || 0));
        $('#repQtd').text(fmtNum.format(+t.qtd || 0));
        const formas = (rp.porForma || []).map(function(f){ return escapeHtml(f.forma) + ': ' + fmtBRL.format(+f.total); });
        $('#repFormas').html(formas.length ? formas.join(' &nbsp;•&nbsp; ') : '—');

        if (dtRep){ dtRep.destroy(); $('#tabelaRepasse tbody').empty(); }
        const tbody = $('#tabelaRepasse tbody');
        (rp.lista || []).forEach(function(d){
            tbody.append('<tr>' +
                '<td>#'+escapeHtml(d.ordem_de_servico_id)+'</td>' +
                '<td>'+escapeHtml(d.cliente)+'</td>' +
                '<td>'+escapeHtml(d.funcionario || '—')+'</td>' +
                '<td data-order="'+escapeHtml(d.data_repasse||'')+'">'+brDataHora(d.data_repasse)+'</td>' +
                '<td>'+escapeHtml(d.forma_repasse || '—')+'</td>' +
                '<td class="text-end text-money" data-order="'+(+d.total_repasse)+'">'+fmtBRL.format(+d.total_repasse)+'</td></tr>');
        });
        dtRep = $('#tabelaRepasse').DataTable({
            order:[[3,'desc']], pageLength:10, language:{ url:DT_LANG },
            dom:'Bfrtip', buttons:['copyHtml5','excelHtml5','pdfHtml5','print']
        });
    }

    /* ----------------- Depósito prévio ----------------- */
    function renderDeposito(dp){
        if (!dp) return;
        const t = dp.totais || {};
        $('#depSaldo').text(fmtBRL.format(+t.total_saldo || 0));
        $('#depQtd').text(fmtNum.format(+t.qtd_os || 0));
        $('#depDepositado').text(fmtBRL.format(+t.total_depositado || 0));
        $('#depConsumido').text(fmtBRL.format(+t.total_consumido || 0));
        $('#depRepassado').text(fmtBRL.format(+t.total_repassado || 0));
        $('#depDevolvido').text(fmtBRL.format(+t.total_devolvido || 0));

        if (dtDep){ dtDep.destroy(); $('#tabelaDeposito tbody').empty(); }
        const tbody = $('#tabelaDeposito tbody');
        (dp.lista || []).forEach(function(d){
            const dataBR = d.data_criacao ? brDataHora(d.data_criacao) : '—';
            tbody.append('<tr>' +
                '<td>#'+escapeHtml(d.id)+'</td>' +
                '<td>'+escapeHtml(d.cliente)+'</td>' +
                '<td>'+escapeHtml(d.cpf_cliente || '—')+'</td>' +
                '<td data-order="'+escapeHtml(d.data_criacao||'')+'">'+dataBR+'</td>' +
                '<td class="text-end text-money" data-order="'+(+d.depositado)+'">'+fmtBRL.format(+d.depositado)+'</td>' +
                '<td class="text-end text-money" data-order="'+(+d.consumido)+'">'+fmtBRL.format(+d.consumido)+'</td>' +
                '<td class="text-end text-money" data-order="'+(+(d.repassado||0))+'">'+fmtBRL.format(+(d.repassado||0))+'</td>' +
                '<td class="text-end text-money" data-order="'+(+d.devolvido)+'">'+fmtBRL.format(+d.devolvido)+'</td>' +
                '<td class="text-end text-money saldo-pos" data-order="'+(+d.saldo)+'">'+fmtBRL.format(+d.saldo)+'</td></tr>');
        });
        dtDep = $('#tabelaDeposito').DataTable({
            order:[[8,'desc']], pageLength:10, language:{ url:DT_LANG },
            dom:'Bfrtip', buttons:['copyHtml5','excelHtml5','pdfHtml5','print']
        });
    }

    function brDataHora(ts){
        // aceita 'YYYY-MM-DD HH:MM:SS' ou 'YYYY-MM-DD'
        const d = String(ts).split(' ')[0].split('-');
        return d.length === 3 ? (d[2]+'/'+d[1]+'/'+d[0]) : ts;
    }

    /* ----------------- Atribuição ----------------- */
    function renderAtribuicao(dados, base){
        const tbody = $('#tabelaAtribuicao tbody').empty();
        let sQ=0,sE=0,sT=0;
        dados.forEach(function(d,i){
            sQ+=+d.qtd; sE+=+d.emolumentos; sT+=+d.total;
            tbody.append('<tr>' +
                '<td><span class="dot" style="background:'+PALETA[i%PALETA.length]+'"></span>'+escapeHtml(d.atribuicao)+'</td>' +
                '<td class="text-end text-money">'+fmtNum.format(+d.qtd)+'</td>' +
                '<td class="text-end text-money">'+fmtBRL.format(+d.emolumentos)+'</td>' +
                '<td class="text-end text-money">'+fmtBRL.format(+d.total)+'</td></tr>');
        });
        $('#atribFootQtd').text(fmtNum.format(sQ));
        $('#atribFootEmol').text(fmtBRL.format(sE));
        $('#atribFootTotal').text(fmtBRL.format(sT));

        destruirChart('atribuicao');
        charts.atribuicao = new Chart(document.getElementById('chartAtribuicao'), {
            type:'doughnut',
            data:{ labels: dados.map(d=>d.atribuicao),
                   datasets:[{ data: dados.map(d=>+d[base]), backgroundColor: dados.map((d,i)=>PALETA[i%PALETA.length]),
                               borderColor: cssVar('--rel-panel'), borderWidth:2 }] },
            options:{ responsive:true, maintainAspectRatio:false, cutout:'62%',
                plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, padding:12, font:{size:11} } },
                          tooltip:{ callbacks:{ label:c=>c.label+': '+fmtBRL.format(c.parsed) } } } }
        });
    }

    /* ----------------- Série temporal ----------------- */
    function renderSerie(serie, base){
        destruirChart('serie');
        charts.serie = new Chart(document.getElementById('chartSerie'), {
            data:{ labels: serie.map(s=>s.periodo),
                datasets:[
                    { type:'bar', label:rotuloBase(), data:serie.map(s=>+s[base]),
                      backgroundColor:'rgba(78,115,223,.85)', borderRadius:6, yAxisID:'y', maxBarThickness:42 },
                    { type:'line', label:'Atos', data:serie.map(s=>+s.qtd),
                      borderColor:'#e74a3b', backgroundColor:'#e74a3b', tension:.35, borderWidth:2,
                      pointRadius:3, yAxisID:'y1' }
                ]},
            options:{ responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false},
                plugins:{ legend:{position:'bottom'},
                    tooltip:{ callbacks:{ label:c=> c.dataset.label==='Atos'
                        ? 'Atos: '+fmtNum.format(c.parsed.y) : c.dataset.label+': '+fmtBRL.format(c.parsed.y) } } },
                scales:{
                    x:{ grid:{ color:gridColor() } },
                    y:{ position:'left', grid:{ color:gridColor() }, ticks:{ callback:v=>fmtBRL.format(v) } },
                    y1:{ position:'right', grid:{ drawOnChartArea:false }, ticks:{ callback:v=>fmtNum.format(v) } }
                } }
        });
    }

    /* ----------------- Funcionários ----------------- */
    function renderFuncionarios(dados, base){
        if (dtFunc){ dtFunc.destroy(); $('#tabelaFuncionarios tbody').empty(); }
        const tbody = $('#tabelaFuncionarios tbody');
        dados.forEach(function(d){
            tbody.append('<tr>' +
                '<td>'+escapeHtml(d.nome)+'</td>' +
                '<td class="text-end text-money" data-order="'+(+d.qtd)+'">'+fmtNum.format(+d.qtd)+'</td>' +
                '<td class="text-end text-money" data-order="'+(+d.emolumentos)+'">'+fmtBRL.format(+d.emolumentos)+'</td>' +
                '<td class="text-end text-money" data-order="'+(+d.total)+'">'+fmtBRL.format(+d.total)+'</td></tr>');
        });
        dtFunc = $('#tabelaFuncionarios').DataTable({
            order:[[2,'desc']], pageLength:10, language:{ url:DT_LANG },
            dom:'Bfrtip', buttons:['copyHtml5','excelHtml5','pdfHtml5','print']
        });

        const top = dados.slice(0,10);
        destruirChart('funcFat');
        charts.funcFat = new Chart(document.getElementById('chartFuncFat'), {
            type:'bar',
            data:{ labels: top.map(d=>d.nome),
                   datasets:[{ label:rotuloBase(), data: top.map(d=>+d[base]), backgroundColor:'#4e73df', borderRadius:5 }] },
            options: barOpts(true)
        });

        const topQ = [...dados].sort((a,b)=>b.qtd-a.qtd).slice(0,10);
        destruirChart('funcQtd');
        charts.funcQtd = new Chart(document.getElementById('chartFuncQtd'), {
            type:'bar',
            data:{ labels: topQ.map(d=>d.nome),
                   datasets:[{ label:'Atos', data: topQ.map(d=>+d.qtd), backgroundColor:'#1cc88a', borderRadius:5 }] },
            options: barOpts(false)
        });
    }

    function barOpts(moeda){
        return { indexAxis:'y', responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{display:false},
                tooltip:{ callbacks:{ label:c=> moeda ? fmtBRL.format(c.parsed.x) : fmtNum.format(c.parsed.x) } } },
            scales:{ x:{ grid:{ color:gridColor() }, ticks:{ callback:v=> moeda ? fmtBRL.format(v) : fmtNum.format(v) } },
                     y:{ grid:{ display:false } } } };
    }

    /* ----------------- Atos mais liquidados ----------------- */
    function renderAtos(dados, base){
        if (dtAtos){ dtAtos.destroy(); $('#tabelaAtos tbody').empty(); }
        const tbody = $('#tabelaAtos tbody');
        dados.forEach(function(d){
            tbody.append('<tr>' +
                '<td>'+escapeHtml(d.ato)+'</td>' +
                '<td>'+escapeHtml(d.descricao)+'</td>' +
                '<td>'+escapeHtml(d.atribuicao)+'</td>' +
                '<td class="text-end text-money" data-order="'+(+d.qtd)+'">'+fmtNum.format(+d.qtd)+'</td>' +
                '<td class="text-end text-money" data-order="'+(+d.emolumentos)+'">'+fmtBRL.format(+d.emolumentos)+'</td>' +
                '<td class="text-end text-money" data-order="'+(+d.total)+'">'+fmtBRL.format(+d.total)+'</td></tr>');
        });
        dtAtos = $('#tabelaAtos').DataTable({
            order:[[3,'desc']], pageLength:10, language:{ url:DT_LANG },
            dom:'Bfrtip', buttons:['copyHtml5','excelHtml5','pdfHtml5','print']
        });

        // Top 8 atos por quantidade (gráfico)
        const top = [...dados].sort((a,b)=>b.qtd-a.qtd).slice(0,8);
        destruirChart('atosTop');
        charts.atosTop = new Chart(document.getElementById('chartAtosTop'), {
            type:'bar',
            data:{ labels: top.map(d=>d.ato),
                   datasets:[{ label:'Qtd. liquidada', data: top.map(d=>+d.qtd),
                               backgroundColor: top.map((d,i)=>PALETA[i%PALETA.length]), borderRadius:5 }] },
            options:{ responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{display:false},
                    tooltip:{ callbacks:{ title:items=>{ const i=items[0].dataIndex; return top[i].ato+' — '+top[i].descricao; },
                                          label:c=> 'Qtd: '+fmtNum.format(c.parsed.y) } } },
                scales:{ x:{ grid:{display:false} }, y:{ grid:{ color:gridColor() }, ticks:{ callback:v=>fmtNum.format(v) } } } }
        });
    }

    // Re-tematiza ao alternar dark/light (toggleMode do menu troca a classe do body)
    let _reTemaTimer = null;
    new MutationObserver(function(){
        clearTimeout(_reTemaTimer);
        _reTemaTimer = setTimeout(function(){ if (window._ultimoResultado) renderizar(window._ultimoResultado); }, 120);
    }).observe(document.body, { attributes:true, attributeFilter:['class'] });

    $(document).ready(gerarRelatorio);
    </script>
</body>
</html>
