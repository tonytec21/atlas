/* ==========================================================================
   ATLAS · INDEXADOR — Exportação de carga CRC
   ========================================================================== */
(function (w, d) {
  'use strict';
  const IX = w.IX, C = w.CG_CFG;
  const $ = (s, r) => (r || d).querySelector(s), $$ = (s, r) => Array.from((r || d).querySelectorAll(s));
  const API = 'api.php';
  const PER = 50;
  let rows = [], sel = new Set(), page = 1, val = {}; // val[id] = {e:[], w:[]}

  $$('[data-mask=date]').forEach(IX.mask.date);
  $$('[data-mask=digits]').forEach(i => IX.mask.digits(i));
  $('#cg-more').onclick = () => { const b = $('#cg-filters'); b.classList.toggle('is-open'); $('#cg-more span').textContent = b.classList.contains('is-open') ? 'Menos filtros' : 'Mais filtros'; };

  $('#cg-form').addEventListener('submit', async e => {
    e.preventDefault();
    const params = { action: 'list', tipo: C.tipo };
    new FormData(e.target).forEach((v, k) => { v = String(v).trim(); if (v) params[k] = v; });
    for (const k of Object.keys(params)) if (/_(de|ate)$/.test(k) && /\//.test(params[k]) && !IX.date.iso(params[k])) { IX.toast('Data inválida: use DD/MM/AAAA.', 'warn'); return; }
    const btn = e.target.querySelector('[type=submit]'); btn.classList.add('is-loading');
    try {
      const r = await IX.api(API, { params });
      rows = r.rows; sel = new Set(rows.map(x => x.id)); val = {}; page = 1;
      if (r.limited) IX.toast('Foram listados os primeiros 50.000 registros. Refine os filtros para cargas maiores.', 'warn');
      $('#cg-summary').classList.add('ix-hidden');
      render();
    } catch (err) { IX.toast(err.message, 'err'); }
    finally { btn.classList.remove('is-loading'); }
  });
  $('#cg-form').addEventListener('reset', () => setTimeout(() => {}, 0));

  function status(id) {
    const v = val[id];
    if (!v) return '<span class="ix-badge" style="background:var(--ix-sunken);color:var(--ix-muted)">não verificado</span>';
    if (v.e.length) return '<button type="button" class="ix-badge is-err" style="border:0;cursor:pointer" data-why="' + id + '">' + IX.icon('error') + v.e.length + (v.e.length === 1 ? ' erro' : ' erros') + '</button>';
    if (v.w.length) return '<button type="button" class="ix-badge is-warn" style="border:0;cursor:pointer" data-why="' + id + '">' + IX.icon('alert') + v.w.length + (v.w.length === 1 ? ' alerta' : ' alertas') + '</button>';
    return '<span class="ix-badge is-ok">' + IX.icon('check') + 'válido</span>';
  }

  function render() {
    const total = rows.length, pages = Math.max(1, Math.ceil(total / PER));
    page = Math.min(page, pages);
    const slice = rows.slice((page - 1) * PER, page * PER);
    $('#cg-count').innerHTML = total ? '<b>' + IX.fmtInt(sel.size) + '</b> de ' + IX.fmtInt(total) + ' selecionados' : 'Nenhum registro encontrado';
    $('#cg-all').checked = total > 0 && sel.size === total;
    $('#cg-all').indeterminate = sel.size > 0 && sel.size < total;
    $('#cg-validate').disabled = !sel.size;
    $('#cg-export').disabled = !sel.size;
    $('#cg-export-lbl').textContent = sel.size ? 'Gerar XML (' + IX.fmtInt(sel.size) + ')' : 'Gerar XML';

    if (!total) {
      $('#cg-tbody').innerHTML = '<tr><td colspan="8"><div class="ix-empty"><div class="ix-empty-mark">' + IX.icon('search') + '</div><h3>Nada encontrado</h3><p>Nenhum registro ativo atende aos filtros.</p></div></td></tr>';
      $('#cg-cards').innerHTML = ''; $('#cg-pager').innerHTML = ''; return;
    }
    $('#cg-tbody').innerHTML = slice.map(r => '<tr data-id="' + r.id + '">' +
      '<td><input type="checkbox" data-sel="' + r.id + '"' + (sel.has(r.id) ? ' checked' : '') + ' aria-label="Selecionar termo ' + IX.esc(r.termo) + '" style="width:18px;height:18px;accent-color:var(--ix-type)"></td>' +
      '<td class="ix-num">' + IX.esc(r.termo) + '</td><td class="ix-num">' + IX.esc(r.livro) + '</td><td class="ix-num">' + IX.esc(r.folha) + '</td>' +
      '<td class="ix-main"><div class="ix-primary-text">' + IX.esc(r.nome || '—') + '</div><div class="ix-sub ix-mono">' + IX.esc(r.matricula || 'sem matrícula') + '</div></td>' +
      '<td class="ix-num">' + (IX.date.br(r.data_ato) || '—') + '</td><td class="ix-num">' + (IX.date.br(r.data_registro) || '—') + '</td>' +
      '<td>' + status(r.id) + '</td></tr>').join('');
    $('#cg-cards').innerHTML = slice.map(r => '<label class="ix-card-row" style="grid-template-columns:auto 1fr;column-gap:12px;margin:0">' +
      '<input type="checkbox" data-sel="' + r.id + '"' + (sel.has(r.id) ? ' checked' : '') + ' style="width:20px;height:20px;accent-color:var(--ix-type);grid-row:span 3">' +
      '<div class="ix-card-ref"><span>Termo <b>' + IX.esc(r.termo) + '</b></span><span>Livro <b>' + IX.esc(r.livro) + '</b></span><span>Folha <b>' + IX.esc(r.folha) + '</b></span></div>' +
      '<div class="ix-card-title">' + IX.esc(r.nome || '—') + '</div><div>' + status(r.id) + '</div></label>').join('');
    $('#cg-pager').innerHTML = '<span class="ix-pager-info">' + ((page - 1) * PER + 1) + '–' + Math.min(total, page * PER) + ' de ' + IX.fmtInt(total) + '</span><span class="ix-spacer"></span>' +
      '<button type="button" class="ix-btn ix-btn-sm" data-pg="-1"' + (page <= 1 ? ' disabled' : '') + '>' + IX.icon('left') + 'Anterior</button>' +
      '<span class="ix-pager-info">Página ' + page + ' de ' + pages + '</span>' +
      '<button type="button" class="ix-btn ix-btn-sm" data-pg="1"' + (page >= pages ? ' disabled' : '') + '>Próxima' + IX.icon('right') + '</button>';
  }

  d.addEventListener('change', e => {
    const c = e.target.closest('[data-sel]'); if (!c) return;
    const id = +c.dataset.sel; if (c.checked) sel.add(id); else sel.delete(id);
    render();
  });
  $('#cg-all').addEventListener('change', e => { sel = e.target.checked ? new Set(rows.map(r => r.id)) : new Set(); render(); });
  $('#cg-pager').addEventListener('click', e => { const b = e.target.closest('[data-pg]'); if (!b || b.disabled) return; page += +b.dataset.pg; render(); });
  $('#cg-results').addEventListener('click', e => {
    const b = e.target.closest('[data-why]'); if (!b) return;
    e.preventDefault();
    const id = +b.dataset.why, r = rows.find(x => x.id === id), v = val[id];
    const L = IX.layer({ kind: 'dialog', title: r.nome || 'Registro', sub: 'Termo ' + IX.esc(r.termo) + ', livro ' + IX.esc(r.livro) + ', folha ' + IX.esc(r.folha),
      body: '<div class="ix-confirm-body"><ul class="ix-crc-list">' +
        v.e.map(m => '<li class="is-err">' + IX.icon('error') + '<div>' + IX.esc(m) + '</div></li>').join('') +
        v.w.map(m => '<li class="is-warn">' + IX.icon('alert') + '<div>' + IX.esc(m) + '</div></li>').join('') + '</ul></div>',
      foot: '<span class="ix-spacer"></span><a class="ix-btn ix-btn-type" href="../' + C.tipo + '/index.php?editar=' + id + '" target="_blank" rel="noopener">' + IX.icon('edit') + 'Corrigir no indexador</a>' });
    L.open();
  });

  async function validate() {
    const ids = Array.from(sel);
    const btn = $('#cg-validate'); btn.classList.add('is-loading');
    try {
      for (let i = 0; i < ids.length; i += 3000) {
        const r = await IX.api(API + '?action=validate&tipo=' + C.tipo, { body: { ids: ids.slice(i, i + 3000).join(',') } });
        Object.assign(val, r.result);
      }
      const nE = ids.filter(id => val[id] && val[id].e.length).length;
      const nW = ids.filter(id => val[id] && !val[id].e.length && val[id].w.length).length;
      const sum = $('#cg-summary');
      sum.classList.remove('ix-hidden');
      sum.innerHTML = '<span><b>' + IX.fmtInt(ids.length) + '</b> verificados</span>' +
        '<span><b style="color:var(--ix-ok)">' + IX.fmtInt(ids.length - nE) + '</b> aceitos pelo XSD e regras</span>' +
        '<span><b style="color:var(--ix-err)">' + IX.fmtInt(nE) + '</b> com erros</span>' +
        '<span><b style="color:var(--ix-warn)">' + IX.fmtInt(nW) + '</b> com alertas</span>' +
        (nE ? '<button type="button" class="ix-btn ix-btn-sm" id="cg-only-err">' + IX.icon('error') + 'Mostrar só os com erro</button>' : '');
      if (nE) $('#cg-only-err').onclick = () => { rows = rows.filter(r => val[r.id] && val[r.id].e.length); sel = new Set(rows.map(r => r.id)); page = 1; render(); IX.toast('Seleção reduzida aos ' + rows.length + ' registros com erro.', 'info'); };
      render();
      return { nE, nW };
    } catch (e) { IX.toast(e.message, 'err'); return null; }
    finally { btn.classList.remove('is-loading'); }
  }
  $('#cg-validate').addEventListener('click', validate);

  $('#cg-export').addEventListener('click', async () => {
    const ids = Array.from(sel);
    const pend = ids.filter(id => !val[id]);
    let res = { nE: ids.filter(id => val[id] && val[id].e.length).length };
    if (pend.length) { res = await validate(); if (!res) return; }
    const f = $('#cg-download');
    f.ids.value = ids.join(',');
    f.somente_validos.value = '';
    if (res.nE) {
      const L = IX.layer({ kind: 'dialog', title: 'Há registros com erro',
        body: '<div class="ix-confirm-body"><p><b>' + IX.fmtInt(res.nE) + '</b> dos ' + IX.fmtInt(ids.length) + ' registros selecionados não passam na validação e seriam recusados pela CRC.</p>' +
          '<p class="ix-hint">O recomendado é gerar a carga só com os válidos e corrigir os demais no indexador.</p></div>',
        foot: '<span class="ix-spacer"></span><button type="button" class="ix-btn" data-g="all">Gerar com todos</button>' +
          '<button type="button" class="ix-btn ix-btn-type" data-g="ok">Gerar só os ' + IX.fmtInt(ids.length - res.nE) + ' válidos</button>' });
      L.foot.addEventListener('click', e => {
        const b = e.target.closest('[data-g]'); if (!b) return;
        if (b.dataset.g === 'ok') { if (ids.length - res.nE <= 0) { IX.toast('Nenhum registro válido para exportar.', 'warn'); return; } f.somente_validos.value = '1'; }
        L.close(true); f.submit(); IX.toast('Gerando ' + C.arquivo + '…', 'ok');
      });
      L.open();
      return;
    }
    f.submit();
    IX.toast('Gerando ' + C.arquivo + '…', 'ok');
  });
})(window, document);
