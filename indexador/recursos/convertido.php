<?php
	$recebeTexto = $_POST['textarea'];
	$recebeTamanho = $_POST['tamanho'];
	
	include('core/conversor.php');
?>
<html>
<title>Conversor Braille</title>
<head>
</head>
<link rel="stylesheet" type="text/css" href="style.css"/>
<body>
<div id="pagina">
	<div id="topo">Conversor Braille</div>
    <div id="texto" style="font-size:<?php echo $recebeTamanho; ?>px"><?php echo ConverteBraille($recebeTexto);?></div>
	<a href="index.html"><div id="bt_home">Voltar para o Início</div></a>
    </form>
</div>
</body>
</html>