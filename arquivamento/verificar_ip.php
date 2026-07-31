<?php
/**
 * Descoberta do IP do selador.
 *
 * A versão anterior chamava shell_exec('ping ...') e devolvia a saída bruta.
 * Aqui a resolução é feita pelo próprio PHP, sem shell.
 */
require_once __DIR__ . '/bootstrap.php';
arq_exige_login();
header('Content-Type: application/json; charset=utf-8');

$host = 'selador.local';
$ip = gethostbyname($host);

if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    echo json_encode([
        'sucesso' => "Ip do selador localizado: $ip, clique em salvar para atualizar o endereco de comunicacao",
        'ip'      => $ip,
    ]);
} else {
    echo json_encode(['erro' => 'Nao foi possivel resolver o endereco do selador (' . $host . ').']);
}
