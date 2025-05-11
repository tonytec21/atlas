<?php
// Configura o diretório como seguro para o Git
shell_exec('git config --global --add safe.directory C:/xampp/htdocs/atlas');

// Executa o comando git pull
$output = shell_exec('git pull 2>&1');

// Verifica o resultado da execução
if (strpos($output, 'Already up to date.') !== false) {
    $mensagem = "Sistema atualizado. Nenhuma atualização pendente.";
} elseif (strpos($output, 'Updating') !== false) {
    $mensagem = "Atualização do código aplicada com sucesso.";
} else {
    $mensagem = "Erro ao executar a atualização via git: " . $output;
}

// Configura o diretório como seguro para o Git
shell_exec('git config --global --add safe.directory C:/xampp/htdocs/xuxuzinho');

// Executa o comando git pull
$output = shell_exec('git pull 2>&1');

// Verifica o resultado da execução
if (strpos($output, 'Already up to date.') !== false) {
    $mensagem = "Sistema atualizado. Nenhuma atualização pendente.";
} elseif (strpos($output, 'Updating') !== false) {
    $mensagem = "Atualização do código aplicada com sucesso.";
} else {
    $mensagem = "Erro ao executar a atualização via git: " . $output;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATLAS</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/custom.css">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <style>
        body {
            font-family: 'Poppins';
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="brand">
            <img src="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/atlas.png'?>" alt="Atlas" style="vertical-align: middle;width: 150px;">
        </div>
        <h2>Login</h2>
        <?php if (isset($_GET['error'])): ?>
            <?php if ($_GET['error'] == 1): ?>
                <div class="alert alert-danger">Usuário ou senha incorretos. Tente novamente.</div>
            <?php elseif ($_GET['error'] == 2): ?>
                <div class="alert alert-danger">Usuário inativo. Contate o administrador.</div>
            <?php endif; ?>
        <?php endif; ?>
        <form action="check_login.php" method="POST">
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>
    </div>
    <script>
        window.onload = function() {
            clearCache();
        };
    </script>
</body>
</html>
