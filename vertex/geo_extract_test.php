<?php
/* ====================================================================
 *  TESTE DA EXTRAÇÃO DE COORDENADAS v2  —  Atlas Vertex
 *  --------------------------------------------------------------------
 *  Rode pelo terminal, sem depender do banco nem da sessão:
 *
 *      cd C:\xampp\htdocs\atlas\vertex
 *      php geo_extract_test.php
 *
 *  Casos reais de matrículas do 1º Ofício de Porto Franco/MA.
 * ==================================================================== */

require_once __DIR__ . '/geo_extract_v2.php';

$falhas = 0;
$total  = 0;

/** UTM 23S só para o arnês de teste (o index.php não é carregado aqui). */
function geoV2PlanTesteUTM($lat, $lon, $zone = 23) {
    $a = 6378137.0; $f = 1 / 298.257223563; $k0 = 0.9996;
    $e2 = $f * (2 - $f); $ep2 = $e2 / (1 - $e2);
    $lon0 = deg2rad(($zone - 1) * 6 - 180 + 3);
    $latR = deg2rad($lat); $lonR = deg2rad($lon);
    $N = $a / sqrt(1 - $e2 * sin($latR) ** 2);
    $T = tan($latR) ** 2; $C = $ep2 * cos($latR) ** 2; $A = cos($latR) * ($lonR - $lon0);
    $M = $a * ((1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256) * $latR
       - (3 * $e2 / 8 + 3 * $e2 ** 2 / 32 + 45 * $e2 ** 3 / 1024) * sin(2 * $latR)
       + (15 * $e2 ** 2 / 256 + 45 * $e2 ** 3 / 1024) * sin(4 * $latR)
       - (35 * $e2 ** 3 / 3072) * sin(6 * $latR));
    $east = $k0 * $N * ($A + (1 - $T + $C) * $A ** 3 / 6
          + (5 - 18 * $T + $T ** 2 + 72 * $C - 58 * $ep2) * $A ** 5 / 120) + 500000.0;
    $north = $k0 * ($M + $N * tan($latR) * ($A ** 2 / 2
           + (5 - $T + 9 * $C + 4 * $C ** 2) * $A ** 4 / 24
           + (61 - 58 * $T + $T ** 2 + 600 * $C - 330 * $ep2) * $A ** 6 / 720));
    if ($lat < 0) $north += 10000000.0;
    return [$east, $north];
}

function ok($cond, $desc, $detalhe = '')
{
    global $falhas, $total;
    $total++;
    if ($cond) {
        echo "  [OK]    $desc\n";
    } else {
        $falhas++;
        echo "  [FALHA] $desc" . ($detalhe !== '' ? "  ->  $detalhe" : '') . "\n";
    }
}

function quase($a, $b, $tol) { return abs($a - $b) <= $tol; }

/* ------------------------------------------------------------------ *
 *  CASO 1 — Matrícula 6.430 (Fazenda Buritizinho)
 *  Formato SIGEF narrativo, 43 vértices. Três armadilhas reais:
 *   (a) estações ativas IBGE no fim do texto (viravam vértices);
 *   (b) vértice CRA-P-1351 sem coordenada (texto colapsado);
 *   (c) vértice CRA-P-1352 órfão (perdeu o azimute/distância).
 * ------------------------------------------------------------------ */
