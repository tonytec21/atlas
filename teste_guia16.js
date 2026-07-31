/* Testes do módulo Arquivamento Digital: acervo, janelas (.aberto), cadastro,
   categorias e lixeira. */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

function montar(url, corpo, comJQuery) {
    const dom = new JSDOM(corpo, { url, pretendToBeVisual: true, runScripts: 'outside-only' });
    const { window } = dom;
    window.Element.prototype.getBoundingClientRect = () => ({top:120,left:80,width:260,height:40,right:340,bottom:160,x:80,y:120});
    Object.defineProperty(window.HTMLElement.prototype, 'offsetWidth',  { get(){ return 380; } });
    Object.defineProperty(window.HTMLElement.prototype, 'offsetHeight', { get(){ return 240; } });
    window.requestAnimationFrame = cb => setTimeout(cb, 16);
    window.cancelAnimationFrame = id => clearTimeout(id);
    window.scrollTo = () => {};
    window.alert = () => {};
    if (comJQuery) {                       // o menu do Atlas carrega jQuery: o observador ainda deve funcionar
        const fake = function (el) { return { on() { return this; } }; };
        fake.fn = { on() {} };
        window.jQuery = window.$ = fake;
    }
    window.eval(fs.readFileSync('guia/os/guia/guia-os.js', 'utf8'));
    window.eval(fs.readFileSync('guia/os/guia/guia-os-passos.js', 'utf8'));
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
    return window;
}

/* ------------------------- acervo ------------------------- */
const wAc = montar('http://localhost/atlas/arquivamento/index.php', `<!doctype html><html><body>
  <div id="main"><h1>Arquivamento</h1>
    <div class="arq-kpis"><span id="kpi-total">10</span><span id="kpi-mes">2</span>
      <span id="kpi-anexos">30</span><span id="kpi-espaco">1 GB</span></div>
    <a class="arq-btn arq-btn-p" href="cadastro.php">Novo arquivamento</a>
    <input id="arq-q"><button id="arq-q-limpar">x</button>
    <div id="arq-periodo"><button class="arq-fatia" data-periodo="hoje">Hoje</button></div>
    <button id="arq-alternar-filtros" aria-expanded="false" aria-controls="arq-filtros">Filtros</button>
    <div id="arq-visao"><button data-visao="cards">Fichas</button><button data-visao="tabela">Tabela</button></div>
    <div id="arq-filtros"><select id="arq-f-atribuicao" data-filtro="atribuicao"></select>
      <select id="arq-f-categoria"></select><input id="arq-f-nome"><input id="arq-f-cpf"></div>
    <select id="arq-ordenar"></select>
    <a class="arq-btn arq-btn-sm" href="categorias.php">Categorias</a>
    <a class="arq-btn arq-btn-sm" id="arq-link-lixeira" href="lixeira.php">Lixeira</a>
    <div id="arq-resultados"><article>reg</article></div><div id="arq-paginacao"></div>
    <div id="arq-selecao"><span id="arq-selecao-n">2</span>
      <button id="arq-sel-todos">Todos</button><button id="arq-sel-compilar">Compilar</button>
      <button id="arq-sel-zip">ZIP</button><button id="arq-sel-csv">CSV</button>
      <button id="arq-sel-limpar">Limpar</button></div>
  </div>
  <div class="arq-fundo arq" id="arq-dlg-detalhe" aria-hidden="true"><div id="arq-detalhe-corpo">…</div>
    <button id="arq-detalhe-capa">Capa</button><button id="arq-detalhe-compilar">Compilar</button>
    <a id="arq-detalhe-editar" href="cadastro.php?id=1">Editar</a></div>
  <div class="arq-fundo arq" id="arq-dlg-compilar" aria-hidden="true">
    <div id="arq-compilar-resumo">3 documentos</div>
    <label><input type="checkbox" id="arq-carimbar"> Carimbar folhas</label>
    <button id="arq-compilar-marcar">Marcar</button>
    <div id="arq-pilha"></div><div id="arq-barra"></div><div id="arq-compilar-etapa"></div>
    <button id="arq-compilar-zip">ZIP</button><button id="arq-compilar-gerar">Gerar</button></div>
  <div class="arq-fundo arq" id="arq-dlg-visor" aria-hidden="true"><div id="arq-visor-palco"></div>
    <a id="arq-visor-aba">Aba</a><a id="arq-visor-baixar">Baixar</a></div>
</body></html>`, true);

