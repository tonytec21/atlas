<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!notas_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $itens = json_decode($_POST['itens'] ?? '[]', true);
    if (!is_array($itens)) throw new RuntimeException('Dados inválidos.');
    $org = notas_org_get($u);
    foreach ($itens as $it) {
        $id = notas_safe_id($it['id'] ?? '');
        if ($id === '') continue;
        $cat = isset($it['cat']) ? (string)$it['cat'] : '';
        $ord = isset($it['ord']) ? (int)$it['ord'] : 0;
        // valida categoria (só aceita existentes ou vazio)
        if ($cat !== '' && !in_array($cat, $org['cats'], true)) $cat = '';
        $org['notes'][$id] = ['cat' => $cat, 'ord' => $ord];
    }
    notas_org_save($u, $org);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
