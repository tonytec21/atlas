<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection2.php');    
?>  
<?php include(__DIR__ . '/complementos_edicao/consultas.php'); ?> 
<?php include(__DIR__ . '/consultas.php'); ?> 
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Atlas - Editar Nota Devolutiva</title>  
    <?php include(__DIR__ . '/complementos_edicao/links.php'); ?> 
    <?php include(__DIR__ . '/style_nota.php'); ?>  
</head>  
<body class="light-mode">  
<?php include(__DIR__ . '/../menu.php'); ?>  
<?php include(__DIR__ . '/complementos_edicao/container.php'); ?> 
<?php include(__DIR__ . '/complementos_edicao/modais.php'); ?> 
<?php include(__DIR__ . '/complementos_edicao/scripts.php'); ?> 
</body>  
</html>