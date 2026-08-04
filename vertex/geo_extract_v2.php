<?php
/* ====================================================================
 *  ATLAS VERTEX — EXTRAÇÃO DE COORDENADAS v2
 *  --------------------------------------------------------------------
 *  Corrige três falhas observadas em memoriais georreferenciados reais:
 *
 *  1) ESTAÇÕES DE REFERÊNCIA VIRAVAM VÉRTICES.
 *     Memoriais certificados encerram com "…georreferenciadas ao Sistema
 *     Geodésico Brasileiro, a partir da estação ativa IBGE-BELE-93620,
 *     de coordenadas N=9.844.131,659m E=782.362,747m…". Essas coordenadas
 *     têm exatamente a mesma forma das dos vértices e eram capturadas como
 *     se fossem parte do perímetro — em outra zona UTM e a centenas de km.
 *     Caso real (matrícula 6.430): 874,6602 ha extraídos como 15.327.589 ha.
 *
 *  2) VÉRTICE COM COORDENADA PERDIDA NA DIGITAÇÃO SUMIA DO POLÍGONO.
 *     Ex.: "até o vértice CRA-P-1351 de coordenadas N CRA-P-1352 de
 *     coordenadas N 9.306.952,038m e E 263.278,269m" — o 1351 ficava sem
 *     coordenada e o 1352 era engolido junto. Agora o 1351 é reconstruído
 *     pelo azimute/distância do próprio memorial e o 1352 é recuperado.
 *
 *  3) O FORMATO NARRATIVO SIGEF NÃO ERA LIDO COMO CADEIA.
 *     "AZ - DIST, até o vértice X de coordenadas N … e E …" não casava com
 *     nenhum dos extratores de lados, então a reconciliação por caminhamento
 *     nunca rodava e erros de coordenada passavam sem aviso.
 *
 *  As funções deste arquivo NÃO dependem do index.php e podem ser testadas
 *  isoladamente (veja geo_extract_test.php).
 * ==================================================================== */

/* ---------------------------------------------------------------- *
 *  NORMALIZAÇÃO
 * ---------------------------------------------------------------- */

/** Normalização base: unifica símbolos de grau/minuto/segundo e conserta
 *  as confusões de OCR mais comuns em memoriais escaneados. */
function geoV2Normalizar($text)
{
    $t = (string) $text;
    if (function_exists('mb_check_encoding') && !mb_check_encoding($t, 'UTF-8')) {
        $t = mb_convert_encoding($t, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
    }
    $t = str_replace(["\xC2\xBA", "\xC2\xB0", "&deg;", "&#176;", "&ordm;", "&#186;"], '°', $t);
    $t = str_replace(["\xE2\x80\x98", "\xE2\x80\x99", "\xC2\xB4", "`", "\xE2\x80\xB2"], "'", $t);
    $t = str_replace(["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\xB3", "''"], '"', $t);

    // "201o53'51"" / "58O08'22"" -> letra o/O no lugar do símbolo de grau
    $t = preg_replace('/(\d{1,3})\s*[oO]\s*(\d{1,2})\s*\'/u', '$1°$2\'', $t);
    // "213°44,42"" -> vírgula no lugar do apóstrofo dos minutos
    $t = preg_replace('/(\d{1,3})\s*°\s*(\d{1,2})\s*,\s*(\d{1,2}(?:[.,]\d+)?)\s*"/u', '$1°$2\'$3"', $t);
    // "44° 28 ' 19" -> remove espaços internos do DMS
    $t = preg_replace('/(\d)\s*°\s*/u', '$1°', $t);

    return $t;
}

/** Repara dígitos trocados por letras dentro de um token numérico
 *  ("109,llm" -> "109,11"; "l.055,53" -> "1.055,53"). */
function geoV2RepararDigitos($s)
{
    return strtr((string) $s, ['l' => '1', 'L' => '1', 'I' => '1', 'i' => '1', 'O' => '0', 'o' => '0']);
}

/** Número no padrão brasileiro -> float. "9.310.179,196" => 9310179.196
 *  Diferente de uma conversão ingênua: pontos isolados de milhar não viram
 *  decimal (senão "9.310.179" seria lido como 9,310179). */
function geoV2Numero($raw)
{
    $raw = trim(geoV2RepararDigitos($raw));
    $raw = preg_replace('/\s+/', '', $raw);
    if ($raw === '') return 0.0;
    if (strpos($raw, ',') !== false) {                       // vírgula = decimal
        $p   = strrpos($raw, ',');
        $int = preg_replace('/\D/', '', substr($raw, 0, $p));
        $dec = preg_replace('/\D/', '', substr($raw, $p + 1));
        return (float) (($int === '' ? '0' : $int) . '.' . ($dec === '' ? '0' : $dec));
    }
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $raw)) {          // só separador de milhar
        return (float) preg_replace('/\D/', '', $raw);
    }
    if (substr_count($raw, '.') === 1) {                      // ponto decimal (OCR/EN)
        $p   = strrpos($raw, '.');
        $int = preg_replace('/\D/', '', substr($raw, 0, $p));
        $dec = preg_replace('/\D/', '', substr($raw, $p + 1));
        return (float) (($int === '' ? '0' : $int) . '.' . ($dec === '' ? '0' : $dec));
    }
    return (float) preg_replace('/\D/', '', $raw);
}

