<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . '/db_connection.php');

// Verificar o nível de acesso do usuário logado
$username = $_SESSION['username'];
$connAtlas = new mysqli("localhost", "root", "", "atlas");

if ($connAtlas->connect_error) {
    die("Falha na conexão com o banco atlas: " . $connAtlas->connect_error);
}

$stmt = $connAtlas->prepare("SELECT nivel_de_acesso FROM funcionarios WHERE usuario = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();
$connAtlas->close();

$nivel_de_acesso = $userData['nivel_de_acesso'];

// Verificar o acesso adicional do usuário logado
$tem_acesso_controle_contas = false;
$tem_acesso_cadastro_funcionarios = false;

if ($nivel_de_acesso === 'usuario') {
    // Fazer uma única consulta para pegar o acesso adicional do usuário
    $stmt_acesso = $conn->prepare("SELECT acesso_adicional FROM funcionarios WHERE usuario = ?");
    $stmt_acesso->bind_param("s", $username);
    $stmt_acesso->execute();
    $result_acesso = $stmt_acesso->get_result();
    $user_acesso_data = $result_acesso->fetch_assoc();
    $stmt_acesso->close();

    // Inicializar as variáveis de acesso
    $tem_acesso_controle_contas = false;
    $tem_acesso_cadastro_funcionarios = false;

    // Verificar se o campo acesso_adicional não é nulo e não está vazio
    if (!empty($user_acesso_data['acesso_adicional'])) {
        // Verificar se o acesso adicional contém "Controle de Contas a Pagar"
        if (strpos($user_acesso_data['acesso_adicional'], 'Controle de Contas a Pagar') !== false) {
            $tem_acesso_controle_contas = true;
        }

        // Verificar se o acesso adicional contém "Cadastro de Funcionários"
        if (strpos($user_acesso_data['acesso_adicional'], 'Cadastro de Funcionários') !== false) {
            $tem_acesso_cadastro_funcionarios = true;
        }
    }
}

// Verificar o modo atual do usuário (light-mode ou dark-mode)
$mode = 'light-mode';
$mode_query = $conn->prepare("SELECT modo FROM modo_usuario WHERE usuario = ?");
$mode_query->bind_param("s", $username);
$mode_query->execute();
$mode_result = $mode_query->get_result();
if ($mode_result->num_rows > 0) {
    $mode_data = $mode_result->fetch_assoc();
    $mode = $mode_data['modo'];
}
$mode_query->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Index</title>
    <style>
        @import url(https://fonts.googleapis.com/css2?family=Roboto&display=swap);
        body {
            font-family: 'Roboto', sans-serif;
        }
        #system-name {
            margin-left: 177px;
        }
        .sidebar a, .dropdown-btn {
            padding: 10px 15px;
            text-decoration: none;
            font-size: 18px;
            color: #818181;
            display: block;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            outline: none;
        }
        .sidebar a:hover, .dropdown-btn:hover {
            color: #f1f1f1;
        }
        .dropdown-container {
            display: none;
            background-color: #262626;
            padding-left: 8px;
        }
        .fa-caret-down {
            float: right;
            padding-right: 8px;
        }
        .sidebar .closebtn {
            right: -80%!important;
        }
        body.dark-mode {
            background-color: #222e35;
            color: #ffffff;
        }
        body.dark-mod .video-description {
            color: #fff;
        }
        body.light-mode {
            background-color: #e3f6ff;
            color: #000000;
        }
        body.light-mod .video-description {
            color: #6c6b6b;
        }
        body.dark-mode .card {
            background-color: #4a4a4a;
        }
        body.dark-mode .card-header {
            background-color: rgb(255 0 0 / 3%)
        }
    </style>
</head>
<body class="<?php echo $mode; ?>">
<div id="main-content-wrapper">
    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
        <button class="mode-switch" onclick="toggleMode()"><span id="mode-icon"><?php echo $mode === 'dark-mode' ? '🌙' : '☀️'; ?></span> Modo</button>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>"><i class="fa fa-home" aria-hidden="true"></i> Página Inicial</a>

        <button class="dropdown-btn"><i class="fa fa-folder-open" aria-hidden="true"></i> Arquivamento 
            <i class="fa fa-caret-down"></i>
        </button>
        <div class="dropdown-container">
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/index.php'?>"><i class="fa fa-eye" aria-hidden="true"></i> Ver Arquivamentos</a>
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/cadastro.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Arquivamento</a>
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/categorias.php'?>"><i class="fa fa-tags" aria-hidden="true"></i> Categorias de Arquivamentos</a>
        </div>

        <button class="dropdown-btn"><i class="fa fa-money" aria-hidden="true"></i> Ordens de Serviço 
            <i class="fa fa-caret-down"></i>
        </button>
        <div class="dropdown-container">
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/index.php'?>"><i class="fa fa-eye" aria-hidden="true"></i> Ver Ordens de Serviço</a>
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/criar_os.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Ordem de Serviço</a>
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/tabela_de_emolumentos.php'?>"><i class="fa fa fa-table" aria-hidden="true"></i> Tabela de Emolumentos</a>
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/caixa/index.php'?>"><i class="fa fa-university" aria-hidden="true"></i> Controle de Caixa</a>
        </div>

        <button class="dropdown-btn"><i class="fa fa-clock-o" aria-hidden="true"></i> Tarefas 
            <i class="fa fa-caret-down"></i>
        </button>
        <div class="dropdown-container">
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/index.php'?>"><i class="fa fa-eye" aria-hidden="true"></i> Ver Tarefas</a>
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/criar-tarefa.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Tarefa</a>
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/categorias.php'?>"><i class="fa fa-tags" aria-hidden="true"></i> Categorias de Tarefas</a>
        </div>

        <button class="dropdown-btn"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Ofícios 
            <i class="fa fa-caret-down"></i>
        </button>
        <div class="dropdown-container">
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/index.php'?>"><i class="fa fa-eye" aria-hidden="true"></i> Ver Ofícios</a>
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/cadastrar-oficio.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Ofício</a>
        </div>

        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/suas_notas/index.php'?>"><i class="fa fa-check-square-o" aria-hidden="true"></i> Anotações</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/relatorios/index.php'?>"><i class="fa fa-line-chart" aria-hidden="true"></i> Relatórios</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/manuais/index.php'?>"><i class="fa fa-file-video-o" aria-hidden="true"></i> Vídeos Tutoriais</a>

        <?php if ($nivel_de_acesso === 'administrador' || $tem_acesso_controle_contas): ?>
            <button class="dropdown-btn"><i class="fa fa-usd" aria-hidden="true"></i> Contas a Pagar 
                <i class="fa fa-caret-down"></i>
            </button>
            <div class="dropdown-container">
                <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/contas_a_pagar/index.php'?>"><i class="fa fa-eye" aria-hidden="true"></i> Ver Contas a Pagar</a>
                <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/contas_a_pagar/cadastrar.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Cadastrar Contas a Pagar</a>
            </div>
        <?php endif; ?>

        <?php if ($nivel_de_acesso === 'administrador' || $tem_acesso_cadastro_funcionarios): ?>            
            <button class="dropdown-btn"><i class="fa fa-cog" aria-hidden="true"></i> Administração 
                <i class="fa fa-caret-down"></i>
            </button>
            <div class="dropdown-container">
                <?php if ($nivel_de_acesso === 'administrador'): ?>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-serventia.php'?>"><i class="fa fa-cog" aria-hidden="true"></i> Dados da Serventia</a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/configuracao_os.php'?>"><i class="fa fa-university" aria-hidden="true"></i> Configuração de Contas</a>
                <?php endif; ?>
                <?php if ($nivel_de_acesso === 'administrador' || $tem_acesso_cadastro_funcionarios): ?>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-funcionario.php'?>"><i class="fa fa-users" aria-hidden="true"></i> Cadastro de Funcionários</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <button class="openbtn" onclick="openNav()">&#9776; Menu</button>
    <div id="system-name">
        <a style="color: #fff!important;text-decoration: none!important;" href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>">
            <img src="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/atlas.png'?>" alt="Atlas" style="vertical-align: middle;width: 88px;">
        </a>
    </div>

    <div id="welcome-section">
        <div>
            <h2>Bem-vindo</h2>
            <p><a style="margin-left: 0px;margin-right: 10px;color: #fff;text-decoration: none;" href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/atualizar-credenciais.php'?>">Olá, <?php echo htmlspecialchars($_SESSION['username']); ?>. Você está logado.</a></p>
        </div>
        <a href="logout.php" id="logout-button" class="btn btn-danger">Sair</a>
    </div>
</div>

<script>
    function openNav() {
        document.getElementById("mySidebar").style.width = "300px";
        document.getElementById("main").style.marginLeft = "300px";
    }

    function closeNav() {
        document.getElementById("mySidebar").style.width = "0";
        document.getElementById("main").style.marginLeft = "0";
    }

    function toggleMode() {
        var body = document.body;
        var modeIcon = document.getElementById("mode-icon");
        var currentMode = body.classList.contains('dark-mode') ? 'dark-mode' : 'light-mode';
        var newMode = currentMode === 'dark-mode' ? 'light-mode' : 'dark-mode';

        // Atualizar o ícone
        modeIcon.innerHTML = newMode === 'dark-mode' ? '🌙' : '☀️';

        // Salvar o novo modo no banco de dados e atualizar a página
        $.ajax({
            url: '<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/save_mode.php'?>',
            method: 'POST',
            data: { mode: newMode },
            success: function(response) {
                console.log(response);
                body.classList.remove('light-mode', 'dark-mode');
                body.classList.add(newMode);
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Carregar o modo do usuário
        $.ajax({
            url: '<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/load_mode.php'?>',
            method: 'GET',
            success: function(mode) {
                var body = document.body;
                var modeIcon = document.getElementById("mode-icon");

                body.classList.remove('light-mode', 'dark-mode');
                body.classList.add(mode);
                if (mode === 'dark-mode') {
                    modeIcon.innerHTML = '🌙';
                } else {
                    modeIcon.innerHTML = '☀️';
                }
            }
        });
    });

    var dropdown = document.getElementsByClassName("dropdown-btn");
    var i;

    for (i = 0; i < dropdown.length; i++) {
        dropdown[i].addEventListener("click", function() {
            this.classList.toggle("active");
            var dropdownContent = this.nextElementSibling;
            if (dropdownContent.style.display === "block") {
                dropdownContent.style.display = "none";
            } else {
                dropdownContent.style.display = "block";
            }
        });
    }

    function getFontColor() {
        return $('body').hasClass('dark-mode') ? '#ffffff' : '#000000';
    }
</script>
</body>
</html>
