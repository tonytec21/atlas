<?php
/**
 * pe_ui.php — Interface de pagamento online dentro do modal da O.S.
 *
 * Incluído por visualizar_os.php em UMA linha, dentro de um if (pe_habilitado()).
 * Com o recurso desligado nada disto é carregado e a tela fica idêntica ao
 * que sempre foi.
 *
 * ESTRATÉGIA DE INTEGRAÇÃO: não recriamos o formulário de lançamento manual.
 * O JavaScript MOVE os nós que já existem (#forma_pagamento, #valor_pagamento,
 * #btnAdicionarPagamento...) para dentro da aba "Lançamento manual". Como os
 * elementos são os mesmos, adicionarPagamento(), atualizarTabelaPagamentos()
 * e todo o resto continuam funcionando sem uma linha de alteração.
 */

$peOsId = (int) ($os_id ?? 0);

try {
    $peSaldo = $peOsId > 0 ? pe_saldo_devido($peOsId) : 0.0;
} catch (Throwable $peErro) {
    pe_log('error', 'ui', 'Falha ao calcular saldo: ' . $peErro->getMessage(), $peOsId ?: null);
    $peSaldo = isset($saldo) ? round(-1 * (float) $saldo, 2) : 0.0;
}

$pePos = pe_pos_habilitado();
$peBoleto = pe_boleto_habilitado();

/* Sem saldo a receber, ou sem nenhum canal ligado, não há o que oferecer. */
if ($peSaldo <= 0 || (!$pePos && !$peBoleto)) {
    return;
}

$peCfg = pe_config();
$peTimeoutVenda = (int) ($peCfg['timeout_venda_seg'] ?? 300);
$peCliente = (string) ($ordem_servico['cliente'] ?? '');
$peDocumento = (string) ($ordem_servico['cpf_cliente'] ?? '');
$peVencPadrao = (new DateTimeImmutable('+3 days'))->format('Y-m-d');
$peSandbox = ($peCfg['ambiente'] ?? 'sandbox') === 'sandbox';
$peIntervalo = max(1, min(30, (int) ($peCfg['timeout_poll_seg'] ?? 3) ?: 3));
?>

<style>
/* =====================================================================
   Pagamento online — escopo isolado sob #pagamentoModal.pe-on
   Nenhum seletor global: com o recurso desligado nada disto é servido, e
   mesmo ligado não há colisão com o CSS existente do Atlas.
   ===================================================================== */
/* As cores derivam dos tokens do Atlas, com valores de reserva. Assim o
   modal acompanha o tema do sistema — inclusive o modo escuro — sem uma
   segunda folha de estilo. */
#pagamentoModal.pe-on {
    --pe-ink:      var(--text-primary,   #0B1B2B);
    --pe-slate:    var(--text-secondary, #64748B);
    --pe-faint:    var(--text-tertiary,  #94A3B8);
    --pe-line:     var(--border-primary, #E4E9F0);
    --pe-surface:  var(--bg-elevated,    #FFFFFF);
    --pe-sub:      var(--bg-secondary,   #F6F8FB);
    --pe-accent:   #0B6B53;   /* verde de impressão de segurança */
    --pe-accent-2: #E7F2ED;
    --pe-warn:     #B45309;
    --pe-danger:   #B91C1C;
}

body.dark-mode #pagamentoModal.pe-on {
    --pe-accent:   #34D399;
    --pe-accent-2: rgba(52, 211, 153, .14);
    --pe-warn:     #FBBF24;
    --pe-danger:   #FCA5A5;
    --pe-sub:      rgba(255, 255, 255, .04);
}

/* Campos e superfícies que no claro são brancos precisam de um tom
   próprio no escuro, senão desaparecem dentro do cartão. */
body.dark-mode #pagamentoModal.pe-on .pe-grid .form-control,
body.dark-mode #pagamentoModal.pe-on .pe-grid .input-group-text,
body.dark-mode #pagamentoModal.pe-on .pe-linha {
    background: rgba(0, 0, 0, .22);
    color: var(--pe-ink);
}
body.dark-mode #pagamentoModal.pe-on .pe-btn { background: rgba(255, 255, 255, .04); }
body.dark-mode #pagamentoModal.pe-on .pe-btn-forte,
body.dark-mode #pagamentoModal.pe-on .pe-acao { color: #06281F; }
body.dark-mode #pagamentoModal.pe-on .pe-canhoto-topo { color: #06281F; }
body.dark-mode #pagamentoModal.pe-on .pe-cancelar { background: transparent; }
body.dark-mode #pagamentoModal.pe-on .pe-switch .pe-trilho::after { background: #E2E8F0; }

/* Dinheiro sempre alinha. */
#pagamentoModal.pe-on .pe-num {
    font-variant-numeric: tabular-nums;
    font-feature-settings: "tnum" 1;
}

/* ---- Fita de valores ---- */
#pagamentoModal.pe-on .pe-tape {
    display: flex;
    flex-wrap: wrap;
    border: 1px solid var(--pe-line);
    border-radius: 10px;
    overflow: hidden;
    background: var(--pe-surface);
    margin: 0 0 22px;
}
#pagamentoModal.pe-on .pe-tape > div {
    flex: 1 1 0;
    min-width: 118px;
    padding: 12px 16px;
    border-right: 1px solid var(--pe-line);
}
#pagamentoModal.pe-on .pe-tape > div:last-child { border-right: 0; }
#pagamentoModal.pe-on .pe-tape dt {
    font-size: .68rem;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: var(--pe-slate);
    font-weight: 600;
    margin: 0 0 3px;
}
#pagamentoModal.pe-on .pe-tape dd {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--pe-ink);
}
#pagamentoModal.pe-on .pe-tape .pe-devido dd { color: var(--pe-accent); }

/* ---- Escolha do método ---- */
#pagamentoModal.pe-on .pe-metodos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(152px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
#pagamentoModal.pe-on .pe-metodo {
    border: 1px solid var(--pe-line);
    background: var(--pe-surface);
    color: var(--pe-ink);
    border-radius: 10px;
    padding: 13px 12px;
    text-align: left;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 11px;
    transition: border-color .14s ease, background-color .14s ease;
}
#pagamentoModal.pe-on .pe-metodo:hover { border-color: var(--pe-accent); background: var(--pe-sub); }
#pagamentoModal.pe-on .pe-metodo:focus-visible { outline: 2px solid var(--pe-accent); outline-offset: 2px; }
#pagamentoModal.pe-on .pe-metodo[aria-selected="true"] {
    border-color: var(--pe-accent);
    background: var(--pe-accent-2);
}
#pagamentoModal.pe-on .pe-metodo svg { flex: none; color: var(--pe-slate); }
#pagamentoModal.pe-on .pe-metodo[aria-selected="true"] svg { color: var(--pe-accent); }
#pagamentoModal.pe-on .pe-metodo b { display: block; font-size: .88rem; color: var(--pe-ink); line-height: 1.25; }
#pagamentoModal.pe-on .pe-metodo em {
    display: block; font-size: .73rem; color: var(--pe-slate); margin-top: 1px; font-style: normal;
}

/* ---- Painéis ---- */
#pagamentoModal.pe-on .pe-pane { display: none; }
#pagamentoModal.pe-on .pe-pane.pe-ativo { display: block; animation: peFade .14s ease-out; }
@keyframes peFade { from { opacity: 0; transform: translateY(3px); } to { opacity: 1; transform: none; } }

