<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include(__DIR__ . '/db_connection.php');

    session_start();
    $funcionario = $_SESSION['nome_funcionario']; // Nome do funcionário logado

    // Captura os dados sem fazer qualquer conversão de caracteres especiais
    $id = $_POST['id'];
    $termo = $_POST['termo'];
    $livro = $_POST['livro'];
    $folha = $_POST['folha'];
    $nome_registrado = $_POST['nome_registrado'];
    $data_nascimento = $_POST['data_nascimento'];
    $nome_pai = $_POST['nome_pai'];
    $nome_mae = $_POST['nome_mae'];
    $data_registro = $_POST['data_registro'];
    $status = 'ativo';

    // Atualizar registro no banco de dados
    $stmt = $conn->prepare("UPDATE indexador_nascimento SET termo = ?, livro = ?, folha = ?, nome_registrado = ?, data_nascimento = ?, nome_pai = ?, nome_mae = ?, data_registro = ?, funcionario = ? WHERE id = ?");
    $stmt->bind_param("sssssssssi", $termo, $livro, $folha, $nome_registrado, $data_nascimento, $nome_pai, $nome_mae, $data_registro, $funcionario, $id);

    if ($stmt->execute()) {
        if (!empty($_FILES['arquivo_pdf']['name'])) {
            $dir = 'anexos/' . $id . '/';
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $arquivo_pdf = $dir . basename($_FILES['arquivo_pdf']['name']);
            if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $arquivo_pdf)) {
                // Atualizar anexo no banco de dados
                $stmt_anexo = $conn->prepare("INSERT INTO indexador_nascimento_anexos (id_nascimento, caminho_anexo, funcionario, status) VALUES (?, ?, ?, ?)");
                $stmt_anexo->bind_param("isss", $id, $arquivo_pdf, $funcionario, $status);
                $stmt_anexo->execute();
            }
        }

        echo json_encode(array('status' => 'success'));
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Erro ao atualizar o registro'));
    }

    $stmt->close();
    $conn->close();
}
?>
