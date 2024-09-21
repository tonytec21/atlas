<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include '/checar_acesso_de_administrador.php';

$notificationMessage = null;
$notificationType = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? $_POST['id'] : null;
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];
    $nome_completo = $_POST['nome_completo'];
    $cargo = $_POST['cargo'];
    $nivel_de_acesso = $_POST['nivel_de_acesso'];

    // Validação para permitir apenas letras e números no campo "Usuário"
    if (!preg_match('/^[a-zA-Z0-9]+$/', $usuario)) {
        $notificationMessage = "O campo Usuário deve conter apenas letras e números, sem espaços ou caracteres especiais.";
        $notificationType = 'danger';
    } else {
        $errorMessage = saveFuncionario($id, $usuario, $senha, $nome_completo, $cargo, $nivel_de_acesso);
        if ($errorMessage) {
            $notificationMessage = $errorMessage;
            $notificationType = 'danger';
        } else {
            $notificationMessage = "Funcionário " . ($id ? "atualizado" : "cadastrado") . " com sucesso!";
            $notificationType = 'success';
        }
    }
}

// Função para cadastrar ou atualizar funcionários nos dois bancos de dados
function saveFuncionario($id, $usuario, $senha, $nome_completo, $cargo, $nivel_de_acesso) {
    $senha_base64 = base64_encode($senha);

    // Conexão com o banco de dados "atlas"
    $connAtlas = new mysqli("localhost", "root", "", "atlas");
    if ($connAtlas->connect_error) {
        die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
    }
    $connAtlas->set_charset("utf8");

    // Conexão com o banco de dados "oficios_db"
    $connOficios = new mysqli("localhost", "root", "", "oficios_db");
    if ($connOficios->connect_error) {
        die("Falha na conexão com o banco oficios_db: " . $connOficios->connect_error);
    }
    $connOficios->set_charset("utf8");

    // Verificar se já existe um usuário com o mesmo nome de login
    $checkStmtAtlas = $connAtlas->prepare("SELECT id FROM funcionarios WHERE usuario = ? AND id != ?");
    $checkStmtAtlas->bind_param("si", $usuario, $id);
    $checkStmtAtlas->execute();
    $checkStmtAtlas->store_result();

    if ($checkStmtAtlas->num_rows > 0) {
        $checkStmtAtlas->close();
        $connAtlas->close();
        $connOficios->close();
        return "Já existe um cadastro com esse nome de usuário!";
    }
    $checkStmtAtlas->close();

    // Verificar se é um novo cadastro ou atualização
    if ($id) {
        $stmtAtlas = $connAtlas->prepare("UPDATE funcionarios SET usuario = ?, senha = ?, nome_completo = ?, cargo = ?, nivel_de_acesso = ? WHERE id = ?");
        $stmtAtlas->bind_param("sssssi", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso, $id);
        $stmtOficios = $connOficios->prepare("UPDATE funcionarios SET usuario = ?, senha = ?, nome_completo = ?, cargo = ?, nivel_de_acesso = ? WHERE id = ?");
        $stmtOficios->bind_param("sssssi", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso, $id);
    } else {
        $stmtAtlas = $connAtlas->prepare("INSERT INTO funcionarios (usuario, senha, nome_completo, cargo, nivel_de_acesso) VALUES (?, ?, ?, ?, ?)");
        $stmtAtlas->bind_param("sssss", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso);
        $stmtOficios = $connOficios->prepare("INSERT INTO funcionarios (usuario, senha, nome_completo, cargo, nivel_de_acesso) VALUES (?, ?, ?, ?, ?)");
        $stmtOficios->bind_param("sssss", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso);
    }

    $stmtAtlas->execute();
    $stmtAtlas->close();
    $connAtlas->close();

    $stmtOficios->execute();
    $stmtOficios->close();
    $connOficios->close();

    return null;
}

