<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    $jsonFile = 'meta-dados/registries.json';

    if (file_exists($jsonFile)) {
        $registries = json_decode(file_get_contents($jsonFile), true);
        if (isset($registries[$id])) {
            unset($registries[$id]);
            file_put_contents($jsonFile, json_encode(array_values($registries)));
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error', 'message' => 'Registro não encontrado'));
        }
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Arquivo de dados não encontrado'));
    }
}
?>
