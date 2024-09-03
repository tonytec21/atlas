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
    <title>Cadastrar Vídeo Tutorial</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Cadastrar Vídeo Tutorial</h3>
            <hr>
            <form id="cadastrarVideoForm" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="titulo">Título:</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="categoria">Categoria:</label>
                        <input type="text" class="form-control" id="categoria" name="categoria" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="descricao">Descrição:</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="caminho_video">Anexar Vídeo:</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="caminho_video" name="caminho_video" required>
                            <label class="custom-file-label" for="caminho_video">Escolher arquivo</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-success" style="width: 100%;"><i class="fa fa-save" aria-hidden="true"></i> Salvar</button>
            </form>
            <hr>
            <?php
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $titulo = $_POST['titulo'];
                $descricao = $_POST['descricao'];
                $categoria = $_POST['categoria'];
                $status = 'ativo';

                // Diretório onde o vídeo será salvo
                $diretorio = __DIR__ . "/anexos/$categoria/";

                if (!file_exists($diretorio)) {
                    mkdir($diretorio, 0777, true);
                }

                $videoNome = $_FILES['caminho_video']['name'];
                $caminhoCompleto = $diretorio . basename($videoNome);

                if (move_uploaded_file($_FILES['caminho_video']['tmp_name'], $caminhoCompleto)) {
                    $caminhoBanco = "anexos/$categoria/" . basename($videoNome);

                    // Inserir dados no banco de dados
                    $conn = getDatabaseConnection();
                    $stmt = $conn->prepare("INSERT INTO manuais (titulo, descricao, categoria, caminho_video, status) VALUES (:titulo, :descricao, :categoria, :caminho_video, :status)");
                    $stmt->bindParam(':titulo', $titulo);
                    $stmt->bindParam(':descricao', $descricao);
                    $stmt->bindParam(':categoria', $categoria);
                    $stmt->bindParam(':caminho_video', $caminhoBanco);
                    $stmt->bindParam(':status', $status);

                    if ($stmt->execute()) {
                        echo "<div class='alert alert-success' role='alert'>Vídeo tutorial cadastrado com sucesso!</div>";
                    } else {
                        echo "<div class='alert alert-danger' role='alert'>Erro ao cadastrar o vídeo tutorial.</div>";
                    }
                } else {
                    echo "<div class='alert alert-danger' role='alert'>Erro ao fazer upload do vídeo.</div>";
                }
            }
            ?>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function () {
            // Custom file input label update
            $('#caminho_video').on('change', function () {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName);
            });
        });
    </script>
    <?php
    include(__DIR__ . '/../rodape.php');
    ?>
</body>

</html>
