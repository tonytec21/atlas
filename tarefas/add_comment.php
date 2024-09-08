<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hash_tarefa = $_POST['taskToken']; // Pegando o token da tarefa
    $comentario = $_POST['commentDescription'];
    $data_comentario = date('Y-m-d H:i:s');
    $funcionario = $_SESSION['username'];
    $status = 'Ativo'; // Defina o status inicial do comentário

    $caminho_anexo = '';

    // 1. Consulta o id_tarefa_principal na tabela de tarefas usando o token (hash_tarefa)
    $sql = "SELECT id_tarefa_principal FROM tarefas WHERE token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $hash_tarefa);
    $stmt->execute();
    $result = $stmt->get_result();

    $id_tarefa_principal = null; // Valor padrão se não encontrar
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id_tarefa_principal = $row['id_tarefa_principal'];
        error_log("ID Tarefa Principal encontrado: " . $id_tarefa_principal);
    } else {
        error_log("Nenhuma tarefa encontrada com o token: " . $hash_tarefa);
    }
    $stmt->close();

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

    // Inserir comentário no banco de dados, agora incluindo o id_tarefa_principal
    $sql = "INSERT INTO comentarios (hash_tarefa, comentario, caminho_anexo, data_comentario, funcionario, status, id_tarefa_principal)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $hash_tarefa, $comentario, $caminho_anexo, $data_comentario, $funcionario, $status, $id_tarefa_principal);

    if ($stmt->execute()) {
        echo "Comentário adicionado com sucesso!";
    } else {
        echo "Erro ao adicionar comentário: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
