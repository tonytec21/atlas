<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$taskId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($taskId == 0) {
    die("ID da tarefa inválido.");
}

// Buscar dados da tarefa
$sql = "SELECT * FROM tarefas WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $taskId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Tarefa não encontrada.");
}

$taskData = $result->fetch_assoc();
$token = $taskData['token'];

// Registrar log da tarefa
$logSql = "INSERT INTO logs_tarefas (task_id, titulo, categoria, origem, data_limite, funcionario_responsavel, descricao, caminho_anexo, data_criacao, data_edicao, atualizado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$logStmt = $conn->prepare($logSql);
$logTaskId = $taskData['id'];
$logTitulo = $taskData['titulo'];
$logCategoria = $taskData['categoria'];
$logOrigem = $taskData['origem'];
$logDataLimite = $taskData['data_limite'];
$logFuncionarioResponsavel = $taskData['funcionario_responsavel'];
$logDescricao = $taskData['descricao'];
$logCaminhoAnexo = $taskData['caminho_anexo'];
$logDataCriacao = $taskData['data_criacao'];
$logDataEdicao = date('Y-m-d H:i:s');
$logAtualizadoPor = $_SESSION['username'];

$logStmt->bind_param("issssssssss", $logTaskId, $logTitulo, $logCategoria, $logOrigem, $logDataLimite, $logFuncionarioResponsavel, $logDescricao, $logCaminhoAnexo, $logDataCriacao, $logDataEdicao, $logAtualizadoPor);
$logStmt->execute();

// Buscar categorias
$sql = "SELECT * FROM categorias WHERE status = 'ativo'";
$result = $conn->query($sql);
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Buscar origens
$sql = "SELECT * FROM origem WHERE status = 'ativo'";
$result = $conn->query($sql);
$origins = [];
while ($row = $result->fetch_assoc()) {
    $origins[] = $row;
}

// Buscar funcionários do banco de dados "atlas"
$employeeConn = new mysqli("localhost", "root", "", "atlas");
if ($employeeConn->connect_error) {
    die("Falha na conexão com o banco atlas: " . $employeeConn->connect_error);
}
$employeeConn->set_charset("utf8"); // Definir charset para UTF-8

$sql = "SELECT nome_completo FROM funcionarios WHERE status = 'ativo'";
$result = $employeeConn->query($sql);
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
$employeeConn->close();

