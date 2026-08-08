<?php
/**
 * Atlas · Tarefas — tela principal do módulo.
 *
 * Reúne em uma única página as cinco visões do acervo: Painel (indicadores),
 * Cards, Kanban, Lista e Calendário. Substitui os quatro arquivos quase
 * idênticos da versão anterior (index.php, index_tarefa.php,
 * consulta-tarefas.php e consulta-tarefas-sub.php), que continuam existindo
 * como atalhos para esta página — nenhum link de outro módulo quebra.
 *
 * Abertura direta continua funcionando:
 *   index.php?token=<hash>   abre a tarefa no modal
 *   index.php?id=<protocolo> idem, pelo número do protocolo geral
 */

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/gemini.php';
exigir_login();

$usuario      = usuario_atual();
$categorias   = listar_categorias();
$origens      = listar_origens();
$funcionarios = listar_funcionarios();
$statusLista  = array_keys(tarefas_status_catalogo());
$prioridades  = array_keys(tarefas_prioridades());

$migracaoPendente = !db_tem_tabela('tarefas_ia_modelos');
$iaAtiva = !$migracaoPendente && ia_disponivel();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas · Controle de Tarefas</title>

    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/tarefas.css?v=2.0.8">

    <script src="../script/jquery-3.5.1.min.js"></script>
    <?php
    /* Estilos compartilhados do Atlas — mantidos para os modais legados. */
    if (file_exists(__DIR__ . '/../style/style_tarefas.php')) {
        include(__DIR__ . '/../style/style_tarefas.php');
    }
    ?>
</head>

<body class="light-mode">

