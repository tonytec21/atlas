<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
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
        .status-iniciada { background-color: #007bff; }
        .status-em-espera { background-color: #ffa500; }
        .status-em-andamento { background-color: #0056b3; }
        .status-concluida { background-color: #28a745; }
        .status-cancelada { background-color: #dc3545; }
        .status-pendente { background-color: #f4f4f4; color: #222; }
        .timeline { position: relative; padding: 20px 0; list-style: none; }
        .timeline::before { content: ''; position: absolute; top: 0; bottom: 0; width: 2px; background: #e9ecef; left: 30px; margin-right: -1.5px; }
        .timeline-item { margin: 0; padding: 0 0 20px; position: relative; }
        .timeline-item::before, .timeline-item::after { content: ""; display: table; }
        .timeline-item::after { clear: both; }
        .timeline-item .timeline-panel { position: relative; width: calc(100% - 75px); float: right; border: 1px solid #d4d4d4; background: #ffffff; border-radius: 2px; padding: 20px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
        .timeline-item .timeline-panel::before { position: absolute; top: 10px; right: -15px; display: inline-block; border-top: 15px solid transparent; border-left: 15px solid #d4d4d4; border-right: 0 solid #d4d4d4; border-bottom: 15px solid transparent; content: " "; }
        .timeline-item .timeline-panel::after { position: absolute; top: 11px; right: -14px; display: inline-block; border-top: 14px solid transparent; border-left: 14px solid #ffffff; border-right: 0 solid #ffffff; border-bottom: 14px solid transparent; content: " "; }
        .timeline-item .timeline-badge { color: #fff; width: 48px; height: 48px; line-height: 52px; font-size: 1.4em; text-align: center; position: absolute; top: 0; left: 0; margin-right: -25px; background-color: #7c7c7c; z-index: 100; border-radius: 50%; }
        .timeline-item .timeline-badge.primary { background-color: #007bff; }
        .timeline-item .timeline-badge.success { background-color: #28a745; }
        .timeline-item .timeline-badge.warning { background-color: #ffc107; }
        .timeline-item .timeline-badge.danger { background-color: #dc3545; }
        .row-quase-vencida {
            background-color: #ffebcc;
        }
        .row-vencida {
            background-color: #ffcccc;
        }
        .row-quase-vencida.dark-mode {
            background-color: #ffebcc;
        }
        .row-vencida.dark-mode {
            background-color: #ffcccc;
        }

        /* Dark mode styles */
        body.dark-mode .timeline::before { background: #444; }
        body.dark-mode .timeline-item .timeline-panel { background: #333; border-color: #444; color: #ddd; }
        body.dark-mode .timeline-item .timeline-panel::before { border-left-color: #444; }
        body.dark-mode .timeline-item .timeline-panel::after { border-left-color: #333; }
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
                <div class="form-group col-md-3">
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
                    <label for="employee">Funcionário Responsável:</label>
                    <select id="employee" name="employee" class="form-control">
                        <option value="">Selecione</option>
                        <?php
                        // Buscando os funcionários diretamente do banco de dados "atlas"
                        $connAtlas = new mysqli("localhost", "root", "", "atlas");
                        if ($connAtlas->connect_error) {
                            die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
                        }

                        $sql = "SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo'";
                        $result = $connAtlas->query($sql);
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";
                            }
                        }
                        $connAtlas->close();
                        ?>
                    </select>
                </div>
                <div class="form-group col-md-9">
                    <label for="description">Descrição:</label>
                    <input type="text" class="form-control" id="description" name="description">
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
        <div class="mt-3">
            <table class="table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Categoria</th>
                        <th>Origem</th>
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
                <h5 class="modal-title" id="viewTaskModalLabel">Dados da Tarefa nº <span id="taskNumber"></span></h5>
                <button id="add-button" type="button" style="width: 130px; margin-left: 170px; margin-top: -8px;" class="btn btn-success" onclick="window.open('../oficios/cadastrar-oficio.php', '_blank')"><i class="fa fa-plus" aria-hidden="true"></i> Criar Ofício</button>
                <button id="vincularOficioButton" type="button" style="width: 170px; margin-left: 2%; margin-top: -8px;" class="btn btn-primary" data-toggle="modal" data-target="#vincularOficioModal"><i class="fa fa-link" aria-hidden="true"></i> Vincular Ofício</button>
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

<!-- Modal Vincular Ofício -->
<div class="modal fade" id="vincularOficioModal" tabindex="-1" role="dialog" aria-labelledby="vincularOficioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vincularOficioModalLabel">Vincular Ofício</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
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

    // Carregar funcionários do banco de dados
    $.ajax({
        url: 'load_employees.php',
        method: 'GET',
        success: function(response) {
            if (response.error) {
                alert(response.error);
                return;
            }
            
            var employees = response;
            var employeeSelect = $('#employee');
            employeeSelect.empty();
            employeeSelect.append('<option value="">Selecione</option>');
            employees.forEach(function(employee) {
                var option = '<option value="' + employee.nome_completo + '">' + employee.nome_completo + '</option>';
                employeeSelect.append(option);
            });
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar os funcionários:', status, error);
            alert('Erro ao carregar os funcionários');
        }
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
                    switch (task.status.toLowerCase()) {
                        case 'iniciada':
                            statusClass = 'status-iniciada';
                            break;
                        case 'em espera':
                            statusClass = 'status-em-espera';
                            break;
                        case 'em andamento':
                            statusClass = 'status-em-andamento';
                            break;
                        case 'concluída':
                            statusClass = 'status-concluida';
                            break;
                        case 'cancelada':
                            statusClass = 'status-cancelada';
                            break;
                        case 'pendente':
                            statusClass = 'status-pendente';
                            break;
                    }

                    var rowClass = '';
                    var deadlineDate = new Date(task.data_limite);
                    var currentDate = new Date();
                    var oneDay = 24 * 60 * 60 * 1000;

                    if (task.status.toLowerCase() !== 'concluída' && task.status.toLowerCase() !== 'cancelada') {
                        if (deadlineDate < currentDate) {
                            rowClass = 'row-vencida';
                        } else if ((deadlineDate - currentDate) <= oneDay) {
                            rowClass = 'row-quase-vencida';
                        }
                    }

                    var actions = '<button class="btn btn-info btn-sm" onclick="viewTask(\'' + task.token + '\')"><i class="fa fa-eye" aria-hidden="true"></i></button> ';
                    if (task.status.toLowerCase() !== 'concluída') {
                        actions += '<button class="btn btn-edit btn-sm" onclick="editTask(' + task.id + ')"><i class="fa fa-pencil" aria-hidden="true"></i></button> ';
                        if (task.status.toLowerCase() === 'pendente') {
                            actions += '<button class="btn btn-delete btn-sm" onclick="deleteTask(' + task.id + ')"><i class="fa fa-trash" aria-hidden="true"></i></button>';
                        }
                    }

                    var row = '<tr class="' + rowClass + '">' +
                        '<td>' + task.titulo + '</td>' +
                        '<td>' + task.categoria_titulo + '</td>' +
                        '<td>' + task.origem_titulo + '</td>' +
                        '<td>' + task.descricao + '</td>' +
                        '<td>' + new Date(task.data_limite).toLocaleString("pt-BR") + '</td>' +
                        '<td>' + task.funcionario_responsavel + '</td>' +
                        '<td><span class="status-label ' + statusClass + '">' + task.status.charAt(0).toUpperCase() + task.status.slice(1).toLowerCase() + '</span></td>' +
                        '<td>' + actions + '</td>' +
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
                alert('Comentário adicionado com sucesso!');
                viewTask(taskToken); // Atualizar a visualização da tarefa
            },
            error: function() {
                alert('Erro ao adicionar comentário');
            }
        });
    });

    $('#saveStatusButton').on('click', function() {
        var taskToken = $('#viewTitle').data('tasktoken');
        var status = $('#viewStatus').val();
        var currentDate = new Date().toISOString().slice(0, 19).replace('T', ' ');

        $.ajax({
            url: 'update_status.php',
            type: 'POST',
            data: {
                taskToken: taskToken,
                status: status,
                dataConclusao: status.toLowerCase() === 'concluída' ? currentDate : null
            },
            success: function(response) {
                alert('Status atualizado com sucesso!');
                $('#viewTaskModal').modal('hide');
                $('#searchForm').submit(); // Atualizar a lista de tarefas
            },
            error: function() {
                alert('Erro ao atualizar o status');
            }
        });
    });

    // Resolver problema de rolagem com modais empilhados
    $('#addCommentModal').on('shown.bs.modal', function () {
        $('body').addClass('modal-open');
    }).on('hidden.bs.modal', function () {
        $('body').removeClass('modal-open');
    });

    $('#viewTaskModal').on('shown.bs.modal', function () {
        var dataConclusao = $('#viewStatus').data('data-conclusao');
        if (dataConclusao === null || dataConclusao === "NULL" || dataConclusao === "") {
            $('#saveStatusButton').prop('disabled', false);
        } else {
            $('#saveStatusButton').prop('disabled', true);
        }

        // Verificar se a tarefa tem um ofício vinculado
        var numeroOficio = $('#viewTitle').data('numeroOficio');
        if (numeroOficio) {
            $('#vincularOficioButton').html('<i class="fa fa-eye" aria-hidden="true"></i> Visualizar Ofício').attr('onclick', 'viewOficio(\'' + numeroOficio + '\')').removeAttr('data-toggle data-target');
        } else {
            $('#vincularOficioButton').html('<i class="fa fa-link" aria-hidden="true"></i> Vincular Ofício').attr('data-toggle', 'modal').attr('data-target', '#vincularOficioModal').removeAttr('onclick');
        }
    });
});

function viewTask(taskToken) {
    $.ajax({
        url: 'view_task.php',
        type: 'GET',
        data: { token: taskToken },
        success: function(response) {
            var task = JSON.parse(response);
            $('#viewTitle').val(task.titulo).data('tasktoken', taskToken).data('numeroOficio', task.numero_oficio);
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

            var commentTimeline = $('#commentTimeline');
            commentTimeline.empty();
            if (task.comentarios) {
                task.comentarios.forEach(function(comentario) {
                    var commentDate = new Date(comentario.data_comentario);
                    var commentDateFormatted = commentDate.toLocaleString("pt-BR");

                    var commentItem = '<div class="timeline-item">' +
                        '<div class="timeline-badge primary"><i class="mdi mdi-comment"></i></div>' +
                        '<div class="timeline-panel">' +
                            '<div class="timeline-heading">' +
                                '<h6 class="timeline-title">' + (comentario.funcionario || 'Desconhecido') + ' <small>' + commentDateFormatted + '</small></h6>' +
                            '</div>' +
                            '<div class="timeline-body">' +
                                '<p>' + comentario.comentario + '</p>';
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
            data: { id: taskId },
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
    window.open('ver_oficio.php?numero=' + numero, '_blank');
}

</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
