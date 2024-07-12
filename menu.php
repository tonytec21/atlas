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
</style>
<div id="mySidebar" class="sidebar">
    <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
    <button class="mode-switch">🔄 Modo</button>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>"><i class="fa fa-home" aria-hidden="true"></i> Página Inicial</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/index.php'?>"><i class="fa fa-folder-open" aria-hidden="true"></i> Arquivamento</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/cadastro.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar arquivamento</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/categorias.php'?>"><i class="fa fa-tags" aria-hidden="true"></i> Categorias de arquivamentos</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/index.php'?>"><i class="fa fa-clock-o" aria-hidden="true"></i> Tarefas</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/criar-tarefa.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Tarefa</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/categorias.php'?>"><i class="fa fa-tags" aria-hidden="true"></i> Categorias de tarefas</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/index.php'?>"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Ofícios</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/cadastrar-oficio.php'?>"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Ofício</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/assinar-doc.php'?>"><i class="fa fa-check-square-o" aria-hidden="true"></i> Assinador digital</a>
    <?php if ($nivel_de_acesso === 'administrador'): ?>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-serventia.php'?>"><i class="fa fa-cog" aria-hidden="true"></i> Dados da Serventia</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-funcionario.php'?>"><i class="fa fa-users" aria-hidden="true"></i> Cadastro de Funcionários</a>
    <?php endif; ?>
</div>

<div id="main-content-wrapper">
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
</script>
