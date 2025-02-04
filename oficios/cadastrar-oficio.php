<?php
include(__DIR__ . '/session_check.php');
checkSession();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oficios_db";

// Conexão com o banco de dados "oficios_db"
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Função para obter o próximo número de ofício
function getNextOficioNumber($conn) {
    $currentYear = date('Y');
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(numero, '/', 1) AS UNSIGNED)) AS max_numero FROM oficios WHERE YEAR(data) = $currentYear");
    $row = $result->fetch_assoc();
    $lastNumero = $row['max_numero'];

    if ($lastNumero) {
        $nextSequence = (int)$lastNumero + 1;
    } else {
        $nextSequence = 1;
    }

    return $nextSequence . '/' . $currentYear;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = getNextOficioNumber($conn);
    $destinatario = $conn->real_escape_string($_POST['destinatario']);
    $assunto = $conn->real_escape_string($_POST['assunto']);
    $corpo = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['corpo'])));
    $assinante = $conn->real_escape_string($_POST['assinante']);
    $data = $conn->real_escape_string($_POST['data']);
    $tratamento = $conn->real_escape_string($_POST['tratamento']);
    $cargo = $conn->real_escape_string($_POST['cargo']);
    $cargo_assinante = $conn->real_escape_string($_POST['cargo_assinante']);
    $dados_complementares = $conn->real_escape_string($_POST['dados_complementares']);

    $stmt = $conn->prepare("INSERT INTO oficios (destinatario, assunto, corpo, assinante, data, numero, tratamento, cargo, cargo_assinante, dados_complementares) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssss", $destinatario, $assunto, $corpo, $assinante, $data, $numero, $tratamento, $cargo, $cargo_assinante, $dados_complementares);
    $stmt->execute();

    $stmt->close();
    $conn->close();

    // A saída precisa estar em conformidade com o DOM e JavaScript
    echo "<script src='../script/sweetalert2.js'></script>
          <script>
              document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire({
                      icon: 'success',
                      title: 'Ofício salvo com sucesso!',
                      showConfirmButton: true,
                      confirmButtonText: 'OK'
                  }).then((result) => {
                      if (result.isConfirmed) {
                          window.location.href = 'index.php';
                      }
                  });
              });
          </script>";
}


// Conexão com o banco de dados "atlas"
$atlasConn = new mysqli($servername, $username, $password, "atlas");
if ($atlasConn->connect_error) {
    die("Falha na conexão com o banco atlas: " . $atlasConn->connect_error);
}
$atlasConn->set_charset("utf8"); // Definir charset para UTF-8

// Buscar funcionários do banco de dados "atlas"
$sql = "SELECT id, nome_completo, cargo FROM funcionarios WHERE status = 'ativo'";
$result = $atlasConn->query($sql);
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
$atlasConn->close();

// Usuário logado
$loggedUser = $_SESSION['username'];

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Criar Ofício</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css"/>  
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css"/>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <script src="../ckeditor/ckeditor.js"></script>
    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <style>
        .cke_notification_warning { display: none !important; }
        .btn-info {
            width: 75px;
        }
        .btn-success {
            width: 75px;
            height: 40px;
            border-radius: 5px;
        }
        /* Estilos adicionais para o modal */  
/* Reset e configurações base */  
.modal * {  
    box-sizing: border-box !important;  
}  

/* Modal */  
.modal {  
    position: fixed !important;  
    top: 0 !important;  
    left: 0 !important;  
    width: 100% !important;  
    height: 100% !important;  
    background: rgba(0, 0, 0, 0.5) !important;  
    z-index: 1055 !important;  
    display: none !important;  
}  

.modal.show {  
    display: block !important;  
}  

.modal-dialog {  
    position: relative !important;  
    width: 85% !important;  
    margin: 2rem auto !important;  
    max-width: 1400px !important;  
}  

.modal-content {  
    background: #fff !important;  
    border-radius: 8px !important;  
    box-shadow: 0 0 20px rgba(0,0,0,0.15) !important;  
    display: flex !important;  
    flex-direction: column !important;  
    height: calc(100vh - 4rem) !important;  
}  

/* Header */  
.modal-header {  
    padding: 1rem 1.5rem !important;  
    border-bottom: 1px solid #e9ecef !important;  
    display: flex !important;  
    align-items: center !important;  
    justify-content: space-between !important;  
}  

.modal-title {  
    font-size: 1.25rem !important;  
    font-weight: 600 !important;  
    margin: 0 !important;  
    display: flex !important;  
    align-items: center !important;  
    gap: 0.5rem !important;  
    color: #2c3e50 !important;  
}  

