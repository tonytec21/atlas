<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];
    $funcionario = $_POST['funcionario'];
    $status = 'ativo';

    $target_dir = __DIR__ . '/anexos/' . $os_id . '/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $success = true;
    $errors = [];

    foreach ($_FILES['novo_anexo']['tmp_name'] as $index => $tmp_name) {
        if ($_FILES['novo_anexo']['error'][$index] == UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['novo_anexo']['tmp_name'][$index];
            $file_name = basename($_FILES['novo_anexo']['name'][$index]);
            $target_file = $target_dir . $file_name;

            if (move_uploaded_file($file_tmp, $target_file)) {
                $conn = getDatabaseConnection();

                try {
                    $stmt = $conn->prepare("INSERT INTO anexos_os (ordem_servico_id, caminho_anexo, data, funcionario, status) VALUES (:ordem_servico_id, :caminho_anexo, NOW(), :funcionario, :status)");
                    $stmt->bindParam(':ordem_servico_id', $os_id);
                    $stmt->bindParam(':caminho_anexo', $file_name);
                    $stmt->bindParam(':funcionario', $funcionario);
                    $stmt->bindParam(':status', $status);
                    $stmt->execute();
                } catch (PDOException $e) {
                    $success = false;
                    $errors[] = $e->getMessage();
                }
            } else {
                $success = false;
                $errors[] = 'Erro ao mover o arquivo ' . $file_name;
            }
        } else {
            $success = false;
            $errors[] = 'Erro ao enviar o arquivo ' . $_FILES['novo_anexo']['name'][$index];
        }
    }

    echo json_encode(['success' => $success, 'message' => $success ? 'Anexo salvo com sucesso!' : implode(', ', $errors)]);
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
}
?>
