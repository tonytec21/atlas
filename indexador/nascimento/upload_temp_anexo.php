<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['arquivo_pdf'])) {
    $dir = 'anexos/temp/';
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $file_name = basename($_FILES['arquivo_pdf']['name']);
    $file_path = $dir . $file_name;

    if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $file_path)) {
        echo json_encode(['success' => true, 'file_path' => $file_path]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao mover o arquivo para o diretório temporário.']);
    }
}
?>
