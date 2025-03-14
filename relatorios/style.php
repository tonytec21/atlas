<style>  
        /* Configurações Gerais */  
        body {  
            transition: background-color 0.3s ease, color 0.3s ease;  
        }  
        
        body.dark-mode {  
            background-color: #121212;  
            color: #e0e0e0;  
        }  

        /* Card Styles */  
        .card-dashboard {  
            border-radius: 15px;  
            margin-bottom: 20px;  
            border: none;  
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);  
            overflow: hidden;  
            transition: all 0.3s ease;  
            position: relative;  
            height: 160px; /* Altura fixa para consistência */  
        }  
        .card-dashboard:hover {  
            transform: translateY(-5px);  
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);  
        }  
        
        body.dark-mode .card-dashboard {  
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.3);  
        }  
        
        body.dark-mode .card-dashboard:hover {  
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);  
        }  
        
        .card-body {  
            padding: 20px;  
            height: 100%;  
            display: flex;  
            flex-direction: column;  
            justify-content: space-between;  
        }  
        .card-title {  
            font-size: 1.1rem;  
            color: rgba(255, 255, 255, 0.9);  
            margin-bottom: 5px;  
        }  
        .card-value {  
            font-size: 2rem;  
            font-weight: 700;  
            color: #fff;  
        }  
        .card-icon {  
            position: absolute;  
            bottom: 15px;  
            right: 15px;  
            font-size: 3rem;  
            opacity: 0.2;  
        }  

        /* Card Colors */  
        .bg-blue {  
            background: linear-gradient(135deg, #1976d2, #64b5f6);  
        }  
        .bg-green {  
            background: linear-gradient(135deg, #43a047, #81c784);  
        }  
        .bg-orange {  
            background: linear-gradient(135deg, #ff9800, #ffb74d);  
        }  
        .bg-red {  
            background: linear-gradient(135deg, #e53935, #ef5350);  
        }  
        .bg-purple {  
            background: linear-gradient(135deg, #5e35b1, #9575cd);  
        }  
        .bg-teal {  
            background: linear-gradient(135deg, #00897b, #4db6ac);  
        }  
        .bg-pink {  
            background: linear-gradient(135deg, #d81b60, #f06292);  
        }  

        /* Dark Mode Card Colors - ajustes sutis para melhor contraste */  
        body.dark-mode .bg-blue {  
            background: linear-gradient(135deg, #155fa7, #4a8ac5);  
        }  
        body.dark-mode .bg-green {  
            background: linear-gradient(135deg, #357a39, #6ca970);  
        }  
        body.dark-mode .bg-orange {  
            background: linear-gradient(135deg, #cc7a00, #d49640);  
        }  
        body.dark-mode .bg-red {  
            background: linear-gradient(135deg, #b82e2a, #c44440);  
        }  
        body.dark-mode .bg-purple {  
            background: linear-gradient(135deg, #4b2a8e, #7956b0);  
        }  
        body.dark-mode .bg-teal {  
            background: linear-gradient(135deg, #006d62, #399389);  
        }  
        body.dark-mode .bg-pink {  
            background: linear-gradient(135deg, #ab174d, #d34d7c);  
        }  

        /* Filter Section */  
        .filter-container {  
            background-color: #fff;  
            border-radius: 15px;  
            padding: 20px;  
            margin-bottom: 25px;  
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);  
        }  
        
        body.dark-mode .filter-container {  
            background-color: #1e1e1e;  
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);  
        }  
        
        .filter-title {  
            font-weight: 600;  
            margin-bottom: 15px;  
            color: #333;  
        }  
        
        body.dark-mode .filter-title {  
            color: #e0e0e0;  
        }  
        
        .filter-label {  
            display: flex;  
            align-items: center;  
            gap: 5px;  
        }  
        
        body.dark-mode .filter-label {  
            color: #cccccc;  
        }  
        
        .filter-container .btn {  
            padding: 8px 16px;  
            border-radius: 8px;  
            transition: all 0.2s;  
        }  
        .filter-container .btn:hover {  
            transform: translateY(-2px);  
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);  
        }  
        
        body.dark-mode .filter-container .btn:hover {  
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);  
        }  
        
        body.dark-mode .filter-container .btn-outline-secondary {  
            color: #b0b0b0;  
            border-color: #606060;  
        }  
        
        body.dark-mode .filter-container .btn-outline-secondary:hover {  
            background-color: #383838;  
            color: #e0e0e0;  
        }  
        
        body.dark-mode .filter-container .btn-primary {  
            background-color: #155fa7;  
            border-color: #124e89;  
        }  
        
        body.dark-mode .filter-container .btn-primary:hover {  
            background-color: #1976d2;  
        }  

        /* Table Styles */  
        .dataTables_wrapper {  
            border-radius: 15px;  
            overflow: hidden;  
            margin-bottom: 25px;  
            background-color: #fff;  
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);  
        }  
        
        body.dark-mode .dataTables_wrapper {  
            background-color: #1e1e1e;  
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);  
        }  
        
        table.dataTable thead th {  
            background-color: #f8f9fa;  
            padding: 15px 10px;  
            font-weight: 600;  
        }  
        
        body.dark-mode table.dataTable thead th {  
            background-color: #2a2a2a;  
            color: #e0e0e0;  
            border-color: #444;  
        }  
        
        body.dark-mode table.dataTable tbody tr {  
            background-color: #1e1e1e;  
            color: #d0d0d0;  
        }  
        
        body.dark-mode table.dataTable tbody tr:hover {  
            background-color: #252525;  
        }  
        
        body.dark-mode table.dataTable tbody td {  
            border-color: #444;  
        }  
        
        body.dark-mode .dataTables_info,  
        body.dark-mode .dataTables_length,  
        body.dark-mode .dataTables_filter label {  
            color: #b0b0b0;  
        }  
        
        body.dark-mode .dataTables_length select,  
        body.dark-mode .dataTables_filter input {  
            background-color: #2a2a2a;  
            color: #e0e0e0;  
            border-color: #444;  
        }  
        
        body.dark-mode .paginate_button {  
            background-color: #2a2a2a !important;  
            color: #e0e0e0 !important;  
            border-color: #444 !important;  
        }  
        
        body.dark-mode .paginate_button.current {  
            background-color: #155fa7 !important;  
            color: #ffffff !important;  
        }  
        
        body.dark-mode .paginate_button:hover {  
            background-color: #383838 !important;  
            color: #ffffff !important;  
        }  

        /* Status Badges */  
        .badge-status {  
            padding: 6px 12px;  
            border-radius: 20px;  
            font-weight: 500;  
            font-size: 0.85rem;  
            color: #fff;
        }  

        /* Charts Tabs */  
        .chart-tabs-container {  
            margin-bottom: 25px;  
            background-color: #fff;  
            border-radius: 15px;  
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);  
            overflow: hidden;  
        }  
        
        body.dark-mode .chart-tabs-container {  
            background-color: #1e1e1e;  
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);  
        }  
        
        .nav-tabs {  
            border-bottom: 1px solid #dee2e6;  
            background-color: #f8f9fa;  
            padding: 10px 10px 0;  
            border-top-left-radius: 15px;  
            border-top-right-radius: 15px;  
        }  
        
        body.dark-mode .nav-tabs {  
            border-bottom-color: #444;  
            background-color: #2a2a2a;  
        }  
        
        .nav-tabs .nav-link {  
            border-radius: 8px 8px 0 0;  
            padding: 10px 20px;  
            font-weight: 500;  
            color: #495057;  
            border: 1px solid transparent;  
            transition: all 0.2s ease;  
        }  
        
        body.dark-mode .nav-tabs .nav-link {  
            color: #b0b0b0;  
        }  
        
        .nav-tabs .nav-link:hover {  
            border-color: #e9ecef #e9ecef #dee2e6;  
            background-color: #f1f3f5;  
        }  
        
        body.dark-mode .nav-tabs .nav-link:hover {  
            border-color: #444 #444 #444;  
            background-color: #383838;  
        }  
        
        .nav-tabs .nav-link.active {  
            color: #1976d2;  
            background-color: #fff;  
            border-color: #dee2e6 #dee2e6 #fff;  
        }  
        
        body.dark-mode .nav-tabs .nav-link.active {  
            color: #4a9bea;  
            background-color: #1e1e1e;  
            border-color: #444 #444 #1e1e1e;  
        }  
        
        .nav-tabs .nav-link i {  
            margin-right: 5px;  
        }  
        
        /* Charts */  
        .chart-container {  
            position: relative;  
            height: 350px;  
            margin-bottom: 25px;  
            background-color: #fff;  
            border-radius: 15px;  
            padding: 20px;  
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.03);  
        }  
        
        body.dark-mode .chart-container {  
            background-color: #1e1e1e;  
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);  
        }  
        
        .chart-title {  
            font-weight: 600;  
            margin-bottom: 15px;  
            color: #333;  
            text-align: center;  
        }  
        
        body.dark-mode .chart-title {  
            color: #e0e0e0;  
        }  
        
        /* Ajustes para gráficos no modo escuro */  
        body.dark-mode .chart-container canvas {  
            filter: brightness(0.9);  
        }  

        /* Loading overlay */  
        #loadingOverlay {  
            position: fixed;  
            top: 0;  
            left: 0;  
            width: 100%;  
            height: 100%;  
            background-color: rgba(255, 255, 255, 0.8);  
            display: flex;  
            justify-content: center;  
            align-items: center;  
            z-index: 9999;  
        }  
        
        body.dark-mode #loadingOverlay {  
            background-color: rgba(18, 18, 18, 0.8);  
        }  
        
        .spinner {  
            width: 40px;  
            height: 40px;  
            border: 4px solid #f3f3f3;  
            border-top: 4px solid #3498db;  
            border-radius: 50%;  
            animation: spin 1s linear infinite;  
        }  
        
        body.dark-mode .spinner {  
            border: 4px solid #2a2a2a;  
            border-top: 4px solid #3498db;  
        }  
        
        @keyframes spin {  
            0% { transform: rotate(0deg); }  
            100% { transform: rotate(360deg); }  
        }  

        /* Date Range Picker */  
        .daterangepicker {  
            font-family: inherit;  
            border-radius: 10px;  
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);  
        }  
        
        body.dark-mode .daterangepicker {  
            background-color: #2a2a2a;  
            color: #e0e0e0;  
            border-color: #444;  
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);  
        }  
        
        body.dark-mode .daterangepicker .calendar-table {  
            background-color: #2a2a2a;  
            border-color: #444;  
        }  
        
        body.dark-mode .daterangepicker td.available:hover,   
        body.dark-mode .daterangepicker th.available:hover {  
            background-color: #383838;  
            color: #ffffff;  
        }  
        
        body.dark-mode .daterangepicker td.active,   
        body.dark-mode .daterangepicker td.active:hover {  
            background-color: #155fa7;  
            color: #ffffff;  
        }  
        
        body.dark-mode .daterangepicker .drp-buttons {  
            border-top-color: #444;  
        }  
        
        body.dark-mode .daterangepicker .drp-selected {  
            color: #b0b0b0;  
        }  
        
        body.dark-mode .daterangepicker .btn {  
            background-color: #155fa7;  
            border-color: #124e89;  
            color: #ffffff;  
        }  
        
        .daterangepicker .ranges li.active {  
            background-color: #1976d2;  
        }  
        
        body.dark-mode .daterangepicker .ranges li {  
            background-color: #2a2a2a;  
            color: #e0e0e0;  
        }  
        
        body.dark-mode .daterangepicker .ranges li:hover {  
            background-color: #383838;  
        }  
        
        body.dark-mode .daterangepicker .ranges li.active {  
            background-color: #155fa7;  
            color: #ffffff;  
        }  
        
        /* Select, Input e outros elementos de formulário para dark mode */  
        body.dark-mode select,  
        body.dark-mode input[type="text"],  
        body.dark-mode input[type="search"],  
        body.dark-mode input[type="number"],  
        body.dark-mode input[type="email"],  
        body.dark-mode textarea {  
            background-color: #2a2a2a;  
            color: #e0e0e0;  
            border-color: #444;  
        }  
        
        body.dark-mode select:focus,  
        body.dark-mode input:focus,  
        body.dark-mode textarea:focus {  
            border-color: #155fa7;  
            box-shadow: 0 0 0 0.2rem rgba(21, 95, 167, 0.25);  
        }  

        /* Responsive adjustments */  
        @media (max-width: 992px) {  
            .card-value {  
                font-size: 1.8rem;  
            }  
            .chart-container {  
                height: 300px;  
            }  
        }  
        @media (max-width: 768px) {  
            .filter-container {  
                padding: 15px;  
            }  
            .chart-container {  
                height: 250px;  
            }  
        }  

        .dataTables_length {  
            margin-top: 0 !important;  
            margin-bottom: 8px !important;  
            padding-top: 0 !important;  
        }  
        .dt-buttons {  
            margin-bottom: 3px !important;  
        }  
        .dataTables_filter {  
            margin-bottom: 3px !important;  
        }  
        
        /* Botões DataTables no dark mode */  
        body.dark-mode .dt-buttons .dt-button {  
            background-color: #2a2a2a;  
            border-color: #444;  
            color: #e0e0e0;  
        }  
        
        body.dark-mode .dt-buttons .dt-button:hover {  
            background-color: #383838;  
        }  
        
        /* Botão de alternância de tema */  
        .theme-toggle {  
            background-color: transparent;  
            border: none;  
            color: #333;  
            font-size: 1.2rem;  
            cursor: pointer;  
            transition: color 0.3s ease;  
        }  
        
        body.dark-mode .theme-toggle {  
            color: #e0e0e0;  
        }  

        /* Dark Mode para page-link */  
        body.dark-mode .page-link {  
            color: #4a9bea;  
            background-color: #2a2a2a;  
            border: 1px solid #444;  
        }  

        body.dark-mode .page-link:hover {  
            color: #ffffff;  
            background-color: #383838;  
            border-color: #444;  
        }  

        body.dark-mode .page-link:focus {  
            z-index: 3;  
            outline: 0;  
            box-shadow: 0 0 0 0.2rem rgba(21, 95, 167, 0.25);  
        }  

        body.dark-mode .page-item.active .page-link {  
            z-index: 3;  
            color: #fff;  
            background-color: #155fa7;  
            border-color: #124e89;  
        }  

        body.dark-mode .page-item.disabled .page-link {  
            color: #6c757d;  
            pointer-events: none;  
            cursor: auto;  
            background-color: #222222;  
            border-color: #444;  
        }  
    </style>  