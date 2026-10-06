/* =====================================================================
   Atlas · Arquivamento Digital — digitalização direta do scanner (TWAIN).

   O navegador não acessa o scanner. Quem acessa é o TCloud Scanner, app
   instalado na estação, que recebe o pedido pelo link tcloudscan:// e envia
   as páginas para o próprio Atlas (api/scanner.php). Esta tela:

     1. cria o pedido no servidor (api/digitalizacao.php?acao=criar);
     2. abre o link tcloudscan:// com as opções escolhidas (resolução, cor,
        frente e verso, alimentador, janela do driver);
     3. acompanha o pedido e mostra as páginas conforme chegam;
     4. deixa girar, excluir, reordenar (arrastando) e ampliar;
     5. monta o PDF no navegador (pdf-lib) — ou imagens separadas — e entrega
        os arquivos para a fila de anexos do cadastro, como se o usuário os
        tivesse escolhido no computador.

   Uso:
     ArqScanner.iniciar({ csrf, aoAnexar: function (arquivos) { ... } });
     ArqScanner.abrir();
   ===================================================================== */
(function (global) {
  'use strict';

  var cfg = {
    csrf: '',
    endpoint: 'api/digitalizacao.php',
    pdfLib: 'assets/vendor/pdf-lib.min.js',
    instalar: '',
    maxBytes: 0,
    container: null,
    aoAnexar: function () {}
  };

  var CHAVE_PREF = 'arq.scanner.opcoes';
  var ESPERA_ESTACAO_MS = 12000;

  /* ---------------- estado ---------------- */
  var token = '';
  var linkBase = '';
  var paginas = [];          // ordem atual: [{n, tipo, dpi, giroSrv, giro, largura, altura, bytes}]
  var conhecidas = {};       // n => true (já vistas, mesmo se excluídas)
  var estadoSrv = '';
  var ultimoContato = 0;
  var lancadoEm = 0;
  var contatoNoLancamento = 0;
  var estadoNoLancamento = '';
  var timer = null;
  var aberto = false;
  var ocupado = false;
  var el = {};

  function $(s, raiz) { return (raiz || document).querySelector(s); }
  function esc(v) {
    return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function bytes(b) {
    var u = ['B', 'KB', 'MB', 'GB'], i = 0;
    while (b >= 1024 && i < 3) { b /= 1024; i++; }
    return b.toFixed(i ? 1 : 0).replace('.', ',') + ' ' + u[i];
  }
  function dois(n) { return (n < 10 ? '0' : '') + n; }
  function nomePadrao() {
    var d = new Date();
    return 'Digitalizacao_' + d.getFullYear() + '-' + dois(d.getMonth() + 1) + '-' + dois(d.getDate()) +
      '_' + dois(d.getHours()) + 'h' + dois(d.getMinutes()) + 'm' + dois(d.getSeconds());
  }
  function aviso(i, t, x) { return global.ArqDlg ? ArqDlg.aviso(i, t, x) : Promise.resolve(); }

  function api(acao, dados, metodo) {
    var url = cfg.endpoint + '?acao=' + encodeURIComponent(acao);
    var init = {
      method: metodo || 'GET', credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': cfg.csrf }
    };
    if (init.method === 'POST') {
      var fd = new FormData();
      Object.keys(dados || {}).forEach(function (k) { fd.append(k, dados[k]); });
      init.body = fd;
    } else if (dados) {
      Object.keys(dados).forEach(function (k) { url += '&' + k + '=' + encodeURIComponent(dados[k]); });
    }
    return fetch(url, init).then(function (r) {
      return r.json().catch(function () { return { ok: false, erro: 'Resposta inválida do servidor (' + r.status + ').' }; })
        .then(function (j) {
          if (r.status === 401 && j && j.redirect) {
            var e = new Error('Sessão expirada. Faça login novamente.'); e.redirect = j.redirect; throw e;
          }
          if (!j || !j.ok) { throw new Error((j && j.erro) || 'Falha na comunicação com o servidor.'); }
          return j;
        });
    });
  }

  /* ---------------- preferências (só conveniência; pode falhar) ---------------- */
  function lerPref() {
    try { return JSON.parse(global.localStorage.getItem(CHAVE_PREF) || '{}') || {}; } catch (e) { return {}; }
  }
  function gravarPref(o) {
    try { global.localStorage.setItem(CHAVE_PREF, JSON.stringify(o)); } catch (e) { /* sem armazenamento */ }
  }
  function corEscolhida() {
    var b = el.cor.querySelector('[aria-pressed=true]');
    return b ? b.getAttribute('data-v') : 'cinza';
  }
  function opcoes() {
    return {
      dpi: parseInt(el.dpi.value, 10) || 300,
      cor: corEscolhida(),
      alimentacao: el.alim.value,
      duplex: el.duplex.checked,
      interface: el.ui.checked
    };
  }

  /* ================================================================
     Montagem da janela
     ================================================================ */
  function montar() {
    if (el.fundo) { return; }
    var raiz = cfg.container || document.querySelector('.arq') || document.body;
    var f = document.createElement('div');
    f.className = 'arq-fundo arq-scan';
    f.setAttribute('role', 'dialog');
    f.setAttribute('aria-modal', 'true');
    f.setAttribute('aria-labelledby', 'arq-scan-titulo');
    f.innerHTML =
      '<div class="arq-dialogo largo">' +
        '<div class="arq-dlg-topo">' +
          '<div class="arq-icone-titulo"><i class="fa fa-print"></i></div>' +
          '<h2 id="arq-scan-titulo">Digitalizar do scanner</h2>' +
          '<span class="arq-scan-chip" data-scan="chip">Pronto</span>' +
          '<button type="button" class="arq-fechar" data-scan="fechar" title="Fechar"><i class="fa fa-times"></i></button>' +
        '</div>' +
        '<div class="arq-dlg-corpo arq-scan-corpo">' +
          '<aside class="arq-scan-lado">' +
            '<div class="arq-scan-bloco">' +
              '<label class="arq-rot" for="arq-scan-dpi">Resolução</label>' +
              '<select id="arq-scan-dpi" data-scan="dpi">' +
                '<option value="150">150 dpi — rascunho, arquivo leve</option>' +
                '<option value="200">200 dpi — documentos comuns</option>' +
                '<option value="300" selected>300 dpi — padrão de acervo</option>' +
                '<option value="400">400 dpi — letras miúdas</option>' +
                '<option value="600">600 dpi — livros antigos, selos</option>' +
              '</select>' +
            '</div>' +
            '<div class="arq-scan-bloco">' +
              '<label class="arq-rot">Cor</label>' +
              '<div class="arq-seg arq-scan-seg" data-scan="cor" role="group" aria-label="Modo de cor">' +
                '<button type="button" data-v="cor" aria-pressed="false">Colorido</button>' +
                '<button type="button" data-v="cinza" aria-pressed="true">Cinza</button>' +
                '<button type="button" data-v="pb" aria-pressed="false">P&amp;B</button>' +
              '</div>' +
            '</div>' +
            '<div class="arq-scan-bloco">' +
              '<label class="arq-rot" for="arq-scan-alim">Alimentação</label>' +
              '<select id="arq-scan-alim" data-scan="alim">' +
                '<option value="auto">Automática (o que o scanner tiver)</option>' +
                '<option value="alimentador">Alimentador de folhas (ADF)</option>' +
                '<option value="mesa">Mesa de vidro</option>' +
              '</select>' +
            '</div>' +
            '<label class="arq-scan-check"><input type="checkbox" data-scan="duplex"> <span><b>Frente e verso</b><small>Para scanners com duplex.</small></span></label>' +
            '<label class="arq-scan-check"><input type="checkbox" data-scan="ui"> <span><b>Abrir a janela do driver</b><small>Para ajustes que só o fabricante oferece (recorte, brilho, perfis).</small></span></label>' +

            '<button type="button" class="arq-btn arq-btn-p arq-scan-go" data-scan="digitalizar">' +
              '<i class="fa fa-play"></i> <span>Digitalizar</span></button>' +

            '<div class="arq-scan-status" data-scan="status" aria-live="polite">' +
              '<i class="fa fa-info-circle"></i><div><b>Coloque o documento no scanner</b>' +
              '<span>e clique em Digitalizar. O TCloud Scanner abre e envia as páginas para cá.</span></div>' +
            '</div>' +

            '<div class="arq-scan-ajuda arq-esconde" data-scan="ajuda">' +
              '<b><i class="fa fa-question-circle"></i> O TCloud Scanner não respondeu</b>' +
              '<p>Se o navegador perguntou se pode abrir o <b>TCloud Scanner</b>, confirme. ' +
              'Se nada apareceu, ele ainda não está instalado nesta estação. Abra o PowerShell e rode:</p>' +
              '<div class="arq-scan-cmd"><code data-scan="cmd"></code>' +
              '<button type="button" class="arq-btn arq-btn-sm arq-btn-ic" data-scan="copiar" title="Copiar comando"><i class="fa fa-clone"></i></button></div>' +
              '<p class="arq-scan-mini">Depois de instalar, clique em Digitalizar de novo.</p>' +
            '</div>' +
          '</aside>' +

          '<section class="arq-scan-paginas">' +
            '<div class="arq-scan-barra">' +
              '<div class="arq-scan-conta"><b data-scan="conta">0</b> <span data-scan="conta-rot">páginas</span> <em data-scan="peso"></em></div>' +
              '<div class="arq-scan-ferr">' +
                '<button type="button" class="arq-btn arq-btn-sm" data-scan="girar-todas" title="Girar todas 90° à direita" disabled><i class="fa fa-repeat"></i> <span>Girar todas</span></button>' +
                '<button type="button" class="arq-btn arq-btn-sm" data-scan="inverter" title="Inverter a ordem (útil quando o alimentador entrega de trás para frente)" disabled><i class="fa fa-sort-amount-desc"></i> <span>Inverter</span></button>' +
                '<button type="button" class="arq-btn arq-btn-sm arq-btn-perigo" data-scan="limpar" title="Excluir todas as páginas" disabled><i class="fa fa-trash"></i></button>' +
              '</div>' +
            '</div>' +
            '<div class="arq-scan-grade" data-scan="grade"></div>' +
            '<div class="arq-scan-vazio" data-scan="vazio">' +
              '<i class="fa fa-file-image-o"></i>' +
              '<b>Nenhuma página ainda</b>' +
              '<span>As páginas aparecem aqui conforme o scanner digitaliza. Depois dá para girar, excluir e arrastar para mudar a ordem.</span>' +
            '</div>' +
          '</section>' +
        '</div>' +
        '<div class="arq-dlg-pe arq-scan-pe">' +
          '<div class="arq-scan-saida">' +
            '<input type="text" data-scan="nome" maxlength="120" aria-label="Nome do arquivo" placeholder="Nome do arquivo">' +
            '<select data-scan="formato" aria-label="Formato">' +
              '<option value="pdf">PDF único</option>' +
              '<option value="img">Uma imagem por página</option>' +
            '</select>' +
          '</div>' +
          '<button type="button" class="arq-btn" data-scan="cancelar">Cancelar</button>' +
          '<button type="button" class="arq-btn arq-btn-p" data-scan="anexar" disabled><i class="fa fa-paperclip"></i> <span>Anexar ao arquivamento</span></button>' +
        '</div>' +
      '</div>' +
      '<div class="arq-scan-zoom arq-esconde" data-scan="zoom"><img alt=""><button type="button" class="arq-fechar" title="Fechar"><i class="fa fa-times"></i></button></div>';

    raiz.appendChild(f);
    el.fundo = f;
    ['chip', 'fechar', 'dpi', 'cor', 'alim', 'duplex', 'ui', 'digitalizar', 'status', 'ajuda', 'cmd', 'copiar',
     'conta', 'conta-rot', 'peso', 'girar-todas', 'inverter', 'limpar', 'grade', 'vazio', 'nome', 'formato',
     'cancelar', 'anexar', 'zoom'].forEach(function (k) {
      el[k.replace(/-(\w)/g, function (m, c) { return c.toUpperCase(); })] = f.querySelector('[data-scan="' + k + '"]');
    });
    el.cmd.textContent = cfg.instalar;

    // preferências salvas
    var p = lerPref();
    if (p.dpi) { el.dpi.value = String(p.dpi); }
    if (p.cor) { marcarCor(p.cor); }
    if (p.alimentacao) { el.alim.value = p.alimentacao; }
    el.duplex.checked = !!p.duplex;
    el.ui.checked = !!p.interface;
    if (p.formato) { el.formato.value = p.formato; }

    ligarEventos();
  }

  function marcarCor(v) {
    Array.prototype.forEach.call(el.cor.querySelectorAll('button'), function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-v') === v ? 'true' : 'false');
    });
  }

  function salvarPref() {
    var o = opcoes();
    o.formato = el.formato.value;
    gravarPref(o);
  }

  /* ================================================================
     Eventos
     ================================================================ */
  function ligarEventos() {
    el.fechar.addEventListener('click', fecharPedindo);
    el.cancelar.addEventListener('click', fecharPedindo);
    el.fundo.addEventListener('mousedown', function (e) { if (e.target === el.fundo) { fecharPedindo(); } });
    document.addEventListener('keydown', function (e) {
      if (!aberto || e.key !== 'Escape') { return; }
      if (!el.zoom.classList.contains('arq-esconde')) { el.zoom.classList.add('arq-esconde'); return; }
      if (document.querySelector('.swal2-container')) { return; }
      fecharPedindo();
    });

    el.cor.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-v]');
      if (b) { marcarCor(b.getAttribute('data-v')); salvarPref(); }
    });
    [el.dpi, el.alim, el.duplex, el.ui, el.formato].forEach(function (c) { c.addEventListener('change', salvarPref); });

    el.digitalizar.addEventListener('click', digitalizar);
    el.copiar.addEventListener('click', function () { copiar(cfg.instalar); });

    el.girarTodas.addEventListener('click', function () {
      paginas.forEach(function (p) { p.giro = (p.giro + 90) % 360; });
      pintar();
    });
    el.inverter.addEventListener('click', function () { paginas.reverse(); pintar(); });
    el.limpar.addEventListener('click', function () {
      if (!paginas.length) { return; }
      ArqDlg.confirmar('Excluir todas as páginas?', 'As ' + paginas.length + ' páginas digitalizadas serão descartadas.', 'Excluir', true)
        .then(function (ok) { if (ok) { paginas = []; pintar(); } });
    });

    el.anexar.addEventListener('click', anexar);

    // ações nas miniaturas
    el.grade.addEventListener('click', function (e) {
      var b = e.target.closest('[data-acao]');
      var card = e.target.closest('.arq-scan-pag');
      if (!card) { return; }
      var i = indicePorN(parseInt(card.getAttribute('data-n'), 10));
      if (i < 0) { return; }
      if (!b) { ampliar(paginas[i]); return; }
      var acao = b.getAttribute('data-acao');
      if (acao === 'esq') { paginas[i].giro = (paginas[i].giro + 270) % 360; }
      if (acao === 'dir') { paginas[i].giro = (paginas[i].giro + 90) % 360; }
      if (acao === 'del') { paginas.splice(i, 1); }
      if (acao === 'ver') { ampliar(paginas[i]); return; }
      pintar();
    });

    // reordenar arrastando
    var arrastando = null;
    el.grade.addEventListener('dragstart', function (e) {
      var card = e.target.closest('.arq-scan-pag');
      if (!card) { return; }
      arrastando = parseInt(card.getAttribute('data-n'), 10);
      card.classList.add('arrastando');
      try { e.dataTransfer.setData('text/plain', String(arrastando)); e.dataTransfer.effectAllowed = 'move'; } catch (x) {}
    });
    el.grade.addEventListener('dragend', function () {
      arrastando = null;
      Array.prototype.forEach.call(el.grade.querySelectorAll('.arrastando, .alvo'), function (c) {
        c.classList.remove('arrastando'); c.classList.remove('alvo');
      });
    });
    el.grade.addEventListener('dragover', function (e) {
      if (arrastando === null) { return; }
      e.preventDefault();
      var card = e.target.closest('.arq-scan-pag');
      Array.prototype.forEach.call(el.grade.querySelectorAll('.alvo'), function (c) { if (c !== card) { c.classList.remove('alvo'); } });
      if (card && parseInt(card.getAttribute('data-n'), 10) !== arrastando) { card.classList.add('alvo'); }
    });
    el.grade.addEventListener('drop', function (e) {
      if (arrastando === null) { return; }
      e.preventDefault();
      var card = e.target.closest('.arq-scan-pag');
      if (!card) { return; }
      var de = indicePorN(arrastando);
      var para = indicePorN(parseInt(card.getAttribute('data-n'), 10));
      if (de < 0 || para < 0 || de === para) { return; }
      var item = paginas.splice(de, 1)[0];
      paginas.splice(para, 0, item);
      pintar();
    });

    el.zoom.addEventListener('click', function () { el.zoom.classList.add('arq-esconde'); });
  }

  function indicePorN(n) {
    for (var i = 0; i < paginas.length; i++) { if (paginas[i].n === n) { return i; } }
    return -1;
  }

  function urlPagina(p) {
    return cfg.endpoint + '?acao=pagina&t=' + encodeURIComponent(token) + '&n=' + p.n;
  }

  function copiar(txt) {
    var feito = function () { if (global.ArqDlg) { ArqDlg.toast('success', 'Comando copiado'); } };
    if (navigator.clipboard && global.isSecureContext) {
      navigator.clipboard.writeText(txt).then(feito, function () {});
      return;
    }
    var ta = document.createElement('textarea');
    ta.value = txt; ta.style.position = 'fixed'; ta.style.left = '-9999px';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); feito(); } catch (e) {}
    document.body.removeChild(ta);
  }

  /* ================================================================
     Pintura
     ================================================================ */
  function pintar() {
    var total = 0;
    var html = paginas.map(function (p, i) {
      total += p.bytes || 0;
      var giro = (p.giroSrv + p.giro) % 360;
      var deitada = giro === 90 || giro === 270;
      // prop = altura/largura da página como ela vai sair (já girada)
      var prop = deitada ? (p.largura / Math.max(1, p.altura)) : (p.altura / Math.max(1, p.largura));
      prop = Math.min(2.2, Math.max(0.35, prop));
      // Girada de 90°, a imagem ocupa uma caixa "deitada" dentro da folha:
      // largura = altura da folha e vice-versa (em % da largura/altura da folha).
      var dim = deitada
        ? 'width:' + (prop * 100).toFixed(2) + '%;height:' + (100 / prop).toFixed(2) + '%;'
        : 'width:100%;height:100%;';
      return '<figure class="arq-scan-pag" draggable="true" data-n="' + p.n + '" title="Arraste para mudar a ordem">' +
        '<div class="arq-scan-folha" style="padding-top:' + (prop * 100).toFixed(2) + '%">' +
          '<img src="' + esc(urlPagina(p)) + '" alt="Página ' + (i + 1) + '" loading="lazy" draggable="false" ' +
          'style="' + dim + 'transform:translate(-50%,-50%) rotate(' + giro + 'deg)">' +
        '</div>' +
        '<figcaption>' +
          '<span class="arq-scan-num">' + (i + 1) + '</span>' +
          '<span class="arq-scan-acoes">' +
            '<button type="button" data-acao="esq" title="Girar à esquerda"><i class="fa fa-undo"></i></button>' +
            '<button type="button" data-acao="dir" title="Girar à direita"><i class="fa fa-repeat"></i></button>' +
            '<button type="button" data-acao="ver" title="Ampliar"><i class="fa fa-search-plus"></i></button>' +
            '<button type="button" data-acao="del" title="Excluir página" class="perigo"><i class="fa fa-trash"></i></button>' +
          '</span>' +
        '</figcaption>' +
      '</figure>';
    }).join('');
    el.grade.innerHTML = html;
    var n = paginas.length;
    el.conta.textContent = n;
    el.contaRot.textContent = n === 1 ? 'página' : 'páginas';
    el.peso.textContent = n ? '· ' + bytes(total) + ' em imagens' : '';
    el.vazio.style.display = n ? 'none' : 'flex';
    el.grade.style.display = n ? 'grid' : 'none';
    el.anexar.disabled = !n || ocupado;
    el.girarTodas.disabled = el.inverter.disabled = el.limpar.disabled = !n || ocupado;
    el.digitalizar.querySelector('span').textContent = n ? 'Digitalizar mais páginas' : 'Digitalizar';
  }

  function ampliar(p) {
    var img = el.zoom.querySelector('img');
    img.src = urlPagina(p);
    var giro = (p.giroSrv + p.giro) % 360;
    var deitada = giro === 90 || giro === 270;
    img.style.transform = 'rotate(' + giro + 'deg)';
    img.style.maxWidth = deitada ? '88vh' : '92vw';
    img.style.maxHeight = deitada ? '92vw' : '88vh';
    el.zoom.classList.remove('arq-esconde');
  }

  /* ---------------- status ---------------- */
  var ICONES = { info: 'fa-info-circle', espera: 'fa-spinner fa-spin', ok: 'fa-check-circle', erro: 'fa-exclamation-triangle' };
  function status(tipo, titulo, texto) {
    el.status.className = 'arq-scan-status ' + tipo;
    el.status.innerHTML = '<i class="fa ' + ICONES[tipo] + '"></i><div><b>' + esc(titulo) + '</b>' +
      (texto ? '<span>' + esc(texto) + '</span>' : '') + '</div>';
  }
  function chip(txt, tipo) {
    el.chip.textContent = txt;
    el.chip.className = 'arq-scan-chip ' + (tipo || '');
  }

  /* ================================================================
     Pedido e acompanhamento
     ================================================================ */
  var criando = null;
  function garantirPedido() {
    if (token) { return Promise.resolve(); }
    if (criando) { return criando; }
    criando = api('criar', { opcoes: JSON.stringify(opcoes()) }, 'POST').then(function (j) {
      criando = null;
      if (!aberto) {
        // Janela fechada enquanto o pedido era criado: não deixa sobra no servidor.
        api('descartar', { t: j.token }, 'POST').catch(function () {});
        return;
      }
      token = j.token;
      linkBase = j.link;
    }, function (e) { criando = null; throw e; });
    return criando;
  }

  /** Pré-cria o pedido ao abrir, para que o clique em Digitalizar abra o
      link na hora — o navegador só abre tcloudscan:// a partir de um clique. */
  function preparar() {
    if (!aberto) { return; }
    garantirPedido().then(acompanhar).catch(function (e) {
      status('erro', 'Não foi possível preparar a digitalização', e.message);
      if (e.redirect) { setTimeout(function () { location.href = e.redirect; }, 1500); }
    });
  }

  function linkCompleto() {
    var o = opcoes();
    return linkBase +
      '&dpi=' + o.dpi + '&cor=' + encodeURIComponent(o.cor) + '&alim=' + encodeURIComponent(o.alimentacao) +
      '&duplex=' + (o.duplex ? 1 : 0) + '&ui=' + (o.interface ? 1 : 0);
  }

  function lancar(link) {
    ArqScanner.lancando = true;
    var a = document.createElement('a');
    a.href = link;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { ArqScanner.lancando = false; }, 2500);
  }

  function digitalizar() {
    if (ocupado) { return; }
    if (!token) {
      // Pedido ainda não veio (rede lenta): cria e pede um segundo clique,
      // porque sem o gesto do usuário o navegador não abre o link.
      status('espera', 'Preparando…', 'Aguarde um instante e clique de novo.');
      preparar();
      return;
    }
    salvarPref();
    el.ajuda.classList.add('arq-esconde');
    lancadoEm = Date.now();
    contatoNoLancamento = ultimoContato;
    estadoNoLancamento = estadoSrv;
    status('espera', 'Abrindo o TCloud Scanner…', 'Se o navegador perguntar, permita abrir o aplicativo.');
    chip('Aguardando estação', 'espera');
    lancar(linkCompleto());
    acompanhar();
  }

  function acompanhar() {
    if (timer) { clearTimeout(timer); timer = null; }
    if (!aberto || !token) { return; }
    var consultado = token;
    api('estado', { t: consultado }).then(function (j) {
      // Resposta atrasada de um pedido já anexado/descartado: ignora.
      if (!aberto || consultado !== token) { return; }
      atualizar(j);
    }).catch(function (e) {
      if (!aberto || consultado !== token) { return; }
      if (e.redirect) { status('erro', 'Sessão encerrada', e.message); return; }
      if (/não encontrado|expirado/i.test(e.message)) {
        // Pedido venceu: começa outro na próxima ação, mantendo nada do anterior.
        token = ''; linkBase = '';
        status('erro', 'O pedido de digitalização expirou', 'Clique em Digitalizar para começar outro.');
        chip('Expirado', 'erro');
        preparar();
        return;
      }
    }).then(function () {
      if (aberto && token && !timer) { timer = setTimeout(acompanhar, document.hidden ? 3000 : 1000); }
    });
  }

  function atualizar(j) {
    ultimoContato = j.contato || 0;
    var novas = 0;
    (j.paginas || []).forEach(function (pg) {
      if (conhecidas[pg.n]) { return; }
      conhecidas[pg.n] = true;
      paginas.push({
        n: pg.n, tipo: pg.tipo, dpi: pg.dpi || 300, giroSrv: pg.giro || 0, giro: 0,
        largura: pg.largura, altura: pg.altura, bytes: pg.bytes
      });
      novas++;
    });
    if (novas) {
      pintar();
      var ult = el.grade.lastElementChild;
      if (ult && ult.scrollIntoView) { ult.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
    }

    var est = j.estacao || {};
    var onde = est.scanner ? est.scanner + (est.nome ? ' · ' + est.nome : '') : '';
    var mudou = j.estado !== estadoSrv;
    estadoSrv = j.estado;

    if (lancadoEm && ultimoContato <= contatoNoLancamento) {
      // Link aberto, estação ainda não falou.
      if (Date.now() - lancadoEm > ESPERA_ESTACAO_MS) {
        el.ajuda.classList.remove('arq-esconde');
        status('erro', 'Sem resposta do TCloud Scanner', 'Veja as instruções abaixo.');
        chip('Sem resposta', 'erro');
        lancadoEm = 0;
      }
      return;
    }
    var chegouAgora = ultimoContato > contatoNoLancamento && contatoNoLancamento >= 0;
    if (chegouAgora) { el.ajuda.classList.add('arq-esconde'); }

    var parado = j.estado === 'aguardando' || (j.estado === estadoNoLancamento && !novas);
    if (chegouAgora && j.estado !== 'digitalizando' && parado) {
      // A estação abriu e ainda não começou (ex.: primeira vez, escolhendo o scanner).
      contatoNoLancamento = -1;
      lancadoEm = 0;
      status('espera', 'TCloud Scanner aberto', 'Confirme o scanner na janela do TCloud Scanner para começar.');
      chip('Na estação', 'espera');
    } else if (j.estado === 'digitalizando') {
      lancadoEm = 0;
      status('espera', 'Digitalizando…', (onde ? onde + ' — ' : '') + paginas.length + ' ' + (paginas.length === 1 ? 'página recebida' : 'páginas recebidas'));
      chip('Digitalizando', 'espera');
    } else if (mudou || novas) {
      if (j.estado === 'concluido') {
        lancadoEm = 0;
        status('ok', 'Digitalização concluída', paginas.length
          ? 'Confira as páginas e clique em Anexar. Para continuar, coloque mais folhas e clique em Digitalizar mais páginas.'
          : 'O scanner não entregou nenhuma página. Confira se há papel no alimentador ou na mesa.');
        chip(paginas.length ? 'Concluído' : 'Sem páginas', paginas.length ? 'ok' : 'alerta');
      } else if (j.estado === 'cancelado') {
        lancadoEm = 0;
        status('info', 'Digitalização cancelada na estação', paginas.length ? 'As páginas já recebidas continuam aqui.' : '');
        chip('Cancelado', '');
      } else if (j.estado === 'erro') {
        lancadoEm = 0;
        status('erro', 'O scanner informou um problema', j.mensagem || 'Erro não especificado.');
        chip('Erro', 'erro');
      }
    }
  }

  /* ================================================================
     Anexar: monta os arquivos e entrega para a fila
     ================================================================ */
  function carregarPdfLib() {
    if (global.PDFLib) { return Promise.resolve(); }
    return new Promise(function (ok, falha) {
      var s = document.createElement('script');
      s.src = cfg.pdfLib;
      s.onload = function () { global.PDFLib ? ok() : falha(new Error('pdf-lib não carregou.')); };
      s.onerror = function () { falha(new Error('Não foi possível carregar a biblioteca de PDF (' + cfg.pdfLib + ').')); };
      document.head.appendChild(s);
    });
  }

  function baixar(p) {
    return fetch(urlPagina(p), { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) { throw new Error('Falha ao ler a página ' + p.n + ' do servidor.'); }
      return r.arrayBuffer();
    });
  }

  function emSequencia(lista, fn) {
    return lista.reduce(function (cad, item, i) {
      return cad.then(function (acc) { return fn(item, i).then(function (r) { acc.push(r); return acc; }); });
    }, Promise.resolve([]));
  }

  function nomeArquivo() {
    var n = (el.nome.value || '').trim() || nomePadrao();
    n = n.replace(/\.(pdf|jpe?g|png)$/i, '').replace(/[\\\/:*?"<>|\x00-\x1F]+/g, '_').trim();
    return n || nomePadrao();
  }

  function criarArquivo(partes, nome, tipo) {
    try { return new File(partes, nome, { type: tipo, lastModified: Date.now() }); }
    catch (e) { var b = new Blob(partes, { type: tipo }); b.name = nome; b.lastModified = Date.now(); return b; }
  }

  function montarPdf(nome, progresso) {
    return carregarPdfLib().then(function () {
      var L = global.PDFLib;
      return L.PDFDocument.create().then(function (doc) {
        return emSequencia(paginas, function (p, i) {
          progresso(i + 1, paginas.length);
          return baixar(p).then(function (buf) {
            return p.tipo === 'png' ? doc.embedPng(buf) : doc.embedJpg(buf);
          }).then(function (img) {
            var dpi = p.dpi || 300;
            var w = p.largura * 72 / dpi, h = p.altura * 72 / dpi;
            var pg = doc.addPage([w, h]);
            pg.drawImage(img, { x: 0, y: 0, width: w, height: h });
            var giro = (p.giroSrv + p.giro) % 360;
            if (giro) { pg.setRotation(L.degrees(giro)); }
          });
        }).then(function () {
          doc.setTitle(nome);
          doc.setCreator('TCloud Scanner');
          doc.setProducer('Atlas · Arquivamento Digital');
          doc.setCreationDate(new Date());
          return doc.save();
        });
      }).then(function (bytesPdf) {
        var f = criarArquivo([bytesPdf], nome + '.pdf', 'application/pdf');
        f.arqPaginas = paginas.length;
        return [f];
      });
    });
  }

  /** Imagem girada de verdade (as imagens separadas não têm "rotação de página"). */
  function girarImagem(buf, tipo, graus) {
    if (!graus) { return Promise.resolve(new Blob([buf], { type: tipo === 'png' ? 'image/png' : 'image/jpeg' })); }
    return new Promise(function (ok, falha) {
      var url = URL.createObjectURL(new Blob([buf]));
      var img = new Image();
      img.onload = function () {
        var c = document.createElement('canvas');
        var deitada = graus === 90 || graus === 270;
        c.width = deitada ? img.naturalHeight : img.naturalWidth;
        c.height = deitada ? img.naturalWidth : img.naturalHeight;
        var g = c.getContext('2d');
        g.translate(c.width / 2, c.height / 2);
        g.rotate(graus * Math.PI / 180);
        g.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) { b ? ok(b) : falha(new Error('Falha ao girar a imagem.')); },
          tipo === 'png' ? 'image/png' : 'image/jpeg', 0.9);
      };
      img.onerror = function () { URL.revokeObjectURL(url); falha(new Error('Imagem inválida.')); };
      img.src = url;
    });
  }

  function montarImagens(nome, progresso) {
    var casas = String(paginas.length).length < 2 ? 2 : String(paginas.length).length;
    return emSequencia(paginas, function (p, i) {
      progresso(i + 1, paginas.length);
      return baixar(p).then(function (buf) {
        return girarImagem(buf, p.tipo, (p.giroSrv + p.giro) % 360);
      }).then(function (blob) {
        var num = String(i + 1);
        while (num.length < casas) { num = '0' + num; }
        var f = criarArquivo([blob], nome + '_p' + num + '.' + (p.tipo === 'png' ? 'png' : 'jpg'), blob.type);
        f.arqPaginas = 1;
        return f;
      });
    });
  }

  function anexar() {
    if (ocupado || !paginas.length) { return; }
    ocupado = true;
    pintar();
    el.digitalizar.disabled = true;
    var rot = el.anexar.innerHTML;
    var nome = nomeArquivo();
    var progresso = function (i, t) {
      el.anexar.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Montando ' + i + '/' + t + '…';
    };
    var tarefa = el.formato.value === 'img' ? montarImagens(nome, progresso) : montarPdf(nome, progresso);

    tarefa.then(function (arquivos) {
      if (cfg.maxBytes) {
        var grande = arquivos.filter(function (f) { return f.size > cfg.maxBytes; })[0];
        if (grande) {
          throw new Error('O arquivo montado tem ' + bytes(grande.size) + ' e o limite por anexo é ' + bytes(cfg.maxBytes) +
            '. Diminua a resolução, use "Cinza" em vez de "Colorido" ou divida em mais de um arquivo.');
        }
      }
      cfg.aoAnexar(arquivos);
      var qtd = paginas.length;
      var tokenAntigo = token;
      // Os bytes já estão no navegador: o pedido no servidor pode ir embora.
      api('descartar', { t: tokenAntigo, anexado: '1', paginas: String(qtd) }, 'POST').catch(function () {});
      reiniciar();
      fechar();
      if (global.ArqDlg) {
        ArqDlg.toast('success', qtd + (qtd === 1 ? ' página anexada' : ' páginas anexadas') + ' — salve para enviar');
      }
    }).catch(function (e) {
      aviso('error', 'Não foi possível anexar', e.message);
    }).then(function () {
      ocupado = false;
      el.anexar.innerHTML = rot;
      el.digitalizar.disabled = false;
      if (el.grade) { pintar(); }
    });
  }

  /* ================================================================
     Abrir / fechar
     ================================================================ */
  function reiniciar() {
    if (timer) { clearTimeout(timer); timer = null; }
    token = ''; linkBase = ''; paginas = []; conhecidas = {};
    estadoSrv = ''; ultimoContato = 0; lancadoEm = 0; contatoNoLancamento = 0;
    if (el.nome) { el.nome.value = ''; }
  }

  function abrir() {
    montar();
    aberto = true;
    document.body.classList.add('arq-scan-aberto');
    el.fundo.classList.add('aberto');
    if (!el.nome.value) { el.nome.value = nomePadrao(); }
    if (!paginas.length && !token) {
      status('info', 'Coloque o documento no scanner', 'e clique em Digitalizar. O TCloud Scanner abre e envia as páginas para cá.');
      chip('Pronto', '');
    }
    pintar();
    preparar();
    setTimeout(function () { el.digitalizar.focus(); }, 60);
  }

  function fechar() {
    aberto = false;
    if (timer) { clearTimeout(timer); timer = null; }
    if (el.fundo) { el.fundo.classList.remove('aberto'); }
    document.body.classList.remove('arq-scan-aberto');
  }

  function fecharPedindo() {
    if (ocupado) { return; }
    if (!paginas.length) {
      if (token) { api('descartar', { t: token }, 'POST').catch(function () {}); }
      reiniciar();
      fechar();
      return;
    }
    ArqDlg.confirmar('Descartar a digitalização?',
      'As <b>' + paginas.length + '</b> páginas digitalizadas ainda não foram anexadas e serão perdidas.',
      'Descartar', true).then(function (ok) {
      if (!ok) { return; }
      api('descartar', { t: token }, 'POST').catch(function () {});
      reiniciar();
      fechar();
    });
  }

  var ArqScanner = {
    lancando: false,
    iniciar: function (o) {
      Object.keys(o || {}).forEach(function (k) { cfg[k] = o[k]; });
    },
    abrir: abrir,
    /** Há páginas digitalizadas que ainda não viraram anexo? */
    pendente: function () { return paginas.length > 0; }
  };

  global.ArqScanner = ArqScanner;
})(window);
