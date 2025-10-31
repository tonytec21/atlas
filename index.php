<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  
include_once 'update_atlas/atualizacao.php';  
date_default_timezone_set('America/Sao_Paulo');  

// Verificar o nível de acesso do usuário logado  
$username = $_SESSION['username'];  
$connAtlas = new mysqli("localhost", "root", "", "atlas");  

// Consulta para verificar o nível de acesso e acesso adicional do usuário  
$sql = "SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ?";  
$stmt = $connAtlas->prepare($sql);  
$stmt->bind_param("s", $username);  
$stmt->execute();  
$result = $stmt->get_result();  
$user = $result->fetch_assoc();  
$nivel_de_acesso = $user['nivel_de_acesso'];  

// Verificar se o usuário tem acesso adicional a "Controle de Tarefas"  
$acesso_adicional = $user['acesso_adicional'];  
$acessos = array_map('trim', explode(',', $acesso_adicional));  
$tem_acesso_controle_tarefas = in_array('Controle de Tarefas', $acessos);  
?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Atlas - Central de Acesso</title>  
    <link rel="stylesheet" href="style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="style/css/style.css">  
    <link rel="icon" href="style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">  
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">  
    <?php include(__DIR__ . '/style/style_index.php'); ?>  

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
            overflow-x: hidden;  
        }  

        /* ===================== MAIN CONTAINER ===================== */  
        .main-container {  
            width: 100%;  
            margin: 60px auto;  
            padding: var(--space-xl) var(--space-lg);  
        }  

        /* ===================== PAGE TITLE ===================== */  
        .page-title {  
            font-size: 48px;  
            font-weight: 800;  
            text-align: center;  
            margin-bottom: var(--space-md);  
            background: var(--gradient-primary);  
            -webkit-background-clip: text;  
            -webkit-text-fill-color: transparent;  
            background-clip: text;  
            letter-spacing: -0.02em;  
        }  

        .title-divider {  
            width: 100px;  
            height: 4px;  
            background: var(--gradient-primary);  
            margin: 0 auto var(--space-xl);  
            border-radius: 999px;  
        }  

        /* ===================== SEARCH BOX ===================== */  
        .search-container {  
            display: flex;  
            justify-content: center;  
            margin-bottom: var(--space-xl);  
        }  

        .search-box {  
            width: 100%;  
            max-width: 600px;  
            padding: 16px 24px;  
            border: 2px solid var(--border-primary);  
            border-radius: var(--radius-xl);  
            font-size: 16px;  
            background: var(--bg-elevated);  
            color: var(--text-primary);  
            box-shadow: var(--shadow-lg);  
            transition: all 0.3s ease;  
        }  

        .search-box:focus {  
            outline: none;  
            border-color: var(--brand-primary);  
            box-shadow: 0 0 0 4px rgba(79,70,229,0.1), var(--shadow-xl);  
        }  

        .search-box::placeholder {  
            color: var(--text-tertiary);  
        }  

        /* ===================== MODULE CARDS GRID ===================== */  
        #sortable-cards {  
            display: grid;  
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));  
            gap: var(--space-lg);  
            margin-bottom: var(--space-xl);  
        }  

        .module-card {  
            background: var(--bg-elevated);  
            border: 2px solid var(--border-primary);  
            border-radius: var(--radius-xl);  
            padding: var(--space-xl);  
            box-shadow: var(--shadow-lg);  
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
            cursor: grab;  
            position: relative;  
            overflow: hidden;  
        }  

        .module-card:active {  
            cursor: grabbing;  
        }  

        .module-card:hover {  
            transform: translateY(-8px);  
            box-shadow: var(--shadow-xl);  
            border-color: var(--brand-primary);  
        }  

        .module-card::before {  
            content: '';  
            position: absolute;  
            top: 0;  
            left: 0;  
            right: 0;  
            height: 4px;  
            background: var(--gradient-primary);  
            opacity: 0;  
            transition: opacity 0.3s ease;  
        }  

        .module-card:hover::before {  
            opacity: 1;  
        }  

        /* ===================== CARD HEADER ===================== */  
        .card-header {  
            display: flex;  
            justify-content: space-between;  
            align-items: center;  
            margin-bottom: var(--space-md);  
        }  

        .card-badge {  
            font-size: 11px;  
            font-weight: 700;  
            text-transform: uppercase;  
            letter-spacing: 0.05em;  
            padding: 6px 12px;  
            border-radius: 999px;  
        }  

        .badge-documental {  
            background: rgba(79, 70, 229, 0.15);  
            color: var(--brand-primary);  
        }  

        .badge-financeiro {  
            background: rgba(16, 185, 129, 0.15);  
            color: var(--brand-success);  
        }  

        .badge-operacional {  
            background: rgba(6, 182, 212, 0.15);  
            color: var(--brand-info);  
        }  

        .badge-juridico {  
            background: rgba(245, 158, 11, 0.15);  
            color: var(--brand-warning);  
        }  

        .badge-administrativo {  
            background: rgba(107, 114, 128, 0.15);  
            color: var(--text-secondary);  
        }  

        .card-icon {  
            width: 56px;  
            height: 56px;  
            border-radius: var(--radius-lg);  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            font-size: 28px;  
            box-shadow: var(--shadow-md);  
        }  

        /* .icon-arquivamento { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }   */
        .icon-caixa { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }  
        .icon-os { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: white; }  
        /* .icon-tarefas { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }   */
        /* .icon-provimentos { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }   */
        .icon-devolutivas { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }  
        .icon-oficios { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }  
        .icon-guia { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: white; }  
        .icon-agenda { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }  
        .icon-contas { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }  
        /* .icon-manuais { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white; }   */
        /* .icon-indexador { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }   */
        /* .icon-xuxuzinho { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); color: white; }   */
        /* .icon-anotacao { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }   */
        .icon-relatorios { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: white; }  
        .icon-certidao { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;}  

        /* ===================== CARD CONTENT ===================== */  
        .card-title {  
            font-size: 20px;  
            font-weight: 800;  
            color: var(--text-primary);  
            margin-bottom: var(--space-sm);  
            letter-spacing: -0.01em;  
        }  

        .card-description {  
            font-size: 14px;  
            color: var(--text-secondary);  
            margin-bottom: var(--space-lg);  
            line-height: 1.6;  
        }  

        .card-button {  
            width: 100%;  
            padding: 14px 24px;  
            border: none;  
            border-radius: var(--radius-md);  
            font-weight: 700;  
            font-size: 15px;  
            cursor: pointer;  
            transition: all 0.3s ease;  
            display: inline-flex;  
            align-items: center;  
            justify-content: center;  
            gap: 10px;  
            box-shadow: var(--shadow);  
        }  

        .card-button:hover {  
            transform: translateY(-2px);  
            box-shadow: var(--shadow-lg);  
        }  

        /* .btn-arquivamento { background: var(--gradient-primary); color: white; }   */
        .btn-caixa { background: var(--gradient-success); color: white; }  
        .btn-os { background: var(--gradient-info); color: white; }  
        /* .btn-tarefas { background: var(--gradient-warning); color: white; }   */
        /* .btn-provimentos { background: var(--gradient-error); color: white; }   */
        .btn-devolutivas { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }  
        .btn-warning { background: var(--gradient-warning); color: white; }  
        .btn-guia { background: var(--gradient-info); color: white; }  
        .btn-agenda { background: var(--gradient-success); color: white; }  
        .btn-contas { background: var(--gradient-error); color: white; }  
        /* .btn-manuais { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white; }   */
        /* .btn-indexador { background: var(--gradient-primary); color: white; }   */
        /* .btn-xuxuzinho { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); color: white; }   */
        /* .btn-anotacao { background: var(--gradient-warning); color: white; }   */
        .btn-relatorios { background: var(--gradient-info); color: white; }  
        .btn-certidao { background: var(--gradient-success); color: white; }  

        /* ===================== MODAL STYLES ===================== */  
        .modal-content {  
            border-radius: var(--radius-xl);  
            border: none;  
            box-shadow: var(--shadow-xl);  
            background: var(--bg-elevated);  
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
            display: flex;  
            align-items: center;  
            gap: 10px;  
        }  

        .modal-body {  
            padding: var(--space-xl);  
            color: var(--text-primary);  
        }  

                /* ===================== MODAL TAREFAS 90% ===================== */
        .modal-fullscreen-custom {
            max-width: 90vw !important;
            width: 90vw !important;
            height: 90vh !important;
            margin: 5vh auto !important;
        }

        .modal-fullscreen-custom .modal-content {
            height: 90vh !important;
            border-radius: var(--radius-xl) !important;
            overflow: hidden;
        }

        .modal-fullscreen-custom .modal-body {
            max-height: calc(90vh - 80px) !important;
            overflow-y: auto !important;
            padding: var(--space-xl) !important;
        }

        .modal-fullscreen-custom .modal-header {
            position: sticky;
            top: 0;
            z-index: 1055;
            border-bottom: 2px solid var(--border-primary);
        }

        /* Scroll suave no modal body */
        .modal-fullscreen-custom .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-fullscreen-custom .modal-body::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 4px;
        }

        .modal-fullscreen-custom .modal-body::-webkit-scrollbar-thumb {
            background: var(--brand-primary);
            border-radius: 4px;
        }

        .modal-fullscreen-custom .modal-body::-webkit-scrollbar-thumb:hover {
            background: var(--brand-secondary);
        }

        /* Responsivo Mobile */
        @media (max-width: 768px) {
            .modal-fullscreen-custom {
                max-width: 95vw !important;
                width: 95vw !important;
                height: 95vh !important;
                margin: 2.5vh auto !important;
            }

            .modal-fullscreen-custom .modal-content {
                height: 95vh !important;
                border-radius: var(--radius-lg) !important;
            }

            .modal-fullscreen-custom .modal-body {
                max-height: calc(95vh - 70px) !important;
                padding: var(--space-md) !important;
            }
        }

        @media (max-width: 576px) {
            .modal-fullscreen-custom {
                max-width: 100vw !important;
                width: 100vw !important;
                height: 80vh !important;
                margin: 0 !important;
            }

            .modal-fullscreen-custom .modal-content {
                height: 80vh !important;
                border-radius: 0 !important;
            }

            .modal-fullscreen-custom .modal-body {
                max-height: calc(100vh - 60px) !important;
            }
        }


        .modal-icon-header {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .modal-fullscreen-custom .modal-header {
            padding: var(--space-lg) var(--space-xl);
            background: var(--gradient-primary);
            color: white;
        }

        .modal-fullscreen-custom .modal-title {
            font-size: 24px;
            font-weight: 800;
            color: #fff;
        }

        .modal {
            left: 8px;
        }

        .btn-close {  
            background: rgba(255,255,255,0.2);  
            border: none;  
            color: white;  
            opacity: 1;  
            font-size: 24px;  
            padding: 8px;  
            border-radius: 50%;  
        }  

        /* ===================== TASK LIST ===================== */  
        .section-title {  
            font-size: 18px!important;  
            font-weight: 800;  
            color: var(--text-primary);  
            margin-bottom: var(--space-md);  
            display: flex;  
            align-items: center;  
            gap: 10px;  
        }  

        .task-list-container h6 {  
            font-weight: 800;  
            color: var(--text-primary);  
            margin-top: var(--space-lg);  
            margin-bottom: var(--space-md);  
        }  

        /* ===================== TABLE DESKTOP ===================== */  
        .table-responsive {  
            overflow-x: auto;  
            border-radius: var(--radius-lg);  
            border: 2px solid var(--border-primary);  
            margin-bottom: var(--space-lg);  
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

        /* ===================== TASK CARDS MOBILE ===================== */  
        .task-cards-mobile {  
            display: none;  
        }  

        .task-card {  
            background: var(--bg-elevated);  
            border: 2px solid var(--border-primary);  
            border-radius: var(--radius-lg);  
            padding: var(--space-md);  
            margin-bottom: var(--space-md);  
            box-shadow: var(--shadow-md);  
            transition: all 0.3s ease;  
        }  

        .task-card:hover {  
            transform: translateY(-2px);  
            box-shadow: var(--shadow-lg);  
            border-color: var(--brand-primary);  
        }  

        .task-card-header {  
            display: flex;  
            justify-content: space-between;  
            align-items: center;  
            margin-bottom: var(--space-sm);  
            padding-bottom: var(--space-sm);  
            border-bottom: 2px solid var(--border-primary);  
        }  

        .task-card-id {  
            font-weight: 800;  
            font-size: 14px;  
            color: var(--brand-primary);  
        }  

        .task-card-title {  
            font-weight: 700;  
            font-size: 16px;  
            color: var(--text-primary);  
            margin-bottom: var(--space-sm);  
        }  

        .task-card-body {  
            display: grid;  
            grid-template-columns: 1fr 1fr;  
            gap: var(--space-sm);  
            margin-bottom: var(--space-md);  
        }  

        .task-card-field {  
            display: flex;  
            flex-direction: column;  
        }  

        .task-card-label {  
            font-size: 11px;  
            font-weight: 700;  
            color: var(--text-tertiary);  
            text-transform: uppercase;  
            letter-spacing: 0.05em;  
            margin-bottom: 4px;  
        }  

        .task-card-value {  
            font-size: 14px;  
            font-weight: 600;  
            color: var(--text-primary);  
        }  

        .task-card-actions {  
            padding-top: var(--space-md);  
            border-top: 2px solid var(--border-primary);  
        }  

        /* ===================== BADGES SOFT ===================== */  
        .soft-badge {  
            display: inline-flex;  
            align-items: center;  
            gap: 6px;  
            padding: 6px 12px;  
            border-radius: 999px;  
            font-size: 11px;  
            font-weight: 700;  
            letter-spacing: 0.02em;  
        }  

        .soft-blue { background: rgba(79, 70, 229, 0.15); color: #4f46e5; }  
        .soft-amber { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }  
        .soft-indigo { background: rgba(99, 102, 241, 0.15); color: #6366f1; }  
        .soft-green { background: rgba(16, 185, 129, 0.15); color: #10b981; }  
        .soft-rose { background: rgba(239, 68, 68, 0.15); color: #ef4444; }  
        .soft-slate { background: rgba(100, 116, 139, 0.15); color: #64748b; }  
        .soft-orange { background: rgba(251, 146, 60, 0.15); color: #fb923c; }  
        .soft-red { background: rgba(220, 38, 38, 0.15); color: #dc2626; }  

        /* ===================== ROW HIGHLIGHTS ===================== */  
        .row-quase-vencida {  
            background: rgba(251, 146, 60, 0.05) !important;  
        }  

        .row-vencida {  
            background: rgba(220, 38, 38, 0.05) !important;  
        }  

        /* ===================== MODAL RECORRENTE (FULLSCREEN) ===================== */  
        .modal-fullscreen .modal-content {  
            border-radius: 0;  
        }  

        .modal-alert-recorrente {  
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);  
            color: #78350f;  
        }  

        .dark-mode .modal-alert-recorrente {  
            background: linear-gradient(135deg, #451a03 0%, #78350f 100%);  
            color: #fef3c7;  
        }  

        .icone-alerta {  
            width: 120px;  
            height: 120px;  
            background: rgba(245, 158, 11, 0.2);  
            border-radius: 50%;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            font-size: 64px;  
            color: #f59e0b;  
        }  

        .titulo-alerta {  
            font-weight: 800;  
            font-size: 32px;  
            letter-spacing: -0.02em;  
        }  

        .opcoes-status {  
            display: flex;  
            gap: var(--space-xl);  
            flex-wrap: wrap;  
        }  

        .form-check-label {  
            font-size: 18px;  
        }  

        .texto-bloqueio {  
            font-weight: 600;  
            opacity: 0.7;  
        }  

        /* ===================== RESPONSIVE ===================== */  
        @media (max-width: 768px) {  
            .main-container {  
                padding: var(--space-md);  
            }  

            .page-title {  
                font-size: 32px;  
            }  

            #sortable-cards {  
                grid-template-columns: 1fr;  
                gap: var(--space-md);  
            }  

            .search-box {  
                padding: 12px 16px;  
                font-size: 14px;  
            }  

            .module-card {  
                padding: var(--space-lg);  
            }  

            .card-icon {  
                width: 48px;  
                height: 48px;  
                font-size: 24px;  
            }  

            .card-title {  
                font-size: 18px;  
            }  

            .table-responsive {  
                display: none;  
            }  

            .task-cards-mobile {  
                display: block;  
            }  

            .modal-dialog {  
                margin: 0.5rem;  
            }  

            .modal-body {  
                padding: var(--space-md);  
            }  

            .icone-alerta {  
                width: 80px;  
                height: 80px;  
                font-size: 48px;  
            }  

            .titulo-alerta {  
                font-size: 24px;  
            }  

            .opcoes-status {  
                flex-direction: column;  
                gap: var(--space-sm);  
            }  
        }  

        @media (max-width: 576px) {  
            .task-card-body {  
                grid-template-columns: 1fr;  
            }  
        }  

        /* ===================== UI STATE HIGHLIGHT ===================== */  
        .ui-state-highlight {  
            background: rgba(79, 70, 229, 0.1);  
            border: 2px dashed var(--brand-primary);  
            height: 200px;  
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

        .module-card {  
            animation: fadeInUp 0.4s ease;  
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
    </style>  
</head>  
<body class="light-mode">  
<?php include(__DIR__ . '/menu.php'); ?>  

<div class="main-container">  
    <!-- <h1 class="page-title">Central de Acesso</h1>   -->
    <div class="title-divider"></div>  
        
    <div class="search-container">  
        <input type="text" class="search-box" id="searchModules" placeholder="🔍 Buscar módulos...">  
    </div>  
        
    <div id="sortable-cards">  
        <!-- Arquivamentos -->  
        <div class="module-card" id="card-arquivamento">  
            <div class="card-header">  
                <span class="card-badge badge-documental">Documental</span>  
                <div class="card-icon icon-arquivamento">  
                    <i class="fa fa-folder-open"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Arquivamentos</h3>  
            <p class="card-description">Controle de arquivamentos com rastreabilidade completa.</p>  
            <button class="card-button btn-arquivamento" onclick="window.location.href='arquivamento/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  
        
        <!-- Ordens de Serviço -->  
        <div class="module-card" id="card-os">  
            <div class="card-header">  
                <span class="card-badge badge-financeiro">Financeiro</span>  
                <div class="card-icon icon-os">  
                    <i class="fa fa-money"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Ordens de Serviço</h3>  
            <p class="card-description">Crie e gerencie ordens de serviço com emolumentos.</p>  
            <button class="card-button btn-os" onclick="window.location.href='os/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  
        
        <!-- Controle de Caixa -->  
        <div class="module-card" id="card-caixa">  
            <div class="card-header">  
                <span class="card-badge badge-financeiro">Financeiro</span>  
                <div class="card-icon icon-caixa">  
                    <i class="fa fa-university"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Controle de Caixa</h3>  
            <p class="card-description">Monitore entradas e saídas financeiras diariamente.</p>  
            <button class="card-button btn-caixa" onclick="window.location.href='caixa/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  
        
        <!-- Tarefas -->  
        <div class="module-card" id="card-tarefas">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-tarefas">  
                    <i class="fa fa-clock-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Tarefas</h3>  
            <p class="card-description">Organize atividades com priorização e prazos.</p>  
            <button class="card-button btn-tarefas" onclick="window.location.href='tarefas/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  
        
        <!-- Ofícios -->  
        <div class="module-card" id="card-oficios">  
            <div class="card-header">  
                <span class="card-badge badge-documental">Documental</span>  
                <div class="card-icon icon-oficios">  
                    <i class="fa fa-file-pdf-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Ofícios</h3>  
            <p class="card-description">Elabore e controle ofícios com numeração automática.</p>  
            <button class="card-button btn-warning" onclick="window.location.href='oficios/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  

        <!-- Devolutiva -->  
        <div class="module-card" id="card-notas-devolutivas">  
            <div class="card-header">  
                <span class="card-badge badge-documental">Documental</span>  
                <div class="card-icon icon-devolutivas">  
                    <i class="fa fa-reply-all"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Nota Devolutiva</h3>  
            <p class="card-description">Elabore e controle notas devolutivas com rastreio.</p>  
            <button class="card-button btn-devolutivas" onclick="window.location.href='nota_devolutiva/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  
        
        <!-- Provimentos e Resoluções -->  
        <div class="module-card" id="card-provimento">  
            <div class="card-header">  
                <span class="card-badge badge-juridico">Jurídico</span>  
                <div class="card-icon icon-provimentos">  
                    <i class="fa fa-balance-scale"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Provimentos</h3>  
            <p class="card-description">Acesse normas, provimentos e resoluções atualizadas.</p>  
            <button class="card-button btn-provimentos" onclick="window.location.href='provimentos/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  
        
        <!-- Guia de Recebimento -->  
        <div class="module-card" id="card-guia">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-guia">  
                    <i class="fa fa-file-text"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Guia de Recebimento</h3>  
            <p class="card-description">Controle de documentos recebidos e protocolados.</p>  
            <button class="card-button btn-guia" onclick="window.location.href='guia_de_recebimento/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  

        <!-- Agenda -->  
        <div class="module-card" id="card-agenda">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-agenda">  
                    <i class="fa fa-calendar-check-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Agenda de Serviços</h3>  
            <p class="card-description">Controle e agendamento de serviços com calendário.</p>  
            <button class="card-button btn-agenda" onclick="window.location.href='agendamento/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  
        
        <!-- Controle de Contas a Pagar -->  
        <div class="module-card" id="card-contas">  
            <div class="card-header">  
                <span class="card-badge badge-financeiro">Financeiro</span>  
                <div class="card-icon icon-contas">  
                    <i class="fa fa-usd"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Contas a Pagar</h3>  
            <p class="card-description">Gerencie contas e controle vencimentos financeiros.</p>  
            <button class="card-button btn-contas" onclick="window.location.href='contas_a_pagar/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  
        
        <!-- Vídeos Tutoriais -->  
        <div class="module-card" id="card-manuais">  
            <div class="card-header">  
                <span class="card-badge badge-administrativo">Administrativo</span>  
                <div class="card-icon icon-manuais">  
                    <i class="fa fa-file-video-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Vídeos Tutoriais</h3>  
            <p class="card-description">Acesse vídeos instrutivos sobre operações do sistema.</p>  
            <button class="card-button btn-manuais" onclick="window.location.href='manuais/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo  
            </button>  
        </div>  
        
        <!-- Indexador -->  
        <?php  
            $configFile = __DIR__ . '/indexador/config_indexador.json';  
            if (file_exists($configFile)) {  
                $configData = json_decode(file_get_contents($configFile), true);  
                if (isset($configData['indexador_ativo']) && $configData['indexador_ativo'] === 'S') {  
                    echo '  
                        <div class="module-card" id="card-indexador">  
                            <div class="card-header">  
                                <span class="card-badge badge-documental">Documental</span>  
                                <div class="card-icon icon-indexador">  
                                    <i class="fa fa-file-text-o"></i>  
                                </div>  
                            </div>  
                            <h3 class="card-title">Indexador</h3>  
                            <p class="card-description">Indexe e localize documentos por conteúdo textual.</p>  
                            <button class="card-button btn-indexador" onclick="window.location.href=\'indexador/index.php\'">  
                                <i class="fa fa-arrow-right"></i> Acessar Módulo  
                            </button>  
                        </div>';  
                }  
            }  
        ?>  
        
        <!-- XUXUZINHO -->  
        <?php  
            $configFile = __DIR__ . '/indexador/config_xuxuzinho.json';  
            if (file_exists($configFile)) {  
                $configData = json_decode(file_get_contents($configFile), true);  
                if (isset($configData['indexador_ativo']) && $configData['indexador_ativo'] === 'S') {  
                    echo '  
                        <div class="module-card" id="card-xuxuzinho">  
                            <div class="card-header">  
                                <span class="card-badge badge-administrativo">Administrativo</span>  
                                <div class="card-icon icon-xuxuzinho">  
                                                                        <img src="../xuxuzinho/images/favicon.png" alt="Ícone Xuxuzinho" style="width: 40px; height: 40px; border-radius: 8px;">  
                                </div>  
                            </div>  
                            <h3 class="card-title">Xuxuzinho</h3>  
                            <p class="card-description">Subsistema para controle de selos e comunicações.</p>  
                            <button class="card-button btn-xuxuzinho" onclick="window.open(\'../xuxuzinho/index.php\', \'_blank\')">  
                                <i class="fa fa-external-link"></i> Acessar em Nova Aba
                            </button>  
                        </div>';  
                }  
            }  
        ?>  

        <!-- DOCMARK -->  
        <?php  
            $configFile = __DIR__ . '/indexador/config_docmark.json';  
            if (file_exists($configFile)) {  
                $configData = json_decode(file_get_contents($configFile), true);  
                if (isset($configData['docmark_ativo']) && $configData['docmark_ativo'] === 'S') {  
                    echo '  
                        <div class="module-card" id="card-xuxuzinho">  
                            <div class="card-header">  
                                <span class="card-badge badge-administrativo">Administrativo</span>  
                                <div class="card-icon icon-xuxuzinho">  
                                                                        <img src="../docmark/img/logo.png" alt="Ícone Xuxuzinho" style="width: 40px; height: 40px; border-radius: 8px;">  
                                </div>  
                            </div>  
                            <h3 class="card-title">DocMark</h3>  
                            <p class="card-description">Subsistema para controle de selos e comunicações.</p>  
                            <button class="card-button btn-xuxuzinho" onclick="window.open(\'../docmark/index.php\', \'_blank\')">  
                                <i class="fa fa-external-link"></i> Acessar em Nova Aba
                            </button>  
                        </div>';  
                }  
            }  
        ?>  

        <!-- Anotações -->  
        <div class="module-card" id="card-anotacao">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-anotacao">  
                    <i class="fa fa-sticky-note-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Anotações</h3>  
            <p class="card-description">Crie e organize anotações e lembretes pessoais.</p>  
            <button class="card-button btn-anotacao" onclick="window.location.href='suas_notas/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo
            </button>  
        </div>  
        
        <!-- Relatórios -->  
        <div class="module-card" id="card-relatorios">  
            <div class="card-header">  
                <span class="card-badge badge-administrativo">Administrativo</span>  
                <div class="card-icon icon-relatorios">  
                    <i class="fa fa-line-chart"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Relatórios e Livros</h3>  
            <p class="card-description">Acesse relatórios gerenciais e visualize registros.</p>  
            <button class="card-button btn-relatorios" onclick="window.location.href='relatorios/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar Módulo
            </button>  
        </div> 
        
        <!-- Pedidos de Certidão -->
        <div class="module-card" id="card-pedidos-certidao">
            <div class="card-header">
                <span class="card-badge badge-documental">Documental</span>
                <div class="card-icon icon-certidao">
                    <i class="fa fa-certificate"></i>
                </div>
            </div>
            <h3 class="card-title">Pedidos de Certidão</h3>
            <p class="card-description">Registre, acompanhe e gerencie pedidos de certidões.</p>
            <button class="card-button btn-certidao" onclick="window.location.href='pedidos_certidao/index.php'">
                <i class="fa fa-arrow-right"></i> Acessar Módulo
            </button>
        </div>

    </div>  
</div>  

<!-- ===================== MODAL DE TAREFAS ===================== -->  
<!-- ===================== MODAL DE TAREFAS ===================== -->  
<div class="modal fade" id="tarefasModal" tabindex="-1" aria-labelledby="tarefasModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-custom">  
        <div class="modal-content">
            <div class="modal-header">  
                <div class="d-flex align-items-center gap-3">
                    <div class="modal-icon-header">
                        <i class="mdi mdi-clipboard-list"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="tarefasModalLabel">Resumo de Tarefas</h5>
                        <small class="text-white opacity-75">Visualização completa das suas pendências</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>  
            </div> 
            <div class="modal-body">  
                <!-- Seção para novas tarefas -->  
                <div id="novas-tarefas-section" style="display: none;">  
                    <h6 class="section-title text-success">  
                        <i class="mdi mdi-new-box"></i> Novas Tarefas  
                    </h6>  
                    <div id="novas-tarefas-list" class="task-list-container"></div>  
                </div>  
                
                <!-- ===================== TAREFAS DE PEDIDOS DE CERTIDÃO ===================== -->
                <div id="tarefas-certidao-section" style="display:none;">
                    <h6 class="section-title text-info">
                        <i class="mdi mdi-certificate"></i> Tarefas de Pedidos de Certidão
                    </h6>
                    <div id="tarefas-certidao-list" class="task-list-container"></div>
                </div>

                <!-- Divisor -->  
                <hr class="my-4">  
                
                <!-- Seção para tarefas pendentes -->
                <div id="tarefas-pendentes-section" style="display:none;">
                    <h6 class="section-title text-primary">
                        <i class="mdi mdi-clock-alert"></i> Tarefas Pendentes
                    </h6>
                    <div id="tarefas-list" class="task-list-container"></div>
                </div>
            </div>  
        </div>  
    </div>  
</div>  

<!-- ===================== MODAL DE ACESSO NEGADO ===================== -->  
<div class="modal fade" id="accessDeniedModal" tabindex="-1" aria-labelledby="accessDeniedLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered">  
        <div class="modal-content">  
            <div class="modal-header" style="background: var(--gradient-error);">  
                <h5 class="modal-title" id="accessDeniedLabel">
                    <i class="mdi mdi-lock"></i> Acesso Negado
                </h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>  
            </div>  
            <div class="modal-body">  
                <p class="mb-0"><i class="mdi mdi-alert-circle"></i> Você não tem permissão para acessar esta página.</p>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Fechar
                </button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- ===================== MODAL TAREFA RECORRENTE OBRIGATÓRIA (FULLSCREEN) ===================== -->
<div class="modal fade" id="recorrenteModal"
     tabindex="-1" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-fullscreen">
    <form id="formCumprirRecorrente" class="modal-content modal-alert-recorrente">
      <div class="modal-body d-flex flex-column justify-content-center align-items-center text-center">
        
        <div class="icone-alerta mb-4">
          <i class="fa fa-exclamation-triangle"></i>
        </div>

        <h2 class="titulo-alerta mb-3">ATENÇÃO! TAREFA OBRIGATÓRIA</h2>
        <p id="recorrenteDescricao" class="lead mb-4"></p>

        <div class="opcoes-status mb-3">
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="optCumprida" value="cumprida" checked>
            <label class="form-check-label fw-bold" for="optCumprida">
                <i class="mdi mdi-check-circle"></i> Cumprida
            </label>
          </div>
          <div class="form-check form-check-inline ms-4">
            <input class="form-check-input" type="radio" name="status" id="optNaoCumprida" value="nao_cumprida">
            <label class="form-check-label fw-bold" for="optNaoCumprida">
                <i class="mdi mdi-close-circle"></i> Não Cumprida
            </label>
          </div>
        </div>

        <div class="w-100" style="max-width:600px;">
          <textarea name="justificativa" id="campoJustificativa"
                    class="form-control d-none"
                    rows="4"
                    placeholder="Explique o motivo de NÃO ter cumprido a tarefa (obrigatório)."></textarea>
        </div>

        <input type="hidden" name="exec_id" id="exec_id">

        <div class="mt-5 d-flex gap-3 flex-wrap justify-content-center">
          <button type="submit" class="btn btn-warning btn-lg fw-bold px-5" style="background: var(--gradient-warning); border: none; color: white;">
            <i class="mdi mdi-check-bold"></i> CONFIRMAR
          </button>
          <button type="button" id="btnAdiar" class="btn btn-secondary btn-lg fw-bold px-5 d-none">
            <i class="mdi mdi-clock-outline"></i> ADIAR
          </button>
        </div>

        <small class="mt-4 texto-bloqueio">
            <i class="mdi mdi-information"></i> Você não poderá acessar o sistema enquanto não confirmar esta tarefa.
        </small>
      </div>
    </form>
  </div>
</div>

<!-- ===================== SCROLL TO TOP ===================== -->
<button id="scrollTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
    <i class="mdi mdi-arrow-up"></i>
</button>

<!-- ===================== SCRIPTS ===================== -->
<script src="script/jquery-3.5.1.min.js"></script>
<script src="script/jquery-ui.min.js"></script>  
<script src="script/bootstrap.min.js"></script>  
<script src="script/jquery.mask.min.js"></script>  

<script>  
'use strict';

$(document).ready(function() {  
    
    // ===================== HELPERS =====================
    function formatarDataBrasileira(dataISO) {  
        const data = new Date(dataISO);  
        if (isNaN(data.getTime())) {  
            return 'Data inválida';  
        }  
        const dia = String(data.getDate()).padStart(2, '0');  
        const mes = String(data.getMonth() + 1).padStart(2, '0');  
        const ano = data.getFullYear();  
        const horas = String(data.getHours()).padStart(2, '0');  
        const minutos = String(data.getMinutes()).padStart(2, '0');  
        return `${dia}/${mes}/${ano} ${horas}:${minutos}`;  
    }  

    function limitarTexto(texto, limite) {  
        if (texto.length > limite) {  
            return texto.substring(0, limite) + '...';  
        }  
        return texto;  
    }  

    function capitalize(text) {
        return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
    }

    // NOVO: normaliza o status vindo do banco (troca "_" por espaço e aplica Title Case)
    function formatStatusLabel(status) {
        const pretty = (status || '').replace(/_/g, ' ');
        return pretty
            ? pretty.toLowerCase().replace(/\b\w/g, c => c.toUpperCase())
            : '';
    }

    // AJUSTE: também normaliza para decidir a classe do badge
    function getStatusBadgeClass(status) {
        const s = (status || '').replace(/_/g, ' ').toLowerCase();
        switch (s) {
            case 'iniciada':      return 'soft-badge soft-blue';
            case 'em espera':     return 'soft-badge soft-amber';
            case 'em andamento':  return 'soft-badge soft-indigo';
            case 'concluída':
            case 'concluida':     return 'soft-badge soft-green';
            case 'cancelada':     return 'soft-badge soft-rose';
            case 'pendente':      return 'soft-badge soft-slate';
            default:              return 'soft-badge soft-slate';
        }
    }

    function getSituacaoBadgeClass(situacao) {
        switch ((situacao || '').toLowerCase()) {
            case 'prestes a vencer': return 'soft-badge soft-orange';
            case 'vencida':          return 'soft-badge soft-red';
            default:                 return 'soft-badge soft-slate';
        }
    }

    function getRowClassBySituacao(situacao) {
        switch ((situacao || '').toLowerCase()) {
            case 'prestes a vencer': return 'row-quase-vencida';
            case 'vencida':          return 'row-vencida';
            default:                 return '';
        }
    }

    // ===================== CRIAR TABELA DESKTOP =====================
    function criarTabelaPorPrioridade(prioridade, tarefas) {  
        let tabela = `  
            <h6 class="mb-3 mt-4" style="font-weight: 800; color: var(--text-primary);">
                <i class="mdi mdi-flag"></i> Prioridade: ${prioridade}
            </h6>  
            <div class="table-responsive">  
                <table class="table table-hover align-middle">  
                    <thead>  
                        <tr>  
                            <th><i class="mdi mdi-pound"></i> ID</th>  
                            <th><i class="mdi mdi-text"></i> Título</th>  
                            <th><i class="mdi mdi-calendar"></i> Data Limite</th>  
                            <th><i class="mdi mdi-chart-line"></i> Status</th>  
                            <th><i class="mdi mdi-alert-circle"></i> Situação</th>  
                            <th></th>  
                        </tr>  
                    </thead>  
                    <tbody>  
        `;  

        tarefas.forEach(tarefa => {  
            const statusClass = getStatusBadgeClass(tarefa.status);  
            const situacaoTexto = (tarefa.status_data || tarefa.situacao || '').trim();
            const situacaoClass = situacaoTexto ? getSituacaoBadgeClass(situacaoTexto) : '';
            const rowHighlight  = getRowClassBySituacao(situacaoTexto);

            tabela += `  
                <tr class="${rowHighlight}">  
                    <td><strong>#${tarefa.id}</strong></td>  
                    <td>${limitarTexto(tarefa.titulo, 70)}</td>  
                    <td>${formatarDataBrasileira(tarefa.data_limite)}</td>  
                    <td><span class="${statusClass}">${formatStatusLabel(tarefa.status) || '—'}</span></td>  
                    <td>${situacaoTexto ? `<span class="${situacaoClass}">${situacaoTexto}</span>` : '—'}</td>  
                    <td class="text-end">  
                        <button class="btn btn-sm btn-secondary" title="Ver tarefa" onclick="window.location.href='tarefas/index_tarefa.php?token=${tarefa.token}'">  
                            <i class="mdi mdi-eye"></i>
                        </button>  
                    </td>  
                </tr>  
            `;  
        });  

        tabela += `  
                    </tbody>  
                </table>  
            </div>  
        `;  

        return tabela;  
    }

    // ===================== CRIAR CARDS MOBILE =====================
    function criarCardsPorPrioridade(prioridade, tarefas) {
        let cards = `
            <h6 class="mb-3 mt-4" style="font-weight: 800; color: var(--text-primary);">
                <i class="mdi mdi-flag"></i> Prioridade: ${prioridade}
            </h6>
            <div class="task-cards-mobile">
        `;

        tarefas.forEach(tarefa => {
            const statusClass = getStatusBadgeClass(tarefa.status);
            const situacaoTexto = (tarefa.status_data || tarefa.situacao || '').trim();
            const situacaoClass = situacaoTexto ? getSituacaoBadgeClass(situacaoTexto) : '';

            cards += `
                <div class="task-card">
                    <div class="task-card-header">
                        <span class="task-card-id"><i class="mdi mdi-pound"></i> ${tarefa.id}</span>
                        <span class="${statusClass}">${formatStatusLabel(tarefa.status) || '—'}</span>
                    </div>
                    <div class="task-card-title">${limitarTexto(tarefa.titulo, 60)}</div>
                    <div class="task-card-body">
                        <div class="task-card-field">
                            <span class="task-card-label"><i class="mdi mdi-calendar"></i> Data Limite</span>
                            <span class="task-card-value">${formatarDataBrasileira(tarefa.data_limite)}</span>
                        </div>
                        <div class="task-card-field">
                            <span class="task-card-label"><i class="mdi mdi-alert-circle"></i> Situação</span>
                            <span class="task-card-value">${situacaoTexto ? `<span class="${situacaoClass}">${situacaoTexto}</span>` : '—'}</span>
                        </div>
                    </div>
                    <div class="task-card-actions">
                        <button class="btn btn-secondary btn-block" onclick="window.location.href='tarefas/index_tarefa.php?token=${tarefa.token}'">
                            <i class="mdi mdi-eye"></i> Ver Tarefa
                        </button>
                    </div>
                </div>
            `;
        });

        cards += `</div>`;
        return cards;
    }

    // ===================== TABELA - TAREFAS DE PEDIDOS DE CERTIDÃO =====================
    function criarTabelaCertidao(tarefas) {
        if (!tarefas || !tarefas.length) return '';

        let html = `
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th><i class="mdi mdi-file-document-edit-outline"></i> O.S.</th>
                            <th><i class="mdi mdi-text-box"></i> Protocolo</th>
                            <th><i class="mdi mdi-account"></i> Responsável</th>
                            <th><i class="mdi mdi-chart-line"></i> Status</th>
                            <th><i class="mdi mdi-calendar-clock"></i> Criado</th>
                            <th><i class="mdi mdi-update"></i> Atualizado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        tarefas.forEach(t => {
            const statusClass = getStatusBadgeClass(t.status);

            html += `
                <tr>
                    <td><strong>${t.numero_os ? `O.S.: ${t.numero_os}` : '—'}</strong></td>
                    <td>${t.protocolo ? `<strong>${t.protocolo}</strong>` : `Pedido #${t.pedido_id}`}</td>
                    <td>${t.responsavel_nome || '—'}</td>
                    <td><span class="${statusClass}">${formatStatusLabel(t.status) || '—'}</span></td>
                    <td>${formatarDataBrasileira(t.criado_em)}</td>
                    <td>${t.atualizado_em ? formatarDataBrasileira(t.atualizado_em) : '—'}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-secondary" title="Abrir Tarefas de Certidão"
                                onclick="window.location.href='${t.link}'">
                            <i class="mdi mdi-open-in-new"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;
        return html;
    }

    // ===================== PROCESSAR TAREFAS =====================
    function processarTarefas(tarefasFuncionario, container) {
        const tarefasCritica = tarefasFuncionario.filter(t => t.nivel_de_prioridade === 'Crítica');
        const tarefasAlta = tarefasFuncionario.filter(t => t.nivel_de_prioridade === 'Alta');
        const tarefasMedia = tarefasFuncionario.filter(t => t.nivel_de_prioridade === 'Média');
        const tarefasBaixa = tarefasFuncionario.filter(t => t.nivel_de_prioridade === 'Baixa');

        if (tarefasCritica.length > 0) {
            container.append(criarTabelaPorPrioridade('Crítica', tarefasCritica));
            container.append(criarCardsPorPrioridade('Crítica', tarefasCritica));
        }
        if (tarefasAlta.length > 0) {
            container.append(criarTabelaPorPrioridade('Alta', tarefasAlta));
            container.append(criarCardsPorPrioridade('Alta', tarefasAlta));
        }
        if (tarefasMedia.length > 0) {
            container.append(criarTabelaPorPrioridade('Média', tarefasMedia));
            container.append(criarCardsPorPrioridade('Média', tarefasMedia));
        }
        if (tarefasBaixa.length > 0) {
            container.append(criarTabelaPorPrioridade('Baixa', tarefasBaixa));
            container.append(criarCardsPorPrioridade('Baixa', tarefasBaixa));
        }
    }

    // ===================== BUSCA DE MÓDULOS =====================
    $("#searchModules").on("keyup", function() {  
        var value = $(this).val().toLowerCase();  
        $("#sortable-cards .module-card").filter(function() {  
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)  
        });  
    });  

    // ===================== SORTABLE (ARRASTAR CARDS) =====================
    $("#sortable-cards").sortable({  
        placeholder: "ui-state-highlight",  
        handle: ".card-header",  
        cursor: "move",  
        update: function(event, ui) {  
            saveCardOrder();  
        }  
    });  

    function saveCardOrder() {  
        let order = [];  
        $("#sortable-cards .module-card").each(function() {  
            order.push($(this).attr('id'));  
        });  

        $.ajax({  
            url: 'save_order.php',  
            type: 'POST',  
            data: { order: order },  
            success: function(response) {  
                console.log('✅ Ordem salva com sucesso!');  
            },  
            error: function(xhr, status, error) {  
                console.error('❌ Erro ao salvar a ordem:', error);  
            }  
        });  
    }  

    function loadCardOrder() {  
        $.ajax({  
            url: 'load_order.php',  
            type: 'GET',  
            dataType: 'json',  
            success: function(data) {  
                if (data && data.order) {  
                    $.each(data.order, function(index, cardId) {  
                        $("#" + cardId).appendTo("#sortable-cards");  
                    });  
                }  
            },  
            error: function(xhr, status, error) {  
                console.error('❌ Erro ao carregar a ordem:', error);  
            }  
        });  
    }  

    // Carrega a ordem ao iniciar a página  
    loadCardOrder();  

    // ===================== CARREGAR TAREFAS PENDENTES E NOVAS =====================
    $.ajax({  
        url: 'verificar_tarefas.php',  
        method: 'GET',  
        dataType: 'json',  
        success: function(response) {  
            var tarefasList        = $('#tarefas-list');  
            var novasTarefasList   = $('#novas-tarefas-list');  
            var certidaoSection    = $('#tarefas-certidao-section');
            var certidaoList       = $('#tarefas-certidao-list');
            var pendentesSection   = $('#tarefas-pendentes-section');

            tarefasList.empty();  
            novasTarefasList.empty();  
            certidaoList.empty();
            pendentesSection.hide(); // começa oculta

            var totalTarefas = 0;  
            var tarefasPendentesCount = 0; // contador só das tarefas “normais”

            // Exibir as novas tarefas (módulo de tarefas)
            $.each(response.novas_tarefas, function(funcionario, tarefasFuncionario) {  
                $('#novas-tarefas-section').show();  
                novasTarefasList.append(`<h6 class="fw-bold mt-4"><i class="mdi mdi-account"></i> ${funcionario}</h6>`);  
                processarTarefas(tarefasFuncionario, novasTarefasList);
                totalTarefas += tarefasFuncionario.length;  
            });  

            // Exibir as tarefas pendentes (módulo de tarefas)
            $.each(response.tarefas, function(funcionario, tarefasFuncionario) {  
                // só cria a seção se existir ao menos 1 item
                if (tarefasFuncionario && tarefasFuncionario.length) {
                    if (!pendentesSection.is(':visible')) pendentesSection.show();
                    tarefasList.append(`<h6 class="fw-bold mt-4"><i class="mdi mdi-account"></i> ${funcionario}</h6>`);  
                    processarTarefas(tarefasFuncionario, tarefasList);
                    tarefasPendentesCount += tarefasFuncionario.length;  
                    totalTarefas += tarefasFuncionario.length;  
                }
            });

            // Se não houve nenhuma tarefa “normal”, mantém a seção oculta
            if (tarefasPendentesCount === 0) {
                pendentesSection.hide();
            }

            // Exibir tarefas de pedidos de certidão (pendentes)
            if (response.tarefas_certidao && response.tarefas_certidao.length) {
                certidaoSection.show();
                certidaoList.append(criarTabelaCertidao(response.tarefas_certidao));
                totalTarefas += response.tarefas_certidao.length;
            } else {
                certidaoSection.hide();
            }

            // Mostrar o modal se houver qualquer tarefa
            if (totalTarefas > 0) {  
                $('#tarefasModal').modal('show');  
            }  
        },  
  
        error: function(xhr, status, error) {  
            console.error('❌ Erro ao carregar as tarefas:', error);  
        }  
    });  

    // ===================== SCROLL TO TOP =====================
    window.addEventListener('scroll', function() {
        const scrollTop = document.getElementById('scrollTop');
        if (window.pageYOffset > 300) {
            scrollTop.classList.add('show');
        } else {
            scrollTop.classList.remove('show');
        }
    });

    // ===================== TAREFAS RECORRENTES =====================
    const $modal              = $('#recorrenteModal');
    const $form               = $('#formCumprirRecorrente');
    const $btnAdiar           = $('#btnAdiar');
    const $campoJustificativa = $('#campoJustificativa');
    const $optCumprida        = $('#optCumprida');
    const $optNaoCumprida     = $('#optNaoCumprida');

    // Verifica tarefas recorrentes que devem aparecer agora
    $.getJSON('verificar_recorrentes.php', resp => {
        if (!resp || !resp.length) return;

        const t = resp[0];
        $('#recorrenteDescricao').text(`${t.titulo} – ${t.descricao || ''}`);
        $('#exec_id').val(t.exec_id);

        // Mostra ou oculta botão ADIAR
        if (parseInt(t.obrigatoria, 10) === 0) {
            $btnAdiar.removeClass('d-none');
        } else {
            $btnAdiar.addClass('d-none');
        }

        // Reseta campos
        $optCumprida.prop('checked', true).trigger('change');
        $campoJustificativa.val('');
        $('#inputStatusAdiar').remove();

        // Abre modal (bloqueante)
        $modal.modal({ backdrop: 'static', keyboard: false }).modal('show');
    });

    // Mostrar / ocultar justificativa
    $optNaoCumprida.on('change', function () {
        $campoJustificativa
          .toggleClass('d-none', !this.checked)
          .prop('required', this.checked);
    });

    $optCumprida.on('change', function () {
        if (this.checked) {
            $campoJustificativa.addClass('d-none')
                               .prop('required', false)
                               .val('');
        }
    });

    // Botão ADIAR (só para tarefas não-obrigatórias)
    $btnAdiar.on('click', function () {
        let $hidden = $('#inputStatusAdiar');
        if (!$hidden.length) {
            $hidden = $('<input>', {
                type: 'hidden',
                id:   'inputStatusAdiar',
                name: 'status'
            }).appendTo($form);
        }
        $hidden.val('adiada');

        // Desabilita rádios para não enviar valores duplicados
        $optCumprida.prop('disabled', true);
        $optNaoCumprida.prop('disabled', true);

        $form.submit();
    });

    // Enviar confirmação (cumprida / não cumprida / adiada)
    $form.on('submit', function (e) {
        e.preventDefault();

        $.post('cumprir_recorrente.php', $(this).serialize(), () => {
            $modal.modal('hide');

            // Limpa/rehabilita para próxima vez
            $('#inputStatusAdiar').remove();
            $optCumprida.prop('disabled', false);
            $optNaoCumprida.prop('disabled', false);
        });
    });

    // ===================== MODO CLARO/ESCURO =====================
    $('.mode-switch').on('click', function() {  
        $('body').toggleClass('dark-mode light-mode');  
    });

});  
</script>  

<?php include(__DIR__ . '/rodape.php'); ?>  
</body>  
</html>