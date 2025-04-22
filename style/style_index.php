<style>

/* Estilos base do Modal */  
.modal-content {  
    border: none;  
    border-radius: 15px;  
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);  
}  

.modal-header {  
    border-radius: 15px 15px 0 0;  
    padding: 1.5rem;  
    border-bottom: 1px solid #dee2e6;  
    background-color: #f8f9fa;  
}  

.modal-title {  
    letter-spacing: 0.5px;  
    font-weight: 600;  
    color: #333;  
}  

.modal-subtitle {  
    font-size: 0.9rem;  
    color: #6c757d;  
    margin-top: 0.25rem;  
}  

/* Tabelas */  
.modal-body table {  
    width: 100%;  
    margin-bottom: 1rem;  
    background-color: #fff;  
    border-collapse: collapse;  
}  

.modal-body table th,  
.modal-body table td {  
    padding: 0.75rem;  
    border: 1px solid #dee2e6;  
}  

.modal-body table th {  
    background-color: #f8f9fa;  
    font-weight: 600;  
}  

.modal-body table tr:hover {  
    background-color: #f8f9fa;  
}  

/* Badges e Status */  
.badge {  
    padding: 0.5em 0.8em;  
    font-weight: 500;  
    border-radius: 4px;  
}  

.status-em-espera {  
    background-color: #ffc107;  
    color: #000;  
}  

.status-prestes-vencer {  
    background-color: #dc3545;  
    color: #fff;  
}  

/* Botão de Visualizar */  
.btn-visualizar {  
    background-color: #0099cc;  
    color: white;  
    border: none;  
    padding: 0.375rem 0.75rem;  
    border-radius: 4px;  
    cursor: pointer;  
    transition: background-color 0.2s;  
}  

.btn-visualizar:hover {  
    background-color: #007bff;  
}  

/* Botão Fechar */  
.modal-footer .btn-secondary {  
    background-color: #f8f9fa;  
    border: 1px solid #dee2e6;  
    color: #6c757d;  
    font-weight: 500;  
    padding: 0.5rem 1.5rem;  
    border-radius: 6px;  
    transition: all 0.2s;  
}  

.modal-footer .btn-secondary:hover {  
    background-color: #e9ecef;  
    color: #495057;  
}  

/* Seções de Tarefas */  
.task-section {  
    background: #fff;  
    border-radius: 10px;  
    padding: 1rem;  
    margin-bottom: 1.5rem;  
    border: 1px solid #dee2e6;  
}  

.section-header {  
    padding-bottom: 0.5rem;  
    margin-bottom: 1rem;  
    border-bottom: 2px solid #dee2e6;  
}  

.section-title {  
    font-size: 1.1rem;  
    font-weight: 600;  
}  

/* Modo Dark */  
.dark-mode .modal-content {  
    background-color: #1e2124;  
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);  
}  

.dark-mode .modal-header {  
    background-color: #2c2f33!important;  
    border-color: #40444b;  
}  

.dark-mode .modal-footer {  
    background-color: #2c2f33!important;  
    border-color: #40444b;  
}  

.dark-mode .modal-title {  
    color: #fff;  
}  

.dark-mode .modal-subtitle {  
    color: #a0a0a0;  
}  

.dark-mode .task-section {  
    background-color: #2c2f33;  
    border-color: #40444b;  
}  

.dark-mode .section-header {  
    border-bottom-color: #40444b;  
}  

.dark-mode .modal-body table {  
    background-color: #2c2f33;  
    color: #fff;  
}  

.dark-mode .modal-body table th {  
    background-color: #40444b;  
    color: #fff;  
    border-color: #40444b;  
}  

.dark-mode .modal-body table td {  
    border-color: #40444b;  
}  

.dark-mode .modal-body table tr:hover {  
    background-color: #34373c;  
}  

.dark-mode .modal-footer .btn-secondary {  
    background-color: #40444b;  
    border-color: #40444b;  
    color: #fff;  
}  

.dark-mode .modal-footer .btn-secondary:hover {  
    background-color: #4a4f57;  
}  

/* Status no modo dark */  
.dark-mode .status-em-espera {  
    background-color: #faa61a;  
    color: #000;  
}  

.dark-mode .status-prestes-vencer {  
    background-color: #f04747;  
    color: #fff;  
}  

.dark-mode .btn-visualizar {  
    background-color: #006687;  
}  

.dark-mode .btn-visualizar:hover {  
    background-color: #0088b3;  
}  

/* Scrollbar Personalizada */  
.modal-body::-webkit-scrollbar {  
    width: 8px;  
}  

.modal-body::-webkit-scrollbar-track {  
    background: #f1f1f1;  
    border-radius: 4px;  
}  

.modal-body::-webkit-scrollbar-thumb {  
    background: #888;  
    border-radius: 4px;  
}  

