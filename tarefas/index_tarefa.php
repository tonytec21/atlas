<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Pesquisa de Tarefas</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <script src="../script/jquery-3.5.1.min.js"></script>
    <style>

        .form-check-input {
            margin-top: 0.1rem;
            margin-left: -1.80rem;
        }

        div.dataTables_wrapper div.dataTables_filter label {
            text-align: right;
        }

        .form-check-inline {
            margin-left: 5px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        #subTaskAttachments {
            margin-top: 8px; 
        }

        .status-prestes-vencer {
            background-color: #ffc107; 
        }

        .status-vencida {
            background-color: #dc3545; 
        }

        .btn-close {
            outline: none; 
            border: none; 
            background: none;
            padding: 0; 
            font-size: 1.5rem;
            cursor: pointer; 
            transition: transform 0.2s ease;
        }

        .btn-close:hover {
            transform: scale(2.10); 
        }

        .btn-close:focus {
            outline: none; 
        }
        .timeline-badge.subtask {
            background-color: #ffc107;
        }

        .timeline-panel.subtask-panel {
            border-left: 4px solid #ffc107;
            background-color: #fffbe6;
        }

        .subtask-title {
            font-weight: bold;
            color: #ffc107;
            margin-bottom: 10px;
        }

        .timeline-panel.subtask-panel .timeline-body {
            background-color: #fffde7;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ffecb3;
        }

        .btn-edit {
            margin-left: 5px;
        }

        .btn-info {
            margin-left: 5px;
        }

        .priority-medium {
            background-color: #fff9c4 !important; 
        }

        .priority-high {
            background-color: #ffe082 !important; 
        }

        .priority-critical {
            background-color: #ff8a80 !important; 
        }
        .row-quase-vencida {
            background-color: #ffebcc!important; 
        }

        .row-vencida {
            background-color: #ffcccc!important; 
        }

        body.dark-mode .priority-medium td {
            background-color: #fff9c4 !important;
            color: #000!important;
        }

        body.dark-mode .priority-high td {
            background-color: #ffe082 !important; 
            color: #000!important;
        }

        body.dark-mode .priority-critical td {
            background-color: #ff8a80 !important; 
        }
        body.dark-mode .row-quase-vencida td {
            background-color: #ffebcc!important; 
            color: #000!important;
        }

        body.dark-mode .row-vencida td {
            background-color: #ffcccc!important; 
            color: #000!important;
        }

        .status-label {
            display: inline-block;
            padding: 0.2em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 2;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25em;
            width: 100%;
        }

        .status-iniciada {
            background-color: #007bff;
        }

        .status-em-espera {
            background-color: #ffa500;
        }

        .status-em-andamento {
            background-color: #0056b3;
        }

        .status-concluida {
            background-color: #28a745;
        }

        .status-cancelada {
            background-color: #dc3545;
        }

        .status-pendente {
            background-color: gray;
        }

        .status-sub-iniciada {
            background-color: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-sub-em-espera {
            background-color: #ffa500;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-sub-em-andamento {
            background-color: #0056b3;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-sub-concluida {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-sub-cancelada {
            background-color: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-sub-pendente {
            background-color: gray;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .priority-sub-medium {
            background-color: #fff9c4 !important; 
        }
        .priority-sub-high {
            background-color: #ffe082 !important; 
        }
        .priority-sub-critical {
                    background-color: #ff8a80 !important; 
        }

        .timeline {
            position: relative;
            padding: 20px 0;
            list-style: none;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
            left: 30px;
            margin-right: -1.5px;
        }

        .timeline-item {
            margin: 0;
            padding: 0 0 20px;
            position: relative;
        }

        .timeline-item::before,
        .timeline-item::after {
            content: "";
            display: table;
        }

        .timeline-item::after {
            clear: both;
        }

        .timeline-item .timeline-panel {
            position: relative;
            width: calc(100% - 75px);
            float: right;
            border: 1px solid #d4d4d4;
            background: #ffffff;
            border-radius: 2px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .timeline-item .timeline-panel::before {
            position: absolute;
            top: 10px;
            right: -15px;
            display: inline-block;
            border-top: 15px solid transparent;
            border-left: 15px solid #d4d4d4;
            border-right: 0 solid #d4d4d4;
            border-bottom: 15px solid transparent;
            content: " ";
        }

        .timeline-item .timeline-panel::after {
            position: absolute;
            top: 11px;
            right: -14px;
            display: inline-block;
            border-top: 14px solid transparent;
            border-left: 14px solid #ffffff;
            border-right: 0 solid #ffffff;
            border-bottom: 14px solid transparent;
            content: " ";
        }

        .timeline-item .timeline-badge {
            color: #fff;
            width: 48px;
            height: 48px;
            line-height: 52px;
            font-size: 1.4em;
            text-align: center;
            position: absolute;
            top: 0;
            left: 0;
            margin-right: -25px;
            background-color: #7c7c7c;
            z-index: 100;
            border-radius: 50%;
        }

        .timeline-item .timeline-badge.primary {
            background-color: #007bff;
        }

        .timeline-item .timeline-badge.success {
            background-color: #28a745;
        }

        .timeline-item .timeline-badge.warning {
            background-color: #ffc107;
        }

        .timeline-item .timeline-badge.danger {
            background-color: #dc3545;
        }

        body.dark-mode .timeline::before {
            background: #444;
        }

        body.dark-mode .timeline-item .timeline-panel {
            background: #333;
            border-color: #444;
            color: #ddd;
        }

        body.dark-mode .timeline-item .timeline-panel::before {
            border-left-color: #444;
        }

        body.dark-mode .timeline-item .timeline-panel::after {
            border-left-color: #333;
        }




        /* Variáveis de Tema */  
        :root {  
            --background-primary: #ffffff;  
            --background-secondary: #f8f9fa;  
            --text-primary: #2c3e50;  
            --text-secondary: #6c757d;  
            --border-color: #dee2e6;  
            --accent-color: #3498db;  
            --success-color: #2ecc71;  
            --warning-color: #f1c40f;  
            --danger-color: #e74c3c;  
            --header-gradient: linear-gradient(135deg, #3498db, #2980b9);  
            --shadow-color: rgba(0, 0, 0, 0.1);  
        }  

        /* Tema Dark */  
        body.dark-mode {  
            --background-primary: #1a1a1a !important;  
            --background-secondary: #2d2d2d !important;  
            --text-primary: #ffffff !important;  
            --text-secondary: #b3b3b3 !important;  
            --border-color: #404040 !important;  
            --header-gradient: linear-gradient(135deg, #2c3e50, #2c3e50) !important;  
            --shadow-color: rgba(0, 0, 0, 0.3) !important;  
        }  

        /* Modal Base */  
        .modal-content {  
            background: var(--background-primary);  
            border: none;  
            border-radius: 15px;  
            box-shadow: 0 10px 30px var(--shadow-color);  
        }  

        .modal-body > div:not(:last-child) {  
            margin-bottom: 2rem;  
        }  

        .modal-body hr {  
            margin: 2rem 0;  
            border-color: var(--border-color);  
            opacity: 0.5;  
        }  

        /* Header */  
        .primary-header {  
            background: var(--header-gradient);  
            padding: 1.5rem;  
            border: none;  
            border-radius: 15px 15px 0 0;  
        }  

        .modal-header-content {  
            width: 100%;  
            text-align: center;  
            position: relative;  
        }  

        .modal-title {  
            color: #ffffff;  
            font-size: 1.25rem;  
            font-weight: 600;  
        }  

        .protocol-number {  
            font-weight: 700;  
        }  

        /* Barra de Ações */  
        .actions-toolbar {  
            background: var(--background-secondary);  
            padding: 1rem;  
            border-bottom: 1px solid var(--border-color);  
        }  

        .action-buttons {  
            display: flex;  
            gap: 0.5rem;  
            flex-wrap: wrap;  
            justify-content: center;  
        }  

        /* Botões */  
        .action-btn,  
        .btn-save,  
        .btn-add-comment,  
        .create-subtask-btn,  
        .btn-close-modal {  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
            padding: 0.5rem 1rem;  
            border: none;  
            border-radius: 8px;  
            font-size: 0.875rem;  
            transition: all 0.2s;  
        }  

        .action-btn {  
            background: var(--background-primary);  
            color: var(--text-primary);  
        }  

        .action-btn.success,  
        .btn-save {  
            background: var(--success-color);  
            color: white;  
        }  

        .action-btn.primary,  
        .btn-add-comment,  
        .create-subtask-btn {  
            background: var(--accent-color);  
            color: white;  
        }  

        /* Hover Estados para Botões */  
        .action-btn:hover,  
        .btn-save:hover,  
        .btn-add-comment:hover,  
        .create-subtask-btn:hover,  
        .btn-close-modal:hover {  
            transform: translateY(-2px);  
            box-shadow: 0 4px 12px var(--shadow-color);  
        }  

        /* Grid e Layout */  
        .info-grid {  
            display: grid;  
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));  
            gap: 1rem;  
            margin-bottom: 1.5rem;  
        }  

        .info-item {  
            display: flex;  
            flex-direction: column;  
            gap: 0.5rem;  
        }  

        /* Formulários */  
        .form-control-modern,  
        select,  
        textarea {  
            background: var(--background-secondary);  
            border: 1px solid var(--border-color);  
            color: var(--text-primary);  
            border-radius: 8px;  
            padding: 0.75rem;  
            transition: all 0.2s;  
        }  

        .form-control-modern:focus {  
            border-color: var(--accent-color);  
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);  
        }  

        .form-control-modern:disabled,  
        .form-control-modern[readonly] {  
            background-color: var(--background-secondary);  
            opacity: 0.8;  
            cursor: not-allowed;  
        }  

        /* Seções */  
        .description-section,  
        .status-section,  
        .creation-info,  
        .attachments-section,  
        .tasks-tables,  
        .timeline-section {  
            margin: 1.5rem 0;  
            padding: 1.5rem;  
            background: var(--background-secondary);  
            border-radius: 12px;  
            box-shadow: 0 2px 8px var(--shadow-color);  
        }  

        /* Labels */  
        label {  
            display: block;  
            font-weight: 600;  
            color: var(--text-primary);  
            margin-bottom: 0.75rem;  
        }  

        /* Status Control */  
        .status-control {  
            display: flex;  
            gap: 1rem;  
            align-items: center;  
        }  

        .status-control select {  
            flex: 1;  
            padding: 0.75rem;  
            background: var(--background-primary);  
            cursor: pointer;  
        }  

        /* Tabelas */  
        .table-modern {  
            width: 100%;  
            border-collapse: separate;  
            border-spacing: 0 0.5rem;  
        }  

        .table-modern th {  
            background: var(--background-secondary);  
            color: var(--text-primary);  
            padding: 1rem;  
            font-weight: 600;  
        }  

        .table-modern td {  
            background: var(--background-primary);  
            color: var(--text-primary);  
            padding: 1rem;  
        }  

        /* Status Colors */  
        #viewStatus option[value="Iniciada"] { color: var(--accent-color); }  
        #viewStatus option[value="Em Espera"] { color: var(--warning-color); }  
        #viewStatus option[value="Em Andamento"] { color: var(--accent-color); }  
        #viewStatus option[value="Concluída"] { color: var(--success-color); }  
        #viewStatus option[value="Cancelada"] { color: var(--danger-color); }  

        /* Modal Footer */  
        .modal-footer {  
            border-top: 1px solid var(--border-color);  
            padding: 1rem 1.5rem;  
            display: flex;  
            justify-content: flex-end;  
        }  

        /* Responsividade */  
        @media (max-width: 768px) {  
            .action-buttons {  
                flex-direction: column;  
            }  
            
            .action-btn {  
                width: 100%;  
            }  
            
            .info-grid {  
                grid-template-columns: 1fr;  
            }  
            
            .table-responsive {  
                overflow-x: auto;  
            }  
        }  

        /* Animações */  
        .modal.fade .modal-dialog {  
            transform: scale(0.95);  
            transition: transform 0.2s ease-out;  
        }  

        .modal.show .modal-dialog {  
            transform: scale(1);  
        }  

        /* Ajustes Dark Mode Específicos */  
        body.dark-mode .modal-content,  
        body.dark-mode .modal-body,  
        body.dark-mode .modal-footer {  
            background: var(--background-primary);  
            color: var(--text-primary);  
        }  

        body.dark-mode .form-control-modern,  
        body.dark-mode input,  
        body.dark-mode select,  
        body.dark-mode textarea {  
            background: #333333;  
            color: var(--text-primary);  
            border-color: var(--border-color);  
        }  

        body.dark-mode .timeline-panel.subtask-panel .timeline-body {
                    background-color: #495057;
                    padding: 10px;
                    border-radius: 4px;
                    border: 1px solid #ffecb3;
                }

        body.dark-mode .action-btn:not(.success):not(.primary) {  
            background: #333333;  
            color: var(--text-primary);  
        }

        /* Ajustes específicos para textarea de descrição */  
        .description-section textarea.form-control-modern {  
            width: 100%;  
            min-height: 120px;  
            padding: 1rem;  
            background: var(--background-primary);  
            color: var(--text-primary);  
            border: 1px solid var(--border-color);  
            border-radius: 8px;  
            resize: vertical;  
            font-size: 0.95rem;  
            line-height: 1.5;  
        }  

        /* Botão Criar Subtarefa */  
        .create-subtask-btn {  
            width: 100%;  
            margin: 1.5rem 0;  
            padding: 1rem;  
            background: var(--accent-color);  
            color: white;  
            border: none;  
            border-radius: 12px;  
            font-weight: 500;  
            font-size: 1rem;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            gap: 0.75rem;  
            transition: all 0.2s ease;  
            box-shadow: 0 2px 8px var(--shadow-color);  
        }  

        .create-subtask-btn i {  
            font-size: 1.1rem;  
        }  

        .create-subtask-btn:hover {  
            background: var(--accent-color);  
            transform: translateY(-2px);  
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);  
        }  

        /* Seção de Subtarefas Vinculadas */  
        .subtasks-section {  
            margin: 1.5rem 0;  
            padding: 1.5rem;  
            background: var(--background-secondary);  
            border-radius: 12px;  
            box-shadow: 0 2px 8px var(--shadow-color);  
        }  

        .subtasks-list {  
            margin-top: 1rem;  
        }  

        .subtask-item {  
            display: flex;  
            align-items: center;  
            justify-content: space-between;  
            padding: 1rem;  
            background: var(--background-primary);  
            border: 1px solid var(--border-color);  
            border-radius: 8px;  
            margin-bottom: 0.5rem;  
        }  

        .subtask-info {  
            display: flex;  
            flex-direction: column;  
            gap: 0.25rem;  
        }  

        .subtask-title {  
            font-weight: 500;  
            color: var(--text-primary);  
        }  

        .subtask-status {  
            font-size: 0.875rem;  
            color: var(--text-secondary);  
        }  

        .subtask-actions {  
            display: flex;  
            gap: 0.5rem;  
        }  

        /* Ajustes Dark Mode para novos elementos */  
        body.dark-mode .description-section textarea.form-control-modern {  
            background: #333333;  
            color: var(--text-primary);  
            border-color: var(--border-color);  
        }  

        body.dark-mode .subtask-item {  
            background: #333333;  
        }  

        body.dark-mode .subtask-title {  
            color: var(--text-primary);  
        }  

        body.dark-mode .subtask-status {  
            color: var(--text-secondary);  
        }

        .modal-xl {  
            max-width: 95% !important; /* ou um valor específico como 1200px */  
            width: 95%;  
            margin: 1.75rem auto;  
        }  

        @media (min-width: 992px) {  
            .modal-xl {  
                max-width: 1200px !important; /* ou o tamanho que preferir */  
            }  
        }

        /* Botões da Toolbar */  
        .action-buttons {  
            display: flex;  
            gap: 0.5rem;  
            flex-wrap: wrap;  
            justify-content: center;  
        }  

        .action-btn {  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
            padding: 0.5rem 1rem;  
            border: none;  
            border-radius: 6px;  
            font-size: 0.875rem;  
            transition: all 0.2s;  
            font-weight: 500;  
        }  

        /* Botão Secondary (cinza) - para Protocolo Geral */  
        #guiaProtocoloButton {  
            background-color: #6c757d;  
            color: white;  
        }  

        #guiaProtocoloButton:hover {  
            background-color: #5a6268;  
            transform: translateY(-1px);  
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);  
        }  

        /* Botão Info2 (azul claro) - para Guia Recebimento e Recibo Entrega */  
        #guiaRecebimentoButton,  
        #reciboEntregaButton {  
            background-color: #17a2b8;  
            color: white;  
        }  

        #guiaRecebimentoButton:hover,  
        #reciboEntregaButton:hover {  
            background-color: #138496;  
            transform: translateY(-1px);  
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.2);  
        }  

        /* Botão Success (verde) - para Criar Ofício */  
        .action-btn.success {  
            background-color: #28a745;  
            color: white;  
        }  

        .action-btn.success:hover {  
            background-color: #218838;  
            transform: translateY(-1px);  
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);  
        }  

        /* Botão Primary (azul) - para Vincular Ofício */  
        .action-btn.primary {  
            background-color: #007bff;  
            color: white;  
        }  

        .action-btn.primary:hover {  
            background-color: #0056b3;  
            transform: translateY(-1px);  
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.2);  
        }  

        /* Dark Mode */  
        body.dark-mode .action-btn {  
            border: 1px solid var(--border-color);  
        }  

        /* Responsividade */  
        @media (max-width: 768px) {  
            .action-buttons {  
                flex-direction: column;  
            }  
            
            .action-btn {  
                width: 100%;  
                justify-content: center;  
            }  
        }

        /* Ajustes específicos para o Modal de Recibo */  
        #reciboEntregaModal .info-grid {  
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));  
            gap: 1.5rem;  
            margin-bottom: 2rem;  
        }  

        #reciboEntregaModal .description-section {  
            margin-bottom: 1.5rem;  
        }  

        #reciboEntregaModal .form-control-modern {  
            width: 100%;  
        }  

        #reciboEntregaModal textarea.form-control-modern {  
            min-height: 100px;  
            resize: vertical;  
        }  

        #reciboEntregaModal .modal-footer {  
            display: flex;  
            justify-content: flex-end;  
            gap: 1rem;  
            padding: 1rem 1.5rem;  
            background: var(--background-secondary);  
            border-top: 1px solid var(--border-color);  
        }  

        #reciboEntregaModal .btn-close-modal {  
            padding: 0.75rem 1.5rem;  
            background: var(--background-primary);  
            color: var(--text-primary);  
            border: 1px solid var(--border-color);  
            border-radius: 8px;  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
            transition: all 0.2s;  
        }  

        #reciboEntregaModal .btn-close-modal:hover {  
            background: var(--background-secondary);  
            transform: translateY(-1px);  
        }  

        #reciboEntregaModal .action-btn.success {  
            padding: 0.75rem 1.5rem;  
        }  

        /* Dark Mode */  
        body.dark-mode #reciboEntregaModal .modal-content {  
            background: var(--background-primary);  
        }  

        body.dark-mode #reciboEntregaModal .btn-close-modal {  
            background: #333333;  
            color: var(--text-primary);  
            border-color: var(--border-color);  
        }  

        /* Responsividade */  
        @media (max-width: 768px) {  
            #reciboEntregaModal .modal-footer {  
                flex-direction: column-reverse;  
            }  

            #reciboEntregaModal .modal-footer button {  
                width: 100%;  
            }  
        }

        /* Ajustes específicos para o Modal de Guia de Recebimento */  
        #guiaRecebimentoModal .info-grid {  
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));  
            gap: 1.5rem;  
            margin-bottom: 2rem;  
        }  

        #guiaRecebimentoModal .description-section {  
            margin-bottom: 1.5rem;  
        }  

        #guiaRecebimentoModal .form-control-modern {  
            width: 100%;  
        }  

        #guiaRecebimentoModal textarea.form-control-modern {  
            min-height: 100px;  
            resize: vertical;  
        }  

        #guiaRecebimentoModal .modal-footer {  
            display: flex;  
            justify-content: flex-end;  
            gap: 1rem;  
            padding: 1rem 1.5rem;  
            background: var(--background-secondary);  
            border-top: 1px solid var(--border-color);  
        }  

        #guiaRecebimentoModal .btn-close-modal {  
            padding: 0.75rem 1.5rem;  
            background: var(--background-primary);  
            color: var(--text-primary);  
            border: 1px solid var(--border-color);  
            border-radius: 8px;  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
            transition: all 0.2s;  
        }  

        #guiaRecebimentoModal .btn-close-modal:hover {  
            background: var(--background-secondary);  
            transform: translateY(-1px);  
        }  

        #guiaRecebimentoModal .action-btn.success {  
            padding: 0.75rem 1.5rem;  
        }  

        /* Dark Mode */  
        body.dark-mode #guiaRecebimentoModal .modal-content {  
            background: var(--background-primary);  
        }  

        body.dark-mode #guiaRecebimentoModal .btn-close-modal {  
            background: #333333;  
            color: var(--text-primary);  
            border-color: var(--border-color);  
        }  

        /* Responsividade */  
        @media (max-width: 768px) {  
            #guiaRecebimentoModal .modal-footer {  
                flex-direction: column-reverse;  
            }  

            #guiaRecebimentoModal .modal-footer button {  
                width: 100%;  
            }  
        }  

        /* Ajuste para inputs readonly */  
        #guiaRecebimentoModal .form-control-modern[readonly] {  
            background-color: var(--background-secondary);  
            opacity: 0.8;  
            cursor: not-allowed;  
        }  

        /* Hover states */  
        #guiaRecebimentoModal .form-control-modern:hover:not([readonly]) {  
            border-color: var(--accent-color);  
        }  

        #guiaRecebimentoModal .form-control-modern:focus:not([readonly]) {  
            border-color: var(--accent-color);  
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);  
        }

        /* Ajustes específicos para o Modal de Vincular Ofício */  
        #vincularOficioModal .link-section {  
            background: var(--background-secondary);  
            border-radius: 12px;  
            padding: 2rem;  
            margin: 1rem 0;  
            box-shadow: 0 2px 8px var(--shadow-color);  
        }  

        #vincularOficioModal .info-item {  
            max-width: 500px;  
            margin: 0 auto;  
        }  

        /* Input Group com ícone */  
        #vincularOficioModal .input-group {  
            position: relative;  
            display: flex;  
            align-items: center;  
        }  

        #vincularOficioModal .input-icon {  
            position: absolute;  
            right: 1rem;  
            color: var(--text-secondary);  
            pointer-events: none;  
        }  

        #vincularOficioModal .form-control-modern {  
            width: 100%;  
            padding-right: 2.5rem;  
            font-size: 1rem;  
            height: 3rem;  
        }  

        /* Footer */  
        #vincularOficioModal .modal-footer {  
            display: flex;  
            justify-content: flex-end;  
            gap: 1rem;  
            padding: 1rem 1.5rem;  
            background: var(--background-secondary);  
            border-top: 1px solid var(--border-color);  
        }  

        #vincularOficioModal .btn-close-modal {  
            padding: 0.75rem 1.5rem;  
            background: var(--background-primary);  
            color: var(--text-primary);  
            border: 1px solid var(--border-color);  
            border-radius: 8px;  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
            transition: all 0.2s;  
        }  

        #vincularOficioModal .action-btn.primary {  
            padding: 0.75rem 1.5rem;  
            background: var(--accent-color);  
            color: white;  
        }  

        /* Hover States */  
        #vincularOficioModal .form-control-modern:hover {  
            border-color: var(--accent-color);  
        }  

        #vincularOficioModal .form-control-modern:focus {  
            border-color: var(--accent-color);  
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);  
        }  

        #vincularOficioModal .btn-close-modal:hover {  
            background: var(--background-secondary);  
            transform: translateY(-1px);  
        }  

        #vincularOficioModal .action-btn.primary:hover {  
            transform: translateY(-1px);  
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);  
        }  

        /* Dark Mode */  
        body.dark-mode #vincularOficioModal .modal-content {  
            background: var(--background-primary);  
        }  

        body.dark-mode #vincularOficioModal .link-section {  
            background: var(--background-secondary);  
        }  

        body.dark-mode #vincularOficioModal .btn-close-modal {  
            background: #333333;  
            color: var(--text-primary);  
            border-color: var(--border-color);  
        }  

        /* Responsividade */  
        @media (max-width: 768px) {  
            #vincularOficioModal .modal-footer {  
                flex-direction: column-reverse;  
            }  

            #vincularOficioModal .modal-footer button {  
                width: 100%;  
            }  

            #vincularOficioModal .link-section {  
                padding: 1rem;  
            }  
        }  

        /* Placeholder */  
        #vincularOficioModal .form-control-modern::placeholder {  
            color: var(--text-secondary);  
            opacity: 0.7;  
        }

        /* Estilos específicos para o Modal de Criar Subtarefa */  
        #createSubTaskModal .form-section {  
            background: var(--background-secondary);  
            border-radius: 12px;  
            padding: 1.5rem;  
            margin-bottom: 1.5rem;  
        }  

        #createSubTaskModal .info-grid {  
            display: grid;  
            gap: 1.5rem;  
            margin-bottom: 1.5rem;  
        }  

        #createSubTaskModal .info-grid:not(.columns-4) {  
            grid-template-columns: repeat(2, 1fr);  
        }  

        #createSubTaskModal .info-grid.columns-4 {  
            grid-template-columns: repeat(4, 1fr);  
        }  

        /* Campos de formulário */  
        #createSubTaskModal .form-control-modern {  
            width: 100%;  
            padding: 0.75rem 1rem;  
            border: 1px solid var(--border-color);  
            border-radius: 8px;  
            background: var(--background-primary);  
            color: var(--text-primary);  
            transition: all 0.2s;  
        }  

        #createSubTaskModal select.form-control-modern {  
            appearance: none;  
            background-image: url("data:image/svg+xml,...");  
            background-repeat: no-repeat;  
            background-position: right 1rem center;  
            padding-right: 2.5rem;  
        }  

        /* Seção de Anexos */  
        .attachments-section {  
            background: var(--background-secondary);  
            border-radius: 12px;  
            padding: 1.5rem;  
            margin-top: 1.5rem;  
        }  

        .attachment-header {  
            display: flex;  
            justify-content: space-between;  
            align-items: center;  
            margin-bottom: 1rem;  
        }  

        .modern-checkbox {  
            width: 1.2rem;  
            height: 1.2rem;  
            margin-right: 0.5rem;  
        }  

        .file-upload-wrapper {  
            position: relative;  
            margin-top: 1rem;  
        }  

        .modern-file-input {  
            position: absolute;  
            width: 100%;  
            height: 100%;  
            opacity: 0;  
            cursor: pointer;  
        }  

        .file-upload-label {  
            display: flex;  
            flex-direction: column;  
            align-items: center;  
            justify-content: center;  
            padding: 2rem;  
            border: 2px dashed var(--border-color);  
            border-radius: 8px;  
            background: var(--background-primary);  
            cursor: pointer;  
            transition: all 0.2s;  
        }  

        .file-upload-label i {  
            font-size: 2rem;  
            margin-bottom: 0.5rem;  
            color: var(--accent-color);  
        }  

        .file-upload-label:hover {  
            border-color: var(--accent-color);  
            background: var(--background-secondary);  
        }  

        /* Responsividade */  
        @media (max-width: 992px) {  
            #createSubTaskModal .info-grid.columns-4 {  
                grid-template-columns: repeat(2, 1fr);  
            }  
        }  

        @media (max-width: 768px) {  
            #createSubTaskModal .info-grid,  
            #createSubTaskModal .info-grid.columns-4 {  
                grid-template-columns: 1fr;  
            }  

            .attachment-header {  
                flex-direction: column;  
                gap: 1rem;  
            }  
        }  

        /* Dark Mode */  
        body.dark-mode #createSubTaskModal .form-control-modern {  
            background: var(--background-primary);  
            color: var(--text-primary);  
        }  

        body.dark-mode #createSubTaskModal .file-upload-label {  
            background: var(--background-primary);  
        }  

        /* Estilos para campos disabled */  
        #createSubTaskModal .form-control-modern:disabled {  
            background-color: var(--background-secondary);  
            opacity: 0.7;  
            cursor: not-allowed;  
        }  

        /* Estilos para a lista de arquivos selecionados */  
        .selected-files {  
            margin-top: 1rem;  
            padding: 0.5rem;  
            border-radius: 8px;  
        }  

        .file-item {  
            display: flex;  
            align-items: center;  
            justify-content: space-between;  
            padding: 0.5rem;  
            background: var(--background-primary);  
            border: 1px solid var(--border-color);  
            border-radius: 6px;  
            margin-bottom: 0.5rem;  
        }  

        .file-item:last-child {  
            margin-bottom: 0;  
        }  

        .file-info {  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
        }  

        .file-name {  
            font-size: 0.9rem;  
            color: var(--text-primary);  
        }  

        .file-size {  
            font-size: 0.8rem;  
            color: var(--text-secondary);  
        }  

        .remove-file {  
            background: none;  
            border: none;  
            color: var(--danger-color);  
            cursor: pointer;  
            padding: 0.25rem;  
            font-size: 1rem;  
            transition: all 0.2s;  
        }  

        .remove-file:hover {  
            color: var(--danger-color-hover);  
            transform: scale(1.1);  
        }  

        .files-counter {  
            background: var(--accent-color);  
            color: white;  
            padding: 0.25rem 0.75rem;  
            border-radius: 1rem;  
            font-size: 0.8rem;  
            margin-top: 0.5rem;  
            display: inline-block;  
        }

        .file-upload-label.drag-hover {  
            background: var(--background-secondary);  
            border-color: var(--accent-color);  
            transform: scale(1.02);  
        }

        /* Estilos específicos para o Modal de Comentário */  
        #addCommentModal .comment-section {  
            background: var(--background-secondary);  
            border-radius: 12px;  
            padding: 1.5rem;  
            margin-bottom: 1.5rem;  
        }  

        #addCommentModal .form-control-modern {  
            width: 100%;  
            padding: 1rem;  
            border: 1px solid var(--border-color);  
            border-radius: 8px;  
            background: var(--background-primary);  
            color: var(--text-primary);  
            transition: all 0.2s;  
            font-family: inherit;  
            resize: vertical;  
            min-height: 120px;  
        }  

        #addCommentModal .form-control-modern:focus {  
            border-color: var(--accent-color);  
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);  
            outline: none;  
        }  

        #addCommentModal .attachments-section {  
            background: var(--background-secondary);  
            border-radius: 12px;  
            padding: 1.5rem;  
        }  

        /* Upload de Arquivo */  
        #addCommentModal .file-upload-wrapper {  
            position: relative;  
            margin-top: 1rem;  
        }  

        #addCommentModal .modern-file-input {  
            position: absolute;  
            width: 100%;  
            height: 100%;  
            opacity: 0;  
            cursor: pointer;  
        }  

        #addCommentModal .file-upload-label {  
            display: flex;  
            flex-direction: column;  
            align-items: center;  
            justify-content: center;  
            padding: 2rem;  
            border: 2px dashed var(--border-color);  
            border-radius: 8px;  
            background: var(--background-primary);  
            cursor: pointer;  
            transition: all 0.2s;  
        }  

        #addCommentModal .file-upload-label i {  
            font-size: 2rem;  
            margin-bottom: 0.5rem;  
            color: var(--accent-color);  
        }  

        #addCommentModal .file-upload-label:hover {  
            border-color: var(--accent-color);  
            background: var(--background-secondary);  
        }  

        /* Lista de Arquivos Selecionados */  
        #addCommentModal .selected-files {  
            margin-top: 1rem;  
        }  

        #addCommentModal .file-item {  
            display: flex;  
            align-items: center;  
            justify-content: space-between;  
            padding: 0.75rem;  
            background: var(--background-primary);  
            border: 1px solid var(--border-color);  
            border-radius: 6px;  
            margin-bottom: 0.5rem;  
        }  

        #addCommentModal .file-info {  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
        }  

        #addCommentModal .file-name {  
            font-size: 0.9rem;  
            color: var(--text-primary);  
        }  

        #addCommentModal .file-size {  
            font-size: 0.8rem;  
            color: var(--text-secondary);  
        }  

        #addCommentModal .files-counter {  
            background: var(--accent-color);  
            color: white;  
            padding: 0.25rem 0.75rem;  
            border-radius: 1rem;  
            font-size: 0.8rem;  
            margin-bottom: 1rem;  
            display: inline-block;  
        }  

        /* Footer */  
        #addCommentModal .modal-footer {  
            display: flex;  
            justify-content: flex-end;  
            gap: 1rem;  
            padding: 1rem 1.5rem;  
            background: var(--background-secondary);  
            border-top: 1px solid var(--border-color);  
        }  

        #addCommentModal .btn-close-modal {  
            padding: 0.75rem 1.5rem;  
            background: var(--background-primary);  
            color: var(--text-primary);  
            border: 1px solid var(--border-color);  
            border-radius: 8px;  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
            transition: all 0.2s;  
        }  

        #addCommentModal .action-btn.success {  
            padding: 0.75rem 1.5rem;  
        }  

        /* Dark Mode */  
        body.dark-mode #addCommentModal .modal-content {  
            background: var(--background-primary);  
        }  

        body.dark-mode #addCommentModal .form-control-modern {  
            background: var(--background-primary);  
            color: var(--text-primary);  
        }  

        /* Responsividade */  
        @media (max-width: 768px) {  
            #addCommentModal .modal-footer {  
                flex-direction: column-reverse;  
            }  

            #addCommentModal .modal-footer button {  
                width: 100%;  
            }  
        }
    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisa de Tarefas</h3>
            <hr>
            <form id="searchForm">
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label for="protocol">Protocolo Geral:</label>
                        <input type="text" class="form-control" id="protocol" name="protocol">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="title">Título da Tarefa:</label>
                        <input type="text" class="form-control" id="title" name="title">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="category">Categoria:</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">Selecione</option>
                            <?php
                            $sql = "SELECT id, titulo FROM categorias WHERE status = 'ativo'";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="origin">Origem:</label>
                        <select id="origin" name="origin" class="form-control">
                            <option value="">Selecione</option>
                            <?php
                            $sql = "SELECT id, titulo FROM origem WHERE status = 'ativo'";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="status">Status:</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Selecione</option>
                            <option value="Iniciada">Iniciada</option>
                            <option value="Em Espera">Em Espera</option>
                            <option value="Em Andamento">Em Andamento</option>
                            <option value="Concluída">Concluída</option>
                            <option value="Cancelada">Cancelada</option>
                            <option value="Pendente">Pendente</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="description">Descrição:</label>
                        <input type="text" class="form-control" id="description" name="description">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="priority">Prioridade:</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="">Selecione</option>
                            <option value="Baixa">Baixa</option>
                            <option value="Média">Média</option>
                            <option value="Alta">Alta</option>
                            <option value="Crítica">Crítica</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="employee">Funcionário Responsável:</label>
                        <select id="employee" name="employee" class="form-control">
                            <option value="">Selecione</option>
                            <?php
                            $sql = "SELECT nome_completo FROM funcionarios WHERE status = 'ativo'";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <button type="submit" style="width: 100%;" class="btn btn-primary"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar</button>
                    </div>
                    <div class="col-md-6 text-right">
                        <button id="add-button" type="button" style="width: 100%;" class="btn btn-success" onclick="window.location.href='criar-tarefa.php'"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar</button>
                    </div>
                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <h5>Resultados da Pesquisa</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 90%">
                    <thead>
                        <tr>
                            <th>Nº Protocolo</th>
                            <th style="width: 15%">Título</th>
                            <th style="width: 10%">Categoria</th>
                            <th style="width: 9%">Origem</th>
                            <th style="width: 20%">Descrição</th>
                            <th style="width: 9%">Data Limite</th>
                            <th style="width: 12%">Funcionário</th>
                            <th>Prioridade</th>
                            <th>Status</th>
                            <th>Situação</th>
                            <th style="width: 8%">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="taskTable">
                        <!-- Dados das tarefas serão inseridos aqui -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="viewTaskModal" tabindex="-1" role="dialog" aria-labelledby="viewTaskModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-xl" role="document" >  
        <div class="modal-content">  
            <!-- Header Principal -->  
            <div class="modal-header primary-header">  
                <div class="modal-header-content">  
                    <h5 class="modal-title" id="viewTaskModalLabel">  
                        <i class="fa fa-tasks"></i>  
                        Protocolo Geral nº.: <span id="taskNumber" class="protocol-number"></span>  
                    </h5>  
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>  
                </div>  
            </div>  

            <!-- Barra de Ações -->  
            <div class="actions-toolbar">  
                <div class="action-buttons">  
                    <button id="guiaProtocoloButton" class="action-btn">  
                        <i class="fa fa-print"></i>  
                        <span>Protocolo Geral</span>  
                    </button>  
                    <button id="guiaRecebimentoButton" class="action-btn">  
                        <i class="fa fa-file-text"></i>  
                        <span>Guia Recebimento</span>  
                    </button>  
                    <button id="add-button" class="action-btn success" onclick="window.open('../oficios/cadastrar-oficio.php', '_blank')">  
                        <i class="fa fa-plus"></i>  
                        <span>Criar Ofício</span>  
                    </button>  
                    <button id="vincularOficioButton" class="action-btn primary" data-toggle="modal" data-target="#vincularOficioModal">  
                        <i class="fa fa-link"></i>  
                        <span>Vincular Ofício</span>  
                    </button>  
                    <button id="reciboEntregaButton" class="action-btn">  
                        <i class="fa fa-file-text"></i>  
                        <span>Recibo Entrega</span>  
                    </button>  
                </div>  
            </div>  

            <!-- Corpo do Modal -->  
            <div class="modal-body">  
                <!-- Grid de Informações -->  
                <div class="info-grid">  
                    <div class="info-item">  
                        <label for="viewTitle">Título</label>  
                        <input type="text" class="form-control-modern" id="viewTitle" readonly>  
                    </div>  
                    <div class="info-item">  
                        <label for="viewCategory">Categoria</label>  
                        <input type="text" class="form-control-modern" id="viewCategory" readonly>  
                    </div>  
                    <div class="info-item">  
                        <label for="viewOrigin">Origem</label>  
                        <input type="text" class="form-control-modern" id="viewOrigin" readonly>  
                    </div>  
                    <div class="info-item">  
                        <label for="viewDeadline">Data Limite</label>  
                        <input type="text" class="form-control-modern" id="viewDeadline" readonly>  
                    </div>  
                    <div class="info-item">  
                        <label for="viewEmployee">Funcionário Responsável</label>  
                        <input type="text" class="form-control-modern" id="viewEmployee" readonly>  
                    </div>  
                    <div class="info-item">  
                        <label for="viewConclusionDate">Data de Conclusão</label>  
                        <input type="text" class="form-control-modern" id="viewConclusionDate" readonly>  
                    </div>  
                </div>  

                <!-- Descrição -->  
                <div class="description-section">  
                    <label for="viewDescription">Descrição</label>  
                    <textarea class="form-control-modern" id="viewDescription" rows="4" readonly></textarea>  
                </div>  

                <!-- Status -->  
                <div class="status-section">  
                    <label for="viewStatus">Status da Tarefa</label>  
                    <div class="status-control">  
                        <select id="viewStatus" class="form-control-modern">  
                            <option value="Iniciada">Iniciada</option>  
                            <option value="Em Espera">Em Espera</option>  
                            <option value="Em Andamento">Em Andamento</option>  
                            <option value="Concluída">Concluída</option>  
                            <option value="Cancelada">Cancelada</option>  
                        </select>  
                        <button type="button" class="btn-save" id="saveStatusButton">  
                            <i class="fa fa-check"></i> Atualizar Status  
                        </button>  
                    </div>  
                </div>  

                <!-- Informações de Criação -->  
                <div class="creation-info">  
                    <div class="info-item">  
                        <label for="createdBy">Criado por</label>  
                        <input type="text" class="form-control-modern" id="createdBy" readonly>  
                    </div>  
                    <div class="info-item">  
                        <label for="createdAt">Data de Criação</label>  
                        <input type="text" class="form-control-modern" id="createdAt" readonly>  
                    </div>  
                </div>  

                <!-- Seção de Anexos -->  
                <div class="attachments-section">  
                    <h4><i class="fa fa-paperclip"></i> Anexos</h4>  
                    <div id="viewAttachments" class="attachments-list"></div>  
                </div>  

                <!-- Botão Criar Subtarefa -->  
                <button id="createSubTaskButton" class="create-subtask-btn" data-toggle="modal" data-target="#createSubTaskModal">  
                    <i class="fa fa-plus"></i> Criar Subtarefa  
                </button>  

                <!-- Tabelas de Tarefas -->  
                <div class="tasks-tables">  
                    <!-- Tabela Principal -->  
                    <div id="mainTaskSection" class="task-section">  
                        <h4 id="mainTaskHeader" style="display: none;">  
                            <i class="fa fa-project-diagram"></i> Tarefa Principal  
                        </h4>  
                        <div class="table-responsive">  
                            <table id="mainTaskTable" class="table table-modern" style="display: none;zoom: 90%">  
                                <thead>  
                                    <tr>  
                                        <th>Protocolo</th>  
                                        <th>Título da Tarefa Principal</th>  
                                        <th>Funcionário Responsável</th>  
                                        <th>Data de Criação</th>  
                                        <th>Data Limite</th>  
                                        <th>Status</th>  
                                        <th>Ações</th>  
                                    </tr>  
                                </thead>  
                                <tbody id="mainTaskTableBody">  
                                    <!-- Linha da tarefa principal será inserida aqui via JavaScript -->  
                                </tbody>  
                            </table>  
                        </div>  
                    </div>  

                    <hr>  

                    <!-- Tabela de Subtarefas -->  
                    <div id="subTasksSection" class="task-section">  
                        <h4 id="subTasksHeader" style="display: none;">  
                            <i class="fa fa-tasks"></i> Subtarefas  
                        </h4>  
                        <div class="table-responsive">  
                            <table id="subTasksTable" class="table table-modern" style="display: none;zoom: 90%">  
                                <thead>  
                                    <tr>  
                                        <th>Protocolo</th>  
                                        <th>Título da Subtarefa</th>  
                                        <th>Funcionário Responsável</th>  
                                        <th>Data de Criação</th>  
                                        <th>Data Limite</th>  
                                        <th>Status</th>  
                                        <th>Ações</th>  
                                    </tr>  
                                </thead>  
                                <tbody id="subTasksTableBody">  
                                    <!-- Linhas de subtarefas serão inseridas aqui via JavaScript -->  
                                </tbody>  
                            </table>  
                        </div>  
                    </div>  
                </div>
                <!-- Timeline -->  
                <div class="timeline-section">  
                    <h4><i class="fa fa-history"></i> Timeline</h4>  
                    <div id="commentTimeline" class="timeline-content"></div>  
                    <button type="button" class="btn-add-comment" id="addCommentButton" data-toggle="modal" data-target="#addCommentModal">  
                        <i class="fa fa-plus-circle"></i> Adicionar Comentário  
                    </button>  
                </div>  
            </div>  

            <!-- Footer -->  
            <div class="modal-footer">  
                <button type="button" class="btn-close-modal" data-dismiss="modal">  
                    <i class="fa fa-times"></i> Fechar  
                </button>  
            </div>  
        </div>  
    </div>  
