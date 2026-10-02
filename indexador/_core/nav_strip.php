<?php
/**
 * Faixa de navegação padrão para as telas auxiliares (importação em lote,
 * validador de XML, relatório). Uso, logo após o menu do Atlas:
 *   <?php $IX_STRIP = ['tipo' => 'nascimento', 'label' => 'Importar planilha']; include __DIR__ . '/../_core/nav_strip.php'; ?>
 */
require_once __DIR__ . '/ui.php';
$__s = $IX_STRIP ?? [];
$__tipo = $__s['tipo'] ?? '';
$__base = $__s['base'] ?? '../';
$__acc = ['nascimento' => 'nasc', 'casamento' => 'casa', 'obito' => 'obito'][$__tipo] ?? 'carga';
?>
<link rel="stylesheet" href="<?= $__base ?>assets/css/indexador.css?v=<?= INDEXADOR_VERSION ?>">
<div class="ix ix-t-<?= $__acc ?> ix-strip">
    <div class="ix-page" style="padding-bottom:0">
        <?php ix_tabs($__s['current'] ?? $__tipo, $__base); ?>
        <div class="ix-crumb" style="margin-bottom:0">
            <a href="<?= $__base ?>index.php"><?= ix_icon('home') ?></a><?= ix_icon('right') ?>
            <a href="<?= $__base ?>index.php">Indexador</a>
            <?php if ($__tipo): $__T = ix_tipo($__tipo); ?>
                <?= ix_icon('right') ?><a href="<?= $__base . $__tipo ?>/index.php"><?= ix_e($__T['label']) ?></a>
            <?php endif; ?>
            <?php if (!empty($__s['label'])): ?><?= ix_icon('right') ?><span><?= ix_e($__s['label']) ?></span><?php endif; ?>
        </div>
    </div>
</div>
