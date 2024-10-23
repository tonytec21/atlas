<?php
include(__DIR__ . '/db_connection.php');

// Definindo cabeçalho JSON para resposta adequada
header('Content-Type: application/json; charset=UTF-8');

// Função para responder com JSON e encerrar execução
function jsonResponse($success, $message = '', $error = '') {
    echo json_encode(['success' => $success, 'message' => $message, 'error' => $error]);
    exit;
}

// Verificar se ID foi enviado
$id = $_POST['id'] ?? null;
if (!$id) {
    jsonResponse(false, 'ID não fornecido.');
}

// Valores recebidos do formulário, com padrão nulo para campos opcionais
$pedidoDeferido = $_POST['pedido_deferido'] ?? null;
$cadastroEfetivado = $_POST['cadastro_efetivado'] ?? null;
$processoConcluido = $_POST['processo_concluido'] ?? null;
$habilitacaoConcluida = $_POST['habilitacao_concluida'] ?? null;
$numeroProclamas = $_POST['numero_proclamas'] ?? null;

// Preparar query dinâmica para atualizar apenas campos enviados
$query = "UPDATE triagem_comunitario SET ";
$updates = [];
$params = [];
$types = '';

// Adiciona campos à query apenas se forem fornecidos
if (!is_null($pedidoDeferido)) {
    $updates[] = "pedido_deferido = ?";
    $params[] = $pedidoDeferido;
    $types .= 'i';
}
if (!is_null($cadastroEfetivado)) {
    $updates[] = "cadastro_efetivado = ?";
    $params[] = $cadastroEfetivado;
    $types .= 'i';
}
if (!is_null($processoConcluido)) {
    $updates[] = "processo_concluido = ?";
    $params[] = $processoConcluido;
    $types .= 'i';
}
if (!is_null($habilitacaoConcluida)) {
    $updates[] = "habilitacao_concluida = ?";
    $params[] = $habilitacaoConcluida;
    $types .= 'i';
}
if (!is_null($numeroProclamas)) {
    $updates[] = "numero_proclamas = ?";
    $params[] = $numeroProclamas;
    $types .= 'i';
}

// Verifica se há algo para atualizar
if (empty($updates)) {
    jsonResponse(false, 'Nenhum campo para atualizar.');
}

// Finaliza a query e adiciona o ID
$query .= implode(', ', $updates) . " WHERE id = ?";
$params[] = $id;
$types .= 'i';

// Preparar e executar a query
$stmt = $conn->prepare($query);
if (!$stmt) {
    jsonResponse(false, 'Erro na preparação da query.', $conn->error);
}

$stmt->bind_param($types, ...$params);
if ($stmt->execute()) {
    jsonResponse(true, 'Situação salva com sucesso.');
} else {
    jsonResponse(false, 'Erro ao salvar a situação.', $stmt->error);
}
?>
