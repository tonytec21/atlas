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

// Atualizado para incluir o cargo  
$stmt = $connAtlas->prepare("SELECT nivel_de_acesso, cargo FROM funcionarios WHERE usuario = ?");  
$stmt->bind_param("s", $username);  
$stmt->execute();  
$result = $stmt->get_result();  
$userData = $result->fetch_assoc();  
$stmt->close();  
$connAtlas->close();  

$nivel_de_acesso = $userData['nivel_de_acesso'];  
$cargo_funcionario = $userData['cargo']; // Nova variável para o cargo  

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
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Atlas - Sistema de Gestão</title>  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">  
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">  
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script> 
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">  

    <style>  
        :root {  
            --primary-bg: #1e2835;  
            --secondary-bg: #2c3847;  
            --accent-color: #3498db;  
            --text-primary: #ffffff;  
            --text-secondary: #a4b4c4;  
            --danger-color: #e74c3c;  
            --success-color: #2ecc71;  
            --warning-color: #f1c40f;  
            --sidebar-width: 280px;  
            --mini-sidebar-width: 60px;  
            --header-height: 60px;  
            --transition: all 0.3s ease;  
        }  

        * {  
            margin: 0;  
            padding: 0;  
            box-sizing: border-box;  
            font-family: 'Inter', sans-serif;  
        }  

        body {  
            background-color: var(--primary-bg);  
            color: var(--text-primary);  
            line-height: 1.6;  
            transition: var(--transition);  
        }  

        /* Sidebar Styles */  
        .sidebar {  
            height: 100%;  
            width: var(--mini-sidebar-width);  
            position: fixed;  
            top: 0;  
            left: 0;  
            background-color: var(--secondary-bg);  
            overflow: hidden;  
            transition: var(--transition);  
            padding-top: var(--header-height);  
            z-index: 1000;  
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);  
        }  

        .sidebar:hover, .sidebar.active {  
            width: var(--sidebar-width);  
        }  

        .sidebar a, .dropdown-btn {  
            padding: 12px 20px;  
            text-decoration: none;  
            font-size: 14px;  
            color: var(--text-secondary);  
            display: flex;  
            align-items: center;  
            white-space: nowrap;  
            transition: var(--transition);  
            border: none;  
            background: none;  
            width: 100%;  
            text-align: left;  
            cursor: pointer;  
        }  

        .sidebar i {  
            min-width: 20px;  
            margin-right: 30px;  
        }  

        .sidebar a:hover, .dropdown-btn:hover {  
            color: var(--text-primary);  
            background-color: rgba(255,255,255,0.1);  
        }  

        .dropdown-container {  
            display: none;  
            background-color: rgba(0,0,0,0.2);  
            /* padding-left: 20px;   */
        }  





        /* Estilos base do dropdown */  
        .dropdown-btn {  
            position: relative;  
            width: 100%;  
            text-align: left;  
            padding: 12px 20px;  
            background: none;  
            border: none;  
            color: var(--text-secondary);  
            cursor: pointer;  
            display: flex;  
            align-items: center;  
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);  
        }  

        /* Container do dropdown */  
        .dropdown-container {  
            position: relative;  
            background-color: rgba(0, 0, 0, 0.2);  
            overflow: hidden;  
            max-height: 0;  
            opacity: 0;  
            transform-origin: top;  
            transform: scaleY(0);  
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);  
        }  

        /* Animação ao hover */  
        .dropdown-btn:hover + .dropdown-container,  
        .dropdown-container:hover {  
            max-height: 500px;  
            opacity: 1;  
            transform: scaleY(1);  
        }  

        /* Links dentro do dropdown */  
        .dropdown-container a {  
            position: relative;  
            padding: 10px 20px 10px 55px;  
            display: flex;  
            align-items: center;  
            transform: translateY(-5px);  
            opacity: 0;  
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);  
        }  

        /* Animação dos links quando o dropdown está aberto */  
        .dropdown-btn:hover + .dropdown-container a,  
        .dropdown-container:hover a {  
            transform: translateY(0);  
            opacity: 1;  
        }  

        /* Delay escalonado para cada item do submenu */  
        .dropdown-container a:nth-child(1) { transition-delay: 0.05s; }  
        .dropdown-container a:nth-child(2) { transition-delay: 0.1s; }  
        .dropdown-container a:nth-child(3) { transition-delay: 0.15s; }  
        .dropdown-container a:nth-child(4) { transition-delay: 0.2s; }  

        /* Ícone de seta */  
        .dropdown-btn .fa-chevron-down {  
            margin-left: auto;  
            font-size: 0.8em;  
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);  
        }  

        .dropdown-btn:hover .fa-chevron-down {  
            transform: rotate(180deg);  
        }  

        /* Efeito hover nos itens do submenu */  
        .dropdown-container a:before {  
            content: '';  
            position: absolute;  
            left: 0;  
            top: 0;  
            height: 100%;  
            width: 0;  
            background-color: rgba(255, 255, 255, 0.05);  
            transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);  
        }  

        .dropdown-container a:hover:before {  
            width: 100%;  
        }  

        /* Efeito hover no botão principal */  
        .dropdown-btn:hover {  
            background-color: rgba(255, 255, 255, 0.05);  
            color: var(--text-primary);  
        }  

        /* Ajuste dos ícones */  
        .dropdown-container a i {  
            margin-right: 10px;  
            width: 20px;  
            text-align: center;  
            opacity: 0.8;  
            transition: opacity 0.25s ease;  
        }  

        .dropdown-container a:hover i {  
            opacity: 1;  
        }





        /* Header Styles */  
        #system-name {
            position: fixed !important;
            top: 0 !important;
            left: var(--mini-sidebar-width) !important;
            right: 0 !important;
            height: var(--header-height) !important;
            background-color: var(--secondary-bg) !important;
            display: flex !important;
            align-items: center !important;
            padding: 0 20px !important;
            z-index: 900 !important;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1) !important;
            transition: var(--transition) !important;
        }

        .sidebar:hover ~ #system-name,  
        .sidebar.active ~ #system-name {  
            left: var(--sidebar-width);  
        }  

        .openbtn {  
            background: none;  
            border: none;  
            color: var(--text-primary);  
            font-size: 24px;  
            cursor: pointer;  
            padding: 0 15px;  
            display: flex;  
            align-items: center;  
            height: var(--header-height);  
            transition: var(--transition);  
        }  

        .openbtn:hover {  
            background-color: rgba(255,255,255,0.1);  
        }  

        /* Welcome Section Styles */  
        #welcome-section {
            position: fixed !important;
            top: 0 !important;
            right: 0 !important;
            height: var(--header-height) !important;
            display: flex !important;
            align-items: center !important;
            gap: 16px !important;
            padding: 0 20px !important;
            z-index: 901 !important;
        }

        .mode-switch {  
            background: none;  
            border: none;  
            color: #6B7280;  
            cursor: pointer;  
            padding: 8px;  
            font-size: 20px;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            transition: var(--transition);  
            border-radius: 50%;  
            width: 38px;
        }  

        .mode-switch:hover {  
            background-color: rgba(255,255,255,0.1);  
        }  

        .user-info {  
            display: flex;  
            align-items: center;  
            gap: 10px;  
            background-color: rgba(255, 255, 255, 0.05);  
            padding: 5px;  
            padding-left: 5px;  
            padding-right: 12px;  
            border-radius: 50px;  
        }  

        .user-avatar {  
            width: 35px;  
            height: 35px;  
            background-color: #2563EB;  
            color: white;  
            border-radius: 50%;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            font-weight: 600;  
            font-size: 16px;  
        }  

        .user-details {  
            display: flex;  
            flex-direction: column;  
            line-height: 1.2;  
        }  

        .user-role {  
            font-size: 14px;  
            font-weight: 600;  
            color: var(--text-primary);  
        }  

        .user-type {  
            font-size: 13px;  
            color: var(--text-secondary);  
        }  

        .logout-button {  
            background-color: #DC2626;  
            color: white;  
            padding: 8px 16px;  
            border-radius: 50px;  
            text-decoration: none;  
            font-size: 14px;  
            font-weight: 500;  
            display: flex;  
            align-items: center;  
            gap: 6px;  
            transition: background-color 0.2s;  
        }  

        .logout-button:hover {  
            text-decoration: none;
            background-color: #B91C1C;  
        }  

        .logout-button i {  
            text-decoration: none;
            font-size: 14px;  
        }  

        /* Main Content */  
        .main-content {  
            margin-left: var(--mini-sidebar-width);  
            padding: calc(var(--header-height) + 20px) 20px 20px;  
            transition: var(--transition);  
        }  

        .sidebar:hover ~ .main-content,  
        .sidebar.active ~ .main-content {  
            margin-left: var(--sidebar-width);  
        }  

        /* Dark/Light Mode */  
        body.light-mode {  
            --primary-bg: #f8f9fa;  
            --secondary-bg: #ffffff;  
            --text-primary: #2c3847;  
            --text-secondary: #6c757d;  
        }  

        /* Estilos base do header mobile */  
        @media screen and (max-width: 768px) {  
            #system-name {
                display: flex !important;
                align-items: center !important;
                padding: 10px !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                background-color: var(--secondary-bg) !important;
                z-index: 1000 !important;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            }

            #welcome-section {
                position: fixed !important;
                top: 0 !important;
                right: 0 !important;
                padding: 10px !important;
                z-index: 1001 !important;
                display: flex !important;
                gap: 10px !important;
            }
  
            .nav-toggle {  
                display: flex;  
                align-items: center;  
                justify-content: center;  
                background: none;  
                border: none;  
                color: var(--text-primary);  
                font-size: 1.5rem;  
                cursor: pointer;  
                padding: 8px;  
                z-index: 1002;  
                transition: all 0.3s ease;  
            }  

            /* Ajuste dos botões no header */  
            .mode-switch {  
                padding: 8px;  
                background: none;  
                border: none;  
                color: var(--text-primary);  
                cursor: pointer;  
                font-size: 1.2rem;  
            }  

            .user-info {  
                display: none; 
            }  

            .logout-button {
                background-color: #DC2626 !important;
                color: white !important;
                padding: 8px !important;
                border: none !important;
                cursor: pointer !important;
                font-size: 0.87rem !important;
            }

            .logout-button:hover {
                color: white !important;
                background-color: #B91C1C !important;
            }


            /* Overlay para quando o menu estiver aberto */  
            .sidebar-overlay {  
                display: none;  
                position: fixed;  
                top: 0;  
                left: 0;  
                right: 0;  
                bottom: 0;  
                background: rgba(0,0,0,0.5);  
                z-index: 998;  
            }  

            .sidebar-overlay.active {  
                display: block;  
            }  

            /* Ajustes da sidebar em mobile */  
            .sidebar {  
                position: fixed;  
                left: -280px;  
                top: 0;  
                bottom: 0;  
                width: 280px;  
                transition: all 0.3s ease;  
                z-index: 999;  
            }  

            .sidebar.active {  
                left: 0;  
            }  
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

                .user-info {  
            text-decoration: none;  
            transition: transform 0.2s ease;  
        }  

        .user-info:hover {  
            text-decoration: none;
            transform: translateY(-1px);  
        }

        .nav-toggle {  
            display: none; /* Esconde por padrão em desktop */  
            background: none;  
            border: none;  
            color: var(--text-primary);  
            font-size: 1.5rem;  
            cursor: pointer;  
            padding: 10px;  
            transition: color 0.3s ease;  
        }  

        /* Mostra o botão apenas em mobile */  
        @media screen and (max-width: 768px) {  
            .nav-toggle {  
                display: block;  
            }  
            
            #system-name {  
                display: flex;  
                align-items: center;  
            }  
        }  

        .nav-toggle:hover {  
            color: var(--text-secondary);  
        }

    </style>  
