<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include '../checar_acesso_de_administrador.php';

// Inicializar a conexão com o banco de dados
$conn = getDatabaseConnection();

// Carregar contas ativas já cadastradas
$contasCadastradas = [];
try {
    $stmt = $conn->prepare("SELECT * FROM configuracao_os WHERE status = 'ativa'");
    $stmt->execute();
    $contasCadastradas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de Contas</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <style>
        .btn-adicionar {
            height: 38px;
            line-height: 24px;
            width: 100%;
        }
        .modal-content {
            border-radius: 10px;
        }
        .modal-dialog {
            max-width: 400px;
            margin: 1.75rem auto;
        }
        .modal-header {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .modal-footer {
            border-top: none;
        }
        .modal-header.error {
            background-color: #dc3545;
            color: white;
        }
        .modal-header.success {
            background-color: #28a745;
            color: white;
        }
    </style>
</head>
<body>
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Configuração de Contas</h3>
        <hr>
        <form id="configForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" id="conta_id" name="conta_id">
            <div id="contas-container">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="banco">Banco:</label>
                        <input type="text" class="form-control" id="banco" name="banco" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="agencia">Agência:</label>
                        <input type="text" class="form-control" id="agencia" name="agencia" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="tipo_conta">Tipo de Conta:</label>
                        <select class="form-control" id="tipo_conta" name="tipo_conta" required>
                            <option value="">Selecione</option>
                            <option value="Poupança">Poupança</option>
                            <option value="Corrente">Corrente</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="numero_conta">Número da Conta:</label>
                        <input type="text" class="form-control" id="numero_conta" name="numero_conta" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="titular_conta">Titular da Conta:</label>
                        <input type="text" class="form-control" id="titular_conta" name="titular_conta" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="cpf_cnpj_titular">CPF/CNPJ do Titular:</label>
                        <input type="text" class="form-control" id="cpf_cnpj_titular" name="cpf_cnpj_titular" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="chave_pix">Chave PIX:</label>
                        <input type="text" class="form-control" id="chave_pix" name="chave_pix" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label for="qr_code_pix">QR Code PIX:</label>
                        <input type="file" class="form-control" id="qr_code_pix" name="qr_code_pix">
                    </div>
                </div>
            </div>
            <button type="button" id="submit-button" class="btn btn-secondary btn-adicionar" onclick="adicionarConta()"><i class="fa fa-plus" aria-hidden="true"></i> Adicionar Conta</button>
        </form>
        <hr>
        <div class="table-responsive">
            <h5>Contas Cadastradas</h5>
                <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom: 100%">
                    <thead>
                        <tr>
                            <th>Banco</th>
                            <th>Agência</th>
                            <th>Tipo de Conta</th>
                            <th>Número da Conta</th>
                            <th>Titular</th>
                            <th>CPF/CNPJ</th>
                            <th>Chave PIX</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="contasAdicionadas">
                        <?php foreach ($contasCadastradas as $conta): ?>
                        <tr data-id="<?= $conta['id'] ?>" data-qrcode="<?= $conta['qr_code_pix'] ?>">
                            <td><?= $conta['banco'] ?></td>
                            <td><?= $conta['agencia'] ?></td>
                            <td><?= $conta['tipo_conta'] ?></td>
                            <td><?= $conta['numero_conta'] ?></td>
                            <td><?= $conta['titular_conta'] ?></td>
                            <td><?= $conta['cpf_cnpj_titular'] ?></td>
                            <td><?= $conta['chave_pix'] ?></td>
                            <td>
                                <button type="button" title="Visualizar QR Code PIX" class="btn btn-info btn-sm" onclick="visualizarQRCode(this)"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                <button type="button" title="Editar" class="btn btn-edit btn-sm" onclick="editarConta(this)"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                <button type="button" title="Excluir" class="btn btn-delete btn-sm" onclick="removerConta(this)"><i class="fa fa-trash" aria-hidden="true"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
        </div>
    </div>
</div>

    <!-- Modal para QR Code -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" role="dialog" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel">QR Code PIX</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="qrCodeModalBody">
                    <!-- QR Code vai aqui -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para alertas -->
    <div class="modal fade" id="alertModal" tabindex="-1" role="dialog" aria-labelledby="alertModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" id="alertModalHeader">
                    <h5 class="modal-title" id="alertModalLabel">Alerta</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="alertModalBody">
                    <!-- Alerta vai aqui -->
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
<script>
function showAlert(message, type) {
    $('#alertModalBody').text(message);
    if (type === 'error') {
        $('#alertModalHeader').removeClass('success').addClass('error');
    } else if (type === 'success') {
        $('#alertModalHeader').removeClass('error').addClass('success');
    }
    $('#alertModal').modal('show');
}

function visualizarQRCode(button) {
    var row = $(button).closest('tr');
    var qrCodeBase64 = row.data('qrcode');
    var qrCodeImg = '<img src="data:image/png;base64,' + qrCodeBase64 + '" class="img-fluid">';
    $('#qrCodeModalBody').html(qrCodeImg);
    $('#qrCodeModal').modal('show');
}

$(document).ready(function() {
    $.ajax({
        url: '../load_mode.php',
        method: 'GET',
        success: function(mode) {
            $('body').removeClass('light-mode dark-mode').addClass(mode);
        }
    });

    $('.mode-switch').on('click', function() {
        var body = $('body');
        body.toggleClass('dark-mode light-mode');

        var mode = body.hasClass('dark-mode') ? 'dark-mode' : 'light-mode';
        $.ajax({
            url: '../save_mode.php',
            method: 'POST',
            data: {
                mode: mode
            },
            success: function(response) {
                console.log(response);
            }
        });
    });

    $('#cpf_cnpj_titular').on('blur', function() {
        var cpfCnpj = $(this).val().replace(/\D/g, '');
        if (cpfCnpj.length === 11) {
            $(this).mask('000.000.000-00', {reverse: true});
            if (!validarCPF(cpfCnpj)) {
                showAlert('CPF inválido!', 'error');
            }
        } else if (cpfCnpj.length === 14) {
            $(this).mask('00.000.000/0000-00', {reverse: true});
            if (!validarCNPJ(cpfCnpj)) {
                showAlert('CNPJ inválido!', 'error');
            }
        } else {
            showAlert('CPF/CNPJ inválido!', 'error');
        }
    });
});

function validarCPF(cpf) {
    cpf = cpf.replace(/[^\d]+/g,'');
    if (cpf == '') return false;
    if (cpf.length != 11 || 
        cpf == "00000000000" || 
        cpf == "11111111111" || 
        cpf == "22222222222" || 
        cpf == "33333333333" || 
        cpf == "44444444444" || 
        cpf == "55555555555" || 
        cpf == "66666666666" || 
        cpf == "77777777777" || 
        cpf == "88888888888" || 
        cpf == "99999999999")
        return false;
    var add = 0;
    for (var i=0; i < 9; i ++)
        add += parseInt(cpf.charAt(i)) * (10 - i);
    var rev = 11 - (add % 11);
    if (rev == 10 || rev == 11)
        rev = 0;
    if (rev != parseInt(cpf.charAt(9)))
        return false;
    add = 0;
    for (var i = 0; i < 10; i ++)
        add += parseInt(cpf.charAt(i)) * (11 - i);
    rev = 11 - (add % 11);
    if (rev == 10 || rev == 11)
        rev = 0;
    if (rev != parseInt(cpf.charAt(10)))
        return false;
    return true;
}

function validarCNPJ(cnpj) {
    cnpj = cnpj.replace(/[^\d]+/g,'');
    if(cnpj == '') return false;
    if (cnpj.length != 14)
        return false;
    if (cnpj == "00000000000000" || 
        cnpj == "11111111111111" || 
        cnpj == "22222222222222" || 
        cnpj == "33333333333333" || 
        cnpj == "44444444444444" || 
        cnpj == "55555555555555" || 
        cnpj == "66666666666666" || 
        cnpj == "77777777777777" || 
        cnpj == "88888888888888" || 
        cnpj == "99999999999999")
        return false;
    var tamanho = cnpj.length - 2
    var numeros = cnpj.substring(0,tamanho);
    var digitos = cnpj.substring(tamanho);
    var soma = 0;
    var pos = tamanho - 7;
    for (var i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2)
              pos = 9;
    }
    var resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado != digitos.charAt(0))
        return false;
    tamanho = tamanho + 1;
    numeros = cnpj.substring(0,tamanho);
    soma = 0;
    pos = tamanho - 7;
    for (var i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2)
              pos = 9;
    }
    resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado != digitos.charAt(1))
          return false;
    return true;
}

