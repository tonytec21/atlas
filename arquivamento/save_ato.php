<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Gerar um novo ID para o ato
    $id = time(); // Você pode usar qualquer lógica para gerar um ID único
    $filePath = "meta-dados/$id.json";
    $uploadDir = "arquivos/$id/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $username = $_SESSION['username']; // Obtendo o nome do usuário da sessão
    $creationTime = date('Y-m-d H:i:s'); // Obtendo a data e hora do cadastro

    $dados = [
        'id' => $id,
        'atribuicao' => $_POST['atribuicao'],
        'categoria' => $_POST['categoria'],
        'data_ato' => $_POST['data_ato'],
        'livro' => isset($_POST['livro']) ? $_POST['livro'] : '',
        'folha' => isset($_POST['folha']) ? $_POST['folha'] : '',
        'termo' => isset($_POST['termo']) ? $_POST['termo'] : '',
        'protocolo' => isset($_POST['protocolo']) ? $_POST['protocolo'] : '',
        'matricula' => isset($_POST['matricula']) ? $_POST['matricula'] : '',
        'descricao' => isset($_POST['descricao']) ? $_POST['descricao'] : '',
        'partes_envolvidas' => json_decode($_POST['partes_envolvidas'], true),
        'cadastrado_por' => $username,
        'data_cadastro' => $creationTime
    ];

    $anexos = [];

    // Adicionar novos arquivos anexados
    if (!empty($_FILES['file-input']['name'][0])) {
        $fileCount = count($_FILES['file-input']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = basename($_FILES['file-input']['name'][$i]);
            $targetFilePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['file-input']['tmp_name'][$i], $targetFilePath)) {
                $anexos[] = $targetFilePath;
            }
        }
    }
    $dados['anexos'] = $anexos;

    // Salvar dados em arquivo JSON
    file_put_contents($filePath, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Retornar a URL de redirecionamento
    echo json_encode(['status' => 'success', 'redirect' => "edit_ato.php?id=$id"]);
} else {
    echo json_encode(['status' => 'error']);
}
?>
