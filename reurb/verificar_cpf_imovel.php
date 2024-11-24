<?php
header('Content-Type: application/json');
include(__DIR__ . '/db_connection.php');

$cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$response = ['success' => false, 'message' => 'CPF não encontrado.'];

if ($cpf) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                proprietario_nome 
            FROM 
                cadastro_de_imoveis 
            WHERE 
                proprietario_cpf = ? OR cpf_conjuge = ?
        ");
        if (!$stmt) {
            throw new Exception('Erro ao preparar consulta: ' . $conn->error);
        }

        $stmt->bind_param("ss", $cpf, $cpf);
        $stmt->execute();
        $stmt->bind_result($proprietarioNome);

        if ($stmt->fetch()) {
            $response = [
                'success' => true,
                'imovelExistente' => true,
                'message' => "Já existe um imóvel cadastrado para o CPF {$cpf}."
            ];
        } else {
            $response = ['success' => true, 'imovelExistente' => false];
        }

        $stmt->close();
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
