<?php
include(__DIR__.'/session_check.php');
checkSession();
include(__DIR__.'/db_connection.php');

$id = intval($_POST['id'] ?? 0);
if(!$id){ http_response_code(400); exit; }

$stmt = $conn->prepare("UPDATE agendamento_anexos
                        SET status='excluido'
                        WHERE id=?");
$stmt->bind_param('i',$id);

if($stmt->execute()){
    echo 'ok';
}else{
    http_response_code(500);
    echo 'erro';
}
