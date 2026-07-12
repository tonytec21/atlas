<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método inválido.');
    if (!notas_csrf_check($_POST['csrf'] ?? '')) throw new RuntimeException('Sessão expirada.');
    $u = $_SESSION['username'];
    $de = trim($_POST['de'] ?? '');
    $para = trim(strip_tags($_POST['para'] ?? ''));
    if ($de === '' || $para === '') throw new RuntimeException('Nomes inválidos.');
    if (mb_strlen($para) > 40) $para = mb_substr($para, 0, 40);
    $org = notas_org_get($u);
    $idx = array_search($de, $org['cats'], true);
    if ($idx === false) throw new RuntimeException('Categoria não encontrada.');
    foreach ($org['cats'] as $c) if (mb_strtolower($c) === mb_strtolower($para) && $c !== $de) throw new RuntimeException('Já existe categoria com esse nome.');
    $org['cats'][$idx] = $para;
    foreach ($org['notes'] as $id => &$m) if (($m['cat'] ?? '') === $de) $m['cat'] = $para;
    notas_org_save($u, $org);
    echo json_encode(['success' => true, 'cats' => $org['cats']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
