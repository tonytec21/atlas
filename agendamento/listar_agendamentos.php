<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

/* ---------- filtros ---------- */
$where=[];

if(!empty($_GET['nome']))
    $where[]="nome_solicitante LIKE '%".$conn->real_escape_string($_GET['nome'])."%'";
if(!empty($_GET['servico']))
    $where[]="servico LIKE '%".$conn->real_escape_string($_GET['servico'])."%'";
$filtrou=false;
if(!empty($_GET['status'])){
    $where[]="status='".$conn->real_escape_string($_GET['status'])."'";
    $filtrou=true;
}
if(!empty($_GET['inicio']) && !empty($_GET['fim'])){
    $i=$conn->real_escape_string($_GET['inicio']);
    $f=$conn->real_escape_string($_GET['fim']);
    $where[]="DATE(data_hora) BETWEEN '$i' AND '$f'";
}
/* baseline = primeira carga → oculta concluído + cancelado */
if(isset($_GET['baseline']) && !$filtrou){
    $where[]="status NOT IN ('cancelado','concluido')";
}

$sql="SELECT * FROM agendamentos".($where?" WHERE ".implode(" AND ",$where):"")." ORDER BY data_hora DESC";
$r=$conn->query($sql);

if(!$r || !$r->num_rows){
    echo "<div class='col-12 text-center text-muted py-4'>Nenhum agendamento encontrado.</div>";
    exit;
}

while($row=$r->fetch_assoc()){
    $id       = $row['id'];
    $nome     = htmlspecialchars($row['nome_solicitante']);
    $servico  = htmlspecialchars($row['servico']);
    $status   = $row['status'];
    $statusCap= ucfirst($status);
    $obs      = htmlspecialchars($row['observacoes']);

    /* Data exibida: se reagendado → data_reagendamento */
    $rawData = ($status==='reagendado' && $row['data_reagendamento'])
               ? $row['data_reagendamento']
               : $row['data_hora'];
    $dhFmt = date('d/m/Y H:i', strtotime($rawData));
    $dhInp = date('Y-m-d\TH:i', strtotime($row['data_hora']));
    $reagInp = $row['data_reagendamento']
               ? date('Y-m-d\TH:i', strtotime($row['data_reagendamento']))
               : '';

    /* badge */
    $badgeClass = match($status){
        'concluido'  => 'bg-success text-white',
        'ativo'      => 'bg-secondary text-white',
        'reagendado' => 'bg-warning text-dark',
        'cancelado'  => 'bg-danger text-white',
        default      => 'bg-light text-dark'
    };
    $lock = $status==='concluido' ? 'badge-locked' : '';

    /* data-attributes para JS */
    $dataAttr = "
        data-id='$id'
        data-nome=\"$nome\"
        data-servico=\"$servico\"
        data-hora=\"$dhInp\"
        data-reag=\"$reagInp\"
        data-hora_formatada=\"$dhFmt\"
        data-status=\"$status\"
        data-status_formatado=\"$statusCap\"
        data-obs=\"$obs\"
    ";

    echo "
    <div class='col-12 col-md-6 col-xl-4'>
      <div class='card card-agendamento mb-3' $dataAttr>
        <div class='card-body'>
          <h5 class='card-title'><i class='fas fa-user me-2'></i>$nome</h5>
          <p><strong>Serviço:</strong> $servico</p>
          <p><strong>Data:</strong> $dhFmt</p>

          <span class='badge badge-status $badgeClass $lock'
                data-id='$id' data-status='$status'>$statusCap</span>

          <div class='acoes mt-3'>
            <button class='btn btn-sm btn-outline-primary btn-editar'
                title='Editar'
                data-id='$id'
                data-nome=\"$nome\"
                data-servico=\"$servico\"
                data-hora=\"$dhInp\"
                data-reag=\"$reagInp\"
                data-status='$status'
                data-obs=\"$obs\">
              <i class='fas fa-edit'></i>
            </button>
          </div>
        </div>
      </div>
    </div>";
}
