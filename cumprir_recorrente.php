<?php
session_start();
include(__DIR__.'/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/* ---------- sessão ---------- */
if(!isset($_SESSION['username'])){
    http_response_code(401); echo json_encode(['error'=>'Sessão expirada']); exit;
}

/* ---------- validação ---------- */
if($_SERVER['REQUEST_METHOD']!=='POST'){echo json_encode(['error'=>'Método inválido']);exit;}

$exec_id       = (int)($_POST['exec_id']??0);
$status        = $_POST['status']??'';
$justificativa = trim($_POST['justificativa']??'');

if(!$exec_id || !in_array($status,['cumprida','nao_cumprida','adiada'])){
    echo json_encode(['error'=>'Dados inválidos']); exit;
}
if($status==='nao_cumprida' && $justificativa===''){
    echo json_encode(['error'=>'Justificativa obrigatória']); exit;
}

/* ---------- usuário ---------- */
$stmt=$conn->prepare("SELECT id,nome_completo FROM funcionarios WHERE usuario=?");
$stmt->bind_param("s",$_SESSION['username']);$stmt->execute();
$user=$stmt->get_result()->fetch_assoc();
if(!$user){echo json_encode(['error'=>'Usuário não encontrado']);exit;}
$func_id=(int)$user['id'];$nome=$user['nome_completo'];

/* ---------- busca execução + tarefa ---------- */
$q="SELECT e.*, t.*
       FROM tarefas_recorrentes_exec e
 INNER JOIN tarefas_recorrentes t ON t.id=e.tarefa_id
      WHERE e.id=?";
$stmt=$conn->prepare($q);$stmt->bind_param("i",$exec_id);$stmt->execute();
$d=$stmt->get_result()->fetch_assoc();
if(!$d){echo json_encode(['error'=>'Execução não encontrada']);exit;}
if($d['funcionario_id']!=$func_id){echo json_encode(['error'=>'Sem permissão']);exit;}
if(in_array($d['status'],['cumprida','nao_cumprida'])){echo json_encode(['error'=>'Já finalizada']);exit;}

/* ---------- atualizar status ---------- */
$stmt=$conn->prepare("UPDATE tarefas_recorrentes_exec
                         SET status=?,justificativa=?,data_cumprimento=NOW()
                       WHERE id=?");
$stmt->bind_param("ssi",$status,$justificativa,$exec_id);$stmt->execute();

/* ---------- calcular próxima ---------- */
$prev=new DateTime($d['data_prevista']);$next=null;
switch($d['recurrence_type']){
 case 'diaria':      $next=$prev->modify('+1 day');break;
 case 'semanal':     $next=$prev->modify('+1 week');break;
 case 'quinzenal':   $next=$prev->modify('+15 day');break;
 case 'mensal':      $dia=(int)$d['dia_mes']?:$prev->format('d');
                     $next=$prev->modify('first day of next month');
                     $next->setDate($next->format('Y'),$next->format('m'),min($dia,$next->format('t')));break;
 case 'trimestral':  $next=$prev->modify('+3 month');break;
}
$h=explode(':',$d['hora_execucao']);$next->setTime($h[0],$h[1]??0,$h[2]??0);

/* ---------- vigência ---------- */
$nextStr=null;
if($next){
   if($d['fim_vigencia']){
       $lim=new DateTime($d['fim_vigencia'].' 23:59:59');
       if($next<=$lim) $nextStr=$next->format('Y-m-d H:i:s');
   }else $nextStr=$next->format('Y-m-d H:i:s');
}

/* ---------- grava próxima ---------- */
$stmt=$conn->prepare("UPDATE tarefas_recorrentes SET proxima_execucao=? WHERE id=?");
$stmt->bind_param("si",$nextStr,$d['tarefa_id']);$stmt->execute();

echo json_encode(['success'=>true,'next_execution'=>$nextStr]);
