<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Funcionário</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
</head>
<body class="light-mode">
    <div class="container mt-5">
        <h2>Cadastro de Funcionário</h2>
        <form id="register-form">
            <div class="form-group">
                <label for="full-name">Nome Completo</label>
                <input type="text" class="form-control" id="full-name" name="full-name" required>
            </div>
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Cadastrar</button>
            <div id="register-error" class="text-danger mt-3"></div>
            <div id="register-success" class="text-success mt-3"></div>
        </form>
    </div>

    <script src="script/jquery-3.5.1.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#register-form').submit(function(e) {
                e.preventDefault();
                const fullName = $('#full-name').val();
                const username = $('#username').val();
                const password = btoa($('#password').val()); // Encode the password in base64

                $.ajax({
                    url: 'save_user.php',
                    method: 'POST',
                    data: { fullName, username, password },
                    success: function(response) {
                        if (response === 'success') {
                            $('#register-success').text('Funcionário cadastrado com sucesso.');
                            $('#register-error').text('');
                            $('#register-form')[0].reset();
                        } else {
                            $('#register-error').text('Erro ao cadastrar funcionário. Tente novamente.');
                            $('#register-success').text('');
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
