<?php
include(__DIR__.'/session_check.php');
checkSession();
include(__DIR__.'/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/* ========== Salvar nova tarefa ========== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['titulo'])) {
    $titulo         = $_POST['titulo'];
    $descricao      = $_POST['descricao'] ?? '';
    $funcionario_id = $_POST['funcionario_id']!=='' ? (int)$_POST['funcionario_id'] : null;
    $rec_type       = $_POST['recurrence_type'];
    $dia_semana     = $rec_type==='semanal' ? (int)$_POST['dia_semana'] : null;
    $dia_mes        = $rec_type==='mensal'  ? (int)$_POST['dia_mes']    : null;
    $hora_exec      = $_POST['hora_execucao'];
    $inicio         = $_POST['inicio_vigencia'];
    $fim            = $_POST['fim_vigencia'] ?: null;
    $obrigatoria    = empty($_POST['obrigatoria']) ? 0 : 1;

    $stmt=$conn->prepare(
      "INSERT INTO tarefas_recorrentes
       (titulo,descricao,funcionario_id,recurrence_type,dia_semana,dia_mes,
        hora_execucao,inicio_vigencia,fim_vigencia,obrigatoria)
       VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssississii",
        $titulo,$descricao,$funcionario_id,$rec_type,$dia_semana,$dia_mes,
        $hora_exec,$inicio,$fim,$obrigatoria);
    $stmt->execute();
    header('Location:tarefas_recorrentes.php?ok=1');
    exit;
}

/* ========== Dados ========== */
$funcionarios=$conn->query(
  "SELECT id,nome_completo FROM funcionarios ORDER BY nome_completo"
)->fetch_all(MYSQLI_ASSOC);

$tarefas=$conn->query(
  "SELECT tr.*,f.nome_completo
     FROM tarefas_recorrentes tr
LEFT JOIN funcionarios f ON f.id=tr.funcionario_id
 ORDER BY tr.id DESC"
)->fetch_all(MYSQLI_ASSOC);

$historico=$conn->query(
  "SELECT e.*,tr.titulo
     FROM tarefas_recorrentes_exec e
     JOIN tarefas_recorrentes tr ON tr.id=e.tarefa_id
 ORDER BY e.id DESC LIMIT 150"
)->fetch_all(MYSQLI_ASSOC);

