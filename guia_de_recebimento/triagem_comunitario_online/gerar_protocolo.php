<?php
include(__DIR__ . '/db_connection.php');

// Verificando se a cidade foi enviada
if (!isset($_POST['cidade']) || empty($_POST['cidade'])) {
    echo json_encode(['success' => false, 'message' => 'Cidade não informada.']);
    exit;
}

$cidade = $_POST['cidade'];

// Definindo a sigla da cidade
$sigla = match ($cidade) {
    'São Roberto' => 'SR',
    'São Raimundo do Doca Bezerra' => 'SRDB',
    'Esperantinópolis' => 'EP',
    default => '',
};

// Verificando se a cidade é válida
if (empty($sigla)) {
    echo json_encode(['success' => false, 'message' => 'Cidade inválida.']);
    exit;
}

// Preparando a query para encontrar o próximo número de protocolo para a cidade
$query = "SELECT COUNT(*) + 1 AS numero FROM triagem_comunitario WHERE cidade = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $cidade);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

// Gerando o número do protocolo com 3 dígitos + sigla da cidade
if ($result) {
    $numero = str_pad($result['numero'], 3, '0', STR_PAD_LEFT); // Ex: 001, 002, 003...
    $protocolo = $numero . $sigla; // Ex: 001SR, 002SR, 001SRDB
    echo $protocolo;
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao gerar número do protocolo.']);
}
?>
