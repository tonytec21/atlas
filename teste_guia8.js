/* Testes do guia de Pagamentos (troca de guia ao abrir a janela) */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

const html = `<!doctype html><html><body>
  <div class="header-actions">
    <button type="button" onclick="imprimirOS()">Imprimir OS</button>
    <button type="button" data-toggle="modal" data-target="#pagamentoModal">Pagamentos</button>
    <button type="button" onclick="editarOS()">Editar OS</button>
  </div>
  <div id="osItens"></div>
  <div class="modal fade" id="pagamentoModal">
    <div class="modal-content">
      <h5 id="pagamentoModalLabel">Efetuar Pagamento</h5>
      <div class="row">
        <input id="total_os_modal" readonly>
        <button id="btnIsentoPagamento" onclick="isentarPagamento()">Ato Isento</button>
        <input id="total_pagamento_modal" readonly><input id="valor_liquidado_modal" readonly>
        <input id="saldo_modal" readonly>
      </div>
      <select id="forma_pagamento"><option value="">Selecione</option><option>PIX</option></select>
      <input id="valor_pagamento">
      <button id="btnAdicionarPagamento" onclick="adicionarPagamento()">Adicionar Pagamento</button>
      <button onclick="abrirRepasseModal()">Repasse</button>
      <button onclick="abrirDevolucaoModal()">Devolução</button>
      <table id="tabelaIPagamentoOS"><tbody id="pagamentosTable"></tbody></table>
    </div>
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
const modal = doc.getElementById('pagamentoModal');
const titulo = () => doc.querySelector('.guia-os__titulo').textContent;
const texto  = () => doc.querySelector('.guia-os__texto').textContent;
const abrir  = () => modal.classList.add('show');
const fechar = () => modal.classList.remove('show');

setTimeout(() => {
    /* o usuário está no guia da O.S., no passo dos Pagamentos */
    G.iniciar('os-criada', { reiniciar: true });
    G.proximo();
    ok(titulo().indexOf('Pagamentos') >= 0, 'passo de Pagamentos não encontrado: ' + titulo());
    const indiceAnterior = G.emExecucao().indice;

    const rotulo = () => doc.querySelector('.guia-os-ajuda__rotulo').textContent;
    const tituloBtn = () => doc.querySelector('.guia-os-ajuda').getAttribute('title');
    ok(rotulo().indexOf('O que fazer com esta O.S') >= 0, 'rótulo inicial errado: ' + rotulo());

    /* abre a janela: o guia deve TROCAR para o de pagamento */
    abrir();
    setTimeout(() => {
        const atual = G.emExecucao();
        ok(atual && atual.nome === 'pagamento-os',
           'o guia não trocou ao abrir a janela (atual: ' + (atual && atual.nome) + ')');
        ok(titulo().indexOf('Painel de pagamentos') >= 0, 'passo 1 do pagamento errado: ' + titulo());
        ok(rotulo() === 'Como adicionar pagamento',
           'o botão de ajuda deveria mudar de rótulo com o modal aberto (está: ' + rotulo() + ')');
        ok(tituloBtn() === 'Como adicionar pagamento', 'o title do botão não acompanhou o rótulo');
        ok(G.botaoAjudaAtual() === 'pagamento-os', 'o botão deveria apontar para o guia de pagamento');

        G.proximo(); G.proximo();
        ok(titulo().indexOf('Forma de pagamento') >= 0, 'passo da forma errado: ' + titulo());
        ok(/comprovante/i.test(texto()), 'a exigência de comprovante precisa aparecer no texto');
        G.proximo();
        ok(titulo().indexOf('Valor do pagamento') >= 0, 'passo do valor errado: ' + titulo());
        ok(/0 ou 5/.test(texto()), 'a regra dos centavos em espécie precisa aparecer');

        /* passo que espera o clique real no botão */
        G.proximo();
        ok(titulo().indexOf('Adicionar Pagamento') >= 0, 'passo de adicionar errado: ' + titulo());
        ok(doc.querySelector('.guia-os__dica').style.display === 'block', 'faltou a dica de clique');
        doc.getElementById('btnAdicionarPagamento').click();

        setTimeout(() => {
            ok(titulo().indexOf('Pagamentos lançados') >= 0, 'clique real não avançou: ' + titulo());

            /* fecha a janela: volta ao guia anterior, no mesmo passo */
            fechar();
            setTimeout(() => {
                const volta = G.emExecucao();
                ok(volta && volta.nome === 'os-criada',
                   'não retomou o guia da O.S. ao fechar (atual: ' + (volta && volta.nome) + ')');
                ok(volta && volta.indice === indiceAnterior,
                   'retomou em outro passo (' + (volta && volta.indice) + ' em vez de ' + indiceAnterior + ')');

                /* o guia de pagamento não deve criar um segundo botão "?" */
                ok(doc.querySelectorAll('.guia-os-ajuda').length === 1,
                   'só deve existir um botão de ajuda na tela');
                ok(rotulo().indexOf('O que fazer com esta O.S') >= 0,
                   'o rótulo do botão não voltou ao original (está: ' + rotulo() + ')');
                ok(G.botaoAjudaAtual() === 'os-criada', 'o botão deveria voltar a apontar o guia da O.S.');

                /* clicar no botão com o modal aberto abre o guia certo */
                G.parar();
                abrir();
                setTimeout(() => {
                    G.parar();
                    doc.querySelector('.guia-os-ajuda').click();
                    const dep = G.emExecucao();
                    ok(dep && dep.nome === 'pagamento-os',
                       'o botão de ajuda deveria abrir o guia de pagamento (abriu: ' + (dep && dep.nome) + ')');

                    console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO GUIA DE PAGAMENTOS PASSARAM ✔');
                    process.exit(falhas.length ? 1 : 0);
                }, 700);
                return;

                console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO GUIA DE PAGAMENTOS PASSARAM ✔');
                process.exit(falhas.length ? 1 : 0);
            }, 800);
        }, 1200);
    }, 700);
}, 1100);
