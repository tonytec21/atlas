<?php
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ordem = $_POST['ordem'];

    $conn = getDatabaseConnection();

    try {
        $conn->beginTransaction();

        foreach ($ordem as $item) {
            $stmt = $conn->prepare("UPDATE ordens_de_servico_itens SET ordem_exibicao = :ordem_exibicao WHERE id = :id");
            $stmt->bindParam(':ordem_exibicao', $item['ordem_exibicao']);
            $stmt->bindParam(':id', $item['id']);
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['error' => 'Erro ao salvar a ordem: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>
