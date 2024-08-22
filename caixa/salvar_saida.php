<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $titulo = $_POST['titulo'];
    $valor_saida = str_replace(',', '.', str_replace('.', '', $_POST['valor_saida']));
    $forma_de_saida = $_POST['forma_de_saida'];
    $data = $_POST['data_saida'];
    $data_caixa = $_POST['data_caixa_saida'];
    $funcionario = trim(str_replace(' ', '', $_POST['funcionario_saida']));
    $status = 'ativo';

    // Upload de arquivo
    if (!empty($_FILES['anexo']['name'])) {
        $targetDir = "anexos/" . $_POST['data_caixa_saida'] . "/" . $funcionario . "/saidas/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $targetFile = $targetDir . basename($_FILES['anexo']['name']);
        move_uploaded_file($_FILES['anexo']['tmp_name'], $targetFile);
        $caminho_anexo = basename($_FILES['anexo']['name']);
    } else {
        $caminho_anexo = null;
    }

    $conn = getDatabaseConnection();

    $sql = 'INSERT INTO saidas_despesas (titulo, valor_saida, forma_de_saida, data, data_caixa, funcionario, caminho_anexo, status) VALUES (:titulo, :valor_saida, :forma_de_saida, :data, :data_caixa, :funcionario, :caminho_anexo, :status)';
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
    echo json_encode(['error' => $e->getMessage()]);
}
?>
