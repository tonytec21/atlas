<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/checar_acesso_de_administrador.php');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Conta a Pagar</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h3>Cadastrar Conta a Pagar</h3>
                </div>
                <div class="col-md-6 text-right">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fa fa-search" aria-hidden="true"></i> Ir para pesquisa
                    </a>
                </div>
            </div>
            <hr>
            <form id="cadastroForm" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="titulo">Título:</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="valor">Valor:</label>
                        <input type="text" class="form-control money" id="valor" name="valor" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="data_vencimento">Data de Vencimento:</label>
                        <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="recorrencia">Recorrência:</label>
                        <select class="form-control" id="recorrencia" name="recorrencia" required>
                            <option value="Nenhuma">Nenhuma</option>
                            <option value="Mensal">Mensal</option>
                            <option value="Semanal">Semanal</option>
                            <option value="Anual">Anual</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="descricao">Descrição (Opcional):</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="anexo">Anexo (Opcional):</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="anexo" name="anexo" accept=".pdf">
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
    <script src="../script/sweetalert2.js"></script>
    <script>
        $(document).ready(function() {
            // Formatar o campo de valor como moeda brasileira
            $('.money').mask('000.000.000.000.000,00', {reverse: true});

            // Atualizar o nome do arquivo selecionado no input de anexo
            $('.custom-file-input').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
            });

            // Enviar o formulário via AJAX
            $('#cadastroForm').on('submit', function(event) {
                event.preventDefault();
                var formData = new FormData(this);

                $.ajax({
                    url: 'salvar_conta.php', // Arquivo separado para salvar os dados
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        console.log(response); // Verifique a resposta do servidor
                        try {
                            if (typeof response === 'string') {
                                response = JSON.parse(response);
                            }
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sucesso!',
                                    text: response.message,
                                    confirmButtonText: 'OK'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.reload();
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro!',
                                    text: response.message,
                                    confirmButtonText: 'OK'
                                });
                            }
                        } catch (e) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro!',
                                text: 'Erro ao processar resposta do servidor.',
                                confirmButtonText: 'OK'
                            });
                            console.error('Erro de parsing JSON:', e);
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Erro ao cadastrar conta.',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            });
        });

    </script>
    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>
