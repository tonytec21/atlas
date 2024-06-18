<?php
$files = glob('meta-dados/*.json');
$atos = [];

foreach ($files as $file) {
    $ato = json_decode(file_get_contents($file), true);
    $ato['id'] = basename($file, '.json');
    $atos[] = $ato;
}

echo json_encode($atos);
?>
