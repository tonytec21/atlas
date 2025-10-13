<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  
date_default_timezone_set('America/Sao_Paulo');  

$issConfig     = json_decode(file_get_contents(__DIR__ . '/iss_config.json'), true);  
$issAtivo      = !empty($issConfig['ativo']);  
$issPercentual = isset($issConfig['percentual']) ? (float)$issConfig['percentual'] : 0;  
$issDescricao  = isset($issConfig['descricao'])   ? $issConfig['descricao']         : 'ISS sobre Emolumentos';  

/* -------------------------------------------------  
   Lista de atos que PODEM ter valor 0 (exceção)   */  
$atosSemValor = json_decode(  
    file_get_contents(__DIR__ . '/atos_valor_zero.json'),  
    true  
);  
?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Criar Ordem de Serviço</title>  
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../style/css/style.css">  
    <link rel="icon" href="../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">  
    <link rel="stylesheet" href="../style/sweetalert2.min.css">  
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
            --space-2xl: 48px;  
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

        /* ===================== PAGE HERO ===================== */  
        .page-hero {  
            background: var(--gradient-primary);  
            border-radius: var(--radius-xl);  
            padding: var(--space-xl);  
            margin-bottom: var(--space-xl);  
            box-shadow: var(--shadow-xl);  
           
        }  

        .title-row {  
            display: flex;  
            align-items: center;  
            gap: var(--space-md);  
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

        /* ===================== BOTÕES MODERNOS ===================== */  
        .btn {  
            border-radius: var(--radius-md);  
            font-weight: 600;  
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
            box-shadow: var(--shadow);  
            position: relative;  
            overflow: hidden;  
            margin: 2px;
            border: none;  
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

        .btn-warning {  
            background: var(--gradient-warning);  
            color: white;  
        }  

        .btn-danger {  
            background: var(--gradient-error);  
            color: white;  
        }  

        .btn-secondary {  
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);  
            color: white;  
        }  

        .btn-delete {  
            background: var(--gradient-error);  
            color: white;  
            padding: 6px 12px;  
            border-radius: var(--radius-md);  
            font-size: 13px;  
            transition: all 0.3s ease;  
        }  

        .btn-delete:hover {  
            transform: translateY(-2px);  
            box-shadow: var(--shadow-lg);  
        }  

        .btn-block {  
            padding: 16px;  
            font-size: 18px;  
            font-weight: 700;  
            letter-spacing: 0.02em;  
        }  

        .btn-adicionar-manual {  
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);  
        }  

        /* ===================== FORM CONTROLS ===================== */  
        .form-control, select.form-control {  
            border: 2px solid var(--border-primary);  
            border-radius: var(--radius-md);  
            padding: 10px 14px;  
            font-size: 14px;  
            transition: all 0.3s ease;  
            background: var(--bg-primary);  
            color: var(--text-primary);  
        }  

        .form-control:focus, select.form-control:focus {  
            border-color: var(--brand-primary);  
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);  
            outline: none;  
        }  

        .form-control[readonly] {  
            background: var(--bg-secondary);  
            cursor: not-allowed;  
        }  

        label {  
            font-weight: 700;  
            font-size: 13px;  
            color: var(--text-secondary);  
            margin-bottom: 6px;  
            letter-spacing: 0.02em;  
        }  

        textarea.form-control {  
            resize: vertical;  
            min-height: 100px;  
        }  

        /* ===================== SELECT MODELO ===================== */  
        .select-modelo-container {  
            background: linear-gradient(135deg, rgba(79,70,229,.05), rgba(99,102,241,.05));  
            border: 2px solid var(--border-primary);  
            border-radius: var(--radius-lg);  
            padding: var(--space-lg);  
            margin-bottom: var(--space-xl);  
        }  

        .select-modelo-container label {  
            font-size: 15px;  
            font-weight: 700;  
            color: var(--text-primary);  
            margin-bottom: var(--space-sm);  
            display: flex;  
            align-items: center;  
            gap: 8px;  
        }  

        /* ===================== TABELAS MODERNAS ===================== */  
        .table-modern {  
            border-collapse: separate;  
            border-spacing: 0;  
            border-radius: var(--radius-lg);  
            overflow: hidden;  
            box-shadow: var(--shadow);  
            background: var(--bg-elevated);  
        }  

        .table-modern thead th {  
            background: var(--bg-tertiary);  
            border-bottom: 2px solid var(--border-primary);  
            font-weight: 700;  
            text-transform: uppercase;  
            font-size: 11px;  
            letter-spacing: 0.05em;  
            color: var(--text-secondary);  
            padding: 12px 16px;  
        }  

        .table-modern tbody tr {  
            transition: all 0.2s ease;  
            cursor: move;  
        }  

        .table-modern tbody tr:hover {  
            background: var(--bg-secondary);  
        }  

        .table-modern tbody td {  
            padding: 12px 16px;  
            border-bottom: 1px solid var(--border-primary);  
            color: var(--text-primary);  
            vertical-align: middle;  
        }  

        .ui-state-highlight {  
            background: rgba(79,70,229,0.1) !important;  
            border: 2px dashed var(--brand-primary);  
        }  

        /* ===================== CARDS MOBILE ===================== */  
        @media (max-width: 768px) {  
            /* Ocultar tabela no mobile */  
            .table-responsive.mobile-hidden {  
                display: none;  
            }  

            /* Container de cards */  
            .mobile-cards {  
                display: block;  
            }  

            /* Card individual */  
            .item-card {  
                background: var(--bg-elevated);  
                border: 2px solid var(--border-primary);  
                border-radius: var(--radius-lg);  
                padding: 16px;  
                margin-bottom: 16px;  
                box-shadow: var(--shadow-md);  
                transition: all 0.3s ease;  
            }  

            .item-card:hover {  
                transform: translateY(-2px);  
                box-shadow: var(--shadow-lg);  
                border-color: var(--brand-primary);  
            }  

            /* Header do card */  
            .card-header-mobile {  
                display: flex;  
                justify-content: space-between;  
                align-items: center;  
                margin-bottom: 12px;  
                padding-bottom: 12px;  
                border-bottom: 2px solid var(--border-primary);  
            }  

            .card-number {  
                width: 36px;  
                height: 36px;  
                border-radius: 10px;  
                background: var(--gradient-primary);  
                color: white;  
                display: flex;  
                align-items: center;  
                justify-content: center;  
                font-weight: 800;  
                font-size: 16px;  
                box-shadow: var(--shadow);  
            }  

            .card-drag-handle {  
                width: 36px;  
                height: 36px;  
                border-radius: 10px;  
                background: var(--bg-tertiary);  
                color: var(--text-secondary);  
                display: flex;  
                align-items: center;  
                justify-content: center;  
                font-size: 18px;  
                cursor: move;  
            }  

            /* Informações do card */  
            .card-info {  
                display: grid;  
                gap: 10px;  
            }  

            .info-row {  
                display: flex;  
                justify-content: space-between;  
                align-items: center;  
                padding: 8px 0;  
            }  

            .info-label {  
                font-size: 11px;  
                font-weight: 700;  
                color: var(--text-tertiary);  
                text-transform: uppercase;  
                letter-spacing: 0.05em;  
            }  

            .info-value {  
                font-size: 14px;  
                font-weight: 600;  
                color: var(--text-primary);  
                text-align: right;  
            }  

            .info-value.highlight {  
                color: var(--brand-primary);  
                font-weight: 800;  
            }  

            /* Ato destacado */  
            .card-ato {  
                font-size: 15px;  
                font-weight: 800;  
                color: var(--brand-primary);  
                margin-bottom: 8px;  
                display: block;  
            }  

            /* Descrição */  
            .card-description {  
                font-size: 13px;  
                color: var(--text-secondary);  
                margin-bottom: 12px;  
                line-height: 1.5;  
            }  

            /* Valores em grid */  
            .valores-grid {  
                display: grid;  
                grid-template-columns: repeat(2, 1fr);  
                gap: 8px;  
                margin: 12px 0;  
                padding: 12px;  
                background: var(--bg-secondary);  
                border-radius: var(--radius-md);  
            }  

            .valor-item {  
                text-align: center;  
            }  

            .valor-label {  
                font-size: 10px;  
                font-weight: 700;  
                color: var(--text-tertiary);  
                text-transform: uppercase;  
                letter-spacing: 0.05em;  
                display: block;  
                margin-bottom: 4px;  
            }  

            .valor-value {  
                font-size: 13px;  
                font-weight: 700;  
                color: var(--text-primary);  
                display: block;  
            }  

            /* Ações do card */  
            .card-actions {  
                margin-top: 12px;  
                padding-top: 12px;  
                border-top: 2px solid var(--border-primary);  
            }  

            /* Botão full width no card */  
            .card-actions .btn {  
                width: 100%;  
                font-weight: 700;  
                padding: 12px;  
            }  

            /* Badge de quantidade */  
            .badge-qty {  
                background: var(--gradient-info);  
                color: white;  
                padding: 4px 12px;  
                border-radius: 20px;  
                font-size: 12px;  
                font-weight: 700;  
                display: inline-flex;  
                align-items: center;  
                gap: 4px;  
            }  

            /* Badge ISS */  
            .badge-iss {  
                background: var(--gradient-success);  
                color: white;  
                padding: 4px 10px;  
                border-radius: 20px;  
                font-size: 11px;  
                font-weight: 700;  
                display: inline-flex;  
                align-items: center;  
                gap: 4px;  
            }  

            /* Card ISS (fixo) */  
            .item-card.iss-card {  
                border-color: var(--brand-success);  
                background: linear-gradient(135deg, rgba(16,185,129,.05), rgba(5,150,105,.05));  
            }  

            .item-card.iss-card .card-number {  
                background: var(--gradient-success);  
            }  

            /* Hero responsivo */  
            .page-hero h1 {  
                font-size: 24px;  
            }  

            .title-icon {  
                width: 48px;  
                height: 48px;  
                font-size: 22px;  
            }  

            /* Formulário responsivo */  
            .btn-adicionar-manual {  
                margin-left: 0 !important;  
                margin-top: 10px;  
            }  
        }  

        /* Ocultar cards no desktop */  
        @media (min-width: 769px) {  
            .mobile-cards {  
                display: none;  
            }  
        }  

        /* ===================== HR MODERNO ===================== */  
        hr {  
            border: 0;  
            height: 2px;  
            background: linear-gradient(90deg, transparent, var(--border-primary), transparent);  
            margin: var(--space-xl) 0;  
        }  

        /* ===================== SECTION TITLE ===================== */  
        h4 {  
            font-weight: 800;  
            font-size: 20px;  
            color: var(--text-primary);  
            margin-bottom: var(--space-lg);  
            letter-spacing: -0.01em;  
        }  

        /* ===================== HEADER ACTIONS ===================== */  
        .header-actions {  
            display: flex;  
            flex-wrap: wrap;  
            gap: 10px;  
            justify-content: center;  
            margin-bottom: var(--space-xl);  
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

        /* ===================== RESPONSIVIDADE ===================== */  
        @media (max-width: 768px) {  
            .container {  
                padding: 15px;  
            }  

            .btn-sm {  
                font-size: 13px;  
                padding: 8px 12px;  
            }  

            .form-group {  
                margin-bottom: 20px;  
            }  
        }  

        /* ===================== ANIMAÇÕES ===================== */  
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

        .table-modern tbody tr {  
            animation: fadeInUp 0.3s ease;  
        }  

        /* ===================== MODAL MODERNO ===================== */  
        .modal-content {  
            border-radius: 16px;  
            border: 1px solid var(--border-primary);  
            box-shadow: var(--shadow-xl);  
            background: var(--bg-elevated);  
        }  

        .modal-header {  
            border-radius: 16px 16px 0 0;  
            padding: 16px 24px;  
            border-bottom: 1px solid var(--border-primary);  
        }  

        .modal-header.error {  
            background: var(--gradient-error);  
            color: white;  
        }  

        .modal-header.success {  
            background: var(--gradient-success);  
            color: white;  
        }  

        .modal-title {  
            font-weight: 700;  
            font-size: 18px;  
        }  

        .modal-body {  
            padding: 24px;  
        }  

        .modal-footer {  
            padding: 16px 24px;  
            border-top: 1px solid var(--border-primary);  
        }  

        .btn-close {  
            font-size: 1.5rem;  
            opacity: 0.7;  
            transition: all 0.2s ease;  
        }  

        .btn-close:hover {  
            opacity: 1;  
            transform: scale(1.1);  
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
                    <i class="fa fa-money" aria-hidden="true"></i>  
                </div>  
                <div>  
                    <h1>Criar Ordem de Serviço</h1>  
                </div>  
            </div>  
        </section>  
    
        <!-- ===================== HEADER ACTIONS ===================== -->  
        <div class="header-actions">  
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.open('tabela_de_emolumentos.php')">  
                <i class="fa fa-table" aria-hidden="true"></i> Tabela de Emolumentos  
            </button>  
            <a href="index.php" class="btn btn-secondary btn-sm">  
                <i class="fa fa-search" aria-hidden="true"></i> Ordens de Serviço  
            </a>  
        </div>  
        <div class="text-center">  
            <label for="modelo_orcamento">  
                <i class="fa fa-folder-open-o"></i>  
                Carregar Modelo de O.S:  
            </label>  
            <select id="modelo_orcamento" class="form-control w-50 mx-auto" onchange="carregarModeloSelecionado()">  
                <option value="">Selecione um modelo...</option>  
            </select>  
        </div>  

        <hr>  

        <!-- ===================== FORMULÁRIO PRINCIPAL ===================== -->  
        <form id="osForm" method="POST">  
            <div class="form-row">  
                <div class="form-group col-md-5">  
                    <label for="cliente">Apresentante:</label>  
                    <input type="text" class="form-control" id="cliente" name="cliente" required>  
                </div>  
                <div class="form-group col-md-3">  
                    <label for="cpf_cliente">CPF/CNPJ do Apresentante:</label>  
                    <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente">  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="base_calculo">Base de Cálculo:</label>  
                    <input type="text" class="form-control" id="base_calculo" name="base_calculo">  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="total_os">Valor Total da OS:</label>  
                    <input type="text" class="form-control" id="total_os" name="total_os" readonly>  
                </div>  
            </div>  

            <div class="form-row">  
                <div class="form-group col-md-12">  
                    <label for="descricao_os">Título da OS:</label>  
                    <input type="text" class="form-control" id="descricao_os" name="descricao_os">  
                </div>  
            </div>  

            <div class="form-row">  
                <div class="form-group col-md-3">  
                    <label for="ato">Código do Ato:</label>  
                    <input type="text" class="form-control" id="ato" name="ato" required pattern="[0-9.]+">  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="quantidade">Quantidade:</label>  
                    <input type="number" class="form-control" id="quantidade" name="quantidade" value="1" required min="1">  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="desconto_legal">Desconto Legal (%):</label>  
                    <input type="number" class="form-control" id="desconto_legal" name="desconto_legal" value="0" required min="0" max="100">  
                </div>  
                <div class="form-group col-md-5" style="display: flex; flex-wrap: wrap; align-items: center; margin-top: 29px; gap: 10px;">  
                    <button type="button" style="flex: 1; min-width: 140px;" class="btn btn-primary" onclick="buscarAto()">  
                        <i class="fa fa-search" aria-hidden="true"></i> Buscar Ato  
                    </button>  
                    <button type="button" style="flex: 1; min-width: 200px;" class="btn btn-secondary btn-adicionar-manual" onclick="adicionarAtoManual()">  
                        <i class="fa fa-i-cursor" aria-hidden="true"></i> Adicionar Ato Manualmente  
                    </button>  
                </div>  
            </div>  

            <div class="form-row">  
                <div class="form-group col-md-12">  
                    <label for="descricao">Descrição:</label>  
                    <input type="text" class="form-control" id="descricao" name="descricao" required readonly>  
                </div>  
            </div>  

            <div class="form-row">  
                <div class="form-group col-md-2">  
                    <label for="emolumentos">Emolumentos:</label>  
                    <input type="text" class="form-control" id="emolumentos" name="emolumentos" readonly>  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="ferc">FERC:</label>  
                    <input type="text" class="form-control" id="ferc" name="ferc" readonly>  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="fadep">FADEP:</label>  
                    <input type="text" class="form-control" id="fadep" name="fadep" readonly>  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="femp">FEMP:</label>  
                    <input type="text" class="form-control" id="femp" name="femp" readonly>  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="total">Total:</label>  
                    <input type="text" class="form-control" id="total" name="total" required readonly>  
                </div>  
                <div class="form-group col-md-2" style="margin-top: 29px;">  
                    <button type="submit" style="width: 100%" class="btn btn-success">  
                        <i class="fa fa-plus" aria-hidden="true"></i> Adicionar à OS  
                    </button>  
                </div>  
            </div>  
        </form>  

        <!-- ===================== ITENS DA OS ===================== -->  
        <div id="osItens" class="mt-4">  
            <h4>Itens da Ordem de Serviço</h4>  
            
            <!-- TABELA DESKTOP -->  
            <div class="table-responsive mobile-hidden">  
                <table class="table table-modern">  
                    <thead>  
                        <tr>  
                            <th>#</th>  
                            <th>Ato</th>  
                            <th>Quantidade</th>  
                            <th>Desconto Legal (%)</th>  
                            <th>Descrição</th>  
                            <th>Emolumentos</th>  
                            <th>FERC</th>  
                            <th>FADEP</th>  
                            <th>FEMP</th>  
                            <th>Total</th>  
                            <th>Ações</th>  
                        </tr>  
                    </thead>  
                    <tbody id="itensTable">  
                        <!-- Itens adicionados vão aqui -->  
                    </tbody>  
                </table>  
            </div>  

            <!-- CARDS MOBILE -->  
            <div class="mobile-cards" id="itensCards">  
                <!-- Cards serão inseridos aqui via JavaScript -->  
            </div>  
        </div>  

        <hr>  

        <!-- ===================== OBSERVAÇÕES ===================== -->  
        <div class="form-group">  
            <label for="observacoes">Observações:</label>  
            <textarea class="form-control" id="observacoes" name="observacoes" rows="4"></textarea>  
        </div>  

        <!-- ===================== BOTÃO SALVAR ===================== -->  
        <button type="button" id="btnSalvarOS" class="btn btn-primary btn-block" onclick="salvarOS()" disabled>  
            <i class="fa fa-floppy-o" aria-hidden="true"></i> SALVAR OS  
        </button>  
    </div>  
</div>  

<!-- ===================== MODAL ===================== -->  
<div class="modal fade" id="alertModal" tabindex="-1" role="dialog" aria-labelledby="alertModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered" role="document">  
        <div class="modal-content">  
            <div class="modal-header" id="alertModalHeader">  
                <h5 class="modal-title" id="alertModalLabel">Alerta</h5>  
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">  
                    <span aria-hidden="true">&times;</span>  
                </button>  
            </div>  
            <div class="modal-body" id="alertModalBody">  
                <!-- Alerta vai aqui -->  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- ===================== SCROLL TO TOP ===================== -->  
<button id="scrollTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">  
    <i class="fa fa-arrow-up"></i>  
</button>  

<!-- ===================== SCRIPTS ===================== -->  
<script src="../script/jquery-3.5.1.min.js"></script>  
<script src="../script/jquery-ui.min.js"></script>  
<script src="../script/jquery.mask.min.js"></script>  
<script src="../script/bootstrap.bundle.min.js"></script>  
<script src="../script/bootstrap.min.js"></script>   
<script src="../script/sweetalert2.js"></script>  

<script>  
    // ===================== CONFIG =====================  
    const ISS_CONFIG = {  
        ativo: <?php echo $issAtivo ? 'true' : 'false'; ?>,  
        percentual: <?php echo $issPercentual; ?>,  
        descricao: "<?php echo addslashes($issDescricao); ?>"  
    };  

    const ATOS_SEM_VALOR = <?php echo json_encode($atosSemValor, JSON_UNESCAPED_UNICODE); ?>;  

    // ===================== SCROLL TO TOP =====================  
    window.addEventListener('scroll', function() {  
        const scrollTop = document.getElementById('scrollTop');  
        if (window.pageYOffset > 300) {  
            scrollTop.style.opacity = '1';  
            scrollTop.style.pointerEvents = 'auto';  
        } else {  
            scrollTop.style.opacity = '0';  
            scrollTop.style.pointerEvents = 'none';  
        }  
    });  

    // ===================== UPDATE BUTTON STATE =====================  
    function updateSalvarButtonState() {  
        const hasItems   = $('#itensTable tr').length > 0;  
        const hasCliente = $('#cliente').val().trim().length > 0;  
        $('#btnSalvarOS').prop('disabled', !(hasItems && hasCliente));  
    }  

    // ===================== ATUALIZAR CARDS MOBILE =====================  
    function atualizarCardsMobile() {  
        const $container = $('#itensCards');  
        $container.empty();  

        $('#itensTable tr').each(function(index) {  
            const $tr = $(this);  
            const ordem = $tr.find('td').eq(0).text();  
            const ato = $tr.find('td').eq(1).text();  
            const quantidade = $tr.find('td').eq(2).text();  
            const desconto = $tr.find('td').eq(3).text();  
            const descricao = $tr.find('td').eq(4).text();  
            const emolumentos = $tr.find('td').eq(5).text();  
            const ferc = $tr.find('td').eq(6).text();  
            const fadep = $tr.find('td').eq(7).text();  
            const femp = $tr.find('td').eq(8).text();  
            const total = $tr.find('td').eq(9).text();  
            const isISS = $tr.attr('id') === 'ISS_ROW';  

            const cardClass = isISS ? 'item-card iss-card' : 'item-card';  
            const badgeExtra = isISS ? '<span class="badge-iss"><i class="fa fa-lock"></i> Item Fixo</span>' : '';  

            const card = `  
                <div class="${cardClass}" data-index="${index}">  
                    <div class="card-header-mobile">  
                        <span class="card-number">${ordem}</span>  
                        ${isISS ? badgeExtra : '<span class="card-drag-handle"><i class="fa fa-bars"></i></span>'}  
                    </div>  

                    <span class="card-ato">  
                        <i class="fa fa-file-text-o"></i> ${ato}  
                    </span>  

                    ${descricao ? `<div class="card-description">${descricao}</div>` : ''}  

                    <div class="info-row">  
                        <span class="info-label">Quantidade</span>  
                        <span class="info-value">  
                            <span class="badge-qty">  
                                <i class="fa fa-cubes"></i> ${quantidade}  
                            </span>  
                        </span>  
                    </div>  

                    ${desconto !== '0%' ? `  
                    <div class="info-row">  
                        <span class="info-label">Desconto Legal</span>  
                        <span class="info-value">${desconto}</span>  
                    </div>` : ''}  

                    <div class="valores-grid">  
                        <div class="valor-item">  
                            <span class="valor-label">Emolumentos</span>  
                            <span class="valor-value">R$ ${emolumentos}</span>  
                        </div>  
                        <div class="valor-item">  
                            <span class="valor-label">FERC</span>  
                            <span class="valor-value">R$ ${ferc}</span>  
                        </div>  
                        <div class="valor-item">  
                            <span class="valor-label">FADEP</span>  
                            <span class="valor-value">R$ ${fadep}</span>  
                        </div>  
                        <div class="valor-item">  
                            <span class="valor-label">FEMP</span>  
                            <span class="valor-value">R$ ${femp}</span>  
                        </div>  
                    </div>  

                    <div class="info-row" style="margin-top: 8px; padding-top: 12px; border-top: 2px solid var(--border-primary);">  
                        <span class="info-label" style="font-size: 13px;">Valor Total</span>  
                        <span class="info-value highlight" style="font-size: 18px;">  
                            R$ ${total}  
                        </span>  
                    </div>  

                    ${!isISS ? `  
                    <div class="card-actions">  
                        <button type="button" class="btn btn-delete btn-sm" onclick="removerItemPorIndice(${index})">  
                            <i class="fa fa-trash"></i> Remover Item  
                        </button>  
                    </div>` : ''}  
                </div>  
            `;  

            $container.append(card);  
        });  

        // Sortable nos cards mobile  
        if (window.innerWidth <= 768) {  
            $container.sortable({  
                handle: '.card-drag-handle',  
                update: function() {  
                    sincronizarOrdemCardsParaTabela();  
                }  
            });  
        }  
    }  

    // ===================== SINCRONIZAR ORDEM CARDS → TABELA =====================  
    function sincronizarOrdemCardsParaTabela() {  
        const novaOrdem = [];  
        $('#itensCards .item-card').each(function() {  
            const index = $(this).data('index');  
            novaOrdem.push(index);  
        });  

        const $tbody = $('#itensTable');  
        const $rows = $tbody.find('tr').detach();  

        novaOrdem.forEach(function(index) {  
            $tbody.append($rows.eq(index));  
        });  

        atualizarOrdemExibicao();  
    }  

    // ===================== REMOVER ITEM POR ÍNDICE =====================  
    function removerItemPorIndice(index) {  
        const $row = $('#itensTable tr').eq(index);  
        const totalItem = parseFloat($row.find('td').eq(9).text().replace(/\./g, '').replace(',', '.')) || 0;  

        var totalOS = parseFloat($('#total_os').val().replace(/\./g, '').replace(',', '.')) || 0;  
        totalOS -= totalItem;  
        $('#total_os').val(totalOS.toFixed(2).replace('.', ','));  

        $row.remove();  
        atualizarISS();  
        atualizarOrdemExibicao();  
        atualizarCardsMobile();  
        updateSalvarButtonState();  
    }  

    // ===================== DOCUMENT READY =====================  
    $(document).ready(function() {  
        // Sortable na tabela desktop  
        $("#itensTable").sortable({  
            placeholder: "ui-state-highlight",  
            update: function(event, ui) {  
                atualizarOrdemExibicao();  
                atualizarCardsMobile();  
            }  
        });  
        $("#itensTable").disableSelection();  

        // Carregar modelos  
        $.ajax({  
            url: 'listar_todos_modelos.php',  
            method: 'GET',  
            dataType: 'json',  
            success: function(response) {  
                if (response.modelos) {  
                    response.modelos.forEach(function(modelo) {  
                        $('#modelo_orcamento').append(  
                            $('<option>', {  
                                value: modelo.id,  
                                text: modelo.nome_modelo  
                            })  
                        );  
                    });  
                }  
            }  
        });  

        // Carregar modo  
        $.ajax({  
            url: '../load_mode.php',  
            method: 'GET',  
            success: function(mode) {  
                $('body').removeClass('light-mode dark-mode').addClass(mode);  
            }  
        });  

        // Máscara CPF/CNPJ  
        $('#cpf_cliente').on('blur', function() {  
            var cpfCnpj = $(this).val().replace(/\D/g, '');  
            if (cpfCnpj.length === 11) {  
                $(this).mask('000.000.000-00', {reverse: true});  
                if (!validarCPF($(this).val())) {  
                    showAlert('CPF inválido!', 'error');  
                    $(this).val('');  
                }  
            } else if (cpfCnpj.length === 14) {  
                $(this).mask('00.000.000/0000-00', {reverse: true});  
                if (!validarCNPJ($(this).val())) {  
                    showAlert('CNPJ inválido!', 'error');  
                    $(this).val('');  
                }  
            } else if (cpfCnpj.length > 0) {  
                showAlert('CPF ou CNPJ inválido!', 'error');  
                $(this).val('');  
            }  
        });  

        // Sanitizar Apresentante  
        $('#cliente')  
            .on('keypress', function (e) {  
                if (e.key === "'" || e.key === '"') {  
                    e.preventDefault();  
                }  
            })  
            .on('input', function () {  
                this.value = this.value.replace(/["'""'']/g, '');  
                updateSalvarButtonState();  
            })  
            .on('paste', function () {  
                const el = this;  
                setTimeout(function () {  
                    el.value = el.value.replace(/["'""'']/g, '');  
                    updateSalvarButtonState();  
                }, 0);  
            })  
            .on('blur', updateSalvarButtonState);  

        // Máscaras de valores  
        $('#base_calculo, #emolumentos, #ferc, #fadep, #femp, #total').mask('#.##0,00', {reverse: true});  

        // Submit do formulário  
        $('#osForm').on('submit', function(e) {  
            e.preventDefault();  
            
            var ato = $('#ato').val();  
            var quantidade = parseInt($('#quantidade').val(), 10);  
            var descontoLegal = $('#desconto_legal').val();  
            var descricao = $('#descricao').val();  
            var emolumentos = parseFloat($('#emolumentos').val().replace(/\./g, '').replace(',', '.'));   
            var ferc = parseFloat($('#ferc').val().replace(/\./g, '').replace(',', '.')) || 0;  
            var fadep = parseFloat($('#fadep').val().replace(/\./g, '').replace(',', '.')) || 0;  
            var femp = parseFloat($('#femp').val().replace(/\./g, '').replace(',', '.')) || 0;  
            var total = parseFloat($('#total').val().replace(/\./g, '').replace(',', '.')) || 0;  

            if (isNaN(emolumentos)) {  
                showAlert('O valor dos emolumentos deve ser um número válido.', 'error');  
                return;  
            }  

            var ordemExibicao = $('#itensTable tr').length + 1;  

            const codigoAto  = ato.trim();  
            const isExcecao  = ATOS_SEM_VALOR.includes(codigoAto);  

            if ((isNaN(total) || total <= 0) && !isExcecao) {  
                showAlert("Por favor, preencha o Valor Total do ato antes de adicionar à O.S.", 'error');  
                return;  
            }  
            
            var item = '<tr>' +  
                '<td>' + ordemExibicao + '</td>' +   
                '<td>' + ato + '</td>' +  
                '<td>' + quantidade + '</td>' +  
                '<td>' + descontoLegal + '%</td>' +  
                '<td>' + descricao + '</td>' +  
                '<td>' + emolumentos.toFixed(2).replace('.', ',') + '</td>' +  
                '<td>' + ferc.toFixed(2).replace('.', ',') + '</td>' +  
                '<td>' + fadep.toFixed(2).replace('.', ',') + '</td>' +  
                '<td>' + femp.toFixed(2).replace('.', ',') + '</td>' +  
                '<td>' + total.toFixed(2).replace('.', ',') + '</td>' +  
                '<td>' +
                '<button type="button" class="btn btn-warning btn-sm" onclick="marcarItemIsento(this)">' +
                    '<i class="fa fa-ban"></i> Ato Isento' +
                '</button> ' +
                '<button type="button" title="Remover" class="btn btn-delete btn-sm" onclick="removerItem(this)">' +
                    '<i class="fa fa-trash" aria-hidden="true"></i>' +
                '</button>' +
                '</td>' +  
                '</tr>';
                  
            $('#itensTable').append(item);  
            atualizarISS();  
            atualizarCardsMobile();  
            updateSalvarButtonState();  

            // Limpar campos  
            $('#ato').val('');  
            $('#quantidade').val('1');  
            $('#desconto_legal').val('0');  
            $('#descricao').val('');  
            $('#emolumentos').val('');  
            $('#ferc').val('');  
            $('#fadep').val('');  
            $('#femp').val('');  
            $('#total').val('');  

            $('#descricao').prop('readonly', true);  
            $('#emolumentos').prop('readonly', true);  
            $('#ferc').prop('readonly', true);  
            $('#fadep').prop('readonly', true);  
            $('#femp').prop('readonly', true);  
            $('#total').prop('readonly', true);  
        });  

        // Filtros de input  
        $('#ato').on('input', function() {  
            this.value = this.value.replace(/[^0-9a-ab-bc-cd-d.]/g, '');  
        });  

        $('#quantidade, #desconto_legal').on('input', function() {  
            this.value = this.value.replace(/[^0-9]/g, '');  
        });  

        updateSalvarButtonState();  
    });  

    // ===================== ATUALIZAR ORDEM EXIBIÇÃO =====================  
    function atualizarOrdemExibicao() {  
        $('#itensTable tr').each(function(index) {  
            $(this).find('td:first').text(index + 1);  
        });  
    }  

    // ===================== SHOW ALERT =====================  
    function showAlert(message, type) {  
        let iconType = type === 'error' ? 'error' : 'success';  
        Swal.fire({  
            icon: iconType,  
            title: type === 'error' ? 'Erro!' : 'Sucesso!',  
            text: message,  
            confirmButtonText: 'OK',  
            confirmButtonColor: type === 'error' ? '#ef4444' : '#10b981'  
        });  
    }  

    // ===================== BUSCAR ATO =====================  
    function buscarAto() {  
        var ato = $('#ato').val();  
        var quantidade = $('#quantidade').val();  
        var descontoLegal = $('#desconto_legal').val();  

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
                        var emolumentos = parseFloat(response.EMOLUMENTOS) * quantidade;  
                        var ferc = parseFloat(response.FERC) * quantidade;  
                        var fadep = parseFloat(response.FADEP) * quantidade;  
                        var femp = parseFloat(response.FEMP) * quantidade;  

                        var desconto = descontoLegal / 100;  
                        emolumentos = emolumentos * (1 - desconto);  
                        ferc = ferc * (1 - desconto);  
                        fadep = fadep * (1 - desconto);  
                        femp = femp * (1 - desconto);  

                        if (ATOS_SEM_VALOR.includes(ato.trim())) {  
                            emolumentos = ferc = fadep = femp = 0;  
                        }  

                        $('#descricao').val(response.DESCRICAO);  
                        $('#emolumentos').val(emolumentos.toFixed(2).replace('.', ','));  
                        $('#ferc').val(ferc.toFixed(2).replace('.', ','));  
                        $('#fadep').val(fadep.toFixed(2).replace('.', ','));  
                        $('#femp').val(femp.toFixed(2).replace('.', ','));  
                        $('#total').val((emolumentos + ferc + fadep + femp).toFixed(2).replace('.', ','));  
                    } catch (e) {  
                        showAlert('Erro ao processar os dados do ato.', 'error');  
                    }  
                }  
            },  
            error: function() {  
                showAlert('Erro ao buscar o ato', 'error');  
            }  
        });  
    }  

    // ===================== ATUALIZAR ISS =====================  
    function atualizarISS() {  
        let totalEmol = 0;  
        $('#itensTable tr').each(function () {  
            if ($(this).attr('id') !== 'ISS_ROW') {  
                totalEmol += parseFloat($(this).find('td').eq(5).text()  
                                        .replace(/\./g, '').replace(',', '.')) || 0;  
            }  
        });  

        if (ISS_CONFIG.ativo) {  
            const baseISS  = totalEmol * 0.88;  
            const valorISS = baseISS * (ISS_CONFIG.percentual / 100);  

            let $linhaISS = $('#ISS_ROW');  
            if ($linhaISS.length === 0) {  
                const ordem = $('#itensTable tr').length + 1;  
                $('#itensTable').append(`  
                    <tr id="ISS_ROW" data-tipo="iss">  
                        <td>${ordem}</td>  
                        <td>ISS</td>  
                        <td>1</td>  
                        <td>0%</td>  
                        <td>${ISS_CONFIG.descricao}</td>  
                        <td>${valorISS.toFixed(2).replace('.', ',')}</td>  
                        <td>0,00</td>  
                        <td>0,00</td>  
                        <td>0,00</td>  
                        <td>${valorISS.toFixed(2).replace('.', ',')}</td>  
                        <td><span class="text-muted" title="Item fixo">  
                                <i class="fa fa-lock"></i></span></td>  
                    </tr>`);  
            } else {  
                $linhaISS.find('td').eq(5).text(valorISS.toFixed(2).replace('.', ','));  
                $linhaISS.find('td').eq(9).text(valorISS.toFixed(2).replace('.', ','));  
            }  
        } else {  
            $('#ISS_ROW').remove();  
        }  

        let totalOS = 0;  
        $('#itensTable tr').each(function () {  
            totalOS += parseFloat($(this).find('td').eq(9).text()  
                                  .replace(/\./g, '').replace(',', '.')) || 0;  
        });  
        $('#total_os').val(totalOS.toFixed(2).replace('.', ','));  

        atualizarCardsMobile();  
    }  

    // ===================== ADICIONAR ATO MANUAL =====================  
    function adicionarAtoManual() {  
        $('#ato').val('0');                  
        $('#descricao').val('').prop('readonly', false);  
        $('#emolumentos').val('0,00').prop('readonly', false);  
        $('#ferc').val('0,00').prop('readonly', false);  
        $('#fadep').val('0,00').prop('readonly', false);  
        $('#femp').val('0,00').prop('readonly', false);  
        $('#total').prop('readonly', false);  
    }  

    // ===================== REMOVER ITEM =====================
    function removerItem(button) {  
        var row = $(button).closest('tr');  
        var totalItem = parseFloat(row.find('td').eq(9).text().replace(/\./g, '').replace(',', '.')) || 0;  

        var totalOS = parseFloat($('#total_os').val().replace(/\./g, '').replace(',', '.')) || 0;  
        totalOS -= totalItem;  
        $('#total_os').val(totalOS.toFixed(2).replace('.', ','));  

        row.remove();  
        atualizarISS();  
        atualizarOrdemExibicao();  
        atualizarCardsMobile();  
        updateSalvarButtonState();  
    }

    // ===================== MARCAR ITEM COMO ISENTO =====================
    window.marcarItemIsento = function(btn){
        const $tr = $(btn).closest('tr');
        const $tds = $tr.find('td');

        // 1) Zera os valores (colunas 5..9: Emol., FERC, FADEP, FEMP, Total)
        for (let i = 5; i <= 9; i++) {
            $tds.eq(i).text('0,00');
        }

        // 2) Anexa " (isento)" ao código do ato (coluna 1) caso ainda não exista
        const $tdAto = $tds.eq(1);
        const atoTxt = ($tdAto.text() || '').trim();
        if (!/\(isento\)$/i.test(atoTxt)) {
            $tdAto.text(atoTxt + ' (isento)');
        }

        // 3) Desabilita o botão para evitar reaplicações
        $(btn).prop('disabled', true).text('Isento aplicado');

        // 4) Recalcula ISS e total
        atualizarISS();
        atualizarCardsMobile();
    };

    // ===================== SALVAR OS =====================  
    function salvarOS() {  
        const clientePreenchido = $('#cliente').val().trim().length > 0;  
        const temItens          = $('#itensTable tr').length > 0;  

        if (!clientePreenchido) {  
            showAlert('Preencha o campo "Apresentante" antes de salvar.', 'error');  
            return;  
        }  
        if (!temItens) {  
            showAlert('Adicione ao menos um ato à OS antes de salvar.', 'error');  
            return;  
        }  

        $('#btnSalvarOS').prop('disabled', true);  

        var cliente = $('#cliente').val().replace(/["'""'']/g, '');  
        var cpf_cliente = $('#cpf_cliente').val();  
        var total_os = $('#total_os').val().replace(/\./g, '').replace(',', '.');  
        var descricao_os = $('#descricao_os').val();  
        var observacoes = $('#observacoes').val();  
        var base_calculo = $('#base_calculo').val().replace(/\./g, '').replace(',', '.');  
        var itens = [];  

        $('#itensTable tr').each(function(index) {  
            var ato = $(this).find('td').eq(1).text();  
            var quantidade = $(this).find('td').eq(2).text();  
            var desconto_legal = $(this).find('td').eq(3).text().replace('%', '');  
            var descricao = $(this).find('td').eq(4).text();  
            var emolumentos = $(this).find('td').eq(5).text().replace(/\./g, '').replace(',', '.');  
            var ferc = $(this).find('td').eq(6).text().replace(/\./g, '').replace(',', '.');  
            var fadep = $(this).find('td').eq(7).text().replace(/\./g, '').replace(',', '.');  
            var femp = $(this).find('td').eq(8).text().replace(/\./g, '').replace(',', '.');  
            var total = $(this).find('td').eq(9).text().replace(/\./g, '').replace(',', '.');  
            var ordem_exibicao = index + 1;  

            itens.push({  
                ato: ato,  
                quantidade: quantidade,  
                desconto_legal: desconto_legal,  
                descricao: descricao,  
                emolumentos: emolumentos,  
                ferc: ferc,  
                fadep: fadep,  
                femp: femp,  
                total: total,  
                ordem_exibicao: ordem_exibicao  
            });  
        });  

        $.ajax({  
            url: 'salvar_os.php',  
            type: 'POST',  
            data: {  
                cliente: cliente,  
                cpf_cliente: cpf_cliente,  
                total_os: total_os,  
                descricao_os: descricao_os,  
                observacoes: observacoes,  
                base_calculo: base_calculo,  
                itens: itens  
            },  
            success: function(response) {  
                try {  
                    var res = JSON.parse(response);  
                    if (res.error) {  
                        showAlert(res.error, 'error');  
                        $('#btnSalvarOS').prop('disabled', false);  
                    } else {  
                        showAlert('Ordem de Serviço salva com sucesso!', 'success');  
                        setTimeout(function() {  
                            window.location.href = 'visualizar_os.php?id=' + res.id;  
                        }, 2000);  
                    }  
                } catch (e) {  
                    showAlert('Erro ao processar a resposta do servidor.', 'error');  
                    $('#btnSalvarOS').prop('disabled', false);  
                }  
            },  
            error: function() {  
                showAlert('Erro ao salvar a Ordem de Serviço', 'error');  
                $('#btnSalvarOS').prop('disabled', false);  
            }  
        });  
    }  

    // ===================== VALIDAR CPF =====================  
    function validarCPF(cpf) {  
        cpf = cpf.replace(/[^\d]+/g, '');  
        if (cpf.length !== 11 || cpf === "00000000000") return false;  
        let soma = 0, resto;  
        for (let i = 1; i <= 9; i++) soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);  
        resto = (soma * 10) % 11;  
        if ((resto === 10) || (resto === 11)) resto = 0;  
        if (resto !== parseInt(cpf.substring(9, 10))) return false;  
        soma = 0;  
        for (let i = 1; i <= 10; i++) soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);  
        resto = (soma * 10) % 11;  
        if ((resto === 10) || (resto === 11)) resto = 0;  
        if (resto !== parseInt(cpf.substring(10, 11))) return false;  
        return true;  
    }  

    // ===================== VALIDAR CNPJ =====================  
    function validarCNPJ(cnpj) {  
        cnpj = cnpj.replace(/[^\d]+/g, '');  
        if (cnpj.length !== 14 || cnpj === "00000000000000") return false;  
        let tamanho = cnpj.length - 2;  
        let numeros = cnpj.substring(0, tamanho);  
        let digitos = cnpj.substring(tamanho);  
        let soma = 0, pos = tamanho - 7;  
        for (let i = tamanho; i >= 1; i--) {  
            soma += numeros.charAt(tamanho - i) * pos--;  
            if (pos < 2) pos = 9;  
        }  
        let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;  
        if (resultado !== parseInt(digitos.charAt(0))) return false;  
        tamanho = tamanho + 1;  
        numeros = cnpj.substring(0, tamanho);  
        soma = 0;  
        pos = tamanho - 7;  
        for (let i = tamanho; i >= 1; i--) {  
            soma += numeros.charAt(tamanho - i) * pos--;  
            if (pos < 2) pos = 9;  
        }  
        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;  
        if (resultado !== parseInt(digitos.charAt(1))) return false;  
        return true;  
    }  

        // ===================== CARREGAR MODELO SELECIONADO =====================
    function carregarModeloSelecionado() {
        const idModelo = $('#modelo_orcamento').val();
        if (!idModelo) return;

        $.ajax({
            url: 'carregar_modelo_orcamento.php',
            type: 'GET',
            data: { id: idModelo },
            dataType: 'json',
            success: function (response) {
                if (response.error) {
                    showAlert(response.error, 'error');
                    return;
                }

                if (response.itens) {
                    response.itens.forEach(function (item) {
                        const emolumentos = parseFloat((item.emolumentos || '0').replace(',', '.'));
                        const ferc        = parseFloat((item.ferc        || '0').replace(',', '.'));
                        const fadep       = parseFloat((item.fadep       || '0').replace(',', '.'));
                        const femp        = parseFloat((item.femp        || '0').replace(',', '.'));
                        const total       = parseFloat((item.total       || '0').replace(',', '.'));

                        const ordemExibicao = $('#itensTable tr').length + 1;

                        const row = `
                            <tr>
                                <td>${ordemExibicao}</td>
                                <td>${item.ato}</td>
                                <td>${item.quantidade}</td>
                                <td>${item.desconto_legal}%</td>
                                <td>${item.descricao}</td>
                                <td>${emolumentos.toFixed(2).replace('.', ',')}</td>
                                <td>${ferc.toFixed(2).replace('.', ',')}</td>
                                <td>${fadep.toFixed(2).replace('.', ',')}</td>
                                <td>${femp.toFixed(2).replace('.', ',')}</td>
                                <td>${total.toFixed(2).replace('.', ',')}</td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="marcarItemIsento(this)">
                                        <i class="fa fa-ban"></i> Ato Isento
                                    </button>
                                    <button type="button" title="Remover"
                                            class="btn btn-delete btn-sm"
                                            onclick="removerItem(this)">
                                        <i class="fa fa-trash" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>`;
                        $('#itensTable').append(row);
                    });

                    atualizarISS();
                    atualizarCardsMobile();
                    updateSalvarButtonState();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAlert('Erro ao carregar o modelo selecionado.', 'error');
            }
        });
    }
</script>

<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>