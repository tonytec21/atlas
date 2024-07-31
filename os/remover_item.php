<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = $_POST['item_id'];

    try {
        $conn = getDatabaseConnection();

        // Inicia a transação
        $conn->beginTransaction();

        // Verifica o status do item antes de remover
        $stmt = $conn->prepare("SELECT status FROM ordens_de_servico_itens WHERE id = :id");
        $stmt->bindParam(':id', $item_id);
        $stmt->execute();
        $status = $stmt->fetchColumn();

        if ($status === 'liquidado parcialmente') {
            echo json_encode(['error' => 'Não é permitido remover um item com status "liquidado parcialmente".']);
        } else {
            // Remove o item
            $stmt = $conn->prepare("DELETE FROM ordens_de_servico_itens WHERE id = :id");
            $stmt->bindParam(':id', $item_id);
            $stmt->execute();

            // Confirma a transação
            $conn->commit();

            echo json_encode(['success' => true]);
        }
    } catch (PDOException $e) {
        // Desfaz a transação em caso de erro
        $conn->rollBack();
        echo json_encode(['error' => 'Erro ao remover o item: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>
