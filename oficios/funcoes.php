<?php
/* atlas-guarda */ if (is_file(__DIR__ . '/../seguranca/guarda.php')) { include_once __DIR__ . '/../seguranca/guarda.php'; }
/**
 * GExtenso class file
 *
 * @author Fausto Gonçalves Cintra (goncin) <goncin@gmail.com>
 * @link http://devfranca.ning.com
 * @link http://twitter.com/g0nc1n
 * @license http://creativecommons.org/licenses/LGPL/2.1/deed.pt
 */
/**
 * GExtenso é uma classe que gera a representação por extenso de um número ou valor monetário.
 *
 * ATENÇÃO: A PÁGINA DE CÓDIGO DESTE ARQUIVO É UTF-8 (Unicode)!
 *
 * Sua implementação foi feita como prova de conceito, utilizando:
 *
 *
 * <ul>
 * <li>Métodos estáticos, implementando o padrão de projeto (<i>design pattern</i>) <b>SINGLETON</b>;</li>
 * <li>Chamadas recursivas a métodos, minimizando repetições e mantendo o código enxuto;</li>
 * <li>Uso de pseudoconstantes ('private static') diante das limitações das constantes de classe;</li>
 * <li>Tratamento de erros por intermédio de exceções; e</li>
 * <li>Utilização do phpDocumentor ({@link http://www.phpdoc.org}) para documentação do código fonte e
 * geração automática de documentação externa.</li>
 * </ul>
 *
 * <b>EXEMPLOS DE USO</b>
 *
 * Para obter o extenso de um número, utilize GExtenso::{@link numero}.
 * <pre>
 * echo GExtenso::numero(832); // oitocentos e trinta e dois
 * echo GExtenso::numero(832, GExtenso::GENERO_FEM) // oitocentas e trinta e duas
 * </pre>
 *
 * Para obter o extenso de um valor monetário, utilize GExtenso::{@link moeda}.
 * <pre>
 * // IMPORTANTE: veja nota sobre o parâmetro 'valor' na documentação do método!
 * echo GExtenso::moeda(15402); // cento e cinquenta e quatro reais e dois centavos
 * echo GExtenso::moeda(47); // quarenta e sete centavos
 * echo GExtenso::moeda(357082, 2,
 *   array('peseta', 'pesetas', GExtenso::GENERO_FEM),
 *   array('cêntimo', 'cêntimos', GExtenso::GENERO_MASC));
 *   // três mil, quinhentas e setenta pesetas e oitenta e dois cêntimos
 * </pre>
 *
 * @author Fausto Gonçalves Cintra (goncin) <goncin@gmail.com>
 * @version 0.1 2010-03-02
 * @package GUtils
 *
 */
 class GExtenso {
  const NUM_SING = 0;
  const NUM_PLURAL = 1;
  const POS_GENERO = 2;
  const GENERO_MASC = 0;
  const GENERO_FEM = 1;
  const VALOR_MAXIMO = 999999999;
  /* Uma vez que o PHP não suporta constantes de classe na forma de matriz (array),
    a saída encontrada foi declarar as strings numéricas como 'private static'.
  */

  /* As unidades 1 e 2 variam em gênero, pelo que precisamos de dois conjuntos de strings (masculinas e femininas) para as unidades */
  private static $UNIDADES = array(
    self::GENERO_MASC => array(
      1 => 'um',
      2 => 'dois',
      3 => 'três',
      4 => 'quatro',
      5 => 'cinco',
      6 => 'seis',
      7 => 'sete',
      8 => 'oito',
      9 => 'nove'
    ),
    self::GENERO_FEM => array(
      1 => 'uma',
      2 => 'duas',
      3 => 'três',
      4 => 'quatro',
      5 => 'cinco',
      6 => 'seis',
      7 => 'sete',
      8 => 'oito',
      9 => 'nove'
    )
  );
  private static $DE11A19 = array(
    11 => 'onze',
    12 => 'doze',
    13 => 'treze',
    14 => 'quatorze',
    15 => 'quinze',
    16 => 'dezesseis',
    17 => 'dezessete',
    18 => 'dezoito',
    19 => 'dezenove'
  );
  private static $DEZENAS = array(
    10 => 'dez',
    20 => 'vinte',
    30 => 'trinta',
    40 => 'quarenta',
    50 => 'cinquenta',
    60 => 'sessenta',
    70 => 'setenta',
    80 => 'oitenta',
    90 => 'noventa'
  );
  private static $CENTENA_EXATA = 'cem';
  /* As centenas, com exceção de 'cento', também variam em gênero. Aqui também se faz
    necessário dois conjuntos de strings (masculinas e femininas).
  */
  private static $CENTENAS = array(
    self::GENERO_MASC => array(
      100 => 'cento',
      200 => 'duzentos',
      300 => 'trezentos',
      400 => 'quatrocentos',
      500 => 'quinhentos',
      600 => 'seiscentos',
      700 => 'setecentos',
      800 => 'oitocentos',
      900 => 'novecentos'
    ),
    self::GENERO_FEM => array(
      100 => 'cento',
      200 => 'duzentas',
      300 => 'trezentas',
      400 => 'quatrocentas',
      500 => 'quinhentas',
      600 => 'seiscentas',
      700 => 'setecentas',
      800 => 'oitocentas',
      900 => 'novecentas'
    )
  );
  /* 'Mil' é invariável, seja em gênero, seja em número */
  private static $MILHAR = 'mil';
  private static $MILHOES = array(
    self::NUM_SING => 'milhão',
    self::NUM_PLURAL => 'milhões'
  );
 /**
 * Gera a representação por extenso de um número inteiro, maior que zero e menor ou igual a GExtenso::VALOR_MAXIMO.
 *
 * @param int O valor numérico cujo extenso se deseja gerar
 *
 * @param int (Opcional; valor padrão: GExtenso::GENERO_MASC) O gênero gramatical (GExtenso::GENERO_MASC ou GExtenso::GENERO_FEM)
 * do extenso a ser gerado. Isso possibilita distinguir, por exemplo, entre 'duzentos e dois homens' e 'duzentas e duas mulheres'.
 *
 * @return string O número por extenso
 *
 * @since 0.1 2010-03-02
 */
  public static function numero($valor, $genero = self::GENERO_MASC) {
    /* ----- VALIDAÇÃO DOS PARÂMETROS DE ENTRADA ---- */
    if(!is_numeric($valor))
      throw new Exception("[Exceção em GExtenso::numero] Parâmetro \$valor não é numérico (recebido: '$valor')");
    else if($valor <= 0)
      throw new Exception("[Exceção em GExtenso::numero] Parâmetro \$valor igual a ou menor que zero (recebido: '$valor')");
    else if($valor > self::VALOR_MAXIMO)
      throw new Exception('[Exceção em GExtenso::numero] Parâmetro $valor deve ser um inteiro entre 1 e ' . self::VALOR_MAXIMO . " (recebido: '$valor')");
    else if($genero != self::GENERO_MASC && $genero != self::GENERO_FEM)
      throw new Exception("Exceção em GExtenso: valor incorreto para o parâmetro \$genero (recebido: '$genero').");
    /* ----------------------------------------------- */
    else if($valor >= 2 && $valor <= 9)
      return self::$UNIDADES[$genero][$valor]; // As unidades 'um' e 'dois' variam segundo o gênero
    else if($valor == 10)
      return self::$DEZENAS[$valor];
    else if($valor >= 11 && $valor <= 19)
      return self::$DE11A19[$valor];
    else if($valor >= 20 && $valor <= 99) {
      $dezena = $valor - ($valor % 10);
      $ret = self::$DEZENAS[$dezena];
      /* Chamada recursiva à função para processar $resto se este for maior que zero.
       * O conectivo 'e' é utilizado entre dezenas e unidades.
       */
      if($resto = $valor - $dezena) $ret .= ' e ' . self::numero($resto, $genero);
      return $ret;
    }
    else if($valor == 100) {
      return self::$CENTENA_EXATA;
    }
    else if($valor >= 101 && $valor <= 999) {
      $centena = $valor - ($valor % 100);
      $ret = self::$CENTENAS[$genero][$centena]; // As centenas (exceto 'cento') variam em gênero
      /* Chamada recursiva à função para processar $resto se este for maior que zero.
       * O conectivo 'e' é utilizado entre centenas e dezenas.
       */
      if($resto = $valor - $centena) $ret .= ' e ' . self::numero($resto, $genero);
      return $ret;
    }
    else if($valor >= 1000 && $valor <= 999999) {
      /* A função 'floor' é utilizada para encontrar o inteiro da divisão de $valor por 1000,
       * assim determinando a quantidade de milhares. O resultado é enviado a uma chamada recursiva
       * da função. A palavra 'mil' não se flexiona.
       */
      $milhar = floor($valor / 1000);
      $ret = self::numero($milhar, self::GENERO_MASC) . ' ' . self::$MILHAR; // 'Mil' é do gênero masculino
      $resto = $valor % 1000;
      /* Chamada recursiva à função para processar $resto se este for maior que zero.
       * O conectivo 'e' é utilizado entre milhares e números entre 1 e 99, bem como antes de centenas exatas.
       */
      if($resto && (($resto >= 1 && $resto <= 99) || $resto % 100 == 0))
        $ret .= ' e ' . self::numero($resto, $genero);
      /* Nos demais casos, após o milhar é utilizada a vírgula. */
      else if ($resto)
        $ret .= ' ' . self::numero($resto, $genero);
      return $ret;
    }
    else if($valor >= 100000 && $valor <= self::VALOR_MAXIMO) {
      /* A função 'floor' é utilizada para encontrar o inteiro da divisão de $valor por 1000000,
       * assim determinando a quantidade de milhões. O resultado é enviado a uma chamada recursiva
       * da função. A palavra 'milhão' flexiona-se no plural.
       */
      $milhoes = floor($valor / 1000000);
      $ret = self::numero($milhoes, self::GENERO_MASC) . ' '; // Milhão e milhões são do gênero masculino

      /* Se a o número de milhões for maior que 1, deve-se utilizar a forma flexionada no plural */
      $ret .= $milhoes == 1 ? self::$MILHOES[self::NUM_SING] : self::$MILHOES[self::NUM_PLURAL];
      $resto = $valor % 1000000;
      /* Chamada recursiva à função para processar $resto se este for maior que zero.
       * O conectivo 'e' é utilizado entre milhões e números entre 1 e 99, bem como antes de centenas exatas.
       */
      if($resto && (($resto >= 1 && $resto <= 99) || $resto % 100 == 0))
        $ret .= ' e ' . self::numero($resto, $genero);
      /* Nos demais casos, após o milhão é utilizada a vírgula. */
      else if ($resto)
        $ret .= ', ' . self::numero($resto, $genero);
      return $ret;
    }
  }
 /**
 * Gera a representação por extenso de um valor monetário, maior que zero e menor ou igual a GExtenso::VALOR_MAXIMO.
 *
 * @param int O valor monetário cujo extenso se deseja gerar.
 * ATENÇÃO: PARA EVITAR OS CONHECIDOS PROBLEMAS DE ARREDONDAMENTO COM NÚMEROS DE PONTO FLUTUANTE, O VALOR DEVE SER PASSADO
 * JÁ DEVIDAMENTE MULTIPLICADO POR 10 ELEVADO A $casasDecimais (o que equivale, normalmente, a passar o valor com centavos
 * multiplicado por 100)
 *
 * @param int (Opcional; valor padrão: 2) Número de casas decimais a serem consideradas como parte fracionária (centavos)
 *
 * @param array (Opcional; valor padrão: array('real', 'reais', GExtenso::GENERO_MASC)) Fornece informações sobre a moeda a ser
 * utilizada. O primeiro valor da matriz corresponde ao nome da moeda no singular, o segundo ao nome da moeda no plural e o terceiro
 * ao gênero gramatical do nome da moeda (GExtenso::GENERO_MASC ou GExtenso::GENERO_FEM)
 *
 * @param array (Opcional; valor padrão: array('centavo', 'centavos', self::GENERO_MASC)) Provê informações sobre a parte fracionária
 * da moeda. O primeiro valor da matriz corresponde ao nome da parte fracionária no singular, o segundo ao nome da parte fracionária no plural
 * e o terceiro ao gênero gramatical da parte fracionária (GExtenso::GENERO_MASC ou GExtenso::GENERO_FEM)
 *
 * @return string O valor monetário por extenso
 *
 * @since 0.1 2010-03-02
 */
  public static function moeda(
    $valor,
    $casasDecimais = 2,
    $infoUnidade = array('real', 'reais', self::GENERO_MASC),
    $infoFracao = array('centavo', 'centavos', self::GENERO_MASC)
  ) {
    /* ----- VALIDAÇÃO DOS PARÂMETROS DE ENTRADA ---- */
    if(!is_numeric($valor))
      throw new Exception("[Exceção em GExtenso::moeda] Parâmetro \$valor não é numérico (recebido: '$valor')");
    else if($valor <= 0)
      throw new Exception("[Exceção em GExtenso::moeda] Parâmetro \$valor igual a ou menor que zero (recebido: '$valor')");
    else if(!is_numeric($casasDecimais) || $casasDecimais < 0)
      throw new Exception("[Exceção em GExtenso::moeda] Parâmetro \$casasDecimais não é numérico ou é menor que zero (recebido: '$casasDecimais')");
    else if(!is_array($infoUnidade) || count($infoUnidade) < 3) {
      $infoUnidade = print_r($infoUnidade, true);
      throw new Exception("[Exceção em GExtenso::moeda] Parâmetro \$infoUnidade não é uma matriz com 3 (três) elementos (recebido: '$infoUnidade')");
    }
    else if($infoUnidade[self::POS_GENERO] != self::GENERO_MASC && $infoUnidade[self::POS_GENERO] != self::GENERO_FEM)
      throw new Exception("Exceção em GExtenso: valor incorreto para o parâmetro \$infoUnidade[self::POS_GENERO] (recebido: '{$infoUnidade[self::POS_GENERO]}').");
    else if(!is_array($infoFracao) || count($infoFracao) < 3) {
      $infoFracao = print_r($infoFracao, true);
      throw new Exception("[Exceção em GExtenso::moeda] Parâmetro \$infoFracao não é uma matriz com 3 (três) elementos (recebido: '$infoFracao')");
    }
    else if($infoFracao[self::POS_GENERO] != self::GENERO_MASC && $infoFracao[self::POS_GENERO] != self::GENERO_FEM)
      throw new Exception("Exceção em GExtenso: valor incorreto para o parâmetro \$infoFracao[self::POS_GENERO] (recebido: '{$infoFracao[self::POS_GENERO]}').");
    /* ----------------------------------------------- */
    /* A parte inteira do valor monetário corresponde ao $valor passado dividido por 10 elevado a $casasDecimais, desprezado o resto.
     * Assim, com o padrão de 2 $casasDecimais, o $valor será dividido por 100 (10^2), e o resto é descartado utilizando-se floor().
     */
    $parteInteira = floor($valor / pow(10, $casasDecimais));
    /* A parte fracionária ('centavos'), por seu turno, corresponderá ao resto da divisão do $valor por 10 elevado a $casasDecimais.
     * No cenário comum em que trabalhamos com 2 $casasDecimais, será o resto da divisão do $valor por 100 (10^2).
     */
    $fracao = $valor % pow(10, $casasDecimais);
      /* Inicia a variável $ret */
      $ret = '';
    /* O extenso para a $parteInteira somente será gerado se esta for maior que zero. Para tanto, utilizamos
     * os préstimos do método GExtenso::numero().
     */
    if($parteInteira) {
      $ret = self::numero($parteInteira, $infoUnidade[self::POS_GENERO]) . ' ';
      $ret .= $parteInteira == 1 ? $infoUnidade[self::NUM_SING] : $infoUnidade[self::NUM_PLURAL];
    }
    /* De forma semelhante, o extenso da $fracao somente será gerado se esta for maior que zero. */
    if($fracao) {
      /* Se a $parteInteira for maior que zero, o extenso para ela já terá sido gerado. Antes de juntar os
       * centavos, precisamos colocar o conectivo 'e'.
       */
      if ($parteInteira) $ret .= ' e ';
      $ret .= self::numero($fracao, $infoFracao[self::POS_GENERO]) . ' ';
      $ret .= $parteInteira == 1 ? $infoFracao[self::NUM_SING] : $infoFracao[self::NUM_PLURAL];
    }
    return $ret;
  }
}
function limpaCPF_CNPJ($valor){
 $valor = trim($valor);
 $valor = str_replace(".", "", $valor);
 $valor = str_replace(",", "", $valor);
 $valor = str_replace("-", "", $valor);
 $valor = str_replace("/", "", $valor);
 return $valor;
}