$m6430 = <<<'TXT'
Inicia-se a descrição deste perímetro no vértice CRA-M-0967, de coordenadas N=9.310.179,196m e E=265.216,098m, situado no limite do FAZENDA NOVA ESPERANÇA; deste, segue confrontando com FAZENDA NOVA ESPERANÇA, com o(s) seguinte(s) azimute(s) e distância (s) : 150°16'26'' - 686,71m, até o vértice CRA-M-0968 de coordenadas N 9.309.582,852m e E 265.556,606m; 208°01'24'' - 963,49m, até o vértice CRA-M-0969 de coordenadas N 9.308.732,325m e E 265.103,931m; 170°19'54'' -176,92m, até o vértice CRA-M-0970 de coordenadas N 9.308.557,921m e E 265.133,643m; 178°43'44'' - 150,38m, até o vértice CRA-M-0971 de coordenadas N 9.308.407,579m e E 265.136,979m; 198°27'06'' - 1054,66m, até o vértice CP5-M-0304 de coordenadas N 9.307.407,140m e E 264.803,175m; 291°48'51'' - 179,13m, até o vértice CP5-M-0303 de coordenadas N 9.307.473,706m e E 264.636,868m; 253°07'50'' - 125,16m, até o vértice CP5-M-0302 de coordenadas N 9.307.437,385m e E 264.517,091m; 303°18'32'' - 195,69m, até o vértice CP5-M0301 de coordenadas N 9.307.544,846m e E 264.353,552m; 280°33'07'' -605,41m, até o vértice CP5-M-0300 de coordenadas N 9.307.655,712m e E 263.758,382m; 279°09'35'' - 60,31m, até o vértice CRA-M-0972 de coordenadas N 9.307.665,313m e E 263.698,838m; 213°44,42'' - 212,51m, até o vértice CRA-P-1264 de coordenadas N 9.307.488,606m e E 263.580,789m; 201o53'51'' -79,98m, até o vértice CRA-P-1265 de coordenadas N 9.307.414,397m e E 263.550,961m; 184°56'50'' - 103,52m, até o vértice CRA-P-1266 de coordenadas N 9.307.311,264m e E 263.542,034m; 174°54'22'' -92,80m, até o vértice CRA-P-1267 de coordenadas N 9.307.218,835m e E 263.550,273m; 199°39'55'' - 76,40m, até o vértice CRA-P-1268 de coordenadas N 9.307.146,892m e E 263.524,563m; 191°58'48'' -176,33m, até o vértice CRA-P-1225 de coordenadas N 9.306.974,406m e E 263.487,963m; 224°40'09'' - 78,15m, até o vértice CRA-P-1226 de coordenadas N 9.306.918,825m e E 263.433,020m; 237°04'40'' - 41,35m, até o vértice CRA-P-1227 de coordenadas N 9.306.896,349m e E 263.398,307m; 271°52'51'' -87,63m, até o vértice CRA-P-1351 de coordenadas N CRA-P-1352 de coordenadas N 9.306.952,038m e E 263.278,269m; 286°54'34'' -62,50m, até o vértice CRA-P-1353 de coordenadas N 9.306.970,216m e E 263.218,474m; 249o29'30'' - 88,33m, até o vértice CRA-P-1354 de coordenadas N 9.306.939,271m e E 263.135,745m; 263°37'01'' -84,25111, até o vértice CRA-P-1355 de coordenadas N 9.306.929,905m e E 263.052,022m; 253°20'50'' - 95,62m, até o vértice CRA-P-1356 de coordenadas N 9.306.902,504m e E 262.960,415m; 243°01'22'' -107,02m, até o vértice CRA-P-1357 de coordenadas N 9.306.853,957m e E 262.865,042m; 224°54'51'' - 61,77m, até o vértice CRA-P-1358 de coordenadas N 9.306.810,216m e E 262.821,432m; 238o49'10'' - 32,72m, até o vértice CRA-P-1359 de coordenadas N 9.306.793,274m e E 262.793,436m; 195°53'21'' -63,68m, até o vértice CRA-P-1360 de coordenadas N 9.306.732,028m e E 262.776,002m; 261°38'32'' - 57,17m, até o vértice CP5-M-0323 de coordenadas N 9.306.723,718m e E 262.719,438m; 305°35'44'' - 50,64m, até o vértice CRA-P-1280 de coordenadas N 9.306.753,193m e E 262.678,261m; 253°57'47'' - 201,50m, até o vértice CRA-P-1278 de coordenadas N 9.306.697,526m e E 262.484,600m; 261°36'30'' - 115,97m, até o vértice CRA-P-1279 de coordenadas N 9.306.680,602m e E 262.369,876m; 223°46'53'' -152,69m, até o vértice CRA-P-1276 de coordenadas N 9.306.570,361m e E 262.264,227m; 240°25'45'' - 109,llm, até o vértice CRA-P-1277 de coordenadas N 9.306.516,514m e E 262.169,327m; 258°29'37'' - 130,23m, até o vértice CRA-P-1275 de coordenadas N 9.306.490,536m e E 262.041,713m; 250°17'07'' -163,34m, até o vértice CRA-P-1272 de coordenadas N 9.306.435,435m e E 261.887,946m; 211o58'02'' - 109,47m, até o vértice CRA-P-1273 de coordenadas N 9.306.342,565m e E 261.829,988m; 266°25'31'' - 54,76m, até o vértice CRA-M0988 de coordenadas N 9.306.339,151m e E 261.775,338m; 346°51'31'' - 2310,99m, até o vértice CRA-M-1006 de coordenadas N 9.308.589,623m e E 261.249,921m; 68°25'12'' - 1693,64m, até o vértice CRA-M-1000 de coordenadas N 9.309.212,543m e E 262.824,847m; 71°54'49'' - 20,84m, até o vértice CRA-M-1001 de coordenadas N 9.309.219,013m e E 262.844,658m; 58o08'22'' - 46,95m, até o vértice CRA-M-1002 de coordenadas N 9.309.243,798m e E 262.884,538m; 68°08'23''-2512,20m, até o vértice CRA-M-0967, ponto inicial da descrição deste perímetro. Todas as coordenadas aqui descritas estão georreferenciadas ao Sistema Geodesico Brasileiro, a partir da estação ativa IBGE-BELE-93620 (Belém-PA), de coordenadas N=9.844.131,659m E=782.362,747m, Meridiano Central 51° WGr, IBGE-BRAZ-91200 (Brasilia-DF), de coordenadas N=8.234.747,341m E=191.901,220m, Meridiano Central 45° WGr, IBGE-CRAT-92300 (Crato-CE), de coordenadas N=9.199.917,893m E=454.119,207m, Meridiano Central 39° WGr, sendo que as coordenadas do perímetro encontram-se representadas no Sistema UTM, referenciadas ao Meridiano Central nr. 45° WGr, tendo como datum o SIRGAS2000. Área total de 874,6602 ha, com perímetro de 13.533,90 metros.
TXT;

