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

        /* .priority-medium {
            background-color: #fff9c4 !important; 
        } */

        /* .priority-high {
            background-color: #ffe082 !important; 
        } */

        /* .priority-critical {
            background-color: #ff8a80 !important; 
        } */
        .row-quase-vencida {
            background-color: #ffebcc!important; 
        }

        .row-vencida {
            background-color: #ffcccc!important; 
        }

        /* body.dark-mode .priority-medium td {
            background-color: #fff9c4 !important;
            color: #000!important;
        }

        body.dark-mode .priority-high td {
            background-color: #ffe082 !important; 
            color: #000!important;
        } */

        /* body.dark-mode .priority-critical td {
            background-color: #ff8a80 !important; 
        } */
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

/* === STATUS NORMAL === */
.status-iniciada {
    background-color: #89CFF0; /* Azul pastel */
    color: #000;
}
.status-em-espera {
    background-color: #FFD580; /* Amarelo pastel */
    color: #000;
}
.status-em-andamento {
    background-color: #7BAFD4; /* Azul médio pastel */
    color: #000;
}
.status-concluida {
    background-color: #A8E6A2; /* Verde pastel */
    color: #000;
}
.status-cancelada {
    background-color: #FFB3B3; /* Vermelho pastel */
    color: #000;
}
.status-pendente {
    background-color: #CFCFCF; /* Cinza claro pastel */
    color: #000;
}
.status-aguardando-retirada {
    background-color: #C1C8CD; /* Cinza azul pastel */
    color: #000;
}
.status-aguardando-pagamento {
    background-color: #FFF5BA; /* Amarelo muito claro pastel */
    color: #000;
}
.status-prazo-de-edital {
    background-color: #A0E7E5; /* Azul água pastel */
    color: #000;
}
.status-exigencia-cumprida {
    background-color: #B8E0D2; /* Verde água pastel */
    color: #000;
}
.status-finalizado-sem-pratica-do-ato {
    background-color: #A9A9A9; /* Cinza neutro pastel */
    color: #000;
}


/* === STATUS PARA SUBTAREFAS === */
[class*="status-sub-"] {
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
    font-weight: 500;
}

.status-sub-iniciada {
    background-color: #89CFF0;
    color: #000;
}
.status-sub-em-espera {
    background-color: #FFD580;
    color: #000;
}
.status-sub-em-andamento {
    background-color: #7BAFD4;
    color: #000;
}
.status-sub-concluida {
    background-color: #A8E6A2;
    color: #000;
}
.status-sub-cancelada {
    background-color: #FFB3B3;
    color: #000;
}
.status-sub-pendente {
    background-color: #CFCFCF;
    color: #000;
}
.status-sub-aguardando-retirada {
    background-color: #C1C8CD;
    color: #000;
}
.status-sub-aguardando-pagamento {
    background-color: #FFF5BA;
    color: #000;
}
.status-sub-prazo-de-edital {
    background-color: #A0E7E5;
    color: #000;
}
.status-sub-exigencia-cumprida {
    background-color: #B8E0D2;
    color: #000;
}
.status-sub-finalizado-sem-pratica-do-ato {
    background-color: #A9A9A9;
    color: #000;
}


/* === MODO DARK === */
body.dark-mode .status-iniciada {
    background-color: #4682B4;
    color: #f0f0f0;
}
body.dark-mode .status-em-espera {
    background-color: #B38B00;
    color: #f0f0f0;
}
body.dark-mode .status-em-andamento {
    background-color: #406882;
    color: #f0f0f0;
}
body.dark-mode .status-concluida {
    background-color: #4CAF50;
    color: #f0f0f0;
}
body.dark-mode .status-cancelada {
    background-color: #D46A6A;
    color: #f0f0f0;
}
body.dark-mode .status-pendente {
    background-color: #6C757D;
    color: #f0f0f0;
}
body.dark-mode .status-aguardando-retirada {
    background-color: #495057;
    color: #f0f0f0;
}
body.dark-mode .status-aguardando-pagamento {
    background-color: #B7950B;
    color: #f0f0f0;
}
body.dark-mode .status-prazo-de-edital {
    background-color: #15858A;
    color: #f0f0f0;
}
body.dark-mode .status-exigencia-cumprida {
    background-color: #157A6E;
    color: #f0f0f0;
}
body.dark-mode .status-finalizado-sem-pratica-do-ato {
    background-color: #343A40;
    color: #f0f0f0;
}


