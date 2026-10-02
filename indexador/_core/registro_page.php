<?php
/**
 * Tela padrão de um indexador (Nascimento, Casamento ou Óbito).
 * Uso: define('IX_TIPO','nascimento'); require __DIR__.'/../_core/registro_page.php';
 */
declare(strict_types=1);

require_once __DIR__ . '/ui.php';
ix_require_page_session();
ix_db();

$T = ix_tipo(IX_TIPO);

/** Links de importação em lote existentes em cada pasta */
$lote = [
    'nascimento' => [['indexador_xlsx.php', 'Planilha (XLSX)'], ['indexador_txt.php', 'Arquivo TXT'], ['indexador_lote.php', 'Somente anexos (PDF)']],
    'casamento'  => [['indexador_xlsx.php', 'Planilha (XLSX)']],
    'obito'      => [['indexador_xlsx_obito.php', 'Planilha (XLSX)'], ['indexador_txt_obito.php', 'Arquivo TXT']],
][$T['key']];
$lote = array_values(array_filter($lote, fn($l) => is_file(IX_ROOT . '/' . $T['key'] . '/' . $l[0])));

$cfg = [
    'tipo'      => $T['key'],
    'label'     => $T['label'],
    'icon'      => $T['icon'],
    'api'       => 'api.php',
    'cns'       => ix_cns(),
    'isAdmin'   => ix_is_admin(),
    'user'      => ix_user(),
    'tipoLivro' => $T['tipo_livro'],
    'sections'  => $T['sections'],
    'fields'    => $T['fields'],
    'columns'   => $T['columns'],
    'filters'   => array_map(fn($f) => array_diff_key($f, ['cols' => 1, 'col' => 1]), $T['filters']),
];

ix_page_start(['title' => $T['title'], 'accent' => $T['accent']]);
?>
    <?php ix_tabs($T['key']); ?>

    <header class="ix-head">
        <div>
            <?php ix_crumb('../', $T['label']); ?>
            <div class="ix-title">
                <span class="ix-title-mark"><?= ix_icon($T['icon']) ?></span>
                <h1><?= ix_e($T['title']) ?></h1>
            </div>
            <div class="ix-stats" id="ix-stats"><span class="ix-skel"></span><span class="ix-skel"></span></div>
        </div>
        <div class="ix-head-actions">
            <button type="button" class="ix-btn" id="ix-audit" title="Verifica os registros contra as regras e o XSD da CRC">
                <?= ix_icon('shield') ?><span>Pendências CRC</span>
            </button>
            <?php if ($lote): ?>
            <details class="ix-menu">
                <summary class="ix-btn"><?= ix_icon('layers') ?><span>Importar em lote</span><?= ix_icon('down') ?></summary>
                <div class="ix-menu-list" role="menu">
                    <?php foreach ($lote as [$href, $label]): ?>
                        <a role="menuitem" href="<?= ix_e($href) ?>"><?= ix_icon('upload') ?><?= ix_e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php endif; ?>
            <a class="ix-btn" href="../carga_crc/index.php?tipo=<?= ix_e($T['key']) ?>"><?= ix_icon('download') ?><span>Exportar carga</span></a>
            <button type="button" class="ix-btn ix-btn-type" id="ix-new" title="Novo registro (Alt+N)">
                <?= ix_icon('plus') ?><span>Novo registro</span>
            </button>
        </div>
    </header>

    <section class="ix-panel ix-filters" id="ix-filters" aria-label="Pesquisa"></section>

    <section class="ix-panel" id="ix-results" aria-label="Resultados">
        <div class="ix-results-head">
            <div class="ix-count" id="ix-count" aria-live="polite">&nbsp;</div>
            <span class="ix-spacer"></span>
        </div>
        <div class="ix-table-wrap">
            <table class="ix-table">
                <thead id="ix-thead"></thead>
                <tbody id="ix-tbody"></tbody>
            </table>
        </div>
        <div class="ix-cards" id="ix-cards"></div>
        <div class="ix-pager" id="ix-pager"></div>
    </section>
<?php
ix_page_end([
    'shortcuts' => true,
    'scripts'   => '<script>window.IX_CFG = ' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
                 . '<script src="../assets/js/registro.js?v=' . INDEXADOR_VERSION . '"></script>',
]);
