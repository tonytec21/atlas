<?php
session_start();
include(__DIR__.'/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/* ---------- sessão ---------- */
if(!isset($_SESSION['username'])){header('Location:login.php');exit;}
$u=$_SESSION['username'];

/* ---------- usuário ---------- */
$stmt=$conn->prepare("SELECT id,nome_completo FROM funcionarios WHERE usuario=?");
$stmt->bind_param("s",$u);$stmt->execute();
$func=$stmt->get_result()->fetch_assoc();
if(!$func){echo json_encode([]);exit;}
$fid=$func['id'];$nome=$func['nome_completo'];$agora=new DateTime();

/* ---------- primeira ocorrência helper ---------- */
function primeira(array $t){
  $ini=new DateTime($t['inicio_vigencia'].' '.$t['hora_execucao']);
  switch($t['recurrence_type']){
   case 'diaria':case 'quinzenal':case 'trimestral':return $ini;
   case 'semanal':
     $dow=$t['dia_semana'];$dt=clone $ini;
     while($dt->format('w')!=$dow)$dt->modify('+1 day');return $dt;
   case 'mensal':
     $dia=$t['dia_mes'];$base=$ini;$dias=$base->format('t');
     $diaAdj=min($dia,$dias);$p=new DateTime($base->format('Y-m-').str_pad($diaAdj,2,'0',STR_PAD_LEFT).' '.$t['hora_execucao']);
     if($p<$base){$p->modify('first day of next month');$dias=$p->format('t');$diaAdj=min($dia,$dias);$p->setDate($p->format('Y'),$p->format('m'),$diaAdj);}
     return $p;
  }
}

/* ---------- tarefas do funcionário na vigência ---------- */
$stmt=$conn->prepare(
 "SELECT * FROM tarefas_recorrentes
   WHERE funcionario_id=?
     AND inicio_vigencia<=CURDATE()
     AND (fim_vigencia IS NULL OR fim_vigencia>=CURDATE())");
$stmt->bind_param("i",$fid);$stmt->execute();$r=$stmt->get_result();

$out=[];while($t=$r->fetch_assoc()){
 if(!$t['proxima_execucao']){
   $p=primeira($t); if($p){
      $pe=$p->format('Y-m-d H:i:s');
      $u2=$conn->prepare("UPDATE tarefas_recorrentes SET proxima_execucao=? WHERE id=?");
      $u2->bind_param("si",$pe,$t['id']);$u2->execute();
      $t['proxima_execucao']=$pe;
   }
 }
 if(!$t['proxima_execucao'])continue;
 $dt=new DateTime($t['proxima_execucao']);
 if($dt>$agora)continue;

 /* existe execução? */
 $dataPrev=$dt->format('Y-m-d H:i:s');
 $stmt2=$conn->prepare("SELECT id,status FROM tarefas_recorrentes_exec WHERE tarefa_id=? AND data_prevista=? LIMIT 1");
 $stmt2->bind_param("is",$t['id'],$dataPrev);$stmt2->execute();
 $ex=$stmt2->get_result()->fetch_assoc();
 if($ex){
    if(in_array($ex['status'],['cumprida','nao_cumprida']))continue;
    $exec_id=$ex['id'];
 }else{
    $ins=$conn->prepare("INSERT INTO tarefas_recorrentes_exec(tarefa_id,data_prevista,status,usuario_responsavel)
                         VALUES(?,?,'pendente',?)");
    $ins->bind_param("iss",$t['id'],$dataPrev,$nome);$ins->execute();$exec_id=$ins->insert_id;
 }
 $out[]=[
   'exec_id'=>$exec_id,
   'tarefa_id'=>$t['id'],
   'titulo'=>$t['titulo'],
   'descricao'=>$t['descricao'],
   'data_prevista'=>$dataPrev,
   'obrigatoria'=>(int)$t['obrigatoria']
 ];
}
header('Content-Type:application/json');echo json_encode($out);
