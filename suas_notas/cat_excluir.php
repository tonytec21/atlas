<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!notas_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $nome = trim($_POST['nome'] ?? '');
    if ($nome === '') throw new RuntimeException('Categoria inválida.');
    $org = notas_org_get($u);
    $org['cats'] = array_values(array_filter($org['cats'], function ($c) use ($nome) { return $c !== $nome; }));
    foreach ($org['notes'] as $id => &$m) if (($m['cat'] ?? '') === $nome) $m['cat'] = '';
    notas_org_save($u, $org);
    echo json_encode(['success' => true, 'cats' => $org['cats']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
