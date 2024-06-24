<?php
include(__DIR__ . '/session_check.php');
checkSession();

function countFilesInDirectory($directory, $dateRange = null) {
    $count = 0;
    if (is_dir($directory)) {
        $files = scandir($directory);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $filePath = $directory . '/' . $file;
                $data = json_decode(file_get_contents($filePath), true);
                if ($dateRange) {
                    $fileDate = strtotime($data['data_cadastro']);
                    if ($fileDate >= $dateRange['start'] && $fileDate <= $dateRange['end']) {
                        $count++;
                    }
                } else {
                    $count++;
                }
            }
        }
    }
    return $count;
}

$totalAcervos = countFilesInDirectory(__DIR__ . '/arquivamento/meta-dados');

$twoDaysAgo = strtotime('-2 days');
$dateRange = ['start' => $twoDaysAgo, 'end' => time()];
$novosCadastros = countFilesInDirectory(__DIR__ . '/arquivamento/meta-dados', $dateRange);

$atosExcluidos = countFilesInDirectory(__DIR__ . '/arquivamento/lixeira');
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
                    <a href="arquivamento/index.php" class="btn btn-primary w-100"><i class="fa fa-folder-open" aria-hidden="true"></i> Acervo Cadastrado</a>
                </div>
                <div class="col-md-4">
                    <a href="arquivamento/cadastro.php" class="btn btn-success w-100"><i class="fa fa-plus-circle" aria-hidden="true"></i> Cadastrar de Acervo</a>
                </div>
                <div class="col-md-4">
                    <a href="arquivamento/categorias.php" class="btn btn-info w-100"><i class="fa fa-tags" aria-hidden="true"></i> Categorias</a>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2><?php echo $totalAcervos; ?></h2>
                                    <p>Total de Acervos</p>
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
                                    <h2><?php echo $novosCadastros; ?></h2>
                                    <p>Novos Cadastros</p>
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
                                    <h2><?php echo $atosExcluidos; ?></h2>
                                    <p>Atos Excluídos</p>
                                </div>
                                <a style="color: #ffffff" href="arquivamento/lixeira.php"><i class="fa fa-trash fa-2x"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 chart-container">
                    <h5>Quantidade de atos diários</h5>
                    <canvas id="dailyAtosChart"></canvas>
                </div>
                <div class="col-md-4 chart-container">
                    <h5>Quantidade de atos semanais</h5>
                    <canvas id="weeklyAtosChart"></canvas>
                </div>
                <div class="col-md-4 chart-container">
                    <h5>Quantidade de atos mensais</h5>
                    <canvas id="monthlyAtosChart"></canvas>
                </div>
                <div class="col-md-4 chart-container full-height">
                    <h5>Quantidade de atos por categoria</h5>
                    <canvas id="categoryAtosChart"></canvas>
                </div>
                <div class="col-md-4 chart-container full-height">
                    <h5>Quantidade de atos cadastrados</h5>
                    <canvas id="totalAtosChart"></canvas>
                </div>
                <div class="col-md-4 chart-container full-height">
                    <h5>Quantidade de atos por usuários</h5>
                    <canvas id="userPerformanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="script/jquery-3.5.1.min.js"></script>
    <script src="script/bootstrap.min.js"></script>
    <script src="script/jquery.mask.min.js"></script>
    <script>
        function openNav() {
            document.getElementById("mySidebar").style.width = "250px";
            document.getElementById("main").style.marginLeft = "250px";
        }

        function closeNav() {
            document.getElementById("mySidebar").style.width = "0";
            document.getElementById("main").style.marginLeft = "0";
        }

        function getFontColor() {
            return $('body').hasClass('dark-mode') ? '#ffffff' : '#000000';
        }

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
                    const totalAtosCtx = document.getElementById('totalAtosChart').getContext('2d');
                    const dailyAtosCtx = document.getElementById('dailyAtosChart').getContext('2d');
                    const weeklyAtosCtx = document.getElementById('weeklyAtosChart').getContext('2d');
                    const monthlyAtosCtx = document.getElementById('monthlyAtosChart').getContext('2d');
                    const categoryAtosCtx = document.getElementById('categoryAtosChart').getContext('2d');
                    const userPerformanceCtx = document.getElementById('userPerformanceChart').getContext('2d');

                    const dailyColors = ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#2ecc71', '#e74c3c', '#3498db'];
                    const weeklyColors = ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#2ecc71'];
                    const monthlyColors = ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#2ecc71', '#e74c3c', '#3498db', '#f39c12', '#9b59b6', '#1abc9c', '#c0392b', '#8e44ad'];
                    const userColors = ['#36a2eb', '#ff6384', '#cc65fe', '#ffce56', '#2ecc71', '#e74c3c', '#3498db'];
                    const categoryColors = ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#2ecc71', '#e74c3c', '#3498db', '#f39c12', '#9b59b6', '#1abc9c', '#c0392b', '#8e44ad'];

                    createChart(totalAtosCtx, 'doughnut', {
                        labels: ['Total de Atos'],
                        datasets: [{
                            data: [data.totalAtos],
                            backgroundColor: ['#36a2eb']
                        }]
                    });

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
                }
            });
        });
    </script>

<?php
include(__DIR__ . '/rodape.php');
?>

</body>
</html>
