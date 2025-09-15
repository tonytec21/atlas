<?php
include(__DIR__ . '/db_connection.php');
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $funcionario   = $_SESSION['username'] ?? ($_SESSION['nome_funcionario'] ?? '');
    $id_casamento  = intval($_POST['id_casamento'] ?? 0);
    $status        = 'ativo';

    if ($id_casamento <= 0) { echo json_encode(['success'=>false,'message'=>'ID inválido.']); exit; }

    if (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] === 0) {
        $dir = __DIR__ . '/anexos/' . $id_casamento . '/';
        if (!file_exists($dir)) { mkdir($dir, 0777, true); }

        $name = basename($_FILES['arquivo_pdf']['name']);
        $dest = $dir . $name;

        if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $dest)) {
            $relPath = 'anexos/' . $id_casamento . '/' . $name;
            $stmt = $conn->prepare("INSERT INTO indexador_casamento_anexos (id_casamento, caminho_anexo, funcionario, status) VALUES (?,?,?,?)");
            $stmt->bind_param("isss", $id_casamento, $relPath, $funcionario, $status);
            if ($stmt->execute()) {
                echo json_encode(['success'=>true]);
            } else {
                echo json_encode(['success'=>false,'message'=>'Erro ao salvar no banco.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success'=>false,'message'=>'Falha ao mover o arquivo.']);
        }
    } else {
        echo json_encode(['success'=>false,'message'=>'Upload inválido.']);
    }
    $conn->close();
}
