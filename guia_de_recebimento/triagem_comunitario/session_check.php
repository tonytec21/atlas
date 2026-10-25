<?php
/* atlas-auditoria */ if (is_file(__DIR__ . '/../../auditoria/hook.php')) { include_once __DIR__ . '/../../auditoria/hook.php'; }
session_start();

function checkSession() {
    if (!isset($_SESSION['username'])) {
        header('Location: ../../login.php');
        exit;
    }
}
?>
