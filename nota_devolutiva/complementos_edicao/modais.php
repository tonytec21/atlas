<!-- Modal para visualizar notas anteriores -->  
<div class="modal fade" id="notasAnterioresModal" tabindex="-1" aria-labelledby="notasAnterioresModalLabel" aria-hidden="true">  
        <div class="modal-dialog modal-xl">  
            <div class="modal-content">  
                <div class="modal-header">  
                    <h5 class="modal-title" id="notasAnterioresModalLabel">Notas Devolutivas Anteriores</h5>  
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>  
                </div>  
                <div class="modal-body">  
                    <table id="notasTable" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">  
                        <thead>  
                            <tr>  
                                <th>Número</th>  
                                <th>Data</th>  
                                <th>Apresentante</th>  
                                <th>Título</th>  
                                <th>Ações</th>  
                            </tr>  
                        </thead>  
                        <tbody id="notasTableBody">  
                            <tr>  
                                <td colspan="5" class="text-center">Carregando notas devolutivas...</td>  
                            </tr>  
                        </tbody>  
                    </table>  
                </div>  
                <div class="modal-footer">  
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>  
                </div>  
            </div>  
        </div>  
    </div>  