</div>

    <!-- Modal Adicionar Comentário -->  
    <div class="modal fade" id="addCommentModal" tabindex="-1" role="dialog" aria-labelledby="addCommentModalLabel" aria-hidden="true">  
        <div class="modal-dialog modal-gl" role="document">  
            <div class="modal-content">  
                <!-- Header -->  
                <div class="primary-header">  
                    <div class="modal-header-content">  
                        <h5 class="modal-title" id="addCommentModalLabel">  
                            <i class="fa fa-comment"></i> Adicionar Comentário e Anexos  
                        </h5>  
                    </div>  
                </div>  

                <!-- Body -->  
                <div class="modal-body">  
                    <form id="commentForm">  
                        <!-- Seção de Comentário -->  
                        <div class="comment-section">  
                            <label for="commentDescription">Comentário</label>  
                            <textarea class="form-control-modern" id="commentDescription" name="commentDescription" rows="5"   
                                placeholder="Digite seu comentário aqui..."></textarea>  
                        </div>  

                        <!-- Seção de Anexos -->  
                        <div class="attachments-section">  
                            <label>Anexos</label>  
                            <div class="file-upload-wrapper">  
                                <input type="file" id="commentAttachments" name="commentAttachments[]" multiple class="modern-file-input">  
                                <label for="commentAttachments" class="file-upload-label">  
                                    <i class="fa fa-cloud-upload"></i>  
                                    <span class="upload-text">Arraste os arquivos ou clique para selecionar</span>  
                                </label>  
                                <div class="selected-files" id="selectedFiles"></div>  
                            </div>  
                        </div>  
                    </form>  
                </div>  

                <!-- Footer -->  
                <div class="modal-footer">  
                    <button type="button" class="btn-close-modal" data-dismiss="modal">  
                        <i class="fa fa-times"></i> Cancelar  
                    </button>  
                    <button type="submit" form="commentForm" class="action-btn success">  
                        <i class="fa fa-save"></i> Salvar Comentário  
                    </button>  
                </div>  
            </div>  
        </div>  
    </div>

    <!-- Modal Vincular Ofício -->  
    <div class="modal fade" id="vincularOficioModal" tabindex="-1" role="dialog" aria-labelledby="vincularOficioModalLabel" aria-hidden="true">  
        <div class="modal-dialog modal-gl" role="document">  
            <div class="modal-content">  
                <!-- Header -->  
                <div class="primary-header">  
                    <div class="modal-header-content">  
                        <h5 class="modal-title" id="vincularOficioModalLabel">  
                            <i class="fa fa-link"></i> Vincular Ofício  
                        </h5>   
                    </div>  
                </div>  

                <!-- Body -->  
                <div class="modal-body">  
                    <form id="vincularOficioForm">  
                        <!-- Seção de Vínculo -->  
                        <div class="link-section">  
                            <div class="info-item">  
                                <label for="numeroOficio">Número do Ofício</label>  
                                <div class="input-group">  
                                    <input type="text" class="form-control-modern" id="numeroOficio" name="numeroOficio" placeholder="Digite o número do ofício">  
                                    <div class="input-icon">  
                                        <i class="fa fa-file-text"></i>  
                                    </div>  
                                </div>  
                            </div>  
                        </div>  
                    </form>  
                </div>  

                <!-- Footer -->  
                <div class="modal-footer">  
                    <button type="button" class="btn-close-modal" data-dismiss="modal">  
                        <i class="fa fa-times"></i> Cancelar  
                    </button>  
                    <button type="submit" form="vincularOficioForm" class="action-btn primary">  
                        <i class="fa fa-save"></i> Vincular  
                    </button>  
                </div>  
            </div>  
        </div>  
    </div>

    <!-- Modal Recibo de Entrega -->  
