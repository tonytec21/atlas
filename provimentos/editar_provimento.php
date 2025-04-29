<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');   

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID do provimento não informado.');
}

$id = intval($_GET['id']);
$conn = getDatabaseConnection();

// Busca os dados do provimento
$stmt = $conn->prepare('SELECT * FROM provimentos WHERE id = :id');
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$provimento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$provimento) {
    die('Provimento não encontrado.');
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Provimento</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/toastr.min.css">
</head>

<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Editar Provimento</h3>
            <hr>

            <form id="editarForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= htmlspecialchars($provimento['id']) ?>">

                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="tipo">Tipo:</label>
                        <select class="form-control" id="tipo" name="tipo" required>
                            <option value="Resolução" <?= $provimento['tipo'] == 'Resolução' ? 'selected' : '' ?>>Resolução</option>
                            <option value="Provimento" <?= $provimento['tipo'] == 'Provimento' ? 'selected' : '' ?>>Provimento</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="numero_provimento">Número:</label>
                        <input type="text" class="form-control" id="numero_provimento" name="numero_provimento" value="<?= htmlspecialchars($provimento['numero_provimento']) ?>" required pattern="\d*" title="Apenas números são permitidos">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="origem">Origem:</label>
                        <select class="form-control" id="origem" name="origem" required>
                            <option value="CGJ/MA" <?= $provimento['origem'] == 'CGJ/MA' ? 'selected' : '' ?>>CGJ/MA</option>
                            <option value="CNJ" <?= $provimento['origem'] == 'CNJ' ? 'selected' : '' ?>>CNJ</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="data_provimento">Data:</label>
                        <input type="date" class="form-control" id="data_provimento" name="data_provimento" value="<?= htmlspecialchars($provimento['data_provimento']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="descricao">Descrição:</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4" required><?= htmlspecialchars($provimento['descricao']) ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="conteudo_anexo">Conteúdo do Anexo (Opcional):</label>
                        <textarea class="form-control" id="conteudo_anexo" name="conteudo_anexo" rows="6"><?= htmlspecialchars($provimento['conteudo_anexo']) ?></textarea>
                    </div>
                </div>

                <?php if (!empty($provimento['caminho_anexo']) && file_exists(__DIR__ . '/' . $provimento['caminho_anexo'])): ?>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label>Anexo Atual:</label><br>
                        <a href="<?= $provimento['caminho_anexo'] ?>" target="_blank" class="btn btn-primary btn-sm">Ver Anexo Atual</a>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" value="1" id="remover_anexo" name="remover_anexo">
                            <label class="form-check-label" for="remover_anexo">
                                Remover Anexo Atual
                            </label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="anexo">Novo Anexo (PDF) (opcional):</label>
                        <input type="file" class="form-control" id="anexo" name="anexo" accept=".pdf">
                    </div>
                </div>

                <div class="row mb-12">
                    <div class="col-md-12">
                        <button type="submit" style="width: 100%; color: #fff!important" class="btn btn-secondary">
                            <i class="fa fa-save" aria-hidden="true"></i> Salvar Alterações
                        </button>
                    </div>
                </div>
            </form>

            <hr>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/toastr.min.js"></script>

    <script>
    $('#editarForm').on('submit', function(event) {
        event.preventDefault();

        function limparTexto(texto) {
            return texto.replace(/[\n\r\t]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
        }

        $('#descricao').val(limparTexto($('#descricao').val()));
        $('#conteudo_anexo').val(limparTexto($('#conteudo_anexo').val()));

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
                            window.location.href = 'index.php'; 
                        }, 2000);
                    } else {
                        toastr.error(response.message, 'Erro');
                    }
                } catch (e) {
                    toastr.error('Erro ao processar resposta do servidor.', 'Erro');
                }
            },
            error: function() {
                toastr.error('Erro ao salvar edição.', 'Erro');
            }
        });
    });
    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>

</html>