#pagamentoModal.pe-on .pe-rotulo {
    display: block;
    font-size: .7rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--pe-slate);
    font-weight: 700;
    margin: 0 0 6px;
}
/* Grid de 12 colunas: a largura de cada campo indica o que se espera
   dentro dele. UF com duas letras não pode ocupar o mesmo espaço que
   Cidade — era isso que deixava o formulário desalinhado. */
#pagamentoModal.pe-on .pe-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 13px 12px;
    align-items: start;
}
#pagamentoModal.pe-on .pe-c2  { grid-column: span 2; }
#pagamentoModal.pe-on .pe-c3  { grid-column: span 3; }
#pagamentoModal.pe-on .pe-c4  { grid-column: span 4; }
#pagamentoModal.pe-on .pe-c5  { grid-column: span 5; }
#pagamentoModal.pe-on .pe-c6  { grid-column: span 6; }
#pagamentoModal.pe-on .pe-c7  { grid-column: span 7; }
#pagamentoModal.pe-on .pe-c12,
#pagamentoModal.pe-on .pe-col-2 { grid-column: 1 / -1; }

@media (max-width: 640px) {
    #pagamentoModal.pe-on .pe-grid > * { grid-column: 1 / -1; }
}

/* Cabeçalho de bloco: uma régua fina que separa sem pesar. */
#pagamentoModal.pe-on .pe-secao {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 22px 0 12px;
}
#pagamentoModal.pe-on .pe-secao:first-child { margin-top: 0; }
#pagamentoModal.pe-on .pe-secao span {
    font-size: .7rem;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: var(--pe-slate);
    font-weight: 700;
    white-space: nowrap;
}
#pagamentoModal.pe-on .pe-secao::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--pe-line);
}

/* Campos e rótulos com respiro e altura uniforme. */
#pagamentoModal.pe-on .pe-grid label.pe-rotulo {
    margin-bottom: 5px;
    letter-spacing: .06em;
}
/* width e box-sizing explicitos: sem isso o layout depende do
   .form-control do Bootstrap, e qualquer variacao de tema estoura a
   coluna. O grid nao deve confiar em CSS de terceiros. */
#pagamentoModal.pe-on .pe-grid .form-control {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
#pagamentoModal.pe-on .pe-grid .input-group { width: 100%; flex-wrap: nowrap; }
#pagamentoModal.pe-on .pe-grid .input-group .form-control { min-width: 0; }
#pagamentoModal.pe-on .pe-grid .form-control,
#pagamentoModal.pe-on .pe-grid .input-group-text {
    height: 40px;
    font-size: .9rem;
    border-color: var(--pe-line);
}
#pagamentoModal.pe-on .pe-grid > div { min-width: 0; }
#pagamentoModal.pe-on .pe-grid .form-control:focus {
    border-color: var(--pe-accent);
    box-shadow: 0 0 0 3px rgba(11, 107, 83, .12);
}
/* A dica ocupa altura mesmo vazia: sem isso, uma coluna com dica e outra
   sem deixam a linha seguinte desalinhada. */
#pagamentoModal.pe-on .pe-ajuda {
    font-size: .74rem;
    line-height: 1.35;
    color: var(--pe-slate);
    margin-top: 4px;
    min-height: 1.35em;
}
#pagamentoModal.pe-on .pe-opcional {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 400;
    color: var(--pe-faint);
}

/* ---- Parcelas ---- */
#pagamentoModal.pe-on .pe-parcelas {
    border: 1px solid var(--pe-line);
    border-radius: 10px;
    max-height: 244px;
    overflow-y: auto;
    background: var(--pe-surface);
}
#pagamentoModal.pe-on .pe-parcela {
    display: grid;
    grid-template-columns: 88px 48px 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
    border-bottom: 1px solid var(--pe-line);
    cursor: pointer;
    font-size: .86rem;
}
#pagamentoModal.pe-on .pe-parcela:last-child { border-bottom: 0; }
#pagamentoModal.pe-on .pe-parcela:hover { background: var(--pe-sub); }
#pagamentoModal.pe-on .pe-parcela[aria-selected="true"] {
    background: var(--pe-accent-2);
    box-shadow: inset 3px 0 0 var(--pe-accent);
}
#pagamentoModal.pe-on .pe-parcela .pe-forma { font-weight: 600; color: var(--pe-ink); }
#pagamentoModal.pe-on .pe-parcela .pe-vezes { color: var(--pe-slate); }
#pagamentoModal.pe-on .pe-parcela .pe-cet { font-size: .76rem; color: var(--pe-slate); }
#pagamentoModal.pe-on .pe-parcela .pe-cobrar { font-weight: 700; color: var(--pe-ink); }
#pagamentoModal.pe-on .pe-vazio { padding: 26px 14px; text-align: center; color: var(--pe-slate); font-size: .86rem; }

/* ---- Ação principal ---- */
#pagamentoModal.pe-on .pe-acao {
    width: 100%;
    border: 0;
    border-radius: 9px;
    background: var(--pe-accent);
    color: #fff;
    font-weight: 600;
    font-size: .94rem;
    padding: 12px 16px;
    margin-top: 18px;
    cursor: pointer;
    transition: background-color .14s ease;
}
#pagamentoModal.pe-on .pe-acao:hover:not(:disabled) { background: #095843; }
#pagamentoModal.pe-on .pe-acao:disabled { background: var(--pe-line); color: var(--pe-faint); cursor: not-allowed; }
#pagamentoModal.pe-on .pe-acao:focus-visible { outline: 2px solid var(--pe-ink); outline-offset: 2px; }

/* ---- Notas ---- */
#pagamentoModal.pe-on .pe-nota { font-size: .8rem; border-radius: 8px; padding: 9px 12px; margin-top: 10px; }
#pagamentoModal.pe-on .pe-nota-info { background: var(--pe-sub); color: var(--pe-slate); }
#pagamentoModal.pe-on .pe-nota-warn { background: #FEF6E7; color: var(--pe-warn); }
#pagamentoModal.pe-on .pe-nota-erro { background: #FEF2F2; color: var(--pe-danger); }

/* ---- Espera no terminal ---- */
#pagamentoModal.pe-on .pe-espera { text-align: center; padding: 26px 10px 8px; }
#pagamentoModal.pe-on .pe-espera h4 { font-size: 1.05rem; font-weight: 700; color: var(--pe-ink); margin: 16px 0 5px; }
#pagamentoModal.pe-on .pe-espera .pe-resumo { font-size: 1.24rem; font-weight: 700; color: var(--pe-accent); margin-bottom: 3px; }
#pagamentoModal.pe-on .pe-espera .pe-conta { font-size: .82rem; color: var(--pe-slate); }
#pagamentoModal.pe-on .pe-pulso { animation: pePulso 1.9s ease-in-out infinite; color: var(--pe-accent); }
@keyframes pePulso { 0%, 100% { opacity: 1; } 50% { opacity: .38; } }
#pagamentoModal.pe-on .pe-cancelar {
    width: 100%; border: 1px solid var(--pe-danger); background: var(--pe-surface); color: var(--pe-danger);
    border-radius: 9px; padding: 11px; font-weight: 600; font-size: .9rem; margin-top: 22px; cursor: pointer;
}
#pagamentoModal.pe-on .pe-cancelar:hover { background: var(--pe-sub); }

