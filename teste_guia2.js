const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c,m)=>{ if(!c) falhas.push(m); };

function montar(url, html) {
  const dom = new JSDOM(html, { url, pretendToBeVisual: true, runScripts: 'outside-only' });
  const { window } = dom;
  window.Element.prototype.getBoundingClientRect = () => ({top:100,left:100,width:200,height:40,right:300,bottom:140,x:100,y:100});
  Object.defineProperty(window.HTMLElement.prototype,'offsetWidth',{get(){return 380;}});
  Object.defineProperty(window.HTMLElement.prototype,'offsetHeight',{get(){return 260;}});
  window.requestAnimationFrame = cb => setTimeout(cb,16);
  window.cancelAnimationFrame = id => clearTimeout(id);
  window.scrollTo = () => {};
  window.eval(fs.readFileSync('guia/os/guia/guia-os.js','utf8'));
  window.eval(fs.readFileSync('guia/os/guia/guia-os-passos.js','utf8'));
  window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
  return window;
}

// ---------- Tela de pesquisa (index.php) ----------
const w1 = montar('http://localhost/atlas/os/index.php', `<!doctype html><html><body>
  <div class="filter-card"></div>
  <table id="tabelaResultados"></table>
  <button onclick="window.location.href='tabela_de_emolumentos.php'">Tabela</button>
  <button onclick="window.location.href='criar_os.php'">Criar Ordem de Serviço</button>
</body></html>`);

setTimeout(() => {
  const doc = w1.document;
  ok(w1.GuiaOS.iniciar('pesquisa-os', {reiniciar:true}), 'guia da pesquisa não iniciou');
  ok(doc.querySelector('.guia-os__titulo').textContent.indexOf('Bem-vindo') >= 0, 'passo 1 da pesquisa errado');
  ok(doc.querySelector('.guia-os-ajuda__rotulo').textContent.indexOf('Guia do módulo') >= 0, 'rótulo do botão de ajuda errado');
  w1.GuiaOS.proximo(); w1.GuiaOS.proximo(); w1.GuiaOS.proximo();
  ok(doc.querySelector('.guia-os__titulo').textContent.indexOf('Criar uma nova') >= 0, 'não chegou ao passo de criação');
  w1.GuiaOS.proximo();   // passo com irPara -> deve gravar o "pendente"
  let pend = null;
  try { pend = JSON.parse(w1.sessionStorage.getItem('atlasGuiaOS.pendente')); } catch(e) {}
  ok(pend && pend.guia === 'criar-os', 'estado pendente para a próxima tela não foi gravado');

  // ---------- Retomada em criar_os.php ----------
  const w2 = montar('http://localhost/atlas/os/criar_os.php', `<!doctype html><html><body>
    <form id="osForm"><select id="modelo_orcamento"></select><input id="cliente"><input id="cpf_cliente">
    <input id="base_calculo"><input id="total_os"><input id="descricao_os"><input id="ato">
    <input id="quantidade"><input id="desconto_legal">
    <button type="button" onclick="buscarAto()">Buscar</button>
    <div class="form-row"><input id="emolumentos"></div>
    <button type="submit">Adicionar à OS</button></form>
    <div id="osItens"></div><textarea id="observacoes"></textarea><button id="btnSalvarOS"></button>
  </body></html>`);
  w2.sessionStorage.setItem('atlasGuiaOS.pendente', JSON.stringify({guia:'criar-os', indice:1}));
  w2.document.dispatchEvent(new w2.Event('DOMContentLoaded'));

  setTimeout(() => {
    const t = w2.document.querySelector('.guia-os__titulo');
    ok(t && t.textContent.indexOf('Apresentante') >= 0, 'retomada na outra tela falhou: ' + (t && t.textContent));
    ok(w2.document.querySelector('.guia-os').classList.contains('guia-os--ativo'), 'overlay não abriu na retomada');
    console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DE NAVEGAÇÃO PASSARAM ✔');
    process.exit(falhas.length ? 1 : 0);
  }, 900);
}, 1100);
