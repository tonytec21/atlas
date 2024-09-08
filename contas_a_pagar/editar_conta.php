<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verifica se o ID da conta foi enviado via GET
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Seleciona os dados da conta a ser editada
    $sql = "SELECT * FROM contas_a_pagar WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $conta = $result->fetch_assoc();
    } else {
        echo "Conta não encontrada!";
        exit;
    }
} else {
    echo "ID da conta não informado!";
    exit;
}

// Atualiza os dados da conta após o envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $valor = str_replace(',', '.', str_replace('.', '', $_POST['valor']));
    $data_vencimento = $_POST['data_vencimento'];
    $descricao = $_POST['descricao'];
    $recorrencia = $_POST['recorrencia'];

    // Se houver um novo anexo, trata o upload
    if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] == 0) {
        $diretorio = __DIR__ . "/anexos/" . $id . "/";
        if (!file_exists($diretorio)) {
            mkdir($diretorio, 0777, true);
        }

        $arquivo = $_FILES['anexo']['name'];
        $caminho_anexo = $diretorio . basename($arquivo);
        if (move_uploaded_file($_FILES['anexo']['tmp_name'], $caminho_anexo)) {
            $caminho_anexo = "anexos/" . $id . "/" . $arquivo;
        } else {
            $caminho_anexo = $conta['caminho_anexo'];  // Mantém o caminho do anexo original
        }
    } else {
        $caminho_anexo = $conta['caminho_anexo'];  // Mantém o anexo original se não houver upload
    }

    // Atualiza os dados no banco de dados
    $update_sql = "UPDATE contas_a_pagar 
                   SET titulo = ?, valor = ?, data_vencimento = ?, descricao = ?, recorrencia = ?, caminho_anexo = ?
                   WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('sdssssi', $titulo, $valor, $data_vencimento, $descricao, $recorrencia, $caminho_anexo, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Conta atualizada com sucesso!'); window.location.href = 'index.php';</script>";
    } else {
        echo "<script>alert('Erro ao atualizar a conta.');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Conta a Pagar</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="stylesheet" href="../style/css/toastr.min.css">
</head>
<body class="light-mode">
    <?php include(__DIR__ . '/../menu.php'); ?>

    <div id="main" class="main-content">
        <div class="container">
            <h3>Editar Conta a Pagar</h3>
            <hr>
            <form id="editarForm" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="titulo">Título:</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo $conta['titulo']; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="valor">Valor:</label>
                        <input type="text" class="form-control" id="valor" name="valor" value="<?php echo number_format($conta['valor'], 2, ',', '.'); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="data_vencimento">Data de Vencimento:</label>
                        <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" value="<?php echo $conta['data_vencimento']; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="recorrencia">Recorrência:</label>
                        <select class="form-control" id="recorrencia" name="recorrencia" required>
                            <option value="Nenhuma" <?php echo ($conta['recorrencia'] === 'Nenhuma') ? 'selected' : ''; ?>>Nenhuma</option>
                            <option value="Mensal" <?php echo ($conta['recorrencia'] === 'Mensal') ? 'selected' : ''; ?>>Mensal</option>
                            <option value="Semanal" <?php echo ($conta['recorrencia'] === 'Semanal') ? 'selected' : ''; ?>>Semanal</option>
                            <option value="Anual" <?php echo ($conta['recorrencia'] === 'Anual') ? 'selected' : ''; ?>>Anual</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="descricao">Descrição:</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4"><?php echo $conta['descricao']; ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                    <label for="anexo">
                        Anexo Atual: 
                        <?php 
                        if (!empty($conta['caminho_anexo'])) {
                            echo basename($conta['caminho_anexo']); 
                        } else {
                            echo "Nenhum anexo disponível";
                        }
                        ?>
                    </label>

                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="anexo" name="anexo" accept=".pdf">
                            <label class="custom-file-label" for="anexo">Escolher arquivo (PDF)</label>
                        </div>
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
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/toastr.min.js"></script>
    <script>
        $(document).ready(function() {
            // Atualiza o nome do arquivo selecionado no input de anexo
            $('.custom-file-input').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
            });

            // Formata o valor como moeda
            $('#valor').mask('000.000.000.000.000,00', {reverse: true});
        });
    </script>

    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
