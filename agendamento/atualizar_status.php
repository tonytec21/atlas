<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$id        = intval($_POST['id']   ?? 0);
$novo      = $_POST['status']      ?? '';
$novaData  = $_POST['nova_data']   ?? null;
$permitidos=['ativo','reagendado','cancelado','concluido'];

if(!$id || !in_array($novo,$permitidos)){die('Parâmetros inválidos');}

/* ---- verifica status atual ---- */
$atual = $conn->query("SELECT status FROM agendamentos WHERE id=$id")->fetch_assoc()['status'] ?? '';
if(!$atual){die('Registro não encontrado');}
if($atual==='concluido'){die('Agendamento já concluído; não pode ser alterado.');}

/* ---- monta update ---- */
if($novo==='reagendado'){
    if(!$novaData) die('Data de reagendamento obrigatória.');
    $stmt=$conn->prepare("UPDATE agendamentos SET status='reagendado', data_hora=?, data_reagendamento=? WHERE id=?");
    $stmt->bind_param('ssi',$novaData,$novaData,$id);
}else{
    $stmt=$conn->prepare("UPDATE agendamentos SET status=?, data_reagendamento=NULL WHERE id=?");
    $stmt->bind_param('si',$novo,$id);
}

if($stmt->execute()){
    echo 'ok';
}else{
    echo 'Erro ao atualizar';
}
