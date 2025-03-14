<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  
include(__DIR__ . '/checar_acesso_de_administrador.php');  
date_default_timezone_set('America/Sao_Paulo');  

// Função para obter estatísticas (otimizada com cache)  
function obterEstatisticas($conn) {  
    $cache_file = __DIR__ . '/cache/estatisticas_os.json';  
    $cache_time = 300; // 5 minutos de cache  

    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {  
        return json_decode(file_get_contents($cache_file), true);  
    }  

    // Consulta única e otimizada para todas as estatísticas  
    $query = "  
        SELECT  
            (SELECT COUNT(*) FROM ordens_de_servico) AS total_os,  
            (SELECT COUNT(*) FROM ordens_de_servico WHERE status = 'Cancelado') AS canceladas,  
            (SELECT COUNT(DISTINCT ordem_de_servico_id) FROM pagamento_os) AS os_pagas,  
            (  
                SELECT COUNT(DISTINCT os.id)  
                FROM ordens_de_servico os  
                LEFT JOIN pagamento_os po ON os.id = po.ordem_de_servico_id  
                WHERE po.ordem_de_servico_id IS NULL AND os.status != 'Cancelado'  
            ) AS os_pendentes_pagamento  
    ";  

    $result = $conn->query($query);  
    $stats = $result->fetch_assoc();  

    // Consulta para status de liquidação  
    $status_query = "  
        SELECT  
            ordem_servico_id,  
            SUM(CASE WHEN status = 'liquidado' THEN 1 ELSE 0 END) AS total_liquidado,  
            SUM(CASE WHEN status = 'parcialmente liquidado' THEN 1 ELSE 0 END) AS total_parcial,  
            SUM(CASE WHEN status IS NULL OR status = '' OR status = 'pendente' THEN 1 ELSE 0 END) AS total_pendente,  
            COUNT(*) AS total_itens  
        FROM ordens_de_servico_itens  
        GROUP BY ordem_servico_id  
    ";  

    $result = $conn->query($status_query);  

    $liquidadas = 0;  
    $parcialmente_liquidadas = 0;  
    $pendentes = 0;  

    while ($row = $result->fetch_assoc()) {  
        if ($row['total_liquidado'] == $row['total_itens']) {  
            $liquidadas++;  
        } elseif ($row['total_parcial'] > 0 || ($row['total_liquidado'] > 0 && $row['total_pendente'] > 0)) {  
            $parcialmente_liquidadas++;  
        } elseif ($row['total_pendente'] == $row['total_itens']) {  
            $pendentes++;  
        }  
    }  

    $stats['liquidadas'] = $liquidadas;  
    $stats['parcialmente_liquidadas'] = $parcialmente_liquidadas;  
    $stats['pendentes'] = $pendentes;  

    // Salvar cache  
    if (!is_dir(__DIR__ . '/cache')) {  
        mkdir(__DIR__ . '/cache', 0755, true);  
    }  
    file_put_contents($cache_file, json_encode($stats));  

    return $stats;  
}  

// Obter estatísticas iniciais (sem filtro)  
$stats = obterEstatisticas($conn);  
?>  

<!DOCTYPE html>  
<html lang="pt-br">  

<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Relatório de Ordens de Serviço</title>  
    <link rel="icon" href="../style/img/favicon.png" type="image/png">  

    <!-- CSS Dependencies -->  
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">  
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">  
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">  
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">  
    <link rel="stylesheet" href="../style/css/style.css">  
    <?php include(__DIR__ . '/style.php'); ?>
    
</head>  

