<?php
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_nota_config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    nd_ensure_schema();
    $numero = trim((string)($_GET['numero'] ?? ''));
    if ($numero === '') throw new RuntimeException('Número não informado.');
    $conn = nd_db();
    $stmt = $conn->prepare("SELECT id, nome_original, mime, tamanho, descricao, enviado_por, DATE_FORMAT(enviado_em,'%d/%m/%Y %H:%i') AS enviado_em FROM nota_anexos WHERE nota_numero = ? ORDER BY id DESC");
    $stmt->bind_param('s', $numero); $stmt->execute();
    $r = $stmt->get_result(); $list = [];
    while ($row = $r->fetch_assoc()) $list[] = $row;
    $stmt->close();
    echo json_encode(['status'=>'success','anexos'=>$list], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
