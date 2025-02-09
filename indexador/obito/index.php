<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  
date_default_timezone_set('America/Sao_Paulo');  
?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Atlas - Pesquisa de Óbitos</title>  
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../../style/css/style.css">  
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css"/>  
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css"/>  
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">  
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">  
    
    <!-- CSS Personalizado -->  
    <style>  
        .btn-group .btn {  
            margin-right: 2px;  
        }  
        .btn-group .btn:last-child {  
            margin-right: 0;  
        }  
        .table-container {  
            margin-top: 20px;  
        }  
        .filter-section {  
            background: #f8f9fa;  
            padding: 20px;  
            border-radius: 8px;  
            margin-bottom: 20px;  
        }  

/* Estilos adicionais para o modal */  
.modal-content {  
    border: none;  
    border-radius: 16px;  
    background: var(--surface-color);  
}  

.modal-header {  
    padding: 1.5rem;  
    background: var(--surface-color);  
    border-bottom: 1px solid var(--border-color);  
}  

.modal-title {  
    font-size: 1.25rem;  
    font-weight: 600;  
    color: var(--text-primary);  
}  

.modal-body {  
    padding: 1.5rem;  
    background: var(--surface-color);  
}  

/* Grid de Informações */  
.info-grid {  
    display: grid;  
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));  
    gap: 1.5rem;  
    padding: 1.5rem;  
    background: var(--surface-secondary);  
    border-radius: 12px;  
}  

.info-item {  
    display: flex;  
    flex-direction: column;  
    gap: 0.5rem;  
}  

.info-item.full-width {  
    grid-column: 1 / -1;  
}  

.info-label {  
    font-size: 0.875rem;  
    color: var(--text-secondary);  
    font-weight: 500;  
}  

.info-value {  
    font-size: 1rem;  
    color: var(--text-primary);  
    font-weight: 500;  
}  

/* Seção de Anexos */  
.attachments-section {  
    display: grid;  
    grid-template-columns: 300px 1fr;  
    gap: 1.5rem;  
    height: 600px;  
    background: var(--surface-secondary);  
    border-radius: 12px;  
    overflow: hidden;  
}  

.attachments-list {  
    background: var(--surface-color);  
    border-right: 1px solid var(--border-color);  
    overflow-y: auto;  
}  

.attachment-viewer {  
    background: var(--surface-tertiary);  
}  

#pdf-iframe {  
    width: 100%;  
    height: 100%;  
    border: none;  
}  

/* Variáveis de Cores - Modo Claro */  
:root {  
    --surface-color: #ffffff;  
    --surface-secondary: #f8f9fa;  
    --surface-tertiary: #f1f3f5;  
    --border-color: #e9ecef;  
    --text-primary: #212529;  
    --text-secondary: #6c757d;  
}  

/* Modo Escuro */  
body.dark-mode {  
    --surface-color: #1a1d21;  
    --surface-secondary: #2d3238;  
    --surface-tertiary: #363b42;  
    --border-color: #404650;  
    --text-primary: #e9ecef;  
    --text-secondary: #adb5bd;  
}  

/* Estilos dos Anexos */  
.list-group-item {  
    padding: 1rem;  
    background: var(--surface-color);  
    border: none;  
    border-bottom: 1px solid var(--border-color);  
    cursor: pointer;  
    transition: background-color 0.2s ease;  
}  


/* Responsividade */  
@media (max-width: 768px) {  
    .attachments-section {  
        grid-template-columns: 1fr;  
        grid-template-rows: auto 1fr;  
    }  

    .info-grid {  
        grid-template-columns: 1fr;  
        gap: 1rem;  
        padding: 1rem;  
    }  
}  
    </style>  

    <!-- JavaScript Core -->  
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>  
    
    <!-- DataTables JS -->  
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>  
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>  
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>  
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script> 
    
      
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
</head>
<body class="light-mode">  
<?php include(__DIR__ . '/../../menu.php'); ?>  

