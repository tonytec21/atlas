<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $jsonFile = __DIR__ . '/meta-dados/' . $id . '.json';
    $uploadDir = __DIR__ . '/arquivos/' . $id;
    $lixeiraDir = __DIR__ . '/lixeira';
    $username = $_SESSION['username']; // Obtendo o nome do usuário da sessão
    $deletionTime = date('Y-m-d H:i:s'); // Obtendo a data e hora da exclusão

    if (!file_exists($lixeiraDir)) {
        mkdir($lixeiraDir, 0777, true);
    }

    if (file_exists($jsonFile)) {
        // Lendo o conteúdo do arquivo JSON
        $jsonContent = file_get_contents($jsonFile);
        $data = json_decode($jsonContent, true);

        // Adicionando informações de exclusão
        $data['excluido_por'] = $username;
        $data['data_exclusao'] = $deletionTime;

        // Escrevendo as informações atualizadas no arquivo JSON
        $jsonLixeira = $lixeiraDir . '/' . $id . '.json';
        file_put_contents($jsonLixeira, json_encode($data, JSON_PRETTY_PRINT));

        // Movendo o arquivo JSON para a lixeira
        unlink($jsonFile);

        // Movendo a pasta de anexos para a lixeira
        if (is_dir($uploadDir)) {
            $uploadLixeira = $lixeiraDir . '/' . $id;
            rename($uploadDir, $uploadLixeira);
        }

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['error' => 'Ato não encontrado']);
    }
}
?>
