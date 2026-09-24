<?php
/**
 * signum/diagnostico_modulos.php — diagnóstico da integração do TCloud Assinador com os módulos
 * (ofícios, notas devolutivas, O.S.). Mostra versões, arquivos e, com "Testar página", executa a página
 * de assinatura do módulo exibindo o erro PHP real (em vez do "HTTP ERROR 500").
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['username'])) { header('Location: ../login.php'); exit; }

$raiz = dirname(__DIR__);
$MODULOS = [
    'oficios'         => ['pagina' => 'assinar-oficio.php', 'titulo' => 'Ofícios',             'exemplo' => 'numero=18/2026'],
    'nota_devolutiva' => ['pagina' => 'assinar-nota.php',   'titulo' => 'Notas devolutivas',   'exemplo' => 'numero=3/2026'],
    'os'              => ['pagina' => 'assinar-os.php',     'titulo' => 'O.S. / Recibos',      'exemplo' => 'tipo=os&id=5505'],
];
$m = (string)($_GET['m'] ?? '');

/* ---------- executa a página do módulo mostrando o erro real ---------- */
if ($m !== '' && isset($MODULOS[$m])) {
    @ini_set('display_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            echo '<div style="position:fixed;left:12px;right:12px;bottom:12px;z-index:99999;background:#7f1d1d;color:#fff;'
               . 'font:14px/1.5 Consolas,monospace;padding:14px 16px;border-radius:10px;white-space:pre-wrap">'
               . '<b>ERRO FATAL (é isto que causa o HTTP ERROR 500):</b>' . "\n"
               . htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8') . "\n"
               . 'Arquivo: ' . htmlspecialchars($e['file'], ENT_QUOTES, 'UTF-8') . ' — linha ' . (int)$e['line'] . '</div>';
        }
    });
    $q = (string)($_GET['q'] ?? $MODULOS[$m]['exemplo']);
    parse_str($q, $params);
    $_GET = $params;
    $dir = $raiz . '/' . $m;
    chdir($dir);
    $_SERVER['SCRIPT_NAME'] = dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/' . $m . '/' . $MODULOS[$m]['pagina'];
    require $dir . '/' . $MODULOS[$m]['pagina'];
    exit;
}

/* ---------- resumo ---------- */
function dg_ok($c) { return $c ? '<b style="color:#16a34a">OK</b>' : '<b style="color:#dc2626">FALTA</b>'; }
function dg_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dg_tem($arq, $trecho) { return is_file($arq) && strpos((string)file_get_contents($arq), $trecho) !== false; }

$cfg = __DIR__ . '/config_assinatura.php';
preg_match("~define\('ASG_VERSAO', '([^']+)'\)~", is_file($cfg) ? file_get_contents($cfg) : '', $mv);
$linhas = [
    ['Versão do PHP', PHP_VERSION, version_compare(PHP_VERSION, '7.2', '>=')],
    ['Atlas Signum (ASG_VERSAO)', $mv[1] ?? '?', isset($mv[1]) && version_compare($mv[1], '1.7.0', '>=')],
    ['signum/inc/tcloud_modulo.php', '', is_file(__DIR__ . '/inc/tcloud_modulo.php')],
    ['signum/js/tcloud_modulo.js', '', is_file(__DIR__ . '/js/tcloud_modulo.js')],
    ['signum/lib/tcloud_local.php com pedidos de módulo', '', dg_tem(__DIR__ . '/lib/tcloud_local.php', 'function asg_tcl_iniciar_modulo')],
    ['oficios/assin_pades.php protegido (sem classe duplicada)', '', dg_tem($raiz . '/oficios/assin_pades.php', "class_exists('AtlasPadesInjector'")],
];
foreach ($MODULOS as $d => $info) {
    $linhas[] = [$d . '/tcloud_iniciar.php', '', is_file($raiz . '/' . $d . '/tcloud_iniciar.php')];
    $linhas[] = [$d . '/tcloud_gravar.php', '', is_file($raiz . '/' . $d . '/tcloud_gravar.php')];
    $linhas[] = [$d . '/' . $info['pagina'] . ' (versão com TCloud)', '', dg_tem($raiz . '/' . $d . '/' . $info['pagina'], 'tcloud_modulo.php')];
}
foreach (['mysqli', 'openssl', 'curl', 'mbstring', 'json'] as $x) $linhas[] = ['Extensão PHP ' . $x, '', extension_loaded($x)];
$opc = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
?><!DOCTYPE html>
<html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagnóstico — TCloud Assinador nos módulos</title>
<style>
body{ font-family:system-ui,Segoe UI,sans-serif; background:#f1f5f9; color:#0f172a; margin:0; padding:18px; }
.box{ max-width:900px; margin:0 auto; background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:18px 20px; }
h1{ font-size:1.25rem; margin:0 0 4px; } p{ color:#475569; }
table{ width:100%; border-collapse:collapse; font-size:.9rem; } td{ padding:7px 6px; border-bottom:1px solid #eef2f7; vertical-align:top; }
td:last-child{ text-align:right; white-space:nowrap; } code{ background:#f1f5f9; padding:1px 5px; border-radius:5px; }
form{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin:8px 0; } input{ flex:1 1 200px; padding:7px 10px; border:1px solid #cbd5e1; border-radius:8px; }
button{ padding:8px 14px; border:0; border-radius:8px; background:#2563eb; color:#fff; font-weight:700; cursor:pointer; }
</style></head><body><div class="box">
<h1>Diagnóstico — TCloud Assinador nos módulos</h1>
<p>Confere se o Atlas Signum 1.7.0 e os arquivos da integração estão no lugar. Para ver o erro real de uma página que dá <b>HTTP ERROR 500</b>, use <b>Testar página</b>.</p>
<table>
<?php foreach ($linhas as $l): ?>
<tr><td><?php echo dg_e($l[0]); ?><?php echo $l[1] !== '' ? ' — <code>' . dg_e($l[1]) . '</code>' : ''; ?></td><td><?php echo dg_ok($l[2]); ?></td></tr>
<?php endforeach; ?>
<tr><td>OPcache</td><td><?php echo $opc && !empty($opc['opcache_enabled']) ? '<b style="color:#b45309">ligado</b> — reinicie o Apache depois de copiar arquivos' : 'desligado'; ?></td></tr>
</table>
<h3 style="margin:18px 0 4px;font-size:1rem">Testar página</h3>
<?php foreach ($MODULOS as $d => $info): ?>
<form method="get"><input type="hidden" name="m" value="<?php echo dg_e($d); ?>">
<b style="min-width:150px"><?php echo dg_e($info['titulo']); ?></b>
<input name="q" value="<?php echo dg_e($info['exemplo']); ?>" title="Parâmetros da página (como na barra de endereço)">
<button type="submit">Testar página</button></form>
<?php endforeach; ?>
<p style="font-size:.82rem">Os parâmetros são os mesmos da barra de endereço da página de assinatura (ex.: <code>numero=18/2026</code> ou <code>tipo=recibo_a4&amp;id=5505</code>). Se houver erro fatal, ele aparece numa faixa vermelha no rodapé.</p>
</div></body></html>
