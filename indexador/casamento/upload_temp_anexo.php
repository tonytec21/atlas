<?php
session_start();
$base = __DIR__ . '/anexos/temp/';
if (!file_exists($base)) { @mkdir($base, 0777, true); }

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Método inválido']); exit;
}

if (!isset($_FILES['arquivo_pdf']) || $_FILES['arquivo_pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'error'=>'Upload inválido']); exit;
}

$orig = $_FILES['arquivo_pdf']['name'];
$ext  = pathinfo($orig, PATHINFO_EXTENSION);
$fname = uniqid('tmp_', true) . ($ext ? '.'.$ext : '');
$dest  = $base . $fname;

if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $dest)) {
    echo json_encode(['success'=>true, 'file_path'=>$dest]);
} else {
    echo json_encode(['success'=>false,'error'=>'Falha ao mover o arquivo']);
}
