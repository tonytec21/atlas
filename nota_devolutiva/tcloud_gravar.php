<?php
/**
 * nota_devolutiva/tcloud_gravar.php — grava a nota devolutiva assinada pelo TCloud Assinador.
 * Chamado pelo Atlas Signum quando a estação devolve o PDF assinado; espelha nota_pades_finalize.php.
 */
require_once __DIR__ . '/assinatura_nota_config.php';
require_once __DIR__ . '/../signum/inc/tcloud_modulo.php';

function tcm_nota_gravar($dados, $pdf, $cert, $usuario)
{
    nd_ensure_schema();
    $numero = trim((string)($dados['numero'] ?? ''));
    if ($numero === '') throw new RuntimeException('Nota não informada.');
    if (strncmp($pdf, '%PDF', 4) !== 0) throw new RuntimeException('PDF assinado inválido.');

    $conn = nd_db();
    $stmt = $conn->prepare("SELECT assinado FROM notas_devolutivas WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) throw new RuntimeException('Nota devolutiva não encontrada.');
    if ((int)$row['assinado'] === 1) throw new RuntimeException('Esta nota já tinha sido assinada.');

    $dir = nd_dir_assinados() . '/' . nd_safe($numero);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Falha ao preparar o diretório da nota.');
    $fileName = nd_safe($numero) . '_assinada_' . date('Ymd_His') . '.pdf';
    if (@file_put_contents($dir . '/' . $fileName, $pdf) === false) throw new RuntimeException('Falha ao salvar o PDF.');
    @chmod($dir . '/' . $fileName, 0644);
    @copy($dir . '/' . $fileName, $dir . '/' . nd_safe($numero) . '.pdf');
    $relative = 'assinados/' . rawurlencode(nd_safe($numero)) . '/' . rawurlencode($fileName);

    $certTxt = substr(asg_tcm_titular_texto($cert), 0, 255) ?: null;
    $pos = $dados['pos'] ?? null;
    $meta = json_encode(['subfilter' => 'ETSI.CAdES.detached', 'pades' => 'TCloud Assinador (PAdES)', 'pos' => $pos,
                         'ip' => $_SERVER['REMOTE_ADDR'] ?? null], JSON_UNESCAPED_UNICODE);
    $codigo = substr((string)($dados['codigo'] ?? ''), 0, 64);
    $pagina = (int)($pos['page'] ?? 1);
    $agora = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE notas_devolutivas SET assinado=1, assinatura_arquivo=?, assinado_por=?, assinante_cert=?, assinado_em=?, assinatura_pagina=?, assinatura_codigo=?, assinatura_meta=? WHERE numero=?");
    if (!$stmt) throw new RuntimeException('Falha ao preparar a atualização da nota.');
    $stmt->bind_param('ssssisss', $relative, $usuario, $certTxt, $agora, $pagina, $codigo, $meta, $numero);
    $stmt->execute(); $stmt->close();

    nd_log("Nota {$numero} assinada (TCloud Assinador) por {$usuario} -> {$fileName}");
    return ['url' => nd_public_url($relative), 'codigo' => $codigo, 'numero' => $numero];
}
