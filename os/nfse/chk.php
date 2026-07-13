<?php
header('Content-Type: text/plain; charset=utf-8');
echo "DIR = " . __DIR__ . "\n\n";

$cands = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
$loaded = null;
foreach ($cands as $c) {
    $r = realpath($c);
    echo (is_file($c) ? '[EXISTE] ' : '[  -   ] ') . $c . ($r ? "  ->  $r" : '') . "\n";
    if ($loaded === null && is_file($c)) { $loaded = $c; }
}
echo "\n";
if (!$loaded) { echo "NENHUM vendor/autoload.php encontrado.\n"; exit; }

require_once $loaded;
echo "AUTOLOAD ATIVO: " . realpath($loaded) . "\n\n";

$base = dirname($loaded) . '/nfse-nacional/nfse-php/src';
echo "pasta src esperada: $base\n";
foreach (['Nfse.php', 'Http/NfseContext.php', 'Dto/Nfse/DpsData.php'] as $f) {
    $p = $base . '/' . $f;
    echo str_pad($f, 26) . " : " . (is_file($p) ? (filesize($p) . ' bytes') : 'FALTANDO <<<') . "\n";
}
echo "\n";
foreach (['\\Nfse\\Nfse', '\\Nfse\\Http\\NfseContext', '\\Nfse\\Dto\\Nfse\\DpsData'] as $cls) {
    $ok = class_exists($cls);
    $fn = '';
    if ($ok) { try { $fn = ' @ ' . (new ReflectionClass($cls))->getFileName(); } catch (Throwable $e) {} }
    echo str_pad($cls, 26) . " : " . ($ok ? 'OK' : 'NAO CARREGA <<<') . $fn . "\n";
}
