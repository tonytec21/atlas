<?php
include(__DIR__ . '/db_connection.php');
session_start();

// Verificar se o método da requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método de requisição inválido.']);
    exit;
}

// Obter o nome do funcionário logado
$funcionario = $_SESSION['nome_funcionario'] ?? 'Desconhecido';

// Verificar se existem arquivos enviados
if (!isset($_FILES['arquivos']) || empty($_FILES['arquivos']['name'][0])) {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo enviado.']);
    exit;
}

// Caminho base para salvar os anexos
$baseDir = __DIR__ . '/anexos/';
$baseRelativeDir = 'anexos/';
if (!file_exists($baseDir)) {
    mkdir($baseDir, 0777, true);
}

// Inicializar a conexão
$conn->begin_transaction();

try {
    foreach ($_FILES['arquivos']['name'] as $index => $fileName) {
        // Verificar se o arquivo segue o padrão esperado
        if (!preg_match('/^A(\d+)_Folha (\d+)_Termo (\d+)\.pdf$/', $fileName, $matches)) {
            throw new Exception("Arquivo $fileName não segue o padrão esperado.");
        }

        // Extrair os dados do nome do arquivo
        $livro = $matches[1];
        $folha = $matches[2];
        $termo = $matches[3];

        // Salvar o registro na tabela `indexador_nascimento`
        $stmt = $conn->prepare("
            INSERT INTO indexador_nascimento (termo, livro, folha, status, funcionario) 
            VALUES (?, ?, ?, 'ativo', ?)
        ");
        $stmt->bind_param('ssss', $termo, $livro, $folha, $funcionario);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception("Erro ao salvar registro para o arquivo $fileName.");
        }

        $lastId = $stmt->insert_id;

        // Salvar o anexo na tabela `indexador_nascimento_anexos`
        $targetDir = $baseDir . $lastId . '/';
        $relativeDir = $baseRelativeDir . $lastId . '/';
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $targetFilePath = $targetDir . basename($fileName);
        $relativeFilePath = $relativeDir . basename($fileName); // Caminho relativo

        if (!move_uploaded_file($_FILES['arquivos']['tmp_name'][$index], $targetFilePath)) {
            throw new Exception("Erro ao mover o arquivo $fileName.");
        }

        $stmtAnexo = $conn->prepare("
            INSERT INTO indexador_nascimento_anexos (id_nascimento, caminho_anexo, funcionario, status)
            VALUES (?, ?, ?, 'ativo')
        ");
        $stmtAnexo->bind_param('iss', $lastId, $relativeFilePath, $funcionario);
        $stmtAnexo->execute();

        if ($stmtAnexo->affected_rows === 0) {
            throw new Exception("Erro ao salvar anexo para o arquivo $fileName.");
        }
    }

    // Commit na transação
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Arquivos processados com sucesso.']);
} catch (Exception $e) {
    // Reverter alterações em caso de erro
    $conn->rollback();
    error_log($e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>
