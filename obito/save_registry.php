<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $termo = $_POST['termo'];
    $livro = $_POST['livro'];
    $folha = $_POST['folha'];
    $data_registro = $_POST['data_registro'];
    $nome_registrado = $_POST['nome_registrado'];
    $filiacao = $_POST['filiacao'];
    $data_nascimento = $_POST['data_nascimento'];
    $data_obito = $_POST['data_obito'];
    $arquivo_pdf = '';

    if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] == 0) {
        $arquivo_pdf = 'uploads/' . basename($_FILES['arquivo_pdf']['name']);
        move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $arquivo_pdf);
    }

    $novo_registro = array(
        'termo' => $termo,
        'livro' => $livro,
        'folha' => $folha,
        'data_registro' => $data_registro,
        'nome_registrado' => $nome_registrado,
        'filiacao' => $filiacao,
        'data_nascimento' => $data_nascimento,
        'data_obito' => $data_obito,
        'arquivo_pdf' => $arquivo_pdf
    );

    $json_file_path = 'meta-dados/registries.json';
    if (file_exists($json_file_path)) {
        $json_data = file_get_contents($json_file_path);
        $registros = json_decode($json_data, true);
    } else {
        $registros = array();
    }

    $registros[] = $novo_registro;
    file_put_contents($json_file_path, json_encode($registros, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo 'Registro salvo com sucesso!';
}
?>
