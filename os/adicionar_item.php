<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];
    $ato = $_POST['ato'];
    $quantidade = $_POST['quantidade'];
    $desconto_legal = $_POST['desconto_legal'];
    $descricao = $_POST['descricao'];
    $emolumentos = $_POST['emolumentos'];
    $ferc = $_POST['ferc'];
    $fadep = $_POST['fadep'];
    $femp = $_POST['femp'];
    $ferrfis = isset($_POST['ferrfis']) ? $_POST['ferrfis'] : 0;
    $total = $_POST['total'];

    try {
        $conn = getDatabaseConnection();

        // Inicia a transação
        $conn->beginTransaction();

        // Adiciona o item na tabela `ordens_de_servico_itens`
        $stmt = $conn->prepare("INSERT INTO ordens_de_servico_itens (ordem_servico_id, ato, quantidade, desconto_legal, descricao, emolumentos, ferc, fadep, femp, ferrfis, total) VALUES (:ordem_servico_id, :ato, :quantidade, :desconto_legal, :descricao, :emolumentos, :ferc, :fadep, :femp, :ferrfis, :total)");
        $stmt->bindParam(':ordem_servico_id', $os_id);
        $stmt->bindParam(':ato', $ato);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':desconto_legal', $desconto_legal);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':emolumentos', $emolumentos);
        $stmt->bindParam(':ferc', $ferc);
        $stmt->bindParam(':fadep', $fadep);
        $stmt->bindParam(':femp', $femp);
        $stmt->bindParam(':ferrfis', $ferrfis);
        $stmt->bindParam(':total', $total);
        $stmt->execute();

        // Atualiza o total da OS na tabela `ordens_de_servico`
        $stmt = $conn->prepare("UPDATE ordens_de_servico SET total_os = total_os + :total WHERE id = :id");
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':id', $os_id);
        $stmt->execute();

        // Confirma a transação
        $conn->commit();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Desfaz a transação em caso de erro
        $conn->rollBack();
        echo json_encode(['error' => 'Erro ao adicionar o item: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>