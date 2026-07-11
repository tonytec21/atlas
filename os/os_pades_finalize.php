<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/assinatura_os_config.php';
header('Content-Type: application/json; charset=utf-8');
function of_fail($h,$t=''){ if($t) os_log('FINALIZE ERR: '.$t); echo json_encode(['status'=>'error','message'=>$h], JSON_UNESCAPED_UNICODE); exit; }
function os_extract_md($der){ $p="\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x09\x04";$L=strlen($der);$pl=strlen($p);$f=0;while(($i=strpos($der,$p,$f))!==false){for($m=$i+$pl;$m<$i+$pl+8&&$m+34<=$L;$m++)if($der[$m]==="\x04"&&$der[$m+1]==="\x20")return substr($der,$m+2,32);$f=$i+$pl;}return null; }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') of_fail('Método inválido.');
    if (!os_csrf_check($_POST['csrf'] ?? '')) of_fail('Sessão expirada.');
    os_ensure_schema();

    $sessionId = preg_replace('~[^0-9a-f]~', '', (string)($_POST['session'] ?? ''));
    $sigB64 = trim((string)($_POST['signature_b64'] ?? ''));
    if ($sessionId === '' || $sigB64 === '') of_fail('Parâmetros ausentes.');

    $sessFile = os_pades_dir() . '/sess_' . $sessionId . '.json';
    if (!is_file($sessFile)) of_fail('Sessão de assinatura não encontrada ou expirada.');
    $sess = json_decode(file_get_contents($sessFile), true);
    if (!is_array($sess)) of_fail('Sessão inválida.');

    $tipo = $sess['tipo']; $osId = (int)$sess['os_id'];
    $preparedPath = os_pades_dir() . '/' . basename($sess['prepared']);
    if (!is_file($preparedPath)) of_fail('Arquivo preparado não encontrado.');

    $cms = base64_decode($sigB64, true);
    if ($cms === false || strlen($cms) < 100) of_fail('CMS inválido (base64).');
    $brDigest = hex2bin($sess['brDigestHex']);
    $md = os_extract_md($cms);
    if ($md !== null && !hash_equals($brDigest, $md)) of_fail('A assinatura não corresponde a este documento.', 'messageDigest != ByteRange');

    $prepared = file_get_contents($preparedPath);
    $final = AtlasPadesInjector::inject($prepared, (int)$sess['holeStart'], (int)$sess['holeEnd'], $cms);
    $br2 = AtlasPadesInjector::readByteRange($final);
    if (!hash_equals($brDigest, $br2['digest'])) of_fail('Falha de integridade após injeção.');
    if (strncmp($final, '%PDF', 4) !== 0) of_fail('PDF final inválido.');

    $slug = os_safe($tipo) . '_' . os_safe($osId);
    $dir = os_dir_assinados() . '/' . $slug;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) of_fail('Falha ao preparar diretório.');
    $fileName = $slug . '_assinado_' . date('Ymd_His') . '.pdf';
    if (@file_put_contents($dir.'/'.$fileName, $final) === false) of_fail('Falha ao salvar o PDF.');
    @chmod($dir.'/'.$fileName, 0644);
    @copy($dir.'/'.$fileName, $dir.'/'.$slug.'.pdf');
    $relative = 'assinados/' . rawurlencode($slug) . '/' . rawurlencode($fileName);

    $certSubject = isset($_POST['cert_subject']) ? substr(trim((string)$_POST['cert_subject']),0,255) : null;
    $meta = json_encode(['subfilter'=>'ETSI.CAdES.detached','pades'=>'CMS SERPRO (AD-RB)','pos'=>$sess['pos'] ?? null,
        'ip'=>$_SERVER['REMOTE_ADDR'] ?? null], JSON_UNESCAPED_UNICODE);
    $por = $_SESSION['username'] ?? null; $codigo = substr((string)($sess['codigo'] ?? ''),0,64); $agora = date('Y-m-d H:i:s');

    $conn = os_db();
    $sql = "INSERT INTO os_documentos_assinados (tipo, os_id, assinado, assinatura_arquivo, assinado_por, assinante_cert, assinado_em, assinatura_codigo, assinatura_meta)
            VALUES (?,?,1,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE assinado=1, assinatura_arquivo=VALUES(assinatura_arquivo), assinado_por=VALUES(assinado_por),
              assinante_cert=VALUES(assinante_cert), assinado_em=VALUES(assinado_em), assinatura_codigo=VALUES(assinatura_codigo), assinatura_meta=VALUES(assinatura_meta)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sissssss', $tipo, $osId, $relative, $por, $certSubject, $agora, $codigo, $meta);
    $stmt->execute(); $stmt->close();

    @unlink($preparedPath); @unlink($sessFile);
    os_log("Documento $tipo da O.S. #$osId assinado por $por -> $fileName");
    echo json_encode(['status'=>'success','url'=>os_public_url($relative),'codigo'=>$codigo], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    of_fail('Erro ao finalizar a assinatura.', $e->getMessage());
}
