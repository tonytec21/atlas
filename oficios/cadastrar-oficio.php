<?php
include(__DIR__ . '/../session_check.php');
checkSession();

function getNextOficioNumber($conn) {
    $currentYear = date('Y');
    $result = $conn->query("SELECT MAX(numero) AS max_numero FROM oficios WHERE YEAR(data) = $currentYear");
    $row = $result->fetch_assoc();
    $lastNumero = $row['max_numero'];

    if ($lastNumero) {
        $lastNumeroParts = explode('/', $lastNumero);
        $nextSequence = (int)$lastNumeroParts[0] + 1;
    } else {
        $nextSequence = 1;
    }

    return $nextSequence . '/' . $currentYear;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "oficios_db";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    $numero = getNextOficioNumber($conn);
    $destinatario = $conn->real_escape_string($_POST['destinatario']);
    $assunto = $conn->real_escape_string($_POST['assunto']);
    $corpo = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['corpo'])));
    $assinante = $conn->real_escape_string($_POST['assinante']);
    $data = $conn->real_escape_string($_POST['data']);
    $tratamento = $conn->real_escape_string($_POST['tratamento']);
    $cargo = $conn->real_escape_string($_POST['cargo']);
    $cargo_assinante = $conn->real_escape_string($_POST['cargo_assinante']);

    $stmt = $conn->prepare("INSERT INTO oficios (destinatario, assunto, corpo, assinante, data, numero, tratamento, cargo, cargo_assinante) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss", $destinatario, $assunto, $corpo, $assinante, $data, $numero, $tratamento, $cargo, $cargo_assinante);
    $stmt->execute();

    $stmt->close();
    $conn->close();

    echo "<script>alert('Ofício salvo com sucesso!'); window.location.href = 'index.php';</script>";
}

$employees = json_decode(file_get_contents(__DIR__ . '/../data.json'), true);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Criar Ofício</title>
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
            <h3>Criar Ofício</h3>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="tratamento">Forma de Tratamento:</label>
                        <input type="text" class="form-control" id="tratamento" name="tratamento">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="destinatario">Destinatário:</label>
                        <input type="text" class="form-control" id="destinatario" name="destinatario" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="cargo">Cargo:</label>
                        <input type="text" class="form-control" id="cargo" name="cargo">
                    </div>
                </div>
                <div class="form-group">
                    <label for="assunto">Assunto:</label>
                    <input type="text" class="form-control" id="assunto" name="assunto" required>
                </div>
                <div class="form-group">
                    <label for="corpo">Corpo do Ofício:</label>
                    <textarea class="form-control" id="corpo" name="corpo" rows="10" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="assinante">Assinante:</label>
                        <select class="form-control" id="assinante" name="assinante" required>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo htmlspecialchars($employee['fullName']); ?>">
                                    <?php echo htmlspecialchars($employee['fullName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="cargo_assinante">Cargo do Assinante:</label>
                        <input type="text" class="form-control" id="cargo_assinante" name="cargo_assinante">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="data">Data:</label>
                        <input type="date" class="form-control" id="data" name="data" required>
                    </div>
                </div>
                <button type="submit" style="margin-bottom: 31px;margin-top: 0px !important;" class="btn btn-primary w-100">Salvar Ofício</button>
            </form>
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

            // Inicializar o CKEditor com corretor ortográfico
            CKEDITOR.replace('corpo', {
                extraPlugins: 'htmlwriter',
                allowedContent: true,
                filebrowserUploadUrl: '/uploader/upload.php',
                filebrowserUploadMethod: 'form',
                scayt_autoStartup: true, // Habilitar o corretor ortográfico automaticamente
                scayt_sLang: 'pt_BR' // Definir o idioma do corretor ortográfico para português brasileiro
            });
        });
    </script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
