<?php
/**
 * =====================================================================
 * auditoria_os_arquivo.php — Abre um comprovante/anexo citado na auditoria
 * ---------------------------------------------------------------------
 * ATLAS-OS-BUILD: 2026-10-03-auditoria
 * RESTRITO A ADMINISTRADOR.
 *
 * Só serve arquivos que constam no retrato de um registro de auditoria
 * (?ev=ID&lado=antes|depois&tipo=comprovante|anexo&rid=ID-da-linha).
 * Comprovantes excluídos ficam em comprovantes_pagamento/_excluidos/.
 * =====================================================================
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/../checar_acesso_de_administrador.php');
require_once __DIR__ . '/auditoria_os_lib.php';

function osaud_arq_falha(string $msg, int $http = 404): void
{
    http_response_code($http);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Arquivo indisponível</title>'
       . '<p style="font-family:system-ui,Arial;padding:32px;color:#475569">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

try {
    $pdo  = osaud_pdo();
    $ev   = osaud_evento($pdo, (int) ($_GET['ev'] ?? 0));
    $lado = ($_GET['lado'] ?? '') === 'antes' ? 'antes' : 'depois';
    $tipo = ($_GET['tipo'] ?? '') === 'anexo' ? 'anexo' : 'comprovante';
    $rid  = (string) (int) ($_GET['rid'] ?? 0);
    if (!$ev || !is_array($ev[$lado] ?? null)) {
        osaud_arq_falha('Registro de auditoria não encontrado.');
    }

    $secao = $tipo === 'anexo' ? 'anexos' : 'comprovantes';
    $linha = null;
    foreach ((array) ($ev[$lado][$secao] ?? []) as $r) {
        if ((string) ($r['id'] ?? '') === $rid) {
            $linha = $r;
            break;
        }
    }
    if (!$linha) {
        osaud_arq_falha('O arquivo não consta neste registro de auditoria.');
    }

    $candidatos = [];
    if ($tipo === 'comprovante') {
        $base = __DIR__ . '/comprovantes_pagamento';
        $nome = basename((string) ($linha['arquivo'] ?? ''));
        $candidatos = [$base . '/' . $nome, $base . '/_excluidos/' . $nome];
        $nomeExib = (string) ($linha['nome_original'] ?? $nome);
    } else {
        $base = __DIR__ . '/anexos';
        $nome = basename((string) ($linha['caminho_anexo'] ?? ''));
        $osId = (int) ($linha['ordem_servico_id'] ?? $ev['os_id']);
        $candidatos = [$base . '/' . $osId . '/' . $nome];
        $nomeExib = $nome;
    }

    $baseReal = realpath($base);
    $arquivo = null;
    foreach ($candidatos as $c) {
        $r = realpath($c);
        if ($r && $baseReal && strncmp($r, $baseReal . DIRECTORY_SEPARATOR, strlen($baseReal) + 1) === 0 && is_file($r)) {
            $arquivo = $r;
            break;
        }
    }
    if ($nome === '' || !$arquivo) {
        osaud_arq_falha('O arquivo "' . $nomeExib . '" não está mais no servidor.');
    }

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo) ?: $mime;
    }
    $inline = (bool) preg_match('#^(application/pdf|image/(png|jpe?g|gif|webp))$#', $mime);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($arquivo));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="'
        . str_replace(['"', "\r", "\n"], '', $nomeExib) . '"; filename*=UTF-8\'\'' . rawurlencode($nomeExib));
    readfile($arquivo);
} catch (Throwable $e) {
    error_log('[auditoria_os_arquivo] ' . $e->getMessage());
    osaud_arq_falha('Falha ao abrir o arquivo.', 500);
}
