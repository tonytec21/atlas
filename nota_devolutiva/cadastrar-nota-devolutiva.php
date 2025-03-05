<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection2.php');  
?>  
<?php include(__DIR__ . '/consultas.php'); ?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Atlas - Criar Nota Devolutiva</title>  
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
    <?php include(__DIR__ . '/style_nota.php'); ?>   
</head>  
<body class="light-mode">  
<?php include(__DIR__ . '/../menu.php'); ?>  

    <div id="main" class="main-content">  
        <div class="container">  
            <div class="d-flex justify-content-between align-items-center">  
                <h3>Criar Nota Devolutiva</h3>  
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#notasAnterioresModal" onclick="carregarNotas()">  
                    <i class="fa fa-history"></i> Ver Notas Anteriores  
                </button>   
            </div>  
            <hr>  
            <form method="POST" action="" id="notaForm">  
                <div class="form-row">  
                    <div class="form-group col-md-4">  
                        <label for="apresentante">Apresentante/Requerente:</label>  
                        <div class="input-group">  
                            <input type="text" class="form-control" id="apresentante" name="apresentante" required>  
                            <div class="input-group-append" id="consultaIndicator" style="display:none;">  
                                <span class="input-group-text">  
                                    <i class="fa fa-spinner fa-spin"></i>  
                                </span>  
                            </div>  
                        </div>  
                    </div>  
                    <div class="form-group col-md-3">  
                        <label for="cpf_cnpj">CPF/CNPJ:</label>  
                        <div class="input-group">  
                            <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj">  
                            <div class="input-group-append">  
                                <button class="btn btn-outline-secondary" type="button" id="consultarCpfCnpj" title="Consultar CNPJ">  
                                    <i class="fa fa-search"></i>  
                                </button>  
                            </div>  
                        </div>  
                    </div>  
                    <div class="form-group col-md-2">  
                        <label for="protocolo">Número do Protocolo:</label>  
                        <input type="text" class="form-control" id="protocolo" name="protocolo">  
                    </div>  
                    <div class="form-group col-md-3">  
                        <label for="data_protocolo">Data do Protocolo:</label>  
                        <input type="date" class="form-control" id="data_protocolo" name="data_protocolo" required>  
                    </div>   
                    <div class="form-group col-md-6">  
                        <label for="titulo">Título Apresentado:</label>  
                        <input type="text" class="form-control" id="titulo" name="titulo" required>  
                    </div>  
                    <div class="form-group col-md-6">  
                        <label for="origem_titulo">Origem do Título:</label>  
                        <input type="text" class="form-control" id="origem_titulo" name="origem_titulo">  
                    </div>  
                </div>  
                <div class="form-group">  
                    <label for="corpo">Motivos da Devolução:</label>  
                    <textarea class="form-control" id="corpo" name="corpo" rows="10" required></textarea>  
                </div>  
                <div class="form-group">  
                    <label for="prazo_cumprimento">Prazo Para Cumprimento:</label>  
                    <textarea class="form-control" id="prazo_cumprimento" name="prazo_cumprimento" rows="5"></textarea>  
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
                        <input type="text" class="form-control" id="cargo_assinante" name="cargo_assinante">  
                    </div>  
                    <div class="form-group col-md-4">  
                        <label for="data">Data da Nota:</label>  
                        <input type="date" class="form-control" id="data" name="data" value="<?php echo date('Y-m-d'); ?>" required>  
                    </div>  
                </div>  
                <div class="form-group">  
                    <label for="dados_complementares">Dados Complementares:</label>  
                    <textarea class="form-control" id="dados_complementares" name="dados_complementares" rows="5"></textarea>  
                </div>  
                <button type="submit" style="margin-bottom: 31px;margin-top: 0px !important;" class="btn btn-primary w-100">Salvar Nota Devolutiva</button>  
            </form>  
        </div>  
    </div>  

    <!-- Modal Notas Anteriores -->  
    <div class="modal fade" id="notasAnterioresModal" tabindex="-1" aria-labelledby="notasAnterioresModalLabel" aria-hidden="true">  
        <div class="modal-dialog modal-xl">  
            <div class="modal-content">  
                <!-- Header -->  
                <div class="modal-header">  
                    <h5 class="modal-title" id="notasAnterioresModalLabel">  
                        <i class="fas fa-file-alt"></i>  
                        Notas Devolutivas Anteriores  
                    </h5>  
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>  
                </div>  
                
                <!-- Body -->  
                <div class="modal-body">  
                    <div class="table-responsive">  
                        <table class="table table-striped" id="notasTable" style="width: 95%!important; zoom: 90%">  
                            <colgroup>  
                                <col class="col-numero">  
                                <col class="col-data">  
                                <col class="col-apresentante">  
                                <col class="col-titulo">  
                                <col class="col-acoes">  
                            </colgroup>  
                            <thead>  
                                <tr>  
                                    <th>Número</th>  
                                    <th>Data</th>  
                                    <th>Apresentante</th>  
                                    <th>Título</th>  
                                    <th>Ações</th>  
                                </tr>  
                            </thead>  
                            <tbody id="notasTableBody"></tbody>  
                        </table>  
                    </div>  
                </div>  
            </div>  
        </div>  
    </div>  

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>  
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>  
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>  
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>  
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  
    <!-- Adicionar jQuery Mask Plugin -->  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>  

    <script>  
        // Validação de CPF  
        function validarCPF(cpf) {  
            cpf = cpf.replace(/[^\d]/g, '');  
            if (cpf.length !== 11) return false;  
            
            // Verificar CPFs conhecidos como inválidos  
            if (/^(\d)\1{10}$/.test(cpf)) return false;  
            
            // Validar dígitos verificadores  
            let soma = 0;  
            let resto;  
            for (let i = 1; i <= 9; i++) {  
                soma += parseInt(cpf.substring(i-1, i)) * (11 - i);  
            }  
            resto = (soma * 10) % 11;  
            if (resto === 10 || resto === 11) resto = 0;  
            if (resto !== parseInt(cpf.substring(9, 10))) return false;  
            
            soma = 0;  
            for (let i = 1; i <= 10; i++) {  
                soma += parseInt(cpf.substring(i-1, i)) * (12 - i);  
            }  
            resto = (soma * 10) % 11;  
            if (resto === 10 || resto === 11) resto = 0;  
            if (resto !== parseInt(cpf.substring(10, 11))) return false;  
            
            return true;  
        }  

        // Validação de CNPJ  
        function validarCNPJ(cnpj) {  
            cnpj = cnpj.replace(/[^\d]/g, '');  
            if (cnpj.length !== 14) return false;  
            
            // Verificar CNPJs conhecidos como inválidos  
            if (/^(\d)\1{13}$/.test(cnpj)) return false;  
            
            // Validar dígitos verificadores  
            let tamanho = cnpj.length - 2;  
            let numeros = cnpj.substring(0, tamanho);  
            let digitos = cnpj.substring(tamanho);  
            let soma = 0;  
            let pos = tamanho - 7;  
            
            for (let i = tamanho; i >= 1; i--) {  
                soma += numeros.charAt(tamanho - i) * pos--;  
                if (pos < 2) pos = 9;  
            }  
            
            let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;  
            if (resultado !== parseInt(digitos.charAt(0))) return false;  
            
            tamanho = tamanho + 1;  
            numeros = cnpj.substring(0, tamanho);  
            soma = 0;  
            pos = tamanho - 7;  
            
            for (let i = tamanho; i >= 1; i--) {  
                soma += numeros.charAt(tamanho - i) * pos--;  
                if (pos < 2) pos = 9;  
            }  
            
            resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;  
            if (resultado !== parseInt(digitos.charAt(1))) return false;  
            
            return true;  
        }  

        // Validar CPF ou CNPJ  
        function validarCpfCnpj(valor) {  
            valor = valor.replace(/[^\d]/g, '');  
            if (valor.length === 11) {  
                return validarCPF(valor);  
            } else if (valor.length === 14) {  
                return validarCNPJ(valor);  
            }  
            return false;  
        }  

        // Função para consultar apenas CNPJ  
        function consultarCnpj() {  
            let documento = $('#cpf_cnpj').val().replace(/[^\d]/g, '');  
            
            if (!documento || documento.length !== 14) {  
                Swal.fire({  
                    icon: 'warning',  
                    title: 'Atenção',  
                    text: 'Por favor, digite um CNPJ válido para consulta.',  
                    confirmButtonColor: '#3085d6'  
                });  
                return;  
            }  
            
            if (!validarCNPJ(documento)) {  
                Swal.fire({  
                    icon: 'error',  
                    title: 'CNPJ Inválido',  
                    text: 'O CNPJ informado não é válido. Verifique e tente novamente.',  
                    confirmButtonColor: '#3085d6',  
                    willClose: function() {  
                        $('#cpf_cnpj').val('');  
                    }  
                });  
                return;  
            }  
            
            // Mostrar indicador de consulta  
            $('#consultaIndicator').show();  
            
            // Consultar via backend PHP  
            $.ajax({  
                url: '?action=consultar',  
                method: 'GET',  
                data: { documento: documento },  
                dataType: 'json',  
                success: function(data) {  
                    console.log("Resposta da API:", data);  
                    
                    if (data.erro) {  
                        Swal.fire({  
                            icon: 'error',  
                            title: 'Erro na consulta',  
                            text: data.mensagem || 'Erro ao consultar o CNPJ.',  
                            confirmButtonColor: '#3085d6'  
                        });  
                        return;  
                    }  
                    
                    // Preencher o campo com a razão social  
                    $('#apresentante').val(data.razao_social || '');  
                    
                    Swal.fire({  
                        icon: 'success',  
                        title: 'Consulta realizada',  
                        text: 'Dados preenchidos com sucesso!',  
                        confirmButtonColor: '#3085d6',  
                        timer: 1500,  
                        showConfirmButton: false  
                    });  
                },  
                error: function(xhr, status, error) {  
                    console.error('Erro na requisição:', error);  
                    console.error('Status HTTP:', xhr.status);  
                    console.error('Resposta:', xhr.responseText);  
                    
                    let mensagem = 'Não foi possível consultar o CNPJ. ';  
                    
                    if (xhr.status === 404) {  
                        mensagem += 'CNPJ não encontrado na base de dados.';  
                    } else if (xhr.status === 429) {  
                        mensagem += 'Limite de consultas excedido. Tente novamente mais tarde.';  
                    } else {  
                        mensagem += 'Erro interno na consulta. Tente novamente.';  
                    }  
                    
                    Swal.fire({  
                        icon: 'error',  
                        title: 'Erro na consulta',  
                        text: mensagem,  
                        confirmButtonColor: '#3085d6'  
                    });  
                },  
                complete: function() {  
                    // Esconder indicador de consulta  
                    $('#consultaIndicator').hide();  
                }  
            });  
        }  

        $(document).ready(function() {  
            // Inicializar o CKEditor para o campo corpo (motivos de devolução)  
            CKEDITOR.replace('corpo', {  
                extraPlugins: 'htmlwriter',  
                allowedContent: true,  
                filebrowserUploadUrl: '/uploader/upload.php',  
                filebrowserUploadMethod: 'form',  
                scayt_autoStartup: true,  
                scayt_sLang: 'pt_BR'  
            });  
            
            // Inicializar o CKEditor para o campo prazo_cumprimento  
            CKEDITOR.replace('prazo_cumprimento', {  
                extraPlugins: 'htmlwriter',  
                allowedContent: true,  
                filebrowserUploadUrl: '/uploader/upload.php',  
                filebrowserUploadMethod: 'form',  
                scayt_autoStartup: true,  
                scayt_sLang: 'pt_BR',  
                height: 150 // Altura menor que o editor de Motivos  
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
            
            // Configuração da máscara CPF/CNPJ com validação  
            var cpfMascara = '000.000.000-000';  
            var cnpjMascara = '00.000.000/0000-00';  

            var options = {  
                onKeyPress: function(input, e, field, options) {  
                    var masks = [cpfMascara, cnpjMascara];  
                    var mask = (input.length > 14) ? masks[1] : masks[0];  
                    $('#cpf_cnpj').mask(mask, options);  
                }  
            };  
            
            $('#cpf_cnpj').mask(cpfMascara, options);  
            
            // Se já tiver um valor, aplicar a máscara apropriada  
            var valorAtual = $('#cpf_cnpj').val().replace(/[^\d]/g, '');  
            if (valorAtual.length > 0) {  
                if (valorAtual.length > 11) {  
                    $('#cpf_cnpj').mask(cnpjMascara);  
                } else {  
                    $('#cpf_cnpj').mask(cpfMascara);  
                }  
            }  
            
            // Evento para consultar o CNPJ ao clicar no botão  
            $('#consultarCpfCnpj').on('click', function() {  
                let documento = $('#cpf_cnpj').val().replace(/[^\d]/g, '');  
                
                // Apenas consultar se for um CNPJ (14 dígitos)  
                if (documento.length === 14) {  
                    consultarCnpj();  
                } else {  
                    Swal.fire({  
                        icon: 'info',  
                        title: 'Atenção',  
                        text: 'A consulta automática está disponível apenas para CNPJ.',  
                        confirmButtonColor: '#3085d6'  
                    });  
                }  
            });  
            
            // Consultar automaticamente ao sair do campo se for um CNPJ válido  
            $('#cpf_cnpj').on('blur', function() {  
                var valor = $(this).val().replace(/[^\d]/g, '');  
                
                // Se for um CNPJ completo (14 dígitos)  
                if (valor.length === 14) {  
                    if (!validarCNPJ(valor)) {  
                        var campoInput = $(this); // Armazena referência ao campo  
                        Swal.fire({  
                            icon: 'error',  
                            title: 'CNPJ Inválido',  
                            text: 'Por favor, insira um CNPJ válido.',  
                            confirmButtonColor: '#3085d6',  
                            willClose: function() {  
                                // Limpa o campo quando o alerta for fechado  
                                campoInput.val('');  
                            }  
                        });  
                    } else {  
                        // Se for válido e o campo de apresentante estiver vazio, consulta automaticamente  
                        if ($('#apresentante').val() === '') {  
                            consultarCnpj();  
                        }  
                    }  
                }  
            });  
            
            // Validar CPF/CNPJ antes do envio do formulário (para formatos)  
            $('#notaForm').on('submit', function(e) {  
                console.log("Formulário sendo enviado. Validando CPF/CNPJ...");  
                var cpfCnpjValue = $('#cpf_cnpj').val();  
                
                if (cpfCnpjValue && cpfCnpjValue.replace(/[^\d]/g, '').length > 0) {  
                    console.log("Valor do CPF/CNPJ:", cpfCnpjValue);  
                    console.log("Valor após remoção de caracteres não-numéricos:", cpfCnpjValue.replace(/[^\d]/g, ''));  
                    
                    var isValid = validarCpfCnpj(cpfCnpjValue);  
                    console.log("CPF/CNPJ é válido?", isValid);  
                    
                    if (!isValid) {  
                        e.preventDefault();  
                        e.stopPropagation();  
                        console.log("Formulário bloqueado devido a CPF/CNPJ inválido");  
                        
                        Swal.fire({  
                            icon: 'error',  
                            title: 'CPF/CNPJ Inválido',  
                            text: 'Por favor, insira um CPF ou CNPJ válido.',  
                            confirmButtonColor: '#3085d6',  
                            willClose: function() {  
                                // Limpa o campo quando o alerta for fechado  
                                $('#cpf_cnpj').val('');  
                            }  
                        });  
                        return false;  
                    }  
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
            $('#data, #data_protocolo').on('change', function() {  
                // Certifique-se de que há um valor antes de validar  
                if ($(this).val()) {  
                    validateDate(this);  
                }  
            });  
        });  
    
let dataTable = null;  

window.carregarNotas = function() {  
    console.log('Iniciando carregamento de notas devolutivas');  
    
    if (dataTable) {  
        dataTable.destroy();  
    }  
    
    $('#notasTableBody').html(`  
        <tr>  
            <td colspan="5" class="text-center">  
                <div class="spinner-border text-primary" role="status">  
                    <span class="sr-only">Carregando...</span>  
                </div>  
            </td>  
        </tr>  
    `);  
    
    $.ajax({  
        url: 'listar_notas_devolutivas.php',  
        method: 'GET',  
        dataType: 'json',  
        success: function(response) {  
            console.log('Dados recebidos:', response);  
            
            if (!response.data || !Array.isArray(response.data)) {  
                $('#notasTableBody').html(`  
                    <tr>  
                        <td colspan="5" class="text-center">Nenhuma nota devolutiva encontrada</td>  
                    </tr>  
                `);  
                return;  
            }  
            
            let html = '';  
            response.data.forEach(function(nota) {  
                const numero = $('<div>').text(nota.numero).html();  
                const apresentante = $('<div>').text(nota.apresentante).html();  
                const titulo = $('<div>').text(nota.titulo).html();  
                
                html += `  
                    <tr>  
                        <td><div class="cell-content">${numero}</div></td>  
                        <td><div class="cell-content">${nota.data}</div></td>  
                        <td><div class="cell-content" title="${apresentante}">${apresentante.length > 40 ? apresentante.substring(0, 37) + "..." : apresentante}</div></td>  
                        <td><div class="cell-content" title="${titulo}">${titulo.length > 65 ? titulo.substring(0, 62) + "..." : titulo}</div></td>  
                        <td>  
                            <div class="action-buttons">  
                                <button class="btn-action btn-view" onclick="verCorpo('${numero}')" title="Visualizar nota">  
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
            
            $('#notasTableBody').html(html);  
            initializeDataTable();  
        },  
        error: function(xhr, status, error) {  
            console.error('Erro na requisição:', error);  
            $('#notasTableBody').html(`  
                <tr>  
                    <td colspan="5" class="text-center text-danger">  
                        <i class="fas fa-exclamation-triangle me-2"></i>  
                        Erro ao carregar notas devolutivas. Por favor, tente novamente.  
                    </td>  
                </tr>  
            `);  
        }  
    });  
};  

function initializeDataTable() {  
    dataTable = $('#notasTable').DataTable({  
        responsive: true,  
        language: {  
            url: '../style/Portuguese-Brasil.json'  
        },  
        pageLength: 15,  
        order: [[1, 'asc']],  
        columnDefs: [  
            { orderable: false, targets: 4 }  
        ],  
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +  
             "<'row'<'col-sm-12'tr>>" +  
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",  
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, "Todos"]]  
    });  
}  

window.verCorpo = function(numero) {  
    $.ajax({  
        url: 'get_nota_details.php',  
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
                title: `Nota Devolutiva ${numero}`,  
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
                text: 'Não foi possível carregar os detalhes da nota devolutiva'  
            });  
        }  
    });  
};  

window.usarModelo = function(numero) {  
    $.ajax({  
        url: 'get_nota_details.php',  
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
                // Limpar os campos que não devem ser preenchidos  
                $('#apresentante').val('');  
                $('#cpf_cnpj').val('');  
                $('#protocolo').val('');  
                $('#data_protocolo').val('');  
                $('#dados_complementares').val('');  
                
                // Preencher apenas os campos necessários  
                $('#titulo').val(response.titulo || '');  
                $('#origem_titulo').val(response.origem_titulo || '');  
                
                if (typeof CKEDITOR !== 'undefined') {  
                    if (CKEDITOR.instances.corpo) {  
                        CKEDITOR.instances.corpo.setData(response.corpo || '');  
                    }  
                    
                    if (CKEDITOR.instances.prazo_cumprimento) {  
                        CKEDITOR.instances.prazo_cumprimento.setData(response.prazo_cumprimento || '');  
                    }  
                }  
                
                // Fecha o modal do bootstrap  
                $('#notasAnterioresModal').modal('hide');  
                
                Swal.close();  
                setTimeout(() => {  
                    Swal.fire({  
                        icon: 'success',  
                        title: 'Modelo carregado!',  
                        text: 'O conteúdo parcial da nota devolutiva foi carregado com sucesso.',  
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
                text: 'Não foi possível carregar o modelo da nota devolutiva: ' + error  
            });  
        }  
    });  
};  
</script>  
<?php  
include(__DIR__ . '/../rodape.php');  
?>  
</body>  
</html>