<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard();
require_once __DIR__ . '/config.php';
cap_ensure_schema();
$conn = cap_db();
$CATS = cap_categorias();
function hr($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* filtros */
$de   = $_GET['de']   ?? date('Y-m-01');
$ate  = $_GET['ate']  ?? date('Y-m-t');
$baseSel = $_GET['base'] ?? 'ambos'; // ambos | vencimento | pagamento
$cat  = trim((string)($_GET['categoria'] ?? ''));
$stat = trim((string)($_GET['status'] ?? 'todas'));
if (!strtotime($de)) $de = date('Y-m-01');
if (!strtotime($ate)) $ate = date('Y-m-t');
$de = date('Y-m-d', strtotime($de)); $ate = date('Y-m-d', strtotime($ate));

$where = []; $types=''; $vals=[]; $orderCol='data_vencimento';
if ($baseSel === 'vencimento')      { $where[]="data_vencimento BETWEEN ? AND ?"; $types.='ss'; array_push($vals,$de,$ate); $orderCol='data_vencimento'; }
elseif ($baseSel === 'pagamento')   { $where[]="data_pagamento BETWEEN ? AND ?"; $types.='ss'; array_push($vals,$de,$ate); $orderCol='data_pagamento'; }
else                                { $where[]="((data_vencimento BETWEEN ? AND ?) OR (data_pagamento BETWEEN ? AND ?))"; $types.='ssss'; array_push($vals,$de,$ate,$de,$ate); }
if ($cat !== '') { $where[]="categoria=?"; $types.='s'; $vals[]=$cat; }
switch ($stat) {
    case 'aberto':   $where[]="status='Pendente'"; break;
    case 'vencidas': $where[]="status='Pendente' AND data_vencimento<CURDATE()"; break;
    case 'pago':     $where[]="status='Pago'"; break;
}
$W = implode(' AND ', $where);
$stmt = $conn->prepare("SELECT * FROM contas_a_pagar WHERE $W ORDER BY $orderCol ASC, id DESC");
$stmt->bind_param($types, ...$vals); $stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

/* Exportação CSV */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio_contas_'.$de.'_'.$ate.'.csv"');
    echo "\xEF\xBB\xBF"; // BOM p/ Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Vencimento','Título','Categoria','Fornecedor','Nota fiscal','Valor','Recorrência','Situação','Pagamento','Forma','Conta'], ';');
    foreach ($rows as $c) {
        fputcsv($out, [
            date('d/m/Y', strtotime($c['data_vencimento'])), $c['titulo'], $c['categoria'], $c['fornecedor'], $c['nota_fiscal'] ?? '',
            number_format((float)$c['valor'],2,',','.'), $c['recorrencia'], cap_status_efetivo($c),
            $c['data_pagamento'] ? date('d/m/Y', strtotime($c['data_pagamento'])) : '',
            $c['forma_pagamento'] ?? '', $c['conta_origem'] ? cap_nome_conta($c['conta_origem']) : ''
        ], ';');
    }
    fclose($out); exit;
}

/* anexos por conta (para a coluna de anexos no detalhamento) */
$anexosPorConta = [];
if ($rows) {
    $ids = array_values(array_filter(array_unique(array_map(fn($r)=>(int)$r['id'], $rows)), fn($v)=>$v>0));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sa = $conn->prepare("SELECT conta_id, COUNT(*) c FROM conta_anexos WHERE conta_id IN ($in) GROUP BY conta_id");
        $sa->bind_param(str_repeat('i', count($ids)), ...$ids);
        $sa->execute(); $ra = $sa->get_result();
        while ($x = $ra->fetch_assoc()) $anexosPorConta[(int)$x['conta_id']] = (int)$x['c'];
        $sa->close();
    }
}

