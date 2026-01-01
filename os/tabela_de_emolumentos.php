<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  
?>  
<!DOCTYPE html>  
<html lang="pt-br">  

<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Tabela de Emolumentos - Atlas</title>  

    <!-- Core CSS -->
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../style/css/style.css">  
    <link rel="icon" href="../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">  

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">  
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">  
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">

    <style>  
        /* ===================== DESIGN SYSTEM ===================== */
        :root {  
            --brand-primary: #4f46e5;
            --brand-secondary: #818cf8;
            --brand-success: #10b981;
            --brand-warning: #f59e0b;
            --brand-error: #ef4444;
            --brand-info: #06b6d4;
            --brand-purple: #8b5cf6;
            --brand-pink: #ec4899;
            --brand-orange: #f97316;

            --text-primary: #111827;
            --text-secondary: #4b5563;
            --text-tertiary: #9ca3af;

            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --bg-tertiary: #f3f4f6;
            --bg-elevated: #ffffff;

            --border-primary: #e5e7eb;
            --border-secondary: #d1d5db;

            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-info: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            --gradient-secondary: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-purple: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);

            --shadow: 0 1px 3px rgba(16,24,40,.1), 0 1px 2px rgba(16,24,40,.06);
            --shadow-md: 0 4px 6px -1px rgba(16,24,40,.1), 0 2px 4px -1px rgba(16,24,40,.06);
            --shadow-lg: 0 10px 15px -3px rgba(16,24,40,.1), 0 4px 6px -2px rgba(16,24,40,.05);
            --shadow-xl: 0 20px 25px -5px rgba(16,24,40,.1), 0 10px 10px -5px rgba(16,24,40,.04);

            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 18px;

            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
        }  

        .dark-mode {  
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --text-tertiary: #9ca3af;
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --bg-tertiary: #374151;
            --bg-elevated: #1f2937;
            --border-primary: #374151;
            --border-secondary: #4b5563;
        }  

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {  
            font-family: 'Inter', sans-serif;  
            background-color: var(--bg-secondary);  
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }  

        .main-content {
            padding: var(--space-lg) 0;
        }

        .container {  
            max-width: 1600px;
        }

        /* ===================== PAGE HERO ===================== */
        .page-hero {
            background: var(--gradient-primary);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            margin-bottom: var(--space-xl);
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .page-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .page-hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: 10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .title-row {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .title-icon {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .title-icon i {  
            font-size: 32px;  
            color: var(--text-primary);  
        }  

        .dark-mode .title-icon i {  
            color: white;  
        } 

        .page-hero h1 {
            font-size: 32px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.02em;
            color: var(--text-primary);
        }

        .dark-mode .page-hero h1 {
            color: white;
        }

        .page-hero .subtitle {
            font-size: 15px;
            opacity: 0.95;
            margin-top: 8px;
            line-height: 1.5;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .dark-mode  .page-hero .subtitle {
            color: rgba(255,255,255,0.9);
        }

        /* ===================== TOOLBAR ===================== */
        .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: var(--space-lg);
        }

        /* ===================== CARDS ===================== */
        .filter-card, .table-card {
            background: var(--bg-elevated);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-lg);
            margin-bottom: var(--space-lg);
            animation: fadeInUp 0.4s ease;
        }

        /* ===================== FORM CONTROLS ===================== */
        .form-group {
            margin-bottom: var(--space-md);
        }

        .form-label {  
            font-weight: 700;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-control, select.form-control {
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .form-control:focus, select.form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
            outline: none;
            background: var(--bg-primary);
        }

        /* ===================== BUTTONS ===================== */
        .btn {
            border-radius: var(--radius-md);
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: none;
            padding: 12px 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-success {
            background: var(--gradient-success);
            color: white;
        }

        .btn-info {
            background: var(--gradient-info);
            color: white;
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid var(--border-primary);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
            border-color: var(--brand-primary);
            color: var(--brand-primary);
        }

        .btn-warning {
            background: var(--gradient-warning);
            color: white;
        }

        .btn-purple {
            background: var(--gradient-purple);
            color: white;
        }

        /* ===================== TABLE MODERN ===================== */
        .table-card {
            padding: 0;
            overflow: hidden;
        }

        .table-header {
            padding: var(--space-lg);
            border-bottom: 2px solid var(--border-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-md);
        }

        .table-title {
            font-weight: 800;
            font-size: 20px;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .table-responsive {
            padding: var(--space-lg);
            overflow-x: auto;
        }

        .table {
            margin-bottom: 0;
            width: 100% !important;
        }

        .table thead th {
            background: var(--bg-tertiary);
            border-bottom: 2px solid var(--border-primary);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            padding: 14px 12px;
            white-space: nowrap;
            vertical-align: middle;
        }

        .table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--border-primary);
        }

        .table tbody tr:hover {
            background: var(--bg-secondary);
        }

        .table tbody td {
            padding: 14px 12px;
            color: var(--text-primary);
            vertical-align: middle;
            font-size: 14px;
        }

        .money-column {
            text-align: right !important;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .ato-badge {
            display: inline-block;
            padding: 6px 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .dark-mode .ato-badge {
            background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
        }

        /* Colunas de valores com cores diferenciadas */
        .value-emolumentos {
            color: var(--brand-primary);
        }

        .value-ferc {
            color: var(--brand-success);
        }

        .value-fadep {
            color: var(--brand-info);
        }

        .value-femp {
            color: var(--brand-warning);
        }

        .value-ferrfis {
            color: var(--brand-purple);
        }

        .value-total {
            color: var(--brand-primary);
            font-weight: 800;
            font-size: 15px;
        }

        /* ===================== DATATABLE CUSTOMIZATION ===================== */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: var(--space-md);
        }

        .dataTables_wrapper .dataTables_filter {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dataTables_wrapper .dataTables_filter label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-md);
            padding: 10px 16px;
            margin: 0;
            width: 280px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--brand-primary);
            outline: none;
        }

        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-md);
            padding: 8px 40px 8px 16px;
            margin: 0 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234b5563' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }

        .dataTables_wrapper .dataTables_info {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .dataTables_wrapper .dataTables_paginate {
            display: flex;
            gap: 4px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 2px solid var(--border-primary) !important;
            border-radius: var(--radius-md) !important;
            background: transparent !important;
            color: var(--text-primary) !important;
            padding: 8px 14px !important;
            margin: 0 !important;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--bg-secondary) !important;
            border-color: var(--brand-primary) !important;
            color: var(--brand-primary) !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--gradient-primary) !important;
            border-color: transparent !important;
            color: white !important;
        }

        .dt-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: var(--space-md);
        }

        /* ===================== STATS CARDS ===================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }

        .stat-card {
            background: var(--bg-elevated);
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-md);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--brand-primary);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .stat-icon.primary {
            background: rgba(79, 70, 229, 0.12);
            color: var(--brand-primary);
        }

        .stat-icon.success {
            background: rgba(16, 185, 129, 0.12);
            color: var(--brand-success);
        }

        .stat-icon.info {
            background: rgba(6, 182, 212, 0.12);
            color: var(--brand-info);
        }

        .stat-icon.warning {
            background: rgba(245, 158, 11, 0.12);
            color: var(--brand-warning);
        }

        .stat-icon.purple {
            background: rgba(139, 92, 246, 0.12);
            color: var(--brand-purple);
        }

        .stat-content {
            flex: 1;
            min-width: 0;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stat-value {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ===================== LEGEND ===================== */
        .legend-container {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: var(--space-md);
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-md);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .legend-dot.emolumentos { background: var(--brand-primary); }
        .legend-dot.ferc { background: var(--brand-success); }
        .legend-dot.fadep { background: var(--brand-info); }
        .legend-dot.femp { background: var(--brand-warning); }
        .legend-dot.ferrfis { background: var(--brand-purple); }

        /* ===================== ANIMATIONS ===================== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .loading {
            animation: pulse 1.5s infinite;
        }

        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .dataTables_wrapper .dataTables_filter input {
                width: 100%;
                margin-top: 8px;
            }

            .dataTables_wrapper .dataTables_filter label {
                width: 100%;
                flex-direction: column;
                align-items: flex-start;
            }

            .page-hero h1 {
                font-size: 24px;
            }

            .title-icon {
                width: 52px;
                height: 52px;
            }

            .title-icon i {
                font-size: 26px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat-value {
                font-size: 18px;
            }
        }

        @media (max-width: 576px) {
            .filter-card, .table-card {
                padding: var(--space-md);
            }

            .page-hero {
                padding: var(--space-lg);
            }

            .table-responsive {
                padding: var(--space-md);
            }

            .btn {
                width: 100%;
            }

            .toolbar-actions {
                width: 100%;
            }

            .toolbar-actions .btn {
                flex: 1;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .legend-container {
                flex-direction: column;
                gap: 8px;
            }
        }

        /* ===================== EMPTY STATE ===================== */
        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            color: var(--text-tertiary);
        }

        .empty-state i {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: var(--space-md);
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: var(--space-lg);
        }

        /* ===================== SCROLL TO TOP ===================== */
        #scrollTop {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            border: none;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #scrollTop:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        #scrollTop.show {
            opacity: 1;
        }

        /* ===================== TOOLTIP ===================== */
        .tooltip-custom {
            position: relative;
            cursor: help;
        }

        .tooltip-custom::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 100;
            box-shadow: var(--shadow-md);
        }

        .tooltip-custom:hover::after {
            opacity: 1;
            visibility: visible;
        }

        /* ===================== PRINT STYLES ===================== */
        @media print {
            .page-hero, .filter-card, .toolbar-actions, #scrollTop, .dt-buttons {
                display: none !important;
            }

            .table-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .table thead th {
                background: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>  
</head>
<body class="light-mode">  
    <?php include(__DIR__ . '/../menu.php'); ?>  

    <?php
        // Valores atuais de filtro
        $vAto = isset($_GET['ato']) ? htmlspecialchars($_GET['ato']) : '';
        $vDesc = isset($_GET['descricao']) ? htmlspecialchars($_GET['descricao']) : '';
        $vAtrib = isset($_GET['atribuicao']) ? htmlspecialchars($_GET['atribuicao']) : '';

        // Preparar query - Atualizado para incluir FERRFIS
        try {
            $conn = getDatabaseConnection();  
            $conditions = [];  
            $params = [];  

            if (!empty($_GET['ato'])) {  
                $conditions[] = 'ATO LIKE :ato';  
                $params[':ato'] = '%' . $_GET['ato'] . '%';  
            }  
            if (!empty($_GET['descricao'])) {  
                $conditions[] = 'DESCRICAO LIKE :descricao';  
                $params[':descricao'] = '%' . $_GET['descricao'] . '%';  
            }  
            if (!empty($_GET['atribuicao'])) {  
                $conditions[] = 'ATO LIKE :atribuicao';  
                $params[':atribuicao'] = $_GET['atribuicao'] . '.%';  
            }  

            // Query atualizada com FERRFIS
            $sql = 'SELECT ID, ATO, DESCRICAO, EMOLUMENTOS, FERC, FADEP, FEMP, FERRFIS, TOTAL FROM tabela_emolumentos';  
            if ($conditions) {  
                $sql .= ' WHERE ' . implode(' AND ', $conditions);  
            }  
            $sql .= ' ORDER BY ID ASC';  

            $stmt = $conn->prepare($sql);  
            foreach ($params as $key => $value) {  
                $stmt->bindValue($key, $value);  
            }  
            $stmt->execute();  
            $emolumentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular estatísticas - incluindo FERRFIS
            $totalRegistros = count($emolumentos);
            $somaTotal = array_sum(array_column($emolumentos, 'TOTAL'));
            $somaEmolumentos = array_sum(array_column($emolumentos, 'EMOLUMENTOS'));
            $somaFerc = array_sum(array_column($emolumentos, 'FERC'));
            $somaFadep = array_sum(array_column($emolumentos, 'FADEP'));
            $somaFemp = array_sum(array_column($emolumentos, 'FEMP'));
            $somaFerrfis = array_sum(array_column($emolumentos, 'FERRFIS'));
        } catch (Exception $e) {
            $emolumentos = [];
            $totalRegistros = 0;
            $somaTotal = 0;
            $somaEmolumentos = 0;
            $somaFerc = 0;
            $somaFadep = 0;
            $somaFemp = 0;
            $somaFerrfis = 0;
        }
    ?>

    <div id="main" class="main-content">  
        <div class="container">  
            
            <!-- ===================== PAGE HERO ===================== -->
            <section class="page-hero">
                <div class="title-row">
                    <div class="title-icon">
                        <i class="mdi mdi-table-large"></i>
                    </div>
                    <div style="flex: 1;">
                        <h1>Tabela de Emolumentos</h1>
                        <div class="subtitle">
                            <i class="mdi mdi-information-outline"></i>
                            Consulta dos valores e faixas de emolumentos com filtros rápidos e exportação para Excel/CSV
                        </div>
                    </div>
                    <div class="toolbar-actions" style="margin-left: auto;">
                        <button type="button" class="btn btn-secondary" onclick="window.print()">
                            <i class="mdi mdi-printer"></i> Imprimir
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="mdi mdi-file-document-multiple"></i> Ordens de Serviço
                        </a>
                    </div>
                </div>
            </section>

            <!-- ===================== STATS CARDS ===================== -->
            <?php if (!empty($_GET['ato']) || !empty($_GET['descricao']) || !empty($_GET['atribuicao'])): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="mdi mdi-format-list-numbered"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Total de Registros</div>
                        <div class="stat-value"><?php echo number_format($totalRegistros, 0, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="mdi mdi-cash"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Emolumentos</div>
                        <div class="stat-value">R$ <?php echo number_format($somaEmolumentos, 2, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="mdi mdi-bank"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">FERC</div>
                        <div class="stat-value">R$ <?php echo number_format($somaFerc, 2, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="mdi mdi-account-group"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">FADEP</div>
                        <div class="stat-value">R$ <?php echo number_format($somaFadep, 2, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="mdi mdi-gavel"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">FEMP</div>
                        <div class="stat-value">R$ <?php echo number_format($somaFemp, 2, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="mdi mdi-shield-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">FERRFIS</div>
                        <div class="stat-value">R$ <?php echo number_format($somaFerrfis, 2, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="mdi mdi-calculator-variant"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Valor Total</div>
                        <div class="stat-value">R$ <?php echo number_format($somaTotal, 2, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===================== FILTER CARD ===================== -->
            <div class="filter-card">
                <form id="pesquisarForm" method="GET" autocomplete="off">
                    <div class="row">
                        <div class="col-md-2 col-sm-6 col-12">
                            <div class="form-group">
                                <label class="form-label" for="ato">
                                    <i class="mdi mdi-pound"></i> Código do Ato
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="ato" 
                                       name="ato" 
                                       pattern="[0-9.]*" 
                                       placeholder="Ex.: 16.01" 
                                       value="<?php echo $vAto; ?>">
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label class="form-label" for="descricao">
                                    <i class="mdi mdi-text"></i> Descrição
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="descricao" 
                                       name="descricao" 
                                       placeholder="Ex.: Registro de..." 
                                       value="<?php echo $vDesc; ?>">
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label class="form-label" for="atribuicao">
                                    <i class="mdi mdi-tag"></i> Atribuição
                                </label>
                                <select class="form-control" id="atribuicao" name="atribuicao">
                                    <option value="">Todas as Atribuições</option>
                                    <option value="13" <?php echo $vAtrib==='13'?'selected':''; ?>>Notas</option>
                                    <option value="14" <?php echo $vAtrib==='14'?'selected':''; ?>>Registro Civil</option>
                                    <option value="15" <?php echo $vAtrib==='15'?'selected':''; ?>>Títulos e Documentos e Pessoas Jurídicas</option>
                                    <option value="16" <?php echo $vAtrib==='16'?'selected':''; ?>>Registro de Imóveis</option>
                                    <option value="17" <?php echo $vAtrib==='17'?'selected':''; ?>>Protesto</option>
                                    <option value="18" <?php echo $vAtrib==='18'?'selected':''; ?>>Contratos Marítimos</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8 col-12 order-2 order-md-1">
                            <button type="button" 
                                    class="btn btn-secondary" 
                                    onclick="limparFiltros()">
                                <i class="mdi mdi-filter-remove"></i> Limpar Filtros
                            </button>
                        </div>
                        <div class="col-md-4 col-12 order-1 order-md-2 mb-3 mb-md-0">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="mdi mdi-magnify"></i> Pesquisar
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ===================== TABLE CARD ===================== -->
            <div class="table-card">
                <div class="table-header">
                    <div>
                        <h5 class="table-title">
                            <i class="mdi mdi-table-large"></i>
                            Resultados da Consulta
                        </h5>
                        <div class="table-subtitle">
                            <?php 
                            if ($totalRegistros > 0) {
                                echo "Exibindo <strong>{$totalRegistros}</strong> " . ($totalRegistros == 1 ? 'registro' : 'registros');
                            } else {
                                echo "Nenhum registro encontrado";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <?php if ($totalRegistros > 0): ?>
                <!-- Legenda das colunas -->
                <div style="padding: var(--space-lg); padding-bottom: 0;">
                    <div class="legend-container">
                        <div class="legend-item">
                            <span class="legend-dot emolumentos"></span>
                            <span>Emolumentos</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot ferc"></span>
                            <span class="tooltip-custom" data-tooltip="Fundo Especial do Registro Civil">FERC</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot fadep"></span>
                            <span class="tooltip-custom" data-tooltip="Fundo de Apoio ao Desenvolvimento Profissional">FADEP</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot femp"></span>
                            <span class="tooltip-custom" data-tooltip="Fundo Especial do Ministério Público">FEMP</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot ferrfis"></span>
                            <span class="tooltip-custom" data-tooltip="Fundo Especial de Reaparelhamento e Modernização da Fiscalização">FERRFIS</span>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="resultadosTabela" class="table table-hover nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th><i class="mdi mdi-pound"></i> Ato</th>
                                <th><i class="mdi mdi-text"></i> Descrição</th>
                                <th class="money-column"><i class="mdi mdi-cash"></i> Emolumentos</th>
                                <th class="money-column"><i class="mdi mdi-bank"></i> FERC</th>
                                <th class="money-column"><i class="mdi mdi-account-group"></i> FADEP</th>
                                <th class="money-column"><i class="mdi mdi-gavel"></i> FEMP</th>
                                <th class="money-column"><i class="mdi mdi-shield-check"></i> FERRFIS</th>
                                <th class="money-column"><i class="mdi mdi-calculator-variant"></i> Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php  
                            foreach ($emolumentos as $emolumento) {  
                                $fmt = function($v){
                                    return is_numeric($v) ? 'R$ ' . number_format($v, 2, ',', '.') : '—';
                                };
                                ?>
                                <tr>
                                    <td>
                                        <span class="ato-badge"><?php echo htmlspecialchars($emolumento['ATO']); ?></span>
                                    </td>
                                    <td style="white-space: normal; max-width: 400px;"><?php echo htmlspecialchars($emolumento['DESCRICAO']); ?></td>
                                    <td class="money-column value-emolumentos"><?php echo $fmt($emolumento['EMOLUMENTOS']); ?></td>
                                    <td class="money-column value-ferc"><?php echo $fmt($emolumento['FERC']); ?></td>
                                    <td class="money-column value-fadep"><?php echo $fmt($emolumento['FADEP']); ?></td>
                                    <td class="money-column value-femp"><?php echo $fmt($emolumento['FEMP']); ?></td>
                                    <td class="money-column value-ferrfis"><?php echo $fmt($emolumento['FERRFIS']); ?></td>
                                    <td class="money-column value-total"><?php echo $fmt($emolumento['TOTAL']); ?></td>
                                </tr>
                                <?php  
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                <td colspan="2" style="text-align: right;">
                                    <strong>TOTAIS:</strong>
                                </td>
                                <td class="money-column value-emolumentos">R$ <?php echo number_format($somaEmolumentos, 2, ',', '.'); ?></td>
                                <td class="money-column value-ferc">R$ <?php echo number_format($somaFerc, 2, ',', '.'); ?></td>
                                <td class="money-column value-fadep">R$ <?php echo number_format($somaFadep, 2, ',', '.'); ?></td>
                                <td class="money-column value-femp">R$ <?php echo number_format($somaFemp, 2, ',', '.'); ?></td>
                                <td class="money-column value-ferrfis">R$ <?php echo number_format($somaFerrfis, 2, ',', '.'); ?></td>
                                <td class="money-column value-total">R$ <?php echo number_format($somaTotal, 2, ',', '.'); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="mdi mdi-table-off"></i>
                    <p>Nenhum registro encontrado com os filtros aplicados.</p>
                    <button type="button" class="btn btn-primary" onclick="limparFiltros()">
                        <i class="mdi mdi-filter-remove"></i> Limpar Filtros
                    </button>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ===================== SCROLL TO TOP ===================== -->
    <button id="scrollTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" aria-label="Voltar ao topo">
        <i class="mdi mdi-arrow-up"></i>
    </button>

    <!-- ===================== SCRIPTS ===================== -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>

    <script>
        'use strict';

        // ===================== HELPERS =====================
        function limparFiltros() {
            document.getElementById('pesquisarForm').reset();
            window.location.href = 'tabela_de_emolumentos.php';
        }

        // ===================== SCROLL TO TOP =====================
        window.addEventListener('scroll', function() {
            const scrollTop = document.getElementById('scrollTop');
            if (window.pageYOffset > 300) {
                scrollTop.classList.add('show');
            } else {
                scrollTop.classList.remove('show');
            }
        });

        // ===================== KEYBOARD SHORTCUTS =====================
        document.addEventListener('keydown', function(e) {
            // Ctrl + F para focar no campo de pesquisa
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('ato').focus();
            }
            // Escape para limpar filtros
            if (e.key === 'Escape') {
                const activeElement = document.activeElement;
                if (activeElement.tagName === 'INPUT' || activeElement.tagName === 'SELECT') {
                    activeElement.blur();
                }
            }
        });

        // ===================== INIT =====================
        $(document).ready(function() {
            // Carregar modo dark/light
            $.ajax({
                url: '../load_mode.php',
                method: 'GET',
                success: function(mode) {
                    $('body').removeClass('light-mode dark-mode').addClass(mode);
                }
            });

            // Sanitizar campo "ato" enquanto digita
            $('#ato').on('input', function() {
                this.value = this.value.replace(/[^0-9.]/g, '');
            });

            // Submeter formulário com Enter
            $('#pesquisarForm input').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#pesquisarForm').submit();
                }
            });

            // Inicializar DataTable apenas se houver registros
            <?php if ($totalRegistros > 0): ?>
            var table = $('#resultadosTabela').DataTable({
                language: { 
                    url: '../style/Portuguese-Brasil.json' 
                },
                order: [],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
                responsive: {
                    details: {
                        type: 'inline',
                        target: 'tr'
                    }
                },
                dom: "<'row align-items-center'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="mdi mdi-file-excel"></i> Excel',
                        titleAttr: 'Exportar para Excel',
                        className: 'btn btn-success',
                        title: 'Tabela de Emolumentos - Atlas',
                        exportOptions: { 
                            columns: ':visible',
                            format: {
                                body: function(data, row, column, node) {
                                    // Remove badges e formatação HTML
                                    return $(node).find('.ato-badge').length ? 
                                           $(node).find('.ato-badge').text() : 
                                           data;
                                }
                            }
                        }
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="mdi mdi-file-delimited"></i> CSV',
                        titleAttr: 'Exportar para CSV',
                        className: 'btn btn-info',
                        exportOptions: { 
                            columns: ':visible',
                            format: {
                                body: function(data, row, column, node) {
                                    return $(node).find('.ato-badge').length ? 
                                           $(node).find('.ato-badge').text() : 
                                           data;
                                }
                            }
                        }
                    },
                    {
                        extend: 'copyHtml5',
                        text: '<i class="mdi mdi-content-copy"></i> Copiar',
                        titleAttr: 'Copiar para área de transferência',
                        className: 'btn btn-secondary',
                        exportOptions: { 
                            columns: ':visible',
                            format: {
                                body: function(data, row, column, node) {
                                    return $(node).find('.ato-badge').length ? 
                                           $(node).find('.ato-badge').text() : 
                                           data;
                                }
                            }
                        }
                    }
                ],
                columnDefs: [
                    { responsivePriority: 1, targets: 0 },  // Ato
                    { responsivePriority: 2, targets: 1 },  // Descrição
                    { responsivePriority: 3, targets: -1 }, // Total
                    { responsivePriority: 4, targets: 2 },  // Emolumentos
                    { responsivePriority: 5, targets: 3 },  // FERC
                    { responsivePriority: 6, targets: 4 },  // FADEP
                    { responsivePriority: 7, targets: 5 },  // FEMP
                    { responsivePriority: 8, targets: 6 }   // FERRFIS
                ],
                drawCallback: function() {
                    // Animação suave ao carregar linhas
                    $(this.api().table().body()).find('tr').each(function(index) {
                        $(this).css({
                            'animation': 'fadeInUp 0.3s ease forwards',
                            'animation-delay': (index * 0.02) + 's',
                            'opacity': '0'
                        });
                    });
                }
            });

            // Highlight de linha ao clicar
            $('#resultadosTabela tbody').on('click', 'tr', function() {
                if ($(this).hasClass('selected')) {
                    $(this).removeClass('selected');
                } else {
                    table.$('tr.selected').removeClass('selected');
                    $(this).addClass('selected');
                }
            });
            <?php endif; ?>

            // Focus no primeiro campo
            $('#ato').focus();
        });
    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>