function limpeCPF_CNPJ($valor){
$valor = preg_replace('/[^0-9]/', '', $valor);
   return $valor;
}


function retorna_idade_civil($nascimento){
  $data = $nascimento;

    // Separa em dia, mês e ano
    list($ano, $mes, $dia) = explode('-', $data);

    // Descobre que dia é hoje e retorna a unix timestamp
    $hoje = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
    // Descobre a unix timestamp da data de nascimento do fulano
    $nascimento = mktime( 0, 0, 0, $mes, $dia, $ano);

    // Depois apenas fazemos o cálculo já citado :)
    $idade = floor((((($hoje - $nascimento) / 60) / 60) / 24) / 365.25);

    return $idade;

}

function encontrouNumeros($string) {
    return (filter_var($string, FILTER_SANITIZE_NUMBER_INT) === '' ? false : true);
}
function encontrouNumeros2($string) {
    $indice = 0;
    while ($indice < strlen($string)) {
        if (ctype_digit($string[$indice]) === true) return true;
        $indice++;
    }
    return false;
}
function corrigirACENTO_utf8($string){
// return utf8_encode($string);
$utf8_ansi = array(
"00c0" =>"À",
"00c1" =>"Á",
"00c2" =>"Â",
"00c3" =>"Ã",
"00c4" =>"Ä",
"00c5" =>"Å",
"00c6" =>"Æ",
"00c7" =>"Ç",
"00c8" =>"È",
"00c9" =>"É",
"00ca" =>"Ê",
"00cb" =>"Ë",
"00cc" =>"Ì",
"00cd" =>"Í",
"00ce" =>"Î",
"00cf" =>"Ï",
"00d1" =>"Ñ",
"00d2" =>"Ò",
"00d3" =>"Ó",
"00d4" =>"Ô",
"00d5" =>"Õ",
"00d6" =>"Ö",
"00d8" =>"Ø",
"00d9" =>"Ù",
"00da" =>"Ú",
"00db" =>"Û",
"00dc" =>"Ü",
"00dd" =>"Ý",
"00df" =>"ß",
"00e0" =>"à",
"00e1" =>"á",
"00e2" =>"â",
"00e3" =>"ã",
"00e4" =>"ä",
"00e5" =>"å",
"00e6" =>"æ",
"00e7" =>"ç",
"00e8" =>"è",
"00e9" =>"é",
"00ea" =>"ê",
"00eb" =>"ë",
"00ec" =>"ì",
"00ed" =>"í",
"00ee" =>"î",
"00ef" =>"ï",
"00f0" =>"ð",
"00f1" =>"ñ",
"00f2" =>"ò",
"00f3" =>"ó",
"00f4" =>"ô",
"00f5" =>"õ",
"00f6" =>"ö",
"00f8" =>"ø",
"00f9" =>"ù",
"00fa" =>"ú",
"00fb" =>"û",
"00fc" =>"ü",
"00fd" =>"ý",
"00ff" =>"ÿ",
"u2013" => "–");

return strtr($string, $utf8_ansi);
}
function corrigir_acento_m1($string) {
  $utf8_ansi2 = array(
    "\u00c0" =>"À",
    "\u00c1" =>"Á",
    "\u00c2" =>"Â",
    "\u00c3" =>"Ã",
    "\u00c4" =>"Ä",
    "\u00c5" =>"Å",
    "\u00c6" =>"Æ",
    "\u00c7" =>"Ç",
    "\u00c8" =>"È",
    "\u00c9" =>"É",
    "\u00ca" =>"Ê",
    "\u00cb" =>"Ë",
    "\u00cc" =>"Ì",
    "\u00cd" =>"Í",
    "\u00ce" =>"Î",
    "\u00cf" =>"Ï",
    "\u00d1" =>"Ñ",
    "\u00d2" =>"Ò",
    "\u00d3" =>"Ó",
    "\u00d4" =>"Ô",
    "\u00d5" =>"Õ",
    "\u00d6" =>"Ö",
    "\u00d8" =>"Ø",
    "\u00d9" =>"Ù",
    "\u00da" =>"Ú",
    "\u00db" =>"Û",
    "\u00dc" =>"Ü",
    "\u00dd" =>"Ý",
    "\u00df" =>"ß",
    "\u00e0" =>"à",
    "\u00e1" =>"á",
    "\u00e2" =>"â",
    "\u00e3" =>"ã",
    "\u00e4" =>"ä",
    "\u00e5" =>"å",
    "\u00e6" =>"æ",
    "\u00e7" =>"ç",
    "\u00e8" =>"è",
    "\u00e9" =>"é",
    "\u00ea" =>"ê",
    "\u00eb" =>"ë",
    "\u00ec" =>"ì",
    "\u00ed" =>"í",
    "\u00ee" =>"î",
    "\u00ef" =>"ï",
    "\u00f0" =>"ð",
    "\u00f1" =>"ñ",
    "\u00f2" =>"ò",
    "\u00f3" =>"ó",
    "\u00f4" =>"ô",
    "\u00f5" =>"õ",
    "\u00f6" =>"ö",
    "\u00f8" =>"ø",
    "\u00f9" =>"ù",
    "\u00fa" =>"ú",
    "\u00fb" =>"û",
    "\u00fc" =>"ü",
    "\u00fd" =>"ý",
    "\u00ff" =>"ÿ");

    return strtr($string, $utf8_ansi2);


}

