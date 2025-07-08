<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$id = intval($_GET['id'] ?? 0);
$r  = $conn->query("SELECT id,caminho,nome_original FROM agendamento_anexos
                    WHERE agendamento_id=$id AND status='ativo'");
if(!$r || $r->num_rows===0){exit;}

while($a=$r->fetch_assoc()){
    $file = htmlspecialchars($a['caminho']);
    $nome = htmlspecialchars($a['nome_original']);
     $linha = "<div class='d-flex align-items-center gap-2 mb-1'>
             <span class='flex-grow-1'>$nome</span>
             <button class='btn btn-sm btn-outline-info ver-anexo' data-file='$file'>
                 <i class='fas fa-eye'></i>
             </button>";

 /* botão de remoção aparece somente na listagem de Edição
    (o JS passa ?edit=1)                               */
 if(isset($_GET['edit'])) {
     $linha .= "<button class='btn btn-sm btn-outline-danger rem-anexo'
                        data-anexo='{$a['id']}'>
                    <i class='fas fa-trash-alt'></i>
                </button>";
 }
 $linha .= "</div>";
 echo $linha;
}