@media (prefers-reduced-motion: reduce) {
    #pagamentoModal.pe-on .pe-pane.pe-ativo { animation: none; }
    #pagamentoModal.pe-on .pe-pulso { animation: none; }
}

/* ---- Canhoto do boleto ---- */
#pagamentoModal.pe-on .pe-canhoto {
    border: 1px solid var(--pe-line); border-radius: 10px; overflow: hidden;
    background: var(--pe-surface); margin-top: 4px;
}
#pagamentoModal.pe-on .pe-canhoto-topo {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 12px 16px; background: var(--pe-accent); color: #fff;
}
#pagamentoModal.pe-on .pe-canhoto-topo .pe-valor { font-size: 1.2rem; font-weight: 700; }
#pagamentoModal.pe-on .pe-canhoto-topo .pe-venc { font-size: .78rem; opacity: .9; }
#pagamentoModal.pe-on .pe-canhoto-corpo { padding: 14px 16px; }

/* Linha digitável: o operador confere dígito a dígito. */
#pagamentoModal.pe-on .pe-linha {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
    font-size: .93rem;
    color: var(--pe-ink);
    background: var(--pe-sub);
    border: 1px dashed var(--pe-line);
    border-radius: 7px;
    padding: 11px 13px;
    word-break: break-all;
    line-height: 1.5;
}
#pagamentoModal.pe-on .pe-barras { display: flex; gap: 1.5px; height: 34px; margin-bottom: 12px; opacity: .82; }
#pagamentoModal.pe-on .pe-barras i { background: var(--pe-ink); display: block; }
body.dark-mode #pagamentoModal.pe-on .pe-barras i { background: var(--pe-slate); }
#pagamentoModal.pe-on .pe-botoes { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
#pagamentoModal.pe-on .pe-btn {
    border: 1px solid var(--pe-line); background: var(--pe-surface); color: var(--pe-ink);
    border-radius: 8px; padding: 8px 13px; font-size: .84rem; font-weight: 600;
    cursor: pointer; text-decoration: none; display: inline-flex; align-items: center;
}
#pagamentoModal.pe-on .pe-btn:hover { background: var(--pe-sub); color: var(--pe-ink); text-decoration: none; }
#pagamentoModal.pe-on .pe-btn-forte { background: var(--pe-accent); border-color: var(--pe-accent); color: #fff; }
#pagamentoModal.pe-on .pe-btn-forte:hover { background: #095843; color: #fff; }

/* ---- Boletos já emitidos ---- */
#pagamentoModal.pe-on .pe-emitido {
    display: flex; align-items: center; flex-wrap: wrap; gap: 8px 12px;
    padding: 11px 14px; border: 1px solid var(--pe-line); border-radius: 9px;
    margin-bottom: 8px; font-size: .86rem; background: var(--pe-surface);
}
#pagamentoModal.pe-on .pe-emitido .pe-cresce { flex: 1 1 220px; }
#pagamentoModal.pe-on .pe-fraco { font-weight: 400; color: var(--pe-slate); font-size: .76rem; }
#pagamentoModal.pe-on .pe-quebra { word-break: break-all; }
#pagamentoModal.pe-on .pe-canhoto-selo {
    font-size: .72rem; opacity: .85; letter-spacing: .08em; text-transform: uppercase;
}
#pagamentoModal.pe-on .pe-emitido .pe-cresce { flex: 1; min-width: 0; }
#pagamentoModal.pe-on .pe-tag {
    font-size: .7rem; font-weight: 700; padding: 3px 9px; border-radius: 999px; white-space: nowrap;
}
#pagamentoModal.pe-on .pe-tag-aberto { background: var(--pe-sub); color: var(--pe-warn); }
#pagamentoModal.pe-on .pe-tag-pago { background: var(--pe-accent-2); color: var(--pe-accent); }
#pagamentoModal.pe-on .pe-tag-alerta { background: var(--pe-sub); color: var(--pe-danger); }
#pagamentoModal.pe-on .pe-tag-morto { background: #F1F5F9; color: var(--pe-slate); }
</style>

