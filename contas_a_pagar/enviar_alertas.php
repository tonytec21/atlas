<?php
/**
 * enviar_alertas.php — verificação/envio de alertas de contas (vencidas e a vencer).
 * Uso: agende no cron/Agendador (php enviar_alertas.php) OU acesse pela web (requer sessão).
 */
require_once __DIR__ . '/config.php';
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/session_check.php'; checkSession();
    // pela web, só quem tem acesso ao módulo (antes, qualquer usuário logado disparava o envio)
    require_once __DIR__ . '/guard_acesso.php'; cap_guard();
    header('Content-Type: text/plain; charset=utf-8');
}
cap_enviar_alertas(true);
