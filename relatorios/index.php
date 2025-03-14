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
    <title>Atlas - Central de Relatórios</title>  
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">  
    <link href="../style/css/all.min.css" rel="stylesheet">  
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../style/css/style.css">  
    <link rel="icon" href="../style/img/favicon.png" type="image/png">  
    <script src="../script/jquery-3.6.0.min.js"></script>  
    <script src="../script/jquery-ui.min.js"></script>  
    <link rel="stylesheet" href="../style/css/jquery-ui.css">  
    <style>  
        .main-content {  
            background-color: #f8f9fa;  
            min-height: calc(100vh - 60px);  
            padding: 30px 0;  
        }  
        
        .page-header {  
            position: relative;  
            margin-bottom: 40px;  
            padding-bottom: 15px;  
            border-bottom: 1px solid #eaeaea;  
        }  
        
        .page-header:after {  
            content: '';  
            position: absolute;  
            left: 0;  
            bottom: -1px;  
            width: 80px;  
            height: 3px;  
            background: linear-gradient(90deg, #1762b8, #17a2b8);  
        }  
        
        .search-box {  
            position: relative;  
            margin-bottom: 30px;  
        }  
        
        .search-box input {  
            border-radius: 30px;  
            padding-left: 45px;  
            border: 1px solid #ddd;  
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);  
            transition: all 0.3s;  
        }  
        
        .search-box input:focus {  
            box-shadow: 0 3px 8px rgba(23, 98, 184, 0.15);  
            border-color: #1762b8;  
        }  
        
        .search-box i {  
            position: absolute;  
            left: 16px;  
            top: 13px;  
            color: #aaa;  
        }  
        
        #sortable-cards .card {  
            border: none;  
            border-radius: 12px;  
            overflow: hidden;  
            transition: all 0.3s ease;  
            margin-bottom: 25px;  
            height: 100%;  
        }  
        
        #sortable-cards .card:hover {  
            transform: translateY(-5px);  
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);  
        }  
        
        #sortable-cards .col-md-4 {  
            cursor: move;  
        }  
        
        .card-body {  
            padding: 25px;  
        }  
        
        .card-icon {  
            display: inline-flex;  
            align-items: center;  
            justify-content: center;  
            width: 70px;  
            height: 70px;  
            border-radius: 12px;  
            margin-bottom: 15px;  
            transition: all 0.3s;  
        }  
        
        .card-icon.primary {  
            background-color: rgba(23, 98, 184, 0.1);  
            color: #1762b8;  
        }  
        
        .card-icon.info {  
            background-color: rgba(23, 162, 184, 0.1);  
            color: #17a2b8;  
        }  
        
        .card-icon.danger {  
            background-color: rgba(255, 138, 128, 0.1);  
            color: #ff8a80;  
        }  
        
        .card-icon.secondary {  
            background-color: rgba(52, 73, 94, 0.1);  
            color: #34495e;  
        }  
        
        .card-icon.success {  
            background-color: rgba(40, 167, 69, 0.1);  
            color: #28a745;  
        }  
        
        .card-title {  
            font-size: 1.2rem;  
            font-weight: 600;  
            margin-bottom: 15px;  
            color: #444;  
        }  
        
        .card-text {  
            color: #777;  
            margin-bottom: 20px;  
            font-size: 0.95rem;  
        }  
        
        .card-category {  
            display: inline-block;  
            font-size: 0.75rem;  
            padding: 3px 10px;  
            border-radius: 20px;  
            margin-bottom: 15px;  
            font-weight: 500;  
        }  
        
        .category-financial {  
            background-color: rgba(255, 138, 128, 0.1);  
            color: #ff8a80;  
        }  
        
        .category-operational {  
            background-color: rgba(23, 162, 184, 0.1);  
            color: #17a2b8;  
        }  
        
        .category-management {  
            background-color: rgba(52, 73, 94, 0.1);  
            color: #34495e;  
        }  
        
        .btn-custom {  
            border-radius: 8px;  
            padding: 10px 20px;  
            font-weight: 500;  
            text-transform: none;  
            transition: all 0.3s;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            gap: 8px;  
        }  
        
        .btn-custom:hover {  
            transform: translateY(-2px);  
        }  
        
        .btn-primary {  
            background: linear-gradient(45deg, #1762b8, #1e88e5);  
            border: none;  
            box-shadow: 0 4px 6px rgba(23, 98, 184, 0.15);  
        }  
        
        .btn-info {  
            background: linear-gradient(45deg, #17a2b8, #00bcd4);  
            border: none;  
            box-shadow: 0 4px 6px rgba(23, 162, 184, 0.15);  
            color: white;  
        }  
        
        .btn-danger {  
            background: linear-gradient(45deg, #ff8a80, #ff5252);  
            border: none;  
            box-shadow: 0 4px 6px rgba(255, 138, 128, 0.15);  
        }  
        
        .btn-secondary {  
            background: linear-gradient(45deg, #34495e, #455a64);  
            border: none;  
            box-shadow: 0 4px 6px rgba(52, 73, 94, 0.15);  
        }  
        
        .ui-sortable-helper {  
            box-shadow: 0 15px 25px rgba(0,0,0,0.15);  
        }  
        
        .ui-state-highlight {  
            height: 320px;  
            background-color: #f8f9fa;  
            border: 2px dashed #ddd;  
            border-radius: 12px;  
        }  
        
        .badge-new {  
            position: absolute;  
            top: -8px;  
            right: -8px;  
            background: linear-gradient(45deg, #ff3d00, #ff8a80);  
            color: white;  
            font-size: 0.7rem;  
            padding: 5px 8px;  
            border-radius: 20px;  
            box-shadow: 0 3px 5px rgba(0,0,0,0.1);  
            z-index: 2;  
        }  
        
        @media (max-width: 768px) {  
            .page-header h2 {  
                font-size: 1.8rem;  
            }  
        }  
        
        /* Animações */  
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
        
        .animate-fade-in-up {  
            animation: fadeInUp 0.5s ease forwards;  
        }  
        
        /* Tema escuro e ajustes */  
        body.dark-mode .main-content {  
            background-color: #1e1e2d;  
        }  
        
        body.dark-mode .card {  
            background-color: #2c2c40;  
        }  
        
        body.dark-mode .card-title,  
        body.dark-mode .page-header h2 {  
            color: #e0e0e0;  
        }  
        
        body.dark-mode .card-text {  
            color: #aaa;  
        }  
        
        body.dark-mode .ui-state-highlight {  
            background-color: #32324a;  
            border: 2px dashed #444;  
        }  
        
        body.dark-mode .search-box input {  
            background-color: #2c2c40;  
            border-color: #444;  
            color: #e0e0e0;  
        }  
        
        body.dark-mode .page-header {  
            border-bottom: 1px solid #32324a;  
        }  
    </style>  
</head>  
<body class="light-mode">  
<?php include(__DIR__ . '/../menu.php'); ?>  

<div id="main" class="main-content">  
    <div class="container">  
        <div class="page-header">  
            <h2 class="text-center">Central de Relatórios</h2>  
        </div>  
        
        <div class="search-box">  
            <i class="fa fa-search"></i>  
            <input type="text" id="searchReports" class="form-control" placeholder="Buscar relatórios...">  
        </div>  
        
        <!-- Cards de Módulos -->  
        <div id="sortable-cards" class="row">  
            <!-- Relatório de Tarefas -->  
            <div class="col-md-4 mb-3 animate-fade-in-up" id="card-tarefas">  
                <div class="card shadow">  
                    <div class="card-body text-center">  
                        <span class="card-category category-operational">Operacional</span>  
                        <div class="card-icon secondary">  
                            <i class="fa fa-tasks fa-2x"></i>  
                        </div>  
                        <h5 class="card-title">Relatório de Tarefas</h5>  
                        <p class="card-text">Visualize e monitore todas as tarefas em andamento, concluídas e pendentes.</p>  
                        <a href="relatorio_tarefas.php" class="btn btn-custom btn-secondary w-100">  
                            <i class="fa fa-chart-bar"></i> Acessar Relatório  
                        </a>  
                    </div>  
                </div>  
            </div>  

            <!-- Relatório de O.S -->  
            <div class="col-md-4 mb-3 animate-fade-in-up" id="card-os" style="animation-delay: 0.1s;">  
                <div class="card shadow">  
                    <!-- <span class="badge-new">Novo</span>   -->
                    <div class="card-body text-center">  
                        <span class="card-category category-operational">Operacional</span>  
                        <div class="card-icon info">  
                            <i class="fas fa-file-invoice-dollar fa-2x"></i>  
                        </div>  
                        <h5 class="card-title">Relatório de O.S</h5>  
                        <p class="card-text">Controle completo de ordens de serviço com status, valores e funcionários.</p>  
                        <a href="relatorio_os.php" class="btn btn-custom btn-info w-100">  
                            <i class="fa fa-file-text"></i> Acessar Relatório  
                        </a>  
                    </div>  
                </div>  
            </div>  

            <!-- Relatório de Contas a Pagar -->  
            <div class="col-md-4 mb-3 animate-fade-in-up" id="card-contas" style="animation-delay: 0.2s;">  
                <div class="card shadow">  
                    <div class="card-body text-center">  
                        <span class="card-category category-financial">Financeiro</span>  
                        <div class="card-icon danger">  
                            <i class="fa fa-credit-card fa-2x"></i>  
                        </div>  
                        <h5 class="card-title">Relatório de Contas a Pagar</h5>  
                        <p class="card-text">Gerencie todas as contas e despesas pendentes com controle de vencimentos.</p>  
                        <a href="#" class="btn btn-custom btn-danger w-100">  
                            <i class="fa fa-money"></i> Acessar Relatório  
                        </a>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  
</div>  

<script src="../script/bootstrap.min.js"></script>  
<script src="../script/jquery.mask.min.js"></script>  
<script>  
    $(document).ready(function () {  
        // Inicializa o sortable para os cards  
        $("#sortable-cards").sortable({  
            placeholder: "ui-state-highlight",   
            helper: 'clone',   
            containment: 'parent',  
            update: function (event, ui) {  
                saveCardOrder();  
            }  
        });  

        // Função para salvar a ordem dos cards no arquivo JSON  
        function saveCardOrder() {  
            let order = [];  
            $("#sortable-cards .col-md-4").each(function () {  
                order.push($(this).attr('id'));  
            });  

            // Faz uma requisição AJAX para salvar a ordem no arquivo JSON  
            $.ajax({  
                url: 'save_order.php',  
                type: 'POST',  
                data: { order: order },  
                success: function (response) {  
                    console.log('Ordem salva com sucesso!');  
                },  
                error: function (xhr, status, error) {  
                    console.error('Erro ao salvar a ordem:', error);  
                }  
            });  
        }  

        // Carrega a ordem dos cards do arquivo JSON  
        function loadCardOrder() {  
            $.ajax({  
                url: 'load_order.php',  
                type: 'GET',  
                dataType: 'json',  
                success: function (data) {  
                    if (data && data.order && Array.isArray(data.order)) {  
                        $.each(data.order, function (index, cardId) {  
                            $("#" + cardId).appendTo("#sortable-cards");  
                        });  
                    }  
                },  
                error: function (xhr, status, error) {  
                    console.error('Erro ao carregar a ordem:', error);  
                }  
            });  
        }  

        // Busca de relatórios  
        $('#searchReports').on('keyup', function() {  
            const value = $(this).val().toLowerCase();  
            $("#sortable-cards .col-md-4").filter(function() {  
                const matches = $(this).find('.card-title').text().toLowerCase().indexOf(value) > -1;  
                $(this).toggle(matches);  
            });  
        });  

        // Carrega a ordem ao carregar a página  
        loadCardOrder();  
        
        // Adiciona efeito de hover nos cards  
        $('#sortable-cards .card').hover(  
            function() {  
                $(this).find('.card-icon').css('transform', 'scale(1.1)');  
            },  
            function() {  
                $(this).find('.card-icon').css('transform', 'scale(1)');  
            }  
        );  
    });  
</script>  

<br><br><br>  
<?php include(__DIR__ . '/../rodape.php'); ?>  
</body>  
</html>