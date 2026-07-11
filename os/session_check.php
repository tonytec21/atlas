<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('checkSession')) {
    function checkSession() {
        if (!isset($_SESSION['username'])) {
            header('Location: ../login.php');
            exit;
        }
    }
}
