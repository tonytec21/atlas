<div id="main" class="main-content">  
    <div class="container">  
        <h3>Pesquisa de Notas Devolutivas</h3>  
        <hr>  
        <form id="searchForm" method="GET">  
            <div class="row mb-3">  
                <div class="col-md-2">  
                    <label for="numero">Número:</label>  
                    <input type="text" class="form-control" id="numero" name="numero" value="<?php echo isset($_GET['numero']) ? htmlspecialchars($_GET['numero']) : ''; ?>">  
                </div>  
                <div class="col-md-2">  
                    <label for="protocolo">Protocolo:</label>  
                    <input type="text" class="form-control" id="protocolo" name="protocolo" value="<?php echo isset($_GET['protocolo']) ? htmlspecialchars($_GET['protocolo']) : ''; ?>">  
                </div> 
                <div class="col-md-2">  
                    <label for="data">Data:</label>  
                    <input type="date" class="form-control" id="data" name="data" value="<?php echo isset($_GET['data']) ? htmlspecialchars($_GET['data']) : ''; ?>">  
                </div>  
                <div class="col-md-3">  
                    <label for="apresentante">Apresentante:</label>  
                    <input type="text" class="form-control" id="apresentante" name="apresentante" value="<?php echo isset($_GET['apresentante']) ? htmlspecialchars($_GET['apresentante']) : ''; ?>">  
                </div>  
                <div class="col-md-3">  
                    <label for="cpf_cnpj">CPF/CNPJ:</label>  
                    <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" value="<?php echo isset($_GET['cpf_cnpj']) ? htmlspecialchars($_GET['cpf_cnpj']) : ''; ?>">  
                </div>  
                <div class="col-md-4">  
                    <label for="titulo">Título:</label>  
                    <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo isset($_GET['titulo']) ? htmlspecialchars($_GET['titulo']) : ''; ?>">  
                </div>  
                <div class="col-md-3">  
                    <label for="origem_titulo">Origem do Título:</label>  
                    <input type="text" class="form-control" id="origem_titulo" name="origem_titulo" value="<?php echo isset($_GET['origem_titulo']) ? htmlspecialchars($_GET['origem_titulo']) : ''; ?>">  
                </div>  
                <div class="col-md-2">  
                    <label for="data_protocolo">Data do Protocolo:</label>  
                    <input type="date" class="form-control" id="data_protocolo" name="data_protocolo" value="<?php echo isset($_GET['data_protocolo']) ? htmlspecialchars($_GET['data_protocolo']) : ''; ?>">  
                </div>  
                <div class="col-md-3">  
                    <label for="status">Status:</label>  
                    <select class="form-control" id="status" name="status">  
                        <option value="">Todos</option>  
                        <?php foreach ($statusOptions as $statusName => $statusColor): ?>  
                            <option value="<?php echo $statusName; ?>" <?php echo (isset($_GET['status']) && $_GET['status'] == $statusName) ? 'selected' : ''; ?>>  
                                <?php echo $statusName; ?>  
                            </option>  
                        <?php endforeach; ?>  
                    </select>   
                </div>  
            </div>  
            <div class="row mb-3" style="margin-top: -15px;">  
                <div class="col-md-6">  
                    <button type="submit" class="btn btn-primary w-100"><i class="fa fa-filter"></i> Filtrar</button>  
                </div>  
                <div class="col-md-6 text-right">  
                    <button type="button" class="btn btn-success w-100" onclick="window.location.href='cadastrar-nota-devolutiva.php'">  
                        <i class="fa fa-plus"></i> Criar Nota Devolutiva  
                    </button>  
                </div>  
            </div>  
        </form>  
        <hr>  
        <div class="table-responsive">  
            <h5>Resultados da Pesquisa</h5>  
            <table class="table table-striped table-bordered" id="tabelaResultados" style="zoom: 90%">  
                <thead>  
                    <tr>  
                        <th>Número</th>  
                        <th>Data</th>  
                        <th>Título</th>  
                        <th>Apresentante</th>  
                        <th>CPF/CNPJ</th>  
                        <th>Protocolo</th>  
                        <th>Status</th>  
                        <th>Ações</th>  
                    </tr>  
                </thead>  
                <tbody>  
                    <?php foreach ($notas as $nota) : ?>  
                        <?php  
                            // Define a classe CSS baseada no status da nota  
                            $statusClass = 'status-pendente'; // Padrão  
                            $status = isset($nota['status']) ? $nota['status'] : 'Pendente';  
                            
                            switch ($status) {  
                                case 'Exigência Cumprida':  
                                    $statusClass = 'status-exigencia-cumprida';  
                                    break;  
                                case 'Exigência Não Cumprida':  
                                    $statusClass = 'status-exigencia-nao-cumprida';  
                                    break;  
                                case 'Prazo Expirado':  
                                    $statusClass = 'status-prazo-expirado';  
                                    break;  
                                case 'Em Análise':  
                                    $statusClass = 'status-em-analise';  
                                    break;  
                                case 'Cancelada':  
                                    $statusClass = 'status-cancelada';  
                                    break;  
                                case 'Aguardando Documentação':  
                                    $statusClass = 'status-aguardando-documentacao';  
                                    break;  
                                default:  
                                    $statusClass = 'status-pendente';  
                            }  
                        ?>  
                        <tr>  
                            <td data-label="Número"><?php echo htmlspecialchars($nota['numero']); ?></td>  
                            <td data-label="Data"><?php echo date('d/m/Y', strtotime($nota['data'])); ?></td>  
                            <td data-label="Título" title="<?php echo htmlspecialchars($nota['titulo']); ?>"><?php echo htmlspecialchars($nota['titulo']); ?></td>  
                            <td data-label="Apresentante"><?php echo htmlspecialchars($nota['apresentante']); ?></td>  
                            <td data-label="CPF/CNPJ"><?php echo htmlspecialchars($nota['cpf_cnpj'] ?? ''); ?></td>  
                            <td data-label="Protocolo"><?php echo htmlspecialchars($nota['protocolo']); ?></td>  
                            <td data-label="Status"><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>  
                            <td data-label="Ações">  
                                <button class="btn btn-info btn-sm" onclick="viewNota('<?php echo $nota['numero']; ?>')" title="Visualizar">  
                                    <i class="fa fa-eye"></i>  
                                </button>  
                                <button class="btn btn-edit btn-sm" onclick="editNota('<?php echo $nota['numero']; ?>')" title="Editar">  
                                    <i class="fa fa-pencil"></i>  
                                </button>  
                            </td>  
                        </tr>  
                    <?php endforeach; ?>  
                </tbody>  
            </table>  
        </div>  
    </div>  
</div>  