<?php
include(__DIR__ . '/session_check.php');
checkSession();

$taskFileName = isset($_GET['file']) ? $_GET['file'] : '';
$taskFilePath = __DIR__ . "/meta-dados/$taskFileName";

if (!file_exists($taskFilePath)) {
    die("Arquivo de tarefa não encontrado.");
}

$taskData = json_decode(file_get_contents($taskFilePath), true);
if (!$taskData) {
    die("Erro ao carregar os dados da tarefa.");
}

$categoriesFilePath = __DIR__ . "/categorias/categorias.json";
if (!file_exists($categoriesFilePath)) {
    die("Arquivo de categorias não encontrado.");
}
$categories = json_decode(file_get_contents($categoriesFilePath), true);

$employeesFilePath = __DIR__ . "/../data.json";
if (!file_exists($employeesFilePath)) {
    die("Arquivo de funcionários não encontrado.");
}
$employees = json_decode(file_get_contents($employeesFilePath), true);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font/css/materialdesignicons.min.css">
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Editar Tarefa</h3>
            <form id="editTaskForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="taskFile" value="<?php echo htmlspecialchars($taskFileName); ?>">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="title">Título da Tarefa:</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($taskData['title']); ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="category">Categoria:</label>
                        <select id="category" name="category" class="form-control" required>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo $category; ?>" <?php echo $category == $taskData['category'] ? 'selected' : ''; ?>><?php echo $category; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="deadline">Data Limite para Conclusão:</label>
                        <input type="datetime-local" class="form-control" id="deadline" name="deadline" value="<?php echo htmlspecialchars($taskData['deadline']); ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="employee">Funcionário Responsável:</label>
                        <select id="employee" name="employee" class="form-control" required>
                            <?php foreach ($employees as $employee) : ?>
                                <option value="<?php echo $employee['fullName']; ?>" <?php echo $employee['fullName'] == $taskData['employee'] ? 'selected' : ''; ?>><?php echo $employee['fullName']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Descrição:</label>
                    <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($taskData['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="attachments">Anexos:</label>
                    <input type="file" id="attachments" name="attachments[]" multiple class="form-control-file">
                </div>
                <button type="submit" class="btn btn-primary">Salvar Tarefa</button>
            </form>
            <h4>Anexos</h4>
            <div id="viewAttachments" class="list-group">
                <?php if (!empty($taskData['attachments'])) : ?>
                    <?php foreach ($taskData['attachments'] as $attachment) : ?>
                        <div class="anexo-item">
                            <span><?php echo basename($attachment); ?></span>
                            <button class="btn btn-info btn-sm visualizar-anexo" data-file="<?php echo $attachment; ?>"><i class="fa fa-eye" aria-hidden="true"></i></button>
                            <button class="btn btn-delete btn-sm excluir-anexo" data-file="<?php echo $attachment; ?>"><i class="fa fa-trash" aria-hidden="true"></i></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <h4>Comentários</h4>
            <div id="commentTimeline" class="timeline">
                <?php if (!empty($taskData['comments'])) : ?>
                    <?php foreach ($taskData['comments'] as $comment) : ?>
                        <div class="timeline-item">
                            <div class="timeline-badge primary"><i class="mdi mdi-comment"></i></div>
                            <div class="timeline-panel">
                                <div class="timeline-heading">
                                    <h6 class="timeline-title"><?php echo $comment['employee'] ?? 'Desconhecido'; ?> <small><?php echo $comment['date']; ?></small></h6>
                                </div>
                                <div class="timeline-body">
                                    <p><?php echo htmlspecialchars($comment['description']); ?></p>
                                    <?php if (!empty($comment['attachments'])) : ?>
                                        <?php foreach ($comment['attachments'] as $attachment) : ?>
                                            <div class="anexo-item">
                                                <span><?php echo basename($attachment); ?></span>
                                                <button class="btn btn-info btn-sm visualizar-anexo" data-file="<?php echo $attachment; ?>"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                                <button class="btn btn-delete btn-sm excluir-anexo" data-file="<?php echo $attachment; ?>"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if ($comment['employee'] == $_SESSION['username']) : ?>
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

            // Enviar formulário de edição de tarefa
            $('#editTaskForm').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                formData.append('taskFile', '<?php echo $taskFileName; ?>');
                formData.append('updatedBy', '<?php echo $_SESSION["username"]; ?>');
                formData.append('updatedAt', new Date().toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' }));

                $.ajax({
                    url: 'salve_task_edit.php',
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

            $(document).on('click', '.visualizar-anexo', function() {
                var filePath = $(this).data('file');
                window.open(filePath, '_blank');
            });

            $(document).on('click', '.excluir-anexo', function() {
                if (confirm('Tem certeza que deseja excluir este anexo?')) {
                    var filePath = $(this).data('file');
                    var fileName = '<?php echo $taskFileName; ?>';

                    $.ajax({
                        url: 'delete_attachment.php',
                        type: 'POST',
                        data: { file: filePath, taskFile: fileName },
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
                $('#editCommentDescription').val(comment.description);
                $('#editCommentModal').modal('show');
                $('#editCommentForm').off('submit').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    formData.append('taskFile', '<?php echo $taskFileName; ?>');
                    formData.append('commentDate', comment.date);

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
            });
        });
    </script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
