<?php  
// Iniciar sessão  
session_start();  

// Verificar se o usuário está logado  
if (!isset($_SESSION['username'])) {  
    header('Location: login.php');  
    exit;  
}  

// Verificar se o usuário tem permissão (nível de acesso) para gerenciar categorias  
// Assumindo que 'administrador' e 'usuario' possam gerenciar categorias  
$allowed_roles = ['administrador', 'usuario'];  
if (!isset($_SESSION['nivel_de_acesso']) || !in_array($_SESSION['nivel_de_acesso'], $allowed_roles)) {  
    die("Acesso negado. Você não tem permissão para gerenciar categorias.");  
}  

// Incluir arquivo de conexão com o banco de dados  
require_once 'conexao_bd.php';  

// Função para sanitizar input  
function sanitize($input) {  
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');  
}  

// Processar requisições AJAX  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {  
    header('Content-Type: application/json');  
    $response = ['success' => false, 'message' => ''];  
    
    // Verificar qual ação está sendo solicitada  
    switch ($_POST['action']) {  
        case 'create':  
            // Validar dados  
            if (empty($_POST['nome'])) {  
                $response['message'] = 'O nome da categoria é obrigatório.';  
                echo json_encode($response);  
                exit;  
            }  
            
            $nome = sanitize($_POST['nome']);  
            $descricao = isset($_POST['descricao']) ? sanitize($_POST['descricao']) : '';  
            
            try {  
                // Verificar se a categoria já existe  
                $stmt = $conexao->prepare("SELECT id FROM categorias WHERE nome = ?");  
                $stmt->execute([$nome]);  
                if ($stmt->fetch()) {  
                    $response['message'] = 'Já existe uma categoria com este nome.';  
                    echo json_encode($response);  
                    exit;  
                }  
                
                // Inserir nova categoria  
                $stmt = $conexao->prepare("  
                    INSERT INTO categorias (nome, descricao, data_criacao)   
                    VALUES (?, ?, NOW())  
                ");  
                $stmt->execute([$nome, $descricao]);  
                
                $response['success'] = true;  
                $response['message'] = 'Categoria criada com sucesso.';  
                $response['id'] = $conexao->lastInsertId();  
            } catch (PDOException $e) {  
                $response['message'] = 'Erro ao criar categoria: ' . $e->getMessage();  
            }  
            break;  
            
        case 'update':  
            // Validar dados  
            if (empty($_POST['id']) || empty($_POST['nome'])) {  
                $response['message'] = 'ID e nome da categoria são obrigatórios.';  
                echo json_encode($response);  
                exit;  
            }  
            
            $id = (int)$_POST['id'];  
            $nome = sanitize($_POST['nome']);  
            $descricao = isset($_POST['descricao']) ? sanitize($_POST['descricao']) : '';  
            
            try {  
                // Verificar se a categoria existe  
                $stmt = $conexao->prepare("SELECT id FROM categorias WHERE id = ?");  
                $stmt->execute([$id]);  
                if (!$stmt->fetch()) {  
                    $response['message'] = 'Categoria não encontrada.';  
                    echo json_encode($response);  
                    exit;  
                }  
                
                // Verificar se já existe outra categoria com o mesmo nome  
                $stmt = $conexao->prepare("SELECT id FROM categorias WHERE nome = ? AND id != ?");  
                $stmt->execute([$nome, $id]);  
                if ($stmt->fetch()) {  
                    $response['message'] = 'Já existe outra categoria com este nome.';  
                    echo json_encode($response);  
                    exit;  
                }  
                
                // Atualizar categoria  
                $stmt = $conexao->prepare("  
                    UPDATE categorias   
                    SET nome = ?, descricao = ?, data_atualizacao = NOW()   
                    WHERE id = ?  
                ");  
                $stmt->execute([$nome, $descricao, $id]);  
                
                $response['success'] = true;  
                $response['message'] = 'Categoria atualizada com sucesso.';  
            } catch (PDOException $e) {  
                $response['message'] = 'Erro ao atualizar categoria: ' . $e->getMessage();  
            }  
            break;  
            
        case 'delete':  
            // Validar dados  
            if (empty($_POST['id'])) {  
                $response['message'] = 'ID da categoria é obrigatório.';  
                echo json_encode($response);  
                exit;  
            }  
            
            $id = (int)$_POST['id'];  
            
            try {  
                // Verificar se a categoria existe  
                $stmt = $conexao->prepare("SELECT id FROM categorias WHERE id = ?");  
                $stmt->execute([$id]);  
                if (!$stmt->fetch()) {  
                    $response['message'] = 'Categoria não encontrada.';  
                    echo json_encode($response);  
                    exit;  
                }  
                
                // Verificar se existem manuais associados a esta categoria  
                $stmt = $conexao->prepare("SELECT COUNT(*) FROM manuais WHERE categoria_id = ?");  
                $stmt->execute([$id]);  
                $count = $stmt->fetchColumn();  
                
                if ($count > 0) {  
                    $response['message'] = 'Esta categoria possui ' . $count . ' manuais associados. Não é possível excluí-la.';  
                    echo json_encode($response);  
                    exit;  
                }  
                
                // Excluir categoria  
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
    // Tabela não existe, vamos criá-la  
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
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Gerenciamento de Categorias</title>  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">  
    
    <!-- Font Awesome -->  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">  
    
    <!-- jQuery -->  
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
    
    <!-- Bootstrap JS Bundle -->  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>  
    
    <!-- SweetAlert2 -->  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
    
    <!-- DataTables -->  
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">  
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>  
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>  
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
            padding-bottom: 20px;  
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
        
        /* Alerts */  
        .alert {  
            display: none;  
        }  

        /* Table styles */  
        .actions {  
            white-space: nowrap;  
        }  
        .btn-sm {  
            margin-right: 5px;  
        }  
        .modal-header {  
            background-color: #f8f9fa;  
        }  
        .table-responsive {  
            margin-top: 20px;  
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
                    <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['username']) ?>  
                </a>  
                <ul class="dropdown-menu dropdown-menu-end">  
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Perfil</a></li>  
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Configurações</a></li>  
                    <li><hr class="dropdown-divider"></li>  
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>  
                </ul>  
            </div>  
        </div>  
    </div>  

    <!-- Sidebar -->  
    <div class="sidebar" id="sidebar">  
        <div class="nav flex-column">  
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>  
            <a class="nav-link" href="manual-list.php"><i class="fas fa-book"></i> Manuais</a>  
            <a class="nav-link active" href="categories.php"><i class="fas fa-tags"></i> Categorias</a>  
            <a class="nav-link" href="users.php"><i class="fas fa-users"></i> Usuários</a>  
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i> Configurações</a>  
        </div>  
    </div>  

    <!-- Main Content -->  
    <div class="main-content" id="main-content">  
        <div class="content-container">  
            <div class="row mb-4">  
                <div class="col-md-8">  
                    <h1>Gerenciamento de Categorias</h1>  
                </div>  
                <div class="col-md-4 text-right">  
                    <button type="button" class="btn btn-primary" id="btn-new-category">  
                        <i class="fas fa-plus"></i> Nova Categoria  
                    </button>  
                </div>  
            </div>  
            
            <div class="alert alert-success" id="success-alert"></div>  
            <div class="alert alert-danger" id="error-alert"></div>  
            
            <div class="table-responsive">  
                <table class="table table-striped table-hover">  
                    <thead class="thead-dark">  
                        <tr>  
                            <th>ID</th>  
                            <th>Nome</th>  
                            <th>Descrição</th>  
                            <th>Data de Criação</th>  
                            <th>Última Atualização</th>  
                            <th>Ações</th>  
                        </tr>  
                    </thead>  
                    <tbody id="categories-table-body">  
                        <?php if (empty($categorias)): ?>  
                            <tr>  
                                <td colspan="6" class="text-center">Nenhuma categoria encontrada.</td>  
                            </tr>  
                        <?php else: ?>  
                            <?php foreach ($categorias as $categoria): ?>  
                                <tr data-id="<?= $categoria['id'] ?>">  
                                    <td><?= $categoria['id'] ?></td>  
                                    <td><?= htmlspecialchars($categoria['nome']) ?></td>  
                                    <td><?= htmlspecialchars($categoria['descricao'] ?? '') ?></td>  
                                    <td><?= date('d/m/Y H:i', strtotime($categoria['data_criacao'])) ?></td>  
                                    <td><?= $categoria['data_atualizacao'] ? date('d/m/Y H:i', strtotime($categoria['data_atualizacao'])) : '-' ?></td>  
                                    <td class="actions">  
                                        <button type="button" class="btn btn-sm btn-info btn-edit" data-id="<?= $categoria['id'] ?>">  
                                            <i class="fas fa-edit"></i>  
                                        </button>  
                                        <button type="button" class="btn btn-sm btn-danger btn-delete" data-id="<?= $categoria['id'] ?>">  
                                            <i class="fas fa-trash"></i>  
                                        </button>  
                                    </td>  
                                </tr>  
                            <?php endforeach; ?>  
                        <?php endif; ?>  
                    </tbody>  
                </table>  
            </div>  
        </div>  
    </div>  
    
    <!-- Modal para Criar/Editar Categoria -->  
    <div class="modal fade" id="categoryModal" tabindex="-1" role="dialog" aria-labelledby="categoryModalLabel" aria-hidden="true">  
        <div class="modal-dialog" role="document">  
            <div class="modal-content">  
                <div class="modal-header">  
                    <h5 class="modal-title" id="categoryModalLabel">Nova Categoria</h5>  
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">  
                        <span aria-hidden="true">&times;</span>  
                    </button>  
                </div>  
                <div class="modal-body">  
                    <form id="categoryForm">  
                        <input type="hidden" id="category-id" name="id" value="">  
                        <input type="hidden" id="category-action" name="action" value="create">  
                        
                        <div class="form-group">  
                            <label for="category-name">Nome da Categoria *</label>  
                            <input type="text" class="form-control" id="category-name" name="nome" required>  
                        </div>  
                        
                        <div class="form-group">  
                            <label for="category-description">Descrição</label>  
                            <textarea class="form-control" id="category-description" name="descricao" rows="3"></textarea>  
                        </div>  
                    </form>  
                </div>  
                <div class="modal-footer">  
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>  
                    <button type="button" class="btn btn-primary" id="btn-save-category">Salvar</button>  
                </div>  
            </div>  
        </div>  
    </div>  
    
    <!-- Modal de Confirmação de Exclusão -->  
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">  
        <div class="modal-dialog" role="document">  
            <div class="modal-content">  
                <div class="modal-header bg-danger text-white">  
                    <h5 class="modal-title" id="deleteModalLabel">Confirmar Exclusão</h5>  
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">  
                        <span aria-hidden="true">&times;</span>  
                    </button>  
                </div>  
                <div class="modal-body">  
                    <p>Tem certeza que deseja excluir esta categoria?</p>  
                    <p><strong>Esta ação não pode ser desfeita.</strong></p>  
                </div>  
                <div class="modal-footer">  
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>  
                    <button type="button" class="btn btn-danger" id="btn-confirm-delete">Excluir</button>  
                </div>  
            </div>  
        </div>  
    </div>  
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>  
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>  
    
    <script>  
    $(document).ready(function() {  
        // Toggle do menu lateral  
        $('#menu-toggle').click(function() {  
            $('#sidebar').toggleClass('collapsed');  
            $('#main-content').toggleClass('expanded');  
        });  
        
        // Variáveis globais  
        let currentCategoryId = null;  
        
        // Mostrar notificação  
        function showAlert(type, message, duration = 5000) {  
            const alertElement = type === 'success' ? $('#success-alert') : $('#error-alert');  
            alertElement.text(message).fadeIn();  
            
            setTimeout(function() {  
                alertElement.fadeOut();  
            }, duration);  
        }  
        
        // Função para recarregar a tabela sem recarregar a página inteira  
        function reloadTable() {  
            $.ajax({  
                url: window.location.href,  
                type: 'GET',  
                success: function(data) {  
                    // Extrair HTML da tabela  
                    const newTableHTML = $(data).find('#categories-table-body').html();  
                    $('#categories-table-body').html(newTableHTML);  
                    setupEventHandlers();  
                },  
                error: function() {  
                    showAlert('error', 'Erro ao atualizar a tabela de categorias.');  
                }  
            });  
        }  
        
        // Configurar manipuladores de eventos para os botões da tabela  
        function setupEventHandlers() {  
            // Botão Editar  
            $('.btn-edit').click(function() {  
                const id = $(this).data('id');  
                const row = $(`tr[data-id="${id}"]`);  
                
                // Preencher o formulário com os dados da categoria  
                $('#category-id').val(id);  
                $('#category-action').val('update');  
                $('#category-name').val(row.find('td:nth-child(2)').text());  
                $('#category-description').val(row.find('td:nth-child(3)').text());  
                
                // Atualizar título do modal  
                $('#categoryModalLabel').text('Editar Categoria');  
                
                // Abrir modal  
                $('#categoryModal').modal('show');  
            });  
            
            // Botão Excluir  
            $('.btn-delete').click(function() {  
                currentCategoryId = $(this).data('id');  
                $('#deleteModal').modal('show');  
            });  
        }  
        
        // Configurar manipuladores de eventos iniciais  
        setupEventHandlers();  
        
        // Botão Nova Categoria  
        $('#btn-new-category').click(function() {  
            // Limpar formulário  
            $('#categoryForm')[0].reset();  
            $('#category-id').val('');  
            $('#category-action').val('create');  
            
            // Atualizar título do modal  
            $('#categoryModalLabel').text('Nova Categoria');  
            
            // Abrir modal  
            $('#categoryModal').modal('show');  
        });  
        
        // Botão Salvar no modal  
        $('#btn-save-category').click(function() {  
            // Validar formulário  
            if (!$('#category-name').val().trim()) {  
                showAlert('error', 'O nome da categoria é obrigatório.');  
                return;  
            }  
            
            // Enviar requisição AJAX  
            $.ajax({  
                url: 'categories.php',  
                type: 'POST',  
                data: $('#categoryForm').serialize(),  
                dataType: 'json',  
                success: function(response) {  
                    if (response.success) {  
                        // Fechar modal  
                        $('#categoryModal').modal('hide');  
                        
                        // Mostrar notificação de sucesso  
                        showAlert('success', response.message);  
                        
                        // Recarregar tabela  
                        reloadTable();  
                    } else {  
                        // Mostrar erro  
                        showAlert('error', response.message);  
                    }  
                },  
                error: function() {  
                    showAlert('error', 'Erro ao processar solicitação.');  
                }  
            });  
        });  
        
        // Botão Confirmar Exclusão  
        $('#btn-confirm-delete').click(function() {  
            // Enviar requisição AJAX para excluir  
            $.ajax({  
                url: 'categories.php',  
                type: 'POST',  
                data: {  
                    action: 'delete',  
                    id: currentCategoryId  
                },  
                dataType: 'json',  
                success: function(response) {  
                    // Fechar modal  
                    $('#deleteModal').modal('hide');  
                    
                    if (response.success) {  
                        // Mostrar notificação de sucesso  
                        showAlert('success', response.message);  
                        
                        // Recarregar tabela  
                        reloadTable();  
                    } else {  
                        // Mostrar erro  
                        showAlert('error', response.message);  
                    }  
                },  
                error: function() {  
                    $('#deleteModal').modal('hide');  
                    showAlert('error', 'Erro ao processar solicitação.');  
                }  
            });  
        });   
        
        // Responsividade para mobile  
        $(window).resize(function() {  
            if ($(window).width() < 992) {  
                $('#sidebar').addClass('collapsed');  
                $('#main-content').addClass('expanded');  
            } else {  
                $('#sidebar').removeClass('collapsed');  
                $('#main-content').removeClass('expanded');  
            }  
        }).trigger('resize');  
    });  
    </script>  
</body>  
</html>