</head>
<body class="<?php echo $mode; ?>">  
<div class="sidebar-overlay" onclick="toggleNav()"></div>
    <div id="mySidebar" class="sidebar">  
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>">  
            <i class="fas fa-home"></i>  
            <span>Central de Acesso</span>  
        </a>  

        <button class="dropdown-btn">  
            <i class="fas fa-folder"></i>  
            <span>Arquivamento</span>  
            <i class="fas fa-chevron-down ml-auto"></i>  
        </button>  
        <div class="dropdown-container">  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/index.php'?>">  
                <i class="fas fa-eye"></i>  
                <span>Ver Arquivamentos</span>  
            </a>  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/cadastro.php'?>">  
                <i class="fas fa-plus-circle"></i>  
                <span>Criar Arquivamento</span>  
            </a>  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/categorias.php'?>">  
                <i class="fas fa-tags"></i>  
                <span>Categorias</span>  
            </a>  
        </div>  

        <button class="dropdown-btn">  
            <i class="fas fa-file-invoice-dollar"></i>  
            <span>Ordens de Serviço</span>  
            <i class="fas fa-chevron-down ml-auto"></i>  
        </button>  
        <div class="dropdown-container">  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/index.php'?>">  
                <i class="fas fa-eye"></i>  
                <span>Ver Ordens de Serviço</span>  
            </a>  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/criar_os.php'?>">  
                <i class="fas fa-plus-circle"></i>  
                <span>Criar Ordem de Serviço</span>  
            </a>  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/tabela_de_emolumentos.php'?>">  
                <i class="fas fa-table"></i>  
                <span>Tabela de Emolumentos</span>  
            </a>  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/caixa/index.php'?>">  
                <i class="fas fa-university"></i>  
                <span>Controle de Caixa</span>  
            </a>  
        </div>  

        <button class="dropdown-btn">  
            <i class="fas fa-tasks"></i>  
            <span>Tarefas</span>  
            <i class="fas fa-chevron-down ml-auto"></i>  
        </button>  
        <div class="dropdown-container">  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/index.php'?>">  
                <i class="fas fa-eye"></i>  
                <span>Ver Tarefas</span>  
            </a>  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/criar-tarefa.php'?>">  
                <i class="fas fa-plus-circle"></i>  
                <span>Criar Tarefa</span>  
            </a>  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/categorias.php'?>">  
                <i class="fas fa-tags"></i>  
                <span>Categorias de Tarefas</span>  
            </a>  
        </div>  

        <button class="dropdown-btn">  
            <i class="fas fa-file-pdf"></i>  
            <span>Ofícios</span>  
            <i class="fas fa-chevron-down ml-auto"></i>  
        </button>  
        <div class="dropdown-container">  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/index.php'?>">  
                <i class="fas fa-eye"></i>  
                <span>Ver Ofícios</span>  
            </a>  
            <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/cadastrar-oficio.php'?>">  
                <i class="fas fa-plus-circle"></i>  
                <span>Criar Ofício</span>  
            </a>  
        </div>  

        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/suas_notas/index.php'?>">  
            <i class="fas fa-sticky-note"></i>  
            <span>Anotações</span>  
        </a>  

        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/relatorios/index.php'?>">  
            <i class="fas fa-chart-line"></i>  
            <span>Relatórios e Livros</span>  
        </a>  

        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/manuais/index.php'?>">  
            <i class="fas fa-video"></i>  
            <span>Vídeos Tutoriais</span>  
        </a>  

        <?php if ($nivel_de_acesso === 'administrador' || $tem_acesso_controle_contas): ?>  
            <button class="dropdown-btn">  
                <i class="fas fa-dollar-sign"></i>  
                <span>Contas a Pagar</span>  
                <i class="fas fa-chevron-down ml-auto"></i>  
            </button>  
            <div class="dropdown-container">  
                <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/contas_a_pagar/index.php'?>">  
                    <i class="fas fa-eye"></i>  
                    <span>Ver Contas a Pagar</span>  
                </a>  
                <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/contas_a_pagar/cadastrar.php'?>">  
                    <i class="fas fa-plus-circle"></i>  
                    <span>Cadastrar Contas a Pagar</span>  
                </a>  
            </div>  
        <?php endif; ?>  

        <?php if ($nivel_de_acesso === 'administrador' || $tem_acesso_cadastro_funcionarios): ?>  
            <button class="dropdown-btn">  
                <i class="fas fa-cog"></i>  
                <span>Administração</span>  
                <i class="fas fa-chevron-down ml-auto"></i>  
            </button>  
            <div class="dropdown-container">  
                <?php if ($nivel_de_acesso === 'administrador'): ?>  
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-serventia.php'?>">  
                        <i class="fas fa-building"></i>  
                        <span>Dados da Serventia</span>  
                    </a>  
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/configuracao_os.php'?>">  
                        <i class="fas fa-university"></i>  
                        <span>Configuração de Contas</span>  
                    </a>  
                <?php endif; ?>  
                <?php if ($nivel_de_acesso === 'administrador' || $tem_acesso_cadastro_funcionarios): ?>  
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-funcionario.php'?>">  
                        <i class="fas fa-users"></i>  
                        <span>Cadastro de Funcionários</span>  
                    </a>  
                <?php endif; ?>  
            </div>  
        <?php endif; ?>  
    </div>  

    <div id="system-name">  
        <button class="nav-toggle" onclick="toggleNav()">  
            <i class="fas fa-bars"></i>  
        </button>  
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>" style="text-decoration: none; margin-left: 20px;">  
            <img id="logo-img" src="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/' . ($mode === 'dark-mode' ? 'atlas2.png' : 'atlas1.png')?>" alt="Atlas" style="height: 40px;">  
        </a>  
    </div>

    <div id="welcome-section">  
        <button class="mode-switch" onclick="toggleMode()">  
            <i class="fas <?php echo $mode === 'dark-mode' ? 'fa-moon' : 'fa-sun'; ?>"></i>  
        </button>   
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/atualizar-credenciais.php'?>" class="user-info">  
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>  
            <div class="user-details">  
                <span class="user-role"><?php echo strtoupper($_SESSION['username']); ?></span>  
                <span class="user-type"><?php echo htmlspecialchars($cargo_funcionario); ?></span>  
            </div>  
        </a>  
        <a href="logout.php" class="logout-button">  
            <i class="fas fa-sign-out-alt"></i>  
            Sair  
        </a>  
    </div>

    <script>  
        function toggleNav() {  
            const sidebar = document.getElementById("mySidebar");  
            const overlay = document.querySelector(".sidebar-overlay");  
            const body = document.body;  
            
            sidebar.classList.toggle("active");  
            overlay.classList.toggle("active");  
            
            // Previne o scroll do body quando o menu está aberto  
            if (sidebar.classList.contains("active")) {  
                body.style.overflow = "hidden";  
            } else {  
                body.style.overflow = "";  
            }  
        }  

        function toggleMode() {  
            const body = document.body;  
            const currentMode = body.classList.contains('dark-mode') ? 'dark-mode' : 'light-mode';  
            const newMode = currentMode === 'dark-mode' ? 'light-mode' : 'dark-mode';  
            const modeIcon = document.querySelector('.mode-switch i');  
            const logoImg = document.getElementById('logo-img');  
            
            // Atualiza o ícone do modo  
            modeIcon.className = `fas ${newMode === 'dark-mode' ? 'fa-moon' : 'fa-sun'}`;  

            // Atualiza o logo baseado no modo  
            const baseUrl = '<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/'?>';  
            logoImg.src = baseUrl + (newMode === 'dark-mode' ? 'atlas1.png' : 'atlas2.png');  

            $.ajax({  
                url: '<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/save_mode.php'?>',  
                method: 'POST',  
                data: { mode: newMode },  
                success: function(response) {  
                    body.classList.remove('light-mode', 'dark-mode');  
                    body.classList.add(newMode);  
                }  
            });  
        } 

        document.addEventListener('DOMContentLoaded', function() {  
            // Detecta se é um dispositivo móvel  
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);  
            
            const dropdowns = document.getElementsByClassName("dropdown-btn");  
            
            for (let i = 0; i < dropdowns.length; i++) {  
                const dropdownBtn = dropdowns[i];  
                const dropdownContent = dropdownBtn.nextElementSibling;  
                const chevronIcon = dropdownBtn.querySelector('.fa-chevron-down');  

                if (isMobile) {  
                    // Comportamento para dispositivos móveis (touch)  
                    dropdownBtn.addEventListener("click", function(e) {  
                        e.preventDefault();  
                        e.stopPropagation();  

                        // Fecha outros dropdowns abertos  
                        const allDropdowns = document.getElementsByClassName("dropdown-container");  
                        const allButtons = document.getElementsByClassName("dropdown-btn");  
                        
                        for (let j = 0; j < allDropdowns.length; j++) {  
                            if (allDropdowns[j] !== dropdownContent) {  
                                allDropdowns[j].style.display = "none";  
                                allButtons[j].classList.remove("active");  
                                const otherIcon = allButtons[j].querySelector('.fa-chevron-down');  
                                if (otherIcon) {  
                                    otherIcon.style.transform = 'rotate(0deg)';  
                                }  
                            }  
                        }  

                        // Toggle do dropdown atual  
                        const isOpen = dropdownContent.style.display === "block";  
                        dropdownContent.style.display = isOpen ? "none" : "block";  
                        this.classList.toggle("active");  
                        
                        if (chevronIcon) {  
                            chevronIcon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';  
                        }  
                    });  
                } else {  
                    // Comportamento para desktop (hover)  
                    dropdownBtn.addEventListener("mouseenter", function() {  
                        dropdownContent.style.display = "block";  
                        this.classList.add("active");  
                        if (chevronIcon) {  
                            chevronIcon.style.transform = 'rotate(180deg)';  
                        }  
                    });  

                    dropdownBtn.addEventListener("mouseleave", function(e) {  
                        if (!dropdownContent.contains(e.relatedTarget)) {  
                            setTimeout(() => {  
                                if (!dropdownContent.matches(':hover')) {  
                                    dropdownContent.style.display = "none";  
                                    this.classList.remove("active");  
                                    if (chevronIcon) {  
                                        chevronIcon.style.transform = 'rotate(0deg)';  
                                    }  
                                }  
                            }, 100);  
                        }  
                    });  

                    dropdownContent.addEventListener("mouseleave", function() {  
                        dropdownContent.style.display = "none";  
                        dropdownBtn.classList.remove("active");  
                        if (chevronIcon) {  
                            chevronIcon.style.transform = 'rotate(0deg)';  
                        }  
                    });  
                }  
            }  

            // Fecha o menu ao clicar em um link da sidebar (em mobile)  
            if (isMobile) {  
                const sidebarLinks = document.querySelectorAll('.sidebar a, .dropdown-container a');  
                sidebarLinks.forEach(link => {  
                    link.addEventListener('click', () => {  
                        const sidebar = document.getElementById("mySidebar");  
                        if (sidebar.classList.contains("active")) {  
                            toggleNav();  
                        }  
                    });  
                });  

                // Fecha dropdowns ao tocar fora em dispositivos móveis  
                document.addEventListener('click', function(e) {  
                    if (!e.target.matches('.dropdown-btn') &&   
                        !e.target.matches('.dropdown-btn *') &&   
                        !e.target.matches('.dropdown-container *')) {  
                        
                        const dropdowns = document.getElementsByClassName("dropdown-container");  
                        const buttons = document.getElementsByClassName("dropdown-btn");  
                        
                        for (let i = 0; i < dropdowns.length; i++) {  
                            dropdowns[i].style.display = "none";  
                            buttons[i].classList.remove("active");  
                            const icon = buttons[i].querySelector('.fa-chevron-down');  
                            if (icon) {  
                                icon.style.transform = 'rotate(0deg)';  
                            }  
                        }  
                    }  
                });  
            }  

            // Fecha o menu ao redimensionar a tela para desktop  
            window.addEventListener('resize', () => {  
                const sidebar = document.getElementById("mySidebar");  
                const overlay = document.querySelector(".sidebar-overlay");  
                if (window.innerWidth > 768 && sidebar.classList.contains("active")) {  
                    sidebar.classList.remove("active");  
                    overlay.classList.remove("active");  
                    document.body.style.overflow = "";  
                }  
            });  

            // Carregar modo inicial  
            $.ajax({  
                url: '<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/load_mode.php'?>',  
                method: 'GET',  
                success: function(mode) {  
                    const body = document.body;  
                    const modeIcon = document.querySelector('.mode-switch i');  
                    const logoImg = document.getElementById('logo-img');  
                    const baseUrl = '<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/'?>';  

                    body.classList.remove('light-mode', 'dark-mode');  
                    body.classList.add(mode);  
                    
                    modeIcon.className = `fas ${mode === 'dark-mode' ? 'fa-moon' : 'fa-sun'}`;  
                    logoImg.src = baseUrl + (mode === 'dark-mode' ? 'atlas1.png' : 'atlas2.png');  
                }  
            });     
        });
    </script>  
</body>  
</html>