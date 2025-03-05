<?php  
require_once 'conexao_bd.php';  

// Inicializar variável de status  
$status = isset($_GET['status']) ? $_GET['status'] : '';  

// Buscar dados para estatísticas  
try {  
    $total_manuais = $conexao->query("SELECT COUNT(*) FROM manuais")->fetchColumn();  
    $total_visualizacoes = $conexao->query("SELECT SUM(visualizacoes) FROM manuais")->fetchColumn() ?: 0;  
    $recentes = $conexao->query("SELECT COUNT(*) FROM manuais WHERE data_criacao > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();  
    $total_downloads = $conexao->query("SELECT SUM(downloads) FROM manuais")->fetchColumn() ?: 0;  
    
    // Buscar categorias para o filtro  
    $stmt = $conexao->query("SELECT id, nome FROM categorias ORDER BY nome");  
    $categorias = $stmt->fetchAll();  
    
    // Buscar manuais com informações adicionais (JOIN)  
    $query = "  
        SELECT m.*,   
               c.nome AS categoria_nome,   
               u.nome AS autor_nome,  
               (SELECT COUNT(*) FROM passos WHERE manual_id = m.id) AS total_passos  
        FROM manuais m  
        LEFT JOIN categorias c ON m.categoria_id = c.id  
        LEFT JOIN usuarios u ON m.autor_id = u.id  
        ORDER BY m.data_criacao DESC  
    ";  
    
    $stmt = $conexao->query($query);  
    $manuais = $stmt->fetchAll();  
    
} catch (PDOException $e) {  
    error_log("Erro ao carregar manuais: " . $e->getMessage());  
    $erro = "Ocorreu um erro ao carregar os manuais.";  
}  
?>  
<!DOCTYPE html>  
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Lista de Manuais</title>  
    
    <!-- Bootstrap CSS -->  
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
        
        /* Filter Bar */  
        .filter-bar {  
            background-color: var(--light-bg);  
            border-radius: 8px;  
            padding: 15px;  
            margin-bottom: 20px;  
        }  
        
        /* Manual Cards */  
        .manual-card {  
            height: 100%;  
            transition: transform 0.2s, box-shadow 0.2s;  
            position: relative;  
            border-radius: 8px;  
            overflow: hidden;  
            border: 1px solid var(--border-color);  
        }  
        
        .manual-card:hover {  
            transform: translateY(-5px);  
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);  
        }  
        
        .manual-card .cover {  
            height: 160px;  
            background-color: #e9ecef;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            overflow: hidden;  
        }  
        
        .manual-card .cover img {  
            width: 100%;  
            height: 100%;  
            object-fit: cover;  
        }  
        
        .manual-card .cover .placeholder {  
            color: var(--secondary-color);  
            font-size: 3rem;  
        }  
        
        .manual-card .card-body {  
            padding: 15px;  
        }  
        
        .manual-card .category-badge {  
            position: absolute;  
            top: 10px;  
            right: 10px;  
            z-index: 10;  
        }  
        
        .manual-card .card-title {  
            margin-bottom: 10px;  
            display: -webkit-box;  
            -webkit-line-clamp: 2;  
            -webkit-box-orient: vertical;  
            overflow: hidden;  
            height: 50px;  
        }  
        
        .manual-card .card-text {  
            color: var(--secondary-color);  
            display: -webkit-box;  
            -webkit-line-clamp: 3;  
            -webkit-box-orient: vertical;  
            overflow: hidden;  
            height: 70px;  
        }  
        
        .manual-card .meta {  
            display: flex;  
            justify-content: space-between;  
            color: var(--secondary-color);  
            font-size: 0.8rem;  
            margin-top: 10px;  
        }  
        
        /* Stats cards */  
        .stat-card {  
            background-color: white;  
            border-radius: 8px;  
            padding: 20px;  
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);  
            display: flex;  
            align-items: center;  
            height: 100%;  
        }  
        
        .stat-card .icon {  
            width: 60px;  
            height: 60px;  
            border-radius: 50%;  
            background-color: rgba(13, 110, 253, 0.1);  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            margin-right: 15px;  
            color: var(--primary-color);  
            font-size: 1.5rem;  
        }  
        
        .stat-card .content h3 {  
            font-size: 1.8rem;  
            font-weight: bold;  
            margin-bottom: 5px;  
        }  
        
        .stat-card .content p {  
            color: var(--secondary-color);  
            margin: 0;  
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
        
        /* DataTables custom styling */  
        div.dataTables_wrapper div.dataTables_filter {  
            text-align: right;  
            margin-bottom: 15px;  
        }  
        
        div.dataTables_wrapper div.dataTables_filter input {  
            margin-left: 0.5em;  
            width: 250px;  
        }  
        
        table.dataTable {  
            width: 100% !important;  
            margin-bottom: 1rem;  
            color: #212529;  
            border-collapse: collapse !important;  
        }  
        
        table.dataTable thead th {  
            position: relative;  
            vertical-align: bottom;  
            border-bottom: 2px solid #dee2e6;  
            font-weight: 600;  
            white-space: nowrap;  
        }  
        
        table.dataTable tbody td {  
            padding: 0.75rem;  
            vertical-align: middle;  
            border-top: 1px solid #dee2e6;  
        }  
        
        table.dataTable.no-footer {  
            border-bottom: 0;  
        }  
        
        /* Table action buttons */  
        .action-btns .btn {  
            width: 36px;  
            height: 36px;  
            padding: 0;  
            display: inline-flex;  
            align-items: center;  
            justify-content: center;  
            border-radius: 50%;  
            margin-right: 5px;  
        }  
        
        .action-btns .btn:last-child {  
            margin-right: 0;  
        }  
        
        /* Status badges */  
        .status-indicator {  
            width: 10px;  
            height: 10px;  
            border-radius: 50%;  
            display: inline-block;  
            margin-right: 5px;  
        }  
        
        .status-active {  
            background-color: #28a745;  
        }  
        
        .status-draft {  
            background-color: #ffc107;  
        }  
        
        .status-archived {  
            background-color: #6c757d;  
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
        <?php if ($status === 'deleted'): ?>  
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">  
                Manual excluído com sucesso!  
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
            </div>  
        <?php endif; ?>  
        
        <?php if (isset($erro)): ?>  
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">  
                <?php echo $erro; ?>  
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
            </div>  
        <?php endif; ?>  
        
        <!-- Estatísticas -->  
        <div class="row mb-4">  
            <div class="col-md-3 mb-3">  
                <div class="stat-card">  
                    <div class="icon">  
                        <i class="fas fa-book"></i>  
                    </div>  
                    <div class="content">  
                        <h3><?php echo number_format($total_manuais); ?></h3>  
                        <p>Total de Manuais</p>  
                    </div>  
                </div>  
            </div>  
            <div class="col-md-3 mb-3">  
                <div class="stat-card">  
                    <div class="icon">  
                        <i class="fas fa-eye"></i>  
                    </div>  
                    <div class="content">  
                        <h3><?php echo number_format($total_visualizacoes); ?></h3>  
                        <p>Visualizações</p>  
                    </div>  
                </div>  
            </div>  
            <div class="col-md-3 mb-3">  
                <div class="stat-card">  
                    <div class="icon">  
                        <i class="fas fa-calendar-alt"></i>  
                    </div>  
                    <div class="content">  
                        <h3><?php echo number_format($recentes); ?></h3>  
                        <p>Manuais Recentes</p>  
                    </div>  
                </div>  
            </div>  
            <div class="col-md-3 mb-3">  
                <div class="stat-card">  
                    <div class="icon">  
                        <i class="fas fa-download"></i>  
                    </div>  
                    <div class="content">  
                        <h3><?php echo number_format($total_downloads); ?></h3>  
                        <p>Downloads</p>  
                    </div>  
                </div>  
            </div>  
        </div>  
        
        <div class="content-container">  
            <div class="d-flex justify-content-between align-items-center mb-4">  
                <h1>Manuais</h1>  
                <a href="manual-creator.php" class="btn btn-primary">  
                    <i class="fas fa-plus-circle"></i> Criar Novo Manual  
                </a>  
            </div>  
            
            <!-- Filtros -->  
            <div class="filter-bar mb-4">  
                <div class="row">  
                    <div class="col-md-4 mb-3 mb-md-0">  
                        <label for="category-filter" class="form-label">Categoria</label>  
                        <select class="form-select" id="category-filter">  
                            <option value="">Todas as categorias</option>  
                            <?php foreach ($categorias as $categoria): ?>  
                                <option value="<?php echo sanitizar($categoria['nome']); ?>">  
                                    <?php echo sanitizar($categoria['nome']); ?>  
                                </option>  
                            <?php endforeach; ?>  
                        </select>  
                    </div>  
                    <div class="col-md-4 mb-3 mb-md-0">  
                        <label for="date-filter" class="form-label">Data</label>  
                        <select class="form-select" id="date-filter">  
                            <option value="">Todas as datas</option>  
                            <option value="today">Hoje</option>  
                            <option value="yesterday">Ontem</option>  
                            <option value="week">Esta semana</option>  
                            <option value="month">Este mês</option>  
                            <option value="year">Este ano</option>  
                        </select>  
                    </div>  
                    <div class="col-md-4">  
                        <label for="status-filter" class="form-label">Status</label>  
                        <select class="form-select" id="status-filter">  
                            <option value="">Todos os status</option>  
                            <option value="Ativo">Ativo</option>  
                            <option value="Rascunho">Rascunho</option>  
                            <option value="Arquivado">Arquivado</option>  
                        </select>  
                    </div>  
                </div>  
            </div>  
            
            <!-- Listagem de Manuais em Tabela -->  
            <div class="table-responsive">  
                <table id="manuais-table" class="table table-hover">  
                    <thead>  
                        <tr>  
                            <th>ID</th>  
                            <th>Título</th>  
                            <th>Categoria</th>  
                            <th>Versão</th>  
                            <th>Autor</th>  
                            <th>Data de Criação</th>  
                            <th>Status</th>  
                            <th>Visualizações</th>  
                            <th>Ações</th>  
                        </tr>  
                    </thead>  
                    <tbody>  
                        <?php foreach ($manuais as $manual): ?>  
                            <?php   
                            // Determinar status (exemplo simples, em um sistema real seria um campo no BD)  
                            $status = 'Ativo';  
                            $statusClass = 'status-active';  
                            
                            $data_criacao = new DateTime($manual['data_criacao']);  
                            ?>  
                            <tr>  
                                <td><?php echo $manual['id']; ?></td>  
                                <td>  
                                    <div class="d-flex align-items-center">  
                                        <div class="me-3" style="width: 50px; height: 50px; overflow: hidden; border-radius: 4px;">  
                                            <?php if (!empty($manual['imagem_capa'])): ?>  
                                                <img src="<?php echo $manual['imagem_capa']; ?>" alt="Capa" style="width: 100%; height: 100%; object-fit: cover;">  
                                            <?php else: ?>  
                                                <div class="bg-light d-flex align-items-center justify-content-center" style="width: 100%; height: 100%;">  
                                                    <i class="fas fa-book text-secondary"></i>  
                                                </div>  
                                            <?php endif; ?>  
                                        </div>  
                                        <div>  
                                            <strong><?php echo sanitizar($manual['titulo']); ?></strong>  
                                            <div class="small text-muted"><?php echo $manual['total_passos']; ?> passos</div>  
                                        </div>  
                                    </div>  
                                </td>  
                                <td>  
                                    <?php if (!empty($manual['categoria_nome'])): ?>  
                                        <span class="badge bg-info"><?php echo sanitizar($manual['categoria_nome']); ?></span>  
                                    <?php else: ?>  
                                        <span class="badge bg-secondary">Sem categoria</span>  
                                    <?php endif; ?>  
                                </td>  
                                <td><?php echo sanitizar($manual['versao']); ?></td>  
                                <td><?php echo sanitizar($manual['autor_nome'] ?? 'N/A'); ?></td>  
                                <td><?php echo $data_criacao->format('d/m/Y H:i'); ?></td>  
                                <td>  
                                    <div class="d-flex align-items-center">  
                                        <span class="status-indicator <?php echo $statusClass; ?>"></span>  
                                        <?php echo $status; ?>  
                                    </div>  
                                </td>  
                                <td><?php echo number_format($manual['visualizacoes']); ?></td>  
                                <td class="action-btns">  
                                    <a href="view-manual.php?id=<?php echo $manual['id']; ?>" class="btn btn-info btn-sm" title="Visualizar">  
                                        <i class="fas fa-eye"></i>  
                                    </a>  
                                    <a href="manual-creator.php?id=<?php echo $manual['id']; ?>" class="btn btn-primary btn-sm" title="Editar">  
                                        <i class="fas fa-edit"></i>  
                                    </a>  
                                    <button class="btn btn-danger btn-sm delete-manual" data-id="<?php echo $manual['id']; ?>" title="Excluir">  
                                        <i class="fas fa-trash"></i>  
                                    </button>  
                                </td>  
                            </tr>  
                        <?php endforeach; ?>  
                    </tbody>  
                </table>  
            </div>  
        </div>  
    </div>  
    
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
            
            // Inicializar DataTables  
            const table = $('#manuais-table').DataTable({  
                language: {  
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'  
                },  
                responsive: true,  
                order: [[5, 'desc']], // Ordenar por data de criação decrescente  
                columnDefs: [  
                    { targets: [0], visible: false } // Esconder coluna ID  
                ]  
            });  
            
            // Filtrar por categoria  
            $('#category-filter').on('change', function() {  
                table.column(2).search($(this).val()).draw();  
            });  
            
            // Filtrar por status  
            $('#status-filter').on('change', function() {  
                table.column(6).search($(this).val()).draw();  
            });  
            
            // Confirmar exclusão de manual  
            $('.delete-manual').on('click', function() {  
                const id = $(this).data('id');  
                
                Swal.fire({  
                    title: 'Confirmar exclusão?',  
                    text: "Esta ação não pode ser desfeita!",  
                    icon: 'warning',  
                    showCancelButton: true,  
                    confirmButtonColor: '#dc3545',  
                    cancelButtonColor: '#6c757d',  
                    confirmButtonText: 'Sim, excluir!',  
                    cancelButtonText: 'Cancelar'  
                }).then((result) => {  
                    if (result.isConfirmed) {  
                        window.location.href = 'delete_manual.php?id=' + id;  
                    }  
                });  
            });  
        });  
    </script>  
</body>  
</html>