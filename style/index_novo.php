<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  
include_once 'update_atlas/atualizacao.php';  
date_default_timezone_set('America/Sao_Paulo');  

// Verificar o nível de acesso do usuário logado  
$username = $_SESSION['username'];  
$connAtlas = new mysqli("localhost", "root", "", "atlas");  

// Consulta para verificar o nível de acesso e acesso adicional do usuário  
$sql = "SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ?";  
$stmt = $connAtlas->prepare($sql);  
$stmt->bind_param("s", $username);  
$stmt->execute();  
$result = $stmt->get_result();  
$user = $result->fetch_assoc();  
$nivel_de_acesso = $user['nivel_de_acesso'];  

// Verificar se o usuário tem acesso adicional a "Controle de Tarefas"  
$acesso_adicional = $user['acesso_adicional'];  
$acessos = array_map('trim', explode(',', $acesso_adicional));  
$tem_acesso_controle_tarefas = in_array('Controle de Tarefas', $acessos);  
?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Atlas - Central de Acesso</title>  
    <link rel="stylesheet" href="style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="style/css/style.css">  
    <link rel="icon" href="style/img/favicon.png" type="image/png">  
    <?php include(__DIR__ . '/style/style_index.php'); ?>  
    <style>  
        body {  
            background-color: #f8f9fa;  
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;  
        }  
        
        .main-container {  
            max-width: 1400px;  
            margin: 30px auto;  
            padding: 0 20px;  
        }  
        
        .page-title {  
            font-size: 28px;  
            font-weight: 600;  
            color: #212529;  
            margin-bottom: 10px;  
        }  
        
        .title-divider {  
            height: 4px;  
            width: 120px;  
            background-color: #0d6efd;  
            margin-bottom: 30px;  
            border-radius: 2px;  
        }  
        
        .search-container {  
            margin-bottom: 30px;  
        }  
        
        .search-box {  
            width: 100%;  
            max-width: 800px;  
            padding: 12px 20px;  
            border-radius: 100px;  
            border: 1px solid #e0e0e0;  
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);  
            font-size: 16px;  
            background-image: url('style/img/search-icon.svg');  
            background-repeat: no-repeat;  
            background-position: 15px center;  
            background-size: 16px;  
            padding-left: 45px;  
        }  
        
        .search-box:focus {  
            outline: none;  
            border-color: #0d6efd;  
            box-shadow: 0 2px 8px rgba(13,110,253,0.15);  
        }  
        
        #sortable-cards {  
            display: grid;  
            grid-template-columns: repeat(3, 1fr);  
            gap: 20px;  
        }  
        
        .module-card {  
            background: white;  
            border-radius: 12px;  
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);  
            padding: 20px;  
            transition: all 0.3s ease;  
            height: 100%;  
            display: flex;  
            flex-direction: column;  
            border: none;  
        }  
        
        .module-card:hover {  
            transform: translateY(-5px);  
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);  
        }  
        
        .card-header {  
            display: flex;  
            align-items: flex-start;  
            margin-bottom: 15px;  
        }  
        
        .card-badge {  
            font-size: 12px;  
            font-weight: 500;  
            padding: 5px 12px;  
            border-radius: 100px;  
            margin-right: 10px;  
        }  
        
        .card-icon {  
            width: 40px;  
            height: 40px;  
            border-radius: 8px;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            margin-left: auto;  
        }  
        
        .card-title {  
            font-size: 18px;  
            font-weight: 600;  
            margin-bottom: 10px;  
            color: #333;  
        }  
        
        .card-description {  
            font-size: 14px;  
            color: #6c757d;  
            margin-bottom: 20px;  
            flex-grow: 1;  
            line-height: 1.5;  
        }  
        
        .card-button {  
            border: none;  
            border-radius: 8px;  
            padding: 10px 15px;  
            font-weight: 500;  
            font-size: 14px;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            width: 100%;  
            cursor: pointer;  
            transition: all 0.2s;  
            color: white;  
        }  
        
        .card-button:hover {  
            transform: translateY(-2px);  
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);  
        }  
        
        .card-button i {  
            margin-right: 8px;  
        }  
        
        /* Estilos de categorias */  
        .badge-operacional {  
            background-color: #e1f5fe;  
            color: #0288d1;  
        }  
        
        .badge-financeiro {  
            background-color: #ffebee;  
            color: #e53935;  
        }  
        
        .badge-administrativo {  
            background-color: #e8f5e9;  
            color: #388e3c;  
        }  
        
        .badge-juridico {  
            background-color: #fff8e1;  
            color: #ffa000;  
        }  
        
        .badge-documental {  
            background-color: #f3e5f5;  
            color: #8e24aa;  
        }  
        
        /* PRESERVANDO CORES ORIGINAIS DOS ÍCONES */  
        .icon-arquivamento {  
            background-color: #4169E1;  
            color: white;  
        }  
        
        .icon-os {  
            background-color: #32CD32;  
            color: white;  
        }  
        
        .icon-caixa {  
            background-color: #4B0082;  
            color: white;  
        }  
        
        .icon-tarefas {  
            background-color: #6A5ACD;  
            color: white;  
        }  
        
        .icon-oficios {  
            background-color: #FF69B4;  
            color: white;  
        }  
        
        .icon-provimentos {  
            background-color: #FF8C00;  
            color: white;  
        }  
        
        .icon-guia {  
            background-color: #006400;  
            color: white;  
        }  
        
        .icon-contas {  
            background-color: #8B0000;  
            color: white;  
        }  
        
        .icon-manuais {  
            background-color: #008B8B;  
            color: white;  
        }  
        
        .icon-indexador {  
            background-color: #9932CC;  
            color: white;  
        }  
        
        .icon-reurb {  
            background-color: #BDB76B;  
            color: white;  
        }  
        
        .icon-anotacoes {  
            background-color: #2F4F4F;  
            color: white;  
        }  
        
        .icon-relatorios {  
            background-color: #708090;  
            color: white;  
        }  
        
        /* PRESERVANDO CORES ORIGINAIS DOS BOTÕES */  
        .btn-arquivamento {  
            background-color: #4169E1;  
        }  
        
        .btn-os {  
            background-color: #32CD32;  
        }  
        
        .btn-caixa {  
            background-color: #4B0082;  
        }  
        
        .btn-tarefas {  
            background-color: #6A5ACD;  
        }  
        
        .btn-oficios {  
            background-color: #FF69B4;  
        }  
        
        .btn-provimentos {  
            background-color: #FF8C00;  
        }  
        
        .btn-guia {  
            background-color: #006400;  
        }  
        
        .btn-contas {  
            background-color: #8B0000;  
        }  
        
        .btn-manuais {  
            background-color: #008B8B;  
        }  
        
        .btn-indexador {  
            background-color: #9932CC;  
        }  
        
        .btn-reurb {  
            background-color: #BDB76B;  
        }  
        
        .btn-anotacoes {  
            background-color: #2F4F4F;  
        }  
        
        .btn-relatorios {  
            background-color: #708090;  
        }  
        
        /* Estilos para o modal de tarefas */  
        .modal-content {  
            border-radius: 12px;  
            border: none;  
            overflow: hidden;  
        }  
        
        .modal-header {  
            background-color: #f8f9fa;  
            border-bottom: 1px solid #f0f0f0;  
            padding: 20px 25px;  
        }  
        
        .modal-title {  
            font-weight: 600;  
            font-size: 20px;  
        }  
        
        .modal-body {  
            padding: 25px;  
        }  
        
        .section-title {  
            font-size: 18px;  
            font-weight: 600;  
            margin-bottom: 15px;  
            display: flex;  
            align-items: center;  
        }  
        
        .section-title i {  
            margin-right: 8px;  
        }  
        
        .task-list-container {  
            background-color: #f9f9f9;  
            border-radius: 10px;  
            padding: 20px;  
            margin-bottom: 20px;  
        }  
        
        .status-label {  
            padding: 4px 10px;  
            border-radius: 4px;  
            font-size: 13px;  
            font-weight: 500;  
        }  
        
        /* Estilos para status */  
        .status-iniciada {  
            background-color: #e1f5fe;  
            color: #0288d1;  
        }  
        
        .status-em-espera {  
            background-color: #e8f5e9;  
            color: #388e3c;  
        }  
        
        .status-em-andamento {  
            background-color: #fff8e1;  
            color: #ffa000;  
        }  
        
        .status-concluida {  
            background-color: #e0f2f1;  
            color: #00897b;  
        }  
        
        .status-cancelada {  
            background-color: #ffebee;  
            color: #d32f2f;  
        }  
        
        .status-pendente {  
            background-color: #f3e5f5;  
            color: #7b1fa2;  
        }  
        
        .status-prestes-vencer {  
            background-color: #fff3e0;  
            color: #e65100;  
        }  
        
        .status-vencida {  
            background-color: #ffebee;  
            color: #b71c1c;  
        }  
        
        /* Estilos para as tabelas */  
        .table {  
            font-size: 14px;  
        }  
        
        .table th {  
            font-weight: 600;  
            color: #495057;  
        }  
        
        /* Placeholder para dragging */  
        .ui-state-highlight {  
            height: 240px;  
            background-color: #f8f9fa;  
            border: 2px dashed #dee2e6;  
            border-radius: 12px;  
        }  
        
        /* Melhorias de responsividade */  
        @media (max-width: 1200px) {  
            #sortable-cards {  
                grid-template-columns: repeat(3, 1fr);  
            }  
        }  
        
        @media (max-width: 992px) {  
            #sortable-cards {  
                grid-template-columns: repeat(2, 1fr);  
            }  
        }  
        
        @media (max-width: 768px) {  
            #sortable-cards {  
                grid-template-columns: 1fr;  
            }  
            
            .main-container {  
                padding: 0 15px;  
                margin: 20px auto;  
            }  
            
            .page-title {  
                font-size: 24px;  
            }  
            
            .card-title {  
                font-size: 16px;  
            }  
            
            .card-description {  
                font-size: 13px;  
            }  
        }  

        /* Modo escuro */  
        body.dark-mode {  
            background-color: #121212;  
        }  
        
        body.dark-mode .page-title {  
            color: #e0e0e0;  
        }  
        
        body.dark-mode .module-card {  
            background-color: #1e1e1e;  
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);  
        }  
        
        body.dark-mode .card-title {  
            color: #e0e0e0;  
        }  
        
        body.dark-mode .card-description {  
            color: #b0b0b0;  
        }  
        
        body.dark-mode .modal-content {  
            background-color: #1e1e1e;  
        }  
        
        body.dark-mode .modal-header {  
            background-color: #252525;  
            border-bottom: 1px solid #333;  
        }  
        
        body.dark-mode .task-list-container {  
            background-color: #252525;  
        }  
        
        body.dark-mode .table {  
            color: #e0e0e0;  
        }  
        
        body.dark-mode .search-box {  
            background-color: #252525;  
            border-color: #333;  
            color: #e0e0e0;  
        }  
    </style>  
