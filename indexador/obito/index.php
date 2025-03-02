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
    <title>Atlas - Pesquisa de Óbitos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css"/>
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css"/>
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .btn-group .btn {
            margin-right: 2px;
        }
        hr:not([size]) {
            height: 0px;
        }
        .btn-group .btn:last-child {
            margin-right: 0;
        }
        .table-container {
            margin-top: 20px;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .modal-content {
            border: none;
            border-radius: 16px;
            background: var(--surface-color);
        }
        .modal-header {
            padding: 1.5rem;
            background: var(--surface-color);
            border-bottom: 1px solid var(--border-color);
        }
        .modal-body {
            padding: 1.5rem;
            background: var(--surface-color);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
            background: var(--surface-secondary);
            border-radius: 12px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .info-item.full-width {
            grid-column: 1 / -1;
        }
        .info-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .info-value {
            font-size: 1rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .attachments-section {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem;
            min-height: 500px; 
            background: var(--surface-secondary);
            border-radius: 12px;
            overflow: hidden;
        }

        body.dark-mode .d-flex .align-items-center .align-items-center{
            color: #fff;
        }

        body.dark-mode .list-group-item{
            color: #fff;
        }

        .attachment-viewer {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        #pdf-iframe,
        #pdf-iframe-edit {
            width: 100%;
            height: 100%;
            border: none;
        }
        .attachments-list {
            background: var(--surface-color);
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
        }
        .attachment-viewer {
            background: var(--surface-tertiary);
        }

        :root {
            --surface-color: #ffffff;
            --surface-secondary: #f8f9fa;
            --surface-tertiary: #f1f3f5;
            --border-color: #e9ecef;
            --text-primary: #212529;
            --text-secondary: #6c757d;
        }
        body.dark-mode {
            --surface-color: #1a1d21;
            --surface-secondary: #2d3238;
            --surface-tertiary: #363b42;
            --border-color: #404650;
            --text-primary: #e9ecef;
            --text-secondary: #adb5bd;
        }
        .list-group-item {
            padding: 1rem;
            background: var(--surface-color);
            border: none;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        @media (max-width: 768px) {
            .attachments-section {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
            }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1rem;
            }
        }
        .cidade-input {
            background-color: #f8f9fa;
            cursor: pointer;
        }
        #resultadoCidades {
            max-height: 300px;
            overflow-y: auto;
        }
        .loading-spinner {
            display: none;
            margin-left: 10px;
        }
        .required-label::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        .drop-zone, .drop-zone-edit {
            max-width: 100%;
            height: 200px;
            padding: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 500;
            cursor: pointer;
            color: #777;
            border: 2px dashed #ddd;
            border-radius: 10px;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        .drop-zone:hover, .drop-zone-edit:hover {
            border-color: #0d6efd;
            color: #0d6efd;
            background-color: #f1f7ff;
        }
        .drop-zone.drop-zone--over, .drop-zone-edit.drop-zone--over {
            border-style: solid;
            background-color: #e9f2ff;
        }
        .drop-zone__input {
            display: none;
        }
        .drop-zone__files, .drop-zone-edit__files {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .drop-zone__file, .drop-zone-edit__file {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .drop-zone__file-icon, .drop-zone-edit__file-icon {
            color: #dc3545;
            margin-right: 8px;
        }
        .drop-zone__file-name, .drop-zone-edit__file-name {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .drop-zone__file-remove, .drop-zone-edit__file-remove {
            margin-left: 8px;
            color: #dc3545;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0 4px;
        }
        .drop-zone__prompt, .drop-zone-edit__prompt {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .drop-zone__prompt i, .drop-zone-edit__prompt i {
            font-size: 2.5rem;
            color: #0d6efd;
        }
        .modal-xxl {
            max-width: 70%;
            width: auto;
        }
        .modal-body .list-group-item {
            border: none;
            padding-left: 0;
        }
        .modal-content {
            border-radius: 12px;
        }
        .modal-header {
            background-color: #007bff;
            color: white;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        .modal-footer {
            border-top: none;
        }
    </style>
</head>
<body class="light-mode">

<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">

        <div class="d-flex flex-wrap justify-content-center align-items-center text-center mb-3">  
                <div class="col-md-auto mb-2">
                    <button type="button" class="btn btn-success text-white" data-bs-toggle="modal" data-bs-target="#cadastroObitoModal">
                        <i class="fa fa-plus" aria-hidden="true"></i> Novo Registro de Óbito
                    </button>
                </div>
                <div class="col-md-auto mb-2">
                    <a href="../index.php" class="btn btn-secondary text-white">
                        <i class="fa fa-home"></i> Central de Acesso
                    </a>
                </div>
                <div class="col-md-auto mb-2">
                    <a href="#" class="btn btn-info2 text-white">
                        <i class="fa fa-file-export"></i> Exportar carga CRC
                    </a>
                </div>
        </div> 
        <hr>
        
            <div class="d-flex justify-content-center align-items-center text-center mb-3">
                <h3>Indexador de Óbito</h3>
            </div>
            <hr>

        <!-- Filtro de pesquisa -->
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
                        <label for="data_obito_inicio" class="form-label">Data do Óbito (Início)</label>
                        <input type="date" id="data_obito_inicio" name="data_obito_inicio" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="data_obito_fim" class="form-label">Data do Óbito (Fim)</label>
                        <input type="date" id="data_obito_fim" name="data_obito_fim" class="form-control">
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
                    <div class="col-md-3 mb-3">
                        <label for="nome_registrado" class="form-label">Nome do Falecido</label>
                        <input type="text" id="nome_registrado" name="nome_registrado" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="matricula" class="form-label">Matrícula</label>
                        <input type="text" id="matricula" name="matricula" class="form-control">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label for="folha" class="form-label">Folha</label>
                        <input type="text" id="folha" name="folha" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nome_pai" class="form-label">Nome do Pai</label>
                        <input type="text" id="nome_pai" name="nome_pai" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nome_mae" class="form-label">Nome da Mãe</label>
                        <input type="text" id="nome_mae" name="nome_mae" class="form-control">
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

        <!-- Tabela de resultados -->
        <div class="table-responsive">
            <table id="tabelaResultados" class="table table-striped table-bordered" style="width: 100%; zoom: 90%">
                <thead>
                    <tr>
                        <th>Termo</th>
                        <th>Livro</th>
                        <th>Folha</th>
                        <th>Matrícula</th>
                        <th>Nome do Falecido</th>
                        <th>Data do Óbito</th>
                        <th>Data de Registro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
                        $where = ["status = 'A'"]; // Registros ativos

                        if (!empty($_POST['data_registro_inicio']))
                            $where[] = "data_registro >= '" . $conn->real_escape_string($_POST['data_registro_inicio']) . "'";
                        if (!empty($_POST['data_registro_fim']))
                            $where[] = "data_registro <= '" . $conn->real_escape_string($_POST['data_registro_fim']) . "'";
                        if (!empty($_POST['data_obito_inicio']))
                            $where[] = "data_obito >= '" . $conn->real_escape_string($_POST['data_obito_inicio']) . "'";
                        if (!empty($_POST['data_obito_fim']))
                            $where[] = "data_obito <= '" . $conn->real_escape_string($_POST['data_obito_fim']) . "'";
                        if (!empty($_POST['nome_registrado']))
                            $where[] = "nome_registrado LIKE '%" . $conn->real_escape_string($_POST['nome_registrado']) . "%'";
                        if (!empty($_POST['nome_pai']))
                            $where[] = "nome_pai LIKE '%" . $conn->real_escape_string($_POST['nome_pai']) . "%'";
                        if (!empty($_POST['nome_mae']))
                            $where[] = "nome_mae LIKE '%" . $conn->real_escape_string($_POST['nome_mae']) . "%'";
                        if (!empty($_POST['termo']))
                            $where[] = "termo LIKE '%" . $conn->real_escape_string($_POST['termo']) . "%'";
                        if (!empty($_POST['livro']))
                            $where[] = "livro LIKE '%" . $conn->real_escape_string($_POST['livro']) . "%'";
                        if (!empty($_POST['folha']))
                            $where[] = "folha LIKE '%" . $conn->real_escape_string($_POST['folha']) . "%'";
                        if (!empty($_POST['matricula']))
                            $where[] = "matricula LIKE '%" . $conn->real_escape_string($_POST['matricula']) . "%'";

                        $whereSQL = 'WHERE ' . implode(' AND ', $where);
                        $query = "SELECT * FROM indexador_obito $whereSQL ORDER BY data_registro DESC";
                        $result = $conn->query($query);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['termo']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['livro']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['folha']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['matricula'] ?? '') . '</td>';
                                echo '<td>' . htmlspecialchars($row['nome_registrado']) . '</td>';
                                echo '<td>' . date('d/m/Y', strtotime($row['data_obito'])) . '</td>';
                                echo '<td>' . date('d/m/Y', strtotime($row['data_registro'])) . '</td>';
                                echo '<td>
                                        <button type="button" class="btn btn-sm btn-info" onclick="visualizarRegistro(' . $row['id'] . ')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-edit btn-sm" onclick="editarRegistro(this)" data-id="' . $row['id'] . '">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                      </td>';
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
    </div>
</div>

<!-- MODAL DE VISUALIZAÇÃO DE REGISTRO -->
<div class="modal fade" id="viewRegistryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cadastroObitoModalLabel">
                    <i class="fa fa-eye"></i> Visualização do Registro
                </h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">
                    &times;
                </button>
            </div>

            <div class="modal-body">
                <!-- Dados do Registro -->
                <div class="registry-info mb-4">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Livro</span>
                            <span id="view-livro" class="info-value"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Folha</span>
                            <span id="view-folha" class="info-value"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Termo</span>
                            <span id="view-termo" class="info-value"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Data de Registro</span>
                            <span id="view-data-registro" class="info-value"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Matrícula</span>
                            <span id="view-matricula" class="info-value"></span>
                        </div>
                    </div>
                </div>

                <!-- Dados do Falecido -->
                <div class="person-info mb-4">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Nome do Falecido</span>
                            <span id="view-nome-registrado" class="info-value"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Data de Nascimento</span>
                            <span id="view-data-nascimento" class="info-value"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Data do Óbito</span>
                            <span id="view-data-obito" class="info-value"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Cidade do Óbito</span>
                            <span id="view-cidade-obito" class="info-value"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nome do Pai</span>
                            <span id="view-nome-pai" class="info-value"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nome da Mãe</span>
                            <span id="view-nome-mae" class="info-value"></span>
                        </div>
                    </div>
                </div>

                <!-- Anexos -->
                <div class="attachments-section">
                    <div class="attachments-list" id="anexos-list">
                        <!-- Lista de anexos será carregada aqui -->
                    </div>
                    <div class="attachment-viewer">
                        <iframe id="pdf-iframe" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE CADASTRO DE ÓBITO -->
<div class="modal fade" id="cadastroObitoModal" tabindex="-1" aria-labelledby="cadastroObitoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cadastroObitoModalLabel">
                    <i class="fa fa-eye"></i> Cadastro de Óbito
                </h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">
                    &times;
                </button>
            </div>
            <div class="modal-body p-4">
                <form id="formObito" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="livro" class="form-label required-label">Livro</label>
                            <input type="text" class="form-control rounded-pill" id="livro" name="livro" required>
                        </div>
                        <div class="col-md-4">
                            <label for="folha" class="form-label required-label">Folha</label>
                            <input type="text" class="form-control rounded-pill" id="folha" name="folha" required>
                        </div>
                        <div class="col-md-4">
                            <label for="termo" class="form-label required-label">Termo</label>
                            <input type="text" class="form-control rounded-pill" id="termo" name="termo" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-4">
                            <label for="data_registro" class="form-label required-label">Data do Registro</label>
                            <input type="date" class="form-control rounded-pill" id="data_registro" name="data_registro" required>
                        </div>
                        <div class="col-md-4">
                            <label for="data_obito" class="form-label required-label">Data do Óbito</label>
                            <input type="date" class="form-control rounded-pill" id="data_obito" name="data_obito" required>
                        </div>
                        <div class="col-md-4">
                            <label for="hora_obito" class="form-label required-label">Hora do Óbito</label>
                            <input type="time" class="form-control rounded-pill" id="hora_obito" name="hora_obito" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-8">
                            <label for="nome_registrado" class="form-label required-label">Nome do Registrado</label>
                            <input type="text" class="form-control rounded-pill" id="nome_registrado" name="nome_registrado" required>
                        </div>
                        <div class="col-md-4">
                            <label for="data_nascimento" class="form-label required-label">Data de Nascimento</label>
                            <input type="date" class="form-control rounded-pill" id="data_nascimento" name="data_nascimento" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label for="nome_pai" class="form-label">Nome do Pai</label>
                            <input type="text" class="form-control rounded-pill" id="nome_pai" name="nome_pai">
                        </div>
                        <div class="col-md-6">
                            <label for="nome_mae" class="form-label">Nome da Mãe</label>
                            <input type="text" class="form-control rounded-pill" id="nome_mae" name="nome_mae">
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label for="cidade_endereco" class="form-label required-label">Cidade do Endereço</label>
                            <input type="text" class="form-control rounded-pill cidade-input" id="cidade_endereco"
                                name="cidade_endereco" placeholder="Clique para pesquisar" readonly required>
                            <input type="hidden" id="ibge_cidade_endereco" name="ibge_cidade_endereco">
                        </div>
                        <div class="col-md-6">
                            <label for="cidade_obito" class="form-label required-label">Cidade do Óbito</label>
                            <input type="text" class="form-control rounded-pill cidade-input" id="cidade_obito"
                                name="cidade_obito" placeholder="Clique para pesquisar" readonly required>
                            <input type="hidden" id="ibge_cidade_obito" name="ibge_cidade_obito">
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-12">
                            <label for="anexos" class="form-label">Anexos (PDF)</label>
                            <!-- Drop zone do cadastro -->
                            <div class="drop-zone border border-dashed rounded-3 p-3 text-center">
                                <span class="drop-zone__prompt text-muted">
                                    <i class="fas fa-file-upload fa-2x text-primary"></i>
                                    <p>Arraste arquivos PDF aqui ou clique para selecionar</p>
                                </span>
                                <input type="file" name="anexos[]" class="drop-zone__input" id="anexos" multiple accept=".pdf">
                            </div>
                            <div class="drop-zone__files mt-2"></div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-success px-4 py-2 rounded-pill">Salvar</button>
                            <button type="reset" class="btn btn-secondary px-4 py-2 rounded-pill">Limpar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE EDIÇÃO DE ÓBITO -->
<div class="modal fade" id="editarObitoModal" tabindex="-1" aria-labelledby="editarObitoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editarObitoModalLabel">
                    <i class="fa fa-edit"></i> Editar Registro de Óbito
                </h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">
                    &times;
                </button>
            </div>
            <div class="modal-body p-4">
                <form id="formEditObito" method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="edit-id" name="id">

                    <!-- Primeira linha de campos -->
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="edit-livro" class="form-label required-label">Livro</label>
                            <input type="text" class="form-control rounded-pill" id="edit-livro" name="livro" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-folha" class="form-label required-label">Folha</label>
                            <input type="text" class="form-control rounded-pill" id="edit-folha" name="folha" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-termo" class="form-label required-label">Termo</label>
                            <input type="text" class="form-control rounded-pill" id="edit-termo" name="termo" required>
                        </div>
                    </div>

                    <!-- Segunda linha de campos -->
                    <div class="row g-3 mt-3">
                        <div class="col-md-4">
                            <label for="edit-data_registro" class="form-label required-label">Data do Registro</label>
                            <input type="date" class="form-control rounded-pill" id="edit-data_registro" name="data_registro" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-data_obito" class="form-label required-label">Data do Óbito</label>
                            <input type="date" class="form-control rounded-pill" id="edit-data_obito" name="data_obito" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-hora_obito" class="form-label required-label">Hora do Óbito</label>
                            <input type="time" class="form-control rounded-pill" id="edit-hora_obito" name="hora_obito" required>
                        </div>
                    </div>

                    <!-- Terceira linha de campos -->
                    <div class="row g-3 mt-3">
                        <div class="col-md-8">
                            <label for="edit-nome_registrado" class="form-label required-label">Nome do Registrado</label>
                            <input type="text" class="form-control rounded-pill" id="edit-nome_registrado" name="nome_registrado" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-data_nascimento" class="form-label required-label">Data de Nascimento</label>
                            <input type="date" class="form-control rounded-pill" id="edit-data_nascimento" name="data_nascimento" required>
                        </div>
                    </div>

                    <!-- Quarta linha de campos -->
                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label for="edit-nome_pai" class="form-label">Nome do Pai</label>
                            <input type="text" class="form-control rounded-pill" id="edit-nome_pai" name="nome_pai">
                        </div>
                        <div class="col-md-6">
                            <label for="edit-nome_mae" class="form-label">Nome da Mãe</label>
                            <input type="text" class="form-control rounded-pill" id="edit-nome_mae" name="nome_mae">
                        </div>
                    </div>

                    <!-- Quinta linha de campos -->
                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label for="edit-cidade_endereco" class="form-label required-label">Cidade do Endereço</label>
                            <input type="text" class="form-control rounded-pill cidade-input" id="edit-cidade_endereco" name="cidade_endereco" readonly required>
                            <input type="hidden" id="edit-ibge_cidade_endereco" name="ibge_cidade_endereco">
                        </div>
                        <div class="col-md-6">
                            <label for="edit-cidade_obito" class="form-label required-label">Cidade do Óbito</label>
                            <input type="text" class="form-control rounded-pill cidade-input" id="edit-cidade_obito" name="cidade_obito" readonly required>
                            <input type="hidden" id="edit-ibge_cidade_obito" name="ibge_cidade_obito">
                        </div>
                    </div>

                    <!-- Seção de Anexos (Existentes + Visualização) -->
                    <div class="row g-3 mt-3">
                        <div class="col-md-12">
                            <label for="edit-anexos" class="form-label">Anexos do Registro</label>
                            <div class="attachments-section">
                                <!-- Lista de anexos existentes -->
                                <div class="attachments-list" id="edit-anexos-list">
                                    <!-- Carregado via JS -->
                                </div>
                                <!-- Visualização do anexo escolhido -->
                                <div class="attachment-viewer">
                                    <iframe id="pdf-iframe-edit" allowfullscreen></iframe>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload de Novos Anexos (Edição) -->
                    <div class="row g-3 mt-3">
                        <div class="col-md-12">
                            <label for="edit-anexos" class="form-label">Adicionar Novos Anexos (PDF)</label>
                            <!-- Drop Zone da Edição -->
                            <div class="drop-zone-edit border border-dashed rounded-3 p-3 text-center">
                                <span class="drop-zone-edit__prompt text-muted">
                                    <i class="fas fa-file-upload fa-2x text-primary"></i>
                                    <p>Arraste novos arquivos PDF aqui ou clique para selecionar</p>
                                </span>
                                <input type="file" name="anexos[]" class="drop-zone__input" id="edit-anexos" multiple accept=".pdf">
                            </div>
                            <div class="drop-zone-edit__files mt-2" id="edit-dropzone-files"></div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill">Salvar Alterações</button>
                            <button type="button" class="btn btn-danger px-4 py-2 rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- MODAL DE PESQUISA DE CIDADE -->
<div class="modal fade" id="cidadeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pesquisar Cidade</h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="cidade_pesquisa"
                        placeholder="Digite o nome da cidade ou UF (mínimo 3 letras)">
                    <div class="loading-spinner">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    </div>
                </div>
                <div id="resultadoCidades" class="list-group">
                    <!-- Resultados serão inseridos aqui -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../../rodape.php'); ?>

<!-- JS e bibliotecas -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.27/dist/sweetalert2.all.min.js"></script>

<script>
// ======================== DATA TABLE ========================
$(document).ready(function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        try {
            $('#tabelaResultados').DataTable({
                language: {
                    url: "../../style/Portuguese-Brasil.json"
                },
                order: [[6, "desc"]],
                pageLength: 25,
                responsive: true,
                initComplete: function() {
                    console.log('DataTable inicializado com sucesso');
                }
            });
        } catch (error) {
            console.error('Erro ao inicializar DataTable:', error);
        }
    } else {
        console.error('DataTables não está disponível');
    }
});

// ======================== VISUALIZAÇÃO DE REGISTRO ========================
function visualizarRegistro(id) {
    Swal.fire({
        title: 'Carregando...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    function carregarAnexos(idObito) {
        $.ajax({
            url: 'buscar_anexos.php',
            type: 'GET',
            data: { id_obito: idObito },
            dataType: 'json',
            success: function(response) {
                const anexosList = $('#anexos-list');
                anexosList.empty();

                if (response.success && response.anexos && response.anexos.length > 0) {
                    response.anexos.forEach(anexo => {
                        if (anexo && anexo.id && anexo.nome_arquivo) {
                            anexosList.append(`
                                <div class="list-group-item" role="button" data-src="${anexo.nome_arquivo}">
                                    <div class="d-flex align-items-center" style="margin-left: 10px">
                                        <i class="fas fa-file-pdf text-success me-2"></i>
                                        <span class="text-truncate" style="max-width: 200px;">
                                            ${anexo.nome_arquivo.split('/').pop()}
                                        </span>
                                    </div>
                                </div>
                            `);
                        }
                    });

                    if (response.anexos[0]) {
                        $('#pdf-iframe').attr('src', response.anexos[0].nome_arquivo);
                        anexosList.find('.list-group-item:first').addClass('active');
                    }
                } else {
                    anexosList.append(`
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-file-alt fa-2x mb-2"></i>
                            <p class="mb-0">Nenhum anexo disponível</p>
                        </div>
                    `);
                    $('#pdf-iframe').attr('src', '');
                }
            },
            error: function() {
                $('#anexos-list').html(`
                    <div class="alert alert-warning m-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Não foi possível carregar os anexos
                    </div>
                `);
            }
        });
    }

    $(document).on('click', '.list-group-item', function(e) {
        e.preventDefault();
        $('.list-group-item').removeClass('active');
        $(this).addClass('active');
        const src = $(this).data('src');
        $('#pdf-iframe').attr('src', src);
    });

    $.ajax({
        url: 'buscar_obito.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (!response.success) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: response.error || 'Erro ao carregar os dados do registro.'
                });
                return;
            }
            const data = response.data;
            $('#view-livro').text(data.livro || '-');
            $('#view-folha').text(data.folha || '-');
            $('#view-termo').text(data.termo || '-');
            $('#view-data-registro').text(data.data_registro || '-');
            $('#view-matricula').text(data.matricula || '-');
            $('#view-nome-registrado').text(data.nome_registrado || '-');
            $('#view-data-obito').text(data.data_obito || '-');
            $('#view-data-nascimento').text(data.data_nascimento || '-');
            $('#view-cidade-obito').text(data.cidade_obito || '-');
            $('#view-nome-pai').text(data.nome_pai || '-');
            $('#view-nome-mae').text(data.nome_mae || '-');

            const modal = new bootstrap.Modal(document.getElementById('viewRegistryModal'));
            modal.show();
            carregarAnexos(id);
        },
        error: function() {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Não foi possível carregar os dados do registro.'
            });
        }
    });
}

