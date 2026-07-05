<?php
/**
 * gerar_pdf_nota.php — Gera e envia o PDF da nota devolutiva (inline).
 * Requer sessão válida. A construção do PDF fica em nota_pdf_lib.php.
 */
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/nota_pdf_lib.php';

$numero = isset($_GET['numero']) ? trim((string)$_GET['numero']) : '';
if ($numero === '') { http_response_code(400); die('Número da nota devolutiva não informado.'); }

try {
    $bytes = nd_build_nota_pdf($numero);
} catch (Throwable $e) {
    http_response_code(404);
    die('Não foi possível gerar o PDF: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

if (ob_get_length()) { ob_clean(); }
$nome = preg_replace('~[^0-9A-Za-z_\-]~', '_', $numero);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Nota_Devolutiva_' . $nome . '.pdf"');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
