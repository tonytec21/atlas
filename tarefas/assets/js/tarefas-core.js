/*!
 * Atlas · Tarefas — núcleo do front-end (v2).
 *
 * Responsável pelo estado da tela, filtros, paginação e renderização das
 * visões Painel, Cards, Kanban, Lista e Calendário.
 *
 * Sem dependência de CDN: usa apenas jQuery e SweetAlert2, ambos já servidos
 * localmente pelo Atlas. Quando o SweetAlert2 não estiver disponível, as
 * funções de diálogo caem para um substituto nativo, para a tela nunca ficar
 * travada por causa de um script ausente.
 */

/* global jQuery, Swal */
var Tarefas = (function ($) {
    'use strict';

    /* ============================================================== */
    /* Estado                                                         */
    /* ============================================================== */

    var estado = {
        visao: 'cards',
        pagina: 1,
        porPagina: 24,
        ordenar: 'protocolo',
        direcao: 'desc',
        filtros: {},
        atalho: '',
        selecionadas: [],
        tarefas: [],
        requisicao: 0,
        painelCarregado: false
    };

    var cfg = window.TarefasConfig || {};

    /* ============================================================== */
    /* Utilitários                                                    */
    /* ============================================================== */

    /**
     * Substitui $.trim, que foi marcado como obsoleto no jQuery 3.5 e removido
     * no 4. Como o Atlas pode atualizar o jQuery a qualquer momento, o módulo
     * não depende mais dele.
     */
    function txt(v) {
        return (v === null || v === undefined) ? '' : String(v).trim();
    }

    /** Escapa texto para inserção segura em HTML. */
    function esc(v) {
        if (v === null || v === undefined) { return ''; }
        return String(v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /** Corta um texto longo preservando palavras. */
    function corta(texto, limite) {
        var t = String(texto || '');
        if (t.length <= limite) { return t; }
        return t.slice(0, limite).replace(/\s+\S*$/, '') + '…';
    }

    /**
     * Ajusta as opções de um diálogo SweetAlert2 conforme o contexto.
     *
     * Quando há um modal do Bootstrap aberto, o SweetAlert2 precisa nascer
     * DENTRO dele. O Bootstrap mantém um vigia de foco (`_enforceFocus`) que
     * devolve o foco ao modal sempre que ele escapa para um elemento de fora
     * — e o SweetAlert2, por padrão, se anexa ao <body>. O resultado é um
     * campo de texto que não aceita digitação: cada tecla perde o foco antes
     * de registrar o caractere. Era exatamente o que travava o "Registrar
     * andamento".
     *
     * Ancorando o diálogo no próprio modal, o foco passa a estar "dentro" na
     * visão do Bootstrap e a digitação funciona. A aparência não muda: o
     * contêiner do SweetAlert2 continua fixo, cobrindo a tela inteira.
     */
    function opcoesDialogo(opcoes) {
        var base = { heightAuto: false };

        var modalAberto = document.querySelector('.modal.show');
        if (modalAberto) {
            base.target = modalAberto;
        }

        return $.extend(base, opcoes || {});
    }

    /** Diálogos: usa SweetAlert2 quando existir. */
    var dlg = {
        ok: function (titulo, texto) {
            if (window.Swal) {
                return Swal.fire(opcoesDialogo({ icon: 'success', title: titulo, text: texto || '', timer: 2200, showConfirmButton: false }));
            }
            window.alert(titulo + (texto ? '\n\n' + texto : ''));
            return $.Deferred().resolve().promise();
        },
        erro: function (titulo, texto) {
            if (window.Swal) {
                return Swal.fire(opcoesDialogo({ icon: 'error', title: titulo, text: texto || '' }));
            }
            window.alert(titulo + (texto ? '\n\n' + texto : ''));
            return $.Deferred().resolve().promise();
        },
        aviso: function (texto) {
            if (window.Swal) {
                return Swal.fire(opcoesDialogo({ icon: 'warning', title: 'Atenção', text: texto }));
            }
            window.alert(texto);
            return $.Deferred().resolve().promise();
        },
        confirmar: function (titulo, texto, textoBotao) {
            if (window.Swal) {
                return Swal.fire(opcoesDialogo({
                    icon: 'question',
                    title: titulo,
                    text: texto || '',
                    showCancelButton: true,
                    confirmButtonText: textoBotao || 'Confirmar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true
                })).then(function (r) { return !!r.isConfirmed; });
            }
            return $.Deferred().resolve(window.confirm(titulo + (texto ? '\n\n' + texto : ''))).promise();
        },
        carregando: function (titulo) {
            if (window.Swal) {
                Swal.fire(opcoesDialogo({
                    title: titulo || 'Processando…',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                }));
            }
        },
        fechar: function () {
            if (window.Swal) { Swal.close(); }
        }
    };

    /**
     * Chamada às APIs do módulo.
     * Trata o caso clássico de resposta contaminada por HTML de erro do PHP,
     * devolvendo uma mensagem compreensível em vez do "Unexpected token '<'".
     */
    function api(arquivo, dados, metodo) {
        var opcoes = {
            url: 'api/' + arquivo,
            type: metodo || 'GET',
            dataType: 'json'
        };

        if (metodo === 'POST') {
            if (dados instanceof FormData) {
                dados.append('_csrf', cfg.csrf);
                opcoes.data = dados;
                opcoes.processData = false;
                opcoes.contentType = false;
            } else {
                opcoes.data = $.extend({ _csrf: cfg.csrf }, dados || {});
            }
        } else {
            opcoes.data = dados || {};
        }

        return $.ajax(opcoes).then(null, function (xhr) {
            var msg = 'Falha de comunicação com o servidor.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                msg = xhr.responseJSON.error;
            } else if (xhr.status === 0) {
                msg = 'Sem resposta do servidor. Verifique se o Apache está ativo.';
            } else if (xhr.status === 401) {
                msg = 'Sua sessão expirou. Faça login novamente.';
            } else if (xhr.responseText && xhr.responseText.indexOf('<') === 0) {
                msg = 'O servidor devolveu uma página de erro em vez de dados. '
                    + 'Verifique o log de erros do PHP.';
            }
            return $.Deferred().reject({ error: msg, status: xhr.status }).promise();
        });
    }

    /** Ícone de acordo com a extensão do anexo. */
    function iconeArquivo(ext) {
        var mapa = {
            pdf: 'fa-file-pdf-o', doc: 'fa-file-word-o', docx: 'fa-file-word-o',
            xls: 'fa-file-excel-o', xlsx: 'fa-file-excel-o', csv: 'fa-file-excel-o',
            png: 'fa-file-image-o', jpg: 'fa-file-image-o', jpeg: 'fa-file-image-o',
            gif: 'fa-file-image-o', webp: 'fa-file-image-o', bmp: 'fa-file-image-o',
            zip: 'fa-file-archive-o', rar: 'fa-file-archive-o', '7z': 'fa-file-archive-o',
            txt: 'fa-file-text-o', xml: 'fa-file-code-o', json: 'fa-file-code-o',
            mp3: 'fa-file-audio-o', wav: 'fa-file-audio-o',
            mp4: 'fa-file-video-o', webm: 'fa-file-video-o'
        };
        return mapa[String(ext || '').toLowerCase()] || 'fa-file-o';
    }

    /** Cor da borda esquerda do card conforme a prioridade. */
    function corPrioridade(p) {
        var mapa = { 'Baixa': '#64748b', 'Média': '#0ea5e9', 'Alta': '#f59e0b', 'Crítica': '#ef4444' };
        return mapa[p] || '#94a3b8';
    }

    /* ============================================================== */
    /* Filtros                                                        */
    /* ============================================================== */

    /** Lê o formulário de filtros e devolve um objeto limpo. */
    function lerFiltros() {
        var f = {};
        $('#tfFormFiltros').find('input, select').each(function () {
            var nome = $(this).attr('name');
            var valor = txt($(this).val() || '');
            if (nome && valor !== '') { f[nome] = valor; }
        });
        var texto = txt($('#tfBusca').val() || '');
        if (texto !== '') { f.texto = texto; }
        if (estado.atalho !== '') { f.situacao = estado.atalho; }
        return f;
    }

    /** Quantos filtros estão ativos (para o selo do botão). */
    function contarFiltros() {
        var n = 0;
        $('#tfFormFiltros').find('input, select').each(function () {
            if (txt($(this).val() || '') !== '') { n++; }
        });
        return n;
    }

    function atualizarSeloFiltros() {
        var n = contarFiltros();
        $('#tfContadorFiltros').text(n > 0 ? n : '').toggle(n > 0);
    }

    function limparFiltros() {
        $('#tfFormFiltros')[0].reset();
        $('#tfBusca').val('');
        estado.atalho = '';
        $('.tf-chip[data-atalho]').removeClass('tf-ativo');
        atualizarSeloFiltros();
        buscar(1);
    }

    /* ============================================================== */
    /* Busca                                                          */
    /* ============================================================== */

    /**
     * Consulta as tarefas e desenha a visão atual.
     *
     * Cada chamada recebe um número de sequência. Só a resposta da chamada
     * mais recente é desenhada — as anteriores são descartadas. Isso resolve
     * dois problemas: trocar de visão enquanto a busca anterior ainda estava
     * no ar deixava a tela permanentemente em branco (a chamada nova era
     * ignorada), e uma resposta atrasada podia desenhar o conteúdo de uma
     * visão dentro de outra.
     */
    function buscar(pagina) {
        estado.pagina = pagina || 1;
        estado.filtros = lerFiltros();

        var seq = ++estado.requisicao;
        var visaoNoPedido = estado.visao;

        var params = $.extend({}, estado.filtros, {
            visao: estado.visao === 'painel' ? 'cards' : estado.visao,
            pagina: estado.pagina,
            por_pagina: estado.porPagina,
            ordenar: estado.ordenar,
            direcao: estado.direcao
        });

        if (estado.visao === 'kanban' || estado.visao === 'calendario') {
            delete params.pagina;
        }

        mostrarEsqueleto();

        api('tarefas.php', params)
            .done(function (r) {
                // Resposta velha ou de outra visão: ignora.
                if (seq !== estado.requisicao || visaoNoPedido !== estado.visao) { return; }

                if (!r.success) {
                    mostrarVazio('Erro', r.error || 'Não foi possível carregar as tarefas.');
                    return;
                }

                if (estado.visao === 'kanban') {
                    estado.tarefas = [];
                    try {
                        renderKanban(r.colunas);
                    } catch (err) {
                        if (window.console) { console.error('[tarefas] kanban', err, r); }
                        mostrarVazio('Não foi possível montar o quadro', err.message);
                    }
                } else if (estado.visao === 'calendario') {
                    // O calendário busca por conta própria, com a janela visível.
                    TarefasCalendario.recarregar();
                } else {
                    estado.tarefas = r.tarefas || [];
                    if (estado.visao === 'lista') {
                        renderLista(r);
                    } else {
                        renderCards(r);
                    }
                    renderPaginacao(r);
                }

                $('#tfTotalResultados').text(
                    r.total === 1 ? '1 tarefa' : (r.total || 0).toLocaleString('pt-BR') + ' tarefas'
                );
                sincronizarSelecao();
            })
            .fail(function (e) {
                if (seq !== estado.requisicao || visaoNoPedido !== estado.visao) { return; }
                mostrarVazio('Erro na consulta', e.error || 'Falha ao consultar o servidor.');
            });
    }

    var buscaAtrasada = null;
    function buscarComAtraso() {
        clearTimeout(buscaAtrasada);
        buscaAtrasada = setTimeout(function () { buscar(1); }, 380);
    }

    /* ============================================================== */
    /* Renderização — estados                                         */
    /* ============================================================== */

    function alvoConteudo() {
        if (estado.visao === 'kanban') { return $('#tfKanban'); }
        if (estado.visao === 'lista') { return $('#tfLista'); }
        return $('#tfCards');
    }

    function mostrarEsqueleto() {
        if (estado.visao === 'calendario' || estado.visao === 'painel') { return; }
        var html = '';
        var n = estado.visao === 'lista' ? 1 : 6;
        for (var i = 0; i < n; i++) {
            html += '<div class="tf-esqueleto"' + (n === 1 ? ' style="height:320px"' : '') + '></div>';
        }
        alvoConteudo().html(html);
    }

    function mostrarVazio(titulo, texto, acao) {
        alvoConteudo().html(
            '<div class="tf-vazio" style="grid-column:1/-1">'
            + '<i class="fa fa-inbox"></i>'
            + '<h4>' + esc(titulo) + '</h4>'
            + '<p>' + esc(texto) + '</p>'
            + (acao || '')
            + '</div>'
        );
        $('#tfPaginacao').empty();
    }

    /* ============================================================== */
    /* Renderização — cards                                           */
    /* ============================================================== */

    function renderCards(r) {
        var $c = $('#tfCards').empty();

        if (!r.tarefas || !r.tarefas.length) {
            mostrarVazio(
                'Nenhuma tarefa encontrada',
                r.tem_filtro ? 'Ajuste os filtros ou limpe a busca para ver mais resultados.'
                             : 'Não há tarefas em aberto no momento.',
                '<button class="tf-btn tf-btn-primario" onclick="Tarefas.limparFiltros()">'
                + '<i class="fa fa-eraser"></i> Limpar filtros</button>'
            );
            return;
        }

        var html = r.tarefas.map(cardHtml).join('');
        $c.html(html);
    }

    function cardHtml(t) {
        var classes = ['tf-card'];
        if (t.situacao === 'vencida') { classes.push('tf-vencida'); }
        if (t.situacao === 'hoje') { classes.push('tf-hoje'); }
        if (estado.selecionadas.indexOf(String(t.id)) !== -1) { classes.push('tf-marcada'); }

        var selos = '<span class="tf-selo tf-selo-status" style="--tf-cor:' + esc(t.status_cor) + '">'
                  + esc(t.status) + '</span>';

        if (t.situacao !== 'encerrada' && t.situacao !== 'no-prazo' && t.situacao !== 'sem-prazo') {
            selos += ' <span class="tf-selo tf-selo-' + esc(t.situacao) + '">'
                   + '<i class="fa fa-clock-o"></i> ' + esc(t.situacao_rotulo) + '</span>';
        }

        if (t.e_subtarefa) {
            selos += ' <span class="tf-selo tf-selo-contorno" style="--tf-cor:#f59e0b">'
                   + '<i class="fa fa-level-down"></i> Subtarefa</span>';
        }

        var indicadores = '';
        if (t.total_anexos > 0) {
            indicadores += '<span title="Anexos"><i class="fa fa-paperclip"></i> ' + t.total_anexos + '</span>';
        }
        if (t.total_comentarios > 0) {
            indicadores += '<span title="Comentários"><i class="fa fa-comment-o"></i> ' + t.total_comentarios + '</span>';
        }
        if (t.total_subtarefas > 0) {
            indicadores += '<span title="Subtarefas"><i class="fa fa-sitemap"></i> ' + t.total_subtarefas + '</span>';
        }
        if (t.numero_oficio) {
            indicadores += '<span title="Ofício vinculado"><i class="fa fa-link"></i> ' + esc(t.numero_oficio) + '</span>';
        }

        return ''
            + '<div class="' + classes.join(' ') + '" style="--tf-prio:' + corPrioridade(t.nivel_de_prioridade) + '"'
            + ' data-id="' + esc(t.id) + '" data-token="' + esc(t.token) + '" onclick="Tarefas.abrir(this, event)">'
            + '  <input type="checkbox" class="tf-card-check" data-id="' + esc(t.id) + '"'
            + (estado.selecionadas.indexOf(String(t.id)) !== -1 ? ' checked' : '') + '>'
            + '  <div class="tf-card-topo">'
            + '    <div style="flex:1;min-width:0">'
            + '      <div class="tf-card-protocolo">PROTOCOLO Nº ' + esc(t.id) + '</div>'
            + '      <h3 class="tf-card-titulo">' + esc(t.titulo) + '</h3>'
            + '    </div>'
            + '  </div>'
            + (t.descricao ? '<div class="tf-card-desc">' + esc(corta(t.descricao, 150)) + '</div>' : '')
            + '  <div class="tf-card-meta">'
            + '    <span><i class="fa fa-user-o"></i> ' + esc(t.funcionario_responsavel || 'Sem responsável') + '</span>'
            + '    <span><i class="fa fa-calendar-o"></i> ' + esc(t.data_limite_br || 'Sem prazo') + '</span>'
            + (t.categoria_titulo ? '<span><i class="fa fa-folder-o"></i> ' + esc(t.categoria_titulo) + '</span>' : '')
            + '  </div>'
            + '  <div class="tf-card-rodape">' + selos
            + (indicadores ? '<span style="margin-left:auto;display:flex;gap:11px;font-size:.76rem;color:var(--tf-texto-3)">'
                             + indicadores + '</span>' : '')
            + '  </div>'
            + '</div>';
    }

    /* ============================================================== */
    /* Renderização — lista                                           */
    /* ============================================================== */

    function renderLista(r) {
        if (!r.tarefas || !r.tarefas.length) {
            mostrarVazio('Nenhuma tarefa encontrada', 'Ajuste os filtros para ver mais resultados.');
            return;
        }

        var colunas = [
            { chave: 'protocolo', rotulo: 'Protocolo' },
            { chave: 'titulo', rotulo: 'Título' },
            { chave: 'funcionario', rotulo: 'Responsável' },
            { chave: 'data', rotulo: 'Prazo' },
            { chave: 'prioridade', rotulo: 'Prioridade' },
            { chave: 'status', rotulo: 'Status' },
            { chave: '', rotulo: 'Situação' }
        ];

        var cab = colunas.map(function (c) {
            if (!c.chave) { return '<th class="tf-sem-ordem">' + c.rotulo + '</th>'; }
            var seta = estado.ordenar === c.chave
                ? ' <i class="fa fa-caret-' + (estado.direcao === 'asc' ? 'up' : 'down') + '"></i>' : '';
            return '<th data-ordem="' + c.chave + '">' + c.rotulo + seta + '</th>';
        }).join('');

        var linhas = r.tarefas.map(function (t) {
            return ''
                + '<tr data-token="' + esc(t.token) + '" data-id="' + esc(t.id) + '" onclick="Tarefas.abrir(this, event)">'
                + '<td class="tf-num tf-forte">' + esc(t.id) + '</td>'
                + '<td>' + esc(corta(t.titulo, 70))
                + (t.e_subtarefa ? ' <i class="fa fa-level-down tf-mudo" title="Subtarefa"></i>' : '')
                + '</td>'
                + '<td>' + esc(t.funcionario_responsavel || '—') + '</td>'
                + '<td class="tf-num">' + esc(t.data_limite_br || '—') + '</td>'
                + '<td><span class="tf-selo tf-selo-contorno" style="--tf-cor:'
                    + corPrioridade(t.nivel_de_prioridade) + '">' + esc(t.nivel_de_prioridade || '—') + '</span></td>'
                + '<td><span class="tf-selo tf-selo-status" style="--tf-cor:' + esc(t.status_cor) + '">'
                    + esc(t.status) + '</span></td>'
                + '<td><span class="tf-selo tf-selo-' + esc(t.situacao) + '">' + esc(t.situacao_rotulo) + '</span></td>'
                + '</tr>';
        }).join('');

        $('#tfLista').html(
            '<div class="tf-tabela-caixa"><table class="tf-tabela">'
            + '<thead><tr>' + cab + '</tr></thead><tbody>' + linhas + '</tbody></table></div>'
        );

        $('#tfLista th[data-ordem]').on('click', function () {
            var c = $(this).data('ordem');
            if (estado.ordenar === c) {
                estado.direcao = estado.direcao === 'asc' ? 'desc' : 'asc';
            } else {
                estado.ordenar = c;
                estado.direcao = 'asc';
            }
            buscar(estado.pagina);
        });
    }

    /* ============================================================== */
    /* Renderização — kanban                                          */
    /* ============================================================== */

    function renderKanban(colunas) {
        var $k = $('#tfKanban').empty();

        if (!colunas || !colunas.length) {
            $k.html('<div class="tf-vazio" style="width:100%">'
                + '<i class="fa fa-columns"></i><h4>Nenhuma tarefa no quadro</h4>'
                + '<p>O servidor não devolveu colunas. Se há tarefas cadastradas, '
                + 'confira se a migração foi executada.</p>'
                + '<a class="tf-btn tf-btn-primario" href="migracao_v2.php">'
                + '<i class="fa fa-database"></i> Verificar migração</a></div>');
            return;
        }

        colunas.forEach(function (col) {
            var cards = col.tarefas.map(function (t) {
                return ''
                    + '<div class="tf-kcard" draggable="true" data-id="' + esc(t.id) + '"'
                    + ' data-token="' + esc(t.token) + '"'
                    + ' style="--tf-prio:' + corPrioridade(t.nivel_de_prioridade) + '">'
                    + '  <div class="tf-kcard-titulo">' + esc(corta(t.titulo, 90)) + '</div>'
                    + '  <div class="tf-kcard-rodape">'
                    + '    <span class="tf-card-protocolo">#' + esc(t.id) + '</span>'
                    + '    <span><i class="fa fa-user-o"></i> '
                          + esc(corta(t.funcionario_responsavel || '—', 18)) + '</span>'
                    + (t.data_limite_br
                        ? '<span class="tf-selo tf-selo-' + esc(t.situacao) + '" style="margin-left:auto">'
                          + esc(t.data_limite_br.slice(0, 10)) + '</span>' : '')
                    + '  </div>'
                    + '</div>';
            }).join('');

            $k.append(''
                + '<div class="tf-coluna" data-status="' + esc(col.status) + '">'
                + '  <div class="tf-coluna-topo">'
                + '    <span class="tf-ponto" style="--tf-cor:' + esc(col.cor) + '"></span>'
                + '    <span class="tf-coluna-titulo">' + esc(col.status) + '</span>'
                + '    <span class="tf-coluna-contador">' + col.tarefas.length + '</span>'
                + '  </div>'
                + '  <div class="tf-coluna-corpo" data-status="' + esc(col.status) + '">'
                + (cards || '<div class="tf-coluna-vazia">Arraste tarefas para cá</div>')
                + '  </div>'
                + '</div>');
        });

        ligarArrastar();
    }

    /** Arrastar e soltar entre colunas, em JS puro (sem biblioteca externa). */
    function ligarArrastar() {
        var arrastado = null;

        $('#tfKanban').off('.tfdrag')
            .on('dragstart.tfdrag', '.tf-kcard', function (e) {
                arrastado = this;
                $(this).addClass('tf-arrastando');
                if (e.originalEvent.dataTransfer) {
                    e.originalEvent.dataTransfer.effectAllowed = 'move';
                    e.originalEvent.dataTransfer.setData('text/plain', $(this).data('id'));
                }
            })
            .on('dragend.tfdrag', '.tf-kcard', function () {
                $(this).removeClass('tf-arrastando');
                $('.tf-coluna-corpo').removeClass('tf-alvo');
                arrastado = null;
            })
            .on('dragover.tfdrag', '.tf-coluna-corpo', function (e) {
                e.preventDefault();
                $(this).addClass('tf-alvo');
            })
            .on('dragleave.tfdrag', '.tf-coluna-corpo', function () {
                $(this).removeClass('tf-alvo');
            })
            .on('drop.tfdrag', '.tf-coluna-corpo', function (e) {
                e.preventDefault();
                var $col = $(this).removeClass('tf-alvo');
                if (!arrastado) { return; }

                var novoStatus = $col.data('status');
                var $card = $(arrastado);
                var origem = $card.closest('.tf-coluna-corpo').data('status');

                if (origem === novoStatus) { return; }

                // Move na tela primeiro: a resposta do servidor só confirma.
                $col.find('.tf-coluna-vazia').remove();
                $col.append($card);
                atualizarContadores();

                api('acoes.php', {
                    acao: 'mover_kanban',
                    id: $card.data('id'),
                    status: novoStatus,
                    ordem: $card.index()
                }, 'POST')
                    .done(function (r) {
                        if (!r.success) {
                            dlg.erro('Não foi possível mover', r.error);
                            buscar(estado.pagina);
                        } else if (window.toastr) {
                            toastr.success('Tarefa movida para "' + novoStatus + '".');
                        }
                    })
                    .fail(function (er) {
                        dlg.erro('Não foi possível mover', er.error);
                        buscar(estado.pagina);
                    });
            })
            .on('click.tfdrag', '.tf-kcard', function () {
                abrirPorToken($(this).data('token'));
            });
    }

    function atualizarContadores() {
        $('.tf-coluna').each(function () {
            var n = $(this).find('.tf-kcard').length;
            $(this).find('.tf-coluna-contador').text(n);
            var $corpo = $(this).find('.tf-coluna-corpo');
            if (n === 0 && !$corpo.find('.tf-coluna-vazia').length) {
                $corpo.append('<div class="tf-coluna-vazia">Arraste tarefas para cá</div>');
            }
        });
    }

    /* ============================================================== */
    /* Paginação                                                      */
    /* ============================================================== */

    function renderPaginacao(r) {
        var $p = $('#tfPaginacao').empty();
        if (!r.paginas || r.paginas <= 1) { return; }

        var atual = r.pagina;
        var total = r.paginas;

        function botao(rotulo, pagina, ativo, desativado) {
            return '<button class="tf-pagina' + (ativo ? ' tf-ativo' : '') + '"'
                 + (desativado ? ' disabled' : ' data-pagina="' + pagina + '"')
                 + '>' + rotulo + '</button>';
        }

        var html = botao('<i class="fa fa-angle-left"></i>', atual - 1, false, atual <= 1);

        var inicio = Math.max(1, atual - 2);
        var fim = Math.min(total, inicio + 4);
        inicio = Math.max(1, fim - 4);

        if (inicio > 1) {
            html += botao('1', 1, false, false);
            if (inicio > 2) { html += '<span class="tf-mudo">…</span>'; }
        }
        for (var i = inicio; i <= fim; i++) {
            html += botao(String(i), i, i === atual, false);
        }
        if (fim < total) {
            if (fim < total - 1) { html += '<span class="tf-mudo">…</span>'; }
            html += botao(String(total), total, false, false);
        }

        html += botao('<i class="fa fa-angle-right"></i>', atual + 1, false, atual >= total);
        html += '<span class="tf-mudo tf-mini" style="margin-left:10px">Página ' + atual + ' de ' + total + '</span>';

        $p.html(html);
        $p.find('button[data-pagina]').on('click', function () {
            buscar(parseInt($(this).data('pagina'), 10));
            $('html, body').animate({ scrollTop: $('#tfConteudo').offset().top - 90 }, 250);
        });
    }

    /* ============================================================== */
    /* Seleção em lote                                                */
    /* ============================================================== */

    function alternarSelecao(id, marcado) {
        var s = String(id);
        var i = estado.selecionadas.indexOf(s);
        if (marcado && i === -1) { estado.selecionadas.push(s); }
        if (!marcado && i !== -1) { estado.selecionadas.splice(i, 1); }
        sincronizarSelecao();
    }

    function sincronizarSelecao() {
        var n = estado.selecionadas.length;
        $('#tfLote').toggleClass('tf-visivel', n > 0);
        $('#tfLoteContador').text(n + (n === 1 ? ' tarefa selecionada' : ' tarefas selecionadas'));
        $('.tf-card').each(function () {
            var marcada = estado.selecionadas.indexOf(String($(this).data('id'))) !== -1;
            $(this).toggleClass('tf-marcada', marcada);
            $(this).find('.tf-card-check').prop('checked', marcada);
        });
    }

    function limparSelecao() {
        estado.selecionadas = [];
        sincronizarSelecao();
    }

    function aplicarLote() {
        var operacao = $('#tfLoteOperacao').val();
        var valor = $('#tfLoteValor').val();

        if (!operacao || !valor) {
            dlg.aviso('Escolha a operação e o valor a aplicar.');
            return;
        }

        dlg.confirmar(
            'Aplicar em ' + estado.selecionadas.length + ' tarefa(s)?',
            'A alteração será registrada no histórico de cada tarefa.',
            'Aplicar'
        ).then(function (ok) {
            if (!ok) { return; }
            dlg.carregando('Aplicando…');
            api('acoes.php', {
                acao: 'lote',
                ids: estado.selecionadas.join(','),
                operacao: operacao,
                valor: valor
            }, 'POST')
                .done(function (r) {
                    dlg.fechar();
                    if (!r.success) { dlg.erro('Erro', r.error); return; }
                    limparSelecao();
                    dlg.ok('Pronto', r.mensagem);
                    buscar(estado.pagina);
                    carregarPainel(true);
                })
                .fail(function (e) { dlg.fechar(); dlg.erro('Erro', e.error); });
        });
    }

    /** Preenche o select de valores conforme a operação escolhida. */
    function montarValoresLote() {
        var op = $('#tfLoteOperacao').val();
        var $v = $('#tfLoteValor').empty();
        var lista = [];

        if (op === 'status') { lista = cfg.status || []; }
        else if (op === 'prioridade') { lista = cfg.prioridades || []; }
        else if (op === 'responsavel') { lista = cfg.funcionarios || []; }

        $v.append('<option value="">Selecione…</option>');
        lista.forEach(function (item) {
            var v = typeof item === 'string' ? item : item.valor;
            $v.append($('<option>').attr('value', v).text(v));
        });
    }

    /* ============================================================== */
    /* Visões                                                         */
    /* ============================================================== */

    function trocarVisao(nova) {
        if (estado.visao === nova) { return; }
        estado.visao = nova;

        $('.tf-visao').removeClass('tf-ativo');
        $('.tf-visao[data-visao="' + nova + '"]').addClass('tf-ativo');

        $('#tfPainel, #tfCards, #tfKanban, #tfLista, #tfCalendario').addClass('tf-oculto');
        $('#tfPaginacao').toggle(nova === 'cards' || nova === 'lista');
        $('#tfBarraResultados').toggle(nova !== 'painel');

        try { localStorage.setItem('tf_visao', nova); } catch (e) { /* modo privado */ }

        if (nova === 'painel') {
            $('#tfPainel').removeClass('tf-oculto');
            carregarPainel();
        } else if (nova === 'kanban') {
            $('#tfKanban').removeClass('tf-oculto');
            buscar(1);
        } else if (nova === 'lista') {
            $('#tfLista').removeClass('tf-oculto');
            buscar(1);
        } else if (nova === 'calendario') {
            $('#tfCalendario').removeClass('tf-oculto');
            TarefasCalendario.iniciar();
        } else {
            $('#tfCards').removeClass('tf-oculto');
            buscar(1);
        }
    }

    /* ============================================================== */
    /* Painel de indicadores                                          */
    /* ============================================================== */

    /**
     * Carrega os indicadores.
     *
     * Cada bloco é desenhado isoladamente: se um falhar, os outros continuam
     * aparecendo e o erro fica visível na tela, em vez de deixar uma área em
     * branco sem explicação nenhuma.
     */
    function carregarPainel(forcar) {
        if (estado.painelCarregado && !forcar) { return; }

        api('dashboard.php')
            .done(function (r) {
                if (!r.success) {
                    falhaPainel('#tfGraficos', r.error || 'O servidor não devolveu os indicadores.');
                    return;
                }
                estado.painelCarregado = true;

                desenhar('#tfKpis',     function () { renderKpis(r.cartoes); });
                desenhar('#tfGraficos', function () { renderGraficos(r); });
                desenhar('#tfAtencao',  function () { renderAtencao(r.atencao); });
            })
            .fail(function (e) {
                falhaPainel('#tfGraficos', e.error || 'Falha ao consultar os indicadores.');
            });
    }

    /** Executa um bloco de renderização isolando eventuais erros. */
    function desenhar(seletor, fn) {
        try {
            fn();
        } catch (e) {
            if (window.console) { console.error('[tarefas] falha ao desenhar ' + seletor, e); }
            falhaPainel(seletor, e.message);
        }
    }

    function falhaPainel(seletor, mensagem) {
        $(seletor).html(
            '<div class="tf-grafico" style="border-color:var(--tf-perigo)">'
            + '<h4 style="color:var(--tf-perigo)">Não foi possível montar esta parte</h4>'
            + '<p class="tf-mini tf-mudo" style="margin:0">' + esc(mensagem) + '</p></div>'
        );
    }

    function renderKpis(c) {
        var itens = [
            { chave: '', cor: '#3b82f6', icone: 'fa-tasks', rotulo: 'Em aberto', valor: c.abertas },
            { chave: 'vencida', cor: '#dc2626', icone: 'fa-exclamation-triangle', rotulo: 'Vencidas', valor: c.vencidas },
            { chave: 'hoje', cor: '#f59e0b', icone: 'fa-calendar-check-o', rotulo: 'Vencem hoje', valor: c.hoje },
            { chave: 'semana', cor: '#eab308', icone: 'fa-calendar', rotulo: 'Próximos 7 dias', valor: c.semana },
            { chave: 'minhas', cor: '#7c3aed', icone: 'fa-user', rotulo: 'Minhas', valor: c.minhas },
            { chave: '', cor: '#16a34a', icone: 'fa-check-circle', rotulo: 'Concluídas no mês', valor: c.concluidas_mes },
            {
                chave: '', cor: '#0ea5e9', icone: 'fa-clock-o', rotulo: 'Tempo médio',
                valor: c.tempo_medio_horas === null ? '—' : formatarHoras(c.tempo_medio_horas),
                extra: 'últimos 90 dias', bruto: true
            },
            {
                chave: '', cor: '#14b8a6', icone: 'fa-bullseye', rotulo: 'Cumprimento de prazo',
                valor: c.taxa_prazo === null ? '—' : c.taxa_prazo + '%',
                extra: 'últimos 90 dias', bruto: true
            }
        ];

        $('#tfKpis').html(itens.map(function (i) {
            return '<div class="tf-kpi' + (estado.atalho && estado.atalho === i.chave ? ' tf-ativo' : '') + '"'
                 + ' style="--tf-cor:' + i.cor + '"'
                 + (i.chave ? ' data-atalho="' + i.chave + '"' : '') + '>'
                 + '<div class="tf-kpi-valor">'
                 + (i.bruto ? esc(i.valor) : (i.valor || 0).toLocaleString('pt-BR')) + '</div>'
                 + '<div class="tf-kpi-rotulo"><i class="fa ' + i.icone + '"></i> ' + i.rotulo + '</div>'
                 + (i.extra ? '<div class="tf-kpi-extra">' + i.extra + '</div>' : '')
                 + '</div>';
        }).join(''));

        $('#tfKpis .tf-kpi[data-atalho]').on('click', function () {
            aplicarAtalho($(this).data('atalho'));
            trocarVisao('cards');
        });
    }

    function formatarHoras(h) {
        if (h < 24) { return Math.round(h) + 'h'; }
        var d = h / 24;
        return (d < 10 ? d.toFixed(1) : Math.round(d)) + ' dias';
    }

    function renderGraficos(r) {
        /**
         * Barras horizontais proporcionais.
         *
         * A largura da barra e o percentual exibido são calculados sobre o
         * TOTAL do grupo, não sobre o maior item. Assim o que se vê na barra e
         * o número ao lado dizem a mesma coisa: a fatia daquele item no
         * conjunto.
         */
        function barras(titulo, dados, campoNome, campoValor, corPadrao) {
            if (!dados || !dados.length) { return ''; }

            var total = dados.reduce(function (soma, d) {
                return soma + (+d[campoValor] || 0);
            }, 0);

            if (total <= 0) {
                return '<div class="tf-grafico"><h4>' + titulo + '</h4>'
                     + '<p class="tf-mini tf-mudo" style="margin:0">Sem dados no período.</p></div>';
            }

            var linhas = dados.map(function (d) {
                var v = +d[campoValor] || 0;
                var pct = v * 100 / total;
                var cor = d.cor || corPadrao;
                var rotulo = d[campoNome];

                return '<div class="tf-barra-linha" title="'
                     + esc(rotulo + ': ' + v + ' de ' + total + ' (' + pct.toFixed(1) + '%)') + '">'
                     + '<span class="tf-barra-nome">' + esc(rotulo) + '</span>'
                     + '<span class="tf-barra-trilho"><span class="tf-barra-preench" style="width:'
                     + pct.toFixed(1) + '%;--tf-cor:' + esc(cor) + '"></span></span>'
                     + '<span class="tf-barra-valor">' + v + '</span>'
                     + '<span class="tf-barra-pct">' + (pct >= 10 ? Math.round(pct) : pct.toFixed(1)) + '%</span>'
                     + '</div>';
            }).join('');

            return '<div class="tf-grafico"><h4>' + titulo + '</h4>' + linhas
                 + '<p class="tf-mini tf-mudo" style="margin:10px 0 0">Total: ' + total + '</p></div>';
        }

        var mov = r.movimentacao || [];
        var maxMov = Math.max.apply(null, mov.map(function (d) {
            return Math.max(d.criadas, d.concluidas);
        })) || 1;

        var spark = '<div class="tf-grafico"><h4>Movimentação · últimos 30 dias</h4><div class="tf-spark">'
            + mov.map(function (d) {
                return '<div class="tf-spark-col" title="' + esc(d.rotulo) + ': ' + d.criadas
                     + ' criada(s), ' + d.concluidas + ' concluída(s)">'
                     + '<div class="tf-spark-a" style="height:' + Math.round(d.criadas * 38 / maxMov) + 'px"></div>'
                     + '<div class="tf-spark-b" style="height:' + Math.round(d.concluidas * 38 / maxMov) + 'px"></div>'
                     + '</div>';
            }).join('')
            + '</div><div class="tf-legenda">'
            + '<span><i style="background:var(--tf-primaria-2)"></i> Criadas</span>'
            + '<span><i style="background:var(--tf-sucesso)"></i> Concluídas</span>'
            + '</div></div>';

        $('#tfGraficos').html(
            spark
            + barras('Por status', r.por_status, 'status', 'total', '#3b82f6')
            + barras('Por responsável', r.por_responsavel, 'responsavel', 'total', '#7c3aed')
            + barras('Por categoria', r.por_categoria, 'categoria', 'total', '#0ea5e9')
            + barras('Por prioridade', r.por_prioridade, 'prioridade', 'total', '#f59e0b')
        );
    }

    function renderAtencao(lista) {
        if (!lista || !lista.length) {
            $('#tfAtencao').html('<div class="tf-grafico"><h4>Prazos próximos</h4>'
                + '<p class="tf-mudo tf-mini" style="margin:0">Nenhuma tarefa com prazo nos próximos 3 dias. '
                + 'Bom trabalho.</p></div>');
            return;
        }

        var linhas = lista.map(function (t) {
            return '<div class="tf-anexo" style="cursor:pointer" data-token="' + esc(t.token) + '">'
                 + '<div class="tf-anexo-icone" style="background:transparent"><i class="fa fa-clock-o"></i></div>'
                 + '<div class="tf-anexo-nome"><strong>#' + esc(t.id) + '</strong> ' + esc(corta(t.titulo, 60))
                 + '<small>' + esc(t.funcionario_responsavel || 'Sem responsável') + ' · '
                 + esc(t.data_limite_br) + '</small></div>'
                 + '<span class="tf-selo tf-selo-' + esc(t.situacao) + '">' + esc(t.situacao_rotulo) + '</span>'
                 + '</div>';
        }).join('');

        $('#tfAtencao').html(
            '<div class="tf-grafico"><h4>Precisam de atenção</h4>'
            + '<div class="tf-anexos">' + linhas + '</div></div>'
        );

        $('#tfAtencao .tf-anexo').on('click', function () {
            abrirPorToken($(this).data('token'));
        });
    }

    /* ============================================================== */
    /* Atalhos de filtro                                              */
    /* ============================================================== */

    function aplicarAtalho(chave) {
        estado.atalho = (estado.atalho === chave) ? '' : chave;
        $('.tf-chip[data-atalho]').removeClass('tf-ativo');
        if (estado.atalho) {
            $('.tf-chip[data-atalho="' + estado.atalho + '"]').addClass('tf-ativo');
        }
        buscar(1);
    }

    /* ============================================================== */
    /* Abertura da tarefa                                             */
    /* ============================================================== */

    function abrir(elemento, evento) {
        // Cliques no checkbox de seleção não abrem a tarefa.
        if (evento && $(evento.target).is('.tf-card-check')) { return; }
        abrirPorToken($(elemento).data('token'));
    }

    function abrirPorToken(token) {
        if (!token) { return; }
        TarefasDetalhe.abrir(token);
    }

    /* ============================================================== */
    /* Busca por IA                                                   */
    /* ============================================================== */

    function buscaInteligente() {
        var pergunta = txt($('#tfBusca').val() || '');
        if (pergunta === '') {
            dlg.aviso('Escreva o que você procura, por exemplo: "escrituras atrasadas da Maria".');
            return;
        }

        dlg.carregando('Interpretando a busca…');

        api('ia.php', { recurso: 'interpretar_busca', pergunta: pergunta }, 'POST')
            .done(function (r) {
                dlg.fechar();
                if (!r.success) { dlg.erro('Não foi possível interpretar', r.error); return; }

                var f = r.filtros || {};
                $('#tfFormFiltros')[0].reset();

                ['category', 'employee', 'revisor', 'status', 'priority', 'dateStart', 'dateEnd']
                    .forEach(function (campo) {
                        if (f[campo]) {
                            $('#tfFormFiltros [name="' + campo + '"]').val(f[campo]);
                        }
                    });

                $('#tfBusca').val(f.texto || '');
                estado.atalho = f.situacao || '';
                $('.tf-chip[data-atalho]').removeClass('tf-ativo');
                if (estado.atalho) {
                    $('.tf-chip[data-atalho="' + estado.atalho + '"]').addClass('tf-ativo');
                }

                atualizarSeloFiltros();
                $('#tfFiltros').slideDown(160);
                buscar(1);

                if (f.explicacao && window.toastr) {
                    toastr.info(f.explicacao, 'Busca interpretada');
                }
            })
            .fail(function (e) {
                dlg.fechar();
                dlg.erro('Busca com IA indisponível', e.error);
            });
    }

    /* ============================================================== */
    /* Exportação                                                     */
    /* ============================================================== */

    function exportarCsv() {
        var params = $.extend({}, lerFiltros(), {
            ordenar: estado.ordenar,
            direcao: estado.direcao
        });
        window.location.href = 'api/exportar.php?' + $.param(params);
    }

    /* ============================================================== */
    /* Inicialização                                                  */
    /* ============================================================== */

    /**
     * Convivência entre modais empilhados.
     *
     * Guia, recibo, subtarefa e arquivamento são abertos de dentro do modal de
     * detalhe, ou seja, um modal por cima do outro. O Bootstrap não gerencia
     * isso sozinho: o modal de cima pode nascer atrás do fundo escurecido, e ao
     * fechá-lo o `modal-open` sai do <body>, deixando o modal de baixo sem
     * rolagem e com a página deslocada.
     *
     * Aqui cada modal aberto recebe um z-index acima do anterior — e o fundo
     * escurecido correspondente, logo abaixo dele — e o `modal-open` é
     * devolvido ao <body> enquanto ainda houver algum modal aberto.
     */
    function empilharModais() {
        var BASE = 1050;
        var PASSO = 20;

        $(document).on('show.bs.modal', '.modal', function () {
            var abertos = $('.modal.show').length;
            var z = BASE + abertos * PASSO;
            $(this).css('z-index', z);

            // O fundo é criado depois; por isso o ajuste sai no próximo ciclo.
            setTimeout(function () {
                $('.modal-backdrop:not(.tf-empilhado)')
                    .addClass('tf-empilhado')
                    .css('z-index', z - 10);
            }, 0);
        });

        $(document).on('hidden.bs.modal', '.modal', function () {
            $(this).css('z-index', '');
            if ($('.modal.show').length > 0) {
                $('body').addClass('modal-open');
            }
        });
    }

    function iniciar() {
        empilharModais();

        // Restaura a última visão utilizada.
        var salva = null;
        try { salva = localStorage.getItem('tf_visao'); } catch (e) { salva = null; }

        $('.tf-visao').on('click', function () { trocarVisao($(this).data('visao')); });
        $('.tf-chip[data-atalho]').on('click', function () { aplicarAtalho($(this).data('atalho')); });

        $('#tfFormFiltros').on('submit', function (e) {
            e.preventDefault();
            atualizarSeloFiltros();
            buscar(1);
        });

        $('#tfFormFiltros').on('change', 'select', function () {
            atualizarSeloFiltros();
            buscar(1);
        });

        $('#tfBusca').on('input', buscarComAtraso);
        $('#tfBusca').on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); buscar(1); }
        });

        $('#tfBtnFiltros').on('click', function () {
            $('#tfFiltros').slideToggle(160);
            $(this).find('i.fa-angle-down, i.fa-angle-up')
                   .toggleClass('fa-angle-down fa-angle-up');
        });

        $('#tfBtnLimpar').on('click', limparFiltros);
        $('#tfBtnBuscaIA').on('click', buscaInteligente);
        $('#tfBtnExportar').on('click', exportarCsv);
        $('#tfBtnAtualizar').on('click', function () {
            buscar(estado.pagina);
            carregarPainel(true);
        });

        $('#tfPorPagina').on('change', function () {
            estado.porPagina = parseInt($(this).val(), 10) || 24;
            buscar(1);
        });

        $('#tfOrdenar, #tfDirecao').on('change', function () {
            estado.ordenar = $('#tfOrdenar').val();
            estado.direcao = $('#tfDirecao').val();
            buscar(estado.pagina);
        });

        $(document).on('change', '.tf-card-check', function (e) {
            e.stopPropagation();
            alternarSelecao($(this).data('id'), this.checked);
        });

        $('#tfLoteOperacao').on('change', montarValoresLote);
        $('#tfLoteAplicar').on('click', aplicarLote);
        $('#tfLoteLimpar').on('click', limparSelecao);

        // Atalhos de teclado.
        $(document).on('keydown', function (e) {
            if ($(e.target).is('input, textarea, select')) { return; }
            if (e.key === '/') { e.preventDefault(); $('#tfBusca').focus(); }
            if (e.key === 'n' && !e.ctrlKey && !e.metaKey) { window.location.href = 'criar-tarefa.php'; }
        });

        // Painel carrega sempre, mesmo quando a visão inicial é outra.
        carregarPainel();

        var inicial = salva || 'painel';
        estado.visao = null;
        trocarVisao(inicial);

        // Abertura direta via ?token= ou ?id=, usada pelos outros módulos.
        var url = new URLSearchParams(window.location.search);
        var token = url.get('token');
        var id = url.get('id');
        if (token) {
            abrirPorToken(token);
        } else if (id) {
            api('tarefa.php', { id: id }).done(function (r) {
                if (r.success && r.tarefa) { abrirPorToken(r.tarefa.token); }
            });
        }
    }

    /* ============================================================== */
    /* API pública                                                    */
    /* ============================================================== */

    return {
        iniciar: iniciar,
        api: api,
        dlg: dlg,
        esc: esc,
        corta: corta,
        opcoesDialogo: opcoesDialogo,
        iconeArquivo: iconeArquivo,
        corPrioridade: corPrioridade,
        buscar: buscar,
        limparFiltros: limparFiltros,
        trocarVisao: trocarVisao,
        abrir: abrir,
        abrirPorToken: abrirPorToken,
        carregarPainel: carregarPainel,
        lerFiltros: lerFiltros,
        estado: estado,
        cfg: cfg
    };

})(jQuery);
