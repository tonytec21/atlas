/* ======================================================================
 * ATLAS • protecao.js — 1.0.0
 * Carregado automaticamente em todas as telas pelo seguranca/guarda.php.
 *  - coloca o token da sessão em todo pedido que a tela faz ao próprio
 *    sistema (jQuery/XMLHttpRequest, fetch, formulários, sendBeacon);
 *  - esconde o endereço dos links no canto inferior da tela;
 *  - avisa quando a sessão terminou ou um pedido foi recusado.
 * ====================================================================== */
(function () {
  'use strict';
  if (window.__atlasProtecao) return;
  window.__atlasProtecao = true;

  var C = window.__ATLAS_G || {};
  var TOKEN = C.t || '';
  var CAMPO = '_atlas_token';

  /* janelas abertas fora da moldura (impressões): tira o marcador do endereço */
  try {
    if (/[?&]_atlas_sem_moldura=1/.test(location.search)) {
      var q = location.search.replace(/([?&])_atlas_sem_moldura=1(&|$)/, function (m, a, b) { return b ? a : ''; });
      history.replaceState(history.state, '', location.pathname + (q === '?' ? '' : q) + location.hash);
    }
  } catch (e) {}

  function mesmaOrigem(u) {
    try { return new URL(String(u), location.href).origin === location.origin; } catch (e) { return false; }
  }

  /* ---------------- XMLHttpRequest (jQuery, axios, bibliotecas) ---------------- */
  var xOpen = XMLHttpRequest.prototype.open;
  var xSend = XMLHttpRequest.prototype.send;
  var xSet = XMLHttpRequest.prototype.setRequestHeader;
  XMLHttpRequest.prototype.open = function (metodo, url) {
    this.__atlasMesma = mesmaOrigem(url);
    return xOpen.apply(this, arguments);
  };
  XMLHttpRequest.prototype.send = function () {
    if (this.__atlasMesma) {
      if (TOKEN) { try { xSet.call(this, 'X-Atlas-Token', TOKEN); } catch (e) {} }
      var xhr = this;
      try {
        this.addEventListener('load', function () {
          if (xhr.status === 401 || xhr.status === 403) {
            var m = ''; try { m = xhr.getResponseHeader('X-Atlas-Guarda') || ''; } catch (e) {}
            if (m) avisar(m);
          }
        });
      } catch (e) {}
    }
    return xSend.apply(this, arguments);
  };

  /* ---------------- fetch ---------------- */
  if (window.fetch) {
    var fOrig = window.fetch;
    window.fetch = function (entrada, opcoes) {
      var mesma = false;
      try {
        var url = (typeof entrada === 'string' || entrada instanceof URL) ? String(entrada) : (entrada && entrada.url);
        mesma = mesmaOrigem(url);
        if (mesma && TOKEN) {
          opcoes = Object.assign({}, opcoes || {});
          var h = new Headers(opcoes.headers || (entrada instanceof Request ? entrada.headers : undefined));
          h.set('X-Atlas-Token', TOKEN);
          opcoes.headers = h;
        }
      } catch (e) {}
      var p = fOrig.call(this, entrada, opcoes);
      if (mesma) {
        p.then(function (r) {
          if (r && (r.status === 401 || r.status === 403)) {
            var m = r.headers.get('X-Atlas-Guarda');
            if (m) avisar(m);
          }
        }, function () {});
      }
      return p;
    };
  }

  /* ---------------- sendBeacon ---------------- */
  if (navigator.sendBeacon) {
    var bOrig = navigator.sendBeacon.bind(navigator);
    navigator.sendBeacon = function (url, dados) {
      try {
        if (TOKEN && mesmaOrigem(url)) url = String(url) + (String(url).indexOf('?') < 0 ? '?' : '&') + CAMPO + '=' + encodeURIComponent(TOKEN);
      } catch (e) {}
      return bOrig(url, dados);
    };
  }

  /* ---------------- formulários ---------------- */
  function prepararForm(form, botao) {
    try {
      if (!TOKEN || !form || form.nodeName !== 'FORM') return;
      var metodo = ((botao && botao.getAttribute && botao.getAttribute('formmethod')) || form.getAttribute('method') || 'get').toLowerCase();
      if (metodo !== 'post') return;
      var acao = (botao && botao.getAttribute && botao.getAttribute('formaction')) || form.getAttribute('action') || location.href;
      if (!mesmaOrigem(acao)) return;
      var campo = null;
      for (var i = 0; i < form.elements.length; i++) {
        if (form.elements[i].name === CAMPO) { campo = form.elements[i]; break; }
      }
      if (!campo) {
        campo = document.createElement('input');
        campo.type = 'hidden';
        campo.name = CAMPO;
        /* primeiro campo: formulários enormes podem passar do max_input_vars do PHP, que corta os últimos */
        form.insertBefore(campo, form.firstChild);
      }
      campo.value = TOKEN;
    } catch (e) {}
  }
  document.addEventListener('submit', function (e) { prepararForm(e.target, e.submitter); }, true);
  var sOrig = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function () { prepararForm(this); return sOrig.apply(this, arguments); };
  /* FormData montado a partir de um formulário já leva o campo; o guarda o retira antes de chegar à página. */

  /* ---------------- aviso de sessão/recusa ---------------- */
  var avisado = false;
  function avisar(motivo) {
    if (avisado) return;
    avisado = true;
    var sessao = motivo === 'sessao';
    var texto = sessao
      ? 'Sua sessão terminou. Entre novamente para continuar.'
      : 'Um pedido desta tela foi recusado pela proteção do sistema. Recarregue a página e tente de novo.';
    try {
      var d = document.createElement('div');
      d.setAttribute('role', 'alert');
      d.style.cssText = 'position:fixed;left:50%;bottom:22px;transform:translateX(-50%);z-index:2147483647;max-width:min(560px,calc(100vw - 32px));' +
        'display:flex;gap:14px;align-items:center;padding:12px 16px;border-radius:12px;background:#1e293b;color:#f1f5f9;' +
        'font:500 14px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif;box-shadow:0 12px 40px rgba(0,0,0,.35)';
      var s = document.createElement('span'); s.textContent = texto; d.appendChild(s);
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = sessao ? 'Entrar' : 'Recarregar';
      b.style.cssText = 'flex:none;border:0;border-radius:8px;padding:7px 12px;background:#6366f1;color:#fff;font:600 13px system-ui,sans-serif;cursor:pointer';
      b.onclick = function () {
        if (sessao) { (window.top || window).location.href = (C.b || '/') + 'login.php'; } else { location.reload(); }
      };
      d.appendChild(b);
      (document.body || document.documentElement).appendChild(d);
      setTimeout(function () { avisado = false; }, 15000);
    } catch (e) {}
  }

  /* ---------------- links sem endereço no canto da tela ---------------- */
  if (C.l) {
    var ATR = 'data-atlas-href';
    function ignorar(h) { return !h || h.charAt(0) === '#' || /^\s*(javascript|mailto|tel|data|blob):/i.test(h); }
    function esconder(a) {
      if (!a || a.hasAttribute(ATR)) return;
      var h = a.getAttribute('href');
      if (ignorar(h)) return;
      try {
        var cs = getComputedStyle(a);
        a.__atlasEstilo = a.getAttribute('style');
        a.style.color = cs.color;
        a.style.textDecoration = cs.textDecoration;
        a.style.cursor = 'pointer';
      } catch (e) {}
      a.setAttribute(ATR, h);
      a.removeAttribute('href');
    }
    function mostrar(a) {
      if (!a || !a.hasAttribute(ATR)) return;
      a.setAttribute('href', a.getAttribute(ATR));
      a.removeAttribute(ATR);
      if (a.__atlasEstilo === null || a.__atlasEstilo === undefined) a.removeAttribute('style'); else a.setAttribute('style', a.__atlasEstilo);
      a.__atlasEstilo = undefined;
    }
    function linkDe(el) { return el && el.closest ? el.closest('a[href],a[' + ATR + ']') : null; }

    document.addEventListener('mouseover', function (e) { esconder(linkDe(e.target)); }, true);
    document.addEventListener('mouseout', function (e) {
      var a = linkDe(e.target);
      if (a && !(e.relatedTarget && a.contains(e.relatedTarget))) mostrar(a);
    }, true);
    /* clique: devolve o endereço a tempo do navegador e das telas usarem (botão direito não) */
    document.addEventListener('mousedown', function (e) {
      if (e.button === 2) return;
      var a = linkDe(e.target);
      if (!a) return;
      mostrar(a);
      var fim = function () {
        document.removeEventListener('click', fim, true);
        document.removeEventListener('auxclick', fim, true);
        setTimeout(function () { try { if (a.matches(':hover')) esconder(a); } catch (er) {} }, 0);
      };
      document.addEventListener('click', fim, true);
      document.addEventListener('auxclick', fim, true);
    }, true);
    /* teclado: Enter num link focado */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') mostrar(linkDe(e.target));
    }, true);
    /* código das telas que lê o endereço (ex.: $(this).attr('href')) por evento sem clique */
    document.addEventListener('focusin', function (e) { mostrar(linkDe(e.target)); }, true);
  }
})();
