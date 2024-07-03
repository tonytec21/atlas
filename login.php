<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/custom.css">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <style>
        @import url(https://fonts.googleapis.com/css2?family=Roboto&display=swap);
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="brand">
            <h1>Atlas</h1>
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
</body>
</html>
