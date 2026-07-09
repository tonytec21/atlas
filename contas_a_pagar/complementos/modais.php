<?php /* complementos/modais.php — modais: conta (cadastro/edição), configurações e anexos */ ?>

<!-- ============ MODAL CONTA (cadastro/edição) ============ -->
<div class="modal fade cap-modal" id="contaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <span style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center"><i class="fa fa-file-text-o"></i></span>
          <div><div style="font-weight:800;font-size:1.05rem" id="contaModalTitle">Nova conta</div><div style="font-size:.8rem;opacity:.9">Contas a pagar</div></div>
        </div>
        <button type="button" class="cap-close" data-bs-dismiss="modal"><i class="fa fa-times"></i></button>
      </div>
      <div class="modal-body">
        <form id="contaForm">
          <input type="hidden" name="id" id="c_id" value="">
          <div class="row">
            <div class="col-12 col-md-7 mb-3"><label class="form-label small text-muted mb-1">Título *</label>
              <div class="input-chip"><i class="fa fa-pencil"></i><input type="text" name="titulo" id="c_titulo" placeholder="Ex.: Energia elétrica" required></div></div>
            <div class="col-6 col-md-5 mb-3"><label class="form-label small text-muted mb-1">Valor (R$) *</label>
              <div class="input-chip"><i class="fa fa-money"></i><input type="text" name="valor" id="c_valor" inputmode="decimal" placeholder="0,00" required></div></div>
            <div class="col-6 col-md-4 mb-3"><label class="form-label small text-muted mb-1">Vencimento *</label>
              <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" name="data_vencimento" id="c_venc" required></div></div>
            <div class="col-6 col-md-4 mb-3"><label class="form-label small text-muted mb-1">Categoria</label>
              <div class="input-chip"><i class="fa fa-tag"></i><select name="categoria" id="c_categoria"><option value="">—</option>
                <?php foreach($CATS as $c): ?><option value="<?php echo htmlspecialchars($c, ENT_QUOTES); ?>"><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?>
              </select></div></div>
            <div class="col-6 col-md-4 mb-3"><label class="form-label small text-muted mb-1">Recorrência</label>
              <div class="input-chip"><i class="fa fa-repeat"></i><select name="recorrencia" id="c_recorrencia">
                <?php foreach($RECS as $r): ?><option value="<?php echo htmlspecialchars($r, ENT_QUOTES); ?>"><?php echo htmlspecialchars($r); ?></option><?php endforeach; ?>
              </select></div></div>
            <div class="col-12 col-md-8 mb-3"><label class="form-label small text-muted mb-1">Fornecedor</label>
              <div class="input-chip"><i class="fa fa-truck"></i><input type="text" name="fornecedor" id="c_fornecedor" placeholder="Ex.: Equatorial Energia"></div></div>
            <div class="col-12 mb-2"><label class="form-label small text-muted mb-1">Descrição / observações</label>
              <textarea class="form-control" name="descricao" id="c_descricao" rows="2" placeholder="Opcional"></textarea></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="contaSalvarBtn" onclick="capSalvarConta()"><i class="fa fa-save"></i> Salvar</button>
      </div>
    </div>
  </div>
</div>