function corrigir_acento_m2($string) {
  $utf8_ansi2 = array(
    "\U00C0" =>"À",
    "\U00C1" =>"Á",
    "\U00C2" =>"Â",
    "\U00C3" =>"Ã",
    "\U00C4" =>"Ä",
    "\U00C5" =>"Å",
    "\U00C6" =>"Æ",
    "\U00C7" =>"Ç",
    "\U00C8" =>"È",
    "\U00C9" =>"É",
    "\U00CA" =>"Ê",
    "\U00CB" =>"Ë",
    "\U00CC" =>"Ì",
    "\U00CD" =>"Í",
    "\U00CE" =>"Î",
    "\U00CF" =>"Ï",
    "\U00D1" =>"Ñ",
    "\U00D2" =>"Ò",
    "\U00D3" =>"Ó",
    "\U00D4" =>"Ô",
    "\U00D5" =>"Õ",
    "\U00D6" =>"Ö",
    "\U00D8" =>"Ø",
    "\U00D9" =>"Ù",
    "\U00DA" =>"Ú",
    "\U00DB" =>"Û",
    "\U00DC" =>"Ü",
    "\U00DD" =>"Ý",
    "\U00DF" =>"ß",
    "\U00E0" =>"à",
    "\U00E1" =>"á",
    "\U00E2" =>"â",
    "\U00E3" =>"ã",
    "\U00E4" =>"ä",
    "\U00E5" =>"å",
    "\U00E6" =>"æ",
    "\U00E7" =>"ç",
    "\U00E8" =>"è",
    "\U00E9" =>"é",
    "\U00EA" =>"ê",
    "\U00EB" =>"ë",
    "\U00EC" =>"ì",
    "\U00ED" =>"í",
    "\U00EE" =>"î",
    "\U00EF" =>"ï",
    "\U00F0" =>"ð",
    "\U00F1" =>"ñ",
    "\U00F2" =>"ò",
    "\U00F3" =>"ó",
    "\U00F4" =>"ô",
    "\U00F5" =>"õ",
    "\U00F6" =>"ö",
    "\U00F8" =>"ø",
    "\U00F9" =>"ù",
    "\U00FA" =>"ú",
    "\U00FB" =>"û",
    "\U00FC" =>"ü",
    "\U00FD" =>"ý",
    "\U00FF" =>"ÿ");

    return strtr($string, $utf8_ansi2);


}