echo "\n== CASO 1 — Matrícula 6.430 (SIGEF narrativo + estações IBGE) ==\n";
$r = extractMemorialGeorreferenciado($m6430);
$area  = geoV2AreaHa($r['pares']);
$perim = geoV2PerimetroM($r['pares']);
ok(!empty($r['ok']), 'a cadeia foi reconhecida');
ok(count($r['pares']) === 43, 'extraiu 43 vértices', 'extraiu ' . count($r['pares']));
ok(quase($area, 874.6602, 0.0005), 'área = 874,6602 ha', number_format($area, 4, ',', '.') . ' ha');
ok(quase($perim, 13533.90, 0.05), 'perímetro = 13.533,90 m', number_format($perim, 2, ',', '.') . ' m');
ok(count($r['reconstruidos']) === 1 && $r['reconstruidos'][0]['rotulo'] === 'CRA-P-1351',
   'reconstruiu o vértice CRA-P-1351');
ok(in_array('CRA-P-1352', $r['rotulos'], true), 'recuperou o vértice órfão CRA-P-1352');
ok(count($r['divergencias']) === 0, 'nenhuma coordenada divergente do caminhamento',
   count($r['divergencias']) . ' divergência(s)');
$maxN = max(array_column($r['pares'], 0));
ok($maxN < 9400000, 'as estações IBGE NÃO entraram como vértices', 'N máximo = ' . $maxN);

/* ------------------------------------------------------------------ *
 *  CASO 1-B — a mesma 6.430, porém na transcrição da IA a partir do PDF.
 *  O Gemini "conserta" a linha corrompida juntando CRA-P-1351 e CRA-P-1352
 *  numa só: 42 vértices e 874,4336 ha em vez de 43 e 874,6602 ha.
 *  O parser precisa notar que a coordenada declarada está 61,99 m fora do
 *  caminhamento e que o lado seguinte, partindo dela, fecha no vértice
 *  certo — sinal de que falta um vértice no meio.
 * ------------------------------------------------------------------ */
