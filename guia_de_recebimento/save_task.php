<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');  
date_default_timezone_set('America/Sao_Paulo');

if (!isset($conn)) {
    die("Erro ao conectar ao banco de dados");
}

// Configura a conexão para usar UTF-8
$conn->set_charset("utf8");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST['title'];
    $categoria = $_POST['category'];
    $data_limite = $_POST['deadline'];
    $funcionario_responsavel = $_POST['employee'];
    $origem = $_POST['origin'];
    $descricao = $_POST['description'];
    $criado_por = $_POST['createdBy'];
    $data_criacao = $_POST['createdAt'];
    $guiaId = $_POST['guiaId'];  // Receber o ID da guia de recebimento
    $token = md5(uniqid(rand(), true));
    $caminho_anexo = '';

    // Verifica se há arquivos anexados
    if (!empty($_FILES['attachments']['name'][0])) {
        $tokenDir = "../tarefas/arquivos/$token/";
        $dbDir = "arquivos/$token/";
        $fullTargetDir = realpath(__DIR__ . "/../tarefas/arquivos/") . "/$token/";

        if (!is_dir($fullTargetDir)) {
            mkdir($fullTargetDir, 0777, true);
        }

        foreach ($_FILES['attachments']['name'] as $key => $name) {
            $targetFile = $fullTargetDir . basename($name);
            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $targetFile)) {
                $caminho_anexo .= "$dbDir" . basename($name) . ";";
            }
        }
        $caminho_anexo = rtrim($caminho_anexo, ';');
    }

    // Inserir dados da tarefa no banco de dados
    $sql = "INSERT INTO tarefas (token, titulo, categoria, origem, descricao, data_limite, funcionario_responsavel, criado_por, data_criacao, caminho_anexo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $token, $titulo, $categoria, $origem, $descricao, $data_limite, $funcionario_responsavel, $criado_por, $data_criacao, $caminho_anexo);

    if ($stmt->execute()) {
        // Capturar o ID da tarefa recém-inserida
        $last_id = $stmt->insert_id;

        // Atualizar o campo `task_id` na tabela `guia_de_recebimento`
        $update_sql = "UPDATE guia_de_recebimento SET task_id = ? WHERE id = ?";
        $stmt_update = $conn->prepare($update_sql);
        $stmt_update->bind_param("ii", $last_id, $guiaId);
        $stmt_update->execute();
        $stmt_update->close();

        // Redirecionar para a página usando o token
        header("Location: ../tarefas/index_tarefa.php?token=" . $token);
        exit();
    } else {
        echo "Erro ao salvar a tarefa: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
