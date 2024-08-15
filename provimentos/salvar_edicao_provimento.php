<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $numero_provimento = $_POST['numero_provimento'];
    $origem = $_POST['origem'];
    $data_provimento = $_POST['data_provimento'];
    $descricao = $_POST['descricao'];
    $funcionario = $_SESSION['username'];
    $data_cadastro = date('Y-m-d H:i:s');
    $status = 'Ativo';

    $conn = getDatabaseConnection();
    
    // Verificação de duplicidade, excluindo o próprio registro
    $stmt = $conn->prepare('SELECT COUNT(*) FROM provimentos WHERE numero_provimento = :numero_provimento AND origem = :origem AND data_provimento = :data_provimento AND id != :id');
    $stmt->bindParam(':numero_provimento', $numero_provimento);
    $stmt->bindParam(':origem', $origem);
    $stmt->bindParam(':data_provimento', $data_provimento);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $exists = $stmt->fetchColumn();

    if ($exists > 0) {
        echo json_encode(['success' => false, 'message' => 'Já existe outro provimento com o mesmo número, origem e data.']);
        exit;
    }

    // Lidar com o upload do novo anexo
    $anexo = $_FILES['anexo'];
    if ($anexo['error'] === UPLOAD_ERR_OK) {
        // Primeiro, remova o anexo anterior, se existir
        $stmt = $conn->prepare('SELECT caminho_anexo FROM provimentos WHERE id = :id');
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $provimento = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($provimento && $provimento['caminho_anexo']) {
            if (file_exists($provimento['caminho_anexo'])) {
                unlink($provimento['caminho_anexo']);
            }
        }

        $extensao = pathinfo($anexo['name'], PATHINFO_EXTENSION);
        $nome_anexo = $numero_provimento . '.' . $extensao;
        $ano = date('Y', strtotime($data_provimento));
        $diretorio_anexo = 'anexo/' . str_replace('/', '_', $origem) . '/' . $ano . '/';

        if (!is_dir(__DIR__ . '/' . $diretorio_anexo)) {
            mkdir(__DIR__ . '/' . $diretorio_anexo, 0777, true);
        }

        $caminho_anexo = $diretorio_anexo . $nome_anexo;

        if (!move_uploaded_file($anexo['tmp_name'], __DIR__ . '/' . $caminho_anexo)) {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar o novo anexo.']);
            exit;
        }
    } else {
        $caminho_anexo = $provimento['caminho_anexo']; // Mantém o caminho anterior se nenhum novo anexo foi enviado
    }

    // Atualiza o provimento
    $stmt = $conn->prepare('UPDATE provimentos SET numero_provimento = :numero_provimento, origem = :origem, descricao = :descricao, data_provimento = :data_provimento, caminho_anexo = :caminho_anexo, funcionario = :funcionario, data_cadastro = :data_cadastro WHERE id = :id');
    $stmt->bindParam(':numero_provimento', $numero_provimento);
    $stmt->bindParam(':origem', $origem);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':data_provimento', $data_provimento);
    $stmt->bindParam(':caminho_anexo', $caminho_anexo);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':data_cadastro', $data_cadastro);
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Provimento atualizado com sucesso.'];
        echo json_encode($response);
    } else {
        $response = ['success' => false, 'message' => 'Erro ao atualizar o provimento.'];
        echo json_encode($response);
    }    
}
?>