// Buscar comentários
$sql = "SELECT * FROM comentarios WHERE hash_tarefa = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $taskData['token']);
$stmt->execute();
$result = $stmt->get_result();
$comments = [];
while ($row = $result->fetch_assoc()) {
    $comments[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Editar Tarefa</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/toastr.min.css">
    <style>
        .comment-bubble {
            position: relative;
            padding: 15px;
            border-radius: 15px;
            background: #f1f1f1;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .comment-bubble::before {
            content: "";
            position: absolute;
            bottom: -20px; /* Adjust to align the tail */
            left: 20px; /* Adjust to align the tail */
            border-width: 10px 10px 0;
            border-style: solid;
            border-color: #f1f1f1 transparent;
            display: block;
            width: 0;
        }
        .comment-bubble h6 {
            margin: 0 0 10px;
        }
        .comment-bubble p {
            margin: 0;
        }
        .anexo-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            background: #fff;
            margin-bottom: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .anexo-item button {
            margin-left: 10px;
        }
        .timeline { position: relative; padding: 20px 0; list-style: none; }
        .timeline::before { content: ''; position: absolute; top: 0; bottom: 0; width: 2px; background: #e9ecef; left: 20px; margin-right: -1.5px; }
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
        .dark-mode .comment-bubble { background: #333; color: #fff; }
        .dark-mode .comment-bubble::before { border-color: #333 transparent; }
        .dark-mode .timeline::before { background: #444; }
        .dark-mode .timeline-item .timeline-panel { border-color: #555; background: #444; }
        .dark-mode .timeline-item .timeline-panel::before { border-left-color: #555; }
        .dark-mode .timeline-item .timeline-panel::after { border-left-color: #444; }
        .dark-mode .anexo-item { background: #555; color: #fff; border-color: #666; }
        .dark-mode .anexo-item button { color: #fff; }
        .btn-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <h4>Edição de Tarefa - Protocolo Geral nº.: <?php echo $taskId; ?></h4>
            <div class="btn-container">
                <button style="width: 172px; height: 40px!important; font-size: 14px; margin-bottom: 5px!important; margin-left: 10px;" class="btn btn-primary mr-2" id="protocoloButton">
                    <i class="fa fa-print" aria-hidden="true"></i> Guia de Protocolo
                </button>
                <button style="width: 150px; height: 40px!important; font-size: 14px; margin-bottom: 5px!important;" onclick="window.location.href='criar-tarefa.php'" class="btn btn-success">
                    <i class="fa fa-plus" aria-hidden="true"></i> Nova Tarefa
                </button>
                <button style="width: 150px; height: 40px!important; font-size: 14px; margin-bottom: 5px!important; margin-left: 10px;" type="button" class="btn btn-secondary btn-sm" onclick="window.location.href='index.php'">
                    <i class="fa fa-search" aria-hidden="true"></i> Pesquisar Tarefas
                </button>
            </div>
        </div>
        <hr>
        <form id="editTaskForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="taskId" value="<?php echo htmlspecialchars($taskId); ?>">
            <input type="hidden" name="taskToken" value="<?php echo htmlspecialchars($token); ?>">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="title">Título da Tarefa:</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($taskData['titulo']); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="category">Categoria:</label>
                    <select id="category" name="category" class="form-control" required>
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo $category['id'] == $taskData['categoria'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['titulo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="origin">Origem:</label>
                    <select id="origin" name="origin" class="form-control" required>
                        <?php foreach ($origins as $origin) : ?>
                            <option value="<?php echo htmlspecialchars($origin['id']); ?>" <?php echo $origin['id'] == $taskData['origem'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($origin['titulo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="deadline">Data Limite para Conclusão:</label>
                    <input type="datetime-local" class="form-control" id="deadline" name="deadline" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $taskData['data_limite'])); ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="priority">Nível de Prioridade:</label>
                    <select id="priority" name="priority" class="form-control" required>
                        <option value="Baixa" <?php echo $taskData['nivel_de_prioridade'] == 'Baixa' ? 'selected' : ''; ?>>Baixa</option>
                        <option value="Média" <?php echo $taskData['nivel_de_prioridade'] == 'Média' ? 'selected' : ''; ?>>Média</option>
                        <option value="Alta" <?php echo $taskData['nivel_de_prioridade'] == 'Alta' ? 'selected' : ''; ?>>Alta</option>
                        <option value="Crítica" <?php echo $taskData['nivel_de_prioridade'] == 'Crítica' ? 'selected' : ''; ?>>Crítica</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="employee">Funcionário Responsável:</label>
                    <select id="employee" name="employee" class="form-control" required>
                        <?php foreach ($employees as $employee) : ?>
                            <option value="<?php echo htmlspecialchars($employee['nome_completo']); ?>" <?php echo $employee['nome_completo'] == $taskData['funcionario_responsavel'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($employee['nome_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="description">Descrição:</label>
                <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($taskData['descricao']); ?></textarea>
            </div>
        <h4>Anexos</h4>
        <div id="viewAttachments" class="list-group">
            <?php
            $anexos = !empty($taskData['caminho_anexo']) ? explode(';', $taskData['caminho_anexo']) : [];
            if (!empty($anexos)) :
                foreach ($anexos as $attachment) :
            ?>
                <div class="anexo-item">
                    <span><?php echo htmlspecialchars(basename($attachment)); ?></span>
                    <button class="btn btn-info btn-sm visualizar-anexo" data-file="<?php echo htmlspecialchars($attachment); ?>"><i class="fa fa-eye" aria-hidden="true"></i></button>
                    <button class="btn btn-delete btn-sm excluir-anexo" data-file="<?php echo htmlspecialchars($attachment); ?>"><i class="fa fa-trash" aria-hidden="true"></i></button>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <hr>
            <div class="form-group">
                <input type="file" id="attachments" name="attachments[]" multiple class="form-control-file">
            </div>
            <button type="submit" style="margin-top: 1px!important;margin-bottom: 30px!important;" class="btn btn-primary w-100">Salvar Tarefa</button>
        </form>
        <hr>
        <h4>Timeline</h4>
        <div id="commentTimeline" class="timeline">
            <?php if (!empty($comments)) : ?>
                <?php foreach ($comments as $comment) : ?>
                    <div class="timeline-item">
                        <div class="timeline-badge primary"><i class="mdi mdi-comment"></i></div>
                        <div class="timeline-panel">
                            <div class="timeline-heading">
                                <h6 class="timeline-title"><?php echo htmlspecialchars($comment['funcionario'] ?? 'Desconhecido'); ?> <small><?php $date = new DateTime($comment['data_comentario']); echo $date->format('d/m/Y H:i:s'); ?></small></h6>
                            </div>
                            <div class="timeline-body">
                                <p><?php echo htmlspecialchars($comment['comentario']); ?></p>
                                <?php if (!empty($comment['caminho_anexo'])) : ?>
                                    <?php
                                    $commentAttachments = explode(';', $comment['caminho_anexo']);
                                    foreach ($commentAttachments as $attachment) :
                                    ?>
                                        <div class="anexo-item">
                                            <span><?php echo htmlspecialchars(basename($attachment)); ?></span>
                                            <button class="btn btn-info btn-sm visualizar-anexo" data-file="<?php echo htmlspecialchars($attachment); ?>"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                            <button class="btn btn-delete btn-sm excluir-anexo-comentario" data-file="<?php echo htmlspecialchars($attachment); ?>" data-comment="<?php echo $comment['id']; ?>"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if ($comment['funcionario'] == $_SESSION['username']) : ?>
                                    <button class="btn btn-edit btn-sm editar-comentario" data-comment='<?php echo json_encode($comment); ?>'><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="editCommentModal" tabindex="-1" role="dialog" aria-labelledby="editCommentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCommentModalLabel">Editar Comentário</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editCommentForm">
                    <input type="hidden" id="taskToken" name="taskToken" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" id="commentId" name="commentId" value="">
                    <div class="form-group">
                        <label for="editCommentDescription">Comentário:</label>
                        <textarea class="form-control" id="editCommentDescription" name="editCommentDescription" rows="5"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="editCommentAttachments">Anexar arquivos:</label>
                        <input type="file" id="editCommentAttachments" name="editCommentAttachments[]" multiple class="form-control-file">
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
<script src="../script/jquery-3.6.0.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/toastr.min.js"></script>
<script>

    document.addEventListener('DOMContentLoaded', function() {
        var deadlineInput = document.getElementById('deadline');
        var now = new Date();
        var year = now.getFullYear();
        var month = ('0' + (now.getMonth() + 1)).slice(-2); // Meses são 0-indexados
        var day = ('0' + now.getDate()).slice(-2);
        var hours = ('0' + now.getHours()).slice(-2);
        var minutes = ('0' + now.getMinutes()).slice(-2);

        // Formato YYYY-MM-DDTHH:MM
        var minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
        deadlineInput.min = minDateTime;

        // Caso o valor atual seja anterior ao mínimo, corrige automaticamente
        if (deadlineInput.value && deadlineInput.value < minDateTime) {
            deadlineInput.value = minDateTime;
        }
    });

    $(document).ready(function() {
        // Enviar formulário de edição de tarefa
        $('#editTaskForm').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            formData.append('taskId', '<?php echo $taskId; ?>');
            formData.append('updatedBy', '<?php echo $_SESSION["username"]; ?>');
            formData.append('updatedAt', new Date().toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' }));

            $.ajax({
                url: 'save_task_edit.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    alert(response);
                    location.reload();
                },
                error: function(error) {
                    alert('Erro ao salvar a tarefa.');
                }
            });
        });


    $(document).ready(function() {
        // Adiciona o evento de clique ao botão quando a página for carregada
        $('#protocoloButton').on('click', function() {
            // Faz a requisição para o JSON
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false, // Desabilita o cache
                success: function(data) {
                    const taskId = '<?php echo $taskId; ?>'; // Pega o taskId via PHP
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


        $(document).on('click', '.visualizar-anexo', function() {
            var filePath = $(this).data('file');
            window.open('<?php echo dirname($_SERVER['REQUEST_URI']); ?>/' + filePath, '_blank');
        });

        $(document).on('click', '.excluir-anexo', function() {
            if (confirm('Tem certeza que deseja excluir este anexo?')) {
                var filePath = $(this).data('file');
                var taskId = '<?php echo $taskId; ?>';

                $.ajax({
                    url: 'delete_attachment.php',
                    type: 'POST',
                    data: { file: filePath, taskId: taskId },
                    success: function(response) {
                        alert('Anexo excluído com sucesso');
                        location.reload();
                    },
                    error: function() {
                        alert('Erro ao excluir o anexo');
                    }
                });
            }
        });

        $(document).on('click', '.excluir-anexo-comentario', function() {
            if (confirm('Tem certeza que deseja excluir este anexo?')) {
                var filePath = $(this).data('file');
                var commentId = $(this).data('comment');

                $.ajax({
                    url: 'delete_comment_attachment.php',
                    type: 'POST',
                    data: { file: filePath, commentId: commentId },
                    success: function(response) {
                        alert('Anexo excluído com sucesso');
                        location.reload();
                    },
                    error: function() {
                        alert('Erro ao excluir o anexo');
                    }
                });
            }
        });

        $(document).on('click', '.editar-comentario', function() {
            var comment = $(this).data('comment');
            $('#editCommentDescription').val(comment.comentario);
            $('#commentId').val(comment.id);
            $('#editCommentForm').off('submit').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                formData.append('commentId', comment.id);
                formData.append('taskToken', '<?php echo $token; ?>');

                $.ajax({
                    url: 'edit_comment.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        alert('Comentário atualizado com sucesso!');
                        location.reload();
                    },
                    error: function() {
                        alert('Erro ao atualizar o comentário.');
                    }
                });
            });
            $('#editCommentModal').modal('show');
        });
    });
</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
