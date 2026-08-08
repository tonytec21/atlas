<?php
/**
 * Atlas · Tarefas — modais de documentos, no padrão visual do módulo v2.
 *
 * Reúne:
 *   · Guia de Recebimento
 *   · Recibo de Entrega
 *   · Arquivar Ato (envia para o módulo de Arquivamento)
 *
 * Todos os IDs e `name` dos campos foram preservados exatamente como estavam
 * na versão anterior, porque o JavaScript do módulo, o save_guia_recebimento.php,
 * o save_recibo_entrega.php e o ../arquivamento/save_ato.php dependem deles.
 *
 * Atenção a uma herança: os campos de observação dos dois primeiros modais têm
 * o mesmo id (`observacoes`), duplicado desde a versão original. Por isso o
 * JavaScript sempre os lê com o formulário no seletor
 * (`#guiaRecebimentoForm #observacoes`), nunca pelo id solto.
 */
?>

<!-- ==================== Guia de Recebimento ==================== -->
<div class="modal fade" id="guiaRecebimentoModal" tabindex="-1" role="dialog"
     aria-labelledby="guiaRecebimentoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg tf-app" role="document">
        <div class="modal-content tf-modal-conteudo">

            <div class="tf-modal-topo">
                <h5 id="guiaRecebimentoModalLabel">
                    <i class="fa fa-file-text-o"></i> Guia de recebimento
                </h5>
                <span class="tf-mini" style="opacity:.85">
                    comprovante do que a serventia recebeu do apresentante
                </span>
                <button type="button" class="tf-fechar" data-dismiss="modal"
                        data-bs-dismiss="modal" aria-label="Fechar">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <form id="guiaRecebimentoForm">
                <div class="tf-modal-corpo">

                    <div class="tf-info-grade" style="margin-bottom:16px">
                        <div style="grid-column:1/-1">
                            <label class="tf-rotulo" for="cliente">
                                Apresentante <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <input type="text" class="tf-input" id="cliente" name="cliente" required
                                   placeholder="Nome de quem entregou os documentos">
                        </div>

                        <div>
                            <label class="tf-rotulo" for="dataRecebimento">
                                Data do recebimento <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <input type="datetime-local" class="tf-input" id="dataRecebimento"
                                   name="dataRecebimento" required>
                        </div>

                        <div>
                            <label class="tf-rotulo" for="funcionario">
                                Recebido por <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <select id="funcionario" name="funcionario" class="tf-select" required>
                                <option value="">Selecione…</option>
                            </select>
                        </div>
                    </div>

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-list-ul"></i> Documentos recebidos
                        </div>
                        <textarea class="tf-textarea" id="documentosRecebidos" name="documentosRecebidos"
                                  rows="5" placeholder="Um documento por linha."></textarea>
                        <p class="tf-mini tf-mudo" style="margin:8px 0 0">
                            Os anexos da tarefa já vêm listados aqui. Ajuste conforme o que foi
                            fisicamente entregue no balcão.
                        </p>
                    </div>

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-commenting-o"></i> Observações
                        </div>
                        <textarea class="tf-textarea" id="observacoes" name="observacoes" rows="3"
                                  placeholder="Ressalvas, prazos combinados, documentos pendentes."></textarea>
                    </div>

                </div>

                <div class="tf-modal-rodape">
                    <button type="button" class="tf-btn" data-dismiss="modal" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="tf-btn tf-btn-primario">
                        <i class="fa fa-print"></i> Emitir e imprimir
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- ==================== Recibo de Entrega ==================== -->
<div class="modal fade" id="reciboEntregaModal" tabindex="-1" role="dialog"
     aria-labelledby="reciboEntregaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg tf-app" role="document">
        <div class="modal-content tf-modal-conteudo">

            <div class="tf-modal-topo">
                <h5 id="reciboEntregaModalLabel">
                    <i class="fa fa-handshake-o"></i> Recibo de entrega
                </h5>
                <span class="tf-mini" style="opacity:.85">
                    comprovante do que foi devolvido ao interessado
                </span>
                <button type="button" class="tf-fechar" data-dismiss="modal"
                        data-bs-dismiss="modal" aria-label="Fechar">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <form id="reciboEntregaForm">
                <div class="tf-modal-corpo">

                    <div class="tf-info-grade" style="margin-bottom:16px">
                        <div style="grid-column:1/-1">
                            <label class="tf-rotulo" for="receptor">
                                Recebedor <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <input type="text" class="tf-input" id="receptor" name="receptor" required
                                   placeholder="Nome de quem está retirando">
                        </div>

                        <div>
                            <label class="tf-rotulo" for="dataEntrega">
                                Data da entrega <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <input type="datetime-local" class="tf-input" id="dataEntrega"
                                   name="dataEntrega" required>
                        </div>

                        <div>
                            <label class="tf-rotulo" for="entregador">
                                Entregue por <span style="color:var(--tf-perigo)">*</span>
                            </label>
                            <input type="text" class="tf-input" id="entregador" name="entregador" required>
                        </div>
                    </div>

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-list-ul"></i> Documentos entregues
                        </div>
                        <textarea class="tf-textarea" id="documentos" name="documentos" rows="5"
                                  placeholder="Um documento por linha."></textarea>
                    </div>

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-commenting-o"></i> Observações
                        </div>
                        <textarea class="tf-textarea" id="observacoes" name="observacoes" rows="3"
                                  placeholder="Ressalvas ou condições da entrega."></textarea>
                    </div>

                </div>

                <div class="tf-modal-rodape">
                    <button type="button" class="tf-btn" data-dismiss="modal" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="tf-btn tf-btn-primario">
                        <i class="fa fa-print"></i> Gerar e imprimir
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- ==================== Arquivar Ato ==================== -->
<div class="modal fade" id="modalArquivarAto" tabindex="-1" role="dialog"
     aria-labelledby="modalArquivarAtoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl tf-app" role="document">
        <div class="modal-content tf-modal-conteudo">

            <div class="tf-modal-topo">
                <h5 id="modalArquivarAtoLabel">
                    <i class="fa fa-archive"></i> Arquivar ato
                </h5>
                <span class="tf-mini" style="opacity:.85">
                    envia o ato e os anexos para o módulo de Arquivamento
                </span>
                <button type="button" class="tf-fechar" data-dismiss="modal"
                        data-bs-dismiss="modal" aria-label="Fechar">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <form id="arquivarAtoForm" enctype="multipart/form-data">
                <div class="tf-modal-corpo">

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-institution"></i> Identificação do ato
                        </div>

                        <div class="tf-info-grade" style="margin-bottom:0">
                            <div>
                                <label class="tf-rotulo" for="arq_atribuicao">
                                    Atribuição <span style="color:var(--tf-perigo)">*</span>
                                </label>
                                <select id="arq_atribuicao" name="atribuicao" class="tf-select" required>
                                    <option value="">Selecione…</option>
                                    <option value="Registro Civil">Registro Civil</option>
                                    <option value="Registro de Imóveis">Registro de Imóveis</option>
                                    <option value="Registro de Títulos e Documentos">Registro de Títulos e Documentos</option>
                                    <option value="Registro Civil das Pessoas Jurídicas">Registro Civil das Pessoas Jurídicas</option>
                                    <option value="Notas">Notas</option>
                                    <option value="Protesto">Protesto</option>
                                    <option value="Contratos Marítimos">Contratos Marítimos</option>
                                    <option value="Administrativo">Administrativo</option>
                                </select>
                            </div>

                            <div>
                                <label class="tf-rotulo" for="arq_categoria">
                                    Categoria <span style="color:var(--tf-perigo)">*</span>
                                </label>
                                <select id="arq_categoria" name="categoria" class="tf-select" required>
                                    <option value="">Carregando…</option>
                                </select>
                            </div>

                            <div>
                                <label class="tf-rotulo" for="arq_data_ato">
                                    Data do ato <span style="color:var(--tf-perigo)">*</span>
                                </label>
                                <input type="date" class="tf-input" id="arq_data_ato" name="data_ato" required>
                            </div>

                            <div>
                                <label class="tf-rotulo" for="arq_protocolo">Protocolo</label>
                                <input type="text" class="tf-input" id="arq_protocolo" name="protocolo" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-book"></i> Localização no acervo
                        </div>

                        <div class="tf-info-grade" style="margin-bottom:0">
                            <div>
                                <label class="tf-rotulo" for="arq_livro">Livro</label>
                                <input type="text" class="tf-input" id="arq_livro" name="livro">
                            </div>
                            <div>
                                <label class="tf-rotulo" for="arq_folha">Folha</label>
                                <input type="text" class="tf-input" id="arq_folha" name="folha">
                            </div>
                            <div>
                                <label class="tf-rotulo" for="arq_termo">Termo</label>
                                <input type="text" class="tf-input" id="arq_termo" name="termo">
                            </div>
                            <div>
                                <label class="tf-rotulo" for="arq_matricula">Matrícula</label>
                                <input type="text" class="tf-input" id="arq_matricula" name="matricula">
                            </div>
                        </div>
                    </div>

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-users"></i> Partes envolvidas
                        </div>

                        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px">
                            <div style="flex:0 0 190px">
                                <label class="tf-rotulo" for="arq_cpf">CPF / CNPJ</label>
                                <input type="text" class="tf-input" id="arq_cpf" placeholder="somente números">
                            </div>
                            <div style="flex:1;min-width:200px">
                                <label class="tf-rotulo" for="arq_nome_parte">Nome</label>
                                <input type="text" class="tf-input" id="arq_nome_parte" placeholder="Nome completo">
                            </div>
                            <button type="button" id="arq_add_parte" class="tf-btn tf-btn-primario">
                                <i class="fa fa-user-plus"></i> Adicionar
                            </button>
                        </div>

                        <div class="tf-tabela-caixa">
                            <table class="tf-tabela">
                                <thead>
                                    <tr>
                                        <th class="tf-sem-ordem" style="width:190px">CPF/CNPJ</th>
                                        <th class="tf-sem-ordem">Nome</th>
                                        <th class="tf-sem-ordem" style="width:70px">Ação</th>
                                    </tr>
                                </thead>
                                <tbody id="arq_partes_table"></tbody>
                            </table>
                        </div>
                        <p class="tf-mini tf-mudo" style="margin:8px 0 0">
                            O CPF/CNPJ é conferido pelo dígito verificador antes de entrar na lista.
                            Deixe em branco quando não houver.
                        </p>
                    </div>

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-align-left"></i> Descrição e detalhes
                        </div>
                        <textarea class="tf-textarea" id="arq_descricao" name="descricao" rows="4"></textarea>
                    </div>

                    <div class="tf-bloco">
                        <div class="tf-bloco-titulo">
                            <i class="fa fa-paperclip"></i> Documentos a arquivar
                        </div>

                        <p class="tf-mini tf-mudo" style="margin:0 0 10px">
                            Marque quais anexos da tarefa devem ir para o arquivo e acrescente outros
                            se precisar.
                        </p>

                        <div id="arq_attachments_list" style="margin-bottom:12px"></div>

                        <div class="tf-zona-upload" id="arq_zona_upload">
                            <i class="fa fa-cloud-upload"></i>
                            <strong>Arraste arquivos aqui</strong> ou clique para selecionar
                            <input type="file" id="arq_file_input" name="file-input[]" multiple
                                   style="display:none">
                        </div>
                        <div class="tf-anexos" id="arq_novos_arquivos" style="margin-top:12px"></div>
                    </div>

                </div>

                <div class="tf-modal-rodape">
                    <button type="button" class="tf-btn" data-dismiss="modal" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancelar
                    </button>
                    <button type="submit" id="arq_submit_btn" class="tf-btn tf-btn-primario">
                        <i class="fa fa-archive"></i> Arquivar ato
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
