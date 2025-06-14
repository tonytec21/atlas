<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Pesquisa de Tarefas</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <script src="../script/jquery-3.5.1.min.js"></script>
    <?php include(__DIR__ . '/../style/style_tarefas.php');?>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisa de Tarefas</h3>
            <form id="searchForm">
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label for="protocol">Protocolo Geral:</label>
                        <input type="text" class="form-control" id="protocol" name="protocol">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="title">Título da Tarefa:</label>
                        <input type="text" class="form-control" id="title" name="title">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="category">Categoria:</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">Selecione</option>
                            <?php
                            $sql = "SELECT id, titulo FROM categorias WHERE status = 'ativo'";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="origin">Origem:</label>
                        <select id="origin" name="origin" class="form-control">
                            <option value="">Selecione</option>
                            <?php
                            $sql = "SELECT id, titulo FROM origem WHERE status = 'ativo'";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="status">Status:</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Selecione</option>
                            <option value="Iniciada">Iniciada</option>
                            <option value="Em Espera">Em Espera</option>
                            <option value="Em Andamento">Em Andamento</option>
                            <option value="Concluída">Concluída</option>
                            <option value="Cancelada">Cancelada</option>
                            <option value="Pendente">Pendente</option>
                            <option value="Aguardando Retirada">Aguardando Retirada</option>
                            <option value="Aguardando Pagamento">Aguardando Pagamento</option>
                            <option value="Prazo de Edital">Prazo de Edital</option>
                            <option value="Exigência Cumprida">Exigência Cumprida</option>
                            <option value="Finalizado sem prática do ato">Finalizado sem prática do ato</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="description">Descrição:</label>
                        <input type="text" class="form-control" id="description" name="description">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="priority">Prioridade:</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="">Selecione</option>
                            <option value="Baixa">Baixa</option>
                            <option value="Média">Média</option>
                            <option value="Alta">Alta</option>
                            <option value="Crítica">Crítica</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="employee">Funcionário Responsável:</label>
                        <select id="revisor" name="employee" class="form-control">
                            <option value="">Selecione</option>
                            <?php
                            $sql = "SELECT nome_completo FROM funcionarios WHERE status = 'ativo'";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="revisor">Revisor:</label>
                        <select id="revisor" name="revisor" class="form-control">
                            <option value="">Selecione</option>
                            <?php
                            $sql = "SELECT nome_completo FROM funcionarios WHERE status = 'ativo'";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <button type="submit" style="width: 100%;" class="btn btn-primary"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar</button>
                    </div>
                    <div class="col-md-6 text-right">
                        <button id="add-button" type="button" style="width: 100%;" class="btn btn-success" onclick="window.location.href='criar-tarefa.php'"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="result-block">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <h5>Resultados da Pesquisa</h5>
                        <div class="form-inline mb-2">
                            <label class="mr-2">Ordenar por:</label>
                            
                            <select id="sortSelect" class="form-control mr-3">
                                <option value="protocolo">Protocolo</option>
                                <option value="data">Data Limite</option>
                                <option value="funcionario">Funcionário</option>
                                <option value="prioridade">Prioridade</option>
                                <option value="titulo">Título</option>
                                <option value="status">Status</option>
                            </select>
                            
                            <select id="sortOrder" class="form-control mr-3">
                                <option value="desc">Decrescente</option>
                                <option value="asc">Crescente</option>
                            </select>
                            
                            <input type="text" id="searchCardInput" class="form-control" placeholder="Pesquisar nos resultados...">
                        </div>
                </div>

                <div id="cardsResultado" class="row">
                    <!-- Cards das tarefas serão inseridos aqui -->
                </div>
            </div>

        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="viewTaskModal" tabindex="-1" role="dialog" aria-labelledby="viewTaskModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-xl" role="document" >  
        <div class="modal-content">  
            <!-- Header Principal -->  
            <div class="modal-header primary-header">  
                <div class="modal-header-content">  
                    <h5 class="modal-title" id="viewTaskModalLabel">  
                        <i class="fa fa-tasks"></i>  
                        Protocolo Geral nº.: <span id="taskNumber" class="protocol-number"></span>  
                    </h5>  
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>  
                </div>  
            </div>  

            <!-- Barra de Ações -->  
            <div class="actions-toolbar">  
                <div class="action-buttons">  
                    <button id="guiaProtocoloButton" class="action-btn">  
                        <i class="fa fa-print"></i>  
                        <span>Protocolo Geral</span>  
                    </button>  
                    <button id="guiaRecebimentoButton" class="action-btn">  
                        <i class="fa fa-file-text"></i>  
                        <span>Guia Recebimento</span>  
                    </button>  
                    <button id="add-button" class="action-btn success" onclick="window.open('../oficios/cadastrar-oficio.php', '_blank')">  
                        <i class="fa fa-plus"></i>  
                        <span>Criar Ofício</span>  
                    </button>  
                    <button id="vincularOficioButton" class="action-btn primary" data-toggle="modal" data-target="#vincularOficioModal">  
                        <i class="fa fa-link"></i>  
                        <span>Vincular Ofício</span>  
                    </button>  
                    <button id="reciboEntregaButton" class="action-btn">  
                        <i class="fa fa-file-text"></i>  
                        <span>Recibo Entrega</span>  
                    </button>  
                    <button id="editTaskButton" class="action-btn editar">
                        <i class="fa fa-pencil"></i>
                        <span>Editar</span>
                    </button>
                </div>  
            </div>

            <!-- Corpo do Modal -->  
            <div class="modal-body">  
                <!-- Grid de Informações -->  
                <div class="info-section">
                    <div class="info-grid columns-4">
                        <div class="info-item">
                            <label for="viewTitle">Título</label>
                            <input type="text" class="form-control-modern" id="viewTitle" readonly>
                        </div>
                        <div class="info-item">
                            <label for="viewCategory">Categoria</label>
                            <input type="text" class="form-control-modern" id="viewCategory" readonly>
                        </div>
                        <div class="info-item">
                            <label for="viewOrigin">Origem</label>
                            <input type="text" class="form-control-modern" id="viewOrigin" readonly>
                        </div>
                        <div class="info-item">
                            <label for="viewDeadline">Data Limite</label>
                            <input type="text" class="form-control-modern" id="viewDeadline" readonly>
                        </div>
                        <div class="info-item">
                            <label for="viewEmployee">Funcionário Responsável</label>
                            <input type="text" class="form-control-modern" id="viewEmployee" readonly>
                        </div>
                        <div class="info-item">
                            <label for="viewRevisor">Revisor</label>
                            <input type="text" class="form-control-modern" id="viewRevisor" readonly>
                        </div>
                        <div class="info-item">
                            <label for="viewConclusionDate">Data de Conclusão</label>
                            <input type="text" class="form-control-modern" id="viewConclusionDate" readonly>
                        </div>
                    </div>
                </div>
  
                <!-- Informações de Criação -->  
                <div class="creation-info info-grid columns-2">
                    <div class="info-item">
                        <label for="createdBy">Criado por</label>
                        <input type="text" class="form-control-modern" id="createdBy" readonly>
                    </div>
                    <div class="info-item">
                        <label for="createdAt">Data de Criação</label>
                        <input type="text" class="form-control-modern" id="createdAt" readonly>
                    </div>
                </div>

                <!-- Descrição -->  
                <div class="description-section">  
                    <label for="viewDescription">Descrição</label>  
                    <textarea class="form-control-modern" id="viewDescription" rows="4" readonly></textarea>  
                </div>  

                <!-- Status -->
                <div class="status-section enhanced-block">
                    <label for="viewStatus">Status da Tarefa</label>
                    <div class="status-control">
                        <select id="viewStatus" class="form-control-modern status-select">
                            <option value="Iniciada">Iniciada</option>
                            <option value="Em Espera">Em Espera</option>
                            <option value="Em Andamento">Em Andamento</option>
                            <option value="Concluída">Concluída</option>
                            <option value="Cancelada">Cancelada</option>
                            <option value="Pendente">Pendente</option>
                            <option value="Aguardando Retirada">Aguardando Retirada</option>
                            <option value="Aguardando Pagamento">Aguardando Pagamento</option>
                            <option value="Prazo de Edital">Prazo de Edital</option>
                            <option value="Exigência Cumprida">Exigência Cumprida</option>
                            <option value="Finalizado sem prática do ato">Finalizado sem prática do ato</option>
                        </select>
                    </div>
                </div>

 
                <!-- Seção de Anexos -->
                <div class="attachments-section enhanced-block">
                    <h4 class="section-title"><i class="fa fa-paperclip"></i> Anexos</h4>
                    <div id="viewAttachments" class="attachments-list"></div>
                </div>


                <!-- Botão Criar Subtarefa -->  
                <button id="createSubTaskButton" class="create-subtask-btn" data-toggle="modal" data-target="#createSubTaskModal">  
                    <i class="fa fa-plus"></i> Criar Subtarefa  
                </button>  

                <!-- Tabelas de Tarefas -->  
                <div class="tasks-tables">  
                    <!-- Tabela Principal -->  
                    <div id="mainTaskSection" class="task-section">  
                        <h4 id="mainTaskHeader" style="display: none;">  
                            <i class="fa fa-project-diagram"></i> Tarefa Principal  
                        </h4>  
                        <div class="table-responsive">  
                            <table id="mainTaskTable" class="table table-modern" style="display: none;zoom: 90%">  
                                <thead>  
                                    <tr>  
                                        <th>Protocolo</th>  
                                        <th>Título da Tarefa Principal</th>  
                                        <th>Funcionário Responsável</th>  
                                        <th>Data de Criação</th>  
                                        <th>Data Limite</th>  
                                        <th>Status</th>  
                                        <th>Ações</th>  
                                    </tr>  
                                </thead>  
                                <tbody id="mainTaskTableBody">  
                                    <!-- Linha da tarefa principal será inserida aqui via JavaScript -->  
                                </tbody>  
                            </table>  
                        </div>  
                    </div>  

                    <hr>  

                    <!-- Tabela de Subtarefas -->  
                    <div id="subTasksSection" class="task-section">  
                        <h4 id="subTasksHeader" style="display: none;">  
                            <i class="fa fa-tasks"></i> Subtarefas  
                        </h4>  
                        <div class="table-responsive">  
                            <table id="subTasksTable" class="table table-modern" style="display: none;zoom: 90%">  
                                <thead>  
                                    <tr>  
                                        <th>Protocolo</th>  
                                        <th>Título da Subtarefa</th>  
                                        <th>Funcionário Responsável</th>  
                                        <th>Data de Criação</th>  
                                        <th>Data Limite</th>  
                                        <th>Status</th>  
                                        <th>Ações</th>  
                                    </tr>  
                                </thead>  
                                <tbody id="subTasksTableBody">  
                                    <!-- Linhas de subtarefas serão inseridas aqui via JavaScript -->  
                                </tbody>  
                            </table>  
                        </div>  
                    </div>  
                </div>
                <!-- Timeline -->
                <div class="timeline-section enhanced-block">
                    <div class="section-header">
                        <h4 class="section-title"><i class="fa fa-history"></i> Timeline</h4>
                        <button type="button" class="btn-add-comment" id="addCommentButton" data-toggle="modal" data-target="#addCommentModal">
                            <i class="fa fa-plus-circle"></i> Adicionar Comentário
                        </button>
                    </div>
                    <div id="commentTimeline" class="timeline-content"></div>
                </div>

            </div>  

            <!-- Footer -->  
            <div class="modal-footer">  
                <button type="button" class="btn-close-modal" data-dismiss="modal">  
                    <i class="fa fa-times"></i> Fechar  
                </button>  
            </div>  
        </div>  
    </div>  
