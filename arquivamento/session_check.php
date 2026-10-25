<?php
/* atlas-auditoria */ if (is_file(__DIR__ . '/../auditoria/hook.php')) { include_once __DIR__ . '/../auditoria/hook.php'; }
/** Compatibilidade: checkSession() continua existindo, agora endurecida. */
require_once __DIR__ . '/bootstrap.php';

if (!function_exists('checkSession')) {
    function checkSession() { arq_exige_login(); }
}
