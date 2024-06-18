<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    $jsonFile = "lixeira/$id.json";
    $jsonDest = "meta-dados/$id.json";
    $dirSource = "lixeira/$id/";
    $dirDest = "arquivos/$id/";

    if (file_exists($jsonFile)) {
        rename($jsonFile, $jsonDest);
    }

    if (is_dir($dirSource)) {
        if (!is_dir($dirDest)) {
            mkdir($dirDest, 0777, true);
        }
        $files = glob($dirSource . '*');
        foreach ($files as $file) {
            $fileDest = $dirDest . basename($file);
            rename($file, $fileDest);
        }
        rmdir($dirSource);
    }

    echo json_encode(['status' => 'success']);
}
?>