</div>

    <!-- Modal Adicionar Comentário -->  
    <div class="modal fade" id="addCommentModal" tabindex="-1" role="dialog" aria-labelledby="addCommentModalLabel" aria-hidden="true">  
        <div class="modal-dialog modal-gl" role="document">  
            <div class="modal-content">  
                <!-- Header -->  
                <div class="primary-header">  
                    <div class="modal-header-content">  
                        <h5 class="modal-title" id="addCommentModalLabel">  
                            <i class="fa fa-comment"></i> Adicionar Comentário e Anexos  
                        </h5>  
                    </div>  
                </div>  

                <!-- Body -->  
                <div class="modal-body">  
                    <form id="commentForm">  
                        <!-- Seção de Comentário -->  
                        <div class="comment-section">  
                            <label for="commentDescription">Comentário</label>  
                            <textarea class="form-control-modern" id="commentDescription" name="commentDescription" rows="5"   
                                placeholder="Digite seu comentário aqui..."></textarea>  
                        </div>  

                        <!-- Seção de Anexos -->  
                        <div class="attachments-section">  
                            <label>Anexos</label>  
                            <div class="file-upload-wrapper">  
                                <input type="file" id="commentAttachments" name="commentAttachments[]" multiple class="modern-file-input">  
                                <label for="commentAttachments" class="file-upload-label">  
                                    <i class="fa fa-cloud-upload"></i>  
                                    <span class="upload-text">Arraste os arquivos ou clique para selecionar</span>  
                                </label>  
                                <div class="selected-files" id="selectedFiles"></div>  
                            </div>  
                        </div>  
                    </form>  
                </div>  

                <!-- Footer -->  
                <div class="modal-footer">  
                    <button type="button" class="btn-close-modal" data-dismiss="modal">  
                        <i class="fa fa-times"></i> Cancelar  
                    </button>  
                    <button type="submit" form="commentForm" class="action-btn success">  
                        <i class="fa fa-save"></i> Salvar Comentário  
                    </button>  
                </div>  
            </div>  
        </div>  
    </div>

    <!-- Modal Vincular Ofício -->  
    <div class="modal fade" id="vincularOficioModal" tabindex="-1" role="dialog" aria-labelledby="vincularOficioModalLabel" aria-hidden="true">  
        <div class="modal-dialog modal-gl" role="document">  
            <div class="modal-content">  
                <!-- Header -->  
                <div class="primary-header">  
                    <div class="modal-header-content">  
                        <h5 class="modal-title" id="vincularOficioModalLabel">  
                            <i class="fa fa-link"></i> Vincular Ofício  
                        </h5>   
                    </div>  
                </div>  

                <!-- Body -->  
                <div class="modal-body">  
                    <form id="vincularOficioForm">  
                        <!-- Seção de Vínculo -->  
                        <div class="link-section">  
                            <div class="info-item">  
                                <label for="numeroOficio">Número do Ofício</label>  
                                <div class="input-group">  
                                    <input type="text" class="form-control-modern" id="numeroOficio" name="numeroOficio" placeholder="Digite o número do ofício">  
                                    <div class="input-icon">  
                                        <i class="fa fa-file-text"></i>  
                                    </div>  
                                </div>  
                            </div>  
                        </div>  
                    </form>  
                </div>  

                <!-- Footer -->  
                <div class="modal-footer">  
                    <button type="button" class="btn-close-modal" data-dismiss="modal">  
                        <i class="fa fa-times"></i> Cancelar  
                    </button>  
                    <button type="submit" form="vincularOficioForm" class="action-btn primary">  
                        <i class="fa fa-save"></i> Vincular  
                    </button>  
                </div>  
            </div>  
        </div>  
    </div>

    <!-- Modal Recibo de Entrega -->  
<div class="modal fade" id="reciboEntregaModal" tabindex="-1" role="dialog" aria-labelledby="reciboEntregaModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-gl" role="document">  
        <div class="modal-content">  
            <!-- Header -->  
            <div class="primary-header">  
                <div class="modal-header-content">  
                    <h5 class="modal-title" id="reciboEntregaModalLabel">  
                        <i class="fa fa-file-text"></i> Recibo de Entrega  
                    </h5>  
                </div>  
            </div>  

            <!-- Body -->  
            <div class="modal-body">  
                <form id="reciboEntregaForm">  
                    <!-- Grid de Informações -->  
                    <div class="info-grid">  
                        <div class="info-item">  
                            <label for="receptor">Nome do Receptor</label>  
                            <input type="text" class="form-control-modern" id="receptor" name="receptor" required>  
                        </div>  
                        
                        <div class="info-item">  
                            <label for="dataEntrega">Data da Entrega</label>  
                            <input type="datetime-local" class="form-control-modern" id="dataEntrega" name="dataEntrega" required>  
                        </div>  
                        
                        <div class="info-item">  
                            <label for="entregador">Nome do Entregador</label>  
                            <input type="text" class="form-control-modern" id="entregador" name="entregador" readonly>  
                        </div>  
                    </div>  

                    <!-- Seção de Documentos -->  
                    <div class="description-section">  
                        <label for="documentos">Documentos Entregues</label>  
                        <textarea class="form-control-modern" id="documentos" name="documentos" rows="3" required></textarea>  
                    </div>  

                    <!-- Seção de Observações -->  
                    <div class="description-section">  
                        <label for="observacoes">Observações</label>  
                        <textarea class="form-control-modern" id="observacoes" name="observacoes" rows="3"></textarea>  
                    </div>  
                </form>  
            </div>  

            <!-- Footer -->  
            <div class="modal-footer">  
                <button type="button" class="btn-close-modal" data-dismiss="modal">  
                    <i class="fa fa-times"></i> Cancelar  
                </button>  
                <button type="submit" form="reciboEntregaForm" class="action-btn success">  
                    <i class="fa fa-save"></i> Salvar Recibo  
                </button>  
            </div>  
        </div>  
    </div>  
</div>

    <!-- Modal Guia de Recebimento -->  
