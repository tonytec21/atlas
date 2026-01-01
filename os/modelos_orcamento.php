<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/**
 * Garante que a coluna FERRFIS exista na tabela modelos_de_orcamento_itens.
 * (Evita esquecer de rodar update no banco)
 */
try {
    $connCheck = getDatabaseConnection();
    $stmtCol = $connCheck->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'modelos_de_orcamento_itens'
          AND COLUMN_NAME = 'ferrfis'
    ");
    $stmtCol->execute();
    $colExists = (int)$stmtCol->fetchColumn() > 0;

    if (!$colExists) {
        $connCheck->exec("ALTER TABLE modelos_de_orcamento_itens ADD COLUMN ferrfis DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER femp");
    }
} catch (PDOException $e) {
    // Não interrompe a tela (apenas loga)
    error_log('Erro ao garantir coluna ferrfis em modelos_de_orcamento_itens: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Modelos de Orçamento - Atlas</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ===================== DESIGN SYSTEM ===================== */
        :root {
            --brand-primary: #4f46e5;
            --brand-secondary: #818cf8;
            --brand-success: #10b981;
            --brand-warning: #f59e0b;
            --brand-error: #ef4444;
            --brand-info: #06b6d4;

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
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-error: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --gradient-info: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            --gradient-secondary: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);

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
            max-width: 1400px;
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

        .title-row {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .title-icon i {  
        font-size: 32px;  
        color: var(--text-primary);  
        position: relative;  
        z-index: 1;  
        }  

        .dark-mode .title-icon {
        color: white; 
        }

        .page-hero h1 {
            font-size: 32px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.02em;
        }

        .page-hero .subtitle {
            font-size: 15px;
            opacity: 0.95;
            margin-top: 8px;
            line-height: 1.5;
        }

        .title-actions {
            margin-left: auto;
        }

        /* ===================== CARDS ===================== */
        .card {
            background: var(--bg-elevated);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            margin-bottom: var(--space-lg);
            animation: fadeInUp 0.4s ease;
            overflow: hidden;
        }

        .card-header {
            background: var(--bg-tertiary);
            border-bottom: 2px solid var(--border-primary);
            padding: var(--space-lg);
            font-weight: 800;
            font-size: 18px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: var(--space-xl);
        }

        /* ===================== FORM CONTROLS ===================== */
        .form-group {
            margin-bottom: var(--space-md);
        }

        .form-group label {
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

        .form-control, textarea.form-control, select.form-control {
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .form-control:focus, textarea.form-control:focus, select.form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
            outline: none;
            background: var(--bg-primary);
        }

        .form-control:read-only {
            background-color: var(--bg-secondary);
            cursor: not-allowed;
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

        .btn-warning {
            background: var(--gradient-warning);
            color: white;
        }

        .btn-delete {
            background: var(--gradient-error);
            color: white;
        }

        .btn-secondary {
            background: var(--gradient-secondary);
            color: white;
        }

        .btn-block {
            width: 100%;
        }

        /* ===================== TABLE MODERN ===================== */
        .table-wrapper {
            overflow-x: auto;
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-primary);
        }

        .table {
            margin-bottom: 0;
            width: 100%;
        }

        .table thead th {
            background: var(--bg-tertiary);
            border-bottom: 2px solid var(--border-primary);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            padding: 16px;
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
            padding: 16px;
            color: var(--text-primary);
            vertical-align: middle;
        }

        /* ===================== MOBILE CARDS ===================== */
        .items-mobile-view {
            display: none;
        }

        .item-card {
            background: var(--bg-elevated);
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-md);
            margin-bottom: var(--space-md);
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }

        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--brand-primary);
        }

        .item-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-sm);
            padding-bottom: var(--space-sm);
            border-bottom: 2px solid var(--border-primary);
        }

        .item-card-title {
            font-weight: 800;
            font-size: 16px;
            color: var(--text-primary);
        }

        .item-card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-sm);
        }

        .item-card-field {
            display: flex;
            flex-direction: column;
        }

        .item-card-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .item-card-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .item-card-actions {
            margin-top: var(--space-md);
            padding-top: var(--space-md);
            border-top: 2px solid var(--border-primary);
        }

        /* ===================== MODELO CARDS ===================== */
        .modelo-card {
            background: var(--bg-elevated);
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-md);
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }

        .modelo-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--brand-primary);
        }

        .modelo-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
            gap: var(--space-md);
            flex-wrap: wrap;
        }

        .modelo-card-title {
            font-weight: 800;
            font-size: 20px;
            color: var(--text-primary);
            margin: 0;
        }

        .modelo-card-description {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .modelo-card-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ===================== BADGES ===================== */
        .badge-soft {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .badge-soft-primary {
            background: rgba(79, 70, 229, 0.15);
            color: var(--brand-primary);
        }

        .badge-soft-success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--brand-success);
        }

        .badge-soft-info {
            background: rgba(6, 182, 212, 0.15);
            color: var(--brand-info);
        }

        /* ===================== MODAL ===================== */
        .modal-content {
            border-radius: var(--radius-xl);
            border: none;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            background: var(--gradient-primary);
            color: white;
            border-top-left-radius: var(--radius-xl);
            border-top-right-radius: var(--radius-xl);
            border-bottom: none;
            padding: var(--space-lg);
        }

        .modal-title {
            font-weight: 800;
            font-size: 20px;
        }

        .modal-body {
            padding: var(--space-xl);
        }

        .btn-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            opacity: 1;
            font-size: 24px;
            line-height: 1;
            padding: 8px;
            border-radius: 50%;
        }

        /* ===================== DIVIDER ===================== */
        hr {
            border: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--border-primary), transparent);
            margin: var(--space-xl) 0;
        }

        /* ===================== SECTION TITLE ===================== */
        .section-title {
            font-weight: 800;
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: var(--space-md);
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--brand-primary);
        }

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

        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 768px) {
            .page-hero h1 {
                font-size: 24px;
            }

            .title-icon {
                width: 52px;
                height: 52px;
                font-size: 26px;
            }

            .title-actions {
                width: 100%;
                margin-left: 0;
                margin-top: var(--space-md);
            }

            .title-actions .btn {
                width: 100%;
            }

            .card-body {
                padding: var(--space-md);
            }

            .btn {
                width: 100%;
                margin-bottom: var(--space-sm);
            }

            .table-wrapper {
                display: none;
            }

            .items-mobile-view {
                display: block;
            }

            .modal-dialog {
                margin: 0.5rem;
            }

            .modelo-card-actions {
                width: 100%;
            }

            .modelo-card-actions .btn {
                flex: 1;
            }
        }

        @media (max-width: 576px) {
            .page-hero {
                padding: var(--space-lg);
            }

            .item-card-body {
                grid-template-columns: 1fr;
            }
        }

        /* ===================== UTILITY CLASSES ===================== */
        .text-money {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }

        .ml-auto {
            margin-left: auto;
        }

        /* ===================== LOADING STATE ===================== */
        .btn.loading {
            pointer-events: none;
            position: relative;
        }

        .btn.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Modal "Itens do Modelo" ocupando 90% da tela */
        #modalVisualizarModelo .modal-dialog.modal-90w {
        max-width: 90vw;   /* largura máxima 90% da viewport */
        width: 90vw;       /* força 90% */
        margin: 5vh auto;  /* centraliza e dá respiro vertical */
        }

        #modalVisualizarModelo .modal-content {
        height: 90vh;      /* altura total do modal = 90% da viewport */
        display: flex;
        flex-direction: column;
        }

        #modalVisualizarModelo .modal-body {
        overflow: auto;    /* rolagem interna caso passe do conteúdo */
        flex: 1;           /* ocupa o espaço disponível entre header/footer */
        }

    </style>
