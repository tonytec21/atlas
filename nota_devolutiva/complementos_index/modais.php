<!-- Modal para visualização da nota devolutiva -->  
<div class="modal fade" id="viewNotaModal" tabindex="-1" aria-labelledby="viewNotaModalLabel" aria-hidden="true">  
    <div class="modal-dialog modal-lg">  
        <div class="modal-content">  
            <div class="modal-header">  
                <h5 class="modal-title" id="viewNotaModalLabel">Nota Devolutiva</h5>  
                <div class="status-controls">  
                    <div class="select-status-wrapper">  
                        <select id="statusSelect">  
                            <?php foreach ($statusOptions as $statusName => $statusColor): ?>  
                                <option value="<?php echo $statusName; ?>"><?php echo $statusName; ?></option>  
                            <?php endforeach; ?>  
                        </select>  
                    </div>  
                    <button type="button" class="btn" id="btnUpdateStatus">Atualizar Status</button>  
                </div>  
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>  
            </div>  
            <div class="modal-body" id="notaModalBody">  
                <div class="d-flex justify-content-center">  
                    <div class="spinner-border text-primary" role="status">  
                        <span class="visually-hidden">Carregando...</span>  
                    </div>  
                </div>  
            </div>  
            <div class="modal-footer">  
                <button type="button" class="btn btn-secondary" id="btnEditNota"><i class="fa fa-pencil"></i> Editar</button>  
                <button type="button" class="btn btn-primary" id="btnPrintNota"><i class="fa fa-print" aria-hidden="true"></i> Imprimir</button>  
            </div>  
        </div>  
    </div>  
</div>  