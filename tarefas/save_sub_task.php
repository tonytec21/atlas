<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST['title'];
    $categoria = $_POST['category'];
    $data_limite = $_POST['deadline'];
    $funcionario_responsavel = $_POST['employee'];
    $origem = $_POST['origin'];
    $descricao = $_POST['description'];
    $criado_por = $_POST['createdBy'];
    $data_criacao = date('Y-m-d H:i:s');
    $token = md5(uniqid(rand(), true));
    $caminho_anexo = '';
    $nivel_de_prioridade = $_POST['priority'];
    
    // Aqui você captura o valor de id_tarefa_principal
    $id_tarefa_principal = $_POST['id_tarefa_principal'];

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
    $sql = "INSERT INTO tarefas (token, titulo, categoria, origem, descricao, data_limite, funcionario_responsavel, criado_por, data_criacao, caminho_anexo, nivel_de_prioridade, sub_categoria, id_tarefa_principal) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Sim', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssssi", $token, $titulo, $categoria, $origem, $descricao, $data_limite, $funcionario_responsavel, $criado_por, $data_criacao, $caminho_anexo, $nivel_de_prioridade, $id_tarefa_principal);

    if ($stmt->execute()) {
        // Capturar o ID da tarefa recém-inserida
        $last_id = $stmt->insert_id;
        header("Location: edit_task.php?id=$last_id");
    } else {
        echo "Erro ao salvar a tarefa: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
