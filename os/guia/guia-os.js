/*!
 * GuiaOS — guia interativo (tour guiado) para o sistema Atlas
 * ------------------------------------------------------------------
 * Sem dependências: JavaScript puro, funciona junto com jQuery/Bootstrap.
 * Usa SweetAlert2 apenas se ele já estiver carregado na página.
 *
 * API:
 *   GuiaOS.registrar(nome, passos, opcoes)
 *   GuiaOS.iniciar(nome, { indice: 0, reiniciar: true })
 *   GuiaOS.parar()            GuiaOS.proximo()      GuiaOS.anterior()
 *   GuiaOS.autoIniciar(nome)  GuiaOS.jaConcluido(nome)
 *   GuiaOS.esquecer(nome)     GuiaOS.esquecerTudo()
 *
 * Qualquer elemento com data-guia="nome-do-guia" inicia o guia ao ser clicado.
 */
(function (window, document) {
    'use strict';

    var PREFIXO = 'atlasGuiaOS.';
    var CHAVE_PENDENTE = PREFIXO + 'pendente';
    var VERSAO = '1.0.0';

    var guias = {};      // nome -> { passos: [], opcoes: {} }
    var estado = null;   // guia em execução
    var elos = {};       // elementos do overlay
    var rafId = null;
    var ouvintes = [];   // listeners temporários do passo atual

    /* ============================================================
     * Utilidades
     * ========================================================== */
    function criar(tag, classe, pai) {
        var el = document.createElement(tag);
        if (classe) { el.className = classe; }
        if (pai) { pai.appendChild(el); }
        return el;
    }

    function ehVisivel(el) {
        if (!el) { return false; }
        var r = el.getBoundingClientRect();
        if (r.width === 0 && r.height === 0) { return false; }
        var s = window.getComputedStyle(el);
        return s.display !== 'none' && s.visibility !== 'hidden';
    }

    function resolverAlvo(passo) {
        if (!passo.alvo) { return null; }
        var el = null;
        if (typeof passo.alvo === 'function') {
            el = passo.alvo();
        } else {
            try { el = document.querySelector(passo.alvo); } catch (e) { el = null; }
        }
        if (el && passo.subir) {
            var pai = el.closest ? el.closest(passo.subir) : null;
            if (pai) { el = pai; }
        }
        return ehVisivel(el) ? el : null;
    }

    function esperarPor(passo, aoAchar, aoDesistir) {
        var limite = passo.aguardar === true ? 6000 : (passo.aguardar || 0);
        var inicio = Date.now();
        (function tentar() {
            var el = resolverAlvo(passo);
            if (el) { return aoAchar(el); }
            if (Date.now() - inicio >= limite) { return aoDesistir(); }
            window.setTimeout(tentar, 120);
        })();
    }

    function guardar(chave, valor) {
        try { window.localStorage.setItem(PREFIXO + chave, valor); } catch (e) {}
    }

    function ler(chave) {
        try { return window.localStorage.getItem(PREFIXO + chave); } catch (e) { return null; }
    }

    function remover(chave) {
        try { window.localStorage.removeItem(PREFIXO + chave); } catch (e) {}
    }

    function limparOuvintes() {
        for (var i = 0; i < ouvintes.length; i++) {
            var o = ouvintes[i];
            o.el.removeEventListener(o.evento, o.fn, o.captura);
        }
        ouvintes = [];
    }

    function ouvir(el, evento, fn, captura) {
        el.addEventListener(evento, fn, !!captura);
        ouvintes.push({ el: el, evento: evento, fn: fn, captura: !!captura });
    }

    function alturaMenuInferior() {
        var nav = document.querySelector('.bottom-nav');
        if (!nav) { return 0; }
        var pos = window.getComputedStyle(nav).position;
        return (pos === 'fixed' || pos === 'sticky') ? nav.getBoundingClientRect().height : 0;
    }

    /* Altura do cabeçalho fixo (no Atlas: #system-name, 56px). Detectado em
       tempo real para funcionar mesmo se o layout mudar. */
    function alturaCabecalhoFixo() {
        var candidatos = document.querySelectorAll(
            '#system-name, header, .navbar.fixed-top, .fixed-top, .app-header');
        var altura = 0;
        for (var i = 0; i < candidatos.length; i++) {
            var el = candidatos[i];
            var st = window.getComputedStyle(el);
            if (st.position !== 'fixed' && st.position !== 'sticky') { continue; }
            var r = el.getBoundingClientRect();
            if (r.top <= 4 && r.height > 0) { altura = Math.max(altura, r.bottom); }
        }
        return altura;
    }

    function areaSobreposta(a, b) {
        var x = Math.min(a.right, b.right) - Math.max(a.left, b.left);
        var y = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
        return (x > 0 && y > 0) ? x * y : 0;
    }

    /* ============================================================
     * Estrutura do overlay (criada uma única vez)
     * ========================================================== */
    function montarOverlay() {
        if (elos.raiz) { return; }

        var raiz = criar('div', 'guia-os', document.body);
        raiz.setAttribute('role', 'dialog');
        raiz.setAttribute('aria-modal', 'false');
        raiz.setAttribute('aria-live', 'polite');

        elos.raiz = raiz;
        elos.mascaras = [];
        ['topo', 'baixo', 'esq', 'dir'].forEach(function (lado) {
            var m = criar('div', 'guia-os__mascara guia-os__mascara--' + lado, raiz);
            elos.mascaras.push(m);
        });
        elos.anel = criar('div', 'guia-os__anel', raiz);

        var balao = criar('div', 'guia-os__balao', raiz);
        elos.balao = balao;
        elos.seta = criar('div', 'guia-os__seta', balao);

        var topo = criar('div', 'guia-os__topo', balao);
        elos.contador = criar('span', 'guia-os__contador', topo);
        elos.fechar = criar('button', 'guia-os__fechar', topo);
        elos.fechar.type = 'button';
        elos.fechar.setAttribute('aria-label', 'Fechar guia');
        elos.fechar.innerHTML = '&times;';

        elos.titulo = criar('h3', 'guia-os__titulo', balao);
        elos.texto = criar('div', 'guia-os__texto', balao);

        var barra = criar('div', 'guia-os__barra', balao);
        elos.progresso = criar('div', 'guia-os__progresso', barra);

        var rodape = criar('div', 'guia-os__rodape', balao);
        elos.pular = criar('button', 'guia-os__btn guia-os__btn--texto', rodape);
        elos.pular.type = 'button';
        elos.pular.textContent = 'Sair do guia';

        var acoes = criar('div', 'guia-os__acoes', rodape);
        elos.anterior = criar('button', 'guia-os__btn guia-os__btn--claro', acoes);
        elos.anterior.type = 'button';
        elos.anterior.textContent = 'Voltar';
        elos.proximo = criar('button', 'guia-os__btn guia-os__btn--principal', acoes);
        elos.proximo.type = 'button';
        elos.proximo.textContent = 'Próximo';

        elos.dica = criar('div', 'guia-os__dica', balao);

        elos.fechar.addEventListener('click', function () { confirmarSaida(); });
        elos.pular.addEventListener('click', function () { confirmarSaida(); });
        elos.anterior.addEventListener('click', function () { anterior(); });
        elos.proximo.addEventListener('click', function () { proximo(); });

        document.addEventListener('keydown', aoTeclar, true);
    }

    function digitando(ev) {
        var el = ev.target;
        if (!el || !el.tagName) { return false; }
        var tag = el.tagName.toUpperCase();
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' ||
               el.isContentEditable === true;
    }

    function dialogoAberto() {
        return !!document.querySelector('.swal2-container, .modal.show, .modal.in');
    }

    function aoTeclar(ev) {
        if (!estado) { return; }
        if (ev.defaultPrevented || ev.ctrlKey || ev.altKey || ev.metaKey) { return; }
        if (dialogoAberto()) { return; }

        // Enquanto o usuário digita em um campo, as setas pertencem ao campo.
        if (digitando(ev) && ev.key !== 'Escape') { return; }

        if (ev.key === 'Escape') { ev.preventDefault(); confirmarSaida(); }
        else if (ev.key === 'ArrowRight') { ev.preventDefault(); proximo(); }
        else if (ev.key === 'ArrowLeft') { ev.preventDefault(); anterior(); }
    }

    /* ============================================================
     * Posicionamento
     * ========================================================== */
    function posicionar() {
        if (!estado) { return; }
        var passo = estado.passos[estado.indice];
        var alvo = estado.alvoAtual;
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        var pad = passo && passo.folga != null ? passo.folga : 8;
        var r;

        if (alvo && ehVisivel(alvo)) {
            var b = alvo.getBoundingClientRect();
            r = { top: b.top - pad, left: b.left - pad, w: b.width + pad * 2, h: b.height + pad * 2 };
        } else {
            r = { top: vh / 2, left: vw / 2, w: 0, h: 0 };   // passo sem alvo: overlay cheio
        }

        var t = Math.max(r.top, 0), l = Math.max(r.left, 0);
        var d = Math.min(r.top + r.h, vh), dr = Math.min(r.left + r.w, vw);

        estilo(elos.mascaras[0], { top: 0, left: 0, width: vw + 'px', height: Math.max(t, 0) + 'px' });
        estilo(elos.mascaras[1], { top: d + 'px', left: 0, width: vw + 'px', height: Math.max(vh - d, 0) + 'px' });
        estilo(elos.mascaras[2], { top: t + 'px', left: 0, width: Math.max(l, 0) + 'px', height: Math.max(d - t, 0) + 'px' });
        estilo(elos.mascaras[3], { top: t + 'px', left: dr + 'px', width: Math.max(vw - dr, 0) + 'px', height: Math.max(d - t, 0) + 'px' });

        if (r.w > 0) {
            elos.anel.style.display = 'block';
            estilo(elos.anel, { top: r.top + 'px', left: r.left + 'px', width: r.w + 'px', height: r.h + 'px' });
        } else {
            elos.anel.style.display = 'none';
        }

        posicionarBalao(r, passo, vw, vh);
    }

    function estilo(el, props) {
        for (var k in props) { if (props.hasOwnProperty(k)) { el.style[k] = props[k]; } }
    }

    /* Calcula a posição do balão para um lado, já com o balão preso dentro da
       área visível (respeitando cabeçalho fixo e menu inferior). */
    function posicaoDoLado(lado, r, bw, bh, vw, vh, topoFixo, navH) {
        var margem = 14;
        var top, left;
        if (lado === 'centro') {
            top = (vh - bh) / 2; left = (vw - bw) / 2;
        } else if (lado === 'baixo') {
            top = r.top + r.h + margem; left = r.left + r.w / 2 - bw / 2;
        } else if (lado === 'topo') {
            top = r.top - bh - margem; left = r.left + r.w / 2 - bw / 2;
        } else if (lado === 'dir') {
            top = r.top + r.h / 2 - bh / 2; left = r.left + r.w + margem;
        } else {
            top = r.top + r.h / 2 - bh / 2; left = r.left - bw - margem;
        }
        // mantém o balão dentro da área útil
        left = Math.min(Math.max(left, 10), Math.max(vw - bw - 10, 10));
        top = Math.min(Math.max(top, topoFixo + 10), Math.max(vh - bh - navH - 10, topoFixo + 10));
        return { top: top, left: left };
    }

    function avaliarLado(lado, r, bw, bh, vw, vh, topoFixo, navH) {
        var p = posicaoDoLado(lado, r, bw, bh, vw, vh, topoFixo, navH);
        var caixaBalao = { top: p.top, left: p.left, right: p.left + bw, bottom: p.top + bh };
        var caixaAlvo = { top: r.top, left: r.left, right: r.left + r.w, bottom: r.top + r.h };
        var cabe = p.top >= topoFixo + 8 &&
                   caixaBalao.bottom <= vh - navH - 8 &&
                   caixaBalao.left >= 8 && caixaBalao.right <= vw - 8;
        return {
            lado: lado,
            pos: p,
            sobreposicao: areaSobreposta(caixaBalao, caixaAlvo),   // 0 = não cobre o destaque
            cabe: cabe
        };
    }

    function escolherLado(r, passo, bw, bh, vw, vh, topoFixo, navH) {
        if (r.w === 0) { return avaliarLado('centro', r, bw, bh, vw, vh, topoFixo, navH); }

        var ordem = ['baixo', 'topo', 'dir', 'esq'];
        if (passo && passo.posicao && passo.posicao !== 'auto') {
            ordem = [passo.posicao].concat(ordem.filter(function (l) { return l !== passo.posicao; }));
        }
        // Histerese: mantém o lado atual enquanto ele continuar válido (evita
        // "pisca-pisca"). Só entra em vigor depois que a rolagem suave termina,
        // para que o lado preferido (abaixo do campo) possa ser reconquistado.
        var rolando = estado && estado.rolandoAte && Date.now() < estado.rolandoAte;
        if (!rolando && estado && estado.lado && ordem.indexOf(estado.lado) >= 0) {
            var atual = avaliarLado(estado.lado, r, bw, bh, vw, vh, topoFixo, navH);
            if (atual.cabe && atual.sobreposicao === 0) { return atual; }
        }
        var melhor = null;
        for (var i = 0; i < ordem.length; i++) {
            var a = avaliarLado(ordem[i], r, bw, bh, vw, vh, topoFixo, navH);
            if (a.cabe && a.sobreposicao === 0) { return a; }        // opção ideal
            if (!melhor ||
                a.sobreposicao < melhor.sobreposicao ||
                (a.sobreposicao === melhor.sobreposicao && a.cabe && !melhor.cabe)) {
                melhor = a;
            }
        }
        return melhor;
    }

    function posicionarBalao(r, passo, vw, vh) {
        var balao = elos.balao;
        var navH = alturaMenuInferior();
        var topoFixo = alturaCabecalhoFixo();
        var bw = balao.offsetWidth;
        var bh = balao.offsetHeight;

        // Telas pequenas: o balão vira uma "folha" fixa na parte de baixo.
        if (vw <= 760) {
            balao.classList.add('guia-os__balao--folha');
            elos.seta.style.display = 'none';
            estilo(balao, { top: 'auto', left: '10px', right: '10px', bottom: (navH + 12) + 'px', width: 'auto' });
            if (estado) { estado.alturaBalao = bh; estado.lado = 'folha'; }
            return;
        }
        balao.classList.remove('guia-os__balao--folha');
        balao.style.right = 'auto';
        balao.style.width = '';

        var escolha = escolherLado(r, passo, bw, bh, vw, vh, topoFixo, navH);
        var lado = escolha.lado;
        var top = escolha.pos.top;
        var left = escolha.pos.left;
        if (estado) { estado.lado = lado; estado.alturaBalao = bh; }

        estilo(balao, { top: top + 'px', left: left + 'px', bottom: 'auto' });

        // seta (escondida quando o balão precisou ser deslocado para longe do alvo)
        var seta = elos.seta;
        var encostado = (lado !== 'centro') && escolha.sobreposicao === 0;
        seta.style.display = encostado ? 'block' : 'none';
        seta.className = 'guia-os__seta guia-os__seta--' + lado;
        if (lado === 'baixo' || lado === 'topo') {
            var cx = r.left + r.w / 2 - left;
            if (cx < 18 || cx > bw - 18) { seta.style.display = 'none'; }
            seta.style.left = Math.min(Math.max(cx, 18), bw - 18) + 'px';
            seta.style.top = '';
        } else if (lado !== 'centro') {
            var cy = r.top + r.h / 2 - top;
            if (cy < 18 || cy > bh - 18) { seta.style.display = 'none'; }
            seta.style.top = Math.min(Math.max(cy, 18), bh - 18) + 'px';
            seta.style.left = '';
        }
    }

    function laco() {
        posicionar();
        rafId = window.requestAnimationFrame(laco);
    }

    /* ============================================================
     * Execução dos passos
     * ========================================================== */
    function mostrarPasso(indice) {
        if (!estado) { return; }
        limparOuvintes();

        if (indice < 0) { indice = 0; }
        if (indice >= estado.passos.length) { return concluir(); }

        estado.indice = indice;
        var passo = estado.passos[indice];

        // permite pular passos condicionais (ex.: botão que não existe para o usuário)
        if (typeof passo.quando === 'function' && !passo.quando()) {
            return mostrarPasso(indice + (estado.direcao || 1));
        }

        var seguir = function (alvo) {
            estado.alvoAtual = alvo;
            if (typeof passo.aoEntrar === 'function') { passo.aoEntrar(alvo, api); }
            estado.lado = null;                       // recalcula o lado a cada passo
            // Pinta primeiro (para medir a altura real do balão) e só então rola,
            // reservando espaço para o balão junto do elemento destacado.
            pintarPasso(passo, indice, alvo);
            if (alvo) { rolarAte(alvo, passo); }
            salvarProgresso();
        };

        if (passo.aguardar) {
            esperarPor(passo, seguir, function () {
                if (passo.opcional !== false) {
                    return mostrarPasso(indice + (estado.direcao || 1));
                }
                seguir(null);
            });
        } else {
            var alvo = resolverAlvo(passo);
            if (!alvo && passo.alvo && passo.opcional !== false) {
                return mostrarPasso(indice + (estado.direcao || 1));
            }
            seguir(alvo);
        }
    }

    /* Rola a página de modo que o elemento destacado E o balão caibam juntos na
       área visível (entre o cabeçalho fixo e o menu inferior). É isso que faz a
       tela "acompanhar" o guia conforme os passos descem no formulário. */
    function rolarAte(el, passo) {
        if (!el) { return; }
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        var topoFixo = alturaCabecalhoFixo();
        var navH = alturaMenuInferior();
        var folga = (passo && passo.folga != null) ? passo.folga : 8;
        var margem = 14;

        var b = el.getBoundingClientRect();
        var alvoTopo = b.top - folga;
        var alvoAltura = b.height + folga * 2;
        var bh = elos.balao.offsetHeight || 240;
        var bw = elos.balao.offsetWidth || 380;

        var util = vh - topoFixo - navH - 20;          // altura realmente utilizável
        var r = { top: alvoTopo, left: b.left - folga, w: b.width + folga * 2, h: alvoAltura };

        // Já está tudo visível e o balão cabe ao lado/abaixo sem cobrir o alvo?
        var ok = alvoTopo >= topoFixo + 8 && (alvoTopo + alvoAltura) <= vh - navH - 8;
        if (ok && vw > 760) {
            var escolha = escolherLado(r, passo, bw, bh, vw, vh, topoFixo, navH);
            if (escolha && escolha.cabe && escolha.sobreposicao === 0) { return; }
        }

        // Precisa rolar: posiciona o conjunto (alvo + balão) na área útil.
        var conjunto = alvoAltura + margem + (vw <= 760 ? bh + 12 : bh);
        var destinoTopo;
        if (vw <= 760) {
            // no celular o balão ocupa a parte de baixo: o alvo fica logo abaixo do cabeçalho
            destinoTopo = topoFixo + 16;
        } else if (conjunto <= util) {
            destinoTopo = topoFixo + 10 + (util - conjunto) / 2;
        } else {
            destinoTopo = topoFixo + 16;               // não cabem os dois: prioriza o alvo
        }

        var delta = alvoTopo - destinoTopo;
        if (Math.abs(delta) < 4) { return; }
        var destino = Math.max(window.pageYOffset + delta, 0);
        if (estado) { estado.rolandoAte = Date.now() + 650; }   // janela da rolagem suave
        try {
            window.scrollTo({ top: destino, behavior: 'smooth' });
        } catch (e) {
            window.scrollTo(0, destino);
        }
    }

    function pintarPasso(passo, indice, alvo) {
        var total = estado.passos.length;
        elos.contador.textContent = 'Passo ' + (indice + 1) + ' de ' + total;
        elos.titulo.textContent = passo.titulo || '';
        elos.texto.innerHTML = passo.texto || '';
        elos.progresso.style.width = Math.round(((indice + 1) / total) * 100) + '%';
        elos.anterior.style.display = indice === 0 ? 'none' : '';
        elos.proximo.textContent = indice === total - 1 ? 'Concluir' : 'Próximo';

        // Passo que espera uma ação real do usuário
        if (passo.avancarEm && alvo) {
            var seletor = passo.avancarEm.seletor;
            var elAcao = seletor ? document.querySelector(seletor) : alvo;
            if (elAcao) {
                elos.dica.style.display = 'block';
                elos.dica.textContent = passo.avancarEm.dica || 'Faça a ação destacada para continuar.';
                elos.proximo.classList.add('guia-os__btn--fantasma');
                ouvir(elAcao, passo.avancarEm.evento || 'click', function () {
                    window.setTimeout(function () {
                        if (estado && estado.indice === indice) { proximo(); }
                    }, passo.avancarEm.atraso || 450);
                });
            }
        } else {
            elos.dica.style.display = 'none';
            elos.proximo.classList.remove('guia-os__btn--fantasma');
        }

        elos.raiz.classList.add('guia-os--ativo');
        elos.balao.classList.remove('guia-os__balao--entrando');
        void elos.balao.offsetWidth;
        elos.balao.classList.add('guia-os__balao--entrando');
        posicionar();
        // Não roubar o foco de quem está digitando (nem de diálogos abertos).
        var ativo = document.activeElement;
        var focadoEmCampo = ativo && ativo.tagName &&
            ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(ativo.tagName.toUpperCase()) >= 0;
        if (!focadoEmCampo && !dialogoAberto()) {
            try { elos.proximo.focus({ preventScroll: true }); } catch (e) { elos.proximo.focus(); }
        }
    }

    function proximo() {
        if (!estado) { return; }
        var passo = estado.passos[estado.indice];
        if (typeof passo.aoSair === 'function') { passo.aoSair(estado.alvoAtual, api); }

        // passo que leva a outra página do sistema
        if (passo.irPara) {
            var retomar = passo.retomar || {};
            try {
                window.sessionStorage.setItem(CHAVE_PENDENTE, JSON.stringify({
                    guia: retomar.guia || estado.nome,
                    indice: retomar.indice || 0
                }));
            } catch (e) {}
            window.location.href = passo.irPara;
            return;
        }
        estado.direcao = 1;
        mostrarPasso(estado.indice + 1);
    }

    function anterior() {
        if (!estado) { return; }
        estado.direcao = -1;
        mostrarPasso(estado.indice - 1);
    }

    function salvarProgresso() {
        if (!estado) { return; }
        guardar('progresso.' + estado.nome, String(estado.indice));
    }

    function concluir() {
        if (!estado) { return; }
        var nome = estado.nome;
        var opcoes = estado.opcoes || {};
        guardar('concluido.' + nome, VERSAO);
        remover('progresso.' + nome);
        parar();
        if (opcoes.aoConcluir) { return opcoes.aoConcluir(); }
        mensagemFinal(opcoes.mensagemFinal);
    }

    /* O overlay do guia usa z-index alto; os diálogos do SweetAlert2 precisam
       ficar acima dele, senão aparecem "por trás" e não recebem cliques. */
    function acimaDoGuia() {
        var caixas = document.querySelectorAll('.swal2-container');
        for (var i = 0; i < caixas.length; i++) {
            caixas[i].style.zIndex = '30000';
        }
    }

    function mensagemFinal(msg) {
        var texto = msg || 'Guia concluído! Você pode reabri-lo quando quiser pelo botão de ajuda “?”, no canto inferior direito.';
        if (window.Swal && window.Swal.fire) {
            window.Swal.fire({ icon: 'success', title: 'Tudo certo!', text: texto,
                confirmButtonText: 'Fechar', didOpen: acimaDoGuia, onOpen: acimaDoGuia });
        } else {
            window.alert(texto);
        }
    }

    function confirmarSaida() {
        if (!estado) { return; }
        var finalizar = function () {
            guardar('dispensado.' + estado.nome, VERSAO);
            parar();
        };
        if (window.Swal && window.Swal.fire) {
            window.Swal.fire({
                icon: 'question',
                title: 'Sair do guia?',
                text: 'Você pode retomar depois pelo botão de ajuda “?”.',
                showCancelButton: true,
                confirmButtonText: 'Sair',
                cancelButtonText: 'Continuar no guia',
                didOpen: acimaDoGuia,
                onOpen: acimaDoGuia            // SweetAlert2 antigo (v9)
            }).then(function (res) { if (res.value || res.isConfirmed) { finalizar(); } });
        } else if (window.confirm('Deseja sair do guia?')) {
            finalizar();
        }
    }

    function parar() {
        limparOuvintes();
        if (rafId) { window.cancelAnimationFrame(rafId); rafId = null; }
        if (elos.raiz) { elos.raiz.classList.remove('guia-os--ativo', 'guia-os--bloqueado'); }
        document.body.classList.remove('guia-os-aberto');
        estado = null;
    }

    /* ============================================================
     * Botão flutuante de ajuda
     * ========================================================== */
    var botaoAjuda = { nome: null, rotulo: null };

    function montarBotaoAjuda(nome, rotulo) {
        var btn = document.querySelector('.guia-os-ajuda');
        if (!btn) {
            btn = criar('button', 'guia-os-ajuda', document.body);
            btn.type = 'button';
            btn.innerHTML = '<span class="guia-os-ajuda__icone">?</span>'
                + '<span class="guia-os-ajuda__rotulo"></span>';
            // O handler lê sempre o guia corrente, para que o botão possa trocar
            // de função conforme o contexto da tela (ex.: janela de pagamento).
            btn.addEventListener('click', function () {
                if (botaoAjuda.nome) { iniciar(botaoAjuda.nome, { reiniciar: true }); }
            });
            btn.style.bottom = (alturaMenuInferior() + 18) + 'px';
        }
        definirBotaoAjuda(nome, rotulo);
        return btn;
    }

    /* Troca o guia (e o rótulo) do botão flutuante de ajuda. */
    function definirBotaoAjuda(nome, rotulo) {
        var btn = document.querySelector('.guia-os-ajuda');
        if (!btn) { return montarBotaoAjuda(nome, rotulo); }
        if (!guias[nome]) { return btn; }

        botaoAjuda.nome = nome;
        botaoAjuda.rotulo = rotulo || (guias[nome].opcoes && guias[nome].opcoes.rotuloAjuda) || 'Guia da tela';

        var alvo = btn.querySelector('.guia-os-ajuda__rotulo');
        if (alvo && alvo.textContent !== botaoAjuda.rotulo) {
            alvo.textContent = botaoAjuda.rotulo;
            btn.classList.remove('guia-os-ajuda--trocando');
            void btn.offsetWidth;
            btn.classList.add('guia-os-ajuda--trocando');
        }
        btn.setAttribute('title', botaoAjuda.rotulo);
        btn.setAttribute('aria-label', botaoAjuda.rotulo);
        return btn;
    }

    /* ============================================================
     * API pública
     * ========================================================== */
    function registrar(nome, passos, opcoes) {
        guias[nome] = { passos: passos || [], opcoes: opcoes || {} };
        if (guias[nome].opcoes.botaoAjuda !== false) {
            aoPronto(function () { montarBotaoAjuda(nome, guias[nome].opcoes.rotuloAjuda); });
        }
        return api;
    }

    function iniciar(nome, cfg) {
        cfg = cfg || {};
        var g = guias[nome];
        if (!g || !g.passos.length) { return false; }
        montarOverlay();
        parar();

        var indice = cfg.indice || 0;
        if (!cfg.reiniciar) {
            var salvo = ler('progresso.' + nome);
            if (salvo !== null && !isNaN(parseInt(salvo, 10))) { indice = parseInt(salvo, 10); }
        }
        estado = { nome: nome, passos: g.passos, opcoes: g.opcoes, indice: 0, direcao: 1, alvoAtual: null };
        document.body.classList.add('guia-os-aberto');
        // Por padrão a página continua totalmente utilizável (o usuário pode
        // digitar e clicar em qualquer campo). Com bloquearFundo: true, apenas
        // o elemento destacado aceita cliques.
        elos.raiz.classList.toggle('guia-os--bloqueado', g.opcoes.bloquearFundo === true);
        mostrarPasso(indice);
        if (!rafId) { laco(); }
        return true;
    }

    function autoIniciar(nome, cfg) {
        cfg = cfg || {};
        if (estado) { return false; }                                  // já há um guia em execução
        if (jaConcluido(nome) || ler('dispensado.' + nome)) { return false; }
        window.setTimeout(function () {
            if (estado) { return; }                                    // usuário abriu outro guia nesse meio-tempo
            iniciar(nome, { reiniciar: true });
        }, cfg.atraso || 700);
        return true;
    }

    function emExecucao() {
        return estado ? { nome: estado.nome, indice: estado.indice } : null;
    }

    function jaConcluido(nome) { return ler('concluido.' + nome) === VERSAO; }
    function esquecer(nome) {
        remover('concluido.' + nome); remover('dispensado.' + nome); remover('progresso.' + nome);
    }
    function esquecerTudo() {
        try {
            var apagar = [];
            for (var i = 0; i < window.localStorage.length; i++) {
                var k = window.localStorage.key(i);
                if (k && k.indexOf(PREFIXO) === 0) { apagar.push(k); }
            }
            apagar.forEach(function (k) { window.localStorage.removeItem(k); });
        } catch (e) {}
    }

    function retomarPendente() {
        var bruto = null;
        try { bruto = window.sessionStorage.getItem(CHAVE_PENDENTE); } catch (e) {}
        if (!bruto) { return false; }
        try { window.sessionStorage.removeItem(CHAVE_PENDENTE); } catch (e) {}
        var dados;
        try { dados = JSON.parse(bruto); } catch (e) { return false; }
        if (!dados || !guias[dados.guia]) { return false; }
        window.setTimeout(function () {
            iniciar(dados.guia, { indice: dados.indice || 0, reiniciar: true });
        }, 500);
        return true;
    }

    /* Observa a abertura/fechamento de um modal do Bootstrap. Usa os eventos do
       jQuery quando disponíveis e, se não houver, cai para um MutationObserver
       na classe do elemento — funciona com Bootstrap 4 (.show) e 3 (.in). */
    function aoAbrirModal(seletor, aoAbrir, aoFechar) {
        var alvo = document.querySelector(seletor);
        if (!alvo) { return false; }

        /* Classes usadas pelas janelas do Atlas:
           - Bootstrap 4/5 → .show      - Bootstrap 3 → .in
           - diálogos próprios (arquivamento) → .aberto */
        var RE_ABERTO = /(^|\s)(show|in|aberto)(\s|$)/;
        var aberto = RE_ABERTO.test(alvo.className);

        function avaliar(novo) {
            if (novo === aberto) { return; }
            aberto = novo;
            if (novo) { aoAbrir && aoAbrir(alvo); } else { aoFechar && aoFechar(alvo); }
        }

        // Eventos do Bootstrap, quando houver jQuery (respeitam a animação).
        var jq = window.jQuery || window.$;
        if (jq && jq.fn && jq.fn.on) {
            jq(alvo).on('shown.bs.modal', function () { avaliar(true); });
            jq(alvo).on('hidden.bs.modal', function () { avaliar(false); });
        }

        // Observador da classe: cobre as janelas que não são do Bootstrap.
        if (window.MutationObserver) {
            new window.MutationObserver(function () {
                var agora = RE_ABERTO.test(alvo.className);
                if (agora === aberto) { return; }
                window.setTimeout(function () {
                    avaliar(RE_ABERTO.test(alvo.className));
                }, 260);                       // espera a animação da janela
            }).observe(alvo, { attributes: true, attributeFilter: ['class'] });
        }
        return true;
    }

    function aoPronto(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    // Qualquer elemento com data-guia="nome" abre o guia correspondente
    aoPronto(function () {
        document.addEventListener('click', function (ev) {
            var alvo = ev.target.closest ? ev.target.closest('[data-guia]') : null;
            if (!alvo) { return; }
            ev.preventDefault();
            iniciar(alvo.getAttribute('data-guia'), { reiniciar: true });
        });
        window.setTimeout(retomarPendente, 60);
    });

    window.addEventListener('resize', function () { if (estado) { posicionar(); } });

    var api = {
        versao: VERSAO,
        registrar: registrar,
        iniciar: iniciar,
        autoIniciar: autoIniciar,
        proximo: proximo,
        anterior: anterior,
        parar: parar,
        emExecucao: emExecucao,
        aoAbrirModal: aoAbrirModal,
        definirBotaoAjuda: definirBotaoAjuda,
        botaoAjudaAtual: function () { return botaoAjuda.nome; },
        jaConcluido: jaConcluido,
        esquecer: esquecer,
        esquecerTudo: esquecerTudo
    };

    window.GuiaOS = api;

})(window, document);
