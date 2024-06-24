<?php
include(__DIR__ . '/../session_check.php');
checkSession();

$numero = isset($_GET['numero']) ? $_GET['numero'] : '';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oficios_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT * FROM oficios WHERE numero = ?");
$stmt->bind_param("s", $numero);  // "s" to accept the format "NUMERO/ANO"
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ofício não encontrado.");
}

$oficioData = $result->fetch_assoc();
$stmt->close();
$conn->close();

$employees = json_decode(file_get_contents(__DIR__ . '/../data.json'), true);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Editar Ofício</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <script src="https://cdn.ckeditor.com/4.16.0/full/ckeditor.js"></script>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Editar Ofício</h3>
            <?php if ($oficioData['status'] == 1): ?>
                <div class="alert alert-danger" role="alert">
                    Este ofício está bloqueado para edição.
                </div>
            <?php else: ?>
                <form id="editOficioForm" method="POST" action="save_oficio.php">
                    <input type="hidden" name="numero" value="<?php echo htmlspecialchars($numero); ?>">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="tratamento">Forma de Tratamento:</label>
                            <input type="text" class="form-control" id="tratamento" name="tratamento" value="<?php echo htmlspecialchars($oficioData['tratamento']); ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="destinatario">Destinatário:</label>
                            <input type="text" class="form-control" id="destinatario" name="destinatario" value="<?php echo htmlspecialchars($oficioData['destinatario']); ?>" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="cargo">Cargo:</label>
                            <input type="text" class="form-control" id="cargo" name="cargo" value="<?php echo htmlspecialchars($oficioData['cargo']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="assunto">Assunto:</label>
                        <input type="text" class="form-control" id="assunto" name="assunto" value="<?php echo htmlspecialchars($oficioData['assunto']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="corpo">Corpo do Ofício:</label>
                        <textarea class="form-control" id="corpo" name="corpo" rows="10" required><?php echo htmlspecialchars($oficioData['corpo']); ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="assinante">Assinante:</label>
                            <select class="form-control" id="assinante" name="assinante" required>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo htmlspecialchars($employee['fullName']); ?>" <?php if ($oficioData['assinante'] == $employee['fullName']) echo "selected"; ?>>
                                        <?php echo htmlspecialchars($employee['fullName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="cargo_assinante">Cargo do Assinante:</label>
                            <input type="text" class="form-control" id="cargo_assinante" name="cargo_assinante" value="<?php echo htmlspecialchars($oficioData['cargo_assinante']); ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="data">Data:</label>
                            <input type="date" class="form-control" id="data" name="data" value="<?php echo htmlspecialchars($oficioData['data']); ?>" required>
                        </div>
                    </div>
                    <button type="submit" style="margin-bottom: 31px;margin-top: 0px !important;" class="btn btn-primary w-100">Salvar Ofício</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
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

            // Inicializar o CKEditor
            CKEDITOR.replace('corpo', {
                extraPlugins: 'htmlwriter',
                allowedContent: true,
                filebrowserUploadUrl: '/uploader/upload.php',
                filebrowserUploadMethod: 'form'
            });

            // Enviar formulário de edição de ofício
            $('#editOficioForm').on('submit', function(e) {
                e.preventDefault();

                for (instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].updateElement();
                }

                var formData = new FormData(this);

                $.ajax({
                    url: 'save_oficio.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        alert('Ofício salvo com sucesso!');
                        window.location.href = 'index.php';
                    },
                    error: function(error) {
                        alert('Erro ao salvar o ofício.');
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
