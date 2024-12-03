<?php

include('../controller/db_functions.php');
#error_reporting(0);
#ini_set('display_errors', 0);
session_start();



$pdo = conectar();

                 
ini_set('display_errors', 1);
error_reporting(E_ALL);                          
$data_atual = '01/01/2023';


$d=  dateRange($data_atual );
$new_data_atual = array();
foreach ($d as $key) {

  $dataAto = $key;
  $dataProtocolo = $key.' 22:00:00';
  $strAnotacoes = ' ';
  $strLivro = '00001';

$busca_valor_pagamento = $pdo->prepare(" SELECT *, count(a.dataAto) AS count_protocolo
FROM (SELECT ID,dataProtocolo,dataAto,strLivro,strSelo FROM imoveis_registro_protocolo WHERE strSelo is not null and strLivro = '00001' and dataAto = '$dataAto') AS a  group BY a.dataAto;
");
$busca_valor_pagamento->execute();

$d = $busca_valor_pagamento->fetch(PDO::FETCH_ASSOC);

$count_protocolo = intval($d['count_protocolo']);

$strAnotacoes = "TERMO DE ENCERRAMENTO: Declaro encerrado o expediente de hoje (".date('d/m/Y',strtotime($dataAto)).") Às 18:00, com ".$count_protocolo." título(s) protocolado(s). Eu ___________________ Oficial Registrador, Dou Fé. <br>";
print_r($strAnotacoes);
    $stmt = $pdo->prepare("INSERT IGNORE into imoveis_registro_protocolo_encerramento_diario(dataAto,strAnotacoes,strLivro,dataProtocolo) values ('$dataAto','$strAnotacoes','$strLivro','$dataProtocolo')");
    $stmt->execute();
    



}

    
function dateRange( $first, $step = '+1 weekdays', $format = 'Y-m-d' ) {
    $dates = [];
    $current = strtotime( date_create(date('Y-m-d',strtotime($first)))->modify("+1 weekdays")->format("Y-m-d")   );
    $last = strtotime( date_create(date('Y-m-d',strtotime($first)))->modify("+30 weekdays")->format("Y-m-d")  );  #3 dias para o protesto

    while( $current <= $last ) {

        $dates[] = date( 'Y-m-d', $current );
        $current = strtotime( '+1 weekdays', $current );
    }

    return $dates;
}

function nextBusinessDay($date, $daysToSkip,$holidays,$temp_dias_semana){

    $day = date('Y-m-d',strtotime($date. ' + '.$daysToSkip.' weekdays'));

        if (!isset($temp_dias_semana)) {
            $temp_dias_semana = [];
        }

        if(!in_array($day,$holidays)){
        
                if (in_array($day,$temp_dias_semana)) {
                    
                    $day = date_create(date('Y-m-d',strtotime($day)))->modify("+1 weekdays")->format("Y-m-d"); 
                    return  @nextBusinessDay($day,0,$holidays,$temp_dias_semana);

                 }

            return @$day;

        } else {
            return @nextBusinessDay(date('Y-m-d',strtotime($date.' +1 weekdays')), $daysToSkip,$holidays);
        }
}