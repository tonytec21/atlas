<?php
include(__DIR__ . '/session_check.php');
checkSession();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oficios_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Verificar se há filtros aplicados
$filters = [];
$filterQuery = "";

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['numero']) || isset($_GET['data']) || isset($_GET['assunto']) || isset($_GET['destinatario']))) {
    if (!empty($_GET['numero'])) {
        $filters[] = "numero LIKE '%" . $conn->real_escape_string($_GET['numero']) . "%'";
    }
    if (!empty($_GET['data'])) {
        $filters[] = "data = '" . $conn->real_escape_string($_GET['data']) . "'";
    }
    if (!empty($_GET['assunto'])) {
        $filters[] = "assunto LIKE '%" . $conn->real_escape_string($_GET['assunto']) . "%'";
    }
    if (!empty($_GET['destinatario'])) {
        $filters[] = "destinatario LIKE '%" . $conn->real_escape_string($_GET['destinatario']) . "%'";
    }

    if (count($filters) > 0) {
        $filterQuery = "WHERE " . implode(" AND ", $filters);
    }
}

$sql = "SELECT * FROM oficios $filterQuery ORDER BY id DESC LIMIT 10";
$result = $conn->query($sql);

$oficios = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $oficios[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Pesquisa de Ofícios</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font/css/materialdesignicons.min.css">
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Pesquisa de Ofícios</h3>
            <hr>
            <form id="searchForm" method="GET">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="numero">Número:</label>
                        <input type="text" class="form-control" id="numero" name="numero">
                    </div>
                    <div class="col-md-3">
                        <label for="data">Data:</label>
                        <input type="date" class="form-control" id="data" name="data">
                    </div>
                    <div class="col-md-3">
                        <label for="assunto">Assunto:</label>
                        <input type="text" class="form-control" id="assunto" name="assunto">
                    </div>
                    <div class="col-md-3">
                        <label for="destinatario">Destinatário:</label>
                        <input type="text" class="form-control" id="destinatario" name="destinatario">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <button type="submit" style="width: 100%;" class="btn btn-primary"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar</button>
                    </div>
                    <div class="col-md-6 text-right">
                        <button id="add-button" type="button" style="width: 100%;" class="btn btn-success" onclick="window.location.href='cadastrar-oficio.php'"><i class="fa fa-plus" aria-hidden="true"></i> Criar Ofício</button>
                    </div>
                </div>
            </form>
            <div class="mt-3">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Data</th>
                            <th>Assunto</th>
                            <th>Destinatário</th>
                            <th>Cargo</th>
                            <th style="width: 10%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="oficioTable">
                        <?php foreach ($oficios as $oficio) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($oficio['numero']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($oficio['data'])); ?></td>
                                <td><?php echo htmlspecialchars($oficio['assunto']); ?></td>
                                <td><?php echo htmlspecialchars($oficio['destinatario']); ?></td>
                                <td><?php echo htmlspecialchars($oficio['cargo']); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="viewOficio('<?php echo $oficio['numero']; ?>')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <button class="btn btn-edit btn-sm" onclick="editOficio('<?php echo $oficio['numero']; ?>')" <?php if ($oficio['status'] == 1) echo 'disabled'; ?>><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                    <button class="btn btn-sm btn-primary" style="width: 40px; height: 40px;" onclick="viewAttachments('<?php echo $oficio['numero']; ?>')"><i class="fa fa-paperclip" aria-hidden="true"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar Ofício -->
    <div class="modal fade" id="viewOficioModal" tabindex="-1" role="dialog" aria-labelledby="viewOficioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewOficioModalLabel">Visualizar Ofício</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <iframe id="oficioPDF" src="" frameborder="0" style="width: 100%; height: 500px;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="lockButton">Travar Edição</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar Anexos -->
    <div class="modal fade" id="viewAttachmentsModal" tabindex="-1" role="dialog" aria-labelledby="viewAttachmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewAttachmentsModalLabel">Anexos do Ofício</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="oficioDetails">
                        <div class="form-row">
                            <div class="form-group col-md-2">
                                <label for="detNumero">Número:</label>
                                <input type="text" class="form-control" id="detNumero" disabled>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="detData">Data:</label>
                                <input type="text" class="form-control" id="detData" disabled>
                            </div>
                            <div class="form-group col-md-8">
                                <label for="detAssunto">Assunto:</label>
                                <input type="text" class="form-control" id="detAssunto" disabled>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="detDestinatario">Destinatário:</label>
                                <input type="text" class="form-control" id="detDestinatario" disabled>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="detCargo">Cargo:</label>
                                <input type="text" class="form-control" id="detCargo" disabled>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="detAssinante">Assinante:</label>
                                <input type="text" class="form-control" id="detAssinante" disabled>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="detCargoAssinante">Cargo do Assinante:</label>
                                <input type="text" class="form-control" id="detCargoAssinante" disabled>
                            </div>
                        </div>
                    </div>
                    <h4>Anexos</h4>
                    <div id="attachmentsContent"></div>
                    <h6>Anexar Novo Arquivo:</h6>
                    <form id="uploadForm" class="form-inline">
                        <input type="hidden" name="numero" id="numeroOficio">
                        <div class="form-group mb-2 mr-2">
                            <label for="file" class="sr-only"></label>
                            <input type="file" class="form-control" name="file" id="file" required>
                        </div>
                        <button type="submit" class="btn btn-success mb-2">Enviar</button>
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
    <script>
        $(document).ready(function() {
            
        });

        function viewOficio(numero) {
            var url = 'view_oficio.php?numero=' + numero;
            $('#oficioPDF').attr('src', url);
            $('#viewOficioModal').modal('show');
            $.ajax({
                url: 'get_status.php',
                type: 'GET',
                data: { numero: numero },
                success: function(response) {
                    var status = JSON.parse(response).status;
                    if (status == 1) {
                        $('#lockButton').hide();
                    } else {
                        $('#lockButton').show();
                    }
                },
                error: function() {
                    alert('Erro ao verificar status do ofício.');
                }
            });
            $('#lockButton').off('click').on('click', function() {
                lockOficio(numero);
            });
        }

        function viewAttachments(numero) {
            $.ajax({
                url: 'get_oficio_details.php',
                type: 'GET',
                data: { numero: numero },
                success: function(response) {
                    var data = JSON.parse(response);
                    $('#detNumero').val(data.numero);
                    $('#detData').val(formatDateToBrazilian(data.data));
                    $('#detAssunto').val(data.assunto);
                    $('#detDestinatario').val(data.destinatario);
                    $('#detCargo').val(data.cargo);
                    $('#detAssinante').val(data.assinante);
                    $('#detCargoAssinante').val(data.cargo_assinante);
                    $('#numeroOficio').val(data.numero);
                    loadAttachments(data.numero);
                    $('#viewAttachmentsModal').modal('show');
                },
                error: function() {
                    alert('Erro ao obter detalhes do ofício.');
                }
            });
        }

        function formatDateToBrazilian(date) {
            var dateParts = date.split('-');
            return dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0];
        }

        function loadAttachments(numero) {
            $.ajax({
                url: 'get_attachments.php',
                type: 'GET',
                data: { numero: numero },
                success: function(response) {
                    $('#attachmentsContent').html(response);
                },
                error: function() {
                    alert('Erro ao carregar anexos.');
                }
            });
        }

        $('#uploadForm').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            $.ajax({
                url: 'upload_attachment.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    alert('Arquivo anexado com sucesso.');
                    loadAttachments($('#numeroOficio').val());
                },
                error: function() {
                    alert('Erro ao anexar arquivo.');
                }
            });
        });

        function editOficio(numero) {
            window.location.href = 'edit_oficio.php?numero=' + numero;
        }

        function lockOficio(numero) {
            if (confirm('Tem certeza que deseja travar a edição deste ofício?')) {
                $.ajax({
                    url: 'lock_oficio.php',
                    type: 'POST',
                    data: { numero: numero },
                    success: function(response) {
                        alert('Edição do ofício travada com sucesso.');
                        location.reload();
                    },
                    error: function() {
                        alert('Erro ao travar a edição do ofício.');
                    }
                });
            }
        }

        $(document).on('click', '.visualizar-anexo', function() {
            var fileUrl = $(this).data('file');
            window.open(fileUrl, '_blank');
        });
    </script>

<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