$('#viewRegistryModal').on('hidden.bs.modal', function () {
    $('#viewRegistryModal input').val('');
    $('#view-attachments-container').empty();
});

// ======================== MODAL DE CIDADES E CADASTRO ========================
$(document).ready(function() {
    let cidadeModal = new bootstrap.Modal(document.getElementById('cidadeModal'));
    let campoAtual = null;
    let timeoutId = null;
    let todasCidades = [];

    function carregarCidades() {
        if (todasCidades.length === 0) {
            $('.loading-spinner').show();
            $.get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios', {
                orderBy: "nome"
            })
            .done(function(cidades) {
                todasCidades = cidades;
                $('.loading-spinner').hide();
            })
            .fail(function() {
                $('.loading-spinner').hide();
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro ao carregar lista de cidades.'
                });
            });
        }
    }
    function removerAcentos(texto) {
        return texto.normalize('NFD').replace(/[̀-\u036f]/g, '').toLowerCase();
    }
    function filtrarCidades(termo) {
        if (termo.length < 3) {
            $('#resultadoCidades').html(`
                <div class="list-group-item text-muted">
                    Digite pelo menos 3 caracteres para pesquisar
                </div>
            `);
            return;
        }
        const termoPesquisa = removerAcentos(termo);
        const resultados = todasCidades.filter(cidade => {
            const nomeSemAcento = removerAcentos(cidade.nome);
            const ufSemAcento = removerAcentos(cidade.microrregiao.mesorregiao.UF.sigla);
            return nomeSemAcento.includes(termoPesquisa) || ufSemAcento.includes(termoPesquisa);
        }).slice(0, 100);

        $('#resultadoCidades').empty();
        if (resultados.length > 0) {
            resultados.forEach(cidade => {
                $('#resultadoCidades').append(`
                    <a href="#" class="list-group-item list-group-item-action cidade-item"
                       data-nome="${cidade.nome}/${cidade.microrregiao.mesorregiao.UF.sigla}"
                       data-ibge="${cidade.id}">
                       ${cidade.nome}/${cidade.microrregiao.mesorregiao.UF.sigla}
                    </a>
                `);
            });
        } else {
            $('#resultadoCidades').append(`
                <div class="list-group-item text-muted">
                    Nenhuma cidade encontrada
                </div>
            `);
        }
    }

    $('.cidade-input').click(function() {
        campoAtual = $(this).attr('id');
        $('#cidade_pesquisa').val('');
        $('#resultadoCidades').empty();
        cidadeModal.show();
        carregarCidades();
    });

    $('#cidade_pesquisa').on('input', function() {
        const termo = $(this).val();
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        timeoutId = setTimeout(() => {
            filtrarCidades(termo);
        }, 300);
    });

    $(document).on('click', '.cidade-item', function(e) {
        e.preventDefault();
        const nome = $(this).data('nome');
        const ibge = $(this).data('ibge');

        if (campoAtual === 'cidade_endereco') {
            $('#cidade_endereco').val(nome);
            $('#ibge_cidade_endereco').val(ibge);
        } else if (campoAtual === 'cidade_obito') {
            $('#cidade_obito').val(nome);
            $('#ibge_cidade_obito').val(ibge);
        } else if (campoAtual === 'edit-cidade_endereco') {
            $('#edit-cidade_endereco').val(nome);
            $('#edit-ibge_cidade_endereco').val(ibge);
        } else if (campoAtual === 'edit-cidade_obito') {
            $('#edit-cidade_obito').val(nome);
            $('#edit-ibge_cidade_obito').val(ibge);
        }

        cidadeModal.hide();
    });

    // =========== SUBMISSÃO FORM CADASTRO ===========
    $('#formObito').on('submit', function(e) {
        e.preventDefault();
        if (!$('#ibge_cidade_endereco').val() || !$('#ibge_cidade_obito').val()) {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Por favor, selecione as cidades através da pesquisa.'
            });
            return;
        }
        let formData = new FormData(this);

        $.ajax({
            url: 'salvar_obito.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                let data;
                try {
                    data = JSON.parse(response);
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Resposta inesperada do servidor.'
                    });
                    return;
                }
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        $('#formObito')[0].reset();
                        $('#ibge_cidade_endereco').val('');
                        $('#ibge_cidade_obito').val('');
                        let modal = bootstrap.Modal.getInstance(document.getElementById('cadastroObitoModal'));
                        modal.hide(); 
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: data.message
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Ocorreu um erro ao processar a requisição.'
                });
            }
        });
    });
});