function adicionarConta() {
    var id = $('#conta_id').val();
    var banco = $('#banco').val();
    var agencia = $('#agencia').val();
    var tipoConta = $('#tipo_conta').val();
    var numeroConta = $('#numero_conta').val();
    var titularConta = $('#titular_conta').val();
    var cpfCnpjTitular = $('#cpf_cnpj_titular').val();
    var chavePix = $('#chave_pix').val();

    if (!banco || !agencia || !tipoConta || !numeroConta || !titularConta || !cpfCnpjTitular || !chavePix) {
        showAlert('Todos os campos são obrigatórios!', 'error');
        return;
    }

    var qrCodePix = $('#qr_code_pix')[0].files[0];
    if (!qrCodePix && !id) {
        showAlert('QR Code PIX é obrigatório!', 'error');
        return;
    }

    if (qrCodePix) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var qrCodeBase64 = e.target.result.split(',')[1];
            salvarConta(id, banco, agencia, tipoConta, numeroConta, titularConta, cpfCnpjTitular, chavePix, qrCodeBase64);
        };
        reader.readAsDataURL(qrCodePix);
    } else {
        salvarConta(id, banco, agencia, tipoConta, numeroConta, titularConta, cpfCnpjTitular, chavePix, null);
    }
}

function salvarConta(id, banco, agencia, tipoConta, numeroConta, titularConta, cpfCnpjTitular, chavePix, qrCodeBase64) {
    var data = {
        id: id,
        banco: banco,
        agencia: agencia,
        tipo_conta: tipoConta,
        numero_conta: numeroConta,
        titular_conta: titularConta,
        cpf_cnpj_titular: cpfCnpjTitular,
        chave_pix: chavePix
    };
    if (qrCodeBase64) {
        data.qr_code_pix = qrCodeBase64;
    }

    $.ajax({
        url: 'salvar_configuracao_os.php',
        type: 'POST',
        data: data,
        success: function(response) {
            try {
                var res = JSON.parse(response);
                if (res.error) {
                    showAlert(res.error, 'error');
                } else {
                    if (id) {
                        $('tr[data-id="' + id + '"]').replaceWith(generateContaHTML(res.id, banco, agencia, tipoConta, numeroConta, titularConta, cpfCnpjTitular, chavePix, qrCodeBase64));
                    } else {
                        $('#contasAdicionadas').append(generateContaHTML(res.id, banco, agencia, tipoConta, numeroConta, titularConta, cpfCnpjTitular, chavePix, qrCodeBase64));
                    }
                    $('#configForm')[0].reset();
                    $('#conta_id').val('');
                    $('#submit-button').html('<i class="fa fa-plus" aria-hidden="true"></i> Adicionar Conta');
                }
            } catch (e) {
                console.log('Erro ao processar a resposta: ', e);
                showAlert('Erro ao processar a resposta do servidor.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.log('Erro:', error);
            console.log('Resposta do servidor:', xhr.responseText);
            showAlert('Erro ao salvar a conta', 'error');
        }
    });
}

function generateContaHTML(id, banco, agencia, tipoConta, numeroConta, titularConta, cpfCnpjTitular, chavePix, qrCodeBase64) {
    return `
        <tr data-id="${id}" data-qrcode="${qrCodeBase64}">
            <td>${banco}</td>
            <td>${agencia}</td>
            <td>${tipoConta}</td>
            <td>${numeroConta}</td>
            <td>${titularConta}</td>
            <td>${cpfCnpjTitular}</td>
            <td>${chavePix}</td>
            <td>
                <button type="button" title="Visualizar QR Code PIX" class="btn btn-info btn-sm" onclick="visualizarQRCode(this)"><i class="fa fa-eye" aria-hidden="true"></i></button>
                <button type="button" title="Editar" class="btn btn-edit btn-sm" onclick="editarConta(this)"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                <button type="button" title="Excluir" class="btn btn-delete btn-sm" onclick="removerConta(this)"><i class="fa fa-trash" aria-hidden="true"></i></button>
            </td>
        </tr>
    `;
}

        // Inicializar o DataTable após os dados serem carregados
        $('#tabelaResultados').DataTable({
            "language": {
                "url": "../../style/Portuguese-Brasil.json"
            },
            "order": [],
        });

function editarConta(button) {
    var row = $(button).closest('tr');
    $('#conta_id').val(row.data('id'));
    $('#banco').val(row.find('td').eq(0).text());
    $('#agencia').val(row.find('td').eq(1).text());
    $('#tipo_conta').val(row.find('td').eq(2).text());
    $('#numero_conta').val(row.find('td').eq(3).text());
    $('#titular_conta').val(row.find('td').eq(4).text());
    $('#cpf_cnpj_titular').val(row.find('td').eq(5).text());
    $('#chave_pix').val(row.find('td').eq(6).text());
    $('#submit-button').html('<i class="fa fa-floppy-o" aria-hidden="true"></i> Salvar Alterações');
}

function removerConta(button) {
    if (confirm('Você tem certeza que deseja remover esta conta?')) {
        var row = $(button).closest('tr');
        var id = row.data('id');

        $.ajax({
            url: 'remover_conta.php',
            type: 'POST',
            data: { id: id },
            success: function(response) {
                try {
                    var res = JSON.parse(response);
                    if (res.error) {
                        showAlert(res.error, 'error');
                    } else {
                        row.remove();
                        showAlert('Conta removida com sucesso!', 'success');
                    }
                } catch (e) {
                    console.log('Erro ao processar a resposta: ', e);
                    showAlert('Erro ao processar a resposta do servidor.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('Erro:', error);
                console.log('Resposta do servidor:', xhr.responseText);
                showAlert('Erro ao remover a conta', 'error');
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
