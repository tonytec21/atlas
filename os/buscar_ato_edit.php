<?php
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json'); // Garante que o conteúdo retornado seja sempre JSON

if (isset($_GET['ato']) && isset($_GET['tabela'])) {
    $ato = $_GET['ato'];
    $tabela = $_GET['tabela'];

    error_log('Ato recebido: ' . $ato); // Log do ato recebido
    error_log('Tabela utilizada: ' . $tabela); // Log da tabela recebida

    try {
        $conn = getDatabaseConnection();

        // Verifica se a tabela é permitida para evitar SQL Injection
        if (!in_array($tabela, ['tabela_emolumentos', 'tabela_emolumentos_2024'])) {
            throw new Exception('Tabela inválida.');
        }

        $stmt = $conn->prepare("SELECT * FROM $tabela WHERE ATO = :ato");
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
    } catch (Exception $e) {
        error_log('Erro geral: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    error_log('Parâmetro ato ou tabela não fornecido'); // Log do parâmetro não fornecido
    echo json_encode(['error' => 'Parâmetro ato ou tabela não fornecido']);
}
?>
