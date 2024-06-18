<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $termo = $_POST['termo'];
    $livro = $_POST['livro'];
    $folha = $_POST['folha'];
    $nome_registrado = $_POST['nome_registrado'];
    $data_nascimento = $_POST['data_nascimento'];
    $filiacao = $_POST['filiacao'];
    $data_registro = $_POST['data_registro'];
    $data_obito = $_POST['data_obito'];
    $arquivo_pdf = $_POST['existing_pdf_file'];

    if (!empty($_FILES['arquivo_pdf']['name'])) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["arquivo_pdf"]["name"]);
        if (move_uploaded_file($_FILES["arquivo_pdf"]["tmp_name"], $target_file)) {
            $arquivo_pdf = $target_file;
        }
    }

    $updatedRegistry = array(
        'termo' => $termo,
        'livro' => $livro,
        'folha' => $folha,
        'nome_registrado' => $nome_registrado,
        'data_nascimento' => $data_nascimento,
        'filiacao' => $filiacao,
        'data_registro' => $data_registro,
        'data_obito' => $data_obito
    );

    if (!empty($_FILES['arquivo_pdf']['name'])) {
        $updatedRegistry['arquivo_pdf'] = $arquivo_pdf;
    }

    $jsonFile = 'meta-dados/registries.json';

    if (file_exists($jsonFile)) {
        $registries = json_decode(file_get_contents($jsonFile), true);
        $registries[$id] = array_merge($registries[$id], $updatedRegistry);
        file_put_contents($jsonFile, json_encode($registries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    echo json_encode(array('status' => 'success'));
}
?>
