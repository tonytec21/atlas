<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');
$response = ['success' => false, 'results' => []];

try {
    // Coleta os filtros
    $processoAdm = $_GET['processoAdm'] ?? null;
    $municipio = $_GET['municipio'] ?? null;
    $representante = $_GET['representante'] ?? null;
    $dataDePublicacao = $_GET['dataDePublicacao'] ?? null;

    // Monta a query dinamicamente
    $query = "
        SELECT 
            id, 
            processo_adm, 
            municipio, 
            representante, 
            DATE_FORMAT(data_de_publicacao, '%d/%m/%Y') AS data_de_publicacao 
        FROM 
            cadastro_de_processo_adm 
        WHERE 
            status = 'ativo'
    ";
    $params = [];
    $types = '';

    if ($processoAdm) {
        $query .= " AND processo_adm LIKE ?";
        $params[] = "%$processoAdm%";
        $types .= 's';
    }
    if ($municipio) {
        $query .= " AND municipio LIKE ?";
        $params[] = "%$municipio%";
        $types .= 's';
    }
    if ($representante) {
        $query .= " AND representante LIKE ?";
        $params[] = "%$representante%";
        $types .= 's';
    }
    if ($dataDePublicacao) {
        $query .= " AND data_de_publicacao = ?";
        $params[] = $dataDePublicacao;
        $types .= 's';
    }

    $stmt = $conn->prepare($query);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Prepara os resultados
    while ($row = $result->fetch_assoc()) {
        $response['results'][] = $row;
    }
    $response['success'] = true;
} catch (Exception $e) {
    $response['message'] = 'Erro ao buscar processos: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