function corrigir_acento_m3($string) {
  $utf8_ansi2 = array(
    "U00C0" =>"À",
    "U00C1" =>"Á",
    "U00C2" =>"Â",
    "U00C3" =>"Ã",
    "U00C4" =>"Ä",
    "U00C5" =>"Å",
    "U00C6" =>"Æ",
    "U00C7" =>"Ç",
    "U00C8" =>"È",
    "U00C9" =>"É",
    "U00CA" =>"Ê",
    "U00CB" =>"Ë",
    "U00CC" =>"Ì",
    "U00CD" =>"Í",
    "U00CE" =>"Î",
    "U00CF" =>"Ï",
    "U00D1" =>"Ñ",
    "U00D2" =>"Ò",
    "U00D3" =>"Ó",
    "U00D4" =>"Ô",
    "U00D5" =>"Õ",
    "U00D6" =>"Ö",
    "U00D8" =>"Ø",
    "U00D9" =>"Ù",
    "U00DA" =>"Ú",
    "U00DB" =>"Û",
    "U00DC" =>"Ü",
    "U00DD" =>"Ý",
    "U00DF" =>"ß",
    "U00E0" =>"à",
    "U00E1" =>"á",
    "U00E2" =>"â",
    "U00E3" =>"ã",
    "U00E4" =>"ä",
    "U00E5" =>"å",
    "U00E6" =>"æ",
    "U00E7" =>"ç",
    "U00E8" =>"è",
    "U00E9" =>"é",
    "U00EA" =>"ê",
    "U00EB" =>"ë",
    "U00EC" =>"ì",
    "U00ED" =>"í",
    "U00EE" =>"î",
    "U00EF" =>"ï",
    "U00F0" =>"ð",
    "U00F1" =>"ñ",
    "U00F2" =>"ò",
    "U00F3" =>"ó",
    "U00F4" =>"ô",
    "U00F5" =>"õ",
    "U00F6" =>"ö",
    "U00F8" =>"ø",
    "U00F9" =>"ù",
    "U00FA" =>"ú",
    "U00FB" =>"û",
    "U00FC" =>"ü",
    "U00FD" =>"ý",
    "U00FF" =>"ÿ");

    return strtr($string, $utf8_ansi2);


}

