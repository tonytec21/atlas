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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">  
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script> 
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <?php include(__DIR__ . '/style/style.php'); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Resetar estilos do menu antigo */
        .sidebar, .sidebar-overlay {
            display: none !important;
        }

        body {
            margin: 0;
            padding: 0;
            padding-bottom: 65px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Header Superior Ultra Moderno e Fino */
        #system-name {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            display: flex;
            align-items: center;
            padding: 0 24px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.04);
            z-index: 998;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.dark-mode #system-name {
            background: rgba(26, 26, 26, 0.95);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        #system-name .nav-toggle {
            display: none;
        }

        #system-name img {
            height: 32px;
            transition: transform 0.3s ease;
        }

        #system-name img:hover {
            transform: scale(1.05);
        }

        #welcome-section {
            position: fixed;
            top: 0;
            right: 0;
            height: 56px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 24px;
            z-index: 999;
        }

        .mode-switch {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 3px 12px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .mode-switch {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #1a1a1a;
            box-shadow: 0 3px 12px rgba(251, 191, 36, 0.35);
        }

        .mode-switch::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .mode-switch:hover::before {
            opacity: 1;
        }

        .mode-switch:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        body.dark-mode .mode-switch:hover {
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.45);
        }

        .mode-switch:active {
            transform: scale(0.95);
        }

        .mode-switch i {
            font-size: 16px;
            transition: all 0.4s ease;
        }

        .mode-switch:hover i {
            transform: rotate(180deg);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 12px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: transparent;
        }

        .user-info:hover {
            background: rgba(102, 126, 234, 0.08);
            transform: translateY(-2px);
        }

        body.dark-mode .user-info:hover {
            background: rgba(139, 157, 255, 0.12);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 3px 12px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .user-info:hover .user-avatar {
            transform: scale(1.1);
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .user-role {
            font-size: 13px;
            font-weight: 600;
            color: #1a1a1a;
            letter-spacing: -0.02em;
        }

        body.dark-mode .user-role {
            color: #ffffff;
        }

        .user-type {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
        }

        body.dark-mode .user-type {
            color: #94a3b8;
        }

        .logout-button {
            padding: 8px 16px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 3px 12px rgba(255, 107, 107, 0.3);
            position: relative;
            overflow: hidden;
        }

        .logout-button::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .logout-button:hover::before {
            opacity: 1;
        }

        .logout-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        }

        .logout-button:active {
            transform: translateY(0);
        }

        /* Menu Inferior Ultra Elegante e Fino */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px) saturate(180%);
            box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.06);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2px;
            padding: 6px 16px 12px;
            z-index: 1000;
            border-radius: 24px 24px 0 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            max-width: 100%;
        }

        body.dark-mode .bottom-nav {
            background: rgba(26, 26, 26, 0.98);
            box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.4);
        }

        @media (min-width: 769px) {
            .bottom-nav {
                left: 50%;
                transform: translateX(-50%);
                max-width: 600px;
                border-radius: 20px;
                bottom: 0px;
                padding: 4px 12px;
            }
        }

        .nav-item {
            flex: 1;
            max-width: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #64748b;
            padding: 8px 6px;
            position: relative;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border-radius: 16px;
            background: transparent;
        }

        body.dark-mode .nav-item {
            color: #94a3b8;
        }

        .nav-item.center {
            flex: 1.4;
            max-width: 95px;
            margin: -28px 6px 0;
        }

        .nav-item-icon {
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            background: transparent;
        }

        .nav-item.center .nav-item-icon {
            width: 46px;
            height: 46px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.35);
            border-radius: 18px;
        }

        body.dark-mode .nav-item.center .nav-item-icon {
            background: linear-gradient(135deg, #8b9dff 0%, #9b7fd6 100%);
            box-shadow: 0 6px 20px rgba(139, 157, 255, 0.4);
        }

        .nav-item i {
            font-size: 18px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }

        .nav-item.center i {
            font-size: 24px;
            color: #ffffff;
        }

        .nav-item span {
            font-size: 10px;
            font-weight: 600;
            margin-top: 4px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: -0.01em;
        }

        .nav-item.center span {
            font-size: 11px;
            font-weight: 700;
            margin-top: 6px;
        }

        /* Hover Effects */
        .nav-item:not(.center):hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.08);
            transform: translateY(-3px);
        }

        body.dark-mode .nav-item:not(.center):hover {
            color: #8b9dff;
            background: rgba(139, 157, 255, 0.12);
        }

        .nav-item:not(.center):hover .nav-item-icon {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            transform: scale(1.1);
        }

        body.dark-mode .nav-item:not(.center):hover .nav-item-icon {
            background: linear-gradient(135deg, rgba(139, 157, 255, 0.2) 0%, rgba(155, 127, 214, 0.2) 100%);
        }

        .nav-item:not(.center):hover i {
            transform: scale(1.15);
        }

        .nav-item.center:hover .nav-item-icon {
            transform: scale(1.06);
            box-shadow: 0 8px 28px rgba(102, 126, 234, 0.45);
        }

        body.dark-mode .nav-item.center:hover .nav-item-icon {
            box-shadow: 0 8px 28px rgba(139, 157, 255, 0.5);
        }

        /* Active State */
        .nav-item.active:not(.center) {
            color: #667eea;
        }

        body.dark-mode .nav-item.active:not(.center) {
            color: #8b9dff;
        }

        .nav-item.active:not(.center) .nav-item-icon {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
        }

        body.dark-mode .nav-item.active:not(.center) .nav-item-icon {
            background: linear-gradient(135deg, rgba(139, 157, 255, 0.2) 0%, rgba(155, 127, 214, 0.2) 100%);
        }

        .nav-item.active i {
            transform: scale(1.08);
        }

        /* Active Indicator */
        .nav-item.active:not(.center)::before {
            content: '';
            position: absolute;
            top: 6px;
            width: 4px;
            height: 4px;
            background: #667eea;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        body.dark-mode .nav-item.active:not(.center)::before {
            background: #8b9dff;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.5);
                opacity: 0.5;
            }
        }

        /* Badge para notificações */
        .nav-badge {
            position: absolute;
            top: 4px;
            right: calc(50% - 20px);
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(255, 107, 107, 0.4);
            animation: bounceIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Modal Ultra Elegante */
        .menu-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1001;
            backdrop-filter: blur(8px);
            animation: fadeIn 0.3s ease;
        }

        .menu-modal.active {
            display: block;
        }

        .menu-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px) saturate(180%);
            border-radius: 24px 24px 0 0;
            max-height: 75vh;
            overflow-y: auto;
            padding: 24px 20px 100px;
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.dark-mode .menu-content {
            background: rgba(26, 26, 26, 0.98);
        }

        @keyframes slideUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        }

        body.dark-mode .menu-header {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .menu-title {
            font-size: 22px;
            font-weight: 800;
            color: #1a1a1a;
            letter-spacing: -0.03em;
        }

        body.dark-mode .menu-title {
            color: #ffffff;
        }

        .close-menu {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            border: none;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 16px;
        }

        body.dark-mode .close-menu {
            background: rgba(139, 157, 255, 0.15);
            color: #8b9dff;
        }

        .close-menu:hover {
            transform: rotate(90deg) scale(1.1);
            background: rgba(102, 126, 234, 0.2);
        }

        body.dark-mode .close-menu:hover {
            background: rgba(139, 157, 255, 0.25);
        }

        .menu-section {
            margin-bottom: 28px;
        }

        .section-title {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 12px;
            padding-left: 4px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: rgba(248, 250, 252, 0.8);
            border-radius: 14px;
            text-decoration: none;
            color: #334155;
            margin-bottom: 8px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .menu-link {
            background: rgba(42, 42, 42, 0.8);
            color: #e2e8f0;
        }

        .menu-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleY(0);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .menu-link:hover::before {
            transform: scaleY(1);
        }

        .menu-link:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.12) 0%, rgba(118, 75, 162, 0.08) 100%);
            color: #667eea;
            transform: translateX(6px);
            box-shadow: 0 3px 12px rgba(102, 126, 234, 0.15);
        }

        body.dark-mode .menu-link:hover {
            background: linear-gradient(135deg, rgba(139, 157, 255, 0.15) 0%, rgba(155, 127, 214, 0.1) 100%);
            color: #8b9dff;
            box-shadow: 0 3px 12px rgba(139, 157, 255, 0.2);
        }

        .menu-link i {
            font-size: 18px;
            width: 24px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .menu-link:hover i {
            transform: scale(1.2);
        }

        .menu-link span {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .submenu-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            background: rgba(248, 250, 252, 0.8);
            border-radius: 14px;
            border: none;
            color: #334155;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .submenu-toggle {
            background: rgba(42, 42, 42, 0.8);
            color: #e2e8f0;
        }

        .submenu-toggle::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleY(0);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .submenu-toggle:hover::before,
        .submenu-toggle.active::before {
            transform: scaleY(1);
        }

        .submenu-toggle:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.12) 0%, rgba(118, 75, 162, 0.08) 100%);
            color: #667eea;
            box-shadow: 0 3px 12px rgba(102, 126, 234, 0.15);
        }

        body.dark-mode .submenu-toggle:hover {
            background: linear-gradient(135deg, rgba(139, 157, 255, 0.15) 0%, rgba(155, 127, 214, 0.1) 100%);
            color: #8b9dff;
            box-shadow: 0 3px 12px rgba(139, 157, 255, 0.2);
        }

        .submenu-toggle.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.12) 0%, rgba(118, 75, 162, 0.08) 100%);
            color: #667eea;
        }

        body.dark-mode .submenu-toggle.active {
            background: linear-gradient(135deg, rgba(139, 157, 255, 0.15) 0%, rgba(155, 127, 214, 0.1) 100%);
            color: #8b9dff;
        }

        .submenu-toggle-content {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .submenu-toggle i:first-child {
            font-size: 18px;
            width: 24px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .submenu-toggle:hover i:first-child {
            transform: scale(1.2);
        }

        .submenu-toggle span {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .submenu-toggle .fa-chevron-down {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 13px;
        }

        .submenu-toggle.active .fa-chevron-down {
            transform: rotate(180deg);
        }

        .submenu {
            display: none;
            padding-left: 38px;
            margin-bottom: 8px;
            margin-top: -2px;
        }

        .submenu.active {
            display: block;
            animation: fadeInDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .submenu .menu-link {
            background: transparent;
            border-left: 2px solid rgba(102, 126, 234, 0.15);
            border-radius: 0 12px 12px 0;
            padding: 11px 14px;
            margin-bottom: 6px;
        }

        body.dark-mode .submenu .menu-link {
            border-left-color: rgba(139, 157, 255, 0.2);
        }

        .submenu .menu-link:hover {
            background: rgba(102, 126, 234, 0.06);
            border-left-color: #667eea;
            transform: translateX(5px);
            box-shadow: none;
        }

        body.dark-mode .submenu .menu-link:hover {
            background: rgba(139, 157, 255, 0.08);
            border-left-color: #8b9dff;
        }

        .submenu .menu-link::before {
            display: none;
        }

        /* Scrollbar Ultra Elegante */
        .menu-content::-webkit-scrollbar {
            width: 6px;
        }

        .menu-content::-webkit-scrollbar-track {
            background: transparent;
        }

        .menu-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.3) 0%, rgba(118, 75, 162, 0.3) 100%);
            border-radius: 10px;
            transition: background 0.3s ease;
        }

        .menu-content::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.5) 0%, rgba(118, 75, 162, 0.5) 100%);
        }

        body.dark-mode .menu-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, rgba(139, 157, 255, 0.3) 0%, rgba(155, 127, 214, 0.3) 100%);
        }

        body.dark-mode .menu-content::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, rgba(139, 157, 255, 0.5) 0%, rgba(155, 127, 214, 0.5) 100%);
        }

        /* Responsividade Perfeita */
        @media (max-width: 768px) {
            body {
                padding-bottom: 68px;
            }

            #system-name {
                padding: 0 16px;
                height: 52px;
            }

            #system-name img {
                height: 28px;
            }

            #welcome-section {
                padding: 0 12px;
                height: 52px;
                gap: 8px;
            }

            .user-details {
                display: none;
            }

            .mode-switch {
                width: 36px;
                height: 36px;
            }

            .logout-button span {
                display: none;
            }

            .logout-button {
                padding: 8px;
                width: 36px;
                height: 36px;
                justify-content: center;
            }

            .bottom-nav {
                padding: 5px 10px 10px;
                gap: 1px;
            }

            .nav-item {
                max-width: 68px;
                padding: 7px 5px;
            }

            .nav-item.center {
                max-width: 76px;
                margin-top: -26px;
            }

            .nav-item-icon {
                width: 38px;
                height: 38px;
            }

            .nav-item.center .nav-item-icon {
                width: 52px;
                height: 52px;
            }

            .nav-item i {
                font-size: 17px;
            }

            .nav-item.center i {
                font-size: 22px;
            }

            .nav-item span {
                font-size: 9px;
            }

            .menu-content {
                padding: 20px 16px 90px;
            }
        }

        @media (max-width: 480px) {
            .nav-item span {
                font-size: 8.5px;
            }

            .nav-item.center span {
                font-size: 9.5px;
            }

            .menu-title {
                font-size: 19px;
            }
        }

        /* Remover sublinhado de todos os links */
        a {
            text-decoration: none !important;
        }

        .atlas-toast {
        position: fixed;
        right: 18px;
        bottom: 18px;
        max-width: 360px;
        z-index: 2000;
        background: rgba(33, 38, 45, 0.96);
        color: #fff;
        border-radius: 14px;
        padding: 14px 16px;
        box-shadow: 0 10px 32px rgba(0,0,0,0.3);
        border: 1px solid rgba(255,255,255,0.08);
        backdrop-filter: blur(12px) saturate(160%);
        display: none;
        animation: atlasToastIn .28s ease-out both;
      }
      body.light-mode .atlas-toast {
        background: rgba(255, 255, 255, 0.98);
        color: #111827;
        border: 1px solid rgba(0,0,0,0.06);
      }
      .atlas-toast .atlas-toast-title {
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
      }
      .atlas-toast .atlas-toast-body {
        font-size: 13px;
        line-height: 1.45;
      }
      .atlas-toast .atlas-toast-actions {
        margin-top: 10px;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
      }
      .atlas-toast .btn-toast {
        border: none;
        border-radius: 10px;
        padding: 8px 12px;
        font-weight: 700;
        cursor: pointer;
      }
      .btn-toast-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
      }
      .btn-toast-muted {
        background: rgba(0,0,0,0.06);
        color: inherit;
      }
      body.dark-mode .btn-toast-muted {
        background: rgba(255,255,255,0.08);
      }
      @keyframes atlasToastIn {
        from { transform: translateY(12px); opacity: 0; }
        to   { transform: translateY(0);    opacity: 1; }
      }
    </style>
