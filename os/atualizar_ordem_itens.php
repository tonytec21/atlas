<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ordem = $_POST['ordem'];

    try {
        $conn = getDatabaseConnection();

        foreach ($ordem as $item) {
            $stmt = $conn->prepare("UPDATE ordens_de_servico_itens SET ordem_exibicao = :ordem WHERE id = :id");
            $stmt->bindParam(':ordem', $item['ordem'], PDO::PARAM_INT);
            $stmt->bindParam(':id', $item['id'], PDO::PARAM_INT);
            $stmt->execute();
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erro ao atualizar a ordem: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>
