<?php
$numero = isset($_POST['numero']) ? $_POST['numero'] : '';
$target_dir = __DIR__ . "/anexos/$numero/";

if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$target_file = $target_dir . basename($_FILES["file"]["name"]);
if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
    echo "Arquivo enviado com sucesso.";
} else {
    echo "Erro ao enviar arquivo.";
}
?>