// ======= DROP ZONE DO CADASTRO =======
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Configuração da Drop Zone do cadastro
const dropZoneCadastro = document.querySelector('.drop-zone');
const inputCadastro = dropZoneCadastro.querySelector('.drop-zone__input');
const fileListCadastro = document.querySelector('.drop-zone__files');

// Ao clicar
dropZoneCadastro.addEventListener('click', () => inputCadastro.click());

// Ao selecionar arquivos
inputCadastro.addEventListener('change', () => {
    if (inputCadastro.files.length) {
        updateFileListCadastro(inputCadastro.files);
    }
});

// Arrastar e soltar
dropZoneCadastro.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZoneCadastro.classList.add('drop-zone--over');
});
['dragleave', 'dragend'].forEach(type => {
    dropZoneCadastro.addEventListener(type, () => {
        dropZoneCadastro.classList.remove('drop-zone--over');
    });
});
dropZoneCadastro.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZoneCadastro.classList.remove('drop-zone--over');
    if (e.dataTransfer.files.length) {
        inputCadastro.files = e.dataTransfer.files;
        updateFileListCadastro(inputCadastro.files);
    }
});

function updateFileListCadastro(files) {
    fileListCadastro.innerHTML = "";
    Array.from(files).forEach((file, index) => {
        const fileElement = document.createElement('div');
        fileElement.classList.add('drop-zone__file');
        fileElement.innerHTML = `
            <i class="fas fa-file-pdf drop-zone__file-icon"></i>
            <span class="drop-zone__file-name">${file.name}</span>
            <span class="text-muted ms-2">(${formatFileSize(file.size)})</span>
            <button type="button" class="drop-zone__file-remove" data-index="${index}">
                <i class="fas fa-times"></i>
            </button>
        `;
        fileListCadastro.appendChild(fileElement);
    });
}

