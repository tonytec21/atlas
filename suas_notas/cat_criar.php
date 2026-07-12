<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!notas_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $nome = trim(strip_tags($_POST['nome'] ?? ''));
    if ($nome === '') throw new RuntimeException('Informe o nome da categoria.');
    if (mb_strlen($nome) > 40) $nome = mb_substr($nome, 0, 40);
    $org = notas_org_get($u);
    foreach ($org['cats'] as $c) if (mb_strtolower($c) === mb_strtolower($nome)) throw new RuntimeException('Categoria já existe.');
    $org['cats'][] = $nome;
    notas_org_save($u, $org);
    echo json_encode(['success' => true, 'cats' => $org['cats']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
