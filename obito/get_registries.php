<?php
/* atlas-guarda */ if (is_file(__DIR__ . '/../seguranca/guarda.php')) { include_once __DIR__ . '/../seguranca/guarda.php'; }
$jsonFile = 'meta-dados/registries.json';
if (file_exists($jsonFile)) {
    $registries = json_decode(file_get_contents($jsonFile), true);
    echo json_encode($registries);
} else {
    echo json_encode(array());
}
?>
