<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $termo = $_POST['termo'];
    $livro = $_POST['livro'];
    $folha = $_POST['folha'];
    $nome_1_nubente = $_POST['nome_1_nubente'];
    $data_nascimento_1_nubente = $_POST['data_nascimento_1_nubente'];
    $filiacao_1_nubente = $_POST['filiacao_1_nubente'];
    $nome_2_nubente = $_POST['nome_2_nubente'];
    $data_nascimento_2_nubente = $_POST['data_nascimento_2_nubente'];
    $filiacao_2_nubente = $_POST['filiacao_2_nubente'];
    $data_casamento = $_POST['data_casamento'];
    $arquivo_pdf = '';

    if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] == 0) {
        $arquivo_pdf = 'uploads/' . basename($_FILES['arquivo_pdf']['name']);
        move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $arquivo_pdf);
    }

    $novo_registro = array(
        'termo' => $termo,
        'livro' => $livro,
        'folha' => $folha,
        'nome_1_nubente' => $nome_1_nubente,
        'data_nascimento_1_nubente' => $data_nascimento_1_nubente,
        'filiacao_1_nubente' => $filiacao_1_nubente,
        'nome_2_nubente' => $nome_2_nubente,
        'data_nascimento_2_nubente' => $data_nascimento_2_nubente,
        'filiacao_2_nubente' => $filiacao_2_nubente,
        'data_casamento' => $data_casamento,
        'arquivo_pdf' => $arquivo_pdf
    );

    $json_file_path = 'meta-dados/casamentos.json';
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
