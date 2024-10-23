<?php
ob_start();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/session_check.php');

// Define cabeçalho JSON
header('Content-Type: application/json; charset=UTF-8');

// Função para enviar a resposta JSON
function jsonResponse($success, $message) {
    ob_end_clean(); // Limpa qualquer saída anterior
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

checkSession();


// Função para salvar anexos
function salvarAnexos($idRegistro) {
    $caminhoBase = __DIR__ . "/anexos/$idRegistro/";
    if (!is_dir($caminhoBase) && !mkdir($caminhoBase, 0777, true)) {
        jsonResponse(false, 'Erro ao criar diretório de anexos.');
    }

    $anexos = [];
    foreach ($_FILES['anexos']['tmp_name'] as $index => $tmpName) {
        $nomeArquivo = $_FILES['anexos']['name'][$index];
        $caminhoCompleto = $caminhoBase . $nomeArquivo;

        if (move_uploaded_file($tmpName, $caminhoCompleto)) {
            $anexos[] = "anexos/$idRegistro/$nomeArquivo";
        } else {
            jsonResponse(false, "Erro ao mover o arquivo: $nomeArquivo");
        }
    }
    return implode(';', $anexos);
}

// Recebendo dados
$id = $_POST['id'] ?? 0;
$cidade = $_POST['cidade'] ?? '';
$nome_noivo = $_POST['nomeNoivo'] ?? '';
$novo_nome_noivo = $_POST['novoNomeNoivo'] ?? null;
$noivo_menor = $_POST['noivoMenor'] ?? 0;
$nome_noiva = $_POST['nomeNoiva'] ?? '';
$novo_nome_noiva = $_POST['novoNomeNoiva'] ?? null;
$noiva_menor = $_POST['noivaMenor'] ?? 0;

if ($id == 0) {
    jsonResponse(false, 'ID do registro não fornecido.');
}

// Query de UPDATE
$stmt = $conn->prepare("
    UPDATE triagem_comunitario
    SET cidade = ?, nome_do_noivo = ?, novo_nome_do_noivo = ?, 
        noivo_menor = ?, nome_da_noiva = ?, novo_nome_da_noiva = ?, 
        noiva_menor = ?
    WHERE id = ?
");

if (!$stmt) {
    jsonResponse(false, 'Erro na preparação do UPDATE: ' . $conn->error);
}

$stmt->bind_param(
    'sssissii',
    $cidade, $nome_noivo, $novo_nome_noivo, 
    $noivo_menor, $nome_noiva, $novo_nome_noiva, 
    $noiva_menor, $id
);

if ($stmt->execute()) {
    if (!empty($_FILES['anexos']['name'][0])) {
        $anexosNovos = salvarAnexos($id);

        $stmtSelect = $conn->prepare("SELECT caminho_anexo FROM triagem_comunitario WHERE id = ?");
        $stmtSelect->bind_param('i', $id);
        $stmtSelect->execute();
        $resultado = $stmtSelect->get_result()->fetch_assoc();
        $anexosExistentes = $resultado['caminho_anexo'] ?? '';

        $todosAnexos = $anexosExistentes 
            ? $anexosExistentes . ';' . $anexosNovos 
            : $anexosNovos;

        $stmtUpdate = $conn->prepare("UPDATE triagem_comunitario SET caminho_anexo = ? WHERE id = ?");
        if (!$stmtUpdate) {
            jsonResponse(false, 'Erro na preparação do UPDATE dos anexos: ' . $conn->error);
        }

        $stmtUpdate->bind_param('si', $todosAnexos, $id);
        if (!$stmtUpdate->execute()) {
            jsonResponse(false, 'Erro ao executar o UPDATE dos anexos: ' . $stmtUpdate->error);
        }
    }

    jsonResponse(true, 'Registro atualizado com sucesso.');
} else {
    jsonResponse(false, 'Erro ao executar o UPDATE: ' . $stmt->error);
}
?>