function corrigir_acento_m4($string) {
  $utf8_ansi2 = array(
    "u00c0" =>"À",
    "u00c1" =>"Á",
    "u00c2" =>"Â",
    "u00c3" =>"Ã",
    "u00c4" =>"Ä",
    "u00c5" =>"Å",
    "u00c6" =>"Æ",
    "u00c7" =>"Ç",
    "u00c8" =>"È",
    "u00c9" =>"É",
    "u00ca" =>"Ê",
    "u00cb" =>"Ë",
    "u00cc" =>"Ì",
    "u00cd" =>"Í",
    "u00ce" =>"Î",
    "u00cf" =>"Ï",
    "u00d1" =>"Ñ",
    "u00d2" =>"Ò",
    "u00d3" =>"Ó",
    "u00d4" =>"Ô",
    "u00d5" =>"Õ",
    "u00d6" =>"Ö",
    "u00d8" =>"Ø",
    "u00d9" =>"Ù",
    "u00da" =>"Ú",
    "u00db" =>"Û",
    "u00dc" =>"Ü",
    "u00dd" =>"Ý",
    "u00df" =>"ß",
    "u00e0" =>"à",
    "u00e1" =>"á",
    "u00e2" =>"â",
    "u00e3" =>"ã",
    "u00e4" =>"ä",
    "u00e5" =>"å",
    "u00e6" =>"æ",
    "u00e7" =>"ç",
    "u00e8" =>"è",
    "u00e9" =>"é",
    "u00ea" =>"ê",
    "u00eb" =>"ë",
    "u00ec" =>"ì",
    "u00ed" =>"í",
    "u00ee" =>"î",
    "u00ef" =>"ï",
    "u00f0" =>"ð",
    "u00f1" =>"ñ",
    "u00f2" =>"ò",
    "u00f3" =>"ó",
    "u00f4" =>"ô",
    "u00f5" =>"õ",
    "u00f6" =>"ö",
    "u00f8" =>"ø",
    "u00f9" =>"ù",
    "u00fa" =>"ú",
    "u00fb" =>"û",
    "u00fc" =>"ü",
    "u00fd" =>"ý",
    "u00ff" =>"ÿ");

    return strtr($string, $utf8_ansi2);


}

