<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/checar_acesso_cadastro.php');

$notificationMessage = null;
$notificationType = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? $_POST['id'] : null;
    $usuario = $_POST['usuario'];
    $senha = isset($_POST['senha']) ? $_POST['senha'] : null;
    $nome_completo = $_POST['nome_completo'];
    $cargo = $_POST['cargo'];
    $nivel_de_acesso = $_POST['nivel_de_acesso'];
    $e_mail = !empty($_POST['e_mail']) ? $_POST['e_mail'] : null;
    $acesso_adicional = !empty($_POST['acesso_adicional']) ? implode(',', $_POST['acesso_adicional']) : null;

    // Validação para permitir apenas letras e números no campo "Usuário"
    if (!preg_match('/^[a-zA-Z0-9]+$/', $usuario)) {
        $notificationMessage = "O campo Usuário deve conter apenas letras e números, sem espaços ou caracteres especiais.";
        $notificationType = 'danger';
    } else {
        $errorMessage = saveFuncionario($id, $usuario, $senha, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail);
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
function saveFuncionario($id, $usuario, $senha, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail) {
    // Verificar se uma nova senha foi fornecida
    $senha_base64 = !empty($senha) ? base64_encode($senha) : null;

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
        // Atualização: verificar se a senha foi fornecida
        if ($senha_base64) {
            $stmtAtlas = $connAtlas->prepare("UPDATE funcionarios SET usuario = ?, senha = ?, nome_completo = ?, cargo = ?, nivel_de_acesso = ?, acesso_adicional = ?, e_mail = ? WHERE id = ?");
            $stmtAtlas->bind_param("sssssssi", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail, $id);

            $stmtOficios = $connOficios->prepare("UPDATE funcionarios SET usuario = ?, senha = ?, nome_completo = ?, cargo = ?, nivel_de_acesso = ?, acesso_adicional = ?, e_mail = ? WHERE id = ?");
            $stmtOficios->bind_param("sssssssi", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail, $id);
        } else {
            // Se não houver nova senha, não altere a senha
            $stmtAtlas = $connAtlas->prepare("UPDATE funcionarios SET usuario = ?, nome_completo = ?, cargo = ?, nivel_de_acesso = ?, acesso_adicional = ?, e_mail = ? WHERE id = ?");
            $stmtAtlas->bind_param("ssssssi", $usuario, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail, $id);

            $stmtOficios = $connOficios->prepare("UPDATE funcionarios SET usuario = ?, nome_completo = ?, cargo = ?, nivel_de_acesso = ?, acesso_adicional = ?, e_mail = ? WHERE id = ?");
            $stmtOficios->bind_param("ssssssi", $usuario, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail, $id);
        }
    } else {
        // Inserção de um novo funcionário
        $stmtAtlas = $connAtlas->prepare("INSERT INTO funcionarios (usuario, senha, nome_completo, cargo, nivel_de_acesso, acesso_adicional, e_mail) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtAtlas->bind_param("sssssss", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail);

        $stmtOficios = $connOficios->prepare("INSERT INTO funcionarios (usuario, senha, nome_completo, cargo, nivel_de_acesso, acesso_adicional, e_mail) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtOficios->bind_param("sssssss", $usuario, $senha_base64, $nome_completo, $cargo, $nivel_de_acesso, $acesso_adicional, $e_mail);
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
    <link href="style/css/select2.min.css" rel="stylesheet" />
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

        .select2-container--default .select2-selection--multiple {
            display: block;
            width: 100%;
            height: auto;
            padding: .375rem .75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da; 
            border-radius: .25rem;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }

        .select2-container--default .select2-selection--multiple:focus,
        .select2-container--default .select2-selection--multiple:active {
            color: #495057;
            background-color: #fff;
            border-color: #80bdff; 
            outline: 0;
            box-shadow: 0 0 0 .2rem rgba(0, 123, 255, .25); 
        }

        .select2-container--default .select2-selection--multiple .select2-selection__placeholder {
            color: #6c757d; 
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #007bff; 
            color: white; 
            border-radius: .2rem;
            padding: 0.25rem 0.75rem 0.25rem 0.5rem; 
            margin-right: 0.25rem; 
            margin-top: 0.25rem; 
            display: inline-block;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            position: relative; 
            right: 0;
            margin-left: -0.5rem;
            font-weight: bold;
            font-size: 1rem; 
            color: white; 
            cursor: pointer;
        }

        .select2-container--default .select2-selection--multiple:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); 
        }

        @media (prefers-reduced-motion: reduce) {
            .select2-container--default .select2-selection--multiple {
                transition: none;
            }
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
                    <input type="password" class="form-control" id="senha" name="senha">
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

            <div class="row">
                <div class="form-group col-md-8" id="email-container">
                    <label for="e_mail">E-mail</label>
                    <input type="email" class="form-control" id="e_mail" name="e_mail">
                </div>
                <div class="form-group col-md-4" id="acesso-adicional-container">
                    <label for="acesso_adicional">Acesso Adicional</label>
                    <select class="form-control select2" id="acesso_adicional" name="acesso_adicional[]" multiple="multiple">
                        <option value="Controle de Tarefas">Controle de Tarefas</option>
                        <option value="Fluxo de Caixa">Fluxo de Caixa</option>
                        <option value="Controle de Contas a Pagar">Controle de Contas a Pagar</option>
                        <option value="Cadastro de Funcionários">Cadastro de Funcionários</option>
                        <!-- <option value="Configuração de Contas">Configuração de Contas</option> -->
                    </select>
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
                            <th>E-mail</th>
                            <th>Acesso Adicional</th>
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
                                <td><?php echo htmlspecialchars($funcionario['e_mail'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($funcionario['acesso_adicional'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <button class="btn btn-warning btn-edit" 
                                            data-id="<?php echo $funcionario['id']; ?>" 
                                            data-usuario="<?php echo htmlspecialchars($funcionario['usuario'], ENT_QUOTES, 'UTF-8'); ?>" 
                                            data-nome="<?php echo htmlspecialchars($funcionario['nome_completo'], ENT_QUOTES, 'UTF-8'); ?>" 
                                            data-cargo="<?php echo htmlspecialchars($funcionario['cargo'], ENT_QUOTES, 'UTF-8'); ?>" 
                                            data-nivel_de_acesso="<?php echo htmlspecialchars($funcionario['nivel_de_acesso'], ENT_QUOTES, 'UTF-8'); ?>" 
                                            data-e_mail="<?php echo htmlspecialchars($funcionario['e_mail'], ENT_QUOTES, 'UTF-8'); ?>" 
                                            data-acesso_adicional="<?php echo htmlspecialchars($funcionario['acesso_adicional'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-pencil" aria-hidden="true"></i>
                                    </button>
                                    <a href="#" class="btn btn-delete" onclick="confirmDelete('<?php echo $funcionario['id']; ?>'); return false;"><i class="fa fa-trash" aria-hidden="true"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
        </div>
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
<script src="script/select2.min.js"></script>
<script src="script/sweetalert2.js"></script>
<script>
    function showNotification(message, type) {
        if (type === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: message,
                showConfirmButton: false,
                timer: 5000
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: message,
                showConfirmButton: false,
                timer: 5000
            });
        }
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Tem certeza?',
            text: 'Tem certeza que deseja excluir este funcionário?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Não, cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redireciona para a URL de exclusão se o usuário confirmar
                window.location.href = '?delete_id=' + id;
            }
        });
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
            $('#e_mail').val($(this).data('e_mail'));

            // Desabilitar o campo de senha e remover a obrigatoriedade ao editar
            $('#senha').val(''); // Limpar o campo de senha
            $('#senha').prop('disabled', true); // Desabilitar o campo de senha
            $('#senha').removeAttr('required'); // Remover a obrigatoriedade
            $('#senha-help-text').text('Deixe em branco para não alterar a senha.'); // Atualizar texto de ajuda

            // Carregar o campo de acesso adicional corretamente
            $('#acesso_adicional').val(null).trigger('change');
            var acessoAdicional = $(this).data('acesso_adicional');
            if (acessoAdicional) {
                var acessos = acessoAdicional.split(',');
                $('#acesso_adicional').val(acessos).trigger('change');
            }

            // Alterar o texto do botão para "Salvar Alterações"
            $('#submit-button').html('<i class="fa fa-floppy-o" aria-hidden="true"></i> Salvar Alterações');
            $('html, body').animate({ scrollTop: 0 }, 'slow');
        });

        // Resetar o formulário para o cadastro de novo funcionário
        $('#novo-funcionario').on('click', function() {
            $('#funcionario-id').val(''); // Limpar o ID
            $('#usuario').val('');
            $('#nome_completo').val('');
            $('#cargo').val('');
            $('#nivel_de_acesso').val('');
            $('#e_mail').val('');

            // Habilitar o campo de senha e definir como obrigatório ao cadastrar
            $('#senha').val('');
            $('#senha').prop('disabled', false); // Habilitar o campo de senha
            $('#senha').attr('required', true); // Definir como obrigatório
            $('#senha-help-text').text('Obrigatório ao cadastrar.');

            // Limpar o campo de acesso adicional
            $('#acesso_adicional').val(null).trigger('change');

            // Alterar o texto do botão para "Cadastrar"
            $('#submit-button').html('<i class="fa fa-floppy-o" aria-hidden="true"></i> Cadastrar');
            $('html, body').animate({ scrollTop: 0 }, 'slow');
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
            var sanitizedUsuario = usuario.replace(/[^a-zA-Z0-9]/g, '');
            if (usuario !== sanitizedUsuario) {
                $(this).val(sanitizedUsuario); 
            }
        });
    });

    $(document).ready(function() {
        $('.select2').select2({
            placeholder: "",
            allowClear: true
        });
    });

    $(document).ready(function() {
        // Inicializar o select2
        $('.select2').select2({
            placeholder: "Selecione os acessos adicionais",
            allowClear: true
        });

        function toggleAcessoAdicional() {
            var nivelDeAcesso = $('#nivel_de_acesso').val();
            if (nivelDeAcesso === 'usuario') {
                $('#acesso-adicional-container').show(); 
                $('#email-container').removeClass('col-md-12').addClass('col-md-8');
            } else {
                $('#acesso-adicional-container').hide(); 
                $('#email-container').removeClass('col-md-8').addClass('col-md-12'); 
            }
        }

        toggleAcessoAdicional();

        $('#nivel_de_acesso').on('change', function() {
            toggleAcessoAdicional();
        });
    });

</script>

<br><br><br>
<?php
include(__DIR__ . '/rodape.php');
?>

</body>
</html>
