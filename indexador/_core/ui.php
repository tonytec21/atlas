<?php
/**
 * Estrutura visual comum das telas do Indexador.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function ix_icon(string $name, string $extra = ''): string
{
    static $icons = null;
    if ($icons === null) $icons = require __DIR__ . '/icons.php';
    $p = $icons[$name] ?? $icons['info'];
    return '<svg class="ix-i ' . $extra . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/** Inclui arquivos do Atlas (menu/rodapé) com acesso às variáveis globais ($conn etc.). */
function ix_include_atlas(string $file): void
{
    $path = IX_ROOT . '/../' . $file;
    if (!is_file($path)) return;
    try { ix_db(); } catch (Throwable $e) {}
    extract($GLOBALS, EXTR_SKIP);
    include $path;
}

function ix_icons_json(): string
{
    return json_encode(require __DIR__ . '/icons.php', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
}

/**
 * Abre a página.
 * $o: title, base ('../' nas subpastas, '' na raiz do indexador), atlas ('../../' ou '../'),
 *     accent (nasc|casa|obito|carga), current (nascimento|casamento|obito|carga|validar|inicio), head (HTML extra)
 */
function ix_page_start(array $o): void
{
    $base = $o['base'] ?? '../';
    $atlas = $o['atlas'] ?? '../../';
    $title = $o['title'] ?? 'Indexador';
    $v = INDEXADOR_VERSION;
    ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas · <?= ix_e($title) ?></title>
    <link rel="stylesheet" href="<?= $atlas ?>style/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $atlas ?>style/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?= $atlas ?>style/css/style.css">
    <link rel="icon" href="<?= $atlas ?>style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="<?= $base ?>assets/css/indexador.css?v=<?= $v ?>">
    <?= $o['head'] ?? '' ?>
</head>
<body class="light-mode ix-body">
<?php ix_include_atlas('menu.php'); ?>
<div id="main" class="main-content">
<div class="ix ix-t-<?= ix_e($o['accent'] ?? 'carga') ?>" id="ix-app">
<div class="ix-page">
    <?php
}

/** Navegação entre as ferramentas (igual em todas as telas). */
function ix_tabs(string $current, string $base = '../'): void
{
    $items = [
        'nascimento' => ['Nascimento', 'nascimento/index.php', 'ix-t-nasc'],
        'casamento'  => ['Casamento', 'casamento/index.php', 'ix-t-casa'],
        'obito'      => ['Óbito', 'obito/index.php', 'ix-t-obito'],
    ];
    echo '<nav class="ix-tabs" aria-label="Ferramentas do indexador">';
    foreach ($items as $k => [$label, $href, $cls]) {
        $cur = $k === $current ? ' aria-current="page"' : '';
        echo '<a class="ix-tab ' . $cls . '" href="' . $base . $href . '"' . $cur . '><span class="ix-dot"></span>' . $label . '</a>';
    }
    echo '<span class="ix-tab-sep" aria-hidden="true"></span>';
    $cur = $current === 'carga' ? ' aria-current="page"' : '';
    echo '<a class="ix-tab" href="' . $base . 'carga_crc/index.php"' . $cur . '>' . ix_icon('download') . 'Carga CRC</a>';
    $cur = $current === 'validar' ? ' aria-current="page"' : '';
    echo '<a class="ix-tab" href="' . $base . 'validar_xml/index.php"' . $cur . '>' . ix_icon('shield') . 'Validar XML</a>';
    echo '</nav>';
}

function ix_crumb(string $base, string $label): void
{
    echo '<div class="ix-crumb"><a href="' . $base . 'index.php">' . ix_icon('home') . '</a>'
        . ix_icon('right') . '<a href="' . $base . 'index.php">Indexador</a>' . ix_icon('right') . '<span>' . ix_e($label) . '</span></div>';
}

/** Fecha a página e carrega os scripts. */
function ix_page_end(array $o = []): void
{
    $base = $o['base'] ?? '../';
    $atlas = $o['atlas'] ?? '../../';
    $v = INDEXADOR_VERSION;
    ?>
    <footer class="ix-foot">
        <span>Indexador v<?= $v ?></span>
        <span class="ix-spacer"></span>
        <?php if (!empty($o['shortcuts'])): ?>
        <span><kbd>Alt</kbd> + <kbd>N</kbd> novo registro &nbsp; <kbd>Ctrl</kbd> + <kbd>S</kbd> salvar &nbsp; <kbd>/</kbd> buscar &nbsp; <kbd>Esc</kbd> fechar</span>
        <?php endif; ?>
    </footer>
</div><!-- .ix-page -->
</div><!-- .ix -->
</div><!-- #main -->
<script src="<?= $atlas ?>script/jquery-3.6.0.min.js"></script>
<script src="<?= $atlas ?>script/bootstrap.bundle.min.js"></script>
<script>window.IX_ICONS = <?= ix_icons_json() ?>;</script>
<script src="<?= $base ?>assets/js/indexador.js?v=<?= $v ?>"></script>
<?= $o['scripts'] ?? '' ?>
<?php ix_include_atlas('rodape.php'); ?>
</body>
</html>
    <?php
}
