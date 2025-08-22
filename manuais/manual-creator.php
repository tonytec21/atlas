<?php  
require_once 'conexao_bd.php';  

// Iniciar sessão  
session_start();  

// Verificar se o usuário está logado  
if (!isset($_SESSION['username'])) {  
    header('Location: ../login.php');  
    exit;  
}  

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
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');  
}  
?>  
<!DOCTYPE html>  
<html lang="pt-BR" data-theme="light">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title><?php echo $id > 0 ? 'Editar Manual' : 'Novo Manual'; ?></title>  

    
    <link rel="icon" href="img/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="img/manuflow-mark-32.png" sizes="32x32">
    <link rel="apple-touch-icon" href="img/manuflow-mark-180.png" sizes="180x180">
    <link rel="icon" type="image/png" href="img/manuflow-mark-512.png" sizes="512x512">
      
    <!-- Bootstrap 5 (moderno) -->  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">  
    <!-- Font Awesome -->  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">  
    <!-- Quill Editor CSS -->  
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">  
    <!-- SweetAlert2 -->  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
      
    <style>  
        /* =========================================================
         *  Tema Light / Dark — UI moderna (consistente com as demais páginas)
         * =======================================================*/
        :root{
            --bg:#f6f7fb; --surface:#ffffff; --surface-2:#f1f3f9;
            --text:#0f172a; --muted:#6b7280; --border:#e5e7eb;
            --primary:#3b82f6; --primary-600:#2563eb; --accent:#22c55e;
            --warning:#f59e0b; --danger:#ef4444; --info:#06b6d4;
            --shadow:0 10px 30px rgba(2,6,23,.07); --radius:14px;
            --sidebar-width:260px; --topbar-h:64px;
        }
        html[data-theme="dark"]{
            --bg:#0b1220; --surface:#0f172a; --surface-2:#0b1220;
            --text:#e5e7eb; --muted:#9aa4b2; --border:#1f2937;
            --primary:#60a5fa; --primary-600:#3b82f6; --accent:#34d399;
            --warning:#fbbf24; --danger:#f87171; --info:#22d3ee;
            --shadow:0 12px 40px rgba(2,6,23,.45);
        }
        *{box-sizing:border-box}
        body{
            overflow-x:hidden;
            min-height:100vh;
            background: radial-gradient(1200px 800px at 10% -10%, rgba(59,130,246,.15), transparent 40%),
                        radial-gradient(1000px 700px at 110% 10%, rgba(34,211,238,.12), transparent 42%),
                        var(--bg);
            color:var(--text);
            padding-bottom:88px;
        }

        /* Topbar */
        .topbar{
            position:fixed; inset:0 0 auto 0; height:var(--topbar-h);
            background:linear-gradient(180deg, rgba(255,255,255,.6), rgba(255,255,255,.2));
            backdrop-filter:blur(10px); border-bottom:1px solid var(--border);
            display:flex; align-items:center; gap:12px; padding:0 16px; z-index:101;
        }
        html[data-theme="dark"] .topbar{ background:linear-gradient(180deg, rgba(15,23,42,.7), rgba(15,23,42,.35)); }
        .menu-toggle{ width:42px;height:42px;display:grid;place-items:center;border-radius:50%;
            cursor:pointer;color:var(--text);transition:.2s;border:1px solid var(--border);background:var(--surface); }
        .menu-toggle:hover{ transform:translateY(-1px); }
        .brand{ display:flex;align-items:center;gap:12px; }
        .brand .logo{ width:36px;height:36px;border-radius:10px;display:grid;place-items:center;color:#fff;
            background:linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%); box-shadow:var(--shadow); font-weight:700; }
        .brand h4{ margin:0; letter-spacing:.2px; }
        .topbar-actions{ margin-left:auto; display:flex; align-items:center; gap:8px; }
        .btn-ghost{ display:inline-flex; align-items:center; gap:8px; border:1px solid var(--border);
            background:var(--surface); padding:8px 12px; border-radius:999px; color:var(--text); }
        .avatar-btn, .theme-btn{ width:42px;height:42px;border-radius:50%;border:1px solid var(--border);
            background:var(--surface);display:grid;place-items:center;color:var(--text); }

        /* Sidebar */
        .sidebar{ position:fixed; top:var(--topbar-h); left:0; bottom:0; width:var(--sidebar-width);
            background:var(--surface); border-right:1px solid var(--border);
            padding:16px; overflow-y:auto; transition:margin-left .25s ease; z-index:100; }
        .sidebar.collapsed{ margin-left:calc(-1 * var(--sidebar-width)); }
        .nav-stacked .nav-link{ display:flex; align-items:center; gap:10px; color:var(--muted);
            border-radius:10px; padding:10px 12px; transition:.15s; }
        .nav-stacked .nav-link:hover{ background:var(--surface-2); color:var(--text); }
        .nav-stacked .nav-link.active{
            color:var(--text);
            background:linear-gradient(135deg, rgba(59,130,246,.12), rgba(34,197,94,.12));
            border:1px solid var(--border);
        }

        /* Main */
        .main-content{ margin-left:var(--sidebar-width); padding:calc(var(--topbar-h) + 24px) 28px 24px; transition:margin-left .25s ease; }
        .main-content.expanded{ margin-left:0; }
        .content-container{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); padding:24px; }
        .page-header .subtitle{ color:var(--muted); }

        /* Form */
        .form-label{ font-weight:600; }
        .form-control, .form-select, textarea{ border:1px solid var(--border); background:var(--surface); color:var(--text); border-radius:12px; }

        /* Steps */
        .step-card{ border:1px solid var(--border); border-radius:14px; margin-bottom:18px; background:var(--surface); box-shadow:var(--shadow); overflow:hidden; }
        .step-card .card-header{ display:flex; align-items:center; gap:12px; padding:14px 16px;
            background:linear-gradient(180deg, var(--surface-2), var(--surface)); border-bottom:1px solid var(--border); cursor:pointer; }
        .drag-handle{ color:var(--muted); cursor:move; }
        .step-number{ width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-weight:800;color:#fff;
            background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%); }
        .step-title{ margin:0; font-size:1.05rem; }
        .step-card .card-body{ padding:18px; }
        .step-card-placeholder{ border:2px dashed var(--border); border-radius:14px; background:var(--surface-2); margin-bottom:18px; }

        /* Cover Image */
        .cover-upload{ border:2px dashed var(--border); border-radius:14px; padding:20px; text-align:center; background:var(--surface-2);
            position:relative; cursor:pointer; height:220px; display:flex; align-items:center; justify-content:center; overflow:hidden; }
        .cover-upload input[type="file"]{ position:absolute; inset:0; opacity:0; cursor:pointer; }
        #coverPreview{ width:100%; height:100%; display:flex; align-items:center; justify-content:center; position:relative; }
        #coverPreview img{ max-width:100%; max-height:100%; object-fit:cover; border-radius:8px; }
        .remove-image{ position:absolute; top:10px; right:10px; width:34px; height:34px; border-radius:50%;
            background:rgba(255,255,255,.85); display:grid; place-items:center; color:#dc3545; cursor:pointer; box-shadow:var(--shadow); }
        html[data-theme="dark"] .remove-image{ background:rgba(15,23,42,.85); color:#f87171;}

        /* Image preview for steps */
        .image-preview{ padding:5px; border:1px solid var(--border); border-radius:8px; margin-top:10px; position:relative; width:100%; height:160px; background:var(--surface-2);
            display:flex; align-items:center; justify-content:center; overflow:hidden; }
        .image-preview img{ max-width:100%; max-height:100%; object-fit:contain; }

        /* Quill */
        .editor-container{ min-height:200px; margin-bottom:12px; }
        .ql-toolbar, .ql-container{ border-color:var(--border)!important; }
        .ql-container{ border-bottom-left-radius:10px; border-bottom-right-radius:10px; }
        .ql-toolbar{ border-top-left-radius:10px; border-top-right-radius:10px; background:var(--surface-2); }

        /* Helper */
        .clipboard-helper{ background:rgba(250,204,21,.15); border:1px solid #facc15; color:#854d0e;
            border-radius:12px; padding:.9rem 1rem; margin-bottom:20px; position:relative; }
        html[data-theme="dark"] .clipboard-helper{ background:rgba(250,204,21,.12); color:#fde68a; border-color:#eab308; }
        .clipboard-helper .close-helper{ position:absolute; top:10px; right:10px; cursor:pointer; }

        .paste-instruction{ position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); text-align:center; padding:14px;
            background:rgba(0,0,0,.06); border-radius:8px; display:none; pointer-events:none; animation:fadeInOut 2s ease-in-out; color:var(--text); }
        .cover-upload:hover .paste-instruction{ display:block; }
        @keyframes fadeInOut{ 0%{opacity:0;} 50%{opacity:1;} 100%{opacity:0;} }

        /* Action Bar fixa */
        .action-bar{ position:fixed; left:0; right:0; bottom:0; background:linear-gradient(0deg, rgba(0,0,0,.06), rgba(0,0,0,0)); z-index:102; }
        .action-inner{ background:var(--surface); border-top:1px solid var(--border); padding:12px 16px; }

        /* Responsivo */
        @media (max-width: 992px){
            .sidebar{ margin-left:calc(-1 * var(--sidebar-width)); }
            .sidebar.mobile-visible{ margin-left:0; }
            .main-content{ margin-left:0; padding:calc(var(--topbar-h) + 16px) 16px 16px; }
        }

        .btn-primary{ background:linear-gradient(135deg, var(--primary) 0%, var(--primary-600) 100%); border:none; }
        .btn-primary:hover{ filter:brightness(.95); }
        .ql-toolbar.ql-snow + .ql-container.ql-snow { min-height: 180px; }

        /* Logomarca se adapta ao tema */
        .brand-logo{ height:50px; display:block; }
        html[data-theme="dark"] .brand-logo{ filter: invert(1) hue-rotate(180deg) brightness(1.1); }

        /* Avatar com iniciais (estilo pill redondo) */
        .avatar-btn{
        width: 42px; height: 42px; border-radius: 50%;
        border: 1px solid var(--border); background: var(--surface);
        color: var(--text);
        }
        .avatar-initials{
        display:inline-flex; align-items:center; justify-content:center;
        width: 100%; height: 100%;
        font-weight: 700; letter-spacing: .5px; user-select:none;
        }

        /* Opcional: esconder texto do wordmark em telas bem pequenas (já controlado pelo <picture>) */
        @media (max-width: 575.98px){
        .brand h4{ display:none; }
        }

    </style>  
</head>  
<body>  
    <!-- Topbar -->  
    <!-- TOPBAR (ADMIN) -->
    <div class="topbar">
        <!-- Botão que abre/fecha a sidebar -->
        <button class="menu-toggle" id="menu-toggle" aria-label="Alternar menu">
            <i class="fa-solid fa-bars"></i>
        </button>

        <!-- Logomarca: wordmark no desktop, mark compacta no mobile -->
        <a href="manual-list.php" class="brand text-decoration-none" aria-label="Início ManuFlow">
            <picture>
                <!-- Desktop / md+ -->
                <source srcset="img/manuflow-wordmark.svg" media="(min-width: 576px)">
                <!-- Mobile: marca compacta -->
                <img src="img/manuflow-mark.svg"
                    alt="ManuFlow — Sistema de Manuais"
                    class="brand-logo"
                    height="28">
            </picture>
        </a>

        <div class="topbar-actions ms-auto d-flex align-items-center gap-2">
            <!-- Botão tema -->
            <button class="btn-ghost" id="themeToggle" type="button" aria-label="Alternar tema">
                <i class="fa-solid fa-moon me-1" id="themeIcon"></i>
                <span class="d-none d-md-inline" id="themeLabel">Dark</span>
            </button>

            <!-- Menu do usuário (admin) -->
            <!-- <div class="dropdown">
                <button class="avatar-btn d-inline-flex align-items-center justify-content-center"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="Abrir menu do usuário"> -->
                    <!-- Iniciais do usuário (fallback ao ícone) -->
                    <!-- <span class="avatar-initials">AD</span> -->
                    <!-- <i class="fa-solid fa-user"></i> -->
                <!-- </button>
                <ul class="dropdown-menu dropdown-menu-end" style="border-radius:12px;">
                    <li><h6 class="dropdown-header">Administrador</h6></li>
                    <li><a class="dropdown-item" href="profile.php"><i class="fa-solid fa-user me-2"></i>Perfil</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fa-solid fa-gear me-2"></i>Configurações</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Sair</a></li>
                </ul>
            </div> -->
        </div>
    </div>

    <!-- Sidebar -->  
    <aside class="sidebar" id="sidebar">  
        <nav class="nav flex-column nav-stacked">   
            <a class="nav-link active" href="manual-list.php"><i class="fa-solid fa-book"></i> Manuais</a>  
            <a class="nav-link" href="categories.php"><i class="fa-solid fa-tags"></i> Categorias</a>  
        </nav>  
    </aside>  

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
          
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3 page-header">  
            <div>  
                <h1 class="mb-0 fw-bold"><?php echo $id > 0 ? 'Editar Manual' : 'Novo Manual'; ?></h1>  
                <small class="subtitle">Preencha as informações, adicione passos e salve.</small>  
            </div>  
            <a href="manual-list.php" class="btn btn-ghost">  
                <i class="fa-solid fa-arrow-left me-2"></i> Voltar para a lista  
            </a>  
        </div>  
          
        <div class="content-container">  
            <form id="manualForm" action="save_manual.php" method="post" enctype="multipart/form-data">  
                <?php if ($id > 0): ?>  
                    <input type="hidden" name="id" value="<?php echo $id; ?>">  
                <?php endif; ?>  
                  
                <!-- Informações Básicas -->  
                <div class="row g-4 mb-2">  
                    <div class="col-lg-8">  
                        <div class="mb-3">  
                            <label for="titulo" class="form-label">Título do Manual</label>  
                            <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo isset($manual['titulo']) ? h($manual['titulo']) : ''; ?>" required>  
                        </div>  
                          
                        <div class="mb-3">  
                            <label for="descricao" class="form-label">Descrição</label>  
                            <textarea class="form-control" id="descricao" name="descricao" rows="4"><?php echo isset($manual['descricao']) ? h($manual['descricao']) : ''; ?></textarea>  
                        </div>  
                          
                        <div class="row g-3">  
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
                      
                    <div class="col-lg-4">  
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
                                    <div class="placeholder text-muted">  
                                        <i class="fas fa-image fa-3x mb-2 d-block"></i>  
                                        <p class="mb-0">Clique, cole (Ctrl+V) ou arraste uma imagem</p>  
                                    </div>  
                                <?php endif; ?>  
                            </div>  
                            <div class="paste-instruction">  
                                <i class="fas fa-paste me-2"></i> Pressione Ctrl+V para colar uma imagem da área de transferência  
                            </div>  
                        </div>  
                        <input type="hidden" name="imagem_capa_atual" value="<?php echo isset($manual['imagem_capa']) ? $manual['imagem_capa'] : ''; ?>">  
                        <input type="hidden" name="remover_capa" id="removerCapa" value="0">  
                        <div class="text-muted small">Tamanho recomendado: 1200x630px.</div>  
                    </div>  
                </div>  
                  
                <!-- Clipboard Paste Helper -->  
                <div class="clipboard-helper" id="clipboard-helper">  
                    <span class="close-helper" id="close-helper"><i class="fas fa-times"></i></span>  
                    <p class="mb-0"><i class="fas fa-lightbulb me-2"></i> <strong>Dica:</strong> Você pode colar imagens diretamente da área de transferência. Faça uma captura de tela e pressione <kbd>Ctrl+V</kbd> (ou <kbd>⌘+V</kbd> no Mac) quando estiver com foco em uma área de imagem.</p>  
                </div>  
                  
                <!-- Passos -->  
                <h4 class="mb-3 mt-2 fw-bold">Passos do Manual</h4>  
                  
                <div id="passos-container">  
                    <?php if (count($passos) > 0): ?>  
                        <?php foreach ($passos as $passo): ?>  
                            <div class="step-card" data-step="<?php echo (int)$passo['numero']; ?>">  
                                <div class="card-header" data-bs-toggle="collapse" data-bs-target="#step<?php echo (int)$passo['numero']; ?>">  
                                    <i class="fas fa-grip-lines drag-handle"></i>  
                                    <div class="step-number"><?php echo (int)$passo['numero']; ?></div>  
                                    <h5 class="step-title mb-0"><?php echo h($passo['titulo']); ?></h5>  
                                    <div class="ms-auto d-flex gap-2">  
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-step" title="Excluir passo">  
                                            <i class="fas fa-trash"></i>  
                                        </button>  
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#step<?php echo (int)$passo['numero']; ?>" title="Recolher/Expandir">  
                                            <i class="fas fa-chevron-up collapse-icon"></i>  
                                        </button>  
                                    </div>  
                                </div>  
                                <div class="collapse show" id="step<?php echo (int)$passo['numero']; ?>">  
                                    <div class="card-body">  
                                        <input type="hidden" name="passo_id[]" value="<?php echo (int)$passo['id']; ?>">  
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
                                        <div class="row g-3">  
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
                                <div class="ms-auto d-flex gap-2">  
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-step" title="Excluir passo">  
                                        <i class="fas fa-trash"></i>  
                                    </button>  
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#step1" title="Recolher/Expandir">  
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
                                    <div class="row g-3">  
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
                  
                <div class="text-center mt-3 mb-2">  
                    <button type="button" id="addStep" class="btn btn-outline-primary">  
                        <i class="fas fa-plus-circle me-2"></i> Adicionar Passo  
                    </button>  
                </div>  
            </form>  
        </div>  
    </div>  
      
    <!-- Fixed Action Bar -->  
    <div class="action-bar">  
        <div class="action-inner">  
            <div class="container d-flex align-items-center justify-content-between">  
                <div>  
                    <a href="manual-list.php" class="btn btn-outline-secondary">Cancelar</a>  
                </div>  
                <div class="d-flex gap-2">  
                    <button type="button" id="btnPrevisualizar" class="btn btn-outline-primary">Pré-visualizar</button>  
                    <button type="button" id="btnSalvar" class="btn btn-success">Salvar Manual</button>  
                </div>  
            </div>  
        </div>  
    </div>  
      
    <!-- jQuery -->  
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>  
    <!-- jQuery UI (for Sortable) -->  
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>  
    <!-- Bootstrap JS Bundle -->  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>  
    <!-- Quill Editor -->  
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>  
      
    <script>  
        (function(){  
            /* -------- Tema (Dark / Light) -------- */  
            const htmlEl = document.documentElement;  
            const themeToggle = document.getElementById('themeToggle');  
            const themeIcon   = document.getElementById('themeIcon');  
            const themeLabel  = document.getElementById('themeLabel');  

            function applyTheme(mode){  
                htmlEl.setAttribute('data-theme', mode);  
                if (mode === 'dark') {  
                    themeIcon.classList.remove('fa-moon'); themeIcon.classList.add('fa-sun');  
                    if (themeLabel) themeLabel.textContent = 'Light';  
                } else {  
                    themeIcon.classList.remove('fa-sun'); themeIcon.classList.add('fa-moon');  
                    if (themeLabel) themeLabel.textContent = 'Dark';  
                }  
                localStorage.setItem('theme', mode);  
            }  
            const saved = localStorage.getItem('theme');  
            if (saved) applyTheme(saved);  
            else {  
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;  
                applyTheme(prefersDark ? 'dark' : 'light');  
            }  
            themeToggle.addEventListener('click', () => {  
                const current = htmlEl.getAttribute('data-theme');  
                applyTheme(current === 'dark' ? 'light' : 'dark');  
            });  

            /* -------- Sidebar Toggle -------- */  
            const sidebar = document.getElementById('sidebar');  
            const main    = document.getElementById('main-content');  
            const menuBtn = document.getElementById('menu-toggle');  

            function checkSize(){  
                if (window.innerWidth < 992) {  
                    sidebar.classList.add('collapsed');  
                    main.classList.add('expanded');  
                } else {  
                    sidebar.classList.remove('collapsed','mobile-visible');  
                    main.classList.remove('expanded');  
                }  
            }  
            window.addEventListener('resize', checkSize);  
            checkSize();  

            menuBtn.addEventListener('click', () => {  
                if (window.innerWidth < 992) {  
                    sidebar.classList.toggle('mobile-visible');  
                } else {  
                    sidebar.classList.toggle('collapsed');  
                    main.classList.toggle('expanded');  
                }  
            });  

            /* -------- Inicializar Quill ---------- */  
            const toolbarOptions = [  
                ['bold', 'italic', 'underline', 'strike'],  
                ['blockquote', 'code-block'],  
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],  
                [{ 'indent': '-1'}, { 'indent': '+1' }],  
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],  
                [{ 'color': [] }, { 'background': [] }],  
                ['clean']  
            ];  

            // Inicializar editores existentes  
            $('.editor').each(function() {  
                const quill = new Quill(this, {  
                    theme: 'snow',  
                    modules: { toolbar: toolbarOptions }  
                });  
                quill.on('text-change', function() {  
                    const html = $(quill.root).html();  
                    $(quill.root).closest('.card-body').find('.editor-content-hidden').val(html);  
                });  
                // Conteúdo inicial -> textarea  
                const html = $(quill.root).html();  
                $(quill.root).closest('.card-body').find('.editor-content-hidden').val(html);  
                setupPasteHandler(quill);  
            });  

            // Handler de colagem para inserir imagem no passo  
            function setupPasteHandler(quill) {  
                quill.root.addEventListener('paste', function(e) {  
                    if (e.clipboardData && e.clipboardData.items) {  
                        const items = e.clipboardData.items;  
                        for (let i = 0; i < items.length; i++) {  
                            if (items[i].type.indexOf('image') !== -1) {  
                                e.preventDefault();  
                                e.stopPropagation();  
                                const file = items[i].getAsFile();  
                                const stepCard = $(quill.root).closest('.step-card');  
                                const reader = new FileReader();  
                                reader.onload = function(event) {  
                                    const imagePreview = stepCard.find('.image-preview');  
                                    imagePreview.find('img').attr('src', event.target.result);  
                                    imagePreview.show();  
                                    const pastedFile = new File([file], 'pasted-image-' + Date.now() + '.png', {type: file.type});  
                                    const dataTransfer = new DataTransfer();  
                                    dataTransfer.items.add(pastedFile);  
                                    const fileInput = stepCard.find('.step-image-input')[0];  
                                    fileInput.files = dataTransfer.files;  
                                    $(fileInput).trigger('change');  
                                };  
                                reader.readAsDataURL(file);  
                                break;  
                            }  
                        }  
                    }  
                });  
            }  

            // Capa: clique direciona ao input  
            $('#coverUploadArea').on('click', function() {  
                $('#imagemCapa').click();  
            });  

            // Colagem global para capa (quando não estiver em editor)  
            document.addEventListener('paste', function(e) {  
                const activeElement = document.activeElement;  
                const isInQuill = $(activeElement).closest('.ql-editor').length > 0;  
                if (isInQuill) return;  
                if (e.clipboardData && e.clipboardData.items) {  
                    const items = e.clipboardData.items;  
                    for (let i = 0; i < items.length; i++) {  
                        if (items[i].type.indexOf('image') !== -1) {  
                            e.preventDefault();  
                            const file = items[i].getAsFile();  
                            Swal.fire({  
                                title: 'Imagem colada!',  
                                text: 'Processando imagem da área de transferência...',  
                                icon: 'info',  
                                showConfirmButton: false,  
                                timer: 1200  
                            });  
                            const reader = new FileReader();  
                            reader.onload = function(event) {  
                                $('#coverPreview').html(`  
                                    <img src="${event.target.result}" alt="Capa do Manual">  
                                    <span class="remove-image" id="removeCover" title="Remover imagem">  
                                        <i class="fas fa-times"></i>  
                                    </span>  
                                `);  
                                const pastedFile = new File([file], 'cover-image-' + Date.now() + '.png', {type: file.type});  
                                const dataTransfer = new DataTransfer();  
                                dataTransfer.items.add(pastedFile);  
                                document.getElementById('imagemCapa').files = dataTransfer.files;  
                                $('#removerCapa').val('0');  
                            };  
                            reader.readAsDataURL(file);  
                            break;  
                        }  
                    }  
                }  
            });  

            // Fechar helper  
            $('#close-helper').on('click', function() {  
                $('#clipboard-helper').slideUp();  
                localStorage.setItem('clipboard_helper_closed', 'true');  
            });  
            if(localStorage.getItem('clipboard_helper_closed') === 'true') {  
                $('#clipboard-helper').hide();  
            }  

            // Adicionar novo passo  
            $('#addStep').on('click', function() {  
                const stepCount = $('.step-card').length;  
                const newStep = stepCount + 1;  
                const stepCard = `  
                    <div class="step-card" data-step="${newStep}">  
                        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#step${newStep}">  
                            <i class="fas fa-grip-lines drag-handle"></i>  
                            <div class="step-number">${newStep}</div>  
                            <h5 class="step-title mb-0">Novo Passo</h5>  
                            <div class="ms-auto d-flex gap-2">  
                                <button type="button" class="btn btn-sm btn-outline-danger remove-step" title="Excluir passo">  
                                    <i class="fas fa-trash"></i>  
                                </button>  
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#step${newStep}" title="Recolher/Expandir">  
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
                                <div class="row g-3">  
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
                    </div>`;  
                  
                $('#passos-container').append(stepCard);  
                  
                const quill = new Quill(`.editor-${newStep}`, { theme: 'snow', modules: { toolbar: toolbarOptions } });  
                quill.on('text-change', function() {  
                    const html = $(quill.root).html();  
                    $(quill.root).closest('.card-body').find('.editor-content-hidden').val(html);  
                });  
                setupPasteHandler(quill);  
                  
                $('html, body').animate({ scrollTop: $(`#step${newStep}`).offset().top - 100 }, 500);  
                initStepEvents();  
                updateStepNumbers();  
            });  

            // Eventos dos passos  
            function initStepEvents(){  
                $('.remove-step').off('click').on('click', function(e){  
                    e.stopPropagation();  
                    const stepCard = $(this).closest('.step-card');  
                    const stepNumber = stepCard.data('step');  
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
                            stepCard.fadeOut(250, function(){  
                                $(this).remove();  
                                updateStepNumbers();  
                            });  
                        }  
                    });  
                });  

                $('.step-title-input').off('input').on('input', function(){  
                    const title = $(this).val();  
                    $(this).closest('.step-card').find('.step-title').text(title || 'Novo Passo');  
                });  

                $('.step-image-input').off('change').on('change', function(){  
                    if (this.files && this.files[0]) {  
                        const reader = new FileReader();  
                        const preview = $(this).closest('.card-body').find('.image-preview');  
                        reader.onload = function(e){  
                            preview.find('img').attr('src', e.target.result);  
                            preview.show();  
                        }  
                        reader.readAsDataURL(this.files[0]);  
                    }  
                });  

                $('.image-preview .remove-image').off('click').on('click', function(e){  
                    e.stopPropagation();  
                    const preview = $(this).closest('.image-preview');  
                    const stepCard = $(this).closest('.step-card');  
                    const fileInput = stepCard.find('.step-image-input');  
                    const currentImageInput = stepCard.find('input[name="passo_imagem_atual[]"]');  
                    const removeImageInput = stepCard.find('input[name="remover_imagem_passo[]"]');  
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
                            fileInput.val('');  
                            if (currentImageInput.val()) removeImageInput.val('1');  
                            preview.hide();  
                        }  
                    });  
                });  

                $('.card-header').off('click').on('click', function(){  
                    $(this).find('.collapse-icon').toggleClass('rotate-180');  
                });  
            }  
            initStepEvents();  

            // Sortable  
            $('#passos-container').sortable({  
                handle: '.drag-handle',  
                placeholder: 'step-card-placeholder',  
                tolerance: 'pointer',  
                start: function(e, ui){ ui.placeholder.height(ui.item.outerHeight()); },  
                stop: function(){ updateStepNumbers(); }  
            });  

            function updateStepNumbers(){  
                $('.step-card').each(function(index){  
                    const newStep = index + 1;  
                    $(this).attr('data-step', newStep);  
                    $(this).find('.step-number').text(newStep);  
                    const collapseButton = $(this).find('.card-header');  
                    const collapseContent = $(this).find('.collapse');  
                    collapseButton.attr('data-bs-target', `#step${newStep}`);  
                    collapseContent.attr('id', `step${newStep}`);  
                });  
            }  

            // Upload normal da capa  
            $('#imagemCapa').on('change', function(){  
                if (this.files && this.files[0]) {  
                    const reader = new FileReader();  
                    reader.onload = function(e){  
                        $('#coverPreview').html(`  
                            <img src="${e.target.result}" alt="Capa do Manual">  
                            <span class="remove-image" id="removeCover" title="Remover imagem">  
                                <i class="fas fa-times"></i>  
                            </span>`);  
                        $('#removerCapa').val('0');  
                    }  
                    reader.readAsDataURL(this.files[0]);  
                }  
            });  

            // Remover capa  
            $(document).on('click', '#removeCover', function(e){  
                e.stopPropagation(); e.preventDefault();  
                Swal.fire({  
                    title: 'Remover imagem de capa?',  
                    text: 'Tem certeza que deseja remover a imagem de capa?',  
                    icon: 'warning',  
                    showCancelButton: true,  
                    confirmButtonColor: '#dc3545',  
                    cancelButtonColor: '#6c757d',  
                    confirmButtonText: 'Sim, remover!',  
                    cancelButtonText: 'Cancelar'  
                }).then((result)=>{  
                    if (result.isConfirmed){  
                        $('#imagemCapa').val('');  
                        $('#removerCapa').val('1');  
                        $('#coverPreview').html(`<div class="placeholder text-muted">
                            <i class="fas fa-image fa-3x mb-2 d-block"></i>
                            <p class="mb-0">Clique, cole (Ctrl+V) ou arraste uma imagem</p>
                        </div>`);  
                    }  
                });  
            });  

            // Drag & drop na capa  
            $('#coverUploadArea').on('dragover', function(e){  
                e.preventDefault(); e.stopPropagation();  
                $(this).addClass('border-primary');  
            }).on('dragleave', function(e){  
                e.preventDefault(); e.stopPropagation();  
                $(this).removeClass('border-primary');  
            }).on('drop', function(e){  
                e.preventDefault(); e.stopPropagation();  
                $(this).removeClass('border-primary');  
                const files = e.originalEvent.dataTransfer.files;  
                if (files.length > 0 && files[0].type.match('image.*')) {  
                    const reader = new FileReader();  
                    reader.onload = function(ev){  
                        $('#coverPreview').html(`  
                            <img src="${ev.target.result}" alt="Capa do Manual">  
                            <span class="remove-image" id="removeCover" title="Remover imagem">  
                                <i class="fas fa-times"></i>  
                            </span>`);  
                        $('#removerCapa').val('0');  
                    };  
                    reader.readAsDataURL(files[0]);  
                    const dt = new DataTransfer();  
                    dt.items.add(files[0]);  
                    document.getElementById('imagemCapa').files = dt.files;  
                }  
            });  

            // Salvar manual  
            $('#btnSalvar').on('click', function(){  
                const titulo = $('#titulo').val().trim();  
                if (!titulo){  
                    Swal.fire({ title:'Atenção!', text:'O título do manual é obrigatório.', icon:'warning' });  
                    return;  
                }  
                if ($('.step-card').length === 0){  
                    Swal.fire({ title:'Atenção!', text:'É necessário ter pelo menos um passo no manual.', icon:'warning' });  
                    return;  
                }  
                let passosSemTitulo = false;  
                $('.step-card').each(function(){  
                    const t = $(this).find('input[name="passo_titulo[]"]');  
                    if (!t.val().trim()){ passosSemTitulo = true; t.addClass('is-invalid'); } else { t.removeClass('is-invalid'); }  
                });  
                if (passosSemTitulo){  
                    Swal.fire({ title:'Atenção!', text:'Todos os passos precisam ter um título.', icon:'warning' });  
                    return;  
                }  
                Swal.fire({ title:'Salvando...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });  
                $('#manualForm').submit();  
            });  

            // Pré-visualização  
            $('#btnPrevisualizar').on('click', function(){  
                const manualId = $('input[name="id"]').val();  
                if (manualId){  
                    window.open(`view-manual.php?id=${manualId}&preview=1`, '_blank');  
                } else {  
                    Swal.fire({ title:'Atenção', text:'Salve o manual primeiro para poder previsualizá-lo.', icon:'info' });  
                }  
            });  

            // Dica Toast ao focar na área da capa  
            $('#coverUploadArea').on('focus click', function(){  
                const tpl = `
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
                    </div>`;
                $('.toast-container').remove();  
                $('body').append(tpl);  
                setTimeout(function(){  
                    $('.toast').toast('hide');  
                    setTimeout(function(){ $('.toast-container').remove(); }, 500);  
                }, 3000);  
            });  
        })();  
    </script>  
</body>  
</html>