echo "\n== CASO 1-B — 6.430 na transcrição da IA (vértice suprimido) ==\n";
$m6430ia = str_replace(
    'até o vértice CRA-P-1351 de coordenadas N CRA-P-1352 de coordenadas N 9.306.952,038m e E 263.278,269m;',
    'até o vértice CRA-P-1351 de coordenadas N 9.306.952,038m e E 263.278,269m;',
    $m6430
);
$r = extractMemorialGeorreferenciado($m6430ia);
$area  = geoV2AreaHa($r['pares']);
$perim = geoV2PerimetroM($r['pares']);
ok(count($r['pares']) === 43, 'restaurou os 43 vértices', 'ficou com ' . count($r['pares']));
ok(quase($area, 874.6602, 0.0005), 'área = 874,6602 ha', number_format($area, 4, ',', '.') . ' ha');
ok(quase($perim, 13533.90, 0.05), 'perímetro = 13.533,90 m', number_format($perim, 2, ',', '.') . ' m');
ok(count($r['suprimidos']) === 1 && $r['suprimidos'][0]['depois_de'] === 'CRA-P-1351',
   'identificou o vértice suprimido após CRA-P-1351');
ok(quase($r['suprimidos'][0]['delta'] ?? 0, 61.99, 0.05), 'desvio detectado = 61,99 m',
   number_format($r['suprimidos'][0]['delta'] ?? 0, 2, ',', '.') . ' m');
ok(count($r['divergencias']) === 0, 'sem divergências residuais');

/* ------------------------------------------------------------------ *
 *  CASO 2 — Matrícula 9.838 (Fazenda Brejo III)
 *  Ordem distância→azimute e um lado sem azimute (Rio Sucupira),
 *  resolvido pelo fechamento da poligonal.
 * ------------------------------------------------------------------ */
$m9838 = <<<'TXT'
Uma gleba de terras com a área de 48,4000has., denominada Fazenda Brejo III. Descrição do Perímetro:- Partindo do marco ME-236, definido pela coordenada geográfica de Latitude 6°24'37,67"SUL e Longitude 47°08'36,29"Oeste, Elipsóide SAD 69 e pela coordenada plana UTM 9.290.927,116m Norte e 262.918,002m Leste, referida ao meridiano central 45°WGr; deste, confrontando neste trecho com as terras de Manoel Oliveira Martins, seguindo com uma distância de 304,66 metros e com o azimute plano de 90°00'00", chega-se no marco ME-237; deste, confrontando neste trecho com as terras de Elienai Martins da Rocha, seguindo com uma distância de 1.610,24 metros e com o azimute plano de 190°22'16", chega-se no marco ME-241; deste, seguindo pela margem direita do Rio Sucupira, no sentido jusante, com uma distância de 306,53 metros, chega-se no marco ME-242; deste, confrontando neste trecho com as terras de Elionete Martins da Rocha, seguindo com uma distancia de 1.619,85 metros e com o azimute plano de 10°22'16", chega-se no marco ME-236, ponto inicial da descrição deste perímetro; com perímetro de 3.841,28 metros lineares.
TXT;

echo "\n== CASO 2 — Matrícula 9.838 (distância→azimute + lado sem azimute) ==\n";
$f = extractCadeiaFlexPoligono($m9838);
$area  = geoV2AreaHa($f['pares']);
$perim = geoV2PerimetroM($f['pares']);
ok(!empty($f['ok']), 'a cadeia flexível foi reconhecida');
ok(count($f['pares']) === 4, 'extraiu 4 vértices', 'extraiu ' . count($f['pares']));
ok(quase($area, 48.4000, 0.001), 'área = 48,4000 ha', number_format($area, 4, ',', '.') . ' ha');
ok(quase($perim, 3841.28, 0.05), 'perímetro = 3.841,28 m', number_format($perim, 2, ',', '.') . ' m');
ok($f['lacuna'] !== null && quase($f['lacuna']['az_calc'], 268.2325, 0.01),
   'lado do Rio Sucupira resolvido em ~268°14\'',
   $f['lacuna'] ? geoV2GrauParaDms($f['lacuna']['az_calc']) : 'sem lacuna');
