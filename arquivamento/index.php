<?php
include(__DIR__ . '/session_check.php');
checkSession();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Acervo</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        .table th:nth-child(9), .table td:nth-child(9) {
            width: 9%;
        }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Acervo Cadastrado - Consulta e Gestão</h3>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="categoria">Categoria</label>
                    <select id="categoria" class="form-control">
                        <option value="">Selecione</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="cpf-cnpj">CPF/CNPJ</label>
                    <input type="text" id="cpf-cnpj" class="form-control" placeholder="CPF/CNPJ">
                </div>
                <div class="col-md-4">
                    <label for="nome">Nome</label>
                    <input type="text" id="nome" class="form-control" placeholder="Nome">
                </div>
                <div class="col-md-4">
                    <label for="livro">Livro</label>
                    <input type="text" id="livro" class="form-control" placeholder="Livro">
                </div>
                <div class="col-md-4">
                    <label for="folha">Folha</label>
                    <input type="text" id="folha" class="form-control" placeholder="Folha">
                </div>
                <div class="col-md-4">
                    <label for="termo">Termo/Ordem</label>
                    <input type="text" id="termo" class="form-control" placeholder="Termo/Ordem">
                </div>
                <div class="col-md-4">
                    <label for="protocolo">Protocolo</label>
                    <input type="text" id="protocolo" class="form-control" placeholder="Protocolo">
                </div>
                <div class="col-md-4">
                    <label for="matricula">Matrícula</label>
                    <input type="text" id="matricula" class="form-control" placeholder="Matrícula">
                </div>
                <div class="col-md-4">
                    <button id="filter-button" class="btn btn btn-primary w-40">Filtrar</button>
                    <button id="add-button" class="btn btn-success w-40" onclick="window.location.href='cadastro.php'">+ Adicionar</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>CPF/CNPJ</th>
                            <th>Nome</th>
                            <th>Livro</th>
                            <th>Folha</th>
                            <th>Termo/Ordem</th>
                            <th>Protocolo</th>
                            <th>Matrícula</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="atos-table-body">
                        <!-- Linhas da tabela serão inseridas aqui dinamicamente -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para visualizar anexos -->
    <div class="modal fade" id="anexosModal" tabindex="-1" aria-labelledby="anexosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="anexosModalLabel">Dados do Ato</h5>
                    <button type="button" id="generate-pdf-button" style="width: 220px; margin-left: 58%; margin-top: -8px;" class="btn btn-primary"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Capa de arquivamento</button>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>

                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="view-atribuicao">Atribuição:</label>
                                <input type="text" class="form-control readonly-field" id="view-atribuicao" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="view-categoria">Categoria:</label>
                                <input type="text" class="form-control readonly-field" id="view-categoria" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="view-data-ato">Data do Ato:</label>
                                <input type="text" class="form-control readonly-field" id="view-data-ato" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="view-livro">Livro:</label>
                                <input type="text" class="form-control readonly-field" id="view-livro" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="view-folha">Folha:</label>
                                <input type="text" class="form-control readonly-field" id="view-folha" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="view-termo">Termo/Ordem:</label>
                                <input type="text" class="form-control readonly-field" id="view-termo" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="view-protocolo">Protocolo:</label>
                                <input type="text" class="form-control readonly-field" id="view-protocolo" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="view-matricula">Matrícula:</label>
                                <input type="text" class="form-control readonly-field" id="view-matricula" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="view-partes-envolvidas">Partes Envolvidas:</label>
                            <textarea class="form-control readonly-field" id="view-partes-envolvidas" rows="2" readonly></textarea>
                        </div>
                        <div class="form-group">
                            <label for="view-descricao">Descrição e Detalhes:</label>
                            <textarea class="form-control readonly-field" id="view-descricao" rows="3" readonly></textarea>
                        </div>
                        <h4>Anexos</h4>
                        <div id="view-anexos-list">
                            <!-- Lista de anexos será inserida aqui dinamicamente -->
                        </div>
                        <div class="form-group text-right">
                            <small class="text-muted">
                                Cadastrado por: <span id="view-cadastrado-por"></span><br>
                                Data de Cadastro: <span id="view-data-cadastro"></span>
                            </small>
                        </div>
                        <div class="form-group" style="font-size: 0.8rem;">
                            <label for="view-modificacoes">Histórico de Modificações:</label>
                            <ul id="view-modificacoes" class="list-unstyled">
                                <!-- Histórico de modificações será inserido aqui dinamicamente -->
                            </ul>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmação de exclusão -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Tem certeza de que deseja excluir este ato?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" id="confirm-delete-button" class="btn btn-danger">Excluir</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script>
        function openNav() {
            document.getElementById("mySidebar").style.width = "250px";
            document.getElementById("main").style.marginLeft = "250px";
        }

        function closeNav() {
            document.getElementById("mySidebar").style.width = "0";
            document.getElementById("main").style.marginLeft = "0";
        }

        function normalizeText(text) {
            return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        function formatDateTime(dateTime) {
            var date = new Date(dateTime);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
        }

        // Função para converter data no formato d-m-Y H:i:s para o formato Date
        function parseCustomDate(dateString) {
            var parts = dateString.split(/[- :]/);
            return new Date(parts[2], parts[1] - 1, parts[0], parts[3], parts[4], parts[5]);
        }

        $(document).ready(function() {
            // Carregar o modo do usuário
            $.ajax({
                url: '../load_mode.php',
                method: 'GET',
                success: function(mode) {
                    $('body').removeClass('light-mode dark-mode').addClass(mode);
                }
            });

            // Função para alternar modos claro e escuro
            $('.mode-switch').on('click', function() {
                var body = $('body');
                body.toggleClass('dark-mode light-mode');

                var mode = body.hasClass('dark-mode') ? 'dark-mode' : 'light-mode';
                $.ajax({
                    url: '../save_mode.php',
                    method: 'POST',
                    data: { mode: mode },
                    success: function(response) {
                        console.log(response);
                    }
                });
            });

            // Carregar categorias do JSON
            $.ajax({
                url: 'categorias/categorias.json',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    var categorias = response;
                    var categoriaSelect = $('#categoria');
                    categorias.forEach(function(categoria) {
                        var option = $('<option></option>').attr('value', categoria).text(categoria);
                        categoriaSelect.append(option);
                    });
                }
            });

            // Carregar dados dos atos
            $('#filter-button').on('click', function() {
                var searchTerm = normalizeText($('#nome').val());
                var searchCategoria = $('#categoria').val();
                var searchCpfCnpj = $('#cpf-cnpj').val();
                var searchLivro = $('#livro').val();
                var searchFolha = $('#folha').val();
                var searchTermo = $('#termo').val();
                var searchProtocolo = $('#protocolo').val();
                var searchMatricula = $('#matricula').val();

                $.ajax({
                    url: 'load_atos.php',
                    method: 'GET',
                    success: function(response) {
                        var atos = JSON.parse(response);
                        var tableBody = $('#atos-table-body');
                        tableBody.empty(); // Limpar a tabela

                        atos.forEach(function(ato) {
                            var normalizedNome = normalizeText(ato.partes_envolvidas.map(p => p.nome).join(', '));
                            var matchesSearch = (!searchTerm || normalizedNome.includes(searchTerm)) &&
                                                (!searchCategoria || ato.categoria === searchCategoria) &&
                                                (!searchCpfCnpj || ato.partes_envolvidas.some(p => p.cpf.includes(searchCpfCnpj))) &&
                                                (!searchLivro || ato.livro.includes(searchLivro)) &&
                                                (!searchFolha || ato.folha.includes(searchFolha)) &&
                                                (!searchTermo || ato.termo.includes(searchTermo)) &&
                                                (!searchProtocolo || ato.protocolo.includes(searchProtocolo)) &&
                                                (!searchMatricula || ato.matricula.includes(searchMatricula));

                            if (matchesSearch) {
                                var cpfsCnpjs = ato.partes_envolvidas.map(p => p.cpf).join(', ');
                                var nomes = ato.partes_envolvidas.map(p => p.nome).join(', ');
                                var row = '<tr>' +
                                    '<td>' + ato.categoria + '</td>' +
                                    '<td>' + cpfsCnpjs + '</td>' +
                                    '<td>' + nomes + '</td>' +
                                    '<td>' + ato.livro + '</td>' +
                                    '<td>' + ato.folha + '</td>' +
                                    '<td>' + ato.termo + '</td>' +
                                    '<td>' + ato.protocolo + '</td>' +
                                    '<td>' + ato.matricula + '</td>' +
                                    '<td>' +
                                        '<button class="btn btn-info btn-sm visualizar-anexos" data-id="' + ato.id + '"><i class="fa fa-eye" aria-hidden="true"></i></button> ' +
                                        '<button class="btn btn-edit btn-sm editar-ato" data-id="' + ato.id + '"><i class="fa fa-pencil" aria-hidden="true"></i></button> ' +
                                        '<button class="btn btn-delete btn-sm excluir-ato" data-id="' + ato.id + '" data-toggle="modal" data-target="#confirmDeleteModal"><i class="fa fa-trash" aria-hidden="true"></i></button>' +
                                    '</td>' +
                                    '</tr>';
                                tableBody.append(row);
                            }
                        });
                    }
                });
            });

            // Visualizar anexos e dados
            $(document).on('click', '.visualizar-anexos', function() {
                var id = $(this).data('id');
                $('#generate-pdf-button').data('id', id);
                $.ajax({
                    url: 'load_ato.php',
                    method: 'GET',
                    data: { id: id },
                    success: function(response) {
                        var ato = JSON.parse(response);

                        $('#view-atribuicao').val(ato.atribuicao);
                        $('#view-categoria').val(ato.categoria);
                        $('#view-data-ato').val(ato.data_ato);
                        $('#view-livro').val(ato.livro);
                        $('#view-folha').val(ato.folha);
                        $('#view-termo').val(ato.termo);
                        $('#view-protocolo').val(ato.protocolo);
                        $('#view-matricula').val(ato.matricula);
                        $('#view-partes-envolvidas').val(ato.partes_envolvidas.map(p => p.cpf + ' - ' + p.nome).join(', '));
                        $('#view-descricao').val(ato.descricao);
                        $('#view-cadastrado-por').text(ato.cadastrado_por);
                        $('#view-data-cadastro').text(formatDateTime(ato.data_cadastro));

                        var modificacoesList = $('#view-modificacoes');
                        modificacoesList.empty();
                        if (ato.modificacoes && ato.modificacoes.length > 0) {
                            ato.modificacoes.forEach(function(modificacao) {
                                var modificacaoDate = parseCustomDate(modificacao.data_hora);
                                var modificacaoItem = '<li>' + modificacao.usuario + ' - ' + formatDateTime(modificacaoDate) + '</li>';
                                modificacoesList.append(modificacaoItem);
                            });
                        }

                        var anexosList = $('#view-anexos-list');
                        anexosList.empty();
                        ato.anexos.forEach(function(anexo, index) {
                            var fileName = anexo.split('/').pop();
                            var anexoItem = '<div class="anexo-item">' +
                                '<span>' + (index + 1) + '</span>' +
                                '<span>' + fileName + '</span>' +
                                '<button class="btn btn-info btn-sm visualizar-anexo" data-file="' + anexo + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                                '</div>';
                            anexosList.append(anexoItem);
                        });

                        $('#anexosModal').modal('show');
                    }
                });
            });

            // Excluir ato
            var atoIdToDelete;
            $(document).on('click', '.excluir-ato', function() {
                atoIdToDelete = $(this).data('id');
            });

            $('#confirm-delete-button').on('click', function() {
                if (atoIdToDelete) {
                    $.ajax({
                        url: 'delete_ato.php',
                        method: 'POST',
                        data: { id: atoIdToDelete },
                        success: function(response) {
                            $('#confirmDeleteModal').modal('hide');
                            alert('Ato movido para a lixeira com sucesso');
                            location.reload();
                        }
                    });
                }
            });

            // Editar ato
            $(document).on('click', '.editar-ato', function() {
                var id = $(this).data('id');
                // Redirecionar para a página de edição com o ID do ato
                window.location.href = 'edit_ato.php?id=' + id;
            });

            // Visualizar anexo
            $(document).on('click', '.visualizar-anexo', function(e) {
                e.preventDefault();
                var anexo = $(this).data('file');
                window.open(anexo, '_blank');
            });

            // Aplicar máscara ao CPF/CNPJ
            $('#cpf-cnpj').on('input', function() {
                var value = $(this).val().replace(/\D/g, '');
                if (value.length <= 11) {
                    $(this).mask('000.000.000-00', { reverse: true });
                } else {
                    $(this).mask('00.000.000/0000-00', { reverse: true });
                }
            });

            // Evitar espaços iniciais no campo Nome
            $('#nome').on('keypress', function(event) {
                if (event.which === 32 && $(this).val().length === 0) {
                    event.preventDefault();
                }
            });

            // Gerar capa de arquivamento em PDF
            $('#generate-pdf-button').on('click', function() {
                var id = $(this).data('id');
                window.open('capa-arquivamento.php?id=' + id, '_blank');
            });
        });
    </script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
