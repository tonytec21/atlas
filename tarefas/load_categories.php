<?php
$categoriesFile = 'categorias/categorias.json';
if (file_exists($categoriesFile)) {
    $categories = json_decode(file_get_contents($categoriesFile), true);
    echo json_encode($categories);
} else {
    echo json_encode([]);
}
?>
