<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Função para cadastrar ou atualizar funcionários nos dois bancos de dados
function saveFuncionario($id, $usuario, $senha, $nome_completo, $cargo) {
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

    // Verificar se é um novo cadastro ou atualização
    if ($id) {
        $stmtAtlas = $connAtlas->prepare("UPDATE funcionarios SET usuario = ?, senha = ?, nome_completo = ?, cargo = ? WHERE id = ?");
        $stmtAtlas->bind_param("ssssi", $usuario, $senha_base64, $nome_completo, $cargo, $id);
        $stmtOficios = $connOficios->prepare("UPDATE funcionarios SET usuario = ?, senha = ?, nome_completo = ?, cargo = ? WHERE id = ?");
        $stmtOficios->bind_param("ssssi", $usuario, $senha_base64, $nome_completo, $cargo, $id);
    } else {
        $stmtAtlas = $connAtlas->prepare("INSERT INTO funcionarios (usuario, senha, nome_completo, cargo) VALUES (?, ?, ?, ?)");
        $stmtAtlas->bind_param("ssss", $usuario, $senha_base64, $nome_completo, $cargo);
        $stmtOficios = $connOficios->prepare("INSERT INTO funcionarios (usuario, senha, nome_completo, cargo) VALUES (?, ?, ?, ?)");
        $stmtOficios->bind_param("ssss", $usuario, $senha_base64, $nome_completo, $cargo);
    }

    $stmtAtlas->execute();
    $stmtAtlas->close();
    $connAtlas->close();

    $stmtOficios->execute();
    $stmtOficios->close();
    $connOficios->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? $_POST['id'] : null;
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];
    $nome_completo = $_POST['nome_completo'];
    $cargo = $_POST['cargo'];

    saveFuncionario($id, $usuario, $senha, $nome_completo, $cargo);
    $successMessage = "Funcionário " . ($id ? "atualizado" : "cadastrado") . " com sucesso!";
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

    // Deletar dados na tabela "funcionarios" do banco "atlas"
    $stmtAtlas = $connAtlas->prepare("DELETE FROM funcionarios WHERE id = ?");
    $stmtAtlas->bind_param("i", $id);
    $stmtAtlas->execute();
    $stmtAtlas->close();
    $connAtlas->close();

    // Deletar dados na tabela "funcionarios" do banco "oficios_db"
    $stmtOficios = $connOficios->prepare("DELETE FROM funcionarios WHERE id = ?");
    $stmtOficios->bind_param("i", $id);
    $stmtOficios->execute();
    $stmtOficios->close();
    $connOficios->close();

    $successMessage = "Funcionário excluído com sucesso!";
}

// Conexão com o banco de dados "atlas"
$connAtlas = new mysqli("localhost", "root", "", "atlas");
if ($connAtlas->connect_error) {
    die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
}
$connAtlas->set_charset("utf8");

// Buscar todos os funcionários no banco "atlas"
$result = $connAtlas->query("SELECT * FROM funcionarios");
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
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <input type="hidden" name="id" id="funcionario-id">
            <div class="form-group">
                <label for="usuario">Usuário</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <div class="form-group">
                <label for="nome_completo">Nome Completo</label>
                <input type="text" class="form-control" id="nome_completo" name="nome_completo" required>
            </div>
            <div class="form-group">
                <label for="cargo">Cargo</label>
                <input type="text" class="form-control" id="cargo" name="cargo" required>
            </div>
            <button type="submit" class="btn btn-primary">Cadastrar</button>
        </form>

        <h3 class="mt-5">Funcionários Cadastrados</h3>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuário</th>
                    <th>Nome Completo</th>
                    <th>Cargo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($funcionarios as $funcionario): ?>
                    <tr>
                        <td><?php echo $funcionario['id']; ?></td>
                        <td><?php echo htmlspecialchars($funcionario['usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($funcionario['nome_completo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($funcionario['cargo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <button class="btn btn-warning btn-edit" data-id="<?php echo $funcionario['id']; ?>" data-usuario="<?php echo htmlspecialchars($funcionario['usuario'], ENT_QUOTES, 'UTF-8'); ?>" data-nome="<?php echo htmlspecialchars($funcionario['nome_completo'], ENT_QUOTES, 'UTF-8'); ?>" data-cargo="<?php echo htmlspecialchars($funcionario['cargo'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                            <a href="?delete_id=<?php echo $funcionario['id']; ?>" class="btn btn-delete" onclick="return confirm('Tem certeza que deseja excluir este funcionário?');"><i class="fa fa-trash" aria-hidden="true"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="script/jquery-3.5.1.min.js"></script>
<script src="script/bootstrap.min.js"></script>
<script src="script/jquery.mask.min.js"></script>
<script>
    function createChart(ctx, type, data, options) {
        return new Chart(ctx, {
            type: type,
            data: data,
            options: $.extend(true, {
                plugins: {
                    legend: {
                        display: type !== 'doughnut' && type !== 'bar' // Hide legend for doughnut and bar charts
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: getFontColor()
                        }
                    },
                    y: {
                        ticks: {
                            color: getFontColor()
                        }
                    }
                }
            }, options)
        });
    }

    $(document).ready(function() {
        // Carregar o modo do usuário
        $.ajax({
            url: 'load_mode.php',
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
                url: 'save_mode.php',
                method: 'POST',
                data: { mode: mode },
                success: function(response) {
                    console.log(response);
                }
            });

            // Atualizar cores das legendas dos gráficos
            Chart.helpers.each(Chart.instances, function(instance) {
                instance.options.plugins.legend.labels.color = getFontColor();
                instance.options.scales.x.ticks.color = getFontColor();
                instance.options.scales.y.ticks.color = getFontColor();
                instance.update();
            });
        });

        // Aplicar máscara ao campo CNS
        $('#cns').mask('000000');

        // Carregar dados do funcionário ao clicar em "Editar"
        $('.btn-edit').on('click', function() {
            $('#funcionario-id').val($(this).data('id'));
            $('#usuario').val($(this).data('usuario'));
            $('#nome_completo').val($(this).data('nome'));
            $('#cargo').val($(this).data('cargo'));
            $('#senha').val(''); // Limpar campo de senha ao editar
            $('html, body').animate({ scrollTop: 0 }, 'slow'); // Rolar para o topo do formulário
        });

        // Função para fechar a notificação
        $('.notification .close-btn').on('click', function() {
            $(this).parent().hide();
        });
    });
</script>
<br><br><br>
<?php
include(__DIR__ . '/rodape.php');
?>

</body>
</html>
