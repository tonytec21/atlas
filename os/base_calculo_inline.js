/**
 * =====================================================================
 * base_calculo_inline.js — Edição da base de cálculo direto na tabela
 * ---------------------------------------------------------------------
 * ATLAS-OS-BASECALC-BUILD: 2026-08-03-inline
 *
 * Permite corrigir a base de cálculo de um ato clicando na célula, do
 * mesmo jeito que já se edita quantidade e descrição.
 *
 * Isto não é só conveniência. Quando o orçamento é montado a partir de
 * um MODELO, os itens entram sem base — o modelo guarda os atos, não o
 * valor do negócio, que muda a cada escritura. Sem edição inline o
 * escrevente teria de apagar o item e lançar de novo só para informar a
 * base.
 *
 * COMO A LINHA GUARDA O ESTADO
 * ----------------------------
 *   <tr data-base="350000"                      valor já validado
 *       data-exige-base="1"                     ato cobrado por faixa
 *       data-faixa="de R$ 327.953,99 a R$ 409.942,47">
 *     ...
 *     <td class="base-ato-td base-edit" contenteditable="true">R$ 350.000,00</td>
 *
 * A faixa é guardada como TEXTO e relida por BaseCalculoAto.extrairFaixa
 * na hora de validar. Guardar o rótulo em vez de mínimo/máximo separados
 * evita que os dois fiquem fora de sincronia — há uma fonte só.
 *
 * DEPENDE DE: base_calculo_ato.js
 * =====================================================================
 */
