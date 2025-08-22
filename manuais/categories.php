<?php
// Iniciar sessão  
session_start();  

// Verificar se o usuário está logado  
if (!isset($_SESSION['username'])) {  
    header('Location: ../login.php');  
    exit;  
}  

// Verificar se o usuário tem permissão (nível de acesso) para gerenciar categorias  
$allowed_roles = ['administrador', 'usuario'];  
if (!isset($_SESSION['nivel_de_acesso']) || !in_array($_SESSION['nivel_de_acesso'], $allowed_roles)) {  
    die("Acesso negado. Você não tem permissão para gerenciar categorias.");  
}  

// Incluir arquivo de conexão com o banco de dados  
require_once 'conexao_bd.php';  

// Função para sanitizar input  
function sanitize($input) {  
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');  
}  

// Processar requisições AJAX  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {  
    header('Content-Type: application/json');  
    $response = ['success' => false, 'message' => ''];  
      
    switch ($_POST['action']) {  
        case 'create':  
            if (empty($_POST['nome'])) {  
                $response['message'] = 'O nome da categoria é obrigatório.';  
                echo json_encode($response);  
                exit;  
            }  
            $nome = sanitize($_POST['nome']);  
            $descricao = isset($_POST['descricao']) ? sanitize($_POST['descricao']) : '';  
            try {  
                $stmt = $conexao->prepare("SELECT id FROM categorias WHERE nome = ?");  
                $stmt->execute([$nome]);  
                if ($stmt->fetch()) {  
                    $response['message'] = 'Já existe uma categoria com este nome.';  
                    echo json_encode($response);  
                    exit;  
                }  
                $stmt = $conexao->prepare("INSERT INTO categorias (nome, descricao, data_criacao) VALUES (?, ?, NOW())");  
                $stmt->execute([$nome, $descricao]);  
                $response['success'] = true;  
                $response['message'] = 'Categoria criada com sucesso.';  
                $response['id'] = $conexao->lastInsertId();  
            } catch (PDOException $e) {  
                $response['message'] = 'Erro ao criar categoria: ' . $e->getMessage();  
            }  
            break;  
        case 'update':  
            if (empty($_POST['id']) || empty($_POST['nome'])) {  
                $response['message'] = 'ID e nome da categoria são obrigatórios.';  
                echo json_encode($response);  
                exit;  
            }  
            $id = (int)$_POST['id'];  
            $nome = sanitize($_POST['nome']);  
            $descricao = isset($_POST['descricao']) ? sanitize($_POST['descricao']) : '';  
            try {  
                $stmt = $conexao->prepare("SELECT id FROM categorias WHERE id = ?");  
                $stmt->execute([$id]);  
                if (!$stmt->fetch()) {  
                    $response['message'] = 'Categoria não encontrada.';  
                    echo json_encode($response);  
                    exit;  
                }  
                $stmt = $conexao->prepare("SELECT id FROM categorias WHERE nome = ? AND id != ?");  
                $stmt->execute([$nome, $id]);  
                if ($stmt->fetch()) {  
                    $response['message'] = 'Já existe outra categoria com este nome.';  
                    echo json_encode($response);  
                    exit;  
                }  
                $stmt = $conexao->prepare("UPDATE categorias SET nome = ?, descricao = ?, data_atualizacao = NOW() WHERE id = ?");  
                $stmt->execute([$nome, $descricao, $id]);  
                $response['success'] = true;  
                $response['message'] = 'Categoria atualizada com sucesso.';  
            } catch (PDOException $e) {  
                $response['message'] = 'Erro ao atualizar categoria: ' . $e->getMessage();  
            }  
            break;  
        case 'delete':  
            if (empty($_POST['id'])) {  
                $response['message'] = 'ID da categoria é obrigatório.';  
                echo json_encode($response);  
                exit;  
            }  
            $id = (int)$_POST['id'];  
            try {  
                $stmt = $conexao->prepare("SELECT id FROM categorias WHERE id = ?");  
                $stmt->execute([$id]);  
                if (!$stmt->fetch()) {  
                    $response['message'] = 'Categoria não encontrada.';  
                    echo json_encode($response);  
                    exit;  
                }  
                $stmt = $conexao->prepare("SELECT COUNT(*) FROM manuais WHERE categoria_id = ?");  
                $stmt->execute([$id]);  
                $count = (int)$stmt->fetchColumn();  
                if ($count > 0) {  
                    $response['message'] = 'Esta categoria possui ' . $count . ' manuais associados. Não é possível excluí-la.';  
                    echo json_encode($response);  
                    exit;  
                }  
                $stmt = $conexao->prepare("DELETE FROM categorias WHERE id = ?");  
                $stmt->execute([$id]);  
                $response['success'] = true;  
                $response['message'] = 'Categoria excluída com sucesso.';  
            } catch (PDOException $e) {  
                $response['message'] = 'Erro ao excluir categoria: ' . $e->getMessage();  
            }  
            break;  
        default:  
            $response['message'] = 'Ação inválida.';  
            break;  
    }  
    echo json_encode($response);  
    exit;  
}  