<!-- ============ MODAL CONFIGURAÇÕES ============ -->
<div class="modal fade cap-modal" id="configModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <span style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center"><i class="fa fa-bell"></i></span>
          <div><div style="font-weight:800;font-size:1.05rem">Notificações & E-mail</div><div style="font-size:.8rem;opacity:.9">Alertas de contas a vencer/vencidas</div></div>
        </div>
        <button type="button" class="cap-close" data-bs-dismiss="modal"><i class="fa fa-times"></i></button>
      </div>
      <div class="modal-body">
        <form id="configForm">
          <div class="row">
            <div class="col-12 col-md-8 mb-3"><label class="form-label small text-muted mb-1">E-mail para receber alertas</label>
              <div class="input-chip"><i class="fa fa-envelope"></i><input type="email" name="email_notificacao" id="cfg_email" placeholder="voce@exemplo.com" value="<?php echo htmlspecialchars($cfg['email_notificacao'] ?? '', ENT_QUOTES); ?>"></div></div>
            <div class="col-6 col-md-2 mb-3"><label class="form-label small text-muted mb-1">Avisar (dias antes)</label>
              <div class="input-chip"><i class="fa fa-clock-o"></i><input type="number" min="0" max="60" name="dias_aviso" id="cfg_dias" value="<?php echo (int)($cfg['dias_aviso'] ?? 3); ?>"></div></div>
            <div class="col-6 col-md-2 mb-3 d-flex align-items-end">
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="notif_ativo" id="cfg_ativo" <?php echo !empty($cfg['notif_ativo'])?'checked':''; ?>><label class="form-check-label small" for="cfg_ativo">Ativo</label></div>
            </div>
          </div>
          <hr>
          <div class="section-sub mb-2"><i class="fa fa-server"></i> Servidor de e-mail (SMTP) — opcional, mas recomendado para envio confiável</div>
          <div class="row">
            <div class="col-12 col-md-6 mb-3"><label class="form-label small text-muted mb-1">Host SMTP</label>
              <div class="input-chip"><i class="fa fa-server"></i><input type="text" name="smtp_host" placeholder="smtp.seudominio.com" value="<?php echo htmlspecialchars($cfg['smtp_host'] ?? '', ENT_QUOTES); ?>"></div></div>
            <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">Porta</label>
              <div class="input-chip"><i class="fa fa-plug"></i><input type="number" name="smtp_port" placeholder="465" value="<?php echo htmlspecialchars($cfg['smtp_port'] ?? '', ENT_QUOTES); ?>"></div></div>
            <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">Segurança</label>
              <div class="input-chip"><i class="fa fa-lock"></i><select name="smtp_secure">
                <?php $sec=$cfg['smtp_secure'] ?? ''; foreach(['','ssl','tls'] as $o): ?><option value="<?php echo $o; ?>" <?php echo $sec===$o?'selected':''; ?>><?php echo $o===''?'Nenhuma':strtoupper($o); ?></option><?php endforeach; ?>
              </select></div></div>
            <div class="col-12 col-md-6 mb-3"><label class="form-label small text-muted mb-1">Usuário SMTP</label>
              <div class="input-chip"><i class="fa fa-user"></i><input type="text" name="smtp_user" placeholder="atlas@seudominio.com" value="<?php echo htmlspecialchars($cfg['smtp_user'] ?? '', ENT_QUOTES); ?>"></div></div>
            <div class="col-12 col-md-6 mb-3"><label class="form-label small text-muted mb-1">Senha SMTP</label>
              <div class="input-chip"><i class="fa fa-key"></i><input type="password" name="smtp_pass" placeholder="<?php echo !empty($cfg['smtp_pass'])?'•••••• (mantém a atual)':'senha'; ?>"></div></div>
            <div class="col-12 col-md-6 mb-3"><label class="form-label small text-muted mb-1">Remetente (e-mail)</label>
              <div class="input-chip"><i class="fa fa-at"></i><input type="email" name="smtp_from_email" placeholder="atlas@seudominio.com" value="<?php echo htmlspecialchars($cfg['smtp_from_email'] ?? '', ENT_QUOTES); ?>"></div></div>
            <div class="col-12 col-md-6 mb-3"><label class="form-label small text-muted mb-1">Remetente (nome)</label>
              <div class="input-chip"><i class="fa fa-id-badge"></i><input type="text" name="smtp_from_name" placeholder="Atlas - Contas a Pagar" value="<?php echo htmlspecialchars($cfg['smtp_from_name'] ?? '', ENT_QUOTES); ?>"></div></div>
          </div>
          <div class="alert alert-info py-2" style="font-size:.82rem"><i class="fa fa-info-circle"></i> Para envio automático, agende o <code>enviar_alertas.php</code> no Agendador de Tarefas/cron (ex.: 1x ao dia).</div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-primary" id="cfgTestBtn" onclick="capTestarAlerta()"><i class="fa fa-paper-plane"></i> Enviar alerta agora</button>
        <button class="btn btn-primary" onclick="capSalvarConfig()"><i class="fa fa-save"></i> Salvar</button>
      </div>
    </div>
  </div>
