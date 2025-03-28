<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $taskId = $_POST['taskId'];
    $title = $_POST['title'];
    $category = $_POST['category'];
    $origin = $_POST['origin'];
    $deadline = $_POST['deadline'];
    $employee = $_POST['employee'];
    $description = $_POST['description'];
    $updatedBy = $_POST['updatedBy'];
    $updatedAt = date('Y-m-d H:i:s'); 
    $nivel_de_prioridade = $_POST['priority'];
    $reviewer = isset($_POST['reviewer']) ? $_POST['reviewer'] : null;

    // Convert deadline to the correct format
    $deadline = DateTime::createFromFormat('Y-m-d\TH:i', $deadline);
    if ($deadline) {
        $deadline = $deadline->format('Y-m-d H:i:s');
    } else {
        die("Data limite inválida.");
    }

    // Update task data
    $sql = "UPDATE tarefas SET titulo = ?, categoria = ?, origem = ?, data_limite = ?, funcionario_responsavel = ?, descricao = ?, data_atualizacao = ?, atualizado_por = ?, nivel_de_prioridade = ?, revisor = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssssssssi', $title, $category, $origin, $deadline, $employee, $description, $updatedAt, $updatedBy, $nivel_de_prioridade, $reviewer, $taskId);

    if ($stmt->execute()) {
        // Retornar um JSON para sucesso
        echo json_encode(['status' => 'success', 'message' => 'Tarefa atualizada com sucesso.']);
    } else {
        // Retornar um JSON para erro
        echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar a tarefa: ' . $stmt->error]);
    }

    // Handle file uploads
    if (!empty($_FILES['attachments']['name'][0])) {
        $uploadsDir = __DIR__ . '/arquivos/' . $_POST['taskToken'] . '/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }
        $newAttachments = [];
        foreach ($_FILES['attachments']['name'] as $key => $name) {
            $tmpName = $_FILES['attachments']['tmp_name'][$key];
            $filePath = $uploadsDir . basename($name);
            if (move_uploaded_file($tmpName, $filePath)) {
                $newAttachments[] = 'arquivos/' . $_POST['taskToken'] . '/' . basename($name);
            } else {
                echo "Erro ao fazer upload do arquivo: " . $name;
            }
        }

        if (!empty($newAttachments)) {
            // Retrieve existing attachments
            $sql = "SELECT caminho_anexo FROM tarefas WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $taskId);
            $stmt->execute();
            $stmt->bind_result($existingAttachments);
            $stmt->fetch();
            $stmt->close();

            // Combine existing and new attachments
            $allAttachments = array_filter(array_merge(
                explode(';', $existingAttachments),
                $newAttachments
            ));
            $allAttachmentPaths = implode(';', $allAttachments);

            // Update the task with the new attachments
            $sql = "UPDATE tarefas SET caminho_anexo = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $allAttachmentPaths, $taskId);
            $stmt->execute();
        }
    }

    $stmt->close();
    $conn->close();
}
?>
