<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard();
require_once __DIR__ . '/config.php';
cap_ensure_schema();
$conn = cap_db();
function he($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$conta = ($_GET['conta'] ?? 'especie') === 'banco' ? 'banco' : 'especie';
$de  = $_GET['de']  ?? date('Y-m-01');
$ate = $_GET['ate'] ?? date('Y-m-t');
if (!strtotime($de))  $de  = date('Y-m-01');
if (!strtotime($ate)) $ate = date('Y-m-t');
$de = date('Y-m-d', strtotime($de)); $ate = date('Y-m-d', strtotime($ate));

$meta   = cap_contas_virtuais()[$conta];
$saldos = cap_saldos();
$temCaixa = cap_tem_deposito_caixa();

/* Entradas: depósitos do módulo Caixa */
$mov = [];
$entPeriodo = 0.0;
if ($temCaixa) {
    $in = "'" . implode("','", array_map([$conn,'real_escape_string'], $meta['tipos'])) . "'";
    $st = $conn->prepare("SELECT data_caixa, valor_do_deposito, tipo_deposito, funcionario FROM deposito_caixa WHERE tipo_deposito IN ($in) AND data_caixa BETWEEN ? AND ? ORDER BY data_caixa");
    $st->bind_param('ss', $de, $ate); $st->execute();
    $r = $st->get_result();
    while ($x = $r->fetch_assoc()) {
        $entPeriodo += (float)$x['valor_do_deposito'];
        $mov[] = ['data'=>$x['data_caixa'],'tipo'=>'entrada','desc'=>'Depósito · '.$x['tipo_deposito'],
                  'obs'=>$x['funcionario'] ?? '', 'valor'=>(float)$x['valor_do_deposito']];
    }
    $st->close();
}
/* Saídas: contas pagas debitadas nesta conta */
$saiPeriodo = 0.0;
$st2 = $conn->prepare("SELECT data_pagamento, titulo, categoria, forma_pagamento, valor FROM contas_a_pagar WHERE status='Pago' AND conta_origem=? AND data_pagamento BETWEEN ? AND ? ORDER BY data_pagamento");
$st2->bind_param('sss', $conta, $de, $ate); $st2->execute();
$r2 = $st2->get_result();
while ($x = $r2->fetch_assoc()) {
    $saiPeriodo += (float)$x['valor'];
    $mov[] = ['data'=>$x['data_pagamento'],'tipo'=>'saida','desc'=>'Pagamento · '.$x['titulo'],
              'obs'=>trim(($x['categoria']??'').' '.($x['forma_pagamento']?('· '.$x['forma_pagamento']):'')), 'valor'=>-(float)$x['valor']];
}
$st2->close();

/* Transferências entre contas virtuais */
$stt = $conn->prepare("SELECT id, data_transferencia, origem, destino, valor, observacao, usuario FROM conta_transferencias WHERE (origem=? OR destino=?) AND data_transferencia BETWEEN ? AND ? ORDER BY data_transferencia");
$stt->bind_param('ssss', $conta, $conta, $de, $ate); $stt->execute();
$r3 = $stt->get_result();
while ($x = $r3->fetch_assoc()) {
    $entrou = ($x['destino'] === $conta);
    if ($entrou) $entPeriodo += (float)$x['valor']; else $saiPeriodo += (float)$x['valor'];
    $outra = cap_nome_conta($entrou ? $x['origem'] : $x['destino']);
    $mov[] = [
        'data'  => $x['data_transferencia'],
        'tipo'  => $entrou ? 'entrada' : 'saida',
        'desc'  => 'Transferência · ' . ($entrou ? ('recebida de ' . $outra) : ('enviada para ' . $outra)),
        'obs'   => trim(($x['observacao'] ?? '') . ($x['usuario'] ? (' · ' . $x['usuario']) : '')),
        'valor' => $entrou ? (float)$x['valor'] : -(float)$x['valor'],
        'transf_id' => (int)$x['id'],
    ];
}
$stt->close();

/* Entradas de O.S. (recebimentos não-espécie) — aparecem apenas na conta bancária */
if ($conta === 'banco' && cap_tem_pagamento_os()) {
    $so = $conn->prepare("SELECT data_pagamento, cliente, total_pagamento, forma_de_pagamento, ordem_de_servico_id
                          FROM pagamento_os
                          WHERE status='pago'
                            AND LOWER(TRIM(forma_de_pagamento)) NOT IN ('espécie','especie','dinheiro')
                            AND DATE(data_pagamento) BETWEEN ? AND ?
                          ORDER BY data_pagamento");
    $so->bind_param('ss', $de, $ate); $so->execute();
    $ro = $so->get_result();
    while ($x = $ro->fetch_assoc()) {
        $entPeriodo += (float)$x['total_pagamento'];
        $os = $x['ordem_de_servico_id'] ? ('O.S. #'.$x['ordem_de_servico_id']) : 'O.S.';
        $mov[] = ['data'=>$x['data_pagamento'],'tipo'=>'entrada',
                  'desc'=>'Recebimento '.$os.' · '.$x['forma_de_pagamento'],
                  'obs'=>$x['cliente'] ?? '', 'valor'=>(float)$x['total_pagamento']];
    }
    $so->close();
}
usort($mov, function($a,$b){ return strcmp($a['data'],$b['data']); });
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas - Extrato da conta virtual</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php include(__DIR__ . '/complementos/style_padrao.php'); ?>
<style>
    #main .container{ padding-bottom:120px; }
    .kpi-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:14px; margin:6px 0 16px; }
    .kpi{ background:#fff;border:1px solid #e5e9f0;border-radius:16px;padding:14px 16px;box-shadow:0 8px 22px rgba(15,23,42,.05); }
    .kpi .lb{ color:#64748b;font-size:.8rem;font-weight:600; } .kpi .vl{ font-size:1.3rem;font-weight:800; }
    .input-chip select,.input-chip input{ border:none;outline:none;width:100%;background:transparent;color:inherit; }
    .tabs-conta .btn{ border-radius:999px; }
    .mv-ent{ color:#16a34a;font-weight:700; } .mv-sai{ color:#b91c1c;font-weight:700; }
    body.dark-mode .kpi{ background:#23272a;border-color:rgba(255,255,255,.07); }
    .cap-modal .modal-content{ border:0;border-radius:16px;overflow:hidden; }
    .cap-modal .modal-header{ background:linear-gradient(135deg,#0ea5e9,#2563eb); color:#fff; border:0; padding:16px 20px; }
    .cap-close{ border:0;background:rgba(255,255,255,.18);color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.15s; }
    .cap-close:hover{ background:rgba(255,255,255,.34); transform:rotate(90deg); }
    .pg-saldo{ display:flex;gap:10px;align-items:center;padding:10px 12px;border-radius:12px;background:#f1f5f9;font-size:.85rem;font-weight:600; }
    .pg-saldo.neg{ background:#fee2e2;color:#b91c1c; } .pg-saldo.ok{ background:#dcfce7;color:#166534; }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
  <div class="container">
    <section class="page-hero">
      <div class="title-row">
        <div class="title-icon"><i class="fa <?php echo he($meta['icone']); ?>"></i></div>
        <div style="flex:1;min-width:0"><h1>Extrato · <?php echo he($meta['nome']); ?></h1>
          <div class="subtitle muted">Entradas vindas dos depósitos do Controle de Caixa e saídas pelos pagamentos de contas.</div></div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary btn-pill" id="btnTransferir"><i class="fa fa-exchange"></i> Transferir saldo</button>
          <a class="btn btn-soft btn-pill" href="index.php"><i class="fa fa-arrow-left"></i> Voltar</a>
        </div>
      </div>
    </section>

    <?php if (!$temCaixa): ?>
      <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> A tabela <code>deposito_caixa</code> (módulo Controle de Caixa) não foi encontrada — as entradas aparecem zeradas.</div>
    <?php endif; ?>

    <div class="d-flex gap-2 mb-3 tabs-conta">
      <?php foreach (cap_contas_virtuais() as $cod=>$m): ?>
        <a class="btn <?php echo $cod===$conta?'btn-primary':'btn-soft'; ?> btn-pill" href="extrato.php?conta=<?php echo $cod; ?>&de=<?php echo he($de); ?>&ate=<?php echo he($ate); ?>">
          <i class="fa <?php echo he($m['icone']); ?>"></i> <?php echo he($m['nome']); ?></a>
      <?php endforeach; ?>
    </div>

    <div class="kpi-grid">
      <div class="kpi"><div class="lb">Saldo atual (total)</div><div class="vl" style="color:<?php echo $saldos[$conta]['saldo']<0?'#b91c1c':'#16a34a'; ?>"><?php echo cap_money($saldos[$conta]['saldo']); ?></div></div>
      <div class="kpi"><div class="lb">Entradas no período</div><div class="vl" style="color:#16a34a"><?php echo cap_money($entPeriodo); ?></div></div>
      <div class="kpi"><div class="lb">Saídas no período</div><div class="vl" style="color:#b91c1c"><?php echo cap_money($saiPeriodo); ?></div></div>
      <div class="kpi"><div class="lb">Resultado do período</div><div class="vl"><?php echo cap_money($entPeriodo - $saiPeriodo); ?></div></div>
    </div>

    <form method="GET" class="filter-card">
      <input type="hidden" name="conta" value="<?php echo he($conta); ?>">
      <div class="section-title">Período</div>
      <div class="row">
        <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">De</label>
          <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" name="de" value="<?php echo he($de); ?>"></div></div>
        <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">Até</label>
          <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" name="ate" value="<?php echo he($ate); ?>"></div></div>
      </div>
      <div class="filter-actions"><button class="btn btn-primary btn-pill"><i class="fa fa-filter"></i> Filtrar</button></div>
    </form>

    <div class="table-responsive table-wrap mt-3">
      <h5 class="mb-2">Movimentações</h5>
      <table id="tabelaContas" class="table table-striped table-bordered data-layout" style="width:100%">
        <thead><tr><th>Data</th><th>Descrição</th><th>Observação</th><th>Valor</th><th style="width:8%">Ações</th></tr></thead>
        <tbody>
        <?php if (!$mov): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma movimentação no período.</td></tr>
        <?php else: foreach ($mov as $m): ?>
          <tr>
            <td data-label="Data"><?php echo date('d/m/Y', strtotime($m['data'])); ?></td>
            <td data-label="Descrição"><?php echo he($m['desc']); ?></td>
            <td data-label="Observação"><?php echo he($m['obs']); ?></td>
            <td data-label="Valor" class="<?php echo $m['tipo']==='entrada'?'mv-ent':'mv-sai'; ?>">
              <?php echo ($m['tipo']==='entrada'?'+ ':'− ') . cap_money(abs($m['valor'])); ?></td>
            <td data-cell="acoes">
              <?php if (!empty($m['transf_id'])): ?>
                <button type="button" class="btn btn-danger btn-sm btn-table js-estornar" data-id="<?php echo (int)$m['transf_id']; ?>" title="Estornar transferência"><i class="fa fa-undo"></i></button>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL TRANSFERÊNCIA -->
<div class="modal fade cap-modal" id="transferirModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <span style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center"><i class="fa fa-exchange"></i></span>
          <div><div style="font-weight:800;font-size:1.05rem">Transferir entre contas</div><div style="font-size:.8rem;opacity:.9">Ex.: depositar a espécie no banco</div></div>
        </div>
        <button type="button" class="cap-close" data-bs-dismiss="modal"><i class="fa fa-times"></i></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-5">
            <label class="form-label small text-muted mb-1">De (origem)</label>
            <div class="input-chip"><i class="fa fa-arrow-circle-o-up"></i><select id="tr_origem">
              <?php foreach(cap_contas_virtuais() as $cod=>$m): ?><option value="<?php echo $cod; ?>" <?php echo $cod===$conta?'selected':''; ?>><?php echo he($m['nome']); ?></option><?php endforeach; ?>
            </select></div>
          </div>
          <div class="col-12 col-md-2 text-center d-none d-md-block"><button type="button" class="btn btn-light btn-sm" id="trSwap" title="Inverter"><i class="fa fa-exchange"></i></button></div>
          <div class="col-12 col-md-5">
            <label class="form-label small text-muted mb-1">Para (destino)</label>
            <div class="input-chip"><i class="fa fa-arrow-circle-o-down"></i><select id="tr_destino">
              <?php foreach(cap_contas_virtuais() as $cod=>$m): ?><option value="<?php echo $cod; ?>" <?php echo $cod!==$conta?'selected':''; ?>><?php echo he($m['nome']); ?></option><?php endforeach; ?>
            </select></div>
          </div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-12 col-md-6">
            <label class="form-label small text-muted mb-1">Valor (R$) *</label>
            <div class="input-chip"><i class="fa fa-money"></i><input type="text" id="tr_valor" inputmode="decimal" placeholder="0,00"></div>
            <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="trTudo">Usar todo o saldo disponível</button>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small text-muted mb-1">Data</label>
            <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" id="tr_data" value="<?php echo date('Y-m-d'); ?>"></div>
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Observação</label>
            <div class="input-chip"><i class="fa fa-comment-o"></i><input type="text" id="tr_obs" placeholder="Ex.: Depósito do malote"></div>
          </div>
        </div>
        <div id="tr_saldo_box" class="pg-saldo mt-3"><i class="fa fa-wallet"></i> <span id="tr_saldo_txt">—</span></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="trConfirmBtn"><i class="fa fa-exchange"></i> Transferir</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
<script>
(function(){
  "use strict";
  var CSRF   = <?php echo json_encode(cap_csrf_token()); ?>;
  var SALDOS = <?php echo json_encode(array_map(function($v){ return $v['saldo']; }, $saldos)); ?>;
  var NOMES  = <?php echo json_encode(array_map(function($m){ return $m['nome']; }, cap_contas_virtuais()), JSON_UNESCAPED_UNICODE); ?>;
  function $(s){ return document.querySelector(s); }
  function brl(v){ return 'R$ ' + Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function parseBR(t){ return parseFloat(String(t||'').replace(/\./g,'').replace(',','.')) || 0; }
  function setMoney(el,n){ el.value = (Number(n)||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
  async function post(url, data){
    data.csrf = CSRF;
    var r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(data).toString(), credentials:'same-origin'});
    var t = await r.text(); try{ return JSON.parse(t); }catch(e){ throw new Error('Resposta inválida: '+t.slice(0,140)); }
  }

  /* ---- máscara ---- */
  var val = $('#tr_valor');
  if (val) val.addEventListener('input', function(){
    var v = this.value.replace(/\D/g,''); v = (parseInt(v||'0',10)/100).toFixed(2);
    this.value = v.replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    box();
  });

  function box(){
    var o=$('#tr_origem').value, d=$('#tr_destino').value, b=$('#tr_saldo_box'), t=$('#tr_saldo_txt');
    b.classList.remove('ok','neg');
    if(o===d){ t.textContent='Escolha contas diferentes para origem e destino.'; b.classList.add('neg'); return; }
    var s=SALDOS[o]||0, v=parseBR($('#tr_valor').value);
    t.textContent = NOMES[o]+': '+brl(s)+' → '+brl(s-v)+'  ·  '+NOMES[d]+' recebe '+brl(v);
    b.classList.add((s-v) < 0 ? 'neg' : 'ok');
  }

  var modal;
  var btnAbrir = $('#btnTransferir');
  if (btnAbrir) btnAbrir.addEventListener('click', function(){
    modal = bootstrap.Modal.getOrCreateInstance($('#transferirModal'));
    $('#tr_valor').value=''; $('#tr_obs').value=''; box();
    modal.show();
  });
  ['#tr_origem','#tr_destino'].forEach(function(sel){ var e=$(sel); if(e) e.addEventListener('change', box); });
  var swap=$('#trSwap'); if(swap) swap.addEventListener('click', function(){ var o=$('#tr_origem').value; $('#tr_origem').value=$('#tr_destino').value; $('#tr_destino').value=o; box(); });
  var tudo=$('#trTudo'); if(tudo) tudo.addEventListener('click', function(){ setMoney($('#tr_valor'), Math.max(0, SALDOS[$('#tr_origem').value]||0)); box(); });

  async function transferir(forcar){
    var o=$('#tr_origem').value, d=$('#tr_destino').value;
    if(o===d) return Swal.fire('Atenção','Origem e destino devem ser diferentes.','warning');
    if(parseBR($('#tr_valor').value) <= 0) return Swal.fire('Atenção','Informe um valor maior que zero.','warning');
    var btn=$('#trConfirmBtn'); btn.disabled=true;
    var data={ origem:o, destino:d, valor:$('#tr_valor').value, data_transferencia:$('#tr_data').value, observacao:$('#tr_obs').value };
    if(forcar===true) data.forcar='1';
    try{
      var r=await post('transferir.php', data);
      if(!r.success && r.saldo_insuficiente){
        btn.disabled=false;
        var ok=(await Swal.fire({icon:'warning',title:'Saldo insuficiente',
          html:'Conta <b>'+r.conta_nome+'</b><br>Disponível: <b>'+r.saldo_fmt+'</b><br>Valor: <b>'+r.valor_fmt+'</b><br><br>Transferir mesmo assim (saldo ficará negativo)?',
          showCancelButton:true, confirmButtonText:'Transferir assim mesmo', cancelButtonText:'Cancelar', confirmButtonColor:'#d97706'})).isConfirmed;
        if(ok) return transferir(true);
        return;
      }
      if(!r.success) throw new Error(r.message||'Falha.');
      modal.hide();
      Swal.fire({icon:'success',title:'Transferência registrada',text:r.message,timer:2400,showConfirmButton:false});
      setTimeout(function(){ location.reload(); }, 1100);
    }catch(e){ Swal.fire('Erro', e.message,'error'); btn.disabled=false; }
  }
  var conf=$('#trConfirmBtn'); if(conf) conf.addEventListener('click', function(){ transferir(false); });

  /* ---- estorno ---- */
  document.addEventListener('click', async function(ev){
    var b = ev.target.closest('.js-estornar'); if(!b) return;
    var ok=(await Swal.fire({icon:'warning',title:'Estornar transferência?',text:'Os saldos serão recalculados.',showCancelButton:true,confirmButtonText:'Estornar',cancelButtonText:'Cancelar',confirmButtonColor:'#dc3545'})).isConfirmed;
    if(!ok) return;
    try{
      var j=await post('transferencia_excluir.php', {id:b.dataset.id});
      if(j.success){ location.reload(); } else { Swal.fire('Erro', j.message||'Falha ao estornar.','error'); }
    }catch(e){ Swal.fire('Erro', e.message,'error'); }
  });

  box();
})();
</script>
</body>
</html>
