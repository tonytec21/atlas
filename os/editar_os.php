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
   Lista de atos que podem ter valor 0 (exceção)
   -------------------------------------------------*/
$atosSemValor = json_decode(
    file_get_contents(__DIR__ . '/atos_valor_zero.json'),
    true
);

if (!isset($_GET['id'])) {
    die('ID da OS não fornecido');
}

$id = $_GET['id'];
$usuario = $_SESSION['username'];

// Buscar dados da OS e copiar para as tabelas de log
try {
    $conn = getDatabaseConnection();
    
    // Iniciar transação
    $conn->beginTransaction();

    // Buscar dados da OS
    $stmt = $conn->prepare("SELECT * FROM ordens_de_servico WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $os = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$os) {
        die('OS não encontrada');
    }

    // Inserir dados da OS na tabela de logs
    $stmt = $conn->prepare("INSERT INTO logs_ordens_de_servico (ordem_de_servico_id, cliente, cpf_cliente, total_os, descricao_os, observacoes, criado_por, editado_por, data_edicao) VALUES (:ordem_de_servico_id, :cliente, :cpf_cliente, :total_os, :descricao_os, :observacoes, :criado_por, :editado_por, NOW())");
    $stmt->bindParam(':ordem_de_servico_id', $id);
    $stmt->bindParam(':cliente', $os['cliente']);
    $stmt->bindParam(':cpf_cliente', $os['cpf_cliente']);
    $stmt->bindParam(':total_os', $os['total_os']);
    $stmt->bindParam(':descricao_os', $os['descricao_os']);
    $stmt->bindParam(':observacoes', $os['observacoes']);
    $stmt->bindParam(':criado_por', $os['criado_por']);
    $stmt->bindParam(':editado_por', $usuario);
    $stmt->execute();
    
    $log_os_id = $conn->lastInsertId();

    // Buscar itens da OS
    $stmt = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Inserir itens da OS na tabela de logs
    $stmt = $conn->prepare("INSERT INTO logs_ordens_de_servico_itens (ordem_servico_id, ato, quantidade, desconto_legal, descricao, emolumentos, ferc, fadep, femp, total, quantidade_liquidada, status, ordem_exibicao) VALUES (:ordem_servico_id, :ato, :quantidade, :desconto_legal, :descricao, :emolumentos, :ferc, :fadep, :femp, :total, :quantidade_liquidada, :status, :ordem_exibicao)");
    
    foreach ($itens as $item) {
        $stmt->bindParam(':ordem_servico_id', $log_os_id);
        $stmt->bindParam(':ato', $item['ato']);
        $stmt->bindParam(':quantidade', $item['quantidade']);
        $stmt->bindParam(':desconto_legal', $item['desconto_legal']);
        $stmt->bindParam(':descricao', $item['descricao']);
        $stmt->bindParam(':emolumentos', $item['emolumentos']);
        $stmt->bindParam(':ferc', $item['ferc']);
        $stmt->bindParam(':fadep', $item['fadep']);
        $stmt->bindParam(':femp', $item['femp']);
        $stmt->bindParam(':total', $item['total']);
        $stmt->bindParam(':quantidade_liquidada', $item['quantidade_liquidada']);
        $stmt->bindParam(':status', $item['status']);
        $stmt->bindParam(':ordem_exibicao', $item['ordem_exibicao']);
        $stmt->execute();
    }

    // Confirmar transação
    $conn->commit();
} catch (PDOException $e) {
    // Reverter transação em caso de erro
    $conn->rollBack();
    die('Erro ao buscar dados da OS: ' . $e->getMessage());
}
?>
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Editar Ordem de Serviço</title>  
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../style/css/style.css">  
    <link rel="icon" href="../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">  
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">  
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

        .btn-edit {  
            background: var(--gradient-info);  
            color: white;  
            padding: 6px 12px;  
            border-radius: var(--radius-md);  
            font-size: 13px;  
            transition: all 0.3s ease;  
            margin-right: 4px;  
        }  

        .btn-edit:hover {  
            transform: translateY(-2px);  
            box-shadow: var(--shadow-lg);  
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

            /* Status badges */  
            .badge-status {  
                padding: 4px 10px;  
                border-radius: 20px;  
                font-size: 11px;  
                font-weight: 700;  
                display: inline-flex;  
                align-items: center;  
                gap: 4px;  
            }  

            .badge-liquidado {  
                background: var(--gradient-success);  
                color: white;  
            }  

            .badge-parcial {  
                background: var(--gradient-warning);  
                color: white;  
            }  

            .badge-pendente {  
                background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);  
                color: white;  
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
                display: flex;  
                gap: 8px;  
            }  

            /* Botões no card */  
            .card-actions .btn {  
                flex: 1;  
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

            /* Card liquidado */  
            .item-card.liquidado-card {  
                opacity: 0.8;  
                border-color: var(--brand-success);  
                background: linear-gradient(135deg, rgba(16,185,129,.03), rgba(5,150,105,.03));  
            }  

            /* Hero responsivo */  
            .page-hero h1 {  
                font-size: 20px;  
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
            background: var(--gradient-primary);  
            color: white;  
        }  

        .modal-header.error {  
            background: var(--gradient-error);  
        }  

        .modal-header.success {  
            background: var(--gradient-success);  
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

        .close {  
            color: white;  
            opacity: 0.9;  
            text-shadow: none;  
        }  

        .close:hover {  
            color: white;  
            opacity: 1;  
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

            .form-group[style*="flex"] {  
                flex-direction: column !important;  
                gap: 10px;  
            }  

            .form-group[style*="flex"] button {  
                width: 100% !important;  
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

        .table-modern tbody tr, .item-card {  
            animation: fadeInUp 0.3s ease;  
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
                    <h1>Editar Ordem de Serviço nº: <?php echo $id; ?></h1>  
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

        <hr>  

        <!-- ===================== FORMULÁRIO PRINCIPAL ===================== -->  
        <form id="osForm" method="POST">  
            <input type="hidden" id="os_id" name="os_id" value="<?php echo $id; ?>">  
            
            <div class="form-row">  
                <div class="form-group col-md-5">  
                    <label for="cliente">Apresentante:</label>  
                    <input type="text" class="form-control" id="cliente" name="cliente" value="<?php echo htmlspecialchars($os['cliente'], ENT_QUOTES, 'UTF-8'); ?>" required>  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="cpf_cliente">CPF/CNPJ do Apresentante:</label>  
                    <input type="text" class="form-control" id="cpf_cliente" name="cpf_cliente" value="<?php echo $os['cpf_cliente']; ?>">  
                </div>  
                <div class="form-group col-md-2">  
                    <label for="base_calculo">Base de Cálculo:</label>  
                    <input type="text" class="form-control" id="base_calculo" name="base_calculo" value="<?php echo number_format($os['base_de_calculo'], 2, ',', '.'); ?>">  
                </div>  
                <div class="form-group col-md-3">  
                    <label for="total_os">Total OS:</label>  
                    <input type="text" class="form-control" id="total_os" name="total_os" value="<?php echo number_format($os['total_os'], 2, ',', '.'); ?>" readonly>  
                </div>  
            </div>  

            <div class="form-row">  
                <div class="form-group col-md-12">  
                    <label for="descricao_os">Título da OS:</label>  
                    <input type="text" class="form-control" id="descricao_os" name="descricao_os" value="<?php echo $os['descricao_os']; ?>">  
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
                <div class="form-group col-md-5" style="display: flex; flex-wrap: wrap; align-items: center; margin-top: 32px; gap: 10px;">  
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
                <div class="form-group col-md-2" style="margin-top: 32px;">  
                    <button type="button" style="width: 100%" class="btn btn-success" onclick="adicionarItem()">  
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
                <table id="tabelaItensOS" class="table table-modern">  
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
                            <th>Qtd Liquidada</th>  
                            <th>Ações</th>  
                        </tr>  
                    </thead>  
                    <tbody id="itensTable">  
                        <!-- Itens existentes -->  
                        <?php foreach ($itens as $item): ?>  
                            <tr data-item-id="<?php echo $item['id']; ?>"  
                                data-ordem_exibicao="<?php echo $item['ordem_exibicao']; ?>"  
                                <?php if ($item['ato'] === 'ISS') echo 'id="ISS_ROW" data-tipo="iss"'; ?>>  
                                <td class="ordem"><?php echo $item['ordem_exibicao']; ?></td>  
                                <td><?php echo $item['ato']; ?></td>  
                                <td><?php echo $item['quantidade']; ?></td>  
                                <td><?php echo $item['desconto_legal']; ?>%</td>  
                                <td><?php echo $item['descricao']; ?></td>  
                                <td><?php echo number_format($item['emolumentos'], 2, ',', '.'); ?></td>  
                                <td><?php echo number_format($item['ferc'], 2, ',', '.'); ?></td>  
                                <td><?php echo number_format($item['fadep'], 2, ',', '.'); ?></td>  
                                <td><?php echo number_format($item['femp'], 2, ',', '.'); ?></td>  
                                <td><?php echo number_format($item['total'], 2, ',', '.'); ?></td>  
                                <td><?php echo $item['quantidade_liquidada']; ?></td>  
                                <td>  
                                <?php if ($item['status'] === 'liquidado'): ?>  
                                    <!-- Se o item estiver liquidado, nenhum botão será mostrado -->  
                                <?php elseif ($item['status'] === 'liquidado parcialmente'): ?>  
                                    <button type="button" class="btn btn-edit btn-sm" onclick="alterarQuantidade(this)">  
                                        <i class="fa fa-pencil" aria-hidden="true"></i>  
                                    </button>  
                                <?php else: ?>  
                                    <button type="button" class="btn btn-edit btn-sm" onclick="alterarQuantidade(this)">  
                                        <i class="fa fa-pencil" aria-hidden="true"></i>  
                                    </button>  
                                    <?php if ($item['status'] === null): ?>  
                                        <button type="button" class="btn btn-delete btn-sm" onclick="removerItem(this)">  
                                            <i class="fa fa-trash" aria-hidden="true"></i>  
                                        </button>  
                                    <?php endif; ?>  
                                <?php endif; ?>  
                                </td>  
                            </tr>  
                        <?php endforeach; ?>  
                    </tbody>  
                </table>  
            </div>  

            <!-- CARDS MOBILE -->  
            <div class="mobile-cards" id="itensCards">  
                <?php foreach ($itens as $idx => $item):   
                    $isISS = ($item['ato'] === 'ISS');  
                    $isLiquidado = ($item['status'] === 'liquidado');  
                    $isParcial = ($item['status'] === 'liquidado parcialmente');  
                    $isPendente = ($item['status'] === null);  
                    
                    $cardClass = 'item-card';  
                    if ($isISS) $cardClass .= ' iss-card';  
                    if ($isLiquidado) $cardClass .= ' liquidado-card';  
                    
                    $statusBadge = '';  
                    if ($isLiquidado) {  
                        $statusBadge = '<span class="badge-status badge-liquidado"><i class="fa fa-check-circle"></i> Liquidado</span>';  
                    } elseif ($isParcial) {  
                        $statusBadge = '<span class="badge-status badge-parcial"><i class="fa fa-clock-o"></i> Liq. Parcialmente</span>';  
                    } else {  
                        $statusBadge = '<span class="badge-status badge-pendente"><i class="fa fa-circle-o"></i> Pendente</span>';  
                    }  
                ?>  
                <div class="<?php echo $cardClass; ?>" data-item-id="<?php echo $item['id']; ?>">  
                    <!-- Header do Card -->  
                    <div class="card-header-mobile">  
                        <span class="card-number"><?php echo $item['ordem_exibicao']; ?></span>  
                        <?php if ($isISS): ?>  
                            <span class="badge-iss"><i class="fa fa-lock"></i> Item Fixo</span>  
                        <?php else: ?>  
                            <?php echo $statusBadge; ?>  
                        <?php endif; ?>  
                    </div>  

                    <!-- Ato -->  
                    <span class="card-ato">  
                        <i class="fa fa-file-text-o"></i> <?php echo htmlspecialchars($item['ato']); ?>  
                    </span>  

                    <!-- Descrição -->  
                    <?php if (!empty($item['descricao'])): ?>  
                    <div class="card-description">  
                        <?php echo htmlspecialchars($item['descricao']); ?>  
                    </div>  
                    <?php endif; ?>  

                    <!-- Quantidade -->  
                    <div class="info-row">  
                        <span class="info-label">Quantidade</span>  
                        <span class="info-value">  
                            <span class="badge-qty">  
                                <i class="fa fa-cubes"></i>  
                                <?php echo $item['quantidade']; ?>  
                            </span>  
                        </span>  
                    </div>  

                    <!-- Quantidade Liquidada -->  
                    <?php if ($item['quantidade_liquidada'] > 0): ?>  
                    <div class="info-row">  
                        <span class="info-label">Qtd Liquidada</span>  
                        <span class="info-value highlight">  
                            <i class="fa fa-check-circle"></i>  
                            <?php echo $item['quantidade_liquidada']; ?>  
                        </span>  
                    </div>  
                    <?php endif; ?>  

                    <!-- Desconto Legal -->  
                    <?php if ($item['desconto_legal'] > 0): ?>  
                    <div class="info-row">  
                        <span class="info-label">Desconto Legal</span>  
                        <span class="info-value"><?php echo $item['desconto_legal']; ?>%</span>  
                    </div>  
                    <?php endif; ?>  

                    <!-- Grid de Valores -->  
                    <div class="valores-grid">  
                        <div class="valor-item">  
                            <span class="valor-label">Emolumentos</span>  
                            <span class="valor-value">R$ <?php echo number_format($item['emolumentos'], 2, ',', '.'); ?></span>  
                        </div>  
                        <div class="valor-item">  
                            <span class="valor-label">FERC</span>  
                            <span class="valor-value">R$ <?php echo number_format($item['ferc'], 2, ',', '.'); ?></span>  
                        </div>  
                        <div class="valor-item">  
                            <span class="valor-label">FADEP</span>  
                            <span class="valor-value">R$ <?php echo number_format($item['fadep'], 2, ',', '.'); ?></span>  
                        </div>  
                        <div class="valor-item">  
                            <span class="valor-label">FEMP</span>  
                            <span class="valor-value">R$ <?php echo number_format($item['femp'], 2, ',', '.'); ?></span>  
                        </div>  
                    </div>  

                    <!-- Total -->  
                    <div class="info-row" style="margin-top: 8px; padding-top: 12px; border-top: 2px solid var(--border-primary);">  
                        <span class="info-label" style="font-size: 13px;">Valor Total</span>  
                        <span class="info-value highlight" style="font-size: 18px;">  
                            R$ <?php echo number_format($item['total'], 2, ',', '.'); ?>  
                        </span>  
                    </div>  

                    <!-- Ações -->  
                    <?php if (!$isLiquidado): ?>  
                    <div class="card-actions">  
                        <?php if ($isParcial || $isPendente): ?>  
                        <button type="button" class="btn btn-edit btn-sm" onclick="alterarQuantidadeCard(<?php echo $item['id']; ?>, <?php echo $item['quantidade']; ?>, <?php echo $item['quantidade_liquidada']; ?>, '<?php echo $item['status']; ?>')">  
                            <i class="fa fa-pencil"></i> Editar Qtd  
                        </button>  
                        <?php endif; ?>  
                        
                        <?php if ($isPendente): ?>  
                        <button type="button" class="btn btn-delete btn-sm" onclick="removerItemCard(<?php echo $item['id']; ?>)">  
                            <i class="fa fa-trash"></i> Remover  
                        </button>  
                        <?php endif; ?>  
                    </div>  
                    <?php endif; ?>  
                </div>  
                <?php endforeach; ?>  
            </div>  
        </div>  

        <hr>  

        <!-- ===================== OBSERVAÇÕES ===================== -->  
        <div class="form-group">  
            <label for="observacoes">Observações:</label>  
            <textarea class="form-control" id="observacoes" name="observacoes" rows="4"><?php echo $os['observacoes']; ?></textarea>  
        </div>  

        <!-- ===================== BOTÃO SALVAR ===================== -->  
        <button type="button" class="btn btn-primary btn-block" onclick="salvarOS()">  
            <i class="fa fa-floppy-o" aria-hidden="true"></i> SALVAR OS  
        </button>  
    </div>  
</div>  

<!-- ===================== MODAL ALTERAR QUANTIDADE ===================== -->  
<div class="modal fade" id="alterarQuantidadeModal" tabindex="-1" role="dialog" aria-labelledby="alterarQuantidadeModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered" role="document">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="alterarQuantidadeModalLabel">Alterar Quantidade</h5>  
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">  
                    <span aria-hidden="true">&times;</span>  
                </button>  
            </div>  
            <div class="modal-body">  
                <form id="alterarQuantidadeForm">  
                    <div class="form-group">  
                        <label for="novaQuantidade">Nova Quantidade:</label>  
                        <input type="number" class="form-control" id="novaQuantidade" name="novaQuantidade" min="1" required>  
                        <input type="hidden" id="quantidadeLiquidada" name="quantidadeLiquidada">  
                        <input type="hidden" id="statusItem" name="statusItem">  
                        <input type="hidden" id="itemIdModal" name="itemIdModal">  
                    </div>  
                    <button type="button" class="btn btn-primary btn-block" onclick="salvarNovaQuantidade()">Salvar</button>  
                </form>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- ===================== MODAL ALERTA ===================== -->  
<div class="modal fade" id="alertModal" tabindex="-1" role="dialog" aria-labelledby="alertModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered" role="document">  
        <div class="modal-content">  
            <div class="modal-header" id="alertModalHeader">  
                <h5 class="modal-title" id="alertModalLabel">Alerta</h5>  
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">  
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

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/jquery.dataTables.min.js"></script>
<script src="../script/dataTables.bootstrap4.min.js"></script>
<script src="../script/jquery-ui.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>
    const ISS_CONFIG = {
        ativo: <?php echo $issAtivo ? 'true' : 'false'; ?>,
        percentual: <?php echo $issPercentual; ?>,
        descricao: "<?php echo addslashes($issDescricao); ?>"
    };

    /* ---------- lista de exceções (atos com valor 0) ---------- */
    const ATOS_SEM_VALOR = <?php echo json_encode($atosSemValor, JSON_UNESCAPED_UNICODE); ?>;

    $(function() {
    // Tornar as linhas da tabela arrastáveis
    $("#itensTable").sortable({
        placeholder: "ui-state-highlight",
        update: function(event, ui) {
            salvarOrdemExibicao(); // Função que será chamada após reordenar
        }
    });

    $("#itensTable").disableSelection();
});

function salvarOrdemExibicao() {
    var ordem = [];
    $('#itensTable tr').each(function(index) {
        var itemId = $(this).data('item-id');
        ordem.push({
            id: itemId,
            ordem_exibicao: index + 1 // Nova ordem de exibição
        });
        $(this).find('.ordem').text(index + 1); // Atualizar a exibição da ordem
    });

    $.ajax({
        url: 'salvar_ordem_exibicao.php',
        type: 'POST',
        data: { ordem: ordem },
        success: function(response) {
            console.log('Ordem de exibição salva com sucesso');
            atualizarISS();
            calcularTotalOS(); // Recalcular o total após salvar a nova ordem
        },
        error: function(xhr, status, error) {
            console.log('Erro ao salvar a ordem de exibição: ' + error);
        }
    });
}

/* ===================================================================
   Cálculo / atualização automática do ISS
   ===================================================================*/
function atualizarISS () {
    if (!ISS_CONFIG.ativo) return;   // ISS desligado → nada faz

    // Soma dos EMOLUMENTOS (coluna 5), ignorando a própria linha do ISS
    let totalEmol = 0;
    $('#itensTable tr').each(function () {
        if ($(this).data('tipo') !== 'iss') {
            totalEmol += parseFloat($(this).find('td').eq(5).text()
                                   .replace(/\./g, '').replace(',', '.')) || 0;
        }
    });

    const baseISS  = totalEmol * 0.88;
    const valorISS = baseISS * (ISS_CONFIG.percentual / 100);

    // Cria ou atualiza a linha fixa
    let $linhaISS = $('#ISS_ROW');

    /*  ────────────────────────────────────────────────────────────
        Se não existir ISS na tabela, não fazemos nada.  
        Assim evitamos criar linhas novas ou duplicadas.
    ────────────────────────────────────────────────────────────*/
    if ($linhaISS.length === 0) return;

    /* Atualiza valores da linha existente */
    $linhaISS.find('td').eq(5).text(valorISS.toFixed(2).replace('.', ','));
    $linhaISS.find('td').eq(9).text(valorISS.toFixed(2).replace('.', ','));

    /* Recalcula o total geral depois da alteração */
    calcularTotalOS();

}


// Função para exibir modal de alerta
function showAlert(message, type, reload = false) {
    let iconType = type === 'error' ? 'error' : 'success';

    Swal.fire({
        icon: iconType,
        title: type === 'error' ? 'Erro!' : 'Sucesso!',
        text: message,
        confirmButtonText: 'OK'
    }).then(() => {
        if (reload) {
            location.reload();
        }
    });
}


    // Inicializar DataTable
    $('#tabelaItensOS').DataTable({
        "language": {
            "url": "../style/Portuguese-Brasil.json"
        },
        "pageLength": 100,
        "order": [[0, 'asc']], // Ordena pela segunda coluna de forma ascendente
    });


$(document).ready(function() {
    // Máscaras e configurações iniciais
    $('#cpf_cliente').on('blur', function() {
        var cpfCnpj = $(this).val().replace(/\D/g, '');
        if (cpfCnpj.length === 11) {
            $(this).mask('000.000.000-00', {reverse: true});
        } else if (cpfCnpj.length === 14) {
            $(this).mask('00.000.000/0000-00', {reverse: true});
        }
    }).blur(); // Chamar a função quando o campo perde o foco

    $('#base_calculo, #emolumentos, #ferc, #fadep, #femp, #total').mask('#.##0,00', {reverse: true});

    // Apresentante: bloquear aspas/apóstrofos ao digitar e sanitizar colas
    $('#cliente')
      // Bloqueia a digitação de " e '
      .on('keypress', function (e) {
        if (e.key === "'" || e.key === '"') {
          e.preventDefault();
        }
      })
      // Remove aspas/apóstrofos enquanto digita (inclui aspas tipográficas “ ” ‘ ’)
      .on('input', function () {
        this.value = this.value.replace(/["'“”‘’]/g, '');
      })
      // Garante a limpeza logo após colar
      .on('paste', function () {
        const el = this;
        setTimeout(function () {
          el.value = el.value.replace(/["'“”‘’]/g, '');
        }, 0);
      });

    if (ISS_CONFIG.ativo) {
        atualizarISS();
        calcularTotalOS();
    }
});

function buscarAtoPorQuantidade(ato, quantidade, descontoLegal, callback) {
    var os_id = $('#os_id').val();

    // Buscar o ano de criação da OS
    $.ajax({
        url: 'buscar_ano_os.php',
        type: 'POST',
        data: { os_id: os_id },
        success: function(response) {
            console.log('Resposta do servidor (buscar_ano_os):', response);

            // Remover JSON.parse, pois o objeto já é JSON
            if (response.error) {
                showAlert(response.error, 'error');
            } else {
                var tabela_emolumentos = (response.ano_criacao == 2024) ? 'tabela_emolumentos_2024' : 'tabela_emolumentos';

                // Buscar dados do ato na tabela apropriada
                $.ajax({
                    url: 'buscar_ato_edit.php',
                    type: 'GET',
                    data: { ato: ato, tabela: tabela_emolumentos },
                    success: function(response) {
                        console.log('Resposta do servidor (buscar_ato):', response);

                        // Remover JSON.parse, pois o objeto já é JSON
                        if (response.error) {
                            showAlert(response.error, 'error');
                        } else {
                            var emolumentos = response.EMOLUMENTOS * quantidade;
                            var ferc = response.FERC * quantidade;
                            var fadep = response.FADEP * quantidade;
                            var femp = response.FEMP * quantidade;

                            var desconto = descontoLegal / 100;
                            emolumentos *= (1 - desconto);
                            ferc *= (1 - desconto);
                            fadep *= (1 - desconto);
                            femp *= (1 - desconto);

                            /* ───────── EXCEÇÃO: zerar valores se o ato estiver na lista ───────── */
                            if (ATOS_SEM_VALOR.includes(ato.trim())) {
                                emolumentos = ferc = fadep = femp = 0;
                            }

                            callback({
                                descricao: response.DESCRICAO,
                                emolumentos: emolumentos.toFixed(2).replace('.', ','),
                                ferc: ferc.toFixed(2).replace('.', ','),
                                fadep: fadep.toFixed(2).replace('.', ','),
                                femp: femp.toFixed(2).replace('.', ','),
                                total: (emolumentos + ferc + fadep + femp).toFixed(2).replace('.', ',')
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro ao buscar ato:', error, xhr.responseText);
                        showAlert('Erro ao buscar o ato.', 'error');
                    }
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao buscar ano de criação:', error, xhr.responseText);
            showAlert('Erro ao buscar o ano de criação da OS.', 'error');
        }
    });
}



function buscarAto() {
    var ato = $('#ato').val();
    var quantidade = $('#quantidade').val();
    var descontoLegal = $('#desconto_legal').val();

    buscarAtoPorQuantidade(ato, quantidade, descontoLegal, function(values) {
        $('#descricao').val(values.descricao);
        $('#emolumentos').val(values.emolumentos);
        $('#ferc').val(values.ferc);
        $('#fadep').val(values.fadep);
        $('#femp').val(values.femp);
        $('#total').val(values.total);
    });
}

function adicionarISS() {
    var totalEmolumentos = 0;
    $('#itensTable tr').each(function() {
        var emolumentos = parseFloat($(this).find('td').eq(5).text().replace(/\./g, '').replace(',', '.')) || 0;
        totalEmolumentos += emolumentos;
    });

    var baseISS = totalEmolumentos * 0.88; 
    var valorISS = baseISS * 0.05;

    var os_id = $('#os_id').val();
    var ato = 'ISS';
    var quantidade = 1;
    var desconto_legal = 0;
    var descricao = ISS_CONFIG.descricao;
    var emolumentos = valorISS;
    var ferc = 0;
    var fadep = 0;
    var femp = 0;
    var total = valorISS;

    $.ajax({
        url: 'adicionar_item.php',
        type: 'POST',
        data: {
            os_id: os_id,
            ato: ato,
            quantidade: quantidade,
            desconto_legal: desconto_legal,
            descricao: descricao,
            emolumentos: emolumentos,
            ferc: ferc,
            fadep: fadep,
            femp: femp,
            total: total
        },
        success: function(response) {
            try {
                var res = JSON.parse(response);
                if (res.error) {
                    showAlert(res.error, 'error');
                } else {
                    var item = '<tr>' +
                        '<td>' + ato + '</td>' +
                        '<td>' + quantidade + '</td>' +
                        '<td>' + desconto_legal + '%</td>' +
                        '<td>' + descricao + '</td>' +
                        '<td>' + valorISS.toFixed(2).replace('.', ',') + '</td>' +
                        '<td>0,00</td>' +
                        '<td>0,00</td>' +
                        '<td>0,00</td>' +
                        '<td>' + valorISS.toFixed(2).replace('.', ',') + '</td>' +
                        '<td>0</td>' +
                        '<td><button type="button" class="btn btn-delete btn-sm" onclick="removerItem(this)"><i class="fa fa-trash-o" aria-hidden="true"></i></button></td>' +
                        '</tr>';

                    $('#itensTable').append(item);

                    var totalOS = parseFloat($('#total_os').val().replace(/\./g, '').replace(',', '.')) || 0;
                    totalOS += valorISS;
                    $('#total_os').val(totalOS.toFixed(2).replace('.', ','));
                    showAlert('ISS adicionado com sucesso!', 'success', true);  // Adicionar a opção de recarregar a página
                }
            } catch (e) {
                console.log('Erro ao processar a resposta: ', e);
                showAlert('Erro ao processar a resposta do servidor.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao adicionar o ISS', 'error');
        }
    });
}

function adicionarAtoManual() {
    $('#ato').val('0');                  
    $('#descricao').val('').prop('readonly', false);
    $('#emolumentos').val('0,00').prop('readonly', false);
    $('#ferc').val('0,00').prop('readonly', false);
    $('#fadep').val('0,00').prop('readonly', false);
    $('#femp').val('0,00').prop('readonly', false);
    $('#total').prop('readonly', false);
}

function adicionarItem() {
    var os_id = $('#os_id').val();
    var ato = $('#ato').val();
    var quantidade = $('#quantidade').val();
    var descontoLegal = $('#desconto_legal').val();
    var descricao = $('#descricao').val();
    var emolumentos = parseFloat($('#emolumentos').val().replace(/\./g, '').replace(',', '.')) || 0;
    var ferc = parseFloat($('#ferc').val().replace(/\./g, '').replace(',', '.')) || 0;
    var fadep = parseFloat($('#fadep').val().replace(/\./g, '').replace(',', '.')) || 0;
    var femp = parseFloat($('#femp').val().replace(/\./g, '').replace(',', '.')) || 0;
    var total = parseFloat($('#total').val().replace(/\./g, '').replace(',', '.')) || 0;

    const codigoAto = ato.trim();
    const isExcecao = ATOS_SEM_VALOR.includes(codigoAto);

    if ((isNaN(total) || total <= 0) && !isExcecao) {
        showAlert("Por favor, preencha o Valor Total do ato antes de adicionar à O.S.", 'error');
        return;
    }

    $.ajax({
        url: 'adicionar_item.php',
        type: 'POST',
        data: {
            os_id: os_id,
            ato: ato,
            quantidade: quantidade,
            desconto_legal: descontoLegal,
            descricao: descricao,
            emolumentos: emolumentos,
            ferc: ferc,
            fadep: fadep,
            femp: femp,
            total: total
        },
        success: function(response) {
            try {
                var res = JSON.parse(response);
                if (res.error) {
                    showAlert(res.error, 'error');
                } else {
                    adicionarItemAosItensTable(ato, quantidade, descontoLegal, descricao, emolumentos.toFixed(2).replace('.', ','), ferc.toFixed(2).replace('.', ','), fadep.toFixed(2).replace('.', ','), femp.toFixed(2).replace('.', ','), total.toFixed(2).replace('.', ','));
                    atualizarISS();          // recalcula ou cria o ISS
                    calcularTotalOS();       // soma geral
                    showAlert('Item adicionado com sucesso!', 'success', true);
                }
            } catch (e) {
                console.log('Erro ao processar a resposta: ', e);
                showAlert('Erro ao processar a resposta do servidor.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao adicionar o item', 'error');
        }
    });
}

function alterarQuantidade(button) {
    var row = $(button).closest('tr');
    var quantidadeLiquidada = parseInt(row.data('quantidade-liquidada'));
    var status = row.data('status');

    // Ajuste para pegar a nova quantidade (coluna 3) e demais informações da tabela
    $('#novaQuantidade').val(row.find('td').eq(2).text());
    $('#quantidadeLiquidada').val(quantidadeLiquidada);
    $('#statusItem').val(status);
    $('#alterarQuantidadeModal').modal('show');
    $('#alterarQuantidadeForm').data('row', row);
}

function salvarNovaQuantidade() {
    var novaQuantidade = parseInt($('#novaQuantidade').val());
    var quantidadeLiquidada = parseInt($('#quantidadeLiquidada').val());
    var statusItem = $('#statusItem').val();

    if (novaQuantidade < quantidadeLiquidada) {
        showAlert('A nova quantidade não pode ser menor que a quantidade já liquidada (' + quantidadeLiquidada + ').', 'error');
        return;
    }

    var row = $('#alterarQuantidadeForm').data('row');
    var item_id = row.data('item-id');
    var ato = row.find('td').eq(1).text();
    var descontoLegal = parseFloat(row.find('td').eq(3).text().replace('%', ''));

    buscarAtoPorQuantidade(ato, novaQuantidade, descontoLegal, function(values) {
        $.ajax({
            url: 'atualizar_quantidade_item.php',
            type: 'POST',
            data: {
                item_id: item_id,
                quantidade: novaQuantidade,
                emolumentos: parseFloat(values.emolumentos.replace(',', '.')),
                ferc: parseFloat(values.ferc.replace(',', '.')),
                fadep: parseFloat(values.fadep.replace(',', '.')),
                femp: parseFloat(values.femp.replace(',', '.')),
                total: parseFloat(values.total.replace(',', '.'))
            },
            success: function(response) {
                try {
                    var res = JSON.parse(response);
                    if (res.error) {
                        showAlert(res.error, 'error');
                    } else {
                        row.find('td').eq(2).text(novaQuantidade);
                        row.find('td').eq(4).text(values.descricao);
                        row.find('td').eq(5).text(values.emolumentos);
                        row.find('td').eq(6).text(values.ferc);
                        row.find('td').eq(7).text(values.fadep);
                        row.find('td').eq(8).text(values.femp);
                        row.find('td').eq(9).text(values.total);

                        if (novaQuantidade === quantidadeLiquidada) {
                            row.data('status', 'liquidado');
                            row.find('td').eq(10).text('liquidado');
                            $.ajax({
                                url: 'atualizar_status_item.php',
                                type: 'POST',
                                data: {
                                    item_id: row.data('item-id'),
                                    status: 'liquidado'
                                },
                                success: function(response) {
                                    try {
                                        var res = JSON.parse(response);
                                        if (res.error) {
                                            showAlert(res.error, 'error');
                                        }
                                    } catch (e) {
                                        showAlert('Erro ao processar a resposta do servidor.', 'error');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    showAlert('Erro ao atualizar o status do item', 'error');
                                }
                            });
                        }

                        // Recalcular o total da OS
                        atualizarISS();
                        calcularTotalOS();

                        $('#alterarQuantidadeModal').modal('hide');
                        showAlert('Quantidade atualizada com sucesso!', 'success');
                    }
                } catch (e) {
                    showAlert('Erro ao processar a resposta do servidor.', 'error');
                }
            },
            error: function(xhr, status, error) {
                showAlert('Erro ao atualizar a quantidade do item', 'error');
            }
        });
    });
}



function removerItem(button) {
    var row = $(button).closest('tr');
    var itemId = row.data('item-id');
    var status = row.data('status');

    if (status === 'liquidado parcialmente') {
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: 'Não é permitido remover um item parcialmente liquidado.',
            confirmButtonText: 'OK'
        });
        return;
    }

    Swal.fire({
        title: 'Você tem certeza?',
        text: 'Deseja realmente remover este item?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sim, remover!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'remover_item.php',
                type: 'POST',
                data: {
                    item_id: itemId
                },
                success: function(response) {
                    try {
                        var res = JSON.parse(response);
                        if (res.error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro!',
                                text: res.error,
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Item removido com sucesso!',
                                confirmButtonText: 'OK'
                            });
                            row.remove(); // Remove a linha da tabela
                            atualizarISS();
                            calcularTotalOS(); // Recalcula o total da OS após remoção
                        }
                    } catch (e) {
                        console.log('Erro ao processar a resposta: ', e);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Erro ao processar a resposta do servidor.',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Erro:', error);
                    console.log('Resposta do servidor:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao remover o item',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
    });
}


function calcularTotalOS() {
    var totalOS = 0;

    $('#itensTable tr').each(function() {
        var total = parseFloat($(this).find('td').eq(9).text().replace(/\./g, '').replace(',', '.')) || 0;
        totalOS += total;
    });

    $('#total_os').val(totalOS.toFixed(2).replace('.', ','));

    // Atualizar o total da OS no banco de dados
    var os_id = $('#os_id').val();
    $.ajax({
        url: 'atualizar_total_os.php',
        type: 'POST',
        data: {
            os_id: os_id,
            total_os: totalOS.toFixed(2).replace('.', ',')
        },
        success: function(response) {
            try {
                var res = JSON.parse(response);
                if (res.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: res.error,
                        confirmButtonText: 'OK'
                    });
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao processar a resposta do servidor.',
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Erro ao atualizar o total da OS',
                confirmButtonText: 'OK'
            });
        }
    });
}


function adicionarItemAosItensTable(ato, quantidade, descontoLegal, descricao, emolumentos, ferc, fadep, femp, total) {
    var item = '<tr>' +
        '<td>#</td>' + // Nova coluna para o índice #
        '<td>' + ato + '</td>' + // Coluna "Ato"
        '<td>' + quantidade + '</td>' + // Coluna "Quantidade"
        '<td>' + descontoLegal + '%</td>' + // Coluna "Desconto Legal"
        '<td>' + descricao + '</td>' + // Coluna "Descrição"
        '<td>' + emolumentos + '</td>' + // Coluna "Emolumentos"
        '<td>' + ferc + '</td>' + // Coluna "FERC"
        '<td>' + fadep + '</td>' + // Coluna "FADEP"
        '<td>' + femp + '</td>' + // Coluna "FEMP"
        '<td>' + total + '</td>' + // Coluna "Total"
        '<td>0</td>' + // Coluna "Qtd Liquidada" (0 para novos itens)
        '<td>' +
            '<button type="button" class="btn btn-edit btn-sm" onclick="alterarQuantidade(this)"><i class="fa fa-pencil" aria-hidden="true"></i></button>' +
            '<button type="button" class="btn btn-danger btn-sm" onclick="removerItem(this)">Remover</button>' +
        '</td>' +
        '</tr>';

    $('#itensTable').append(item);
    atualizarISS();
    calcularTotalOS(); // Recalcula o total após a adição
}

function atualizarTotalOS(os_id) {
    var totalOS = parseFloat($('#total_os').val().replace(/\./g, '').replace(',', '.')) || 0;

    $.ajax({
        url: 'atualizar_total_os.php',
        type: 'POST',
        data: {
            os_id: os_id,
            total_os: totalOS
        },
        success: function(response) {
            try {
                var res = JSON.parse(response);
                if (res.error) {
                    showAlert(res.error, 'error');
                }
            } catch (e) {
                console.log('Erro ao processar a resposta: ', e);
                showAlert('Erro ao processar a resposta do servidor.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao atualizar o total da OS', 'error');
        }
    });
}

function salvarOS() {
    var os_id = $('#os_id').val();
    var cliente = $('#cliente').val().replace(/["'“”‘’]/g, '');
    var cpf_cliente = $('#cpf_cliente').val();
    var total_os = $('#total_os').val().replace(/\./g, '').replace(',', '.');
    var descricao_os = $('#descricao_os').val();
    var observacoes = $('#observacoes').val();
    var base_calculo = $('#base_calculo').val().replace(/\./g, '').replace(',', '.');

    $.ajax({
        url: 'atualizar_os.php',
        type: 'POST',
        dataType: 'json',
        data: {
            os_id: os_id,
            cliente: cliente,
            cpf_cliente: cpf_cliente,
            total_os: total_os,
            descricao_os: descricao_os,
            observacoes: observacoes,
            base_calculo: base_calculo
        },
        success: function(response) {
            console.log(response);
            if (response.error) {
                showAlert(response.error, 'error');
            } else {
                showAlert('Ordem de Serviço atualizada com sucesso!', 'success');
                setTimeout(function() {
                    window.location.href = 'visualizar_os.php?id=' + os_id;
                }, 2000);
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao atualizar a Ordem de Serviço', 'error');
        }
    });
}
</script>
<script>
function alterarQuantidade(button) {
    var row = $(button).closest('tr');
    var quantidadeLiquidada = parseInt(row.data('quantidade-liquidada'));
    var status = row.data('status');

    // Ajuste para pegar a nova quantidade (coluna 3) e demais informações da tabela
    $('#novaQuantidade').val(row.find('td').eq(2).text());
    $('#quantidadeLiquidada').val(quantidadeLiquidada);
    $('#statusItem').val(status);
    $('#alterarQuantidadeModal').modal('show');
    $('#alterarQuantidadeForm').data('row', row);
}

function salvarNovaQuantidade() {
    var novaQuantidade = parseInt($('#novaQuantidade').val());
    var quantidadeLiquidada = parseInt($('#quantidadeLiquidada').val());
    var statusItem = $('#statusItem').val();

    if (novaQuantidade < quantidadeLiquidada) {
        showAlert('A nova quantidade não pode ser menor que a quantidade já liquidada (' + quantidadeLiquidada + ').', 'error');
        return;
    }

    var row = $('#alterarQuantidadeForm').data('row');
    var item_id = row.data('item-id');
    var ato = row.find('td').eq(1).text();
    var descontoLegal = parseFloat(row.find('td').eq(3).text().replace('%', ''));

    buscarAtoPorQuantidade(ato, novaQuantidade, descontoLegal, function(values) {
        $.ajax({
            url: 'atualizar_quantidade_item.php',
            type: 'POST',
            data: {
                item_id: item_id,
                quantidade: novaQuantidade,
                emolumentos: parseFloat(values.emolumentos.replace(',', '.')),
                ferc: parseFloat(values.ferc.replace(',', '.')),
                fadep: parseFloat(values.fadep.replace(',', '.')),
                femp: parseFloat(values.femp.replace(',', '.')),
                total: parseFloat(values.total.replace(',', '.'))
            },
            success: function(response) {
                try {
                    var res = JSON.parse(response);
                    if (res.error) {
                        showAlert(res.error, 'error');
                    } else {
                        row.find('td').eq(2).text(novaQuantidade);
                        row.find('td').eq(4).text(values.descricao);
                        row.find('td').eq(5).text(values.emolumentos);
                        row.find('td').eq(6).text(values.ferc);
                        row.find('td').eq(7).text(values.fadep);
                        row.find('td').eq(8).text(values.femp);
                        row.find('td').eq(9).text(values.total);

                        if (novaQuantidade === quantidadeLiquidada) {
                            row.data('status', 'liquidado');
                            row.find('td').eq(10).text('liquidado');
                            $.ajax({
                                url: 'atualizar_status_item.php',
                                type: 'POST',
                                data: {
                                    item_id: row.data('item-id'),
                                    status: 'liquidado'
                                },
                                success: function(response) {
                                    try {
                                        var res = JSON.parse(response);
                                        if (res.error) {
                                            showAlert(res.error, 'error');
                                        }
                                    } catch (e) {
                                        showAlert('Erro ao processar a resposta do servidor.', 'error');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    showAlert('Erro ao atualizar o status do item', 'error');
                                }
                            });
                        }

                        // Recalcular o total da OS
                        atualizarISS();
                        calcularTotalOS();

                        $('#alterarQuantidadeModal').modal('hide');
                        showAlert('Quantidade atualizada com sucesso!', 'success');
                    }
                } catch (e) {
                    showAlert('Erro ao processar a resposta do servidor.', 'error');
                }
            },
            error: function(xhr, status, error) {
                showAlert('Erro ao atualizar a quantidade do item', 'error');
            }
        });
    });
}

// Detecta o fechamento do modal de alteração de quantidade
$('#alterarQuantidadeModal').on('hidden.bs.modal', function () {
    // Define um atraso de 1 segundo antes de recarregar a página
    setTimeout(function() {
        location.reload();
    }, 900); // 1000 milissegundos = 1 segundo
});

</script>

<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
