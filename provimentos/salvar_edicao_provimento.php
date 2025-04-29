<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');   

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDatabaseConnection();

    $id = intval($_POST['id']);
    $numero_provimento = $_POST['numero_provimento'];
    $origem = $_POST['origem'];
    $data_provimento = $_POST['data_provimento'];
    $descricao = $_POST['descricao'];
    $tipo = $_POST['tipo'];
    $conteudo_anexo = !empty($_POST['conteudo_anexo']) ? $_POST['conteudo_anexo'] : null;
    $ano = date('Y', strtotime($data_provimento));
    $funcionario = $_SESSION['username'];
    $data_atualizacao = date('Y-m-d H:i:s');

    $stmt = $conn->prepare('SELECT caminho_anexo FROM provimentos WHERE id = :id');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $provimento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$provimento) {
        echo json_encode(['success' => false, 'message' => 'Provimento não encontrado.']);
        exit;
    }

    $caminho_anexo = $provimento['caminho_anexo'];

    if (isset($_POST['remover_anexo']) && $_POST['remover_anexo'] == '1' && !empty($caminho_anexo)) {
        if (file_exists(__DIR__ . '/' . $caminho_anexo)) {
            unlink(__DIR__ . '/' . $caminho_anexo);
        }
        $caminho_anexo = null;
    }

    if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] == 0) {
        $extensao = pathinfo($_FILES['anexo']['name'], PATHINFO_EXTENSION);
        $novo_nome = $numero_provimento . '.' . $extensao;
        $diretorio = 'anexo/' . str_replace('/', '_', $origem) . '/' . $tipo . '/' . $ano . '/';

        if (!is_dir(__DIR__ . '/' . $diretorio)) {
            mkdir(__DIR__ . '/' . $diretorio, 0777, true);
        }

        $novo_caminho = $diretorio . $novo_nome;

        if (move_uploaded_file($_FILES['anexo']['tmp_name'], __DIR__ . '/' . $novo_caminho)) {
            $caminho_anexo = $novo_caminho;
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar o novo anexo.']);
            exit;
        }
    }

    try {
        $stmt = $conn->prepare('UPDATE provimentos SET numero_provimento = :numero_provimento, origem = :origem, descricao = :descricao, data_provimento = :data_provimento, caminho_anexo = :caminho_anexo, tipo = :tipo, funcionario = :funcionario, data_cadastro = :data_cadastro, conteudo_anexo = :conteudo_anexo WHERE id = :id');
        $stmt->bindParam(':numero_provimento', $numero_provimento);
        $stmt->bindParam(':origem', $origem);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':data_provimento', $data_provimento);
        $stmt->bindParam(':caminho_anexo', $caminho_anexo);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':funcionario', $funcionario);
        $stmt->bindParam(':data_cadastro', $data_atualizacao);
        $stmt->bindParam(':conteudo_anexo', $conteudo_anexo);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Provimento atualizado com sucesso!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
