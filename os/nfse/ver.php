<?php
/** Verificador de deploy do NFS-e. Acesse: .../os/nfse/ver.php  — APAGUE depois. */
header('Content-Type: text/plain; charset=utf-8');

$lib = __DIR__ . '/nfse_lib.php';
echo "nfse_lib.php ativo : " . (realpath($lib) ?: '(NAO ENCONTRADO)') . "\n";

$src = is_file($lib) ? (string) file_get_contents($lib) : '';
foreach (explode("\n", $src) as $l) {
    if (strpos($l, 'ATLAS-NFSE-BUILD') !== false) { echo "build marker      : " . trim($l) . "\n"; break; }
}
echo "tem fix autoload   : " . (strpos($src, "function nfse_cliente") !== false ? 'SIM' : 'NAO  <<< arquivo antigo!') . "\n\n";

require_once $lib;
echo "nfse_autoload()    : " . (nfse_autoload() ? 'true' : 'false') . "\n";
echo "\\Nfse\\Nfse         : " . (class_exists('\\Nfse\\Nfse') ? 'OK' : 'NAO CARREGA <<<') . "\n";
echo "\\Nfse\\Http\\NfseContext : " . (class_exists('\\Nfse\\Http\\NfseContext') ? 'OK' : 'NAO') . "\n";
if (class_exists('\\Nfse\\Nfse')) {
    echo "resolvido em       : " . (new ReflectionClass('\\Nfse\\Nfse'))->getFileName() . "\n";
    echo "\n>> Autoload OK. Se a tela ainda falhar, o navegador/servidor esta usando codigo antigo.\n";
} else {
    echo "\n>> Ainda nao resolve — me mande esta saida inteira.\n";
}
