<?php
/**
 * os/tcloud_gravar.php — grava o documento da O.S. assinado pelo TCloud Assinador.
 * Chamado pelo Atlas Signum quando a estação devolve o PDF assinado; espelha os_pades_finalize.php
 * (reassinatura substitui a versão anterior).
 */
require_once __DIR__ . '/assinatura_os_config.php';
require_once __DIR__ . '/../signum/inc/tcloud_modulo.php';

function tcm_os_gravar($dados, $pdf, $cert, $usuario)
{
    os_ensure_schema();
    $tipo = (string)($dados['tipo'] ?? ''); $osId = (int)($dados['os_id'] ?? 0);
    if (!os_tipo_valido($tipo) || $osId <= 0) throw new RuntimeException('Documento da O.S. inválido.');
    if (strncmp($pdf, '%PDF', 4) !== 0) throw new RuntimeException('PDF assinado inválido.');

    $slug = os_safe($tipo) . '_' . os_safe($osId);
    $dir = os_dir_assinados() . '/' . $slug;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Falha ao preparar o diretório.');
    $fileName = $slug . '_assinado_' . date('Ymd_His') . '.pdf';
    if (@file_put_contents($dir . '/' . $fileName, $pdf) === false) throw new RuntimeException('Falha ao salvar o PDF.');
    @chmod($dir . '/' . $fileName, 0644);
    @copy($dir . '/' . $fileName, $dir . '/' . $slug . '.pdf');
    $relative = 'assinados/' . rawurlencode($slug) . '/' . rawurlencode($fileName);

    $certTxt = substr(asg_tcm_titular_texto($cert), 0, 255) ?: null;
    $meta = json_encode(['subfilter' => 'ETSI.CAdES.detached', 'pades' => 'TCloud Assinador (PAdES)', 'pos' => $dados['pos'] ?? null,
                         'ip' => $_SERVER['REMOTE_ADDR'] ?? null], JSON_UNESCAPED_UNICODE);
    $codigo = substr((string)($dados['codigo'] ?? ''), 0, 64);
    $agora = date('Y-m-d H:i:s');

    $conn = os_db();
    $sql = "INSERT INTO os_documentos_assinados (tipo, os_id, assinado, assinatura_arquivo, assinado_por, assinante_cert, assinado_em, assinatura_codigo, assinatura_meta)
            VALUES (?,?,1,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE assinado=1, assinatura_arquivo=VALUES(assinatura_arquivo), assinado_por=VALUES(assinado_por),
              assinante_cert=VALUES(assinante_cert), assinado_em=VALUES(assinado_em), assinatura_codigo=VALUES(assinatura_codigo), assinatura_meta=VALUES(assinatura_meta)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Falha ao preparar a gravação.');
    $stmt->bind_param('sissssss', $tipo, $osId, $relative, $usuario, $certTxt, $agora, $codigo, $meta);
    $stmt->execute(); $stmt->close();

    os_log("Documento $tipo da O.S. #$osId assinado (TCloud Assinador) por $usuario -> $fileName");
    return ['url' => os_public_url($relative), 'codigo' => $codigo, 'tipo' => $tipo, 'os_id' => $osId];
}
