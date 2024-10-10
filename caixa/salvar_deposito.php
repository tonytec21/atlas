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

        // Verificando se o comprovante foi carregado ou se a opção "Sem comprovante" foi marcada
        if (isset($_POST['sem_comprovante']) && $_POST['sem_comprovante'] === 'on') {
            // Caso "Sem comprovante" tenha sido marcado
            $file_name = "sem_comprovante.php";
            $file_path = $target_dir . $file_name;

            // Criando o conteúdo PHP com a mensagem centralizada e layout completo
            $php_content = "<!DOCTYPE html>\n";
            $php_content .= "<html lang=\"pt-br\">\n";
            $php_content .= "<head>\n";
            $php_content .= "    <meta charset=\"UTF-8\">\n";
            $php_content .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
            $php_content .= "    <title>Atlas - Sem Comprovante</title>\n";
            $php_content .= "    <link rel=\"stylesheet\" href=\"../../../../style/css/bootstrap.min.css\">\n";
            $php_content .= "    <link rel=\"stylesheet\" href=\"../../../../style/css/style.css\">\n";
            $php_content .= "    <link rel=\"icon\" href=\"../../../../style/img/favicon.png\" type=\"image/png\">\n";
            $php_content .= "</head>\n";
            $php_content .= "<body class=\"light-mode\">\n";
            $php_content .= "    <?php include(__DIR__ . '/../../../../menu.php'); ?>\n";
            $php_content .= "    <div style=\"text-align: center; margin-top: 10%;\">\n";
            $php_content .= "        <h2>SEM COMPROVANTE/RECIBO - CAIXA DE " . strtoupper($funcionario) . " - DATA " . date('d/m/Y', strtotime($data_caixa)) . "</h2>\n";
            $php_content .= "    </div>\n";
            $php_content .= "</body>\n";
            $php_content .= "</html>";

            // Salvando o arquivo PHP
            if (!file_put_contents($file_path, $php_content)) {
                throw new Exception("Falha ao criar o arquivo 'sem_comprovante.php'.");
            }
        } elseif (isset($_FILES["comprovante_deposito"]) && $_FILES["comprovante_deposito"]["error"] === UPLOAD_ERR_OK) {
            // Processando o comprovante, se ele foi carregado
            $file_name = basename($_FILES["comprovante_deposito"]["name"]);
            $target_file = $target_dir . $file_name;

            // Movendo o arquivo carregado
            if (!move_uploaded_file($_FILES["comprovante_deposito"]["tmp_name"], $target_file)) {
                throw new Exception("Falha ao mover o arquivo carregado para o diretório alvo.");
            }
        } else {
            // Caso não tenha sido enviado nenhum arquivo e "Sem comprovante" não foi marcado
            throw new Exception("Nenhum comprovante foi enviado.");
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
