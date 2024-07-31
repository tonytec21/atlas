<?php
include(__DIR__ . '/session_check.php');
checkSession();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oficios_db";

// Conexão com o banco de dados "oficios_db"
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Função para obter o próximo número de ofício
function getNextOficioNumber($conn) {
    $currentYear = date('Y');
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(numero, '/', 1) AS UNSIGNED)) AS max_numero FROM oficios WHERE YEAR(data) = $currentYear");
    $row = $result->fetch_assoc();
    $lastNumero = $row['max_numero'];

    if ($lastNumero) {
        $nextSequence = (int)$lastNumero + 1;
    } else {
        $nextSequence = 1;
    }

    return $nextSequence . '/' . $currentYear;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

// Conexão com o banco de dados "atlas"
$atlasConn = new mysqli($servername, $username, $password, "atlas");
if ($atlasConn->connect_error) {
    die("Falha na conexão com o banco atlas: " . $atlasConn->connect_error);
}
$atlasConn->set_charset("utf8"); // Definir charset para UTF-8

// Buscar funcionários do banco de dados "atlas"
$sql = "SELECT id, nome_completo, cargo FROM funcionarios WHERE status = 'ativo'";
$result = $atlasConn->query($sql);
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
$atlasConn->close();

// Usuário logado
$loggedUser = $_SESSION['username'];

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
    <script src="../ckeditor/ckeditor.js"></script>
    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <style>
        .cke_notification_warning { display: none !important; }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Criar Ofício</h3>
            <hr>
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
                                <option value="<?php echo htmlspecialchars($employee['nome_completo']); ?>" <?php echo $loggedUser == $employee['nome_completo'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="cargo_assinante">Cargo do Assinante:</label>
                        <input type="text" class="form-control" id="cargo_assinante" name="cargo_assinante" value="<?php echo $loggedUser == $employee['nome_completo'] ? htmlspecialchars($employee['cargo']) : ''; ?>">
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

    <script>
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

            // Preencher automaticamente o campo de cargo ao selecionar um assinante
            $('#assinante').on('change', function() {
                var selectedAssinante = $(this).val();
                var cargoAssinante = '';

                <?php foreach ($employees as $employee): ?>
                if (selectedAssinante === "<?php echo htmlspecialchars($employee['nome_completo']); ?>") {
                    cargoAssinante = "<?php echo htmlspecialchars($employee['cargo']); ?>";
                }
                <?php endforeach; ?>

                $('#cargo_assinante').val(cargoAssinante);
            }).trigger('change'); // Trigger change event to set initial value
        });
    </script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