// Remover arquivo da lista (cadastro)
document.addEventListener('click', (e) => {
    if (e.target.closest('.drop-zone__file-remove')) {
        const index = e.target.closest('.drop-zone__file-remove').dataset.index;
        const dt = new DataTransfer();
        const { files } = inputCadastro;

        for (let i = 0; i < files.length; i++) {
            if (i !== parseInt(index)) {
                dt.items.add(files[i]);
            }
        }
        inputCadastro.files = dt.files;
        updateFileListCadastro(inputCadastro.files);
    }
});

// ================= DROP ZONE DO MODAL EDIÇÃO =================
const dropZoneEdicao = document.querySelector('.drop-zone-edit');
const inputEdicao = dropZoneEdicao.querySelector('.drop-zone__input');
const fileListEdicao = document.querySelector('#edit-dropzone-files');

// Ao clicar
dropZoneEdicao.addEventListener('click', () => inputEdicao.click());

// Ao selecionar arquivos
inputEdicao.addEventListener('change', () => {
    if (inputEdicao.files.length) {
        updateFileListEdicao(inputEdicao.files);
    }
});

// Arrastar e soltar - Edição
dropZoneEdicao.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZoneEdicao.classList.add('drop-zone--over');
});
['dragleave', 'dragend'].forEach(type => {
    dropZoneEdicao.addEventListener(type, () => {
        dropZoneEdicao.classList.remove('drop-zone--over');
    });
});
dropZoneEdicao.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZoneEdicao.classList.remove('drop-zone--over');
    if (e.dataTransfer.files.length) {
        inputEdicao.files = e.dataTransfer.files;
        updateFileListEdicao(inputEdicao.files);
    }
});

