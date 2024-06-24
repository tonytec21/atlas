<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_POST['file'];

    $metaDir = 'meta-dados/';
    $uploadDir = 'arquivos/' . pathinfo($file, PATHINFO_FILENAME) . '/';

    $filePath = $metaDir . $file;

    if (file_exists($filePath)) {
        unlink($filePath);

        // Remover os arquivos do subdiretório
        $files = glob($uploadDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        // Remover o subdiretório
        rmdir($uploadDir);

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
