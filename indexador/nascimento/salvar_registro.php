<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include(__DIR__ . '/db_connection.php');

    // Obter o nome do funcionário da sessão
    session_start();
    $funcionario = $_SESSION['nome_funcionario']; // Nome do funcionário logado

    // Captura os dados sem fazer qualquer conversão de caracteres especiais
    $termo = $_POST['termo'];
    $livro = $_POST['livro'];
    $folha = $_POST['folha'];
    $data_registro = $_POST['data_registro'];
    $data_nascimento = $_POST['data_nascimento'];
    $nome_registrado = $_POST['nome_registrado'];
    $nome_pai = $_POST['nome_pai'];
    $nome_mae = $_POST['nome_mae'];
    $status = 'ativo';

    // Inserir registro no banco de dados
    $stmt = $conn->prepare("INSERT INTO indexador_nascimento (termo, livro, folha, data_registro, data_nascimento, nome_registrado, nome_pai, nome_mae, funcionario, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssss", $termo, $livro, $folha, $data_registro, $data_nascimento, $nome_registrado, $nome_pai, $nome_mae, $funcionario, $status);

    if ($stmt->execute()) {
        $last_id = $stmt->insert_id;
        $arquivo_pdf = '';

        if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] == 0) {
            $dir = 'anexos/' . $last_id . '/';
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $arquivo_pdf = $dir . basename($_FILES['arquivo_pdf']['name']);
            move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $arquivo_pdf);

            // Inserir anexo no banco de dados
            $stmt_anexo = $conn->prepare("INSERT INTO indexador_nascimento_anexos (id_nascimento, caminho_anexo, funcionario, status) VALUES (?, ?, ?, ?)");
            $stmt_anexo->bind_param("isss", $last_id, $arquivo_pdf, $funcionario, $status);
            $stmt_anexo->execute();
        }

        echo 'Registro salvo com sucesso!';
    } else {
        echo 'Erro ao salvar o registro: ' . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