/* totais + agregações */
$total=0; $qtdPago=0; $vlPago=0; $vlAberto=0; $vlVenc=0; $porCat=[]; $porMes=[];
foreach ($rows as $c) {
    $v=(float)$c['valor']; $total+=$v;
    $st=cap_status_efetivo($c);
    if ($st==='Pago'){ $qtdPago++; $vlPago+=$v; } elseif ($st==='Atrasado'){ $vlVenc+=$v; } else { $vlAberto+=$v; }
    $k = $c['categoria'] ?: 'Sem categoria'; $porCat[$k]=($porCat[$k]??0)+$v;
    $ref = ($c['status']==='Pago' && !empty($c['data_pagamento'])) ? $c['data_pagamento'] : $c['data_vencimento'];
    $m = date('m/Y', strtotime($ref)); $porMes[$m]=($porMes[$m]??0)+$v;
}
arsort($porCat);
$catLabels=array_keys($porCat); $catVals=array_values($porCat);
$mesLabels=array_keys($porMes); $mesVals=array_values($porMes);
$qs = http_build_query(['de'=>$de,'ate'=>$ate,'base'=>$baseSel,'categoria'=>$cat,'status'=>$stat]);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas - Relatórios de Contas</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../style/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../style/css/style.css">
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php include(__DIR__ . '/complementos/style_padrao.php'); ?>
<style>
    #main .container{ padding-bottom:120px; }
    .kpi-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:14px; margin:6px 0 16px; }
    .kpi{ background:#fff;border:1px solid #e5e9f0;border-radius:16px;padding:14px 16px;box-shadow:0 8px 22px rgba(15,23,42,.05); }
    .kpi .lb{ color:#64748b;font-size:.8rem;font-weight:600; } .kpi .vl{ font-size:1.3rem;font-weight:800; }
    .chart-card{ background:#fff;border:1px solid #e5e9f0;border-radius:16px;padding:16px;box-shadow:0 8px 22px rgba(15,23,42,.05); }
    .chart-card h6{ font-weight:800;font-size:.9rem;margin:0 0 10px; } .chart-card canvas{ max-height:260px; }
    .input-chip select,.input-chip input{ border:none;outline:none;width:100%;background:transparent;color:inherit; }
    body.dark-mode .kpi,body.dark-mode .chart-card{ background:#23272a;border-color:rgba(255,255,255,.07); }
    .st-badge{ display:inline-block;padding:3px 10px;border-radius:999px;font-size:.76rem;font-weight:700; }
    .st-Pendente{ background:#dbeafe;color:#1d4ed8; } .st-Atrasado{ background:#fee2e2;color:#b91c1c; } .st-Pago{ background:#dcfce7;color:#166534; }
    /* modal de anexos no relatório */
    .ax-item{ display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid #e5e9f0;border-radius:12px;margin-bottom:8px;background:#fff; }
    body.dark-mode .ax-item{ background:#23272a;border-color:rgba(255,255,255,.07); }
    .ax-item .fi{ width:40px;height:40px;border-radius:10px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.05rem; }
    .ax-item .nm{ font-weight:600;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .ax-item .sub{ color:#64748b;font-size:.76rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .ax-item .acts{ margin-left:auto;display:flex;gap:6px;flex:0 0 auto; }
    .ax-item .acts button{ border:1px solid #e5e9f0;background:#f8fafc;border-radius:8px;width:34px;height:34px;cursor:pointer;color:#334155; }
    body.dark-mode .ax-item .acts button{ background:#2c2f33;border-color:rgba(255,255,255,.08);color:#e5e7eb; }
    #relAxViewerBody iframe,#relAxViewerBody img{ width:100%;height:70vh;border:1px solid #e5e9f0;border-radius:12px; }
    #relAxViewerBody img{ height:auto;max-height:70vh;object-fit:contain; }
</style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>
<div id="main" class="main-content">
    <div class="container">
        <section class="page-hero">
            <div class="title-row">
                <div class="title-icon"><i class="fa fa-bar-chart"></i></div>
                <div style="flex:1;min-width:0"><h1>Relatórios de Contas</h1>
                    <div class="subtitle muted">Análise de despesas por período, categoria e situação.</div></div>
                <div class="d-flex gap-2">
                    <a class="btn btn-success btn-pill" href="relatorios.php?<?php echo hr($qs); ?>&export=csv"><i class="fa fa-file-excel-o"></i> Exportar CSV</a>
                    <a class="btn btn-soft btn-pill" href="index.php"><i class="fa fa-arrow-left"></i> Voltar</a>
                </div>
            </div>
        </section>

        <form method="GET" class="filter-card">
            <div class="section-title">Período & filtros</div>
            <div class="row">
                <div class="col-6 col-md-2 mb-3"><label class="form-label small text-muted mb-1">De</label>
                    <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" name="de" value="<?php echo hr($de); ?>"></div></div>
                <div class="col-6 col-md-2 mb-3"><label class="form-label small text-muted mb-1">Até</label>
                    <div class="input-chip"><i class="fa fa-calendar"></i><input type="date" name="ate" value="<?php echo hr($ate); ?>"></div></div>
                <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">Base da data</label>
                    <div class="input-chip"><i class="fa fa-clock-o"></i><select name="base">
                        <option value="ambos" <?php echo $baseSel==='ambos'?'selected':''; ?>>Vencimento ou pagamento</option>
                        <option value="vencimento" <?php echo $baseSel==='vencimento'?'selected':''; ?>>Vencimento</option>
                        <option value="pagamento" <?php echo $baseSel==='pagamento'?'selected':''; ?>>Pagamento</option>
                    </select></div></div>
                <div class="col-6 col-md-3 mb-3"><label class="form-label small text-muted mb-1">Categoria</label>
                    <div class="input-chip"><i class="fa fa-tag"></i><select name="categoria"><option value="">Todas</option>
                        <?php foreach($CATS as $c): ?><option value="<?php echo hr($c); ?>" <?php echo $cat===$c?'selected':''; ?>><?php echo hr($c); ?></option><?php endforeach; ?>
                    </select></div></div>
                <div class="col-6 col-md-2 mb-3"><label class="form-label small text-muted mb-1">Situação</label>
                    <div class="input-chip"><i class="fa fa-flag"></i><select name="status">
                        <?php foreach(['todas'=>'Todas','aberto'=>'Em aberto','vencidas'=>'Vencidas','pago'=>'Pagas'] as $k=>$v): ?>
                            <option value="<?php echo $k; ?>" <?php echo $stat===$k?'selected':''; ?>><?php echo $v; ?></option><?php endforeach; ?>
                    </select></div></div>
            </div>
            <div class="filter-actions"><button class="btn btn-primary btn-pill"><i class="fa fa-filter"></i> Gerar</button></div>
        </form>

        <div class="kpi-grid">
            <div class="kpi"><div class="lb">Total no período</div><div class="vl"><?php echo cap_money($total); ?></div><div class="text-muted" style="font-size:.76rem"><?php echo count($rows); ?> conta(s)</div></div>
            <div class="kpi"><div class="lb">Pago</div><div class="vl" style="color:#16a34a"><?php echo cap_money($vlPago); ?></div><div class="text-muted" style="font-size:.76rem"><?php echo $qtdPago; ?> conta(s)</div></div>
            <div class="kpi"><div class="lb">Em aberto</div><div class="vl" style="color:#2563eb"><?php echo cap_money($vlAberto); ?></div></div>
            <div class="kpi"><div class="lb">Vencidas</div><div class="vl" style="color:#b91c1c"><?php echo cap_money($vlVenc); ?></div></div>
        </div>

        <div class="row g-3 mb-1">
            <div class="col-12 col-lg-6"><div class="chart-card"><h6><i class="fa fa-tags text-primary"></i> Por categoria</h6><canvas id="chartCat"></canvas></div></div>
            <div class="col-12 col-lg-6"><div class="chart-card"><h6><i class="fa fa-line-chart text-primary"></i> Por mês</h6><canvas id="chartMes"></canvas></div></div>
        </div>

        <div class="table-responsive table-wrap mt-3">
            <h5 class="mb-2">Detalhamento</h5>
            <table id="tabelaContas" class="table table-striped table-bordered data-layout" style="width:100%">
                <thead><tr><th>Vencimento</th><th>Título</th><th>Categoria</th><th>Fornecedor</th><th>Nota fiscal</th><th>Valor</th><th>Situação</th><th>Pagamento</th><th>Forma</th><th>Anexos</th></tr></thead>
                <tbody>
                <?php foreach($rows as $c): $st=cap_status_efetivo($c); ?>
                    <tr>
                        <td data-label="Vencimento" data-order="<?php echo date('Y-m-d',strtotime($c['data_vencimento'])); ?>"><?php echo date('d/m/Y',strtotime($c['data_vencimento'])); ?></td>
                        <td data-label="Título"><?php echo hr($c['titulo']); ?></td>
                        <td data-label="Categoria"><?php echo hr($c['categoria'] ?? ''); ?></td>
                        <td data-label="Fornecedor"><?php echo hr($c['fornecedor'] ?? ''); ?></td>
                        <td data-label="Nota fiscal"><?php echo !empty($c['nota_fiscal']) ? hr($c['nota_fiscal']) : '—'; ?></td>
                        <td data-label="Valor" data-order="<?php echo (float)$c['valor']; ?>"><?php echo cap_money($c['valor']); ?></td>
                        <td data-label="Situação"><span class="st-badge st-<?php echo $st; ?>"><?php echo $st; ?></span></td>
                        <td data-label="Pagamento"><?php echo $c['data_pagamento']?date('d/m/Y',strtotime($c['data_pagamento'])):'—'; ?></td>
                        <td data-label="Forma"><?php echo $c['forma_pagamento']?hr($c['forma_pagamento']):'—'; ?></td>
                        <td data-label="Anexos" class="text-center">
                            <?php $nax = $anexosPorConta[(int)$c['id']] ?? 0; if ($nax > 0): ?>
                            <button type="button" class="btn btn-soft btn-sm btn-anexos" data-id="<?php echo (int)$c['id']; ?>" data-titulo="<?php echo hr($c['titulo']); ?>" title="Ver anexos">
                                <i class="fa fa-paperclip"></i> <?php echo $nax; ?>
                            </button>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="relAnexosModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <span style="width:38px;height:38px;border-radius:10px;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center"><i class="fa fa-paperclip"></i></span>
          <div><div style="font-weight:800;font-size:1.05rem">Anexos</div><div style="font-size:.8rem;color:#64748b" id="relAxSub">Comprovantes e documentos</div></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div id="relAxScreenList">
          <div id="relAxList"><div class="text-center text-muted py-3">Carregando…</div></div>
        </div>
        <div id="relAxScreenView" style="display:none">
          <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
            <button class="btn btn-outline-secondary btn-sm" id="relAxBack"><i class="fa fa-arrow-left"></i> Voltar</button>
            <span class="fw-bold text-truncate" id="relAxViewName" style="max-width:45%"></span>
            <span class="ms-auto d-flex gap-2">
              <a class="btn btn-outline-primary btn-sm" id="relAxOpenTab" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Nova aba</a>
              <a class="btn btn-primary btn-sm" id="relAxDownload"><i class="fa fa-download"></i> Baixar</a>
            </span>
          </div>
          <div id="relAxViewerBody"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php @include(__DIR__ . '/../rodape.php'); ?>
<script>
(function(){
  if(typeof Chart==='undefined') return;
  Chart.defaults.font.family="Inter, system-ui, sans-serif";
  var brl=function(v){ return 'R$ '+Number(v).toLocaleString('pt-BR'); };
  new Chart(document.getElementById('chartCat'),{ type:'bar',
    data:{ labels:<?php echo json_encode($catLabels, JSON_UNESCAPED_UNICODE); ?>, datasets:[{ data:<?php echo json_encode(array_map(fn($v)=>round($v,2),$catVals)); ?>, backgroundColor:'#4f46e5', borderRadius:6 }] },
    options:{ plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback:brl } } } } });
  new Chart(document.getElementById('chartMes'),{ type:'line',
    data:{ labels:<?php echo json_encode($mesLabels); ?>, datasets:[{ data:<?php echo json_encode(array_map(fn($v)=>round($v,2),$mesVals)); ?>, borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.15)', fill:true, tension:.35 }] },
    options:{ plugins:{legend:{display:false}}, scales:{ y:{ ticks:{ callback:brl } } } } });
})();
</script>
<script>
(function(){
  var modalEl=document.getElementById("relAnexosModal");
  if(!modalEl || typeof bootstrap==="undefined") return;
  var bs=new bootstrap.Modal(modalEl);
  var $sub=document.getElementById("relAxSub"),
      $list=document.getElementById("relAxList"),
      $scrList=document.getElementById("relAxScreenList"),
      $scrView=document.getElementById("relAxScreenView"),
      $vName=document.getElementById("relAxViewName"),
      $vBody=document.getElementById("relAxViewerBody"),
      $open=document.getElementById("relAxOpenTab"),
      $dl=document.getElementById("relAxDownload");

  function esc(s){ return (s==null?"":String(s)).replace(/[&<>\"']/g,function(m){return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m]; }); }
  function fmtSize(b){ b=Number(b||0); if(b<1024) return b+" B"; if(b<1048576) return (b/1024).toFixed(1)+" KB"; return (b/1048576).toFixed(1)+" MB"; }
  function meta(name,mime){
    var n=((name||"")+" "+(mime||"")).toLowerCase(), c="#64748b", ic="fa-file", k="other";
    if(/pdf/.test(n)){ c="#ef4444"; ic="fa-file-pdf-o"; k="pdf"; }
    else if(/(png|jpe?g|gif|webp|image\/)/.test(n)){ c="#0ea5e9"; ic="fa-file-image-o"; k="image"; }
    else if(/(doc|word)/.test(n)){ c="#2563eb"; ic="fa-file-word-o"; }
    else if(/(xls|sheet|excel|csv)/.test(n)){ c="#16a34a"; ic="fa-file-excel-o"; }
    else if(/zip/.test(n)){ c="#a16207"; ic="fa-file-archive-o"; }
    else if(/(xml|ofx)/.test(n)){ c="#7c3aed"; ic="fa-file-code-o"; }
    else if(/txt/.test(n)){ c="#475569"; ic="fa-file-text-o"; }
    return {c:c, ic:ic, kind:k};
  }
  function showList(){ $scrView.style.display="none"; $scrList.style.display="block"; }
  function showView(){ $scrList.style.display="none"; $scrView.style.display="block"; }

  function ver(a, kind){
    $vName.textContent=a.nome_original;
    $open.href="anexos_baixar.php?id="+a.id+"&inline=1";
    $dl.href="anexos_baixar.php?id="+a.id;
    var url="anexos_baixar.php?id="+a.id+"&inline=1"; $vBody.innerHTML="";
    if(kind==="image"){ var img=document.createElement("img"); img.src=url; img.alt=a.nome_original; $vBody.appendChild(img); }
    else { var ifr=document.createElement("iframe"); ifr.src=url; $vBody.appendChild(ifr); }
    showView();
  }

  async function carregar(contaId){
    $list.innerHTML='<div class="text-center text-muted py-3">Carregando…</div>';
    try{
      var r=await fetch("anexos_listar.php?conta_id="+encodeURIComponent(contaId), {credentials:"same-origin"});
      var j=await r.json();
      if(j.status!=="success"){ $list.innerHTML='<div class="text-center text-muted py-3">Erro ao listar anexos.</div>'; return; }
      if(!j.anexos.length){ $list.innerHTML='<div class="text-center text-muted py-4"><i class="fa fa-inbox fa-2x"></i><br>Nenhum anexo nesta conta.</div>'; return; }
      $list.innerHTML="";
      j.anexos.forEach(function(a){
        var mi=meta(a.nome_original,a.mime), previa=(mi.kind==="pdf"||mi.kind==="image"), acts="";
        if(previa){ acts+='<button class="v" title="Visualizar"><i class="fa fa-eye"></i></button><button class="o" title="Nova aba"><i class="fa fa-external-link"></i></button>'; }
        acts+='<button class="d" title="Baixar"><i class="fa fa-download"></i></button>';
        var it=document.createElement("div"); it.className="ax-item";
        it.innerHTML='<div class="fi" style="background:'+mi.c+'"><i class="fa '+mi.ic+'"></i></div>'+
          '<div style="min-width:0;flex:1"><div class="nm">'+esc(a.nome_original)+'</div><div class="sub">'+fmtSize(a.tamanho)+" · "+esc(a.enviado_em||"")+(a.descricao?(" · "+esc(a.descricao)):"")+'</div></div>'+
          '<div class="acts">'+acts+'</div>';
        var v=it.querySelector(".v"),o=it.querySelector(".o"),d=it.querySelector(".d");
        if(v) v.onclick=function(){ ver(a, mi.kind); };
        if(o) o.onclick=function(){ window.open("anexos_baixar.php?id="+a.id+"&inline=1","_blank","noopener"); };
        if(d) d.onclick=function(){ window.location="anexos_baixar.php?id="+a.id; };
        $list.appendChild(it);
      });
    }catch(e){ $list.innerHTML='<div class="text-center text-muted py-3">Erro ao listar anexos.</div>'; }
  }

  document.getElementById("relAxBack").onclick=function(){ showList(); };
  modalEl.addEventListener("hidden.bs.modal", function(){ $vBody.innerHTML=""; showList(); });

  document.addEventListener("click", function(ev){
    var b=ev.target.closest(".btn-anexos"); if(!b) return;
    var id=b.dataset.id, titulo=b.dataset.titulo||"";
    $sub.textContent = titulo ? ("Conta: "+titulo) : "Comprovantes e documentos";
    showList(); bs.show(); carregar(id);
  });
})();
</script>
</body>
</html>
