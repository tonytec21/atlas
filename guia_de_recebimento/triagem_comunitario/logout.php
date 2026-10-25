<?php
/* atlas-auditoria */ if (is_file(__DIR__ . '/../../auditoria/hook.php')) { include_once __DIR__ . '/../../auditoria/hook.php'; }
session_start();
session_destroy();
header('Location: ../../login.php');
exit;
?>
