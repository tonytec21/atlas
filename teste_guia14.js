/* Testes do módulo de Fluxo de Caixa: guia da tela e um guia por janela (modal) */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

const html = `<!doctype html><html><body>
  <form id="pesquisarForm">
    <select id="funcionario"></select><select id="periodo"></select>
    <input type="date" id="data_inicial"><input type="date" id="data_final">
    <div class="form-check"><input type="checkbox" id="modo_visualizacao"><label>Modo de visualização</label></div>
    <button type="submit">Pesquisar</button>
  </form>
  <button type="button" onclick="window.location.href='../analitico/analiticos.php'">Analíticos</button>
  <h5>Resultados da Pesquisa</h5>
  <div id="cardsResultados" class="row cards-wrap">
    <div class="card caixa-card" onclick="verDetalhes()">
      <button title="Saídas e Despesas" class="btn btn-delete btn-sm btn-icon">S</button>
      <button title="Depósito do Caixa" class="btn btn-success btn-sm btn-icon">D</button>
      <a title="Imprimir Fechamento" class="btn btn-primary btn-sm btn-icon" href="#">P</a>
      <button title="Fechar caixa" class="btn btn-lock btn-sm btn-icon">F</button>
    </div>
  </div>

  <div class="modal fade" id="detalhesModal"><div class="modal-content">
    <h5 id="detalhesModalLabel"></h5><div id="modalStatusPill">Aberto</div>
    <div id="cardSaldoInicial"></div><div id="cardTotalAtos"></div><div id="cardTotalRecebido"></div>
    <div id="cardSaidasDespesas"></div><div id="cardTotalSelos"></div><div id="cardTotalEmCaixa"></div>
    <div id="filtrosAtosLiquidados"><input id="filtroAtosAto"><button id="btnLimparFiltrosAtos">Limpar</button></div>
    <table id="tabelaAtos"><tbody id="detalhesAtos"></tbody></table>
  </div></div>

  <div class="modal fade" id="cadastroSaidaModal"><div class="modal-content">
    <form id="formCadastroSaida">
      <input id="titulo"><input id="valor_saida"><select id="forma_de_saida"></select>
      <div class="custom-file"><input type="file" id="anexo"><label class="custom-file-label">Selecione…</label></div>
      <button type="submit">Cadastrar Saída</button></form>
    <table id="tabelaSaidasCadastradas"><tbody id="detalhesSaidasCadastradas"></tbody></table>
  </div></div>

  <div class="modal fade" id="cadastroDepositoModal"><div class="modal-content">
    <div id="total_em_caixa">R$ 1.000</div><div id="total_depositos">R$ 0</div><div id="saldo_transportado">R$ 0</div>
    <form id="formCadastroDeposito">
      <input id="valor_deposito"><select id="tipo_deposito"></select>
      <div class="custom-file"><input type="file" id="comprovante_deposito"><label class="custom-file-label">Selecione…</label></div>
      <div id="sem-comprovante-group"><input type="checkbox" id="sem_comprovante"></div>
      <button type="submit" id="btnAdicionarDeposito">Adicionar</button></form>
    <table id="tabelaDepositosRegistrados"><tbody id="detalhesDepositosRegistrados"></tbody></table>
    <button type="button" id="btnTransportarSaldo" onclick="transportarSaldoFecharCaixa()">Transportar saldo</button>
  </div></div>

  <div class="modal fade" id="anexarComprovanteModal"><div class="modal-content">
    <form id="formAnexarComprovante">
      <div class="custom-file"><input type="file" id="arquivo_comprovante"><label class="custom-file-label">Selecione…</label></div>
      <button type="submit">Anexar</button></form></div></div>

  <div class="modal fade" id="verDepositosCaixaModal"><div class="modal-content">
    <table id="tabelaDepositosCaixaUnificado"><tbody id="detalhesDepositosCaixaUnificado"></tbody></table></div></div>

  <div class="modal fade" id="abrirCaixaModal"><div class="modal-content">
    <form id="formAbrirCaixa"><input id="saldo_inicial"><button type="submit">Abrir Caixa</button></form>
    <button type="button" onclick="pularAberturaCaixa()">Pular</button></div></div>
</body></html>`;

const dom = new JSDOM(html, { url: 'http://localhost/atlas/caixa/index.php', pretendToBeVisual: true, runScripts: 'outside-only' });
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
const rotulo = () => doc.querySelector('.guia-os-ajuda__rotulo').textContent;
const titulo = () => doc.querySelector('.guia-os__titulo').textContent;
const texto  = () => doc.querySelector('.guia-os__texto').textContent;

