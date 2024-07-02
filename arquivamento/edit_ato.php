<?php
include(__DIR__ . '/session_check.php');
include(__DIR__ . '/db_connection.php');
checkSession();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Editar Arquivos</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        /* CSS para estilizar o modal */
        .modal-dialog {
            max-width: 400px; /* Define a largura máxima do modal */
            margin: 30vh auto; /* Centraliza o modal verticalmente na tela */
        }

        .modal-content {
            border-radius: 10px; /* Torna os cantos do modal arredondados */
            padding: 20px; /* Adiciona padding ao conteúdo do modal */
            box-shadow: 0 5px 15px rgba(0,0,0,.5); /* Adiciona uma sombra ao redor do modal */
        }

        .modal-header, .modal-footer {
            border: none; /* Remove a borda da parte superior e inferior do modal */
            padding: 10px 20px; /* Ajusta o padding da parte superior e inferior do modal */
        }

        .modal-header .close {
            margin-top: -10px; /* Ajusta a posição do botão de fechar */
        }

        .modal-title {
            font-size: 18px; /* Define o tamanho da fonte do título */
            font-weight: bold; /* Torna o título em negrito */
        }

        .modal-body {
            font-size: 16px; /* Define o tamanho da fonte do corpo do modal */
            padding: 10px 0; /* Ajusta o padding do corpo do modal */
            text-align: center; /* Centraliza o texto do corpo do modal */
        }

        .modal-footer {
            display: flex; /* Usa flexbox para alinhar os itens no footer */
            justify-content: center; /* Centraliza os itens no footer */
        }

        /* Alinhamento dos botões ao lado do título */
        .title-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .buttons-right {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');

// Obter o nome completo do usuário logado
$usuarioLogado = $_SESSION['nome_completo'];

// Verificar se já existe um selo para o arquivo
$arquivo_id = $_GET['id'];
$selos_arquivamentos = $conn->prepare("SELECT selos.* FROM selos_arquivamentos JOIN selos ON selos_arquivamentos.selo_id = selos.id WHERE selos_arquivamentos.arquivo_id = ?");
$selos_arquivamentos->bind_param("i", $arquivo_id);
$selos_arquivamentos->execute();
$selos_arquivamentos_result = $selos_arquivamentos->get_result();
$selo_existe = $selos_arquivamentos_result->num_rows > 0;
$selo_html = '';
if ($selo_existe) {
    $selo = $selos_arquivamentos_result->fetch_assoc();
    $selo_html = '<div style="border: 1px solid #ddd; padding: 10px; margin-top: 20px;">';
    $selo_html .= '<table>';
    $selo_html .= '<tr><td><img src="data:image/png;base64,' . $selo['qr_code'] . '" alt="QR Code"></td>';
    $selo_html .= '<td>';
    $selo_html .= '<p style="text-align: center;font-size: 14px;"><strong>Poder Judiciário – TJMA</strong><br><strong>Selo: ' . $selo['numero_selo'] . '</strong></p><p style="text-align: justify;font-size: 14px;margin-top: -12px;">' . $selo['texto_selo'] . '</p>';
    $selo_html .= '</td></tr>';
    $selo_html .= '</table>';
    $selo_html .= '</div>';
}
$selos_arquivamentos->close();
?>

<div id="main" class="main-content">
    <div class="container">
        <div class="title-buttons">
            <h3>Editar Arquivo - Atualização de Registro</h3>
            <div class="buttons-right">
                <a href="capa-arquivamento.php?id=<?php echo $arquivo_id; ?>" target="_blank" class="btn btn-primary"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Capa de Arquivamento</a>
                <a href="cadastro.php" class="btn btn-success"><i class="fa fa-plus-circle" aria-hidden="true"></i> Criar Arquivamento</a>
            </div>
        </div>
        <hr>
        <form id="ato-form">
            <input type="hidden" id="ato-id" name="id">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="atribuicao">Atribuição:</label>
                    <select id="atribuicao" name="atribuicao" class="form-control" required>
                        <option value="">Selecione</option>
                        <option value="Registro Civil">Registro Civil</option>
                        <option value="Registro de Imóveis">Registro de Imóveis</option>
                        <option value="Registro de Títulos e Documentos">Registro de Títulos e Documentos</option>
                        <option value="Registro Civil das Pessoas Jurídicas">Registro Civil das Pessoas Jurídicas</option>
                        <option value="Notas">Notas</option>
                        <option value="Protesto">Protesto</option>
                        <option value="Contratos Marítimos">Contratos Marítimos</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="categoria">Categoria:</label>
                    <select id="categoria" name="categoria" class="form-control" required></select>
                </div>
                <div class="form-group col-md-3">
                    <label for="data_ato">Data do Ato:</label>
                    <input type="date" class="form-control" id="data_ato" name="data_ato" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="livro">Livro:</label>
                    <input type="text" class="form-control" id="livro" name="livro">
                </div>
                <div class="form-group col-md-3">
                    <label for="folha">Folha:</label>
                    <input type="text" class="form-control" id="folha" name="folha">
                </div>
                <div class="form-group col-md-3">
                    <label for="termo">Termo/Ordem:</label>
                    <input type="text" class="form-control" id="termo" name="termo">
                </div>
                <div class="form-group col-md-3">
                    <label for="protocolo">Protocolo:</label>
                    <input type="text" class="form-control" id="protocolo" name="protocolo">
                </div>
                <div class="form-group col-md-3">
                    <label for="matricula">Matrícula:</label>
                    <input type="text" class="form-control" id="matricula" name="matricula">
                </div>
            </div>
            <h4>Parte Envolvida</h4>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <input type="text" class="form-control" id="cpf" placeholder="CPF/CNPJ">
                </div>
                <div class="form-group col-md-6">
                    <input type="text" class="form-control" id="nome" placeholder="Nome">
                </div>
            </div>
            <button type="button" class="btn btn-secondary" id="adicionar-parte">Adicionar Parte</button>
            <table class="table mt-3">
                <thead>
                    <tr>
                        <th>CPF/CNPJ</th>
                        <th>Nome</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody id="partes-envolvidas">
                </tbody>
            </table>
            <h4>Descrição e Detalhes</h4>
            <div class="form-group">
                <label for="descricao">Descrição e Detalhes:</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
            </div>
            <h4>Anexos</h4>
            <div class="form-group">
                <label for="file-input">Anexar arquivos:</label>
                <input type="file" id="file-input" name="file-input[]" multiple class="form-control">
            </div>
            <div id="file-list"></div><br>
            <button type="submit" style="margin-top:0px;margin-bottom: 30px" class="btn btn-primary w-100">Salvar</button>
        </form>

<!-- Formulário de Solicitação de Selo -->
<?php if (!$selo_existe): ?>
<h4>Solicitar Selo</h4>
<form method="post" id="selo-form" class="form-row">
    <input type="hidden" name="numeroControle" value="<?php echo $arquivo_id; ?>">
    <input type="hidden" name="livro" id="livro_selo" value="">
    <input type="hidden" name="folha" id="folha_selo" value="">
    <input type="hidden" name="termo" id="termo_selo" value="">
    <div class="form-group col-md-7">
        <label for="ato">Ato:</label>
        <select id="ato" name="ato" class="form-control" required>
            <option value="13.30">13.30 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,25</option>
            <option value="14.12">14.12 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,25</option>
            <option value="15.22">15.22 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,25</option>
            <option value="16.39">16.39 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,25</option>
            <option value="17.9">17.9 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,12</option>
            <option value="18.13">18.13 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,25</option>
        </select>
    </div>

    <div class="form-group col-md-3" style="display:none;">
        <label for="escrevente">Escrevente:</label>
        <input type="text" id="escrevente" name="escrevente" class="form-control" required readonly value="<?php echo utf8_encode($usuarioLogado); ?>">                
    </div>

    <div class="form-group col-md-6" style="display:none;">
        <label for="partes">Partes:</label>
        <input id="partes" name="partes" class="form-control" required></input>
    </div>

    <div class="form-group col-md-2">
        <label for="quantidade">Quantidade:</label>
        <input type="number" id="quantidade" name="quantidade" class="form-control" required>
    </div>

    <div class="form-group col-md-3">
        <button type="submit" id="solicitar-selo-btn" class="btn btn-primary" style="margin-top: 30px;width: 100%;">Solicitar Selo</button>
    </div>
</form>
<?php else: ?>
<p>O selo já foi gerado para este arquivo.</p>
<?php echo $selo_html; ?>
<?php endif; ?>

<script>
    $(document).ready(function() {
        // Adicionar valores de livro, folha e termo ao formulário de solicitação de selo
        $('#solicitar-selo-btn').click(function() {
            $('#livro_selo').val($('#livro').val());
            $('#folha_selo').val($('#folha').val());
            $('#termo_selo').val($('#termo').val());
        });
    });
</script>

    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalLabel"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="messageModalBody">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
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

    $(document).ready(function() {
        var filesToRemove = []; // Array to store files to be removed

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

        // Máscara para CPF e CNPJ
        $(document).ready(function() {
                $('#cpf').on('blur', function() {
                    var value = $(this).val().replace(/\D/g, '');
                    
                    if (value.length === 11) {
                        $(this).mask('000.000.000-00', { reverse: true });
                    } else if (value.length === 14) {
                        $(this).mask('00.000.000/0000-00', { reverse: true });
                    } else {
                        $(this).unmask();
                    }
                });
            });

        // Validador de CPF e CNPJ
        function validarCPF_CNPJ(value) {
            value = value.replace(/[^\d]+/g, '');
            if (value.length === 11) {
                // Validar CPF
                if (/^(\d)\1{10}$/.test(value)) return false;
                var soma = 0, resto;
                for (var i = 1; i <= 9; i++) soma = soma + parseInt(value.substring(i - 1, i)) * (11 - i);
                resto = (soma * 10) % 11;
                if ((resto === 10) || (resto === 11)) resto = 0;
                if (resto !== parseInt(value.substring(9, 10))) return false;
                soma = 0;
                for (var i = 1; i <= 10; i++) soma = soma + parseInt(value.substring(i - 1, i)) * (12 - i);
                resto = (soma * 10) % 11;
                if ((resto === 10) || (resto === 11)) resto = 0;
                if (resto !== parseInt(value.substring(10, 11))) return false;
                return true;
            } else if (value.length === 14) {
                // Validar CNPJ
                if (/^(\d)\1{13}$/.test(value)) return false;
                var tamanho = value.length - 2;
                var numeros = value.substring(0, tamanho);
                var digitos = value.substring(tamanho);
                var soma = 0;
                var pos = tamanho - 7;
                for (var i = tamanho; i >= 1; i--) {
                    soma += numeros.charAt(tamanho - i) * pos--;
                    if (pos < 2) pos = 9;
                }
                var resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
                if (resultado !== parseInt(digitos.charAt(0))) return false;
                tamanho = tamanho + 1;
                numeros = value.substring(0, tamanho);
                soma = 0;
                pos = tamanho - 7;
                for (var i = tamanho; i >= 1; i--) {
                    soma += numeros.charAt(tamanho - i) * pos--;
                    if (pos < 2) pos = 9;
                }
                resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
                if (resultado !== parseInt(digitos.charAt(1))) return false;
                return true;
            }
            return false;
        }

        // Adicionar parte envolvida
        $('#adicionar-parte').click(function() {
            var cpf = $('#cpf').val();
            var nome = $('#nome').val();
            if (cpf && !validarCPF_CNPJ(cpf)) {
                alert('CPF/CNPJ inválido.');
                return;
            }
            if (nome || $('#partes-envolvidas tr').length > 0) {
                var row = '<tr>' +
                    '<td>' + (cpf || '') + '</td>' +
                    '<td>' + (nome || '') + '</td>' +
                    '<td><button class="btn btn-delete btn-sm remover-parte"><i class="fa fa-trash" aria-hidden="true"></i></button></td>' +
                    '</tr>';
                $('#partes-envolvidas').append(row);
                $('#cpf').val('');
                $('#nome').val('');
            } else {
                alert('Preencha o nome.');
            }
        });

        // Remover parte envolvida
        $(document).on('click', '.remover-parte', function() {
            $(this).closest('tr').remove();
        });

        // Carregar categorias do JSON
        $.ajax({
            url: 'categorias/categorias.json',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                var categorias = response;
                var categoriaSelect = $('#categoria');
                categorias.forEach(function(categoria) {
                    var option = $('<option></option>').attr('value', categoria).text(categoria);
                    categoriaSelect.append(option);
                });
            }
        });

        // Carregar os dados do ato para edição
        var atoId = new URLSearchParams(window.location.search).get('id');
        $.ajax({
            url: 'get_ato.php',
            method: 'GET',
            data: { id: atoId },
            success: function(response) {
                var ato = JSON.parse(response);
                if (ato) {
                    $('#ato-id').val(ato.id);
                    $('#atribuicao').val(ato.atribuicao);
                    $('#categoria').val(ato.categoria);
                    $('#data_ato').val(ato.data_ato);
                    $('#livro').val(ato.livro);
                    $('#folha').val(ato.folha);
                    $('#termo').val(ato.termo);
                    $('#protocolo').val(ato.protocolo);
                    $('#matricula').val(ato.matricula);
                    $('#descricao').val(ato.descricao);

                    var partesEnvolvidas = [];
                    ato.partes_envolvidas.forEach(function(parte) {
                        var row = '<tr>' +
                            '<td>' + parte.cpf + '</td>' +
                            '<td>' + parte.nome + '</td>' +
                            '<td><button class="btn btn-delete btn-sm remover-parte"><i class="fa fa-trash" aria-hidden="true"></i></button></td>' +
                            '</tr>';
                        $('#partes-envolvidas').append(row);
                        partesEnvolvidas.push(parte.nome);
                    });

                    // Preencher o campo Partes no formulário de solicitação de selo
                    $('#partes').val(partesEnvolvidas.join(", "));

                    var fileList = $('#file-list');
                    fileList.empty();
                    ato.anexos.forEach(function(anexo, index) {
                        var row = '<div class="attachment-item">' +
                            '<span class="attachment-id">' + (index + 1) + '</span>' +
                            '<span class="attachment-name">' + anexo.split('/').pop() + '</span>' +
                            '<span class="attachment-actions">' +
                                '<button class="btn btn-info btn-sm visualizar-anexo" data-file="' + anexo + '"><i class="fa fa-eye" aria-hidden="true"></i></button>' +
                                '<button class="btn btn-delete btn-sm remover-anexo" data-file="' + anexo + '"><i class="fa fa-trash" aria-hidden="true"></i></button>' +
                            '</span>' +
                            '</div>';
                        fileList.append(row);
                    });

                    // Definir o valor do campo "Ato" automaticamente
                    var atribuicao = ato.atribuicao;
                    var atoField = $('#ato');
                    switch (atribuicao) {
                        case 'Registro Civil':
                            atoField.val('14.12');
                            break;
                        case 'Registro de Imóveis':
                            atoField.val('16.39');
                            break;
                        case 'Registro de Títulos e Documentos':
                        case 'Registro Civil das Pessoas Jurídicas':
                            atoField.val('15.22');
                            break;
                        case 'Notas':
                            atoField.val('13.30');
                            break;
                        case 'Protesto':
                            atoField.val('17.9');
                            break;
                        case 'Contratos Marítimos':
                            atoField.val('18.13');
                            break;
                        default:
                            atoField.val('');
                            break;
                    }
                } else {
                    alert('Ato não encontrado.');
                }
            }
        });

        // Remover anexo
        $(document).on('click', '.remover-anexo', function() {
            var anexo = $(this).data('file');
            $(this).closest('.attachment-item').remove();
            filesToRemove.push(anexo); // Add the file to the remove list
        });

        // Visualizar anexo
        $(document).on('click', '.visualizar-anexo', function(e) {
            e.preventDefault(); // Prevent the form from submitting
            var anexo = $(this).data('file');
            window.open(anexo, '_blank');
        });

        // Enviar formulário
        $('#ato-form').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);

            // Verificar se há pelo menos uma parte envolvida
            if ($('#partes-envolvidas tr').length === 0) {
                alert('Adicione pelo menos uma parte envolvida.');
                return;
            }

            // Adicionar partes envolvidas
            var partesEnvolvidas = [];
            $('#partes-envolvidas tr').each(function() {
                var cpf = $(this).find('td').eq(0).text();
                var nome = $(this).find('td').eq(1).text();
                partesEnvolvidas.push({ cpf: cpf, nome: nome });
            });
            formData.append('partes_envolvidas', JSON.stringify(partesEnvolvidas));

            // Add files to be removed
            formData.append('files_to_remove', JSON.stringify(filesToRemove));

            // Enviar dados
            $.ajax({
                url: 'update_ato.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    alert('Dados salvos com sucesso');
                    window.location.href = '';
                },
                error: function() {
                    alert('Erro ao salvar os dados');
                }
            });
        });

        // Submeter formulário de solicitação de selo
        $('#selo-form').on('submit', function(e) {
            e.preventDefault();

            // Desativar o botão "Solicitar Selo"
            $('#solicitar-selo-btn').prop('disabled', true);

            $.ajax({
                url: 'selos_arquivamentos.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    var data = JSON.parse(response);
                    if (data.success) {
                        // Exibir selo gerado e esconder formulário de solicitação de selo
                        $('#selo-form').hide();
                        $('#selo-form').after(data.html);

                        // Exibir modal com mensagem de sucesso
                        $('#messageModalLabel').text('Sucesso');
                        $('#messageModalBody').text(data.success);
                        $('#messageModal').modal('show');

                        // Atualizar a página ao fechar o modal
                        $('#messageModal').on('hidden.bs.modal', function () {
                            location.reload();
                        });
                    } else if (data.error) {
                        // Exibir modal com mensagem de erro
                        $('#messageModalLabel').text('Erro');
                        $('#messageModalBody').html(data.error + '<br><button id="verificarIpBtn" class="btn btn-primary">Verificar IP do Selador</button>');
                        $('#messageModal').modal('show');

                        $('#verificarIpBtn').click(function() {
                            $.ajax({
                                url: 'verificar_ip.php',
                                type: 'GET',
                                success: function(response) {
                                    var ipData = JSON.parse(response);
                                    if (ipData.sucesso) {
                                        $('#messageModalBody').html(ipData.sucesso + '<br><button id="salvarIpBtn" class="btn btn-primary">Salvar</button>');
                                        $('#salvarIpBtn').click(function() {
                                            $.ajax({
                                                url: 'atualizar_ip.php',
                                                type: 'POST',
                                                data: { ip: ipData.ip },
                                                success: function(updateResponse) {
                                                    $('#messageModalBody').html(updateResponse);
                                                },
                                                error: function() {
                                                    $('#messageModalBody').text('Erro ao atualizar o IP.');
                                                }
                                            });
                                        });
                                    } else {
                                        $('#messageModalBody').text(ipData.erro);
                                    }
                                },
                                error: function() {
                                    $('#messageModalBody').text('Erro ao verificar o IP.');
                                }
                            });
                        });
                    }
                },
                error: function() {
                    // Exibir modal com mensagem de erro
                    $('#messageModalLabel').text('Erro');
                    $('#messageModalBody').text('Erro ao solicitar o selo.');
                    $('#messageModal').modal('show');

                    // Reativar o botão "Solicitar Selo"
                    $('#solicitar-selo-btn').prop('disabled', false);
                }
            });
        });

        // Esconder o formulário de solicitação de selo se o selo já foi gerado
        if (typeof seloGerado !== 'undefined' && seloGerado) {
            $('#selo-form').hide();
        }
    });
</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