ok($f['misfech'] < 0.05, 'erro de fechamento < 5 cm',
   number_format($f['misfech'], 3, ',', '.') . ' m');

/* ------------------------------------------------------------------ *
 *  CASO 2-B — Planilha de vértices do SIGEF achatada em texto corrido
 *  (São Mateus do Maranhão/MA, 11 vértices). Longitude antes da latitude,
 *  coluna de altitude e azimutes com a mesma aparência de coordenada.
 * ------------------------------------------------------------------ */
$plan = "FTO-P-633 -44\u{00BA}27'59,517 -4\u{00BA}01 '17,054 15,06; FTO-P634 164\u{00B0}57' 522,62 Rodovia Federal BR-316; "
      . "FTO-P-634 -44\u{00BA}27'55, 118 -4\u{00BA}01'33,484 16.25; FTO--P632 283\u{00B0}06'742,5 Patrim\u{00F4}nio Urbano de S\u{00E3}o Mateus do Maranh\u{00E3}o - MA; "
      . "FTO-P-632 --44\u{00B0}28'18,562 - 4\u{00B0}01'28,002 13,48; FTO-P-M-631 200\u{00B0}39' 2016,72 Patrimonio Urbano de S\u{00E3}o Mateus do Maranh\u{00E3}o; "
      . "FTO-M-631 -44\u{00B0}28'41,619 -4\u{00BA}02'29,439 14,22; FTO-M-630 274\u{00B0}08'374,45 Projeto de Assentamento Bocaina; "
      . "FTO-M-630 -44\u{00B0}28'53,727 -4\u{00BA}02'28,560 13,29 APG-M-676 358\u{00B0}38' 396,03 Projeto de\u{00B7} Assentamento Bocaina; "
      . "APG-M-676 -44\u{00B0}28'54,030 -4\u{00BA}02'15,671 15,62 FTO-M-629 10\u{00B0}06' :364,08 Projeto de Assentamento Bocaina; "
      . "FTO-M-629 -44\u{00B0}28'51,959 -4\u{00B0}02'04,002 21,77 FTO-M-628 352\u{00B0}56' 606,34 Projeto de Assentamento Bocaina: "
      . "FTO-M-628 -44\u{00B0}28'54,374 -4\u{00B0}01'44,412 24,05 FTO-P-100630 28\u{00BA}11' 352,72 Projeto de Assentamento Bocaina; "
      . "FTO-P-100630 -44\u{00BA}28'48,972 - 4\u{00BA}01'34,292 13,88 FTO-M-627 11\u{00B0}47' 170,68 Projeto de Assentamento Bocaina; "
      . "FTO-M-627 - 44\u{00B0}28'47,841 -4\u{00BA}01'28,852 11,22 FTO-M-626 45\u{00B0}04' 346,59 Projeto de Assentamento Bocaina; "
      . "FTOM-626 -44\u{00B0}28'39,885 -4\u{00B0}01'20,885 6,59; FTO-P-633 84\u{00B0}36' 1250,74 Jos\u{00E9} Lu\u{00ED}s Arruda.";

echo "\n== CASO 2-B — Planilha de vértices SIGEF (lon/lat em GMS) ==\n";
$p = extractPlanilhaSigefGeo($plan);
ok(!empty($p['ok']), 'a planilha foi reconhecida');
ok(count($p['pts']) === 11, 'extraiu 11 vértices', 'extraiu ' . count($p['pts']));
ok(quase($p['pts'][0][0], -4.0214039, 1e-6) && quase($p['pts'][0][1], -44.4665325, 1e-6),
   'FTO-P-633 com lat/lon corretos (longitude vinha primeiro)');