.modal-body::-webkit-scrollbar-thumb:hover {  
    background: #555;  
}  

/* Scrollbar no modo dark */  
.dark-mode .modal-body::-webkit-scrollbar-track {  
    background: #2c2f33;  
}  

.dark-mode .modal-body::-webkit-scrollbar-thumb {  
    background: #40444b;  
}  

.dark-mode .modal-body::-webkit-scrollbar-thumb:hover {  
    background: #4a4f57;  
}  

/* Responsividade */  
@media (max-width: 768px) {  
    .modal-dialog {  
        margin: 0.5rem;  
    }  
    
    .modal-header {  
        padding: 1rem;  
    }  
    
    .modal-body {  
        padding: 1rem;  
    }  
    
    .section-title {  
        font-size: 1rem;  
    }  
    
    .modal-body table {  
        font-size: 0.9rem;  
    }  
}  

/* Cores específicas mantidas em ambos os modos */  
.text-success {  
    color: #28a745 !important;  
}  

.text-danger {  
    color: #dc3545 !important;  
}  

/* Animações */  
.modal.fade .modal-dialog {  
    transform: scale(0.95);  
    transition: transform 0.2s ease-out;  
}  

.modal.show .modal-dialog {  
    transform: scale(1);  
}  

/* Botão close */  
.btn-close {  
    opacity: 0.7;  
    transition: all 0.2s;  
}  

.btn-close:hover {  
    opacity: 1;  
    transform: rotate(90deg);  
}  

.dark-mode .btn-close {  
    filter: invert(1) grayscale(100%) brightness(200%);  
}

