<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verificar se o ID do provimento foi passado
if (!isset($_GET['id'])) {
    echo "ID do provimento não fornecido.";
    exit;
}

$conn = getDatabaseConnection();
$stmt = $conn->prepare('SELECT * FROM provimentos WHERE id = :id');
$stmt->bindParam(':id', $_GET['id']);
$stmt->execute();
$provimento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$provimento) {
    echo "Provimento não encontrado.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Provimento</title>
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
            <h3>Editar Provimento</h3>
            <hr>
            <form id="editarForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $provimento['id']; ?>">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="numero_provimento">Número do Provimento:</label>
                        <input type="text" class="form-control" id="numero_provimento" name="numero_provimento" required pattern="\d*" title="Apenas números são permitidos" value="<?php echo $provimento['numero_provimento']; ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="origem">Origem:</label>
                        <select class="form-control" id="origem" name="origem" required>
                            <option value="CGJ/MA" <?php echo $provimento['origem'] == 'CGJ/MA' ? 'selected' : ''; ?>>CGJ/MA</option>
                            <option value="CNJ" <?php echo $provimento['origem'] == 'CNJ' ? 'selected' : ''; ?>>CNJ</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="data_provimento">Data do Provimento:</label>
                        <input type="date" class="form-control" id="data_provimento" name="data_provimento" required value="<?php echo $provimento['data_provimento']; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="descricao">Descrição:</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4" required><?php echo $provimento['descricao']; ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="anexo">Anexo (PDF):</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="anexo" name="anexo" accept=".pdf">
                            <label class="custom-file-label" for="anexo">Escolher arquivo</label>
                        </div>
                    </div>
                </div>
                <div class="row mb-12">
                    <div class="col-md-12">
                        <button type="submit" style="width: 100%; color: #fff!important" class="btn btn-secondary"><i class="fa fa-save" aria-hidden="true"></i> Salvar Alterações</button>
                    </div>
                </div>
            </form>
            <hr>

            <!-- Listar o anexo atual -->
            <div>
                <h5>Anexo Atual</h5>
                <?php if ($provimento['caminho_anexo']) : ?>
                    <p>
                        <a href="<?php echo $provimento['caminho_anexo']; ?>" target="_blank">Visualizar Anexo</a> |
                        <button class="btn btn-danger btn-sm" onclick="removerAnexo('<?php echo $provimento['id']; ?>')">Remover Anexo</button>
                    </p>
                <?php else : ?>
                    <p>Nenhum anexo disponível.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/toastr.min.js"></script>
    <script>
        $(document).ready(function() {
            // Atualizar o nome do arquivo selecionado no input de anexo
            $('.custom-file-input').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
            });

            // Validação e envio do formulário
            $('#editarForm').on('submit', function(event) {
                event.preventDefault();

                var formData = new FormData(this);

                $.ajax({
                    url: 'salvar_edicao_provimento.php',
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
                                    window.location.href = 'pesquisar_provimentos.php';
                                }, 2000); // Aguarda 2 segundos antes de redirecionar
                            } else {
                                toastr.error(response.message, 'Erro');
                            }
                        } catch (e) {
                            toastr.error('Erro ao processar resposta do servidor.', 'Erro');
                        }
                    },
                    error: function() {
                        toastr.error('Erro ao editar provimento.', 'Erro');
                    }
                });
            });
        });

        function removerAnexo(provimentoId) {
            if (confirm('Tem certeza que deseja remover o anexo? Esta ação não pode ser desfeita.')) {
                $.ajax({
                    url: 'remover_anexo_provimento.php',
                    type: 'POST',
                    data: { id: provimentoId },
                    success: function(response) {
                        try {
                            response = JSON.parse(response);
                            if (response.success) {
                                toastr.success(response.message, 'Sucesso');
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                toastr.error(response.message, 'Erro');
                            }
                        } catch (e) {
                            toastr.error('Erro ao processar resposta do servidor.', 'Erro');
                        }
                    },
                    error: function() {
                        toastr.error('Erro ao remover o anexo.', 'Erro');
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
