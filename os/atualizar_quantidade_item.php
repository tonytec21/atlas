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
    $total = $_POST['total'];

    try {
        $conn = getDatabaseConnection();

        $stmt = $conn->prepare("UPDATE ordens_de_servico_itens SET quantidade = :quantidade, emolumentos = :emolumentos, ferc = :ferc, fadep = :fadep, femp = :femp, total = :total WHERE id = :id");
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':emolumentos', $emolumentos);
        $stmt->bindParam(':ferc', $ferc);
        $stmt->bindParam(':fadep', $fadep);
        $stmt->bindParam(':femp', $femp);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':id', $item_id);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erro ao atualizar a quantidade do item: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>
