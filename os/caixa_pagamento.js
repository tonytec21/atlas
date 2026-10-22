/* Pagamento na O.S. exige o caixa do dia aberto (ver caixa_pagamento_lib.php).
   Ao abrir a janela de pagamentos, consulta o caixa do usuário; se não estiver
   aberto, mostra o aviso na janela e impede o lançamento já na tela.
   O servidor (salvar_pagamento.php) faz a mesma verificação. */
(function ($) {
  if (!$) return;
  var situacao = null;

  function esc(t) { return String(t || '').replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  function avisoHtml(r) {
    var fechado = r.motivo === 'caixa_fechado';
    var titulo = fechado ? 'Seu caixa de hoje está fechado.' : 'Seu caixa de hoje ainda não foi aberto.';
    var texto = fechado ? 'Para lançar pagamentos, peça ao Oficial para desfazer o fechamento.' : 'Abra o caixa no Fluxo de Caixa antes de lançar pagamentos.';
    return '<div class="alert alert-warning os-aviso-caixa" role="alert" style="display:flex;gap:10px;align-items:flex-start;border-left:4px solid #d97706;margin-bottom:14px;">'
      + '<i class="fa fa-lock" aria-hidden="true" style="margin-top:3px"></i><div><b>' + titulo + '</b> ' + texto
      + (r.motivo === 'sem_caixa' ? ' <a href="../caixa/index.php" class="btn btn-sm btn-warning" style="margin-left:6px">Abrir o caixa</a>' : '')
      + '</div></div>';
  }

  function aplicar(r) {
    situacao = r || { ok: true };
    var $m = $('#pagamentoModal');
    $m.find('.os-aviso-caixa').remove();
    if (!situacao.ok) {
      $m.find('.modal-body').first().prepend(avisoHtml(situacao));
    }
  }

  window.osVerificarCaixaPagamento = function () {
    return $.getJSON('caixa_situacao.php').done(aplicar).fail(function () { aplicar({ ok: true }); });
  };

  window.osAvisarCaixaBloqueado = function (r) {
    aplicar(r);
    if (window.Swal) {
      Swal.fire({
        icon: 'warning',
        title: r.motivo === 'caixa_fechado' ? 'Caixa fechado' : 'Abra o caixa primeiro',
        html: esc(r.mensagem) + (r.motivo === 'sem_caixa' ? '<br><br><a href="../caixa/index.php" class="btn btn-warning">Ir para o Fluxo de Caixa</a>' : ''),
        confirmButtonText: 'OK'
      });
    } else {
      alert(r.mensagem);
    }
  };

  /* Mensagem de erro do salvar_pagamento.php (inclui o bloqueio do caixa). */
  window.osErroPagamento = function (resp, padrao) {
    if (resp && resp.caixa_bloqueio) { window.osAvisarCaixaBloqueado({ ok: false, motivo: resp.caixa_bloqueio, mensagem: resp.error }); return true; }
    return false;
  };

  $(document).on('show.bs.modal', '#pagamentoModal', function (e) {
    if (e.target && e.target.id === 'pagamentoModal') window.osVerificarCaixaPagamento();
  });

  $(function () {
    // Antes de lançar: se o caixa não está aberto, avisa e não chama o servidor
    if (typeof window.adicionarPagamento === 'function') {
      var original = window.adicionarPagamento;
      window.adicionarPagamento = function () {
        if (situacao && situacao.ok === false) { window.osAvisarCaixaBloqueado(situacao); return; }
        return original.apply(this, arguments);
      };
    }
  });
})(window.jQuery);
