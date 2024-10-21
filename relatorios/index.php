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
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container mt-4">
        <h2 class="text-center mb-4">Central de Relatórios</h2>

        <!-- Cards de Módulos -->
        <div id="sortable-cards" class="row">
            <!-- Relatório de Tarefas -->
            <div class="col-md-4 mb-3" id="card-tarefas">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-tasks fa-3x text-secondary mb-2"></i>
                        <h5 class="card-title">Relatório de Tarefas</h5>
                        <a href="relatorio_tarefas.php" class="btn btn-secondary w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Relatório de O.S -->
            <div class="col-md-4 mb-3" id="card-os">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-file-invoice-dollar fa-3x text-info mb-2"></i>
                        <h5 class="card-title">Relatório de O.S</h5>
                        <a href="relatorio_os.php" class="btn btn-info2 w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Relatório de Contas a Pagar -->
            <div class="col-md-4 mb-3" id="card-contas">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-credit-card fa-3x text-5 mb-2"></i>
                        <h5 class="card-title">Relatório de Contas a Pagar</h5>
                        <a href="#" class="btn btn-5 w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Tarefas -->
            <!-- <div class="col-md-4 mb-3" id="card-tarefas">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-clock-o fa-3x text-secondary mb-2"></i>
                        <h5 class="card-title">Tarefas</h5>
                        <a href="tarefas/index.php" class="btn btn-secondary w-100">Acessar</a>
                    </div>
                </div>
            </div> -->

            <!-- Ofícios -->
            <!-- <div class="col-md-4 mb-3" id="card-oficios">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-file-pdf-o fa-3x text-warning mb-2"></i>
                        <h5 class="card-title">Ofícios</h5>
                        <a href="oficios/index.php" class="btn btn-warning w-100">Acessar</a>
                    </div>
                </div>
            </div> -->

            <!-- Provimentos e Resoluções -->
            <!-- <div class="col-md-4 mb-3" id="card-provimento">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-balance-scale fa-3x text-6 mb-2"></i>
                        <h5 class="card-title">Provimento e Resoluções</h5>
                        <a href="provimentos/index.php" class="btn btn-6 w-100">Acessar</a>
                    </div>
                </div>
            </div> -->

            <!-- Guia de Recebimento -->
            <!-- <div class="col-md-4 mb-3" id="card-guia">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-file-text fa-3x text-4 mb-2"></i>
                        <h5 class="card-title">Guia de Recebimento</h5>
                        <a href="guia_de_recebimento/index.php" class="btn btn-4 w-100">Acessar</a>
                    </div>
                </div>
            </div> -->

            <!-- Controle de Contas a Pagar -->
            <!-- <div class="col-md-4 mb-3" id="card-contas">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-usd fa-3x text-5 mb-2"></i>
                        <h5 class="card-title">Controle de Contas a Pagar</h5>
                        <a href="contas_a_pagar/index.php" class="btn btn-5 w-100">Acessar</a>
                    </div>
                </div>
            </div> -->

            <!-- Vídeos Tutoriais -->
            <!-- <div class="col-md-4 mb-3" id="card-manuais">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-file-video-o fa-3x text-tutoriais mb-2"></i>
                        <h5 class="card-title">Vídeos Tutoriais</h5>
                        <a href="manuais/index.php" class="btn btn-tutoriais w-100">Acessar</a>
                    </div>
                </div>
            </div> -->

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
