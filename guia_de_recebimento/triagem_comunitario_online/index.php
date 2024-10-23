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
    <title>Atlas - Triagem Comunitário</title>
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="stylesheet" href="../../style/css/dataTables.bootstrap4.min.css">
    <script src="../../script/jquery-3.6.0.min.js"></script>
    <script src="../../script/bootstrap.bundle.min.js"></script>
    <script src="../../script/sweetalert2.js"></script>
    <style>
        .modal-lg {
            max-width: 60%;
        }

        .btn-warning, .btn-secondary, .btn-success {
            width: 40px;
            height: 40px;
            margin-bottom: 5px;
        }

        .status-span {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            font-size: 0.875rem;
            text-align: center;
            min-width: 120px;
        }

        .status-sim {
            background-color: #28a745; /* Verde */
        }

        .status-nao {
            background-color: #dc3545; /* Vermelho */
        }
    </style>
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Triagem Comunitário</h3>
            <button class="btn btn-primary" data-toggle="modal" data-target="#modalCadastro">Cadastrar Novo</button>
        </div>
        <hr>

        <!-- Filtro de Pesquisa -->
        <form id="searchForm" class="form-row">
            <div class="form-group col-md-2">
                <label for="filtroCidade">Cidade</label>
                <select id="filtroCidade" class="form-control" name="cidade">
                    <option value="">Selecione</option>
                    <option value="São Roberto">São Roberto</option>
                    <option value="São Raimundo do Doca Bezerra">São Raimundo do Doca Bezerra</option>
                    <option value="Esperantinópolis">Esperantinópolis</option>
                </select>
            </div>

            <div class="form-group col-md-1">
                <label for="filtroProtocolo">Nº Protocolo</label>
                <input type="text" id="filtroProtocolo" name="n_protocolo" class="form-control">
            </div>

            <div class="form-group col-md-1">
                <label for="filtroNumeroProclamas">Nº Proclamas</label>
                <input type="text" id="filtroNumeroProclamas" name="numero_proclamas" class="form-control">
            </div>

            <div class="form-group col-md-2">
                <label for="filtroPedidoDeferido">Pedido Deferido</label>
                <select id="filtroPedidoDeferido" name="pedido_deferido" class="form-control">
                    <option value="">Selecione</option>
                    <option value="1">Sim</option>
                    <option value="0">Não</option>
                </select>
            </div>

            <div class="form-group col-md-2">
                <label for="filtroCadastroEfetivado">Cadastro Efetivado</label>
                <select id="filtroCadastroEfetivado" name="cadastro_efetivado" class="form-control">
                    <option value="">Selecione</option>
                    <option value="1">Sim</option>
                    <option value="0">Não</option>
                </select>
            </div>

            <div class="form-group col-md-2">
                <label for="filtroProcessoConcluido">Processo Concluído</label>
                <select id="filtroProcessoConcluido" name="processo_concluido" class="form-control">
                    <option value="">Selecione</option>
                    <option value="1">Sim</option>
                    <option value="0">Não</option>
                </select>
            </div>

            <div class="form-group col-md-2">
                <label for="filtroHabilitacaoConcluida">Habilitação Concluída</label>
                <select id="filtroHabilitacaoConcluida" name="habilitacao_concluida" class="form-control">
                    <option value="">Selecione</option>
                    <option value="1">Sim</option>
                    <option value="0">Não</option>
                </select>
            </div>

            <div class="form-group col-md-4">
                <label for="filtroNomeNoivo">Nome do Noivo</label>
                <input type="text" id="filtroNomeNoivo" name="nome_noivo" class="form-control">
            </div>

            <div class="form-group col-md-4">
                <label for="filtroNomeNoiva">Nome da Noiva</label>
                <input type="text" id="filtroNomeNoiva" name="nome_noiva" class="form-control">
            </div>

            

            <div class="form-group col-md-4" style="margin-top: 0.5rem!important">
                <button type="submit" class="btn btn-success btn-block mt-4">Pesquisar</button>
            </div>
        </form>

        <hr>
        <div class="table-responsive">
            <h5>Resultados da Pesquisa</h5>
            <table id="resultadosTabela" class="table table-striped table-bordered" style="zoom: 85%">
                <thead>
                    <tr>
                        <th>Cidade</th>    
                        <th>Nº Protocolo</th>
                        <th>Nome do Noivo</th>
                        <th>Noivo Menor</th>
                        <th>Nome da Noiva</th>
                        <th>Noiva Menor</th>
                        <th>Pedido Deferido</th>
                        <th>Cad. Efetivado</th>
                        <th>Proc. Concluído</th>
                        <th>Hab. Concluída</th>
                        <th style="width: 8%">Ações</th>
                    </tr>
                </thead>
                <tbody id="resultadosPesquisa"></tbody>
            </table>
        </div>
    </div>
