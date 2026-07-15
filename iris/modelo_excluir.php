<?php
error_reporting(0); @ini_set('display_errors', '0');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_iris.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!iris_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    iris_require_admin();
    iris_modelo_del((int)($_POST['id'] ?? 0));
    echo json_encode(['status' => 'success', 'message' => 'Modelo excluído.']);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
