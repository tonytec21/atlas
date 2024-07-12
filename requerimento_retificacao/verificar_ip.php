<?php
$output = shell_exec('ping selador.local -4');
$test = explode("[", $output);
$test2 = explode("]", $test[1]);
$ip = $test2[0];

if (isset($ip) && !empty($ip)) {
    echo json_encode(['sucesso' => "Ip do selador localizado: $ip, clique em salvar para atualizar o endereço de comunicação", 'ip' => $ip]);
} else {
    echo json_encode(['erro' => "Não foi possível conectar ao selador!"]);
}
?>
