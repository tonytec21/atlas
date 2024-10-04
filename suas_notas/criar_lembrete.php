<?php
// Inclui os arquivos de conexão e sessão
include 'db_connection.php';
include 'session_check.php';
checkSession();
date_default_timezone_set('America/Sao_Paulo');

// Obtém o nome do usuário da sessão
$username = $_SESSION['username'];

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $conteudo = $_POST['conteudo'] ?? '';

    // Cria o diretório para o usuário, se não existir
    $userDirectory = 'lembretes/' . $username;
    if (!file_exists($userDirectory)) {
        mkdir($userDirectory, 0777, true);
    }

    // Salva o lembrete em um arquivo
    $arquivoNome = $userDirectory . '/' . time() . '.txt';
    $conteudoLembrete = "Título: $titulo\n\nConteúdo:\n$conteudo";
    file_put_contents($arquivoNome, $conteudoLembrete);
    echo "<div class='notification'>Lembrete criado com sucesso!<button class='close-btn'>&times;</button></div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Lembrete</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <style>
        /* Notificação de sucesso */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #28a745;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }
        .notification .close-btn {
            cursor: pointer;
            float: right;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/../menu.php'); ?>
    <div id="main" class="main-content">
        <div class="container">
            <h3 class="mt-4">Criar Lembrete</h3>
            <form action="" method="post" class="mt-4">
                <div class="mb-3">
                    <label for="titulo" class="form-label">Título</label>
                    <input type="text" id="titulo" name="titulo" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="conteudo" class="form-label">Conteúdo</label>
                    <textarea id="conteudo" name="conteudo" rows="5" class="form-control" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Criar Lembrete</button>
            </form>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script>
        // Função para fechar a notificação
        $('.notification .close-btn').on('click', function() {
            $(this).parent().hide();
        });
    </script>
    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
