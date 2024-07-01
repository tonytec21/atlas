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
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>">Página Inicial</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/index.php'?>">Arquivamento</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/cadastro.php'?>">Criar arquivamento</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/categorias.php'?>">Categorias de arquivamentos</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/index.php'?>">Tarefas</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/criar-tarefa.php'?>">Criar Tarefa</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/categorias.php'?>">Categorias de tarefas</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/index.php'?>">Ofícios</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/cadastrar-oficio.php'?>">Criar Ofício</a>
    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/assinar-doc.php'?>">Assinador digital</a>
    <?php if ($nivel_de_acesso === 'administrador'): ?>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-serventia.php'?>">Dados da Serventia</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-funcionario.php'?>">Cadastro de Funcionários</a>
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
        document.getElementById("mySidebar").style.width = "250px";
        document.getElementById("main-content-wrapper").style.marginLeft = "250px";
    }

    function closeNav() {
        document.getElementById("mySidebar").style.width = "0";
        document.getElementById("main-content-wrapper").style.marginLeft = "0";
    }
</script>
