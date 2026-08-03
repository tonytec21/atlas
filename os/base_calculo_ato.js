/**
 * =====================================================================
 * base_calculo_ato.js — Base de cálculo por ato (lado do navegador)
 * ---------------------------------------------------------------------
 * ATLAS-OS-BASECALC-BUILD: 2026-08-01-v2
 *
 * Espelho de `base_calculo_lib.php`. As duas implementações precisam
 * concordar: aqui o escrevente é avisado na hora, no servidor a regra é
 * aplicada de verdade. Alterou uma, altere a outra.
 *
 * Atos cobrados por FAIXA DE VALOR DECLARADO trazem a faixa escrita na
 * própria descrição:
 *
 *     "De R$ 327.953,99 a R$ 409.942,47"     -> intervalo
 *     "Acima de R$ 1.024.856,25"             -> sem teto
 *     "Até R$ 1.024,85"                      -> sem piso
 *
 * Nesses o campo de base aparece e vira obrigatório. Em atos como
 * "Arquivamento, por folha do documento, os emolumentos serão:" não há
 * faixa — o campo nem aparece.
 * =====================================================================
 */
(function (global) {
    'use strict';

    /* ---------------------------------------------------------------- *
     * Valores
     * ---------------------------------------------------------------- */

    function bcValor(v) {
        if (typeof v === 'number') { return v; }

        var s = String(v == null ? '' : v).trim();
        if (s === '') { return 0; }

        s = s.replace(/[^\d,.\-]/g, '');

        if (s.indexOf(',') !== -1) {
            /* Formato brasileiro: vírgula é o separador decimal. */
            s = s.replace(/\./g, '').replace(',', '.');
        } else if (/\.\d{3}(\D|$)/.test(s)) {
            /* "1.024.856" — ponto como separador de milhar. */
            s = s.replace(/\./g, '');
        }

        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    function bcBRL(v) {
        return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    /** minúsculas, sem acento, espaços colapsados */
    function bcNormalizar(s) {
        s = String(s == null ? '' : s).toLowerCase();
        s = s.replace(/[áàâãä]/g, 'a').replace(/[éèêë]/g, 'e')
             .replace(/[íìîï]/g, 'i').replace(/[óòôõö]/g, 'o')
             .replace(/[úùûü]/g, 'u').replace(/ç/g, 'c').replace(/ñ/g, 'n');
        return s.replace(/\s+/g, ' ').trim();
    }

    /* Trecho de regex que casa um valor monetário. */
    var V = '(?:R\\$\\s*)?(\\d{1,3}(?:\\.\\d{3})+(?:,\\d{1,2})?|\\d+(?:[,.]\\d{1,2})?)';

    /* ---------------------------------------------------------------- *
     * Detecção
     * ---------------------------------------------------------------- */

    /**
     * @return {null|{tipo,minimo,maximo,rotulo,trecho}}
     */
    function bcExtrairFaixa(descricao) {
        var n = bcNormalizar(descricao);
        if (!n) { return null; }

        var m, i;

        /* 1. Intervalo: "de X a Y" / "entre X e Y" / "faixa de X a Y" */
        var intervalos = [
            new RegExp('\\b(?:de|entre)\\s+' + V + '\\s+(?:a|ate|e)\\s+' + V, 'i'),
            new RegExp('\\bfaixa\\s+(?:de\\s+)?' + V + '\\s+(?:a|ate|e)\\s+' + V, 'i'),
            new RegExp('\\bvalor(?:es)?\\s+(?:de\\s+)?' + V + '\\s+(?:a|ate|e)\\s+' + V, 'i')
        ];
        for (i = 0; i < intervalos.length; i++) {
            m = n.match(intervalos[i]);
            if (m) {
                var min = bcValor(m[1]), max = bcValor(m[2]);
                if (max >= min && max > 0) {
                    return {
                        tipo: 'intervalo', minimo: min, maximo: max, trecho: m[0].trim(),
                        rotulo: 'de ' + bcBRL(min) + ' a ' + bcBRL(max)
                    };
                }
            }
        }

        /* 2. Sem teto */
        var acima = [
            new RegExp('\\bacima\\s+de\\s+' + V, 'i'),
            new RegExp('\\bsuperior(?:es)?\\s+a\\s+' + V, 'i'),
            new RegExp('\\bmais\\s+de\\s+' + V, 'i'),
            new RegExp('\\ba\\s+partir\\s+de\\s+' + V, 'i'),
            new RegExp('\\bexceden(?:te|do)\\s+(?:a\\s+)?' + V, 'i')
        ];
        for (i = 0; i < acima.length; i++) {
            m = n.match(acima[i]);
            if (m) {
                var pi = bcValor(m[1]);
                if (pi > 0) {
                    return {
                        tipo: 'acima', minimo: pi, maximo: null, trecho: m[0].trim(),
                        rotulo: 'acima de ' + bcBRL(pi)
                    };
                }
            }
        }

        /* 3. Sem piso */
        var ate = [
            new RegExp('\\bate\\s+' + V, 'i'),
            new RegExp('\\binferior(?:es)?\\s+a\\s+' + V, 'i'),
            new RegExp('\\bno\\s+maximo\\s+' + V, 'i'),
            new RegExp('\\bmenor(?:es)?\\s+(?:que|de)\\s+' + V, 'i')
        ];
        for (i = 0; i < ate.length; i++) {
            m = n.match(ate[i]);
            if (m) {
                var te = bcValor(m[1]);
                if (te > 0) {
                    return {
                        tipo: 'ate', minimo: null, maximo: te, trecho: m[0].trim(),
                        rotulo: 'até ' + bcBRL(te)
                    };
                }
            }
        }

        return null;
    }

    function bcExigeBase(descricao) {
        return bcExtrairFaixa(descricao) !== null;
    }

    /* ---------------------------------------------------------------- *
     * Validação
     * ---------------------------------------------------------------- */

    /**
     * Tolerância de 1 centavo nas bordas: as faixas da tabela são
     * contíguas (uma termina em ...,47 e a seguinte começa em ...,48) e
     * um arredondamento não pode barrar lançamento legítimo.
     *
     * @return {{ok:boolean, codigo:?string, mensagem:string}}
     */
    function bcValidar(base, faixa) {
        base = Number(base) || 0;

        if (!faixa) { return { ok: true, codigo: null, mensagem: '' }; }

        if (base <= 0) {
            return {
                ok: false, codigo: 'base_obrigatoria',
                mensagem: 'Este ato é cobrado por faixa de valor declarado (' + faixa.rotulo +
                          '). Informe a base de cálculo do ato.'
            };
        }

        var tol = 0.011;

        if (faixa.minimo !== null && base < (faixa.minimo - tol)) {
            return {
                ok: false, codigo: 'base_fora_da_faixa',
                mensagem: 'A base de cálculo informada (' + bcBRL(base) + ') está ABAIXO da faixa ' +
                          'deste ato (' + faixa.rotulo + ').\n\n' +
                          'Selecione o ato da faixa correta para este valor.'
            };
        }

        if (faixa.maximo !== null && base > (faixa.maximo + tol)) {
            return {
                ok: false, codigo: 'base_fora_da_faixa',
                mensagem: 'A base de cálculo informada (' + bcBRL(base) + ') está ACIMA da faixa ' +
                          'deste ato (' + faixa.rotulo + ').\n\n' +
                          'Selecione o ato da faixa correta para este valor.'
            };
        }

        return { ok: true, codigo: null, mensagem: '' };
    }

    /* ---------------------------------------------------------------- *
     * Ligação com o formulário
     * ---------------------------------------------------------------- *
     * Espera no HTML:
     *   #grupoBaseAto  - contêiner do campo (escondido por padrão)
     *   #base_ato      - o input
     *   #faixaBaseAto  - onde a faixa esperada é exibida
     * ---------------------------------------------------------------- */

    var __faixaAtual = null;

    /** Faixa do ato que está no formulário agora. */
    function bcFaixaAtual() { return __faixaAtual; }

    /**
     * Mostra ou esconde o campo conforme a descrição do ato.
     * @param {string}  descricao
     * @param {number}  [valorAtual] preenche o campo (uso na edição)
     */
    function bcAplicarFaixa(descricao, valorAtual) {
        var faixa = bcExtrairFaixa(descricao);
        __faixaAtual = faixa;

        var $grupo = document.getElementById('grupoBaseAto');
        var $input = document.getElementById('base_ato');
        var $dica  = document.getElementById('faixaBaseAto');

        if (!$grupo || !$input) { return faixa; }

        if (!faixa) {
            /* Ato sem faixa: o campo some e não guarda resíduo. */
            $grupo.style.display = 'none';
            $input.value = '';
            $input.removeAttribute('required');
            if ($dica) { $dica.textContent = ''; }
            return null;
        }

        $grupo.style.display = '';
        $input.setAttribute('required', 'required');
        $input.value = (valorAtual !== undefined && valorAtual !== null && Number(valorAtual) > 0)
            ? Number(valorAtual).toFixed(2).replace('.', ',')
            : '';

        if ($dica) {
            $dica.textContent = 'Faixa deste ato: ' + faixa.rotulo;
        }

        bcRealcar();
        return faixa;
    }

    /** Realça o campo em vermelho quando a base está fora da faixa. */
    function bcRealcar() {
        var $input = document.getElementById('base_ato');
        var $dica  = document.getElementById('faixaBaseAto');
        if (!$input || !__faixaAtual) { return; }

        var base = bcValor($input.value);
        var r = bcValidar(base, __faixaAtual);

        if ($input.value.trim() === '') {
            $input.style.borderColor = '';
            if ($dica) { $dica.style.color = '#64748b'; }
            return;
        }

        $input.style.borderColor = r.ok ? '#16a34a' : '#dc2626';
        if ($dica) { $dica.style.color = r.ok ? '#16a34a' : '#dc2626'; }
    }

    /** Limpa o campo e o estado (após adicionar o item). */
    function bcLimpar() {
        __faixaAtual = null;
        var $grupo = document.getElementById('grupoBaseAto');
        var $input = document.getElementById('base_ato');
        var $dica  = document.getElementById('faixaBaseAto');
        if ($grupo) { $grupo.style.display = 'none'; }
        if ($input) { $input.value = ''; $input.style.borderColor = ''; $input.removeAttribute('required'); }
        if ($dica)  { $dica.textContent = ''; }
    }

    /**
     * Valida o que está no formulário antes de adicionar o item.
     * @return {{ok:boolean, base:?number, mensagem:string}}
     */
    function bcValidarFormulario() {
        if (!__faixaAtual) {
            return { ok: true, base: null, mensagem: '' };
        }

        var $input = document.getElementById('base_ato');
        var base = $input ? bcValor($input.value) : 0;
        var r = bcValidar(base, __faixaAtual);

        return { ok: r.ok, base: r.ok ? base : null, mensagem: r.mensagem };
    }

    global.BaseCalculoAto = {
        valor: bcValor,
        brl: bcBRL,
        normalizar: bcNormalizar,
        extrairFaixa: bcExtrairFaixa,
        exigeBase: bcExigeBase,
        validar: bcValidar,
        faixaAtual: bcFaixaAtual,
        aplicarFaixa: bcAplicarFaixa,
        realcar: bcRealcar,
        limpar: bcLimpar,
        validarFormulario: bcValidarFormulario
    };
})(window);