.close {  
    background: none !important;  
    border: none !important;  
    font-size: 1.5rem !important;  
    cursor: pointer !important;  
    padding: 0.5rem !important;  
    color: #6c757d !important;  
    transition: color 0.2s !important;  
}  

.close:hover {  
    color: #dc3545 !important;  
}  

/* Body */  
.modal-body {  
    flex: 1 !important;  
    padding: 1.5rem !important;  
    overflow: auto !important;  
}  

/* Table */  
.table {  
    width: 100% !important;  
    border-collapse: collapse !important;  
    margin: 0 !important;  
    white-space: nowrap !important;  
}  

.table thead th {  
    background: #f8f9fa !important;  
    padding: 1rem !important;  
    font-weight: 600 !important;  
    color: #2c3e50 !important;  
    text-align: left !important;  
    border-bottom: 2px solid #dee2e6 !important;  
}  

/* Definição das larguras das colunas */  
.col-numero { width: 8% !important; }  
.col-data { width: 10% !important; }  
.col-destinatario { width: 30% !important; }  
.col-assunto { width: 35% !important; }  
.col-assinante { width: 12% !important; }  
.col-acoes { width: 5% !important; text-align: center !important; }  

.table td {  
    padding: 0.75rem 1rem !important;  
    border-bottom: 1px solid #e9ecef !important;  
    vertical-align: middle !important;  
}  

/* Células com texto longo */  
.cell-content {  
    max-width: 100% !important;  
    overflow: hidden !important;  
    text-overflow: ellipsis !important;  
    white-space: nowrap !important;  
}  

/* Botões de ação */  
.action-buttons {  
    display: flex !important;  
    gap: 0.5rem !important;  
    justify-content: center !important;  
}  

.btn-action {  
    width: 32px !important;  
    height: 32px !important;  
    padding: 0 !important;  
    border: none !important;  
    border-radius: 4px !important;  
    display: flex !important;  
    align-items: center !important;  
    justify-content: center !important;  
    cursor: pointer !important;  
    transition: all 0.2s !important;  
}  

.btn-view {  
    background: #e3f2fd !important;  
    color: #0d6efd !important;  
}  

.btn-use {  
    background: #e8f5e9 !important;  
    color: #198754 !important;  
}  

.btn-action:hover {  
    transform: translateY(-1px) !important;  
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;  
}  

.btn-close {
    outline: none !important;
    border: none !important; 
    background: none !important;
    padding: 0 !important; 
    font-size: 1.5rem !important;
    cursor: pointer !important; 
    transition: transform 0.2s ease !important;
}

.btn-close:hover {
    transform: scale(2.10) !important;
}

