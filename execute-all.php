<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include_once 'update_atlas/atualizacao-full.php';
date_default_timezone_set('America/Sao_Paulo');

// Verificar o nível de acesso do usuário logado
$username = $_SESSION['username'];
$connAtlas = new mysqli("localhost", "root", "", "atlas");

// Consulta para verificar o nível de acesso e acesso adicional do usuário
$sql = "SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ?";
$stmt = $connAtlas->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$nivel_de_acesso = $user['nivel_de_acesso'];

// Verificar se o usuário tem acesso adicional a "Controle de Tarefas"
$acesso_adicional = $user['acesso_adicional'];
$acessos = array_map('trim', explode(',', $acesso_adicional));
$tem_acesso_controle_tarefas = in_array('Controle de Tarefas', $acessos);

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
    <script src="script/jquery-3.6.0.min.js"></script>
    <script src="script/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="style/css/jquery-ui.css">
    <style>

/* Cursor para indicar que os elementos são arrastáveis */
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
            margin-bottom: 5px;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            border: none;
        }
        .btn-info2:hover {
            color: #fff;
        }

        /* Estilos exclusivos para o modal com a classe modal-alerta */
        .modal-alerta .modal-content {
            border: 2px solid #dc3545; 
            background-color: #f8d7da; 
            color: #721c24; 
        }

        .modal-alerta .modal-header {
            background-color: #dc3545; 
            color: white; 
        }

        .modal-alerta .modal-body {
            font-weight: bold; 
        }

        .modal-alerta .modal-footer {
            background-color: #f5c6cb;
        }

        
        .modal-alerta .btn-close {
            background-color: white;
            border: 1px solid #dc3545;
        }

        .modal-alerta .btn-close:hover {
            background-color: #dc3545;
            color: white;
        }

        .modal-alerta .modal-footer .btn-secondary {
            background-color: #dc3545; 
            border: none;
        }

        .modal-alerta .modal-footer .btn-secondary:hover {
            background-color: #c82333; 
        }


        .status-label {
            display: inline-block;
            padding: 0.2em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 2;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25em;
            width: 100px;
        }

        .status-iniciada {
            background-color: #007bff;
        }

        .status-em-espera {
            background-color: #ffa500;
        }

        .status-em-andamento {
            background-color: #0056b3;
        }

        .status-concluida {
            background-color: #28a745;
        }

        .status-cancelada {
            background-color: #dc3545;
        }

        .status-pendente {
            background-color: gray;
        }

        .status-prestes-vencer {
            background-color: #ffc107; 
        }

        .status-vencida {
            background-color: #dc3545; 
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

        .modal-body ul {
            list-style-type: none;
            padding-left: 0;
        }

        .modal-body li {
            padding-left: 20px!important;
            padding: 10px 0; 
            border-bottom: 1px solid #ddd;
        }

        .modal-body h5 {
            /* margin-top: 20px;
            margin-bottom: 10px; */
            font-weight: bold;
        }

        .modal-dialog {
            max-width: 700px;
        }

        /* Prioridades */
        .priority-medium {
            background-color: #fff9c4 !important; 
            padding: 10px;
        }

        .priority-high {
            background-color: #ffe082 !important;
            padding: 10px;
        }

        .priority-critical {
            background-color: #ff8a80 !important;
            padding: 10px;
        }

        .row-quase-vencida {
            background-color: #ffebcc!important;
            padding: 10px;
        }

        .row-vencida {
            background-color: #ffcccc!important;
            padding: 10px;
        }

        body.dark-mode .priority-medium {
            background-color: #fff9c4 !important;
            color: #000!important;
        }

        body.dark-mode .priority-high {
            background-color: #ffe082 !important;
            color: #000!important;
        }

        body.dark-mode .priority-critical {
            background-color: #ff8a80 !important;
        }

        /* Modo escuro - Quase vencida e vencida */
        body.dark-mode .row-quase-vencida {
            background-color: #ffebcc!important; 
            color: #000!important;
        }

        body.dark-mode .row-vencida {
            background-color: #ffcccc!important; 
            color: #000!important;
        }

        /* Status das tarefas */
        .status-iniciada {
            background-color: #007bff;
            color: #fff;
        }

        .status-em-espera {
            background-color: #ffa500; 
            color: #fff;
        }

        .status-em-andamento {
            background-color: #0056b3;
            color: #fff;
        }

        .status-concluida {
            background-color: #28a745;
            color: #fff;
        }

        .status-cancelada {
            background-color: #dc3545; 
            color: #fff;
        }

        .status-pendente {
            background-color: gray;
            color: #fff;
        }

        .chart-container {
            position: relative;
            height: 240px;
        }
        .chart-container.full-height {
            height: 360px;
            margin-top: 30px;
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
    <div class="container mt-4">
        <h2 class="text-center mb-4">Central de Acesso</h2>

        <!-- Cards de Módulos -->
        <div id="sortable-cards" class="row">
            <!-- Arquivamentos -->
            <div class="col-md-4 mb-3" id="card-arquivamento">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-folder-open fa-3x text-primary mb-2"></i>
                        <h5 class="card-title">Arquivamentos</h5>
                        <a href="arquivamento/index.php" class="btn btn-primary w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Ordens de Serviço -->
            <div class="col-md-4 mb-3" id="card-os">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-money fa-3x text-info mb-2"></i>
                        <h5 class="card-title">Ordens de Serviço</h5>
                        <a href="os/index.php" class="btn btn-info2 w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Controle de Caixa -->
            <div class="col-md-4 mb-3" id="card-caixa">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-university fa-3x text-success mb-2"></i>
                        <h5 class="card-title">Controle de Caixa</h5>
                        <a href="caixa/index.php" class="btn btn-success w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Tarefas -->
            <div class="col-md-4 mb-3" id="card-tarefas">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-clock-o fa-3x text-secondary mb-2"></i>
                        <h5 class="card-title">Tarefas</h5>
                        <a href="tarefas/index.php" class="btn btn-secondary w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Ofícios -->
            <div class="col-md-4 mb-3" id="card-oficios">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-file-pdf-o fa-3x text-warning mb-2"></i>
                        <h5 class="card-title">Ofícios</h5>
                        <a href="oficios/index.php" class="btn btn-warning w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Provimentos e Resoluções -->
            <div class="col-md-4 mb-3" id="card-provimento">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-balance-scale fa-3x text-6 mb-2"></i>
                        <h5 class="card-title">Provimento e Resoluções</h5>
                        <a href="provimentos/index.php" class="btn btn-6 w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Guia de Recebimento -->
            <div class="col-md-4 mb-3" id="card-guia">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-file-text fa-3x text-4 mb-2"></i>
                        <h5 class="card-title">Guia de Recebimento</h5>
                        <a href="guia_de_recebimento/index.php" class="btn btn-4 w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Controle de Contas a Pagar -->
            <div class="col-md-4 mb-3" id="card-contas">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-usd fa-3x text-5 mb-2"></i>
                        <h5 class="card-title">Controle de Contas a Pagar</h5>
                        <a href="contas_a_pagar/index.php" class="btn btn-5 w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Vídeos Tutoriais -->
            <div class="col-md-4 mb-3" id="card-manuais">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-file-video-o fa-3x text-tutoriais mb-2"></i>
                        <h5 class="card-title">Vídeos Tutoriais</h5>
                        <a href="manuais/index.php" class="btn btn-tutoriais w-100">Acessar</a>
                    </div>
                </div>
            </div>

             <!-- Indexador -->
             <?php
                $configFile = __DIR__ . '/indexador/config_indexador.json';
                if (file_exists($configFile)) {
                    $configData = json_decode(file_get_contents($configFile), true);
                    if (isset($configData['indexador_ativo']) && $configData['indexador_ativo'] === 'S') {
                        echo '
                            <div class="col-md-4 mb-3" id="card-indexador">
                                <div class="card shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fa fa-file-text-o fa-3x text-indexador mb-2"></i>
                                        <h5 class="card-title">Indexador</h5>
                                        <a href="indexador/index.php" class="btn btn-indexador w-100">Acessar</a>
                                    </div>
                                </div>
                            </div>';
                    }
                }
            ?>

        </div>
    </div>
</div>

<div class="modal fade" id="tarefasModal" tabindex="-1" aria-labelledby="tarefasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width: 70%;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 style="text-align: center;width: 100%;" class="modal-title" id="tarefasModalLabel">RESUMO DAS TAREFAS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <!-- Seção para novas tarefas -->
                <div id="novas-tarefas-section" style="display: none;">
                    <h5 class="text-success">NOVAS TAREFAS:</h5>
                    <div id="novas-tarefas-list">
                        <!-- As novas tarefas serão carregadas aqui via AJAX e exibidas em tabelas -->
                    </div>
                    <hr>
                </div>

                <!-- Seção para tarefas pendentes -->
                <h5 class="text-danger">TAREFAS PENDENTES:</h5>
                <div id="tarefas-list">
                    <!-- As tarefas pendentes serão carregadas aqui via AJAX e exibidas em tabelas -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-alerta" id="accessDeniedModal" tabindex="-1" aria-labelledby="accessDeniedLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 20%!important;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="accessDeniedLabel">Acesso Negado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Você não tem permissão para acessar esta página.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="script/bootstrap.min.js"></script>
<script src="script/jquery.mask.min.js"></script>
<script>
$(document).ready(function() {
    // Função para formatar a data no padrão brasileiro
    function formatarDataBrasileira(dataISO) {
        const data = new Date(dataISO);
        if (isNaN(data.getTime())) {
            return 'Data inválida';
        }
        const dia = String(data.getDate()).padStart(2, '0');
        const mes = String(data.getMonth() + 1).padStart(2, '0');
        const ano = data.getFullYear();
        const horas = String(data.getHours()).padStart(2, '0');
        const minutos = String(data.getMinutes()).padStart(2, '0');
        const segundos = String(data.getSeconds()).padStart(2, '0');
        return `${dia}/${mes}/${ano} ${horas}:${minutos}:${segundos}`;
    }

    // Função para adicionar classes de prioridade
    function getPriorityClass(priority) {
        if (priority === 'Média') {
            return 'priority-medium';
        } else if (priority === 'Alta') {
            return 'priority-high';
        } else if (priority === 'Crítica') {
            return 'priority-critical';
        }
        return '';
    }

    // Função para adicionar classes de status baseado no estado
    function getStatusClass(status_data) {
        if (status_data === 'Prestes a vencer') {
            return 'row-quase-vencida';
        } else if (status_data === 'Vencida') {
            return 'row-vencida';
        }
        return '';
    }

    // Função para limitar o texto a 60 caracteres
    function limitarTexto(texto, limite) {
        if (texto.length > limite) {
            return texto.substring(0, limite) + '...';
        }
        return texto;
    }

// Função para capitalizar o texto
function capitalize(text) {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

// Função para retornar a classe de status
function getStatusClassLabel(status) {
    switch (status.toLowerCase()) {
        case 'iniciada':
            return 'status-iniciada';
        case 'em espera':
            return 'status-em-espera';
        case 'em andamento':
            return 'status-em-andamento';
        case 'concluída':
            return 'status-concluida';
        case 'cancelada':
            return 'status-cancelada';
        case 'pendente':
            return 'status-pendente';
        default:
            return '';
    }
}

// Função para adicionar classes de status baseado no estado para o fundo e texto
function getStatusClassBackground(status_data) {
    switch (status_data) {
        case 'Prestes a vencer':
            return 'status-prestes-vencer';  // Classe para fundo e texto de "Prestes a vencer"
        case 'Vencida':
            return 'status-vencida';  // Classe para fundo e texto de "Vencida"
        default:
            return '';
    }
}

// Função para criar uma tabela HTML com as tarefas
function criarTabelaPorPrioridade(prioridade, tarefas) {
    let tabela = `
        <h6 class="text-${prioridade === 'Baixa' ? 'primary' : prioridade === 'Média' ? 'warning' : 'danger'}">
            <b style="margin-left: 2%">Tarefas - Prioridade ${prioridade}</b>
        </h6>
        <table class="table table-striped" style="zoom: 90%; margin-left: 2%; max-width: 96%;">
            <thead>
                <tr>
                    <th>Nº Prot.</th>
                    <th>Título</th>
                    <th>Descrição</th>
                    <th>Data Criação</th>
                    <th>Data Limite</th>
                    <th>Status</th>
                    <th>Situação</th>
                    <th style="text-align: center;">Visualizar</th>
                </tr>
            </thead>
            <tbody>
    `;

    // Adiciona as tarefas à tabela
    tarefas.forEach(tarefa => {
        const statusClassLabel = getStatusClassLabel(tarefa.status); // Obter a classe correta para o status
        const statusClassBackground = getStatusClassBackground(tarefa.status_data); // Classe para fundo e texto de situação
        
        tabela += `
            <tr class="${getPriorityClass(tarefa.nivel_de_prioridade)}">
                <td>${tarefa.id}</td>
                <td>${limitarTexto(tarefa.titulo, 60)}</td>
                <td>${limitarTexto(tarefa.descricao, 60)}</td>
                <td>${formatarDataBrasileira(tarefa.data_criacao)}</td>
                <td>${formatarDataBrasileira(tarefa.data_limite)}</td>
                <td>
                    <span class="status-label ${statusClassLabel}">${capitalize(tarefa.status)}</span>
                </td>
                <td>
                    ${tarefa.status_data ? `<span class="status-label ${statusClassBackground}">${tarefa.status_data}</span>` : ''}
                </td>
                <td style="text-align: center;">
                    <button class="btn btn-info btn-sm" style="margin-bottom: 0px; width: 40px; height: 30px;" onclick="window.location.href='tarefas/index_tarefa.php?token=${tarefa.token}'">
                        <i class="fa fa-eye" aria-hidden="true"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tabela += `
            </tbody>
        </table>
    `;

    return tabela;
}

    // Carregar as tarefas pendentes ao carregar a página
    $.ajax({
        url: 'verificar_tarefas.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            var tarefasList = $('#tarefas-list');
            var novasTarefasList = $('#novas-tarefas-list');
            tarefasList.empty();
            novasTarefasList.empty();

            var totalTarefas = 0; // Contador para tarefas diferentes de "Concluída" ou "Cancelada"

            // Exibir as novas tarefas agrupadas por funcionário e prioridade
            $.each(response.novas_tarefas, function(funcionario, tarefasFuncionario) {
                $('#novas-tarefas-section').show();
                novasTarefasList.append(`<h6><b>Tarefas de ${funcionario}:</b></h6>`);
                
                const novasTarefasCritica = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Crítica');
                const novasTarefasAlta = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Alta');
                const novasTarefasMedia = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Média');
                const novasTarefasBaixa = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Baixa');

                // Adicionar as tabelas por prioridade nas novas tarefas
                if (novasTarefasCritica.length > 0) {
                    novasTarefasList.append(criarTabelaPorPrioridade('Crítica', novasTarefasCritica));
                }
                if (novasTarefasAlta.length > 0) {
                    novasTarefasList.append(criarTabelaPorPrioridade('Alta', novasTarefasAlta));
                }
                if (novasTarefasMedia.length > 0) {
                    novasTarefasList.append(criarTabelaPorPrioridade('Média', novasTarefasMedia));
                }
                if (novasTarefasBaixa.length > 0) {
                    novasTarefasList.append(criarTabelaPorPrioridade('Baixa', novasTarefasBaixa));
                }

                totalTarefas += tarefasFuncionario.length; // Contabilizar novas tarefas
            });

            // Exibir as tarefas pendentes agrupadas por funcionário e prioridade
            $.each(response.tarefas, function(funcionario, tarefasFuncionario) {
                tarefasList.append(`<h6><b>Tarefas de ${funcionario}:</b></h6>`);
                
                const tarefasCritica = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Crítica');
                const tarefasAlta = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Alta');
                const tarefasMedia = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Média');
                const tarefasBaixa = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Baixa');

                // Adicionar as tabelas por prioridade nas tarefas pendentes
                if (tarefasCritica.length > 0) {
                    tarefasList.append(criarTabelaPorPrioridade('Crítica', tarefasCritica));
                }
                if (tarefasAlta.length > 0) {
                    tarefasList.append(criarTabelaPorPrioridade('Alta', tarefasAlta));
                }
                if (tarefasMedia.length > 0) {
                    tarefasList.append(criarTabelaPorPrioridade('Média', tarefasMedia));
                }
                if (tarefasBaixa.length > 0) {
                    tarefasList.append(criarTabelaPorPrioridade('Baixa', tarefasBaixa));
                }

                totalTarefas += tarefasFuncionario.length; // Contabilizar tarefas pendentes
            });

            // Verificar se existem tarefas. Se houver, abrir o modal.
            if (totalTarefas > 0) {
                $('#tarefasModal').modal('show');
            } else {
                console.log('Nenhuma tarefa pendente ou nova para exibir.');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar as tarefas:', error);
            console.log(xhr.responseText);
        }
    });
});

    $(document).ready(function() {
          // Função para alternar modos claro e escuro
        $('.mode-switch').on('click', function() {
            var body = $('body');
            body.toggleClass('dark-mode light-mode');

            // Atualizar cores das legendas dos gráficos
            Chart.helpers.each(Chart.instances, function(instance) {
                instance.options.plugins.legend.labels.color = getFontColor();
                instance.options.scales.x.ticks.color = getFontColor();
                instance.options.scales.y.ticks.color = getFontColor();
                instance.update();
            });
        });

        // Função para fechar a notificação
        $('.notification .close-btn').on('click', function() {
            $(this).parent().hide();
        });
    });

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
include(__DIR__ . '/rodape.php');
?>

<script src="script/popper.min.js"></script>
<script src="script/bootstrap2.min.js"></script>

</body>
</html>