<div id="main" class="main-content">  
    <div class="container">  
        <div class="d-flex justify-content-between align-items-center">  
            <h3>Pesquisa de Registros de Óbito</h3>  
            <a href="index.php" class="btn btn-secondary">  
                <i class="fa fa-plus" aria-hidden="true"></i> Novo Registro de Óbito  
            </a>  
        </div>  
        <hr>  

        <div class="filter-section">  
            <form method="post" action="" id="filterForm">  
                <div class="row">  
                    <div class="col-md-3 mb-3">  
                        <label for="data_registro_inicio" class="form-label">Data de Registro (Início)</label>  
                        <input type="date" id="data_registro_inicio" name="data_registro_inicio" class="form-control">  
                    </div>  
                    <div class="col-md-3 mb-3">  
                        <label for="data_registro_fim" class="form-label">Data de Registro (Fim)</label>  
                        <input type="date" id="data_registro_fim" name="data_registro_fim" class="form-control">  
                    </div>  
                    <div class="col-md-3 mb-3">  
                        <label for="data_obito_inicio" class="form-label">Data do Óbito (Início)</label>  
                        <input type="date" id="data_obito_inicio" name="data_obito_inicio" class="form-control">  
                    </div>  
                    <div class="col-md-3 mb-3">  
                        <label for="data_obito_fim" class="form-label">Data do Óbito (Fim)</label>  
                        <input type="date" id="data_obito_fim" name="data_obito_fim" class="form-control">  
                    </div>  
                </div>  
                <div class="row">  
                    <div class="col-md-2 mb-2">  
                        <label for="livro" class="form-label">Livro</label>  
                        <input type="text" id="livro" name="livro" class="form-control">  
                    </div>  
                    <div class="col-md-2 mb-2">  
                        <label for="termo" class="form-label">Termo</label>  
                        <input type="text" id="termo" name="termo" class="form-control">  
                    </div>  
                    <div class="col-md-3 mb-3">  
                        <label for="nome_registrado" class="form-label">Nome do Falecido</label>  
                        <input type="text" id="nome_registrado" name="nome_registrado" class="form-control">  
                    </div>  
                    <div class="col-md-3 mb-3">  
                        <label for="matricula" class="form-label">Matrícula</label>  
                        <input type="text" id="matricula" name="matricula" class="form-control">  
                    </div>  
                    <div class="col-md-2 mb-2">  
                        <label for="folha" class="form-label">Folha</label>  
                        <input type="text" id="folha" name="folha" class="form-control">  
                    </div>  
                </div>  
                <div class="row">  
                    <div class="col-md-6 mb-3">  
                        <label for="nome_pai" class="form-label">Nome do Pai</label>  
                        <input type="text" id="nome_pai" name="nome_pai" class="form-control">  
                    </div>  
                    <div class="col-md-6 mb-3">  
                        <label for="nome_mae" class="form-label">Nome da Mãe</label>  
                        <input type="text" id="nome_mae" name="nome_mae" class="form-control">  
                    </div>  
                </div>  
                <div class="row">  
                    <div class="col-12">  
                        <button type="submit" name="search" class="btn btn-primary w-100">  
                            <i class="fas fa-search"></i>  
                            Pesquisar  
                        </button>  
                    </div>  
                </div>  
            </form>  
        </div>  

        <div class="table-container">  
            <table id="tabelaResultados" class="table table-hover">  
                <thead>  
                    <tr>  
                        <th>Termo</th>  
                        <th>Livro</th>  
                        <th>Folha</th>  
                        <th>Matrícula</th>  
                        <th>Nome do Falecido</th>  
                        <th>Data do Óbito</th>  
                        <th>Data de Registro</th>  
                        <th>Ações</th>  
                    </tr>  
                </thead>  
                <tbody>  
                    <?php  
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {  
                        $where = ["status = 'A'"]; // Registros ativos  
                        
                        if (!empty($_POST['data_registro_inicio']))   
                            $where[] = "data_registro >= '" . $conn->real_escape_string($_POST['data_registro_inicio']) . "'";  
                        if (!empty($_POST['data_registro_fim']))   
                            $where[] = "data_registro <= '" . $conn->real_escape_string($_POST['data_registro_fim']) . "'";  
                        if (!empty($_POST['data_obito_inicio']))   
                            $where[] = "data_obito >= '" . $conn->real_escape_string($_POST['data_obito_inicio']) . "'";  
                        if (!empty($_POST['data_obito_fim']))   
                            $where[] = "data_obito <= '" . $conn->real_escape_string($_POST['data_obito_fim']) . "'";  
                        if (!empty($_POST['nome_registrado']))   
                            $where[] = "nome_registrado LIKE '%" . $conn->real_escape_string($_POST['nome_registrado']) . "%'";  
                        if (!empty($_POST['nome_pai']))   
                            $where[] = "nome_pai LIKE '%" . $conn->real_escape_string($_POST['nome_pai']) . "%'";  
                        if (!empty($_POST['nome_mae']))   
                            $where[] = "nome_mae LIKE '%" . $conn->real_escape_string($_POST['nome_mae']) . "%'";  
                        if (!empty($_POST['termo']))   
                            $where[] = "termo LIKE '%" . $conn->real_escape_string($_POST['termo']) . "%'";  
                        if (!empty($_POST['livro']))   
                            $where[] = "livro LIKE '%" . $conn->real_escape_string($_POST['livro']) . "%'";  
                        if (!empty($_POST['folha']))   
                            $where[] = "folha LIKE '%" . $conn->real_escape_string($_POST['folha']) . "%'";  
                        if (!empty($_POST['matricula']))   
                            $where[] = "matricula LIKE '%" . $conn->real_escape_string($_POST['matricula']) . "%'";  

                        $whereSQL = 'WHERE ' . implode(' AND ', $where);  
                        $query = "SELECT * FROM indexador_obito $whereSQL ORDER BY data_registro DESC";  
                        $result = $conn->query($query);  

                        if ($result && $result->num_rows > 0) {  
                            while ($row = $result->fetch_assoc()) {  
                                echo '<tr>';  
                                echo '<td>' . htmlspecialchars($row['termo']) . '</td>';  
                                echo '<td>' . htmlspecialchars($row['livro']) . '</td>';  
                                echo '<td>' . htmlspecialchars($row['folha']) . '</td>';  
                                echo '<td>' . htmlspecialchars($row['matricula'] ?? '') . '</td>';  
                                echo '<td>' . htmlspecialchars($row['nome_registrado']) . '</td>';  
                                echo '<td>' . date('d/m/Y', strtotime($row['data_obito'])) . '</td>';  
                                echo '<td>' . date('d/m/Y', strtotime($row['data_registro'])) . '</td>';  
                                echo '<td>  
                                        <button type="button" class="btn btn-sm btn-info" onclick="visualizarRegistro(' . $row['id'] . ')">  
                                            <i class="fas fa-eye"></i>  
                                        </button>  
                                        <button type="button" class="btn btn-sm btn-warning" onclick="editarRegistro(' . $row['id'] . ')">  
                                            <i class="fas fa-edit"></i>  
                                        </button>  
                                      </td>';  
                                echo '</tr>';  
                            }  
                        } else {  
                            echo '<tr><td colspan="8" class="text-center">Nenhum registro encontrado</td></tr>';  
                        }  
                    }  
                    ?>  
                </tbody>  
            </table>  
        </div>  
    </div>  
