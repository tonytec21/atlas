<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requerente = $conn->real_escape_string($_POST['requerente']);
    $qualificacao = $conn->real_escape_string($_POST['qualificacao']);
    $motivo = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['motivo'])));
    $peticao = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['peticao'])));
    $criadoPor = $conn->real_escape_string($_SESSION['username']);
    $data = date('Y-m-d');

    $stmt = $conn->prepare("INSERT INTO requerimentos (requerente, qualificacao, motivo, peticao, data, criado_por) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $requerente, $qualificacao, $motivo, $peticao, $data, $criadoPor);

    if ($stmt->execute()) {
        echo "<script>alert('Requerimento salvo com sucesso!'); window.location.href = 'index.php';</script>";
    } else {
        echo "Erro ao salvar o requerimento: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Cadastro de Requerimento</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <script src="../ckeditor/ckeditor.js"></script>
    <style>
        .cke_notification_warning {
            display: none !important;
        }
    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Cadastro de Requerimento</h3>
            <form id="requerimentoForm" method="POST" action="">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="requerente">Requerente:</label>
                        <input type="text" class="form-control" id="requerente" name="requerente" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="qualificacao">Qualificação:</label>
                        <input type="text" class="form-control" id="qualificacao" name="qualificacao" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="motivo">Motivo:</label>
                    <textarea class="form-control" id="motivo" name="motivo" rows="5" required></textarea>
                    <script>
                        CKEDITOR.replace('motivo');
                    </script>
                </div>
                <div class="form-group">
                    <label for="peticao">Petição:</label>
                    <textarea class="form-control" id="peticao" name="peticao" rows="5" required></textarea>
                    <script>
                        CKEDITOR.replace('peticao');
                    </script>
                </div>
                <input type="hidden" id="criadoPor" name="criadoPor" value="<?php echo $_SESSION['username']; ?>">
                <button type="submit" style="margin-top: 1px; margin-bottom: 20px;" class="btn btn-primary w-100">Salvar Requerimento</button>
            </form>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
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
                    data: {
                        mode: mode
                    },
                    success: function(response) {
                        console.log(response);
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