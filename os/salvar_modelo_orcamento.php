<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/**
 * Garante que a coluna FERRFIS exista na tabela modelos_de_orcamento_itens.
 */
function ensureFerrfisColumnExists(PDO $conn): void {
    $stmtCol = $conn->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'modelos_de_orcamento_itens'
          AND COLUMN_NAME = 'ferrfis'
    ");
    $stmtCol->execute();
    $colExists = (int)$stmtCol->fetchColumn() > 0;

    if (!$colExists) {
        $conn->exec("ALTER TABLE modelos_de_orcamento_itens ADD COLUMN ferrfis DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER femp");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_modelo       = $_POST['nome_modelo'];
    $descricao_modelo  = $_POST['descricao_modelo'];
    $itens             = $_POST['itens'];
    $criado_por        = $_SESSION['username'];

    try {
        $conn = getDatabaseConnection();
        ensureFerrfisColumnExists($conn);
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
            (modelo_id, ato, quantidade, desconto_legal, descricao, emolumentos, ferc, fadep, femp, ferrfis, total)
            VALUES (:modelo_id, :ato, :quantidade, :desconto_legal, :descricao, :emolumentos, :ferc, :fadep, :femp, :ferrfis, :total)
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
            $ferrfis        = str_replace(',', '.', ($item['ferrfis'] ?? '0'));
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
            $stmtItem->bindParam(':ferrfis', $ferrfis);
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
