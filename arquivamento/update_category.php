<?php
if (isset($_POST['id']) && isset($_POST['category'])) {
    $id = $_POST['id'];
    $category = $_POST['category'];
    $categoriesFile = 'categorias/categorias.json';
    $categories = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];
    if (isset($categories[$id])) {
        $categories[$id] = $category;
        file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
?>
