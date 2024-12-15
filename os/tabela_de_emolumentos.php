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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">  
    <style>  
        :root {  
            --primary-color: #2196F3;  
            --secondary-color: #6c757d;  
            --success-color: #28a745;  
            --danger-color: #dc3545;  
            --warning-color: #ffc107;  
            --bg-primary: #ffffff;  
            --bg-secondary: #f8f9fa;  
            --text-primary: #2c3e50;  
            --text-secondary: #6c757d;  
            --border-color: #dee2e6;  
        }  

        body.dark-mode {  
            --primary-color: #3498db;  
            --bg-primary: #1a1d21;  
            --bg-secondary: #242832;  
            --text-primary: #e9ecef;  
            --text-secondary: #adb5bd;  
            --border-color: #2d3238;  
            --input-bg: #2d3238;  
            --input-border: #404650;  
        }  

        body.dark-mode .container {  
            background-color: var(--bg-primary);  
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);  
        }  

        body.dark-mode .form-control {  
            background-color: var(--input-bg);  
            border-color: var(--input-border);  
            color: var(--text-primary);  
        }  

        body.dark-mode .form-control:focus {  
            background-color: var(--input-bg);  
            border-color: var(--primary-color);  
            color: var(--text-primary);  
        }  

        body.dark-mode .table {  
            color: var(--text-primary);  
        }  

        body.dark-mode .table thead th {  
            background-color: var(--bg-secondary);  
            border-color: var(--border-color);  
            color: var(--text-primary);  
        }  

        body.dark-mode .table td {  
            border-color: var(--border-color);  
        }  

        body.dark-mode .table tbody tr:hover {  
            background-color: var(--bg-secondary);  
        }  

        body.dark-mode .dataTables_wrapper .dataTables_length,  
        body.dark-mode .dataTables_wrapper .dataTables_filter,  
        body.dark-mode .dataTables_wrapper .dataTables_info,  
        body.dark-mode .dataTables_wrapper .dataTables_processing,  
        body.dark-mode .dataTables_wrapper .dataTables_paginate {  
            color: var(--text-secondary);  
        }  

        body.dark-mode .dataTables_wrapper .dataTables_filter input {  
            background-color: var(--input-bg);  
            border-color: var(--input-border);  
            color: var(--text-primary);  
        }  

        body.dark-mode .dataTables_wrapper .dataTables_length select {  
            background-color: var(--input-bg);  
            border-color: var(--input-border);  
            color: var(--text-primary);  
        }  

        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button {  
            background: var(--bg-secondary);  
            color: var(--text-primary) !important;  
            border-color: var(--border-color);  
        }  

        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button.current,  
        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {  
            background: var(--primary-color);  
            color: white !important;  
            border-color: var(--primary-color);  
        }  

        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button:hover {  
            background: var(--primary-color);  
            color: white !important;  
            border-color: var(--primary-color);  
        }  

        body.dark-mode .card {  
            background-color: var(--bg-primary);  
            border-color: var(--border-color);  
        }  

        body.dark-mode .btn-secondary {  
            background-color: var(--bg-secondary);  
            border-color: var(--border-color);  
            color: var(--text-primary);  
        }  

        body.dark-mode .btn-secondary:hover {  
            background-color: var(--primary-color);  
            border-color: var(--primary-color);  
            color: white;  
        }  

        body {  
            font-family: 'Inter', sans-serif;  
            background-color: var(--bg-secondary);  
            color: var(--text-primary);  
        }  

        .container {  
            background-color: var(--bg-primary);  
            border-radius: 10px;  
            padding: 2rem;  
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);  
            margin-top: 20px;  
        }  

        .form-control {  
            border-radius: 6px;  
            border: 1.5px solid var(--border-color);  
            padding: 0.25rem 0.78rem;  
            transition: all 0.2s ease;  
        }  

        .form-control:focus {  
            border-color: var(--primary-color);  
            box-shadow: 0 0 0 0.2rem rgba(33, 150, 243, 0.15);  
        }  

        .btn {  
            padding: 0.625rem 1.25rem;  
            border-radius: 6px;  
            font-weight: 500;  
            transition: all 0.3s ease;  
        }  

        .btn:hover {  
            transform: translateY(-1px);  
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);  
        }  

        .table-container {  
            margin-top: 2rem;  
            border-radius: 8px;  
            overflow: hidden;  
        }  

        .table {  
            margin-bottom: 0;  
        }  

        .table th {  
            background-color: var(--bg-secondary);  
            font-weight: 600;  
            text-transform: uppercase;  
            font-size: 0.875rem;  
            letter-spacing: 0.5px;  
            vertical-align: middle;  
        }  

        .table td {  
            vertical-align: middle;  
            padding: 1rem;  
        }  

        /* DataTables Customization */  
        .dataTables_wrapper .dataTables_length,  
        .dataTables_wrapper .dataTables_filter {  
            margin: 1rem 0;  
        }  

        .dataTables_wrapper .dataTables_filter input {  
            border: 1.5px solid var(--border-color);  
            border-radius: 6px;  
            padding: 0.5rem 1rem;  
            margin-left: 0.5rem;  
            width: 250px;  
        }  

        .dataTables_wrapper .dataTables_length select {  
            border: 1.5px solid var(--border-color);  
            border-radius: 6px;  
            padding: 0.30rem 2rem 0.5rem 1rem;  
            margin: 0 0.5rem;  
        }  

        .money-column {  
            text-align: right !important;  
        }  

        @media (max-width: 768px) {  
            .container {  
                padding: 1rem;  
            }  

            .dataTables_wrapper .dataTables_filter input {  
                width: 100%;  
                margin-left: 0;  
                margin-top: 0.5rem;  
            }  
        }  

        h3 {  
            color: var(--text-primary);  
            font-weight: 600;  
            margin-bottom: 1.5rem;  
        }  

        h4 {  
            color: var(--text-primary);  
            font-weight: 500;  
            margin-bottom: 1rem;  
        }  

        hr {  
            margin: 1.5rem 0;  
            border-color: var(--border-color);  
        }  

        .form-label {  
            font-weight: 500;  
            color: var(--text-secondary);  
            margin-bottom: 0.5rem;  
        }  

        .table {  
            width: 100% !important;  
            table-layout: fixed;  
        }  

        .table th,  
        .table td {  
            overflow: hidden;  
            text-overflow: ellipsis;  
            white-space: nowrap;  
        }  

        .table td:nth-child(2) {  
            white-space: normal;  
            word-wrap: break-word;  
        }  
    </style>  
