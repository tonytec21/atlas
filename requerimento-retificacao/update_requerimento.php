<?php
include(__DIR__ . '/session_check.php');
include(__DIR__ . '/db_connection.php');
checkSession();

// Verificar se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $requerente = $_POST['requerente'];
    $qualificacao = $_POST['qualificacao'];
    $motivo = $_POST['motivo'];
    $peticao = $_POST['peticao'];
    $criado_por = $_POST['criado_por'];

    // Certifique-se de que os dados sejam escapados corretamente
    $requerente = $conn->real_escape_string($requerente);
    $qualificacao = $conn->real_escape_string($qualificacao);
    $motivo = $conn->real_escape_string($motivo);
    $peticao = $conn->real_escape_string($peticao);
    $criado_por = $conn->real_escape_string($criado_por);

    // Preparar e executar a atualização no banco de dados
    $stmt = $conn->prepare("UPDATE requerimentos SET requerente = ?, qualificacao = ?, motivo = ?, peticao = ?, criado_por = ? WHERE id = ?");
    $stmt->bind_param('sssssi', $requerente, $qualificacao, $motivo, $peticao, $criado_por, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => 'Dados salvos com sucesso']);
    } else {
        echo json_encode(['error' => 'Erro ao salvar os dados: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['error' => 'Método de requisição inválido']);
}

$conn->close();
