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
$stmt->bind_param("s", $numero);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ofício não encontrado.");
}

$oficioData = $result->fetch_assoc();
$stmt->close();

// Conexão com o banco de dados "atlas"
$atlasConn = new mysqli($servername, $username, $password, "atlas");
if ($atlasConn->connect_error) {
    die("Falha na conexão com o banco atlas: " . $atlasConn->connect_error);
}
$atlasConn->set_charset("utf8");

// Buscar funcionários do banco de dados "atlas"
$sql = "SELECT id, nome_completo, cargo FROM funcionarios WHERE status = 'ativo'";
$result = $atlasConn->query($sql);
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

// Logar a edição na tabela logs_oficios
$loggedUser = $_SESSION['username'];
$sql = "INSERT INTO logs_oficios (numero, destinatario, assunto, corpo, assinante, data, tratamento, cargo, cargo_assinante, atualizado_por) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $atlasConn->prepare($sql);
$stmt->bind_param("ssssssssss", $oficioData['numero'], $oficioData['destinatario'], $oficioData['assunto'], $oficioData['corpo'], $oficioData['assinante'], $oficioData['data'], $oficioData['tratamento'], $oficioData['cargo'], $oficioData['cargo_assinante'], $loggedUser);
$stmt->execute();
$stmt->close();

$atlasConn->close();

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
    <script src="../ckeditor/ckeditor.js"></script>
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
        <h3>Edição de Ofício Nº.: <?php echo $numero; ?></h3>
        <hr>
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
                                <option value="<?php echo htmlspecialchars($employee['nome_completo']); ?>" <?php if ($oficioData['assinante'] == $employee['nome_completo']) echo "selected"; ?>>
                                    <?php echo htmlspecialchars($employee['nome_completo']); ?>
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
                <div class="form-group">
                    <label for="dados_complementares">Dados Complementares:</label>
                    <textarea class="form-control" id="dados_complementares" name="dados_complementares" rows="5"><?php echo htmlspecialchars($oficioData['dados_complementares']); ?></textarea>
                </div>
                <button type="submit" style="margin-bottom: 31px;margin-top: 0px !important;" class="btn btn-primary w-100">Salvar Ofício</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script>

    $(document).ready(function() {
        // Inicializar o CKEditor com corretor ortográfico
        CKEDITOR.replace('corpo', {
            extraPlugins: 'htmlwriter',
            allowedContent: true,
            filebrowserUploadUrl: '/uploader/upload.php',
            filebrowserUploadMethod: 'form',
            scayt_autoStartup: true,
            scayt_sLang: 'pt_BR'
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
