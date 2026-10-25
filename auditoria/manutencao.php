<?php
/**
 * Manutenção da auditoria central (selagem, gatilhos e prazo de guarda).
 *
 * Opcional: o Painel do Administrador já faz isto ao ser aberto. Para rodar
 * também de madrugada, agende no Windows (Agendador de Tarefas):
 *     C:\xampp\php\php.exe C:\xampp\htdocs\atlas\auditoria\manutencao.php
 * Só roda pela linha de comando.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
date_default_timezone_set('America/Fortaleza');
require __DIR__ . '/lib.php';
aud_manutencao(true);
$v = [];
foreach (array_keys(aud_cadeias()) as $c) {
    $r = aud_verificar($c);
    $v[] = $c . '=' . ($r['ok'] ? 'ok' : 'QUEBRA: ' . $r['motivo']);
}
echo date('d/m/Y H:i:s') . ' manutenção concluída · ' . implode(' · ', $v) . PHP_EOL;
