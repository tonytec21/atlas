<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar se a ação foi definida
    if (!isset($_POST['action']) || $_POST['action'] !== 'delete_attachment') {
        die('Ação inválida.');
    }

    $filePath = $_POST['file'];
    $taskId = $_POST['taskId'];

    // Remove the file path from the database
    $sql = "SELECT caminho_anexo FROM tarefas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();

    if ($task) {
        $attachments = explode(';', $task['caminho_anexo']);
        $attachments = array_filter($attachments, function($attachment) use ($filePath) {
            return $attachment !== $filePath;
        });
        $newAttachmentPath = implode(';', $attachments);

        $sql = "UPDATE tarefas SET caminho_anexo = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $newAttachmentPath, $taskId);
        if ($stmt->execute()) {
            // Delete the file from the server
            if (file_exists(__DIR__ . '/../' . $filePath)) {
                unlink(__DIR__ . '/../' . $filePath);
            }
            echo "Anexo excluído com sucesso.";
        } else {
            echo "Erro ao atualizar a tarefa: " . $stmt->error;
        }
    } else {
        echo "Tarefa não encontrada.";
    }

    $stmt->close();
    $conn->close();
}

?>
