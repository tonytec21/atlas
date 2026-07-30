/* Testes das correções: interação com a página, teclado, foco e SweetAlert2 */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

/* ---------- 1) CSS: o guia não pode capturar cliques ---------- */
const css = fs.readFileSync('guia/os/guia/guia-os.css', 'utf8').replace(/\r\n/g, '\n');
function bloco(seletor) {
    const i = css.indexOf(seletor + ' {');
    return i < 0 ? '' : css.slice(i, css.indexOf('}', i));
}
ok(/pointer-events:\s*none/.test(bloco('.guia-os')), '.guia-os deveria ter pointer-events:none');
ok(/pointer-events:\s*none/.test(bloco('.guia-os__mascara')), 'a máscara deveria ter pointer-events:none');
ok(/pointer-events:\s*auto/.test(bloco('.guia-os__balao')), 'o balão precisa receber cliques');
ok(/\.guia-os--bloqueado \.guia-os__mascara \{ pointer-events: auto/.test(css), 'falta o modo bloquearFundo');
ok(/body\.guia-os-aberto \.swal2-container \{ z-index: 30000/.test(css), 'falta elevar o z-index do SweetAlert2');

/* ---------- 2) comportamento ---------- */
const dom = new JSDOM(`<!doctype html><html><body>
  <form id="osForm"><input id="cliente"><input id="ato">
  <button type="button" onclick="buscarAto()">Buscar</button></form>
  <button id="btnSalvarOS"></button></body></html>`,
  { url: 'http://localhost/atlas/os/criar_os.php', pretendToBeVisual: true, runScripts: 'outside-only' });
const { window } = dom, doc = window.document;
window.Element.prototype.getBoundingClientRect = () => ({top:100,left:100,width:200,height:40,right:300,bottom:140,x:100,y:100});
Object.defineProperty(window.HTMLElement.prototype, 'offsetWidth',  { get(){ return 380; } });
Object.defineProperty(window.HTMLElement.prototype, 'offsetHeight', { get(){ return 260; } });
window.requestAnimationFrame = cb => setTimeout(cb, 16);
window.cancelAnimationFrame = id => clearTimeout(id);
window.scrollTo = () => {};
window.eval(fs.readFileSync('guia/os/guia/guia-os.js', 'utf8'));

const G = window.GuiaOS;
G.registrar('t', [
    { alvo: '#cliente', titulo: 'Um', texto: 'a' },
    { alvo: '#ato', titulo: 'Dois', texto: 'b' },
    { alvo: '#btnSalvarOS', titulo: 'Três', texto: 'c' }
], { botaoAjuda: false });
doc.dispatchEvent(new window.Event('DOMContentLoaded'));

setTimeout(() => {
    G.iniciar('t', { reiniciar: true });
    const titulo = () => doc.querySelector('.guia-os__titulo').textContent;
    const tecla = (key, alvo) => (alvo || doc).dispatchEvent(
        new window.KeyboardEvent('keydown', { key, bubbles: true }));

    ok(doc.body.classList.contains('guia-os-aberto'), 'classe guia-os-aberto não foi aplicada ao body');
    ok(!doc.querySelector('.guia-os').classList.contains('guia-os--bloqueado'),
       'sem bloquearFundo o guia não deve bloquear a página');

    /* digitação: as setas pertencem ao campo, não ao guia */
    const campo = doc.getElementById('cliente');
    campo.focus();
    tecla('ArrowRight', campo);
    ok(titulo() === 'Um', 'seta dentro do campo não podia avançar o guia (foi para: ' + titulo() + ')');
    campo.value = 'JOÃO DA SILVA';
    ok(campo.value === 'JOÃO DA SILVA', 'campo do formulário deveria continuar editável');

    /* fora dos campos, as setas continuam navegando */
    tecla('ArrowRight', doc.body);
    ok(titulo() === 'Dois', 'seta fora do campo deveria avançar (está em: ' + titulo() + ')');

    /* o guia não rouba o foco de quem está digitando */
    campo.focus();
    G.proximo();
    ok(doc.activeElement === campo, 'o guia roubou o foco de um campo em edição');

    /* SweetAlert2: diálogo acima do guia e teclado neutralizado */
    let containerSwal = null;
    window.Swal = {
        fire(cfg) {
            containerSwal = doc.createElement('div');
            containerSwal.className = 'swal2-container';
            doc.body.appendChild(containerSwal);
            if (cfg.didOpen) { cfg.didOpen(); }
            return { then(fn) { window.__resolverSwal = () => { containerSwal.remove(); fn({ isConfirmed: true }); }; } };
        }
    };
    tecla('Escape', doc.body);
    ok(containerSwal, 'a confirmação de saída não foi aberta');
    ok(containerSwal.style.zIndex === '30000',
       'o diálogo precisa ficar acima do guia (z-index atual: ' + containerSwal.style.zIndex + ')');

    const antes = titulo();
    tecla('ArrowRight', doc.body);
    ok(titulo() === antes, 'com diálogo aberto, o teclado não deve mexer no guia');

    window.__resolverSwal();
    ok(!doc.querySelector('.guia-os').classList.contains('guia-os--ativo'), 'o guia não fechou após confirmar a saída');
    ok(!doc.body.classList.contains('guia-os-aberto'), 'a classe do body não foi removida ao sair');

    /* modo opcional de bloqueio */
    G.registrar('bloq', [{ alvo: '#cliente', titulo: 'X', texto: 'x' }], { botaoAjuda: false, bloquearFundo: true });
    G.iniciar('bloq', { reiniciar: true });
    ok(doc.querySelector('.guia-os').classList.contains('guia-os--bloqueado'),
       'bloquearFundo:true deveria ativar a máscara clicável');

    console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DAS CORREÇÕES PASSARAM ✔');
    process.exit(falhas.length ? 1 : 0);
}, 200);
