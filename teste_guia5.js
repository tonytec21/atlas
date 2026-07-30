/* Testes do guia de Modelos de O.S. (modelos_orcamento.php) */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

const html = `<!doctype html><html><body>
  <section class="page-hero"></section>
  <form id="formModelo">
    <input id="nome_modelo"><textarea id="descricao_modelo"></textarea>
    <input id="ato"><input id="quantidade"><input id="desconto_legal">
    <button type="button" onclick="buscarAto()">Buscar Ato</button>
    <button type="button" onclick="adicionarAtoManual()">Adicionar Manualmente</button>
    <input id="descricao_item" readonly>
    <div class="row"><input id="emolumentos"><input id="ferc"><input id="fadep">
      <input id="femp"><input id="ferrfis"><input id="total"></div>
    <button type="button" onclick="adicionarItemTabela()">Adicionar Item</button>
    <div class="table-wrapper"><table><tbody id="tabelaItensModelo"></tbody></table></div>
    <button type="button" onclick="salvarModelo()"><span id="btnSalvarText">Salvar Modelo</span></button>
  </form>
  <div class="card"><div class="card-body" id="listaModelos">modelos...</div></div>
</body></html>`;

const dom = new JSDOM(html, { url: 'http://localhost/atlas/os/modelos_orcamento.php', pretendToBeVisual: true, runScripts: 'outside-only' });
const { window } = dom, doc = window.document;
window.Element.prototype.getBoundingClientRect = () => ({top:120,left:80,width:260,height:40,right:340,bottom:160,x:80,y:120});
Object.defineProperty(window.HTMLElement.prototype, 'offsetWidth',  { get(){ return 380; } });
Object.defineProperty(window.HTMLElement.prototype, 'offsetHeight', { get(){ return 240; } });
window.requestAnimationFrame = cb => setTimeout(cb, 16);
window.cancelAnimationFrame = id => clearTimeout(id);
window.scrollTo = () => {};
window.eval(fs.readFileSync('guia/os/guia/guia-os.js', 'utf8'));
window.eval(fs.readFileSync('guia/os/guia/guia-os-passos.js', 'utf8'));
doc.dispatchEvent(new window.Event('DOMContentLoaded'));

const G = window.GuiaOS;
const titulo = () => doc.querySelector('.guia-os__titulo').textContent;

setTimeout(() => {
    ok(doc.querySelector('.guia-os-ajuda__rotulo').textContent.indexOf('modelo') >= 0,
       'o botão de ajuda deveria ser o do guia de modelos');
    ok(G.iniciar('modelos-os', { reiniciar: true }), 'guia de modelos não iniciou nesta tela');
    ok(titulo().indexOf('Para que servem') >= 0, 'passo 1 errado: ' + titulo());

    G.proximo();
    ok(titulo().indexOf('Nome do modelo') >= 0, 'passo 2 errado: ' + titulo());

    /* percorre até o passo do "Buscar Ato" e simula o clique real */
    for (let i = 0; i < 5; i++) { G.proximo(); }
    ok(titulo().indexOf('Buscar Ato') >= 0, 'não chegou ao passo Buscar Ato: ' + titulo());
    ok(doc.querySelector('.guia-os__dica').style.display === 'block', 'faltou a dica de ação');
    doc.querySelector('button[onclick*="buscarAto"]').click();

    setTimeout(() => {
        ok(titulo().indexOf('Descrição do item') >= 0, 'clique real não avançou: ' + titulo());

        /* o passo dos valores usa "subir" para destacar a linha inteira */
        G.proximo();
        ok(titulo().indexOf('Valores do ato') >= 0, 'passo dos valores errado: ' + titulo());

        /* segue até "Adicionar Item" (que também espera clique real) */
        G.proximo(); G.proximo();
        ok(titulo().indexOf('Adicionar Item') >= 0, 'não chegou ao passo Adicionar Item: ' + titulo());
        doc.querySelector('button[onclick*="adicionarItemTabela"]').click();

        setTimeout(() => {
            /* a tbody está vazia: o passo é opcional e deve ser pulado sem travar */
            ok(['Itens do modelo', 'Salvar Modelo'].some(t => titulo().indexOf(t) >= 0),
               'fluxo travou depois de adicionar o item: ' + titulo());

            /* até o fim, incluindo o passo final sem alvo */
            window.alert = () => {};
            for (let i = 0; i < 8; i++) { G.proximo(); }
            ok(G.jaConcluido('modelos-os'), 'conclusão do guia de modelos não foi registrada');

            /* a tela de pesquisa ganhou o passo que aponta os Modelos O.S */
            const passos = fs.readFileSync('guia/os/guia/guia-os-passos.js', 'utf8');
            ok(/alvo: 'a\[href\*="modelos_orcamento"\]'/.test(passos),
               'faltou o passo apontando o botão Modelos O.S na tela de pesquisa');

            console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO GUIA DE MODELOS PASSARAM ✔');
            process.exit(falhas.length ? 1 : 0);
        }, 1100);
    }, 1300);
}, 1100);