/* === SUB EM DARK === */
body.dark-mode [class*="status-sub-"] {
    color: #f0f0f0;
}

body.dark-mode .status-sub-iniciada {
    background-color: #4682B4;
}
body.dark-mode .status-sub-em-espera {
    background-color: #B38B00;
}
body.dark-mode .status-sub-em-andamento {
    background-color: #406882;
}
body.dark-mode .status-sub-concluida {
    background-color: #4CAF50;
}
body.dark-mode .status-sub-cancelada {
    background-color: #D46A6A;
}
body.dark-mode .status-sub-pendente {
    background-color: #6C757D;
}
body.dark-mode .status-sub-aguardando-retirada {
    background-color: #495057;
}
body.dark-mode .status-sub-aguardando-pagamento {
    background-color: #B7950B;
}
body.dark-mode .status-sub-prazo-de-edital {
    background-color: #15858A;
}
body.dark-mode .status-sub-exigencia-cumprida {
    background-color: #157A6E;
}
body.dark-mode .status-sub-finalizado-sem-pratica-do-ato {
    background-color: #343A40;
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
            left: -16px;
            display: inline-block;
            border-top: 15px solid transparent;
            border-right: 15px solid var(--border-color-light);
            border-left: 0 solid transparent;
            border-bottom: 15px solid transparent;
            content: " ";
        }


       .timeline-item .timeline-panel::after {
            position: absolute;
            top: 11px;
            left: -14px;
            display: inline-block;
            border-top: 14px solid transparent;
            border-right: 14px solid var(--block-bg); /* Usa a cor do bloco para se adaptar ao dark/light */
            border-left: 0 solid transparent;
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
        /* #viewStatus option[value="Iniciada"] { color: var(--accent-color); }  
        #viewStatus option[value="Em Espera"] { color: var(--warning-color); }  
        #viewStatus option[value="Em Andamento"] { color: var(--accent-color); }  
        #viewStatus option[value="Concluída"] { color: var(--success-color); }  
        #viewStatus option[value="Cancelada"] { color: var(--danger-color); }  
        #viewStatus option[value="Aguardando Retirada"] { color: var(--secondary-color); }  
        #viewStatus option[value="Aguardando Pagamento"] { color: var(--warning-color); }  
        #viewStatus option[value="Prazo de Edital"] { color: var(--info-color); }  
        #viewStatus option[value="Exigência Cumprida"] { color: var(--teal-color); }  
        #viewStatus option[value="Finalizado sem prática do ato"] { color: var(--dark-color); }   */

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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            background-color: var(--background-primary);
            color: var(--text-primary);
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
            background-color: var(--background-secondary);
        }

        .action-btn:active {
            transform: scale(0.98);
        }


        /* Botão Secondary (cinza) - para Protocolo Geral */  
        #guiaProtocoloButton {
            background-color: #6c757d;
            color: #fff;
        }

        #guiaProtocoloButton:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.3);
        }

        #guiaProtocoloButton:active {
            transform: scale(0.97);
        }


        /* Botão Info2 (azul claro) - para Guia Recebimento e Recibo Entrega */  
        #guiaRecebimentoButton,
        #reciboEntregaButton {
            background-color: #17a2b8;
            color: #fff;
        }

        #guiaRecebimentoButton:hover,
        #reciboEntregaButton:hover {
            background-color: #138496;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(23, 162, 184, 0.3);
        }

        #guiaRecebimentoButton:active,
        #reciboEntregaButton:active {
            transform: scale(0.97);
        }


        /* Botão Success (verde) - para Criar Ofício */  
        .action-btn.success {
            background-color: #28a745;
            color: #fff;
        }

        .action-btn.success:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
        }

        .action-btn.success:active {
            transform: scale(0.97);
        }


        /* Botão Primary (azul) - para Vincular Ofício */  
        .action-btn.primary {
            background-color: #007bff;
            color: #fff;
        }

        .action-btn.primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
        }

        .action-btn.primary:active {
            transform: scale(0.97);
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

.card-tarefa {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    background-color: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: transform 0.2s;
}

body.dark-mode .card-tarefa {
    background-color: #1f1f1f;
    border: 1px solid #333;
    box-shadow: 0 2px 6px rgba(0,0,0,0.5);
}

.card-tarefa:hover {
    transform: translateY(-3px);
}

.card-tarefa .card-title {
    font-size: 18px;
    font-weight: bold;
    color: #333;
}

body.dark-mode .card-tarefa .card-title {
    color: #f1f1f1;
}

.card-tarefa .card-info {
    margin-bottom: 5px;
    font-size: 14px;
    color: #555;
}

body.dark-mode .card-tarefa .card-info {
    color: #ccc;
}

.card-tarefa .badge {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 8px;
}

.card-actions {
    margin-top: 10px;
}

.card-actions button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    font-size: 0.9rem;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.card-actions button i {
    font-size: 1rem;
}

.card-actions .btn-visualizar {
    background-color: #17a2b8;
    color: white;
}

.card-actions .btn-visualizar:hover {
    background-color: #138496;
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(23, 162, 184, 0.3);
}

body.dark-mode .card-actions .btn-visualizar {
    background-color: #138496;
    color: #fff;
}

body.dark-mode .card-actions .btn-visualizar:hover {
    background-color: #0f6674;
}

.card-actions .btn-editar {
    background-color: #ffc107;
    color: #212529;
}

.card-actions .btn-editar:hover {
    background-color: #e0a800;
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(255, 193, 7, 0.3);
}

body.dark-mode .card-actions .btn-editar {
    background-color: #d39e00;
    color: #fff;
}

body.dark-mode .card-actions .btn-editar:hover {
    background-color: #a37000;
}

/* Status Label */
.status-label {
    padding: 4px 8px;
    border-radius: 8px;
    color: #fff;
    font-size: 12px;
}
/* ---------- LIGHT MODE ---------- */
body.light-mode .status-iniciada {
    background-color: #A5D8FF; /* Azul pastel claro */
    color: #000;
}

body.light-mode .status-em-andamento {
    background-color: #91A8D0; /* Azul suave */
    color: #000;
}

body.light-mode .status-concluida {
    background-color: #A8E6A3; /* Verde claro pastel */
    color: #000;
}

body.light-mode .status-cancelada {
    background-color: #FFB3B3; /* Vermelho claro pastel */
    color: #000;
}

body.light-mode .status-pendente {
    background-color: #FFF4B2; /* Amarelo bem suave pastel */
    color: #000;
}

body.light-mode .status-em-espera {
    background-color: #FFD59A; /* Laranja bem suave pastel */
    color: #000;
}

/* Situação */
body.light-mode .situacao-vencida {
    background-color: #FFB3B3;
    color: #000;
}

body.light-mode .situacao-quase {
    background-color: #FFF4B2;
    color: #000;
}


/* ---------- DARK MODE ---------- */
body.dark-mode .status-iniciada {
    background-color: #4682B4; /* Azul médio fechado */
    color: #f0f0f0;
}

body.dark-mode .status-em-andamento {
    background-color: #5B7DB1; /* Azul fechado */
    color: #f0f0f0;
}

body.dark-mode .status-concluida {
    background-color: #3E8E41; /* Verde musgo fechado */
    color: #f0f0f0;
}

body.dark-mode .status-cancelada {
    background-color: #D46A6A; /* Vermelho queimado */
    color: #f0f0f0;
}

body.dark-mode .status-pendente {
    background-color: #B7950B; /* Mostarda suave */
    color: #000;
}

body.dark-mode .status-em-espera {
    background-color: #B38B00; /* Laranja queimado */
    color: #000;
}


/* Situação */
body.dark-mode .situacao-vencida {
    background-color: #D46A6A; /* Vermelho queimado */
    color: #f0f0f0;
}

body.dark-mode .situacao-quase {
    background-color: #B7950B; /* Mostarda suave */
    color: #000;
}



/* Cores de Prioridade */
/* 🌞 Light Mode */
body.light-mode .card-tarefa.priority-medium {
    background-color: #eae5d7; /* Bege claro */
    color: #333; /* Preto suave */
}

body.light-mode .card-tarefa.priority-high {
    background-color: #ffcccc; /* Rosa pastel claro */
    color: #4d0000; /* Vermelho escuro para legibilidade */
}

body.light-mode .card-tarefa.priority-critical {
    background-color: #ffe6e6; /* Vermelho claro */
    color: #4d0000; /* Vermelho escuro para legibilidade */
}

/* 🌙 Dark Mode */
body.dark-mode .card-tarefa.priority-medium {
    background-color: #6a5b4d; /* Castanho claro */
    color: #f0f0f0; /* Branco suave */
}

body.dark-mode .card-tarefa.priority-high {
    background-color: #9a4d4d; /* Vermelho pastel */
    color: #f0f0f0; /* Branco suave */
}

body.dark-mode .card-tarefa.priority-critical {
    background-color: #c74e4e; /* Vermelho intenso */
    color: #f0f0f0; /* Branco suave */
}

/* Cores de Situação - Vencida */
/* 🌞 Light Mode */
body.light-mode .card-tarefa.situacao-vencida {
    border: 2px solid #d85f5f; /* Vermelho suave */
    background-color: #ffe6e6!important; /* Vermelho claro */
    color: #333; /* Preto suave */
}

/* 🌙 Dark Mode */
body.dark-mode .card-tarefa.situacao-vencida {
    border: 2px solid #d85f5f; /* Vermelho suave */
    background-color: #6a3b3b!important; /* Vermelho intenso */
    color: #f0f0f0; /* Branco suave */
}

/* Cores de Situação - Quase Vencida */
/* 🌞 Light Mode */
body.light-mode .card-tarefa.situacao-quase {
    border: 2px solid #ffd699; /* Amarelo suave */
    background-color: #fcf8e3; /* Amarelo muito claro */
    color: #333; /* Preto suave */
}

/* 🌙 Dark Mode */
body.dark-mode .card-tarefa.situacao-quase {
    border: 2px solid #d5d133; /* Amarelo escuro */
    background-color: #4e4233; /* Cinza escuro */
    color: #f0f0f0; /* Branco suave */
}

/* Aplicando Vencido Independente da Prioridade */
/* Light Mode */
body.light-mode .card-tarefa.vencida {
    background-color: #ffe6e6; /* Vermelho claro */
    color: #333; /* Preto suave */
}

/* Dark Mode */
body.dark-mode .card-tarefa.vencida {
    background-color: #6a3b3b; /* Vermelho intenso */
    color: #f0f0f0; /* Branco suave */
}

/* Base do select */
.status-select {
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #ccc;
    transition: background-color 0.3s ease, color 0.3s ease;
    font-weight: 600;
}

/* Cores para cada status */
.status-iniciada {
    background-color: #e3f2fd;
    color: #1976d2;
}

.status-em-espera {
    background-color: #fff3e0;
    color: #ef6c00;
}

.status-em-andamento {
    background-color: #ede7f6;
    color: #5e35b1;
}

.status-concluida {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.status-cancelada {
    background-color: #ffebee;
    color: #c62828;
}

.status-pendente {
    background-color: #fffde7;
    color: #f9a825;
}

.status-aguardando-retirada {
    background-color: #ede7f6;
    color: #512da8;
}

.status-aguardando-pagamento {
    background-color: #fce4ec;
    color: #ad1457;
}

.status-prazo-de-edital {
    background-color: #e3f2fd;
    color: #0288d1;
}

.status-exigencia-cumprida {
    background-color: #f1f8e9;
    color: #558b2f;
}

.status-finalizado-sem-pratica-do-ato {
    background-color: #f3e5f5;
    color: #6a1b9a;
}


/* === GRID FLEXÍVEL === */
.info-grid {
    display: grid;
    gap: 18px;
    margin-bottom: 15px;
}

.info-grid.columns-2 {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

.info-grid.columns-3 {
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
}

.info-grid.columns-4 {
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}


/* === LABELS === */
.info-item label {
    font-weight: 600;
    margin-bottom: 6px;
    display: block;
    color: var(--label-color);
    font-size: 0.95rem;
    letter-spacing: 0.2px;
}


/* === INPUT PADRÃO === */
.form-control-modern {
    width: 100%;
    border: 1px solid var(--border-color);
    padding: 10px 14px;
    border-radius: 8px;
    background-color: var(--input-bg);
    color: var(--text-color);
    transition: border-color 0.3s ease, background-color 0.3s;
    box-shadow: none;
    font-size: 0.95rem;
}

.form-control-modern:focus {
    border-color: var(--primary-color);
    outline: none;
}


/* === BLOCO DE INFORMAÇÕES === */
.creation-info {
    background-color: var(--block-bg);
    padding: 20px;
    border-radius: 12px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border-color-light);
}


/* === MODO LIGHT === */
body.light-mode {
    --block-bg: #ffffff;
    --border-color: #ccc;
    --border-color-light: #e0e0e0;
    --input-bg: #f9f9f9;
    --text-color: #333;
    --label-color: #555;
    --primary-color: #007bff;
    --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}


/* === MODO DARK === */
body.dark-mode {
    --block-bg: #1e1f22;
    --border-color: #555;
    --border-color-light: #444;
    --input-bg: #2a2b2f;
    --text-color: #ddd;
    --label-color: #bbb;
    --primary-color: #3399ff;
    --shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
}



/* === BLOCO EXTERNO === */
.info-section {
    background-color: var(--block-bg);
    border: 1px solid var(--border-color-light);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: var(--shadow);
    transition: background-color 0.3s, border-color 0.3s;
}

/* === GRID FLEXÍVEL === */
.info-grid {
    display: grid;
    gap: 18px;
}

.info-grid.columns-4 {
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.info-grid.columns-3 {
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.info-grid.columns-2 {
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

/* === ITENS === */
.info-item label {
    font-weight: 600;
    color: var(--label-color);
    margin-bottom: 6px;
    display: block;
    font-size: 0.95rem;
}

.form-control-modern {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background-color: var(--input-bg);
    color: var(--text-color);
    transition: border-color 0.3s, background-color 0.3s;
}

.form-control-modern:focus {
    border-color: var(--primary-color);
    outline: none;
}

/* === SHADOW === */
body {
    --shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}


/* === VARIÁVEIS MODO LIGHT === */
body.light-mode {
    --block-bg: #ffffff;
    --border-color: #ccc;
    --border-color-light: #e0e0e0;
    --input-bg: #f9f9f9;
    --text-color: #333;
    --label-color: #555;
    --primary-color: #007bff;
}

/* === VARIÁVEIS MODO DARK === */
body.dark-mode {
    --block-bg: #1e1f22;
    --border-color: #555;
    --border-color-light: #444;
    --input-bg: #2a2b2f;
    --text-color: #ddd;
    --label-color: #bbb;
    --primary-color: #3399ff;
}



/* === BLOCO PADRONIZADO === */
.enhanced-block {
    background-color: var(--block-bg);
    border: 1px solid var(--border-color-light);
    padding: 20px;
    border-radius: 12px;
    box-shadow: var(--shadow);
    margin-bottom: 20px;
}

/* === TÍTULOS DAS SEÇÕES (ANEXOS, TIMELINE...) === */
.section-title {
    font-size: 1.1rem;
    margin-bottom: 12px;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* === CONTROLE DE STATUS === */
.status-control {
    display: flex;
    gap: 10px;
    align-items: center;
}

.status-select {
    flex: 1;
    min-width: 220px;
}


/* === ATTACHMENTS LIST === */
.attachments-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}


/* === Timeline Section === */
.timeline-section {
    background-color: var(--block-bg);
    padding: 20px;
    border-radius: 10px;
    box-shadow: var(--shadow);
    margin-bottom: 20px;
}

/* Header da Seção com botão à direita */
.timeline-section .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
}

.timeline-section .section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-add-comment i {
    margin-right: 4px;
}

/* Conteúdo da timeline */
.timeline-content {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.status-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(0,123,255,0.2);
}


/* === Blocos de Filtro e Resultado === */
.search-block,
.result-block {
    background-color: var(--block-bg);
    padding: 20px;
    border-radius: 12px;
    box-shadow: var(--shadow);
    margin-bottom: 25px;
    margin-top: 25px;
    transition: background-color 0.3s, box-shadow 0.3s;
}

.search-block h3,
.result-block h5 {
    margin-bottom: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-block hr,
.result-block hr {
    margin: 20px 0;
}

/* === Inputs e Selects === */
.search-block .form-control,
.result-block .form-control {
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background-color: var(--input-bg);
    color: var(--text-color);
}

.search-block .form-control:focus,
.result-block .form-control:focus {
    border-color: var(--primary-color);
    outline: none;
}

/* === Botões === */
.search-block .btn,
.result-block .btn {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.search-block .btn i,
.result-block .btn i {
    margin-right: 4px;
}

/* === Modo Light === */
body.light-mode {
    --block-bg: #f9f9f9;
    --border-color: #ccc;
    --input-bg: #fff;
    --text-color: #333;
    --shadow: 0 2px 8px rgba(0,0,0,0.08);
    --primary-color: #007bff;
}

/* === Modo Dark === */
body.dark-mode {
    --block-bg: #1e1f22;
    --border-color: #444;
    --input-bg: #2a2b2f;
    --text-color: #ddd;
    --shadow: 0 2px 8px rgba(0,0,0,0.6);
    --primary-color: #3399ff;
}


    </style>