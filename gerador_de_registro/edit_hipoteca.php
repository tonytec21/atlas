<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$id = $_GET['id'] ?? '';

if (!$id) {
    echo "<script>alert('ID não fornecido.'); window.location.href = 'index.php';</script>";
    exit;
}

// Buscar dados da cédula para edição
$stmt = $conn->prepare("SELECT * FROM registros_cedulas WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$cedula = $result->fetch_assoc();
$stmt->close();

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
    <title>Atlas - Editar Cédula</title>
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
        <h3>Editar Cédula</h3>
        <hr>
        <form method="POST" action="update_hipoteca.php">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="titulo_cedula">Título da Cédula:</label>
                    <input type="text" class="form-control" id="titulo_cedula" name="titulo_cedula" value="<?php echo htmlspecialchars($cedula['titulo_cedula']); ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="n_cedula">Número da Cédula:</label>
                    <input type="text" class="form-control" id="n_cedula" name="n_cedula" value="<?php echo htmlspecialchars($cedula['n_cedula']); ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="emissao_cedula">Data de Emissão:</label>
                    <input type="date" class="form-control" id="emissao_cedula" name="emissao_cedula" value="<?php echo htmlspecialchars($cedula['emissao_cedula']); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="valor_cedula">Valor da Cédula:</label>
                    <input type="text" class="form-control" id="valor_cedula" name="valor_cedula" value="<?php echo number_format($cedula['valor_cedula'], 2, ',', '.'); ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="credor">Credor:</label>
                    <input type="text" class="form-control" id="credor" name="credor" value="<?php echo htmlspecialchars($cedula['credor']); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="emitente">Emitente:</label>
                    <input type="text" class="form-control" id="emitente" name="emitente" value="<?php echo htmlspecialchars($cedula['emitente']); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="registro_garantia">Registro de Garantia:</label>
                <textarea class="form-control" id="registro_garantia" name="registro_garantia" rows="5" required><?php echo htmlspecialchars($cedula['registro_garantia']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="forma_de_pagamento">Forma de Pagamento:</label>
                <textarea class="form-control" id="forma_de_pagamento" name="forma_de_pagamento" rows="5" required><?php echo htmlspecialchars($cedula['forma_de_pagamento']); ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="matricula">Matrícula:</label>
                    <input type="text" class="form-control" id="matricula" name="matricula" value="<?php echo htmlspecialchars($cedula['matricula']); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="data">Data:</label>
                    <input type="date" class="form-control" id="data" name="data" value="<?php echo htmlspecialchars($cedula['data']); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="funcionario">Funcionário:</label>
                <select class="form-control" id="funcionario" name="funcionario" required>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo htmlspecialchars($employee['nome_completo']); ?>" <?php echo $cedula['funcionario'] == $employee['nome_completo'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['nome_completo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-100">Atualizar Registro</button>
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