function corrigirACENTO_migracao_utf8($string){
  // return utf8_encode($string);
  $utf8_ansi = array(
  "\'c0" =>"À",
  "\'c1" =>"Á",
  "\'c2" =>"Â",
  "\'c3" =>"Ã",
  "\'c4" =>"Ä",
  "\'c5" =>"Å",
  "\'c6" =>"Æ",
  "\'c7" =>"Ç",
  "\'c8" =>"È",
  "\'c9" =>"É",
  "\'ca" =>"Ê",
  "\'cb" =>"Ë",
  "\'cc" =>"Ì",
  "\'cd" =>"Í",
  "\'ce" =>"Î",
  "\'cf" =>"Ï",
  "\'d1" =>"Ñ",
  "\'d2" =>"Ò",
  "\'d3" =>"Ó",
  "\'d4" =>"Ô",
  "\'d5" =>"Õ",
  "\'d6" =>"Ö",
  "\'d8" =>"Ø",
  "\'d9" =>"Ù",
  "\'da" =>"Ú",
  "\'db" =>"Û",
  "\'dc" =>"Ü",
  "\'dd" =>"Ý",
  "\'df" =>"ß",
  "\'e0" =>"à",
  "\'e1" =>"á",
  "\'e2" =>"â",
  "\'e3" =>"ã",
  "\'e4" =>"ä",
  "\'e5" =>"å",
  "\'e6" =>"æ",
  "\'e7" =>"ç",
  "\'e8" =>"è",
  "\'e9" =>"é",
  "\'ea" =>"ê",
  "\'eb" =>"ë",
  "\'ec" =>"ì",
  "\'ed" =>"í",
  "\'ee" =>"î",
  "\'ef" =>"ï",
  "\'f0" =>"ð",
  "\'f1" =>"ñ",
  "\'f2" =>"ò",
  "\'f3" =>"ó",
  "\'f4" =>"ô",
  "\'f5" =>"õ",
  "\'f6" =>"ö",
  "\'f8" =>"ø",
  "\'f9" =>"ù",
  "\'fa" =>"ú",
  "\'fb" =>"û",
  "\'fc" =>"ü",
  "\'fd" =>"ý",
  "\'ff" =>"ÿ",
  "\'ba" => "º",
  "\'b0" => "°",

  " {\* d__outorgado2" => "",
  "CESS?O DE DIREITOS HEREDIT?RIOSAA0002400410024|CESS?O DE DIREITOS
  HEREDIT?RIOS" => "",
  "CESS?O\'20DE\'20DIREITOS\'20HEREDIT?RIOSAA0002400410024|CESS?O\'20DE\'20DIREITOS\'20HEREDIT?RIOS{\*{." => "",

    "{\*{. {\*{. {\*{) {\*{({) {\*{({) {\*{({) {\*{({) {\*{({)" => "",
    "�" => "",
    "\'96 " => "",
   
 







  "u2013" => "–");
  
  return strtr($string, $utf8_ansi);
  }

