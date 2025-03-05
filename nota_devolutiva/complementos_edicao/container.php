<div id="main" class="main-content">  
        <div class="container">  
            <div class="d-flex justify-content-between align-items-center">  
                <h3>Editar Nota Devolutiva - Nº <?php echo htmlspecialchars($numero); ?></h3>  
                <div>  
                    <a href="index.php" class="btn btn-secondary me-2">  
                        <i class="fa fa-arrow-left"></i> Voltar  
                    </a>  
                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#notasAnterioresModal" onclick="carregarNotas()">  
                        <i class="fa fa-history"></i> Ver Notas Anteriores  
                    </button>  
                </div>  
            </div>  
            <hr>  
            
            <?php if (isset($_GET['erro'])): ?>  
            <div class="alert alert-danger alert-dismissible fade show" role="alert">  
                <?php   
                $mensagem = "Erro ao processar a solicitação.";  
                
                if ($_GET['erro'] == 'falha_atualizacao') {  
                    $mensagem = "Erro ao atualizar a nota devolutiva.";  
                    if (isset($_GET['msg'])) {  
                        $mensagem .= " Detalhes: " . htmlspecialchars($_GET['msg']);  
                    }  
                }  
                echo $mensagem;  
                ?>  
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>  
            </div>  
            <?php endif; ?>  
            
            <form method="POST" action="atualizar_nota_devolutiva.php" id="notaForm">  
                <input type="hidden" name="id" value="<?php echo (int)$nota['id']; ?>">  
                <input type="hidden" name="numero" value="<?php echo htmlspecialchars($numero); ?>">  
                
                <div class="form-row">  
                    <div class="form-group col-md-4">  
                        <label for="apresentante">Apresentante/Requerente:</label>  
                        <div class="input-group">  
                            <input type="text" class="form-control" id="apresentante" name="apresentante"   
                                   value="<?php echo htmlspecialchars($nota['apresentante'] ?? ''); ?>" required>  
                            <div class="input-group-append" id="consultaIndicator" style="display:none;">  
                                <span class="input-group-text">  
                                    <i class="fa fa-spinner fa-spin"></i>  
                                </span>  
                            </div>  
                        </div>  
                    </div>  
                    <div class="form-group col-md-3">  
                        <label for="cpf_cnpj">CPF/CNPJ:</label>  
                        <div class="input-group">  
                            <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj"   
                                   value="<?php echo htmlspecialchars($nota['cpf_cnpj'] ?? ''); ?>">  
                            <div class="input-group-append">  
                                <button class="btn btn-outline-secondary" type="button" id="consultarCpfCnpj" title="Consultar CNPJ">  
                                    <i class="fa fa-search"></i>  
                                </button>  
                            </div>  
                        </div>  
                    </div>  
                    <div class="form-group col-md-2">  
                        <label for="protocolo">Número do Protocolo:</label>  
                        <input type="text" class="form-control" id="protocolo" name="protocolo"   
                               value="<?php echo htmlspecialchars($nota['protocolo'] ?? ''); ?>">  
                    </div>  
                    <div class="form-group col-md-3">  
                        <label for="data_protocolo">Data do Protocolo:</label>  
                        <input type="date" class="form-control" id="data_protocolo" name="data_protocolo"   
                               value="<?php echo htmlspecialchars($nota['data_protocolo'] ?? ''); ?>" required>  
                    </div>  
                    <div class="form-group col-md-6">  
                        <label for="titulo">Título Apresentado:</label>  
                        <input type="text" class="form-control" id="titulo" name="titulo"   
                               value="<?php echo htmlspecialchars($nota['titulo'] ?? ''); ?>" required>  
                    </div>  
                    <div class="form-group col-md-6">  
                        <label for="origem_titulo">Origem do Título:</label>  
                        <input type="text" class="form-control" id="origem_titulo" name="origem_titulo"   
                               value="<?php echo htmlspecialchars($nota['origem_titulo'] ?? ''); ?>">  
                    </div>  
                </div>  
                <div class="form-group">  
                    <label for="corpo">Motivos da Devolução:</label>  
                    <textarea class="form-control" id="corpo" name="corpo" rows="10" required><?php echo htmlspecialchars($nota['corpo'] ?? ''); ?></textarea>  
                </div>  
                <div class="form-group">  
                    <label for="prazo_cumprimento">Prazo Para Cumprimento:</label>  
                    <textarea class="form-control" id="prazo_cumprimento" name="prazo_cumprimento" rows="5"><?php echo htmlspecialchars($nota['prazo_cumprimento'] ?? ''); ?></textarea>  
                </div>  
                <div class="form-row">  
                    <div class="form-group col-md-4">  
                        <label for="assinante">Assinante:</label>  
                        <select class="form-control" id="assinante" name="assinante" required>  
                            <?php foreach ($employees as $employee): ?>  
                                <option value="<?php echo htmlspecialchars($employee['nome_completo']); ?>"   
                                        <?php echo (($nota['assinante'] ?? '') == $employee['nome_completo']) ? 'selected' : ''; ?>>  
                                    <?php echo htmlspecialchars($employee['nome_completo']); ?>  
                                </option>  
                            <?php endforeach; ?>  
                        </select>  
                    </div>  
                    <div class="form-group col-md-4">  
                        <label for="cargo_assinante">Cargo do Assinante:</label>  
                        <input type="text" class="form-control" id="cargo_assinante" name="cargo_assinante"   
                               value="<?php echo htmlspecialchars($nota['cargo_assinante'] ?? ''); ?>">  
                    </div>  
                    <div class="form-group col-md-4">  
                        <label for="data">Data da Nota:</label>  
                        <input type="date" class="form-control" id="data" name="data"   
                               value="<?php echo htmlspecialchars($nota['data'] ?? date('Y-m-d')); ?>" required>  
                    </div>  
                </div>  
                
                <div class="form-group mt-4 d-flex justify-content-end">  
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>  
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>  
                </div>  
            </form>  
        </div>  
    </div>  