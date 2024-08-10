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
        .btn-edit {
            margin-left: 5px;
        }

        .btn-info {
            width: 40px;
            height: 40px;
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

        /* Estilo do modal de confirmação */
        #confirmRemoveAttachmentModal .modal-dialog {
            max-width: 400px;
            margin: 1.75rem auto;
        }

        #confirmRemoveAttachmentModal .modal-content {
            padding: 20px;
            border-radius: 10px;
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
                <button id="filter-button" class="btn btn-primary w-100">Filtrar</button>
            </div>
            <div class="col-md-6 col-lg-3 mb-2">
                <button class="btn btn-add w-100" data-toggle="modal" data-target="#addRegistryModal">+ Adicionar</button>
            </div>
        </div>
        <hr>
        <div class="table-responsive">
            <h5>Resultados da Pesquisa</h5>
            <table id="tabelaResultados" class="table table-striped table-bordered">
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
                        echo '<td>' . date("d/m/Y", strtotime($row['data_nascimento'])) . '</td>';
                        echo '<td>' . date("d/m/Y", strtotime($row['data_registro'])) . '</td>';
                        echo '<td>' .
                                '<button class="btn btn-info btn-view" data-id="' . $row['id'] . '"><i class="fa fa-eye" aria-hidden="true"></i></button>' .
                                '<button class="btn btn-edit" data-id="' . $row['id'] . '"><i class="fa fa-pencil" aria-hidden="true"></i></button> ' .
                                '<button class="btn btn-delete" data-id="' . $row['id'] . '"><i class="fa fa-trash" aria-hidden="true"></i></button>' .
                             '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Modal de Adição de Registro -->
        <div class="modal fade" id="addRegistryModal" tabindex="-1" role="dialog" aria-labelledby="addRegistryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-custom" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addRegistryModalLabel">Adicionar Registro</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="registry-form">
                            <div class="form-row">
                                <div class="form-group col-md-2">
                                    <label for="term">Termo</label>
                                    <input type="number" class="form-control" id="term" name="termo" required min="0">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="book">Livro</label>
                                    <input type="number" class="form-control" id="book" name="livro" required min="0">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="page">Folha</label>
                                    <input type="number" class="form-control" id="page" name="folha" required min="0">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="registry-date">Data de Registro</label>
                                    <input type="date" class="form-control" id="registry-date" name="data_registro" required>
                                </div>
                                <div class="form-group col-md-3">
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
            <div class="modal-dialog modal-custom" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editRegistryModalLabel">Editar Registro</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="edit-registry-form">
                            <input type="hidden" id="edit-id" name="id">
                            <div class="form-row">
                                <div class="form-group col-md-2">
                                    <label for="edit-term">Termo</label>
                                    <input type="number" class="form-control" id="edit-term" name="termo" required min="0">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="edit-book">Livro</label>
                                    <input type="number" class="form-control" id="edit-book" name="livro" required min="0">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="edit-page">Folha</label>
                                    <input type="number" class="form-control" id="edit-page" name="folha" required min="0">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="edit-registry-date">Data de Registro</label>
                                    <input type="date" class="form-control" id="edit-registry-date" name="data_registro" required>
                                </div>
                                <div class="form-group col-md-3">
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
            <div class="modal-dialog modal-custom" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewRegistryModalLabel">Visualizar Registro</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form>
                            <div class="form-row">
                                <div class="form-group col-md-2">
                                    <label for="view-term">Termo</label>
                                    <input type="text" class="form-control" id="view-term" readonly>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="view-book">Livro</label>
                                    <input type="text" class="form-control" id="view-book" readonly>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="view-page">Folha</label>
                                    <input type="text" class="form-control" id="view-page" readonly>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="view-registry-date">Data de Registro</label>
                                    <input type="text" class="form-control" id="view-registry-date" readonly>
                                </div>
                                <div class="form-group col-md-3">
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
                                    <tbody id="view-attachments-table-body">
                                        <!-- Linhas serão adicionadas dinamicamente -->
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Confirmação de Remoção de Anexo -->
        <div class="modal fade" id="confirmRemoveAttachmentModal" tabindex="-1" role="dialog" aria-labelledby="confirmRemoveAttachmentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmRemoveAttachmentModalLabel">Confirmar Remoção</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        Tem certeza de que deseja remover este anexo?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" id="confirmRemoveBtn" class="btn btn-danger btn-confirm-remove">Remover</button>
                    </div>
                </div>
            </div>
        </div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/jquery.dataTables.min.js"></script>
