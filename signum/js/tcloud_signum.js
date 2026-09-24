/*!
 * tcloud_signum.js — Atlas Signum × TCloud Assinador 2.x (modo link)
 * Cria o pedido, abre tcloudsign:// neste computador e acompanha até o PDF voltar.
 * Sem pareamento. Depende apenas do SweetAlert2 (já usado no Atlas). Sem CDN.
 */
(function (w) {
  'use strict';

  var CFG = { endpoint: 'tcloud_api.php', endpointIniciar: '', csrf: '', intervalo: 1500, urlInstalacao: '', esperaAviso: 8000, esperaInstalar: 6000,
              cmdExecutar: '', cmdMac: '', cmdLinux: '', aoMudarApp: null };

  /* ------------------------------------------------ o app está neste computador? */
  // O navegador não consegue ver programas instalados. O sinal confiável é o próprio fluxo:
  // o app pegou o pedido ('ok') ou o link não abriu nada a tempo ('nao'). Fica guardado neste navegador.
  var CHAVE_APP = 'tcSignumApp';
  function appStatus() { try { return localStorage.getItem(CHAVE_APP) || ''; } catch (e) { return ''; } }
  /** {status: 'ok'|'nao'|'', em: Date|null} — 'em' é a última vez que o app respondeu neste navegador. */
  function appInfo() {
    var em = null;
    try { var t = parseInt(localStorage.getItem(CHAVE_APP + 'Em') || '', 10); if (t > 0) em = new Date(t); } catch (e) {}
    return { status: appStatus(), em: em };
  }
  function marcaApp(v) {
    var mudou = appStatus() !== v;
    try { localStorage.setItem(CHAVE_APP, v); if (v === 'ok') localStorage.setItem(CHAVE_APP + 'Em', String(Date.now())); } catch (e) {}
    if (typeof CFG.aoMudarApp === 'function') { try { CFG.aoMudarApp(v, mudou); } catch (e) {} }
  }

  /** 'windows' | 'mac' | 'linux' | 'outro' (celular, tablet, ChromeOS…: sem instalador por comando). */
  function sistemaOperacional() {
    var ua = (navigator.userAgent || '').toLowerCase();
    var p = ((navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || '').toLowerCase();
    var toque = (navigator.maxTouchPoints || 0) > 1;
    // estes também se dizem "Linux" ou "Mac", mas não rodam o instalador
    if (/android|iphone|ipad|ipod|cros|chrome os/.test(ua) || /android|chrome os|ios/.test(p)) return 'outro';
    if (p.indexOf('win') >= 0 || ua.indexOf('windows') >= 0) return 'windows';
    if (p.indexOf('mac') >= 0 || ua.indexOf('mac os') >= 0) return toque ? 'outro' : 'mac';   // iPad se apresenta como Mac
    if (p.indexOf('linux') >= 0 || ua.indexOf('linux') >= 0 || ua.indexOf('x11') >= 0) return 'linux';
    return 'outro';
  }
  var NOME_SO = { windows: 'Windows', mac: 'Mac', linux: 'Linux' };

  /** Copia texto; funciona também em página HTTP (sem a API de área de transferência). */
  function copiar(texto) {
    var ok = false;
    try {                                         // síncrono, ainda dentro do clique do usuário
      var ta = document.createElement('textarea');
      ta.value = texto; ta.setAttribute('readonly', '');
      ta.style.cssText = 'position:fixed;top:-1000px;left:-1000px;opacity:0';
      document.body.appendChild(ta); ta.select(); ta.setSelectionRange(0, texto.length);
      ok = document.execCommand('copy');
      document.body.removeChild(ta);
    } catch (e) { ok = false; }
    if (!ok && navigator.clipboard && w.isSecureContext) {
      navigator.clipboard.writeText(texto).catch(function () {});
      ok = true;
    }
    return ok;
  }

  function tecla(t) {
    return '<kbd style="display:inline-block;min-width:26px;padding:3px 8px;border:1px solid #cbd5e1;border-bottom-width:3px;border-radius:7px;background:#fff;color:#0f172a;font:700 .82rem/1.2 system-ui,sans-serif;text-align:center">' + t + '</kbd>';
  }
  function passo(n, html) {
    return '<li style="display:flex;gap:12px;align-items:center;padding:9px 0;border-bottom:1px solid #eef2f7">' +
      '<span style="width:26px;height:26px;border-radius:50%;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.8rem;flex:0 0 auto">' + n + '</span>' +
      '<span style="flex:1">' + html + '</span></li>';
  }

  /**
   * Assistente de instalação. Não há como um site abrir o PowerShell/Terminal (os navegadores não deixam),
   * então o botão copia o comando pronto e mostra as três teclas para colar e rodar.
   */
  /** Instruções de instalação para um sistema: {so, nome, cmd, passos (HTML de <li>)}. cmd vazio = sem instalador. */
  function instrucoes(so) {
    so = so || sistemaOperacional();
    var cmd = { windows: CFG.cmdExecutar, mac: CFG.cmdMac, linux: CFG.cmdLinux }[so] || '';
    var passos = {
      windows: passo(1, 'Aperte ' + tecla('⊞ Win') + ' + ' + tecla('R') + ' — abre a caixa <b>Executar</b>.') +
               passo(2, 'Aperte ' + tecla('Ctrl') + ' + ' + tecla('V') + ' para colar o comando.') +
               passo(3, 'Aperte ' + tecla('Enter') + '. O PowerShell abre e instala sozinho.'),
      mac:     passo(1, 'Aperte ' + tecla('⌘ Cmd') + ' + ' + tecla('Espaço') + ', digite <b>Terminal</b> e aperte ' + tecla('Enter') + '.') +
               passo(2, 'Aperte ' + tecla('⌘ Cmd') + ' + ' + tecla('V') + ' para colar o comando.') +
               passo(3, 'Aperte ' + tecla('Enter') + ' e, se pedir, digite a senha do Mac.'),
      linux:   passo(1, 'Aperte ' + tecla('Ctrl') + ' + ' + tecla('Alt') + ' + ' + tecla('T') + ' para abrir o <b>Terminal</b> (ou procure "Terminal" no menu).') +
               passo(2, 'Aperte ' + tecla('Ctrl') + ' + ' + tecla('Shift') + ' + ' + tecla('V') + ' para colar o comando.') +
               passo(3, 'Aperte ' + tecla('Enter') + ' e, se pedir, digite a sua senha.')
    }[so] || '';
    return { so: so, nome: NOME_SO[so] || '', cmd: cmd, passos: passos };
  }

  /** Bloco de instalação embutido (passos + comando + copiar), para janelas que já estão abertas. */
  function blocoInstalacao(pref) {
    var ins = instrucoes();
    if (!ins.cmd) {
      return '<div style="font-size:.86rem;color:#475569">O TCloud Assinador funciona em computadores com <b>Windows</b>, <b>Mac</b> ou <b>Linux</b>. Use um desses computadores para assinar com o token.</div>';
    }
    return '<ol style="list-style:none;padding:0;margin:0 0 10px;font-size:.88rem;color:#334155">' + ins.passos + '</ol>' +
      '<textarea id="' + pref + 'Cmd" readonly rows="3" style="box-sizing:border-box;width:100%;font:12px/1.4 Consolas,monospace;border:1px solid #e2e8f0;border-radius:8px;padding:8px;color:#0f172a;background:#fff;resize:none">' + esc(ins.cmd) + '</textarea>' +
      '<div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:10px">' +
        '<button type="button" id="' + pref + 'Copiar" style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border:0;border-radius:10px;background:#d97706;color:#fff;font-weight:700;font-size:.88rem;cursor:pointer"><i class="fa fa-clipboard"></i> Copiar comando</button>' +
        '<button type="button" id="' + pref + 'Denovo" style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border:0;border-radius:10px;background:#16a34a;color:#fff;font-weight:700;font-size:.88rem;cursor:pointer"><i class="fa fa-check"></i> Já instalei — tentar de novo</button>' +
      '</div>';
  }
  function ligaCopiar(pref) {
    var b = document.getElementById(pref + 'Copiar'), t = document.getElementById(pref + 'Cmd');
    if (!b || !t) return;
    b.addEventListener('click', function () {
      var ok = copiar(t.value);
      b.innerHTML = ok ? '<i class="fa fa-check"></i> Copiado — agora cole' : '<i class="fa fa-clipboard"></i> Copiar comando';
      if (!ok) { t.focus(); t.select(); }
      setTimeout(function () { b.innerHTML = '<i class="fa fa-clipboard"></i> Copiar comando'; }, 2500);
    });
  }

  /**
   * Passo a passo de instalação (comando copiado + teclas).
   * opts.acao: 'testar' (padrão: "Já instalei — testar" roda o teste) ou 'assinar' ("Já está instalado — assinar").
   * opts.aviso: texto curto no topo. Devolve 'seguir' quando o usuário confirma que vai assinar; senão null.
   */
  async function instalar(opts) {
    opts = opts || {};
    var acao = opts.acao === 'assinar' ? 'assinar' : 'testar';
    var ins = instrucoes();
    var so = ins.so, cmd = ins.cmd;
    if (!cmd) {
      await Swal.fire({ icon: 'info', title: 'Instalar o TCloud Assinador',
        html: 'O TCloud Assinador funciona em computadores com <b>Windows</b>, <b>Mac</b> ou <b>Linux</b>. ' +
              'Para assinar com o token, use um desses computadores' +
              (CFG.urlInstalacao ? ' (instaladores em <a href="' + esc(CFG.urlInstalacao) + '" target="_blank" rel="noopener"><b>' + esc(CFG.urlInstalacao) + '</b></a>).' : '.'),
        confirmButtonText: 'Ok' });
      return null;
    }
    var copiado = copiar(cmd);
    var html =
      '<div style="text-align:left">' +
        (opts.aviso ? '<div style="font-size:.9rem;color:#475569;margin-bottom:10px">' + opts.aviso + '</div>' : '') +
        '<div style="padding:10px 12px;border-radius:10px;font-size:.86rem;margin-bottom:6px;' +
          (copiado ? 'background:#dcfce7;color:#166534"><i class="fa fa-check-circle"></i> Comando de instalação <b>copiado</b>.'
                   : 'background:#fef3c7;color:#92400e"><i class="fa fa-exclamation-triangle"></i> Clique em <b>Copiar comando</b> e siga os passos.') +
        '</div>' +
        '<ol style="list-style:none;padding:0;margin:0 0 12px;font-size:.9rem;color:#334155">' + ins.passos + '</ol>' +
        '<textarea id="tcCmdInst" readonly rows="4" style="box-sizing:border-box;width:100%;font:12px/1.4 Consolas,monospace;border:1px solid #e2e8f0;border-radius:8px;padding:8px;color:#0f172a;background:#f8fafc;resize:none">' + esc(cmd) + '</textarea>' +
        '<div style="font-size:.8rem;color:#64748b;margin-top:8px">' +
          (acao === 'assinar' ? 'Quando a instalação terminar, clique em <b>Já está instalado — assinar</b>.' : 'Quando a instalação terminar, clique em <b>Já instalei — testar</b>.') +
          ' O computador precisa também do driver do token.</div>' +
      '</div>';
    var qi = await Swal.fire({
      title: 'Instalar o TCloud Assinador' + (NOME_SO[so] ? ' no ' + NOME_SO[so] : ''), html: html, width: 560,
      showDenyButton: true, denyButtonText: '<i class="fa fa-clipboard"></i> ' + (copiado ? 'Copiar de novo' : 'Copiar comando'), denyButtonColor: '#475569',
      confirmButtonText: acao === 'assinar' ? '<i class="fa fa-pencil"></i> Já está instalado — assinar' : '<i class="fa fa-check"></i> Já instalei — testar',
      confirmButtonColor: '#16a34a',
      showCancelButton: true, cancelButtonText: 'Fechar',
      preDeny: function () { copiar(cmd); var t = document.getElementById('tcCmdInst'); if (t) { t.focus(); t.select(); } return false; }
    });
    if (!qi.isConfirmed) return null;
    if (acao === 'assinar') return 'seguir';
    await verificar(true);   // "Já instalei — testar"
    return null;
  }

  function esc(s) {
    return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  async function post(acao, dados) {
    dados = dados || {};
    dados.acao = acao;
    dados.csrf = CFG.csrf;
    // o pedido pode ser criado por um endpoint do módulo (ofícios, notas, O.S.); o resto vai ao Signum
    var url = (acao === 'iniciar' && CFG.endpointIniciar) ? CFG.endpointIniciar : CFG.endpoint;
    var r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(dados).toString(),
      credentials: 'same-origin'
    });
    var t = await r.text();
    try { return JSON.parse(t); }
    catch (e) { throw new Error('Resposta inválida do servidor: ' + t.slice(0, 180)); }
  }

  function situacao() { return post('situacao'); }

  /** Abre o link do programa sem sair da página (o navegador entrega ao sistema operacional). */
  function abrirLink(link) {
    // iframe escondido: não tira o usuário da página em nenhum navegador (recomendação do guia 2.4.0);
    // o botão "Abrir o TCloud Assinador" da janela é a alternativa com clique de verdade.
    try {
      var q = document.createElement('iframe');
      q.style.display = 'none';
      q.src = link;
      document.body.appendChild(q);
      setTimeout(function () { if (q.parentNode) q.parentNode.removeChild(q); }, 5000);
    } catch (e) { /* segue pelo botão */ }
  }

  /* ------------------------------------------------ só abre o link com o app instalado */
  /**
   * Antes de abrir qualquer tcloudsign://: se o app ainda não foi detectado neste computador, mostra
   * direto o passo a passo de instalação (o "Já está instalado" fica no fim dele).
   * Sem o app, abrir o link faria o Windows mostrar "Obter um aplicativo… Microsoft Store" — a instalação
   * é só pelo comando. Devolve true para seguir (abrir o link) ou false (foi instalar ou cancelou).
   */
  async function garantirInstalado(acao) {
    if (appStatus() === 'ok') return true;
    var aviso = appStatus() === 'nao'
      ? '<b>O TCloud Assinador não foi detectado neste computador.</b> Instale (ou atualize) pelo comando abaixo.'
      : '<b>Para ' + (acao === 'testar' ? 'testar' : 'assinar com o seu token') + ', o TCloud Assinador precisa estar instalado neste computador.</b> Se ainda não está, instale pelo comando abaixo.';
    var r = await instalar({ acao: acao, aviso: aviso });
    return r === 'seguir';
  }

  /* ------------------------------------------------ testar o assinador (teste real) */
  /**
   * Abre um pedido de TESTE: o TCloud Assinador deste computador abre a janela normal com um pequeno JSON,
   * o usuário escolhe o certificado e digita o PIN, e o sistema confere a assinatura — sem gravar nada.
   * Devolve true (o app abriu), false (não abriu) ou null (cancelado antes de abrir).
   */
  async function verificar(jaConfirmado) {
    if (!jaConfirmado && !(await garantirInstalado('testar'))) return null;
    var r = await post('sondar');
    if (!r.success) { await Swal.fire('Teste do assinador', r.message || 'Falha ao iniciar o teste.', 'error'); return null; }
    var ativo = true, cancelado = false, abriu = false, fim = null, reiniciar = false;
    var inicio = Date.now(), limiteAbrir = inicio + 115000, mostrouInst = false;
    var win = sistemaOperacional() === 'windows';
    var passoHtml = function (id, n, txt) {
      return '<li id="' + id + '" style="display:flex;gap:10px;align-items:center;padding:7px 0;color:#94a3b8">' +
        '<span class="tv-n" style="width:24px;height:24px;border-radius:50%;background:#e2e8f0;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;flex:0 0 auto">' + n + '</span>' +
        '<span style="font-size:.9rem">' + txt + '</span></li>';
    };
    Swal.fire({
      title: 'Testando o TCloud Assinador',
      html: '<div style="text-align:left">' +
              '<ul style="list-style:none;padding:0;margin:0 0 10px">' +
                passoHtml('tv1', 1, 'Abrir o TCloud Assinador neste computador') +
                passoHtml('tv2', 2, 'Escolher o certificado e digitar o PIN') +
                passoHtml('tv3', 3, 'Conferir a assinatura de teste') +
              '</ul>' +
              '<div id="tvMsg" style="font-size:.86rem;color:#475569;line-height:1.5">Se o navegador perguntar, clique em <b>Abrir TCloud Assinador</b>. Se o assinador perguntar se confia neste servidor, responda <b>Sim</b>.</div>' +
              (win ? '<div id="tvStore" style="margin-top:10px;padding:10px 12px;border-radius:10px;background:#fef3c7;color:#92400e;font-size:.83rem">' +
                     '<i class="fa fa-info-circle"></i> Se o Windows disser que <b>não há aplicativo para abrir este link</b>, feche essa janela do Windows (<b>não use a loja</b>): as instruções de instalação aparecem aqui.</div>' : '') +
              '<div style="margin-top:10px;font-size:.8rem;color:#64748b"><i class="fa fa-lock"></i> É só um teste: o documento assinado é descartado, nada é gravado.</div>' +
              '<div id="tvInst" style="display:none;margin-top:12px;padding:12px;border-radius:12px;background:#fffbeb;border:1px solid #fcd34d">' +
                '<div style="font-weight:800;color:#78350f;margin-bottom:8px"><i class="fa fa-download"></i> Não abriu? Instale o TCloud Assinador neste computador:</div>' +
                blocoInstalacao('tv') +
              '</div>' +
            '</div>',
      showConfirmButton: false,
      showCancelButton: true, cancelButtonText: 'Fechar', allowOutsideClick: false, allowEscapeKey: false, width: 540
    }).then(function () {
      if (!ativo) return;
      ativo = false; cancelado = true;
      post('sonda_cancelar', { id: r.id }).catch(function () {});
    });
    ligaCopiar('tv');
    var dn = document.getElementById('tvDenovo');
    if (dn) dn.addEventListener('click', function () { reiniciar = true; ativo = false; post('sonda_cancelar', { id: r.id }).catch(function () {}); Swal.close(); });
    // sem resposta em alguns segundos: troca para o passo a passo (o teste continua esperando por baixo)
    var mostraInst = function () {
      if (mostrouInst || abriu) return; mostrouInst = true; marcaApp('nao');
      var t = Swal.getTitle(); if (t) t.textContent = 'O TCloud Assinador não abriu';
      ['tvStore', 'tvMsg'].forEach(function (x) { var e = document.getElementById(x); if (e) e.style.display = 'none'; });
      var bx = document.getElementById('tvInst'); if (bx) bx.style.display = 'block';
    };
    var etapa = function (n) {
      for (var k = 1; k <= 3; k++) {
        var li = document.getElementById('tv' + k); if (!li) continue;
        var b = li.querySelector('.tv-n');
        if (k < n) { li.style.color = '#0f172a'; b.style.background = '#22c55e'; b.style.color = '#fff'; b.innerHTML = '&#10003;'; }
        else if (k === n) { li.style.color = '#0f172a'; b.style.background = '#2563eb'; b.style.color = '#fff'; b.innerHTML = String(k); }
      }
    };
    etapa(1);
    abrirLink(r.link);

    while (ativo) {
      await sleep(1200);
      if (!ativo) break;
      if (!abriu && Date.now() - inicio > CFG.esperaInstalar) mostraInst();
      if (!abriu && Date.now() > limiteAbrir) { fim = { estado: 'nao_abriu' }; break; }
      var st;
      try { st = await post('sonda_status', { id: r.id }); } catch (e) { continue; }
      if (!ativo) break;
      if (!st.success) { fim = { estado: 'erro', mensagem: st.message }; break; }
      if (st.outro_ip && !st.detectado) { fim = { estado: 'outro_ip' }; break; }
      if (st.detectado && !abriu) {
        abriu = true; marcaApp('ok'); etapa(2);
        var m = document.getElementById('tvMsg');
        if (m) m.innerHTML = '<b style="color:#166534"><i class="fa fa-check-circle"></i> O TCloud Assinador está instalado e abriu.</b><br>Na janela dele, escolha o certificado e digite o PIN para testar a assinatura (ou clique em Recusar lá, se só queria confirmar a instalação).';
        ['tvStore', 'tvLink', 'tvInst'].forEach(function (x) { var e = document.getElementById(x); if (e) e.style.display = 'none'; });
        var tt = Swal.getTitle(); if (tt) tt.textContent = 'Testando o TCloud Assinador';
        var mm = document.getElementById('tvMsg'); if (mm) mm.style.display = 'block';
      }
      if (st.estado === 'assinando') etapa(3);
      if (st.finalizado) { fim = st; break; }
    }
    if (reiniciar) return verificar(true);
    if (cancelado) return abriu ? true : null;
    ativo = false;

    if (fim && fim.estado === 'concluido') {
      await Swal.fire({ icon: 'success', title: 'Assinatura testada com sucesso',
        html: 'O TCloud Assinador está instalado e a assinatura com o seu certificado funcionou' +
              (fim.titular ? ' — <b>' + esc(fim.titular) + '</b>' : '') + '.<br><small style="color:#64748b">Era só um teste: nada foi gravado.</small>',
        confirmButtonText: 'Ok' });
      return true;
    }
    if (abriu) {
      var rec = fim && (fim.estado === 'recusado' || fim.estado === 'cancelado');
      await Swal.fire({ icon: rec ? 'info' : 'warning',
        title: rec ? 'TCloud Assinador instalado' : 'Instalado, mas o teste de assinatura falhou',
        text: rec ? 'Ele está instalado e abriu neste computador. O teste de assinatura foi cancelado.'
                  : ((fim && fim.mensagem) || 'A assinatura de teste não foi concluída.') + ' Confira se o token está conectado e o driver instalado.',
        confirmButtonText: 'Ok' });
      return true;
    }
    marcaApp('nao');
    post('sonda_cancelar', { id: r.id }).catch(function () {});
    if (fim && fim.estado === 'outro_ip') {
      await Swal.fire({ icon: 'warning', title: 'TCloud Assinador em outro caminho de rede',
        text: 'Ele respondeu, mas por outro caminho de rede. Abra o sistema pelo mesmo endereço que este computador usa e tente de novo.' });
      return false;
    }
    await instalar({ acao: 'testar', aviso: '<b>O TCloud Assinador não respondeu neste computador.</b> Instale (ou atualize) pelo comando abaixo.' });
    return false;
  }

  /* ----------------------------------------------------- janela de acompanhamento */
  var ETAPAS = [
    { e: 'aguardando_estacao', t: 'Abrindo o TCloud Assinador',   d: 'Neste computador. Se o navegador perguntar, permita.' },
    { e: 'na_estacao',         t: 'Escolha o certificado',        d: 'Na janela do TCloud Assinador; depois digite o PIN do token.' },
    { e: 'assinando',          t: 'Finalizando',                  d: 'Gravando a assinatura no documento.' }
  ];
  var TITULO_FIM = {
    recusado: 'Assinatura recusada',
    expirado: 'Tempo esgotado',
    erro: 'Não foi possível assinar',
    cancelado: 'Assinatura cancelada'
  };

  function modalHtml(link, urlInst) {
    var li = ETAPAS.map(function (x, i) {
      return '<li data-e="' + x.e + '" style="display:flex;gap:10px;align-items:flex-start;padding:7px 0;color:#94a3b8">' +
        '<span class="tc-n" style="width:24px;height:24px;border-radius:50%;background:#e2e8f0;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;flex:0 0 auto">' + (i + 1) + '</span>' +
        '<span><b style="display:block;font-size:.92rem">' + x.t + '</b><small style="font-size:.78rem">' + x.d + '</small></span></li>';
    }).join('');
    return '<div style="text-align:left">' +
      '<ul id="tcEtapas" style="list-style:none;padding:0;margin:0 0 10px">' + li + '</ul>' +
      '<div id="tcMsg" style="font-size:.85rem;color:#475569;min-height:20px"></div>' +
      '<div style="text-align:center;margin-top:12px">' +
        '<a id="tcAbrir" href="' + esc(link) + '" style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:10px;background:#eef2ff;color:#1e40af;font-weight:700;font-size:.88rem;text-decoration:none">' +
        '<i class="fa fa-external-link"></i> Abrir o TCloud Assinador</a>' +
      '</div>' +
      '<div id="tcAviso" style="display:none;margin-top:12px;padding:12px;border-radius:12px;background:#fffbeb;border:1px solid #fcd34d">' +
        '<div style="font-weight:800;color:#78350f;margin-bottom:6px"><i class="fa fa-download"></i> Não abriu? Instale o TCloud Assinador neste computador:</div>' +
        '<div style="font-size:.8rem;color:#92400e;margin-bottom:8px">Se o Windows disser que não há aplicativo para abrir o link, feche essa janela (não use a loja) e siga os passos:</div>' +
        blocoInstalacao('tca') +
      '</div></div>';
  }

  function marcaEtapa(estado) {
    var idx = -1;
    ETAPAS.forEach(function (x, i) { if (x.e === estado) idx = i; });
    var lis = document.querySelectorAll('#tcEtapas li');
    for (var i = 0; i < lis.length; i++) {
      var n = lis[i].querySelector('.tc-n');
      if (i < idx) { lis[i].style.color = '#0f172a'; n.style.background = '#22c55e'; n.style.color = '#fff'; n.innerHTML = '&#10003;'; }
      else if (i === idx) { lis[i].style.color = '#0f172a'; n.style.background = '#2563eb'; n.style.color = '#fff'; n.innerHTML = String(i + 1); }
      else { lis[i].style.color = '#94a3b8'; n.style.background = '#e2e8f0'; n.style.color = '#64748b'; n.innerHTML = String(i + 1); }
    }
    var ab = document.getElementById('tcAbrir'), avi = document.getElementById('tcAviso');
    var instNaTela = avi && avi.style.display === 'block';
    if (ab) ab.style.display = (idx <= 0 && !instNaTela) ? 'inline-flex' : 'none';
    if (idx > 0) { var av = document.getElementById('tcAviso'); if (av) av.style.display = 'none'; }
  }
  function msg(t) { var e = document.getElementById('tcMsg'); if (e) e.textContent = t || ''; }

  /* --------------------------------------------------------------------- assinar */
  /**
   * Cria o pedido, abre o TCloud Assinador neste computador e acompanha até o fim.
   * @param {Object} dados token (upload), page, xn, yn, wn
   * @returns {Promise<Object|null>} resposta com .doc quando assinado; null se cancelado/recusado/erro já mostrado.
   */
  async function assinar(dados, jaConfirmado) {
    if (!jaConfirmado && !(await garantirInstalado('assinar'))) return null;
    Swal.fire({ title: 'Preparando a assinatura…', didOpen: function () { Swal.showLoading(); }, allowOutsideClick: false });
    var r = await post('iniciar', Object.assign({}, dados));
    if (!r.success) throw new Error(r.message || 'Falha ao criar o pedido de assinatura.');

    var id = r.id, ativo = true, falhas = 0, inicio = Date.now(), abriu = false, reiniciar = false;
    var urlInst = r.url_instalacao || CFG.urlInstalacao;
    Swal.fire({
      title: 'Assine no TCloud Assinador',
      html: modalHtml(r.link, urlInst),
      showConfirmButton: false, showCancelButton: true, cancelButtonText: '<i class="fa fa-times"></i> Cancelar',
      allowOutsideClick: false, allowEscapeKey: false, width: 540,
      didOpen: function () { Swal.hideLoading(); }   // tira o "carregando" da janela anterior
    }).then(function () {
      if (ativo) { ativo = false; post('cancelar', { id: id }).catch(function () {}); }
    });
    Swal.hideLoading();
    marcaEtapa(r.estado || 'aguardando_estacao');
    msg('Abrindo o TCloud Assinador neste computador…');
    ligaCopiar('tca');
    var dn = document.getElementById('tcaDenovo');
    if (dn) dn.addEventListener('click', function () { reiniciar = true; ativo = false; post('cancelar', { id: id }).catch(function () {}); Swal.close(); });
    abrirLink(r.link);

    while (ativo) {
      await sleep(CFG.intervalo);
      if (!ativo) break;
      var s;
      try { s = await post('status', { id: id }); falhas = 0; }
      catch (e) {
        if (++falhas >= 5) { ativo = false; await Swal.fire('Não foi possível acompanhar', e.message, 'error'); return null; }
        continue;
      }
      if (!ativo) break;
      if (!s.success) { ativo = false; await Swal.fire('Não foi possível assinar', s.message || 'Falha.', 'error'); return null; }
      if (s.estado && s.estado !== 'aguardando_estacao') { abriu = true; marcaApp('ok'); }
      if (s.doc) { ativo = false; return s; }
      if (s.finalizado) {
        ativo = false;
        if (s.estado === 'expirado' && !abriu) {
          marcaApp('nao');
          var ir = await instalar({ acao: 'assinar', aviso: '<b>O TCloud Assinador não abriu neste computador.</b> Instale (ou atualize) pelo comando abaixo e assine de novo.' });
          return ir === 'seguir' ? assinar(dados, true) : null;
        }
        var info = (s.estado === 'recusado' || s.estado === 'cancelado');
        await Swal.fire({ icon: info ? 'info' : 'error', title: TITULO_FIM[s.estado] || 'Assinatura não concluída', text: s.mensagem || '' });
        return null;
      }
      marcaEtapa(s.estado);
      if (abriu) { var tt2 = Swal.getTitle(); if (tt2 && tt2.textContent !== 'Assine no TCloud Assinador') tt2.textContent = 'Assine no TCloud Assinador'; }
      if (s.estado === 'aguardando_estacao' && Date.now() - inicio > CFG.esperaInstalar) {
        var av = document.getElementById('tcAviso');
        if (av && av.style.display !== 'block') {
          av.style.display = 'block'; marcaApp('nao');
          var tt = Swal.getTitle(); if (tt) tt.textContent = 'O TCloud Assinador não abriu';
          var ab = document.getElementById('tcAbrir'); if (ab) ab.style.display = 'none';
        }
      }
      msg(s.estado === 'aguardando_estacao' ? 'Aguardando o TCloud Assinador abrir…' : (s.mensagem || ''));
    }
    if (reiniciar) return assinar(dados, true);   // "Já instalei — tentar de novo"
    return null;   // cancelado pelo usuário
  }

  w.TcSignum = {
    init: function (o) { for (var k in (o || {})) if (Object.prototype.hasOwnProperty.call(o, k)) CFG[k] = o[k]; },
    post: post,
    instalar: instalar,
    appStatus: appStatus,
    appInfo: appInfo,
    verificar: verificar,
    garantirInstalado: garantirInstalado,
    instrucoes: instrucoes,
    copiar: copiar,
    sistemaOperacional: sistemaOperacional,
    situacao: situacao,
    assinar: assinar,
    esc: esc
  };
})(window);
