<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if (!isset($_GET['id'])) {
    echo "ID do requerimento não fornecido.";
    exit;
}

$id = intval($_GET['id']);

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requerente = $conn->real_escape_string($_POST['requerente']);
    $qualificacao = $conn->real_escape_string($_POST['qualificacao']);
    $motivo = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['motivo'])));
    $peticao = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['peticao'])));
    $criadoPor = $conn->real_escape_string($_SESSION['username']);
    $data = date('Y-m-d');

    $stmt = $conn->prepare("UPDATE requerimentos SET requerente = ?, qualificacao = ?, motivo = ?, peticao = ?, data = ?, criado_por = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $requerente, $qualificacao, $motivo, $peticao, $data, $criadoPor, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Requerimento atualizado com sucesso!'); window.location.href = 'index.php';</script>";
    } else {
        echo "Erro ao atualizar o requerimento: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Buscar dados do requerimento
$stmt = $conn->prepare("SELECT * FROM requerimentos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$requerimento = $result->fetch_assoc();

if (!$requerimento) {
    echo "Requerimento não encontrado.";
    exit;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Editar Requerimento</title>
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
            <div class="title-buttons">
                <h3>Editar Requerimento</h3>
                <div class="buttons-right">
                    <a href="capa-arquivamento.php?id=<?php echo $arquivo_id; ?>" target="_blank" class="btn btn-primary"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Imprimir Requerimento</a>
                    <a href="cadastro.php" class="btn btn-success"><i class="fa fa-plus-circle" aria-hidden="true"></i> Novo Requerimento</a>
                </div>
            </div>
            <hr>
            <h3>Editar Requerimento</h3>
            <form id="requerimentoForm" method="POST" action="">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="requerente">Requerente:</label>
                        <input type="text" class="form-control" id="requerente" name="requerente" value="<?php echo htmlspecialchars($requerimento['requerente'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="qualificacao">Qualificação:</label>
                        <input type="text" class="form-control" id="qualificacao" name="qualificacao" value="<?php echo htmlspecialchars($requerimento['qualificacao'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="motivo">Motivo:</label>
                    <textarea class="form-control" id="motivo" name="motivo" rows="5" required><?php echo htmlspecialchars($requerimento['motivo'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <script>
                        CKEDITOR.replace('motivo');
                    </script>
                </div>
                <div class="form-group">
                    <label for="peticao">Petição:</label>
                    <textarea class="form-control" id="peticao" name="peticao" rows="5" required><?php echo htmlspecialchars($requerimento['peticao'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <script>
                        CKEDITOR.replace('peticao');
                    </script>
                </div>
                <button type="submit" style="margin-top: 1px; margin-bottom: 20px;" class="btn btn-primary w-100">Atualizar Requerimento</button>
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