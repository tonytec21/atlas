<div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
        <button class="mode-switch">🔄 Modo</button>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>">Página Inicial</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/index.php'?>">Arquivamento</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/cadastro.php'?>">Criar arquivamento</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/categorias.php'?>">Categorias de arquivamento</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/index.php'?>">Tarefas</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/cadastro.php'?>">Criar Tarefa</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/categorias.php'?>">Categorias de tarefas</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/index.php'?>">Ofícios</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/cadastrar-oficio.php'?>">Criar Ofício</a>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/assinar-doc.php'?>">Assinador digital</a>
    </div>

    <div id="main-content-wrapper">
        <button class="openbtn" onclick="openNav()">&#9776; Menu</button>
        <div id="system-name"><a style="color: #fff!important;text-decoration: none!important;" href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>">Atlas</a></div>
        <div id="welcome-section">
            <div>
                <h2>Bem-vindo</h2>
                <p>Olá, <?php echo htmlspecialchars($_SESSION['username']); ?>. Você está logado.</p>
            </div>
            <a href="../logout.php" id="logout-button" class="btn btn-danger">Sair</a>
        </div>
    </div>