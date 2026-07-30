/* Testes da janela de autorização: etapa do certificado, etapa da permissão,
   reconexão automática e troca de guia. */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

const html = `<!doctype html><html><body>
  <div class="hero"><h1>Assinar Recibo A4 — O.S. nº 5421</h1>
    <span id="serproChip" class="chip"><span id="serproChipTxt">Autorização pendente</span></span></div>
  <div class="card"><div id="pages"></div><div id="statusLine">ok</div></div>
  <div id="sAstat" class="astat"><div id="sState">Autorização pendente</div><div id="sHelp">Clique em Autorizar.</div></div>
  <button id="btnReconnect">Reconectar</button>
  <a class="btn btn-outline-secondary btn-sm" href="http://127.0.0.1:65056/" target="_blank">Autorizar</a>
  <input id="sealW" type="range" value="0.42">
  <button id="btnAssinar" disabled>Assinar com o token</button>
</body></html>`;

const dom = new JSDOM(html, { url: 'http://localhost/atlas/os/assinar-os.php?tipo=recibo_a4&id=5421', pretendToBeVisual: true, runScripts: 'outside-only' });
const { window } = dom, doc = window.document;
window.Element.prototype.getBoundingClientRect = () => ({top:120,left:80,width:260,height:40,right:340,bottom:160,x:80,y:120});
Object.defineProperty(window.HTMLElement.prototype, 'offsetWidth',  { get(){ return 380; } });
Object.defineProperty(window.HTMLElement.prototype, 'offsetHeight', { get(){ return 240; } });
window.requestAnimationFrame = cb => setTimeout(cb, 16);
window.cancelAnimationFrame = id => clearTimeout(id);
window.scrollTo = () => {};
/* popup simulado: guarda a referência e permite fechar */
let popup = null;
window.open = (url, nome, specs) => {
    popup = { url: url, nome: nome, specs: specs || '', closed: false, close() { this.closed = true; } };
    return popup;
};

/* certificado começa BLOQUEADO (fetch falha, como no Chrome com cert não aceito) */
let certLiberado = false;
let sondagens = 0;
window.fetch = (url) => {
    sondagens++;
    return certLiberado ? Promise.resolve({ type: 'opaque' })
                        : Promise.reject(new TypeError('Failed to fetch'));
};

/* o "Reconectar" da página só conecta depois que o certificado é liberado */
let cliquesReconectar = 0;
doc.getElementById('btnReconnect').addEventListener('click', () => {
    cliquesReconectar++;
    if (certLiberado && cliquesReconectar > 2) {
        doc.getElementById('serproChip').className = 'chip on';
        doc.getElementById('serproChipTxt').textContent = 'Assinador conectado';
        doc.getElementById('btnAssinar').disabled = false;
    }
});

window.eval(fs.readFileSync('guia/os/guia/guia-os.js', 'utf8'));
window.eval(fs.readFileSync('guia/os/guia/guia-os-passos.js', 'utf8'));
window.eval(fs.readFileSync('guia/os/guia/assinador-autorizar.js', 'utf8'));
doc.dispatchEvent(new window.Event('DOMContentLoaded'));

const G = window.GuiaOS;
const modal = () => doc.getElementById('modalAutorizarAssinador');
const janela = () => doc.querySelector('.aa-janela');
const statusTxt = () => doc.querySelector('.aa-status-txt').textContent;

setTimeout(() => {
    const botao = doc.getElementById('btnAutorizarAssinador');
    ok(botao, 'o link "Autorizar" deveria ter virado botão');
    ok(window.AutorizarAssinador, 'a API do módulo não foi exposta');

    botao.click();
    ok(modal().classList.contains('show'), 'a janela não abriu');
    ok(sondagens > 0, 'deveria sondar o certificado ao abrir');

    setTimeout(() => {
        /* ---------- etapa 1: certificado ainda não aceito ---------- */
        ok(janela().classList.contains('aa-janela--certificado'),
           'deveria mostrar a etapa do certificado quando o fetch falha');
        ok(doc.getElementById('btnLiberarCertificado'), 'faltou o botão de liberar o certificado');
        ok(doc.querySelector('.aa-frame').src.indexOf('65056') < 0,
           'o iframe não deve carregar a autorização antes do certificado');
        ok(/certificado/i.test(statusTxt()), 'status deveria falar do certificado: ' + statusTxt());
        ok(window.AutorizarAssinador.etapa() === 'certificado', 'etapa interna errada');

        /* o guia trocou e mostra o passo do certificado */
        const atual = G.emExecucao();
        ok(atual && atual.nome === 'autorizar-assinador', 'o guia não trocou: ' + (atual && atual.nome));
        const tit = doc.querySelector('.guia-os__titulo').textContent;
        ok(tit.indexOf('Passo 1') >= 0, 'o guia deveria começar no passo do certificado: ' + tit);

        /* ---------- usuário clica em liberar: deve abrir POPUP, não guia ---------- */
        doc.getElementById('btnLiberarCertificado').click();
        ok(popup, 'o botão deveria abrir uma janela');
        ok(/width=\d+/.test(popup.specs) && /height=\d+/.test(popup.specs),
           'deveria ser uma janelinha (popup) e não uma guia nova: ' + popup.specs);
        ok(popup.url.indexOf('127.0.0.1:65156') >= 0, 'a janelinha deveria abrir o endereço do certificado');
        ok(/janelinha/i.test(statusTxt()), 'o status deveria orientar sobre a janelinha: ' + statusTxt());

        /* usuário aceita o certificado na janelinha */
        certLiberado = true;

        setTimeout(() => {
            ok(popup.closed, 'a janelinha deveria ser fechada automaticamente após a liberação');
            ok(!janela().classList.contains('aa-janela--certificado'),
               'deveria avançar para a etapa de autorização depois do certificado liberado');
            const frame = doc.querySelector('.aa-frame');
            ok(frame.src.indexOf('127.0.0.1:65056') >= 0,
               'o iframe deveria carregar a página de autorização (src: ' + frame.src + ')');
            ok(frame.style.transform.indexOf('scale') >= 0 && frame.style.top,
               'o recorte/zoom do iframe não foi aplicado');
            ok(window.AutorizarAssinador.etapa() === 'autorizar', 'etapa interna deveria ser autorizar');

            /* ---------- reconexão automática e fechamento ---------- */
            setTimeout(() => {
                ok(/conectado/i.test(statusTxt()), 'status deveria indicar sucesso: ' + statusTxt());
                ok(doc.querySelector('.aa-spin').className.indexOf('aa-spin--ok') >= 0,
                   'indicador deveria ficar verde');
                setTimeout(() => {
                    ok(!modal().classList.contains('show'), 'a janela deveria fechar sozinha');
                    ok(doc.querySelector('.guia-os-ajuda__rotulo').textContent === 'Como assinar o documento',
                       'o botão de ajuda deveria voltar ao guia da assinatura');
                    console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DA AUTORIZAÇÃO DO ASSINADOR PASSARAM ✔');
                    process.exit(falhas.length ? 1 : 0);
                }, 2200);
            }, 5000);
        }, 2600);
    }, 900);
}, 1200);
