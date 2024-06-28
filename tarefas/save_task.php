<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST['title'];
    $categoria = $_POST['category'];
    $data_limite = $_POST['deadline'];
    $funcionario_responsavel = $_POST['employee'];
    $origem = $_POST['origin'];
    $descricao = $_POST['description'];
    $criado_por = $_POST['createdBy'];
    $data_criacao = $_POST['createdAt'];
    $token = md5(uniqid(rand(), true));
    $caminho_anexo = '';

    // Verifica se há arquivos anexados
    if (!empty($_FILES['attachments']['name'][0])) {
        $targetDir = "/arquivos/$token/";
        $fullTargetDir = __DIR__ . $targetDir;
        if (!is_dir($fullTargetDir)) {
            mkdir($fullTargetDir, 0777, true);
        }

        foreach ($_FILES['attachments']['name'] as $key => $name) {
            $targetFile = $fullTargetDir . basename($name);
            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $targetFile)) {
                $caminho_anexo .= "$targetDir" . basename($name) . ";";
            }
        }
        // Remover o ponto e vírgula final
        $caminho_anexo = rtrim($caminho_anexo, ';');
    }

    // Inserir dados da tarefa no banco de dados
    $sql = "INSERT INTO tarefas (token, titulo, categoria, origem, descricao, data_limite, funcionario_responsavel, criado_por, data_criacao, caminho_anexo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $token, $titulo, $categoria, $origem, $descricao, $data_limite, $funcionario_responsavel, $criado_por, $data_criacao, $caminho_anexo);

    if ($stmt->execute()) {
        echo "Tarefa salva com sucesso!";
        header("Location: index.php");
    } else {
        echo "Erro ao salvar a tarefa: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