<div class="modal fade" id="reciboEntregaModal" tabindex="-1" role="dialog" aria-labelledby="reciboEntregaModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-gl" role="document">  
        <div class="modal-content">  
            <!-- Header -->  
            <div class="primary-header">  
                <div class="modal-header-content">  
                    <h5 class="modal-title" id="reciboEntregaModalLabel">  
                        <i class="fa fa-file-text"></i> Recibo de Entrega  
                    </h5>  
                </div>  
            </div>  

            <!-- Body -->  
            <div class="modal-body">  
                <form id="reciboEntregaForm">  
                    <!-- Grid de Informações -->  
                    <div class="info-grid">  
                        <div class="info-item">  
                            <label for="receptor">Nome do Receptor</label>  
                            <input type="text" class="form-control-modern" id="receptor" name="receptor" required>  
                        </div>  
                        
                        <div class="info-item">  
                            <label for="dataEntrega">Data da Entrega</label>  
                            <input type="datetime-local" class="form-control-modern" id="dataEntrega" name="dataEntrega" required>  
                        </div>  
                        
                        <div class="info-item">  
                            <label for="entregador">Nome do Entregador</label>  
                            <input type="text" class="form-control-modern" id="entregador" name="entregador" readonly>  
                        </div>  
                    </div>  

                    <!-- Seção de Documentos -->  
                    <div class="description-section">  
                        <label for="documentos">Documentos Entregues</label>  
                        <textarea class="form-control-modern" id="documentos" name="documentos" rows="3" required></textarea>  
                    </div>  

                    <!-- Seção de Observações -->  
                    <div class="description-section">  
                        <label for="observacoes">Observações</label>  
                        <textarea class="form-control-modern" id="observacoes" name="observacoes" rows="3"></textarea>  
                    </div>  
                </form>  
            </div>  

            <!-- Footer -->  
            <div class="modal-footer">  
                <button type="button" class="btn-close-modal" data-dismiss="modal">  
                    <i class="fa fa-times"></i> Cancelar  
                </button>  
                <button type="submit" form="reciboEntregaForm" class="action-btn success">  
                    <i class="fa fa-save"></i> Salvar Recibo  
                </button>  
            </div>  
        </div>  
    </div>  
