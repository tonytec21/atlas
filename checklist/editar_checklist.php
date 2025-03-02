<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = getDatabaseConnection();
    $id = $_POST['id'];
    $titulo = $_POST['titulo'];
    $observacoes = isset($_POST['observacoes']) ? $_POST['observacoes'] : null;
    $itens = json_decode($_POST['itens'], true);

    try {
        $conn->beginTransaction();

        // Atualiza o título e as observações
        $stmt = $conn->prepare("UPDATE checklists SET titulo = :titulo, observacoes = :observacoes WHERE id = :id");
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Remove os itens antigos e insere os novos
        $stmt = $conn->prepare("DELETE FROM checklist_itens WHERE checklist_id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO checklist_itens (checklist_id, item) VALUES (:checklist_id, :item)");
        foreach ($itens as $item) {
            $stmt->bindParam(':checklist_id', $id);
            $stmt->bindParam(':item', $item);
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Checklist atualizado com sucesso!"]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(["error" => "Erro ao atualizar checklist: " . $e->getMessage()]);
    }
}
?>
