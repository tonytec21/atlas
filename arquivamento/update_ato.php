<?php
include(__DIR__ . '/session_check.php');
checkSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $atribuicao = $_POST['atribuicao'];
    $categoria = $_POST['categoria'];
    $data_ato = $_POST['data_ato'];
    $livro = $_POST['livro'];
    $folha = $_POST['folha'];
    $termo = $_POST['termo'];
    $protocolo = $_POST['protocolo'];
    $matricula = $_POST['matricula'];
    $descricao = $_POST['descricao'];
    $partes_envolvidas = json_decode($_POST['partes_envolvidas'], true);

    $file_dir = "arquivos/$id/";
    if (!is_dir($file_dir)) {
        mkdir($file_dir, 0777, true);
    }

    $anexos = [];
    if (isset($_FILES['file-input'])) {
        foreach ($_FILES['file-input']['tmp_name'] as $key => $tmp_name) {
            $file_name = $_FILES['file-input']['name'][$key];
            $file_tmp = $_FILES['file-input']['tmp_name'][$key];
            $file_path = $file_dir . basename($file_name);
            if (move_uploaded_file($file_tmp, $file_path)) {
                $anexos[] = $file_path;
            }
        }
    }

    // Manter arquivos existentes que não foram removidos
    $existing_ato = json_decode(file_get_contents("meta-dados/$id.json"), true);
    $existing_anexos = $existing_ato['anexos'];

    // Adicionar arquivos removidos ao array $files_to_remove
    $files_to_remove = isset($_POST['files_to_remove']) ? json_decode($_POST['files_to_remove'], true) : [];

    // Remover os arquivos do sistema de arquivos
    foreach ($files_to_remove as $file) {
        if (($key = array_search($file, $existing_anexos)) !== false) {
            unset($existing_anexos[$key]);
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    // Mesclar arquivos existentes (que não foram removidos) com novos arquivos
    $anexos = array_merge($existing_anexos, $anexos);

    // Registrar a modificação
    $modification = [
        'usuario' => $_SESSION['username'],
        'data_hora' => date('d-m-Y H:i:s')
    ];

    if (!isset($existing_ato['modificacoes'])) {
        $existing_ato['modificacoes'] = [];
    }

    $existing_ato['modificacoes'][] = $modification;

    $ato = [
        'id' => $id,
        'atribuicao' => $atribuicao,
        'categoria' => $categoria,
        'data_ato' => $data_ato,
        'livro' => $livro,
        'folha' => $folha,
        'termo' => $termo,
        'protocolo' => $protocolo,
        'matricula' => $matricula,
        'descricao' => $descricao,
        'partes_envolvidas' => $partes_envolvidas,
        'anexos' => array_values($anexos),
        'cadastrado_por' => $existing_ato['cadastrado_por'],
        'data_cadastro' => $existing_ato['data_cadastro'],
        'modificacoes' => $existing_ato['modificacoes']
    ];

    file_put_contents("meta-dados/$id.json", json_encode($ato, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode(['status' => 'success']);
}
?>
