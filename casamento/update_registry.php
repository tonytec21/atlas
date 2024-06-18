<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
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
    $arquivo_pdf = $_POST['existing_pdf_file'];

    if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] == 0) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["arquivo_pdf"]["name"]);
        if (move_uploaded_file($_FILES["arquivo_pdf"]["tmp_name"], $target_file)) {
            $arquivo_pdf = $target_file;
        }
    }

    $jsonFile = 'meta-dados/casamentos.json';

    if (file_exists($jsonFile)) {
        $registries = json_decode(file_get_contents($jsonFile), true);
        
        if (isset($registries[$id])) {
            $registries[$id]['termo'] = $termo;
            $registries[$id]['livro'] = $livro;
            $registries[$id]['folha'] = $folha;
            $registries[$id]['nome_1_nubente'] = $nome_1_nubente;
            $registries[$id]['data_nascimento_1_nubente'] = $data_nascimento_1_nubente;
            $registries[$id]['filiacao_1_nubente'] = $filiacao_1_nubente;
            $registries[$id]['nome_2_nubente'] = $nome_2_nubente;
            $registries[$id]['data_nascimento_2_nubente'] = $data_nascimento_2_nubente;
            $registries[$id]['filiacao_2_nubente'] = $filiacao_2_nubente;
            $registries[$id]['data_casamento'] = $data_casamento;
            
            if (isset($arquivo_pdf)) {
                $registries[$id]['arquivo_pdf'] = $arquivo_pdf;
            }

            file_put_contents($jsonFile, json_encode($registries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode(array('status' => 'success'));
        } else {
            echo json_encode(array('status' => 'error', 'message' => 'Registro não encontrado'));
        }
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Arquivo de dados não encontrado'));
    }
}
?>
