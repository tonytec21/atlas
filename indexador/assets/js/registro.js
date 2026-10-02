/* ==========================================================================
   ATLAS · INDEXADOR — tela de registros (mesma para Nascimento, Casamento e Óbito)
   Configuração recebida do PHP em window.IX_CFG (gerada de _core/tipos.php).
   ========================================================================== */
(function (w, d) {
  'use strict';
  const IX = w.IX, C = w.IX_CFG;
  const $ = (s, r) => (r || d).querySelector(s);
  const $$ = (s, r) => Array.from((r || d).querySelectorAll(s));
  const API = C.api;
  const F = C.fields;

  const state = {
    page: 1, per: IX.store.get('per.' + C.tipo, 25), sort: 'id', dir: 'desc', total: 0, rows: [],
    filters: {}, loading: false
  };

  /* ======================================================================
     Cabeçalho: indicadores
     ====================================================================== */
  function loadStats() {
    IX.api(API, { params: { action: 'stats' } }).then(r => {
      const s = r.stats;
      $('#ix-stats').innerHTML =
        '<span><b>' + IX.fmtInt(s.total) + '</b>registros ativos</span>' +
        '<span><b>' + IX.fmtInt(s.mes) + '</b>cadastrados este mês</span>' +
        '<span><b>' + IX.fmtInt(s.hoje) + '</b>hoje' + (s.meus_hoje ? ' (' + IX.fmtInt(s.meus_hoje) + ' por você)' : '') + '</span>' +
        '<span><b>' + IX.fmtInt(s.livros) + '</b>' + (s.livros === 1 ? 'livro' : 'livros') + '</span>';
    }).catch(() => { $('#ix-stats').innerHTML = ''; });
  }

  /* ======================================================================
     Filtros
     ====================================================================== */
  function filterControl(f) {
    const id = 'f-' + f.key;
    let ctl;
    switch (f.type) {
      case 'daterange':
        ctl = '<div class="ix-range"><input class="ix-input" id="' + id + '" data-f="' + f.key + '_de" placeholder="De DD/MM/AAAA" data-mask="date">' +
          '<span>até</span><input class="ix-input" data-f="' + f.key + '_ate" placeholder="DD/MM/AAAA" data-mask="date" aria-label="' + IX.esc(f.label) + ' até"></div>';
        break;
      case 'livro':
        ctl = '<select class="ix-input" id="' + id + '" data-f="' + f.key + '"><option value="">Todos</option></select>';
        break;
      case 'user':
        ctl = '<select class="ix-input" id="' + id + '" data-f="' + f.key + '"><option value="">Todos</option></select>';
        break;
      case 'select': {
        const opts = (F[f.options_from] || {}).options || {};
        ctl = '<select class="ix-input" id="' + id + '" data-f="' + f.key + '"><option value="">Todos</option>' +
          Object.keys(opts).map(k => '<option value="' + k + '">' + IX.esc(opts[k]) + '</option>').join('') + '</select>';
        break;
      }
      case 'int':
        ctl = '<input class="ix-input" id="' + id + '" data-f="' + f.key + '" inputmode="numeric" data-mask="digits" autocomplete="off">';
        break;
      default:
        ctl = '<input class="ix-input" id="' + id + '" data-f="' + f.key + '" autocomplete="off"' + (f.key === 'q' ? ' placeholder="Digite parte do nome"' : '') + '>';
    }
    return '<div class="ix-col ix-col-' + (f.span || 3) + ' ix-field"><label class="ix-label" for="' + id + '">' + IX.esc(f.label) + '</label>' + ctl + '</div>';
  }

  function buildFilters() {
    const main = C.filters.filter(f => f.main), more = C.filters.filter(f => !f.main);
    const box = $('#ix-filters');
    box.innerHTML =
      '<form id="ix-filter-form" autocomplete="off" role="search">' +
        '<div class="ix-grid ix-grid-tight">' + main.map(filterControl).join('') + '</div>' +
        '<div class="ix-filters-more"><div class="ix-grid">' + more.map(filterControl).join('') + '</div></div>' +
        '<div class="ix-filters-bar">' +
          '<button type="submit" class="ix-btn ix-btn-primary">' + IX.icon('search') + 'Pesquisar</button>' +
          '<button type="button" class="ix-btn ix-btn-ghost" id="ix-more">' + IX.icon('sliders') + '<span>Mais filtros</span></button>' +
          '<button type="button" class="ix-btn ix-btn-ghost ix-hidden" id="ix-clear">' + IX.icon('x') + 'Limpar</button>' +
          '<span class="ix-spacer"></span><div class="ix-chipline" id="ix-chips"></div>' +
        '</div>' +
      '</form>';
    $$('[data-mask=date]', box).forEach(IX.mask.date);
    $$('[data-mask=digits]', box).forEach(i => IX.mask.digits(i));
    $('#ix-more').onclick = () => {
      box.classList.toggle('is-open');
      $('#ix-more span').textContent = box.classList.contains('is-open') ? 'Menos filtros' : 'Mais filtros';
    };
    $('#ix-clear').onclick = () => { $$('[data-f]', box).forEach(i => i.value = ''); applyFilters(); };
    $('#ix-filter-form').addEventListener('submit', e => { e.preventDefault(); applyFilters(); });
    $$('select[data-f]', box).forEach(s => s.addEventListener('change', applyFilters));

    // valores da URL (permite compartilhar/voltar a uma pesquisa)
    const qs = new URLSearchParams(location.search);
    let hasMore = false;
    $$('[data-f]', box).forEach(i => {
      const v = qs.get(i.dataset.f);
      if (v && i.tagName === 'SELECT' && !Array.from(i.options).some(o => o.value === v)) {
        i.insertAdjacentHTML('beforeend', '<option value="' + IX.esc(v) + '">' + (i.id === 'f-livro' ? 'Livro ' : '') + IX.esc(v) + '</option>');
      }
      if (v) { i.value = v; if (!main.some(m => i.dataset.f.indexOf(m.key) === 0)) hasMore = true; }
    });
    if (qs.get('sort')) state.sort = qs.get('sort');
    if (qs.get('dir')) state.dir = qs.get('dir');
    if (qs.get('page')) state.page = Math.max(1, +qs.get('page') || 1);
    if (hasMore) $('#ix-more').click();
    readFilters();

    IX.api(API, { params: { action: 'livros' } }).then(r => {
      const sel = $('#f-livro'); if (!sel) return;
      const cur = qs.get('livro') || '';
      r.livros.forEach(l => { if (!Array.from(sel.options).some(o => o.value === String(l))) sel.insertAdjacentHTML('beforeend', '<option value="' + l + '">Livro ' + l + '</option>'); });
      if (cur) sel.value = cur;
    }).catch(() => {});
    IX.api(API, { params: { action: 'users' } }).then(r => {
      const sel = $('#f-func'); if (!sel) return;
      const cur = qs.get('func') || '';
      r.users.forEach(u => {
        const ex = Array.from(sel.options).find(o => o.value === u.usuario);
        if (ex) ex.textContent = u.nome; else sel.insertAdjacentHTML('beforeend', '<option value="' + IX.esc(u.usuario) + '">' + IX.esc(u.nome) + '</option>');
      });
      if (cur) sel.value = cur;
      renderChips();
    }).catch(() => {});
  }

  function readFilters() {
    const f = {};
    $$('#ix-filters [data-f]').forEach(i => { const v = i.value.trim(); if (v) f[i.dataset.f] = v; });
    state.filters = f;
    renderChips();
    return f;
  }

  function filterLabel(key) {
    const base = key.replace(/_(de|ate)$/, '');
    const def = C.filters.find(x => x.key === base);
    if (!def) return key;
    if (/_de$/.test(key)) return def.label + ' a partir de';
    if (/_ate$/.test(key)) return def.label + ' até';
    return def.label;
  }

  function renderChips() {
    const keys = Object.keys(state.filters);
    $('#ix-clear').classList.toggle('ix-hidden', !keys.length);
    $('#ix-chips').innerHTML = keys.map(k => {
      let v = state.filters[k];
      const el = $('#ix-filters [data-f="' + k + '"]');
      if (el && el.tagName === 'SELECT') v = el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : v;
      return '<button type="button" class="ix-chip" data-k="' + k + '" title="Remover filtro">' + IX.esc(filterLabel(k)) + ': ' + IX.esc(v) + IX.icon('x') + '</button>';
    }).join('');
    $$('#ix-chips .ix-chip').forEach(c => c.onclick = () => { const el = $('#ix-filters [data-f="' + c.dataset.k + '"]'); if (el) el.value = ''; applyFilters(); });
  }

  function applyFilters() {
    for (const k in state.filters) delete state.filters[k];
    const f = readFilters();
    for (const k of Object.keys(f)) {
      if (/_(de|ate)$/.test(k) && !IX.date.iso(f[k])) {
        IX.toast('Use o formato DD/MM/AAAA em "' + filterLabel(k) + '".', 'warn');
        return;
      }
    }
    state.page = 1;
    loadList();
  }

  function syncUrl() {
    const q = new URLSearchParams(state.filters);
    if (state.sort !== 'id') q.set('sort', state.sort);
    if (state.dir !== 'desc') q.set('dir', state.dir);
    if (state.page > 1) q.set('page', state.page);
    const s = q.toString();
    history.replaceState(null, '', location.pathname + (s ? '?' + s : ''));
  }

  /* ======================================================================
     Listagem
     ====================================================================== */
  function cell(col, r) {
    const v = r[col.key];
    if (col.date) return '<td class="ix-num">' + (IX.date.br(v) || '<span style="color:var(--ix-muted)">—</span>') + '</td>';
    if (col.mono) return '<td class="ix-num">' + IX.esc(v) + '</td>';
    if (col.badge) return '<td><span class="ix-badge">' + IX.esc(((F[col.key] || {}).options || {})[v] ? shortOpt(col.key, v) : v) + '</span></td>';
    if (col.primary) {
      const sub = r[col.sub] ? '<div class="ix-sub">' + IX.esc(r[col.sub]) + '</div>' : '';
      const clip = r._anexos ? '<span class="ix-clip" title="' + r._anexos + ' anexo(s)">' + IX.icon('clip') + r._anexos + '</span>' : '';
      return '<td class="ix-main"><div class="ix-primary-text">' + IX.esc(v || '—') + clip + '</div>' + sub + '</td>';
    }
    return '<td>' + IX.esc(v) + '</td>';
  }
  function shortOpt(key, v) { return key === 'tipo_casamento' ? (v === 'CIVIL' ? 'Civil' : 'Religioso') : F[key].options[v]; }

  function rowActions(r) {
    return '<div class="ix-row-actions">' +
      '<button type="button" class="ix-btn ix-btn-ghost ix-btn-icon ix-btn-sm" data-act="view" data-id="' + r.id + '" title="Visualizar">' + IX.icon('eye') + '</button>' +
      '<button type="button" class="ix-btn ix-btn-ghost ix-btn-icon ix-btn-sm" data-act="edit" data-id="' + r.id + '" title="Editar">' + IX.icon('edit') + '</button>' +
      (C.isAdmin ? '<button type="button" class="ix-btn ix-btn-ghost ix-btn-icon ix-btn-sm ix-btn-danger" data-act="delete" data-id="' + r.id + '" title="Excluir">' + IX.icon('trash') + '</button>' : '') +
      '</div>';
  }

  function renderTableHead() {
    $('#ix-thead').innerHTML = '<tr>' + C.columns.map(c => {
      const sorted = state.sort === c.sort;
      const aria = sorted ? ' aria-sort="' + (state.dir === 'asc' ? 'ascending' : 'descending') + '"' : '';
      const icon = sorted ? (state.dir === 'asc' ? 'sort-asc' : 'sort-desc') : 'sort';
      return '<th' + aria + (c.mono ? ' style="width:84px"' : (c.date ? ' style="width:120px"' : '')) + '><button type="button" data-sort="' + c.sort + '">' + IX.esc(c.label) + IX.icon(icon, 'ix-sort') + '</button></th>';
    }).join('') + '<th><span class="ix-sr">Ações</span></th></tr>';
  }

  let listSeq = 0;
  function loadList() {
    const seq = ++listSeq;
    state.loading = true;
    syncUrl();
    renderTableHead();
    const tb = $('#ix-tbody');
    if (!state.rows.length) {
      tb.innerHTML = Array.from({ length: 6 }).map(() => '<tr class="ix-skel-row">' + C.columns.map(() => '<td><div></div></td>').join('') + '<td></td></tr>').join('');
    } else {
      tb.style.opacity = '.55';
    }
    const params = Object.assign({ action: 'list', page: state.page, per_page: state.per, sort: state.sort, dir: state.dir }, state.filters);
    IX.api(API, { params }).then(r => {
      if (seq !== listSeq) return;
      state.rows = r.rows; state.total = r.total; state.page = r.page; state.pages = r.pages;
      renderList();
    }).catch(e => {
      if (seq !== listSeq) return;
      tb.innerHTML = '<tr><td colspan="' + (C.columns.length + 1) + '"><div class="ix-empty">' + IX.esc(e.message) + '</div></td></tr>';
    }).finally(() => { if (seq === listSeq) { state.loading = false; tb.style.opacity = ''; } });
  }

  function renderList() {
    const rows = state.rows;
    const hasF = Object.keys(state.filters).length > 0;
    $('#ix-count').innerHTML = IX.fmtInt(state.total) + ' ' + (state.total === 1 ? 'registro' : 'registros') +
      (hasF ? ' <small>com os filtros aplicados</small>' : ' <small>— mais recentes primeiro</small>');
    if (!rows.length) {
      const empty = hasF
        ? '<div class="ix-empty"><div class="ix-empty-mark">' + IX.icon('search') + '</div><h3>Nada encontrado</h3><p>Nenhum registro atende aos filtros. Revise os critérios ou limpe a pesquisa.</p><button type="button" class="ix-btn" onclick="document.getElementById(\'ix-clear\').click()">Limpar filtros</button></div>'
        : '<div class="ix-empty"><div class="ix-empty-mark">' + IX.icon(C.icon) + '</div><h3>Nenhum registro de ' + IX.esc(C.label.toLowerCase()) + ' indexado</h3><p>Comece cadastrando o primeiro termo ou importe uma planilha em lote.</p><button type="button" class="ix-btn ix-btn-type" data-new>' + IX.icon('plus') + 'Novo registro</button></div>';
      $('#ix-tbody').innerHTML = '<tr><td colspan="' + (C.columns.length + 1) + '">' + empty + '</td></tr>';
      $('#ix-cards').innerHTML = empty;
    } else {
      $('#ix-tbody').innerHTML = rows.map(r => '<tr tabindex="0" data-id="' + r.id + '">' + C.columns.map(c => cell(c, r)).join('') + '<td class="ix-actions">' + rowActions(r) + '</td></tr>').join('');
      $('#ix-cards').innerHTML = rows.map(r => {
        const p = C.columns.find(c => c.primary);
        const dates = C.columns.filter(c => c.date).map(c => c.label + ' ' + (IX.date.br(r[c.key]) || '—')).join('   ');
        return '<div class="ix-card-row" data-id="' + r.id + '">' +
          '<div class="ix-card-ref"><span>Termo <b>' + IX.esc(r.termo) + '</b></span><span>Livro <b>' + IX.esc(r.livro) + '</b></span><span>Folha <b>' + IX.esc(r.folha) + '</b></span>' +
          (r._anexos ? '<span class="ix-clip">' + IX.icon('clip') + r._anexos + '</span>' : '') + '</div>' +
          '<div class="ix-card-title">' + IX.esc(r[p.key] || '—') + '</div>' +
          (r[p.sub] ? '<div class="ix-card-meta">' + IX.esc(r[p.sub]) + '</div>' : '') +
          '<div class="ix-card-meta">' + IX.esc(dates) + '</div></div>';
      }).join('');
    }
    // paginação
    const from = state.total ? (state.page - 1) * state.per + 1 : 0, to = Math.min(state.total, state.page * state.per);
    $('#ix-pager').innerHTML =
      '<span class="ix-pager-info">' + (state.total ? from + '–' + to + ' de ' + IX.fmtInt(state.total) : '') + '</span>' +
      '<label class="ix-pager-info" style="display:flex;gap:6px;align-items:center;margin:0">por página <select class="ix-input" id="ix-per">' +
      [10, 25, 50, 100].map(n => '<option' + (n === state.per ? ' selected' : '') + '>' + n + '</option>').join('') + '</select></label>' +
      '<span class="ix-spacer"></span>' +
      '<button type="button" class="ix-btn ix-btn-sm" data-pg="prev"' + (state.page <= 1 ? ' disabled' : '') + '>' + IX.icon('left') + 'Anterior</button>' +
      '<span class="ix-pager-info">Página ' + state.page + ' de ' + (state.pages || 1) + '</span>' +
      '<button type="button" class="ix-btn ix-btn-sm" data-pg="next"' + (state.page >= state.pages ? ' disabled' : '') + '>Próxima' + IX.icon('right') + '</button>';
    $('#ix-per').onchange = e => { state.per = +e.target.value; IX.store.set('per.' + C.tipo, state.per); state.page = 1; loadList(); };
  }

  function bindList() {
    $('#ix-thead').addEventListener('click', e => {
      const b = e.target.closest('[data-sort]'); if (!b) return;
      const s = b.dataset.sort;
      if (state.sort === s) state.dir = state.dir === 'asc' ? 'desc' : 'asc';
      else { state.sort = s; state.dir = ['termo', 'livro', 'folha'].indexOf(s) >= 0 || s.indexOf('nome') >= 0 || s.indexOf('conjuge') === 0 ? 'asc' : 'desc'; }
      state.page = 1; loadList();
    });
    const open = e => {
      const a = e.target.closest('[data-act]');
      if (a) {
        e.stopPropagation();
        const id = +a.dataset.id;
        if (a.dataset.act === 'view') openView(id);
        if (a.dataset.act === 'edit') openEditor(id);
        if (a.dataset.act === 'delete') removeRecord(id);
        return;
      }
      const tr = e.target.closest('[data-id]'); if (tr) openView(+tr.dataset.id);
    };
    $('#ix-tbody').addEventListener('click', open);
    $('#ix-cards').addEventListener('click', open);
    $('#ix-tbody').addEventListener('keydown', e => {
      if (e.key === 'Enter' && e.target.matches('tr[data-id]')) openView(+e.target.dataset.id);
      if (e.key === 'ArrowDown' && e.target.matches('tr')) { e.preventDefault(); const n = e.target.nextElementSibling; if (n) n.focus(); }
      if (e.key === 'ArrowUp' && e.target.matches('tr')) { e.preventDefault(); const p = e.target.previousElementSibling; if (p) p.focus(); }
    });
    $('#ix-pager').addEventListener('click', e => {
      const b = e.target.closest('[data-pg]'); if (!b || b.disabled) return;
      state.page += b.dataset.pg === 'next' ? 1 : -1; loadList();
      $('#ix-results').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    d.addEventListener('click', e => { if (e.target.closest('[data-new]')) openEditor(0); });
  }

  /* ======================================================================
     Visualização (gaveta)
     ====================================================================== */
  function display(key, row) {
    const f = F[key]; const v = row[key];
    if (v === null || v === undefined || v === '') return null;
    if (f.type === 'date') return IX.date.br(v);
    if (f.type === 'select') return f.options[v] || v;
    if (f.type === 'city') return v + (row[f.ibge] ? '  (IBGE ' + row[f.ibge] + ')' : '');
    return v;
  }

  async function openView(id) {
    const L = IX.layer({ kind: 'drawer', title: 'Carregando…' });
    L.body.innerHTML = '<div class="ix-empty">Carregando registro…</div>';
    L.open();
    let r;
    try { r = (await IX.api(API, { params: { action: 'get', id } })).row; }
    catch (e) { L.body.innerHTML = '<div class="ix-empty">' + IX.esc(e.message) + '</div>'; return; }

    L.title(r._nome || C.label).sub('Termo ' + IX.esc(r.termo) + ', livro ' + IX.esc(r.livro) + ', folha ' + IX.esc(r.folha));
    L.tools.innerHTML =
      '<button type="button" class="ix-btn ix-btn-sm" data-v="crc">' + IX.icon('shield') + '<span class="ix-hide-sm">Verificar CRC</span></button>' +
      (C.isAdmin ? '<button type="button" class="ix-btn ix-btn-sm ix-btn-icon ix-btn-danger" data-v="del" title="Excluir">' + IX.icon('trash') + '</button>' : '') +
      '<button type="button" class="ix-btn ix-btn-sm ix-btn-type" data-v="edit">' + IX.icon('edit') + 'Editar</button>';

    let html = '<div class="ix-view"><div class="ix-view-data">' +
      '<div class="ix-ref"><div><small>Livro</small><b>' + IX.esc(r.livro) + '</b></div><div><small>Folha</small><b>' + IX.esc(r.folha) + '</b></div><div><small>Termo</small><b>' + IX.esc(r.termo) + '</b></div></div>' +
      '<dl class="ix-dl">' +
        '<div><dt>Matrícula</dt><dd class="ix-mono' + (r.matricula ? '' : ' is-empty') + '">' + (r.matricula ? IX.esc(r.matricula) + '<button type="button" class="ix-copy" data-copy="' + IX.esc(r.matricula) + '" title="Copiar matrícula">' + IX.icon('copy') + '</button>' : 'não gerada') + '</dd></div>' +
        '<div><dt>Data do registro</dt><dd>' + (IX.date.br(r.data_registro) || '—') + '</dd></div>' +
      '</dl>';
    Object.keys(C.sections).forEach(sk => {
      if (sk === 'registro') {
        const extra = Object.keys(F).filter(k => F[k].section === 'registro' && ['livro', 'folha', 'termo', 'data_registro'].indexOf(k) < 0);
        if (!extra.length) return;
        html += '<dl class="ix-dl" style="margin-top:12px">' + extra.map(k => dd(k, r)).join('') + '</dl>';
        return;
      }
      const keys = Object.keys(F).filter(k => F[k].section === sk);
      html += '<div class="ix-dl-group"><h4>' + IX.icon(C.sections[sk].icon) + IX.esc(C.sections[sk].label) + '</h4><dl class="ix-dl">' + keys.map(k => dd(k, r)).join('') + '</dl></div>';
    });
    html += '<div class="ix-dl-group"><dl class="ix-dl"><div><dt>Cadastrado por</dt><dd>' + IX.esc(r._cadastrado_por || '—') + (r._cadastrado_em ? ', em ' + IX.date.stamp(r._cadastrado_em) : '') + '</dd></div></dl></div>';
    html += '<div id="ix-view-crc"></div></div><div class="ix-view-doc" id="ix-view-doc"></div></div>';
    L.body.innerHTML = html;

    const viewer = IX.docViewer($('#ix-view-doc', L.body), { empty: 'Este registro não tem anexos.' });
    viewer.set(r._anexos_lista);

    L.tools.addEventListener('click', async e => {
      const b = e.target.closest('[data-v]'); if (!b) return;
      if (b.dataset.v === 'edit') { await L.close(true); openEditor(id); }
      if (b.dataset.v === 'del') { if (await removeRecord(id)) L.close(true); }
      if (b.dataset.v === 'crc') checkSaved(r, $('#ix-view-crc', L.body), b);
    });
    L.body.addEventListener('click', e => { const c = e.target.closest('[data-copy]'); if (c) IX.copy(c.dataset.copy); });
  }
  function dd(k, r) {
    const v = display(k, r);
    return '<div><dt>' + IX.esc(F[k].label) + '</dt><dd' + (v === null ? ' class="is-empty">não informado' : '>' + IX.esc(v)) + '</dd></div>';
  }

  async function checkSaved(r, host, btn) {
    btn.classList.add('is-loading');
    const body = new FormData();
    body.append('action', 'check'); body.append('id', r.id);
    Object.keys(F).forEach(k => { body.append(k, r[k] == null ? '' : r[k]); if (F[k].ibge) body.append(F[k].ibge, r[F[k].ibge] || ''); });
    try {
      const res = await IX.api(API + '?action=check', { body });
      host.innerHTML = '<div class="ix-dl-group"><h4>' + IX.icon('shield') + 'Conformidade com a CRC</h4>' + crcListHtml(res, true) + '</div>';
      host.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (e) { IX.toast(e.message, 'err'); }
    finally { btn.classList.remove('is-loading'); }
  }

  function crcListHtml(res, compact) {
    const items = (res.errors || []).map(x => ({ t: 'err', x })).concat((res.warnings || []).map(x => ({ t: 'warn', x })));
    if (!items.length) return '<ul class="ix-crc-list"><li class="is-ok">' + IX.icon('check') + '<div><b>Pronto para a carga.</b> O registro passa nas regras do indexador e no XSD oficial da CRC.</div></li></ul>';
    return '<ul class="ix-crc-list">' + items.map(i => {
      const lbl = i.x.field && F[i.x.field] ? F[i.x.field].label : (i.x.field === 'matricula' ? 'Matrícula' : '');
      const src = { xsd: 'XSD CRC', regra: 'Regra', duplicidade: 'Duplicidade' }[i.x.source] || '';
      return '<li class="is-' + i.t + '">' + IX.icon(i.t === 'err' ? 'error' : 'alert') + '<div>' +
        (lbl && !compact ? '<a href="#" data-goto="' + i.x.field + '">' + IX.esc(lbl) + '</a>: ' : '') + IX.esc(i.x.msg) +
        (src ? '<span class="ix-src">' + src + '</span>' : '') + '</div></li>';
    }).join('') + '</ul>';
  }

  /* ======================================================================
     Exclusão
     ====================================================================== */
  async function removeRecord(id) {
    const row = state.rows.find(r => +r.id === id);
    const ok = await IX.confirm({
      title: 'Excluir registro',
      html: '<p>O registro ' + (row ? '<b>' + IX.esc(row._nome) + '</b> (termo ' + IX.esc(row.termo) + ', livro ' + IX.esc(row.livro) + ')' : '#' + id) +
        ' deixará de aparecer nas pesquisas e não será incluído nas próximas cargas.</p><p class="ix-hint">A exclusão é lógica: os dados continuam no banco com a situação "removido".</p>',
      okText: 'Excluir', danger: true
    });
    if (!ok) return false;
    try { await IX.api(API + '?action=delete', { body: { id } }); IX.toast('Registro excluído.', 'ok'); loadList(); loadStats(); return true; }
    catch (e) { IX.toast(e.message, 'err'); return false; }
  }

  /* ======================================================================
     Editor (inclusão / alteração)
     ====================================================================== */
  function fieldHtml(key) {
    const f = F[key], id = 'e-' + key;
    const req = f.required ? '<span class="ix-req" aria-hidden="true">*</span>' : '<span class="ix-opt">opcional</span>';
    const counter = f.max ? '<span class="ix-counter" data-counter="' + key + '">0/' + f.max + '</span>' : '';
    let ctl;
    const aria = ' aria-describedby="m-' + key + '"' + (f.required ? ' aria-required="true"' : '');
    switch (f.type) {
      case 'int':
        ctl = '<input class="ix-input" id="' + id + '" name="' + key + '" inputmode="numeric" autocomplete="off"' + aria + '>'; break;
      case 'date':
        ctl = '<input class="ix-input" id="' + id + '" name="' + key + '" placeholder="DD/MM/AAAA" autocomplete="off"' + aria + '>'; break;
      case 'time':
        ctl = '<input class="ix-input" id="' + id + '" name="' + key + '" placeholder="HH:MM" autocomplete="off"' + aria + '>'; break;
      case 'select':
        ctl = '<select class="ix-input" id="' + id + '" name="' + key + '"' + aria + '><option value="">Selecione</option>' +
          Object.keys(f.options).map(k => '<option value="' + k + '">' + IX.esc(f.options[k]) + '</option>').join('') + '</select>'; break;
      case 'city':
        ctl = '<div class="ix-city"><input class="ix-input is-name" id="' + id + '" name="' + key + '" readonly placeholder="Clique ou pressione Enter para pesquisar"' + aria + '>' +
          '<span class="ix-city-code" data-code="' + key + '">sem IBGE</span><input type="hidden" name="' + f.ibge + '"></div>'; break;
      default:
        ctl = '<input class="ix-input is-name" id="' + id + '" name="' + key + '" autocomplete="off" spellcheck="false"' + aria + '>';
    }
    return '<div class="ix-col ix-col-' + (f.span || 6) + ' ix-field" data-field="' + key + '">' +
      '<label class="ix-label" for="' + id + '">' + IX.esc(f.label) + req + counter + '</label>' + ctl +
      '<div class="ix-msg-slot" id="m-' + key + '" aria-live="polite">' + (f.hint ? '<div class="ix-hint">' + IX.esc(f.hint) + '</div>' : '') + '</div></div>';
  }

  function editorHtml(isNew) {
    let html = '<form class="ix-editor-form" id="ix-form" novalidate autocomplete="off"><input type="hidden" name="id">';
    Object.keys(C.sections).forEach(sk => {
      const keys = Object.keys(F).filter(k => F[k].section === sk);
      html += '<section class="ix-section" aria-labelledby="sec-' + sk + '"><h3 class="ix-section-title" id="sec-' + sk + '">' + IX.icon(C.sections[sk].icon) + IX.esc(C.sections[sk].label) + '</h3>' +
        '<div class="ix-grid">' + keys.map(fieldHtml).join('') + '</div>' +
        (sk === 'registro' ? '<div class="ix-mat" id="ix-mat"></div><div class="ix-hint" id="ix-next-hint" style="margin-top:8px"></div>' : '') + '</section>';
    });
    html += '<section class="ix-section"><h3 class="ix-section-title">' + IX.icon('clip') + 'Anexos</h3>' +
      '<div class="ix-drop" id="ix-drop">' + IX.icon('upload') + '<span><b>Arraste o PDF ou a imagem do termo</b> ou clique para escolher</span></div>' +
      '<div class="ix-hint" style="margin-top:6px">PDF, PNG, JPG ou WEBP. ' + (isNew ? 'Os arquivos são enviados ao salvar o registro.' : 'Os arquivos são enviados imediatamente.') + '</div></section>';
    html += '</form>';
    return '<div class="ix-editor has-doc" id="ix-editor">' + html + '<aside class="ix-editor-doc" id="ix-editor-doc" aria-label="Documento"></aside></div>';
  }

  function tipoLivro(get) {
    const tl = C.tipoLivro;
    if (typeof tl === 'string') return tl;
    return tl.map[get(tl.field)] || '';
  }

  let editorOpen = null;

  async function openEditor(id, opts) {
    opts = opts || {};
    if (editorOpen) return;
    const isNew = !id;
    const L = IX.layer({ kind: 'editor', title: isNew ? 'Novo registro de ' + C.label.toLowerCase() : 'Editar registro', sub: isNew ? 'Os campos com * são obrigatórios para a carga da CRC.' : 'Carregando…' });
    L.body.classList.add('ix-editor-scroll');
    L.body.innerHTML = editorHtml(isNew);
    L.tools.innerHTML = '<button type="button" class="ix-btn ix-btn-sm ix-btn-ghost" id="ix-toggle-doc" title="Mostrar/ocultar documento">' + IX.icon('panel') + '<span>Documento</span></button>';
    L.setFoot(
      '<button type="button" class="ix-crc" id="ix-crc" aria-live="polite"><span class="ix-crc-dot"></span><span id="ix-crc-text">Preencha os campos para validar com a CRC</span></button>' +
      '<span class="ix-spacer"></span>' +
      '<button type="button" class="ix-btn" data-dismiss-layer>Cancelar</button>' +
      (isNew ? '<button type="button" class="ix-btn" id="ix-save-new" title="Salva e já abre o próximo termo do mesmo livro">' + IX.icon('plus') + 'Salvar e novo</button>' : '') +
      '<button type="button" class="ix-btn ix-btn-type" id="ix-save">' + IX.icon('save') + 'Salvar <span class="ix-kbd">Ctrl S</span></button>');
    const form = $('#ix-form', L.body);
    const editor = $('#ix-editor', L.body);
    editorOpen = L;

    // Documento ao lado
    let pending = []; // arquivos locais (inclusão)
    const viewer = IX.docViewer($('#ix-editor-doc', L.body), {
      empty: 'Anexe a imagem do termo para digitar olhando o documento.',
      onPick: () => $('#ix-drop', L.body).click(),
      onDrop: files => handleFiles(files),
      onRemove: async (f) => {
        if (f.local) { pending = pending.filter(p => p !== f); URL.revokeObjectURL(f.url); viewer.set(pending); dirty = true; return; }
        if (!await IX.confirm({ title: 'Remover anexo', text: 'Remover "' + f.nome + '" deste registro?', okText: 'Remover', danger: true })) return;
        try { const r = await IX.api(API + '?action=anexo_remove', { body: { anexo_id: f.id } }); viewer.set(r.anexos); IX.toast('Anexo removido.', 'ok'); loadList(); }
        catch (e) { IX.toast(e.message, 'err'); }
      }
    });
    const docVisible = IX.store.get('editorDoc', true);
    editor.classList.toggle('has-doc', docVisible);
    $('#ix-toggle-doc', L.root).onclick = () => { const v = !editor.classList.contains('has-doc'); editor.classList.toggle('has-doc', v); IX.store.set('editorDoc', v); };

    IX.dropzone($('#ix-drop', L.body), files => handleFiles(files));
    async function handleFiles(files) {
      const ok = files.filter(f => /\.(pdf|png|jpe?g|webp)$/i.test(f.name));
      if (ok.length < files.length) IX.toast('Alguns arquivos foram ignorados: use PDF, PNG, JPG ou WEBP.', 'warn');
      if (!ok.length) return;
      editor.classList.add('has-doc');
      if (isNew || !form.id.value) {
        ok.forEach(file => { const f = { local: true, file, nome: file.name, ext: file.name.split('.').pop().toLowerCase(), url: URL.createObjectURL(file) }; pending.push(f); });
        viewer.set(pending); viewer.show(pending.length - 1); dirty = true;
      } else {
        const fd = new FormData(); fd.append('id', form.id.value); ok.forEach(f => fd.append('anexos[]', f));
        const drop = $('#ix-drop', L.body); drop.style.opacity = '.6';
        try { const r = await IX.api(API + '?action=anexo_upload', { body: fd }); viewer.set(r.anexos); viewer.show(r.anexos.length - 1); IX.toast(r.salvos.length + ' anexo(s) adicionado(s).', 'ok'); if (r.recusados.length) IX.toast('Não enviados: ' + r.recusados.join('; '), 'warn'); loadList(); }
        catch (e) { IX.toast(e.message, 'err'); }
        finally { drop.style.opacity = ''; }
      }
    }

    // Máscaras e comportamento dos campos
    Object.keys(F).forEach(k => {
      const f = F[k], el = form.elements[k];
      if (f.type === 'date') IX.mask.date(el);
      if (f.type === 'time') IX.mask.time(el);
      if (f.type === 'int') IX.mask.digits(el, f.digits);
      if (f.type === 'name') el.addEventListener('blur', () => { el.value = el.value.replace(/\s{2,}/g, ' ').trim().toUpperCase(); updateCounter(k); });
      if (f.type === 'city') {
        const pick = async () => {
          const r = await IX.pickCity(el.value);
          if (r) { el.value = r.nome; form.elements[f.ibge].value = r.ibge; setCityCode(k); changed(k); validateField(k); }
          el.focus();
        };
        el.addEventListener('click', pick);
        el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ' || e.key === 'F2') { e.preventDefault(); pick(); } if (e.key === 'Delete' || e.key === 'Backspace') { el.value = ''; form.elements[f.ibge].value = ''; setCityCode(k); changed(k); } });
      }
      el.addEventListener('input', () => { changed(k); updateCounter(k); });
      el.addEventListener('change', () => changed(k));
      el.addEventListener('blur', () => validateField(k));
    });
    function setCityCode(k) {
      const code = form.elements[F[k].ibge].value; const b = $('[data-code="' + k + '"]', form);
      b.textContent = code ? 'IBGE ' + code : 'sem IBGE'; b.classList.toggle('is-ok', /^\d{7}$/.test(code));
    }
    function updateCounter(k) {
      const c = $('[data-counter="' + k + '"]', form); if (!c) return;
      const n = form.elements[k].value.trim().replace(/\s{2,}/g, ' ').length;
      c.textContent = n + '/' + F[k].max; c.classList.toggle('is-over', n > F[k].max);
    }

    // ---------------- validação ----------------
    let dirty = false, server = null, checkSeq = 0, touched = {};
    const clientErr = {};
    const get = k => (form.elements[k] ? form.elements[k].value.trim() : '');

    function clientRule(k) {
      const f = F[k], v = get(k);
      if (f.type === 'city') { if (!v && f.required) return 'Campo obrigatório.'; if (v && !/^\d{7}$/.test(get(f.ibge))) return 'Selecione o município pela pesquisa (código IBGE).'; return ''; }
      if (!v) return f.required ? (f.type === 'select' ? 'Selecione uma opção.' : 'Campo obrigatório.') : '';
      if (f.type === 'int') { if (!/^\d+$/.test(v) || +v <= 0) return 'Informe um número maior que zero.'; if (String(+v).length > f.digits) return 'Máximo de ' + f.digits + ' dígitos (formato da matrícula).'; }
      if (f.type === 'date') { const iso = IX.date.iso(v); if (!iso) return 'Data inválida. Use DD/MM/AAAA.'; if (+iso.slice(0, 4) < 1800) return 'Ano parece incorreto.'; if (f.not_future && iso > IX.date.today()) return 'Não pode ser posterior a hoje.'; }
      if (f.type === 'time' && !/^([01]\d|2[0-3]):[0-5]\d$/.test(v)) return 'Hora inválida. Use HH:MM.';
      if (f.type === 'name') { const n = v.replace(/\s{2,}/g, ' '); if (/\d/.test(n)) return 'Não use números no nome.'; if (/[^\p{L}\s'\-.]/u.test(n)) return 'Caractere não aceito pela CRC: "' + n.match(/[^\p{L}\s'\-.]/u)[0] + '".'; if (f.max && n.length > f.max) return 'Máximo de ' + f.max + ' caracteres.'; }
      return '';
    }

    function validateField(k) {
      // campo vazio só é cobrado depois de uma tentativa de salvar (evita alertas enquanto a pessoa navega)
      if (!get(k) && !touched[k]) return;
      touched[k] = true;
      clientErr[k] = clientRule(k);
      paintField(k);
      paintCrc();
    }

    function paintField(k) {
      const box = $('[data-field="' + k + '"]', form); if (!box) return;
      const slot = $('#m-' + k, form);
      let err = touched[k] ? clientErr[k] : '';
      let warn = '';
      if (!err && server) {
        const se = (server.errors || []).find(x => x.field === k);
        const sw = (server.warnings || []).find(x => x.field === k);
        if (se && (touched[k] || server.forceShow)) err = se.msg;
        else if (sw) warn = sw.msg;
      }
      box.classList.toggle('has-error', !!err);
      box.classList.toggle('has-warn', !err && !!warn);
      const input = form.elements[k]; if (input) input.setAttribute('aria-invalid', err ? 'true' : 'false');
      slot.innerHTML = err ? '<div class="ix-msg">' + IX.icon('error') + '<span>' + IX.esc(err) + '</span></div>'
        : warn ? '<div class="ix-msg is-warn">' + IX.icon('alert') + '<span>' + IX.esc(warn) + '</span></div>'
        : (F[k].hint ? '<div class="ix-hint">' + IX.esc(F[k].hint) + '</div>' : '');
    }

    function paintMat() {
      const iso = IX.date.iso(get('data_registro'));
      const num = k => { const v = get(k).replace(/\D/g, ''); return v === '' ? '' : String(+v); };
      const m = IX.matricula(C.cns, tipoLivro(get), num('livro'), num('folha'), num('termo'), iso);
      const bad = { livro: num('livro').length > 5, folha: num('folha').length > 3, termo: num('termo').length > 7 };
      const serverMat = server && (server.errors || []).find(x => x.field === 'matricula');
      $('#ix-mat', form).innerHTML =
        '<div class="ix-mat-head">' + IX.icon('book') + '<span>Matrícula que será enviada</span>' +
        (m.value && !bad.livro && !bad.folha && !bad.termo ? '<b class="ix-mono">' + m.value + '</b>' : '') + '<span class="ix-spacer"></span>' +
        (C.cns ? '' : '<span class="ix-badge is-err">CNS da serventia não cadastrado</span>') +
        (serverMat && m.complete ? '<span class="ix-badge is-err">' + IX.esc(serverMat.msg) + '</span>' : '') + '</div>' +
        '<div class="ix-mat-seg">' + m.segs.map(s => {
          const isBad = bad[s.k] || (s.v && s.v.length > s.n);
          const cls = s.dv ? 'is-dv' : (isBad ? 'is-bad' : (s.v ? '' : 'is-empty'));
          return '<span class="' + cls + '" title="' + s.k + ' (' + s.n + ' dígitos)">' + (s.v ? IX.esc(s.v) : '·'.repeat(s.n)) + '<small>' + s.k + '</small></span>';
        }).join('') + '</div>';
    }

    function paintCrc() {
      const btn = $('#ix-crc', L.root), txt = $('#ix-crc-text', L.root);
      btn.classList.remove('is-ok', 'is-warn', 'is-err', 'is-busy');
      const cErr = Object.keys(clientErr).filter(k => clientErr[k] && touched[k]).length;
      if (server && server.busy) { btn.classList.add('is-busy'); txt.textContent = 'Validando com o XSD da CRC…'; return; }
      if (!server) { txt.textContent = cErr ? cErr + ' campo(s) com erro' : 'Preencha os campos para validar com a CRC'; if (cErr) btn.classList.add('is-err'); return; }
      const missing = Object.keys(F).filter(k => F[k].required && !get(k));
      const errs = (server.errors || []).filter(x => missing.indexOf(x.field) < 0 && !(missing.length && x.field === 'matricula'));
      const nE = errs.length, nW = (server.warnings || []).length + (server.duplicate ? 1 : 0);
      if (nE) { btn.classList.add('is-err'); txt.textContent = nE + (nE === 1 ? ' pendência impede' : ' pendências impedem') + ' a carga na CRC' + (missing.length ? ' (e faltam ' + missing.length + ' campos)' : ''); }
      else if (missing.length) { txt.textContent = missing.length === 1 ? 'Falta 1 campo obrigatório' : 'Faltam ' + missing.length + ' campos obrigatórios'; }
      else if (nW) { btn.classList.add('is-warn'); txt.textContent = 'Válido no XSD da CRC, com ' + nW + (nW === 1 ? ' alerta' : ' alertas'); }
      else { btn.classList.add('is-ok'); txt.textContent = 'Pronto para a carga da CRC'; }
    }

    const formData = () => { const fd = new FormData(form); return fd; };

    const runCheck = IX.debounce(async () => {
      const seq = ++checkSeq;
      server = Object.assign({}, server || {}, { busy: true }); paintCrc();
      const fd = formData(); fd.append('action', 'check');
      try {
        const r = await IX.api(API + '?action=check', { body: fd });
        if (seq !== checkSeq) return;
        server = r;
        Object.keys(F).forEach(paintField); paintMat(); paintCrc();
        if (r.duplicate) showDupHint(r.duplicate);
        else $('#ix-next-hint', form).dataset.dup = '';
      } catch (e) {
        if (seq !== checkSeq) return;
        server = null; paintCrc();
      }
    }, 550);

    function showDupHint(dup) {
      const h = $('#ix-next-hint', form);
      h.innerHTML = '<span class="ix-msg is-warn">' + IX.icon('alert') + '<span>Já existe registro com o mesmo livro, folha, termo e data: <b>' + IX.esc(dup.nome) + '</b>.</span></span>';
    }

    function changed(k) {
      dirty = true;
      if (['livro', 'folha', 'termo', 'data_registro', 'tipo_casamento'].indexOf(k) >= 0) paintMat();
      if (touched[k]) { clientErr[k] = clientRule(k); paintField(k); }
      runCheck();
    }

    // Sugestão do próximo termo
    const suggestNext = async () => {
      const livro = get('livro'); const h = $('#ix-next-hint', form);
      if (!livro || form.id.value) { if (!form.id.value) h.innerHTML = ''; return; }
      if (C.tipo === 'casamento' && !get('tipo_casamento')) { h.innerHTML = 'Selecione o tipo de casamento para ver o último termo deste livro.'; return; }
      try {
        const p = { action: 'next', livro }; if (C.tipo === 'casamento') p.tipo_casamento = get('tipo_casamento');
        const r = await IX.api(API, { params: p });
        if (!r.next) { h.innerHTML = 'Livro ' + IX.esc(livro) + ' ainda sem registros indexados.'; return; }
        h.innerHTML = 'Último termo indexado neste livro: <b>' + r.next.ultimo_termo + '</b> (folha ' + r.next.ultima_folha + ', registro em ' + IX.date.br(r.next.ultima_data) + '). ' +
          (get('termo') ? '' : '<button type="button" class="ix-btn ix-btn-sm" data-use-next="' + r.next.termo + '" data-folha="' + r.next.ultima_folha + '">Usar termo ' + r.next.termo + '</button>');
      } catch (e) { h.innerHTML = ''; }
    };
    form.elements.livro.addEventListener('change', suggestNext);
    form.elements.livro.addEventListener('blur', suggestNext);
    if (form.elements.tipo_casamento) form.elements.tipo_casamento.addEventListener('change', suggestNext);
    form.addEventListener('mousedown', e => { if (e.target.closest('[data-use-next]')) e.preventDefault(); });
    form.addEventListener('click', e => {
      const b = e.target.closest('[data-use-next]'); if (!b) return;
      form.elements.termo.value = b.dataset.useNext;
      if (!get('folha') && String(b.dataset.folha).length <= 3) form.elements.folha.value = b.dataset.folha;
      changed('termo'); validateField('termo'); $('#ix-next-hint', form).innerHTML = '';
      const firstName = Object.keys(F).find(k => F[k].type === 'name' || F[k].section !== 'registro');
      form.elements[firstName].focus();
    });

    // CRC: detalhes
    $('#ix-crc', L.root).addEventListener('click', () => showCrcDetails());
    function showCrcDetails() {
      Object.keys(F).forEach(k => { touched[k] = true; clientErr[k] = clientRule(k); paintField(k); });
      const res = server || { errors: [], warnings: [] };
      const local = Object.keys(clientErr).filter(k => clientErr[k]).map(k => ({ field: k, msg: clientErr[k], source: 'regra' }));
      const merged = { errors: local.concat((res.errors || []).filter(e => !local.some(l => l.field === e.field))), warnings: res.warnings || [] };
      const D = IX.layer({ kind: 'dialog', title: 'Conformidade com a CRC',
        body: '<div class="ix-confirm-body">' + crcListHtml(merged) +
          (res.xml ? '<details><summary class="ix-hint" style="cursor:pointer">Ver o XML deste registro, como sairá na carga</summary><pre class="ix-xml">' + IX.esc(res.xml) + '</pre></details>' : '') +
          '<p class="ix-hint">A validação usa as mesmas regras do gerador de carga e o arquivo XSD oficial da CRC (catalogo-crc.xsd).</p></div>' });
      D.body.addEventListener('click', e => { const a = e.target.closest('[data-goto]'); if (!a) return; e.preventDefault(); D.close(true); focusField(a.dataset.goto); });
      D.open();
    }
    function focusField(k) {
      const el = form.elements[k] || form.elements.livro;
      if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); setTimeout(() => el.focus({ preventScroll: true }), 250); }
    }

    // ---------------- carregar dados ----------------
    function fill(r) {
      form.id.value = r.id || '';
      Object.keys(F).forEach(k => {
        const f = F[k]; let v = r[k];
        if (v == null) v = '';
        if (f.type === 'date') v = IX.date.br(v);
        if (f.type === 'time') v = String(v).slice(0, 5);
        form.elements[k].value = v;
        if (f.type === 'city') { form.elements[f.ibge].value = r[f.ibge] || ''; setCityCode(k); }
        updateCounter(k);
      });
    }

    if (!isNew) {
      try {
        const r = (await IX.api(API, { params: { action: 'get', id } })).row;
        fill(r);
        L.title(r._nome || 'Editar registro').sub('Termo ' + IX.esc(r.termo) + ', livro ' + IX.esc(r.livro) + ', folha ' + IX.esc(r.folha) + (r._cadastrado_por ? '. Cadastrado por ' + IX.esc(r._cadastrado_por) : ''));
        viewer.set(r._anexos_lista);
        if (!r._anexos_lista.length && !docVisible) editor.classList.remove('has-doc');
        Object.keys(F).forEach(k => touched[k] = true);
        server = { forceShow: true };
      } catch (e) { IX.toast(e.message, 'err'); editorOpen = null; L.close(true); return; }
    } else if (opts.preset) {
      fill(opts.preset);
    }
    paintMat();
    if (!isNew || opts.preset) runCheck();
    dirty = false;

    L.onBeforeClose = async () => {
      if (!dirty) return true;
      return IX.confirm({ title: 'Descartar alterações?', text: 'Há informações não salvas neste registro.', okText: 'Descartar', danger: true });
    };
    L.onClose = () => { editorOpen = null; pending.forEach(p => URL.revokeObjectURL(p.url)); d.removeEventListener('keydown', keys); };

    // ---------------- salvar ----------------
    let saving = false;
    async function save(andNew, extra) {
      if (saving) return;
      Object.keys(F).forEach(k => { touched[k] = true; clientErr[k] = clientRule(k); paintField(k); });
      const firstErr = Object.keys(F).find(k => clientErr[k]);
      if (firstErr) { paintCrc(); IX.toast('Corrija os campos destacados antes de salvar.', 'warn'); focusField(firstErr); return; }
      const btn = $(andNew ? '#ix-save-new' : '#ix-save', L.root);
      btn.classList.add('is-loading');
      saving = true;
      let retry = null;
      const fd = formData();
      pending.forEach(p => fd.append('anexos[]', p.file, p.nome));
      Object.keys(extra || {}).forEach(k => fd.append(k, extra[k]));
      try {
        const r = await IX.api(API + '?action=save', { body: fd });
        dirty = false;
        IX.toast('Matrícula ' + (r.matricula || '—') + (r.anexos_salvos.length ? '. ' + r.anexos_salvos.length + ' anexo(s) enviado(s).' : '.'), 'ok', isNew ? 'Registro cadastrado' : 'Alterações salvas');
        if (r.anexos_recusados && r.anexos_recusados.length) IX.toast('Não enviados: ' + r.anexos_recusados.join('; '), 'warn');
        loadList(); loadStats();
        if (opts.onSaved) opts.onSaved(r);
        if (andNew) {
          const keep = { livro: get('livro'), folha: get('folha'), data_registro: IX.date.iso(get('data_registro')), termo: String((+get('termo') || 0) + 1) };
          if (C.tipo === 'casamento') keep.tipo_casamento = get('tipo_casamento');
          await L.close(true);
          openEditor(0, { preset: keep, focus: true });
        } else {
          L.close(true);
        }
      } catch (e) {
        const data = e.data || {};
        if (data.status === 'invalid') {
          server = { errors: data.errors, warnings: data.warnings, forceShow: true };
          Object.keys(F).forEach(paintField); paintMat(); paintCrc();
          IX.toast(data.message, 'err', 'Não foi salvo');
          const fe = (data.errors || []).find(x => x.field && F[x.field]); if (fe) focusField(fe.field);
        } else if (data.status === 'duplicate' && data.can_force === false) {
          const dd = data.duplicate;
          await IX.confirm({ title: 'Registro já cadastrado',
            html: '<p>Já existe um registro ativo com a mesma matrícula (mesmo livro, folha, termo e ano):</p>' +
              '<ul class="ix-crc-list"><li class="is-err">' + IX.icon('error') + '<div><b>' + IX.esc(dd.nome) + '</b><br>Termo ' + IX.esc(dd.termo) + ', livro ' + IX.esc(dd.livro) + ', folha ' + IX.esc(dd.folha) + ', registro em ' + IX.esc(dd.data_registro) + '</div></li></ul>' +
              '<p>A CRC não aceita duas matrículas iguais. Confira os dados digitados ou edite o registro existente.</p>',
            okText: 'Entendi', cancelText: 'Fechar' });
        } else if (data.status === 'duplicate') {
          const dd = data.duplicate;
          const ok = await IX.confirm({ title: 'Registro possivelmente duplicado',
            html: '<p>Já existe um registro ativo com o mesmo <b>livro, folha, termo e data de registro</b>:</p>' +
              '<ul class="ix-crc-list"><li class="is-warn">' + IX.icon('alert') + '<div><b>' + IX.esc(dd.nome) + '</b><br>Termo ' + IX.esc(dd.termo) + ', livro ' + IX.esc(dd.livro) + ', folha ' + IX.esc(dd.folha) + ', registro em ' + IX.esc(dd.data_registro) + '</div></li></ul>' +
              warnList(data.warnings) + '<p>Salvar mesmo assim?</p>',
            okText: 'Salvar mesmo assim' });
          if (ok) retry = { forcar: 1, confirmar_alertas: 1 };
        } else if (data.status === 'warnings') {
          const ok = await IX.confirm({ title: 'Confirme os alertas', html: '<p>O registro é válido para a CRC, mas há pontos para conferir:</p>' + warnList(data.warnings) + '<p>Salvar assim mesmo?</p>', okText: 'Salvar' });
          if (ok) retry = Object.assign({}, extra, { confirmar_alertas: 1 });
        } else {
          IX.toast(e.message, 'err', 'Não foi salvo');
        }
      } finally { btn.classList.remove('is-loading'); saving = false; }
      if (retry) return save(andNew, retry);
    }
    function warnList(ws) {
      if (!ws || !ws.length) return '';
      return '<ul class="ix-crc-list">' + ws.map(x => '<li class="is-warn">' + IX.icon('alert') + '<div>' + IX.esc(x.msg) + '</div></li>').join('') + '</ul>';
    }
    $('#ix-save', L.root).onclick = () => save(false);
    if (isNew) $('#ix-save-new', L.root).onclick = () => save(true);
    function keys(e) {
      if (IX.topLayer() !== L) return;
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(false); }
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && isNew) { e.preventDefault(); save(true); }
    }
    d.addEventListener('keydown', keys);
    form.addEventListener('submit', e => { e.preventDefault(); save(false); });

    L.open();
    setTimeout(() => {
      let target = form.elements.livro;
      if (opts.preset && opts.preset.termo) target = form.elements[Object.keys(F).find(k => F[k].section !== 'registro')];
      if (target) target.focus();
      if (opts.preset) suggestNext();
    }, 120);
  }

  /* ======================================================================
     Pendências CRC (auditoria)
     ====================================================================== */
  async function openAudit() {
    const hasF = Object.keys(state.filters).length > 0;
    const L = IX.layer({ kind: 'dialog', cls: 'is-wide', title: 'Pendências para a carga da CRC',
      sub: hasF ? 'Registros que atendem aos filtros atuais' : 'Todos os registros ativos de ' + C.label.toLowerCase() });
    L.body.innerHTML = '<div class="ix-empty"><div class="ix-empty-mark">' + IX.icon('shield') + '</div><h3>Verificando registros…</h3><p>Cada registro é montado como na carga e validado contra o XSD oficial.</p></div>';
    L.tools.innerHTML = '<button type="button" class="ix-btn ix-btn-sm" id="ix-audit-again">' + IX.icon('refresh') + 'Verificar novamente</button>';
    L.open();
    const run = async () => {
      try {
        const r = await IX.api(API, { params: Object.assign({ action: 'audit' }, state.filters) });
        if (!r.items.length) {
          L.body.innerHTML = '<div class="ix-empty"><div class="ix-empty-mark" style="background:var(--ix-ok-soft);color:var(--ix-ok)">' + IX.icon('check') + '</div><h3>Nenhuma pendência</h3><p>' + IX.fmtInt(r.checked) + ' registro(s) verificados: todos prontos para a carga da CRC.</p></div>';
          return;
        }
        L.body.innerHTML = '<div class="ix-audit-sum"><span><b>' + IX.fmtInt(r.checked) + '</b> verificados</span>' +
          '<span><b style="color:var(--ix-err)">' + IX.fmtInt(r.com_erros) + '</b> com erros que impedem a carga</span>' +
          '<span><b style="color:var(--ix-warn)">' + IX.fmtInt(r.com_alertas) + '</b> apenas com alertas</span>' +
          (r.limited ? '<span>Mostrando os primeiros 20.000. Use filtros para refinar.</span>' : '') + '</div>' +
          r.items.map(it => '<div class="ix-audit-item" data-id="' + it.id + '"><div>' +
            '<div class="ix-card-ref"><span>Termo <b>' + IX.esc(it.termo) + '</b></span><span>Livro <b>' + IX.esc(it.livro) + '</b></span><span>Folha <b>' + IX.esc(it.folha) + '</b></span><span>' + IX.esc(it.data_registro) + '</span></div>' +
            '<h5>' + IX.esc(it.nome || '(sem nome)') + '</h5><ul>' +
            it.errors.map(x => '<li class="is-err">' + IX.icon('error') + '<span>' + IX.esc(x.msg) + '</span></li>').join('') +
            it.warnings.map(x => '<li class="is-warn">' + IX.icon('alert') + '<span>' + IX.esc(x.msg) + '</span></li>').join('') +
            '</ul></div><div><button type="button" class="ix-btn ix-btn-sm ix-btn-type" data-fix="' + it.id + '">' + IX.icon('edit') + 'Corrigir</button></div></div>').join('');
      } catch (e) { L.body.innerHTML = '<div class="ix-empty">' + IX.esc(e.message) + '</div>'; }
    };
    $('#ix-audit-again', L.root).onclick = run;
    L.body.addEventListener('click', e => {
      const b = e.target.closest('[data-fix]'); if (!b) return;
      const item = b.closest('.ix-audit-item');
      openEditor(+b.dataset.fix, { onSaved: () => { item.style.opacity = '.45'; b.outerHTML = '<span class="ix-badge is-ok">' + IX.icon('check') + 'Corrigido</span>'; } });
    });
    run();
  }

  /* ======================================================================
     Início
     ====================================================================== */
  function init() {
    const qs0 = new URLSearchParams(location.search);
    buildFilters();
    bindList();
    loadStats();
    loadList();
    $('#ix-new').addEventListener('click', () => openEditor(0));
    $('#ix-audit').addEventListener('click', openAudit);
    d.addEventListener('keydown', e => {
      if (IX.topLayer()) return;
      if (e.altKey && (e.key === 'n' || e.key === 'N')) { e.preventDefault(); openEditor(0); }
      if (e.key === '/' && !/INPUT|SELECT|TEXTAREA/.test(d.activeElement.tagName)) { e.preventDefault(); const q = $('#f-q'); if (q) q.focus(); }
    });
    if (qs0.get('novo')) openEditor(0);
    if (qs0.get('editar')) openEditor(+qs0.get('editar'));
  }
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', init); else init();
})(window, document);
