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
    <title>Atlas - Indexador - Nascimento</title>
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../../style/css/dataTables.bootstrap4.min.css">
    <style>
        .btn-close {
            outline: none;
            border: none; 
            background: none;
            padding: 0; 
            font-size: 1.5rem; 
            cursor: pointer; 
            transition: transform 0.2s ease;
        }

        .btn-close:hover {
            transform: scale(2.10);
        }

        .btn-close:focus {
            outline: none;
        }
        
        .btn-edit {
            margin-left: 5px;
        }

        .btn-info {
            width: 40px;
            height: 40px;
            margin-left: 5px;
        }

        .alert-popup {
            position: fixed;
            top: 85%;
            right: 20px;
            padding: 15px;
            background-color: #5bc0de;
            color: white;
            border-radius: 5px;
            z-index: 10000;
        }
        .alert-success {
            background-color: #5cb85c;
        }
        .alert-error {
            background-color: #d9534f;
        }
        .alert-warning {
            background-color: #f0ad4e;
        }

        .modal-dialog {
            max-width: 80%;
            margin: 1.75rem auto;
        }

         /* Ajuste do tamanho do modal de visualização */
        .modal-edicao {
            width: 80%;
            margin: 1.75rem auto;
        }

        .modal-cadastro {
            width: 80%;
            margin: 1.75rem auto;
        }

        .modal-visualizacao {
            width: 100%;
            margin: 1.75rem auto;
        }

        #confirmRemoveAttachmentModal .modal-header {
            border-bottom: none;
        }

        #confirmRemoveAttachmentModal .modal-footer {
            border-top: none;
            display: flex;
            justify-content: center;
        }

        #confirmRemoveAttachmentModal .btn-confirm-remove {
            width: 100px;
        }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../../menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Indexador de Nascimento</h3>
        <hr>
        <!-- Formulário de Pesquisa e Filtros -->
        <div class="row mb-3">
            <div class="col-md-6 col-lg-6 mb-2">
                <label for="search-term">Nome do Registrado</label>
                <input type="text" id="search-term" class="form-control" placeholder="Nome do registrado">
            </div>
            <div class="col-md-6 col-lg-2 mb-2">
                <label for="search-term-term">Termo</label>
                <input type="number" id="search-term-term" class="form-control" placeholder="Termo" min="0">
            </div>
            <div class="col-md-6 col-lg-2 mb-2">
                <label for="search-term-book">Livro</label>
                <input type="number" id="search-term-book" class="form-control" placeholder="Livro" min="0">
            </div>
            <div class="col-md-6 col-lg-2 mb-2">
                <label for="search-term-page">Folha</label>
                <input type="number" id="search-term-page" class="form-control" placeholder="Folha" min="0">
            </div>
            <div class="col-md-6 col-lg-6 mb-2">
                <label for="search-father">Nome do Pai</label>
                <input type="text" id="search-father" class="form-control" placeholder="Nome do Pai">
            </div>
            <div class="col-md-6 col-lg-6 mb-2">
                <label for="search-mother">Nome da Mãe</label>
                <input type="text" id="search-mother" class="form-control" placeholder="Nome da Mãe">
            </div>
            <div class="col-md-6 col-lg-3 mb-2">
                <label for="search-birthdate">Data de Nascimento</label>
                <input type="date" id="search-birthdate" class="form-control">
            </div>
            <div class="col-md-6 col-lg-3 mb-2">
                <label for="search-registry-date">Data de Registro</label>
                <input type="date" id="search-registry-date" class="form-control">
            </div>
            <div class="col-md-6 col-lg-3 mb-2">
                <button id="filter-button" class="btn btn-primary w-100"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar</button>
            </div>
            <div class="col-md-6 col-lg-3 mb-2">
                <button class="btn btn-add w-100" data-toggle="modal" data-target="#addRegistryModal"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar</button>
            </div>
        </div>
        <hr>
        <div class="table-responsive">
            <h5>Resultados da Pesquisa</h5>
            <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 90%">
                <thead>
                    <tr>
                        <th>Termo</th>
                        <th>Livro</th>
                        <th>Folha</th>
                        <th>Nome do Registrado</th>
                        <th>Filiação</th>
                        <th>Data de Nascimento</th>
                        <th>Data de Registro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="registry-table-body">
                    <!-- Linhas serão adicionadas dinamicamente -->
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM indexador_nascimento WHERE status = 'ativo'");
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . $row['termo'] . '</td>';
                        echo '<td>' . $row['livro'] . '</td>';
                        echo '<td>' . $row['folha'] . '</td>';
                        echo '<td>' . $row['nome_registrado'] . '</td>';
                        echo '<td>' . ($row['nome_pai'] ? $row['nome_pai'] . ' e ' . $row['nome_mae'] : $row['nome_mae']) . '</td>';
                        echo '<td data-order="' . date("Y-m-d", strtotime($row['data_nascimento'])) . '">' . date("d/m/Y", strtotime($row['data_nascimento'])) . '</td>';
                        echo '<td data-order="' . date("Y-m-d", strtotime($row['data_registro'])) . '">' . date("d/m/Y", strtotime($row['data_registro'])) . '</td>';                        
                        echo '<td>' .
                        '<button class="btn btn-info btn-view" data-id="' . $row['id'] . '"><i class="fa fa-eye" aria-hidden="true"></i></button>' .
                        '<button class="btn btn-edit" data-id="' . $row['id'] . '"><i class="fa fa-pencil" aria-hidden="true"></i></button> ' .
                        ($nivel_de_acesso === 'administrador' ? '<button class="btn btn-delete" data-id="' . $row['id'] . '"><i class="fa fa-trash" aria-hidden="true"></i></button>' : '') .
                        '</td>';

                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Modal de Adição de Registro -->
        <div class="modal fade" id="addRegistryModal" tabindex="-1" role="dialog" aria-labelledby="addRegistryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-custom modal-dialog-centered" role="document">
                <div class="modal-content modal-cadastro">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addRegistryModalLabel">Adicionar Registro</h5>
                        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                            &times;
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="registry-form">
                            <div class="form-row">
                                <div class="form-group col-12 col-md-2">
                                    <label for="term">Termo</label>
                                    <input type="number" class="form-control" id="term" name="termo" required min="0">
                                </div>
                                <div class="form-group col-12 col-md-2">
                                    <label for="book">Livro</label>
                                    <input type="number" class="form-control" id="book" name="livro" required min="0">
                                </div>
                                <div class="form-group col-12 col-md-2">
                                    <label for="page">Folha</label>
                                    <input type="number" class="form-control" id="page" name="folha" required min="0">
                                </div>
                                <div class="form-group col-12 col-md-3">
                                    <label for="registry-date">Data de Registro</label>
                                    <input type="date" class="form-control" id="registry-date" name="data_registro" required>
                                </div>
                                <div class="form-group col-12 col-md-3">
                                    <label for="birthdate">Data de Nascimento</label>
                                    <input type="date" class="form-control" id="birthdate" name="data_nascimento" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="name">Nome do Registrado</label>
                                <input type="text" class="form-control" id="name" name="nome_registrado" required>
                            </div>
                            <div class="form-group">
                                <label for="father-name">Nome do Pai</label>
                                <input type="text" class="form-control" id="father-name" name="nome_pai">
                            </div>
                            <div class="form-group">
                                <label for="mother-name">Nome da Mãe</label>
                                <input type="text" class="form-control" id="mother-name" name="nome_mae" required>
                            </div>

                            <!-- Adicionar Anexo -->
                            <div class="form-group">
                                <label for="add-pdf-file">Adicionar Anexo PDF</label>
                                <input type="file" class="form-control-file" id="add-pdf-file">
                                <button type="button" style="width: 100%" id="add-attachment-btn" class="btn btn-secondary mt-2">Adicionar Anexo</button>
                            </div>
                            <div class="mt-4">
                                <h5>Anexos</h5>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Nome do Arquivo</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="add-attachments-table-body">
                                        <!-- Linhas serão adicionadas dinamicamente -->
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" style="width: 100%" class="btn btn-primary">Salvar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Edição de Registro -->
        <div class="modal fade" id="editRegistryModal" tabindex="-1" role="dialog" aria-labelledby="editRegistryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-custom modal-dialog-centered" role="document">
                <div class="modal-content modal-edicao">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editRegistryModalLabel">Editar Registro</h5>
                        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                            &times;
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="edit-registry-form">
                            <input type="hidden" id="edit-id" name="id">
                            <div class="form-row">
                                <div class="form-group col-12 col-md-2">
                                    <label for="edit-term">Termo</label>
                                    <input type="number" class="form-control" id="edit-term" name="termo" required min="0">
                                </div>
                                <div class="form-group col-12 col-md-2">
                                    <label for="edit-book">Livro</label>
                                    <input type="number" class="form-control" id="edit-book" name="livro" required min="0">
                                </div>
                                <div class="form-group col-12 col-md-2">
                                    <label for="edit-page">Folha</label>
                                    <input type="number" class="form-control" id="edit-page" name="folha" required min="0">
                                </div>
                                <div class="form-group col-12 col-md-3">
                                    <label for="edit-registry-date">Data de Registro</label>
                                    <input type="date" class="form-control" id="edit-registry-date" name="data_registro" required>
                                </div>
                                <div class="form-group col-12 col-md-3">
                                    <label for="edit-birthdate">Data de Nascimento</label>
                                    <input type="date" class="form-control" id="edit-birthdate" name="data_nascimento" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="edit-name">Nome do Registrado</label>
                                <input type="text" class="form-control" id="edit-name" name="nome_registrado" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-father-name">Nome do Pai</label>
                                <input type="text" class="form-control" id="edit-father-name" name="nome_pai">
                            </div>
                            <div class="form-group">
                                <label for="edit-mother-name">Nome da Mãe</label>
                                <input type="text" class="form-control" id="edit-mother-name" name="nome_mae" required>
                            </div>

                            <!-- Adicionar Anexo -->
                            <div class="form-group">
                                <label for="edit-pdf-file">Adicionar Anexo PDF</label>
                                <input type="file" class="form-control-file" id="edit-pdf-file">
                                <button type="button" style="width: 100%" id="edit-add-attachment-btn" class="btn btn-secondary mt-2">Adicionar Anexo</button>
                            </div>

                            <!-- Tabela de Anexos -->
                            <div class="mt-4">
                                <h5>Anexos</h5>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Nome do Arquivo</th>
                                            <th>Data do Anexo</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="edit-attachments-table-body">
                                        <!-- Linhas serão adicionadas dinamicamente -->
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" style="width: 100%" class="btn btn-primary">Salvar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Visualização de Registro -->
        <div class="modal fade" id="viewRegistryModal" tabindex="-1" role="dialog" aria-labelledby="viewRegistryModalLabel" aria-hidden="true">
             <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                 <div class="modal-content modal-visualizacao">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewRegistryModalLabel">Visualizar Registro</h5>
                        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                            &times;
                        </button>
                    </div>
                    <div class="modal-body">
                        <form>
                            <div class="form-row">
                                <div class="form-group col-12 col-md-2">
                                    <label for="view-term">Termo</label>
                                    <input type="text" class="form-control" id="view-term" readonly>
                                </div>
                                <div class="form-group col-12 col-md-2">
                                    <label for="view-book">Livro</label>
                                    <input type="text" class="form-control" id="view-book" readonly>
                                </div>
                                <div class="form-group col-12 col-md-2">
                                    <label for="view-page">Folha</label>
                                    <input type="text" class="form-control" id="view-page" readonly>
                                </div>
                                <div class="form-group col-12 col-md-3">
                                    <label for="view-registry-date">Data de Registro</label>
                                    <input type="text" class="form-control" id="view-registry-date" readonly>
                                </div>
                                <div class="form-group col-12 col-md-3">
                                    <label for="view-birthdate">Data de Nascimento</label>
                                    <input type="text" class="form-control" id="view-birthdate" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="view-name">Nome do Registrado</label>
                                <input type="text" class="form-control" id="view-name" readonly>
                            </div>
                            <div class="form-group">
                                <label for="view-father-name">Nome do Pai</label>
                                <input type="text" class="form-control" id="view-father-name" readonly>
                            </div>
                            <div class="form-group">
                                <label for="view-mother-name">Nome da Mãe</label>
                                <input type="text" class="form-control" id="view-mother-name" readonly>
                            </div>

                            <!-- Área de Visualização de Anexos -->
                            <div class="mt-4">
                                <h5>Anexos</h5>
                                <div id="view-attachments-container">
                                    <!-- O PDF será exibido aqui -->
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

