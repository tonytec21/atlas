<?php
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $filePath = "meta-dados/$id.json";

    if (file_exists($filePath)) {
        $ato = json_decode(file_get_contents($filePath), true);
        echo json_encode($ato);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Ato não encontrado']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
}
?>
