<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
require_once(__DIR__ . '/db_connection2.php');  
?>  
<?php include(__DIR__ . '/complementos_index/consultas.php'); ?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <title>Atlas - Pesquisa de Notas Devolutivas</title>  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    
<?php include(__DIR__ . '/complementos_index/links.php'); ?> 
<?php include(__DIR__ . '/style_nota.php'); ?>
<?php include(__DIR__ . '/style_nota_extra.php'); ?>   
<?php include(__DIR__ . '/complementos_index/style.php'); ?>
</head>  
<body class="light-mode">  
<?php include(__DIR__ . '/../menu.php'); ?>  
<?php include(__DIR__ . '/complementos_index/container.php'); ?>
<?php include(__DIR__ . '/complementos_index/modais.php'); ?>
<?php include(__DIR__ . '/complementos_index/scripts.php'); ?>
<?php include(__DIR__ . '/../rodape.php'); ?>  
</body>  
</html>