function updateFileListEdicao(files) {
    fileListEdicao.innerHTML = "";
    Array.from(files).forEach((file, index) => {
        const fileElement = document.createElement('div');
        fileElement.classList.add('drop-zone__file');
        fileElement.innerHTML = `
            <i class="fas fa-file-pdf drop-zone__file-icon"></i>
            <span class="drop-zone__file-name">${file.name}</span>
            <span class="text-muted ms-2">(${formatFileSize(file.size)})</span>
            <button type="button" class="drop-zone__file-remove" data-index="${index}">
                <i class="fas fa-times"></i>
            </button>
        `;
        fileListEdicao.appendChild(fileElement);
    });
}

// Remover arquivo da lista (edição)
document.addEventListener('click', (e) => {
    if (e.target.closest('.drop-zone__file-remove') && fileListEdicao.contains(e.target.closest('.drop-zone__file-remove'))) {
        const index = e.target.closest('.drop-zone__file-remove').dataset.index;
        const dt = new DataTransfer();
        const { files } = inputEdicao;

        for (let i = 0; i < files.length; i++) {
            if (i !== parseInt(index)) {
                dt.items.add(files[i]);
            }
        }
        inputEdicao.files = dt.files;
        updateFileListEdicao(inputEdicao.files);
    }
});

