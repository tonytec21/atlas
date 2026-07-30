/* Testes do guia de Anexos (troca de guia e de rótulo ao abrir a janela) */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

const html = `<!doctype html><html><body>
  <div class="header-actions">
    <button type="button" onclick="imprimirOS()">Imprimir OS</button>
    <button type="button" data-toggle="modal" data-target="#pagamentoModal">Pagamentos</button>
    <button type="button" onclick="$('#anexoModal').modal('show');">Anexos</button>
  </div>
  <div id="osItens"></div>
  <div class="modal fade" id="pagamentoModal"><div class="row"><input id="total_os_modal"></div>
    <select id="forma_pagamento"></select><input id="valor_pagamento">
    <button id="btnAdicionarPagamento">Adicionar</button></div>
  <div class="modal fade" id="anexoModal">
    <h5 id="anexoModalLabel">Anexos</h5>
    <form id="formAnexos">
      <div class="upload-card"><strong>Enviar anexos</strong></div>
      <div class="custom-file"><input type="file" id="novo_anexo" multiple>
        <label class="custom-file-label" for="novo_anexo">Clique para escolher os arquivos</label></div>
      <button type="button" onclick="salvarAnexo()">Anexar Arquivos</button>
    </form>
    <table id="anexosTable"><tbody><tr><td>doc.pdf</td></tr></tbody></table>
  </div>
</body></html>`;

const dom = new JSDOM(html, { url: 'http://localhost/atlas/os/visualizar_os.php?id=1042', pretendToBeVisual: true, runScripts: 'outside-only' });
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
const anexo = doc.getElementById('anexoModal');
const pag = doc.getElementById('pagamentoModal');
const titulo = () => doc.querySelector('.guia-os__titulo').textContent;
const texto  = () => doc.querySelector('.guia-os__texto').textContent;
const rotulo = () => doc.querySelector('.guia-os-ajuda__rotulo').textContent;

setTimeout(() => {
    G.iniciar('os-criada', { reiniciar: true });
    G.proximo(); G.proximo();
    const indiceAnterior = G.emExecucao().indice;
    ok(rotulo().indexOf('O que fazer com esta O.S') >= 0, 'rótulo inicial errado: ' + rotulo());

    anexo.classList.add('show');
    setTimeout(() => {
        const atual = G.emExecucao();
        ok(atual && atual.nome === 'anexo-os',
           'o guia não trocou ao abrir os Anexos (atual: ' + (atual && atual.nome) + ')');
        ok(rotulo() === 'Como adicionar anexo', 'o botão “?” deveria mudar de rótulo: ' + rotulo());
        ok(G.botaoAjudaAtual() === 'anexo-os', 'o botão deveria apontar o guia de anexos');
        ok(titulo().indexOf('Anexos da Ordem') >= 0, 'passo 1 errado: ' + titulo());
        ok(/PIX|comprovante/i.test(texto()), 'o passo inicial precisa citar a exigência do comprovante');

        G.proximo();
        ok(titulo().indexOf('Escolher os arquivos') >= 0, 'passo 2 errado: ' + titulo());
        G.proximo();
        ok(titulo().indexOf('Anexar Arquivos') >= 0, 'passo 3 errado: ' + titulo());
        ok(doc.querySelector('.guia-os__dica').style.display === 'block', 'faltou a dica de clique');
        doc.querySelector('#formAnexos button[onclick*="salvarAnexo"]').click();

        setTimeout(() => {
            ok(titulo().indexOf('Anexos adicionados') >= 0, 'clique real não avançou: ' + titulo());

            anexo.classList.remove('show');
            setTimeout(() => {
                const volta = G.emExecucao();
                ok(volta && volta.nome === 'os-criada', 'não retomou o guia da O.S.: ' + (volta && volta.nome));
                ok(volta && volta.indice === indiceAnterior, 'retomou em outro passo');
                ok(rotulo().indexOf('O que fazer com esta O.S') >= 0, 'rótulo não voltou: ' + rotulo());
                ok(doc.querySelectorAll('.guia-os-ajuda').length === 1, 'deve haver só um botão de ajuda');

                /* as duas janelas convivem: abrir Pagamentos continua funcionando */
                pag.classList.add('show');
                setTimeout(() => {
                    ok(G.botaoAjudaAtual() === 'pagamento-os',
                       'o botão deveria apontar o guia de pagamento com aquela janela aberta');
                    ok(rotulo() === 'Como adicionar pagamento', 'rótulo do pagamento errado: ' + rotulo());
                    pag.classList.remove('show');
                    setTimeout(() => {
                        ok(G.botaoAjudaAtual() === 'os-criada', 'o botão não voltou ao guia da O.S.');
                        console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO GUIA DE ANEXOS PASSARAM ✔');
                        process.exit(falhas.length ? 1 : 0);
                    }, 700);
                }, 700);
            }, 800);
        }, 1500);
    }, 700);
}, 1100);
