<?php
include(__DIR__ . '/db_connection.php');

if (isset($_GET['id'])) {
    $conn = getDatabaseConnection();
    $id = $_GET['id'];

    $stmt = $conn->prepare("SELECT titulo, observacoes FROM checklists WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $checklist = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($checklist) {
        $stmt = $conn->prepare("SELECT item FROM checklist_itens WHERE checklist_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            "titulo" => $checklist['titulo'], 
            "observacoes" => $checklist['observacoes'], // Agora incluído corretamente
            "itens" => $itens
        ]);
    } else {
        echo json_encode(["error" => "Checklist não encontrado"]);
    }
}
?>
