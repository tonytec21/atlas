<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/custom.css">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
</head>
<body>
    <div class="container mt-5">
        <div class="brand">
            <h1>Atlas</h1>
        </div>
        <h2>Login</h2>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">Usuário ou senha incorretos. Tente novamente.</div>
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
