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
    <style>

#compartilharAnexos {
    margin-left: 10px;
}

.form-check-inline {
    margin-left: 5px;
}

.form-group {
    margin-bottom: 1rem;
}

#subTaskAttachments {
    margin-top: 8px; 
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
        .timeline-badge.subtask {
            background-color: #ffc107;
        }

        .timeline-panel.subtask-panel {
            border-left: 4px solid #ffc107;
            background-color: #fffbe6;
        }

        .subtask-title {
            font-weight: bold;
            color: #ffc107;
            margin-bottom: 10px;
        }

        .timeline-panel.subtask-panel .timeline-body {
            background-color: #fffde7;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ffecb3;
        }

        .btn-edit {
            margin-left: 5px;
        }

        .btn-info {
            margin-left: 5px;
        }

        .priority-medium {
            background-color: #fff9c4 !important; 
        }

        .priority-high {
            background-color: #ffe082 !important; 
        }

        .priority-critical {
            background-color: #ff8a80 !important; 
        }
        .row-quase-vencida {
            background-color: #ffebcc!important; 
        }

        .row-vencida {
            background-color: #ffcccc!important; 
        }

        body.dark-mode .priority-medium td {
            background-color: #fff9c4 !important;
            color: #000!important;
        }

        body.dark-mode .priority-high td {
            background-color: #ffe082 !important; 
            color: #000!important;
        }

        body.dark-mode .priority-critical td {
            background-color: #ff8a80 !important; 
        }
        body.dark-mode .row-quase-vencida td {
            background-color: #ffebcc!important; 
            color: #000!important;
        }

        body.dark-mode .row-vencida td {
            background-color: #ffcccc!important; 
            color: #000!important;
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

        .status-sub-iniciada {
            background-color: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-sub-em-espera {
            background-color: #ffa500;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-sub-em-andamento {
            background-color: #0056b3;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-sub-concluida {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-sub-cancelada {
            background-color: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-sub-pendente {
            background-color: gray;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .priority-sub-medium {
            background-color: #fff9c4 !important; 
        }
        .priority-sub-high {
            background-color: #ffe082 !important; 
        }
        .priority-sub-critical {
                    background-color: #ff8a80 !important; 
        }


        .timeline {
            position: relative;
            padding: 20px 0;
            list-style: none;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
            left: 30px;
            margin-right: -1.5px;
        }

        .timeline-item {
            margin: 0;
            padding: 0 0 20px;
            position: relative;
        }

        .timeline-item::before,
        .timeline-item::after {
            content: "";
            display: table;
        }

        .timeline-item::after {
            clear: both;
        }

        .timeline-item .timeline-panel {
            position: relative;
            width: calc(100% - 75px);
            float: right;
            border: 1px solid #d4d4d4;
            background: #ffffff;
            border-radius: 2px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .timeline-item .timeline-panel::before {
            position: absolute;
            top: 10px;
            right: -15px;
            display: inline-block;
            border-top: 15px solid transparent;
            border-left: 15px solid #d4d4d4;
            border-right: 0 solid #d4d4d4;
            border-bottom: 15px solid transparent;
            content: " ";
        }

        .timeline-item .timeline-panel::after {
            position: absolute;
            top: 11px;
            right: -14px;
            display: inline-block;
            border-top: 14px solid transparent;
            border-left: 14px solid #ffffff;
            border-right: 0 solid #ffffff;
            border-bottom: 14px solid transparent;
            content: " ";
        }

        .timeline-item .timeline-badge {
            color: #fff;
            width: 48px;
            height: 48px;
            line-height: 52px;
            font-size: 1.4em;
            text-align: center;
            position: absolute;
            top: 0;
            left: 0;
            margin-right: -25px;
            background-color: #7c7c7c;
            z-index: 100;
            border-radius: 50%;
        }

        .timeline-item .timeline-badge.primary {
            background-color: #007bff;
        }

        .timeline-item .timeline-badge.success {
            background-color: #28a745;
        }

        .timeline-item .timeline-badge.warning {
            background-color: #ffc107;
        }

        .timeline-item .timeline-badge.danger {
            background-color: #dc3545;
        }

        body.dark-mode .timeline::before {
            background: #444;
        }

        body.dark-mode .timeline-item .timeline-panel {
            background: #333;
            border-color: #444;
            color: #ddd;
        }

        body.dark-mode .timeline-item .timeline-panel::before {
            border-left-color: #444;
        }

        body.dark-mode .timeline-item .timeline-panel::after {
            border-left-color: #333;
        }
    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisa de Tarefas</h3>
            <hr>
            <form id="searchForm">
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label for="protocol">Protocolo Geral:</label>
                        <input type="text" class="form-control" id="protocol" name="protocol">
                    </div>
                    <div class="form-group col-md-4">
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
                    <div class="form-group col-md-2">
                        <label for="status">Status:</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Selecione</option>
                            <option value="Iniciada">Iniciada</option>
                            <option value="Em Espera">Em Espera</option>
                            <option value="Em Andamento">Em Andamento</option>
                            <option value="Concluída">Concluída</option>
                            <option value="Cancelada">Cancelada</option>
                            <option value="Pendente">Pendente</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
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
                    <div class="form-group col-md-4">
                        <label for="employee">Funcionário Responsável:</label>
                        <select id="employee" name="employee" class="form-control">
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
            <hr>
            <div class="table-responsive">
                <h5>Resultados da Pesquisa</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 90%">
                    <thead>
                        <tr>
                            <th>Nº Protocolo</th>
                            <th style="width: 15%">Título</th>
                            <th style="width: 10%">Categoria</th>
                            <th style="width: 9%">Origem</th>
                            <th style="width: 20%">Descrição</th>
                            <th style="width: 9%">Data Limite</th>
                            <th style="width: 12%">Funcionário</th>
                            <th>Prioridade</th>
                            <th>Status</th>
                            <th>Situação</th>
                            <th style="width: 8%">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="taskTable">
                        <!-- Dados das tarefas serão inseridos aqui -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="viewTaskModal" tabindex="-1" role="dialog" aria-labelledby="viewTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header d-block text-center">
                    <h5 class="modal-title" id="viewTaskModalLabel">Dados da Tarefa - Protocolo Geral nº.: <span id="taskNumber"></span></h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" style="position: absolute; top: 5px; right: 15px;">
                        &times;
                    </button>
                </div>

                <div class="modal-header d-block text-center">
                    <div class="btn-group" role="group" aria-label="Ações da Tarefa">
                        <button style="font-size:12px" id="guiaProtocoloButton" type="button" class="btn btn-secondary mr-2">
                            <i class="fa fa-print" aria-hidden="true"></i> Guia de Protocolo Geral
                        </button>
                        <button style="font-size:12px" id="guiaRecebimentoButton" type="button" class="btn btn-info2 mr-2">
                            <i class="fa fa-file-text" aria-hidden="true"></i> Guia de Recebimento
                        </button>
                        <button style="font-size:12px" id="add-button" type="button" class="btn btn-success mr-2" onclick="window.open('../oficios/cadastrar-oficio.php', '_blank')">
                            <i class="fa fa-plus" aria-hidden="true"></i> Criar Ofício
                        </button>
                        <button style="font-size:12px" id="vincularOficioButton" type="button" class="btn btn-primary mr-2" data-toggle="modal" data-target="#vincularOficioModal">
                            <i class="fa fa-link" aria-hidden="true"></i> Vincular Ofício
                        </button>
                        <button style="font-size:12px" id="reciboEntregaButton" type="button" class="btn btn-info2 mr-2">
                            <i class="fa fa-file-text" aria-hidden="true"></i> Recibo de Entrega
                        </button>
                    </div>
                </div>

                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="viewTitle">Título:</label>
                            <input type="text" class="form-control" id="viewTitle" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="viewCategory">Categoria:</label>
                            <input type="text" class="form-control" id="viewCategory" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="viewOrigin">Origem:</label>
                            <input type="text" class="form-control" id="viewOrigin" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="viewDeadline">Data Limite:</label>
                            <input type="text" class="form-control" id="viewDeadline" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="viewEmployee">Funcionário Responsável:</label>
                            <input type="text" class="form-control" id="viewEmployee" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="viewConclusionDate">Data de Conclusão:</label>
                            <input type="text" class="form-control" id="viewConclusionDate" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="viewDescription">Descrição:</label>
                        <textarea class="form-control" id="viewDescription" rows="5" readonly></textarea>
                    </div>
                    <div class="form-group">
                        <label for="viewStatus">Status:</label>
                        <div class="input-group">
                            <select id="viewStatus" class="form-control">
                                <option value="Iniciada">Iniciada</option>
                                <option value="Em Espera">Em Espera</option>
                                <option value="Em Andamento">Em Andamento</option>
                                <option value="Concluída">Concluída</option>
                                <option value="Cancelada">Cancelada</option>
                            </select>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-success" id="saveStatusButton">Salvar Status</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="createdBy">Criado por:</label>
                            <input type="text" class="form-control" id="createdBy" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="createdAt">Data de Criação:</label>
                            <input type="text" class="form-control" id="createdAt" readonly>
                        </div>
                    </div>
                    <h4>Anexos</h4>
                    <div id="viewAttachments" class="list-group">
                        <!-- Lista de anexos será inserida aqui -->
                    </div>
                    <hr>
                    <button style="margin-bottom:20px; width: 100%; " id="createSubTaskButton" type="button" class="btn btn-primary" data-toggle="modal" data-target="#createSubTaskModal">
                        <i class="fa fa-plus" aria-hidden="true"></i> Criar subtarefa
                    </button>
                    <!-- Tabela da Tarefa Principal -->
                    <h4 id="mainTaskHeader" style="display: none;">Tarefa Principal</h4>
                    <table style="zoom: 85%; display: none;" id="mainTaskTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Protocolo</th>
                                <th>Título da Tarefa Principal</th>
                                <th>Funcionário Responsável</th>
                                <th>Data de Criação</th>
                                <th>Data Limite</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="mainTaskTableBody">
                            <!-- Linha da tarefa principal será inserida aqui via JavaScript -->
                        </tbody>
                    </table>

                    <hr>

                    <!-- Tabela de Subtarefas -->
                    <h4 id="subTasksHeader" style="display: none;">Subtarefas</h4>
                    <table style="zoom: 85%; display: none;" id="subTasksTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Protocolo</th>
                                <th>Título da Subtarefa</th>
                                <th>Funcionário Responsável</th>
                                <th>Data de Criação</th>
                                <th>Data Limite</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="subTasksTableBody">
                            <!-- Linhas de subtarefas serão inseridas aqui via JavaScript -->
                        </tbody>
                    </table>
                    <hr>
                    <h4>Timeline</h4>
                    <div id="commentTimeline" class="timeline">
                        <!-- Comentários serão inseridos aqui -->
                    </div>
                    <button type="button" class="btn btn-primary" id="addCommentButton" data-toggle="modal" data-target="#addCommentModal"><i class="fa fa-plus-circle" aria-hidden="true"></i> Comentário e Anexos</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Adicionar Comentário -->
    <div class="modal fade" id="addCommentModal" tabindex="-1" role="dialog" aria-labelledby="addCommentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document" style="max-width: 60%;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCommentModalLabel">Adicionar Comentário e Anexos</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                        &times;
                    </button>
                </div>
                <div class="modal-body">
                    <form id="commentForm">
                        <div class="form-group">
                            <label for="commentDescription">Comentário:</label>
                            <textarea class="form-control" id="commentDescription" name="commentDescription" rows="5"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="commentAttachments">Anexar arquivos:</label>
                            <input type="file" id="commentAttachments" name="commentAttachments[]" multiple class="form-control-file">
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar Comentário</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Vincular Ofício -->
    <div class="modal fade" id="vincularOficioModal" tabindex="-1" role="dialog" aria-labelledby="vincularOficioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vincularOficioModalLabel">Vincular Ofício</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                        &times;
                    </button>

                </div>
                <div class="modal-body">
                    <form id="vincularOficioForm">
                        <div class="form-group">
                            <label for="numeroOficio">Número do Ofício:</label>
                            <input type="text" class="form-control" id="numeroOficio" name="numeroOficio">
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Recibo de Entrega -->
    <div class="modal fade" id="reciboEntregaModal" tabindex="-1" role="dialog" aria-labelledby="reciboEntregaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reciboEntregaModalLabel">Recibo de Entrega</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                        &times;
                    </button>
                </div>
                <div class="modal-body">
                    <form id="reciboEntregaForm">
                        <div class="form-group">
                            <label for="receptor">Nome do Receptor:</label>
                            <input type="text" class="form-control" id="receptor" name="receptor" required>
                        </div>
                        <div class="form-group">
                            <label for="dataEntrega">Data da Entrega:</label>
                            <input type="datetime-local" class="form-control" id="dataEntrega" name="dataEntrega" required>
                        </div>
                        <div class="form-group">
                            <label for="documentos">Documentos Entregues:</label>
                            <textarea class="form-control" id="documentos" name="documentos" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="observacoes">Observações:</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="entregador">Nome do Entregador:</label>
                            <input type="text" class="form-control" id="entregador" name="entregador" readonly>
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar Recibo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Guia de Recebimento -->
    <div class="modal fade" id="guiaRecebimentoModal" tabindex="-1" role="dialog" aria-labelledby="guiaRecebimentoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="guiaRecebimentoModalLabel">Guia de Recebimento</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                        &times;
                    </button>
                </div>
                <div class="modal-body">
                    <form id="guiaRecebimentoForm">
                        <div class="form-group">
                            <label for="cliente">Apresentante:</label>
                            <input type="text" class="form-control" id="cliente" name="cliente" required>
                        </div>
                        <div class="form-group">
                            <label for="dataRecebimento">Data de Recebimento:</label>
                            <input type="datetime-local" class="form-control" id="dataRecebimento" name="dataRecebimento" required>
                        </div>
                        <div class="form-group">
                            <label for="documentosRecebidos">Documentos Recebidos:</label>
                            <textarea class="form-control" id="documentosRecebidos" name="documentosRecebidos" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="observacoes">Observações:</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="funcionario">Funcionário:</label>
                            <input type="text" class="form-control" id="funcionario" name="funcionario" readonly>
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar Guia</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal Criar Subtarefa -->
<div class="modal fade" id="createSubTaskModal" tabindex="-1" role="dialog" aria-labelledby="createSubTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 60%;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSubTaskModalLabel">Criar Subtarefa</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <form id="subTaskForm" enctype="multipart/form-data" method="POST" action="save_sub_task.php">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="subTaskTitle">Título da Subtarefa:</label>
                            <input type="text" class="form-control" id="subTaskTitle" name="title" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="subTaskCategory">Categoria:</label>
                            <select id="subTaskCategory" name="category" class="form-control" required>
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
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="subTaskDeadline">Data Limite:</label>
                            <input type="datetime-local" class="form-control" id="subTaskDeadline" name="deadline" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="subTaskPriority">Nível de Prioridade:</label>
                            <select id="subTaskPriority" name="priority" class="form-control" required>
                                <option value="">Selecione</option>
                                <option value="Baixa">Baixa</option>
                                <option value="Média">Média</option>
                                <option value="Alta">Alta</option>
                                <option value="Crítica">Crítica</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="subTaskEmployee">Funcionário Responsável:</label>
                            <select id="subTaskEmployee" name="employee" class="form-control" required>
                                <option value="">Selecione</option>
                                    <?php
                                    $sql = "SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo'";
                                    $result = $conn->query($sql);
                                    $loggedInUser = $_SESSION['username'];

                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            $selected = ($row['nome_completo'] == $loggedInUser) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";
                                        }
                                    }
                                    ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="subTaskOrigin">Origem:</label>
                            <select id="subTaskOrigin" name="origin" class="form-control" required>
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
                    <div class="form-group">
                        <label for="subTaskDescription">Descrição:</label>
                        <textarea class="form-control" id="subTaskDescription" name="description" rows="5"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="d-flex align-items-center">
                            <label for="subTaskAttachments" class="mr-2">Anexos:</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="compartilharAnexos" name="compartilharAnexos">
                                <label class="form-check-label" for="compartilharAnexos">Compartilhar anexos da tarefa principal</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-2">
                        <input type="file" id="subTaskAttachments" name="attachments[]" multiple class="form-control-file">
                    </div>

                    <script>
                        $('#compartilharAnexos').change(function () {
                            if ($(this).is(':checked')) {
                                $('#subTaskAttachments').prop('disabled', true);
                            } else {
                                $('#subTaskAttachments').prop('disabled', false);
                            }
                        });
                    </script>

                    <input type="hidden" id="subTaskCreatedBy" name="createdBy" value="<?php echo $_SESSION['username']; ?>">
                    <input type="hidden" id="subTaskCreatedAt" name="createdAt" value="<?php echo date('Y-m-d H:i:s'); ?>">
                    <input type="hidden" id="subTaskPrincipalId" name="id_tarefa_principal">
                    <button type="submit" class="btn btn-primary w-100">Salvar Subtarefa</button>
                </form>
            </div>
        </div>
    </div>
</div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
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
        default:
            return '';
    }
}

// Função para definir a classe de linha com base na data limite
function getRowClass(status, data_limite) {
    var deadlineDate = new Date(data_limite);
    var currentDate = new Date();
    var oneDay = 24 * 60 * 60 * 1000;

    if (status !== 'concluída' && status !== 'cancelada') {
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
        case 'Baixa':
            return 'priority-low';
        case 'Média':
            return 'priority-sub-medium';
        case 'Alta':
            return 'priority-sub-high';
        case 'Crítica':
            return 'priority-sub-critical';
        default:
            return '';
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
                    
                    // Limpar os dados da tabela DataTables sem destruir a instância
                    dataTable.clear();

                    // Verificar se há tarefas
                    if (tasks.length > 0) {
                        // Popular a tabela com os novos dados
                        tasks.forEach(function(task) {
                            // Definir classe de status
                            var statusClass = getStatusClass(task.status.toLowerCase());

                            var rowClass = '';

                            // Aplicar regras de coloração apenas se o status não for "Concluída" ou "Cancelada"
                            if (task.status.toLowerCase() !== 'concluída' && task.status.toLowerCase() !== 'cancelada') {
                                // Verificar vencimento e definir classe de linha
                                rowClass = getRowClass(task.status.toLowerCase(), task.data_limite);

                                // Se a tarefa não estiver vencida, aplicar a classe de prioridade
                                if (!rowClass) {
                                    rowClass = getPriorityClass(task.nivel_de_prioridade);
                                }
                            }

                            // Definir os botões de ação
                            var actions = '<button class="btn btn-info btn-sm" onclick="viewTask(\'' + task.token + '\')"><i class="fa fa-eye" aria-hidden="true"></i></button>';
                            if (task.status.toLowerCase() !== 'concluída') {
                                actions += '<button class="btn btn-edit btn-sm" onclick="editTask(' + task.id + ')"><i class="fa fa-pencil" aria-hidden="true"></i></button>';
                            }

                                // Função para adicionar classes de status baseado no estado para o fundo e texto
                                function getStatusClassBackground(situacao) {
                                    switch (situacao) {
                                        case 'Prestes a vencer':
                                            return 'status-prestes-vencer';  // Classe para fundo e texto de "Prestes a vencer"
                                        case 'Vencida':
                                            return 'status-vencida';  // Classe para fundo e texto de "Vencida"
                                        default:
                                            return '';
                                    }
                                }

                                var descricaoLimitada = task.descricao.length > 80 ? task.descricao.substring(0, 80) + '...' : task.descricao;

                                var situacao = '';
                                if (rowClass === 'row-vencida') {
                                    situacao = 'Vencida';
                                } else if (rowClass === 'row-quase-vencida') {
                                    situacao = 'Prestes a vencer';
                                } else {
                                    situacao = '-';
                                }

                                // Adicionar a classe de fundo e texto para a situação
                                var statusClassBackground = getStatusClassBackground(situacao);

                                var row = dataTable.row.add([
                                    task.id,
                                    task.titulo,
                                    task.categoria_titulo,
                                    task.origem_titulo,
                                    descricaoLimitada, // Limita a descrição a 80 caracteres
                                    new Date(task.data_limite).toLocaleString("pt-BR"),
                                    task.funcionario_responsavel,
                                    task.nivel_de_prioridade,
                                    '<span class="status-label ' + statusClass + '">' + capitalize(task.status) + '</span>',
                                    '<span class="status-label ' + statusClassBackground + '">' + situacao + '</span>', // Coluna "Situação" com classe dinâmica
                                    actions
                                ]).draw().node();

                                // Aplicar a classe de coloração na linha
                                $(row).addClass(rowClass);

                        });
                    } else {
                        // Exibir mensagem de "Nenhum resultado encontrado" se não houver tarefas
                        $('#taskTable').html('<tr><td colspan="10" class="text-center">Nenhum resultado encontrado</td></tr>');
                    }
                },
                error: function() {
                    alert('Erro ao buscar as tarefas');
                }
            });
        }

        // Função para retornar a classe de status
        function getStatusClass(status) {
            switch (status) {
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

        // Função para definir a classe de linha com base na data limite
        function getRowClass(status, data_limite) {
            var deadlineDate = new Date(data_limite);
            var currentDate = new Date();
            var oneDay = 24 * 60 * 60 * 1000;

            if (status !== 'concluída' && status !== 'cancelada') {
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
                case 'Baixa':
                    return 'priority-low';
                case 'Média':
                    return 'priority-medium';
                case 'Alta':
                    return 'priority-high';
                case 'Crítica':
                    return 'priority-critical';
                default:
                    return '';
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
            var taskToken = $('#viewTitle').data('tasktoken'); // Assume que o token da tarefa está armazenado como atributo de dados

            formData.append('taskToken', taskToken);

            $.ajax({
                url: 'add_comment.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#addCommentModal').modal('hide');
                    $('body').removeClass('modal-open'); // Corrigir problema de rolagem

                    Swal.fire({
                        title: 'Sucesso!',
                        text: 'Comentário adicionado com sucesso!',
                        icon: 'success'
                    }).then(() => {
                        viewTask(taskToken); // Atualizar a visualização da tarefa
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


        $('#saveStatusButton').on('click', function() {
            var taskToken = $('#viewTitle').data('tasktoken');
            var status = $('#viewStatus').val();
            var currentDate = new Date().toISOString().slice(0, 19).replace('T', ' ');

            Swal.fire({
                title: 'Tem certeza?',
                text: 'Deseja realmente atualizar o status da tarefa para "' + status + '"?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, atualizar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Se o usuário confirmar, proceder com a atualização
                    $.ajax({
                        url: 'update_status.php',
                        type: 'POST',
                        data: {
                            taskToken: taskToken,
                            status: status,
                            dataConclusao: status.toLowerCase() === 'concluída' ? currentDate : null
                        },
                        success: function(response) {
                            // SweetAlert2 para sucesso, mas sem fechar o modal
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Status atualizado com sucesso!'
                            }).then(() => {
                                $('#searchForm').submit(); // Atualizar a lista de tarefas, se necessário
                                // O modal continuará aberto após a atualização
                            });
                        },
                        error: function() {
                            // SweetAlert2 para erro
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Ocorreu um erro ao atualizar o status.'
                            });
                        }
                    });
                }
            });
        });

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
                                alert('Recibo de entrega salvo com sucesso!');
                                window.open('recibo-entrega.php?id=' + $('#taskNumber').text(), '_blank');
                            } else {
                                console.error(result.error);
                                alert('Erro ao salvar o recibo de entrega: ' + result.error);
                            }
                        } catch (e) {
                            console.error('Erro ao parsear JSON:', e, response);
                            alert('Erro ao salvar o recibo de entrega');
                        }
                    },
                    error: function() {
                        alert('Erro ao salvar o recibo de entrega');
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
                    window.open('guia-recebimento.php?id=' + $('#taskNumber').text(), '_blank');
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

    </script>
    <?php
    include(__DIR__ . '/../rodape.php');
    ?>
</body>

</html>