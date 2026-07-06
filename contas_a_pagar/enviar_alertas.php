<?php
/**
 * enviar_alertas.php — verificação/envio de alertas de contas (vencidas e a vencer).
 * Uso: agende no cron/Agendador (php enviar_alertas.php) OU acesse pela web (requer sessão).
 */
require_once __DIR__ . '/config.php';
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/session_check.php'; checkSession();
}
cap_enviar_alertas(true);
