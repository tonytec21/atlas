<?php
/** prepare_nota_pdf.php — devolve o PDF da nota (sem selo) em base64 para o preview. */
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_nota_config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $numero = isset($_POST['numero']) ? trim((string)$_POST['numero']) : (isset($_GET['numero']) ? trim((string)$_GET['numero']) : '');
    if ($numero === '') throw new RuntimeException('Número não informado.');
    $bytes = nd_generate_pdf_bytes($numero);
    echo json_encode(['status' => 'success', 'pdf_base64' => base64_encode($bytes)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
