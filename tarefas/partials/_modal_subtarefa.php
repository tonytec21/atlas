<!-- Modal Criar Subtarefa -->
<?php
/*
 * Modal de criação de subtarefa, redesenhado no padrão visual do módulo v2.
 *
 * Todos os IDs e atributos `name` do formulário foram preservados exatamente
 * como estavam (subTaskTitle, subTaskCategory, subTaskOrigin, subTaskDeadline,
 * subTaskPriority, subTaskEmployee, reviewer, subTaskDescription,
 * subTaskAttachments, compartilharAnexos, selectedFiles, subTaskCreatedBy,
 * subTaskCreatedAt, subTaskPrincipalId), porque o JavaScript do módulo e o
 * endpoint save_sub_task.php dependem deles.
 */
$tfUsuarioSub = usuario_atual();
?>
<div class="modal fade" id="createSubTaskModal" tabindex="-1" role="dialog"
     aria-labelledby="createSubTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg tf-app" role="document">
        <div class="modal-content tf-modal-conteudo">

            <div class="tf-modal-topo">
                <h5 id="createSubTaskModalLabel">
                    <i class="fa fa-sitemap"></i> Nova subtarefa
                </h5>
                <span class="tf-mini" style="opacity:.85">
                    vinculada à tarefa <span id="tfSubtarefaPrincipalRotulo">—</span>
                </span>
                <button type="button" class="tf-fechar" data-dismiss="modal"
                        data-bs-dismiss="modal" aria-label="Fechar">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <div class="tf-modal-corpo">
                <form id="subTaskForm" enctype="multipart/form-data" method="POST" action="save_sub_task.php">

                    <div class="tf-info-grade" style="margin-bottom:16px">
                        <div style="grid-column:1/-1">
                            <label class="tf-rotulo" for="subTaskTitle">
                                Título da subtarefa <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <input type="text" class="tf-input" id="subTaskTitle" name="title" required
                                   placeholder="Ex.: conferir cadeia dominial dos últimos 20 anos">
                        </div>

                        <div>
                            <label class="tf-rotulo" for="subTaskCategory">
                                Categoria <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <select id="subTaskCategory" name="category" class="tf-select" required>
                                <option value="">Selecione…</option>
                                <?php
                                foreach (listar_categorias() as $row) {
                                    echo "<option value='" . e($row['id']) . "'>" . e($row['titulo']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="tf-rotulo" for="subTaskOrigin">
                                Origem <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <select id="subTaskOrigin" name="origin" class="tf-select" required>
                                <option value="">Selecione…</option>
                                <?php
                                foreach (listar_origens() as $row) {
                                    echo "<option value='" . e($row['id']) . "'>" . e($row['titulo']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="tf-rotulo" for="subTaskDeadline">
                                Data limite <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <input type="datetime-local" class="tf-input" id="subTaskDeadline"
                                   name="deadline" required>
                        </div>

                        <div>
                            <label class="tf-rotulo" for="subTaskPriority">
                                Prioridade <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <select id="subTaskPriority" name="priority" class="tf-select" required>
                                <?php foreach (array_keys(tarefas_prioridades()) as $tfP): ?>
                                    <option value="<?php echo e($tfP); ?>"<?php echo $tfP === 'Média' ? ' selected' : ''; ?>>
                                        <?php echo e($tfP); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="tf-rotulo" for="subTaskEmployee">
                                Funcionário responsável <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <select id="subTaskEmployee" name="employee" class="tf-select" required>
                                <option value="">Selecione…</option>
                                <?php
                                foreach (listar_funcionarios() as $row) {
                                    $sel = ($row['nome_completo'] === $tfUsuarioSub['nome']) ? ' selected' : '';
                                    echo "<option value='" . e($row['nome_completo']) . "'" . $sel . ">"
                                       . e($row['nome_completo']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="tf-rotulo" for="reviewer">Revisor</label>
                            <select class="tf-select" id="reviewer" name="reviewer">
                                <option value="">Nenhum</option>
                                <?php
                                foreach (listar_funcionarios() as $row) {
                                    echo "<option value='" . e($row['nome_completo']) . "'>"
                                       . e($row['nome_completo']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-align-left"></i> Descrição
                        </div>
                        <textarea class="tf-textarea" id="subTaskDescription" name="description" rows="4"
                                  placeholder="O que precisa ser feito nesta etapa."></textarea>
                    </div>

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-paperclip"></i> Anexos
                        </div>

                        <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;margin-bottom:12px">
                            <input class="tf-check-caixa" type="checkbox" id="compartilharAnexos"
                                   name="compartilharAnexos" value="1">
                            <span>
                                <strong>Reaproveitar os anexos da tarefa principal</strong>
                                <span class="tf-mini tf-mudo" style="display:block">
                                    A subtarefa passa a apontar para os mesmos arquivos, sem duplicar nada no disco.
                                </span>
                            </span>
                        </label>

                        <div class="file-upload-wrapper">
                            <div class="tf-zona-upload" id="subTaskZonaUpload">
                                <i class="fa fa-cloud-upload"></i>
                                <strong>Arraste arquivos aqui</strong> ou clique para selecionar
                                <div class="tf-mini tf-mudo" style="margin-top:6px">
                                    Até <?php echo TAREFAS_UPLOAD_MAX_MB; ?> MB por arquivo
                                </div>
                                <input type="file" id="subTaskAttachments" name="attachments[]" multiple
                                       style="display:none">
                            </div>
                            <div class="tf-anexos" id="selectedFiles" style="margin-top:12px"></div>
                        </div>
                    </div>

                    <input type="hidden" id="subTaskCreatedBy" name="createdBy"
                           value="<?php echo e($tfUsuarioSub['usuario']); ?>">
                    <input type="hidden" id="subTaskCreatedAt" name="createdAt"
                           value="<?php echo date('Y-m-d H:i:s'); ?>">
                    <input type="hidden" id="subTaskPrincipalId" name="id_tarefa_principal">
                </form>
            </div>

            <div class="tf-modal-rodape">
                <button type="button" class="tf-btn" data-dismiss="modal" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Cancelar
                </button>
                <button type="submit" form="subTaskForm" class="tf-btn tf-btn-primario">
                    <i class="fa fa-save"></i> Criar subtarefa
                </button>
            </div>

        </div>
    </div>
</div>