</head>
<body>
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <!-- ===================== PAGE HERO ===================== -->
        <section class="page-hero">
            <div class="title-row">
                <div class="title-icon">
                    <i class="mdi mdi-file-document-multiple"></i>
                </div>
                <div style="flex: 1;">
                    <h1>Modelos de Orçamento</h1>
                    <div class="subtitle">
                        <i class="mdi mdi-information-outline"></i>
                        Catálogo e gestão dos modelos de ordem de serviço para agilizar o processo de criação
                    </div>
                </div>
                <div class="title-actions">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="mdi mdi-file-search"></i> Ordens de Serviço
                    </a>
                </div>
            </div>
        </section>

        <!-- ===================== FORMULÁRIO CRIAR/EDITAR MODELO ===================== -->
        <div class="card">
            <div class="card-header">
                <i class="mdi mdi-plus-circle"></i>
                <span id="formHeaderText">Criar Novo Modelo</span>
            </div>
            <div class="card-body">
                <form id="formModelo">
                    <input type="hidden" id="modelo_id_edit" name="modelo_id_edit" value="">

                    <div class="form-group">
                        <label for="nome_modelo">
                            <i class="mdi mdi-text"></i> Nome do Modelo
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="nome_modelo" 
                               name="nome_modelo" 
                               placeholder="Ex.: Registro de Imóvel Padrão"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="descricao_modelo">
                            <i class="mdi mdi-text-box"></i> Descrição (opcional)
                        </label>
                        <textarea class="form-control" 
                                  id="descricao_modelo" 
                                  name="descricao_modelo" 
                                  rows="3"
                                  placeholder="Descreva o modelo de forma detalhada..."></textarea>
                    </div>

                    <hr>

                    <!-- ===================== ADICIONAR ITENS ===================== -->
                    <h5 class="section-title">
                        <i class="mdi mdi-playlist-plus"></i>
                        Adicionar Itens ao Modelo
                    </h5>

                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="ato">
                                    <i class="mdi mdi-pound"></i> Código do Ato
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="ato" 
                                       name="ato" 
                                       pattern="[0-9A-Za-z.]+" 
                                       placeholder="Ex.: 16.01">
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="quantidade">
                                    <i class="mdi mdi-numeric"></i> Quantidade
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="quantidade" 
                                       name="quantidade" 
                                       value="1" 
                                       min="1">
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="desconto_legal">
                                    <i class="mdi mdi-percent"></i> Desconto (%)
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="desconto_legal" 
                                       name="desconto_legal" 
                                       value="0" 
                                       min="0" 
                                       max="100">
                            </div>
                        </div>
                        <div class="col-md-5 col-12">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="d-flex" style="gap: 8px; flex-wrap: wrap;">
                                    <button type="button" class="btn btn-primary" onclick="buscarAto()">
                                        <i class="mdi mdi-magnify"></i> Buscar Ato
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="adicionarAtoManual()">
                                        <i class="fa fa-i-cursor"></i> Adicionar Manualmente
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descricao_item">
                            <i class="mdi mdi-text"></i> Descrição
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="descricao_item" 
                               name="descricao_item" 
                               placeholder="Descrição do item"
                               readonly>
                    </div>

                    <div class="row">
                        <div class="col-md-2 col-6">
                            <div class="form-group">
                                <label for="emolumentos">
                                    <i class="mdi mdi-cash"></i> Emolumentos
                                </label>
                                <input type="text" 
                                       class="form-control text-money" 
                                       id="emolumentos" 
                                       name="emolumentos" 
                                       placeholder="0,00"
                                       readonly>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="form-group">
                                <label for="ferc">
                                    <i class="mdi mdi-cash"></i> FERC
                                </label>
                                <input type="text" 
                                       class="form-control text-money" 
                                       id="ferc" 
                                       name="ferc" 
                                       placeholder="0,00"
                                       readonly>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="form-group">
                                <label for="fadep">
                                    <i class="mdi mdi-cash"></i> FADEP
                                </label>
                                <input type="text" 
                                       class="form-control text-money" 
                                       id="fadep" 
                                       name="fadep" 
                                       placeholder="0,00"
                                       readonly>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="form-group">
                                <label for="femp">
                                    <i class="mdi mdi-cash"></i> FEMP
                                </label>
                                <input type="text" 
                                       class="form-control text-money" 
                                       id="femp" 
                                       name="femp" 
                                       placeholder="0,00"
                                       readonly>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="form-group">
                                <label for="ferrfis">
                                    <i class="mdi mdi-cash"></i> FERRFIS
                                </label>
                                <input type="text" 
                                       class="form-control text-money" 
                                       id="ferrfis" 
                                       name="ferrfis" 
                                       placeholder="0,00"
                                       readonly>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="form-group">
                                <label for="total">
                                    <i class="mdi mdi-cash-multiple"></i> Total
                                </label>
                                <input type="text" 
                                       class="form-control text-money" 
                                       id="total" 
                                       name="total" 
                                       placeholder="0,00"
                                       readonly>
                            </div>
                        </div>
                        <div class="col-md-2 col-12">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-success btn-block" onclick="adicionarItemTabela()">
                                    <i class="mdi mdi-plus-circle"></i> Adicionar
                                </button>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- ===================== TABELA DE ITENS (DESKTOP) ===================== -->
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><i class="mdi mdi-pound"></i> Ato</th>
                                    <th><i class="mdi mdi-numeric"></i> Qtd</th>
                                    <th><i class="mdi mdi-percent"></i> Desc (%)</th>
                                    <th><i class="mdi mdi-text"></i> Descrição</th>
                                    <th><i class="mdi mdi-cash"></i> Emolumentos</th>
                                    <th><i class="mdi mdi-cash"></i> FERC</th>
                                    <th><i class="mdi mdi-cash"></i> FADEP</th>
                                    <th><i class="mdi mdi-cash"></i> FEMP</th>
                                    <th><i class="mdi mdi-cash"></i> FERRFIS</th>
                                    <th><i class="mdi mdi-cash-multiple"></i> Total</th>
                                    <th><i class="mdi mdi-cog"></i> Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaItensModelo">
                            </tbody>
                        </table>
                    </div>

                    <!-- ===================== CARDS DE ITENS (MOBILE) ===================== -->
                    <div class="items-mobile-view" id="itemsMobileView">
                        <!-- Cards serão inseridos aqui via JS -->
                    </div>

                    <button type="button" class="btn btn-primary btn-block" onclick="salvarModelo()">
                        <i class="mdi mdi-content-save"></i> 
                        <span id="btnSalvarText">Salvar Modelo</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- ===================== LISTAGEM DE MODELOS EXISTENTES ===================== -->
        <div class="card">
            <div class="card-header">
                <i class="mdi mdi-view-list"></i>
                Modelos Existentes
            </div>
            <div class="card-body" id="listaModelos">
                <!-- Modelos serão carregados via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- ===================== MODAL VISUALIZAR MODELO ===================== -->
