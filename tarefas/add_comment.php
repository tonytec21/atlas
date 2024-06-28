<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hash_tarefa = $_POST['taskToken'];
    $comentario = $_POST['commentDescription'];
    $data_comentario = date('Y-m-d H:i:s');
    $funcionario = $_SESSION['username'];
    $status = 'Ativo'; // Defina o status inicial do comentário

    $caminho_anexo = '';

    // Verifica se há arquivos anexados
    if (!empty($_FILES['commentAttachments']['name'][0])) {
        $targetDir = "/arquivos/$hash_tarefa/";
        $fullTargetDir = __DIR__ . $targetDir;
        if (!is_dir($fullTargetDir)) {
            mkdir($fullTargetDir, 0777, true);
        }

        foreach ($_FILES['commentAttachments']['name'] as $key => $name) {
            $targetFile = $fullTargetDir . basename($name);
            if (move_uploaded_file($_FILES['commentAttachments']['tmp_name'][$key], $targetFile)) {
                $caminho_anexo .= "$targetDir" . basename($name) . ";";
            }
        }
        // Remover o ponto e vírgula final
        $caminho_anexo = rtrim($caminho_anexo, ';');
    }

    // Inserir comentário no banco de dados
    $sql = "INSERT INTO comentarios (hash_tarefa, comentario, caminho_anexo, data_comentario, funcionario, status)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $hash_tarefa, $comentario, $caminho_anexo, $data_comentario, $funcionario, $status);

    if ($stmt->execute()) {
        echo "Comentário adicionado com sucesso!";
    } else {
        echo "Erro ao adicionar comentário: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
?>
