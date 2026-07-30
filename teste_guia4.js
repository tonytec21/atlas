/* Testes de rolagem automática e de posicionamento do balão (sem cobrir o destaque) */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

const VH = 800, VW = 1280, CABECALHO = 56, NAV = 65, BALAO_H = 240, BALAO_W = 380;

const html = `<!doctype html><html><body>
  <div id="system-name" style="position:fixed"></div>
  <div class="bottom-nav" style="position:fixed"></div>
  <input id="campo1"><input id="campo2"><input id="campo3"><input id="campo4">
</body></html>`;
const dom = new JSDOM(html, { url: 'http://localhost/atlas/os/criar_os.php', pretendToBeVisual: true, runScripts: 'outside-only' });
const { window } = dom, doc = window.document;

/* ---- modelo de layout falso: cada elemento tem uma posição no documento ---- */
const posDoc = new Map();               // elemento -> {top,left,w,h} em coordenadas do documento
posDoc.set(doc.getElementById('system-name'), { top: 0,    left: 0,   w: VW, h: CABECALHO, fixo: true });
posDoc.set(doc.querySelector('.bottom-nav'),  { top: VH - NAV, left: 0, w: VW, h: NAV,     fixo: true });
posDoc.set(doc.getElementById('campo1'), { top: 200,  left: 60, w: 300, h: 40 });
posDoc.set(doc.getElementById('campo2'), { top: 700,  left: 60, w: 300, h: 40 });   // perto do rodapé
posDoc.set(doc.getElementById('campo3'), { top: 2400, left: 60, w: 300, h: 40 });   // bem abaixo
posDoc.set(doc.getElementById('campo4'), { top: 3000, left: 60, w: 900, h: 40 });   // largo, no fim da página

let scrollY = 0;
Object.defineProperty(window, 'pageYOffset', { get: () => scrollY });
window.scrollTo = (a) => { scrollY = Math.max(0, typeof a === 'object' ? a.top : arguments[1] || 0); };
Object.defineProperty(window.document.documentElement, 'clientHeight', { get: () => VH });
Object.defineProperty(window.document.documentElement, 'clientWidth',  { get: () => VW });
window.Element.prototype.getBoundingClientRect = function () {
    const p = posDoc.get(this);
    if (!p) { return { top: 0, left: 0, width: 0, height: 0, right: 0, bottom: 0, x: 0, y: 0 }; }
    const top = p.fixo ? p.top : p.top - scrollY;
    return { top, left: p.left, width: p.w, height: p.h, right: p.left + p.w, bottom: top + p.h, x: p.left, y: top };
};
Object.defineProperty(window.HTMLElement.prototype, 'offsetWidth',  { get() { return BALAO_W; } });
Object.defineProperty(window.HTMLElement.prototype, 'offsetHeight', { get() { return BALAO_H; } });
window.requestAnimationFrame = cb => setTimeout(cb, 16);
window.cancelAnimationFrame = id => clearTimeout(id);
window.eval(fs.readFileSync('guia/os/guia/guia-os.js', 'utf8'));

const G = window.GuiaOS;
G.registrar('t', [
    { alvo: '#campo1', titulo: '1', texto: 'a' },
    { alvo: '#campo2', titulo: '2', texto: 'b' },
    { alvo: '#campo3', titulo: '3', texto: 'c' },
    { alvo: '#campo4', titulo: '4', texto: 'd' }
], { botaoAjuda: false });
doc.dispatchEvent(new window.Event('DOMContentLoaded'));

const balao = () => doc.querySelector('.guia-os__balao');
function caixaBalao() {
    const b = balao();
    return { top: parseFloat(b.style.top), left: parseFloat(b.style.left),
             bottom: parseFloat(b.style.top) + BALAO_H, right: parseFloat(b.style.left) + BALAO_W };
}
function caixaAlvo(id, folga = 8) {
    const r = doc.getElementById(id).getBoundingClientRect();
    return { top: r.top - folga, left: r.left - folga, bottom: r.bottom + folga, right: r.right + folga };
}
function sobrepoe(a, b) {
    const x = Math.min(a.right, b.right) - Math.max(a.left, b.left);
    const y = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
    return (x > 0 && y > 0) ? x * y : 0;
}
function visivel(id) {
    const r = doc.getElementById(id).getBoundingClientRect();
    return r.top >= CABECALHO && r.bottom <= VH - NAV;
}

setTimeout(() => {
    G.iniciar('t', { reiniciar: true });

    /* passo 1 — campo no meio da tela: sem necessidade de rolar demais */
    ok(visivel('campo1'), 'campo1 deveria estar visível');
    ok(sobrepoe(caixaBalao(), caixaAlvo('campo1')) === 0, 'balão cobriu o campo1');

    /* passo 2 — campo perto do rodapé: a página deve rolar sozinha */
    G.proximo();
    ok(visivel('campo2'), 'campo2 continuou fora da área visível (scrollY=' + scrollY + ')');
    ok(sobrepoe(caixaBalao(), caixaAlvo('campo2')) === 0, 'balão cobriu o campo2');
    const cb2 = caixaBalao();
    ok(cb2.top >= CABECALHO && cb2.bottom <= VH - NAV, 'balão saiu da área útil no campo2');

    /* passo 3 — campo bem abaixo: rola bastante e mantém tudo visível */
    const antes = scrollY;
    G.proximo();
    ok(scrollY > antes, 'a página não rolou para o campo3');
    ok(visivel('campo3'), 'campo3 fora da área visível após rolar');
    ok(sobrepoe(caixaBalao(), caixaAlvo('campo3')) === 0, 'balão cobriu o campo3');

    /* passo 4 — elemento largo no fim da página */
    G.proximo();
    ok(visivel('campo4'), 'campo4 fora da área visível');
    ok(sobrepoe(caixaBalao(), caixaAlvo('campo4')) === 0, 'balão cobriu o campo4');
    const cb4 = caixaBalao();
    ok(cb4.top >= CABECALHO && cb4.bottom <= VH - NAV, 'balão saiu da área útil no campo4');

    /* varredura: nenhuma posição de alvo pode resultar em balão sobre o destaque */
    let ruins = 0;
    for (let topo = 100; topo <= 3200; topo += 137) {
        posDoc.set(doc.getElementById('campo1'), { top: topo, left: 60, w: 300, h: 40 });
        scrollY = Math.max(0, topo - 400);
        G.iniciar('t', { reiniciar: true });
        if (sobrepoe(caixaBalao(), caixaAlvo('campo1')) > 0) { ruins++; }
        if (!visivel('campo1')) { ruins++; }
    }
    ok(ruins === 0, ruins + ' posições resultaram em balão sobre o destaque ou alvo invisível');

    console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DE ROLAGEM E POSICIONAMENTO PASSARAM ✔');
    process.exit(falhas.length ? 1 : 0);
}, 150);
