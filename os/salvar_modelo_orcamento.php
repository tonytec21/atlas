<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_modelo       = $_POST['nome_modelo'];
    $descricao_modelo  = $_POST['descricao_modelo'];
    $itens             = $_POST['itens'];
    $criado_por        = $_SESSION['username'];

    try {
        $conn = getDatabaseConnection();
        $conn->beginTransaction();

        // Insere o modelo
        $stmt = $conn->prepare("
            INSERT INTO modelos_de_orcamento (nome_modelo, descricao, criado_por)
            VALUES (:nome_modelo, :descricao, :criado_por)
        ");
        $stmt->bindParam(':nome_modelo', $nome_modelo);
        $stmt->bindParam(':descricao', $descricao_modelo);
        $stmt->bindParam(':criado_por', $criado_por);
        $stmt->execute();
        
        $modelo_id = $conn->lastInsertId();

        // Insere itens do modelo
        $stmtItem = $conn->prepare("
            INSERT INTO modelos_de_orcamento_itens 
            (modelo_id, ato, quantidade, desconto_legal, descricao, emolumentos, ferc, fadep, femp, total)
            VALUES (:modelo_id, :ato, :quantidade, :desconto_legal, :descricao, :emolumentos, :ferc, :fadep, :femp, :total)
        ");

        foreach ($itens as $item) {
            $ato            = $item['ato'];
            $quantidade     = $item['quantidade'];
            $desconto_legal = $item['desconto_legal'];
            $descricao      = $item['descricao'];
            // Convertendo vírgula em ponto
            $emolumentos    = str_replace(',', '.', $item['emolumentos']);
            $ferc           = str_replace(',', '.', $item['ferc']);
            $fadep          = str_replace(',', '.', $item['fadep']);
            $femp           = str_replace(',', '.', $item['femp']);
            $total          = str_replace(',', '.', $item['total']);

            $stmtItem->bindParam(':modelo_id', $modelo_id);
            $stmtItem->bindParam(':ato', $ato);
            $stmtItem->bindParam(':quantidade', $quantidade);
            $stmtItem->bindParam(':desconto_legal', $desconto_legal);
            $stmtItem->bindParam(':descricao', $descricao);
            $stmtItem->bindParam(':emolumentos', $emolumentos);
            $stmtItem->bindParam(':ferc', $ferc);
            $stmtItem->bindParam(':fadep', $fadep);
            $stmtItem->bindParam(':femp', $femp);
            $stmtItem->bindParam(':total', $total);

            $stmtItem->execute();
        }

        $conn->commit();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['error' => 'Erro ao salvar o modelo: '.$e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