</head>
<body class="light-mode">  
    <?php include(__DIR__ . '/../menu.php'); ?>  

    <div id="main" class="main-content">  
        <div class="container">  
            <h3><i class="fa fa-table me-2" style="margin-right: 7px;"></i>Tabela de Emolumentos</h3>  
            <hr>  
            
            <div class="card">  
                <div class="card-body">  
                    <form id="pesquisarForm" method="GET">  
                        <div class="row">  
                            <div class="col-md-2">  
                                <div class="form-group">  
                                    <label class="form-label" for="ato">Código do Ato</label>  
                                    <input type="text" class="form-control" id="ato" name="ato" pattern="[0-9.]*">  
                                </div>  
                            </div>  
                            <div class="col-md-6">  
                                <div class="form-group">  
                                    <label class="form-label" for="descricao">Descrição</label>  
                                    <input type="text" class="form-control" id="descricao" name="descricao">  
                                </div>  
                            </div>  
                            <div class="col-md-4">  
                                <div class="form-group">  
                                    <label class="form-label" for="atribuicao">Atribuição</label>  
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
                            </div>  
                            <div class="col-12 mt-3">  
                                <button type="submit" class="btn btn-primary w-100">  
                                    <i class="fa fa-search me-2"></i>Pesquisar  
                                </button>  
                            </div>  
                        </div>  
                    </form>  
                </div>  
            </div>  

            <div class="table-container mt-4">  
                <div class="table-responsive">  
                    <table id="resultadosTabela" class="table table-hover">  
                        <thead>  
                            <tr>  
                                <th>Ato</th>  
                                <th>Descrição</th>  
                                <th class="money-column">Emolumentos</th>  
                                <th class="money-column">FERC</th>  
                                <th class="money-column">FADEP</th>  
                                <th class="money-column">FEMP</th>  
                                <th class="money-column">Total</th>  
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
                                    <td class="money-column"><?php echo is_numeric($emolumento['EMOLUMENTOS']) ? 'R$ ' . number_format($emolumento['EMOLUMENTOS'], 2, ',', '.') : htmlspecialchars($emolumento['EMOLUMENTOS']); ?></td>  
                                    <td class="money-column"><?php echo is_numeric($emolumento['FERC']) ? 'R$ ' . number_format($emolumento['FERC'], 2, ',', '.') : ''; ?></td>  
                                    <td class="money-column"><?php echo is_numeric($emolumento['FADEP']) ? 'R$ ' . number_format($emolumento['FADEP'], 2, ',', '.') : ''; ?></td>  
                                    <td class="money-column"><?php echo is_numeric($emolumento['FEMP']) ? 'R$ ' . number_format($emolumento['FEMP'], 2, ',', '.') : ''; ?></td>  
                                    <td class="money-column"><?php echo is_numeric($emolumento['TOTAL']) ? 'R$ ' . number_format($emolumento['TOTAL'], 2, ',', '.') : ''; ?></td>  
                                </tr>  
                            <?php  
                            }  
                            ?>  
                        </tbody>  
                    </table>  
                </div>  
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
            var table = $('#resultadosTabela').DataTable({  
                "language": {  
                    "url": "../style/Portuguese-Brasil.json"  
                },  
                "order": [],  
                "pageLength": 25,  
                "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],  
                "columnDefs": [  
                    { "width": "8%", "targets": 0 }, // Coluna Ato  
                    { "width": "40%", "targets": 1 }, // Coluna Descrição  
                    { "width": "10%", "className": "text-right", "targets": 2 }, // Coluna Emolumentos  
                    { "width": "10%", "className": "text-right", "targets": 3 }, // Coluna FERC  
                    { "width": "10%", "className": "text-right", "targets": 4 }, // Coluna FADEP  
                    { "width": "10%", "className": "text-right", "targets": 5 }, // Coluna FEMP  
                    { "width": "12%", "className": "text-right", "targets": 6 }  // Coluna Total  
                ],  
                "dom": "<'row'<'col-sm-6'l><'col-sm-6'f>>" +  
                    "<'row'<'col-sm-12'tr>>" +  
                    "<'row'<'col-sm-5'i><'col-sm-7'p>>",  
                "responsive": true,  
                "initComplete": function() {  
                    $('.dataTables_filter input').addClass('form-control');  
                    $('.dataTables_length select').addClass('form-control');  
                }  
            }); 

            $('#ato').on('input', function() {  
                this.value = this.value.replace(/[^0-9.]/g, '');  
            });  

            // Mantém os valores dos filtros após o submit  
            var urlParams = new URLSearchParams(window.location.search);  
            $('#ato').val(urlParams.get('ato'));  
            $('#descricao').val(urlParams.get('descricao'));  
            $('#atribuicao').val(urlParams.get('atribuicao'));  
        });  
    </script>  
    
    <?php include(__DIR__ . '/../rodape.php'); ?>  
</body>  
</html>