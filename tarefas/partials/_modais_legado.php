    <!-- Modal Adicionar Comentário -->  
    <div class="modal fade" id="addCommentModal" tabindex="-1" role="dialog" aria-labelledby="addCommentModalLabel" aria-hidden="true">  
        <div class="modal-dialog modal-gl" role="document">  
            <div class="modal-content">  
                <!-- Header -->  
                <div class="primary-header">  
                    <div class="modal-header-content">  
                        <h5 class="modal-title" id="addCommentModalLabel">  
                            <i class="fa fa-comment"></i> Adicionar Comentário e Anexos  
                        </h5>  
                    </div>  
                </div>  

                <!-- Body -->  
                <div class="modal-body">  
                    <form id="commentForm">  
                        <!-- Seção de Comentário -->  
                        <div class="comment-section">  
                            <label for="commentDescription">Comentário</label>  
                            <textarea class="form-control-modern" id="commentDescription" name="commentDescription" rows="5"   
                                placeholder="Digite seu comentário aqui..."></textarea>  
                        </div>  

                        <!-- Seção de Anexos -->  
                        <div class="attachments-section">  
                            <label>Anexos</label>  
                            <div class="file-upload-wrapper">  
                                <input type="file" id="commentAttachments" name="commentAttachments[]" multiple class="modern-file-input">  
                                <label for="commentAttachments" class="file-upload-label">  
                                    <i class="fa fa-cloud-upload"></i>  
                                    <span class="upload-text">Arraste os arquivos ou clique para selecionar</span>  
                                </label>  
                                <div class="selected-files" id="selectedFiles"></div>  
                            </div>  
                        </div>  
                    </form>  
                </div>  

                <!-- Footer -->  
                <div class="modal-footer">  
                    <button type="button" class="btn-close-modal" data-dismiss="modal">  
                        <i class="fa fa-times"></i> Cancelar  
                    </button>  
                    <button type="submit" form="commentForm" class="action-btn success">  
                        <i class="fa fa-save"></i> Salvar Comentário  
                    </button>  
                </div>  
            </div>  
        </div>  
    </div>

    <!-- Modal Vincular Ofício -->  
    <div class="modal fade" id="vincularOficioModal" tabindex="-1" role="dialog" aria-labelledby="vincularOficioModalLabel" aria-hidden="true">  
        <div class="modal-dialog modal-gl" role="document">  
            <div class="modal-content">  
                <!-- Header -->  
                <div class="primary-header">  
                    <div class="modal-header-content">  
                        <h5 class="modal-title" id="vincularOficioModalLabel">  
                            <i class="fa fa-link"></i> Vincular Ofício  
                        </h5>   
                    </div>  
                </div>  

                <!-- Body -->  
                <div class="modal-body">  
                    <form id="vincularOficioForm">  
                        <!-- Seção de Vínculo -->  
                        <div class="link-section">  
                            <div class="info-item">  
                                <label for="numeroOficio">Número do Ofício</label>  
                                <div class="input-group">  
                                    <input type="text" class="form-control-modern" id="numeroOficio" name="numeroOficio" placeholder="Digite o número do ofício">  
                                    <div class="input-icon">  
                                        <i class="fa fa-file-text"></i>  
                                    </div>  
                                </div>  
                            </div>  
                        </div>  
                    </form>  
                </div>  

                <!-- Footer -->  
                <div class="modal-footer">  
                    <button type="button" class="btn-close-modal" data-dismiss="modal">  
                        <i class="fa fa-times"></i> Cancelar  
                    </button>  
                    <button type="submit" form="vincularOficioForm" class="action-btn primary">  
                        <i class="fa fa-save"></i> Vincular  
                    </button>  
                </div>  
            </div>  
        </div>  
    </div>

