<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/assinatura_os_config.php';
require_once __DIR__ . '/../oficios/src/autoload.php';
require_once __DIR__ . '/../oficios/tcpdf/tcpdf.php';
use setasign\Fpdi\Tcpdf\Fpdi;
header('Content-Type: application/json; charset=utf-8');
class OsFpdiSig extends Fpdi { public function setSigMaxLength($n){ $this->signature_max_length=(int)$n; } }
function op_fail($h,$t=''){ if($t) os_log('PREPARE ERR: '.$t); echo json_encode(['status'=>'error','message'=>$h], JSON_UNESCAPED_UNICODE); exit; }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') op_fail('Método inválido.');
    if (!os_csrf_check($_POST['csrf'] ?? '')) op_fail('Sessão expirada. Recarregue a página.');
    os_ensure_schema();

    $tipo = trim((string)($_POST['tipo'] ?? ''));
    $osId = (int)($_POST['os_id'] ?? 0);
    if (!os_tipo_valido($tipo) || $osId <= 0) op_fail('Parâmetros inválidos.');

    $info = os_doc_info($tipo, $osId);
    // Reassinatura é permitida: se a O.S./recibo mudou, gera-se uma nova versão
    // assinada que sobrescreve a anterior (finalize usa ON DUPLICATE KEY UPDATE).

    $page = max(1, (int)($_POST['page'] ?? 1));
    $xn=(float)($_POST['xn'] ?? 0.55); $yn=(float)($_POST['yn'] ?? 0.80); $wn=(float)($_POST['wn'] ?? 0.24);

    $signer = os_signer_info();
    $baseBytes = os_generate_pdf_bytes($tipo, $osId);
    $codigo = implode('-', str_split(strtoupper(substr(hash('sha256', $baseBytes), 0, 16)), 4));
    $sealed = os_stamp_seal($baseBytes, [
        'page'=>$page,'xn'=>$xn,'yn'=>$yn,'wn'=>$wn,
        'nome'=>$signer['nome'],'cargo'=>$signer['cargo'],'codigo'=>$codigo,'quando'=>date('d/m/Y H:i:s'),
    ]);
    if (!$sealed || strncmp(ltrim($sealed), '%PDF', 4) !== 0) op_fail('Falha ao gerar o PDF com o selo.');

    $tmp = os_pades_dir() . '/sealed_' . bin2hex(random_bytes(6)) . '.pdf';
    file_put_contents($tmp, $sealed);
    $dcrt = __DIR__ . '/../oficios/pades_dummy.crt'; $dkey = __DIR__ . '/../oficios/pades_dummy.key';
    if (!is_file($dcrt) || !is_file($dkey)) op_fail('Certificado de placeholder ausente (../oficios/pades_dummy.*).');

    $pdf = new OsFpdiSig('P','mm','A4', true, 'UTF-8');
    $pdf->SetPrintHeader(false); $pdf->SetPrintFooter(false);
    $n = $pdf->setSourceFile($tmp);
    for ($i=1;$i<=$n;$i++){ $t=$pdf->importPage($i); $z=$pdf->getTemplateSize($t); $pdf->AddPage($z['orientation'], [$z['width'],$z['height']]); $pdf->useTemplate($t); }
    $pdf->setSigMaxLength(16000);
    $pdf->setSignatureAppearance(0,0,0,0,1);
    $pdf->setSignature('file://'.$dcrt, 'file://'.$dkey, '', '', 2, [], false);
    $prepared = $pdf->Output('prepared.pdf', 'S');
    @unlink($tmp);

    $prepared = AtlasPadesInjector::toEtsiCades($prepared);
    $br = AtlasPadesInjector::readByteRange($prepared);

    $sessionId = bin2hex(random_bytes(16));
    $preparedPath = os_pades_dir() . '/prep_' . $sessionId . '.pdf';
    file_put_contents($preparedPath, $prepared);
    file_put_contents(os_pades_dir() . '/sess_' . $sessionId . '.json', json_encode([
        'tipo'=>$tipo,'os_id'=>$osId,'prepared'=>basename($preparedPath),
        'holeStart'=>$br['holeStart'],'holeEnd'=>$br['holeEnd'],'brDigestHex'=>bin2hex($br['digest']),
        'codigo'=>$codigo,'pos'=>['page'=>$page,'xn'=>$xn,'yn'=>$yn,'wn'=>$wn],'created'=>time(),
    ], JSON_UNESCAPED_UNICODE));

    echo json_encode(['status'=>'success','session'=>$sessionId,'to_sign'=>base64_encode($br['digest']),'codigo'=>$codigo], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    op_fail('Erro ao preparar a assinatura.', $e->getMessage());
}
