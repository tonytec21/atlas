<?php
session_start();

function checkSession() {
    if (!isset($_SESSION['username'])) {
        header('Location: ../login.php');
        exit;
    }
}
?>
