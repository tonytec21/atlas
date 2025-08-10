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

<div class="main-container">  
    <h1 class="page-title"></h1>  
    <div class="title-divider"></div>  
        
    <div class="search-container">  
        <input type="text" class="search-box" id="searchModules" placeholder="Buscar módulos...">  
    </div>  
        
    <div id="sortable-cards">  
        <!-- Arquivamentos -->  
        <div class="module-card" id="card-arquivamento">  
            <div class="card-header">  
                <span class="card-badge badge-documental">Documental</span>  
                <div class="card-icon icon-arquivamento">  
                    <i class="fa fa-folder-open"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Arquivamentos</h3>  
            <p class="card-description">Controle de arquivamentos com rastreabilidade.</p>  
            <button class="card-button btn-arquivamento" onclick="window.location.href='arquivamento/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Ordens de Serviço -->  
        <div class="module-card" id="card-os">  
            <div class="card-header">  
                <span class="card-badge badge-financeiro">Financeiro</span>  
                <div class="card-icon icon-os">  
                    <i class="fa fa-money"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Ordens de Serviço</h3>  
            <p class="card-description">Crie e gerencie ordens de serviço (O.S).</p>  
            <button class="card-button btn-os" onclick="window.location.href='os/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Controle de Caixa -->  
        <div class="module-card" id="card-caixa">  
            <div class="card-header">  
                <span class="card-badge badge-financeiro">Financeiro</span>  
                <div class="card-icon icon-caixa">  
                    <i class="fa fa-university"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Controle de Caixa</h3>  
            <p class="card-description">Monitore entradas e saídas financeiras.</p>  
            <button class="card-button btn-caixa" onclick="window.location.href='caixa/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Tarefas -->  
        <div class="module-card" id="card-tarefas">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-tarefas">  
                    <i class="fa fa-clock-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Tarefas</h3>  
            <p class="card-description">Organize atividades com priorização e prazos.</p>  
            <button class="card-button btn-tarefas" onclick="window.location.href='tarefas/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Ofícios -->  
        <div class="module-card" id="card-oficios">  
            <div class="card-header">  
                <span class="card-badge badge-documental">Documental</span>  
                <div class="card-icon icon-oficios">  
                    <i class="fa fa-file-pdf-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Ofícios</h3>  
            <p class="card-description">Elabore e controle ofícios com numeração.</p>  
            <button class="card-button btn-warning" onclick="window.location.href='oficios/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  

        <!-- Devolutiva -->  
        <div class="module-card" id="card-notas-devolutivas">  
            <div class="card-header">  
                <span class="card-badge badge-documental">Documental</span>  
                <div class="card-icon icon-devolutivas">  
                    <i class="fa fa-reply-all"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Nota Devolutiva</h3>  
            <p class="card-description">Elabore e controle notas devolutivas.</p>  
            <button class="card-button btn-devolutivas" onclick="window.location.href='nota_devolutiva/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Provimentos e Resoluções -->  
        <div class="module-card" id="card-provimento">  
            <div class="card-header">  
                <span class="card-badge badge-juridico">Jurídico</span>  
                <div class="card-icon icon-provimentos">  
                    <i class="fa fa-balance-scale"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Provimentos</h3>  
            <p class="card-description">Acesse normas e provimentos e resoluções.</p>  
            <button class="card-button btn-provimentos" onclick="window.location.href='provimentos/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Guia de Recebimento -->  
        <div class="module-card" id="card-guia">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-guia">  
                    <i class="fa fa-file-text"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Guia de Recebimento</h3>  
            <p class="card-description">Controle de documentos recebidos.</p>  
            <button class="card-button btn-guia" onclick="window.location.href='guia_de_recebimento/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  

        <!-- Agenda -->  
        <div class="module-card" id="card-agenda">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-agenda">  
                    <i class="fa fa-calendar-check-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Agenda de Serviços</h3>  
            <p class="card-description">Controle e agenda de serviços.</p>  
            <button class="card-button btn-agenda" onclick="window.location.href='agendamento/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Controle de Contas a Pagar -->  
        <div class="module-card" id="card-contas">  
            <div class="card-header">  
                <span class="card-badge badge-financeiro">Financeiro</span>  
                <div class="card-icon icon-contas">  
                    <i class="fa fa-usd"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Contas a Pagar</h3>  
            <p class="card-description">Gerencie contas e controle vencimentos.</p>  
            <button class="card-button btn-contas" onclick="window.location.href='contas_a_pagar/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Vídeos Tutoriais -->  
        <div class="module-card" id="card-manuais">  
            <div class="card-header">  
                <span class="card-badge badge-administrativo">Administrativo</span>  
                <div class="card-icon icon-manuais">  
                    <i class="fa fa-file-video-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Vídeos Tutoriais</h3>  
            <p class="card-description">Acesse vídeos sobre operações do sistema.</p>  
            <button class="card-button btn-manuais" onclick="window.location.href='manuais/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Indexador -->  
        <?php  
            $configFile = __DIR__ . '/indexador/config_indexador.json';  
            if (file_exists($configFile)) {  
                $configData = json_decode(file_get_contents($configFile), true);  
                if (isset($configData['indexador_ativo']) && $configData['indexador_ativo'] === 'S') {  
                    echo '  
                        <div class="module-card" id="card-indexador">  
                            <div class="card-header">  
                                <span class="card-badge badge-documental">Documental</span>  
                                <div class="card-icon icon-indexador">  
                                    <i class="fa fa-file-text-o"></i>  
                                </div>  
                            </div>  
                            <h3 class="card-title">Indexador</h3>  
                            <p class="card-description">Indexe e localize documentos por conteúdo.</p>  
                            <button class="card-button btn-indexador" onclick="window.location.href=\'indexador/index.php\'">  
                                <i class="fa fa-arrow-right"></i> Acessar  
                            </button>  
                        </div>';  
                }  
            }  
        ?>  
        
        <!-- XUXUZINHO -->  
        <?php  
            $configFile = __DIR__ . '/indexador/config_xuxuzinho.json';  
            if (file_exists($configFile)) {  
                $configData = json_decode(file_get_contents($configFile), true);  
                if (isset($configData['indexador_ativo']) && $configData['indexador_ativo'] === 'S') {  
                    echo '  
                        <div class="module-card" id="card-xuxuzinho">  
                            <div class="card-header">  
                                <span class="card-badge badge-administrativo">Administrativo</span>  
                                <div class="card-icon icon-xuxuzinho">  
                                    <img src="../xuxuzinho/images/favicon.png" alt="Ícone Xuxuzinho" style="width: 40px; height: 40px;">  
                                </div>  
                            </div>  
                            <h3 class="card-title">Xuxuzinho</h3>  
                            <p class="card-description">Subsistema para controle de selos e comunicações.</p>  
                            <button class="card-button btn-xuxuzinho" onclick="window.open(\'../xuxuzinho/index.php\', \'_blank\')">  
                                <i class="fa fa-arrow-right"></i> Acessar  
                            </button>
  
                        </div>';  
                }  
            }  
        ?>  

        
        <!-- Anotações -->  
        <div class="module-card" id="card-anotacao">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-anotacao">  
                    <i class="fa fa-sticky-note-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Anotações</h3>  
            <p class="card-description">Crie e organize anotações e lembretes.</p>  
            <button class="card-button btn-anotacao" onclick="window.location.href='suas_notas/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Relatórios -->  
        <div class="module-card" id="card-relatorios">  
            <div class="card-header">  
                <span class="card-badge badge-administrativo">Administrativo</span>  
                <div class="card-icon icon-relatorios">  
                    <i class="fa fa-line-chart"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Relatórios e Livros</h3>  
            <p class="card-description">Acesse relatórios e visualize registros.</p>  
            <button class="card-button btn-relatorios" onclick="window.location.href='relatorios/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
    </div>  