.btn-close:focus {
    outline: none !important;
}

    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

    <div id="main" class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">  
                <h3>Criar Ofício</h3>  
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#oficiosAnterioresModal" onclick="carregarOficios()">  
                    <i class="fa fa-history"></i> Ver Ofícios Anteriores  
                </button> 
            </div>
            <hr>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="tratamento">Forma de Tratamento:</label>
                        <input type="text" class="form-control" id="tratamento" name="tratamento">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="destinatario">Destinatário:</label>
                        <input type="text" class="form-control" id="destinatario" name="destinatario" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="cargo">Cargo:</label>
                        <input type="text" class="form-control" id="cargo" name="cargo">
                    </div>
                </div>
                <div class="form-group">
                    <label for="assunto">Assunto:</label>
                    <input type="text" class="form-control" id="assunto" name="assunto" required>
                </div>
                <div class="form-group">
                    <label for="corpo">Corpo do Ofício:</label>
                    <textarea class="form-control" id="corpo" name="corpo" rows="10" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="assinante">Assinante:</label>
                        <select class="form-control" id="assinante" name="assinante" required>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo htmlspecialchars($employee['nome_completo']); ?>" <?php echo $loggedUser == $employee['nome_completo'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="cargo_assinante">Cargo do Assinante:</label>
                        <input type="text" class="form-control" id="cargo_assinante" name="cargo_assinante" value="<?php echo $loggedUser == $employee['nome_completo'] ? htmlspecialchars($employee['cargo']) : ''; ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="data">Data:</label>
                        <input type="date" class="form-control" id="data" name="data" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="dados_complementares">Dados Complementares:</label>
                    <textarea class="form-control" id="dados_complementares" name="dados_complementares" rows="5"></textarea>
                </div>
                <button type="submit" style="margin-bottom: 31px;margin-top: 0px !important;" class="btn btn-primary w-100">Salvar Ofício</button>
            </form>
        </div>
    </div>

    <!-- Modal Ofícios Anteriores -->  
    <div class="modal fade" id="oficiosAnterioresModal" tabindex="-1" aria-labelledby="oficiosAnterioresModalLabel" aria-hidden="true">  
        <div class="modal-dialog modal-xl">  
            <div class="modal-content">  
                <!-- Header -->  
                <div class="modal-header">  
                    <h5 class="modal-title" id="oficiosAnterioresModalLabel">  
                        <i class="fas fa-file-alt"></i>  
                        Ofícios Anteriores  
                    </h5>  
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>  
                </div>  
                
                <!-- Body -->  
                <div class="modal-body">  
                    <div class="table-responsive">  
                        <table class="table table-striped" id="oficiosTable" style="width: 95%!important; zoom: 90%">  
                            <colgroup>  
                                <col class="col-numero">  
                                <col class="col-data">  
                                <col class="col-destinatario">  
                                <col class="col-assunto">  
                                <col class="col-assinante">  
                                <col class="col-acoes">  
                            </colgroup>  
                            <thead>  
                                <tr>  
                                    <th>Número</th>  
                                    <th>Data</th>  
                                    <th>Destinatário</th>  
                                    <th>Assunto</th>  
                                    <th>Assinante</th>  
                                    <th>Ações</th>  
                                </tr>  
                            </thead>  
                            <tbody id="oficiosTableBody"></tbody>  
                        </table>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>
 
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>  
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>  
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>  
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>


    <script>
        $(document).ready(function() {
            // Inicializar o CKEditor com corretor ortográfico
            CKEDITOR.replace('corpo', {
                extraPlugins: 'htmlwriter',
                allowedContent: true,
                filebrowserUploadUrl: '/uploader/upload.php',
                filebrowserUploadMethod: 'form',
                scayt_autoStartup: true, // Habilitar o corretor ortográfico automaticamente
                scayt_sLang: 'pt_BR' // Definir o idioma do corretor ortográfico para português brasileiro
            });

            // Preencher automaticamente o campo de cargo ao selecionar um assinante
            $('#assinante').on('change', function() {
                var selectedAssinante = $(this).val();
                var cargoAssinante = '';

                <?php foreach ($employees as $employee): ?>
                if (selectedAssinante === "<?php echo htmlspecialchars($employee['nome_completo']); ?>") {
                    cargoAssinante = "<?php echo htmlspecialchars($employee['cargo']); ?>";
                }
                <?php endforeach; ?>

                $('#cargo_assinante').val(cargoAssinante);
            }).trigger('change'); // Trigger change event to set initial value
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

<script>  
let dataTable = null;  

window.carregarOficios = function() {  
    console.log('Iniciando carregamento de ofícios');  
    
    if (dataTable) {  
        dataTable.destroy();  
    }  
    
    $('#oficiosTableBody').html(`  
        <tr>  
            <td colspan="6" class="text-center">  
                <div class="spinner-border text-primary" role="status">  
                    <span class="sr-only">Carregando...</span>  
                </div>  
            </td>  
        </tr>  
    `);  
    
    $.ajax({  
        url: 'listar_oficios.php',  
        method: 'GET',  
        dataType: 'json',  
        success: function(response) {  
            console.log('Dados recebidos:', response);  
            
            if (!response.data || !Array.isArray(response.data)) {  
                $('#oficiosTableBody').html(`  
                    <tr>  
                        <td colspan="6" class="text-center">Nenhum ofício encontrado</td>  
                    </tr>  
                `);  
                return;  
            }  
            
            let html = '';  
            response.data.forEach(function(oficio) {  
                const numero = $('<div>').text(oficio.numero).html();  
                const destinatario = $('<div>').text(oficio.destinatario).html();  
                const assunto = $('<div>').text(oficio.assunto).html();  
                const assinante = $('<div>').text(oficio.assinante).html();  
                
                html += `  
                    <tr>  
                        <td><div class="cell-content">${numero}</div></td>  
                        <td><div class="cell-content">${oficio.data}</div></td>  
                        <td><div class="cell-content" title="${destinatario}">${destinatario}</div></td>  
                        <td><div class="cell-content" title="${assunto}">${assunto}</div></td>  
                        <td><div class="cell-content" title="${assinante}">${assinante}</div></td>  
                        <td>  
                            <div class="action-buttons">  
                                <button class="btn-action btn-view" onclick="verCorpo('${numero}')" title="Visualizar ofício">  
                                    <i class="fa fa-eye"></i>  
                                </button>  
                                <button class="btn-action btn-use" onclick="usarModelo('${numero}')" title="Usar como modelo">  
                                    <i class="fa fa-copy"></i>  
                                </button>  
                            </div>  
                        </td>  
                    </tr>  
                `;  
            });  
            
            $('#oficiosTableBody').html(html);  
            initializeDataTable();  
        },  
        error: function(xhr, status, error) {  
            console.error('Erro na requisição:', error);  
            $('#oficiosTableBody').html(`  
                <tr>  
                    <td colspan="6" class="text-center text-danger">  
                        <i class="fas fa-exclamation-triangle me-2"></i>  
                        Erro ao carregar ofícios. Por favor, tente novamente.  
                    </td>  
                </tr>  
            `);  
        }  
    });  
};  

function initializeDataTable() {  
    dataTable = $('#oficiosTable').DataTable({  
        responsive: true,  
        language: {  
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'  
        },  
        pageLength: 15,  
        order: [[1, 'desc']],  
        columnDefs: [  
            { orderable: false, targets: 5 }  
        ],  
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +  
             "<'row'<'col-sm-12'tr>>" +  
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",  
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, "Todos"]]  
    });  
}  

