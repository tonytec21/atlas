<?php
// Iniciar sessão  
session_start();  

// Verificar se o usuário está logado  
if (!isset($_SESSION['username'])) {  
    header('Location: login.php');  
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

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <style>
        /* =========================================================
         *  Tema Light / Dark — UI moderna (mesmo design do manual-list.php)
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

        /* Table + Datatables */
        .table-wrap{ background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:12px; }
        table.dataTable thead th{ white-space:nowrap; font-weight:700; border-bottom:1px solid var(--border);
            background:linear-gradient(180deg, var(--surface-2), var(--surface)); }
        table.dataTable tbody td{ vertical-align:middle; border-top:1px solid var(--border); }
        .actions .btn{ width:36px; height:36px; border-radius:10px; display:inline-grid; place-items:center; margin-right:6px; }
        .actions .btn:last-child{ margin-right:0; }

        /* Alerts custom */
        .alert-soft{ display:none; border-radius:12px; border:1px solid var(--border); }

        /* Modal */
        .modal-content{ background:var(--surface); color:var(--text); border:1px solid var(--border); border-radius:16px; }
        .modal-header{ background:linear-gradient(180deg, var(--surface-2), var(--surface)); border-bottom:1px solid var(--border); }
        .form-control, .form-select, textarea{ border:1px solid var(--border); background:var(--surface); color:var(--text); border-radius:12px; }

        /* Responsive */
        @media (max-width: 992px){
            .sidebar{ margin-left:calc(-1 * var(--sidebar-width)); }
            .sidebar.mobile-visible{ margin-left:0; }
            .main{ margin-left:0; padding:calc(var(--topbar-h) + 16px) 16px 16px; }
        }

        .btn-primary{ background:linear-gradient(135deg, var(--primary) 0%, var(--primary-600) 100%); border:none; }
        .btn-primary:hover{ filter:brightness(.95); }
    </style>
</head>  
<body>

    <!-- Topbar -->
    <div class="topbar">
        <button class="menu-toggle" id="menu-toggle" aria-label="Alternar menu"><i class="fa-solid fa-bars"></i></button>
        <div class="brand">
            <div class="logo">M</div>
            <h4 class="m-0">Sistema de Manuais</h4>
        </div>
        <div class="topbar-actions">
            <button class="btn-ghost" id="themeToggle" type="button" aria-label="Alternar tema">
                <i class="fa-solid fa-moon me-1" id="themeIcon"></i>
                <span class="d-none d-md-inline" id="themeLabel">Dark</span>
            </button>
            <div class="dropdown">
                <button class="avatar-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-user"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="border-radius:12px;">
                    <li><span class="dropdown-item-text"><i class="fa-regular fa-id-badge me-2"></i><?= sanitize($_SESSION['username']) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#"><i class="fa-solid fa-user me-2"></i>Perfil</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fa-solid fa-gear me-2"></i>Configurações</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Sair</a></li>
                </ul>
            </div>
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

        <div class="alert alert-success alert-soft" id="success-alert"></div>
        <div class="alert alert-danger alert-soft" id="error-alert"></div>

        <div class="table-wrap">
            <div class="table-responsive">
                <table id="tbl-categorias" class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Data de Criação</th>
                            <th>Última Atualização</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="categories-table-body">
                        <?php if (empty($categorias)): ?>
                            <tr><td colspan="6" class="text-center">Nenhuma categoria encontrada.</td></tr>
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

    <!-- Modal Confirmação Exclusão -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold" id="deleteModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir esta categoria?</p>
                    <p><strong>Esta ação não pode ser desfeita.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btn-confirm-delete">Excluir</button>
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

        /* -------- Notificações -------- */
        function showAlert(type, message, duration = 5000){
            const el = (type === 'success') ? $('#success-alert') : $('#error-alert');
            el.text(message).fadeIn();
            setTimeout(()=> el.fadeOut(), duration);
        }

        /* -------- DataTables -------- */
        const dt = $('#tbl-categorias').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/pt-BR.json' },
            responsive: true,
            order: [[1, 'asc']],
            columnDefs: [
                { targets: [5], orderable: false }
            ]
        });

        /* -------- Recarregar tabela -------- */
        function reloadTable(){
            $.ajax({
                url: window.location.href,
                type: 'GET',
                success: function(data){
                    const tbody = $(data).find('#categories-table-body').html();
                    // Atualiza apenas o tbody para preservar a instância do DataTable
                    dt.clear();
                    // Cria uma tabela temporária para extrair linhas
                    const temp = document.createElement('tbody');
                    temp.innerHTML = tbody;
                    $(temp).find('tr').each(function(){
                        const tds = $(this).children('td');
                        if (tds.length){
                            dt.row.add([
                                tds.eq(0).html(),
                                tds.eq(1).html(),
                                tds.eq(2).html(),
                                tds.eq(3).html(),
                                tds.eq(4).html(),
                                `<div class="actions text-center">
                                    <button type="button" class="btn btn-outline-info btn-sm btn-edit" data-id="${$(this).data('id')}" title="Editar">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-delete" data-id="${$(this).data('id')}" title="Excluir">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                  </div>`
                            ]);
                        }
                    });
                    dt.draw(false);
                    setupRowHandlers();
                },
                error: function(){ showAlert('error', 'Erro ao atualizar a tabela de categorias.'); }
            });
        }

        /* -------- Handlers das linhas -------- */
        let currentCategoryId = null;

        function setupRowHandlers(){
            // Editar
            $('#tbl-categorias').off('click', '.btn-edit').on('click', '.btn-edit', function(){
                const id  = $(this).data('id');
                const row = $(this).closest('tr');
                const nome = row.find('td').eq(1).text().trim();
                const descricao = row.find('td').eq(2).text().trim();

                $('#category-id').val(id);
                $('#category-action').val('update');
                $('#category-name').val(nome);
                $('#category-description').val(descricao);
                $('#categoryModalLabel').text('Editar Categoria');

                const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
                modal.show();
            });

            // Excluir
            $('#tbl-categorias').off('click', '.btn-delete').on('click', '.btn-delete', function(){
                currentCategoryId = $(this).data('id');
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                modal.show();
            });
        }
        setupRowHandlers();

        // Nova categoria
        $('#btn-new-category').on('click', function(){
            $('#categoryForm')[0].reset();
            $('#category-id').val('');
            $('#category-action').val('create');
            $('#categoryModalLabel').text('Nova Categoria');

            const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
            modal.show();
        });

        // Salvar (criar/atualizar)
        $('#btn-save-category').on('click', function(){
            if (!$('#category-name').val().trim()){
                showAlert('error', 'O nome da categoria é obrigatório.');
                return;
            }
            $.ajax({
                url: 'categories.php',
                type: 'POST',
                data: $('#categoryForm').serialize(),
                dataType: 'json',
                success: function(resp){
                    if (resp.success){
                        bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
                        showAlert('success', resp.message);
                        reloadTable();
                    } else {
                        showAlert('error', resp.message);
                    }
                },
                error: function(){ showAlert('error', 'Erro ao processar solicitação.'); }
            });
        });

        // Confirmar exclusão
        $('#btn-confirm-delete').on('click', function(){
            $.ajax({
                url: 'categories.php',
                type: 'POST',
                data: { action:'delete', id: currentCategoryId },
                dataType: 'json',
                success: function(resp){
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    if (resp.success){
                        showAlert('success', resp.message);
                        reloadTable();
                    } else {
                        showAlert('error', resp.message);
                    }
                },
                error: function(){
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    showAlert('error', 'Erro ao processar solicitação.');
                }
            });
        });
    })();
    </script>
</body>
</html>
