/* Testes do guia de Edição de O.S. (editar_os.php) */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

const html = `<!doctype html><html><body>
  <section class="page-hero"></section>
  <form id="osForm">
    <input id="cliente"><input id="cpf_cliente"><input id="base_calculo"><input id="total_os" readonly>
    <input id="descricao_os">
    <input id="ato"><input id="quantidade"><input id="desconto_legal">
    <button type="button" onclick="buscarAto()">Buscar Ato</button>
    <button type="button" onclick="adicionarAtoManual()">Adicionar Ato Manualmente</button>
    <input id="descricao">
    <div class="form-row"><input id="emolumentos"><input id="ferc"><input id="fadep">
      <input id="femp"><input id="ferrfis"><input id="total"></div>
    <button type="button" onclick="adicionarItem()">Adicionar à OS</button>
  </form>
  <div id="osItens"><table id="tabelaItensOS"><tbody id="itensTable">
    <tr data-item-id="1" data-quantidade-liquidada="0">
      <td>1</td><td>16.2</td><td>1</td>
      <td><button type="button" onclick="alterarQuantidade(this)">Alterar</button>
          <button type="button" onclick="marcarItemIsento(this)">Isentar</button>
          <button type="button" onclick="removerItem(this)">Remover</button></td>
    </tr></tbody></table></div>
  <textarea id="observacoes"></textarea>
  <button type="button" onclick="salvarOS()">SALVAR OS</button>
</body></html>`;

const dom = new JSDOM(html, { url: 'http://localhost/atlas/os/editar_os.php?id=1042', pretendToBeVisual: true, runScripts: 'outside-only' });
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
const texto  = () => doc.querySelector('.guia-os__texto').textContent;

setTimeout(() => {
    ok(doc.querySelector('.guia-os-ajuda__rotulo').textContent.indexOf('editar') >= 0,
       'botão de ajuda deveria ser o do guia de edição');
    ok(G.iniciar('editar-os', { reiniciar: true }), 'guia de edição não iniciou');
    ok(titulo().indexOf('Editando uma O.S') >= 0, 'passo 1 errado: ' + titulo());
    ok(/valem.*na hora|SALVAR OS/.test(texto()), 'o passo 1 precisa explicar o que vale na hora e o que só ao salvar');

    for (let i = 0; i < 7; i++) { G.proximo(); }
    ok(titulo().indexOf('Buscar Ato') >= 0, 'não chegou ao passo Buscar Ato: ' + titulo());
    doc.querySelector('button[onclick*="buscarAto"]').click();

    setTimeout(() => {
        ok(titulo().indexOf('Valores do novo ato') >= 0, 'clique real não avançou: ' + titulo());
        G.proximo();
        ok(titulo().indexOf('Adicionar à OS') >= 0, 'passo Adicionar à OS não apareceu: ' + titulo());
        doc.querySelector('#osForm button[onclick*="adicionarItem"]').click();

        setTimeout(() => {
            ok(titulo().indexOf('Itens da Ordem') >= 0, 'não avançou para a lista de itens: ' + titulo());

            /* os três botões de linha existem: nenhum passo pode ser pulado */
            G.proximo();
            ok(titulo().indexOf('Alterar quantidade') >= 0, 'faltou o passo de alterar quantidade: ' + titulo());
            ok(/já liquidada/.test(texto()), 'a regra da quantidade liquidada precisa aparecer no texto');
            G.proximo();
            ok(titulo().indexOf('isento') >= 0, 'faltou o passo de isenção: ' + titulo());
            G.proximo();
            ok(titulo().indexOf('Remover item') >= 0, 'faltou o passo de remoção: ' + titulo());
            ok(/parcialmente liquidado/.test(texto()), 'a regra do item parcialmente liquidado precisa aparecer');
            G.proximo();
            ok(titulo().indexOf('Observações') >= 0, 'faltou o passo de observações: ' + titulo());
            G.proximo();
            ok(titulo().indexOf('SALVAR OS') >= 0, 'faltou o passo final: ' + titulo());

            window.alert = () => {};
            G.proximo();
            ok(G.jaConcluido('editar-os'), 'conclusão do guia de edição não foi registrada');

            const passos = fs.readFileSync('guia/os/guia/guia-os-passos.js', 'utf8');
            ok(/alvo: 'button\[onclick\*="editarOS"\]'/.test(passos),
               'faltou o passo apontando o botão Editar OS na tela da O.S.');

            console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO GUIA DE EDIÇÃO PASSARAM ✔');
            process.exit(falhas.length ? 1 : 0);
        }, 1200);
    }, 1300);
}, 1100);
