/* Atlas Iris 2.x — área de trabalho (visualizador, extração página a página, revisão) */
(function () {
  'use strict';
  var C = window.IRIS || {};
  var CFG = C.cfg || {};
  if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = 'vendor/pdfjs/pdf.worker.min.js';

  /* ============================== utilidades ============================== */
  function el(id) { return document.getElementById(id); }
  function qs(s, r) { return (r || document).querySelector(s); }
  function qsa(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function humano(n) { n = +n || 0; if (n < 1024) return n + ' B'; if (n < 1048576) return (n / 1024).toFixed(1) + ' KB'; return (n / 1048576).toFixed(1) + ' MB'; }
  function num(n) { return (+n || 0).toLocaleString('pt-BR'); }
  function hora() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
  function lsGet(k, d) { try { var v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } }
  function lsSet(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { } }
  function erroAbort(e) { return e && (e.name === 'AbortError' || e.abortado); }
  function toast(msg, icon) { if (window.Swal) Swal.fire({ toast: true, position: 'bottom-end', timer: 2200, showConfirmButton: false, icon: icon || 'success', title: msg }); }
  function alerta(t, m, i) { return Swal.fire(t, m, i || 'warning'); }
  function uid() { return 'd' + Math.random().toString(36).slice(2, 9); }

  function api(acao, dados, opt) {
    opt = opt || {};
    var fd;
    if (dados instanceof FormData) fd = dados;
    else { fd = new FormData(); Object.keys(dados || {}).forEach(function (k) { if (dados[k] !== undefined && dados[k] !== null) fd.append(k, dados[k]); }); }
    fd.append('acao', acao); fd.append('csrf', C.csrf);
    return fetch('api.php', { method: 'POST', body: fd, credentials: 'same-origin', signal: opt.signal }).then(function (r) {
      if (opt.blob) {
        var ct = r.headers.get('Content-Type') || '';
        if (ct.indexOf('application/json') === 0) return r.json().then(function (j) { throw new Error(j.message || 'Falha.'); });
        return r.blob();
      }
      return r.text().then(function (t) {
        var j; try { j = JSON.parse(t); } catch (e) { throw new Error('Resposta inválida do servidor: ' + t.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180)); }
        if (j.status !== 'success') { var er = new Error(j.message || 'Falha na operação.'); if (j.login) er.login = true; throw er; }
        return j;
      });
    });
  }
  function baixarBlob(blob, nome) {
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nome;
    document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
  }
  function canvasBlob(cv, q) { return new Promise(function (res) { cv.toBlob(function (b) { res(b); }, 'image/jpeg', q || 0.9); }); }
  function novoCanvas(w, h) { var c = document.createElement('canvas'); c.width = Math.max(1, Math.round(w)); c.height = Math.max(1, Math.round(h)); return c; }

  /* ============================== estado ============================== */
  var docs = [];          // documentos da fila
  var atual = null;       // documento em exibição
  var pg = 0;             // página em exibição (índice)
  var zoom = 1;
  var modoRegiao = false, selFrac = null;
  var OPT = Object.assign({ modo: 'transcricao', precisao: CFG.precisao_padrao || 'padrao', tipo: 'auto', marcadores: true, digital: false }, lsGet('iris_opts', {}));
  if (['transcricao', 'dados', 'completo'].indexOf(OPT.modo) < 0) OPT.modo = 'transcricao';
  var UNIR_PADRAO = lsGet('iris_unir', true);

  /* ============================== carga de arquivos ============================== */
  function tipoDoArquivo(f) {
    var n = (f.name || '').toLowerCase(), t = (f.type || '').toLowerCase();
    if (t === 'application/pdf' || /\.pdf$/.test(n)) return 'pdf';
    if (t === 'image/tiff' || /\.tiff?$/.test(n)) return 'tiff';
    if (t === 'image/heic' || t === 'image/heif' || /\.hei[cf]$/.test(n)) return 'bruto';
    if (/^image\/(jpeg|png|webp|gif|bmp)$/.test(t) || /\.(jpe?g|png|webp|gif|bmp)$/.test(n)) return 'img';
    return null;
  }

  function adicionarArquivos(lista) {
    var arr = Array.prototype.slice.call(lista || []);
    if (!arr.length) return;
    if (!C.temChave) { alerta('Configuração necessária', C.isAdmin ? 'Cadastre a chave da API do Gemini em "Configurar".' : 'Peça ao administrador para configurar a chave da API.'); return; }
    var aceitos = [];
    arr.forEach(function (f) {
      var t = tipoDoArquivo(f);
      if (!t) { toast('Formato não suportado: ' + f.name, 'warning'); return; }
      if (f.size > 300 * 1048576) { toast('Arquivo muito grande: ' + f.name, 'warning'); return; }
      aceitos.push({ f: f, t: t });
    });
    aceitos.forEach(function (a, i) {
      var d = novoDoc(a.f, a.t);
      docs.push(d);
      carregarDoc(d).then(function () { if (d === atual) exibirDoc(d); renderFila(); verificarDuplicado(d); });
      if (i === 0 && (!atual || atual.status !== 'processando')) exibirDoc(d);
    });
    renderFila();
  }

  function novoDoc(file, tipo) {
    return {
      uid: uid(), file: file, nome: file ? file.name : 'documento', tamanho: file ? file.size : 0, mime: file ? file.type : '',
      tipoArq: tipo, paginas: [], carregado: false, erroCarga: '', status: 'novo', texto: '', dados: null, avisos: [],
      uso: { entrada: 0, saida: 0 }, usoPendente: { entrada: 0, saida: 0 }, ms: 0, histId: null, dirty: false, unido: UNIR_PADRAO,
      snapshot: null, modelo: '', hash: null, cache: new Map(), nativo: new Map(), realce: false, opcoes: null
    };
  }

  function carregarDoc(d) {
    var p;
    if (d.tipoArq === 'pdf') p = d.file.arrayBuffer().then(function (buf) {
      var task = pdfjsLib.getDocument({ data: buf, isEvalSupported: false });
      task.onPassword = function (cb, motivo) {
        Swal.fire({ title: 'PDF protegido', text: motivo === 2 ? 'Senha incorreta. Tente novamente:' : 'Informe a senha para abrir "' + d.nome + '":', input: 'password', showCancelButton: true, confirmButtonText: 'Abrir', cancelButtonText: 'Cancelar' })
          .then(function (r) { if (r.isConfirmed) cb(r.value); else task.destroy(); });
      };
      return task.promise.then(function (pdf) { d.pdf = pdf; d.paginas = paginasVazias(pdf.numPages); detectarTextoDigital(d); });
    });
    else if (d.tipoArq === 'tiff') p = d.file.arrayBuffer().then(function (buf) {
      var ifds = UTIF.decode(buf).filter(function (i) { return i && i.t256 && i.t257; });
      if (!ifds.length) throw new Error('TIFF sem páginas legíveis.');
      d.tiff = { buf: buf, ifds: ifds }; d.paginas = paginasVazias(ifds.length);
    });
    else if (d.tipoArq === 'img') p = (window.createImageBitmap ? createImageBitmap(d.file, { imageOrientation: 'from-image' }).catch(function () { return carregarImagemEl(d.file); }) : carregarImagemEl(d.file))
      .then(function (img) { d.img = img; d.paginas = paginasVazias(1); });
    else p = Promise.resolve().then(function () { d.paginas = paginasVazias(1); });
    return p.then(function () { d.carregado = true; calcularHash(d); })
      .catch(function (e) { d.carregado = true; d.erroCarga = (e && e.message) || 'Não foi possível abrir o arquivo.'; if (d.tipoArq !== 'bruto') { d.tipoArq = 'bruto'; d.paginas = paginasVazias(1); } });
  }
  function carregarImagemEl(file) {
    return new Promise(function (res, rej) { var i = new Image(); i.onload = function () { res(i); }; i.onerror = function () { rej(new Error('Imagem inválida.')); }; i.src = URL.createObjectURL(file); });
  }
  function paginasVazias(n) {
    var a = [];
    for (var i = 0; i < n; i++) a.push({ n: i + 1, rot: 0, incluir: true, textoDigital: '', status: 'pendente', texto: '', duvidas: 0, erro: '', thumb: null });
    return a;
  }
  function calcularHash(d) {
    if (!d.file || !window.crypto || !crypto.subtle) return;
    d.file.arrayBuffer().then(function (b) { return crypto.subtle.digest('SHA-256', b); }).then(function (h) {
      d.hash = Array.prototype.map.call(new Uint8Array(h), function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
      verificarDuplicado(d);
    }).catch(function () { });
  }
  function verificarDuplicado(d) {
    if (!d.hash || d.histId || d.dupChecado) return;
    d.dupChecado = true;
    api('hist_hash', { hash: d.hash }).then(function (j) { d.duplicado = j.item || null; if (d === atual) renderDup(); }).catch(function () { });
  }
  function renderDup() {
    var b = el('dupAviso'), d = atual;
    if (!d || !d.duplicado || d.histId) { b.style.display = 'none'; return; }
    var it = d.duplicado;
    b.innerHTML = '<i class="fa fa-info-circle"></i> Este arquivo já foi extraído em <b>' + esc(dataBR(it.criado_em)) + '</b>' + (it.usuario !== C.usuario ? ' por ' + esc(it.usuario) : '')
      + '. <button class="lnk" id="dupAbrir">Abrir o resultado anterior</button> ou extraia de novo.';
    b.style.display = 'block';
    el('dupAbrir').onclick = function () { abrirHistorico(it.id); };
  }
  function dataBR(s) { if (!s) return ''; var m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/); return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : s; }

  async function detectarTextoDigital(d) {
    var n = 0;
    for (var i = 0; i < d.paginas.length && i < 400; i++) {
      try {
        var page = await d.pdf.getPage(i + 1), tc = await page.getTextContent(), s = '';
        tc.items.forEach(function (it) { s += (it.str || '') + (it.hasEOL ? '\n' : (it.str ? ' ' : '')); });
        s = s.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
        if (s.replace(/\s/g, '').length >= 80) { d.paginas[i].textoDigital = s; n++; }
      } catch (e) { }
    }
    d.nDigital = n;
    if (d === atual) atualizarOpcoesUI();
  }

  /* ============================== renderização de páginas ============================== */
  function escalar(src, sw, sh, longo, maxUp) {
    var f = Math.min(longo / Math.max(sw, sh), maxUp || 1);
    var c = novoCanvas(sw * f, sh * f), x = c.getContext('2d');
    x.imageSmoothingEnabled = true; x.imageSmoothingQuality = 'high';
    x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
    x.drawImage(src, 0, 0, c.width, c.height);
    return c;
  }
  async function renderBase(d, i, longo) {
    if (d.tipoArq === 'pdf') {
      var page = await d.pdf.getPage(i + 1), vp1 = page.getViewport({ scale: 1 });
      var s = Math.min(longo / Math.max(vp1.width, vp1.height), 6), vp = page.getViewport({ scale: s });
      var c = novoCanvas(vp.width, vp.height), x = c.getContext('2d');
      x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
      await page.render({ canvasContext: x, viewport: vp }).promise;
      return c;
    }
    if (d.tipoArq === 'tiff') {
      var nat = d.nativo.get(i);
      if (!nat) {
        var ifd = d.tiff.ifds[i];
        UTIF.decodeImage(d.tiff.buf, ifd, d.tiff.ifds);
        var rgba = UTIF.toRGBA8(ifd), w = ifd.width, h = ifd.height;
        nat = novoCanvas(w, h);
        var id = nat.getContext('2d').createImageData(w, h); id.data.set(rgba); nat.getContext('2d').putImageData(id, 0, 0);
        ifd.data = null;
        if (d.nativo.size >= 2) d.nativo.delete(d.nativo.keys().next().value);
        d.nativo.set(i, nat);
      }
      return escalar(nat, nat.width, nat.height, longo, 1.5);
    }
    if (d.tipoArq === 'img') {
      var im = d.img, iw = im.width || im.naturalWidth, ih = im.height || im.naturalHeight;
      return escalar(im, iw, ih, longo, 2);
    }
    throw new Error('Pré-visualização indisponível para este formato.');
  }
  function girar(c, rot) {
    rot = ((rot % 360) + 360) % 360;
    if (!rot) return c;
    var r = rot === 90 || rot === 270, o = novoCanvas(r ? c.height : c.width, r ? c.width : c.height), x = o.getContext('2d');
    x.translate(o.width / 2, o.height / 2); x.rotate(rot * Math.PI / 180); x.drawImage(c, -c.width / 2, -c.height / 2);
    return o;
  }
  /** Realce para tinta apagada: tons de cinza + contraste por percentis + leve nitidez. */
  function realcar(c) {
    var x = c.getContext('2d'), W = c.width, H = c.height, im = x.getImageData(0, 0, W, H), p = im.data, n = W * H;
    var g = new Uint8ClampedArray(n), hist = new Uint32Array(256), i;
    for (i = 0; i < n; i++) { var v = (p[i * 4] * 77 + p[i * 4 + 1] * 150 + p[i * 4 + 2] * 29) >> 8; g[i] = v; hist[v]++; }
    var lo = 0, hi = 255, acc = 0, a1 = n * 0.01, a2 = n * 0.995;
    for (i = 0; i < 256; i++) { acc += hist[i]; if (acc >= a1) { lo = i; break; } }
    acc = 0; for (i = 0; i < 256; i++) { acc += hist[i]; if (acc >= a2) { hi = i; break; } }
    if (hi - lo < 20) { lo = 0; hi = 255; }
    var lut = new Uint8ClampedArray(256);
    for (i = 0; i < 256; i++) { var t = Math.min(1, Math.max(0, (i - lo) / (hi - lo))); lut[i] = Math.round(255 * Math.pow(t, 1.25)); }
    var s = new Uint8ClampedArray(n);
    for (i = 0; i < n; i++) s[i] = lut[g[i]];
    // nitidez (unsharp 3x3, 60%)
    for (var y = 0; y < H; y++) for (var xx = 0; xx < W; xx++) {
      var k = y * W + xx, v0 = s[k];
      if (y > 0 && y < H - 1 && xx > 0 && xx < W - 1) {
        var lap = 4 * v0 - s[k - 1] - s[k + 1] - s[k - W] - s[k + W];
        v0 = v0 + 0.6 * lap;
      }
      v0 = v0 < 0 ? 0 : v0 > 255 ? 255 : v0;
      p[k * 4] = p[k * 4 + 1] = p[k * 4 + 2] = v0; p[k * 4 + 3] = 255;
    }
    x.putImageData(im, 0, 0);
    return c;
  }
  /** Página pronta (girada/realçada) com cache LRU por documento. */
  async function getPagina(d, i, longo) {
    var p = d.paginas[i], k = i + '|' + longo + '|' + p.rot + '|' + (d.realce ? 1 : 0);
    if (d.cache.has(k)) { var c0 = d.cache.get(k); d.cache.delete(k); d.cache.set(k, c0); return c0; }
    var c = await renderBase(d, i, longo);
    c = girar(c, p.rot);
    if (d.realce) c = realcar(c);
    d.cache.set(k, c);
    while (d.cache.size > 5) d.cache.delete(d.cache.keys().next().value);
    return c;
  }
  function limparCache(d, i) {
    Array.from(d.cache.keys()).forEach(function (k) { if (i == null || k.indexOf(i + '|') === 0) d.cache.delete(k); });
  }
  function qualidade(prec) { var q = CFG.qualidade_px || 2400; return prec === 'maxima' ? Math.max(q, 3000) : q; }

  /* ============================== fila ============================== */
  function renderFila() {
    var f = el('fila');
    document.body.classList.toggle('tem-doc', docs.length > 0);
    el('btnExtrairTodos').style.display = docs.filter(function (d) { return d.status === 'novo' || d.status === 'erro'; }).length > 1 ? '' : 'none';
    if (docs.length < 2) { f.style.display = 'none'; return; }
    f.style.display = 'flex';
    f.innerHTML = '<span class="muted" style="margin-right:4px"><i class="fa fa-list"></i> Fila:</span>' + docs.map(function (d) {
      var duv = d.texto ? contarDuvidas(d.texto) : 0;
      var st = d.status === 'pronto' ? (duv ? 'duv' : 'pronto') : d.status;
      return '<span class="fila-it' + (d === atual ? ' on' : '') + '" data-uid="' + d.uid + '" title="' + esc(d.nome) + '"><span class="dot ' + st + '"></span><span class="n">' + esc(d.nome) + '</span>'
        + (duv ? '<span class="cnt alerta">' + duv + '</span>' : '') + '</span>';
    }).join('');
  }
  el('fila').addEventListener('click', function (e) {
    var it = e.target.closest('.fila-it'); if (!it) return;
    var d = docs.find(function (x) { return x.uid === it.dataset.uid; }); if (d && d !== atual) exibirDoc(d);
  });

  /* ============================== exibição do documento ============================== */
  function guardarEditor() { if (atual) atual.texto = el('editor').value; }

  function exibirDoc(d) {
    guardarEditor();
    atual = d; pg = 0; zoom = 1; cancelarRegiao();
    el('ws').style.display = 'grid';
    el('arqNome').textContent = d.nome;
    var meta = [];
    meta.push(d.tipoArq === 'pdf' ? 'PDF' : d.tipoArq === 'tiff' ? 'TIFF' : d.tipoArq === 'img' ? (d.mime || 'imagem').replace('image/', '').toUpperCase() : (d.file ? 'Arquivo' : 'Histórico'));
    if (d.tamanho) meta.push(humano(d.tamanho));
    if (d.paginas.length) meta.push(d.paginas.length + (d.paginas.length > 1 ? ' páginas' : ' página'));
    if (d.histId) meta.push('histórico nº ' + d.histId);
    el('arqMeta').textContent = meta.join(' · ');
    el('arqIc').innerHTML = '<i class="fa ' + (d.tipoArq === 'pdf' ? 'fa-file-pdf-o' : d.file ? 'fa-file-image-o' : 'fa-history') + '"></i>';
    el('vwRealce').classList.toggle('ativo', !!d.realce);
    if (d.opcoes) { OPT = Object.assign(OPT, d.opcoes); }
    atualizarOpcoesUI();
    renderThumbs();
    mostrarPagina(0);
    renderDup();
    // resultado
    var temRes = d.status === 'pronto' || d.texto || d.dados;
    el('resCard').style.display = temRes ? 'block' : 'none';
    el('editor').value = d.texto || '';
    atualizarEditor();
    renderDados();
    renderAvisos();
    atualizarSalvoInfo();
    setBtnUnir();
    if (d.status === 'processando') mostrarProgresso(true); else mostrarProgresso(false);
    if (temRes && d.status === 'pronto' && !d.texto && d.dados) trocarAba('dados'); else trocarAba('texto');
    renderFila();
    history.replaceState(null, '', d.histId ? '?abrir=' + d.histId : location.pathname);
  }

  async function mostrarPagina(i) {
    var d = atual; if (!d) return;
    var n = d.paginas.length;
    pg = Math.max(0, Math.min(i, Math.max(0, n - 1)));
    el('vwPag').textContent = n ? (pg + 1) + ' / ' + n : '—';
    el('vwPrev').disabled = pg <= 0; el('vwNext').disabled = pg >= n - 1;
    qsa('.th', el('vwThumbs')).forEach(function (t) { t.classList.toggle('on', +t.dataset.i === pg); });
    var th = qs('.th[data-i="' + pg + '"]', el('vwThumbs')); if (th) th.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    cancelarRegiao();
    var msg = el('vwMsg'), cv = el('vwCanvas'), wrap = el('vwWrap');
    var semVisual = !d.carregado || !d.file || d.tipoArq === 'bruto' || !n;
    ['vwRotL', 'vwRotR', 'vwRealce', 'vwRegiao', 'vwZoomIn', 'vwZoomOut', 'vwFit'].forEach(function (b) { el(b).disabled = semVisual; });
    el('vwReextrair').disabled = !d.file || !n || d.status === 'processando';
    if (semVisual) {
      wrap.style.display = 'none'; msg.style.display = 'flex';
      msg.innerHTML = !d.carregado ? '<div><i class="fa fa-spinner fa-spin fa-2x"></i><br><br>Abrindo o arquivo…</div>'
        : !d.file ? '<div><i class="fa fa-file-o fa-2x"></i><br><br>O arquivo original não está guardado no histórico<br>(ou já passou do prazo de retenção).</div>'
        : '<div><i class="fa fa-eye-slash fa-2x"></i><br><br>' + esc(d.erroCarga || 'Pré-visualização indisponível para este formato') + '.<br>O arquivo será enviado à IA como está.</div>';
      return;
    }
    msg.style.display = 'none'; wrap.style.display = 'block';
    aplicarZoom();
    var alvo = pg;
    try {
      var c = await getPagina(d, alvo, Math.min(qualidade('padrao'), 2000));
      if (d !== atual || pg !== alvo) return;
      cv.width = c.width; cv.height = c.height; cv.getContext('2d').drawImage(c, 0, 0);
    } catch (e) {
      wrap.style.display = 'none'; msg.style.display = 'flex';
      msg.innerHTML = '<div><i class="fa fa-exclamation-triangle fa-2x"></i><br><br>Não foi possível exibir esta página: ' + esc(e.message) + '</div>';
    }
  }
  function aplicarZoom() { el('vwWrap').style.width = (zoom * 100) + '%'; }

  /* miniaturas */
  var obsThumb = window.IntersectionObserver ? new IntersectionObserver(function (ents) {
    ents.forEach(function (en) { if (en.isIntersecting) { obsThumb.unobserve(en.target); gerarThumb(atual, +en.target.dataset.i); } });
  }, { root: null, rootMargin: '200px' }) : null;

  function renderThumbs() {
    var d = atual, box = el('vwThumbs');
    box.innerHTML = '';
    var multi = d.paginas.length > 1;
    qs('.vw-thumbs-head').style.display = multi ? 'flex' : 'none';
    box.style.display = multi ? 'flex' : 'none';
    if (!multi) { atualizarResumo(); return; }
    d.paginas.forEach(function (p, i) {
      var t = document.createElement('div');
      t.className = 'th' + (p.incluir ? '' : ' fora'); t.dataset.i = i;
      t.innerHTML = '<input type="checkbox" title="Incluir na extração"' + (p.incluir ? ' checked' : '') + '>'
        + (p.thumb ? '<img src="' + p.thumb + '" alt="">' : '<div class="ph">' + (i + 1) + '</div>')
        + '<span class="st" style="display:none"></span><div class="num">' + (i + 1) + '</div>';
      box.appendChild(t);
      atualizarThumbStatus(i);
      if (!p.thumb && d.file && d.tipoArq !== 'bruto') { if (obsThumb) obsThumb.observe(t); else gerarThumb(d, i); }
    });
    atualizarResumo();
  }
  var filaThumbs = Promise.resolve();
  function gerarThumb(d, i) {
    if (!d || !d.paginas[i] || d.paginas[i].thumb || !d.carregado || d.tipoArq === 'bruto') return;
    filaThumbs = filaThumbs.then(async function () {
      try {
        var c = await renderBase(d, i, 200);
        c = girar(c, d.paginas[i].rot);
        d.paginas[i].thumb = c.toDataURL('image/jpeg', 0.7);
        if (d === atual) { var t = qs('.th[data-i="' + i + '"]', el('vwThumbs')); if (t) { var ph = qs('.ph,img', t); var im = document.createElement('img'); im.src = d.paginas[i].thumb; ph.replaceWith(im); } }
      } catch (e) { }
    });
  }
  function atualizarThumbStatus(i) {
    if (!atual) return;
    var t = qs('.th[data-i="' + i + '"]', el('vwThumbs')); if (!t) return;
    var p = atual.paginas[i], s = qs('.st', t), m = { proc: ['proc', '…'], ok: ['ok', '✓'], erro: ['erro', '!'], digital: ['dig', 'T'] };
    var v = p.status === 'ok' && p.duvidas ? ['duv', p.duvidas] : m[p.status];
    if (!v) { s.style.display = 'none'; return; }
    s.className = 'st ' + v[0]; s.textContent = v[1]; s.style.display = 'flex';
    s.title = p.status === 'erro' ? p.erro : p.status === 'digital' ? 'Texto digital do PDF' : p.duvidas ? p.duvidas + ' dúvida(s)' : 'Extraída';
  }
  el('vwThumbs').addEventListener('click', function (e) {
    var t = e.target.closest('.th'); if (!t) return;
    var i = +t.dataset.i;
    if (e.target.tagName === 'INPUT') { atual.paginas[i].incluir = e.target.checked; t.classList.toggle('fora', !e.target.checked); atualizarResumo(); return; }
    mostrarPagina(i);
  });
  function marcarTodas(v) { if (!atual) return; atual.paginas.forEach(function (p) { p.incluir = v; }); renderThumbs(); }
  el('thTodas').onclick = function () { marcarTodas(true); };
  el('thNenhuma').onclick = function () { marcarTodas(false); };

  /* navegação / zoom / rotação / realce */
  el('vwPrev').onclick = function () { mostrarPagina(pg - 1); };
  el('vwNext').onclick = function () { mostrarPagina(pg + 1); };
  el('vwZoomIn').onclick = function () { zoom = Math.min(4, zoom * 1.25); aplicarZoom(); };
  el('vwZoomOut').onclick = function () { zoom = Math.max(0.4, zoom / 1.25); aplicarZoom(); };
  el('vwFit').onclick = function () { zoom = 1; aplicarZoom(); el('vwStage').scrollLeft = 0; };
  el('vwStage').addEventListener('wheel', function (e) {
    if (!e.ctrlKey || !atual) return;
    e.preventDefault(); zoom = Math.max(0.4, Math.min(4, zoom * (e.deltaY < 0 ? 1.12 : 1 / 1.12))); aplicarZoom();
  }, { passive: false });
  function rotacionar(delta) {
    var p = atual.paginas[pg]; p.rot = (p.rot + delta + 360) % 360; p.thumb = null;
    limparCache(atual, pg); mostrarPagina(pg);
    var t = qs('.th[data-i="' + pg + '"]', el('vwThumbs')); if (t) { var im = qs('img', t); if (im) { var ph = document.createElement('div'); ph.className = 'ph'; ph.textContent = pg + 1; im.replaceWith(ph); } gerarThumb(atual, pg); }
  }
  el('vwRotL').onclick = function () { rotacionar(-90); };
  el('vwRotR').onclick = function () { rotacionar(90); };
  el('vwRealce').onclick = function () {
    atual.realce = !atual.realce; this.classList.toggle('ativo', atual.realce);
    limparCache(atual); mostrarPagina(pg);
    toast(atual.realce ? 'Realce ativado — também será usado no envio à IA' : 'Realce desativado', 'info');
  };
  el('btnFechar').onclick = function () {
    var d = atual; if (!d) return;
    var sair = function () {
      if (d.abort) d.abort.abort();
      docs.splice(docs.indexOf(d), 1);
      if (docs.length) exibirDoc(docs[docs.length - 1]);
      else { atual = null; el('ws').style.display = 'none'; history.replaceState(null, '', location.pathname); }
      renderFila();
    };
    if (d.dirty || d.status === 'processando') Swal.fire({ icon: 'warning', title: 'Fechar documento?', text: d.status === 'processando' ? 'A extração em andamento será cancelada.' : 'Há alterações não salvas.', showCancelButton: true, confirmButtonText: 'Fechar', cancelButtonText: 'Voltar' }).then(function (r) { if (r.isConfirmed) sair(); });
    else sair();
  };

  /* ============================== seleção de região ============================== */
  var arrasto = null;
  el('vwRegiao').onclick = function () {
    modoRegiao = !modoRegiao; this.classList.toggle('ativo', modoRegiao);
    el('vwWrap').classList.toggle('regiao', modoRegiao);
    if (!modoRegiao) cancelarRegiao(); else toast('Desenhe um retângulo sobre o trecho difícil', 'info');
  };
  function cancelarRegiao() {
    selFrac = null; arrasto = null; el('vwSel').style.display = 'none'; el('vwSelBar').style.display = 'none';
  }
  function fracDoEvento(e) {
    var r = el('vwCanvas').getBoundingClientRect();
    return { x: Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)), y: Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)) };
  }
  function desenharSel(f) {
    var s = el('vwSel'); s.style.display = 'block';
    s.style.left = (f.x * 100) + '%'; s.style.top = (f.y * 100) + '%'; s.style.width = (f.w * 100) + '%'; s.style.height = (f.h * 100) + '%';
  }
  el('vwWrap').addEventListener('pointerdown', function (e) {
    if (!modoRegiao || e.button > 0 || e.target.closest('.vw-selbar')) return;
    e.preventDefault(); el('vwSelBar').style.display = 'none';
    arrasto = fracDoEvento(e); this.setPointerCapture(e.pointerId);
  });
  el('vwWrap').addEventListener('pointermove', function (e) {
    if (!arrasto) return;
    var p = fracDoEvento(e);
    selFrac = { x: Math.min(arrasto.x, p.x), y: Math.min(arrasto.y, p.y), w: Math.abs(p.x - arrasto.x), h: Math.abs(p.y - arrasto.y) };
    desenharSel(selFrac);
  });
  el('vwWrap').addEventListener('pointerup', function () {
    if (!arrasto) return; arrasto = null;
    if (!selFrac || selFrac.w < 0.02 || selFrac.h < 0.008) { cancelarRegiao(); return; }
    var b = el('vwSelBar'); b.style.display = 'flex';
    b.style.left = Math.min(selFrac.x * 100, 70) + '%';
    b.style.top = 'calc(' + ((selFrac.y + selFrac.h) * 100) + '% + 6px)';
  });
  el('vwSelCancel').onclick = cancelarRegiao;
  el('vwSelReler').onclick = relerRegiao;

  async function relerRegiao() {
    var d = atual, f = selFrac; if (!d || !f) return;
    var btn = el('vwSelReler'); btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Lendo…';
    try {
      var c = await getPagina(d, pg, qualidade('maxima'));
      var sx = Math.round(f.x * c.width), sy = Math.round(f.y * c.height), sw = Math.max(8, Math.round(f.w * c.width)), sh = Math.max(8, Math.round(f.h * c.height));
      var alvo = Math.max(sw, sh) < 1400 ? Math.min(2.5, 1400 / Math.max(sw, sh)) : 1;
      var cr = novoCanvas(sw * alvo, sh * alvo), x = cr.getContext('2d');
      x.imageSmoothingQuality = 'high'; x.fillStyle = '#fff'; x.fillRect(0, 0, cr.width, cr.height);
      x.drawImage(c, sx, sy, sw, sh, 0, 0, cr.width, cr.height);
      var blob = await canvasBlob(cr, 0.93);
      var ed = el('editor'), a = ed.selectionStart, b = ed.selectionEnd, temSel = b > a;
      var fd = new FormData();
      fd.append('imagem', blob, 'regiao.jpg');
      fd.append('antes', ed.value.slice(Math.max(0, a - 300), a));
      fd.append('depois', ed.value.slice(b, b + 300));
      var j = await api('reler_regiao', fd);
      somarUso(d, j.uso);
      var prev = cr.toDataURL('image/jpeg', 0.8);
      var r = await Swal.fire({
        title: 'Releitura do trecho', width: 640,
        html: '<img class="rg-img" src="' + prev + '"><textarea id="rgTxt" class="rg-txt">' + esc(j.texto) + '</textarea>'
          + '<div style="font-size:.8rem;color:#64748b;margin-top:6px">Lido com ' + esc(j.modelo) + '. Você pode ajustar antes de aplicar.</div>',
        showDenyButton: true, showCancelButton: true,
        confirmButtonText: temSel ? 'Substituir a seleção do texto' : 'Inserir no cursor do texto', denyButtonText: 'Copiar', cancelButtonText: 'Fechar',
        preConfirm: function () { return el('rgTxt').value; }, preDeny: function () { return el('rgTxt').value; }
      });
      if (r.isConfirmed) {
        el('resCard').style.display = 'block'; trocarAba('texto');
        ed.focus(); ed.setSelectionRange(a, b);
        substituirIntervalo(a, b, r.value);
        cancelarRegiao();
      } else if (r.isDenied) copiarTexto(r.value || j.texto);
    } catch (e) { if (!erroAbort(e)) alerta('Não foi possível reler', e.message, 'error'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="fa fa-magic"></i> Reler este trecho'; }
  }

  /* ============================== opções ============================== */
  function segSet(id, v) { qsa('#' + id + ' button').forEach(function (b) { b.classList.toggle('on', b.dataset.v === v); }); }
  function atualizarOpcoesUI() {
    segSet('segModo', OPT.modo); segSet('segPrec', OPT.precisao);
    el('optTipoBox').style.display = OPT.modo === 'transcricao' ? 'none' : '';
    var tipo = el('selTipo'); if (qs('option[value="' + OPT.tipo + '"]', tipo)) tipo.value = OPT.tipo;
    var d = atual, nd = d && d.nDigital;
    el('ckDigitalBox').style.display = nd && OPT.modo !== 'dados' ? 'flex' : 'none';
    el('ckDigital').checked = !!OPT.digital;
    el('ckDigitalInfo').textContent = nd ? '(' + nd + ' de ' + d.paginas.length + ' páginas)' : '';
    el('ckMarcadoresBox').style.display = d && d.paginas.length > 1 && OPT.modo !== 'dados' ? 'flex' : 'none';
    el('ckMarcadores').checked = !!OPT.marcadores;
    atualizarResumo();
  }
  function atualizarResumo() {
    var d = atual; if (!d) return;
    var n = d.paginas.filter(function (p) { return p.incluir; }).length, t = d.paginas.length;
    var partes = [];
    if (t > 1) partes.push(n + ' de ' + t + ' páginas selecionadas'); else partes.push('1 página');
    if (OPT.modo !== 'dados') partes.push(OPT.precisao === 'maxima' ? 'leitura + verificação por página' : '1 leitura por página');
    if (OPT.modo !== 'transcricao') partes.push('extração de campos');
    el('optResumo').textContent = partes.join(' · ');
    el('thumbsInfo').textContent = n + ' de ' + t + ' páginas para extrair';
    el('btnExtrair').disabled = !n || !d.file || d.status === 'processando';
    el('btnExtrair').innerHTML = d.status === 'pronto' ? '<i class="fa fa-refresh"></i> Extrair de novo' : '<i class="fa fa-magic"></i> Extrair';
  }
  function salvarOpcoes() { lsSet('iris_opts', { modo: OPT.modo, precisao: OPT.precisao, tipo: OPT.tipo, marcadores: OPT.marcadores, digital: OPT.digital, modelo: OPT.modelo }); }
  el('segModo').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; OPT.modo = b.dataset.v; salvarOpcoes(); atualizarOpcoesUI(); });
  el('segPrec').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; OPT.precisao = b.dataset.v; salvarOpcoes(); atualizarOpcoesUI(); });
  el('selTipo').onchange = function () { OPT.tipo = this.value; salvarOpcoes(); };
  el('ckDigital').onchange = function () { OPT.digital = this.checked; salvarOpcoes(); };
  el('ckMarcadores').onchange = function () { OPT.marcadores = this.checked; salvarOpcoes(); };
  function lerOpcoes() {
    return { modo: OPT.modo, precisao: OPT.precisao, tipo: el('selTipo').value, modelo: '',
             marcadores: el('ckMarcadores').checked, digital: el('ckDigital').checked && el('ckDigitalBox').style.display !== 'none' };
  }

  /* ============================== extração ============================== */
  function somarUso(d, u) { if (!u) return; ['entrada', 'saida'].forEach(function (k) { d.uso[k] += +u[k] || 0; d.usoPendente[k] += +u[k] || 0; }); }
  function mostrarProgresso(v) { el('prog').style.display = v ? 'block' : 'none'; }
  function progresso(d, feito, total, msg, sub) {
    d.prog = { feito: feito, total: total, msg: msg, sub: sub };
    if (d !== atual) return;
    mostrarProgresso(true);
    el('progMsg').textContent = msg; el('progSub').textContent = sub || '';
    el('progBar').style.width = (total ? Math.round(100 * feito / total) : 5) + '%';
  }

  /** Recortes de detalhe (2 metades com sobreposição) para o modo Máxima. */
  async function vistasDetalhe(c) {
    var ret = [], retrato = c.height >= c.width, sob = 0.12;
    for (var k = 0; k < 2; k++) {
      var ini = k === 0 ? 0 : 0.5 - sob, fim = k === 0 ? 0.5 + sob : 1;
      var sx = retrato ? 0 : Math.round(ini * c.width), sy = retrato ? Math.round(ini * c.height) : 0;
      var sw = retrato ? c.width : Math.round((fim - ini) * c.width), sh = retrato ? Math.round((fim - ini) * c.height) : c.height;
      var o = novoCanvas(sw, sh); o.getContext('2d').drawImage(c, sx, sy, sw, sh, 0, 0, sw, sh);
      var bl = await canvasBlob(o, 0.9), lim = (CFG.max_upload || 8 * 1048576) * 0.25;
      if (bl.size > lim) bl = await canvasBlob(o, 0.75);
      if (bl.size > lim) bl = await canvasBlob(escalar(o, sw, sh, Math.max(sw, sh) * 0.75, 1), 0.75);
      ret.push(bl);
    }
    return ret;
  }
  /** Gera o JPEG da página respeitando o limite de upload do servidor (reduz qualidade e, se preciso, a escala). */
  async function blobDaPagina(d, i, longo, q, fracao) {
    var lim = Math.min(6 * 1048576, (CFG.max_upload || 8 * 1048576) * 0.9 * (fracao || 1));
    var c = await getPagina(d, i, longo), b = await canvasBlob(c, q), qs2 = [0.82, 0.72], k = 0;
    while (b.size > lim && k < qs2.length) b = await canvasBlob(c, qs2[k++]);
    var e = c;
    while (b.size > lim && e.width > 900) { e = escalar(e, e.width, e.height, Math.max(e.width, e.height) * 0.8, 1); b = await canvasBlob(e, 0.8); }
    return { c: c, b: b };
  }

  async function transcreverPagina(d, i, o, sinal) {
    var p = d.paginas[i];
    if (o.digital && p.textoDigital) { p.texto = p.textoDigital; p.duvidas = 0; p.status = 'digital'; if (d === atual) atualizarThumbStatus(i); return; }
    p.status = 'proc'; p.erro = ''; if (d === atual) atualizarThumbStatus(i);
    var fd = new FormData();
    if (d.tipoArq === 'bruto') fd.append('imagens[]', d.file, d.nome);
    else {
      var r = await blobDaPagina(d, i, qualidade(o.precisao), 0.9, o.precisao === 'maxima' ? 0.45 : 1);
      fd.append('imagens[]', r.b, 'pagina-' + (i + 1) + '.jpg');
      if (o.precisao === 'maxima') (await vistasDetalhe(r.c)).forEach(function (b, k) { fd.append('imagens[]', b, 'detalhe-' + (k + 1) + '.jpg'); });
    }
    fd.append('pagina', i + 1); fd.append('total', d.paginas.length); fd.append('precisao', o.precisao); fd.append('modelo', o.modelo);
    try {
      var j = await api('transcrever', fd, { signal: sinal });
      p.texto = j.texto; p.duvidas = j.duvidas; p.modelo = j.modelo; p.verificado = j.verificado; p.status = 'ok';
      somarUso(d, j.uso); d.modelo = j.modelo;
      if (j.aviso) d.avisos.push('Página ' + (i + 1) + ': ' + j.aviso);
      if (j.truncado) d.avisos.push('Página ' + (i + 1) + ': a resposta pode ter sido cortada por tamanho — use "Reler região" nas partes finais.');
    } catch (e) {
      if (erroAbort(e)) { p.status = 'pendente'; throw e; }
      if (e.login) throw e;
      p.status = 'erro'; p.erro = e.message; d.avisos.push('Página ' + (i + 1) + ': ' + e.message);
    }
    if (d === atual) atualizarThumbStatus(i);
  }

  function comporTexto(d, o) {
    var sel = d.paginas.filter(function (p) { return p.incluir && (p.status === 'ok' || p.status === 'digital' || p.status === 'erro'); });
    var marc = o.marcadores && d.paginas.length > 1;
    return sel.map(function (p) {
      var t = p.status === 'erro' ? '[ilegível] (falha ao extrair esta página: ' + p.erro + ')' : limparTracos(p.texto || '');
      if (d.unido) t = unirLinhasTexto(t);
      return (marc ? '[Página ' + p.n + ']\n\n' : '') + t;
    }).join('\n\n');
  }

  async function extrairDoc(d, o) {
    if (!d.file) return;
    var sel = d.paginas.map(function (p, i) { return p.incluir ? i : -1; }).filter(function (i) { return i >= 0; });
    if (!sel.length) { alerta('Nenhuma página selecionada', 'Marque ao menos uma página nas miniaturas.'); return; }
    d.status = 'processando'; d.avisos = []; d.opcoes = o; d.abort = new AbortController();
    var sinal = d.abort.signal, t0 = Date.now();
    renderFila(); if (d === atual) { atualizarResumo(); el('vwReextrair').disabled = true; }
    try {
      if (o.modo !== 'dados') {
        var fila = sel.slice(), feitos = 0, total = sel.length;
        var conc = Math.max(1, Math.min(CFG.paginas_simultaneas || 2, o.precisao === 'maxima' ? 2 : 4, total));
        progresso(d, 0, total, 'Lendo ' + (total > 1 ? 'as páginas' : 'a página') + '…', o.precisao === 'maxima' ? 'Máxima precisão: leitura + verificação por página (mais lento).' : '');
        var trabalhador = async function () {
          while (fila.length) {
            if (sinal.aborted) return;
            var i = fila.shift();
            await transcreverPagina(d, i, o, sinal);
            feitos++;
            progresso(d, feitos, total + (o.modo === 'completo' ? 1 : 0), 'Páginas lidas: ' + feitos + ' de ' + total, d.paginas[i].status === 'erro' ? 'Falha na página ' + (i + 1) + ' — as demais continuam.' : '');
          }
        };
        var ws = []; for (var k = 0; k < conc; k++) ws.push(trabalhador());
        await Promise.all(ws);
        if (sinal.aborted) throw { abortado: true };
        d.texto = comporTexto(d, o); d.snapshot = null;
        if (d === atual) { el('editor').value = d.texto; atualizarEditor(); setBtnUnir(); el('resCard').style.display = 'block'; }
      }
      if (o.modo !== 'transcricao') {
        progresso(d, 1, 1, 'Extraindo os campos do documento…', 'Classificação e leitura estruturada.');
        await extrairDados(d, o, sinal);
      }
      d.status = 'pronto'; d.ms += Date.now() - t0;
      if (d.paginas.some(function (p) { return p.status === 'erro'; }) && d.paginas.every(function (p) { return !p.incluir || p.status === 'erro'; })) d.status = 'erro';
      await salvar(d, true);
    } catch (e) {
      if (erroAbort(e)) { d.status = d.texto ? 'pronto' : 'novo'; toast('Extração cancelada', 'info'); }
      else { d.status = 'erro'; if (e.login) alerta('Sessão expirada', 'Faça login novamente em outra aba e tente de novo.', 'error'); else alerta('Não foi possível extrair', e.message, 'error'); }
    } finally {
      d.abort = null;
      if (d === atual) {
        mostrarProgresso(false); atualizarResumo(); renderAvisos(); el('vwReextrair').disabled = false;
        el('resCard').style.display = (d.texto || d.dados) ? 'block' : 'none';
        if (o.modo === 'dados' && d.dados) trocarAba('dados'); else if (d.texto) trocarAba('texto');
        atualizarDuvidas();
      }
      renderFila();
    }
  }

  async function extrairDados(d, o, sinal) {
    var fd = new FormData();
    fd.append('tipo', o.tipo || 'auto'); fd.append('modelo', o.modelo || '');
    var texto = d === atual ? el('editor').value : d.texto;
    if (texto && texto.trim()) fd.append('texto', texto);
    if (d.file) {
      var sel = d.paginas.map(function (p, i) { return p.incluir ? i : -1; }).filter(function (i) { return i >= 0; });
      if (d.tipoArq === 'bruto') fd.append('imagens[]', d.file, d.nome);
      else {
        var lim = texto && texto.trim() ? 8 : 12, tot = 0;
        if (sel.length > lim) d.avisos.push('Para os dados foram enviadas as ' + lim + ' primeiras páginas selecionadas' + (texto ? ' (e o texto de todas).' : '.'));
        for (var k = 0; k < Math.min(sel.length, lim); k++) {
          var r = await blobDaPagina(d, sel[k], 1600, 0.85, 1 / Math.min(sel.length, lim));
          tot += r.b.size; if (tot > Math.min(12 * 1048576, (CFG.max_upload || 8 * 1048576) * 0.9)) break;
          fd.append('imagens[]', r.b, 'pagina-' + (sel[k] + 1) + '.jpg');
        }
      }
    }
    try {
      var j = await api('estruturar', fd, { signal: sinal });
      d.dados = j.dados; somarUso(d, j.uso); d.dirty = true;
      if (d === atual) { renderDados(); el('resCard').style.display = 'block'; }
    } catch (e) {
      if (erroAbort(e) || e.login) throw e;
      d.avisos.push('Dados: ' + e.message);
    }
  }

  el('btnExtrair').onclick = function () {
    if (!atual) return;
    var o = lerOpcoes();
    var go = function () { extrairDoc(atual, o); };
    if (atual.dirty && atual.texto) Swal.fire({ icon: 'question', title: 'Extrair de novo?', text: 'O texto atual (com suas edições não salvas) será substituído.', showCancelButton: true, confirmButtonText: 'Extrair', cancelButtonText: 'Cancelar' }).then(function (r) { if (r.isConfirmed) go(); });
    else go();
  };
  el('btnExtrairTodos').onclick = async function () {
    var o = lerOpcoes(), pend = docs.filter(function (d) { return d.status === 'novo' || d.status === 'erro'; });
    this.disabled = true;
    for (var i = 0; i < pend.length; i++) {
      var d = pend[i];
      if (!d.carregado) await new Promise(function (r) { var t = setInterval(function () { if (d.carregado) { clearInterval(t); r(); } }, 200); });
      await extrairDoc(d, o);
    }
    this.disabled = false; toast('Fila concluída');
  };
  el('btnCancelar').onclick = function () { if (atual && atual.abort) atual.abort.abort(); };

  el('vwReextrair').onclick = async function () {
    var d = atual, i = pg; if (!d || !d.file) return;
    var r = await Swal.fire({ title: 'Reextrair a página ' + (i + 1) + '?', input: 'radio', inputOptions: { padrao: 'Padrão', maxima: 'Máxima precisão (manuscritos)' }, inputValue: 'maxima', showCancelButton: true, confirmButtonText: 'Reextrair', cancelButtonText: 'Cancelar' });
    if (!r.isConfirmed) return;
    var o = Object.assign(lerOpcoes(), { precisao: r.value || 'maxima', digital: false });
    var btn = this; btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Lendo…';
    try {
      d.avisos = [];
      await transcreverPagina(d, i, o);
      var p = d.paginas[i];
      if (p.status === 'erro') throw new Error(p.erro);
      var novo = limparTracos(p.texto); if (d.unido) novo = unirLinhasTexto(novo);
      var ed = el('editor'), v = ed.value, re = new RegExp('^\\[Página ' + (i + 1) + '\\]\\s*\\n', 'm'), m = re.exec(v);
      el('resCard').style.display = 'block'; trocarAba('texto');
      if (m) {
        var ini = m.index + m[0].length, prox = /^\[Página \d+\]\s*$/m, resto = v.slice(ini), m2 = prox.exec(resto);
        var fim = m2 ? ini + m2.index : v.length;
        substituirIntervalo(ini, fim, '\n' + novo + (m2 ? '\n\n' : ''));
        toast('Página ' + (i + 1) + ' atualizada no texto');
      } else if (!v.trim() || d.paginas.length === 1) {
        substituirIntervalo(0, v.length, novo); toast('Texto atualizado');
      } else {
        var rr = await Swal.fire({ title: 'Página ' + (i + 1), html: '<textarea class="rg-txt" id="rgTxt" style="min-height:240px">' + esc(novo) + '</textarea>', width: 720, showDenyButton: true, confirmButtonText: 'Inserir no cursor', denyButtonText: 'Copiar', showCancelButton: true, cancelButtonText: 'Fechar', preConfirm: function () { return el('rgTxt').value; }, preDeny: function () { return el('rgTxt').value; } });
        if (rr.isConfirmed) substituirIntervalo(ed.selectionStart, ed.selectionEnd, rr.value); else if (rr.isDenied) copiarTexto(rr.value);
      }
      renderAvisos();
    } catch (e) { alerta('Não foi possível reextrair', e.message, 'error'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="fa fa-refresh"></i> Reextrair página'; }
  };

  /* ============================== editor ============================== */
  var RX_MARC = /(\[\?[^\]\n]*?\?\])|(\[ileg[ií]vel\])|(\[(?:assinatura|rubrica|carimbo|selo|riscado|entrelinha|margem)(?::[^\[\]\n]*)?\])|(^\[Página \d+\]$)/gim;
  var RX_LINHA_ESP = /^\[(?:assinatura|rubrica|carimbo|selo|margem)\b[^\]]*\]$/i;
  var RX_DUV = /\[\?([^\]\n]*?)\?\]|\[ileg[ií]vel\]/gi;
  function contarDuvidas(t) { var m = String(t || '').match(RX_DUV); return m ? m.length : 0; }
  function realceHTML(t) {
    return esc(t).replace(RX_MARC, function (s, a, b, c, d) {
      return '<mark class="' + (a ? 'm-duv' : b ? 'm-ileg' : c ? 'm-esp' : 'm-pag') + '">' + s + '</mark>';
    });
  }
  var rafRealce = 0;
  function atualizarEditor() {
    cancelAnimationFrame(rafRealce);
    rafRealce = requestAnimationFrame(function () {
      var ed = el('editor');
      el('edBack').innerHTML = realceHTML(ed.value) + '\n ';
      el('edBack').scrollTop = ed.scrollTop;
      var t = ed.value, pal = t.trim() ? t.trim().split(/\s+/).length : 0;
      el('edInfo').textContent = num(t.length) + ' caracteres · ' + num(pal) + ' palavras' + (atual && atual.modelo ? ' · ' + atual.modelo : '');
      atualizarDuvidas();
    });
  }
  el('editor').addEventListener('input', function () { if (atual) { atual.dirty = true; atual.texto = this.value; atualizarSalvoInfo(); } atualizarEditor(); });
  el('editor').addEventListener('scroll', function () { el('edBack').scrollTop = this.scrollTop; });
  if (window.ResizeObserver) new ResizeObserver(function () { el('edBack').scrollTop = el('editor').scrollTop; }).observe(el('editor'));

  function substituirIntervalo(a, b, txt) {
    var ed = el('editor'); ed.focus(); ed.setSelectionRange(a, b);
    // execCommand preserva o desfazer (Ctrl+Z) do navegador
    var ok = false; try { ok = document.execCommand('insertText', false, txt); } catch (e) { }
    if (!ok) { ed.setRangeText(txt, a, b, 'end'); }
    ed.dispatchEvent(new Event('input'));
  }

  /* Remove traços/underscores de linhas em branco de formulário. Mantém as quebras. */
  function limparTracos(t) {
    return String(t || '').replace(/\r\n/g, '\n')
      .replace(/^[ \t]*[-_=.·•*–—]{3,}[ \t]*$/gm, '')
      .replace(/[_–—]{3,}|-{4,}/g, ' ')
      .replace(/[ \t]{2,}/g, ' ').replace(/[ \t]+\n/g, '\n').replace(/^[ \t]+/gm, '')
      .replace(/\n{3,}/g, '\n\n').replace(/^\n+|\n+$/g, '');
  }
  /* Remove quebras simples (viram espaço), reúne palavras hifenizadas e mantém parágrafos, marcadores de página e linhas de tabela. */
  function unirLinhasTexto(t) {
    return limparTracos(t)
      .replace(/([A-Za-zÀ-ÿ])-\n([a-zà-ÿ])/g, '$1$2')
      .split(/\n{2,}/)
      .map(function (p) {
        if (/^\[Página \d+\]$/.test(p.trim())) return p.trim();
        if (/ \| /.test(p)) return p;             // tabela: preserva linhas
        // marcações de assinatura, carimbo, selo e margem ficam em linha própria
        var ls = p.split(/\n+/), out = '';
        ls.forEach(function (l, k) {
          l = l.trim(); if (!l) return;
          var esp = RX_LINHA_ESP.test(l), antEsp = k > 0 && RX_LINHA_ESP.test(ls[k - 1].trim());
          out += (out ? ((esp || antEsp) ? '\n' : ' ') : '') + l;
        });
        return out.replace(/[ \t]{2,}/g, ' ');
      })
      .filter(function (p) { return p.length > 0; }).join('\n\n');
  }
  function setBtnUnir() {
    var b = el('btnUnir'), u = atual && atual.unido;
    if (u) { b.innerHTML = '<i class="fa fa-undo"></i> Desfazer união'; b.classList.add('ativo'); }
    else { b.innerHTML = '<i class="fa fa-align-left"></i> Unir linhas'; b.classList.remove('ativo'); }
  }
  el('btnUnir').onclick = function () {
    if (!atual) return;
    var ed = el('editor');
    if (!atual.unido) { atual.snapshot = ed.value; ed.value = unirLinhasTexto(ed.value); atual.unido = true; }
    else {
      if (atual.snapshot != null) ed.value = atual.snapshot;
      else if (atual.paginas.some(function (p) { return p.texto; })) { atual.unido = false; ed.value = comporTexto(atual, atual.opcoes || lerOpcoes()); }
      atual.unido = false; atual.snapshot = null;
    }
    UNIR_PADRAO = atual.unido; lsSet('iris_unir', UNIR_PADRAO);
    atual.dirty = true; atual.texto = ed.value; setBtnUnir(); atualizarEditor(); atualizarSalvoInfo();
  };
  el('btnMono').onclick = function () { el('edWrap').classList.toggle('mono'); this.classList.toggle('ativo'); atualizarEditor(); };
  function copiarTexto(t) {
    var feito = function () { toast('Copiado'); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(t).then(feito, fallback); else fallback();
    function fallback() { var ta = document.createElement('textarea'); ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); feito(); } catch (e) { } ta.remove(); }
  }
  el('btnCopiar').onclick = function () { copiarTexto(el('editor').value); };

  /* marcações */
  qsa('[data-limpar]').forEach(function (b) {
    b.onclick = function () {
      var t = el('editor').value, m = b.dataset.limpar;
      if (m === 'duvidas' || m === 'tudo') t = t.replace(/\[\?([^\]\n]*?)\?\]/g, '$1');
      if (m === 'especiais' || m === 'tudo') t = t.replace(/\[(?:assinatura|rubrica)(?::\s*([^\[\]\n]*))?\]/gi, function (s, n) { return m === 'tudo' && n ? n.trim() : ''; })
                                              .replace(/\[(?:carimbo|selo|margem|entrelinha)(?::\s*([^\[\]\n]*))?\]/gi, function (s, n) { return m === 'tudo' ? (n || '').trim() : ''; })
                                              .replace(/\[riscado(?::[^\[\]\n]*)?\]/gi, '');
      if (m === 'paginas' || m === 'tudo') t = t.replace(/^\[Página \d+\]\s*\n?/gm, '');
      t = t.replace(/[ \t]{2,}/g, ' ').replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
      substituirIntervalo(0, el('editor').value.length, t);
      fecharMenus();
    };
  });

  /* localizar / substituir */
  el('btnBuscar').onclick = function () { var b = el('buscaBar'); b.style.display = b.style.display === 'none' ? 'flex' : 'none'; if (b.style.display === 'flex') el('bLoc').focus(); };
  el('bFechar').onclick = function () { el('buscaBar').style.display = 'none'; };
  function localizar(apartir) {
    var q = el('bLoc').value; if (!q) return -1;
    var ed = el('editor'), v = ed.value.toLowerCase(), i = v.indexOf(q.toLowerCase(), apartir);
    if (i < 0 && apartir > 0) i = v.indexOf(q.toLowerCase(), 0);
    if (i >= 0) { ed.focus(); ed.setSelectionRange(i, i + q.length); rolarAte(i); }
    var tot = v.split(q.toLowerCase()).length - 1; el('bInfo').textContent = tot + ' ocorrência(s)';
    return i;
  }
  el('bProx').onclick = function () { localizar(el('editor').selectionEnd); };
  el('bLoc').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); localizar(el('editor').selectionEnd); } });
  el('bSubUm').onclick = function () {
    var ed = el('editor'), q = el('bLoc').value, s = ed.value.slice(ed.selectionStart, ed.selectionEnd);
    if (q && s.toLowerCase() === q.toLowerCase()) substituirIntervalo(ed.selectionStart, ed.selectionEnd, el('bSub').value);
    localizar(ed.selectionEnd);
  };
  el('bSubTodos').onclick = function () {
    var q = el('bLoc').value; if (!q) return;
    var ed = el('editor'), re = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'), n = (ed.value.match(re) || []).length;
    if (!n) { el('bInfo').textContent = 'nada encontrado'; return; }
    var sub = el('bSub').value;
    substituirIntervalo(0, ed.value.length, ed.value.replace(re, function () { return sub; }));
    el('bInfo').textContent = n + ' substituída(s)';
  };
  el('editor').addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') { e.preventDefault(); el('buscaBar').style.display = 'flex'; var s = this.value.slice(this.selectionStart, this.selectionEnd); if (s && s.length < 80) el('bLoc').value = s; el('bLoc').focus(); el('bLoc').select(); }
  });
  function rolarAte(idx) {
    // posição exata a partir do espelho com realce
    var back = el('edBack'), ed = el('editor');
    back.innerHTML = esc(ed.value.slice(0, idx)) + '<span id="edPos">|</span>';
    var sp = el('edPos'), top = sp ? sp.offsetTop : 0;
    ed.scrollTop = Math.max(0, top - ed.clientHeight / 3);
    atualizarEditor();
  }

  /* baixar */
  function nomeBase() { return ((atual && atual.nome) || 'extracao').replace(/\.[^.]+$/, ''); }
  qsa('[data-baixar]').forEach(function (b) {
    b.onclick = async function () {
      fecharMenus();
      var t = el('editor').value, f = b.dataset.baixar;
      if (f === 'txt') baixarBlob(new Blob(['﻿' + t.replace(/\n/g, '\r\n')], { type: 'text/plain;charset=utf-8' }), nomeBase() + '.txt');
      else if (f === 'json') baixarBlob(new Blob([JSON.stringify({ arquivo: atual.nome, extraido_em: new Date().toISOString(), modelo: atual.modelo, texto: t, dados: atual.dados ? simplificarDados(atual.dados) : null, dados_completos: atual.dados }, null, 2)], { type: 'application/json' }), nomeBase() + '.json');
      else if (f === 'docx') {
        try { baixarBlob(await api('exportar_docx', { texto: t, nome: nomeBase(), realcar: '1' }, { blob: true }), nomeBase() + '.docx'); }
        catch (e) { alerta('Não foi possível gerar o .docx', e.message, 'error'); }
      }
    };
  });

  /* menus suspensos */
  function fecharMenus() { qsa('.dd.aberto').forEach(function (d) { d.classList.remove('aberto'); }); }
  document.addEventListener('click', function (e) {
    var dd = e.target.closest('.dd');
    if (dd && e.target.closest('.dd > .tbtn')) { var ab = dd.classList.contains('aberto'); fecharMenus(); if (!ab) dd.classList.add('aberto'); return; }
    if (!dd) fecharMenus();
  });

  /* ============================== dúvidas ============================== */
  var duvidas = [];
  function atualizarDuvidas() {
    var t = el('editor').value; duvidas = []; var m;
    RX_DUV.lastIndex = 0;
    while ((m = RX_DUV.exec(t))) {
      var antes = t.slice(0, m.index), pm = antes.match(/\[Página (\d+)\](?![\s\S]*\[Página \d+\])/);
      duvidas.push({ i: m.index, len: m[0].length, txt: m[1], ileg: m[1] === undefined, pag: pm ? +pm[1] : null,
                     a: t.slice(Math.max(0, m.index - 50), m.index).replace(/\s+/g, ' '), d: t.slice(m.index + m[0].length, m.index + m[0].length + 50).replace(/\s+/g, ' ') });
    }
    var c = el('cntDuv'); c.textContent = duvidas.length; c.className = 'cnt' + (duvidas.length ? ' alerta' : (t ? ' ok' : ''));
    var box = el('duvLista');
    if (!duvidas.length) { box.innerHTML = '<div class="vazio">' + (t ? '<i class="fa fa-check-circle" style="color:#22c55e;font-size:1.6rem"></i><br>Nenhuma leitura duvidosa marcada.' : 'Nada extraído ainda.') + '</div>'; return; }
    box.innerHTML = duvidas.map(function (q, k) {
      return '<div class="duv-it" data-k="' + k + '"><span class="duv-tag ' + (q.ileg ? 'ileg' : 'duv') + '">' + (q.ileg ? 'ILEGÍVEL' : 'DÚVIDA') + '</span>'
        + '<div class="duv-ctx">…' + esc(q.a) + '<b>' + esc(q.ileg ? '[ilegível]' : q.txt) + '</b>' + esc(q.d) + '…</div>'
        + (q.pag ? '<span class="duv-pag">pág. ' + q.pag + '</span>' : '')
        + (q.ileg ? '' : '<button class="ib" data-aceitar="' + k + '" title="Aceitar esta leitura (remove as marcações)"><i class="fa fa-check"></i></button>') + '</div>';
    }).join('');
  }
  el('duvLista').addEventListener('click', function (e) {
    var ac = e.target.closest('[data-aceitar]');
    if (ac) { e.stopPropagation(); var q = duvidas[+ac.dataset.aceitar]; substituirIntervalo(q.i, q.i + q.len, q.txt); return; }
    var it = e.target.closest('.duv-it'); if (!it) return;
    focarDuvida(+it.dataset.k);
  });
  function focarDuvida(k) {
    var q = duvidas[k]; if (!q) return;
    trocarAba('texto');
    var ed = el('editor'); ed.focus(); ed.setSelectionRange(q.i, q.i + q.len);
    rolarAte(q.i);
    if (q.pag && atual && atual.paginas[q.pag - 1] && q.pag - 1 !== pg) mostrarPagina(q.pag - 1);
  }
  function proximaDuvida() {
    if (!duvidas.length) { toast('Nenhuma dúvida restante', 'info'); return; }
    var pos = el('editor').selectionEnd, k = duvidas.findIndex(function (q) { return q.i >= pos; });
    focarDuvida(k < 0 ? 0 : k);
  }
  el('btnAceitarTodas').onclick = function () {
    var t = el('editor').value, n = (t.match(/\[\?[^\]\n]*?\?\]/g) || []).length;
    if (!n) return;
    substituirIntervalo(0, t.length, t.replace(/\[\?([^\]\n]*?)\?\]/g, '$1'));
    toast(n + ' leitura(s) aceita(s)');
  };

  /* ============================== abas / avisos / salvar ============================== */
  function trocarAba(t) {
    qsa('#tabs button[data-tab]').forEach(function (b) { b.classList.toggle('on', b.dataset.tab === t); });
    ['texto', 'duvidas', 'dados'].forEach(function (x) { el('tab-' + x).style.display = x === t ? 'block' : 'none'; });
  }
  el('tabs').addEventListener('click', function (e) { var b = e.target.closest('button[data-tab]'); if (b) trocarAba(b.dataset.tab); });

  function renderAvisos() {
    var b = el('avisoTexto'), d = atual;
    if (!d || !d.avisos.length) { b.style.display = 'none'; return; }
    b.innerHTML = d.avisos.slice(0, 8).map(function (a) { return '<div><i class="fa fa-exclamation-triangle"></i> ' + esc(a) + '</div>'; }).join('') + (d.avisos.length > 8 ? '<div>… e mais ' + (d.avisos.length - 8) + '.</div>' : '');
    b.style.display = 'block';
  }
  function atualizarSalvoInfo() {
    var d = atual, s = el('salvoInfo'); if (!d) return;
    if (d.dirty) s.innerHTML = '<i class="fa fa-circle" style="color:#f59e0b;font-size:.6rem"></i> Alterações não salvas';
    else if (d.histId) s.innerHTML = '<i class="fa fa-check" style="color:#22c55e"></i> Salvo no histórico' + (d.salvoEm ? ' às ' + d.salvoEm : '');
    else s.textContent = '';
  }

  async function salvar(d, auto) {
    if (!d) return;
    if (d === atual) guardarEditor();
    var o = d.opcoes || lerOpcoes();
    var fd = new FormData();
    if (d.histId) fd.append('id', d.histId);
    fd.append('arquivo_nome', d.nome); fd.append('arquivo_mime', d.mime || ''); fd.append('arquivo_tamanho', d.tamanho || 0);
    if (d.hash) fd.append('arquivo_hash', d.hash);
    fd.append('paginas', d.paginas.filter(function (p) { return p.incluir; }).length || 1);
    fd.append('modo', o.modo); fd.append('precisao', o.precisao); fd.append('modelo', d.modelo || '');
    fd.append('texto', d.texto || '');
    if (d.dados) { fd.append('dados', JSON.stringify(d.dados)); fd.append('tipo_slug', d.dados.slug || ''); fd.append('tipo_nome', d.dados.nome || ''); }
    fd.append('tokens_entrada', d.usoPendente.entrada); fd.append('tokens_saida', d.usoPendente.saida); fd.append('duracao_ms', d.ms || 0);
    if (!d.histId && d.file && CFG.guardar_arquivos && d.file.size <= (CFG.max_upload || 0) * 0.95) fd.append('arquivo', d.file, d.nome);
    try {
      var j = await api('salvar', fd);
      d.histId = j.id; d.dirty = false; d.salvoEm = hora(); d.usoPendente = { entrada: 0, saida: 0 };
      if (d === atual) { atualizarSalvoInfo(); history.replaceState(null, '', '?abrir=' + d.histId); }
      if (!auto) toast('Salvo no histórico');
    } catch (e) {
      if (auto) { d.avisos.push('Não foi possível salvar no histórico: ' + e.message); if (d === atual) renderAvisos(); }
      else alerta('Não foi possível salvar', e.message, 'error');
    }
  }
  el('btnSalvar').onclick = function () { salvar(atual, false); };

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's' && atual && el('ws').style.display !== 'none') { e.preventDefault(); salvar(atual, false); }
    if (e.key === 'F8' && atual) { e.preventDefault(); proximaDuvida(); }
    if (e.key === 'Escape' && modoRegiao) { el('vwRegiao').click(); }
    if (!e.target.closest('input,textarea,select') && atual && el('ws').style.display !== 'none') {
      if (e.key === 'PageDown') { e.preventDefault(); mostrarPagina(pg + 1); }
      if (e.key === 'PageUp') { e.preventDefault(); mostrarPagina(pg - 1); }
    }
  });
  window.addEventListener('beforeunload', function (e) {
    guardarEditor();
    if (docs.some(function (d) { return d.dirty || d.status === 'processando'; })) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ============================== dados estruturados ============================== */
  var CONF_TXT = { alta: 'confiança alta', media: 'confiança média', baixa: 'conferir — leitura difícil' };
  function badgesCampo(c) {
    var h = '';
    if (c.valor == null && c.tipo !== 'lista') return '';
    if (c.confianca === 'baixa') h += '<span class="bdg warn"><i class="fa fa-eye"></i> ' + CONF_TXT.baixa + '</span>';
    else if (c.confianca === 'media') h += '<span class="bdg info">' + CONF_TXT.media + '</span>';
    var v = c.validacao || {};
    if (v.ok === true) h += '<span class="bdg ok"><i class="fa fa-check"></i> ' + esc(v.msg || 'válido') + '</span>';
    else if (v.ok === false) h += '<span class="bdg bad"><i class="fa fa-times"></i> ' + esc(v.msg || 'inválido') + '</span>';
    else if (v.msg) h += '<span class="bdg info">' + esc(v.msg) + '</span>';
    return h;
  }
  function renderDados() {
    var d = atual, box = el('dadosCorpo'), cnt = el('cntDados');
    el('btnDadosExtrair').disabled = !d || (!d.file && !(d.texto || '').trim());
    if (!d || !d.dados) {
      cnt.style.display = 'none'; el('dadosTitulo').textContent = '';
      box.innerHTML = '<div class="vazio">Os dados aparecem aqui quando o modo <b>Dados</b> ou <b>Texto + dados</b> é usado — ou clique em <b>Extrair dados</b>.</div>';
      return;
    }
    var D = d.dados, res = D.resumo || {};
    el('dadosTitulo').innerHTML = '<i class="fa fa-table" style="color:var(--ir-primary)"></i> ' + esc(D.nome)
      + (D.classificacao && D.classificacao.titulo ? ' <span class="muted">— “' + esc(D.classificacao.titulo) + '”</span>' : '');
    var alertas = (res.invalidos || 0) + (res.baixa || 0) + (D.avisos || []).length;
    cnt.style.display = ''; cnt.textContent = alertas || '✓'; cnt.className = 'cnt ' + (alertas ? 'alerta' : 'ok');
    var h = '';
    if ((D.avisos || []).length) h += '<div class="aviso dd-avisos">' + D.avisos.map(function (a) { return '<div><i class="fa fa-exclamation-triangle"></i> ' + esc(a.msg) + '</div>'; }).join('') + '</div>';
    h += '<div class="muted" style="margin-bottom:10px">' + (res.preenchidos || 0) + ' de ' + (res.total || 0) + ' campos encontrados'
      + (res.invalidos ? ' · <b style="color:var(--ir-bad)">' + res.invalidos + ' com erro de validação</b>' : '')
      + (res.baixa ? ' · <b style="color:var(--ir-warn)">' + res.baixa + ' para conferir</b>' : '') + '</div>';
    if (D.observacoes) h += '<div class="dd-obs"><b>Observações:</b> ' + esc(D.observacoes) + '</div>';
    var cheios = [], vazios = [];
    (D.ordem || Object.keys(D.campos)).forEach(function (k) { var c = D.campos[k]; if (!c) return; ((c.tipo === 'lista' ? c.itens.length : c.valor != null) ? cheios : vazios).push(c); });
    h += '<div class="dd-grupo">' + cheios.map(linhaCampo).join('') + (cheios.length ? '' : '<div class="vazio">Nenhum campo encontrado.</div>') + '</div>';
    if (vazios.length) h += '<details class="dd-vazios"><summary>Campos não encontrados no documento (' + vazios.length + ') — preencha se necessário</summary><div class="dd-grupo">' + vazios.map(linhaCampo).join('') + '</div></details>';
    box.innerHTML = h;
  }
  function linhaCampo(c) {
    var cls = 'dd-row' + (c.validacao && c.validacao.ok === false ? ' invalido' : c.confianca === 'baixa' && (c.valor != null || (c.itens || []).length) ? ' baixa' : '');
    var lbl = '<div class="dd-lbl">' + esc(c.rotulo) + (c.pagina ? '<span class="pg" data-pg="' + c.pagina + '" title="Ver a página">p.' + c.pagina + '</span>' : '') + '</div>';
    if (c.tipo === 'lista') {
      var th = c.subcampos.map(function (s) { return '<th>' + esc(s.rotulo) + '</th>'; }).join('');
      var trs = c.itens.map(function (it, i) {
        var val = (c.itens_validacao || [])[i] || {};
        return '<tr>' + c.subcampos.map(function (s) {
          return '<td><input class="inp" data-k="' + esc(c.chave) + '" data-i="' + i + '" data-s="' + esc(s.chave) + '" value="' + esc(it[s.chave] == null ? '' : it[s.chave]) + '"' + (val[s.chave] ? ' style="border-color:#ef4444" title="' + esc(val[s.chave]) + '"' : '') + '></td>';
        }).join('') + '<td class="x"><button class="ib" data-rm="' + esc(c.chave) + '" data-i="' + i + '" title="Remover linha"><i class="fa fa-trash-o"></i></button></td></tr>';
      }).join('');
      return '<div class="' + cls + '" style="grid-template-columns:1fr">' + lbl + '<div class="dd-val"><table class="dd-tab"><thead><tr>' + th + '<th></th></tr></thead><tbody>' + trs + '</tbody></table>'
        + '<button class="lnk" data-add="' + esc(c.chave) + '"><i class="fa fa-plus"></i> adicionar linha</button><div class="dd-bad">' + badgesCampo(c) + '</div></div></div>';
    }
    var v = c.valor == null ? '' : c.valor;
    var inp = c.tipo === 'texto_longo' ? '<textarea class="inp" data-k="' + esc(c.chave) + '">' + esc(v) + '</textarea>' : '<input class="inp" data-k="' + esc(c.chave) + '" value="' + esc(v) + '">';
    return '<div class="' + cls + '" data-row="' + esc(c.chave) + '">' + lbl + '<div class="dd-val">' + inp + '<div class="dd-bad">' + badgesCampo(c) + '</div></div></div>';
  }
  el('dadosCorpo').addEventListener('input', function (e) {
    var t = e.target; if (!t.dataset.k || !atual || !atual.dados) return;
    var c = atual.dados.campos[t.dataset.k]; if (!c) return;
    if (t.dataset.s) c.itens[+t.dataset.i][t.dataset.s] = t.value || null;
    else { c.valor = t.value === '' ? null : t.value; c.confianca = 'alta'; }
    atual.dirty = true; atualizarSalvoInfo();
  });
  el('dadosCorpo').addEventListener('change', function (e) {
    var t = e.target; if (!t.dataset.k || t.dataset.s || !atual || !atual.dados) return;
    var c = atual.dados.campos[t.dataset.k]; if (!c) return;
    api('validar_campo', { tipo: c.tipo, valor: t.value }).then(function (j) {
      c.validacao = { ok: j.validacao.ok, msg: j.validacao.msg };
      if (j.validacao.valor != null && j.validacao.valor !== t.value && j.validacao.ok !== false) { c.valor = j.validacao.valor; t.value = c.valor; }
      var row = t.closest('.dd-row'); row.classList.toggle('invalido', c.validacao.ok === false); row.classList.remove('baixa');
      qs('.dd-bad', row).innerHTML = badgesCampo(c);
    }).catch(function () { });
  });
  el('dadosCorpo').addEventListener('click', function (e) {
    var p = e.target.closest('[data-pg]'); if (p) { mostrarPagina(+p.dataset.pg - 1); return; }
    var add = e.target.closest('[data-add]'), rm = e.target.closest('[data-rm]');
    if (!add && !rm) return;
    var c = atual.dados.campos[(add || rm).dataset.add || (add || rm).dataset.rm];
    if (add) { var n = {}; c.subcampos.forEach(function (s) { n[s.chave] = null; }); c.itens.push(n); (c.itens_validacao = c.itens_validacao || []).push({}); }
    else { c.itens.splice(+rm.dataset.i, 1); (c.itens_validacao || []).splice(+rm.dataset.i, 1); }
    atual.dirty = true; atualizarSalvoInfo(); renderDados();
  });
  function simplificarDados(D) {
    var out = {};
    (D.ordem || Object.keys(D.campos)).forEach(function (k) { var c = D.campos[k]; if (!c) return; out[c.tipo === 'lista' || D.slug !== 'livre' ? k : c.rotulo] = c.tipo === 'lista' ? c.itens : c.valor; });
    return { tipo: D.nome, tipo_slug: D.slug, campos: out, observacoes: D.observacoes || '' };
  }
  el('btnDadosCopiar').onclick = function () { if (atual && atual.dados) copiarTexto(JSON.stringify(simplificarDados(atual.dados), null, 2)); };
  qsa('[data-baixar-dados]').forEach(function (b) {
    b.onclick = function () {
      fecharMenus(); if (!atual || !atual.dados) return;
      var D = atual.dados;
      if (b.dataset.baixarDados === 'json') { baixarBlob(new Blob([JSON.stringify(simplificarDados(D), null, 2)], { type: 'application/json' }), nomeBase() + '-dados.json'); return; }
      var linhas = [['Campo', 'Valor', 'Confiança', 'Validação']], csv = function (v) { v = v == null ? '' : String(v); return /[;"\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
      (D.ordem || Object.keys(D.campos)).forEach(function (k) {
        var c = D.campos[k]; if (!c) return;
        if (c.tipo === 'lista') c.itens.forEach(function (it, i) { c.subcampos.forEach(function (s) { linhas.push([c.rotulo + ' ' + (i + 1) + ' — ' + s.rotulo, it[s.chave], c.confianca, '']); }); });
        else linhas.push([c.rotulo, c.valor, c.valor == null ? '' : c.confianca, c.validacao && c.validacao.ok === false ? c.validacao.msg : '']);
      });
      baixarBlob(new Blob(['﻿' + linhas.map(function (l) { return l.map(csv).join(';'); }).join('\r\n')], { type: 'text/csv;charset=utf-8' }), nomeBase() + '-dados.csv');
    };
  });
  el('btnDadosExtrair').onclick = async function () {
    var d = atual; if (!d) return;
    var o = Object.assign(lerOpcoes(), { modo: 'dados' });
    var btn = this; btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Extraindo…';
    d.avisos = d.avisos.filter(function (a) { return a.indexOf('Dados:') !== 0; });
    try { await extrairDados(d, o); renderAvisos(); if (d.dados) { await salvar(d, true); trocarAba('dados'); } }
    catch (e) { alerta('Não foi possível extrair os dados', e.message, 'error'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="fa fa-refresh"></i> Extrair dados'; renderAvisos(); }
  };

  /* ============================== histórico ============================== */
  async function abrirHistorico(id) {
    var j;
    try { j = await api('hist_abrir', { id: id }); } catch (e) { alerta('Não foi possível abrir', e.message, 'error'); return; }
    var it = j.item;
    var ja = docs.find(function (x) { return x.histId === it.id; });
    if (ja) { exibirDoc(ja); return; }
    var d = novoDoc(null, 'bruto');
    d.nome = it.arquivo_nome; d.histId = it.id; d.texto = it.texto || ''; d.dados = it.dados; d.status = 'pronto'; d.carregado = true;
    d.modelo = it.modelo || ''; d.tamanho = +it.arquivo_tamanho || 0; d.mime = it.arquivo_mime || ''; d.hash = it.arquivo_hash; d.unido = false;
    d.opcoes = { modo: it.modo, precisao: it.precisao, tipo: it.tipo_slug || 'auto', marcadores: true, digital: false };
    docs.push(d); exibirDoc(d);
    if (it.tem_arquivo) {
      try {
        var r = await fetch('api.php?acao=hist_arquivo&id=' + it.id + '&csrf=' + encodeURIComponent(C.csrf), { credentials: 'same-origin' });
        if (!r.ok || (r.headers.get('Content-Type') || '').indexOf('application/json') === 0) throw new Error('arquivo indisponível');
        var blob = await r.blob();
        d.file = new File([blob], it.arquivo_nome, { type: it.arquivo_mime || blob.type });
        d.tipoArq = tipoDoArquivo(d.file) || 'bruto'; d.carregado = false;
        if (d === atual) mostrarPagina(0);
        await carregarDoc(d);
        d.paginas.forEach(function (p) { p.status = 'ok'; });
        if (d === atual) exibirDoc(d);
      } catch (e) { d.file = null; if (d === atual) mostrarPagina(0); }
    }
  }

  /* ============================== upload ============================== */
  var dz = el('dz'), fi = el('fileInput');
  dz.addEventListener('click', function () { fi.click(); });
  dz.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fi.click(); } });
  ['dragover', 'dragenter'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('drag'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('drag'); }); });
  dz.addEventListener('drop', function (e) { adicionarArquivos(e.dataTransfer.files); });
  fi.addEventListener('change', function () { adicionarArquivos(fi.files); fi.value = ''; });
  document.addEventListener('paste', function (e) {
    if (e.target.closest && e.target.closest('textarea,input')) return;
    var fs = []; Array.prototype.forEach.call((e.clipboardData && e.clipboardData.items) || [], function (it) { if (it.kind === 'file') { var f = it.getAsFile(); if (f) fs.push(new File([f], f.name && f.name !== 'image.png' ? f.name : 'colado-' + hora().replace(':', 'h') + '.png', { type: f.type })); } });
    if (fs.length) { e.preventDefault(); adicionarArquivos(fs); }
  });
  // arrastar arquivos para qualquer lugar da página
  document.addEventListener('dragover', function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') >= 0) e.preventDefault(); });
  document.addEventListener('drop', function (e) { if (e.target.closest && e.target.closest('#dz')) return; if (e.dataTransfer && e.dataTransfer.files.length) { e.preventDefault(); adicionarArquivos(e.dataTransfer.files); } });

  /* ============================== início ============================== */
  atualizarOpcoesUI();
  if (C.abrir) abrirHistorico(C.abrir);
})();
