/* =====================================================================
   Painel do Administrador — Atlas
   Interface: abas, gráficos (Chart.js 4), linha do tempo da auditoria,
   gaveta de detalhes, configurações e análise com IA.
   ===================================================================== */
(function () {
  'use strict';

  /* ============================ Utilidades ============================ */
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  var nfBRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  var nfInt = new Intl.NumberFormat('pt-BR');
  var nf1 = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });
  function brl(v) { return nfBRL.format(+v || 0); }
  function brlC(v) { v = +v || 0; var a = Math.abs(v); if (a >= 1e6) return 'R$ ' + nf1.format(v / 1e6) + ' mi'; if (a >= 1e4) return 'R$ ' + nf1.format(v / 1e3) + ' mil'; return brl(v); }
  function int(v) { return nfInt.format(+v || 0); }
  function pct(v) { return v == null ? '—' : nf1.format(v) + '%'; }
  function dataBR(s) { if (!s) return '—'; var d = String(s).slice(0, 10).split('-'); return d.length === 3 ? d[2] + '/' + d[1] + '/' + d[0] : s; }
  function horaBR(s) { return s ? String(s).slice(11, 16) : ''; }
  function dataHoraBR(s) { return s ? dataBR(s) + ' ' + horaBR(s) : '—'; }
  function relativo(s) {
    if (!s) return 'nunca';
    var t = new Date(String(s).replace(' ', 'T')), d = (Date.now() - t.getTime()) / 1000;
    if (d < 60) return 'agora'; if (d < 3600) return 'há ' + Math.floor(d / 60) + ' min'; if (d < 86400) return 'há ' + Math.floor(d / 3600) + ' h';
    if (d < 86400 * 30) return 'há ' + Math.floor(d / 86400) + ' dia' + (d >= 172800 ? 's' : ''); return dataBR(s);
  }
  function iniciais(n) { var p = String(n || '?').trim().split(/\s+/); return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase(); }
  var PALETA = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899', '#f97316', '#22c55e', '#3b82f6', '#a855f7'];
  function corDe(s) { var h = 0; s = String(s || ''); for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0; return PALETA[h % PALETA.length]; }
  function avatar(nome, login, cls) { var c = corDe(login || nome); return '<span class="pa-av ' + (cls || '') + '" style="background:linear-gradient(135deg,' + c + ',' + c + 'cc)">' + esc(iniciais(nome)) + '</span>'; }
  function delta(v, inverso) {
    if (v == null) return '<span class="pa-delta flat">novo</span>';
    var bom = inverso ? v < 0 : v > 0, cls = Math.abs(v) < 0.05 ? 'flat' : (bom ? 'up' : 'down');
    return '<span class="pa-delta ' + cls + '"><i class="fas fa-arrow-' + (v >= 0 ? 'up' : 'down') + '" style="font-size:.7em"></i>' + nf1.format(Math.abs(v)) + '%</span>';
  }
  function cssVar(n) { return getComputedStyle(document.body).getPropertyValue(n).trim(); }
  function escuro() { return document.body.classList.contains('dark-mode'); }
  function toast(msg, erro) {
    var t = $('#paToast'); t.textContent = msg; t.className = 'pa-toast mostra' + (erro ? ' erro' : '');
    clearTimeout(toast._t); toast._t = setTimeout(function () { t.className = 'pa-toast'; }, 3800);
  }
  function api(acao, params, post) {
    params = params || {};
    var url = 'api.php?acao=' + encodeURIComponent(acao), opt = { credentials: 'same-origin' };
    var q = Object.assign({ ini: estado.ini, fim: estado.fim }, post ? {} : params);
    Object.keys(q).forEach(function (k) { if (q[k] !== undefined && q[k] !== null && q[k] !== '') url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(q[k]); });
    if (post) {
      var fd = new FormData(); fd.append('csrf', PA.csrf);
      Object.keys(params).forEach(function (k) { if (params[k] !== undefined && params[k] !== null) fd.append(k, params[k]); });
      opt.method = 'POST'; opt.body = fd;
    }
    return fetch(url, opt).then(function (r) {
      return r.json().catch(function () { throw new Error('Resposta inválida do servidor (HTTP ' + r.status + ').'); });
    }).then(function (j) {
      if (!j.ok) throw new Error(j.erro || 'Erro desconhecido.');
      return j.dados;
    });
  }

  /* ============================ Estado ============================ */
  var estado = { p: 'mes', ini: null, fim: null, aba: 'geral', cache: {}, filtros: { sub: 'operacoes', pagina: 1, sem_navegacao: 1 } };
  var charts = {};
  try {
    var salvo = JSON.parse(localStorage.getItem('pa_estado') || '{}');
    if (salvo.p) estado.p = salvo.p;
    if (salvo.p === 'custom' && salvo.ini && salvo.fim) { estado.ini = salvo.ini; estado.fim = salvo.fim; }
  } catch (e) { }
  function guardar() { try { localStorage.setItem('pa_estado', JSON.stringify({ p: estado.p, ini: estado.ini, fim: estado.fim })); } catch (e) { } }

  function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function calcPeriodo(p) {
    var h = new Date(PA.hoje + 'T12:00:00'), i = new Date(h), f = new Date(h);
    if (p === 'hoje') { /* hoje */ }
    else if (p === '7d') i.setDate(h.getDate() - 6);
    else if (p === '30d') i.setDate(h.getDate() - 29);
    else if (p === 'mes') i = new Date(h.getFullYear(), h.getMonth(), 1);
    else if (p === 'mes_ant') { i = new Date(h.getFullYear(), h.getMonth() - 1, 1); f = new Date(h.getFullYear(), h.getMonth(), 0); }
    else if (p === 'ano') i = new Date(h.getFullYear(), 0, 1);
    else return;
    estado.ini = ymd(i); estado.fim = ymd(f);
  }
  function mostrarPeriodo() {
    $$('#paChips button').forEach(function (b) { b.classList.toggle('ativo', b.dataset.p === estado.p); });
    $('#paDatas').classList.toggle('ativo', estado.p === 'custom');
    $('#paIni').value = estado.ini; $('#paFim').value = estado.fim;
    var dias = Math.round((new Date(estado.fim) - new Date(estado.ini)) / 864e5) + 1;
    $('#paPeriodoInfo').innerHTML = '<i class="far fa-calendar"></i> ' + dataBR(estado.ini) + ' a ' + dataBR(estado.fim) + ' · ' + dias + ' dia' + (dias > 1 ? 's' : '') + ' · comparado ao período anterior';
  }

  /* ============================ Gráficos ============================ */
  function temaGraficos() {
    if (!window.Chart) return;
    Chart.defaults.font.family = "'Inter', -apple-system, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.font.size = 11.5;
    Chart.defaults.color = cssVar('--pa-chart-text') || '#64748b';
    Chart.defaults.borderColor = cssVar('--pa-chart-grid') || 'rgba(0,0,0,.06)';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = escuro() ? '#1e293b' : '#0f172a';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 10;
    Chart.defaults.plugins.tooltip.titleFont = { weight: '700' };
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.elements.line.cubicInterpolationMode = 'monotone';
  }
  function grafico(id, cfg) {
    if (charts[id]) { charts[id].destroy(); delete charts[id]; }
    var el = document.getElementById(id); if (!el || !window.Chart) return null;
    charts[id] = new Chart(el, cfg); return charts[id];
  }
  function limparGraficos() { Object.keys(charts).forEach(function (k) { try { charts[k].destroy(); } catch (e) { } delete charts[k]; }); }
  function degrade(cor, a1, a2) {
    return function (ctx) {
      var c = ctx.chart, area = c.chartArea; if (!area) return cor;
      var g = c.ctx.createLinearGradient(0, area.top, 0, area.bottom);
      g.addColorStop(0, hexA(cor, a1 == null ? .35 : a1)); g.addColorStop(1, hexA(cor, a2 == null ? .02 : a2)); return g;
    };
  }
  function hexA(hex, a) { var h = hex.replace('#', ''); if (h.length === 3) h = h.split('').map(function (x) { return x + x; }).join(''); var n = parseInt(h, 16); return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')'; }
  var tickBRL = function (v) { return brlC(v); };
  var tipBRL = { callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + brl(c.parsed.y != null ? c.parsed.y : c.parsed); } } };
  function sparkline(id, dados, cor) {
    if (!dados || !dados.length) return;
    grafico(id, { type: 'line', data: { labels: dados.map(function (_, i) { return i; }), datasets: [{ data: dados, borderColor: cor, borderWidth: 2, pointRadius: 0, tension: .35, fill: true, backgroundColor: degrade(cor, .28, 0) }] },
      options: { responsive: false, animation: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false } }, elements: { line: { capBezierPoints: true } } } });
  }
  function rosca(id, rotulos, valores, cores, fmtFn) {
    return grafico(id, { type: 'doughnut', data: { labels: rotulos, datasets: [{ data: valores, backgroundColor: cores, borderWidth: 2, borderColor: cssVar('--pa-panel'), hoverOffset: 6 }] },
      options: { cutout: '68%', plugins: { legend: { position: 'bottom', labels: { padding: 12 } }, tooltip: { callbacks: { label: function (c) { var t = c.dataset.data.reduce(function (a, b) { return a + b; }, 0); return ' ' + c.label + ': ' + (fmtFn ? fmtFn(c.parsed) : int(c.parsed)) + ' (' + nf1.format(t ? c.parsed / t * 100 : 0) + '%)'; } } } } } });
  }

  /* ============================ Gaveta e modal ============================ */
  function abrirGaveta(tit, html) {
    $('#paGavetaTit').innerHTML = tit; $('#paGavetaC').innerHTML = html; $('#paGavetaC').scrollTop = 0;
    $('#paGaveta').classList.add('aberta'); $('#paGavetaFundo').classList.add('aberta'); $('#paGaveta').setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function fecharGaveta() {
    $('#paGaveta').classList.remove('aberta'); $('#paGavetaFundo').classList.remove('aberta'); $('#paGaveta').setAttribute('aria-hidden', 'true');
    document.body.style.overflow = ''; ['gavRadar', 'gavHeat'].forEach(function (k) { if (charts[k]) { charts[k].destroy(); delete charts[k]; } });
  }
  function carregando(el) { el.innerHTML = '<div class="pa-grid pa-g4">' + [1, 2, 3, 4].map(function () { return '<div class="pa-skel" style="height:110px"></div>'; }).join('') + '</div><div class="pa-skel pa-mt" style="height:340px"></div>'; }
  function erroHTML(e) { return '<div class="pa-card pa-vazio"><i class="fas fa-exclamation-triangle" style="color:var(--pa-red);opacity:1"></i>' + esc(e.message || e) + '<div style="margin-top:12px"><button class="pa-btn" onclick="location.reload()"><i class="fas fa-redo"></i> Tentar de novo</button></div></div>'; }

  /* ============================ KPI ============================ */
  function kpi(o) {
    return '<div class="pa-kpi pa-fade" style="--k:' + o.cor + '"><div class="pa-kpi-top"><span class="pa-kpi-rot">' + esc(o.rot) + '</span><span class="pa-kpi-ico"><i class="fas ' + o.ico + '"></i></span></div>' +
      '<div class="pa-kpi-val" title="' + esc(o.titulo || '') + '">' + o.val + '</div>' +
      '<div class="pa-kpi-rod"><span class="pa-kpi-sub">' + (o.delta !== undefined ? delta(o.delta, o.inverso) + ' ' : '') + (o.sub || '') + '</span>' + (o.spark ? '<canvas class="pa-spark" id="' + o.spark + '" width="92" height="30"></canvas>' : '') + '</div></div>';
  }

  /* ============================ Navegação ============================ */
  var renderizadores = {};
  function irPara(aba, filtro) {
    estado.aba = aba;
    $$('#paAbas button').forEach(function (b) { b.classList.toggle('ativo', b.dataset.aba === aba); });
    var bt = $('#paAbas button[data-aba="' + aba + '"]'); if (bt && bt.scrollIntoView) bt.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
    if (filtro) { estado.filtros = Object.assign({ sub: aba === 'trilhas' ? 'trilhas' : 'operacoes', pagina: 1, sem_navegacao: 1 }, filtro); }
    if (aba === 'trilhas') { estado.aba = 'auditoria'; estado.filtros.sub = 'trilhas'; $$('#paAbas button').forEach(function (b) { b.classList.toggle('ativo', b.dataset.aba === 'auditoria'); }); }
    try { history.replaceState(null, '', '#' + estado.aba); } catch (e) { }
    limparGraficos();
    var c = $('#paConteudo'); carregando(c);
    (renderizadores[estado.aba] || renderizadores.geral)(c);
  }
  function dados(acao, params) {
    var k = acao + '|' + estado.ini + '|' + estado.fim + '|' + JSON.stringify(params || {});
    if (estado.cache[k]) return Promise.resolve(estado.cache[k]);
    return api(acao, params).then(function (d) { estado.cache[k] = d; return d; });
  }

  /* ============================ Visão geral ============================ */
  renderizadores.geral = function (c) {
    dados('visao_geral').then(function (d) {
      var f = d.faturamento, t = f.totais, v = f.variacao, s = d.saude, aud = d.auditoria, tar = d.tarefas;
      var corS = s.pontuacao >= 85 ? '#10b981' : s.pontuacao >= 70 ? '#22c55e' : s.pontuacao >= 50 ? '#f59e0b' : '#ef4444';
      var nomesComp = { financeiro: 'Financeiro', tarefas: 'Prazos', seguranca: 'Segurança', integridade: 'Integridade', deposito: 'Depósitos' };
      var badgeAlertas = d.alertas.filter(function (a) { return a.nivel === 'critico'; }).length;
      var bA = $('#paAbas button[data-aba="auditoria"]'); var old = bA.querySelector('.pa-badge-n'); if (old) old.remove();
      if (badgeAlertas) bA.insertAdjacentHTML('beforeend', '<span class="pa-badge-n">' + badgeAlertas + '</span>');

      var h = '<div class="pa-grid pa-g-37">' +
        '<div class="pa-card pa-saude pa-fade"><div class="pa-card-h" style="width:100%"><h3><i class="fas fa-heartbeat"></i> Saúde da serventia</h3><span class="pa-dica">0 a 100</span></div>' +
        '<div class="pa-gauge"><canvas id="gSaude"></canvas><div class="pa-gauge-val"><b>' + s.pontuacao + '</b><span style="background:' + hexA(corS, .15) + ';color:' + corS + '">' + esc(s.rotulo) + '</span></div></div>' +
        '<div class="pa-comp">' + Object.keys(s.componentes).map(function (k) {
          var x = s.componentes[k], cc = x >= 80 ? ['#10b981', '#2dd4bf'] : x >= 55 ? ['#f59e0b', '#fbbf24'] : ['#ef4444', '#f97316'];
          return '<div class="pa-comp-l"><span>' + nomesComp[k] + '</span><div class="pa-barra"><i style="width:' + x + '%;--c1:' + cc[0] + ';--c2:' + cc[1] + '"></i></div><b>' + x + '</b></div>';
        }).join('') + '</div></div>' +
        '<div><div class="pa-kpis">' +
        kpi({ rot: 'Recebido', ico: 'fa-hand-holding-usd', cor: '#6366f1', val: brl(t.recebido), delta: v.recebido, sub: int(t.recebimentos) + ' pagamentos', spark: 'spRec' }) +
        kpi({ rot: 'Atos praticados', ico: 'fa-stamp', cor: '#0ea5e9', val: brl(t.praticado), delta: v.praticado, sub: int(t.atos_qtd) + ' atos', spark: 'spPrat' }) +
        kpi({ rot: 'Emolumentos', ico: 'fa-landmark', cor: '#10b981', val: brl(t.emolumentos), delta: v.emolumentos, sub: 'receita da serventia', spark: 'spEmol' }) +
        kpi({ rot: 'O.S. criadas', ico: 'fa-file-signature', cor: '#8b5cf6', val: int(t.os_criadas), delta: v.os_criadas, sub: t.os_canceladas ? int(t.os_canceladas) + ' canceladas' : 'nenhuma cancelada', spark: 'spOs' }) +
        kpi({ rot: 'Ticket médio', ico: 'fa-receipt', cor: '#f59e0b', val: brl(t.ticket_medio), delta: v.ticket_medio, sub: 'por O.S.' }) +
        kpi({ rot: 'Resultado do caixa', ico: 'fa-balance-scale', cor: t.resultado >= 0 ? '#14b8a6' : '#ef4444', val: brl(t.resultado), delta: v.resultado, sub: 'entradas − saídas' }) +
        kpi({ rot: 'Tarefas no prazo', ico: 'fa-check-double', cor: '#22c55e', val: tar.concluidas ? pct(tar.concluidas_no_prazo / tar.concluidas * 100) : '—', sub: int(tar.concluidas) + ' concluídas · ' + int(tar.atrasadas) + ' atrasadas' }) +
        kpi({ rot: 'Operações auditadas', ico: 'fa-fingerprint', cor: '#ec4899', val: int(aud.operacoes), sub: int(aud.criticas) + ' críticas · ' + int(aud.dados) + ' registros' }) +
        '</div></div></div>';

      h += '<div class="pa-grid pa-g-64 pa-mt">' +
        '<div class="pa-card pa-fade"><div class="pa-card-h"><h3><i class="fas fa-chart-area"></i> Recebido × atos praticados</h3><span class="pa-dica">por ' + ({ dia: 'dia', semana: 'semana', mes: 'mês' })[f.granularidade] + '</span></div><div class="pa-chart lg"><canvas id="gFluxo"></canvas></div></div>' +
        '<div class="pa-card pa-fade"><div class="pa-card-h"><h3><i class="fas fa-bell"></i> Alertas inteligentes</h3><span class="pa-dica">' + d.alertas.length + '</span></div>' +
        (d.alertas.length ? '<div class="pa-alertas">' + d.alertas.map(alertaHTML).join('') + '</div>' : '<div class="pa-vazio"><i class="fas fa-check-circle" style="color:var(--pa-green);opacity:1"></i>Nenhum ponto de atenção no período.</div>') +
        '</div></div>';

      h += '<div class="pa-grid pa-g3 pa-mt">' +
        '<div class="pa-card pa-fade"><div class="pa-card-h"><h3><i class="fas fa-trophy"></i> Destaques da equipe</h3><a href="#equipe" data-ir="equipe" class="pa-dica">ver todos</a></div><div class="pa-top">' +
        (d.equipe_top.length ? d.equipe_top.map(function (m, i) {
          return '<div class="pa-top-l" data-membro="' + esc(m.usuario) + '"><span class="pa-pos">' + (i + 1) + '</span>' + avatar(m.nome, m.usuario) +
            '<div class="pa-nm"><b>' + esc(m.nome) + '</b><span>' + int(m.atos_qtd) + ' atos · ' + brlC(m.recebido) + '</span></div>' + anel(m.pontuacao) + '</div>';
        }).join('') : '<div class="pa-vazio">Sem atividade no período.</div>') + '</div></div>' +
        '<div class="pa-card pa-fade"><div class="pa-card-h"><h3><i class="fas fa-fingerprint"></i> Auditoria do período</h3><a href="#auditoria" data-ir="auditoria" class="pa-dica">abrir trilha</a></div>' +
        '<div class="pa-membro-st" style="grid-template-columns:repeat(3,1fr);margin-top:0">' +
        mini('Operações', int(aud.operacoes)) + mini('Críticas', int(aud.criticas), aud.criticas ? 'var(--pa-red)' : '') + mini('Falhas', int(aud.falhas), aud.falhas ? 'var(--pa-amber)' : '') +
        mini('Negados', int(aud.negados), aud.negados ? 'var(--pa-amber)' : '') + mini('Fora do horário', int(aud.fora)) + mini('Telas abertas', int(aud.telas)) + '</div>' +
        '<div class="pa-chart xs pa-mt"><canvas id="gAudMini"></canvas></div></div>' +
        '<div class="pa-card pa-fade"><div class="pa-card-h"><h3><i class="fas fa-exclamation-circle"></i> Eventos que merecem atenção</h3></div>' +
        (aud.recentes && aud.recentes.length ? '<div>' + aud.recentes.slice(0, 6).map(eventoCompacto).join('') + '</div>' : '<div class="pa-vazio"><i class="fas fa-check-circle"></i>Nada crítico registrado.</div>') + '</div></div>';

      h += '<div class="pa-mt" id="paIaResumo"></div>';
      c.innerHTML = h;

      // Medidor de saúde
      grafico('gSaude', { type: 'doughnut', data: { datasets: [{ data: [s.pontuacao, 100 - s.pontuacao], backgroundColor: [corS, cssVar('--pa-panel-2')], borderWidth: 0, borderRadius: [8, 0] }] },
        options: { rotation: -90, circumference: 180, cutout: '78%', plugins: { legend: { display: false }, tooltip: { enabled: false } }, animation: { animateRotate: true, duration: 1100 } } });
      sparkline('spRec', f.serie_recebido, '#6366f1'); sparkline('spPrat', f.serie_praticado, '#0ea5e9');
      sparkline('spEmol', f.serie_emolumentos, '#10b981'); sparkline('spOs', f.serie_os, '#8b5cf6');
      grafico('gFluxo', {
        data: { labels: f.eixo, datasets: [
          { type: 'bar', label: 'Atos praticados', data: f.serie_praticado, backgroundColor: hexA('#0ea5e9', .75), borderRadius: 6, maxBarThickness: 26, order: 2 },
          { type: 'line', label: 'Recebido', data: f.serie_recebido, borderColor: '#6366f1', backgroundColor: degrade('#6366f1', .25, 0), fill: true, tension: .35, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 5, order: 1 }
        ] },
        options: { interaction: { mode: 'index', intersect: false }, plugins: { tooltip: tipBRL, legend: { position: 'top', align: 'end' } }, scales: { y: { ticks: { callback: tickBRL }, beginAtZero: true }, x: { grid: { display: false } } } }
      });
      if (aud.serie) grafico('gAudMini', { type: 'bar', data: { labels: aud.eixo, datasets: [
        { label: 'Operações', data: aud.serie, backgroundColor: hexA('#ec4899', .55), borderRadius: 4, maxBarThickness: 14 },
        { label: 'Críticas', data: aud.serie_criticas, backgroundColor: '#ef4444', borderRadius: 4, maxBarThickness: 14 }] },
        options: { plugins: { legend: { display: false } }, scales: { x: { stacked: false, display: false }, y: { beginAtZero: true, ticks: { precision: 0 } } } } });
      iaResumo(d.ia);
    }).catch(function (e) { c.innerHTML = erroHTML(e); });
  };
  function mini(r, v, cor) { return '<div><small>' + r + '</small><b' + (cor ? ' style="color:' + cor + '"' : '') + '>' + v + '</b></div>'; }
  function anel(v) { var c = v >= 80 ? '#10b981' : v >= 60 ? '#6366f1' : v >= 40 ? '#f59e0b' : '#ef4444'; return '<span class="pa-ring" style="--v:' + v + ';--c:' + c + '"><span>' + v + '</span></span>'; }
  function alertaHTML(a) {
    return '<div class="pa-alerta ' + a.nivel + '"' + (a.acao ? ' data-aba="' + esc(a.acao.aba) + '" data-filtro=\'' + esc(JSON.stringify(a.acao.filtro || null)) + '\'' : '') + '>' +
      '<span class="pa-al-ico"><i class="fas ' + esc(a.icone) + '"></i></span><div><b>' + esc(a.titulo) + '</b><p>' + esc(a.texto) + '</p></div></div>';
  }
  function eventoCompacto(e) {
    return '<div class="pa-ev" data-ev="' + e.id + '" style="padding:7px 6px"><span class="pa-ev-ico" style="--c:' + e.cor + ';width:34px;height:34px"><i class="fas ' + e.icone + '"></i></span>' +
      '<div class="pa-ev-c"><div class="pa-ev-t" style="font-size:.84rem">' + esc(e.acao) + '</div><div class="pa-ev-m">' + esc(e.nome || e.usuario || '—') + ' · ' + dataHoraBR(e.criado_em) + '</div></div>' +
      '<div class="pa-ev-r"><span class="pa-res ' + e.resultado + '">' + rotuloRes(e.resultado) + '</span></div></div>';
  }
  function rotuloRes(r) { return { sucesso: 'sucesso', falha: 'falhou', negado: 'negado' }[r] || r; }

  /* ============================ Faturamento ============================ */
  renderizadores.faturamento = function (c) {
    dados('faturamento').then(function (f) {
      var t = f.totais, v = f.variacao, dep = f.deposito;
      var h = '<div class="pa-kpis k4">' +
        kpi({ rot: 'Recebido', ico: 'fa-hand-holding-usd', cor: '#6366f1', val: brl(t.recebido), delta: v.recebido, sub: int(t.recebimentos) + ' pagamentos' }) +
        kpi({ rot: 'Atos praticados', ico: 'fa-stamp', cor: '#0ea5e9', val: brl(t.praticado), delta: v.praticado, sub: int(t.atos_qtd) + ' atos' }) +
        kpi({ rot: 'Emolumentos', ico: 'fa-landmark', cor: '#10b981', val: brl(t.emolumentos), delta: v.emolumentos, sub: pct(t.praticado ? t.emolumentos / t.praticado * 100 : 0) + ' do praticado' }) +
        kpi({ rot: 'Fundos e taxas', ico: 'fa-university', cor: '#8b5cf6', val: brl(t.fundos), sub: 'FERC, FADEP, FEMP, FERRFIS' }) +
        kpi({ rot: 'Devoluções', ico: 'fa-undo', cor: '#f97316', val: brl(t.devolvido), sub: 'aos clientes' }) +
        kpi({ rot: 'Repasses a credores', ico: 'fa-exchange-alt', cor: '#ec4899', val: brl(t.repasse) }) +
        kpi({ rot: 'Despesas pagas', ico: 'fa-file-invoice-dollar', cor: '#ef4444', val: brl(t.despesas_caixa + t.contas_pagas), sub: 'caixa + contas a pagar' }) +
        kpi({ rot: 'Resultado', ico: 'fa-balance-scale', cor: t.resultado >= 0 ? '#14b8a6' : '#ef4444', val: brl(t.resultado), delta: v.resultado, sub: 'entradas − saídas' }) +
        '</div>';
      h += '<div class="pa-grid pa-g-64 pa-mt"><div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-chart-bar"></i> Evolução</h3><span class="pa-dica">emolumentos, fundos e recebido</span></div><div class="pa-chart lg"><canvas id="fEvol"></canvas></div></div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-chart-line"></i> Recebido acumulado</h3><span class="pa-dica">período × anterior</span></div><div class="pa-chart lg"><canvas id="fAcum"></canvas></div></div></div>';
      h += '<div class="pa-grid pa-g3 pa-mt"><div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-wallet"></i> Formas de pagamento</h3></div><div class="pa-chart sm"><canvas id="fFormas"></canvas></div></div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-layer-group"></i> Composição dos atos</h3></div><div class="pa-chart sm"><canvas id="fComp"></canvas></div></div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-calendar-week"></i> Recebido por dia da semana</h3></div><div class="pa-chart sm"><canvas id="fSemana"></canvas></div></div></div>';
      // Balanço
      var linhasB = [['Recebido (entradas)', t.recebido, '#10b981'], ['Devoluções', -t.devolvido, '#f97316'], ['Repasses a credores', -t.repasse, '#ec4899'], ['Despesas do caixa', -t.despesas_caixa, '#ef4444'], ['Contas pagas', -t.contas_pagas, '#dc2626']];
      var maxB = Math.max.apply(null, linhasB.map(function (x) { return Math.abs(x[1]); }).concat([Math.abs(t.resultado), 1]));
      h += '<div class="pa-grid pa-g-55 pa-mt"><div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-balance-scale-right"></i> Balanço do período</h3><span class="pa-dica">dinheiro que entrou e saiu</span></div>' +
        linhasB.map(function (x) { return '<div class="pa-comp-l" style="grid-template-columns:150px 1fr 110px;margin-bottom:10px"><span>' + x[0] + '</span><div class="pa-barra" style="height:10px"><i style="width:' + (Math.abs(x[1]) / maxB * 100) + '%;--c1:' + x[2] + ';--c2:' + x[2] + '"></i></div><b style="color:' + (x[1] < 0 ? 'var(--pa-red)' : 'var(--pa-green)') + '">' + brl(x[1]) + '</b></div>'; }).join('') +
        '<div class="pa-comp-l" style="grid-template-columns:150px 1fr 110px;border-top:1px dashed var(--pa-border-2);padding-top:12px"><span><b>Resultado</b></span><div class="pa-barra" style="height:12px"><i style="width:' + (Math.abs(t.resultado) / maxB * 100) + '%;--c1:#6366f1;--c2:#22d3ee"></i></div><b style="font-size:.95rem">' + brl(t.resultado) + '</b></div>' +
        '<div class="pa-chart xs pa-mt"><canvas id="fMeses"></canvas></div></div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-piggy-bank"></i> Depósito prévio</h3><span class="pa-dica">saldo dos clientes ainda não usado em atos</span></div>' +
        '<div class="pa-membro-st" style="margin-top:0">' + mini('Saldo em aberto', brl(dep.saldo_total)) + mini('O.S. com saldo', int(dep.os_com_saldo)) + mini('Parado +30 dias', brl(dep.paradas_valor), dep.paradas_qtd ? 'var(--pa-amber)' : '') + '</div>' +
        (dep.paradas.length ? '<div class="pa-tabela-wrap pa-mt"><table class="pa-tabela"><thead><tr><th>O.S.</th><th>Cliente</th><th class="num">Saldo</th><th class="num">Sem movimento</th></tr></thead><tbody>' +
          dep.paradas.map(function (p) { return '<tr><td><a href="../os/visualizar_os.php?id=' + p.id + '" target="_blank">#' + p.id + '</a></td><td>' + esc(p.cliente) + '</td><td class="num">' + brl(p.saldo) + '</td><td class="num">' + p.dias + ' dias</td></tr>'; }).join('') +
          '</tbody></table></div>' : '<div class="pa-vazio"><i class="fas fa-check-circle"></i>Nenhum depósito parado há mais de 30 dias.</div>') + '</div></div>';
      // Top atos
      var maxA = f.atos_top.length ? f.atos_top[0].v : 1;
      h += '<div class="pa-card pa-mt"><div class="pa-card-h"><h3><i class="fas fa-medal"></i> Atos que mais faturaram</h3></div>' +
        (f.atos_top.length ? '<div class="pa-tabela-wrap"><table class="pa-tabela"><thead><tr><th>Ato</th><th>Descrição</th><th class="num">Qtd.</th><th class="num">Emolumentos</th><th class="num">Total</th><th style="width:22%"></th></tr></thead><tbody>' +
          f.atos_top.map(function (a) { return '<tr><td><b>' + esc(a.ato) + '</b></td><td>' + esc(a.descricao) + '</td><td class="num">' + int(a.qtd) + '</td><td class="num">' + brl(a.e) + '</td><td class="num"><b>' + brl(a.v) + '</b></td><td><div class="pa-minibar"><i style="width:' + (a.v / maxA * 100) + '%"></i></div></td></tr>'; }).join('') +
          '</tbody></table></div>' : '<div class="pa-vazio">Nenhum ato liquidado no período.</div>') + '</div>';
      c.innerHTML = h;

      grafico('fEvol', { data: { labels: f.eixo, datasets: [
        { type: 'bar', label: 'Emolumentos', data: f.serie_emolumentos, backgroundColor: hexA('#10b981', .8), borderRadius: 5, stack: 'p', maxBarThickness: 28 },
        { type: 'bar', label: 'Fundos e taxas', data: f.serie_praticado.map(function (x, i) { return Math.max(0, +(x - f.serie_emolumentos[i]).toFixed(2)); }), backgroundColor: hexA('#8b5cf6', .75), borderRadius: 5, stack: 'p', maxBarThickness: 28 },
        { type: 'line', label: 'Recebido', data: f.serie_recebido, borderColor: '#6366f1', borderWidth: 2.5, tension: .35, pointRadius: 0, pointHoverRadius: 5 }] },
        options: { interaction: { mode: 'index', intersect: false }, plugins: { tooltip: tipBRL, legend: { position: 'top', align: 'end' } }, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { callback: tickBRL } } } } });
      var dias = f.acumulado.map(function (_, i) { return 'dia ' + (i + 1); });
      grafico('fAcum', { type: 'line', data: { labels: dias, datasets: [
        { label: 'Este período', data: f.acumulado, borderColor: '#6366f1', backgroundColor: degrade('#6366f1', .22, 0), fill: true, borderWidth: 2.5, tension: .3, pointRadius: 0 },
        { label: 'Período anterior', data: f.acumulado_anterior, borderColor: '#94a3b8', borderDash: [5, 5], borderWidth: 2, tension: .3, pointRadius: 0 }] },
        options: { interaction: { mode: 'index', intersect: false }, plugins: { tooltip: tipBRL, legend: { position: 'top', align: 'end' } }, scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } }, y: { beginAtZero: true, ticks: { callback: tickBRL } } } } });
      rosca('fFormas', f.formas.map(function (x) { return x.f; }), f.formas.map(function (x) { return x.v; }), PALETA, brl);
      rosca('fComp', f.composicao.map(function (x) { return x.rotulo; }), f.composicao.map(function (x) { return x.v; }), ['#10b981', '#6366f1', '#0ea5e9', '#f59e0b', '#ec4899'], brl);
      grafico('fSemana', { type: 'bar', data: { labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'], datasets: [{ label: 'Recebido', data: f.dia_semana, backgroundColor: f.dia_semana.map(function (x) { return x === Math.max.apply(null, f.dia_semana) ? '#6366f1' : hexA('#6366f1', .35); }), borderRadius: 8 }] },
        options: { plugins: { legend: { display: false }, tooltip: tipBRL }, scales: { y: { beginAtZero: true, ticks: { callback: tickBRL } }, x: { grid: { display: false } } } } });
      var mesesN = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
      grafico('fMeses', { type: 'bar', data: { labels: f.historico_meses.map(function (m) { var p = m.k.split('-'); return mesesN[+p[1] - 1] + '/' + p[0].slice(2); }), datasets: [{ label: 'Recebido no mês', data: f.historico_meses.map(function (m) { return m.v; }), backgroundColor: degrade('#14b8a6', .85, .35), borderRadius: 6 }] },
        options: { plugins: { legend: { display: false }, tooltip: tipBRL, title: { display: true, text: 'Últimos 12 meses', align: 'start', font: { weight: '600' } } }, scales: { y: { beginAtZero: true, ticks: { callback: tickBRL } }, x: { grid: { display: false } } } } });
    }).catch(function (e) { c.innerHTML = erroHTML(e); });
  };

  /* ============================ Equipe ============================ */
  renderizadores.equipe = function (c) {
    dados('equipe').then(function (eq) {
      var ms = eq.membros;
      estado.equipe = eq;
      var h = '<div class="pa-membros">' + ms.map(function (m, i) {
        var tags = [];
        if (i === 0 && m.pontuacao > 0) tags.push('<span class="pa-tag roxo"><i class="fas fa-crown"></i> Destaque</span>');
        tags.push(m.tfa ? '<span class="pa-tag ok"><i class="fas fa-shield-alt"></i> 2FA</span>' : '<span class="pa-tag"><i class="fas fa-unlock"></i> sem 2FA</span>');
        if (m.tarefas_atrasadas) tags.push('<span class="pa-tag aviso"><i class="fas fa-clock"></i> ' + m.tarefas_atrasadas + ' atrasada(s)</span>');
        if (m.exclusoes) tags.push('<span class="pa-tag alerta"><i class="fas fa-trash-alt"></i> ' + m.exclusoes + ' exclusão(ões)</span>');
        if (m.fora_expediente) tags.push('<span class="pa-tag info"><i class="fas fa-moon"></i> ' + m.fora_expediente + ' fora do horário</span>');
        if (!m.dias_ativos) tags.push('<span class="pa-tag"><i class="fas fa-bed"></i> sem atividade</span>');
        return '<div class="pa-membro pa-fade" data-membro="' + esc(m.usuario) + '" style="animation-delay:' + (i * 40) + 'ms"><div class="pa-membro-h">' + avatar(m.nome, m.usuario, 'lg') +
          '<div class="pa-nm"><b>' + esc(m.nome) + '</b><span>' + esc(m.cargo || m.nivel) + '</span></div>' + anel(m.pontuacao) + '</div>' +
          '<div class="pa-membro-st">' + mini('Atos', int(m.atos_qtd)) + mini('Valor atos', brlC(m.atos_valor)) + mini('Recebido', brlC(m.recebido)) +
          mini('O.S.', int(m.os_criadas)) + mini('Tarefas', int(m.tarefas_concluidas)) + mini('Dias ativos', int(m.dias_ativos)) + '</div>' +
          '<div class="pa-tags">' + tags.join('') + '</div></div>';
      }).join('') + '</div>';
      h += '<div class="pa-grid pa-g-55 pa-mt"><div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-chart-bar"></i> Produção por pessoa</h3></div><div class="pa-chart"><canvas id="eProd"></canvas></div></div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-fire"></i> Quando a serventia trabalha</h3><span class="pa-dica">atividade por dia e hora</span></div>' + mapaCalor(eq.mapa_calor) + '</div></div>';
      var cols = [['nome', 'Pessoa'], ['pontuacao', 'Pontuação', 1], ['os_criadas', 'O.S.', 1], ['atos_qtd', 'Atos', 1], ['atos_valor', 'Valor dos atos', 1, brl], ['recebido', 'Recebido', 1, brl], ['pedidos', 'Pedidos', 1],
        ['tarefas_concluidas', 'Tarefas', 1], ['tarefas_atrasadas', 'Atrasadas', 1], ['operacoes', 'Operações', 1], ['exclusoes', 'Exclusões', 1], ['falhas', 'Falhas', 1], ['fora_expediente', 'Fora do horário', 1], ['logins', 'Entradas', 1], ['dias_ativos', 'Dias ativos', 1]];
      h += '<div class="pa-card pa-mt"><div class="pa-card-h"><h3><i class="fas fa-table"></i> Comparativo completo</h3><span class="pa-dica">clique no título da coluna para ordenar</span></div><div class="pa-tabela-wrap"><table class="pa-tabela" id="eTab"><thead><tr>' +
        cols.map(function (x) { return '<th data-ord="' + x[0] + '"' + (x[2] ? ' class="num"' : '') + '>' + x[1] + '</th>'; }).join('') + '</tr></thead><tbody></tbody></table></div></div>';
      c.innerHTML = h;
      var ordem = { k: 'pontuacao', d: -1 };
      function tabela() {
        var l = ms.slice().sort(function (a, b) { var x = a[ordem.k], y = b[ordem.k]; return (typeof x === 'string' ? String(x).localeCompare(y) : (x - y)) * ordem.d; });
        $('#eTab tbody').innerHTML = l.map(function (m) {
          return '<tr data-membro="' + esc(m.usuario) + '" style="cursor:pointer">' + cols.map(function (x) {
            if (x[0] === 'nome') return '<td><div class="pa-pessoa">' + avatar(m.nome, m.usuario, 'sm') + '<div><b>' + esc(m.nome) + '</b><small>' + esc(m.cargo) + '</small></div></div></td>';
            if (x[0] === 'pontuacao') return '<td class="num">' + anel(m.pontuacao) + '</td>';
            return '<td class="num">' + (x[3] ? x[3](m[x[0]]) : int(m[x[0]])) + '</td>';
          }).join('') + '</tr>';
        }).join('');
      }
      tabela();
      $$('#eTab th[data-ord]').forEach(function (th) { th.onclick = function () { ordem.d = ordem.k === th.dataset.ord ? -ordem.d : -1; ordem.k = th.dataset.ord; tabela(); }; });
      var top = ms.slice(0, 10);
      grafico('eProd', { type: 'bar', data: { labels: top.map(function (m) { return m.nome.split(' ')[0]; }), datasets: [
        { label: 'Valor dos atos', data: top.map(function (m) { return m.atos_valor; }), backgroundColor: hexA('#0ea5e9', .8), borderRadius: 6 },
        { label: 'Recebido', data: top.map(function (m) { return m.recebido; }), backgroundColor: hexA('#6366f1', .8), borderRadius: 6 }] },
        options: { indexAxis: 'y', plugins: { tooltip: { callbacks: { label: function (x) { return ' ' + x.dataset.label + ': ' + brl(x.parsed.x); } } }, legend: { position: 'top', align: 'end' } }, scales: { x: { ticks: { callback: tickBRL }, beginAtZero: true }, y: { grid: { display: false } } } } });
    }).catch(function (e) { c.innerHTML = erroHTML(e); });
  };
  function mapaCalor(g) {
    var max = 0; g.forEach(function (d) { d.forEach(function (n) { if (n > max) max = n; }); });
    var dias = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'], base = escuro() ? [129, 140, 248] : [79, 70, 229];
    var h = '<div class="pa-heat"><span></span>';
    for (var hh = 0; hh < 24; hh++) h += '<span class="pa-hh">' + (hh % 3 === 0 ? hh : '') + '</span>';
    g.forEach(function (lin, d) {
      h += '<span class="pa-hd">' + dias[d] + '</span>';
      lin.forEach(function (n, hr) {
        var a = max ? n / max : 0, cor = n ? 'rgba(' + base.join(',') + ',' + (0.12 + a * 0.88).toFixed(2) + ')' : '';
        h += '<span class="pa-hc" style="' + (cor ? 'background:' + cor : '') + '" data-t="' + dias[d] + ' ' + hr + 'h: ' + int(n) + ' registro' + (n === 1 ? '' : 's') + '"></span>';
      });
    });
    h += '</div><div class="pa-heat-leg">menos ' + [0.12, 0.35, 0.6, 0.85, 1].map(function (a) { return '<i style="background:rgba(' + base.join(',') + ',' + a + ')"></i>'; }).join('') + ' mais</div>';
    return h;
  }
  function abrirMembro(login) {
    var go = function (eq) {
      var m = eq.membros.filter(function (x) { return x.usuario === login; })[0]; if (!m) return;
      var r = m.radar, dims = [['producao', 'Produção'], ['atendimento', 'Atendimento'], ['financeiro', 'Financeiro'], ['tarefas', 'Tarefas'], ['pontualidade', 'Pontualidade'], ['qualidade', 'Qualidade']];
      var tit = '<div style="display:flex;gap:14px;align-items:center">' + avatar(m.nome, m.usuario, 'lg') + '<div><h2>' + esc(m.nome) + '</h2><div class="pa-sub2">' + esc(m.cargo) + ' · ' + esc(m.usuario) + ' · último acesso ' + relativo(m.ultimo_acesso) + '</div></div></div>';
      var html = '<div class="pa-grid pa-g-55"><div class="pa-card" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px"><span class="pa-ring lg" style="--v:' + m.pontuacao + ';--c:#6366f1"><span>' + m.pontuacao + '</span></span><b>Pontuação no período</b><small style="color:var(--pa-muted);text-align:center">média ponderada de produção, atendimento, recebimentos, tarefas, pontualidade e qualidade</small></div>' +
        '<div class="pa-card"><div class="pa-chart sm"><canvas id="gavRadar"></canvas></div></div></div>' +
        '<div class="pa-meta pa-mt">' + [['O.S. criadas', int(m.os_criadas)], ['Valor das O.S.', brl(m.os_valor)], ['O.S. canceladas', int(m.os_canceladas)], ['Atos liquidados', int(m.atos_qtd)], ['Valor dos atos', brl(m.atos_valor)],
          ['Recebimentos', int(m.recebimentos) + ' · ' + brl(m.recebido)], ['Devoluções', int(m.devolucoes)], ['Pedidos de certidão', int(m.pedidos)], ['Tarefas concluídas', int(m.tarefas_concluidas) + ' (' + int(m.tarefas_no_prazo) + ' no prazo)'],
          ['Tarefas em aberto', int(m.tarefas_abertas) + ' (' + int(m.tarefas_atrasadas) + ' atrasadas)'], ['Operações auditadas', int(m.operacoes)], ['Exclusões/estornos', int(m.exclusoes)], ['Falhas', int(m.falhas)], ['Acessos negados', int(m.negados)],
          ['Fora do expediente', int(m.fora_expediente)], ['Entradas no sistema', int(m.logins)], ['Dias com atividade', int(m.dias_ativos)], ['Telas abertas', int(m.telas)], ['Verificação em 2 etapas', m.tfa ? 'ativa' : 'não ativada']].map(function (x) {
          return '<div><small>' + x[0] + '</small><b>' + x[1] + '</b></div>'; }).join('') + '</div>' +
        '<div class="pa-bloco"><h4><i class="fas fa-fire"></i> Horários de trabalho</h4><div class="pa-card" id="gavHeatC"><div class="pa-skel" style="height:150px"></div></div></div>' +
        '<div class="pa-bloco"><button class="pa-btn prim" data-ver-trilha="' + esc(m.usuario) + '"><i class="fas fa-fingerprint"></i> Ver a trilha de auditoria de ' + esc(m.nome.split(' ')[0]) + '</button></div>';
      abrirGaveta(tit, html);
      grafico('gavRadar', { type: 'radar', data: { labels: dims.map(function (x) { return x[1]; }), datasets: [{ label: m.nome.split(' ')[0], data: dims.map(function (x) { return r[x[0]] == null ? 0 : r[x[0]]; }), backgroundColor: hexA('#6366f1', .22), borderColor: '#6366f1', pointBackgroundColor: '#6366f1', borderWidth: 2 }] },
        options: { plugins: { legend: { display: false } }, scales: { r: { min: 0, max: 100, ticks: { display: false, stepSize: 25 }, grid: { color: cssVar('--pa-chart-grid') }, angleLines: { color: cssVar('--pa-chart-grid') }, pointLabels: { font: { size: 11, weight: '600' } } } } } });
      api('mapa_usuario', { usuario: login }).then(function (g) { var el = $('#gavHeatC'); if (el) el.innerHTML = mapaCalor(g); });
    };
    if (estado.equipe) go(estado.equipe); else dados('equipe').then(function (eq) { estado.equipe = eq; go(eq); });
  }

  /* ============================ Serventia ============================ */
  renderizadores.serventia = function (c) {
    Promise.all([dados('serventia'), dados('faturamento')]).then(function (r) {
      var s = r[0], f = r[1], o = s.os, t = s.tarefas, p = t.pedidos;
      var h = '<div class="pa-kpis k4">' +
        kpi({ rot: 'O.S. criadas', ico: 'fa-file-signature', cor: '#8b5cf6', val: int(o.criadas), sub: 'no período' }) +
        kpi({ rot: 'O.S. com pagamento', ico: 'fa-money-check-alt', cor: '#6366f1', val: pct(o.taxa_pagamento), sub: int(o.com_pagamento) + ' de ' + int(o.criadas) }) +
        kpi({ rot: 'O.S. com atos praticados', ico: 'fa-stamp', cor: '#0ea5e9', val: pct(o.taxa_liquidacao), sub: int(o.liquidadas) + ' de ' + int(o.criadas) }) +
        kpi({ rot: 'Tempo até praticar', ico: 'fa-stopwatch', cor: '#f59e0b', val: o.horas_ate_liquidar == null ? '—' : (o.horas_ate_liquidar >= 48 ? nf1.format(o.horas_ate_liquidar / 24) + ' dias' : nf1.format(o.horas_ate_liquidar) + ' h'), sub: 'da O.S. ao 1º ato' }) +
        kpi({ rot: 'Tarefas concluídas', ico: 'fa-check-circle', cor: '#10b981', val: int(t.concluidas), sub: (t.concluidas ? pct(t.concluidas_no_prazo / t.concluidas * 100) : '—') + ' no prazo' }) +
        kpi({ rot: 'Tarefas em aberto', ico: 'fa-tasks', cor: t.atrasadas ? '#ef4444' : '#14b8a6', val: int(t.abertas), sub: int(t.atrasadas) + ' atrasadas' }) +
        kpi({ rot: 'Pedidos de certidão', ico: 'fa-certificate', cor: '#ec4899', val: int(p.criados), sub: int(p.entregues) + ' emitidos/entregues · ' + int(p.abertos_total || 0) + ' na fila' }) +
        kpi({ rot: 'Tempo de tarefa', ico: 'fa-hourglass-half', cor: '#64748b', val: t.tempo_medio_horas == null ? '—' : (t.tempo_medio_horas >= 48 ? nf1.format(t.tempo_medio_horas / 24) + ' dias' : nf1.format(t.tempo_medio_horas) + ' h'), sub: 'criação → conclusão' }) +
        '</div>';
      h += '<div class="pa-grid pa-g-64 pa-mt"><div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-chart-line"></i> O.S. abertas</h3></div><div class="pa-chart"><canvas id="sOs"></canvas></div></div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-layer-group"></i> Tabelas de atos (O.S. do período)</h3></div><div class="pa-chart"><canvas id="sGrupos"></canvas></div></div></div>';
      h += '<div class="pa-grid pa-g-55 pa-mt"><div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-tags"></i> Tarefas por categoria</h3></div><div class="pa-chart"><canvas id="sCat"></canvas></div></div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-th"></i> Uso dos módulos</h3><span class="pa-dica">operações + telas abertas</span></div><div class="pa-chart"><canvas id="sUso"></canvas></div></div></div>';
      c.innerHTML = h;
      grafico('sOs', { type: 'line', data: { labels: f.eixo, datasets: [{ label: 'O.S. abertas', data: f.serie_os, borderColor: '#8b5cf6', backgroundColor: degrade('#8b5cf6', .3, 0), fill: true, tension: .35, borderWidth: 2.5, pointRadius: 2 }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } } });
      if (s.grupos_atos.length) rosca('sGrupos', s.grupos_atos.map(function (g) { return g.grupo; }), s.grupos_atos.map(function (g) { return g.v; }), PALETA, brl);
      var cats = Object.keys(t.por_categoria || {});
      grafico('sCat', { type: 'bar', data: { labels: cats, datasets: [{ label: 'Tarefas', data: cats.map(function (k) { return t.por_categoria[k]; }), backgroundColor: cats.map(function (_, i) { return PALETA[i % PALETA.length]; }), borderRadius: 6 }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } } } });
      grafico('sUso', { type: 'bar', data: { labels: s.uso_modulos.map(function (u) { return u.rotulo; }), datasets: [{ label: 'Registros', data: s.uso_modulos.map(function (u) { return u.n; }), backgroundColor: degrade('#0ea5e9', .9, .45), borderRadius: 6 }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } } } });
    }).catch(function (e) { c.innerHTML = erroHTML(e); });
  };

  /* ============================ Auditoria ============================ */
  var filtrosBase = null;
  function obterFiltros() { return filtrosBase ? Promise.resolve(filtrosBase) : api('filtros').then(function (f) { filtrosBase = f; return f; }); }
  renderizadores.auditoria = function (c) {
    obterFiltros().then(function (fb) {
      var F = estado.filtros;
      c.innerHTML = '<div class="pa-card"><div class="pa-subabas">' +
        [['operacoes', 'fa-stream', 'Operações'], ['dados', 'fa-database', 'Alterações de dados'], ['navegacao', 'fa-mouse-pointer', 'Telas abertas'], ['trilhas', 'fa-archive', 'Trilhas dos módulos']].map(function (x) {
          return '<button data-sub="' + x[0] + '" class="' + (F.sub === x[0] ? 'ativo' : '') + '"><i class="fas ' + x[1] + '"></i> ' + x[2] + '</button>';
        }).join('') + '</div><div id="audFiltros"></div><div id="audLista" class="pa-mt"></div></div>';
      $$('.pa-subabas button[data-sub]', c).forEach(function (b) { b.onclick = function () { estado.filtros = { sub: b.dataset.sub, pagina: 1, sem_navegacao: 1 }; renderizadores.auditoria(c); }; });
      var usuOpts = '<option value="">Todos os usuários</option>' + fb.usuarios.map(function (u) { return '<option value="' + esc(u.usuario) + '"' + (F.usuario === u.usuario ? ' selected' : '') + '>' + esc(u.nome) + '</option>'; }).join('');
      var modOpts = '<option value="">Todos os módulos</option>' + fb.modulos.map(function (m) { return '<option value="' + esc(m.chave) + '"' + (F.modulo === m.chave ? ' selected' : '') + '>' + esc(m.rotulo) + '</option>'; }).join('');
      var fh = '<div class="pa-filtros"><div class="pa-busca"><i class="fas fa-search"></i><input class="pa-input" id="audBusca" placeholder="' +
        (F.sub === 'dados' ? 'Buscar em valores, tabela ou registro…' : 'Buscar ação, tela, alvo, IP, parâmetro…') + '" value="' + esc(F.busca || '') + '"></div>' +
        '<select class="pa-select" id="audUsuario">' + usuOpts + '</select>';
      if (F.sub === 'operacoes' || F.sub === 'navegacao') fh += '<select class="pa-select" id="audModulo">' + modOpts + '</select>';
      if (F.sub === 'operacoes') fh += '<select class="pa-select" id="audResultado"><option value="">Qualquer resultado</option><option value="sucesso"' + (F.resultado === 'sucesso' ? ' selected' : '') + '>Sucesso</option><option value="falha"' + (F.resultado === 'falha' ? ' selected' : '') + '>Falhou</option><option value="negado"' + (F.resultado === 'negado' ? ' selected' : '') + '>Negado</option></select>' +
        '<select class="pa-select" id="audSev"><option value="">Qualquer gravidade</option><option value="critico"' + (F.severidade === 'critico' ? ' selected' : '') + '>Crítica</option><option value="aviso"' + (F.severidade === 'aviso' ? ' selected' : '') + '>Atenção</option><option value="info"' + (F.severidade === 'info' ? ' selected' : '') + '>Informativa</option></select>' +
        '<label class="pa-toggle-chip"><input type="checkbox" id="audFora"' + (F.fora ? ' checked' : '') + '> Fora do expediente</label>';
      if (F.sub === 'dados') fh += '<select class="pa-select" id="audOp"><option value="">Inclusões, alterações e exclusões</option><option value="I"' + (F.operacao === 'I' ? ' selected' : '') + '>Só inclusões</option><option value="U"' + (F.operacao === 'U' ? ' selected' : '') + '>Só alterações</option><option value="D"' + (F.operacao === 'D' ? ' selected' : '') + '>Só exclusões</option></select><select class="pa-select" id="audTabela"><option value="">Todas as tabelas</option></select>';
      if (F.sub === 'trilhas') fh += '<select class="pa-select" id="audModT"><option value="">Todos os módulos</option>' + ['os', 'caixa', 'tarefas', 'pedidos_certidao', 'nota_devolutiva', 'arquivamento', 'acesso', 'sistema'].map(function (k) {
        var m = fb.modulos.filter(function (x) { return x.chave === k; })[0]; return '<option value="' + k + '"' + (F.modulo_t === k ? ' selected' : '') + '>' + esc(m ? m.rotulo : k) + '</option>'; }).join('') + '</select>';
      if (F.sub !== 'navegacao') fh += '<button class="pa-btn" id="audExport"><i class="fas fa-file-csv"></i> Exportar</button>';
      fh += '</div>';
      if (F.sub === 'operacoes') fh += '<div class="pa-catchips" id="audCats"></div>';
      $('#audFiltros').innerHTML = fh;
      var deb;
      var aplicar = function () { F.pagina = 1; carregarLista(); };
      $('#audBusca').oninput = function () { clearTimeout(deb); var v = this.value; deb = setTimeout(function () { F.busca = v; aplicar(); }, 350); };
      $('#audUsuario').onchange = function () { F.usuario = this.value; aplicar(); };
      if ($('#audModulo')) $('#audModulo').onchange = function () { F.modulo = this.value; aplicar(); };
      if ($('#audResultado')) $('#audResultado').onchange = function () { F.resultado = this.value; aplicar(); };
      if ($('#audSev')) $('#audSev').onchange = function () { F.severidade = this.value; aplicar(); };
      if ($('#audFora')) $('#audFora').onchange = function () { F.fora = this.checked ? 1 : ''; aplicar(); };
      if ($('#audOp')) $('#audOp').onchange = function () { F.operacao = this.value; aplicar(); };
      if ($('#audTabela')) $('#audTabela').onchange = function () { F.tabela = this.value; aplicar(); };
      if ($('#audModT')) $('#audModT').onchange = function () { F.modulo_t = this.value; aplicar(); };
      if ($('#audExport')) $('#audExport').onclick = function () {
        var p = Object.assign({ acao: 'exportar', tipo: F.sub === 'operacoes' ? 'auditoria' : F.sub, ini: estado.ini, fim: estado.fim }, F);
        delete p.sub; delete p.pagina;
        location.href = 'api.php?' + Object.keys(p).filter(function (k) { return p[k] !== '' && p[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(p[k]); }).join('&');
        toast('Gerando o arquivo CSV…');
      };
      carregarLista();
    }).catch(function (e) { c.innerHTML = erroHTML(e); });
  };
  function carregarLista() {
    var F = estado.filtros, el = $('#audLista'); if (!el) return;
    el.classList.add('pa-carregando');
    var q = {}; Object.keys(F).forEach(function (k) { if (k !== 'sub') q[k] = F[k]; });
    var acao = { operacoes: 'auditoria', dados: 'dados', trilhas: 'trilhas', navegacao: 'auditoria' }[F.sub];
    if (F.sub === 'navegacao') { q.categoria = 'navegacao'; }
    var prom = F.sub === 'navegacao' ? listarNavegacao(q) : api(acao, q);
    prom.then(function (r) {
      el.classList.remove('pa-carregando');
      if (F.sub === 'operacoes') {
        var cats = (filtrosBase.categorias || []).filter(function (x) { return x.chave !== 'navegacao'; }), sel = (F.categoria || '').split(',').filter(Boolean);
        $('#audCats').innerHTML = cats.map(function (x) {
          var n = r.facetas[x.chave] || 0; if (!n && sel.indexOf(x.chave) < 0) return '';
          return '<button class="pa-catchip' + (sel.indexOf(x.chave) >= 0 ? ' ativo' : '') + '" data-cat="' + x.chave + '" style="--c:' + x.cor + '"><i class="fas ' + x.icone + '"></i> ' + esc(x.rotulo) + ' <em>' + int(n) + '</em></button>';
        }).join('') + (sel.length ? '<button class="pa-catchip" data-cat="" style="--c:var(--pa-muted)"><i class="fas fa-times"></i> limpar</button>' : '');
        $$('#audCats [data-cat]').forEach(function (b) { b.onclick = function () {
          var k = b.dataset.cat; if (!k) sel = []; else if (sel.indexOf(k) >= 0) sel.splice(sel.indexOf(k), 1); else sel.push(k);
          F.categoria = sel.join(','); F.pagina = 1; carregarLista(); }; });
        el.innerHTML = r.linhas.length ? linhaTempo(r.linhas) + paginacao(r) : vazioAud();
      } else if (F.sub === 'dados') {
        var selT = $('#audTabela'); if (selT && selT.options.length <= 1) selT.innerHTML = '<option value="">Todas as tabelas</option>' + r.tabelas.map(function (t) { return '<option value="' + esc(t.tabela) + '"' + (F.tabela === t.tabela ? ' selected' : '') + '>' + esc(t.tabela) + ' (' + int(t.n) + ')</option>'; }).join('');
        el.innerHTML = r.linhas.length ? r.linhas.map(function (d) { return diffHTML(d, true); }).join('') + paginacao(r) : vazioAud('Nenhuma alteração de registro no período. A trilha de dados registra inclusões, alterações e exclusões feitas no banco.');
      } else if (F.sub === 'navegacao') {
        el.innerHTML = r;
      } else {
        el.innerHTML = r.linhas.length ? '<div class="pa-dica" style="margin-bottom:8px;color:var(--pa-muted);font-size:.8rem"><i class="fas fa-info-circle"></i> Registros que cada módulo já guardava por conta própria (inclusive antes da auditoria central). Fontes: ' + Object.keys(r.fontes).map(function (k) { return esc(k) + ' (' + int(r.fontes[k]) + ')'; }).join(', ') + '.</div>' +
          '<div class="pa-tabela-wrap"><table class="pa-tabela"><thead><tr><th>Quando</th><th>Quem</th><th>Módulo</th><th>Ação</th><th>Detalhe</th><th>Referência</th></tr></thead><tbody>' +
          r.linhas.map(function (l) { return '<tr><td style="white-space:nowrap">' + dataHoraBR(l.quando) + '</td><td><div class="pa-pessoa" style="min-width:150px">' + avatar(l.nome, l.usuario, 'sm') + '<b>' + esc(l.nome || '—') + '</b></div></td><td><span class="pa-tag">' + esc(l.modulo_rotulo) + '</span></td><td><b style="color:var(--pa-text)">' + esc(l.acao) + '</b></td><td style="max-width:380px">' + esc(l.detalhe) + '</td><td style="white-space:nowrap">' + esc(l.referencia) + '</td></tr>'; }).join('') +
          '</tbody></table></div>' + paginacao(r) : vazioAud('Nenhum registro nas trilhas dos módulos neste período.');
      }
      $$('[data-pg]', el).forEach(function (b) { b.onclick = function () { F.pagina = +b.dataset.pg; carregarLista(); var top = $('#audFiltros'); if (top) top.scrollIntoView({ behavior: 'smooth', block: 'start' }); }; });
    }).catch(function (e) { el.classList.remove('pa-carregando'); el.innerHTML = erroHTML(e); });
  }
  function listarNavegacao(q) {
    return api('navegacao', q).then(function (r) {
      if (!r.linhas.length) return vazioAud('Nenhuma tela aberta registrada com estes filtros.');
      return '<div class="pa-tabela-wrap"><table class="pa-tabela"><thead><tr><th>Quando</th><th>Quem</th><th>Módulo</th><th>Tela</th><th>IP</th><th>Dispositivo</th></tr></thead><tbody>' +
        r.linhas.map(function (l) {
          return '<tr><td style="white-space:nowrap">' + dataHoraBR(l.criado_em) + (l.fora_expediente ? ' <i class="fas fa-moon" title="fora do expediente" style="color:var(--pa-blue)"></i>' : '') + '</td><td><div class="pa-pessoa" style="min-width:150px">' + avatar(l.nome, l.usuario, 'sm') + '<b>' + esc(l.nome || l.usuario) + '</b></div></td>' +
            '<td><span class="pa-tag">' + esc(l.modulo_rotulo) + '</span></td><td><code style="font-size:.78rem">' + esc(l.pagina) + (l.consulta ? '?' + esc(l.consulta) : '') + '</code></td><td>' + esc(l.ip) + '</td><td>' + esc(l.dispositivo) + '</td></tr>';
        }).join('') + '</tbody></table></div>' + paginacao(r);
    });
  }
  function vazioAud(msg) { return '<div class="pa-vazio"><i class="fas fa-search"></i>' + esc(msg || 'Nenhuma operação encontrada com estes filtros.') + '</div>'; }
  function paginacao(r) {
    if (r.paginas <= 1) return '<div class="pa-pag"><span>' + int(r.total) + ' registro' + (r.total === 1 ? '' : 's') + '</span></div>';
    return '<div class="pa-pag"><span>' + int(r.total) + ' registros · página ' + r.pagina + ' de ' + r.paginas + '</span><div class="pa-pag-b">' +
      '<button class="pa-btn sm" data-pg="1"' + (r.pagina <= 1 ? ' disabled' : '') + '><i class="fas fa-angle-double-left"></i></button>' +
      '<button class="pa-btn sm" data-pg="' + (r.pagina - 1) + '"' + (r.pagina <= 1 ? ' disabled' : '') + '><i class="fas fa-angle-left"></i> Anterior</button>' +
      '<button class="pa-btn sm" data-pg="' + (r.pagina + 1) + '"' + (r.pagina >= r.paginas ? ' disabled' : '') + '>Próxima <i class="fas fa-angle-right"></i></button></div></div>';
  }
  var corSev = { critico: '#ef4444', aviso: '#f59e0b', info: 'transparent' };
  function linhaTempo(linhas) {
    var h = '', dia = '';
    var hoje = PA.hoje, ontem = ymd(new Date(new Date(PA.hoje + 'T12:00:00').getTime() - 864e5));
    linhas.forEach(function (e) {
      var d = e.criado_em.slice(0, 10);
      if (d !== dia) { dia = d; var dt = new Date(d + 'T12:00:00'); h += '<div class="pa-tl-dia">' + (d === hoje ? 'Hoje' : d === ontem ? 'Ontem' : dt.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })) + '</div>'; }
      h += '<div class="pa-ev" data-ev="' + e.id + '"><span class="pa-ev-ico" style="--c:' + e.cor + '"><i class="fas ' + e.icone + '"></i>' + (e.severidade !== 'info' ? '<span class="pa-sev" style="background:' + corSev[e.severidade] + '"></span>' : '') + '</span>' +
        '<div class="pa-ev-c"><div class="pa-ev-t">' + esc(e.acao) + '</div><div class="pa-ev-m">' + avatar(e.nome, e.usuario, 'xs') +
        '<span>' + esc(e.nome || e.usuario || 'não identificado') + '</span><span class="pa-sep">•</span><span class="pa-tag" style="padding:1px 7px">' + esc(e.modulo_rotulo) + '</span>' +
        (e.alvo ? '<span class="pa-sep">•</span><span>' + esc(e.alvo) + '</span>' : '') + (e.dados_linhas ? '<span class="pa-sep">•</span><span><i class="fas fa-database"></i> ' + int(e.dados_linhas) + ' registro' + (e.dados_linhas > 1 ? 's' : '') + '</span>' : '') +
        (e.fora_expediente ? '<span class="pa-tag info" style="padding:1px 7px"><i class="fas fa-moon"></i> fora do horário</span>' : '') + (e.mensagem && e.resultado !== 'sucesso' ? '<span style="color:var(--pa-red)">' + esc(e.mensagem) + '</span>' : '') + '</div></div>' +
        '<div class="pa-ev-r"><time>' + horaBR(e.criado_em) + '</time><span>' + (e.resultado !== 'sucesso' ? '<span class="pa-res ' + e.resultado + '">' + rotuloRes(e.resultado) + '</span> ' : '') + (e.selado ? '<i class="fas fa-lock pa-lock" title="Selado na cadeia de integridade"></i>' : '') + '</span></div></div>';
    });
    return h;
  }
  function fmtValor(v) {
    if (v === null || v === undefined) return '<span class="igual">—</span>';
    if (typeof v === 'object') return '<code>' + esc(JSON.stringify(v)) + '</code>';
    return esc(String(v));
  }
  function diffHTML(d, comCab) {
    var campos = d.campos.filter(function (c) { return d.operacao !== 'U' || c.mudou; });
    var h = '<div class="pa-diff"><div class="pa-diff-h"><span class="pa-op ' + d.operacao + '">' + d.operacao_rotulo + '</span><b>' + esc(d.tabela) + '</b><span style="color:var(--pa-muted)">#' + esc(d.registro_id) + '</span>' +
      '<span class="pa-tag">' + esc(d.modulo_rotulo) + '</span>' + (comCab ? '<span style="color:var(--pa-muted)">' + esc(d.nome || '') + ' · ' + dataHoraBR(d.criado_em) + '</span>' : '') +
      '<span style="margin-left:auto;display:flex;gap:6px;align-items:center">' + (d.selado ? '<i class="fas fa-lock pa-lock" title="Selado"></i>' : '') + '<button class="pa-btn sm" data-hist-t="' + esc(d.tabela) + '" data-hist-r="' + esc(d.registro_id) + '"><i class="fas fa-history"></i> Histórico</button></span></div>' +
      '<table><thead><tr><th>Campo</th>' + (d.operacao === 'I' ? '<th>Valor gravado</th>' : d.operacao === 'D' ? '<th>Valor apagado</th>' : '<th>Antes</th><th>Depois</th>') + '</tr></thead><tbody>' +
      campos.slice(0, 40).map(function (c) {
        if (d.operacao === 'I') return '<tr><td>' + esc(c.campo) + '</td><td class="depois">' + fmtValor(c.depois) + '</td></tr>';
        if (d.operacao === 'D') return '<tr><td>' + esc(c.campo) + '</td><td class="antes">' + fmtValor(c.antes) + '</td></tr>';
        return '<tr><td>' + esc(c.campo) + '</td><td class="antes">' + fmtValor(c.antes) + '</td><td class="depois">' + fmtValor(c.depois) + '</td></tr>';
      }).join('') + (campos.length > 40 ? '<tr><td colspan="3" style="color:var(--pa-muted)">… mais ' + (campos.length - 40) + ' campos</td></tr>' : '') + '</tbody></table></div>';
    return h;
  }
  function abrirEvento(id) {
    abrirGaveta('<h2>Carregando…</h2>', '<div class="pa-skel" style="height:120px"></div><div class="pa-skel pa-mt" style="height:260px"></div>');
    api('evento', { id: id }).then(function (e) {
      var tit = '<div style="display:flex;gap:12px;align-items:flex-start"><span class="pa-ev-ico" style="--c:' + e.cor + ';flex:0 0 auto"><i class="fas ' + e.icone + '"></i></span><div><h2>' + esc(e.acao) + '</h2><div class="pa-sub2">' + esc(e.categoria_rotulo) + ' · ' + esc(e.modulo_rotulo) + ' · ' + dataHoraBR(e.criado_em) + ' <span class="pa-res ' + e.resultado + '">' + rotuloRes(e.resultado) + '</span></div></div></div>';
      var html = '<div class="pa-meta">' + [['Quem', (e.nome || '—') + (e.usuario ? ' (' + e.usuario + ')' : '')], ['Quando', dataHoraBR(e.criado_em) + ':' + e.criado_em.slice(17, 19)], ['IP', e.ip || '—'],
        ['Dispositivo', e.dispositivo.tipo + ' · ' + e.dispositivo.sistema + ' · ' + e.dispositivo.navegador], ['Tela / endpoint', e.pagina], ['Método', e.metodo + (e.status_http ? ' · HTTP ' + e.status_http : '')],
        ['Duração', e.duracao_ms != null ? int(e.duracao_ms) + ' ms' : '—'], ['Gravidade', { critico: 'Crítica', aviso: 'Atenção', info: 'Informativa' }[e.severidade] || e.severidade], ['Alvo', e.alvo || '—']].map(function (x) {
        return '<div><small>' + x[0] + '</small><b>' + esc(x[1]) + '</b></div>'; }).join('') + '</div>';
      if (e.mensagem) html += '<div class="pa-bloco"><h4><i class="fas fa-comment-alt"></i> Mensagem</h4><div class="pa-aviso-caixa" style="background:' + (e.resultado === 'sucesso' ? 'var(--pa-blue-soft)' : 'var(--pa-red-soft)') + '"><i class="fas fa-info-circle"></i>' + esc(e.mensagem) + '</div></div>';
      if (e.fora_expediente) html += '<div class="pa-aviso-caixa" style="margin-top:14px"><i class="fas fa-moon"></i>Operação feita fora do horário de expediente configurado.</div>';
      if (e.dados && e.dados.length) html += '<div class="pa-bloco"><h4><i class="fas fa-database"></i> Registros alterados no banco (' + e.dados.length + ')</h4>' + e.dados.map(function (d) { return diffHTML(d, false); }).join('') + '</div>';
      else html += '<div class="pa-bloco"><h4><i class="fas fa-database"></i> Registros alterados no banco</h4><div class="pa-kv"><div><span>Nenhum</span><code>Esta operação não alterou registros no banco de dados.</code></div></div></div>';
      if (e.parametros && Object.keys(e.parametros).length) html += '<div class="pa-bloco"><h4><i class="fas fa-list"></i> Dados enviados pela tela</h4><div class="pa-kv">' + Object.keys(e.parametros).map(function (k) {
        var v = e.parametros[k]; return '<div><span>' + esc(k) + '</span><code>' + esc(typeof v === 'object' ? JSON.stringify(v, null, 1) : v) + '</code></div>'; }).join('') + '</div><div class="pa-campo"><div class="pa-ajuda">Senhas, chaves e códigos aparecem mascarados (••••••).</div></div></div>';
      if (e.contexto && e.contexto.length) html += '<div class="pa-bloco"><h4><i class="fas fa-route"></i> O que a mesma sessão fez perto deste momento</h4>' + e.contexto.map(function (x) {
        return '<div class="pa-ev" data-ev="' + x.id + '" style="padding:6px 8px"><span class="pa-dot" style="background:' + ((filtrosBase && (filtrosBase.categorias.filter(function (c) { return c.chave === x.categoria; })[0] || {}).cor) || '#94a3b8') + '"></span><div class="pa-ev-c"><div class="pa-ev-t" style="font-size:.84rem">' + esc(x.acao) + '</div><div class="pa-ev-m">' + esc(x.modulo_rotulo) + '</div></div><div class="pa-ev-r"><time>' + dataHoraBR(x.criado_em) + '</time></div></div>'; }).join('') + '</div>';
      html += '<div class="pa-bloco"><h4><i class="fas fa-link"></i> Selo de integridade</h4><div class="pa-kv"><div><span>Situação</span><code>' + (e.selado ? 'Selado na posição ' + e.selo.seq + ' da cadeia' : 'Aguardando selagem (em poucos segundos)') + '</code></div>' +
        (e.selado ? '<div><span>Hash</span><code class="pa-hash">' + esc(e.selo.hash) + '</code></div><div><span>Hash anterior</span><code class="pa-hash">' + esc(e.selo.anterior || '(início da cadeia)') + '</code></div>' : '') + '<div><span>Requisição</span><code class="pa-hash">' + esc(e.req_id) + '</code></div></div></div>';
      abrirGaveta(tit, html);
    }).catch(function (er) { abrirGaveta('<h2>Erro</h2>', erroHTML(er)); });
  }
  function abrirHistorico(tab, reg) {
    abrirGaveta('<h2>Histórico do registro</h2>', '<div class="pa-skel" style="height:240px"></div>');
    api('historico_registro', { tabela: tab, registro: reg }).then(function (l) {
      var tit = '<div><h2><i class="fas fa-history" style="color:var(--pa-primary)"></i> ' + esc(tab) + ' #' + esc(reg) + '</h2><div class="pa-sub2">' + l.length + ' mudança' + (l.length === 1 ? '' : 's') + ' registradas, da mais recente para a mais antiga</div></div>';
      abrirGaveta(tit, l.length ? l.map(function (d) {
        return '<div class="pa-tl-dia">' + dataHoraBR(d.criado_em) + ' · ' + esc(d.nome) + (d.operacao_id ? ' · <a href="#" data-ev-link="' + d.operacao_id + '">' + esc(d.operacao_acao || 'ver operação') + '</a>' : '') + '</div>' + diffHTML(d, false);
      }).join('') : '<div class="pa-vazio">Sem histórico.</div>');
    }).catch(function (er) { abrirGaveta('<h2>Erro</h2>', erroHTML(er)); });
  }

  /* ============================ Acessos ============================ */
  renderizadores.acessos = function (c) {
    dados('acessos').then(function (a) {
      var t = a.totais;
      var h = '<div class="pa-kpis">' +
        kpi({ rot: 'Entradas', ico: 'fa-sign-in-alt', cor: '#6366f1', val: int(t.logins), sub: int(t.usuarios) + ' usuários' }) +
        kpi({ rot: 'Tentativas recusadas', ico: 'fa-user-lock', cor: t.falhas ? '#ef4444' : '#10b981', val: int(t.falhas), sub: 'senha ou 2FA inválidos' }) +
        kpi({ rot: 'Acessos negados', ico: 'fa-hand-paper', cor: t.negados ? '#f59e0b' : '#10b981', val: int(t.negados), sub: 'sem permissão' }) +
        kpi({ rot: 'Fora do expediente', ico: 'fa-moon', cor: '#0ea5e9', val: int(t.fora_expediente), sub: a.expediente.inicio + '–' + a.expediente.fim }) +
        kpi({ rot: 'IPs diferentes', ico: 'fa-network-wired', cor: '#8b5cf6', val: int(t.ips), sub: int(a.ips_novos.length) + ' novos no período' }) +
        '</div>';
      h += '<div class="pa-grid pa-g-64 pa-mt"><div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-chart-bar"></i> Entradas no sistema</h3></div><div class="pa-chart"><canvas id="aDia"></canvas></div></div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-clock"></i> Horário das entradas</h3><span class="pa-dica">faixa clara = expediente</span></div><div class="pa-chart"><canvas id="aHora"></canvas></div></div></div>';
      h += '<div class="pa-card pa-mt"><div class="pa-card-h"><h3><i class="fas fa-users-cog"></i> Contas de usuário</h3></div><div class="pa-tabela-wrap"><table class="pa-tabela"><thead><tr><th>Usuário</th><th>Nível</th><th>Situação</th><th>2FA</th><th class="num">Entradas</th><th class="num">Recusadas</th><th class="num">Negados</th><th class="num">IPs</th><th>Último acesso</th></tr></thead><tbody>' +
        a.por_usuario.map(function (u) {
          return '<tr><td><div class="pa-pessoa">' + avatar(u.nome, u.usuario, 'sm') + '<div><b>' + esc(u.nome) + '</b><small>' + esc(u.usuario) + ' · ' + esc(u.cargo) + '</small></div></div></td>' +
            '<td>' + (u.nivel === 'administrador' ? '<span class="pa-tag roxo"><i class="fas fa-crown"></i> administrador</span>' : '<span class="pa-tag">' + esc(u.nivel) + '</span>') + '</td>' +
            '<td>' + (String(u.status).toLowerCase() === 'ativo' ? '<span class="pa-tag ok">ativo</span>' : '<span class="pa-tag">' + esc(u.status) + '</span>') + '</td>' +
            '<td>' + (u.tfa ? '<span class="pa-tag ok"><i class="fas fa-shield-alt"></i> ativo</span>' : '<span class="pa-tag ' + (u.nivel === 'administrador' ? 'alerta' : 'aviso') + '"><i class="fas fa-unlock"></i> não</span>') + '</td>' +
            '<td class="num">' + int(u.logins) + '</td><td class="num"' + (u.falhas ? ' style="color:var(--pa-red);font-weight:700"' : '') + '>' + int(u.falhas) + '</td><td class="num"' + (u.negados ? ' style="color:var(--pa-amber);font-weight:700"' : '') + '>' + int(u.negados) + '</td><td class="num">' + int(u.ips) + '</td><td>' + (u.ultimo ? dataHoraBR(u.ultimo) + ' <small style="color:var(--pa-muted)">(' + relativo(u.ultimo) + ')</small>' : '—') + '</td></tr>';
        }).join('') + '</tbody></table></div></div>';
      h += '<div class="pa-grid pa-g3 pa-mt"><div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-user-lock"></i> Tentativas recusadas</h3></div>' +
        (a.tentativas.length ? a.tentativas.map(function (x) { return '<div class="pa-ev" style="padding:7px 4px;cursor:default"><span class="pa-ev-ico" style="--c:#ef4444;width:32px;height:32px"><i class="fas fa-times"></i></span><div class="pa-ev-c"><div class="pa-ev-t" style="font-size:.84rem">' + esc(x.usuario || '?') + '</div><div class="pa-ev-m">IP ' + esc(x.ip) + ' · ' + esc(x.mensagem || x.acao) + '</div></div><div class="pa-ev-r"><time>' + dataHoraBR(x.criado_em) + '</time></div></div>'; }).join('') : '<div class="pa-vazio"><i class="fas fa-check-circle"></i>Nenhuma tentativa recusada.</div>') + '</div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-map-marker-alt"></i> IPs</h3></div><div class="pa-tabela-wrap"><table class="pa-tabela"><thead><tr><th>IP</th><th class="num">Entradas</th><th class="num">Usuários</th><th>Último</th></tr></thead><tbody>' +
        a.ips.map(function (x) { return '<tr><td><b>' + esc(x.ip) + '</b></td><td class="num">' + int(x.n) + '</td><td class="num">' + int(x.u) + '</td><td>' + relativo(x.ult) + '</td></tr>'; }).join('') + '</tbody></table></div>' +
        (a.ips_novos.length ? '<div class="pa-bloco"><h4><i class="fas fa-plus-circle"></i> Primeira vez neste período</h4>' + a.ips_novos.slice(0, 6).map(function (x) { return '<div style="font-size:.8rem;padding:4px 0;color:var(--pa-text-2)"><b>' + esc(x.nome) + '</b> · ' + esc(x.ip) + ' · ' + dataHoraBR(x.primeiro) + '</div>'; }).join('') + '</div>' : '') + '</div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-laptop"></i> Dispositivos</h3></div><div class="pa-chart sm"><canvas id="aDisp"></canvas></div><div class="pa-tags" style="justify-content:center">' +
        Object.keys(a.dispositivos.navegador).map(function (k) { return '<span class="pa-tag">' + esc(k) + ' · ' + int(a.dispositivos.navegador[k]) + '</span>'; }).join('') + '</div></div></div>';
      c.innerHTML = h;
      grafico('aDia', { data: { labels: a.eixo, datasets: [
        { type: 'bar', label: 'Entradas', data: a.serie_logins, backgroundColor: hexA('#6366f1', .75), borderRadius: 6, maxBarThickness: 24, order: 2 },
        { type: 'line', label: 'Usuários distintos', data: a.serie_usuarios, borderColor: '#10b981', borderWidth: 2, tension: .35, pointRadius: 0, order: 1 },
        { type: 'line', label: 'Recusadas', data: a.serie_falhas, borderColor: '#ef4444', backgroundColor: '#ef4444', borderWidth: 2, tension: .3, pointRadius: 3, order: 0 }] },
        options: { interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'top', align: 'end' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } } });
      var ini = parseInt(a.expediente.inicio, 10), fim = parseInt(a.expediente.fim, 10);
      grafico('aHora', { type: 'bar', data: { labels: a.por_hora.map(function (_, i) { return i + 'h'; }), datasets: [{ label: 'Entradas', data: a.por_hora, borderRadius: 5,
        backgroundColor: a.por_hora.map(function (_, i) { return i >= ini && i < fim ? hexA('#6366f1', .8) : hexA('#f59e0b', .85); }) }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 12 } } } } });
      var tp = a.dispositivos.tipo; rosca('aDisp', Object.keys(tp), Object.keys(tp).map(function (k) { return tp[k]; }), ['#6366f1', '#ec4899', '#10b981', '#94a3b8']);
    }).catch(function (e) { c.innerHTML = erroHTML(e); });
  };

  /* ============================ Integridade ============================ */
  renderizadores.integridade = function (c) {
    api('integridade').then(function (r) {
      var g = r.gatilhos, est = g.estado || {}, tot = r.totais;
      var h = '<div class="pa-banner ' + (r.ok ? 'ok' : 'ruim') + ' pa-fade"><span class="pa-b-ico"><i class="fas ' + (r.ok ? 'fa-shield-alt' : 'fa-unlink') + '"></i></span><div><h3>' +
        (r.ok ? 'Todas as trilhas de auditoria estão íntegras' : 'Atenção: foi encontrada alteração em uma trilha de auditoria') + '</h3><p>Verificado agora (' + dataHoraBR(r.verificado_em) + '). ' +
        (r.ok ? 'Nenhum registro foi alterado ou apagado depois de gravado.' : 'Algum registro foi alterado ou apagado direto no banco de dados. Veja os detalhes abaixo.') + '</p></div>' +
        '<button class="pa-btn" id="intVerificar"><i class="fas fa-redo"></i> Verificar de novo</button></div>';
      h += '<div class="pa-grid pa-g-64 pa-mt"><div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-link"></i> Cadeias de integridade (SHA-256)</h3></div>' + r.cadeias.map(function (x) {
        return '<div class="pa-cadeia ' + (x.ok ? 'ok' : 'ruim') + '"><span class="pa-c-ico"><i class="fas ' + (x.ok ? 'fa-check' : 'fa-times') + '"></i></span><div><b>' + esc(x.rotulo) + '</b><small>' + int(x.total) + ' registros verificados' +
          (x.pendentes ? ' · ' + int(x.pendentes) + ' aguardando selagem' : '') + (x.ok ? '' : ' · <span style="color:var(--pa-red)">' + esc(x.motivo) + '</span>') + '</small>' +
          (x.quebras && x.quebras.length ? '<div style="margin-top:6px;font-size:.76rem;color:var(--pa-red)">' + x.quebras.slice(0, 5).map(function (q) { return (q.id ? 'Registro #' + q.id + ' (selo ' + q.seq + '): ' : '') + esc(q.motivo); }).join('<br>') + '</div>' : '') +
          '</div><span class="pa-tag ' + (x.ok ? 'ok' : 'alerta') + '">' + (x.ok ? 'íntegra' : 'quebrada') + '</span></div>';
      }).join('') + '<div class="pa-campo"><div class="pa-ajuda"><i class="fas fa-info-circle"></i> Cada registro guarda o hash do anterior. Uma alteração direta no banco quebra a cadeia; uma âncora gravada fora do banco acusa até a remoção dos últimos registros.</div></div></div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-database"></i> Trilha de dados (gatilhos)</h3></div>' +
        '<div class="pa-membro-st" style="margin-top:0;grid-template-columns:repeat(2,1fr)">' + mini('Situação', g.config.dados ? (est.ligado ? '<span style="color:var(--pa-green)">ligada</span>' : '<span style="color:var(--pa-amber)">instalando</span>') : 'desligada') +
        mini('Tabelas monitoradas', int(est.tabelas || 0)) + mini('Gatilhos ativos', int(g.instalados)) + mini('Banco de dados', esc((est.mysql || '').split('-')[0] || '—')) + '</div>' +
        (est.erros && est.erros.length ? '<div class="pa-aviso-caixa pa-mt" style="background:var(--pa-amber-soft)"><i class="fas fa-exclamation-triangle" style="color:var(--pa-amber)"></i><div>' + est.erros.map(esc).join('<br>') + '</div></div>' : '') +
        '<div class="pa-membro-st" style="grid-template-columns:repeat(3,1fr)">' + mini('Operações', int(tot.operacoes)) + mini('Alterações', int(tot.dados)) + mini('Telas', int(tot.navegacao)) + '</div>' +
        '<div class="pa-campo pa-mt"><div class="pa-ajuda">Auditoria central instalada em ' + dataHoraBR(tot.instalado_em) + '. Atualizada por último em ' + dataHoraBR(est.em) + '.</div></div>' +
        '<button class="pa-btn" id="intReinstalar"><i class="fas fa-tools"></i> Reinstalar gatilhos</button></div></div>';
      h += '<div class="pa-card pa-mt"><div class="pa-card-h"><h3><i class="fas fa-th-list"></i> Cobertura por módulo</h3><span class="pa-dica">últimos 30 dias</span></div><div class="pa-tabela-wrap"><table class="pa-tabela"><thead><tr><th>Módulo</th><th class="num">Operações</th><th class="num">Telas abertas</th><th class="num">Registros alterados</th><th>Último registro</th></tr></thead><tbody>' +
        r.cobertura.map(function (m) {
          var ativo = m.operacoes + m.telas + m.dados > 0;
          return '<tr><td><span class="pa-dot" style="background:' + (ativo ? 'var(--pa-green)' : 'var(--pa-border-2)') + ';margin-right:8px"></span><b style="color:var(--pa-text)">' + esc(m.rotulo) + '</b></td><td class="num">' + int(m.operacoes) + '</td><td class="num">' + int(m.telas) + '</td><td class="num">' + int(m.dados) + '</td><td>' + (m.ultimo ? relativo(m.ultimo) : '<span style="color:var(--pa-muted)">sem uso no período</span>') + '</td></tr>';
        }).join('') + '</tbody></table></div><div class="pa-campo pa-mt"><div class="pa-ajuda">Todos os módulos estão ligados à auditoria central. Um módulo sem registros simplesmente não foi usado nos últimos 30 dias.</div></div></div>';
      c.innerHTML = h;
      $('#intVerificar').onclick = function () { renderizadores.integridade(c); toast('Verificando as trilhas…'); };
      $('#intReinstalar').onclick = function () {
        var b = this; b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reinstalando…';
        api('gatilhos_reinstalar', {}, true).then(function (x) { toast(x.ok ? 'Gatilhos reinstalados em ' + x.tabelas + ' tabelas.' : 'Concluído com avisos: ' + (x.erros[0] || x.mensagem), !x.ok); renderizadores.integridade(c); })
          .catch(function (e) { toast(e.message, true); b.disabled = false; });
      };
    }).catch(function (e) { c.innerHTML = erroHTML(e); });
  };

  /* ============================ Análise com IA ============================ */
  function md(txt) {
    var linhas = String(txt || '').replace(/\r/g, '').split('\n'), out = [], lista = null, tabela = [];
    function inline(s) {
      return esc(s).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/(^|[^*])\*(?!\s)(.+?)\*(?!\*)/g, '$1<em>$2</em>').replace(/`([^`]+)`/g, '<code>$1</code>');
    }
    function fechaLista() { if (lista) { out.push('</' + lista + '>'); lista = null; } }
    function fechaTabela() {
      if (!tabela.length) return;
      var lin = tabela.filter(function (l) { return !/^\s*\|?\s*:?-{2,}/.test(l); }).map(function (l) { return l.replace(/^\s*\|/, '').replace(/\|\s*$/, '').split('|').map(function (c) { return c.trim(); }); });
      out.push('<table><thead><tr>' + lin[0].map(function (c) { return '<th>' + inline(c) + '</th>'; }).join('') + '</tr></thead><tbody>' + lin.slice(1).map(function (r) { return '<tr>' + r.map(function (c) { return '<td>' + inline(c) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table>');
      tabela = [];
    }
    linhas.forEach(function (l) {
      if (/^\s*\|.*\|\s*$/.test(l)) { fechaLista(); tabela.push(l); return; }
      fechaTabela();
      var m;
      if ((m = l.match(/^(#{1,4})\s+(.*)$/))) { fechaLista(); var n = Math.min(4, Math.max(2, m[1].length)); out.push('<h' + n + '>' + inline(m[2]) + '</h' + n + '>'); return; }
      if ((m = l.match(/^\s*[-*•]\s+(.*)$/))) { if (lista !== 'ul') { fechaLista(); out.push('<ul>'); lista = 'ul'; } out.push('<li>' + inline(m[1]) + '</li>'); return; }
      if ((m = l.match(/^\s*\d+[.)]\s+(.*)$/))) { if (lista !== 'ol') { fechaLista(); out.push('<ol>'); lista = 'ol'; } out.push('<li>' + inline(m[1]) + '</li>'); return; }
      if ((m = l.match(/^\s*>\s?(.*)$/))) { fechaLista(); out.push('<blockquote>' + inline(m[1]) + '</blockquote>'); return; }
      if (/^\s*(-{3,}|\*{3,})\s*$/.test(l)) { fechaLista(); out.push('<hr>'); return; }
      if (!l.trim()) { fechaLista(); return; }
      fechaLista(); out.push('<p>' + inline(l) + '</p>');
    });
    fechaLista(); fechaTabela();
    return out.join('');
  }
  function iaResumo(ia) {
    var el = $('#paIaResumo'); if (!el) return;
    if (!ia || !ia.ativa) {
      el.innerHTML = '<div class="pa-ia-resumo pa-fade" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap"><span class="pa-ia-mini" style="width:52px;height:52px;border-radius:16px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,#f0abfc,#a78bfa 45%,#22d3ee)"><i class="fas fa-magic"></i></span>' +
        '<div style="flex:1;min-width:220px"><b style="color:var(--pa-text);font-size:1rem">Análise automática com IA</b><div style="color:var(--pa-muted);font-size:.86rem;margin-top:3px">Um relatório escrito sobre faturamento, equipe, serventia e riscos da auditoria, gerado pelo Gemini ou pelo Claude. Configure uma vez e use quando quiser, ou deixe automático.</div></div>' +
        '<button class="pa-btn prim" data-cfg="ia"><i class="fas fa-cog"></i> Configurar</button></div>';
      return;
    }
    if (ia.automatica_pendente) { el.innerHTML = '<div class="pa-ia-resumo"><div class="pa-pensando" style="padding:20px"><div class="pa-orb" style="width:46px;height:46px"></div>Gerando a análise automática do período…</div></div>'; gerarAnalise('geral', true).then(function () { delete estado.cache[Object.keys(estado.cache).filter(function (k) { return k.indexOf('visao_geral|') === 0; })[0]]; }); return; }
    if (!ia.ultima) {
      el.innerHTML = '<div class="pa-ia-resumo" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap"><i class="fas fa-magic" style="color:#a78bfa;font-size:1.3rem"></i><div style="flex:1"><b style="color:var(--pa-text)">A IA ainda não analisou este período.</b></div><button class="pa-btn prim" data-ir="ia"><i class="fas fa-magic"></i> Gerar análise</button></div>';
      return;
    }
    var u = ia.ultima;
    el.innerHTML = '<div class="pa-ia-resumo pa-fade"><div class="pa-ia-cab"><span class="pa-ia-mini"><i class="fas fa-magic"></i></span><div><b>Análise da IA</b><small>' + esc(u.provedor === 'claude' ? 'Claude' : 'Gemini') + ' · ' + esc(u.modelo) + ' · ' + dataHoraBR(u.criado_em) + ' · período ' + dataBR(u.periodo_ini) + ' a ' + dataBR(u.periodo_fim) + '</small></div>' +
      '<button class="pa-btn" data-ir="ia" style="margin-left:auto"><i class="fas fa-book-open"></i> Ler completa</button></div><div class="pa-md">' + md(u.texto) + '</div></div>';
  }
  function gerarAnalise(foco, auto) {
    return api('ia_analisar', { foco: foco || 'geral', automatica: auto ? 1 : 0, ini: estado.ini, fim: estado.fim }, true);
  }
  renderizadores.ia = function (c) {
    Promise.all([api('config'), api('ia_historico')]).then(function (r) {
      var cfg = r[0].ia, hist = r[1];
      if (!cfg.ativa || !(cfg['modelo_' + cfg.provedor]) || !cfg['tem_chave_' + cfg.provedor]) {
        c.innerHTML = '<div class="pa-card pa-ia-vazio"><div class="pa-ia-ico"><i class="fas fa-magic"></i></div><h3>Análise automática com IA</h3>' +
          '<p>A IA lê os indicadores do período — faturamento, equipe, prazos, acessos e auditoria — e escreve um relatório com diagnóstico, riscos e recomendações priorizadas. Funciona com o <b>Google Gemini</b> ou com o <b>Anthropic Claude</b>, usando a chave de API da serventia.</p>' +
          '<p style="font-size:.84rem"><i class="fas fa-user-shield" style="color:var(--pa-green)"></i> Só indicadores agregados são enviados: nomes de clientes, CPFs e o conteúdo das operações nunca saem do servidor.</p>' +
          '<button class="pa-btn prim" data-cfg="ia"><i class="fas fa-cog"></i> Configurar a análise com IA</button></div>';
        return;
      }
      var h = '<div class="pa-grid pa-g-73"><div class="pa-card" id="iaPainel"><div class="pa-ia-cab"><span class="pa-ia-mini"><i class="fas fa-magic"></i></span><div><b>Análise do período</b><small>' + dataBR(estado.ini) + ' a ' + dataBR(estado.fim) + ' · ' + (cfg.provedor === 'claude' ? 'Claude' : 'Gemini') + ' · ' + esc(cfg['modelo_' + cfg.provedor]) + '</small></div>' +
        '<div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap"><select class="pa-select" id="iaFoco"><option value="geral">Análise completa</option><option value="financeiro">Ênfase: finanças</option><option value="equipe">Ênfase: equipe</option><option value="auditoria">Ênfase: auditoria e riscos</option></select>' +
        '<button class="pa-btn prim" id="iaGerar"><i class="fas fa-magic"></i> Gerar análise</button></div></div><div id="iaTexto"></div></div>' +
        '<div class="pa-card"><div class="pa-card-h"><h3><i class="fas fa-history"></i> Análises anteriores</h3></div><div class="pa-ia-hist" id="iaHist">' +
        (hist.length ? hist.map(function (x) { return '<button data-ia="' + x.id + '"><b>' + dataBR(x.periodo_ini) + ' a ' + dataBR(x.periodo_fim) + (x.automatica == 1 ? ' · automática' : '') + '</b>' + dataHoraBR(x.criado_em) + ' · ' + esc(x.modelo) + (x.ok == 1 ? '' : ' · <span style="color:var(--pa-red)">falhou</span>') + '</button>'; }).join('') : '<div class="pa-vazio">Nenhuma ainda.</div>') +
        '</div></div></div>';
      c.innerHTML = h;
      var mostrar = function (a) {
        $$('#iaHist button').forEach(function (b) { b.classList.toggle('ativo', +b.dataset.ia === +a.id); });
        $('#iaTexto').innerHTML = a.ok == 1 || a.texto ? '<div class="pa-md pa-fade">' + md(a.texto) + '</div><div class="pa-pag pa-noprint"><span>' + esc(a.provedor === 'claude' ? 'Claude' : 'Gemini') + ' · ' + esc(a.modelo) + ' · ' + dataHoraBR(a.criado_em) + (a.tokens_entrada ? ' · ' + int(a.tokens_entrada) + ' + ' + int(a.tokens_saida) + ' tokens' : '') + '</span><span class="pa-pag-b"><button class="pa-btn sm" id="iaCopiar"><i class="far fa-copy"></i> Copiar</button><button class="pa-btn sm" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button></span></div>'
          : '<div class="pa-vazio"><i class="fas fa-exclamation-triangle" style="color:var(--pa-red);opacity:1"></i>' + esc(a.erro || 'Falhou.') + '</div>';
        var cp = $('#iaCopiar'); if (cp) cp.onclick = function () { (navigator.clipboard ? navigator.clipboard.writeText(a.texto) : Promise.reject()).then(function () { toast('Análise copiada.'); }).catch(function () { toast('Não foi possível copiar.', true); }); };
      };
      var doPeriodo = hist.filter(function (x) { return x.ok == 1 && x.periodo_ini === estado.ini && x.periodo_fim === estado.fim; })[0];
      if (doPeriodo) api('ia_ver', { id: doPeriodo.id }).then(mostrar);
      else $('#iaTexto').innerHTML = '<div class="pa-vazio" style="padding:50px 10px"><i class="fas fa-magic" style="color:#a78bfa;opacity:1"></i>Ainda não há análise deste período. Clique em <b>Gerar análise</b>.</div>';
      $$('#iaHist [data-ia]').forEach(function (b) { b.onclick = function () { api('ia_ver', { id: b.dataset.ia }).then(mostrar); }; });
      $('#iaGerar').onclick = function () {
        var b = this; b.disabled = true;
        $('#iaTexto').innerHTML = '<div class="pa-pensando"><div class="pa-orb"></div><b style="color:var(--pa-text)">A IA está analisando a serventia…</b><span>Lendo os indicadores de faturamento, equipe, prazos, acessos e auditoria. Pode levar até um minuto.</span></div>';
        gerarAnalise($('#iaFoco').value).then(function (x) {
          toast('Análise gerada.'); Object.keys(estado.cache).forEach(function (k) { if (k.indexOf('visao_geral|') === 0) delete estado.cache[k]; });
          renderizadores.ia(c);
        }).catch(function (e) { b.disabled = false; $('#iaTexto').innerHTML = '<div class="pa-vazio"><i class="fas fa-exclamation-triangle" style="color:var(--pa-red);opacity:1"></i>' + esc(e.message) + '</div>'; });
      };
    }).catch(function (e) { c.innerHTML = erroHTML(e); });
  };

  /* ============================ Configurações ============================ */
  var cfgAtual = null;
  function abrirConfig(aba) {
    $('#paModal').classList.add('aberto'); document.body.style.overflow = 'hidden';
    $$('#paCfgAbas button').forEach(function (b) { b.classList.toggle('ativo', b.dataset.c === (aba || 'ia')); });
    $('#paCfgC').innerHTML = '<div class="pa-skel" style="height:300px"></div>';
    api('config').then(function (c) { cfgAtual = c; (aba === 'aud' ? cfgAuditoria : cfgIA)(); }).catch(function (e) { $('#paCfgC').innerHTML = erroHTML(e); });
  }
  function fecharConfig() { $('#paModal').classList.remove('aberto'); document.body.style.overflow = ''; }
  function sw(id, tit, sub, on) { return '<label class="pa-switch"><input type="checkbox" id="' + id + '"' + (on ? ' checked' : '') + '><span class="pa-sw"></span><span><b>' + tit + '</b><small>' + sub + '</small></span></label>'; }
  function cfgIA() {
    var c = cfgAtual.ia, prov = c.provedor;
    var opcoes = function (p) {
      var l = c['modelos_' + p] || [];
      if (!l.length) return '<option value="">— carregue a lista de modelos da API —</option>';
      return '<option value="">Escolha um modelo…</option>' + l.map(function (m) { return '<option value="' + esc(m.id) + '"' + (c['modelo_' + p] === m.id ? ' selected' : '') + '>' + esc(m.nome) + ' (' + esc(m.id) + ')</option>'; }).join('');
    };
    var bloco = function (p) {
      var nome = p === 'claude' ? 'Anthropic' : 'Google AI Studio', url = p === 'claude' ? 'console.anthropic.com' : 'aistudio.google.com';
      return '<div class="pa-campo" data-prov-campos="' + p + '" style="' + (prov === p ? '' : 'display:none') + '"><label>Chave da API (' + nome + ')</label><div class="pa-linha"><input class="pa-input" type="password" id="cfChave_' + p + '" autocomplete="off" placeholder="' + (c['tem_chave_' + p] ? '' : 'cole aqui a chave') + '" value="' + esc(c['chave_' + p]) + '">' +
        '<button class="pa-btn" type="button" data-ver="cfChave_' + p + '" title="Mostrar"><i class="far fa-eye"></i></button><button class="pa-btn" type="button" id="cfCarregar_' + p + '"><i class="fas fa-cloud-download-alt"></i> Carregar modelos</button></div>' +
        '<div class="pa-ajuda">Crie a chave em <b>' + url + '</b>. Ela fica criptografada no servidor e aparece mascarada aqui. Deixe como está para manter a atual; apague o campo e salve para remover.</div>' +
        '<label style="margin-top:12px">Modelo</label><select class="pa-select" id="cfModelo_' + p + '">' + opcoes(p) + '</select>' +
        '<div class="pa-modelo-info" id="cfInfo_' + p + '">' + (c.modelos_em && c.modelos_em[p] ? 'Lista consultada na API em ' + dataHoraBR(c.modelos_em[p]) + ' · ' + (c['modelos_' + p] || []).length + ' modelos disponíveis para esta chave.' : 'A lista mostra só os modelos que a sua chave pode usar, direto da API do provedor.') + '</div></div>';
    };
    $('#paCfgC').innerHTML =
      '<div class="pa-aviso-caixa"><i class="fas fa-user-shield"></i><div>Para a análise, o painel envia ao provedor apenas <b>indicadores agregados</b> (totais, médias, contagens por funcionário). Nomes de clientes, CPFs, documentos e o conteúdo das operações <b>nunca</b> saem do servidor.</div></div>' +
      sw('cfAtiva', 'Ativar a análise com IA', 'Mostra o botão de análise no painel e permite gerar relatórios.', c.ativa) +
      '<div class="pa-prov"><label><input type="radio" name="cfProv" value="gemini"' + (prov === 'gemini' ? ' checked' : '') + '><span class="pa-logo" style="background:linear-gradient(135deg,#4285f4,#9b72cb,#d96570)">G</span><span><b>Google Gemini</b><small>Família Gemini (Pro, Flash)</small></span></label>' +
      '<label><input type="radio" name="cfProv" value="claude"' + (prov === 'claude' ? ' checked' : '') + '><span class="pa-logo" style="background:linear-gradient(135deg,#d97757,#c15f3c)">C</span><span><b>Anthropic Claude</b><small>Família Claude (Opus, Sonnet, Haiku)</small></span></label></div>' +
      bloco('gemini') + bloco('claude') +
      sw('cfAuto', 'Gerar a análise automaticamente', 'Ao abrir o painel, se ainda não houver análise do período no dia, ela é gerada sozinha (no máximo uma vez por dia para cada período).', c.automatica) +
      sw('cfAnon', 'Anonimizar os nomes dos funcionários', 'Os nomes são trocados por "Colaborador A, B…" antes do envio e trocados de volta na resposta.', c.anonimizar) +
      '<div class="pa-campo"><label>Orientações adicionais (opcional)</label><textarea class="pa-input" id="cfInstr" placeholder="Ex.: A meta mensal de recebimento é R$ 60.000. Avalie também o tempo de resposta dos pedidos de certidão.">' + esc(c.instrucoes) + '</textarea></div>' +
      '<div style="display:flex;justify-content:flex-end;gap:8px"><button class="pa-btn" id="cfCancelar">Cancelar</button><button class="pa-btn prim" id="cfSalvar"><i class="fas fa-save"></i> Salvar</button></div>';
    $$('input[name="cfProv"]').forEach(function (r) { r.onchange = function () { $$('[data-prov-campos]').forEach(function (b) { b.style.display = b.dataset.provCampos === r.value ? '' : 'none'; }); }; });
    $$('[data-ver]').forEach(function (b) { b.onclick = function () { var i = document.getElementById(b.dataset.ver); if (i.value.indexOf('•') >= 0) { i.value = ''; i.placeholder = 'digite a nova chave'; } i.type = i.type === 'password' ? 'text' : 'password'; }; });
    ['gemini', 'claude'].forEach(function (p) {
      $('#cfCarregar_' + p).onclick = function () {
        var b = this, ch = $('#cfChave_' + p).value.trim();
        if (!ch) { toast('Informe a chave da API primeiro.', true); return; }
        b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando…';
        api('ia_modelos', { provedor: p, chave: ch }, true).then(function (r) {
          cfgAtual.ia = r.config; var atual = $('#cfModelo_' + p).value;
          cfgAtual.ia['modelo_' + p] = atual || cfgAtual.ia['modelo_' + p];
          $('#cfModelo_' + p).innerHTML = opcoes(p); if (atual) $('#cfModelo_' + p).value = atual;
          $('#cfChave_' + p).value = r.config['chave_' + p];
          $('#cfInfo_' + p).innerHTML = '<span style="color:var(--pa-green)"><i class="fas fa-check-circle"></i> Chave válida.</span> ' + r.modelos.length + ' modelos disponíveis para esta chave.';
          toast(r.modelos.length + ' modelos carregados.');
        }).catch(function (e) { $('#cfInfo_' + p).innerHTML = '<span style="color:var(--pa-red)"><i class="fas fa-times-circle"></i> ' + esc(e.message) + '</span>'; toast(e.message, true); })
          .then(function () { b.disabled = false; b.innerHTML = '<i class="fas fa-cloud-download-alt"></i> Carregar modelos'; });
      };
    });
    $('#cfCancelar').onclick = fecharConfig;
    $('#cfSalvar').onclick = function () {
      var b = this, p = $('input[name="cfProv"]:checked').value; b.disabled = true;
      api('ia_config_salvar', { ativa: $('#cfAtiva').checked ? 1 : 0, provedor: p, automatica: $('#cfAuto').checked ? 1 : 0, anonimizar: $('#cfAnon').checked ? 1 : 0,
        modelo_gemini: $('#cfModelo_gemini').value, modelo_claude: $('#cfModelo_claude').value, chave_gemini: $('#cfChave_gemini').value, chave_claude: $('#cfChave_claude').value, instrucoes: $('#cfInstr').value }, true)
        .then(function (r) { cfgAtual.ia = r.config; toast(r.aviso || 'Configuração da IA salva.', !!r.aviso); estado.cache = {}; if (!r.aviso) fecharConfig(); irPara(estado.aba); })
        .catch(function (e) { toast(e.message, true); }).then(function () { b.disabled = false; });
    };
  }
  function cfgAuditoria() {
    var a = cfgAtual.auditoria, g = cfgAtual.gatilhos || {}, excl = a.tabelas_excluidas || [];
    var dias = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    $('#paCfgC').innerHTML =
      sw('caAtiva', 'Trilha de operações', 'Registra cada operação feita em qualquer módulo: quem, quando, de onde, o que enviou e o resultado. Recomendado: sempre ligada.', +a.ativa) +
      sw('caDados', 'Trilha de dados (antes e depois)', 'Gatilhos no banco registram o valor anterior e o novo de cada registro incluído, alterado ou excluído — inclusive mudanças feitas fora do Atlas. ' + (cfgAtual.mysql.json ? '' : '<b style="color:var(--pa-red)">Indisponível: o banco (' + esc(cfgAtual.mysql.versao) + ') não tem funções JSON.</b>'), +a.dados) +
      sw('caNav', 'Telas abertas', 'Registra cada tela aberta por cada usuário (navegação). Útil para saber quem consultou o quê.', +a.navegacao) +
      '<div class="pa-grid pa-g2"><div class="pa-campo"><label>Expediente</label><div class="pa-linha"><input class="pa-input" type="time" id="caIni" value="' + esc(a.expediente_inicio) + '"><span style="align-self:center;color:var(--pa-muted)">até</span><input class="pa-input" type="time" id="caFim" value="' + esc(a.expediente_fim) + '"></div>' +
      '<div class="pa-dias" style="margin-top:8px">' + dias.map(function (d, i) { return '<label><input type="checkbox" class="caDia" value="' + (i + 1) + '"' + (a.expediente_dias.map(Number).indexOf(i + 1) >= 0 ? ' checked' : '') + '><span>' + d + '</span></label>'; }).join('') + '</div>' +
      '<div class="pa-ajuda">Operações e entradas fora deste horário recebem a marca "fora do expediente" e geram alertas.</div></div>' +
      '<div class="pa-campo"><label>Prazo de guarda</label><div class="pa-linha"><input class="pa-input" type="number" min="0" id="caRet" value="' + (+a.retencao_dias) + '"><span style="align-self:center;color:var(--pa-muted);white-space:nowrap">dias (operações e dados)</span></div>' +
      '<div class="pa-linha" style="margin-top:8px"><input class="pa-input" type="number" min="0" id="caRetNav" value="' + (+a.retencao_navegacao) + '"><span style="align-self:center;color:var(--pa-muted);white-space:nowrap">dias (telas abertas)</span></div>' +
      '<div class="pa-ajuda">0 = guardar para sempre (recomendado para operações). O prazo de guarda é aplicado uma vez por dia e o novo início da cadeia de integridade fica registrado.</div></div></div>' +
      '<div class="pa-campo"><label>Tabelas fora da trilha de dados</label><input class="pa-input" id="caFiltroTab" placeholder="Filtrar tabelas…" style="margin-bottom:8px"><div class="pa-tabs-lista" id="caTabs">' +
      cfgAtual.tabelas.map(function (t) { return '<label data-t="' + esc(t.tabela) + '"><input type="checkbox" class="caExcl" value="' + esc(t.tabela) + '"' + (excl.indexOf(t.tabela) >= 0 ? ' checked' : '') + '> ' + esc(t.tabela) + '<small>' + esc(t.modulo) + '</small></label>'; }).join('') +
      '</div><div class="pa-ajuda">Marque só tabelas de cache ou de importação em massa. As tabelas de log e as próprias trilhas já ficam de fora automaticamente. ' + (g && g.tabelas ? 'Hoje: ' + int(g.tabelas) + ' tabelas monitoradas.' : '') + '</div></div>' +
      '<div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><button class="pa-btn" id="caExpurgar"><i class="fas fa-broom"></i> Aplicar o prazo de guarda agora</button><span style="display:flex;gap:8px"><button class="pa-btn" id="caCancelar">Cancelar</button><button class="pa-btn prim" id="caSalvar"><i class="fas fa-save"></i> Salvar</button></span></div>';
    $('#caFiltroTab').oninput = function () { var q = this.value.toLowerCase(); $$('#caTabs label').forEach(function (l) { l.style.display = l.dataset.t.indexOf(q) >= 0 ? '' : 'none'; }); };
    $('#caCancelar').onclick = fecharConfig;
    $('#caExpurgar').onclick = function () { api('expurgar', {}, true).then(function (r) { toast('Prazo de guarda aplicado: ' + int(r.operacoes + r.dados + r.navegacao) + ' registro(s) antigos removidos.'); }).catch(function (e) { toast(e.message, true); }); };
    $('#caSalvar').onclick = function () {
      var b = this; b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando…';
      var cfg = { ativa: $('#caAtiva').checked ? 1 : 0, dados: $('#caDados').checked ? 1 : 0, navegacao: $('#caNav').checked ? 1 : 0, expediente_inicio: $('#caIni').value, expediente_fim: $('#caFim').value,
        expediente_dias: $$('.caDia:checked').map(function (x) { return x.value; }), retencao_dias: $('#caRet').value, retencao_navegacao: $('#caRetNav').value, tabelas_excluidas: $$('.caExcl:checked').map(function (x) { return x.value; }) };
      api('auditoria_config_salvar', { config: JSON.stringify(cfg) }, true).then(function (r) {
        toast(r.gatilhos ? 'Configuração salva. Gatilhos atualizados em ' + r.gatilhos.tabelas + ' tabelas.' : 'Configuração da auditoria salva.'); estado.cache = {}; fecharConfig(); irPara(estado.aba);
      }).catch(function (e) { toast(e.message, true); }).then(function () { b.disabled = false; b.innerHTML = '<i class="fas fa-save"></i> Salvar'; });
    };
  }

  /* ============================ Eventos globais ============================ */
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-ev], [data-ev-link], [data-membro], [data-ir], [data-cfg], [data-hist-t], [data-ver-trilha], .pa-alerta[data-aba]');
    if (!t) return;
    if (t.matches('[data-hist-t]')) { ev.preventDefault(); ev.stopPropagation(); abrirHistorico(t.dataset.histT, t.dataset.histR); return; }
    if (t.matches('[data-ev-link]')) { ev.preventDefault(); abrirEvento(t.dataset.evLink); return; }
    if (t.matches('[data-ev]')) { abrirEvento(t.dataset.ev); return; }
    if (t.matches('[data-membro]')) { abrirMembro(t.dataset.membro); return; }
    if (t.matches('[data-ver-trilha]')) { fecharGaveta(); irPara('auditoria', { sub: 'operacoes', usuario: t.dataset.verTrilha, pagina: 1, sem_navegacao: 1 }); return; }
    if (t.matches('[data-ir]')) { ev.preventDefault(); irPara(t.dataset.ir); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    if (t.matches('[data-cfg]')) { abrirConfig(t.dataset.cfg === 'aud' ? 'aud' : 'ia'); return; }
    if (t.matches('.pa-alerta[data-aba]')) {
      var f = null; try { f = JSON.parse(t.dataset.filtro || 'null'); } catch (e) { }
      irPara(t.dataset.aba, f ? Object.assign({ sub: t.dataset.aba === 'trilhas' ? 'trilhas' : 'operacoes', pagina: 1, sem_navegacao: 1 }, f) : null);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });
  $$('#paAbas button').forEach(function (b) { b.onclick = function () { if (b.dataset.aba === 'auditoria') estado.filtros = { sub: 'operacoes', pagina: 1, sem_navegacao: 1 }; irPara(b.dataset.aba); }; });
  $$('#paChips button').forEach(function (b) { b.onclick = function () {
    estado.p = b.dataset.p;
    if (estado.p === 'custom') { mostrarPeriodo(); $('#paIni').focus(); return; }
    calcPeriodo(estado.p); guardar(); mostrarPeriodo(); irPara(estado.aba);
  }; });
  $('#paAplicar').onclick = function () {
    var i = $('#paIni').value, f = $('#paFim').value; if (!i || !f) { toast('Informe as duas datas.', true); return; }
    if (i > f) { var x = i; i = f; f = x; } estado.ini = i; estado.fim = f; estado.p = 'custom'; guardar(); mostrarPeriodo(); irPara(estado.aba);
  };
  $('#paAtualizar').onclick = function () { estado.cache = {}; filtrosBase = null; irPara(estado.aba); toast('Dados atualizados.'); };
  $('#paImprimir').onclick = function () { window.print(); };
  $('#paConfig').onclick = function () { abrirConfig(estado.aba === 'auditoria' || estado.aba === 'integridade' ? 'aud' : 'ia'); };
  $('#paIaBtn').onclick = function () { irPara('ia'); window.scrollTo({ top: 0, behavior: 'smooth' }); };
  $('#paGavetaX').onclick = fecharGaveta; $('#paGavetaFundo').onclick = fecharGaveta;
  $('#paModalX').onclick = fecharConfig;
  $('#paModal').addEventListener('click', function (e) { if (e.target === this) fecharConfig(); });
  $$('#paCfgAbas button').forEach(function (b) { b.onclick = function () { $$('#paCfgAbas button').forEach(function (x) { x.classList.toggle('ativo', x === b); }); (b.dataset.c === 'aud' ? cfgAuditoria : cfgIA)(); }; });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { fecharGaveta(); fecharConfig(); } });
  // Troca de tema claro/escuro pelo menu do Atlas: redesenha os gráficos com as novas cores
  var temaAtual = escuro();
  new MutationObserver(function () { if (escuro() === temaAtual) return; temaAtual = escuro(); temaGraficos(); irPara(estado.aba); }).observe(document.body, { attributes: true, attributeFilter: ['class'] });

  /* ============================ Início ============================ */
  if (estado.p !== 'custom' || !estado.ini) { if (estado.p === 'custom') estado.p = 'mes'; calcPeriodo(estado.p); }
  mostrarPeriodo();
  temaGraficos();
  window.addEventListener('hashchange', function () { var h = (location.hash || '').replace('#', ''); if (renderizadores[h] && h !== estado.aba) irPara(h); });
  var inicial = (location.hash || '').replace('#', '');
  irPara(renderizadores[inicial] ? inicial : 'geral');
})();