ok($p['rotulos'][0] === 'FTO-P-633' && $p['rotulos'][2] === 'FTO-P-632' && $p['rotulos'][10] === 'FTO-M-626',
   'rótulos normalizados (FTO--P632 e FTOM-626 corrigidos)',
   implode(',', $p['rotulos']));
ok(quase($p['altitudes'][1], 16.25, 0.001), 'altitude com ponto decimal ("16.25") lida');
ok(quase($p['legs'][1]['dist'], 742.50, 0.01) && quase($p['legs'][1]['az'], 283.1, 0.001),
   'azimute colado na distância ("283°06\'742,5") separado');
ok($p['legs'][0]['confrontante'] === 'Rodovia Federal BR-316',
   'confrontante preserva "BR-316"', $p['legs'][0]['confrontante']);
// nenhum azimute pode ter virado vértice
$fora = 0;
foreach ($p['pts'] as $pt) { if ($pt[1] > -44.0 || $pt[1] < -45.0) $fora++; }
ok($fora === 0, 'nenhum azimute foi confundido com coordenada', $fora . ' fora de faixa');
// área no plano UTM
$paresU = [];
foreach ($p['pts'] as $pt) { $u = geoV2PlanTesteUTM($pt[0], $pt[1]); $paresU[] = [$u[1], $u[0]]; }
ok(quase(geoV2AreaHa($paresU), 174.2477, 0.001), 'área = 174,2477 ha',
   number_format(geoV2AreaHa($paresU), 4, ',', '.') . ' ha');

/* ------------------------------------------------------------------ *
 *  CASO 2-C — Coordenadas em GRAU DECIMAL com os rótulos N/E trocados
 *  (matrícula 1817, lote urbano em Bom Jardim/MA). O valor escrito como
 *  "N" é a longitude e o escrito como "E" é a latitude; confiar no rótulo
 *  joga o imóvel no oceano. Os azimutes declarados são contra-azimutes.
 * ------------------------------------------------------------------ */
$m1817 = "Inicia-se a descri\u{00E7}\u{00E3}o deste per\u{00ED}metro no v\u{00E9}rtice 1, de coordenadas N -45.606018216666700 e "
       . "E -3.547271864444440; deste, segue confrontando com RUA SETE DE SETEMBRO, com os seguintes "
       . "azimutes e dist\u{00E2}ncias: 310\u{00B0}37'58\" e 9,15 m at\u{00E9} o v\u{00E9}rtice 2, de coordenadas N -45.605955733333300 e "
       . "E -3.547325809166670; deste, segue confrontando com O LOTE 29, com os seguintes azimutes e "
       . "dist\u{00E2}ncias: 40\u{00B0}37'58\" e 10,35 m at\u{00E9} o v\u{00E9}rtice 3, de coordenadas N -45.606016462222200 e "
       . "E -3.547396825277780; deste, segue confrontando com o LOTE 32, com os seguintes azimutes e "
       . "dist\u{00E2}ncias: 130\u{00B0}37'58\" e 9,15 m at\u{00E9} o v\u{00E9}rtice 4, de coordenadas N -45.606078945555600 e "
       . "E -3.547342880555560 ; deste, segue confrontando com LOTE 27, com os seguintes azimutes e "
       . "dist\u{00E2}ncias: 220\u{00B0}37'58\" e 10,35 m at\u{00E9} o v\u{00E9}rtice 1, ponto inicial da descri\u{00E7}\u{00E3}o deste per\u{00ED}metro. "
       . "\u{00C1}rea: 94,70 m\u{00B2}. Per\u{00ED}metro: 39,00 m. Datum WGS-84.";

echo "\n== CASO 2-C — Grau decimal com rótulos N/E trocados ==\n";
$g = extractCoordenadasDecimais($m1817);
ok(!empty($g['ok']), 'o formato em grau decimal foi reconhecido');
ok(count($g['pts']) === 4, 'extraiu 4 vértices', 'extraiu ' . count($g['pts']));
ok(quase($g['pts'][0][0], -3.547271864, 1e-8), 'latitude veio do campo rotulado "E"',
   (string) $g['pts'][0][0]);
