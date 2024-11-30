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
    <title>Atlas - Exportar Carga</title>
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../../style/css/dataTables.bootstrap4.min.css">    
    <script src="../../script/jquery-3.6.0.min.js"></script>
    <script src="../../script/jquery.dataTables.min.js"></script>
    <script src="../../script/dataTables.bootstrap4.min.js"></script>
    <style>
        .btn-close { outline: none; border: none; background: none; padding: 0; font-size: 1.5rem; cursor: pointer; transition: transform 0.2s ease; }
        .btn-close:hover { transform: scale(2.10); }
        .btn-close:focus { outline: none; }
        .modal-dialog { max-width: 80%; margin: 1.75rem auto; }
    </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Exportar Carga de Nascimento</h3>
        <hr>
        <form method="post" action="" id="filterForm">
            <div class="row mb-4">
                <div class="col-md-3">
                    <label for="data_registro_inicio" class="form-label">Data de Registro (Início):</label>
                    <input type="date" id="data_registro_inicio" name="data_registro_inicio" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="data_registro_fim" class="form-label">Data de Registro (Fim):</label>
                    <input type="date" id="data_registro_fim" name="data_registro_fim" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="data_cadastro_inicio" class="form-label">Data de Cadastro (Início):</label>
                    <input type="date" id="data_cadastro_inicio" name="data_cadastro_inicio" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="data_cadastro_fim" class="form-label">Data de Cadastro (Fim):</label>
                    <input type="date" id="data_cadastro_fim" name="data_cadastro_fim" class="form-control">
                </div>
                <div class="col-md-2 mt-3">
                    <label for="livro" class="form-label">Livro:</label>
                    <input type="text" id="livro" name="livro" class="form-control">
                </div>
                <div class="col-md-2 mt-3">
                    <label for="termo" class="form-label">Termo:</label>
                    <input type="text" id="termo" name="termo" class="form-control">
                </div>
                <div class="col-md-4 mt-3">
                    <label for="nome_registrado" class="form-label">Nome do Registrado:</label>
                    <input type="text" id="nome_registrado" name="nome_registrado" class="form-control">
                </div>
                <div class="col-md-4 mt-3">
                    <label for="matricula" class="form-label">Matrícula:</label>
                    <input type="text" id="matricula" name="matricula" class="form-control">
                </div>
                <div class="col-md-12 mt-4 text-center">
                    <button type="submit" name="search" class="btn btn-primary w-100" style="margin-top: -10px"><i class="fa fa-filter"></i> Pesquisar</button>
                </div>
            </div>
        </form>
        <hr>

        <div class="table-responsive">
            <h5>Resultados da Pesquisa</h5>
            <table id="tabelaResultados" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th><input type="checkbox" onclick="toggleSelectAll(this)"></th>
                        <th>Termo</th>
                        <th>Livro</th>
                        <th>Folha</th>
                        <th>Matrícula</th>
                        <th>Nome Registrado</th>
                        <th>Data de Nascimento</th>
                        <th>Data de Registro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
                        $where = [];

                        if (!empty($_POST['data_registro_inicio'])) $where[] = "data_registro >= '" . $_POST['data_registro_inicio'] . "'";
                        if (!empty($_POST['data_registro_fim'])) $where[] = "data_registro <= '" . $_POST['data_registro_fim'] . "'";
                        if (!empty($_POST['data_cadastro_inicio'])) $where[] = "data_cadastro >= '" . $_POST['data_cadastro_inicio'] . "'";
                        if (!empty($_POST['data_cadastro_fim'])) $where[] = "data_cadastro <= '" . $_POST['data_cadastro_fim'] . "'";
                        if (!empty($_POST['nome_registrado'])) $where[] = "nome_registrado LIKE '%" . $conn->real_escape_string($_POST['nome_registrado']) . "%'";
                        if (!empty($_POST['termo'])) $where[] = "termo LIKE '%" . $conn->real_escape_string($_POST['termo']) . "%'";
                        if (!empty($_POST['livro'])) $where[] = "livro LIKE '%" . $conn->real_escape_string($_POST['livro']) . "%'";
                        if (!empty($_POST['matricula'])) $where[] = "matricula LIKE '%" . $conn->real_escape_string($_POST['matricula']) . "%'";

                        $whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) . " AND status = 'ativo'" : "WHERE status = 'ativo'";
                        $query = "SELECT * FROM indexador_nascimento $whereSQL";
                        $result = $conn->query($query);

                        function formatarData($data) {
                            return $data ? date('d/m/Y', strtotime($data)) : '';
                        }

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo '<tr>';
                                echo '<td><input type="checkbox" name="selected_ids[]" value="' . $row['id'] . '"></td>';
                                echo '<td>' . htmlspecialchars($row['termo']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['livro']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['folha']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['matricula'] ?? '') . '</td>';
                                echo '<td>' . htmlspecialchars($row['nome_registrado']) . '</td>';
                                echo '<td>' . htmlspecialchars(formatarData($row['data_nascimento'])) . '</td>';
                                echo '<td>' . htmlspecialchars(formatarData($row['data_registro'])) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center text-danger">Nenhum registro encontrado.</td></tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#tabelaResultados').DataTable({
            "language": { "url": "../../style/Portuguese-Brasil.json" },
            "pageLength": 10
        });
    });

    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = source.checked);
    }
</script>
<?php
    include(__DIR__ . '/../../rodape.php');
    ?>
</body>
</html>
