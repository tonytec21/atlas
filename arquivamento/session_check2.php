<?php
/* atlas-auditoria */ if (is_file(__DIR__ . '/../auditoria/hook.php')) { include_once __DIR__ . '/../auditoria/hook.php'; }
/** Arquivo obsoleto: a autenticação é do Atlas, não deste módulo. */
require_once __DIR__ . '/bootstrap.php';
arq_exige_login();
