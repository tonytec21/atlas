<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo'); 

// Função para atualizar os dados nos dois bancos de dados
function updateServentia($razao_social, $cidade, $status, $cns) {
    // Conexão com o banco de dados "atlas"
    $connAtlas = new mysqli("localhost", "root", "", "atlas");
    if ($connAtlas->connect_error) {
        die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
    }

    // Conexão com o banco de dados "oficios_db"
    $connOficios = new mysqli("localhost", "root", "", "oficios_db");
    if ($connOficios->connect_error) {
        die("Falha na conexão com o banco oficios_db: " . $connOficios->connect_error);
    }

    // Atualizar dados na tabela "cadastro_serventia" do banco "atlas"
    $stmtAtlas = $connAtlas->prepare("UPDATE cadastro_serventia SET razao_social = ?, cidade = ?, status = ?, cns = ? WHERE id = 1");
    $stmtAtlas->bind_param("ssss", $razao_social, $cidade, $status, $cns);
    $stmtAtlas->execute();
    $stmtAtlas->close();
    $connAtlas->close();

    // Atualizar dados na tabela "cadastro_serventia" do banco "oficios_db"
    $stmtOficios = $connOficios->prepare("UPDATE cadastro_serventia SET razao_social = ?, cidade = ?, status = ?, cns = ? WHERE id = 1");
    $stmtOficios->bind_param("ssss", $razao_social, $cidade, $status, $cns);
    $stmtOficios->execute();
    $stmtOficios->close();
    $connOficios->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $razao_social = $_POST['razao_social'];
    $cidade = $_POST['cidade'];
    $status = $_POST['status'];
    $cns = $_POST['cns'];
    
    if (!preg_match('/^\d{6}$/', $cns)) {
        $errorMessage = "O CNS deve conter exatamente 6 caracteres numéricos.";
    } else {
        updateServentia($razao_social, $cidade, $status, $cns);
        $successMessage = "Dados da serventia atualizados com sucesso!";
    }
}

// Conexão com o banco de dados "atlas"
$connAtlas = new mysqli("localhost", "root", "", "atlas");
if ($connAtlas->connect_error) {
    die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
}

// Buscar dados da serventia no banco "atlas"
$stmt = $connAtlas->prepare("SELECT * FROM cadastro_serventia WHERE id = 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Dados da serventia não encontrados.");
}

$serventiaData = $result->fetch_assoc();
$stmt->close();
$connAtlas->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Editar Cadastro da Serventia</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/font-awesome.min.css">
    <link rel="stylesheet" href="style/css/style.css">
    <link rel="icon" href="style/img/favicon_novo.png" type="image/png">
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
        <h3>Editar Cadastro da Serventia</h3>
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="form-group">
                <label for="razao_social">Razão Social</label>
                <input type="text" class="form-control" id="razao_social" name="razao_social" value="<?php echo htmlspecialchars($serventiaData['razao_social']); ?>" required>
            </div>
            <div class="form-group">
                <label for="cidade">Cidade</label>
                <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo htmlspecialchars($serventiaData['cidade']); ?>" required>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <input type="text" class="form-control" id="status" name="status" value="<?php echo htmlspecialchars($serventiaData['status']); ?>" required>
            </div>
            <div class="form-group">
                <label for="cns">CNS</label>
                <input type="text" class="form-control" id="cns" name="cns" value="<?php echo htmlspecialchars($serventiaData['cns']); ?>" required pattern="\d{6}" title="O CNS deve conter exatamente 6 caracteres numéricos." maxlength="6">
            </div>
            <button type="submit" class="btn btn-primary">Salvar</button>
        </form>
    </div>
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
            // Atualizar cores das legendas dos gráficos
            Chart.helpers.each(Chart.instances, function(instance) {
                instance.options.plugins.legend.labels.color = getFontColor();
                instance.options.scales.x.ticks.color = getFontColor();
                instance.options.scales.y.ticks.color = getFontColor();
                instance.update();
            });
        });

        // Aplicar máscara ao campo CNS
        $('#cns').mask('000000');

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
