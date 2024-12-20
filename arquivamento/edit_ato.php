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
    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/sweetalert2.min.css">
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
            <h4>Edição de Arquivamento</h4>
            <div class="buttons-right">
                <button style="font-size: 14px; margin-bottom: 5px!important; margin-left: 10px;" type="button" class="btn btn-primary" id="capaArquivamentoButton">
                    <i class="fa fa-print" aria-hidden="true"></i> Capa de Arquivamento
                </button>
                <button style="font-size: 14px; margin-bottom: 5px!important; margin-left: 10px;" type="button" class="btn btn-success" onclick="window.location.href='cadastro.php'">
                    <i class="fa fa-plus" aria-hidden="true"></i> Criar Arquivamento
                </button>
                <button style=" font-size: 14px; margin-bottom: 5px!important; margin-left: 10px;" type="button" class="btn btn-secondary btn-sm" onclick="window.location.href='index.php'">
                    <i class="fa fa-search" aria-hidden="true"></i> Pesquisar Arquivamentos
                </button>
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
            <div class="form-group">
                <label for="file-input">Anexar arquivos:</label>
                <input type="file" id="file-input" name="file-input[]" multiple class="form-control">
            </div>
            <button type="submit" style="margin-top:0px;margin-bottom: 30px" class="btn btn-primary w-100">Salvar</button>
        </form>
        <hr>
        <h4>Anexos</h4>
        <div id="file-list"></div><br>

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

<script src="../script/bootstrap.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script src="../script/jquery.dataTables.min.js"></script>
<script src="../script/dataTables.bootstrap4.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>

            $(document).ready(function() {
            // Adiciona o evento de clique ao botão
            $('#capaArquivamentoButton').on('click', function() {
                // Faz a requisição para o JSON
                $.ajax({
                    url: '../style/configuracao.json',
                    dataType: 'json',
                    cache: false, // Desabilita o cache
                    success: function(data) {
                        const arquivoId = '<?php echo $arquivo_id; ?>'; // Pega o arquivo_id via PHP
                        let url = '';

                        // Verifica o valor do "timbrado" e ajusta a URL
                        if (data.timbrado === 'S') {
                            url = 'capa_arquivamento.php?id=' + arquivoId;
                        } else if (data.timbrado === 'N') {
                            url = 'capa-arquivamento.php?id=' + arquivoId;
                        }

                        // Abre a URL correspondente em uma nova aba
                        window.open(url, '_blank');
                    },
                    error: function() {
                        alert('Erro ao carregar o arquivo de configuração.');
                    }
                });
            });
        });

    $(document).ready(function() {
        var filesToRemove = []; // Array to store files to be removed

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
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'CPF/CNPJ inválido.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            if (nome || $('#partes-envolvidas tr').length > 0) {
                var row = '<tr>' +
                    '<td>' + (cpf || '') + '</td>' +
                    '<td>' + (nome || '') + '</td>' +
                    '<td><button type="button" class="btn btn-delete btn-sm remover-parte"><i class="fa fa-trash" aria-hidden="true"></i></button></td>' +
                    '</tr>';
                $('#partes-envolvidas').append(row);
                $('#cpf').val('');
                $('#nome').val('');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Preencha o nome.',
                    confirmButtonText: 'OK'
                });
            }
        });

        // Remover parte envolvida com confirmação
        $(document).on('click', '.remover-parte', function(e) {
            e.preventDefault(); // Previne a ação padrão do botão
            var row = $(this).closest('tr'); // Captura a linha da tabela que contém o botão

            Swal.fire({
                title: 'Você tem certeza?',
                text: 'Deseja realmente remover esta parte envolvida?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    row.remove(); // Executa a remoção somente após a confirmação
                    Swal.fire({
                        icon: 'success',
                        title: 'Removido!',
                        text: 'A parte envolvida foi removida com sucesso.',
                        confirmButtonText: 'OK'
                    });
                }
            });
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

        // Remover anexo com confirmação
        $(document).on('click', '.remover-anexo', function() {
            var button = $(this); // Referência ao botão que foi clicado
            var anexo = button.data('file'); // Obtém o nome do arquivo a ser removido

            // Exibir confirmação com SweetAlert2
            Swal.fire({
                title: 'Você tem certeza?',
                text: 'Deseja realmente remover este anexo?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Remover o anexo somente após a confirmação
                    button.closest('.attachment-item').remove();
                    filesToRemove.push(anexo); // Adicionar o anexo à lista de remoção

                    // Exibir mensagem de sucesso
                    Swal.fire({
                        icon: 'success',
                        title: 'Removido!',
                        text: 'O anexo foi removido com sucesso.',
                        confirmButtonText: 'OK'
                    });
                }
            });
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
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Adicione pelo menos uma parte envolvida.',
                    confirmButtonText: 'OK'
                });
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
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Dados salvos com sucesso.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = '';
                    });
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Erro ao salvar os dados.',
                        confirmButtonText: 'OK'
                    });
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

                        // Exibir mensagem de sucesso com SweetAlert2
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso',
                            text: data.success,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });

                    } else if (data.error) {
                        // Exibir mensagem de erro com SweetAlert2
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            html: data.error + '<br><button id="verificarIpBtn" class="btn btn-primary mt-2">Verificar IP do Selador</button>',
                            didOpen: () => {
                                $('#verificarIpBtn').click(function() {
                                    // Desabilitar o botão e mostrar mensagem
                                    $(this).prop('disabled', true).text('Verificando... aguarde');

                                    $.ajax({
                                        url: 'verificar_ip.php',
                                        type: 'GET',
                                        success: function(response) {
                                            var ipData = JSON.parse(response);
                                            if (ipData.sucesso) {
                                                Swal.fire({
                                                    icon: 'success',
                                                    title: 'IP Verificado',
                                                    html: ipData.sucesso + '<br><button id="salvarIpBtn" class="btn btn-primary mt-2">Salvar</button>',
                                                    didOpen: () => {
                                                        $('#salvarIpBtn').click(function() {
                                                            $.ajax({
                                                                url: 'atualizar_ip.php',
                                                                type: 'POST',
                                                                data: { ip: ipData.ip },
                                                                success: function(updateResponse) {
                                                                    Swal.fire({
                                                                        icon: 'success',
                                                                        title: 'IP Atualizado',
                                                                        text: updateResponse,
                                                                        confirmButtonText: 'OK'
                                                                    });
                                                                },
                                                                error: function() {
                                                                    Swal.fire({
                                                                        icon: 'error',
                                                                        title: 'Erro',
                                                                        text: 'Erro ao atualizar o IP.',
                                                                        confirmButtonText: 'OK'
                                                                    });
                                                                }
                                                            });
                                                        });
                                                    }
                                                });
                                            } else {
                                                Swal.fire({
                                                    icon: 'error',
                                                    title: 'Erro',
                                                    text: ipData.erro,
                                                    confirmButtonText: 'OK'
                                                });
                                            }
                                        },
                                        error: function() {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Erro',
                                                text: 'Erro ao verificar o IP.',
                                                confirmButtonText: 'OK'
                                            });
                                        }
                                    });
                                });
                            }
                        });
                    }
                },
                error: function() {
                    // Exibir mensagem de erro com SweetAlert2
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao solicitar o selo.',
                        confirmButtonText: 'OK'
                    });

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
        $('#data_ato').on('change', function() {
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
