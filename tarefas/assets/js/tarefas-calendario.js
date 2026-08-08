/*!
 * Atlas · Tarefas — calendário mensal.
 *
 * Substitui o FullCalendar que o módulo antigo carregava do CDN
 * jsdelivr. Além de eliminar a dependência externa (a serventia pode operar
 * sem internet nas máquinas de atendimento), a implementação própria usa o
 * mesmo endpoint da listagem, respeita os filtros da tela e segue o tema
 * claro/escuro do Atlas.
 */

/* global jQuery, Tarefas */
var TarefasCalendario = (function ($) {
    'use strict';

    var referencia = new Date();
    var iniciado = false;
    var eventos = [];

    var MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    var DIAS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

    function iso(d) {
        return d.getFullYear() + '-'
             + String(d.getMonth() + 1).padStart(2, '0') + '-'
             + String(d.getDate()).padStart(2, '0');
    }

    /** Primeiro domingo da grade (pode cair no mês anterior). */
    function inicioGrade(base) {
        var d = new Date(base.getFullYear(), base.getMonth(), 1);
        d.setDate(d.getDate() - d.getDay());
        return d;
    }

    /** Último sábado da grade — sempre 6 semanas, para a altura não pular. */
    function fimGrade(base) {
        var d = inicioGrade(base);
        d.setDate(d.getDate() + 41);
        return d;
    }

    function montarEsqueleto() {
        var $c = $('#tfCalendario');
        if ($c.find('.tf-cal').length) { return; }

        $c.html(''
            + '<div class="tf-cal">'
            + '  <div class="tf-cal-topo">'
            + '    <button class="tf-btn tf-btn-icone" id="tfCalAnterior" title="Mês anterior">'
            + '      <i class="fa fa-chevron-left"></i></button>'
            + '    <button class="tf-btn tf-btn-icone" id="tfCalProximo" title="Próximo mês">'
            + '      <i class="fa fa-chevron-right"></i></button>'
            + '    <button class="tf-btn tf-btn-sm" id="tfCalHoje">Hoje</button>'
            + '    <span class="tf-cal-mes" id="tfCalMes"></span>'
            + '    <span class="tf-mudo tf-mini" id="tfCalTotal" style="margin-left:auto"></span>'
            + '  </div>'
            + '  <div class="tf-cal-grade" id="tfCalGrade"></div>'
            + '</div>');

        $('#tfCalAnterior').on('click', function () {
            referencia = new Date(referencia.getFullYear(), referencia.getMonth() - 1, 1);
            carregar();
        });
        $('#tfCalProximo').on('click', function () {
            referencia = new Date(referencia.getFullYear(), referencia.getMonth() + 1, 1);
            carregar();
        });
        $('#tfCalHoje').on('click', function () {
            referencia = new Date();
            carregar();
        });
    }

    function carregar() {
        montarEsqueleto();

        $('#tfCalMes').text(MESES[referencia.getMonth()] + ' de ' + referencia.getFullYear());
        $('#tfCalGrade').html('<div style="grid-column:1/-1;padding:40px;text-align:center" class="tf-mudo">'
            + '<i class="fa fa-circle-o-notch tf-girando"></i> Carregando…</div>');

        var params = $.extend({}, Tarefas.lerFiltros(), {
            visao: 'calendario',
            start: iso(inicioGrade(referencia)),
            end: iso(fimGrade(referencia))
        });

        Tarefas.api('tarefas.php', params)
            .done(function (r) {
                eventos = (r && r.success) ? (r.eventos || []) : [];
                desenhar();
            })
            .fail(function () {
                $('#tfCalGrade').html('<div style="grid-column:1/-1;padding:40px;text-align:center" class="tf-mudo">'
                    + 'Não foi possível carregar o calendário.</div>');
            });
    }

    function desenhar() {
        var porDia = {};
        eventos.forEach(function (e) {
            (porDia[e.data] = porDia[e.data] || []).push(e);
        });

        var html = DIAS.map(function (d) {
            return '<div class="tf-cal-dia-nome">' + d + '</div>';
        }).join('');

        var hoje = iso(new Date());
        var d = inicioGrade(referencia);

        for (var i = 0; i < 42; i++) {
            var chave = iso(d);
            var fora = d.getMonth() !== referencia.getMonth();
            var lista = porDia[chave] || [];

            // Mais cedo primeiro dentro do dia.
            lista.sort(function (a, b) { return a.hora.localeCompare(b.hora); });

            var evtHtml = lista.slice(0, 3).map(function (e) {
                return '<div class="tf-cal-evt' + (e.situacao === 'vencida' ? ' tf-evt-vencida' : '') + '"'
                     + ' style="--tf-cor:' + Tarefas.esc(e.cor) + '"'
                     + ' data-token="' + Tarefas.esc(e.token) + '"'
                     + ' title="' + Tarefas.esc('#' + e.id + ' · ' + e.titulo + ' · ' + e.status
                        + ' · ' + (e.funcionario || 'sem responsável')) + '">'
                     + Tarefas.esc(e.hora) + ' ' + Tarefas.esc(Tarefas.corta(e.titulo, 24))
                     + '</div>';
            }).join('');

            if (lista.length > 3) {
                evtHtml += '<div class="tf-cal-mais" data-dia="' + chave + '">+ '
                         + (lista.length - 3) + ' outras</div>';
            }

            html += '<div class="tf-cal-cel' + (fora ? ' tf-fora' : '')
                  + (chave === hoje ? ' tf-hoje' : '') + '">'
                  + '<span class="tf-cal-num">' + d.getDate() + '</span>'
                  + evtHtml
                  + '</div>';

            d.setDate(d.getDate() + 1);
        }

        $('#tfCalGrade').html(html);
        $('#tfCalTotal').text(eventos.length + ' tarefa(s) com prazo no período');

        $('#tfCalGrade').off('click.tfcal')
            .on('click.tfcal', '.tf-cal-evt', function () {
                Tarefas.abrirPorToken($(this).data('token'));
            })
            .on('click.tfcal', '.tf-cal-mais', function () {
                mostrarDia($(this).data('dia'), porDia[$(this).data('dia')] || []);
            });
    }

    /** Lista completa de um dia, quando há mais eventos do que cabem na célula. */
    function mostrarDia(dia, lista) {
        var partes = dia.split('-');
        var titulo = partes[2] + ' de ' + MESES[parseInt(partes[1], 10) - 1] + ' de ' + partes[0];

        var html = '<div class="tf-anexos" style="text-align:left">' + lista.map(function (e) {
            return '<div class="tf-anexo" style="cursor:pointer" data-token="' + Tarefas.esc(e.token) + '">'
                 + '<div class="tf-anexo-icone" style="background:' + Tarefas.esc(e.cor) + ';color:#fff">'
                 + '<i class="fa fa-tasks"></i></div>'
                 + '<div class="tf-anexo-nome"><strong>#' + Tarefas.esc(e.id) + '</strong> '
                 + Tarefas.esc(e.titulo)
                 + '<small>' + Tarefas.esc(e.hora) + ' · ' + Tarefas.esc(e.status)
                 + ' · ' + Tarefas.esc(e.funcionario || 'sem responsável') + '</small></div>'
                 + '</div>';
        }).join('') + '</div>';

        if (window.Swal) {
            Swal.fire(Tarefas.opcoesDialogo({
                title: titulo,
                html: html,
                width: 620,
                showConfirmButton: false,
                showCloseButton: true,
                didOpen: function () {
                    $('.swal2-html-container .tf-anexo').on('click', function () {
                        var t = $(this).data('token');
                        Swal.close();
                        Tarefas.abrirPorToken(t);
                    });
                }
            }));
        }
    }

    return {
        iniciar: function () {
            if (!iniciado) {
                iniciado = true;
                referencia = new Date();
            }
            carregar();
        },
        recarregar: carregar,
        irPara: function (data) {
            referencia = new Date(data);
            carregar();
        }
    };

})(jQuery);