// Eventos do Modal de Cidades
document.addEventListener("DOMContentLoaded", function () {
    let cidadeModal = document.getElementById("cidadeModal");
    let cadastroModal = document.getElementById("cadastroObitoModal");
    let editarObitoModal = document.getElementById("editarObitoModal");

    cidadeModal.addEventListener("hidden.bs.modal", function () {
        if (cadastroModal.classList.contains("show")) {
            cadastroModal.style.overflow = "auto";
        }
        if (editarObitoModal.classList.contains("show")) {
            editarObitoModal.style.overflow = "auto";
        }
    });

    cidadeModal.addEventListener("shown.bs.modal", function () {
        if (cadastroModal.classList.contains("show")) {
            cadastroModal.style.overflow = "hidden";
        }
        if (editarObitoModal.classList.contains("show")) {
            editarObitoModal.style.overflow = "hidden";
        }
    });
});

// Reseta o modal de Cadastro ao abrir
document.addEventListener("DOMContentLoaded", function () {
    let cadastroModal = document.getElementById("cadastroObitoModal");
    cadastroModal.addEventListener("show.bs.modal", function () {
        document.getElementById("formObito").reset();
        let inputAnexo = document.getElementById("anexos");
        if (inputAnexo) {
            inputAnexo.value = "";
        }
        let fileList = document.querySelector(".drop-zone__files");
        if (fileList) {
            fileList.innerHTML = "";
        }
    });
});