</div>  

<!-- Modal de Visualização -->  
<div class="modal fade" id="viewRegistryModal" tabindex="-1" aria-hidden="true">  
    <div class="modal-dialog modal-xl" style="max-width: 95%; width: 1400px;">  
        <div class="modal-content">  
            <div class="modal-header">  
                <div>  
                    <h5 class="modal-title mb-1">  
                        Visualização do Registro  
                    </h5>  
                    <small class="text-muted">Informações detalhadas do registro selecionado</small>  
                </div>  
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>  
            </div>  

            <div class="modal-body">  
                <!-- Dados do Registro -->  
                <div class="registry-info mb-4">  
                    <div class="info-grid">  
                        <div class="info-item full-width">  
                            <span class="info-label">Matrícula</span>  
                            <span id="view-matricula" class="info-value"></span>  
                        </div>  
                        <div class="info-item">  
                            <span class="info-label">Livro</span>  
                            <span id="view-livro" class="info-value"></span>  
                        </div>  
                        <div class="info-item">  
                            <span class="info-label">Folha</span>  
                            <span id="view-folha" class="info-value"></span>  
                        </div>  
                        <div class="info-item">  
                            <span class="info-label">Termo</span>  
                            <span id="view-termo" class="info-value"></span>  
                        </div>  
                        <div class="info-item">  
                            <span class="info-label">Data de Registro</span>  
                            <span id="view-data-registro" class="info-value"></span>  
                        </div>  
                    </div>  
                </div>  

                <!-- Dados do Falecido -->  
                <div class="person-info mb-4">  
                    <div class="info-grid">  
                        <div class="info-item full-width">  
                            <span class="info-label">Nome do Falecido</span>  
                            <span id="view-nome-registrado" class="info-value"></span>  
                        </div>  
                        <div class="info-item">  
                            <span class="info-label">Data de Nascimento</span>  
                            <span id="view-data-nascimento" class="info-value"></span>  
                        </div>  
                        <div class="info-item">  
                            <span class="info-label">Data do Óbito</span>  
                            <span id="view-data-obito" class="info-value"></span>  
                        </div>  
                        <div class="info-item">  
                            <span class="info-label">Cidade do Óbito</span>  
                            <span id="view-cidade-obito" class="info-value"></span>  
                        </div>  
                        <div class="info-item">  
                            <span class="info-label">Nome do Pai</span>  
                            <span id="view-nome-pai" class="info-value"></span>  
                        </div>  
                        <div class="info-item">  
                            <span class="info-label">Nome da Mãe</span>  
                            <span id="view-nome-mae" class="info-value"></span>  
                        </div>  
                    </div>  
                </div>  

                <!-- Anexos -->  
                <div class="attachments-section">  
                    <div class="attachments-list" id="anexos-list">  
                        <!-- Lista de anexos será carregada aqui -->  
                    </div>  
                    <div class="attachment-viewer">  
                        <iframe id="pdf-iframe" allowfullscreen></iframe>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  
