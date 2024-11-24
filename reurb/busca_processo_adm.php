<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Busca de Processos Administrativos</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <style>
        .custom-container {
            max-width: 1000px;
            margin: auto;
        }
        table th, table td {
            text-align: center;
            vertical-align: middle;
        }
        .btn-central {
            background: #6c927c;
            border: #6c927c;
            color: #fff;
        }
        .btn-central:hover {
            background: #638873;
            border: #638873;
            color: #fff;
        }
    </style>
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Busca de Processos Administrativos</h3>
            <div>
                <button type="button" class="btn btn-central" onclick="window.location.href='index.php'">
                    <i class="fa fa-desktop" aria-hidden="true"></i> Central de Acesso
                </button>
                <button type="button" class="btn btn-primary" onclick="window.location.href='cadastro_processo_adm.php'">
                    <i class="fa fa-plus" aria-hidden="true"></i> Cadastrar Novo</button>
                </button>
            </div>
        </div>
        <hr>

        <!-- Filtros de Busca -->
        <form id="filtroProcessos">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="processoAdm">Número do Processo Administrativo</label>
                    <input type="text" class="form-control" id="processoAdm" name="processoAdm" placeholder="Ex.: 1234/2024">
                </div>
                <div class="form-group col-md-6">
                    <label for="municipio">Município</label>
                    <input type="text" class="form-control" id="municipio" name="municipio" placeholder="Ex.: São Luís">
                </div>
                <div class="form-group col-md-6">
                    <label for="representante">Representante</label>
                    <input type="text" class="form-control" id="representante" name="representante" placeholder="Nome do Representante">
                </div>
                <div class="form-group col-md-6">
                    <label for="dataDePublicacao">Data de Publicação</label>
                    <input type="date" class="form-control" id="dataDePublicacao" name="dataDePublicacao">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Buscar</button>
        </form>
        <hr>
        <h5>Resultado da pesquisa</h5>
        <!-- Resultados da Pesquisa -->
        <div class="table-responsive">
            <table id="tabelaResultados" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Nº Processo</th>
                        <th>Município</th>
                        <th>Representante</th>
                        <th>Data de Publicação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dados carregados via AJAX -->
                </tbody>
            </table>
        </div>

    </div>
</div>
</div>

<script>
    $(document).ready(function () {
        // Inicializa o DataTable
        const tabela = $('#tabelaResultados').DataTable({
            language: {
                url: "../style/Portuguese-Brasil.json"
            },
            pageLength: 10,
            destroy: true, // Permite reinicializar o DataTable após atualização
            lengthMenu: [5, 10, 25, 50]
        });

        // Submissão do filtro de busca
        $('#filtroProcessos').on('submit', function (e) {
            e.preventDefault();

            const formData = $(this).serialize();
            $.ajax({
                url: 'buscar_processo_adm_action.php',
                method: 'GET',
                data: formData,
                success: function (response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;

                        // Limpa os dados da tabela
                        tabela.clear();

                        if (!data.success || !data.results.length) {
                            Swal.fire('Atenção!', 'Nenhum processo encontrado.', 'warning');
                            tabela.draw(); // Atualiza a tabela vazia
                            return;
                        }

                        // Adiciona os dados na tabela
                        data.results.forEach((processo) => {
                            tabela.row.add([
                                processo.processo_adm,
                                processo.municipio,
                                processo.representante,
                                processo.data_de_publicacao || '--',
                                `
                                <button class="btn btn-primary btn-sm visualizar" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" data-id="${processo.id}"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                <button class="btn btn-warning btn-sm editar" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" data-id="${processo.id}"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                `
                            ]);
                        });

                        // Renderiza os dados adicionados
                        tabela.draw();
                    } catch (error) {
                        console.error('Erro ao processar a resposta:', error);
                        Swal.fire('Erro!', 'Erro ao processar os dados.', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Erro!', 'Erro na requisição.', 'error');
                }
            });
        });

        // Ação de Visualizar
        $(document).on('click', '.visualizar', function () {
            const id = $(this).data('id');
            $.ajax({
                url: 'visualizar_processo_adm_action.php',
                method: 'GET',
                data: { id },
                success: function (response) {
                    try {
                        const processo = typeof response === 'string' ? JSON.parse(response) : response;

                        if (!processo.success) {
                            Swal.fire('Erro!', processo.message || 'Erro ao buscar os dados do processo.', 'error');
                            return;
                        }

                        let detalhes = `
                            <p><strong>Número do Processo:</strong> ${processo.data.processo_adm}</p>
                            <p><strong>Data de Publicação:</strong> ${processo.data.data_de_publicacao}</p>
                            <p><strong>Classificação:</strong> ${processo.data.classificacao_individual}</p>
                            <p><strong>Direito Real Outorgado:</strong> ${processo.data.direito_real_outorgado}</p>
                            <p><strong>Município:</strong> ${processo.data.municipio}</p>
                            <p><strong>Qualificação do Município:</strong> ${processo.data.qualificacao_municipio || 'N/A'}</p>
                            <p><strong>Representante:</strong> ${processo.data.representante}</p>
                            <p><strong>Qualificação do Representante:</strong> ${processo.data.qualificacao_representante || 'N/A'}</p>
                            <p><strong>Edital:</strong> ${processo.data.edital || 'N/A'}</p>
                            <p><strong>Data do Edital:</strong> ${processo.data.data_edital || 'N/A'}</p>
                            <p><strong>Responsável Técnico:</strong> ${processo.data.responsavel_tecnico || 'N/A'}</p>
                            <p><strong>Qualificação do Responsável Técnico:</strong> ${processo.data.qualificacao_responsavel_tecnico || 'N/A'}</p>
                            <p><strong>Matrícula Mãe:</strong> ${processo.data.matricula_mae || 'N/A'}</p>
                            <p><strong>Oficial do Registro:</strong> ${processo.data.oficial_do_registro || 'N/A'}</p>
                            <p><strong>Cargo do Oficial:</strong> ${processo.data.cargo_oficial || 'N/A'}</p>
                            <p><strong>Data de Cadastro:</strong> ${processo.data.data_cadastro}</p>
                        `;


                        Swal.fire({
                            title: 'Detalhes do Processo',
                            html: detalhes,
                            icon: 'info',
                            width: '600px'
                        });
                    } catch (error) {
                        console.error('Erro ao processar a resposta:', error);
                        Swal.fire('Erro!', 'Erro ao processar os dados.', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Erro!', 'Erro ao buscar os dados do processo.', 'error');
                }
            });
        });

        // Ação de Editar
        $(document).on('click', '.editar', function () {
            const id = $(this).data('id');
            window.location.href = `editar_processo_adm.php?id=${id}`;
        });
    });

</script>

</body>

</html>
