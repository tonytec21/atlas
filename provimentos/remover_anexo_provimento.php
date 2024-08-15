<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare('SELECT caminho_anexo FROM provimentos WHERE id = :id');
    $stmt->bindParam(':id', $_POST['id']);
    $stmt->execute();
    $provimento = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($provimento && $provimento['caminho_anexo']) {
        $caminho_anexo = $provimento['caminho_anexo'];

        // Tenta remover o arquivo físico
        if (file_exists($caminho_anexo) && unlink($caminho_anexo)) {
            // Remove o caminho do anexo do banco de dados
            $stmt = $conn->prepare('UPDATE provimentos SET caminho_anexo = NULL WHERE id = :id');
            $stmt->bindParam(':id', $_POST['id']);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Anexo removido com sucesso.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao remover o anexo do banco de dados.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao remover o arquivo físico.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Anexo não encontrado.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID do provimento não fornecido.']);
}
?>
