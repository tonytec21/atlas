<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
$p = asg_logo_path();
if (!$p) { http_response_code(404); exit(); }
$ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'image/jpeg'));
header('Cache-Control: no-cache');
readfile($p);
