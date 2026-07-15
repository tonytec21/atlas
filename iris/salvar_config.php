<?php
error_reporting(0); @ini_set('display_errors', '0');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_iris.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!iris_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    iris_require_admin();
    $campos = [];
    $novaChave = $_POST['api_key'] ?? '';
    if ($novaChave !== '' && strpos($novaChave, '•') === false) { // ignora o placeholder mascarado
        $campos['api_key_enc'] = iris_enc(trim($novaChave));
    }
    if (isset($_POST['prompt_extra'])) $campos['prompt_extra'] = trim($_POST['prompt_extra']);
    if ($campos) iris_config_set($campos);
    echo json_encode(['status' => 'success', 'message' => 'Configurações salvas.']);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