</head>  
<body class="light-mode">  
<?php include(__DIR__ . '/menu.php'); ?>  

<div class="main-container">  
    <h1 class="page-title">Central de Acesso</h1>  
    <div class="title-divider"></div>  
    
    <div class="search-container">  
        <input type="text" class="search-box" id="searchModules" placeholder="Buscar módulos...">  
    </div>  
    
    <div id="sortable-cards">  
        <!-- Arquivamentos -->  
        <div class="module-card" id="card-arquivamento">  
            <div class="card-header">  
                <span class="card-badge badge-documental">Documental</span>  
                <div class="card-icon icon-arquivamento">  
                    <i class="fa fa-folder-open"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Arquivamentos</h3>  
            <p class="card-description">Controle de arquivamentos com rastreabilidade.</p>  
            <button class="card-button btn-arquivamento" onclick="window.location.href='arquivamento/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Ordens de Serviço -->  
        <div class="module-card" id="card-os">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-os">  
                    <i class="fa fa-money"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Ordens de Serviço</h3>  
            <p class="card-description">Gerencie ordens de serviço e valores.</p>  
            <button class="card-button btn-os" onclick="window.location.href='os/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Controle de Caixa -->  
        <div class="module-card" id="card-caixa">  
            <div class="card-header">  
                <span class="card-badge badge-financeiro">Financeiro</span>  
                <div class="card-icon icon-caixa">  
                    <i class="fa fa-university"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Controle de Caixa</h3>  
            <p class="card-description">Monitore entradas e saídas financeiras.</p>  
            <button class="card-button btn-caixa" onclick="window.location.href='caixa/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Tarefas -->  
        <div class="module-card" id="card-tarefas">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-tarefas">  
                    <i class="fa fa-clock-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Tarefas</h3>  
            <p class="card-description">Organize atividades com priorização e prazos.</p>  
            <button class="card-button btn-tarefas" onclick="window.location.href='tarefas/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Ofícios -->  
        <div class="module-card" id="card-oficios">  
            <div class="card-header">  
                <span class="card-badge badge-documental">Documental</span>  
                <div class="card-icon icon-oficios">  
                    <i class="fa fa-file-pdf-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Ofícios</h3>  
            <p class="card-description">Elabore e controle ofícios com numeração.</p>  
            <button class="card-button btn-oficios" onclick="window.location.href='oficios/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Provimentos e Resoluções -->  
        <div class="module-card" id="card-provimento">  
            <div class="card-header">  
                <span class="card-badge badge-juridico">Jurídico</span>  
                <div class="card-icon icon-provimentos">  
                    <i class="fa fa-balance-scale"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Provimentos</h3>  
            <p class="card-description">Acesse normas e provimentos aplicáveis.</p>  
            <button class="card-button btn-provimentos" onclick="window.location.href='provimentos/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Guia de Recebimento -->  
        <div class="module-card" id="card-guia">  
            <div class="card-header">  
                <span class="card-badge badge-financeiro">Financeiro</span>  
                <div class="card-icon icon-guia">  
                    <i class="fa fa-file-text"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Guia de Recebimento</h3>  
            <p class="card-description">Emita e controle guias de recebimento.</p>  
            <button class="card-button btn-guia" onclick="window.location.href='guia_de_recebimento/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Controle de Contas a Pagar -->  
        <div class="module-card" id="card-contas">  
            <div class="card-header">  
                <span class="card-badge badge-financeiro">Financeiro</span>  
                <div class="card-icon icon-contas">  
                    <i class="fa fa-usd"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Contas a Pagar</h3>  
            <p class="card-description">Gerencie contas e controle vencimentos.</p>  
            <button class="card-button btn-contas" onclick="window.location.href='contas_a_pagar/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Vídeos Tutoriais -->  
        <div class="module-card" id="card-manuais">  
            <div class="card-header">  
                <span class="card-badge badge-administrativo">Administrativo</span>  
                <div class="card-icon icon-manuais">  
                    <i class="fa fa-file-video-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Vídeos Tutoriais</h3>  
            <p class="card-description">Acesse vídeos sobre operações do sistema.</p>  
            <button class="card-button btn-manuais" onclick="window.location.href='manuais/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Indexador -->  
        <?php  
            $configFile = __DIR__ . '/indexador/config_indexador.json';  
            if (file_exists($configFile)) {  
                $configData = json_decode(file_get_contents($configFile), true);  
                if (isset($configData['indexador_ativo']) && $configData['indexador_ativo'] === 'S') {  
                    echo '  
                        <div class="module-card" id="card-indexador">  
                            <div class="card-header">  
                                <span class="card-badge badge-documental">Documental</span>  
                                <div class="card-icon icon-indexador">  
                                    <i class="fa fa-file-text-o"></i>  
                                </div>  
                            </div>  
                            <h3 class="card-title">Indexador</h3>  
                            <p class="card-description">Indexe e localize documentos por conteúdo.</p>  
                            <button class="card-button btn-indexador" onclick="window.location.href=\'indexador/index.php\'">  
                                <i class="fa fa-arrow-right"></i> Acessar  
                            </button>  
                        </div>';  
                }  
            }  
        ?>  
        
        <!-- REURB -->  
        <?php  
            $configFile = __DIR__ . '/reurb/config_reurb.json';  
            if (file_exists($configFile)) {  
                $configData = json_decode(file_get_contents($configFile), true);  
                if (isset($configData['reurb_ativo']) && $configData['reurb_ativo'] === 'S') {  
                    echo '  
                        <div class="module-card" id="card-reurb">  
                            <div class="card-header">  
                                <span class="card-badge badge-juridico">Jurídico</span>  
                                <div class="card-icon icon-reurb">  
                                    <i class="fa fa-map"></i>  
                                </div>  
                            </div>  
                            <h3 class="card-title">REURB</h3>  
                            <p class="card-description">Gerencie processos de regularização urbana.</p>  
                            <button class="card-button btn-reurb" onclick="window.location.href=\'reurb/index.php\'">  
                                <i class="fa fa-arrow-right"></i> Acessar  
                            </button>  
                        </div>';  
                }  
            }  
        ?>  
        
        <!-- Anotações -->  
        <div class="module-card" id="card-anotacoes">  
            <div class="card-header">  
                <span class="card-badge badge-administrativo">Administrativo</span>  
                <div class="card-icon icon-anotacoes">  
                    <i class="fa fa-sticky-note-o"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Anotações</h3>  
            <p class="card-description">Crie e organize anotações e lembretes.</p>  
            <button class="card-button btn-anotacoes" onclick="window.location.href='suas_notas/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
        
        <!-- Relatórios -->  
        <div class="module-card" id="card-relatorios">  
            <div class="card-header">  
                <span class="card-badge badge-operacional">Operacional</span>  
                <div class="card-icon icon-relatorios">  
                    <i class="fa fa-line-chart"></i>  
                </div>  
            </div>  
            <h3 class="card-title">Relatórios e Livros</h3>  
            <p class="card-description">Acesse relatórios e visualize registros.</p>  
            <button class="card-button btn-relatorios" onclick="window.location.href='relatorios/index.php'">  
                <i class="fa fa-arrow-right"></i> Acessar  
            </button>  
        </div>  
    </div>  
