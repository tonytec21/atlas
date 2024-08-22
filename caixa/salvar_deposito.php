<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');
ob_start();

$response = ['success' => false, 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = getDatabaseConnection();

        $funcionario = trim(str_replace(' ', '', $_POST['funcionario_deposito']));
        $data_caixa = $_POST['data_caixa_deposito'];
        $valor_do_deposito = str_replace(['.', ','], ['', '.'], $_POST['valor_deposito']);
        $tipo_deposito = $_POST['tipo_deposito'];

        // Definindo o diretório alvo
        $target_dir = __DIR__ . "/anexos/" . date('d-m-y', strtotime($data_caixa)) . "/" . $funcionario . "/";
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                throw new Exception("Falha ao criar diretório: " . $target_dir);
            }
        }

        // Definindo o caminho do arquivo
        $file_name = basename($_FILES["comprovante_deposito"]["name"]);
        $target_file = $target_dir . $file_name;

        // Movendo o arquivo carregado
        if (!move_uploaded_file($_FILES["comprovante_deposito"]["tmp_name"], $target_file)) {
            throw new Exception("Falha ao mover o arquivo carregado para o diretório alvo.");
        }

        // Preparando a declaração SQL
        $stmt = $conn->prepare('INSERT INTO deposito_caixa (funcionario, data_caixa, valor_do_deposito, tipo_deposito, caminho_anexo) VALUES (:funcionario, :data_caixa, :valor_do_deposito, :tipo_deposito, :caminho_anexo)');
        $stmt->bindParam(':funcionario', $funcionario);
        $stmt->bindParam(':data_caixa', $data_caixa);
        $stmt->bindParam(':valor_do_deposito', $valor_do_deposito);
        $stmt->bindParam(':tipo_deposito', $tipo_deposito);
        $stmt->bindParam(':caminho_anexo', $file_name);

        // Executando a declaração
        if (!$stmt->execute()) {
            throw new Exception("Falha ao executar a consulta no banco de dados.");
        }

        $response['success'] = true;
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }
}

ob_end_clean();
echo json_encode($response);
exit;
?>
