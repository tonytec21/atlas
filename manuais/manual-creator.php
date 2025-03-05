<?php  
require_once 'conexao_bd.php';  

// Verificar se é edição de um manual existente  
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;  
$status = isset($_GET['status']) ? $_GET['status'] : '';  
$manual = null;  
$passos = [];  
$categorias = [];  

try {  
    // Buscar categorias  
    $stmt = $conexao->query("SELECT id, nome FROM categorias ORDER BY nome");  
    $categorias = $stmt->fetchAll();  
    
    // Se for edição, buscar dados do manual  
    if ($id > 0) {  
        // Buscar dados do manual  
        $stmt = $conexao->prepare("SELECT * FROM manuais WHERE id = ?");  
        $stmt->execute([$id]);  
        $manual = $stmt->fetch();  
        
        if (!$manual) {  
            header('Location: manual-list.php');  
            exit;  
        }  
        
        // Buscar passos do manual  
        $stmt = $conexao->prepare("SELECT * FROM passos WHERE manual_id = ? ORDER BY numero ASC");  
        $stmt->execute([$id]);  
        $passos = $stmt->fetchAll();  
    }  
} catch (PDOException $e) {  
    error_log("Erro ao carregar dados: " . $e->getMessage());  
    $erro = "Ocorreu um erro ao carregar os dados do manual.";  
}  

