<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/base_calculo_lib.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $modelo_id       = $_POST['id']; // ID do modelo a editar
    $nome_modelo     = $_POST['nome_modelo'];
    $descricao_modelo= $_POST['descricao_modelo'];
    $itens           = $_POST['itens'];

    if (empty($modelo_id)) {
        echo json_encode(['error' => 'ID do modelo não informado.']);
        exit;
    }

    try {
        $conn = getDatabaseConnection();
        // ensureFerrfisColumnExists($conn);
        $conn->beginTransaction();

        // Atualiza o modelo principal
        $stmt = $conn->prepare("
            UPDATE modelos_de_orcamento
               SET nome_modelo = :nome_modelo,
                   descricao   = :descricao
             WHERE id = :id
        ");
        $stmt->bindParam(':nome_modelo', $nome_modelo);
        $stmt->bindParam(':descricao', $descricao_modelo);
        $stmt->bindParam(':id', $modelo_id);
        $stmt->execute();

        // Remove itens antigos
        $stmtDel = $conn->prepare("DELETE FROM modelos_de_orcamento_itens WHERE modelo_id = :id");
        $stmtDel->bindParam(':id', $modelo_id);
        $stmtDel->execute();

        // Insere itens novamente
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
            /* Normalização dos valores vindos da tela.
               O JS envia o TEXTO da célula, em formato brasileiro. A conversão
               antiga (str_replace de ',' por '.') não removia o separador de
               MILHAR: "8.388,96" virava "8.388.96" e o MySQL, ao converter para
               DECIMAL, parava no segundo ponto e gravava 8,39.
               Só aparecia acima de R$ 1.000,00 — por isso passou despercebido
               até entrarem os atos de valor declarado.
               bc_valor() trata os dois formatos ("8.388,96" e "8388.96"). */
            $emolumentos    = bc_valor($item['emolumentos']);
            $ferc           = bc_valor($item['ferc']);
            $fadep          = bc_valor($item['fadep']);
            $femp           = bc_valor($item['femp']);
            $ferrfis        = bc_valor($item['ferrfis'] ?? '0');
            $total          = bc_valor($item['total']);

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
        echo json_encode(['error' => 'Erro ao atualizar o modelo: '.$e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido.']);
}