/* ========== Estatísticas p/ gráficos ========== */
$totais=['cumprida'=>0,'nao_cumprida'=>0,'adiada'=>0];
$porFuncionario=[];
foreach($historico as $h){
    $st=$h['status'];
    if(isset($totais[$st])) $totais[$st]++;
    $u=$h['usuario_responsavel'] ?: 'Desconhecido';
    if(!isset($porFuncionario[$u]))
        $porFuncionario[$u]=['cumprida'=>0,'nao_cumprida'=>0,'adiada'=>0];
    if(isset($porFuncionario[$u][$st])) $porFuncionario[$u][$st]++;
}
ksort($porFuncionario);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Atlas — Tarefas Recorrentes</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="style/css/bootstrap.min.css">
<link rel="stylesheet" href="style/css/font-awesome.min.css">
<link rel="icon" href="style/img/favicon.png" type="image/png">  
<?php include(__DIR__.'/style/style_index.php'); ?>
<!-- libs -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
<style>
/* ======= Layout / cores ======= */
.main-container{max-width:1200px;margin:30px auto;padding:0 20px}
.page-title{font-size:28px;font-weight:600;margin-bottom:5px}
.title-divider{height:4px;width:120px;background:#0d6efd;margin-bottom:28px;border-radius:2px}
.form-card,.table-box,.chart-box{background:#fff;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,.05)}
.form-card{padding:25px 30px;margin-bottom:35px}
.badge-rec{background:#e1f5fe;color:#0288d1;font-size:12px;padding:4px 12px;border-radius:30px;font-weight:600}
.btn-primary{border-radius:8px;font-weight:600;padding:10px 26px}
.equal-h{height:calc(2.875rem + 2px);border-radius:8px;border:1.5px solid var(--input-border);padding:0.625rem 1rem;width: 100%;}
.form-label{margin-bottom: 0.2rem;margin-top: 0.7rem;}
/* ----- Tabela config ----- */
.table-rec{width:100%;border-collapse:separate;border-spacing:0 6px}
.table-rec thead th{background:#eaf3ff;font-size:12px;font-weight:600;padding:9px 8px;white-space:nowrap}
.table-rec tbody tr{background:#f7fbff}.table-rec tbody tr:hover{background:#eef7ff}
.table-rec td{padding:8px;border-top:1px solid #fff;vertical-align:middle;font-size:13px}
.table-rec input,.table-rec select,.table-rec textarea{font-size:12px;padding:.35rem .55rem;border-radius:6px}
.table-rec textarea{resize:vertical;min-height:32px}
.status-suspensa{background:#fff3cd;color:#856404;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:600}
.status-ativa{background:#e8f5e9;color:#2e7d32;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:600}
.inline-save-btn{border:none;border-radius:6px;padding:6px 10px;font-size:11px;font-weight:600}
.inline-save-btn.btn-success{background:#198754;color:#fff}
.search-input{max-width:240px}
/* ----- Histórico ----- */
.table-hist{width:100%;border-collapse:collapse}
.table-hist thead th{background:#343a40;color:#fff;font-size:12px;font-weight:600;padding:8px}
.table-hist tbody td{padding:7px;border-bottom:1px solid #e0e0e0;font-size:13px}
.h-nao{background:#ffe8e8}.h-adiada{background:#fff5d6}
/* ----- Gráficos ----- */
.chart-box{padding:20px;margin-top:35px}
.chart-box canvas{width:100%!important;height:300px!important;aspect-ratio:1/1}
/* ----- Responsivo ----- */
@media(max-width:992px){
  .table-rec thead{display:none}
  .table-rec tbody tr{display:flex;flex-direction:column;padding:10px;border-radius:8px}
  .table-rec td{width:100%;display:flex;flex-direction:column}
  .search-input{max-width:100%}
}
@media(max-width:768px){
  .page-title{font-size:24px}
  .form-card{padding:20px}
}
/* ----- Dark mode ----- */
body.dark-mode .form-card,
body.dark-mode .table-box,
body.dark-mode .chart-box{background:#1e1e1e}
body.dark-mode .table-rec thead th{background:#2c3b4a;color:#e0e0e0}
body.dark-mode .table-rec tbody tr{background:#25303a}
body.dark-mode .table-rec tbody tr:hover{background:#313e49}
body.dark-mode .table-rec input,
body.dark-mode .table-rec select,
body.dark-mode .table-rec textarea{background:#2a2a2a;color:#e0e0e0;border:1px solid #444}
body.dark-mode .table-hist thead th{background:#1f1f1f}
</style>
</head>
<body class="light-mode">
<?php include(__DIR__.'/menu.php'); ?>

<div class="main-container">
  <h1 class="page-title"></h1><div class="title-divider"></div>

  <?php if(isset($_GET['ok'])): ?>
    <script>Swal.fire({icon:'success',title:'Tarefa cadastrada!',timer:2000,showConfirmButton:false});</script>
  <?php endif; ?>

  <!-- ========== Formulário ========== -->
  <div class="form-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
      <h5 class="m-0"><i class="fa fa-plus-circle"></i> Nova Tarefa Recorrente</h5>
      <span class="badge-rec">Configuração</span>
    </div>

    <form method="post" class="row g-3">
      <div class="col-md-8">
        <label class="form-label">Título <span class="text-danger">*</span></label>
        <input name="titulo" class="form-control equal-h" required>
      </div>

      <div class="col-md-4">
        <label class="form-label">Responsável</label><br>
        <select name="funcionario_id" class="form-select equal-h">
          <option value="">Selecione...</option>
          <?php foreach($funcionarios as $f): ?>
            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nome_completo']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label">Descrição</label>
        <textarea name="descricao" rows="2" class="form-control"></textarea>
      </div>

      <div class="col-md-4">
        <label class="form-label">Recorrência <span class="text-danger">*</span></label><br>
        <select name="recurrence_type" id="recurrence_type" class="form-select equal-h" required>
          <option value="diaria">Diária</option>
          <option value="semanal">Semanal</option>
          <option value="quinzenal">Quinzenal</option>
          <option value="mensal">Mensal</option>
          <option value="trimestral">Trimestral</option>
        </select>
      </div>

      <div class="col-md-4 d-none" id="field_dia_semana">
        <label class="form-label">Dia da Semana</label>
        <select name="dia_semana" class="form-select equal-h">
          <?php $map=[1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',0=>'Dom'];
                foreach($map as $k=>$v) echo "<option value='$k'>$v</option>"; ?>
        </select>
      </div>

      <div class="col-md-4 d-none" id="field_dia_mes">
        <label class="form-label">Dia do Mês (1-31)</label>
        <input name="dia_mes" type="number" min="1" max="31" class="form-control equal-h">
      </div>

      <div class="col-md-2">
        <label class="form-label">Hora <span class="text-danger">*</span></label>
        <input name="hora_execucao" type="time" class="form-control equal-h" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Início da Vigência <span class="text-danger">*</span></label>
        <input name="inicio_vigencia" type="date" class="form-control equal-h" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Fim da Vigência</label>
        <input name="fim_vigencia" type="date" class="form-control equal-h">
      </div>

      <div class="col-md-2 form-check ms-2 mt-2">
        <input class="form-check-input" type="checkbox" id="obrigatoria" name="obrigatoria" checked>
        <label class="form-check-label" for="obrigatoria">Obrigatória</label>
      </div>

      <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-primary"><i class="fa fa-save"></i> Salvar</button>
      </div>
    </form>
  </div><!-- /form-card -->

  <!-- ========== Listagem / edição in-line ========== -->
  <div class="table-box p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
      <h6 class="fw-bold mb-0"><i class="fa fa-cogs"></i> Tarefas Cadastradas</h6>
    </div>

    <div class="table-responsive">
      <table class="table-rec">
        <thead>
          <tr>
            <th>ID</th><th>Título / Descrição</th><th>Responsável</th><th>Rec.</th>
            <th>Dia S.</th><th>Dia M.</th><th>Hora</th><th>Vigência</th>
            <th>Obr.</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody id="tbodyRec">
        <?php if(!$tarefas): ?>
          <tr><td colspan="11" class="text-center text-muted bg-white">Nenhuma tarefa.</td></tr>
        <?php endif; ?>
        <?php foreach($tarefas as $t):
              $susp=is_null($t['funcionario_id']);
              $badge=$susp?'<span class="status-suspensa">Suspensa</span>':'<span class="status-ativa">Ativa</span>';?>
          <tr data-id="<?= $t['id'] ?>">
            <td><?= $t['id'] ?></td>
            <td>
              <input class="form-control mb-1 input-titulo" value="<?= htmlspecialchars($t['titulo']) ?>">
              <textarea class="form-control input-descricao" rows="1"><?= htmlspecialchars($t['descricao']) ?></textarea>
            </td>
            <td>
              <select class="form-select select-func">
                <option value="">Selecione...</option>
                <?php foreach($funcionarios as $f): ?>
                  <option value="<?= $f['id'] ?>"<?= $t['funcionario_id']==$f['id']?' selected':'' ?>>
                    <?= htmlspecialchars($f['nome_completo']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <select class="form-select select-rec">
                <?php $types=['diaria'=>'Diária','semanal'=>'Semanal','quinzenal'=>'Quinzenal',
                              'mensal'=>'Mensal','trimestral'=>'Trimestral'];
                      foreach($types as $val=>$lab){
                        $sel=$t['recurrence_type']==$val?' selected':'';echo"<option value='$val'$sel>$lab</option>";
                      } ?>
              </select>
            </td>
            <td>
              <select class="form-select select-ds <?= $t['recurrence_type']=='semanal'?'':'d-none' ?>">
                <?php foreach($map as $k=>$v){
                      $sel=$t['dia_semana']===$k?' selected':'';echo"<option value='$k'$sel>$v</option>";
                    } ?>
              </select>
            </td>
            <td>
              <input type="number" class="form-control input-dm <?= $t['recurrence_type']=='mensal'?'':'d-none' ?>"
                     min="1" max="31" value="<?= $t['dia_mes'] ?>">
            </td>
            <td><input type="time" class="form-control input-hora" value="<?= substr($t['hora_execucao'],0,5) ?>"></td>
            <td>
              <input type="date" class="form-control mb-1 input-inicio" value="<?= $t['inicio_vigencia'] ?>">
              <input type="date" class="form-control input-fim" value="<?= $t['fim_vigencia'] ?>">
            </td>
            <td class="text-center">
              <input type="checkbox" class="form-check-input chk-ob"<?= $t['obrigatoria']?' checked':'' ?>>
            </td>
            <td class="td-status"><?= $badge ?></td>
            <td>
              <button class="inline-save-btn btn btn-success btn-save"><i class="fa fa-floppy-o"></i></button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div><!-- /table-box -->

  <!-- ========== Histórico ========== -->
  <div class="table-box p-3 mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
      <h6 class="fw-bold mb-0"><i class="fa fa-history"></i> Histórico de Cumprimento</h6>
      <input id="searchHist" class="form-control form-control-sm search-input" placeholder="Pesquisar...">
    </div>

    <div class="table-responsive">
      <table class="table-hist">
        <thead>
          <tr><th>ID</th><th>Tarefa</th><th>Prevista</th><th>Status</th><th>Justificativa</th><th>Data Final</th><th>Usuário</th></tr>
        </thead>
        <tbody id="tbodyHist">
        <?php if(!$historico): ?>
          <tr><td colspan="7" class="text-center">Nenhum registro.</td></tr>
        <?php endif; ?>
        <?php foreach($historico as $h):
              $cls=$h['status']=='nao_cumprida'?'h-nao':($h['status']=='adiada'?'h-adiada':''); ?>
          <tr class="<?= $cls ?>">
            <td><?= $h['id'] ?></td>
            <td><?= htmlspecialchars($h['titulo']) ?></td>
            <td><?= date('d/m/Y H:i',strtotime($h['data_prevista'])) ?></td>
            <td style="text-transform:capitalize"><?= str_replace('_',' ',$h['status']) ?></td>
            <td><?= htmlspecialchars($h['justificativa']) ?></td>
            <td><?= $h['data_cumprimento']?date('d/m/Y H:i',strtotime($h['data_cumprimento'])):'-' ?></td>
            <td><?= htmlspecialchars($h['usuario_responsavel']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div><!-- /histórico -->

  <!-- ========== Gráficos ========== -->
  <div class="row">
    <div class="col-lg-6">
      <div class="chart-box">
        <h6 class="fw-bold mb-3"><i class="fa fa-circle-o-notch"></i> Cumpridas × Não × Adiadas</h6>
        <canvas id="donutTotais"></canvas>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="chart-box">
        <h6 class="fw-bold mb-3"><i class="fa fa-bars"></i> Cumprimento por Funcionário</h6>
        <canvas id="barFunc"></canvas>
      </div>
    </div>
  </div>
</div><!-- /main-container -->

<?php include(__DIR__.'/rodape.php'); ?>
<script src="script/jquery-3.6.0.min.js"></script>
<script src="script/bootstrap.bundle.min.js"></script>
<script>
/* === Campos condicionais (form) === */
$('#recurrence_type').on('change',function(){
  const v=$(this).val();
  $('#field_dia_semana').toggleClass('d-none',v!=='semanal');
  $('#field_dia_mes').toggleClass('d-none',v!=='mensal');
}).trigger('change');

/* === Ajustes de linha ao mudar tipo === */
$(document).on('change','.select-rec',function(){
  const row=$(this).closest('tr'),v=$(this).val();
  row.find('.select-ds').toggleClass('d-none',v!=='semanal');
  row.find('.input-dm').toggleClass('d-none',v!=='mensal');
});

/* === Salvar in-line === */
$(document).on('click','.btn-save',function(e){
  e.preventDefault();
  const r=$(this).closest('tr');r.addClass('opacity-50');
  $.post('atualizar_tarefa_recorrente.php',{
    id:r.data('id'),
    titulo:r.find('.input-titulo').val(),
    descricao:r.find('.input-descricao').val(),
    funcionario_id:r.find('.select-func').val(),
    recurrence_type:r.find('.select-rec').val(),
    dia_semana:r.find('.select-ds').val(),
    dia_mes:r.find('.input-dm').val(),
    hora_execucao:r.find('.input-hora').val(),
    inicio_vigencia:r.find('.input-inicio').val(),
    fim_vigencia:r.find('.input-fim').val(),
    obrigatoria:r.find('.chk-ob').is(':checked')?1:0
  },resp=>{
    if(resp.success){
      const badge=resp.suspensa?'<span class="status-suspensa">Suspensa</span>':'<span class="status-ativa">Ativa</span>';
      r.find('.td-status').html(badge);
      Swal.fire({icon:'success',title:'Salvo!',timer:1500,showConfirmButton:false});
    }else Swal.fire({icon:'error',title:'Erro',text:resp.error||'Falha'});
  },'json').fail(()=>Swal.fire({icon:'error',title:'Erro',text:'Requisição falhou'}))
  .always(()=>r.removeClass('opacity-50'));
});

/* === Pesquisa === */
$('#searchConfig').on('keyup',function(){
  const v=$(this).val().toLowerCase();
  $('#tbodyRec tr').each(function(){
    let txt=$(this).text().toLowerCase();
    $(this).find('input[type=text],textarea').each(function(){txt+=' '+$(this).val().toLowerCase();});
    $(this).find('select').each(function(){txt+=' '+$(this).find('option:selected').text().toLowerCase();});
    $(this).toggle(txt.indexOf(v)>-1);
  });
});
$('#searchHist').on('keyup',function(){
  const v=$(this).val().toLowerCase();
  $('#tbodyHist tr').each(function(){
    $(this).toggle($(this).text().toLowerCase().indexOf(v)>-1);
  });
});

/* === Gráficos (Chart.js) === */
Chart.register(ChartDataLabels);
Chart.defaults.font.family="'Segoe UI',sans-serif";
Chart.defaults.color="#666";

/* Donut Totais */
(() =>{
  const data=<?php echo json_encode($totais); ?>;
  const total=data.cumprida+data.nao_cumprida+data.adiada||1;
  new Chart(document.getElementById('donutTotais'),{
    type:'doughnut',
    data:{
      labels:['Cumprida','Não Cumprida','Adiada'],
      datasets:[{
        data:[data.cumprida,data.nao_cumprida,data.adiada],
        backgroundColor:['#27ae60','#e74c3c','#f1c40f'],
        borderWidth:0
      }]
    },
    options:{
      cutout:'70%',
      plugins:{
        legend:{display:false},
        datalabels:{
          formatter:(v)=>((v/total*100)||0).toFixed(0)+'%',
          color:'#fff',font:{weight:'600',size:14}
        }
      }
    }
  });
})();

/* Barra horizontal por funcionário */
(() =>{
  const pf=<?php echo json_encode($porFuncionario); ?>;
  const nomes=Object.keys(pf);
  const c=nomes.map(n=>pf[n].cumprida);
  const n=nomes.map(n=>pf[n].nao_cumprida+pf[n].adiada);

  new Chart(document.getElementById('barFunc'),{
    type:'bar',
    data:{
      labels:nomes,
      datasets:[
        {label:'Cumpridas',data:c,backgroundColor:'#27ae60',borderRadius:6,stack:'s'},
        {label:'Não Cumpridas / Adiadas',data:n,backgroundColor:'#e67e22',borderRadius:6,stack:'s'}
      ]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false,
      indexAxis:'y',
      scales:{
        x:{beginAtZero:true,grid:{display:false}},
        y:{grid:{display:false}}
      },
      plugins:{
        legend:{position:'bottom'},
        datalabels:{
          anchor:'center',align:'center',color:'#fff',font:{weight:'600',size:12}
        }
      }
    }
  });
})();
</script>
</body>
</html>
