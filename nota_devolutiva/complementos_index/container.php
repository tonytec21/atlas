<?php
$hasFilter = false;
foreach (['numero','protocolo','data','apresentante','cpf_cnpj','titulo','origem_titulo','data_protocolo','status'] as $f) {
    if (!empty($_GET[$f])) { $hasFilter = true; break; }
}
function nd_val($k){ return isset($_GET[$k]) ? htmlspecialchars($_GET[$k], ENT_QUOTES) : ''; }
?>
<div id="main" class="main-content">
    <div class="container">

        <!-- HERO / TÍTULO -->
        <section class="page-hero">
            <div class="title-row">
                <div class="title-icon"><i class="fa fa-reply"></i></div>
                <div>
                    <h1>Pesquisa de Notas Devolutivas</h1>
                    <div class="subtitle muted">Consulta e gestão de notas devolutivas, com filtros rápidos, assinatura digital e anexos.</div>
                    <?php if (!$hasFilter): ?>
                        <div class="mt-2"><span class="chip"><i class="fa fa-info-circle"></i> Use os filtros para refinar a pesquisa.</span></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- FILTROS -->
        <form id="searchForm" method="GET" class="filter-card">
            <div class="section-title">Filtros de pesquisa</div>
            <div class="section-sub">Refine por número, protocolo, datas, apresentante, CPF/CNPJ, título, origem e status.</div>

            <div class="row">
                <div class="col-6 col-md-2 mb-3">
                    <label for="numero" class="form-label small text-muted mb-1">Número</label>
                    <div class="input-chip"><i class="fa fa-hashtag"></i>
                        <input type="text" id="numero" name="numero" placeholder="Número" value="<?php echo nd_val('numero'); ?>"></div>
                </div>
                <div class="col-6 col-md-2 mb-3">
                    <label for="protocolo" class="form-label small text-muted mb-1">Protocolo</label>
                    <div class="input-chip"><i class="fa fa-file-text-o"></i>
                        <input type="text" id="protocolo" name="protocolo" placeholder="Protocolo" value="<?php echo nd_val('protocolo'); ?>"></div>
                </div>
                <div class="col-6 col-md-2 mb-3">
                    <label for="data" class="form-label small text-muted mb-1">Data</label>
                    <div class="input-chip"><i class="fa fa-calendar"></i>
                        <input type="date" id="data" name="data" value="<?php echo nd_val('data'); ?>"></div>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label for="apresentante" class="form-label small text-muted mb-1">Apresentante</label>
                    <div class="input-chip"><i class="fa fa-user"></i>
                        <input type="text" id="apresentante" name="apresentante" placeholder="Apresentante" value="<?php echo nd_val('apresentante'); ?>"></div>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label for="cpf_cnpj" class="form-label small text-muted mb-1">CPF/CNPJ</label>
                    <div class="input-chip"><i class="fa fa-id-card-o"></i>
                        <input type="text" id="cpf_cnpj" name="cpf_cnpj" placeholder="CPF/CNPJ" value="<?php echo nd_val('cpf_cnpj'); ?>"></div>
                </div>
                <div class="col-12 col-md-4 mb-3">
                    <label for="titulo" class="form-label small text-muted mb-1">Título</label>
                    <div class="input-chip"><i class="fa fa-bookmark-o"></i>
                        <input type="text" id="titulo" name="titulo" placeholder="Título" value="<?php echo nd_val('titulo'); ?>"></div>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label for="origem_titulo" class="form-label small text-muted mb-1">Origem do Título</label>
                    <div class="input-chip"><i class="fa fa-map-marker"></i>
                        <input type="text" id="origem_titulo" name="origem_titulo" placeholder="Origem do título" value="<?php echo nd_val('origem_titulo'); ?>"></div>
                </div>
                <div class="col-6 col-md-2 mb-3">
                    <label for="data_protocolo" class="form-label small text-muted mb-1">Data do Protocolo</label>
                    <div class="input-chip"><i class="fa fa-calendar-check-o"></i>
                        <input type="date" id="data_protocolo" name="data_protocolo" value="<?php echo nd_val('data_protocolo'); ?>"></div>
                </div>
                <div class="col-12 col-md-3 mb-3">
                    <label for="status" class="form-label small text-muted mb-1">Status</label>
                    <div class="input-chip"><i class="fa fa-flag"></i>
                        <select id="status" name="status" style="border:none;outline:none;width:100%;background:transparent;color:inherit;">
                            <option value="">Todos</option>
                            <?php foreach ($statusOptions as $statusName => $statusColor): ?>
                                <option value="<?php echo htmlspecialchars($statusName, ENT_QUOTES); ?>" <?php echo (isset($_GET['status']) && $_GET['status'] === $statusName) ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="filter-actions mt-2">
                <button type="submit" class="btn btn-primary btn-pill"><i class="fa fa-filter"></i> Filtrar</button>
                <button type="button" class="btn btn-success btn-pill" onclick="window.location.href='cadastrar-nota-devolutiva.php'"><i class="fa fa-plus"></i> Nova Nota Devolutiva</button>
                <?php if ($hasFilter): ?>
                    <a href="index.php" class="btn btn-soft btn-pill"><i class="fa fa-times"></i> Limpar filtros</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- RESULTADOS -->
        <div class="table-responsive table-wrap mt-3">
            <h5 class="mb-2">Resultados da Pesquisa</h5>
            <table id="tabelaNotas" class="table table-striped table-bordered data-layout" style="width:100%">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Data</th>
                        <th>Título</th>
                        <th>Apresentante</th>
                        <th>CPF/CNPJ</th>
                        <th>Protocolo</th>
                        <th>Status</th>
                        <th style="width:12%">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notas as $nota) :
                        $status = isset($nota['status']) ? $nota['status'] : 'Pendente';
                        $statusClass = 'status-pendente';
                        switch ($status) {
                            case 'Exigência Cumprida': $statusClass = 'status-exigencia-cumprida'; break;
                            case 'Exigência Não Cumprida': $statusClass = 'status-exigencia-nao-cumprida'; break;
                            case 'Prazo Expirado': $statusClass = 'status-prazo-expirado'; break;
                            case 'Em Análise': $statusClass = 'status-em-analise'; break;
                            case 'Cancelada': $statusClass = 'status-cancelada'; break;
                            case 'Aguardando Documentação': $statusClass = 'status-aguardando-documentacao'; break;
                        }
                        $assinado = !empty($nota['assinado']);
                        // chave de ordenação numérica do número (ex.: 2/2026 -> 2026000002)
                        $ordKey = (int)$nota['id'];
                        if (preg_match('~^\s*(\d+)\s*/\s*(\d+)\s*$~', (string)$nota['numero'], $mk)) { $ordKey = ((int)$mk[2]) * 1000000 + (int)$mk[1]; }
                        elseif (preg_match('~(\d+)~', (string)$nota['numero'], $mk)) { $ordKey = (int)$mk[1]; }
                    ?>
                        <tr>
                            <td data-label="Número" data-order="<?php echo $ordKey; ?>"><?php echo htmlspecialchars($nota['numero']); ?></td>
                            <td data-label="Data" data-order="<?php echo date('Y-m-d', strtotime($nota['data'])); ?>"><?php echo date('d/m/Y', strtotime($nota['data'])); ?></td>
                            <td data-label="Título" title="<?php echo htmlspecialchars($nota['titulo']); ?>"><?php echo htmlspecialchars($nota['titulo']); ?></td>
                            <td data-label="Apresentante"><?php echo htmlspecialchars($nota['apresentante']); ?></td>
                            <td data-label="CPF/CNPJ"><?php echo htmlspecialchars($nota['cpf_cnpj'] ?? ''); ?></td>
                            <td data-label="Protocolo"><?php echo htmlspecialchars($nota['protocolo']); ?></td>
                            <td data-label="Status"><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                            <td data-cell="acoes">
                                    <button class="btn btn-info btn-sm btn-table" onclick="viewNota('<?php echo htmlspecialchars($nota['numero'], ENT_QUOTES); ?>')" title="Visualizar"><i class="fa fa-eye"></i></button>
                                    <?php if (!$assinado): ?>
                                        <button class="btn btn-warning btn-sm btn-table" onclick="editNota('<?php echo htmlspecialchars($nota['numero'], ENT_QUOTES); ?>')" title="Editar"><i class="fa fa-pencil"></i></button>
                                        <a class="btn btn-success btn-sm btn-table" href="assinar-nota.php?numero=<?php echo rawurlencode($nota['numero']); ?>" title="Assinar digitalmente"><i class="fa fa-pencil-square-o"></i></a>
                                    <?php else: ?>
                                        <a class="btn btn-secondary btn-sm btn-table" href="view_signed_nota.php?numero=<?php echo rawurlencode($nota['numero']); ?>" target="_blank" title="Ver PDF assinado"><i class="fa fa-file-pdf-o"></i></a>
                                    <?php endif; ?>
                                    <button class="btn btn-primary btn-sm btn-table" onclick="abrirAnexos('<?php echo htmlspecialchars($nota['numero'], ENT_QUOTES); ?>')" title="Anexos"><i class="fa fa-paperclip"></i></button>
                                <?php if ($assinado): ?><span class="btn btn-sm btn-table acao-assinada" title="Assinada digitalmente"><i class="fa fa-lock"></i></span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>