</div>  

<!-- Modal de Tarefas -->  
<div class="modal fade" id="tarefasModal" tabindex="-1" aria-labelledby="tarefasModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-lg" style="max-width: 70%;">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="tarefasModalLabel">Resumo de Tarefas</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>  
            </div>  
            <div class="modal-body">  
                <!-- Seção para novas tarefas -->  
                <div id="novas-tarefas-section" style="display: none;">  
                    <h6 class="section-title text-success">  
                        <i class="fa fa-plus-circle"></i> Novas Tarefas  
                    </h6>  
                    <div id="novas-tarefas-list" class="task-list-container"></div>  
                </div>  
                
                <!-- Divisor -->  
                <hr class="my-4">  
                
                <!-- Seção para tarefas pendentes -->  
                <div>  
                    <h6 class="section-title text-primary">  
                        <i class="fa fa-tasks"></i> Tarefas Pendentes  
                    </h6>  
                    <div id="tarefas-list" class="task-list-container"></div>  
                </div>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<!-- Modal de Acesso Negado -->  
<div class="modal fade" id="accessDeniedModal" tabindex="-1" aria-labelledby="accessDeniedLabel" aria-hidden="true">  
    <div class="modal-dialog modal-dialog-centered">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="accessDeniedLabel">Acesso Negado</h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>  
            </div>  
            <div class="modal-body">  
                Você não tem permissão para acessar esta página.  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>  
            </div>  
        </div>  
    </div>  
