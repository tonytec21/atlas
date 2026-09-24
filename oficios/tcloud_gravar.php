<?php
/**
 * oficios/tcloud_gravar.php — grava o ofício assinado pelo TCloud Assinador.
 * Chamado pelo Atlas Signum (tcloud_estacao.php) quando a estação devolve o PDF assinado; espelha a
 * gravação do pades_finalize.php. Não usa a sessão: o usuário vem do pedido.
 */
require_once __DIR__ . '/assinatura_config.php';
require_once __DIR__ . '/../signum/inc/tcloud_modulo.php';

function tcm_oficio_gravar($dados, $pdf, $cert, $usuario)
{
    assin_ensure_schema();
    $numero = trim((string)($dados['numero'] ?? ''));
    if ($numero === '') throw new RuntimeException('Ofício não informado.');
    if (strncmp($pdf, '%PDF', 4) !== 0) throw new RuntimeException('PDF assinado inválido.');

    $conn = assin_db();
    $stmt = $conn->prepare("SELECT assinado FROM oficios WHERE numero = ? LIMIT 1");
    $stmt->bind_param('s', $numero); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) throw new RuntimeException('Ofício não encontrado.');
    if ((int)$row['assinado'] === 1) throw new RuntimeException('Este ofício já tinha sido assinado.');

    $numeroSafe = preg_replace('~[^0-9A-Za-z_\-]~', '_', $numero);
    $dir = assin_dir_assinados() . '/' . $numeroSafe;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Falha ao preparar o diretório do ofício.');
    $fileName = $numeroSafe . '_assinado_' . date('Ymd_His') . '.pdf';
    if (@file_put_contents($dir . '/' . $fileName, $pdf) === false) throw new RuntimeException('Falha ao salvar o PDF.');
    @chmod($dir . '/' . $fileName, 0644);
    @copy($dir . '/' . $fileName, $dir . '/' . $numeroSafe . '.pdf');
    $relative = 'assinados/' . rawurlencode($numeroSafe) . '/' . rawurlencode($fileName);

    $certTxt = substr(asg_tcm_titular_texto($cert), 0, 255) ?: null;
    $pos = $dados['pos'] ?? null;
    $meta = json_encode(['subfilter' => 'ETSI.CAdES.detached', 'pades' => 'TCloud Assinador (PAdES)', 'pos' => $pos,
                         'ip' => $_SERVER['REMOTE_ADDR'] ?? null], JSON_UNESCAPED_UNICODE);
    $codigo = substr((string)($dados['codigo'] ?? ''), 0, 64);
    $pagina = isset($pos['page']) ? (int)$pos['page'] : null;
    $agora = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE oficios
               SET assinado = 1, assinatura_arquivo = ?, assinado_por = ?, assinante_cert = ?,
                   assinado_em = ?, assinatura_pagina = ?, assinatura_codigo = ?, assinatura_meta = ?, status = 1
             WHERE numero = ?");
    if (!$stmt) throw new RuntimeException('Falha ao preparar a atualização do ofício.');
    $stmt->bind_param('ssssisss', $relative, $usuario, $certTxt, $agora, $pagina, $codigo, $meta, $numero);
    $stmt->execute(); $stmt->close();

    assin_log("Ofício {$numero} assinado (PAdES/TCloud Assinador) por {$usuario} -> {$fileName}");
    return ['url' => assin_public_url($relative), 'codigo' => $codigo, 'numero' => $numero];
}