// Verificar se a tabela de categorias existe, senão criá-la  
try {  
    $conexao->query("SELECT 1 FROM categorias LIMIT 1");  
} catch (PDOException $e) {  
    $sql = "CREATE TABLE IF NOT EXISTS categorias (  
        id INT AUTO_INCREMENT PRIMARY KEY,  
        nome VARCHAR(100) NOT NULL UNIQUE,  
        descricao TEXT,  
        data_criacao DATETIME NOT NULL,  
        data_atualizacao DATETIME NULL  
    )";  
    try {  
        $conexao->exec($sql);  
    } catch (PDOException $e) {  
        die("Erro ao criar tabela de categorias: " . $e->getMessage());  
    }  
}  

// Buscar todas as categorias  
try {  
    $stmt = $conexao->query("SELECT * FROM categorias ORDER BY nome");  
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);  
} catch (PDOException $e) {  
    die("Erro ao buscar categorias: " . $e->getMessage());  
}  
?>  

<!DOCTYPE html>  
<html lang="pt-BR" data-theme="light">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Gerenciamento de Categorias</title>  

        
    <link rel="icon" href="img/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="img/manuflow-mark-32.png" sizes="32x32">
    <link rel="apple-touch-icon" href="img/manuflow-mark-180.png" sizes="180x180">
    <link rel="icon" type="image/png" href="img/manuflow-mark-512.png" sizes="512x512">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <!-- DataTables Responsive -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* =========================================================
         *  Tema Light / Dark — UI moderna
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
            background: radial-gradient(1200px 800px at 10% -10%, rgba(59,130,246,.15), transparent 40%),
                        radial-gradient(1000px 700px at 110% 10%, rgba(34,211,238,.12), transparent 42%),
                        var(--bg);
            color:var(--text); min-height:100vh; overflow-x:hidden; padding-bottom:24px;
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
        .avatar-btn{ width:42px;height:42px;border-radius:50%;border:1px solid var(--border);
            background:var(--surface);display:grid;place-items:center;color:var(--text); }

        /* Sidebar */
        .sidebar{ position:fixed; top:var(--topbar-h); left:0; bottom:0; width:var(--sidebar-width);
            background:var(--surface); border-right:1px solid var(--border);
            padding:16px; overflow-y:auto; transition:margin-left .25s ease; z-index:100; }
        .sidebar.collapsed{ margin-left:calc(-1 * var(--sidebar-width)); }

        /* NOVO: no mobile, ao abrir (mobile-visible) o sidebar deve aparecer */
        .sidebar.mobile-visible{
            margin-left: 0 !important;
            box-shadow: 0 20px 40px rgba(2,6,23,.25);
        }

        /* NOVO: backdrop para o mobile */
        .sidebar-backdrop{
            display:none;
            position:fixed;
            top: var(--topbar-h);
            left:0; right:0; bottom:0;
            background: rgba(15,23,42,.4);
            z-index: 99; /* abaixo do sidebar (100) e da topbar (101) */
        }
        .sidebar-backdrop.show{ display:block; }

        .nav-stacked .nav-link{ display:flex; align-items:center; gap:10px; color:var(--muted);
            border-radius:10px; padding:10px 12px; transition:.15s; }
        .nav-stacked .nav-link:hover{ background:var(--surface-2); color:var(--text); }
        .nav-stacked .nav-link.active{
            color:var(--text);
            background:linear-gradient(135deg, rgba(59,130,246,.12), rgba(34,197,94,.12));
            border:1px solid var(--border);
        }


        /* Main */
        .main{ margin-left:var(--sidebar-width); padding:calc(var(--topbar-h) + 24px) 28px 24px; transition:margin-left .25s ease; }
        .main.expanded{ margin-left:0; }
        .panel{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); padding:22px; }

        /* Header + CTA */
        .page-header .subtitle{ color:var(--muted); }

        /* Table wrap */
        .table-wrap{ background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:12px; }

        /* Tabela base + DataTables */
        table.dataTable thead th{
            white-space:nowrap; font-weight:700; border-bottom:1px solid var(--border);
            background:linear-gradient(180deg, var(--surface-2), var(--surface));
            color:var(--text);
        }
        table.dataTable tbody td{ vertical-align:middle; border-top:1px solid var(--border); color:var(--text); }
        .actions .btn{ width:36px; height:36px; border-radius:10px; display:inline-grid; place-items:center; margin-right:6px; }
        .actions .btn:last-child{ margin-right:0; }

        /* Ajustes Bootstrap Table via CSS vars (faz a tabela respeitar os temas) */
        .table{
            --bs-table-color: var(--text);
            --bs-table-bg: var(--surface);
            --bs-table-border-color: var(--border);
            --bs-table-striped-bg: rgba(2,6,23,.04);
            --bs-table-striped-color: var(--text);
            --bs-table-hover-bg: rgba(2,6,23,.06);
            --bs-table-hover-color: var(--text);
        }
        html[data-theme="dark"] .table{
            --bs-table-striped-bg: rgba(255,255,255,.03);
            --bs-table-hover-bg: rgba(255,255,255,.06);
        }

        /* DataTables: length, search, info, paginate, processing */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate{
            color: var(--text);
        }
        .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_length label{
            color: var(--text);
        }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select{
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 6px 10px;
            outline: none;
        }
        .dataTables_wrapper .dataTables_filter input::placeholder{ color: var(--muted); opacity:.9; }

        /* Paginação (suporta tanto paginação do DT quanto do Bootstrap5) */
        .dataTables_wrapper .dataTables_paginate .paginate_button{
            border:1px solid var(--border) !important;
            background: var(--surface) !important;
            color: var(--text) !important;
            border-radius:10px !important;
            margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover{
            background: var(--surface-2) !important;
            color: var(--text) !important;
            border-color: var(--border) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover{
            background: linear-gradient(135deg, var(--primary), var(--primary-600)) !important;
            color:#fff !important;
            border-color: var(--primary-600) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover{
            color: var(--muted) !important;
            background: var(--surface) !important;
            border-color: var(--border) !important;
        }
        /* Caso o plugin use page-link/page-item do Bootstrap */
        .dataTables_wrapper .pagination .page-link{
            background: var(--surface);
            color: var(--text);
            border:1px solid var(--border);
        }
        .dataTables_wrapper .pagination .page-link:hover{
            background: var(--surface-2);
            color: var(--text);
        }
        .dataTables_wrapper .pagination .page-item.active .page-link{
            background: linear-gradient(135deg, var(--primary), var(--primary-600));
            color:#fff;
            border-color: var(--primary-600);
        }

        .dataTables_wrapper .dataTables_processing{
            background: var(--surface);
            color: var(--text);
            border:1px solid var(--border);
            box-shadow: var(--shadow);
            border-radius: 10px;
        }

        /* Cards (mobile) */
        #cards-container{ margin-top:12px; }
        .cards-grid{
            display:grid;
            grid-template-columns: 1fr;
            gap:12px;
        }
        .category-card{
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:16px;
            box-shadow:var(--shadow);
            padding:16px;
            color: var(--text);
        }
        .category-card .title{ font-weight:700; margin-bottom:6px; }
        .category-card .muted{ color:var(--muted); font-size:.925rem; }
        .category-card .badge-id{
            font-size:.75rem; border:1px solid var(--border); padding:2px 8px; border-radius:999px; margin-right:8px;
            background:var(--surface-2);
        }
        .category-card .btn{ border-radius:10px; }

        /* Mostrar Tabela no desktop, Cards no mobile */
        @media (min-width: 992px){
            .show-desktop{ display:block !important; }
            .show-mobile{ display:none !important; }
        }
        @media (max-width: 991.98px){
            .show-desktop{ display:none !important; }
            .show-mobile{ display:block !important; }
        }

        .btn-primary{ background:linear-gradient(135deg, var(--primary) 0%, var(--primary-600) 100%); border:none; }
        .btn-primary:hover{ filter:brightness(.95); }

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
            <a class="nav-link" href="manual-list.php"><i class="fa-solid fa-book"></i> Manuais</a>
            <a class="nav-link active" href="categories.php"><i class="fa-solid fa-tags"></i> Categorias</a>
        </nav>
    </aside>

    <!-- Main -->
    <main class="main" id="main-content">
        <div class="panel mb-3 page-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h1 class="mb-0 fw-bold">Gerenciamento de Categorias</h1>
                <small class="subtitle">Crie, edite e exclua categorias usadas pelos manuais.</small>
            </div>
            <button type="button" class="btn btn-primary" id="btn-new-category">
                <i class="fa-solid fa-plus me-2"></i> Nova Categoria
            </button>
        </div>

        <!-- Tabela (desktop) -->
        <div class="table-wrap show-desktop">
            <div class="table-responsive">
                <table id="tbl-categorias" class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Data de Criação</th>
                            <th>Última Atualização</th>
                            <th class="text-center" style="width:120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="categories-table-body">
                        <?php if (empty($categorias)): ?>
                            <!-- Tbody intencionalmente vazio; DataTables mostrará 'emptyTable' -->
                        <?php else: ?>
                            <?php foreach ($categorias as $categoria): ?>
                                <tr data-id="<?= (int)$categoria['id'] ?>">
                                    <td><?= (int)$categoria['id'] ?></td>
                                    <td><?= sanitize($categoria['nome']) ?></td>
                                    <td><?= sanitize($categoria['descricao'] ?? '') ?></td>
                                    <td data-order="<?= date('Y-m-d H:i:s', strtotime($categoria['data_criacao'])) ?>">
                                        <?= date('d/m/Y H:i', strtotime($categoria['data_criacao'])) ?>
                                    </td>
                                    <td data-order="<?= $categoria['data_atualizacao'] ? date('Y-m-d H:i:s', strtotime($categoria['data_atualizacao'])) : '' ?>">
                                        <?= $categoria['data_atualizacao'] ? date('d/m/Y H:i', strtotime($categoria['data_atualizacao'])) : '-' ?>
                                    </td>
                                    <td class="actions text-center">
                                        <button type="button" class="btn btn-outline-info btn-sm btn-edit" data-id="<?= (int)$categoria['id'] ?>" title="Editar">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-delete" data-id="<?= (int)$categoria['id'] ?>" title="Excluir">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Data de Criação</th>
                            <th>Última Atualização</th>
                            <th>Ações</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Cards (mobile) -->
        <section id="cards-container" class="show-mobile">
            <div class="cards-grid" id="cards-grid"></div>
        </section>
    </main>

    <!-- Modal Criar/Editar Categoria -->
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="categoryModalLabel">Nova Categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form id="categoryForm">
                        <input type="hidden" id="category-id" name="id" value="">
                        <input type="hidden" id="category-action" name="action" value="create">
                        <div class="mb-3">
                            <label for="category-name" class="form-label">Nome da Categoria *</label>
                            <input type="text" class="form-control" id="category-name" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="category-description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="category-description" name="descricao" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-save-category">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery (único) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap Bundle 5 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Responsive -->
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
        window.addEventListener('resize', () => {
            checkSize();
            renderCardsFromTable(); // re-render cards ao mudar o tamanho
        });
        checkSize();

        menuBtn.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                sidebar.classList.toggle('mobile-visible');
            } else {
                sidebar.classList.toggle('collapsed');
                main.classList.toggle('expanded');
            }
        });

        /* -------- SweetAlert helpers -------- */
        function swalAlert(type, message, timer=1700){
            Swal.fire({
                icon: type,
                title: (type === 'success') ? 'Sucesso' : 'Ops...',
                text: message,
                timer: timer,
                showConfirmButton: false
            });
        }
        function swalConfirmDelete(){
            return Swal.fire({
                icon: 'warning',
                title: 'Confirmar exclusão',
                text: 'Tem certeza que deseja excluir esta categoria? Esta ação não pode ser desfeita.',
                showCancelButton: true,
                confirmButtonText: 'Excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444'
            });
        }

        /* -------- Tradução DataTables (inline, evita CORS) -------- */
        const DT_LANG_PTBR = {
            "decimal": ",",
            "thousands": ".",
            "processing": "Processando...",
            "lengthMenu": "Mostrar _MENU_ registros",
            "zeroRecords": "Nenhum registro encontrado",
            "emptyTable": "Nenhuma categoria encontrada.",
            "info": "Mostrando _START_ até _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 até 0 de 0 registros",
            "infoFiltered": "(filtrado de _MAX_ registros no total)",
            "search": "Buscar:",
            "loadingRecords": "Carregando...",
            "paginate": {
                "first": "Primeiro",
                "last": "Último",
                "next": "Próximo",
                "previous": "Anterior"
            },
            "aria": {
                "sortAscending":  ": ativar para ordenar a coluna em ordem crescente",
                "sortDescending": ": ativar para ordenar a coluna em ordem decrescente"
            },
            "select": {
                "rows": {
                    "_": "%d linhas selecionadas",
                    "0": "Nenhuma linha selecionada",
                    "1": "1 linha selecionada"
                }
            }
        };

        /* -------- DataTables (init/reinit seguro) -------- */
        let dt = null;

        function getDTOptions(){
            return {
                language: DT_LANG_PTBR,
                responsive: true,
                order: [[1, 'asc']],
                columnDefs: [
                    { targets: [5], orderable: false }
                ]
            };
        }

        function initDataTable(){
            dt = $('#tbl-categorias').DataTable(getDTOptions());
            // Render cards sempre que a tabela desenhar (respeita busca/ordem)
            dt.on('draw', renderCardsFromTable);
            renderCardsFromTable();
        }

        function reinitDataTableWithHTML(newTbodyHTML){
            if (dt) {
                dt.off('draw', renderCardsFromTable);
                dt.destroy();
            }
            $('#tbl-categorias tbody').html(newTbodyHTML || '');
            initDataTable();
        }

        // Inicializa na primeira carga
        initDataTable();

        /* -------- Construção dos cards (mobile) -------- */
        function renderCardsFromTable(){
            const grid = document.getElementById('cards-grid');
            if (!grid) return;
            grid.innerHTML = '';

            if (!dt) return;
            // percorre as linhas visíveis dado o filtro
            dt.rows({ search: 'applied' }).every(function(){
                // Lê o texto direto do DOM da linha para evitar [object Object]
                const rowNode = this.node();
                const cells = rowNode.querySelectorAll('td');

                const idText          = (cells[0]?.textContent || '').trim();
                const nomeText        = (cells[1]?.textContent || '').trim();
                const descText        = (cells[2]?.textContent || '').trim();
                const dataCriacao     = (cells[3]?.textContent || '').trim();
                const dataAtualizacao = (cells[4]?.textContent || '').trim();

                const id = idText.replace(/[^\d]/g,'') || idText;

                const card = document.createElement('div');
                card.className = 'category-card';

                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="badge-id">#${id}</span>
                            <span class="title">${nomeText || '(Sem nome)'}</span>
                        </div>
                    </div>
                    <div class="mt-2 muted">${descText ? descText : '— sem descrição —'}</div>
                    <div class="mt-3 muted">
                        <div><i class="fa-regular fa-calendar-plus me-2"></i><strong>Criado:</strong> ${dataCriacao || '-'}</div>
                        <div><i class="fa-regular fa-clock me-2"></i><strong>Atualizado:</strong> ${dataAtualizacao || '-'}</div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="button" class="btn btn-outline-info btn-sm btn-edit"
                                data-id="${id}" data-nome="${encodeURIComponent(nomeText)}" data-descricao="${encodeURIComponent(descText)}">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-delete" data-id="${id}">
                            <i class="fa-solid fa-trash me-1"></i> Excluir
                        </button>
                    </div>
                `;
                grid.appendChild(card);
            });

            if (!grid.children.length){
                const empty = document.createElement('div');
                empty.className = 'category-card';
                empty.innerHTML = `<div class="muted">Nenhuma categoria encontrada.</div>`;
                grid.appendChild(empty);
            }
        }

        /* -------- Handlers (tabela e cards) -------- */
        function openEditModal(id, nome, descricao){
            $('#category-id').val(id || '');
            $('#category-action').val(id ? 'update' : 'create');
            $('#category-name').val(nome || '');
            $('#category-description').val(descricao || '');
            $('#categoryModalLabel').text(id ? 'Editar Categoria' : 'Nova Categoria');
            const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
            modal.show();
        }

        function setupRowHandlers(){
            // Editar (tabela)
            $('#tbl-categorias').off('click', '.btn-edit').on('click', '.btn-edit', function(){
                const id  = $(this).data('id');
                const row = $(this).closest('tr');
                const nome = row.find('td').eq(1).text().trim();
                const descricao = row.find('td').eq(2).text().trim();
                openEditModal(id, nome, descricao);
            });

            // Excluir (tabela)
            $('#tbl-categorias').off('click', '.btn-delete').on('click', '.btn-delete', function(){
                const id = $(this).data('id');
                if (!id) return;
                swalConfirmDelete().then(result => {
                    if (result.isConfirmed){
                        deleteCategory(id);
                    }
                });
            });

            // Editar (cards)
            $('#cards-container').off('click', '.btn-edit').on('click', '.btn-edit', function(){
                const id = $(this).data('id');
                const nome = decodeURIComponent($(this).data('nome') || '');
                const descricao = decodeURIComponent($(this).data('descricao') || '');
                openEditModal(id, nome, descricao);
            });

            // Excluir (cards)
            $('#cards-container').off('click', '.btn-delete').on('click', '.btn-delete', function(){
                const id = $(this).data('id');
                if (!id) return;
                swalConfirmDelete().then(result => {
                    if (result.isConfirmed){
                        deleteCategory(id);
                    }
                });
            });
        }
        setupRowHandlers();

        // Novo
        $('#btn-new-category').on('click', function(){
            openEditModal('', '', '');
        });

        // Salvar (criar/atualizar)
        $('#btn-save-category').on('click', function(){
            const $btn = $(this);
            if (!$('#category-name').val().trim()){
                swalAlert('error', 'O nome da categoria é obrigatório.');
                return;
            }
            $btn.prop('disabled', true);
            $.ajax({
                url: 'categories.php',
                type: 'POST',
                data: $('#categoryForm').serialize(),
                dataType: 'json',
                success: function(resp){
                    $btn.prop('disabled', false);
                    if (resp && resp.success){
                        bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
                        swalAlert('success', resp.message);
                        reloadTable();
                    } else {
                        swalAlert('error', (resp && resp.message) ? resp.message : 'Não foi possível salvar.');
                    }
                },
                error: function(){
                    $btn.prop('disabled', false);
                    swalAlert('error', 'Erro ao processar a solicitação.');
                }
            });
        });

        // Excluir (função)
        function deleteCategory(id){
            $.ajax({
                url: 'categories.php',
                type: 'POST',
                data: { action:'delete', id: id },
                dataType: 'json',
                success: function(resp){
                    if (resp && resp.success){
                        swalAlert('success', resp.message);
                        reloadTable();
                    } else {
                        swalAlert('error', (resp && resp.message) ? resp.message : 'Não foi possível excluir.');
                    }
                },
                error: function(){
                    swalAlert('error', 'Erro ao processar a solicitação.');
                }
            });
        }

        /* -------- Recarregar tabela (corrige erro do DataTables) --------
           Estratégia: buscar o HTML da página, extrair apenas o <tbody> da tabela
           e REINICIALIZAR o DataTables do zero com o novo HTML.
           Isso evita o erro "Requested unknown parameter [object Object]".
        --------------------------------------------------------------- */
        function reloadTable(){
            $.ajax({
                url: window.location.href,
                type: 'GET',
                success: function(data){
                    const newTbodyHTML = $(data).find('#categories-table-body').html() || '';
                    reinitDataTableWithHTML(newTbodyHTML);
                    setupRowHandlers();           // reanexa os handlers
                    renderCardsFromTable();       // atualiza cards (mobile)
                },
                error: function(){
                    swalAlert('error', 'Erro ao atualizar a lista de categorias.');
                }
            });
        }

    })();
    </script>
</body>
</html>
