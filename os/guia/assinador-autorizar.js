/*!
 * AutorizarAssinador — libera o Assinador SERPRO sem sair do sistema
 * ------------------------------------------------------------------
 * Por que duas etapas?
 *
 *   1) O Assinador conversa por "wss://127.0.0.1:65156" com um certificado
 *      local que o navegador ainda não conhece. Enquanto esse certificado não
 *      for aceito UMA VEZ, em uma aba normal, nada funciona — e o aviso de
 *      segurança do Chrome NUNCA aparece dentro de um iframe (era por isso que
 *      a janelinha exibia "a página pode estar temporariamente indisponível").
 *
 *   2) Com o certificado aceito, a página de autorização (http://127.0.0.1:65056,
 *      que redireciona para a 65156) já pode ser embutida aqui, e o usuário
 *      apenas clica em "Autorizar".
 *
 * O módulo descobre em qual etapa o usuário está, conduz só o que falta e
 * reconecta sozinho ao final.
 *
 * Ajustes (opcionais), ANTES deste script:
 *   window.AUTORIZAR_ASSINADOR = {
 *       url: 'http://127.0.0.1:65056/',            // página de autorização
 *       urlCertificado: 'https://127.0.0.1:65156/',
 *       altura: 300, deslocY: -86, zoom: 1.08
 *   };
 */