<!-- Modal Histórico de Guias de Recebimento -->
<style>
    #guiaHistoricoModal .modal-body { padding: 1.5rem; }

    #guiaHistoricoModal .guia-hist-info {
        margin-bottom: 1rem;
        font-size: .9rem;
        color: var(--text-secondary, #6c757d);
    }

    #guiaHistoricoModal .guia-hist-wrapper {
        max-height: 55vh;
        overflow-y: auto;
        border: 1px solid var(--border-color, #dee2e6);
        border-radius: 8px;
    }

    #guiaHistoricoTabela { width: 100%; margin: 0; font-size: .875rem; }

    #guiaHistoricoTabela thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--background-secondary, #f1f3f5);
        color: var(--text-primary, #212529);
        border-bottom: 2px solid var(--border-color, #dee2e6);
        padding: .65rem .75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    #guiaHistoricoTabela tbody td {
        padding: .6rem .75rem;
        border-bottom: 1px solid var(--border-color, #eef1f4);
        vertical-align: middle;
    }

    #guiaHistoricoTabela tbody tr:last-child td { border-bottom: 0; }

    #guiaHistoricoTabela tbody tr.guia-hist-atual {
        background: rgba(39, 174, 96, .08);
    }

    #guiaHistoricoModal .guia-hist-badge {
        display: inline-block;
        background: #27ae60;
        color: #fff;
        border-radius: 10px;
        padding: 1px 8px;
        font-size: .7rem;
        font-weight: 600;
        margin-left: 4px;
    }

    #guiaHistoricoModal .guia-hist-vazio {
        text-align: center;
        padding: 2rem;
        color: var(--text-secondary, #6c757d);
    }

    #guiaHistoricoModal .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        padding: 1rem 1.5rem;
        background: var(--background-secondary, #f8f9fa);
        border-top: 1px solid var(--border-color, #dee2e6);
    }

    body.dark-mode #guiaHistoricoModal .modal-content { background: var(--background-primary); }
    body.dark-mode #guiaHistoricoTabela tbody tr.guia-hist-atual { background: rgba(39, 174, 96, .18); }

    /* Dica abaixo do select de funcionário na emissão */
    #guiaRecebimentoModal .guia-hint {
        display: block;
        margin-top: .35rem;
        font-size: .75rem;
        line-height: 1.3;
        color: var(--text-secondary, #6c757d);
    }

    @media (max-width: 768px) {
        #guiaHistoricoModal .modal-footer { flex-direction: column-reverse; }
        #guiaHistoricoModal .modal-footer button { width: 100%; }
    }
</style>

<div class="modal fade" id="guiaHistoricoModal" tabindex="-1" role="dialog" aria-labelledby="guiaHistoricoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <!-- Header -->
            <div class="primary-header">
                <div class="modal-header-content">
                    <h5 class="modal-title" id="guiaHistoricoModalLabel">
                        <i class="fa fa-history"></i> Histórico de Guias de Recebimento
                    </h5>
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body">
                <div class="guia-hist-info">
                    Guias emitidas para o Protocolo Geral nº <b id="guiaHistoricoProtocolo"></b>.
                    Escolha uma guia para reimprimir ou emita uma nova.
                </div>

                <div class="guia-hist-wrapper">
                    <table id="guiaHistoricoTabela" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Guia nº</th>
                                <th>Recebimento</th>
                                <th>Apresentante</th>
                                <th>Funcionário</th>
                                <th>Emitida em</th>
                                <th>Emitida por</th>
                                <th class="text-center">Impressões</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button type="button" class="btn-close-modal" data-dismiss="modal">
                    <i class="fa fa-times"></i> Fechar
                </button>
                <button type="button" id="btnAtualizarHistoricoGuias" class="action-btn">
                    <i class="fa fa-refresh"></i> Atualizar
                </button>
                <button type="button" id="btnNovaGuiaHistorico" class="action-btn success">
                    <i class="fa fa-plus"></i> Emitir nova guia
                </button>
            </div>
        </div>
    </div>
</div>


<?php
/*
 * Guia de recebimento, recibo de entrega e arquivamento do ato foram
 * redesenhados no padrão visual do módulo v2 e vivem agora em
 * partials/_modais_documentos.php, com os mesmos IDs e names de antes.
 *
 * IMPORTANTE: este include tem de ficar no nível raiz do arquivo. Um modal
 * aninhado dentro de outro nunca aparece, porque o modal externo fica com
 * display:none enquanto está fechado — e leva os filhos junto.
 */
include(__DIR__ . '/_modais_documentos.php');
?>

<?php
/*
 * O modal de criação de subtarefa foi redesenhado no padrão visual do
 * módulo v2 e vive agora em partials/_modal_subtarefa.php, com os mesmos
 * IDs e names de antes.
 */
include(__DIR__ . '/_modal_subtarefa.php');
?>
