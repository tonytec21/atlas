<?php
include(__DIR__ . '/session_check.php');
include(__DIR__ . '/db_connection.php');
checkSession();

$conn = new mysqli($servername, $username, $password, "atlas");
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo_cedula = $conn->real_escape_string($_POST['titulo_cedula']);
    $n_cedula = $conn->real_escape_string($_POST['n_cedula']);
    $emissao_cedula = $conn->real_escape_string($_POST['emissao_cedula']);
    $valor_cedula = str_replace(',', '.', str_replace('.', '', $conn->real_escape_string($_POST['valor_cedula'])));
    $credor = $conn->real_escape_string($_POST['credor']);
    $emitente = $conn->real_escape_string($_POST['emitente']);
    $registro_garantia = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['registro_garantia'])));
    $forma_de_pagamento = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['forma_de_pagamento'])));
    $matricula = $conn->real_escape_string($_POST['matricula']);
    $data = $conn->real_escape_string($_POST['data']);
    $funcionario = $conn->real_escape_string($_POST['funcionario']);

    // Definindo valores padrão para campos que não podem ser nulos
    $vencimento_cedula = '9999-12-31'; // Data futura arbitrária
    $vencimento_antecipado = '9999-12-31'; // Data futura arbitrária
    $juros = 0.0; // Valor padrão para juros
    $tipo = 'hipoteca';  // Valor fixo para a coluna "tipo"

    $stmt = $conn->prepare("INSERT INTO registros_cedulas (titulo_cedula, n_cedula, emissao_cedula, vencimento_cedula, valor_cedula, credor, emitente, registro_garantia, forma_de_pagamento, vencimento_antecipado, juros, matricula, data, funcionario, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssssss", $titulo_cedula, $n_cedula, $emissao_cedula, $vencimento_cedula, $valor_cedula, $credor, $emitente, $registro_garantia, $forma_de_pagamento, $vencimento_antecipado, $juros, $matricula, $data, $funcionario, $tipo);
    $stmt->execute();

    $stmt->close();
    $conn->close();

    echo "<script>alert('Registro salvo com sucesso!'); window.location.href = 'index.php';</script>";
}

// Buscar funcionários do banco de dados "atlas"
$sql = "SELECT id, nome_completo, cargo FROM funcionarios WHERE status = 'ativo'";
$result = $conn->query($sql);
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
$conn->close();

// Usuário logado
$loggedUser = $_SESSION['username'];

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Cadastro de Cédulas</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <script src="../ckeditor/ckeditor.js"></script>
    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
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
        <h3>Cadastro de Cédulas</h3>
        <hr>
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="titulo_cedula">Título da Cédula:</label>
                    <input type="text" class="form-control" id="titulo_cedula" name="titulo_cedula" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="n_cedula">Número da Cédula:</label>
                    <input type="text" class="form-control" id="n_cedula" name="n_cedula" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="emissao_cedula">Data de Emissão:</label>
                    <input type="date" class="form-control" id="emissao_cedula" name="emissao_cedula" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="valor_cedula">Valor da Cédula:</label>
                    <input type="text" class="form-control" id="valor_cedula" name="valor_cedula" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="credor">Credor:</label>
                    <input type="text" class="form-control" id="credor" name="credor" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="emitente">Emitente:</label>
                    <input type="text" class="form-control" id="emitente" name="emitente" required>
                </div>
            </div>
            <div class="form-group">
                <label for="registro_garantia">Registro de Garantia:</label>
                <textarea class="form-control" id="registro_garantia" name="registro_garantia" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label for="forma_de_pagamento">Forma de Pagamento:</label>
                <textarea class="form-control" id="forma_de_pagamento" name="forma_de_pagamento" rows="5" required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="matricula">Matrícula:</label>
                    <input type="text" class="form-control" id="matricula" name="matricula" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="data">Data:</label>
                    <input type="date" class="form-control" id="data" name="data" required>
                </div>
            </div>
            <div class="form-group">
                <label for="funcionario">Funcionário:</label>
                <select class="form-control" id="funcionario" name="funcionario" required>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo htmlspecialchars($employee['nome_completo']); ?>" <?php echo $loggedUser == $employee['nome_completo'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['nome_completo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-100">Salvar Registro</button>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Inicializar o CKEditor
        CKEDITOR.replace('registro_garantia', {
            extraPlugins: 'htmlwriter',
            allowedContent: true,
            filebrowserUploadUrl: '/uploader/upload.php',
            filebrowserUploadMethod: 'form',
            scayt_autoStartup: true,
            scayt_sLang: 'pt_BR'
        });
        CKEDITOR.replace('forma_de_pagamento', {
            extraPlugins: 'htmlwriter',
            allowedContent: true,
            filebrowserUploadUrl: '/uploader/upload.php',
            filebrowserUploadMethod: 'form',
            scayt_autoStartup: true,
            scayt_sLang: 'pt_BR'
        });
        // Aplicar máscara ao campo Valor da Cédula
        $('#valor_cedula').mask('#.##0,00', {reverse: true});
    });
</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
