<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

// Função para carregar dados com base no filtro (usaremos isso para AJAX)
function carregarDados($conn, $filtro = [])
{
    // Filtros aplicados
    $filtroDia = isset($filtro['dia']) ? $filtro['dia'] : '';
    $filtroMes = isset($filtro['mes']) ? $filtro['mes'] : '';
    $filtroAno = isset($filtro['ano']) ? $filtro['ano'] : date('Y'); // Por padrão, o ano corrente
    $filtroStatus = isset($filtro['status']) ? $filtro['status'] : '';
    $filtroFuncionario = isset($filtro['funcionario']) ? $filtro['funcionario'] : 'todos';

    // Condições SQL dinâmicas
    $condicoes = [];
    if ($filtroDia) {
        $condicoes[] = "DATE(data_criacao) = '$filtroDia'";
    }
    if ($filtroMes) {
        $condicoes[] = "DATE_FORMAT(data_criacao, '%Y-%m') = '$filtroMes'";
    }
    if ($filtroAno) {
        $condicoes[] = "YEAR(data_criacao) = '$filtroAno'";
    }
    if ($filtroStatus && $filtroStatus != 'todos') {
        $filtroStatusEscapado = $conn->real_escape_string($filtroStatus);
        $condicoes[] = "tarefas.status = '$filtroStatusEscapado'";
    } elseif ($filtroStatus == 'todos') {
        $condicoes[] = "1 = 1"; 
    }
    if (!empty($filtro['funcionario']) && $filtro['funcionario'] != 'todos') {
        $filtroFuncionarioEscapado = $conn->real_escape_string($filtro['funcionario']);
        $condicoes[] = "tarefas.funcionario_responsavel = '$filtroFuncionarioEscapado'";
    }

    $where = '';
    if (count($condicoes) > 0) {
        $where = 'WHERE ' . implode(' AND ', $condicoes);
    }

    // Consultas para cards
    $totalTarefas = $conn->query("SELECT COUNT(*) as total FROM tarefas $where")->fetch_assoc()['total'] ?? 0;
    $concluidas = $conn->query("SELECT COUNT(*) as total FROM tarefas $where AND status = 'concluida'")->fetch_assoc()['total'] ?? 0;
    $pendentes = $conn->query("SELECT COUNT(*) as total FROM tarefas $where AND status = 'pendente'")->fetch_assoc()['total'] ?? 0;
    $canceladas = $conn->query("SELECT COUNT(*) as total FROM tarefas $where AND status = 'cancelada'")->fetch_assoc()['total'] ?? 0;
    $atraso = $conn->query("SELECT COUNT(*) as total FROM tarefas $where AND status = 'pendente' AND data_limite < NOW()")->fetch_assoc()['total'] ?? 0;

    $categoriaData = [];
    $result = $conn->query("
        SELECT 
            categorias.titulo AS categoria_titulo,  /* Traz o nome da categoria */
            WEEK(tarefas.data_criacao, 1) AS semana,  /* Agrupa pela semana de criação da tarefa (segunda-feira como o primeiro dia da semana) */
            COUNT(*) as total  /* Conta o total de tarefas por categoria e semana */
        FROM tarefas 
        LEFT JOIN categorias ON tarefas.categoria = categorias.id  /* Faz a junção com a tabela de categorias */
        $where  /* Aplica os filtros de dia, mês, ano, status, funcionário, etc. */
        GROUP BY categorias.titulo, WEEK(tarefas.data_criacao, 1)  /* Agrupa por categoria e semana */
    ");

    while ($row = $result->fetch_assoc()) {
        $categoriaData[] = $row; 
    }

    $funcionarioData = [];
    $resultFuncionario = $conn->query("
        SELECT 
            funcionario_responsavel, 
            COUNT(*) as total 
        FROM tarefas 
        $where 
        GROUP BY funcionario_responsavel
    ");
    while ($row = $resultFuncionario->fetch_assoc()) {
        $funcionarioData[] = $row;
    }

    // Consulta para gráfico por mês (quantitativo de tarefas por mês do ano)
    $mesData = [];
    $sqlMes = "
        SELECT 
            MONTH(data_criacao) as mes, 
            COUNT(*) as total 
        FROM 
            tarefas 
        $where 
        GROUP BY 
            MONTH(data_criacao)
    ";

    // Executa a consulta para meses
    $resultMes = $conn->query($sqlMes);
    while ($row = $resultMes->fetch_assoc()) {
        $mesData[] = $row;
    }

    // Consulta para gráfico de semanas (quantitativo de tarefas por semana)
    $semanaData = [];
    $sqlSemana = "
        SELECT 
            WEEK(data_criacao, 1) as semana, 
            COUNT(*) as total 
        FROM 
            tarefas 
        $where 
        GROUP BY 
            WEEK(data_criacao, 1)
    ";

    // Executa a consulta para semanas
    $resultSemana = $conn->query($sqlSemana);
    while ($row = $resultSemana->fetch_assoc()) {
        $semanaData[] = $row;
    }


    // Consulta para a tabela
    $tarefas = [];
    $resultTarefas = $conn->query("
        SELECT 
            tarefas.id, 
            tarefas.titulo, 
            categorias.titulo AS categoria_titulo, 
            tarefas.data_limite, 
            tarefas.data_criacao, 
            tarefas.data_conclusao, 
            tarefas.funcionario_responsavel, 
            tarefas.status 
        FROM tarefas 
        LEFT JOIN categorias ON tarefas.categoria = categorias.id 
        $where
    ");
    while ($row = $resultTarefas->fetch_assoc()) {
        $tarefas[] = $row;
    }

    return [
        'totalTarefas' => $totalTarefas,
        'concluidas' => $concluidas,
        'pendentes' => $pendentes,
        'canceladas' => $canceladas,
        'atraso' => $atraso,
        'categoriaData' => $categoriaData,
        'funcionarioData' => $funcionarioData,
        'mesData' => $mesData,
        'semanaData' => $semanaData,
        'tarefas' => $tarefas
    ];
}

// Chamada inicial sem filtro, incluindo o status 'todos' para carregar todos os dados no início
$dados = carregarDados($conn, ['status' => 'todos']);

// Carregar filtros via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filtros = [
        'dia' => $_POST['filtroDia'] ?? '',
        'mes' => $_POST['filtroMes'] ?? '',
        'ano' => $_POST['filtroAno'] ?? '',
        'status' => $_POST['filtroStatus'] ?? '',
        'funcionario' => $_POST['filtroFuncionario'] ?? 'todos'
    ];

    $dados = carregarDados($conn, $filtros);
    echo json_encode($dados);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Tarefas</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">    
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <style>
        #graficoCategoria {
            display: block;
            margin-left: auto;
            margin-right: auto;
            width: 90%; 
            height: auto; 
        }


        .graficos-barra {
            display: flex;
            flex-wrap: wrap; 
            justify-content: center;
            gap: 20px; 
        }

        .grafico-barra {
            flex: 1 1 45%; 
            max-width: 1200px;
            min-width: 300px;
            height: 400px;
            display: flex;
            justify-content: center;
        }

        canvas {
            max-width: 100%;
            height: auto;
        }

        table.dataTable th:nth-child(1),
        table.dataTable td:nth-child(1) {
            width: 8%;
        }

        table.dataTable th:nth-child(4),
        table.dataTable td:nth-child(4),
        table.dataTable th:nth-child(5),
        table.dataTable td:nth-child(5),
        table.dataTable th:nth-child(6),
        table.dataTable td:nth-child(6) {
            width: 11%;
        }

        table.dataTable th:nth-child(8),
        table.dataTable td:nth-child(8) {
            width: 8%;
        }

        table.dataTable th:nth-child(9),
        table.dataTable td:nth-child(9) {
            width: 12%;
        }


    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Relatório de Tarefas</h3>
            <hr>

            <!-- Cards Dinâmicos -->
            <div class="row">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total de Tarefas</h5>
                            <p class="card-text" id="totalTarefas"><?= $dados['totalTarefas'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Concluídas</h5>
                            <p class="card-text" id="concluidas"><?= $dados['concluidas'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h5 class="card-title">Pendentes</h5>
                            <p class="card-text" id="pendentes"><?= $dados['pendentes'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Em Atraso</h5>
                            <p class="card-text" id="atraso"><?= $dados['atraso'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <hr>

            <!-- Filtros Dinâmicos -->
            <form id="filtroForm">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="filtroDia">Filtrar por Dia:</label>
                        <input type="date" class="form-control" id="filtroDia" name="filtroDia">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="filtroMes">Filtrar por Mês:</label>
                        <input type="month" class="form-control" id="filtroMes" name="filtroMes">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="filtroAno">Filtrar por Ano:</label>
                        <input type="number" class="form-control" id="filtroAno" name="filtroAno" placeholder="YYYY">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="filtroStatus">Filtrar por Status:</label>
                        <select class="form-control" id="filtroStatus" name="filtroStatus">
                            <option value="todos">Todos</option>
                            <option value="pendente">Pendente</option>
                            <option value="Iniciada">Iniciada</option>
                            <option value="Em Andamento">Em Andamento</option>
                            <option value="Em Espera">Em Espera</option>
                            <option value="cancelada">Cancelada</option>
                            <option value="concluida">Concluída</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="filtroFuncionario">Filtrar por Funcionário:</label>
                        <select class="form-control" id="filtroFuncionario" name="filtroFuncionario">
                            <option value="todos">Todos</option>
                            <?php foreach ($dados['funcionarioData'] as $funcionario) { ?>
                                <option value="<?= $funcionario['funcionario_responsavel'] ?>"><?= $funcionario['funcionario_responsavel'] ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </form>
            <hr>
            <!-- Tabela de Resultados -->
            <div class="table-responsive">
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 90%">
                    <thead>
                        <tr>
                            <th>Nº Protocolo</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Data Criação</th>
                            <th>Data Limite</th>
                            <th>Data de Conclusão</th>
                            <th>Responsável</th>
                            <th>Status</th>
                            <th>Tempo de Execução</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados['tarefas'] as $tarefa) { 
                            // Calcular tempo de execução
                            $tempoExecucao = '-';
                            if (!empty($tarefa['data_conclusao'])) {
                                $inicio = strtotime($tarefa['data_criacao']);
                                $fim = strtotime($tarefa['data_conclusao']);
                                $diferenca = $fim - $inicio;

                                // Calcular dias, horas e minutos
                                $dias = floor($diferenca / (60 * 60 * 24));
                                $horas = floor(($diferenca % (60 * 60 * 24)) / (60 * 60));
                                $minutos = floor(($diferenca % (60 * 60)) / 60);

                                // Formatar tempo de execução com dias, horas e minutos
                                $tempoExecucao = "{$dias}d {$horas}h {$minutos}m";
                            }
                        ?>
                            <tr>
                                <td><?= $tarefa['id'] ?></td>
                                <td><?= $tarefa['titulo'] ?></td>
                                <td><?= $tarefa['categoria_titulo'] ?></td>
                                <td><?= !empty($tarefa['data_criacao']) ? date('d/m/Y H:i:s', strtotime($tarefa['data_criacao'])) : '-' ?></td>
                                <td><?= !empty($tarefa['data_limite']) ? date('d/m/Y H:i:s', strtotime($tarefa['data_limite'])) : '-' ?></td>
                                <td><?= !empty($tarefa['data_conclusao']) ? date('d/m/Y H:i:s', strtotime($tarefa['data_conclusao'])) : '-' ?></td>
                                <td><?= $tarefa['funcionario_responsavel'] ?></td>
                                <td><?= ucwords(strtolower($tarefa['status'])) ?></td>
                                <td><?= $tempoExecucao ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <hr>
            
            <div class="graficos-barra">
                <div class="grafico-barra">
                    <canvas id="graficoMes"></canvas>
                </div>
                <div class="grafico-barra">
                    <canvas id="graficoSemana"></canvas>
                </div>
            </div>
            <hr>
            <canvas id="graficoCategoria"></canvas>
    
            <hr>
            <div class="graficos-barra">
                <div class="grafico-barra">
                    <canvas id="graficoFuncionario"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/chart.js"></script>
    <script src="../script/chartjs-plugin-datalabels2.0.js"></script>
    <script src="../script/sweetalert2.js"></script>

    <script>
        $(document).ready(function () {
            // Inicializar DataTable
            $('#tabelaResultados').DataTable();

            // Filtro Dinâmico via AJAX
            $('#filtroForm input, #filtroForm select').on('change', function () {
                var filtroDia = $('#filtroDia').val();
                var filtroMes = $('#filtroMes').val();
                var filtroAno = $('#filtroAno').val();
                var filtroStatus = $('#filtroStatus').val();
                var filtroFuncionario = $('#filtroFuncionario').val();

                $.ajax({
                    url: '',
                    method: 'POST',
                    data: {
                        filtroDia: filtroDia,
                        filtroMes: filtroMes,
                        filtroAno: filtroAno,
                        filtroStatus: filtroStatus,
                        filtroFuncionario: filtroFuncionario
                    },
                    success: function (response) {
                        var dados = JSON.parse(response);
                        
                        // Atualizar Cards
                        $('#totalTarefas').text(dados.totalTarefas);
                        $('#concluidas').text(dados.concluidas);
                        $('#pendentes').text(dados.pendentes);
                        $('#canceladas').text(dados.canceladas);
                        $('#atraso').text(dados.atraso);

                        // Atualizar a tabela de resultados
                        function capitalizeWords(str) {
                            return str.toLowerCase().replace(/(?:^|\s)\S/g, function (char) {
                                return char.toUpperCase();
                            });
                        }

                        // Destruir o DataTable antes de atualizar os dados
                        $('#tabelaResultados').DataTable().destroy();

                        // Atualizar a tabela de resultados
                        var tabelaBody = $('#tabelaResultados tbody');
                        tabelaBody.empty(); 
                        dados.tarefas.forEach(function (tarefa) {
                            var dataConclusao = tarefa.data_conclusao ? tarefa.data_conclusao : '-';
                            var tempoExecucao = '-';
                            if (tarefa.data_conclusao) {
                                var inicio = new Date(tarefa.data_criacao);
                                var fim = new Date(tarefa.data_conclusao);
                                var diferenca = fim - inicio;

                                var dias = Math.floor(diferenca / (1000 * 60 * 60 * 24));
                                var horas = Math.floor((diferenca % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                var minutos = Math.floor((diferenca % (1000 * 60 * 60)) / (1000 * 60));

                                tempoExecucao = `${dias}d ${horas}h ${minutos}m`;
                            }

                            var dataCriacao = tarefa.data_criacao ? new Date(tarefa.data_criacao).toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' }) : '-';
                            var dataLimite = tarefa.data_limite ? new Date(tarefa.data_limite).toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' }) : '-';
                            var dataConclusaoFormatada = tarefa.data_conclusao ? new Date(tarefa.data_conclusao).toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' }) : '-';

                            tabelaBody.append('<tr>' +
                                '<td>' + tarefa.id + '</td>' +
                                '<td>' + tarefa.titulo + '</td>' +
                                '<td>' + tarefa.categoria_titulo + '</td>' +
                                '<td>' + dataCriacao + '</td>' +
                                '<td>' + dataLimite + '</td>' +
                                '<td>' + dataConclusaoFormatada + '</td>' +
                                '<td>' + tarefa.funcionario_responsavel + '</td>' +
                                '<td>' + capitalizeWords(tarefa.status) + '</td>' + 
                                '<td>' + tempoExecucao + '</td>' + 
                            '</tr>');
                        });

                        $('#tabelaResultados').DataTable();

                        // Atualizar gráficos
                        atualizarGraficos(dados);
                    }
                });
            });

            var chartCategoria, chartMes, chartSemana, chartFuncionario;

            function atualizarGraficos(dados) {
                if (chartCategoria) chartCategoria.destroy();
                if (chartMes) chartMes.destroy();
                if (chartSemana) chartSemana.destroy();
                if (chartFuncionario) chartFuncionario.destroy();

                function gerarCorAleatoria() {
                    const letras = '0123456789ABCDEF';
                    let cor = '#';
                    for (let i = 0; i < 6; i++) {
                        cor += letras[Math.floor(Math.random() * 16)];
                    }
                    return cor;
                }

                if (Array.isArray(dados.categoriaData)) {
                    var totaisPorCategoria = {};

                    // Inicializa o array de totais por categoria e semana, somando os valores de semanas repetidas
                    dados.categoriaData.forEach(function (cat) {
                        if (!totaisPorCategoria[cat.categoria_titulo]) {
                            totaisPorCategoria[cat.categoria_titulo] = {}; // Inicializa o objeto para a categoria
                        }

                        // Se já existir um valor para a semana, somamos ao valor atual
                        if (!totaisPorCategoria[cat.categoria_titulo][cat.semana]) {
                            totaisPorCategoria[cat.categoria_titulo][cat.semana] = 0;
                        }
                        totaisPorCategoria[cat.categoria_titulo][cat.semana] += cat.total; // Soma os totais por semana
                    });

                    // Gera as cores e os datasets para o gráfico
                    var datasets = Object.keys(totaisPorCategoria).map((categoria, index) => {
                        var semanasOrdenadas = Object.keys(totaisPorCategoria[categoria]).sort(); // Ordena as semanas
                        return {
                            label: categoria,
                            data: semanasOrdenadas.map(semana => totaisPorCategoria[categoria][semana]), // Extrai os totais por semana
                            borderColor: gerarCorAleatoria(),
                            backgroundColor: gerarCorAleatoria(),
                            fill: false,
                            tension: 0.1
                        };
                    });

                    // Gera uma lista única de semanas ordenadas
                    var semanas = Array.from(new Set(dados.categoriaData.map(cat => cat.semana))).sort();

                    var ctxCategoria = document.getElementById('graficoCategoria').getContext('2d');
                    chartCategoria = new Chart(ctxCategoria, {
                        type: 'line',
                        data: {
                            labels: semanas.map(sem => `Semana ${sem}`), // Mapeia as semanas
                            datasets: datasets // Um dataset para cada categoria
                        },
                        options: {
                            plugins: {
                                legend: {
                                    display: true
                                },
                                title: {
                                    display: true,
                                    text: 'Categorias por semana',
                                    font: {
                                        size: 18
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }


                if (Array.isArray(dados.funcionarioData)) {
                    var funcionarios = [];
                    var totaisFuncionario = [];
                    var cores = [];

                    // Função para gerar cores aleatórias
                    function gerarCorAleatoria() {
                        const letras = '0123456789ABCDEF';
                        let cor = '#';
                        for (let i = 0; i < 6; i++) {
                            cor += letras[Math.floor(Math.random() * 16)];
                        }
                        return cor;
                    }

                    // Preenche os dados e gera cores dinâmicas
                    dados.funcionarioData.forEach(function (func, index) {
                        funcionarios.push(func.funcionario_responsavel);
                        totaisFuncionario.push(func.total);
                        cores.push(gerarCorAleatoria()); // Gera uma cor para cada funcionário
                    });

                    var ctxFuncionario = document.getElementById('graficoFuncionario').getContext('2d');
                    chartFuncionario = new Chart(ctxFuncionario, {
                        type: 'bar',
                        data: {
                            labels: funcionarios,
                            datasets: [{
                                label: 'Tarefas por funcionário',
                                data: totaisFuncionario,
                                backgroundColor: cores // Usa as cores dinâmicas
                            }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    display: true,
                                    labels: {
                                        generateLabels: function (chart) {
                                            return funcionarios.map((funcionario, index) => ({
                                                text: funcionario,
                                                fillStyle: cores[index],
                                                strokeStyle: cores[index],
                                                hidden: false,
                                                index: index
                                            }));
                                        }
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Tarefas por funcionário',
                                    font: {
                                        size: 18
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    display: true
                                },
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }


                // Atualizar gráfico de meses (tarefas por mês, cores diferentes por mês)
                if (Array.isArray(dados.mesData) && dados.mesData.length > 0) {
                    var meses = [];
                    var totaisMes = [];
                    var coresMes = ['#007bff', '#dc3545', '#ffc107', '#28a745', '#17a2b8', '#6610f2', '#fd7e14', '#20c997', '#6f42c1', '#e83e8c', '#28a745', '#ffc107'];

                    dados.mesData.forEach(function (mes) {
                        meses.push(`Mês ${mes.mes}`);
                        totaisMes.push(mes.total);
                    });

                    var ctxMes = document.getElementById('graficoMes').getContext('2d');
                    chartMes = new Chart(ctxMes, {
                        type: 'bar',
                        data: {
                            labels: meses,
                            datasets: [{
                                label: 'Tarefas por mês',
                                data: totaisMes,
                                backgroundColor: coresMes.slice(0, meses.length)
                            }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    display: false,
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Tarefas por mês',
                                    font: {
                                        size: 18
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                } else {
                    // Se não houver dados, limpar o gráfico
                    var ctxMes = document.getElementById('graficoMes').getContext('2d');
                    chartMes = new Chart(ctxMes, {
                        type: 'bar',
                        data: {
                            labels: [],
                            datasets: []
                        },
                        options: {
                            plugins: {
                                legend: {
                                    display: false,
                                },
                                title: {
                                    display: true,
                                    text: 'Tarefas por mês (sem dados)',
                                    font: {
                                        size: 18
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }

                // Atualizar gráfico de semanas (tarefas por semana, cores diferentes por semana)
                if (Array.isArray(dados.semanaData) && dados.semanaData.length > 0) {
                    var semanas = [];
                    var totaisSemana = [];
                    var coresSemana = ['#007bff', '#dc3545', '#ffc107', '#28a745', '#17a2b8', '#6610f2', '#fd7e14'];

                    dados.semanaData.forEach(function (semana) {
                        semanas.push(`Semana ${semana.semana}`);
                        totaisSemana.push(semana.total);
                    });

                    var ctxSemana = document.getElementById('graficoSemana').getContext('2d');
                    chartSemana = new Chart(ctxSemana, {
                        type: 'bar',
                        data: {
                            labels: semanas,
                            datasets: [{
                                label: 'Tarefas por semana',
                                data: totaisSemana,
                                backgroundColor: coresSemana.slice(0, semanas.length) 
                            }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    display: false,
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Tarefas por semana',
                                    font: {
                                        size: 18
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                } else {
                    // Se não houver dados, limpar o gráfico
                    var ctxSemana = document.getElementById('graficoSemana').getContext('2d');
                    chartSemana = new Chart(ctxSemana, {
                        type: 'bar',
                        data: {
                            labels: [],
                            datasets: []
                        },
                        options: {
                            plugins: {
                                legend: {
                                    display: false,
                                },
                                title: {
                                    display: true,
                                    text: 'Tarefas por semana (sem dados)',
                                    font: {
                                        size: 18
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }



            }

            atualizarGraficos(<?= json_encode($dados) ?>);
        });
    </script>
     <?php
            include(__DIR__ . '/../rodape.php');
      ?>
</body>

</html>
