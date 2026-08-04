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
