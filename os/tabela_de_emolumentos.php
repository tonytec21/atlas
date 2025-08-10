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

    <!-- Core CSS -->
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../style/css/style.css">  
    <link rel="icon" href="../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">  
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">  

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">  
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">  
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">

    <style>  
        :root {  
            --primary-color: #2563eb;  
            --secondary-color: #6c757d;  
            --success-color: #16a34a;  
            --danger-color: #dc3545;  
            --warning-color: #f59e0b;  
            --bg-primary: #ffffff;  
            --bg-secondary: #f6f8fb;  
            --text-primary: #0f172a;  
            --text-secondary: #6b7280;  
            --border-color: #e5e7eb;  
            --soft-shadow: 0 10px 26px rgba(0,0,0,.08);
        }  

        body.dark-mode {  
            --primary-color: #3b82f6;  
            --bg-primary: #0f172a;  
            --bg-secondary: #111827;  
            --text-primary: #e5e7eb;  
            --text-secondary: #9ca3af;  
            --border-color: #1f2937;  
            --input-bg: #111827;  
            --input-border: #1f2937;  
        }  

        body {  
            font-family: 'Inter', sans-serif;  
            background-color: var(--bg-secondary);  
            color: var(--text-primary);  
        }  

        .container {  
            background-color: var(--bg-primary);  
            border-radius: 14px;  
            padding: 1.25rem 1.25rem 2rem;  
            box-shadow: var(--soft-shadow);  
            margin-top: 20px;  
        }  

        /* HERO / TÍTULO */
        .page-hero{
          background: linear-gradient(180deg, rgba(79,70,229,.10), rgba(79,70,229,0));
          border-radius: 18px;
          padding: 18px 18px 8px 18px;
          margin: 4px 0 12px;
          box-shadow: var(--soft-shadow);
        }
        .page-hero .title-row{ display:flex;align-items:center;gap:14px;flex-wrap:wrap; }
        .page-hero .title-icon{
          width:48px;height:48px;border-radius:12px;background:#EEF2FF;color:#3730A3;
          display:flex;align-items:center;justify-content:center;font-size:20px;
        }
        body.dark-mode .page-hero .title-icon{ background:#1f2937;color:#c7d2fe; }
        .page-hero h1{ font-size: clamp(1.25rem, .9rem + 2vw, 1.75rem); font-weight: 800;margin:0;letter-spacing:.2px; }
        .page-hero .subtitle{ font-size:.95rem; color: var(--text-secondary); margin-top:2px; }

        /* Form */
        .form-label {  
            font-weight: 600;  
            color: var(--text-secondary);  
            margin-bottom: 0.4rem;  
        }  
        .form-control {  
            border-radius: 10px;  
            border: 1.5px solid var(--border-color);  
            padding: 0.45rem 0.9rem;  
            transition: all 0.2s ease;  
            background: #fff;
        }  
        .form-control:focus {  
            border-color: var(--primary-color);  
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);  
        }  
        body.dark-mode .form-control {  
            background-color: var(--input-bg);  
            border-color: var(--input-border);  
            color: var(--text-primary);  
        }  
        body.dark-mode .form-control:focus {  
            background-color: var(--input-bg);  
            border-color: var(--primary-color);  
        }  

        /* Buttons */
        .btn {  
            padding: 0.625rem 1rem;  
            border-radius: 10px;  
            font-weight: 600;  
            transition: transform .15s ease, box-shadow .2s ease;  
        }  
        .btn:hover {  
            transform: translateY(-1px);  
            box-shadow: 0 8px 18px rgba(0,0,0,0.12);  
        }  
        .btn-primary { background: var(--primary-color); border-color: var(--primary-color); }
        .btn-secondary { background: #e5e7eb; border-color:#e5e7eb; color:#111827; }
        body.dark-mode .btn-secondary { background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary); }  
        body.dark-mode .btn-secondary:hover { background-color: var(--primary-color); border-color: var(--primary-color); color: white; }  

        /* Table container */
        .table-container {  
            margin-top: 1.25rem;  
            border-radius: 12px;  
            overflow: hidden;  
            border: 1px solid var(--border-color);
            background: #fff;
        }  
        body.dark-mode .table-container { background: var(--bg-primary); }

        /* DataTables */
        .dataTables_wrapper .dataTables_length,  
        .dataTables_wrapper .dataTables_filter {  
            margin: 1rem 0;  
        }  
        .dataTables_wrapper .dataTables_filter input {  
            border: 1.5px solid var(--border-color);  
            border-radius: 10px;  
            padding: 0.45rem 0.9rem;  
            margin-left: 0.5rem;  
            width: 260px;  
        }  
        .dataTables_wrapper .dataTables_length select {  
            border: 1.5px solid var(--border-color);  
            border-radius: 10px;  
            padding: 0.30rem 2rem 0.5rem 0.75rem;  
            margin: 0 0.5rem;  
        }  
        .dt-buttons { margin-bottom: 12px; }
        .dt-buttons .btn { margin-right: 6px; }

        .table {  
            margin-bottom: 0;  
            width: 100% !important;  
        }  
        .table thead th {  
            background-color: var(--bg-secondary);  
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;  
            text-transform: uppercase;  
            font-size: 0.82rem;  
            letter-spacing: .4px;  
            vertical-align: middle;  
        }  
        .table td {  
            vertical-align: middle;  
            padding: 0.85rem 0.9rem;  
        }  
        .money-column { text-align: right !important; }  

        /* Responsive helpers */
        @media (max-width: 992px){
            .dataTables_wrapper .dataTables_filter input { width: 100%; margin-left: 0; margin-top: 0.5rem; }
            .dataTables_wrapper .dt-buttons { display:flex; flex-wrap:wrap; gap:8px; }
        }
        @media (max-width: 576px){
            .container { padding: 1rem; }
        }

        /* Dark-mode tweaks for DataTables */
        body.dark-mode .table { color: var(--text-primary); }  
        body.dark-mode .table thead th { background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary); }  
        body.dark-mode .table td { border-color: var(--border-color); }  
        body.dark-mode .table tbody tr:hover { background-color: #0f1b33; }  
        body.dark-mode .dataTables_wrapper .dataTables_length,  
        body.dark-mode .dataTables_wrapper .dataTables_filter,  
        body.dark-mode .dataTables_wrapper .dataTables_info,  
        body.dark-mode .dataTables_wrapper .dataTables_processing,  
        body.dark-mode .dataTables_wrapper .dataTables_paginate { color: var(--text-secondary); }  
        body.dark-mode .dataTables_wrapper .dataTables_filter input { background-color: var(--input-bg); border-color: var(--input-border); color: var(--text-primary); }  
        body.dark-mode .dataTables_wrapper .dataTables_length select { background-color: var(--input-bg); border-color: var(--input-border); color: var(--text-primary); }  
        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button { background: var(--bg-secondary); color: var(--text-primary) !important; border-color: var(--border-color); }  
        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button.current,  
        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover { background: var(--primary-color); color: white !important; border-color: var(--primary-color); }  
        body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: var(--primary-color); color: white !important; border-color: var(--primary-color); }  
    </style>  
