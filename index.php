<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include_once 'update_atlas/atualizacao.php';
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
    <title>Atlas - Central de Acesso</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/font-awesome.min.css">
    <link rel="stylesheet" href="style/css/style.css">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <?php include(__DIR__ . '/style/style_index.php'); ?>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/menu.php'); ?>

<div id="main" class="main-content">
    <div class="container mt-4">
        <h2 class="page-title">Central de Acesso</h2>

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

            <!-- Nota Devolutiva -->
            <div class="col-md-4 mb-3" id="card-notas-devolutivas">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-reply-all fa-3x text-devolutivas mb-2"></i>
                        <h5 class="card-title">Nota Devolutiva</h5>
                        <a href="nota_devolutiva/index.php" class="btn btn-devolutivas w-100">Acessar</a>
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

            <!-- Xuxuzinho -->
            <?php
                $configFile = __DIR__ . '/indexador/config_xuxuzinho.json';
                if (file_exists($configFile)) {
                    $configData = json_decode(file_get_contents($configFile), true);
                    if (isset($configData['indexador_ativo']) && $configData['indexador_ativo'] === 'S') {
                        echo '
                              
                            <div class="col-md-4 mb-3" id="card-xuxuzinho">  
                                <div class="card shadow-sm">  
                                    <div class="card-body text-center">  
                                        <img src="../xuxuzinho/images/favicon.png" alt="Xuxuzinho" class="mb-2" style="height: 60px; width: auto;">  
                                        <h5 class="card-title">Xuxuzinho</h5>  
                                        <a href="../xuxuzinho/index.php" class="btn btn-success w-100">Acessar</a>  
                                    </div>  
                                </div>  
                            </div>';
                    }
                }
            ?>

            <!-- REURB -->
            <?php
                $configFile = __DIR__ . '/reurb/config_reurb.json';
                if (file_exists($configFile)) {
                    $configData = json_decode(file_get_contents($configFile), true);
                    if (isset($configData['reurb_ativo']) && $configData['reurb_ativo'] === 'S') {
                        echo '
                            <div class="col-md-4 mb-3" id="card-reurb">
                                <div class="card shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fa fa-map fa-3x text-reurb mb-2"></i>
                                        <h5 class="card-title">REURB</h5>
                                        <a href="reurb/index.php" class="btn btn-reurb w-100">Acessar</a>
                                    </div>
                                </div>
                            </div>';
                    }
                }
            ?>

            <!-- Anotações -->
            <div class="col-md-4 mb-3" id="card-anotacoes">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-sticky-note-o fa-3x text-anotacoes mb-2"></i>
                        <h5 class="card-title">Anotações</h5>
                        <a href="suas_notas/index.php" class="btn btn-anotacoes w-100">Acessar</a>
                    </div>
                </div>
            </div>

            <!-- Relatórios -->
            <div class="col-md-4 mb-3" id="card-relatorios">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <i class="fa fa-line-chart fa-3x text-relatorios mb-2"></i>
                        <h5 class="card-title">Relatórios e Livros</h5>
                        <a href="relatorios/index.php" class="btn btn-relatorios w-100">Acessar</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="tarefasModal" tabindex="-1" aria-labelledby="tarefasModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-lg" style="max-width: 70%;">  
        <div class="modal-content">  
            <div class="modal-header border-0 bg-light">  
                <div class="modal-title-wrapper w-100 text-center">  
                    <h4 class="modal-title fw-bold" id="tarefasModalLabel">  
                        <i class="fas fa-tasks me-2"></i>  
                        Resumo das Tarefas  
                    </h4>  
                    <div class="modal-subtitle text-muted small mt-1">  
                        Visualize e gerencie suas tarefas de forma eficiente  
                    </div>  
                </div>  
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>  
            </div>  
            
            <div class="modal-body px-4 py-4">  
                <!-- Seção para novas tarefas -->  
                <div id="novas-tarefas-section" class="task-section mb-4" style="display: none;">  
                    <div class="section-header d-flex align-items-center mb-3">  
                        <div class="section-icon me-2">  
                            <i class="fas fa-plus-circle text-success" style="padding-right: 7px;"></i>  
                        </div>  
                        <h5 class="section-title text-success mb-0 fw-bold">Novas Tarefas</h5>  
                    </div>  
                    <div id="novas-tarefas-list" class="task-list-container">  
                        <!-- As novas tarefas serão carregadas aqui via AJAX -->  
                    </div>  
                </div>  

                <!-- Divisor elegante -->  
                <div class="task-divider my-4" style="display: none;">  
                    <hr class="divider-line">  
                </div>  

                <!-- Seção para tarefas pendentes -->  
                <div class="task-section">  
                    <div class="section-header d-flex align-items-center mb-3">  
                        <div class="section-icon me-2">  
                            <i class="fas fa-clock text-danger" style="padding-right: 7px;"></i>  
                        </div>  
                        <h5 class="section-title text-danger mb-0 fw-bold">Tarefas Pendentes</h5>  
                    </div>  
                    <div id="tarefas-list" class="task-list-container">  
                        <!-- As tarefas pendentes serão carregadas aqui via AJAX -->  
                    </div>  
                </div>  
            </div>  

            <div class="modal-footer border-0 bg-light">  
                <button type="button" class="btn btn-secondary px-4 py-2" data-bs-dismiss="modal">  
                    <i class="fas fa-times me-2"></i>Fechar  
                </button>  
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
        case 'aguardando retirada':
            return 'status-aguardando-retirada';
        case 'aguardando pagamento':
            return 'status-aguardando-pagamento';
        case 'prazo de edital':
            return 'status-prazo-de-edital';
        case 'exigência cumprida':
            return 'status-exigencia-cumprida';
        case 'finalizado sem prática do ato':
            return 'status-finalizado-sem-pratica-do-ato';
        default:
            return '';
    }
}

// Função para adicionar classes de status baseado no estado para o fundo e texto
function getStatusClassBackground(status_data) {
    switch (status_data) {
        case 'Prestes a vencer':
            return 'status-prestes-vencer';
        case 'Vencida':
            return 'status-vencida';
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
        const statusClassLabel = getStatusClassLabel(tarefa.status); 
        const statusClassBackground = getStatusClassBackground(tarefa.status_data); 
        
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
