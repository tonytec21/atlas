<?php
include(__DIR__ . '/session_check.php');
checkSession();

$filterDate = isset($_GET['filterDate']) ? $_GET['filterDate'] : '';
$filterAssunto = isset($_GET['filterAssunto']) ? $_GET['filterAssunto'] : '';
$filterDestinatario = isset($_GET['filterDestinatario']) ? $_GET['filterDestinatario'] : '';

$directory = __DIR__ . "/meta-dados/";
$files = array_diff(scandir($directory), array('..', '.'));

$oficios = [];

foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
        $oficio = json_decode(file_get_contents($directory . $file), true);
        if ($oficio) {
            if (($filterDate && strpos($oficio['data'], $filterDate) === false) ||
                ($filterAssunto && stripos($oficio['assunto'], $filterAssunto) === false) ||
                ($filterDestinatario && stripos($oficio['destinatario'], $filterDestinatario) === false)) {
                continue;
            }
            $oficios[] = $oficio;
        }
    }
}

echo json_encode($oficios);
?>