if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

    // Conexão com o banco de dados "atlas"
    $connAtlas = new mysqli("localhost", "root", "", "atlas");
    if ($connAtlas->connect_error) {
        die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
    }
    $connAtlas->set_charset("utf8");

    // Conexão com o banco de dados "oficios_db"
    $connOficios = new mysqli("localhost", "root", "", "oficios_db");
    if ($connOficios->connect_error) {
        die("Falha na conexão com o banco oficios_db: " . $connOficios->connect_error);
    }
    $connOficios->set_charset("utf8");

    // Atualizar o status do funcionário para "removido" no banco "atlas"
    $stmtAtlas = $connAtlas->prepare("UPDATE funcionarios SET status = 'removido' WHERE id = ?");
    $stmtAtlas->bind_param("i", $id);
    $stmtAtlas->execute();
    $stmtAtlas->close();
    $connAtlas->close();

    // Atualizar o status do funcionário para "removido" no banco "oficios_db"
    $stmtOficios = $connOficios->prepare("UPDATE funcionarios SET status = 'removido' WHERE id = ?");
    $stmtOficios->bind_param("i", $id);
    $stmtOficios->execute();
    $stmtOficios->close();
    $connOficios->close();

    $notificationMessage = "Funcionário excluído com sucesso!";
    $notificationType = 'success';
}

// Conexão com o banco de dados "atlas"
$connAtlas = new mysqli("localhost", "root", "", "atlas");
if ($connAtlas->connect_error) {
    die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
}
$connAtlas->set_charset("utf8");

