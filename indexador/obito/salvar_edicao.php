<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include(__DIR__ . '/db_connection.php');
    date_default_timezone_set('America/Sao_Paulo');

    session_start();
    $funcionario = $_SESSION['username'] ?? '';
    $status = 'A';

    if (!isset($_POST['id']) || empty($_POST['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID do registro não fornecido.']);
        exit;
    }

    $id = intval($_POST['id']);

    $livro              = $_POST['livro'] ?? '';
    $folha              = $_POST['folha'] ?? '';
    $termo              = $_POST['termo'] ?? '';
    $data_registro      = $_POST['data_registro'] ?? '';
    $data_obito         = $_POST['data_obito'] ?? '';
    $hora_obito         = $_POST['hora_obito'] ?? '';
    $nome_registrado    = mb_strtoupper(trim($_POST['nome_registrado']), 'UTF-8');
    $data_nascimento    = $_POST['data_nascimento'] ?? '';
    $nome_pai           = mb_strtoupper(trim($_POST['nome_pai'] ?? ''), 'UTF-8');
    $nome_mae           = mb_strtoupper(trim($_POST['nome_mae'] ?? ''), 'UTF-8');
    $cidade_endereco    = $_POST['cidade_endereco'] ?? '';
    $ibge_cidade_endereco = $_POST['ibge_cidade_endereco'] ?? '';
    $cidade_obito       = $_POST['cidade_obito'] ?? '';
    $ibge_cidade_obito  = $_POST['ibge_cidade_obito'] ?? '';

    // LOG para debug
    error_log("Recebido para edição: ID=$id, Livro=$livro, Nome=$nome_registrado");

    // Atualizar os dados do óbito
    $sql = "UPDATE indexador_obito
            SET livro = ?, folha = ?, termo = ?, data_registro = ?, data_obito = ?, hora_obito = ?,
                nome_registrado = ?, data_nascimento = ?, nome_pai = ?, nome_mae = ?, cidade_endereco = ?,
                ibge_cidade_endereco = ?, cidade_obito = ?, ibge_cidade_obito = ?, funcionario = ?
            WHERE id = ? AND status = 'A'";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssssssssssssssi", 
            $livro, $folha, $termo, $data_registro, $data_obito, $hora_obito,
            $nome_registrado, $data_nascimento, $nome_pai, $nome_mae, $cidade_endereco,
            $ibge_cidade_endereco, $cidade_obito, $ibge_cidade_obito, $funcionario, $id
        );

        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar: ' . $stmt->error]);
            exit;
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao preparar query: ' . $conn->error]);
        exit;
    }

    // LOG para debug
    error_log("Registro atualizado com sucesso: ID=$id");

    // ================================
    //      UPLOAD DE NOVOS ANEXOS
    // ================================
    if (!empty($_FILES['anexos']['name'][0])) {
        $uploadDir = 'anexos/obitos/' . $id . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $stmt_anexo = $conn->prepare("INSERT INTO indexador_obito_anexos (id_obito, caminho_anexo, funcionario, status) 
                                      VALUES (?, ?, ?, ?)");
        if (!$stmt_anexo) {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao preparar query de anexos: ' . $conn->error]);
            exit;
        }

        foreach ($_FILES['anexos']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['anexos']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['anexos']['name'][$key];
                $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExt === 'pdf') {
                    $uniqueName = uniqid() . '_' . $fileName;
                    $finalPath = $uploadDir . $uniqueName;

                    if (move_uploaded_file($tmp_name, $finalPath)) {
                        $stmt_anexo->bind_param("isss", $id, $finalPath, $funcionario, $status);
                        if (!$stmt_anexo->execute()) {
                            error_log("Erro ao salvar anexo no banco: " . $stmt_anexo->error);
                        } else {
                            error_log("Anexo salvo: " . $finalPath);
                        }
                    } else {
                        error_log("Erro ao mover arquivo: " . $fileName);
                    }
                } else {
                    error_log("Arquivo não permitido: " . $fileName);
                }
            }
        }
        $stmt_anexo->close();
    }

    echo json_encode(['status' => 'success', 'message' => 'Registro atualizado com sucesso!']);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido!']);
    exit;
}
?>