<div class="modal fade" id="guiaRecebimentoModal" tabindex="-1" role="dialog" aria-labelledby="guiaRecebimentoModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-gl" role="document">  
        <div class="modal-content">  
            <!-- Header -->  
            <div class="primary-header">  
                <div class="modal-header-content">  
                    <h5 class="modal-title" id="guiaRecebimentoModalLabel">  
                        <i class="fa fa-file-text"></i> Guia de Recebimento  
                    </h5>    
                </div>  
            </div>  

            <!-- Body -->  
            <div class="modal-body">  
                <form id="guiaRecebimentoForm">  
                    <!-- Grid de Informações -->  
                    <div class="info-grid">  
                        <div class="info-item">  
                            <label for="cliente">Apresentante</label>  
                            <input type="text" class="form-control-modern" id="cliente" name="cliente" required>  
                        </div>  
                        
                        <div class="info-item">  
                            <label for="dataRecebimento">Data de Recebimento</label>  
                            <input type="datetime-local" class="form-control-modern" id="dataRecebimento" name="dataRecebimento" required>  
                        </div>  
                        
                        <div class="info-item">  
                            <label for="funcionario">Funcionário</label>  
                            <input type="text" class="form-control-modern" id="funcionario" name="funcionario" readonly>  
                        </div>  
                    </div>  

                    <!-- Seção de Documentos -->  
                    <div class="description-section">  
                        <label for="documentosRecebidos">Documentos Recebidos</label>  
                        <textarea class="form-control-modern" id="documentosRecebidos" name="documentosRecebidos" rows="3" required></textarea>  
                    </div>  

                    <!-- Seção de Observações -->  
                    <div class="description-section">  
                        <label for="observacoes">Observações</label>  
                        <textarea class="form-control-modern" id="observacoes" name="observacoes" rows="3"></textarea>  
                    </div>  
                </form>  
            </div>  

            <!-- Footer -->  
            <div class="modal-footer">  
                <button type="button" class="btn-close-modal" data-dismiss="modal">  
                    <i class="fa fa-times"></i> Cancelar  
                </button>  
                <button type="submit" form="guiaRecebimentoForm" class="action-btn success">  
                    <i class="fa fa-save"></i> Salvar Guia  
                </button>  
            </div>  
        </div>  
    </div>  
</div>


    <!-- Modal Criar Subtarefa -->  
<div class="modal fade" id="createSubTaskModal" tabindex="-1" role="dialog" aria-labelledby="createSubTaskModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-xl" role="document">  
        <div class="modal-content">  
            <!-- Header -->  
            <div class="primary-header">  
                <div class="modal-header-content">  
                    <h5 class="modal-title" id="createSubTaskModalLabel">  
                        <i class="fa fa-tasks"></i> Criar Subtarefa  
                    </h5>  
                    
                </div>  
            </div>  

            <!-- Body -->  
            <div class="modal-body">  
                <form id="subTaskForm" enctype="multipart/form-data" method="POST" action="save_sub_task.php">  
                    <!-- Informações Principais -->  
                    <div class="form-section">  
                        <div class="info-grid columns-4"> 
                            <div class="info-item">  
                                <label for="subTaskTitle">Título da Subtarefa</label>  
                                <input type="text" class="form-control-modern" id="subTaskTitle" name="title" required>  
                            </div>  
                            
                            <div class="info-item">  
                                <label for="subTaskCategory">Categoria</label>  
                                <select id="subTaskCategory" name="category" class="form-control-modern" required>  
                                    <option value="">Selecione</option>  
                                    <?php  
                                    $sql = "SELECT id, titulo FROM categorias WHERE status = 'ativo'";  
                                    $result = $conn->query($sql);  
                                    if ($result->num_rows > 0) {  
                                        while($row = $result->fetch_assoc()) {  
                                            echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                        }  
                                    }  
                                    ?>  
                                </select>  
                            </div>  
                          
                            <div class="info-item">  
                                <label for="subTaskDeadline">Data Limite</label>  
                                <input type="datetime-local" class="form-control-modern" id="subTaskDeadline" name="deadline" required>  
                            </div>  

                            <div class="info-item">  
                                <label for="subTaskPriority">Nível de Prioridade</label>  
                                <select id="subTaskPriority" name="priority" class="form-control-modern" required>  
                                    <option value="">Selecione</option>  
                                    <option value="Baixa">Baixa</option>  
                                    <option value="Média">Média</option>  
                                    <option value="Alta">Alta</option>  
                                    <option value="Crítica">Crítica</option>  
                                </select>  
                            </div>  

                            <div class="info-item">  
                                <label for="subTaskEmployee">Funcionário Responsável</label>  
                                <select id="subTaskEmployee" name="employee" class="form-control-modern" required>  
                                    <option value="">Selecione</option>  
                                    <?php  
                                    $sql = "SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo'";  
                                    $result = $conn->query($sql);  
                                    $loggedInUser = $_SESSION['username'];  

                                    if ($result->num_rows > 0) {  
                                        while($row = $result->fetch_assoc()) {  
                                            $selected = ($row['nome_completo'] == $loggedInUser) ? 'selected' : '';  
                                            echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "' $selected>" .   
                                                htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                        }  
                                    }  
                                    ?>  
                                </select>  
                            </div>

                            <div class="info-item">  
                                <label class="subTaskEmployee">Revisor (Opcional):</label>
                                <select class="form-control-modern" id="reviewer" name="reviewer">
                                    <option value="">Selecione</option>
                                    <?php  
                                    $sql = "SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo'";  
                                    $result = $conn->query($sql);  
                                    if ($result->num_rows > 0) {  
                                        while($row = $result->fetch_assoc()) {  
                                            echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "'>" .   
                                                htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                        }  
                                    }  
                                    ?>  
                                </select>  
                            </div>

                            <div class="info-item">  
                                <label for="subTaskOrigin">Origem</label>  
                                <select id="subTaskOrigin" name="origin" class="form-control-modern" required>  
                                    <option value="">Selecione</option>  
                                    <?php  
                                    $sql = "SELECT id, titulo FROM origem WHERE status = 'ativo'";  
                                    $result = $conn->query($sql);  
                                    if ($result->num_rows > 0) {  
                                        while($row = $result->fetch_assoc()) {  
                                            echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                        }  
                                    }  
                                    ?>  
                                </select>  
                            </div>  
                        </div>  
                    </div>  

                    <!-- Descrição -->  
                    <div class="description-section">  
                        <label for="subTaskDescription">Descrição</label>  
                        <textarea class="form-control-modern" id="subTaskDescription" name="description" rows="5"></textarea>  
                    </div>  

                    <!-- Anexos -->  
                    <div class="attachments-section">  
                        <div class="attachment-header">  
                            <label>Anexos</label>  
                            <div class="form-check">  
                                <input class="form-check-input modern-checkbox" type="checkbox" id="compartilharAnexos" name="compartilharAnexos">  
                                <label class="form-check-label" for="compartilharAnexos">  
                                    Compartilhar anexos da tarefa principal  
                                </label>  
                            </div>  
                        </div>  
                        
                        <div class="file-upload-wrapper">  
                            <input type="file" id="subTaskAttachments" name="attachments[]" multiple class="modern-file-input">  
                            <label for="subTaskAttachments" class="file-upload-label">  
                                <i class="fa fa-cloud-upload"></i>  
                                <span class="upload-text">Arraste os arquivos ou clique para selecionar</span>  
                            </label>  
                            <div class="selected-files" id="selectedFiles"></div>  
                        </div>
                    </div>  

                    <!-- Campos ocultos -->  
                    <input type="hidden" id="subTaskCreatedBy" name="createdBy" value="<?php echo $_SESSION['username']; ?>">  
                    <input type="hidden" id="subTaskCreatedAt" name="createdAt" value="<?php echo date('Y-m-d H:i:s'); ?>">  
                    <input type="hidden" id="subTaskPrincipalId" name="id_tarefa_principal">  
                </form>  
            </div>  

            <!-- Footer -->  
            <div class="modal-footer">  
                <button type="button" class="btn-close-modal" data-dismiss="modal">  
                    <i class="fa fa-times"></i> Cancelar  
                </button>  
                <button type="submit" form="subTaskForm" class="action-btn success">  
                    <i class="fa fa-save"></i> Criar Subtarefa  
                </button>  
            </div>  
        </div>  
    </div>  
