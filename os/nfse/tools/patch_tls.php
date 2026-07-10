<?php
/**
 * ATLAS-NFSE — Correção do HTTP 403 do IIS da SEFIN Nacional.
 *
 * Em alguns servidores Windows, o front-end IIS da SEFIN devolve 403 quando a
 * negociação TLS sobe para 1.3. O SDK já força HTTP/1.1, mas não limita a
 * versão do TLS. Este script adiciona CURLOPT_SSLVERSION => CURL_SSLVERSION_MAX_TLSv1_2
 * ao cliente do SDK.
 *
 * Rode UMA VEZ após cada "composer install/update":
 *     php tools/patch_tls.php
 *
 * É idempotente: rodar de novo não faz nada.
 */

$alvos = [
    __DIR__ . '/../vendor/nfse-nacional/nfse-php/src/Http/Client/SefinClient.php',
    __DIR__ . '/../vendor/nfse-nacional/nfse-php/src/Http/Client/AdnClient.php',
    __DIR__ . '/../vendor/nfse-nacional/nfse-php/src/Http/Client/CncClient.php',
];

$ancora = 'CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,';
$patch  = $ancora . "\n                CURLOPT_SSLVERSION => CURL_SSLVERSION_MAX_TLSv1_2, // ATLAS-NFSE patch";

$aplicados = 0;

foreach ($alvos as $arquivo) {
    if (!is_file($arquivo)) {
        echo "  - ignorado (não existe): {$arquivo}\n";
        continue;
    }

    $conteudo = file_get_contents($arquivo);

    if (str_contains($conteudo, 'ATLAS-NFSE patch')) {
        echo "  = já aplicado: " . basename($arquivo) . "\n";
        continue;
    }
    if (!str_contains($conteudo, $ancora)) {
        echo "  ! âncora não encontrada em " . basename($arquivo) . " (o SDK mudou?)\n";
        continue;
    }

    copy($arquivo, $arquivo . '.bak');
    file_put_contents($arquivo, str_replace($ancora, $patch, $conteudo));
    echo "  + patch aplicado: " . basename($arquivo) . "\n";
    $aplicados++;
}

echo "\n{$aplicados} arquivo(s) alterado(s). Reinicie o Apache (OPcache).\n";
