/* Testes do guia de assinatura digital (assinar-os.php) — O.S. e Recibo A4 */
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

/* --- estado 1: documento ainda não assinado (editor de posição do selo) --- */
const editor = `<!doctype html><html><body>
  <div class="hero"><h1>Assinar Ordem de Serviço / Orçamento — O.S. nº 1042</h1>
    <span id="serproChip" class="chip"><span id="serproChipTxt">Verificando…</span></span></div>
  <div class="card"><div id="pages"></div><div id="statusLine">Carregando documento…</div></div>
  <div id="sAstat" class="astat"><div id="sState">Assinador conectado</div><div id="sHelp">Token pronto.</div></div>
  <button id="btnReconnect">Reconectar</button>
  <a href="http://127.0.0.1:65056/">Autorizar</a>
  <input id="sealW" type="range" min="0.28" max="0.72" value="0.42"><span id="wVal">42%</span>
  <button id="btnAssinar" disabled>Assinar com o token</button>
</body></html>`;

/* --- estado 2: documento já assinado --- */
const assinado = `<!doctype html><html><body>
  <div class="hero"><h1>Assinar Recibo A4 — O.S. nº 1042</h1>
    <span id="serproChip"><span id="serproChipTxt">—</span></span></div>
  <div class="card"><div class="bd">
    <a href="view_signed_os.php?tipo=recibo_a4&os_id=1042">Abrir PDF assinado</a>
    <a href="assinar-os.php?tipo=recibo_a4&id=1042&resign=1">Assinar novamente</a>
  </div></div>
</body></html>`;

const w1 = montar('http://localhost/atlas/os/assinar-os.php?tipo=os&id=1042', editor);
const w2 = montar('http://localhost/atlas/os/assinar-os.php?tipo=recibo_a4&id=1042', assinado);

setTimeout(() => {
    /* ---------- tela de assinatura (O.S.) ---------- */
    const d1 = w1.document, t1 = () => d1.querySelector('.guia-os__titulo').textContent;
    const x1 = () => d1.querySelector('.guia-os__texto').textContent;
    ok(d1.querySelector('.guia-os-ajuda__rotulo').textContent.indexOf('assinar') >= 0,
       'botão de ajuda errado na tela de assinatura');
    ok(w1.GuiaOS.iniciar('assinar-os', { reiniciar: true }), 'guia de assinatura não iniciou');
    ok(t1().indexOf('Assinatura digital') >= 0, 'passo 1 errado: ' + t1());
    ok(/Recibo A4/.test(x1()), 'o passo 1 deve deixar claro que a tela serve também ao Recibo A4');

    const vistos = [];
    w1.alert = () => {};
    for (let i = 0; i < 9; i++) { vistos.push(t1()); w1.GuiaOS.proximo(); }
    ['Situação do Assinador', 'Conectando o token', 'Onde a assinatura vai aparecer',
     'Tamanho do selo', 'Assinar com o token', 'Terminou'].forEach(t => {
        ok(vistos.some(v => v.indexOf(t) >= 0), 'passo ausente: ' + t);
    });
    /* passos exclusivos do estado "já assinado" devem ser pulados aqui */
    ok(!vistos.some(v => v.indexOf('Documento já assinado') >= 0),
       'o passo do PDF assinado não deveria aparecer no modo editor');
    ok(w1.GuiaOS.jaConcluido('assinar-os'), 'conclusão não registrada');

    /* ---------- tela do documento já assinado (Recibo A4) ---------- */
    const d2 = w2.document, t2 = () => d2.querySelector('.guia-os__titulo').textContent;
    ok(w2.GuiaOS.iniciar('assinar-os', { reiniciar: true }), 'guia não iniciou no estado assinado');
    const vistos2 = [];
    w2.alert = () => {};
    for (let i = 0; i < 9; i++) { vistos2.push(t2()); w2.GuiaOS.proximo(); }
    ['Documento já assinado', 'Assinar novamente', 'Terminou'].forEach(t => {
        ok(vistos2.some(v => v.indexOf(t) >= 0), 'passo ausente no estado assinado: ' + t);
    });
    /* passos do editor (que não existem nesta tela) precisam ser pulados sem travar */
    ok(!vistos2.some(v => v.indexOf('Tamanho do selo') >= 0),
       'passos do editor não deveriam aparecer no documento já assinado');

    /* a tela da O.S. aponta o botão Assinar OS */
    const passos = fs.readFileSync('guia/os/guia/guia-os-passos.js', 'utf8');
    ok(/alvo: 'button\[onclick\*="assinarDocOS"\]'/.test(passos),
       'faltou o passo apontando o botão Assinar OS');

    console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO GUIA DE ASSINATURA PASSARAM ✔');
    process.exit(falhas.length ? 1 : 0);
}, 1100);
