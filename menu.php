<?php
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
?>

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
</style>
<div id="main-content-wrapper">
<div id="mySidebar" class="sidebar">
    <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
    <button class="mode-switch">🔄 Modo</button>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>"><i class="fa fa-home" aria-hidden="true"></i> Página Inicial</a>
    
    <button class="dropdown-btn"><i class="fa fa-folder-open" aria-hidden="true"></i> Arquivamento 
        <i class="fa fa-caret-down"></i>
    </button>
    <div class="dropdown-container">
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/index.php'?>"><i class="fa fa-folder-open" aria-hidden="true"></i> Ver Arquivamentos</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/cadastro.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Arquivamento</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/categorias.php'?>"><i class="fa fa-tags" aria-hidden="true"></i> Categorias de Arquivamentos</a>
    </div>

    <button class="dropdown-btn"><i class="fa fa-money" aria-hidden="true"></i> Ordens de Serviço 
        <i class="fa fa-caret-down"></i>
    </button>
    <div class="dropdown-container">
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/index.php'?>"><i class="fa fa-money" aria-hidden="true"></i> Ver Ordens de Serviço</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/criar_os.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Ordem de Serviço</a>
    </div>

    <button class="dropdown-btn"><i class="fa fa-clock-o" aria-hidden="true"></i> Tarefas 
        <i class="fa fa-caret-down"></i>
    </button>
    <div class="dropdown-container">
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/index.php'?>"><i class="fa fa-clock-o" aria-hidden="true"></i> Ver Tarefas</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/criar-tarefa.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Tarefa</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/categorias.php'?>"><i class="fa fa-tags" aria-hidden="true"></i> Categorias de Tarefas</a>
    </div>

    <button class="dropdown-btn"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Ofícios 
        <i class="fa fa-caret-down"></i>
    </button>
    <div class="dropdown-container">
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/index.php'?>"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Ver Ofícios</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/cadastrar-oficio.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Ofício</a>
    </div>

    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/assinar-doc.php'?>"><i class="fa fa-check-square-o" aria-hidden="true"></i> Assinador Digital</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/gerador_de_registro/index.php'?>"><i class="fa fa-file-text" aria-hidden="true"></i> Gerador de Registro de Garantias</a>
    
    <?php if ($nivel_de_acesso === 'administrador'): ?>
        <button class="dropdown-btn"><i class="fa fa-cog" aria-hidden="true"></i> Administração 
            <i class="fa fa-caret-down"></i>
        </button>
        <div class="dropdown-container">
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-serventia.php'?>"><i class="fa fa-cog" aria-hidden="true"></i> Dados da Serventia</a>
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-funcionario.php'?>"><i class="fa fa-users" aria-hidden="true"></i> Cadastro de Funcionários</a>
        </div>
    <?php endif; ?>
</div>

    <button class="openbtn" onclick="openNav()">&#9776; Menu</button>
    <div id="system-name"><a style="color: #fff!important;text-decoration: none!important;" href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>">Atlas</a></div>
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

    function getFontColor() {
        return $('body').hasClass('dark-mode') ? '#ffffff' : '#000000';
    }

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
</script>
