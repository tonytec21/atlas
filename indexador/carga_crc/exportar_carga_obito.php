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
    <title>Exportar Carga de Óbito</title>
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../../style/css/style.css">  
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">  
    <link rel="stylesheet" href="../../style/css/dataTables.bootstrap4.min.css">    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">  
    <script src="../../script/jquery-3.6.0.min.js"></script>  
    <script src="../../script/jquery.dataTables.min.js"></script>  
    <script src="../../script/dataTables.bootstrap4.min.js"></script>  
    <script src="../../script/bootstrap.bundle.min.js"></script>  
    <style>  
        :root {  
            /* Light Theme */  
            --bg-primary: #ffffff;  
            --bg-secondary: #f8f9fa;  
            --bg-tertiary: #e9ecef;  
            --text-primary: #2c3e50;  
            --text-secondary: #6c757d;  
            --border-color: #dee2e6;  
            --input-bg: #ffffff;  
            --input-border: #ced4da;  
            --card-shadow: 0 2px 15px rgba(0,0,0,0.08);  
            --hover-bg: #f8f9fa;  
            --accent-color: #2196F3;  
            --accent-hover: #1976D2;  
            --danger-color: #dc3545;  
            --success-color: #28a745;  
        }  

        body.dark-mode {  
            --bg-primary: #1a1d21;  
            --bg-secondary: #242832;  
            --bg-tertiary: #2d3238;  
            --text-primary: #e9ecef;  
            --text-secondary: #adb5bd;  
            --border-color: #2d3238;  
            --input-bg: #2d3238;  
            --input-border: #404650;  
            --card-shadow: 0 2px 15px rgba(0,0,0,0.2);  
            --hover-bg: #2d3238;  
            --accent-color: #3498db;  
            --accent-hover: #2980b9;  
        }  

        body {  
            font-family: 'Inter', sans-serif;  
            background-color: var(--bg-secondary);  
            color: var(--text-primary);  
            transition: all 0.3s ease;  
            margin: 0;  
            padding: 0;  
        }  

        .main-content {  
            padding: 2rem 1rem;  
        }  

        .container {  
            background-color: var(--bg-primary);  
            border-radius: 16px;  
            padding: 2rem;  
            box-shadow: var(--card-shadow);  
            margin-top: 20px;  
        }  

        .page-header {  
            display: flex;  
            align-items: center;  
            justify-content: space-between;  
            margin-bottom: 2rem;  
            padding-bottom: 1rem;  
            border-bottom: 2px solid var(--border-color);  
        }  

        .page-title {  
            font-size: 1.5rem;  
            font-weight: 600;  
            color: var(--text-primary);  
            margin: 0;  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
        }  

        .page-title i {  
            color: var(--accent-color);  
            font-size: 1.75rem;  
        }  

        .filter-section {  
            background-color: var(--bg-secondary);  
            border-radius: 12px;  
            padding: 1.5rem;  
            margin-bottom: 2rem;  
        }  

        .form-control {  
            background-color: var(--input-bg);  
            border: 1.5px solid var(--input-border);  
            color: var(--text-primary);  
            border-radius: 8px;  
            padding: 0.625rem 1rem;  
            transition: all 0.2s ease;  
            height: auto;  
        }  

        .form-control:focus {  
            border-color: var(--accent-color);  
            box-shadow: 0 0 0 0.2rem rgba(33, 150, 243, 0.15);  
            background-color: var(--input-bg);  
            color: var(--text-primary);  
        }  

        .form-label {  
            color: var(--text-secondary);  
            font-weight: 500;  
            font-size: 0.875rem;  
            margin-bottom: 0.5rem;  
        }  

        .btn {  
            padding: 0.625rem 1.25rem;  
            border-radius: 8px;  
            font-weight: 500;  
            display: inline-flex;  
            align-items: center;  
            justify-content: center;  
            gap: 0.5rem;  
            transition: all 0.3s ease;  
            border: none;  
        }  

        .btn-primary {  
            background: linear-gradient(45deg, var(--accent-color), var(--accent-hover));  
            color: white;  
        }  

        .btn-success {  
            background: linear-gradient(45deg, var(--success-color), #218838);  
            color: white;  
        }  

        .btn:hover {  
            transform: translateY(-1px);  
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);  
        }  

        .table-container {  
            background-color: var(--bg-primary);  
            border-radius: 12px;  
            overflow: hidden;  
            margin-top: 2rem;  
        }  

        .table {  
            margin-bottom: 0;  
            color: var(--text-primary);  
            border-collapse: separate;  
            border-spacing: 0;  
            width: 100%;  
        }  

        .table th {  
            background-color: var(--bg-secondary);  
            font-weight: 600;  
            padding: 1rem;  
            font-size: 0.875rem;  
            text-transform: uppercase;  
            letter-spacing: 0.5px;  
            border-top: none;  
        }  

        .table td {  
            padding: 1rem;  
            vertical-align: middle;  
            border-top: 1px solid var(--border-color);  
        }  

        .table tbody tr:hover {  
            background-color: var(--hover-bg);  
        }  

        /* Custom Checkbox */  
        .custom-checkbox {  
            width: 18px;  
            height: 18px;  
            border: 2px solid var(--input-border);  
            border-radius: 4px;  
            position: relative;  
            cursor: pointer;  
            transition: all 0.2s ease;  
        }  

        .custom-checkbox:checked {  
            background-color: var(--accent-color);  
            border-color: var(--accent-color);  
        }  

        .custom-checkbox:checked::after {  
            content: '✓';  
            color: white;  
            position: absolute;  
            top: 50%;  
            left: 50%;  
            transform: translate(-50%, -50%);  
            font-size: 12px;  
        }  

        /* DataTables Customization */  
        .dataTables_wrapper .dataTables_length,  
        .dataTables_wrapper .dataTables_filter,  
        .dataTables_wrapper .dataTables_info,  
        .dataTables_wrapper .dataTables_processing,  
        .dataTables_wrapper .dataTables_paginate {  
            color: var(--text-secondary);  
            margin: 1rem;  
        }  

        .dataTables_wrapper .dataTables_filter input {  
            background-color: var(--input-bg);  
            border: 1.5px solid var(--input-border);  
            color: var(--text-primary);  
            border-radius: 8px;  
            padding: 0.4rem 1rem;  
            margin-left: 0.5rem;  
        }  

        .dataTables_wrapper .dataTables_paginate .paginate_button {  
            border-radius: 6px;  
            padding: 0.5rem 1rem;  
            margin: 0 2px;  
            border: none;  
            background: var(--bg-secondary);  
            color: var(--text-primary) !important;  
        }  

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {  
            background: var(--accent-color);  
            color: white !important;  
        }  

        /* Responsive Design */  
        @media (max-width: 768px) {  
            .container {  
                padding: 1rem;  
            }  

            .page-header {  
                flex-direction: column;  
                align-items: flex-start;  
                gap: 1rem;  
            }  

            .btn {  
                width: 100%;  
                margin: 0.5rem 0;  
            }  

            .table-responsive {  
                margin: 0 -1rem;  
                padding: 0 1rem;  
            }  
        }  

        /* Loading Animation */  
        .loading-overlay {  
            position: fixed;  
            top: 0;  
            left: 0;  
            right: 0;  
            bottom: 0;  
            background-color: rgba(0,0,0,0.5);  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            z-index: 9999;  
        }  

        .loading-spinner {  
            width: 50px;  
            height: 50px;  
            border: 5px solid var(--bg-primary);  
            border-top: 5px solid var(--accent-color);  
            border-radius: 50%;  
            animation: spin 1s linear infinite;  
        }  

        @keyframes spin {  
            0% { transform: rotate(0deg); }  
            100% { transform: rotate(360deg); }  
        }  

        .table th {  
        vertical-align: middle !important; /* Garante alinhamento vertical centralizado */  
    }  

    .checkbox-column {  
        width: 40px;  
        text-align: center;  
        vertical-align: middle !important;  
    }  

    .form-check {  
        margin: 0;  
        padding: 0;  
        display: flex;  
        justify-content: center;  
        align-items: center;  
        min-height: auto;  
    }  

    .form-check-input {  
        margin: 0;  
        position: relative;  
    }  
    </style>  
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../../menu.php'); ?>
<div id="main" class="main-content">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <h3>Exportar Carga de Óbito</h3>
            <a href="../obito/index.php" class="btn btn-secondary">
                <i class="fa fa-file-export"></i> Indexador de Óbito
            </a>
        </div>
        <hr>

        <div class="filter-section">
            <form method="post" action="" id="filterForm">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="data_registro_inicio" class="form-label">Data de Registro (Início)</label>
                        <input type="date" id="data_registro_inicio" name="data_registro_inicio" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="data_registro_fim" class="form-label">Data de Registro (Fim)</label>
                        <input type="date" id="data_registro_fim" name="data_registro_fim" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nome_registrado" class="form-label">Nome do Falecido</label>
                        <input type="text" id="nome_registrado" name="nome_registrado" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-2 mb-3">
                        <label for="livro" class="form-label">Livro</label>
                        <input type="text" id="livro" name="livro" class="form-control">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="folha" class="form-label">Folha</label>
                        <input type="text" id="folha" name="folha" class="form-control">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="termo" class="form-label">Termo</label>
                        <input type="text" id="termo" name="termo" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="matricula" class="form-label">Matrícula</label>
                        <input type="text" id="matricula" name="matricula" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" name="search" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Pesquisar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-container">
            <form method="post" action="gerar_carga_obito.php" id="exportForm">
                <table id="tabelaResultados" class="table table-hover">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                            <th>Termo</th>
                            <th>Livro</th>
                            <th>Folha</th>
                            <th>Matrícula</th>
                            <th>Falecido</th>
                            <th>Data Óbito</th>
                            <th>Data Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
                            $where = ["status = 'A'"];
                            if (!empty($_POST['data_registro_inicio'])) $where[] = "data_registro >= '" . $conn->real_escape_string($_POST['data_registro_inicio']) . "'";
                            if (!empty($_POST['data_registro_fim'])) $where[] = "data_registro <= '" . $conn->real_escape_string($_POST['data_registro_fim']) . "'";
                            if (!empty($_POST['nome_registrado'])) $where[] = "nome_registrado LIKE '%" . $conn->real_escape_string($_POST['nome_registrado']) . "%'";
                            if (!empty($_POST['matricula'])) $where[] = "matricula LIKE '%" . $conn->real_escape_string($_POST['matricula']) . "%'";
                            if (!empty($_POST['livro'])) $where[] = "livro LIKE '%" . $conn->real_escape_string($_POST['livro']) . "%'";
                            if (!empty($_POST['folha'])) $where[] = "folha LIKE '%" . $conn->real_escape_string($_POST['folha']) . "%'";
                            if (!empty($_POST['termo'])) $where[] = "termo LIKE '%" . $conn->real_escape_string($_POST['termo']) . "%'";

                            $sql = "SELECT * FROM indexador_obito WHERE " . implode(" AND ", $where);
                            $result = $conn->query($sql);

                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td><input type='checkbox' name='selected_ids[]' value='{$row['id']}'></td>";
                                    echo "<td>{$row['termo']}</td>";
                                    echo "<td>{$row['livro']}</td>";
                                    echo "<td>{$row['folha']}</td>";
                                    echo "<td>{$row['matricula']}</td>";
                                    echo "<td>{$row['nome_registrado']}</td>";
                                    echo "<td>" . date('d/m/Y', strtotime($row['data_obito'])) . "</td>";
                                    echo "<td>" . date('d/m/Y', strtotime($row['data_registro'])) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>Nenhum registro encontrado</td></tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success w-100" name="exportar">
                        <i class="fas fa-download"></i> Exportar Carga
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleSelectAll(source) {
    document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = source.checked);
}
</script>
<?php include(__DIR__ . '/../../rodape.php'); ?>
</body>
</html>
