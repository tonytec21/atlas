<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = getDatabaseConnection();

    // Recebendo os campos do formulário
    $titulo = $_POST['titulo'];
    $observacoes = $_POST['observacoes'] ?? null;
    $criado_por = $_SESSION['username'];
    $itens = json_decode($_POST['itens'], true);

    try {
        $conn->beginTransaction();

        // Salva o checklist (agora também grava 'observacoes')
        $stmt = $conn->prepare("INSERT INTO checklists (titulo, observacoes, criado_por) VALUES (:titulo, :observacoes, :criado_por)");
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':criado_por', $criado_por);
        $stmt->execute();
        $checklist_id = $conn->lastInsertId();

        // Salva os itens do checklist
        $stmt = $conn->prepare("INSERT INTO checklist_itens (checklist_id, item) VALUES (:checklist_id, :item)");
        foreach ($itens as $item) {
            $stmt->bindParam(':checklist_id', $checklist_id);
            $stmt->bindParam(':item', $item);
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Checklist salvo com sucesso!"]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(["error" => "Erro ao salvar checklist: " . $e->getMessage()]);
    }
}
?>
