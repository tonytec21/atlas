<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

// Definir timezone corretamente
date_default_timezone_set('America/Sao_Paulo');

try {
    // Dados recebidos
    $titulo          = trim($_POST['titulo']);
    $valor_saida     = str_replace(',', '.', str_replace('.', '', $_POST['valor_saida']));
    $forma_de_saida  = trim($_POST['forma_de_saida']);
    $data            = $_POST['data_saida'];
    $data_caixa      = $_POST['data_caixa_saida'];
    $funcionario     = trim(str_replace(' ', '', $_POST['funcionario_saida']));
    $status          = 'ativo';

    // Processamento do upload de anexo
    $caminho_anexo = null;
    if (!empty($_FILES['anexo']['name'])) {
        $targetDir = "anexos/" . $data_caixa . "/" . $funcionario . "/saidas/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = basename($_FILES['anexo']['name']);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES['anexo']['tmp_name'], $targetFile)) {
            $caminho_anexo = $fileName;
        } else {
            throw new Exception('Erro ao fazer upload do anexo.');
        }
    }

    // Inserção no banco
    $conn = getDatabaseConnection();

    $sql = 'INSERT INTO saidas_despesas 
        (titulo, valor_saida, forma_de_saida, data, data_caixa, funcionario, caminho_anexo, status) 
        VALUES 
        (:titulo, :valor_saida, :forma_de_saida, :data, :data_caixa, :funcionario, :caminho_anexo, :status)';

    $stmt = $conn->prepare($sql);

    $stmt->bindParam(':titulo', $titulo);
    $stmt->bindParam(':valor_saida', $valor_saida);
    $stmt->bindParam(':forma_de_saida', $forma_de_saida);
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':caminho_anexo', $caminho_anexo);
    $stmt->bindParam(':status', $status);

    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
?>