</div>  

<script src="script/jquery.min.js"></script>  
<script src="script/jquery-ui.min.js"></script>  
<script src="script/bootstrap.min.js"></script>  
<script src="script/jquery.mask.min.js"></script>  
<script>  
$(document).ready(function() {  
    // Função para formatar a data no padrão brasileiro  
    function formatarDataBrasileira(dataISO) {  
        const data = new Date(dataISO);  
        if (isNaN(data.getTime())) {  
            return 'Data inválida';  
        }  
        const dia = String(data.getDate()).padStart(2, '0');  
        const mes = String(data.getMonth() + 1).padStart(2, '0');  
        const ano = data.getFullYear();  
        const horas = String(data.getHours()).padStart(2, '0');  
        const minutos = String(data.getMinutes()).padStart(2, '0');  
        return `${dia}/${mes}/${ano} ${horas}:${minutos}`;  
    }  

    // Função para limitar o texto  
    function limitarTexto(texto, limite) {  
        if (texto.length > limite) {  
            return texto.substring(0, limite) + '...';  
        }  
        return texto;  
    }  

    // Função para capitalizar texto  
    function capitalize(text) {  
        return text.charAt(0).toUpperCase() + text.slice(1);  
    }  

    // Função para retornar a classe de status  
    function getStatusClassLabel(status) {  
        switch (status.toLowerCase()) {  
            case 'iniciada': return 'status-iniciada';  
            case 'em espera': return 'status-em-espera';  
            case 'em andamento': return 'status-em-andamento';  
            case 'concluída': return 'status-concluida';  
            case 'cancelada': return 'status-cancelada';  
            case 'pendente': return 'status-pendente';  
            default: return '';  
        }  
    }  

    // Função para retornar a classe de status para o fundo  
    function getStatusClassBackground(status_data) {  
        switch (status_data) {  
            case 'Prestes a vencer': return 'status-prestes-vencer';  
            case 'Vencida': return 'status-vencida';  
            default: return '';  
        }  
    }  

    // Função para criar tabelas HTML com as tarefas  
    function criarTabelaPorPrioridade(prioridade, tarefas) {  
        let tabela = `  
            <h6 class="mb-3 mt-4">Prioridade: ${prioridade}</h6>  
            <div class="table-responsive">  
                <table class="table table-hover">  
                    <thead>  
                        <tr>  
                            <th>ID</th>  
                            <th>Título</th>  
                            <th>Data Limite</th>  
                            <th>Status</th>  
                            <th>Situação</th>  
                            <th></th>  
                        </tr>  
                    </thead>  
                    <tbody>  
        `;  

        // Adiciona as tarefas à tabela  
        tarefas.forEach(tarefa => {  
            const statusClassLabel = getStatusClassLabel(tarefa.status);  
            const statusClassBackground = getStatusClassBackground(tarefa.status_data);  
            
            tabela += `  
                <tr>  
                    <td>${tarefa.id}</td>  
                    <td>${limitarTexto(tarefa.titulo, 50)}</td>  
                    <td>${formatarDataBrasileira(tarefa.data_limite)}</td>  
                    <td><span class="status-label ${statusClassLabel}">${capitalize(tarefa.status)}</span></td>  
                    <td>${tarefa.status_data ? `<span class="status-label ${statusClassBackground}">${tarefa.status_data}</span>` : ''}</td>  
                    <td>  
                        <button class="btn btn-sm btn-info" onclick="window.location.href='tarefas/index_tarefa.php?token=${tarefa.token}'">  
                            <i class="fa fa-eye"></i>  
                        </button>  
                    </td>  
                </tr>  
            `;  
        });  

        tabela += `  
                    </tbody>  
                </table>  
            </div>  
        `;  

        return tabela;  
    }  

    // Busca de módulos  
    $("#searchModules").on("keyup", function() {  
        var value = $(this).val().toLowerCase();  
        $("#sortable-cards .module-card").filter(function() {  
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)  
        });  
    });  

    // Inicializa o sortable para os cards  
    $("#sortable-cards").sortable({  
        placeholder: "ui-state-highlight",  
        handle: ".card-header",  
        cursor: "move",  
        update: function(event, ui) {  
            saveCardOrder();  
        }  
    });  

    // Função para salvar a ordem dos cards  
    function saveCardOrder() {  
        let order = [];  
        $("#sortable-cards .module-card").each(function() {  
            order.push($(this).attr('id'));  
        });  

        $.ajax({  
            url: 'save_order.php',  
            type: 'POST',  
            data: { order: order },  
            success: function(response) {  
                console.log('Ordem salva com sucesso!');  
            },  
            error: function(xhr, status, error) {  
                console.error('Erro ao salvar a ordem:', error);  
            }  
        });  
    }  

    // Carrega a ordem dos cards  
    function loadCardOrder() {  
        $.ajax({  
            url: 'load_order.php',  
            type: 'GET',  
            dataType: 'json',  
            success: function(data) {  
                if (data && data.order) {  
                    $.each(data.order, function(index, cardId) {  
                        $("#" + cardId).appendTo("#sortable-cards");  
                    });  
                }  
            },  
            error: function(xhr, status, error) {  
                console.error('Erro ao carregar a ordem:', error);  
            }  
        });  
    }  

    // Carrega a ordem ao iniciar a página  
    loadCardOrder();  

    // Carregar as tarefas pendentes  
    $.ajax({  
        url: 'verificar_tarefas.php',  
        method: 'GET',  
        dataType: 'json',  
        success: function(response) {  
            var tarefasList = $('#tarefas-list');  
            var novasTarefasList = $('#novas-tarefas-list');  
            tarefasList.empty();  
            novasTarefasList.empty();  

            var totalTarefas = 0;  

            // Exibir as novas tarefas  
            $.each(response.novas_tarefas, function(funcionario, tarefasFuncionario) {  
                $('#novas-tarefas-section').show();  
                novasTarefasList.append(`<h6 class="fw-bold">${funcionario}</h6>`);  
                
                const novasTarefasCritica = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Crítica');  
                const novasTarefasAlta = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Alta');  
                const novasTarefasMedia = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Média');  
                const novasTarefasBaixa = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Baixa');  

                if (novasTarefasCritica.length > 0) {  
                    novasTarefasList.append(criarTabelaPorPrioridade('Crítica', novasTarefasCritica));  
                }  
                if (novasTarefasAlta.length > 0) {  
                    novasTarefasList.append(criarTabelaPorPrioridade('Alta', novasTarefasAlta));  
                }  
                if (novasTarefasMedia.length > 0) {  
                    novasTarefasList.append(criarTabelaPorPrioridade('Média', novasTarefasMedia));  
                }  
                if (novasTarefasBaixa.length > 0) {  
                    novasTarefasList.append(criarTabelaPorPrioridade('Baixa', novasTarefasBaixa));  
                }  

                totalTarefas += tarefasFuncionario.length;  
            });  

            // Exibir as tarefas pendentes  
            $.each(response.tarefas, function(funcionario, tarefasFuncionario) {  
                tarefasList.append(`<h6 class="fw-bold">${funcionario}</h6>`);  
                
                const tarefasCritica = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Crítica');  
                const tarefasAlta = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Alta');  
                const tarefasMedia = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Média');  
                const tarefasBaixa = tarefasFuncionario.filter(tarefa => tarefa.nivel_de_prioridade === 'Baixa');  

                if (tarefasCritica.length > 0) {  
                    tarefasList.append(criarTabelaPorPrioridade('Crítica', tarefasCritica));  
                }  
                if (tarefasAlta.length > 0) {  
                    tarefasList.append(criarTabelaPorPrioridade('Alta', tarefasAlta));  
                }  
                if (tarefasMedia.length > 0) {  
                    tarefasList.append(criarTabelaPorPrioridade('Média', tarefasMedia));  
                }  
                if (tarefasBaixa.length > 0) {  
                    tarefasList.append(criarTabelaPorPrioridade('Baixa', tarefasBaixa));  
                }  

                totalTarefas += tarefasFuncionario.length;  
            });  

            // Mostrar o modal se houver tarefas  
            if (totalTarefas > 0) {  
                $('#tarefasModal').modal('show');  
            }  
        },  
        error: function(xhr, status, error) {  
            console.error('Erro ao carregar as tarefas:', error);  
        }  
    });  

    // Alternar modo claro/escuro  
    $('.mode-switch').on('click', function() {  
        $('body').toggleClass('dark-mode light-mode');  
    });  
});  
</script>  

<?php include(__DIR__ . '/rodape.php'); ?>  
</body>  
</html>