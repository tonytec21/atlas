<?php
/**
 * oficios/pades_finalize.php  (injeção do CMS do SERPRO)
 * ---------------------------------------------------------------------------
 * Fase 2: recebe o CMS/CAdES devolvido pelo Assinador SERPRO (sign('hash'))
 * e injeta no placeholder do PDF preparado, preservando o /ByteRange. Salva o
 * ofício assinado e atualiza o banco.
 *
 * POST:
 *   session        (string) id de pades_prepare.php
 *   signature_b64  (string) CMS (base64) devolvido pelo SERPRO (campo signature)
 *   cert_subject   (string) opcional — assunto do certificado (auditoria)
 *
 * Saída JSON: { status, url, codigo, numero }
 * ---------------------------------------------------------------------------
 */
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_config.php';
require_once __DIR__ . '/assin_pades.php';

header('Content-Type: application/json; charset=utf-8');

function pf_fail($human, $tech = '')
{
    if ($tech) assin_log('PADES-FINALIZE ERROR: ' . $tech);
    echo json_encode(['status' => 'error', 'message' => $human], JSON_UNESCAPED_UNICODE);
    exit;
}
function pades_dir()
{
    $d = assin_dir_assinados() . '/.pades';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}
/** Extrai os 32 bytes do atributo messageDigest (OID 1.2.840.113549.1.9.4) do CMS. */
function pades_extract_message_digest($der)
{
    $pat = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x09\x04";
    $len = strlen($der); $plen = strlen($pat);
    $from = 0;
    while (($i = strpos($der, $pat, $from)) !== false) {
        for ($m = $i + $plen; $m < $i + $plen + 8 && $m + 2 + 32 <= $len; $m++) {
            if ($der[$m] === "\x04" && $der[$m + 1] === "\x20") {
                return substr($der, $m + 2, 32);
            }
        }
        $from = $i + $plen;
    }
    return null;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') pf_fail('Método inválido.');
    assin_ensure_schema();

    $sessionId = isset($_POST['session']) ? preg_replace('~[^0-9a-f]~', '', (string)$_POST['session']) : '';
    $sigB64    = isset($_POST['signature_b64']) ? trim((string)$_POST['signature_b64']) : '';
    if ($sessionId === '' || $sigB64 === '') pf_fail('Parâmetros ausentes.');

    $sessFile = pades_dir() . '/sess_' . $sessionId . '.json';
    if (!is_file($sessFile)) pf_fail('Sessão de assinatura não encontrada ou expirada.');
    $sess = json_decode(file_get_contents($sessFile), true);
    if (!is_array($sess)) pf_fail('Sessão inválida.');

    $numero = $sess['numero'];
    $preparedPath = pades_dir() . '/' . basename($sess['prepared']);
    if (!is_file($preparedPath)) pf_fail('Arquivo preparado não encontrado.');

    $cms = base64_decode($sigB64, true);
    if ($cms === false || strlen($cms) < 100) pf_fail('CMS inválido (base64).', 'b64 decode');
    // aceita PEM-like caso venha com cabeçalho
    if (strncmp($cms, '-----', 5) === 0) {
        $cms = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $sigB64), true);
    }

    // Rede de segurança: o messageDigest do CMS precisa bater com o ByteRange
    $md = pades_extract_message_digest($cms);
    $brDigest = hex2bin($sess['brDigestHex']);
    if ($md === null) {
        assin_log('PADES-FINALIZE WARN: messageDigest não localizado no CMS');
    } elseif (!hash_equals($brDigest, $md)) {
        pf_fail(
            'A assinatura não corresponde a este documento (modo do Assinador). '
            . 'Verifique o veredito do modo hash (diag-serpro.php).',
            'messageDigest != ByteRange. md=' . bin2hex($md) . ' br=' . $sess['brDigestHex']
        );
    }

    // Injeta o CMS no placeholder
    $prepared = file_get_contents($preparedPath);
    $final = AtlasPadesInjector::inject($prepared, (int)$sess['holeStart'], (int)$sess['holeEnd'], $cms);

    // Sanidade: ByteRange assinado não pode ter mudado
    $br2 = AtlasPadesInjector::readByteRange($final);
    if (!hash_equals($brDigest, $br2['digest'])) pf_fail('Falha de integridade após injeção.', 'brDigest mismatch');
    if (strncmp($final, '%PDF', 4) !== 0) pf_fail('PDF final inválido.', 'sem %PDF');

    // ---- Gravação (espelha save_signed_oficio.php) ----
    $baseDir = assin_dir_assinados();
    $numeroSafe = preg_replace('~[^0-9A-Za-z_\-]~', '_', $numero);
    $dir = $baseDir . '/' . $numeroSafe;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) pf_fail('Falha ao preparar diretório.', 'mkdir ' . $dir);
    $ts = date('Ymd_His');
    $fileName = $numeroSafe . '_assinado_' . $ts . '.pdf';
    $fullPath = $dir . '/' . $fileName;
    if (@file_put_contents($fullPath, $final) === false) pf_fail('Falha ao salvar o PDF.', $fullPath);
    @chmod($fullPath, 0644);
    $stable = $dir . '/' . $numeroSafe . '.pdf';
    @copy($fullPath, $stable); @chmod($stable, 0644);

    $relative = 'assinados/' . rawurlencode($numeroSafe) . '/' . rawurlencode($fileName);
    $url = assin_public_url($relative);

    $certSubject = isset($_POST['cert_subject']) ? substr(trim((string)$_POST['cert_subject']), 0, 255) : null;
    $meta = [
        'subfilter'  => 'ETSI.CAdES.detached',
        'pades'      => 'CMS SERPRO (AD-RB)',
        'pos'        => $sess['pos'] ?? null,
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ];
    $assinadoPor = $_SESSION['username'] ?? null;
    $codigo = isset($sess['codigo']) ? substr((string)$sess['codigo'], 0, 64) : null;
    $pagina = isset($sess['pos']['page']) ? (int)$sess['pos']['page'] : null;
    $agora  = date('Y-m-d H:i:s');
    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);

    $conn = assin_db();
    $sql = "UPDATE oficios
               SET assinado = 1, assinatura_arquivo = ?, assinado_por = ?, assinante_cert = ?,
                   assinado_em = ?, assinatura_pagina = ?, assinatura_codigo = ?, assinatura_meta = ?, status = 1
             WHERE numero = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) pf_fail('Falha ao preparar atualização.', $conn->error);
    $stmt->bind_param('ssssisss', $relative, $assinadoPor, $certSubject, $agora, $pagina, $codigo, $metaJson, $numero);
    $stmt->execute(); $stmt->close();

    @unlink($preparedPath); @unlink($sessFile);
    assin_log("Ofício {$numero} assinado (PAdES/CMS SERPRO) por {$assinadoPor} -> {$fileName}");

    echo json_encode(['status' => 'success', 'url' => $url, 'codigo' => $codigo, 'numero' => $numero], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    pf_fail('Erro ao finalizar a assinatura.', $e->getMessage());
}