window.verCorpo = function(numero) {  
    $.ajax({  
        url: 'get_oficio_details.php',  
        method: 'GET',  
        data: { numero: numero },  
        dataType: 'json',  
        beforeSend: function() {  
            Swal.fire({  
                title: 'Carregando...',  
                html: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Carregando...</span></div>',  
                showConfirmButton: false,  
                allowOutsideClick: false,  
                didOpen: () => {  
                    Swal.showLoading();  
                }  
            });  
        },  
        success: function(response) {  
            Swal.fire({  
                title: `Ofício ${numero}`,  
                html: `<div class="text-start p-3">${response.corpo}</div>`,  
                width: '80%',  
                confirmButtonText: 'Fechar',  
                customClass: {  
                    container: 'swal-large-text'  
                }  
            });  
        },  
        error: function() {  
            Swal.fire({  
                icon: 'error',  
                title: 'Erro',  
                text: 'Não foi possível carregar os detalhes do ofício'  
            });  
        }  
    });  
};  

window.usarModelo = function(numero) {  
    $.ajax({  
        url: 'get_oficio_details.php',  
        method: 'GET',  
        data: { numero: numero },  
        dataType: 'json',  
        beforeSend: function() {  
            Swal.fire({  
                title: 'Carregando modelo...',  
                html: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Carregando...</span></div>',  
                showConfirmButton: false,  
                allowOutsideClick: false,  
                allowEscapeKey: false,  
                didOpen: () => {  
                    Swal.showLoading();  
                }  
            });  
        },  
        success: function(response) {  
            try {  
                $('#tratamento').val(response.tratamento || '');  
                $('#destinatario').val(response.destinatario || '');  
                $('#cargo').val(response.cargo || '');  
                $('#assunto').val(response.assunto || '');  
                
                if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances.corpo) {  
                    CKEDITOR.instances.corpo.setData(response.corpo || '');  
                }  
                
                $('#dados_complementares').val(response.dados_complementares || '');  
                
                // Fecha o modal do bootstrap  
                $('#oficiosAnterioresModal').modal('hide');  
                
                Swal.close();  
                setTimeout(() => {  
                    Swal.fire({  
                        icon: 'success',  
                        title: 'Modelo carregado!',  
                        text: 'O conteúdo do ofício foi carregado com sucesso.',  
                        timer: 1500,  
                        showConfirmButton: false  
                    });  
                }, 200);  
            } catch (error) {  
                console.error('Erro ao preencher campos:', error);  
                Swal.fire({  
                    icon: 'error',  
                    title: 'Erro',  
                    text: 'Erro ao preencher os campos do formulário: ' + error.message  
                });  
            }  
        },  
        error: function(xhr, status, error) {  
            console.error('Erro na requisição:', error);  
            Swal.fire({  
                icon: 'error',  
                title: 'Erro',  
                text: 'Não foi possível carregar o modelo do ofício: ' + error  
            });  
        }  
    });  
};  

// Inicialização do CKEditor quando o documento estiver pronto  
$(document).ready(function() {  
    CKEDITOR.replace('corpo', {  
        extraPlugins: 'htmlwriter',  
        allowedContent: true,  
        filebrowserUploadUrl: '/uploader/upload.php',  
        filebrowserUploadMethod: 'form',  
        scayt_autoStartup: true,  
        scayt_sLang: 'pt_BR'  
    });  
});
</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
