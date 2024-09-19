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
    <title>Atlas - Cadastro de Tarefas</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Cadastro de Tarefas</h3>
        <form id="taskForm" enctype="multipart/form-data" method="POST" action="save_task.php">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="title">Título da Tarefa:</label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="category">Categoria:</label>
                    <select id="category" name="category" class="form-control" required>
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
                    <label for="deadline">Data Limite para Conclusão:</label>
                    <input type="datetime-local" class="form-control" id="deadline" name="deadline" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="priority">Nível de Prioridade:</label>
                    <select id="priority" name="priority" class="form-control" required>
                        <option value="">Selecione</option>
                        <option value="Baixa">Baixa</option>
                        <option value="Média">Média</option>
                        <option value="Alta">Alta</option>
                        <option value="Crítica">Crítica</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="employee">Funcionário Responsável:</label>
                    <select id="employee" name="employee" class="form-control" required>
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
                    <label for="origin">Origem:</label>
                    <select id="origin" name="origin" class="form-control" required>
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
                <label for="description">Descrição:</label>
                <textarea class="form-control" id="description" name="description" rows="5"></textarea>
            </div>
            <div class="form-group">
                <label for="attachments">Anexos:</label>
                <input type="file" id="attachments" name="attachments[]" multiple class="form-control-file">
            </div>
            <input type="hidden" id="createdBy" name="createdBy" value="<?php echo $_SESSION['username']; ?>">
            <input type="hidden" id="createdAt" name="createdAt" value="<?php echo date('Y-m-d H:i:s'); ?>">
            <button type="submit" style="margin-top: 1px; margin-bottom: 20px;" class="btn btn-primary w-100">Salvar Tarefa</button>
        </form>
    </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
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
    });

    $(document).ready(function() {

    });
</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
