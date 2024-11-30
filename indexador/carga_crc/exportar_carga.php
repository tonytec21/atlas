<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Carga</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script>
        // Função para selecionar ou desmarcar todos os checkboxes
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = source.checked);
        }
    </script>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Exportar Carga de Registros</h1>
        <form method="post" action="" id="filterForm">
            <div class="row mt-4">
                <div class="col-md-3 mt-3">
                    <label for="data_registro_inicio" class="form-label">Data de Registro (Início):</label>
                    <input type="date" id="data_registro_inicio" name="data_registro_inicio" class="form-control">
                </div>
                <div class="col-md-3 mt-3">
                    <label for="data_registro_fim" class="form-label">Data de Registro (Fim):</label>
                    <input type="date" id="data_registro_fim" name="data_registro_fim" class="form-control">
                </div>
                <div class="col-md-3 mt-3">
                    <label for="data_cadastro_inicio" class="form-label">Data de Cadastro (Início):</label>
                    <input type="date" id="data_cadastro_inicio" name="data_cadastro_inicio" class="form-control">
                </div>
                <div class="col-md-3 mt-3">
                    <label for="data_cadastro_fim" class="form-label">Data de Cadastro (Fim):</label>
                    <input type="date" id="data_cadastro_fim" name="data_cadastro_fim" class="form-control">
                </div>
                <div class="col-md-2 mt-3">
                    <label for="termo" class="form-label">Termo:</label>
                    <input type="text" id="termo" name="termo" class="form-control">
                </div>
                <div class="col-md-2 mt-3">
                    <label for="livro" class="form-label">Livro:</label>
                    <input type="text" id="livro" name="livro" class="form-control">
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
                    <button type="submit" name="search" class="btn btn-primary">Pesquisar</button>
                </div>
            </div>
        </form>

        <div class="mt-5">
            <?php
            require_once __DIR__ . '/db_connection.php';

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
                $where = [];
                if (!empty($_POST['data_registro_inicio'])) {
                    $where[] = "data_registro >= '" . $_POST['data_registro_inicio'] . "'";
                }
                if (!empty($_POST['data_registro_fim'])) {
                    $where[] = "data_registro <= '" . $_POST['data_registro_fim'] . "'";
                }
                if (!empty($_POST['data_cadastro_inicio'])) {
                    $where[] = "data_cadastro >= '" . $_POST['data_cadastro_inicio'] . "'";
                }
                if (!empty($_POST['data_cadastro_fim'])) {
                    $where[] = "data_cadastro <= '" . $_POST['data_cadastro_fim'] . "'";
                }
                if (!empty($_POST['nome_registrado'])) {
                    $where[] = "nome_registrado LIKE '%" . $conn->real_escape_string($_POST['nome_registrado']) . "%'";
                }
                if (!empty($_POST['termo'])) {
                    $where[] = "termo LIKE '%" . $conn->real_escape_string($_POST['termo']) . "%'";
                }
                if (!empty($_POST['livro'])) {
                    $where[] = "livro LIKE '%" . $conn->real_escape_string($_POST['livro']) . "%'";
                }
                if (!empty($_POST['matricula'])) {
                    $where[] = "matricula LIKE '%" . $conn->real_escape_string($_POST['matricula']) . "%'";
                }

                $whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                $query = "SELECT * FROM indexador_nascimento $whereSQL AND status = 'ativo'";
                $result = $conn->query($query);

                // Função para formatar a data no formato brasileiro (DD/MM/AAAA)
                function formatarData($data) {
                    return $data ? date('d/m/Y', strtotime($data)) : '';
                }

                if ($result->num_rows > 0) {
                    echo '<form method="post" action="gerar_carga.php">';
                    echo '<table class="table table-bordered">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th><input type="checkbox" onclick="toggleSelectAll(this)"></th>';
                    echo '<th>Termo</th>';
                    echo '<th>Livro</th>';
                    echo '<th>Folha</th>';
                    echo '<th>Matrícula</th>';
                    echo '<th>Nome Registrado</th>';
                    echo '<th>Data de Nascimento</th>';
                    echo '<th>Data de Registro</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
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
                    echo '</tbody></table>';
                    echo '<div class="text-center mt-4">';
                    echo '<button type="submit" class="btn btn-success">Exportar Selecionados</button>';
                    echo '</div>';
                    echo '</form>';
                } else {
                    echo '<p class="text-center text-danger">Nenhum registro encontrado.</p>';
                }
            }

            $conn->close();
            ?>
        </div>
    </div>
</body>
</html>