<div class="modal fade" id="modalVisualizarModelo" tabindex="-1" aria-labelledby="modalVisualizarModeloLabel" aria-hidden="true">
    <div class="modal-dialog modal-90w">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVisualizarModeloLabel">
                    <i class="mdi mdi-eye"></i>
                    Itens do Modelo
                </h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- DESKTOP: TABELA -->
                <div class="table-wrapper">
                    <table class="table" id="tabelaItensVisualizar">
                        <thead>
                            <tr>
                                <th><i class="mdi mdi-pound"></i> Ato</th>
                                <th><i class="mdi mdi-numeric"></i> Qtd</th>
                                <th><i class="mdi mdi-percent"></i> Desc (%)</th>
                                <th><i class="mdi mdi-text"></i> Descrição</th>
                                <th><i class="mdi mdi-cash"></i> Emolumentos</th>
                                <th><i class="mdi mdi-cash"></i> FERC</th>
                                <th><i class="mdi mdi-cash"></i> FADEP</th>
                                <th><i class="mdi mdi-cash"></i> FEMP</th>
                                <th><i class="mdi mdi-cash"></i> FERRFIS</th>
                                <th><i class="mdi mdi-cash-multiple"></i> Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE: CARDS -->
                <div class="items-mobile-view" id="modalItemsMobileView">
                    <!-- Cards serão inseridos aqui via JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================== SCROLL TO TOP ===================== -->
