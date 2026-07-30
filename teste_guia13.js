/* Testes do módulo Atlas Forja: guia geral, um guia por ferramenta,
   troca automática ao mudar de aba e guia das configurações. */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

const FERR = [
    ['comprimir', 'Como comprimir PDF'], ['pdf2img', 'Como converter PDF em imagens'],
    ['img2pdf', 'Como gerar PDF de imagens'], ['juntar', 'Como juntar PDFs'],
    ['multiplo', 'Como usar a união múltipla'], ['dividir', 'Como dividir um PDF'],
    ['word2pdf', 'Como converter Word em PDF'], ['pdf2word', 'Como converter PDF em Word']
];

function painel(chave, extra) {
    return `<div class="panel${chave === 'comprimir' ? ' active' : ''}" id="panel-${chave}">
        <div class="fj-card"><div class="dz" data-dz="${chave}">solte aqui<input type="file" hidden></div>
        <div class="flist" data-list="${chave}"></div>${extra || ''}
        <div class="result" data-result="${chave}"></div></div></div>`;
}

const html = `<!doctype html><html><body>
  <section class="fj-hero"><h1>Atlas Forja</h1>
    <div class="fj-actions"><span class="chip on">Ferramentas OK</span>
      <a class="fj-pill fj-soft" href="configurar.php">Configurar</a></div></section>
  <div class="tabs">
    ${FERR.map(([k], i) => `<button class="tab${i === 0 ? ' active' : ''}" data-tab="${k}">${k}</button>`).join('')}
  </div>
  ${painel('comprimir', '<select id="nivelComp"><option value="recomendado">Recomendada</option></select>'
      + '<select id="cinzaComp"><option value="auto">Automático</option></select>'
      + '<div class="hint" id="hintComp">dica</div>'
      + '<button id="btnComprimir">Comprimir</button>'
      + '<div class="badges"><span class="bdg ok">72% menor</span></div>'
      + '<button class="fj-pill fj-soft sel" data-pv="novo">Comprimido</button>'
      + '<button class="fj-pill fj-soft" data-pv="orig">Original</button>'
      + '<button class="fj-pill fj-soft" data-pv="dois">Lado a lado</button>')}
  ${painel('pdf2img', '<select id="fmtImg"></select><select id="dpiImg"></select><button id="btnPdf2Img">Converter</button>')}
  ${painel('img2pdf', '<select id="modoImg2Pdf"></select><button id="btnImg2Pdf">Gerar PDF</button>')}
  ${painel('juntar', '<button id="btnJuntar">Juntar</button>')}
  <div class="panel" id="panel-multiplo"><div class="fj-card">
    <div class="dz" data-dz="ladoA">Lado A<input type="file" hidden></div><div class="flist" data-list="ladoA"></div>
    <div class="dz" data-dz="ladoB">Lado B<input type="file" hidden></div><div class="flist" data-list="ladoB"></div>
    <select id="posMultiplo"></select><button id="btnMultiplo">Gerar em lote</button></div></div>
  ${painel('dividir', '<select id="modoDividir"></select><input type="number" id="valDividir"><button id="btnDividir">Dividir</button>')}
  ${painel('word2pdf', '<button id="btnWord2Pdf">Converter para PDF</button>')}
  ${painel('pdf2word', '<select id="modoPdf2Word"></select><button id="btnPdf2Word">Converter para Word</button>')}
</body></html>`;

function montar(url, corpo) {
    const dom = new JSDOM(corpo, { url, pretendToBeVisual: true, runScripts: 'outside-only' });
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

const w = montar('http://localhost/atlas/forja/index.php', html);
const doc = w.document, G = w.GuiaOS;
const rotulo = () => doc.querySelector('.guia-os-ajuda__rotulo').textContent;
const titulo = () => doc.querySelector('.guia-os__titulo').textContent;

/* as abas trocam de painel, como no módulo real */
doc.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => {
    doc.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
    doc.querySelectorAll('.panel').forEach(x => x.classList.remove('active'));
    t.classList.add('active');
    doc.getElementById('panel-' + t.dataset.tab).classList.add('active');
}));

