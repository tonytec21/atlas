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
    if (!empty($_GET['dados_complementares'])) {
        $filters[] = "dados_complementares LIKE '%" . $conn->real_escape_string($_GET['dados_complementares']) . "%'";
    }   

    if (count($filters) > 0) {
        $filterQuery = "WHERE " . implode(" AND ", $filters);
    }
}

$sql = "SELECT * FROM oficios $filterQuery ORDER BY id DESC";
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
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <style>
        .btn-close {
            outline: none;
            border: none; 
            background: none;
            padding: 0; 
            font-size: 1.5rem;
            cursor: pointer; 
            transition: transform 0.2s ease;
        }

        .btn-close:hover {
            transform: scale(2.10);
        }

        .btn-close:focus {
            outline: none;
        }

        /* #viewOficioModal .modal-dialog {
            height: 100vh; 
            display: flex; 
            flex-direction: column; 
        }

        #viewOficioModal .modal-content {
            height: 95%;
        }

        #viewOficioModal .modal-body {
            flex-grow: 1;
            padding: 0; 
        }

        #viewOficioModal #oficioPDF {
            width: 100%;
            height: 100%;
            border: none;
        } */

       /* Modal Styles */  
        #viewOficioModal .modal-dialog {  
            max-width: 80%;  
            margin: auto;  
            border-radius: 15px;  
            overflow: hidden;  
        }  

        #viewOficioModal .modal-content {  
            border: none;  
            border-radius: 15px;  
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);  
            transition: background-color 0.3s ease, box-shadow 0.3s ease;  
        }  

        #viewOficioModal .modal-header {  
            background-color: #ffffff; /* Light mode default */  
            border-bottom: none;  
            padding: 1.5rem;  
            display: flex;  
            justify-content: space-between;  
            align-items: center;  
        }  

        #viewOficioModal .modal-header h5 {  
            font-size: 1.75rem;  
            font-weight: 700;  
            color: #333; /* Light mode default */  
            margin: 0;  
        }  

        #viewOficioModal .btn-close {  
            font-size: 1.5rem;  
            color: #6c757d; /* Light mode default */  
            background: transparent;  
            border: none;  
            transition: color 0.3s ease;  
        }  

        #viewOficioModal .btn-close:hover {  
            color: #ff4d4f; /* Hover color */  
        }  

        #viewOficioModal .modal-body {  
            padding: 2rem;  
            background-color: #f8f9fa; /* Light mode default */  
            color: #333; /* Light mode default */  
        }  

        #viewOficioModal iframe {  
            width: 100%;  
            height: calc(100vh - 300px);  
            border: none;  
            border-radius: 10px;  
            background-color: #f1f3f5; /* Light mode default */  
        }  

        #viewOficioModal .modal-footer {  
            padding: 1.5rem;  
            background-color: #f8f9fa; /* Light mode default */  
            border-top: none;  
            display: flex;  
            justify-content: flex-end;  
        }  

        /* Button Styles */  
        #viewOficioModal .modal-footer .btn {  
            border-radius: 30px;  
            padding: 10px 30px;  
            font-weight: bold;  
            font-size: 1rem;  
            transition: background-color 0.2s ease, transform 0.2s ease;  
            border: none;  
        }  

        /* .btn-primary {  
            background-color: #007bff;  
            color: #fff;  
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.2);  
        }  

        .btn-primary:hover {  
            background-color: #0056b3;  
            transform: translateY(-3px);  
        }   */

        .btn-secondary {  
            background-color: #6c757d;  
            color: #fff;  
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);  
        }  

        .btn-secondary:hover {  
            background-color: #5a6268;  
            transform: translateY(-3px);  
        }  

        /* .btn-info {  
            background-color: #17a2b8;  
            color: #fff;  
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.2);  
        }  

        .btn-info:hover {  
            background-color: #138496;  
            transform: translateY(-3px);  
        }   */

        /* Dark Mode Styles */  
        body.dark-mode #viewOficioModal .modal-header {  
            background-color: #2c2f33;  
            color: #ffffff;  
        }  

        body.dark-mode #viewOficioModal .modal-header h5 {  
            color: #ffffff;  
        }  

        body.dark-mode #viewOficioModal .btn-close {  
            color: #a9a9a9;  
        }  

        body.dark-mode #viewOficioModal .btn-close:hover {  
            color: #ff4d4f; /* Hover color */  
        }  

        body.dark-mode #viewOficioModal .modal-body {  
            background-color: #23272a;  
            color: #ffffff;  
        }  

        body.dark-mode #viewOficioModal .modal-footer {  
            background-color: #2c2f33;  
        }  

        body.dark-mode #viewOficioModal .modal-footer .btn {  
            background-color: #007bff; /* Keep buttons same for both modes */  
        }  

        body.dark-mode #viewOficioModal .modal-footer .btn-primary {  
            background-color: #007bff; /* Button primary color */  
        }  

        body.dark-mode #viewOficioModal .modal-footer .btn-secondary {  
            background-color: #6c757d; /* Button secondary color */  
        }  

        body.dark-mode #viewOficioModal .modal-footer .btn-info {  
            background-color: #17a2b8; /* Button info color */  
        }  

        body.dark-mode #viewOficioModal iframe {  
            background-color: #343a40; /* Dark mode iframe background */  
        }

    </style>
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
                    <div class="col-md-1">
                        <label for="numero">Número:</label>
                        <input type="text" class="form-control" id="numero" name="numero">
                    </div>
                    <div class="col-md-2">
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
                    <div class="col-md-3">
                        <label for="dados_complementares">Dados Complementares:</label>
                        <input type="text" class="form-control" id="dados_complementares" name="dados_complementares">
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
            <hr>
            <div class="table-responsive">
                <h5>Resultados da Pesquisa</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 90%">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Data</th>
                            <th>Assunto</th>
                            <th>Destinatário</th>
                            <th>Cargo</th>
                            <th style="width: 15%;">Dados Complementares</th>
                            <th style="width: 10%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="oficioTable">
                        <?php foreach ($oficios as $oficio) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($oficio['numero']); ?></td>
                                <td data-order="<?php echo date('Y-m-d', strtotime($oficio['data'])); ?>"><?php echo date('d/m/Y', strtotime($oficio['data'])); ?></td>
                                <td><?php echo htmlspecialchars($oficio['assunto']); ?></td>
                                <td><?php echo htmlspecialchars($oficio['destinatario']); ?></td>
                                <td><?php echo htmlspecialchars($oficio['cargo']); ?></td>
                                <td><?php echo htmlspecialchars($oficio['dados_complementares']); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="viewOficio('<?php echo $oficio['numero']; ?>')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    <button class="btn btn-edit btn-sm" onclick="editOficio('<?php echo $oficio['numero']; ?>')" <?php if ($oficio['status'] == 1) echo 'disabled'; ?>><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                    <button class="btn btn-sm btn-primary" style="width: 40px; height: 40px; margin-bottom: 5px;" onclick="viewAttachments('<?php echo $oficio['numero']; ?>')"><i class="fa fa-paperclip" aria-hidden="true"></i></button>
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
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                        &times;
                    </button>
                </div>
                <div class="modal-body">
                    <iframe id="oficioPDF" src="" frameborder="0"></iframe>
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
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                        &times;
                    </button>
                </div>
                <div class="modal-body">
                    <div id="oficioDetails">
                        <div class="form-row">
                            <div class="form-group col-md-2">
                                <label for="detNumero">Nº do Ofício:</label>
                                <input type="text" class="form-control" id="detNumero" disabled>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="detTarefaId">Nº da Tarefa:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="detTarefaId" disabled>
                                    <div class="input-group-append">
                                        <button id="viewTaskButton" class="btn btn-info btn-sm" style="height: 38px; display: none;">
                                            <i class="fa fa-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="detData">Data:</label>
                                <input type="text" class="form-control" id="detData" disabled>
                            </div>
                            <div class="form-group col-md-6">
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
                        <div class="form-group">
                            <label for="detDadosComplementares">Dados Complementares:</label>
                            <textarea class="form-control" id="detDadosComplementares" rows="5" disabled></textarea>
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
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script>
        $(document).ready(function() {
            $('#viewOficioModal').on('shown.bs.modal', function() {
                // Ajustar a altura do modal para ocupar 100% da tela
                $(this).find('.modal-dialog').css({
                    'max-height': '100vh', // Limite máximo de altura igual à altura da viewport
                    'height': '100vh'      // Define o modal para ocupar 100% da altura da tela
                });
            });
        });

             // Inicializar DataTable
             $('#tabelaResultados').DataTable({
                "language": {
                    "url": "../style/Portuguese-Brasil.json"
                },
                "order": [[1, 'desc']]
            });

        function viewOficio(numero) {
            // Primeiro, verificamos o JSON de configuração
            $.ajax({
                url: '../style/configuracao.json',
                dataType: 'json',
                cache: false, // Desabilita o cache para garantir que o JSON mais recente seja carregado
                success: function(data) {
                    // Verifica o valor do "timbrado" no JSON
                    var url = '';
                    if (data.timbrado === 'S') {
                        url = 'view_oficio.php?numero=' + numero;
                    } else if (data.timbrado === 'N') {
                        url = 'view-oficio.php?numero=' + numero;
                    }

                    // Define a URL no iframe e abre o modal
                    $('#oficioPDF').attr('src', url);
                    $('#viewOficioModal').modal('show');
                    
                    // Verifica o status do ofício
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

                    // Define a ação para o botão de bloqueio
                    $('#lockButton').off('click').on('click', function() {
                        lockOficio(numero);
                    });

                },
                error: function() {
                    alert('Erro ao carregar a configuração de timbrado.');
                }
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
                    $('#detDadosComplementares').val(data.dados_complementares);
                    $('#numeroOficio').val(data.numero);
                    loadAttachments(data.numero);

                    // Verificar se o número do ofício existe na tabela tarefas
                    $.ajax({
                        url: 'verificar_tarefa.php',
                        type: 'GET',
                        data: { numero_oficio: numero },
                        success: function(response) {
                            var result = JSON.parse(response);
                            if (result.status === 'success') {
                                // Preencher o campo com o ID da tarefa
                                $('#detTarefaId').val(result.id);

                                // Obter o token da tarefa e atualizar o botão
                                $.ajax({
                                    url: 'get_tarefa_token.php',
                                    type: 'GET',
                                    data: { id: result.id },
                                    success: function(response) {
                                        var tarefa = JSON.parse(response);
                                        if (tarefa.status === 'success') {
                                            $('#viewTaskButton').attr('onclick', `window.location.href='../tarefas/index_tarefa.php?token=${tarefa.token}'`);
                                            $('#viewTaskButton').show(); // Exibir o botão
                                        } else {
                                            $('#viewTaskButton').hide(); // Ocultar o botão se o token não for encontrado
                                        }
                                    },
                                    error: function() {
                                        alert('Erro ao obter o token da tarefa.');
                                        $('#viewTaskButton').hide(); // Ocultar o botão em caso de erro
                                    }
                                });
                            } else {
                                $('#detTarefaId').val('Não encontrado');
                                $('#viewTaskButton').hide(); // Ocultar o botão se a tarefa não for encontrada
                                console.log(result.message);
                            }
                        },
                        error: function() {
                            alert('Erro ao verificar a tarefa.');
                            $('#viewTaskButton').hide(); // Ocultar o botão em caso de erro
                        }
                    });

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
            console.log("Carregando anexos para o número:", numero); // Verifica se o valor do número está correto
            $.ajax({
                url: 'get_attachments.php',
                type: 'GET',
                data: { numero: numero },
                success: function(response) {
                    console.log("Resposta recebida:", response); 
                    $('#attachmentsContent').html(response); 
                },
                error: function(xhr, status, error) {
                    console.log("Erro na requisição:", error); // Exibe o erro no console
                    alert('Erro ao carregar anexos.');
                }
            });
        }


        $(document).on('click', '.visualizar-anexo', function() {
            var filePath = $(this).data('file');
            
            // Abrir o anexo em uma nova aba/janela ou dentro de um modal
            window.open(filePath, '_blank');
        });

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
                    // Exibir mensagem de sucesso usando SweetAlert2
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Arquivo anexado com sucesso.',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        // Recarregar anexos após fechar o alerta
                        loadAttachments($('#numeroOficio').val());
                    });
                },
                error: function() {
                    // Exibir mensagem de erro usando SweetAlert2
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao anexar arquivo.',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            });
        });


        function editOficio(numero) {
            window.location.href = 'edit_oficio.php?numero=' + numero;
        }

        function lockOficio(numero) {
            Swal.fire({
                title: 'Tem certeza?',
                text: 'Tem certeza que deseja travar a edição deste ofício?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, travar',
                cancelButtonText: 'Não, cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Se o usuário confirmar, realiza a requisição AJAX
                    $.ajax({
                        url: 'lock_oficio.php',
                        type: 'POST',
                        data: { numero: numero },
                        success: function(response) {
                            // Exibe mensagem de sucesso usando SweetAlert2
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Edição do ofício travada com sucesso.',
                                showConfirmButton: false,
                                timer: 2000
                            }).then(() => {
                                // Recarrega a página após fechar o alerta
                                location.reload();
                            });
                        },
                        error: function() {
                            // Exibe mensagem de erro usando SweetAlert2
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro!',
                                text: 'Erro ao travar a edição do ofício.',
                                showConfirmButton: false,
                                timer: 2000
                            });
                        }
                    });
                }
            });
        }

        
        $(document).on('click', '.excluir-anexo', function() {
            var filePath = $(this).data('file'); 
            var numero = $(this).data('numero'); 

            // Confirmação antes de excluir
            Swal.fire({
                title: 'Tem certeza?',
                text: "Você quer excluir esse anexo?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, excluir!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Faz a requisição para mover o arquivo
                    $.ajax({
                        url: 'mover_para_lixeira.php',
                        type: 'POST',
                        data: { file: filePath, numero: numero }, // Envia os dados corretos
                        success: function(response) {
                            var data = JSON.parse(response);
                            if (data.status === 'success') {
                                Swal.fire(
                                    'Excluído!',
                                    data.message,
                                    'success'
                                );
                                // Recarregar a lista de anexos após mover o arquivo
                                loadAttachments(numero);
                            } else {
                                Swal.fire('Erro', data.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Erro', 'Falha ao excluir o anexo.', 'error');
                        }
                    });
                }
            });
        });

        $(document).ready(function() {
            var currentYear = new Date().getFullYear();

            // Função de validação de data
            function validateDate(input) {
                var selectedDate = new Date($(input).val());
                if (selectedDate.getFullYear() > currentYear) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Data inválida',
                        text: 'O ano não pode ser maior que o ano atual.',
                        confirmButtonText: 'Ok'
                    });
                    $(input).val(''); // Limpa o campo da data
                }
            }

            // Aplicar a validação de data nos campos de filtro de pesquisa
            $('#data').on('change', function() {
                // Certifique-se de que há um valor antes de validar
                if ($(this).val()) {
                    validateDate(this);
                }
            });
        });


    </script>

<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
