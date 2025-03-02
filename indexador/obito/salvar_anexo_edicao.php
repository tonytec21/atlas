<?php
include(__DIR__ . '/db_connection.php');
session_start();
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $funcionario = $_SESSION['username'];
    $id_obito = $_POST['id_obito'];
    $status = 'A';

    if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] == 0) {
        $dir = 'anexos/obitos/' . $id_obito . '/';

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $arquivo_pdf = $dir . basename($_FILES['arquivo_pdf']['name']);

        if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $arquivo_pdf)) {
            $stmt = $conn->prepare("INSERT INTO indexador_obito_anexos (id_obito, caminho_anexo, funcionario, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $id_obito, $arquivo_pdf, $funcionario, $status);

            if ($stmt->execute()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Anexo salvo com sucesso!'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erro ao salvar o anexo no banco de dados.'
                ]);
            }

            $stmt->close();
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao mover o arquivo para o diretório.'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao fazer upload do arquivo.'
        ]);
    }

    $conn->close();
}
?>