/** DMS -> grau decimal. */
function geoV2Dms($d, $m = 0, $s = 0)
{
    $d = (float) preg_replace('/[^\d]/', '', (string) $d);
    $m = (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', (string) $m));
    $s = (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', (string) $s));
    return $d + $m / 60.0 + $s / 3600.0;
}

/* ---------------------------------------------------------------- *
 *  RECORTE DO TEXTO ÚTIL
 * ---------------------------------------------------------------- */

/** Trechos que, quando aparecem, indicam coordenadas que NÃO são vértices
 *  do perímetro: estações ativas da RBMC, vértices de apoio, bases, etc. */
function geoV2MascararEstacoes($t, array &$avisos)
{
    $inicio = '/(?:a\s+partir\s+d[ao]s?\s+)?'
        . '(?:esta[çc][õo]es\s+ativas|esta[çc][ãa]o\s+ativa|esta[çc][ãa]o\s+geod[ée]sica'
        . '|esta[çc][õo]es\s+geod[ée]sicas|rede\s+brasileira\s+de\s+monitoramento'
        . '|\bRBMC\b|IBGE\s*-\s*[A-Z]{3,4}\s*-\s*\d{3,6})/iu';
    $fim = '/\bsendo\s+que\b|\bTodos\s+os\s+azimutes\b|\btendo\s+como\s+datum\b'
        . '|\bPROPRIET[ÁA]RI[OA]S?\b|\bREGISTRO\s+ANTERIOR\b/iu';

    if (!preg_match($inicio, $t, $mi, PREG_OFFSET_CAPTURE)) return $t;
    $ini = $mi[0][1];

    $resto = substr($t, $ini);
    $len   = strlen($resto);
    if (preg_match($fim, $resto, $mf, PREG_OFFSET_CAPTURE) && $mf[0][1] > 0) {
        $len = $mf[0][1];
    }
    $bloco = substr($t, $ini, $len);

    // só mascara se o bloco realmente contiver coordenadas (senão é texto inócuo)
    if (!preg_match('/[NE]\s*=?\s*\d{1,3}(?:\.\d{3})+,\d+/u', $bloco)) return $t;

    $n = preg_match_all('/[NE]\s*=?\s*\d{1,3}(?:\.\d{3})+,\d+/u', $bloco);
    $avisos[] = 'Bloco de estações de referência (RBMC/IBGE) ignorado: '
        . $n . ' coordenada(s) fora do perímetro.';

    return substr($t, 0, $ini) . str_repeat(' ', $len) . substr($t, $ini + $len);
}

/** Corta o rodapé cartorário (qualificação das partes, selo, emolumentos).
 *  Só corta se o que sobra ainda tiver coordenadas suficientes — assim nunca
 *  amputa um memorial cujo quadro de vértices venha depois. */
function geoV2CortarRodape($t, array &$avisos)
{
    $marcas = '/\bPROPRIET[ÁA]RI[OA]S?\s*:|\bREGISTRO\s+ANTERIOR\s*:'
        . '|\bGEORREFERENCIAMENTO\s+DO\s+INCRA\b|\bPrenota[çc][ãa]o\s*:'
        . '|\bEmolumentos?\s+e\s+FERC\b|\bDou\s+F[ée]\b|\bSelo\s*:/iu';
    if (!preg_match($marcas, $t, $m, PREG_OFFSET_CAPTURE)) return $t;
    $pos = $m[0][1];
    if ($pos < 200) return $t;

    $cabeca = substr($t, 0, $pos);
    $cauda  = substr($t, $pos);

    $coordRe = '/\d{1,3}(?:\.\d{3})+,\d+|\d+\s*°\s*\d+\s*\'/u';
    $nCab = preg_match_all($coordRe, $cabeca);
    $nCau = preg_match_all($coordRe, $cauda);
    if ($nCab < 6 || $nCau > $nCab) return $t;      // a cauda é que tem os dados: não corta

    if ($nCau > 0) $avisos[] = 'Rodapé da matrícula (qualificação/selo) descartado na leitura das coordenadas.';
    return $cabeca;
}

/** Saneamento completo do texto antes de qualquer extração.
 *  Devolve ['texto'=>..., 'avisos'=>[...]]. */
function geoV2Sanear($texto)
{
    $avisos = [];
    $t = geoV2Normalizar($texto);
    $t = geoV2MascararEstacoes($t, $avisos);
    $t = geoV2CortarRodape($t, $avisos);
    return ['texto' => $t, 'avisos' => $avisos];
}

/* ---------------------------------------------------------------- *
 *  FILTRO DE VÉRTICES DISCREPANTES (rede de segurança final)
 * ---------------------------------------------------------------- */

/** Mediana de uma lista numérica. */
function geoV2Mediana(array $a)
{
    if (!$a) return 0.0;
    sort($a, SORT_NUMERIC);
    $n = count($a); $k = intdiv($n, 2);
    return ($n % 2) ? (float) $a[$k] : (((float) $a[$k - 1] + (float) $a[$k]) / 2.0);
}

/**
 * Descarta vértices geograficamente impossíveis para um mesmo imóvel.
 * Critério robusto (MAD): distância até o centro mediano maior que
 * 8x a distância mediana dos demais, com piso de 2 km e teto de 60 km.
 * Um imóvel de 100.000 ha tem raio ~18 km — o piso e o fator garantem que
 * nenhum vértice legítimo seja descartado.
 *
 * $pares = [[N, E], ...]. Devolve os pares mantidos.
 */
function geoV2FiltrarDiscrepantes(array $pares, array &$avisos)
{
    $n = count($pares);
    if ($n < 4) return $pares;

    $medN = geoV2Mediana(array_column($pares, 0));
    $medE = geoV2Mediana(array_column($pares, 1));
    $dist = [];
    foreach ($pares as $p) $dist[] = hypot($p[0] - $medN, $p[1] - $medE);

    $medD  = geoV2Mediana($dist);
    $limite = max(2000.0, min(60000.0, 8.0 * $medD));

    $out = []; $fora = 0;
    foreach ($pares as $i => $p) {
        if ($dist[$i] > $limite) { $fora++; continue; }
        $out[] = $p;
    }
    if ($fora > 0 && count($out) >= 3) {
        $avisos[] = $fora . ' coordenada(s) descartada(s) por estarem a mais de '
            . number_format($limite / 1000, 1, ',', '.') . ' km do imóvel '
            . '(provável estação de referência ou coordenada de outro documento).';
        return $out;
    }
    return $pares;
}

/* ---------------------------------------------------------------- *
 *  PARSER SEQUENCIAL DO MEMORIAL GEORREFERENCIADO (formato SIGEF narrativo)
 * ---------------------------------------------------------------- */

/* Padrões reutilizados */
define('GEOV2_NUM',    '\d{1,3}(?:\.\d{3})+(?:,\d+)?|\d{4,9}(?:[.,]\d+)?');
define('GEOV2_ROTULO', '[A-Z0-9][A-Z0-9\-\.\/]{2,24}');

/** Lê "N <num> [m] e E <num> [m]" (ou "N=<num> E=<num>") logo após a posição
 *  informada. Devolve [N, E] ou null. */
function geoV2LerParNE($trecho)
{
    $re = '/\bN\s*[:=]?\s*(' . GEOV2_NUM . ')\s*m?\s*(?:,|e|;)?\s*\bE\s*[:=]?\s*(' . GEOV2_NUM . ')\s*m?/iu';
    if (!preg_match($re, $trecho, $m)) return null;
    $N = geoV2Numero($m[1]);
    $E = geoV2Numero($m[2]);
    if ($N < 1000000 || $N > 10500000) return null;
    if ($E < 100000  || $E > 999999)   return null;
    return [$N, $E];
}

/** Varre um trecho atrás de TODAS as ocorrências "[rótulo] de coordenadas N … e E …".
 *  Devolve [['rotulo'=>..., 'N'=>..., 'E'=>...], ...] na ordem do texto. */
function geoV2LerCoordenadasRotuladas($trecho)
{
    $re = '/(?:v[ée]rtice\s+)?(' . GEOV2_ROTULO . ')?\s*,?\s*de\s+coordenadas\s+'
        . 'N\s*[:=]?\s*(' . GEOV2_NUM . ')\s*m?\s*(?:,|e|;)?\s*E\s*[:=]?\s*(' . GEOV2_NUM . ')\s*m?/iu';
    preg_match_all($re, $trecho, $ms, PREG_SET_ORDER);
    $out = [];
    foreach ($ms as $m) {
        $N = geoV2Numero($m[2]);
        $E = geoV2Numero($m[3]);
        if ($N < 1000000 || $N > 10500000) continue;
        if ($E < 100000  || $E > 999999)   continue;
        $out[] = ['rotulo' => strtoupper(trim((string) ($m[1] ?? ''))), 'N' => $N, 'E' => $E];
    }
    return $out;
}

/**
 * Parser da cadeia narrativa:
 *   "<az>° <min>' <seg>" - <dist>m, até o vértice <RÓTULO> de coordenadas N <n>m e E <e>m;"
 *
 * Monta a lista de vértices NA ORDEM DO DOCUMENTO, preenchendo por caminhamento
 * (azimute + distância) os vértices cuja coordenada se perdeu na digitação, e
 * recuperando vértices cujo cabeçalho de lado se perdeu mas cuja coordenada
 * sobreviveu.
 *
 * Retorno:
 *   ok, pares [[N,E],...], rotulos[], legs[], reconstruidos[], divergencias[], avisos[]
 */
function extractMemorialGeorreferenciado($texto, $zone = 23, $south = true, $tolM = 0.25)
{
    $vazio = ['ok' => false, 'pares' => [], 'rotulos' => [], 'legs' => [],
              'reconstruidos' => [], 'divergencias' => [], 'suprimidos' => [], 'avisos' => []];

    $san    = geoV2Sanear($texto);
    $t      = $san['texto'];
    $avisos = $san['avisos'];

    /* ---------- 1ª passada: tokeniza a cadeia em passos ---------- */
    $reLeg = '/(\d{1,3})\s*°\s*(\d{1,2})\s*\'\s*(\d{1,2}(?:[.,]\d+)?)?\s*"?\s*'   // azimute
        . '[^0-9]{0,12}?'                                                          // separador / travessão
        . '([\d][\d.,\slLIiOo]*?)\s*m?\s*,?\s*'                                    // distância
        . 'at[ée]\s+(?:o\s+)?(?:v[ée]rtice\s+)?(' . GEOV2_ROTULO . ')/iu';
    if (!preg_match_all($reLeg, $t, $legsM, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) return $vazio;
    if (count($legsM) < 3) return $vazio;

    // vértice inicial (âncora): a última coordenada rotulada antes do 1º lado
    $ancoras = geoV2LerCoordenadasRotuladas(substr($t, 0, $legsM[0][0][1]));
    if (!$ancoras) return $vazio;
    $anc = $ancoras[count($ancoras) - 1];

    $rotIni = ($anc['rotulo'] !== '') ? $anc['rotulo'] : 'V-01';
    $passos = [];
    $nLegs  = count($legsM);
    for ($i = 0; $i < $nLegs; $i++) {
        $m   = $legsM[$i];
        $az  = geoV2Dms($m[1][0], $m[2][0] ?? '0', $m[3][0] ?? '0');
        $dis = geoV2Numero($m[4][0]);
        $rot = strtoupper(trim($m[5][0]));
        if ($az < 0 || $az > 360 || $dis <= 0 || $dis > 200000) continue;

        $ini    = $m[0][1] + strlen($m[0][0]);
        $fim    = ($i + 1 < $nLegs) ? $legsM[$i + 1][0][1] : strlen($t);
        $trecho = substr($t, $ini, max(0, $fim - $ini));

        $achados = geoV2LerCoordenadasRotuladas($trecho);
        $coord   = null;
        if ($achados) {
            $a0 = $achados[0];
            if ($a0['rotulo'] === '' || $a0['rotulo'] === $rot) {
                $coord = [$a0['N'], $a0['E']];
                array_shift($achados);
            }
        }
        $orfaos = [];
        foreach ($achados as $orf) {
            if ($orf['rotulo'] === '' || $orf['rotulo'] === $rot || $orf['rotulo'] === $rotIni) continue;
            $orfaos[] = $orf;
        }

        $passos[] = [
            'az' => $az, 'dist' => $dis, 'rot' => $rot, 'coord' => $coord, 'orfaos' => $orfaos,
            'fecha' => ($rot === $rotIni) || (bool) preg_match('/ponto\s+inicial/iu', $trecho),
        ];
    }
    if (count($passos) < 3) return $vazio;

    /* ---------- 2ª passada: monta a cadeia ---------- */
    $pares   = [[$anc['N'], $anc['E']]];
    $rotulos = [$rotIni];
    $legs = []; $reconst = []; $diverg = []; $suprim = [];

    $nP = count($passos);
    for ($i = 0; $i < $nP; $i++) {
        $p = $passos[$i];
        $legs[] = ['az' => $p['az'], 'dist' => $p['dist'], 'para' => $p['rot']];

        $ult  = $pares[count($pares) - 1];
        $calc = [$ult[0] + $p['dist'] * cos(deg2rad($p['az'])),
                 $ult[1] + $p['dist'] * sin(deg2rad($p['az']))];

        if ($p['fecha']) {
            $d = hypot($calc[0] - $pares[0][0], $calc[1] - $pares[0][1]);
            $perim = array_sum(array_column($legs, 'dist'));
            if ($d > max(1.0, 0.001 * $perim)) {
                $avisos[] = 'Erro de fechamento da poligonal: ' . number_format($d, 2, ',', '.') . ' m.';
            }
            break;
        }

        if ($p['coord'] === null) {
            $pares[]   = $calc;
            $rotulos[] = $p['rot'];
            $reconst[] = ['rotulo' => $p['rot'], 'N' => $calc[0], 'E' => $calc[1],
                          'az' => $p['az'], 'dist' => $p['dist']];
        } else {
            $d = hypot($p['coord'][0] - $calc[0], $p['coord'][1] - $calc[1]);

            /* VÉRTICE SUPRIMIDO NA TRANSCRIÇÃO
             * A coordenada declarada está longe demais do caminhamento, MAS o lado
             * seguinte, partido DESSA coordenada, cai exatamente no vértice seguinte.
             * Isso significa que a coordenada não é deste vértice: é do próximo, e o
             * vértice intermediário foi perdido na transcrição (o rótulo ficou colado
             * na coordenada errada). Caso real: a IA, ao ler o PDF da matrícula 6.430,
             * fundiu CRA-P-1351 e CRA-P-1352 numa linha só — 42 vértices em vez de 43,
             * 874,4336 ha em vez de 874,6602 ha.
             * Reconstrói o vértice perdido pelo caminhamento e mantém os dois. */
            $ehSuprimido = false;
            if ($d > max(1.0, 4 * $tolM) && isset($passos[$i + 1]) && $passos[$i + 1]['coord'] !== null) {
                $q  = $passos[$i + 1];
                $c2 = [$p['coord'][0] + $q['dist'] * cos(deg2rad($q['az'])),
                       $p['coord'][1] + $q['dist'] * sin(deg2rad($q['az']))];
                $d2 = hypot($c2[0] - $q['coord'][0], $c2[1] - $q['coord'][1]);
                // o encadeamento a partir da coordenada declarada é coerente => falta um vértice
                if ($d2 <= max($tolM, 0.02 * $d)) $ehSuprimido = true;
            }

            if ($ehSuprimido) {
                $pares[]   = $calc;                 // o vértice que o rótulo realmente designa
                $rotulos[] = $p['rot'];
                $reconst[] = ['rotulo' => $p['rot'], 'N' => $calc[0], 'E' => $calc[1],
                              'az' => $p['az'], 'dist' => $p['dist']];
                $pares[]   = $p['coord'];           // a coordenada declarada é do vértice seguinte
                $rotulos[] = '(vértice sem rótulo)';
                $suprim[]  = ['depois_de' => $p['rot'], 'delta' => $d,
                              'N' => $p['coord'][0], 'E' => $p['coord'][1]];
            } else {
                $pares[]   = $p['coord'];
                $rotulos[] = $p['rot'];
                if ($d > $tolM) {
                    $diverg[] = ['rotulo' => $p['rot'], 'delta' => $d,
                                 'az' => $p['az'], 'dist' => $p['dist']];
                }
            }
        }

        // vértices órfãos: coordenada presente no texto sem cabeçalho de lado próprio
        foreach ($p['orfaos'] as $orf) {
            $rotulos[] = $orf['rotulo'];
            $pares[]   = [$orf['N'], $orf['E']];
            $avisos[]  = 'Vértice ' . $orf['rotulo'] . ' recuperado: o texto perdeu o azimute/distância '
                . 'que levava até ele, mas as coordenadas estavam presentes.';
        }
    }

    if (count($pares) < 3) return $vazio;

    // rede de segurança (caso alguma coordenada estranha tenha escapado)
    $antes = count($pares);
    $pares = geoV2FiltrarDiscrepantes($pares, $avisos);
    if (count($pares) !== $antes) $rotulos = array_slice($rotulos, 0, count($pares));

    if ($reconst) {
        $lst = implode(', ', array_column($reconst, 'rotulo'));
        $avisos[] = count($reconst) . ' vértice(s) sem coordenada no documento (' . $lst
            . ') reconstruído(s) pelo azimute/distância do próprio memorial.';
    }
    if ($suprim) {
        $lst = implode(', ', array_column($suprim, 'depois_de'));
        $avisos[] = count($suprim) . ' vértice(s) suprimido(s) na transcrição foram restaurados: '
            . 'a coordenada escrita logo após ' . $lst . ' pertence, na verdade, ao vértice '
            . 'seguinte da cadeia. O vértice intermediário foi recalculado pelo azimute/distância.';
    }
    if ($diverg) {
        $pior = 0.0;
        foreach ($diverg as $dv) $pior = max($pior, $dv['delta']);
        $avisos[] = count($diverg) . ' vértice(s) com coordenada divergente do caminhamento '
            . '(maior desvio ' . number_format($pior, 2, ',', '.') . ' m).';
    }

    return ['ok' => true, 'pares' => $pares, 'rotulos' => $rotulos, 'legs' => $legs,
            'reconstruidos' => $reconst, 'divergencias' => $diverg, 'suprimidos' => $suprim,
            'avisos' => $avisos];
}

/** Área (Gauss) em hectares direto sobre pares UTM [[N,E],...]. */
function geoV2AreaHa(array $pares)
{
    $n = count($pares);
    if ($n < 3) return 0.0;
    $a = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $j = ($i + 1) % $n;
        $a += $pares[$i][1] * $pares[$j][0] - $pares[$j][1] * $pares[$i][0];
    }
    return abs($a) / 2.0 / 10000.0;
}

/** Perímetro em metros sobre pares UTM [[N,E],...]. */
function geoV2PerimetroM(array $pares)
{
    $n = count($pares);
    if ($n < 3) return 0.0;
    $p = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $j = ($i + 1) % $n;
        $p += hypot($pares[$j][0] - $pares[$i][0], $pares[$j][1] - $pares[$i][1]);
    }
    return $p;
}

/* ---------------------------------------------------------------- *
 *  CADEIA FLEXÍVEL: "distância … e azimute …, chega-se no marco X"
 *  --------------------------------------------------------------------
 *  Cobre os memoriais mais comuns dos cartórios do Maranhão, em que:
 *   - a DISTÂNCIA vem ANTES do azimute ("com uma distância de 304,66 metros
 *     e com o azimute plano de 90°00'00''"), ordem que os extratores
 *     antigos não reconheciam; e
 *   - um dos lados NÃO tem azimute por ser limite natural ("seguindo pela
 *     margem direita do Rio Sucupira, no sentido jusante, com uma distância
 *     de 306,53 metros"). Esse lado é resolvido pelo FECHAMENTO: caminha-se
 *     para a frente a partir da âncora e para trás a partir do ponto de
 *     retorno, e a lacuna fica determinada geometricamente.
 * ---------------------------------------------------------------- */

/** Âncora em coordenadas planas UTM, aceitando os rótulos por extenso
 *  ("9.290.927,116m Norte e 262.918,002m Leste") e a forma "N=… E=…". */
function geoV2AncoraUTM($t)
{
    $N = '\d{1,2}\.\d{3}\.\d{3}(?:,\d+)?|\d{7,8}(?:[.,]\d+)?';
    $E = '\d{3}\.\d{3}(?:,\d+)?|\d{6}(?:[.,]\d+)?';

    // Norte primeiro
    if (preg_match('/(' . $N . ')\s*m?\s*(?:N\b|Norte)[^0-9]{0,30}?(' . $E . ')\s*m?\s*(?:E\b|Leste|Este)/iu', $t, $m)) {
        $n = geoV2Numero($m[1]); $e = geoV2Numero($m[2]);
        if ($n >= 1000000 && $n <= 10500000 && $e >= 100000 && $e <= 999999) return [$n, $e];
    }
    // Leste primeiro
    if (preg_match('/(' . $E . ')\s*m?\s*(?:E\b|Leste|Este)[^0-9]{0,30}?(' . $N . ')\s*m?\s*(?:N\b|Norte)/iu', $t, $m)) {
        $e = geoV2Numero($m[1]); $n = geoV2Numero($m[2]);
        if ($n >= 1000000 && $n <= 10500000 && $e >= 100000 && $e <= 999999) return [$n, $e];
    }
    // "N=… e E=…"
    return geoV2LerParNE($t);
}

/**
 * Lê a descrição do perímetro como uma sequência de trechos delimitados por
 * "chega-se no marco X" / "até o vértice X", extraindo de cada trecho a
 * distância e — quando houver — o azimute.
 *
 * Retorna ['ancora'=>[N,E]|null, 'legs'=>[['az'=>float|null,'dist'=>float,'para'=>string], ...]].
 */
function geoV2LerCadeiaFlex($t)
{
    $reFim = '/(?:chega[\s\-]*se\s+(?:no|ao|n[ao])?\s*|at[ée]\s+(?:o|a)\s+)'
        . '(?:marco|v[ée]rtice|ponto)\s+(' . GEOV2_ROTULO . ')/iu';
    if (!preg_match_all($reFim, $t, $ms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return ['ancora' => null, 'legs' => []];
    }

    $cabeca = substr($t, 0, $ms[0][0][1]);
    $ancora = geoV2AncoraUTM($cabeca);

    $legs = []; $pos = 0;
    foreach ($ms as $m) {
        $ini    = $pos;
        $fim    = $m[0][1];
        $trecho = substr($t, $ini, max(0, $fim - $ini));
        $pos    = $m[0][1] + strlen($m[0][0]);
        $rot    = strtoupper(trim($m[1][0]));

        if (!preg_match('/dist[âa]nci?a\s*(?:de)?\s*([\d][\d.,\slLIiOo]*?)\s*(?:metros|mts|m)\b/iu', $trecho, $md)) {
            // formatos "AZ - DIST m," sem a palavra distância
            if (!preg_match('/°[^0-9]{0,12}([\d][\d.,]*)\s*m\b/u', $trecho, $md)) continue;
        }
        $dist = geoV2Numero($md[1]);
        if ($dist <= 0 || $dist > 200000) continue;

        $az = null;
        if (preg_match('/azimute[^0-9]{0,24}(\d{1,3})\s*°\s*(?:(\d{1,2})\s*\'\s*(?:(\d{1,2}(?:[.,]\d+)?)\s*"?)?)?/iu', $trecho, $ma)) {
            $az = geoV2Dms($ma[1], $ma[2] ?? '0', $ma[3] ?? '0');
        } elseif (preg_match('/(?<![,\d])(\d{1,3})\s*°\s*(\d{1,2})\s*\'\s*(\d{1,2}(?:[.,]\d+)?)?\s*"?\s*[-–—\s]/u', $trecho, $ma)) {
            $az = geoV2Dms($ma[1], $ma[2] ?? '0', $ma[3] ?? '0');
        }
        if ($az !== null && ($az < 0 || $az > 360)) $az = null;

        $legs[] = ['az' => $az, 'dist' => $dist, 'para' => $rot];
    }
    return ['ancora' => $ancora, 'legs' => $legs];
}

/**
 * Reconstrói o polígono a partir da âncora e dos lados, resolvendo UM lado sem
 * azimute pelo fechamento (limite natural: rio, grota, estrada).
 *
 * Devolve ['ok','pares','rotulos','lacuna','misfech','avisos'].
 */
function extractCadeiaFlexPoligono($texto, $zone = 23, $south = true)
{
    $vazio = ['ok' => false, 'pares' => [], 'rotulos' => [], 'lacuna' => null, 'misfech' => 0.0, 'avisos' => []];

    $san = geoV2Sanear($texto);
    $c   = geoV2LerCadeiaFlex($san['texto']);
    $avisos = $san['avisos'];

    $legs = $c['legs'];
    $anc  = $c['ancora'];
    if (!$anc || count($legs) < 3) return $vazio;

    // o último lado costuma voltar ao marco inicial — ele fecha, não gera vértice novo
    $nL = count($legs);
    $rotIni = $legs[$nL - 1]['para'];
    $semAz  = [];
    foreach ($legs as $i => $lg) if ($lg['az'] === null) $semAz[] = $i;
    if (count($semAz) > 1) {
        $avisos[] = count($semAz) . ' lados sem azimute no memorial (limites naturais) — '
            . 'não é possível reconstruir a geometria só pelo fechamento.';
        return $vazio;
    }

    $pares   = [$anc];
    $rotulos = ['V-01'];

    if (!$semAz) {
        // todos os lados têm azimute: caminhamento direto, descartando o retorno ao início
        for ($i = 0; $i < $nL - 1; $i++) {
            $u = $pares[count($pares) - 1];
            $a = deg2rad($legs[$i]['az']); $d = $legs[$i]['dist'];
            $pares[]   = [$u[0] + $d * cos($a), $u[1] + $d * sin($a)];
            $rotulos[] = $legs[$i]['para'];
        }
        $u = $pares[count($pares) - 1];
        $a = deg2rad($legs[$nL - 1]['az']); $d = $legs[$nL - 1]['dist'];
        $volta = [$u[0] + $d * cos($a), $u[1] + $d * sin($a)];
        $mis = hypot($volta[0] - $anc[0], $volta[1] - $anc[1]);
        $rotulos[0] = $rotIni;
        return ['ok' => true, 'pares' => $pares, 'rotulos' => $rotulos,
                'lacuna' => null, 'misfech' => $mis, 'avisos' => $avisos];
    }

    /* Um lado sem azimute: caminha para a frente até ele e para trás a partir
       do ponto inicial (o último lado sempre retorna à âncora). */
    $g = $semAz[0];

    $frente = [$anc];
    for ($i = 0; $i < $g; $i++) {
        $u = $frente[count($frente) - 1];
        $a = deg2rad($legs[$i]['az']); $d = $legs[$i]['dist'];
        $frente[] = [$u[0] + $d * cos($a), $u[1] + $d * sin($a)];
    }
    $tras = [$anc];                       // caminha ao contrário, do fim para o início
    for ($i = $nL - 1; $i > $g; $i--) {
        $u = $tras[count($tras) - 1];
        $a = deg2rad($legs[$i]['az']); $d = $legs[$i]['dist'];
        $tras[] = [$u[0] - $d * cos($a), $u[1] - $d * sin($a)];
    }

    $ini = $frente[count($frente) - 1];   // extremidade anterior à lacuna
    $fim = $tras[count($tras) - 1];       // extremidade posterior à lacuna
    $dCalc = hypot($fim[0] - $ini[0], $fim[1] - $ini[1]);
    $azCalc = fmod(rad2deg(atan2($fim[1] - $ini[1], $fim[0] - $ini[0])) + 360.0, 360.0);
    $erro = $dCalc - $legs[$g]['dist'];

    $pares = $frente;
    for ($i = count($tras) - 1; $i >= 1; $i--) $pares[] = $tras[$i];

    $rotulos = [$rotIni];
    for ($i = 0; $i < $nL - 1; $i++) $rotulos[] = $legs[$i]['para'];
    $rotulos = array_slice($rotulos, 0, count($pares));

    $avisos[] = 'Lado sem azimute no memorial (limite natural) resolvido pelo fechamento da '
        . 'poligonal: ' . number_format($dCalc, 2, ',', '.') . ' m no azimute '
        . geoV2GrauParaDms($azCalc) . ' (o documento declara '
        . number_format($legs[$g]['dist'], 2, ',', '.') . ' m; diferença de '
        . number_format($erro, 3, ',', '.') . ' m).';

    return ['ok' => true, 'pares' => $pares, 'rotulos' => $rotulos,
            'lacuna' => ['indice' => $g, 'dist_calc' => $dCalc, 'az_calc' => $azCalc, 'erro' => $erro],
            'misfech' => abs($erro), 'avisos' => $avisos];
}

/** Grau decimal -> "GGG°MM'SS"". */
function geoV2GrauParaDms($dd)
{
    $dd = fmod($dd + 360.0, 360.0);
    $d  = (int) floor($dd);
    $m  = (int) floor(($dd - $d) * 60);
    $s  = ($dd - $d - $m / 60.0) * 3600.0;
    return $d . '°' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . "'"
        . str_pad(number_format($s, 0, ',', ''), 2, '0', STR_PAD_LEFT) . '"';
}

/* ---------------------------------------------------------------- *
 *  CONFERÊNCIA CONTRA O QUE O DOCUMENTO DECLARA
 * ---------------------------------------------------------------- */

/** Perímetro declarado no texto, em metros. */
function geoV2PerimetroDeclarado($texto)
{
    $t = geoV2Normalizar($texto);
    $re = '/per[íi]metro\s*(?:total)?\s*(?:de|:)?\s*([\d][\d.,]*)\s*(?:metros|mts|m)\b/iu';
    if (preg_match($re, $t, $m)) {
        $v = geoV2Numero($m[1]);
        if ($v > 0) return $v;
    }
    if (preg_match('/fechando\s+s?e?u?\s*per[íi]metro\s+com\s+([\d][\d.,]*)\s*(?:metros|m)\b/iu', $t, $m)) {
        $v = geoV2Numero($m[1]);
        if ($v > 0) return $v;
    }
    return null;
}

/** Área declarada no texto, em m². */
function geoV2AreaDeclarada($texto)
{
    $t = geoV2Normalizar($texto);
    if (!preg_match('/[áa]rea\s*(?:total)?\s*(?:de|:)?\s*([\d][\d.,]*)\s*(m²|m2|ha|has\.?|hectares?)/iu', $t, $m)) return null;
    $v = geoV2Numero($m[1]);
    if ($v <= 0) return null;
    return (stripos($m[2], 'h') === 0) ? $v * 10000.0 : $v;
}

/**
 * Compara a geometria extraída com a área/perímetro declarados no documento e
 * devolve avisos legíveis. É a rede que impede uma extração absurda de passar
 * despercebida: se a área calculada divergir da declarada acima da tolerância,
 * o usuário é avisado ANTES de gravar.
 *
 * $areaHa e $perimM vêm da geometria já montada.
 */
function geoV2Conferir($texto, $areaHa, $perimM, $tolPct = 2.0)
{
    $avisos = [];
    $decA = geoV2AreaDeclarada($texto);
    $decP = geoV2PerimetroDeclarado($texto);

    if ($decA !== null && $areaHa > 0) {
        $calc = $areaHa * 10000.0;
        $dif  = $calc - $decA;
        $pct  = ($decA > 0) ? abs($dif) / $decA * 100.0 : 0.0;
        if ($pct > $tolPct) {
            $avisos[] = 'ATENÇÃO — a área calculada pelas coordenadas ('
                . number_format($areaHa, 4, ',', '.') . ' ha) diverge '
                . number_format($pct, 1, ',', '.') . '% da área declarada no documento ('
                . number_format($decA / 10000, 4, ',', '.') . ' ha). '
                . 'Confira os vértices antes de gravar: normalmente indica coordenada perdida na '
                . 'digitação, vértice fora do perímetro ou zona UTM incorreta.';
        }
    }
    if ($decP !== null && $perimM > 0) {
        $pct = abs($perimM - $decP) / $decP * 100.0;
        if ($pct > $tolPct) {
            $avisos[] = 'O perímetro calculado (' . number_format($perimM, 2, ',', '.')
                . ' m) diverge ' . number_format($pct, 1, ',', '.') . '% do declarado ('
                . number_format($decP, 2, ',', '.') . ' m).';
        }
    }
    return $avisos;
}
