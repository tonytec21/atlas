/* =====================================================================
   Atlas · Arquivamento Digital — diálogos.

   Todas as caixas do módulo passam por aqui, para que sempre saiam no
   SweetAlert2 e nunca no alert/confirm/prompt do navegador.

   Se o SweetAlert2 ainda não estiver carregado quando alguma tela pedir um
   diálogo, este arquivo o carrega sob demanda (primeiro do próprio servidor,
   depois do CDN) e só então mostra a caixa. As funções devolvem Promise, de
   modo que quem chama não precisa saber de nada disso.

   Único diálogo do sistema que continua sendo do navegador é o
   "beforeunload" ("Sair do site?"). A especificação não permite conteúdo
   próprio ali — é uma proteção para que uma página não consiga impedir o
   usuário de fechá-la.
   ===================================================================== */
(function (global) {
  'use strict';

  var COR = '#0E7C86';
  var COR_PERIGO = '#B0322F';

  var carregando = null;

  function injetar(src) {
    return new Promise(function (ok, falha) {
      var s = document.createElement('script');
      s.src = src;
      s.onload = ok;
      s.onerror = function () { falha(new Error('falha ao carregar ' + src)); };
      document.head.appendChild(s);
    });
  }

  /** Resolve quando window.Swal estiver disponível. */
  function pronto() {
    if (global.Swal) { return Promise.resolve(true); }
    if (carregando) { return carregando; }

    carregando = injetar('../script/sweetalert2.js')
      .catch(function () {
        return injetar('https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js');
      })
      .then(function () { return !!global.Swal; })
      .catch(function () { return false; });

    return carregando;
  }

  /**
   * Último recurso, caso o SweetAlert2 não carregue de jeito nenhum.
   * Uma faixa simples no topo da página — ainda assim sem diálogo nativo.
   */
  function faixa(titulo, texto) {
    var d = document.createElement('div');
    d.setAttribute('role', 'alert');
    d.style.cssText =
      'position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:99999;' +
      'max-width:min(560px,92vw);background:#0B1F26;color:#fff;padding:12px 16px;' +
      'border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.35);font:600 .9rem/1.4 Inter,sans-serif';
    d.textContent = titulo + (texto ? ' — ' + texto : '');
    document.body.appendChild(d);
    setTimeout(function () { d.remove(); }, 5000);
  }

  var ArqDlg = {
    pronto: pronto,

    /** Caixa de aviso. Resolve quando o usuário fecha. */
    aviso: function (icone, titulo, texto) {
      return pronto().then(function (temSwal) {
        if (!temSwal) { faixa(titulo, texto); return {}; }
        return Swal.fire({
          icon: icone, title: titulo, text: texto, confirmButtonColor: COR
        });
      });
    },

    /** Notificação curta no canto. */
    toast: function (icone, titulo) {
      return pronto().then(function (temSwal) {
        if (!temSwal) { faixa(titulo, ''); return {}; }
        return Swal.fire({
          toast: true, position: 'top-end', icon: icone, title: titulo,
          showConfirmButton: false, timer: 2200, timerProgressBar: true
        });
      });
    },

    /** Confirmação. Resolve com true/false. */
    confirmar: function (titulo, texto, rotuloBotao, perigo) {
      return pronto().then(function (temSwal) {
        if (!temSwal) { faixa(titulo, 'Recarregue a página para concluir esta ação.'); return false; }
        return Swal.fire({
          icon: perigo ? 'warning' : 'question',
          title: titulo,
          html: texto,
          showCancelButton: true,
          confirmButtonText: rotuloBotao || 'Confirmar',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: perigo ? COR_PERIGO : COR,
          reverseButtons: true
        }).then(function (r) { return !!r.isConfirmed; });
      });
    },

    /**
     * Pergunta com campo de texto. Resolve com a string digitada,
     * ou null se o usuário cancelar.
     */
    perguntar: function (titulo, opcoes) {
      opcoes = opcoes || {};
      return pronto().then(function (temSwal) {
        if (!temSwal) { faixa(titulo, 'Recarregue a página para concluir esta ação.'); return null; }
        return Swal.fire({
          title: titulo,
          html: opcoes.html || '',
          input: 'text',
          inputValue: opcoes.valor || '',
          inputPlaceholder: opcoes.exemplo || '',
          showCancelButton: true,
          confirmButtonText: opcoes.botao || 'Confirmar',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: opcoes.perigo ? COR_PERIGO : COR,
          reverseButtons: true
        }).then(function (r) {
          return r.isConfirmed ? String(r.value == null ? '' : r.value) : null;
        });
      });
    }
  };

  global.ArqDlg = ArqDlg;
})(window);
