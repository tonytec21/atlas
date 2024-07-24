<?php
include(__DIR__ . '/db_connection.php');

if (isset($_GET['ato'])) {
    $ato = $_GET['ato'];

    try {
        $conn = getDatabaseConnection();
        $stmt = $conn->prepare("SELECT * FROM tabela_emolumentos WHERE ATO = :ato");
        $stmt->bindParam(':ato', $ato);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        if ($result) {
            echo json_encode($result);
        } else {
            echo json_encode(['error' => 'Ato não encontrado']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Erro ao buscar o ato: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Parâmetro ato não fornecido']);
}
?>
