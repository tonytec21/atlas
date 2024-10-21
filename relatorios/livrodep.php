<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

// Consultas para os cards
$total_de_os_query = "SELECT COUNT(*) AS total FROM ordens_de_servico";
$total_de_os = $conn->query($total_de_os_query)->fetch_assoc()['total'];

$liquidadas_query = "
    SELECT COUNT(DISTINCT os.id) AS total
    FROM ordens_de_servico os
    INNER JOIN ordens_de_servico_itens osi ON os.id = osi.ordem_servico_id
    GROUP BY os.id
    HAVING SUM(CASE WHEN osi.status = 'liquidado' THEN 1 ELSE 0 END) = COUNT(osi.id)
";
$liquidadas = $conn->query($liquidadas_query)->num_rows;

$canceladas_query = "
    SELECT COUNT(*) AS total
    FROM ordens_de_servico
    WHERE status = 'Cancelado'
";
$result = $conn->query($canceladas_query);
$canceladas = $result->fetch_assoc()['total'];

$pendentes_query = "
    SELECT ordem_servico_id
    FROM ordens_de_servico_itens
    GROUP BY ordem_servico_id
    HAVING 
        SUM(CASE WHEN status IS NULL OR status = '' THEN 1 ELSE 0 END) = COUNT(*)
";
$result = $conn->query($pendentes_query);
$pendentes = $result->num_rows;

$parcialmente_liquidadas_query = "
    SELECT ordem_servico_id
    FROM ordens_de_servico_itens
    GROUP BY ordem_servico_id
    HAVING 
        (
            SUM(CASE WHEN status IN ('liquidado', 'parcialmente liquidado') THEN 1 ELSE 0 END) > 0
            AND SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) > 0
        )
        OR 
        (
            SUM(CASE WHEN status = 'parcialmente liquidado' THEN 1 ELSE 0 END) = COUNT(*)
        )
        OR 
        (
            SUM(CASE WHEN status = 'parcialmente liquidado' THEN 1 ELSE 0 END) > 0
            AND SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) > 0
        )
";
$result = $conn->query($parcialmente_liquidadas_query);
$parcialmente_liquidadas = $result->num_rows;

$pagas_query = "
    SELECT COUNT(DISTINCT ordem_de_servico_id) AS total
    FROM pagamento_os
";
$result = $conn->query($pagas_query);
$os_pagas = $result->fetch_assoc()['total'];

$pendente_pagamento_query = "
    SELECT COUNT(DISTINCT os.id) AS total
    FROM ordens_de_servico os
    LEFT JOIN pagamento_os po ON os.id = po.ordem_de_servico_id
    WHERE po.ordem_de_servico_id IS NULL
    AND os.status != 'Cancelado'
";
$result = $conn->query($pendente_pagamento_query);
$os_pendentes_pagamento = $result->fetch_assoc()['total'];

// Início da Tabela HTML
$html = '
    <table id="tabelaOS" class="table table-striped table-bordered" style="width:100%">
        <thead>
            <tr>
                <th>Nº OS</th>
                <th>Apresentante</th>
                <th>CPF/CNPJ</th>
                <th>Valor Total</th>
                <th>Data</th>
                <th>Funcionário</th>
                <th>Situação</th>
                <th>Status</th>
                <th>Depósito Prévio</th>
                <th>Atos Praticados</th>
            </tr>
        </thead>
        <tbody>
';

