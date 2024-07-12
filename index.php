<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Dashboard</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/font-awesome.min.css">
    <link rel="stylesheet" href="style/css/style.css">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <script src="script/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 240px;
        }
        .chart-container.full-height {
            height: 360px;
        }
        .btn-info:hover {
            color: #fff;
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
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Dashboard - Visão Geral do Sistema</h3>
        <div class="row mb-4">
            <div class="col-md-4">
                <a href="arquivamento/index.php" class="btn btn-primary w-100"><i class="fa fa-folder-open" aria-hidden="true"></i> Arquivamentos</a>
            </div>
            <div class="col-md-4">
                <a href="arquivamento/cadastro.php" class="btn btn-success w-100"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar arquivamento</a>
            </div>
            <div class="col-md-4">
                <a href="arquivamento/categorias.php" class="btn btn-info w-100"><i class="fa fa-tags" aria-hidden="true"></i> Categorias</a>
            </div>
            <div class="col-md-4">
                <a href="tarefas/index.php" class="btn btn-secondary w-100"><i class="fa fa-clock-o" aria-hidden="true"></i> Tarefas</a>
            </div>
            <div class="col-md-4">
                <a href="oficios/index.php" class="btn btn-oficio w-100"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Ofícios</a>
            </div>
            <div class="col-md-4">
                <a href="arquivamento/assinar-doc.php" class="btn btn-assinador w-100"><i class="fa fa-check-square-o" aria-hidden="true"></i> Assinador digital</a>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 id="totalAcervos">0</h2>
                                <p>Total de arquivamentos</p>
                            </div>
                            <i class="fa fa-folder fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 id="novosCadastros">0</h2>
                                <p>Novos arquivamentos</p>
                            </div>
                            <i class="fa fa-plus fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 id="atosExcluidos">0</h2>
                                <p>Arquivamentos excluídos</p>
                            </div>
                            <a style="color: #ffffff" href="arquivamento/lixeira.php"><i class="fa fa-trash fa-2x"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 chart-container">
                <h5>Arquivamentos diários</h5>
                <canvas id="dailyAtosChart"></canvas>
            </div>
            <div class="col-md-4 chart-container">
                <h5>Arquivamentos semanais</h5>
                <canvas id="weeklyAtosChart"></canvas>
            </div>
            <div class="col-md-4 chart-container">
                <h5>Arquivamentos mensais</h5>
                <canvas id="monthlyAtosChart"></canvas>
            </div>
            <div class="col-md-4 chart-container full-height">
                <h5>Arquivamentos por categoria</h5>
                <canvas id="categoryAtosChart"></canvas>
            </div>
            <div class="col-md-4 chart-container full-height">
                <h5>Arquivamentos por usuários</h5>
                <canvas id="userPerformanceChart"></canvas>
            </div>
            <div class="col-md-4 chart-container full-height">
                <h5>Tarefas por status</h5>
                <canvas id="tasksChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="notification">
    <span class="close-btn">&times;</span>    
    <h6>Resumo das tarefas</h6>
    <hr style="border-top: 1px solid rgb(255 255 255 / 31%);">
    <p id="tarefasPendentes">Tarefas pendentes: 0</p>
    <p id="tarefasVencidas">Tarefas com data limite ultrapassada: 0</p>
    <p id="tarefasPrestesVencer">Tarefas prestes a vencer: 0</p>
</div>

<script src="script/jquery-3.5.1.min.js"></script>
<script src="script/bootstrap.min.js"></script>
<script src="script/jquery.mask.min.js"></script>
<script>
    function createChart(ctx, type, data, options) {
        return new Chart(ctx, {
            type: type,
            data: data,
            options: $.extend(true, {
                plugins: {
                    legend: {
                        display: type !== 'doughnut' && type !== 'bar' // Hide legend for doughnut and bar charts
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: getFontColor()
                        }
                    },
                    y: {
                        ticks: {
                            color: getFontColor()
                        }
                    }
                }
            }, options)
        });
    }

    $(document).ready(function() {
        // Carregar o modo do usuário
        $.ajax({
            url: 'load_mode.php',
            method: 'GET',
            success: function(mode) {
                $('body').removeClass('light-mode dark-mode').addClass(mode);
            }
        });

        // Função para alternar modos claro e escuro
        $('.mode-switch').on('click', function() {
            var body = $('body');
            body.toggleClass('dark-mode light-mode');

            var mode = body.hasClass('dark-mode') ? 'dark-mode' : 'light-mode';
            $.ajax({
                url: 'save_mode.php',
                method: 'POST',
                data: { mode: mode },
                success: function(response) {
                    console.log(response);
                }
            });

            // Atualizar cores das legendas dos gráficos
            Chart.helpers.each(Chart.instances, function(instance) {
                instance.options.plugins.legend.labels.color = getFontColor();
                instance.options.scales.x.ticks.color = getFontColor();
                instance.options.scales.y.ticks.color = getFontColor();
                instance.update();
            });
        });

        // Carregar dados do dashboard
        $.ajax({
            url: 'load_dashboard_data.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                $('#totalAcervos').text(data.totalAtos);
                $('#novosCadastros').text(data.novosCadastros);
                $('#atosExcluidos').text(data.atosExcluidos);

                $('#tarefasPendentes').text('Tarefas pendentes: ' + data.tarefasStatus.pendente);
                $('#tarefasVencidas').text('Tarefas com data limite ultrapassada: ' + data.overdueTasks);
                $('#tarefasPrestesVencer').text('Tarefas prestes a vencer: ' + data.upcomingTasks);

                const dailyAtosCtx = document.getElementById('dailyAtosChart').getContext('2d');
                const weeklyAtosCtx = document.getElementById('weeklyAtosChart').getContext('2d');
                const monthlyAtosCtx = document.getElementById('monthlyAtosChart').getContext('2d');
                const categoryAtosCtx = document.getElementById('categoryAtosChart').getContext('2d');
                const userPerformanceCtx = document.getElementById('userPerformanceChart').getContext('2d');
                const tasksCtx = document.getElementById('tasksChart').getContext('2d');

                const dailyColors = ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#2ecc71', '#e74c3c', '#3498db'];
                const weeklyColors = ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#2ecc71'];
                const monthlyColors = ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#2ecc71', '#e74c3c', '#3498db', '#f39c12', '#9b59b6', '#1abc9c', '#c0392b', '#8e44ad'];
                const userColors = ['#36a2eb', '#ff6384', '#cc65fe', '#ffce56', '#2ecc71', '#e74c3c', '#3498db'];
                const categoryColors = ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#2ecc71', '#e74c3c', '#3498db', '#f39c12', '#9b59b6', '#1abc9c', '#c0392b', '#8e44ad'];
                const taskColors = ['#ffce56', '#36a2eb', '#2ecc71', '#e74c3c', '#9b59b6', '#f39c12']; // Added new colors

                createChart(dailyAtosCtx, 'bar', {
                    labels: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
                    datasets: [{
                        label: 'Atos Diários',
                        data: data.dailyAtos,
                        backgroundColor: dailyColors
                    }]
                });

                createChart(weeklyAtosCtx, 'bar', {
                    labels: ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4', 'Semana 5'],
                    datasets: [{
                        label: 'Atos Semanais',
                        data: data.weeklyAtos,
                        backgroundColor: weeklyColors
                    }]
                });

                createChart(monthlyAtosCtx, 'bar', {
                    labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                    datasets: [{
                        label: 'Atos Mensais',
                        data: data.monthlyAtos,
                        backgroundColor: monthlyColors
                    }]
                });

                createChart(categoryAtosCtx, 'pie', {
                    labels: Object.keys(data.atosByCategory),
                    datasets: [{
                        data: Object.values(data.atosByCategory),
                        backgroundColor: categoryColors
                    }]
                });

                createChart(userPerformanceCtx, 'pie', {
                    labels: Object.keys(data.atosByUser),
                    datasets: [{
                        label: 'Quantidade de atos por usuário',
                        data: Object.values(data.atosByUser),
                        backgroundColor: userColors
                    }]
                });

                createChart(tasksCtx, 'pie', {
                    labels: ['Pendente', 'Em Andamento', 'Concluída', 'Cancelada', 'Data Limite Ultrapassada', 'Prestes a Vencer'],
                    datasets: [{
                        label: 'Tarefas',
                        data: [
                            data.tarefasStatus.pendente,
                            data.tarefasStatus['em andamento'] + data.tarefasStatus['iniciada'], // Sum 'Em Andamento' and 'Iniciada'
                            data.tarefasStatus.concluída,
                            data.tarefasStatus.cancelada,
                            data.overdueTasks,
                            data.upcomingTasks
                        ],
                        backgroundColor: taskColors
                    }]
                });
            }
        });

        // Função para fechar a notificação
        $('.notification .close-btn').on('click', function() {
            $(this).parent().hide();
        });
    });
</script>
<br><br><br>
<?php
include(__DIR__ . '/rodape.php');
?>

</body>
</html>
