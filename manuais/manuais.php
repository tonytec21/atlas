<?php  
require_once 'conexao_bd.php';

/* ------------------------------------------------------------------
   Helpers
-------------------------------------------------------------------*/
if (!function_exists('sanitizar')) {
    function sanitizar($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

/* ------------------------------------------------------------------
   Parâmetros
-------------------------------------------------------------------*/
$alertStatus = isset($_GET['status']) ? $_GET['status'] : '';

/* ------------------------------------------------------------------
   Consultas
-------------------------------------------------------------------*/
try {  
    $total_manuais       = $conexao->query("SELECT COUNT(*) FROM manuais")->fetchColumn();  
    $total_visualizacoes = $conexao->query("SELECT SUM(visualizacoes) FROM manuais")->fetchColumn() ?: 0;  
    $recentes            = $conexao->query("SELECT COUNT(*) FROM manuais WHERE data_criacao > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();  
    $total_downloads     = $conexao->query("SELECT SUM(downloads) FROM manuais")->fetchColumn() ?: 0;  
      
    // Categorias
    $stmt = $conexao->query("SELECT id, nome FROM categorias ORDER BY nome");  
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);  
      
    // Manuais + extras
    $query = "
        SELECT m.*, 
               c.nome AS categoria_nome, 
               u.nome AS autor_nome,
               (SELECT COUNT(*) FROM passos WHERE manual_id = m.id) AS total_passos
        FROM manuais m
        LEFT JOIN categorias c ON m.categoria_id = c.id
        LEFT JOIN usuarios u   ON m.autor_id = u.id
        ORDER BY m.data_criacao DESC
    ";
    $stmt = $conexao->query($query);
    $manuais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {  
    error_log('Erro ao carregar manuais: '.$e->getMessage());  
    $erro = 'Ocorreu um erro ao carregar os manuais.';  
}  
?>  
<!DOCTYPE html>  
<html lang="pt-BR" data-theme="light">
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Lista de Manuais</title>  

    <link rel="icon" href="img/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="img/manuflow-mark-32.png" sizes="32x32">
    <link rel="apple-touch-icon" href="img/manuflow-mark-180.png" sizes="180x180">
    <link rel="icon" type="image/png" href="img/manuflow-mark-512.png" sizes="512x512">

    <!-- Bootstrap CSS -->  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">  

    <!-- Font Awesome -->  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">  

    <!-- DataTables -->  
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <!-- DataTables Responsive -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <style>
        /* =========================================================
         *  Tema Light / Dark  —  UI moderna e responsiva
         *  Alterna com <html data-theme="light|dark">
         * =======================================================*/
        :root {
            /* cores base (light) */
            --bg:            #f6f7fb;
            --surface:       #ffffff;
            --surface-2:     #f1f3f9;
            --text:          #0f172a;
            --muted:         #6b7280;
            --border:        #e5e7eb;
            --primary:       #3b82f6;
            --primary-600:   #2563eb;
            --accent:        #22c55e;
            --warning:       #f59e0b;
            --danger:        #ef4444;
            --info:          #06b6d4;
            --shadow:        0 10px 30px rgba(2,6,23,.07);
            --radius:        14px;

            --sidebar-width: 260px;
            --topbar-h:      64px;
        }
        html[data-theme="dark"] {
            --bg:            #0b1220;
            --surface:       #0f172a;
            --surface-2:     #0b1220;
            --text:          #e5e7eb;
            --muted:         #9aa4b2;
            --border:        #1f2937;
            --primary:       #60a5fa;
            --primary-600:   #3b82f6;
            --accent:        #34d399;
            --warning:       #fbbf24;
            --danger:        #f87171;
            --info:          #22d3ee;
            --shadow:        0 12px 40px rgba(2,6,23,.45);
        }
        * { box-sizing: border-box; }
        body {
            background: radial-gradient(1200px 800px at 10% -10%, rgba(59,130,246,.15), transparent 40%),
                        radial-gradient(1000px 700px at 110% 10%, rgba(34,211,238,.12), transparent 42%),
                        var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            padding-bottom: 24px;
        }

        /* ---------------- Topbar ---------------- */
        .topbar {
            position: fixed; inset: 0 0 auto 0; height: var(--topbar-h);
            background: linear-gradient(180deg, rgba(255,255,255,.6), rgba(255,255,255,.2));
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 12px;
            padding: 0 16px;
            z-index: 101;
        }
        html[data-theme="dark"] .topbar {
            background: linear-gradient(180deg, rgba(15,23,42,.7), rgba(15,23,42,.35));
        }
        .menu-toggle {
            width: 42px; height: 42px; display: grid; place-items: center;
            border-radius: 50%; cursor: pointer; color: var(--text);
            transition: .2s; border: 1px solid var(--border);
            background: var(--surface);
        }
        .menu-toggle:hover { transform: translateY(-1px); }
        .brand {
            display: flex; align-items: center; gap: 12px;
        }
        .brand .logo {
            width: 36px; height: 36px; border-radius: 10px;
            display: grid; place-items: center; color: #fff;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            box-shadow: var(--shadow);
            font-weight: 700;
        }
        .brand h4 { margin: 0; letter-spacing: .2px; }

        .topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; }
        .btn-ghost {
            display: inline-flex; align-items: center; gap: 8px;
            border: 1px solid var(--border); background: var(--surface);
            padding: 8px 12px; border-radius: 999px; color: var(--text);
        }
        .avatar-btn {
            width: 42px; height: 42px; border-radius: 50%;
            border: 1px solid var(--border); background: var(--surface);
            display: grid; place-items: center; color: var(--text);
        }

        /* ---------------- Main ---------------- */
        .main {
            /* margin-left: var(--sidebar-width); */
            padding: calc(var(--topbar-h) + 24px) 28px 24px;
            transition: margin-left .25s ease;
        }
        /* .main.expanded { margin-left: 0; } */

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 22px;
        }

        /* ---------------- Stat Cards ---------------- */
        .stat-card {
            display: grid; grid-template-columns: 56px auto; gap: 12px; align-items: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px; padding: 16px 18px; box-shadow: var(--shadow);
            height: 100%;
        }
        .stat-card .icon {
            width: 56px; height: 56px; border-radius: 14px; display: grid; place-items: center;
            color: #fff; font-size: 1.4rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
        }
        .stat-card .content h3 { margin: 0; font-weight: 800; letter-spacing: .2px; }
        .stat-card .content p { margin: 2px 0 0; color: var(--muted); }

        /* ---------------- Filtros ---------------- */
        .filter-bar {
            background: linear-gradient(180deg, rgba(59,130,246,.06), transparent 100%), var(--surface);
            border: 1px solid var(--border); border-radius: 16px; padding: 16px;
        }
        .filter-bar .form-label { font-weight: 600; color: var(--muted); }
        .form-select, .form-control {
            border-radius: 12px; border: 1px solid var(--border); background: var(--surface); color: var(--text);
        }

        /* ---------------- Tabela ---------------- */
        .table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 12px; }
        table.dataTable { color: var(--text); }
        table.dataTable thead th {
            white-space: nowrap; font-weight: 700;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, var(--surface-2), var(--surface));
            color: var(--text);
        }
        table.dataTable tbody td { vertical-align: middle; border-top: 1px solid var(--border); color: var(--text); }

        /* Bootstrap table vars para respeitar o tema */
        .table{
            --bs-table-color: var(--text);
            --bs-table-bg: var(--surface);
            --bs-table-border-color: var(--border);
            --bs-table-striped-bg: rgba(2,6,23,.04);
            --bs-table-striped-color: var(--text);
            --bs-table-hover-bg: rgba(2,6,23,.06);
            --bs-table-hover-color: var(--text);
        }
        html[data-theme="dark"] .table{
            --bs-table-striped-bg: rgba(255,255,255,.03);
            --bs-table-hover-bg: rgba(255,255,255,.06);
        }

        /* CAPA (DESKTOP) — quadrada forçada e destacada */
        .cover-64{
            width: 80px;
            height: 80px;
            aspect-ratio: 1/1;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid var(--border);
            background: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 80px;
            box-shadow: 0 6px 16px rgba(2,6,23,.08);
        }
        .cover-64 img{ width:100%; height:100%; object-fit: cover; display:block; }

        /* Ações na tabela */
        .action-btns .btn { width: auto; height: auto; border-radius: 10px; display: inline-grid; place-items: center; margin-right: 0px; }
        .action-btns .btn:last-child { margin-right: 0; }

        .status-indicator { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .status-active   { background: var(--accent); }
        .status-draft    { background: var(--warning); }
        .status-archived { background: var(--muted); }

        /* DataTables UI (inputs/paginação) usando variáveis de tema */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate{ color: var(--text); }
        .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_length label{ color: var(--text); }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select{
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 6px 10px;
            outline: none;
        }
        .dataTables_wrapper .dataTables_filter input::placeholder{ color: var(--muted); opacity:.9; }

        .dataTables_wrapper .dataTables_paginate .paginate_button{
            border:1px solid var(--border) !important;
            background: var(--surface) !important;
            color: var(--text) !important;
            border-radius:10px !important;
            margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover{
            background: var(--surface-2) !important;
            color: var(--text) !important;
            border-color: var(--border) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover{
            background: linear-gradient(135deg, var(--primary), var(--primary-600)) !important;
            color:#fff !important;
            border-color: var(--primary-600) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover{
            color: var(--muted) !important;
            background: var(--surface) !important;
            border-color: var(--border) !important;
        }
        .dataTables_wrapper .pagination .page-link{
            background: var(--surface);
            color: var(--text);
            border:1px solid var(--border);
        }
        .dataTables_wrapper .pagination .page-link:hover{
            background: var(--surface-2);
            color: var(--text);
        }
        .dataTables_wrapper .pagination .page-item.active .page-link{
            background: linear-gradient(135deg, var(--primary), var(--primary-600));
            color:#fff;
            border-color: var(--primary-600);
        }
        .dataTables_wrapper .dataTables_processing{
            background: var(--surface);
            color: var(--text);
            border:1px solid var(--border);
            box-shadow: var(--shadow);
            border-radius: 10px;
        }

        /* ---------------- Cards (mobile) ---------------- */
        #cards-container{ margin-top:12px; }
        .cards-grid{
            display:grid;
            grid-template-columns: 1fr;
            gap:12px;
        }
        .manual-card{
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:16px;
            box-shadow:var(--shadow);
            padding:16px;
            color: var(--text);
        }
        .manual-card .header{
            display:flex; gap:12px; align-items:flex-start;
        }
        .manual-card .cover{
            width:84px; height:84px; aspect-ratio:1/1;
            border-radius:14px; overflow:hidden; border:1px solid var(--border);
            background:var(--surface-2);
            display:flex;align-items:center;justify-content:center;
            box-shadow: 0 6px 16px rgba(2,6,23,.08);
            flex: 0 0 84px;
        }
        .manual-card .cover img{ width:100%; height:100%; object-fit:cover; display:block; }
        .manual-card .title{ font-weight:800; margin:0; line-height:1.25; }
        .manual-card .meta{ color:var(--muted); font-size:.92rem; }
        .manual-card .badge-soft{
            background: rgba(59,130,246,.12);
            color: var(--primary-600);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 4px 8px;
            font-size: .8rem;
        }
        .badge-row{ display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
        .status-row{ margin-top:10px; font-weight:600; display:flex; align-items:center; gap:6px; }
        .bottom-row{ display:flex; align-items:center; justify-content: flex-end; margin-top:8px; }
        .view-chip{
            display:inline-flex; align-items:center; gap:6px;
            background: var(--surface-2);
            border:1px solid var(--border);
            color: var(--muted);
            padding: 4px 10px;
            border-radius: 999px;
            font-weight:600;
        }
        .manual-card .footer{ display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
        .manual-card .btn{ border-radius:10px; }

        /* Mostrar Tabela no desktop, Cards no mobile */
        @media (min-width: 992px){
            .show-desktop{ display:block !important; }
            .show-mobile{ display:none !important; }
        }
        @media (max-width: 991.98px){
            .show-desktop{ display:none !important; }
            .show-mobile{ display:block !important; }
        }

        /* ---------------- Misc ---------------- */
        .badge-soft {
            background: rgba(59,130,246,.12);
            color: var(--primary-600);
            border: 1px solid var(--border);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-600) 100%);
            border: none;
        }
        .btn-primary:hover { filter: brightness(.95); }
        .topbar .dropdown-menu {
            border-radius: 12px; border: 1px solid var(--border); background: var(--surface);
        }

        .brand-logo{ height:50px; display:block; }
        html[data-theme="dark"] .brand-logo{ filter: invert(1) hue-rotate(180deg) brightness(1.1); }

    </style>
</head>  
<body>  
    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
            <img src="img/manuflow-wordmark.svg" alt="ManuFlow — Sistema de Manuais" class="brand-logo" height="28">
        </div>

        <div class="topbar-actions">
            <button class="btn-ghost" id="themeToggle" type="button" aria-label="Alternar tema">
                <i class="fa-solid fa-moon me-1" id="themeIcon"></i>
                <span class="d-none d-md-inline" id="themeLabel">Dark</span>
            </button>
        </div>
    </div>


    <!-- Main -->
    <main class="main" id="main-content">
        <!-- Alertas -->
        <?php if ($alertStatus === 'deleted'): ?>  
            <div class="alert alert-success alert-dismissible fade show mb-4 panel" role="alert">  
                <i class="fa-solid fa-check-circle me-2"></i>Manual excluído com sucesso!  
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
            </div>  
        <?php endif; ?>  
        <?php if (isset($erro)): ?>  
            <div class="alert alert-danger alert-dismissible fade show mb-4 panel" role="alert">  
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $erro; ?>  
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
            </div>  
        <?php endif; ?>  

        <!-- Estatísticas -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="icon"><i class="fa-solid fa-book"></i></div>
                    <div class="content">
                        <h3><?php echo number_format((float)$total_manuais); ?></h3>
                        <p>Total de Manuais</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="icon" style="background: linear-gradient(135deg, var(--info), var(--primary));"><i class="fa-solid fa-eye"></i></div>
                    <div class="content">
                        <h3><?php echo number_format((float)$total_visualizacoes); ?></h3>
                        <p>Visualizações</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="icon" style="background: linear-gradient(135deg, var(--warning), var(--primary));"><i class="fa-solid fa-calendar-plus"></i></div>
                    <div class="content">
                        <h3><?php echo number_format((float)$recentes); ?></h3>
                        <p>Manuais Recentes (30d)</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="icon" style="background: linear-gradient(135deg, var(--accent), var(--primary));"><i class="fa-solid fa-download"></i></div>
                    <div class="content">
                        <h3><?php echo number_format((float)$total_downloads); ?></h3>
                        <p>Downloads</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header (somente visualização: sem CTA de criar) -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
            <div>
                <h1 class="mb-0 fw-bold">Manuais</h1>
                <small class="text-muted">Navegue e visualize os manuais disponíveis.</small>
            </div>
        </div>

        <!-- Filtros -->
        <section class="filter-bar mb-4">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label for="category-filter" class="form-label">Categoria</label>
                    <select class="form-select" id="category-filter">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo sanitizar($categoria['nome']); ?>">
                                <?php echo sanitizar($categoria['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label for="date-filter" class="form-label">Data</label>
                    <select class="form-select" id="date-filter">
                        <option value="">Todas as datas</option>
                        <option value="today">Hoje</option>
                        <option value="yesterday">Ontem</option>
                        <option value="week">Esta semana</option>
                        <option value="month">Este mês</option>
                        <option value="year">Este ano</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label for="status-filter" class="form-label">Status</label>
                    <select class="form-select" id="status-filter">
                        <option value="">Todos os status</option>
                        <option value="Ativo">Ativo</option>
                        <option value="Rascunho">Rascunho</option>
                        <option value="Arquivado">Arquivado</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Tabela (desktop) -->
        <div class="table-wrap show-desktop">
            <div class="table-responsive">
                <table id="manuais-table" class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th class="text-nowrap">Título</th>
                            <th>Categoria</th>
                            <th>Versão</th>
                            <th>Autor</th>
                            <th>Data de Criação</th>
                            <th>Status</th>
                            <th>Visualizações</th>
                            <th class="text-center">Visualizar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manuais as $manual): ?>
                            <?php
                                $rowStatus   = 'Ativo';
                                $statusClass = 'status-active';
                                $data_criacao = new DateTime($manual['data_criacao']);
                                $viewsFmt     = number_format((float)$manual['visualizacoes']);
                            ?>
                            <tr class="manual-row"
                                data-id="<?php echo (int)$manual['id']; ?>"
                                data-title="<?php echo sanitizar($manual['titulo']); ?>"
                                data-category="<?php echo sanitizar($manual['categoria_nome'] ?? ''); ?>"
                                data-version="<?php echo sanitizar($manual['versao']); ?>"
                                data-author="<?php echo sanitizar($manual['autor_nome'] ?? 'N/A'); ?>"
                                data-created="<?php echo $data_criacao->format('d/m/Y H:i'); ?>"
                                data-status="<?php echo $rowStatus; ?>"
                                data-status-class="<?php echo $statusClass; ?>"
                                data-views="<?php echo $viewsFmt; ?>"
                                data-img="<?php echo !empty($manual['imagem_capa']) ? sanitizar($manual['imagem_capa']) : ''; ?>"
                                data-passos="<?php echo (int)$manual['total_passos']; ?>"
                            >
                                <td><?php echo (int)$manual['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="cover-64 me-3">
                                            <?php if (!empty($manual['imagem_capa'])): ?>
                                                <img src="<?php echo sanitizar($manual['imagem_capa']); ?>" alt="Capa">
                                            <?php else: ?>
                                                <i class="fa-solid fa-book text-secondary"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong class="d-block"><?php echo sanitizar($manual['titulo']); ?></strong>
                                            <small class="text-muted"><?php echo (int)$manual['total_passos']; ?> passos</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($manual['categoria_nome'])): ?>
                                        <span class="badge badge-soft rounded-pill px-3 py-2">
                                            <i class="fa-solid fa-tag me-1"></i><?php echo sanitizar($manual['categoria_nome']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary rounded-pill px-3 py-2">Sem categoria</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitizar($manual['versao']); ?></td>
                                <td><?php echo sanitizar($manual['autor_nome'] ?? 'N/A'); ?></td>
                                <td data-order="<?php echo $data_criacao->format('Y-m-d H:i:s'); ?>">
                                    <?php echo $data_criacao->format('d/m/Y H:i'); ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="status-indicator <?php echo $statusClass; ?>"></span>
                                        <?php echo $rowStatus; ?>
                                    </div>
                                </td>
                                <td><?php echo $viewsFmt; ?></td>
                                <td class="action-btns text-center">
                                    <a href="view-manual.php?id=<?php echo (int)$manual['id']; ?>" class="btn btn-outline-info btn-sm" title="Visualizar">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Versão</th>
                            <th>Autor</th>
                            <th>Data de Criação</th>
                            <th>Status</th>
                            <th>Visualizações</th>
                            <th>Visualizar</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Cards (mobile) -->
        <section id="cards-container" class="show-mobile">
            <div class="cards-grid" id="cards-grid"></div>
        </section>
    </main>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>  

    <!-- Bootstrap JS Bundle -->  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>  

    <!-- DataTables -->  
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>  
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>  
    <!-- DataTables Responsive -->
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <script>
        (function(){
            /* ---------------- Tema (Dark / Light) ---------------- */
            const htmlEl = document.documentElement;
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon   = document.getElementById('themeIcon');
            const themeLabel  = document.getElementById('themeLabel');

            function applyTheme(mode){
                htmlEl.setAttribute('data-theme', mode);
                if (mode === 'dark') {
                    themeIcon.classList.remove('fa-moon'); themeIcon.classList.add('fa-sun');
                    if (themeLabel) themeLabel.textContent = 'Light';
                } else {
                    themeIcon.classList.remove('fa-sun'); themeIcon.classList.add('fa-moon');
                    if (themeLabel) themeLabel.textContent = 'Dark';
                }
                localStorage.setItem('theme', mode);
            }
            // Preferência salva
            const saved = localStorage.getItem('theme');
            if (saved) applyTheme(saved);
            else {
                // Preferência do SO
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                applyTheme(prefersDark ? 'dark' : 'light');
            }
            themeToggle.addEventListener('click', () => {
                const current = htmlEl.getAttribute('data-theme');
                applyTheme(current === 'dark' ? 'light' : 'dark');
            });

            /* ---------------- Tradução DataTables (inline, evita CORS) ---------------- */
            const DT_LANG_PTBR = {
                "decimal": ",",
                "thousands": ".",
                "processing": "Processando...",
                "lengthMenu": "Mostrar _MENU_ registros",
                "zeroRecords": "Nenhum registro encontrado",
                "emptyTable": "Nenhum registro disponível",
                "info": "Mostrando _START_ até _END_ de _TOTAL_ registros",
                "infoEmpty": "Mostrando 0 até 0 de 0 registros",
                "infoFiltered": "(filtrado de _MAX_ no total)",
                "search": "Buscar:",
                "loadingRecords": "Carregando...",
                "paginate": {
                    "first": "Primeiro",
                    "last": "Último",
                    "next": "Próximo",
                    "previous": "Anterior"
                },
                "aria": {
                    "sortAscending":  ": ativar para ordenar a coluna em ordem crescente",
                    "sortDescending": ": ativar para ordenar a coluna em ordem decrescente"
                }
            };

            /* ---------------- DataTables ---------------- */
            let dt = $('#manuais-table').DataTable({
                language: DT_LANG_PTBR,
                responsive: true,
                order: [[5, 'desc']], // Data de criação
                columnDefs: [
                    { targets: [0], visible: false }, // Oculta ID
                    { targets: [8], orderable: false } // Botão Visualizar
                ]
            });

            // Renderizar cards quando a tabela for desenhada (respeita busca/ordem/filtros)
            dt.on('draw', renderCardsFromTable);

            /* ---------------- Filtros: Categoria e Status ---------------- */
            $('#category-filter').on('change', function(){
                dt.column(2).search($(this).val()).draw();
            });
            $('#status-filter').on('change', function(){
                dt.column(6).search($(this).val()).draw();
            });

            /* ---------------- Filtro por Data (custom) ---------------- */
            function parseBRDate(str){
                if (!str) return null;
                const [datePart, timePart='00:00'] = str.split(' ');
                const [d,m,y] = datePart.split('/');
                const [hh,ii] = timePart.split(':');
                return new Date(parseInt(y), parseInt(m)-1, parseInt(d), parseInt(hh), parseInt(ii));
            }
            $.fn.dataTable.ext.search.push(function(settings, data){
                if (settings.nTable !== document.getElementById('manuais-table')) return true;
                const filter = $('#date-filter').val();
                if (!filter) return true;

                const dateStr = data[5]; // coluna "Data de Criação"
                const rowDate = parseBRDate(dateStr);
                if (!rowDate) return true;

                const now = new Date();
                const start = new Date(now); start.setHours(0,0,0,0);
                const end   = new Date(now); end.setHours(23,59,59,999);

                let from = null, to = null;

                switch(filter){
                    case 'today':
                        from = start; to = end; break;
                    case 'yesterday':
                        from = new Date(start); from.setDate(from.getDate()-1);
                        to   = new Date(end);   to.setDate(to.getDate()-1);
                        break;
                    case 'week': {
                        const day = start.getDay(); // 0 dom
                        const diffToMon = (day === 0 ? -6 : 1 - day);
                        from = new Date(start); from.setDate(from.getDate()+diffToMon);
                        to   = new Date(end);
                        break;
                    }
                    case 'month':
                        from = new Date(start.getFullYear(), start.getMonth(), 1);
                        to   = new Date(end.getFullYear(), end.getMonth()+1, 0, 23,59,59,999);
                        break;
                    case 'year':
                        from = new Date(start.getFullYear(), 0, 1);
                        to   = new Date(end.getFullYear(), 11, 31, 23,59,59,999);
                        break;
                }

                if (from && to) {
                    return rowDate >= from && rowDate <= to;
                }
                return true;
            });
            $('#date-filter').on('change', function(){ dt.draw(); });

            /* ---------------- Cards (mobile): renderização ---------------- */
            function escapeHtml(str){
                return (str ?? '').toString()
                    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
            }

            function renderCardsFromTable(){
                const grid = document.getElementById('cards-grid');
                if (!grid) return;
                grid.innerHTML = '';

                dt.rows({ search: 'applied' }).every(function(){
                    const rowNode = this.node();
                    const d = rowNode?.dataset || {};

                    // Fallback defensivo caso dataset não exista (mantém compatibilidade)
                    let id        = d.id || '';
                    let title     = d.title || '';
                    let categoria = d.category || '';
                    let versao    = d.version || '';
                    let autor     = d.author || '';
                    let criado    = d.created || '';
                    let statusTxt = d.status || 'Ativo';
                    let statusCls = d.statusClass || 'status-active';
                    let views     = d.views || '0';
                    let imgSrc    = d.img || '';

                    // Se por algum motivo faltar título no dataset, tenta extrair da célula 1
                    if (!title) {
                        const cells = rowNode.querySelectorAll('td');
                        const titleCell = cells[1];
                        title = titleCell?.querySelector('strong')?.textContent?.trim() || '';
                        if (!imgSrc) imgSrc = titleCell?.querySelector('img')?.getAttribute('src') || '';
                    }

                    const card = document.createElement('div');
                    card.className = 'manual-card';

                    card.innerHTML = `
                        <div class="header">
                            <div class="cover">
                                ${imgSrc ? `<img src="${escapeHtml(imgSrc)}" alt="Capa">`
                                         : `<i class="fa-solid fa-book text-secondary"></i>`}
                            </div>
                            <div class="w-100">
                                <h6 class="title">${escapeHtml(title)}</h6>
                                <div class="meta mt-1">
                                    <span><i class="fa-solid fa-user-pen me-1"></i>${escapeHtml(autor || 'N/A')}</span>
                                    &nbsp;&middot;&nbsp;
                                    <span><i class="fa-regular fa-calendar-plus me-1"></i>${escapeHtml(criado || '-')}</span>
                                </div>
                                <div class="badge-row">
                                    ${categoria ? `<span class="badge-soft"><i class="fa-solid fa-tag me-1"></i>${escapeHtml(categoria)}</span>` : ''}
                                    ${versao ? `<span class="badge-soft"><i class="fa-solid fa-code-branch me-1"></i>v${escapeHtml(versao)}</span>` : ''}
                                    ${id ? `<span class="badge-soft">#${escapeHtml(id)}</span>` : ''}
                                </div>
                            </div>
                        </div>

                        <div class="status-row">
                            <span class="status-indicator ${escapeHtml(statusCls)}"></span>${escapeHtml(statusTxt)}
                        </div>

                        <div class="bottom-row">
                            <span class="view-chip"><i class="fa-regular fa-eye"></i> ${escapeHtml(views)}</span>
                        </div>

                        <div class="footer">
                            <a href="view-manual.php?id=${encodeURIComponent(id)}" class="btn btn-outline-info btn-sm">
                                <i class="fa-solid fa-eye me-1"></i> Visualizar
                            </a>
                        </div>
                    `;
                    grid.appendChild(card);
                });

                if (!grid.children.length){
                    const empty = document.createElement('div');
                    empty.className = 'manual-card';
                    empty.innerHTML = `<div class="meta">Nenhum manual encontrado.</div>`;
                    grid.appendChild(empty);
                }
            }

            // Primeira renderização de cards
            renderCardsFromTable();
        })();
    </script>
</body>
</html>
