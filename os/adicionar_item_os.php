<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ordem_servico_id = $_POST['ordem_servico_id'];
    $ato = $_POST['ato'];
    $quantidade = $_POST['quantidade'];
    $desconto_legal = $_POST['desconto_legal'];
    $descricao = $_POST['descricao'];
    $emolumentos = str_replace(',', '.', $_POST['emolumentos']);
    $ferc = str_replace(',', '.', $_POST['ferc']);
    $fadep = str_replace(',', '.', $_POST['fadep']);
    $femp = str_replace(',', '.', $_POST['femp']);
    $total = str_replace(',', '.', $_POST['total']);

    try {
        $conn = getDatabaseConnection();

        // Insere o item na tabela `ordens_de_servico_itens`
        $stmt = $conn->prepare("INSERT INTO ordens_de_servico_itens (ordem_servico_id, ato, quantidade, desconto_legal, descricao, emolumentos, ferc, fadep, femp, total) VALUES (:ordem_servico_id, :ato, :quantidade, :desconto_legal, :descricao, :emolumentos, :ferc, :fadep, :femp, :total)");
        $stmt->bindParam(':ordem_servico_id', $ordem_servico_id);
        $stmt->bindParam(':ato', $ato);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':desconto_legal', $desconto_legal);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':emolumentos', $emolumentos);
        $stmt->bindParam(':ferc', $ferc);
        $stmt->bindParam(':fadep', $fadep);
        $stmt->bindParam(':femp', $femp);
        $stmt->bindParam(':total', $total);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erro ao adicionar item: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>
