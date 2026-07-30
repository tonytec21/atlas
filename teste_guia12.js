/* Testes do módulo Atlas Signum: guia da tela, guia das configurações e
   a janela de autorização do Assinador funcionando por lá. */
const { JSDOM } = require('jsdom'); const fs = require('fs');
const falhas = []; const ok = (c, m) => { if (!c) falhas.push(m); };

function montar(url, html, comFetch) {
    const dom = new JSDOM(html, { url, pretendToBeVisual: true, runScripts: 'outside-only' });
    const { window } = dom;
    window.Element.prototype.getBoundingClientRect = () => ({top:120,left:80,width:260,height:40,right:340,bottom:160,x:80,y:120});
    Object.defineProperty(window.HTMLElement.prototype, 'offsetWidth',  { get(){ return 380; } });
    Object.defineProperty(window.HTMLElement.prototype, 'offsetHeight', { get(){ return 240; } });
    window.requestAnimationFrame = cb => setTimeout(cb, 16);
    window.cancelAnimationFrame = id => clearTimeout(id);
    window.scrollTo = () => {};
    window.open = () => ({ closed: false, close() { this.closed = true; } });
    if (comFetch) { window.fetch = () => Promise.resolve({ type: 'opaque' }); }  // certificado já liberado
    window.eval(fs.readFileSync('guia/os/guia/guia-os.js', 'utf8'));
    window.eval(fs.readFileSync('guia/os/guia/guia-os-passos.js', 'utf8'));
    window.eval(fs.readFileSync('guia/os/guia/assinador-autorizar.js', 'utf8'));
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
    return window;
}

/* ---------------- signum/index.php ---------------- */
const w1 = montar('http://localhost/atlas/signum/index.php', `<!doctype html><html><body>
  <section class="sg-hero"><h1>Atlas Signum</h1>
    <span class="chip" id="topChip">Assinador SERPRO</span>
    <a class="sg-pill sg-soft" href="configurar.php">Configurar</a></section>
  <div class="sg-card" id="uploadCard"><div class="dz" id="dz">Arraste um PDF</div>
    <input type="file" id="fileInput" hidden></div>
  <div class="sg-card" id="signPanel">
    <div class="pdf-scroll" id="pages"></div>
    <button class="szbtn" id="szMinus">-</button>
    <input type="range" id="sealW" value="0.30"><button class="szbtn" id="szPlus">+</button>
    <span id="wVal">30%</span><div id="statusLine"></div>
    <div class="sig-astat" id="sAstat"><b id="sState">Autorização pendente</b><small id="sHelp">…</small></div>
    <button id="btnReconnect">Reconectar</button>
    <a class="sg-pill sg-soft" href="http://127.0.0.1:65056/" target="_blank">Autorizar</a>
    <button id="btnAuth" disabled>Autenticar certificado</button>
    <ul><li class="sig-step" id="st1">Conectar o token</li><li class="sig-step" id="st2">Autenticar</li>
        <li class="sig-step" id="st3">Posicionar</li><li class="sig-step" id="st4">Assinar</li></ul>
    <button id="btnAssinar" disabled>Assinar documento</button></div>
  <div class="sg-card"><input type="text" id="fq"><select id="fmetodo"></select>
    <table><tbody id="docBody"><tr><td>doc.pdf</td>
      <td><a class="tbtn" href="ver.php?id=1">ver</a><a class="tbtn dl" href="baixar.php?id=1">baixar</a>
          <button class="tbtn rm js-del">excluir</button></td></tr></tbody></table></div>
</body></html>`, true);

/* ---------------- signum/configurar.php ---------------- */
const w2 = montar('http://localhost/atlas/signum/configurar.php', `<!doctype html><html><body>
  <section class="sg-hero"><h1>Configurar · Atlas Signum</h1>
    <a class="sg-pill sg-soft" href="index.php">Voltar</a></section>
  <form id="cfgForm">
    <input type="hidden" name="metodo" id="metodoField" value="a3">
    <div class="card-blk"><h5>Seu método de assinatura</h5>
      <div class="methods"><div class="method sel" data-m="a3">Certificado A3</div>
        <div class="method" data-m="a1">Certificado A1</div></div></div>
    <div class="card-blk only-a3"><h5>Assinador SERPRO</h5>
      <span class="cert-badge cert-warn" id="cfgAssBadge">verificando…</span>
      <button type="button" class="file-btn" id="cfgTestar">Testar</button>
      <a class="file-btn" href="http://127.0.0.1:65056/" target="_blank">Autorizar</a></div>
    <div class="card-blk only-a1"><h5>Seu certificado A1</h5>
      <div class="row2"><div class="field"><label>Arquivo</label>
          <label class="file-btn"><span id="certName">Escolher…</span><input type="file" name="cert" id="certInput" hidden></label></div>
        <div class="field"><label>Senha</label><input class="inp" type="password" name="cert_senha"></div></div></div>
    <div class="card-blk"><h5>Logomarca do carimbo</h5>
      <div class="logo-prev" id="logoPrev"></div>
      <label class="file-btn"><span id="logoName">Escolher…</span><input type="file" name="logo" id="logoInput" hidden></label></div>
    <div class="card-blk"><h5>Dados do carimbo</h5>
      <div class="field"><label class="switch"><input type="checkbox" name="usar_cn_titular" value="1"> Usar o nome do titular</label></div>
      <div class="row2"><div class="field"><input class="inp" name="assinante_nome"></div>
        <div class="field"><input class="inp" name="assinante_cpf" id="cpfInp"></div></div>
      <div class="row2"><div class="field"><input class="inp" name="assinante_cargo"></div>
        <div class="field"><input class="inp" name="assinante_local"></div></div>
      <div class="row2"><div class="field"><input class="inp" name="carimbo_titulo"></div>
        <div class="field"><input class="inp" name="motivo"></div></div></div>
    <button type="submit">Salvar configurações</button>
  </form>
</body></html>`, true);

