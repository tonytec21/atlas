<?php
include(__DIR__ . '/session_check.php');
checkSession();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font/css/materialdesignicons.min.css">
    <style>
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
        .timeline-item::before, .timeline-item::after {
            content: "";
            display: table;
        }
        .timeline-item::after {
            clear: both;
        }
        .timeline-item::before, .timeline-item::after {
            content: " ";
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
    </style>
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
                    <div class="form-group col-md-3">
                        <label for="title">Título da Tarefa:</label>
                        <input type="text" class="form-control" id="title" name="title">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="category">Categoria:</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">Selecione</option>
                            <option value="Novo">Novo</option>
                            <option value="Em Progresso">Em Progresso</option>
                            <option value="Concluído">Concluído</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="employee">Funcionário Responsável:</label>
                        <input type="text" class="form-control" id="employee" name="employee">
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
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-9">
                        <label for="description">Descrição:</label>
                        <input type="text" class="form-control" id="description" name="description">
                    </div>
                    <div class="form-group col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        <button id="add-button" class="btn btn-success ml-2 w-100" onclick="window.location.href='cadastro.php'">+ Adicionar</button>
                    </div>
                </div>
            </form>
            <div class="mt-3">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Descrição</th>
                            <th>Data Limite</th>
                            <th>Funcionário</th>
                            <th>Status</th>
                            <th>Ações</th>
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
                <div class="modal-header">
                    <h5 class="modal-title" id="viewTaskModalLabel">Dados da Tarefa</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
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
                            <label for="viewDeadline">Data Limite:</label>
                            <input type="text" class="form-control" id="viewDeadline" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="viewEmployee">Funcionário Responsável:</label>
                            <input type="text" class="form-control" id="viewEmployee" readonly>
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
                    <h4>Timeline</h4>
                    <div id="commentTimeline" class="timeline">
                        <!-- Comentários serão inseridos aqui -->
                    </div>
                    <button type="button" class="btn btn-primary" id="addCommentButton" data-toggle="modal" data-target="#addCommentModal">Adicionar Comentário</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Adicionar Comentário -->
    <div class="modal fade" id="addCommentModal" tabindex="-1" role="dialog" aria-labelledby="addCommentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCommentModalLabel">Adicionar Comentário</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
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

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script>
        function openNav() {
            document.getElementById("mySidebar").style.width = "250px";
            document.getElementById("main").style.marginLeft = "250px";
        }

        function closeNav() {
            document.getElementById("mySidebar").style.width = "0";
            document.getElementById("main").style.marginLeft = "0";
        }

        function normalizeText(text) {
            return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        function formatDateTime(dateTime) {
            var date = new Date(dateTime);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
        }

        $(document).ready(function() {
            // Carregar o modo do usuário
            $.ajax({
                url: '../load_mode.php',
                method: 'GET',
                success: function(mode) {
                    $('body').removeClass('light-mode dark-mode').addClass(mode);
                }
            });

            // Função para alternar modos claro e escuro
            $('.mode-switch').on('click', function() {
                var body = $('body');
                body.toggleClass('dark-mode light-mode');

                var mode = body.hasClass('dark-mode') ? 'dark-mode' : 'light-mode';
                $.ajax({
                    url: '../save_mode.php',
                    method: 'POST',
                    data: { mode: mode },
                    success: function(response) {
                        console.log(response);
                    }
                });
            });

            // Enviar formulário de pesquisa
            $('#searchForm').on('submit', function(e) {
                e.preventDefault();

                var formData = $(this).serialize();

                $.ajax({
                    url: 'search_tasks.php',
                    type: 'GET',
                    data: formData,
                    success: function(response) {
                        var tasks = JSON.parse(response);
                        var taskTable = $('#taskTable');
                        taskTable.empty();
                        tasks.forEach(function(task) {
                            var statusClass = '';
                            switch (task.status) {
                                case 'Iniciada':
                                    statusClass = 'status-iniciada';
                                    break;
                                case 'Em Espera':
                                    statusClass = 'status-em-espera';
                                    break;
                                case 'Em Andamento':
                                    statusClass = 'status-em-andamento';
                                    break;
                                case 'Concluída':
                                    statusClass = 'status-concluida';
                                    break;
                                case 'Cancelada':
                                    statusClass = 'status-cancelada';
                                    break;
                            }
                            var row = '<tr>' +
                                '<td>' + task.title + '</td>' +
                                '<td>' + task.category + '</td>' +
                                '<td>' + task.description + '</td>' +
                                '<td>' + new Date(task.deadline).toLocaleString("pt-BR") + '</td>' +
                                '<td>' + task.employee + '</td>' +
                                '<td><span class="status-label ' + statusClass + '">' + task.status + '</span></td>' +
                                '<td>' +
                                    '<button class="btn btn-info btn-sm" onclick="viewTask(\'' + task.fileName + '\')"><i class="fa fa-eye" aria-hidden="true"></i></button> ' +
                                    '<button class="btn btn-edit btn-sm" onclick="editTask(\'' + task.fileName + '\')"><i class="fa fa-pencil" aria-hidden="true"></i></button> ' +
                                    '<button class="btn btn-delete btn-sm" onclick="deleteTask(\'' + task.fileName + '\')"><i class="fa fa-trash" aria-hidden="true"></i></button>' +
                                '</td>' +
                                '</tr>';
                            taskTable.append(row);
                        });
                    },
                    error: function() {
                        alert('Erro ao buscar as tarefas');
                    }
                });
            });

            $('#commentForm').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                var fileName = $('#viewTitle').data('filename'); // Assume that filename is stored as data attribute

                formData.append('fileName', fileName);

                $.ajax({
                    url: 'add_comment.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#addCommentModal').modal('hide');
                        alert('Comentário adicionado com sucesso!');
                        viewTask(fileName); // Refresh the task view
                    },
                    error: function() {
                        alert('Erro ao adicionar comentário');
                    }
                });
            });

            $('#saveStatusButton').on('click', function() {
                var fileName = $('#viewTitle').data('filename');
                var status = $('#viewStatus').val();

                $.ajax({
                    url: 'update_status.php',
                    type: 'POST',
                    data: { fileName: fileName, status: status },
                    success: function(response) {
                        alert('Status atualizado com sucesso!');
                        $('#viewTaskModal').modal('hide');
                        $('#searchForm').submit(); // Refresh the task list
                    },
                    error: function() {
                        alert('Erro ao atualizar o status');
                    }
                });
            });
        });

        function viewTask(fileName) {
            $.ajax({
                url: 'view_task.php',
                type: 'GET',
                data: { file: fileName },
                success: function(response) {
                    var task = JSON.parse(response);
                    $('#viewTitle').val(task.title).data('filename', fileName);
                    $('#viewCategory').val(task.category);
                    $('#viewDeadline').val(new Date(task.deadline).toLocaleString("pt-BR"));
                    $('#viewEmployee').val(task.employee);
                    $('#viewDescription').val(task.description);
                    $('#viewStatus').val(task.status);
                    $('#createdBy').val(task.createdBy);
                    $('#createdAt').val(task.createdAt);

                    var viewAttachments = $('#viewAttachments');
                    viewAttachments.empty();
                    task.attachments.forEach(function(attachment, index) {
                        var fileName = attachment.split('/').pop();
                        var attachmentItem = '<div class="anexo-item">' +
                            '<span>' + (index + 1) + '</span>' +
                            '<span>' + fileName + '</span>' +
                            '<button class="btn btn-info btn-sm visualizar-anexo" data-file="' + attachment + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                            '</div>';
                        viewAttachments.append(attachmentItem);
                    });

                    var commentTimeline = $('#commentTimeline');
                    commentTimeline.empty();
                    if (task.comments) {
                        task.comments.forEach(function(comment) {
                            var commentDate = new Date(comment.date);
                            var commentDateFormatted = commentDate.toLocaleString("pt-BR");

                            var commentItem = '<div class="timeline-item">' +
                                '<div class="timeline-badge primary"><i class="mdi mdi-comment"></i></div>' +
                                '<div class="timeline-panel">' +
                                    '<div class="timeline-heading">' +
                                        '<h6 class="timeline-title">' + (comment.employee || 'Desconhecido') + ' <small>' + commentDateFormatted + '</small></h6>' +
                                    '</div>' +
                                    '<div class="timeline-body">' +
                                        '<p>' + comment.description + '</p>';
                            if (comment.attachments) {
                                comment.attachments.forEach(function(attachment) {
                                    var fileName = attachment.split('/').pop();
                                    commentItem += '<div class="anexo-item">' +
                                        '<span>' + fileName + '</span>' +
                                        '<button class="btn btn-info btn-sm visualizar-anexo" data-file="' + attachment + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                                        '</div>';
                                });
                            }
                            commentItem += '</div></div></div>';
                            commentTimeline.append(commentItem);
                        });
                    }

                    $('#viewTaskModal').modal('show');
                },
                error: function() {
                    alert('Erro ao buscar a tarefa');
                }
            });
        }

        $(document).on('click', '.visualizar-anexo', function() {
            var filePath = $(this).data('file');
            window.open(filePath, '_blank');
        });

        function editTask(fileName) {
            window.location.href = 'edit_task.php?file=' + fileName;
        }

        function deleteTask(fileName) {
            if (confirm('Tem certeza que deseja excluir esta tarefa?')) {
                $.ajax({
                    url: 'delete_task.php',
                    type: 'POST',
                    data: { file: fileName },
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
    </script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
