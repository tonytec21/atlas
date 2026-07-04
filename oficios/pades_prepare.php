<?php
/**
 * oficios/pades_prepare.php  (modelo de injeção do CMS do SERPRO)
 * ---------------------------------------------------------------------------
 * Fase 1: gera o PDF do ofício com o NOSSO selo na posição do clique e o
 * placeholder de assinatura (/ByteRange + /Contents), troca o /SubFilter para
 * ETSI.CAdES.detached e calcula o digest do /ByteRange.
 *
 * O Assinador SERPRO (sign('hash', ...)) devolve um CMS/CAdES completo (com
 * política AD-RB). Dependendo do modo do Assinador, enviaremos:
 *   - "digesto": o SHA-256 do ByteRange   -> campo to_sign
 *   - "conteudo": o ByteRange inteiro      -> campo content_b64
 * (o veredito é dado por diag-serpro.php, botão 5).
 *
 * POST: numero (obrigatório); page,xn,yn,wn (posição do selo); timbrado (S|N).
 * Saída JSON: { status, session, to_sign, content_b64, subfilter }
 * ---------------------------------------------------------------------------
 */
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_config.php';
require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/tcpdf/tcpdf.php';
require_once __DIR__ . '/assin_pades.php';

use setasign\Fpdi\Tcpdf\Fpdi;

header('Content-Type: application/json; charset=utf-8');

/** Fpdi que permite aumentar o placeholder da assinatura (para caber CMS+carimbo). */
class AtlasFpdiSig extends Fpdi
{
    public function setSigMaxLength($n) { $this->signature_max_length = (int)$n; }
}

function pp_fail($human, $tech = '')
{
    if ($tech) assin_log('PADES-PREPARE ERROR: ' . $tech);
    echo json_encode(['status' => 'error', 'message' => $human], JSON_UNESCAPED_UNICODE);
    exit;
}
function pades_dir()
{
    $d = assin_dir_assinados() . '/.pades';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}
/** Cert dummy só para o TCPDF criar o placeholder (o PKCS#7 dele é descartado). */
function pades_dummy_paths()
{
    $bundledCrt = __DIR__ . '/pades_dummy.crt';
    $bundledKey = __DIR__ . '/pades_dummy.key';
    if (is_file($bundledCrt) && is_file($bundledKey)) return [$bundledCrt, $bundledKey];

    $d = pades_dir(); $crt = $d . '/dummy.crt'; $key = $d . '/dummy.key';
    if (!is_file($crt) || !is_file($key)) {
        $conf = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => atlas_openssl_conf()];
        $pk = openssl_pkey_new($conf);
        if ($pk === false) pp_fail('OpenSSL indisponível para gerar o placeholder.', 'pkey_new false');
        $csr = openssl_csr_new(['commonName' => 'Placeholder Atlas'], $pk, $conf);
        $x = openssl_csr_sign($csr, null, $pk, 3650, $conf);
        openssl_x509_export($x, $certPem); openssl_pkey_export($pk, $keyPem, null, $conf);
        file_put_contents($crt, $certPem); file_put_contents($key, $keyPem); @chmod($key, 0600);
    }
    return [$crt, $key];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') pp_fail('Método inválido.');
    assin_ensure_schema();

    $numero = isset($_POST['numero']) ? trim((string)$_POST['numero']) : '';
    if ($numero === '') pp_fail('Número do ofício não informado.');
    $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
    $xn = isset($_POST['xn']) ? (float)$_POST['xn'] : 0.55;
    $yn = isset($_POST['yn']) ? (float)$_POST['yn'] : 0.80;
    $wn = isset($_POST['wn']) ? (float)$_POST['wn'] : 0.38;
    $timbrado = assin_timbrado_flag($_POST['timbrado'] ?? null);

    $conn = assin_db();
    $stmt = $conn->prepare("SELECT assinante, cargo_assinante, assinado FROM oficios WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero); $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) pp_fail('Ofício não encontrado.');
    $of = $res->fetch_assoc(); $stmt->close();
    if ((int)($of['assinado'] ?? 0) === 1) pp_fail('Este ofício já está assinado.');

    // 1) PDF-base + NOSSO selo na posição do clique
    $baseBytes = assin_generate_pdf_bytes($numero, $timbrado);
    $hash = strtoupper(substr(hash('sha256', $baseBytes), 0, 16));
    $codigo = implode('-', str_split($hash, 4));
    $sealedBytes = assin_stamp_seal($baseBytes, [
        'page' => $page, 'xn' => $xn, 'yn' => $yn, 'wn' => $wn,
        'nome' => $of['assinante'] ?? '', 'cargo' => $of['cargo_assinante'] ?? '',
        'numero' => $numero, 'codigo' => $codigo, 'quando' => date('d/m/Y H:i:s'),
    ]);
    if (!$sealedBytes || strncmp(ltrim($sealedBytes), '%PDF', 4) !== 0) pp_fail('Falha ao gerar o PDF com o selo.', 'sealed inválido');

    // 2) Placeholder de assinatura (cert dummy) — placeholder grande p/ caber CMS+carimbo
    $tmpSealed = pades_dir() . '/sealed_' . bin2hex(random_bytes(6)) . '.pdf';
    file_put_contents($tmpSealed, $sealedBytes);
    list($dcrt, $dkey) = pades_dummy_paths();

    $pdf = new AtlasFpdiSig('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetPrintHeader(false); $pdf->SetPrintFooter(false);
    $n = $pdf->setSourceFile($tmpSealed);
    for ($i = 1; $i <= $n; $i++) {
        $tpl = $pdf->importPage($i); $sz = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($sz['orientation'], [$sz['width'], $sz['height']]);
        $pdf->useTemplate($tpl);
    }
    $pdf->setSigMaxLength(16000);                    // ~16 KB p/ CMS AD-RB + carimbo do tempo
    $pdf->setSignatureAppearance(0, 0, 0, 0, 1);
    $pdf->setSignature('file://' . $dcrt, 'file://' . $dkey, '', '', 2, [], false);
    $prepared = $pdf->Output('prepared.pdf', 'S');
    @unlink($tmpSealed);

    // 3) SubFilter -> ETSI.CAdES.detached (PAdES). Depois, ByteRange.
    $prepared = AtlasPadesInjector::toEtsiCades($prepared);
    $br = AtlasPadesInjector::readByteRange($prepared);
    $content = substr($prepared, $br['a'], $br['len1']) . substr($prepared, $br['b'], $br['len2']);

    // 4) Sessão
    $sessionId = bin2hex(random_bytes(16));
    $preparedPath = pades_dir() . '/prep_' . $sessionId . '.pdf';
    file_put_contents($preparedPath, $prepared);
    $session = [
        'numero'      => $numero,
        'prepared'    => basename($preparedPath),
        'holeStart'   => $br['holeStart'],
        'holeEnd'     => $br['holeEnd'],
        'brDigestHex' => bin2hex($br['digest']),
        'codigo'      => $codigo,
        'pos'         => ['page' => $page, 'xn' => $xn, 'yn' => $yn, 'wn' => $wn],
        'created'     => time(),
    ];
    file_put_contents(pades_dir() . '/sess_' . $sessionId . '.json', json_encode($session, JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'status'      => 'success',
        'session'     => $sessionId,
        'to_sign'     => base64_encode($br['digest']),   // modo DIGESTO (envie isto ao sign('hash'))
        'content_b64' => base64_encode($content),         // modo CONTEUDO (alternativa)
        'subfilter'   => 'ETSI.CAdES.detached',
        'codigo'      => $codigo,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    pp_fail('Erro ao preparar a assinatura.', $e->getMessage());
}
