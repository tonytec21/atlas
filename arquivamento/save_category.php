<?php
if (isset($_POST['category'])) {
    $category = $_POST['category'];
    $categoriesFile = 'categorias/categorias.json';
    $categories = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];
    $categories[] = $category; // Add the new category
    file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
?>