</div>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    
    <script>  
        document.getElementById('compartilharAnexos').addEventListener('change', function() {  
            const fileInput = document.getElementById('subTaskAttachments');  
            const uploadWrapper = fileInput.closest('.file-upload-wrapper');  
            
            if (this.checked) {  
                uploadWrapper.style.opacity = '0.5';  
                fileInput.disabled = true;  
            } else {  
                uploadWrapper.style.opacity = '1';  
                fileInput.disabled = false;  
            }  
        });  
    </script>
    <script>

        document.addEventListener('DOMContentLoaded', function() {
            var subTaskDeadlineInput = document.getElementById('subTaskDeadline');
            var now = new Date();
            var year = now.getFullYear();
            var month = ('0' + (now.getMonth() + 1)).slice(-2); // Meses são 0-indexados
            var day = ('0' + now.getDate()).slice(-2);
            var hours = ('0' + now.getHours()).slice(-2);
            var minutes = ('0' + now.getMinutes()).slice(-2);

            // Formato YYYY-MM-DDTHH:MM
            var minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            subTaskDeadlineInput.min = minDateTime;
        });

        document.addEventListener('DOMContentLoaded', function() {
            var dataRecebimentoInput = document.getElementById('dataRecebimento');
            var now = new Date();
            var year = now.getFullYear();
            var month = ('0' + (now.getMonth() + 1)).slice(-2); // Meses são 0-indexados
            var day = ('0' + now.getDate()).slice(-2);
            var hours = ('0' + now.getHours()).slice(-2);
            var minutes = ('0' + now.getMinutes()).slice(-2);

            // Formato YYYY-MM-DDTHH:MM
            var minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            dataRecebimentoInput.min = minDateTime;
        });

        document.addEventListener('DOMContentLoaded', function() {
            var dataEntregaInput = document.getElementById('dataEntrega');
            var now = new Date();
            var year = now.getFullYear();
            var month = ('0' + (now.getMonth() + 1)).slice(-2); // Meses são 0-indexados
            var day = ('0' + now.getDate()).slice(-2);
            var hours = ('0' + now.getHours()).slice(-2);
            var minutes = ('0' + now.getMinutes()).slice(-2);

            // Formato YYYY-MM-DDTHH:MM
            var minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            dataEntregaInput.min = minDateTime;
        });

        function normalizeText(text) {
            return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        function formatDateTime(dateTime) {
            var date = new Date(dateTime);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
        }

        $(document).ready(function() {
            $('#createSubTaskButton').on('click', function() {
                var taskId = $('#taskNumber').text(); // Obtém o ID da tarefa principal
                $('#subTaskPrincipalId').val(taskId); // Define o ID da tarefa principal no campo oculto
            });
        });

        function loadMainTask(subTaskId) {
            $.ajax({
                url: 'get_tarefa_principal.php', // Arquivo PHP que busca a tarefa principal
                type: 'GET',
                data: { id_tarefa_sub: subTaskId }, // Envia o ID da subtarefa
                success: function(response) {
                    var mainTask = JSON.parse(response);
                    var mainTaskTableBody = $('#mainTaskTableBody');
                    var mainTaskTable = $('#mainTaskTable');
                    var mainTaskHeader = $('#mainTaskHeader');

                    mainTaskTableBody.empty(); // Limpa as linhas antigas da tabela
                    
                    // Verifica se a tarefa que está sendo visualizada é a mesma que a principal
                    if (mainTask.error || !mainTask.id || mainTask.id == subTaskId) {
                        mainTaskTable.hide(); // Oculta a tabela e o cabeçalho se não houver tarefa principal ou se for a própria tarefa
                        mainTaskHeader.hide();
                    } else {
                        mainTaskTable.show(); // Mostra a tabela de tarefa principal
                        mainTaskHeader.show();
                        var row = '<tr>' +
                            '<td>' + mainTask.id + '</td>' +
                            '<td>' + mainTask.titulo + '</td>' +
                            '<td>' + mainTask.funcionario_responsavel + '</td>' +
                            '<td>' + new Date(mainTask.data_criacao).toLocaleString("pt-BR") + '</td>' +
                            '<td>' + new Date(mainTask.data_limite).toLocaleString("pt-BR") + '</td>' +
                            '<td><span class="' + getStatusClass(mainTask.status) + '">' + capitalize(mainTask.status) + '</span></td>' +
                            '<td><button title="Visualizar" class="btn btn-info btn-sm" onclick="abrirTarefaEmNovaGuia(' + mainTask.id + ')">' +
                            '<i class="fa fa-eye" aria-hidden="true"></i></button></td>' +
                            '</tr>';
                        mainTaskTableBody.append(row);

                    }
                },
                error: function() {
                    alert('Erro ao buscar a tarefa principal');
                }
            });
        }


        $('#viewTaskModal').on('shown.bs.modal', function() {
            var taskId = $('#taskNumber').text(); // ID da tarefa (pode ser principal ou subtarefa)
            
            loadSubTasks(taskId); // Carregar subtarefas da tarefa principal (caso seja uma tarefa principal)
            loadMainTask(taskId); // Carregar a tarefa principal (caso seja uma subtarefa)
        });


        function loadSubTasks(taskId) {
            $.ajax({
                url: 'get_sub_tasks.php', // Certifique-se que a URL está correta
                type: 'GET',
                data: { id_tarefa_principal: taskId }, // Envia o ID da tarefa principal
                success: function(response) {
                    var subTasks = JSON.parse(response);
                    var subTasksTableBody = $('#subTasksTableBody');
                    var subTasksTable = $('#subTasksTable');
                    var subTasksHeader = $('#subTasksHeader');

                    subTasksTableBody.empty(); // Limpa as linhas antigas da tabela

                    if (subTasks.length > 0) {
                        subTasksTable.show(); // Mostra a tabela de subtarefas caso existam
                        subTasksHeader.show();
                        subTasks.forEach(function(subTask) {
                            var statusClass = getStatusClass(subTask.status);
                            var rowClass = getRowClass(subTask.status, subTask.data_limite);
                            var row = '<tr class="' + rowClass + '">' +
                                '<td>' + subTask.id + '</td>' +
                                '<td>' + subTask.titulo + '</td>' +
                                '<td>' + subTask.funcionario_responsavel + '</td>' +
                                '<td>' + new Date(subTask.data_criacao).toLocaleString("pt-BR") + '</td>' +
                                '<td>' + new Date(subTask.data_limite).toLocaleString("pt-BR") + '</td>' +
                                '<td><span class="' + statusClass + '">' + capitalize(subTask.status) + '</span></td>' +
                                '<td><button title="Visualizar" class="btn btn-info btn-sm" onclick="abrirTarefaEmNovaGuia(' + subTask.id + ')">' +
                                '<i class="fa fa-eye" aria-hidden="true"></i></button></td>' +
                                '</tr>';
                            subTasksTableBody.append(row);
                        });
                    } else {
                        subTasksTable.hide(); // Oculta a tabela se não houver subtarefas
                        subTasksHeader.hide();
                    }
                },
                error: function() {
                    alert('Erro ao buscar as subtarefas');
                }
            });
        }

        function capitalize(text) {
            return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
        }

// Função para retornar a classe de status
function getStatusClass(status) {
    switch (status) {
        case 'Iniciada':
            return 'status-sub-iniciada';
        case 'Em Espera':
            return 'status-sub-em-espera';
        case 'Em Andamento':
            return 'status-sub-em-andamento';
        case 'Concluída':
            return 'status-sub-concluida';
        case 'Cancelada':
            return 'status-sub-cancelada';
        case 'pendente':
            return 'status-sub-pendente';
        case 'Aguardando Retirada':
            return 'status-sub-aguardando-retirada';
        case 'Aguardando Pagamento':
            return 'status-sub-aguardando-pagamento';
        case 'Prazo de Edital':
            return 'status-sub-prazo-de-edital';
        case 'Exigência Cumprida':
            return 'status-sub-exigencia-cumprida';
        case 'Finalizado sem prática do ato':
            return 'status-sub-finalizado-sem-pratica-do-ato';
        default:
            return '';
    }
}

// Função para definir a classe de linha com base na data limite
function getRowClass(status, data_limite) {
    var deadlineDate = new Date(data_limite);
    var currentDate = new Date();
    var oneDay = 24 * 60 * 60 * 1000;

    if (status !== 'concluída' && status !== 'cancelada' && status !== 'Finalizado sem prática do ato' && status !== 'Aguardando Retirada') {
        if (deadlineDate < currentDate) {
            return 'row-sub-vencida';  // Tarefa já está vencida
        } else if ((deadlineDate - currentDate) <= oneDay) {
            return 'row-sub-quase-vencida';  // Tarefa está prestes a vencer
        }
    }
    return '';
}

// Função para definir a classe de prioridade
function getPriorityClass(priority) {
    switch (priority) {
        case 'Baixa': return ''; // Não adiciona cor para baixa
        case 'Média': return 'priority-medium';
        case 'Alta': return 'priority-high';
        case 'Crítica': return 'priority-critical';
        default: return '';
    }
}


$('#viewTaskModal').on('shown.bs.modal', function() {
    var taskId = $('#taskNumber').text(); // ID da tarefa principal
    loadSubTasks(taskId); // Carregar subtarefas da tarefa principal
});



        $(document).ready(function() {
        // Inicializar DataTable uma vez, sem destruir
        var dataTable = $('#tabelaResultados').DataTable({
            "language": {
                "url": "../style/Portuguese-Brasil.json"
            },
            "pageLength": 10,
            "order": [[0, 'desc']], // Ordena a primeira coluna (índice 0) em ordem decrescente
            "destroy": false // Certificar-se de que não destruímos o DataTable
        });

        // Carregar automaticamente as tarefas com status diferente de "Concluída" e "Cancelada" ao carregar a página
        loadTasks();

        // Enviar formulário de pesquisa quando o usuário clicar em "Filtrar"
        $('#searchForm').on('submit', function(e) {
            e.preventDefault();
            loadTasks();  // Carregar as tarefas com base nos filtros do formulário
        });

        // Função para carregar as tarefas
        function loadTasks() {
            var formData = $('#searchForm').serialize();

            $.ajax({
                url: 'search_tasks.php',
                type: 'GET',
                data: formData,
                success: function(response) {
                    var tasks = JSON.parse(response);
                    var container = $('#cardsResultado');
                    container.empty();

                    if (tasks.length > 0) {
                        tasks.forEach(function(task) {
                            const card = `
                                <div class="col-md-6 col-lg-4">
                                    <div class="card-tarefa ${getPriorityClass(task.nivel_de_prioridade)} ${getSituacaoClass(task.data_limite, task.status)}" onclick="viewTask('${task.token}')" style="cursor: pointer;">
                                        <div class="card-title">Protocolo #${task.id}</div>
                                        <div class="card-info"><b>Título:</b> ${task.titulo}</div>
                                        <div class="card-info"><b>Categoria:</b> ${task.categoria_titulo}</div>
                                        <div class="card-info"><b>Origem:</b> ${task.origem_titulo}</div>
                                        <div class="card-info"><b>Descrição:</b> ${task.descricao}</div>
                                        <div class="card-info"><b>Data Limite:</b> ${new Date(task.data_limite).toLocaleString("pt-BR")}</div>
                                        <div class="card-info"><b>Funcionário:</b> ${task.funcionario_responsavel}</div>
                                        <div class="card-info"><b>Prioridade:</b> ${task.nivel_de_prioridade}</div>
                                        <div class="card-info">
                                            <b>Status:</b> <span class="badge ${getStatusClass(task.status.toLowerCase())}">${capitalize(task.status)}</span>
                                        </div>
                                        <div class="card-info">
                                            <b>Situação:</b> <span class="badge ${getSituacaoClass(task.data_limite, task.status)}">${getSituacaoLabel(task.data_limite, task.status)}</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                            container.append(card);
                        });
                    } else {
                        container.html('<div class="col-12 text-center"><p>Nenhum resultado encontrado.</p></div>');
                    }
                },
                error: function() {
                    alert('Erro ao buscar as tarefas');
                }
            });
        }


function getStatusClass(status) {
    switch (status) {
        case 'iniciada': return 'status-iniciada';
        case 'em andamento': return 'status-em-andamento';
        case 'concluída': return 'status-concluida';
        case 'cancelada': return 'status-cancelada';
        case 'pendente': return 'status-pendente';
        default: return '';
    }
}

function getSituacaoLabel(data_limite, status) {
    const deadline = new Date(data_limite);
    const now = new Date();
    if (status.toLowerCase() === 'concluída' || status.toLowerCase() === 'cancelada') {
        return '-';
    }
    if (deadline < now) return 'Vencida';
    if ((deadline - now) / (1000 * 60 * 60 * 24) <= 1) return 'Prestes a Vencer';
    return '-';
}

function getSituacaoClass(data_limite, status) {
    const deadline = new Date(data_limite);
    const now = new Date();
    if (status.toLowerCase() === 'concluída' || status.toLowerCase() === 'cancelada') {
        return '';
    }
    if (deadline < now) return 'situacao-vencida';
    if ((deadline - now) / (1000 * 60 * 60 * 24) <= 1) return 'situacao-quase';
    return '';
}

function capitalize(text) {
    return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
}

$('#sortSelect, #sortOrder').on('change', function() {
    var cards = $('#cardsResultado .col-md-6, #cardsResultado .col-lg-4').get();
    var sortBy = $('#sortSelect').val();
    var sortOrder = $('#sortOrder').val();

    cards.sort(function(a, b) {
        let aValue = '';
        let bValue = '';

        if (sortBy === 'protocolo') {
            aValue = parseInt($(a).find('.card-title').text().replace(/\D/g, '')) || 0;
            bValue = parseInt($(b).find('.card-title').text().replace(/\D/g, '')) || 0;
        } else if (sortBy === 'data') {
            aValue = new Date($(a).find('.card-info:contains("Data Limite")').text().replace('Data Limite:', '').trim());
            bValue = new Date($(b).find('.card-info:contains("Data Limite")').text().replace('Data Limite:', '').trim());
        } else if (sortBy === 'prioridade') {
            const priorityMap = { 'baixa': 1, 'média': 2, 'media': 2, 'alta': 3, 'crítica': 4, 'critica': 4 };
            aValue = priorityMap[$(a).find('.card-info:contains("Prioridade")').text().replace('Prioridade:', '').trim().toLowerCase()] || 0;
            bValue = priorityMap[$(b).find('.card-info:contains("Prioridade")').text().replace('Prioridade:', '').trim().toLowerCase()] || 0;
        } else {
            aValue = $(a).find(`.card-info:contains(${sortByLabel(sortBy)})`).text().toLowerCase();
            bValue = $(b).find(`.card-info:contains(${sortByLabel(sortBy)})`).text().toLowerCase();
        }

        if (aValue < bValue) return sortOrder === 'asc' ? -1 : 1;
        if (aValue > bValue) return sortOrder === 'asc' ? 1 : -1;
        return 0;
    });

    $.each(cards, function(idx, itm) {
        $('#cardsResultado').append(itm);
    });
});


function sortByLabel(key) {
    switch (key) {
        case 'protocolo': return 'Protocolo';
        case 'data': return 'Data Limite';
        case 'funcionario': return 'Funcionário';
        case 'prioridade': return 'Prioridade';
        case 'status': return 'Status';
        default: return '';
    }
}

// 🔍 Filtro de pesquisa dinâmica nos cards
$('#searchCardInput').on('keyup', function() {
    var value = $(this).val().toLowerCase();
    $('#cardsResultado .card-tarefa').filter(function() {
        $(this).toggle(
            $(this).text().toLowerCase().indexOf(value) > -1
        );
    });
});

$('#editTaskButton').on('click', function() {
    const taskId = $('#taskNumber').text();
    window.location.href = 'edit_task.php?id=' + taskId;
});


        // Função para retornar a classe de status
        function getStatusClass(status) {
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


        // Função para definir a classe de linha com base na data limite
        function getRowClass(status, data_limite) {
            var deadlineDate = new Date(data_limite);
            var currentDate = new Date();
            var oneDay = 24 * 60 * 60 * 1000;

            if (status !== 'concluída' && status !== 'cancelada' && status !== 'Finalizado sem prática do ato' && status !== 'Aguardando Retirada') {
                if (deadlineDate < currentDate) {
                    return 'row-vencida';  // Tarefa já está vencida
                } else if ((deadlineDate - currentDate) <= oneDay) {
                    return 'row-quase-vencida';  // Tarefa está prestes a vencer
                }
            }
            return '';
        }

        // Função para definir a classe de prioridade
        function getPriorityClass(priority) {
            switch (priority) {
                case 'Baixa': return ''; // Não adiciona cor para baixa
                case 'Média': return 'priority-medium';
                case 'Alta': return 'priority-high';
                case 'Crítica': return 'priority-critical';
                default: return '';
            }
        }


        // Função para capitalizar a primeira letra
        function capitalize(text) {
            return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
        }
    });

    $(document).ready(function() {
        // Adiciona o evento de clique ao botão quando a página for carregada
        $('#guiaProtocoloButton').on('click', function() {
            // Faz a requisição para o JSON
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false, // Desabilita o cache
                success: function(data) {
                    const taskId = document.getElementById('taskNumber').innerText; // Pega o taskId via o elemento HTML
                    let url = '';

                    // Verifica o valor do "timbrado" e ajusta a URL
                    if (data.timbrado === 'S') {
                        url = 'protocolo_geral.php?id=' + taskId;
                    } else if (data.timbrado === 'N') {
                        url = 'protocolo-geral.php?id=' + taskId;
                    }

                    // Abre a URL correspondente em uma nova aba
                    window.open(url, '_blank');
                },
                error: function() {
                    alert('Erro ao carregar o arquivo de configuração.');
                }
            });
        });
    });

        $('#commentForm').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var taskToken = $('#viewTitle').data('tasktoken');

            formData.append('taskToken', taskToken);

            $.ajax({
                url: 'add_comment.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#addCommentModal').modal('hide');
                    $('body').removeClass('modal-open');

                    // ✅ Limpar textarea e input file após salvar
                    $('#commentDescription').val('');
                    $('#commentAttachments').val('');
                    $('#selectedFiles').html('');
                    $('.upload-text').text('Arraste os arquivos ou clique para selecionar');

                    Swal.fire({
                        title: 'Sucesso!',
                        text: 'Comentário adicionado com sucesso!',
                        icon: 'success'
                    }).then(() => {
                        viewTask(taskToken);
                    });
                },
                error: function() {
                    Swal.fire({
                        title: 'Erro!',
                        text: 'Erro ao adicionar comentário',
                        icon: 'error'
                    });
                }
            });
        });

        $('#addCommentModal').on('hidden.bs.modal', function () {
            $('#commentDescription').val('');
            $('#commentAttachments').val('');
            $('#selectedFiles').html('');
            $('.upload-text').text('Arraste os arquivos ou clique para selecionar');
        });

        $('#viewStatus').on('change', function() {
            const taskToken = $('#viewTitle').data('tasktoken');
            const status = $(this).val();
            const currentDate = new Date().toISOString().slice(0, 19).replace('T', ' ');

            Swal.fire({
                title: 'Confirmar alteração de status?',
                text: 'Deseja atualizar o status para "' + status + '"?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    atualizarStatus(taskToken, status, currentDate);
                }
            });
        });

        // Função isolada para atualizar
        function atualizarStatus(taskToken, status, currentDate) {
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                data: {
                    taskToken: taskToken,
                    status: status,
                    dataConclusao: status.toLowerCase() === 'concluída' ? currentDate : null
                },
                success: function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Status atualizado para "' + status + '".'
                    });
                    $('#searchForm').submit(); // Atualiza a lista de tarefas se quiser
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Não foi possível atualizar o status.'
                    });
                }
            });
        }


// Resolver problema de rolagem com modais empilhados
$('#addCommentModal, #createSubTaskModal, #guiaRecebimentoModal, #reciboEntregaModal, #vincularOficioModal').on('shown.bs.modal', function() {
    $('body').addClass('modal-open');
}).on('hidden.bs.modal', function() {
    $('body').removeClass('modal-open');
});

$('#viewTaskModal').on('shown.bs.modal', function() {
    // Fazer uma chamada AJAX para buscar o nível de acesso do usuário logado
    $.ajax({
        url: 'get_user_access.php',
        type: 'GET',
        success: function(response) {
            var userData = JSON.parse(response);
            var nivelAcesso = userData.nivel_de_acesso;
            
            var dataConclusao = $('#viewStatus').data('data-conclusao');
            if (dataConclusao !== null && dataConclusao !== "NULL" && dataConclusao !== "") {
                // Se a tarefa já tem data de conclusão e o nível de acesso for "usuario", desabilitar o botão
                if (nivelAcesso === 'usuario') {
                    $('#saveStatusButton').prop('disabled', true);
                } else if (nivelAcesso === 'administrador') {
                    $('#saveStatusButton').prop('disabled', false); // Administradores podem alterar o status
                }
            } else {
                // Se não há data de conclusão, permitir alteração para todos
                $('#saveStatusButton').prop('disabled', false);
            }
        },
        error: function() {
            alert('Erro ao verificar o nível de acesso do usuário.');
        }
    });

    // Verificar se a tarefa tem um ofício vinculado
    var numeroOficio = $('#viewTitle').data('numeroOficio');
    if (numeroOficio) {
        $('#vincularOficioButton').html('<i class="fa fa-eye" aria-hidden="true"></i> Visualizar Ofício')
            .attr('onclick', 'viewOficio(\'' + numeroOficio + '\')')
            .removeAttr('data-toggle data-target');
    } else {
        $('#vincularOficioButton').html('<i class="fa fa-link" aria-hidden="true"></i> Vincular Ofício')
            .attr('data-toggle', 'modal')
            .attr('data-target', '#vincularOficioModal')
            .removeAttr('onclick');
    }

   // Verificar se o recibo de entrega já foi gerado
    if ($('#viewTitle').data('reciboGerado')) {
        $('#reciboEntregaButton').off('click').on('click', function() {
            // Faz a requisição para o JSON
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false, // Desabilita o cache
                success: function(data) {
                    const taskId = $('#taskNumber').text(); // Pega o taskNumber via jQuery
                    let url = '';

                    // Verifica o valor do "timbrado" e ajusta a URL
                    if (data.timbrado === 'S') {
                        url = 'recibo_entrega.php?id=' + taskId;
                    } else if (data.timbrado === 'N') {
                        url = 'recibo-entrega.php?id=' + taskId;
                    }

                    // Abre a URL correspondente em uma nova aba
                    window.open(url, '_blank');
                },
                error: function() {
                    alert('Erro ao carregar o arquivo de configuração.');
                }
            });
        });

    } else {
        $('#reciboEntregaButton').off('click').on('click', function() {
            $('#reciboEntregaModal').modal('show');
            $('#entregador').val($('#viewEmployee').val()); // Preencher o entregador automaticamente
            $('#receptor').val(''); // Limpar o campo receptor
            $('#observacoes').val(''); // Limpar o campo observações
            $('#dataEntrega').val(''); // Limpar o campo data da entrega
            $('#documentos').val(''); // Limpar o campo documentos entregues
        });
    }
});


    $('#reciboEntregaForm').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize() + '&task_id=' + $('#taskNumber').text();

        $.ajax({
            url: 'save_recibo_entrega.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                try {
                    var result = JSON.parse(response);
                    if (result.success) {
                        $('#reciboEntregaModal').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Recibo de entrega salvo com sucesso!',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.open('recibo_entrega.php?id=' + $('#taskNumber').text(), '_blank');
                        });
                    } else {
                        console.error(result.error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Erro ao salvar o recibo de entrega: ' + result.error,
                            confirmButtonText: 'OK'
                        });
                    }
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e, response);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao salvar o recibo de entrega',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao salvar o recibo de entrega',
                    confirmButtonText: 'OK'
                });
            }
        });
    });


// Função para verificar se a guia de recebimento já foi gerada
function verificarOuAbrirGuia(taskId) {
    $.ajax({
        url: 'verificar_guia.php', // O arquivo PHP que criamos para verificar a guia
        type: 'GET',
        data: {
            task_id: taskId
        },
        success: function(response) {
            var result = JSON.parse(response);

            if (result.guia_existe) {
                // Se a guia já existe, abrir a guia diretamente com o task_id
                $.ajax({
                    url: '../style/configuracao.json',
                    dataType: 'json',
                    cache: false,
                    success: function(data) {
                        let url = '';

                        if (data.timbrado === 'S') {
                            url = 'guia_recebimento.php?id=' + taskId; // Usar task_id na URL
                        } else {
                            url = 'guia-recebimento.php?id=' + taskId; // Usar task_id na URL
                        }

                        // Abre a URL correspondente em uma nova aba
                        window.open(url, '_blank');
                    },
                    error: function() {
                        alert('Erro ao carregar o arquivo de configuração.');
                    }
                });

            } else {
                // Se a guia não existe, abrir o modal para criar a guia
                $('#guiaRecebimentoModal').modal('show');
                $('#funcionario').val($('#viewEmployee').val()); // Preencher o funcionário automaticamente
                $('#cliente').val(''); // Limpar o campo cliente
                $('#observacoes').val(''); // Limpar o campo observações
                $('#dataRecebimento').val(''); // Limpar o campo data de recebimento
                $('#documentosRecebidos').val(''); // Limpar o campo documentos recebidos
            }
        },
        error: function() {
            alert('Erro ao verificar a guia de recebimento.');
        }
    });
}

// Associa a verificação à ação do botão de guia de recebimento
$('#guiaRecebimentoButton').off('click').on('click', function() {
    const taskId = $('#taskNumber').text(); // Pega o taskNumber via jQuery
    verificarOuAbrirGuia(taskId);
});


// Submissão do formulário de criação de guia
$('#guiaRecebimentoForm').on('submit', function(e) {
    e.preventDefault();

    var formData = $(this).serialize() + '&task_id=' + $('#taskNumber').text();

    $.ajax({
        url: 'save_guia_recebimento.php',
        type: 'POST',
        data: formData,
        success: function(response) {
            try {
                var result = JSON.parse(response);
                if (result.success) {
                    $('#guiaRecebimentoModal').modal('hide');
                    alert('Guia de recebimento salva com sucesso!');
                    window.open('guia_recebimento.php?id=' + $('#taskNumber').text(), '_blank');
                } else {
                    console.error(result.error);
                    alert('Erro ao salvar a guia de recebimento: ' + result.error);
                }
            } catch (e) {
                console.error('Erro ao parsear JSON:', e, response);
                alert('Erro ao salvar a guia de recebimento');
            }
        },
        error: function() {
            alert('Erro ao salvar a guia de recebimento');
        }
    });
});



function viewTask(taskToken) {
    $.ajax({
        url: 'view_task.php',
        type: 'GET',
        data: {
            token: taskToken
        },
        success: function(response) {
            var task = JSON.parse(response);

            // Carregar os dados da tarefa
            $('#viewTitle').val(task.titulo)
                .data('tasktoken', taskToken)
                .data('numeroOficio', task.numero_oficio)
                .data('reciboGerado', task.recibo_gerado) // Recibo de Entrega gerado
                .data('guiaGerado', task.guia_gerada); // Guia de Recebimento gerado

            $('#viewCategory').val(task.categoria_titulo);
            $('#viewOrigin').val(task.origem_titulo);
            $('#viewDeadline').val(new Date(task.data_limite).toLocaleString("pt-BR"));
            $('#viewEmployee').val(task.funcionario_responsavel);
            $('#viewRevisor').val(task.revisor);
            $('#viewDescription').val(task.descricao);
            $('#viewStatus').val(task.status).data('data-conclusao', task.data_conclusao);
            $('#createdBy').val(task.criado_por);
            $('#createdAt').val(new Date(task.data_criacao).toLocaleString("pt-BR"));
            $('#taskNumber').text(task.id); // Atualizar o número da tarefa aqui
            $('#viewConclusionDate').val(task.data_conclusao ? new Date(task.data_conclusao).toLocaleString("pt-BR") : '');

            // Gerenciamento de anexos
            var viewAttachments = $('#viewAttachments');
            viewAttachments.empty();
            if (task.caminho_anexo) {
                task.caminho_anexo.split(';').forEach(function(anexo, index) {
                    var fileName = anexo.split('/').pop();
                    var filePath = anexo.startsWith('/') ? anexo : '/' + anexo;
                    var attachmentItem = '<div class="anexo-item">' +
                        '<span>' + (index + 1) + '</span>' +
                        '<span>' + fileName + '</span>' +
                        '<button class="btn btn-info btn-sm visualizar-anexo" data-file="' + filePath + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                        '</div>';
                    viewAttachments.append(attachmentItem);
                });
            }

            // Gerenciamento da linha do tempo de comentários
            var commentTimeline = $('#commentTimeline');
            commentTimeline.empty();
            if (task.comentarios) {
                task.comentarios.forEach(function(comentario) {
                    var commentDate = new Date(comentario.data_comentario);
                    var commentDateFormatted = commentDate.toLocaleString("pt-BR");

                    var commentItem = '<div class="timeline-item">';
                    if (comentario.is_subtask) {
                        // Destaque para comentários de subtarefas
                        commentItem += '<div class="timeline-badge subtask"><i class="fa fa-comments-o" aria-hidden="true"></i></div>'; // Ícone especial para subtarefas
                        commentItem += '<div class="timeline-panel subtask-panel">'; // Estilo especial para subtarefas
                        commentItem += '<div class="timeline-heading"><h6 class="timeline-title">' + (comentario.funcionario || 'Desconhecido') + ' <small>' + commentDateFormatted + '</small></h6>';
                        commentItem += '<div class="subtask-title">Comentário de Subtarefa</div>'; // Título de "Comentário de Subtarefa"
                    } else {
                        commentItem += '<div class="timeline-badge primary"><i class="fa fa-commenting-o" aria-hidden="true"></i></div>';
                        commentItem += '<div class="timeline-panel">';
                        commentItem += '<div class="timeline-heading"><h6 class="timeline-title">' + (comentario.funcionario || 'Desconhecido') + ' <small>' + commentDateFormatted + '</small></h6>';
                    }

                    commentItem += '</div><div class="timeline-body"><p>' + comentario.comentario + '</p>';
                    
                    if (comentario.caminho_anexo) {
                        comentario.caminho_anexo.split(';').forEach(function(anexo) {
                            var fileName = anexo.split('/').pop();
                            var filePath = anexo.startsWith('/') ? anexo : '/' + anexo;
                            commentItem += '<div class="anexo-item">' +
                                '<span>' + fileName + '</span>' +
                                '<button class="btn btn-info btn-sm visualizar-anexo" data-file="' + filePath + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                                '</div>';
                        });
                    }

                    commentItem += '</div></div></div>';
                    commentTimeline.append(commentItem);
                });
            }

            // Exibir o modal da tarefa
            $('#viewTaskModal').modal('show');
        },
        error: function() {
            alert('Erro ao buscar a tarefa');
        }
    });
}


        $(document).on('click', '.visualizar-anexo', function() {
            var filePath = $(this).data('file');
            var baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
            if (!filePath.startsWith('/')) {
                filePath = '/' + filePath;
            }
            window.open(baseUrl + filePath, '_blank');
        });

        function editTask(taskId) {
            window.location.href = 'edit_task.php?id=' + taskId;
        }

        function deleteTask(taskId) {
            if (confirm('Tem certeza que deseja excluir esta tarefa?')) {
                $.ajax({
                    url: 'delete_task.php',
                    type: 'POST',
                    data: {
                        id: taskId
                    },
                    success: function(response) {
                        alert('Tarefa excluída com sucesso');
                        $('#searchForm').submit(); // Recarregar a lista de tarefas
                    },
                    error: function() {
                        alert('Erro ao excluir a tarefa');
                    }
                });
            }
        }

        $('#vincularOficioForm').on('submit', function(e) {
            e.preventDefault();

            var taskId = $('#viewTitle').data('tasktoken'); // Assume que o token da tarefa está armazenado aqui
            var numeroOficio = $('#numeroOficio').val();

            $.ajax({
                url: 'vincular_oficio.php',
                type: 'POST',
                data: {
                    taskToken: taskId,
                    numeroOficio: numeroOficio
                },
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.success) {
                        alert('Ofício vinculado com sucesso!');
                        $('#vincularOficioModal').modal('hide');
                        $('#vincularOficioButton').html('<i class="fa fa-eye" aria-hidden="true"></i> Visualizar Ofício').attr('onclick', 'viewOficio(\'' + numeroOficio + '\')').removeAttr('data-toggle data-target');
                        $('#viewTitle').data('numeroOficio', numeroOficio); // Atualizar o atributo de dados da tarefa
                        viewOficio(numeroOficio); // Abrir o ofício em uma nova guia
                    } else {
                        alert('Erro ao vincular o ofício');
                    }
                },
                error: function() {
                    alert('Erro ao vincular o ofício');
                }
            });
        });

        function viewOficio(numero) {
            // Faz a requisição para o arquivo JSON
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false, // Desabilita o cache
                success: function(data) {
                    let url = '';

                    // Verifica o valor do "timbrado" e ajusta a URL
                    if (data.timbrado === 'S') {
                        url = 'ver_oficio.php?numero=' + numero;
                    } else if (data.timbrado === 'N') {
                        url = 'ver-oficio.php?numero=' + numero;
                    }

                    // Abre a URL correspondente em uma nova aba
                    window.open(url, '_blank');
                },
                error: function() {
                    alert('Erro ao carregar o arquivo de configuração.');
                }
            });
        }

        
        $(document).ready(function() {
            // Função para obter o valor do parâmetro da URL
            function getUrlParameter(name) {
                name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
                var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
                var results = regex.exec(location.search);
                return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
            }

            // Verifica se existe um token na URL
            var taskToken = getUrlParameter('token');

            if (taskToken) {
                // Se houver um token, chama a função para visualizar a tarefa e abrir o modal
                viewTask(taskToken);
            }

            // Adiciona um evento para redirecionar a página quando o modal for fechado e se houver um token
            $('#viewTaskModal').on('hidden.bs.modal', function () {
                if (taskToken) { // Verifica se o token está presente
                    window.location.href = 'index.php'; // Redireciona para index.php quando o modal é fechado
                }
            });
        });


        // Ação para abrir o formulário usando SweetAlert2
        function showAddCommentForm() {
            Swal.fire({
                title: 'Adicionar Comentário e Anexos',
                html: `
                    <form id="swalCommentForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="swalCommentDescription">Comentário:</label>
                            <textarea class="form-control" id="swalCommentDescription" name="commentDescription" rows="5"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="swalCommentAttachments">Anexar arquivos:</label>
                            <input type="file" id="swalCommentAttachments" name="commentAttachments[]" multiple class="form-control-file">
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Salvar Comentário',
                cancelButtonText: 'Fechar',
                preConfirm: () => {
                    // Coletar dados do formulário antes de confirmar
                    const commentDescription = document.getElementById('swalCommentDescription').value;
                    const attachments = document.getElementById('swalCommentAttachments').files;

                    if (!commentDescription) {
                        Swal.showValidationMessage('O campo de comentário é obrigatório.');
                        return false;
                    }

                    return {
                        commentDescription: commentDescription,
                        attachments: attachments
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Criar um FormData para enviar os dados via AJAX
                    const formData = new FormData();
                    formData.append('commentDescription', result.value.commentDescription);

                    for (let i = 0; i < result.value.attachments.length; i++) {
                        formData.append('commentAttachments[]', result.value.attachments[i]);
                    }

                    // Fazer a requisição AJAX para salvar o comentário
                    $.ajax({
                        url: 'add_comment.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            Swal.fire({
                                title: 'Sucesso!',
                                text: 'Comentário adicionado com sucesso!',
                                icon: 'success'
                            }).then(() => {
                                // Atualizar a visualização da tarefa
                                location.reload();
                            });
                        },
                        error: function() {
                            Swal.fire({
                                title: 'Erro!',
                                text: 'Erro ao adicionar comentário.',
                                icon: 'error'
                            });
                        }
                    });
                }
            });
        }

        function abrirTarefaEmNovaGuia(tarefaId) {
            $.ajax({
                url: 'get_token.php',
                type: 'GET',
                data: { id: tarefaId },
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.token) {
                        var url = 'index_tarefa.php?token=' + result.token;
                        window.open(url, '_blank'); // Abre a nova aba com o token
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Token não encontrado para essa tarefa.'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao buscar o token da tarefa.'
                    });
                }
            });
        }

        $(document).on('show.bs.modal', function () {
            // Desativa a rolagem do fundo
            $('body').css('overflow', 'hidden');
        });

        $(document).on('hidden.bs.modal', function () {
            // Restaura a rolagem do fundo apenas se não houver mais modais abertos
            if ($('.modal.show').length === 0) {
                $('body').css('overflow', 'auto');
            }
        });

        // Adicionar rolagem ao modal principal após fechar o secundário
        $('#vincularOficioModal, #reciboEntregaModal, #guiaRecebimentoModal, #createSubTaskModal, #addCommentModal').on('hidden.bs.modal', function () {
            $('#viewTaskModal').css('overflow-y', 'auto');
        });


        document.addEventListener('DOMContentLoaded', function() {  
            const fileInput = document.getElementById('subTaskAttachments');  
            const selectedFilesDiv = document.getElementById('selectedFiles');  
            const uploadText = document.querySelector('.upload-text');  
            let filesArray = []; // Array para manter controle dos arquivos  

            fileInput.addEventListener('change', function(e) {  
                const files = Array.from(e.target.files);  
                updateFileList(files);  
            });  

            function updateFileList(newFiles) {  
                filesArray = newFiles; // Atualiza o array de arquivos  
                selectedFilesDiv.innerHTML = ''; // Limpa a lista atual  

                if (filesArray.length > 0) {  
                    // Adiciona contador de arquivos  
                    const counterDiv = document.createElement('div');  
                    counterDiv.className = 'files-counter';  
                    counterDiv.textContent = `${filesArray.length} arquivo(s) selecionado(s)`;  
                    selectedFilesDiv.appendChild(counterDiv);  

                    // Lista cada arquivo  
                    filesArray.forEach((file, index) => {  
                        const fileItem = document.createElement('div');  
                        fileItem.className = 'file-item';  

                        const fileInfo = document.createElement('div');  
                        fileInfo.className = 'file-info';  
                        
                        // Escolhe o ícone baseado no tipo do arquivo  
                        let fileIcon = 'fa-file-o';  
                        if (file.type.includes('image')) fileIcon = 'fa-file-image-o';  
                        else if (file.type.includes('pdf')) fileIcon = 'fa-file-pdf-o';  
                        else if (file.type.includes('word')) fileIcon = 'fa-file-word-o';  
                        else if (file.type.includes('excel')) fileIcon = 'fa-file-excel-o';  

                        fileInfo.innerHTML = `  
                            <i class="fa ${fileIcon}"></i>  
                            <span class="file-name">${file.name}</span>  
                            <span class="file-size">(${formatFileSize(file.size)})</span>  
                        `;  

                        const removeButton = document.createElement('button');  
                        removeButton.className = 'remove-file';  
                        removeButton.innerHTML = '<i class="fa fa-times"></i>';  
                        removeButton.onclick = () => removeFile(index);  

                        fileItem.appendChild(fileInfo);  
                        fileItem.appendChild(removeButton);  
                        selectedFilesDiv.appendChild(fileItem);  
                    });  

                    // Atualiza o texto do label  
                    uploadText.textContent = 'Adicionar mais arquivos';  
                } else {  
                    // Reseta o texto se não houver arquivos  
                    uploadText.textContent = 'Arraste os arquivos ou clique para selecionar';  
                    selectedFilesDiv.innerHTML = '';  
                }  
            }  

            function removeFile(index) {  
                const dt = new DataTransfer();  
                
                // Recria o FileList sem o arquivo removido  
                filesArray.forEach((file, i) => {  
                    if (i !== index) dt.items.add(file);  
                });  
                
                fileInput.files = dt.files; // Atualiza o input  
                updateFileList(Array.from(dt.files)); // Atualiza a lista visual  
            }  

            function formatFileSize(bytes) {  
                if (bytes === 0) return '0 Bytes';  
                const k = 1024;  
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];  
                const i = Math.floor(Math.log(bytes) / Math.log(k));  
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];  
            }  

            // Adiciona suporte para drag and drop  
            const dropZone = document.querySelector('.file-upload-label');  

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {  
                dropZone.addEventListener(eventName, preventDefaults, false);  
            });  

            function preventDefaults(e) {  
                e.preventDefault();  
                e.stopPropagation();  
            }  

            ['dragenter', 'dragover'].forEach(eventName => {  
                dropZone.addEventListener(eventName, highlight, false);  
            });  

            ['dragleave', 'drop'].forEach(eventName => {  
                dropZone.addEventListener(eventName, unhighlight, false);  
            });  

            function highlight(e) {  
                dropZone.classList.add('drag-hover');  
            }  

            function unhighlight(e) {  
                dropZone.classList.remove('drag-hover');  
            }  

            dropZone.addEventListener('drop', handleDrop, false);  

            function handleDrop(e) {  
                const dt = e.dataTransfer;  
                const files = Array.from(dt.files);  
                fileInput.files = dt.files;  
                updateFileList(files);  
            }  
        });


        document.addEventListener('DOMContentLoaded', function() {  
            const fileInput = document.getElementById('commentAttachments');  
            const selectedFilesDiv = document.getElementById('selectedFiles');  
            const uploadText = document.querySelector('.upload-text');  
            let filesArray = [];  

            fileInput.addEventListener('change', function(e) {  
                const files = Array.from(e.target.files);  
                updateFileList(files);  
            });  

            function updateFileList(newFiles) {  
                filesArray = newFiles;  
                selectedFilesDiv.innerHTML = '';  

                if (filesArray.length > 0) {  
                    const counterDiv = document.createElement('div');  
                    counterDiv.className = 'files-counter';  
                    counterDiv.textContent = `${filesArray.length} arquivo(s) selecionado(s)`;  
                    selectedFilesDiv.appendChild(counterDiv);  

                    filesArray.forEach((file, index) => {  
                        const fileItem = document.createElement('div');  
                        fileItem.className = 'file-item';  

                        const fileInfo = document.createElement('div');  
                        fileInfo.className = 'file-info';  
                        
                        let fileIcon = 'fa-file-o';  
                        if (file.type.includes('image')) fileIcon = 'fa-file-image-o';  
                        else if (file.type.includes('pdf')) fileIcon = 'fa-file-pdf-o';  
                        else if (file.type.includes('word')) fileIcon = 'fa-file-word-o';  
                        else if (file.type.includes('excel')) fileIcon = 'fa-file-excel-o';  

                        fileInfo.innerHTML = `  
                            <i class="fa ${fileIcon}"></i>  
                            <span class="file-name">${file.name}</span>  
                            <span class="file-size">(${formatFileSize(file.size)})</span>  
                        `;  

                        const removeButton = document.createElement('button');  
                        removeButton.className = 'remove-file';  
                        removeButton.innerHTML = '<i class="fa fa-times"></i>';  
                        removeButton.onclick = () => removeFile(index);  

                        fileItem.appendChild(fileInfo);  
                        fileItem.appendChild(removeButton);  
                        selectedFilesDiv.appendChild(fileItem);  
                    });  

                    uploadText.textContent = 'Adicionar mais arquivos';  
                } else {  
                    uploadText.textContent = 'Arraste os arquivos ou clique para selecionar';  
                }  
            }  

            function removeFile(index) {  
                const dt = new DataTransfer();  
                filesArray.forEach((file, i) => {  
                    if (i !== index) dt.items.add(file);  
                });  
                fileInput.files = dt.files;  
                updateFileList(Array.from(dt.files));  
            }  

            function formatFileSize(bytes) {  
                if (bytes === 0) return '0 Bytes';  
                const k = 1024;  
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];  
                const i = Math.floor(Math.log(bytes) / Math.log(k));  
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];  
            }  

            // Drag and Drop  
            const dropZone = document.querySelector('.file-upload-label');  

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {  
                dropZone.addEventListener(eventName, preventDefaults, false);  
            });  

            function preventDefaults(e) {  
                e.preventDefault();  
                e.stopPropagation();  
            }  

            ['dragenter', 'dragover'].forEach(eventName => {  
                dropZone.addEventListener(eventName, highlight, false);  
            });  

            ['dragleave', 'drop'].forEach(eventName => {  
                dropZone.addEventListener(eventName, unhighlight, false);  
            });  

            function highlight(e) {  
                dropZone.classList.add('drag-hover');  
            }  

            function unhighlight(e) {  
                dropZone.classList.remove('drag-hover');  
            }  

            dropZone.addEventListener('drop', handleDrop, false);  

            function handleDrop(e) {  
                const dt = e.dataTransfer;  
                const files = Array.from(dt.files);  
                fileInput.files = dt.files;  
                updateFileList(files);  
            }  
        });


        // Função para aplicar cor no select conforme o status selecionado
        function aplicarCorStatusSelect() {
            const select = document.getElementById('viewStatus');
            const status = select.value.toLowerCase().replaceAll(' ', '-').normalize('NFD').replace(/[\u0300-\u036f]/g, '');

            // Remove classes anteriores
            select.className = 'form-control-modern status-select';

            // Adiciona a classe correspondente
            select.classList.add(`status-${status}`);
        }

        // Evento para quando o status for alterado manualmente
        document.getElementById('viewStatus').addEventListener('change', aplicarCorStatusSelect);

        // Evento para quando abrir o modal, já aplicar a cor correta
        $('#viewTaskModal').on('shown.bs.modal', function() {
            aplicarCorStatusSelect();
        });

    </script>
    <?php
    include(__DIR__ . '/../rodape.php');
    ?>
</body>

</html>