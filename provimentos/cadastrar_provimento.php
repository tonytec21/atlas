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
    <title>Cadastrar Provimento e Resolução</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/toastr.min.css">
    <style>
        .btn-adicionar {
            height: 38px;
            line-height: 24px;
            margin-left: 10px;
        }

        .modal-content {
            border-radius: 10px;
        }

        .modal-dialog {
            max-width: 30%;
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

        .custom-file-input ~ .custom-file-label::after {
            content: "Escolher";
        }

        .custom-file-label {
            border-radius: 0.25rem;
            padding: 0.5rem 1rem;
            background-color: #fff;
            color: #777;
            cursor: pointer;
        }

        .custom-file-input:focus ~ .custom-file-label {
            outline: -webkit-focus-ring-color auto 1px;
            outline-offset: -2px;
        }
    </style>
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Cadastrar Provimento e Resolução</h3>
            <hr>
            <form id="cadastroForm" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="tipo">Tipo:</label>
                        <select class="form-control" id="tipo" name="tipo" required>
                            <option value="Resolução">Resolução</option>        
                            <option value="Provimento">Provimento</option>    
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="numero_provimento">Número:</label>
                        <input type="text" class="form-control" id="numero_provimento" name="numero_provimento" required pattern="\d*" title="Apenas números são permitidos">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="origem">Origem:</label>
                        <select class="form-control" id="origem" name="origem" required>
                            <option value="CGJ/MA">CGJ/MA</option>        
                            <option value="CNJ">CNJ</option>    
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="data_provimento">Data:</label>
                        <input type="date" class="form-control" id="data_provimento" name="data_provimento" required>
                    </div>
                    </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="descricao">Descrição:</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4" required></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="conteudo_anexo">Conteúdo do Anexo (Opcional):</label>
                        <textarea class="form-control" id="conteudo_anexo" name="conteudo_anexo" rows="6"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="anexo">Anexo (PDF):</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="anexo" name="anexo" accept=".pdf" required>
                            <label class="custom-file-label" for="anexo">Escolher arquivo</label>
                        </div>
                    </div>
                </div>
                <div class="row mb-12">
                    <div class="col-md-12">
                        <button type="submit" style="width: 100%; color: #fff!important" class="btn btn-secondary"><i class="fa fa-save" aria-hidden="true"></i> Cadastrar</button>
                    </div>
                </div>
            </form>
            <hr>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/toastr.min.js"></script>
    <script>
        // Validação e envio do formulário
        $('#cadastroForm').on('submit', function(event) {
            event.preventDefault();

            // Tratamento para remover quebras de linha, tabs e espaços duplos
            function limparTexto(texto) {
                return texto
                    .replace(/[\n\r\t]+/g, ' ') // substitui \n, \r, \t por espaço simples
                    .replace(/\s{2,}/g, ' ')    // substitui múltiplos espaços por apenas um espaço
                    .trim();                   // remove espaços no início e fim
            }

            // Limpar os campos antes de enviar
            var descricao = $('#descricao').val();
            var conteudoAnexo = $('#conteudo_anexo').val();

            $('#descricao').val(limparTexto(descricao));
            $('#conteudo_anexo').val(limparTexto(conteudoAnexo));

            var formData = new FormData(this);

            $.ajax({
                url: 'salvar_provimento.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            toastr.success(response.message, 'Sucesso');
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000); // Aguarda 2 segundos antes de recarregar
                        } else {
                            toastr.error(response.message, 'Erro');
                        }
                    } catch (e) {
                        toastr.error('Erro ao processar resposta do servidor.', 'Erro');
                    }
                },
                error: function() {
                    toastr.error('Erro ao cadastrar provimento.', 'Erro');
                }
            });
        });
    </script>
    <?php
    include(__DIR__ . '/../rodape.php');
    ?>
</body>

</html>
