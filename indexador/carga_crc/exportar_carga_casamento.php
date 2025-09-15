<?php  
// :contentReference[oaicite:0]{index=0}
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
    <title>Atlas - Exportar Carga (Casamento)</title>  
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
            vertical-align: middle !important;  
        }  

        .table td {  
            padding: 1rem;  
            vertical-align: middle;  
            border-top: 1px solid var(--border-color);  
        }  

        .table tbody tr:hover {  
            background-color: var(--hover-bg);  
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

        @media (max-width: 768px) {  
            .container { padding: 1rem; }  
            .btn { width: 100%; margin: 0.5rem 0; }  
            .table-responsive { margin: 0 -1rem; padding: 0 1rem; }  
        }  

        .loading-overlay {  
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;  
            background-color: rgba(0,0,0,0.5);  
            display: none; align-items: center; justify-content: center;  
            z-index: 9999;  
        }  
        .loading-spinner {  
            width: 50px; height: 50px; border: 5px solid var(--bg-primary);  
            border-top: 5px solid var(--accent-color); border-radius: 50%;  
            animation: spin 1s linear infinite;  
        }  
        @keyframes spin { 0%{transform:rotate(0)} 100%{transform:rotate(360deg)} }  
    </style>  
</head>  
<body class="light-mode">
<?php include(__DIR__ . '/../../menu.php'); ?>  

<div id="main" class="main-content">  
    <div class="container">  
        <div class="d-flex justify-content-between align-items-center">
            <h3>Exportar Carga de Casamento</h3>
            <a href="../casamento/index.php" class="btn btn-secondary">
                <i class="fa fa-ring" aria-hidden="true"></i> Indexador de Casamento
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
                    <div class="col-md-3 mb-3">  
                        <label for="data_casamento_inicio" class="form-label">Data do Casamento (Início)</label>  
                        <input type="date" id="data_casamento_inicio" name="data_casamento_inicio" class="form-control">  
                    </div>  
                    <div class="col-md-3 mb-3">  
                        <label for="data_casamento_fim" class="form-label">Data do Casamento (Fim)</label>  
                        <input type="date" id="data_casamento_fim" name="data_casamento_fim" class="form-control">  
                    </div>  
                </div>  
                <div class="row">  
                    <div class="col-md-2 mb-2">  
                        <label for="livro" class="form-label">Livro</label>  
                        <input type="text" id="livro" name="livro" class="form-control">  
                    </div>  
                    <div class="col-md-2 mb-2">  
                        <label for="termo" class="form-label">Termo</label>  
                        <input type="text" id="termo" name="termo" class="form-control">  
                    </div>  
                    <div class="col-md-4 mb-4">  
                        <label for="nome_conjuge" class="form-label">Nome do Cônjuge (1 ou 2)</label>  
                        <input type="text" id="nome_conjuge" name="nome_conjuge" class="form-control" placeholder="Ex.: MARIA SILVA">  
                    </div>  
                    <div class="col-md-4 mb-3">  
                        <label for="matricula" class="form-label">Matrícula</label>  
                        <input type="text" id="matricula" name="matricula" class="form-control">  
                    </div>  
                </div>  
                <div class="row">  
                    <div class="col-12">  
                        <button type="submit" name="search" class="btn btn-primary w-100">  
                            <i class="fas fa-search"></i>  
                            Pesquisar  
                        </button>  
                    </div>  
                </div>  
            </form>  
        </div>  

        <div class="table-container">  
            <form method="post" action="gerar_carga_casamento.php" id="exportForm">  
                <div class="table-responsive">
                <table id="tabelaResultados" class="table table-hover">  
                    <thead>  
                        <tr>  
                            <th class="checkbox-column">
                                <div class="form-check">  
                                    <input type="checkbox" class="form-check-input" id="selectAll" onclick="toggleSelectAll(this)">  
                                </div>  
                            </th>  
                            <th>Termo</th>  
                            <th>Livro</th>  
                            <th>Folha</th>  
                            <th>Matrícula</th>  
                            <th>Cônjuges</th>  
                            <th>Data do Casamento</th>  
                            <th>Data de Registro</th>  
                        </tr>  
                    </thead>  
                    <tbody>  
                        <?php  
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {  
                            $where = ["status = 'ativo'"];  

                            if (!empty($_POST['data_registro_inicio'])) $where[] = "data_registro >= '" . $conn->real_escape_string($_POST['data_registro_inicio']) . "'";  
                            if (!empty($_POST['data_registro_fim']))    $where[] = "data_registro <= '" . $conn->real_escape_string($_POST['data_registro_fim']) . "'";  
                            if (!empty($_POST['data_casamento_inicio'])) $where[] = "data_casamento >= '" . $conn->real_escape_string($_POST['data_casamento_inicio']) . "'";  
                            if (!empty($_POST['data_casamento_fim']))    $where[] = "data_casamento <= '" . $conn->real_escape_string($_POST['data_casamento_fim']) . "'";  
                            if (!empty($_POST['livro']))  $where[] = "livro LIKE '%"  . $conn->real_escape_string($_POST['livro'])  . "%'";  
                            if (!empty($_POST['termo']))  $where[] = "termo LIKE '%"  . $conn->real_escape_string($_POST['termo'])  . "%'";  
                            if (!empty($_POST['matricula'])) $where[] = "matricula LIKE '%" . $conn->real_escape_string($_POST['matricula']) . "%'";  
                            if (!empty($_POST['nome_conjuge'])) {  
                                $n = $conn->real_escape_string($_POST['nome_conjuge']);  
                                $where[] = "(conjuge1_nome LIKE '%$n%' OR conjuge2_nome LIKE '%$n%')";  
                            }  

                            $whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';  
                            $query = "SELECT id, termo, livro, folha, matricula, conjuge1_nome, conjuge1_sexo, conjuge2_nome, conjuge2_sexo, data_casamento, data_registro  
                                      FROM indexador_casamento $whereSQL  
                                      ORDER BY termo ASC";  
                            $result = $conn->query($query);  

                            if ($result && $result->num_rows > 0) {  
                                while ($row = $result->fetch_assoc()) {  
                                    $conjuges = htmlspecialchars($row['conjuge1_nome']) . ' (' . htmlspecialchars($row['conjuge1_sexo']) . ') & ' .  
                                                htmlspecialchars($row['conjuge2_nome']) . ' (' . htmlspecialchars($row['conjuge2_sexo']) . ')';  
                                    echo '<tr>';  
                                    echo '<td class="checkbox-column"><div class="form-check"><input type="checkbox" class="form-check-input" name="selected_ids[]" value="' . intval($row['id']) . '"></div></td>';  
                                    echo '<td>' . htmlspecialchars($row['termo']) . '</td>';  
                                    echo '<td>' . htmlspecialchars($row['livro']) . '</td>';  
                                    echo '<td>' . htmlspecialchars($row['folha']) . '</td>';  
                                    echo '<td>' . htmlspecialchars($row['matricula'] ?? '') . '</td>';  
                                    echo '<td>' . $conjuges . '</td>';  
                                    echo '<td>' . (!empty($row['data_casamento']) ? date('d/m/Y', strtotime($row['data_casamento'])) : '') . '</td>';  
                                    echo '<td>' . (!empty($row['data_registro']) ? date('d/m/Y', strtotime($row['data_registro'])) : '') . '</td>';  
                                    echo '</tr>';  
                                }  
                            } else {  
                                echo '<tr><td colspan="8" class="text-center">Nenhum registro encontrado</td></tr>';  
                            }  
                        }  
                        ?>  
                    </tbody>  
                </table>  
                </div>
                <div class="mt-3">  
                    <button type="submit" class="btn btn-success w-100" name="exportar">  
                        <i class="fas fa-download"></i>  
                        Exportar Carga (XML)  
                    </button>  
                </div>  
            </form>  
        </div>  
    </div>  
</div>  

<div class="loading-overlay">  
    <div class="loading-spinner"></div>  
</div>  

<script>  
$(document).ready(function() {  
    $('#tabelaResultados').DataTable({  
        "language": { "url": "../../style/Portuguese-Brasil.json" },  
        "pageLength": 10,  
        "responsive": true,  
        "order": [[1, 'asc']],  
        "columnDefs": [{ "orderable": false, "targets": 0 }]  
    });  

    $('#filterForm').on('submit', function() {  
        $('.loading-overlay').show();  
    });  

    $('#exportForm').on('submit', function(e) {  
        e.preventDefault();  
        const selectedItems = $('input[name="selected_ids[]"]:checked').length;  
        if (selectedItems === 0) {  
            alert('Por favor, selecione pelo menos um registro para exportar.');  
            return false;  
        }  
        $('.loading-overlay').show();  
        this.submit();  
    });  
});  

function toggleSelectAll(source) {  
    const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');  
    checkboxes.forEach(checkbox => checkbox.checked = source.checked);  
}  

function toggleTheme() {  
    document.body.classList.toggle('dark-mode');  
    localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');  
}  

document.addEventListener('DOMContentLoaded', () => {  
    const savedTheme = localStorage.getItem('theme');  
    if (savedTheme === 'dark') {  
        document.body.classList.add('dark-mode');  
    }  
});  
</script>  

<?php include(__DIR__ . '/../../rodape.php'); ?>  
</body>  
</html>