</div>  

<!-- Modal de Tarefas -->  
<div class="modal fade" id="tarefasModal" tabindex="-1" aria-labelledby="tarefasModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-lg">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="tarefasModalLabel">Resumo de Tarefas</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>  
            </div>  
            <div class="modal-body">  
                <!-- Seção para novas tarefas -->  
                <div id="novas-tarefas-section" style="display: none;">  
                    <h6 class="section-title text-success">  
                        <i class="fa fa-plus-circle"></i> Novas Tarefas  
                    </h6>  
                    <div id="novas-tarefas-list" class="task-list-container"></div>  
                </div>  
                
                <!-- Divisor -->  
                <hr class="my-4">  
                
                <!-- Seção para tarefas pendentes -->  
                <div>  
                    <h6 class="section-title text-primary">  
                        <i class="fa fa-tasks"></i> Tarefas Pendentes  
                    </h6>  
                    <div id="tarefas-list" class="task-list-container"></div>  
                </div>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- Modal de Acesso Negado -->  
<div class="modal fade" id="accessDeniedModal" tabindex="-1" aria-labelledby="accessDeniedLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered">  
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


<!-- Modal – Tarefa Recorrente Obrigatória (FULLSCREEN) -->
<div class="modal fade" id="recorrenteModal"
     tabindex="-1" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-fullscreen">
    <form id="formCumprirRecorrente" class="modal-content modal-alert-recorrente">
      <div class="modal-body d-flex flex-column justify-content-center align-items-center text-center">
        
        <div class="icone-alerta mb-4">
          <i class="fa fa-exclamation-triangle"></i>
        </div>

        <h2 class="titulo-alerta mb-3">ATENÇÃO! TAREFA OBRIGATÓRIA</h2>
        <p id="recorrenteDescricao" class="lead mb-4"></p>

        <div class="opcoes-status mb-3">
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="optCumprida" value="cumprida" checked>
            <label class="form-check-label fw-bold" for="optCumprida">Cumprida</label>
          </div>
          <div class="form-check form-check-inline ms-4">
            <input class="form-check-input" type="radio" name="status" id="optNaoCumprida" value="nao_cumprida">
            <label class="form-check-label fw-bold" for="optNaoCumprida">Não Cumprida</label>
          </div>
        </div>

        <div class="w-100" style="max-width:600px;">
          <textarea name="justificativa" id="campoJustificativa"
                    class="form-control d-none"
                    rows="4"
                    placeholder="Explique o motivo de NÃO ter cumprido a tarefa (obrigatório)."></textarea>
        </div>

        <input type="hidden" name="exec_id" id="exec_id">

        <div class="mt-5 d-flex gap-3">
          <button type="submit" class="btn btn-light btn-lg fw-bold px-5">
            CONFIRMAR
          </button>
        </div>

        <small class="mt-4 texto-bloqueio">
          Você não poderá acessar o sistema enquanto não confirmar esta tarefa.
        </small>
      </div>
    </form>
  </div>
