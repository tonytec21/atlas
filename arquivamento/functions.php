<?php
/** Compatibilidade: helpers antigos, agora com PDO. */
require_once __DIR__ . '/bootstrap.php';

if (!function_exists('get_user_full_name')) {
    function get_user_full_name($conn, $username) {
        $pdo = arq_db();
        if (!$pdo) { return null; }
        try {
            $st = $pdo->prepare('SELECT nome_completo FROM funcionarios WHERE usuario = :u LIMIT 1');
            $st->execute([':u' => $username]);
            $r = $st->fetch();
            return $r ? $r['nome_completo'] : null;
        } catch (PDOException $e) {
            error_log('[arquivamento] get_user_full_name: ' . $e->getMessage());
            return null;
        }
    }
}
