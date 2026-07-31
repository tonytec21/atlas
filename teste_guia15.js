/* Testes do módulo Contas a Pagar: painel, janelas, extrato/transferência e relatórios */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

function montar(url, corpo) {
    const dom = new JSDOM(corpo, { url, pretendToBeVisual: true, runScripts: 'outside-only' });
    const { window } = dom;
    window.Element.prototype.getBoundingClientRect = () => ({top:120,left:80,width:260,height:40,right:340,bottom:160,x:80,y:120});
    Object.defineProperty(window.HTMLElement.prototype, 'offsetWidth',  { get(){ return 380; } });
    Object.defineProperty(window.HTMLElement.prototype, 'offsetHeight', { get(){ return 240; } });
    window.requestAnimationFrame = cb => setTimeout(cb, 16);
    window.cancelAnimationFrame = id => clearTimeout(id);
    window.scrollTo = () => {};
    window.alert = () => {};
    window.eval(fs.readFileSync('guia/os/guia/guia-os.js', 'utf8'));
    window.eval(fs.readFileSync('guia/os/guia/guia-os-passos.js', 'utf8'));
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
    return window;
}

/* ------------------------- painel ------------------------- */
const wIdx = montar('http://localhost/atlas/contas_a_pagar/index.php', `<!doctype html><html><body>
  <div id="main"><h1>Contas a Pagar</h1>
    <button class="btn btn-primary btn-pill" data-bs-toggle="modal" data-bs-target="#contaModal">Nova conta</button>
    <a class="btn btn-soft btn-pill" href="relatorios.php">Relatórios</a>
    <a class="btn btn-soft btn-pill" href="extrato.php">Extrato</a>
    <button id="btnSyncFundos">Sincronizar fundos</button>
    <button data-bs-toggle="modal" data-bs-target="#configModal">Configurações</button>
    <div class="kpi-grid"><div class="kpi k-aberto"></div><div class="kpi k-venc"></div>
      <div class="kpi k-prox"></div><div class="kpi k-pago"></div></div>
    <div class="row g-3 mb-1"><div class="vconta banco"><a href="extrato.php?conta=banco">Extrato</a></div>
      <div class="vconta especie"></div></div>
    <div class="row"><canvas id="chartStatus"></canvas><canvas id="chartCat"></canvas><canvas id="chartEvol"></canvas></div>
    <form id="searchForm"><input name="q"><select name="categoria"></select><select name="recorrencia"></select>
      <input type="month" name="mes"><select name="status"></select><button type="submit">Filtrar</button></form>
    <table id="tabelaContas"><tbody><tr><td>Energia</td>
      <td><button class="js-pagar">P</button><button class="js-editar">E</button>
          <button class="js-anexos">A</button><button class="js-excluir">X</button></td></tr></tbody></table>
  </div>
  <div class="modal fade cap-modal" id="contaModal"><form id="contaForm">
    <input id="c_titulo"><input id="c_valor"><input id="c_venc"><select id="c_categoria"></select>
    <select id="c_recorrencia"></select>
    <div class="form-check"><input type="checkbox" id="c_parc_on"><label>Parcelar</label></div>
    <div id="c_parc_box"><input id="c_parc_n"><select id="c_parc_tipo"></select><div id="c_parc_prev"></div></div>
    <input id="c_fornecedor"><input id="c_nota_fiscal"><textarea id="c_descricao"></textarea>
    <button id="contaSalvarBtn">Salvar</button></form></div>
  <div class="modal fade cap-modal" id="pagarModal"><div id="pg_titulo"></div>
    <input id="pg_id"><input id="pg_valor"><div id="pg_valor_fmt">R$ 100</div>
    <select id="pg_forma"></select><input id="pg_data"><div id="pg_saldo_box"><span id="pg_saldo_txt"></span></div>
    <button id="pgConfirmBtn">Confirmar</button></div>
  <div class="modal fade cap-modal" id="anexosModal"><div id="axSub"></div>
    <div id="axScreenList"><div id="axDz">solte</div><input type="file" id="axFile"><input id="axDesc">
      <div id="axQueue"></div><div id="axList"></div></div>
    <div id="axScreenView"><button id="axBack">Voltar</button><span id="axViewName"></span>
      <a id="axOpenTab">Abrir</a><a id="axDownload">Baixar</a><div id="axViewerBody"></div></div></div>
  <div class="modal fade cap-modal" id="configModal"><form id="configForm">
    <input id="cfg_email"><input id="cfg_dias">
    <div class="form-check"><input type="checkbox" id="cfg_ativo"><label>Ativo</label></div>
    <input name="smtp_host"><input name="smtp_port"><select name="smtp_sec"></select>
    <button type="button" id="cfgTestBtn">Testar</button></form></div>
</body></html>`);

