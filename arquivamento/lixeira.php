<?php
include(__DIR__ . '/session_check.php');
checkSession();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Lixeira</title>
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
            <h3>Acervo Excluído</h3>
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
                    <button id="filter-button" class="btn btn btn-primary w-100">Filtrar</button>
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="view-categoria">Categoria:</label>
                                <input type="text" class="form-control readonly-field" id="view-categoria" readonly>
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
                        </div>
                        <div class="form-row">
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
                            <small class="text-muted">
                                Excluído por: <span id="view-excluido-por"></span><br>
                                Data de Exclusão: <span id="view-data-exclusao"></span>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmação de restauração -->
    <div class="modal fade" id="confirmRestoreModal" tabindex="-1" aria-labelledby="confirmRestoreModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmRestoreModalLabel">Confirmar Restauração</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Tem certeza de que deseja restaurar este ato?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" id="confirm-restore-button" class="btn btn-success">Restaurar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script>

        function normalizeText(text) {
            return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        function formatDateTime(dateTime) {
            var date = new Date(dateTime);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
        }

        $(document).ready(function() {
            // Carregar categorias do JSON
            $.ajax({
                url: 'categorias/categorias.json',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    var categorias = response;
                    var categoriaSelect = $('#categoria');
                    categoriaSelect.append('<option value="">Selecione</option>');
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
                    url: 'load_lixeira.php',
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
                                        '<button class="btn btn-success2 btn-sm restaurar-ato" data-id="' + ato.id + '" data-toggle="modal" data-target="#confirmRestoreModal"><i class="fa fa-undo" aria-hidden="true"></i></button>' +
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
                $.ajax({
                    url: 'load_lixo.php',
                    method: 'GET',
                    data: { id: id },
                    success: function(response) {
                        var ato = JSON.parse(response);

                        $('#view-categoria').val(ato.categoria);
                        $('#view-livro').val(ato.livro);
                        $('#view-folha').val(ato.folha);
                        $('#view-termo').val(ato.termo);
                        $('#view-protocolo').val(ato.protocolo);
                        $('#view-matricula').val(ato.matricula);
                        $('#view-partes-envolvidas').val(ato.partes_envolvidas.map(p => p.cpf + ' - ' + p.nome).join(', '));
                        $('#view-cadastrado-por').text(ato.cadastrado_por);
                        $('#view-data-cadastro').text(formatDateTime(ato.data_cadastro));

                        var modificacoesList = $('#view-modificacoes');
                        modificacoesList.empty();
                        if (ato.modificacoes && ato.modificacoes.length > 0) {
                            ato.modificacoes.forEach(function(modificacao) {
                                var modificacaoItem = '<li>' + modificacao.usuario + ' - ' + formatDateTime(modificacao.data_hora) + '</li>';
                                modificacoesList.append(modificacaoItem);
                            });
                        }
                        $('#view-excluido-por').text(ato.excluido_por);
                        $('#view-data-exclusao').text(formatDateTime(ato.data_exclusao));

                        var anexosList = $('#view-anexos-list');
                        anexosList.empty();
                        ato.anexos.forEach(function(anexo, index) {
                            var fileName = anexo.split('/').pop();
                            var anexoPath = anexo.replace('arquivos', 'lixeira');
                            var anexoItem = '<div class="anexo-item">' +
                                '<span>' + (index + 1) + '</span>' +
                                '<span>' + fileName + '</span>' +
                                '<button class="btn btn-info btn-sm visualizar-anexo" data-file="' + anexoPath + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                                '</div>';
                            anexosList.append(anexoItem);
                        });

                        $('#anexosModal').modal('show');
                    }
                });
            });

            // Restaurar ato
            var atoIdToRestore;
            $(document).on('click', '.restaurar-ato', function() {
                atoIdToRestore = $(this).data('id');
            });

            $('#confirm-restore-button').on('click', function() {
                if (atoIdToRestore) {
                    $.ajax({
                        url: 'restore_ato.php',
                        method: 'POST',
                        data: { id: atoIdToRestore },
                        success: function(response) {
                            $('#confirmRestoreModal').modal('hide');
                            alert('Ato restaurado com sucesso');
                            location.reload();
                        }
                    });
                }
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
        });
    </script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
