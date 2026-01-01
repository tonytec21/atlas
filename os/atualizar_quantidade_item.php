<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = $_POST['item_id'];
    $quantidade = $_POST['quantidade'];
    $emolumentos = $_POST['emolumentos'];
    $ferc = $_POST['ferc'];
    $fadep = $_POST['fadep'];
    $femp = $_POST['femp'];
    $ferrfis = isset($_POST['ferrfis']) ? $_POST['ferrfis'] : 0;
    $total = $_POST['total'];

    try {
        $conn = getDatabaseConnection();

        // Verifica a quantidade já liquidada
        $stmt = $conn->prepare("SELECT quantidade_liquidada, status FROM ordens_de_servico_itens WHERE id = :id");
        $stmt->bindParam(':id', $item_id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['error' => 'Item não encontrado.']);
            exit;
        }

        $quantidadeLiquidada = (int) $item['quantidade_liquidada'];
        $statusAtual = $item['status'];

        // Verifica se a nova quantidade é menor que a quantidade liquidada
        if ($quantidade < $quantidadeLiquidada) {
            echo json_encode(['error' => 'A nova quantidade não pode ser menor do que a quantidade já liquidada (' . $quantidadeLiquidada . ').']);
            exit;
        }

        // Atualiza os valores do item
        $stmt = $conn->prepare("
            UPDATE ordens_de_servico_itens 
            SET quantidade = :quantidade, emolumentos = :emolumentos, ferc = :ferc, fadep = :fadep, femp = :femp, ferrfis = :ferrfis, total = :total 
            WHERE id = :id
        ");
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':emolumentos', $emolumentos);
        $stmt->bindParam(':ferc', $ferc);
        $stmt->bindParam(':fadep', $fadep);
        $stmt->bindParam(':femp', $femp);
        $stmt->bindParam(':ferrfis', $ferrfis);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':id', $item_id);
        $stmt->execute();

        // Verifica se a quantidade atual é igual à quantidade liquidada para alterar o status
        if ($quantidade == $quantidadeLiquidada && $statusAtual !== 'liquidado') {
            $stmt = $conn->prepare("UPDATE ordens_de_servico_itens SET status = 'liquidado' WHERE id = :id");
            $stmt->bindParam(':id', $item_id);
            $stmt->execute();
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erro ao atualizar a quantidade do item: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>