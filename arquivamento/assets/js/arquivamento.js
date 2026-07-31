/* =====================================================================
   Atlas · Arquivamento Digital — aplicação da tela de acervo.
   Sem jQuery: só DOM nativo, para não depender da versão carregada pelo menu.
   Todo texto vindo do servidor passa por esc() antes de virar HTML.
   ===================================================================== */
(function () {
  'use strict';

  var CFG = window.ARQ_CFG || {};
  var CSRF = CFG.csrf || '';

  /* ================================================================= *
   * Utilidades
   * ================================================================= */
  function $(s, ctx) { return (ctx || document).querySelector(s); }
  function $$(s, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(s)); }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function fmtData(d) {
    if (!d) { return '—'; }
    var p = String(d).slice(0, 10).split('-');
    return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d;
  }

  function fmtDataHora(d) {
    if (!d) { return '—'; }
    var s = String(d).replace('T', ' ');
    var p = s.slice(0, 16).split(' ');
    return fmtData(p[0]) + (p[1] ? ' às ' + p[1] : '');
  }

  function fmtDoc(v) {
    var d = String(v || '').replace(/\D/g, '');
    if (d.length === 11) { return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4'); }
    if (d.length === 14) { return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5'); }
    return v || '';
  }

  function iconePorExt(e) {
    switch (String(e || '').toLowerCase()) {
      case 'pdf': return 'fa-file-pdf-o';
      case 'jpg': case 'jpeg': case 'png': case 'gif': case 'webp': case 'bmp': case 'tif': case 'tiff':
        return 'fa-file-image-o';
      case 'doc': case 'docx': case 'odt': return 'fa-file-word-o';
      case 'xls': case 'xlsx': return 'fa-file-excel-o';
      case 'txt': case 'xml': return 'fa-file-text-o';
      case 'p7s': return 'fa-certificate';
      default: return 'fa-file-o';
    }
  }

  function ehImagem(e) {
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].indexOf(String(e || '').toLowerCase()) >= 0;
  }

  /* Avisos — usa SweetAlert2 se existir, senão cai no nativo. */
  function aviso(icone, titulo, texto) {
    return ArqDlg.aviso(icone, titulo, texto);
  }

  function toast(icone, titulo) {
    return ArqDlg.toast(icone, titulo);
  }

  function confirmar(titulo, texto, botao, perigo) {
    return ArqDlg.confirmar(titulo, texto, botao, perigo);
  }

  /* Requisições --------------------------------------------------------- */
  function pegar(url) {
    return fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(tratar);
  }

  function enviar(url, dados) {
    var fd = dados instanceof FormData ? dados : new FormData();
    if (!(dados instanceof FormData)) {
      Object.keys(dados || {}).forEach(function (k) { fd.append(k, dados[k]); });
    }
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF, 'Accept': 'application/json' }
    }).then(tratar);
  }

  function tratar(r) {
    return r.json().catch(function () {
      throw new Error('O servidor devolveu uma resposta inesperada.');
    }).then(function (j) {
      if (j && j.ok) { return j; }
      if (r.status === 401 && j && j.redirect) {
        aviso('warning', 'Sessão encerrada', 'Faça login novamente para continuar.')
          .then(function () { window.location.href = j.redirect; });
        throw new Error('sessão expirada');
      }
      throw new Error((j && j.erro) || 'Não foi possível concluir a operação.');
    });
  }

  /* Diálogos ------------------------------------------------------------ */
  var pilhaDialogos = [];

  function abrirDialogo(el) {
    el.classList.add('aberto');
    el.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    pilhaDialogos.push(el);
    var foco = el.querySelector('[data-foco], .arq-fechar');
    if (foco) { foco.focus(); }
  }

  function fecharDialogo(el) {
    el = el || pilhaDialogos[pilhaDialogos.length - 1];
    if (!el) { return; }
    el.classList.remove('aberto');
    el.setAttribute('aria-hidden', 'true');
    pilhaDialogos = pilhaDialogos.filter(function (d) { return d !== el; });
    if (!pilhaDialogos.length) { document.body.style.overflow = ''; }
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && pilhaDialogos.length) { fecharDialogo(); }
  });

  document.addEventListener('click', function (e) {
    if (e.target.classList && e.target.classList.contains('arq-fundo')) { fecharDialogo(e.target); }
    var f = e.target.closest && e.target.closest('[data-fechar]');
    if (f) { fecharDialogo(f.closest('.arq-fundo')); }
  });

  /* ================================================================= *
   * Estado
   * ================================================================= */
  var estado = {
    filtros: {
      q: '', atribuicao: '', categoria: '', cpf: '', nome: '', livro: '', folha: '',
      termo: '', protocolo: '', matricula: '', descricao: '', data: '', de: '', ate: '',
      com_anexo: '', ordenar: 'data_ato', direcao: 'desc'
    },
    periodo: '30d',
    pagina: 1,
    porPagina: 24,
    visao: localStorage.getItem('arq_visao') || 'cards',
    selecao: {},
    ultimoResultado: null,
    idsResultado: []
  };

  /* Períodos ------------------------------------------------------------ */
  function limitesPeriodo(chave) {
    var hoje = new Date();
    function iso(d) {
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    var de = new Date(hoje);
    switch (chave) {
      case 'hoje':  return { de: iso(hoje), ate: iso(hoje) };
      case '7d':    de.setDate(de.getDate() - 6); return { de: iso(de), ate: iso(hoje) };
      case '30d':   de.setDate(de.getDate() - 29); return { de: iso(de), ate: iso(hoje) };
      case 'ano':   return { de: hoje.getFullYear() + '-01-01', ate: iso(hoje) };
      case '12m':   de.setMonth(de.getMonth() - 12); return { de: iso(de), ate: iso(hoje) };
      default:      return { de: '', ate: '' };
    }
  }

  /* ================================================================= *
   * Renderização da lista
   * ================================================================= */
  function marcado(id) { return !!estado.selecao[id]; }

  function fichaHTML(it) {
    var partes = (it.partes || []).join(' · ');
    var codigos = [
      ['Livro', it.livro], ['Folha', it.folha], ['Termo', it.termo],
      ['Protocolo', it.protocolo], ['Matrícula', it.matricula]
    ].filter(function (c) { return c[1] !== '' && c[1] != null; });

    var marcas = '';
    if (it.anexos_qtd > 0) {
      marcas += '<span class="arq-marca tem-anexo"><i class="fa fa-paperclip"></i>' + it.anexos_qtd + '</span>';
    }
    if (it.selos > 0) {
      marcas += '<span class="arq-marca tem-selo"><i class="fa fa-certificate"></i>' + it.selos + '</span>';
    }
    marcas += '<span class="arq-marca"><i class="fa fa-calendar-o"></i>' + fmtData(it.data_ato) + '</span>';

    return '' +
      '<article class="arq-ficha' + (marcado(it.id) ? ' marcada' : '') + '" data-id="' + esc(it.id) + '" data-atr="' + esc(it.atribuicao) + '">' +
        '<div class="arq-lombada" data-abrir="' + esc(it.id) + '" title="Abrir arquivamento ' + esc(it.id) + '">' +
          '<span>' + esc(it.id) + '</span>' +
        '</div>' +
        '<div class="arq-ficha-corpo">' +
          '<div class="arq-ficha-topo">' +
            '<label><input type="checkbox" class="arq-marcar" data-id="' + esc(it.id) + '"' + (marcado(it.id) ? ' checked' : '') + ' aria-label="Selecionar arquivamento ' + esc(it.id) + '"></label>' +
            '<div style="flex:1;min-width:0">' +
              '<div class="arq-etiqueta">' + esc(it.atribuicao || 'Sem atribuição') + '</div>' +
              '<h3 data-abrir="' + esc(it.id) + '" title="' + esc(it.categoria) + '">' + esc(it.categoria || 'Sem categoria') + '</h3>' +
            '</div>' +
          '</div>' +
          '<p class="arq-partes" data-abrir="' + esc(it.id) + '">' + esc(partes) + '</p>' +
          (codigos.length ? '<div class="arq-codigos">' + codigos.map(function (c) {
            return '<div class="arq-codigo"><em>' + esc(c[0]) + '</em><b>' + esc(c[1]) + '</b></div>';
          }).join('') + '</div>' : '') +
          '<div class="arq-ficha-pe">' +
            '<div class="arq-marcas">' + marcas + '</div>' +
            '<div class="arq-acoes">' +
              '<button class="arq-btn arq-btn-sm arq-btn-ic" data-abrir="' + esc(it.id) + '" title="Visualizar"><i class="fa fa-eye"></i></button>' +
              '<button class="arq-btn arq-btn-sm arq-btn-ic" data-compilar="' + esc(it.id) + '" title="Compilar documentos"><i class="fa fa-files-o"></i></button>' +
              '<button class="arq-btn arq-btn-sm arq-btn-ic" data-editar="' + esc(it.id) + '" title="Editar"><i class="fa fa-pencil"></i></button>' +
              '<button class="arq-btn arq-btn-sm arq-btn-ic arq-btn-perigo" data-excluir="' + esc(it.id) + '" title="Mover para a lixeira"><i class="fa fa-trash-o"></i></button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</article>';
  }

  function linhaHTML(it) {
    return '' +
      '<tr data-id="' + esc(it.id) + '" class="' + (marcado(it.id) ? 'marcada' : '') + '">' +
        '<td class="col-min"><input type="checkbox" class="arq-marcar" data-id="' + esc(it.id) + '"' + (marcado(it.id) ? ' checked' : '') + ' aria-label="Selecionar"></td>' +
        '<td class="col-min arq-mono" style="font-size:.78rem;color:var(--arq-suave)">' + esc(it.id) + '</td>' +
        '<td class="col-min">' + fmtData(it.data_ato) + '</td>' +
        '<td><span class="arq-pilula-atr"><span class="arq-ponto" style="background:' + corAtribuicao(it.atribuicao) + '"></span>' + esc(it.atribuicao) + '</span></td>' +
        '<td>' + esc(it.categoria) + '</td>' +
        '<td style="max-width:280px">' + esc((it.partes || []).join(' · ')) + '</td>' +
        '<td class="col-min arq-mono">' + esc(it.livro || '—') + '/' + esc(it.folha || '—') + '</td>' +
        '<td class="col-min">' + (it.anexos_qtd || 0) + '</td>' +
        '<td class="col-min">' +
          '<button class="arq-btn arq-btn-sm arq-btn-ic" data-abrir="' + esc(it.id) + '" title="Abrir"><i class="fa fa-eye"></i></button> ' +
          '<button class="arq-btn arq-btn-sm arq-btn-ic" data-compilar="' + esc(it.id) + '" title="Compilar"><i class="fa fa-files-o"></i></button> ' +
          '<button class="arq-btn arq-btn-sm arq-btn-ic" data-editar="' + esc(it.id) + '" title="Editar"><i class="fa fa-pencil"></i></button>' +
        '</td>' +
      '</tr>';
  }

  function corAtribuicao(a) {
    var m = {
      'Registro Civil': 'var(--arq-rc)',
      'Registro de Imóveis': 'var(--arq-ri)',
      'Registro de Títulos e Documentos': 'var(--arq-rtd)',
      'Registro Civil das Pessoas Jurídicas': 'var(--arq-rcpj)',
      'Notas': 'var(--arq-not)',
      'Protesto': 'var(--arq-prot)',
      'Contratos Marítimos': 'var(--arq-cmar)'
    };
    return m[a] || 'var(--arq-adm)';
  }

  function renderizar(dados) {
    var alvo = $('#arq-resultados');
    var itens = dados.itens || [];

    $('#arq-contagem').innerHTML = itens.length
      ? '<b>' + dados.total + '</b> arquivamento' + (dados.total === 1 ? '' : 's') +
        ' · <b>' + dados.resumo.anexos + '</b> anexo' + (dados.resumo.anexos === 1 ? '' : 's') +
        ' · ' + esc(dados.resumo.bytes_legivel)
      : '';

    if (!itens.length) {
      alvo.innerHTML =
        '<div class="arq-vazio">' +
          '<i class="fa fa-archive"></i>' +
          '<h3>Nenhum arquivamento encontrado</h3>' +
          '<p>Ajuste os filtros ou amplie o período para ver mais registros.</p>' +
          '<button class="arq-btn" id="arq-limpar-tudo"><i class="fa fa-refresh"></i> Limpar filtros</button>' +
        '</div>';
      $('#arq-paginacao').innerHTML = '';
      return;
    }

    if (estado.visao === 'tabela') {
      alvo.innerHTML =
        '<div class="arq-tabela-caixa"><table class="arq-tabela">' +
          '<thead><tr>' +
            '<th class="col-min"><input type="checkbox" id="arq-marcar-todos" aria-label="Selecionar tudo nesta página"></th>' +
            '<th data-ord="id">Nº</th>' +
            '<th data-ord="data_ato">Data</th>' +
            '<th data-ord="atribuicao">Atribuição</th>' +
            '<th data-ord="categoria">Categoria</th>' +
            '<th>Partes</th>' +
            '<th>Livro/Folha</th>' +
            '<th data-ord="anexos_qtd">Anexos</th>' +
            '<th></th>' +
          '</tr></thead><tbody>' + itens.map(linhaHTML).join('') + '</tbody>' +
        '</table></div>';
    } else {
      alvo.innerHTML = '<div class="arq-estante">' + itens.map(fichaHTML).join('') + '</div>';
    }

    renderizarPaginacao(dados);
  }

  function renderizarPaginacao(d) {
    var box = $('#arq-paginacao');
    if (d.paginas <= 1) { box.innerHTML = ''; return; }
    box.innerHTML =
      '<button class="arq-btn arq-btn-sm" data-pag="1" ' + (d.pagina === 1 ? 'disabled' : '') + '><i class="fa fa-angle-double-left"></i></button>' +
      '<button class="arq-btn arq-btn-sm" data-pag="' + (d.pagina - 1) + '" ' + (d.pagina === 1 ? 'disabled' : '') + '><i class="fa fa-angle-left"></i> Anterior</button>' +
      '<span class="arq-pag-info">Página ' + d.pagina + ' de ' + d.paginas + '</span>' +
      '<button class="arq-btn arq-btn-sm" data-pag="' + (d.pagina + 1) + '" ' + (d.pagina === d.paginas ? 'disabled' : '') + '>Próxima <i class="fa fa-angle-right"></i></button>' +
      '<button class="arq-btn arq-btn-sm" data-pag="' + d.paginas + '" ' + (d.pagina === d.paginas ? 'disabled' : '') + '><i class="fa fa-angle-double-right"></i></button>';
  }

  /* Busca --------------------------------------------------------------- */
  var buscando = false;

  function buscar(manterPagina) {
    if (!manterPagina) { estado.pagina = 1; }
    var p = limitesPeriodo(estado.periodo);
    var f = Object.assign({}, estado.filtros);
    if (!f.data) { f.de = p.de; f.ate = p.ate; }

    var qs = Object.keys(f)
      .filter(function (k) { return f[k] !== '' && f[k] != null; })
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(f[k]); })
      .join('&');
    qs += '&pagina=' + estado.pagina + '&por_pagina=' + estado.porPagina;

    if (!buscando) {
      $('#arq-resultados').innerHTML = '<div class="arq-estante">' +
        new Array(6).join('<div class="arq-esqueleto"></div>') + '</div>';
    }
    buscando = true;

    return pegar('api/listar.php?' + qs).then(function (d) {
      buscando = false;
      estado.ultimoResultado = d;
      estado.idsResultado = d.ids || [];
      renderizar(d);
      atualizarChipsAtivos();
      atualizarBarraSelecao();
    }).catch(function (e) {
      buscando = false;
      $('#arq-resultados').innerHTML =
        '<div class="arq-vazio"><i class="fa fa-exclamation-triangle"></i>' +
        '<h3>Não foi possível carregar o acervo</h3><p>' + esc(e.message) + '</p></div>';
    });
  }

  /* Chips de filtros ativos --------------------------------------------- */
  var ROTULOS = {
    q: 'Busca', atribuicao: 'Atribuição', categoria: 'Categoria', cpf: 'CPF/CNPJ',
    nome: 'Nome', livro: 'Livro', folha: 'Folha', termo: 'Termo', protocolo: 'Protocolo',
    matricula: 'Matrícula', descricao: 'Descrição', data: 'Data', de: 'De', ate: 'Até',
    com_anexo: 'Anexos'
  };

  function atualizarChipsAtivos() {
    var box = $('#arq-ativos');
    var html = '';
    Object.keys(ROTULOS).forEach(function (k) {
      var v = estado.filtros[k];
      if (!v) { return; }
      var mostrado = k === 'com_anexo' ? (v === 'sim' ? 'com anexo' : 'sem anexo') : v;
      html += '<span class="arq-ativo">' + esc(ROTULOS[k]) + ': ' + esc(mostrado) +
              '<button data-tirar="' + k + '" aria-label="Remover filtro">&times;</button></span>';
    });
    if (html) {
      html += '<button class="arq-btn arq-btn-sm" id="arq-limpar-tudo"><i class="fa fa-times"></i> Limpar tudo</button>';
    }
    box.innerHTML = html;
  }

  /* ================================================================= *
   * Seleção
   * ================================================================= */
  function alternarSelecao(id, ligado) {
    if (ligado) { estado.selecao[id] = true; } else { delete estado.selecao[id]; }
    var ficha = $('.arq-ficha[data-id="' + id + '"]') || $('tr[data-id="' + id + '"]');
    if (ficha) { ficha.classList.toggle('marcada', !!ligado); }
    atualizarBarraSelecao();
  }

  function idsSelecionados() { return Object.keys(estado.selecao); }

  function atualizarBarraSelecao() {
    var ids = idsSelecionados();
    var barra = $('#arq-selecao');
    barra.classList.toggle('visivel', ids.length > 0);
    $('#arq-selecao-n').textContent = ids.length;
    $('#arq-selecao-rot').textContent = ids.length === 1 ? 'arquivamento selecionado' : 'arquivamentos selecionados';
  }

  /* ================================================================= *
   * Detalhe
   * ================================================================= */
  var atoAtual = null;

  function abrirDetalhe(id) {
    var dlg = $('#arq-dlg-detalhe');
    $('#arq-detalhe-corpo').innerHTML = '<div class="arq-esqueleto" style="height:280px"></div>';
    $('#arq-detalhe-titulo').textContent = 'Carregando…';
    $('#arq-detalhe-num').textContent = 'Nº ' + id;
    abrirDialogo(dlg);

    pegar('api/obter.php?id=' + encodeURIComponent(id)).then(function (d) {
      atoAtual = d.ato;
      renderizarDetalhe(d.ato);
    }).catch(function (e) {
      $('#arq-detalhe-corpo').innerHTML = '<div class="arq-nota arq-nota-perigo"><i class="fa fa-exclamation-circle"></i><div>' + esc(e.message) + '</div></div>';
    });
  }

  function renderizarDetalhe(a) {
    $('#arq-detalhe-titulo').textContent = a.categoria || 'Arquivamento';
    $('#arq-detalhe-num').textContent = 'Nº ' + a.id;
    $('#arq-detalhe-editar').setAttribute('href', 'cadastro.php?id=' + encodeURIComponent(a.id));
    $('#arq-detalhe-capa').setAttribute('href', 'capa_arquivamento.php?id=' + encodeURIComponent(a.id));
    $('#arq-detalhe-compilar').dataset.compilar = a.id;

    var dados = [
      ['Atribuição', a.atribuicao], ['Categoria', a.categoria], ['Data do ato', fmtData(a.data_ato)],
      ['Livro', a.livro], ['Folha', a.folha], ['Termo/Ordem', a.termo],
      ['Protocolo', a.protocolo], ['Matrícula', a.matricula]
    ].filter(function (d) { return d[1] !== '' && d[1] != null; });

    var html = '<div class="arq-detalhe"><div>';

    html += '<div class="arq-secao"><h3>Identificação</h3><div class="arq-dados">' +
      dados.map(function (d) {
        var mono = ['Livro', 'Folha', 'Termo/Ordem', 'Protocolo', 'Matrícula'].indexOf(d[0]) >= 0;
        return '<div class="arq-dado"><em>' + esc(d[0]) + '</em><b' + (mono ? ' class="arq-mono"' : '') + '>' + esc(d[1]) + '</b></div>';
      }).join('') + '</div></div>';

    html += '<div class="arq-secao"><h3>Partes envolvidas</h3><ul class="arq-lista-partes">' +
      (a.partes_envolvidas.length
        ? a.partes_envolvidas.map(function (p) {
            return '<li><span class="arq-cpf">' + esc(fmtDoc(p.cpf) || '—') + '</span>' +
                   '<span style="flex:1">' + esc(p.nome) + (p.papel ? ' <em style="color:var(--arq-suave);font-size:.8rem">· ' + esc(p.papel) + '</em>' : '') + '</span></li>';
          }).join('')
        : '<li style="color:var(--arq-suave)">Nenhuma parte registrada.</li>') +
      '</ul></div>';

    if (a.descricao) {
      html += '<div class="arq-secao"><h3>Descrição</h3><p style="font-size:.9rem;line-height:1.6;margin:0;white-space:pre-wrap">' +
              esc(a.descricao) + '</p></div>';
    }

    if (a.selos && a.selos.length) {
      html += '<div class="arq-secao"><h3>Selos digitais</h3><div class="arq-selos">' +
        a.selos.map(function (s) {
          return '<div class="arq-selo">' +
            (s.qr_code ? '<img src="data:image/png;base64,' + esc(s.qr_code) + '" alt="QR Code do selo">' : '') +
            '<div style="min-width:0"><div class="arq-selo-num">' + esc(s.numero_selo) +
            ' <button class="arq-btn arq-btn-sm arq-btn-ic" data-copiar="' + esc(s.numero_selo) + '" title="Copiar número"><i class="fa fa-clone"></i></button></div>' +
            '<div class="arq-selo-txt">' + esc(String(s.texto_selo || '').slice(0, 320)) + '</div></div></div>';
        }).join('') + '</div></div>';
    }

    html += '<div class="arq-secao"><h3>Histórico</h3><ul class="arq-linha-tempo">';
    html += '<li><b>Cadastrado por ' + esc(a.cadastrado_por || '—') + '</b><span>' + fmtDataHora(a.data_cadastro) + '</span></li>';
    (a.modificacoes || []).slice().reverse().slice(0, 12).forEach(function (m) {
      var rot = m.acao === 'restauracao' ? 'Restaurado' : (m.acao === 'cadastro' ? 'Cadastrado' : 'Alterado');
      html += '<li><b>' + rot + ' por ' + esc(m.usuario || '—') + '</b><span>' + fmtDataHora(m.data_hora) + '</span></li>';
    });
    html += '</ul></div>';

    /* --------- Coluna de anexos --------- */
    html += '</div><div>';
    html += '<div class="arq-secao"><h3>Documentos anexados (' + a.anexos.length + ')</h3>';

    if (!a.anexos.length) {
      html += '<div class="arq-nota arq-nota-alerta"><i class="fa fa-info-circle"></i>' +
              '<div>Este arquivamento não possui documentos digitalizados.</div></div>';
    } else {
      html += '<div class="arq-anexos">' + a.anexos.map(function (x) {
        var mini = ehImagem(x.ext)
          ? '<img src="' + esc(x.url) + '" alt="" loading="lazy">'
          : '<i class="fa ' + iconePorExt(x.ext) + '"></i>';
        var meta = esc(x.tamanho_legivel) +
          (x.origem === 'tarefa' ? ' · vindo de tarefa' : '') +
          (x.hash ? ' · <span class="arq-hash" title="SHA-256">' + esc(String(x.hash).slice(0, 12)) + '…</span>' : '');
        return '<div class="arq-anexo' + (x.disponivel ? '' : ' indisponivel') + '" data-ext="' + esc(x.ext) + '">' +
          '<div class="arq-anexo-ic" data-ver="' + esc(x.url) + '" data-ext="' + esc(x.ext) + '" data-nome="' + esc(x.nome) + '">' + mini + '</div>' +
          '<div class="arq-anexo-info" data-ver="' + esc(x.url) + '" data-ext="' + esc(x.ext) + '" data-nome="' + esc(x.nome) + '">' +
            '<div class="arq-anexo-nome">' + esc(x.nome) + '</div>' +
            '<div class="arq-anexo-meta">' + meta + (x.disponivel ? '' : ' · <b style="color:var(--arq-perigo)">arquivo ausente</b>') + '</div>' +
          '</div>' +
          '<div class="arq-anexo-acoes">' +
            '<a class="arq-btn arq-btn-sm arq-btn-ic" href="' + esc(x.url) + '&download=1" title="Baixar"><i class="fa fa-download"></i></a>' +
          '</div></div>';
      }).join('') + '</div>';

      html += '<button class="arq-btn arq-btn-p" style="margin-top:14px;width:100%" data-compilar="' + esc(a.id) + '">' +
              '<i class="fa fa-files-o"></i> Compilar documentos em um PDF</button>';
      html += '<a class="arq-btn" style="margin-top:8px;width:100%" href="compilar.php?formato=zip&id=' + esc(a.id) + '">' +
              '<i class="fa fa-file-archive-o"></i> Baixar originais em ZIP</a>';
    }
    html += '</div>';

    if (a.auditoria && a.auditoria.length) {
      html += '<div class="arq-secao"><h3>Trilha de acesso</h3><ul class="arq-linha-tempo">' +
        a.auditoria.slice(0, 8).map(function (ev) {
          var acoes = { ver: 'Consultou', baixar: 'Baixou', editar: 'Alterou', criar: 'Cadastrou', compilar: 'Compilou', excluir: 'Excluiu', restaurar: 'Restaurou' };
          var det = ev.detalhes && ev.detalhes.anexo ? ' — ' + esc(ev.detalhes.anexo) : '';
          return '<li><b>' + esc(acoes[ev.acao] || ev.acao) + ' · ' + esc(ev.nome || ev.usuario) + det + '</b>' +
                 '<span>' + fmtDataHora(ev.ts) + ' · ' + esc(ev.ip) + '</span></li>';
        }).join('') + '</ul></div>';
    }

    html += '</div></div>';
    $('#arq-detalhe-corpo').innerHTML = html;
  }

  /* ================================================================= *
   * Visualizador de anexo
   * ================================================================= */
  function verAnexo(url, ext, nome) {
    var dlg = $('#arq-dlg-visor');
    var palco = $('#arq-visor-palco');
    $('#arq-visor-titulo').textContent = nome || 'Documento';
    $('#arq-visor-baixar').setAttribute('href', url + '&download=1');
    $('#arq-visor-aba').setAttribute('href', url);

    if (String(ext).toLowerCase() === 'pdf') {
      var visor = CFG.pdfjs
        ? CFG.pdfjs + '?file=' + encodeURIComponent(new URL(url, location.href).href)
        : url;
      palco.innerHTML = '<iframe src="' + esc(visor) + '" title="Visualização do documento"></iframe>';
    } else if (ehImagem(ext)) {
      palco.innerHTML = '<img src="' + esc(url) + '" alt="' + esc(nome) + '">';
    } else {
      palco.innerHTML = '<div style="color:#fff;text-align:center;padding:40px">' +
        '<i class="fa ' + iconePorExt(ext) + '" style="font-size:3rem;opacity:.7"></i>' +
        '<p style="margin-top:14px">Este formato não abre no navegador. Use o botão Baixar.</p></div>';
    }
    abrirDialogo(dlg);
  }

  /* ================================================================= *
   * Bandeja de compilação
   * ================================================================= */
  var bandeja = { itens: [], ids: [] };

  function abrirCompilacao(ids) {
    ids = Array.isArray(ids) ? ids : [ids];
    bandeja = { itens: [], ids: ids };

    var dlg = $('#arq-dlg-compilar');
    $('#arq-pilha').innerHTML = '<div class="arq-esqueleto" style="height:160px"></div>';
    $('#arq-compilar-etapa').textContent = '';
    $('#arq-barra > div').style.width = '0';
    $('#arq-compilar-zip').setAttribute('href', 'compilar.php?formato=zip&ids=' + encodeURIComponent(ids.join(',')));
    abrirDialogo(dlg);

    if (!window.ArqCompilador || !ArqCompilador.disponivel()) {
      $('#arq-pilha').innerHTML = '<div class="arq-nota arq-nota-perigo"><i class="fa fa-exclamation-circle"></i>' +
        '<div>A biblioteca de PDF não carregou. Verifique se o arquivo <code>assets/vendor/pdf-lib.min.js</code> ' +
        'existe no servidor e recarregue a página.</div></div>';
      return;
    }

    ArqCompilador.manifesto(ids).then(function (m) {
      var itens = [];
      m.dossies.forEach(function (d) {
        d.anexos.forEach(function (a) {
          itens.push({
            id: d.id, indice: a.indice, nome: a.nome, ext: a.ext, mime: a.mime,
            url: a.url, tamanho_legivel: a.tamanho_legivel, hash: a.hash,
            compilavel: a.compilavel && a.disponivel, disponivel: a.disponivel,
            incluir: a.compilavel && a.disponivel,
            dossie: d.categoria + ' · ' + fmtData(d.data_ato)
          });
        });
      });
      bandeja.itens = itens;
      bandeja.usuario = m.usuario;
      renderizarPilha();
    }).catch(function (e) {
      $('#arq-pilha').innerHTML = '<div class="arq-nota arq-nota-perigo"><i class="fa fa-exclamation-circle"></i><div>' + esc(e.message) + '</div></div>';
    });
  }

  function renderizarPilha() {
    var lista = $('#arq-pilha');
    if (!bandeja.itens.length) {
      lista.innerHTML = '<div class="arq-nota arq-nota-alerta"><i class="fa fa-info-circle"></i>' +
        '<div>Não há documentos anexados nos arquivamentos selecionados.</div></div>';
      $('#arq-compilar-gerar').disabled = true;
      return;
    }

    var incluidos = bandeja.itens.filter(function (i) { return i.incluir; });
    $('#arq-compilar-gerar').disabled = incluidos.length === 0;
    $('#arq-compilar-resumo').textContent =
      incluidos.length + ' de ' + bandeja.itens.length + ' documento(s) entram no PDF';

    var dossieAnterior = null;
    var ordem = 0;
    var html = '';

    bandeja.itens.forEach(function (it, i) {
      if (bandeja.ids.length > 1 && it.id !== dossieAnterior) {
        dossieAnterior = it.id;
        html += '<li class="arq-grupo">Arquivamento ' + esc(it.id) + ' — ' + esc(it.dossie) + '</li>';
      }
      if (it.incluir) { ordem++; }
      html += '<li draggable="true" data-i="' + i + '" class="' + (it.incluir ? '' : 'fora') + '">' +
        '<i class="fa fa-bars arq-punho" aria-hidden="true"></i>' +
        '<input type="checkbox" data-incluir="' + i + '"' + (it.incluir ? ' checked' : '') +
        (it.compilavel ? '' : ' disabled') + ' aria-label="Incluir ' + esc(it.nome) + '">' +
        '<span class="arq-ordem">' + (it.incluir ? ordem : '—') + '</span>' +
        '<div class="arq-item-nome">' + esc(it.nome) +
          '<div class="arq-item-sub">' + esc(it.tamanho_legivel) +
          (it.compilavel ? '' : (it.disponivel ? ' · formato não entra no PDF (use o ZIP)' : ' · arquivo ausente no acervo')) +
          '</div></div>' +
        '<span class="arq-item-fls">' + esc(String(it.ext).toUpperCase()) + '</span>' +
        '</li>';
    });

    lista.innerHTML = html;
    ativarArrasto(lista);
  }

  function ativarArrasto(lista) {
    var origem = null;

    lista.addEventListener('dragstart', function (e) {
      var li = e.target.closest('li[data-i]');
      if (!li) { return; }
      origem = li;
      li.classList.add('arrastando');
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', li.dataset.i); } catch (err) {}
    });

    lista.addEventListener('dragover', function (e) {
      e.preventDefault();
      var li = e.target.closest('li[data-i]');
      $$('li.alvo', lista).forEach(function (x) { x.classList.remove('alvo'); });
      if (li && li !== origem) { li.classList.add('alvo'); }
    });

    lista.addEventListener('dragleave', function (e) {
      var li = e.target.closest('li[data-i]');
      if (li) { li.classList.remove('alvo'); }
    });

    lista.addEventListener('drop', function (e) {
      e.preventDefault();
      var destino = e.target.closest('li[data-i]');
      if (!origem || !destino || origem === destino) { return; }
      var de = parseInt(origem.dataset.i, 10);
      var para = parseInt(destino.dataset.i, 10);
      var movido = bandeja.itens.splice(de, 1)[0];
      bandeja.itens.splice(para, 0, movido);
      renderizarPilha();
    });

    lista.addEventListener('dragend', function () {
      if (origem) { origem.classList.remove('arrastando'); }
      $$('li.alvo', lista).forEach(function (x) { x.classList.remove('alvo'); });
      origem = null;
    });
  }

  function gerarPdf() {
    var incluidos = bandeja.itens.filter(function (i) { return i.incluir; });
    if (!incluidos.length) { return; }

    var btn = $('#arq-compilar-gerar');
    btn.disabled = true;
    $('#arq-compilar-fechar').disabled = true;

    ArqCompilador.compilar(incluidos, bandeja.ids, {
      csrf: CSRF,
      autor: CFG.usuario,
      carimbar: $('#arq-carimbar').checked,
      rodape: 'Atlas · Arquivamento ' + bandeja.ids.join(', ') + ' · ' + new Date().toLocaleDateString('pt-BR'),
      aoProgredir: function (pct, texto) {
        $('#arq-barra > div').style.width = pct + '%';
        $('#arq-compilar-etapa').textContent = texto;
      }
    }).then(function (r) {
      ArqCompilador.baixar(r.blob, r.nome);
      $('#arq-compilar-etapa').textContent = 'PDF gerado com ' + r.paginas + ' página(s).';
      if (r.falhas && r.falhas.length) {
        aviso('warning', 'PDF gerado com ressalvas',
          'Estes documentos ficaram de fora:\n\n' + r.falhas.join('\n'));
      } else {
        toast('success', 'Dossiê compilado');
      }
    }).catch(function (e) {
      $('#arq-compilar-etapa').textContent = '';
      $('#arq-barra > div').style.width = '0';
      aviso('error', 'Não foi possível compilar', e.message);
    }).then(function () {
      btn.disabled = false;
      $('#arq-compilar-fechar').disabled = false;
    });
  }

  /* ================================================================= *
   * Ações sobre registros
   * ================================================================= */
  function excluir(id) {
    confirmar('Mover para a lixeira?',
      'O arquivamento nº ' + id + ' sai do acervo ativo e fica ' + (CFG.retencao || 90) +
      ' dias na lixeira, de onde pode ser restaurado.', 'Mover para a lixeira', true)
      .then(function (ok) {
        if (!ok) { return; }
        return enviar('api/lixeira.php', { acao: 'excluir', id: id }).then(function () {
          toast('success', 'Movido para a lixeira');
          delete estado.selecao[id];
          buscar(true);
        });
      }).catch(function (e) { aviso('error', 'Erro', e.message); });
  }

  function exportarCsv() {
    var d = estado.ultimoResultado;
    if (!d || !d.itens.length) { return; }
    var ids = idsSelecionados();
    var itens = ids.length ? d.itens.filter(function (i) { return ids.indexOf(i.id) >= 0; }) : d.itens;

    var cab = ['Nº', 'Data do ato', 'Atribuição', 'Categoria', 'Livro', 'Folha', 'Termo', 'Protocolo', 'Matrícula', 'Partes', 'Anexos', 'Cadastrado por'];
    function celula(v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; }
    var linhas = [cab.map(celula).join(';')];
    itens.forEach(function (i) {
      linhas.push([i.id, fmtData(i.data_ato), i.atribuicao, i.categoria, i.livro, i.folha,
        i.termo, i.protocolo, i.matricula, (i.partes || []).join(' | '), i.anexos_qtd, i.cadastrado_por]
        .map(celula).join(';'));
    });
    var blob = new Blob(['\ufeff' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'acervo-' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 3000);
  }

  /* ================================================================= *
   * Eventos
   * ================================================================= */
  function ligarEventos() {
    /* Busca livre com atraso */
    var t = null;
    $('#arq-q').addEventListener('input', function () {
      var v = this.value;
      $('#arq-q').nextElementSibling.style.display = v ? 'block' : 'none';
      clearTimeout(t);
      t = setTimeout(function () { estado.filtros.q = v.trim(); buscar(); }, 280);
    });
    $('#arq-q-limpar').addEventListener('click', function () {
      $('#arq-q').value = ''; this.style.display = 'none';
      estado.filtros.q = ''; buscar();
    });

    /* Período */
    $$('#arq-periodo .arq-fatia').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('#arq-periodo .arq-fatia').forEach(function (x) { x.setAttribute('aria-pressed', 'false'); });
        b.setAttribute('aria-pressed', 'true');
        estado.periodo = b.dataset.periodo;
        estado.filtros.data = '';
        buscar();
      });
    });

    /* Filtros avançados */
    $('#arq-alternar-filtros').addEventListener('click', function () {
      var box = $('#arq-filtros');
      var aberto = box.classList.toggle('aberto');
      this.classList.toggle('ativo', aberto);
      this.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });

    $$('#arq-filtros [data-filtro]').forEach(function (campo) {
      var evento = campo.tagName === 'SELECT' || campo.type === 'date' ? 'change' : 'input';
      var atraso = evento === 'input' ? 320 : 0;
      var tt = null;
      campo.addEventListener(evento, function () {
        clearTimeout(tt);
        tt = setTimeout(function () {
          estado.filtros[campo.dataset.filtro] = campo.value.trim();
          buscar();
        }, atraso);
      });
    });

    $('#arq-ordenar').addEventListener('change', function () {
      var v = this.value.split(':');
      estado.filtros.ordenar = v[0];
      estado.filtros.direcao = v[1];
      buscar();
    });

    /* Visão */
    $$('#arq-visao button').forEach(function (b) {
      b.addEventListener('click', function () {
        estado.visao = b.dataset.visao;
        localStorage.setItem('arq_visao', estado.visao);
        $$('#arq-visao button').forEach(function (x) {
          x.setAttribute('aria-pressed', x.dataset.visao === estado.visao ? 'true' : 'false');
        });
        if (estado.ultimoResultado) { renderizar(estado.ultimoResultado); }
      });
      b.setAttribute('aria-pressed', b.dataset.visao === estado.visao ? 'true' : 'false');
    });

    /* Delegação geral */
    document.addEventListener('click', function (e) {
      var el;

      if ((el = e.target.closest('[data-abrir]'))) { abrirDetalhe(el.dataset.abrir); return; }
      if ((el = e.target.closest('[data-editar]'))) { location.href = 'cadastro.php?id=' + encodeURIComponent(el.dataset.editar); return; }
      if ((el = e.target.closest('[data-excluir]'))) { excluir(el.dataset.excluir); return; }
      if ((el = e.target.closest('[data-compilar]'))) { abrirCompilacao(el.dataset.compilar); return; }
      if ((el = e.target.closest('[data-ver]'))) { verAnexo(el.dataset.ver, el.dataset.ext, el.dataset.nome); return; }

      if ((el = e.target.closest('[data-pag]'))) {
        estado.pagina = parseInt(el.dataset.pag, 10);
        buscar(true);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }

      if ((el = e.target.closest('[data-tirar]'))) {
        estado.filtros[el.dataset.tirar] = '';
        var campo = $('#arq-filtros [data-filtro="' + el.dataset.tirar + '"]');
        if (campo) { campo.value = ''; }
        if (el.dataset.tirar === 'q') { $('#arq-q').value = ''; }
        buscar();
        return;
      }

      if (e.target.closest('#arq-limpar-tudo')) {
        Object.keys(estado.filtros).forEach(function (k) {
          if (k !== 'ordenar' && k !== 'direcao') { estado.filtros[k] = ''; }
        });
        $$('#arq-filtros [data-filtro]').forEach(function (c) { c.value = ''; });
        $('#arq-q').value = '';
        $('#arq-q-limpar').style.display = 'none';
        buscar();
        return;
      }

      if ((el = e.target.closest('[data-copiar]'))) {
        var txt = el.dataset.copiar;
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(txt).then(function () { toast('success', 'Número copiado'); });
        } else {
          var ta = document.createElement('textarea');
          ta.value = txt; ta.style.position = 'fixed'; ta.style.left = '-9999px';
          document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); toast('success', 'Número copiado'); } catch (err) {}
          document.body.removeChild(ta);
        }
        return;
      }

      /* Ordenação pelo cabeçalho da tabela */
      if ((el = e.target.closest('th[data-ord]'))) {
        var campoOrd = el.dataset.ord;
        estado.filtros.direcao = (estado.filtros.ordenar === campoOrd && estado.filtros.direcao === 'desc') ? 'asc' : 'desc';
        estado.filtros.ordenar = campoOrd;
        buscar();
        return;
      }
    });

    /* Seleção */
    document.addEventListener('change', function (e) {
      if (e.target.classList.contains('arq-marcar')) {
        alternarSelecao(e.target.dataset.id, e.target.checked);
      }
      if (e.target.id === 'arq-marcar-todos') {
        $$('.arq-marcar').forEach(function (c) {
          c.checked = e.target.checked;
          alternarSelecao(c.dataset.id, e.target.checked);
        });
      }
      if (e.target.dataset && e.target.dataset.incluir != null) {
        bandeja.itens[parseInt(e.target.dataset.incluir, 10)].incluir = e.target.checked;
        renderizarPilha();
      }
    });

    /* Barra de seleção */
    $('#arq-sel-limpar').addEventListener('click', function () {
      estado.selecao = {};
      $$('.arq-marcar').forEach(function (c) { c.checked = false; });
      $$('.marcada').forEach(function (c) { c.classList.remove('marcada'); });
      atualizarBarraSelecao();
    });
    $('#arq-sel-todos').addEventListener('click', function () {
      estado.idsResultado.forEach(function (id) { estado.selecao[id] = true; });
      $$('.arq-marcar').forEach(function (c) { c.checked = true; });
      $$('.arq-ficha, tr[data-id]').forEach(function (c) { c.classList.add('marcada'); });
      atualizarBarraSelecao();
    });
    $('#arq-sel-compilar').addEventListener('click', function () {
      var ids = idsSelecionados();
      if (ids.length > 50) { aviso('warning', 'Muitos arquivamentos', 'Selecione no máximo 50 por compilação.'); return; }
      abrirCompilacao(ids);
    });
    $('#arq-sel-zip').addEventListener('click', function () {
      location.href = 'compilar.php?formato=zip&ids=' + encodeURIComponent(idsSelecionados().join(','));
    });
    $('#arq-sel-csv').addEventListener('click', exportarCsv);

    /* Compilação */
    $('#arq-compilar-gerar').addEventListener('click', gerarPdf);
    $('#arq-compilar-marcar').addEventListener('click', function () {
      var ligar = bandeja.itens.some(function (i) { return i.compilavel && !i.incluir; });
      bandeja.itens.forEach(function (i) { if (i.compilavel) { i.incluir = ligar; } });
      renderizarPilha();
    });

    /* Atalhos */
    document.addEventListener('keydown', function (e) {
      if (e.target.matches('input, textarea, select')) { return; }
      if (e.key === '/') { e.preventDefault(); $('#arq-q').focus(); }
      if (e.key === 'n' && !e.ctrlKey && !e.metaKey) { location.href = 'cadastro.php'; }
      if (e.key === 'f' && !e.ctrlKey && !e.metaKey) { $('#arq-alternar-filtros').click(); }
    });
  }

  /* ================================================================= *
   * Indicadores
   * ================================================================= */
  function carregarIndicadores() {
    pegar('api/estatisticas.php').then(function (d) {
      var s = d.estatisticas;
      $('#kpi-total').textContent = s.total.toLocaleString('pt-BR');
      $('#kpi-mes').textContent = s.mes.toLocaleString('pt-BR');
      $('#kpi-anexos').textContent = s.anexos.toLocaleString('pt-BR');
      $('#kpi-espaco').textContent = s.bytes_legivel;
      if (s.lixeira > 0) {
        var l = $('#arq-link-lixeira');
        if (l) { l.innerHTML = '<i class="fa fa-trash-o"></i> Lixeira (' + s.lixeira + ')'; }
      }
    }).catch(function () { /* indicadores são acessórios */ });
  }

  /* ================================================================= *
   * Início
   * ================================================================= */
  document.addEventListener('DOMContentLoaded', function () {
    // Categorias no filtro
    pegar('api/categorias.php?acao=listar').then(function (d) {
      var sel = $('#arq-f-categoria');
      d.categorias.forEach(function (c) {
        var o = document.createElement('option');
        o.value = c.nome;
        o.textContent = c.nome + (c.uso ? ' (' + c.uso + ')' : '');
        sel.appendChild(o);
      });
    }).catch(function () {});

    // Estado inicial vindo da URL (permite compartilhar links de busca)
    var params = new URLSearchParams(location.search);
    Object.keys(estado.filtros).forEach(function (k) {
      if (params.has(k)) {
        estado.filtros[k] = params.get(k);
        var campo = $('#arq-filtros [data-filtro="' + k + '"]');
        if (campo) { campo.value = estado.filtros[k]; }
      }
    });
    if (estado.filtros.q) {
      $('#arq-q').value = estado.filtros.q;
      $('#arq-q-limpar').style.display = 'block';
    }
    if (params.has('periodo')) { estado.periodo = params.get('periodo'); }
    $$('#arq-periodo .arq-fatia').forEach(function (b) {
      b.setAttribute('aria-pressed', b.dataset.periodo === estado.periodo ? 'true' : 'false');
    });

    ligarEventos();
    carregarIndicadores();
    buscar();

    if (params.has('abrir')) { abrirDetalhe(params.get('abrir')); }
  });
})();
