<?php
/**
 * GuiaOS — inclusão do guia interativo (Atlas)
 * ------------------------------------------------------------------
 * Uso: coloque a linha abaixo logo antes de </body> nas telas do sistema.
 *
 *   Telas do módulo O.S. (atlas/os/…):
 *       <?php include(__DIR__ . '/guia/guia.php'); ?>
 *
 *   Telas fora do módulo, como atlas/liberar_os.php:
 *       <?php include(__DIR__ . '/os/guia/guia.php'); ?>
 *
 * A URL dos arquivos estáticos é calculada a partir do DOCUMENT_ROOT, de modo
 * que o include funciona de qualquer pasta. Se precisar forçar um caminho,
 * defina $guiaBaseUrl antes do include (ex.: $guiaBaseUrl = '/atlas/os/guia';).
 *
 * ABERTURA AUTOMÁTICA: desligada. O guia só abre quando o usuário clica no
 * botão "?" no canto inferior direito da tela. Para religá-la numa tela
 * específica, defina antes do include:
 *
 *       $guiaAutoIniciar = true;
 *       include(__DIR__ . '/guia/guia.php');
 *
 * O ?v= usa a data do arquivo para furar o cache do navegador a cada
 * atualização (são arquivos estáticos; o OPcache não interfere).
 */

$__guiaDir = str_replace('\\', '/', realpath(__DIR__));
$__guiaBase = 'guia/';                       // padrão: telas do próprio módulo O.S.

$__docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
if ($__docRoot) {
    $__docRoot = rtrim(str_replace('\\', '/', $__docRoot), '/');
    if ($__docRoot !== '' && strpos($__guiaDir, $__docRoot) === 0) {
        $__guiaBase = substr($__guiaDir, strlen($__docRoot));
        $__guiaBase = '/' . trim($__guiaBase, '/') . '/';
    }
}
if (!empty($guiaBaseUrl)) {                  // caminho forçado pelo desenvolvedor
    $__guiaBase = rtrim($guiaBaseUrl, '/') . '/';
}

$__guiaCss = $__guiaDir . '/guia-os.css';
$__guiaJs  = $__guiaDir . '/guia-os.js';
$__guiaPas = $__guiaDir . '/guia-os-passos.js';
$__guiaAss = $__guiaDir . '/assinador-autorizar.js';

$__vCss = is_file($__guiaCss) ? filemtime($__guiaCss) : time();
$__vJs  = is_file($__guiaJs)  ? filemtime($__guiaJs)  : time();
$__vPas = is_file($__guiaPas) ? filemtime($__guiaPas) : time();
$__vAss = is_file($__guiaAss) ? filemtime($__guiaAss) : time();
?>
<link rel="stylesheet" href="<?php echo $__guiaBase; ?>guia-os.css?v=<?php echo $__vCss; ?>">
<?php if (!empty($guiaAutoIniciar)): ?>
<script>window.GUIA_OS_AUTO_INICIAR = true;</script>
<?php endif; ?>
<script src="<?php echo $__guiaBase; ?>guia-os.js?v=<?php echo $__vJs; ?>"></script>
<script src="<?php echo $__guiaBase; ?>guia-os-passos.js?v=<?php echo $__vPas; ?>"></script>
<!-- Liberação do Assinador SERPRO sem sair da tela (só age em assinar-os.php) -->
<script src="<?php echo $__guiaBase; ?>assinador-autorizar.js?v=<?php echo $__vAss; ?>"></script>
