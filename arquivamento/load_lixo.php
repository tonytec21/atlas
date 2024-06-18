<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'];
    $jsonFile = 'lixeira/' . $id . '.json';

    if (file_exists($jsonFile)) {
        $ato = json_decode(file_get_contents($jsonFile), true);
        echo json_encode($ato);
    } else {
        echo json_encode(['error' => 'Ato não encontrado']);
    }
}
?>