// ============= EDIÇÃO DE REGISTRO ==============
function editarRegistro(button) {
    let id = $(button).data("id");
    Swal.fire({
        title: "Carregando...",
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    $.ajax({
        url: "buscar_obito_edit.php",
        type: "GET",
        data: { id: id },
        dataType: "json",
        success: function(response) {
            Swal.close();
            if (!response.success) {
                Swal.fire({
                    icon: "error",
                    title: "Erro!",
                    text: response.error || "Erro ao carregar os dados do registro."
                });
                return;
            }
            const data = response.data;
            $("#edit-id").val(data.id);
            $("#edit-livro").val(data.livro);
            $("#edit-folha").val(data.folha);
            $("#edit-termo").val(data.termo);
            $("#edit-data_registro").val(data.data_registro);
            $("#edit-data_obito").val(data.data_obito);
            $("#edit-hora_obito").val(data.hora_obito);
            $("#edit-nome_registrado").val(data.nome_registrado);
            $("#edit-data_nascimento").val(data.data_nascimento);
            $("#edit-nome_pai").val(data.nome_pai);
            $("#edit-nome_mae").val(data.nome_mae);
            $("#edit-cidade_endereco").val(data.cidade_endereco);
            $("#edit-ibge_cidade_endereco").val(data.ibge_cidade_endereco);
            $("#edit-cidade_obito").val(data.cidade_obito);
            $("#edit-ibge_cidade_obito").val(data.ibge_cidade_obito);

            carregarAnexos(id);

            let modal = new bootstrap.Modal(document.getElementById("editarObitoModal"));
            modal.show();
        },
        error: function() {
            Swal.close();
            Swal.fire({
                icon: "error",
                title: "Erro!",
                text: "Não foi possível carregar os dados do registro."
            });
        }
    });
}

// Carrega anexos existentes no modal de edição
function carregarAnexos(id) {
    $.ajax({
        url: "buscar_anexos.php",
        type: "GET",
        data: { id_obito: id },
        dataType: "json",
        success: function(response) {
            const anexosList = $("#edit-anexos-list");
            const pdfIframe = $("#pdf-iframe-edit");
            anexosList.empty();

            if (response.success && response.anexos && response.anexos.length > 0) {
                response.anexos.forEach(anexo => {
                    anexosList.append(`
                        <div class="list-group-item d-flex justify-content-between align-items-center" data-src="${anexo.nome_arquivo}">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-pdf text-success me-2" style="margin-left: 10px;"></i>
                                <span class="text-truncate" style="max-width: 200px;">
                                    ${anexo.nome_arquivo.split('/').pop()}
                                </span>
                            </div>
                            <button type="button" class="btn btn-delete btn-sm" onclick="removerAnexo(${anexo.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `);
                });
                pdfIframe.attr("src", response.anexos[0].nome_arquivo);
                anexosList.find(".list-group-item:first").addClass("active");
            } else {
                anexosList.append(`
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-file-alt fa-2x mb-2"></i>
                        <p class="mb-0">Nenhum anexo disponível</p>
                    </div>
                `);
                pdfIframe.attr("src", "");
            }
        },
        error: function() {
            $("#edit-anexos-list").html('<div class="alert alert-warning">Erro ao carregar anexos.</div>');
        }
    });
}

// Clique p/ trocar de PDF no modal de edição
$(document).on("click", "#edit-anexos-list .list-group-item", function() {
    $("#edit-anexos-list .list-group-item").removeClass("active");
    $(this).addClass("active");
    const src = $(this).data("src");
    $("#pdf-iframe-edit").attr("src", src);
});

// Remover anexo
function removerAnexo(anexoId) {
    Swal.fire({
        title: "Tem certeza?",
        text: "Este anexo será removido permanentemente!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Sim, remover!"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "remover_anexo.php",
                type: "POST",
                data: { id: anexoId },
                success: function() {
                    Swal.fire("Removido!", "O anexo foi removido.", "success");
                    carregarAnexos($("#edit-id").val());
                },
                error: function() {
                    Swal.fire("Erro!", "Não foi possível remover o anexo.", "error");
                }
            });
        }
    });
}

