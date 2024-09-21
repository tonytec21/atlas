<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include_once 'update_atlas/atualizacao.php';
date_default_timezone_set('America/Sao_Paulo');

// Verificar o nível de acesso do usuário logado
$username = $_SESSION['username'];
$connAtlas = new mysqli("localhost", "root", "", "atlas");

// Consulta para verificar o nível de acesso do usuário
$sql = "SELECT nivel_de_acesso FROM funcionarios WHERE usuario = ?";
$stmt = $connAtlas->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$nivel_de_acesso = $user['nivel_de_acesso'];

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

        .btn-4 {
            background: #34495e;
            color: #fff;
        }
        .btn-4:hover {
            background: #2c3e50;
            color: #fff;
        }

        .btn-5 {
            background: #ff8a80;
            color: #fff;
        }
        .btn-5:hover {
            background: #e3786f;
            color: #fff;
        }
        .btn-6 {
            background: #427b8e;
            color: #fff;
        }
        .btn-6:hover {
            background: #366879;
            color: #fff;
        }


        /* Estilos exclusivos para o modal com a classe modal-alerta */
        .modal-alerta .modal-content {
            border: 2px solid #dc3545; /* Borda vermelha */
            background-color: #f8d7da; /* Fundo rosa claro */
            color: #721c24; /* Texto vermelho escuro */
        }

        .modal-alerta .modal-header {
            background-color: #dc3545; /* Cabeçalho vermelho */
            color: white; /* Texto branco no cabeçalho */
        }

        .modal-alerta .modal-body {
            font-weight: bold; /* Texto da mensagem em negrito */
        }

        .modal-alerta .modal-footer {
            background-color: #f5c6cb; /* Fundo do rodapé em tom claro */
        }

        /* Estilo do botão de fechar */
        .modal-alerta .btn-close {
            background-color: white;
            border: 1px solid #dc3545; /* Borda vermelha */
        }

        .modal-alerta .btn-close:hover {
            background-color: #dc3545; /* Cor ao passar o mouse */
            color: white;
        }

        /* Estilo do botão "Fechar" */
        .modal-alerta .modal-footer .btn-secondary {
            background-color: #dc3545; /* Botão vermelho */
            border: none;
        }

        .modal-alerta .modal-footer .btn-secondary:hover {
            background-color: #c82333; /* Tom mais escuro ao passar o mouse */
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
            background-color: #ffc107; /* Cor amarelada para "Prestes a vencer" */
        }

        .status-vencida {
            background-color: #dc3545; /* Cor avermelhada para "Vencida" */
        }

        /* Remover a borda de foco no botão de fechar */
        .btn-close {
            outline: none; /* Remove a borda ao clicar */
            border: none; /* Remove qualquer borda padrão */
            background: none; /* Remove o fundo padrão */
            padding: 0; /* Remove o espaçamento extra */
            font-size: 1.5rem; /* Ajuste o tamanho do ícone */
            cursor: pointer; /* Mostra o ponteiro de clique */
            transition: transform 0.2s ease; /* Suaviza a transição do hover */
        }

        /* Aumentar o tamanho do botão em 5% no hover */
        .btn-close:hover {
            transform: scale(2.10); /* Aumenta 5% */
        }

        /* Opcional: Adicionar foco suave sem borda visível */
        .btn-close:focus {
            outline: none; /* Remove a borda ao foco */
        }

         /* Remover marcadores de lista */
        .modal-body ul {
            list-style-type: none; /* Remove os marcadores de lista */
            padding-left: 0; /* Remove o padding padrão */
        }

        /* Recuo personalizado para os itens da lista */
        .modal-body li {
            padding-left: 20px!important; /* Recuo da lista */
            padding: 10px 0; /* Adiciona espaço vertical */
            border-bottom: 1px solid #ddd; /* Linha separadora */
        }

        /* Estilo dos títulos de funcionários */
        .modal-body h5 {
            /* margin-top: 20px;
            margin-bottom: 10px; */
            font-weight: bold;
        }

        /* Exemplo de ajuste no modal para torná-lo mais largo */
        .modal-dialog {
            max-width: 700px; /* Aumenta a largura do modal */
        }

        /* Prioridades */
        .priority-medium {
            background-color: #fff9c4 !important; /* Amarelo claro */
            padding: 10px;
        }

        .priority-high {
            background-color: #ffe082 !important; /* Laranja claro */
            padding: 10px;
        }

        .priority-critical {
            background-color: #ff8a80 !important; /* Vermelho claro */
            padding: 10px;
        }

        /* Tarefas quase vencidas e vencidas */
        .row-quase-vencida {
            background-color: #ffebcc!important; /* Amarelo claro */
            padding: 10px;
        }

        .row-vencida {
            background-color: #ffcccc!important; /* Vermelho claro */
            padding: 10px;
        }

        /* Modo escuro - Prioridades */
        body.dark-mode .priority-medium {
            background-color: #fff9c4 !important; /* Amarelo claro */
            color: #000!important;
        }

        body.dark-mode .priority-high {
            background-color: #ffe082 !important; /* Laranja claro */
            color: #000!important;
        }

        body.dark-mode .priority-critical {
            background-color: #ff8a80 !important; /* Vermelho claro */
        }

        /* Modo escuro - Quase vencida e vencida */
        body.dark-mode .row-quase-vencida {
            background-color: #ffebcc!important; /* Amarelo claro */
            color: #000!important;
        }

        body.dark-mode .row-vencida {
            background-color: #ffcccc!important; /* Vermelho claro */
            color: #000!important;
        }

        /* Status das tarefas */
        .status-iniciada {
            background-color: #007bff; /* Azul */
            color: #fff;
        }

        .status-em-espera {
            background-color: #ffa500; /* Laranja */
            color: #fff;
        }

        .status-em-andamento {
            background-color: #0056b3; /* Azul escuro */
            color: #fff;
        }

        .status-concluida {
            background-color: #28a745; /* Verde */
            color: #fff;
        }

        .status-cancelada {
            background-color: #dc3545; /* Vermelho */
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
                <a href="os/index.php" class="btn btn-info2 w-100"><i class="fa fa-money" aria-hidden="true"></i> Ordens de Serviço</a>
            </div>
            <div class="col-md-4">
                <a href="caixa/index.php" class="btn btn-success w-100"><i class="fa fa-university" aria-hidden="true"></i></i> Controle de Caixa</a>
            </div>
            <div class="col-md-4">
                <a href="tarefas/index.php" class="btn btn-secondary w-100"><i class="fa fa-clock-o" aria-hidden="true"></i> Tarefas</a>
            </div>
            <div class="col-md-4">
                <a href="oficios/index.php" class="btn btn-oficio w-100"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Ofícios</a>
            </div>
            <div class="col-md-4">
                <a href="provimentos/index.php" class="btn btn-assinador w-100"><i class="fa fa-balance-scale" aria-hidden="true"></i> Provimentos e Resoluções</a>
            </div>
            <div class="col-md-4">
                <a href="guia_de_recebimento/index.php" class="btn btn-4 w-100"><i class="fa fa-file-text" aria-hidden="true"></i> Guia de Recebimento</a>
            </div>
            <!-- Botão e Pop-up -->
            <div class="col-md-4">
                <?php if ($nivel_de_acesso === 'administrador') : ?>
                    <!-- Se for administrador, o botão é clicável -->
                    <a href="contas_a_pagar/index.php" class="btn btn-5 w-100"><i class="fa fa-usd" aria-hidden="true"></i> Controle de Contas a Pagar</a>
                <?php else : ?>
                    <!-- Se não for administrador, exibe um botão que chama o modal -->
                    <button class="btn btn-5 w-100" data-bs-toggle="modal" data-bs-target="#accessDeniedModal"><i class="fa fa-usd" aria-hidden="true"></i> Controle de Contas a Pagar</button>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <a href="manuais/index.php" class="btn btn-6 w-100"><i class="fa fa-file-video-o" aria-hidden="true"></i> Vídeos Tutoriais</a>
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


<script src="script/jquery-3.5.1.min.js"></script>
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
</script>


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

        // Carregar dados do dashboard
        $.ajax({
            url: 'load_dashboard_data.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                $('#totalAcervos').text(data.totalAtos);
                $('#novosCadastros').text(data.novosCadastros);
                $('#atosExcluidos').text(data.atosExcluidos);

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

    // window.onload = function() {
    //     clearCache();
    // };
</script>
<br><br><br>
<?php
include(__DIR__ . '/rodape.php');
?>

<script src="script/popper.min.js"></script>
<script src="script/bootstrap2.min.js"></script>


</body>
</html>