<button id="scrollTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
    <i class="mdi mdi-arrow-up"></i>
</button>

<!-- ===================== SCRIPTS ===================== -->
<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/sweetalert2.js"></script>

<script>
'use strict';

// ===================== HELPERS =====================
function showAlert(message, type) {
    const iconColors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#06b6d4'
    };

    Swal.fire({
        icon: type === 'error' ? 'error' : 'success',
        title: type === 'error' ? 'Erro!' : 'Sucesso!',
        text: message,
        confirmButtonText: 'OK',
        confirmButtonColor: iconColors[type] || '#4f46e5'
    });
}

function formatMoney(value) {
    if (!value) return '0,00';
    return parseFloat(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function syncTableAndCards() {
    // Limpa os cards
    $('#itemsMobileView').empty();

    // Para cada linha da tabela, cria um card
    $('#tabelaItensModelo tr').each(function(index) {
        const tds = $(this).find('td');
        const card = `
        <div class="item-card">
            <div class="item-card-header">
                <span class="item-card-title">
                    <i class="mdi mdi-file-document"></i> Item ${index + 1}
                </span>
                <span class="badge-soft badge-soft-primary">${tds.eq(0).text()}</span>
            </div>
            <div class="item-card-body">
                <div class="item-card-field">
                    <span class="item-card-label">Quantidade</span>
                    <span class="item-card-value">${tds.eq(1).text()}</span>
                </div>
                <div class="item-card-field">
                    <span class="item-card-label">Desconto</span>
                    <span class="item-card-value">${tds.eq(2).text()}%</span>
                </div>
                <div class="item-card-field" style="grid-column: 1 / -1;">
                    <span class="item-card-label">Descrição</span>
                    <span class="item-card-value">${tds.eq(3).text()}</span>
                </div>
                <div class="item-card-field">
                    <span class="item-card-label">Emolumentos</span>
                    <span class="item-card-value text-money">R$ ${tds.eq(4).text()}</span>
                </div>
                <div class="item-card-field">
                    <span class="item-card-label">FERC</span>
                    <span class="item-card-value text-money">R$ ${tds.eq(5).text()}</span>
                </div>
                <div class="item-card-field">
                    <span class="item-card-label">FADEP</span>
                    <span class="item-card-value text-money">R$ ${tds.eq(6).text()}</span>
                </div>
                <div class="item-card-field">
                    <span class="item-card-label">FEMP</span>
                    <span class="item-card-value text-money">R$ ${tds.eq(7).text()}</span>
                </div>
                <div class="item-card-field">
                    <span class="item-card-label">FERRFIS</span>
                    <span class="item-card-value text-money">R$ ${tds.eq(8).text()}</span>
                </div>
                <div class="item-card-field" style="grid-column: 1 / -1;">
                    <span class="item-card-label">Total</span>
                    <span class="item-card-value text-money" style="font-size: 18px; color: var(--brand-success);">R$ ${tds.eq(9).text()}</span>
                </div>
            </div>
            <div class="item-card-actions">
                <button type="button" class="btn btn-delete btn-block" onclick="removerItemPorIndex(${index})">
                    <i class="mdi mdi-delete"></i> Remover Item
                </button>
            </div>
        </div>`;
        
        $('#itemsMobileView').append(card);
    });
}

function removerItemPorIndex(index) {
    $('#tabelaItensModelo tr').eq(index).remove();
    syncTableAndCards();
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

// ===================== INIT =====================
$(document).ready(function() {
    carregarModelos();
    
    // Máscaras
    $('#emolumentos, #ferc, #fadep, #femp, #ferrfis, #total').mask('#.##0,00', {reverse: true});
    
    // Sanitizar campo ato
    $('#ato').on('input', function() {
        this.value = this.value.replace(/[^0-9a-zA-Z.]/g, '');
    });

    // Carregar modo dark/light
    $.ajax({
        url: '../load_mode.php',
        method: 'GET',
        success: function(mode) {
            $('body').removeClass('light-mode dark-mode').addClass(mode);
        }
    });
});

// ===================== BUSCAR ATO =====================
function buscarAto() {
    const ato = $('#ato').val().trim();
    const quantidade = parseInt($('#quantidade').val()) || 1;
    const descontoLegal = parseFloat($('#desconto_legal').val()) || 0;

    if (!ato) {
        showAlert('Informe o código do ato', 'error');
        $('#ato').focus();
        return;
    }

    $.ajax({
        url: 'buscar_ato.php',
        type: 'GET',
        dataType: 'json',
        data: { ato: ato },
        success: function(response) {
            if (response.error) {
                showAlert(response.error, 'error');
            } else {
                try {
                    let emolumentos = parseFloat(response.EMOLUMENTOS || 0) * quantidade;
                    let ferc       = parseFloat(response.FERC || 0)        * quantidade;
                    let fadep      = parseFloat(response.FADEP || 0)       * quantidade;
                    let femp       = parseFloat(response.FEMP || 0)        * quantidade;
                    let ferrfis    = parseFloat(response.FERRFIS || 0)     * quantidade;

                    const desconto = descontoLegal / 100;
                    emolumentos *= (1 - desconto);
                    ferc        *= (1 - desconto);
                    fadep       *= (1 - desconto);
                    femp        *= (1 - desconto);
                    ferrfis     *= (1 - desconto);

                    const total = emolumentos + ferc + fadep + femp + ferrfis;

                    $('#descricao_item').val(response.DESCRICAO || '').prop('readonly', true);
                    $('#emolumentos').val(formatMoney(emolumentos)).prop('readonly', true);
                    $('#ferc').val(formatMoney(ferc)).prop('readonly', true);
                    $('#fadep').val(formatMoney(fadep)).prop('readonly', true);
                    $('#femp').val(formatMoney(femp)).prop('readonly', true);
                    $('#ferrfis').val(formatMoney(ferrfis)).prop('readonly', true);
                    $('#total').val(formatMoney(total)).prop('readonly', true);
                } catch (e) {
                    showAlert('Erro ao processar os dados do ato.', 'error');
                    console.error(e);
                }
            }
        },
        error: function(xhr) {
            showAlert('Erro ao buscar o ato', 'error');
            console.error(xhr.responseText);
        }
    });
}

// ===================== ADICIONAR ATO MANUAL =====================
function adicionarAtoManual() {
    // Limpa e habilita campos
    $('#ato').val('0');
    $('#desconto_legal').val('0');
    $('#descricao_item').val('').prop('readonly', false);
    $('#emolumentos').val('0,00').prop('readonly', false);
    $('#ferc').val('0,00').prop('readonly', false);
    $('#fadep').val('0,00').prop('readonly', false);
    $('#femp').val('0,00').prop('readonly', false);
    $('#ferrfis').val('0,00').prop('readonly', false);
    $('#total').val('').prop('readonly', false);
    $('#quantidade').val('1');

    // Focus no campo descrição
    $('#descricao_item').focus();

    showAlert('Preencha manualmente os campos. Descrição e Total são obrigatórios.', 'info');
}

// ===================== ADICIONAR ITEM À TABELA =====================
function adicionarItemTabela() {
    const ato           = $('#ato').val().trim();
    const quantidade    = $('#quantidade').val() || '1';
    const descontoLegal = $('#desconto_legal').val() || '0';
    const descricao     = $('#descricao_item').val().trim();
    const emolumentos   = $('#emolumentos').val() || '0,00';
    const ferc          = $('#ferc').val() || '0,00';
    const fadep         = $('#fadep').val() || '0,00';
    const femp          = $('#femp').val() || '0,00';
    const ferrfis       = $('#ferrfis').val() || '0,00';
    const total         = $('#total').val().trim();

    // Validações
    if (!descricao) {
        showAlert('A Descrição é obrigatória', 'error');
        $('#descricao_item').focus();
        return;
    }

    if (!total || total === '0,00' || total === '0') {
        showAlert('O Total é obrigatório e deve ser maior que zero', 'error');
        $('#total').focus();
        return;
    }

    // Monta a linha da tabela
    const item = `
    <tr>
        <td>${ato || '—'}</td>
        <td>${quantidade}</td>
        <td>${descontoLegal}</td>
        <td>${descricao}</td>
        <td>${emolumentos}</td>
        <td>${ferc}</td>
        <td>${fadep}</td>
        <td>${femp}</td>
        <td>${ferrfis}</td>
        <td>${total}</td>
        <td>
            <button type="button" class="btn btn-delete btn-sm" onclick="removerItem(this)">
                <i class="mdi mdi-delete"></i>
            </button>
        </td>
    </tr>`;

    $('#tabelaItensModelo').append(item);

    // Sincroniza cards mobile
    syncTableAndCards();

    // Limpa os campos
    $('#ato').val('');
    $('#quantidade').val('1');
    $('#desconto_legal').val('0');
    $('#descricao_item').val('');
    $('#emolumentos').val('');
    $('#ferc').val('');
    $('#fadep').val('');
    $('#femp').val('');
    $('#ferrfis').val('');
    $('#total').val('');

    // Retorna campos ao readonly
    $('#descricao_item, #emolumentos, #ferc, #fadep, #femp, #ferrfis, #total').prop('readonly', true);

    // Focus no campo ato
    $('#ato').focus();
}

// ===================== REMOVER ITEM =====================
function removerItem(button) {
    $(button).closest('tr').remove();
    syncTableAndCards();
}

// ===================== SALVAR MODELO =====================
function salvarModelo() {
    const nome_modelo = $('#nome_modelo').val().trim();
    const descricao_modelo = $('#descricao_modelo').val().trim();
    const modelo_id_edit = $('#modelo_id_edit').val();

    if (!nome_modelo) {
        showAlert('O Nome do Modelo é obrigatório!', 'error');
        $('#nome_modelo').focus();
        return;
    }

    // Monta o array de itens
    const itens = [];
    $('#tabelaItensModelo tr').each(function() {
        const tds = $(this).find('td');
        const item = {
            ato:            tds.eq(0).text(),
            quantidade:     tds.eq(1).text(),
            desconto_legal: tds.eq(2).text(),
            descricao:      tds.eq(3).text(),
            emolumentos:    tds.eq(4).text(),
            ferc:           tds.eq(5).text(),
            fadep:          tds.eq(6).text(),
            femp:           tds.eq(7).text(),
            ferrfis:        tds.eq(8).text(),
            total:          tds.eq(9).text()
        };
        itens.push(item);
    });

    if (itens.length === 0) {
        showAlert('Adicione ao menos um item ao modelo', 'error');
        return;
    }

    // Decide qual arquivo chamar
    const urlAcao = modelo_id_edit ? 'atualizar_modelo_orcamento.php' : 'salvar_modelo_orcamento.php';

    const $btnSalvar = $('#btnSalvarText').parent();
    $btnSalvar.addClass('loading').prop('disabled', true);

    $.ajax({
        url: urlAcao,
        type: 'POST',
        dataType: 'json',
        data: {
            id: modelo_id_edit,
            nome_modelo: nome_modelo,
            descricao_modelo: descricao_modelo,
            itens: itens
        },
        success: function(response) {
            $btnSalvar.removeClass('loading').prop('disabled', false);
            
            if (response.error) {
                showAlert(response.error, 'error');
            } else {
                const msg = modelo_id_edit 
                          ? 'Modelo atualizado com sucesso!' 
                          : 'Modelo salvo com sucesso!';
                showAlert(msg, 'success');
                
                // Limpar formulário
                $('#formModelo')[0].reset();
                $('#tabelaItensModelo').empty();
                $('#itemsMobileView').empty();
                $('#modelo_id_edit').val('');
                $('#formHeaderText').text('Criar Novo Modelo');
                $('#btnSalvarText').text('Salvar Modelo');

                // Recarrega a listagem
                carregarModelos();

                // Scroll para o topo
                $('html, body').animate({ scrollTop: 0 }, 'slow');
            }
        },
        error: function(xhr) {
            $btnSalvar.removeClass('loading').prop('disabled', false);
            showAlert('Erro ao salvar/atualizar o modelo.', 'error');
            console.error(xhr.responseText);
        }
    });
}

// ===================== CARREGAR MODELOS =====================
function carregarModelos() {
    $.ajax({
        url: 'listar_modelos_orcamento.php',
        type: 'GET',
        dataType: 'html',
        success: function(response) {
            $('#listaModelos').html(response);
        },
        error: function() {
            $('#listaModelos').html('<div class="empty-state"><i class="mdi mdi-alert-circle"></i><p>Erro ao carregar os modelos.</p></div>');
        }
    });
}

// ===================== VISUALIZAR MODELO =====================
function visualizarModelo(id) {
    $.ajax({
        url: 'carregar_modelo_orcamento.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                showAlert(response.error, 'error');
            } else if (response.itens) {
                // Limpar tabela e cards
                $('#tabelaItensVisualizar tbody').empty();
                $('#modalItemsMobileView').empty();

                response.itens.forEach(function(item, index) {
                    // Linha da tabela
                    const row = `
                    <tr>
                        <td>${item.ato || '—'}</td>
                        <td>${item.quantidade}</td>
                        <td>${item.desconto_legal}</td>
                        <td>${item.descricao}</td>
                        <td class="text-money">R$ ${formatMoney(item.emolumentos)}</td>
                        <td class="text-money">R$ ${formatMoney(item.ferc)}</td>
                        <td class="text-money">R$ ${formatMoney(item.fadep)}</td>
                        <td class="text-money">R$ ${formatMoney(item.femp)}</td>
                        <td class="text-money">R$ ${formatMoney(item.ferrfis || 0)}</td>
                        <td class="text-money">R$ ${formatMoney(item.total)}</td>
                    </tr>`;
                    $('#tabelaItensVisualizar tbody').append(row);

                    // Card mobile
                    const card = `
                    <div class="item-card">
                        <div class="item-card-header">
                            <span class="item-card-title">
                                <i class="mdi mdi-file-document"></i> Item ${index + 1}
                            </span>
                            <span class="badge-soft badge-soft-primary">${item.ato || '—'}</span>
                        </div>
                        <div class="item-card-body">
                            <div class="item-card-field">
                                <span class="item-card-label">Quantidade</span>
                                <span class="item-card-value">${item.quantidade}</span>
                            </div>
                            <div class="item-card-field">
                                <span class="item-card-label">Desconto</span>
                                <span class="item-card-value">${item.desconto_legal}%</span>
                            </div>
                            <div class="item-card-field" style="grid-column: 1 / -1;">
                                <span class="item-card-label">Descrição</span>
                                <span class="item-card-value">${item.descricao}</span>
                            </div>
                            <div class="item-card-field">
                                <span class="item-card-label">Emolumentos</span>
                                <span class="item-card-value text-money">R$ ${formatMoney(item.emolumentos)}</span>
                            </div>
                            <div class="item-card-field">
                                <span class="item-card-label">FERC</span>
                                <span class="item-card-value text-money">R$ ${formatMoney(item.ferc)}</span>
                            </div>
                            <div class="item-card-field">
                                <span class="item-card-label">FADEP</span>
                                <span class="item-card-value text-money">R$ ${formatMoney(item.fadep)}</span>
                            </div>
                            <div class="item-card-field">
                                <span class="item-card-label">FEMP</span>
                                <span class="item-card-value text-money">R$ ${formatMoney(item.femp)}</span>
                            </div>
                            <div class="item-card-field">
                                <span class="item-card-label">FERRFIS</span>
                                <span class="item-card-value text-money">R$ ${formatMoney(item.ferrfis || 0)}</span>
                            </div>
                            <div class="item-card-field" style="grid-column: 1 / -1;">
                                <span class="item-card-label">Total</span>
                                <span class="item-card-value text-money" style="font-size: 18px; color: var(--brand-success);">R$ ${formatMoney(item.total)}</span>
                            </div>
                        </div>
                    </div>`;
                    
                    $('#modalItemsMobileView').append(card);
                });

                // Exibir o modal
                $('#modalVisualizarModelo').modal('show');
            }
        },
        error: function() {
            showAlert('Erro ao carregar itens do modelo.', 'error');
        }
    });
}

// ===================== EDITAR MODELO =====================
function editarModelo(id) {
    // Define o campo hidden
    $('#modelo_id_edit').val(id);
    $('#formHeaderText').text('Editar Modelo');
    $('#btnSalvarText').text('Atualizar Modelo');

    // Limpa o formulário
    $('#formModelo')[0].reset();
    $('#tabelaItensModelo').empty();
    $('#itemsMobileView').empty();

    $.ajax({
        url: 'carregar_modelo_orcamento.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                showAlert(response.error, 'error');
            } else if (response.itens) {
                // Preenche nome e descrição
                if (response.nome_modelo) {
                    $('#nome_modelo').val(response.nome_modelo);
                }
                if (response.descricao_modelo) {
                    $('#descricao_modelo').val(response.descricao_modelo);
                }

                // Inserir itens na tabela
                response.itens.forEach(function(item) {
                    const row = `
                    <tr>
                        <td>${item.ato || '—'}</td>
                        <td>${item.quantidade}</td>
                        <td>${item.desconto_legal}</td>
                        <td>${item.descricao}</td>
                        <td>${item.emolumentos}</td>
                        <td>${item.ferc}</td>
                        <td>${item.fadep}</td>
                        <td>${item.femp}</td>
                        <td>${(item.ferrfis !== undefined && item.ferrfis !== null) ? item.ferrfis : '0,00'}</td>
                        <td>${item.total}</td>
                        <td>
                            <button type="button" class="btn btn-delete btn-sm" onclick="removerItem(this)">
                                <i class="mdi mdi-delete"></i>
                            </button>
                        </td>
                    </tr>`;
                    $('#tabelaItensModelo').append(row);
                });

                // Sincroniza cards
                syncTableAndCards();

                // Rola até o formulário
                $('html, body').animate({ scrollTop: $('#formModelo').offset().top - 100 }, 'slow');
            }
        },
        error: function() {
            showAlert('Erro ao carregar modelo para edição.', 'error');
        }
    });
}

// ===================== EXCLUIR MODELO =====================
function excluirModelo(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Deseja realmente excluir este modelo?',
        text: 'Esta ação não pode ser desfeita!',
        showCancelButton: true,
        confirmButtonText: '<i class="mdi mdi-check"></i> Sim, excluir',
        cancelButtonText: '<i class="mdi mdi-close"></i> Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'excluir_modelo_orcamento.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        showAlert(response.error, 'error');
                    } else {
                        showAlert('Modelo excluído com sucesso!', 'success');
                        carregarModelos();
                    }
                },
                error: function() {
                    showAlert('Erro ao excluir o modelo.', 'error');
                }
            });
        }
    });
}
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>