<body class="light-mode">  
    <!-- Loading Overlay -->  
    <div id="loadingOverlay">  
        <div class="spinner"></div>  
    </div>  

    <?php include(__DIR__ . '/../menu.php'); ?>  

    <div id="main" class="main-content">  
        <div class="container-fluid px-4">  
            <div class="container" style="margin-bottom: 20px;">  
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">  
                <h3 class="mb-3 mb-md-0">Relatório O.S e Depósito Prévio</h3>  
                <button onclick="imprimir()" class="btn btn-primary">  
                    <i class="fa fa-print" aria-hidden="true"></i> Livro de Depósito Prévio  
                </button>  
            </div>  

            <!-- Dashboard Cards -->  
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 mb-4">  
                <div class="col">  
                    <div class="card card-dashboard bg-blue">  
                        <div class="card-body">  
                            <h5 class="card-title">Total de O.S.</h5>  
                            <div class="card-value" id="cardTotalOS"><?= number_format($stats['total_os'], 0, ',', '.') ?></div>  
                            <div class="card-icon"><i class="fa fa-file-text"></i></div>  
                        </div>  
                    </div>  
                </div>  
                <div class="col">  
                    <div class="card card-dashboard bg-green">  
                        <div class="card-body">  
                            <h5 class="card-title">Total de O.S. Pagas</h5>  
                            <div class="card-value" id="cardTotalOSPagas"><?= number_format($stats['os_pagas'], 0, ',', '.') ?></div>  
                            <div class="card-icon"><i class="fa fa-check-circle"></i></div>  
                        </div>  
                    </div>  
                </div>  
                <div class="col">  
                    <div class="card card-dashboard bg-teal">  
                        <div class="card-body">  
                            <h5 class="card-title">Total Liquidadas</h5>  
                            <div class="card-value" id="cardTotalLiquidadas"><?= number_format($stats['liquidadas'], 0, ',', '.') ?></div>  
                            <div class="card-icon"><i class="fa fa-check-square"></i></div>  
                        </div>  
                    </div>  
                </div>  
                <div class="col">  
                    <div class="card card-dashboard bg-orange">  
                        <div class="card-body">  
                            <h5 class="card-title">Parcialmente Liquidadas</h5>  
                            <div class="card-value" id="cardTotalParcLiquidadas"><?= number_format($stats['parcialmente_liquidadas'], 0, ',', '.') ?></div>  
                            <div class="card-icon"><i class="fa fa-adjust"></i></div>  
                        </div>  
                    </div>  
                </div>  
                <div class="col">  
                    <div class="card card-dashboard bg-red">  
                        <div class="card-body">  
                            <h5 class="card-title">Canceladas</h5>  
                            <div class="card-value" id="cardTotalCanceladas"><?= number_format($stats['canceladas'], 0, ',', '.') ?></div>  
                            <div class="card-icon"><i class="fa fa-ban"></i></div>  
                        </div>  
                    </div>  
                </div>  
                <div class="col">  
                    <div class="card card-dashboard bg-purple">  
                        <div class="card-body">  
                            <h5 class="card-title">Pendentes de Liquidação</h5>  
                            <div class="card-value" id="cardTotalPendentes"><?= number_format($stats['pendentes'], 0, ',', '.') ?></div>  
                            <div class="card-icon"><i class="fa fa-clock-o"></i></div>  
                        </div>  
                    </div>  
                </div>  
                <div class="col">  
                    <div class="card card-dashboard bg-pink">  
                        <div class="card-body">  
                            <h5 class="card-title">Pendentes de Pagamento</h5>  
                            <div class="card-value" id="cardTotalPendPgto"><?= number_format($stats['os_pendentes_pagamento'], 0, ',', '.') ?></div>  
                            <div class="card-icon"><i class="fa fa-exclamation-circle"></i></div>  
                        </div>  
                    </div>  
                </div>  
            </div>  
            </div> 

            <!-- Filtros -->  
            <div class="filter-container">  
                <h5 class="filter-title"><i class="fa fa-filter"></i> Filtros</h5>  
                <div class="row g-3 align-items-end">  
                    <div class="col-md-3">  
                        <label for="dateRange" class="form-label filter-label">  
                            <i class="fa fa-calendar"></i> Período  
                        </label>  
                        <input type="text" class="form-control" id="dateRange" placeholder="Selecione um período">  
                        <input type="hidden" id="dataInicial">  
                        <input type="hidden" id="dataFinal">  
                    </div>  
                    <div class="col-md-3">  
                        <label for="filtroStatus" class="form-label filter-label">  
                            <i class="fa fa-tasks"></i> Status  
                        </label>  
                        <select id="filtroStatus" class="form-control form-select">  
                            <option value="">Todos os status</option>  
                            <option value="Pendente de Liquidação">Pendente de Liquidação</option>  
                            <option value="Liquidada">Liquidada</option>  
                            <option value="Parcialmente Liquidada">Parcialmente Liquidada</option>  
                            <option value="Cancelada">Cancelada</option>  
                        </select>  
                    </div>  
                    <div class="col-md-3">  
                        <label for="filtroSituacao" class="form-label filter-label">  
                            <i class="fa fa-check-circle-o"></i> Situação  
                        </label>  
                        <select id="filtroSituacao" class="form-control form-select">  
                            <option value="">Todas as situações</option>  
                            <option value="Cancelada">Cancelada</option>  
                            <option value="Paga">Paga</option>  
                            <option value="Pendente de Pagamento">Pendente de Pagamento</option>  
                        </select>  
                    </div>  
                    <div class="col-md-3">  
                        <label for="filtroFuncionario" class="form-label filter-label">  
                            <i class="fa fa-user"></i> Funcionário  
                        </label>  
                        <select id="filtroFuncionario" class="form-control form-select">  
                            <option value="">Todos os funcionários</option>  
                        </select>  
                    </div>  
                    <div class="col-12 text-end mt-3">  
                        <button id="btnFiltrar" class="btn btn-primary me-2">  
                            <i class="fa fa-search"></i> Aplicar Filtros  
                        </button>  
                        <button id="btnLimparFiltros" class="btn btn-outline-secondary">  
                            <i class="fa fa-refresh"></i> Limpar Filtros  
                        </button>  
                    </div>  
                </div>  
            </div>  

            <!-- Tabela de OS -->  
            <div class="table-responsive">  
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
                        <!-- Dados carregados via AJAX -->  
                    </tbody>  
                </table>  
            </div>  

            <!-- Gráficos com sistema de abas -->  
            <div class="chart-tabs-container">  
                <ul class="nav nav-tabs" id="chartsTab" role="tablist">  
                    <li class="nav-item" role="presentation">  
                        <a class="nav-link active" id="os-tab" data-bs-toggle="tab" href="#os-charts" role="tab" aria-controls="os-charts" aria-selected="true">  
                            <i class="fa fa-file-text"></i> Ordens de Serviço  
                        </a>  
                    </li>  
                    <li class="nav-item" role="presentation">  
                        <a class="nav-link" id="faturamento-tab" data-bs-toggle="tab" href="#faturamento-charts" role="tab" aria-controls="faturamento-charts" aria-selected="false">  
                            <i class="fa fa-money"></i> Faturamento  
                        </a>  
                    </li>  
                    <li class="nav-item" role="presentation">  
                        <a class="nav-link" id="funcionario-tab" data-bs-toggle="tab" href="#funcionario-charts" role="tab" aria-controls="funcionario-charts" aria-selected="false">  
                            <i class="fa fa-users"></i> Por Funcionário  
                        </a>  
                    </li>  
                </ul>  
                
                <div class="tab-content p-3" id="chartsTabContent">  
                    <!-- Tab: Ordens de Serviço -->  
                    <div class="tab-pane fade show active" id="os-charts" role="tabpanel" aria-labelledby="os-tab">  
                        <div class="row">  
                            <div class="col-md-6">  
                                <div class="chart-container">  
                                    <h5 class="chart-title">Quantidade de O.S. por mês</h5>  
                                    <canvas id="osPorMes"></canvas>  
                                </div>  
                            </div>  
                            <div class="col-md-6">  
                                <div class="chart-container">  
                                    <h5 class="chart-title">Quantidade de O.S. por semana</h5>  
                                    <canvas id="osPorSemana"></canvas>  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                    
                    <!-- Tab: Faturamento -->  
                    <div class="tab-pane fade" id="faturamento-charts" role="tabpanel" aria-labelledby="faturamento-tab">  
                        <div class="row">  
                            <div class="col-md-6">  
                                <div class="chart-container">  
                                    <h5 class="chart-title">Faturamento por mês</h5>  
                                    <canvas id="faturamentoPorMes"></canvas>  
                                </div>  
                            </div>  
                            <div class="col-md-6">  
                                <div class="chart-container">  
                                    <h5 class="chart-title">Faturamento por semana</h5>  
                                    <canvas id="faturamentoPorSemana"></canvas>  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                    
                    <!-- Tab: Por Funcionário -->  
                    <div class="tab-pane fade" id="funcionario-charts" role="tabpanel" aria-labelledby="funcionario-tab">  
                        <div class="row">  
                            <div class="col-md-6">  
                                <div class="chart-container">  
                                    <h5 class="chart-title">Quantidade de O.S. por funcionário</h5>  
                                    <canvas id="osPorFuncionario"></canvas>  
                                </div>  
                            </div>  
                            <div class="col-md-6">  
                                <div class="chart-container">  
                                    <h5 class="chart-title">Faturamento por funcionário</h5>  
                                    <canvas id="faturamentoPorFuncionario"></canvas>  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  

    <!-- Scripts -->  
    <script src="../script/jquery-3.5.1.min.js"></script>  
    <script src="../script/bootstrap.bundle.min.js"></script>  
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>  
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>  
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>  
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>  
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>  
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>  
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>  
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/moment/moment.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>  
    <script src="../script/chart.js"></script>  

    <script>  
        $(document).ready(function() {  
            // Inicializar DateRangePicker  
            $('#dateRange').daterangepicker({  
                opens: 'left',  
                autoUpdateInput: false,  
                locale: {  
                    format: 'DD/MM/YYYY',  
                    applyLabel: 'Aplicar',  
                    cancelLabel: 'Cancelar',  
                    fromLabel: 'De',  
                    toLabel: 'Até',  
                    customRangeLabel: 'Período Customizado',  
                    weekLabel: 'S',  
                    daysOfWeek: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],  
                    monthNames: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',  
                                 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],  
                },  
                ranges: {  
                    'Hoje': [moment(), moment()],  
                    'Ontem': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],  
                    'Últimos 7 dias': [moment().subtract(6, 'days'), moment()],  
                    'Últimos 30 dias': [moment().subtract(29, 'days'), moment()],  
                    'Este mês': [moment().startOf('month'), moment().endOf('month')],  
                    'Mês passado': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]  
                }  
            });  

            $('#dateRange').on('apply.daterangepicker', function(ev, picker) {  
                $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));  
                $('#dataInicial').val(picker.startDate.format('YYYY-MM-DD'));  
                $('#dataFinal').val(picker.endDate.format('YYYY-MM-DD'));  
            });  

            $('#dateRange').on('cancel.daterangepicker', function() {  
                $(this).val('');  
                $('#dataInicial').val('');  
                $('#dataFinal').val('');  
            });  

            // Carregar lista de funcionários  
            $.ajax({  
                url: 'buscar_funcionarios.php',  
                method: 'GET',  
                dataType: 'json',  
                success: function(data) {  
                    const select = $('#filtroFuncionario');  
                    if (Array.isArray(data)) {  
                        data.forEach(funcionario => {  
                            // Mostra o nome em caixa alta no dropdown, mas mantém o valor original  
                            select.append(`<option value="${funcionario}">${funcionario.toUpperCase()}</option>`);  
                        });  
                    }  
                },  
                error: function(xhr, status, error) {  
                    console.error('Erro ao carregar funcionários:', error);  
                }  
            });  
 
            // Inicializar DataTable (server-side)  
            const tabela = $('#tabelaOS').DataTable({  
                processing: true,  
                serverSide: true,  
                responsive: true,  
                ajax: {  
                    url: 'buscar_os_server_side.php',  
                    type: 'POST',  
                    data: function(d) {  
                        d.data_inicial = $('#dataInicial').val();  
                        d.data_final = $('#dataFinal').val();  
                        d.status = $('#filtroStatus').val();  
                        d.situacao = $('#filtroSituacao').val();  
                        d.funcionario = $('#filtroFuncionario').val();  
                    }  
                },  
                columns: [  
                    { data: 'id' },  
                    { data: 'cliente' },  
                    { data: 'cpf_cliente' },  
                    { data: 'total_os' },  
                    { data: 'data_criacao' },  
                    {   
                        data: 'criado_por',  
                        render: function(data) {  
                            return data ? data.toUpperCase() : '';  
                        }  
                    },  
                    {  
                        data: 'situacao',  
                        render: function(data) {  
                            let badgeClass = '';  
                            switch(data) {  
                                case 'Paga':  
                                    badgeClass = 'bg-success';  
                                    break;  
                                case 'Pendente de Pagamento':  
                                    badgeClass = 'bg-warning';  
                                    break;  
                                case 'Cancelada':  
                                    badgeClass = 'bg-danger';  
                                    break;  
                                default:  
                                    badgeClass = 'bg-secondary';  
                            }  
                            return `<span class="badge ${badgeClass} badge-status">${data}</span>`;  
                        }  
                    },  
                    {  
                        data: 'status',  
                        render: function(data) {  
                            let badgeClass = '';  
                            switch(data) {  
                                case 'Liquidada':  
                                    badgeClass = 'bg-success';  
                                    break;  
                                case 'Parcialmente Liquidada':  
                                    badgeClass = 'bg-info';  
                                    break;  
                                case 'Pendente de Liquidação':  
                                    badgeClass = 'bg-warning';  
                                    break;  
                                case 'Cancelada':  
                                    badgeClass = 'bg-danger';  
                                    break;  
                                default:  
                                    badgeClass = 'bg-secondary';  
                            }  
                            return `<span class="badge ${badgeClass} badge-status">${data}</span>`;  
                        }  
                    },  
                    { data: 'deposito_previo' },  
                    { data: 'atos_praticados' }  
                ],   
                dom: '<"row g-0 mb-2"<"col-md-6"B><"col-md-6 d-flex justify-content-end"f>><"row g-0"<"col-12"l>>rt<"row"<"col-sm-6"i><"col-sm-6 text-end"p>>',  
                buttons: [  
                    {  
                        extend: 'excel',  
                        text: '<i class="fa fa-file-excel-o"></i> Excel',  
                        className: 'btn btn-success btn-sm',  
                        exportOptions: {  
                            columns: ':visible'  
                        }  
                    },  
                    {  
                        extend: 'pdf',  
                        text: '<i class="fa fa-file-pdf-o"></i> PDF',  
                        className: 'btn btn-danger btn-sm',  
                        exportOptions: {  
                            columns: ':visible'  
                        }  
                    },  
                    {  
                        extend: 'print',  
                        text: '<i class="fa fa-print"></i> Imprimir',  
                        className: 'btn btn-info2 btn-sm',  
                        exportOptions: {  
                            columns: ':visible'  
                        }  
                    }  
                ],  
                language: {  
                    processing: "Processando...",  
                    search: "Pesquisar:",  
                    lengthMenu: "Mostrar _MENU_ registros por página",  
                    info: "Mostrando _START_ até _END_ de _TOTAL_ registros",  
                    infoEmpty: "Mostrando 0 até 0 de 0 registros",  
                    infoFiltered: "(filtrado de _MAX_ registros no total)",  
                    infoPostFix: "",  
                    loadingRecords: "Carregando registros...",  
                    zeroRecords: "Nenhum registro encontrado",  
                    emptyTable: "Nenhum registro disponível",  
                    paginate: {  
                        first: "Primeiro",  
                        previous: "Anterior",  
                        next: "Próximo",  
                        last: "Último"  
                    },  
                    aria: {  
                        sortAscending: ": ative para ordenar a coluna em ordem crescente",  
                        sortDescending: ": ative para ordenar a coluna em ordem decrescente"  
                    }  
                },  
                  
                fnInfoCallback: function(settings, start, end, max, total, pre) {  
                    var formattedTotal = total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");  
                    var formattedMax = max.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");  
                    
                    if (max != total) {  
                        return "Mostrando " + start + " até " + end + " de " + formattedTotal +   
                            " registros (filtrado de " + formattedMax + " registros no total)";  
                    }  
                    return "Mostrando " + start + " até " + end + " de " + formattedTotal + " registros";  
                },  
                order: [[0, 'desc']],  
                stateSave: true,  
                drawCallback: function() {    
                    $('#loadingOverlay').fadeOut(300);  
                }  
            });  

            // Ao clicar em "Aplicar Filtros"  
            $('#btnFiltrar').on('click', function() {  
                $('#loadingOverlay').fadeIn(300);  
                tabela.ajax.reload();  
                carregarGraficos();  
                atualizarEstatisticas(); 
            });  

            // Ao clicar em "Limpar Filtros"  
            $('#btnLimparFiltros').on('click', function() {  
                $('#dataInicial').val('');  
                $('#dataFinal').val('');  
                $('#dateRange').val('');  
                $('#filtroStatus').val('');  
                $('#filtroSituacao').val('');  
                $('#filtroFuncionario').val('');  

                $('#loadingOverlay').fadeIn(300);  
                tabela.ajax.reload();  
                carregarGraficos();  
                atualizarEstatisticas(); // Atualiza os cards para valores originais  
            });  

            // Função para animar contadores  
            function animarContador(elemento, valorInicial, valorFinal, duracao) {  
                const intervalo = 16; // 60fps  
                const passos = Math.ceil(duracao / intervalo);  
                const incremento = (valorFinal - valorInicial) / passos;  
                let atual = valorInicial;  
                let contador = 0;  

                const timer = setInterval(() => {  
                    contador++;  
                    atual += incremento;  
                    
                    if (contador >= passos) {  
                        clearInterval(timer);  
                        atual = valorFinal;  
                    }  
                    
                    $(elemento).text(Math.floor(atual).toLocaleString());  
                }, intervalo);  
            }  

            // Função para atualizar estatísticas nos cards  
            function atualizarEstatisticas() {  
                const filtros = {  
                    data_inicial: $('#dataInicial').val(),  
                    data_final: $('#dataFinal').val(),  
                    status: $('#filtroStatus').val(),  
                    situacao: $('#filtroSituacao').val(),  
                    funcionario: $('#filtroFuncionario').val()  
                };  

                $.ajax({  
                    url: 'buscar_estatisticas.php',  
                    method: 'GET',  
                    data: filtros,  
                    dataType: 'json',  
                    success: function(data) {  
                        // Obter valores atuais para animação  
                        const valorAtualTotalOS = parseInt($('#cardTotalOS').text().replace(/[^\d]/g, '')) || 0;  
                        const valorAtualOSPagas = parseInt($('#cardTotalOSPagas').text().replace(/[^\d]/g, '')) || 0;  
                        const valorAtualLiquidadas = parseInt($('#cardTotalLiquidadas').text().replace(/[^\d]/g, '')) || 0;  
                        const valorAtualParcLiquidadas = parseInt($('#cardTotalParcLiquidadas').text().replace(/[^\d]/g, '')) || 0;  
                        const valorAtualCanceladas = parseInt($('#cardTotalCanceladas').text().replace(/[^\d]/g, '')) || 0;  
                        const valorAtualPendentes = parseInt($('#cardTotalPendentes').text().replace(/[^\d]/g, '')) || 0;  
                        const valorAtualPendPgto = parseInt($('#cardTotalPendPgto').text().replace(/[^\d]/g, '')) || 0;  

                        // Animação de contadores  
                        animarContador('#cardTotalOS', valorAtualTotalOS, data.total_os || 0, 500);  
                        animarContador('#cardTotalOSPagas', valorAtualOSPagas, data.os_pagas || 0, 500);  
                        animarContador('#cardTotalLiquidadas', valorAtualLiquidadas, data.liquidadas || 0, 500);  
                        animarContador('#cardTotalParcLiquidadas', valorAtualParcLiquidadas, data.parcialmente_liquidadas || 0, 500);  
                        animarContador('#cardTotalCanceladas', valorAtualCanceladas, data.canceladas || 0, 500);  
                        animarContador('#cardTotalPendentes', valorAtualPendentes, data.pendentes || 0, 500);  
                        animarContador('#cardTotalPendPgto', valorAtualPendPgto, data.os_pendentes_pagamento || 0, 500);  
                    },  
                    error: function(xhr, status, error) {  
                        console.error('Erro ao carregar estatísticas filtradas:', error);  
                    }  
                });  
            }  

            // Variáveis para armazenar instâncias de gráficos  
            let chartInstances = {};  

            // Função para gerar cores dinâmicas  
            function gerarCores(quantidade) {  
                const cores = [];  
                for (let i = 0; i < quantidade; i++) {  
                    const cor = `hsl(${(i * 360) / quantidade}, 70%, 50%)`;  
                    cores.push(cor);  
                }  
                return cores;  
            }  

            // Formatação de rótulos  
            function formatarMes(anoMes) {  
                const meses = [  
                    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',  
                    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'  
                ];  
                const [ano, mes] = anoMes.split('-');  
                return `${meses[parseInt(mes) - 1]}/${ano}`;  
            }  
            function formatarSemana(anoSemana) {  
                const [ano, semana] = anoSemana.split('-');  
                return `Sem ${semana}/${ano}`;  
            }  

            // Função para criar ou atualizar gráficos  
            function atualizarGrafico(id, titulo, tipo, dados, formatarLabel = (label) => label) {  
                // Remove mensagem de "sem dados" caso exista  
                $(`#${id}`).parent().find('.text-center.text-muted').remove();  

                // Destrói o gráfico anterior se existir  
                if (chartInstances[id]) {  
                    chartInstances[id].destroy();  
                }  

                // Caso não haja dados  
                if (!dados || !dados.labels || !dados.data || dados.labels.length === 0) {  
                    $(`#${id}`).parent().append('<div class="text-center text-muted">Sem dados disponíveis</div>');  
                    return;  
                }  

                const ctx = document.getElementById(id).getContext('2d');  
                const cores = gerarCores(dados.labels.length);  

                // Processamento especial para gráficos de funcionários - converter para caixa alta  
                let formatador = formatarLabel;  
                if (id === 'osPorFuncionario' || id === 'faturamentoPorFuncionario') {  
                    formatador = label => formatarLabel(label).toUpperCase();  
                }  

                let options = {  
                    responsive: true,  
                    maintainAspectRatio: false,  
                    plugins: {  
                        legend: {  
                            position: 'top',  
                            labels: {  
                                boxWidth: 12,  
                                padding: 10,  
                                font: {  
                                    size: 12  
                                }  
                            }  
                        },  
                        tooltip: {  
                            backgroundColor: 'rgba(0,0,0,0.8)',  
                            titleFont: {  
                                size: 14  
                            },  
                            bodyFont: {  
                                size: 13  
                            },  
                            padding: 10,  
                            cornerRadius: 5,  
                            displayColors: true  
                        }  
                    }  
                };  

                // Configurações específicas para diferentes tipos de gráficos  
                if (tipo === 'line') {  
                    options.tension = 0.3;    // Linhas mais curvas  
                    options.pointRadius = 4; // Tamanho dos pontos  
                    options.pointHoverRadius = 6; // Tamanho ao passar o mouse  
                }  
                if (tipo === 'doughnut' || tipo === 'pie') {  
                    options.cutout = '50%'; // Para gráficos doughnut  
                    options.plugins.legend.position = 'right';  
                }  

                chartInstances[id] = new Chart(ctx, {  
                    type: tipo,  
                    data: {  
                        labels: dados.labels.map(formatador),  
                        datasets: [{  
                            label: titulo,  
                            data: dados.data,  
                            backgroundColor: tipo === 'line' ? cores[0] : cores,  
                            borderColor: tipo === 'line' ? cores[0] : cores,  
                            fill: tipo === 'line' ? false : true,  
                            borderWidth: tipo === 'line' ? 2 : 1  
                        }]  
                    },  
                    options: options  
                });  
            } 

            // Função para carregar gráficos  
            function carregarGraficos() {  
                const filtros = {  
                    data_inicial: $('#dataInicial').val(),  
                    data_final: $('#dataFinal').val(),  
                    status: $('#filtroStatus').val(),  
                    situacao: $('#filtroSituacao').val(),  
                    funcionario: $('#filtroFuncionario').val()  
                };  

                $.ajax({  
                    url: 'buscar_dados_graficos.php',  
                    method: 'GET',  
                    data: filtros,  
                    dataType: 'json',  
                    success: function(data) {  
                        atualizarGrafico('osPorMes', 'Quantidade de O.S. por mês', 'bar', data.osMes, formatarMes);  
                        atualizarGrafico('osPorSemana', 'Quantidade de O.S. por semana', 'line', data.osSemana, formatarSemana);  
                        atualizarGrafico('faturamentoPorMes', 'Faturamento por mês', 'bar', data.faturamentoMes, formatarMes);  
                        atualizarGrafico('faturamentoPorSemana', 'Faturamento por semana', 'line', data.faturamentoSemana, formatarSemana);  
                        atualizarGrafico('osPorFuncionario', 'Quantidade de O.S. por funcionário', 'doughnut', data.osFuncionario);  
                        atualizarGrafico('faturamentoPorFuncionario', 'Faturamento por funcionário', 'bar', data.faturamentoFuncionario);  
                    },  
                    error: function(xhr, status, error) {  
                        console.error('Erro ao carregar os gráficos:', error);  
                    },  
                    complete: function() {  
                        $('#loadingOverlay').fadeOut(300);  
                    }  
                });  
            }  

            // Ativar funcionalidade das abas  
            $(document).on('click', 'a[data-bs-toggle="tab"]', function(e) {  
                e.preventDefault();  
                $(this).tab('show');  
                
                // Redimensionar gráficos quando a aba é ativada  
                setTimeout(function() {  
                    Object.values(chartInstances).forEach(chart => {  
                        if (chart) chart.resize();  
                    });  
                }, 50);  
            });  

            // Inicializar gráficos após carregar a tabela  
            tabela.on('draw', function() {  
                setTimeout(carregarGraficos, 100);  
            });  

            // Função para imprimir livro de depósito prévio  
            function imprimir() {  
                const timestamp = new Date().getTime();  

                fetch(`../style/configuracao.json?nocache=${timestamp}`)  
                    .then(response => response.json())  
                    .then(data => {  
                        const url = data.timbrado === 'S'  
                            ? `livro_dep_previo.php`  
                            : `livro-dep-previo.php`;  

                        window.open(url, '_blank');  
                    })  
                    .catch(error => {  
                        console.error('Erro ao carregar configuração:', error);  
                    });  
            }  

            // Expor função ao escopo global  
            window.imprimir = imprimir;  
        });  
    </script>  

    <?php include(__DIR__ . '/../rodape.php'); ?>  
</body>  
</html>