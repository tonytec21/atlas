<?php
include(__DIR__ . '/session_check.php');
checkSession();
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
            <h3>Cadastro de Tarefas - Inserir Nova Tarefa</h3>
            <form id="taskForm" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="title">Título da Tarefa:</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="category">Categoria:</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Selecione</option>
                            <!-- Categorias serão carregadas via JavaScript -->
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="deadline">Data Limite para Conclusão:</label>
                        <input type="datetime-local" class="form-control" id="deadline" name="deadline" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="employee">Funcionário Responsável:</label>
                        <select id="employee" name="employee" class="form-control" required>
                            <option value="">Selecione</option>
                            <!-- Funcionários serão carregados via JavaScript -->
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
                <button type="submit" class="btn btn-primary">Salvar Tarefa</button>
            </form>
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

            // Carregar categorias do arquivo JSON
            $.getJSON('categorias/categorias.json', function(data) {
                var categorySelect = $('#category');
                $.each(data, function(index, category) {
                    categorySelect.append($('<option>', {
                        value: category,
                        text: category
                    }));
                });
            });

            // Carregar funcionários do arquivo JSON
            $.getJSON('../data.json', function(data) {
                var employeeSelect = $('#employee');
                var loggedInUser = '<?php echo $_SESSION["username"]; ?>';
                $.each(data, function(index, employee) {
                    employeeSelect.append($('<option>', {
                        value: employee.fullName,
                        text: employee.fullName,
                        selected: employee.username === loggedInUser // Seleciona o funcionário logado
                    }));
                });
            });

            $('#taskForm').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                formData.append('createdBy', '<?php echo $_SESSION["username"]; ?>');
                formData.append('createdAt', new Date().toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' }));

                $.ajax({
                    url: 'save_task.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        alert('Tarefa salva com sucesso!');
                        $('#taskForm')[0].reset();
                    },
                    error: function(error) {
                        alert('Erro ao salvar a tarefa.');
                    }
                });
            });
        });
    </script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
