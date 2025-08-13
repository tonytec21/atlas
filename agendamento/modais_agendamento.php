<!-- Modal Cadastro / Edição -->
<div class="modal fade" id="modalAgendamento" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="formAgendamento">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalAgendamentoLabel">Novo Agendamento</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="agendamento_id" name="agendamento_id">

          <div class="row g-3">
            <!-- Nome -->
            <div class="col-md-6 form-floating">
              <label style="margin-top: 0.5rem; margin-bottom: .2rem;" for="nome">
                <i class="fas fa-user me-1"></i>&nbsp;Nome do Solicitante
              </label>
              <input class="form-control" id="nome" name="nome" placeholder="Nome" required>
            </div>

            <!-- Serviço -->
            <div class="col-md-6 form-floating">
              <label style="margin-top: 0.5rem; margin-bottom: .2rem;" for="servico">
                <i class="fas fa-briefcase me-1"></i>&nbsp;Serviço
              </label>
              <input class="form-control" id="servico" name="servico" placeholder="Serviço" required>
            </div>

            <!-- Data original -->
            <div class="col-md-6 form-floating">
              <label style="margin-top: 0.5rem; margin-bottom: .2rem;" for="data_hora">
                <i class="far fa-clock me-1"></i>&nbsp;Data e Hora
              </label>
              <input type="datetime-local" class="form-control" id="data_hora" name="data_hora"
                     min="<?= date('Y-m-d\TH:i'); ?>">
            </div>

            <!-- Status (wrapper) -->
            <div id="grp_status" class="col-md-6 form-floating" style="display:none;">
              <label style="margin-top: 0.5rem; margin-bottom: .2rem;" for="status_select" class="text-muted">
                <i class="fas fa-info-circle me-1"></i>&nbsp;Status
              </label>
              <select class="form-select w-100" id="status_select" name="status"
                      style="height:calc(2.8rem + 2px);border-radius:8px;border-color:#ced4da;">
                <option value="ativo">Ativo</option>
                <option value="reagendado">Reagendado</option>
                <option value="cancelado">Cancelado</option>
                <option value="concluido">Concluído</option>
              </select>
            </div>

            <!-- Data de reagendamento -->
            <div class="col-md-6 form-floating" id="grp_reagendamento" style="display:none">
              <label style="margin-top: 0.5rem; margin-bottom: .2rem;" for="data_reagendamento">
                <i class="far fa-clock me-1"></i>&nbsp;Nova Data
              </label>
              <input type="datetime-local" class="form-control" id="data_reagendamento"
                     name="data_reagendamento" min="<?= date('Y-m-d\TH:i'); ?>" required>
            </div>

            <!-- Observações -->
            <div class="col-12 form-floating">
              <label style="margin-top: 0.5rem; margin-bottom: .2rem;" for="observacoes">Observações</label>
              <textarea class="form-control" id="observacoes" name="observacoes" style="height:130px"></textarea>
            </div>
          </div>

          <!-- Anexos (somente em edição) -->
          <div id="anexosWrapper" class="mt-4" style="display:none;">
            <label style="margin-top: 0.5rem; margin-bottom: .2rem;" class="form-label fw-semibold">Anexos</label>
            <div id="dropAnexos" class="dropzone"></div>
            <div id="listaAnexos" class="mt-3"></div>
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-check me-1"></i>&nbsp;Salvar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Visualização Detalhes -->
<div class="modal fade" id="modalVisualizar" tabindex="-1" aria-hidden="true" data-payload="">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white d-flex align-items-center">
        <h5 class="modal-title">
          <i class="fas fa-eye me-1"></i>&nbsp;Detalhes do Agendamento
        </h5>
        <div class="ms-auto d-flex gap-2">
          <button type="button" class="btn btn-sm btn-light btn-print" id="btnImprimirComprovante" title="Imprimir comprovante">
            <i class="fas fa-print me-1"></i>Imprimir
          </button>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
      </div>

      <div class="modal-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Protocolo</dt><dd class="col-sm-8" id="view_id"></dd>
          <dt class="col-sm-4">Nome do Solicitante</dt><dd class="col-sm-8" id="view_nome"></dd>
          <dt class="col-sm-4">Serviço</dt><dd class="col-sm-8" id="view_servico"></dd>
          <dt class="col-sm-4">Data e Hora</dt><dd class="col-sm-8" id="view_datahora"></dd>
          <dt class="col-sm-4">Status</dt><dd class="col-sm-8" id="view_status"></dd>
          <dt class="col-sm-4">Observações</dt><dd class="col-sm-8" id="view_obs"></dd>
          <dt class="col-sm-4">Anexos</dt><dd class="col-sm-8" id="view_anexos"></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<!-- Modal Viewer -->
<div class="modal fade" id="modalViewer" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl" style="max-width:90%">
    <div class="modal-content bg-dark">
      <div class="modal-header border-0">
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="frameViewer" style="width:100%;height:80vh;border:0"></iframe>
      </div>
    </div>
  </div>
</div>
