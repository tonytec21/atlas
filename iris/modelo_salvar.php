<?php
error_reporting(0); @ini_set('display_errors', '0');
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_iris.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!iris_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    iris_require_admin();
    $id = (int)($_POST['id'] ?? 0);
    $rotulo = $_POST['rotulo'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    if ($id > 0) {
        iris_modelo_update($id, $rotulo, $descricao);
        echo json_encode(['status' => 'success', 'message' => 'Modelo atualizado.']);
    } else {
        $novo = iris_modelo_add($_POST['identificador'] ?? '', $rotulo, $descricao);
        echo json_encode(['status' => 'success', 'message' => 'Modelo cadastrado.', 'id' => $novo]);
    }
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