</div>

    <!-- Modal de Cadastro -->
    <div class="modal fade" id="modalCadastro" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cadastro de Triagem</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formCadastro" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="cidade">Cidade</label>
                                <select id="cidade" name="cidade" class="form-control" required>
                                    <option value="">Selecione</option>
                                    <option value="São Roberto">São Roberto</option>
                                    <option value="São Raimundo do Doca Bezerra">São Raimundo do Doca Bezerra</option>
                                    <option value="Esperantinópolis">Esperantinópolis</option>
                                </select>
                            </div>
                            <div class="form-group col-md-5">
                                <label for="nomeNoivo">Nome do Noivo</label>
                                <input type="text" id="nomeNoivo" name="nomeNoivo" class="form-control" required>
                            </div>
                            <div class="form-group col-md-5">
                                <label for="novoNomeNoivo">Novo Nome do Noivo (se houver)</label>
                                <input type="text" id="novoNomeNoivo" name="novoNomeNoivo" class="form-control">
                            </div>
                        
                            <div class="form-group col-md-2">
                                <label for="noivoMenor">Noivo Menor?</label>
                                <select id="noivoMenor" name="noivoMenor" class="form-control" required>
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">

                            <div class="form-group col-md-5">
                                <label for="nomeNoiva">Nome da Noiva</label>
                                <input type="text" id="nomeNoiva" name="nomeNoiva" class="form-control" required>
                            </div>
                            <div class="form-group col-md-5">
                                <label for="novoNomeNoiva">Novo Nome da Noiva (se houver)</label>
                                <input type="text" id="novoNomeNoiva" name="novoNomeNoiva" class="form-control">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="noivaMenor">Noiva Menor?</label>
                                <select id="noivaMenor" name="noivaMenor" class="form-control" required>
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="anexos">Anexos</label>
                            <input type="file" id="anexos" name="anexos[]" class="form-control-file" multiple>
                        </div>

                        <input type="hidden" id="protocolo" name="protocolo">

                        <button type="submit" class="btn btn-primary btn-block">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Visualização -->
    <div class="modal fade" id="modalVisualizar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Visualizar Registro</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formVisualizar" readonly>

                        <!-- Linha 1: Cidade, Protocolo, Número de Proclamas -->
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="visualizarCidade">Cidade</label>
                                <input type="text" id="visualizarCidade" class="form-control" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="visualizarProtocolo">Nº Protocolo</label>
                                <input type="text" id="visualizarProtocolo" class="form-control" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="visualizarNumeroProclamas">Número de Proclamas</label>
                                <input type="text" id="visualizarNumeroProclamas" class="form-control" readonly>
                            </div>
                        </div>

                        <!-- Linha 2: Nome do Noivo, Novo Nome do Noivo, Noivo Menor -->
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="visualizarNomeNoivo">Nome do Noivo</label>
                                <input type="text" id="visualizarNomeNoivo" class="form-control" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="visualizarNovoNomeNoivo">Novo Nome do Noivo</label>
                                <input type="text" id="visualizarNovoNomeNoivo" class="form-control" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="visualizarNoivoMenor">Noivo Menor?</label>
                                <input type="text" id="visualizarNoivoMenor" class="form-control" readonly>
                            </div>
                        </div>

                        <!-- Linha 3: Nome da Noiva, Novo Nome da Noiva, Noiva Menor -->
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="visualizarNomeNoiva">Nome da Noiva</label>
                                <input type="text" id="visualizarNomeNoiva" class="form-control" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="visualizarNovoNomeNoiva">Novo Nome da Noiva</label>
                                <input type="text" id="visualizarNovoNomeNoiva" class="form-control" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="visualizarNoivaMenor">Noiva Menor?</label>
                                <input type="text" id="visualizarNoivaMenor" class="form-control" readonly>
                            </div>
                        </div>

                        <!-- Linha 4: Pedido Deferido, Cadastro Efetivado, Processo Concluído -->
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="visualizarPedidoDeferido">Pedido Deferido</label>
                                <input type="text" id="visualizarPedidoDeferido" class="form-control" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="visualizarCadastroEfetivado">Cadastro Efetivado</label>
                                <input type="text" id="visualizarCadastroEfetivado" class="form-control" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="visualizarProcessoConcluido">Processo Concluído</label>
                                <input type="text" id="visualizarProcessoConcluido" class="form-control" readonly>
                            </div>
                        </div>

                        <!-- Linha 5: Habilitação Concluída -->
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="visualizarHabilitacaoConcluida">Habilitação Concluída</label>
                                <input type="text" id="visualizarHabilitacaoConcluida" class="form-control" readonly>
                            </div>
                        </div>

                        <!-- Tabela de Anexos -->
                        <div class="form-group">
                            <label>Anexos</label>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Nome do Anexo</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody id="anexosVisualizar">
                                    <!-- Anexos serão carregados aqui via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Registro</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formEditar" enctype="multipart/form-data">
                        <input type="hidden" id="editarId" name="id">

                        <!-- Linha: Cidade e Protocolo -->
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="editarCidade">Cidade</label>
                                <input type="text" id="editarCidade" name="cidade" class="form-control" readonly>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="editarProtocolo">Número do Protocolo</label>
                                <input type="text" id="editarProtocolo" name="n_protocolo" class="form-control" readonly>
                            </div>
                        </div>

                        <!-- Linha: Nome do Noivo, Novo Nome do Noivo e Noivo Menor -->
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="editarNomeNoivo">Nome do Noivo</label>
                                <input type="text" id="editarNomeNoivo" name="nomeNoivo" class="form-control" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="editarNovoNomeNoivo">Novo Nome do Noivo</label>
                                <input type="text" id="editarNovoNomeNoivo" name="novoNomeNoivo" class="form-control">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="editarNoivoMenor">Noivo Menor?</label>
                                <select id="editarNoivoMenor" name="noivoMenor" class="form-control" required>
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                            </div>
                        </div>

                        <!-- Linha: Nome da Noiva, Novo Nome da Noiva e Noiva Menor -->
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="editarNomeNoiva">Nome da Noiva</label>
                                <input type="text" id="editarNomeNoiva" name="nomeNoiva" class="form-control" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="editarNovoNomeNoiva">Novo Nome da Noiva</label>
                                <input type="text" id="editarNovoNomeNoiva" name="novoNomeNoiva" class="form-control">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="editarNoivaMenor">Noiva Menor?</label>
                                <select id="editarNoivaMenor" name="noivaMenor" class="form-control" required>
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                            </div>
                        </div>

                        <!-- Anexos -->
                        <div class="form-group">
                            <label for="editarAnexos">Anexos</label>
                            <input type="file" id="editarAnexos" name="anexos[]" class="form-control-file" multiple>
                            <button type="button" class="btn btn-success mt-2" onclick="adicionarAnexos()">Adicionar Anexos</button>

                            <table class="table table-bordered mt-2">
                                <thead>
                                    <tr>
                                        <th>Nome do Anexo</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody id="anexosEditar">
                                    <!-- Anexos serão carregados aqui -->
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Salvar Alterações</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Definir Situações -->
    <div class="modal fade" id="modalSituacao" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Definir Situações</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formSituacao">
                        <input type="hidden" id="situacaoId" name="id">

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="situacaoPedidoDeferido">Pedido Deferido</label>
                                <select id="situacaoPedidoDeferido" name="pedido_deferido" class="form-control">
                                    <option value="">Selecione</option>
                                    <option value="1">Sim</option>
                                    <option value="0">Não</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="situacaoCadastroEfetivado">Cadastro Efetivado</label>
                                <select id="situacaoCadastroEfetivado" name="cadastro_efetivado" class="form-control">
                                    <option value="">Selecione</option>
                                    <option value="1">Sim</option>
                                    <option value="0">Não</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="situacaoProcessoConcluido">Processo Concluído</label>
                                <select id="situacaoProcessoConcluido" name="processo_concluido" class="form-control">
                                    <option value="">Selecione</option>
                                    <option value="1">Sim</option>
                                    <option value="0">Não</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="situacaoHabilitacaoConcluida">Habilitação Concluída</label>
                                <select id="situacaoHabilitacaoConcluida" name="habilitacao_concluida" class="form-control">
                                    <option value="">Selecione</option>
                                    <option value="1">Sim</option>
                                    <option value="0">Não</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="situacaoNumeroProclamas">Número de Proclamas</label>
                            <input type="text" id="situacaoNumeroProclamas" name="numero_proclamas" class="form-control">
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Salvar Situação</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../script/jquery.dataTables.min.js"></script>
    <script src="../../script/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            var dataTable = $('#resultadosTabela').DataTable({
                "language": {
                    "url": "../style/Portuguese-Brasil.json"
                },
                "order": [[1, 'desc']],  // Ordenar por ID de forma decrescente
            });
        });

        // Geração de Protocolo
        $('#cidade').on('change', function () {
            gerarProtocolo($(this).val());
        });

        function gerarProtocolo(cidade) {
            $.ajax({
                url: 'gerar_protocolo.php',
                method: 'POST',
                data: { cidade: cidade },
                success: function (response) {
                    $('#protocolo').val(response);
                },
                error: function () {
                    Swal.fire('Erro!', 'Erro ao gerar o protocolo.', 'error');
                }
            });
        }

        $('#formCadastro').on('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);

            $.ajax({
                url: 'salvar_registro.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function () {
                    Swal.fire('Sucesso!', 'Registro salvo com sucesso.', 'success').then(() => location.reload());
                },
                error: function () {
                    Swal.fire('Erro!', 'Erro ao salvar o registro.', 'error');
                }
            });
        });

        function carregarTodosRegistros() {
            $.ajax({
                url: 'pesquisar_registros.php',
                method: 'GET',
                success: function (response) {
                    $('#resultadosPesquisa').html(response);
                }
            });
        }

        $(document).ready(function () {
            carregarTodosRegistros();
        });

        $('#searchForm').on('submit', function (e) {
            e.preventDefault(); // Evita o reload da página

            var formData = $(this).serialize(); // Serializa os dados do formulário

            $.ajax({
                url: 'filtrar_registros.php', // Arquivo PHP que processará a pesquisa
                method: 'GET',
                data: formData,
                success: function (response) {
                    console.log('Resposta recebida:', response); // Verificar a resposta no console
                    $('#resultadosPesquisa').html(response); // Exibe os resultados na tabela
                },
                error: function (xhr, status, error) {
                    console.error('Erro na solicitação AJAX:', xhr.responseText);
                    Swal.fire('Erro!', 'Erro ao realizar a pesquisa.', 'error');
                }
            });
        });


        function abrirModalEditar(id) {
            $.ajax({
                url: 'get_registro.php',
                method: 'GET',
                data: { id: id },
                success: function (response) {
                    const registro = JSON.parse(response);

                    // Preenche os campos com os dados do registro
                    $('#editarId').val(registro.id);
                    $('#editarCidade').val(registro.cidade);
                    $('#editarProtocolo').val(registro.n_protocolo);
                    $('#editarNomeNoivo').val(registro.nome_do_noivo);
                    $('#editarNovoNomeNoivo').val(registro.novo_nome_do_noivo);
                    $('#editarNoivoMenor').val(registro.noivo_menor);
                    $('#editarNomeNoiva').val(registro.nome_da_noiva);
                    $('#editarNovoNomeNoiva').val(registro.novo_nome_da_noiva);
                    $('#editarNoivaMenor').val(registro.noiva_menor);

                    let anexosHtml = '';
                    registro.anexos.forEach(anexo => {
                        anexosHtml += `
                            <tr>
                                <td>${anexo.nome}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="visualizarAnexo('${anexo.caminho}')">Visualizar</button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removerAnexo(${registro.id}, '${anexo.nome}')">Excluir</button>
                                </td>
                            </tr>`;
                    });
                    $('#anexosEditar').html(anexosHtml);

                    $('#modalEditar').modal('show');
                }
            });
        }


        function visualizarAnexo(caminho) {
            window.open(caminho, '_blank');
        }

        function removerAnexo(idRegistro, nomeAnexo) {
            Swal.fire({
                title: 'Tem certeza?',
                text: 'Você deseja excluir este anexo?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'remover_anexo.php',
                        method: 'POST',
                        data: {
                            id: idRegistro,
                            nome: nomeAnexo
                        },
                        success: function (response) {
                            const result = JSON.parse(response);
                            if (result.success) {
                                Swal.fire({
                                    title: 'Excluído!',
                                    text: 'O anexo foi removido com sucesso.',
                                    icon: 'success',
                                    timer: 1500,
                                    showConfirmButton: false
                                });

                                // Atualiza a lista de anexos sem fechar o modal
                                carregarAnexos(idRegistro);
                            } else {
                                Swal.fire('Erro!', result.message, 'error');
                            }
                        },
                        error: function () {
                            Swal.fire('Erro!', 'Não foi possível excluir o anexo.', 'error');
                        }
                    });
                }
            });
        }

        function carregarAnexos(idRegistro) {
            $.ajax({
                url: 'get_registro.php',
                method: 'GET',
                data: { id: idRegistro },
                success: function (response) {
                    const registro = JSON.parse(response);
                    let anexosHtml = '';

                    registro.anexos.forEach(anexo => {
                        anexosHtml += `
                            <tr>
                                <td>${anexo.nome}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="visualizarAnexo('${anexo.caminho}')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="removerAnexo(${idRegistro}, '${anexo.nome}')"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                </td>
                            </tr>`;
                    });

                    $('#anexosEditar').html(anexosHtml);
                }
            });
        }



        function adicionarAnexos() {
            var formData = new FormData();
            var id = $('#editarId').val();
            var arquivos = $('#editarAnexos')[0].files;

            formData.append('id', id);
            for (let i = 0; i < arquivos.length; i++) {
                formData.append('anexos[]', arquivos[i]);
            }

            $.ajax({
                url: 'adicionar_anexos.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        Swal.fire('Sucesso!', response.message, 'success');
                        carregarAnexos(id); // Atualiza a lista de anexos no modal
                        $('#editarAnexos').val(''); // Limpa o campo de arquivo
                    } else {
                        Swal.fire('Erro!', response.message, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Erro na solicitação:', xhr.responseText);
                    Swal.fire('Erro!', 'Não foi possível adicionar os anexos.', 'error');
                }
            });
        }

        $('#formEditar').on('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);

            $.ajax({
                url: 'update_registro.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json', // Define o tipo de dado esperado como JSON
                success: function (res) {
                    if (res.success) {
                        Swal.fire('Sucesso!', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Erro!', res.message, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Erro na solicitação AJAX:', xhr.responseText);
                    Swal.fire('Erro!', 'Erro ao atualizar o registro.', 'error');
                }
            });
        });

        function abrirModalSituacao(id) {
            // Define o valor do ID no campo oculto do formulário
            $('#situacaoId').val(id);

            // Limpa os campos do formulário e carrega os dados atuais da situação
            $('#formSituacao')[0].reset();

            // Faz a requisição para obter os dados atuais das situações
            $.ajax({
                url: 'get_situacao.php',
                method: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        const situacao = res.situacao;

                        // Preenche os campos do modal com os dados atuais
                        $('#situacaoPedidoDeferido').val(situacao.pedido_deferido);
                        $('#situacaoCadastroEfetivado').val(situacao.cadastro_efetivado);
                        $('#situacaoProcessoConcluido').val(situacao.processo_concluido);
                        $('#situacaoHabilitacaoConcluida').val(situacao.habilitacao_concluida);
                        $('#situacaoNumeroProclamas').val(situacao.numero_proclamas);

                        // Exibe o modal
                        $('#modalSituacao').modal('show');
                    } else {
                        Swal.fire('Erro!', res.error || 'Erro ao carregar a situação.', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Erro na solicitação:', xhr.responseText);
                    Swal.fire('Erro!', 'Erro ao carregar a situação.', 'error');
                }
            });

            // Configura o evento de submissão do formulário
            $('#formSituacao').off('submit').on('submit', function (e) {
                e.preventDefault(); // Impede o envio padrão do formulário
                const formData = $(this).serialize(); // Serializa os dados do formulário

                $.ajax({
                    url: 'salvar_situacao.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({
                                title: 'Sucesso!',
                                text: 'Situação salva com sucesso.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload(); // Recarrega a página após o alerta de sucesso ser confirmado
                            });
                        } else {
                            Swal.fire('Erro!', res.error || 'Erro ao salvar a situação.', 'error');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erro na solicitação:', xhr.responseText);
                        Swal.fire('Erro!', 'Erro ao salvar a situação.', 'error');
                    }
                });
            });
        }



        function abrirModalVisualizar(id) {
            $.ajax({
                url: 'get_registro.php',
                method: 'GET',
                data: { id: id },
                success: function (data) {
                    const registro = JSON.parse(data);

                    // Preenche os campos do modal
                    $('#visualizarCidade').val(registro.cidade);
                    $('#visualizarProtocolo').val(registro.n_protocolo);
                    $('#visualizarNomeNoivo').val(registro.nome_do_noivo);
                    $('#visualizarNovoNomeNoivo').val(registro.novo_nome_do_noivo);
                    $('#visualizarNoivoMenor').val(registro.noivo_menor ? 'Sim' : 'Não');
                    $('#visualizarNomeNoiva').val(registro.nome_da_noiva);
                    $('#visualizarNovoNomeNoiva').val(registro.novo_nome_da_noiva);
                    $('#visualizarNoivaMenor').val(registro.noiva_menor ? 'Sim' : 'Não');
                    $('#visualizarPedidoDeferido').val(registro.pedido_deferido ? 'Sim' : 'Não');
                    $('#visualizarCadastroEfetivado').val(registro.cadastro_efetivado ? 'Sim' : 'Não');
                    $('#visualizarProcessoConcluido').val(registro.processo_concluido ? 'Sim' : 'Não');
                    $('#visualizarHabilitacaoConcluida').val(registro.habilitacao_concluida ? 'Sim' : 'Não');
                    $('#visualizarNumeroProclamas').val(registro.numero_proclamas);

                    // Limpa a tabela de anexos antes de carregar novos
                    $('#anexosVisualizar').empty();

                    // Carrega anexos na tabela
                    registro.anexos.forEach(anexo => {
                        $('#anexosVisualizar').append(`
                            <tr>
                                <td>${anexo.nome}</td>
                                <td><a href="${anexo.caminho}" target="_blank" class="btn btn-primary btn-sm"><i class="fa fa-eye" aria-hidden="true"></i></a></td>
                            </tr>
                        `);
                    });

                    // Abre o modal de visualização
                    $('#modalVisualizar').modal('show');
                },
                error: function () {
                    Swal.fire('Erro!', 'Erro ao carregar os dados.', 'error');
                }
            });
        }

        $(document).ready(function () {
            // Ação de enviar formulário de pesquisa
            $('#searchForm').on('submit', function (e) {
                e.preventDefault(); // Impede o recarregamento da página
                var formData = $(this).serialize(); // Serializa os dados do formulário

                $.ajax({
                    url: 'pesquisar_registros.php', // URL do arquivo PHP
                    method: 'GET',
                    data: formData, // Dados do formulário
                    success: function (response) {
                        console.log('Resposta recebida:', response); // Verifica a resposta no console
                        $('#resultadosPesquisa').html(response); // Insere os resultados na tabela
                    },
                    error: function (xhr, status, error) {
                        console.error('Erro na solicitação AJAX:', xhr.responseText);
                        Swal.fire('Erro!', 'Erro ao realizar a pesquisa.', 'error');
                    }
                });
            });
        });


        // Detecta quando o modal "modalSituacao" é fechado e recarrega a página
        $('#modalEditar, #modalCadastro').on('hidden.bs.modal', function () {
            location.reload(); // Recarrega a página
        });

        function imprimirGuia(id) {
            const url = `guia_de_impressao.php?id=${id}`;
            window.open(url, '_blank'); // Abre a URL em uma nova aba
        }

    </script>

    <?php include(__DIR__ . '/../../rodape.php'); ?>
</body>

</html>
