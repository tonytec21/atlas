<?php
/* atlas-auditoria */ if (is_file(__DIR__ . '/../auditoria/hook.php')) { include_once __DIR__ . '/../auditoria/hook.php'; }
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('checkSession')) {
    function checkSession() {
        if (!isset($_SESSION['username'])) {
            header('Location: ../login.php');
            exit;
        }
    }
}
