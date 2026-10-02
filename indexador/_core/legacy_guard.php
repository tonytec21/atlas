<?php
/**
 * Proteção dos endpoints antigos (mantidos por compatibilidade):
 * exige sessão ativa e, para exclusões, perfil de administrador.
 */
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
if (empty($_SESSION['username'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}
if (!empty($IX_LEGACY_ADMIN) && ($_SESSION['nivel_de_acesso'] ?? '') !== 'administrador') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Apenas administradores podem excluir registros.']);
    exit;
}
// libera a sessão: os scripts antigos chamam session_start() novamente
session_write_close();
