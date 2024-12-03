<?php

function conectar(){

    try{
    
    $pdo = new PDO("mysql:host=localhost;dbname=bookc", "root","");
        //$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        $pdo->exec("SET CHARACTER SET utf8");
    
    }
    
    catch (PDOException $e) {
    
    //echo '<pre>'.$e->getMessage().'</pre>';
    }
    
    return $pdo;
    }


function dateRange( $first, $step = '+1 weekdays', $format = 'Y-m-d' ) {
    $dates = [];
    $current = strtotime( date_create(date('Y-m-d',strtotime($first)))->modify("+1 weekdays")->format("Y-m-d")   );
    $last = strtotime( date_create(date('Y-m-d',strtotime($first)))->modify("+3 weekdays")->format("Y-m-d")  );  #3 dias para o protesto

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

function get_data_vencimento($first){
	
	#$dias = array('2022-10-25','2022-10-26','2022-10-27');

    $dias = [];
    $current = strtotime( date_create(date('Y-m-d',strtotime($first)))->modify("+1 weekdays")->format("Y-m-d")   );
    $last = strtotime( date_create(date('Y-m-d',strtotime($first)))->modify("+3 weekdays")->format("Y-m-d")  );  #3 dias para o protesto

    while( $current <= $last ) {

        $dias[] = date( 'Y-m-d', $current );
        $current = strtotime( '+1 weekdays', $current );
    }

        $pdo = conectar();
        $query = 
        $stmt =  $pdo->prepare("SELECT *,CONCAT(YEAR(NOW()),'-', data_americano) AS feriado
        FROM protesto_dias_feriados");
	    $stmt->execute();
        $row = $stmt->fetchall(PDO::FETCH_ASSOC);
        $feriado = [];
        foreach ($row as $key) {
            $feriado[] = $key['feriado'];
        }
        print_r('<pre>');
        print_r($feriado);
        print_r('</pre>');

       # $feriado = array('2022-10-21','2022-10-27','2022-10-25','2022-11-02','2022-11-01','2022-11-03','2022-11-04');
        $dias_semana =  [];


            #Compara e verifica feriado
            foreach ($dias as $dias_compare) {

                if ( in_array($dias_compare, $feriado) ){

                    $temp = date_create(date('Y-m-d',strtotime(end($dias))))->format("Y-m-d"); 
                #  $temp = date_create(date('Y-m-d',strtotime(end($dias))))->modify("+1 weekdays")->format("Y-m-d"); 
                    $dias_semana[] =     nextBusinessDay($temp,1,$feriado,$dias_semana);
                    #  array_push($dias, nextBusinessDay($temp,1,$feriado));

                }else{
                        
                    $dias_semana[] = date('Y-m-d',strtotime($dias_compare));

                }

                usort($dias_semana, function ($a, $b) {
                    return strtotime($a) - strtotime($b);
                });
        
            }

            //Verifica por via das duvidas se existe algum sabado e domingo
            foreach  ($dias_semana as $str) :

                $str =  date('Y-m-d',strtotime($str));
                $date = date_create($str);
                $data_pre = date_format($date, 'N');

                    if(!in_array($data_pre, array(6,7))){
                    #echo   $data_final[] = $addtime->format('Y-m-d');
                        $data_final[] = date_format($date, 'Y-m-d');

                    } elseif($data_pre == 6){
                        
                        $data_final[] =   date_create(date_format($date, 'Y-m-d'))->modify("+2 weekdays")->format("Y-m-d"); 

                    } elseif($data_pre == 7){
                        
                        $data_final[] =  date_create(date_format($date, 'Y-m-d'))->modify("+1 weekdays")->format("Y-m-d");

                    }
            
             endforeach;
    

        usort($data_final, function ($a, $b) {
            return strtotime($a) - strtotime($b);
        });

   
    // echo '<pre>';
    // print_r($data_final);
    // echo '</pre>';
   # return $data_final;
    $pdo = NULL;
    
    return max($data_final);

}



echo '<pre>';
print_r(get_data_vencimento('2022-10-20'));
echo '</pre>';

// echo '<pre>';
// print_r(dateRange('2022-10-20'));
// echo '</pre>';