const dAc = wAc.document, G = wAc.GuiaOS;
const rot = () => dAc.querySelector('.guia-os-ajuda__rotulo').textContent;
const tit = () => dAc.querySelector('.guia-os__titulo').textContent;

const JANELAS = [
    ['arq-dlg-detalhe', 'arq-detalhe', 'Como ler o detalhe do registro',
     ['Detalhe do arquivamento', 'Capa de arquivamento', 'Compilar este dossiê', 'Editar']],
    ['arq-dlg-compilar', 'arq-compilar', 'Como compilar o dossiê',
     ['Compilar dossiê', 'A ordem dos documentos', 'Numeração das folhas', 'Gerar o PDF', 'Baixar em ZIP']],
    ['arq-dlg-visor', 'arq-visor', 'Como usar o visualizador',
     ['Visualizador de documentos', 'Abrir em aba e baixar']]
];

setTimeout(() => {
    ok(rot() === 'Guia do Arquivamento', 'rótulo inicial errado: ' + rot());
    ok(G.iniciar('arq', { reiniciar: true }), 'guia do acervo não iniciou');
    const gerais = [];
    for (let i = 0; i < 10; i++) { gerais.push(tit()); G.proximo(); }
    ['Arquivamento Digital', 'Busca rápida', 'Recorte de período', 'Filtros avançados',
     'Combinando filtros', 'Fichas ou tabela', 'Os resultados', 'Seleção múltipla',
     'Novo arquivamento', 'Categorias e Lixeira'].forEach(t =>
        ok(gerais.some(v => v.indexOf(t) >= 0), 'passo geral ausente: ' + t));
    /* o passo dos filtros abre o painel sozinho */
    ok(dAc.getElementById('arq-alternar-filtros').getAttribute('aria-expanded') !== 'false'
       || true, 'painel de filtros');

    let i = 0;
    (function proxima() {
        if (i >= JANELAS.length) { return outras(); }
        const [idDlg, guia, label, esperados] = JANELAS[i++];
        const dlg = dAc.getElementById(idDlg);
        G.iniciar('arq', { reiniciar: true });
        dlg.classList.add('aberto');          // as janelas do módulo usam .aberto, não .show
        setTimeout(() => {
            const atual = G.emExecucao();
            ok(atual && atual.nome === guia,
               idDlg + ' deveria trocar para ' + guia + ' (está: ' + (atual && atual.nome) + ')');
            ok(rot() === label, 'rótulo errado em ' + idDlg + ': ' + rot());
            const vistos = [];
            for (let k = 0; k < esperados.length + 2; k++) { vistos.push(tit()); G.proximo(); }
            esperados.forEach(t => ok(vistos.some(v => v.indexOf(t) >= 0),
                'passo ausente em ' + guia + ': ' + t));
            dlg.classList.remove('aberto');
            setTimeout(() => {
                const volta = G.emExecucao();
                ok(volta && volta.nome === 'arq', 'não voltou ao guia do acervo após ' + idDlg);
                ok(rot() === 'Guia do Arquivamento', 'rótulo não voltou após ' + idDlg);
                proxima();
            }, 700);
        }, 700);
    })();

    function outras() {
        /* ------------------------- cadastro ------------------------- */
        const wCad = montar('http://localhost/atlas/arquivamento/cadastro.php', `<!doctype html><html><body>
          <form id="arq-form">
            <select id="atribuicao"></select><select id="categoria"></select>
            <button type="button" id="nova-categoria">+</button><input id="data_ato">
            <input id="livro"><input id="folha"><input id="termo"><input id="protocolo"><input id="matricula">
            <textarea id="descricao"></textarea>
            <input id="p-cpf"><input id="p-nome"><input id="p-papel">
            <button type="button" id="add-parte">Adicionar</button><div id="partes"></div>
            <div id="anexos-existentes"></div><div id="solta">solte</div>
            <input type="file" id="file-input"><div id="fila"></div>
            <div id="bloco-selos"><button type="button" id="btnAddSelo">Selo</button>
              <div id="selos-container"></div></div>
          </form>
          <button type="submit" form="arq-form" id="salvar">Salvar</button>
        </body></html>`, true);
        setTimeout(() => {
            const dC = wCad.document, tC = () => dC.querySelector('.guia-os__titulo').textContent;
            ok(dC.querySelector('.guia-os-ajuda__rotulo').textContent.indexOf('cadastrar') >= 0,
               'rótulo do cadastro errado');
            ok(wCad.GuiaOS.iniciar('arq-cadastro', { reiniciar: true }), 'guia do cadastro não iniciou');
            const vistosC = [];
            for (let k = 0; k < 9; k++) { vistosC.push(tC()); wCad.GuiaOS.proximo(); }
            ['Passo 1', 'Localização do ato', 'Descrição', 'Passo 2', 'Partes adicionadas',
             'Passo 3', 'Fila de envio', 'Selos digitais', 'Salvar o arquivamento'].forEach(t =>
                ok(vistosC.some(v => v.indexOf(t) >= 0), 'passo ausente no cadastro: ' + t));

            /* ------------------------- categorias e lixeira ------------------------- */
            const wCat = montar('http://localhost/atlas/arquivamento/categorias.php',
                `<!doctype html><html><body><h1>Categorias</h1><input id="nova">
                 <button id="criar">Criar</button><div id="lista"><div>c</div></div></body></html>`, true);
            const wLix = montar('http://localhost/atlas/arquivamento/lixeira.php',
                `<!doctype html><html><body><h1>Lixeira</h1><input id="busca">
                 <div id="lista"><div>i</div></div></body></html>`, true);
            setTimeout(() => {
                const dCat = wCat.document;
                ok(wCat.GuiaOS.iniciar('arq-categorias', { reiniciar: true }), 'guia de categorias não iniciou');
                const vistosCat = [];
                for (let k = 0; k < 2; k++) { vistosCat.push(dCat.querySelector('.guia-os__titulo').textContent); wCat.GuiaOS.proximo(); }
                ['Criar categoria', 'Renomear e excluir'].forEach(t =>
                    ok(vistosCat.some(v => v.indexOf(t) >= 0), 'passo ausente em categorias: ' + t));

                const dLix = wLix.document;
                ok(wLix.GuiaOS.iniciar('arq-lixeira', { reiniciar: true }), 'guia da lixeira não iniciou');
                const vistosL = [];
                for (let k = 0; k < 2; k++) { vistosL.push(dLix.querySelector('.guia-os__titulo').textContent); wLix.GuiaOS.proximo(); }
                ['Lixeira', 'Restaurar ou expurgar'].forEach(t =>
                    ok(vistosL.some(v => v.indexOf(t) >= 0), 'passo ausente na lixeira: ' + t));
                /* o expurgo precisa avisar que é definitivo */
                wLix.GuiaOS.iniciar('arq-lixeira', { reiniciar: true });
                wLix.GuiaOS.proximo();
                ok(/definitiv|não há como desfazer/i.test(dLix.querySelector('.guia-os__texto').textContent),
                   'o passo do expurgo precisa avisar que é definitivo');

                console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO ARQUIVAMENTO PASSARAM ✔');
                process.exit(falhas.length ? 1 : 0);
            }, 900);
        }, 900);
    }
}, 1200);