</head>

<body class="<?php echo $mode; ?>">  
    <!-- Header Superior -->
    <div id="system-name">  
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>">  
            <img id="logo-img" src="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/' . ($mode === 'dark-mode' ? 'atlas_logo_2025_2.png' : 'atlas_logo_2025_1.png')?>" alt="Atlas">  
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
            <span>Sair</span>  
        </a>  
    </div>

    <!-- Menu Inferior Ultra Elegante -->
    <nav class="bottom-nav">
        
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/index.php'?>" class="nav-item">
            <div class="nav-item-icon">
                <i class="fas fa-file-invoice"></i>
            </div>
            <span>O.S</span>
        </a>
        
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/caixa/index.php'?>" class="nav-item">
            <div class="nav-item-icon">
                <i class="fas fa-cash-register"></i>
            </div>
            <span>Caixa</span>
        </a>
        
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/index.php'?>" class="nav-item center active">
            <div class="nav-item-icon">
                <i class="fas fa-home"></i>
            </div>
            <span>Início</span>
        </a>
        
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/index.php'?>" class="nav-item">
            <div class="nav-item-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <span>Tarefas</span>
        </a>
        
        <div class="nav-item" onclick="toggleMenu()">
            <div class="nav-item-icon">
                <i class="fas fa-grip-horizontal"></i>
            </div>
            <span>Menu</span>
        </div>
    </nav>

    <!-- Modal Menu Completo -->
    <div class="menu-modal" id="menuModal" onclick="closeMenuOnOverlay(event)">
        <div class="menu-content">
            <div class="menu-header">
                <h2 class="menu-title">Menu Completo</h2>
                <button class="close-menu" onclick="toggleMenu()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="menu-section">
                <div class="section-title">Principal</div>

                <button class="submenu-toggle" onclick="toggleSubmenu(this)">
                    <div class="submenu-toggle-content">
                        <i class="fas fa-certificate"></i>
                        <span>Pedidos de Certidão</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="submenu">
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/pedidos_certidao/index.php'?>" class="menu-link">
                        <i class="fas fa-eye"></i>
                        <span>Ver Pedidos</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/pedidos_certidao/novo_pedido.php'?>" class="menu-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Novo Pedido</span>
                    </a>
                </div>

                <button class="submenu-toggle" onclick="toggleSubmenu(this)">
                    <div class="submenu-toggle-content">
                        <i class="fas fa-file-invoice"></i>
                        <span>Ordens de Serviço</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="submenu">
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/index.php'?>" class="menu-link">
                        <i class="fas fa-eye"></i>
                        <span>Ver Ordens de Serviço</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/criar_os.php'?>" class="menu-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Criar Ordem de Serviço</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/tabela_de_emolumentos.php'?>" class="menu-link">
                        <i class="fas fa-table"></i>
                        <span>Tabela de Emolumentos</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/caixa/index.php'?>" class="menu-link">
                        <i class="fas fa-cash-register"></i>
                        <span>Controle de Caixa</span>
                    </a>
                </div>

                <button class="submenu-toggle" onclick="toggleSubmenu(this)">
                    <div class="submenu-toggle-content">
                        <i class="fas fa-check-circle"></i>
                        <span>Tarefas</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="submenu">
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/index.php'?>" class="menu-link">
                        <i class="fas fa-eye"></i>
                        <span>Ver Tarefas</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/criar-tarefa.php'?>" class="menu-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Criar Tarefa</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas/categorias.php'?>" class="menu-link">
                        <i class="fas fa-tags"></i>
                        <span>Categorias de Tarefas</span>
                    </a>
                </div>

                <button class="submenu-toggle" onclick="toggleSubmenu(this)">
                    <div class="submenu-toggle-content">
                        <i class="fas fa-folder-open"></i>
                        <span>Arquivamento</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="submenu">
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/index.php'?>" class="menu-link">
                        <i class="fas fa-eye"></i>
                        <span>Ver Arquivamentos</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/cadastro.php'?>" class="menu-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Criar Arquivamento</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/arquivamento/categorias.php'?>" class="menu-link">
                        <i class="fas fa-tags"></i>
                        <span>Categorias</span>
                    </a>
                </div>

                <button class="submenu-toggle" onclick="toggleSubmenu(this)">
                    <div class="submenu-toggle-content">
                        <i class="fas fa-file-alt"></i>
                        <span>Ofícios</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="submenu">
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/index.php'?>" class="menu-link">
                        <i class="fas fa-eye"></i>
                        <span>Ver Ofícios</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/oficios/cadastrar-oficio.php'?>" class="menu-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Criar Ofício</span>
                    </a>
                </div>

                <button class="submenu-toggle" onclick="toggleSubmenu(this)">
                    <div class="submenu-toggle-content">
                        <i class="fas fa-file-alt"></i>
                        <span>Nota Devolutiva</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="submenu">
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/nota_devolutiva/index.php'?>" class="menu-link">
                        <i class="fas fa-eye"></i>
                        <span>Ver Notas Devolutivas</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/nota_devolutiva/cadastrar-nota-devolutiva.php'?>" class="menu-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Criar Nota Devolutiva</span>
                    </a>
                </div>
            </div>

            <div class="menu-section">
                <div class="section-title">Ferramentas</div>

                <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/provimentos/index.php'?>" class="menu-link">
                    <i class="fas fa-balance-scale"></i>
                    <span>Provimentos e Resoluções</span>
                </a>
                
                <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/suas_notas/index.php'?>" class="menu-link">
                    <i class="fas fa-sticky-note"></i>
                    <span>Anotações</span>
                </a>

                <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/relatorios/index.php'?>" class="menu-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Relatórios e Livros</span>
                </a>

                <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/manuais/index.php'?>" class="menu-link">
                    <i class="fas fa-play-circle"></i>
                    <span>Vídeos Tutoriais</span>
                </a>
            </div>

            <?php if ($nivel_de_acesso === 'administrador' || $tem_acesso_controle_contas): ?>
            <div class="menu-section">
                <div class="section-title">Financeiro</div>
                
                <button class="submenu-toggle" onclick="toggleSubmenu(this)">
                    <div class="submenu-toggle-content">
                        <i class="fas fa-wallet"></i>
                        <span>Contas a Pagar</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="submenu">
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/contas_a_pagar/index.php'?>" class="menu-link">
                        <i class="fas fa-eye"></i>
                        <span>Ver Contas a Pagar</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/contas_a_pagar/cadastrar.php'?>" class="menu-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Cadastrar Contas a Pagar</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($nivel_de_acesso === 'administrador' || $tem_acesso_cadastro_funcionarios): ?>
            <div class="menu-section">
                <div class="section-title">Administração</div>
                
                <button class="submenu-toggle" onclick="toggleSubmenu(this)">
                    <div class="submenu-toggle-content">
                        <i class="fas fa-cog"></i>
                        <span>Configurações</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="submenu">
                    <?php if ($nivel_de_acesso === 'administrador'): ?>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-serventia.php'?>" class="menu-link">
                        <i class="fas fa-building"></i>
                        <span>Dados da Serventia</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/os/configuracao_os.php'?>" class="menu-link">
                        <i class="fas fa-university"></i>
                        <span>Configuração de Contas</span>
                    </a>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/tarefas_recorrentes.php'?>" class="menu-link">
                        <i class="fas fa-redo-alt"></i>
                        <span>Tarefas Recorrentes</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($nivel_de_acesso === 'administrador' || $tem_acesso_cadastro_funcionarios): ?>
                    <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/cadastro-funcionario.php'?>" class="menu-link">
                        <i class="fas fa-user-plus"></i>
                        <span>Cadastro de Funcionários</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>  
        function toggleMode() {  
            const body = document.body;  
            const currentMode = body.classList.contains('dark-mode') ? 'dark-mode' : 'light-mode';  
            const newMode = currentMode === 'dark-mode' ? 'light-mode' : 'dark-mode';  
            const modeIcon = document.querySelector('.mode-switch i');  
            const logoImg = document.getElementById('logo-img');  
            
            modeIcon.className = `fas ${newMode === 'dark-mode' ? 'fa-moon' : 'fa-sun'}`;  
            
            const baseUrl = '<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/style/img/'?>';  
            logoImg.src = baseUrl + (newMode === 'dark-mode' ? 'atlas_logo_2025_2.png' : 'atlas_logo_2025_1.png');  
            
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

        function toggleMenu() {
            const modal = document.getElementById('menuModal');
            const isActive = modal.classList.contains('active');
            
            if (isActive) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeMenuOnOverlay(event) {
            if (event.target === event.currentTarget) {
                toggleMenu();
            }
        }

        function toggleSubmenu(button) {
            const submenu = button.nextElementSibling;
            const isActive = button.classList.contains('active');
            
            // Fecha todos os submenus
            document.querySelectorAll('.submenu-toggle').forEach(btn => {
                if (btn !== button) {
                    btn.classList.remove('active');
                    btn.nextElementSibling.classList.remove('active');
                }
            });
            
            // Toggle do submenu clicado
            if (!isActive) {
                button.classList.add('active');
                submenu.classList.add('active');
            } else {
                button.classList.remove('active');
                submenu.classList.remove('active');
            }
        }

        // Marca o item ativo baseado na URL atual
               // Marca o item ativo baseado na URL atual
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const navItems = document.querySelectorAll('.bottom-nav .nav-item');
            
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && currentPath.includes(href.split('/').filter(p => p).pop())) {
                    navItems.forEach(i => i.classList.remove('active'));
                    item.classList.add('active');
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
                    logoImg.src = baseUrl + (mode === 'dark-mode' ? 'atlas_logo_2025_2.png' : 'atlas_logo_2025_1.png');  
                }  
            });

            // Fecha o menu ao pressionar ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('menuModal');
                    if (modal.classList.contains('active')) {
                        toggleMenu();
                    }
                }
            });

            // ===================== NOTIFICAÇÕES EM "TEMPO REAL" (polling) =====================
            (function initTaskNotify(){
              const API_URL = '<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/pedidos_certidao/tarefas.php'?>';
              const CURRENT_USER = '<?=addslashes($_SESSION['username'] ?? '')?>';
              if (!CURRENT_USER) return; // sem usuário logado, não notifica

              const STORAGE_KEY = 'atlas_last_task_seen_' + CURRENT_USER;
              let lastSeen = localStorage.getItem(STORAGE_KEY) || ''; // ISO ou vazio

              const $toast = $('#atlas-toast');
              const $toastBody = $('#atlas-toast .atlas-toast-body');

              $('#atlas-toast-dismiss').on('click', function(){
                $toast.fadeOut(150);
              });

              function humanizeDate(s) {
                try {
                  const d = new Date(s.replace(' ', 'T'));
                  return d.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
                } catch(e) { return s; }
              }

              function checkNewAssignments() {
                $.ajax({
                  url: API_URL,
                  data: { action: 'notify_new_assignments', since: lastSeen },
                  dataType: 'json',
                  cache: false,
                  success: function(r){
                    if (!r || !r.success) return;
                    if ((r.count || 0) > 0) {
                      // Monta corpo do toast
                      const items = (r.items || []).map(it => {
                        const proto = it.protocolo ? `<strong>${it.protocolo}</strong>` : `#${it.pedido_id}`;
                        const when  = humanizeDate(it.criado_em || '');
                        return `<div style="margin-bottom:6px;">
                                  <i class="fas fa-circle" style="font-size:7px; margin-right:6px;"></i>
                                  Pedido ${proto} — <span style="opacity:0.8">${when}</span>
                                </div>`;
                      }).join('') || 'Você recebeu novas tarefas.';

                      $toastBody.html(items);
                      $toast.stop(true, true).fadeIn(150);

                      // Atualiza ponteiro para não repetir
                      if (r.latest_ts) {
                        lastSeen = r.latest_ts;
                        localStorage.setItem(STORAGE_KEY, lastSeen);
                      }
                    } else {
                      // Nenhuma nova; mantém lastSeen como está
                    }
                  }
                });
              }

              // Primeira rodada rápida ao entrar
              setTimeout(checkNewAssignments, 1500);
              // Polling a cada 15s (ajuste se quiser)
              setInterval(checkNewAssignments, 15000);
            })();
            // ======================================================================
        });

    </script>  

    
    
    <!-- Container de Toast (injetado dinamicamente) -->
    <div id="atlas-toast" class="atlas-toast" role="alert" aria-live="polite" aria-atomic="true">
      <div class="atlas-toast-title">
        <i class="fas fa-bell"></i>
        <span>Novas tarefas atribuídas</span>
      </div>
      <div class="atlas-toast-body"></div>
      <div class="atlas-toast-actions">
        <button type="button" class="btn-toast btn-toast-muted" id="atlas-toast-dismiss">Dispensar</button>
        <a href="<?='http://'.$_SERVER['HTTP_HOST'].'/atlas/pedidos_certidao/tarefas.php'?>" class="btn-toast btn-toast-primary">Ver Tarefas</a>
      </div>
    </div>
</body>  
</html>