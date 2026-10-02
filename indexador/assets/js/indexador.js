/* ==========================================================================
   ATLAS · INDEXADOR — biblioteca de interface (sem dependências)
   ========================================================================== */
(function (w, d) {
  'use strict';

  const IX = w.IX = w.IX || {};

  /* ----------------------------------------------------------------------
     Utilidades
     ---------------------------------------------------------------------- */
  IX.icon = function (name, cls) {
    const p = (w.IX_ICONS || {})[name] || (w.IX_ICONS || {}).info || '';
    return '<svg class="ix-i ' + (cls || '') + '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + p + '</svg>';
  };

  IX.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  };

  IX.debounce = function (fn, ms) {
    let t; return function () { const a = arguments, s = this; clearTimeout(t); t = setTimeout(() => fn.apply(s, a), ms); };
  };

  IX.fold = function (s) {
    return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  };

  IX.el = function (html) {
    const t = d.createElement('template'); t.innerHTML = html.trim(); return t.content.firstElementChild;
  };

  IX.store = {
    get(k, def) { try { const v = w.localStorage.getItem('ix.' + k); return v == null ? def : JSON.parse(v); } catch (e) { return def; } },
    set(k, v) { try { w.localStorage.setItem('ix.' + k, JSON.stringify(v)); } catch (e) { /* armazenamento indisponível */ } }
  };

  /* Datas: o formulário usa DD/MM/AAAA; a API aceita os dois formatos. */
  IX.date = {
    br(iso) {
      const m = String(iso || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
      return m ? m[3] + '/' + m[2] + '/' + m[1] : '';
    },
    iso(br) {
      const m = String(br || '').match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
      if (!m) return null;
      const dd = +m[1], mm = +m[2], yy = +m[3];
      const dt = new Date(yy, mm - 1, dd);
      if (dt.getFullYear() !== yy || dt.getMonth() !== mm - 1 || dt.getDate() !== dd) return null;
      return m[3] + '-' + m[2] + '-' + m[1];
    },
    today() { const t = new Date(); return t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0'); },
    stamp(s) {
      const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
      return m ? m[3] + '/' + m[2] + '/' + m[1] + ' às ' + m[4] + ':' + m[5] : IX.date.br(s);
    }
  };

  /* Máscaras de digitação */
  IX.mask = {
    date(input) {
      input.setAttribute('inputmode', 'numeric');
      input.setAttribute('maxlength', '10');
      input.addEventListener('input', () => {
        let v = input.value.replace(/\D/g, '').slice(0, 8);
        if (v.length > 4) v = v.slice(0, 2) + '/' + v.slice(2, 4) + '/' + v.slice(4);
        else if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2);
        input.value = v;
      });
    },
    time(input) {
      input.setAttribute('inputmode', 'numeric');
      input.setAttribute('maxlength', '5');
      input.addEventListener('input', () => {
        let v = input.value.replace(/\D/g, '').slice(0, 4);
        if (v.length > 2) v = v.slice(0, 2) + ':' + v.slice(2);
        input.value = v;
      });
    },
    digits(input, max) {
      input.setAttribute('inputmode', 'numeric');
      input.addEventListener('input', () => {
        const v = input.value.replace(/\D/g, '');
        input.value = max ? v.slice(0, max + 2) : v; // permite exceder um pouco para mostrar o erro
      });
    }
  };

  /* ----------------------------------------------------------------------
     Requisições
     ---------------------------------------------------------------------- */
  IX.api = async function (url, opts) {
    opts = opts || {};
    const init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (opts.params) {
      const q = new URLSearchParams();
      Object.keys(opts.params).forEach(k => { const v = opts.params[k]; if (v !== undefined && v !== null && v !== '') q.append(k, v); });
      url += (url.indexOf('?') >= 0 ? '&' : '?') + q.toString();
    }
    if (opts.body) {
      init.method = 'POST';
      if (opts.body instanceof FormData) init.body = opts.body;
      else { const fd = new FormData(); Object.keys(opts.body).forEach(k => fd.append(k, opts.body[k] == null ? '' : opts.body[k])); init.body = fd; }
    }
    let res, data;
    try {
      res = await fetch(url, init);
    } catch (e) {
      throw Object.assign(new Error('Sem conexão com o servidor. Verifique a rede e tente novamente.'), { network: true });
    }
    const text = await res.text();
    try { data = JSON.parse(text); } catch (e) {
      throw Object.assign(new Error('O servidor respondeu em formato inesperado (HTTP ' + res.status + ').'), { raw: text, status: res.status });
    }
    if (res.status === 401) {
      IX.toast('Sua sessão expirou. Faça login novamente.', 'err');
      throw Object.assign(new Error(data.message || 'Sessão expirada.'), { status: 401, data });
    }
    if (!res.ok || data.ok === false) {
      throw Object.assign(new Error(data.message || ('Falha na requisição (HTTP ' + res.status + ').')), { status: res.status, data });
    }
    return data;
  };

  /* ----------------------------------------------------------------------
     Avisos (toasts)
     ---------------------------------------------------------------------- */
  IX.toast = function (msg, type, title) {
    let box = d.querySelector('.ix-toasts');
    if (!box) { box = d.createElement('div'); box.className = 'ix-toasts ix-layer-host'; box.setAttribute('role', 'status'); box.setAttribute('aria-live', 'polite'); d.body.appendChild(box); }
    const icon = { ok: 'check', err: 'error', warn: 'alert' }[type] || 'info';
    const t = IX.el('<div class="ix-toast is-' + (type || 'info') + '">' + IX.icon(icon) +
      '<div>' + (title ? '<b>' + IX.esc(title) + '</b>' : '') + IX.esc(msg) + '</div><button type="button" aria-label="Fechar">×</button></div>');
    t.querySelector('button').onclick = () => t.remove();
    box.appendChild(t);
    setTimeout(() => t.remove(), type === 'err' ? 9000 : 4500);
  };

  /* ----------------------------------------------------------------------
     Camadas (editor, gaveta, diálogo)
     ---------------------------------------------------------------------- */
  const stack = [];
  IX.layer = function (opts) {
    const kind = opts.kind || 'dialog';
    const accent = d.getElementById('ix-app') ? (d.getElementById('ix-app').className.match(/ix-t-\w+/) || [''])[0] : '';
    const root = IX.el(
      '<div class="ix-layer ix-layer-' + kind + ' ' + accent + ' ' + (opts.cls || '') + '" role="dialog" aria-modal="true">' +
        '<div class="ix-backdrop"></div>' +
        '<div class="ix-sheet" tabindex="-1">' +
          '<header class="ix-sheet-head"><div><h2 class="ix-sheet-title"></h2><div class="ix-sheet-sub"></div></div><span class="ix-spacer"></span>' +
          '<div class="ix-sheet-tools"></div>' +
          '<button type="button" class="ix-btn ix-btn-ghost ix-btn-icon" data-close aria-label="Fechar (Esc)">' + IX.icon('x') + '</button></header>' +
          '<div class="ix-sheet-body"></div>' +
          '<footer class="ix-sheet-foot ix-hidden"></footer>' +
        '</div>' +
      '</div>');
    const L = {
      root,
      sheet: root.querySelector('.ix-sheet'),
      body: root.querySelector('.ix-sheet-body'),
      foot: root.querySelector('.ix-sheet-foot'),
      tools: root.querySelector('.ix-sheet-tools'),
      title(t) { root.querySelector('.ix-sheet-title').textContent = t; root.setAttribute('aria-label', t); return L; },
      sub(t) { root.querySelector('.ix-sheet-sub').innerHTML = t || ''; return L; },
      setFoot(html) { L.foot.innerHTML = html || ''; L.foot.classList.toggle('ix-hidden', !html); return L; },
      onBeforeClose: opts.onBeforeClose || null,
      onClose: opts.onClose || null,
      open() {
        d.body.appendChild(root);
        root.classList.add('is-open');
        stack.push(L);
        d.body.classList.add('ix-locked');
        L._prevFocus = d.activeElement;
        requestAnimationFrame(() => { root.classList.add('is-shown'); (root.querySelector('[autofocus]') || L.sheet).focus({ preventScroll: true }); });
        return L;
      },
      async close(force) {
        if (!force && L.onBeforeClose) { const ok = await L.onBeforeClose(); if (ok === false) return false; }
        root.classList.remove('is-shown');
        const i = stack.indexOf(L); if (i >= 0) stack.splice(i, 1);
        setTimeout(() => { root.remove(); if (!stack.length) d.body.classList.remove('ix-locked'); }, 180);
        if (L._prevFocus && L._prevFocus.focus) try { L._prevFocus.focus({ preventScroll: true }); } catch (e) {}
        if (L.onClose) L.onClose();
        return true;
      }
    };
    if (opts.title) L.title(opts.title);
    if (opts.sub) L.sub(opts.sub);
    if (opts.body) { if (typeof opts.body === 'string') L.body.innerHTML = opts.body; else L.body.appendChild(opts.body); }
    if (opts.foot) L.setFoot(opts.foot);
    root.querySelector('[data-close]').addEventListener('click', () => L.close());
    root.querySelector('.ix-backdrop').addEventListener('click', () => { if (kind !== 'editor') L.close(); });
    root.addEventListener('click', e => { if (e.target.closest('[data-dismiss-layer]')) L.close(); });
    return L;
  };
  IX.topLayer = () => stack[stack.length - 1] || null;

  d.addEventListener('keydown', e => {
    if (e.key === 'Escape' && stack.length) { e.preventDefault(); stack[stack.length - 1].close(); }
    // Mantém o foco dentro da camada do topo
    if (e.key === 'Tab' && stack.length) {
      const L = stack[stack.length - 1];
      const f = Array.from(L.root.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select,textarea,[tabindex]:not([tabindex="-1"])')).filter(x => x.offsetParent !== null);
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  IX.confirm = function (o) {
    return new Promise(resolve => {
      let done = false;
      const L = IX.layer({
        kind: 'dialog', title: o.title || 'Confirmar',
        body: '<div class="ix-confirm-body">' + (o.html || '<p>' + IX.esc(o.text || '') + '</p>') + '</div>',
        foot: '<span class="ix-spacer"></span><button type="button" class="ix-btn" data-act="no">' + IX.esc(o.cancelText || 'Cancelar') + '</button>' +
              '<button type="button" class="ix-btn ' + (o.danger ? 'ix-btn-danger' : 'ix-btn-primary') + '" data-act="yes" autofocus>' + IX.esc(o.okText || 'Confirmar') + '</button>',
        onClose() { if (!done) resolve(false); }
      });
      L.foot.addEventListener('click', e => {
        const b = e.target.closest('[data-act]'); if (!b) return;
        done = true; resolve(b.dataset.act === 'yes'); L.close(true);
      });
      L.open();
      setTimeout(() => { const y = L.foot.querySelector('[data-act=yes]'); if (y) y.focus(); }, 60);
    });
  };

  /* ----------------------------------------------------------------------
     Matrícula CNJ — mesmo algoritmo do servidor, para pré-visualização
     ---------------------------------------------------------------------- */
  IX.matricula = function (cns, tipoLivro, livro, folha, termo, dataIso) {
    const pad = (v, n) => String(v || '').padStart(n, '0');
    const segs = [
      { k: 'CNS', v: cns ? pad(cns, 6) : '', n: 6 },
      { k: 'acervo', v: '01', n: 2 },
      { k: 'RCPN', v: '55', n: 2 },
      { k: 'ano', v: dataIso ? dataIso.slice(0, 4) : '', n: 4 },
      { k: 'tipo', v: tipoLivro || '', n: 1 },
      { k: 'livro', v: livro ? pad(livro, 5) : '', n: 5 },
      { k: 'folha', v: folha ? pad(folha, 3) : '', n: 3 },
      { k: 'termo', v: termo ? pad(termo, 7) : '', n: 7 }
    ];
    const base = segs.map(s => s.v).join('');
    const complete = segs.every(s => s.v.length > 0);
    let dv = '';
    if (complete && base.length === 30) {
      let m1 = 32, s1 = 0; for (let i = 0; i < 30; i++) { m1--; s1 += (+base[i]) * m1; }
      let d1 = (s1 * 10) % 11; d1 = d1 === 10 ? 1 : d1;
      let m2 = 33, s2 = 0; for (let j = 0; j < 30; j++) { m2--; s2 += (+base[j]) * m2; }
      s2 += d1 * 2; let d2 = (s2 * 10) % 11; d2 = d2 === 10 ? 1 : d2;
      dv = '' + d1 + d2;
    }
    segs.push({ k: 'DV', v: dv, n: 2, dv: true });
    return { segs, value: complete && dv ? base + dv : '', length: (base + dv).length, complete, valid: complete && base.length === 30 };
  };

  /* ----------------------------------------------------------------------
     Municípios (IBGE) — lista carregada uma vez e guardada no navegador
     ---------------------------------------------------------------------- */
  let citiesPromise = null;
  IX.cities = function () {
    if (citiesPromise) return citiesPromise;
    const cached = IX.store.get('ibge.v1', null);
    if (cached && cached.t && Date.now() - cached.t < 1000 * 60 * 60 * 24 * 30 && cached.l && cached.l.length > 5000) {
      citiesPromise = Promise.resolve(cached.l);
      return citiesPromise;
    }
    citiesPromise = fetch('https://servicodados.ibge.gov.br/api/v1/localidades/municipios?orderBy=nome')
      .then(r => { if (!r.ok) throw new Error('IBGE indisponível'); return r.json(); })
      .then(arr => {
        const l = arr.map(c => {
          const uf = (c.microrregiao && c.microrregiao.mesorregiao && c.microrregiao.mesorregiao.UF && c.microrregiao.mesorregiao.UF.sigla)
            || (c['regiao-imediata'] && c['regiao-imediata']['regiao-intermediaria'] && c['regiao-imediata']['regiao-intermediaria'].UF && c['regiao-imediata']['regiao-intermediaria'].UF.sigla) || '';
          return [String(c.id), c.nome, uf];
        });
        IX.store.set('ibge.v1', { t: Date.now(), l });
        return l;
      })
      .catch(e => { citiesPromise = null; throw e; });
    return citiesPromise;
  };

  /**
   * Abre a pesquisa de município. Resolve com {nome:'Cidade/UF', ibge:'1234567'} ou null.
   */
  IX.pickCity = function (current) {
    return new Promise(resolve => {
      let chosen = null, list = [], active = 0;
      const body = IX.el(
        '<div class="ix-picker">' +
          '<div class="ix-field"><label class="ix-label" for="ix-city-q">Município</label>' +
          '<input id="ix-city-q" class="ix-input" autocomplete="off" placeholder="Digite o nome (ex.: São Luís, caxias ma)" autofocus></div>' +
          '<div class="ix-picker-note">Carregando a lista do IBGE…</div>' +
          '<div class="ix-picker-list" role="listbox" aria-label="Municípios encontrados"></div>' +
          '<details class="ix-picker-manual"><summary class="ix-picker-note" style="cursor:pointer">Sem acesso ao IBGE? Informe o código manualmente</summary>' +
            '<div class="ix-grid" style="margin-top:10px">' +
              '<div class="ix-col ix-col-8 ix-field"><label class="ix-label">Município/UF</label><input class="ix-input is-name" data-m="nome" placeholder="CIDADE/UF"></div>' +
              '<div class="ix-col ix-col-4 ix-field"><label class="ix-label">Código IBGE</label><input class="ix-input" data-m="ibge" maxlength="7" inputmode="numeric" placeholder="7 dígitos"></div>' +
              '<div class="ix-col ix-col-12"><button type="button" class="ix-btn" data-m="ok">Usar este município</button></div>' +
            '</div></details>' +
        '</div>');
      const L = IX.layer({ kind: 'dialog', title: 'Pesquisar município', body, onClose() { resolve(chosen); } });
      const q = body.querySelector('#ix-city-q'), note = body.querySelector('.ix-picker-note'), box = body.querySelector('.ix-picker-list');
      if (current) q.value = String(current).split('/')[0];

      const render = () => {
        const term = IX.fold(q.value.trim());
        if (term.length < 2) { box.innerHTML = ''; note.textContent = 'Digite ao menos 2 letras. Use a sigla da UF para refinar (ex.: "santa ines ma").'; return; }
        const parts = term.split(/\s+/);
        let ufFilter = '';
        if (parts.length > 1 && parts[parts.length - 1].length === 2) ufFilter = parts.pop().toUpperCase();
        const nameTerm = parts.join(' ');
        list = [];
        for (const c of (IX._cities || [])) {
          const f = IX.fold(c[1]);
          if (f.indexOf(nameTerm) === -1) continue;
          if (ufFilter && c[2] !== ufFilter) { if (!(f.indexOf(term) !== -1)) continue; }
          list.push(c);
        }
        list.sort((a, b) => (IX.fold(a[1]).startsWith(nameTerm) ? 0 : 1) - (IX.fold(b[1]).startsWith(nameTerm) ? 0 : 1) || a[1].localeCompare(b[1]));
        list = list.slice(0, 80);
        active = 0;
        note.textContent = list.length ? list.length + (list.length === 80 ? '+' : '') + ' encontrado(s). Use ↑ ↓ e Enter.' : 'Nenhum município encontrado.';
        const hi = (name) => {
          const fn = IX.fold(name), i = fn.indexOf(nameTerm);
          if (i < 0 || !nameTerm) return IX.esc(name);
          return IX.esc(name.slice(0, i)) + '<mark>' + IX.esc(name.slice(i, i + nameTerm.length)) + '</mark>' + IX.esc(name.slice(i + nameTerm.length));
        };
        box.innerHTML = list.map((c, i) => '<button type="button" role="option" class="ix-picker-item' + (i === 0 ? ' is-active' : '') + '" data-i="' + i + '">' +
          '<span>' + hi(c[1]) + '</span><span class="ix-uf">' + c[2] + '</span><span class="ix-spacer"></span><code>' + c[0] + '</code></button>').join('');
      };
      const pick = (c) => { chosen = { nome: c[1] + '/' + c[2], ibge: c[0] }; L.close(true); };

      q.addEventListener('input', IX.debounce(render, 120));
      q.addEventListener('keydown', e => {
        const items = box.querySelectorAll('.ix-picker-item');
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault(); if (!items.length) return;
          items[active] && items[active].classList.remove('is-active');
          active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
          items[active].classList.add('is-active'); items[active].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') { e.preventDefault(); if (list[active]) pick(list[active]); }
      });
      box.addEventListener('click', e => { const b = e.target.closest('[data-i]'); if (b) pick(list[+b.dataset.i]); });
      body.querySelector('[data-m=ok]').addEventListener('click', () => {
        const nome = body.querySelector('[data-m=nome]').value.trim().toUpperCase();
        const ibge = body.querySelector('[data-m=ibge]').value.replace(/\D/g, '');
        if (!nome || ibge.length !== 7) { IX.toast('Informe o município e o código IBGE com 7 dígitos.', 'warn'); return; }
        chosen = { nome, ibge }; L.close(true);
      });
      L.open();
      IX.cities().then(l => { IX._cities = l; render(); })
        .catch(() => { note.textContent = 'Não foi possível consultar o IBGE agora. Informe o código manualmente abaixo.'; body.querySelector('details').open = true; });
    });
  };

  /* ----------------------------------------------------------------------
     Visualizador de documentos (PDF / imagem) com abas
     ---------------------------------------------------------------------- */
  IX.docViewer = function (host, o) {
    o = o || {};
    const emptyHtml = () => '<div class="ix-doc-empty"><div>' + IX.icon('file') + '<p style="margin:8px 0 12px">' + IX.esc(o.empty || 'Nenhum documento anexado.') + '</p>' +
      (o.onPick ? '<button type="button" class="ix-btn ix-btn-sm" data-pick>' + IX.icon('upload') + 'Escolher arquivo</button>' : '') + '</div></div>';
    host.innerHTML =
      '<div class="ix-doc-bar ix-hidden"><div class="ix-doc-tabs"></div><span class="ix-spacer"></span>' +
      '<span class="ix-doc-tools"></span></div>' +
      '<div class="ix-doc-view"><div class="ix-doc-empty"><div>' + IX.icon('file') + '<p style="margin:8px 0 0">' + IX.esc(o.empty || 'Nenhum documento anexado.') + '</p></div></div></div>';
    const tabs = host.querySelector('.ix-doc-tabs'), view = host.querySelector('.ix-doc-view'), tools = host.querySelector('.ix-doc-tools');
    let files = [], cur = -1, zoom = 1, rot = 0;
    const V = {
      set(list) { files = list || []; V.render(); if (files.length) V.show(Math.min(Math.max(cur, 0), files.length - 1)); else V.clear(); return V; },
      add(f) { files.push(f); V.render(); V.show(files.length - 1); },
      clear() { cur = -1; view.innerHTML = emptyHtml(); tools.innerHTML = ''; host.querySelector('.ix-doc-bar').classList.add('ix-hidden'); },
      render() {
        host.querySelector('.ix-doc-bar').classList.toggle('ix-hidden', !files.length);
        tabs.innerHTML = files.map((f, i) => '<button type="button" class="ix-doc-tab' + (i === cur ? ' is-active' : '') + '" data-i="' + i + '" title="' + IX.esc(f.nome) + '">' +
          IX.icon(f.ext === 'pdf' ? 'file' : 'eye') + '<span>' + IX.esc(f.nome) + '</span>' +
          (o.onRemove ? '<span class="ix-x" role="button" tabindex="0" data-rm="' + i + '" aria-label="Remover ' + IX.esc(f.nome) + '">' + IX.icon('x') + '</span>' : '') + '</button>').join('');
      },
      show(i) {
        cur = i; zoom = 1; rot = 0; V.render();
        const f = files[i]; if (!f) return V.clear();
        const isImg = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'].indexOf(f.ext) >= 0;
        if (f.existe === false) {
          view.innerHTML = '<div class="ix-doc-empty"><div>' + IX.icon('alert') + '<p style="margin:8px 0 0">Arquivo não encontrado no servidor:<br><small>' + IX.esc(f.url) + '</small></p></div></div>';
        } else if (isImg) {
          view.innerHTML = '<div class="ix-doc-img-wrap"><img alt="' + IX.esc(f.nome) + '" src="' + IX.esc(f.url) + '"></div>';
        } else {
          view.innerHTML = '<iframe title="' + IX.esc(f.nome) + '" src="' + IX.esc(f.url) + '#view=FitH"></iframe>';
        }
        tools.innerHTML = (isImg ? '<button type="button" class="ix-btn ix-btn-ghost ix-btn-icon ix-btn-sm" data-t="out" title="Diminuir">' + IX.icon('zoom-out') + '</button>' +
          '<button type="button" class="ix-btn ix-btn-ghost ix-btn-icon ix-btn-sm" data-t="in" title="Ampliar">' + IX.icon('zoom-in') + '</button>' +
          '<button type="button" class="ix-btn ix-btn-ghost ix-btn-icon ix-btn-sm" data-t="rot" title="Girar">' + IX.icon('rotate') + '</button>' : '') +
          '<a class="ix-btn ix-btn-ghost ix-btn-icon ix-btn-sm" href="' + IX.esc(f.url) + '" target="_blank" rel="noopener" title="Abrir em nova guia">' + IX.icon('external') + '</a>' +
          (f.local ? '' : '<a class="ix-btn ix-btn-ghost ix-btn-icon ix-btn-sm" href="' + IX.esc(f.url) + '" download="' + IX.esc(f.nome) + '" title="Baixar">' + IX.icon('download') + '</a>');
      },
      files: () => files
    };
    tabs.addEventListener('click', e => {
      const rm = e.target.closest('[data-rm]');
      if (rm) { e.stopPropagation(); o.onRemove(files[+rm.dataset.rm], +rm.dataset.rm); return; }
      const b = e.target.closest('[data-i]'); if (b) V.show(+b.dataset.i);
    });
    view.addEventListener('click', e => { if (e.target.closest('[data-pick]') && o.onPick) o.onPick(); });
    if (o.onDrop) {
      ['dragenter', 'dragover'].forEach(t => host.addEventListener(t, e => { e.preventDefault(); host.classList.add('is-over'); }));
      ['dragleave', 'drop'].forEach(t => host.addEventListener(t, e => { e.preventDefault(); if (t === 'drop' || !host.contains(e.relatedTarget)) host.classList.remove('is-over'); }));
      host.addEventListener('drop', e => { if (e.dataTransfer && e.dataTransfer.files.length) o.onDrop(Array.from(e.dataTransfer.files)); });
    }
    tools.addEventListener('click', e => {
      const b = e.target.closest('[data-t]'); if (!b) return;
      if (b.dataset.t === 'in') zoom = Math.min(zoom + .25, 4);
      if (b.dataset.t === 'out') zoom = Math.max(zoom - .25, .5);
      if (b.dataset.t === 'rot') rot = (rot + 90) % 360;
      const img = view.querySelector('img'); if (img) { img.style.transform = 'rotate(' + rot + 'deg) scale(' + zoom + ')'; img.style.maxWidth = zoom > 1 ? 'none' : '100%'; }
    });
    return V;
  };

  /* Área de soltar arquivos */
  IX.dropzone = function (el, onFiles, accept) {
    const input = d.createElement('input');
    input.type = 'file'; input.multiple = true; input.accept = accept || '.pdf,.png,.jpg,.jpeg,.webp'; input.hidden = true;
    el.appendChild(input);
    el.setAttribute('tabindex', '0'); el.setAttribute('role', 'button');
    el.addEventListener('click', e => { if (e.target !== input) input.click(); });
    el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    ['dragenter', 'dragover'].forEach(t => el.addEventListener(t, e => { e.preventDefault(); el.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(t => el.addEventListener(t, e => { e.preventDefault(); el.classList.remove('is-over'); }));
    el.addEventListener('drop', e => { if (e.dataTransfer && e.dataTransfer.files.length) onFiles(Array.from(e.dataTransfer.files)); });
    input.addEventListener('change', () => { if (input.files.length) onFiles(Array.from(input.files)); input.value = ''; });
  };

  IX.copy = function (text) {
    const done = () => IX.toast('Copiado para a área de transferência.', 'ok');
    if (navigator.clipboard && w.isSecureContext) navigator.clipboard.writeText(text).then(done).catch(() => fallback());
    else fallback();
    function fallback() { const t = d.createElement('textarea'); t.value = text; d.body.appendChild(t); t.select(); try { d.execCommand('copy'); done(); } catch (e) {} t.remove(); }
  };

  IX.fmtInt = n => (+n || 0).toLocaleString('pt-BR');

  /* Tema: a classe dark-mode do Atlas também é aplicada às camadas (fora do #ix-app) */
})(window, document);
/* Fecha menus <details class="ix-menu"> ao clicar fora */
document.addEventListener('click', function (e) {
  document.querySelectorAll('details.ix-menu[open]').forEach(function (m) { if (!m.contains(e.target)) m.removeAttribute('open'); });
});
/* Mantém visível a aba atual quando a barra de abas rola na horizontal (celular) */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.ix-tabs').forEach(function (t) {
    var a = t.querySelector('[aria-current="page"]');
    if (a && t.scrollWidth > t.clientWidth) t.scrollLeft = a.offsetLeft - 12;
  });
});