</div>

    <!-- Modal Guia de Recebimento -->  
<div class="modal fade" id="guiaRecebimentoModal" tabindex="-1" role="dialog" aria-labelledby="guiaRecebimentoModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-gl" role="document">  
        <div class="modal-content">  
            <!-- Header -->  
            <div class="primary-header">  
                <div class="modal-header-content">  
                    <h5 class="modal-title" id="guiaRecebimentoModalLabel">  
                        <i class="fa fa-file-text"></i> Guia de Recebimento  
                    </h5>    
                </div>  
            </div>  

            <!-- Body -->  
            <div class="modal-body">  
                <form id="guiaRecebimentoForm">  
                    <!-- Grid de Informações -->  
                    <div class="info-grid">  
                        <div class="info-item">  
                            <label for="cliente">Apresentante</label>  
                            <input type="text" class="form-control-modern" id="cliente" name="cliente" required>  
                        </div>  
                        
                        <div class="info-item">  
                            <label for="dataRecebimento">Data de Recebimento</label>  
                            <input type="datetime-local" class="form-control-modern" id="dataRecebimento" name="dataRecebimento" required>  
                        </div>  
                        
                        <div class="info-item">  
                            <label for="funcionario">Funcionário</label>  
                            <input type="text" class="form-control-modern" id="funcionario" name="funcionario" readonly>  
                        </div>  
                    </div>  

                    <!-- Seção de Documentos -->  
                    <div class="description-section">  
                        <label for="documentosRecebidos">Documentos Recebidos</label>  
                        <textarea class="form-control-modern" id="documentosRecebidos" name="documentosRecebidos" rows="3" required></textarea>  
                    </div>  

                    <!-- Seção de Observações -->  
                    <div class="description-section">  
                        <label for="observacoes">Observações</label>  
                        <textarea class="form-control-modern" id="observacoes" name="observacoes" rows="3"></textarea>  
                    </div>  
                </form>  
            </div>  

            <!-- Footer -->  
            <div class="modal-footer">  
                <button type="button" class="btn-close-modal" data-dismiss="modal">  
                    <i class="fa fa-times"></i> Cancelar  
                </button>  
                <button type="submit" form="guiaRecebimentoForm" class="action-btn success">  
                    <i class="fa fa-save"></i> Salvar Guia  
                </button>  
            </div>  
        </div>  
    </div>  
