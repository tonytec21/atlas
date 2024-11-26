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
    <title>Busca de Imóveis</title>
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
        .custom-container {
            max-width: 1000px;
            margin: auto;
        }

        table th,
        table td {
            text-align: center;
            vertical-align: middle;
        }

        .w-100 {
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
            <h3 class="mb-0">Busca de Imóveis</h3>
            <div>
                <button type="button" class="btn btn-central" onclick="window.location.href='index.php'">
                    <i class="fa fa-desktop" aria-hidden="true"></i> Central de Acesso
                </button>
                <button type="button" class="btn btn-primary" onclick="window.location.href='cadastro_imoveis.php'">
                    <i class="fa fa-plus" aria-hidden="true"></i> Cadastrar Novo
                </button>
            </div>
            </div>
            <hr>

            <!-- Filtros de Busca -->
            <form id="filtroImoveis">
                <div class="form-row">
                    <div class="form-group col-md-5">
                        <label for="proprietario">Proprietário</label>
                        <input type="text" class="form-control" id="proprietario" name="proprietario"
                            placeholder="Nome do Proprietário">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="cpfProprietario">CPF do Proprietário</label>
                        <input type="text" class="form-control" id="cpfProprietario" name="cpfProprietario" maxlength="14">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="logradouro">Logradouro</label>
                        <input type="text" class="form-control" id="logradouro" name="logradouro" placeholder="Logradouro">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="bairro">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" placeholder="Bairro">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="cidade">Cidade</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" placeholder="Cidade">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="memorialDescritivo">Memorial Descritivo</label>
                        <input type="text" class="form-control" id="memorialDescritivo" name="memorialDescritivo"
                            placeholder="Palavras-chave do memorial descritivo">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Buscar</button>
            </form>
            <hr>
            <h5>Resultado da pesquisa</h5>
            <!-- Resultados da Pesquisa -->
            <div class="table-responsive">
                <table id="tabelaImoveis" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Endereço</th>
                            <th>Proprietário</th>
                            <th>Cônjuge</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dados serão preenchidos dinamicamente -->
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script>
        $(document).ready(function () {
            // Máscara para CPF
            if ($.fn.mask) {
                $('#cpfProprietario').mask('000.000.000-00');
            } else {
                console.error('A biblioteca jquery.mask.min.js não foi carregada.');
            }

            // Inicializa DataTables
            let tabela = $('#tabelaImoveis').DataTable({
                language: {
                    url: "../style/Portuguese-Brasil.json"
                },
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50]
            });

            // Submissão do filtro de busca
            $('#filtroImoveis').on('submit', function (e) {
                e.preventDefault();

                const formData = $(this).serialize();
                $.ajax({
                    url: 'buscar_imoveis_action.php',
                    method: 'GET',
                    data: formData,
                    success: function (response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;

                            // Limpa os dados da tabela
                            tabela.clear();

                            if (!data.success || !data.results.length) {
                                Swal.fire('Atenção!', 'Nenhum imóvel encontrado.', 'warning');
                                tabela.draw(); // Atualiza a tabela vazia
                                return;
                            }

                            // Adiciona os dados na tabela
                            data.results.forEach((imovel) => {
                                tabela.row.add([
                                    imovel.endereco,
                                    imovel.proprietario,
                                    imovel.conjuge || '--',
                                    `
                                    <button class="btn btn-primary btn-sm visualizar" title="Detalhes do Imóvel" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" data-id="${imovel.id}"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <button class="btn btn-warning btn-sm editar" title="Editar" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" data-id="${imovel.id}"> <i class="fa fa-pencil" aria-hidden="true"></i></button>
                                    <button class="btn btn-info btn-sm visualizar-matricula" title="Texto da Abertura de Matrícula" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" data-id="${imovel.id}"><i class="fa fa-file-text" aria-hidden="true"></i></button>
                                    <button class="btn btn-success btn-sm visualizar-registro" title="Texto do Registro" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" data-id="${imovel.id}"><i class="fa fa-file-text" aria-hidden="true"></i></button>
                                    <button class="btn btn-secondary btn-sm exportar" title="Exportar para TXT" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" data-id="${imovel.id}"><i class="fa fa-download" aria-hidden="true"></i></button>
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
                    url: 'visualizar_imovel_action.php',
                    method: 'GET',
                    data: { id },
                    success: function (response) {
                        try {
                            const imovel = typeof response === 'string' ? JSON.parse(response) : response;

                            if (!imovel.success) {
                                Swal.fire('Erro!', imovel.message || 'Erro ao buscar os dados do imóvel.', 'error');
                                return;
                            }

                            let detalhes = `
                                <p><strong>Endereço:</strong> ${imovel.endereco}</p>
                                <p><strong>Proprietário:</strong> ${imovel.proprietario}</p>
                                <p><strong>CPF:</strong> ${imovel.proprietario_cpf}</p>
                                ${imovel.nome_conjuge ? `<p><strong>Cônjuge:</strong> ${imovel.nome_conjuge}</p>` : ''}
                                ${imovel.cpf_conjuge ? `<p><strong>CPF:</strong> ${imovel.cpf_conjuge}</p>` : ''}
                                <p><strong>Área do Lote:</strong> ${imovel.area_do_lote} m²</p>
                                <p><strong>Perímetro:</strong> ${imovel.perimetro} m</p>
                                <p><strong>Área Construída:</strong> ${imovel.area_construida} m²</p>
                                <p><strong>Processo Administrativo:</strong> ${imovel.processo_adm}</p>
                                <p><strong>Memorial Descritivo:</strong> ${imovel.memorial_descritivo}</p>
                                <p><strong>Data de Cadastro:</strong> ${imovel.data_cadastro}</p>
                            `;

                            Swal.fire({
                                title: 'Detalhes do Imóvel',
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
                        Swal.fire('Erro!', 'Erro ao buscar os dados do imóvel.', 'error');
                    }
                });
            });

            // Ação de Visualizar Matrícula
            $(document).on('click', '.visualizar-matricula', function () {
                const id = $(this).data('id');
                $.ajax({
                    url: 'visualizar_matricula_imovel.php',
                    method: 'GET',
                    data: { id },
                    success: function (response) {
                        try {
                            const matricula = typeof response === 'string' ? JSON.parse(response) : response;

                            if (!matricula.success) {
                                Swal.fire('Erro!', matricula.message || 'Erro ao buscar a matrícula.', 'error');
                                return;
                            }

                            Swal.fire({
                                title: 'Matrícula do Imóvel',
                                html: `<p>${matricula.data}</p>`,
                                icon: 'info',
                                width: '800px'
                            });
                        } catch (error) {
                            console.error('Erro ao processar a resposta:', error);
                            Swal.fire('Erro!', 'Erro ao processar os dados.', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Erro!', 'Erro ao buscar a matrícula.', 'error');
                    }
                });
            });

            // Ação de Visualizar Registro
            $(document).on('click', '.visualizar-registro', function () {
                const id = $(this).data('id');
                $.ajax({
                    url: 'visualizar_registro_imovel.php',
                    method: 'GET',
                    data: { id },
                    success: function (response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;

                            if (!data.success) {
                                Swal.fire('Erro!', data.message || 'Erro ao buscar o registro.', 'error');
                                return;
                            }

                            Swal.fire({
                                title: 'Registro do Imóvel',
                                html: `<div style="text-align: left;">${data.data}</div>`,
                                icon: 'info',
                                width: '800px'
                            });
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

            // Ação de Exportar
            $(document).on('click', '.exportar', function () {
                const id = $(this).data('id');
                $.ajax({
                    url: 'exportar_texto_imovel.php',
                    method: 'GET',
                    data: { id },
                    success: function (response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;

                            if (!data.success) {
                                Swal.fire('Erro!', data.message || 'Erro ao gerar o arquivo.', 'error');
                                return;
                            }

                            // Cria o arquivo TXT
                            const fileContent = data.texto;
                            const blob = new Blob([fileContent], { type: 'text/plain' });
                            const link = document.createElement('a');
                            link.href = URL.createObjectURL(blob);
                            // Função para aplicar a máscara no CPF
                            function formatarCPF(cpf) {
                                return cpf.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, "$1.$2.$3-$4");
                            }

                            // Gera o nome do arquivo com o padrão "NOME DO PROPRIETÁRIO - CPF" com máscara
                            const nomeProprietario = data.nome_proprietario || `imovel_${id}`;
                            const cpfProprietario = data.cpf_proprietario ? formatarCPF(data.cpf_proprietario) : '';
                            const nomeArquivo = `${nomeProprietario} - ${cpfProprietario}.txt`.replace(/[<>:"/\\|?*]/g, ''); // Remove caracteres inválidos
                            link.download = nomeArquivo;


                            link.click();
                        } catch (error) {
                            console.error('Erro ao processar a resposta:', error);
                            Swal.fire('Erro!', 'Erro ao gerar o arquivo.', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Erro!', 'Erro ao buscar os dados do imóvel.', 'error');
                    }
                });
            });

            // Ação de Editar
            $(document).on('click', '.editar', function () {
                const id = $(this).data('id');
                window.location.href = `editar_imovel.php?id=${id}`;
            });
        });
</script>

</body>

</html>
