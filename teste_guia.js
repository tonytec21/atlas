const { JSDOM } = require('jsdom');
const fs = require('fs');

const html = `<!doctype html><html><body>
<div class="bottom-nav" style="height:65px"></div>
<div id="main" class="main-content">
  <div class="header-actions">
    <button type="button" onclick="window.open('tabela_de_emolumentos.php')">Tabela de Emolumentos</button>
  </div>
  <form id="osForm">
    <select id="modelo_orcamento"><option>Selecione</option></select>
    <input id="cliente"><input id="cpf_cliente"><input id="base_calculo"><input id="total_os" readonly>
    <input id="descricao_os">
    <input id="ato"><input id="quantidade"><input id="desconto_legal">
    <button type="button" onclick="buscarAto()">Buscar Ato</button>
    <button type="button" onclick="adicionarAtoManual()">Adicionar Ato Manualmente</button>
    <input id="descricao">
    <div class="form-row"><input id="emolumentos"><input id="ferc"><input id="fadep"><input id="femp"><input id="ferrfis"><input id="total"></div>
    <button type="submit">Adicionar à OS</button>
  </form>
  <div id="osItens"><table><tbody id="itensTable"></tbody></table></div>
  <textarea id="observacoes"></textarea>
  <button id="btnSalvarOS" disabled>SALVAR OS</button>
</div></body></html>`;

const dom = new JSDOM(html, { url: 'http://localhost/atlas/os/criar_os.php', pretendToBeVisual: true, runScripts: 'outside-only' });
const { window } = dom;

// jsdom não calcula layout: damos dimensões a todos os elementos
window.Element.prototype.getBoundingClientRect = function () {
  const w = this.className && String(this.className).indexOf('guia-os__balao') >= 0 ? 380 : 200;
  return { top: 100, left: 100, width: w, height: 40, right: 100 + w, bottom: 140, x: 100, y: 100 };
};
Object.defineProperty(window.HTMLElement.prototype, 'offsetWidth', { get() { return 380; } });
Object.defineProperty(window.HTMLElement.prototype, 'offsetHeight', { get() { return 260; } });
window.requestAnimationFrame = cb => setTimeout(cb, 16);
window.cancelAnimationFrame = id => clearTimeout(id);
window.scrollTo = () => {};

const evalScript = f => window.eval(fs.readFileSync(f, 'utf8'));
evalScript('guia/os/guia/guia-os.js');
evalScript('guia/os/guia/guia-os-passos.js');
window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

const G = window.GuiaOS;
const doc = window.document;
const falhas = [];
const ok = (c, m) => { if (!c) falhas.push(m); };

setTimeout(() => {
  ok(!!doc.querySelector('.guia-os-ajuda'), 'botão flutuante de ajuda não foi criado');
  ok(G.iniciar('criar-os', { reiniciar: true }), 'guia criar-os não iniciou');

  const titulo = () => doc.querySelector('.guia-os__titulo').textContent;
  const contador = () => doc.querySelector('.guia-os__contador').textContent;
  const anel = () => doc.querySelector('.guia-os__anel');

  ok(doc.querySelector('.guia-os').classList.contains('guia-os--ativo'), 'overlay não ficou ativo');
  ok(titulo().indexOf('Modelo') >= 0, 'passo 1 errado: ' + titulo());
  ok(/Passo 1 de \d+/.test(contador()), 'contador errado: ' + contador());
  ok(anel().style.display !== 'none', 'anel de destaque não apareceu');

  G.proximo();
  ok(titulo().indexOf('Apresentante') >= 0, 'passo 2 errado: ' + titulo());
  G.anterior();
  ok(titulo().indexOf('Modelo') >= 0, 'voltar não funcionou: ' + titulo());

  // navega até o passo do "Buscar Ato" e simula o clique do usuário
  for (let i = 0; i < 9; i++) G.proximo();
  ok(titulo().indexOf('Buscar Ato') >= 0, 'não chegou no passo Buscar Ato: ' + titulo());
  ok(doc.querySelector('.guia-os__dica').style.display === 'block', 'dica de ação não apareceu');
  doc.querySelector('button[onclick*="buscarAto"]').click();

  setTimeout(() => {
    ok(titulo().indexOf('Valores calculados') >= 0, 'clique real não avançou o passo: ' + titulo());

    // teclado
    const ev = new window.KeyboardEvent('keydown', { key: 'ArrowRight' });
    doc.dispatchEvent(ev);
    ok(titulo().indexOf('Ato fora da tabela') >= 0, 'seta do teclado não avançou: ' + titulo());

    // conclusão + persistência
    window.alert = () => {};
    const total = 16;
    for (let i = 0; i < total; i++) G.proximo();
    ok(G.jaConcluido('criar-os'), 'conclusão não foi registrada no localStorage');
    ok(!doc.querySelector('.guia-os').classList.contains('guia-os--ativo'), 'overlay não fechou ao concluir');
    ok(!G.autoIniciar('criar-os'), 'autoIniciar deveria ser ignorado após conclusão');
    G.esquecer('criar-os');
    ok(!G.jaConcluido('criar-os'), 'esquecer() não limpou o histórico');

    console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TODOS OS TESTES PASSARAM ✔');
    process.exit(falhas.length ? 1 : 0);
  }, 1200);
}, 1100);
