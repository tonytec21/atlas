<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if (isset($_GET['id'])) {
    $conn = getDatabaseConnection();
    $id = $_GET['id'];

    try {
        // Atualiza o status para "removido" ao invés de deletar o checklist
        $stmt = $conn->prepare("UPDATE checklists SET status = 'removido' WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        echo json_encode(["success" => true, "message" => "Checklist excluído com sucesso!"]);
    } catch (Exception $e) {
        echo json_encode(["error" => "Erro ao excluir checklist: " . $e->getMessage()]);
    }
}
?>
