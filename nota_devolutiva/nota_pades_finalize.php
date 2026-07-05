<?php
/** nota_pades_finalize.php — Fase 2 PAdES: injeta o CMS do SERPRO e grava a nota assinada. */
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_nota_config.php';

header('Content-Type: application/json; charset=utf-8');

function ndf_fail($h, $t = '') { if ($t) nd_log('FINALIZE ERROR: ' . $t); echo json_encode(['status'=>'error','message'=>$h], JSON_UNESCAPED_UNICODE); exit; }
function nd_pades_dir() { $d = nd_dir_assinados() . '/.pades'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
function nd_extract_md($der) {
    $pat = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x09\x04"; $len = strlen($der); $pl = strlen($pat); $f = 0;
    while (($i = strpos($der, $pat, $f)) !== false) {
        for ($m = $i + $pl; $m < $i + $pl + 8 && $m + 34 <= $len; $m++) if ($der[$m] === "\x04" && $der[$m+1] === "\x20") return substr($der, $m + 2, 32);
        $f = $i + $pl;
    }
    return null;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ndf_fail('Método inválido.');
    if (!nd_csrf_check($_POST['csrf'] ?? '')) ndf_fail('Sessão expirada. Recarregue a página.');
    nd_ensure_schema();

    $sessionId = preg_replace('~[^0-9a-f]~', '', (string)($_POST['session'] ?? ''));
    $sigB64 = trim((string)($_POST['signature_b64'] ?? ''));
    if ($sessionId === '' || $sigB64 === '') ndf_fail('Parâmetros ausentes.');

    $sessFile = nd_pades_dir() . '/sess_' . $sessionId . '.json';
    if (!is_file($sessFile)) ndf_fail('Sessão de assinatura não encontrada ou expirada.');
    $sess = json_decode(file_get_contents($sessFile), true);
    if (!is_array($sess)) ndf_fail('Sessão inválida.');

    $numero = $sess['numero'];
    $preparedPath = nd_pades_dir() . '/' . basename($sess['prepared']);
    if (!is_file($preparedPath)) ndf_fail('Arquivo preparado não encontrado.');

    $cms = base64_decode($sigB64, true);
    if ($cms === false || strlen($cms) < 100) ndf_fail('CMS inválido (base64).');

    $brDigest = hex2bin($sess['brDigestHex']);
    $md = nd_extract_md($cms);
    if ($md !== null && !hash_equals($brDigest, $md)) {
        ndf_fail('A assinatura não corresponde a este documento.', 'messageDigest != ByteRange');
    }

    $prepared = file_get_contents($preparedPath);
    $final = AtlasPadesInjector::inject($prepared, (int)$sess['holeStart'], (int)$sess['holeEnd'], $cms);
    $br2 = AtlasPadesInjector::readByteRange($final);
    if (!hash_equals($brDigest, $br2['digest'])) ndf_fail('Falha de integridade após injeção.', 'brDigest mismatch');
    if (strncmp($final, '%PDF', 4) !== 0) ndf_fail('PDF final inválido.');

    // Gravação
    $dir = nd_dir_assinados() . '/' . nd_safe($numero);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) ndf_fail('Falha ao preparar diretório.', $dir);
    $fileName = nd_safe($numero) . '_assinada_' . date('Ymd_His') . '.pdf';
    $fullPath = $dir . '/' . $fileName;
    if (@file_put_contents($fullPath, $final) === false) ndf_fail('Falha ao salvar o PDF.');
    @chmod($fullPath, 0644);
    @copy($fullPath, $dir . '/' . nd_safe($numero) . '.pdf');

    $relative = 'assinados/' . rawurlencode(nd_safe($numero)) . '/' . rawurlencode($fileName);

    $certSubject = isset($_POST['cert_subject']) ? substr(trim((string)$_POST['cert_subject']), 0, 255) : null;
    $meta = json_encode([
        'subfilter'=>'ETSI.CAdES.detached','pades'=>'CMS SERPRO (AD-RB)','pos'=>$sess['pos'] ?? null,
        'ip'=>$_SERVER['REMOTE_ADDR'] ?? null,'user_agent'=>substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ], JSON_UNESCAPED_UNICODE);
    $assinadoPor = $_SESSION['username'] ?? null;
    $codigo = substr((string)($sess['codigo'] ?? ''), 0, 64);
    $pagina = (int)($sess['pos']['page'] ?? 1);
    $agora = date('Y-m-d H:i:s');

    $conn = nd_db();
    $sql = "UPDATE notas_devolutivas SET assinado=1, assinatura_arquivo=?, assinado_por=?, assinante_cert=?, assinado_em=?, assinatura_pagina=?, assinatura_codigo=?, assinatura_meta=? WHERE numero=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) ndf_fail('Falha ao preparar atualização.', $conn->error);
    $stmt->bind_param('ssssisss', $relative, $assinadoPor, $certSubject, $agora, $pagina, $codigo, $meta, $numero);
    $stmt->execute(); $stmt->close();

    @unlink($preparedPath); @unlink($sessFile);
    nd_log("Nota {$numero} assinada por {$assinadoPor} -> {$fileName}");

    echo json_encode(['status'=>'success','url'=>nd_public_url($relative),'codigo'=>$codigo,'numero'=>$numero], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ndf_fail('Erro ao finalizar a assinatura.', $e->getMessage());
}
