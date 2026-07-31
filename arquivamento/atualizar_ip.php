<?php
/** Atualiza o endereço base do selador. */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_exige_post();

$ip = trim((string) (isset($_POST['ip']) ? $_POST['ip'] : ''));
$ip = preg_replace('~^https?://~i', '', $ip);
$ip = rtrim($ip, '/');

if (!filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('/^[a-z0-9.\-]+$/i', $ip)) {
    arq_erro('Endereco invalido.', 422);
}

$pdo = arq_db();
if (!$pdo) { arq_erro('Banco de dados indisponivel.', 503); }

try {
    $st = $pdo->prepare('UPDATE conexao_selador SET url_base = :u WHERE id = 1');
    $st->execute([':u' => 'https://' . $ip]);
    arq_auditar('config', 'selador', ['url_base' => 'https://' . $ip]);
    arq_ok(['mensagem' => 'Endereco do selador atualizado.']);
} catch (PDOException $e) {
    error_log('[arquivamento] atualizar_ip: ' . $e->getMessage());
    arq_erro('Erro ao atualizar o endereco.', 500);
}
