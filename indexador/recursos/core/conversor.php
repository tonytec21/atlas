<?php
	
	/*
	Conversor de Texto
	Autor: José Rafael de Santana
	URL: qiinformatica.blogspot.com.br
	Versão:1.0
	*/
	
	function ConverteBraille($texto){
		/*Alfabeto*/
		$texto=str_replace("a","⠁",$texto);
		$texto=str_replace("b","⠃",$texto);
		$texto=str_replace("c","⠉",$texto);
		$texto=str_replace("d","⠙",$texto);
		$texto=str_replace("e","⠑",$texto);
		$texto=str_replace("f","⠋",$texto);
		$texto=str_replace("g","⠛",$texto);
		$texto=str_replace("h","⠓",$texto);
		$texto=str_replace("i","⠊",$texto);
		$texto=str_replace("j","⠚",$texto);
		$texto=str_replace("k","⠅",$texto);
		$texto=str_replace("l","⠇",$texto);
		$texto=str_replace("m","⠍",$texto);
		$texto=str_replace("n","⠝",$texto);
		$texto=str_replace("o","⠕",$texto);
		$texto=str_replace("p","⠏",$texto);
		$texto=str_replace("q","⠟",$texto);
		$texto=str_replace("r","⠗",$texto);
		$texto=str_replace("s","⠎",$texto);
		$texto=str_replace("t","⠞",$texto);
		$texto=str_replace("u","⠥",$texto);
		$texto=str_replace("v","⠧",$texto);
		$texto=str_replace("w","⠺",$texto);
		$texto=str_replace("x","⠭",$texto);
		$texto=str_replace("y","⠽",$texto);
		$texto=str_replace("z","⠵",$texto);
		
		$texto=str_replace("ç","⠯",$texto);
		$texto=str_replace("Ç","⠠⠯",$texto);
		
		$texto=str_replace("A","⠠⠁",$texto);
		$texto=str_replace("B","⠠⠃",$texto);
		$texto=str_replace("C","⠠⠉",$texto);
		$texto=str_replace("D","⠠⠙",$texto);
		$texto=str_replace("E","⠠⠑",$texto);
		$texto=str_replace("F","⠠⠋",$texto);
		$texto=str_replace("G","⠠⠛",$texto);
		$texto=str_replace("H","⠠⠓",$texto);
		$texto=str_replace("I","⠠⠊",$texto);
		$texto=str_replace("J","⠠⠚",$texto);
		$texto=str_replace("K","⠠⠅",$texto);
		$texto=str_replace("L","⠠⠇",$texto);
		$texto=str_replace("M","⠠⠍",$texto);
		$texto=str_replace("N","⠠⠝",$texto);
		$texto=str_replace("O","⠠⠕",$texto);
		$texto=str_replace("P","⠠⠏",$texto);
		$texto=str_replace("Q","⠠⠟",$texto);
		$texto=str_replace("R","⠠⠗",$texto);
		$texto=str_replace("S","⠠⠎",$texto);
		$texto=str_replace("T","⠠⠞",$texto);
		$texto=str_replace("U","⠠⠥",$texto);
		$texto=str_replace("V","⠠⠧",$texto);
		$texto=str_replace("W","⠠⠺",$texto);
		$texto=str_replace("X","⠠⠭",$texto);
		$texto=str_replace("Y","⠠⠽",$texto);
		$texto=str_replace("Z","⠠⠵",$texto);
		
		$texto=str_replace("ou","⠳",$texto);
		$texto=str_replace("er","⠻",$texto);
		
		/*Números*/
		$texto=str_replace("0","⠴",$texto);
		$texto=str_replace("1","⠂",$texto);
		$texto=str_replace("2","⠆",$texto);
		$texto=str_replace("3","⠒",$texto);
		$texto=str_replace("4","⠲",$texto);
		$texto=str_replace("5","⠢",$texto);
		$texto=str_replace("6","⠖",$texto);
		$texto=str_replace("7","⠶",$texto);
		$texto=str_replace("8","⠦",$texto);
		$texto=str_replace("9","⠔",$texto);
		
		$texto=str_replace(" 0","⠼⠴",$texto);
		$texto=str_replace(" 1","⠼⠂",$texto);
		$texto=str_replace(" 2","⠼⠆",$texto);
		$texto=str_replace(" 3","⠼⠒",$texto);
		$texto=str_replace(" 4","⠼⠲",$texto);
		$texto=str_replace(" 5","⠼⠢",$texto);
		$texto=str_replace(" 6","⠼⠖",$texto);
		$texto=str_replace(" 7","⠼⠶",$texto);
		$texto=str_replace(" 8","⠼⠦",$texto);
		$texto=str_replace(" 9","⠼⠔",$texto);
		
		$texto=str_replace(" ","⠀",$texto);
		
		/*Acento Agudo*/
		$texto=str_replace("á","⠷",$texto);
		$texto=str_replace("é","⠿",$texto);
		$texto=str_replace("í","⡈",$texto);
		$texto=str_replace("ó","⠬",$texto);
		$texto=str_replace("ú","⠾",$texto);
		
		$texto=str_replace("Á","⠠⠷",$texto);
		$texto=str_replace("É","⠠⠿",$texto);
		$texto=str_replace("Í","⠠⡈",$texto);
		$texto=str_replace("Ó","⠠⠬",$texto);
		$texto=str_replace("Ú","⠠⠾",$texto);
		
		/*Acento Circunflexo*/
		$texto=str_replace("â","⠡",$texto);
		$texto=str_replace("ê","⠣",$texto);
		$texto=str_replace("ô","⠹",$texto);
		
		$texto=str_replace("Â","⠠⠡",$texto);
		$texto=str_replace("Ê","⠠⠣",$texto);
		$texto=str_replace("Ô","⠠⠹",$texto);
		
		/*Acento Til*/
		$texto=str_replace("ã","⠜",$texto);
		$texto=str_replace("õ","⠪",$texto);
		
		$texto=str_replace("ã","⠠⠜",$texto);
		$texto=str_replace("õ","⠠⠪",$texto);
		
		/*Crase*/
		$texto=str_replace("à","⠫",$texto);
		$texto=str_replace("À","⠠⠫",$texto);
		
		/*Trema*/
		$texto=str_replace("ü","⠫",$texto);
		$texto=str_replace("Ü","⠠⠫",$texto);
		
		/*Pontuação*/
		$texto=str_replace(",","⠂",$texto);
		$texto=str_replace(".","⠄",$texto);
		$texto=str_replace("'","⠄",$texto);
		$texto=str_replace("...","⠄⠄⠄",$texto);
		$texto=str_replace(";","⠆",$texto);
		$texto=str_replace(":","⠒",$texto);
		$texto=str_replace("!","⠖",$texto);
		$texto=str_replace("?","⠢",$texto);
		$texto=str_replace("-","⠤",$texto);
		$texto=str_replace("—","⠤⠤",$texto);
		$texto=str_replace('"',"⠦",$texto);
		$texto=str_replace("*","⠔",$texto);
		$texto=str_replace("$","⠰",$texto);
		$texto=str_replace("€","⠈⠑",$texto);
		
		return $texto;
	}
?>