<script>
(function () {
    'use strict';

    var PE = {
        osId: <?= $peOsId ?>,
        csrf: '<?= htmlspecialchars(pe_csrf_token(), ENT_QUOTES, 'UTF-8') ?>',
        saldo: <?= json_encode(round($peSaldo, 2)) ?>,
        timeoutVenda: <?= $peTimeoutVenda ?>,
        temPos: <?= $pePos ? 'true' : 'false' ?>,
        temBoleto: <?= $peBoleto ? 'true' : 'false' ?>,
        cliente: <?= json_encode($peCliente, JSON_UNESCAPED_UNICODE) ?>,
        documento: <?= json_encode($peDocumento) ?>,
        vencPadrao: <?= json_encode($peVencPadrao) ?>,
        sandbox: <?= $peSandbox ? 'true' : 'false' ?>,
        intervalo: <?= $peIntervalo * 1000 ?>,
        saleId: null, forma: null, parcelas: 1,
        inicio: null, cancelado: false, timer: null, montado: false
    };

    /* ---------------- utilitários ---------------- */

    function brl(v) {
        return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmt(v) {
        return Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    /* Aceita "1.234,56", "1234,56" e "1234.56". Cobrança nunca é negativa. */
    function lerValor(sel) {
        var txt = String($(sel).val() || '').replace(/[^\d.,]/g, '');
        if (txt.indexOf(',') !== -1) txt = txt.replace(/\./g, '').replace(',', '.');
        var n = parseFloat(txt);
        return (isNaN(n) || n < 0) ? 0 : n;
    }

    /* O jQuery reduz qualquer HTTP >= 400 a falha genérica e descarta o
       corpo. Nossos endpoints mandam {error}, então lemos de lá. */
    function erroDo(xhr, alternativa) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.error) return xhr.responseJSON.error;
        if (xhr && xhr.responseText) {
            try { var j = JSON.parse(xhr.responseText); if (j && j.error) return j.error; }
            catch (e) { return 'O servidor respondeu em formato inesperado. Verifique o log do PHP.'; }
        }
        return alternativa || 'Não foi possível concluir a operação.';
    }

    function nota(sel, texto, tipo) {
        var el = $(sel);
        if (!el.length) return;
        if (!texto) { el.hide().text(''); return; }
        el.attr('class', 'pe-nota pe-nota-' + (tipo || 'info')).text(texto).show();
    }

    /* Mesma validação por dígito verificador que o servidor faz. Duplicar
       aqui evita a ida e volta no erro mais comum do balcão: CPF vazio ou
       digitado errado. O servidor continua validando — o cliente é
       conveniência, nunca a garantia. */
    function documentoValido(valor) {
        var d = String(valor || '').replace(/\D/g, '');

        if (d.length === 11) {
            if (/^(\d)\1{10}$/.test(d)) return false;
            for (var t = 9; t < 11; t++) {
                var soma = 0;
                for (var i = 0; i < t; i++) soma += parseInt(d[i], 10) * ((t + 1) - i);
                if (parseInt(d[t], 10) !== ((10 * soma) % 11) % 10) return false;
            }
            return true;
        }

        if (d.length === 14) {
            if (/^(\d)\1{13}$/.test(d)) return false;
            var calc = function (pesos) {
                var soma = 0;
                for (var j = 0; j < pesos.length; j++) soma += parseInt(d[j], 10) * pesos[j];
                var r = soma % 11;
                return r < 2 ? 0 : 11 - r;
            };
            return parseInt(d[12], 10) === calc([5,4,3,2,9,8,7,6,5,4,3,2])
                && parseInt(d[13], 10) === calc([6,5,4,3,2,9,8,7,6,5,4,3,2]);
        }

        return false;
    }

    function mascaraDocumento(valor) {
        var d = String(valor || '').replace(/\D/g, '').slice(0, 14);

        if (d.length <= 11) {
            return d.replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }

        return d.replace(/(\d{2})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1/$2')
                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }

    var ICONES = {
        manual: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>',
        pos: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M8 6h8M8 10h8"/><rect x="8" y="14" width="8" height="4" rx="1"/></svg>',
        boleto: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9v6M10 9v6M13 9v6M17 9v6"/></svg>'
    };

    /* Faixa derivada da própria linha digitável: a largura de cada barra vem
       dos dígitos, então dois boletos desenham faixas diferentes. Não é um
       código de barras legível — o valor conferível é a linha logo abaixo. */
    function faixaBarras(linha) {
        var d = String(linha || '').replace(/\D/g, '');
        if (!d) return '';
        var html = '<div class="pe-barras" aria-hidden="true">';
        for (var i = 0; i < d.length; i++) {
            html += '<i style="width:' + (1 + (parseInt(d[i], 10) % 3)) + 'px"></i>';
        }
        return html + '</div>';
    }

    /* ---------------- montagem ---------------- */

    function montar() {
        if (PE.montado) return;

        var manualGrid = $('#btnAdicionarPagamento').closest('.form-grid');
        var corpo = $('#pagamentoModal .modal-body');

        if (!manualGrid.length || !corpo.length) return;

        PE.montado = true;
        $('#pagamentoModal').addClass('pe-on');

        var tituloManual = manualGrid.prev('.section-title');
        var ancora = tituloManual.length ? tituloManual : manualGrid;

        // Fita de valores no lugar da stats-grid original.
        var stats = corpo.find('.stats-grid').first();
        var fita = $('<dl class="pe-tape pe-num">' +
            '<div><dt>Total da O.S.</dt><dd id="peTapeTotal">—</dd></div>' +
            '<div><dt>Pago</dt><dd id="peTapePago">—</dd></div>' +
            '<div class="pe-devido"><dt>A receber</dt><dd id="peTapeDevido">' + brl(PE.saldo) + '</dd></div>' +
            '</dl>');

        if (stats.length) {
            var lerCard = function (rotulo) {
                var achado = null;
                stats.find('.stat-card').each(function () {
                    if ($(this).find('.stat-label').text().trim().toLowerCase().indexOf(rotulo) !== -1) {
                        achado = $(this).find('input').val();
                        return false;
                    }
                });
                return achado;
            };

            fita.insertBefore(stats);
            stats.hide();
            $('#peTapeTotal').text(lerCard('total') || '—');
            $('#peTapePago').text(lerCard('pago') || brl(0));
        } else {
            fita.prependTo(corpo);
        }

        // Barra de métodos.
        var metodos = $('<div class="pe-metodos" role="tablist"></div>');

        function tile(chave, titulo, sub) {
            return $('<button type="button" class="pe-metodo" role="tab" aria-selected="false">' +
                ICONES[chave] + '<span><b>' + titulo + '</b><em>' + sub + '</em></span></button>')
                .attr('data-pe', chave);
        }

        metodos.append(tile('manual', 'Lançamento manual', 'Espécie, PIX, cheque'));
        if (PE.temPos) metodos.append(tile('pos', 'Maquininha', 'Cartão no terminal'));
        if (PE.temBoleto) metodos.append(tile('boleto', 'Boleto', 'Para pagar depois'));

        ancora.before(metodos);

        // Painel manual: envolve o que já existe, sem recriar nada.
        var paneManual = $('<div class="pe-pane pe-ativo" data-pane="manual"></div>');
        ancora.before(paneManual);
        if (tituloManual.length) paneManual.append(tituloManual);
        paneManual.append(manualGrid);

        var ultimo = paneManual;

        if (PE.temPos) { ultimo.after(painelPos()); ultimo = paneManual.next(); }
        if (PE.temBoleto) { ultimo.after(painelBoleto()); }

        metodos.on('click', '.pe-metodo', function () { selecionar($(this).attr('data-pe')); });
        metodos.find('.pe-metodo').first().attr('aria-selected', 'true');
    }

    function selecionar(chave) {
        $('#pagamentoModal .pe-metodo').attr('aria-selected', function () {
            return $(this).attr('data-pe') === chave ? 'true' : 'false';
        });

        $('#pagamentoModal .pe-pane').removeClass('pe-ativo')
            .filter('[data-pane="' + chave + '"]').addClass('pe-ativo');

        if (chave === 'pos') { carregarTerminais(); carregarParcelas(); }
        if (chave === 'boleto') { listarBoletos(); }
    }

    /* ================================================================
       MAQUININHA
       ================================================================ */

    function painelPos() {
        return $('<div class="pe-pane" data-pane="pos">' +
            '<div id="pePosForm">' +
              '<div class="pe-grid">' +
                '<div class="pe-c5">' +
                  '<label class="pe-rotulo" for="pePosValor">Valor a cobrar</label>' +
                  '<div class="input-group">' +
                    '<div class="input-group-prepend"><span class="input-group-text">R$</span></div>' +
                    '<input type="text" class="form-control pe-num" id="pePosValor" value="' + fmt(PE.saldo) + '">' +
                  '</div>' +
                  '<div class="pe-ajuda">Saldo em aberto: ' + brl(PE.saldo) + '</div>' +
                '</div>' +
                '<div class="pe-c7">' +
                  '<label class="pe-rotulo" for="pePosTerminal">Terminal</label>' +
                  '<select class="form-control" id="pePosTerminal"><option value="">Carregando…</option></select>' +
                  '<div class="pe-ajuda">Onde o cliente vai passar o cartão.</div>' +
                '</div>' +
              '</div>' +
              '<div id="pePosErroTerminal" class="pe-nota pe-nota-erro" style="display:none"></div>' +
              '<span class="pe-rotulo" style="margin-top:18px">Forma de pagamento</span>' +
              '<div class="pe-parcelas pe-num" id="pePosParcelas">' +
                '<div class="pe-vazio">Informe o valor para ver as opções.</div>' +
              '</div>' +
              '<div id="pePosNota" class="pe-nota pe-nota-info" style="display:none"></div>' +
              '<button type="button" class="pe-acao" id="pePosEnviar" disabled>Enviar para a maquininha</button>' +
            '</div>' +
            '<div id="pePosEspera" style="display:none"><div class="pe-espera">' +
              '<div class="pe-pulso"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M8 6h8M8 10h8"/><rect x="8" y="14" width="8" height="4" rx="1"/></svg></div>' +
              '<h4>Finalize na maquininha</h4>' +
              '<div class="pe-resumo pe-num" id="pePosResumo"></div>' +
              '<div class="pe-conta" id="pePosConta"></div>' +
              '<button type="button" class="pe-cancelar" id="pePosInterromper">Interromper cobrança</button>' +
            '</div></div>' +
          '</div>');
    }

    function carregarTerminais() {
        var sel = $('#pePosTerminal');
        if (!sel.length || sel.attr('data-carregado')) return;

        sel.html('<option value="">Carregando…</option>');

        $.get('pagamento_online/pe_terminais.php', { csrf: PE.csrf }, function (r) {
            if (!r || !r.success || !r.terminais || !r.terminais.length) {
                sel.html('<option value="">Nenhum terminal disponível</option>');
                nota('#pePosErroTerminal', 'Nenhum terminal vinculado a esta conta. Solicite um à Parcela Express.', 'erro');
                return;
            }

            sel.attr('data-carregado', '1').empty().append('<option value="">Selecione o terminal</option>');

            r.terminais.forEach(function (t) {
                var id = t.id || t.terminal_id || t.serial_number;
                sel.append($('<option>').val(id).text(t.name || t.serial_number || id));
            });

            // Um terminal só: escolhe sozinho, poupa um clique no balcão.
            if (r.terminais.length === 1) sel.val(sel.find('option').eq(1).val()).trigger('change');

            nota('#pePosErroTerminal', '');
        }, 'json').fail(function (xhr) {
            sel.html('<option value="">Indisponível</option>');
            nota('#pePosErroTerminal', erroDo(xhr, 'Não foi possível consultar os terminais.'), 'erro');
        });
    }

    function formasBasicas() {
        var l = [{ form_payment: 'pix', installments: 1 }, { form_payment: 'debit', installments: 1 }];
        for (var n = 1; n <= 21; n++) l.push({ form_payment: 'credit', installments: n });
        return l;
    }

    function rotuloForma(f) {
        var s = String(f).toLowerCase();
        if (s.indexOf('pix') === 0) return 'PIX';
        if (s.indexOf('deb') === 0) return 'Débito';
        if (s.indexOf('cred') === 0) return 'Crédito';
        return f;
    }

    function carregarParcelas() {
        var valor = lerValor('#pePosValor');
        var box = $('#pePosParcelas');

        nota('#pePosNota', '');
        PE.forma = null;
        $('#pePosEnviar').prop('disabled', true);

        if (valor <= 0) {
            box.html('<div class="pe-vazio">Informe o valor para ver as opções.</div>');
            return;
        }

        box.html('<div class="pe-vazio">Consultando as taxas da serventia…</div>');

        $.get('pagamento_online/pe_simular.php', { csrf: PE.csrf, valor: valor }, function (r) {
            if (r && r.success && r.opcoes && r.opcoes.length) {
                desenharParcelas(r.opcoes, false);
            } else {
                desenharParcelas(formasBasicas(), true);
                nota('#pePosNota', 'Taxas indisponíveis. Os valores serão aplicados pela maquininha.', 'warn');
            }
        }, 'json').fail(function (xhr) {
            desenharParcelas(formasBasicas(), true);
            nota('#pePosNota', erroDo(xhr, 'Taxas indisponíveis.') + ' Os valores serão aplicados pela maquininha.', 'warn');
        });
    }

    function desenharParcelas(opcoes, semTaxas) {
        var html = '';

        opcoes.forEach(function (o) {
            var forma = o.form_payment || o.forma || 'credit';
            var n = parseInt(o.installments || o.parcelas || 1, 10);
            var cet = o.effective_cost || o.cet;

            html += '<div class="pe-parcela" role="option" aria-selected="false"' +
                ' data-forma="' + esc(forma) + '" data-parcelas="' + n + '">' +
                '<span class="pe-forma">' + esc(rotuloForma(forma)) + '</span>' +
                '<span class="pe-vezes">' + n + 'x</span>' +
                '<span class="pe-cet">' + (semTaxas ? '' : (cet ? esc(cet) + '% de custo' : '')) + '</span>' +
                '<span class="pe-cobrar">' + (semTaxas ? '' : brl(o.total_amount || o.valor_cobrar)) + '</span>' +
                '</div>';
        });

        $('#pePosParcelas').html(html);
    }

    $(document).on('click', '#pagamentoModal .pe-parcela', function () {
        var el = $(this);
        $('#pePosParcelas .pe-parcela').attr('aria-selected', 'false');
        el.attr('aria-selected', 'true');

        PE.forma = el.attr('data-forma');
        PE.parcelas = parseInt(el.attr('data-parcelas'), 10) || 1;

        var precisaEmissor = String(PE.forma).toLowerCase().indexOf('cred') === 0
            && PE.parcelas >= 13 && PE.parcelas <= 18;

        if (precisaEmissor) {
            nota('#pePosNota', 'De 13x a 18x o parcelamento depende de aprovação do banco emissor.', 'warn');
        }

        $('#pePosEnviar').prop('disabled', !$('#pePosTerminal').val());
    });

    $(document).on('change', '#pePosTerminal', function () {
        $('#pePosEnviar').prop('disabled', !$(this).val() || !PE.forma);
    });

    var debounce = null;
    $(document).on('input', '#pePosValor', function () {
        clearTimeout(debounce);
        debounce = setTimeout(carregarParcelas, 550);
    });

    $(document).on('click', '#pePosEnviar', function () {
        var btn = $(this).prop('disabled', true).text('Enviando…');

        $.post('pagamento_online/pe_venda_criar.php', {
            csrf: PE.csrf,
            os_id: PE.osId,
            terminal_id: $('#pePosTerminal').val(),
            forma: PE.forma,
            parcelas: PE.parcelas,
            valor: lerValor('#pePosValor'),
            observacao: ($('#observacao_pagamento').val() || '').trim().slice(0, 500)
        }, function (r) {
            btn.text('Enviar para a maquininha');

            if (!r || !r.success) {
                Swal.fire({ icon: 'error', title: 'Não foi possível enviar', text: (r && r.error) || '' });
                btn.prop('disabled', false);
                return;
            }

            PE.saleId = r.sale_id;
            PE.inicio = Date.now();
            PE.cancelado = false;

            $('#pePosResumo').text(brl(r.valor) + ' · ' + r.parcelas + 'x');
            $('#pePosForm').hide();
            $('#pePosEspera').show();

            contar();
            aguardar();
        }, 'json').fail(function (xhr) {
            btn.text('Enviar para a maquininha').prop('disabled', false);
            Swal.fire({ icon: 'error', title: 'Não foi possível enviar', text: erroDo(xhr) });
        });
    });

    function contar() {
        clearInterval(PE.timer);

        PE.timer = setInterval(function () {
            var resta = PE.timeoutVenda - Math.floor((Date.now() - PE.inicio) / 1000);
            if (resta <= 0) { clearInterval(PE.timer); return; }
            $('#pePosConta').text('Aguardando o operador — ' + resta + 's');
        }, 1000);
    }

    /* O spec não expõe rota de acompanhamento para POS: usamos a consulta
       comum de venda, GET /v1/sellers/{id}/sales/{saleId}. Por isso as
       chamadas são espaçadas pelo intervalo configurado, em vez de
       reabertas na hora — sem isso seria um loop quente contra a API. */
    function aguardar() {
        if (PE.cancelado || !PE.saleId) return;

        if ((Date.now() - PE.inicio) / 1000 >= PE.timeoutVenda) { interromper(true); return; }

        $.get('pagamento_online/pe_venda_status.php', {
            csrf: PE.csrf, sale_id: PE.saleId, os_id: PE.osId,
            observacao: ($('#observacao_pagamento').val() || '').trim().slice(0, 500)
        }, function (r) {
            if (PE.cancelado) return;
            if (!r || !r.success) { encerrarPos(false, (r && r.error) || 'Falha ao consultar o status.'); return; }
            if (r.aguardando) { setTimeout(aguardar, PE.intervalo); return; }

            if (r.aprovada) {
                refletirPagamento(r);
                encerrarPos(true, 'Pagamento aprovado e lançado na O.S.');
            } else {
                encerrarPos(false, r.motivo || 'A venda não foi aprovada.');
            }
        }, 'json').fail(function (xhr, status) {
            if (PE.cancelado) return;
            if (status === 'timeout' || xhr.status === 0) { setTimeout(aguardar, PE.intervalo); return; }
            encerrarPos(false, erroDo(xhr, 'Falha ao acompanhar a venda.'));
        });
    }

    function interromper(porTempo) {
        PE.cancelado = true;
        clearInterval(PE.timer);

        $.post('pagamento_online/pe_venda_interromper.php',
            { csrf: PE.csrf, sale_id: PE.saleId, os_id: PE.osId }, function () {}, 'json'
        ).always(function () {
            encerrarPos(false, porTempo ? 'A cobrança expirou e foi interrompida.' : 'Cobrança interrompida.');
        });
    }

    $(document).on('click', '#pePosInterromper', function () {
        Swal.fire({
            title: 'Interromper a cobrança?',
            text: 'A venda será cancelada no terminal.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Interromper',
            cancelButtonText: 'Continuar aguardando'
        }).then(function (res) { if (res.isConfirmed) interromper(false); });
    });

    function encerrarPos(sucesso, mensagem) {
        PE.cancelado = true;
        clearInterval(PE.timer);
        PE.saleId = null;

        $('#pePosEspera').hide();
        $('#pePosForm').show();
        $('#pePosEnviar').prop('disabled', false);

        if (sucesso) {
            $('#pagamentoModal').modal('hide');
            Swal.fire({ icon: 'success', title: 'Pagamento aprovado', text: mensagem });
        } else {
            Swal.fire({ icon: 'info', title: 'Cobrança não concluída', text: mensagem });
        }
    }

    /* ================================================================
       BOLETO
       ================================================================ */

    function painelBoleto() {
        return $('<div class="pe-pane" data-pane="boleto">' +
            '<div id="peBoletoEmitidos"></div>' +
            '<div id="peBoletoForm">' +

              '<div class="pe-secao"><span>Cobrança</span></div>' +
              '<div class="pe-grid">' +
                '<div class="pe-c4">' +
                  '<label class="pe-rotulo" for="peBolValor">Valor</label>' +
                  '<div class="input-group">' +
                    '<div class="input-group-prepend"><span class="input-group-text">R$</span></div>' +
                    '<input type="text" class="form-control pe-num" id="peBolValor" value="' + fmt(PE.saldo) + '">' +
                  '</div>' +
                  '<div class="pe-ajuda">Saldo em aberto: ' + brl(PE.saldo) + '</div>' +
                '</div>' +
                '<div class="pe-c4">' +
                  '<label class="pe-rotulo" for="peBolVenc">Vencimento</label>' +
                  '<input type="date" class="form-control" id="peBolVenc" value="' + PE.vencPadrao + '">' +
                  '<div class="pe-ajuda">Prazo para o cliente pagar.</div>' +
                '</div>' +
                '<div class="pe-c4">' +
                  '<label class="pe-rotulo" for="peBolInstr">Instruções <span class="pe-opcional">(opcional)</span></label>' +
                  '<input type="text" class="form-control" id="peBolInstr" maxlength="255" placeholder="Não receber após o vencimento">' +
                  '<div class="pe-ajuda">Aparece impresso no boleto.</div>' +
                '</div>' +
              '</div>' +

              '<div class="pe-secao"><span>Pagador</span></div>' +
              '<div class="pe-grid">' +
                '<div class="pe-c12">' +
                  '<label class="pe-rotulo" for="peBolNome">Nome completo</label>' +
                  '<input type="text" class="form-control" id="peBolNome" value="' + esc(PE.cliente) + '">' +
                  '<div class="pe-ajuda">Precisa bater com o cadastro na Receita Federal.</div>' +
                '</div>' +
                '<div class="pe-c5">' +
                  '<label class="pe-rotulo" for="peBolDoc">CPF ou CNPJ</label>' +
                  '<input type="text" class="form-control pe-num" id="peBolDoc" value="' + esc(PE.documento) + '" inputmode="numeric" placeholder="000.000.000-00">' +
                '</div>' +
                '<div class="pe-c7">' +
                  '<label class="pe-rotulo" for="peBolEmail">E-mail <span class="pe-opcional">(opcional)</span></label>' +
                  '<input type="email" class="form-control" id="peBolEmail" placeholder="para enviar o boleto ao cliente">' +
                '</div>' +
              '</div>' +

              '<div class="pe-secao"><span>Endereço do pagador</span></div>' +
              '<div class="pe-grid">' +
                '<div class="pe-c3">' +
                  '<label class="pe-rotulo" for="peBolCep">CEP</label>' +
                  '<input type="text" class="form-control pe-num" id="peBolCep" inputmode="numeric" maxlength="9" placeholder="00000-000">' +
                  '<div class="pe-ajuda" id="peBolCepAviso">Preenche o resto sozinho.</div>' +
                '</div>' +
                '<div class="pe-c7">' +
                  '<label class="pe-rotulo" for="peBolLogradouro">Logradouro</label>' +
                  '<input type="text" class="form-control" id="peBolLogradouro">' +
                  '<div class="pe-ajuda"></div>' +
                '</div>' +
                '<div class="pe-c2">' +
                  '<label class="pe-rotulo" for="peBolNumero">Número</label>' +
                  '<input type="text" class="form-control" id="peBolNumero" placeholder="S/N">' +
                  '<div class="pe-ajuda"></div>' +
                '</div>' +
                '<div class="pe-c5">' +
                  '<label class="pe-rotulo" for="peBolBairro">Bairro</label>' +
                  '<input type="text" class="form-control" id="peBolBairro">' +
                '</div>' +
                '<div class="pe-c5">' +
                  '<label class="pe-rotulo" for="peBolCidade">Cidade</label>' +
                  '<input type="text" class="form-control" id="peBolCidade">' +
                '</div>' +
                '<div class="pe-c2">' +
                  '<label class="pe-rotulo" for="peBolUf">UF</label>' +
                  '<input type="text" class="form-control" id="peBolUf" maxlength="2" style="text-transform:uppercase">' +
                '</div>' +
                '<div class="pe-c12">' +
                  '<label class="pe-rotulo" for="peBolComplemento">Complemento <span class="pe-opcional">(opcional)</span></label>' +
                  '<input type="text" class="form-control" id="peBolComplemento" placeholder="Apto, bloco, sala">' +
                '</div>' +
              '</div>' +

              '<div class="pe-nota pe-nota-info" style="margin-top:20px">' +
                'Emitir não dá baixa na O.S. O pagamento entra quando a compensação for confirmada.' +
              '</div>' +
              '<div id="peBolErro" class="pe-nota pe-nota-erro" style="display:none"></div>' +
              '<button type="button" class="pe-acao" id="peBolEmitir">Emitir boleto</button>' +
            '</div>' +
          '</div>');
    }

    function listarBoletos() {
        var box = $('#peBoletoEmitidos');
        if (!box.length) return;

        $.get('pagamento_online/pe_boleto_listar.php', { csrf: PE.csrf, os_id: PE.osId }, function (r) {
            if (!r || !r.success || !r.boletos || !r.boletos.length) { box.empty(); return; }

            var html = '<span class="pe-rotulo">Boletos desta O.S.</span>';

            r.boletos.forEach(function (b) {
                var classe = b.pago ? 'pe-tag-pago'
                           : (b.aberto ? 'pe-tag-aberto'
                           : (b.indeterminado ? 'pe-tag-alerta' : 'pe-tag-morto'));

                html += '<div class="pe-emitido">' +
                    '<div class="pe-cresce">' +
                      '<div class="pe-num" style="font-weight:700">' + brl(b.valor) +
                      (b.vencimento_br ? ' <span class="pe-fraco">· vence ' + esc(b.vencimento_br) + '</span>' : '') +
                      '</div>' +
                      (b.linha_digitavel ? '<div class="pe-fraco pe-quebra">' + esc(b.linha_digitavel) + '</div>' : '') +
                    '</div>' +
                    '<span class="pe-tag ' + classe + '">' + esc(b.status_rotulo) + '</span>' +
                    (b.url_pdf ? '<a class="pe-btn" href="' + esc(b.url_pdf) + '" target="_blank" rel="noopener">Abrir</a>' : '') +
                    (b.aberto ? '<button type="button" class="pe-btn pe-conferir" data-ref="' + esc(b.local_reference) + '">Conferir</button>' : '') +
                    (b.aberto && PE.sandbox ? '<button type="button" class="pe-btn pe-simular" data-ref="' + esc(b.local_reference) + '" title="Só funciona em sandbox">Simular pagamento</button>' : '') +
                    '</div>' +
                    (b.indeterminado
                       ? '<div class="pe-nota pe-nota-warn" style="margin:-4px 0 8px">Esta emissão não foi confirmada pela Parcela Express. Verifique no portal antes de emitir outro.</div>'
                       : '');
            });

            box.html(html);
        }, 'json');
    }

    $(document).on('click', '#pagamentoModal .pe-conferir', function () {
        var btn = $(this).prop('disabled', true).text('Conferindo…');

        $.post('pagamento_online/pe_boleto_consultar.php',
            { csrf: PE.csrf, os_id: PE.osId, local_reference: btn.attr('data-ref') },
            function (r) {
                btn.prop('disabled', false).text('Conferir');

                if (!r || !r.success) {
                    Swal.fire({ icon: 'error', title: 'Não foi possível conferir', text: (r && r.error) || '' });
                    return;
                }

                if (!r.pago) {
                    Swal.fire({
                        icon: 'info',
                        title: r.status_rotulo || 'Ainda não compensado',
                        text: 'A compensação bancária costuma levar até um dia útil.'
                    });
                    listarBoletos();
                    return;
                }

                if (!r.ja_lancado) refletirPagamento(r);

                listarBoletos();
                Swal.fire({ icon: 'success', title: 'Boleto pago', text: 'Pagamento lançado na O.S.' });
            }, 'json'
        ).fail(function (xhr) {
            btn.prop('disabled', false).text('Conferir');
            Swal.fire({ icon: 'error', title: 'Não foi possível conferir', text: erroDo(xhr) });
        });
    });

    /* Busca o endereço pelo CEP. O ViaCEP é público e aceita CORS; se
       estiver fora do ar ou o micro sem internet, o operador digita à mão —
       por isso a falha só avisa, não bloqueia. */
    var cepTimer = null;

    $(document).on('input', '#peBolCep', function () {
        var campo = $(this);
        campo.val(String(campo.val() || '').replace(/\D/g, '').slice(0, 8)
            .replace(/^(\d{5})(\d)/, '$1-$2'));

        var cep = String(campo.val()).replace(/\D/g, '');
        clearTimeout(cepTimer);

        if (cep.length !== 8) { $('#peBolCepAviso').text('Preenche o resto sozinho.'); return; }

        $('#peBolCepAviso').text('Buscando…');

        cepTimer = setTimeout(function () {
            $.getJSON('https://viacep.com.br/ws/' + cep + '/json/')
                .done(function (d) {
                    if (!d || d.erro) { $('#peBolCepAviso').text('CEP não encontrado — preencha à mão.'); return; }

                    $('#peBolCepAviso').text('Endereço preenchido.');
                    if (d.logradouro) $('#peBolLogradouro').val(d.logradouro);
                    if (d.bairro) $('#peBolBairro').val(d.bairro);
                    if (d.localidade) $('#peBolCidade').val(d.localidade);
                    if (d.uf) $('#peBolUf').val(d.uf);
                    if (!$('#peBolNumero').val()) $('#peBolNumero').focus();
                })
                .fail(function () {
                    $('#peBolCepAviso').text('Não foi possível consultar o CEP — preencha à mão.');
                });
        }, 400);
    });

    $(document).on('input', '#peBolUf', function () {
        $(this).val(String($(this).val() || '').replace(/[^a-zA-Z]/g, '').toUpperCase().slice(0, 2));
    });

    $(document).on('input', '#peBolDoc', function () {
        var pos = this.selectionStart === this.value.length;
        $(this).val(mascaraDocumento($(this).val()));
        if (pos) this.setSelectionRange(this.value.length, this.value.length);
    });

    /* Sandbox: marca o boleto como pago na API, para exercitar o ciclo
       completo sem esperar compensação bancária. Não existe em produção. */
    $(document).on('click', '#pagamentoModal .pe-simular', function () {
        var btn = $(this).prop('disabled', true).text('Simulando…');

        $.post('pagamento_online/pe_boleto_pagar_sandbox.php',
            { csrf: PE.csrf, local_reference: btn.attr('data-ref') },
            function (r) {
                btn.prop('disabled', false).text('Simular pagamento');

                if (!r || !r.success) {
                    Swal.fire({ icon: 'error', title: 'Não foi possível simular', text: (r && r.error) || '' });
                    return;
                }

                Swal.fire({ icon: 'success', title: 'Pago no sandbox', text: r.mensagem || '' });
                listarBoletos();
            }, 'json'
        ).fail(function (xhr) {
            btn.prop('disabled', false).text('Simular pagamento');
            Swal.fire({ icon: 'error', title: 'Não foi possível simular', text: erroDo(xhr) });
        });
    });

    $(document).on('click', '#peBolEmitir', function () {
        var doc = $('#peBolDoc').val();

        if (!documentoValido(doc)) {
            nota('#peBolErro', String(doc || '').trim() === ''
                ? 'Informe o CPF ou CNPJ do pagador.'
                : 'O CPF ou CNPJ informado não confere.', 'erro');
            $('#peBolDoc').focus();
            return;
        }

        if (!String($('#peBolNome').val() || '').trim()) {
            nota('#peBolErro', 'Informe o nome do pagador.', 'erro');
            $('#peBolNome').focus();
            return;
        }

        /* Endereço é obrigatório na prática: sem ele a API responde erro
           interno em vez de recusar com mensagem. */
        var faltando = [
            ['#peBolCep', String($('#peBolCep').val() || '').replace(/\D/g, '').length === 8, 'CEP'],
            ['#peBolLogradouro', String($('#peBolLogradouro').val() || '').trim() !== '', 'Logradouro'],
            ['#peBolBairro', String($('#peBolBairro').val() || '').trim() !== '', 'Bairro'],
            ['#peBolCidade', String($('#peBolCidade').val() || '').trim() !== '', 'Cidade'],
            ['#peBolUf', String($('#peBolUf').val() || '').trim().length === 2, 'UF']
        ].filter(function (f) { return !f[1]; });

        if (faltando.length) {
            nota('#peBolErro', 'Endereço do pagador incompleto: ' +
                faltando.map(function (f) { return f[2]; }).join(', ') + '.', 'erro');
            $(faltando[0][0]).focus();
            return;
        }

        var btn = $(this).prop('disabled', true).text('Emitindo…');
        nota('#peBolErro', '');

        $.post('pagamento_online/pe_boleto_criar.php', {
            csrf: PE.csrf,
            os_id: PE.osId,
            valor: lerValor('#peBolValor'),
            vencimento: $('#peBolVenc').val(),
            pagador_nome: $('#peBolNome').val(),
            pagador_documento: $('#peBolDoc').val(),
            pagador_email: $('#peBolEmail').val(),
            instrucoes: $('#peBolInstr').val(),
            cep: $('#peBolCep').val(),
            logradouro: $('#peBolLogradouro').val(),
            numero: $('#peBolNumero').val(),
            complemento: $('#peBolComplemento').val(),
            bairro: $('#peBolBairro').val(),
            cidade: $('#peBolCidade').val(),
            uf: $('#peBolUf').val()
        }, function (r) {
            btn.prop('disabled', false).text('Emitir boleto');

            if (!r || !r.success) { nota('#peBolErro', (r && r.error) || 'Falha na emissão.', 'erro'); return; }

            mostrarCanhoto(r.boleto);
            listarBoletos();
        }, 'json').fail(function (xhr) {
            btn.prop('disabled', false).text('Emitir boleto');
            nota('#peBolErro', erroDo(xhr, 'Falha na emissão.'), 'erro');
        });
    });

    /* O boleto emitido vira canhoto: o operador reconhece o artefato e
       confere a linha digitável dígito a dígito. */
    function mostrarCanhoto(b) {
        var html = '<div class="pe-canhoto">' +
            '<div class="pe-canhoto-topo">' +
              '<div><div class="pe-valor pe-num">' + brl(b.valor) + '</div>' +
              '<div class="pe-venc">Vence em ' + esc(b.vencimento_br) + '</div></div>' +
              '<div style="text-align:right">' +
                '<div class="pe-canhoto-selo">Boleto emitido</div>' +
                '<div style="font-size:.82rem">' + esc(b.pagador_nome) + '</div>' +
              '</div>' +
            '</div>' +
            '<div class="pe-canhoto-corpo">' +
              faixaBarras(b.linha_crua || b.linha_digitavel) +
              (b.divergencia
                ? '<div class="pe-nota pe-nota-erro" style="margin-top:0">⚠ ' + esc(b.divergencia) + '</div>'
                : '') +
              (b.linha_digitavel
                ? '<div class="pe-linha" id="peLinha">' + esc(b.linha_digitavel) + '</div>'
                : '<div class="pe-nota pe-nota-warn">A API não retornou a linha digitável. Abra o PDF para obtê-la.</div>') +
              '<div class="pe-botoes">' +
                (b.linha_digitavel ? '<button type="button" class="pe-btn pe-btn-forte" id="peCopiarLinha">Copiar linha digitável</button>' : '') +
                (b.url_pdf ? '<a class="pe-btn" href="' + esc(b.url_pdf) + '" target="_blank" rel="noopener">Abrir PDF</a>' : '') +
                (b.pix_copia_cola ? '<button type="button" class="pe-btn" id="peCopiarPix" data-pix="' + esc(b.pix_copia_cola) + '">Copiar código PIX</button>' : '') +
                '<button type="button" class="pe-btn" id="peNovoBoleto">Emitir outro</button>' +
              '</div>' +
              '<div class="pe-nota pe-nota-info">Guardado nesta O.S. Use "Conferir" depois que o cliente pagar para dar baixa.</div>' +
            '</div>' +
          '</div>';

        $('#peBoletoForm').hide().after('<div id="peCanhoto">' + html + '</div>');
    }

    $(document).on('click', '#peNovoBoleto', function () {
        $('#peCanhoto').remove();
        $('#peBoletoForm').show();
    });

    /* Sem HTTPS o clipboard moderno não existe, e o Atlas roda em rede
       local — por isso o caminho alternativo com textarea. */
    function copiar(texto, botao, rotulo) {
        var ok = function () {
            $(botao).text('Copiado');
            setTimeout(function () { $(botao).text(rotulo); }, 1600);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(texto).then(ok);
            return;
        }

        var t = $('<textarea>').val(texto).css({ position: 'fixed', left: '-9999px' }).appendTo('body');
        t[0].select();
        try { document.execCommand('copy'); ok(); } catch (e) {}
        t.remove();
    }

    $(document).on('click', '#peCopiarLinha', function () {
        copiar($('#peLinha').text().replace(/\s/g, ''), this, 'Copiar linha digitável');
    });

    $(document).on('click', '#peCopiarPix', function () {
        copiar($(this).attr('data-pix'), this, 'Copiar código PIX');
    });

    /* ================================================================
       PONTE COM A TELA
       ================================================================ */

    /* O pagamento já foi gravado no servidor. Aqui só refletimos, usando as
       funções que a página já possui — nada é recalculado por conta própria. */
    function refletirPagamento(r) {
        if (typeof pagamentos === 'undefined') return;

        pagamentos.push({
            id: r.pagamento_id,
            forma_de_pagamento: r.forma_de_pagamento,
            total_pagamento: r.total_pagamento,
            data_pagamento: r.data_pagamento,
            funcionario: r.funcionario,
            observacao: r.observacao || ''
        });

        if (typeof atualizarTabelaPagamentos === 'function') atualizarTabelaPagamentos();
        if (typeof atualizarSaldo === 'function') atualizarSaldo();
        if (typeof refreshOsHeaderAndStats === 'function') refreshOsHeaderAndStats();
    }

    /* Fechar durante a espera exige interromper: sair deixando a venda viva
       no terminal é o caminho mais curto para cobrança sem lançamento. */
    $(document).on('hide.bs.modal', '#pagamentoModal', function (e) {
        if (PE.saleId && !PE.cancelado) {
            e.preventDefault();
            $('#pePosInterromper').trigger('click');
        }
    });

    $(function () {
        $('#pagamentoModal').on('shown.bs.modal', montar);
        if ($('#pagamentoModal').hasClass('show')) montar();
    });
})();
</script>
