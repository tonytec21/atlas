<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];
    $total_os = str_replace(',', '.', $_POST['total_os']);

    try {
        $conn = getDatabaseConnection();

        // Atualiza o total da OS na tabela `ordens_de_servico`
        $stmt = $conn->prepare("UPDATE ordens_de_servico SET total_os = :total_os WHERE id = :id");
        $stmt->bindParam(':total_os', $total_os);
        $stmt->bindParam(':id', $os_id);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erro ao atualizar o total da OS: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>