/* Cursor para indicar que os elementos são arrastáveis */
#sortable-buttons .col-md-4 {
            cursor: move;
        }

        .page-title {  
            font-size: 2.0rem;  
            font-weight: 700;  
            color: #34495e;  
            margin-bottom: 2rem;  
            text-align: center;  
            text-transform: uppercase;  
            letter-spacing: 1px;  
        }  

        body.dark-mode .page-title {
            font-size: 2.0rem;  
            font-weight: 700;  
            color: #fff;  
            margin-bottom: 2rem;  
            text-align: center;  
            text-transform: uppercase;  
            letter-spacing: 1px;  
            
        }
    
        .btn {  
            border-radius: 10px!important;  
        }

        .btn-warning {
            color: #fff!important;
        }

        .text-tutoriais {
            color: #1762b8;
        }

        .btn-tutoriais {
            background: #1762b8;
            color: #fff;
        }

        .btn-tutoriais:hover {
            background: #0c52a3;
            color: #fff;
        }

        .btn-4 {
            background: #34495e;
            color: #fff;
        }
        .btn-4:hover {
            background: #2c3e50;
            color: #fff;
        }

        .text-4 {
            color: #34495e;
        }

        body.dark-mode .btn-4 {
            background: #54718e;
            color: #fff;
        }
        body.dark-mode .btn-4:hover {
            background: #435c74;
            color: #fff;
        }

        body.dark-mode .text-4 {
            color: #54718e;
        }

        .btn-5 {
            background: #ff8a80;
            color: #fff;
        }
        .btn-5:hover {
            background: #e3786f;
            color: #fff;
        }
        
        .text-5 {
            color: #ff8a80;
        }
        
        .btn-6 {
            background: #427b8e;
            color: #fff;
        }
        .btn-6:hover {
            background: #366879;
            color: #fff;
        }
        
        .text-6 {
            color: #427b8e;
        }
        
        .btn-indexador {
            background: #FF7043;
            color: #fff;
        }
        
        .btn-indexador:hover {
            background: #D64E27;
            color: #fff;
        }

        .text-indexador {
            color: #FF7043;
        }

        .btn-anotacoes {
            background: #A7D676;
            color: #fff;
        }

        .btn-anotacoes:hover {
            background: #7CB342;
            color: #fff;
        }
        
        .text-anotacoes {
            color: #A7D676;
        }

        .btn-reurb {
            background: #FFC8A2;
            color: #fff;
        }

        .btn-reurb:hover {
            background: #f7b283;
            color: #fff;
        }
        
        .text-reurb {
            color: #FFC8A2;
        }

        .btn-relatorios {
            background: #4A90E2;
            color: #fff;
        }

        .btn-relatorios:hover {
            background: #357ABD;
            color: #fff;
        }
        
        .text-relatorios {
            color: #4A90E2;
        }
        
        
        .btn-info2{
            background-color: #17a2b8;
            color: white;
            margin-bottom: 3px!important;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            border: none;
        }
        .btn-info2:hover {
            color: #fff;
        }

        /* Estilos exclusivos para o modal com a classe modal-alerta */
        .modal-alerta .modal-content {
            border: 2px solid #dc3545; 
            background-color: #f8d7da; 
            color: #721c24; 
        }

        .modal-alerta .modal-header {
            background-color: #dc3545; 
            color: white; 
        }

        .modal-alerta .modal-body {
            font-weight: bold; 
        }

        .modal-alerta .modal-footer {
            background-color: #f5c6cb;
        }

        
        .modal-alerta .btn-close {
            background-color: white;
            border: 1px solid #dc3545;
        }

        .modal-alerta .btn-close:hover {
            background-color: #dc3545;
            color: white;
        }

        .modal-alerta .modal-footer .btn-secondary {
            background-color: #dc3545; 
            border: none;
        }

        .modal-alerta .modal-footer .btn-secondary:hover {
            background-color: #c82333; 
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

        .modal-body ul {
            list-style-type: none;
            padding-left: 0;
        }

        .modal-body li {
            padding-left: 20px!important;
            padding: 10px 0; 
            border-bottom: 1px solid #ddd;
        }

        .modal-body h5 {
            /* margin-top: 20px;
            margin-bottom: 10px; */
            font-weight: bold;
        }

        .modal-dialog {
            max-width: 700px;
        }

        /* Prioridades */
        .priority-medium {
            background-color: #fff9c4 !important; 
            padding: 10px;
        }

        .priority-high {
            background-color: #ffe082 !important;
            padding: 10px;
        }

        .priority-critical {
            background-color: #ff8a80 !important;
            padding: 10px;
        }

        .row-quase-vencida {
            background-color: #ffebcc!important;
            padding: 10px;
        }

        .row-vencida {
            background-color: #ffcccc!important;
            padding: 10px;
        }

        body.dark-mode .priority-medium {
            background-color: #fff9c4 !important;
            color: #000!important;
        }

        body.dark-mode .priority-high {
            background-color: #ffe082 !important;
            color: #000!important;
        }

        body.dark-mode .priority-critical {
            background-color: #ff8a80 !important;
        }

        /* Modo escuro - Quase vencida e vencida */
        body.dark-mode .row-quase-vencida {
            background-color: #ffebcc!important; 
            color: #000!important;
        }

        body.dark-mode .row-vencida {
            background-color: #ffcccc!important; 
            color: #000!important;
        }

        /* Status das tarefas */
        .status-iniciada {
            background-color: #007bff;
            color: #fff;
        }

        .status-em-espera {
            background-color: #ffa500; 
            color: #fff;
        }

        .status-em-andamento {
            background-color: #0056b3;
            color: #fff;
        }

        .status-concluida {
            background-color: #28a745;
            color: #fff;
        }

        .status-cancelada {
            background-color: #dc3545; 
            color: #fff;
        }

        .status-pendente {
            background-color: gray;
            color: #fff;
        }

        .status-aguardando-retirada {
            background-color: #6c757d; /* Cinza escuro */
            color: #fff;
        }

        .status-aguardando-pagamento {
            background-color: #ffc107; /* Amarelo */
            color: #000;
        }

        .status-prazo-de-edital {
            background-color: #17a2b8; /* Azul claro */
            color: #fff;
        }

        .status-exigencia-cumprida {
            background-color: #20c997; /* Verde água */
            color: #fff;
        }

        .status-finalizado-sem-pratica-do-ato {
            background-color: #343a40; /* Preto acinzentado */
            color: #fff;
        }


        .chart-container {
            position: relative;
            height: 240px;
        }
        .chart-container.full-height {
            height: 360px;
            margin-top: 30px;
        }
        @media (max-width: 768px) {
            .chart-container {
                height: 200px;
                margin-top: 20px;
            }
            .chart-container.full-height {
                height: 300px;
                margin-top: 20px;
                margin-bottom: 20px;
            }
            .card-body {
                padding: 1rem;
            }
            .card {
                margin-bottom: 1rem;
            }
        }
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #343a40;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }
        .notification .close-btn {
            cursor: pointer;
            float: right;
            margin-left: 10px;
        }
        .w-100 {
            margin-bottom: 5px;
        }
        #sortable-cards .card {  
            border: none;  
            border-radius: 15px;  
            transition: all var(--transition-speed) ease;  
            cursor: grab;  
            background: var(--primary-bg);  
            box-shadow: var(--card-shadow);  
            overflow: hidden;  
            height: 100%;  
        }  

        #sortable-cards .card:hover {  
            transform: var(--card-hover-transform);  
            box-shadow: 0 12px 20px rgba(0,0,0,0.15);  
        }  

        .btn-devolutivas {
            background-color: #9c27b0;
            color: white;
        }
        .btn-devolutivas:hover {
            background-color:rgb(115, 26, 131);
            color: white;
        }
        .text-devolutivas {
            color: #9c27b0;
        }

    </style>