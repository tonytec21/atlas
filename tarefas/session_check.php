<?php
/**
 * Atlas · Tarefas — compatibilidade: session_check.php.
 *
 * Mantido porque os arquivos de impressão ainda o incluem. A sessão em si já
 * é aberta por core/bootstrap.php.
 */

require_once __DIR__ . '/core/bootstrap.php';

if (!function_exists('checkSession')) {
    function checkSession()
    {
        exigir_login();
    }
}