<script src="../../script/jquery-3.5.1.min.js"></script>
<script src="../../script/bootstrap.min.js"></script>
<script src="../../script/bootstrap.bundle.min.js"></script>
<script src="../../script/jquery.mask.min.js"></script>
<script src="../../script/jquery.dataTables.min.js"></script>
<script src="../../script/dataTables.bootstrap4.min.js"></script>
<script src="../../script/sweetalert2.js"></script>
<script>
    $(document).ready(function() {
        var addAttachments = []; // Para armazenar anexos adicionados no modal "Adicionar Registro"
        var attachmentIdToRemove = null; // Variável para armazenar o ID do anexo a ser removido
        
        // Função para formatar data para pt-br
        function formatDate(date) {
            if (!date) return '';
            const [year, month, day] = date.split('-');
            return `${day}/${month}/${year}`;
        }

        // Função para exibir registros
        function displayRegistries(registries) {
            var tableBody = $('#registry-table-body');
            tableBody.empty(); // Limpar linhas existentes

            $.each(registries, function(index, registry) {
                var filiacao = registry.nome_pai ? `${registry.nome_pai} e ${registry.nome_mae}` : registry.nome_mae;

                var row = '<tr>' +
                    '<td>' + registry.termo + '</td>' +
                    '<td>' + registry.livro + '</td>' +
                    '<td>' + registry.folha + '</td>' +
                    '<td>' + registry.nome_registrado + '</td>' +
                    '<td>' + filiacao + '</td>' +
                    '<td data-order="' + registry.data_nascimento + '">' + formatDate(registry.data_nascimento) + '</td>' +
                    '<td data-order="' + registry.data_registro + '">' + formatDate(registry.data_registro) + '</td>' +
                    '<td>' +
                        '<button title="Visualizar Registro" class="btn btn-info btn-view" data-id="' + registry.id + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                        '<button title="Editar Registro" class="btn btn-edit" data-id="' + registry.id + '"><i class="fa fa-pencil" aria-hidden="true"></i></button> ' +
                        <?php if ($nivel_de_acesso === 'administrador'): ?>
                            <button title="Remover Registro" class="btn btn-delete" data-id="<?php echo $registry['id']; ?>">
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>
                        <?php endif; ?>
                    '</td>' +
                    '</tr>';
                tableBody.append(row);
            });

            // Inicializar o DataTable após os dados serem carregados
            $('#tabelaResultados').DataTable({
                "language": {
                    "url": "../../style/Portuguese-Brasil.json"
                },
                "pageLength": 10,
                "order": [[0, 'desc']],
                "destroy": true
            });
        }

        // Função para filtrar registros
        function filterRegistries() {
            var searchTerm = $('#search-term').val().toLowerCase();
            var searchTermTerm = $('#search-term-term').val().toLowerCase();
            var searchTermBook = $('#search-term-book').val().toLowerCase();
            var searchTermPage = $('#search-term-page').val().toLowerCase();
            var searchFather = $('#search-father').val().toLowerCase();
            var searchMother = $('#search-mother').val().toLowerCase();
            var birthDate = $('#search-birthdate').val();
            var registryDate = $('#search-registry-date').val();

            var urlParams = new URLSearchParams();
            if (searchTerm) urlParams.append('searchTerm', searchTerm);
            if (searchTermTerm) urlParams.append('searchTermTerm', searchTermTerm);
            if (searchTermBook) urlParams.append('searchTermBook', searchTermBook);
            if (searchTermPage) urlParams.append('searchTermPage', searchTermPage);
            if (searchFather) urlParams.append('searchFather', searchFather);
            if (searchMother) urlParams.append('searchMother', searchMother);
            if (birthDate) urlParams.append('birthDate', birthDate);
            if (registryDate) urlParams.append('registryDate', registryDate);

            window.location.search = urlParams.toString();
        }

        // Carregar registros filtrados com base na URL
        function loadFilteredRegistries() {
            var params = new URLSearchParams(window.location.search);
            var searchTerm = params.get('searchTerm') || '';
            var searchTermTerm = params.get('searchTermTerm') || '';
            var searchTermBook = params.get('searchTermBook') || '';
            var searchTermPage = params.get('searchTermPage') || '';
            var searchFather = params.get('searchFather') || '';
            var searchMother = params.get('searchMother') || '';
            var birthDate = params.get('birthDate') || '';
            var registryDate = params.get('registryDate') || '';

            $('#search-term').val(searchTerm);
            $('#search-term-term').val(searchTermTerm);
            $('#search-term-book').val(searchTermBook);
            $('#search-term-page').val(searchTermPage);
            $('#search-father').val(searchFather);
            $('#search-mother').val(searchMother);
            $('#search-birthdate').val(birthDate);
            $('#search-registry-date').val(registryDate);

            $.ajax({
                type: 'GET',
                url: 'carregar_registros.php',
                data: {
                    searchTerm: searchTerm,
                    searchTermTerm: searchTermTerm,
                    searchTermBook: searchTermBook,
                    searchTermPage: searchTermPage,
                    searchFather: searchFather,
                    searchMother: searchMother,
                    birthDate: birthDate,
                    registryDate: registryDate
                },
                success: function(response) {
                    var registries = JSON.parse(response);
                    displayRegistries(registries);
                }
            });
        }

        // Evento de clique do botão de filtro
        $('#filter-button').on('click', function() {
            filterRegistries();
        });

        // Carregar registros filtrados ao carregar a página
        loadFilteredRegistries();

        // Enviar formulário de registro
        $('#registry-form').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);

            // Adicionar caminhos dos anexos temporários ao FormData
            addAttachments.forEach(function(filePath, index) {
                formData.append('arquivo_pdf_paths[]', filePath);
            });

            $.ajax({
                type: 'POST',
                url: 'salvar_registro.php',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.status === 'duplicate') {
                        Swal.fire({
                            title: 'Registro Duplicado!',
                            text: 'Já existe um registro com o mesmo livro, folha, termo e data de registro em nome de: ' + result.nome_registrado + '. Deseja continuar?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Sim, continuar',
                            cancelButtonText: 'Não, cancelar'
                        }).then((willSubmit) => {
                            if (willSubmit.isConfirmed) {
                                // Forçar o envio mesmo com o registro duplicado
                                $.ajax({
                                    type: 'POST',
                                    url: 'salvar_registro.php',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    success: function(response) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Sucesso!',
                                            text: 'Registro adicionado com sucesso.',
                                            confirmButtonText: 'Ok'
                                        }).then(() => {
                                            $('#addRegistryModal').modal('hide');
                                            loadFilteredRegistries();
                                            addAttachments = []; 
                                        });
                                    }
                                });
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Registro adicionado com sucesso.',
                            confirmButtonText: 'Ok'
                        }).then(() => {
                            $('#addRegistryModal').modal('hide');
                            loadFilteredRegistries(); 
                            addAttachments = []; 
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Houve um problema ao adicionar o registro.',
                        confirmButtonText: 'Ok'
                    });
                }
            });
        });

        // Adicionar Anexo no Modal "Adicionar Registro"
        $('#add-attachment-btn').on('click', function() {
            var fileInput = $('#add-pdf-file')[0];
            if (fileInput.files.length > 0) {
                var file = fileInput.files[0];
                var formData = new FormData();
                formData.append('arquivo_pdf', file);

                $.ajax({
                    type: 'POST',
                    url: 'upload_temp_anexo.php',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        var data = JSON.parse(response);
                        if (data.success) {
                            var filePath = data.file_path;
                            addAttachments.push(filePath);

                            var row = '<tr>' +
                                '<td>' + file.name + '</td>' +
                                '<td><button class="btn btn-danger btn-remove-add-attachment"><i class="fa fa-trash" aria-hidden="true"></i></button></td>' +
                                '</tr>';
                            $('#add-attachments-table-body').append(row);

                            // Limpar o input de arquivo
                            fileInput.value = '';
                        } else {
                            alert(data.error);
                        }
                    }
                });
            }
        });

        // Remover Anexo no Modal "Adicionar Registro"
        $(document).on('click', '.btn-remove-add-attachment', function() {
            var rowIndex = $(this).closest('tr').index();
            addAttachments.splice(rowIndex, 1);
            $(this).closest('tr').remove();
        });

        // Função para carregar anexos do registro
        function loadAttachments(registryId, isViewMode = false) {
            $.ajax({
                type: 'GET',
                url: 'get_anexos.php',
                data: { id_nascimento: registryId },
                success: function(response) {
                    var attachments = JSON.parse(response);
                    var container = isViewMode ? $('#view-attachments-container') : $('#edit-attachments-table-body');
                    container.empty(); // Limpar o conteúdo existente

                    if (isViewMode) {
                        $.each(attachments, function(index, attachment) {
                            // Exibir o PDF em um iframe
                            var iframe = '<iframe src="' + attachment.caminho_anexo + '" width="100%" height="600px" style="border: none;"></iframe>';
                            container.append(iframe);
                        });
                    } else {
                        // Código para editar e remover anexos, conforme sua implementação atual
                        // Este bloco permanece inalterado
                        $.each(attachments, function(index, attachment) {
                            var formattedDate = formatDate(attachment.data.split(' ')[0]); // Formatar data sem hora

                            var row = '<tr>' +
                                '<td>' + attachment.caminho_anexo.split('/').pop() + '</td>' +
                                '<td>' + formattedDate + '</td>' +
                                '<td>' +
                                    '<a href="' + attachment.caminho_anexo + '" target="_blank" title="Visualizar Anexo" style="width: 40px; height: 40px;margin-top: 5px; margin-right: 5px;" class="btn btn-info"><i class="fa fa-eye" aria-hidden="true"></i></a>' +
                                    (!isViewMode ? '<button type="button" style="width: 40px; height: 40px" title="Remover Anexo" class="btn btn-danger btn-delete-attachment" data-id="' + attachment.id + '"><i class="fa fa-trash" aria-hidden="true"></i></button>' : '') +
                                '</td>' +
                                '</tr>';
                            container.append(row);
                        });
                    }
                }
            });
        }

        // Impedir o disparo da ação de "Salvar registro" ao clicar no botão de "Remover Anexo"
        $(document).on('click', '.btn-delete-attachment', function(event) {
            event.stopPropagation(); 
            event.preventDefault(); 
            attachmentIdToRemove = $(this).data('id');
            
            // Substituir o modal pela confirmação via SweetAlert2
            Swal.fire({
                title: 'Tem certeza?',
                text: 'Você realmente deseja remover este anexo?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'POST',
                        url: 'remover_anexo.php',
                        data: { id: attachmentIdToRemove },
                        success: function(response) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Anexo removido com sucesso.',
                                confirmButtonText: 'Ok'
                            }).then(() => {
                                loadAttachments($('#edit-id').val()); // Recarregar anexos após remover
                            });
                        },
                        error: function(xhr, status, error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Ocorreu um problema ao remover o anexo.',
                                confirmButtonText: 'Ok'
                            });
                        }
                    });
                }
            });
        });


        // Enviar formulário de edição
        $('#edit-registry-form').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);

            $.ajax({
                type: 'POST',
                url: 'atualizar_registro.php',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Registro atualizado com sucesso.',
                        confirmButtonText: 'Ok'
                    }).then(() => {
                        $('#editRegistryModal').modal('hide');
                        loadFilteredRegistries(); // Carregar registros recentes após atualizar
                    });
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Ocorreu um problema ao atualizar o registro.',
                        confirmButtonText: 'Ok'
                    });
                }
            });
        });


        // Adicionar Anexo no Modal "Editar Registro"
        $('#edit-add-attachment-btn').on('click', function() {
            var fileInput = $('#edit-pdf-file')[0];
            if (fileInput.files.length > 0) {
                var file = fileInput.files[0];
                var fileName = file.name;
                var registryId = $('#edit-id').val();

                var formData = new FormData();
                formData.append('arquivo_pdf', file);
                formData.append('id_nascimento', registryId);

                $.ajax({
                    type: 'POST',
                    url: 'salvar_anexo.php',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Anexo adicionado com sucesso.',
                            confirmButtonText: 'Ok'
                        }).then(() => {
                            loadAttachments(registryId); // Recarregar anexos após adicionar
                            fileInput.value = ''; // Limpar o input de arquivo
                        });
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Erro ao adicionar anexo: ' + error,
                            confirmButtonText: 'Ok'
                        });
                    }
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Aviso',
                    text: 'Por favor, selecione um arquivo para adicionar.',
                    confirmButtonText: 'Ok'
                });
            }
        });

        // Visualizar Registro
        $(document).on('click', '.btn-view', function() {
            var registryId = $(this).data('id');

            $.ajax({
                type: 'GET',
                url: 'get_registros.php',
                data: {id: registryId},
                success: function(response) {
                    var registry = JSON.parse(response);

                    $('#view-term').val(registry.termo);
                    $('#view-book').val(registry.livro);
                    $('#view-page').val(registry.folha);
                    $('#view-registry-date').val(formatDate(registry.data_registro));
                    $('#view-birthdate').val(formatDate(registry.data_nascimento));
                    $('#view-name').val(registry.nome_registrado);
                    $('#view-father-name').val(registry.nome_pai);
                    $('#view-mother-name').val(registry.nome_mae);

                    loadAttachments(registry.id, true); // Carregar anexos no modo de visualização
                    $('#viewRegistryModal').modal('show');
                }
            });
        });

        // Editar registro
        $(document).on('click', '.btn-edit', function() {
            var registryId = $(this).data('id');

            $.ajax({
                type: 'GET',
                url: 'get_registros.php',
                data: {id: registryId},
                success: function(response) {
                    var registry = JSON.parse(response);

                    // Preencher os campos de edição
                    $('#edit-id').val(registry.id);
                    $('#edit-term').val(registry.termo);
                    $('#edit-book').val(registry.livro);
                    $('#edit-page').val(registry.folha);
                    $('#edit-name').val(registry.nome_registrado);
                    $('#edit-birthdate').val(registry.data_nascimento);
                    $('#edit-father-name').val(registry.nome_pai);
                    $('#edit-mother-name').val(registry.nome_mae);
                    $('#edit-registry-date').val(registry.data_registro);

                    // Carregar anexos
                    loadAttachments(registry.id);
                    
                    $('#editRegistryModal').modal('show');
                }
            });
        });

        // Excluir registro
        $(document).on('click', '.btn-delete', function() {
            var registryId = $(this).data('id');

            Swal.fire({
                title: 'Tem certeza?',
                text: 'Você realmente deseja excluir este registro?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'POST',
                        url: 'remover_registro.php',
                        data: { id: registryId },
                        success: function(response) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Registro removido com sucesso.',
                                confirmButtonText: 'Ok'
                            }).then(() => {
                                loadFilteredRegistries(); // Carregar registros recentes após excluir
                            });
                        },
                        error: function(xhr, status, error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Ocorreu um problema ao remover o registro.',
                                confirmButtonText: 'Ok'
                            });
                        }
                    });
                }
            });
        });

        // Inicializar o modal de adicionar
        $('#addRegistryModal').modal({
            show: false
        });

        // Função para mostrar alertas personalizados com SweetAlert2
        function showAlert(message, type) {
            let iconType = '';
            
            // Definir o tipo de ícone com base no tipo de mensagem
            switch (type) {
                case 'success':
                    iconType = 'success';
                    break;
                case 'error':
                    iconType = 'error';
                    break;
                case 'warning':
                    iconType = 'warning';
                    break;
                default:
                    iconType = 'info'; // Padrão para qualquer outro caso
            }

            // Usando SweetAlert2 para exibir a mensagem
            Swal.fire({
                icon: iconType,
                title: message,
                confirmButtonText: 'Ok'
            });
        }

        // Adicionar evento para recarregar a página ao fechar os modais
        $('#editRegistryModal').on('hidden.bs.modal', function () {
            location.reload();
        });

        $('#viewRegistryModal').on('hidden.bs.modal', function () {
            location.reload();
        });

        $('#addRegistryModal').on('hidden.bs.modal', function () {
            location.reload();
        });
    });

    $(document).ready(function() {
        var currentYear = new Date().getFullYear();

        // Validação de data no modal de Adição de Registro
        $('#registry-date, #birthdate').on('change', function() {
            var selectedDate = new Date($(this).val());
            if (selectedDate.getFullYear() > currentYear) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Data inválida',
                    text: 'O ano não pode ser maior que o ano atual.',
                    confirmButtonText: 'Ok'
                });
                $(this).val(''); // Limpa o campo da data
            }
        });

        // Validação de data no modal de Edição de Registro
        $('#edit-registry-date, #edit-birthdate').on('change', function() {
            var selectedDate = new Date($(this).val());
            if (selectedDate.getFullYear() > currentYear) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Data inválida',
                    text: 'O ano não pode ser maior que o ano atual.',
                    confirmButtonText: 'Ok'
                });
                $(this).val(''); // Limpa o campo da data
            }
        });

    });

    $(document).ready(function() {
        var currentYear = new Date().getFullYear();

        // Função de validação de data
        function validateDate(input) {
            var selectedDate = new Date($(input).val());
            if (selectedDate.getFullYear() > currentYear) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Data inválida',
                    text: 'O ano não pode ser maior que o ano atual.',
                    confirmButtonText: 'Ok'
                });
                $(input).val(''); // Limpa o campo da data
            }
        }

        // Aplicar a validação de data nos campos de filtro de pesquisa
        $('#search-birthdate, #search-registry-date').on('change', function() {
            // Certifique-se de que há um valor antes de validar
            if ($(this).val()) {
                validateDate(this);
            }
        });
    });

</script>

</body>
</html>