// Função para sanitizar valores para exibição em HTML  
function h($string) {  
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');  
}  
?>  
<!DOCTYPE html>  
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title><?php echo $id > 0 ? 'Editar Manual' : 'Novo Manual'; ?></title>  
    
    <!-- Bootstrap CSS -->  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">  
    
    <!-- Font Awesome -->  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">  
    
    <!-- Quill Editor CSS -->  
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">  
    
    <!-- SweetAlert2 -->  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
    
    <style>  
        :root {  
            --sidebar-width: 250px;  
            --topbar-height: 60px;  
            --primary-color: #0d6efd;  
            --secondary-color: #6c757d;  
            --light-bg: #f8f9fa;  
            --border-color: #dee2e6;  
        }  
        
        body {  
            overflow-x: hidden;  
            background-color: #f5f5f5;  
            min-height: 100vh;  
            padding-bottom: 50px;  
        }  
        
        /* Sidebar */  
        .sidebar {  
            position: fixed;  
            top: 0;  
            left: 0;  
            height: 100vh;  
            width: var(--sidebar-width);  
            background-color: #212529;  
            padding-top: var(--topbar-height);  
            z-index: 100;  
            transition: all 0.3s;  
        }  
        
        .sidebar.collapsed {  
            margin-left: calc(-1 * var(--sidebar-width));  
        }  
        
        .sidebar .nav-link {  
            color: rgba(255, 255, 255, 0.75);  
            padding: 0.8rem 1rem;  
            border-left: 3px solid transparent;  
            transition: all 0.2s;  
        }  
        
        .sidebar .nav-link.active {  
            color: white;  
            background-color: rgba(255, 255, 255, 0.1);  
            border-left-color: var(--primary-color);  
        }  
        
        .sidebar .nav-link:hover {  
            color: white;  
            background-color: rgba(255, 255, 255, 0.1);  
        }  
        
        .sidebar .nav-link i {  
            width: 24px;  
            margin-right: 10px;  
            text-align: center;  
        }  
        
        /* Topbar */  
        .topbar {  
            position: fixed;  
            top: 0;  
            left: 0;  
            right: 0;  
            height: var(--topbar-height);  
            background-color: white;  
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);  
            z-index: 101;  
            display: flex;  
            align-items: center;  
            padding: 0 1rem;  
        }  
        
        .menu-toggle {  
            margin-right: 1rem;  
            cursor: pointer;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            width: 40px;  
            height: 40px;  
            border-radius: 50%;  
            transition: all 0.2s;  
        }  
        
        .menu-toggle:hover {  
            background-color: rgba(0, 0, 0, 0.05);  
        }  
        
        /* Main Content */  
        .main-content {  
            margin-left: var(--sidebar-width);  
            padding-top: calc(var(--topbar-height) + 20px);  
            padding-left: 40px;  
            padding-right: 40px;  
            transition: all 0.3s;  
        }  
        
        .main-content.expanded {  
            margin-left: 0;  
        }  
        
        .content-container {  
            background-color: #fff;  
            border-radius: 10px;  
            padding: 30px;  
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);  
        }  
        
        /* Form */  
        .form-group {  
            margin-bottom: 25px;  
        }  
        
        .form-label {  
            font-weight: 500;  
            margin-bottom: 8px;  
        }  
        
        /* Steps */  
        .step-card {  
            border: 1px solid var(--border-color);  
            border-radius: 8px;  
            margin-bottom: 15px;  
            background-color: white;  
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);  
        }  
        
        .step-card .card-header {  
            display: flex;  
            align-items: center;  
            padding: 15px;  
            border-bottom: 1px solid var(--border-color);  
            background-color: var(--light-bg);  
            border-top-left-radius: 8px;  
            border-top-right-radius: 8px;  
            cursor: pointer;  
        }  
        
        .step-card .card-header .drag-handle {  
            cursor: move;  
            margin-right: 10px;  
            color: var(--secondary-color);  
        }  
        
        .step-card .step-number {  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            width: 30px;  
            height: 30px;  
            border-radius: 50%;  
            background-color: var(--primary-color);  
            color: white;  
            font-weight: bold;  
            margin-right: 10px;  
        }  
        
        .step-card .step-title {  
            flex-grow: 1;  
        }  
        
        .step-card .card-body {  
            padding: 25px;  
        }  
        
        .step-card .btn-group {  
            margin-left: 10px;  
        }  
        
        .step-card-placeholder {  
            border: 2px dashed var(--border-color);  
            border-radius: 8px;  
            background-color: rgba(0, 0, 0, 0.02);  
            margin-bottom: 15px;  
        }  
        
        /* Cover Image */  
        .cover-upload {  
            border: 2px dashed var(--border-color);  
            border-radius: 8px;  
            padding: 20px;  
            text-align: center;  
            background-color: var(--light-bg);  
            position: relative;  
            cursor: pointer;  
            height: 200px;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            overflow: hidden;  
        }  
        
        .cover-upload input[type="file"] {  
            position: absolute;  
            top: 0;  
            left: 0;  
            width: 100%;  
            height: 100%;  
            opacity: 0;  
            cursor: pointer;  
        }  
        
        .cover-upload .placeholder {  
            display: flex;  
            flex-direction: column;  
            align-items: center;  
            color: var(--secondary-color);  
        }  
        
        #coverPreview {  
            width: 100%;  
            height: 100%;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            position: relative;  
        }  
        
        #coverPreview img {  
            max-width: 100%;  
            max-height: 100%;  
            object-fit: cover;  
            border-radius: 4px;  
        }  
        
        .remove-image {  
            position: absolute;  
            top: 10px;  
            right: 10px;  
            width: 30px;  
            height: 30px;  
            border-radius: 50%;  
            background-color: rgba(255, 255, 255, 0.8);  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            color: #dc3545;  
            cursor: pointer;  
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);  
            opacity: 0.8;  
            transition: all 0.2s;  
        }  
        
        .remove-image:hover {  
            opacity: 1;  
            transform: scale(1.1);  
        }  
        
        /* Image preview for steps */  
        .image-preview {  
            padding: 5px;  
            border: 1px solid var(--border-color);  
            border-radius: 4px;  
            margin-top: 10px;  
            position: relative;  
            width: 100%;  
            height: 150px;  
            background-color: var(--light-bg);  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            overflow: hidden;  
        }  
        
        .image-preview img {  
            max-width: 100%;  
            max-height: 100%;  
            object-fit: contain;  
        }  
        
        /* Editor customization */  
        .editor-container {  
            height: 200px;  
            margin-bottom: 15px;  
        }  
        
        .ql-editor {  
            min-height: 150px;  
        }  
        
        .ql-toolbar {  
            border-top-left-radius: 4px;  
            border-top-right-radius: 4px;  
        }  
        
        .ql-container {  
            border-bottom-left-radius: 4px;  
            border-bottom-right-radius: 4px;  
        }  
        
        /* Action Buttons */  
        .action-buttons {  
            position: fixed;  
            bottom: 0;  
            left: 0;  
            right: 0;  
            background-color: white;  
            padding: 15px 0;  
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);  
            z-index: 99;  
        }  
        
        .action-buttons .container {  
            display: flex;  
            justify-content: space-between;  
        }  
        
        /* Mobile Responsive */  
        @media (max-width: 992px) {  
            .sidebar {  
                margin-left: calc(-1 * var(--sidebar-width));  
            }  
            
            .sidebar.mobile-visible {  
                margin-left: 0;  
            }  
            
            .main-content {  
                margin-left: 0;  
                padding-left: 20px;  
                padding-right: 20px;  
            }  
            
            .topbar .menu-toggle {  
                display: flex;  
            }  
        }  
        
        /* Enhancements for collapse controls */  
        .collapse-icon {  
            transition: transform 0.3s;  
        }  
        
        .collapsed .collapse-icon {  
            transform: rotate(180deg);  
        }  
        
        /* Paste image clipboard helper */  
        .clipboard-helper {  
            background-color: #fff3cd;  
            border: 1px solid #ffeeba;  
            border-radius: 4px;  
            padding:.75rem 1.25rem;  
            margin-bottom: 20px;  
            position: relative;  
            color: #856404;  
        }  
        
        .clipboard-helper p {  
            margin-bottom: 0;  
            padding-right: 30px;  
        }  
        
        .clipboard-helper .close-helper {  
            position: absolute;  
            top: 10px;  
            right: 10px;  
            cursor: pointer;  
            color: #856404;  
        }  
        
        /* Alert para instruções de colagem */  
        .paste-instruction {  
            position: absolute;  
            top: 50%;  
            left: 50%;  
            transform: translate(-50%, -50%);  
            text-align: center;  
            padding: 15px;  
            background-color: rgba(0, 0, 0, 0.05);  
            border-radius: 5px;  
            display: none;  
            pointer-events: none;  
            animation: fadeInOut 2s ease-in-out;  
        }  
        
        .cover-upload:hover .paste-instruction {  
            display: block;  
        }  
        
        @keyframes fadeInOut {  
            0% { opacity: 0; }  
            50% { opacity: 1; }  
            100% { opacity: 0; }  
        }  
    </style>  