</div>


    <!-- Modal Criar Subtarefa -->  
<div class="modal fade" id="createSubTaskModal" tabindex="-1" role="dialog" aria-labelledby="createSubTaskModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-xl" role="document">  
        <div class="modal-content">  
            <!-- Header -->  
            <div class="primary-header">  
                <div class="modal-header-content">  
                    <h5 class="modal-title" id="createSubTaskModalLabel">  
                        <i class="fa fa-tasks"></i> Criar Subtarefa  
                    </h5>  
                    
                </div>  
            </div>  

            <!-- Body -->  
            <div class="modal-body">  
                <form id="subTaskForm" enctype="multipart/form-data" method="POST" action="save_sub_task.php">  
                    <!-- Informações Principais -->  
                    <div class="form-section">  
                        <div class="info-grid">  
                            <div class="info-item">  
                                <label for="subTaskTitle">Título da Subtarefa</label>  
                                <input type="text" class="form-control-modern" id="subTaskTitle" name="title" required>  
                            </div>  
                            
                            <div class="info-item">  
                                <label for="subTaskCategory">Categoria</label>  
                                <select id="subTaskCategory" name="category" class="form-control-modern" required>  
                                    <option value="">Selecione</option>  
                                    <?php  
                                    $sql = "SELECT id, titulo FROM categorias WHERE status = 'ativo'";  
                                    $result = $conn->query($sql);  
                                    if ($result->num_rows > 0) {  
                                        while($row = $result->fetch_assoc()) {  
                                            echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                        }  
                                    }  
                                    ?>  
                                </select>  
                            </div>  
                        </div>  

                        <!-- Segunda linha de campos -->  
                        <div class="info-grid columns-4">  
                            <div class="info-item">  
                                <label for="subTaskDeadline">Data Limite</label>  
                                <input type="datetime-local" class="form-control-modern" id="subTaskDeadline" name="deadline" required>  
                            </div>  

                            <div class="info-item">  
                                <label for="subTaskPriority">Nível de Prioridade</label>  
                                <select id="subTaskPriority" name="priority" class="form-control-modern" required>  
                                    <option value="">Selecione</option>  
                                    <option value="Baixa">Baixa</option>  
                                    <option value="Média">Média</option>  
                                    <option value="Alta">Alta</option>  
                                    <option value="Crítica">Crítica</option>  
                                </select>  
                            </div>  

                            <div class="info-item">  
                                <label for="subTaskEmployee">Funcionário Responsável</label>  
                                <select id="subTaskEmployee" name="employee" class="form-control-modern" required>  
                                    <option value="">Selecione</option>  
                                    <?php  
                                    $sql = "SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo'";  
                                    $result = $conn->query($sql);  
                                    $loggedInUser = $_SESSION['username'];  

                                    if ($result->num_rows > 0) {  
                                        while($row = $result->fetch_assoc()) {  
                                            $selected = ($row['nome_completo'] == $loggedInUser) ? 'selected' : '';  
                                            echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "' $selected>" .   
                                                htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                        }  
                                    }  
                                    ?>  
                                </select>  
                            </div>  

                            <div class="info-item">  
                                <label for="subTaskOrigin">Origem</label>  
                                <select id="subTaskOrigin" name="origin" class="form-control-modern" required>  
                                    <option value="">Selecione</option>  
                                    <?php  
                                    $sql = "SELECT id, titulo FROM origem WHERE status = 'ativo'";  
                                    $result = $conn->query($sql);  
                                    if ($result->num_rows > 0) {  
                                        while($row = $result->fetch_assoc()) {  
                                            echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                        }  
                                    }  
                                    ?>  
                                </select>  
                            </div>  
                        </div>  
                    </div>  

                    <!-- Descrição -->  
                    <div class="description-section">  
                        <label for="subTaskDescription">Descrição</label>  
                        <textarea class="form-control-modern" id="subTaskDescription" name="description" rows="5"></textarea>  
                    </div>  

                    <!-- Anexos -->  
                    <div class="attachments-section">  
                        <div class="attachment-header">  
                            <label>Anexos</label>  
                            <div class="form-check">  
                                <input class="form-check-input modern-checkbox" type="checkbox" id="compartilharAnexos" name="compartilharAnexos">  
                                <label class="form-check-label" for="compartilharAnexos">  
                                    Compartilhar anexos da tarefa principal  
                                </label>  
                            </div>  
                        </div>  
                        
                        <div class="file-upload-wrapper">  
                            <input type="file" id="subTaskAttachments" name="attachments[]" multiple class="modern-file-input">  
                            <label for="subTaskAttachments" class="file-upload-label">  
                                <i class="fa fa-cloud-upload"></i>  
                                <span class="upload-text">Arraste os arquivos ou clique para selecionar</span>  
                            </label>  
                            <div class="selected-files" id="selectedFiles"></div>  
                        </div>
                    </div>  

                    <!-- Campos ocultos -->  
                    <input type="hidden" id="subTaskCreatedBy" name="createdBy" value="<?php echo $_SESSION['username']; ?>">  
                    <input type="hidden" id="subTaskCreatedAt" name="createdAt" value="<?php echo date('Y-m-d H:i:s'); ?>">  
                    <input type="hidden" id="subTaskPrincipalId" name="id_tarefa_principal">  
                </form>  
            </div>  

            <!-- Footer -->  
            <div class="modal-footer">  
                <button type="button" class="btn-close-modal" data-dismiss="modal">  
                    <i class="fa fa-times"></i> Cancelar  
                </button>  
                <button type="submit" form="subTaskForm" class="action-btn success">  
                    <i class="fa fa-save"></i> Criar Subtarefa  
                </button>  
            </div>  
        </div>  
    </div>  