<script src="../script/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        var addAttachments = []; // Para armazenar anexos adicionados no modal "Adicionar Registro"
        var attachmentIdToRemove = null; // Variável para armazenar o ID do anexo a ser removido
        
        // Inicializar DataTable
        $('#tabelaResultados').DataTable({
            "language": {
                "url": "../style/Portuguese-Brasil.json"
            },
            "pageLength": 10
        });

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
                    '<td>' + formatDate(registry.data_nascimento) + '</td>' +
                    '<td>' + formatDate(registry.data_registro) + '</td>' +
                    '<td>' +
                        '<button title="Visualizar Registro" class="btn btn-info btn-view" data-id="' + registry.id + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                        '<button title="Editar Registro" class="btn btn-edit" data-id="' + registry.id + '"><i class="fa fa-pencil" aria-hidden="true"></i></button> ' +
                        '<button title="Remover Registro" class="btn btn-delete" data-id="' + registry.id + '"><i class="fa fa-trash" aria-hidden="true"></i></button>' +
                    '</td>' +
                    '</tr>';
                tableBody.append(row);
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

            // Adicionar anexos ao FormData
            addAttachments.forEach(function(file, index) {
                formData.append('arquivo_pdf_' + index, file);
            });

            $.ajax({
                type: 'POST',
                url: 'salvar_registro.php',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    showAlert('Registro adicionado com sucesso', 'success');
                    $('#addRegistryModal').modal('hide');
                    loadFilteredRegistries(); // Carregar registros recentes após salvar
                    addAttachments = []; // Limpar anexos após salvar
                }
            });
        });

        // Adicionar Anexo no Modal "Adicionar Registro"
        $('#add-attachment-btn').on('click', function() {
            var fileInput = $('#add-pdf-file')[0];
            if (fileInput.files.length > 0) {
                var file = fileInput.files[0];
                var fileName = file.name;
                addAttachments.push(file);

                var row = '<tr>' +
                    '<td>' + fileName + '</td>' +
                    '<td><button class="btn btn-danger btn-remove-add-attachment"><i class="fa fa-trash" aria-hidden="true"></i></button></td>' +
                    '</tr>';
                $('#add-attachments-table-body').append(row);

                // Limpar o input de arquivo
                fileInput.value = '';
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
                data: {id_nascimento: registryId},
                success: function(response) {
                    var attachments = JSON.parse(response);
                    var tableBody = isViewMode ? $('#view-attachments-table-body') : $('#edit-attachments-table-body');
                    tableBody.empty(); // Limpar linhas existentes

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
                        tableBody.append(row);
                    });
                }
            });
        }

        // Impedir o disparo da ação de "Salvar registro" ao clicar no botão de "Remover Anexo"
        $(document).on('click', '.btn-delete-attachment', function(event) {
            event.stopPropagation(); // Previne o clique de se propagar para outros elementos
            event.preventDefault(); // Previne a ação padrão do botão
            attachmentIdToRemove = $(this).data('id');
            $('#confirmRemoveAttachmentModal').modal('show');
        });

        // Confirmar remoção de anexo
        $('#confirmRemoveBtn').on('click', function() {
            $.ajax({
                type: 'POST',
                url: 'remover_anexo.php',
                data: { id: attachmentIdToRemove },
                success: function(response) {
                    showAlert('Anexo removido com sucesso', 'success');
                    $('#confirmRemoveAttachmentModal').modal('hide');
                    loadAttachments($('#edit-id').val()); // Recarregar anexos após remover
                }
            });
        });

        // Remover anexo no modal de edição
        $(document).on('click', '.btn-delete-attachment', function() {
            attachmentIdToRemove = $(this).data('id');
            $('#confirmRemoveAttachmentModal').modal('show');
        });

        // Confirmar remoção de anexo
        $('#confirmRemoveBtn').on('click', function() {
            $.ajax({
                type: 'POST',
                url: 'remover_anexo.php',
                data: { id: attachmentIdToRemove },
                success: function(response) {
                    showAlert('Anexo removido com sucesso', 'success');
                    $('#confirmRemoveAttachmentModal').modal('hide');
                    loadAttachments($('#edit-id').val()); // Recarregar anexos após remover
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
                    showAlert('Registro atualizado com sucesso', 'success');
                    $('#editRegistryModal').modal('hide');
                    loadFilteredRegistries(); // Carregar registros recentes após atualizar
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
                        showAlert('Anexo adicionado com sucesso', 'success');
                        loadAttachments(registryId); // Recarregar anexos após adicionar
                        fileInput.value = ''; // Limpar o input de arquivo
                    },
                    error: function(xhr, status, error) {
                        showAlert('Erro ao adicionar anexo: ' + error, 'error');
                    }
                });
            } else {
                showAlert('Por favor, selecione um arquivo para adicionar.', 'warning');
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

            if (confirm('Tem certeza de que deseja excluir este registro?')) {
                $.ajax({
                    type: 'POST',
                    url: 'remover_registro.php',
                    data: { id: registryId },
                    success: function(response) {
                        showAlert('Registro removido com sucesso', 'success');
                        loadFilteredRegistries(); // Carregar registros recentes após excluir
                    }
                });
            }
        });

        // Inicializar o modal de adicionar
        $('#addRegistryModal').modal({
            show: false
        });

        // Função para mostrar alertas personalizados
        function showAlert(message, type) {
            var icon = '';
            switch (type) {
                case 'success':
                    icon = '✔️';
                    break;
                case 'error':
                    icon = '❌';
                    break;
                case 'warning':
                    icon = '⚠️';
                    break;
                default:
                    icon = '';
            }

            var alertPopup = $('<div class="alert-popup alert-' + type + '"><span>' + icon + ' ' + message + '</span></div>');
            $('body').append(alertPopup);

            setTimeout(function() {
                alertPopup.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
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
</script>

</body>
</html>
