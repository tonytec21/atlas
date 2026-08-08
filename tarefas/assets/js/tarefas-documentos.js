/*!
 * Atlas · Tarefas — documentos e integrações.
 *
 * Concentra tudo que sai da tarefa em papel ou vai para outro módulo:
 * protocolo geral, guia de recebimento (com histórico e reimpressão),
 * recibo de entrega, vínculo de ofício, criação de subtarefa e envio para o
 * módulo de Arquivamento.
 *
 * A lógica foi portada do módulo anterior preservando os mesmos endpoints e
 * os mesmos nomes de campo esperados pelo back-end — inclusive a escolha
 * entre arquivo com e sem timbre, lida de ../style/configuracao.json.
 */

/* global jQuery, Tarefas, TarefasDetalhe, Swal */
var TarefasDocumentos = (function ($) {
    'use strict';

    var timbradoCache = null;   // 'S' ou 'N', lido uma vez por sessão de página
    var funcionariosCache = null;
    var categoriasArqCarregadas = false;

    /**
     * Substitui $.trim, que foi marcado como obsoleto no jQuery 3.5 e removido
     * no 4. Como o Atlas pode atualizar o jQuery a qualquer momento, o módulo
     * não depende mais dele.
     */
    function txt(v) {
        return (v === null || v === undefined) ? '' : String(v).trim();
    }

    function esc(v) { return Tarefas.esc(v); }
    function dlg() { return Tarefas.dlg; }

    /* ============================================================== */
    /* Configuração de timbre                                         */
    /* ============================================================== */

    /**
     * Descobre se a serventia imprime em papel timbrado.
     * 'S' usa os arquivos com sublinhado (sem cabeçalho impresso);
     * 'N' usa os com hífen (que desenham o cabeçalho no PDF).
     * Essa convenção vem do módulo original e foi mantida intacta.
     */
    function comTimbre(callback) {
        if (timbradoCache !== null) { callback(timbradoCache); return; }

        $.ajax({ url: '../style/configuracao.json', dataType: 'json', cache: false })
            .done(function (d) {
                timbradoCache = (d && d.timbrado === 'S') ? 'S' : 'N';
                callback(timbradoCache);
            })
            .fail(function () {
                // Sem o arquivo de configuração, imprime a versão com cabeçalho.
                timbradoCache = 'N';
                callback(timbradoCache);
            });
    }

    function abrirImpressao(arquivoTimbrado, arquivoSimples, query) {
        comTimbre(function (t) {
            var arquivo = (t === 'S') ? arquivoTimbrado : arquivoSimples;
            window.open(arquivo + '?' + query, '_blank');
        });
    }

    /* ============================================================== */
    /* Protocolo geral                                                */
    /* ============================================================== */

    function imprimirProtocolo(tarefa) {
        abrirImpressao('protocolo_geral.php', 'protocolo-geral.php', 'id=' + encodeURIComponent(tarefa.id));
    }

    /* ============================================================== */
    /* Guia de recebimento                                            */
    /* ============================================================== */

    function carregarFuncionarios(callback) {
        if (funcionariosCache) { callback(funcionariosCache); return; }

        $.ajax({ url: 'load_employees.php', dataType: 'json' })
            .done(function (lista) {
                funcionariosCache = lista || [];
                callback(funcionariosCache);
            })
            .fail(function () { callback([]); });
    }

    /** Preenche o select do funcionário sugerindo o responsável pela tarefa. */
    function preencherFuncionarios(responsavel) {
        carregarFuncionarios(function (lista) {
            var $sel = $('#funcionario').empty();
            $sel.append('<option value="">Selecione…</option>');

            var achou = false;
            lista.forEach(function (f) {
                var nome = f.nome_completo;
                var sel = (nome === responsavel);
                if (sel) { achou = true; }
                $sel.append($('<option>').attr('value', nome).prop('selected', sel).text(nome));
            });

            // Se o responsável não estiver mais ativo, mantém o nome mesmo assim.
            if (!achou && responsavel) {
                $sel.append($('<option>').attr('value', responsavel).prop('selected', true)
                    .text(responsavel + ' (inativo)'));
            }
        });
    }

    /**
     * Ponto de entrada do botão "Guia Recebimento": se já houver guias
     * emitidas, mostra o histórico; senão, abre direto o formulário.
     */
    function guiaRecebimento(tarefa) {
        $.ajax({ url: 'listar_guias.php', data: { task_id: tarefa.id }, dataType: 'json' })
            .done(function (r) {
                if (r && r.success && r.total > 0) {
                    abrirHistoricoGuias(tarefa, r.guias);
                } else {
                    abrirFormularioGuia(tarefa);
                }
            })
            .fail(function () { abrirFormularioGuia(tarefa); });
    }

    function abrirFormularioGuia(tarefa) {
        $('#guiaHistoricoModal').modal('hide');

        $('#guiaRecebimentoForm')[0].reset();
        $('#cliente').val(tarefa.apresentante || '');
        $('#dataRecebimento').val(agoraLocal());
        $('#guiaRecebimentoForm #documentosRecebidos').val(nomesAnexos(tarefa));
        preencherFuncionarios(tarefa.funcionario_responsavel);

        $('#guiaRecebimentoModal').data('tarefa', tarefa).modal('show');
    }

    function abrirHistoricoGuias(tarefa, guias) {
        var $tbody = $('#guiaHistoricoTabela tbody').empty();

        guias.forEach(function (g, i) {
            $tbody.append(''
                + '<tr' + (i === 0 ? ' class="guia-hist-atual"' : '') + '>'
                + '<td>' + esc(g.id) + (i === 0 ? ' <span class="guia-hist-badge">atual</span>' : '') + '</td>'
                + '<td>' + esc(g.cliente || '—') + '</td>'
                + '<td>' + esc(g.criado_em_br || g.data_recebimento_br || '—') + '</td>'
                + '<td>' + esc(g.emitido_por || g.funcionario || '—') + '</td>'
                + '<td>' + esc(g.impressoes || 0) + '</td>'
                + '<td>' + esc(g.ultima_impressao_br || '—') + '</td>'
                + '<td><button class="tf-btn tf-btn-sm" data-guia="' + esc(g.id) + '">'
                + '<i class="fa fa-print"></i> Imprimir</button></td>'
                + '</tr>');
        });

        $tbody.off('click.tfguia').on('click.tfguia', '[data-guia]', function () {
            abrirImpressao('guia_recebimento.php', 'guia-recebimento.php',
                'guia_id=' + encodeURIComponent($(this).data('guia')));
        });

        $('#guiaHistoricoProtocolo').text(tarefa.id);
        $('#guiaHistoricoModal').data('tarefa', tarefa).modal('show');
    }

    function salvarGuia() {
        var tarefa = $('#guiaRecebimentoModal').data('tarefa');
        if (!tarefa) { return; }

        var dados = {
            task_id: tarefa.id,
            cliente: $('#cliente').val(),
            dataRecebimento: $('#dataRecebimento').val(),
            funcionario: $('#funcionario').val(),
            documentosRecebidos: $('#guiaRecebimentoForm #documentosRecebidos').val(),
            observacoes: $('#guiaRecebimentoForm #observacoes').val()
        };

        if (!dados.cliente || !dados.dataRecebimento || !dados.funcionario) {
            dlg().aviso('Preencha apresentante, data de recebimento e funcionário.');
            return;
        }

        dlg().carregando('Emitindo guia…');

        $.ajax({ url: 'save_guia_recebimento.php', type: 'POST', data: dados, dataType: 'json' })
            .done(function (r) {
                dlg().fechar();
                var ok = r && (r.success === true || r.status === 'success');
                if (!ok) {
                    dlg().erro('Erro', (r && (r.error || r.message)) || 'Não foi possível emitir a guia.');
                    return;
                }
                $('#guiaRecebimentoModal').modal('hide');
                var id = r.guia_id || r.id;
                abrirImpressao('guia_recebimento.php', 'guia-recebimento.php',
                    id ? 'guia_id=' + encodeURIComponent(id) : 'task_id=' + encodeURIComponent(tarefa.id));
                TarefasDetalhe.recarregar();
            })
            .fail(function () {
                dlg().fechar();
                dlg().erro('Erro', 'Não foi possível emitir a guia de recebimento.');
            });
    }

    /* ============================================================== */
    /* Recibo de entrega                                              */
    /* ============================================================== */

    function reciboEntrega(tarefa) {
        $('#reciboEntregaForm')[0].reset();
        $('#dataEntrega').val(agoraLocal());
        $('#entregador').val(Tarefas.cfg.nome || '');
        $('#reciboEntregaForm #documentos').val(nomesAnexos(tarefa));

        $('#reciboEntregaModal').data('tarefa', tarefa).modal('show');
    }

    function salvarRecibo() {
        var tarefa = $('#reciboEntregaModal').data('tarefa');
        if (!tarefa) { return; }

        var dados = {
            task_id: tarefa.id,
            receptor: $('#receptor').val(),
            dataEntrega: $('#dataEntrega').val(),
            entregador: $('#entregador').val(),
            documentos: $('#reciboEntregaForm #documentos').val(),
            observacoes: $('#reciboEntregaForm #observacoes').val()
        };

        if (!dados.receptor || !dados.dataEntrega || !dados.documentos) {
            dlg().aviso('Preencha receptor, data da entrega e os documentos entregues.');
            return;
        }

        dlg().carregando('Gerando recibo…');

        $.ajax({ url: 'save_recibo_entrega.php', type: 'POST', data: dados })
            .done(function () {
                dlg().fechar();
                $('#reciboEntregaModal').modal('hide');
                abrirImpressao('recibo_entrega.php', 'recibo-entrega.php',
                    'id=' + encodeURIComponent(tarefa.id));
                TarefasDetalhe.recarregar();
            })
            .fail(function () {
                dlg().fechar();
                dlg().erro('Erro', 'Não foi possível salvar o recibo de entrega.');
            });
    }

    /* ============================================================== */
    /* Ofício                                                         */
    /* ============================================================== */

    function vincularOficio(tarefa) {
        $('#numeroOficio').val(tarefa.numero_oficio || '');
        $('#vincularOficioModal').data('tarefa', tarefa).modal('show');
    }

    function salvarOficio() {
        var tarefa = $('#vincularOficioModal').data('tarefa');
        var numero = txt($('#numeroOficio').val());

        if (!tarefa || numero === '') {
            dlg().aviso('Informe o número do ofício.');
            return;
        }

        Tarefas.api('acoes.php', {
            acao: 'vincular_oficio',
            taskToken: tarefa.token,
            numeroOficio: numero
        }, 'POST')
            .done(function (r) {
                if (!r.success) { dlg().erro('Erro', r.error); return; }
                $('#vincularOficioModal').modal('hide');
                dlg().ok('Vinculado', r.mensagem);
                TarefasDetalhe.recarregar();
            })
            .fail(function (e) { dlg().erro('Erro', e.error); });
    }

    function verOficio(numero) {
        abrirImpressao('ver_oficio.php', 'ver-oficio.php', 'numero=' + encodeURIComponent(numero));
    }

    /* ============================================================== */
    /* Subtarefa                                                      */
    /* ============================================================== */

    /** Arquivos escolhidos para a subtarefa, antes do envio. */
    var arquivosSubtarefa = [];

    function abrirSubtarefa(tarefa) {
        $('#subTaskForm')[0].reset();
        arquivosSubtarefa = [];
        desenharArquivosSubtarefa();

        $('#subTaskPrincipalId').val(tarefa.id);
        $('#subTaskCreatedBy').val(Tarefas.cfg.usuario || '');
        $('#subTaskTitle').val('');
        $('#subTaskCategory').val(tarefa.categoria || '');
        $('#subTaskOrigin').val(tarefa.origem || '');
        $('#subTaskEmployee').val(tarefa.funcionario_responsavel || '');
        $('#subTaskPriority').val(tarefa.nivel_de_prioridade || 'Média');

        // Prazo padrão: o mesmo da tarefa principal, quando houver.
        if (tarefa.data_limite) {
            $('#subTaskDeadline').val(String(tarefa.data_limite).replace(' ', 'T').slice(0, 16));
        }

        $('#tfSubtarefaPrincipalRotulo').text(
            '#' + tarefa.id + ' · ' + Tarefas.corta(tarefa.titulo || '', 46)
        );

        $('#subTaskZonaUpload').removeClass('tf-desativada');
        $('#createSubTaskModal').data('tarefa', tarefa).modal('show');
    }

    /** Lista os arquivos escolhidos para a subtarefa. */
    function desenharArquivosSubtarefa() {
        var $l = $('#selectedFiles').empty();
        arquivosSubtarefa.forEach(function (f, i) {
            $l.append('<div class="tf-anexo">'
                + '<div class="tf-anexo-icone"><i class="fa fa-file-o"></i></div>'
                + '<div class="tf-anexo-nome">' + esc(f.name)
                + '<small>' + Math.max(1, Math.round(f.size / 1024)) + ' KB</small></div>'
                + '<button type="button" class="tf-btn tf-btn-sm" data-tirar-sub="' + i + '">'
                + '<i class="fa fa-times"></i></button></div>');
        });
    }

    function salvarSubtarefa(e) {
        e.preventDefault();

        var tarefa = $('#createSubTaskModal').data('tarefa');
        if (!tarefa) { return; }

        var titulo = txt($('#subTaskTitle').val());
        if (titulo === '') {
            dlg().aviso('Informe o título da subtarefa.');
            return;
        }

        var fd = new FormData();
        fd.append('acao', 'criar_subtarefa');
        fd.append('id_tarefa_principal', tarefa.id);
        fd.append('title', titulo);
        fd.append('category', $('#subTaskCategory').val());
        fd.append('origin', $('#subTaskOrigin').val());
        fd.append('deadline', $('#subTaskDeadline').val());
        fd.append('employee', $('#subTaskEmployee').val());
        fd.append('reviewer', $('#createSubTaskModal #reviewer').val() || '');
        fd.append('description', $('#subTaskDescription').val());
        fd.append('priority', $('#subTaskPriority').val());

        if ($('#compartilharAnexos').is(':checked')) {
            fd.append('compartilharAnexos', '1');
        } else {
            arquivosSubtarefa.forEach(function (f) { fd.append('attachments[]', f); });
        }

        dlg().carregando('Criando subtarefa…');

        Tarefas.api('acoes.php', fd, 'POST')
            .done(function (r) {
                dlg().fechar();
                if (!r.success) { dlg().erro('Erro', r.error); return; }
                $('#createSubTaskModal').modal('hide');
                dlg().ok('Criada', 'Subtarefa #' + r.id + ' criada com sucesso.');
                TarefasDetalhe.recarregar();
                Tarefas.buscar(Tarefas.estado.pagina);
            })
            .fail(function (e2) { dlg().fechar(); dlg().erro('Erro', e2.error); });
    }

    /* ============================================================== */
    /* Arquivamento do ato                                            */
    /* ============================================================== */

    /** Validação de CPF/CNPJ usada no cadastro de partes (regra original). */
    function validarCpfCnpj(valor) {
        var d = String(valor || '').replace(/[^\d]/g, '');
        if (!d) { return false; }

        if (d.length === 11) {
            if (/^(\d)\1{10}$/.test(d)) { return false; }
            var soma = 0, resto, i;
            for (i = 1; i <= 9; i++) { soma += parseInt(d.substring(i - 1, i), 10) * (11 - i); }
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) { resto = 0; }
            if (resto !== parseInt(d.substring(9, 10), 10)) { return false; }
            soma = 0;
            for (i = 1; i <= 10; i++) { soma += parseInt(d.substring(i - 1, i), 10) * (12 - i); }
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) { resto = 0; }
            return resto === parseInt(d.substring(10, 11), 10);
        }

        if (d.length === 14) {
            if (/^(\d)\1{13}$/.test(d)) { return false; }
            var tam = d.length - 2;
            var nums = d.substring(0, tam);
            var digs = d.substring(tam);
            var s = 0, pos = tam - 7, k;
            for (k = tam; k >= 1; k--) {
                s += nums.charAt(tam - k) * pos--;
                if (pos < 2) { pos = 9; }
            }
            var res = s % 11 < 2 ? 0 : 11 - s % 11;
            if (res !== parseInt(digs.charAt(0), 10)) { return false; }
            tam = tam + 1;
            nums = d.substring(0, tam);
            s = 0; pos = tam - 7;
            for (k = tam; k >= 1; k--) {
                s += nums.charAt(tam - k) * pos--;
                if (pos < 2) { pos = 9; }
            }
            res = s % 11 < 2 ? 0 : 11 - s % 11;
            return res === parseInt(digs.charAt(1), 10);
        }

        return false;
    }

    function carregarCategoriasArquivamento() {
        if (categoriasArqCarregadas) { return $.Deferred().resolve().promise(); }

        return $.ajax({ url: '../arquivamento/categorias/categorias.json', dataType: 'json' })
            .then(function (lista) {
                var $sel = $('#arq_categoria').empty().append('<option value="">Selecione</option>');
                (lista || []).forEach(function (c) {
                    $sel.append($('<option>').attr('value', c).text(c));
                });
                categoriasArqCarregadas = true;
            }, function () {
                $('#arq_categoria').html('<option value="">(Falha ao carregar)</option>');
            });
    }

    /** Arquivos novos escolhidos para o arquivamento, antes do envio. */
    var arquivosArquivamento = [];

    function desenharArquivosArquivamento() {
        var $l = $('#arq_novos_arquivos').empty();
        arquivosArquivamento.forEach(function (f, i) {
            $l.append('<div class="tf-anexo">'
                + '<div class="tf-anexo-icone"><i class="fa fa-file-o"></i></div>'
                + '<div class="tf-anexo-nome">' + esc(f.name)
                + '<small>' + Math.max(1, Math.round(f.size / 1024)) + ' KB</small></div>'
                + '<button type="button" class="tf-btn tf-btn-sm" data-tirar-arq="' + i + '">'
                + '<i class="fa fa-times"></i></button></div>');
        });
    }

    function abrirArquivamento(tarefa) {
        carregarCategoriasArquivamento().always(function () {
            var hoje = new Date();
            $('#arq_data_ato').val(hoje.getFullYear() + '-'
                + String(hoje.getMonth() + 1).padStart(2, '0') + '-'
                + String(hoje.getDate()).padStart(2, '0'));

            $('#arq_protocolo').val(tarefa.id);
            $('#arq_descricao').val(tarefa.titulo || '');

            // Anexos da tarefa, marcáveis, no padrão visual do módulo.
            var $lista = $('#arq_attachments_list').empty();
            if (!tarefa.anexos || !tarefa.anexos.length) {
                $lista.html('<p class="tf-mini tf-mudo" style="margin:0">'
                    + 'Nenhum anexo nesta tarefa.</p>');
            } else {
                $lista.addClass('tf-anexos');
                tarefa.anexos.forEach(function (a, i) {
                    $lista.append(''
                        + '<label class="tf-anexo" style="cursor:pointer;margin:0">'
                        + '<input type="checkbox" class="tf-check-caixa" data-rel="' + esc(a.rel) + '"'
                        + ' id="arqAnexo' + i + '" checked>'
                        + '<div class="tf-anexo-icone"><i class="fa fa-file-o"></i></div>'
                        + '<div class="tf-anexo-nome">' + esc(a.nome)
                        + '<small>' + esc(a.tamanho_br || '') + '</small></div>'
                        + '</label>');
                });
            }

            arquivosArquivamento = [];
            desenharArquivosArquivamento();

            $('#modalArquivarAto').data('tarefa', tarefa).modal('show');
        });
    }

    function salvarArquivamento(e) {
        e.preventDefault();

        var tarefa = $('#modalArquivarAto').data('tarefa');
        var $btn = $('#arq_submit_btn').prop('disabled', true);

        var fd = new FormData();
        ['atribuicao', 'categoria', 'data_ato', 'livro', 'folha', 'termo',
         'protocolo', 'matricula', 'descricao'].forEach(function (campo) {
            fd.append(campo, $('#arq_' + campo).val() || '');
        });

        var partes = [];
        $('#arq_partes_table tr').each(function () {
            var cpf = txt($(this).find('td').eq(0).text());
            var nome = txt($(this).find('td').eq(1).text());
            if (nome) { partes.push({ cpf: cpf, nome: nome }); }
        });
        fd.append('partes_envolvidas', JSON.stringify(partes));

        $('#arq_attachments_list input[type="checkbox"]:checked').each(function () {
            var rel = $(this).data('rel');
            if (rel) { fd.append('existing_files[]', rel); }
        });

        arquivosArquivamento.forEach(function (f) { fd.append('file-input[]', f); });

        $.ajax({
            url: '../arquivamento/save_ato.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        })
            .done(function (resp) {
                var json;
                try {
                    json = (typeof resp === 'string') ? JSON.parse(resp) : resp;
                } catch (err) {
                    dlg().erro('Erro', 'Resposta inválida do módulo de Arquivamento.');
                    return;
                }
                if (json.status === 'success' && json.redirect) {
                    $('#modalArquivarAto').modal('hide');
                    dlg().ok('Arquivado', 'Ato arquivado com sucesso.').then(function () {
                        window.open('../arquivamento/' + json.redirect, '_blank');
                    });
                    if (tarefa) { TarefasDetalhe.recarregar(); }
                } else {
                    dlg().erro('Erro', json.message || 'Falha ao salvar o arquivamento.');
                }
            })
            .fail(function () {
                dlg().erro('Erro', 'Não foi possível salvar o arquivamento.');
            })
            .always(function () { $btn.prop('disabled', false); });
    }

    /* ============================================================== */
    /* Utilitários                                                    */
    /* ============================================================== */

    /** Data/hora atual no formato aceito por <input type="datetime-local">. */
    function agoraLocal() {
        var d = new Date();
        return d.getFullYear() + '-'
            + String(d.getMonth() + 1).padStart(2, '0') + '-'
            + String(d.getDate()).padStart(2, '0') + 'T'
            + String(d.getHours()).padStart(2, '0') + ':'
            + String(d.getMinutes()).padStart(2, '0');
    }

    /** Sugestão de texto para os campos de documentos, a partir dos anexos. */
    function nomesAnexos(tarefa) {
        if (!tarefa.anexos || !tarefa.anexos.length) { return ''; }
        return tarefa.anexos.map(function (a) { return a.nome; }).join('\n');
    }

    /* ============================================================== */
    /* Ligações                                                       */
    /* ============================================================== */

    $(function () {
        function tarefaAtual() { return TarefasDetalhe.atualAsync(); }

        $('#tfAcaoProtocolo').on('click', function () {
            var t = tarefaAtual(); if (t) { imprimirProtocolo(t); }
        });
        $('#tfAcaoGuia').on('click', function () {
            var t = tarefaAtual(); if (t) { guiaRecebimento(t); }
        });
        $('#tfAcaoRecibo').on('click', function () {
            var t = tarefaAtual(); if (t) { reciboEntrega(t); }
        });
        $('#tfAcaoOficio').on('click', function () {
            var t = tarefaAtual(); if (t) { vincularOficio(t); }
        });
        $('#tfAcaoCriarOficio').on('click', function () {
            window.open('../oficios/cadastrar-oficio.php', '_blank');
        });
        $('#tfAcaoSubtarefa').on('click', function () {
            var t = tarefaAtual(); if (t) { abrirSubtarefa(t); }
        });
        $('#tfAcaoArquivar').on('click', function () {
            var t = tarefaAtual(); if (t) { abrirArquivamento(t); }
        });

        $('#guiaRecebimentoForm').on('submit', function (e) { e.preventDefault(); salvarGuia(); });
        $('#reciboEntregaForm').on('submit', function (e) { e.preventDefault(); salvarRecibo(); });
        $('#vincularOficioForm').on('submit', function (e) { e.preventDefault(); salvarOficio(); });
        $('#subTaskForm').on('submit', salvarSubtarefa);
        $('#arquivarAtoForm').on('submit', salvarArquivamento);

        // Botões do rodapé do histórico de guias.
        $(document).on('click', '#btnNovaGuiaHistorico', function () {
            var t = $('#guiaHistoricoModal').data('tarefa');
            $('#guiaHistoricoModal').modal('hide');
            if (t) { setTimeout(function () { abrirFormularioGuia(t); }, 320); }
        });

        $(document).on('click', '#btnAtualizarHistoricoGuias', function () {
            var t = $('#guiaHistoricoModal').data('tarefa');
            if (!t) { return; }
            $.ajax({ url: 'listar_guias.php', data: { task_id: t.id }, dataType: 'json' })
                .done(function (r) {
                    if (r && r.success) { abrirHistoricoGuias(t, r.guias); }
                });
        });

        // Reaproveitar os anexos da principal desativa o envio de arquivos novos.
        $(document).on('change', '#compartilharAnexos', function () {
            $('#subTaskZonaUpload').toggleClass('tf-desativada', this.checked);
            if (this.checked) {
                arquivosSubtarefa = [];
                desenharArquivosSubtarefa();
            }
        });

        /* Anexos da subtarefa: clique e arrastar-soltar. */
        $(document).on('click', '#subTaskZonaUpload', function (e) {
            if (e.target === document.getElementById('subTaskAttachments')) { return; }
            $('#subTaskAttachments').trigger('click');
        });

        $(document).on('change', '#subTaskAttachments', function () {
            Array.prototype.push.apply(arquivosSubtarefa, this.files);
            this.value = '';
            desenharArquivosSubtarefa();
        });

        $(document).on('dragover', '#subTaskZonaUpload', function (e) {
            e.preventDefault();
            $(this).addClass('tf-sobre');
        });
        $(document).on('dragleave', '#subTaskZonaUpload', function () {
            $(this).removeClass('tf-sobre');
        });
        $(document).on('drop', '#subTaskZonaUpload', function (e) {
            e.preventDefault();
            $(this).removeClass('tf-sobre');
            Array.prototype.push.apply(arquivosSubtarefa, e.originalEvent.dataTransfer.files);
            desenharArquivosSubtarefa();
        });

        $(document).on('click', '[data-tirar-sub]', function () {
            arquivosSubtarefa.splice(parseInt($(this).data('tirar-sub'), 10), 1);
            desenharArquivosSubtarefa();
        });

        // Cadastro de partes no arquivamento.
        $(document).on('click', '#arq_add_parte', function () {
            var cpf = txt($('#arq_cpf').val());
            var nome = txt($('#arq_nome_parte').val());

            if (!nome) { dlg().aviso('Informe o nome da parte.'); return; }
            if (cpf && !validarCpfCnpj(cpf)) {
                dlg().aviso('CPF/CNPJ inválido.');
                return;
            }

            $('#arq_partes_table').append(
                '<tr><td>' + esc(cpf) + '</td><td>' + esc(nome) + '</td>'
                + '<td><button type="button" class="tf-btn tf-btn-sm" data-remover-parte>'
                + '<i class="fa fa-times"></i></button></td></tr>'
            );
            $('#arq_cpf, #arq_nome_parte').val('');
        });

        $(document).on('click', '[data-remover-parte]', function () {
            $(this).closest('tr').remove();
        });

        /* Documentos novos do arquivamento: clique e arrastar-soltar. */
        $(document).on('click', '#arq_zona_upload', function (e) {
            if (e.target === document.getElementById('arq_file_input')) { return; }
            $('#arq_file_input').trigger('click');
        });

        $(document).on('change', '#arq_file_input', function () {
            Array.prototype.push.apply(arquivosArquivamento, this.files);
            this.value = '';
            desenharArquivosArquivamento();
        });

        $(document).on('dragover', '#arq_zona_upload', function (e) {
            e.preventDefault();
            $(this).addClass('tf-sobre');
        });
        $(document).on('dragleave', '#arq_zona_upload', function () {
            $(this).removeClass('tf-sobre');
        });
        $(document).on('drop', '#arq_zona_upload', function (e) {
            e.preventDefault();
            $(this).removeClass('tf-sobre');
            Array.prototype.push.apply(arquivosArquivamento, e.originalEvent.dataTransfer.files);
            desenharArquivosArquivamento();
        });

        $(document).on('click', '[data-tirar-arq]', function () {
            arquivosArquivamento.splice(parseInt($(this).data('tirar-arq'), 10), 1);
            desenharArquivosArquivamento();
        });

        // O clique no rótulo do anexo não deve marcar e desmarcar duas vezes.
        $(document).on('click', '#arq_attachments_list .tf-check-caixa', function (e) {
            e.stopPropagation();
        });
    });

    return {
        imprimirProtocolo: imprimirProtocolo,
        guiaRecebimento: guiaRecebimento,
        reciboEntrega: reciboEntrega,
        vincularOficio: vincularOficio,
        verOficio: verOficio,
        abrirSubtarefa: abrirSubtarefa,
        abrirArquivamento: abrirArquivamento,
        validarCpfCnpj: validarCpfCnpj,
        comTimbre: comTimbre
    };

})(jQuery);
