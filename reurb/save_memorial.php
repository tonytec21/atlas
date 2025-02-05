<?php
require_once 'db_connection_kml.php';

header('Content-Type: application/json');

// Verificar se os dados foram enviados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['coordinates'])) {
    $name = $_POST['name'];
    $rawMemorial = $_POST['coordinates'];

    // Extrair coordenadas UTM do memorial usando regex
    preg_match_all('/N\s*([\d,.]+)[^\d]*E\s*([\d,.]+)/i', $rawMemorial, $matches, PREG_SET_ORDER);

    if (empty($matches)) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma coordenada encontrada no memorial descritivo.']);
        exit;
    }

    // Processar coordenadas extraídas
    $coordinates = [];
    foreach ($matches as $match) {
        // Limpar e formatar coordenadas
        $north = str_replace(['.', ','], '', $match[1]); // Remove pontos e vírgulas
        $east = str_replace(['.', ','], '', $match[2]);

        // Inserir ponto decimal no lugar correto (UTM usa três casas decimais)
        $north = substr_replace($north, '.', -2, 0); // Exemplo: 971903518 → 9719035.18
        $east = substr_replace($east, '.', -2, 0);

        $coordinates[] = "$east,$north"; // Formato "E,N" para coordenadas UTM
    }

    // Combinar coordenadas em uma única string separada por espaços
    $coordinatesString = implode(' ', $coordinates);

    try {
        // Salvar no banco de dados
        $stmt = $pdo->prepare("INSERT INTO memorial_data (name, coordinates) VALUES (?, ?)");
        $stmt->execute([$name, $coordinatesString]);

        echo json_encode(['success' => true, 'message' => 'Coordenadas extraídas e salvas com sucesso.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco de dados: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
}
