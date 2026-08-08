/*!
 * Atlas · Tarefas — modal de detalhe da tarefa.
 *
 * Reúne em abas tudo o que antes ficava empilhado numa única tela rolante:
 * dados, anexos, checklist, linha do tempo, assistente de IA e histórico de
 * alterações. Todas as ações do módulo antigo continuam disponíveis na barra
 * superior (protocolo geral, guia de recebimento, recibo de entrega, vincular
 * ofício, arquivar ato, criar subtarefa e editar).
 */

/* global jQuery, Tarefas, Swal */
var TarefasDetalhe = (function ($) {
    'use strict';

    var atual = null;         // tarefa carregada
    var esc = null;           // atalhos preenchidos na inicialização
    var api = null;
    var dlg = null;

    /**
     * Substitui $.trim, que foi marcado como obsoleto no jQuery 3.5 e removido
     * no 4. Como o Atlas pode atualizar o jQuery a qualquer momento, o módulo
     * não depende mais dele.
     */
    function txt(v) {
        return (v === null || v === undefined) ? '' : String(v).trim();
    }

    function init() {
        esc = Tarefas.esc;
        api = Tarefas.api;
        dlg = Tarefas.dlg;
    }

    /* ============================================================== */
    /* Abertura                                                       */
    /* ============================================================== */

    function abrir(token) {
        if (!esc) { init(); }

        $('#tfModalDetalhe').modal('show');
        $('#tfDetalheCorpo').html(
            '<div style="padding:60px;text-align:center" class="tf-mudo">'
            + '<i class="fa fa-circle-o-notch tf-girando fa-2x"></i>'
            + '<p style="margin-top:14px">Carregando tarefa…</p></div>'
        );

        api('tarefa.php', { token: token })
            .done(function (r) {
                if (!r.success) {
                    $('#tfDetalheCorpo').html('<div class="tf-vazio"><i class="fa fa-exclamation-triangle"></i>'
                        + '<h4>Não foi possível abrir</h4><p>' + esc(r.error) + '</p></div>');
                    return;
                }
                atual = r.tarefa;
                render();
            })
            .fail(function (e) {
                $('#tfDetalheCorpo').html('<div class="tf-vazio"><i class="fa fa-exclamation-triangle"></i>'
                    + '<h4>Erro</h4><p>' + esc(e.error) + '</p></div>');
            });
    }

    function recarregar() {
        if (atual) { abrir(atual.token); }
    }

    /* ============================================================== */
    /* Render principal                                               */
    /* ============================================================== */

    function render() {
        var t = atual;

        $('#tfDetalheProtocolo').text(t.id);
        $('#tfDetalheTitulo').text(Tarefas.corta(t.titulo, 70));

        // Barra de ações — respeita as permissões devolvidas pelo servidor.
        $('#tfAcaoEditar').toggle(!!t.permissoes.editar);
        $('#tfAcaoExcluir').toggle(!!t.permissoes.excluir);

        $('#tfDetalheCorpo').html(
            painelGeral(t)
            + painelAnexos(t)
            + painelChecklist(t)
            + painelTempo(t)
            + painelIA(t)
            + painelHistorico(t)
        );

        // Contadores das abas.
        $('#tfAbaAnexos .tf-aba-contador').text(t.anexos.length);
        $('#tfAbaTempo .tf-aba-contador').text(t.comentarios.length);
        $('#tfAbaCheck .tf-aba-contador').text(t.checklist.length);
        $('#tfAbaHist .tf-aba-contador').text(t.historico.length);

        trocarAba('geral');
        ligarEventos();
    }

    /* ============================================================== */
    /* Aba — visão geral                                              */
    /* ============================================================== */

    function painelGeral(t) {
        function item(rotulo, valor, icone) {
            return '<div class="tf-info-item">'
                 + '<label class="tf-rotulo">' + (icone ? '<i class="fa ' + icone + '"></i> ' : '') + rotulo + '</label>'
                 + '<div class="tf-info-valor">' + (valor ? esc(valor) : '<span class="tf-mudo">—</span>') + '</div>'
                 + '</div>';
        }

        var opcoesStatus = (Tarefas.cfg.status || []).map(function (s) {
            return '<option value="' + esc(s) + '"' + (s === t.status ? ' selected' : '') + '>' + esc(s) + '</option>';
        }).join('');

        var alertaPrazo = '';
        if (t.situacao === 'vencida') {
            alertaPrazo = '<div class="tf-bloco" style="background:rgba(220,38,38,.08);border-color:rgba(220,38,38,.3)">'
                + '<strong style="color:var(--tf-perigo)"><i class="fa fa-exclamation-triangle"></i> '
                + 'Prazo vencido</strong> <span class="tf-mudo tf-mini">— venceu em ' + esc(t.data_limite_br)
                + '.</span></div>';
        } else if (t.situacao === 'hoje') {
            alertaPrazo = '<div class="tf-bloco" style="background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.35)">'
                + '<strong style="color:#b45309"><i class="fa fa-clock-o"></i> Vence hoje</strong> '
                + '<span class="tf-mudo tf-mini">— às ' + esc((t.data_limite_br || '').slice(11)) + '.</span></div>';
        }

        var vinculo = '';
        if (t.tarefa_principal) {
            vinculo += '<div class="tf-bloco"><div class="tf-bloco-titulo">'
                + '<i class="fa fa-level-up"></i> Tarefa principal</div>'
                + '<div class="tf-anexo" style="cursor:pointer" data-abrir="' + esc(t.tarefa_principal.token) + '">'
                + '<div class="tf-anexo-icone"><i class="fa fa-tasks"></i></div>'
                + '<div class="tf-anexo-nome"><strong>#' + esc(t.tarefa_principal.id) + '</strong> '
                + esc(t.tarefa_principal.titulo)
                + '<small>' + esc(t.tarefa_principal.funcionario_responsavel || '—')
                + ' · prazo ' + esc(t.tarefa_principal.data_limite_br || '—') + '</small></div>'
                + '<span class="tf-selo tf-selo-status" style="--tf-cor:' + esc(t.tarefa_principal.status_cor) + '">'
                + esc(t.tarefa_principal.status) + '</span></div></div>';
        }

        if (t.subtarefas.length) {
            vinculo += '<div class="tf-bloco"><div class="tf-bloco-titulo">'
                + '<i class="fa fa-sitemap"></i> Subtarefas (' + t.subtarefas.length + ')'
                + '<button class="tf-btn tf-btn-sm" id="tfNovaSubtarefa"><i class="fa fa-plus"></i> Nova</button>'
                + '</div><div class="tf-anexos">'
                + t.subtarefas.map(function (s) {
                    return '<div class="tf-anexo" style="cursor:pointer" data-abrir="' + esc(s.token) + '">'
                        + '<div class="tf-anexo-icone"><i class="fa fa-check-square-o"></i></div>'
                        + '<div class="tf-anexo-nome"><strong>#' + esc(s.id) + '</strong> ' + esc(s.titulo)
                        + '<small>' + esc(s.funcionario_responsavel || '—')
                        + ' · prazo ' + esc(s.data_limite_br || '—') + '</small></div>'
                        + '<span class="tf-selo tf-selo-status" style="--tf-cor:' + esc(s.status_cor) + '">'
                        + esc(s.status) + '</span></div>';
                }).join('')
                + '</div></div>';
        }

        var resumoIA = '';
        if (t.ia_resumo) {
            resumoIA = '<div class="tf-ia-caixa">'
                + '<div class="tf-ia-titulo"><i class="fa fa-magic"></i> Resumo gerado pela IA</div>'
                + '<div class="tf-ia-saida">' + esc(t.ia_resumo) + '</div>'
                + '<div class="tf-ia-rodape"><i class="fa fa-info-circle"></i> '
                + 'Conteúdo gerado automaticamente — confira antes de usar.</div></div>';
        }

        return ''
            + '<div class="tf-aba-painel" data-painel="geral">'
            + alertaPrazo
            + '<div class="tf-info-grade">'
            + item('Título', t.titulo, 'fa-file-text-o')
            + item('Categoria', t.categoria_titulo, 'fa-folder-o')
            + item('Origem', t.origem_titulo, 'fa-sign-in')
            + item('Data limite', t.data_limite_br, 'fa-calendar-o')
            + item('Responsável', t.funcionario_responsavel, 'fa-user-o')
            + item('Revisor', t.revisor, 'fa-eye')
            + item('Prioridade', t.nivel_de_prioridade, 'fa-flag-o')
            + item('Conclusão', t.data_conclusao_br, 'fa-check')
            + (t.apresentante ? item('Apresentante', t.apresentante, 'fa-user-circle-o') : '')
            + (t.numero_oficio ? item('Ofício vinculado', t.numero_oficio, 'fa-link') : '')
            + '</div>'

            + '<div class="tf-bloco"><div class="tf-bloco-titulo"><i class="fa fa-align-left"></i> Descrição</div>'
            + '<div style="white-space:pre-wrap;line-height:1.6;font-size:.88rem">'
            + (t.descricao ? esc(t.descricao) : '<span class="tf-mudo">Sem descrição.</span>')
            + '</div></div>'

            + resumoIA

            + '<div class="tf-bloco"><div class="tf-bloco-titulo"><i class="fa fa-exchange"></i> Situação</div>'
            + '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">'
            + '<select class="tf-select" id="tfStatusSelect" style="max-width:280px">' + opcoesStatus + '</select>'
            + '<button class="tf-btn tf-btn-primario" id="tfSalvarStatus">'
            + '<i class="fa fa-check"></i> Aplicar</button>'
            + '<button class="tf-btn" id="tfAssumir"><i class="fa fa-hand-paper-o"></i> Assumir</button>'
            + '</div></div>'

            + vinculo

            + '<div class="tf-bloco"><div class="tf-bloco-titulo"><i class="fa fa-info-circle"></i> Registro</div>'
            + '<div class="tf-card-meta">'
            + '<span><i class="fa fa-user-plus"></i> Criada por ' + esc(t.criado_por || '—') + '</span>'
            + '<span><i class="fa fa-calendar-plus-o"></i> ' + esc(t.data_criacao_br || '—') + '</span>'
            + (t.atualizado_por ? '<span><i class="fa fa-pencil"></i> Última alteração por '
                + esc(t.atualizado_por) + ' em ' + esc(t.data_atualizacao_br) + '</span>' : '')
            + '</div></div>'
            + '</div>';
    }

    /* ============================================================== */
    /* Aba — anexos                                                   */
    /* ============================================================== */

    function painelAnexos(t) {
        var lista = t.anexos.length
            ? t.anexos.map(function (a) {
                var podeIA = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'txt'].indexOf(a.ext) !== -1;
                return '<div class="tf-anexo' + (a.existe ? '' : ' tf-sumido') + '">'
                    + '<div class="tf-anexo-icone"><i class="fa ' + Tarefas.iconeArquivo(a.ext) + '"></i></div>'
                    + '<div class="tf-anexo-nome">' + esc(a.nome)
                    + '<small>' + (a.existe ? esc(a.tamanho_br)
                        : '<span style="color:var(--tf-perigo)">arquivo não encontrado no servidor</span>')
                    + '</small></div>'
                    + '<div class="tf-anexo-acoes">'
                    + (a.existe
                        ? '<a class="tf-btn tf-btn-sm" href="' + esc(a.url) + '" target="_blank" title="Abrir">'
                          + '<i class="fa fa-external-link"></i></a>'
                          + '<a class="tf-btn tf-btn-sm" href="' + esc(a.url) + '" download title="Baixar">'
                          + '<i class="fa fa-download"></i></a>'
                        : '')
                    + (podeIA && a.existe
                        ? '<button class="tf-btn tf-btn-sm tf-btn-ia" data-analisar="' + esc(a.rel)
                          + '" title="Analisar com IA"><i class="fa fa-magic"></i></button>' : '')
                    + (t.permissoes.editar
                        ? '<button class="tf-btn tf-btn-sm" data-excluir-anexo="' + esc(a.rel)
                          + '" title="Excluir"><i class="fa fa-trash-o"></i></button>' : '')
                    + '</div></div>';
            }).join('')
            : '<p class="tf-mudo tf-mini">Nenhum anexo nesta tarefa.</p>';

        return ''
            + '<div class="tf-aba-painel" data-painel="anexos">'
            + (t.permissoes.editar
                ? '<div class="tf-zona-upload" id="tfZonaUpload">'
                  + '<i class="fa fa-cloud-upload"></i>'
                  + '<strong>Arraste arquivos aqui</strong> ou clique para selecionar'
                  + '<div class="tf-mini tf-mudo" style="margin-top:6px">'
                  + 'PDF, imagens, Office, ZIP e afins — até 40 MB por arquivo</div>'
                  + '<input type="file" id="tfArquivoInput" multiple style="display:none">'
                  + '</div><div id="tfFilaUpload" style="margin:12px 0"></div>' : '')
            + '<div class="tf-anexos" style="margin-top:14px">' + lista + '</div>'
            + '<div id="tfSaidaAnexoIA" style="margin-top:14px"></div>'
            + '</div>';
    }

    /* ============================================================== */
    /* Aba — checklist                                                */
    /* ============================================================== */

    function painelChecklist(t) {
        var feitos = t.checklist.filter(function (i) { return i.concluido; }).length;
        var pct = t.checklist.length ? Math.round(feitos * 100 / t.checklist.length) : 0;

        var itens = t.checklist.length
            ? t.checklist.map(function (i) {
                return '<div class="tf-check-item' + (i.concluido ? ' tf-feito' : '') + '" data-item="' + i.id + '">'
                    + '<input type="checkbox"' + (i.concluido ? ' checked' : '') + '>'
                    + '<div class="tf-check-texto">' + esc(i.descricao)
                    + (i.origem === 'ia' ? ' <span class="tf-selo tf-selo-contorno" style="--tf-cor:#7c3aed">IA</span>' : '')
                    + (i.concluido && i.concluido_por
                        ? '<small>concluído por ' + esc(i.concluido_por) + '</small>' : '')
                    + '</div>'
                    + '<button class="tf-btn tf-btn-sm" data-excluir-item="' + i.id + '" title="Remover">'
                    + '<i class="fa fa-times"></i></button></div>';
            }).join('')
            : '<p class="tf-mudo tf-mini">Nenhum item de conferência. Adicione manualmente ou peça sugestões à IA.</p>';

        return ''
            + '<div class="tf-aba-painel" data-painel="checklist">'
            + (t.checklist.length
                ? '<div class="tf-progresso"><div style="width:' + pct + '%"></div></div>'
                  + '<p class="tf-mini tf-mudo">' + feitos + ' de ' + t.checklist.length
                  + ' concluído(s) — ' + pct + '%</p>' : '')
            + '<div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">'
            + '<input type="text" class="tf-input" id="tfNovoItem" placeholder="Novo item de conferência…" style="flex:1;min-width:200px">'
            + '<button class="tf-btn tf-btn-primario" id="tfAddItem"><i class="fa fa-plus"></i> Adicionar</button>'
            + '<button class="tf-btn tf-btn-ia" id="tfSugerirCheck"><i class="fa fa-magic"></i> Sugerir com IA</button>'
            + '</div>'
            + '<div class="tf-check-lista">' + itens + '</div>'
            + '</div>';
    }

    /* ============================================================== */
    /* Aba — linha do tempo                                           */
    /* ============================================================== */

    function painelTempo(t) {
        var itens = t.comentarios.length
            ? t.comentarios.map(function (c) {
                var anexos = (c.anexos || []).map(function (a) {
                    return '<a class="tf-btn tf-btn-sm" href="' + esc(a.url) + '" target="_blank">'
                        + '<i class="fa ' + Tarefas.iconeArquivo(a.ext) + '"></i> ' + esc(a.nome) + '</a>';
                }).join(' ');

                return '<div class="tf-tempo-item' + (c.is_subtask ? ' tf-sub' : '') + '">'
                    + '<div class="tf-tempo-marca"><i class="fa fa-comment"></i></div>'
                    + '<div class="tf-tempo-caixa">'
                    + '<div class="tf-tempo-cabec">'
                    + '<span class="tf-tempo-autor">' + esc(c.funcionario || '—') + '</span>'
                    + '<span>' + esc(c.data_br) + '</span>'
                    + (c.atualizado_br ? '<span class="tf-mini">(editado em ' + esc(c.atualizado_br) + ')</span>' : '')
                    + (c.is_subtask ? '<span class="tf-selo tf-selo-contorno" style="--tf-cor:#f59e0b">subtarefa</span>' : '')
                    + '<span class="tf-tempo-acoes">'
                    + '<button class="tf-btn tf-btn-sm" data-editar-com="' + c.id + '" title="Editar">'
                    + '<i class="fa fa-pencil"></i></button>'
                    + '<button class="tf-btn tf-btn-sm" data-excluir-com="' + c.id + '" title="Excluir">'
                    + '<i class="fa fa-trash-o"></i></button>'
                    + '</span></div>'
                    + '<div class="tf-tempo-texto">' + esc(c.comentario || '') + '</div>'
                    + (anexos ? '<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">' + anexos + '</div>' : '')
                    + '</div></div>';
            }).join('')
            : '<p class="tf-mudo tf-mini">Nenhum registro de andamento ainda.</p>';

        return ''
            + '<div class="tf-aba-painel" data-painel="tempo">'
            + '<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">'
            + '<button class="tf-btn tf-btn-primario" id="tfNovoComentario">'
            + '<i class="fa fa-comment-o"></i> Registrar andamento</button>'
            + '<button class="tf-btn tf-btn-ia" id="tfRedigirIA">'
            + '<i class="fa fa-magic"></i> Redigir com IA</button>'
            + '</div>'
            + '<div class="tf-tempo">' + itens + '</div>'
            + '</div>';
    }

    /* ============================================================== */
    /* Aba — IA                                                       */
    /* ============================================================== */

    function painelIA(t) {
        if (!Tarefas.cfg.ia_ativa) {
            return '<div class="tf-aba-painel" data-painel="ia">'
                + '<div class="tf-ia-desligada"><i class="fa fa-magic"></i>'
                + '<h4>Assistente de IA não configurado</h4>'
                + '<p class="tf-mudo">Cadastre a chave da API do Gemini para usar resumo automático, '
                + 'sugestão de próximos passos, redação assistida e leitura de anexos.</p>'
                + (Tarefas.cfg.admin
                    ? '<a class="tf-btn tf-btn-ia" href="configuracoes-ia.php" style="margin-top:12px">'
                      + '<i class="fa fa-cog"></i> Configurar agora</a>'
                    : '<p class="tf-mini tf-mudo">Peça ao administrador do sistema para configurar.</p>')
                + '</div></div>';
        }

        return ''
            + '<div class="tf-aba-painel" data-painel="ia">'
            + '<div class="tf-ia-caixa">'
            + '<div class="tf-ia-titulo"><i class="fa fa-magic"></i> O que a IA pode fazer nesta tarefa</div>'
            + '<div class="tf-ia-acoes">'
            + '<button class="tf-btn tf-btn-sm tf-btn-ia" data-ia="resumir">'
            + '<i class="fa fa-file-text-o"></i> Resumir</button>'
            + '<button class="tf-btn tf-btn-sm tf-btn-ia" data-ia="proximos_passos">'
            + '<i class="fa fa-list-ol"></i> Próximos passos</button>'
            + '<button class="tf-btn tf-btn-sm tf-btn-ia" data-ia="redigir">'
            + '<i class="fa fa-pencil-square-o"></i> Redigir texto</button>'
            + '</div>'
            + '<div class="tf-ia-saida" id="tfIASaida"></div>'
            + '<div class="tf-ia-rodape" id="tfIARodape"></div>'
            + '</div>'

            + '<div class="tf-bloco">'
            + '<div class="tf-bloco-titulo"><i class="fa fa-comments-o"></i> Perguntar sobre esta tarefa'
            + '<button class="tf-btn tf-btn-sm" id="tfIALimpar" title="Reiniciar conversa">'
            + '<i class="fa fa-refresh"></i></button></div>'
            + '<div class="tf-ia-chat" id="tfIAChat"></div>'
            + '<div style="display:flex;gap:8px">'
            + '<input type="text" class="tf-input" id="tfIAPergunta" style="flex:1"'
            + ' placeholder="Ex.: o que falta para eu concluir esta tarefa?">'
            + '<button class="tf-btn tf-btn-ia" id="tfIAEnviar"><i class="fa fa-paper-plane"></i></button>'
            + '</div>'
            + '<p class="tf-mini tf-mudo" style="margin:10px 0 0">'
            + 'A IA responde com base nos dados desta tarefa. Confira sempre antes de usar em ato oficial.</p>'
            + '</div>'
            + '</div>';
    }

    /* ============================================================== */
    /* Aba — histórico                                                */
    /* ============================================================== */

    function painelHistorico(t) {
        var icones = {
            criacao: 'fa-plus', status: 'fa-exchange', edicao: 'fa-pencil',
            anexo: 'fa-paperclip', comentario: 'fa-comment', oficio: 'fa-link',
            checklist: 'fa-check-square-o', exclusao: 'fa-trash'
        };

        var itens = t.historico.length
            ? t.historico.map(function (h) {
                var mudanca = '';
                if (h.valor_anterior || h.valor_novo) {
                    mudanca = '<div class="tf-mini tf-mudo" style="margin-top:4px">'
                        + (h.valor_anterior ? '<s>' + esc(h.valor_anterior) + '</s> → ' : '')
                        + '<strong>' + esc(h.valor_novo || '—') + '</strong></div>';
                }
                return '<div class="tf-tempo-item tf-sistema">'
                    + '<div class="tf-tempo-marca"><i class="fa ' + (icones[h.acao] || 'fa-circle') + '"></i></div>'
                    + '<div class="tf-tempo-caixa">'
                    + '<div class="tf-tempo-cabec">'
                    + '<span class="tf-tempo-autor">' + esc(h.usuario || 'sistema') + '</span>'
                    + '<span>' + esc(h.data_br) + '</span></div>'
                    + '<div class="tf-tempo-texto">' + esc(h.descricao || h.acao) + '</div>'
                    + mudanca + '</div></div>';
            }).join('')
            : '<p class="tf-mudo tf-mini">Sem histórico registrado. '
              + 'As alterações passam a ser gravadas a partir da migração v2.</p>';

        return '<div class="tf-aba-painel" data-painel="historico">'
             + '<div class="tf-tempo">' + itens + '</div></div>';
    }

    /* ============================================================== */
    /* Abas                                                           */
    /* ============================================================== */

    function trocarAba(nome) {
        $('#tfModalDetalhe .tf-aba').removeClass('tf-ativo');
        $('#tfModalDetalhe .tf-aba[data-aba="' + nome + '"]').addClass('tf-ativo');
        $('#tfDetalheCorpo .tf-aba-painel').removeClass('tf-ativo');
        $('#tfDetalheCorpo .tf-aba-painel[data-painel="' + nome + '"]').addClass('tf-ativo');
        $('#tfDetalheCorpo').scrollTop(0);
    }

    /* ============================================================== */
    /* Eventos                                                        */
    /* ============================================================== */

    function ligarEventos() {
        var $c = $('#tfDetalheCorpo').off('.tfdet');

        /* --- navegação entre tarefas relacionadas --- */
        $c.on('click.tfdet', '[data-abrir]', function () {
            abrir($(this).data('abrir'));
        });

        /* --- status --- */
        $c.on('click.tfdet', '#tfSalvarStatus', function () {
            var novo = $('#tfStatusSelect').val();
            if (novo === atual.status) { return; }

            var $b = $(this).addClass('tf-carregando').prop('disabled', true);

            api('acoes.php', { acao: 'status', taskToken: atual.token, status: novo }, 'POST')
                .done(function (r) {
                    $b.removeClass('tf-carregando').prop('disabled', false);
                    if (!r.success) { dlg.erro('Erro', r.error); return; }
                    if (window.toastr) { toastr.success('Status alterado para "' + novo + '".'); }
                    Tarefas.buscar(Tarefas.estado.pagina);
                    Tarefas.carregarPainel(true);
                    recarregar();
                })
                .fail(function (e) {
                    $b.removeClass('tf-carregando').prop('disabled', false);
                    dlg.erro('Erro', e.error);
                });
        });

        $c.on('click.tfdet', '#tfAssumir', function () {
            api('acoes.php', { acao: 'assumir', id: atual.id }, 'POST')
                .done(function (r) {
                    if (!r.success) { dlg.erro('Erro', r.error); return; }
                    dlg.ok('Pronto', r.mensagem);
                    recarregar();
                    Tarefas.buscar(Tarefas.estado.pagina);
                });
        });

        /* --- anexos --- */
        /*
         * O input de arquivo fica dentro da zona clicável; sem esta guarda o
         * clique dele volta para a zona e reabre o seletor indefinidamente.
         */
        $c.on('click.tfdet', '#tfZonaUpload', function (e) {
            if (e.target === document.getElementById('tfArquivoInput')) { return; }
            $('#tfArquivoInput').trigger('click');
        });

        $c.on('dragover.tfdet', '#tfZonaUpload', function (e) {
            e.preventDefault(); $(this).addClass('tf-sobre');
        });
        $c.on('dragleave.tfdet', '#tfZonaUpload', function () { $(this).removeClass('tf-sobre'); });
        $c.on('drop.tfdet', '#tfZonaUpload', function (e) {
            e.preventDefault();
            $(this).removeClass('tf-sobre');
            enviarArquivos(e.originalEvent.dataTransfer.files);
        });
        $c.on('change.tfdet', '#tfArquivoInput', function () { enviarArquivos(this.files); });

        $c.on('click.tfdet', '[data-excluir-anexo]', function () {
            var arq = $(this).data('excluir-anexo');
            dlg.confirmar('Excluir anexo?', 'O arquivo será removido do servidor.', 'Excluir')
                .then(function (ok) {
                    if (!ok) { return; }
                    api('acoes.php', { acao: 'excluir_anexo', taskId: atual.id, file: arq }, 'POST')
                        .done(function (r) {
                            if (!r.success) { dlg.erro('Erro', r.error); return; }
                            recarregar();
                        });
                });
        });

        $c.on('click.tfdet', '[data-analisar]', function () {
            analisarAnexo($(this).data('analisar'));
        });

        /* --- checklist --- */
        $c.on('click.tfdet', '#tfAddItem', function () {
            var texto = txt($('#tfNovoItem').val());
            if (texto === '') { return; }
            api('acoes.php', { acao: 'checklist_add', tarefa_id: atual.id, descricao: texto }, 'POST')
                .done(function (r) {
                    if (!r.success) { dlg.erro('Erro', r.error); return; }
                    $('#tfNovoItem').val('');
                    recarregarAba('checklist');
                });
        });

        $c.on('keydown.tfdet', '#tfNovoItem', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); $('#tfAddItem').click(); }
        });

        $c.on('change.tfdet', '.tf-check-item input[type=checkbox]', function () {
            var $item = $(this).closest('.tf-check-item');
            var marcado = this.checked;
            $item.toggleClass('tf-feito', marcado);
            api('acoes.php', {
                acao: 'checklist_marcar',
                id: $item.data('item'),
                concluido: marcado ? '1' : '0'
            }, 'POST');
        });

        $c.on('click.tfdet', '[data-excluir-item]', function () {
            var id = $(this).data('excluir-item');
            $(this).closest('.tf-check-item').fadeOut(150, function () { $(this).remove(); });
            api('acoes.php', { acao: 'checklist_excluir', id: id }, 'POST');
        });

        $c.on('click.tfdet', '#tfSugerirCheck', function () {
            var $b = $(this).addClass('tf-carregando').prop('disabled', true);
            api('ia.php', { recurso: 'sugerir_checklist', tarefa_id: atual.id, aplicar: '1' }, 'POST')
                .done(function (r) {
                    $b.removeClass('tf-carregando').prop('disabled', false);
                    if (!r.success) { dlg.erro('IA indisponível', r.error); return; }
                    recarregarAba('checklist');
                    if (window.toastr) { toastr.success(r.itens.length + ' item(ns) sugerido(s).'); }
                })
                .fail(function (e) {
                    $b.removeClass('tf-carregando').prop('disabled', false);
                    dlg.erro('IA indisponível', e.error);
                });
        });

        /* --- comentários --- */
        $c.on('click.tfdet', '#tfNovoComentario', function () { formComentario(); });
        $c.on('click.tfdet', '#tfRedigirIA', function () { formRedigir(); });

        $c.on('click.tfdet', '[data-editar-com]', function () {
            var id = $(this).data('editar-com');
            var com = null;
            atual.comentarios.forEach(function (x) { if (String(x.id) === String(id)) { com = x; } });
            if (com) { formComentario(com); }
        });

        $c.on('click.tfdet', '[data-excluir-com]', function () {
            var id = $(this).data('excluir-com');
            dlg.confirmar('Excluir comentário?', 'Esta ação não pode ser desfeita.', 'Excluir')
                .then(function (ok) {
                    if (!ok) { return; }
                    api('acoes.php', { acao: 'excluir_comentario', id: id }, 'POST')
                        .done(function (r) {
                            if (!r.success) { dlg.erro('Erro', r.error); return; }
                            recarregar();
                        });
                });
        });

        /* --- IA --- */
        $c.on('click.tfdet', '[data-ia]', function () {
            var recurso = $(this).data('ia');
            if (recurso === 'redigir') { formRedigir(); return; }
            executarIA(recurso, $(this));
        });

        $c.on('click.tfdet', '#tfIAEnviar', enviarPergunta);
        $c.on('keydown.tfdet', '#tfIAPergunta', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); enviarPergunta(); }
        });
        $c.on('click.tfdet', '#tfIALimpar', function () {
            api('ia.php', { recurso: 'chat_limpar', tarefa_id: atual.id }, 'POST')
                .done(function () { $('#tfIAChat').empty(); });
        });

        /* --- subtarefa --- */
        $c.on('click.tfdet', '#tfNovaSubtarefa', function () {
            TarefasDocumentos.abrirSubtarefa(atual);
        });
    }

    /** Recarrega a tarefa mantendo a aba aberta. */
    function recarregarAba(aba) {
        api('tarefa.php', { token: atual.token }).done(function (r) {
            if (!r.success) { return; }
            atual = r.tarefa;
            render();
            trocarAba(aba);
        });
    }

    /* ============================================================== */
    /* Upload                                                         */
    /* ============================================================== */

    function enviarArquivos(arquivos) {
        if (!arquivos || !arquivos.length) { return; }

        var fd = new FormData();
        fd.append('acao', 'editar');
        fd.append('taskId', atual.id);
        // Reenvia os campos obrigatórios sem alterá-los.
        fd.append('title', atual.titulo);
        fd.append('category', atual.categoria || '');
        fd.append('origin', atual.origem || '');
        fd.append('deadline', (atual.data_limite || '').replace(' ', 'T').slice(0, 16));
        fd.append('employee', atual.funcionario_responsavel || '');
        fd.append('description', atual.descricao || '');
        fd.append('priority', atual.nivel_de_prioridade || '');
        fd.append('reviewer', atual.revisor || '');

        for (var i = 0; i < arquivos.length; i++) {
            fd.append('attachments[]', arquivos[i]);
        }

        $('#tfFilaUpload').html('<p class="tf-mini tf-mudo">'
            + '<i class="fa fa-circle-o-notch tf-girando"></i> Enviando '
            + arquivos.length + ' arquivo(s)…</p>');

        api('acoes.php', fd, 'POST')
            .done(function (r) {
                if (!r.success) { dlg.erro('Erro', r.error); return; }
                if (r.avisos && r.avisos.length) {
                    dlg.aviso(r.avisos.join('\n'));
                }
                recarregarAba('anexos');
            })
            .fail(function (e) {
                $('#tfFilaUpload').empty();
                dlg.erro('Erro no envio', e.error);
            });
    }

    /* ============================================================== */
    /* IA                                                             */
    /* ============================================================== */

    function executarIA(recurso, $botao) {
        var $saida = $('#tfIASaida');
        var $rodape = $('#tfIARodape');

        if ($botao) { $botao.addClass('tf-carregando').prop('disabled', true); }
        $saida.html('<i class="fa fa-circle-o-notch tf-girando"></i> Consultando o modelo…');
        $rodape.empty();

        var dados = { recurso: recurso, tarefa_id: atual.id };
        if (recurso === 'resumir') { dados.forcar = '1'; }

        api('ia.php', dados, 'POST')
            .done(function (r) {
                if ($botao) { $botao.removeClass('tf-carregando').prop('disabled', false); }

                if (!r.success) {
                    $saida.html('<span style="color:var(--tf-perigo)">' + esc(r.error) + '</span>');
                    return;
                }

                if (recurso === 'proximos_passos') {
                    $saida.html(formatarPassos(r.dados));
                } else {
                    $saida.text(r.texto);
                }

                $rodape.html('<i class="fa fa-info-circle"></i> Gerado por ' + esc(r.modelo || '—')
                    + ' · confira antes de usar. '
                    + '<button class="tf-btn tf-btn-sm" id="tfCopiarIA">'
                    + '<i class="fa fa-clipboard"></i> Copiar</button>');

                $('#tfCopiarIA').on('click', function () {
                    copiar($saida.text());
                });
            })
            .fail(function (e) {
                if ($botao) { $botao.removeClass('tf-carregando').prop('disabled', false); }
                $saida.html('<span style="color:var(--tf-perigo)">' + esc(e.error) + '</span>');
            });
    }

    function formatarPassos(d) {
        var html = '';
        if (d.passos && d.passos.length) {
            html += '<strong>Próximos passos</strong><ol style="margin:8px 0 0;padding-left:20px">';
            d.passos.forEach(function (p) {
                html += '<li style="margin-bottom:7px">'
                    + '<strong>' + esc(p.titulo || '') + '</strong>'
                    + (p.urgente ? ' <span class="tf-selo tf-selo-vencida">urgente</span>' : '')
                    + (p.detalhe ? '<br><span class="tf-mudo">' + esc(p.detalhe) + '</span>' : '')
                    + '</li>';
            });
            html += '</ol>';
        }
        if (d.pendencias && d.pendencias.length) {
            html += '<div style="margin-top:12px"><strong>Pendências externas</strong><ul style="margin:6px 0 0;padding-left:20px">';
            d.pendencias.forEach(function (p) { html += '<li>' + esc(p) + '</li>'; });
            html += '</ul></div>';
        }
        if (d.observacao) {
            html += '<div style="margin-top:12px" class="tf-mudo">' + esc(d.observacao) + '</div>';
        }
        return html || '<span class="tf-mudo">O modelo não retornou passos.</span>';
    }

    function enviarPergunta() {
        var texto = txt($('#tfIAPergunta').val());
        if (texto === '') { return; }

        var $chat = $('#tfIAChat');
        $chat.append('<div class="tf-msg tf-msg-user">' + esc(texto) + '</div>');
        $('#tfIAPergunta').val('');

        var $resposta = $('<div class="tf-msg tf-msg-ia"><i class="fa fa-circle-o-notch tf-girando"></i></div>');
        $chat.append($resposta);
        $chat.scrollTop($chat[0].scrollHeight);

        api('ia.php', { recurso: 'chat', tarefa_id: atual.id, mensagem: texto }, 'POST')
            .done(function (r) {
                $resposta.text(r.success ? r.texto : (r.error || 'Sem resposta.'));
                $chat.scrollTop($chat[0].scrollHeight);
            })
            .fail(function (e) {
                $resposta.html('<span style="color:var(--tf-perigo)">' + esc(e.error) + '</span>');
            });
    }

    function analisarAnexo(arquivo) {
        if (!Tarefas.cfg.ia_ativa) {
            dlg.aviso('Configure a integração com o Gemini para analisar anexos.');
            return;
        }

        var $saida = $('#tfSaidaAnexoIA').html(
            '<div class="tf-ia-caixa"><div class="tf-ia-titulo"><i class="fa fa-magic"></i> '
            + 'Analisando ' + esc(arquivo.split('/').pop()) + '…</div>'
            + '<div class="tf-ia-saida"><i class="fa fa-circle-o-notch tf-girando"></i> '
            + 'Isso pode levar alguns segundos.</div></div>'
        );

        api('ia.php', { recurso: 'analisar_anexo', tarefa_id: atual.id, arquivo: arquivo }, 'POST')
            .done(function (r) {
                if (!r.success) {
                    $saida.find('.tf-ia-saida').html('<span style="color:var(--tf-perigo)">' + esc(r.error) + '</span>');
                    return;
                }
                $saida.find('.tf-ia-titulo').html('<i class="fa fa-magic"></i> Análise de ' + esc(r.arquivo));
                $saida.find('.tf-ia-saida').text(r.texto);
                $saida.find('.tf-ia-caixa').append(
                    '<div class="tf-ia-rodape"><i class="fa fa-info-circle"></i> '
                    + 'Leitura automática por ' + esc(r.modelo) + ' — confira o documento original.</div>'
                );
            })
            .fail(function (e) {
                $saida.find('.tf-ia-saida').html('<span style="color:var(--tf-perigo)">' + esc(e.error) + '</span>');
            });
    }

    function copiar(texto) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(texto).then(function () {
                if (window.toastr) { toastr.success('Texto copiado.'); }
            });
        } else {
            var $t = $('<textarea>').val(texto).appendTo('body').select();
            document.execCommand('copy');
            $t.remove();
            if (window.toastr) { toastr.success('Texto copiado.'); }
        }
    }

    /* ============================================================== */
    /* Formulários em diálogo                                         */
    /* ============================================================== */

    /** Novo comentário ou edição de um existente. */
    function formComentario(comentario) {
        var editando = !!comentario;

        if (!window.Swal) {
            // Sem SweetAlert2, cai para o modal legado já existente na página.
            $('#addCommentModal').modal('show');
            return;
        }

        Swal.fire(Tarefas.opcoesDialogo({
            title: editando ? 'Editar registro' : 'Registrar andamento',
            width: 640,
            html: '<textarea id="tfComTexto" class="tf-textarea" rows="6" style="width:100%"'
                + ' placeholder="Descreva o andamento…">' + esc(editando ? comentario.comentario : '') + '</textarea>'
                + '<div style="margin-top:12px;text-align:left">'
                + '<label class="tf-rotulo">Anexos (opcional)</label>'
                + '<input type="file" id="tfComArquivos" multiple class="tf-input"></div>',
            showCancelButton: true,
            confirmButtonText: editando ? 'Salvar alterações' : 'Registrar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusConfirm: false,
            preConfirm: function () {
                var texto = $('#tfComTexto').val();
                var arquivos = document.getElementById('tfComArquivos').files;
                if (!txt(texto) && !arquivos.length) {
                    Swal.showValidationMessage('Escreva um texto ou anexe um arquivo.');
                    return false;
                }
                return { texto: texto, arquivos: arquivos };
            },
            didOpen: function () {
                // Garante o cursor no campo assim que o diálogo abre.
                var ta = document.getElementById('tfComTexto');
                if (ta) { ta.focus(); }
            }
        })).then(function (res) {
            if (!res.isConfirmed) { return; }

            var fd = new FormData();
            if (editando) {
                fd.append('acao', 'editar_comentario');
                fd.append('commentId', comentario.id);
                fd.append('editCommentDescription', res.value.texto);
                fd.append('taskToken', atual.token);
                for (var i = 0; i < res.value.arquivos.length; i++) {
                    fd.append('editCommentAttachments[]', res.value.arquivos[i]);
                }
            } else {
                fd.append('acao', 'comentar');
                fd.append('taskToken', atual.token);
                fd.append('commentDescription', res.value.texto);
                for (var j = 0; j < res.value.arquivos.length; j++) {
                    fd.append('commentAttachments[]', res.value.arquivos[j]);
                }
            }

            dlg.carregando('Salvando…');
            api('acoes.php', fd, 'POST')
                .done(function (r) {
                    dlg.fechar();
                    if (!r.success) { dlg.erro('Erro', r.error); return; }
                    if (r.avisos && r.avisos.length) { dlg.aviso(r.avisos.join('\n')); }
                    recarregarAba('tempo');
                })
                .fail(function (e) { dlg.fechar(); dlg.erro('Erro', e.error); });
        });
    }

    /** Redação assistida: escolhe o tipo de texto e gera com a IA. */
    function formRedigir() {
        if (!Tarefas.cfg.ia_ativa) {
            dlg.aviso('Configure a integração com o Gemini para usar a redação assistida.');
            return;
        }
        if (!window.Swal) { return; }

        Swal.fire(Tarefas.opcoesDialogo({
            title: 'Redigir com IA',
            width: 640,
            html: '<div style="text-align:left">'
                + '<label class="tf-rotulo">Tipo de texto</label>'
                + '<select id="tfRedTipo" class="tf-select">'
                + '<option value="comentario">Registro de andamento</option>'
                + '<option value="despacho">Despacho interno</option>'
                + '<option value="exigencia">Nota de exigência ao apresentante</option>'
                + '<option value="email">E-mail ao interessado</option>'
                + '<option value="whatsapp">Mensagem de WhatsApp</option>'
                + '<option value="conclusao">Texto de conclusão</option>'
                + '</select>'
                + '<label class="tf-rotulo" style="margin-top:12px">Linguagem</label>'
                + '<select id="tfRedTom" class="tf-select">'
                + '<option value="formal">Formal de serventia</option>'
                + '<option value="simples">Simples, para o cidadão</option>'
                + '<option value="tecnico">Técnica registral</option>'
                + '</select>'
                + '<label class="tf-rotulo" style="margin-top:12px">Orientação adicional (opcional)</label>'
                + '<textarea id="tfRedExtra" class="tf-textarea" rows="2"'
                + ' placeholder="Ex.: mencionar que falta a certidão de ônus."></textarea>'
                + '</div>',
            showCancelButton: true,
            confirmButtonText: 'Gerar texto',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusConfirm: false,
            preConfirm: function () {
                return {
                    tipo: $('#tfRedTipo').val(),
                    tom: $('#tfRedTom').val(),
                    instrucao: $('#tfRedExtra').val()
                };
            }
        })).then(function (res) {
            if (!res.isConfirmed) { return; }

            dlg.carregando('Redigindo…');

            api('ia.php', $.extend({ recurso: 'redigir', tarefa_id: atual.id }, res.value), 'POST')
                .done(function (r) {
                    dlg.fechar();
                    if (!r.success) { dlg.erro('IA indisponível', r.error); return; }
                    mostrarTextoGerado(r.texto, res.value.tipo);
                })
                .fail(function (e) { dlg.fechar(); dlg.erro('IA indisponível', e.error); });
        });
    }

    /** Mostra o texto gerado, permitindo editar e registrar na linha do tempo. */
    function mostrarTextoGerado(texto, tipo) {
        Swal.fire(Tarefas.opcoesDialogo({
            title: 'Texto gerado',
            width: 700,
            html: '<textarea id="tfTextoGerado" class="tf-textarea" rows="12" style="width:100%">'
                + esc(texto) + '</textarea>'
                + '<p class="tf-mini tf-mudo" style="text-align:left;margin:10px 0 0">'
                + '<i class="fa fa-info-circle"></i> Revise o conteúdo antes de registrar. '
                + 'A IA pode cometer erros e não substitui a conferência do responsável.</p>',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Registrar no andamento',
            denyButtonText: 'Copiar',
            cancelButtonText: 'Descartar',
            reverseButtons: true,
            focusConfirm: false,
            didOpen: function () {
                var ta = document.getElementById('tfTextoGerado');
                if (ta) { ta.focus(); }
            }
        })).then(function (res) {
            var conteudo = $('#tfTextoGerado').val();

            if (res.isDenied) {
                copiar(conteudo);
                return;
            }
            if (!res.isConfirmed) { return; }

            var fd = new FormData();
            fd.append('acao', 'comentar');
            fd.append('taskToken', atual.token);
            fd.append('commentDescription', conteudo);

            api('acoes.php', fd, 'POST').done(function (r) {
                if (!r.success) { dlg.erro('Erro', r.error); return; }
                recarregarAba('tempo');
                if (window.toastr) { toastr.success('Registrado na linha do tempo.'); }
            });
        });
    }

    /* ============================================================== */
    /* Ligações fixas do modal                                        */
    /* ============================================================== */

    $(function () {
        init();

        $('#tfModalDetalhe').on('click', '.tf-aba', function () {
            trocarAba($(this).data('aba'));
        });

        $('#tfAcaoEditar').on('click', function () {
            if (atual) { window.location.href = 'edit_task.php?id=' + atual.id; }
        });

        $('#tfAcaoExcluir').on('click', function () {
            if (!atual) { return; }
            dlg.confirmar('Excluir a tarefa #' + atual.id + '?',
                'Comentários e anexos vinculados permanecerão no banco, mas a tarefa sairá das listas.',
                'Excluir').then(function (ok) {
                if (!ok) { return; }
                api('acoes.php', { acao: 'excluir', id: atual.id, confirmar_subtarefas: '1' }, 'POST')
                    .done(function (r) {
                        if (!r.success) { dlg.erro('Erro', r.error); return; }
                        $('#tfModalDetalhe').modal('hide');
                        dlg.ok('Excluída', r.mensagem);
                        Tarefas.buscar(1);
                        Tarefas.carregarPainel(true);
                    });
            });
        });

        // Ao fechar, limpa o parâmetro token da URL sem recarregar a página.
        $('#tfModalDetalhe').on('hidden.bs.modal', function () {
            if (window.history.replaceState && window.location.search.indexOf('token=') !== -1) {
                window.history.replaceState({}, '', window.location.pathname);
            }
        });
    });

    return {
        abrir: abrir,
        recarregar: recarregar,
        atualAsync: function () { return atual; },
        copiar: copiar
    };

})(jQuery);