ok(quase($g['pts'][0][1], -45.606018217, 1e-8), 'longitude veio do campo rotulado "N"',
   (string) $g['pts'][0][1]);
$temSwap = false; $temContra = false;
foreach ($g['avisos'] as $a) {
    if (strpos($a, 'invertidos') !== false)      $temSwap = true;
    if (strpos($a, 'CONTRA-AZIMUTES') !== false) $temContra = true;
}
ok($temSwap, 'avisou que os rótulos N/E estão trocados');
ok($temContra, 'detectou que os 4 azimutes são contra-azimutes');
$paresU = [];
foreach ($g['pts'] as $pt) { $u = geoV2PlanTesteUTM($pt[0], $pt[1]); $paresU[] = [$u[1], $u[0]]; }
ok(quase(geoV2AreaHa($paresU) * 10000, 94.70, 0.05), 'área = 94,70 m²',
   number_format(geoV2AreaHa($paresU) * 10000, 2, ',', '.') . ' m²');
ok(quase(geoV2PerimetroM($paresU), 39.00, 0.05), 'perímetro = 39,00 m',
   number_format(geoV2PerimetroM($paresU), 2, ',', '.') . ' m');

/* ------------------------------------------------------------------ *
 *  CASO 3 — Filtro de coordenadas discrepantes (rede de segurança)
 * ------------------------------------------------------------------ */
echo "\n== CASO 3 — Filtro de coordenadas discrepantes ==\n";
$pares = [
    [9290927.116, 262918.002], [9290927.116, 263222.662],
    [9289343.188, 262932.784], [9289333.730, 262626.395],
    [9844131.659, 782362.747],   // estação IBGE-BELE
];
$av = [];
$mant = geoV2FiltrarDiscrepantes($pares, $av);
ok(count($mant) === 4, 'descartou a estação distante', count($mant) . ' mantidos');
ok(quase(geoV2AreaHa($mant), 48.4000, 0.001), 'área volta a 48,4000 ha',
   number_format(geoV2AreaHa($mant), 4, ',', '.') . ' ha');

/* ------------------------------------------------------------------ *
 *  CASO 4 — Conferência contra o declarado
 * ------------------------------------------------------------------ */
echo "\n== CASO 4 — Conferência contra área/perímetro declarados ==\n";
ok(quase((float) geoV2AreaDeclarada($m6430), 8746602.0, 1.0), 'leu a área declarada (874,6602 ha)');
ok(quase((float) geoV2PerimetroDeclarado($m6430), 13533.90, 0.01), 'leu o perímetro declarado');
$c = geoV2Conferir($m6430, 874.6602, 13533.90);
ok(count($c) === 0, 'geometria correta não gera alerta', implode(' | ', $c));
$c = geoV2Conferir($m6430, 15327589.0, 3708458.0);
ok(count($c) === 2, 'geometria absurda gera alerta de área e de perímetro', count($c) . ' alerta(s)');

/* ------------------------------------------------------------------ *
 *  CASO 5 — Saneamento de OCR
 * ------------------------------------------------------------------ */
echo "\n== CASO 5 — Saneamento de OCR ==\n";
$n = geoV2Normalizar("201o53'51'' e 213°44,42'' e 58O08'22''");
ok(strpos($n, "201°53'51\"") !== false, "letra 'o' no lugar do grau corrigida", $n);
ok(strpos($n, "213°44'42\"") !== false, 'vírgula no lugar do apóstrofo corrigida', $n);
ok(quase(geoV2Numero('109,llm'), 109.11, 0.001), "'109,llm' lido como 109,11");
ok(quase(geoV2Numero('9.310.179,196'), 9310179.196, 0.001), 'milhar BR preservado');

echo "\n--------------------------------------------------\n";
echo ($falhas === 0 ? "TODOS OS $total TESTES PASSARAM.\n" : "$falhas de $total TESTES FALHARAM.\n");
exit($falhas === 0 ? 0 : 1);
