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
    <title>Atlas - Tabela de Emolumentos</title>
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
            max-width: 30%;
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
            background-color: #28a745;
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

        .toast {
            min-width: 250px;
            margin-top: 0px;
        }

        .toast .toast-header {
            color: #fff;
        }

        .toast .bg-success {
            background-color: #28a745 !important;
        }

        .toast .bg-danger {
            background-color: #dc3545 !important;
        }

        .btn-delete {
            margin-bottom: 0px!important;
        }

        .status-label {
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            display: inline-block;
        }

        .status-pendente {
            background-color: #dc3545;
            width: 75px;
            text-align: center;
        }

        .status-parcialmente {
            background-color: #ffc107;
            width: 75px;
            text-align: center;
        }

        .status-liquidado {
            background-color: #28a745;
            width: 75px;
            text-align: center;
        }

    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisar Tabela de Emolumentos</h3>
            <hr>
            <form id="pesquisarForm" method="GET">
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label for="ato">Código do Ato:</label>
                        <input type="text" class="form-control" id="ato" name="ato" pattern="[0-9.]*">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="descricao">Descrição:</label>
                        <input type="text" class="form-control" id="descricao" name="descricao">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="atribuicao">Atribuição:</label>
                        <select class="form-control" id="atribuicao" name="atribuicao">
                            <option value="">Selecione a Atribuição</option>
                            <option value="13">Notas</option>
                            <option value="14">Registro Civil</option>
                            <option value="15">Títulos e Documentos e Pessoas Jurídicas</option>
                            <option value="16">Registro de Imóveis</option>
                            <option value="17">Protesto</option>
                            <option value="18">Contratos Marítimos</option>
                        </select>
                    </div>
                
                    <div class="col-md-12">
                        <button type="submit" style="width: 100%;" class="btn btn-secondary"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar</button>
                    </div>
                </div>
            </form>
            <hr>
            <div id="resultados">
                <h4>Resultados da Pesquisa</h4>
                <table id="resultadosTabela" class="table table-striped table-bordered" style="width:100%; zoom: 90%">
                    <thead>
                        <tr>
                            <th>Ato</th>
                            <th>Descrição</th>
                            <th style="width:9%;">Emolumentos</th>
                            <th style="width:8%;">FERC</th>
                            <th style="width:8%;">FADEP</th>
                            <th style="width:8%;">FEMP</th>
                            <th style="width:10%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = getDatabaseConnection();
                        $conditions = [];
                        $params = [];

                        if (!empty($_GET['ato'])) {
                            $conditions[] = 'ATO LIKE :ato';
                            $params[':ato'] = '%' . $_GET['ato'] . '%';
                        }
                        if (!empty($_GET['descricao'])) {
                            $conditions[] = 'DESCRICAO LIKE :descricao';
                            $params[':descricao'] = '%' . $_GET['descricao'] . '%';
                        }
                        if (!empty($_GET['atribuicao'])) {
                            $conditions[] = 'ATO LIKE :atribuicao';
                            $params[':atribuicao'] = $_GET['atribuicao'] . '.%';
                        }

                        $sql = 'SELECT ID, ATO, DESCRICAO, EMOLUMENTOS, FERC, FADEP, FEMP, TOTAL FROM tabela_emolumentos';
                        if ($conditions) {
                            $sql .= ' WHERE ' . implode(' AND ', $conditions);
                        }
                        $sql .= ' ORDER BY ID ASC';

                        $stmt = $conn->prepare($sql);
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        $stmt->execute();
                        $emolumentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($emolumentos as $emolumento) {
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($emolumento['ATO']); ?></td>
                                <td><?php echo htmlspecialchars($emolumento['DESCRICAO']); ?></td>
                                <td><?php echo is_numeric($emolumento['EMOLUMENTOS']) ? 'R$ ' . number_format($emolumento['EMOLUMENTOS'], 2, ',', '.') : htmlspecialchars($emolumento['EMOLUMENTOS']); ?></td>
                                <td><?php echo is_numeric($emolumento['FERC']) ? 'R$ ' . number_format($emolumento['FERC'], 2, ',', '.') : ''; ?></td>
                                <td><?php echo is_numeric($emolumento['FADEP']) ? 'R$ ' . number_format($emolumento['FADEP'], 2, ',', '.') : ''; ?></td>
                                <td><?php echo is_numeric($emolumento['FEMP']) ? 'R$ ' . number_format($emolumento['FEMP'], 2, ',', '.') : ''; ?></td>
                                <td><?php echo is_numeric($emolumento['TOTAL']) ? 'R$ ' . number_format($emolumento['TOTAL'], 2, ',', '.') : ''; ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
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
            // Inicializar DataTable
            $('#resultadosTabela').DataTable({
                "language": {
                    "url": "../style/Portuguese-Brasil.json"
                },
                "order": [],
            });

            $('#ato').on('input', function() {
                this.value = this.value.replace(/[^0-9.]/g, '');
            });
            


            $('#cpf_cliente').mask('000.000.000-00', { reverse: true }).on('blur', function() {
                var cpfCnpj = $(this).val().replace(/\D/g, '');
                if (cpfCnpj.length === 11) {
                    $(this).mask('000.000.000-00', { reverse: true });
                } else if (cpfCnpj.length === 14) {
                    $(this).mask('00.000.000/0000-00', { reverse: true });
                }
            });

            $('#total_os').mask('#.##0,00', { reverse: true });
            $('#valor_pagamento').mask('#.##0,00', { reverse: true });
            $('#valor_devolucao').mask('#.##0,00', { reverse: true });
        });

    </script>
    <?php
    include(__DIR__ . '/../rodape.php');
    ?>
</body>

</html>