</div>  

    <script src="../../script/jquery-3.6.0.min.js"></script>  
    <script src="../../script/bootstrap.bundle.min.js"></script>  
    <script src="../../script/jquery.dataTables.min.js"></script>  
    <script src="../../script/dataTables.bootstrap4.min.js"></script>

    <script>  
    $(document).ready(function() {  
        // Verificar se o DataTables está disponível  
        if (typeof $.fn.DataTable !== 'undefined') {  
            try {  
                $('#tabelaResultados').DataTable({  
                    "language": {  
                        "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json"  
                    },  
                    "order": [[6, "desc"]],  
                    "pageLength": 25,  
                    "responsive": true,  
                    "initComplete": function() {  
                        console.log('DataTable inicializado com sucesso');  
                    }  
                });  
            } catch (error) {  
                console.error('Erro ao inicializar DataTable:', error);  
            }  
        } else {  
            console.error('DataTables não está disponível');  
        }  
    });  

    // Função para visualizar registro  
    function visualizarRegistro(id) {  
        Swal.fire({  
            title: 'Carregando...',  
            didOpen: () => {  
                Swal.showLoading();  
            },  
            allowOutsideClick: false,  
            showConfirmButton: false  
        });  

        // Função para carregar os anexos  
        function carregarAnexos(idObito) {  
            $.ajax({  
                url: 'buscar_anexos.php',  
                type: 'GET',  
                data: { id_obito: idObito },  
                dataType: 'json',  
                success: function(response) {  
                    const anexosList = $('#anexos-list');  
                    anexosList.empty();  

                    if (response.success && response.anexos && response.anexos.length > 0) {  
                        response.anexos.forEach((anexo, index) => {  
                            if (anexo && anexo.id && anexo.nome_arquivo) {  
                                anexosList.append(`  
                                    <div class="list-group-item" role="button" data-src="${anexo.nome_arquivo}">  
                                        <div class="d-flex align-items-center">  
                                            <i class="fas fa-file-pdf text-success me-2"></i>  
                                            <span class="text-truncate" style="max-width: 200px;">  
                                                ${anexo.nome_arquivo.split('/').pop()}  
                                            </span>  
                                        </div>  
                                    </div>  
                                `);  
                            } 
                        });  

                        // Carrega o primeiro anexo automaticamente  
                        if (response.anexos[0]) {  
                            $('#pdf-iframe').attr('src', response.anexos[0].nome_arquivo);  
                            // Marca o primeiro item como ativo  
                            anexosList.find('.list-group-item:first').addClass('active');  
                        }  
                    } else {  
                        anexosList.append(`  
                            <div class="text-center text-muted py-3">  
                                <i class="fas fa-file-alt fa-2x mb-2"></i>  
                                <p class="mb-0">Nenhum anexo disponível</p>  
                            </div>  
                        `);  
                        $('#pdf-iframe').attr('src', '');  
                    }  
                },  
                error: function() {  
                    $('#anexos-list').html(`  
                        <div class="alert alert-warning m-3">  
                            <i class="fas fa-exclamation-triangle me-2"></i>  
                            Não foi possível carregar os anexos  
                        </div>  
                    `);  
                }  
            });  
        }  

        // Adiciona evento de clique para o item da lista  
        $(document).on('click', '.list-group-item', function(e) {  
            e.preventDefault();  
            
            // Remove a classe active de todos os items  
            $('.list-group-item').removeClass('active');  
            
            // Adiciona a classe active ao item clicado  
            $(this).addClass('active');  
            
            // Atualiza o iframe com o novo arquivo  
            const src = $(this).data('src');  
            $('#pdf-iframe').attr('src', src);  
        });

        // Adiciona evento de clique para o item inteiro da lista  
        $(document).on('click', '.list-group-item', function(e) {  
            if (!$(e.target).hasClass('btn') && !$(e.target).hasClass('fas')) {  
                const src = $(this).find('.visualizar-anexo').data('src');  
                visualizarAnexo(e, src);  
            }  
        });

        // Carregar dados principais  
        $.ajax({  
            url: 'buscar_obito.php',  
            type: 'GET',  
            data: { id: id },  
            dataType: 'json',  
            success: function(response) {  
                Swal.close();  
                
                if (!response.success) {  
                    Swal.fire({  
                        icon: 'error',  
                        title: 'Erro!',  
                        text: response.error || 'Erro ao carregar os dados do registro.'  
                    });  
                    return;  
                }  
                
                const data = response.data;  
                
                // Preenche os campos com texto plano  
                $('#view-livro').text(data.livro || '-');  
                $('#view-folha').text(data.folha || '-');  
                $('#view-termo').text(data.termo || '-');  
                $('#view-data-registro').text(data.data_registro || '-');  
                $('#view-matricula').text(data.matricula || '-');  
                $('#view-nome-registrado').text(data.nome_registrado || '-');  
                $('#view-data-obito').text(data.data_obito || '-');
                $('#view-data-nascimento').text(data.data_nascimento || '-');  
                $('#view-cidade-obito').text(data.cidade_obito || '-');  
                $('#view-nome-pai').text(data.nome_pai || '-');  
                $('#view-nome-mae').text(data.nome_mae || '-');  

                // Abre o modal  
                const modal = new bootstrap.Modal(document.getElementById('viewRegistryModal'));  
                modal.show();  

                // Carrega os anexos após abrir o modal  
                carregarAnexos(id);  
            },  
            error: function() {  
                Swal.close();  
                Swal.fire({  
                    icon: 'error',  
                    title: 'Erro!',  
                    text: 'Não foi possível carregar os dados do registro.'  
                });  
            }  
        });  
    }

    // Função para editar registro  
    function editarRegistro(id) {  
        window.location.href = `editar.php?id=${id}`;  
    }  

    // Função auxiliar para formatar data  
    function formatarData(dataString) {  
        if (!dataString) return '';  
        const data = new Date(dataString);  
        return data.toLocaleDateString('pt-BR');  
    }  

    // Limpar modal quando fechado  
    $('#viewRegistryModal').on('hidden.bs.modal', function () {  
        $('#viewRegistryModal input').val('');  
        $('#view-attachments-container').empty();  
    });  
    </script>  

<?php include(__DIR__ . '/../../rodape.php'); ?>  
</body>  
</html>  