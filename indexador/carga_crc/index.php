<?php
/**
 * Atlas · Indexador — Exportação de carga CRC (Nascimento, Casamento e Óbito).
 */
require_once __DIR__ . '/../_core/ui.php';
require_once __DIR__ . '/../_core/crc.php';
ix_require_page_session();
ix_db();

$tipo = $_GET['tipo'] ?? 'nascimento';
if (!in_array($tipo, ['nascimento', 'casamento', 'obito'], true)) $tipo = 'nascimento';
$T = ix_tipo($tipo);
$ato = ['nascimento' => 'Data de nascimento', 'casamento' => 'Data do casamento', 'obito' => 'Data do óbito'][$tipo];
$nomeLbl = ['nascimento' => 'Nome do registrado', 'casamento' => 'Nome do cônjuge (1º ou 2º)', 'obito' => 'Nome do(a) falecido(a)'][$tipo];
$gerar = ['nascimento' => 'gerar_carga.php', 'casamento' => 'gerar_carga_casamento.php', 'obito' => 'gerar_carga_obito.php'][$tipo];
$meta = crc_meta($tipo);

ix_page_start(['title' => 'Carga CRC — ' . $T['label'], 'accent' => $T['accent']]);
ix_tabs('carga');
?>
    <header class="ix-head">
        <div>
            <?php ix_crumb('../', 'Carga CRC'); ?>
            <div class="ix-title">
                <span class="ix-title-mark"><?= ix_icon('download') ?></span>
                <h1>Exportar carga para a CRC</h1>
            </div>
            <div class="ix-stats"><span>Selecione os registros, valide contra o XSD oficial e gere o arquivo <b style="font-weight:600"><?= ix_e($meta['arquivo']) ?></b></span></div>
        </div>
        <div class="ix-head-actions">
            <a class="ix-btn" href="../<?= ix_e($tipo) ?>/index.php"><?= ix_icon($T['icon']) ?><span>Indexador de <?= ix_e($T['label']) ?></span></a>
        </div>
    </header>

    <nav class="ix-tabs" aria-label="Tipo de carga">
        <?php foreach (['nascimento' => 'ix-t-nasc', 'casamento' => 'ix-t-casa', 'obito' => 'ix-t-obito'] as $k => $cls): ?>
            <a class="ix-tab <?= $cls ?>" href="?tipo=<?= $k ?>"<?= $k === $tipo ? ' aria-current="page"' : '' ?>><span class="ix-dot"></span>Carga de <?= ix_e(mb_strtolower(ix_tipo($k)['label'], 'UTF-8')) ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="ix-panel ix-filters" id="cg-filters">
        <form id="cg-form" autocomplete="off">
            <div class="ix-grid ix-grid-tight">
                <div class="ix-col ix-col-2 ix-field"><label class="ix-label" for="cg-livro">Livro</label><input class="ix-input" id="cg-livro" name="livro" inputmode="numeric" data-mask="digits"></div>
                <div class="ix-col ix-col-2 ix-field"><label class="ix-label" for="cg-ti">Termo inicial</label><input class="ix-input" id="cg-ti" name="termo_de" inputmode="numeric" data-mask="digits"></div>
                <div class="ix-col ix-col-2 ix-field"><label class="ix-label" for="cg-tf">Termo final</label><input class="ix-input" id="cg-tf" name="termo_ate" inputmode="numeric" data-mask="digits"></div>
                <div class="ix-col ix-col-6 ix-field"><label class="ix-label" for="cg-reg">Data do registro</label>
                    <div class="ix-range"><input class="ix-input" id="cg-reg" name="reg_de" placeholder="De DD/MM/AAAA" data-mask="date"><span>até</span><input class="ix-input" name="reg_ate" placeholder="DD/MM/AAAA" data-mask="date" aria-label="Data do registro até"></div></div>
            </div>
            <div class="ix-filters-more"><div class="ix-grid">
                <div class="ix-col ix-col-6 ix-field"><label class="ix-label" for="cg-nome"><?= ix_e($nomeLbl) ?></label><input class="ix-input" id="cg-nome" name="nome"></div>
                <div class="ix-col ix-col-4 ix-field"><label class="ix-label" for="cg-mat">Matrícula</label><input class="ix-input" id="cg-mat" name="matricula" inputmode="numeric"></div>
                <div class="ix-col ix-col-2 ix-field"><label class="ix-label" for="cg-folha">Folha</label><input class="ix-input" id="cg-folha" name="folha" inputmode="numeric" data-mask="digits"></div>
                <div class="ix-col ix-col-6 ix-field"><label class="ix-label" for="cg-cad">Data de cadastro no indexador</label>
                    <div class="ix-range"><input class="ix-input" id="cg-cad" name="cad_de" placeholder="De DD/MM/AAAA" data-mask="date"><span>até</span><input class="ix-input" name="cad_ate" placeholder="DD/MM/AAAA" data-mask="date" aria-label="Data de cadastro até"></div></div>
                <div class="ix-col ix-col-6 ix-field"><label class="ix-label" for="cg-ato"><?= ix_e($ato) ?></label>
                    <div class="ix-range"><input class="ix-input" id="cg-ato" name="ato_de" placeholder="De DD/MM/AAAA" data-mask="date"><span>até</span><input class="ix-input" name="ato_ate" placeholder="DD/MM/AAAA" data-mask="date" aria-label="<?= ix_e($ato) ?> até"></div></div>
            </div></div>
            <div class="ix-filters-bar">
                <button type="submit" class="ix-btn ix-btn-primary"><?= ix_icon('search') ?>Pesquisar</button>
                <button type="button" class="ix-btn ix-btn-ghost" id="cg-more"><?= ix_icon('sliders') ?><span>Mais filtros</span></button>
                <button type="reset" class="ix-btn ix-btn-ghost"><?= ix_icon('x') ?>Limpar</button>
            </div>
        </form>
    </section>

    <section class="ix-panel" id="cg-results">
        <div class="ix-results-head">
            <label style="display:flex;gap:10px;align-items:center;margin:0;cursor:pointer">
                <input type="checkbox" id="cg-all" style="width:18px;height:18px;accent-color:var(--ix-type)">
                <span class="ix-count" id="cg-count">Use os filtros e clique em Pesquisar</span>
            </label>
            <span class="ix-spacer"></span>
            <button type="button" class="ix-btn" id="cg-validate" disabled><?= ix_icon('shield') ?>Validar selecionados</button>
            <button type="button" class="ix-btn ix-btn-type" id="cg-export" disabled><?= ix_icon('download') ?><span id="cg-export-lbl">Gerar XML</span></button>
        </div>
        <div id="cg-summary" class="ix-audit-sum ix-hidden"></div>
        <div class="ix-table-wrap">
            <table class="ix-table">
                <thead><tr>
                    <th style="width:44px"><span class="ix-sr">Selecionar</span></th>
                    <th style="width:84px">Termo</th><th style="width:84px">Livro</th><th style="width:84px">Folha</th>
                    <th><?= $tipo === 'casamento' ? 'Cônjuges' : ($tipo === 'obito' ? 'Falecido(a)' : 'Registrado') ?></th>
                    <th style="width:120px"><?= ['nascimento' => 'Nascimento', 'casamento' => 'Casamento', 'obito' => 'Óbito'][$tipo] ?></th><th style="width:120px">Registro</th>
                    <th style="width:150px">Situação CRC</th>
                </tr></thead>
                <tbody id="cg-tbody"><tr><td colspan="8"><div class="ix-empty"><div class="ix-empty-mark"><?= ix_icon('search') ?></div><h3>Escolha o que exportar</h3><p>Filtre por livro, intervalo de termos ou datas. Todos os registros encontrados ficam selecionados, inclusive os das outras páginas.</p></div></td></tr></tbody>
            </table>
        </div>
        <div class="ix-cards" id="cg-cards"></div>
        <div class="ix-pager" id="cg-pager"></div>
    </section>

    <form id="cg-download" method="post" action="<?= ix_e($gerar) ?>" class="ix-hidden">
        <input type="hidden" name="ids">
        <input type="hidden" name="somente_validos">
    </form>
<?php
$cfg = ['tipo' => $tipo, 'arquivo' => $meta['arquivo']];
ix_page_end([
    'scripts' => '<script>window.CG_CFG = ' . json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script><script src="../assets/js/carga.js?v=' . INDEXADOR_VERSION . '"></script>',
]);
