<?php
include(__DIR__ . '/session_check.php');
checkSession();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Cadastro de Arquivamento</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Cadastro de Arquivamento - Inserir Novo Registro</h3>
            <form id="ato-form">
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
                        <select id="categoria" name="categoria" class="form-control" required>
                            <option value="">Selecione</option>
                        </select>
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
                <button type="submit" class="btn btn-primary w-100" style="margin-top:0px; margin-bottom: 30px;">Salvar e Concluir</button>
            </form>
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
            $('#cpf').on('input', function() {
                var value = $(this).val().replace(/\D/g, ''); // Remove tudo que não é dígito
                if (value.length <= 11) {
                    $(this).mask('000.000.000-00', { reverse: true });
                } else {
                    $(this).mask('00.000.000/0000-00', { reverse: true });
                }
            });

            // Validador de CPF e CNPJ
            function validarCPF_CNPJ(value) {
                if (value.length === 14) {
                    // Validar CPF
                    value = value.replace(/[^\d]+/g, '');
                    if (value.length !== 11 || /^(\d)\1{10}$/.test(value)) return false;
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
                } else if (value.length === 18) {
                    // Validar CNPJ
                    value = value.replace(/[^\d]+/g, '');
                    if (value.length !== 14) return false;
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
                    categoriaSelect.append('<option value="">Selecione</option>');
                    categorias.forEach(function(categoria) {
                        var option = $('<option></option>').attr('value', categoria).text(categoria);
                        categoriaSelect.append(option);
                    });
                }
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

                // Enviar dados
                $.ajax({
                    url: 'save_ato.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        alert('Dados salvos com sucesso');
                        $('#ato-form')[0].reset(); // Limpar o formulário após salvar
                        $('#partes-envolvidas').empty(); // Limpar as partes envolvidas
                    },
                    error: function() {
                        alert('Erro ao salvar os dados');
                    }
                });
            });
        });
    </script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