</head>
<body class="light-mode">  
    <?php include(__DIR__ . '/../menu.php'); ?>  

    <?php
        // Valores atuais de filtro (para manter no form)
        $vAto = isset($_GET['ato']) ? htmlspecialchars($_GET['ato']) : '';
        $vDesc = isset($_GET['descricao']) ? htmlspecialchars($_GET['descricao']) : '';
        $vAtrib = isset($_GET['atribuicao']) ? htmlspecialchars($_GET['atribuicao']) : '';
    ?>

    <div id="main" class="main-content">  
        <div class="container">  
            <!-- HERO / TÍTULO -->
            <section class="page-hero" aria-label="Cabeçalho da página">
              <div class="title-row">
                <div class="title-icon"><i class="fa fa-table" aria-hidden="true"></i></div>
                <div class="title-texts">
                  <h1>Tabela de Emolumentos</h1>
                  <div class="subtitle muted">Consulta dos valores e faixas de emolumentos com filtros rápidos e exportação.</div>
                </div>
              </div>
            </section>
            <div class="d-flex flex-wrap justify-content-center justify-content-md-between align-items-center text-center mb-3 top-actions">
                <div class="col-md-auto mb-2">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fa fa-search" aria-hidden="true"></i> Ordens de Serviço
                    </a>
                </div>
            </div>
            
            <div class="card border-0">  
                <div class="card-body">  
                    <form id="pesquisarForm" method="GET" autocomplete="off" role="search" aria-label="Filtro de emolumentos">  
                        <div class="form-row">  
                            <div class="col-md-2 col-6">  
                                <div class="form-group">  
                                    <label class="form-label" for="ato">Código do Ato</label>  
                                    <input type="text" class="form-control" id="ato" name="ato" pattern="[0-9.]*" placeholder="Ex.: 16.01" value="<?php echo $vAto; ?>">  
                                </div>  
                            </div>  
                            <div class="col-md-6 col-12">  
                                <div class="form-group">  
                                    <label class="form-label" for="descricao">Descrição</label>  
                                    <input type="text" class="form-control" id="descricao" name="descricao" placeholder="Ex.: Registro de..." value="<?php echo $vDesc; ?>">  
                                </div>  
                            </div>  
                            <div class="col-md-4 col-12">  
                                <div class="form-group">  
                                    <label class="form-label" for="atribuicao">Atribuição</label>  
                                    <select class="form-control" id="atribuicao" name="atribuicao">  
                                        <option value="">Selecione a Atribuição</option>  
                                        <option value="13" <?php echo $vAtrib==='13'?'selected':''; ?>>Notas</option>  
                                        <option value="14" <?php echo $vAtrib==='14'?'selected':''; ?>>Registro Civil</option>  
                                        <option value="15" <?php echo $vAtrib==='15'?'selected':''; ?>>Títulos e Documentos e Pessoas Jurídicas</option>  
                                        <option value="16" <?php echo $vAtrib==='16'?'selected':''; ?>>Registro de Imóveis</option>  
                                        <option value="17" <?php echo $vAtrib==='17'?'selected':''; ?>>Protesto</option>  
                                        <option value="18" <?php echo $vAtrib==='18'?'selected':''; ?>>Contratos Marítimos</option>  
                                    </select>  
                                </div>  
                            </div>  
                        </div>  
                        <div class="row mt-2">
                            <div class="col-12 col-md-8 order-2 order-md-1 mt-2 mt-md-0">
                                <button type="button" class="btn btn-secondary w-100 w-md-auto" onclick="document.getElementById('pesquisarForm').reset(); window.location='tabela_de_emolumentos.php';">
                                    <i class="fa fa-undo" aria-hidden="true"></i> Limpar
                                </button>
                            </div>
                            <div class="col-12 col-md-4 order-1 order-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fa fa-search" aria-hidden="true"></i> Pesquisar
                                </button>
                            </div>
                        </div>
                    </form>  
                </div>  
            </div>  

            <div class="table-container mt-4">  
                <div class="dt-buttons mb-0 px-3 pt-3">  
                    <!-- Botões do DataTables aparecerão aqui -->
                </div>  

                <div class="table-responsive px-3 pb-3">  
                    <table id="resultadosTabela" class="table table-hover nowrap" style="width:100%">  
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
                            try {
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
                                    $fmt = function($v){
                                        return is_numeric($v) ? 'R$ ' . number_format($v, 2, ',', '.') : '';
                                    };
                                    ?>
                                    <tr>  
                                        <td><?php echo htmlspecialchars($emolumento['ATO']); ?></td>  
                                        <td style="white-space: normal;"><?php echo htmlspecialchars($emolumento['DESCRICAO']); ?></td>  
                                        <td class="money-column"><?php echo $fmt($emolumento['EMOLUMENTOS']); ?></td>  
                                        <td class="money-column"><?php echo $fmt($emolumento['FERC']); ?></td>  
                                        <td class="money-column"><?php echo $fmt($emolumento['FADEP']); ?></td>  
                                        <td class="money-column"><?php echo $fmt($emolumento['FEMP']); ?></td>  
                                        <td class="money-column"><?php echo $fmt($emolumento['TOTAL']); ?></td>  
                                    </tr>  
                                    <?php  
                                }  
                            } catch (Exception $e) {
                                echo '<tr><td colspan="7">Erro ao carregar dados.</td></tr>';
                            }
                            ?>  
                        </tbody>  
                    </table>  
                </div>  
            </div>
        </div>  
    </div>  

    <!-- JS (ordem correta) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>  
    <script src="../script/jquery.mask.min.js"></script>  

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>  
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>  
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>  
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>  
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>  
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>

    <script>  
        $(function() {  
            // Sanitiza o campo "ato" enquanto digita
            $('#ato').on('input', function() {  
                this.value = this.value.replace(/[^0-9.]/g, '');  
            });  

            // DataTables
            var table = $('#resultadosTabela').DataTable({  
                language: { url: "../style/Portuguese-Brasil.json" },  
                order: [],  
                pageLength: 25,  
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],  
                responsive: {
                    details: {
                        type: 'inline',
                        target: 'tr'
                    }
                },
                dom: "<'row align-items-center px-3 pt-3'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row pb-3 px-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                buttons: [  
                    {  
                        extend: 'excelHtml5',  
                        text: '<i class="fa fa-file-excel-o" aria-hidden="true"></i> Exportar Excel',  
                        titleAttr: 'Exportar para Excel',  
                        className: 'btn btn-success',  
                        exportOptions: { columns: ':visible' }  
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="fa fa-file-text-o" aria-hidden="true"></i> CSV',
                        titleAttr: 'Exportar CSV',
                        className: 'btn btn-secondary',
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'copyHtml5',
                        text: '<i class="fa fa-copy" aria-hidden="true"></i> Copiar',
                        titleAttr: 'Copiar para área de transferência',
                        className: 'btn btn-secondary',
                        exportOptions: { columns: ':visible' }
                    }
                ],
                columnDefs: [
                    { responsivePriority: 1, targets: 0 },     // Ato
                    { responsivePriority: 2, targets: 1 },     // Descrição
                    { responsivePriority: 3, targets: -1 }     // Total
                ]
            });   
        });  
    </script>  
    
    <?php include(__DIR__ . '/../rodape.php'); ?>  
</body>  
</html>
