/* Testes dos guias da Tabela de Emolumentos e do Desfazer Liquidações */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

function montar(url, html) {
    const dom = new JSDOM(html, { url, pretendToBeVisual: true, runScripts: 'outside-only' });
    const { window } = dom;
    window.Element.prototype.getBoundingClientRect = () => ({top:120,left:80,width:260,height:40,right:340,bottom:160,x:80,y:120});
    Object.defineProperty(window.HTMLElement.prototype, 'offsetWidth',  { get(){ return 380; } });
    Object.defineProperty(window.HTMLElement.prototype, 'offsetHeight', { get(){ return 240; } });
    window.requestAnimationFrame = cb => setTimeout(cb, 16);
    window.cancelAnimationFrame = id => clearTimeout(id);
    window.scrollTo = () => {};
    window.eval(fs.readFileSync('guia/os/guia/guia-os.js', 'utf8'));
    window.eval(fs.readFileSync('guia/os/guia/guia-os-passos.js', 'utf8'));
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
    return window;
}

/* ---------------- Tabela de Emolumentos ---------------- */
const w1 = montar('http://localhost/atlas/os/tabela_de_emolumentos.php', `<!doctype html><html><body>
  <section class="page-hero"></section>
  <div class="stat-card"></div>
  <div class="filter-card"><form id="pesquisarForm">
    <input id="ato"><input id="descricao"><select id="atribuicao"><option>Todas</option></select>
    <button type="button" onclick="limparFiltros()">Limpar</button>
    <button type="submit">Pesquisar</button>
  </form></div>
  <div class="dt-buttons"><button>Excel</button></div>
  <table id="resultadosTabela"><tbody><tr><td>16.2</td></tr></tbody></table>
  <button type="button" onclick="window.print()">Imprimir</button>
</body></html>`);

/* ---------------- Desfazer Liquidações ---------------- */
const w2 = montar('http://localhost/atlas/liberar_os.php', `<!doctype html><html><body>
  <section class="page-hero"></section>
  <form id="formLiberar">
    <input id="os_id">
    <button id="btnResumo" type="button">Ver Resumo</button>
    <button id="btnLiberar" type="button" disabled>Desfazer Liquidação</button>
  </form>
  <div id="resumoWrap"><div id="sum_liq_hoje">2</div><div id="sum_man_hoje">0</div>
    <div id="sum_anteriores">0</div><div id="sum_itens">2</div></div>
  <table id="tabelaLogs"><tbody><tr><td>hoje</td></tr></tbody></table>
</body></html>`);

setTimeout(() => {
    /* --- emolumentos --- */
    const d1 = w1.document, t1 = () => d1.querySelector('.guia-os__titulo').textContent;
    ok(d1.querySelector('.guia-os-ajuda__rotulo').textContent.indexOf('tabela') >= 0,
       'botão de ajuda errado na tabela de emolumentos');
    ok(w1.GuiaOS.iniciar('tabela-emolumentos', { reiniciar: true }), 'guia da tabela não iniciou');
    ok(t1().indexOf('Tabela de Emolumentos') >= 0, 'passo 1 errado: ' + t1());
    const vistos1 = [];
    w1.alert = () => {};
    for (let i = 0; i < 10; i++) { vistos1.push(t1()); w1.GuiaOS.proximo(); }
    ok(w1.GuiaOS.jaConcluido('tabela-emolumentos'), 'conclusão do guia da tabela não registrada');
    ['Filtrar por código', 'Filtrar por descrição', 'Filtrar por atribuição', 'Pesquisar',
     'Resultado da consulta', 'Exportar', 'Voltando para a O.S.'].forEach(t => {
        ok(vistos1.some(v => v.indexOf(t) >= 0), 'passo ausente na tabela: ' + t);
    });
    /* a tela de pesquisa não pode iniciar sozinha (evita abrir tour numa aba de consulta) */
    ok(!/tabela-emolumentos'\);\s*window\.GuiaOS\.autoIniciar/.test(
        fs.readFileSync('guia/os/guia/guia-os-passos.js', 'utf8')),
       'a tabela de emolumentos não deve abrir o guia automaticamente');

    /* --- liberar_os --- */
    const d2 = w2.document, t2 = () => d2.querySelector('.guia-os__titulo').textContent;
    ok(d2.querySelector('.guia-os-ajuda__rotulo').textContent.indexOf('liquidação') >= 0,
       'botão de ajuda errado no desfazer liquidações');
    ok(w2.GuiaOS.iniciar('liberar-os', { reiniciar: true }), 'guia de liberação não iniciou');
    const txt2 = () => d2.querySelector('.guia-os__texto').textContent;
    ok(/somente os atos.*hoje/i.test(txt2()), 'o passo inicial precisa deixar claro que só apaga o de hoje');
    ok(/não pode ser desfeita/i.test(txt2()), 'o passo inicial precisa avisar que a ação é irreversível');

    w2.GuiaOS.proximo();
    ok(t2().indexOf('Número da O.S') >= 0, 'passo 2 errado: ' + t2());
    w2.GuiaOS.proximo();
    ok(t2().indexOf('Ver Resumo') >= 0, 'passo 3 errado: ' + t2());
    ok(d2.querySelector('.guia-os__dica').style.display === 'block', 'faltou a dica de clique em Ver Resumo');
    d2.getElementById('btnResumo').click();

    setTimeout(() => {
        ok(t2().indexOf('O que será apagado') >= 0, 'clique real não avançou: ' + t2());
        w2.GuiaOS.proximo();
        ok(t2().indexOf('bloqueio') >= 0, 'faltou o passo da regra de bloqueio: ' + t2());
        ok(/anteriores/i.test(txt2()), 'a regra dos registros anteriores precisa aparecer no texto');
        w2.GuiaOS.proximo();
        ok(t2().indexOf('Desfazer Liquidação') >= 0, 'faltou o passo do botão desfazer: ' + t2());
        w2.GuiaOS.proximo();
        ok(t2().indexOf('Histórico') >= 0, 'faltou o passo do histórico/log: ' + t2());
        w2.alert = () => {};
        w2.GuiaOS.proximo(); w2.GuiaOS.proximo();
        ok(w2.GuiaOS.jaConcluido('liberar-os'), 'conclusão do guia de liberação não registrada');

        /* include precisa funcionar fora da pasta os/ */
        const php = fs.readFileSync('guia/os/guia/guia.php', 'utf8');
        ok(/DOCUMENT_ROOT/.test(php) && /guiaBaseUrl/.test(php),
           'o include precisa calcular a URL a partir do DOCUMENT_ROOT');

        console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DOS GUIAS DE TABELA E LIBERAÇÃO PASSARAM ✔');
        process.exit(falhas.length ? 1 : 0);
    }, 1500);
}, 1100);
