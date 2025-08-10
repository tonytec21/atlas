<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $commentId = $_POST['commentId'];
    $description = $_POST['editCommentDescription'];
    $taskToken = $_POST['taskToken'];
    $updatedAt = date('Y-m-d H:i:s'); // Current timestamp

    // Update comment data
    $sql = "UPDATE comentarios SET comentario = ?, data_atualizacao = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $description, $updatedAt, $commentId);

    if ($stmt->execute()) {
        // Handle file uploads only if the comment update was successful
        if (!empty($_FILES['editCommentAttachments']['name'][0])) {
            $uploadsDir = __DIR__ . '/arquivos/' . $taskToken . '/';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }
            $newAttachments = [];
            foreach ($_FILES['editCommentAttachments']['name'] as $key => $name) {
                $tmpName = $_FILES['editCommentAttachments']['tmp_name'][$key];
                $filePath = $uploadsDir . basename($name);
                if (move_uploaded_file($tmpName, $filePath)) {
                    $newAttachments[] = 'arquivos/' . $taskToken . '/' . basename($name);
                } else {
                    echo "Erro ao fazer upload do arquivo: " . $name;
                }
            }

            if (!empty($newAttachments)) {
                // Retrieve existing attachments
                $sql = "SELECT caminho_anexo FROM comentarios WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $commentId);
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

                // Update the comment with the new attachments
                $sql = "UPDATE comentarios SET caminho_anexo = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('si', $allAttachmentPaths, $commentId);
                $stmt->execute();
            }
        }
        echo "Comentário atualizado com sucesso.";
    } else {
        echo "Erro ao atualizar o comentário: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
