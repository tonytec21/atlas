<?php
$numero = isset($_GET['numero']) ? $_GET['numero'] : '';
$anexos_dir = __DIR__ . "/anexos/$numero/";

if (is_dir($anexos_dir)) {
    $files = array_diff(scandir($anexos_dir), array('.', '..'));
    if (count($files) > 0) {
        foreach ($files as $index => $file) {
            $filePath = "anexos/$numero/$file";
            echo "<div class='anexo-item'>
                    <span>" . ($index + 1) . "</span>
                    <span>$file</span>
                    <button class='btn btn-info btn-sm visualizar-anexo' data-file='$filePath'><i class='fa fa-eye' aria-hidden='true'></i></button>
                  </div>";
        }
    } else {
        echo "<p>Sem anexos disponíveis.</p>";
    }
} else {
    echo "<p>Sem anexos disponíveis.</p>";
}
?>
