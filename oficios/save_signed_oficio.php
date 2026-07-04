<?php
/**
 * oficios/save_signed_oficio.php
 * ---------------------------------------------------------------------------
 * Recebe o PDF JÁ ASSINADO pelo Assinador SERPRO (base64, contendo a
 * assinatura criptográfica ICP-Brasil/PAdES embutida) e persiste:
 *   - grava o arquivo em /oficios/assinados/<numero>/<numero>_assinado_<ts>.pdf
 *   - mantém um ponteiro estável <numero>.pdf
 *   - atualiza a tabela `oficios` (assinado=1, metadados, trava edição)
 *
 * POST:
 *   numero      (string)  obrigatório
 *   pdf_base64  (string)  obrigatório — PDF assinado
 *   page,xn,yn,wn         opcional — posição do selo (auditoria)
 *   codigo      (string)  opcional — código de verificação exibido no selo
 *   cert_subject(string)  opcional — titular do certificado (retorno do SERPRO)
 *
 * Saída JSON: { status:"success", url:"...", filename:"...", assinado_em:"..." }
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/session_check.php';
checkSession();
require_once __DIR__ . '/assinatura_config.php';

header('Content-Type: application/json; charset=utf-8');

function s_fail($human, $tech = '')
{
    if ($tech) {
        assin_log('SAVE ERROR: ' . $tech);
    }
    echo json_encode(['status' => 'error', 'message' => $human], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        s_fail('Método inválido.');
    }

    assin_ensure_schema();

    $numero = isset($_POST['numero']) ? trim((string)$_POST['numero']) : '';
    $b64    = isset($_POST['pdf_base64']) ? (string)$_POST['pdf_base64'] : '';
    if ($numero === '' || $b64 === '') {
        s_fail('Parâmetros obrigatórios ausentes.');
    }

    // Alguns clientes devolvem "data:application/pdf;base64,...."
    if (($pos = strpos($b64, 'base64,')) !== false) {
        $b64 = substr($b64, $pos + 7);
    }
    $bytes = base64_decode($b64, true);
    if ($bytes === false || strlen($bytes) < 100) {
        s_fail('Conteúdo assinado inválido.', 'base64 inválido/curto');
    }
    $probe = ltrim($bytes);
    if (strncmp($probe, '%PDF', 4) !== 0) {
        s_fail('O conteúdo assinado não é um PDF válido.', 'sem %PDF');
    }
    $bytes = $probe;

    // Diretório de destino
    $baseDir = assin_dir_assinados();
    $numeroSafe = preg_replace('~[^0-9A-Za-z_\-]~', '_', $numero);
    $dir = $baseDir . '/' . $numeroSafe;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        s_fail('Falha ao preparar o diretório de destino.', 'mkdir ' . $dir);
    }

    $ts = date('Ymd_His');
    $fileName = $numeroSafe . '_assinado_' . $ts . '.pdf';
    $fullPath = $dir . '/' . $fileName;
    if (@file_put_contents($fullPath, $bytes) === false) {
        s_fail('Falha ao salvar o PDF assinado.', 'file_put_contents ' . $fullPath);
    }
    @chmod($fullPath, 0644);

    // Ponteiro estável
    $stable = $dir . '/' . $numeroSafe . '.pdf';
    @copy($fullPath, $stable);
    @chmod($stable, 0644);

    // URL pública
    $relative = 'assinados/' . rawurlencode($numeroSafe) . '/' . rawurlencode($fileName);
    $url = assin_public_url($relative);

    // Metadados / auditoria
    $meta = [
        'page'   => isset($_POST['page']) ? (int)$_POST['page'] : null,
        'xn'     => isset($_POST['xn']) ? (float)$_POST['xn'] : null,
        'yn'     => isset($_POST['yn']) ? (float)$_POST['yn'] : null,
        'wn'     => isset($_POST['wn']) ? (float)$_POST['wn'] : null,
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ];

    $assinadoPor = $_SESSION['username'] ?? null;
    $certSubject = isset($_POST['cert_subject']) ? substr(trim((string)$_POST['cert_subject']), 0, 255) : null;
    $codigo      = isset($_POST['codigo']) ? substr((string)$_POST['codigo'], 0, 64) : null;
    $pagina      = isset($_POST['page']) ? (int)$_POST['page'] : null;
    $agora       = date('Y-m-d H:i:s');
    $metaJson    = json_encode($meta, JSON_UNESCAPED_UNICODE);

    $conn = assin_db();
    $sql = "UPDATE oficios
               SET assinado = 1,
                   assinatura_arquivo = ?,
                   assinado_por = ?,
                   assinante_cert = ?,
                   assinado_em = ?,
                   assinatura_pagina = ?,
                   assinatura_codigo = ?,
                   assinatura_meta = ?,
                   status = 1
             WHERE numero = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        s_fail('Falha ao preparar atualização.', $conn->error);
    }
    $stmt->bind_param(
        'ssssisss',
        $relative,
        $assinadoPor,
        $certSubject,
        $agora,
        $pagina,
        $codigo,
        $metaJson,
        $numero
    );
    $stmt->execute();
    $stmt->close();

    assin_log("Ofício {$numero} assinado por {$assinadoPor} -> {$fileName}");

    echo json_encode([
        'status'      => 'success',
        'url'         => $url,
        'filename'    => $fileName,
        'assinado_em' => $agora,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    s_fail('Erro ao salvar o documento assinado.', $e->getMessage());
}
