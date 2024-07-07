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
    <title>Atlas - Pesquisa de Requerimentos</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font/css/materialdesignicons.min.css">
    <style>
        .status-label {
            display: inline-block;
            padding: 0.2em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 2;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25em;
            width: 100px;
        }
        .status-iniciada { background-color: #007bff; }
        .status-em-espera { background-color: #ffa500; }
        .status-em-andamento { background-color: #0056b3; }
        .status-concluida { background-color: #28a745; }
        .status-cancelada { background-color: #dc3545; }
        .status-pendente { background-color: #f4f4f4; color: #222; }
        .row-quase-vencida {
            background-color: #ffebcc;
        }
        .row-vencida {
            background-color: #ffcccc;
        }
        .row-quase-vencida.dark-mode {
            background-color: #ffebcc;
        }
        .row-vencida.dark-mode {
            background-color: #ffcccc;
        }

        /* Dark mode styles */
        body.dark-mode .timeline::before { background: #444; }
        body.dark-mode .timeline-item .timeline-panel { background: #333; border-color: #444; color: #ddd; }
        body.dark-mode .timeline-item .timeline-panel::before { border-left-color: #444; }
        body.dark-mode .timeline-item .timeline-panel::after { border-left-color: #333; }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Pesquisa de Requerimentos</h3>
        <hr>
        <form id="searchForm">
            <div class="form-row">
                <div class="form-group col-md-2">
                    <label for="requerente">Requerente:</label>
                    <input type="text" class="form-control" id="requerente" name="requerente">
                </div>
                <div class="form-group col-md-4">
                    <label for="qualificacao">Qualificação:</label>
                    <input type="text" class="form-control" id="qualificacao" name="qualificacao">
                </div>
                <div class="form-group col-md-4">
                    <label for="motivo">Motivo:</label>
                    <input type="text" class="form-control" id="motivo" name="motivo">
                </div>
                <div class="form-group col-md-2">
                    <label for="criado_por">Criado Por:</label>
                    <select id="criado_por" name="criado_por" class="form-control">
                        <option value="">Selecione</option>
                        <?php
                        $sql = "SELECT DISTINCT criado_por FROM requerimentos";
                        $result = $conn->query($sql);
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['criado_por'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['criado_por'], ENT_QUOTES, 'UTF-8') . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <button type="submit" style="width: 100%;" class="btn btn-primary"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar</button>
                </div>
                <div class="col-md-6 text-right">
                    <button id="add-button" type="button" style="width: 100%;" class="btn btn-success" onclick="window.location.href='cadastro.php'"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar</button>
                </div>
            </div>
        </form>
        <div class="mt-3">
            <table class="table" style="zoom: 85%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Requerente</th>
                        <th>Qualificação</th>
                        <th>Motivo</th>
                        <th>Petição</th>
                        <th>Criado Por</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="requerimentoTable">
                    <!-- Dados dos requerimentos serão inseridos aqui -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script>
    function openNav() {
        document.getElementById("mySidebar").style.width = "250px";
        document.getElementById("main").style.marginLeft = "250px";
    }

    function closeNav() {
        document.getElementById("mySidebar").style.width = "0";
        document.getElementById("main").style.marginLeft = "0";
    }

    function normalizeText(text) {
        return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    function formatDateTime(dateTime) {
        var date = new Date(dateTime);
        return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
    }

    $(document).ready(function() {
        // Carregar o modo do usuário
        $.ajax({
            url: '../load_mode.php',
            method: 'GET',
            success: function(mode) {
                $('body').removeClass('light-mode dark-mode').addClass(mode);
            }
        });

        // Função para alternar modos claro e escuro
        $('.mode-switch').on('click', function() {
            var body = $('body');
            body.toggleClass('dark-mode light-mode');

            var mode = body.hasClass('dark-mode') ? 'dark-mode' : 'light-mode';
            $.ajax({
                url: '../save_mode.php',
                method: 'POST',
                data: { mode: mode },
                success: function(response) {
                    console.log(response);
                }
            });
        });

        // Enviar formulário de pesquisa
        $('#searchForm').on('submit', function(e) {
            e.preventDefault();

            var formData = $(this).serialize();

            $.ajax({
                url: 'search_requerimentos.php',
                type: 'GET',
                data: formData,
                success: function(response) {
                    var requerimentos = JSON.parse(response);
                    var requerimentoTable = $('#requerimentoTable');
                    requerimentoTable.empty();
                    requerimentos.forEach(function(requerimento) {
                        var actions = '<button class="btn btn-info btn-sm" onclick="viewRequerimento(' + requerimento.id + ')"><i class="fa fa-eye" aria-hidden="true"></i></button> ';
                        actions += '<button class="btn btn-edit btn-sm" onclick="editRequerimento(' + requerimento.id + ')"><i class="fa fa-pencil" aria-hidden="true"></i></button> ';
                        actions += '<button class="btn btn-delete btn-sm" onclick="deleteRequerimento(' + requerimento.id + ')"><i class="fa fa-trash" aria-hidden="true"></i></button>';

                        var row = '<tr>' +
                            '<td>' + requerimento.id + '</td>' +
                            '<td>' + requerimento.requerente + '</td>' +
                            '<td>' + requerimento.qualificacao + '</td>' +
                            '<td>' + requerimento.motivo + '</td>' +
                            '<td>' + requerimento.peticao + '</td>' +
                            '<td>' + requerimento.criado_por + '</td>' +
                            '<td>' + new Date(requerimento.data).toLocaleDateString("pt-BR") + '</td>' +
                            '<td>' + actions + '</td>' +
                            '</tr>';
                        requerimentoTable.append(row);
                    });
                },
                error: function() {
                    alert('Erro ao buscar os requerimentos');
                }
            });
        });
    });

    function editRequerimento(requerimentoId) {
        window.location.href = 'edit_requerimento.php?id=' + requerimentoId;
    }

    function deleteRequerimento(requerimentoId) {
        if (confirm('Tem certeza que deseja excluir este requerimento?')) {
            $.ajax({
                url: 'delete_requerimento.php',
                type: 'POST',
                data: { id: requerimentoId },
                success: function(response) {
                    alert('Requerimento excluído com sucesso');
                    $('#searchForm').submit(); // Recarregar a lista de requerimentos
                },
                error: function() {
                    alert('Erro ao excluir o requerimento');
                }
            });
        }
    }
</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
