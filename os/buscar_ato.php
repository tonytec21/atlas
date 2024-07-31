<?php
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json'); // Garante que o conteúdo retornado seja sempre JSON

if (isset($_GET['ato'])) {
    $ato = $_GET['ato'];
    error_log('Ato recebido: ' . $ato); // Log do ato recebido

    try {
        $conn = getDatabaseConnection();
        $stmt = $conn->prepare("SELECT * FROM tabela_emolumentos WHERE ATO = :ato");
        $stmt->bindParam(':ato', $ato);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            error_log('Resultado encontrado: ' . json_encode($result)); // Log do resultado encontrado
            echo json_encode($result);
        } else {
            error_log('Ato não encontrado: ' . $ato); // Log do ato não encontrado
            echo json_encode(['error' => 'Ato não encontrado']);
        }
    } catch (PDOException $e) {
        error_log('Erro ao buscar o ato: ' . $e->getMessage()); // Adiciona log de erro
        echo json_encode(['error' => 'Erro ao buscar o ato: ' . $e->getMessage()]);
    }
} else {
    error_log('Parâmetro ato não fornecido'); // Log do parâmetro não fornecido
    echo json_encode(['error' => 'Parâmetro ato não fornecido']);
}
?>
