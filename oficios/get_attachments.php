<?php
$numero = isset($_GET['numero']) ? $_GET['numero'] : '';
$anexos_dir = __DIR__ . "/anexos/$numero/";
$lixeira_dir = __DIR__ . "/lixeira/$numero/"; // Diretório para a lixeira

// Cria o diretório da Lixeira, se não existir
if (!is_dir($lixeira_dir)) {
    mkdir($lixeira_dir, 0777, true);
}

if (is_dir($anexos_dir)) {
    $files = array_diff(scandir($anexos_dir), array('.', '..'));
    if (count($files) > 0) {
        foreach ($files as $index => $file) {
            $filePath = "anexos/$numero/$file";
            echo "<div class='anexo-item'>
                    <span>" . ($index + 1) . "</span>
                    <span>$file</span>
                    <button class='btn btn-info btn-sm visualizar-anexo' data-file='$filePath'><i class='fa fa-eye' aria-hidden='true'></i></button>
                    <button class='btn btn-delete btn-sm excluir-anexo' data-file='$filePath' data-numero='$numero'><i class='fa fa-trash' aria-hidden='true'></i></button>
                  </div>";
        }
    } else {
        echo "<p>Sem anexos disponíveis.</p>";
    }
} else {
    echo "<p>Sem anexos disponíveis.</p>";
}
?>