(function (window, document) {
    'use strict';

    var CFG = window.AUTORIZAR_ASSINADOR || {};
    var URL_AUTORIZACAO = CFG.url || '';                 // também lida do link da página
    var URL_CERTIFICADO = CFG.urlCertificado || 'https://127.0.0.1:65156/';
    var ALTURA = CFG.altura || 300;
    var DESLOC_Y = (CFG.deslocY != null) ? CFG.deslocY : -86;
    var ZOOM = CFG.zoom || 1.08;

    var INTERVALO = 2000;
    var LIMITE = 120000;

    var modal = null, iframe = null, janela = null, painelCert = null;
    var statusTxt = null, statusIcone = null, instrucao = null;
    var temporizador = null, inicio = 0, jaConectou = false, etapa = null;
    var janelaCert = null, vigiaCert = null;

    /* ------------------------------------------------------------ util */
    function el(tag, classe, pai, html) {
        var e = document.createElement(tag);
        if (classe) { e.className = classe; }
        if (html != null) { e.innerHTML = html; }
        if (pai) { pai.appendChild(e); }
        return e;
    }

    function porId(id) { return document.getElementById(id); }

    /* Indicadores de conexão conhecidos:
       - assinar-os.php  → #serproChip (classe chip on|off)
       - signum          → #topChip (chip on|off|wait) e #sAstat (sig-astat on|off)
       Pode ser ampliado por window.AUTORIZAR_ASSINADOR.seletoresEstado. */
    var SELETORES_ESTADO = CFG.seletoresEstado ||
        ['#serproChip', '#topChip', '#sAstat', '#cfgAssBadge'];

    function estadoConexao() {
        for (var i = 0; i < SELETORES_ESTADO.length; i++) {
            var e = document.querySelector(SELETORES_ESTADO[i]);
            if (!e) { continue; }
            var c = ' ' + e.className + ' ';
            if (/\son\s/.test(c) || /\scert-ok\s/.test(c)) { return 'on'; }
            if (/\soff\s/.test(c) || /\scert-err\s/.test(c)) { return 'off'; }
            return '';
        }
        return null;
    }

    var SELETORES_RECONECTAR = CFG.seletoresReconectar || ['#btnReconnect', '#cfgTestar'];

    function botaoReconectar() {
        for (var i = 0; i < SELETORES_RECONECTAR.length; i++) {
            var b = document.querySelector(SELETORES_RECONECTAR[i]);
            if (b) { return b; }
        }
        return null;
    }

    function reconectar() {
        var btn = botaoReconectar();
        if (btn) { btn.click(); return true; }
        return false;
    }

    /* O certificado local já foi aceito? Um "fetch" para a origem do Assinador
       falha enquanto ele não estiver liberado — é esse o sinal que usamos. */
    function sondarCertificado() {
        if (!window.fetch) { return Promise.resolve(null); }
        var controle = window.AbortController ? new window.AbortController() : null;
        if (controle) { window.setTimeout(function () { controle.abort(); }, 4000); }
        return window.fetch(URL_CERTIFICADO + '?probe=' + Date.now(), {
            mode: 'no-cors',
            cache: 'no-store',
            signal: controle ? controle.signal : undefined
        }).then(function () { return true; }).catch(function () { return false; });
    }

    /* ------------------------------------------------------------ modal */
    function montar() {
        if (modal) { return modal; }

        modal = el('div', 'aa-modal', document.body);
        modal.id = 'modalAutorizarAssinador';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-label', 'Autorizar o Assinador SERPRO');

        el('div', 'aa-fundo', modal).addEventListener('click', fechar);

        var caixa = el('div', 'aa-caixa', modal);

        var cab = el('div', 'aa-cabecalho', caixa);
        el('span', 'aa-icone', cab, '&#128274;');
        el('h3', 'aa-titulo', cab, 'Liberar o Assinador SERPRO');
        var fechaBtn = el('button', 'aa-fechar', cab, '&times;');
        fechaBtn.type = 'button';
        fechaBtn.setAttribute('aria-label', 'Fechar');
        fechaBtn.addEventListener('click', fechar);

        instrucao = el('p', 'aa-instrucao', caixa, 'Verificando o que falta…');

        janela = el('div', 'aa-janela', caixa);
        janela.style.height = ALTURA + 'px';

        /* --- etapa 1: liberar o certificado (precisa de aba normal) --- */
        painelCert = el('div', 'aa-cert', janela);
        el('div', 'aa-cert__selo', painelCert, '&#128272;');
        el('div', 'aa-cert__titulo', painelCert, 'Passo 1 de 2 — liberar o certificado');
        el('div', 'aa-cert__texto', painelCert,
            'O Assinador usa um certificado próprio, instalado no seu computador, que este navegador '
            + 'ainda não conhece. Clique no botão abaixo: abre uma <b>janelinha</b> com o aviso de '
            + 'segurança — escolha <b>Avançado</b> e depois <b>“Ir para 127.0.0.1 (não seguro)”</b>.<br>'
            + 'A janelinha <b>fecha sozinha</b> e o sistema segue daqui mesmo. Só é preciso fazer isso '
            + 'uma vez por navegador (ou nunca mais, se a T.I. instalar o certificado na máquina).');
        var btnCert = el('button', 'aa-btn aa-btn--principal aa-cert__btn', painelCert,
            'Liberar o certificado do Assinador');
        btnCert.type = 'button';
        btnCert.id = 'btnLiberarCertificado';
        btnCert.addEventListener('click', abrirLiberacaoCertificado);
        el('div', 'aa-cert__url', painelCert, URL_CERTIFICADO);

        /* --- etapa 2: autorizar (página embutida) --- */
        iframe = el('iframe', 'aa-frame', janela);
        iframe.title = 'Autorização do Assinador SERPRO';
        iframe.style.transform = 'scale(' + ZOOM + ')';
        iframe.style.top = DESLOC_Y + 'px';
        el('div', 'aa-cortina aa-cortina--topo', janela);
        el('div', 'aa-cortina aa-cortina--base', janela);

        var st = el('div', 'aa-status', caixa);
        statusIcone = el('span', 'aa-spin', st);
        statusTxt = el('span', 'aa-status-txt', st, 'Verificando…');

        var acoes = el('div', 'aa-acoes', caixa);
        var bRe = el('button', 'aa-btn aa-btn--claro', acoes, 'Já autorizei — reconectar');
        bRe.type = 'button';
        bRe.addEventListener('click', function () {
            definirStatus('espera', 'Reconectando…');
            tentarConectar();
            decidirEtapa();
        });
        var bNova = el('button', 'aa-btn aa-btn--claro', acoes, 'Abrir em outra aba');
        bNova.type = 'button';
        bNova.addEventListener('click', function () {
            window.open(URL_AUTORIZACAO || URL_CERTIFICADO, '_blank', 'noopener');
        });
        var bFechar = el('button', 'aa-btn aa-btn--principal', acoes, 'Fechar');
        bFechar.type = 'button';
        bFechar.addEventListener('click', fechar);

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && modal.classList.contains('show')) { fechar(); }
        });

        // Ao voltar para esta aba (depois de aceitar o certificado), reavalia na hora.
        window.addEventListener('focus', function () {
            if (modal.classList.contains('show')) { decidirEtapa(); tentarConectar(); }
        });
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && modal.classList.contains('show')) { decidirEtapa(); }
        });

        return modal;
    }

    /* Abre a liberação do certificado em uma JANELINHA sobre o sistema (popup),
       e não em outra guia. Não existe forma de o navegador aceitar o certificado
       dentro de um iframe: o aviso de segurança do Chrome nunca é exibido em
       conteúdo embutido — por isso a janelinha. Ela fecha sozinha assim que a
       liberação é detectada. */
    function abrirLiberacaoCertificado() {
        var largura = 560, altura = 640;
        var esq = Math.max((window.screen.width - largura) / 2, 0);
        var topo = Math.max((window.screen.height - altura) / 2 - 30, 0);
        try {
            janelaCert = window.open(URL_CERTIFICADO, 'liberarCertificadoAssinador',
                'width=' + largura + ',height=' + altura + ',left=' + esq + ',top=' + topo
                + ',resizable=yes,scrollbars=yes');
        } catch (e) { janelaCert = null; }

        if (!janelaCert) {
            definirStatus('erro', 'O navegador bloqueou a janela. Libere os pop-ups deste endereço '
                + 'ou use “Abrir em outra aba”.');
            return;
        }
        definirStatus('espera', 'Na janelinha que abriu: Avançado → “Ir para 127.0.0.1 (não seguro)”. '
            + 'Ela fecha sozinha quando terminar.');
        vigiarLiberacao();
    }

    /* Enquanto a janelinha estiver aberta, sonda o certificado com frequência;
       ao detectar a liberação, fecha a janelinha e segue para o passo 2. */
    function vigiarLiberacao() {
        if (vigiaCert) { window.clearInterval(vigiaCert); }
        vigiaCert = window.setInterval(function () {
            sondarCertificado().then(function (liberado) {
                if (liberado === false) { return; }
                window.clearInterval(vigiaCert);
                vigiaCert = null;
                fecharJanelaCert();
                mostrarEtapa('autorizar');
                tentarConectar();
            });
        }, 1500);
    }

    function fecharJanelaCert() {
        try { if (janelaCert && !janelaCert.closed) { janelaCert.close(); } } catch (e) {}
        janelaCert = null;
    }

    function definirStatus(tipo, texto) {
        if (!statusTxt) { return; }
        statusTxt.textContent = texto;
        statusIcone.className = 'aa-spin' + (tipo === 'ok' ? ' aa-spin--ok'
            : (tipo === 'erro' ? ' aa-spin--erro' : ''));
    }

    /* ------------------------------------------------------------ etapas */
    function mostrarEtapa(qual) {
        if (etapa === qual) { return; }
        etapa = qual;
        janela.classList.toggle('aa-janela--certificado', qual === 'certificado');

        if (qual === 'certificado') {
            instrucao.innerHTML = 'Antes de autorizar, o navegador precisa <b>confiar no certificado</b> '
                + 'do Assinador. Por segurança, só o próprio navegador pode fazer isso — por isso o aviso '
                + 'aparece em uma janelinha separada, que fecha sozinha ao terminar.';
            iframe.src = 'about:blank';
            definirStatus('espera', 'Aguardando a liberação do certificado…');
        } else {
            instrucao.innerHTML = 'Na janela abaixo, clique no botão de <b>autorizar</b> do Assinador. '
                + 'O resto é automático: assim que a permissão for concedida, o sistema reconecta e '
                + 'esta janela se fecha sozinha.';
            if (URL_AUTORIZACAO) { iframe.src = URL_AUTORIZACAO + '?t=' + Date.now(); }
            definirStatus('espera', 'Aguardando a autorização…');
        }
    }

    function decidirEtapa() {
        if (window.location.protocol === 'https:') {
            // Página em HTTPS não embute conteúdo de http://127.0.0.1
            mostrarEtapa('certificado');
            return Promise.resolve(false);
        }
        return sondarCertificado().then(function (liberado) {
            mostrarEtapa(liberado === false ? 'certificado' : 'autorizar');
            return liberado;
        });
    }

    /* ------------------------------------------------------------ fluxo */
    function abrir() {
        montar();
        jaConectou = false;
        etapa = null;
        modal.classList.add('show');
        document.body.classList.add('aa-travado');
        definirStatus('espera', 'Verificando o Assinador…');

        inicio = Date.now();
        decidirEtapa();
        tentarConectar();
        temporizador = window.setInterval(verificar, INTERVALO);
    }

    function verificar() {
        if (estadoConexao() === 'on') { return sucesso(); }

        if (Date.now() - inicio > LIMITE) {
            window.clearInterval(temporizador);
            temporizador = null;
            definirStatus('erro', 'Ainda não detectamos a autorização. Confira se o Assinador SERPRO '
                + 'está em execução e use “Já autorizei — reconectar”.');
            return;
        }
        if (etapa === 'certificado') { decidirEtapa(); }
        tentarConectar();
    }

    /* Tenta reconectar e confere logo em seguida, sem esperar o próximo ciclo. */
    function tentarConectar() {
        reconectar();
        window.setTimeout(function () {
            if (estadoConexao() === 'on') { sucesso(); }
        }, 900);
    }

    function sucesso() {
        if (jaConectou) { return; }
        jaConectou = true;
        if (temporizador) { window.clearInterval(temporizador); temporizador = null; }
        definirStatus('ok', 'Assinador conectado! Você já pode assinar o documento.');
        window.setTimeout(fechar, 1600);
    }

    function fechar() {
        if (temporizador) { window.clearInterval(temporizador); temporizador = null; }
        if (vigiaCert) { window.clearInterval(vigiaCert); vigiaCert = null; }
        fecharJanelaCert();
        if (!modal) { return; }
        modal.classList.remove('show');
        document.body.classList.remove('aa-travado');
        if (iframe) { iframe.src = 'about:blank'; }
        var btn = porId('btnAssinar');
        if (btn && !btn.disabled) { btn.focus(); }
    }

    /* ------------------------------------------------------------ ligação */
    function ligar() {
        var link = document.querySelector('a[href*="65056"], a[href*="65156"]');
        if (!link || !botaoReconectar()) { return; }

        // A URL de autorização vem do próprio link da página (respeita a instalação).
        if (!URL_AUTORIZACAO) { URL_AUTORIZACAO = link.href; }

        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = link.className + ' aa-abrir';
        botao.id = 'btnAutorizarAssinador';
        botao.innerHTML = link.innerHTML;
        botao.title = 'Liberar o Assinador sem sair desta tela';
        botao.addEventListener('click', function (ev) { ev.preventDefault(); abrir(); });
        link.parentNode.replaceChild(botao, link);

        montar();     // montada desde já (oculta), para o guia observar a abertura

        window.setTimeout(function () {
            if (estadoConexao() === '' && modal && !modal.classList.contains('show')) {
                botao.classList.add('aa-abrir--piscando');
            }
        }, 3500);

        window.AutorizarAssinador = {
            abrir: abrir,
            fechar: fechar,
            reconectar: reconectar,
            sondarCertificado: sondarCertificado,
            etapa: function () { return etapa; }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ligar);
    } else {
        ligar();
    }

})(window, document);