// Consulta das OS
$os_query = $conn->query("
    SELECT os.id, os.cliente, os.cpf_cliente, os.total_os, os.data_criacao, 
           os.status AS situacao, os.criado_por
    FROM ordens_de_servico os
");

while ($os = $os_query->fetch_assoc()) {
    $os_id = $os['id'];
    $cliente = $os['cliente'];
    $cpf_cnpj = $os['cpf_cliente'] ?: '---';
    $total_os = 'R$ ' . number_format($os['total_os'], 2, ',', '.');
    $data_os = date('d/m/Y', strtotime($os['data_criacao']));
    $funcionario = $os['criado_por'] ?: 'Desconhecido';
    
    // Verificar a Situação da OS
    $situacao = ($os['situacao'] === 'Cancelado') 
        ? 'Cancelada' 
        : (temPagamento($conn, $os_id) 
            ? 'Paga' 
            : 'Pendente de Pagamento');

    // Verificar o Status da OS
    $status = obterStatusOS($conn, $os_id);

    // Calcular Depósito Prévio
    $deposito_query = $conn->prepare("
        SELECT SUM(total_pagamento) AS total_deposito
        FROM pagamento_os
        WHERE ordem_de_servico_id = ?
    ");
    $deposito_query->bind_param("i", $os_id);
    $deposito_query->execute();
    $deposito_query->bind_result($total_deposito);
    $deposito_query->fetch();
    $deposito_query->close();

    $deposito_previo = $total_deposito 
        ? 'R$ ' . number_format($total_deposito, 2, ',', '.') 
        : '---';

    // Calcular Atos Praticados (soma das duas tabelas)
    $atos_query = $conn->prepare("
        SELECT 
            (SELECT IFNULL(SUM(total), 0) FROM atos_liquidados WHERE ordem_servico_id = ?) +
            (SELECT IFNULL(SUM(total), 0) FROM atos_manuais_liquidados WHERE ordem_servico_id = ?)
        AS total_atos
    ");
    $atos_query->bind_param("ii", $os_id, $os_id);
    $atos_query->execute();
    $atos_query->bind_result($total_atos);
    $atos_query->fetch();
    $atos_query->close();

    $atos_praticados = 'R$ ' . number_format($total_atos, 2, ',', '.');

    // Montar a linha da tabela
    $html .= "
        <tr style='zoom: 90%'>
            <td>{$os_id}</td>
            <td>{$cliente}</td>
            <td>{$cpf_cnpj}</td>
            <td>{$total_os}</td>
            <td>{$data_os}</td>
            <td>{$funcionario}</td>
            <td>{$situacao}</td>
            <td>{$status}</td>
            <td>{$deposito_previo}</td>
            <td>{$atos_praticados}</td>
        </tr>
    ";
}

$html .= '</tbody></table>';

// Função para verificar se a OS possui pagamento
function temPagamento($conn, $os_id) {
    $query = $conn->prepare("SELECT COUNT(*) FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $query->bind_param("i", $os_id);
    $query->execute();
    $query->bind_result($count);
    $query->fetch();
    return $count > 0;
}

// Função para obter o status da OS
function obterStatusOS($conn, $os_id) {
    $query = $conn->prepare("
        SELECT 
            SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) AS total_liquidado,
            SUM(CASE WHEN status = 'parcialmente liquidado' THEN 1 ELSE 0 END) AS total_parcial,
            SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) AS total_pendente,
            SUM(CASE WHEN status = 'Cancelado' THEN 1 ELSE 0 END) AS total_cancelado,
            COUNT(*) AS total_itens
        FROM ordens_de_servico_itens
        WHERE ordem_servico_id = ?
    ");
    $query->bind_param("i", $os_id);
    $query->execute();
    $query->bind_result($total_liquidado, $total_parcial, $total_pendente, $total_cancelado, $total_itens);
    $query->fetch();
    $query->close();

    // Lógica para definir o status da OS
    if ($total_cancelado == $total_itens) {
        return 'Cancelada';
    } elseif ($total_liquidado > 0 && $total_pendente == 0 && $total_parcial == 0) {
        return 'Liquidada';
    } elseif ($total_parcial > 0 || ($total_liquidado > 0 && $total_pendente > 0)) {
        return 'Parcialmente Liquidada';
    } else {
        return 'Pendente de Liquidação';
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório e Ordens de Serviço</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <style>
        .card-status {
            margin-bottom: 15px;
        }
        .card {
            border-radius: 12px;
            height: 180px;
            text-align: center;
        }

        .card h5 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .card p {
            font-size: 2rem;
            margin: 0;
            font-weight: bold;
        }

        .shadow {
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
        }

        .row.g-3 > .col {
            padding: 10px;
        }

        .row {
            margin-bottom: 20px;
        }

        .card-total {
            background-color: #007bff; 
            color: white;
        }

        .card-pagas {
            background-color: #17a2b8; 
            color: white;
        }

        .card-liquidadas {
            background-color: #28a745; 
            color: white;
        }

        .card-parcialmente {
            background-color: #ffc107; 
            color: white;
        }

        .card-canceladas {
            background-color: #dc3545; 
            color: white;
        }

        .card-pendentes {
            background-color: #6c757d; 
            color: white;
        }

        .card-pendentes-pagamento {
            background-color: #ff9800; 
            color: white;
        }

        .col-md-3 {
            margin-bottom: 20px;
        }
    </style>
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Relatório O.S e Depósito Prévio</h3>
            <hr>
            <div class="row g-3 justify-content-center">
                <div class="col-md-3">
                    <div class="card card-total text-center shadow h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5>Total de O.S.</h5>
                            <p><?= $total_de_os ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-pagas text-center shadow h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5>Todal de O.S. Pagas</h5>
                            <p><?= htmlspecialchars($os_pagas) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-liquidadas text-center shadow h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5>Total Liquidadas</h5>
                            <p><?= $liquidadas ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-parcialmente text-center shadow h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5>Parcialmente Liquidadas</h5>
                            <p><?= $parcialmente_liquidadas ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-canceladas text-center shadow h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5>Canceladas</h5>
                            <p><?= $canceladas ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-pendentes text-center shadow h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5>Pendentes de Liquidação</h5>
                            <p><?= $pendentes ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-pendentes-pagamento text-center shadow h-100">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5>Pendentes de Pagamento</h5>
                            <p><?= htmlspecialchars($os_pendentes_pagamento) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr>
            <div class="row mb-4">
                <div class="col-md-2">
                    <label for="filtroDia">Filtrar por Dia:</label>
                    <input type="date" id="filtroDia" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="filtroMes">Filtrar por Mês:</label>
                    <input type="month" id="filtroMes" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="filtroAno">Filtrar por Ano:</label>
                    <input type="number" id="filtroAno" class="form-control" min="2000" max="2100" placeholder="Ex: 2024">
                </div>
                <div class="col-md-2">
                    <label for="filtroStatus">Filtrar por Status:</label>
                    <select id="filtroStatus" class="form-control">
                        <option value="">Todos</option>
                        <option value="Pendente de Liquidação">Pendente de Liquidação</option>
                        <option value="Liquidada">Liquidada</option>
                        <option value="Parcialmente Liquidada">Parcialmente Liquidada</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtroSituacao">Filtrar por Situação:</label>
                    <select id="filtroSituacao" class="form-control">
                        <option value="">Todas</option>
                        <option value="Cancelada">Cancelada</option>
                        <option value="Paga">Paga</option>
                        <option value="Pendente de Pagamento">Pendente de Pagamento</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtroFuncionario">Filtrar por Funcionário:</label>
                    <select id="filtroFuncionario" class="form-control">
                        <option value="">Selecione um funcionário</option>
                        <!-- As opções de funcionários serão inseridas aqui -->
                    </select>
                </div>
            </div>
            <hr>
            <div class="table-responsive">
                <?= $html ?>
            </div>

            <hr>
            <div class="row">
                <div class="col-md-6">
                    <canvas id="osPorMes"></canvas>
                </div>
                <div class="col-md-6">
                    <canvas id="osPorSemana"></canvas>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <canvas id="faturamentoPorMes"></canvas>
                </div>
                <div class="col-md-6">
                    <canvas id="faturamentoPorSemana"></canvas>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6" style="width: 400px; height: 400px;">
                    <canvas id="osPorFuncionario"></canvas>
                </div>
                <div class="col-md-6">
                    <canvas id="faturamentoPorFuncionario"></canvas>
                </div>
            </div>
            <hr>

        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/chart.js"></script>

    <script>
        $(document).ready(function () {
            function carregarFuncionarios() {
                $.ajax({
                    url: 'buscar_funcionarios.php',
                    method: 'GET',
                    success: function (data) {
                        console.log('Dados recebidos:', data);
                        
                        // Garante que 'data' seja um array válido
                        if (Array.isArray(data)) {
                            let select = $('#filtroFuncionario');
                            data.forEach(funcionario => {
                                select.append(`<option value="${funcionario}">${funcionario}</option>`);
                            });
                        } else {
                            console.error('Erro: A resposta não é um array válido.');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erro ao carregar os funcionários:', error);
                    }
                });
            }

            carregarFuncionarios();

            // Inicializa a DataTable
            let tabela = $('#tabelaOS').DataTable(); 

            function carregarDados() {
                const filtros = {
                    dia: $('#filtroDia').val(),
                    mes: $('#filtroMes').val(),
                    ano: $('#filtroAno').val(),
                    status: $('#filtroStatus').val(),
                    situacao: $('#filtroSituacao').val(),
                    funcionario: $('#filtroFuncionario').val()
                };

                $.ajax({
                    url: 'buscar_os.php',
                    method: 'GET',
                    data: filtros,
                    success: function (data) {
                        tabela.destroy(); 
                        $('#tabelaOS tbody').html(data); 

                        // Reinicializa a DataTable com os mesmos recursos
                        tabela = $('#tabelaOS').DataTable({
                            pageLength: 10,
                            lengthChange: true,
                            searching: true,
                            ordering: true,
                            info: true,
                            autoWidth: false,
                        });
                    },
                    error: function (xhr, status, error) {
                        console.error('Erro ao carregar os dados:', error);
                    }
                });
            }

            // Eventos de mudança para cada filtro
            $('#filtroDia, #filtroMes, #filtroAno, #filtroStatus, #filtroSituacao, #filtroFuncionario').on('change keyup', function () {
                carregarDados();
            });
        });

        function gerarCores(quantidade) {
            const cores = [];
            for (let i = 0; i < quantidade; i++) {
                const cor = `hsl(${(i * 360) / quantidade}, 70%, 50%)`; // Gera cores dinâmicas
                cores.push(cor);
            }
            return cores;
        }

        function formatarMes(anoMes) {
            const meses = [
                'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
            ];

            const [ano, mes] = anoMes.split('-');
            return `${meses[parseInt(mes) - 1]} de ${ano}`; 
        }

        function formatarSemana(anoSemana) {
            const [ano, semana] = anoSemana.split('-');
            return `${semana}-${ano}`;
        }

        function carregarGraficos() {
            $.ajax({
                url: 'buscar_dados_graficos.php',
                method: 'GET',
                success: function (data) {
                    const { osMes, osSemana, faturamentoMes, faturamentoSemana, osFuncionario, faturamentoFuncionario } = data;

                    // Gráfico de Quantidade de OS por Mês
                    new Chart(document.getElementById('osPorMes'), {
                        type: 'bar',
                        data: {
                            labels: osMes.labels.map(formatarMes), // Aplica o formato desejado
                            datasets: [{
                                label: 'Quantidade de OS',
                                data: osMes.data,
                                backgroundColor: gerarCores(osMes.labels.length),
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Quantidade de O.S. por mês',
                                    font: {
                                        size: 18
                                    }
                                },
                                legend: {
                                    display: false,
                                    position: 'top'
                                }
                            }
                        }
                    });


                    // Gráfico de Quantidade de OS por Semana
                    new Chart(document.getElementById('osPorSemana'), {
                        type: 'line',
                        data: {
                            labels: osSemana.labels.map(formatarSemana), // Aplica o formato desejado
                            datasets: [{
                                label: 'Quantidade de OS',
                                data: osSemana.data,
                                borderColor: gerarCores(1)[0],
                                fill: false
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Quantidade de O.S. por semana',
                                    font: {
                                        size: 18
                                    }
                                },
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            }
                        }
                    });

                    // Gráfico de Faturamento por Mês
                    new Chart(document.getElementById('faturamentoPorMes'), {
                        type: 'bar',
                        data: {
                            labels: faturamentoMes.labels.map(formatarMes), // Aplica o formato desejado
                            datasets: [{
                                label: 'Faturamento (R$)',
                                data: faturamentoMes.data,
                                backgroundColor: gerarCores(faturamentoMes.labels.length),
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Faturamento por mês',
                                    font: {
                                        size: 18
                                    }
                                },
                                legend: {
                                    display: false,
                                    position: 'top'
                                }
                            }
                        }
                    });


                    // Gráfico de Faturamento por Semana
                    new Chart(document.getElementById('faturamentoPorSemana'), {
                        type: 'line',
                        data: {
                            labels: faturamentoSemana.labels.map(formatarSemana),
                            datasets: [{
                                label: 'Faturamento (R$)',
                                data: faturamentoSemana.data,
                                borderColor: gerarCores(1)[0],
                                fill: false
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Faturamento por semana',
                                    font: {
                                        size: 18
                                    }
                                },
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            }
                        }
                    });


                    // Gráfico de OS por Funcionário
                    new Chart(document.getElementById('osPorFuncionario'), {
                        type: 'doughnut',
                        data: {
                            labels: osFuncionario.labels,
                            datasets: [{
                                label: 'OS por Funcionário',
                                data: osFuncionario.data,
                                backgroundColor: gerarCores(osFuncionario.labels.length),
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Quantidade de O.S. por funcionário',
                                    font: {
                                        size: 18
                                    }
                                },
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            }
                        }
                    });

                    // Gráfico de Faturamento por Funcionário
                    new Chart(document.getElementById('faturamentoPorFuncionario'), {
                        type: 'line',
                        data: {
                            labels: faturamentoFuncionario.labels,
                            datasets: [{
                                label: 'Faturamento por Funcionário',
                                data: faturamentoFuncionario.data,
                                backgroundColor: gerarCores(faturamentoFuncionario.labels.length),
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Faturamento por funcionário',
                                    font: {
                                        size: 18
                                    }
                                },
                                legend: {
                                    display: false,
                                    position: 'top'
                                }
                            }
                        }
                    });
                },
                error: function (xhr, status, error) {
                    console.error('Erro ao carregar os gráficos:', error);
                }
            });
        }

        carregarGraficos();


    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>
