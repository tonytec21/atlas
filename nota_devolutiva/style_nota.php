<style>  
        hr:not([size]) {
            height: 0px;
        }

        .btn-close {  
            outline: none;  
            border: none;   
            background: none;  
            padding: 0;   
            font-size: 1.5rem;  
            cursor: pointer;   
            transition: transform 0.2s ease;  
            position: absolute;  
            right: 20px;  
            top: 15px;  
        }  

        .btn-close:hover {  
            transform: scale(2.10);  
        }  

        .btn-close:focus {  
            outline: none;  
        }  

        /* Modal de visualização */  
        #viewNotaModal .modal-dialog {  
            max-width: 80%;  
            margin: auto;  
            border-radius: 15px;  
            overflow: hidden;  
        }  

        #viewNotaModal .modal-content {  
            border: none;  
            border-radius: 15px;  
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);  
            transition: background-color 0.3s ease, box-shadow 0.3s ease;  
        }  

        #viewNotaModal .modal-header {  
            background-color: #ffffff; /* Light mode default */  
            border-bottom: none;  
            padding: 1.5rem;  
            position: relative;  
            text-align: center;  
            flex-direction: column;  
            align-items: center;  
        }  

        #viewNotaModal .modal-header h5 {  
            font-size: 1.75rem;  
            font-weight: 700;  
            color: #333; /* Light mode default */  
            /* margin-bottom: 20px;   */
        }  

        #viewNotaModal .modal-body {  
            /* padding: 2rem;   */
            background-color: #f8f9fa; /* Light mode default */  
            color: #333; /* Light mode default */  
        }  

        #viewNotaModal .modal-footer {  
            padding: 1.5rem;  
            background-color: #f8f9fa; /* Light mode default */  
            border-top: none;  
            display: flex;  
            flex-wrap: wrap;  
            justify-content: flex-end;  
            align-items: center;  
        }  

        /* Status Controls */  
        .status-controls {  
            display: flex;  
            align-items: center;  
            background-color: #f0f0f0;  
            border-radius: 30px;  
            padding: 5px 15px;  
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);  
            margin-top: 15px;  
            margin-bottom: 5px;  
            width: fit-content;  
            min-width: 300px;  
        }  

        /* Estilo para o status */  
        .status-badge {  
            display: inline-block;  
            padding: 5px 15px;  
            border-radius: 15px;  
            color: white;  
            font-weight: bold;  
            font-size: 0.85rem;  
            text-align: center;  
            min-width: 120px;  
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);  
        }  

        /* Button Styles */  
        #viewNotaModal .modal-footer .btn {  
            font-size: 1rem;  
            transition: background-color 0.2s ease, transform 0.2s ease;  
            border: none;  
        }  

        /* Cores para cada status */  
        .status-pendente {  
            background-color: #ffc107;  
        }  
        .status-exigencia-cumprida {  
            background-color: #28a745;  
        }  
        .status-exigencia-nao-cumprida {  
            background-color: #dc3545;  
        }  
        .status-prazo-expirado {  
            background-color: #fd7e14;  
        }  
        .status-em-analise {  
            background-color: #007bff;  
        }  
        .status-cancelada {  
            background-color: #6c757d;  
        }  
        .status-aguardando-documentacao {  
            background-color: #6f42c1;  
        }  

        /* Estilo para o select de status */  
        .select-status-wrapper {  
            position: relative;  
            margin-right: 10px;  
            width: 100%;  
            max-width: 300px;  
        }  

        #statusSelect {  
            width: 100%;  
            padding: 8px 30px 8px 15px;  
            border-radius: 20px;  
            border: 1px solid #ddd;  
            background-color: #fff;  
            font-size: 0.9rem;  
            transition: all 0.3s ease;  
            appearance: none;  
            -webkit-appearance: none;  
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='6'%3E%3Cpath d='M0 0l6 6 6-6z' fill='%23666'/%3E%3C/svg%3E");  
            background-repeat: no-repeat;  
            background-position: right 12px center;  
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);  
            cursor: pointer;  
            color: #fff;  
            font-weight: 600;  
        }  
        
        #statusSelect:focus {  
            border-color: #80bdff;  
            outline: 0;  
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);  
        }  
        
        /* Estilos para as opções da lista suspensa quando selecionadas */  
        #statusSelect option {  
            color: #000;  
            background-color: #fff;  
            padding: 10px;  
        }  
        
        /* Cores específicas para o select baseado no status */  
        #statusSelect.select-pendente {  
            background-color: #ffc107;  
            border-color: #e0a800;  
        }  
        
        #statusSelect.select-exigencia-cumprida {  
            background-color: #28a745;  
            border-color: #218838;  
        }  
        
        #statusSelect.select-exigencia-nao-cumprida {  
            background-color: #dc3545;  
            border-color: #c82333;  
        }  
        
        #statusSelect.select-prazo-expirado {  
            background-color: #fd7e14;  
            border-color: #e56a04;  
        }  
        
        #statusSelect.select-em-analise {  
            background-color: #007bff;  
            border-color: #0069d9;  
        }  
        
        #statusSelect.select-cancelada {  
            background-color: #6c757d;  
            border-color: #5a6268;  
        }  
        
        #statusSelect.select-aguardando-documentacao {  
            background-color: #6f42c1;  
            border-color: #613da8;  
        }  
        
        #btnUpdateStatus {  
            border-radius: 20px;  
            padding: 8px 16px;  
            font-size: 0.9rem;  
            background-color: #007bff;  
            color: white;  
            border: none;  
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);  
            transition: all 0.3s ease;  
            white-space: nowrap;  
        }  
        
        #btnUpdateStatus:hover {  
            background-color: #0069d9;  
            transform: translateY(-2px);  
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);  
        }  
        
        #btnUpdateStatus:active {  
            transform: translateY(0);  
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);  
        }  

        /* Estilos adicionais para a tabela */  
        .table th, .table td {  
            vertical-align: middle;  
        }  
        
        .btn-edit {  
            background-color: #ffc107;  
            color: #212529;  
        }  
        
        .btn-edit:hover {  
            background-color: #e0a800;  
            color: #212529;  
        }  
        
        /* Estilo para o conteúdo da nota */  
        .nota-content {  
            max-height: 60vh;  
            overflow-y: auto;  
            padding: 15px;  
            background-color: #fff;  
            border-radius: 8px;  
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);  
        }  

        .dark-mode .nota-content {  
            background-color: #2c2f33;    
        }  
        
        .nota-metadata {  
            margin-bottom: 20px;  
            padding-bottom: 15px;  
            border-bottom: 1px solid #dee2e6;  
        }  
        
        .nota-metadata p {  
            margin-bottom: 5px;  
        }  
        
        .nota-body {  
            font-size: 1.1rem;  
            line-height: 1.6;  
        }  
        
        .section-title {  
            font-weight: bold;  
            font-size: 1.2rem;  
            margin-top: 15px;  
            margin-bottom: 10px;  
            padding-bottom: 5px;  
            border-bottom: 1px solid #e0e0e0;  
        }  
        
        .nota-prazo-cumprimento {  
            margin-top: 15px;  
            padding-top: 15px;  
            border-top: 1px solid #dee2e6;  
        }  

        /* Dark Mode Styles */  
        body.dark-mode #viewNotaModal .modal-header {  
            background-color: #2c2f33;  
            color: #ffffff;  
        }  

        body.dark-mode #viewNotaModal .modal-header h5 {  
            color: #ffffff!important;  
        }  

        body.dark-mode #viewNotaModal .btn-close {  
            color: #a9a9a9;  
        }  

        body.dark-mode #viewNotaModal .btn-close:hover {  
            color: #ff4d4f; /* Hover color */  
        }  

        body.dark-mode #viewNotaModal .modal-body {  
            background-color: #23272a;  
            color: #ffffff;  
        }  

        body.dark-mode #viewNotaModal .modal-footer {  
            background-color: #2c2f33;  
        }  

        body.dark-mode .status-controls {  
            background-color: #383f45;  
        }  

        body.dark-mode #statusSelect option {  
            background-color: #2c2f33;  
            color: #fff;  
        }  

        /* Responsividade */  
        @media (max-width: 768px) {  
            .status-controls {  
                flex-direction: column;  
                padding: 10px;  
                gap: 10px;  
                margin: 10px auto;  
                width: 100%;  
            }  
            
            .select-status-wrapper {  
                width: 100%;  
                max-width: none;  
                margin-right: 0;  
            }  
            
            #btnUpdateStatus {  
                width: 100%;  
            }  
            
            #viewNotaModal .modal-dialog {  
                max-width: 95%;  
            }  
        }  


        /* CRIAR NOTA */
        .cke_notification_warning { display: none !important; }  
        
        .btn-success {  
            width: 75px;  
            height: 40px;  
            border-radius: 5px;  
        }  
        /* Estilos para o modal */  
        .modal * {  
            box-sizing: border-box !important;  
        }  
        
        .modal {  
            position: fixed !important;  
            top: 0 !important;  
            left: 0 !important;  
            width: 100% !important;  
            height: 100% !important;  
            background: rgba(0, 0, 0, 0.5) !important;  
            z-index: 1055 !important;  
            display: none !important;  
        }  
        
        .modal.show {  
            display: block !important;  
        }  
        
        .modal-dialog {  
            position: relative !important;  
            width: 85% !important;  
            margin: 2rem auto !important;  
            max-width: 1400px !important;  
        }  
        
        .modal-content {  
            background: #fff !important;  
            border-radius: 8px !important;  
            box-shadow: 0 0 20px rgba(0,0,0,0.15) !important;  
            display: flex !important;  
            flex-direction: column !important;  
            height: calc(100vh - 4rem) !important;  
        }  
        
        .modal-header {  
            padding: 1rem 1.5rem !important;  
            border-bottom: 1px solid #e9ecef !important;  
            display: flex !important;  
            align-items: center !important;  
            justify-content: space-between !important;  
        }  
        
        .modal-title {  
            font-size: 1.25rem !important;  
            font-weight: 600 !important;  
            margin: 0 !important;  
            display: flex !important;  
            align-items: center !important;  
            gap: 0.5rem !important;  
            color: #2c3e50 !important;  
        }  
        
        .close {  
            background: none !important;  
            border: none !important;  
            font-size: 1.5rem !important;  
            cursor: pointer !important;  
            padding: 0.5rem !important;  
            color: #6c757d !important;  
            transition: color 0.2s !important;  
        }  
        
        .close:hover {  
            color: #dc3545 !important;  
        }  
        
        .modal-body {  
            flex: 1 !important;  
            padding: 1.5rem !important;  
            overflow: auto !important;  
        }  
        
        .table {  
            width: 100% !important;  
            border-collapse: collapse !important;  
            margin: 0 !important;  
            white-space: nowrap !important;  
        }  
        
        .table thead th {  
            background: #f8f9fa !important;  
            padding: 1rem !important;  
            font-weight: 600 !important;  
            color: #2c3e50 !important;  
            text-align: left !important;  
            border-bottom: 2px solid #dee2e6 !important;  
        }  
        
        .col-numero { width: 8% !important; }  
        .col-data { width: 10% !important; }  
        .col-apresentante { width: 30% !important; }  
        .col-titulo { width: 35% !important; }  
        .col-acoes { width: 5% !important; text-align: center !important; }  
        
        .table td {  
            padding: 0.75rem 1rem !important;  
            border-bottom: 1px solid #e9ecef !important;  
            vertical-align: middle !important;  
        }  
        
        .cell-content {  
            max-width: 100% !important;  
            overflow: hidden !important;  
            text-overflow: ellipsis !important;  
            white-space: nowrap !important;  
        }  
        
        .action-buttons {  
            display: flex !important;  
            gap: 0.5rem !important;  
            justify-content: center !important;  
        }  
        
        .btn-action {  
            width: 32px !important;  
            height: 32px !important;  
            padding: 0 !important;  
            border: none !important;  
            border-radius: 4px !important;  
            display: flex !important;  
            align-items: center !important;  
            justify-content: center !important;  
            cursor: pointer !important;  
            transition: all 0.2s !important;  
        }  
        
        .btn-view {  
            background: #e3f2fd !important;  
            color: #0d6efd !important;  
        }  
        
        .btn-use {  
            background: #e8f5e9 !important;  
            color: #198754 !important;  
        }  
        
        .btn-action:hover {  
            transform: translateY(-1px) !important;  
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;  
        }  
        
        .btn-close {  
            outline: none !important;  
            border: none !important;   
            background: none !important;  
            padding: 0 !important;   
            font-size: 1.5rem !important;  
            cursor: pointer !important;   
            transition: transform 0.2s ease !important;  
        }  
        
        .btn-close:hover {  
            transform: scale(2.10) !important;  
        }  
        
        .btn-close:focus {  
            outline: none !important;  
        }  
    </style>  