// Limpar modal de edição ao fechar
$("#editarObitoModal").on("hidden.bs.modal", function () {
    $(this).find("form")[0].reset();
    $("#edit-anexos-list").empty();
    $("#pdf-iframe-edit").attr("src", "");
});

// Enviar formulário de edição
$("#formEditObito").on("submit", function (e) {
    e.preventDefault();
    let formData = new FormData(this);

    Swal.fire({
        title: "Salvando...",
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: "salvar_edicao.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            Swal.close();
            let data;
            try {
                data = JSON.parse(response);
            } catch (error) {
                Swal.fire({
                    icon: "error",
                    title: "Erro!",
                    text: "Resposta inesperada do servidor."
                });
                return;
            }
            if (data.status === "success") {
                Swal.fire({
                    icon: "success",
                    title: "Sucesso!",
                    text: data.message,
                    timer: 1500
                }).then(() => {
                    let editModal = bootstrap.Modal.getInstance(document.getElementById("editarObitoModal"));
                    editModal.hide();
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Erro!",
                    text: data.message
                });
            }
        },
        error: function () {
            Swal.close();
            Swal.fire({
                icon: "error",
                title: "Erro!",
                text: "Não foi possível salvar as alterações."
            });
        }
    });
});
</script>
</body>
</html>
