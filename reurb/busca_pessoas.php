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
    <title>Atlas - Busca de Pessoas</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <script src="../script/jquery-3.6.0.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <style>
        .form-group {
            margin-bottom: 1rem;
        }

        .custom-container {
            max-width: 1200px;
        }

        .btn-action {
            margin-right: 5px;
        }
        .w-100{
            margin-bottom: 31px;
            margin-top: 0px;
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
            <h3 class="mb-0">Busca de Pessoas</h3>
            <div>
                <button type="button" class="btn btn-central" onclick="window.location.href='index.php'">
                    <i class="fa fa-desktop" aria-hidden="true"></i> Central de Acesso
                </button>
                <button type="button" class="btn btn-primary" onclick="window.location.href='cadastro_pessoas.php'">
                    <i class="fa fa-plus" aria-hidden="true"></i> Cadastrar Novo
                </button>
            </div>
        </div>
        <hr>
        <!-- Filtros de Pesquisa -->
        <form id="formBusca" class="mb-4">
            <div class="form-row">
                <div class="form-group col-md-2">
                    <label for="cpf">CPF</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" placeholder="000.000.000-00">
                </div>
                <div class="form-group col-md-2">
                    <label for="rg">RG</label>
                    <input type="text" class="form-control" id="rg" name="rg" placeholder="000000000-0">
                </div>
                <div class="form-group col-md-4">
                    <label for="nome">Nome</label>
                    <input type="text" class="form-control" id="nome" name="nome" placeholder="Digite o nome">
                </div>
                <div class="form-group col-md-4">
                    <label for="filiacao">Filiação</label>
                    <input type="text" class="form-control" id="filiacao" name="filiacao" placeholder="Nome da mãe ou pai">
                </div>
                    <button type="submit" class="btn btn-primary w-100">Buscar</button>
            </div>
        </form>
        <hr>
        <h5>Resultado da pesquisa</h5>
        <!-- Resultados da Pesquisa -->
        <div class="table-responsive">
            <table id="tabelaResultados" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width: 25%;">Nome</th>
                        <th style="width: 14%;">Data de Nascimento</th>
                        <th style="width: 10%;">CPF</th>
                        <th style="width: 12%;">RG</th>
                        <th style="width: 22%;">Filiação</th>
                        <th style="width: 9%;">Estado Civil</th>
                        <th style="width: 8%;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dados carregados via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

    <script>
        $(document).ready(function () {
            // Máscaras para os campos de CPF e RG
            $('#cpf').mask('000.000.000-00');
            $('#rg').mask('00.000.000-0');

            // Inicializa DataTables
            const tabela = $('#tabelaResultados').DataTable({
                language: {
                    url: "../style/Portuguese-Brasil.json"
                }
            });

            // Formulário de Busca
            $('#formBusca').on('submit', function (e) {
                e.preventDefault();
                const formData = $(this).serializeArray();
                const cpf = $('#cpf').val().replace(/\D/g, ''); // Remove máscara do CPF

                // Adiciona CPF sem máscara ao formData
                formData.push({ name: 'cpf', value: cpf });

                $.ajax({
                    url: 'buscar_pessoas_action.php',
                    method: 'GET',
                    data: formData,
                    success: function (response) {
                        const resultados = JSON.parse(response);
                        tabela.clear();

                        if (resultados.length > 0) {
                            resultados.forEach(pessoa => {
                                const filiacao = pessoa.filiacao.includes(';')
                                    ? pessoa.filiacao.replace(';', ' e ')
                                    : pessoa.filiacao;

                                const cpfFormatado = pessoa.cpf.replace(
                                    /(\d{3})(\d{3})(\d{3})(\d{2})/,
                                    '$1.$2.$3-$4'
                                );

                                const dataNascimento = new Date(pessoa.data_de_nascimento)
                                    .toLocaleDateString('pt-BR', { timeZone: 'UTC' });

                                tabela.row.add([
                                    pessoa.nome,
                                    dataNascimento,
                                    cpfFormatado,
                                    pessoa.rg,
                                    filiacao,
                                    pessoa.estado_civil,
                                    `
                                    <button class="btn btn-primary btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" onclick="visualizar(${pessoa.id})">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </button>
                                    <button class="btn btn-warning btn-sm" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" onclick="editar(${pessoa.id})">
                                        <i class="fa fa-pencil" aria-hidden="true"></i>
                                    </button>
                                    `
                                ]).draw();
                            });
                        } else {
                            Swal.fire('Sem resultados', 'Nenhuma pessoa encontrada.', 'info');
                        }
                    },
                    error: function () {
                        Swal.fire('Erro', 'Erro ao buscar os dados.', 'error');
                    }
                });
            });

            // Função para Visualizar
            window.visualizar = function (id) {
                $.ajax({
                    url: 'visualizar_pessoa_action.php',
                    method: 'GET',
                    data: { id },
                    success: function (response) {
                        const pessoa = JSON.parse(response);

                        if (pessoa.error) {
                            Swal.fire('Erro!', pessoa.error, 'error');
                            return;
                        }

                        let conteudo = '';

                        // Adiciona as informações ao conteúdo somente se existirem
                        if (pessoa.nome) conteudo += `<strong>Nome:</strong> ${pessoa.nome}<br>`;
                        if (pessoa.data_de_nascimento) conteudo += `<strong>Data de Nascimento:</strong> ${pessoa.data_de_nascimento}<br>`;
                        if (pessoa.cpf) conteudo += `<strong>CPF:</strong> ${pessoa.cpf}<br>`;
                        if (pessoa.rg) conteudo += `<strong>RG:</strong> ${pessoa.rg}<br>`;
                        if (pessoa.data_emissao_rg && pessoa.data_emissao_rg !== "0000-00-00") {conteudo += `<strong>Data de Emissão do RG:</strong> ${new Date(pessoa.data_emissao_rg).toLocaleDateString('pt-BR')}<br>`;}
                        if (pessoa.orgao_emissor_rg) conteudo += `<strong>Órgão Emissor do RG:</strong> ${pessoa.orgao_emissor_rg}<br>`;
                        if (pessoa.nacionalidade) conteudo += `<strong>Nacionalidade:</strong> ${pessoa.nacionalidade}<br>`;
                        if (pessoa.naturalidade) conteudo += `<strong>Naturalidade:</strong> ${pessoa.naturalidade}<br>`;
                        if (pessoa.profissao) conteudo += `<strong>Profissão:</strong> ${pessoa.profissao}<br>`;
                        if (pessoa.estado_civil) conteudo += `<strong>Estado Civil:</strong> ${pessoa.estado_civil}<br>`;
                        if (pessoa.regime_de_bens) conteudo += `<strong>Regime de Bens:</strong> ${pessoa.regime_de_bens}<br>`;
                        if (pessoa.filiacao) conteudo += `<strong>Filiação:</strong> ${pessoa.filiacao.replace(';', ' e ')}<br>`;
                        if (pessoa.logradouro || pessoa.numero || pessoa.bairro || pessoa.cidade || pessoa.cep) {
                            conteudo += `<strong>Endereço:</strong> `;
                            if (pessoa.logradouro) conteudo += `${pessoa.logradouro}`;
                            if (pessoa.numero) conteudo += `, ${pessoa.numero}`;
                            if (pessoa.bairro) conteudo += `, ${pessoa.bairro}`;
                            if (pessoa.cidade) conteudo += `, ${pessoa.cidade}`;
                            if (pessoa.cep) conteudo += ` - CEP: ${pessoa.cep}`;
                            conteudo += `<br>`;
                        }
                        if (pessoa.data_cadastro) conteudo += `<strong>Data de Cadastro:</strong> ${new Date(pessoa.data_cadastro).toLocaleDateString('pt-BR')}<br>`;

                        // Exibe o modal com os dados formatados
                        Swal.fire({
                            title: 'Detalhes da Pessoa',
                            html: conteudo || 'Nenhuma informação disponível.',
                            icon: 'info',
                            width: '600px'
                        });
                    },
                    error: function () {
                        Swal.fire('Erro!', 'Erro ao buscar os dados da pessoa.', 'error');
                    }
                });
            };

            // Função para Editar
            window.editar = function (id) {
                window.location.href = `editar_pessoa.php?id=${id}`;
            };
        });
    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>
