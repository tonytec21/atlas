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
    <title>Provimentos e Resoluções</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <style>
        .btn-adicionar {
            height: 38px;
            line-height: 24px;
            margin-left: 10px;
        }

        .modal-content {
            border-radius: 10px;
        }

        .modal-dialog {
            max-width: 60%;
            margin: 1.75rem auto;
        }

        .modal-header {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .modal-footer {
            border-top: none;
        }

        .modal-header.error {
            background-color: #dc3545;
            color: white;
        }

        .modal-header.success {
            background-color: #6c757d;
            color: white;
        }

        .custom-file-input ~ .custom-file-label::after {
            content: "Escolher";
        }

        .custom-file-label {
            border-radius: 0.25rem;
            padding: 0.5rem 1rem;
            background-color: #fff;
            color: #777;
            cursor: pointer;
        }

        .custom-file-input:focus ~ .custom-file-label {
            outline: -webkit-focus-ring-color auto 1px;
            outline-offset: -2px;
        }

    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisar Provimentos e Resoluções</h3>
            <hr>
            <form id="pesquisarForm" method="GET">
                <div class="form-row">
                    <div class="form-group col-md-2">
                            <label for="tipo">Tipo:</label>
                            <select class="form-control" id="tipo" name="tipo">
                                <option value="">Todos</option>
                                <option value="Provimento">Provimento</option>
                                <option value="Resolução">Resolução</option>
                            </select>
                        </div>    
                    <div class="form-group col-md-2">
                        <label for="numero_provimento">Nº Prov./Resol.:</label>
                        <input type="text" class="form-control" id="numero_provimento" name="numero_provimento">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="ano">Ano:</label>
                        <input type="text" class="form-control" id="ano" name="ano" pattern="\d{4}" title="Digite um ano válido (ex: 2023)" maxlength="4" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="origem">Origem:</label>
                        <select class="form-control" id="origem" name="origem">
                            <option value="">Selecione</option>
                            <option value="CGJ_MA">CGJ/MA</option>
                            <option value="CNJ">CNJ</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="data_provimento">Data:</label>
                        <input type="date" class="form-control" id="data_provimento" name="data_provimento">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="descricao">Descrição:</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="conteudo_anexo">Conteúdo:</label>
                        <textarea class="form-control" id="conteudo_anexo" name="conteudo_anexo" rows="3"></textarea>
                    </div>
                </div>
                <div class="row mb-12">
                    <div class="col-md-6">
                        <button type="submit" style="width: 100%; color: #fff!important" class="btn btn-primary">
                            <i class="fa fa-filter" aria-hidden="true"></i> Filtrar
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" style="width: 100%; color: #fff!important" class="btn btn-secondary" onclick="window.open('cadastrar_provimento.php')">
                            <i class="fa fa-plus" aria-hidden="true"></i> Cadastrar Prov./Resol.
                        </button>
                    </div>
                </div>
            </form>
            <hr>
            <div id="resultados">
                <h5>Resultados da Pesquisa</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 90%">
                    <thead>
                        <tr>
                            <th>Tipo</th>    
                            <th>Nº</th>
                            <th>Origem</th>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = getDatabaseConnection();
                        $conditions = [];
                        $params = [];
                        $filtered = false;

                        if (!empty($_GET['numero_provimento'])) {
                            if (strpos($_GET['numero_provimento'], '/') !== false) {
                                list($numero, $ano) = explode('/', $_GET['numero_provimento']);
                                $conditions[] = 'numero_provimento = :numero AND YEAR(data_provimento) = :ano';
                                $params[':numero'] = $numero;
                                $params[':ano'] = $ano;
                            } else {
                                $conditions[] = 'numero_provimento = :numero';
                                $params[':numero'] = $_GET['numero_provimento'];
                            }
                            $filtered = true;
                        }
                        if (!empty($_GET['origem'])) {
                            $conditions[] = 'origem = :origem';
                            $params[':origem'] = $_GET['origem'];
                            $filtered = true;
                        }
                        if (!empty($_GET['tipo'])) {
                            $conditions[] = 'tipo = :tipo';
                            $params[':tipo'] = $_GET['tipo'];
                            $filtered = true;
                        }
                        if (!empty($_GET['ano'])) {
                            $conditions[] = 'YEAR(data_provimento) = :ano';
                            $params[':ano'] = $_GET['ano'];
                            $filtered = true;
                        }                        
                        if (!empty($_GET['data_provimento'])) {
                            $conditions[] = 'data_provimento = :data_provimento';
                            $params[':data_provimento'] = $_GET['data_provimento'];
                            $filtered = true;
                        }
                        if (!empty($_GET['descricao'])) {
                            $conditions[] = 'descricao LIKE :descricao';
                            $params[':descricao'] = '%' . $_GET['descricao'] . '%';
                            $filtered = true;
                        }
                        if (!empty($_GET['conteudo_anexo'])) {
                            $conditions[] = 'conteudo_anexo LIKE :conteudo_anexo';
                            $params[':conteudo_anexo'] = '%' . $_GET['conteudo_anexo'] . '%';
                            $filtered = true;
                        }
                        
                        $sql = 'SELECT * FROM provimentos';
                        if ($conditions) {
                            $sql .= ' WHERE ' . implode(' AND ', $conditions);
                        }
                        if (!$filtered) {
                            $sql .= ' ORDER BY data_provimento DESC';
                        }

                        $stmt = $conn->prepare($sql);
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        $stmt->execute();
                        $provimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($provimentos as $provimento) {
                            $numero_provimento_ano = $provimento['numero_provimento'] . '/' . date('Y', strtotime($provimento['data_provimento']));
                            ?>
                            <tr>
                                <td><?php echo $provimento['tipo']; ?></td>    
                                <td><?php echo $numero_provimento_ano; ?></td>
                                <td><?php echo $provimento['origem']; ?></td>
                                <td data-order="<?php echo date('Y-m-d', strtotime($provimento['data_provimento'])); ?>"><?php echo date('d/m/Y', strtotime($provimento['data_provimento'])); ?></td>
                                <td><?php echo $provimento['descricao']; ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" title="Visualizar Provimento" style="margin-bottom: 5px; font-size: 20px; width: 40px; height: 40px; border-radius: 5px; border: none;" onclick="visualizarProvimento('<?php echo $provimento['id']; ?>')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Visualização -->
    <div class="modal fade" id="visualizarModal" tabindex="-1" role="dialog" aria-labelledby="visualizarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header success">
                    <h5 class="modal-title" id="visualizarModalLabel">Visualizar Provimento</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="numero_provimento_modal">Número do Provimento:</label>
                                <input type="text" class="form-control" id="numero_provimento_modal" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="origem_modal">Origem:</label>
                                <input type="text" class="form-control" id="origem_modal" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="data_provimento_modal">Data do Provimento:</label>
                                <input type="text" class="form-control" id="data_provimento_modal" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="descricao_modal">Descrição:</label>
                            <textarea class="form-control" id="descricao_modal" rows="3" readonly></textarea>
                        </div>
                        <div class="form-group">
                            <label for="anexo_visualizacao">Provimento:</label>
                            <iframe id="anexo_visualizacao" style="width: 100%; height: 800px;" frameborder="0"></iframe>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            $('#tabelaResultados').DataTable({
                "language": {
                    "url": "../style/Portuguese-Brasil.json"
                },
                "order": [[3, 'desc']]
            });
        });

        function visualizarProvimento(id) {
            $.ajax({
                url: 'obter_provimento.php',
                type: 'GET',
                data: { id: id },
                success: function(response) {
                    try {
                        var provimento = JSON.parse(response);

                        var numero_provimento_ano = provimento.numero_provimento + '/' + provimento.ano_provimento;
                        $('#numero_provimento_modal').val(numero_provimento_ano);
                        $('#origem_modal').val(provimento.origem);
                        let dataProvimento = new Date(provimento.data_provimento + 'T00:00:00');
                        $('#data_provimento_modal').val(dataProvimento.toLocaleDateString('pt-BR'));
                        $('#descricao_modal').val(provimento.descricao);
                        $('#anexo_visualizacao').attr('src', provimento.caminho_anexo);

                        $('#visualizarModal').modal('show');
                    } catch (e) {
                        alert('Erro ao processar resposta do servidor.');
                    }
                },
                error: function() {
                    alert('Erro ao obter os dados do provimento.');
                }
            });
        }
    </script>
    <?php
    include(__DIR__ . '/../rodape.php');
    ?>
</body>

</html>