</div>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    
    <script>  
        document.getElementById('compartilharAnexos').addEventListener('change', function() {  
            const fileInput = document.getElementById('subTaskAttachments');  
            const uploadWrapper = fileInput.closest('.file-upload-wrapper');  
            
            if (this.checked) {  
                uploadWrapper.style.opacity = '0.5';  
                fileInput.disabled = true;  
            } else {  
                uploadWrapper.style.opacity = '1';  
                fileInput.disabled = false;  
            }  
        });  
    </script>
    <script>

        document.addEventListener('DOMContentLoaded', function() {
            var subTaskDeadlineInput = document.getElementById('subTaskDeadline');
            var now = new Date();
            var year = now.getFullYear();
            var month = ('0' + (now.getMonth() + 1)).slice(-2); // Meses são 0-indexados
            var day = ('0' + now.getDate()).slice(-2);
            var hours = ('0' + now.getHours()).slice(-2);
            var minutes = ('0' + now.getMinutes()).slice(-2);

            // Formato YYYY-MM-DDTHH:MM
            var minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            subTaskDeadlineInput.min = minDateTime;
        });

        document.addEventListener('DOMContentLoaded', function() {
            var dataRecebimentoInput = document.getElementById('dataRecebimento');
            var now = new Date();
            var year = now.getFullYear();
            var month = ('0' + (now.getMonth() + 1)).slice(-2); // Meses são 0-indexados
            var day = ('0' + now.getDate()).slice(-2);
            var hours = ('0' + now.getHours()).slice(-2);
            var minutes = ('0' + now.getMinutes()).slice(-2);

            // Formato YYYY-MM-DDTHH:MM
            var minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            dataRecebimentoInput.min = minDateTime;
        });

        document.addEventListener('DOMContentLoaded', function() {
            var dataEntregaInput = document.getElementById('dataEntrega');
            var now = new Date();
            var year = now.getFullYear();
            var month = ('0' + (now.getMonth() + 1)).slice(-2); // Meses são 0-indexados
            var day = ('0' + now.getDate()).slice(-2);
            var hours = ('0' + now.getHours()).slice(-2);
            var minutes = ('0' + now.getMinutes()).slice(-2);

            // Formato YYYY-MM-DDTHH:MM
            var minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            dataEntregaInput.min = minDateTime;
        });

        function normalizeText(text) {
            return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        function formatDateTime(dateTime) {
            var date = new Date(dateTime);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
        }

        $(document).ready(function() {
            $('#createSubTaskButton').on('click', function() {
                var taskId = $('#taskNumber').text(); // Obtém o ID da tarefa principal
                $('#subTaskPrincipalId').val(taskId); // Define o ID da tarefa principal no campo oculto
            });
        });

        function loadMainTask(subTaskId) {
            $.ajax({
                url: 'get_tarefa_principal.php', // Arquivo PHP que busca a tarefa principal
                type: 'GET',
                data: { id_tarefa_sub: subTaskId }, // Envia o ID da subtarefa
                success: function(response) {
                    var mainTask = JSON.parse(response);
                    var mainTaskTableBody = $('#mainTaskTableBody');
                    var mainTaskTable = $('#mainTaskTable');
                    var mainTaskHeader = $('#mainTaskHeader');

                    mainTaskTableBody.empty(); // Limpa as linhas antigas da tabela
                    
                    // Verifica se a tarefa que está sendo visualizada é a mesma que a principal
                    if (mainTask.error || !mainTask.id || mainTask.id == subTaskId) {
                        mainTaskTable.hide(); // Oculta a tabela e o cabeçalho se não houver tarefa principal ou se for a própria tarefa
                        mainTaskHeader.hide();
                    } else {
                        mainTaskTable.show(); // Mostra a tabela de tarefa principal
                        mainTaskHeader.show();
                        var row = '<tr>' +
                            '<td>' + mainTask.id + '</td>' +
                            '<td>' + mainTask.titulo + '</td>' +
                            '<td>' + mainTask.funcionario_responsavel + '</td>' +
                            '<td>' + new Date(mainTask.data_criacao).toLocaleString("pt-BR") + '</td>' +
                            '<td>' + new Date(mainTask.data_limite).toLocaleString("pt-BR") + '</td>' +
                            '<td><span class="' + getStatusClass(mainTask.status) + '">' + capitalize(mainTask.status) + '</span></td>' +
                            '<td><button title="Visualizar" class="btn btn-info btn-sm" onclick="abrirTarefaEmNovaGuia(' + mainTask.id + ')">' +
                            '<i class="fa fa-eye" aria-hidden="true"></i></button></td>' +
                            '</tr>';
                        mainTaskTableBody.append(row);

                    }
                },
                error: function() {
                    alert('Erro ao buscar a tarefa principal');
                }
            });
        }


        $('#viewTaskModal').on('shown.bs.modal', function() {
            var taskId = $('#taskNumber').text(); // ID da tarefa (pode ser principal ou subtarefa)
            
            loadSubTasks(taskId); // Carregar subtarefas da tarefa principal (caso seja uma tarefa principal)
            loadMainTask(taskId); // Carregar a tarefa principal (caso seja uma subtarefa)
        });


        function loadSubTasks(taskId) {
            $.ajax({
                url: 'get_sub_tasks.php', // Certifique-se que a URL está correta
                type: 'GET',
                data: { id_tarefa_principal: taskId }, // Envia o ID da tarefa principal
                success: function(response) {
                    var subTasks = JSON.parse(response);
                    var subTasksTableBody = $('#subTasksTableBody');
                    var subTasksTable = $('#subTasksTable');
                    var subTasksHeader = $('#subTasksHeader');

                    subTasksTableBody.empty(); // Limpa as linhas antigas da tabela

                    if (subTasks.length > 0) {
                        subTasksTable.show(); // Mostra a tabela de subtarefas caso existam
                        subTasksHeader.show();
                        subTasks.forEach(function(subTask) {
                            var statusClass = getStatusClass(subTask.status);
                            var rowClass = getRowClass(subTask.status, subTask.data_limite);
                            var row = '<tr class="' + rowClass + '">' +
                                '<td>' + subTask.id + '</td>' +
                                '<td>' + subTask.titulo + '</td>' +
                                '<td>' + subTask.funcionario_responsavel + '</td>' +
                                '<td>' + new Date(subTask.data_criacao).toLocaleString("pt-BR") + '</td>' +
                                '<td>' + new Date(subTask.data_limite).toLocaleString("pt-BR") + '</td>' +
                                '<td><span class="' + statusClass + '">' + capitalize(subTask.status) + '</span></td>' +
                                '<td><button title="Visualizar" class="btn btn-info btn-sm" onclick="abrirTarefaEmNovaGuia(' + subTask.id + ')">' +
                                '<i class="fa fa-eye" aria-hidden="true"></i></button></td>' +
                                '</tr>';
                            subTasksTableBody.append(row);
                        });
                    } else {
                        subTasksTable.hide(); // Oculta a tabela se não houver subtarefas
                        subTasksHeader.hide();
                    }
                },
                error: function() {
                    alert('Erro ao buscar as subtarefas');
                }
            });
        }

        function capitalize(text) {
            return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
        }

// Função para retornar a classe de status
function getStatusClass(status) {
    switch (status) {
        case 'Iniciada':
            return 'status-sub-iniciada';
        case 'Em Espera':
            return 'status-sub-em-espera';
        case 'Em Andamento':
            return 'status-sub-em-andamento';
        case 'Concluída':
            return 'status-sub-concluida';
        case 'Cancelada':
            return 'status-sub-cancelada';
        case 'pendente':
            return 'status-sub-pendente';
        default:
            return '';
    }
}

// Função para definir a classe de linha com base na data limite
function getRowClass(status, data_limite) {
    var deadlineDate = new Date(data_limite);
    var currentDate = new Date();
    var oneDay = 24 * 60 * 60 * 1000;

    if (status !== 'concluída' && status !== 'cancelada') {
        if (deadlineDate < currentDate) {
            return 'row-sub-vencida';  // Tarefa já está vencida
        } else if ((deadlineDate - currentDate) <= oneDay) {
            return 'row-sub-quase-vencida';  // Tarefa está prestes a vencer
        }
    }
    return '';
}

// Função para definir a classe de prioridade
function getPriorityClass(priority) {
    switch (priority) {
        case 'Baixa':
            return 'priority-low';
        case 'Média':
            return 'priority-sub-medium';
        case 'Alta':
            return 'priority-sub-high';
        case 'Crítica':
            return 'priority-sub-critical';
        default:
            return '';
    }
}

$('#viewTaskModal').on('shown.bs.modal', function() {
    var taskId = $('#taskNumber').text(); // ID da tarefa principal
    loadSubTasks(taskId); // Carregar subtarefas da tarefa principal
});



        $(document).ready(function() {
        // Inicializar DataTable uma vez, sem destruir
        var dataTable = $('#tabelaResultados').DataTable({
            "language": {
                "url": "../style/Portuguese-Brasil.json"
            },
            "pageLength": 10,
            "order": [[0, 'desc']], // Ordena a primeira coluna (índice 0) em ordem decrescente
            "destroy": false // Certificar-se de que não destruímos o DataTable
        });

        // Carregar automaticamente as tarefas com status diferente de "Concluída" e "Cancelada" ao carregar a página
        loadTasks();

        // Enviar formulário de pesquisa quando o usuário clicar em "Filtrar"
        $('#searchForm').on('submit', function(e) {
            e.preventDefault();
            loadTasks();  // Carregar as tarefas com base nos filtros do formulário
        });

        // Função para carregar as tarefas
        function loadTasks() {
            var formData = $('#searchForm').serialize();

            $.ajax({
                url: 'search_tasks.php',
                type: 'GET',
                data: formData,
                success: function(response) {
                    var tasks = JSON.parse(response);
                    
                    // Limpar os dados da tabela DataTables sem destruir a instância
                    dataTable.clear();

                    // Verificar se há tarefas
                    if (tasks.length > 0) {
                        // Popular a tabela com os novos dados
                        tasks.forEach(function(task) {
                            // Definir classe de status
                            var statusClass = getStatusClass(task.status.toLowerCase());

                            var rowClass = '';

                            // Aplicar regras de coloração apenas se o status não for "Concluída" ou "Cancelada"
                            if (task.status.toLowerCase() !== 'concluída' && task.status.toLowerCase() !== 'cancelada') {
                                // Verificar vencimento e definir classe de linha
                                rowClass = getRowClass(task.status.toLowerCase(), task.data_limite);

                                // Se a tarefa não estiver vencida, aplicar a classe de prioridade
                                if (!rowClass) {
                                    rowClass = getPriorityClass(task.nivel_de_prioridade);
                                }
                            }

                            // Definir os botões de ação
                            var actions = '<button class="btn btn-info btn-sm" onclick="viewTask(\'' + task.token + '\')"><i class="fa fa-eye" aria-hidden="true"></i></button>';
                            if (task.status.toLowerCase() !== 'concluída') {
                                actions += '<button class="btn btn-edit btn-sm" onclick="editTask(' + task.id + ')"><i class="fa fa-pencil" aria-hidden="true"></i></button>';
                            }

                                // Função para adicionar classes de status baseado no estado para o fundo e texto
                                function getStatusClassBackground(situacao) {
                                    switch (situacao) {
                                        case 'Prestes a vencer':
                                            return 'status-prestes-vencer';  // Classe para fundo e texto de "Prestes a vencer"
                                        case 'Vencida':
                                            return 'status-vencida';  // Classe para fundo e texto de "Vencida"
                                        default:
                                            return '';
                                    }
                                }

                                var descricaoLimitada = task.descricao.length > 80 ? task.descricao.substring(0, 80) + '...' : task.descricao;

                                var situacao = '';
                                if (rowClass === 'row-vencida') {
                                    situacao = 'Vencida';
                                } else if (rowClass === 'row-quase-vencida') {
                                    situacao = 'Prestes a vencer';
                                } else {
                                    situacao = '-';
                                }

                                // Adicionar a classe de fundo e texto para a situação
                                var statusClassBackground = getStatusClassBackground(situacao);

                                var row = dataTable.row.add([
                                    task.id,
                                    task.titulo,
                                    task.categoria_titulo,
                                    task.origem_titulo,
                                    descricaoLimitada, // Limita a descrição a 80 caracteres
                                    new Date(task.data_limite).toLocaleString("pt-BR"),
                                    task.funcionario_responsavel,
                                    task.nivel_de_prioridade,
                                    '<span class="status-label ' + statusClass + '">' + capitalize(task.status) + '</span>',
                                    '<span class="status-label ' + statusClassBackground + '">' + situacao + '</span>', // Coluna "Situação" com classe dinâmica
                                    actions
                                ]).draw().node();

                                // Aplicar a classe de coloração na linha
                                $(row).addClass(rowClass);

                        });
                    } else {
                        // Exibir mensagem de "Nenhum resultado encontrado" se não houver tarefas
                        $('#taskTable').html('<tr><td colspan="10" class="text-center">Nenhum resultado encontrado</td></tr>');
                    }
                },
                error: function() {
                    alert('Erro ao buscar as tarefas');
                }
            });
        }

        // Função para retornar a classe de status
        function getStatusClass(status) {
            switch (status) {
                case 'iniciada':
                    return 'status-iniciada';
                case 'em espera':
                    return 'status-em-espera';
                case 'em andamento':
                    return 'status-em-andamento';
                case 'concluída':
                    return 'status-concluida';
                case 'cancelada':
                    return 'status-cancelada';
                case 'pendente':
                    return 'status-pendente';
                default:
                    return '';
            }
        }

        // Função para definir a classe de linha com base na data limite
        function getRowClass(status, data_limite) {
            var deadlineDate = new Date(data_limite);
            var currentDate = new Date();
            var oneDay = 24 * 60 * 60 * 1000;

            if (status !== 'concluída' && status !== 'cancelada') {
                if (deadlineDate < currentDate) {
                    return 'row-vencida';  // Tarefa já está vencida
                } else if ((deadlineDate - currentDate) <= oneDay) {
                    return 'row-quase-vencida';  // Tarefa está prestes a vencer
                }
            }
            return '';
        }

        // Função para definir a classe de prioridade
        function getPriorityClass(priority) {
            switch (priority) {
                case 'Baixa':
                    return 'priority-low';
                case 'Média':
                    return 'priority-medium';
                case 'Alta':
                    return 'priority-high';
                case 'Crítica':
                    return 'priority-critical';
                default:
                    return '';
            }
        }

        // Função para capitalizar a primeira letra
        function capitalize(text) {
            return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
        }
    });

    $(document).ready(function() {
        // Adiciona o evento de clique ao botão quando a página for carregada
        $('#guiaProtocoloButton').on('click', function() {
            // Faz a requisição para o JSON
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false, // Desabilita o cache
                success: function(data) {
                    const taskId = document.getElementById('taskNumber').innerText; // Pega o taskId via o elemento HTML
                    let url = '';

                    // Verifica o valor do "timbrado" e ajusta a URL
                    if (data.timbrado === 'S') {
                        url = 'protocolo_geral.php?id=' + taskId;
                    } else if (data.timbrado === 'N') {
                        url = 'protocolo-geral.php?id=' + taskId;
                    }

                    // Abre a URL correspondente em uma nova aba
                    window.open(url, '_blank');
                },
                error: function() {
                    alert('Erro ao carregar o arquivo de configuração.');
                }
            });
        });
    });

        $('#commentForm').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var taskToken = $('#viewTitle').data('tasktoken'); // Assume que o token da tarefa está armazenado como atributo de dados

            formData.append('taskToken', taskToken);

            $.ajax({
                url: 'add_comment.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#addCommentModal').modal('hide');
                    $('body').removeClass('modal-open'); // Corrigir problema de rolagem

                    Swal.fire({
                        title: 'Sucesso!',
                        text: 'Comentário adicionado com sucesso!',
                        icon: 'success'
                    }).then(() => {
                        viewTask(taskToken); // Atualizar a visualização da tarefa
                    });
                },
                error: function() {
                    Swal.fire({
                        title: 'Erro!',
                        text: 'Erro ao adicionar comentário',
                        icon: 'error'
                    });
                }
            });
        });


        $('#saveStatusButton').on('click', function() {
            var taskToken = $('#viewTitle').data('tasktoken');
            var status = $('#viewStatus').val();
            var currentDate = new Date().toISOString().slice(0, 19).replace('T', ' ');

            Swal.fire({
                title: 'Tem certeza?',
                text: 'Deseja realmente atualizar o status da tarefa para "' + status + '"?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, atualizar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Se o usuário confirmar, proceder com a atualização
                    $.ajax({
                        url: 'update_status.php',
                        type: 'POST',
                        data: {
                            taskToken: taskToken,
                            status: status,
                            dataConclusao: status.toLowerCase() === 'concluída' ? currentDate : null
                        },
                        success: function(response) {
                            // SweetAlert2 para sucesso, mas sem fechar o modal
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Status atualizado com sucesso!'
                            }).then(() => {
                                $('#searchForm').submit(); // Atualizar a lista de tarefas, se necessário
                                // O modal continuará aberto após a atualização
                            });
                        },
                        error: function() {
                            // SweetAlert2 para erro
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Ocorreu um erro ao atualizar o status.'
                            });
                        }
                    });
                }
            });
        });

// Resolver problema de rolagem com modais empilhados
$('#addCommentModal, #createSubTaskModal, #guiaRecebimentoModal, #reciboEntregaModal, #vincularOficioModal').on('shown.bs.modal', function() {
    $('body').addClass('modal-open');
}).on('hidden.bs.modal', function() {
    $('body').removeClass('modal-open');
});

