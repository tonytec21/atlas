<?php
// pedidos_certidao/index.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/* ============================================================
   0) MIGRAÇÃO – cria tabelas caso não existam (execução silenciosa)
   ============================================================ */
function ensureSchema(PDO $conn) {
    $sqls = [];

    // Tabela principal de pedidos
    $sqls[] = <<<SQL
CREATE TABLE IF NOT EXISTS pedidos_certidao (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  protocolo         VARCHAR(32)  NOT NULL UNIQUE,
  token_publico     CHAR(40)     NOT NULL UNIQUE,
  atribuicao        VARCHAR(20)  NOT NULL,
  tipo              VARCHAR(50)  NOT NULL,
  status            ENUM('pendente','em_andamento','emitida','entregue','cancelada') NOT NULL DEFAULT 'pendente',

  requerente_nome   VARCHAR(255) NOT NULL,
  requerente_doc    VARCHAR(32)  NULL,
  requerente_email  VARCHAR(120) NULL,
  requerente_tel    VARCHAR(30)  NULL,

  portador_nome     VARCHAR(255) NULL,
  portador_doc      VARCHAR(32)  NULL,

  referencias_json  JSON         NULL,
  base_calculo      DECIMAL(12,2) DEFAULT 0,
  total_os          DECIMAL(12,2) DEFAULT 0,
  ordem_servico_id  INT          NULL,

  anexo_pdf_path    VARCHAR(500) NULL,
  retirado_por      VARCHAR(255) NULL,
  cancelado_motivo  VARCHAR(500) NULL,

  criado_por        VARCHAR(120) NOT NULL,
  atualizado_por    VARCHAR(120) NULL,
  criado_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status (status),
  INDEX idx_protocolo (protocolo),
  INDEX idx_token_publico (token_publico),
  INDEX idx_os (ordem_servico_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    // Log de transição de status
    $sqls[] = <<<SQL
CREATE TABLE IF NOT EXISTS pedidos_certidao_status_log (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id      INT NOT NULL,
  status_anterior ENUM('pendente','em_andamento','emitida','entregue','cancelada') NULL,
  novo_status     ENUM('pendente','em_andamento','emitida','entregue','cancelada') NOT NULL,
  observacao      VARCHAR(500) NULL,
  usuario         VARCHAR(255) NOT NULL,
  ip              VARCHAR(45)  NULL,
  user_agent      VARCHAR(255) NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pedido (pedido_id),
  CONSTRAINT fk_pedido_statuslog FOREIGN KEY (pedido_id)
    REFERENCES pedidos_certidao(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    // Outbox para integração online (assíncrona)
    $sqls[] = <<<SQL
CREATE TABLE IF NOT EXISTS api_outbox (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  topic         ENUM('pedido_criado','status_atualizado') NOT NULL,
  protocolo     VARCHAR(32)  NOT NULL,
  token_publico CHAR(40)     NOT NULL,
  payload_json  JSON         NOT NULL,
  api_key       VARCHAR(120) NULL,
  signature     VARCHAR(256) NULL,
  timestamp_utc BIGINT       NOT NULL,
  request_id    VARCHAR(64)  NOT NULL,
  delivered_at  DATETIME     NULL,
  retries       INT          NOT NULL DEFAULT 0,
  last_error    VARCHAR(1000) NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_topic (topic),
  INDEX idx_protocolo (protocolo),
  INDEX idx_token (token_publico)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    foreach ($sqls as $sql) {
        $conn->exec($sql);
    }
}

try {
    $conn = getDatabaseConnection();
    ensureSchema($conn);
} catch (Throwable $e) {
    // silencioso
}

/* ============================================================
   1) Busca dataset para listar (já com pendência de API)
   ============================================================ */
$stmt = $conn->query("
  SELECT p.*,
         (SELECT COUNT(*) FROM pedidos_certidao_status_log s WHERE s.pedido_id = p.id) as logs,
         (SELECT COUNT(*) FROM api_outbox o
           WHERE o.protocolo = p.protocolo
             AND o.token_publico = p.token_publico
             AND o.delivered_at IS NULL) AS pend_api,
         (SELECT MAX(o.last_error) FROM api_outbox o
           WHERE o.protocolo = p.protocolo
             AND o.token_publico = p.token_publico
             AND o.delivered_at IS NULL) AS last_api_error
    FROM pedidos_certidao p
ORDER BY p.criado_em DESC
");
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Lista de tipos disponíveis para o filtro (distinct) */
$tipos = [];
try {
    $tq = $conn->query("SELECT DISTINCT tipo FROM pedidos_certidao WHERE tipo IS NOT NULL AND tipo <> '' ORDER BY tipo ASC");
    $tipos = $tq->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $tipos = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedidos de Certidão</title>
<link rel="stylesheet" href="../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css"><!-- Font Awesome -->
<link rel="stylesheet" href="../style/css/style.css">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
<?php if (file_exists(__DIR__ . '/../style/sweetalert2.min.css')): ?>
<link rel="stylesheet" href="../style/sweetalert2.min.css">
<?php else: ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<?php endif; ?>
<style>
/* ====== Design tokens/ajustes gerais ====== */
:root{
  --radius: 6px;
  --shadow: 0 6px 16px rgba(0,0,0,.06);
  --shadow-strong: 0 10px 22px rgba(0,0,0,.08);
}
.btn, .form-control, .custom-select, .input-group-text {
  border-radius: var(--radius) !important;
}
.btn{
  font-weight: 600;
  padding: .48rem .85rem;
}
.btn i{ margin-right:.45rem; }

/* ====== Responsividade: tabela no desktop, cards no mobile ====== */
@media (min-width: 992px){ .desktop-table { display:block; } .mobile-cards { display:none; } }
@media (max-width: 991.98px){ .desktop-table { display:none; } .mobile-cards { display:block; } }

/* ====== Hero ====== */
.page-hero{
  background: linear-gradient(135deg, rgba(13,110,253,.08), rgba(0,0,0,0));
  border-radius:16px; padding:18px 20px; margin: 8px 0 22px;
  box-shadow: var(--shadow);
}
.title-row{ display:flex; align-items:center; gap:12px; }
.title-icon{
  width:48px; height:48px; border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  background: rgba(13,110,253,.12);
}
.title-icon i{ font-size:22px; color:#0d6efd; }

/* ====== Barra de filtros ====== */
.filter-card{
  border-radius:16px; box-shadow: var(--shadow);
  padding: 16px;
}
.filter-actions{
  display:flex; gap:10px; align-items:center; justify-content:flex-end;
}
@media (max-width: 991.98px){
  .filter-actions{ justify-content:stretch; flex-wrap:wrap; }
  .filter-actions .btn{ flex:1; }
}

/* ====== Badges de status ====== */
.badge-status {
  font-size:.82rem; padding:.45em .6em; border-radius:6px;
}
.status-pendente    { background:#ffd65a; color:#3b3b3b; text-transform: uppercase}
.status-em_andamento{ background:#17a2b8; color:#fff; text-transform: uppercase}
.status-emitida     { background:#28a745; color:#fff; text-transform: uppercase}
.status-entregue    { background:#6f42c1; color:#fff; text-transform: uppercase}
.status-cancelada   { background:#dc3545; color:#fff; text-transform: uppercase}

/* ====== Tabela ====== */
.table thead th { white-space: nowrap; }
.table td, .table th { vertical-align: middle !important; }
.table-hover tbody tr:hover{ background: rgba(13,110,253,.05); }
.nowrap { white-space: nowrap !important; }

/* ====== Ações ====== */
td .actions{ display:inline-flex; flex-wrap:wrap; gap:8px; }
.actions .btn{ padding:.42rem .68rem; }

/* ====== Cards (mobile) ====== */
.card-pedido {
  border-radius: 16px; margin-bottom: 16px; box-shadow: var(--shadow-strong);
}
.card-pedido .card-body{ padding: 14px 14px 12px; }
.card-pedido h5{ font-size:1rem; }

/* ====== Dark mode ====== */
.dark-mode .page-hero{ background: linear-gradient(135deg, rgba(13,110,253,.2), rgba(255,255,255,0.02)); }
.dark-mode .title-icon{ background: rgba(13,110,253,.25); }
.dark-mode .table-hover tbody tr:hover{ background: rgba(255,255,255,.06); }
.dark-mode .card-pedido{ box-shadow: 0 6px 18px rgba(0,0,0,.5); }

/* ====== API badges ====== */
.badge-api-ok{ background:#22c55e; color:#fff; }
.badge-api-warn{ background:#f59e0b; color:#1a1a1a; }
.badge-api-err{ background:#ef4444; color:#fff; }
.small-muted{ color:#6c757d; font-size:.86rem; }
.text-wrap-anywhere{ word-wrap:anywhere; word-break:break-word; }

/* seletor de page length */
.page-length{ display:flex; align-items:center; gap:8px; }
.page-length .custom-select{ width:5.2rem; }
</style>
</head>
<body>
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">
    <section class="page-hero">
      <div class="title-row">
        <div class="title-icon"><i class="fa fa-files-o" aria-hidden="true"></i></div>
        <div><h1 class="mb-0">Pedidos de Certidão</h1></div>
      </div>
    </section>

    <!-- Filtros -->
    <div class="filter-card mb-3">
      <div class="row">
        <div class="col-lg-3 col-md-6 mb-3">
          <label class="mb-1">Protocolo</label>
          <input type="text" id="f_protocolo" class="form-control" placeholder="Ex.: ABC123">
        </div>

        <div class="col-lg-2 col-md-6 mb-3">
          <label class="mb-1">Status</label>
          <select id="f_status" class="custom-select">
            <option value="">Todos</option>
            <option value="pendente">Pendente</option>
            <option value="em_andamento">Em andamento</option>
            <option value="emitida">Emitida</option>
            <option value="entregue">Entregue</option>
            <option value="cancelada">Cancelada</option>
          </select>
        </div>

        <div class="col-lg-2 col-md-6 mb-3">
          <label class="mb-1">Atribuição</label>
          <select id="f_atr" class="custom-select">
            <option value="">Todas</option>
            <option value="Notas">Notas</option>
            <option value="Registro Civil">Registro Civil</option>
            <option value="RI">RI</option>
            <option value="RTD/RTDPJ">RTD/RTDPJ</option>
          </select>
        </div>

        <div class="col-lg-2 col-md-6 mb-3">
          <label class="mb-1">Tipo</label>
          <select id="f_tipo" class="custom-select">
            <option value="">Todos</option>
            <?php foreach ($tipos as $t): ?>
              <option value="<?=htmlspecialchars($t)?>"><?=htmlspecialchars($t)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
          <label class="mb-1">Requerente</label>
          <input type="text" id="f_req" class="form-control" placeholder="Nome / parte">
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
          <label class="mb-1">Portador</label>
          <input type="text" id="f_portador" class="form-control" placeholder="Nome / parte">
        </div>

        <div class="col-lg-2 col-md-6 mb-3">
          <label class="mb-1">De</label>
          <input type="text" id="f_de" class="form-control" placeholder="dd/mm/aaaa">
        </div>
        <div class="col-lg-2 col-md-6 mb-3">
          <label class="mb-1">Até</label>
          <input type="text" id="f_ate" class="form-control" placeholder="dd/mm/aaaa">
        </div>

        <div class="col-lg-5 col-md-12 mb-3 d-flex align-items-end">
          <div class="filter-actions w-100">
            <a href="novo_pedido.php" class="btn btn-primary">
              <i class="fa fa-plus"></i> Novo Pedido
            </a>
            <button id="btnAplicar" class="btn btn-outline-primary">
              <i class="fa fa-filter"></i> Aplicar filtros
            </button>
            <button id="btnLimpar" class="btn btn-outline-secondary">
              <i class="fa fa-times"></i> Limpar
            </button>
          </div>
        </div>
      </div>
      <div class="small text-muted">Mostrando todos os registros.</div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
      <div class="page-length mb-2">
        <select id="len" class="custom-select">
          <option value="10">10</option>
          <option value="25" selected>25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span>resultados por página</span>
      </div>
      <div class="mb-2 d-flex align-items-center gap-2">
        <span class="mr-2">Pesquisar</span>
        <input id="globalSearch" type="text" class="form-control" style="min-width:240px;">
      </div>
    </div>

    <!-- DESKTOP: DataTable -->
    <div class="desktop-table">
      <div class="table-responsive">
        <table id="tabela" class="table table-striped table-hover align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th class="nowrap">Protocolo</th>
              <th>Status</th>
              <th>Atribuição / Tipo</th>
              <th>Requerente</th>
              <th>Portador</th>
              <th class="nowrap">Total O.S.</th>
              <th class="nowrap">Criado em</th>
              <th class="nowrap">API</th>
              <th class="nowrap" style="min-width:220px;">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pedidos as $p): ?>
            <?php
              $pend = (int)($p['pend_api'] ?? 0);
              $hasErr = $pend > 0 && !empty($p['last_api_error']);
            ?>
            <tr data-pedido-id="<?=$p['id']?>">
              <td class="nowrap"><?=htmlspecialchars($p['id'])?></td>
              <td class="nowrap"><?=htmlspecialchars($p['protocolo'])?></td>
              <td>
                <span class="badge badge-status status-<?=htmlspecialchars($p['status'])?>"><?=str_replace('_',' ',htmlspecialchars($p['status']))?></span>
              </td>
              <td><?=htmlspecialchars($p['atribuicao'])?> / <?=htmlspecialchars($p['tipo'])?></td>
              <td><?=htmlspecialchars($p['requerente_nome'])?></td>
              <td><?=htmlspecialchars($p['portador_nome'] ?? '')?></td>
              <td class="nowrap">R$ <?=number_format((float)$p['total_os'],2,',','.')?></td>
              <td class="nowrap"><?=date('d/m/Y H:i', strtotime($p['criado_em']))?></td>
              <td class="nowrap">
                <?php if ($pend > 0): ?>
                  <span class="badge <?=$hasErr ? 'badge-api-err':'badge-api-warn'?>" title="<?=htmlspecialchars($p['last_api_error'] ?? '')?>">
                    Pendente (<?=$pend?>)
                  </span>
                <?php else: ?>
                  <span class="badge badge-api-ok">OK</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="actions">
                  <a href="visualizar_pedido.php?id=<?=$p['id']?>"
                     class="btn btn-sm btn-info2" title="Ver detalhes" aria-label="Ver detalhes">
                    <i class="fa fa-eye" aria-hidden="true"></i> Ver
                  </a>
                  <a href="gerar_recibo_pedido.php?id=<?=$p['id']?>"
                     target="_blank" class="btn btn-sm btn-secondary" title="Recibo"
                     aria-label="Recibo">
                    <i class="fa fa-print" aria-hidden="true"></i> Recibo
                  </a>
                  <?php if ($pend > 0): ?>
                  <button type="button"
                          class="btn btn-sm btn-warning btn-reenviar-api"
                          data-id="<?=$p['id']?>"
                          title="Reenviar mensagens pendentes para a API">
                    <i class="fa fa-refresh"></i> Reenviar API
                  </button>
                  <?php endif; ?>
                </div>
                <?php if ($hasErr): ?>
                  <div class="small small-muted mt-1 text-wrap-anywhere">
                    <i class="fa fa-exclamation-circle"></i>
                    <?=htmlspecialchars(mb_strimwidth($p['last_api_error'],0,120,'…','UTF-8'))?>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MOBILE: Cards -->
    <div class="mobile-cards">
      <?php foreach ($pedidos as $p): ?>
      <?php
        $pend = (int)($p['pend_api'] ?? 0);
        $hasErr = $pend > 0 && !empty($p['last_api_error']);
      ?>
      <div class="card card-pedido" data-pedido-id="<?=$p['id']?>">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-1">Protocolo: <?=htmlspecialchars($p['protocolo'])?></h5>
            <span class="badge badge-status status-<?=htmlspecialchars($p['status'])?>"><?=str_replace('_',' ',htmlspecialchars($p['status']))?></span>
          </div>

          <p class="mb-1"><strong><?=htmlspecialchars($p['atribuicao'])?> / <?=htmlspecialchars($p['tipo'])?></strong></p>
          <p class="mb-1">Requerente: <?=htmlspecialchars($p['requerente_nome'])?></p>
          <p class="mb-1">Portador: <?=htmlspecialchars($p['portador_nome'] ?? '-')?></p>
          <p class="mb-1">Total O.S.: <strong>R$ <?=number_format((float)$p['total_os'],2,',','.')?></strong></p>
          <p class="mb-2">
            API:
            <?php if ($pend > 0): ?>
              <span class="badge <?=$hasErr ? 'badge-api-err':'badge-api-warn'?>">Pendente (<?=$pend?>)</span>
            <?php else: ?>
              <span class="badge badge-api-ok">OK</span>
            <?php endif; ?>
          </p>
          <?php if ($hasErr): ?>
            <div class="small small-muted mb-2 text-wrap-anywhere">
              <i class="fa fa-exclamation-circle"></i>
              <?=htmlspecialchars(mb_strimwidth($p['last_api_error'],0,140,'…','UTF-8'))?>
            </div>
          <?php endif; ?>

          <div class="d-grid gap-2">
            <a href="visualizar_pedido.php?id=<?=$p['id']?>" class="btn btn-info2 btn-sm" style="border-radius:12px;">
              <i class="fa fa-eye" aria-hidden="true"></i> Ver
            </a>
            <a href="gerar_recibo_pedido.php?id=<?=$p['id']?>" target="_blank" class="btn btn-secondary btn-sm" style="border-radius:12px;">
              <i class="fa fa-print" aria-hidden="true"></i> Recibo
            </a>
            <?php if ($pend > 0): ?>
            <button type="button" class="btn btn-warning btn-sm btn-reenviar-api" data-id="<?=$p['id']?>" style="border-radius:12px;">
              <i class="fa fa-refresh"></i> Reenviar API
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.dataTables.min.js"></script>
<script src="../script/dataTables.bootstrap4.min.js"></script>
<script src="../script/sweetalert2.js"></script>
<script>
$(function(){
  // aplica o modo salvo (o toggle fica no menu)
  $.get('../load_mode.php', function(mode){
    $('body').removeClass('light-mode dark-mode').addClass(mode);
  });

  let table = null;
  if (window.matchMedia('(min-width: 992px)').matches) {
    table = $('#tabela').DataTable({
      pageLength: 25,
      order:[[0,'desc']],
      language: { url: '../style/Portuguese-Brasil.json' },
      autoWidth: false,
      dom: 't<"d-flex justify-content-between align-items-center mt-2"ip>',
      columnDefs: [
        { targets: [1,6,7,8,9], className: 'nowrap' }
      ]
    });

    // controlar page length do select externo
    $('#len').on('change', function(){ table.page.len(parseInt(this.value||25,10)).draw(); });
  }

  // Pesquisa global
  $('#globalSearch').on('keyup change', function(){
    if (table){ table.search(this.value).draw(); }
  });

  // Normalização para busca acento-insensível
  function norm(s){
    return (s||'').toString()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g,'')
      .toLowerCase();
  }

  // Remove filtros customizados prévios por tag
  function removeCustomFilter(tag){
    $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn){
      return !(fn && fn.__tag === tag);
    });
  }

  // Aplicar filtros
  function applyFilters(){
    if (!table) return;

    // campos
    const protocolo = $('#f_protocolo').val().trim();
    const status    = $('#f_status').val();
    const atr       = $('#f_atr').val().trim();
    const tipo      = $('#f_tipo').val().trim();
    const reqTerm   = $('#f_req').val().trim();
    const portTerm  = $('#f_portador').val().trim();
    const de        = $('#f_de').val().trim();
    const ate       = $('#f_ate').val().trim();

    // filtros simples por coluna (regex desativado para protocolo; ativado para atr/tipo)
    table.columns(0).search(protocolo, false, false);
    table.columns(1).search(status ? status.replace('_',' ') : '', false, false);
    function regexEscape(s){ return (s||'').replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

    // dentro de applyFilters()
    const tipoR = tipo ? '.*' + regexEscape(tipo) : '';
    const atrTipo = (atr ? regexEscape(atr) : '') + tipoR;
    table.columns(2).search(atrTipo, true, false);

    // limpar buscas padrão de req/portador (vamos usar filtro customizado acento-insensível)
    table.columns(3).search('');
    table.columns(4).search('');

    // --- Filtro por datas (tag: 'date') ---
    removeCustomFilter('date');
    if (de || ate) {
      const dateFilter = function(settings, data){
        const val = (data[6]||'').trim(); // col 6
        const m = val.match(/(\d{2})\/(\d{2})\/(\d{4})/);
        if (!m) return true;
        const d = new Date(+m[3], +m[2]-1, +m[1], 0,0,0,0).getTime();
        let ok = true;
        if (de){
          const md = de.split('/');
          const dmin = new Date(+md[2], +md[1]-1, +md[0], 0,0,0,0).getTime();
          ok = ok && (d >= dmin);
        }
        if (ate){
          const ma = ate.split('/');
          const dmax = new Date(+ma[2], +ma[1]-1, +ma[0], 23,59,59,999).getTime();
          ok = ok && (d <= dmax);
        }
        return ok;
      };
      dateFilter.__tag = 'date';
      $.fn.dataTable.ext.search.push(dateFilter);
    }

    // --- Filtro acento-insensível para Requerente e Portador (tag: 'people') ---
    removeCustomFilter('people');
    if (reqTerm || portTerm){
      const reqN = norm(reqTerm);
      const portN = norm(portTerm);
      const peopleFilter = function(settings, data){
        const req = norm(data[3]||'');   // coluna 3
        const por = norm(data[4]||'');   // coluna 4
        let ok = true;
        if (reqN){ ok = ok && req.indexOf(reqN) !== -1; }
        if (portN){ ok = ok && por.indexOf(portN) !== -1; }
        return ok;
      };
      peopleFilter.__tag = 'people';
      $.fn.dataTable.ext.search.push(peopleFilter);
    }

    table.draw();
  }

  $('#btnAplicar').on('click', applyFilters);

  $('#btnLimpar').on('click', function(){
    $('#f_protocolo,#f_tipo,#f_req,#f_portador,#f_de,#f_ate').val('');
    $('#f_status,#f_atr').val('');
    if (table){
      $('#globalSearch').val('');
      table.search('');
      removeCustomFilter('date');
      removeCustomFilter('people');
      table.columns().search('');
      table.draw();
    }
  });

  // Reenvio para API (desktop + mobile)
  $(document).on('click', '.btn-reenviar-api', function(){
    const id = $(this).data('id');
    const $btn = $(this);
    const original = $btn.html();

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Reenviando...');

    $.post('reenvio_api.php', { pedido_id: id }, function(resp){
      if (!resp || resp.error){
        Swal.fire({icon:'error', title:'Erro', text:(resp && resp.error) ? resp.error : 'Falha ao reenviar.'});
        return;
      }
      const ok = (resp.success === true && resp.failed === 0);
      const msg = `Mensagens entregues: ${resp.delivered||0}` + (resp.failed>0 ? ` • Falhas: ${resp.failed}` : '');
      Swal.fire({icon: ok ? 'success':'warning', title: ok ? 'Reenviado':'Parcialmente entregue', text: msg})
        .then(()=>{ location.reload(); });
    }, 'json')
    .fail(function(xhr){
      console.error(xhr.responseText);
      Swal.fire({icon:'error', title:'Erro', text:'Não foi possível contatar o servidor.'});
    })
    .always(function(){
      $btn.prop('disabled', false).html(original);
    });
  });
});
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
