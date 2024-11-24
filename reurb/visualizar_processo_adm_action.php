<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');
$response = ['success' => false];

try {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new Exception('ID não fornecido.');
    }

    $stmt = $conn->prepare("
        SELECT 
            id, 
            processo_adm, 
            municipio, 
            qualificacao_municipio,
            representante, 
            qualificacao_representante, 
            DATE_FORMAT(data_de_publicacao, '%d/%m/%Y') AS data_de_publicacao, 
            classificacao_individual, 
            direito_real_outorgado, 
            edital,
            DATE_FORMAT(data_edital, '%d/%m/%Y') AS data_edital,
            responsavel_tecnico,
            qualificacao_responsavel_tecnico,
            matricula_mae,
            oficial_do_registro,
            cargo_oficial,
            funcionario, 
            DATE_FORMAT(data_cadastro, '%d/%m/%Y') AS data_cadastro
        FROM 
            cadastro_de_processo_adm 
        WHERE 
            id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Processo não encontrado.');
    }

    $response['data'] = $result->fetch_assoc();
    $response['success'] = true;
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