$('#viewTaskModal').on('shown.bs.modal', function() {
    // Fazer uma chamada AJAX para buscar o nível de acesso do usuário logado
    $.ajax({
        url: 'get_user_access.php',
        type: 'GET',
        success: function(response) {
            var userData = JSON.parse(response);
            var nivelAcesso = userData.nivel_de_acesso;
            
            var dataConclusao = $('#viewStatus').data('data-conclusao');
            if (dataConclusao !== null && dataConclusao !== "NULL" && dataConclusao !== "") {
                // Se a tarefa já tem data de conclusão e o nível de acesso for "usuario", desabilitar o botão
                if (nivelAcesso === 'usuario') {
                    $('#saveStatusButton').prop('disabled', true);
                } else if (nivelAcesso === 'administrador') {
                    $('#saveStatusButton').prop('disabled', false); // Administradores podem alterar o status
                }
            } else {
                // Se não há data de conclusão, permitir alteração para todos
                $('#saveStatusButton').prop('disabled', false);
            }
        },
        error: function() {
            alert('Erro ao verificar o nível de acesso do usuário.');
        }
    });

    // Verificar se a tarefa tem um ofício vinculado
    var numeroOficio = $('#viewTitle').data('numeroOficio');
    if (numeroOficio) {
        $('#vincularOficioButton').html('<i class="fa fa-eye" aria-hidden="true"></i> Visualizar Ofício')
            .attr('onclick', 'viewOficio(\'' + numeroOficio + '\')')
            .removeAttr('data-toggle data-target');
    } else {
        $('#vincularOficioButton').html('<i class="fa fa-link" aria-hidden="true"></i> Vincular Ofício')
            .attr('data-toggle', 'modal')
            .attr('data-target', '#vincularOficioModal')
            .removeAttr('onclick');
    }

   // Verificar se o recibo de entrega já foi gerado
    if ($('#viewTitle').data('reciboGerado')) {
        $('#reciboEntregaButton').off('click').on('click', function() {
            // Faz a requisição para o JSON
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false, // Desabilita o cache
                success: function(data) {
                    const taskId = $('#taskNumber').text(); // Pega o taskNumber via jQuery
                    let url = '';

                    // Verifica o valor do "timbrado" e ajusta a URL
                    if (data.timbrado === 'S') {
                        url = 'recibo_entrega.php?id=' + taskId;
                    } else if (data.timbrado === 'N') {
                        url = 'recibo-entrega.php?id=' + taskId;
                    }

                    // Abre a URL correspondente em uma nova aba
                    window.open(url, '_blank');
                },
                error: function() {
                    alert('Erro ao carregar o arquivo de configuração.');
                }
            });
        });

    } else {
        $('#reciboEntregaButton').off('click').on('click', function() {
            $('#reciboEntregaModal').modal('show');
            $('#entregador').val($('#viewEmployee').val()); // Preencher o entregador automaticamente
            $('#receptor').val(''); // Limpar o campo receptor
            $('#observacoes').val(''); // Limpar o campo observações
            $('#dataEntrega').val(''); // Limpar o campo data da entrega
            $('#documentos').val(''); // Limpar o campo documentos entregues
        });
    }
});


    $('#reciboEntregaForm').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize() + '&task_id=' + $('#taskNumber').text();

        $.ajax({
            url: 'save_recibo_entrega.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                try {
                    var result = JSON.parse(response);
                    if (result.success) {
                        $('#reciboEntregaModal').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Recibo de entrega salvo com sucesso!',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.open('recibo_entrega.php?id=' + $('#taskNumber').text(), '_blank');
                        });
                    } else {
                        console.error(result.error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Erro ao salvar o recibo de entrega: ' + result.error,
                            confirmButtonText: 'OK'
                        });
                    }
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e, response);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao salvar o recibo de entrega',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao salvar o recibo de entrega',
                    confirmButtonText: 'OK'
                });
            }
        });
    });
           

// Função para verificar se a guia de recebimento já foi gerada
function verificarOuAbrirGuia(taskId) {
    $.ajax({
        url: 'verificar_guia.php', // O arquivo PHP que criamos para verificar a guia
        type: 'GET',
        data: {
            task_id: taskId
        },
        success: function(response) {
            var result = JSON.parse(response);

            if (result.guia_existe) {
                // Se a guia já existe, abrir a guia diretamente com o task_id
                $.ajax({
                    url: '../style/configuracao.json',
                    dataType: 'json',
                    cache: false,
                    success: function(data) {
                        let url = '';

                        if (data.timbrado === 'S') {
                            url = 'guia_recebimento.php?id=' + taskId; // Usar task_id na URL
                        } else {
                            url = 'guia-recebimento.php?id=' + taskId; // Usar task_id na URL
                        }

                        // Abre a URL correspondente em uma nova aba
                        window.open(url, '_blank');
                    },
                    error: function() {
                        alert('Erro ao carregar o arquivo de configuração.');
                    }
                });

            } else {
                // Se a guia não existe, abrir o modal para criar a guia
                $('#guiaRecebimentoModal').modal('show');
                $('#funcionario').val($('#viewEmployee').val()); // Preencher o funcionário automaticamente
                $('#cliente').val(''); // Limpar o campo cliente
                $('#observacoes').val(''); // Limpar o campo observações
                $('#dataRecebimento').val(''); // Limpar o campo data de recebimento
                $('#documentosRecebidos').val(''); // Limpar o campo documentos recebidos
            }
        },
        error: function() {
            alert('Erro ao verificar a guia de recebimento.');
        }
    });
}

// Associa a verificação à ação do botão de guia de recebimento
$('#guiaRecebimentoButton').off('click').on('click', function() {
    const taskId = $('#taskNumber').text(); // Pega o taskNumber via jQuery
    verificarOuAbrirGuia(taskId);
});


// Submissão do formulário de criação de guia
$('#guiaRecebimentoForm').on('submit', function(e) {
    e.preventDefault();

    var formData = $(this).serialize() + '&task_id=' + $('#taskNumber').text();

    $.ajax({
        url: 'save_guia_recebimento.php',
        type: 'POST',
        data: formData,
        success: function(response) {
            try {
                var result = JSON.parse(response);
                if (result.success) {
                    $('#guiaRecebimentoModal').modal('hide');
                    alert('Guia de recebimento salva com sucesso!');
                    window.open('guia-recebimento.php?id=' + $('#taskNumber').text(), '_blank');
                } else {
                    console.error(result.error);
                    alert('Erro ao salvar a guia de recebimento: ' + result.error);
                }
            } catch (e) {
                console.error('Erro ao parsear JSON:', e, response);
                alert('Erro ao salvar a guia de recebimento');
            }
        },
        error: function() {
            alert('Erro ao salvar a guia de recebimento');
        }
    });
});



