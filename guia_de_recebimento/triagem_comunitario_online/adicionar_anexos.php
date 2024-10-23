<?php
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/session_check.php');
header('Content-Type: application/json');
checkSession();

$idRegistro = $_POST['id'];

function salvarAnexos($idRegistro) {
    $caminhoBase = __DIR__ . "/anexos/$idRegistro/";
    if (!is_dir($caminhoBase) && !mkdir($caminhoBase, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar diretório.']);
        exit;
    }

    $anexos = [];
    foreach ($_FILES['anexos']['tmp_name'] as $index => $tmpName) {
        $nomeArquivo = $_FILES['anexos']['name'][$index];
        $caminhoCompleto = $caminhoBase . $nomeArquivo;

        if (move_uploaded_file($tmpName, $caminhoCompleto)) {
            $anexos[] = "anexos/$idRegistro/$nomeArquivo";
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao mover o arquivo.']);
            exit;
        }
    }
    return implode(';', $anexos);
}

if (!empty($_FILES['anexos']['name'][0])) {
    $anexosNovos = salvarAnexos($idRegistro);

    $stmt = $conn->prepare("SELECT caminho_anexo FROM triagem_comunitario WHERE id = ?");
    $stmt->bind_param('i', $idRegistro);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $anexosExistentes = $resultado['caminho_anexo'] ?? '';

    $todosAnexos = $anexosExistentes 
        ? $anexosExistentes . ';' . $anexosNovos 
        : $anexosNovos;

    $stmtUpdate = $conn->prepare("UPDATE triagem_comunitario SET caminho_anexo = ? WHERE id = ?");
    $stmtUpdate->bind_param('si', $todosAnexos, $idRegistro);

    if ($stmtUpdate->execute()) {
        echo json_encode(['success' => true, 'message' => 'Anexos adicionados com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar anexos.']);
    }
}
?>