setTimeout(() => {
    /* ---------- guia geral ---------- */
    ok(rotulo() === 'Guia da Atlas Forja', 'rótulo inicial deveria ser o guia geral: ' + rotulo());
    ok(G.iniciar('forja', { reiniciar: true }), 'guia geral da Forja não iniciou');
    ok(titulo().indexOf('Atlas Forja') >= 0, 'passo 1 errado: ' + titulo());
    const gerais = [];
    w.alert = () => {};
    for (let i = 0; i < 5; i++) { gerais.push(titulo()); G.proximo(); }
    ['Ferramentas externas', 'As oito ferramentas', 'Um guia para cada ferramenta',
     'Como funciona'].forEach(t => ok(gerais.some(v => v.indexOf(t) >= 0), 'passo geral ausente: ' + t));

    /* ---------- o botão “?” segue a aba ---------- */
    FERR.forEach(([chave, label]) => {
        doc.querySelector('.tab[data-tab="' + chave + '"]').click();
    });
    setTimeout(() => {
        ok(rotulo() === 'Como converter PDF em Word',
           'o rótulo deveria seguir a última aba aberta: ' + rotulo());
        ok(G.botaoAjudaAtual() === 'forja-pdf2word', 'o botão deveria apontar o guia da aba atual');

        /* ---------- cada ferramenta tem o seu guia, e ele abre a aba certa ---------- */
        const conteudos = {
            'forja-comprimir':  ['Nível de compressão', 'Cores', 'A dica muda', 'Comprimir',
                                 'O que aconteceu com o arquivo', 'Confira antes de baixar',
                                 'Se ainda estiver grande'],
            'forja-pdf2img':    ['Formato', 'Resolução', 'Converter'],
            'forja-img2pdf':    ['ordem das páginas', 'Tamanho da página', 'Gerar PDF'],
            'forja-juntar':     ['Ordem dos documentos', 'Juntar'],
            'forja-multiplo':   ['Lado A', 'Lado B', 'Ordem da junção', 'Gerar em lote'],
            'forja-dividir':    ['Critério da divisão', 'Quantidade', 'Dividir'],
            'forja-word2pdf':   ['Envie o documento', 'Converter para PDF'],
            'forja-pdf2word':   ['Modo da conversão', 'Converter para Word']
        };
        Object.keys(conteudos).forEach(nome => {
            const chave = nome.replace('forja-', '');
            doc.querySelector('.tab[data-tab="comprimir"]').click();   // sai da aba certa de propósito
            ok(G.iniciar(nome, { reiniciar: true }), 'guia ' + nome + ' não iniciou');
            ok(doc.querySelector('.tab[data-tab="' + chave + '"]').classList.contains('active'),
               'o guia ' + nome + ' deveria abrir a aba ' + chave + ' sozinho');
            const vistos = [];
            for (let i = 0; i < 11; i++) { vistos.push(titulo()); G.proximo(); }
            conteudos[nome].forEach(t => ok(vistos.some(v => v.indexOf(t) >= 0),
                'passo ausente em ' + nome + ': ' + t));
        });

        /* trocar de aba durante um guia troca o guia junto */
        setTimeout(() => {
          G.iniciar('forja-comprimir', { reiniciar: true });
          setTimeout(() => {
            doc.querySelector('.tab[data-tab="dividir"]').click();
            setTimeout(() => {
            const atual = G.emExecucao();
            ok(atual && atual.nome === 'forja-dividir',
               'ao trocar de aba durante o guia, ele deveria acompanhar (está: ' + (atual && atual.nome) + ')');
            ok(rotulo() === 'Como dividir um PDF', 'o rótulo deveria acompanhar: ' + rotulo());

            /* ---------- configurações ---------- */
            const w2 = montar('http://localhost/atlas/forja/configurar.php', `<!doctype html><html><body>
              <section class="fj-hero"><h1>Configurar · Atlas Forja</h1></section>
              <div id="statusFerramentas">ok</div><button id="btnTestar">Testar ferramentas</button>
              <form id="cfgForm">
                <div class="field"><input class="inp" name="gs_path"></div>
                <div class="field"><input class="inp" name="magick_path"></div>
                <div class="field"><input class="inp" name="lo_path"></div>
                <div class="field"><label><input type="checkbox" name="forja_ativo" value="S"> Módulo ativo</label></div>
                <button type="submit">Salvar</button></form>
              <div class="field"><input class="inp" id="loUrl"></div>
              <button id="btnInstalarLO">Baixar e instalar</button>
            </body></html>`);
            setTimeout(() => {
                const d2 = w2.document, t2 = () => d2.querySelector('.guia-os__titulo').textContent;
                ok(w2.GuiaOS.iniciar('forja-config', { reiniciar: true }), 'guia de configuração não iniciou');
                const vistos2 = [];
                w2.alert = () => {};
                for (let i = 0; i < 10; i++) { vistos2.push(t2()); w2.GuiaOS.proximo(); }
                ['Configurar a Forja', 'O que foi encontrado', 'Testar ferramentas', 'Ghostscript',
                 'ImageMagick', 'LibreOffice', 'Módulo ativo', 'Instalar o LibreOffice sozinho',
                 'Baixar e instalar', 'Salvar'].forEach(t =>
                    ok(vistos2.some(v => v.indexOf(t) >= 0), 'passo ausente nas configurações: ' + t));

                console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO MÓDULO FORJA PASSARAM ✔');
                process.exit(falhas.length ? 1 : 0);
            }, 900);
            }, 400);
          }, 200);
        }, 400);
    }, 400);
}, 1200);
