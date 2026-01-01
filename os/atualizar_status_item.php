<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = $_POST['item_id'];
    $quantidade = $_POST['quantidade'];

    try {
        $conn = getDatabaseConnection();

        // Buscar a quantidade liquidada atual do item
        $stmt = $conn->prepare("SELECT quantidade_liquidada FROM ordens_de_servico_itens WHERE id = :id");
        $stmt->bindParam(':id', $item_id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['error' => 'Item não encontrado']);
            exit;
        }

        $quantidade_liquidada = $item['quantidade_liquidada'];

        // Determinar o novo status com base na quantidade liquidada e a nova quantidade
        $status = ($quantidade == $quantidade_liquidada) ? 'liquidado' : 'parcialmente liquidado';

        $stmt = $conn->prepare("UPDATE ordens_de_servico_itens SET quantidade = :quantidade, status = :status WHERE id = :id");
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $item_id);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erro ao atualizar o status do item: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>