(function ($) {
    'use strict';

    if (!$ || typeof window.BaseCalculoAto === 'undefined') {
        return;
    }

    var BC = window.BaseCalculoAto;

    /* ---------------------------------------------------------------- *
     * Estado da linha
     * ---------------------------------------------------------------- */

    function faixaDaLinha($tr) {
        var rot = $tr.attr('data-faixa');
        return rot ? BC.extrairFaixa(rot) : null;
    }

    function exigeBase($tr) {
        return String($tr.attr('data-exige-base') || '') === '1';
    }

    /** Pinta a célula conforme o estado. */
    function pintar($td, ok, vazio) {
        $td.removeClass('base-pendente base-ok base-erro');
        if (vazio) {
            $td.addClass('base-pendente');
        } else {
            $td.addClass(ok ? 'base-ok' : 'base-erro');
        }
    }

    /**
     * Lê o que foi digitado, valida contra a faixa e grava na linha.
     * @return {boolean} a linha ficou válida?
     */
    function aplicar($td) {
        var $tr = $td.closest('tr');
        if (!$tr.length) { return true; }

        var faixa = faixaDaLinha($tr);
        var texto = ($td.text() || '').trim();

        /* Placeholder não é valor. */
        if (/^(informar|—|-|n[aã]o informada)$/i.test(texto)) {
            texto = '';
        }

        var base = BC.valor(texto);

        /* Ato sem faixa não guarda base. */
        if (!exigeBase($tr) || !faixa) {
            $tr.attr('data-base', '');
            $td.text('—').removeClass('base-pendente base-ok base-erro');
            return true;
        }

        if (texto === '' || base <= 0) {
            $tr.attr('data-base', '');
            $td.text('informar');
            $td.attr('title', 'Ato com valor declarado (' + faixa.rotulo + '). Informe a base de cálculo.');
            pintar($td, false, true);
            return false;
        }

        var r = BC.validar(base, faixa);

        if (!r.ok) {
            /* Mantém o que foi digitado: apagar faria o escrevente perder
               o número e não entender o que estava errado. */
            $tr.attr('data-base', '');
            $td.text(BC.brl(base));
            $td.attr('title', r.mensagem.replace(/\n+/g, ' '));
            pintar($td, false, false);
            return false;
        }

        $tr.attr('data-base', base);
        $td.text(BC.brl(base));
        $td.attr('title', 'Faixa deste ato: ' + faixa.rotulo);
        pintar($td, true, false);
        return true;
    }

    /* ---------------------------------------------------------------- *
     * Verificação geral (usada para travar o botão Salvar)
     * ---------------------------------------------------------------- */

    /**
     * @return {{ok:boolean, pendentes:number, invalidas:number, atos:string[]}}
     */
    function verificarTabela(seletor) {
        var pendentes = 0, invalidas = 0, atos = [];

        $(seletor || '#itensTable').find('tr').each(function () {
            var $tr = $(this);
            if ($tr.attr('id') === 'ISS_ROW' || !exigeBase($tr)) { return; }

            var faixa = faixaDaLinha($tr);
            if (!faixa) { return; }

            var base = parseFloat($tr.attr('data-base'));
            var ato  = ($tr.find('td').eq(1).text() || '').trim();

            if (!base || base <= 0) {
                pendentes++;
                atos.push(ato);
                return;
            }
            if (!BC.validar(base, faixa).ok) {
                invalidas++;
                atos.push(ato);
            }
        });

        return {
            ok: (pendentes + invalidas) === 0,
            pendentes: pendentes,
            invalidas: invalidas,
            atos: atos
        };
    }

    /** Mensagem pronta para o alerta de bloqueio. */
    function mensagemPendencia(res) {
        var partes = [];
        if (res.pendentes) {
            partes.push(res.pendentes + ' ato(s) com valor declarado sem base de cálculo');
        }
        if (res.invalidas) {
            partes.push(res.invalidas + ' ato(s) com base fora da faixa');
        }
        return 'Não é possível salvar: ' + partes.join(' e ') + '.\n\n' +
               'Ato(s): ' + res.atos.join(', ') + '\n\n' +
               'Clique na coluna "Base de Cálculo" da linha e informe o valor do negócio jurídico.';
    }

    /* ---------------------------------------------------------------- *
     * Ligação com a tabela
     * ---------------------------------------------------------------- */

    $(document)
        /* Enter confirma, como nas outras células editáveis. */
        .on('keydown', '#itensTable td.base-edit', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                $(this).blur();
            }
        })
        /* Ao entrar, mostra o número cru — editar "R$ 350.000,00" é ruim. */
        .on('focusin', '#itensTable td.base-edit', function () {
            var $tr = $(this).closest('tr');
            var base = parseFloat($tr.attr('data-base'));
            $(this).text(base > 0 ? base.toFixed(2).replace('.', ',') : '');
        })
        .on('focusout', '#itensTable td.base-edit', function () {
            aplicar($(this));
            if (typeof window.atualizarCardsMobile === 'function') { window.atualizarCardsMobile(); }
            if (typeof window.updateSalvarButtonState === 'function') { window.updateSalvarButtonState(); }
        });

    /* Estado inicial das linhas já presentes (edição de O.S. e modelos). */
    $(function () {
        $('#itensTable tr').each(function () {
            var $tr = $(this);
            if (!exigeBase($tr)) { return; }

            var $td  = $tr.find('td.base-edit');
            if (!$td.length) { return; }

            var base = parseFloat($tr.attr('data-base'));
            var faixa = faixaDaLinha($tr);

            if (base > 0 && faixa && BC.validar(base, faixa).ok) {
                pintar($td, true, false);
                $td.attr('title', 'Faixa deste ato: ' + faixa.rotulo);
            } else {
                pintar($td, false, true);
                if (faixa) {
                    $td.attr('title', 'Ato com valor declarado (' + faixa.rotulo + '). Informe a base de cálculo.');
                }
            }
        });

        if (typeof window.updateSalvarButtonState === 'function') {
            window.updateSalvarButtonState();
        }
    });

    window.BaseCalculoInline = {
        aplicar: aplicar,
        verificar: verificarTabela,
        mensagem: mensagemPendencia,
        faixaDaLinha: faixaDaLinha,
        exigeBase: exigeBase
    };
})(window.jQuery);