setTimeout(() => {
    /* ---------- guia da tela principal ---------- */
    const d1 = w1.document, t1 = () => d1.querySelector('.guia-os__titulo').textContent;
    ok(d1.querySelector('.guia-os-ajuda__rotulo').textContent.indexOf('Signum') >= 0,
       'botão de ajuda errado no Signum: ' + d1.querySelector('.guia-os-ajuda__rotulo').textContent);
    ok(w1.GuiaOS.iniciar('signum', { reiniciar: true }), 'guia do Signum não iniciou');
    ok(t1().indexOf('Atlas Signum') >= 0, 'passo 1 errado: ' + t1());

    const vistos = [];
    w1.alert = () => {};
    for (let i = 0; i < 14; i++) { vistos.push(t1()); w1.GuiaOS.proximo(); }
    ['Anexar o PDF', 'Posicionar o carimbo', 'Tamanho do carimbo', 'Assinador SERPRO',
     'Autenticar o certificado', 'roteiro da tela', 'Assinar o documento',
     'Documentos assinados', 'Ações de cada documento', 'Configurar'].forEach(t => {
        ok(vistos.some(v => v.indexOf(t) >= 0), 'passo ausente no Signum: ' + t);
    });

    /* ---------- autorização do Assinador dentro do Signum ---------- */
    const botao = d1.getElementById('btnAutorizarAssinador');
    ok(botao, 'o link Autorizar do Signum deveria virar botão');
    ok(vistos.some(v => v.indexOf('Autorizar o Assinador') >= 0),
       'o guia do Signum deveria falar do botão Autorizar');

    /* o módulo precisa ler o estado pelo #topChip/#sAstat (e não pelo #serproChip) */
    d1.getElementById('topChip').className = 'chip on';
    botao.click();
    const modal = d1.getElementById('modalAutorizarAssinador');
    ok(modal && modal.classList.contains('show'), 'a janela de autorização não abriu no Signum');

    setTimeout(() => {
        const atual = w1.GuiaOS.emExecucao();
        ok(atual && atual.nome === 'autorizar-assinador',
           'o guia não trocou ao abrir a autorização no Signum: ' + (atual && atual.nome));
        ok(d1.querySelector('.guia-os-ajuda__rotulo').textContent === 'Como autorizar o Assinador',
           'rótulo do botão não trocou no Signum');

        setTimeout(() => {
            ok(!modal.classList.contains('show'),
               'com o Assinador já online, a janela deveria detectar e fechar sozinha');
            ok(d1.querySelector('.guia-os-ajuda__rotulo').textContent === 'Guia do Atlas Signum',
               'o rótulo deveria voltar ao guia do Signum');

            /* ---------- guia das configurações ---------- */
            const d2 = w2.document, t2 = () => d2.querySelector('.guia-os__titulo').textContent;
            ok(d2.querySelector('.guia-os-ajuda__rotulo').textContent.indexOf('configurações') >= 0,
               'botão de ajuda errado no configurar do Signum');
            ok(w2.GuiaOS.iniciar('signum-config', { reiniciar: true }), 'guia de configuração não iniciou');
            const vistos2 = [];
            w2.alert = () => {};
            for (let i = 0; i < 13; i++) { vistos2.push(t2()); w2.GuiaOS.proximo(); }
            ['Configurações do Signum', 'Método de assinatura', 'Situação do Assinador', 'Testar',
             'Autorizar o Assinador', 'Certificado A1', 'Logomarca do carimbo', 'Nome do assinante',
             'CPF no carimbo', 'Cargo e local', 'Textos do carimbo', 'Salvar configurações',
             'Tudo pronto'].forEach(t => {
                ok(vistos2.some(v => v.indexOf(t) >= 0), 'passo ausente nas configurações: ' + t);
            });
            ok(w2.GuiaOS.jaConcluido('signum-config'), 'conclusão do guia de configurações não registrada');

            /* o módulo de autorização também precisa funcionar nesta tela,
               onde o "reconectar" é o #cfgTestar e o estado vem do #cfgAssBadge */
            const btnCfg = d2.getElementById('btnAutorizarAssinador');
            ok(btnCfg, 'o link Autorizar das configurações deveria virar botão');
            let testesCfg = 0;
            d2.getElementById('cfgTestar').addEventListener('click', () => { testesCfg++; });
            btnCfg.click();
            const modalCfg = d2.getElementById('modalAutorizarAssinador');
            ok(modalCfg && modalCfg.classList.contains('show'), 'a janela não abriu nas configurações');
            ok(testesCfg >= 1, 'deveria acionar o botão “Testar” para reconectar');
            d2.getElementById('cfgAssBadge').className = 'cert-badge cert-ok';

            setTimeout(() => {
                ok(!modalCfg.classList.contains('show'),
                   'com o selo em cert-ok, a janela deveria detectar a conexão e fechar');
                console.log(falhas.length ? 'FALHAS:\n - ' + falhas.join('\n - ') : 'TESTES DO MÓDULO SIGNUM PASSARAM ✔');
                process.exit(falhas.length ? 1 : 0);
            }, 4200);
        }, 4200);
    }, 800);
}, 1200);
