<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_provimento = $_POST['numero_provimento'];
    $origem = $_POST['origem'];
    $data_provimento = $_POST['data_provimento'];
    $descricao = $_POST['descricao'];
    $tipo = $_POST['tipo']; // Novo campo para o tipo (Provimento ou Resolução)
    $ano = date('Y', strtotime($data_provimento));
    $funcionario = $_SESSION['username'];
    $data_cadastro = date('Y-m-d H:i:s');
    $status = 'Ativo';

    // Verificação de duplicidade
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare('SELECT COUNT(*) FROM provimentos WHERE numero_provimento = :numero_provimento AND origem = :origem AND data_provimento = :data_provimento AND tipo = :tipo');
    $stmt->bindParam(':numero_provimento', $numero_provimento);
    $stmt->bindParam(':origem', $origem);
    $stmt->bindParam(':data_provimento', $data_provimento);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->execute();
    $exists = $stmt->fetchColumn();

    if ($exists > 0) {
        echo json_encode(['success' => false, 'message' => 'O provimento já está cadastrado.']);
        exit;
    }

    $anexo = $_FILES['anexo'];
    $extensao = pathinfo($anexo['name'], PATHINFO_EXTENSION);
    $nome_anexo = $numero_provimento . '.' . $extensao;
    $diretorio_anexo = 'anexo/' . str_replace('/', '_', $origem) . '/' . $tipo . '/' . $ano . '/';

    if (!is_dir(__DIR__ . '/' . $diretorio_anexo)) {
        mkdir(__DIR__ . '/' . $diretorio_anexo, 0777, true);
    }

    $caminho_anexo = $diretorio_anexo . $nome_anexo;

    if (move_uploaded_file($anexo['tmp_name'], __DIR__ . '/' . $caminho_anexo)) {
        try {
            $stmt = $conn->prepare('INSERT INTO provimentos (numero_provimento, origem, descricao, data_provimento, caminho_anexo, tipo, funcionario, data_cadastro, status) VALUES (:numero_provimento, :origem, :descricao, :data_provimento, :caminho_anexo, :tipo, :funcionario, :data_cadastro, :status)');
            $stmt->bindParam(':numero_provimento', $numero_provimento);
            $stmt->bindParam(':origem', $origem);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':data_provimento', $data_provimento);
            $stmt->bindParam(':caminho_anexo', $caminho_anexo);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':funcionario', $funcionario);
            $stmt->bindParam(':data_cadastro', $data_cadastro);
            $stmt->bindParam(':status', $status);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Provimento cadastrado com sucesso!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao mover o anexo para o diretório destino.']);
    }
}
?>
