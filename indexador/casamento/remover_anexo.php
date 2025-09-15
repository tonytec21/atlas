<?php
include(__DIR__ . '/db_connection.php');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id = intval($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID inválido.']); exit; }

    // localizar caminho
    $stmt = $conn->prepare("SELECT caminho_anexo FROM indexador_casamento_anexos WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute(); $res = $stmt->get_result();
    if (!($row = $res->fetch_assoc())) {
        echo json_encode(['success'=>false,'message'=>'Anexo não encontrado.']); exit;
    }
    $stmt->close();

    // apagar arquivo (se existir)
    $pathRel = $row['caminho_anexo'];
    $pathAbs = __DIR__ . '/' . $pathRel;
    if (file_exists($pathAbs)) { @unlink($pathAbs); }

    // remover do banco
    $stmt2 = $conn->prepare("DELETE FROM indexador_casamento_anexos WHERE id=?");
    $stmt2->bind_param("i", $id);
    $ok = $stmt2->execute();
    $stmt2->close();

    echo json_encode(['success'=>$ok ? true:false, 'message'=>$ok?'':'Falha ao remover do banco.']);
    $conn->close();
}
