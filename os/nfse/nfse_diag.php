<?php
/**
 * ATLAS O.S. — Diagnóstico do SDK NFS-e / autoload
 * Coloque em os/nfse/ e acesse pelo navegador: .../os/nfse/nfse_diag.php
 * APAGUE o arquivo depois de usar.
 */
header('Content-Type: text/plain; charset=utf-8');

include(__DIR__ . '/../session_check.php');
if (function_exists('checkSession')) { checkSession(); }
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

function linha($k, $v) { printf("%-34s %s\n", $k . ':', $v); }
function simnao($b) { return $b ? 'SIM' : 'NAO'; }

echo "==== DIAGNOSTICO NFS-e (ATLAS O.S.) ====\n\n";
linha('PHP_VERSION', PHP_VERSION);
linha('PHP_OS', PHP_OS);
linha('__DIR__', __DIR__);
linha('extensao openssl', simnao(extension_loaded('openssl')));
linha('extensao curl', simnao(extension_loaded('curl')));
linha('extensao dom', simnao(extension_loaded('dom')));
echo "\n";

/* 1) Qual autoload.php seria usado */
echo "---- autoload candidatos ----\n";
$cands = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
$usado = null;
foreach ($cands as $c) {
    $ex = is_file($c);
    linha($ex ? '[USA] ' . $c : '      ' . $c, $ex ? 'existe' : 'nao existe');
    if ($ex && $usado === null) { $usado = $c; }
}
echo "\n";
if ($usado === null) {
    echo "!! NENHUM vendor/autoload.php encontrado — rode composer install em os/nfse.\n";
    exit;
}
require_once $usado;
linha('autoload carregado', $usado);
echo "\n";

/* 2) Arquivos-chave do SDK: existencia + tamanho */
echo "---- arquivos do SDK (existencia / bytes) ----\n";
$base = dirname($usado) . '/nfse-nacional/nfse-php/src';
$alvos = [
    'Nfse.php',
    'Http/NfseContext.php',
    'Dto/Nfse/DpsData.php',
    'Signer/Certificate.php',
];
foreach ($alvos as $rel) {
    $p = $base . '/' . $rel;
    if (is_file($p)) {
        $sz = filesize($p);
        linha($rel, $sz . ' bytes' . ($sz === 0 ? '  <<< ZERO BYTES!' : ''));
    } else {
        linha($rel, 'FALTANDO <<<');
    }
}
echo "\n";

/* 3) class_exists de cada classe usada na emissao */
echo "---- class_exists ----\n";
foreach (['\\Nfse\\Nfse', '\\Nfse\\Http\\NfseContext', '\\Nfse\\Dto\\Nfse\\DpsData', '\\Nfse\\Signer\\Certificate'] as $cls) {
    $ok = class_exists($cls);
    $onde = '';
    if ($ok) {
        try { $r = new ReflectionClass($cls); $onde = ' @ ' . $r->getFileName(); } catch (Throwable $e) {}
    }
    linha($cls, simnao($ok) . $onde);
}
echo "\n";

/* 4) Varredura de arquivos 0-byte sob o pacote (pega sync truncado) */
echo "---- arquivos 0-byte no pacote SDK ----\n";
$dirSdk = dirname($usado) . '/nfse-nacional';
$zero = [];
if (is_dir($dirSdk)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirSdk, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getSize() === 0 && substr($f->getFilename(), -4) === '.php') {
            $zero[] = $f->getPathname();
        }
    }
}
echo $zero ? implode("\n", $zero) . "\n" : "(nenhum .php com 0 byte)\n";
echo "\n";

/* 5) OPcache */
echo "---- OPcache ----\n";
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    linha('opcache habilitado', simnao(is_array($st) && !empty($st['opcache_enabled'])));
    if (is_array($st) && isset($st['opcache_statistics'])) {
        linha('scripts em cache', $st['opcache_statistics']['num_cached_scripts'] ?? '?');
    }
    $ini = ini_get('opcache.validate_timestamps');
    linha('validate_timestamps', $ini === false ? '(n/d)' : $ini);
    $rev = ini_get('opcache.revalidate_freq');
    linha('revalidate_freq', $rev === false ? '(n/d)' : $rev);
    echo "\n>> Se 'validate_timestamps=0', o OPcache NAO recarrega arquivos alterados sem reiniciar o Apache.\n";
} else {
    linha('OPcache', 'nao disponivel (ok)');
}
echo "\n==== FIM ====\n";
