<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $commentId = $_POST['commentId'];
    $filePath = $_POST['file'];

    // Remover anexo do servidor
    if (file_exists(__DIR__ . '/' . $filePath)) {
        unlink(__DIR__ . '/' . $filePath);
    }

    // Remover anexo do banco de dados
    $sql = "SELECT caminho_anexo FROM comentarios WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $commentId);
    $stmt->execute();
    $stmt->bind_result($existingAttachments);
    $stmt->fetch();
    $stmt->close();

    $attachmentsArray = explode(';', $existingAttachments);
    $attachmentsArray = array_filter($attachmentsArray, function($attachment) use ($filePath) {
        return $attachment !== $filePath;
    });
    $newAttachments = implode(';', $attachmentsArray);

    $sql = "UPDATE comentarios SET caminho_anexo = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $newAttachments, $commentId);

    if ($stmt->execute()) {
        echo "Anexo de comentário excluído com sucesso.";
    } else {
        echo "Erro ao excluir o anexo do comentário: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
