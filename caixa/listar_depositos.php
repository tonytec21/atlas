<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$response = ['success' => false, 'depositos' => [], 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    try {
        $conn = getDatabaseConnection();

        $funcionario = $_GET['funcionarios'];
        $data_caixa = $_GET['data'];

        $stmt = $conn->prepare('SELECT id, funcionario, data_caixa, data_cadastro, valor_do_deposito, tipo_deposito, caminho_anexo FROM deposito_caixa WHERE funcionario = :funcionario AND data_caixa = :data_caixa AND status = "ativo"');
        $stmt->bindParam(':funcionario', $funcionario);
        $stmt->bindParam(':data_caixa', $data_caixa);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['depositos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("Falha ao executar a consulta no banco de dados.");
        }
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