// Buscar todos os funcionários ativos no banco "atlas"
$result = $connAtlas->query("SELECT * FROM funcionarios WHERE status = 'ativo'");
$funcionarios = $result->fetch_all(MYSQLI_ASSOC);
$connAtlas->close();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Cadastro de Funcionários</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/font-awesome.min.css">
    <link rel="stylesheet" href="style/css/style.css">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <script src="script/chart.js"></script>
    <link rel="stylesheet" href="style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="style/css/dataTables.bootstrap4.min.css">
    <style>
        .chart-container {
            position: relative;
            height: 240px;
        }
        .chart-container.full-height {
            height: 360px;
        }
        .btn-info:hover {
            color: #fff;
        }
        @media (max-width: 768px) {
            .chart-container {
                height: 200px;
                margin-top: 20px;
            }
            .chart-container.full-height {
                height: 300px;
                margin-top: 20px;
                margin-bottom: 20px;
            }
            .card-body {
                padding: 1rem;
            }
            .card {
                margin-bottom: 1rem;
            }
        }
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #343a40;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
        }
        .notification .close-btn {
            cursor: pointer;
            float: right;
            margin-left: 10px;
        }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Cadastro de Funcionários</h3>
        <form method="post" action="">
            <input type="hidden" name="id" id="funcionario-id">

            <div class="row">
                <div class="form-group col-md-4">
                    <label for="usuario">Usuário</label>
                    <input type="text" class="form-control" id="usuario" name="usuario" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="senha">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="nivel_de_acesso">Nível de Acesso</label>
                    <select class="form-control" id="nivel_de_acesso" name="nivel_de_acesso" required>
                        <option value="usuario">Usuário</option>
                        <option value="administrador">Administrador</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="form-group col-md-8">
                    <label for="nome_completo">Nome Completo</label>
                    <input type="text" class="form-control" id="nome_completo" name="nome_completo" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="cargo">Cargo</label>
                    <input type="text" class="form-control" id="cargo" name="cargo" required>
                </div>
            </div>

            <button type="submit" id="submit-button" class="btn btn-secondary" style="width: 100%"><i class="fa fa-floppy-o" aria-hidden="true"></i> Cadastrar</button>
        </form>
        <hr>
        <div class="table-responsive">
            <h5>Funcionários Cadastrados</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 100%">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Nome Completo</th>
                            <th>Cargo</th>
                            <th>Nível de Acesso</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($funcionarios as $funcionario): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($funcionario['usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($funcionario['nome_completo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($funcionario['cargo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($funcionario['nivel_de_acesso']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <button class="btn btn-warning btn-edit" data-id="<?php echo $funcionario['id']; ?>" data-usuario="<?php echo htmlspecialchars($funcionario['usuario'], ENT_QUOTES, 'UTF-8'); ?>" data-nome="<?php echo htmlspecialchars($funcionario['nome_completo'], ENT_QUOTES, 'UTF-8'); ?>" data-cargo="<?php echo htmlspecialchars($funcionario['cargo'], ENT_QUOTES, 'UTF-8'); ?>" data-nivel_de_acesso="<?php echo htmlspecialchars($funcionario['nivel_de_acesso'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                    <a href="?delete_id=<?php echo $funcionario['id']; ?>" class="btn btn-delete" onclick="return confirm('Tem certeza que deseja excluir este funcionário?');"><i class="fa fa-trash" aria-hidden="true"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

<div class="notification">
    <span class="close-btn">&times;</span>
    <span id="notification-message"></span>
</div>

<script src="script/jquery-3.5.1.min.js"></script>
<script src="script/bootstrap.min.js"></script>
<script src="script/bootstrap.bundle.min.js"></script>
<script src="script/jquery.mask.min.js"></script>
<script src="script/jquery.dataTables.min.js"></script>
<script src="script/dataTables.bootstrap4.min.js"></script>
<script>
    function showNotification(message, type) {
        var notification = $('.notification');
        notification.removeClass('alert-success alert-danger');
        if (type === 'success') {
            notification.css('background-color', '#28a745');
        } else {
            notification.css('background-color', '#dc3545');
        }
        $('#notification-message').text(message);
        notification.fadeIn();

        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
    }

    $(document).ready(function() {
        // Mostrar notificação se existir uma mensagem
        <?php if ($notificationMessage): ?>
            showNotification('<?php echo $notificationMessage; ?>', '<?php echo $notificationType; ?>');
        <?php endif; ?>

        // Carregar dados do funcionário ao clicar em "Editar"
        $('.btn-edit').on('click', function() {
            $('#funcionario-id').val($(this).data('id'));
            $('#usuario').val($(this).data('usuario'));
            $('#nome_completo').val($(this).data('nome'));
            $('#cargo').val($(this).data('cargo'));
            $('#nivel_de_acesso').val($(this).data('nivel_de_acesso'));
            $('#senha').val(''); // Limpar campo de senha ao editar
            $('#submit-button').html('<i class="fa fa-floppy-o" aria-hidden="true"></i> Salvar Alterações');
            $('html, body').animate({ scrollTop: 0 }, 'slow'); // Rolar para o topo do formulário
        });

        // Inicializar o DataTable após os dados serem carregados
        $('#tabelaResultados').DataTable({
            "language": {
                "url": "../../style/Portuguese-Brasil.json"
            },
            "order": [],
        });

        // Função para fechar a notificação
        $('.notification .close-btn').on('click', function() {
            $(this).parent().fadeOut();
        });

        // Limpar o formulário ao enviar para redefinir o botão
        $('form').on('submit', function() {
            setTimeout(function() {
                $('#submit-button').html('<i class="fa fa-floppy-o" aria-hidden="true"></i> Cadastrar');
            }, 1000);
        });

        // Validação do campo "Usuário" para aceitar apenas letras e números
        $('#usuario').on('input', function() {
            var usuario = $(this).val();
            var sanitizedUsuario = usuario.replace(/[^a-zA-Z0-9]/g, ''); // Remove espaços e caracteres especiais
            if (usuario !== sanitizedUsuario) {
                $(this).val(sanitizedUsuario); // Atualiza o campo com o valor sanitizado
            }
        });
    });
</script>

<br><br><br>
<?php
include(__DIR__ . '/rodape.php');
?>

</body>
</html>
