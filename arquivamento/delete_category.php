<?php
if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $categoriesFile = 'categorias/categorias.json';
    $categories = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];
    if (isset($categories[$id])) {
        array_splice($categories, $id, 1);
        file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
?>
