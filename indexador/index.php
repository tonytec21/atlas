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
    <title>Atlas - Central de Acesso - Indexador</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link href="../style/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="../style/css/jquery-ui.css">
    <style>

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

        #sortable-buttons .col-md-4 {
            cursor: move;
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
            background: #8e427c;
            color: #fff;
        }
        
        .btn-indexador:hover {
            background: #783768;
            color: #fff;
        }
        
        .text-indexador {
            color: #8e427c;
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
        .w-100 {
            margin-bottom: 5px;
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
        .btn {  
            border-radius: 10px!important;  
        }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container mt-4">
        <h2 class="page-title">Central de Acesso - Indexador</h2>

        <!-- Cards de Módulos -->
        <div id="sortable-cards" class="row">
            <!-- Nascimento -->
            <div class="col-md-4 mb-3" id="card-pessoas">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-baby-carriage fa-3x text-anotacoes" aria-hidden="true"></i>
                        <h5 class="card-title">Nascimento</h5>
                        <a href="nascimento/index.php" class="btn btn-anotacoes w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Casamento -->
            <div class="col-md-4 mb-3" id="card-imoveis">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-heart-circle-check fa-3x text-5" aria-hidden="true"></i>
                        <h5 class="card-title">Casamento</h5>
                        <a href="index.php" class="btn btn-5 w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Óbito -->
            <div class="col-md-4 mb-3" id="card-processoadm">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-cross fa-3x text-secondary" aria-hidden="true"></i>
                        <h5 class="card-title">Óbito</h5>
                        <a href="obito/index.php" class="btn btn-secondary w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Notas -->
            <div class="col-md-4 mb-3" id="card-exportar">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-file-alt fa-3x text-reurb mb-2"></i>
                        <h5 class="card-title">Notas</h5>
                        <a href="index.php" class="btn btn-reurb w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Imóveis -->
            <div class="col-md-4 mb-3" id="card-oficios">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-home fa-3x text-info"></i>
                        <h5 class="card-title">Imóveis</h5>
                        <a href="index.php" class="btn btn-info2 w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Protesto -->
            <div class="col-md-4 mb-3" id="card-provimento">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-gavel fa-3x text-success mb-2"></i>
                        <h5 class="card-title">Protesto</h5>
                        <a href="index.php" class="btn btn-success w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Guia de Recebimento -->
            <div class="col-md-4 mb-3" id="card-guia">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-file-signature fa-3x text-4 mb-2"></i>
                        <h5 class="card-title">Títulos e Documentos</h5>
                        <a href="index.php" class="btn btn-4 w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Controle de Contas a Pagar -->
            <div class="col-md-4 mb-3" id="card-contas">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-building fa-3x text-6 mb-2"></i>
                        <h5 class="card-title">Pessoas Jurídicas</h5>
                        <a href="index.php" class="btn btn-6 w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Vídeos Tutoriais -->
            <div class="col-md-4 mb-3" id="card-manuais">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-ship fa-3x text-tutoriais mb-2"></i>
                        <h5 class="card-title">Contratos Marítimos</h5>
                        <a href="index.php" class="btn btn-tutoriais w-100">Acessar</a>
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
                    $.each(data.order, function (index, cardId) {
                        $("#" + cardId).appendTo("#sortable-cards");
                    });
                },
                error: function (xhr, status, error) {
                    console.error('Erro ao carregar a ordem:', error);
                }
            });
        }

        // Carrega a ordem ao carregar a página
        loadCardOrder();
    });

</script>

<br><br><br>
<?php
include(__DIR__ . '/../rodape.php');
?>

</body>
</html>