</div>



<script src="script/jquery-ui.min.js"></script>  
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
        return `${dia}/${mes}/${ano} ${horas}:${minutos}`;  
    }  

    // Função para limitar o texto  
    function limitarTexto(texto, limite) {  
        if (texto.length > limite) {  
            return texto.substring(0, limite) + '...';  
        }  
        return texto;  
    }  

    // Função para capitalizar texto  
    function capitalize(text) {  
        return text.charAt(0).toUpperCase() + text.slice(1);  
    }  

    // Mapeia status para classes de badge (pastéis)
    function getStatusBadgeClass(status) {
        switch ((status || '').toLowerCase()) {
            case 'iniciada':      return 'soft-badge soft-blue';
            case 'em espera':     return 'soft-badge soft-amber';
            case 'em andamento':  return 'soft-badge soft-indigo';
            case 'concluída':
            case 'concluida':     return 'soft-badge soft-green';
            case 'cancelada':     return 'soft-badge soft-rose';
            case 'pendente':      return 'soft-badge soft-slate';
            default:              return 'soft-badge soft-slate';
        }
    }

    // Mapeia situação (prazo) para classes de badge (pastéis)
    function getSituacaoBadgeClass(situacao) {
        switch ((situacao || '').toLowerCase()) {
            case 'prestes a vencer': return 'soft-badge soft-orange';
            case 'vencida':          return 'soft-badge soft-red';
            default:                 return 'soft-badge soft-slate';
        }
    }

    // Classe da linha para destaque de situação
    function getRowClassBySituacao(situacao) {
        switch ((situacao || '').toLowerCase()) {
            case 'prestes a vencer': return 'row-quase-vencida';
            case 'vencida':          return 'row-vencida';
            default:                 return '';
        }
    }

    // Função para criar tabelas HTML com as tarefas (usada em Novas e Pendentes)  
    function criarTabelaPorPrioridade(prioridade, tarefas) {  
        let tabela = `  
            <h6 class="mb-3 mt-4">Prioridade: ${prioridade}</h6>  
            <div class="table-responsive">  
                <table class="table table-hover align-middle">  
                    <thead>  
                        <tr>  
                            <th>ID</th>  
                            <th>Título</th>  
                            <th>Data Limite</th>  
                            <th>Status</th>  
                            <th>Situação</th>  
                            <th></th>  
                        </tr>  
                    </thead>  
                    <tbody>  
        `;  

        // Adiciona as tarefas à tabela  
        tarefas.forEach(tarefa => {  
            const statusClass = getStatusBadgeClass(tarefa.status);  
            // Aceita 'status_data' OU 'situacao' (alguns endpoints podem enviar um dos dois)
            const situacaoTexto = (tarefa.status_data || tarefa.situacao || '').trim();
            const situacaoClass = situacaoTexto ? getSituacaoBadgeClass(situacaoTexto) : '';
            const rowHighlight  = getRowClassBySituacao(situacaoTexto);

            tabela += `  
                <tr class="${rowHighlight}">  
                    <td>${tarefa.id}</td>  
                    <td>${limitarTexto(tarefa.titulo, 70)}</td>  
                    <td>${formatarDataBrasileira(tarefa.data_limite)}</td>  
                    <td><span class="${statusClass}">${capitalize(tarefa.status || '') || '-'}</span></td>  
                    <td>${situacaoTexto ? `<span class="${situacaoClass}">${situacaoTexto}</span>` : '-'}</td>  
                    <td class="text-end">  
                        <button class="btn btn-sm btn-info" title="Ver tarefa" onclick="window.location.href='tarefas/index_tarefa.php?token=${tarefa.token}'">  
                            <i class="fa fa-eye"></i>  
                        </button>  
                    </td>  
                </tr>  
            `;  
        });  

        tabela += `  
                    </tbody>  
                </table>  
            </div>  
        `;  

        return tabela;  
    }  

    // Busca de módulos  
    $("#searchModules").on("keyup", function() {  
        var value = $(this).val().toLowerCase();  
        $("#sortable-cards .module-card").filter(function() {  
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)  
        });  
    });  

    // Inicializa o sortable para os cards  
    $("#sortable-cards").sortable({  
        placeholder: "ui-state-highlight",  
        handle: ".card-header",  
        cursor: "move",  
        update: function(event, ui) {  
            saveCardOrder();  
        }  
    });  

    // Função para salvar a ordem dos cards  
    function saveCardOrder() {  
        let order = [];  
        $("#sortable-cards .module-card").each(function() {  
            order.push($(this).attr('id'));  
        });  

        $.ajax({  
            url: 'save_order.php',  
            type: 'POST',  
            data: { order: order },  
            success: function(response) {  
                console.log('Ordem salva com sucesso!');  
            },  
            error: function(xhr, status, error) {  
                console.error('Erro ao salvar a ordem:', error);  
            }  
        });  
    }  

    // Carrega a ordem dos cards  
    function loadCardOrder() {  
        $.ajax({  
            url: 'load_order.php',  
            type: 'GET',  
            dataType: 'json',  
            success: function(data) {  
                if (data && data.order) {  
                    $.each(data.order, function(index, cardId) {  
                        $("#" + cardId).appendTo("#sortable-cards");  
                    });  
                }  
            },  
            error: function(xhr, status, error) {  
                console.error('Erro ao carregar a ordem:', error);  
            }  
        });  
    }  

    // Carrega a ordem ao iniciar a página  
    loadCardOrder();  

    // Carregar as tarefas pendentes e novas  
    $.ajax({  
        url: 'verificar_tarefas.php',  
        method: 'GET',  
        dataType: 'json',  
        success: function(response) {  
            var tarefasList = $('#tarefas-list');  
            var novasTarefasList = $('#novas-tarefas-list');  
            tarefasList.empty();  
            novasTarefasList.empty();  

            var totalTarefas = 0;  

            // Exibir as novas tarefas (com situação corrigida)  
            $.each(response.novas_tarefas, function(funcionario, tarefasFuncionario) {  
                $('#novas-tarefas-section').show();  
                novasTarefasList.append(`<h6 class="fw-bold">${funcionario}</h6>`);  
                
                const novasTarefasCritica = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Crítica');  
                const novasTarefasAlta = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Alta');  
                const novasTarefasMedia = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Média');  
                const novasTarefasBaixa = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Baixa');  

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

                totalTarefas += tarefasFuncionario.length;  
            });  

            // Exibir as tarefas pendentes  
            $.each(response.tarefas, function(funcionario, tarefasFuncionario) {  
                tarefasList.append(`<h6 class="fw-bold">${funcionario}</h6>`);  
                
                const tarefasCritica = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Crítica');  
                const tarefasAlta = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Alta');  
                const tarefasMedia = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Média');  
                const tarefasBaixa = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Baixa');  

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

                totalTarefas += tarefasFuncionario.length;  
            });  

            // Mostrar o modal se houver tarefas  
            if (totalTarefas > 0) {  
                $('#tarefasModal').modal('show');  
            }  
        },  
        error: function(xhr, status, error) {  
            console.error('Erro ao carregar as tarefas:', error);  
        }  
    });  

    // Alternar modo claro/escuro (a classe no <body> é atualizada pelo menu também)
    $('.mode-switch').on('click', function() {  
        $('body').toggleClass('dark-mode light-mode');  
    });  


