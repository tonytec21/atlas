<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    if (empty($id)) {
        echo json_encode(['error' => 'ID do modelo não informado.']);
        exit;
    }

    try {
        $conn = getDatabaseConnection();
        $conn->beginTransaction();

        // Primeiro removemos os itens
        $stmtItens = $conn->prepare("DELETE FROM modelos_de_orcamento_itens WHERE modelo_id = :id");
        $stmtItens->bindParam(':id', $id);
        $stmtItens->execute();

        // Removemos o modelo em si
        $stmtModelo = $conn->prepare("DELETE FROM modelos_de_orcamento WHERE id = :id");
        $stmtModelo->bindParam(':id', $id);
        $stmtModelo->execute();

        $conn->commit();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['error' => 'Erro ao excluir modelo: '.$e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido.']);
}
