<?php
// Inclui os arquivos de conexão e sessão
include 'db_connection.php';
include 'session_check.php';
checkSession();

// Obtém o nome do usuário da sessão
$username = $_SESSION['username'];
$userDirectory = 'lembretes/' . $username;

// Verifica se a solicitação é POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noteId = $_POST['note_id'] ?? '';
    $newColor = $_POST['color'] ?? '';

    // Verifica se o ID da nota e a cor foram fornecidos
    if (empty($noteId) || empty($newColor)) {
        echo json_encode(['status' => 'error', 'message' => 'ID da nota ou cor não fornecidos.']);
        exit();
    }

    // Caminho do arquivo de cor individual para a nota
    $colorFile = $userDirectory . '/' . $noteId . '.json';

    // Dados a serem salvos no arquivo JSON
    $colorData = [
        'id' => $noteId,
        'cor' => $newColor
    ];

    // Salva ou atualiza o arquivo JSON com a nova cor
    if (file_put_contents($colorFile, json_encode($colorData, JSON_PRETTY_PRINT))) {
        echo json_encode(['status' => 'success', 'message' => 'Cor atualizada com sucesso.', 'color' => $newColor]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar a cor no arquivo JSON.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método de solicitação inválido.']);
}
?>