/* ------------------------------------------------------------------
   Verifica tarefas recorrentes que devem aparecer agora
------------------------------------------------------------------*/
$(function () {

  const $modal              = $('#recorrenteModal');
  const $form               = $('#formCumprirRecorrente');
  const $btnAdiar           = $('#btnAdiar');        // botão “Adiar” (não-obrigatória)
  const $campoJustificativa = $('#campoJustificativa');
  const $optCumprida        = $('#optCumprida');
  const $optNaoCumprida     = $('#optNaoCumprida');

  /* --------- carrega uma tarefa pendente --------- */
  $.getJSON('verificar_recorrentes.php', resp => {
      if (!resp || !resp.length) return;

      const t = resp[0];
      $('#recorrenteDescricao').text(`${t.titulo} – ${t.descricao || ''}`);
      $('#exec_id').val(t.exec_id);

      /* mostra ou oculta botão ADIAR */
      if (parseInt(t.obrigatoria, 10) === 0) {
          $btnAdiar.removeClass('d-none');
      } else {
          $btnAdiar.addClass('d-none');
      }

      /* reseta campos */
      $optCumprida.prop('checked', true).trigger('change');
      $campoJustificativa.val('');
      $('#inputStatusAdiar').remove();        // hidden que criamos ao adiar

      /* abre modal (bloqueante) */
      $modal.modal({ backdrop: 'static', keyboard: false }).modal('show');
  });

  /* ------------------------------------------------------------------
     Mostrar / ocultar justificativa
  ------------------------------------------------------------------*/
  $optNaoCumprida.on('change', function () {
      $campoJustificativa
        .toggleClass('d-none', !this.checked)
        .prop('required', this.checked);
  });
  $optCumprida.on('change', function () {
      if (this.checked) {
          $campoJustificativa.addClass('d-none')
                             .prop('required', false)
                             .val('');
      }
  });

  /* ------------------------------------------------------------------
     Botão ADIAR (só para tarefas não-obrigatórias)
  ------------------------------------------------------------------*/
  $btnAdiar.on('click', function () {
      /* cria (ou atualiza) campo hidden com status = adiada */
      let $hidden = $('#inputStatusAdiar');
      if (!$hidden.length) {
          $hidden = $('<input>', {
              type: 'hidden',
              id:   'inputStatusAdiar',
              name: 'status'
          }).appendTo($form);
      }
      $hidden.val('adiada');

      /* desabilita rádios para não enviar valores duplicados */
      $optCumprida.prop('disabled', true);
      $optNaoCumprida.prop('disabled', true);

      $form.submit();
  });

  /* ------------------------------------------------------------------
     Enviar confirmação (cumprida / não cumprida / adiada)
  ------------------------------------------------------------------*/
  $form.on('submit', function (e) {
      e.preventDefault();

      $.post('cumprir_recorrente.php', $(this).serialize(), () => {
          $modal.modal('hide');

          /* limpa/rehabilita para próxima vez */
          $('#inputStatusAdiar').remove();
          $optCumprida.prop('disabled', false);
          $optNaoCumprida.prop('disabled', false);
      });
  });

});


});  
</script>  

<?php include(__DIR__ . '/rodape.php'); ?>  
</body>  
</html>