function titulo_utf8($string){
    return mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
#ucwords(strtolower($string));
}
function maiusculo_utf8($string){
    return mb_convert_case($string, MB_CASE_UPPER, "UTF-8");
#ucwords(strtolower($string));
}function minusculo_utf8($string){
    return mb_convert_case($string, MB_CASE_LOWER, "UTF-8");
#ucwords(strtolower($string));
}



function palavras_bloqueadas($string) {
  $palavras_bloqueadas = array(
    '<div id="icpbravoaccess_loaded">&nbsp;</div>',
    '<div id="njcdgcofcbnlbpkpdhmlmiblaglnkpnj">&nbsp;</div>',
  '<p class="extojustificado">&nbsp;</p>');

    return str_replace($palavras_bloqueadas,"",$string);


}
#$palavras_bloqueadas = array('<div id="icpbravoaccess_loaded">&nbsp;</div>');


// converter numero para extenso

?>

<?php
function valorPorExtenso($valor=0) {
  $singular = array("centavo", "real", "mil", "milhão", "bilhão", "trilhão", "quatrilhão");
  $plural = array("centavos", "reais", "mil", "milhões", "bilhões", "trilhões","quatrilhões");

  $c = array("", "cem", "duzentos", "trezentos", "quatrocentos","quinhentos", "seiscentos", "setecentos", "oitocentos", "novecentos");
  $d = array("", "dez", "vinte", "trinta", "quarenta", "cinquenta","sessenta", "setenta", "oitenta", "noventa");
  $d10 = array("dez", "onze", "doze", "treze", "quatorze", "quinze","dezesseis", "dezesete", "dezoito", "dezenove");
  $u = array("", "um", "dois", "três", "quatro", "cinco", "seis","sete", "oito", "nove");

  $z=0;

  $valor = number_format($valor, 2, ".", ".");
  $inteiro = explode(".", $valor);
  for($i=0;$i<count($inteiro);$i++)
      for($ii=strlen($inteiro[$i]);$ii<3;$ii++)
          $inteiro[$i] = "0".$inteiro[$i];

  // $fim identifica onde que deve se dar junção de centenas por "e" ou por "," 😉
  $fim = count($inteiro) - ($inteiro[count($inteiro)-1] > 0 ? 1 : 2);
  for ($i=0;$i<count($inteiro);$i++) {
      $valor = $inteiro[$i];
      $rc = (($valor > 100) && ($valor < 200)) ? "cento" : $c[$valor[0]];
      $rd = ($valor[1] < 2) ? "" : $d[$valor[1]];
      $ru = ($valor > 0) ? (($valor[1] == 1) ? $d10[$valor[2]] : $u[$valor[2]]) : "";

      $r = $rc.(($rc && ($rd || $ru)) ? " e " : "").$rd.(($rd && $ru) ? " e " : "").$ru;
      $t = count($inteiro)-1-$i;
      $r .= $r ? " ".($valor > 1 ? "": "") : "";
      if ($valor == "000")$z++; elseif ($z > 0) $z--;
      if (($t==1) && ($z>0) && ($inteiro[0] > 0)) $r .= (($z>1) ? " de " : "").$plural[$t]; 
      if ($r) $rt = $rt . ((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? ( ($i < $fim) ? ", " : " e ") : " ") . $r;
  }

  return($rt ? $rt : "zero");
}




function data_extenso(){
  $data = date("d/m/Y");
  $data_extenso = explode('/',$data);
  $dia = $data_extenso['0'];
  $mes = $data_extenso['1'];
  $ano = $data_extenso['2'];
  switch ($dia){

    case 1: $dia = 'primeiro de '; break;
    case 2: $dia = 'dois de '; break;
    case 3: $dia = 'três de '; break;
    case 4: $dia = 'quatro de '; break;
    case 5: $dia = 'cinco de '; break;
    case 6: $dia = 'seis de '; break;
    case 7: $dia = 'sete de '; break;
    case 8: $dia = 'oito de '; break;
    case 9: $dia = 'nove de '; break;
    case 10: $dia = 'dez de '; break;
    case 11: $dia = 'onze de '; break;
    case 12: $dia = 'doze de '; break;
    case 13: $dia = 'treze de '; break;
    case 14: $dia = 'catorze de '; break;
    case 15: $dia = 'quinze de '; break;
    case 16: $dia = 'dezesseis de '; break;
    case 17: $dia = 'dezessete de '; break;
    case 18: $dia = 'dezoito de '; break;
    case 19: $dia = 'dezenove de '; break;
    case 20: $dia = 'vinte de '; break;
    case 21: $dia = 'vinte e um de '; break;
    case 22: $dia = 'vinte e dois de '; break;
    case 23: $dia = 'vinte e três de '; break;
    case 24: $dia = 'vinte e quatro de '; break;
    case 25: $dia = 'vinte e cinco de '; break;
    case 26: $dia = 'vinte e seis de '; break;
    case 27: $dia = 'vinte e sete de '; break;
    case 28: $dia = 'vinte e oito de '; break;
    case 29: $dia = 'vinte e nove de '; break;
    case 30: $dia = 'trinta de '; break;
    case 31: $dia = 'trinta e um de '; break;
    }
    
    // configuração mes
    
    switch ($mes){
    
    case 1: $mes = 'janeiro'; break;
    case 2: $mes = 'fevereiro'; break;
    case 3: $mes = 'março'; break;
    case 4: $mes = 'abril'; break;
    case 5: $mes = 'maio'; break;
    case 6: $mes = 'junho'; break;
    case 7: $mes = 'julho'; break;
    case 8: $mes = 'agosto'; break;
    case 9: $mes = 'setembro'; break;
    case 10: $mes = 'outubro'; break;
    case 11: $mes = 'novembro'; break;
    case 12: $mes = 'dezembro'; break;
    }
    
    switch ($ano){
    
      case 2017: $ano = ' de dois mil e dezessete'; break;
      case 2018: $ano = ' de dois mil e dezoito'; break;
      case 2019: $ano = ' de dois mil e dezenove'; break;
      case 2020: $ano = ' de dois mil e vinte'; break;
      case 2021: $ano = ' de dois mil e vinte e um'; break;
      case 2022: $ano = ' de dois mil e vinte e dois'; break;
      case 2023: $ano = ' de dois mil e vinte e três'; break;
      case 2024: $ano = ' de dois mil e vinte e quatro'; break;
      case 2025: $ano = ' de dois mil e vinte e cinco'; break;
      case 2026: $ano = ' de dois mil e vinte e seis'; break;
      case 2027: $ano = ' de dois mil e vinte e sete'; break;
      case 2028: $ano = ' de dois mil e vinte e oito'; break;
      case 2029: $ano = ' de dois mil e vinte e nove'; break;
      case 2030: $ano = ' de dois mil e trinta'; break;
      case 2031: $ano = ' de dois mil e trinta e um'; break;
      case 2032: $ano = ' de dois mil e trinta e  de dois'; break;
      case 2033: $ano = ' de dois mil e trinta e três'; break;
      case 2034: $ano = ' de dois mil e trinta e quatro'; break;
      case 2035: $ano = ' de dois mil e trinta e cinco'; break;
      case 2036: $ano = ' de dois mil e trinta e seis'; break;
      case 2037: $ano = ' de dois mil e trinta e sete'; break;
      case 2038: $ano = ' de dois mil e trinta e oito'; break;
      case 2039: $ano = ' de dois mil e trinta e nove'; break;
      case 2040: $ano = ' de dois mil e quarenta'; break;
      case 2041: $ano = ' de dois mil e quarenta e um'; break;
      case 2042: $ano = ' de dois mil e quarenta e  de dois'; break;
      case 2043: $ano = ' de dois mil e quarenta e três'; break;
      case 2044: $ano = ' de dois mil e quarenta e quatro'; break;
      case 2045: $ano = ' de dois mil e quarenta e cinco'; break;
      case 2046: $ano = ' de dois mil e quarenta e seis'; break;
      case 2047: $ano = ' de dois mil e quarenta e sete'; break;
      case 2048: $ano = ' de dois mil e quarenta e oito'; break;
      case 2049: $ano = ' de dois mil e quarenta e nove'; break;
      case 2050: $ano = ' de dois mil e cinquenta'; break;
    }

  echo $dia.''.$mes.''.$ano;
}

  
function corrigir_ponttuacao($string) {
  $corrigir_ponttuacao = array(
    "<strong>" =>'<span class="negrito">',
    "</strong>" =>"</span>",
    'style="text-align: justify;"' =>'class="alinhamento"',
    'style="ext-align:"' =>'class="alinhamento"',
   
  

    
    "\U00FF" =>"ÿ");

    return strtr($string, $corrigir_ponttuacao);


}

?>