<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container-fluid tf-app">

        <?php if ($migracaoPendente): ?>
        <div class="tf-painel" style="border-color:#f59e0b;background:rgba(245,158,11,.08);padding:14px 18px;margin-bottom:16px">
            <strong><i class="fa fa-database"></i> Migração pendente.</strong>
            Os recursos novos (histórico, checklist e assistente de IA) só ficam disponíveis
            depois de executar a migração. Nada do acervo atual é alterado por ela.
            <a href="migracao_v2.php" class="tf-btn tf-btn-sm tf-btn-primario" style="margin-left:10px">
                <i class="fa fa-play"></i> Executar agora
            </a>
        </div>
        <?php endif; ?>

        <!-- ============================ CABEÇALHO ============================ -->
        <div class="tf-topo">
            <div class="tf-topo-icone"><i class="fa fa-tasks"></i></div>
            <div>
                <h1>Controle de Tarefas</h1>
                <div class="tf-sub">
                    <?php echo e($usuario['nome']); ?> ·
                    <?php echo usuario_ve_tudo() ? 'acesso completo ao acervo' : 'suas tarefas e as já concluídas'; ?>
                </div>
            </div>

            <div class="tf-topo-acoes">
                <div class="tf-visoes">
                    <button class="tf-visao" data-visao="painel"><i class="fa fa-dashboard"></i> Painel</button>
                    <button class="tf-visao" data-visao="cards"><i class="fa fa-th-large"></i> Cards</button>
                    <button class="tf-visao" data-visao="kanban"><i class="fa fa-columns"></i> Kanban</button>
                    <button class="tf-visao" data-visao="lista"><i class="fa fa-list"></i> Lista</button>
                    <button class="tf-visao" data-visao="calendario"><i class="fa fa-calendar"></i> Calendário</button>
                </div>

                <a href="criar-tarefa.php" class="tf-btn tf-btn-primario">
                    <i class="fa fa-plus"></i> Nova tarefa
                </a>

                <?php if (usuario_ve_tudo()): ?>
                <a href="configuracoes-ia.php" class="tf-btn" title="Configurações da IA">
                    <i class="fa fa-magic"></i>
                </a>
                <a href="categorias.php" class="tf-btn" title="Categorias e origens">
                    <i class="fa fa-tags"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================ INDICADORES ========================== -->
        <div class="tf-kpis" id="tfKpis"></div>

        <!-- ============================ BUSCA =============================== -->
        <div class="tf-painel">
            <div class="tf-busca">
                <div class="tf-busca-campo">
                    <i class="fa fa-search"></i>
                    <input type="text" class="tf-input" id="tfBusca"
                           placeholder="Buscar por protocolo, título, apresentante, responsável, ofício… (tecle / para focar)">
                </div>

                <?php if ($iaAtiva): ?>
                <button class="tf-btn tf-btn-ia" id="tfBtnBuscaIA"
                        title="Interpretar a busca em linguagem natural com a IA">
                    <i class="fa fa-magic"></i> Buscar com IA
                </button>
                <?php endif; ?>

                <button class="tf-btn" id="tfBtnFiltros">
                    <i class="fa fa-sliders"></i> Filtros
                    <span class="tf-selo tf-selo-status" id="tfContadorFiltros"
                          style="--tf-cor:var(--tf-primaria);display:none"></span>
                    <i class="fa fa-angle-down"></i>
                </button>

                <button class="tf-btn tf-btn-icone" id="tfBtnAtualizar" title="Atualizar">
                    <i class="fa fa-refresh"></i>
                </button>
                <button class="tf-btn tf-btn-icone" id="tfBtnExportar" title="Exportar resultados em CSV">
                    <i class="fa fa-download"></i>
                </button>
            </div>

            <div class="tf-chips">
                <span class="tf-chip" data-atalho="vencida"><i class="fa fa-exclamation-triangle"></i> Vencidas</span>
                <span class="tf-chip" data-atalho="hoje"><i class="fa fa-calendar-check-o"></i> Vencem hoje</span>
                <span class="tf-chip" data-atalho="semana"><i class="fa fa-calendar"></i> Próximos 7 dias</span>
                <span class="tf-chip" data-atalho="minhas"><i class="fa fa-user"></i> Minhas</span>
                <span class="tf-chip" data-atalho="sem_responsavel"><i class="fa fa-user-times"></i> Sem responsável</span>
            </div>

            <!-- Filtros detalhados -->
            <form id="tfFormFiltros" class="tf-filtros" style="display:none">
                <div>
                    <label class="tf-rotulo">Protocolo geral</label>
                    <input type="text" class="tf-input" name="protocol" placeholder="Ex.: 1234">
                </div>
                <div>
                    <label class="tf-rotulo">Título</label>
                    <input type="text" class="tf-input" name="title">
                </div>
                <div>
                    <label class="tf-rotulo">Categoria</label>
                    <select class="tf-select" name="category">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?php echo e($c['id']); ?>"><?php echo e($c['titulo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="tf-rotulo">Origem</label>
                    <select class="tf-select" name="origin">
                        <option value="">Todas</option>
                        <?php foreach ($origens as $o): ?>
                            <option value="<?php echo e($o['id']); ?>"><?php echo e($o['titulo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="tf-rotulo">Responsável</label>
                    <select class="tf-select" name="employee">
                        <option value="">Todos</option>
                        <?php foreach ($funcionarios as $f): ?>
                            <option value="<?php echo e($f['nome_completo']); ?>"><?php echo e($f['nome_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="tf-rotulo">Revisor</label>
                    <select class="tf-select" name="revisor">
                        <option value="">Todos</option>
                        <?php foreach ($funcionarios as $f): ?>
                            <option value="<?php echo e($f['nome_completo']); ?>"><?php echo e($f['nome_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="tf-rotulo">Status</label>
                    <select class="tf-select" name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statusLista as $s): ?>
                            <option value="<?php echo e($s); ?>"><?php echo e($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="tf-rotulo">Prioridade</label>
                    <select class="tf-select" name="priority">
                        <option value="">Todas</option>
                        <?php foreach ($prioridades as $p): ?>
                            <option value="<?php echo e($p); ?>"><?php echo e($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="tf-rotulo">Prazo de</label>
                    <input type="date" class="tf-input" name="dateStart">
                </div>
                <div>
                    <label class="tf-rotulo">Prazo até</label>
                    <input type="date" class="tf-input" name="dateEnd">
                </div>
                <div>
                    <label class="tf-rotulo">Descrição contém</label>
                    <input type="text" class="tf-input" name="description">
                </div>
                <div>
                    <label class="tf-rotulo">Nº do ofício</label>
                    <input type="text" class="tf-input" name="oficio">
                </div>

                <div class="tf-filtros-rodape">
                    <button type="button" class="tf-btn" id="tfBtnLimpar">
                        <i class="fa fa-eraser"></i> Limpar
                    </button>
                    <button type="submit" class="tf-btn tf-btn-primario">
                        <i class="fa fa-filter"></i> Aplicar filtros
                    </button>
                </div>
            </form>
        </div>

        <!-- ============================ RESULTADOS =========================== -->
        <div id="tfBarraResultados"
             style="display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap">
            <span class="tf-forte" id="tfTotalResultados">—</span>
            <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <label class="tf-mini tf-mudo" style="margin:0">Ordenar</label>
                <select class="tf-select" id="tfOrdenar" style="width:auto">
                    <option value="protocolo">Protocolo</option>
                    <option value="data">Data limite</option>
                    <option value="criacao">Data de criação</option>
                    <option value="prioridade">Prioridade</option>
                    <option value="funcionario">Responsável</option>
                    <option value="titulo">Título</option>
                    <option value="status">Status</option>
                </select>
                <select class="tf-select" id="tfDirecao" style="width:auto">
                    <option value="desc">Decrescente</option>
                    <option value="asc">Crescente</option>
                </select>
                <select class="tf-select" id="tfPorPagina" style="width:auto">
                    <option value="24">24 por página</option>
                    <option value="48">48 por página</option>
                    <option value="96">96 por página</option>
                </select>
            </div>
        </div>

        <div id="tfConteudo">
            <!-- Painel -->
            <div id="tfPainel" class="tf-oculto">
                <div class="tf-graficos" id="tfGraficos"></div>
                <div class="tf-graficos" id="tfAtencao"></div>
            </div>

            <!-- Cards -->
            <div id="tfCards" class="tf-grade tf-oculto"></div>

            <!-- Kanban -->
            <div id="tfKanban" class="tf-kanban tf-oculto"></div>

            <!-- Lista -->
            <div id="tfLista" class="tf-oculto"></div>

            <!-- Calendário -->
            <div id="tfCalendario" class="tf-oculto"></div>

            <div class="tf-paginacao" id="tfPaginacao"></div>
        </div>

    </div>
</div>

<!-- ============================ AÇÕES EM LOTE ============================ -->
<div class="tf-lote tf-app" id="tfLote">
    <span id="tfLoteContador" class="tf-forte">0 tarefas selecionadas</span>
    <select id="tfLoteOperacao">
        <option value="">Operação…</option>
        <option value="status">Alterar status</option>
        <option value="responsavel">Definir responsável</option>
        <option value="prioridade">Alterar prioridade</option>
    </select>
    <select id="tfLoteValor"><option value="">Selecione…</option></select>
    <button class="tf-btn tf-btn-sm tf-btn-sucesso" id="tfLoteAplicar">
        <i class="fa fa-check"></i> Aplicar
    </button>
    <button class="tf-btn tf-btn-sm" id="tfLoteLimpar">
        <i class="fa fa-times"></i>
    </button>
</div>

<!-- ============================ MODAL DE DETALHE ========================= -->
<div class="modal fade" id="tfModalDetalhe" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl tf-app" role="document">
        <!--
             A classe `modal-content` é obrigatória: o Bootstrap aplica
             `pointer-events: none` em `.modal-dialog` e só devolve os cliques
             em `.modal-content`. Sem ela, nada dentro do modal recebe clique
             nem rolagem, e qualquer clique atravessa para o fundo e fecha a
             janela.
        -->
        <div class="modal-content tf-modal-conteudo">

            <div class="tf-modal-topo">
                <h5>
                    <i class="fa fa-tasks"></i>
                    Protocolo Geral nº <span id="tfDetalheProtocolo">—</span>
                </h5>
                <span class="tf-mini" style="opacity:.85" id="tfDetalheTitulo"></span>
                <button type="button" class="tf-fechar" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Fechar">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <div class="tf-barra-acoes">
                <button class="tf-btn tf-btn-sm" id="tfAcaoProtocolo">
                    <i class="fa fa-print"></i> Protocolo geral
                </button>
                <button class="tf-btn tf-btn-sm" id="tfAcaoGuia">
                    <i class="fa fa-file-text-o"></i> Guia de recebimento
                </button>
                <button class="tf-btn tf-btn-sm" id="tfAcaoRecibo">
                    <i class="fa fa-file-o"></i> Recibo de entrega
                </button>
                <button class="tf-btn tf-btn-sm" id="tfAcaoOficio">
                    <i class="fa fa-link"></i> Vincular ofício
                </button>
                <button class="tf-btn tf-btn-sm" id="tfAcaoCriarOficio">
                    <i class="fa fa-plus"></i> Criar ofício
                </button>
                <button class="tf-btn tf-btn-sm" id="tfAcaoSubtarefa">
                    <i class="fa fa-sitemap"></i> Subtarefa
                </button>
                <button class="tf-btn tf-btn-sm" id="tfAcaoArquivar">
                    <i class="fa fa-archive"></i> Arquivar ato
                </button>
                <button class="tf-btn tf-btn-sm tf-btn-primario" id="tfAcaoEditar">
                    <i class="fa fa-pencil"></i> Editar
                </button>
                <button class="tf-btn tf-btn-sm tf-btn-perigo" id="tfAcaoExcluir">
                    <i class="fa fa-trash-o"></i> Excluir
                </button>
            </div>

            <div class="tf-abas">
                <button class="tf-aba tf-ativo" data-aba="geral">
                    <i class="fa fa-info-circle"></i> Visão geral
                </button>
                <button class="tf-aba" data-aba="anexos" id="tfAbaAnexos">
                    <i class="fa fa-paperclip"></i> Anexos <span class="tf-aba-contador">0</span>
                </button>
                <button class="tf-aba" data-aba="checklist" id="tfAbaCheck">
                    <i class="fa fa-check-square-o"></i> Checklist <span class="tf-aba-contador">0</span>
                </button>
                <button class="tf-aba" data-aba="tempo" id="tfAbaTempo">
                    <i class="fa fa-history"></i> Andamento <span class="tf-aba-contador">0</span>
                </button>
                <button class="tf-aba" data-aba="ia">
                    <i class="fa fa-magic"></i> Assistente
                </button>
                <button class="tf-aba" data-aba="historico" id="tfAbaHist">
                    <i class="fa fa-clock-o"></i> Alterações <span class="tf-aba-contador">0</span>
                </button>
            </div>

            <div class="tf-modal-corpo" id="tfDetalheCorpo"></div>

        </div>
    </div>
</div>

<?php
/*
 * Modais auxiliares herdados da versão anterior: comentário, vínculo de
 * ofício, recibo de entrega, guia de recebimento, histórico de guias,
 * criação de subtarefa e arquivamento do ato.
 *
 * Foram mantidos com os mesmos IDs de campo, para que o back-end e o módulo
 * de Arquivamento continuem recebendo exatamente o que esperam. O que mudou
 * foi apenas o JavaScript que os controla.
 */
include(__DIR__ . '/partials/_modais_legado.php');
?>

<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script src="../script/toastr.min.js"></script>

<script>
/* Configuração entregue ao front-end. */
window.TarefasConfig = {
    csrf: <?php echo json_encode(csrf_token()); ?>,
    usuario: <?php echo json_encode($usuario['usuario'], JSON_UNESCAPED_UNICODE); ?>,
    nome: <?php echo json_encode($usuario['nome'], JSON_UNESCAPED_UNICODE); ?>,
    admin: <?php echo usuario_ve_tudo() ? 'true' : 'false'; ?>,
    ia_ativa: <?php echo $iaAtiva ? 'true' : 'false'; ?>,
    status: <?php echo json_encode($statusLista, JSON_UNESCAPED_UNICODE); ?>,
    prioridades: <?php echo json_encode($prioridades, JSON_UNESCAPED_UNICODE); ?>,
    funcionarios: <?php echo json_encode(array_column($funcionarios, 'nome_completo'), JSON_UNESCAPED_UNICODE); ?>
};
</script>

<script src="assets/js/tarefas-core.js?v=2.0.8"></script>
<script src="assets/js/tarefas-calendario.js?v=2.0.8"></script>
<script src="assets/js/tarefas-detalhe.js?v=2.0.8"></script>
<script src="assets/js/tarefas-documentos.js?v=2.0.8"></script>

<script>
jQuery(function ($) {
    if (window.toastr) {
        toastr.options = {
            positionClass: 'toast-bottom-right',
            timeOut: 3200,
            progressBar: true,
            preventDuplicates: true
        };
    }
    Tarefas.iniciar();
});
</script>

</body>
</html>
