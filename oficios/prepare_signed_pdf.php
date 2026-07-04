<?php
/**
 * oficios/prepare_signed_pdf.php
 * ---------------------------------------------------------------------------
 * Gera o PDF-base do ofício (SEM selo do Atlas) e devolve em base64, pronto
 * para ser enviado ao Assinador SERPRO. O selo visual da assinatura é aplicado
 * pelo próprio Assinador SERPRO (com os dados do certificado), evitando dois
 * carimbos no documento.
 *
 * POST:
 *   numero    (string) obrigatório
 *   timbrado  (S|N)    opcional (default: lê ../style/configuracao.json)
 *
 * Saída JSON:
 *   { status:"success", pdf_base64:"...", codigo:"XXXX-XXXX-...",
 *     nome:"...", cargo:"...", quando:"dd/mm/aaaa HH:MM:SS" }
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_config.php';

header('Content-Type: application/json; charset=utf-8');

function pfail($human, $tech = '')
{
    if ($tech) {
        assin_log('PREPARE ERROR: ' . $tech);
    }
    echo json_encode(['status' => 'error', 'message' => $human], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        pfail('Método inválido.');
    }

    assin_ensure_schema();

    $numero = isset($_POST['numero']) ? trim((string)$_POST['numero']) : '';
    if ($numero === '') {
        pfail('Número do ofício não informado.');
    }

    $timbrado = assin_timbrado_flag($_POST['timbrado'] ?? null);

    $conn = assin_db();
    $stmt = $conn->prepare("SELECT assinante, cargo_assinante, assinado FROM oficios WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        pfail('Ofício não encontrado.');
    }
    $of = $res->fetch_assoc();
    $stmt->close();

    if ((int)($of['assinado'] ?? 0) === 1) {
        pfail('Este ofício já está assinado digitalmente.');
    }

    // PDF-base (idêntico ao visualizado), SEM selo do Atlas.
    $baseBytes = assin_generate_pdf_bytes($numero, $timbrado);
    if (!$baseBytes || strncmp(ltrim($baseBytes), '%PDF', 4) !== 0) {
        pfail('Falha ao gerar o documento.', 'base bytes inválidos');
    }

    // Código de verificação interno (auditoria), derivado do PDF-base.
    $hash   = strtoupper(substr(hash('sha256', $baseBytes), 0, 16));
    $codigo = implode('-', str_split($hash, 4));
    $quando = date('d/m/Y H:i:s');

    echo json_encode([
        'status'     => 'success',
        'pdf_base64' => base64_encode($baseBytes),
        'codigo'     => $codigo,
        'nome'       => $of['assinante'] ?? '',
        'cargo'      => $of['cargo_assinante'] ?? '',
        'quando'     => $quando,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    pfail('Erro ao preparar o documento para assinatura.', $e->getMessage());
}
