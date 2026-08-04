/* =============================================================================
   ATLAS - MODULO DE OFICIOS
   busca_avancada.js - Central de busca inteligente (vanilla JS, sem CDN)
   -----------------------------------------------------------------------------
   Depende apenas de:
     - buscar_oficios.php    (resultados + facetas)
     - busca_sugestoes.php   (autocomplete)
     - exportar_oficios.php  (CSV)
     - SweetAlert2 (Swal) ja carregado pelo index.php
   As funcoes de acao (viewOficio, editOficio, viewAttachments, assinarOficio)
   continuam definidas no index.php.
============================================================================= */

var AtlasBusca = (function () {
    'use strict';

    /* =========================================================================
       ESTADO
    ========================================================================= */
    var estado = {
        q: '',
        modo: 'e',
        numero: '',
        assunto: '',
        destinatario: '',
        assinante: '',
        cargo: '',
        dados_complementares: '',
        corpo: '',
        data_ini: '',
        data_fim: '',
        periodo: '',
        assinado: '',
        travado: '',
        anexo: '',
        buscar_corpo: 0,
        ordem: 'relevancia',
        dir: 'desc',
        pagina: 1,
        por_pagina: 25
    };

    var CHAVE_SALVAS   = 'atlas_oficios_buscas_salvas';
    var CHAVE_HISTORICO = 'atlas_oficios_historico';

    var el = {};              // cache de elementos
    var timerBusca = null;
    var timerSugestoes = null;
    var requisicaoAtual = 0;  // descarta respostas fora de ordem
    var termosDestaque = [];
    var sugestaoIndice = -1;
    var sugestoesAtuais = [];

    /* =========================================================================
       UTILITARIOS
    ========================================================================= */
    function $(id) { return document.getElementById(id); }

    function escapar(txt) {
        if (txt === null || txt === undefined) return '';
        return String(txt)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function semAcento(s) {
        if (!s) return '';
        if (String.prototype.normalize) {
            return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return String(s);
    }

    function escaparRegex(s) {
        return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Destaca os termos buscados dentro do texto, ignorando acentos e caixa.
     * Trabalha sobre o texto ja escapado para nunca injetar HTML.
     */
    function destacar(texto) {
        var seguro = escapar(texto);
        if (!termosDestaque.length || !seguro) return seguro;

        var alvo = semAcento(seguro).toLowerCase();
        var marcas = [];

        termosDestaque.forEach(function (termo) {
            if (!termo || termo.length < 2) return;
            var t = semAcento(termo).toLowerCase();
            var re;
            try {
                re = new RegExp(escaparRegex(t), 'g');
            } catch (e) { return; }
            var m;
            while ((m = re.exec(alvo)) !== null) {
                marcas.push([m.index, m.index + t.length]);
                if (re.lastIndex === m.index) re.lastIndex++;
            }
        });

        if (!marcas.length) return seguro;

        // Une intervalos sobrepostos
        marcas.sort(function (a, b) { return a[0] - b[0]; });
        var unidas = [marcas[0]];
        for (var i = 1; i < marcas.length; i++) {
            var ultima = unidas[unidas.length - 1];
            if (marcas[i][0] <= ultima[1]) {
                ultima[1] = Math.max(ultima[1], marcas[i][1]);
            } else {
                unidas.push(marcas[i]);
            }
        }

        // Nao quebra entidades HTML (&amp; etc.): descarta marcas que caiam dentro delas
        var resultado = '';
        var pos = 0;
        unidas.forEach(function (par) {
            var trecho = seguro.substring(par[0], par[1]);
            if (trecho.indexOf('&') !== -1 || trecho.indexOf(';') !== -1 ||
                trecho.indexOf('<') !== -1 || trecho.indexOf('>') !== -1) {
                return;
            }
            if (par[0] < pos) return;
            resultado += seguro.substring(pos, par[0]);
            resultado += '<mark class="hl">' + trecho + '</mark>';
            pos = par[1];
        });
        resultado += seguro.substring(pos);
        return resultado;
    }

    function truncar(txt, limite) {
        if (!txt) return '';
        txt = String(txt);
        return txt.length > limite ? txt.substring(0, limite) + '...' : txt;
    }

    /** Remove tags do HTML armazenado no corpo/complementos. */
    function semTags(html) {
        if (!html) return '';
        var tmp = document.createElement('div');
        tmp.innerHTML = String(html);
        return (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
    }

    function paramsAtivos() {
        var p = {};
        Object.keys(estado).forEach(function (k) {
            var v = estado[k];
            if (v === '' || v === null || v === undefined) return;
            if (k === 'buscar_corpo' && !v) return;
            if (k === 'pagina' && v === 1) return;
            p[k] = v;
        });
        return p;
    }

    function queryString(extra) {
        var p = paramsAtivos();
        if (extra) {
            Object.keys(extra).forEach(function (k) { p[k] = extra[k]; });
        }
        var partes = [];
        Object.keys(p).forEach(function (k) {
            partes.push(encodeURIComponent(k) + '=' + encodeURIComponent(p[k]));
        });
        return partes.join('&');
    }

    function temAlgumFiltro() {
        return !!(estado.q || estado.numero || estado.assunto || estado.destinatario ||
                  estado.assinante || estado.cargo || estado.dados_complementares ||
                  estado.corpo || estado.data_ini || estado.data_fim ||
                  estado.assinado !== '' || estado.travado !== '' || estado.anexo !== '');
    }

    /* =========================================================================
       SINCRONIZACAO COM A URL (permite compartilhar/marcar a pesquisa)
    ========================================================================= */
    function lerUrl() {
        var busca = window.location.search.replace(/^\?/, '');
        if (!busca) return;
        busca.split('&').forEach(function (par) {
            if (!par) return;
            var idx = par.indexOf('=');
            var chave = decodeURIComponent(idx < 0 ? par : par.substring(0, idx));
            var valor = idx < 0 ? '' : decodeURIComponent(par.substring(idx + 1).replace(/\+/g, ' '));

            // Compatibilidade com os links antigos do modulo
            if (chave === 'data' && valor) {
                estado.data_ini = valor;
                estado.data_fim = valor;
                return;
            }
            if (Object.prototype.hasOwnProperty.call(estado, chave)) {
                if (chave === 'pagina' || chave === 'por_pagina' || chave === 'buscar_corpo') {
                    estado[chave] = parseInt(valor, 10) || estado[chave];
                } else {
                    estado[chave] = valor;
                }
            }
        });
    }

    function gravarUrl() {
        if (!window.history || !window.history.replaceState) return;
        var qs = queryString();
        var url = window.location.pathname + (qs ? '?' + qs : '');
        window.history.replaceState(null, '', url);
    }

    /* =========================================================================
       BUSCA (AJAX)
    ========================================================================= */
    function agendarBusca(atraso) {
        clearTimeout(timerBusca);
        timerBusca = setTimeout(function () {
            estado.pagina = 1;
            buscar();
        }, atraso === undefined ? 350 : atraso);
    }

    function buscar(manterPagina) {
        if (!manterPagina) { /* pagina definida por quem chamou */ }

        var meuId = ++requisicaoAtual;
        mostrarCarregando(true);
        gravarUrl();

        var url = 'buscar_oficios.php?facetas=1&' + queryString();

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            if (meuId !== requisicaoAtual) return; // resposta obsoleta

            mostrarCarregando(false);

            if (xhr.status < 200 || xhr.status >= 300) {
                renderErro('Nao foi possivel consultar os oficios. Verifique a conexao com o servidor.');
                return;
            }

            var dados;
            try {
                dados = JSON.parse(xhr.responseText);
            } catch (e) {
                renderErro('Resposta invalida do servidor.');
                return;
            }

            if (!dados.ok) {
                renderErro(dados.erro || 'Falha na consulta.');
                return;
            }

            termosDestaque = dados.termos || [];
            // Tambem destaca o conteudo dos campos avancados
            ['numero', 'assunto', 'destinatario', 'assinante', 'cargo', 'dados_complementares']
                .forEach(function (c) {
                    if (estado[c]) termosDestaque.push(estado[c]);
                });

            renderResultados(dados);
            renderChipsAtivos(dados.chips || []);
            renderFacetas(dados.facetas || {});
            renderPaginacao(dados);
            atualizarChipsPeriodo();
            renderAvisoOperador(dados.nao_reconhecidos || []);
        };
        xhr.onerror = function () {
            if (meuId !== requisicaoAtual) return;
            mostrarCarregando(false);
            renderErro('Erro de rede ao consultar os oficios.');
        };
        xhr.send();
    }

    function mostrarCarregando(ativo) {
        if (el.carregando) el.carregando.classList.toggle('ativo', !!ativo);
        if (el.tabelaWrap) el.tabelaWrap.style.opacity = ativo ? '.45' : '1';
    }

    /* =========================================================================
       RENDERIZACAO DA TABELA
    ========================================================================= */
    function renderResultados(dados) {
        var tbody = el.tbody;
        if (!tbody) return;

        if (!dados.linhas.length) {
            tbody.innerHTML = '';
            el.vazio.style.display = 'block';
            el.vazio.innerHTML = dados.tem_filtro
                ? '<i class="fa fa-search"></i>' +
                  '<h5>Nenhum oficio encontrado</h5>' +
                  '<p>Nenhum registro corresponde aos filtros aplicados.</p>' +
                  '<button type="button" class="btn btn-soft btn-pill" onclick="AtlasBusca.limparTudo()">' +
                  '<i class="fa fa-times"></i> Limpar todos os filtros</button>'
                : '<i class="fa fa-inbox"></i><h5>Nenhum oficio cadastrado</h5>' +
                  '<p>Cadastre o primeiro oficio para comecar.</p>';
            atualizarInfo(dados);
            return;
        }

        el.vazio.style.display = 'none';

        var html = [];
        dados.linhas.forEach(function (o) {
            var travado  = o.status === 1;
            var assinado = o.assinado === 1;
            var bloqueado = travado || assinado;

            var badges = '';
            if (assinado)    badges += '<span class="badge-estado badge-assinado"><i class="fa fa-check-circle"></i> Assinado</span>';
            if (travado && !assinado) badges += '<span class="badge-estado badge-travado"><i class="fa fa-lock"></i> Travado</span>';
            if (o.tem_anexo) badges += '<span class="badge-estado badge-anexo"><i class="fa fa-paperclip"></i> Anexo</span>';

            var numeroEsc = encodeURIComponent(o.numero);
            var complemento = semTags(o.dados_complementares);

            html.push(
                '<tr>' +
                    '<td data-label="Numero"><strong>' + destacar(o.numero) + '</strong>' +
                        (badges ? '<div class="mt-1">' + badges + '</div>' : '') + '</td>' +
                    '<td data-label="Data">' + escapar(o.data_br || '-') + '</td>' +
                    '<td data-label="Assunto"><span class="celula-truncada" title="' + escapar(o.assunto) + '">' +
                        destacar(o.assunto) + '</span></td>' +
                    '<td data-label="Destinatario"><span class="celula-truncada" title="' + escapar(o.destinatario) + '">' +
                        destacar(o.destinatario) + '</span></td>' +
                    '<td data-label="Cargo"><span class="celula-truncada" title="' + escapar(o.cargo) + '">' +
                        destacar(o.cargo) + '</span></td>' +
                    '<td data-label="Complementos"><span class="celula-truncada" title="' + escapar(complemento) + '">' +
                        destacar(truncar(complemento, 90)) + '</span></td>' +
                    '<td data-cell="acoes" data-label="Acoes">' +
                        '<button class="btn btn-info btn-sm btn-table" title="Visualizar oficio" ' +
                            'onclick="viewOficio(decodeURIComponent(\'' + numeroEsc + '\'), ' + (assinado ? 'true' : 'false') + ')">' +
                            '<i class="fa fa-eye"></i></button>' +
                        '<button class="btn btn-sm btn-table ' + (bloqueado ? 'btn-secondary' : 'btn-warning') + '" ' +
                            'title="' + (assinado ? 'Oficio assinado - edicao bloqueada' : (travado ? 'Edicao travada' : 'Editar oficio')) + '" ' +
                            (bloqueado ? 'disabled ' : '') +
                            'onclick="editOficio(decodeURIComponent(\'' + numeroEsc + '\'))">' +
                            '<i class="fa fa-pencil"></i></button>' +
                        '<button class="btn btn-sm btn-primary btn-table" title="Anexos" ' +
                            'onclick="viewAttachments(decodeURIComponent(\'' + numeroEsc + '\'))">' +
                            '<i class="fa fa-paperclip"></i></button>' +
                        '<button class="btn btn-sm btn-table ' + (assinado ? 'btn-secondary' : 'btn-success') + '" ' +
                            'title="' + (assinado ? 'Oficio assinado digitalmente' : 'Assinar digitalmente') + '" ' +
                            'onclick="assinarOficio(decodeURIComponent(\'' + numeroEsc + '\'), ' + (assinado ? 'true' : 'false') + ')">' +
                            '<i class="fa ' + (assinado ? 'fa-check-circle' : 'fa-pencil-square-o') + '"></i></button>' +
                    '</td>' +
                '</tr>'
            );
        });

        tbody.innerHTML = html.join('');
        atualizarInfo(dados);
    }

    function atualizarInfo(dados) {
        if (!el.info) return;

        if (!dados.tem_filtro) {
            el.info.innerHTML = '<strong>' + dados.linhas.length + '</strong> oficio(s) mais recente(s) ' +
                '<span class="text-muted">de ' + dados.total + ' no total</span> ' +
                '<span class="text-muted">&middot; ' + dados.tempo_ms + ' ms</span>';
            return;
        }

        var ini = (dados.pagina - 1) * dados.por_pagina + 1;
        var fim = Math.min(dados.pagina * dados.por_pagina, dados.total);

        if (dados.total === 0) {
            el.info.innerHTML = 'Nenhum resultado';
        } else {
            el.info.innerHTML = 'Exibindo <strong>' + ini + '-' + fim + '</strong> de <strong>' +
                dados.total + '</strong> resultado(s) ' +
                '<span class="text-muted">&middot; ' + dados.tempo_ms + ' ms</span>';
        }
    }

    function renderErro(msg) {
        if (el.tbody) el.tbody.innerHTML = '';
        if (el.vazio) {
            el.vazio.style.display = 'block';
            el.vazio.innerHTML = '<i class="fa fa-exclamation-triangle"></i>' +
                '<h5>Erro na consulta</h5><p>' + escapar(msg) + '</p>';
        }
        if (el.info) el.info.innerHTML = '';
    }

    /* =========================================================================
       CHIPS DE FILTROS ATIVOS
    ========================================================================= */
    function renderChipsAtivos(chips) {
        if (!el.chipsAtivos) return;

        if (!chips.length) {
            el.chipsAtivos.innerHTML = '';
            if (el.btnLimparTudo) el.btnLimparTudo.style.display = 'none';
            return;
        }

        var html = chips.map(function (c) {
            return '<span class="chip-ativo">' +
                '<span class="chip-rotulo">' + escapar(c.rotulo) + ':</span>' +
                '<span class="chip-valor" title="' + escapar(c.valor) + '">' + escapar(truncar(c.valor, 40)) + '</span>' +
                '<button type="button" title="Remover filtro" onclick="AtlasBusca.removerChip(\'' +
                    escapar(c.campo) + '\')">&times;</button>' +
            '</span>';
        }).join('');

        el.chipsAtivos.innerHTML = html;
        if (el.btnLimparTudo) el.btnLimparTudo.style.display = 'inline-flex';
    }

    function removerChip(campo) {
        if (campo === 'periodo_datas') {
            estado.data_ini = '';
            estado.data_fim = '';
            estado.periodo = '';
        } else if (Object.prototype.hasOwnProperty.call(estado, campo)) {
            estado[campo] = (campo === 'buscar_corpo') ? 0 : '';
        }
        sincronizarFormulario();
        estado.pagina = 1;
        buscar();
    }

    /* =========================================================================
       FACETAS
    ========================================================================= */
    function renderFacetas(facetas) {
        if (!el.facetas) return;

        var titulos = {
            destinatario: 'Destinatarios frequentes',
            assinante:    'Assinantes',
            cargo:        'Cargos',
            assunto:      'Assuntos recorrentes',
            ano:          'Ano'
        };
        var campos = ['destinatario', 'assinante', 'ano', 'cargo', 'assunto'];
        var html = [];

        campos.forEach(function (campo) {
            var itens = facetas[campo];
            if (!itens || !itens.length) return;
            if (itens.length < 2 && campo !== 'ano') return;

            var linha = itens.map(function (it) {
                var acao = (campo === 'ano')
                    ? "AtlasBusca.aplicarAno('" + escapar(it.valor) + "')"
                    : "AtlasBusca.aplicarFaceta('" + campo + "', '" + escapar(String(it.valor).replace(/'/g, "\\'")) + "')";
                return '<button type="button" class="faceta-item" onclick="' + acao + '">' +
                    '<span class="v" title="' + escapar(it.valor) + '">' + escapar(truncar(it.valor, 36)) + '</span>' +
                    '<span class="n">' + it.total + '</span>' +
                '</button>';
            }).join('');

            html.push('<div class="faceta-grupo">' +
                '<div class="faceta-titulo">' + titulos[campo] + '</div>' +
                '<div class="faceta-itens">' + linha + '</div>' +
            '</div>');
        });

        if (!html.length) {
            el.facetas.innerHTML = '';
            el.facetas.style.display = 'none';
        } else {
            el.facetas.innerHTML = html.join('');
            el.facetas.style.display = el.facetasVisivel ? 'block' : 'none';
        }
    }

    function aplicarFaceta(campo, valor) {
        if (Object.prototype.hasOwnProperty.call(estado, campo)) {
            estado[campo] = valor;
        }
        sincronizarFormulario();
        abrirAvancado(true);
        estado.pagina = 1;
        buscar();
    }

    function aplicarAno(ano) {
        estado.data_ini = ano + '-01-01';
        estado.data_fim = ano + '-12-31';
        estado.periodo = '';
        sincronizarFormulario();
        estado.pagina = 1;
        buscar();
    }

    /* =========================================================================
       PAGINACAO
    ========================================================================= */
    function renderPaginacao(dados) {
        if (!el.paginacao) return;

        if (!dados.tem_filtro || dados.paginas <= 1) {
            el.paginacao.innerHTML = '';
            return;
        }

        var atual = dados.pagina;
        var total = dados.paginas;
        var html = [];

        html.push('<button type="button" ' + (atual <= 1 ? 'disabled' : '') +
            ' onclick="AtlasBusca.irPara(' + (atual - 1) + ')" title="Pagina anterior">' +
            '<i class="fa fa-chevron-left"></i></button>');

        var paginas = [];
        var janela = 2;
        for (var i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= atual - janela && i <= atual + janela)) {
                paginas.push(i);
            }
        }

        var anterior = 0;
        paginas.forEach(function (p) {
            if (anterior && p - anterior > 1) {
                html.push('<span class="reticencias">...</span>');
            }
            html.push('<button type="button" class="' + (p === atual ? 'ativo' : '') +
                '" onclick="AtlasBusca.irPara(' + p + ')">' + p + '</button>');
            anterior = p;
        });

        html.push('<button type="button" ' + (atual >= total ? 'disabled' : '') +
            ' onclick="AtlasBusca.irPara(' + (atual + 1) + ')" title="Proxima pagina">' +
            '<i class="fa fa-chevron-right"></i></button>');

        el.paginacao.innerHTML = html.join('');
    }

    function irPara(pagina) {
        estado.pagina = Math.max(1, pagina);
        buscar(true);
        if (el.tabelaWrap) {
            el.tabelaWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /* =========================================================================
       ORDENACAO
    ========================================================================= */
    function ordenarPor(coluna) {
        if (estado.ordem === coluna) {
            estado.dir = (estado.dir === 'asc') ? 'desc' : 'asc';
        } else {
            estado.ordem = coluna;
            estado.dir = (coluna === 'assunto' || coluna === 'destinatario') ? 'asc' : 'desc';
        }
        if (el.selOrdem) el.selOrdem.value = estado.ordem + '|' + estado.dir;
        marcarColunaOrdenada();
        estado.pagina = 1;
        buscar();
    }

    function marcarColunaOrdenada() {
        var ths = document.querySelectorAll('#tabelaResultados th.ordenavel');
        for (var i = 0; i < ths.length; i++) {
            var th = ths[i];
            var col = th.getAttribute('data-ordem');
            var icone = th.querySelector('.fa');
            th.classList.remove('ord-ativa');
            if (icone) icone.className = 'fa fa-sort';
            if (col === estado.ordem) {
                th.classList.add('ord-ativa');
                if (icone) icone.className = 'fa ' + (estado.dir === 'asc' ? 'fa-sort-asc' : 'fa-sort-desc');
            }
        }
    }

    /* =========================================================================
       CHIPS DE PERIODO
    ========================================================================= */
    function aplicarPeriodo(preset) {
        if (estado.periodo === preset) {
            estado.periodo = '';
            estado.data_ini = '';
            estado.data_fim = '';
        } else {
            estado.periodo = preset;
            estado.data_ini = '';
            estado.data_fim = '';
        }
        sincronizarFormulario();
        estado.pagina = 1;
        buscar();
    }

    function alternarEstado(campo, valor) {
        if (estado[campo] === valor) {
            estado[campo] = '';
        } else {
            estado[campo] = valor;
        }
        sincronizarFormulario();
        estado.pagina = 1;
        buscar();
    }

    function atualizarChipsPeriodo() {
        var chips = document.querySelectorAll('[data-periodo]');
        for (var i = 0; i < chips.length; i++) {
            chips[i].classList.toggle('ativo', chips[i].getAttribute('data-periodo') === estado.periodo);
        }
        var estados = document.querySelectorAll('[data-estado-campo]');
        for (var j = 0; j < estados.length; j++) {
            var c = estados[j].getAttribute('data-estado-campo');
            var v = estados[j].getAttribute('data-estado-valor');
            estados[j].classList.toggle('ativo', estado[c] === v);
        }
    }

    /* =========================================================================
       AVISO DE OPERADOR NAO RECONHECIDO
    ========================================================================= */
    function renderAvisoOperador(lista) {
        if (!el.avisoOperador) return;
        if (!lista || !lista.length) {
            el.avisoOperador.innerHTML = '';
            el.avisoOperador.style.display = 'none';
            return;
        }
        el.avisoOperador.style.display = 'block';
        el.avisoOperador.innerHTML = '<i class="fa fa-info-circle"></i> O operador <code>' +
            escapar(lista[0]) + ':</code> nao existe e foi tratado como texto comum. ' +
            '<a href="#" onclick="AtlasBusca.ajuda(); return false;">Ver operadores disponiveis</a>';
    }

    /* =========================================================================
       AUTOCOMPLETE
    ========================================================================= */
    function pedirSugestoes(termo) {
        clearTimeout(timerSugestoes);
        if (!termo || termo.replace(/^.*[\s:]/, '').length < 2) {
            fecharSugestoes();
            return;
        }
        timerSugestoes = setTimeout(function () {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'busca_sugestoes.php?termo=' + encodeURIComponent(termo), true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4 || xhr.status < 200 || xhr.status >= 300) return;
                var dados;
                try { dados = JSON.parse(xhr.responseText); } catch (e) { return; }
                if (!dados.ok) return;
                renderSugestoes(dados.sugestoes || []);
            };
            xhr.send();
        }, 240);
    }

    function renderSugestoes(lista) {
        if (!el.sugestoes) return;
        sugestoesAtuais = lista;
        sugestaoIndice = -1;

        if (!lista.length) {
            fecharSugestoes();
            return;
        }

        var grupos = {};
        lista.forEach(function (s) {
            if (!grupos[s.rotulo]) grupos[s.rotulo] = [];
            grupos[s.rotulo].push(s);
        });

        var html = [];
        var indice = 0;
        Object.keys(grupos).forEach(function (rotulo) {
            html.push('<div class="sug-grupo">' + escapar(rotulo) + '</div>');
            grupos[rotulo].forEach(function (s) {
                html.push('<div class="sug-item" data-indice="' + indice + '" ' +
                    'onmousedown="event.preventDefault(); AtlasBusca.usarSugestao(' + indice + ')">' +
                    '<i class="fa ' + escapar(s.icone) + '"></i>' +
                    '<span class="sug-valor">' + escapar(s.valor) + '</span>' +
                    '<span class="sug-total">' + s.total + '</span>' +
                '</div>');
                indice++;
            });
        });

        // Reordena a lista plana para bater com os indices renderizados
        var plana = [];
        Object.keys(grupos).forEach(function (r) {
            grupos[r].forEach(function (s) { plana.push(s); });
        });
        sugestoesAtuais = plana;

        el.sugestoes.innerHTML = html.join('');
        el.sugestoes.classList.add('aberto');
    }

    function fecharSugestoes() {
        if (el.sugestoes) {
            el.sugestoes.classList.remove('aberto');
            el.sugestoes.innerHTML = '';
        }
        sugestoesAtuais = [];
        sugestaoIndice = -1;
    }

    function usarSugestao(indice) {
        var s = sugestoesAtuais[indice];
        if (!s) return;
        estado.q = s.consulta;
        if (el.inputQ) el.inputQ.value = estado.q;
        fecharSugestoes();
        estado.pagina = 1;
        buscar();
    }

    function moverSugestao(delta) {
        if (!sugestoesAtuais.length) return;
        sugestaoIndice += delta;
        if (sugestaoIndice < 0) sugestaoIndice = sugestoesAtuais.length - 1;
        if (sugestaoIndice >= sugestoesAtuais.length) sugestaoIndice = 0;

        var itens = el.sugestoes.querySelectorAll('.sug-item');
        for (var i = 0; i < itens.length; i++) {
            itens[i].classList.toggle('ativo', parseInt(itens[i].getAttribute('data-indice'), 10) === sugestaoIndice);
        }
    }

    /* =========================================================================
       BUSCAS SALVAS (localStorage)
    ========================================================================= */
    function lerSalvas() {
        try {
            return JSON.parse(localStorage.getItem(CHAVE_SALVAS) || '[]');
        } catch (e) { return []; }
    }

    function gravarSalvas(lista) {
        try { localStorage.setItem(CHAVE_SALVAS, JSON.stringify(lista)); } catch (e) {}
    }

    function salvarBusca() {
        if (!temAlgumFiltro()) {
            Swal.fire({ icon: 'info', title: 'Nada para salvar', text: 'Aplique ao menos um filtro antes de salvar a pesquisa.' });
            return;
        }
        Swal.fire({
            title: 'Salvar pesquisa',
            input: 'text',
            inputLabel: 'Nome da pesquisa',
            inputPlaceholder: 'Ex.: Oficios do INSS em 2026',
            showCancelButton: true,
            confirmButtonText: 'Salvar',
            cancelButtonText: 'Cancelar',
            inputValidator: function (v) {
                if (!v || !v.trim()) return 'Informe um nome.';
                return null;
            }
        }).then(function (r) {
            if (!r.isConfirmed) return;
            var lista = lerSalvas();
            lista = lista.filter(function (s) { return s.nome !== r.value.trim(); });
            lista.unshift({ nome: r.value.trim(), params: paramsAtivos() });
            gravarSalvas(lista.slice(0, 12));
            renderSalvas();
            Swal.fire({ icon: 'success', title: 'Pesquisa salva!', showConfirmButton: false, timer: 1400 });
        });
    }

    function renderSalvas() {
        if (!el.salvas) return;
        var lista = lerSalvas();
        if (!lista.length) {
            el.salvas.innerHTML = '';
            return;
        }
        el.salvas.innerHTML = '<span class="text-muted" style="font-size:.8rem;font-weight:700;">Salvas:</span>' +
            lista.map(function (s, i) {
                return '<span class="chip-salva" onclick="AtlasBusca.usarSalva(' + i + ')">' +
                    '<i class="fa fa-bookmark"></i>' + escapar(truncar(s.nome, 28)) +
                    '<button type="button" title="Remover" onclick="event.stopPropagation(); AtlasBusca.removerSalva(' + i + ')">&times;</button>' +
                '</span>';
            }).join('');
    }

    function usarSalva(indice) {
        var lista = lerSalvas();
        var item = lista[indice];
        if (!item) return;
        limparEstado();
        Object.keys(item.params).forEach(function (k) {
            if (Object.prototype.hasOwnProperty.call(estado, k)) {
                estado[k] = item.params[k];
            }
        });
        estado.pagina = 1;
        sincronizarFormulario();
        buscar();
    }

    function removerSalva(indice) {
        var lista = lerSalvas();
        lista.splice(indice, 1);
        gravarSalvas(lista);
        renderSalvas();
    }

    /* =========================================================================
       FORMULARIO AVANCADO
    ========================================================================= */
    function abrirAvancado(forcar) {
        if (!el.painelAvancado) return;
        var aberto = forcar !== undefined ? forcar : !el.painelAvancado.classList.contains('aberto');
        el.painelAvancado.classList.toggle('aberto', aberto);
        if (el.btnAvancado) {
            el.btnAvancado.innerHTML = aberto
                ? '<i class="fa fa-chevron-up"></i> Ocultar filtros avancados'
                : '<i class="fa fa-sliders"></i> Filtros avancados';
        }
    }

    function alternarFacetas() {
        el.facetasVisivel = !el.facetasVisivel;
        if (el.facetas) {
            el.facetas.style.display = (el.facetasVisivel && el.facetas.innerHTML) ? 'block' : 'none';
        }
        if (el.btnFacetas) {
            el.btnFacetas.innerHTML = el.facetasVisivel
                ? '<i class="fa fa-chevron-up"></i> Ocultar sugestoes de filtro'
                : '<i class="fa fa-magic"></i> Sugestoes de filtro';
        }
    }

    /** Copia o estado para os controles da tela. */
    function sincronizarFormulario() {
        if (el.inputQ) el.inputQ.value = estado.q;

        [['numero','f_numero'], ['assunto','f_assunto'], ['destinatario','f_destinatario'],
         ['assinante','f_assinante'], ['cargo','f_cargo'],
         ['dados_complementares','f_complementos'], ['corpo','f_corpo'],
         ['data_ini','f_data_ini'], ['data_fim','f_data_fim']].forEach(function (par) {
            var input = $(par[1]);
            if (input) input.value = estado[par[0]] || '';
        });

        var selAssinado = $('f_assinado');
        if (selAssinado) selAssinado.value = estado.assinado;
        var selTravado = $('f_travado');
        if (selTravado) selTravado.value = estado.travado;
        var selAnexo = $('f_anexo');
        if (selAnexo) selAnexo.value = estado.anexo;

        var chkCorpo = $('f_buscar_corpo');
        if (chkCorpo) chkCorpo.checked = !!estado.buscar_corpo;

        if (el.selOrdem) el.selOrdem.value = estado.ordem + '|' + estado.dir;
        if (el.selPorPagina) el.selPorPagina.value = String(estado.por_pagina);

        var btnsModo = document.querySelectorAll('[data-modo]');
        for (var i = 0; i < btnsModo.length; i++) {
            btnsModo[i].classList.toggle('ativo', btnsModo[i].getAttribute('data-modo') === estado.modo);
        }

        atualizarChipsPeriodo();
        marcarColunaOrdenada();
    }

    function limparEstado() {
        estado.q = '';
        estado.numero = '';
        estado.assunto = '';
        estado.destinatario = '';
        estado.assinante = '';
        estado.cargo = '';
        estado.dados_complementares = '';
        estado.corpo = '';
        estado.data_ini = '';
        estado.data_fim = '';
        estado.periodo = '';
        estado.assinado = '';
        estado.travado = '';
        estado.anexo = '';
        estado.buscar_corpo = 0;
        estado.modo = 'e';
        estado.pagina = 1;
    }

    function limparTudo() {
        limparEstado();
        estado.ordem = 'relevancia';
        estado.dir = 'desc';
        sincronizarFormulario();
        fecharSugestoes();
        buscar();
        if (el.inputQ) el.inputQ.focus();
    }

    function exportar() {
        var qs = queryString();
        window.location.href = 'exportar_oficios.php' + (qs ? '?' + qs : '');
    }

    /* =========================================================================
       AJUDA DA SINTAXE
    ========================================================================= */
    function ajuda() {
        var linhas = [
            ['numero:145/2026', 'Localiza pelo numero do oficio'],
            ['assunto:penhora', 'Busca somente no campo assunto'],
            ['dest:"banco do brasil"', 'Destinatario, com frase exata entre aspas'],
            ['assinante:maria', 'Filtra pelo assinante'],
            ['cargo:tabeliao', 'Filtra pelo cargo do destinatario'],
            ['compl:processo', 'Busca nos dados complementares'],
            ['corpo:usucapiao', 'Busca dentro do texto do oficio'],
            ['de:01/01/2026 ate:31/03/2026', 'Intervalo de datas'],
            ['ano:2026', 'Todos os oficios do ano'],
            ['mes:2026-03', 'Todos os oficios do mes'],
            ['data:15/04/2026', 'Data exata'],
            ['hoje', 'Somente os de hoje (tambem: ontem)'],
            ['assinado:sim', 'Apenas assinados (ou nao)'],
            ['travado:nao', 'Apenas com edicao liberada'],
            ['anexo:sim', 'Apenas com arquivos anexados'],
            ['-cancelado', 'Exclui resultados que contenham a palavra'],
            ['"inteiro teor"', 'Frase exata']
        ];

        var tabela = '<table style="width:100%;text-align:left;">' +
            linhas.map(function (l) {
                return '<tr><td style="white-space:nowrap;"><code>' + escapar(l[0]) + '</code></td>' +
                       '<td>' + escapar(l[1]) + '</td></tr>';
            }).join('') + '</table>';

        Swal.fire({
            title: 'Como pesquisar',
            html: '<div class="ajuda-sintaxe">' +
                  '<p style="text-align:left;">Digite palavras normalmente ou combine os operadores abaixo. ' +
                  'A busca ignora acentos e maiusculas/minusculas.</p>' + tabela +
                  '<p style="text-align:left;margin-top:12px;opacity:.8;font-size:.85rem;">' +
                  'Atalhos: <code>/</code> foca a busca &middot; <code>Esc</code> limpa &middot; ' +
                  '<code>Enter</code> pesquisa imediatamente.</p></div>',
            width: 680,
            confirmButtonText: 'Entendi'
        });
    }

    /* =========================================================================
       INICIALIZACAO
    ========================================================================= */
    function init() {
        el.inputQ         = $('q');
        el.sugestoes      = $('buscaSugestoes');
        el.chipsAtivos    = $('chipsAtivos');
        el.facetas        = $('facetasBox');
        el.painelAvancado = $('painelAvancado');
        el.btnAvancado    = $('btnAvancado');
        el.btnFacetas     = $('btnFacetas');
        el.btnLimparTudo  = $('btnLimparTudo');
        el.tbody          = $('oficioTable');
        el.tabelaWrap     = $('tabelaWrap');
        el.vazio          = $('semResultados');
        el.info           = $('resultadosInfo');
        el.paginacao      = $('paginacao');
        el.carregando     = $('buscaCarregando');
        el.selOrdem       = $('selOrdem');
        el.selPorPagina   = $('selPorPagina');
        el.salvas         = $('buscasSalvas');
        el.avisoOperador  = $('avisoOperador');
        el.facetasVisivel = false;

        lerUrl();
        sincronizarFormulario();
        renderSalvas();

        /* ---- Busca principal ---- */
        if (el.inputQ) {
            el.inputQ.addEventListener('input', function () {
                estado.q = this.value;
                pedirSugestoes(this.value);
                agendarBusca(400);
            });

            el.inputQ.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); moverSugestao(1); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); moverSugestao(-1); }
                else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (sugestaoIndice >= 0) {
                        usarSugestao(sugestaoIndice);
                    } else {
                        clearTimeout(timerBusca);
                        fecharSugestoes();
                        estado.pagina = 1;
                        buscar();
                    }
                } else if (e.key === 'Escape') {
                    if (el.sugestoes && el.sugestoes.classList.contains('aberto')) {
                        fecharSugestoes();
                    } else {
                        this.value = '';
                        estado.q = '';
                        agendarBusca(0);
                    }
                }
            });

            el.inputQ.addEventListener('blur', function () {
                setTimeout(fecharSugestoes, 150);
            });
        }

        /* ---- Campos avancados ---- */
        var mapaCampos = {
            f_numero: 'numero',
            f_assunto: 'assunto',
            f_destinatario: 'destinatario',
            f_assinante: 'assinante',
            f_cargo: 'cargo',
            f_complementos: 'dados_complementares',
            f_corpo: 'corpo'
        };
        Object.keys(mapaCampos).forEach(function (id) {
            var input = $(id);
            if (!input) return;
            input.addEventListener('input', function () {
                estado[mapaCampos[id]] = this.value;
                agendarBusca(450);
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(timerBusca);
                    estado.pagina = 1;
                    buscar();
                }
            });
        });

        ['f_data_ini', 'f_data_fim'].forEach(function (id) {
            var input = $(id);
            if (!input) return;
            input.addEventListener('change', function () {
                estado[id === 'f_data_ini' ? 'data_ini' : 'data_fim'] = this.value;
                estado.periodo = '';
                atualizarChipsPeriodo();
                agendarBusca(0);
            });
        });

        [['f_assinado', 'assinado'], ['f_travado', 'travado'], ['f_anexo', 'anexo']].forEach(function (par) {
            var sel = $(par[0]);
            if (!sel) return;
            sel.addEventListener('change', function () {
                estado[par[1]] = this.value;
                agendarBusca(0);
            });
        });

        var chkCorpo = $('f_buscar_corpo');
        if (chkCorpo) {
            chkCorpo.addEventListener('change', function () {
                estado.buscar_corpo = this.checked ? 1 : 0;
                agendarBusca(0);
            });
        }

        /* ---- Ordenacao e paginacao ---- */
        if (el.selOrdem) {
            el.selOrdem.addEventListener('change', function () {
                var partes = this.value.split('|');
                estado.ordem = partes[0];
                estado.dir = partes[1] || 'desc';
                estado.pagina = 1;
                marcarColunaOrdenada();
                buscar();
            });
        }

        if (el.selPorPagina) {
            el.selPorPagina.addEventListener('change', function () {
                estado.por_pagina = parseInt(this.value, 10) || 25;
                estado.pagina = 1;
                buscar();
            });
        }

        var ths = document.querySelectorAll('#tabelaResultados th.ordenavel');
        for (var i = 0; i < ths.length; i++) {
            (function (th) {
                th.addEventListener('click', function () {
                    ordenarPor(th.getAttribute('data-ordem'));
                });
            })(ths[i]);
        }

        /* ---- Modo E/OU ---- */
        var btnsModo = document.querySelectorAll('[data-modo]');
        for (var m = 0; m < btnsModo.length; m++) {
            (function (btn) {
                btn.addEventListener('click', function () {
                    estado.modo = btn.getAttribute('data-modo');
                    sincronizarFormulario();
                    estado.pagina = 1;
                    buscar();
                });
            })(btnsModo[m]);
        }

        /* ---- Atalhos de teclado ---- */
        document.addEventListener('keydown', function (e) {
            var alvo = e.target;
            var digitando = alvo && (alvo.tagName === 'INPUT' || alvo.tagName === 'TEXTAREA' ||
                                     alvo.tagName === 'SELECT' || alvo.isContentEditable);
            if (e.key === '/' && !digitando) {
                e.preventDefault();
                if (el.inputQ) { el.inputQ.focus(); el.inputQ.select(); }
            }
        });

        /* ---- Primeira consulta ---- */
        buscar();
    }

    /* =========================================================================
       API PUBLICA
    ========================================================================= */
    return {
        init: init,
        buscar: function () { estado.pagina = 1; buscar(); },
        irPara: irPara,
        removerChip: removerChip,
        aplicarFaceta: aplicarFaceta,
        aplicarAno: aplicarAno,
        aplicarPeriodo: aplicarPeriodo,
        alternarEstado: alternarEstado,
        abrirAvancado: abrirAvancado,
        alternarFacetas: alternarFacetas,
        limparTudo: limparTudo,
        salvarBusca: salvarBusca,
        usarSalva: usarSalva,
        removerSalva: removerSalva,
        usarSugestao: usarSugestao,
        exportar: exportar,
        ajuda: ajuda,
        estado: estado
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('q')) {
        AtlasBusca.init();
    }
});