function viewTask(taskToken) {
    $.ajax({
        url: 'view_task.php',
        type: 'GET',
        data: {
            token: taskToken
        },
        success: function(response) {
            var task = JSON.parse(response);

            // Carregar os dados da tarefa
            $('#viewTitle').val(task.titulo)
                .data('tasktoken', taskToken)
                .data('numeroOficio', task.numero_oficio)
                .data('reciboGerado', task.recibo_gerado) // Recibo de Entrega gerado
                .data('guiaGerado', task.guia_gerada); // Guia de Recebimento gerado

            $('#viewCategory').val(task.categoria_titulo);
            $('#viewOrigin').val(task.origem_titulo);
            $('#viewDeadline').val(new Date(task.data_limite).toLocaleString("pt-BR"));
            $('#viewEmployee').val(task.funcionario_responsavel);
            $('#viewDescription').val(task.descricao);
            $('#viewStatus').val(task.status).data('data-conclusao', task.data_conclusao);
            $('#createdBy').val(task.criado_por);
            $('#createdAt').val(new Date(task.data_criacao).toLocaleString("pt-BR"));
            $('#taskNumber').text(task.id); // Atualizar o número da tarefa aqui
            $('#viewConclusionDate').val(task.data_conclusao ? new Date(task.data_conclusao).toLocaleString("pt-BR") : '');

            // Gerenciamento de anexos
            var viewAttachments = $('#viewAttachments');
            viewAttachments.empty();
            if (task.caminho_anexo) {
                task.caminho_anexo.split(';').forEach(function(anexo, index) {
                    var fileName = anexo.split('/').pop();
                    var filePath = anexo.startsWith('/') ? anexo : '/' + anexo;
                    var attachmentItem = '<div class="anexo-item">' +
                        '<span>' + (index + 1) + '</span>' +
                        '<span>' + fileName + '</span>' +
                        '<button class="btn btn-info btn-sm visualizar-anexo" data-file="' + filePath + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                        '</div>';
                    viewAttachments.append(attachmentItem);
                });
            }

            // Gerenciamento da linha do tempo de comentários
            var commentTimeline = $('#commentTimeline');
            commentTimeline.empty();
            if (task.comentarios) {
                task.comentarios.forEach(function(comentario) {
                    var commentDate = new Date(comentario.data_comentario);
                    var commentDateFormatted = commentDate.toLocaleString("pt-BR");

                    var commentItem = '<div class="timeline-item">';
                    if (comentario.is_subtask) {
                        // Destaque para comentários de subtarefas
                        commentItem += '<div class="timeline-badge subtask"><i class="fa fa-comments-o" aria-hidden="true"></i></div>'; // Ícone especial para subtarefas
                        commentItem += '<div class="timeline-panel subtask-panel">'; // Estilo especial para subtarefas
                        commentItem += '<div class="timeline-heading"><h6 class="timeline-title">' + (comentario.funcionario || 'Desconhecido') + ' <small>' + commentDateFormatted + '</small></h6>';
                        commentItem += '<div class="subtask-title">Comentário de Subtarefa</div>'; // Título de "Comentário de Subtarefa"
                    } else {
                        commentItem += '<div class="timeline-badge primary"><i class="fa fa-commenting-o" aria-hidden="true"></i></div>';
                        commentItem += '<div class="timeline-panel">';
                        commentItem += '<div class="timeline-heading"><h6 class="timeline-title">' + (comentario.funcionario || 'Desconhecido') + ' <small>' + commentDateFormatted + '</small></h6>';
                    }

                    commentItem += '</div><div class="timeline-body"><p>' + comentario.comentario + '</p>';
                    
                    if (comentario.caminho_anexo) {
                        comentario.caminho_anexo.split(';').forEach(function(anexo) {
                            var fileName = anexo.split('/').pop();
                            var filePath = anexo.startsWith('/') ? anexo : '/' + anexo;
                            commentItem += '<div class="anexo-item">' +
                                '<span>' + fileName + '</span>' +
                                '<button class="btn btn-info btn-sm visualizar-anexo" data-file="' + filePath + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                                '</div>';
                        });
                    }

                    commentItem += '</div></div></div>';
                    commentTimeline.append(commentItem);
                });
            }

            // Exibir o modal da tarefa
            $('#viewTaskModal').modal('show');
        },
        error: function() {
            alert('Erro ao buscar a tarefa');
        }
    });
}


        $(document).on('click', '.visualizar-anexo', function() {
            var filePath = $(this).data('file');
            var baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
            if (!filePath.startsWith('/')) {
                filePath = '/' + filePath;
            }
            window.open(baseUrl + filePath, '_blank');
        });

        function editTask(taskId) {
            window.location.href = 'edit_task.php?id=' + taskId;
        }

        function deleteTask(taskId) {
            if (confirm('Tem certeza que deseja excluir esta tarefa?')) {
                $.ajax({
                    url: 'delete_task.php',
                    type: 'POST',
                    data: {
                        id: taskId
                    },
                    success: function(response) {
                        alert('Tarefa excluída com sucesso');
                        $('#searchForm').submit(); // Recarregar a lista de tarefas
                    },
                    error: function() {
                        alert('Erro ao excluir a tarefa');
                    }
                });
            }
        }

        $('#vincularOficioForm').on('submit', function(e) {
            e.preventDefault();

            var taskId = $('#viewTitle').data('tasktoken'); // Assume que o token da tarefa está armazenado aqui
            var numeroOficio = $('#numeroOficio').val();

            $.ajax({
                url: 'vincular_oficio.php',
                type: 'POST',
                data: {
                    taskToken: taskId,
                    numeroOficio: numeroOficio
                },
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.success) {
                        alert('Ofício vinculado com sucesso!');
                        $('#vincularOficioModal').modal('hide');
                        $('#vincularOficioButton').html('<i class="fa fa-eye" aria-hidden="true"></i> Visualizar Ofício').attr('onclick', 'viewOficio(\'' + numeroOficio + '\')').removeAttr('data-toggle data-target');
                        $('#viewTitle').data('numeroOficio', numeroOficio); // Atualizar o atributo de dados da tarefa
                        viewOficio(numeroOficio); // Abrir o ofício em uma nova guia
                    } else {
                        alert('Erro ao vincular o ofício');
                    }
                },
                error: function() {
                    alert('Erro ao vincular o ofício');
                }
            });
        });

        function viewOficio(numero) {
            // Faz a requisição para o arquivo JSON
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false, // Desabilita o cache
                success: function(data) {
                    let url = '';

                    // Verifica o valor do "timbrado" e ajusta a URL
                    if (data.timbrado === 'S') {
                        url = 'ver_oficio.php?numero=' + numero;
                    } else if (data.timbrado === 'N') {
                        url = 'ver-oficio.php?numero=' + numero;
                    }

                    // Abre a URL correspondente em uma nova aba
                    window.open(url, '_blank');
                },
                error: function() {
                    alert('Erro ao carregar o arquivo de configuração.');
                }
            });
        }

        
        $(document).ready(function() {
            // Função para obter o valor do parâmetro da URL
            function getUrlParameter(name) {
                name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
                var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
                var results = regex.exec(location.search);
                return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
            }

            // Verifica se existe um token na URL
            var taskToken = getUrlParameter('token');

            if (taskToken) {
                // Se houver um token, chama a função para visualizar a tarefa e abrir o modal
                viewTask(taskToken);
            }

            // Adiciona um evento para redirecionar a página quando o modal for fechado e se houver um token
            $('#viewTaskModal').on('hidden.bs.modal', function () {
                if (taskToken) { // Verifica se o token está presente
                    window.location.href = 'index.php'; // Redireciona para index.php quando o modal é fechado
                }
            });
        });


        // Ação para abrir o formulário usando SweetAlert2
        function showAddCommentForm() {
            Swal.fire({
                title: 'Adicionar Comentário e Anexos',
                html: `
                    <form id="swalCommentForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="swalCommentDescription">Comentário:</label>
                            <textarea class="form-control" id="swalCommentDescription" name="commentDescription" rows="5"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="swalCommentAttachments">Anexar arquivos:</label>
                            <input type="file" id="swalCommentAttachments" name="commentAttachments[]" multiple class="form-control-file">
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Salvar Comentário',
                cancelButtonText: 'Fechar',
                preConfirm: () => {
                    // Coletar dados do formulário antes de confirmar
                    const commentDescription = document.getElementById('swalCommentDescription').value;
                    const attachments = document.getElementById('swalCommentAttachments').files;

                    if (!commentDescription) {
                        Swal.showValidationMessage('O campo de comentário é obrigatório.');
                        return false;
                    }

                    return {
                        commentDescription: commentDescription,
                        attachments: attachments
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Criar um FormData para enviar os dados via AJAX
                    const formData = new FormData();
                    formData.append('commentDescription', result.value.commentDescription);

                    for (let i = 0; i < result.value.attachments.length; i++) {
                        formData.append('commentAttachments[]', result.value.attachments[i]);
                    }

                    // Fazer a requisição AJAX para salvar o comentário
                    $.ajax({
                        url: 'add_comment.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            Swal.fire({
                                title: 'Sucesso!',
                                text: 'Comentário adicionado com sucesso!',
                                icon: 'success'
                            }).then(() => {
                                // Atualizar a visualização da tarefa
                                location.reload();
                            });
                        },
                        error: function() {
                            Swal.fire({
                                title: 'Erro!',
                                text: 'Erro ao adicionar comentário.',
                                icon: 'error'
                            });
                        }
                    });
                }
            });
        }

        function abrirTarefaEmNovaGuia(tarefaId) {
            $.ajax({
                url: 'get_token.php',
                type: 'GET',
                data: { id: tarefaId },
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.token) {
                        var url = 'index_tarefa.php?token=' + result.token;
                        window.open(url, '_blank'); // Abre a nova aba com o token
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Token não encontrado para essa tarefa.'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao buscar o token da tarefa.'
                    });
                }
            });
        }

        $(document).on('show.bs.modal', function () {
            // Desativa a rolagem do fundo
            $('body').css('overflow', 'hidden');
        });

        $(document).on('hidden.bs.modal', function () {
            // Restaura a rolagem do fundo apenas se não houver mais modais abertos
            if ($('.modal.show').length === 0) {
                $('body').css('overflow', 'auto');
            }
        });

        // Adicionar rolagem ao modal principal após fechar o secundário
        $('#vincularOficioModal, #reciboEntregaModal, #guiaRecebimentoModal, #createSubTaskModal, #addCommentModal').on('hidden.bs.modal', function () {
            $('#viewTaskModal').css('overflow-y', 'auto');
        });


        document.addEventListener('DOMContentLoaded', function() {  
            const fileInput = document.getElementById('subTaskAttachments');  
            const selectedFilesDiv = document.getElementById('selectedFiles');  
            const uploadText = document.querySelector('.upload-text');  
            let filesArray = []; // Array para manter controle dos arquivos  

            fileInput.addEventListener('change', function(e) {  
                const files = Array.from(e.target.files);  
                updateFileList(files);  
            });  

            function updateFileList(newFiles) {  
                filesArray = newFiles; // Atualiza o array de arquivos  
                selectedFilesDiv.innerHTML = ''; // Limpa a lista atual  

                if (filesArray.length > 0) {  
                    // Adiciona contador de arquivos  
                    const counterDiv = document.createElement('div');  
                    counterDiv.className = 'files-counter';  
                    counterDiv.textContent = `${filesArray.length} arquivo(s) selecionado(s)`;  
                    selectedFilesDiv.appendChild(counterDiv);  

                    // Lista cada arquivo  
                    filesArray.forEach((file, index) => {  
                        const fileItem = document.createElement('div');  
                        fileItem.className = 'file-item';  

                        const fileInfo = document.createElement('div');  
                        fileInfo.className = 'file-info';  
                        
                        // Escolhe o ícone baseado no tipo do arquivo  
                        let fileIcon = 'fa-file-o';  
                        if (file.type.includes('image')) fileIcon = 'fa-file-image-o';  
                        else if (file.type.includes('pdf')) fileIcon = 'fa-file-pdf-o';  
                        else if (file.type.includes('word')) fileIcon = 'fa-file-word-o';  
                        else if (file.type.includes('excel')) fileIcon = 'fa-file-excel-o';  

                        fileInfo.innerHTML = `  
                            <i class="fa ${fileIcon}"></i>  
                            <span class="file-name">${file.name}</span>  
                            <span class="file-size">(${formatFileSize(file.size)})</span>  
                        `;  

                        const removeButton = document.createElement('button');  
                        removeButton.className = 'remove-file';  
                        removeButton.innerHTML = '<i class="fa fa-times"></i>';  
                        removeButton.onclick = () => removeFile(index);  

                        fileItem.appendChild(fileInfo);  
                        fileItem.appendChild(removeButton);  
                        selectedFilesDiv.appendChild(fileItem);  
                    });  

                    // Atualiza o texto do label  
                    uploadText.textContent = 'Adicionar mais arquivos';  
                } else {  
                    // Reseta o texto se não houver arquivos  
                    uploadText.textContent = 'Arraste os arquivos ou clique para selecionar';  
                    selectedFilesDiv.innerHTML = '';  
                }  
            }  

            function removeFile(index) {  
                const dt = new DataTransfer();  
                
                // Recria o FileList sem o arquivo removido  
                filesArray.forEach((file, i) => {  
                    if (i !== index) dt.items.add(file);  
                });  
                
                fileInput.files = dt.files; // Atualiza o input  
                updateFileList(Array.from(dt.files)); // Atualiza a lista visual  
            }  

            function formatFileSize(bytes) {  
                if (bytes === 0) return '0 Bytes';  
                const k = 1024;  
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];  
                const i = Math.floor(Math.log(bytes) / Math.log(k));  
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];  
            }  

            // Adiciona suporte para drag and drop  
            const dropZone = document.querySelector('.file-upload-label');  

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {  
                dropZone.addEventListener(eventName, preventDefaults, false);  
            });  

            function preventDefaults(e) {  
                e.preventDefault();  
                e.stopPropagation();  
            }  

            ['dragenter', 'dragover'].forEach(eventName => {  
                dropZone.addEventListener(eventName, highlight, false);  
            });  

            ['dragleave', 'drop'].forEach(eventName => {  
                dropZone.addEventListener(eventName, unhighlight, false);  
            });  

            function highlight(e) {  
                dropZone.classList.add('drag-hover');  
            }  

            function unhighlight(e) {  
                dropZone.classList.remove('drag-hover');  
            }  

            dropZone.addEventListener('drop', handleDrop, false);  

            function handleDrop(e) {  
                const dt = e.dataTransfer;  
                const files = Array.from(dt.files);  
                fileInput.files = dt.files;  
                updateFileList(files);  
            }  
        });


        document.addEventListener('DOMContentLoaded', function() {  
            const fileInput = document.getElementById('commentAttachments');  
            const selectedFilesDiv = document.getElementById('selectedFiles');  
            const uploadText = document.querySelector('.upload-text');  
            let filesArray = [];  

            fileInput.addEventListener('change', function(e) {  
                const files = Array.from(e.target.files);  
                updateFileList(files);  
            });  

            function updateFileList(newFiles) {  
                filesArray = newFiles;  
                selectedFilesDiv.innerHTML = '';  

                if (filesArray.length > 0) {  
                    const counterDiv = document.createElement('div');  
                    counterDiv.className = 'files-counter';  
                    counterDiv.textContent = `${filesArray.length} arquivo(s) selecionado(s)`;  
                    selectedFilesDiv.appendChild(counterDiv);  

                    filesArray.forEach((file, index) => {  
                        const fileItem = document.createElement('div');  
                        fileItem.className = 'file-item';  

                        const fileInfo = document.createElement('div');  
                        fileInfo.className = 'file-info';  
                        
                        let fileIcon = 'fa-file-o';  
                        if (file.type.includes('image')) fileIcon = 'fa-file-image-o';  
                        else if (file.type.includes('pdf')) fileIcon = 'fa-file-pdf-o';  
                        else if (file.type.includes('word')) fileIcon = 'fa-file-word-o';  
                        else if (file.type.includes('excel')) fileIcon = 'fa-file-excel-o';  

                        fileInfo.innerHTML = `  
                            <i class="fa ${fileIcon}"></i>  
                            <span class="file-name">${file.name}</span>  
                            <span class="file-size">(${formatFileSize(file.size)})</span>  
                        `;  

                        const removeButton = document.createElement('button');  
                        removeButton.className = 'remove-file';  
                        removeButton.innerHTML = '<i class="fa fa-times"></i>';  
                        removeButton.onclick = () => removeFile(index);  

                        fileItem.appendChild(fileInfo);  
                        fileItem.appendChild(removeButton);  
                        selectedFilesDiv.appendChild(fileItem);  
                    });  

                    uploadText.textContent = 'Adicionar mais arquivos';  
                } else {  
                    uploadText.textContent = 'Arraste os arquivos ou clique para selecionar';  
                }  
            }  

            function removeFile(index) {  
                const dt = new DataTransfer();  
                filesArray.forEach((file, i) => {  
                    if (i !== index) dt.items.add(file);  
                });  
                fileInput.files = dt.files;  
                updateFileList(Array.from(dt.files));  
            }  

            function formatFileSize(bytes) {  
                if (bytes === 0) return '0 Bytes';  
                const k = 1024;  
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];  
                const i = Math.floor(Math.log(bytes) / Math.log(k));  
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];  
            }  

            // Drag and Drop  
            const dropZone = document.querySelector('.file-upload-label');  

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {  
                dropZone.addEventListener(eventName, preventDefaults, false);  
            });  

            function preventDefaults(e) {  
                e.preventDefault();  
                e.stopPropagation();  
            }  

            ['dragenter', 'dragover'].forEach(eventName => {  
                dropZone.addEventListener(eventName, highlight, false);  
            });  

            ['dragleave', 'drop'].forEach(eventName => {  
                dropZone.addEventListener(eventName, unhighlight, false);  
            });  

            function highlight(e) {  
                dropZone.classList.add('drag-hover');  
            }  

            function unhighlight(e) {  
                dropZone.classList.remove('drag-hover');  
            }  

            dropZone.addEventListener('drop', handleDrop, false);  

            function handleDrop(e) {  
                const dt = e.dataTransfer;  
                const files = Array.from(dt.files);  
                fileInput.files = dt.files;  
                updateFileList(files);  
            }  
        });

        $(document).ready(function() {
            // Função para obter o valor do parâmetro da URL
            function getUrlParameter(name) {
                name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
                var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
                var results = regex.exec(location.search);
                return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
            }

            // Verifica se existe um token na URL
            var taskToken = getUrlParameter('token');

            if (taskToken) {
                // Se houver um token, chama a função para visualizar a tarefa e abrir o modal
                viewTask(taskToken);
            }

            // Adiciona um evento para voltar à página anterior quando o modal for fechado e se houver um token
            $('#viewTaskModal').on('hidden.bs.modal', function () {
                if (taskToken) { 
                    window.history.back();
                }
            });
        });
    </script>
    <?php
    include(__DIR__ . '/../rodape.php');
    ?>
</body>

</html>