const dIdx = wIdx.document, G = wIdx.GuiaOS;
const rot = () => dIdx.querySelector('.guia-os-ajuda__rotulo').textContent;
const tit = () => dIdx.querySelector('.guia-os__titulo').textContent;

const JANELAS = [
    ['contaModal', 'cap-conta', 'Como cadastrar uma conta',
     ['Título da conta', 'Valor e vencimento', 'Categoria', 'Recorrência', 'Parcelamento',
      'Fornecedor, nota', 'Salvar']],
    ['pagarModal', 'cap-pagar', 'Como registrar o pagamento',
     ['Registrar o pagamento', 'Forma de pagamento', 'Data do pagamento', 'Saldo da conta', 'Confirmar pagamento']],
    ['anexosModal', 'cap-anexos', 'Como anexar documentos',
     ['Anexar documentos', 'Descrição', 'Arquivos anexados', 'Visualizador']],
    ['configModal', 'cap-config', 'Como configurar os alertas',
     ['E-mail dos alertas', 'Antecedência do aviso', 'Ativar os alertas', 'Servidor de envio', 'Testar o envio']]
];

setTimeout(() => {
    ok(rot() === 'Guia do Contas a Pagar', 'rótulo inicial errado: ' + rot());
    ok(G.iniciar('cap', { reiniciar: true }), 'guia do painel não iniciou');
    const gerais = [];
    for (let i = 0; i < 9; i++) { gerais.push(tit()); G.proximo(); }
    ['Contas a Pagar', 'Contas virtuais', 'Os gráficos', 'Filtros', 'A lista de contas', 'Nova conta',
     'Sincronizar fundos', 'Extrato e Relatórios', 'Configurações'].forEach(t =>
        ok(gerais.some(v => v.indexOf(t) >= 0), 'passo geral ausente: ' + t));

    let i = 0;
    (function proxima() {
        if (i >= JANELAS.length) { return extrato(); }
        const [idModal, guia, label, esperados] = JANELAS[i++];
        const modal = dIdx.getElementById(idModal);
        G.iniciar('cap', { reiniciar: true });
        modal.classList.add('show');
        setTimeout(() => {
            const atual = G.emExecucao();
            ok(atual && atual.nome === guia, idModal + ' deveria trocar para ' + guia + ' (está: ' + (atual && atual.nome) + ')');
            ok(rot() === label, 'rótulo errado em ' + idModal + ': ' + rot());
            const vistos = [];
            for (let k = 0; k < esperados.length + 2; k++) { vistos.push(tit()); G.proximo(); }
            esperados.forEach(t => ok(vistos.some(v => v.indexOf(t) >= 0), 'passo ausente em ' + guia + ': ' + t));
            modal.classList.remove('show');
            setTimeout(() => {
                const volta = G.emExecucao();
                ok(volta && volta.nome === 'cap', 'não voltou ao guia do painel após ' + idModal);
                ok(rot() === 'Guia do Contas a Pagar', 'rótulo não voltou após ' + idModal);
                proxima();
            }, 700);
        }, 700);
    })();

    function extrato() {
        /* ------------------------- extrato + transferência ------------------------- */
        const wEx = montar('http://localhost/atlas/contas_a_pagar/extrato.php?conta=banco', `<!doctype html><html><body>
          <div id="main"><h1>Extrato · Banco</h1><button id="btnTransferir">Transferir</button>
            <div class="filter-card"><input name="de" type="date"><input name="ate" type="date"></div>
            <table id="tabelaContas"><tbody><tr><td>01/07</td><td>Depósito</td>
              <td><button class="js-estornar">Estornar</button></td></tr></tbody></table></div>
          <div class="modal fade cap-modal" id="transferirModal">
            <select id="tr_origem"></select><button id="trSwap">↔</button><select id="tr_destino"></select>
            <input id="tr_valor"><button id="trTudo">Usar todo o saldo</button>
            <input id="tr_data"><input id="tr_obs"><div id="tr_saldo_box"><span id="tr_saldo_txt"></span></div>
            <button id="trConfirmBtn">Transferir</button></div>
        </body></html>`);
        const dEx = wEx.document, GE = wEx.GuiaOS;
        setTimeout(() => {
            ok(dEx.querySelector('.guia-os-ajuda__rotulo').textContent === 'Guia do extrato',
               'rótulo do extrato errado');
            ok(GE.iniciar('cap-extrato', { reiniciar: true }), 'guia do extrato não iniciou');
            const vistosE = [];
            for (let k = 0; k < 5; k++) { vistosE.push(dEx.querySelector('.guia-os__titulo').textContent); GE.proximo(); }
            ['Extrato da conta virtual', 'Período', 'Os movimentos', 'Transferir entre contas',
             'Estornar'].forEach(t => ok(vistosE.some(v => v.indexOf(t) >= 0), 'passo ausente no extrato: ' + t));

            GE.iniciar('cap-extrato', { reiniciar: true });
            dEx.getElementById('transferirModal').classList.add('show');
            setTimeout(() => {
                const atual = GE.emExecucao();
                ok(atual && atual.nome === 'cap-transferir', 'a janela de transferência deveria trocar o guia');
                ok(dEx.querySelector('.guia-os-ajuda__rotulo').textContent === 'Como transferir entre contas',
                   'rótulo da transferência errado');
                const vistosT = [];
                for (let k = 0; k < 6; k++) { vistosT.push(dEx.querySelector('.guia-os__titulo').textContent); GE.proximo(); }
                ['Origem e destino', 'Valor', 'Data e observação', 'Saldo da origem', 'Transferir'].forEach(t =>
                    ok(vistosT.some(v => v.indexOf(t) >= 0), 'passo ausente na transferência: ' + t));

                /* ------------------------- relatórios ------------------------- */
                const wRel = montar('http://localhost/atlas/contas_a_pagar/relatorios.php', `<!doctype html><html><body>
                  <div id="main"><h1>Relatórios de Contas</h1>
                    <a class="btn btn-success btn-pill" href="relatorios.php?export=csv">Exportar CSV</a>
                    <div class="filter-card"><input name="de"><input name="ate"><select name="base"></select>
                      <select name="categoria"></select><select name="status"></select></div>
                    <div class="row"><canvas id="chartCat"></canvas><canvas id="chartMes"></canvas></div>
                    <table id="tabelaContas"><tbody><tr><td>x</td></tr></tbody></table></div>
                </body></html>`);
                setTimeout(() => {
                    const dR = wRel.document;
                    ok(dR.querySelector('.guia-os-ajuda__rotulo').textContent === 'Guia dos relatórios',
                       'rótulo dos relatórios errado');
                    ok(wRel.GuiaOS.iniciar('cap-relatorios', { reiniciar: true }), 'guia dos relatórios não iniciou');
                    const vistosR = [];
                    for (let k = 0; k < 5; k++) { vistosR.push(dR.querySelector('.guia-os__titulo').textContent); wRel.GuiaOS.proximo(); }
                    ['Relatórios de contas', 'Categoria e situação', 'Os gráficos', 'A tabela detalhada',
                     'Exportar CSV'].forEach(t => ok(vistosR.some(v => v.indexOf(t) >= 0),
                        'passo ausente nos relatórios: ' + t));

                    /* conteúdo crítico: a forma de pagamento define a conta debitada */
                    G.iniciar('cap-pagar', { reiniciar: true });
                    let achou = false;
                    for (let k = 0; k < 6; k++) {
                        if (tit().indexOf('Forma de pagamento') >= 0) {
                            achou = /Espécie/.test(dIdx.querySelector('.guia-os__texto').textContent);
                        }
                        G.proximo();
                    }
                    ok(achou, 'o passo da forma precisa explicar qual conta virtual é debitada');

                    console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO CONTAS A PAGAR PASSARAM ✔');
                    process.exit(falhas.length ? 1 : 0);
                }, 900);
            }, 800);
        }, 900);
    }
}, 1200);
