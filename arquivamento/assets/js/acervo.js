/**
 * Atlas · Arquivamento Digital — tela de acervo.
 *
 * Conversa com api/listar.php (filtro e paginação no servidor) e com
 * ArqCompilador (assets/js/compilador.js) para o dossiê em PDF único.
 */
(function ($) {
  'use strict';

  var CSRF = window.ARQ_CSRF;
  var pagina = 1;
  var itens = [];
  var selecionados = {};
  var visao = 'cards';
  var periodo = 'all';
  var customDe = null, customAte = null;

  /* Cores da barra lateral do card, por atribuição — como sempre foi. */
  var classeAtribuicao = {
    'Registro Civil': 'rc',
    'Registro de Imóveis': 'ri',
    'Registro de Títulos e Documentos': 'rtd',
    'Registro Civil das Pessoas Jurídicas': 'rcpj',
    'Notas': 'notas',
    'Protesto': 'protes',
    'Contratos Marítimos': 'cmar'
  };

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function fData(d) {
    if (!d) { return '-'; }
    var p = String(d).split('-');
    return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d;
  }

  function fDoc(v) {
    var d = String(v || '').replace(/\D/g, '');
    if (d.length === 11) { return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4'); }
    if (d.length === 14) { return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5'); }
    return v || '';
  }

  /* ---------- período ---------- */
  function limites() {
    var hoje = new Date(); hoje.setHours(0, 0, 0, 0);
    var iso = function (d) {
      return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    };
    var s;
    switch (periodo) {
      case 'today': return { de: iso(hoje), ate: iso(hoje) };
      case '7d':    s = new Date(hoje); s.setDate(s.getDate() - 6); return { de: iso(s), ate: iso(hoje) };
      case '30d':   s = new Date(hoje); s.setMonth(s.getMonth() - 1); s.setDate(s.getDate() + 1); return { de: iso(s), ate: iso(hoje) };
      case '365d':  s = new Date(hoje); s.setFullYear(s.getFullYear() - 1); s.setDate(s.getDate() + 1); return { de: iso(s), ate: iso(hoje) };
      case 'custom': return { de: customDe || '', ate: customAte || '' };
      default: return { de: '', ate: '' };
    }
  }

  function filtros() {
    var l = limites();
    return {
      atribuicao: $('#f-atribuicao').val() || '',
      categoria:  $('#f-categoria').val() || '',
      cpf:        $('#f-cpf').val() || '',
      nome:       $('#f-nome').val() || '',
      livro:      $('#f-livro').val() || '',
      folha:      $('#f-folha').val() || '',
      termo:      $('#f-termo').val() || '',
      protocolo:  $('#f-protocolo').val() || '',
      matricula:  $('#f-matricula').val() || '',
      descricao:  $('#f-descricao').val() || '',
      data_ato:   $('#f-data').val() || '',
      de: l.de, ate: l.ate,
      pagina: pagina
    };
  }

  /* ---------- chips de filtro ativo ---------- */
  var rotulos = {
    atribuicao: 'Atribuição', categoria: 'Categoria', cpf: 'CPF/CNPJ', nome: 'Nome',
    livro: 'Livro', folha: 'Folha', termo: 'Termo', protocolo: 'Protocolo',
    matricula: 'Matrícula', descricao: 'Descrição', data_ato: 'Data'
  };
  var campos = {
    atribuicao: '#f-atribuicao', categoria: '#f-categoria', cpf: '#f-cpf', nome: '#f-nome',
    livro: '#f-livro', folha: '#f-folha', termo: '#f-termo', protocolo: '#f-protocolo',
    matricula: '#f-matricula', descricao: '#f-descricao', data_ato: '#f-data'
  };

  function pintarChipsAtivos() {
    var f = filtros(), html = '';
    Object.keys(rotulos).forEach(function (k) {
      if (f[k]) {
        html += '<span class="arq-chip-filtro">' + esc(rotulos[k]) + ': ' + esc(f[k]) +
                ' <button data-tirar="' + k + '" title="Remover">&times;</button></span>';
      }
    });
    $('#filtros-ativos').html(html);
  }

  $(document).on('click', '[data-tirar]', function () {
    $(campos[$(this).data('tirar')]).val('');
    pagina = 1; carregar();
  });

  /* ---------- carregar ---------- */
  function carregar() {
    $('#cards-container').html(
      '<div class="col-12"><div class="arq-esqueleto" style="height:180px"></div></div>');

    $.get('api/listar.php', filtros(), function (r) {
      if (!r.ok) {
        Swal.fire({ icon: 'error', title: 'Erro na consulta', text: r.erro || '' });
        return;
      }
      itens = r.itens;
      pintarChipsAtivos();
      $('#resumo').text('— ' + r.total + ' registro(s), ' + r.resumo.anexos +
                        ' anexo(s), ' + r.resumo.bytes_legivel);
      if (visao === 'cards') { pintarCards(); } else { pintarTabela(); }
      pintarPaginacao(r.pagina, r.paginas);
      atualizarBarra();
    }, 'json').fail(function (x) {
      if (x.status === 401) { window.location = '../login.php'; return; }
      Swal.fire({ icon: 'error', title: 'Falha de rede', text: 'Não foi possível consultar o acervo.' });
    });
  }

  /* ---------- cards ---------- */
  function pintarCards() {
    var c = $('#cards-container').empty();
    $('#tabela-container').hide();
    $('#cards-container').show();

    if (!itens.length) {
      c.html('<div class="col-12"><div class="arq-vazio"><i class="fa fa-archive"></i>' +
             '<h3>Nenhum arquivamento encontrado</h3>' +
             '<p>Ajuste os filtros ou amplie o período da busca.</p></div></div>');
      return;
    }

    itens.forEach(function (a) {
      var nomes = (a.partes || []).join(', ');
      var cls = classeAtribuicao[a.atribuicao] || '';
      var marcado = selecionados[a.id] ? 'checked' : '';
      c.append(
        '<div class="col-12 col-md-6 col-xl-4 mb-3 col-card">' +
          '<div class="card card-ato shadow-sm ' + cls + '" data-id="' + esc(a.id) + '">' +
            '<div class="card-body">' +
              '<div class="title-wrap">' +
                '<div>' +
                  '<div class="badge-soft mb-2"><i class="fa fa-hashtag"></i>' + esc(a.atribuicao) + '</div>' +
                  '<h5 class="mb-1" style="font-weight:800">' + esc(a.categoria || '—') + '</h5>' +
                '</div>' +
                '<label class="mb-0" title="Selecionar" onclick="event.stopPropagation()">' +
                  '<input type="checkbox" class="sel-ato" data-id="' + esc(a.id) + '" ' + marcado + '>' +
                '</label>' +
              '</div>' +
              '<div class="name-block">' +
                '<label class="name-label">Partes:</label>' +
                '<textarea class="name-area" readonly onclick="event.stopPropagation()">' + esc(nomes || '-') + '</textarea>' +
              '</div>' +
              '<p class="mb-1"><strong>Data:</strong> ' + fData(a.data_ato) + '</p>' +
              '<p class="mb-2"><strong>Livro/Folha/Termo/Matricula:</strong> ' +
                esc(a.livro || '-') + ' / ' + esc(a.folha || '-') + ' / ' +
                esc(a.termo || '-') + ' / ' + esc(a.matricula || '-') + '</p>' +
              '<div class="mt-auto pt-2">' +
                '<button class="btn btn-warning btn-sm editar-ato" data-id="' + esc(a.id) + '" title="Editar"><i class="fa fa-pencil"></i></button>' +
                '<button class="btn btn-danger btn-sm excluir-ato" data-id="' + esc(a.id) + '" title="Enviar para a lixeira"><i class="fa fa-trash"></i></button>' +
                '<a class="btn btn-oficio btn-sm" href="capa_arquivamento.php?id=' + encodeURIComponent(a.id) + '" target="_blank" title="Capa" onclick="event.stopPropagation()"><i class="fa fa-file-pdf-o"></i></a>' +
                (a.selos ? '<span class="badge-soft ml-2"><i class="fa fa-certificate"></i>' + a.selos + ' selo(s)</span>' : '') +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>');
    });
  }

  /* ---------- tabela ---------- */
  function pintarTabela() {
    $('#cards-container').hide();
    var $t = $('#tabela-container').show();

    if (!itens.length) {
      $t.html('<div class="arq-vazio"><i class="fa fa-archive"></i>' +
              '<h3>Nenhum arquivamento encontrado</h3></div>');
      return;
    }

    var linhas = itens.map(function (a) {
      return '<tr data-id="' + esc(a.id) + '">' +
        '<td onclick="event.stopPropagation()"><input type="checkbox" class="sel-ato" data-id="' + esc(a.id) + '" ' +
          (selecionados[a.id] ? 'checked' : '') + '></td>' +
        '<td class="arq-cod">' + esc(a.id) + '</td>' +
        '<td>' + esc(a.atribuicao) + '</td>' +
        '<td>' + esc(a.categoria || '—') + '</td>' +
        '<td>' + fData(a.data_ato) + '</td>' +
        '<td class="arq-cod">' + esc(a.livro || '-') + '/' + esc(a.folha || '-') + '</td>' +
        '<td>' + esc((a.partes || []).join(', ')) + '</td>' +
        '<td class="text-center">' + a.anexos_qtd + '</td>' +
      '</tr>';
    }).join('');

    $t.html(
      '<div class="table-responsive"><table class="table table-striped arq-tabela-acervo">' +
        '<thead><tr><th style="width:36px"></th><th>Nº</th><th>Atribuição</th><th>Categoria</th>' +
        '<th>Data</th><th>Livro/Folha</th><th>Partes</th><th class="text-center">Anexos</th></tr></thead>' +
        '<tbody>' + linhas + '</tbody></table></div>');
  }

  function pintarPaginacao(atual, total) {
    if (total <= 1) { $('#paginacao').empty(); return; }
    var h = '<nav><ul class="pagination pagination-sm mb-0">';
    for (var i = 1; i <= total; i++) {
      if (i === 1 || i === total || Math.abs(i - atual) <= 2) {
        h += '<li class="page-item ' + (i === atual ? 'active' : '') + '">' +
             '<a class="page-link" href="#" data-pag="' + i + '">' + i + '</a></li>';
      } else if (Math.abs(i - atual) === 3) {
        h += '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }
    }
    $('#paginacao').html(h + '</ul></nav>');
  }

  $(document).on('click', '[data-pag]', function (e) {
    e.preventDefault();
    pagina = +$(this).data('pag');
    carregar();
    $('html,body').animate({ scrollTop: $('#cards-container').offset().top - 90 }, 200);
  });

  /* ---------- seleção ---------- */
  function atualizarBarra() {
    var n = Object.keys(selecionados).length;
    $('#sel-qtd').text(n);
    $('#barra-selecao').toggleClass('visivel', n > 0);
  }

  $(document).on('change', '.sel-ato', function () {
    var id = String($(this).data('id'));
    if (this.checked) { selecionados[id] = true; } else { delete selecionados[id]; }
    atualizarBarra();
  });

  $('#btn-limpar-sel').on('click', function () {
    selecionados = {};
    $('.sel-ato').prop('checked', false);
    atualizarBarra();
  });

  /* ---------- detalhe ---------- */
  $(document).on('click', '.card-ato, .arq-tabela-acervo tbody tr', function (e) {
    if ($(e.target).closest('button, a, input, textarea, label').length) { return; }
    abrirDetalhe($(this).data('id'));
  });

  function abrirDetalhe(id) {
    $('#modal-corpo').html('<div class="arq-esqueleto" style="height:220px"></div>');
    $('#modal-titulo').text('Arquivamento ' + id);
    $('#anexosModal').modal('show');

    $.get('api/obter.php', { id: id }, function (r) {
      if (!r.ok) { $('#modal-corpo').html('<p class="text-danger">' + esc(r.erro) + '</p>'); return; }
      var a = r.ato;

      var partes = a.partes_envolvidas.map(function (p) {
        return '<li>' + esc(p.nome) + (p.cpf ? ' <span class="muted">(' + fDoc(p.cpf) + ')</span>' : '') +
               (p.papel ? ' — ' + esc(p.papel) : '') + '</li>';
      }).join('') || '<li class="muted">Nenhuma parte informada.</li>';

      var anexos = a.anexos.map(function (x) {
        return '<div class="anexo-item">' +
          '<span class="anexo-nome">' +
            (x.disponivel
              ? '<a href="' + x.url + '" target="_blank">' + esc(x.nome) + '</a>'
              : esc(x.nome) + ' <span class="badge badge-warning">ausente</span>') +
            '<small class="muted d-block">' + esc(x.tamanho_legivel) + '</small></span>' +
          '</div>';
      }).join('') || '<p class="muted">Sem anexos.</p>';

      var selos = (a.selos || []).map(function (s) {
        return '<div class="seal-wrapper" style="margin-bottom:10px"><div class="seal-card">' +
          '<div class="seal-head"><div class="seal-title">Poder Judiciário – TJMA</div>' +
          '<span class="seal-pill"><i class="fa fa-check-circle"></i> Selo gerado</span></div>' +
          '<div class="seal-grid"><div class="seal-qr">' +
          (s.qr_code ? '<img src="data:image/png;base64,' + s.qr_code + '" alt="QR">' : '') +
          '</div><div class="seal-meta"><div class="seal-number">Selo: <b>' + esc(s.numero_selo) + '</b></div>' +
          '<p class="seal-text">' + esc(s.texto_selo) + '</p></div></div></div></div>';
      }).join('');

      var linha = function (r0, v0) {
        return v0 ? '<p class="mb-1"><strong>' + r0 + ':</strong> ' + esc(v0) + '</p>' : '';
      };

      $('#modal-corpo').html(
        '<div class="row"><div class="col-12 col-lg-6">' +
          '<div class="badge-soft mb-2"><i class="fa fa-hashtag"></i>' + esc(a.atribuicao) + '</div>' +
          linha('Categoria', a.categoria) +
          linha('Data do ato', fData(a.data_ato)) +
          linha('Livro', a.livro) + linha('Folha', a.folha) + linha('Termo', a.termo) +
          linha('Protocolo', a.protocolo) + linha('Matrícula', a.matricula) +
          (a.descricao ? '<p class="mt-2"><strong>Descrição:</strong><br>' + esc(a.descricao) + '</p>' : '') +
          '<p class="mt-2 mb-1"><strong>Partes:</strong></p><ul>' + partes + '</ul>' +
        '</div><div class="col-12 col-lg-6">' +
          '<p class="mb-1"><strong>Documentos:</strong></p>' + anexos +
          (selos ? '<p class="mb-1 mt-3"><strong>Selos:</strong></p>' + selos : '') +
          '<div class="mt-3">' +
            '<a class="btn btn-warning btn-sm" href="cadastro.php?id=' + encodeURIComponent(a.id) + '"><i class="fa fa-pencil"></i> Editar</a> ' +
            '<a class="btn btn-oficio btn-sm" href="capa_arquivamento.php?id=' + encodeURIComponent(a.id) + '" target="_blank"><i class="fa fa-file-pdf-o"></i> Capa</a> ' +
            '<button class="btn btn-primary btn-sm" onclick="ArqAcervo.compilar([\'' + a.id + '\'])"><i class="fa fa-files-o"></i> Compilar dossiê</button>' +
          '</div>' +
        '</div></div>');
    }, 'json');
  }

  /* Busca dentro do modal, com destaque. */
  $('#modal-busca').on('input', function () {
    var termo = this.value.trim();
    var $c = $('#modal-corpo');
    $c.find('mark.mark-search').each(function () { $(this).replaceWith(this.textContent); });
    if (termo.length < 2) { return; }
    var re = new RegExp('(' + termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    $c.find('p, li, h5, span.anexo-nome').each(function () {
      var el = $(this);
      if (el.children().length) { return; }
      el.html(el.text().replace(re, '<mark class="mark-search">$1</mark>'));
    });
  });

  /* ---------- ações ---------- */
  $(document).on('click', '.editar-ato', function (e) {
    e.stopPropagation();
    window.location = 'cadastro.php?id=' + encodeURIComponent($(this).data('id'));
  });

  $(document).on('click', '.excluir-ato', function (e) {
    e.stopPropagation();
    var id = $(this).data('id');
    Swal.fire({
      icon: 'warning',
      title: 'Enviar para a lixeira?',
      text: 'O arquivamento nº ' + id + ' poderá ser restaurado depois.',
      input: 'text',
      inputPlaceholder: 'Motivo (opcional)',
      showCancelButton: true,
      confirmButtonText: 'Enviar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true
    }).then(function (res) {
      if (!res.isConfirmed) { return; }
      $.ajax({
        url: 'api/lixeira.php', type: 'POST',
        headers: { 'X-CSRF-Token': CSRF },
        data: { acao: 'excluir', id: id, motivo: res.value || '' },
        dataType: 'json'
      }).done(function (r) {
        if (r.ok) {
          Swal.fire({ icon: 'success', title: 'Enviado para a lixeira', timer: 1200, showConfirmButton: false });
          delete selecionados[id];
          carregar();
        } else {
          Swal.fire({ icon: 'error', title: 'Erro', text: r.erro || '' });
        }
      });
    });
  });

  /* ---------- compilação ---------- */
  var bandeja = [];

  function compilar(ids) {
    $.get('compilar.php', { formato: 'manifesto', ids: ids.join(',') }, function (r) {
      if (!r.ok) { Swal.fire({ icon: 'error', title: 'Erro', text: r.erro || '' }); return; }

      bandeja = [];
      r.dossies.forEach(function (d) {
        d.anexos.forEach(function (a) {
          if (a.compilavel) { bandeja.push({ id: d.id, indice: a.indice, nome: a.nome, ext: a.ext,
                                             tam: a.tamanho_legivel, url: a.url }); }
        });
      });

      if (!bandeja.length) {
        Swal.fire({ icon: 'info', title: 'Nada a compilar',
          text: 'Nenhum dos anexos selecionados pode ser incorporado a um PDF. Use o download em ZIP.' });
        return;
      }

      pintarBandeja();
      $('#compilarModal').data('ids', ids).modal('show');
    }, 'json');
  }

  function pintarBandeja() {
    $('#bandeja').html(bandeja.map(function (b, i) {
      return '<div class="arq-bandeja-item" draggable="true" data-i="' + i + '">' +
        '<span class="puxador"><i class="fa fa-bars"></i></span>' +
        '<span class="nome">' + esc(b.nome) + '</span>' +
        '<span class="tam">' + esc(b.tam) + '</span></div>';
    }).join(''));
  }

  var arrastando = null;
  $(document).on('dragstart', '.arq-bandeja-item', function () {
    arrastando = +$(this).data('i'); $(this).addClass('arrastando');
  });
  $(document).on('dragend', '.arq-bandeja-item', function () { $(this).removeClass('arrastando'); });
  $(document).on('dragover', '.arq-bandeja-item', function (e) { e.preventDefault(); });
  $(document).on('drop', '.arq-bandeja-item', function (e) {
    e.preventDefault();
    var destino = +$(this).data('i');
    if (arrastando === null || arrastando === destino) { return; }
    var item = bandeja.splice(arrastando, 1)[0];
    bandeja.splice(destino, 0, item);
    arrastando = null;
    pintarBandeja();
  });

  $('#btn-gerar-pdf').on('click', function () {
    var $b = $(this).prop('disabled', true);
    $('#prog-caixa').show();

    var ids = $('#compilarModal').data('ids');

    ArqCompilador.compilar(bandeja, ids, {
      csrf: CSRF,
      aoProgredir: function (pct, texto) {
        $('#prog').css('width', pct + '%');
        $b.html('<i class="fa fa-spinner fa-spin"></i> ' + (texto || 'Compilando…'));
      }
    }).then(function (r) {
      ArqCompilador.baixar(r.blob, r.nome || ('dossie-' + ids.join('-') + '.pdf'));
      $('#compilarModal').modal('hide');
      if (r.falhas && r.falhas.length) {
        Swal.fire({ icon: 'warning', title: 'Dossiê gerado com ressalvas',
          html: 'Não entraram no PDF:<br>' + esc(r.falhas.join('<br>')) });
      }
    }).catch(function (err) {
      Swal.fire({ icon: 'error', title: 'Falha ao compilar', text: err.message || String(err) });
    }).then(function () {
      $b.prop('disabled', false).html('<i class="fa fa-download"></i> Gerar PDF único');
      $('#prog-caixa').hide(); $('#prog').css('width', 0);
    });
  });

  $('#btn-compilar').on('click', function () { compilar(Object.keys(selecionados)); });

  $('#btn-zip').on('click', function () {
    window.location = 'compilar.php?formato=zip&ids=' + encodeURIComponent(Object.keys(selecionados).join(','));
  });

  $('#btn-csv').on('click', function () {
    var sel = itens.filter(function (a) { return selecionados[a.id]; });
    var linhas = [['Numero','Atribuicao','Categoria','Data','Livro','Folha','Termo','Protocolo','Matricula','Partes','Anexos']];
    sel.forEach(function (a) {
      linhas.push([a.id, a.atribuicao, a.categoria, a.data_ato, a.livro, a.folha,
                   a.termo, a.protocolo, a.matricula, (a.partes || []).join(' | '), a.anexos_qtd]);
    });
    var csv = '\ufeff' + linhas.map(function (l) {
      return l.map(function (c) { return '"' + String(c == null ? '' : c).replace(/"/g, '""') + '"'; }).join(';');
    }).join('\r\n');
    ArqCompilador.baixar(new Blob([csv], { type: 'text/csv;charset=utf-8' }), 'arquivamentos.csv');
  });

  /* ---------- filtros e visão ---------- */
  $('#filter-button').on('click', function () { pagina = 1; carregar(); });

  $('#dateChips').on('click', '.chip', function () {
    periodo = $(this).data('range');
    $('#dateChips .chip').removeClass('active');
    $(this).addClass('active');
    $('#custom-range').toggle(periodo === 'custom');
    if (periodo !== 'custom') { pagina = 1; carregar(); }
  });

  $('#apply-custom').on('click', function () {
    customDe = $('#custom-from').val();
    customAte = $('#custom-to').val();
    pagina = 1; carregar();
  });

  $('.filter-card').on('keydown', 'input', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); pagina = 1; carregar(); }
  });

  function trocarVisao(v) {
    visao = v;
    $('#v-cards').toggleClass('btn-primary', v === 'cards').toggleClass('btn-secondary', v !== 'cards');
    $('#v-tabela').toggleClass('btn-primary', v === 'tabela').toggleClass('btn-secondary', v !== 'tabela');
    try { localStorage.setItem('arq_visao', v); } catch (e) {}
    if (v === 'cards') { pintarCards(); } else { pintarTabela(); }
  }
  $('#v-cards').on('click', function () { trocarVisao('cards'); });
  $('#v-tabela').on('click', function () { trocarVisao('tabela'); });

  try {
    var salva = localStorage.getItem('arq_visao');
    if (salva === 'tabela') { visao = 'tabela'; }
  } catch (e) {}

  $('#custom-range').hide();
  trocarVisao(visao);
  carregar();

  window.ArqAcervo = { compilar: compilar, recarregar: carregar };

})(jQuery);
