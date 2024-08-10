<?php
include(__DIR__ . '/db_connection.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $funcionario = $_SESSION['nome_funcionario']; // Nome do funcionário logado
    $id_nascimento = $_POST['id_nascimento'];
    $status = 'ativo';

    if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] == 0) {
        $dir = 'anexos/' . $id_nascimento . '/';
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $arquivo_pdf = $dir . basename($_FILES['arquivo_pdf']['name']);
        if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $arquivo_pdf)) {
            // Inserir anexo no banco de dados
            $stmt = $conn->prepare("INSERT INTO indexador_nascimento_anexos (id_nascimento, caminho_anexo, funcionario, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $id_nascimento, $arquivo_pdf, $funcionario, $status);
            if ($stmt->execute()) {
                echo 'Anexo salvo com sucesso!';
            } else {
                echo 'Erro ao salvar o anexo no banco de dados.';
            }
            $stmt->close();
        } else {
            echo 'Erro ao mover o arquivo para o diretório.';
        }
    } else {
        echo 'Erro ao fazer upload do arquivo.';
    }

    $conn->close();
}
?>
