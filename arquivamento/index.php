<?php
include(__DIR__ . '/session_check.php');
checkSession();
// Função para definir o fuso horário corretamente como sendo brasileiro
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Arquivamentos</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <style>
        .table th:nth-child(1), .table td:nth-child(1) { /* Atribuição */
        width: 8%;
        }
        .table th:nth-child(2), .table td:nth-child(2) { /* Categoria */
            width: 8%;
        }
        .table th:nth-child(3), .table td:nth-child(3) { /* CPF/CNPJ */
            width: 10%;
        }
        .table th:nth-child(4), .table td:nth-child(4) { /* Nome */
            width: 15%;
        }
        .table th:nth-child(5), .table td:nth-child(5) { /* Data do Ato */
            width: 8%;
        }
        .table th:nth-child(6), .table td:nth-child(6) { /* Livro */
            width: 5%;
        }
        .table th:nth-child(7), .table td:nth-child(7) { /* Folha */
            width: 5%;
        }
        .table th:nth-child(8), .table td:nth-child(8) { /* Termo/Ordem */
            width: 7%;
        }
        .table th:nth-child(9), .table td:nth-child(9) { /* Protocolo */
            width: 7%;
        }
        .table th:nth-child(10), .table td:nth-child(10) { /* Matrícula */
            width: 6%;
        }
        .table th:nth-child(11), .table td:nth-child(11) { /* Descrição e Detalhes */
            width: 14%;
        }
        .table th:nth-child(12), .table td:nth-child(12) { /* Ações */
            width: 8%;
        }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Arquivamentos Cadastrados - Consulta e Gestão</h3>
            <hr>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="atribuicao">Atribuição:</label>
                    <select id="atribuicao" name="atribuicao" class="form-control" required>
                        <option value="">Selecione</option>
                        <option value="Registro Civil">Registro Civil</option>
                        <option value="Registro de Imóveis">Registro de Imóveis</option>
                        <option value="Registro de Títulos e Documentos">Registro de Títulos e Documentos</option>
                        <option value="Registro Civil das Pessoas Jurídicas">Registro Civil das Pessoas Jurídicas</option>
                        <option value="Notas">Notas</option>
                        <option value="Protesto">Protesto</option>
                        <option value="Contratos Marítimos">Contratos Marítimos</option>
                    </select>
                </div>
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
                <div class="col-md-6">
                    <label for="nome">Nome</label>
                    <input type="text" id="nome" class="form-control" placeholder="Nome">
                </div>
                <div class="col-md-2">
                    <label for="livro">Livro</label>
                    <input type="text" id="livro" class="form-control" placeholder="Livro">
                </div>
                <div class="col-md-2">
                    <label for="folha">Folha</label>
                    <input type="text" id="folha" class="form-control" placeholder="Folha">
                </div>
                <div class="col-md-2">
                    <label for="termo">Termo/Ordem</label>
                    <input type="text" id="termo" class="form-control" placeholder="Termo/Ordem">
                </div>
                <div class="col-md-2">
                    <label for="protocolo">Protocolo</label>
                    <input type="text" id="protocolo" class="form-control" placeholder="Protocolo">
                </div>
                <div class="col-md-2">
                    <label for="matricula">Matrícula</label>
                    <input type="text" id="matricula" class="form-control" placeholder="Matrícula">
                </div>
                <div class="col-md-2">
                    <label for="data-ato">Data do Ato</label>
                    <input type="date" id="data-ato" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="descricao">Descrição e Detalhes</label>
                    <input type="text" id="descricao" class="form-control" placeholder="Descrição e Detalhes">
                </div>
                <div class="col-md-12">
                    <button style="width: 49.8%; margin-top: 10px;" id="filter-button" class="btn btn-primary"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar</button>
                    <button style="width: 49.8%; margin-top: 10px;" id="add-button" class="btn btn-success" onclick="window.location.href='cadastro.php'"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar</button>
                </div>
            </div>
            <hr>
            <div class="table-responsive">
                <h5>Resultados da Pesquisa</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 90%">
                    <thead>
                        <tr>
                            <th>Atribuição</th>
                            <th>Categoria</th>
                            <th>CPF/CNPJ</th>
                            <th>Nome</th>
                            <th>Data do Ato</th>
                            <th>Livro</th>
                            <th>Folha</th>
                            <th>Termo/Ordem</th>
                            <th>Protocolo</th>
                            <th>Matrícula</th>
                            <th>Descrição e Detalhes</th>
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
                    <h5 class="modal-title" id="anexosModalLabel" style="flex-grow: 1;">Dados do Ato</h5>
                    <button type="button" id="generate-pdf-button" class="btn btn-primary" style="margin-left: auto;">
                        <i class="fa fa-print" aria-hidden="true"></i> Capa de arquivamento
                    </button>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <form>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="view-atribuicao">Atribuição:</label>
                                <input type="text" class="form-control readonly-field" id="view-atribuicao" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="view-categoria">Categoria:</label>
                                <input type="text" class="form-control readonly-field" id="view-categoria" readonly>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="view-data-ato">Data do Ato:</label>
                                <input type="text" class="form-control readonly-field" id="view-data-ato" readonly>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="view-livro">Livro:</label>
                                <input type="text" class="form-control readonly-field" id="view-livro" readonly>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="view-folha">Folha:</label>
                                <input type="text" class="form-control readonly-field" id="view-folha" readonly>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="view-termo">Termo/Ordem:</label>
                                <input type="text" class="form-control readonly-field" id="view-termo" readonly>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="view-protocolo">Protocolo:</label>
                                <input type="text" class="form-control readonly-field" id="view-protocolo" readonly>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="view-matricula">Matrícula:</label>
                                <input type="text" class="form-control readonly-field" id="view-matricula" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="view-selo-arquivamento">Selo de Arquivamento:</label>
                                <input type="text" class="form-control readonly-field" id="view-selo-arquivamento" readonly>
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

    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script>
        function normalizeText(text) {
            if (typeof text !== 'string') {
                return '';
            }
            return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        function formatDateTime(dateTime) {
            var date = new Date(dateTime + 'T00:00:00'); // Adicione um horário fixo para evitar a conversão incorreta de fuso horário
            return date.toLocaleDateString('pt-BR');
        }

        function formatDateTime2(dateTime) {
            var date = new Date(dateTime);
            return date.toLocaleDateString('pt-BR');
        }

        function formatDate(dateTime) {
            var parts = dateTime.split(' ');
            var dateParts = parts[0].split('-');

            var formattedDate = dateParts[0] + '/' + dateParts[1] + '/' + dateParts[2];

            return formattedDate;
        }


        // Função para converter data no formato d-m-Y H:i:s para o formato Date
        function parseCustomDate(dateString) {
            var parts = dateString.split(/[- :]/);
            return new Date(parts[0], parts[1] - 1, parts[2], parts[3] || 0, parts[4] || 0, parts[5] || 0);
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
                    categorias.forEach(function(categoria) {
                        var option = $('<option></option>').attr('value', categoria).text(categoria);
                        categoriaSelect.append(option);
                    });
                }
            });

            
            $(document).ready(function () {
                // Inicializar DataTables com Bootstrap 4
                var table = $('.table').DataTable({
                    "order": [], // Remove a ordenação inicial
                    "pageLength": 10, // Define o número padrão de registros por página
                    "language": {
                        "url": "../style/Portuguese-Brasil.json"
                    },
                    "responsive": true,
                });

                // Carregar os últimos 100 documentos ao entrar na página
                $.ajax({
                    url: 'load_atos.php',
                    method: 'GET',
                    data: { limit: 100 }, // Passa o limite de 100 registros
                    success: function (response) {
                        try {
                            var atos = JSON.parse(response);
                            // Limpar e preencher a tabela com os dados
                            table.clear();
                            atos.forEach(function (ato) {
                                var cpfsCnpjs = ato.partes_envolvidas.map(p => p.cpf).join(', ');
                                var nomes = ato.partes_envolvidas.map(p => p.nome).join(', ');
                                table.row.add([
                                    ato.atribuicao,
                                    ato.categoria,
                                    cpfsCnpjs,
                                    nomes,
                                    formatDateTime(ato.data_ato),
                                    ato.livro,
                                    ato.folha,
                                    ato.termo,
                                    ato.protocolo,
                                    ato.matricula,
                                    ato.descricao,
                                    '<button class="btn btn-info btn-sm visualizar-anexos" data-id="' + ato.id + '"><i class="fa fa-eye" aria-hidden="true"></i></button> ' +
                                    '<button class="btn btn-edit btn-sm editar-ato" data-id="' + ato.id + '"><i class="fa fa-pencil" aria-hidden="true"></i></button> ' +
                                    '<button class="btn btn-delete btn-sm excluir-ato" data-id="' + ato.id + '"><i class="fa fa-trash" aria-hidden="true"></i></button>'
                                ]);
                            });
                            table.draw(); // Atualizar a tabela com os novos dados
                        } catch (e) {
                            console.error("Erro ao analisar resposta JSON: ", e);
                            console.error("Resposta recebida: ", response);
                        }
                    },
                    error: function () {
                        console.error("Erro ao carregar os dados.");
                    }
                });

                // Aplicar o filtro personalizado
                $('#filter-button').on('click', function () {
                    var searchTerm = normalizeText($('#nome').val());
                    var searchCategoria = $('#categoria').val();
                    var searchCpfCnpj = $('#cpf-cnpj').val();
                    var searchLivro = $('#livro').val();
                    var searchFolha = $('#folha').val();
                    var searchTermo = $('#termo').val();
                    var searchProtocolo = $('#protocolo').val();
                    var searchMatricula = $('#matricula').val();
                    var searchDataAto = $('#data-ato').val();
                    var searchDescricao = normalizeText($('#descricao').val());
                    var searchAtribuicao = $('#atribuicao').val();

                    $.ajax({
                        url: 'load_atos.php',
                        method: 'GET',
                        success: function (response) {
                            try {
                                var atos = JSON.parse(response);
                                // Filtrar os dados
                                var filteredAtos = atos.filter(function (ato) {
                                    var normalizedNome = normalizeText(ato.partes_envolvidas.map(p => p.nome).join(', '));
                                    var normalizedDescricao = normalizeText(ato.descricao);

                                    return (!searchTerm || normalizedNome.includes(searchTerm)) &&
                                        (!searchCategoria || ato.categoria === searchCategoria) &&
                                        (!searchCpfCnpj || ato.partes_envolvidas.some(p => p.cpf.includes(searchCpfCnpj))) &&
                                        (!searchLivro || ato.livro.includes(searchLivro)) &&
                                        (!searchFolha || ato.folha.includes(searchFolha)) &&
                                        (!searchTermo || ato.termo.includes(searchTermo)) &&
                                        (!searchProtocolo || ato.protocolo.includes(searchProtocolo)) &&
                                        (!searchMatricula || ato.matricula.includes(searchMatricula)) &&
                                        (!searchDataAto || ato.data_ato === searchDataAto) &&
                                        (!searchDescricao || normalizedDescricao.includes(searchDescricao)) &&
                                        (!searchAtribuicao || ato.atribuicao.includes(searchAtribuicao));
                                });

                                // Limpar e adicionar os dados filtrados à tabela
                                table.clear();
                                filteredAtos.forEach(function (ato) {
                                    var cpfsCnpjs = ato.partes_envolvidas.map(p => p.cpf).join(', ');
                                    var nomes = ato.partes_envolvidas.map(p => p.nome).join(', ');
                                    table.row.add([
                                        ato.atribuicao,
                                        ato.categoria,
                                        cpfsCnpjs,
                                        nomes,
                                        formatDateTime(ato.data_ato),
                                        ato.livro,
                                        ato.folha,
                                        ato.termo,
                                        ato.protocolo,
                                        ato.matricula,
                                        ato.descricao,
                                        '<button class="btn btn-info btn-sm visualizar-anexos" data-id="' + ato.id + '"><i class="fa fa-eye" aria-hidden="true"></i></button> ' +
                                        '<button class="btn btn-edit btn-sm editar-ato" data-id="' + ato.id + '"><i class="fa fa-pencil" aria-hidden="true"></i></button> ' +
                                        '<button class="btn btn-delete btn-sm excluir-ato" data-id="' + ato.id + '"><i class="fa fa-trash" aria-hidden="true"></i></button>'
                                    ]);
                                });
                                table.draw(); // Atualizar a tabela com os novos dados filtrados
                            } catch (e) {
                                console.error("Erro ao analisar resposta JSON: ", e);
                                console.error("Resposta recebida: ", response);
                            }
                        },
                        error: function () {
                            console.error("Erro ao carregar os dados.");
                        }
                    });
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
                        $('#view-data-ato').val(formatDateTime(ato.data_ato));
                        $('#view-livro').val(ato.livro);
                        $('#view-folha').val(ato.folha);
                        $('#view-termo').val(ato.termo);
                        $('#view-protocolo').val(ato.protocolo);
                        $('#view-matricula').val(ato.matricula);
                        $('#view-partes-envolvidas').val(ato.partes_envolvidas.map(p => p.cpf + ' - ' + p.nome).join(', '));
                        $('#view-descricao').val(ato.descricao);
                        $('#view-cadastrado-por').text(ato.cadastrado_por);
                        $('#view-data-cadastro').text(formatDateTime2(ato.data_cadastro));

                        // Buscar número do selo e exibir no campo "Selo de Arquivamento"
                        $.ajax({
                            url: 'get_selo_modal.php',
                            method: 'GET',
                            data: { id: id },
                            success: function(seloResponse) {
                                var selo = JSON.parse(seloResponse);
                                $('#view-selo-arquivamento').val(selo.numero_selo);
                            },
                            error: function() {
                                $('#view-selo-arquivamento').val('Erro ao buscar selo');
                            }
                        });

                        var modificacoesList = $('#view-modificacoes');
                            modificacoesList.empty();
                            if (ato.modificacoes && ato.modificacoes.length > 0) {
                                ato.modificacoes.forEach(function(modificacao) {
                                    var modificacaoItem = '<li>' + modificacao.usuario + ' - ' + formatDate(modificacao.data_hora) + '</li>'; // Use formatDate aqui
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

            // Excluir ato com SweetAlert2
            $(document).on('click', '.excluir-ato', function() {
                var atoIdToDelete = $(this).data('id');

                Swal.fire({
                    title: 'Tem certeza?',
                    text: "Você realmente deseja excluir este ato?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sim, excluir',
                    cancelButtonText: 'Não, cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Se o usuário confirmar, prossegue com a exclusão
                        $.ajax({
                            url: 'delete_ato.php',
                            method: 'POST',
                            data: { id: atoIdToDelete },
                            success: function(response) {
                                Swal.fire(
                                    'Excluído!',
                                    'Ato movido para a lixeira com sucesso.',
                                    'success'
                                );
                                location.reload();
                            },
                            error: function() {
                                Swal.fire(
                                    'Erro!',
                                    'Houve um problema ao tentar mover o ato para a lixeira.',
                                    'error'
                                );
                            }
                        });
                    }
                });
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

                // Faz a requisição para o arquivo JSON
                $.ajax({
                    url: '../style/configuracao.json',
                    dataType: 'json',
                    cache: false, // Desabilita o cache
                    success: function(data) {
                        let url = '';

                        // Verifica o valor do "timbrado" e ajusta a URL
                        if (data.timbrado === 'S') {
                            url = 'capa_arquivamento.php?id=' + id;
                        } else if (data.timbrado === 'N') {
                            url = 'capa-arquivamento.php?id=' + id;
                        }

                        // Abre a URL correspondente em uma nova aba
                        window.open(url, '_blank');
                    },
                    error: function() {
                        alert('Erro ao carregar o arquivo de configuração.');
                    }
                });
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
            $('#data-ato').on('change', function() {
                // Certifique-se de que há um valor antes de validar
                if ($(this).val()) {
                    validateDate(this);
                }
            });
        });
        
    </script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
