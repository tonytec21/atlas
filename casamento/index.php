<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indexador Registro Civil - Casamento</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
</head>
<body class="light-mode">
    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
        <button class="mode-switch">🔄 Modo</button>
        <a href="../index.php">Página Inicial</a>
        <a href="../nascimento/index.php">Nascimento</a>
        <a href="../casamento/index.php">Casamento</a>
        <a href="../obito/index.php">Óbito</a>
        <a href="../procuracao/index.php">Procuração</a>
        <a href="../escritura/index.php">Escritura</a>
        <a href="../protesto/index.php">Protesto</a>
        <a href="../ri/index.php">Registro de Imóveis</a>
        <a href="../rcpj/index.php">Pessoas Jurídicas</a>
        <a href="../rtd/nascimento/index.php">Títulos e Documentos</a>
    </div>

    <button class="openbtn" onclick="openNav()">&#9776; Menu</button>

    <div id="main" class="main-content">
        <div class="container">
            <h1 class="my-4">Indexador Registro Civil - Casamento</h1>
            
            <!-- Formulário de Pesquisa e Filtros -->
            <div class="row mb-3">
                <div class="col-md-6 col-lg-4 mb-2">
                    <input type="text" id="search-term-1-nubente" class="form-control" placeholder="Nome do 1º Nubente">
                </div>
                <div class="col-md-6 col-lg-4 mb-2">
                    <input type="text" id="search-term-2-nubente" class="form-control" placeholder="Nome do 2º Nubente">
                </div>
                <div class="col-md-6 col-lg-4 mb-2">
                    <input type="text" id="search-term-term" class="form-control" placeholder="Termo">
                </div>
                <div class="col-md-6 col-lg-4 mb-2">
                    <input type="text" id="search-term-book" class="form-control" placeholder="Livro">
                </div>
                <div class="col-md-6 col-lg-4 mb-2">
                    <input type="text" id="search-term-page" class="form-control" placeholder="Folha">
                </div>
                <div class="col-md-6 col-lg-4 mb-2">
                    <input type="text" id="search-term-parentage-1-nubente" class="form-control" placeholder="Filiação do 1º Nubente">
                </div>
                <div class="col-md-6 col-lg-4 mb-2">
                    <input type="text" id="search-term-parentage-2-nubente" class="form-control" placeholder="Filiação do 2º Nubente">
                </div>
                <div class="col-md-6 col-lg-4 mb-2">
                    <input type="date" id="search-term-marriage-date" class="form-control" placeholder="Data do Casamento">
                </div>
                <div class="col-md-6 col-lg-4 mb-2">
                    <button id="filter-button" class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="col-md-6 col-lg-4 mb-2">
                    <button class="btn btn-add w-100" data-toggle="modal" data-target="#addRegistryModal">+ Adicionar</button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Termo</th>
                            <th>Livro</th>
                            <th>Folha</th>
                            <th>Nome do 1º Nubente</th>
                            <th>Data de Nascimento do 1º Nubente</th>
                            <th>Filiação do 1º Nubente</th>
                            <th>Nome do 2º Nubente</th>
                            <th>Data de Nascimento do 2º Nubente</th>
                            <th>Filiação do 2º Nubente</th>
                            <th>Data do Casamento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="registry-table-body">
                        <!-- Linhas serão adicionadas dinamicamente -->
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
                                    <div class="form-group col-md-3">
                                        <label for="term">Termo</label>
                                        <input type="text" class="form-control" id="term" name="termo" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="book">Livro</label>
                                        <input type="text" class="form-control" id="book" name="livro" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="page">Folha</label>
                                        <input type="text" class="form-control" id="page" name="folha" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="marriage-date">Data do Casamento</label>
                                        <input type="date" class="form-control" id="marriage-date" name="data_casamento" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="name_1_nubente">Nome do 1º Nubente</label>
                                    <input type="text" class="form-control" id="name_1_nubente" name="nome_1_nubente" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="birthdate_1_nubente">Data de Nascimento do 1º Nubente</label>
                                        <input type="date" class="form-control" id="birthdate_1_nubente" name="data_nascimento_1_nubente" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="parentage_1_nubente">Filiação do 1º Nubente</label>
                                        <input type="text" class="form-control" id="parentage_1_nubente" name="filiacao_1_nubente" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="name_2_nubente">Nome do 2º Nubente</label>
                                    <input type="text" class="form-control" id="name_2_nubente" name="nome_2_nubente" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="birthdate_2_nubente">Data de Nascimento do 2º Nubente</label>
                                        <input type="date" class="form-control" id="birthdate_2_nubente" name="data_nascimento_2_nubente" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="parentage_2_nubente">Filiação do 2º Nubente</label>
                                        <input type="text" class="form-control" id="parentage_2_nubente" name="filiacao_2_nubente" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="pdf-file">Arquivo PDF</label>
                                    <input type="file" class="form-control-file" id="pdf-file" name="arquivo_pdf">
                                </div>
                                <button type="submit" class="btn btn-primary">Salvar</button>
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
                                    <div class="form-group col-md-3">
                                        <label for="edit-term">Termo</label>
                                        <input type="text" class="form-control" id="edit-term" name="termo" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="edit-book">Livro</label>
                                        <input type="text" class="form-control" id="edit-book" name="livro" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="edit-page">Folha</label>
                                        <input type="text" class="form-control" id="edit-page" name="folha" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="edit-marriage-date">Data do Casamento</label>
                                        <input type="date" class="form-control" id="edit-marriage-date" name="data_casamento" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="edit-name_1_nubente">Nome do 1º Nubente</label>
                                    <input type="text" class="form-control" id="edit-name_1_nubente" name="nome_1_nubente" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="edit-birthdate_1_nubente">Data de Nascimento do 1º Nubente</label>
                                        <input type="date" class="form-control" id="edit-birthdate_1_nubente" name="data_nascimento_1_nubente" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="edit-parentage_1_nubente">Filiação do 1º Nubente</label>
                                        <input type="text" class="form-control" id="edit-parentage_1_nubente" name="filiacao_1_nubente" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="edit-name_2_nubente">Nome do 2º Nubente</label>
                                    <input type="text" class="form-control" id="edit-name_2_nubente" name="nome_2_nubente" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="edit-birthdate_2_nubente">Data de Nascimento do 2º Nubente</label>
                                        <input type="date" class="form-control" id="edit-birthdate_2_nubente" name="data_nascimento_2_nubente" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="edit-parentage_2_nubente">Filiação do 2º Nubente</label>
                                        <input type="text" class="form-control" id="edit-parentage_2_nubente" name="filiacao_2_nubente" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="edit-pdf-file">Arquivo PDF</label>
                                    <input type="file" class="form-control-file" id="edit-pdf-file" name="arquivo_pdf">
                                </div>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script>
        function openNav() {
            document.getElementById("mySidebar").style.width = "250px";
            document.getElementById("main").style.marginLeft = "250px";
        }

        function closeNav() {
            document.getElementById("mySidebar").style.width = "0";
            document.getElementById("main").style.marginLeft = "0";
        }

        $(document).ready(function() {
            // Função para alternar modos claro e escuro
            $('.mode-switch').on('click', function() {
                $('body').toggleClass('dark-mode light-mode');
            });

            // Função para normalizar texto
            function normalizeText(text) {
                return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
            }

            // Função para formatar data para pt-br
            function formatDate(date) {
                if (!date) return '';
                const [year, month, day] = date.split('-');
                return `${day}/${month}/${year}`;
            }

            // Função para impedir espaços iniciais
            function preventInitialSpace(event) {
                if (event.target.value === '' && event.which === 32) {
                    event.preventDefault();
                }
            }

            // Aplicar prevenção de espaços iniciais aos campos de entrada
            $('#search-term-1-nubente, #search-term-2-nubente, #search-term-term, #search-term-book, #search-term-page, #search-term-parentage-1-nubente, #search-term-parentage-2-nubente').on('keypress', preventInitialSpace);

            // Função para exibir registros
            function displayRegistries(registries) {
                var tableBody = $('#registry-table-body');
                tableBody.empty(); // Limpar linhas existentes

                // Adicionar novas linhas para cada registro
                $.each(registries, function(index, registry) {
                    var row = '<tr>' +
                        '<td>' + (index + 1) + '</td>' +
                        '<td>' + registry.termo + '</td>' +
                        '<td>' + registry.livro + '</td>' +
                        '<td>' + registry.folha + '</td>' +
                        '<td>' + registry.nome_1_nubente + '</td>' +
                        '<td>' + formatDate(registry.data_nascimento_1_nubente) + '</td>' +
                        '<td>' + registry.filiacao_1_nubente + '</td>' +
                        '<td>' + registry.nome_2_nubente + '</td>' +
                        '<td>' + formatDate(registry.data_nascimento_2_nubente) + '</td>' +
                        '<td>' + registry.filiacao_2_nubente + '</td>' +
                        '<td>' + formatDate(registry.data_casamento) + '</td>' +
                        '<td>' +
                            '<button class="btn btn-info" onclick="window.open(\'' + registry.arquivo_pdf + '\', \'_blank\')"><i class="fa fa-eye" aria-hidden="true"></i></button>'+
                            '<button class="btn btn-edit" data-id="' + index + '"><i class="fa fa-pencil" aria-hidden="true"></i></button> ' +
                            '<button class="btn btn-delete" data-id="' + index + '"><i class="fa fa-trash" aria-hidden="true"></i></button>' +
                        '</td>' +
                        '</tr>';
                    tableBody.append(row);
                });
            }

            // Função para filtrar registros
            function filterRegistries(event) {
                var searchTerm1Nubente = normalizeText($('#search-term-1-nubente').val());
                var searchTerm2Nubente = normalizeText($('#search-term-2-nubente').val());
                var searchTermTerm = normalizeText($('#search-term-term').val());
                var searchTermBook = normalizeText($('#search-term-book').val());
                var searchTermPage = normalizeText($('#search-term-page').val());
                var searchTermParentage1Nubente = normalizeText($('#search-term-parentage-1-nubente').val());
                var searchTermParentage2Nubente = normalizeText($('#search-term-parentage-2-nubente').val());
                var searchTermMarriageDate = $('#search-term-marriage-date').val();

                // Verificar se os campos de "Nome dos nubentes" e "Filiação" têm pelo menos 2 caracteres
                if ((searchTerm1Nubente.length > 0 && searchTerm1Nubente.length < 2) || 
                    (searchTerm2Nubente.length > 0 && searchTerm2Nubente.length < 2) || 
                    (searchTermParentage1Nubente.length > 0 && searchTermParentage1Nubente.length < 2) || 
                    (searchTermParentage2Nubente.length > 0 && searchTermParentage2Nubente.length < 2)) {
                    $('#registry-table-body').empty(); // Limpar a tabela se os critérios mínimos não forem atendidos
                    return;
                }

                // Ignorar teclas de função e outras teclas não alfanuméricas
                var ignoredKeys = [8, 16, 17, 18, 20, 27, 32, 33, 34, 35, 36, 37, 38, 39, 40, 45, 46]; // Backspace, Shift, Ctrl, Alt, CapsLock, Escape, Space, Page Up, Page Down, End, Home, setas, Insert, Delete
                if (event && ignoredKeys.includes(event.which)) {
                    return;
                }

                $.ajax({
                    type: 'GET',
                    url: 'get_registries.php',
                    success: function(response) {
                        var registries = JSON.parse(response);
                        var filteredRegistries = registries.filter(function(registry) {
                            var normalizedRegistryName1Nubente = normalizeText(registry.nome_1_nubente);
                            var normalizedRegistryName2Nubente = normalizeText(registry.nome_2_nubente);
                            var normalizedRegistryTerm = normalizeText(registry.termo);
                            var normalizedRegistryBook = normalizeText(registry.livro);
                            var normalizedRegistryPage = normalizeText(registry.folha);
                            var normalizedRegistryParentage1Nubente = normalizeText(registry.filiacao_1_nubente);
                            var normalizedRegistryParentage2Nubente = normalizeText(registry.filiacao_2_nubente);

                            var matchesSearch = (searchTerm1Nubente === '' || normalizedRegistryName1Nubente.includes(searchTerm1Nubente)) &&
                                                (searchTerm2Nubente === '' || normalizedRegistryName2Nubente.includes(searchTerm2Nubente)) &&
                                                (searchTermTerm === '' || normalizedRegistryTerm.includes(searchTermTerm)) &&
                                                (searchTermBook === '' || normalizedRegistryBook.includes(searchTermBook)) &&
                                                (searchTermPage === '' || normalizedRegistryPage.includes(searchTermPage)) &&
                                                (searchTermParentage1Nubente === '' || normalizedRegistryParentage1Nubente.includes(searchTermParentage1Nubente)) &&
                                                (searchTermParentage2Nubente === '' || normalizedRegistryParentage2Nubente.includes(searchTermParentage2Nubente));
                                                
                            var matchesMarriageDate = (!searchTermMarriageDate || registry.data_casamento === searchTermMarriageDate);

                            return matchesSearch && matchesMarriageDate;
                        });
                        displayRegistries(filteredRegistries);
                    }
                });
            }

            // Enviar formulário de registro
            $('#registry-form').on('submit', function(e) {
                e.preventDefault();

                $.ajax({
                    type: 'POST',
                    url: 'save_registry.php',
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        alert('Registro adicionado com sucesso');
                        $('#addRegistryModal').modal('hide');
                        filterRegistries(); // Buscar e exibir registros atualizados
                    }
                });
            });

            // Editar registro
            $(document).on('click', '.btn-edit', function() {
                var registryId = $(this).data('id');

                $.ajax({
                    type: 'GET',
                    url: 'get_registries.php',
                    success: function(response) {
                        var registries = JSON.parse(response);
                        var registry = registries[registryId];

                        $('#edit-id').val(registryId);
                        $('#edit-term').val(registry.termo);
                        $('#edit-book').val(registry.livro);
                        $('#edit-page').val(registry.folha);
                        $('#edit-name_1_nubente').val(registry.nome_1_nubente);
                        $('#edit-birthdate_1_nubente').val(registry.data_nascimento_1_nubente);
                        $('#edit-parentage_1_nubente').val(registry.filiacao_1_nubente);
                        $('#edit-name_2_nubente').val(registry.nome_2_nubente);
                        $('#edit-birthdate_2_nubente').val(registry.data_nascimento_2_nubente);
                        $('#edit-parentage_2_nubente').val(registry.filiacao_2_nubente);
                        $('#edit-marriage-date').val(registry.data_casamento);

                        $('#editRegistryModal').modal('show');
                    }
                });
            });

            // Atualizar registro
            $('#edit-registry-form').on('submit', function(e) {
                e.preventDefault();
                var registryId = $('#edit-id').val();

                $.ajax({
                    type: 'POST',
                    url: 'update_registry.php',
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        alert('Registro atualizado com sucesso');
                        $('#editRegistryModal').modal('hide');
                        filterRegistries(); // Buscar e exibir registros atualizados
                    }
                });
            });

            // Excluir registro
            $(document).on('click', '.btn-delete', function() {
                var registryId = $(this).data('id');

                if (confirm('Tem certeza de que deseja excluir este registro?')) {
                    $.ajax({
                        type: 'POST',
                        url: 'delete_registry.php',
                        data: { id: registryId },
                        success: function(response) {
                            alert('Registro excluído com sucesso');
                            filterRegistries(); // Buscar e exibir registros atualizados
                        }
                    });
                }
            });

            // Evento de clique do botão de filtro
            $('#filter-button').on('click', function() {
                filterRegistries();
            });

            // Evento de digitação no campo de pesquisa
            $('#search-term-1-nubente, #search-term-2-nubente, #search-term-term, #search-term-book, #search-term-page, #search-term-parentage-1-nubente, #search-term-parentage-2-nubente').on('keyup', function(event) {
                // Ignorar teclas de função e outras teclas não alfanuméricas
                var ignoredKeys = [8, 16, 17, 18, 20, 27, 32, 33, 34, 35, 36, 37, 38, 39, 40, 45, 46]; // Backspace, Shift, Ctrl, Alt, CapsLock, Escape, Space, Page Up, Page Down, End, Home, setas, Insert, Delete
                if (!ignoredKeys.includes(event.which)) {
                    filterRegistries(event);
                }
            });
        });
    </script>
</body>
</html>