const JANELAS = [
    ['detalhesModal', 'caixa-detalhes', 'Como ler os detalhes do caixa',
     ['Situação do caixa', 'Saldo inicial', 'O que entrou', 'O que saiu', 'Selos e repasses',
      'Total em caixa', 'Filtrar os atos', 'listas detalhadas']],
    ['cadastroSaidaModal', 'caixa-saida', 'Como lançar uma saída',
     ['Título da saída', 'Valor', 'Forma de saída', 'Anexo', 'Cadastrar a saída', 'Saídas já cadastradas']],
    ['cadastroDepositoModal', 'caixa-deposito', 'Como depositar e fechar o caixa',
     ['Quanto há para depositar', 'Valor do depósito', 'Tipo', 'Comprovante', 'Adicionar depósito',
      'Depósitos registrados', 'Transportar saldo']],
    ['anexarComprovanteModal', 'caixa-anexar', 'Como anexar o comprovante',
     ['Escolher o comprovante', 'Enviar']],
    ['verDepositosCaixaModal', 'caixa-depositos', 'Como conferir os depósitos',
     ['Depósitos do caixa unificado']],
    ['abrirCaixaModal', 'caixa-abrir', 'Como abrir o caixa do dia',
     ['Saldo inicial do dia', 'Abrir caixa', 'Pular por enquanto']]
];

setTimeout(() => {
    /* ---------- guia da tela ---------- */
    ok(rotulo() === 'Guia do Fluxo de Caixa', 'rótulo inicial errado: ' + rotulo());
    ok(G.iniciar('caixa', { reiniciar: true }), 'guia do caixa não iniciou');
    const gerais = [];
    window.alert = () => {};
    for (let i = 0; i < 9; i++) { gerais.push(titulo()); G.proximo(); }
    ['Fluxo de Caixa', 'Funcionário', 'Período', 'Datas', 'Modo de visualização', 'Pesquisar',
     'cards do resultado', 'Ações de cada caixa', 'Analíticos'].forEach(t =>
        ok(gerais.some(v => v.indexOf(t) >= 0), 'passo geral ausente: ' + t));

    /* ---------- cada janela troca o guia e o rótulo ---------- */
    let i = 0;
    (function proxima() {
        if (i >= JANELAS.length) { return final(); }
        const [idModal, nomeGuia, label, esperados] = JANELAS[i++];
        const modal = doc.getElementById(idModal);
        G.iniciar('caixa', { reiniciar: true });          // usuário estava no guia geral
        modal.classList.add('show');
        setTimeout(() => {
            const atual = G.emExecucao();
            ok(atual && atual.nome === nomeGuia,
               'a janela ' + idModal + ' deveria trocar para ' + nomeGuia + ' (está: ' + (atual && atual.nome) + ')');
            ok(rotulo() === label, 'rótulo errado em ' + idModal + ': ' + rotulo());

            const vistos = [];
            for (let k = 0; k < esperados.length + 2; k++) { vistos.push(titulo()); G.proximo(); }
            esperados.forEach(t => ok(vistos.some(v => v.indexOf(t) >= 0),
                'passo ausente em ' + nomeGuia + ': ' + t));

            modal.classList.remove('show');
            setTimeout(() => {
                const volta = G.emExecucao();
                ok(volta && volta.nome === 'caixa',
                   'ao fechar ' + idModal + ' deveria voltar ao guia do caixa (está: ' + (volta && volta.nome) + ')');
                ok(rotulo() === 'Guia do Fluxo de Caixa', 'o rótulo não voltou após fechar ' + idModal);
                proxima();
            }, 700);
        }, 700);
    })();

    function final() {
        ok(doc.querySelectorAll('.guia-os-ajuda').length === 1, 'deve haver só um botão de ajuda');
        /* conteúdo crítico: o fechamento de caixa precisa avisar que é definitivo */
        G.iniciar('caixa-deposito', { reiniciar: true });
        let achou = false;
        for (let k = 0; k < 8; k++) {
            if (titulo().indexOf('Transportar saldo') >= 0) {
                achou = /não aceita novos lançamentos|confira o dinheiro/i.test(texto());
            }
            G.proximo();
        }
        ok(achou, 'o passo do fechamento precisa avisar que o caixa não aceita mais lançamentos');
        console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO FLUXO DE CAIXA PASSARAM ✔');
        process.exit(falhas.length ? 1 : 0);
    }
}, 1200);
