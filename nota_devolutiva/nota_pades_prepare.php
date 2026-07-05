<?php
/** nota_pades_prepare.php — Fase 1 PAdES: PDF+selo+placeholder, devolve hash a assinar. */
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_nota_config.php';
require_once __DIR__ . '/../oficios/src/autoload.php';
require_once __DIR__ . '/../oficios/tcpdf/tcpdf.php';

use setasign\Fpdi\Tcpdf\Fpdi;

header('Content-Type: application/json; charset=utf-8');

class NdFpdiSig extends Fpdi { public function setSigMaxLength($n){ $this->signature_max_length = (int)$n; } }

function ndp_fail($h, $t = '') { if ($t) nd_log('PREPARE ERROR: ' . $t); echo json_encode(['status'=>'error','message'=>$h], JSON_UNESCAPED_UNICODE); exit; }
function nd_pades_dir() { $d = nd_dir_assinados() . '/.pades'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ndp_fail('Método inválido.');
    if (!nd_csrf_check($_POST['csrf'] ?? '')) ndp_fail('Sessão expirada. Recarregue a página.');
    nd_ensure_schema();

    $numero = trim((string)($_POST['numero'] ?? ''));
    if ($numero === '') ndp_fail('Número da nota não informado.');
    $page = max(1, (int)($_POST['page'] ?? 1));
    $xn = (float)($_POST['xn'] ?? 0.55); $yn = (float)($_POST['yn'] ?? 0.80); $wn = (float)($_POST['wn'] ?? 0.38);

    $conn = nd_db();
    $stmt = $conn->prepare("SELECT assinante, cargo_assinante, assinado FROM notas_devolutivas WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero); $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) ndp_fail('Nota devolutiva não encontrada.');
    $nota = $res->fetch_assoc(); $stmt->close();
    if ((int)($nota['assinado'] ?? 0) === 1) ndp_fail('Esta nota já está assinada.');

    // PDF + nosso selo
    $baseBytes = nd_generate_pdf_bytes($numero);
    $codigo = implode('-', str_split(strtoupper(substr(hash('sha256', $baseBytes), 0, 16)), 4));
    $sealed = nd_stamp_seal($baseBytes, [
        'page'=>$page,'xn'=>$xn,'yn'=>$yn,'wn'=>$wn,
        'nome'=>$nota['assinante'] ?? '','cargo'=>$nota['cargo_assinante'] ?? '',
        'codigo'=>$codigo,'quando'=>date('d/m/Y H:i:s'),
    ]);
    if (!$sealed || strncmp(ltrim($sealed), '%PDF', 4) !== 0) ndp_fail('Falha ao gerar o PDF com o selo.', 'sealed inválido');

    // Placeholder (cert dummy de ../oficios)
    $tmp = nd_pades_dir() . '/sealed_' . bin2hex(random_bytes(6)) . '.pdf';
    file_put_contents($tmp, $sealed);
    $dcrt = __DIR__ . '/../oficios/pades_dummy.crt'; $dkey = __DIR__ . '/../oficios/pades_dummy.key';
    if (!is_file($dcrt) || !is_file($dkey)) ndp_fail('Certificado de placeholder ausente (../oficios/pades_dummy.*).', 'dummy ausente');

    $pdf = new NdFpdiSig('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetPrintHeader(false); $pdf->SetPrintFooter(false);
    $n = $pdf->setSourceFile($tmp);
    for ($i = 1; $i <= $n; $i++) {
        $tpl = $pdf->importPage($i); $sz = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($sz['orientation'], [$sz['width'], $sz['height']]);
        $pdf->useTemplate($tpl);
    }
    $pdf->setSigMaxLength(16000);
    $pdf->setSignatureAppearance(0, 0, 0, 0, 1);
    $pdf->setSignature('file://' . $dcrt, 'file://' . $dkey, '', '', 2, [], false);
    $prepared = $pdf->Output('prepared.pdf', 'S');
    @unlink($tmp);

    $prepared = AtlasPadesInjector::toEtsiCades($prepared);
    $br = AtlasPadesInjector::readByteRange($prepared);

    $sessionId = bin2hex(random_bytes(16));
    $preparedPath = nd_pades_dir() . '/prep_' . $sessionId . '.pdf';
    file_put_contents($preparedPath, $prepared);
    file_put_contents(nd_pades_dir() . '/sess_' . $sessionId . '.json', json_encode([
        'numero'=>$numero,'prepared'=>basename($preparedPath),
        'holeStart'=>$br['holeStart'],'holeEnd'=>$br['holeEnd'],'brDigestHex'=>bin2hex($br['digest']),
        'codigo'=>$codigo,'pos'=>['page'=>$page,'xn'=>$xn,'yn'=>$yn,'wn'=>$wn],'created'=>time(),
    ], JSON_UNESCAPED_UNICODE));

    echo json_encode(['status'=>'success','session'=>$sessionId,'to_sign'=>base64_encode($br['digest']),'codigo'=>$codigo], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ndp_fail('Erro ao preparar a assinatura.', $e->getMessage());
}