</div>

<!-- ============ MODAL PAGAMENTO ============ -->
<div class="modal fade cap-modal" id="pagarModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#16a34a,#15803d)">
        <div class="d-flex align-items-center gap-2">
          <span style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center"><i class="fa fa-check"></i></span>
          <div><div style="font-weight:800;font-size:1.05rem">Registrar pagamento</div><div style="font-size:.8rem;opacity:.9" id="pg_titulo">—</div></div>
        </div>
        <button type="button" class="cap-close" data-bs-dismiss="modal"><i class="fa fa-times"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="pg_id"><input type="hidden" id="pg_valor">
        <div class="mb-3 text-center">
          <div class="text-muted small">Valor da conta</div>
          <div style="font-size:1.6rem;font-weight:800" id="pg_valor_fmt">R$ 0,00</div>
        </div>
        <div class="mb-3">
          <label class="form-label small text-muted mb-1">Forma de pagamento *</label>
          <div class="input-chip"><i class="fa fa-credit-card"></i><select id="pg_forma">
            <?php foreach(cap_formas_pagamento() as $f=>$conta): ?>
              <option value="<?php echo htmlspecialchars($f, ENT_QUOTES); ?>" data-conta="<?php echo $conta; ?>"><?php echo htmlspecialchars($f); ?></option>
            <?php endforeach; ?>
          </select></div>
        </div>
        <div class="mb-3">
          <label class="form-label small text-muted mb-1">Data do pagamento</label>
          <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" id="pg_data"></div>
        </div>
        <div id="pg_saldo_box" class="pg-saldo"><i class="fa fa-wallet"></i> <span id="pg_saldo_txt">—</span></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" id="pgConfirmBtn" onclick="capConfirmarPagamento()"><i class="fa fa-check"></i> Confirmar pagamento</button>
      </div>
    </div>
  </div>
</div>

<!-- ============ MODAL ANEXOS ============ -->
<div class="modal fade cap-modal" id="anexosModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <span style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center"><i class="fa fa-paperclip"></i></span>
          <div><div style="font-weight:800;font-size:1.05rem">Anexos</div><div style="font-size:.8rem;opacity:.9" id="axSub">Comprovantes e documentos</div></div>
        </div>
        <button type="button" class="cap-close" data-bs-dismiss="modal"><i class="fa fa-times"></i></button>
      </div>
      <div class="modal-body">
        <div id="axScreenList">
          <div id="axDz" class="cap-dz">
            <div class="ic"><i class="fa fa-cloud-upload"></i></div>
            <div style="font-weight:700">Arraste comprovantes aqui ou clique para selecionar</div>
            <div class="text-muted" style="font-size:.84rem">PDF, imagens, Word, Excel, TXT, ZIP, XML, OFX — até 20 MB</div>
            <input id="axFile" type="file" multiple hidden>
          </div>
          <input id="axDesc" class="form-control mt-3" placeholder="Descrição (opcional) — ex.: Comprovante de pagamento">
          <div id="axQueue" class="ax-queue"></div>
          <hr>
          <div id="axList" class="ax-list"><div class="text-center text-muted py-3">Carregando…</div></div>
        </div>
        <div id="axScreenView" style="display:none">
          <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
            <button class="btn btn-outline-secondary btn-sm" id="axBack"><i class="fa fa-arrow-left"></i> Voltar</button>
            <span class="fw-bold text-truncate" id="axViewName" style="max-width:45%"></span>
            <span class="ms-auto d-flex gap-2">
              <a class="btn btn-outline-primary btn-sm" id="axOpenTab" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Nova aba</a>
              <a class="btn btn-primary btn-sm" id="axDownload"><i class="fa fa-download"></i> Baixar</a>
            </span>
          </div>
          <div id="axViewerBody"></div>
        </div>
      </div>
    </div>
  </div>
</div>
