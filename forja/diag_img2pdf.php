<?php
/**
 * Atlas Forja — Diagnóstico de "Imagens → PDF".
 * Abra no navegador:  http://localhost/atlas/forja/diag_img2pdf.php
 * Mostra limites do PHP, GD, TCPDF/FPDI, permissões e faz um teste real de
 * conversão (gera uma imagem de 2000×1400 e monta um PDF), medindo o pico
 * de memória. Também exibe as últimas linhas de tmp/forja_erros.log.
 */
error_reporting(E_ALL);
@ini_set('display_errors', '1');
@set_time_limit(0);
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_forja.php';

header('Content-Type: text/html; charset=utf-8');
$ok  = function ($b) { return $b ? '<b style="color:#137333">OK</b>' : '<b style="color:#c5221f">FALHOU</b>'; };
$esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
?>
<!doctype html>
<html lang="pt-br"><head><meta charset="utf-8"><title>Forja — Diagnóstico Imagens→PDF</title>
<style>
body{font:14px/1.55 Segoe UI,Arial,sans-serif;margin:24px;color:#202124;background:#f7f8fa}
h1{font-size:20px;margin:0 0 4px}h2{font-size:15px;margin:22px 0 8px;color:#1a73e8}
table{border-collapse:collapse;background:#fff;width:100%;max-width:900px;box-shadow:0 1px 3px rgba(0,0,0,.12)}
td{padding:7px 10px;border-bottom:1px solid #eceff1;vertical-align:top}
td:first-child{width:270px;color:#5f6368}
pre{background:#fff;padding:12px;border:1px solid #e0e0e0;max-width:900px;overflow:auto;font-size:12px}
small{color:#5f6368}
</style></head><body>
<h1>Atlas Forja — diagnóstico de Imagens → PDF</h1>
<small>Rode este teste com o mesmo usuário/navegador em que ocorre o erro 500.</small>

<h2>1. Limites do PHP</h2>
<table>
<tr><td>Versão do PHP</td><td><?= $esc(PHP_VERSION) ?> (<?= PHP_INT_SIZE * 8 ?> bits)</td></tr>
<tr><td>memory_limit</td><td><?= $esc(ini_get('memory_limit')) ?> — <?= $ok(forja_ini_bytes(ini_get('memory_limit')) === -1 || forja_ini_bytes(ini_get('memory_limit')) >= 536870912) ?> <small>(recomendado ≥ 1024M)</small></td></tr>
<tr><td>max_execution_time</td><td><?= $esc(ini_get('max_execution_time')) ?> s</td></tr>
<tr><td>upload_max_filesize</td><td><?= $esc(ini_get('upload_max_filesize')) ?></td></tr>
<tr><td>post_max_size</td><td><?= $esc(ini_get('post_max_size')) ?></td></tr>
<tr><td>max_file_uploads</td><td><?= $esc(ini_get('max_file_uploads')) ?> <small>(limite de imagens por envio)</small></td></tr>
<tr><td>php.ini em uso</td><td><?= $esc(php_ini_loaded_file()) ?></td></tr>
<tr><td>error_log</td><td><?= $esc(ini_get('error_log') ?: '(padrão do Apache: xampp\apache\logs\error.log)') ?></td></tr>
</table>

<h2>1b. Limites de envio e disco</h2>
<?php $L = forja_limites_php(); ?>
<table>
<tr><td>Limite configurado no módulo</td><td><?= $esc(forja_human($L['config'])) ?></td></tr>
<tr><td>Limite em vigor (menor dos três)</td><td><b><?= $esc(forja_human($L['efetivo'])) ?></b> — <?= $ok($L['efetivo'] >= $L['config']) ?><?php if ($L['efetivo'] < $L['config']): ?> <small>o php.ini está segurando: ajuste upload_max_filesize e post_max_size e reinicie o Apache</small><?php endif; ?></td></tr>
<tr><td>PHP 64 bits</td><td><?= $ok($L['x64']) ?> <small><?= $L['x64'] ? 'suporta arquivos acima de 2 GB' : 'PHP de 32 bits não trata arquivos acima de 2 GB' ?></small></td></tr>
<tr><td>Espaço livre em disco</td><td><?= $esc(forja_human((int)forja_disco_livre())) ?> <small>(a compressão chega a usar 2,5× o tamanho do arquivo)</small></td></tr>
<tr><td>Em uso por tmp/saida</td><td><?php $u = forja_uso_disco(); echo $esc(forja_human($u['tmp'])) . ' + ' . $esc(forja_human($u['saida'])); ?></td></tr>
</table>

<h2>2. Extensões e bibliotecas</h2>
<table>
<tr><td>GD</td><td><?= $ok(extension_loaded('gd')) ?> <?php if (function_exists('gd_info')) { $g = gd_info(); echo $esc('— ' . ($g['GD Version'] ?? '') . ' | JPEG: ' . (!empty($g['JPEG Support']) ? 'sim' : 'não') . ' | PNG: ' . (!empty($g['PNG Support']) ? 'sim' : 'não') . ' | WEBP: ' . (!empty($g['WebP Support']) ? 'sim' : 'não')); } ?></td></tr>
<tr><td>fileinfo (finfo)</td><td><?= $ok(class_exists('finfo')) ?></td></tr>
<tr><td>exif</td><td><?= $ok(function_exists('exif_read_data')) ?> <small>(corrige fotos deitadas)</small></td></tr>
<tr><td>zip</td><td><?= $ok(class_exists('ZipArchive')) ?></td></tr>
<?php
$libErro = '';
try { forja_load_libs(); } catch (Throwable $e) { $libErro = $e->getMessage(); }
?>
<tr><td>TCPDF</td><td><?= $ok(class_exists('TCPDF')) ?> <?= $esc($libErro) ?></td></tr>
<tr><td>FPDI</td><td><?= $ok(class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi')) ?></td></tr>
<tr><td>K_PATH_CACHE (TCPDF)</td><td><?php
$kc = defined('K_PATH_CACHE') ? K_PATH_CACHE : '';
echo $kc ? $esc($kc) . ' — ' . $ok(is_dir($kc) && is_writable($kc)) : '<small>não definido</small>';
?></td></tr>
</table>

<h2>3. Pastas</h2>
<table>
<tr><td>forja/tmp</td><td><?= $esc(forja_dir_tmp()) ?> — <?= $ok(is_writable(forja_dir_tmp())) ?></td></tr>
<tr><td>forja/saida</td><td><?= $esc(forja_dir_out()) ?> — <?= $ok(is_writable(forja_dir_out())) ?></td></tr>
<tr><td>upload_tmp_dir</td><td><?= $esc(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()) ?> — <?= $ok(is_writable(ini_get('upload_tmp_dir') ?: sys_get_temp_dir())) ?></td></tr>
</table>

<h2>4. Teste real de conversão</h2>
<?php
$teste = forja_dir_tmp() . '/diag_teste.png';
$msg = '';
try {
    if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('GD indisponível — não é possível testar.');
    $im = imagecreatetruecolor(2000, 1400);
    imagefilledrectangle($im, 0, 0, 2000, 1400, imagecolorallocate($im, 245, 245, 245));
    imagefilledrectangle($im, 100, 100, 1900, 300, imagecolorallocate($im, 26, 115, 232));
    imagepng($im, $teste, 6);
    imagedestroy($im);

    $t0 = microtime(true);
    $r  = forja_imagens_para_pdf([$teste], 'a4');
    $dt = round((microtime(true) - $t0) * 1000);

    $msg = '<p>' . $ok(true) . ' PDF gerado: <code>' . $esc(basename($r['path'])) . '</code> — '
         . $esc(forja_human(filesize($r['path']))) . ', ' . (int)$r['paginas'] . ' pág., '
         . $dt . ' ms, pico de memória <b>' . $esc($r['pico_mb']) . ' MB</b>.</p>';
    @unlink($r['path']);
} catch (Throwable $e) {
    $msg = '<p>' . $ok(false) . ' ' . $esc($e->getMessage()) . '<br><small>' . $esc($e->getFile() . ':' . $e->getLine()) . '</small></p>';
}
@unlink($teste);
echo $msg;
?>
<p><small>Estimativa de memória por imagem: <b>largura × altura × 4 bytes × ~2,3</b>. Uma foto de 12 MP consome cerca de 110 MB durante o preparo.</small></p>

<h2>5. Últimos erros registrados (tmp/forja_erros.log)</h2>
<pre><?php
$logf = forja_dir_tmp() . '/forja_erros.log';
if (is_file($logf)) {
    $linhas = array_slice(array_filter(explode("\n", str_replace("\r", '', file_get_contents($logf)))), -40);
    echo $esc(implode("\n", $linhas));
} else { echo '(sem registros)'; }
?></pre>
</body></html>
