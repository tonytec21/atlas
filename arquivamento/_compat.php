<?php
/**
 * Atlas · Arquivamento Digital
 * Ponte de compatibilidade com os endpoints da versão anterior.
 *
 * Outros módulos do Atlas (principalmente Tarefas) chamam arquivos antigos
 * deste módulo. Em vez de quebrar essas integrações, os arquivos antigos
 * continuam existindo e delegam para a nova camada — agora com sessão
 * obrigatória e validação de origem.
 *
 * Todos eles emitem um aviso no log para que a migração seja rastreável.
 */

require_once __DIR__ . '/bootstrap.php';

/** Registra a chamada legada uma vez por requisição. */
function arq_compat_avisar($arquivo)
{
    error_log('[arquivamento] Endpoint legado chamado: ' . $arquivo
        . ' — origem: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'desconhecida'));
}

/**
 * Escrita vinda de integração antiga: aceita o token CSRF do módulo ou,
 * na falta dele, exige que a requisição venha do mesmo host (Origin/Referer).
 * É menos rígido que arq_exige_post_seguro(), mas ainda barra POST de fora.
 */
function arq_compat_exige_post()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        arq_erro('Método não permitido.', 405);
    }

    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN']
           : (isset($_POST['_csrf']) ? $_POST['_csrf'] : '');
    if ($token !== '' && isset($_SESSION['arq_csrf']) && hash_equals($_SESSION['arq_csrf'], $token)) {
        return;
    }

    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $origem = '';
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        $origem = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
    } elseif (!empty($_SERVER['HTTP_REFERER'])) {
        $origem = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    }
    $hostSemPorta = preg_replace('/:\d+$/', '', $host);

    if ($origem !== '' && $origem === $hostSemPorta) {
        return;
    }
    // Sec-Fetch-Site é enviado pelos navegadores modernos.
    if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] === 'same-origin') {
        return;
    }

    arq_erro('Requisição bloqueada: origem não confirmada.', 403);
}