</head>  
<body>  
    <!-- Topbar -->  
    <div class="topbar">  
        <div class="menu-toggle" id="menu-toggle">  
            <i class="fas fa-bars"></i>  
        </div>  
        <div class="logo">  
            <h4 class="m-0">Sistema de Manuais</h4>  
        </div>  
        <div class="ms-auto d-flex align-items-center">  
            <div class="dropdown">  
                <a class="dropdown-toggle text-decoration-none text-dark" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">  
                    <i class="fas fa-user-circle me-1"></i> Admin  
                </a>  
                <ul class="dropdown-menu dropdown-menu-end">  
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Perfil</a></li>  
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Configurações</a></li>  
                    <li><hr class="dropdown-divider"></li>  
                    <li><a class="dropdown-item" href="#"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>  
                </ul>  
            </div>  
        </div>  
    </div>  

    <!-- Sidebar -->  
    <div class="sidebar" id="sidebar">  
        <div class="nav flex-column">  
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>  
            <a class="nav-link active" href="manual-list.php"><i class="fas fa-book"></i> Manuais</a>  
            <a class="nav-link" href="categories.php"><i class="fas fa-tags"></i> Categorias</a>  
            <a class="nav-link" href="users.php"><i class="fas fa-users"></i> Usuários</a>  
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i> Configurações</a>  
        </div>  
    </div>  

    <!-- Main Content -->  
    <div class="main-content" id="main-content">  
        <!-- Mensagem de Status -->  
        <?php if ($status === 'success'): ?>  
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">  
                Manual salvo com sucesso!  
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
            </div>  
        <?php elseif ($status === 'error'): ?>  
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">  
                Ocorreu um erro ao salvar o manual. Tente novamente.  
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
            </div>  
        <?php endif; ?>  
        
        <?php if (isset($erro)): ?>  
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">  
                <?php echo $erro; ?>  
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
            </div>  
        <?php endif; ?>  
        
        <div class="d-flex justify-content-between align-items-center mb-4">  
            <h1><?php echo $id > 0 ? 'Editar Manual' : 'Novo Manual'; ?></h1>  
            <a href="manual-list.php" class="btn btn-outline-secondary">  
                <i class="fas fa-arrow-left me-2"></i> Voltar para a lista  
            </a>  
        </div>  
        
        <div class="content-container">  
            <form id="manualForm" action="save_manual.php" method="post" enctype="multipart/form-data">  
                <?php if ($id > 0): ?>  
                    <input type="hidden" name="id" value="<?php echo $id; ?>">  
                <?php endif; ?>  
                
                <!-- Informações Básicas -->  
                <div class="row mb-4">  
                    <div class="col-md-8">  
                        <div class="mb-3">  
                            <label for="titulo" class="form-label">Título do Manual</label>  
                            <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo isset($manual['titulo']) ? h($manual['titulo']) : ''; ?>" required>  
                        </div>  
                        
                        <div class="mb-3">  
                            <label for="descricao" class="form-label">Descrição</label>  
                            <textarea class="form-control" id="descricao" name="descricao" rows="4"><?php echo isset($manual['descricao']) ? h($manual['descricao']) : ''; ?></textarea>  
                        </div>  
                        
                        <div class="row">  
                            <div class="col-md-6">  
                                <div class="mb-3">  
                                    <label for="categoria_id" class="form-label">Categoria</label>  
                                    <select class="form-select" id="categoria_id" name="categoria_id">  
                                        <option value="">Selecione uma categoria</option>  
                                        <?php foreach ($categorias as $categoria): ?>  
                                            <option value="<?php echo $categoria['id']; ?>" <?php echo (isset($manual['categoria_id']) && $manual['categoria_id'] == $categoria['id']) ? 'selected' : ''; ?>>  
                                                <?php echo h($categoria['nome']); ?>  
                                            </option>  
                                        <?php endforeach; ?>  
                                    </select>  
                                </div>  
                            </div>  
                            <div class="col-md-6">  
                                <div class="mb-3">  
                                    <label for="versao" class="form-label">Versão</label>  
                                    <input type="text" class="form-control" id="versao" name="versao" value="<?php echo isset($manual['versao']) ? h($manual['versao']) : '1.0'; ?>">  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                    
                    <div class="col-md-4">  
                        <label class="form-label">Imagem de Capa</label>  
                        <div class="cover-upload mb-2" id="coverUploadArea">  
                            <input type="file" id="imagemCapa" name="imagem_capa" accept="image/*">  
                            <div id="coverPreview">  
                                <?php if (isset($manual['imagem_capa']) && !empty($manual['imagem_capa'])): ?>  
                                    <img src="<?php echo $manual['imagem_capa']; ?>" alt="Capa do Manual">  
                                    <span class="remove-image" id="removeCover" title="Remover imagem">  
                                        <i class="fas fa-times"></i>  
                                    </span>  
                                <?php else: ?>  
                                    <div class="placeholder">  
                                        <i class="fas fa-image fa-3x mb-2"></i>  
                                        <p>Clique ou cole uma imagem (Ctrl+V)</p>  
                                    </div>  
                                <?php endif; ?>  
                            </div>  
                            <div class="paste-instruction">  
                                <i class="fas fa-paste me-2"></i> Pressione Ctrl+V para colar uma imagem da área de transferência  
                            </div>  
                        </div>  
                        <input type="hidden" name="imagem_capa_atual" value="<?php echo isset($manual['imagem_capa']) ? $manual['imagem_capa'] : ''; ?>">  
                        <input type="hidden" name="remover_capa" id="removerCapa" value="0">  
                        <div class="text-muted small">Clique para selecionar, cole (Ctrl+V) ou arraste uma imagem. Tamanho recomendado: 1200x630px.</div>  
                    </div>  
                </div>  
                
                <!-- Clipboard Paste Helper -->  
                <div class="clipboard-helper" id="clipboard-helper">  
                    <span class="close-helper" id="close-helper"><i class="fas fa-times"></i></span>  
                    <p><i class="fas fa-lightbulb me-2"></i> <strong>Dica:</strong> Você pode colar imagens diretamente da área de transferência. Faça uma captura de tela e pressione <kbd>Ctrl+V</kbd> (ou <kbd>⌘+V</kbd> no Mac) quando estiver com foco em uma área de imagem.</p>  
                </div>  
                
                <!-- Passos -->  
                <h4 class="mb-3 mt-4">Passos do Manual</h4>  
                
                <div id="passos-container">  
                    <?php if (count($passos) > 0): ?>  
                        <?php foreach ($passos as $passo): ?>  
                            <div class="step-card" data-step="<?php echo $passo['numero']; ?>">  
                                <div class="card-header" data-bs-toggle="collapse" data-bs-target="#step<?php echo $passo['numero']; ?>">  
                                    <i class="fas fa-grip-lines drag-handle"></i>  
                                    <div class="step-number"><?php echo $passo['numero']; ?></div>  
                                    <h5 class="step-title mb-0"><?php echo h($passo['titulo']); ?></h5>  
                                    <div class="btn-group">  
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-step">  
                                            <i class="fas fa-trash"></i>  
                                        </button>  
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#step<?php echo $passo['numero']; ?>">  
                                            <i class="fas fa-chevron-up collapse-icon"></i>  
                                        </button>  
                                    </div>  
                                </div>  
                                <div class="collapse show" id="step<?php echo $passo['numero']; ?>">  
                                    <div class="card-body">  
                                        <input type="hidden" name="passo_id[]" value="<?php echo $passo['id']; ?>">  
                                        <div class="mb-3">  
                                            <label class="form-label">Título do Passo</label>  
                                            <input type="text" class="form-control step-title-input" name="passo_titulo[]" placeholder="Ex: Instalação do Software" value="<?php echo h($passo['titulo']); ?>" required>  
                                        </div>  
                                        <div class="mb-3">  
                                            <label class="form-label">Conteúdo</label>  
                                            <div class="editor-container">  
                                                <div class="editor"><?php echo $passo['texto']; ?></div>  
                                            </div>  
                                            <textarea class="editor-content-hidden" name="passo_texto[]" style="display: none;"><?php echo $passo['texto']; ?></textarea>  
                                        </div>  
                                        <div class="row">  
                                            <div class="col-md-6">  
                                                <label class="form-label">Imagem (Opcional)</label>  
                                                <input type="file" class="form-control step-image-input" name="passo_imagem[]" accept="image/*">  
                                                <input type="hidden" name="passo_imagem_atual[]" value="<?php echo $passo['imagem']; ?>">  
                                                <input type="hidden" name="remover_imagem_passo[]" value="0">  
                                                <?php if (!empty($passo['imagem'])): ?>  
                                                    <div class="image-preview mt-2">  
                                                        <img src="<?php echo $passo['imagem']; ?>" alt="Preview">  
                                                        <span class="remove-image" title="Remover imagem">  
                                                            <i class="fas fa-times"></i>  
                                                        </span>  
                                                    </div>  
                                                <?php else: ?>  
                                                    <div class="image-preview mt-2" style="display: none;">  
                                                        <img src="" alt="Preview">  
                                                        <span class="remove-image" title="Remover imagem">  
                                                            <i class="fas fa-times"></i>  
                                                        </span>  
                                                    </div>  
                                                <?php endif; ?>  
                                            </div>  
                                            <div class="col-md-6">  
                                                <label class="form-label">Legenda da Imagem</label>  
                                                <input type="text" class="form-control" name="passo_legenda[]" placeholder="Descreva a imagem" value="<?php echo h($passo['legenda']); ?>">  
                                            </div>  
                                        </div>  
                                    </div>  
                                </div>  
                            </div>  
                        <?php endforeach; ?>  
                    <?php else: ?>  
                        <!-- Primeiro passo (padrão) -->  
                        <div class="step-card" data-step="1">  
                            <div class="card-header" data-bs-toggle="collapse" data-bs-target="#step1">  
                                <i class="fas fa-grip-lines drag-handle"></i>  
                                <div class="step-number">1</div>  
                                <h5 class="step-title mb-0">Novo Passo</h5>  
                                <div class="btn-group">  
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-step">  
                                        <i class="fas fa-trash"></i>  
                                    </button>  
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#step1">  
                                        <i class="fas fa-chevron-up collapse-icon"></i>  
                                    </button>  
                                </div>  
                            </div>  
                            <div class="collapse show" id="step1">  
                                <div class="card-body">  
                                    <input type="hidden" name="passo_id[]" value="">  
                                    <div class="mb-3">  
                                        <label class="form-label">Título do Passo</label>  
                                        <input type="text" class="form-control step-title-input" name="passo_titulo[]" placeholder="Ex: Instalação do Software" required>  
                                    </div>  
                                    <div class="mb-3">  
                                        <label class="form-label">Conteúdo</label>  
                                        <div class="editor-container">  
                                            <div class="editor"></div>  
                                        </div>  
                                        <textarea class="editor-content-hidden" name="passo_texto[]" style="display: none;"></textarea>  
                                    </div>  
                                    <div class="row">  
                                        <div class="col-md-6">  
                                            <label class="form-label">Imagem (Opcional)</label>  
                                            <input type="file" class="form-control step-image-input" name="passo_imagem[]" accept="image/*">  
                                            <input type="hidden" name="passo_imagem_atual[]" value="">  
                                            <input type="hidden" name="remover_imagem_passo[]" value="0">  
                                            <div class="image-preview mt-2" style="display: none;">  
                                                <img src="" alt="Preview">  
                                                <span class="remove-image" title="Remover imagem">  
                                                    <i class="fas fa-times"></i>  
                                                </span>  
                                            </div>  
                                        </div>  
                                        <div class="col-md-6">  
                                            <label class="form-label">Legenda da Imagem</label>  
                                            <input type="text" class="form-control" name="passo_legenda[]" placeholder="Descreva a imagem">  
                                        </div>  
                                    </div>  
                                </div>  
                            </div>  
                        </div>  
                    <?php endif; ?>  
                </div>  
                
                <div class="text-center mt-3 mb-5">  
                    <button type="button" id="addStep" class="btn btn-outline-primary">  
                        <i class="fas fa-plus-circle me-2"></i> Adicionar Passo  
                    </button>  
                </div>  
            </form>  
        </div>  
    </div>  
    
    <!-- Fixed Action Buttons -->  
    <div class="action-buttons">  
        <div class="container">  
            <div>  
                <a href="manual-list.php" class="btn btn-outline-secondary">Cancelar</a>  
            </div>  
            <div>  
                <button type="button" id="btnPrevisualizar" class="btn btn-outline-primary me-2">Pré-visualizar</button>  
                <button type="button" id="btnSalvar" class="btn btn-success">Salvar Manual</button>  
            </div>  
        </div>  
    </div>  
    
    <!-- jQuery -->  
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
    <!-- jQuery UI (for Sortable) -->  
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>  
    <!-- Bootstrap JS Bundle -->  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>  
    <!-- Quill Editor -->  
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>  
    
    <script>  
        $(document).ready(function() {  
            // Toggle Sidebar  
            $('#menu-toggle').on('click', function() {  
                $('#sidebar').toggleClass('collapsed');  
                $('#main-content').toggleClass('expanded');  
            });  
            
            // Responsive behavior  
            function checkSize() {  
                if ($(window).width() < 992) {  
                    $('#sidebar').addClass('collapsed');  
                    $('#main-content').addClass('expanded');  
                } else {  
                    $('#sidebar').removeClass('collapsed mobile-visible');  
                    $('#main-content').removeClass('expanded');  
                }  
            }  
            
            $(window).resize(checkSize);  
            checkSize(); // Initial check  
            
            // Mobile menu toggle  
            $('#menu-toggle').on('click', function() {  
                if ($(window).width() < 992) {  
                    $('#sidebar').toggleClass('mobile-visible');  
                }  
            });  
            
            // Inicializar os editores Quill  
            const toolbarOptions = [  
                ['bold', 'italic', 'underline', 'strike'],  
                ['blockquote', 'code-block'],  
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],  
                [{ 'indent': '-1'}, { 'indent': '+1' }],  
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],  
                [{ 'color': [] }, { 'background': [] }],  
                ['clean']  
            ];  
            
            // Inicializar editores para passos existentes  
            $('.editor').each(function(index) {  
                const quill = new Quill(this, {  
                    theme: 'snow',  
                    modules: {  
                        toolbar: toolbarOptions  
                    }  
                });  
                
                // Atualizar textarea oculto quando o conteúdo mudar  
                quill.on('text-change', function() {  
                    const html = $(quill.root).html();  
                    $(quill.root).closest('.card-body').find('.editor-content-hidden').val(html);  
                });  
                
                // Inicializar com o conteúdo atual  
                const html = $(quill.root).html();  
                $(quill.root).closest('.card-body').find('.editor-content-hidden').val(html);  
                
                // Configurar handler de cola para imagens  
                setupPasteHandler(quill);  
            });  
            
            // Helper de cola para as imagens nos editores de passos  
            function setupPasteHandler(quill) {  
                quill.root.addEventListener('paste', function(e) {  
                    if (e.clipboardData && e.clipboardData.items) {  
                        var items = e.clipboardData.items;  
                        
                        for (var i = 0; i < items.length; i++) {  
                            if (items[i].type.indexOf('image') !== -1) {  
                                e.preventDefault();  
                                e.stopPropagation();  
                                
                                var file = items[i].getAsFile();  
                                var stepCard = $(quill.root).closest('.step-card');  
                                
                                // Atualizar a prévia da imagem  
                                var reader = new FileReader();  
                                reader.onload = function(event) {  
                                    var imagePreview = stepCard.find('.image-preview');  
                                    imagePreview.find('img').attr('src', event.target.result);  
                                    imagePreview.show();  
                                    
                                    // Criar um novo File objeto a partir do blob  
                                    var pastedFile = new File([file], 'pasted-image-' + new Date().getTime() + '.png', {type: file.type});  
                                    
                                    // Criar um novo objeto DataTransfer  
                                    var dataTransfer = new DataTransfer();  
                                    dataTransfer.items.add(pastedFile);  
                                    
                                    // Atribuir o novo arquivo ao input de arquivo  
                                    var fileInput = stepCard.find('.step-image-input')[0];  
                                    fileInput.files = dataTransfer.files;  
                                    
                                    // Trigger change event  
                                    $(fileInput).trigger('change');  
                                };  
                                reader.readAsDataURL(file);  
                                
                                break;  
                            }  
                        }  
                    }  
                });  
            }  
            
            // Handler de colagem para a imagem de capa  
            $('#coverUploadArea').on('click', function() {  
                $('#imagemCapa').click();  
            });  
            
            // Adicionar manipulador de eventos de colagem ao documento para a capa  
            document.addEventListener('paste', function(e) {  
                // Verificar se o foco está em um editor Quill  
                var activeElement = document.activeElement;  
                var isInQuillEditor = $(activeElement).closest('.ql-editor').length > 0;  
                
                // Se estiver em um editor, não interferir  
                if (isInQuillEditor) {  
                    return;  
                }  
                
                if (e.clipboardData && e.clipboardData.items) {  
                    var items = e.clipboardData.items;  
                    
                    for (var i = 0; i < items.length; i++) {  
                        if (items[i].type.indexOf('image') !== -1) {  
                            e.preventDefault();  
                            
                            var file = items[i].getAsFile();  
                            
                            // Mostrar feedback visual  
                            Swal.fire({  
                                title: 'Imagem colada!',  
                                text: 'Processando imagem da área de transferência...',  
                                icon: 'info',  
                                showConfirmButton: false,  
                                timer: 1500  
                            });  
                            
                            // Atualizar a prévia da imagem de capa  
                            var reader = new FileReader();  
                            reader.onload = function(event) {  
                                $('#coverPreview').html(`  
                                    <img src="${event.target.result}" alt="Capa do Manual">  
                                    <span class="remove-image" id="removeCover" title="Remover imagem">  
                                        <i class="fas fa-times"></i>  
                                    </span>  
                                `);  
                                
                                // Criar um novo File objeto a partir do blob  
                                var pastedFile = new File([file], 'cover-image-' + new Date().getTime() + '.png', {type: file.type});  
                                
                                // Criar um novo objeto DataTransfer  
                                var dataTransfer = new DataTransfer();  
                                dataTransfer.items.add(pastedFile);  
                                
                                // Atribuir o novo arquivo ao input de arquivo da capa  
                                var fileInput = document.getElementById('imagemCapa');  
                                fileInput.files = dataTransfer.files;  
                                
                                // Reset remover flag  
                                $('#removerCapa').val('0');  
                            };  
                            reader.readAsDataURL(file);  
                            
                            break;  
                        }  
                    }  
                }  
            });  
            
            // Fechar helper de Clipboard  
            $('#close-helper').on('click', function() {  
                $('#clipboard-helper').slideUp();  
                localStorage.setItem('clipboard_helper_closed', 'true');  
            });  
            
            // Verificar se o helper já foi fechado antes  
            if(localStorage.getItem('clipboard_helper_closed') === 'true') {  
                $('#clipboard-helper').hide();  
            }  
            
            // Adicionar novo passo  
            $('#addStep').on('click', function() {  
                // Contar quantos passos já existem  
                const stepCount = $('.step-card').length;  
                const newStep = stepCount + 1;  
                
                // Criar novo card de passo  
                const stepCard = `  
                    <div class="step-card" data-step="${newStep}">  
                        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#step${newStep}">  
                            <i class="fas fa-grip-lines drag-handle"></i>  
                            <div class="step-number">${newStep}</div>  
                            <h5 class="step-title mb-0">Novo Passo</h5>  
                            <div class="btn-group">  
                                <button type="button" class="btn btn-sm btn-outline-danger remove-step">  
                                    <i class="fas fa-trash"></i>  
                                </button>  
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#step${newStep}">  
                                    <i class="fas fa-chevron-up collapse-icon"></i>  
                                </button>  
                            </div>  
                        </div>  
                        <div class="collapse show" id="step${newStep}">  
                            <div class="card-body">  
                                <input type="hidden" name="passo_id[]" value="">  
                                <div class="mb-3">  
                                    <label class="form-label">Título do Passo</label>  
                                    <input type="text" class="form-control step-title-input" name="passo_titulo[]" placeholder="Ex: Instalação do Software" required>  
                                </div>  
                                <div class="mb-3">  
                                    <label class="form-label">Conteúdo</label>  
                                    <div class="editor-container">  
                                        <div class="editor-${newStep}"></div>  
                                    </div>  
                                    <textarea class="editor-content-hidden" name="passo_texto[]" style="display: none;"></textarea>  
                                </div>  
                                <div class="row">  
                                    <div class="col-md-6">  
                                        <label class="form-label">Imagem (Opcional)</label>  
                                        <input type="file" class="form-control step-image-input" name="passo_imagem[]" accept="image/*">  
                                        <input type="hidden" name="passo_imagem_atual[]" value="">  
                                        <input type="hidden" name="remover_imagem_passo[]" value="0">  
                                        <div class="image-preview mt-2" style="display: none;">  
                                            <img src="" alt="Preview">  
                                            <span class="remove-image" title="Remover imagem">  
                                                <i class="fas fa-times"></i>  
                                            </span>  
                                        </div>  
                                    </div>  
                                    <div class="col-md-6">  
                                        <label class="form-label">Legenda da Imagem</label>  
                                        <input type="text" class="form-control" name="passo_legenda[]" placeholder="Descreva a imagem">  
                                    </div>  
                                </div>  
                            </div>  
                        </div>  
                    </div>  
                `;  
                
                // Adicionar card ao container  
                $('#passos-container').append(stepCard);  
                
                // Inicializar editor para o novo passo  
                const quill = new Quill(`.editor-${newStep}`, {  
                    theme: 'snow',  
                    modules: {  
                        toolbar: toolbarOptions  
                    }  
                });  
                
                // Atualizar textarea oculto quando o conteúdo mudar  
                quill.on('text-change', function() {  
                    const html = $(quill.root).html();  
                    $(quill.root).closest('.card-body').find('.editor-content-hidden').val(html);  
                });  
                
                // Configurar handler de cola para imagens no novo editor  
                setupPasteHandler(quill);  
                
                // Scrollar para o novo passo  
                $('html, body').animate({  
                    scrollTop: $(`#step${newStep}`).offset().top - 100  
                }, 500);  
                
                // Reinicializar eventos para o novo passo  
                initStepEvents();  
                
                // Atualizar numeração  
                updateStepNumbers();  
            });  
            
            // Inicializar eventos para os passos  
            function initStepEvents() {  
                // Remover passo  
                $('.remove-step').off('click').on('click', function(e) {  
                    e.stopPropagation();  
                    
                    const stepCard = $(this).closest('.step-card');  
                    const stepNumber = stepCard.data('step');  
                    
                    // Confirmar exclusão  
                    if (stepNumber !== undefined) {  
                        Swal.fire({  
                            title: 'Confirmar exclusão?',  
                            text: `Tem certeza que deseja excluir o Passo ${stepNumber}?`,  
                            icon: 'warning',  
                            showCancelButton: true,  
                            confirmButtonColor: '#dc3545',  
                            cancelButtonColor: '#6c757d',  
                            confirmButtonText: 'Sim, excluir!',  
                            cancelButtonText: 'Cancelar'  
                        }).then((result) => {  
                            if (result.isConfirmed) {  
                                stepCard.fadeOut(300, function() {  
                                    $(this).remove();  
                                    updateStepNumbers();  
                                });  
                            }  
                        });  
                    }  
                });  
                
                // Atualizar título do passo no cabeçalho quando editado  
                $('.step-title-input').off('input').on('input', function() {  
                    const title = $(this).val();  
                    $(this).closest('.step-card').find('.step-title').text(title || 'Novo Passo');  
                });  
                
                // Preview de imagem quando selecionada  
                $('.step-image-input').off('change').on('change', function() {  
                    if (this.files && this.files[0]) {  
                        const reader = new FileReader();  
                        const preview = $(this).siblings('.image-preview');  
                        
                        reader.onload = function(e) {  
                            preview.find('img').attr('src', e.target.result);  
                            preview.show();  
                        }  
                        
                        reader.readAsDataURL(this.files[0]);  
                    }  
                });  
                
                // Remover imagem  
                $('.remove-image').off('click').on('click', function(e) {  
                    e.stopPropagation();  
                    
                    const preview = $(this).closest('.image-preview');  
                    const stepCard = $(this).closest('.step-card');  
                    const fileInput = stepCard.find('.step-image-input');  
                    const currentImageInput = stepCard.find('input[name="passo_imagem_atual[]"]');  
                    const removeImageInput = stepCard.find('input[name="remover_imagem_passo[]"]');  
                    
                    // Confirmar remoção  
                    Swal.fire({  
                        title: 'Remover imagem?',  
                        text: 'Tem certeza que deseja remover esta imagem?',  
                        icon: 'warning',  
                        showCancelButton: true,  
                        confirmButtonColor: '#dc3545',  
                        cancelButtonColor: '#6c757d',  
                        confirmButtonText: 'Sim, remover!',  
                        cancelButtonText: 'Cancelar'  
                    }).then((result) => {  
                        if (result.isConfirmed) {  
                            // Limpar campo de arquivo  
                            fileInput.val('');  
                            
                            // Se havia uma imagem anterior, marcá-la para remoção  
                            if (currentImageInput.val()) {  
                                removeImageInput.val('1');  
                            }  
                            
                            // Esconder preview  
                            preview.hide();  
                        }  
                    });  
                });  
                
                // Toggle para mostrar/esconder conteúdo quando o cabeçalho é clicado  
                $('.card-header').off('click').on('click', function() {  
                    $(this).find('.collapse-icon').toggleClass('rotate-180');  
                });  
            }  
            
            // Inicializar eventos para os passos iniciais  
            initStepEvents();  
            
            // Tornar os passos ordenáveis  
            $('#passos-container').sortable({  
                handle: '.drag-handle',  
                placeholder: 'step-card-placeholder',  
                tolerance: 'pointer',  
                start: function(e, ui) {  
                    ui.placeholder.height(ui.item.outerHeight());  
                },  
                stop: function() {  
                    updateStepNumbers();  
                }  
            });  
            
            // Atualizar números dos passos  
            function updateStepNumbers() {  
                $('.step-card').each(function(index) {  
                    const newStep = index + 1;  
                    $(this).attr('data-step', newStep);  
                    $(this).find('.step-number').text(newStep);  
                    
                    // Atualizar o ID da collapse  
                    const collapseButton = $(this).find('.card-header');  
                    const collapseContent = $(this).find('.collapse');  
                    
                    collapseButton.attr('data-bs-target', `#step${newStep}`);  
                    collapseContent.attr('id', `step${newStep}`);  
                });  
            }  
            
            // Imagem de capa - upload normal  
            $('#imagemCapa').on('change', function() {  
                if (this.files && this.files[0]) {  
                    const reader = new FileReader();  
                    
                    reader.onload = function(e) {  
                        $('#coverPreview').html(`  
                            <img src="${e.target.result}" alt="Capa do Manual">  
                            <span class="remove-image" id="removeCover" title="Remover imagem">  
                                <i class="fas fa-times"></i>  
                            </span>  
                        `);  
                        
                        // Reset remover flag  
                        $('#removerCapa').val('0');  
                    }  
                    
                    reader.readAsDataURL(this.files[0]);  
                }  
            });  
            
            // Remover imagem de capa  
            $(document).on('click', '#removeCover', function(e) {  
                e.stopPropagation();  
                e.preventDefault();  
                
                Swal.fire({  
                    title: 'Remover imagem de capa?',  
                    text: 'Tem certeza que deseja remover a imagem de capa?',  
                    icon: 'warning',  
                    showCancelButton: true,  
                    confirmButtonColor: '#dc3545',  
                    cancelButtonColor: '#6c757d',  
                    confirmButtonText: 'Sim, remover!',  
                    cancelButtonText: 'Cancelar'  
                }).then((result) => {  
                    if (result.isConfirmed) {  
                        // Limpar campo de arquivo  
                        $('#imagemCapa').val('');  
                        
                        // Marcar para remoção  
                        $('#removerCapa').val('1');  
                        
                        // Atualizar preview  
                        $('#coverPreview').html(`  
                            <div class="placeholder">  
                                <i class="fas fa-image fa-3x mb-2"></i>  
                                <p>Clique ou cole uma imagem (Ctrl+V)</p>  
                            </div>  
                        `);  
                    }  
                });  
            });  
            
            // Adicionar suporte para arrastar e soltar imagens na capa  
            $('#coverUploadArea').on('dragover', function(e) {  
                e.preventDefault();  
                e.stopPropagation();  
                $(this).addClass('border-primary');  
            }).on('dragleave', function(e) {  
                e.preventDefault();  
                e.stopPropagation();  
                $(this).removeClass('border-primary');  
            }).on('drop', function(e) {  
                e.preventDefault();  
                e.stopPropagation();  
                $(this).removeClass('border-primary');  
                
                var files = e.originalEvent.dataTransfer.files;  
                if (files.length > 0 && files[0].type.match('image.*')) {  
                    var reader = new FileReader();  
                    reader.onload = function(e) {  
                        $('#coverPreview').html(`  
                            <img src="${e.target.result}" alt="Capa do Manual">  
                            <span class="remove-image" id="removeCover" title="Remover imagem">  
                                <i class="fas fa-times"></i>  
                            </span>  
                        `);  
                        
                        // Reset remover flag  
                        $('#removerCapa').val('0');  
                    };  
                    
                    reader.readAsDataURL(files[0]);  
                    
                    // Atualizar input file  
                    var dataTransfer = new DataTransfer();  
                    dataTransfer.items.add(files[0]);  
                    document.getElementById('imagemCapa').files = dataTransfer.files;  
                }  
            });  
            
            // Salvar manual  
            $('#btnSalvar').on('click', function() {  
                // Verificar validação básica  
                const titulo = $('#titulo').val().trim();  
                
                if (!titulo) {  
                    Swal.fire({  
                        title: 'Atenção!',  
                        text: 'O título do manual é obrigatório.',  
                        icon: 'warning'  
                    });  
                    return;  
                }  
                
                // Verificar se existe pelo menos um passo  
                if ($('.step-card').length === 0) {  
                    Swal.fire({  
                        title: 'Atenção!',  
                        text: 'É necessário ter pelo menos um passo no manual.',  
                        icon: 'warning'  
                    });  
                    return;  
                }  
                
                // Verificar se todos os passos têm título  
                let passosSemTitulo = false;  
                $('.step-card').each(function() {  
                    const tituloInput = $(this).find('input[name="passo_titulo[]"]');  
                    if (!tituloInput.val().trim()) {  
                        passosSemTitulo = true;  
                        tituloInput.addClass('is-invalid');  
                    } else {  
                        tituloInput.removeClass('is-invalid');  
                    }  
                });  
                
                if (passosSemTitulo) {  
                    Swal.fire({  
                        title: 'Atenção!',  
                        text: 'Todos os passos precisam ter um título.',  
                        icon: 'warning'  
                    });  
                    return;  
                }  
                
                // Mostrar loading  
                Swal.fire({  
                    title: 'Salvando...',  
                    allowOutsideClick: false,  
                    didOpen: () => {  
                        Swal.showLoading();  
                    }  
                });  
                
                // Enviar formulário  
                $('#manualForm').submit();  
            });  
            
            // Preview do manual  
            $('#btnPrevisualizar').on('click', function() {  
                const manualId = $('input[name="id"]').val();  
                if (manualId) {  
                    window.open(`view-manual.php?id=${manualId}&preview=1`, '_blank');  
                } else {  
                    Swal.fire({  
                        title: 'Atenção',  
                        text: 'Salve o manual primeiro para poder previsualizá-lo.',  
                        icon: 'info'  
                    });  
                }  
            });  
            
            // Mostrar notificação sobre colagem de imagens na capa quando usuário foca na área  
            $('#coverUploadArea').on('focus click', function() {  
                // Mostrar uma dica temporária sobre colar imagens  
                const instruction = `  
                    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">  
                        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">  
                            <div class="toast-header">  
                                <i class="fas fa-lightbulb me-2 text-warning"></i>  
                                <strong class="me-auto">Dica</strong>  
                                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>  
                            </div>  
                            <div class="toast-body">  
                                Você pode colar uma imagem usando <kbd>Ctrl+V</kbd> ou <kbd>⌘+V</kbd>  
                            </div>  
                        </div>  
                    </div>  
                `;  
                
                // Remover toast existente  
                $('.toast-container').remove();  
                
                // Adicionar novo toast  
                $('body').append(instruction);  
                
                // Configurar auto-hide  
                setTimeout(function() {  
                    $('.toast').toast('hide');  
                    setTimeout(function() {  
                        $('.toast-container').remove();  
                    }, 500);  
                }, 3000);  
            });  
        });  
    </script>  
</body>  
</html>