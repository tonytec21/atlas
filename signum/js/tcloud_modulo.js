/*!
 * tcloud_modulo.js — liga as páginas de assinatura dos módulos do Atlas (ofícios, notas devolutivas,
 * O.S.…) ao TCloud Assinador centralizado no Atlas Signum. Usa o tcloud_signum.js (mesma instalação,
 * teste e acompanhamento do Signum).
 *
 * A página fornece (ids): tcAstat, tcState, tcHelp (cartão), tcTestar, tcInstalar, tcBanner, tcBanTit,
 * tcBanTxt, tcBanInstalar (faixa) e, opcionalmente, o chip serproChip/serproChipTxt.
 */
(function (w) {
  'use strict';
  var aoMudar = [];

  function el(id) { return document.getElementById(id); }
  function dataHora(d) { function z(n) { return (n < 10 ? '0' : '') + n; } return z(d.getDate()) + '/' + z(d.getMonth() + 1) + ' às ' + z(d.getHours()) + ':' + z(d.getMinutes()); }

  function pintar(estado, titulo, ajuda, chip) {
    var a = el('tcAstat');
    if (a) { a.className = 'tcm-astat ' + estado; if (el('tcState')) el('tcState').textContent = titulo; if (el('tcHelp')) el('tcHelp').textContent = ajuda; }
    var c = el('serproChip');
    if (c) {
      c.className = c.className.replace(/\b(on|off)\b/g, '').trim() + (estado ? ' ' + estado : '');
      if (el('serproChipTxt')) el('serproChipTxt').textContent = 'TCloud Assinador' + (chip ? ' · ' + chip : '');
    }
  }

  /** Mostra o que este navegador sabe do TCloud Assinador neste computador (nunca "pronto" sem saber). */
  function atualizar() {
    if (!w.TcSignum) { pintar('off', 'Script não carregou', 'Arquivo ../signum/js/tcloud_signum.js ausente.', 'erro'); return; }
    var i = TcSignum.appInfo(), st = i.status;
    if (st === 'ok') pintar('on', 'Detectado neste computador', (i.em ? 'Respondeu pela última vez em ' + dataHora(i.em) + '. ' : '') + 'Se ele foi desinstalado, use “Testar agora”.', 'detectado');
    else if (st === 'nao') pintar('off', 'Não detectado neste computador', 'Instale o TCloud Assinador (botão abaixo) ou use “Testar agora”.', 'não detectado');
    else pintar('', 'Ainda não verificado neste computador', 'Ao assinar, ele abre aqui. Use “Testar agora” para testar antes.', 'não verificado');

    var bi = el('tcInstalar'); if (bi) bi.style.display = (st === 'ok') ? 'none' : 'inline-flex';
    var bn = el('tcBanner');
    if (bn) {
      bn.style.display = (st === 'ok') ? 'none' : 'flex';
      if (el('tcBanTit')) el('tcBanTit').textContent = st === 'nao' ? 'O TCloud Assinador não foi detectado neste computador' : 'TCloud Assinador ainda não verificado neste computador';
      if (el('tcBanTxt')) el('tcBanTxt').textContent = st === 'nao'
        ? 'Ele não respondeu da última vez. Instale (ou atualize) para assinar com o seu token — leva menos de um minuto.'
        : 'Para assinar com o seu token, ele precisa estar instalado aqui. A instalação leva menos de um minuto.';
    }
    aoMudar.forEach(function (f) { try { f(st); } catch (e) {} });
  }

  function init(cfg) {
    if (!w.TcSignum) { atualizar(); return; }
    cfg = cfg || {};
    cfg.aoMudarApp = function () { atualizar(); };
    TcSignum.init(cfg);
    function liga(id, fn) { var b = el(id); if (b) b.addEventListener('click', fn); }
    var iniciar = function () {
      liga('tcTestar', async function () { await TcSignum.verificar(); atualizar(); });
      liga('tcInstalar', async function () { await TcSignum.instalar(); atualizar(); });
      liga('tcBanInstalar', async function () { await TcSignum.instalar(); atualizar(); });
      atualizar();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
  }

  /**
   * Assina pelo TCloud Assinador. dados = parâmetros do endpoint do módulo (numero/tipo/os_id, page, xn, yn, wn…).
   * Devolve o "doc" gravado pelo módulo ({url, codigo, titular…}) ou null (cancelado/recusado — já avisado).
   */
  async function assinar(dados) {
    try {
      var r = await TcSignum.assinar(dados);
      atualizar();
      return (r && r.doc) ? r.doc : null;
    } catch (e) {
      atualizar();
      if (w.Swal) await Swal.fire({ icon: 'error', title: 'Não foi possível assinar', text: e.message });
      else alert(e.message);
      return null;
    }
  }

  w.TcModulo = { init: init, atualizar: atualizar, assinar: assinar, aoMudar: function (f) { aoMudar.push(f); } };
})(window);
