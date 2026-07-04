<?php
/**
 * oficios/pades_selftest.php
 * ---------------------------------------------------------------------------
 * Autoteste do pipeline PAdES (assinatura externa). NÃO usa o token e NÃO gera
 * certificados em runtime: usa o par empacotado pades_dummy.crt/.key tanto como
 * certificado do placeholder (igual ao pades_prepare.php) quanto como "token
 * simulado" (assina os signedAttributes com a chave dele). Prova, no SEU
 * servidor, que a montagem do CMS e a injeção produzem assinatura válida.
 *
 * Rode:  php pades_selftest.php   (ou abra no navegador)
 * ---------------------------------------------------------------------------
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$CLI = (php_sapi_name() === 'cli');
$NL = $CLI ? "\n" : "<br>\n";
function out($s){ global $NL; echo $s . $NL; }

require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/tcpdf/tcpdf.php';
require_once __DIR__ . '/assin_pades.php';

use setasign\Fpdi\Tcpdf\Fpdi;

$dummyCrt = __DIR__ . '/pades_dummy.crt';
$dummyKey = __DIR__ . '/pades_dummy.key';
if (!is_file($dummyCrt) || !is_file($dummyKey)) {
    out('ERRO: pades_dummy.crt/.key não encontrados na pasta do módulo.');
    exit(1);
}
$dummyCertPem = file_get_contents($dummyCrt);
$dummyKeyPem  = file_get_contents($dummyKey);

$tmp = rtrim(sys_get_temp_dir(), '/\\') . '/pades_selftest_' . getmypid();
@mkdir($tmp, 0777, true);

/* PDF-base */
$base = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$base->SetPrintHeader(false); $base->SetPrintFooter(false);
$base->AddPage(); $base->SetFont('helvetica','',12);
$base->writeHTML('<h2>Ofício de teste</h2><p>Prova de assinatura PAdES externa.</p><p>Atenciosamente,</p>');
file_put_contents("$tmp/base.pdf", $base->Output('base.pdf','S'));

/* PREPARO: importa + selo + placeholder (cert dummy empacotado) */
$pos = ['page'=>1,'xn'=>0.55,'yn'=>0.80,'wn'=>0.38];
$pdf = new Fpdi('P','mm','A4',true,'UTF-8');
$pdf->SetPrintHeader(false); $pdf->SetPrintFooter(false);
$n = $pdf->setSourceFile("$tmp/base.pdf");
for ($i=1;$i<=$n;$i++){
    $tpl=$pdf->importPage($i); $sz=$pdf->getTemplateSize($tpl);
    $pdf->AddPage($sz['orientation'], [$sz['width'],$sz['height']]);
    $pdf->useTemplate($tpl);
    if ($i===$pos['page']){
        $pw=$sz['width'];$ph=$sz['height'];$w=$pos['wn']*$pw;$h=$w*0.42;$x=$pos['xn']*$pw;$y=$pos['yn']*$ph;
        $pdf->SetDrawColor(37,99,235);$pdf->SetFillColor(245,248,255);$pdf->SetLineWidth(0.4);
        $pdf->Rect($x,$y,$w,$h,'DF');
        $pdf->SetTextColor(37,99,235);$pdf->SetFont('helvetica','B',6.5);
        $pdf->SetXY($x+2,$y+1.5);$pdf->Cell($w-4,3,'ASSINADO DIGITALMENTE',0,2);
    }
}
$pdf->setSignatureAppearance(0,0,0,0,1);
$pdf->setSignature('file://'.$dummyCrt, 'file://'.$dummyKey, '', '', 2, [], false);
$prepared = $pdf->Output('prepared.pdf','S');

/* SubFilter → ETSI.CAdES.detached e leitura do ByteRange */
$prepared = AtlasPadesInjector::toEtsiCades($prepared);
$br = AtlasPadesInjector::readByteRange($prepared);
out('== Preparo ==');
out("  prepared: ".strlen($prepared)." bytes | ByteRange a={$br['a']} len1={$br['len1']} b={$br['b']} len2={$br['len2']}");

/* signedAttrs (Modo A: PAdES-BES) — usando o cert dummy como "signatário" de teste */
$signer = new AtlasPadesSigner($dummyCertPem);
$signedAttrs = $signer->buildSignedAttrs($br['digest'], true);
$toSign = AtlasPadesSigner::digestOfSignedAttrs($signedAttrs);
out("  hash p/ o token: ".bin2hex($toSign));

/* TOKEN SIMULADO: assina os signedAttrs (RSA/SHA-256) com a chave dummy */
$sig = '';
if (!openssl_sign($signedAttrs, $sig, $dummyKeyPem, OPENSSL_ALGO_SHA256)) {
    out('ERRO: openssl_sign falhou: ' . openssl_error_string());
    exit(1);
}

/* CMS + injeção */
$cms = $signer->buildCms($signedAttrs, $sig);
$final = AtlasPadesInjector::inject($prepared, $br['holeStart'], $br['holeEnd'], $cms);
file_put_contents("$tmp/signed.pdf", $final);
file_put_contents("$tmp/cms.der", $cms);

/* Verificações */
out('== Verificação ==');
$br2 = AtlasPadesInjector::readByteRange($final);
$okDigest = hash_equals($br['digest'], $br2['digest']);
out('  ByteRange íntegro após injeção: ' . ($okDigest ? 'OK' : 'FALHOU'));

$content = substr($final,$br2['a'],$br2['len1']) . substr($final,$br2['b'],$br2['len2']);
file_put_contents("$tmp/content.bin", $content);

$v = openssl_verify($signedAttrs, $sig, $dummyCertPem, OPENSSL_ALGO_SHA256);
out('  Assinatura RSA sobre signedAttrs: ' . ($v===1 ? 'VÁLIDA' : 'INVÁLIDA'));

$sub = (strpos($final, '/SubFilter /ETSI.CAdES.detached') !== false) ? 'ETSI.CAdES.detached' : '??';
out('  SubFilter: ' . $sub);

/* openssl cms -verify (se o binário estiver disponível), com -binary */
$cmsOk = 'n/d (openssl CLI ausente)';
$openssl = trim((string)@shell_exec((stripos(PHP_OS,'WIN')===0 ? 'where' : 'command -v') . ' openssl 2>&1'));
$openssl = strtok($openssl, "\r\n");
if ($openssl !== '' && stripos($openssl,'not found')===false && $openssl!==false) {
    $cmd = escapeshellarg($openssl)." cms -verify -inform DER -in ".escapeshellarg("$tmp/cms.der").
           " -content ".escapeshellarg("$tmp/content.bin")." -binary -noverify -out ".escapeshellarg((stripos(PHP_OS,'WIN')===0?'NUL':'/dev/null'))." 2>&1";
    $o = (string)@shell_exec($cmd);
    $cmsOk = (stripos($o, 'successful') !== false) ? 'CMS Verification successful' : ('n/d: '.trim($o));
}
out('  openssl cms -verify: ' . $cmsOk);

out('');
out('PDF assinado (teste): ' . "$tmp/signed.pdf");
out($okDigest && $v===1 ? '>>> PIPELINE OK <<<' : '>>> PROBLEMA <<<');
