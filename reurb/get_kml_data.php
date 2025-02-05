<?php
require_once 'db_connection_kml.php';

// Configurar o cabeçalho para retornar JSON
header('Content-Type: application/json');

try {
    // Consulta os dados do banco
    $query = "SELECT name, coordinates FROM kml_data";
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Verifica se os dados foram encontrados
    if (!$data || count($data) === 0) {
        echo json_encode([]);
        exit;
    }

    // Retorna os dados como JSON
    echo json_encode($data);
} catch (Exception $e) {
    // Retorna erro em formato JSON
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao buscar dados do banco de dados: ' . $e->getMessage()
    ]);
    exit;
}
