<?php
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
/* =====================================================================
 *  index.php (Atlas Dimensor)  —  Sistema Atlas
 *  Lê memoriais descritivos com coordenadas geográficas em GMS
 *  (graus/minutos/segundos), mapeia no Google Maps e grava no banco
 *  identificando o imóvel por nome ou número de matrícula.
 *
 *  Endpoints (POST acao=...):
 *    - processar : parseia o memorial e devolve pontos/área/perímetro
 *    - salvar    : grava o memorial mapeado na tabela memoriais_mapeados
 *    - listar    : lista memoriais já salvos
 *    - carregar  : devolve um memorial salvo pelo id
 *    - excluir   : remove um memorial salvo
 * ===================================================================== */

// ----- Integração com o Atlas: protege por sessão e usa a conexão mysqli padrão -----
require_once __DIR__ . '/session_check.php';
checkSession();                              // redireciona para ../login.php se não autenticado
require_once __DIR__ . '/db_connection2.php'; // fornece $conn (mysqli), padrão do Atlas

// Fuso horário do Maranhão (UTC-3, sem horário de verão) — corrige a data/hora dos relatórios.
date_default_timezone_set('America/Fortaleza');

/* ---------- Chave do Google Maps ---------- */
// Chave do mapa dinâmico (navegador). Pode ter restrição por "referer".
define('GMAPS_KEY', 'AIzaSyCeFWemOC1xUDqaZlSAu0yGYp8zJWQqpyk');
// Chave do Static Maps (usado pelo SERVIDOR ao gerar o PDF). Se a chave acima
// tiver restrição por referer, ela NÃO funciona no servidor — crie/use aqui uma
// chave sem restrição de referer (ou com restrição por IP do servidor) e com a
// "Maps Static API" habilitada no Google Cloud.
define('GMAPS_STATIC_KEY', 'AIzaSyD_uaOpwEVncDlEqhZ56fxjmVjySRl2h_0');

/* ---------- Diagnóstico do Static Maps: acesse dimensor/index.php?diag_staticmap=1 ---------- */
if (isset($_GET['diag_staticmap'])) {
    header('Content-Type: text/plain; charset=UTF-8');
    $url = 'https://maps.googleapis.com/maps/api/staticmap?center=-4.14,-46.9&zoom=12&size=400x200&maptype=hybrid&key=' . GMAPS_STATIC_KEY;
    echo "Teste de geração de imagem do mapa (lado servidor)\n";
    echo str_repeat('-', 60) . "\n";
    echo "URL:\n$url\n\n";
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err = curl_error($ch);
        curl_close($ch);
        echo "cURL: disponível\n";
        echo "HTTP status: $code\n";
        echo "Content-Type: $ctype\n";
        echo "Erro cURL: " . ($err !== '' ? $err : '(nenhum)') . "\n";
        echo "Tamanho da resposta: " . strlen((string)$data) . " bytes\n\n";
        if (strpos($ctype, 'image') !== false) {
            echo "RESULTADO: OK — o Google retornou uma IMAGEM. O relatório deve exibir o mapa.\n";
        } else {
            echo "RESULTADO: FALHA — o Google NÃO retornou imagem. Mensagem recebida:\n\n";
            echo substr((string)$data, 0, 3000) . "\n";
        }
    } else {
        echo "cURL: INDISPONÍVEL\n";
        echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'on' : 'OFF (impede o download da imagem)') . "\n";
        $data = @file_get_contents($url);
        echo "Tamanho da resposta: " . strlen((string)$data) . " bytes\n";
    }
    exit;
}

/* ---------- Download/visualização de anexo: dimensor/index.php?anexo=<id>[&dl=1] ---------- */
if (isset($_GET['anexo'])) {
    $aid = (int)$_GET['anexo'];
    $a = function_exists('anexoObter') ? anexoObter($conn, $aid) : null;
    if (!$a) { http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); echo 'Anexo não encontrado.'; exit; }
    $caminho = anexosDir() . '/' . $a['arquivo'];
    if (!is_file($caminho)) { http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); echo 'Arquivo ausente no servidor.'; exit; }
    $mime = $a['mime'] ?: 'application/octet-stream';
    $ext  = strtolower(pathinfo($a['arquivo'], PATHINFO_EXTENSION));
    if ($mime === 'application/octet-stream') { if ($ext === 'pdf') $mime = 'application/pdf'; elseif ($ext === 'kml') $mime = 'application/vnd.google-earth.kml+xml'; }
    $inline = empty($_GET['dl']) && $ext === 'pdf'; // PDF abre no navegador; demais baixam
    $nome = preg_replace('/[\r\n"]+/', '', (string)$a['nome_original']);
    if (!headers_sent()) {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nome . '"');
        header('Content-Length: ' . filesize($caminho));
        header('Cache-Control: private, max-age=0, must-revalidate');
    }
    readfile($caminho);
    exit;
}

/* ====================================================================
 *  BIBLIOTECA DE COORDENADAS
 * ==================================================================== */

/** Normaliza codificação e símbolos (°, ', ") do texto do memorial. */
function normalizeGeoText($text) {
    if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
    }
    $text = str_replace(["\xC2\xBA", "\xC2\xB0", "&deg;", "&#176;", "&ordm;", "&#186;"], '°', $text);
    $text = str_replace(["\xE2\x80\x98", "\xE2\x80\x99", "\xC2\xB4", "`", "\xE2\x80\xB2"], "'", $text);
    $text = str_replace(["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\xB3", "''"], '"', $text);
    return $text;
}

/**
 * Extrai coordenadas GMS SEM rótulo (tabela SIGEF: colunas Longitude/Latitude).
 * Exige o sinal NEGATIVO (coordenadas BR são negativas) — azimutes são positivos,
 * então não são confundidos. Classifica por grandeza: |grau|>=20 = longitude.
 */
function extractGeoCoordinatesTabela($rawText) {
    $t = normalizeGeoText($rawText);
    preg_match_all('/(-\s*\d+)\s*°\s*(\d+)\s*\'\s*([\d.,]+)\s*"/u', $t, $m, PREG_SET_ORDER);
    $lons = []; $lats = [];
    foreach ($m as $x) {
        $deg = abs((float) preg_replace('/[^\d]/', '', $x[1]));
        $val = dmsToDecimal($x[1], $x[2], $x[3]);
        if ($deg >= 20) $lons[] = $val; else $lats[] = $val;
    }
    $n = min(count($lons), count($lats));
    $pts = [];
    for ($i = 0; $i < $n; $i++) {
        $lat = $lats[$i]; $lng = $lons[$i];
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) $pts[] = [$lat, $lng];
    }
    return ['pts' => $pts, 'lon_count' => count($lons), 'lat_count' => count($lats)];
}

/** Converte Graus/Minutos/Segundos para grau decimal. */
function dmsToDecimal($deg, $min, $sec) {
    $negative = strpos((string)$deg, '-') !== false;
    $d = abs((float) str_replace(',', '.', preg_replace('/[^\d.,\-]/', '', (string)$deg)));
    $m = (float) str_replace(',', '.', (string)$min);
    $s = (float) str_replace(',', '.', (string)$sec);
    $dec = $d + ($m / 60.0) + ($s / 3600.0);
    return $negative ? -$dec : $dec;
}

/** Extrai todos os valores GMS rotulados por "long..." ou "lat...". */
function extractByLabel($text, $label) {
    $re = '/' . $label . '(?:itude)?\s*[:.]?\s*(-?\s*\d+)\s*°\s*(\d+)\s*\'\s*([\d.,]+)\s*"/iu';
    preg_match_all($re, $text, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $x) {
        $out[] = dmsToDecimal($x[1], $x[2], $x[3]);
    }
    return $out;
}

/**
 * Extrai coordenadas geográficas (lat/lng) do memorial.
 * Longitudes e latitudes são extraídas de forma independente e pareadas
 * por índice — robusto a variações de espaçamento e do conector.
 */
function extractGeoCoordinates($rawText) {
    $t = normalizeGeoText($rawText);
    $lons = extractByLabel($t, 'long');
    $lats = extractByLabel($t, 'lat');
    $n = min(count($lons), count($lats));
    $pts = [];
    for ($i = 0; $i < $n; $i++) {
        $lat = $lats[$i];
        $lng = $lons[$i];
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            $pts[] = [$lat, $lng];
        }
    }
    return ['pts' => $pts, 'lon_count' => count($lons), 'lat_count' => count($lats)];
}

/** Projeção geográfica -> UTM (Transversa de Mercator, SIRGAS2000/WGS84). */
function geoToUTM($lat, $lon, $zone = 23) {
    $a = 6378137.0; $f = 1 / 298.257223563; $k0 = 0.9996;
    $e2 = $f * (2 - $f); $ep2 = $e2 / (1 - $e2);
    $lon0 = deg2rad(($zone - 1) * 6 - 180 + 3);
    $latR = deg2rad($lat); $lonR = deg2rad($lon);
    $N = $a / sqrt(1 - $e2 * sin($latR) ** 2);
    $T = tan($latR) ** 2; $C = $ep2 * cos($latR) ** 2;
    $A = cos($latR) * ($lonR - $lon0);
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

/** Área do polígono em hectares (fórmula de Gauss sobre coordenadas UTM). */
function polygonAreaHa($pts) {
    $u = array_map(function ($p) { return geoToUTM($p[0], $p[1]); }, $pts);
    $a = 0; $k = count($u);
    for ($i = 0; $i < $k; $i++) {
        $j = ($i + 1) % $k;
        $a += $u[$i][0] * $u[$j][1] - $u[$j][0] * $u[$i][1];
    }
    return abs($a) / 2.0 / 10000.0;
}

function haversine($a, $b) {
    $R = 6378137; $d2r = M_PI / 180;
    $dLat = ($b[0] - $a[0]) * $d2r; $dLon = ($b[1] - $a[1]) * $d2r;
    $s = sin($dLat / 2) ** 2 + cos($a[0] * $d2r) * cos($b[0] * $d2r) * sin($dLon / 2) ** 2;
    return 2 * $R * asin(sqrt($s));
}

/** Perímetro do polígono fechado, em metros. */
function polygonPerimeterM($pts) {
    $p = 0; $k = count($pts);
    for ($i = 0; $i < $k; $i++) {
        $j = ($i + 1) % $k;
        $p += haversine($pts[$i], $pts[$j]);
    }
    return $p;
}

/** Núcleo: monta o pacote de dados a partir de uma lista de pontos [[lat,lng],...]. */
function buildGeoDataFromPoints($pts) {
    if (count($pts) < 3) {
        return ['ok' => false, 'num_vertices' => count($pts)];
    }
    $utm = array_map(function ($p) { return geoToUTM($p[0], $p[1]); }, $pts);
    $cenLat = array_sum(array_column($pts, 0)) / count($pts);
    $cenLng = array_sum(array_column($pts, 1)) / count($pts);

    $wgs84Str = implode(' ', array_map(function ($p) {
        return number_format($p[0], 8, '.', '') . ',' . number_format($p[1], 8, '.', '');
    }, $pts));
    $utmStr = implode(' ', array_map(function ($u) {
        return number_format($u[0], 2, '.', '') . ',' . number_format($u[1], 2, '.', '');
    }, $utm));

    return [
        'ok' => true,
        'pts' => $pts,                       // [[lat,lng], ...] para o Google Maps
        'num_vertices' => count($pts),
        'area_ha' => polygonAreaHa($pts),
        'perimetro_m' => polygonPerimeterM($pts),
        'centro_lat' => $cenLat,
        'centro_lng' => $cenLng,
        'coordenadas_wgs84' => $wgs84Str,
        'coordenadas_utm' => $utmStr,        // formato "east,north ..."
    ];
}

/** Converte um número no formato brasileiro (1.234.567,89) para float, tolerante a OCR. */
function brNumero($s) {
    $s = trim((string)$s);
    // separador decimal = o ÚLTIMO ',' ou '.' do número (tolera OCR que troca ',' por '.')
    $posC = strrpos($s, ',');
    $posD = strrpos($s, '.');
    $pos = max($posC === false ? -1 : $posC, $posD === false ? -1 : $posD);
    if ($pos >= 0) {
        $int = preg_replace('/\D/', '', substr($s, 0, $pos));
        $dec = preg_replace('/\D/', '', substr($s, $pos + 1));
        return (float)(($int === '' ? '0' : $int) . '.' . ($dec === '' ? '0' : $dec));
    }
    return (float)preg_replace('/[^\d]/', '', $s);
}

/** UTM (Transversa de Mercator) -> geográfica (lat/lon), SIRGAS2000/WGS84. */
function utmToGeo($east, $north, $zone = 23, $south = true) {
    $a = 6378137.0; $f = 1 / 298.257223563; $k0 = 0.9996;
    $e2 = $f * (2 - $f);
    $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));
    $x = $east - 500000.0;
    $y = $north - ($south ? 10000000.0 : 0.0);
    $lon0 = deg2rad(($zone - 1) * 6 - 180 + 3);
    $M = $y / $k0;
    $mu = $M / ($a * (1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256));
    $phi1 = $mu
        + (3 * $e1 / 2 - 27 * $e1 ** 3 / 32) * sin(2 * $mu)
        + (21 * $e1 ** 2 / 16 - 55 * $e1 ** 4 / 32) * sin(4 * $mu)
        + (151 * $e1 ** 3 / 96) * sin(6 * $mu)
        + (1097 * $e1 ** 4 / 512) * sin(8 * $mu);
    $ep2 = $e2 / (1 - $e2);
    $C1 = $ep2 * cos($phi1) ** 2;
    $T1 = tan($phi1) ** 2;
    $N1 = $a / sqrt(1 - $e2 * sin($phi1) ** 2);
    $R1 = $a * (1 - $e2) / pow(1 - $e2 * sin($phi1) ** 2, 1.5);
    $D = $x / ($N1 * $k0);
    $lat = $phi1 - ($N1 * tan($phi1) / $R1) * ($D ** 2 / 2
        - (5 + 3 * $T1 + 10 * $C1 - 4 * $C1 ** 2 - 9 * $ep2) * $D ** 4 / 24
        + (61 + 90 * $T1 + 298 * $C1 + 45 * $T1 ** 2 - 252 * $ep2 - 3 * $C1 ** 2) * $D ** 6 / 720);
    $lon = $lon0 + ($D
        - (1 + 2 * $T1 + $C1) * $D ** 3 / 6
        + (5 - 2 * $C1 + 28 * $T1 - 3 * $C1 ** 2 + 8 * $ep2 + 24 * $T1 ** 2) * $D ** 5 / 120) / cos($phi1);
    return [rad2deg($lat), rad2deg($lon)];
}

/**
 * Extrai coordenadas UTM (E/N em metros) do memorial e converte para lat/lng.
 *
 * IMPORTANTE: cada vértice é montado PAREANDO LOCALMENTE o N e o E que aparecem
 * juntos no texto ("N=... e E=..."), preservando a ORDEM do documento. A versão
 * antiga separava todos os Eastings num array e todos os Northings noutro e
 * pareava por índice — o que desalinhava o polígono inteiro sempre que a
 * quantidade de E e N capturados era diferente (ex.: o 1º vértice cuja Easting
 * vem SEM o "m" final: "E=433.093,50 referidas ao MC..."), gerando um polígono
 * cruzado/embaralhado. O pareamento local elimina esse problema.
 *
 * Os números são ancorados no rótulo MAIÚSCULO "N"/"E" (isolado, nunca parte de
 * palavras como EROMIR/SEU) com o "m" final OPCIONAL — assim distâncias
 * ("distância de 178,69m") e o "e" conectivo não são confundidos com coordenadas.
 * Classifica por grandeza: Easting ~6 dígitos, Northing ~7-8 dígitos.
 */
function extractUTMCoordinates($rawText, $zone = 23, $south = true) {
    $t = normalizeGeoText($rawText);
    // Tokens "N ..." / "E ..." na ordem do texto; "m" opcional; '=' opcional.
    $re = '/(?<![\p{L}])([NE])\s*=?\s*(\d{1,3}(?:\.\d{3})+(?:,\d+)?|\d+(?:[.,]\d+)?)\s*m?(?!\d)/u';
    preg_match_all($re, $t, $m, PREG_SET_ORDER);
    $tokens = [];
    foreach ($m as $x) {
        $val = brNumero($x[2]);
        $ip  = (int) floor(abs($val));
        if ($x[1] === 'N' && $ip >= 1000000 && $ip <= 99999999) $tokens[] = ['N', $val];
        elseif ($x[1] === 'E' && $ip >= 100000 && $ip <= 999999) $tokens[] = ['E', $val];
    }
    // Pareamento LOCAL: 1 N + 1 E consecutivos (em qualquer ordem) = 1 vértice.
    $pares = []; $pendN = null; $pendE = null; $ne = 0; $nn = 0;
    foreach ($tokens as $tk) {
        if ($tk[0] === 'N') { $pendN = $tk[1]; $nn++; }
        else { $pendE = $tk[1]; $ne++; }
        if ($pendN !== null && $pendE !== null) {
            $pares[] = [$pendN, $pendE]; // [north, east]
            $pendN = null; $pendE = null;
        }
    }
    // remove vértice final repetido (== primeiro), comum no fechamento do perímetro
    $k = count($pares);
    if ($k > 1
        && abs($pares[0][0] - $pares[$k-1][0]) < 0.05
        && abs($pares[0][1] - $pares[$k-1][1]) < 0.05) {
        array_pop($pares);
    }
    $pts = [];
    foreach ($pares as $p) {
        $g = utmToGeo($p[1], $p[0], $zone, $south); // utmToGeo(east, north)
        if ($g[0] >= -90 && $g[0] <= 90 && $g[1] >= -180 && $g[1] <= 180) $pts[] = [$g[0], $g[1]];
    }
    return ['pts' => $pts, 'e_count' => $ne, 'n_count' => $nn];
}

/**
 * Detecta segmentos de "azimute + distância" (memoriais antigos por rumos/distâncias).
 * Não georreferencia (faltam coordenadas de âncora); serve para identificar o tipo.
 */
function extractTraverseLegs($text) {
    $t = normalizeGeoText($text);
    $re = '/azimute\s*(?:de)?\s*(\d+)\s*°\s*(\d+)\s*\'\s*([\d.,]+)\s*"?[^0-9]{0,40}?dist[âa]ncia\s*(?:de)?\s*([\d.,]+)\s*m/isu';
    preg_match_all($re, $t, $m, PREG_SET_ORDER);
    $legs = [];
    foreach ($m as $x) {
        $legs[] = ['az' => dmsToDecimal($x[1], $x[2], $x[3]), 'dist' => brNumero($x[4])];
    }
    return $legs;
}

/** Mediana de uma lista de floats (robusta a outliers). */
function medianaFloat(array $arr) {
    $a = $arr; sort($a, SORT_NUMERIC); $n = count($a);
    if ($n === 0) return 0.0;
    $mid = intdiv($n, 2);
    return ($n % 2) ? (float)$a[$mid] : (((float)$a[$mid - 1] + (float)$a[$mid]) / 2.0);
}

/**
 * Reconcilia os vértices (lat/lng) com o caminhamento por AZIMUTE+DISTÂNCIA do
 * memorial. Muitos memoriais trazem, redundantemente, as coordenadas de cada
 * vértice E os azimutes/distâncias de cada lado. Quando a matrícula tem ERRO DE
 * DIGITAÇÃO numa coordenada (vértice trocado/duplicado), a coordenada escrita
 * fica incoerente com o caminhamento — que é a medição original do agrimensor e
 * fecha o polígono. Esta função:
 *   1) reconstrói o polígono a partir dos azimutes/distâncias (lados);
 *   2) ancora a reconstrução nos próprios vértices por MEDIANA das diferenças
 *      (robusta: ignora os vértices errados ao estimar a posição/translação);
 *   3) substitui APENAS os vértices cujo desvio passa de $tol (erros reais),
 *      mantendo intactos os vértices coerentes do documento.
 *
 * Só atua quando há um lado por vértice (legs == v ou v-1) e a MAIORIA dos
 * vértices é coerente com o caminhamento (evita "consertar" o que não deve).
 * Retorna ['pts'=>[[lat,lng]...], 'corrigidos'=>[índices 1-based], 'usou'=>bool].
 */
function reconcileTraverse(array $pts, array $legs, $zone = 23, $south = true, $tol = 5.0) {
    $v = count($pts); $nl = count($legs);
    if ($v < 3 || ($nl !== $v && $nl !== $v - 1)) return ['pts' => $pts, 'corrigidos' => [], 'usou' => false];

    // vértices escritos -> UTM [north, east]
    $U = [];
    foreach ($pts as $p) { $u = geoToUTM($p[0], $p[1], $zone); $U[] = [$u[1], $u[0]]; }

    // caminhamento relativo a partir de (0,0): azimute medido da NORTE da grade
    $rel = [[0.0, 0.0]];
    for ($i = 0; $i < $v - 1; $i++) {
        $az = deg2rad($legs[$i]['az']); $d = (float)$legs[$i]['dist'];
        $rel[] = [$rel[$i][0] + $d * cos($az), $rel[$i][1] + $d * sin($az)];
    }

    // âncora robusta: mediana de (escrito - relativo) em cada eixo
    $dN = []; $dE = [];
    for ($i = 0; $i < $v; $i++) { $dN[] = $U[$i][0] - $rel[$i][0]; $dE[] = $U[$i][1] - $rel[$i][1]; }
    $offN = medianaFloat($dN); $offE = medianaFloat($dE);

    // correção híbrida: mantém o vértice escrito quando coerente; senão reconstrói
    $corrig = []; $novoU = []; $inliers = 0;
    for ($i = 0; $i < $v; $i++) {
        $rN = $rel[$i][0] + $offN; $rE = $rel[$i][1] + $offE;
        $dev = hypot($U[$i][0] - $rN, $U[$i][1] - $rE);
        if ($dev <= $tol) { $novoU[] = $U[$i]; $inliers++; }
        else { $novoU[] = [$rN, $rE]; $corrig[] = $i + 1; }
    }

    if (empty($corrig) || $inliers < (int)ceil($v * 0.6)) {
        return ['pts' => $pts, 'corrigidos' => [], 'usou' => false];
    }

    $novoPts = [];
    foreach ($novoU as $u) { $g = utmToGeo($u[1], $u[0], $zone, $south); $novoPts[] = [$g[0], $g[1]]; }
    return ['pts' => $novoPts, 'corrigidos' => $corrig, 'usou' => true];
}

/** Monta o pacote a partir do texto de um memorial descritivo (GMS). */
function buildGeoData($memorial) {
    $res = extractGeoCoordinates($memorial);   // 1º GMS rotulado (Longitude:/Latitude:)
    $pts = $res['pts'];
    $fonte = 'gms';
    if (count($pts) < 3) {                       // 2º GMS sem rótulo (tabela SIGEF/INCRA)
        $tab = extractGeoCoordinatesTabela($memorial);
        if (count($tab['pts']) >= 3) { $pts = $tab['pts']; $fonte = 'gms_tabela'; $res = $tab; }
    }
    if (count($pts) < 3) {                        // 3º UTM (E/N em metros)
        $utm = extractUTMCoordinates($memorial);
        if (count($utm['pts']) >= 3) { $pts = $utm['pts']; $fonte = 'utm'; }
    }

    // Reconciliação por caminhamento: corrige vértices com coordenada incoerente
    // (erro de digitação no documento) usando os azimutes/distâncias do memorial.
    $corrigidos = [];
    if (count($pts) >= 3) {
        $legs = extractTraverseLegs($memorial);
        if (count($legs) >= 3) {
            $rec = reconcileTraverse($pts, $legs);
            if ($rec['usou']) { $pts = $rec['pts']; $corrigidos = $rec['corrigidos']; }
        }
    }

    $data = buildGeoDataFromPoints($pts);
    $data['lon_count'] = $res['lon_count'] ?? 0;
    $data['lat_count'] = $res['lat_count'] ?? 0;
    $data['fonte_coord'] = $fonte;
    $data['vertices_corrigidos'] = $corrigidos;
    if (!empty($corrigidos)) {
        $rotulos = implode(', ', array_map(function ($i) { return 'M' . str_pad($i, 2, '0', STR_PAD_LEFT); }, $corrigidos));
        $data['aviso_geometria'] = 'Atenção: ' . count($corrigidos) . ' vértice(s) com coordenada inconsistente no documento '
            . '(' . $rotulos . ') foram reconstruídos a partir dos azimutes/distâncias do próprio memorial. '
            . 'Confira o desenho antes de gravar.';
    }
    return $data;
}

/** Reconstrói o pacote a partir da string "lat,lng lat,lng ..." gravada no banco. */
function buildGeoDataFromWgs84($str) {
    $pts = [];
    foreach (preg_split('/\s+/', trim((string)$str)) as $par) {
        if ($par === '') continue;
        $xy = explode(',', $par);
        if (count($xy) >= 2 && is_numeric($xy[0]) && is_numeric($xy[1])) {
            $pts[] = [(float)$xy[0], (float)$xy[1]];
        }
    }
    return buildGeoDataFromPoints($pts);
}

/**
 * Lê um arquivo KML e devolve os placemarks: [['nome'=>..., 'pts'=>[[lat,lng],...]], ...].
 * KML usa a ordem "longitude,latitude,altitude" — invertida em relação ao mapa.
 */
function parseKml($kml) {
    $kml = normalizeGeoText($kml);
    $placemarks = [];

    // Captura cada <Placemark> ... </Placemark> (case-insensitive, multiline)
    if (preg_match_all('/<Placemark\b[^>]*>(.*?)<\/Placemark>/is', $kml, $pmMatches)) {
        foreach ($pmMatches[1] as $bloco) {
            // nome do placemark
            $nome = '';
            if (preg_match('/<name\b[^>]*>(.*?)<\/name>/is', $bloco, $nm)) {
                $nome = trim(html_entity_decode(strip_tags($nm[1]), ENT_QUOTES, 'UTF-8'));
            }
            // todos os blocos de <coordinates> dentro do placemark (anel externo)
            if (preg_match('/<coordinates\b[^>]*>(.*?)<\/coordinates>/is', $bloco, $cm)) {
                $pts = parseKmlCoordinates($cm[1]);
                if (count($pts) >= 3) {
                    $placemarks[] = ['nome' => $nome, 'pts' => $pts];
                }
            }
        }
    }

    // Fallback: KML sem <Placemark> explícito, só com <coordinates>
    if (empty($placemarks) && preg_match_all('/<coordinates\b[^>]*>(.*?)<\/coordinates>/is', $kml, $all)) {
        foreach ($all[1] as $i => $bloco) {
            $pts = parseKmlCoordinates($bloco);
            if (count($pts) >= 3) {
                $placemarks[] = ['nome' => 'Polígono ' . ($i + 1), 'pts' => $pts];
            }
        }
    }

    return $placemarks;
}

/** Converte o conteúdo de <coordinates> (lon,lat[,alt] separados por espaço) em [[lat,lng],...]. */
function parseKmlCoordinates($raw) {
    $pts = [];
    $tokens = preg_split('/\s+/', trim($raw));
    foreach ($tokens as $tok) {
        if ($tok === '') continue;
        $c = explode(',', $tok);
        if (count($c) < 2) continue;
        $lng = (float)$c[0];   // KML: longitude primeiro
        $lat = (float)$c[1];
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            $pts[] = [$lat, $lng];
        }
    }
    // remove o vértice de fechamento repetido (KML fecha o anel: 1º == último)
    if (count($pts) > 3) {
        $a = $pts[0]; $b = end($pts);
        if (abs($a[0] - $b[0]) < 1e-9 && abs($a[1] - $b[1]) < 1e-9) {
            array_pop($pts);
        }
    }
    return $pts;
}

/** Processa uma fonte (memorial ou kml) e devolve o pacote de geometria. */
function processarFonte($origem, $conteudo) {
    if ($origem === 'kml') {
        $pm = parseKml($conteudo);
        if (empty($pm)) return ['ok' => false, 'num_vertices' => 0];
        return buildGeoDataFromPoints($pm[0]['pts']);
    }
    return buildGeoData($conteudo);
}

/* ====================================================================
 *  BANCO DE DADOS
 * ==================================================================== */

function ensureTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS memoriais_mapeados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identificador VARCHAR(255) NOT NULL,
        tipo_identificador VARCHAR(20) NOT NULL DEFAULT 'nome',
        origem VARCHAR(20) NOT NULL DEFAULT 'memorial',
        imovel_id INT NULL,
        memorial_descritivo MEDIUMTEXT,
        num_vertices INT,
        area_ha DECIMAL(16,4),
        perimetro_m DECIMAL(16,2),
        centro_lat DECIMAL(12,8),
        centro_lng DECIMAL(12,8),
        coordenadas_wgs84 MEDIUMTEXT,
        coordenadas_utm MEDIUMTEXT,
        cor VARCHAR(20) NULL DEFAULT NULL,
        cor_opacidade DECIMAL(3,2) NULL DEFAULT NULL,
        numero_matricula VARCHAR(60) NULL DEFAULT NULL,
        proprietario TEXT NULL DEFAULT NULL,
        cpf TEXT NULL DEFAULT NULL,
        tipo_imovel VARCHAR(12) NULL DEFAULT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Compatibilidade: adiciona a coluna 'origem' se a tabela já existia sem ela
    $col = $conn->query("SHOW COLUMNS FROM memoriais_mapeados LIKE 'origem'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE memoriais_mapeados ADD COLUMN origem VARCHAR(20) NOT NULL DEFAULT 'memorial' AFTER tipo_identificador");
    }

    // Compatibilidade: coluna 'cor' (destaque do imóvel no mapa)
    $colCor = $conn->query("SHOW COLUMNS FROM memoriais_mapeados LIKE 'cor'");
    if ($colCor && $colCor->num_rows === 0) {
        $conn->query("ALTER TABLE memoriais_mapeados ADD COLUMN cor VARCHAR(20) NULL DEFAULT NULL");
    }

    // Compatibilidade: campos de cadastro do imóvel + intensidade da cor
    $novas = [
        'numero_matricula' => "ADD COLUMN numero_matricula VARCHAR(60) NULL DEFAULT NULL",
        'proprietario'     => "ADD COLUMN proprietario TEXT NULL DEFAULT NULL",
        'cpf'              => "ADD COLUMN cpf TEXT NULL DEFAULT NULL",
        'tipo_imovel'      => "ADD COLUMN tipo_imovel VARCHAR(12) NULL DEFAULT NULL",
        'cor_opacidade'    => "ADD COLUMN cor_opacidade DECIMAL(3,2) NULL DEFAULT NULL",
        // ---- Atributos do shapefile ONR (Mapa do Registro de Imóveis) ----
        'nome_imo'         => "ADD COLUMN nome_imo VARCHAR(180) NULL DEFAULT NULL",
        'dat_mat'          => "ADD COLUMN dat_mat VARCHAR(20) NULL DEFAULT NULL",
        'liv_mat'          => "ADD COLUMN liv_mat VARCHAR(20) NULL DEFAULT NULL",
        'fol_mat'          => "ADD COLUMN fol_mat VARCHAR(20) NULL DEFAULT NULL",
        'transcri'         => "ADD COLUMN transcri VARCHAR(60) NULL DEFAULT NULL",
        'cnm'              => "ADD COLUMN cnm VARCHAR(40) NULL DEFAULT NULL",
        'cns'              => "ADD COLUMN cns VARCHAR(20) NULL DEFAULT NULL",
        'endereco'         => "ADD COLUMN endereco VARCHAR(255) NULL DEFAULT NULL",
        'numero_imovel'    => "ADD COLUMN numero_imovel VARCHAR(30) NULL DEFAULT NULL",
        'cep'              => "ADD COLUMN cep VARCHAR(12) NULL DEFAULT NULL",
        'municipio'        => "ADD COLUMN municipio VARCHAR(120) NULL DEFAULT NULL",
        'uf'               => "ADD COLUMN uf VARCHAR(2) NULL DEFAULT NULL",
        'conf_mat'         => "ADD COLUMN conf_mat VARCHAR(255) NULL DEFAULT NULL",
        'conf_nom'         => "ADD COLUMN conf_nom VARCHAR(255) NULL DEFAULT NULL",
        'rel_jur'          => "ADD COLUMN rel_jur VARCHAR(60) NULL DEFAULT NULL",
        'dat_ini'          => "ADD COLUMN dat_ini VARCHAR(20) NULL DEFAULT NULL",
        'dat_fim'          => "ADD COLUMN dat_fim VARCHAR(20) NULL DEFAULT NULL",
        'per_rel'          => "ADD COLUMN per_rel VARCHAR(20) NULL DEFAULT NULL",
        'ccir_sncr'        => "ADD COLUMN ccir_sncr VARCHAR(40) NULL DEFAULT NULL",
        'sigef'            => "ADD COLUMN sigef VARCHAR(60) NULL DEFAULT NULL",
        'snci'             => "ADD COLUMN snci VARCHAR(40) NULL DEFAULT NULL",
        'cib_nirf'         => "ADD COLUMN cib_nirf VARCHAR(40) NULL DEFAULT NULL",
        'itbi'             => "ADD COLUMN itbi VARCHAR(30) NULL DEFAULT NULL",
        'car'              => "ADD COLUMN car VARCHAR(80) NULL DEFAULT NULL",
        'rip'              => "ADD COLUMN rip VARCHAR(30) NULL DEFAULT NULL",
        'cif'              => "ADD COLUMN cif VARCHAR(30) NULL DEFAULT NULL",
        'classifica'       => "ADD COLUMN classifica VARCHAR(2) NULL DEFAULT NULL",
        // ---- Metadados da importação ONR ----
        'onr_nivel_publicidade' => "ADD COLUMN onr_nivel_publicidade VARCHAR(2) NULL DEFAULT NULL",
        'onr_classificacao'     => "ADD COLUMN onr_classificacao VARCHAR(3) NULL DEFAULT NULL",
        'onr_numero_prenotacao' => "ADD COLUMN onr_numero_prenotacao VARCHAR(60) NULL DEFAULT NULL",
        'onr_descricao'         => "ADD COLUMN onr_descricao VARCHAR(500) NULL DEFAULT NULL",
        'onr_importation_id'    => "ADD COLUMN onr_importation_id VARCHAR(80) NULL DEFAULT NULL",
        'onr_status'            => "ADD COLUMN onr_status VARCHAR(60) NULL DEFAULT NULL",
        'onr_enviado_em'        => "ADD COLUMN onr_enviado_em DATETIME NULL DEFAULT NULL",
        // ---- Ciclo de vida da matrícula (encerramento) ----
        'situacao'             => "ADD COLUMN situacao VARCHAR(20) NOT NULL DEFAULT 'ativa'",
        'motivo_situacao'      => "ADD COLUMN motivo_situacao VARCHAR(20) NULL DEFAULT NULL",
        'matricula_sucessora'  => "ADD COLUMN matricula_sucessora VARCHAR(120) NULL DEFAULT NULL",
        // Imóvel detectado FORA do perímetro do município (guarda o município real). Bloqueia envio ONR/ITN.
        'fora_municipio'       => "ADD COLUMN fora_municipio VARCHAR(120) NULL DEFAULT NULL",
        // Contexto da carga ITN 03 para imóvel RURAL: '1' padrão, '2' Imóvel da União, '3' Estrangeiros. Vazio = autodetectar.
        'contexto_rural'       => "ADD COLUMN contexto_rural VARCHAR(1) NULL DEFAULT NULL",
        // Matrícula cadastrada SÓ para a carga ITN 03 (sem coordenadas/mapeamento).
        'itn03_exclusivo'      => "ADD COLUMN itn03_exclusivo TINYINT(1) NOT NULL DEFAULT 0",
        // Qualificação estruturada dos titulares ATUAIS (JSON), extraída dos registros/averbações.
        // Usada pela carga ITN 03 (dados_pessoa) e mantém compat. com as colunas proprietario/cpf.
        'qualificacao_json'    => "ADD COLUMN qualificacao_json MEDIUMTEXT NULL DEFAULT NULL",
        // Inconsistências detectadas na importação (JSON: [{sev,msg}, ...]). Imóvel é cadastrado mesmo assim.
        'inconsistencias'      => "ADD COLUMN inconsistencias MEDIUMTEXT NULL DEFAULT NULL",
    ];
    foreach ($novas as $colNome => $ddl) {
        $c = $conn->query("SHOW COLUMNS FROM memoriais_mapeados LIKE '" . $conn->real_escape_string($colNome) . "'");
        if ($c && $c->num_rows === 0) { $conn->query("ALTER TABLE memoriais_mapeados " . $ddl); }
    }
    // Colunas que podem conter MUITOS valores separados por vírgula e truncavam:
    //  - matricula_sucessora: intervalos de desmembramento expandidos (ex.: 745-900 => 156 números)
    //  - proprietario / cpf: vários titulares (VARCHAR(180)/VARCHAR(20) cortavam o 2º em diante)
    // Promove todas para TEXT (idempotente).
    foreach (['matricula_sucessora', 'proprietario', 'cpf'] as $colMulti) {
        $c = $conn->query("SHOW COLUMNS FROM memoriais_mapeados LIKE '" . $conn->real_escape_string($colMulti) . "'");
        if ($c && ($col = $c->fetch_assoc()) && stripos($col['Type'], 'text') === false) {
            $conn->query("ALTER TABLE memoriais_mapeados MODIFY $colMulti TEXT NULL DEFAULT NULL");
        }
    }

    // ---- Anexos do imóvel (PDF da matrícula, PDF do SIGEF, KML, outros) ----
    $conn->query("CREATE TABLE IF NOT EXISTS memoriais_anexos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        memorial_id INT NOT NULL,
        tipo VARCHAR(20) NOT NULL DEFAULT 'outro',
        nome_original VARCHAR(255) NOT NULL,
        arquivo VARCHAR(255) NOT NULL,
        mime VARCHAR(120) NULL DEFAULT NULL,
        tamanho INT NULL DEFAULT NULL,
        hash CHAR(40) NULL DEFAULT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_anexo_memorial (memorial_id),
        INDEX idx_anexo_hash (memorial_id, hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ====================================================================
 *  ANEXOS DO IMÓVEL (armazenamento em disco + registro no banco)
 * ==================================================================== */
/* Diretório de anexos (criado sob demanda, com proteção contra acesso direto). */
function anexosDir() {
    $dir = __DIR__ . '/anexos';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    // Defesa em profundidade: bloqueia acesso direto via Apache (o download passa pelo index.php).
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    $idx = $dir . '/index.html';
    if (!is_file($idx)) @file_put_contents($idx, '');
    return $dir;
}
/* Classifica o tipo do anexo a partir do nome/conteúdo. */
function anexoTipo($nomeOriginal, $mime = '', $dadosExtraidos = null) {
    $ext = strtolower(pathinfo((string)$nomeOriginal, PATHINFO_EXTENSION));
    if ($ext === 'kml' || stripos((string)$mime, 'kml') !== false) return 'kml';
    if ($ext === 'pdf' || stripos((string)$mime, 'pdf') !== false) {
        $n = strtolower((string)$nomeOriginal);
        $temSigef = (is_array($dadosExtraidos) && trim((string)($dadosExtraidos['sigef'] ?? '')) !== '');
        // UUID no nome (ex.: download do SIGEF) ou menção a SIGEF/INCRA => PDF do SIGEF
        if (strpos($n, 'sigef') !== false || strpos($n, 'incra') !== false
            || preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $n)
            || $temSigef && strpos($n, 'matricula') === false) {
            return 'pdf_sigef';
        }
        return 'pdf_matricula';
    }
    return 'outro';
}
/* Persiste bytes como anexo do imóvel. Deduplica por (memorial_id, hash). Retorna id ou null. */
function anexoSalvarBytes($conn, $memorialId, $bytes, $nomeOriginal, $tipo = 'outro', $mime = null) {
    $memorialId = (int)$memorialId;
    if ($memorialId <= 0 || $bytes === '' || $bytes === false) return null;
    $hash = sha1($bytes);
    // já existe esse arquivo neste imóvel? então não duplica.
    $st = $conn->prepare("SELECT id FROM memoriais_anexos WHERE memorial_id = ? AND hash = ? LIMIT 1");
    if ($st) { $st->bind_param('is', $memorialId, $hash); $st->execute(); $rs = $st->get_result();
        if ($rs && ($row = $rs->fetch_assoc())) return (int)$row['id']; }
    $dir = anexosDir();
    $ext = strtolower(pathinfo((string)$nomeOriginal, PATHINFO_EXTENSION)); if ($ext === '' || strlen($ext) > 5) $ext = 'bin';
    $arquivo = $memorialId . '_' . date('YmdHis') . '_' . substr($hash, 0, 8) . '.' . $ext;
    if (@file_put_contents($dir . '/' . $arquivo, $bytes) === false) return null;
    $tam = strlen($bytes);
    $nomeOriginal = mb_substr((string)$nomeOriginal, 0, 250);
    $agora = date('Y-m-d H:i:s'); // usa o fuso do PHP (America/Fortaleza), não o do servidor MySQL
    $st = $conn->prepare("INSERT INTO memoriais_anexos (memorial_id, tipo, nome_original, arquivo, mime, tamanho, hash, criado_em) VALUES (?,?,?,?,?,?,?,?)");
    if (!$st) { @unlink($dir . '/' . $arquivo); return null; }
    $st->bind_param('issssiss', $memorialId, $tipo, $nomeOriginal, $arquivo, $mime, $tam, $hash, $agora);
    if (!$st->execute()) { @unlink($dir . '/' . $arquivo); return null; }
    return (int)$st->insert_id;
}
/* Lista anexos de um imóvel (mais recentes primeiro). */
function anexosListar($conn, $memorialId) {
    $memorialId = (int)$memorialId; $out = [];
    $st = $conn->prepare("SELECT id, tipo, nome_original, arquivo, mime, tamanho, criado_em FROM memoriais_anexos WHERE memorial_id = ? ORDER BY id DESC");
    if (!$st) return $out;
    $st->bind_param('i', $memorialId); $st->execute(); $rs = $st->get_result();
    while ($rs && ($row = $rs->fetch_assoc())) $out[] = $row;
    return $out;
}
/* Obtém um anexo pelo id. */
function anexoObter($conn, $anexoId) {
    $anexoId = (int)$anexoId;
    $st = $conn->prepare("SELECT * FROM memoriais_anexos WHERE id = ? LIMIT 1");
    if (!$st) return null;
    $st->bind_param('i', $anexoId); $st->execute(); $rs = $st->get_result();
    return ($rs && ($row = $rs->fetch_assoc())) ? $row : null;
}
/* Exclui um anexo (registro + arquivo em disco). */
function anexoExcluir($conn, $anexoId) {
    $a = anexoObter($conn, $anexoId); if (!$a) return false;
    @unlink(anexosDir() . '/' . $a['arquivo']);
    $st = $conn->prepare("DELETE FROM memoriais_anexos WHERE id = ?");
    if (!$st) return false; $id = (int)$anexoId; $st->bind_param('i', $id); return $st->execute();
}
/* Rótulo amigável do tipo de anexo. */
function anexoTipoRotulo($tipo) {
    return [
        'pdf_matricula' => 'PDF da matrícula',
        'pdf_sigef'     => 'PDF do SIGEF',
        'kml'           => 'KML',
        'outro'         => 'Anexo',
    ][$tipo] ?? 'Anexo';
}

/* ====================================================================
 *  INCONSISTÊNCIAS DE IMPORTAÇÃO
 *  O imóvel é SEMPRE cadastrado; as inconsistências são apenas sinalizadas
 *  (JSON [{sev:'erro'|'alerta'|'info', msg:'...'}]) para conferência/relatório.
 * ==================================================================== */
function inconsCoordForaBrasil($pts) {
    foreach ($pts as $p) { $la = $p[0]; $lo = $p[1];
        if ($la < -34 || $la > 6 || $lo < -74 || $lo > -33) return true; }
    return false;
}
function inconsSegCruza($p1, $p2, $p3, $p4) {
    $d = function ($a, $b, $c) { return ($b[0]-$a[0])*($c[1]-$a[1]) - ($b[1]-$a[1])*($c[0]-$a[0]); };
    $d1=$d($p3,$p4,$p1); $d2=$d($p3,$p4,$p2); $d3=$d($p1,$p2,$p3); $d4=$d($p1,$p2,$p4);
    return ((($d1>0&&$d2<0)||($d1<0&&$d2>0)) && (($d3>0&&$d4<0)||($d3<0&&$d4>0)));
}
function inconsAutoIntersecta($pts) {
    $n = count($pts); if ($n < 4) return false;
    for ($i = 0; $i < $n-1; $i++) {
        for ($j = $i+1; $j < $n-1; $j++) {
            if ($j === $i || $j === $i+1 || ($i === 0 && $j === $n-2)) continue; // lados adjacentes
            if (inconsSegCruza($pts[$i], $pts[$i+1], $pts[$j], $pts[$j+1])) return true;
        }
    }
    return false;
}
/* Analisa o nome do placemark do KML. Retorna [nomeLimpo, [inconsistências]]. */
function inconsNomeKml($nomeBruto) {
    $orig = trim((string)$nomeBruto); $inc = []; $nome = $orig;
    if (preg_match('#[\\\\/]#', $nome) || preg_match('#^[A-Za-z]:#', $nome)) {
        $base = preg_replace('#^.*[\\\\/]#', '', $nome);
        $base = preg_replace('#\.kml$#i', '', $base);
        $inc[] = ['sev' => 'alerta', 'msg' => 'O nome do placemark era um caminho de arquivo ("' . $orig . '") — usado apenas o nome final.'];
        $nome = trim($base);
    }
    $low = function_exists('mb_strtolower') ? mb_strtolower($nome, 'UTF-8') : strtolower($nome);
    $low = strtr($low, ['ã'=>'a','á'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
    foreach (['nao exato','aproximad','rascunho','provisori','estimad','nao georref','sem georref'] as $m) {
        if (strpos($low, $m) !== false) { $inc[] = ['sev' => 'alerta', 'msg' => 'O nome indica geometria aproximada/inexata ("' . $orig . '") — a poligonal pode não corresponder ao perímetro exato.']; break; }
    }
    $limpo = trim(preg_replace('/[-_\s]*(n[ãa]o\s*exat[oa]|aproximad[oa]|rascunho|provis[óo]ri[oa]|estimad[oa]|sem\s*georref\w*|n[ãa]o\s*georref\w*).*$/iu', '', $nome));
    if ($limpo !== '') $nome = $limpo;
    return [trim($nome), $inc];
}
/* Formata distância em m/km no padrão pt-BR. */
function inconsFmtDist($m) {
    return $m >= 1000 ? number_format($m / 1000, 2, ',', '.') . ' km'
                      : number_format($m, $m < 10 ? 1 : 0, ',', '.') . ' m';
}
/* Checagens geométricas detalhadas (KML e PDF): lados desproporcionais, vértices
   duplicados, área atípica, coordenadas fora do Brasil e autointerseção. */
function detectarInconsistenciasGeo($geo, $origem = '') {
    $inc = [];
    $nv = (int)($geo['num_vertices'] ?? 0);
    if ($nv > 0 && $nv < 4) $inc[] = ['sev' => 'erro', 'msg' => 'Polígono com poucos vértices (' . $nv . ') — o mínimo para uma poligonal fechada é 4.'];

    $pts = [];
    foreach (preg_split('/\s+/', trim((string)($geo['coordenadas_wgs84'] ?? ''))) as $par) {
        if ($par === '') continue; $xy = explode(',', $par); if (count($xy) >= 2) $pts[] = [(float)$xy[0], (float)$xy[1]];
    }
    $n = count($pts);

    // Área
    $area = (float)($geo['area_ha'] ?? 0);
    if ($n >= 3 && $area <= 0.0001) {
        $inc[] = ['sev' => 'erro', 'msg' => 'Área praticamente nula — geometria possivelmente degenerada.'];
    } elseif ($area > 50000) {
        $inc[] = ['sev' => 'erro', 'msg' => 'Área de ~' . number_format($area, 0, ',', '.') . ' ha — muito acima do esperado; provável erro de escala/datum.'];
    } elseif ($area > 5000) {
        $inc[] = ['sev' => 'alerta', 'msg' => 'Área de ~' . number_format($area, 0, ',', '.') . ' ha — atípica para um único imóvel (confira a poligonal/escala).'];
    }

    if ($n >= 3) {
        if (inconsCoordForaBrasil($pts)) $inc[] = ['sev' => 'erro', 'msg' => 'Há vértices fora dos limites aproximados do Brasil — verifique o datum/fuso das coordenadas.'];

        // Comprimento de cada lado (inclui o lado de fechamento Vn→V1)
        $sides = []; for ($i = 0; $i < $n; $i++) { $sides[] = haversine($pts[$i], $pts[($i + 1) % $n]); }
        $ord = $sides; sort($ord); $med = $ord[intdiv($n, 2)]; if ($med <= 0) $med = array_sum($sides) / max(1, $n);

        // Vértices consecutivos quase coincidentes (duplicados/redundantes)
        for ($i = 0; $i < $n; $i++) {
            if ($sides[$i] < 2.0) {
                $a = $i + 1; $b = (($i + 1) % $n) + 1;
                $inc[] = ['sev' => 'alerta', 'msg' => 'V' . $a . ' e V' . $b . ' a ~' . inconsFmtDist($sides[$i]) . ' de distância (vértice duplicado/redundante).'];
            }
        }
        // Lados desproporcionais (≥ 3× a mediana e > 1 km) — possível vértice ausente/fora de ordem
        $outliers = [];
        for ($i = 0; $i < $n; $i++) { if ($med > 0 && $sides[$i] >= 3 * $med && $sides[$i] > 1000) $outliers[] = $i; }
        usort($outliers, function ($x, $y) use ($sides) { return $sides[$y] <=> $sides[$x]; });
        foreach (array_slice($outliers, 0, 3) as $i) {
            $a = $i + 1; $b = (($i + 1) % $n) + 1;
            $rot = ($i === $n - 1) ? ('Lado de fechamento V' . $a . '→V1') : ('Lado V' . $a . '→V' . $b);
            $inc[] = ['sev' => 'alerta', 'msg' => $rot . ' ≈ ' . inconsFmtDist($sides[$i]) . ' — muito maior que os demais (mediana ≈ ' . inconsFmtDist($med) . '). Possível vértice ausente ou fora de ordem.'];
        }
        // Autointerseção
        if (inconsAutoIntersecta($pts)) $inc[] = ['sev' => 'alerta', 'msg' => 'A poligonal parece se autointersectar (lados se cruzam) — confira a ordem dos vértices.'];
    }
    return $inc;
}
/* Inconsistências específicas de uma matrícula lida por IA (PDF). */
function detectarInconsistenciasPdf($d, $geo = null) {
    $inc = [];
    $cnm = trim((string)($d['cnm'] ?? ''));
    if (!preg_match('#^(?:\d{6}\.\d\.\d{7}-\d{2}|\d{16})$#', $cnm)) $inc[] = ['sev' => 'alerta', 'msg' => 'CNM ausente ou em formato inválido — é necessário para a carga ITN 03.'];
    if (trim((string)($d['municipio'] ?? '')) === '') $inc[] = ['sev' => 'alerta', 'msg' => 'Município não identificado no documento.'];
    if (trim((string)($d['uf'] ?? '')) === '')        $inc[] = ['sev' => 'alerta', 'msg' => 'UF não identificada no documento.'];
    if (trim((string)($d['proprietario'] ?? '')) === '') $inc[] = ['sev' => 'alerta', 'msg' => 'Proprietário(s) atual(is) não identificado(s) na cadeia de registros/averbações.'];
    if (is_array($geo)) {
        if (!empty($geo['aviso_geometria'])) $inc[] = ['sev' => 'alerta', 'msg' => 'Geometria: ' . $geo['aviso_geometria']];
        $vc = $geo['vertices_corrigidos'] ?? [];
        if (is_array($vc) && count($vc)) $inc[] = ['sev' => 'alerta', 'msg' => count($vc) . ' vértice(s) corrigido(s) na leitura automática — confira a poligonal no mapa.'];
        $inc = array_merge($inc, detectarInconsistenciasGeo($geo, 'pdf'));
    }
    return $inc;
}
/* Grava (ou limpa) a lista de inconsistências de um imóvel. */
function inconsGravar($conn, $id, array $inc) {
    $id = (int)$id; if ($id <= 0) return;
    $json = $inc ? json_encode(array_values($inc), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $st = $conn->prepare("UPDATE memoriais_mapeados SET inconsistencias = ? WHERE id = ?");
    if ($st) { $st->bind_param('si', $json, $id); $st->execute(); }
}

/**
 * Lista canônica dos campos ONR (coluna no banco => rótulo) e o agrupamento da UI.
 * Fonte única usada pelo formulário, pelo salvamento e (futuramente) pela geração do shapefile.
 */
function onrGrupos() {
    return [
        'Matrícula e registro' => [
            'nome_imo'  => ['Nome do imóvel', 'text', 'Ex.: Fazenda Santa Rita'],
            'dat_mat'   => ['Data da matrícula', 'text', 'dd/mm/aaaa'],
            'liv_mat'   => ['Livro', 'text', 'Ex.: 2'],
            'fol_mat'   => ['Folha', 'text', 'Ex.: 103'],
            'transcri'  => ['Transcrição', 'text', 'Ex.: TR987654'],
            'cnm'       => ['CNM (Cód. Nacional Matrícula)', 'text', '000000.0.0000000-00'],
            'cns'       => ['CNS (Cód. Nacional Serventia)', 'text', 'Ex.: 000000'],
        ],
        'Localização' => [
            'endereco'      => ['Endereço', 'text', 'Ex.: Rodovia BR 000, km 00'],
            'numero_imovel' => ['Número', 'text', 'Ex.: 123'],
            'cep'           => ['CEP', 'text', '00000000'],
            'municipio'     => ['Município', 'text', 'Ex.: Zé Doca'],
            'uf'            => ['UF', 'text', 'Ex.: MA'],
        ],
        'Proprietário e relação jurídica' => [
            'rel_jur'  => ['Relação jurídica', 'text', 'Ex.: propriedade, usufruto, promessa c&v'],
            'dat_ini'  => ['Início da relação', 'text', 'dd/mm/aaaa'],
            'dat_fim'  => ['Fim da relação', 'text', 'dd/mm/aaaa'],
            'per_rel'  => ['Percentual', 'text', 'Ex.: 33,33%'],
        ],
        'Confrontações' => [
            'conf_mat' => ['Matrículas confrontantes', 'text', 'Ex.: 10001,10010,210001'],
            'conf_nom' => ['Nomes dos confrontantes', 'text', 'Fulano, Sicrano, Beltrano'],
        ],
        'Cadastros externos' => [
            'ccir_sncr' => ['CCIR / SNCR', 'text', '000.000.000.000-0'],
            'sigef'     => ['SIGEF', 'text', ''],
            'snci'      => ['SNCI', 'text', ''],
            'cib_nirf'  => ['CIB / NIRF', 'text', ''],
            'car'       => ['CAR', 'text', 'SP-0000000-...'],
            'rip'       => ['RIP', 'text', ''],
            'cif'       => ['CIF', 'text', ''],
            'itbi'      => ['Valor do ITBI', 'text', 'Ex.: 5.000,00'],
        ],
    ];
}
/** Lista achatada das colunas ONR editáveis pelo usuário (sem metadados de envio). */
function onrCampos() {
    $cols = [];
    foreach (onrGrupos() as $campos) { foreach ($campos as $col => $_) { $cols[] = $col; } }
    // metadados da importação também são salvos pelo mesmo mecanismo
    return array_merge($cols, ['classifica', 'onr_nivel_publicidade', 'onr_classificacao', 'onr_numero_prenotacao', 'onr_descricao']);
}
/** Salva (UPDATE) os campos ONR de um imóvel a partir de um array associativo (ex.: $_POST). */
function salvarCamposOnr($conn, $id, $src) {
    $id = (int)$id; if ($id <= 0) return false;
    $sets = []; $vals = []; $types = '';
    foreach (onrCampos() as $col) {
        if (!array_key_exists($col, $src)) continue;
        $v = trim((string)$src[$col]);
        $sets[] = "`$col` = ?";
        $vals[] = ($v === '') ? null : $v;
        $types .= 's';
    }
    if (empty($sets)) return false;
    $vals[] = $id; $types .= 'i';
    $stmt = $conn->prepare("UPDATE memoriais_mapeados SET " . implode(', ', $sets) . " WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$vals);
    return $stmt->execute();
}

/* ===================== CONFIGURAÇÃO DA API ONR ===================== */
function onrConfigPath() { return __DIR__ . '/config_onr.json'; }
function onrConfigLer() {
    $p = onrConfigPath();
    $base = ['base_url' => 'https://mapa.onr.org.br/', 'token' => ''];
    if (is_file($p)) {
        $d = json_decode((string)@file_get_contents($p), true);
        if (is_array($d)) $base = array_merge($base, $d);
    }
    // Corrige host legado sem registro DNS (www.mapa.onr.org.br -> mapa.onr.org.br)
    if (!empty($base['base_url'])) $base['base_url'] = preg_replace('#^(https?://)www\.mapa\.onr\.org\.br#i', '$1mapa.onr.org.br', $base['base_url']);
    if ($base['base_url'] !== '' && substr($base['base_url'], -1) !== '/') $base['base_url'] .= '/';
    return $base;
}
function onrConfigSalvar($base_url, $token) {
    $base_url = trim($base_url); if ($base_url === '') $base_url = 'https://mapa.onr.org.br/';
    if (substr($base_url, -1) !== '/') $base_url .= '/';
    $data = ['base_url' => $base_url, 'token' => trim($token)];
    return @file_put_contents(onrConfigPath(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
}

/* ===================== GERADOR DE SHAPEFILE (PHP puro) ===================== */
function onrNumBr($s) {
    $s = trim((string)$s);
    if ($s === '') return 0.0;
    $s = preg_replace('/[^0-9,.\-]/', '', $s);          // remove R$, %, espaços, etc.
    if (strpos($s, ',') !== false) {                     // formato BR: 1.234,56
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float)$s : 0.0;
}
// Definição das colunas .dbf na ordem oficial: [NOME_DBF, tipo C/N, tamanho, decimais, origem]
function onrCamposDbf() {
    return [
        ['MATRICULA','C',20,0,'numero_matricula'],
        ['DAT_MAT','C',12,0,'dat_mat'],
        ['LIV_MAT','N',10,0,'liv_mat'],
        ['FOL_MAT','N',10,0,'fol_mat'],
        ['TRANSCRI','C',30,0,'transcri'],
        ['CNM','C',30,0,'cnm'],
        ['CNS','C',20,0,'cns'],
        ['ENDERECO','C',120,0,'endereco'],
        ['NUMERO','C',15,0,'numero_imovel'],
        ['CEP','N',10,0,'cep'],
        ['MUNICIPIO','C',60,0,'municipio'],
        ['UF','C',2,0,'uf'],
        ['NOME_PROP','C',150,0,'proprietario'],
        ['CPF_CNPJ','C',60,0,'cpf'],
        ['CONF_MAT','C',150,0,'conf_mat'],
        ['CONF_NOM','C',150,0,'conf_nom'],
        ['REL_JUR','C',40,0,'rel_jur'],
        ['DAT_INI','C',12,0,'dat_ini'],
        ['DAT_FIM','C',12,0,'dat_fim'],
        ['PER_REL','N',12,2,'per_rel'],
        ['NOME_IMO','C',80,0,'__nome_imo'],
        ['AREA_HA','N',18,4,'__area_ha'],
        ['AREA_M2','N',18,2,'__area_m2'],
        ['PERIM_M','N',16,2,'__perim_m'],
        ['PERIM_KM','N',15,3,'__perim_km'],
        ['CCIR_SNCR','C',30,0,'ccir_sncr'],
        ['SIGEF','C',60,0,'sigef'],
        ['SNCI','C',40,0,'snci'],
        ['CIB_NIRF','C',40,0,'cib_nirf'],
        ['ITBI','N',15,2,'itbi'],
        ['CAR','C',80,0,'car'],
        ['RIP','N',18,0,'rip'],
        ['CIF','N',18,0,'cif'],
        ['CLASSIFICA','N',2,0,'classifica'],
    ];
}
// Valor de um campo a partir do registro (com campos computados __*)
function onrValorCampo($origem, $row) {
    switch ($origem) {
        case '__nome_imo':  return ($row['nome_imo'] ?? '') !== '' ? $row['nome_imo'] : ($row['identificador'] ?? '');
        case '__area_ha':   return (float)($row['area_ha'] ?? 0);
        case '__area_m2':   return (float)($row['area_ha'] ?? 0) * 10000.0;
        case '__perim_m':   return (float)($row['perimetro_m'] ?? 0);
        case '__perim_km':  return (float)($row['perimetro_m'] ?? 0) / 1000.0;
        default:            return $row[$origem] ?? '';
    }
}
// Gera os 4 arquivos do shapefile em $dir com nome base $base. Retorna lista de caminhos ou false.
function onrGerarShapefile($conn, $id, $dir, $base) {
    $id = (int)$id;
    $res = $conn->query("SELECT * FROM memoriais_mapeados WHERE id = $id LIMIT 1");
    if (!$res || !($row = $res->fetch_assoc())) return false;

    // pontos: "lat,lng lat,lng ..."  -> shapefile usa X=lng, Y=lat
    $pts = [];
    foreach (preg_split('/\s+/', trim((string)$row['coordenadas_wgs84'])) as $par) {
        if ($par === '') continue;
        $xy = explode(',', $par);
        if (count($xy) >= 2) $pts[] = [(float)$xy[1], (float)$xy[0]]; // [X=lng, Y=lat]
    }
    if (count($pts) < 3) return false;
    // fecha o anel
    if ($pts[0][0] !== $pts[count($pts)-1][0] || $pts[0][1] !== $pts[count($pts)-1][1]) $pts[] = $pts[0];
    // garante orientação horária (anel externo do shapefile)
    $area2 = 0.0;
    for ($i = 0; $i < count($pts)-1; $i++) { $area2 += ($pts[$i][0]*$pts[$i+1][1] - $pts[$i+1][0]*$pts[$i][1]); }
    if ($area2 > 0) $pts = array_reverse($pts); // estava anti-horário -> inverte

    $xs = array_column($pts, 0); $ys = array_column($pts, 1);
    $xmin=min($xs); $xmax=max($xs); $ymin=min($ys); $ymax=max($ys);
    $n = count($pts);

    // ---- conteúdo do registro .shp (Polygon = 5) ----
    $rec  = pack('V', 5);
    $rec .= pack('e', $xmin).pack('e', $ymin).pack('e', $xmax).pack('e', $ymax);
    $rec .= pack('V', 1);          // numParts
    $rec .= pack('V', $n);         // numPoints
    $rec .= pack('V', 0);          // part 0 começa no índice 0
    foreach ($pts as $p) { $rec .= pack('e', $p[0]).pack('e', $p[1]); }
    $recLenWords = strlen($rec) / 2;

    // ---- .shp ----
    $shpHeaderLenWords = (100 + 8 + strlen($rec)) / 2;
    $hdr  = pack('N', 9994).str_repeat(pack('N', 0), 5);
    $shp  = $hdr.pack('N', (int)$shpHeaderLenWords).pack('V', 1000).pack('V', 5);
    $shp .= pack('e', $xmin).pack('e', $ymin).pack('e', $xmax).pack('e', $ymax);
    $shp .= str_repeat(pack('e', 0), 4); // z e m ranges
    $shp .= pack('N', 1).pack('N', (int)$recLenWords); // record header (num=1)
    $shp .= $rec;

    // ---- .shx ----
    $shxHeaderLenWords = (100 + 8) / 2;
    $shx  = $hdr.pack('N', (int)$shxHeaderLenWords).pack('V', 1000).pack('V', 5);
    $shx .= pack('e', $xmin).pack('e', $ymin).pack('e', $xmax).pack('e', $ymax);
    $shx .= str_repeat(pack('e', 0), 4);
    $shx .= pack('N', 50).pack('N', (int)$recLenWords); // offset 50 words (byte 100)

    // ---- .dbf ----
    $campos = onrCamposDbf();
    $recordLen = 1; foreach ($campos as $c) { $recordLen += $c[2]; }
    $headerLen = 32 + count($campos)*32 + 1;
    $dbf  = pack('C', 0x03);
    $dbf .= pack('C', (int)date('y')).pack('C', (int)date('n')).pack('C', (int)date('j'));
    $dbf .= pack('V', 1);                 // 1 registro
    $dbf .= pack('v', $headerLen).pack('v', $recordLen);
    $dbf .= str_repeat("\x00", 20);
    foreach ($campos as $c) {
        list($nome,$tipo,$len,$dec) = $c;
        $dbf .= str_pad(substr($nome,0,10), 11, "\x00");
        $dbf .= $tipo;
        $dbf .= str_repeat("\x00", 4);
        $dbf .= pack('C', $len).pack('C', $dec);
        $dbf .= str_repeat("\x00", 14);
    }
    $dbf .= "\x0D"; // terminador de cabeçalho
    $dbf .= " ";    // flag de não-deletado
    foreach ($campos as $c) {
        list($nome,$tipo,$len,$dec,$orig) = $c;
        $val = onrValorCampo($orig, $row);
        if ($tipo === 'N') {
            $num = is_numeric($val) ? (float)$val : onrNumBr($val);
            $txt = ($dec > 0) ? number_format($num, $dec, '.', '') : (string)(int)round($num);
            if (strlen($txt) > $len) $txt = substr($txt, 0, $len);
            $dbf .= str_pad($txt, $len, ' ', STR_PAD_LEFT);
        } else {
            $txt = (string)$val;
            // remove acentos/caracteres fora do ASCII para o dBASE
            $txt = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
            if ($txt === false) $txt = (string)$val;
            $txt = substr($txt, 0, $len);
            $dbf .= str_pad($txt, $len, ' ', STR_PAD_RIGHT);
        }
    }
    $dbf .= "\x1A"; // EOF

    // ---- .prj (SIRGAS 2000 geográfico, EPSG:4674) ----
    $prj = 'GEOGCS["SIRGAS 2000",DATUM["Sistema_de_Referencia_Geocentrico_para_as_Americas_2000",'
         . 'SPHEROID["GRS 1980",6378137,298.257222101]],PRIMEM["Greenwich",0],'
         . 'UNIT["degree",0.0174532925199433]]';

    $paths = [];
    $map = ['shp'=>$shp, 'shx'=>$shx, 'dbf'=>$dbf, 'prj'=>$prj];
    foreach ($map as $ext => $bin) {
        $fp = rtrim($dir,'/').'/'.$base.'.'.$ext;
        if (@file_put_contents($fp, $bin) === false) return false;
        $paths[$ext] = $fp;
    }
    return $paths;
}

/* ===================== CLIENTE HTTP DA API ONR ===================== */
function onrHttp($method, $url, $token, $jsonBody = null, $putFile = null) {
    $ch = curl_init($url);
    $headers = [];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    // XAMPP/Windows costuma não ter CA bundle configurado -> HTTPS falha (HTTP 0).
    // Usa o cacert.pem ao lado deste arquivo, se existir; caso contrário não verifica o par.
    $ca = __DIR__ . '/cacert.pem';
    if (is_file($ca)) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    if ($putFile !== null) {
        $headers[] = 'Content-Type: application/octet-stream';
        curl_setopt($ch, CURLOPT_POSTFIELDS, (string)@file_get_contents($putFile));
    }
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    $json = json_decode((string)$body, true);
    return ['code' => $code, 'body' => $body, 'json' => $json, 'erro' => $err, 'errno' => $errno];
}
// Executa o fluxo completo de envio de um imóvel; retorna ['ok'=>bool,'mensagem','importation_id','status']
function onrEnviarImovel($conn, $id) {
    $cfg = onrConfigLer();
    if (trim($cfg['token']) === '') return ['ok' => false, 'mensagem' => 'Token da API ONR não configurado.'];
    // normaliza a URL base: precisa de esquema http/https e barra final
    $base = trim((string)$cfg['base_url']);
    if ($base === '') return ['ok' => false, 'mensagem' => 'URL base da API ONR não configurada (⚙ Configurar API ONR).'];
    if (!preg_match('#^https?://#i', $base)) return ['ok' => false, 'mensagem' => 'URL base inválida: deve começar com http:// ou https:// (valor atual: "' . $base . '").'];
    $base = rtrim($base, '/') . '/';
    $token = $cfg['token'];

    $id = (int)$id;
    $res = $conn->query("SELECT * FROM memoriais_mapeados WHERE id = $id LIMIT 1");
    if (!$res || !($row = $res->fetch_assoc())) return ['ok' => false, 'mensagem' => 'Imóvel não encontrado.'];

    // imóvel fora do perímetro do município: não pertence ao cartório — envio bloqueado
    $foraMun = trim((string)($row['fora_municipio'] ?? ''));
    if ($foraMun !== '') return ['ok' => false, 'mensagem' => 'Imóvel FORA do município' . ($foraMun !== 'fora' ? ' (está em ' . $foraMun . ')' : '') . ' — não pertence a este cartório. Envio ao Mapa ONR bloqueado.'];

    // valida metadados obrigatórios
    $cat = ($row['tipo_imovel'] === 'rural') ? 'RURAL' : (($row['tipo_imovel'] === 'urbano') ? 'URBANO' : '');
    $faltam = [];
    if ($cat === '') $faltam[] = 'tipo (urbano/rural)';
    if (($row['onr_nivel_publicidade'] ?? '') === '') $faltam[] = 'nível de publicidade';
    if (($row['onr_classificacao'] ?? '') === '') $faltam[] = 'classificação da importação';
    if (($row['onr_numero_prenotacao'] ?? '') === '') $faltam[] = 'número da prenotação';
    if (($row['onr_descricao'] ?? '') === '') $faltam[] = 'descrição';
    if ($faltam) return ['ok' => false, 'mensagem' => 'Faltam dados ONR: ' . implode(', ', $faltam) . '.'];

    // gera shapefile em diretório temporário
    $dir = sys_get_temp_dir() . '/onr_' . $id . '_' . uniqid();
    @mkdir($dir, 0775, true);
    $baseNome = preg_replace('/[^A-Za-z0-9_]/', '_', 'imovel_' . ($row['numero_matricula'] ?: $id));
    $arqs = onrGerarShapefile($conn, $id, $dir, $baseNome);
    if (!$arqs) return ['ok' => false, 'mensagem' => 'Falha ao gerar o shapefile (polígono inválido?).'];

    $nomes = array_map(fn($e) => $baseNome . '.' . $e, ['shp','shx','dbf','prj']);

    // Passo 1: gerar URLs de importação
    $payload = [
        'categoria_poligono'      => $cat,
        'nivel_publicidade'       => (int)$row['onr_nivel_publicidade'],
        'classificacao_poligonos' => (int)$row['onr_classificacao'],
        'numero_prenotacao'       => (string)$row['onr_numero_prenotacao'],
        'descricao_importacao'    => (string)$row['onr_descricao'],
        'nomes_arquivos'          => array_values($nomes),
    ];
    $r1 = onrHttp('POST', $base . 'api/v1/poligonos/gerar-url-importacao', $token, $payload);
    if ($r1['code'] < 200 || $r1['code'] >= 300 || empty($r1['json']['data']['importation_id'])) {
        if ((int)$r1['code'] === 0) {
            $detalhe = $r1['erro'] !== '' ? $r1['erro'] : 'sem resposta do servidor';
            return ['ok' => false, 'mensagem' => 'Não foi possível conectar à API ONR (' . $detalhe . '). Verifique a URL base, a internet do servidor e o certificado SSL (cacert.pem).'];
        }
        $msg = $r1['json']['mensagem'] ?? ($r1['json']['message'] ?? ('HTTP ' . $r1['code'] . ($r1['erro'] ? ' — ' . $r1['erro'] : '')));
        return ['ok' => false, 'mensagem' => 'Erro ao gerar URLs: ' . $msg];
    }
    $importId = $r1['json']['data']['importation_id'];
    $urls = $r1['json']['data']['upload_urls'] ?? [];

    // Passo 2: upload (PUT) de cada arquivo na URL correspondente
    foreach ($urls as $u) {
        $fn = $u['filename'] ?? ''; $upUrl = $u['upload_url'] ?? '';
        $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
        if (!isset($arqs[$ext]) || $upUrl === '') continue;
        $rp = onrHttp('PUT', $upUrl, null, null, $arqs[$ext]);
        if ($rp['code'] < 200 || $rp['code'] >= 300) {
            $d = ((int)$rp['code'] === 0 && $rp['erro'] !== '') ? $rp['erro'] : ('HTTP ' . $rp['code']);
            return ['ok' => false, 'mensagem' => "Falha no upload de $fn ($d)."];
        }
    }

    // Passo 3: confirmar
    $r3 = onrHttp('POST', $base . 'api/v1/poligonos/confirmar', $token, ['importation_id' => $importId]);
    if ($r3['code'] < 200 || $r3['code'] >= 300) {
        if ((int)$r3['code'] === 0) {
            $d = $r3['erro'] !== '' ? $r3['erro'] : 'sem resposta do servidor';
            return ['ok' => false, 'mensagem' => 'Não foi possível confirmar na API ONR (' . $d . ').', 'importation_id' => $importId];
        }
        $msg = $r3['json']['mensagem'] ?? ($r3['json']['message'] ?? ('HTTP ' . $r3['code']));
        return ['ok' => false, 'mensagem' => 'Erro ao confirmar: ' . $msg, 'importation_id' => $importId];
    }

    // grava o id da importação e status no imóvel
    $stmt = $conn->prepare("UPDATE memoriais_mapeados SET onr_importation_id=?, onr_status=?, onr_enviado_em=NOW() WHERE id=?");
    $st = 'ENVIADO';
    $stmt->bind_param('ssi', $importId, $st, $id);
    $stmt->execute();

    // limpa arquivos temporários
    foreach ($arqs as $f) { @unlink($f); } @rmdir($dir);

    return ['ok' => true, 'mensagem' => 'Enviado com sucesso à ONR.', 'importation_id' => $importId, 'status' => 'ENVIADO'];
}

/** Tenta localizar um imóvel existente pela matrícula. */
function findImovelIdByMatricula($conn, $matricula) {
    try {
        // O Atlas usa a coluna 'numero' em cadastro_de_imoveis; outros sistemas usam
        // 'numero_matricula'. Detecta qual coluna existe para vincular o imóvel.
        $coluna = null;
        foreach (array('numero_matricula', 'numero', 'matricula') as $cand) {
            $chk = @$conn->query("SHOW COLUMNS FROM cadastro_de_imoveis LIKE '" . $conn->real_escape_string($cand) . "'");
            if ($chk && $chk->num_rows > 0) { $coluna = $cand; break; }
        }
        if ($coluna === null) return null;

        $stmt = $conn->prepare("SELECT id FROM cadastro_de_imoveis WHERE `$coluna` = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $matricula);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;
        return $row ? (int)$row['id'] : null;
    } catch (Throwable $e) {
        // Se a tabela/coluna não existir neste ambiente, apenas não vincula o imóvel.
        return null;
    }
}

/** Insere um imóvel mapeado e devolve o id gerado. */
function inserirMemorial($conn, $identificador, $tipo, $origem, $imovelId, $fonte, $data, $numMatricula = '', $proprietario = '', $cpf = '', $tipoImovel = '') {
    $nm = ($numMatricula !== '') ? $numMatricula : null;
    $pr = ($proprietario !== '') ? $proprietario : null;
    $cp = ($cpf !== '') ? $cpf : null;
    $ti = in_array($tipoImovel, ['urbano', 'rural'], true) ? $tipoImovel : null;
    $stmt = $conn->prepare(
        "INSERT INTO memoriais_mapeados
         (identificador, tipo_identificador, origem, imovel_id, memorial_descritivo,
          num_vertices, area_ha, perimetro_m, centro_lat, centro_lng,
          coordenadas_wgs84, coordenadas_utm,
          numero_matricula, proprietario, cpf, tipo_imovel)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'sssisiddddssssss',
        $identificador, $tipo, $origem, $imovelId, $fonte,
        $data['num_vertices'], $data['area_ha'], $data['perimetro_m'],
        $data['centro_lat'], $data['centro_lng'],
        $data['coordenadas_wgs84'], $data['coordenadas_utm'],
        $nm, $pr, $cp, $ti
    );
    $stmt->execute();
    return $stmt->insert_id;
}

/* ====================================================================
 *  RELATÓRIO DE SOBREPOSIÇÃO (PDF / TCPDF)
 * ==================================================================== */

/** Codifica um número no algoritmo de polyline do Google. */
function encodePolylineNumber($num) {
    $num = $num << 1;
    if ($num < 0) $num = ~$num;
    $chunks = '';
    while ($num >= 0x20) {
        $chunks .= chr((0x20 | ($num & 0x1f)) + 63);
        $num >>= 5;
    }
    $chunks .= chr($num + 63);
    return $chunks;
}

/** Codifica uma lista de pontos [[lat,lng],...] em polyline (encurta a URL do Static Maps). */
function encodePolyline($points) {
    $result = ''; $prevLat = 0; $prevLng = 0;
    foreach ($points as $p) {
        $lat = (int) round($p[0] * 1e5);
        $lng = (int) round($p[1] * 1e5);
        $result .= encodePolylineNumber($lat - $prevLat);
        $result .= encodePolylineNumber($lng - $prevLng);
        $prevLat = $lat; $prevLng = $lng;
    }
    return $result;
}

/** Fecha o anel (1º vértice == último) para o preenchimento do polígono. */
function closeRing($pts) {
    if (count($pts) < 2) return $pts;
    $f = $pts[0]; $l = end($pts);
    if (abs($f[0] - $l[0]) > 1e-12 || abs($f[1] - $l[1]) > 1e-12) $pts[] = $f;
    return $pts;
}

/** Monta a URL do Google Static Maps com imóvel A, imóvel B e a sobreposição destacada. */
function staticMapUrlSobreposicao($polyA, $polyB, $rings, $key) {
    $params = ['size=640x360', 'scale=2', 'maptype=hybrid', 'format=png'];
    if ($polyA && count($polyA) >= 3) {
        $v = 'color:0x3b82f6ff|weight:2|fillcolor:0x3b82f633|enc:' . encodePolyline(closeRing($polyA));
        $params[] = 'path=' . rawurlencode($v);
    }
    if ($polyB && count($polyB) >= 3) {
        $v = 'color:0xeab308ff|weight:2|fillcolor:0xeab30833|enc:' . encodePolyline(closeRing($polyB));
        $params[] = 'path=' . rawurlencode($v);
    }
    if (is_array($rings)) {
        foreach ($rings as $ring) {
            if (count($ring) >= 3) {
                $v = 'color:0xe2342fff|weight:2|fillcolor:0xe2342fcc|enc:' . encodePolyline(closeRing($ring));
                $params[] = 'path=' . rawurlencode($v);
            }
        }
    }
    $params[] = 'key=' . $key;
    return 'https://maps.googleapis.com/maps/api/staticmap?' . implode('&', $params);
}

/** Static Maps de UM imóvel: desenha o polígono (a partir das coordenadas gravadas). */
function staticMapUrlImovel($pts, $key) {
    if (count($pts) < 3) return '';
    $params = ['size=640x360', 'scale=2', 'maptype=hybrid', 'format=png'];
    $params[] = 'path=' . rawurlencode('color:0xe2342fff|weight:3|fillcolor:0xe2342f33|enc:' . encodePolyline(closeRing($pts)));
    // marcadores pequenos nos vértices (ajudam a localizar os pontos citados nas inconsistências)
    if (count($pts) <= 60) {
        $locs = [];
        foreach ($pts as $p) { $locs[] = round($p[0], 6) . ',' . round($p[1], 6); }
        $params[] = 'markers=' . rawurlencode('size:tiny|color:0xffffff|' . implode('|', $locs));
    }
    $params[] = 'key=' . $key;
    return 'https://maps.googleapis.com/maps/api/staticmap?' . implode('&', $params);
}

/** Busca os bytes de uma imagem por URL (cURL ou file_get_contents). */
function fetchImageBytes($url, &$erro = null) {
    $erro = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true,
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($data !== false && $code === 200 && strpos($ctype, 'image') !== false) return $data;
        if ($cerr !== '') { $erro = 'cURL: ' . $cerr; }
        elseif ($code !== 200) { $erro = 'HTTP ' . $code . ($data ? ' — ' . substr(strip_tags((string)$data), 0, 120) : ''); }
        else { $erro = 'resposta sem imagem (' . $ctype . ')'; }
        return false;
    }
    if (!ini_get('allow_url_fopen')) { $erro = 'allow_url_fopen desligado'; return false; }
    $ctx = stream_context_create(['http' => ['timeout' => 12]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) { $erro = 'file_get_contents falhou'; return false; }
    return $data;
}

/** Busca texto (JSON/GeoJSON) por URL — usado para as malhas/municípios do IBGE. */
function httpGetText($url, &$erro = null) {
    $erro = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json, application/vnd.geo+json'],
            CURLOPT_USERAGENT => 'Atlas-Mapeador/1.0',
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        if ($data !== false && $code >= 200 && $code < 300) return $data;
        if ($cerr !== '') { $erro = 'cURL: ' . $cerr; }
        else { $erro = 'HTTP ' . $code . ($data ? ' — ' . substr(strip_tags((string)$data), 0, 200) : ''); }
        return false;
    }
    if (!ini_get('allow_url_fopen')) { $erro = 'allow_url_fopen desligado'; return false; }
    $ctx = stream_context_create(['http' => ['timeout' => 25, 'header' => "Accept: application/json\r\n"]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) { $erro = 'file_get_contents falhou'; return false; }
    return $data;
}

/** Coloca uma imagem (binário PNG) no PDF, respeitando a quebra de página. */
function pdfColocarImagem($pdf, $imgData, $w = 182, $ratio = 0.5625) {
    if ($imgData === false || $imgData === null) return false;
    $h = $w * $ratio;
    if ($pdf->GetY() + $h > $pdf->getPageHeight() - 18) $pdf->AddPage();
    $tmp = tempnam(sys_get_temp_dir(), 'sm') . '.png';
    file_put_contents($tmp, $imgData);
    $pdf->Image($tmp, 14, $pdf->GetY(), $w, 0, 'PNG');
    $pdf->SetY($pdf->GetY() + $h + 3);
    @unlink($tmp);
    return true;
}

/** Mapa geral: todos os imóveis (azul) + regiões de sobreposição (vermelho). */
function staticMapUrlGeral($imoveisById, $allRings, $key) {
    $base = 'https://maps.googleapis.com/maps/api/staticmap?size=640x420&scale=2&maptype=hybrid&format=png';
    $paths = '';
    // regiões de sobreposição primeiro (sempre incluídas)
    if (is_array($allRings)) {
        foreach ($allRings as $ring) {
            if (count($ring) >= 3) {
                $paths .= '&path=' . rawurlencode('color:0xe2342fff|weight:2|fillcolor:0xe2342fcc|enc:' . encodePolyline(closeRing($ring)));
            }
        }
    }
    // contornos dos imóveis (limita o tamanho da URL ~8000 chars)
    foreach ($imoveisById as $pts) {
        if (count($pts) < 3) continue;
        $p = '&path=' . rawurlencode('color:0x3b82f6ff|weight:2|fillcolor:0x3b82f622|enc:' . encodePolyline(closeRing($pts)));
        if (strlen($base . $paths . $p) > 8000) break;
        $paths .= $p;
    }
    return $base . $paths . '&key=' . $key;
}

/** Lê os pontos [[lat,lng],...] de um imóvel pelo id. */
function ptsById($conn, $id) {
    $stmt = $conn->prepare("SELECT coordenadas_wgs84 FROM memoriais_mapeados WHERE id = ?");
    if (!$stmt) return [];
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    if (!$row) return [];
    $pts = [];
    foreach (preg_split('/\s+/', trim((string)$row['coordenadas_wgs84'])) as $par) {
        if ($par === '') continue;
        $xy = explode(',', $par);
        if (count($xy) >= 2) $pts[] = [(float)$xy[0], (float)$xy[1]];
    }
    return $pts;
}

function gerarRelatorioSobreposicaoPDF($dados) {
    // TCPDF compartilhado do Atlas (mesma instância usada por recibo/guia em os/)
    if (file_exists(__DIR__ . '/../oficios/tcpdf/tcpdf.php')) {
        require_once __DIR__ . '/../oficios/tcpdf/tcpdf.php';
    } else {
        require_once __DIR__ . '/tcpdf/tcpdf.php';
    }

    if (!class_exists('RelatorioSobrepPDF')) {
        class RelatorioSobrepPDF extends TCPDF {
            public function Header() {
                // Timbrado FORÇADO a 100% da folha (estica para A4, ignorando a proporção da imagem).
                $timbrado = __DIR__ . '/../style/img/timbrado.png';
                if (@file_exists($timbrado)) {
                    $pw = $this->getPageWidth();
                    $ph = $this->getPageHeight();
                    // O fitBlock do TCPDF encolhe a imagem para caber nas margens; por isso zeramos
                    // temporariamente margens e quebra de página só para desenhar o timbrado em página cheia.
                    $oldL = $this->lMargin; $oldR = $this->rMargin; $oldT = $this->tMargin;
                    $oldB = $this->bMargin; $oldAPB = $this->AutoPageBreak;
                    $this->lMargin = 0; $this->rMargin = 0; $this->tMargin = 0;
                    $this->SetAutoPageBreak(false, 0);
                    // type '' = autodetecta o formato (caso o timbrado seja trocado por JPG etc.)
                    @$this->Image($timbrado, 0, 0, $pw, $ph, '', '', '', false, 300, '', false, false, 0, false, false, false);
                    // restaura o estado original para o conteúdo
                    $this->lMargin = $oldL; $this->rMargin = $oldR; $this->tMargin = $oldT;
                    $this->SetAutoPageBreak($oldAPB, $oldB);
                } else {
                    // Fallback: barra vermelha caso o timbrado não exista neste ambiente
                    $this->SetFillColor(168, 15, 30);
                    $this->Rect(0, 0, $this->getPageWidth(), 24, 'F');
                    $this->SetTextColor(255, 255, 255);
                    $this->SetFont('helvetica', 'B', 14);
                    $this->SetXY(14, 6);
                    $this->Cell(0, 8, 'RELATÓRIO DE SOBREPOSIÇÃO DE IMÓVEIS', 0, 1, 'L');
                    $this->SetTextColor(0, 0, 0);
                }
            }
            public function Footer() {
                $pw = $this->getPageWidth();
                $this->SetFont('helvetica', '', 7);
                $this->SetTextColor(120, 120, 120);
                // "Emitido em ..." na VERTICAL, junto à margem direita da página
                $txt = 'Emitido em ' . date('d/m/Y H:i') . ' — Atlas Dimensor / Sistema Atlas';
                $tw  = $this->GetStringWidth($txt);
                $xv  = $pw - 6;                                  // ~6 mm da borda direita (afastado)
                $yv  = ($this->getPageHeight() + $tw) / 2;       // centralizado verticalmente
                $this->StartTransform();
                $this->Rotate(90, $xv, $yv);                     // gira 90° (lê de baixo p/ cima)
                $this->Text($xv, $yv, $txt);
                $this->StopTransform();
                // "Página X de Y" — 2 mm acima e 5 mm mais à direita
                $this->SetXY(0, -24);
                $this->Cell($pw - 9, 5, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'R');
            }
        }
    }

    $overlaps = isset($dados['overlaps']) && is_array($dados['overlaps']) ? $dados['overlaps'] : [];
    $totalImoveis = isset($dados['total_imoveis']) ? (int)$dados['total_imoveis'] : 0;
    $br = function ($v, $d = 2) { return number_format((float)$v, $d, ',', '.'); };

    // Carrega os polígonos apenas dos imóveis envolvidos nas sobreposições do relatório
    global $conn;
    $imoveisById = [];
    if ($conn) {
        $ids = [];
        foreach ($overlaps as $o) {
            if (isset($o['a']['id'])) $ids[(int)$o['a']['id']] = true;
            if (isset($o['b']['id'])) $ids[(int)$o['b']['id']] = true;
        }
        foreach (array_keys($ids) as $id) {
            $pts = ptsById($conn, $id);
            if (count($pts) >= 3) $imoveisById[$id] = $pts;
        }
    }

    $areaTotal = 0;
    foreach ($overlaps as $o) { $areaTotal += isset($o['area_ha']) ? (float)$o['area_ha'] : 0; }

    $pdf = new RelatorioSobrepPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('Sistema Atlas');
    $pdf->SetTitle('Relatório de Sobreposição de Imóveis');
    $pdf->setHeaderMargin(0);       // timbrado encosta no topo da folha
    $pdf->setFooterMargin(26);      // reserva o pé da folha para o rodapé do timbrado
    $pdf->setImageScale(1);
    $pdf->SetMargins(14, 42, 14);   // topo 42 para limpar o cabeçalho do timbrado
    $pdf->SetAutoPageBreak(true, 28);// fundo 28 para o conteúdo não invadir o rodapé do timbrado
    $pdf->AddPage();

    // ---- Título do relatório (no corpo, pois o cabeçalho agora é o timbrado) ----
    $titulo = '<div style="text-align:center">'
        . '<span style="font-size:15px;font-weight:bold;color:#a80f1e">RELATÓRIO DE SOBREPOSIÇÃO DE IMÓVEIS</span><br>'
        . '<span style="font-size:9px;color:#555555">Análise técnica para verificação e correção — Sistema Atlas</span>'
        . '</div><br>';
    $pdf->writeHTML($titulo, true, false, true, false, '');

    // ---- Matrículas em foco + resumo descritivo (logo no início do relatório) ----
    $rotMat = function ($s) {
        $s = trim((string)$s);
        return $s === '' ? '' : preg_replace('/^0+(?=\d)/', '', $s);
    };
    // matrículas pesquisadas (campo "mats"); na falta, as envolvidas nas sobreposições
    $matsFoco = trim((string)($_POST['mats'] ?? ''));
    if ($matsFoco === '') {
        $set = [];
        foreach ($overlaps as $o) {
            foreach (['a', 'b'] as $k) {
                $m = isset($o[$k]['numero_matricula']) ? $rotMat($o[$k]['numero_matricula']) : '';
                if ($m !== '') $set[$m] = true;
            }
        }
        $matsFoco = implode(', ', array_keys($set));
    }
    $focoLabel = $matsFoco !== '' ? 'Mat. ' . htmlspecialchars($matsFoco, ENT_QUOTES, 'UTF-8') : '—';
    $focoFrase = $matsFoco !== ''
        ? 'a(s) matrícula(s) <b>Mat. ' . htmlspecialchars($matsFoco, ENT_QUOTES, 'UTF-8') . '</b>'
        : 'os imóveis cadastrados';

    $nOver = count($overlaps);
    // separa material x formal (Prov. CNJ 149, red. Prov. 195/2025, Art. 440-AZ §§1º-2º)
    $nMat = 0; $nForm = 0; $areaMat = 0.0; $areaForm = 0.0;
    foreach ($overlaps as $o) {
        $t = ($o['tipo'] ?? 'material') === 'formal' ? 'formal' : 'material';
        if ($t === 'formal') { $nForm++; $areaForm += (float)($o['area_ha'] ?? 0); }
        else { $nMat++; $areaMat += (float)($o['area_ha'] ?? 0); }
    }
    $listaHtml = '';
    if ($nOver > 0) {
        $descr = 'Esta análise tem como foco ' . $focoFrase . '. '
            . 'Foram identificadas <b>' . $nOver . '</b> coincidência(s) de área, totalizando <b>' . $br($areaTotal, 4) . ' ha</b> (' . $br($areaTotal * 10000, 2) . ' m²): '
            . '<b>' . $nMat . '</b> material(is)' . ($nForm ? ' e <b>' . $nForm . '</b> meramente formal(is), de divisa' : '') . '. '
            . 'Nos termos do Provimento CNJ n. 149 (red. Prov. n. 195/2025), Art. 440-AZ: a sobreposição <b>material</b> (§1º) ultrapassa a tolerância posicional do manual técnico do ONR e demanda saneamento (Art. 440-BA); a <b>formal</b> (§2º) restringe-se às divisas ou a pequena parte por técnica de levantamento, dentro da tolerância, não ensejando saneamento isoladamente.';
        $lim = 0;
        foreach ($overlaps as $o) {
            $lim++;
            if ($lim > 6) { $listaHtml .= '• … e mais ' . ($nOver - 6) . ' coincidência(s) detalhada(s) na sequência.<br>'; break; }
            $ma = $rotMat($o['a']['numero_matricula'] ?? '');
            $mb = $rotMat($o['b']['numero_matricula'] ?? '');
            $na = trim((string)($o['a']['identificador'] ?? ''));
            $nb = trim((string)($o['b']['identificador'] ?? ''));
            $rotA = $ma !== '' ? 'Mat. ' . $ma : ($na !== '' ? $na : '—');
            $rotB = $mb !== '' ? 'Mat. ' . $mb : ($nb !== '' ? $nb : '—');
            $ao = isset($o['area_ha']) ? (float)$o['area_ha'] : 0;
            $tf = ($o['tipo'] ?? 'material') === 'formal';
            $tag = $tf ? '<span style="color:#b45309;">[formal · divisa]</span>' : '<span style="color:#a80f1e;">[material]</span>';
            $listaHtml .= '• <b>' . htmlspecialchars($rotA, ENT_QUOTES, 'UTF-8') . '</b> &times; <b>' . htmlspecialchars($rotB, ENT_QUOTES, 'UTF-8') . '</b> ' . $tag . ' — '
                . $br($ao, 4) . ' ha (' . $br($ao * 10000, 2) . ' m²)<br>';
        }
    } else {
        $descr = 'Esta análise tem como foco ' . $focoFrase . '. '
            . '<b>Não foi detectada sobreposição</b> entre os imóveis analisados, conforme as coordenadas registradas.';
    }

    // Caixa "MATRÍCULA(S) EM FOCO" desenhada manualmente (controle total do espaçamento;
    // a versão em <table> deixava um vão grande depois dela).
    $focoLabelPlain = $matsFoco !== '' ? 'Mat. ' . $matsFoco : '—';
    $pdf->SetTextColor(0, 0, 0);
    $mg = $pdf->getMargins();
    $bx = $mg['left']; $by = $pdf->GetY();
    $bw = $pdf->getPageWidth() - $mg['left'] - $mg['right'];
    $pdf->SetFont('helvetica', 'B', 12);
    $nLab = max(1, $pdf->getNumLines($focoLabelPlain, $bw - 6));
    $boxH = 9 + $nLab * 5.0;
    $pdf->SetFillColor(251, 234, 236);   // #fbeaec
    $pdf->SetDrawColor(168, 15, 30);     // #a80f1e
    $pdf->SetLineWidth(0.3);
    $pdf->Rect($bx, $by, $bw, $boxH, 'DF');
    $pdf->SetXY($bx + 3, $by + 2.3);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(168, 15, 30);
    $pdf->MultiCell($bw - 6, 4, 'MATRÍCULA(S) EM FOCO', 0, 'L');
    $pdf->SetX($bx + 3);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->MultiCell($bw - 6, 5.0, $focoLabelPlain, 0, 'L');
    $pdf->SetY($by + $boxH + 2);
    // Descrição + lista num ÚNICO bloco (evita o espaço em branco que vários <div>/<br> geram)
    $bloco = '<div style="font-family:helvetica;font-size:9.5px;color:#222222;text-align:justify;">' . $descr;
    if ($listaHtml !== '') {
        $bloco .= '<br><br><span style="font-size:9px;color:#333333;">' . $listaHtml . '</span>';
    }
    $bloco .= '</div>';
    $pdf->SetTextColor(0, 0, 0);
    $pdf->writeHTML($bloco, true, false, true, false, '');
    $pdf->Ln(1);

    // ---- Resumo ----
    $resumo = '
    <style>
      .box { border:1px solid #d9d9d9; background:#f6f6f6; }
      td { font-family:helvetica; }
      .k { color:#555; }
      .v { font-weight:bold; }
    </style>
    <table cellpadding="5" class="box">
      <tr>
        <td width="33%"><span class="k">Data da análise</span><br><span class="v">' . date('d/m/Y H:i') . '</span></td>
        <td width="33%"><span class="k">Imóveis analisados</span><br><span class="v">' . $totalImoveis . '</span></td>
        <td width="34%"><span class="k">Sobreposições detectadas</span><br><span class="v" style="color:#a80f1e">' . count($overlaps) . '</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="k">Área total sobreposta</span> &nbsp; <span class="v">' . $br($areaTotal, 4) . ' ha</span> &nbsp;(' . $br($areaTotal * 10000, 2) . ' m²)</td>
      </tr>
    </table>';
    $pdf->SetTextColor(0, 0, 0);
    $pdf->writeHTML($resumo, true, false, true, false, '');
    $pdf->Ln(2);

    // ---- Imagem do mapa geral (imóveis + sobreposições) ----
    if (!empty($overlaps) && !empty($imoveisById)) {
        $allRings = [];
        foreach ($overlaps as $o) {
            if (!empty($o['rings']) && is_array($o['rings'])) {
                foreach ($o['rings'] as $r) $allRings[] = $r;
            }
        }
        $erroImg = '';
        $imgGeral = fetchImageBytes(staticMapUrlGeral($imoveisById, $allRings, GMAPS_STATIC_KEY), $erroImg);
        if ($imgGeral !== false) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Mapa geral — imóveis (azul) e sobreposições (vermelho)', 0, 1, 'L');
            pdfColocarImagem($pdf, $imgGeral, 168, 420 / 640);
            $pdf->Ln(2);
        } else {
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(168, 15, 30);
            $pdf->MultiCell(0, 5, 'Mapa indisponível (' . $erroImg . '). Habilite a "Maps Static API" no Google Cloud e use, em GMAPS_STATIC_KEY, uma chave sem restrição de referer (o servidor não envia referer).', 0, 'L');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(2);
        }
    }

    if (empty($overlaps)) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(20, 120, 60);
        $pdf->Ln(4);
        $pdf->Cell(0, 8, 'Nenhuma sobreposição detectada entre os imóveis analisados.', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 6, 'Os ' . $totalImoveis . ' imóveis cadastrados não apresentam interseção de áreas conforme as coordenadas registradas.', 0, 'L');
    } else {
        // Página 1 fica reservada ao resumo + mapa geral; o detalhamento por
        // sobreposição começa sempre na página 2.
        $pdf->AddPage();
        $n = 0;
        // rótulos: matrícula em destaque (quando houver) + identificação
        $linhaImovel = function ($mat, $nome) {
            $parts = [];
            if ($mat !== '') $parts[] = '<b>Matrícula ' . htmlspecialchars($mat, ENT_QUOTES, 'UTF-8') . '</b>';
            if ($nome !== '' && $nome !== '—') $parts[] = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
            if (empty($parts)) $parts[] = '—';
            return implode('<br>', $parts);
        };
        $capImovel = function ($mat, $nome) {
            $mat = preg_replace('/^0+(?=\d)/', '', trim((string)$mat));   // sem zeros à esquerda
            $nome = trim((string)$nome);
            $iguala = ($nome !== '' && (mb_strtolower($nome) === mb_strtolower($mat) || ltrim($nome, '0') === ltrim($mat, '0')));
            if ($mat !== '' && $nome !== '' && $nome !== '—' && !$iguala) return 'Mat. ' . $mat . ' (' . $nome . ')';
            if ($mat !== '') return 'Mat. ' . $mat;
            return ($nome !== '' ? $nome : '—');
        };
        foreach ($overlaps as $o) {
            $n++;
            $a = isset($o['a']) ? $o['a'] : [];
            $b = isset($o['b']) ? $o['b'] : [];
            $nomeA = isset($a['identificador']) ? $a['identificador'] : '—';
            $nomeB = isset($b['identificador']) ? $b['identificador'] : '—';
            $matA  = isset($a['numero_matricula']) ? trim((string)$a['numero_matricula']) : '';
            $matB  = isset($b['numero_matricula']) ? trim((string)$b['numero_matricula']) : '';
            $areaA = isset($a['area_ha']) ? (float)$a['area_ha'] : 0;
            $areaB = isset($b['area_ha']) ? (float)$b['area_ha'] : 0;
            $areaO = isset($o['area_ha']) ? (float)$o['area_ha'] : 0;
            $cen   = isset($o['centro']) ? $o['centro'] : ['lat' => 0, 'lng' => 0];
            $cenUtm = geoToUTM((float)$cen['lat'], (float)$cen['lng']);
            $pctA = $areaA > 0 ? ($areaO / $areaA * 100) : 0;
            $pctB = $areaB > 0 ? ($areaO / $areaB * 100) : 0;

            // Classificação material x formal (Prov. CNJ 149, red. Prov. 195/2025, Art. 440-AZ)
            $ehFormal = (($o['tipo'] ?? 'material') === 'formal');
            $largOv = (isset($o['largura_m']) && $o['largura_m'] !== null) ? (float)$o['largura_m'] : null;
            $largTxt = $largOv !== null ? ' Faixa de sobreposição de ~' . $br($largOv, 2) . ' m.' : '';
            $tagHdr = $ehFormal
                ? '&nbsp;&nbsp;<span style="background-color:#b45309;color:#fff;font-weight:bold;">&nbsp; FORMAL · DIVISA &nbsp;</span>'
                : '&nbsp;&nbsp;<span style="background-color:#7a0c16;color:#fff;font-weight:bold;">&nbsp; MATERIAL &nbsp;</span>';
            $notaCls = $ehFormal
                ? '<b>Sobreposição meramente formal (Art. 440-AZ §2º):</b> restrita à divisa / pequena parte por técnica de levantamento, dentro da tolerância posicional (' . $br(0.50, 2) . ' m).' . $largTxt . ' Não enseja saneamento isoladamente; cabe ao registrador a prudente análise com base no SIG-RI.'
                : '<b>Sobreposição material (Art. 440-AZ §1º):</b> ultrapassa a tolerância posicional do manual técnico do ONR.' . $largTxt . ' Recomenda-se observação na certidão (Art. 440-BA) e saneamento na forma do art. 213, II, da Lei 6.015/1973.';
            $corNota = $ehFormal ? '#fdf3e3' : '#fbeaec';

            $bloco = '
            <table cellpadding="4">
              <tr><td><span style="background-color:#a80f1e;color:#fff;font-weight:bold;">&nbsp; SOBREPOSIÇÃO ' . $n . ' &nbsp;</span>' . $tagHdr . '</td></tr>
            </table>
            <table cellpadding="4" border="0.5" style="border-color:#cccccc;">
              <tr style="background-color:#f0f0f0;">
                <td width="50%"><b>Imóvel A</b></td><td width="50%"><b>Imóvel B</b></td>
              </tr>
              <tr>
                <td>' . $linhaImovel($matA, $nomeA) . '<br><small style="color:#666;">Área total: ' . $br($areaA, 4) . ' ha</small></td>
                <td>' . $linhaImovel($matB, $nomeB) . '<br><small style="color:#666;">Área total: ' . $br($areaB, 4) . ' ha</small></td>
              </tr>
            </table>
            <table cellpadding="4" border="0.5" style="border-color:#cccccc;">
              <tr>
                <td width="34%"><span style="color:#555;">Área sobreposta</span><br><b style="color:#a80f1e;">' . $br($areaO, 4) . ' ha</b> (' . $br($areaO * 10000, 2) . ' m²)</td>
                <td width="33%"><span style="color:#555;">% sobre A / B</span><br><b>' . $br($pctA, 1) . '% / ' . $br($pctB, 1) . '%</b></td>
                <td width="33%"><span style="color:#555;">Centro (UTM 23S)</span><br><b>E ' . $br($cenUtm[0], 2) . ' &nbsp; N ' . $br($cenUtm[1], 2) . '</b></td>
              </tr>
            </table>
            <table cellpadding="4"><tr><td width="100%" style="background-color:' . $corNota . ';font-size:8.5px;color:#333333;">' . $notaCls . '</td></tr></table>';
            $pdf->writeHTML($bloco, true, false, true, false, '');

            // Imagem da sobreposição (A azul · B amarelo · sobreposição vermelha)
            $rings = isset($o['rings']) && is_array($o['rings']) ? $o['rings'] : [];
            $polyA = isset($a['id']) && isset($imoveisById[(int)$a['id']]) ? $imoveisById[(int)$a['id']] : null;
            $polyB = isset($b['id']) && isset($imoveisById[(int)$b['id']]) ? $imoveisById[(int)$b['id']] : null;
            $imgOv = fetchImageBytes(staticMapUrlSobreposicao($polyA, $polyB, $rings, GMAPS_STATIC_KEY));
            if ($imgOv !== false) {
                $pdf->Ln(1);
                $pdf->SetFont('helvetica', 'B', 8);
                $tituloMapa = 'Mapa: ' . $capImovel($matA, $nomeA) . ' (azul) · '
                    . $capImovel($matB, $nomeB) . ' (amarelo) · sobreposição (vermelho)';
                $pdf->MultiCell(0, 4.2, $tituloMapa, 0, 'L');   // MultiCell quebra dentro da margem
                pdfColocarImagem($pdf, $imgOv, 182, 360 / 640);
            }

            // Vértices da região de sobreposição (lat/lng + UTM)
            $vtx = '<table cellpadding="3" border="0.5" style="border-color:#dddddd;"><tr style="background-color:#f0f0f0;">'
                 . '<td width="10%"><b>V</b></td><td width="22%"><b>Latitude</b></td><td width="22%"><b>Longitude</b></td>'
                 . '<td width="23%"><b>UTM E</b></td><td width="23%"><b>UTM N</b></td></tr>';
            $iv = 0;
            foreach ($rings as $ring) {
                foreach ($ring as $pt) {
                    $iv++;
                    $lat = (float)$pt[0]; $lng = (float)$pt[1];
                    $u = geoToUTM($lat, $lng);
                    $vtx .= '<tr><td>' . $iv . '</td><td>' . $br($lat, 6) . '</td><td>' . $br($lng, 6) . '</td>'
                          . '<td>' . $br($u[0], 2) . '</td><td>' . $br($u[1], 2) . '</td></tr>';
                }
            }
            $vtx .= '</table>';
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Ln(1);
            $pdf->Cell(0, 5, 'Vértices da região de sobreposição', 0, 1, 'L');
            $pdf->writeHTML($vtx, true, false, true, false, '');

            // Espaço para parecer do engenheiro.
            // A caixa abaixo é desenhada com Rect (que NÃO dispara a quebra automática),
            // então garantimos manualmente 2,5 cm (25 mm) de folga da borda inferior:
            // rótulo (~6) + caixa (18) = 24 mm. Se não couber, vai para a próxima página.
            if ($pdf->GetY() + 24 > $pdf->getPageHeight() - 25) {
                $pdf->AddPage();
            }
            $pdf->Ln(1);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(0, 5, 'Parecer técnico / correção:', 0, 1, 'L');
            $pdf->SetDrawColor(200, 200, 200);
            $y = $pdf->GetY();
            $pdf->Rect(14, $y, $pdf->getPageWidth() - 28, 18);
            $pdf->Ln(22);
        }

        // Assinatura — respeita a mesma folga de 2,5 cm da borda inferior
        if ($pdf->GetY() + 15 > $pdf->getPageHeight() - 25) {
            $pdf->AddPage();
        }
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, '____________________________________________', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Responsável Técnico (Engenheiro / Agrimensor)', 0, 1, 'C');
    }

    // Nome do arquivo: "Relatório de Sobreposição Mat. <matrículas pesquisadas>.pdf"
    $mats = isset($_POST['mats']) ? trim((string)$_POST['mats']) : '';
    // remove caracteres inválidos para nome de arquivo e limita o tamanho
    $mats = preg_replace('/[\/\\\\:\*\?"<>\|\r\n]+/u', ' ', $mats);
    $mats = trim(preg_replace('/\s{2,}/u', ' ', $mats));
    if (mb_strlen($mats) > 80) $mats = mb_substr($mats, 0, 80) . '…';
    $nomeArq = ($mats !== '')
        ? 'Relatório de Sobreposição Mat. ' . $mats . '.pdf'
        : 'Relatório de Sobreposição.pdf';
    $pdf->Output($nomeArq, 'I');
}

/* Relatório de inconsistências (TCPDF). $ids vazio => todos os imóveis com inconsistências. */
function gerarRelatorioInconsistenciasPDF($conn, $ids) {
    if (file_exists(__DIR__ . '/../oficios/tcpdf/tcpdf.php')) { require_once __DIR__ . '/../oficios/tcpdf/tcpdf.php'; }
    elseif (file_exists(__DIR__ . '/tcpdf/tcpdf.php'))       { require_once __DIR__ . '/tcpdf/tcpdf.php'; }
    else { header('Content-Type: text/plain; charset=UTF-8'); echo 'TCPDF não encontrado neste ambiente.'; return; }

    $ids = array_values(array_filter(array_map('intval', (array)$ids)));
    $cols = "id, identificador, numero_matricula, municipio, uf, area_ha, num_vertices, origem, coordenadas_wgs84, inconsistencias";
    if ($ids) {
        $in = implode(',', $ids);
        $sql = "SELECT $cols FROM memoriais_mapeados WHERE id IN ($in) ORDER BY identificador";
    } else {
        $sql = "SELECT $cols FROM memoriais_mapeados WHERE (inconsistencias IS NOT NULL AND inconsistencias <> '' AND inconsistencias <> '[]') ORDER BY identificador";
    }
    $rank = ['erro' => 0, 'alerta' => 1, 'info' => 2];
    $rows = []; $res = $conn->query($sql);
    while ($res && ($r = $res->fetch_assoc())) {
        $stored = json_decode((string)$r['inconsistencias'], true); if (!is_array($stored)) $stored = [];
        // recomputa a análise geométrica a partir das coordenadas gravadas (enriquece registros antigos)
        $geoInc = detectarInconsistenciasGeo([
            'num_vertices' => $r['num_vertices'], 'area_ha' => $r['area_ha'], 'coordenadas_wgs84' => $r['coordenadas_wgs84']
        ], (string)$r['origem']);
        // mescla preservando as de nome e deduplicando por mensagem; ordena por severidade
        $merged = []; $vistos = [];
        foreach (array_merge($stored, $geoInc) as $it) {
            $m = (string)($it['msg'] ?? ''); if ($m === '' || isset($vistos[$m])) continue; $vistos[$m] = 1; $merged[] = $it;
        }
        usort($merged, function ($a, $b) use ($rank) { return ($rank[$a['sev'] ?? 'alerta'] ?? 1) <=> ($rank[$b['sev'] ?? 'alerta'] ?? 1); });
        if ($merged) {
            inconsGravar($conn, (int)$r['id'], $merged); // re-grava p/ refletir no badge/InfoWindow da consulta
            $r['_inc'] = $merged; $rows[] = $r;
        }
    }

    if (!class_exists('RelatorioInconsPDF')) {
        class RelatorioInconsPDF extends TCPDF {
            public function Header() {
                $timbrado = __DIR__ . '/../style/img/timbrado.png';
                if (@file_exists($timbrado)) {
                    $pw = $this->getPageWidth(); $ph = $this->getPageHeight();
                    $oldL=$this->lMargin;$oldR=$this->rMargin;$oldT=$this->tMargin;$oldB=$this->bMargin;$oldAPB=$this->AutoPageBreak;
                    $this->lMargin=0;$this->rMargin=0;$this->tMargin=0;$this->SetAutoPageBreak(false,0);
                    @$this->Image($timbrado,0,0,$pw,$ph,'','','',false,300,'',false,false,0,false,false,false);
                    $this->lMargin=$oldL;$this->rMargin=$oldR;$this->tMargin=$oldT;$this->SetAutoPageBreak($oldAPB,$oldB);
                } else {
                    $this->SetFillColor(168,15,30); $this->Rect(0,0,$this->getPageWidth(),24,'F');
                    $this->SetTextColor(255,255,255); $this->SetFont('helvetica','B',13); $this->SetXY(14,7);
                    $this->Cell(0,8,'RELATÓRIO DE INCONSISTÊNCIAS',0,1,'L'); $this->SetTextColor(0,0,0);
                }
            }
            public function Footer() {
                $pw = $this->getPageWidth();
                $this->SetFont('helvetica', '', 7);
                $this->SetTextColor(120, 120, 120);
                // "Emitido em ..." na VERTICAL, junto à margem direita da página (centralizado)
                $txt = 'Emitido em ' . date('d/m/Y H:i') . ' — Atlas Dimensor / Sistema Atlas';
                $tw  = $this->GetStringWidth($txt);
                $xv  = $pw - 6;                                  // ~6 mm da borda direita
                $yv  = ($this->getPageHeight() + $tw) / 2;       // centralizado verticalmente
                $this->StartTransform();
                $this->Rotate(90, $xv, $yv);                     // gira 90° (lê de baixo p/ cima)
                $this->Text($xv, $yv, $txt);
                $this->StopTransform();
                // "Página X de Y" — alinhada à direita, acima do timbrado
                $this->SetXY(0, -24);
                $this->Cell($pw - 9, 5, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'R');
            }
        }
    }
    $pdf = new RelatorioInconsPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('Atlas Dimensor'); $pdf->SetTitle('Relatório de inconsistências');
    $pdf->SetMargins(16, 42, 16); $pdf->SetAutoPageBreak(true, 28); // topo 42 e fundo 28 para limpar o timbrado
    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 15); $pdf->SetTextColor(30,30,30);
    $pdf->Cell(0, 8, 'Relatório de inconsistências de importação', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9); $pdf->SetTextColor(110,110,110);
    $pdf->Cell(0, 6, 'Emitido em ' . date('d/m/Y H:i') . ' · ' . count($rows) . ' imóvel(is) com inconsistências', 0, 1, 'L');
    $pdf->Ln(3);

    if (!$rows) {
        $pdf->SetFont('helvetica', '', 11); $pdf->SetTextColor(40,40,40);
        $pdf->MultiCell(0, 7, 'Nenhuma inconsistência encontrada para os imóveis selecionados.', 0, 'L');
        $pdf->Output('Relatorio_Inconsistencias.pdf', 'I'); return;
    }

    foreach ($rows as $r) {
        $titulo = trim((string)$r['identificador']); if ($titulo === '') $titulo = 'Imóvel #' . $r['id'];
        $mat = trim((string)$r['numero_matricula']);
        $loc = trim(trim((string)$r['municipio']) . (trim((string)$r['uf']) !== '' ? '/' . $r['uf'] : ''), '/');
        $area = ($r['area_ha'] !== null && $r['area_ha'] !== '') ? number_format((float)$r['area_ha'], 4, ',', '.') . ' ha' : '';

        $pdf->SetDrawColor(220,220,220); $pdf->SetFillColor(245,246,248);
        $pdf->SetFont('helvetica', 'B', 11); $pdf->SetTextColor(20,20,20);
        $pdf->MultiCell(0, 7, $titulo . ($mat !== '' ? '   ·   Matrícula ' . $mat : ''), 0, 'L', true, 1);
        $sub = [];
        if ($loc !== '')  $sub[] = $loc;
        if ($area !== '') $sub[] = $area;
        $sub[] = 'origem: ' . ($r['origem'] ?: '—');
        $pdf->SetFont('helvetica', '', 8.5); $pdf->SetTextColor(120,120,120);
        $pdf->MultiCell(0, 5, implode('   ·   ', $sub), 0, 'L');
        $pdf->Ln(1);

        foreach ($r['_inc'] as $it) {
            $sev = strtolower((string)($it['sev'] ?? 'alerta'));
            $msg = (string)($it['msg'] ?? '');
            $msg = strtr($msg, ['≈' => '~', '→' => ' a ', '–' => '-', '—' => '-']); // helvetica (core) não tem esses glifos
            if ($sev === 'erro') { $pdf->SetTextColor(168,15,30); $tag = 'ERRO'; }
            elseif ($sev === 'info') { $pdf->SetTextColor(60,90,160); $tag = 'INFO'; }
            else { $pdf->SetTextColor(150,110,0); $tag = 'ALERTA'; }
            $pdf->SetFont('helvetica', 'B', 8.5);
            $pdf->Cell(16, 5, $tag, 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9.5); $pdf->SetTextColor(45,45,45);
            $pdf->MultiCell(0, 5, $msg, 0, 'L', false, 1, $pdf->GetX(), $pdf->GetY());
        }
        // Desenho do imóvel no mapa (Static Maps), a partir das coordenadas gravadas
        $ptsMapa = [];
        foreach (preg_split('/\s+/', trim((string)$r['coordenadas_wgs84'])) as $par) {
            if ($par === '') continue; $xy = explode(',', $par); if (count($xy) >= 2) $ptsMapa[] = [(float)$xy[0], (float)$xy[1]];
        }
        if (count($ptsMapa) >= 3) {
            $pdf->Ln(1);
            $errImg = '';
            $img = fetchImageBytes(staticMapUrlImovel($ptsMapa, GMAPS_STATIC_KEY), $errImg);
            $w = 120; $h = $w * 0.5625;
            if ($img !== false && $img !== null) {
                if ($pdf->GetY() + $h > $pdf->getPageHeight() - 30) $pdf->AddPage();
                $pdf->SetFont('helvetica', '', 8); $pdf->SetTextColor(120,120,120);
                $pdf->Cell(0, 4, 'Desenho do imóvel (polígono gerado pelas coordenadas):', 0, 1, 'L');
                $tmp = tempnam(sys_get_temp_dir(), 'inc') . '.png'; file_put_contents($tmp, $img);
                $pdf->Image($tmp, $pdf->getMargins()['left'], $pdf->GetY() + 1, $w, 0, 'PNG');
                $pdf->SetY($pdf->GetY() + $h + 4);
                @unlink($tmp);
            } else {
                $pdf->SetFont('helvetica', 'I', 8); $pdf->SetTextColor(150,150,150);
                $pdf->MultiCell(0, 4, 'Desenho do imóvel indisponível (' . $errImg . '). Habilite a "Maps Static API" no Google Cloud e use, em GMAPS_STATIC_KEY, uma chave sem restrição de referer.', 0, 'L');
            }
        }
        $pdf->Ln(4);
    }
    $pdf->Output('Relatorio_Inconsistencias.pdf', 'I');
}

/* ====================================================================
 *  ROTEAMENTO DE AÇÕES (AJAX)
 * ==================================================================== */

/* ====================================================================
 *  IA (GEMINI) — OCR de matrículas em PDF
 * ==================================================================== */
function geminiModelosPadrao() { return ['gemini-3.1-flash-lite', 'gemini-3.5-flash', 'gemini-3.1-pro-preview']; }
function geminiConfigPath() { return __DIR__ . '/config_gemini.json'; }
function geminiConfigLer() {
    $base = ['api_key' => '', 'models' => geminiModelosPadrao(), 'default_model' => 'gemini-3.5-flash'];
    $p = geminiConfigPath();
    if (is_file($p)) { $d = json_decode((string)@file_get_contents($p), true); if (is_array($d)) $base = array_merge($base, $d); }
    if (empty($base['models']) || !is_array($base['models'])) $base['models'] = geminiModelosPadrao();
    if (!in_array($base['default_model'], $base['models'], true)) $base['default_model'] = $base['models'][0];
    return $base;
}
function geminiConfigSalvar($data) {
    return @file_put_contents(geminiConfigPath(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
}
/* Procura um imóvel mapeado pelo número da matrícula (exato e depois normalizado). */
function acharMemorialPorMatricula($conn, $mat) {
    $norm = function ($s) { return strtolower(preg_replace('/[^0-9a-zA-Z]/', '', (string)$s)); };
    $alvo = $norm($mat);
    if ($alvo === '') return null;
    $stmt = $conn->prepare("SELECT id FROM memoriais_mapeados WHERE numero_matricula = ? LIMIT 1");
    if ($stmt) { $stmt->bind_param('s', $mat); $stmt->execute(); $r = $stmt->get_result(); if ($r && ($row = $r->fetch_assoc())) return (int)$row['id']; }
    $res = $conn->query("SELECT id, numero_matricula FROM memoriais_mapeados WHERE numero_matricula IS NOT NULL AND numero_matricula <> ''");
    while ($res && ($row = $res->fetch_assoc())) { if ($norm($row['numero_matricula']) === $alvo) return (int)$row['id']; }
    return null;
}

/* Busca o imóvel para complementar via PDF: por número de matrícula (arquivo ou documento)
   e, como fallback, por identificador — cobre imóveis cadastrados só com KML (que muitas
   vezes têm apenas o identificador preenchido, sem numero_matricula). */
function acharMemorialParaPdf($conn, $mat, $matDoc = '') {
    $id = acharMemorialPorMatricula($conn, $mat);
    if ($id) return $id;
    if ($matDoc !== '' && $matDoc !== $mat) { $id = acharMemorialPorMatricula($conn, $matDoc); if ($id) return $id; }
    $norm = function ($s) { return strtolower(preg_replace('/[^0-9a-zA-Z]/', '', (string)$s)); };
    $alvos = [];
    foreach ([$mat, $matDoc] as $a) { $n = $norm($a); if ($n !== '') $alvos[$n] = true; }
    if ($alvos) {
        $res = $conn->query("SELECT id, identificador FROM memoriais_mapeados WHERE identificador IS NOT NULL AND identificador <> ''");
        while ($res && ($row = $res->fetch_assoc())) { if (isset($alvos[$norm($row['identificador'])])) return (int)$row['id']; }
    }
    return null;
}

/* ====================================================================
 *  CICLO DE VIDA DA MATRÍCULA (encerramento / desmembramento) a partir do PDF
 * ==================================================================== */
/* Normaliza um número de matrícula (só dígitos) para comparação. */
function matNormalizar($s) { return preg_replace('/\D+/', '', (string)$s); }

/* Recebe array OU string e devolve a lista de matrículas (só dígitos), sem duplicatas. */
function matriculasLimpar($v) {
    $itens = [];
    if (is_array($v)) { foreach ($v as $x) $itens[] = (string)$x; }
    else { foreach (preg_split('/[;,\/]| e /', (string)$v) as $x) $itens[] = $x; }
    $out = [];
    foreach ($itens as $x) { $n = matNormalizar($x); if ($n !== '' && !isset($out[$n])) $out[$n] = $n; }
    return array_values($out);
}

/* Atualiza situação/motivo de um imóvel e MESCLA (união) as matrículas sucessoras. */
function atualizarSituacaoMerge($conn, $id, $situacao, $motivo, array $novasSuc) {
    $id = (int)$id; if ($id <= 0) return false;
    $rs = $conn->query("SELECT matricula_sucessora FROM memoriais_mapeados WHERE id = $id LIMIT 1");
    $row = $rs ? $rs->fetch_assoc() : null;
    if (!$row) return false;
    $suc = [];
    foreach (preg_split('/[;,]/', (string)($row['matricula_sucessora'] ?? '')) as $s) { $s = trim($s); $k = matNormalizar($s); if ($k !== '' && !isset($suc[$k])) $suc[$k] = $s; }
    foreach ($novasSuc as $s) { $s = trim((string)$s); $k = matNormalizar($s); if ($k !== '' && !isset($suc[$k])) $suc[$k] = $s; }
    $sucStr = implode(', ', array_values($suc));
    $st = $conn->prepare("UPDATE memoriais_mapeados SET situacao = ?, motivo_situacao = ?, matricula_sucessora = ? WHERE id = ?");
    if (!$st) return false;
    $st->bind_param('sssi', $situacao, $motivo, $sucStr, $id);
    return $st->execute();
}

/* Aplica o ciclo de vida extraído do PDF:
 *  (1) à PRÓPRIA matrícula: se há averbação de encerramento -> encerra (com motivo + sucessoras);
 *      se há desmembramento de parte (mãe segue ativa) -> marca desmembramento + matrículas originadas;
 *  (2) à matrícula ANTERIOR citada no registro de abertura -> marca nela a nova matrícula originada,
 *      encerrando-a (unificação/georref.) ou mantendo-a ativa (desmembramento), SE estiver cadastrada.
 * Retorna o que foi aplicado (para a mensagem de retorno). */
function aplicarCicloVida($conn, $idAtual, $matriculaAtual, $cv) {
    $res = ['self' => null, 'anterior' => null];
    if (!is_array($cv)) return $res;
    $matAtual     = trim((string)$matriculaAtual);
    $matAtualNorm = matNormalizar($matAtual);

    $encerrada = filter_var($cv['encerrada'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $motivo = strtolower(trim((string)($cv['motivo'] ?? '')));
    if (!in_array($motivo, ['unificacao','desmembramento','georreferenciamento'], true)) $motivo = '';
    $originou = matriculasLimpar($cv['originou_matriculas'] ?? []);
    $originou = array_values(array_filter($originou, fn($m) => $m !== $matAtualNorm)); // sem auto-referência

    // (1) própria matrícula
    if ((int)$idAtual > 0) {
        if ($encerrada) {
            $mot = $motivo ?: 'unificacao';
            atualizarSituacaoMerge($conn, $idAtual, 'encerrada', $mot, $originou);
            $res['self'] = ['situacao' => 'encerrada', 'motivo' => $mot, 'sucessora' => $originou];
        } elseif ($originou) {
            atualizarSituacaoMerge($conn, $idAtual, 'ativa', 'desmembramento', $originou);
            $res['self'] = ['situacao' => 'ativa', 'motivo' => 'desmembramento', 'sucessora' => $originou];
        }
    }

    // (2) matrícula anterior (origem desta), se cadastrada
    $matAnt  = matNormalizar($cv['registro_anterior_matricula'] ?? '');
    $tipoAnt = strtolower(trim((string)($cv['registro_anterior_tipo'] ?? '')));
    if ($tipoAnt === 'encerramento') $tipoAnt = 'unificacao';
    if (!in_array($tipoAnt, ['unificacao','desmembramento','georreferenciamento'], true)) $tipoAnt = '';
    if ($matAnt !== '' && $matAnt !== $matAtualNorm && $matAtual !== '') {
        $idAnt = acharMemorialPorMatricula($conn, $matAnt);
        if ($idAnt && (int)$idAnt !== (int)$idAtual) {
            $tipo = $tipoAnt ?: 'desmembramento'; // padrão conservador: mãe segue ativa
            if ($tipo === 'desmembramento') atualizarSituacaoMerge($conn, $idAnt, 'ativa', 'desmembramento', [$matAtual]);
            else                            atualizarSituacaoMerge($conn, $idAnt, 'encerrada', $tipo, [$matAtual]);
            $res['anterior'] = ['id' => (int)$idAnt, 'matricula' => $matAnt, 'tipo' => $tipo];
        }
    }
    return $res;
}

/* Monta um resumo legível do que o ciclo de vida aplicou (para a mensagem de retorno). */
function cicloVidaResumo($cv) {
    $p = [];
    if (!empty($cv['self'])) {
        $s = $cv['self'];
        if ($s['situacao'] === 'encerrada') $p[] = 'matrícula marcada como ENCERRADA (' . $s['motivo'] . ')' . ($s['sucessora'] ? ' → ' . implode(', ', $s['sucessora']) : '');
        else $p[] = 'desmembramento registrado → ' . implode(', ', $s['sucessora']);
    }
    if (!empty($cv['anterior'])) {
        $a = $cv['anterior'];
        $p[] = 'matrícula anterior ' . $a['matricula'] . ' atualizada (' . $a['tipo'] . ')';
    }
    return $p ? (' Ciclo de vida: ' . implode('; ', $p) . '.') : '';
}

/* Prompt de extração: pede JSON com as chaves = colunas do banco.
   IMPORTANTE: a titularidade é definida pela CADEIA de atos (Registros R-n e Averbações Av-n).
   O modelo deve percorrê-la em ordem e devolver os TITULARES ATUAIS com a qualificação completa. */
function geminiPromptMatricula() {
    return <<<'PROMPT'
Você é um extrator de dados de matrículas de imóveis de cartório de Registro de Imóveis do Brasil.
Leia a matrícula em PDF e devolva SOMENTE um objeto JSON (sem texto extra, sem markdown) com as chaves abaixo.
Use string vazia "" quando não encontrar; use [] para listas vazias. NUNCA invente dados.

COMO DETERMINAR OS TITULARES ATUAIS (regra mais importante):
- A matrícula tem o registro de abertura (R-0/R-1) e depois uma sequência de REGISTROS (R-2, R-3, ...) e AVERBAÇÕES (Av-1, Av-2, ...).
- Percorra TODOS os atos na ordem em que aparecem. Cada transmissão (compra e venda, doação, partilha por óbito/divórcio, dação, arrematação, integralização, usucapião, permuta, adjudicação etc.) ou instituição/extinção de direito real (usufruto, fideicomisso, superfície, promessa de compra e venda registrada etc.) ALTERA quem é o titular atual.
- O TITULAR ATUAL é definido pelo ÚLTIMO ato eficaz de cada direito. Atos CANCELADOS, baixados ou substituídos por averbação posterior NÃO valem — ignore o titular antigo quando houver transmissão posterior.
- Atente para averbações que cancelam registros (ex.: "Av-5 ... cancelo o R-3"), alteram estado civil/nome, ou registram divórcio/partilha que muda a titularidade ou o percentual.
- Quando houver mais de um titular atual (co-proprietários, nu-proprietário + usufrutuário, casal), retorne TODOS, cada um com seu direito e percentual/fração.

{
"nome_imo": denominação do imóvel (ex.: FAZENDA AGRO TRÊS IRMÃOS),
"numero_matricula": número da matrícula do imóvel, se constar (ex.: 'Matrícula do imóvel: 873' => 873),
"tipo_imovel": "rural" ou "urbano",
"imovel_uniao": true SE o imóvel pertencer à UNIÃO FEDERAL / domínio da União (ex.: proprietário 'União Federal', terras/terreno de marinha, domínio da União, SPU, INCRA como titular do domínio, terras devolutas federais) — senão false,
"dat_mat": data de abertura/registro da matrícula (dd/mm/aaaa),
"liv_mat": número do livro,
"fol_mat": número da folha/ficha,
"transcri": número de transcrição anterior, se houver,
"cnm": Código Nacional da Matrícula (CNM),
"cns": Código Nacional da Serventia (CNS), normalmente o prefixo do CNM,
"endereco": localização/endereço do imóvel,
"municipio": município do imóvel,
"uf": sigla do estado (2 letras),
"cep": CEP do imóvel (8 dígitos, só números); se não constar no documento, informe o CEP GERAL do município,
"proprietario": nome(s) do(s) TITULAR(es) ATUAL(is) conforme a regra acima; separe vários por vírgula,
"cpf": CPF ou CNPJ do(s) titular(es) atual(is), na MESMA ordem de "proprietario", separados por vírgula,
"rel_jur": relação jurídica do titular principal por extenso (ex.: propriedade, usufruto, nua-propriedade, promessa de compra e venda),
"dat_ini": data (dd/mm/aaaa) do ATO que originou a relação jurídica atual (a data do registro/averbação da última transmissão eficaz),
"per_rel": percentual/fração do titular principal (ex.: 100%, 50%, 1/2),
"pessoas": [
   // UMA entrada por TITULAR ATUAL. Liste todos. Cada objeto:
   {
     "nome": nome completo,
     "cpf_cnpj": CPF (11) ou CNPJ (14) — pode conter máscara,
     "estrangeiro": true se a nacionalidade NÃO for brasileira, senão false,
     "nacionalidade": nacionalidade por extenso (ex.: brasileira, argentina, portuguesa),
     "estado_civil": estado civil por extenso (solteiro, casado, separado, divorciado, viúvo, união estável),
     "regime_bens": regime de bens por extenso, se casado/união estável (ex.: comunhão parcial, comunhão universal, separação total/convencional, separação legal/obrigatória),
     "profissao": profissão, se constar,
     "rg": número do RG/identidade, se constar,
     "orgao_emissor": órgão emissor do RG (ex.: SSP/MA), se constar,
     "endereco": endereço/domicílio do titular, se constar,
     "relacao_juridica": direito real do titular por extenso (proprietário, usufrutuário, nu-proprietário, promitente comprador, fiduciário, superficiário, etc.),
     "data_inicio": data (dd/mm/aaaa) do ato que conferiu esse direito a este titular,
     "percentual": percentual numérico do direito (ex.: 100, 50, 33.33). Se não constar, divida igualmente entre os co-titulares,
     "condicao": "adquirente" (titular atual) ou "alienante" (transmitente anterior). Liste apenas titulares ATUAIS como "adquirente"
   }
],
"conf_nom": nomes dos confrontantes/limites separados por vírgula,
"conf_mat": matrículas confrontantes, se citadas, separadas por vírgula,
"ccir_sncr": número do CCIR,
"sigef": código de certificação SIGEF/INCRA, se houver,
"snci": número SNCI, se houver,
"cib_nirf": número CIB ou NIRF (Receita Federal),
"car": número do CAR (Cadastro Ambiental Rural),
"rip": número RIP, se houver,
"cif": número CIF, se houver,
"classifica": "1" se o imóvel for georreferenciado e CERTIFICADO pelo INCRA (ou urbano com ART), "2" se georreferenciado sem certificação, "3" se apenas desenho/sem georreferenciamento,
"onr_numero_prenotacao": número do PROTOCOLO ou da PRENOTAÇÃO da matrícula — procure por 'protocolo', 'prenotação' ou 'prenot' (ex.: 'sob protocolo n° 10.676' => 10676). Sempre existe; retorne apenas o número,
"ciclo_vida": {
   // EVENTOS de encerramento/desmembramento DESTA matrícula e a ORIGEM dela. Analise os REGISTROS (R-n) e AVERBAÇÕES (Av-n).
   "encerrada": true SE houver averbação que ENCERRA/CANCELA esta matrícula por completo (ex.: 'encerra-se a presente matrícula', unificação com outra(s), desmembramento TOTAL da área, ou georreferenciamento que abriu nova matrícula em substituição) — senão false,
   "motivo": SE encerrada, o motivo — 'unificacao' (unificada a outra matrícula), 'desmembramento' (toda a área foi desmembrada), 'georreferenciamento' (encerrada por georreferenciamento que originou nova matrícula) — senão "",
   "originou_matriculas": [matrículas NOVAS abertas A PARTIR DESTA, por encerramento total OU por desmembramento de parte (ex.: 'desmembrada a área tal, originando a matrícula 5.678'). SOMENTE os números, sem pontos],
   "registro_anterior_matricula": número da matrícula ANTERIOR da qual ESTA se originou, citada no registro de abertura/R-1 (ex.: 'imóvel havido por desmembramento da matrícula 1.234' => 1234). Apenas o número. Senão "",
   "registro_anterior_tipo": como ESTA se originou da anterior — 'desmembramento', 'unificacao' ou 'georreferenciamento' — senão ""
},
"memorial": transcreva a descrição do perímetro com TODOS os vértices e coordenadas, EXATAMENTE como no documento. Pode ser: (a) texto corrido começando em 'Inicia-se a descrição...'; (b) coordenadas UTM 'E ... m' e 'N ... m' em metros; ou (c) uma TABELA do SIGEF/INCRA com colunas Código, Longitude, Latitude — neste caso transcreva cada linha mantendo a Longitude e a Latitude (ex.: 'D6B-M-10902 -46°51'49,039" -4°05'50,116"'). Inclua todos os vértices; não converta, não arredonde, não omita nenhum
}
PROMPT;
}
/* Chama a API do Gemini com o PDF e retorna os dados extraídos. */
function geminiExtrairMatricula($cfg, $pdfBytes) {
    if (trim($cfg['api_key']) === '') return ['ok' => false, 'erro' => 'Chave da API do Gemini não configurada.'];
    $model = $cfg['default_model'];
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($cfg['api_key']);
    $payload = [
        'contents' => [['parts' => [
            ['text' => geminiPromptMatricula()],
            ['inline_data' => ['mime_type' => 'application/pdf', 'data' => base64_encode($pdfBytes)]],
        ]]],
        'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json'],
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 180,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($resp === false) return ['ok' => false, 'erro' => 'Falha de conexão com o Gemini: ' . $err];
    $j = json_decode($resp, true);
    if ($code < 200 || $code >= 300) {
        $msg = $j['error']['message'] ?? ('HTTP ' . $code);
        return ['ok' => false, 'erro' => 'Gemini: ' . $msg];
    }
    $txt = $j['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $txt = trim(preg_replace('/```json|```/i', '', $txt));
    $dados = json_decode($txt, true);
    if (!is_array($dados)) return ['ok' => false, 'erro' => 'A IA não retornou um JSON válido.'];
    return ['ok' => true, 'dados' => $dados, 'modelo' => $model];
}
/* ===================== QUALIFICAÇÃO DOS TITULARES (registros/averbações) =====================
   A IA devolve "pessoas": lista de TITULARES ATUAIS com qualificação. Guardamos o array bruto em
   qualificacao_json e derivamos as colunas planas (proprietario/cpf/rel_jur/dat_ini/per_rel) para
   manter a compatibilidade com o mapa/InfoWindow. A carga ITN 03 (itn03ImovelDaLinha) lê este JSON
   e faz o mapeamento texto -> enum no momento da exportação. */

/* Estado civil por extenso -> código (glossário ITN 03). Default 1 (solteiro). */
function itn03MapEstadoCivil($s) {
    $t = itn03NormNome($s);
    if ($t === '') return 1;
    if (strpos($t, 'uniao') !== false || strpos($t, 'convivente') !== false) return 6; // união estável
    if (strpos($t, 'cas') !== false)                                          return 2; // casado(a)
    if (strpos($t, 'divorc') !== false)                                       return 4;
    if (strpos($t, 'separ') !== false)                                        return 3;
    if (strpos($t, 'viuv') !== false || strpos($t, 'viuvo') !== false)        return 5;
    if (strpos($t, 'solt') !== false)                                         return 1;
    return 1;
}
/* Regime de bens por extenso -> código (glossário ITN 03). Default 1 (comunhão parcial). */
function itn03MapRegimeBens($s) {
    $t = itn03NormNome($s);
    if ($t === '') return 1;
    if (strpos($t, 'universal') !== false)                                  return 2;
    if (strpos($t, 'obrigat') !== false || strpos($t, 'legal') !== false)   return 4; // separação legal/obrigatória
    if (strpos($t, 'separ') !== false)                                      return 3; // separação convencional/absoluta
    if (strpos($t, 'aquest') !== false || strpos($t, 'participacao') !== false) return 5;
    if (strpos($t, 'estrangeir') !== false)                                 return 7;
    if (strpos($t, 'pacto') !== false || strpos($t, 'misto') !== false)     return 6;
    if (strpos($t, 'parcial') !== false || strpos($t, 'comunhao') !== false) return 1;
    return 1;
}
/* Relação jurídica por extenso -> código (glossário ITN 03). Default 1 (proprietário). */
function itn03MapRelacaoJuridica($s) {
    $t = itn03NormNome($s);
    if ($t === '') return 1;
    if (strpos($t, 'nu') === 0 || strpos($t, 'nu propriet') !== false || strpos($t, 'nua propriedade') !== false) return 3;
    if (strpos($t, 'usufrut') !== false)                                    return 2;
    if (strpos($t, 'promitente') !== false || strpos($t, 'promessa') !== false || strpos($t, 'compromiss') !== false) return 12;
    if (strpos($t, 'fiduciante') !== false)                                 return 8;
    if (strpos($t, 'fiduciari') !== false)                                  return 9;
    if (strpos($t, 'superfici') !== false)                                  return 7;
    if (strpos($t, 'arrendante') !== false)                                 return 10;
    if (strpos($t, 'arrendatari') !== false)                                return 11;
    if (strpos($t, 'multipropriet') !== false)                              return 13;
    if (strpos($t, 'parceir') !== false)                                    return 14;
    if (strpos($t, 'enfiteut') !== false)                                   return 17;
    if (strpos($t, 'habitad') !== false || strpos($t, 'habitac') !== false) return 5;
    if (strpos($t, 'usuari') !== false)                                     return 4;
    if (strpos($t, 'propriet') !== false || strpos($t, 'propriedade') !== false) return 1;
    return 1;
}
/* Extrai dígitos de percentual ("33,33%" / "1/2" -> número). Retorna float ou null. */
function itn03PercentualNum($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    if (preg_match('#(\d+)\s*/\s*(\d+)#', $s, $m) && (float)$m[2] != 0) return round(100 * (float)$m[1] / (float)$m[2], 2);
    $s = str_replace(['%', ' '], '', $s);
    $s = str_replace('.', '', $s);   // separador de milhar eventual
    $s = str_replace(',', '.', $s);  // vírgula decimal -> ponto
    return is_numeric($s) ? round((float)$s, 2) : null;
}
/* Normaliza a lista "pessoas" da IA num array consistente (mantém texto + flags). */
function qualificacaoNormalizar($pessoas) {
    if (!is_array($pessoas)) return [];
    $out = [];
    foreach ($pessoas as $p) {
        if (!is_array($p)) continue;
        $nome = trim((string)($p['nome'] ?? $p['nome_completo'] ?? ''));
        $doc  = itn03Dig($p['cpf_cnpj'] ?? ($p['cpf'] ?? ''));
        if ($nome === '' && $doc === '') continue;
        $estr = $p['estrangeiro'] ?? null;
        if (is_string($estr)) $estr = in_array(strtolower(trim($estr)), ['1','true','sim','yes'], true);
        $nac  = trim((string)($p['nacionalidade'] ?? ''));
        if ($estr === null) $estr = ($nac !== '' && itn03NormNome($nac) !== 'brasileira' && itn03NormNome($nac) !== 'brasileiro' && itn03NormNome($nac) !== 'brasil');
        $out[] = [
            'nome'          => $nome,
            'cpf_cnpj'      => $doc,
            'estrangeiro'   => (bool)$estr,
            'nacionalidade' => $nac,
            'estado_civil'  => trim((string)($p['estado_civil'] ?? '')),
            'regime_bens'   => trim((string)($p['regime_bens'] ?? '')),
            'profissao'     => trim((string)($p['profissao'] ?? '')),
            'rg'            => trim((string)($p['rg'] ?? '')),
            'orgao_emissor' => trim((string)($p['orgao_emissor'] ?? '')),
            'endereco'      => trim((string)($p['endereco'] ?? '')),
            'relacao_juridica' => trim((string)($p['relacao_juridica'] ?? ($p['relacao'] ?? ''))),
            'data_inicio'   => trim((string)($p['data_inicio'] ?? ($p['data'] ?? ''))),
            'percentual'    => trim((string)($p['percentual'] ?? '')),
            'condicao'      => trim((string)($p['condicao'] ?? '')),
        ];
    }
    return $out;
}
/* A partir das pessoas, preenche colunas planas vazias (proprietario/cpf/rel_jur/dat_ini/per_rel). */
function qualificacaoDerivarFlat(array &$d, array $pessoas) {
    if (!$pessoas) return;
    // Apenas titulares atuais (descarta explicitamente "alienante").
    $atuais = array_values(array_filter($pessoas, fn($p) => itn03NormNome($p['condicao']) !== 'alienante'));
    if (!$atuais) $atuais = $pessoas;
    $nomes = array_values(array_filter(array_map(fn($p) => $p['nome'], $atuais)));
    $docs  = array_values(array_filter(array_map(fn($p) => $p['cpf_cnpj'], $atuais)));
    if ($nomes && trim((string)($d['proprietario'] ?? '')) === '') $d['proprietario'] = implode(', ', $nomes);
    if ($docs  && trim((string)($d['cpf'] ?? '')) === '')          $d['cpf']          = implode(', ', $docs);
    $p0 = $atuais[0];
    if (trim((string)($d['rel_jur'] ?? '')) === '' && $p0['relacao_juridica'] !== '') $d['rel_jur'] = $p0['relacao_juridica'];
    if (trim((string)($d['dat_ini'] ?? '')) === '' && $p0['data_inicio'] !== '')      $d['dat_ini'] = $p0['data_inicio'];
    if (trim((string)($d['per_rel'] ?? '')) === '' && $p0['percentual'] !== '')       $d['per_rel'] = $p0['percentual'];
}
/* Grava o JSON de qualificação na coluna qualificacao_json (prepared). */
function qualificacaoGravar($conn, $id, array $pessoas) {
    $id = (int)$id; if ($id <= 0) return false;
    $json = $pessoas ? json_encode($pessoas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $st = $conn->prepare("UPDATE memoriais_mapeados SET qualificacao_json = ? WHERE id = ?");
    if (!$st) return false;
    $st->bind_param('si', $json, $id);
    return $st->execute();
}
/* Ponto único: normaliza pessoas, deriva colunas planas em $d e persiste o JSON. */
function aplicarQualificacao($conn, $id, array &$d) {
    if (!array_key_exists('pessoas', $d)) return;
    $pessoas = qualificacaoNormalizar($d['pessoas']);
    qualificacaoDerivarFlat($d, $pessoas);
    qualificacaoGravar($conn, $id, $pessoas);
}

/* Aplica os dados extraídos ao imóvel (campos ONR + base). Não sobrescreve a geometria. */
function aplicarDadosMatricula($conn, $id, $d) {
    aplicarQualificacao($conn, $id, $d); // persiste qualificação + deriva proprietario/cpf/rel_jur/dat_ini/per_rel
    salvarCamposOnr($conn, $id, $d); // colunas ONR (nomes iguais às chaves)
    $sets = []; $vals = []; $types = '';
    $base = ['identificador', 'proprietario', 'cpf', 'tipo_imovel'];
    foreach ($base as $col) {
        $k = $col;
        if ($col === 'identificador' && (!isset($d['identificador']) || trim((string)$d['identificador']) === '')) $k = 'nome_imo';
        if (!isset($d[$k]) || trim((string)$d[$k]) === '') continue;
        $v = trim((string)$d[$k]);
        if ($col === 'tipo_imovel') { $v = (stripos($v, 'rural') !== false) ? 'rural' : ((stripos($v, 'urban') !== false) ? 'urbano' : ''); if ($v === '') continue; }
        $sets[] = "`$col` = ?"; $vals[] = $v; $types .= 's';
    }
    if ($sets) { $vals[] = $id; $types .= 'i'; $st = $conn->prepare("UPDATE memoriais_mapeados SET " . implode(', ', $sets) . " WHERE id = ?"); $st->bind_param($types, ...$vals); $st->execute(); }
    return true;
}

/* Preenche APENAS os campos vazios do imóvel a partir de $d (mesma lógica do modal anexo_analisar):
   só toca colunas que existem na linha e estão vazias — nunca sobrescreve nem referencia coluna
   inexistente (evita erro de SQL/exceção mysqli). Retorna a lista de campos preenchidos. */
function complementarMatricula($conn, $id, array $d, array $rowAtual) {
    $id = (int)$id; if ($id <= 0) return [];
    $pessoas = qualificacaoNormalizar($d['pessoas'] ?? []);
    qualificacaoDerivarFlat($d, $pessoas);
    $whitelist = array_merge(onrCampos(), ['proprietario', 'cpf', 'tipo_imovel', 'identificador', 'numero_matricula', 'contexto_rural']);
    $sets = []; $vals = []; $types = ''; $preenchidos = [];
    foreach ($whitelist as $col) {
        if (!array_key_exists($col, $rowAtual)) continue;                 // coluna precisa existir na tabela
        $k = $col;
        if ($col === 'identificador' && trim((string)($d['identificador'] ?? '')) === '') $k = 'nome_imo';
        if (!array_key_exists($k, $d)) continue;
        $v = is_scalar($d[$k]) ? trim((string)$d[$k]) : '';
        if ($v === '') continue;
        if ($col === 'tipo_imovel') { $v = (stripos($v, 'rural') !== false) ? 'rural' : ((stripos($v, 'urban') !== false) ? 'urbano' : ''); if ($v === '') continue; }
        if (trim((string)$rowAtual[$col]) !== '') continue;              // já preenchido -> respeita
        $sets[] = "`$col` = ?"; $vals[] = $v; $types .= 's'; $preenchidos[] = $col;
    }
    if ($sets) {
        $vals[] = $id; $types .= 'i';
        $st = $conn->prepare("UPDATE memoriais_mapeados SET " . implode(', ', $sets) . " WHERE id = ?");
        if ($st) { $st->bind_param($types, ...$vals); $st->execute(); }
    }
    if ($pessoas && trim((string)($rowAtual['qualificacao_json'] ?? '')) === '') {
        qualificacaoGravar($conn, $id, $pessoas); $preenchidos[] = 'qualificacao_json';
    }
    return array_values(array_unique($preenchidos));
}

/* ====================================================================
 *  CONSULTA DE CEP (ViaCEP)
 * ==================================================================== */
function cepNormalizar($cep) { $d = preg_replace('/\D/', '', (string)$cep); return strlen($d) === 8 ? $d : ''; }
function cepConsultar($cep) {
    $c = cepNormalizar($cep);
    if ($c === '') return null;
    $ch = curl_init('https://viacep.com.br/ws/' . $c . '/json/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($resp === false || $code < 200 || $code >= 300) return null;
    $j = json_decode((string)$resp, true);
    if (!is_array($j) || !empty($j['erro'])) return null;
    return [
        'cep'        => $c,
        'logradouro' => $j['logradouro'] ?? '',
        'bairro'     => $j['bairro'] ?? '',
        'municipio'  => $j['localidade'] ?? '',
        'uf'         => $j['uf'] ?? '',
    ];
}
/* Normaliza o CEP extraído e, se válido, completa município/UF/endereço vazios. */
function enriquecerCepExtraido(&$d) {
    $cep = cepNormalizar($d['cep'] ?? '');
    if ($cep === '') { return; }
    $d['cep'] = $cep;
    $info = cepConsultar($cep);
    if (!$info) return;
    if (trim((string)($d['municipio'] ?? '')) === '' && $info['municipio'] !== '') $d['municipio'] = $info['municipio'];
    if (trim((string)($d['uf'] ?? '')) === '' && $info['uf'] !== '') $d['uf'] = $info['uf'];
    if (trim((string)($d['endereco'] ?? '')) === '' && $info['logradouro'] !== '') $d['endereco'] = $info['logradouro'];
}

/* ======================= EXPORTADOR DE CARGA ITN 03 (ONR, schema v1.2.0) =======================
   Gera a carga JSON validável: { version, cns, imoveis:[...] }. Urbano (tipo_imovel=1) e rural (=2)
   usam SCHEMAS DIFERENTES, então a carga é separada por tipo. Campos obrigatórios ausentes recebem
   padrões válidos e são registrados em $avisos para a serventia completar antes do envio ao ONR. */
function itn03UfCod($uf2) {
    $m = ['RO'=>11,'AC'=>12,'AM'=>13,'RR'=>14,'PA'=>15,'AP'=>16,'TO'=>17,'MA'=>21,
          'PI'=>22,'CE'=>23,'RN'=>24,'PB'=>25,'PE'=>26,'AL'=>27,'SE'=>28,'BA'=>29,
          'MG'=>31,'ES'=>32,'RJ'=>33,'SP'=>35,'PR'=>41,'SC'=>42,'RS'=>43,'MS'=>50,
          'MT'=>51,'GO'=>52,'DF'=>53];
    return $m[strtoupper(trim((string)$uf2))] ?? null;
}
function itn03DataBr($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $s)) return $s;
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $s, $m)) return "$m[3]/$m[2]/$m[1]";
    return null;
}
function itn03Dig($s) { return preg_replace('/\D+/', '', (string)$s); }
function itn03NormNome($s) {
    $s = function_exists('mb_strtolower') ? mb_strtolower(trim((string)$s), 'UTF-8') : strtolower(trim((string)$s));
    $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c',
                    'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}
/* resolve o código IBGE (7 díg.) do município a partir do nome + UF, com cache em arquivo. */
function itn03MunicipioCod($uf2, $nome) {
    static $cache = [];
    $uf2 = strtoupper(trim((string)$uf2));
    $alvo = itn03NormNome($nome);
    if ($uf2 === '' || $alvo === '') return null;
    if (!isset($cache[$uf2])) {
        $cache[$uf2] = [];
        $dir = __DIR__ . '/data'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $cf = $dir . '/ibge_mun_' . $uf2 . '.json';
        $arr = null;
        if (is_file($cf)) { $arr = json_decode((string)@file_get_contents($cf), true); }
        if (!is_array($arr)) {
            $err = '';
            $url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/' . rawurlencode($uf2) . '/municipios?orderBy=nome';
            $txt = function_exists('httpGetText') ? httpGetText($url, $err) : @file_get_contents($url);
            $arr = $txt ? json_decode($txt, true) : null;
            if (is_array($arr)) @file_put_contents($cf, json_encode($arr, JSON_UNESCAPED_UNICODE));
        }
        if (is_array($arr)) foreach ($arr as $m) {
            if (isset($m['id'], $m['nome'])) $cache[$uf2][itn03NormNome($m['nome'])] = (int)$m['id'];
        }
    }
    return $cache[$uf2][$alvo] ?? null;
}
/* mapeia uma linha de memoriais_mapeados -> objeto "imóvel" da ITN 03; acumula avisos por imóvel. */
/* Detecta se algum titular é a UNIÃO FEDERAL (CNPJ 00.394.411/... ou nome "União Federal"). */
function itn03EhUniaoTitulares($pessoas) {
    foreach ((array)$pessoas as $p) {
        $doc = itn03Dig($p['cpf_cnpj'] ?? '');
        if (strlen($doc) === 14 && strpos($doc, '00394411') === 0) return true; // CNPJ da União
        $nome = itn03NormNome($p['nome'] ?? '');
        if (strpos($nome, 'uniao federal') !== false) return true;
    }
    return false;
}

/* Detecta o contexto da carga ITN 03 para imóvel rural a partir dos dados extraídos:
 *  '2' = Imóvel da União; '3' = Estrangeiros; '' = padrão/auto (contexto 1). */
function itn03ContextoRuralDetectar($d) {
    $uniao = filter_var($d['imovel_uniao'] ?? false, FILTER_VALIDATE_BOOLEAN) || itn03EhUniaoTitulares($d['pessoas'] ?? []);
    if ($uniao) return '2';
    foreach ((array)($d['pessoas'] ?? []) as $p) { if (!empty($p['estrangeiro'])) return '3'; }
    return '';
}

function itn03ImovelDaLinha(array $r, array &$avisos) {
    $rotulo = trim((string)($r['numero_matricula'] ?? '')) ?: trim((string)($r['identificador'] ?? '')) ?: ('#' . ($r['id'] ?? '?'));
    $cls = strtolower(trim((string)($r['classifica'] ?? '')));
    $tip = strtolower(trim((string)($r['tipo_imovel'] ?? '')));
    $eh_rural = false;
    if ($cls === 'r' || strpos($tip, 'rural') !== false) $eh_rural = true;
    if ($cls === 'u' || strpos($tip, 'urb') !== false)  $eh_rural = false;

    $uf2   = strtoupper(trim((string)($r['uf'] ?? '')));
    $ufCod = itn03UfCod($uf2);
    if (!$ufCod) { $avisos[] = "$rotulo: UF ausente/ inválida (usado 0 — corrigir)."; $ufCod = 0; }
    $munCod = itn03MunicipioCod($uf2, $r['municipio'] ?? '');
    if (!$munCod) { $avisos[] = "$rotulo: código IBGE do município não resolvido (usado 0 — preencher)."; $munCod = 0; }

    $geo    = trim((string)($r['coordenadas_wgs84'] ?? '')) !== '';
    $cnm    = trim((string)($r['cnm'] ?? ''));
    if (!preg_match('#^(?:\d{6}\.\d\.\d{7}-\d{2}|\d{16})$#', $cnm)) { $avisos[] = "$rotulo: CNM ausente/ inválido (obrigatório — preencher)."; }
    $numMat = trim((string)($r['numero_matricula'] ?? ''));
    $dataMat= itn03DataBr($r['dat_mat'] ?? '') ?: '01/01/2024';
    $nomeImo= trim((string)($r['nome_imo'] ?? ''));
    $sigef  = trim((string)($r['sigef'] ?? ''));
    $certif = (bool)preg_match('#^[A-Za-z0-9]{32}$#', $sigef);

    $di = [
        'logradouro' => (trim((string)($r['endereco'] ?? '')) !== '') ? trim((string)$r['endereco']) : 'Não informado',
        'cep'        => itn03Dig($r['cep'] ?? '') ?: '00000000',
        'cod_ibge_municipio' => $munCod,
        'uf'         => $ufCod,
    ];
    if (!$eh_rural) {
        $di['tipo_logradouro']   = 250;
        $di['numero_logradouro'] = (trim((string)($r['numero_imovel'] ?? '')) !== '') ? trim((string)$r['numero_imovel']) : 'S/N';
        if (isset($r['area_ha']) && $r['area_ha'] !== null) $di['area_m2'] = round(((float)$r['area_ha']) * 10000, 2);
    }

    // ---- Titulares ATUAIS: prioriza a qualificação estruturada (lida de registros/averbações) ----
    $qual = [];
    $rawQ = trim((string)($r['qualificacao_json'] ?? ''));
    if ($rawQ !== '') { $tmp = json_decode($rawQ, true); if (is_array($tmp)) $qual = $tmp; }
    if ($qual) { // descarta alienantes, se marcados
        $qa = array_values(array_filter($qual, fn($p) => itn03NormNome($p['condicao'] ?? '') !== 'alienante'));
        if ($qa) $qual = $qa;
    }
    $relJurTxt = trim((string)($r['rel_jur'] ?? ''));
    $datIniCol = itn03DataBr($r['dat_ini'] ?? '') ?: $dataMat;

    $pessoas = []; $temEstrangeiro = false;
    if ($qual) {
        $np = max(1, count($qual));
        foreach ($qual as $p) {
            $nome = trim((string)($p['nome'] ?? '')) ?: 'Proprietário não informado';
            $doc  = itn03Dig($p['cpf_cnpj'] ?? '');
            if ($doc === '' || (strlen($doc) !== 11 && strlen($doc) !== 14)) { $doc = '00000000000'; $avisos[] = "$rotulo: CPF/CNPJ de \"$nome\" ausente/ inválido (preencher)."; }
            $estr = !empty($p['estrangeiro']); if ($estr) $temEstrangeiro = true;
            $ec   = itn03MapEstadoCivil($p['estado_civil'] ?? '');
            $perc = itn03PercentualNum($p['percentual'] ?? ''); if ($perc === null) $perc = round(100 / $np, 2);
            $dini = itn03DataBr($p['data_inicio'] ?? '') ?: $datIniCol;
            $rel  = itn03MapRelacaoJuridica($p['relacao_juridica'] ?? $relJurTxt);
            $cond = (itn03NormNome($p['condicao'] ?? '') === 'alienante') ? 1 : 2;
            $pess = [
                'nome_completo' => $nome, 'cpf_cnpj' => $doc, 'estrangeiro' => $estr,
                'estado_civil' => $ec, 'condicao_parte' => $cond, 'relacao_juridica' => $rel,
                'data_inicio_rel_juridica' => $dini, 'percentual' => $perc,
            ];
            if ($ec === 2 || $ec === 6) { // casado/união estável => regime de bens obrigatório
                $pess['regime_bens'] = itn03MapRegimeBens($p['regime_bens'] ?? '');
                if (trim((string)($p['regime_bens'] ?? '')) === '') $avisos[] = "$rotulo: regime de bens de \"$nome\" não informado (usado comunhão parcial — conferir).";
            }
            if ($estr) {
                $pess['filhos_brasileiros'] = 3; // 3: não informado (preencher)
                $nacCod = itn03Dig($p['nacionalidade'] ?? '');
                if ($nacCod !== '') $pess['nacionalidade'] = (int)$nacCod;
                else $avisos[] = "$rotulo: nacionalidade (cód. IBGE do país) de \"$nome\" não informada (obrigatória p/ estrangeiro — preencher).";
            }
            $pessoas[] = $pess;
        }
    } else {
        // Fallback: colunas planas proprietario/cpf (sem qualificação estruturada disponível)
        $nomes = array_values(array_filter(array_map('trim', preg_split('/[;,]/', (string)($r['proprietario'] ?? '')))));
        $cpfs  = array_values(array_filter(array_map('trim', preg_split('/[;,]/', (string)($r['cpf'] ?? '')))));
        if (!$nomes) { $nomes = ['Proprietário não informado']; $avisos[] = "$rotulo: proprietário não informado (preencher)."; }
        $np = count($nomes);
        $relCod = itn03MapRelacaoJuridica($relJurTxt);
        $perCol = ($np === 1) ? itn03PercentualNum($r['per_rel'] ?? '') : null; // per_rel = % do principal; com vários, divide igualmente
        foreach ($nomes as $i => $nome) {
            $doc = itn03Dig($cpfs[$i] ?? ($cpfs[0] ?? ''));
            if ($doc === '' || (strlen($doc) !== 11 && strlen($doc) !== 14)) { $doc = '00000000000'; $avisos[] = "$rotulo: CPF/CNPJ de \"$nome\" ausente/ inválido (preencher)."; }
            $pessoas[] = [
                'nome_completo' => $nome, 'cpf_cnpj' => $doc, 'estrangeiro' => false,
                'estado_civil' => 1, 'condicao_parte' => 2, 'relacao_juridica' => $relCod,
                'data_inicio_rel_juridica' => $datIniCol,
                'percentual' => $perCol !== null ? $perCol : round(100 / $np, 2),
            ];
        }
    }

    // Contexto rural: valor PERSISTIDO (1/2/3) tem prioridade; senão autodetecta.
    //   1 = padrão · 2 = Imóvel da União · 3 = Estrangeiros. Urbano usa 1 (União não autodetectada).
    $ctxPersist = trim((string)($r['contexto_rural'] ?? ''));
    if ($eh_rural && in_array($ctxPersist, ['1', '2', '3'], true)) {
        $contextoVal = (int)$ctxPersist;
    } elseif ($eh_rural) {
        $ehUniao = itn03EhUniaoTitulares($qual)
                || itn03EhUniaoTitulares([['nome' => $r['proprietario'] ?? '', 'cpf_cnpj' => $r['cpf'] ?? '']]);
        $contextoVal = $ehUniao ? 2 : ($temEstrangeiro ? 3 : 1);
    } else {
        $contextoVal = 1;
    }
    if ($eh_rural && $contextoVal === 2) {
        $avisos[] = "$rotulo: classificado como IMÓVEL DA UNIÃO (contexto rural 2) — confira os campos específicos exigidos pela ITN 03 para imóveis da União antes de enviar.";
    }

    $imovel = [
        'tipo_imovel' => $eh_rural ? 2 : 1,
        ($eh_rural ? 'contexto_rural' : 'contexto_urbano') => $contextoVal,
        'motivo_envio' => 2,
        'georreferenciamento' => $geo,
        'tipo_matricula_transcricao' => 1,
        'numero_matricula' => $numMat !== '' ? $numMat : '0',
        'data_matricula' => $dataMat,
        'situacao' => 1,
        'cnm' => $cnm,
        'tipo_ato' => 1, 'numero_ato' => '1', 'ato' => 1, 'data_ato' => $dataMat,
        'certificacao_incra' => $certif,
        'dados_imovel' => [$di],
        'dados_pessoa' => $pessoas,
    ];
    $pren = itn03Dig($r['onr_numero_prenotacao'] ?? '');
    if ($pren !== '' && (int)$pren !== 0) {
        $imovel['protocolo_prenotacao'] = (int)$pren;
        $imovel['data_protocolo_prenotacao'] = $dataMat;
    } else {
        $imovel['protocolo_prenotacao'] = null;       // 0 ou em branco => null
        $imovel['data_protocolo_prenotacao'] = null;
    }

    if ($eh_rural) {
        // Campos EXCLUSIVOS do contexto 3 (Imóvel Rural de Estrangeiros) — não enviar em imóvel padrão.
        if ($contextoVal === 3) {
            $imovel['autorizacao_incra'] = false;
            $imovel['faixa_fronteira']   = false;
            $imovel['area_sn']           = false;
        }
        $imovel['imovel_possui_nome']= ($nomeImo !== '');
        if ($nomeImo !== '') $imovel['nome_imovel'] = $nomeImo;
        $imovel['area_terreno_total'] = ['valor' => round((float)($r['area_ha'] ?? 0), 4), 'unidade' => 2];
        if ($certif) $imovel['codigo_incra'] = $sigef;
        $cod = itn03Dig($r['ccir_sncr'] ?? '');
        $imovel['cod_sncr'] = preg_match('#^(?:\d{12}|\d{13})$#', $cod) ? $cod : '000000000000';
        if (preg_match('#^\d{11}$#', $cod)) $imovel['ccir'] = $cod;
        $car = trim((string)($r['car'] ?? ''));
        $carNorm = preg_replace('/[.\s]/', '', $car); // hash vem agrupado por pontos -> torna contíguo
        if (preg_match('#^(?:[A-Za-z]{2}-\d{7}-[A-Za-z0-9]{32}|[A-Za-z0-9]{41})$#', $carNorm)) {
            $imovel['car'] = $carNorm;
        } else {
            $uc = preg_match('#^[A-Za-z]{2}$#', $uf2) ? $uf2 : 'XX';
            $imovel['car'] = $uc . '-0000000-' . str_repeat('0', 32);
            $avisos[] = "$rotulo: CAR ausente/ inválido (usado placeholder — preencher).";
        }
    } else {
        $cif = trim((string)($r['cif'] ?? ''));
        $imovel['cif'] = $cif !== '' ? $cif : '0';
        if ($cif === '') $avisos[] = "$rotulo: CIF (Cadastro Imobiliário Fiscal) ausente (usado 0 — preencher).";
    }
    $cib = trim((string)($r['cib_nirf'] ?? ''));
    if (preg_match('#^(?:[A-Za-z0-9]{8}|[A-Za-z0-9]{7}-[A-Za-z0-9])$#', $cib)) $imovel['cib'] = $cib;

    $imovel['coordenadas'] = ''; // por padrão em branco (geometria enviada por outra via)
    $imovel['__tipo'] = $eh_rural ? 'rural' : 'urbano';
    return $imovel;
}
/* monta os arquivos de carga (1 por tipo presente) a partir de uma lista de linhas. */
function itn03GerarArquivos(array $linhas) {
    $avisos = []; $porTipo = ['urbano' => [], 'rural' => []]; $cnsCont = [];
    foreach ($linhas as $r) {
        $im = itn03ImovelDaLinha($r, $avisos);
        $tipo = $im['__tipo']; unset($im['__tipo']);
        $porTipo[$tipo][] = $im;
        $c = trim((string)($r['cns'] ?? '')); if ($c !== '') $cnsCont[$c] = ($cnsCont[$c] ?? 0) + 1;
    }
    $cns = '000000';
    if ($cnsCont) { arsort($cnsCont); $cns = (string)array_key_first($cnsCont); }
    if (!preg_match('#^(?:\d{6}|\d{5}-\d)$#', $cns)) { $cns = '000000'; $avisos[] = "CNS da serventia ausente/ inválido (usado 000000 — configurar)."; }
    $arquivos = [];
    foreach (['urbano', 'rural'] as $tipo) {
        if (!$porTipo[$tipo]) continue;
        $carga = ['version' => '1.2.0', 'cns' => $cns, 'imoveis' => $porTipo[$tipo]];
        $plural = ($tipo === 'rural') ? 'rurais' : 'urbanos';
        $arquivos[] = [
            'tipo' => $tipo, 'n' => count($porTipo[$tipo]),
            'nome' => 'carga_itn03_' . $plural . '.json',
            'conteudo' => json_encode($carga, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
    return ['arquivos' => $arquivos, 'avisos' => array_values(array_unique($avisos))];
}
function itn03SelectCols() {
    return "id, identificador, numero_matricula, cnm, cns, nome_imo, dat_mat, liv_mat, fol_mat,
            tipo_imovel, classifica, endereco, numero_imovel, cep, municipio, uf,
            proprietario, cpf, dat_ini, rel_jur, per_rel, qualificacao_json,
            area_ha, sigef, ccir_sncr, car, cib_nirf, cif,
            onr_nivel_publicidade, onr_classificacao, onr_numero_prenotacao, onr_descricao,
            itn03_exclusivo, fora_municipio, contexto_rural, coordenadas_wgs84";
}
/* "apto para o Mapa da ONR" (mesmo critério do onr_pronto): se está pronto p/ o Mapa, está pronto p/ a carga ITN 03. */
/* Imóvel marcado como FORA do perímetro do município (não pertence ao cartório). */
function imovelForaMunicipio($r) { return trim((string)($r['fora_municipio'] ?? '')) !== ''; }

function itn03Faltam(array $r) {
    $faltam = [];
    if (imovelForaMunicipio($r)) $faltam[] = 'imóvel fora do município';
    if (!in_array((string)($r['tipo_imovel'] ?? ''), ['urbano','rural'], true)) $faltam[] = 'tipo (urbano/rural)';
    if (trim((string)($r['onr_nivel_publicidade'] ?? '')) === '') $faltam[] = 'nível de publicidade';
    if (trim((string)($r['onr_classificacao'] ?? '')) === '')     $faltam[] = 'classificação da importação';
    if (trim((string)($r['onr_numero_prenotacao'] ?? '')) === '')  $faltam[] = 'número da prenotação';
    if (trim((string)($r['onr_descricao'] ?? '')) === '')          $faltam[] = 'descrição';
    return $faltam;
}
function itn03Apto(array $r) { return count(itn03Faltam($r)) === 0; }
/* Aptidão das matrículas EXCLUSIVAS da ITN 03 (sem mapa): só o mínimo que a carga exige. */
function itn03ExclusivoFaltam(array $r) {
    $faltam = [];
    if (imovelForaMunicipio($r)) $faltam[] = 'imóvel fora do município';
    if (!in_array((string)($r['tipo_imovel'] ?? ''), ['urbano','rural'], true)) $faltam[] = 'tipo (urbano/rural)';
    if (trim((string)($r['numero_matricula'] ?? '')) === '') $faltam[] = 'número da matrícula';
    if (!preg_match('#^(?:\d{6}\.\d\.\d{7}-\d{2}|\d{16})$#', trim((string)($r['cnm'] ?? '')))) $faltam[] = 'CNM válido';
    if (trim((string)($r['municipio'] ?? '')) === '') $faltam[] = 'município';
    if (trim((string)($r['uf'] ?? '')) === '') $faltam[] = 'UF';
    return $faltam;
}
function itn03ExclusivoApto(array $r) { return count(itn03ExclusivoFaltam($r)) === 0; }

if (isset($_POST['acao'])) {

    /* ----- Relatório de sobreposição em PDF (saída binária, antes do header JSON) ----- */
    if ($_POST['acao'] === 'relatorio_sobreposicao') {
        $dados = json_decode(isset($_POST['dados']) ? (string)$_POST['dados'] : '{}', true);
        if (!is_array($dados)) $dados = [];
        gerarRelatorioSobreposicaoPDF($dados);
        exit;
    }

    /* ----- Relatório de inconsistências em PDF (saída binária, antes do header JSON) ----- */
    if ($_POST['acao'] === 'relatorio_inconsistencias') {
        ensureTable($conn);
        $ids = json_decode(isset($_POST['ids']) ? (string)$_POST['ids'] : '[]', true);
        if (!is_array($ids)) $ids = [];
        gerarRelatorioInconsistenciasPDF($conn, $ids);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');
    ensureTable($conn);
    $acao = $_POST['acao'];

    try {
        if ($acao === 'ibge_municipios') {
            // aceita UF por sigla (MA) ou por código IBGE do estado (21) — ambos funcionam na API
            $uf = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['uf'] ?? '')));
            if ($uf === '') { echo json_encode(['ok' => false, 'erro' => 'UF não informada.']); exit; }
            $url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/' . rawurlencode($uf) . '/municipios?orderBy=nome';
            $err = '';
            $json = httpGetText($url, $err);
            if ($json === false) { echo json_encode(['ok' => false, 'erro' => 'Falha ao consultar o IBGE: ' . $err]); exit; }
            $arr = json_decode($json, true);
            if (!is_array($arr)) { echo json_encode(['ok' => false, 'erro' => 'Resposta inválida do IBGE.']); exit; }
            $out = [];
            foreach ($arr as $m) {
                if (isset($m['id'], $m['nome'])) $out[] = ['id' => (int)$m['id'], 'nome' => (string)$m['nome']];
            }
            echo json_encode(['ok' => true, 'municipios' => $out], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'ibge_malha_uf') {
            // Malha do ESTADO subdividida por município (cada feature traz codarea = código do município).
            // Usada para identificar, por ponto-em-polígono, qual município vizinho pega parte do imóvel.
            $uf = preg_replace('/\D/', '', (string)($_POST['uf'] ?? ''));
            if ($uf === '') { echo json_encode(['ok' => false, 'erro' => 'UF não informada.']); exit; }
            $base = 'https://servicodados.ibge.gov.br/api/v3/malhas/estados/' . rawurlencode($uf)
                  . '?formato=application/vnd.geo%2Bjson&intrarregiao=municipio';
            $err = '';
            $geo = httpGetText($base . '&qualidade=2', $err);   // qualidade 2 = leve, suficiente p/ identificar
            if ($geo === false) {
                $err2 = '';
                $geo = httpGetText($base, $err2);
                if ($geo === false) { echo json_encode(['ok' => false, 'erro' => 'Falha ao obter a malha estadual no IBGE: ' . $err]); exit; }
            }
            $gj = json_decode($geo, true);
            if (!is_array($gj) || !isset($gj['type'])) { echo json_encode(['ok' => false, 'erro' => 'GeoJSON estadual inválido do IBGE.']); exit; }
            echo json_encode(['ok' => true, 'geojson' => $gj], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'ibge_malha') {
            $mun = preg_replace('/\D/', '', (string)($_POST['municipio'] ?? ''));
            if ($mun === '') { echo json_encode(['ok' => false, 'erro' => 'Município não informado.']); exit; }
            $q = (int)($_POST['qualidade'] ?? 4); if ($q < 1 || $q > 4) $q = 4;
            // ATENÇÃO: o '+' em "vnd.geo+json" precisa ir codificado (%2B), senão o IBGE
            // interpreta como espaço e devolve HTTP 400.
            $url = 'https://servicodados.ibge.gov.br/api/v3/malhas/municipios/' . rawurlencode($mun)
                 . '?formato=application/vnd.geo%2Bjson&qualidade=' . $q;
            $err = '';
            $geo = httpGetText($url, $err);
            if ($geo === false) {
                // tenta novamente sem o parâmetro de qualidade (alguns ambientes recusam a combinação)
                $url2 = 'https://servicodados.ibge.gov.br/api/v3/malhas/municipios/' . rawurlencode($mun)
                      . '?formato=application/vnd.geo%2Bjson';
                $err2 = '';
                $geo = httpGetText($url2, $err2);
                if ($geo === false) { echo json_encode(['ok' => false, 'erro' => 'Falha ao obter o limite no IBGE: ' . $err]); exit; }
            }
            $gj = json_decode($geo, true);
            if (!is_array($gj) || !isset($gj['type'])) { echo json_encode(['ok' => false, 'erro' => 'GeoJSON inválido do IBGE.']); exit; }
            echo json_encode(['ok' => true, 'geojson' => $gj], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'processar') {
            $memorial = isset($_POST['memorial']) ? (string)$_POST['memorial'] : '';
            $data = buildGeoData($memorial);
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'processar_kml') {
            $kml = isset($_POST['kml']) ? (string)$_POST['kml'] : '';
            $pm = parseKml($kml);
            if (empty($pm)) {
                echo json_encode(['ok' => false, 'erro' => 'Nenhum polígono encontrado no arquivo KML.']);
                exit;
            }
            $saida = [];
            foreach ($pm as $p) {
                $geo = buildGeoDataFromPoints($p['pts']);
                if ($geo['ok']) {
                    $geo['nome'] = $p['nome'];
                    $saida[] = $geo;
                }
            }
            echo json_encode(['ok' => true, 'total' => count($saida), 'placemarks' => $saida], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'salvar') {
            $origem      = ($_POST['origem'] ?? 'memorial') === 'kml' ? 'kml' : 'memorial';
            $fonte       = isset($_POST['memorial']) ? (string)$_POST['memorial'] : '';
            $identificador = trim(isset($_POST['identificador']) ? (string)$_POST['identificador'] : '');
            $numMatricula  = trim(isset($_POST['numero_matricula']) ? (string)$_POST['numero_matricula'] : '');
            $proprietario  = trim(isset($_POST['proprietario']) ? (string)$_POST['proprietario'] : '');
            $cpf           = trim(isset($_POST['cpf']) ? (string)$_POST['cpf'] : '');
            $tipoImovel    = ($_POST['tipo_imovel'] ?? '') === 'rural' ? 'rural' : (($_POST['tipo_imovel'] ?? '') === 'urbano' ? 'urbano' : '');

            // Identificação principal: usa o campo informado; se vazio, cai para a matrícula
            if ($identificador === '' && $numMatricula !== '') $identificador = $numMatricula;
            if ($identificador === '') {
                echo json_encode(['ok' => false, 'erro' => 'Informe ao menos a identificação do imóvel ou o número da matrícula.']);
                exit;
            }
            $tipo = ($numMatricula !== '') ? 'matricula' : 'nome';

            // DEDUPLICAÇÃO: se a matrícula já está cadastrada, NÃO cria um novo
            // registro (evita polígono duplicado/sobreposição). Apenas complementa
            // as informações do registro existente — mesmo comportamento do fluxo
            // de processamento de PDF. A geometria gravada é preservada.
            if ($numMatricula !== '') {
                $idExistente = acharMemorialPorMatricula($conn, $numMatricula);
                if ($idExistente) {
                    $imovelId = findImovelIdByMatricula($conn, $numMatricula);
                    // só atualiza o que veio preenchido (não apaga dados já existentes)
                    $sets = []; $vals = []; $types = '';
                    if ($identificador !== '') { $sets[] = 'identificador=?';     $vals[] = $identificador; $types .= 's'; }
                    $sets[] = 'tipo_identificador=?'; $vals[] = 'matricula'; $types .= 's';
                    if ($proprietario !== '') { $sets[] = 'proprietario=?';        $vals[] = $proprietario;  $types .= 's'; }
                    if ($cpf !== '')          { $sets[] = 'cpf=?';                 $vals[] = $cpf;           $types .= 's'; }
                    if ($tipoImovel !== '')   { $sets[] = 'tipo_imovel=?';         $vals[] = $tipoImovel;    $types .= 's'; }
                    if ($imovelId !== null)   { $sets[] = 'imovel_id=?';           $vals[] = $imovelId;      $types .= 'i'; }
                    if (!empty($sets)) {
                        $vals[] = $idExistente; $types .= 'i';
                        $stmt = $conn->prepare("UPDATE memoriais_mapeados SET " . implode(', ', $sets) . " WHERE id = ?");
                        if ($stmt) { $stmt->bind_param($types, ...$vals); $stmt->execute(); }
                    }
                    // complementa os campos ONR enviados junto (se houver)
                    salvarCamposOnr($conn, $idExistente, $_POST);
                    // arquiva o KML também quando a matrícula já existia (geometria preservada)
                    if ($origem === 'kml' && $fonte !== '') {
                        $nomeArqDup = trim((string)($_POST['nome_arquivo'] ?? ''));
                        if ($nomeArqDup === '') $nomeArqDup = ($numMatricula !== '' ? $numMatricula : 'imovel') . '.kml';
                        anexoSalvarBytes($conn, $idExistente, $fonte, $nomeArqDup, 'kml', 'application/vnd.google-earth.kml+xml');
                    }
                    // inconsistências de nome do KML (geometria preservada do registro existente)
                    $incDup = [];
                    if ($origem === 'kml') {
                        foreach (parseKml($fonte) as $p0) { list(, $iN) = inconsNomeKml($p0['nome'] ?? ''); $incDup = array_merge($incDup, $iN); }
                        if ($incDup) inconsGravar($conn, $idExistente, $incDup);
                    }
                    echo json_encode([
                        'ok' => true, 'existe' => true, 'atualizado' => true, 'criado' => false,
                        'id' => $idExistente, 'imovel_id' => $imovelId, 'inconsistencias' => array_values($incDup),
                        'mensagem' => 'A matrícula ' . $numMatricula . ' já estava cadastrada — as informações foram complementadas, sem duplicar o polígono no mapa.'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            $data = processarFonte($origem, $fonte);
            if (!$data['ok']) {
                $legs = ($origem === 'kml') ? [] : extractTraverseLegs($fonte);
                if (count($legs) >= 3) {
                    echo json_encode(['ok' => false, 'erro' => 'Este memorial descreve o perímetro por azimutes e distâncias (' . count($legs) . ' segmentos) a partir de marcos, sem coordenadas geográficas (Long/Lat ou UTM E/N). Não é possível posicioná-lo no mapa — informe um memorial com as coordenadas dos vértices.']);
                    exit;
                }
                echo json_encode(['ok' => false, 'erro' => 'A fonte não gerou um polígono válido (mínimo 3 vértices).']);
                exit;
            }

            $imovelId = ($numMatricula !== '') ? findImovelIdByMatricula($conn, $numMatricula) : null;
            $novoId = inserirMemorial($conn, $identificador, $tipo, $origem, $imovelId, $fonte, $data, $numMatricula, $proprietario, $cpf, $tipoImovel);

            // grava também os campos ONR enviados junto, se houver
            salvarCamposOnr($conn, $novoId, $_POST);

            // arquiva o próprio KML como anexo (para conferência/reprocessamento posterior)
            if ($origem === 'kml' && $fonte !== '') {
                $nomeArq = trim((string)($_POST['nome_arquivo'] ?? ''));
                if ($nomeArq === '') $nomeArq = ($identificador !== '' ? $identificador : 'imovel') . '.kml';
                anexoSalvarBytes($conn, $novoId, $fonte, $nomeArq, 'kml', 'application/vnd.google-earth.kml+xml');
            }

            // inconsistências: geometria + (se KML) nome do placemark interno
            $incList = detectarInconsistenciasGeo($data, $origem);
            if ($origem === 'kml') {
                foreach (parseKml($fonte) as $p0) { list(, $incNome) = inconsNomeKml($p0['nome'] ?? ''); $incList = array_merge($incList, $incNome); }
            }
            inconsGravar($conn, $novoId, $incList);

            echo json_encode([
                'ok' => true,
                'id' => $novoId,
                'imovel_id' => $imovelId,
                'inconsistencias' => array_values($incList),
                'mensagem' => 'Imóvel "' . $identificador . '" gravado com ' . $data['num_vertices'] . ' vértices.'
                    . ($imovelId ? ' Vinculado ao cadastro (id ' . $imovelId . ').' : '')
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'salvar_onr') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Selecione/grave um imóvel antes de salvar os dados ONR.']); exit; }
            $ok = salvarCamposOnr($conn, $id, $_POST);
            echo json_encode(['ok' => (bool)$ok, 'id' => $id, 'mensagem' => $ok ? 'Dados ONR salvos.' : 'Nada a salvar.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'salvar_situacao') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Imóvel inválido.']); exit; }
            $sit = (($_POST['situacao'] ?? '') === 'encerrada') ? 'encerrada' : 'ativa';
            $m = $_POST['motivo_situacao'] ?? '';
            $mot = in_array($m, ['unificacao', 'desmembramento', 'georreferenciamento'], true) ? $m : null;
            $s = trim((string)($_POST['matricula_sucessora'] ?? ''));
            $suc = ($s !== '') ? $s : null;
            // coerência: unificação e georreferenciamento encerram a matrícula; desmembramento parcial a mantém ativa
            if ($mot === 'unificacao' || $mot === 'georreferenciamento') $sit = 'encerrada';
            if ($sit === 'ativa' && $mot !== 'desmembramento') { $mot = null; $suc = null; }
            $stmt = $conn->prepare("UPDATE memoriais_mapeados SET situacao=?, motivo_situacao=?, matricula_sucessora=? WHERE id=?");
            $stmt->bind_param('sssi', $sit, $mot, $suc, $id);
            $stmt->execute();
            echo json_encode(['ok' => true, 'id' => $id, 'situacao' => $sit, 'motivo_situacao' => $mot, 'matricula_sucessora' => $suc], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'marcar_fora_municipio') {
            // Marca/desmarca o imóvel como fora do perímetro do município (bloqueia envio ONR/ITN).
            // 'municipio' = nome do município real (quando fora) ou vazio para limpar (quando dentro).
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Imóvel inválido.']); exit; }
            $mun = trim((string)($_POST['municipio'] ?? ''));
            if (mb_strlen($mun) > 120) $mun = mb_substr($mun, 0, 120);
            $val = ($mun !== '') ? $mun : null;
            $stmt = $conn->prepare("UPDATE memoriais_mapeados SET fora_municipio = ? WHERE id = ?");
            if ($stmt) { $stmt->bind_param('si', $val, $id); $stmt->execute(); }
            echo json_encode(['ok' => true, 'id' => $id, 'fora_municipio' => $val], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'consultar_cep') {
            $info = cepConsultar($_POST['cep'] ?? '');
            if (!$info) { echo json_encode(['ok' => false, 'erro' => 'CEP não encontrado.']); exit; }
            echo json_encode(array_merge(['ok' => true], $info), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($acao === 'gemini_config_get') {
            $cfg = geminiConfigLer();
            $k = (string)$cfg['api_key'];
            $mask = ($k === '') ? '' : (substr($k, 0, 4) . str_repeat('•', max(0, min(16, strlen($k) - 8))) . substr($k, -4));
            echo json_encode(['ok' => true, 'configurado' => ($k !== ''), 'key_mascara' => $mask, 'models' => $cfg['models'], 'default_model' => $cfg['default_model']], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($acao === 'gemini_config_save') {
            $atual = geminiConfigLer();
            $api_key = trim((string)($_POST['api_key'] ?? ''));
            if ($api_key === '') $api_key = $atual['api_key']; // vazio mantém a chave atual
            $models = json_decode((string)($_POST['models'] ?? '[]'), true);
            if (!is_array($models) || empty($models)) $models = geminiModelosPadrao();
            $models = array_values(array_unique(array_filter(array_map(fn($m) => trim((string)$m), $models))));
            $def = trim((string)($_POST['default_model'] ?? ''));
            if (!in_array($def, $models, true)) $def = $models[0];
            $ok = geminiConfigSalvar(['api_key' => $api_key, 'models' => $models, 'default_model' => $def]);
            echo json_encode(['ok' => (bool)$ok, 'mensagem' => $ok ? 'Configuração de IA salva.' : 'Não foi possível salvar (permissão de escrita?).'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($acao === 'processar_pdf_matricula') {
          try {
            if (empty($_FILES['pdf']['tmp_name']) || !is_uploaded_file($_FILES['pdf']['tmp_name'])) {
                echo json_encode(['ok' => false, 'erro' => 'Nenhum PDF foi enviado.']); exit;
            }
            $matricula = trim((string)($_POST['matricula'] ?? ''));
            if ($matricula === '') $matricula = preg_replace('/\.pdf$/i', '', (string)$_FILES['pdf']['name']);
            $matricula = trim($matricula);
            if ($matricula === '') { echo json_encode(['ok' => false, 'erro' => 'Nomeie o PDF com o número da matrícula (ex.: 2470.pdf).']); exit; }
            // IA configurada?
            $cfg = geminiConfigLer();
            if (trim($cfg['api_key']) === '') { echo json_encode(['ok' => false, 'erro' => 'Configure a chave da API do Gemini antes de processar.']); exit; }
            // lê e processa o PDF
            $pdfBytes = @file_get_contents($_FILES['pdf']['tmp_name']);
            if ($pdfBytes === false || $pdfBytes === '') { echo json_encode(['ok' => false, 'erro' => 'Falha ao ler o PDF enviado.']); exit; }
            $r = geminiExtrairMatricula($cfg, $pdfBytes);
            if (!$r['ok']) { echo json_encode($r); exit; }
            $d = $r['dados'];
            enriquecerCepExtraido($d); // valida/completa CEP via ViaCEP (cadastro e complemento)
            $ctxR = itn03ContextoRuralDetectar($d); if ($ctxR !== '') $d['contexto_rural'] = $ctxR; // União/estrangeiro p/ ITN 03
            // matrícula efetiva: se o nome do arquivo não for um número limpo (ex.: UUID do SIGEF),
            // usa a matrícula extraída do documento.
            $matDoc = trim((string)($d['numero_matricula'] ?? ''));
            if (!preg_match('/^\d{1,8}$/', $matricula) && $matDoc !== '') $matricula = preg_replace('/\D/', '', $matDoc);
            if ($matricula === '' || !preg_match('/\d/', $matricula)) {
                echo json_encode(['ok' => false, 'erro' => 'Não foi possível identificar o número da matrícula (nem no nome do arquivo nem no documento). Renomeie o PDF com o número da matrícula.']);
                exit;
            }

            $id = acharMemorialParaPdf($conn, $matricula, $matDoc);
            if ($id) {
                // JÁ EXISTE -> complementa apenas os campos vazios (não altera a geometria)
                $rs = $conn->query("SELECT * FROM memoriais_mapeados WHERE id = " . (int)$id . " LIMIT 1");
                $rowAtual = $rs ? $rs->fetch_assoc() : [];
                if (!is_array($rowAtual)) $rowAtual = [];
                // não sobrescreve prenotação já preenchida manualmente
                if (trim((string)($rowAtual['onr_numero_prenotacao'] ?? '')) !== '') unset($d['onr_numero_prenotacao']);
                // padrões da importação (só quando ainda não houver, no banco e no extraído)
                if (trim((string)($rowAtual['onr_descricao'] ?? '')) === '' && trim((string)($d['onr_descricao'] ?? '')) === '') $d['onr_descricao'] = 'Importação de polígonos';
                if (trim((string)($rowAtual['onr_nivel_publicidade'] ?? '')) === '' && trim((string)($d['onr_nivel_publicidade'] ?? '')) === '') $d['onr_nivel_publicidade'] = '3';
                if (trim((string)($rowAtual['onr_classificacao'] ?? '')) === '' && trim((string)($d['onr_classificacao'] ?? '')) === '') $d['onr_classificacao'] = '1';
                // imóvel cadastrado só com KML costuma não ter numero_matricula: preenche agora
                if (trim((string)($rowAtual['numero_matricula'] ?? '')) === '' && $matricula !== '' && trim((string)($d['numero_matricula'] ?? '')) === '') $d['numero_matricula'] = $matricula;
                $preenchidos = complementarMatricula($conn, $id, $d, $rowAtual);
                $anexoId = anexoSalvarBytes($conn, $id, $pdfBytes, (string)$_FILES['pdf']['name'], anexoTipo((string)$_FILES['pdf']['name'], 'application/pdf', $d), 'application/pdf');
                $incPdf = detectarInconsistenciasPdf($d, null);
                inconsGravar($conn, $id, $incPdf);
                $cv = aplicarCicloVida($conn, $id, $matricula, $d['ciclo_vida'] ?? []);
                echo json_encode([
                    'ok' => true, 'existe' => true, 'criado' => false, 'id' => $id, 'matricula' => $matricula, 'modelo' => $r['modelo'],
                    'campos' => $preenchidos, 'anexo_id' => $anexoId, 'inconsistencias' => array_values($incPdf),
                    'ciclo_vida' => $cv,
                    'mensagem' => 'Matrícula ' . $matricula . ' já cadastrada — ' . count($preenchidos) . ' campo(s) complementado(s). PDF arquivado.' . cicloVidaResumo($cv)
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // NÃO EXISTE -> cadastra extraindo as coordenadas do memorial
            $memorial = (string)($d['memorial'] ?? '');
            $geo = buildGeoData($memorial);
            if (empty($geo['ok'])) {
                $legs = extractTraverseLegs($memorial);
                if (count($legs) >= 3) {
                    $tot = array_sum(array_column($legs, 'dist'));
                    echo json_encode(['ok' => false, 'erro' => 'A matrícula ' . $matricula . ' descreve o perímetro por AZIMUTES E DISTÂNCIAS a partir de marcos físicos (' . count($legs) . ' segmentos, ~' . number_format($tot, 0, ',', '.') . ' m de extensão), porém SEM coordenadas geográficas (Long/Lat ou UTM E/N). É um memorial antigo, não georreferenciado — não há como posicioná-lo no mapa automaticamente. Para mapear, é necessário um memorial com as coordenadas dos vértices, ou o georreferenciamento (SIGEF/INCRA) do imóvel.']);
                    exit;
                }
                echo json_encode(['ok' => false, 'erro' => 'A matrícula ' . $matricula . ' não está cadastrada e não foi possível extrair as coordenadas do memorial no PDF (vértices encontrados: ' . (int)($geo['num_vertices'] ?? 0) . '). Verifique se o PDF contém a descrição do perímetro com coordenadas (Longitude/Latitude em GMS ou UTM E/N em metros).']);
                exit;
            }
            $identificador = trim((string)($d['nome_imo'] ?? '')); if ($identificador === '') $identificador = $matricula;
            $tipoImovel = (stripos((string)($d['tipo_imovel'] ?? ''), 'rural') !== false) ? 'rural' : ((stripos((string)($d['tipo_imovel'] ?? ''), 'urban') !== false) ? 'urbano' : '');
            $pessoasNovo = qualificacaoNormalizar($d['pessoas'] ?? []);
            qualificacaoDerivarFlat($d, $pessoasNovo); // proprietario/cpf/rel_jur/dat_ini/per_rel a partir dos titulares
            $proprietario = trim((string)($d['proprietario'] ?? ''));
            $cpf = trim((string)($d['cpf'] ?? ''));
            $imovelId = findImovelIdByMatricula($conn, $matricula);
            $novoId = inserirMemorial($conn, $identificador, 'matricula', 'memorial', $imovelId, $memorial, $geo, $matricula, $proprietario, $cpf, $tipoImovel);
            // descrição da importação: padrão quando não houver
            if (trim((string)($d['onr_descricao'] ?? '')) === '') $d['onr_descricao'] = 'Importação de polígonos';
            // parâmetros de envio: padrões
            if (trim((string)($d['onr_nivel_publicidade'] ?? '')) === '') $d['onr_nivel_publicidade'] = '3';
            if (trim((string)($d['onr_classificacao'] ?? '')) === '') $d['onr_classificacao'] = '1';
            // grava os demais campos ONR extraídos
            salvarCamposOnr($conn, $novoId, $d);
            if (!empty($d['contexto_rural'])) { $stx = $conn->prepare("UPDATE memoriais_mapeados SET contexto_rural = ? WHERE id = ?"); if ($stx) { $cv2 = (string)$d['contexto_rural']; $stx->bind_param('si', $cv2, $novoId); $stx->execute(); } }
            qualificacaoGravar($conn, $novoId, $pessoasNovo); // qualificação estruturada dos titulares atuais
            $anexoId = anexoSalvarBytes($conn, $novoId, $pdfBytes, (string)$_FILES['pdf']['name'], anexoTipo((string)$_FILES['pdf']['name'], 'application/pdf', $d), 'application/pdf');
            $incPdf = detectarInconsistenciasPdf($d, $geo);
            inconsGravar($conn, $novoId, $incPdf);
            $cv = aplicarCicloVida($conn, $novoId, $matricula, $d['ciclo_vida'] ?? []);
            $preenchidos = array_values(array_filter(array_keys($d), fn($k) => $k !== 'memorial' && trim((string)($d[$k] ?? '')) !== ''));
            echo json_encode([
                'ok' => true, 'existe' => false, 'criado' => true, 'id' => $novoId, 'matricula' => $matricula, 'modelo' => $r['modelo'],
                'num_vertices' => $geo['num_vertices'], 'area_ha' => $geo['area_ha'], 'campos' => $preenchidos,
                'vertices_corrigidos' => $geo['vertices_corrigidos'] ?? [],
                'aviso_geometria' => $geo['aviso_geometria'] ?? '', 'inconsistencias' => array_values($incPdf),
                'ciclo_vida' => $cv,
                'mensagem' => 'Matrícula ' . $matricula . ' cadastrada e mapeada com ' . $geo['num_vertices'] . ' vértices (' . number_format($geo['area_ha'], 4, ',', '.') . ' ha). ' . count($preenchidos) . ' campo(s) preenchido(s).'
                    . (!empty($geo['aviso_geometria']) ? ' ' . $geo['aviso_geometria'] : '') . cicloVidaResumo($cv)
            ], JSON_UNESCAPED_UNICODE);
            exit;
          } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'erro' => 'Erro ao processar o PDF: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
          }
        }

        if ($acao === 'onr_config_get') {
            $cfg = onrConfigLer();
            // não devolve o token inteiro por segurança — apenas máscara + se está configurado
            $tok = (string)$cfg['token'];
            $mask = ($tok === '') ? '' : (substr($tok, 0, 4) . str_repeat('•', max(0, min(20, strlen($tok)-8))) . substr($tok, -4));
            echo json_encode(['ok' => true, 'base_url' => $cfg['base_url'], 'configurado' => ($tok !== ''), 'token_mascara' => $mask], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($acao === 'onr_config_save') {
            $base_url = (string)($_POST['base_url'] ?? '');
            $token    = (string)($_POST['token'] ?? '');
            // se o token vier vazio, mantém o atual (permite salvar só a URL)
            if (trim($token) === '') { $atual = onrConfigLer(); $token = $atual['token']; }
            $ok = onrConfigSalvar($base_url, $token);
            echo json_encode(['ok' => (bool)$ok, 'mensagem' => $ok ? 'Configuração salva.' : 'Não foi possível salvar (verifique permissão de escrita na pasta).'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'enviar_onr') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'mensagem' => 'Imóvel inválido.']); exit; }
            echo json_encode(onrEnviarImovel($conn, $id), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($acao === 'enviar_onr_lote') {
            // envia todos os que estão prontos e ainda não foram enviados
            $res = $conn->query(
                "SELECT id FROM memoriais_mapeados
                 WHERE tipo_imovel IN ('urbano','rural')
                   AND onr_nivel_publicidade IS NOT NULL AND onr_nivel_publicidade <> ''
                   AND onr_classificacao IS NOT NULL AND onr_classificacao <> ''
                   AND onr_numero_prenotacao IS NOT NULL AND onr_numero_prenotacao <> ''
                   AND onr_descricao IS NOT NULL AND onr_descricao <> ''
                   AND (onr_importation_id IS NULL OR onr_importation_id = '')
                 ORDER BY id"
            );
            $ids = []; while ($res && $r = $res->fetch_assoc()) { $ids[] = (int)$r['id']; }
            $enviados = 0; $falhas = []; 
            foreach ($ids as $iid) {
                $r = onrEnviarImovel($conn, $iid);
                if ($r['ok']) $enviados++; else $falhas[] = "#$iid: " . $r['mensagem'];
            }
            echo json_encode(['ok' => true, 'total' => count($ids), 'enviados' => $enviados, 'falhas' => $falhas], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($acao === 'status_onr') {
            $id = (int)($_POST['id'] ?? 0);
            $res = $conn->query("SELECT onr_importation_id FROM memoriais_mapeados WHERE id = $id LIMIT 1");
            $row = $res ? $res->fetch_assoc() : null;
            $impId = $row['onr_importation_id'] ?? '';
            if ($impId === '') { echo json_encode(['ok' => false, 'mensagem' => 'Imóvel ainda não enviado.']); exit; }
            $cfg = onrConfigLer();
            $r = onrHttp('POST', $cfg['base_url'] . 'api/v1/poligonos/status', $cfg['token'], ['importation_id' => $impId]);
            $status = $r['json']['data']['status'] ?? ('HTTP ' . $r['code']);
            $stmt = $conn->prepare("UPDATE memoriais_mapeados SET onr_status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            echo json_encode(['ok' => true, 'status' => $status, 'importation_id' => $impId], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'atualizar_imovel') {
            $id            = (int)($_POST['id'] ?? 0);
            $identificador = trim((string)($_POST['identificador'] ?? ''));
            $numMatricula  = trim((string)($_POST['numero_matricula'] ?? ''));
            $proprietario  = trim((string)($_POST['proprietario'] ?? ''));
            $cpf           = trim((string)($_POST['cpf'] ?? ''));
            $tipoImovel    = ($_POST['tipo_imovel'] ?? '') === 'rural' ? 'rural' : (($_POST['tipo_imovel'] ?? '') === 'urbano' ? 'urbano' : '');
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Imóvel inválido.']); exit; }
            if ($identificador === '' && $numMatricula !== '') $identificador = $numMatricula;
            if ($identificador === '') { echo json_encode(['ok' => false, 'erro' => 'Informe a identificação ou a matrícula.']); exit; }

            // não permite atribuir uma matrícula que já pertence a OUTRO imóvel
            if ($numMatricula !== '') {
                $dono = acharMemorialPorMatricula($conn, $numMatricula);
                if ($dono && $dono !== $id) {
                    echo json_encode(['ok' => false, 'erro' => 'Já existe outro imóvel cadastrado com a matrícula ' . $numMatricula . ' (registro #' . $dono . '). Não é possível duplicar o número da matrícula.']);
                    exit;
                }
            }

            $tipo = ($numMatricula !== '') ? 'matricula' : 'nome';
            $imovelId = ($numMatricula !== '') ? findImovelIdByMatricula($conn, $numMatricula) : null;
            $nm = $numMatricula !== '' ? $numMatricula : null;
            $pr = $proprietario !== '' ? $proprietario : null;
            $cp = $cpf !== '' ? $cpf : null;
            $ti = in_array($tipoImovel, ['urbano', 'rural'], true) ? $tipoImovel : null;

            $stmt = $conn->prepare("UPDATE memoriais_mapeados SET identificador=?, tipo_identificador=?, numero_matricula=?, proprietario=?, cpf=?, tipo_imovel=?, imovel_id=? WHERE id=?");
            $stmt->bind_param('ssssssii', $identificador, $tipo, $nm, $pr, $cp, $ti, $imovelId, $id);
            $stmt->execute();
            // contexto rural da carga ITN 03 ('1'|'2'|'3'); vazio => autodetecção
            $ctxRural = in_array((string)($_POST['contexto_rural'] ?? ''), ['1', '2', '3'], true) ? (string)$_POST['contexto_rural'] : null;
            $stx = $conn->prepare("UPDATE memoriais_mapeados SET contexto_rural=? WHERE id=?");
            if ($stx) { $stx->bind_param('si', $ctxRural, $id); $stx->execute(); }
            salvarCamposOnr($conn, $id, $_POST);
            echo json_encode(['ok' => true, 'id' => $id, 'imovel_id' => $imovelId], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'itn03_individual') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Imóvel inválido.']); exit; }
            $stmt = $conn->prepare("SELECT " . itn03SelectCols() . " FROM memoriais_mapeados WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id); $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) { echo json_encode(['ok' => false, 'erro' => 'Imóvel não encontrado.']); exit; }
            $ehExcl = (int)($row['itn03_exclusivo'] ?? 0) === 1;
            $falta = $ehExcl ? itn03ExclusivoFaltam($row) : itn03Faltam($row);
            if ($falta) {
                $ctx = $ehExcl
                    ? 'a carga ITN 03 (mínimo: tipo + número da matrícula + CNM válido + município + UF)'
                    : 'o Mapa da ONR (e, portanto, para a carga ITN 03)';
                echo json_encode(['ok' => false, 'erro' => 'Esta matrícula ainda não está apta para ' . $ctx . '. Faltam: ' . implode(', ', $falta) . '.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $res = itn03GerarArquivos([$row]);
            echo json_encode(['ok' => true, 'arquivos' => $res['arquivos'], 'avisos' => $res['avisos']], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($acao === 'itn03_lote') {
            $escopo = (($_POST['escopo'] ?? 'mapa') === 'exclusivas') ? 'exclusivas' : 'mapa';
            $idsRaw = trim((string)($_POST['ids'] ?? ''));
            $linhas = [];
            if ($escopo === 'exclusivas') {
                $rs = $conn->query("SELECT " . itn03SelectCols() . " FROM memoriais_mapeados WHERE itn03_exclusivo = 1 AND (situacao <> 'encerrada' OR situacao IS NULL) ORDER BY id");
                while ($rs && $r = $rs->fetch_assoc()) $linhas[] = $r;
                $aptoFn = 'itn03ExclusivoApto';
                $msgVazio = 'Nenhuma matrícula exclusiva ITN 03 apta. Cada uma precisa de: tipo (urbano/rural) + número da matrícula + CNM válido + município + UF.';
                $msgSemReg = 'Nenhuma matrícula exclusiva ITN 03 cadastrada.';
            } else {
                if ($idsRaw !== '') {
                    $ids = array_values(array_filter(array_map('intval', preg_split('/[^0-9]+/', $idsRaw))));
                    if ($ids) {
                        $in = implode(',', $ids);
                        $rs = $conn->query("SELECT " . itn03SelectCols() . " FROM memoriais_mapeados WHERE id IN ($in) AND itn03_exclusivo = 0 ORDER BY id");
                        while ($rs && $r = $rs->fetch_assoc()) $linhas[] = $r;
                    }
                } else {
                    $rs = $conn->query("SELECT " . itn03SelectCols() . " FROM memoriais_mapeados WHERE itn03_exclusivo = 0 AND (situacao <> 'encerrada' OR situacao IS NULL) ORDER BY id");
                    while ($rs && $r = $rs->fetch_assoc()) $linhas[] = $r;
                }
                $aptoFn = 'itn03Apto';
                $msgVazio = 'Nenhum imóvel apto para a carga ITN 03. Só são exportados os que estão prontos para o Mapa da ONR (tipo + nível de publicidade + classificação + prenotação + descrição).';
                $msgSemReg = 'Nenhum imóvel para exportar.';
            }
            if (!$linhas) { echo json_encode(['ok' => false, 'erro' => $msgSemReg], JSON_UNESCAPED_UNICODE); exit; }
            $total = count($linhas);
            $aptas = array_values(array_filter($linhas, $aptoFn));
            $puladas = $total - count($aptas);
            if (!$aptas) { echo json_encode(['ok' => false, 'erro' => $msgVazio], JSON_UNESCAPED_UNICODE); exit; }
            $res = itn03GerarArquivos($aptas);
            echo json_encode(['ok' => true, 'total' => count($aptas), 'puladas' => $puladas, 'escopo' => $escopo, 'arquivos' => $res['arquivos'], 'avisos' => $res['avisos']], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($acao === 'itn03_exclusiva_nova') {
            $identificador = trim((string)($_POST['identificador'] ?? ''));
            $numMatricula  = trim((string)($_POST['numero_matricula'] ?? ''));
            $proprietario  = trim((string)($_POST['proprietario'] ?? ''));
            $cpf           = trim((string)($_POST['cpf'] ?? ''));
            $tipoImovel    = in_array(($_POST['tipo_imovel'] ?? ''), ['urbano', 'rural'], true) ? (string)$_POST['tipo_imovel'] : '';
            $areaHa        = trim((string)($_POST['area_ha'] ?? ''));
            if ($identificador === '' && $numMatricula !== '') $identificador = $numMatricula;
            if ($identificador === '') { echo json_encode(['ok' => false, 'erro' => 'Informe a identificação ou a matrícula.']); exit; }
            if ($numMatricula !== '') {
                $dono = acharMemorialPorMatricula($conn, $numMatricula);
                if ($dono) { echo json_encode(['ok' => false, 'erro' => 'Já existe um imóvel cadastrado com a matrícula ' . $numMatricula . ' (registro #' . $dono . ').']); exit; }
            }
            $tipo = ($numMatricula !== '') ? 'matricula' : 'nome';
            $areaVal = ($areaHa !== '') ? (float)str_replace(',', '.', $areaHa) : null;
            $data = ['num_vertices' => null, 'area_ha' => $areaVal, 'perimetro_m' => null,
                     'centro_lat' => null, 'centro_lng' => null, 'coordenadas_wgs84' => null, 'coordenadas_utm' => null];
            $novoId = inserirMemorial($conn, $identificador, $tipo, 'itn03', null, null, $data, $numMatricula, $proprietario, $cpf, $tipoImovel);
            if (!$novoId) { echo json_encode(['ok' => false, 'erro' => 'Falha ao cadastrar a matrícula.']); exit; }
            $conn->query("UPDATE memoriais_mapeados SET itn03_exclusivo = 1 WHERE id = " . (int)$novoId);
            salvarCamposOnr($conn, $novoId, $_POST);
            echo json_encode(['ok' => true, 'id' => $novoId], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'serventia_municipio') {
            $raw = '';
            try {
                $r = @$conn->query("SELECT cidade FROM cadastro_serventia WHERE cidade IS NOT NULL AND cidade <> '' ORDER BY id LIMIT 1");
                if ($r && $row = $r->fetch_assoc()) { $raw = trim((string)$row['cidade']); }
            } catch (Throwable $e) { $raw = ''; }
            // O cadastro pode trazer a UF junto: "Zé Doca-MA", "Zé Doca - MA", "Zé Doca/MA", "Zé Doca, MA".
            $nome = $raw; $uf = 'MA';
            if ($raw !== '' && preg_match('/^(.*?)\s*[-\/,]\s*([A-Za-z]{2})$/u', $raw, $m)) {
                $nome = trim($m[1]);
                $uf   = strtoupper($m[2]);
            }
            echo json_encode(['ok' => true, 'cidade' => $nome, 'uf' => $uf, 'origem' => $raw], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'salvar_kml_lote') {
            $kml = isset($_POST['kml']) ? (string)$_POST['kml'] : '';
            $nomes = isset($_POST['nomes']) ? json_decode((string)$_POST['nomes'], true) : [];
            $tipos = isset($_POST['tipos']) ? json_decode((string)$_POST['tipos'], true) : [];
            if (!is_array($nomes)) $nomes = [];
            if (!is_array($tipos)) $tipos = [];

            $pm = parseKml($kml);
            if (empty($pm)) {
                echo json_encode(['ok' => false, 'erro' => 'Nenhum polígono encontrado no KML.']);
                exit;
            }
            $salvos = 0; $nomesSalvos = []; $resultados = [];
            foreach ($pm as $i => $p) {
                list($nomeLimpoInterno, $incNome) = inconsNomeKml($p['nome'] ?? '');
                $geo = buildGeoDataFromPoints($p['pts']);
                if (!$geo['ok']) {
                    $resultados[] = ['nome' => ($nomes[$i] ?? $nomeLimpoInterno ?: ('Imóvel KML ' . ($i+1))), 'status' => 'erro', 'id' => null,
                        'msg' => 'Polígono inválido (menos de 3 vértices).', 'inconsistencias' => []];
                    continue;
                }
                $nome = isset($nomes[$i]) ? trim((string)$nomes[$i]) : '';
                if ($nome === '') $nome = $nomeLimpoInterno !== '' ? $nomeLimpoInterno : ('Imóvel KML ' . ($i + 1));
                $tipo = (isset($tipos[$i]) && $tipos[$i] === 'matricula') ? 'matricula' : 'nome';
                $imovelId = ($tipo === 'matricula') ? findImovelIdByMatricula($conn, $nome) : null;

                $novoId = inserirMemorial($conn, $nome, $tipo, 'kml', $imovelId, '', $geo);
                $inc = array_merge($incNome, detectarInconsistenciasGeo($geo, 'kml'));
                inconsGravar($conn, $novoId, $inc);
                // arquiva o KML completo como anexo deste imóvel (para análise posterior)
                $nomeArqLote = trim((string)($_POST['nome_arquivo'] ?? '')); if ($nomeArqLote === '') $nomeArqLote = 'importacao.kml';
                anexoSalvarBytes($conn, $novoId, $kml, $nomeArqLote, 'kml', 'application/vnd.google-earth.kml+xml');
                $salvos++; $nomesSalvos[] = $nome;
                $resultados[] = ['nome' => $nome, 'status' => 'criado', 'id' => $novoId, 'msg' => $geo['num_vertices'] . ' vértices', 'inconsistencias' => array_values($inc)];
            }
            echo json_encode(['ok' => true, 'salvos' => $salvos, 'nomes' => $nomesSalvos, 'resultados' => $resultados], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'lista_sig') {
            // Assinatura leve do estado atual (para sincronização entre usuários por polling).
            // Cobre inserções, edições e exclusões sem transferir a lista inteira.
            $res = $conn->query("SELECT id, situacao, motivo_situacao, matricula_sucessora, fora_municipio, contexto_rural,
                                        tipo_imovel, proprietario, cpf, numero_matricula, identificador, cor, cor_opacidade,
                                        area_ha, num_vertices, onr_status, onr_importation_id, inconsistencias
                                 FROM memoriais_mapeados ORDER BY id");
            $h = hash_init('crc32b'); $c = 0;
            while ($res && $row = $res->fetch_assoc()) { $c++; hash_update($h, implode('|', array_map(fn($v) => (string)$v, $row)) . "\n"); }
            echo json_encode(['ok' => true, 'sig' => $c . '-' . hash_final($h)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'listar') {
            $res = $conn->query(
                "SELECT id, identificador, tipo_identificador, origem, imovel_id, num_vertices,
                        area_ha, perimetro_m, centro_lat, centro_lng, cor, cor_opacidade,
                        numero_matricula, proprietario, cpf, tipo_imovel, cnm, municipio, uf,
                        onr_status, onr_importation_id, onr_numero_prenotacao, onr_classificacao,
                        onr_nivel_publicidade, onr_descricao, itn03_exclusivo, inconsistencias,
                        situacao, motivo_situacao, matricula_sucessora, fora_municipio, contexto_rural, criado_em
                 FROM memoriais_mapeados ORDER BY criado_em DESC, id DESC LIMIT 1000"
            );
            $rows = [];
            while ($res && $row = $res->fetch_assoc()) {
                $fora = imovelForaMunicipio($row);
                $pronto = !$fora
                    && in_array($row['tipo_imovel'], ['urbano','rural'], true)
                    && trim((string)$row['onr_nivel_publicidade']) !== ''
                    && trim((string)$row['onr_classificacao']) !== ''
                    && trim((string)$row['onr_numero_prenotacao']) !== ''
                    && trim((string)$row['onr_descricao']) !== '';
                $row['onr_pronto'] = $pronto ? 1 : 0;
                $row['onr_enviado'] = (trim((string)$row['onr_importation_id']) !== '') ? 1 : 0;
                $row['itn03_exclusivo'] = (int)($row['itn03_exclusivo'] ?? 0);
                $row['itn03_apto'] = itn03ExclusivoApto($row) ? 1 : 0; // aptidão p/ carga exclusiva ITN 03
                $rows[] = $row;
            }
            echo json_encode(['ok' => true, 'itens' => $rows], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'listar_geo') {
            // Devolve todos os polígonos (lat/lng) para a visão geral e detecção de sobreposição
            $res = $conn->query(
                "SELECT id, identificador, tipo_identificador, origem, area_ha, cor, cor_opacidade,
                        numero_matricula, proprietario, cpf, tipo_imovel,
                        situacao, motivo_situacao, matricula_sucessora, fora_municipio, coordenadas_wgs84
                 FROM memoriais_mapeados ORDER BY id"
            );
            $itens = [];
            while ($res && $row = $res->fetch_assoc()) {
                $pts = [];
                foreach (preg_split('/\s+/', trim((string)$row['coordenadas_wgs84'])) as $par) {
                    if ($par === '') continue;
                    $xy = explode(',', $par);
                    if (count($xy) >= 2) $pts[] = [(float)$xy[0], (float)$xy[1]];
                }
                if (count($pts) >= 3) {
                    $itens[] = [
                        'id' => (int)$row['id'],
                        'identificador' => $row['identificador'],
                        'origem' => $row['origem'],
                        'area_ha' => (float)$row['area_ha'],
                        'cor' => $row['cor'],
                        'cor_opacidade' => $row['cor_opacidade'] !== null ? (float)$row['cor_opacidade'] : null,
                        'numero_matricula' => $row['numero_matricula'],
                        'proprietario' => $row['proprietario'],
                        'cpf' => $row['cpf'],
                        'tipo_imovel' => $row['tipo_imovel'],
                        'situacao' => $row['situacao'] ?? 'ativa',
                        'motivo_situacao' => $row['motivo_situacao'],
                        'matricula_sucessora' => $row['matricula_sucessora'],
                        'fora_municipio' => $row['fora_municipio'] ?? '',
                        'pts' => $pts,
                    ];
                }
            }
            echo json_encode(['ok' => true, 'itens' => $itens], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'salvar_cor') {
            $id = (int)($_POST['id'] ?? 0);
            $cor = strtolower(trim((string)($_POST['cor'] ?? '')));
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Imóvel inválido.']); exit; }
            // Aceita apenas hex (#rrggbb) ou vazio (limpar). NUNCA aceita tons de vermelho (reservado a sobreposição).
            if ($cor !== '') {
                if (!preg_match('/^#[0-9a-f]{6}$/', $cor)) { echo json_encode(['ok' => false, 'erro' => 'Cor inválida.']); exit; }
                $r = hexdec(substr($cor, 1, 2)); $g = hexdec(substr($cor, 3, 2)); $b = hexdec(substr($cor, 5, 2));
                if ($r >= 150 && $g <= 90 && $b <= 90) { echo json_encode(['ok' => false, 'erro' => 'O vermelho é reservado para sobreposições.']); exit; }
            }
            $valor = ($cor === '') ? null : $cor;
            // Intensidade (opacidade do preenchimento): limitada entre 0.08 e 0.55 para não fechar o mapa
            $op = null;
            if (isset($_POST['opacidade']) && $_POST['opacidade'] !== '') {
                $op = (float)$_POST['opacidade'];
                if ($op < 0.08) $op = 0.08;
                if ($op > 0.55) $op = 0.55;
            }
            $stmt = $conn->prepare("UPDATE memoriais_mapeados SET cor = ?, cor_opacidade = ? WHERE id = ?");
            $stmt->bind_param('sdi', $valor, $op, $id);
            $stmt->execute();
            echo json_encode(['ok' => true, 'id' => $id, 'cor' => $valor, 'cor_opacidade' => $op], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'anexo_listar') {
            $mid = (int)($_POST['id'] ?? 0);
            if ($mid <= 0) { echo json_encode(['ok' => false, 'erro' => 'Imóvel inválido.']); exit; }
            echo json_encode(['ok' => true, 'anexos' => anexosListar($conn, $mid)], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'anexo_excluir') {
            $aid = (int)($_POST['aid'] ?? 0);
            $mid = (int)($_POST['id'] ?? 0);
            $ok = anexoExcluir($conn, $aid);
            echo json_encode(['ok' => $ok, 'anexos' => $mid > 0 ? anexosListar($conn, $mid) : []], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'anexo_upload') {
            $mid = (int)($_POST['id'] ?? 0);
            if ($mid <= 0) { echo json_encode(['ok' => false, 'erro' => 'Salve o imóvel antes de anexar arquivos.']); exit; }
            if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) { echo json_encode(['ok' => false, 'erro' => 'Nenhum arquivo enviado.']); exit; }
            $bytes = @file_get_contents($_FILES['file']['tmp_name']);
            if ($bytes === false || $bytes === '') { echo json_encode(['ok' => false, 'erro' => 'Falha ao ler o arquivo.']); exit; }
            $nome = (string)$_FILES['file']['name'];
            $mime = (string)($_FILES['file']['type'] ?? '');
            $tipo = anexoTipo($nome, $mime);
            $aid = anexoSalvarBytes($conn, $mid, $bytes, $nome, $tipo, $mime ?: null);
            if (!$aid) { echo json_encode(['ok' => false, 'erro' => 'Não foi possível arquivar o anexo.']); exit; }
            echo json_encode(['ok' => true, 'anexo_id' => $aid, 'tipo' => $tipo, 'anexos' => anexosListar($conn, $mid),
                'mensagem' => anexoTipoRotulo($tipo) . ' anexado.'], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'anexo_analisar') {
            // Analisa um PDF (anexo existente via aid, ou upload novo via file) e PREENCHE
            // apenas os campos VAZIOS do imóvel (nunca sobrescreve o que já está preenchido).
            $mid = (int)($_POST['id'] ?? 0);
            if ($mid <= 0) { echo json_encode(['ok' => false, 'erro' => 'Salve o imóvel antes de analisar anexos.']); exit; }
            $cfg = geminiConfigLer();
            if (trim($cfg['api_key']) === '') { echo json_encode(['ok' => false, 'erro' => 'Configure a chave da API do Gemini antes de analisar.']); exit; }

            $bytes = null; $nome = ''; $mime = 'application/pdf'; $aid = (int)($_POST['aid'] ?? 0);
            if ($aid > 0) {
                $a = anexoObter($conn, $aid);
                if (!$a) { echo json_encode(['ok' => false, 'erro' => 'Anexo não encontrado.']); exit; }
                $bytes = @file_get_contents(anexosDir() . '/' . $a['arquivo']); $nome = $a['nome_original']; $mime = $a['mime'] ?: 'application/pdf';
            } elseif (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                $bytes = @file_get_contents($_FILES['file']['tmp_name']); $nome = (string)$_FILES['file']['name']; $mime = (string)($_FILES['file']['type'] ?? 'application/pdf');
            } else { echo json_encode(['ok' => false, 'erro' => 'Nenhum arquivo para analisar.']); exit; }
            if ($bytes === false || $bytes === '') { echo json_encode(['ok' => false, 'erro' => 'Falha ao ler o arquivo para análise.']); exit; }
            if (stripos($mime, 'pdf') === false && strtolower(pathinfo($nome, PATHINFO_EXTENSION)) !== 'pdf') {
                echo json_encode(['ok' => false, 'erro' => 'A análise por IA é para PDF (matrícula/SIGEF). Para KML, use o mapeamento.']); exit;
            }
            $r = geminiExtrairMatricula($cfg, $bytes);
            if (!$r['ok']) { echo json_encode($r); exit; }
            $d = $r['dados'];
            enriquecerCepExtraido($d);

            // se veio de upload novo (sem aid), arquiva agora para reprocessamento futuro
            if ($aid <= 0) { $aid = anexoSalvarBytes($conn, $mid, $bytes, $nome, anexoTipo($nome, $mime, $d), $mime ?: 'application/pdf'); }

            $rs = $conn->query("SELECT * FROM memoriais_mapeados WHERE id = " . (int)$mid . " LIMIT 1");
            $rowAtual = $rs ? $rs->fetch_assoc() : null;
            if (!$rowAtual) { echo json_encode(['ok' => false, 'erro' => 'Imóvel não encontrado.']); exit; }

            // deriva colunas planas dos titulares (sem sobrescrever o que a IA já trouxe em $d)
            $pessoas = qualificacaoNormalizar($d['pessoas'] ?? []);
            qualificacaoDerivarFlat($d, $pessoas);
            $ctxR = itn03ContextoRuralDetectar($d); if ($ctxR !== '') $d['contexto_rural'] = $ctxR; // União/estrangeiro p/ ITN 03

            // monta o conjunto "preencher se vazio" usando uma whitelist de colunas
            $whitelist = array_merge(onrCampos(), ['proprietario', 'cpf', 'tipo_imovel', 'identificador', 'contexto_rural']);
            $sets = []; $vals = []; $types = ''; $preenchidos = [];
            foreach ($whitelist as $col) {
                if (!array_key_exists($col, $d)) continue;
                $v = is_scalar($d[$col]) ? trim((string)$d[$col]) : '';
                if ($v === '') continue;
                if ($col === 'tipo_imovel') { $v = (stripos($v, 'rural') !== false) ? 'rural' : ((stripos($v, 'urban') !== false) ? 'urbano' : ''); if ($v === '') continue; }
                if (array_key_exists($col, $rowAtual) && trim((string)$rowAtual[$col]) !== '') continue; // já preenchido -> respeita
                $sets[] = "`$col` = ?"; $vals[] = $v; $types .= 's'; $preenchidos[] = $col;
            }
            if ($sets) {
                $vals[] = $mid; $types .= 'i';
                $st = $conn->prepare("UPDATE memoriais_mapeados SET " . implode(', ', $sets) . " WHERE id = ?");
                if ($st) { $st->bind_param($types, ...$vals); $st->execute(); }
            }
            // qualificação: só grava se ainda não houver (preenchimento de faltante)
            if ($pessoas && trim((string)($rowAtual['qualificacao_json'] ?? '')) === '') {
                qualificacaoGravar($conn, $mid, $pessoas); $preenchidos[] = 'qualificacao_json';
            }

            // ciclo de vida (encerramento/desmembramento + matrícula anterior) a partir do PDF
            $matAtual = trim((string)($d['numero_matricula'] ?? ($rowAtual['numero_matricula'] ?? '')));
            $cvAplicado = aplicarCicloVida($conn, $mid, $matAtual, $d['ciclo_vida'] ?? []);

            // devolve o registro atualizado (para o formulário recarregar) + anexos
            $rs2 = $conn->query("SELECT * FROM memoriais_mapeados WHERE id = " . (int)$mid . " LIMIT 1");
            $registro = $rs2 ? $rs2->fetch_assoc() : $rowAtual;
            $nFalta = count(array_unique($preenchidos));
            echo json_encode([
                'ok' => true, 'id' => $mid, 'anexo_id' => $aid, 'modelo' => $r['modelo'],
                'registro' => $registro, 'anexos' => anexosListar($conn, $mid),
                'campos' => array_values(array_unique($preenchidos)), 'ciclo_vida' => $cvAplicado,
                'mensagem' => ($nFalta > 0 ? ($nFalta . ' campo(s) faltante(s) preenchido(s) pela IA. Revise e salve.') : 'Nada a preencher — os campos já estavam completos.') . cicloVidaResumo($cvAplicado)
            ], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'carregar') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("SELECT * FROM memoriais_mapeados WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $r = $stmt->get_result();
            $row = $r ? $r->fetch_assoc() : null;
            if (!$row) { echo json_encode(['ok' => false, 'erro' => 'Registro não encontrado.']); exit; }
            // Backfill: imóveis cadastrados via KML antes do arquivamento têm o KML em memorial_descritivo.
            // Se ainda não houver anexo KML, cria um a partir do conteúdo salvo (sem reimportar).
            if (($row['origem'] ?? '') === 'kml') {
                $md = (string)($row['memorial_descritivo'] ?? '');
                if ($md !== '' && (stripos($md, '<kml') !== false || stripos($md, '<coordinates') !== false)) {
                    $temKml = false;
                    foreach (anexosListar($conn, $id) as $ax) { if (($ax['tipo'] ?? '') === 'kml') { $temKml = true; break; } }
                    if (!$temKml) {
                        $nm = trim((string)($row['numero_matricula'] ?? '')); if ($nm === '') $nm = trim((string)($row['identificador'] ?? '')); if ($nm === '') $nm = 'imovel';
                        anexoSalvarBytes($conn, $id, $md, $nm . '.kml', 'kml', 'application/vnd.google-earth.kml+xml');
                    }
                }
            }
            // reconstrói a geometria a partir das coordenadas gravadas (independe da origem)
            $data = buildGeoDataFromWgs84($row['coordenadas_wgs84']);
            echo json_encode(['ok' => true, 'registro' => $row, 'geo' => $data, 'anexos' => anexosListar($conn, $id)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'excluir') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM memoriais_mapeados WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            echo json_encode(['ok' => true]);
            exit;
        }

        echo json_encode(['ok' => false, 'erro' => 'Ação desconhecida.']);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'erro' => 'Erro no servidor: ' . $e->getMessage()]);
        exit;
    }
}

// Página HTML (GET): impede o navegador de servir uma versão em cache, para que
// cada atualização do sistema apareça imediatamente, sem refresh forçado.
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlas Dimensor — Atlas</title>
<!-- ATLAS-DIMENSOR-BUILD: 2026-06-20-sync-multiusuario (armazenamento de PDF/KML por imóvel, modal largo responsivo, dropzone + análise IA p/ campos faltantes) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#f4f6f9; --panel:#ffffff; --panel-2:#f4f6f9; --line:#e3e8ee;
    --ink:#1f2733; --muted:#475569; --faint:#64748b;
    --red:#a80f1e; --red-bright:#cf1626; --red-soft:rgba(168,15,30,.10);
    --green:#1f9d57; --amber:#c8881f;
    --red-text:#a80f1e; --green-text:#14743f; --amber-text:#8a5d00;
    --ov-bg:rgba(255,255,255,.97); --ov-shadow:0 12px 34px rgba(16,24,40,.16);
    --mono:'IBM Plex Mono',ui-monospace,Menlo,monospace;
    --disp:'Inter','Space Grotesk',system-ui,sans-serif;
    --atlas-header:60px;
  }
  /* Tema escuro do Atlas (body.dark-mode) — sobrescreve as variáveis do mapeador */
  body.dark-mode{
    --bg:#0e1217; --panel:#161c24; --panel-2:#1c242e; --line:#283038;
    --ink:#e7edf3; --muted:#8b97a4; --faint:#5d6975;
    --red:#a80f1e; --red-bright:#e2342f; --red-soft:rgba(168,15,30,.16);
    --green:#2faa6a; --amber:#d99a2b;
    --red-text:#f0a3a3; --green-text:#7fd9a8; --amber-text:#e9c07a;
    --ov-bg:rgba(16,20,26,.94); --ov-shadow:0 12px 34px rgba(0,0,0,.46);
  }
  *{box-sizing:border-box}
  html,body{margin:0}
  /* App do mapeador ocupa a área abaixo do header fixo do Atlas */
  .mapeador-shell{position:fixed;top:var(--header-height,60px);left:0;right:0;bottom:0;
    font-family:var(--disp);background:var(--bg);color:var(--ink);
    display:grid;grid-template-columns:420px 1fr;overflow:hidden;z-index:1}
  .panel{background:var(--panel);border-right:1px solid var(--line);display:flex;flex-direction:column;min-height:0}
  .head{padding:18px 22px 15px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:10px}
  .back-atlas{flex:none;font-size:11px;font-weight:600;font-family:var(--mono);color:var(--muted);
    text-decoration:none;border:1px solid var(--line);border-radius:8px;padding:6px 10px;white-space:nowrap;transition:all .15s}
  .back-atlas:hover{color:var(--ink);border-color:var(--red-bright)}
  .brand{display:flex;align-items:center;gap:11px}
  .mark{width:30px;height:30px;border-radius:7px;flex:none;
    background:linear-gradient(135deg,#0d9488 0%,#1d4ed8 100%);display:grid;place-items:center;
    box-shadow:0 2px 10px rgba(13,148,136,.4)}
  .mark svg{width:17px;height:17px}
  .brand h1{font-size:15px;font-weight:600;margin:0;line-height:1.1}
  .brand p{margin:2px 0 0;font-size:11px;color:var(--muted);font-family:var(--mono)}
  .body{padding:18px 22px;overflow-y:auto;flex:1;min-height:0}
  .label{font-family:var(--mono);font-size:10.5px;letter-spacing:1.4px;text-transform:uppercase;
    color:var(--faint);margin:0 0 8px}
  textarea,input,select{width:100%;background:var(--bg);color:var(--ink);border:1px solid var(--line);
    border-radius:9px;padding:10px 12px;font-family:var(--mono);font-size:12px;outline:none;transition:border-color .15s}
  textarea{height:140px;resize:vertical;line-height:1.5;font-size:11.5px}
  textarea:focus,input:focus,select:focus{border-color:var(--red-bright)}
  .row{display:grid;grid-template-columns:1fr 130px;gap:9px;margin-top:14px}
  .field-label{font-size:11px;color:var(--muted);margin:0 0 5px;font-family:var(--mono)}
  .actions{display:flex;gap:9px;margin-top:13px}
  button{font-family:var(--disp);cursor:pointer;border:none;border-radius:8px;font-size:13px;font-weight:500;
    transition:filter .15s,background .15s,border-color .15s,color .15s}
  .btn-primary{flex:1;background:var(--red);color:#fff;padding:11px;box-shadow:0 2px 12px var(--red-soft)}
  .btn-primary:hover{filter:brightness(1.12)}
  .btn-primary:disabled{opacity:.5;cursor:default;filter:none}
  .btn-save{flex:1;background:var(--green);color:#06140c;padding:11px;font-weight:600}
  .btn-save:hover{filter:brightness(1.08)}
  .btn-save:disabled{opacity:.4;cursor:default;filter:none}
  .btn-ghost{background:transparent;color:var(--muted);border:1px solid var(--line);padding:11px 14px}
  .btn-ghost:hover{border-color:var(--red-bright);color:var(--ink)}
  .status{margin-top:13px;font-family:var(--mono);font-size:11.5px;padding:9px 12px;border-radius:8px;line-height:1.45;display:none}
  .muni-box{margin-top:18px;padding-top:16px;border-top:1px dashed var(--line)}
  .muni-label{display:flex;align-items:center;gap:7px;color:var(--faint)}
  .muni-row{display:flex;gap:9px;margin-top:2px}
  #muni-status{margin-top:10px}
  /* Selo de pertencimento sobre o mapa */
  .muni-badge{position:absolute;left:14px;bottom:120px;z-index:5;display:none;max-width:340px;
    font-family:var(--mono);font-size:11.5px;font-weight:600;line-height:1.35;padding:8px 12px;border-radius:9px;
    backdrop-filter:blur(10px);box-shadow:var(--ov-shadow);border:1px solid var(--line)}
  .muni-badge.dentro{background:rgba(31,157,87,.16);border-color:rgba(31,157,87,.5);color:var(--green-text)}
  .muni-badge.parcial{background:rgba(200,136,31,.16);border-color:rgba(200,136,31,.5);color:var(--amber-text)}
  .muni-badge.fora{background:var(--red-soft);border-color:rgba(168,15,30,.5);color:var(--red-text)}
  /* Estilo do limite municipal desenhado (legenda) */
  .legend .sw.muni{background:rgba(37,99,235,.25);border:1px solid #2563eb}
  /* Seletor de cor de destaque (painel) */
  .cor-box{margin-top:18px;padding-top:16px;border-top:1px dashed var(--line)}
  .cor-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:7px;margin-top:4px}
  .cor-sw{width:100%;aspect-ratio:1/1;min-height:24px;border-radius:7px;border:2px solid transparent;cursor:pointer;
    box-shadow:inset 0 0 0 1px rgba(0,0,0,.12);transition:transform .1s;padding:0}
  .cor-sw:hover{transform:scale(1.08)}
  .cor-sw.sel{border-color:var(--ink);box-shadow:0 0 0 2px var(--panel),0 0 0 4px var(--ink)}
  .cor-hint{font-family:var(--mono);font-size:9.5px;color:var(--faint);line-height:1.45;margin-top:9px}
  /* Accordion de dados ONR */
  .onr-box{margin-top:14px}
  .onr-accordion{border:1px solid var(--line);border-radius:11px;background:var(--bg);overflow:hidden}
  .onr-accordion>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:8px;padding:11px 13px;font-family:var(--disp);font-size:13px;font-weight:600;color:var(--ink)}
  .onr-accordion>summary::-webkit-details-marker{display:none}
  .onr-accordion>summary::after{content:'▾';margin-left:auto;color:var(--faint);transition:transform .2s}
  .onr-accordion[open]>summary::after{transform:rotate(180deg)}
  .onr-hint-active{font-family:var(--mono);font-size:10px;font-weight:400;color:var(--faint)}
  .onr-body{padding:4px 13px 14px;border-top:1px solid var(--line)}
  .onr-sub{margin-top:10px;border:1px solid var(--line);border-radius:9px;overflow:hidden}
  .onr-sub>summary{list-style:none;cursor:pointer;padding:8px 11px;font-family:var(--mono);font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;color:var(--faint);background:var(--panel)}
  .onr-sub>summary::-webkit-details-marker{display:none}
  .onr-sub>summary::after{content:'+';float:right;color:var(--faint);font-weight:700}
  .onr-sub[open]>summary::after{content:'–'}
  .qual-list{padding:8px 10px;display:flex;flex-direction:column;gap:8px}
  .qual-card{border:1px solid var(--line);border-radius:8px;padding:8px 10px;background:var(--panel)}
  .qual-head{display:flex;align-items:center;gap:6px;margin-bottom:5px;font-size:12.5px}
  .qual-tag{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:1px 6px;border-radius:6px}
  .qual-tag.adq{background:rgba(19,105,63,.14);color:#13693f}
  .qual-tag.alien{background:rgba(168,15,30,.14);color:#a80f1e}
  .qual-row{display:flex;gap:8px;font-size:11.5px;line-height:1.45}
  .qual-k{flex:0 0 116px;color:var(--faint);font-family:var(--mono);font-size:10px;text-transform:uppercase;letter-spacing:.4px;padding-top:1px}
  .qual-v{flex:1;color:var(--ink)}
  .onr-sub .form-grid{padding:10px 11px 11px}
  .onr-sub input[readonly]{opacity:.65;background:var(--panel);cursor:not-allowed}
  /* Popup de cor sobre o mapa (InfoWindow — bolha branca) */
  .cor-pop{font-family:var(--disp);min-width:200px;color:#1f2733}
  .cor-pop-t{font-size:13px;font-weight:700;line-height:1.2}
  .cor-pop-sub{font-size:11px;color:#6b7785;margin:1px 0 9px}
  /* Bloco de informações do imóvel no popup do mapa */
  .ip-box{margin:2px 0 11px;padding:9px 10px;background:#f4f6f9;border:1px solid #e3e8ee;border-radius:8px;display:flex;flex-direction:column;gap:4px}
  .ip-row{display:flex;gap:8px;font-size:11.5px;line-height:1.35}
  .ip-k{flex:none;width:78px;color:#64748b;font-family:var(--mono);font-size:9.5px;text-transform:uppercase;letter-spacing:.4px;padding-top:1px}
  .ip-v{flex:1;color:#1f2733;font-weight:600;word-break:break-word}
  /* Acordeão das opções de cor no popup */
  .cor-pop-acc{margin-top:2px}
  .cor-pop-acc>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:6px;margin-bottom:0;padding:3px 0;user-select:none}
  .cor-pop-acc>summary::-webkit-details-marker{display:none}
  .cor-pop-acc>summary::after{content:'▾';margin-left:auto;font-size:11px;transition:transform .2s}
  .cor-pop-acc[open]>summary::after{transform:rotate(180deg)}
  .cor-pop-acc>summary:hover{color:#0d9488}
  /* Dialog do mapa (InfoWindow) no modo escuro */
  body.dark-mode .gm-style .gm-style-iw-c,
  body.dark-mode .gm-style .gm-style-iw-d{background:#161c24 !important}
  body.dark-mode .gm-style .gm-style-iw-d{overflow:auto !important}
  body.dark-mode .gm-style .gm-style-iw-t::after{background:linear-gradient(45deg,#161c24 50%,rgba(0,0,0,0) 51%) !important}
  body.dark-mode .gm-style .gm-style-iw-tc::after{background:#161c24 !important}
  body.dark-mode .gm-style .gm-style-iw-c button img,
  body.dark-mode .gm-style .gm-ui-hover-effect img{filter:invert(1) brightness(1.6) !important}
  body.dark-mode .cor-pop{color:#e7edf3}
  body.dark-mode .cor-pop-sub{color:#8b97a4}
  body.dark-mode .cor-pop-lbl{color:#8b97a4}
  body.dark-mode .ip-box{background:#1c242e;border-color:#283038}
  body.dark-mode .ip-k{color:#8b97a4}
  body.dark-mode .ip-v{color:#e7edf3}
  body.dark-mode .cor-pop-clear{background:#1c242e;border-color:#283038;color:#e2342f}
  body.dark-mode .cor-pop-clear:hover{background:#2a1a1c;border-color:#a80f1e}
  .cor-pop-lbl{font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:#9aa6b2;margin-bottom:6px}
  .cor-pop-grid{display:grid;grid-template-columns:repeat(6,22px);gap:6px}
  .cor-pop .cor-sw{width:22px;height:22px;aspect-ratio:auto;min-height:0}
  .cor-pop-clear{margin-top:11px;width:100%;font-family:var(--disp);font-size:11px;font-weight:600;color:#a80f1e;
    background:#fff;border:1px solid #e3e8ee;border-radius:7px;padding:6px;cursor:pointer}
  .cor-pop-clear:hover{background:#faf0f1;border-color:#a80f1e}
  .status.ok{display:block;background:rgba(31,157,87,.10);color:var(--green-text);border:1px solid rgba(31,157,87,.30)}
  .status.err{display:block;background:var(--red-soft);color:var(--red-text);border:1px solid rgba(168,15,30,.30)}
  .status.warn{display:block;background:rgba(200,136,31,.12);color:var(--amber-text);border:1px solid rgba(200,136,31,.35)}
  .stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}
  .stat{background:var(--panel-2);border:1px solid var(--line);border-radius:10px;padding:12px 13px}
  .stat .v{font-family:var(--mono);font-size:19px;font-weight:600;letter-spacing:-.5px}
  .stat .u{font-size:12px;color:var(--faint);font-weight:400}
  .stat .k{font-size:10px;color:var(--muted);margin-top:3px;font-family:var(--mono);text-transform:uppercase;letter-spacing:.8px}
  .saved{margin-top:24px}
  .saved h3{font-family:var(--mono);font-size:10.5px;letter-spacing:1.4px;text-transform:uppercase;color:var(--faint);margin:0 0 10px}
  .item{display:flex;align-items:center;gap:10px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;margin-bottom:8px;cursor:pointer;transition:border-color .15s,background .15s}
  .item:hover{border-color:var(--red-bright);background:rgba(168,15,30,.06)}
  .item.sel{border-color:#f59e0b;background:rgba(245,158,11,.12)}
  .item.sel .ic{background:#f59e0b}
  .item.destaque{border-color:#0d9488;background:rgba(13,148,136,.12);box-shadow:0 0 0 1px rgba(13,148,136,.35) inset}
  /* Popup do mapa sempre acima dos rótulos/elementos */
  .gm-style .gm-style-iw-c,.gm-style .gm-style-iw-t,.gm-style .gm-style-iw{z-index:99999 !important}
  .map-chip{z-index:1}
  .item .ic{width:7px;height:7px;border-radius:2px;background:var(--red-bright);flex:none}
  .item .info{flex:1;min-width:0}
  .item .nm{font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .item .mt{font-family:var(--mono);font-size:10px;color:var(--muted);margin-top:2px}
  .item .tag{font-family:var(--mono);font-size:9px;letter-spacing:.5px;padding:2px 6px;border-radius:4px;
    background:rgba(139,151,164,.14);color:var(--muted);text-transform:uppercase}
  .item .tag.mat{background:var(--red-soft);color:#f0a3a3}
  .item .del{background:transparent;border:none;color:var(--faint);font-size:15px;padding:2px 6px;border-radius:5px}
  .item .del:hover{color:var(--red-bright);background:rgba(226,52,47,.1)}
  .empty-list{font-family:var(--mono);font-size:11px;color:var(--faint);padding:6px 2px}
  .map-wrap{position:relative;min-width:0}
  #map{position:absolute;inset:0;background:#0a0d11}
  .readout{position:absolute;left:14px;bottom:80px;z-index:5;background:rgba(14,18,23,.86);
    backdrop-filter:blur(8px);border:1px solid var(--line);border-radius:9px;padding:9px 13px;
    font-family:var(--mono);font-size:11px;color:var(--muted);display:none}
  .readout b{color:var(--ink);font-weight:500}
  .readout .dot{color:var(--red-bright)}

  /* KML import */
  .kml-zone{margin-top:11px;display:flex;align-items:center;gap:9px;padding:11px 13px;border:1px dashed var(--line);
    border-radius:9px;color:var(--muted);cursor:pointer;font-size:12.5px;transition:border-color .15s,color .15s,background .15s}
  .kml-zone:hover,.kml-zone.drag{border-color:var(--red-bright);color:var(--ink);background:rgba(168,15,30,.05)}
  .kml-zone b{color:var(--ink);font-weight:600}
  .kml-zone.loaded{border-style:solid;border-color:rgba(47,170,106,.4);color:#7fd9a8}
  .kml-zone.lote{margin-top:8px}
  .kml-zone.lote:hover,.kml-zone.lote.drag{border-color:#0d9488;color:var(--ink);background:rgba(13,148,136,.06)}
  .kml-zone.ia{margin-top:8px}
  .zone-multi{font-family:var(--mono);font-size:9.5px;color:var(--faint);opacity:.85}
  .kml-zone.ia:hover,.kml-zone.ia.drag{border-color:#7c3aed;color:var(--ink);background:rgba(124,58,237,.07)}
  .link-config{display:block;width:100%;margin-top:6px;background:none;border:none;color:var(--faint);
    font-family:var(--mono);font-size:10px;cursor:pointer;text-align:left;padding:3px 1px}
  .link-config:hover{color:#7c3aed}
  .gem-models{display:flex;flex-wrap:wrap;gap:5px;min-height:22px}

  /* Cabeçalho da lista + botão ver todos */
  .saved-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
  .vista-toggle{display:flex;gap:4px;background:var(--panel2,rgba(128,128,128,.08));border:1px solid var(--line);border-radius:9px;padding:3px;margin-bottom:8px}
  .vista-toggle .vt-btn{flex:1;border:none;background:transparent;color:var(--ink,#555);font-size:11.5px;font-weight:600;padding:6px 8px;border-radius:7px;cursor:pointer;transition:.15s;white-space:nowrap}
  .vista-toggle .vt-btn.active{background:var(--card,#fff);color:var(--brand,#0d9488);box-shadow:0 1px 3px rgba(0,0,0,.12)}
  .vt-count{display:inline-block;min-width:16px;padding:0 5px;margin-left:2px;border-radius:9px;background:rgba(13,148,136,.18);color:#0d9488;font-size:10px;line-height:15px}
  .itn03-actions{display:flex;gap:6px;margin-bottom:8px}
  .itn03-actions .mini-btn{flex:1}
  .item .itn03-badge{display:inline-block;font-size:9.5px;font-weight:700;padding:1px 6px;border-radius:6px;background:rgba(99,102,241,.16);color:#4f46e5;margin-left:4px}
  .item .itn03-apto{font-size:10px;font-weight:600;margin-left:4px}
  .item .itn03-apto.ok{color:#13693f}
  .item .itn03-apto.no{color:#a80f1e}
  .saved-head h3{margin:0}
  .mini-btn{font-family:var(--mono);font-size:10px;letter-spacing:.5px;padding:6px 10px;background:transparent;
    border:1px solid var(--line);color:var(--muted);border-radius:6px}
  .mini-btn:hover{border-color:var(--red-bright);color:var(--ink)}
  .mini-btn.active{background:var(--red-soft);border-color:var(--red-bright);color:#f0a3a3}

  /* Painel de visão geral / sobreposições */
  .overview-panel{position:absolute;top:60px;right:14px;z-index:6;width:280px;max-height:calc(100% - 74px);
    display:none;flex-direction:column;background:var(--ov-bg);color:var(--ink);backdrop-filter:blur(10px);
    border:1px solid var(--line);border-radius:11px;overflow:hidden;box-shadow:var(--ov-shadow)}
  .overview-panel.show{display:flex}
  .overview-panel.dragging{transition:none;cursor:grabbing}
  .ovh{display:flex;align-items:flex-start;justify-content:space-between;padding:13px 15px;border-bottom:1px solid var(--line);
    cursor:grab;user-select:none;-webkit-user-select:none;touch-action:none}
  .ovh:active{cursor:grabbing}
  .ovh-title{font-size:13px;font-weight:600}
  .ovh-sub{font-family:var(--mono);font-size:10.5px;color:var(--muted);margin-top:3px}
  .ov-close{background:transparent;border:none;color:var(--faint);font-size:18px;line-height:1;padding:0 2px;border-radius:5px}
  .ov-close:hover{color:var(--red-bright)}
  .legend{display:flex;gap:16px;padding:10px 15px;border-bottom:1px solid var(--line);font-family:var(--mono);
    font-size:10.5px;color:var(--muted)}
  .legend span{display:flex;align-items:center;gap:6px}
  .legend .sw{width:13px;height:9px;border-radius:2px;display:inline-block}
  .legend .sw.normal{background:rgba(22,163,74,.35);border:1px solid #16a34a}
  .legend .sw.over{background:rgba(226,52,47,.5);border:1px solid var(--red-bright)}
  .legend .sw.sel{background:rgba(245,158,11,.45);border:1px solid #f59e0b}
  .ov-hint{font-family:var(--mono);font-size:9.5px;color:var(--faint);line-height:1.4;padding:8px 15px 0}
  .ov-search{display:flex;gap:6px;padding:8px 10px 2px}
  .ov-itn03{padding:6px 10px 2px}
  .btn-itn03{width:100%;padding:8px 10px;border:1px solid #1f7a4d;background:#e8f7ee;color:#13693f;border-radius:8px;font-weight:600;font-size:12px;cursor:pointer;transition:.15s}
  .btn-itn03:hover{background:#d6f0e0}
  .btn-itn03:disabled{opacity:.6;cursor:default}
  .ov-search input{flex:1;background:var(--panel-2);border:1px solid var(--line);border-radius:8px;color:var(--ink);
    font-size:12px;padding:7px 9px;font-family:var(--disp);outline:none}
  .ov-search input:focus{border-color:#0d9488}
  .ov-search input::placeholder{color:var(--faint)}
  .ov-search button{flex:none;width:30px;height:34px;background:var(--panel-2);border:1px solid var(--line);
    border-radius:8px;color:var(--faint);font-size:16px;line-height:1;cursor:pointer}
  .ov-search button:hover{border-color:var(--red-bright);color:var(--red-bright)}
  .ov-overlaps{overflow-y:auto;padding:8px 10px}
  .ov-overlaps .ttl{font-family:var(--mono);font-size:10px;letter-spacing:1px;text-transform:uppercase;
    color:var(--faint);padding:4px 5px 8px}
  .ov-row{padding:9px 11px;border:1px solid rgba(226,52,47,.25);background:rgba(226,52,47,.06);border-radius:8px;
    margin-bottom:7px;cursor:pointer;transition:background .15s}
  .ov-row:hover{background:rgba(226,52,47,.13)}
  .ov-row .pair{font-size:12px;font-weight:500;line-height:1.35}
  .ov-row .amt{font-family:var(--mono);font-size:10.5px;color:var(--red-text);margin-top:3px}
  .ov-tag{display:inline-block;font-family:var(--mono);font-size:8.5px;font-weight:700;letter-spacing:.3px;
    text-transform:uppercase;padding:1px 6px;border-radius:7px;vertical-align:middle;margin-left:4px;white-space:nowrap}
  .ov-tag.material{background:rgba(226,52,47,.18);color:var(--red-text);border:1px solid rgba(226,52,47,.5)}
  .ov-tag.formal{background:rgba(245,158,11,.16);color:#b45309;border:1px solid rgba(180,83,9,.45)}
  .ov-none{font-family:var(--mono);font-size:11px;color:var(--green-text);padding:10px 6px}
  .ov-foot{padding:11px;border-top:1px solid var(--line)}

  /* Botão flutuante para reexibir o painel */
  .ov-reopen{position:absolute;top:60px;right:14px;z-index:6;display:none;align-items:center;gap:8px;
    background:var(--ov-bg);backdrop-filter:blur(10px);border:1px solid var(--line);color:var(--ink);
    border-radius:9px;padding:9px 13px;font-size:12px;font-weight:500;box-shadow:var(--ov-shadow)}
  .ov-reopen:hover{border-color:var(--red-bright)}
  .ov-reopen.show{display:flex}

  /* Barra de seleção (Ctrl+clique) */
  .sel-bar{position:absolute;bottom:80px;left:50%;transform:translateX(-50%);z-index:7;display:none;
    align-items:center;gap:12px;background:rgba(14,18,23,.94);backdrop-filter:blur(10px);
    border:1px solid #f59e0b;border-radius:11px;padding:9px 12px 9px 16px;box-shadow:0 6px 24px rgba(0,0,0,.4)}
  .sel-bar.show{display:flex}
  .sel-count{font-family:var(--mono);font-size:12px;color:var(--muted)}
  .sel-count b{color:#f59e0b;font-size:14px}
  .sel-rep{background:var(--red);color:#fff;padding:8px 14px;border-radius:8px;font-size:12.5px;font-weight:500}
  .sel-rep:hover{filter:brightness(1.12)}
  .sel-clear{background:transparent;border:1px solid var(--line);color:var(--muted);padding:8px 12px;border-radius:8px;font-size:12px}
  .sel-clear:hover{border-color:#f59e0b;color:var(--ink)}

  /* Botão de relatório por sobreposição na linha */
  .ov-row{position:relative}
  .ov-row .row-rep{position:absolute;top:8px;right:8px;background:rgba(168,15,30,.85);border:none;color:#fff;
    font-family:var(--mono);font-size:9px;letter-spacing:.4px;padding:4px 7px;border-radius:5px;opacity:0;transition:opacity .12s}
  .ov-row:hover .row-rep{opacity:1}
  .ov-row .row-rep:hover{background:var(--red-bright)}
  .btn-report{width:100%;background:var(--red);color:#fff;padding:10px;border-radius:8px;font-size:12.5px;
    font-weight:500;box-shadow:0 2px 12px var(--red-soft)}
  .btn-report:hover{filter:brightness(1.12)}

  /* Painel de importação KML (nomear cada imóvel) */
  .kml-panel{width:320px}
  .kml-rows{overflow-y:auto;padding:9px 11px;flex:1}
  .kml-row{padding:9px;border:1px solid var(--line);border-radius:8px;margin-bottom:8px;background:var(--panel-2)}
  .kml-row.sel{border-color:var(--red-bright)}
  .kml-row .top{display:flex;align-items:center;gap:7px;margin-bottom:7px}
  .kml-row .idx{font-family:var(--mono);font-size:10px;color:var(--red-bright);font-weight:600;flex:none}
  .kml-row .meta{font-family:var(--mono);font-size:9.5px;color:var(--faint);margin-left:auto}
  .kml-row .inp{display:grid;grid-template-columns:1fr 96px;gap:7px}
  .kml-row input,.kml-row select{padding:7px 9px;font-size:11.5px;border-radius:7px}
  .kml-foot{padding:11px;border-top:1px solid var(--line)}

  /* Rótulo (nome/matrícula) sobre o polígono no mapa */
  .map-chip{position:absolute;transform:translate(-50%,-50%);background:rgba(14,18,23,.82);color:#fff;
    font-family:var(--mono);font-size:11px;font-weight:600;padding:2px 8px;border-radius:6px;
    border:1px solid rgba(226,52,47,.65);white-space:nowrap;pointer-events:none;
    text-shadow:0 1px 2px rgba(0,0,0,.85);letter-spacing:.2px}
  .map-chip.clic{pointer-events:auto;cursor:pointer;transition:transform .1s ease,background .12s ease,border-color .12s ease}
  .map-chip.clic:hover{background:rgba(13,148,136,.96);border-color:rgba(255,255,255,.55);
    transform:translate(-50%,-50%) scale(1.08);box-shadow:0 3px 12px rgba(0,0,0,.45);z-index:5}
  .map-chip.hover{transform:translate(-50%,calc(-50% - 20px));background:rgba(13,148,136,.96);
    border-color:rgba(255,255,255,.35);font-family:var(--disp);font-weight:600;letter-spacing:.1px;
    box-shadow:0 4px 14px rgba(0,0,0,.4);animation:chipFade .14s ease-out}
  .map-chip.vizinho{background:rgba(180,83,9,.94);border-color:rgba(251,191,36,.7);color:#fff;
    font-family:var(--disp);font-weight:700;box-shadow:0 3px 12px rgba(0,0,0,.45)}
  @keyframes chipFade{from{opacity:0;transform:translate(-50%,calc(-50% - 12px))}to{opacity:1;transform:translate(-50%,calc(-50% - 20px))}}
  .overlay{position:absolute;inset:0;display:grid;place-items:center;z-index:4;color:var(--faint);
    font-family:var(--mono);font-size:12px;text-align:center;pointer-events:none}
  ::-webkit-scrollbar{width:9px;height:9px}
  ::-webkit-scrollbar-thumb{background:var(--line);border-radius:5px}
  ::-webkit-scrollbar-thumb:hover{background:#3a444f}
  /* ===== Largura do painel (mais enxuta) ===== */
  .mapeador-shell{grid-template-columns:360px 1fr}
  .panel{transition:width .3s ease, opacity .25s ease}
  /* ===== Formulário de cadastro ===== */
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:11px 12px;margin-top:6px}
  .fld{display:flex;flex-direction:column;min-width:0}
  .fld .field-label{margin:0 0 5px}
  .grid-2{grid-column:1 / -1}
  /* ===== Busca ===== */
  .search-wrap{position:relative;margin:4px 0 10px}
  .search-ic{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--faint);pointer-events:none}
  #busca{width:100%;padding:9px 30px 9px 32px;background:var(--bg);border:1px solid var(--line);border-radius:9px;
    color:var(--ink);font-family:var(--disp);font-size:13px;outline:none}
  #busca:focus{border-color:var(--red-bright)}
  .search-clear{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;
    color:var(--faint);font-size:18px;cursor:pointer;line-height:1;padding:0 4px}
  .search-clear:hover{color:var(--red-bright)}
  /* ===== Itens da lista ===== */
  .item-dot{width:10px;height:10px;border-radius:50%;flex:none;box-shadow:inset 0 0 0 1px rgba(0,0,0,.12)}
  .item-dot.vazio{background:var(--line)}
  .item .it-edit{background:transparent;border:none;color:var(--faint);font-size:14px;cursor:pointer;padding:2px 5px;border-radius:6px;flex:none}
  .item .it-edit:hover{color:var(--red-bright);background:var(--red-soft)}
  .tag.urb{background:rgba(37,99,235,.14);color:#2563eb;border:1px solid rgba(37,99,235,.3)}
  .tag.rural{background:rgba(22,163,74,.14);color:#16a34a;border:1px solid rgba(22,163,74,.3)}
  /* Envio ONR na lista */
  .saved-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
  .mini-btn.onr{background:linear-gradient(135deg,#0d9488,#1d4ed8);color:#fff;border-color:transparent}
  .mini-btn.onr:hover{filter:brightness(1.08)}
  .item .it-onr{background:transparent;border:none;color:#0d9488;font-size:13px;cursor:pointer;padding:2px 5px;border-radius:6px;flex:none}
  .item .it-onr:hover:not(:disabled){background:rgba(13,148,136,.14)}
  .item .it-onr:disabled{color:var(--line);cursor:not-allowed}
  .item .it-onr.enviado{color:#1d4ed8}
  .onr-badge{display:inline-block;font-family:var(--mono);font-size:9px;padding:1px 5px;border-radius:5px;background:var(--line);color:var(--faint);margin-left:4px;vertical-align:middle}
  .onr-badge.env{background:rgba(29,78,216,.16);color:#1d4ed8}
  /* Matrícula encerrada ("morta") */
  .item.morto{opacity:.6}
  .item.morto .nm{text-decoration:line-through;text-decoration-thickness:1px;text-decoration-color:var(--faint)}
  .morto-badge{display:inline-block;font-family:var(--mono);font-size:9px;padding:1px 5px;border-radius:5px;background:rgba(120,130,145,.18);color:#8893a3;margin-left:4px;vertical-align:middle;text-decoration:none}
  .desmembra-badge{display:inline-block;font-family:var(--mono);font-size:9px;padding:1px 5px;border-radius:5px;background:rgba(13,148,136,.16);color:#0d9488;margin-left:4px;vertical-align:middle;text-decoration:none}
  .fora-badge{display:inline-block;font-family:var(--mono);font-size:9px;font-weight:700;padding:1px 5px;border-radius:5px;background:rgba(226,52,47,.16);color:#e2342f;border:1px solid rgba(226,52,47,.4);margin-left:4px;vertical-align:middle;text-decoration:none}
  .item.fora-mun{box-shadow:inset 3px 0 0 #e2342f}
  .situacao-edit{margin-top:6px;padding-top:11px;border-top:1px solid var(--line)}
  /* Multi-entrada de matrículas (chips) */
  .chips{display:flex;flex-wrap:wrap;gap:5px;min-height:24px;margin-bottom:6px}
  .chips-vazio{font-family:var(--mono);font-size:10px;color:var(--faint);font-style:italic}
  .chip{display:inline-flex;align-items:center;gap:5px;background:rgba(13,148,136,.14);color:#0d9488;
    font-family:var(--mono);font-size:11px;padding:2px 4px 2px 8px;border-radius:6px;border:1px solid rgba(13,148,136,.3)}
  .chip-x{background:none;border:none;color:#0d9488;cursor:pointer;font-size:14px;line-height:1;padding:0 2px;border-radius:4px}
  .chip-x:hover{background:rgba(13,148,136,.2)}
  .chips-add{display:flex;gap:6px}
  .chips-add input{flex:1}
  .btn-ghost-sm{background:var(--panel);border:1px solid var(--line);color:var(--ink);border-radius:8px;
    padding:0 12px;font-size:11px;cursor:pointer;white-space:nowrap}
  .btn-ghost-sm:hover{border-color:#0d9488;color:#0d9488}
  /* Lista de proprietários (vários, PF/PJ) */
  .prop-list{display:flex;flex-direction:column;gap:7px}
  .prop-row{display:flex;gap:6px;align-items:center}
  .prop-row .prop-nome{flex:1.3}
  .prop-doc-wrap{flex:1;position:relative;display:flex;align-items:center}
  .prop-doc-wrap .prop-doc{width:100%;padding-right:64px}
  .prop-doc-badge{position:absolute;right:8px;font-family:var(--mono);font-size:8.5px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:var(--faint);pointer-events:none}
  .prop-doc-badge.ok{color:#1f9d57}
  .prop-doc-badge.bad{color:#cf1626}
  .prop-doc.doc-ok{border-color:rgba(31,157,87,.55)}
  .prop-doc.doc-bad{border-color:rgba(207,22,38,.6)}
  .prop-del{flex:none;width:32px;height:34px;background:var(--panel);border:1px solid var(--line);border-radius:8px;color:var(--faint);font-size:16px;line-height:1;cursor:pointer}
  .prop-del:hover{border-color:#cf1626;color:#cf1626}
  /* Aviso de matrícula encerrada no cadastro */
  .enc-info{margin-bottom:11px;padding:10px 12px;border:1px solid rgba(120,130,145,.35);border-left:3px solid #8893a3;border-radius:9px;background:rgba(120,130,145,.10)}
  .enc-info-h{display:flex;align-items:center;gap:6px;font-family:var(--disp);font-size:12.5px;font-weight:600;color:var(--ink)}
  .enc-ico{color:#8893a3;font-size:13px}
  .enc-info-b{margin-top:5px;font-size:11.5px;line-height:1.55;color:var(--ink)}
  .enc-mut{color:var(--faint);font-family:var(--mono);font-size:9.5px}
  .enc-info.desmembra{border-color:rgba(13,148,136,.4);border-left-color:#0d9488;background:rgba(13,148,136,.08)}
  .enc-info.desmembra .enc-ico{color:#0d9488}
  /* ===== Slider de intensidade ===== */
  .op-wrap{display:flex;align-items:center;gap:10px;margin-top:11px}
  .op-lbl{font-family:var(--mono);font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--faint);flex:none}
  .op-range{-webkit-appearance:none;appearance:none;flex:1;height:5px;border-radius:3px;
    background:linear-gradient(90deg,var(--line),var(--muted));outline:none;cursor:pointer}
  .op-range::-webkit-slider-thumb{-webkit-appearance:none;width:16px;height:16px;border-radius:50%;background:var(--red);
    border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.3);cursor:pointer}
  .op-range::-moz-range-thumb{width:16px;height:16px;border-radius:50%;background:var(--red);border:2px solid #fff;cursor:pointer}
  .cor-pop .op-range{width:100%;margin:2px 0}
  /* ===== Botão recolher painel ===== */
  .toggle-panel{position:absolute;top:50%;left:12px;transform:translateY(-50%);z-index:7;width:30px;height:46px;display:flex;align-items:center;justify-content:center;
    background:var(--ov-bg);border:1px solid var(--line);border-radius:9px;color:var(--ink);cursor:pointer;box-shadow:var(--ov-shadow);backdrop-filter:blur(10px)}
  .toggle-panel:hover{border-color:var(--red-bright)}
  .toggle-panel .ic-expand{display:none}
  body.panel-collapsed .panel{width:0;min-width:0;border-right:none;overflow:hidden;opacity:0;pointer-events:none}
  body.panel-collapsed .mapeador-shell{grid-template-columns:0 1fr}
  body.panel-collapsed .toggle-panel .ic-collapse{display:none}
  body.panel-collapsed .toggle-panel .ic-expand{display:block}
  /* ===== Modal de edição ===== */
  .modal-ov{position:fixed;inset:0;z-index:1200;background:rgba(8,12,18,.55);backdrop-filter:blur(3px);
    display:none;align-items:center;justify-content:center;padding:18px}
  .swal2-container{z-index:100050 !important}
  .modal-ov.show{display:flex}
  .modal-card{width:100%;max-width:440px;background:var(--panel);color:var(--ink);border:1px solid var(--line);
    border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.4);overflow:hidden;animation:modalIn .18s ease;
    display:flex;flex-direction:column;max-height:calc(100vh - 36px)}
  @keyframes modalIn{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:none}}
  .modal-h{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-bottom:1px solid var(--line)}
  .modal-h h3{margin:0;font-size:15px;font-weight:600}
  .modal-x{background:none;border:none;color:var(--faint);font-size:22px;line-height:1;cursor:pointer}
  .modal-x:hover{color:var(--red-bright)}
  .modal-b{padding:18px;display:flex;flex-direction:column;gap:12px;overflow-y:auto}
  .modal-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .modal-f{display:flex;gap:10px;padding:14px 18px;border-top:1px solid var(--line);justify-content:flex-end}
  .modal-f .btn-primary,.modal-f .btn-ghost{width:auto;padding:9px 16px}
  /* ===== Modal de edição — largo, responsivo e organizado ===== */
  #modal-edit .modal-card{max-width:1020px}
  #modal-edit .modal-b{padding:16px 18px;gap:14px}
  .ed-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start}
  .ed-col{display:flex;flex-direction:column;gap:14px;min-width:0}
  .ed-section{border:1px solid var(--line);border-radius:12px;background:var(--bg);overflow:hidden}
  .ed-section>.ed-sec-head{display:flex;align-items:center;gap:8px;padding:10px 13px;background:var(--panel);border-bottom:1px solid var(--line)}
  .ed-section>.ed-sec-head .ed-sec-ic{display:flex;color:var(--red)}
  .ed-section>.ed-sec-head h4{margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--ink)}
  .ed-section>.ed-sec-head .ed-sec-sub{margin-left:auto;font-size:10.5px;color:var(--faint)}
  .ed-section>.ed-sec-body{padding:13px;display:flex;flex-direction:column;gap:11px}
  #modal-edit .onr-box{margin:0}
  #modal-edit .onr-accordion{border:1px solid var(--line);border-radius:12px;overflow:hidden}
  /* Dropzone de anexos */
  .ed-drop{border:1.6px dashed var(--line);border-radius:11px;padding:16px 12px;text-align:center;cursor:pointer;
    transition:.15s;background:var(--panel);color:var(--faint)}
  .ed-drop:hover,.ed-drop.drag{border-color:var(--red);background:rgba(168,15,30,.05);color:var(--ink)}
  .ed-drop .ed-drop-ic{display:flex;justify-content:center;margin-bottom:6px;color:var(--red)}
  .ed-drop b{color:var(--ink)}
  .ed-drop small{display:block;margin-top:3px;font-size:10.5px}
  .ed-drop-opts{display:flex;align-items:center;gap:7px;justify-content:center;margin-top:9px;font-size:11.5px;color:var(--ink)}
  .ed-drop-opts input{accent-color:var(--red)}
  /* Lista de anexos */
  .anx-list{display:flex;flex-direction:column;gap:8px}
  .anx-empty{font-size:11.5px;color:var(--faint);padding:4px 2px}
  .anx-item{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:10px;padding:9px 11px;background:var(--panel)}
  .anx-ic{flex:0 0 30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff}
  .anx-ic.pdf_matricula{background:#a80f1e}.anx-ic.pdf_sigef{background:#1f7a4d}.anx-ic.kml{background:#2563eb}.anx-ic.outro{background:#6b7280}
  .anx-meta{flex:1;min-width:0}
  .anx-nome{font-size:12px;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .anx-sub{font-size:10px;color:var(--faint);margin-top:1px}
  .anx-acts{display:flex;gap:4px;flex:0 0 auto}
  .anx-btn{width:30px;height:30px;border:1px solid var(--line);background:var(--bg);color:var(--faint);border-radius:8px;cursor:pointer;
    display:flex;align-items:center;justify-content:center;transition:.15s}
  .anx-btn:hover{color:var(--ink);border-color:var(--red)}
  .anx-btn.danger:hover{color:#fff;background:var(--red);border-color:var(--red)}
  .anx-btn[disabled]{opacity:.45;cursor:default}
  /* feedback de processamento DENTRO do modal (antes ficava só na barra atrás do modal) */
  .ed-drop.busy{opacity:.6;border-style:solid;cursor:not-allowed}
  .anx-busy{display:flex;align-items:center;gap:9px;padding:10px 12px;border-radius:10px;font-size:12px;
    border:1px solid var(--line);background:var(--panel);color:var(--ink)}
  .anx-busy.work{border-color:rgba(168,15,30,.4);background:rgba(168,15,30,.06)}
  .anx-busy.warn{border-color:#caa700;background:rgba(202,167,0,.10)}
  .anx-busy.ok{border-color:rgba(19,105,63,.45);background:rgba(19,105,63,.08)}
  .anx-spin{flex:0 0 16px;width:16px;height:16px;border-radius:50%;border:2.5px solid rgba(168,15,30,.25);
    border-top-color:var(--red);animation:anxspin .7s linear infinite}
  @keyframes anxspin{to{transform:rotate(360deg)}}
  .anx-busy.shake{animation:anxshake .4s ease}
  @keyframes anxshake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}
  @media (max-width:820px){ .ed-grid{grid-template-columns:1fr} }
  /* ===== Overlay de progresso da importação ===== */
  .import-ov{position:fixed;inset:0;z-index:100040;display:none;align-items:center;justify-content:center;
    background:rgba(8,12,18,.62);backdrop-filter:blur(3px)}
  .import-ov.show{display:flex}
  .import-card{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:26px 30px;text-align:center;
    box-shadow:0 24px 70px rgba(0,0,0,.5);min-width:260px}
  .import-ttl{font-size:13px;font-weight:600;color:var(--ink);margin-bottom:16px;letter-spacing:.2px}
  .import-ring{position:relative;width:120px;height:120px;margin:0 auto}
  .import-ring svg{transform:rotate(-90deg)}
  .import-ring .ring-bg{fill:none;stroke:var(--line);stroke-width:9}
  .import-ring .ring-fg{fill:none;stroke:var(--red);stroke-width:9;stroke-linecap:round;
    stroke-dasharray:326.7;stroke-dashoffset:326.7;transition:stroke-dashoffset .3s ease}
  .import-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;color:var(--ink)}
  .import-meta{margin-top:14px;font-size:12px;color:var(--faint)}
  .import-file{margin-top:4px;font-size:11.5px;color:var(--ink);max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:var(--mono)}
  /* ===== Modal de resultados da importação ===== */
  .impres-resumo{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px}
  .impres-chip{font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:20px;border:1px solid var(--line)}
  .impres-chip.ok{color:#13693f;background:rgba(19,105,63,.10);border-color:rgba(19,105,63,.3)}
  .impres-chip.dup{color:#1f5fa5;background:rgba(31,95,165,.10);border-color:rgba(31,95,165,.3)}
  .impres-chip.err{color:#a80f1e;background:rgba(168,15,30,.10);border-color:rgba(168,15,30,.3)}
  .impres-chip.warn{color:#8a6d00;background:rgba(202,167,0,.12);border-color:rgba(202,167,0,.35)}
  .impres-list{display:flex;flex-direction:column;gap:8px}
  .impres-item{border:1px solid var(--line);border-radius:11px;padding:10px 12px;background:var(--panel)}
  .impres-row1{display:flex;align-items:center;gap:9px}
  .impres-ic{flex:0 0 22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff}
  .impres-ic.criado{background:#13693f}.impres-ic.duplicado{background:#1f5fa5}.impres-ic.erro{background:#a80f1e}
  .impres-nome{font-size:12.5px;font-weight:600;color:var(--ink)}
  .impres-st{margin-left:auto;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--faint)}
  .impres-msg{font-size:11px;color:var(--faint);margin-top:3px;margin-left:31px}
  .impres-inc{margin:7px 0 0 31px;display:flex;flex-direction:column;gap:4px}
  .impres-inc .inc-line{display:flex;gap:7px;align-items:flex-start;font-size:11.5px;line-height:1.4}
  .inc-tag{flex:0 0 auto;font-size:9px;font-weight:800;letter-spacing:.4px;padding:1px 6px;border-radius:5px;margin-top:1px}
  .inc-tag.erro{background:rgba(168,15,30,.14);color:#a80f1e}
  .inc-tag.alerta{background:rgba(202,167,0,.16);color:#8a6d00}
  .inc-tag.info{background:rgba(31,95,165,.14);color:#1f5fa5}
  .impres-inc .inc-msg{color:var(--ink)}
  .impres-relrow{margin:7px 0 0 31px}
  .impres-relrow .mini-rel{font-size:11px;color:var(--red);background:none;border:none;cursor:pointer;padding:0;text-decoration:underline}
  /* badge de inconsistência na lista */
  .inc-badge{display:inline-flex;align-items:center;gap:2px;font-size:9.5px;font-weight:800;letter-spacing:.3px;
    padding:1px 6px;border-radius:10px;background:rgba(202,167,0,.16);color:#8a6d00;border:1px solid rgba(202,167,0,.4);cursor:pointer;vertical-align:middle}
  .inc-badge:hover{background:rgba(202,167,0,.28)}
  /* bloco de inconsistências no InfoWindow do mapa */
  .ip-inc{margin-top:9px;padding-top:9px;border-top:1px dashed rgba(0,0,0,.18)}
  .ip-inc-h{font-size:11.5px;font-weight:700;color:#8a6d00;margin-bottom:6px}
  .ip-inc-row{display:flex;gap:6px;align-items:flex-start;font-size:11px;line-height:1.4;margin-bottom:4px}
  .ip-inc-row .inc-msg{color:#1a2330}
  .ip-inc-btn{margin-top:5px;font-size:11px;font-weight:600;color:#fff;background:var(--red);border:none;border-radius:7px;padding:5px 10px;cursor:pointer}
  .ip-inc-btn:hover{background:var(--red-bright)}
  /* ===== FAB + backdrop (mobile) ===== */
  .fab-panel{display:none;position:fixed;right:16px;bottom:92px;z-index:1100;width:52px;height:52px;border-radius:50%;
    background:var(--red);color:#fff;border:none;box-shadow:0 6px 20px rgba(168,15,30,.45);cursor:pointer;align-items:center;justify-content:center}
  .panel-backdrop{display:none;position:fixed;inset:0;z-index:899;background:rgba(8,12,18,.5)}
  body.panel-open .panel-backdrop{display:block}
  /* ===== Responsivo ===== */
  @media (max-width:880px){
    .mapeador-shell{grid-template-columns:1fr}
    .panel{position:fixed;top:var(--header-height,60px);bottom:0;left:0;width:88%;max-width:380px;z-index:900;
      transform:translateX(-102%);transition:transform .28s ease;border-right:1px solid var(--line);box-shadow:6px 0 30px rgba(0,0,0,.3)}
    body.panel-open .panel{transform:none}
    body.panel-collapsed .mapeador-shell{grid-template-columns:1fr}
    .fab-panel{display:flex}
    .toggle-panel{display:none}
  }
  @media (max-width:420px){
    .fab-panel{bottom:84px;right:14px}
    .modal-row{grid-template-columns:1fr}
  }
</style>
</head>
<body>
<?php include(__DIR__ . '/../menu.php'); ?>
<script>
  // O menu.php injeta um <body class="$mode"> que o navegador ignora (body aninhado);
  // aplicamos o modo salvo ao body real para que o tema (dark/light) chegue ao mapeador.
  (function(){
    var m = '<?php echo (isset($mode) && $mode === "dark-mode") ? "dark-mode" : "light-mode"; ?>';
    document.body.classList.remove('dark-mode','light-mode');
    document.body.classList.add(m);
  })();
</script>
<div class="mapeador-shell">
  <div class="panel">
    <div class="head">
      <div class="brand">
        <div class="mark">
          <svg viewBox="0 0 24 24" fill="#fff" stroke="none">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/>
          </svg>
        </div>
        <div>
          <h1>Atlas Dimensor</h1>
          <p>GMS · Google Maps · Atlas</p>
        </div>
      </div>
      <a href="../index.php" class="back-atlas" title="Voltar ao Atlas">← Atlas</a>
    </div>

    <div class="body">

      <details class="onr-accordion manual-accordion">
        <summary class="onr-summary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"></path></svg>
          Cadastro manual
          <span class="onr-hint-active">— memorial / dados do imóvel</span>
        </summary>
        <div class="onr-body">
      <p class="label">Memorial descritivo</p>
      <textarea id="memorial" spellcheck="false"
        placeholder="Cole o memorial com coordenadas em graus, minutos e segundos (ex.: longitude -46°53'11,184&quot; e latitude -4°8'33,962&quot;)…"></textarea>

      <div class="enc-info" id="enc-info" style="display:none">
        <div class="enc-info-h"><span class="enc-ico">✝</span> <b id="enc-info-titulo">Matrícula encerrada</b></div>
        <div class="enc-info-b" id="enc-info-corpo"></div>
      </div>

      <div class="form-grid">
        <div class="fld grid-2">
          <label class="field-label">Identificação do imóvel</label>
          <input id="identificador" type="text" placeholder="Ex.: Lote 12 — Fazenda Boa Vista">
        </div>
        <div class="fld">
          <label class="field-label">Nº da matrícula</label>
          <input id="numero_matricula" type="text" placeholder="Ex.: 12.345">
        </div>
        <div class="fld">
          <label class="field-label">Tipo do imóvel</label>
          <select id="tipo_imovel">
            <option value="">— selecione —</option>
            <option value="urbano">Urbano</option>
            <option value="rural">Rural</option>
          </select>
        </div>
        <div class="fld grid-2">
          <label class="field-label">Proprietário</label>
          <input id="proprietario" type="text" placeholder="Nome do proprietário">
        </div>
        <div class="fld grid-2">
          <label class="field-label">CPF do proprietário</label>
          <input id="cpf" type="text" placeholder="000.000.000-00" maxlength="14">
        </div>
      </div>
      <input type="hidden" id="tipo_identificador" value="nome">

      <div class="actions">
        <button class="btn-primary" id="btn-map">Mapear</button>
        <button class="btn-save" id="btn-save" disabled>Gravar no banco</button>
      </div>
        </div>
      </details>

      <div class="onr-box">
        <details class="onr-accordion">
          <summary class="onr-summary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            Dados para o Mapa ONR
            <span class="onr-hint-active" id="onr-hint-active">— grave/abra um imóvel</span>
          </summary>
          <div class="onr-body">
            <?php foreach (onrGrupos() as $grupo => $campos): ?>
              <details class="onr-sub" open>
                <summary><?= htmlspecialchars($grupo) ?></summary>
                <div class="form-grid">
                  <?php foreach ($campos as $col => $meta): list($rot, $tp, $ph) = $meta; ?>
                    <div class="fld grid-2">
                      <label class="field-label"><?= htmlspecialchars($rot) ?></label>
                      <input id="onr_<?= $col ?>" data-onr="<?= $col ?>" type="text" placeholder="<?= htmlspecialchars($ph) ?>">
                    </div>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php endforeach; ?>

            <details class="onr-sub" open>
              <summary>Geometria e classificação</summary>
              <div class="form-grid">
                <div class="fld"><label class="field-label">Área (ha)</label><input id="onr_area_ha" type="text" readonly></div>
                <div class="fld"><label class="field-label">Área (m²)</label><input id="onr_area_m2" type="text" readonly></div>
                <div class="fld"><label class="field-label">Perímetro (m)</label><input id="onr_perim_m" type="text" readonly></div>
                <div class="fld"><label class="field-label">Perímetro (km)</label><input id="onr_perim_km" type="text" readonly></div>
                <div class="fld grid-2">
                  <label class="field-label">Classificação (CLASSIFICA)</label>
                  <select id="onr_classifica" data-onr="classifica">
                    <option value="">—</option>
                    <option value="1">A — georref. rural certificado / urbano com ART</option>
                    <option value="2">B — georreferenciado sem certificação</option>
                    <option value="3">C — desenho sobre imagem de satélite</option>
                  </select>
                </div>
              </div>
            </details>

            <details class="onr-sub">
              <summary>Parâmetros de envio à ONR</summary>
              <div class="form-grid">
                <div class="fld"><label class="field-label">Categoria</label><input id="onr_categoria" type="text" readonly placeholder="vem do Tipo do imóvel"></div>
                <div class="fld">
                  <label class="field-label">Nível de publicidade</label>
                  <select id="onr_nivel_publicidade" data-onr="onr_nivel_publicidade">
                    <option value="">—</option>
                    <option value="1">1 — Somente quem enviou</option>
                    <option value="2">2 — Somente a serventia</option>
                    <option value="3" selected>3 — Todos oficiais (internet)</option>
                    <option value="4">4 — Público geral (internet)</option>
                  </select>
                </div>
                <div class="fld grid-2">
                  <label class="field-label">Classificação da importação</label>
                  <select id="onr_classificacao" data-onr="onr_classificacao">
                    <option value="">—</option>
                    <option value="1" selected>1 — Geral</option><option value="2">2 — Loteamento</option>
                    <option value="3">3 — Usucapião</option><option value="4">4 — Retificação</option>
                    <option value="5">5 — REURB</option><option value="6">6 — Definido pelo RI1</option>
                    <option value="7">7 — Definido pelo RI2</option><option value="8">8 — Estrangeiro</option>
                    <option value="9">9 — Fusão</option><option value="10">10 — Desmembramento</option>
                  </select>
                </div>
                <div class="fld grid-2"><label class="field-label">Número da prenotação</label><input id="onr_numero_prenotacao" data-onr="onr_numero_prenotacao" type="text" placeholder="Ex.: 2024-54321"></div>
                <div class="fld grid-2"><label class="field-label">Descrição da importação</label><input id="onr_descricao" data-onr="onr_descricao" type="text" placeholder="Ex.: Importação do polígono ..."></div>
              </div>
            </details>

            <button type="button" class="btn-save" id="btn-onr-salvar" disabled style="width:100%;margin-top:12px">Salvar dados ONR</button>
            <p class="cor-hint">Esses dados alimentam o shapefile e o envio à API do Mapa do Registro de Imóveis (ONR).</p>
          </div>
        </details>
      </div>

      <div class="muni-box">
        <p class="label muni-label">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line></svg>
          Limite do município (IBGE)
        </p>
        <div class="muni-row">
          <div style="flex:0 0 80px">
            <p class="field-label">UF</p>
            <select id="muni-uf">
              <option value="AC">AC</option><option value="AL">AL</option><option value="AP">AP</option>
              <option value="AM">AM</option><option value="BA">BA</option><option value="CE">CE</option>
              <option value="DF">DF</option><option value="ES">ES</option><option value="GO">GO</option>
              <option value="MA" selected>MA</option><option value="MT">MT</option><option value="MS">MS</option>
              <option value="MG">MG</option><option value="PA">PA</option><option value="PB">PB</option>
              <option value="PR">PR</option><option value="PE">PE</option><option value="PI">PI</option>
              <option value="RJ">RJ</option><option value="RN">RN</option><option value="RS">RS</option>
              <option value="RO">RO</option><option value="RR">RR</option><option value="SC">SC</option>
              <option value="SP">SP</option><option value="SE">SE</option><option value="TO">TO</option>
            </select>
          </div>
          <div style="flex:1;min-width:0">
            <p class="field-label">Município</p>
            <select id="muni-list"><option value="">Carregando…</option></select>
          </div>
        </div>
        <div class="actions">
          <button class="btn-ghost" id="btn-muni-mostrar" style="flex:1">Mostrar limite no mapa</button>
          <button class="btn-ghost" id="btn-muni-ocultar" style="display:none">Ocultar</button>
        </div>
        <div class="status" id="muni-status"></div>
      </div>

      <div class="kml-zone lote" id="kml-lote-zone">
        <input type="file" id="kml-lote-file" accept=".kml,application/vnd.google-earth.kml+xml" multiple hidden>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1"></rect><line x1="9" y1="12" x2="15" y2="12"></line><line x1="9" y1="16" x2="13" y2="16"></line></svg>
        <span id="kml-lote-label">Importar <b>KML</b> <span class="zone-multi">(1 ou vários)</span></span>
      </div>

      <div class="kml-zone ia" id="pdf-mat-zone">
        <input type="file" id="pdf-mat-file" accept="application/pdf,.pdf" multiple hidden>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M9 15h6M9 18h4"></path></svg>
        <span id="pdf-mat-label">Matrícula ou <b>SIGEF</b> em PDF — mapear via IA <span class="zone-multi">(1 ou vários)</span></span>
      </div>
      <button type="button" class="link-config" id="btn-gemini-config">⚙ Configurar IA (Gemini)</button>

      <div class="status" id="status"></div>

      <div class="stats" id="stats" style="display:none">
        <div class="stat"><div class="v" id="s-vtx">—</div><div class="k">Vértices</div></div>
        <div class="stat"><div class="v" id="s-area">—<span class="u"> ha</span></div><div class="k">Área (UTM 23S)</div></div>
        <div class="stat"><div class="v" id="s-per">—<span class="u"> km</span></div><div class="k">Perímetro</div></div>
        <div class="stat"><div class="v" id="s-cen" style="font-size:12px">—</div><div class="k">Centro lat,lng</div></div>
      </div>

      <div class="cor-box" id="cor-box" style="display:none">
        <p class="label muni-label">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5"></circle><circle cx="17.5" cy="10.5" r=".5"></circle><circle cx="8.5" cy="7.5" r=".5"></circle><circle cx="6.5" cy="12.5" r=".5"></circle><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path></svg>
          Cor de destaque do imóvel
        </p>
        <div class="cor-grid" id="cor-grid"></div>
        <div class="op-wrap">
          <span class="op-lbl">Intensidade</span>
          <input type="range" id="cor-op" class="op-range" min="0.08" max="0.55" step="0.01" value="0.18">
        </div>
        <button type="button" class="btn-ghost" id="cor-clear" style="margin-top:8px;width:100%">Remover destaque</button>
        <p class="cor-hint">Dica: clique sobre um imóvel no mapa (em "Ver todos") para destacá-lo também. O vermelho é reservado a sobreposições.</p>
      </div>

      <div class="saved">
        <div class="saved-head">
          <h3>Imóveis gravados</h3>
          <div class="saved-actions">
            <button class="mini-btn" id="btn-todos">Ver todos no mapa</button>
            <button class="mini-btn onr" id="btn-onr-lote" title="Enviar todos os imóveis prontos ao Mapa ONR">➤ Enviar prontos</button>
            <button class="mini-btn" id="btn-onr-config" title="Configurar a API do Mapa ONR">⚙</button>
          </div>
        </div>
        <div class="vista-toggle" id="vista-toggle">
          <button type="button" class="vt-btn active" data-vista="mapa">Mapeadas</button>
          <button type="button" class="vt-btn" data-vista="itn03">Exclusivas ITN 03 <span id="vt-count-itn03" class="vt-count"></span></button>
        </div>
        <div class="itn03-actions" id="itn03-actions" style="display:none">
          <button class="mini-btn" id="btn-itn03-nova" title="Cadastrar uma matrícula só para a carga ITN 03 (sem coordenadas/mapa)">➕ Nova matrícula</button>
          <button class="mini-btn onr" id="btn-itn03-export-excl" title="Exportar a carga ITN 03 das matrículas exclusivas aptas">⤓ Exportar carga</button>
        </div>
        <div class="search-wrap">
          <svg class="search-ic" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <input id="busca" type="text" placeholder="Buscar por matrícula, proprietário, identificação…">
          <button id="busca-clear" class="search-clear" title="Limpar" style="display:none">×</button>
        </div>
        <div id="saved-list"><div class="empty-list">Carregando…</div></div>
      </div>
    </div>
  </div>

  <div class="map-wrap">
    <div id="map"></div>
    <button id="btn-toggle-panel" class="toggle-panel" title="Mostrar/ocultar painel" aria-label="Mostrar ou ocultar painel">
      <svg class="ic-collapse" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
      <svg class="ic-expand" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </button>
    <div class="overlay" id="overlay">Clique em <span style="color:var(--red-bright);margin:0 4px">Mapear</span> para visualizar o imóvel</div>
    <div class="readout" id="readout"><span class="dot">◆</span> <b id="ro-name">Imóvel</b> &nbsp;·&nbsp; <span id="ro-area"></span> ha</div>
    <div class="muni-badge" id="muni-badge"></div>

    <div class="overview-panel" id="overview-panel">
      <div class="ovh">
        <div>
          <div class="ovh-title">Visão geral</div>
          <div class="ovh-sub" id="ov-sub">—</div>
        </div>
        <button class="ov-close" id="ov-hide" title="Ocultar painel">–</button>
      </div>
      <div class="legend">
        <span><i class="sw normal"></i>Imóvel</span>
        <span><i class="sw sel"></i>Selecionado</span>
        <span><i class="sw over"></i>Sobreposição</span>
      </div>
      <div class="ov-hint" id="ov-hint">Ctrl+clique (ou clique direito) nos imóveis para selecionar · clique numa sobreposição para o relatório dela</div>
      <div class="ov-search">
        <input type="text" id="ov-busca" placeholder="Filtrar... (use ; para ver só esses: 744;822;867)">
        <button id="ov-busca-clear" title="Limpar filtro">×</button>
      </div>
      <div class="ov-itn03">
        <button id="ov-itn03" class="btn-itn03" title="Gerar a carga ITN 03 (ONR) dos imóveis prontos para o Mapa ONR — todos, ou apenas os do filtro ;">⤓ Exportar carga ITN 03 (lote)</button>
      </div>
      <div class="ov-overlaps" id="ov-overlaps"></div>
      <div class="ov-foot">
        <button class="btn-report" id="btn-relatorio">Gerar relatório de sobreposição (PDF)</button>
      </div>
    </div>

    <button class="ov-reopen" id="ov-reopen" title="Mostrar painel de imóveis e sobreposições">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
      <span>Imóveis e sobreposições</span>
    </button>

    <div class="sel-bar" id="sel-bar">
      <span class="sel-count"><b id="sel-n">0</b> selecionado(s)</span>
      <button class="sel-rep" id="sel-relatorio">Relatório dos selecionados</button>
      <button class="sel-clear" id="sel-limpar">Limpar</button>
    </div>

    <div class="overview-panel kml-panel" id="kml-panel">
      <div class="ovh">
        <div>
          <div class="ovh-title">Importar do KML</div>
          <div class="ovh-sub" id="kml-sub">—</div>
        </div>
        <button class="ov-close" id="kml-close" title="Cancelar">×</button>
      </div>
      <div class="kml-rows" id="kml-rows"></div>
      <div class="kml-foot">
        <button class="btn-save" id="btn-import-lote" style="width:100%">Gravar imóveis</button>
      </div>
    </div>
  </div>
</div><!-- /.mapeador-shell -->

<!-- Botão flutuante p/ abrir o painel no mobile -->
<button id="fab-panel" class="fab-panel" title="Imóveis e cadastro" aria-label="Abrir painel">
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
</button>
<div id="panel-backdrop" class="panel-backdrop"></div>

<!-- Modal: editar dados do imóvel -->
<div id="modal-edit" class="modal-ov">
  <div class="modal-card">
    <div class="modal-h">
      <h3 id="ed-titulo">Editar dados do imóvel</h3>
      <button class="modal-x" id="ed-cancelar" title="Fechar">×</button>
    </div>
    <input type="hidden" id="ed-id">
    <div class="modal-b">
      <div class="ed-grid">
       <div class="ed-col">
        <div class="ed-section">
          <div class="ed-sec-head">
            <span class="ed-sec-ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
            <h4>Identificação do imóvel</h4>
          </div>
          <div class="ed-sec-body">
      <div class="fld"><label class="field-label">Identificação do imóvel</label><input id="ed-identificador" type="text"></div>
      <div class="modal-row">
        <div class="fld"><label class="field-label">Nº da matrícula</label><input id="ed-matricula" type="text"></div>
        <div class="fld"><label class="field-label">Tipo</label>
          <select id="ed-tipo"><option value="">—</option><option value="urbano">Urbano</option><option value="rural">Rural</option></select>
        </div>
      </div>
      <div class="modal-row" id="ed-ctxr-wrap" style="display:none">
        <div class="fld" style="flex:1">
          <label class="field-label">Contexto rural (carga ITN 03)</label>
          <select id="ed-contexto-rural">
            <option value="">Padrão (autodetectar)</option>
            <option value="1">Padrão</option>
            <option value="2">Imóvel da União</option>
            <option value="3">Cidadão estrangeiro</option>
          </select>
          <div class="field-hint" style="margin-top:4px">Indica à ITN 03 se o imóvel rural é da União ou de estrangeiro. Deixe em "autodetectar" para o sistema decidir pelos titulares.</div>
        </div>
      </div>
      <div class="fld grid-2">
        <label class="field-label">Proprietários (pessoa física ou jurídica)</label>
        <div id="ed-prop-list" class="prop-list"></div>
        <button type="button" id="ed-prop-add" class="btn-ghost-sm" style="margin-top:7px">+ Adicionar proprietário</button>
      </div>

      <div class="situacao-edit">
        <div class="fld">
          <label class="field-label">Situação da matrícula</label>
          <select id="ed-situacao">
            <option value="ativa">Ativa</option>
            <option value="unificacao">Encerrada por unificação</option>
            <option value="georreferenciamento">Encerrada por georreferenciamento</option>
            <option value="desmembramento">Desmembramento — saiu um trecho</option>
          </select>
        </div>
        <div id="ed-enc-extra" style="display:none">
          <div class="fld">
            <label class="field-label" id="ed-suc-label">Matrículas sucessoras (originadas)</label>
            <div id="ed-sucessora-chips" class="chips"></div>
            <div class="chips-add">
              <input id="ed-sucessora-input" type="text" placeholder="Ex.: 745-900 (intervalo) ou 745;785;796 (específicas)">
              <button type="button" id="ed-sucessora-add" class="btn-ghost-sm">+ Adicionar</button>
            </div>
            <p class="cor-hint" id="ed-suc-syntax">Use <b>745-900</b> para um intervalo (745 até 900) e <b>;</b> para números específicos (745;785;796). Pode combinar: <b>745-760;800;ou 12.345-6</b>.</p>
            <p class="cor-hint" id="ed-suc-feedback" style="display:none"></p>
          </div>
          <p class="cor-hint" id="ed-sit-hint"></p>
        </div>
      </div>
          </div>
        </div>
       </div>

       <div class="ed-col">
        <div class="ed-section">
          <div class="ed-sec-head">
            <span class="ed-sec-ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg></span>
            <h4>Anexos do imóvel</h4>
            <span class="ed-sec-sub" id="ed-anx-count"></span>
          </div>
          <div class="ed-sec-body">
            <div id="ed-anexos-list" class="anx-list"><div class="anx-empty">—</div></div>
            <div id="ed-anx-busy" class="anx-busy" style="display:none" aria-live="polite"></div>
            <div id="ed-drop" class="ed-drop" tabindex="0">
              <div class="ed-drop-ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
              <b>Arraste um arquivo aqui</b> ou clique para selecionar
              <small>PDF da matrícula · PDF do SIGEF · KML</small>
              <label class="ed-drop-opts" onclick="event.stopPropagation()"><input type="checkbox" id="ed-anx-ia" checked> Analisar com IA p/ preencher campos faltantes</label>
            </div>
            <input type="file" id="ed-anx-file" accept=".pdf,.kml,application/pdf,application/vnd.google-earth.kml+xml" style="display:none">
            <p class="cor-hint">Os PDFs enviados para cadastro/complemento ficam arquivados aqui para conferência e reprocessamento.</p>
          </div>
        </div>
       </div>
      </div>

      <!-- Dados para o Mapa ONR (recolhível, igual ao painel lateral) -->
      <div class="onr-box">
        <details class="onr-accordion ed-onr">
          <summary class="onr-summary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            Dados para o Mapa ONR
            <span class="onr-hint-active">— opcional</span>
          </summary>
          <div class="onr-body">
            <?php foreach (onrGrupos() as $grupo => $campos): ?>
              <details class="onr-sub" open>
                <summary><?= htmlspecialchars($grupo) ?></summary>
                <div class="form-grid">
                  <?php foreach ($campos as $col => $meta): list($rot, $tp, $ph) = $meta; ?>
                    <div class="fld grid-2">
                      <label class="field-label"><?= htmlspecialchars($rot) ?></label>
                      <input id="eonr_<?= $col ?>" data-eonr="<?= $col ?>" type="text" placeholder="<?= htmlspecialchars($ph) ?>">
                    </div>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php endforeach; ?>

            <details class="onr-sub" id="ed-qual-sub" open>
              <summary>Titulares atuais — qualificação (carga ITN 03)</summary>
              <div id="ed-qual-list" class="qual-list"><span class="chips-vazio">Sem qualificação estruturada. Processe o PDF da matrícula para extrair os titulares atuais (registros/averbações).</span></div>
            </details>

            <details class="onr-sub" open>
              <summary>Classificação e parâmetros de envio</summary>
              <div class="form-grid">
                <div class="fld grid-2">
                  <label class="field-label">Classificação (CLASSIFICA)</label>
                  <select id="eonr_classifica" data-eonr="classifica">
                    <option value="">—</option>
                    <option value="1">A — georref. rural certificado / urbano com ART</option>
                    <option value="2">B — georreferenciado sem certificação</option>
                    <option value="3">C — desenho sobre imagem de satélite</option>
                  </select>
                </div>
                <div class="fld">
                  <label class="field-label">Nível de publicidade</label>
                  <select id="eonr_onr_nivel_publicidade" data-eonr="onr_nivel_publicidade">
                    <option value="">—</option>
                    <option value="1">1 — Somente quem enviou</option>
                    <option value="2">2 — Somente a serventia</option>
                    <option value="3">3 — Todos oficiais (internet)</option>
                    <option value="4">4 — Público geral (internet)</option>
                  </select>
                </div>
                <div class="fld grid-2">
                  <label class="field-label">Classificação da importação</label>
                  <select id="eonr_onr_classificacao" data-eonr="onr_classificacao">
                    <option value="">—</option>
                    <option value="1">1 — Geral</option><option value="2">2 — Loteamento</option>
                    <option value="3">3 — Usucapião</option><option value="4">4 — Retificação</option>
                    <option value="5">5 — REURB</option><option value="6">6 — Definido pelo RI1</option>
                    <option value="7">7 — Definido pelo RI2</option><option value="8">8 — Estrangeiro</option>
                    <option value="9">9 — Fusão</option><option value="10">10 — Desmembramento</option>
                  </select>
                </div>
                <div class="fld grid-2"><label class="field-label">Número da prenotação</label><input id="eonr_onr_numero_prenotacao" data-eonr="onr_numero_prenotacao" type="text" placeholder="Ex.: 2024-54321"></div>
                <div class="fld grid-2"><label class="field-label">Descrição da importação</label><input id="eonr_onr_descricao" data-eonr="onr_descricao" type="text" placeholder="Ex.: Importação do polígono ..."></div>
              </div>
            </details>
            <p class="cor-hint">Esses dados alimentam o shapefile e o envio ao Mapa do Registro de Imóveis (ONR) e são gravados junto ao clicar em “Salvar alterações”.</p>
          </div>
        </details>
      </div>
    </div>
    <div class="modal-f">
      <button class="btn-ghost" id="ed-cancelar2" onclick="fecharEdicao()">Cancelar</button>
      <button class="btn-ghost" id="ed-itn03" title="Gerar a carga ITN 03 desta matrícula (precisa estar pronta para o Mapa ONR)">⤓ Carga ITN 03</button>
      <button class="btn-primary" id="ed-salvar">Salvar alterações</button>
    </div>
  </div>
</div>

<!-- Modal: configuração da API ONR -->
<div id="modal-onr-config" class="modal-ov">
  <div class="modal-card">
    <div class="modal-h">
      <h3>Configurar API do Mapa ONR</h3>
      <button class="modal-x" onclick="fecharConfigOnr()" title="Fechar">×</button>
    </div>
    <div class="modal-b">
      <div class="fld"><label class="field-label">BASE_URL</label><input id="cfg-base-url" type="text" placeholder="https://mapa.onr.org.br/"></div>
      <div class="fld"><label class="field-label">Bearer Token (chave da API)</label><input id="cfg-token" type="text" autocomplete="off" placeholder="Cole aqui o Bearer Token"></div>
      <p class="cor-hint">O token é gerado no portal do Mapa ONR (Configurações &gt; Chave API para envio de polígonos) com certificado e-CPF e tem validade de 15 dias. Fica salvo no servidor em <code>dimensor/config_onr.json</code>.</p>
    </div>
    <div class="modal-f">
      <button class="btn-ghost" onclick="fecharConfigOnr()">Cancelar</button>
      <button class="btn-primary" onclick="salvarConfigOnr()">Salvar configuração</button>
    </div>
  </div>
</div>

<!-- Overlay de progresso da importação (centralizado) -->
<div id="import-ov" class="import-ov">
  <div class="import-card">
    <div class="import-ttl" id="import-ttl">Importando…</div>
    <div class="import-ring">
      <svg viewBox="0 0 120 120" width="120" height="120">
        <circle cx="60" cy="60" r="52" class="ring-bg"></circle>
        <circle cx="60" cy="60" r="52" class="ring-fg" id="import-ring-fg"></circle>
      </svg>
      <div class="import-pct" id="import-pct">0%</div>
    </div>
    <div class="import-meta" id="import-meta">0 de 0</div>
    <div class="import-file" id="import-file"></div>
  </div>
</div>

<!-- Modal: resultados da importação -->
<div id="modal-import-res" class="modal-ov">
  <div class="modal-card" style="max-width:680px">
    <div class="modal-h">
      <h3 id="impres-titulo">Resultado da importação</h3>
      <button class="modal-x" id="impres-x" title="Fechar">×</button>
    </div>
    <div class="modal-b">
      <div class="impres-resumo" id="impres-resumo"></div>
      <div class="impres-list" id="impres-list"></div>
    </div>
    <div class="modal-f">
      <button class="btn-ghost" id="impres-rel" title="Gerar PDF com as inconsistências encontradas nesta importação">⤓ Relatório de inconsistências</button>
      <button class="btn-primary" id="impres-fechar">Fechar</button>
    </div>
  </div>
</div>

<!-- Modal: configuração da IA (Gemini) -->
<div id="modal-gemini-config" class="modal-ov">
  <div class="modal-card">
    <div class="modal-h">
      <h3>Configurar IA (Gemini)</h3>
      <button class="modal-x" onclick="fecharConfigGemini()" title="Fechar">×</button>
    </div>
    <div class="modal-b">
      <div class="fld"><label class="field-label">Chave da API do Gemini</label><input id="gem-key" type="text" autocomplete="off" placeholder="Cole a API key do Google AI Studio"></div>
      <div class="fld">
        <label class="field-label">Modelos cadastrados</label>
        <div id="gem-models" class="gem-models"></div>
        <div class="chips-add" style="margin-top:7px">
          <input id="gem-model-input" type="text" placeholder="Adicionar modelo (ex.: gemini-3.5-flash)">
          <button type="button" id="gem-model-add" class="btn-ghost-sm">+ Adicionar</button>
        </div>
      </div>
      <div class="fld">
        <label class="field-label">Modelo padrão para OCR de matrículas</label>
        <select id="gem-default"></select>
      </div>
      <p class="cor-hint">A chave fica salva no servidor em <code>dimensor/config_gemini.json</code>. Use um modelo com leitura de PDF (visão). O modelo padrão é o usado para extrair os dados das matrículas.</p>
    </div>
    <div class="modal-f">
      <button class="btn-ghost" onclick="fecharConfigGemini()">Cancelar</button>
      <button class="btn-primary" onclick="salvarConfigGemini()">Salvar configuração</button>
    </div>
  </div>
</div>
<?php include(__DIR__ . '/../rodape.php'); ?>

<script>
let map;
let polygon = null, vertexMarkers = [];      // modo single
let overviewPolys = [], overlapPolys = [];   // modo visão geral
let labelOverlays = [];                       // rótulos nome/matrícula no mapa
let kmlPlacemarks = [];                       // placemarks do KML em importação
let overlapsAtuais = [], totalImoveisAtual = 0; // dados para o relatório de sobreposição
let selecionados = new Set();                    // ids de imóveis selecionados (Ctrl+clique)
let itensOverview = [];                           // itens exibidos na visão geral (com refs de polígono)
let ctrlAtivo = false;                            // estado da tecla Ctrl/Cmd
document.addEventListener('keydown', e=>{ if(e.key==='Control'||e.key==='Meta'||e.ctrlKey||e.metaKey) ctrlAtivo=true; });
document.addEventListener('keyup',   e=>{ if(e.key==='Control'||e.key==='Meta') ctrlAtivo=false; });
window.addEventListener('blur', ()=>{ ctrlAtivo=false; });
let lastGeo = null, origemAtual = 'memorial', kmlRaw = '', kmlNomeArquivo = '';
let modo = 'single';
let LabelOverlay = null;

function initMap(){
  map = new google.maps.Map(document.getElementById('map'), {
    center: {lat:-4.14, lng:-46.9}, zoom: 13,
    mapTypeId: 'hybrid',                       // mapa real (satélite + rótulos)
    mapTypeControl: true, streetViewControl: false, fullscreenControl: true,
    backgroundColor:'#0a0d11'
  });

  // Rótulo flutuante (chip) sobre o polígono — definido aqui pois depende da API já carregada
  LabelOverlay = class extends google.maps.OverlayView {
    constructor(position, text, cls, onClick){ super(); this.position=position; this.text=text; this.cls=cls||''; this.onClick=onClick||null; this.div=null; this.setMap(map); }
    onAdd(){
      const d=document.createElement('div');
      d.className='map-chip'+(this.cls?(' '+this.cls):'')+(this.onClick?' clic':''); d.textContent=this.text;
      if(this.onClick){
        const fn=this.onClick;
        d.addEventListener('click', ev=>{ ev.stopPropagation(); fn(ev); });
      }
      this.div=d; this.getPanes().floatPane.appendChild(d);
    }
    draw(){
      if(!this.div) return;
      const p=this.getProjection().fromLatLngToDivPixel(this.position);
      this.div.style.left=p.x+'px'; this.div.style.top=p.y+'px';
    }
    setText(t){ this.text=t; if(this.div) this.div.textContent=t; }
    onRemove(){ if(this.div){ this.div.remove(); this.div=null; } }
  };

  carregarLista();
  verTodos();   // abre a visão geral com todos os imóveis ao entrar
  iniciarPollLista();   // sincronização multiusuário (sem refresh da página)
}
window.initMap = initMap;
console.info('%cAtlas Dimensor — build 2026-06-20-sync-multiusuario','color:#0ea5e9;font-weight:bold');

function centroidOf(pts){
  let la=0,ln=0; pts.forEach(p=>{ la+=p[0]; ln+=p[1]; });
  return {lat:la/pts.length, lng:ln/pts.length};
}
function addLabel(pos, text, onClick){
  if(!text) return null;
  const ov = new LabelOverlay(new google.maps.LatLng(pos.lat, pos.lng), text, '', onClick||null);
  labelOverlays.push(ov);
  return ov;
}
function limparLabels(){ labelOverlays.forEach(l=>l.setMap(null)); labelOverlays=[]; ocultarHoverTip(); }

/* Tooltip de identificação exibido ao pousar o mouse ~2s sobre o imóvel */
let hoverTip=null, hoverTimer=null;
function ocultarHoverTip(){ if(hoverTimer){ clearTimeout(hoverTimer); hoverTimer=null; } if(hoverTip){ hoverTip.setMap(null); hoverTip=null; } }
function agendarHoverTip(pos, text){
  if(!text) return;
  if(hoverTimer) clearTimeout(hoverTimer);
  hoverTimer = setTimeout(()=>{
    if(hoverTip){ hoverTip.setMap(null); hoverTip=null; }
    hoverTip = new LabelOverlay(new google.maps.LatLng(pos.lat, pos.lng), text, 'hover');
  }, 2000);
}

function fmt(n,d){ return Number(n).toLocaleString('pt-BR',{minimumFractionDigits:d,maximumFractionDigits:d}); }
function swalTema(){
  const dark = document.body.classList.contains('dark-mode');
  return { background: dark ? '#161c24' : '#ffffff', color: dark ? '#e6edf3' : '#1f2733' };
}
function stripTags(s){ const d=document.createElement('div'); d.innerHTML=s; return d.textContent||d.innerText||''; }
function swalToast(icon, title){
  if(typeof Swal==='undefined') return;
  Swal.fire(Object.assign({
    toast:true, position:'top-end', timer: icon==='error'?4800:2800, timerProgressBar:true,
    showConfirmButton:false, icon:icon, title:title
  }, swalTema()));
}
async function swalConfirm(titulo, texto, confirmar){
  if(typeof Swal==='undefined') return window.confirm(texto||titulo);
  const r = await Swal.fire(Object.assign({
    title:titulo, text:texto||'', icon:'question', showCancelButton:true,
    confirmButtonText:confirmar||'Confirmar', cancelButtonText:'Cancelar',
    confirmButtonColor:'#a80f1e', cancelButtonColor:'#6b7785', reverseButtons:true
  }, swalTema()));
  return r.isConfirmed;
}
function setStatus(type,msg){
  const el=document.getElementById('status'); if(el){ el.className='status '+type; el.innerHTML=msg; }
  if(type==='err') swalToast('error', stripTags(msg));
  else if(type==='ok') swalToast('success', stripTags(msg));
}

function limparSingle(){
  if(polygon){ polygon.setMap(null); polygon=null; }
  vertexMarkers.forEach(m=>m.setMap(null)); vertexMarkers=[];
  limparLabels();
  imovelEditandoId=null;
  const cb=document.getElementById('cor-box'); if(cb) cb.style.display='none';
  if(typeof onrSetAtivo==='function'){ onrSetAtivo(null); document.querySelectorAll('[data-onr]').forEach(el=>{ const col=el.getAttribute('data-onr'); el.value = (typeof ONR_PADRAO!=='undefined' && ONR_PADRAO[col]!==undefined) ? ONR_PADRAO[col] : ''; }); onrPreencherGeometria({area_ha:null,perimetro_m:null}); }
  const ei=document.getElementById('enc-info'); if(ei) ei.style.display='none';
}
function limparOverview(){
  overviewPolys.forEach(p=>p.setMap(null)); overviewPolys=[];
  overlapPolys.forEach(p=>p.setMap(null)); overlapPolys=[];
  limparLabels();
  selecionados.clear();
  const sb=document.getElementById('sel-bar'); if(sb) sb.classList.remove('show');
  const rp=document.getElementById('ov-reopen'); if(rp) rp.classList.remove('show');
}

async function post(params){
  const r = await fetch(window.location.pathname, {method:'POST', body:new URLSearchParams(params)});
  return r.json();
}

/* ===================== MODO SINGLE ===================== */
function desenhar(geo, nome){
  modo='single';
  document.getElementById('btn-todos').classList.remove('active');
  document.getElementById('overview-panel').classList.remove('show');
  document.getElementById('kml-panel').classList.remove('show');
  limparOverview(); limparSingle();
  document.getElementById('overlay').style.display='none';

  const path = geo.pts.map(p=>({lat:p[0], lng:p[1]}));
  polygon = new google.maps.Polygon({
    paths:path, strokeColor:'#e2342f', strokeOpacity:.95, strokeWeight:2,
    fillColor:'#a80f1e', fillOpacity:.22, map:map
  });
  geo.pts.forEach((p,i)=>{
    vertexMarkers.push(new google.maps.Marker({
      position:{lat:p[0],lng:p[1]}, map:map,
      icon:{path:google.maps.SymbolPath.CIRCLE, scale:4, fillColor:'#0e1217',
            fillOpacity:1, strokeColor:'#e2342f', strokeWeight:2},
      title:'V'+(i+1)
    }));
  });
  const b = new google.maps.LatLngBounds();
  path.forEach(pt=>b.extend(pt)); map.fitBounds(b, 40);

  // rótulo com nome/matrícula no centro do imóvel
  addLabel({lat:geo.centro_lat, lng:geo.centro_lng}, nome);

  document.getElementById('stats').style.display='grid';
  document.getElementById('s-vtx').textContent = geo.num_vertices;
  document.getElementById('s-area').innerHTML = fmt(geo.area_ha,4)+'<span class="u"> ha</span>';
  document.getElementById('s-per').innerHTML  = fmt(geo.perimetro_m/1000,3)+'<span class="u"> km</span>';
  document.getElementById('s-cen').textContent = Number(geo.centro_lat).toFixed(5)+', '+Number(geo.centro_lng).toFixed(5);

  const ro=document.getElementById('readout'); ro.style.display='block';
  document.getElementById('ro-name').textContent = nome || 'Imóvel';
  document.getElementById('ro-area').textContent = fmt(geo.area_ha,2);

  verificarPertencimento(geo); // confere se o imóvel está dentro do limite municipal carregado
}

/* ===================== MAPEAR (memorial) ===================== */
document.getElementById('btn-map').onclick = async ()=>{
  const memorial = document.getElementById('memorial').value;
  if(!memorial.trim()){ setStatus('err','Cole um memorial descritivo.'); return; }
  origemAtual='memorial'; resetKmlZone();
  setStatus('warn','Processando…');
  const geo = await post({acao:'processar', memorial});
  if(!geo.ok){
    setStatus('err', `Não foi possível formar um polígono. Encontradas ${geo.lon_count||0} longitude(s) e ${geo.lat_count||0} latitude(s) em GMS — mínimo de 3 vértices válidos.`);
    document.getElementById('btn-save').disabled=true; return;
  }
  lastGeo=geo;
  if(geo.lon_count!==geo.lat_count)
    setStatus('warn', `${geo.lon_count} longitudes e ${geo.lat_count} latitudes — mapeados ${geo.num_vertices} vértices pareados. Confira o texto.`);
  else
    setStatus('ok', `${geo.num_vertices} vértices reconhecidos e mapeados.`);
  desenhar(geo, document.getElementById('identificador').value.trim());
  document.getElementById('btn-save').disabled=false;
};

/* ===================== GRAVAR ===================== */
document.getElementById('btn-save').onclick = async ()=>{
  const identificador = document.getElementById('identificador').value.trim();
  const numero_matricula = document.getElementById('numero_matricula').value.trim();
  if(!identificador && !numero_matricula){ setStatus('err','Informe a identificação do imóvel ou o número da matrícula.'); return; }
  const fonte = origemAtual==='kml' ? kmlRaw : document.getElementById('memorial').value;
  setStatus('warn','Gravando…');
  const res = await post({acao:'salvar', origem:origemAtual, memorial:fonte,
    identificador, numero_matricula,
    proprietario: document.getElementById('proprietario').value.trim(),
    cpf: document.getElementById('cpf').value.trim(),
    tipo_imovel: document.getElementById('tipo_imovel').value
  });
  if(!res.ok){ setStatus('err', res.erro || 'Falha ao gravar.'); return; }
  setStatus('ok', res.mensagem);
  if(res.id){ abrirCorPainel(res.id, null, null); onrSetAtivo(res.id, identificador); onrPreencherGeometria(lastGeo); }
  carregarLista();
};

/* ===================== IMPORTAÇÃO KML ===================== */
/* ---- Importação de KML: 1 ou vários arquivos (1 imóvel por arquivo) ---- */
const kmlLoteZone = document.getElementById('kml-lote-zone');
const kmlLoteFile = document.getElementById('kml-lote-file');
if(kmlLoteZone && kmlLoteFile){
  kmlLoteZone.onclick = ()=> kmlLoteFile.click();
  kmlLoteFile.onchange = e=>{ if(e.target.files && e.target.files.length) lerLoteKml(e.target.files); e.target.value=''; };
  ['dragover','dragenter'].forEach(ev=>kmlLoteZone.addEventListener(ev,e=>{e.preventDefault();kmlLoteZone.classList.add('drag');}));
  ['dragleave','drop'].forEach(ev=>kmlLoteZone.addEventListener(ev,e=>{e.preventDefault();kmlLoteZone.classList.remove('drag');}));
  kmlLoteZone.addEventListener('drop', e=>{ const fs=e.dataTransfer.files; if(fs && fs.length) lerLoteKml(fs); });
}
function resetKmlZone(){ /* zona de KML único removida — no-op por compatibilidade */ }
// Define se o nome do arquivo é "número de matrícula" (só dígitos e separadores . - / espaço)
function nomeEhMatricula(nome){
  return /[0-9]/.test(nome) && /^[0-9.\-\/\s]+$/.test(nome);
}
function resultadoDeResposta(nome, r){
  if(!r || !r.ok) return {nome, status:'erro', id:null, msg:(r&&r.erro)||'falha', inconsistencias:[]};
  const status = r.existe ? 'duplicado' : 'criado';
  return {nome, status, id:r.id||null, msg:r.mensagem||'', inconsistencias:r.inconsistencias||[]};
}
async function lerLoteKml(files){
  const arr = Array.from(files).filter(f=>/\.kml$/i.test(f.name));
  if(!arr.length){ setStatus('err','Selecione um ou mais arquivos .kml.'); return; }
  // Um único arquivo com VÁRIOS polígonos -> abre o painel de nomeação
  if(arr.length===1){
    try{
      const txt = await arr[0].text(); kmlRaw = txt; kmlNomeArquivo = arr[0].name||'';
      const res = await post({acao:'processar_kml', kml:txt});
      if(res && res.ok && res.total>1){ origemAtual='kml'; abrirPainelKml(res.placemarks); setStatus('ok', `${res.total} polígonos no KML — nomeie cada um e grave.`); return; }
    }catch(e){ /* cai no fluxo padrão abaixo */ }
  }
  // 1 ou vários arquivos (1 imóvel por arquivo): processa com progresso + lista de resultados
  const resultados=[];
  importProgressShow('Importando KML', arr.length);
  for(let i=0;i<arr.length;i++){
    const f=arr[i]; const base=f.name.replace(/\.kml$/i,'').trim();
    importProgressUpdate(i, arr.length, base);
    try{
      const txt = await f.text();
      const params = { acao:'salvar', origem:'kml', memorial: txt, nome_arquivo: f.name };
      if(nomeEhMatricula(base)) params.numero_matricula = base; else params.identificador = base;
      const r = await post(params);
      resultados.push(resultadoDeResposta(base, r));
    }catch(e){ resultados.push({nome:base, status:'erro', id:null, msg:'erro de leitura', inconsistencias:[]}); }
  }
  importProgressUpdate(arr.length, arr.length, '');
  importProgressHide();
  await carregarLista(); if(modo==='overview') verTodos();
  importResultadosModal('Importação de KML', resultados);
}

/* ===================== PROGRESSO + RESULTADOS DE IMPORTAÇÃO ===================== */
const IMPORT_RING_LEN = 326.7;
function importProgressShow(titulo, total){
  const ov=document.getElementById('import-ov'); if(!ov) return;
  const t=document.getElementById('import-ttl'); if(t) t.textContent = titulo||'Importando…';
  importProgressUpdate(0, total||1, '');
  ov.classList.add('show');
}
function importProgressUpdate(done, total, nome){
  total = total||1; const pct = Math.max(0, Math.min(100, Math.round(done/total*100)));
  const fg=document.getElementById('import-ring-fg'); if(fg) fg.style.strokeDashoffset = (IMPORT_RING_LEN*(1-pct/100)).toFixed(1);
  const p=document.getElementById('import-pct'); if(p) p.textContent = pct+'%';
  const m=document.getElementById('import-meta'); if(m) m.textContent = `${Math.min(done,total)} de ${total}`;
  const fn=document.getElementById('import-file'); if(fn) fn.textContent = nome||'';
}
function importProgressHide(){ const ov=document.getElementById('import-ov'); if(ov) ov.classList.remove('show'); }

let impresIdsInc = [];
function incLinhasHTML(inc){
  if(!inc || !inc.length) return '';
  return '<div class="impres-inc">'+inc.map(x=>{
    const sev=(x.sev||'alerta'); const tag = sev==='erro'?'ERRO':(sev==='info'?'INFO':'ALERTA');
    return `<div class="inc-line"><span class="inc-tag ${sev}">${tag}</span><span class="inc-msg">${escapeHtml(x.msg||'')}</span></div>`;
  }).join('')+'</div>';
}
function importResultadosModal(titulo, resultados){
  resultados = resultados||[];
  const cont = {criado:0,duplicado:0,erro:0}; let comInc=0; impresIdsInc=[];
  resultados.forEach(r=>{ cont[r.status]=(cont[r.status]||0)+1; if(r.inconsistencias && r.inconsistencias.length){ comInc++; if(r.id) impresIdsInc.push(r.id); } });
  const tt=document.getElementById('impres-titulo'); if(tt) tt.textContent = titulo||'Resultado da importação';
  const resumo=[];
  if(cont.criado) resumo.push(`<span class="impres-chip ok">${cont.criado} cadastrado(s)</span>`);
  if(cont.duplicado) resumo.push(`<span class="impres-chip dup">${cont.duplicado} já existente(s)</span>`);
  if(cont.erro) resumo.push(`<span class="impres-chip err">${cont.erro} com erro</span>`);
  if(comInc) resumo.push(`<span class="impres-chip warn">${comInc} com inconsistência(s)</span>`);
  document.getElementById('impres-resumo').innerHTML = resumo.join('') || '<span class="impres-chip">Nada importado</span>';
  document.getElementById('impres-list').innerHTML = resultados.map(r=>{
    const ic = r.status==='criado'?'✓':(r.status==='duplicado'?'≡':'×');
    const st = r.status==='criado'?'Cadastrado':(r.status==='duplicado'?'Já existente':'Erro');
    const rel = (r.inconsistencias && r.inconsistencias.length && r.id)
      ? `<div class="impres-relrow"><button class="mini-rel" data-rel="${r.id}">⤓ Relatório deste imóvel</button></div>` : '';
    return `<div class="impres-item">
      <div class="impres-row1"><span class="impres-ic ${r.status}">${ic}</span><span class="impres-nome">${escapeHtml(r.nome||'(sem nome)')}</span><span class="impres-st">${st}</span></div>
      ${r.msg?`<div class="impres-msg">${escapeHtml(r.msg)}</div>`:''}
      ${incLinhasHTML(r.inconsistencias)}
      ${rel}
    </div>`;
  }).join('') || '<div class="impres-msg">Nenhum item processado.</div>';
  document.querySelectorAll('#impres-list [data-rel]').forEach(b=> b.onclick=()=> gerarRelatorioInconsistencias([+b.dataset.rel]));
  const relBtn=document.getElementById('impres-rel');
  if(relBtn){ relBtn.style.display = impresIdsInc.length?'inline-flex':'none'; relBtn.onclick=()=> gerarRelatorioInconsistencias(impresIdsInc); }
  document.getElementById('modal-import-res').classList.add('show');
}
function gerarRelatorioInconsistencias(ids){
  ids = (ids||[]).filter(Boolean);
  if(!ids.length){ setStatus('warn','Não há imóveis com inconsistências para o relatório.'); return; }
  const f=document.createElement('form'); f.method='POST'; f.action=window.location.pathname; f.target='_blank';
  const i1=document.createElement('input'); i1.type='hidden'; i1.name='acao'; i1.value='relatorio_inconsistencias'; f.appendChild(i1);
  const i2=document.createElement('input'); i2.type='hidden'; i2.name='ids'; i2.value=JSON.stringify(ids); f.appendChild(i2);
  document.body.appendChild(f); f.submit(); document.body.removeChild(f);
}
(function(){
  const fechar=()=>{ const m=document.getElementById('modal-import-res'); if(m) m.classList.remove('show'); };
  const x=document.getElementById('impres-x'); if(x) x.onclick=fechar;
  const fe=document.getElementById('impres-fechar'); if(fe) fe.onclick=fechar;
  const ov=document.getElementById('modal-import-res'); if(ov) ov.addEventListener('click', e=>{ if(e.target===ov) fechar(); });
})();


// abre o painel de nomeação dos imóveis do KML (vários polígonos)
function abrirPainelKml(placemarks){
  modo='kml';
  document.getElementById('overview-panel').classList.remove('show');
  document.getElementById('btn-todos').classList.remove('active');
  limparSingle(); limparOverview();
  document.getElementById('overlay').style.display='none';
  document.getElementById('readout').style.display='none';
  document.getElementById('stats').style.display='none';

  kmlPlacemarks = placemarks.map((pm,i)=>({
    pts: pm.pts, area_ha: pm.area_ha, num_vertices: pm.num_vertices,
    nome: pm.nome || ('Imóvel ' + (i+1)), _poly:null, _label:null
  }));

  const b = new google.maps.LatLngBounds();
  kmlPlacemarks.forEach((pm,i)=>{
    const path = pm.pts.map(p=>({lat:p[0], lng:p[1]}));
    pm._poly = new google.maps.Polygon({paths:path,strokeColor:'#5b96e6',strokeOpacity:.9,
      strokeWeight:1.5,fillColor:'#5b96e6',fillOpacity:.15,map:map});
    overviewPolys.push(pm._poly);
    pm._label = addLabel(centroidOf(pm.pts), pm.nome);
    pm._poly.addListener('click',()=>{
      const el=document.querySelector('.kml-row[data-i="'+i+'"] input'); if(el){ el.focus(); }
      map.panTo(centroidOf(pm.pts));
    });
    path.forEach(pt=>b.extend(pt));
  });
  map.fitBounds(b,40);

  const rows = document.getElementById('kml-rows');
  rows.innerHTML = kmlPlacemarks.map((pm,i)=>`
    <div class="kml-row" data-i="${i}">
      <div class="top">
        <span class="idx">#${i+1}</span>
        <span class="meta">${pm.num_vertices} vtx · ${fmt(pm.area_ha,2)} ha</span>
      </div>
      <div class="inp">
        <input type="text" value="${escapeHtml(pm.nome)}" placeholder="Nome ou matrícula">
        <select><option value="nome">Nome</option><option value="matricula">Matrícula</option></select>
      </div>
    </div>`).join('');

  rows.querySelectorAll('.kml-row').forEach(row=>{
    const i = +row.dataset.i;
    const inp = row.querySelector('input');
    inp.oninput = ()=>{
      kmlPlacemarks[i].nome = inp.value;
      if(kmlPlacemarks[i]._label) kmlPlacemarks[i]._label.setText(inp.value || ('Imóvel '+(i+1)));
    };
    inp.onfocus = ()=>{ map.panTo(centroidOf(kmlPlacemarks[i].pts)); };
  });

  document.getElementById('kml-sub').textContent = kmlPlacemarks.length + ' polígonos · nomeie e grave';
  document.getElementById('btn-import-lote').textContent = 'Gravar ' + kmlPlacemarks.length + ' imóveis';
  document.getElementById('kml-panel').classList.add('show');
}

document.getElementById('kml-close').onclick = ()=>{
  document.getElementById('kml-panel').classList.remove('show');
  limparOverview();
  document.getElementById('overlay').style.display='grid';
  resetKmlZone();
};

document.getElementById('btn-import-lote').onclick = async ()=>{
  if(!kmlPlacemarks.length) return;
  const nomes=[], tipos=[];
  document.querySelectorAll('#kml-rows .kml-row').forEach(row=>{
    const i=+row.dataset.i;
    nomes[i]=(row.querySelector('input').value||'').trim();
    tipos[i]=row.querySelector('select').value;
  });
  setStatus('warn','Gravando…');
  const r = await post({acao:'salvar_kml_lote', kml:kmlRaw, nomes:JSON.stringify(nomes), tipos:JSON.stringify(tipos), nome_arquivo:kmlNomeArquivo});
  if(!r.ok){ setStatus('err', r.erro||'Falha na importação.'); return; }
  document.getElementById('kml-panel').classList.remove('show');
  await carregarLista(); verTodos();
  if(Array.isArray(r.resultados)) importResultadosModal('Importação de KML (vários polígonos)', r.resultados);
  setStatus('ok', `${r.salvos} imóveis gravados do KML.`);
};

/* ===================== VISÃO GERAL + SOBREPOSIÇÕES ===================== */
function ringLngLat(pts){
  const r = pts.map(p=>[p[1],p[0]]);            // turf usa [lng,lat]
  const f=r[0], l=r[r.length-1];
  if(f[0]!==l[0]||f[1]!==l[1]) r.push([f[0],f[1]]);
  return r;
}
function bboxOf(pts){
  let mnx=1e9,mny=1e9,mxx=-1e9,mxy=-1e9;
  pts.forEach(p=>{ if(p[1]<mnx)mnx=p[1]; if(p[1]>mxx)mxx=p[1]; if(p[0]<mny)mny=p[0]; if(p[0]>mxy)mxy=p[0]; });
  return [mnx,mny,mxx,mxy];
}
function bboxOverlap(a,b){ return !(a[2]<b[0]||b[2]<a[0]||a[3]<b[1]||b[3]<a[1]); }
function turfToPaths(geom){
  const out=[];
  const polys = geom.type==='MultiPolygon' ? geom.coordinates : [geom.coordinates];
  polys.forEach(poly=>{ out.push(poly[0].map(c=>({lat:c[1],lng:c[0]}))); });
  return out;
}

document.getElementById('btn-todos').onclick = ()=>{
  if(modo==='overview') sairOverview(); else verTodos();
};
document.getElementById('btn-relatorio').onclick = gerarRelatorio;

function sairOverview(){
  modo='single';
  document.getElementById('btn-todos').classList.remove('active');
  document.getElementById('overview-panel').classList.remove('show');
  limparOverview();
  document.getElementById('overlay').style.display='grid';
}

/* ---- contagem de imóveis distintos numa lista de sobreposições ---- */
function totalDistintos(overlaps){
  const s=new Set(); overlaps.forEach(o=>{ s.add(o.a.id); s.add(o.b.id); }); return s.size;
}

/* ---- envia um conjunto de sobreposições para o PDF (nova aba) ---- */
function matsParaNomeArquivo(overlaps){
  const termo = (document.getElementById('ov-busca')?.value || '').trim();
  let nums = [];
  if(termo){
    // matrículas/termos efetivamente pesquisados (separados por ; ou ,)
    nums = termo.split(/[;,]+/).map(s=>s.trim()).filter(Boolean)
               .map(s=>s.replace(/^0+(?=\d)/,''));   // sem zeros à esquerda
  } else {
    // sem filtro: matrículas distintas envolvidas nas sobreposições do relatório
    const set=new Set();
    (overlaps||[]).forEach(o=>{
      [o.a.numero_matricula, o.b.numero_matricula].forEach(m=>{
        const v=(m==null?'':String(m)).trim(); if(v) set.add(v.replace(/^0+(?=\d)/,''));
      });
    });
    nums=[...set];
  }
  // remove duplicadas preservando ordem
  nums = nums.filter((v,i)=>nums.indexOf(v)===i);
  if(!nums.length || nums.length>8) return '';   // muitas: usa o nome genérico
  return nums.join(', ');
}
function gerarRelatorioComDados(overlaps, total){
  const lean = overlaps.map(o=>({
    a:{id:o.a.id, identificador:o.a.identificador, numero_matricula:o.a.numero_matricula, area_ha:o.a.area_ha},
    b:{id:o.b.id, identificador:o.b.identificador, numero_matricula:o.b.numero_matricula, area_ha:o.b.area_ha},
    area_ha:o.area_ha, centro:o.centro, rings:o.rings,
    tipo:o.tipo||'material', largura_m:(o.largura_m!=null&&isFinite(o.largura_m))?o.largura_m:null
  }));
  const dados = JSON.stringify({ total_imoveis: total, overlaps: lean });
  const f = document.createElement('form');
  f.method='POST'; f.action=window.location.pathname; f.target='_blank';
  const i1=document.createElement('input'); i1.type='hidden'; i1.name='acao'; i1.value='relatorio_sobreposicao'; f.appendChild(i1);
  const i2=document.createElement('input'); i2.type='hidden'; i2.name='dados'; i2.value=dados; f.appendChild(i2);
  const i3=document.createElement('input'); i3.type='hidden'; i3.name='mats'; i3.value=matsParaNomeArquivo(overlaps); f.appendChild(i3);
  document.body.appendChild(f); f.submit(); document.body.removeChild(f);
}

/* relatório do conjunto exibido (todas ou filtradas por imóvel) */
function gerarRelatorio(){
  if(modo!=='overview'){ setStatus('warn','Abra "Ver todos no mapa" antes de gerar o relatório.'); return; }
  const lista = (overlapsExibidos && overlapsExibidos.length!==undefined) ? overlapsExibidos : overlapsAtuais;
  if(!lista.length){ setStatus('warn','Não há sobreposições para relatar (verifique o filtro).'); return; }
  gerarRelatorioComDados(lista, totalDistintos(lista));
}
(function(){
  const b=document.getElementById('ov-busca');
  if(b) b.addEventListener('input', filtrarOverlaps);
  const c=document.getElementById('ov-busca-clear');
  if(c) c.addEventListener('click', ()=>{ const bb=document.getElementById('ov-busca'); if(bb){ bb.value=''; bb.focus(); } filtrarOverlaps(); });
})();

/* ocultar / reexibir o painel (sem sair do overview) */
document.getElementById('ov-hide').onclick = ()=>{
  document.getElementById('overview-panel').classList.remove('show');
  if(modo==='overview') document.getElementById('ov-reopen').classList.add('show');
};
document.getElementById('ov-reopen').onclick = ()=>{
  document.getElementById('ov-reopen').classList.remove('show');
  document.getElementById('overview-panel').classList.add('show');
};

/* ---- seleção de imóveis (Ctrl+clique) ---- */

/* Ponto dentro do polígono (ray casting em [lat,lng]) — independe da lib geometry */
function pontoEmPoligono(latLng, pts){
  if(!pts || pts.length<3) return false;
  const x=latLng.lng(), y=latLng.lat(); let dentro=false;
  for(let i=0,j=pts.length-1;i<pts.length;j=i++){
    const xi=pts[i][1], yi=pts[i][0], xj=pts[j][1], yj=pts[j][0];
    if(((yi>y)!==(yj>y)) && (x < (xj-xi)*(y-yi)/((yj-yi)||1e-12)+xi)) dentro=!dentro;
  }
  return dentro;
}
/* Todos os imóveis cujo polígono contém o ponto, do menor para o maior (o de cima primeiro) */
function imoveisSobPonto(latLng){
  return itensOverview
    .filter(o=>o && o.pts && pontoEmPoligono(latLng, o.pts))
    .sort((a,b)=>(+a.area_ha||0)-(+b.area_ha||0));
}
/* Estado do ciclo: cliques repetidos ~no mesmo lugar avançam para a camada seguinte */
let pilhaUltima = {x:-99, y:-99, idx:0, ids:''};
function clicarPilha(latLng, e, ctrl){
  const pilha = imoveisSobPonto(latLng);
  if(!pilha.length) return;
  const ev = e && e.domEvent ? e.domEvent : null;
  const x = ev ? ev.clientX : 0, y = ev ? ev.clientY : 0;
  const ids = pilha.map(o=>o.id).join(',');
  const mesmoLocal = ids===pilhaUltima.ids && Math.abs(x-pilhaUltima.x)<14 && Math.abs(y-pilhaUltima.y)<14;
  const idx = (mesmoLocal && pilha.length>1) ? (pilhaUltima.idx+1) % pilha.length : 0;
  pilhaUltima = {x, y, idx, ids};
  const it = pilha[idx];
  if(pilha.length>1) swalToast('info', `Imóvel ${idx+1} de ${pilha.length} sob o cursor — clique de novo para alternar.`);
  if(ctrl) toggleSelecao(it); else abrirSeletorCor(it, e);
}
/* Seleção direta a partir do rótulo (ignora a sobreposição: vai exatamente neste imóvel) */
function selecionarImovelDireto(it, ctrl){
  if(!it) return;
  pilhaUltima = {x:-99, y:-99, idx:0, ids:''};
  if(ctrl) toggleSelecao(it);
  else abrirSeletorCor(it, {latLng: new google.maps.LatLng(centroidOf(it.pts).lat, centroidOf(it.pts).lng)});
}

function estiloImovel(it){
  if(!it._poly) return;
  const sel = selecionados.has(it.id);
  const base = corBaseImovel(it);
  it._poly.setOptions(sel
    ? {strokeColor:'#f59e0b', strokeWeight:2.5, fillColor:'#f59e0b', fillOpacity:.30, zIndex:3}
    : {strokeColor:base, strokeOpacity:strokeOpacImovel(it), strokeWeight:imovelMorto(it)?1:1.5, fillColor:base, fillOpacity:opacidadeImovel(it), zIndex:imovelMorto(it)?0:1});
}
function atualizarSelBar(){
  const n=selecionados.size;
  document.getElementById('sel-n').textContent=n;
  document.getElementById('sel-bar').classList.toggle('show', n>0);
}
function toggleSelecao(it){
  if(selecionados.has(it.id)) selecionados.delete(it.id); else selecionados.add(it.id);
  estiloImovel(it); atualizarSelBar(); sincronizarListaSelecao();
}
function limparSelecao(){
  selecionados.clear();
  itensOverview.forEach(it=>estiloImovel(it));
  atualizarSelBar(); sincronizarListaSelecao();
}
/* reflete a seleção atual nos itens da lista "Imóveis gravados" */
function sincronizarListaSelecao(){
  document.querySelectorAll('#saved-list .item').forEach(el=>{
    el.classList.toggle('sel', selecionados.has(Number(el.dataset.id)));
  });
}
/* seleciona/desseleciona um imóvel a partir da lista (Ctrl+clique) */
async function selecionarDaLista(id){
  id = Number(id);
  if(modo!=='overview'){ await verTodos(); }   // garante o mapa e as sobreposições calculadas
  const it = itensOverview.find(x=>Number(x.id)===id);
  if(it){ toggleSelecao(it); }
  else {
    if(selecionados.has(id)) selecionados.delete(id); else selecionados.add(id);
    atualizarSelBar(); sincronizarListaSelecao();
  }
}
document.getElementById('sel-limpar').onclick = limparSelecao;
document.getElementById('sel-relatorio').onclick = ()=>{
  if(!selecionados.size){ setStatus('warn','Selecione imóveis com Ctrl+clique.'); return; }
  let subset;
  if(selecionados.size===1)
    subset = overlapsAtuais.filter(o=> selecionados.has(o.a.id) || selecionados.has(o.b.id));
  else
    subset = overlapsAtuais.filter(o=> selecionados.has(o.a.id) && selecionados.has(o.b.id));
  if(!subset.length){ setStatus('warn','Nenhuma sobreposição entre os imóveis selecionados.'); return; }
  gerarRelatorioComDados(subset, totalDistintos(subset));
};

// === Classificação de sobreposição conforme Provimento CNJ 149 (red. Prov. 195/2025), Art. 440-AZ ===
// §1º sobreposição MATERIAL: ultrapassa a tolerância posicional (manual técnico do ONR) → exige saneamento.
// §2º sobreposição FORMAL: apenas nas divisas / pequena parte por técnica de levantamento, dentro da tolerância.
// Tolerância posicional de referência: 0,50 m (norma de georreferenciamento). A faixa de divisa entre dois
// imóveis, cada um com erro até 0,50 m, pode chegar a ~1,0 m de largura → usamos isso como limite do "formal".
const TOL_POSICIONAL_M = 0.50;
const TOL_DIVISA_M = 2 * TOL_POSICIONAL_M; // 1,0 m
function larguraFaixaSobrep(inter){
  // largura média de uma faixa ≈ 2 * área / perímetro (robusto para tiras finas de divisa)
  try{
    const area = turf.area(inter);          // m²
    let perim = 0;
    const ln = turf.polygonToLine(inter);
    if(ln.type==='FeatureCollection'){ (ln.features||[]).forEach(f=>{ perim += turf.length(f,{units:'meters'}); }); }
    else { perim = turf.length(ln,{units:'meters'}); }
    if(perim<=0) return Infinity;
    return 2*area/perim;
  }catch(e){ return Infinity; }
}
function classificarSobrep(inter){
  const larg = larguraFaixaSobrep(inter);
  return { tipo: (larg <= TOL_DIVISA_M ? 'formal' : 'material'), largura_m: larg };
}

async function verTodos(preservarVista){
  modo='overview';
  document.getElementById('btn-todos').classList.add('active');
  document.getElementById('kml-panel').classList.remove('show');
  limparSingle(); limparOverview();
  itensOverview=[];
  document.getElementById('readout').style.display='none';
  document.getElementById('overlay').style.display='none';

  const res = await post({acao:'listar_geo'});
  if(!res.ok || !res.itens.length){
    document.getElementById('btn-todos').classList.remove('active');
    document.getElementById('overlay').style.display='grid';
    return;
  }
  const itens = res.itens;
  itensOverview = itens;
  const bounds = new google.maps.LatLngBounds();

  // desenha todos os polígonos
  itens.forEach(it=>{
    const path = it.pts.map(p=>({lat:p[0],lng:p[1]}));
    const base = corBaseImovel(it);
    const poly = new google.maps.Polygon({paths:path,strokeColor:base,strokeOpacity:strokeOpacImovel(it),
      strokeWeight:imovelMorto(it)?1:1.5,fillColor:base,fillOpacity:opacidadeImovel(it),map:map,zIndex:imovelMorto(it)?0:1});
    it._poly = poly;
    poly.addListener('click',(e)=>{
      const ctrl = ctrlAtivo || (e && e.domEvent && (e.domEvent.ctrlKey || e.domEvent.metaKey));
      if(e && e.latLng) clicarPilha(e.latLng, e, ctrl);
      else if(ctrl) toggleSelecao(it); else abrirSeletorCor(it, e);
    });
    // clique direito também seleciona (alternativa ao Ctrl, e cobre Mac)
    poly.addListener('rightclick',(e)=>{ if(e && e.latLng) clicarPilha(e.latLng, e, true); else toggleSelecao(it); });
    overviewPolys.push(poly);
    it._bbox = bboxOf(it.pts);
    const centro = centroidOf(it.pts);
    // rótulo padrão: "Mat. <número sem zeros à esquerda>", se existir — agora clicável (seleciona este imóvel)
    const mat = (it.numero_matricula||'').trim();
    if(mat) it._label = addLabel(centro, rotuloMat(mat), (ev)=>{
      const ctrl = ctrlAtivo || (ev && (ev.ctrlKey || ev.metaKey));
      selecionarImovelDireto(it, ctrl);
    });
    // identificação do imóvel: só ao pousar o mouse ~2s
    poly.addListener('mouseover', ()=> agendarHoverTip(centro, it.identificador));
    poly.addListener('mousemove', ()=> { if(!hoverTip && !hoverTimer) agendarHoverTip(centro, it.identificador); });
    poly.addListener('mouseout',  ()=> ocultarHoverTip());
    path.forEach(pt=>bounds.extend(pt));
  });
  if(!preservarVista) map.fitBounds(bounds,40);

  // detecção de sobreposição (pré-filtro por bounding box + turf.intersect)
  const overlaps = [];
  for(let i=0;i<itens.length;i++){
    for(let j=i+1;j<itens.length;j++){
      // matrículas encerradas (unificação/desmembramento) deram origem a algo novo: não geram sobreposição
      if(imovelMorto(itens[i]) || imovelMorto(itens[j])) continue;
      if(!bboxOverlap(itens[i]._bbox, itens[j]._bbox)) continue;
      try{
        const a = turf.polygon([ringLngLat(itens[i].pts)]);
        const b = turf.polygon([ringLngLat(itens[j].pts)]);
        const inter = turf.intersect(a,b);
        if(inter){
          const areaHa = turf.area(inter)/10000;
          if(areaHa < 0.0001) continue; // ignora toques de borda
          // Desmembramento: o trecho coincidente com a nova matrícula é "morto" (cinza), não conflito.
          // A matrícula-mãe continua ativa; apenas este trecho fica destacado.
          if(ehDesmembramentoPar(itens[i], itens[j])){
            turfToPaths(inter.geometry).forEach(path=>{
              const op = new google.maps.Polygon({paths:path,strokeColor:'#9aa3ad',
                strokeOpacity:.55,strokeWeight:1,fillColor:'#9aa3ad',fillOpacity:.5,map:map,zIndex:4,clickable:false});
              op._pair=[itens[i].id, itens[j].id]; op._tipo='morto';
              overlapPolys.push(op);
            });
            // exceção: se o trecho cobre ~toda a matrícula-mãe, ela fica "morta" por completo
            let mae=null;
            if(itens[i].motivo_situacao==='desmembramento' && listaMatKey(itens[i].matricula_sucessora).includes(matKey(itens[j].numero_matricula))) mae=itens[i];
            else if(itens[j].motivo_situacao==='desmembramento' && listaMatKey(itens[j].matricula_sucessora).includes(matKey(itens[i].numero_matricula))) mae=itens[j];
            if(mae && mae.area_ha>0 && (areaHa/mae.area_ha)>=0.98 && mae._poly){
              mae._poly.setOptions({strokeColor:'#9aa3ad',strokeOpacity:.4,strokeWeight:1,fillColor:'#9aa3ad',fillOpacity:.05,zIndex:0});
            }
            continue; // não entra na lista de sobreposições (sem vermelho, sem contagem)
          }
          const rings = turfToPaths(inter.geometry).map(path=>path.map(pt=>[pt.lat, pt.lng]));
          const cls = classificarSobrep(inter);              // formal (divisa/tolerável) x material
          const formal = cls.tipo==='formal';
          // Sobreposição MERAMENTE FORMAL (apenas na divisa, dentro da tolerância — Art. 440-AZ §2º):
          // NÃO é desenhada no mapa. Apenas as MATERIAIS aparecem destacadas (vermelho).
          if(!formal){
            turfToPaths(inter.geometry).forEach(path=>{
              const op = new google.maps.Polygon({paths:path,strokeColor:'#e2342f',
                strokeOpacity:.95,strokeWeight:1.5,fillColor:'#e2342f',fillOpacity:.5,map:map,zIndex:5,clickable:false});
              op._pair=[itens[i].id, itens[j].id]; op._tipo='material';
              overlapPolys.push(op);
            });
          }
          const c = turf.centroid(inter).geometry.coordinates;
          overlaps.push({
            a:{id:itens[i].id, identificador:itens[i].identificador, numero_matricula:itens[i].numero_matricula, area_ha:itens[i].area_ha, pts:itens[i].pts},
            b:{id:itens[j].id, identificador:itens[j].identificador, numero_matricula:itens[j].numero_matricula, area_ha:itens[j].area_ha, pts:itens[j].pts},
            area_ha:areaHa, centro:{lat:c[1],lng:c[0]}, rings:rings, tipo:cls.tipo, largura_m:cls.largura_m
          });
        }
      }catch(err){ /* polígono inválido: ignora */ }
    }
  }

  overlapsAtuais = overlaps;
  totalImoveisAtual = itens.length;
  renderOverviewPanel(itens.length, overlaps);
  setStatus(overlaps.length? 'warn':'ok',
    overlaps.length? `${overlaps.length} sobreposição(ões) detectada(s) entre ${itens.length} imóveis.`
                   : `${itens.length} imóveis exibidos. Nenhuma sobreposição detectada.`);
}

/* ===================== CORES DE DESTAQUE DOS IMÓVEIS ===================== */
// Paleta — SEM tons de vermelho (reservado p/ sobreposições). Vibrantes + pastéis.
const PALETA_CORES = [
  '#2563eb','#0ea5e9','#0891b2','#0d9488','#16a34a','#65a30d',
  '#ca8a04','#f59e0b','#7c3aed','#6366f1','#c026d3','#db2777',
  '#93c5fd','#a5f3fc','#99f6e4','#bbf7d0','#d9f99d','#fde68a',
  '#fed7aa','#c7d2fe','#e9d5ff','#f5d0fe','#fbcfe8','#cbd5e1'
];
const COR_PADRAO = '#16a34a';
const OPACIDADE_PADRAO = 0.18, OPAC_MIN = 0.08, OPAC_MAX = 0.55;

function corValida(c){
  if(typeof c!=='string' || !/^#[0-9a-fA-F]{6}$/.test(c)) return false;
  const r=parseInt(c.substr(1,2),16), g=parseInt(c.substr(3,2),16), b=parseInt(c.substr(5,2),16);
  if(r>=150 && g<=90 && b<=90) return false; // bloqueia vermelho (reservado a sobreposições)
  return true;
}
function imovelMorto(it){ return !!(it && it.situacao === 'encerrada'); }
function normMat(s){ return (s==null?'':String(s)).replace(/[^0-9a-zA-Z]/g,'').toLowerCase(); }
function listaMat(s){ return (s==null?'':String(s)).split(',').map(x=>normMat(x)).filter(Boolean); }
/* Chave de comparação de NÚMERO de matrícula: além de remover pontuação e caixa,
   ignora ZEROS À ESQUERDA — assim "00000745", "745" e "0745" são a MESMA matrícula.
   Necessário porque os rótulos/matrículas são gravados com zero-padding (00000745),
   enquanto as sucessoras (inclusive intervalos 745-900) são digitadas sem padding. */
function matKey(s){ const k=normMat(s); return k.replace(/^0+(?=.)/,''); }
function listaMatKey(s){ return (s==null?'':String(s)).split(/[,;]+/).map(x=>matKey(x)).filter(Boolean); }
/* Rótulo de exibição da matrícula: remove zeros à esquerda (preservando letras/
   formato) e prefixa "Mat. ". Ex.: "00000860" -> "Mat. 860". */
function rotuloMat(s){
  let n = String(s==null?'':s).trim();
  if(!n) return '';
  n = n.replace(/^0+(?=\d)/,'');   // tira zeros à esquerda apenas antes de dígitos
  return 'Mat. ' + n;
}
/* A e B têm relação de desmembramento? (uma lista a outra como matrícula sucessora) */
function ehDesmembramentoPar(a,b){
  const an=matKey(a.numero_matricula), bn=matKey(b.numero_matricula);
  return (a.motivo_situacao==='desmembramento' && bn && listaMatKey(a.matricula_sucessora).includes(bn)) ||
         (b.motivo_situacao==='desmembramento' && an && listaMatKey(b.matricula_sucessora).includes(an));
}
function corBaseImovel(it){
  if(imovelMorto(it)) return '#9aa3ad';                 // cinza "morto"
  return (it && corValida(it.cor)) ? it.cor : COR_PADRAO;
}
function opacidadeImovel(it){
  if(imovelMorto(it)) return 0.05;                      // bem apagado
  let o = (it && it.cor_opacidade!=null) ? parseFloat(it.cor_opacidade) : OPACIDADE_PADRAO;
  if(isNaN(o)) o = OPACIDADE_PADRAO;
  return Math.max(OPAC_MIN, Math.min(OPAC_MAX, o));
}
function strokeOpacImovel(it){ return imovelMorto(it) ? 0.4 : 0.9; }
function swatchesHTML(atual){
  const a=(atual||'').toLowerCase();
  return PALETA_CORES.map(c=>`<button type="button" class="cor-sw${a===c?' sel':''}" style="background:${c}" title="${c}" data-cor="${c}"></button>`).join('');
}

let infoWinCor = null;
function incParse(v){
  if(!v) return [];
  if(Array.isArray(v)) return v;
  try{ const a=JSON.parse(v); return Array.isArray(a)?a:[]; }catch(e){ return []; }
}
function incSevTag(sev){ return sev==='erro'?'ERRO':(sev==='info'?'INFO':'ALERTA'); }
function infoImovelHTML(it){
  const linhas = [];
  const add=(rot,val)=>{ if(val!==undefined && val!==null && String(val).trim()!=='') linhas.push(`<div class="ip-row"><span class="ip-k">${rot}</span><span class="ip-v">${escapeHtml(String(val))}</span></div>`); };
  add('Matrícula', it.numero_matricula);
  add('Identificação', it.identificador);
  add('Proprietário', it.proprietario);
  add('CPF/CNPJ', it.cpf);
  add('Tipo', it.tipo_imovel);
  add('Área', (it.area_ha!=null ? fmt(it.area_ha,4)+' ha' : ''));
  const suc=((it.matricula_sucessora)||'').split(',').map(s=>s.trim()).filter(Boolean).join(', ');
  if(it.situacao==='encerrada') add('Situação', (it.motivo_situacao==='georreferenciamento'?'Encerrada por georreferenciamento':'Encerrada por unificação')+(suc?(' → '+suc):''));
  else if(it.motivo_situacao==='desmembramento') add('Situação', 'Desmembramento'+(suc?(' → trecho(s): '+suc):''));
  // alerta de imóvel fora do perímetro do município (também busca no cache da lista)
  const cacheFora = (typeof imoveisCache!=='undefined') ? imoveisCache.find(x=>String(x.id)===String(it.id)) : null;
  const foraMun = ((cacheFora && cacheFora.fora_municipio) || it.fora_municipio || '').toString().trim();
  let foraHTML = '';
  if(foraMun){
    foraHTML = `<div class="ip-inc" style="border-color:rgba(226,52,47,.5)">
      <div class="ip-inc-h">⚠ Imóvel FORA do município${foraMun!=='fora'?(' — está em '+escapeHtml(foraMun)):''}</div>
      <div class="ip-inc-row"><span class="inc-msg">Este imóvel não pertence ao município do cartório. O envio ao Mapa ONR e a carga ITN 03 estão <b>bloqueados</b> para esta matrícula.</span></div>
    </div>`;
  }
  // inconsistências (busca também no cache da lista, que traz o campo completo)
  const cache = (typeof imoveisCache!=='undefined') ? imoveisCache.find(x=>String(x.id)===String(it.id)) : null;
  const inc = incParse((cache && cache.inconsistencias) || it.inconsistencias);
  let incHTML = '';
  if(inc.length){
    incHTML = `<div class="ip-inc">
      <div class="ip-inc-h">⚠ ${inc.length} inconsistência(s) detectada(s)</div>
      ${inc.map(x=>`<div class="ip-inc-row"><span class="inc-tag ${x.sev||'alerta'}">${incSevTag(x.sev)}</span><span class="inc-msg">${escapeHtml(x.msg||'')}</span></div>`).join('')}
      <button type="button" class="ip-inc-btn" onclick="gerarRelatorioInconsistencias([${parseInt(it.id,10)||0}])">⤓ Gerar relatório de inconsistências</button>
    </div>`;
  }
  const base = linhas.join('') || '<div class="ip-row"><span class="ip-v" style="color:#9aa6b2">Sem dados cadastrados.</span></div>';
  return foraHTML + base + incHTML;
}
function destacarNoPainel(id){
  const lista=document.getElementById('saved-list'); if(!lista) return;
  lista.querySelectorAll('.item.destaque').forEach(el=>el.classList.remove('destaque'));
  const el=lista.querySelector('.item[data-id="'+id+'"]');
  if(el){ el.classList.add('destaque'); try{ el.scrollIntoView({block:'nearest',behavior:'smooth'}); }catch(_){ el.scrollIntoView(); } }
}
function abrirSeletorCor(it, e){
  if(!infoWinCor) infoWinCor = new google.maps.InfoWindow();
  const atual = corValida(it.cor) ? it.cor.toLowerCase() : '';
  const op = opacidadeImovel(it);
  infoWinCor.setContent(`<div class="cor-pop" id="cor-pop">
    <div class="cor-pop-t">Informações do imóvel</div>
    <div class="ip-box">${infoImovelHTML(it)}</div>
    <details class="cor-pop-acc">
      <summary class="cor-pop-lbl cor-pop-acc-sum">Cor de destaque</summary>
      <div class="cor-pop-grid" style="margin-top:8px">${swatchesHTML(atual)}</div>
      <div class="cor-pop-lbl" style="margin-top:9px">Intensidade</div>
      <input type="range" id="cor-pop-op" class="op-range" min="${OPAC_MIN}" max="${OPAC_MAX}" step="0.01" value="${op}">
      <button type="button" class="cor-pop-clear" id="cor-pop-clear">Remover destaque</button>
    </details>
  </div>`);
  infoWinCor.setPosition((e && e.latLng) ? e.latLng : centroidOf(it.pts));
  infoWinCor.open(map);
  if(infoWinCor.setZIndex) infoWinCor.setZIndex(99999);
  destacarNoPainel(it.id);
  google.maps.event.addListenerOnce(infoWinCor, 'domready', ()=>{
    const pop = document.getElementById('cor-pop'); if(!pop) return;
    let corSel = atual;
    pop.querySelectorAll('.cor-sw').forEach(b=> b.addEventListener('click', ()=>{
      corSel = b.dataset.cor;
      pop.querySelectorAll('.cor-sw').forEach(x=>x.classList.remove('sel')); b.classList.add('sel');
      const opv = parseFloat(document.getElementById('cor-pop-op').value);
      window.__setCorImovel(it.id, corSel, opv);
    }));
    const opEl = document.getElementById('cor-pop-op');
    if(opEl) opEl.addEventListener('change', ()=>{ if(corSel) window.__setCorImovel(it.id, corSel, parseFloat(opEl.value)); });
    const clr = document.getElementById('cor-pop-clear');
    if(clr) clr.addEventListener('click', ()=>{ corSel=''; window.__setCorImovel(it.id, '', null); });
  });
}

// Salva cor + intensidade e recolore ao vivo (visão geral, lista e foco)
window.__setCorImovel = async function(id, hex, opac){
  try{
    const params = {acao:'salvar_cor', id:id, cor:hex};
    if(opac!=null && !isNaN(opac)) params.opacidade = opac;
    const r = await post(params);
    if(!r.ok){ setStatus('err', r.erro || 'Não foi possível salvar a cor.'); return; }
    const novaCor = r.cor || null;
    const novaOp = (r.cor_opacidade!=null) ? parseFloat(r.cor_opacidade) : null;
    const cacheIt = (imoveisCache||[]).find(x=>String(x.id)===String(id));
    if(cacheIt){ cacheIt.cor = novaCor; cacheIt.cor_opacidade = novaOp; }
    const ov = (itensOverview||[]).find(x=>x.id===id);
    if(ov){ ov.cor = novaCor; ov.cor_opacidade = novaOp;
      if(ov._poly && !selecionados.has(ov.id)){
        const base = corBaseImovel(ov);
        ov._poly.setOptions({strokeColor:base, fillColor:base, fillOpacity:opacidadeImovel(ov)});
      }
    }
    if(imovelEditandoId === id){
      imovelEditandoCor = novaCor; imovelEditandoOpac = novaOp;
      marcarSwatchPainel(novaCor); ajustarSliderPainel(novaOp);
      if(polygon){
        const base = corValida(novaCor) ? novaCor : '#e2342f';
        polygon.setOptions({strokeColor: base, fillColor: base, fillOpacity: (novaOp!=null?novaOp:0.22)});
      }
    }
    renderLista();
    setStatus('ok', hex ? 'Cor/intensidade salvas.' : 'Destaque removido.');
  }catch(e){ setStatus('err','Erro ao salvar a cor.'); }
};

/* ---- Seletor de cor no painel (ao editar/gravar um imóvel) ---- */
let imovelEditandoId = null, imovelEditandoCor = null, imovelEditandoOpac = null;

function montarSeletorCorPainel(){
  const box = document.getElementById('cor-grid'); if(!box) return;
  box.innerHTML = swatchesHTML('');
  box.querySelectorAll('.cor-sw').forEach(b=> b.addEventListener('click', ()=>{
    if(!imovelEditandoId) return;
    const op = document.getElementById('cor-op');
    window.__setCorImovel(imovelEditandoId, b.dataset.cor, op?parseFloat(op.value):null);
  }));
  const clr = document.getElementById('cor-clear');
  if(clr) clr.addEventListener('click', ()=>{ if(imovelEditandoId) window.__setCorImovel(imovelEditandoId, '', null); });
  const op = document.getElementById('cor-op');
  if(op) op.addEventListener('change', ()=>{ if(imovelEditandoId && imovelEditandoCor) window.__setCorImovel(imovelEditandoId, imovelEditandoCor, parseFloat(op.value)); });
}
function marcarSwatchPainel(cor){
  const box = document.getElementById('cor-grid'); if(!box) return;
  const c = (cor||'').toLowerCase();
  box.querySelectorAll('.cor-sw').forEach(b=> b.classList.toggle('sel', b.dataset.cor===c));
}
function ajustarSliderPainel(op){ const s=document.getElementById('cor-op'); if(s) s.value = (op!=null&&!isNaN(op))?op:OPACIDADE_PADRAO; }
function abrirCorPainel(id, cor, opac){
  imovelEditandoId = id; imovelEditandoCor = corValida(cor)?cor:null; imovelEditandoOpac = (opac!=null)?parseFloat(opac):null;
  const sec = document.getElementById('cor-box'); if(sec) sec.style.display = id ? 'block' : 'none';
  marcarSwatchPainel(imovelEditandoCor); ajustarSliderPainel(imovelEditandoOpac);
  if(polygon && corValida(cor)) polygon.setOptions({strokeColor:cor, fillColor:cor, fillOpacity:(imovelEditandoOpac!=null?imovelEditandoOpac:0.22)});
}

function infoImovel(it){
  setStatus('ok', `<b>${escapeHtml(it.identificador)}</b> — ${fmt(it.area_ha,2)} ha (${it.origem}).`);
}

let overlapsExibidos = [];
function renderOverviewPanel(total, overlaps){
  const panel=document.getElementById('overview-panel');
  panel.classList.add('show');
  const busca=document.getElementById('ov-busca'); if(busca) busca.value='';
  filtrarOverlaps();
}
// conta sobreposições materiais x formais (divisa/toleráveis) — Prov. CNJ 149, Art. 440-AZ §§1º-2º
function contarTipos(lista){
  let mat=0, formal=0;
  (lista||[]).forEach(o=>{ if(o.tipo==='formal') formal++; else mat++; });
  return {mat, formal};
}
function filtrarOverlaps(){
  const termoRaw = (document.getElementById('ov-busca')?.value || '').trim();

  // Modo LISTA: se a busca tem ';', mostra SOMENTE os imóveis listados no mapa
  if(termoRaw.includes(';')){ aplicarFiltroPorLista(termoRaw); return; }

  // Modo normal: restaura todos os imóveis no mapa e filtra apenas a lista de sobreposições
  restaurarMapaCompleto();
  const termo = termoRaw.toLowerCase();
  const norm = s => (s==null?'':String(s)).toLowerCase();
  const normD = s => (s==null?'':String(s)).replace(/[^0-9a-z]/gi,'').toLowerCase();
  let lista = overlapsAtuais;
  if(termo){
    const td = normD(termoRaw);
    lista = overlapsAtuais.filter(o=>{
      const campos=[o.a.identificador,o.a.numero_matricula,o.b.identificador,o.b.numero_matricula];
      const txt = campos.some(c=>norm(c).includes(termo));
      const doc = td && [o.a.numero_matricula,o.b.numero_matricula].some(c=>normD(c) && normD(c).includes(td));
      return txt || doc;
    });
  }
  overlapsExibidos = lista;
  const sub=document.getElementById('ov-sub');
  if(sub){
    if(termo){
      sub.textContent = `${lista.length} de ${overlapsAtuais.length} sobreposição(ões) · filtro "${termoRaw}"`;
    } else {
      const t=contarTipos(overlapsAtuais);
      sub.textContent = `${totalImoveisAtual} imóveis · ${t.mat} material(is)` + (t.formal?` · ${t.formal} formal(is) de divisa (não exibidas no mapa)`:'');
    }
  }
  // rótulo do botão de relatório
  const btn=document.getElementById('btn-relatorio');
  if(btn) btn.textContent = termo ? 'Gerar relatório do imóvel filtrado (PDF)' : 'Gerar relatório de sobreposição (PDF)';
  desenharListaOverlaps(lista, termoRaw);
  agendarFocoBusca(termoRaw);
}

// Testa se um imóvel casa com um token (matrícula sem zeros à esquerda OU identificação)
function imovelCasaToken(it, tk){
  const t=(tk||'').trim(); if(!t) return false;
  const tl=t.toLowerCase();
  const tk2=matKey(t);
  const mk=matKey(it.numero_matricula);
  if(tk2 && (tk2===mk || (mk && mk.includes(tk2)))) return true;
  const idn=(it.identificador==null?'':String(it.identificador)).toLowerCase();
  if(idn.includes(tl)) return true;
  return false;
}
// Mostra SOMENTE os imóveis cujas matrículas/identificações foram listadas com ';'
function aplicarFiltroPorLista(termoRaw){
  const tokens = termoRaw.split(';').map(s=>s.trim()).filter(Boolean);
  const mostrados = new Set();
  const matched = [];
  itensOverview.forEach(it=>{
    const ok = tokens.some(tk=>imovelCasaToken(it, tk));
    if(it._poly)  it._poly.setMap(ok?map:null);
    if(it._label) it._label.setMap(ok?map:null);
    if(ok){ mostrados.add(it.id); matched.push(it); }
  });
  // ANÁLISE DE SOBREPOSIÇÃO entre os imóveis filtrados: mostra só os destaques
  // cujos dois imóveis estão exibidos; esconde os demais.
  overlapPolys.forEach(p=>{
    const pr=p._pair;
    const vis = pr && mostrados.has(pr[0]) && mostrados.has(pr[1]);
    p.setMap(vis?map:null);
  });
  // na lista do painel, mantém só sobreposições entre dois imóveis exibidos
  const lista = overlapsAtuais.filter(o=>mostrados.has(o.a.id) && mostrados.has(o.b.id));
  overlapsExibidos = lista;
  const sub=document.getElementById('ov-sub');
  if(sub){
    const t=contarTipos(lista);
    sub.textContent = `${matched.length} imóvel(is) · ${t.mat} material(is)` + (t.formal?` + ${t.formal} formal(is)`:'') + ` entre eles · lista de ${tokens.length} item(ns)`;
  }
  const btn=document.getElementById('btn-relatorio');
  if(btn) btn.textContent = lista.length ? 'Gerar relatório dos imóveis filtrados (PDF)' : 'Gerar relatório de sobreposição (PDF)';
  desenharListaOverlaps(lista, termoRaw);
  // ajusta o zoom para enquadrar só os imóveis exibidos
  if(matched.length){
    const b=new google.maps.LatLngBounds();
    matched.forEach(it=>(it.pts||[]).forEach(p=>b.extend({lat:p[0],lng:p[1]})));
    if(!b.isEmpty()) map.fitBounds(b, 60);
  }
  const semMatch = tokens.filter(tk=>!itensOverview.some(it=>imovelCasaToken(it,tk)));
  if(matched.length){
    const t = contarTipos(lista);
    let msg;
    if(t.mat) msg = `${matched.length} imóvel(is) exibido(s). ⚠ ${t.mat} sobreposição(ões) MATERIAL(IS) entre eles` + (t.formal?` (e ${t.formal} apenas formal/divisa).`:'.');
    else if(t.formal) msg = `${matched.length} imóvel(is) exibido(s). ${t.formal} sobreposição(ões) apenas FORMAL(IS) de divisa — toleráveis (Art. 440-AZ §2º).`;
    else msg = `${matched.length} imóvel(is) exibido(s). ✓ Nenhuma sobreposição entre eles.`;
    if(semMatch.length) msg += ` Não encontrado(s): ${semMatch.join(', ')}.`;
    setStatus(t.mat?'warn':'ok', msg);
  } else {
    setStatus('warn','Nenhum imóvel encontrado para: '+tokens.join(', '));
  }
}
// Restaura todos os imóveis e sobreposições no mapa (desfaz o filtro por lista)
function restaurarMapaCompleto(){
  if(modo!=='overview') return;
  if(itensOverview) itensOverview.forEach(it=>{
    if(it._poly)  it._poly.setMap(map);
    if(it._label) it._label.setMap(map);
  });
  overlapPolys.forEach(p=>p.setMap(map));
}
let buscaFocusTimer=null;
function agendarFocoBusca(termoRaw){
  if(buscaFocusTimer) clearTimeout(buscaFocusTimer);
  if(!termoRaw || termoRaw.length<2) return;
  buscaFocusTimer = setTimeout(()=>focarImovelBusca(termoRaw), 450);
}
function focarImovelBusca(termoRaw){
  if(modo!=='overview' || !itensOverview || !itensOverview.length) return;
  const termo = termoRaw.toLowerCase();
  const td = normMat(termoRaw);
  const matches = itensOverview.filter(it=>{
    const txt = [it.identificador, it.numero_matricula].some(c=>(c==null?'':String(c)).toLowerCase().includes(termo));
    const doc = td && normMat(it.numero_matricula) && normMat(it.numero_matricula).includes(td);
    return txt || doc;
  });
  if(!matches.length) return;
  const b = new google.maps.LatLngBounds();
  matches.forEach(it=>{ (it.pts||[]).forEach(p=>b.extend({lat:p[0],lng:p[1]})); });
  if(!b.isEmpty()) map.fitBounds(b, 70);
}
function desenharListaOverlaps(overlaps, termo){
  const box=document.getElementById('ov-overlaps');
  if(!overlaps.length){
    box.innerHTML = '<div class="ov-none">'+(termo ? '✓ Nenhuma sobreposição para "'+escapeHtml(termo)+'".' : '✓ Nenhuma sobreposição entre os imóveis exibidos.')+'</div>';
    return;
  }
  const lista = overlaps.slice().sort((x,y)=>{
    const fx = x.tipo==='formal'?1:0, fy = y.tipo==='formal'?1:0;
    if(fx!==fy) return fx-fy;          // materiais primeiro
    return y.area_ha-x.area_ha;
  });
  const rotulo = o => escapeHtml((o.numero_matricula? o.numero_matricula+' — ':'')+(o.identificador||'')) || escapeHtml(o.identificador||'(sem id)');
  box.innerHTML = '<div class="ttl">Sobreposições</div>' + lista.map((o,i)=>{
    const formal = o.tipo==='formal';
    const badge = formal
      ? `<span class="ov-tag formal" title="Apenas na divisa, dentro da tolerância — Art. 440-AZ §2º">formal · divisa</span>`
      : `<span class="ov-tag material" title="Ultrapassa a tolerância posicional — Art. 440-AZ §1º; requer saneamento">material</span>`;
    const larg = (o.largura_m!=null && isFinite(o.largura_m)) ? ` · faixa ~${fmt(o.largura_m,2)} m` : '';
    return `
    <div class="ov-row" data-i="${i}">
      <button class="row-rep" data-i="${i}">relatório</button>
      <div class="pair">${rotulo(o.a)} <span style="color:var(--faint)">⨯</span> ${rotulo(o.b)} ${badge}</div>
      <div class="amt">${fmt(o.area_ha,4)} ha sobrepostos${larg}</div>
    </div>`;
  }).join('');
  box.querySelectorAll('.ov-row').forEach(row=>{
    row.onclick=()=>{ const o=lista[+row.dataset.i]; map.panTo(o.centro); map.setZoom(15); };
  });
  box.querySelectorAll('.row-rep').forEach(btn=>{
    btn.onclick=(e)=>{ e.stopPropagation(); const o=lista[+btn.dataset.i]; gerarRelatorioComDados([o], 2); };
  });
}

/* ===================== LISTA DE SALVOS ===================== */
let imoveisCache = [];
let vistaLista = 'mapa'; // 'mapa' (mapeadas) | 'itn03' (exclusivas ITN 03)
async function carregarLista(){
  const res = await post({acao:'listar'});
  imoveisCache = (res.ok && res.itens) ? res.itens : [];
  renderLista();
}

/* ===================== SINCRONIZAÇÃO MULTIUSUÁRIO (polling) =====================
   Detecta cadastros/edições/exclusões feitos por QUALQUER usuário e atualiza a lista
   (e o mapa, preservando a vista) sem precisar recarregar a página. */
let listaSig = null;            // assinatura do último estado conhecido
let listaPollMs = 9000;         // intervalo do polling (ms)
let listaPollTimer = null;
let listaPollOcupado = false;

function podeAtualizarMapaPoll(){
  const modal = document.getElementById('modal-edit');
  if(modal && modal.classList.contains('show')) return false;          // não mexe durante edição
  const ov = document.getElementById('import-ov');
  if(ov && getComputedStyle(ov).display !== 'none') return false;       // não mexe durante importação
  if(typeof infoWinCor!=='undefined' && infoWinCor && infoWinCor.getMap && infoWinCor.getMap()) return false; // popup aberto
  return true;
}

async function pollLista(){
  if(listaPollOcupado || document.hidden) return;
  listaPollOcupado = true;
  try{
    const r = await post({acao:'lista_sig'});
    if(r && r.ok){
      if(listaSig === null){ listaSig = r.sig; }            // primeira leitura: estabelece a base
      else if(r.sig !== listaSig){                          // algo mudou (outro usuário ou esta aba)
        listaSig = r.sig;
        await carregarLista();
        if(typeof modo!=='undefined' && modo==='overview' && podeAtualizarMapaPoll()) verTodos(true);
      }
    }
  }catch(e){ /* silencioso: tenta novamente no próximo ciclo */ }
  finally{ listaPollOcupado = false; }
}

function iniciarPollLista(){
  if(listaPollTimer) clearInterval(listaPollTimer);
  listaPollTimer = setInterval(pollLista, listaPollMs);
  pollLista(); // estabelece a assinatura inicial imediatamente
}
document.addEventListener('visibilitychange', ()=>{ if(!document.hidden) pollLista(); });
window.addEventListener('focus', ()=>{ pollLista(); });
function renderLista(){
  const wrap = document.getElementById('saved-list');
  const termo = (document.getElementById('busca').value||'').trim().toLowerCase();
  const ehItn03 = it => String(it.itn03_exclusivo)==='1';
  const nExcl = imoveisCache.filter(ehItn03).length;
  const cb=document.getElementById('vt-count-itn03'); if(cb) cb.textContent = nExcl||'';
  const acts=document.getElementById('itn03-actions'); if(acts) acts.style.display = (vistaLista==='itn03')?'flex':'none';
  let itens = imoveisCache.filter(it=> vistaLista==='itn03' ? ehItn03(it) : !ehItn03(it));
  if(termo){
    itens = itens.filter(it=>[it.identificador,it.numero_matricula,it.proprietario,it.cpf,it.tipo_imovel,it.origem]
      .some(c=>(c||'').toString().toLowerCase().includes(termo)));
  }
  if(!itens.length){
    const vazio = vistaLista==='itn03'
      ? (nExcl ? 'Nenhuma encontrada.' : 'Nenhuma matrícula exclusiva ITN 03 ainda. Use “➕ Nova matrícula”.')
      : (imoveisCache.length?'Nenhum imóvel encontrado.':'Nenhum imóvel gravado ainda.');
    wrap.innerHTML = '<div class="empty-list">'+vazio+'</div>';
    return;
  }
  wrap.innerHTML = itens.map(it=>{
    const sub = [];
    if(it.numero_matricula) sub.push(escapeHtml(rotuloMat(it.numero_matricula)));
    if(it.proprietario) sub.push(escapeHtml(it.proprietario));
    const meta = `${fmt(it.area_ha,2)} ha`;
    const corDot = corValida(it.cor) ? `<span class="item-dot" style="background:${it.cor}"></span>` : '<span class="item-dot vazio"></span>';
    const tag = it.tipo_imovel ? `<span class="tag ${it.tipo_imovel==='rural'?'rural':'urb'}">${it.tipo_imovel}</span>` : '';
    const enviado = String(it.onr_enviado)==='1';
    const pronto  = String(it.onr_pronto)==='1';
    const excl    = ehItn03(it);
    const aptoItn = String(it.itn03_apto)==='1';
    const morto   = it.situacao==='encerrada';
    const desmembrada = !morto && it.motivo_situacao==='desmembramento';
    const ehGeoref = morto && it.motivo_situacao==='georreferenciamento';
    const mortoBadge = morto
      ? (ehGeoref
          ? `<span class="morto-badge" title="${it.matricula_sucessora?('Nova matrícula: '+escapeHtml(it.matricula_sucessora)):'Encerrada por georreferenciamento'}">⊗ georref.</span>`
          : `<span class="morto-badge" title="${it.matricula_sucessora?('Sucessora: '+escapeHtml(it.matricula_sucessora)):'Encerrada por unificação'}">✝ unificada</span>`)
      : (desmembrada ? `<span class="desmembra-badge" title="${it.matricula_sucessora?('Trecho originou a matrícula '+escapeHtml(it.matricula_sucessora)):'Desmembramento'}">✂ desmembrada</span>` : '');
    const exclBadge = excl
      ? `<span class="itn03-badge" title="Matrícula exclusiva ITN 03 (sem mapa)">ITN 03</span><span class="itn03-apto ${aptoItn?'ok':'no'}" title="${aptoItn?'Apta para a carga ITN 03':'Faltam dados mínimos (tipo, matrícula, CNM, município, UF)'}">${aptoItn?'✓ apta':'⚠ incompleta'}</span>`
      : '';
    const foraMun = (it.fora_municipio||'').toString().trim();
    const foraBadge = foraMun
      ? `<span class="fora-badge" title="Imóvel FORA do município${foraMun!=='fora'?(' — está em '+escapeHtml(foraMun)):''}. Não pertence ao cartório; envio ONR e carga ITN bloqueados.">⚠ fora do município</span>`
      : '';
    const statusTxt = it.onr_status ? escapeHtml(it.onr_status) : (enviado?'ENVIADO':'');
    const onrBadge = (statusTxt && !excl) ? `<span class="onr-badge ${enviado?'env':''}">${statusTxt}</span>` : '';
    const acaoBtn = excl
      ? `<button class="it-onr" data-act="itn03" title="${aptoItn?'Exportar carga ITN 03 desta matrícula':(foraMun?'Bloqueado: imóvel fora do município':'Faltam dados mínimos da ITN 03 para exportar')}" ${aptoItn?'':'disabled'}>⤓</button>`
      : (enviado
          ? `<button class="it-onr enviado" data-act="status" title="Consultar status na ONR">⟳</button>`
          : `<button class="it-onr" data-act="enviar" title="${pronto?'Enviar ao Mapa ONR':(foraMun?'Bloqueado: imóvel fora do município':'Faltam dados ONR para enviar')}" ${pronto?'':'disabled'}>➤</button>`);
    return `<div class="item${morto?' morto':''}${foraMun?' fora-mun':''}" data-id="${it.id}">
      ${corDot}
      <div class="info">
        <div class="nm">${escapeHtml(it.identificador||'(sem identificação)')} ${mortoBadge}${foraBadge}${exclBadge}${(function(){const n=incParse(it.inconsistencias).length;return n?`<span class="inc-badge" title="${n} inconsistência(s) — clique para ver/relatar" data-inc="${it.id}">⚠ ${n}</span>`:'';})()}</div>
        <div class="mt">${sub.join(' · ')||meta} ${onrBadge}</div>
      </div>
      ${tag}
      ${acaoBtn}
      <button class="it-edit" title="Editar dados">✎</button>
      <button class="del" title="Excluir">×</button>
    </div>`;
  }).join('');
  wrap.querySelectorAll('.item').forEach(el=>{
    const id = el.dataset.id;
    el.querySelector('.del').onclick = async (e)=>{ e.stopPropagation(); if(!(await swalConfirm('Excluir imóvel?','Esta ação não pode ser desfeita.','Excluir')))return; await post({acao:'excluir', id}); carregarLista(); if(modo==='overview') verTodos(); };
    el.querySelector('.it-edit').onclick = (e)=>{ e.stopPropagation(); abrirEdicao(id); };
    const onrB = el.querySelector('.it-onr');
    if(onrB) onrB.onclick = (e)=>{ e.stopPropagation();
      const act = onrB.dataset.act;
      if(act==='itn03') exportarItn03Individual(id);
      else if(act==='status') consultarStatusOnr(id);
      else enviarOnr(id);
    };
    const incB = el.querySelector('.inc-badge');
    if(incB) incB.onclick = (e)=>{ e.stopPropagation(); gerarRelatorioInconsistencias([parseInt(id,10)||0]); };
    el.oncontextmenu = (e)=>{ e.preventDefault(); selecionarDaLista(id); };
    el.onclick = (e)=>{ if(ctrlAtivo || e.ctrlKey || e.metaKey){ e.preventDefault(); selecionarDaLista(id); return; }
      if(String(el.dataset.id) && imoveisCache.find(x=>String(x.id)===String(id)&&String(x.itn03_exclusivo)==='1')){ abrirEdicao(id); return; } // exclusiva: sem mapa, abre edição
      carregarImovel(id); };
  });
  sincronizarListaSelecao();
}
async function carregarImovel(id){
  const res = await post({acao:'carregar', id});
  if(!res.ok || !res.geo.ok){ setStatus('err','Não foi possível carregar este registro.'); return; }
  const reg = res.registro;
  origemAtual = reg.origem || 'memorial';
  kmlRaw = origemAtual==='kml' ? (reg.memorial_descritivo||'') : '';
  document.getElementById('memorial').value = reg.memorial_descritivo || '';
  document.getElementById('identificador').value = reg.identificador||'';
  document.getElementById('numero_matricula').value = reg.numero_matricula||'';
  document.getElementById('proprietario').value = reg.proprietario||'';
  document.getElementById('cpf').value = reg.cpf||'';
  document.getElementById('tipo_imovel').value = reg.tipo_imovel||'';
  document.getElementById('tipo_identificador').value = reg.tipo_identificador||'nome';
  resetKmlZone();
  lastGeo = res.geo;
  desenhar(res.geo, reg.identificador);
  abrirCorPainel(id, reg.cor, reg.cor_opacidade);
  preencherOnr(reg); onrPreencherGeometria(res.geo); onrSetAtivo(id, reg.identificador);
  mostrarEncInfo(reg);
  document.getElementById('btn-save').disabled=false;
  setStatus('ok', `Carregado: ${reg.identificador} (${res.geo.num_vertices} vértices).`);
  abrirPainelMobile();
}

/* ---- edição inline (modal) ---- */
/* ===================== PROPRIETÁRIOS (vários, PF/PJ) + máscara CPF/CNPJ ===================== */
function soDigitos(v){ return (v||'').replace(/\D/g,''); }
function fmtCPF(d){
  d=d.slice(0,11); let o=d;
  if(d.length>3) o=d.slice(0,3)+'.'+d.slice(3);
  if(d.length>6) o=d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
  if(d.length>9) o=d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9,11);
  return o;
}
function fmtCNPJ(d){
  d=d.slice(0,14); let o=d;
  if(d.length>2) o=d.slice(0,2)+'.'+d.slice(2);
  if(d.length>5) o=d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5);
  if(d.length>8) o=d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8);
  if(d.length>12) o=d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8,12)+'-'+d.slice(12,14);
  return o;
}
function mascaraDoc(v){ const d=soDigitos(v).slice(0,14); return d.length<=11 ? fmtCPF(d) : fmtCNPJ(d); }
function validaCPF(v){
  const c=soDigitos(v); if(c.length!==11 || /^(\d)\1{10}$/.test(c)) return false;
  let s=0; for(let i=0;i<9;i++) s+=+c[i]*(10-i);
  let d1=11-(s%11); if(d1>=10) d1=0; if(d1!==+c[9]) return false;
  s=0; for(let i=0;i<10;i++) s+=+c[i]*(11-i);
  let d2=11-(s%11); if(d2>=10) d2=0; return d2===+c[10];
}
function validaCNPJ(v){
  const c=soDigitos(v); if(c.length!==14 || /^(\d)\1{13}$/.test(c)) return false;
  const calc=(base)=>{ let len=base.length,pos=len-7,sum=0;
    for(let i=len;i>=1;i--){ sum+=+base[len-i]*pos--; if(pos<2)pos=9; }
    const r=sum%11; return r<2?0:11-r; };
  if(calc(c.substring(0,12))!==+c[12]) return false;
  return calc(c.substring(0,13))===+c[13];
}
function docTipo(v){ const d=soDigitos(v); return d.length<=11 ? 'CPF' : 'CNPJ'; }
function docValido(v){ const d=soDigitos(v); if(d.length===11) return validaCPF(d); if(d.length===14) return validaCNPJ(d); return null; }

let edProps = [];
function edRenderProps(){
  const wrap=document.getElementById('ed-prop-list'); if(!wrap) return;
  if(!edProps.length) edProps=[{nome:'',doc:''}];
  wrap.innerHTML = edProps.map((p,i)=>`
    <div class="prop-row" data-i="${i}">
      <input class="prop-nome" data-i="${i}" type="text" placeholder="Nome / Razão social" value="${escapeHtml(p.nome||'')}">
      <div class="prop-doc-wrap">
        <input class="prop-doc" data-i="${i}" type="text" inputmode="numeric" maxlength="18" placeholder="CPF ou CNPJ" value="${escapeHtml(p.doc||'')}">
        <span class="prop-doc-badge" data-i="${i}"></span>
      </div>
      <button type="button" class="prop-del" data-i="${i}" title="Remover">×</button>
    </div>`).join('');
  wrap.querySelectorAll('.prop-nome').forEach(inp=> inp.addEventListener('input', e=>{ edProps[+e.target.dataset.i].nome = e.target.value; }));
  wrap.querySelectorAll('.prop-doc').forEach(inp=>{
    inp.addEventListener('input', e=>{
      const i=+e.target.dataset.i;
      e.target.value = mascaraDoc(e.target.value);
      edProps[i].doc = e.target.value;
      edAtualizaBadge(i);
    });
    edAtualizaBadge(+inp.dataset.i);
  });
  wrap.querySelectorAll('.prop-del').forEach(b=> b.addEventListener('click', ()=>{
    edProps.splice(+b.dataset.i,1); if(!edProps.length) edProps=[{nome:'',doc:''}]; edRenderProps();
  }));
}
function edAtualizaBadge(i){
  const wrap=document.getElementById('ed-prop-list'); if(!wrap) return;
  const inp=wrap.querySelector('.prop-doc[data-i="'+i+'"]');
  const badge=wrap.querySelector('.prop-doc-badge[data-i="'+i+'"]');
  if(!inp||!badge) return;
  const d=soDigitos(inp.value); const v=docValido(inp.value);
  inp.classList.remove('doc-ok','doc-bad');
  if(d.length===0){ badge.textContent=''; badge.className='prop-doc-badge'; return; }
  const tipo=docTipo(inp.value);
  if(v===true){ inp.classList.add('doc-ok'); badge.textContent=tipo+' ✓'; badge.className='prop-doc-badge ok'; }
  else if(v===false){ inp.classList.add('doc-bad'); badge.textContent=tipo+' inválido'; badge.className='prop-doc-badge bad'; }
  else { badge.textContent=tipo+'…'; badge.className='prop-doc-badge'; }
}
function edAddProp(){ edProps.push({nome:'',doc:''}); edRenderProps();
  const wrap=document.getElementById('ed-prop-list'); if(wrap){ const ins=wrap.querySelectorAll('.prop-nome'); if(ins.length) ins[ins.length-1].focus(); }
}

let edNovoItn03 = false;
function sincronizarVistaToggle(){
  document.querySelectorAll('#vista-toggle .vt-btn').forEach(b=>{
    b.classList.toggle('active', b.dataset.vista===vistaLista);
  });
  const acts=document.getElementById('itn03-actions'); if(acts) acts.style.display=(vistaLista==='itn03')?'flex':'none';
}
function novaMatriculaItn03(){
  edNovoItn03 = true;
  document.getElementById('ed-id').value = '';
  document.getElementById('ed-identificador').value = '';
  document.getElementById('ed-matricula').value = '';
  edProps=[{nome:'',doc:''}]; edRenderProps();
  document.getElementById('ed-tipo').value = '';
  const sit=document.getElementById('ed-situacao'); if(sit) sit.value='ativa';
  edSucList=[]; if(typeof edRenderSucChips==='function') edRenderSucChips();
  if(typeof edToggleEnc==='function') edToggleEnc();
  edPreencherOnr(null);
  edAnxId = 0;
  edAnxBusy=false; if(typeof edAnxBusyUI==='function') edAnxBusyUI('off'); edRenderAnexos([]);
  const onrAcc=document.querySelector('#modal-edit .ed-onr'); if(onrAcc) onrAcc.open=true;
  const t=document.getElementById('ed-titulo'); if(t) t.textContent='Nova matrícula — só ITN 03 (sem mapa)';
  document.getElementById('modal-edit').classList.add('show');
  edAtualizarDrop();
  document.getElementById('ed-identificador').focus();
}
async function abrirEdicao(id){
  const it = imoveisCache.find(x=>String(x.id)===String(id));
  if(!it) return;
  edNovoItn03 = false;
  const tt=document.getElementById('ed-titulo'); if(tt) tt.textContent='Editar dados do imóvel';
  document.getElementById('ed-id').value = it.id;
  document.getElementById('ed-identificador').value = it.identificador||'';
  document.getElementById('ed-matricula').value = it.numero_matricula||'';
  // proprietários (nomes e documentos separados por vírgula, pareados por posição)
  const nomes=(it.proprietario||'').split(',').map(s=>s.trim());
  const docs=(it.cpf||'').split(',').map(s=>s.trim());
  edProps=[]; const n=Math.max(nomes.length, docs.length);
  for(let k=0;k<n;k++){ const nm=nomes[k]||'', dc=docs[k]?mascaraDoc(docs[k]):''; if(nm||dc) edProps.push({nome:nm, doc:dc}); }
  if(!edProps.length) edProps=[{nome:'',doc:''}];
  edRenderProps();
  document.getElementById('ed-tipo').value = it.tipo_imovel||'';
  const ctxSel=document.getElementById('ed-contexto-rural'); if(ctxSel) ctxSel.value = (it.contexto_rural!=null?String(it.contexto_rural):'');
  if(typeof edToggleContextoRural==='function') edToggleContextoRural();
  let sitSel='ativa';
  if(it.motivo_situacao==='desmembramento') sitSel='desmembramento';
  else if(it.motivo_situacao==='georreferenciamento') sitSel='georreferenciamento';
  else if(it.situacao==='encerrada' || it.motivo_situacao==='unificacao') sitSel='unificacao';
  document.getElementById('ed-situacao').value = sitSel;
  edSucList = (it.matricula_sucessora||'').split(',').map(s=>s.trim()).filter(Boolean);
  edRenderSucChips();
  if(typeof edSucFeedback==='function') edSucFeedback('');
  edToggleEnc();
  edPreencherOnr(null);            // limpa enquanto busca o registro completo
  edAnxId = it.id;
  edAnxBusy=false; if(typeof edAnxBusyUI==='function') edAnxBusyUI('off'); edRenderAnexos(null);
  document.getElementById('modal-edit').classList.add('show');
  edAtualizarDrop();
  // os dados ONR completos não vêm na listagem enxuta: busca o registro inteiro
  try {
    const res = await post({acao:'carregar', id});
    if(res && res.ok && res.registro) edPreencherOnr(res.registro);
    if(res && res.ok) edRenderAnexos(res.anexos||[]);
  } catch(e){ edRenderAnexos([]); }
}
const EONR_PADRAO = {onr_nivel_publicidade:'3', onr_classificacao:'1'};
function edPreencherOnr(reg){
  document.querySelectorAll('[data-eonr]').forEach(el=>{
    const col = el.getAttribute('data-eonr');
    let v = (reg && reg[col]!=null && reg[col]!=='') ? reg[col] : '';
    if(v==='' && EONR_PADRAO[col]!==undefined) v = EONR_PADRAO[col];
    el.value = v;
  });
  edRenderQualificacao(reg ? reg.qualificacao_json : '');
}
/* Mostra (somente leitura) os titulares atuais extraídos dos registros/averbações. */
function edRenderQualificacao(json){
  const box=document.getElementById('ed-qual-list'); if(!box) return;
  let arr=[];
  try{ if(json){ arr = (typeof json==='string') ? JSON.parse(json) : json; } }catch(e){ arr=[]; }
  if(!Array.isArray(arr) || !arr.length){
    box.innerHTML='<span class="chips-vazio">Sem qualificação estruturada. Processe o PDF da matrícula para extrair os titulares atuais (registros/averbações).</span>';
    return;
  }
  const linha=(rot,val)=> val? `<div class="qual-row"><span class="qual-k">${escapeHtml(rot)}</span><span class="qual-v">${escapeHtml(String(val))}</span></div>`:'';
  box.innerHTML = arr.map(p=>{
    const nac = p.estrangeiro ? (p.nacionalidade||'estrangeiro(a)') : 'brasileiro(a)';
    const cd  = (p.condicao||'').toLowerCase()==='alienante' ? '<span class="qual-tag alien">alienante</span>' : '<span class="qual-tag adq">titular atual</span>';
    return `<div class="qual-card">
      <div class="qual-head"><b>${escapeHtml(p.nome||'(sem nome)')}</b> ${cd}</div>
      ${linha('CPF/CNPJ', p.cpf_cnpj?mascaraDoc(String(p.cpf_cnpj)):'')}
      ${linha('Relação jurídica', p.relacao_juridica)}
      ${linha('Início da relação', p.data_inicio)}
      ${linha('Percentual', p.percentual!==''&&p.percentual!=null?(p.percentual+(String(p.percentual).includes('%')?'':'%')):'')}
      ${linha('Nacionalidade', nac)}
      ${linha('Estado civil', p.estado_civil)}
      ${linha('Regime de bens', p.regime_bens)}
      ${linha('Profissão', p.profissao)}
      ${linha('RG', [p.rg,p.orgao_emissor].filter(Boolean).join(' '))}
      ${linha('Endereço', p.endereco)}
    </div>`;
  }).join('');
}
function edColetarOnr(){
  const o = {};
  document.querySelectorAll('[data-eonr]').forEach(el=>{ o[el.getAttribute('data-eonr')] = el.value.trim(); });
  return o;
}
let edSucList = [];
function edRenderSucChips(){
  const wrap=document.getElementById('ed-sucessora-chips'); if(!wrap) return;
  if(!edSucList.length){ wrap.innerHTML='<span class="chips-vazio">nenhuma matrícula adicionada</span>'; return; }
  wrap.innerHTML = edSucList.map((m,idx)=>`<span class="chip">${escapeHtml(m)}<button type="button" data-i="${idx}" class="chip-x" title="Remover">×</button></span>`).join('');
  wrap.querySelectorAll('.chip-x').forEach(b=>b.onclick=()=>{ edSucList.splice(+b.dataset.i,1); edRenderSucChips(); });
}
/* Interpreta a entrada de matrículas sucessoras:
   - "745-900"  => intervalo expandido (745, 746, …, 900)
   - "745;785"  => números específicos (separados por ; , ou nova linha)
   - combinações: "745-760;800;1001-1003"
   Segurança: "12.345-6" (dígito verificador, fim < início) é tratado como uma
   única matrícula, e intervalos absurdos (> MAX) são ignorados com aviso. */
function edParseSucessoras(raw){
  const MAX_INTERVALO = 5000;
  const out = [], avisos = [];
  const tokens = String(raw||'').split(/[;\n,]+/).map(s=>s.trim()).filter(Boolean);
  for(const tk of tokens){
    const r = tk.match(/^(\d+)\s*[-–—]\s*(\d+)$/);   // intervalo (aceita - – —)
    if(r){
      const a = parseInt(r[1],10), b = parseInt(r[2],10);
      if(b < a){ out.push(tk); continue; }           // ex.: 12345-6 => matrícula literal
      if(b - a > MAX_INTERVALO){ avisos.push(tk+` (intervalo acima de ${MAX_INTERVALO.toLocaleString('pt-BR')})`); continue; }
      for(let n=a; n<=b; n++) out.push(String(n));
    } else {
      out.push(tk);
    }
  }
  return { nums: out, avisos };
}
function edSucFeedback(txt, tipo){
  const el = document.getElementById('ed-suc-feedback');
  if(!el) return;
  if(!txt){ el.style.display='none'; el.textContent=''; return; }
  el.style.display=''; el.textContent = txt;
  el.style.color = tipo==='warn' ? 'var(--amber, #b45309)' : 'var(--faint)';
}
function edAddSuc(){
  const inp=document.getElementById('ed-sucessora-input'); if(!inp) return;
  const raw=inp.value.trim(); if(!raw){ return; }
  const { nums, avisos } = edParseSucessoras(raw);
  let add=0, dup=0;
  for(const v of nums){
    if(edSucList.some(x=>matKey(x)===matKey(v))){ dup++; continue; }
    edSucList.push(v); add++;
  }
  inp.value=''; inp.focus(); edRenderSucChips();
  let msg = add ? `${add} matrícula(s) adicionada(s)` : 'Nenhuma matrícula nova';
  if(dup) msg += `, ${dup} já estava(m) na lista`;
  if(avisos.length) msg += `. Ignorado(s): ${avisos.join('; ')}`;
  edSucFeedback(msg + '.', avisos.length ? 'warn' : 'ok');
}
function edToggleEnc(){
  const v = document.getElementById('ed-situacao').value;
  const ex = document.getElementById('ed-enc-extra');
  const hint = document.getElementById('ed-sit-hint');
  const lab = document.getElementById('ed-suc-label');
  if(ex) ex.style.display = (v==='ativa') ? 'none' : 'block';
  if(lab) lab.textContent = (v==='unificacao') ? 'Matrícula sucessora (em que foi unificada)'
                          : (v==='georreferenciamento') ? 'Nova matrícula (aberta pelo georreferenciamento)'
                          : 'Matrículas sucessoras (originadas do desmembramento)';
  if(hint){
    if(v==='unificacao') hint.innerHTML = 'A matrícula é <b>encerrada por completo</b> (apagada/"morta") e não gera sobreposição, pois deu origem à nova matrícula.';
    else if(v==='georreferenciamento') hint.innerHTML = 'A matrícula é <b>encerrada pelo georreferenciamento</b> e deu origem a uma nova matrícula. Ela fica <b>cinza</b> no mapa (inativa) e <b>não gera sobreposição</b>. Indique a nova matrícula aberta.';
    else if(v==='desmembramento') hint.innerHTML = 'A matrícula-mãe <b>permanece ativa</b>. Cada <b>trecho</b> coincidente com uma matrícula originada é destacado como "morto" e não gera sobreposição. Você pode adicionar <b>quantas matrículas</b> saíram dela.';
    else hint.textContent='';
  }
}
/* ===================== ANEXOS DO IMÓVEL ===================== */
function edToggleContextoRural(){
  const wrap=document.getElementById('ed-ctxr-wrap');
  const tipo=document.getElementById('ed-tipo');
  if(wrap) wrap.style.display = (tipo && tipo.value==='rural') ? 'flex' : 'none';
}
let edAnxId = 0;            // id do imóvel atualmente em edição (0 = ainda não salvo)
let edAnxBusy = false;
const ANX_ROTULO = {pdf_matricula:'PDF da matrícula', pdf_sigef:'PDF do SIGEF', kml:'KML', outro:'Anexo'};
function fmtBytes(n){ n=+n||0; if(n<1024) return n+' B'; if(n<1048576) return (n/1024).toFixed(0)+' KB'; return (n/1048576).toFixed(1)+' MB'; }
function fmtDataHora(s){ const m=String(s||'').match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : (s||''); }
function edAtualizarDrop(){
  const drop=document.getElementById('ed-drop'); if(!drop) return;
  if(edAnxId>0){ drop.classList.remove('disabled'); drop.style.opacity=''; drop.style.pointerEvents=''; }
  else { drop.style.opacity='.55'; drop.style.pointerEvents='none'; }
}
function edRenderAnexos(list){
  const box=document.getElementById('ed-anexos-list'); const cnt=document.getElementById('ed-anx-count');
  if(!box) return;
  if(list===null){ box.innerHTML='<div class="anx-empty">Carregando…</div>'; if(cnt) cnt.textContent=''; return; }
  if(!Array.isArray(list) || !list.length){
    box.innerHTML = '<div class="anx-empty">'+(edAnxId>0?'Nenhum anexo ainda. Use a área abaixo para enviar.':'Salve o imóvel para anexar arquivos.')+'</div>';
    if(cnt) cnt.textContent=''; return;
  }
  if(cnt) cnt.textContent = list.length+' arquivo'+(list.length>1?'s':'');
  const ehPdf = t => t==='pdf_matricula'||t==='pdf_sigef';
  box.innerHTML = list.map(a=>{
    const rot = ANX_ROTULO[a.tipo]||'Anexo';
    const sub = rot+' · '+fmtBytes(a.tamanho)+(a.criado_em?(' · '+fmtDataHora(a.criado_em)):'');
    const url = window.location.pathname+'?anexo='+a.id;
    const analisarBtn = ehPdf(a.tipo)
      ? `<button class="anx-btn" title="Analisar com IA e preencher campos faltantes" data-anx-ia="${a.id}"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v2m0 14v2m9-9h-2M5 12H3m14.7-6.7l-1.4 1.4M7.7 16.3l-1.4 1.4m12.4 0l-1.4-1.4M7.7 7.7L6.3 6.3"/><circle cx="12" cy="12" r="3.2"/></svg></button>` : '';
    const sigla = a.tipo==='kml'?'KML':(ehPdf(a.tipo)?'PDF':'•');
    return `<div class="anx-item">
      <div class="anx-ic ${a.tipo}">${sigla}</div>
      <div class="anx-meta"><div class="anx-nome" title="${escapeHtml(a.nome_original)}">${escapeHtml(a.nome_original)}</div><div class="anx-sub">${escapeHtml(sub)}</div></div>
      <div class="anx-acts">
        <a class="anx-btn" href="${url}" target="_blank" rel="noopener" title="Abrir / baixar"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></a>
        ${analisarBtn}
        <button class="anx-btn danger" title="Excluir anexo" data-anx-del="${a.id}"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
      </div>
    </div>`;
  }).join('');
  box.querySelectorAll('[data-anx-ia]').forEach(b=> b.onclick=()=> edAnalisarAnexo(+b.dataset.anxIa));
  box.querySelectorAll('[data-anx-del]').forEach(b=> b.onclick=()=> edExcluirAnexo(+b.dataset.anxDel));
}
/* aplica um registro completo ao formulário (após análise IA) — proprietários, tipo, ONR, qualificação */
function edAplicarRegistro(reg){
  if(!reg) return;
  if(reg.identificador && !document.getElementById('ed-identificador').value.trim()) document.getElementById('ed-identificador').value = reg.identificador;
  if(reg.tipo_imovel && !document.getElementById('ed-tipo').value) document.getElementById('ed-tipo').value = reg.tipo_imovel;
  // proprietários: se o editor estiver vazio, repovoa a partir do registro
  const vazio = !edProps.some(p=>(p.nome||'').trim()||(p.doc||'').trim());
  if(vazio && (reg.proprietario||reg.cpf)){
    const nomes=(reg.proprietario||'').split(',').map(s=>s.trim());
    const docs=(reg.cpf||'').split(',').map(s=>s.trim());
    edProps=[]; const n=Math.max(nomes.length,docs.length);
    for(let k=0;k<n;k++){ const nm=nomes[k]||'', dc=docs[k]?mascaraDoc(docs[k]):''; if(nm||dc) edProps.push({nome:nm,doc:dc}); }
    if(!edProps.length) edProps=[{nome:'',doc:''}];
    edRenderProps();
  }
  edPreencherOnrSeVazio(reg); // preenche apenas os inputs vazios; preserva o que o usuário digitou
  // contexto rural (ITN 03) detectado no PDF — preenche se ainda estiver vazio
  const ctxSel=document.getElementById('ed-contexto-rural');
  if(ctxSel && reg && reg.contexto_rural!=null && String(reg.contexto_rural)!=='' && !ctxSel.value){ ctxSel.value=String(reg.contexto_rural); }
  if(typeof edToggleContextoRural==='function') edToggleContextoRural();
  // situação atualizada por ciclo de vida (encerramento/desmembramento) detectado no PDF
  const selSit = document.getElementById('ed-situacao');
  if(selSit && reg){
    let sit='';
    if(reg.motivo_situacao==='desmembramento') sit='desmembramento';
    else if(reg.motivo_situacao==='georreferenciamento') sit='georreferenciamento';
    else if(reg.situacao==='encerrada' || reg.motivo_situacao==='unificacao') sit='unificacao';
    if(sit){
      selSit.value = sit;
      edSucList = (reg.matricula_sucessora||'').split(',').map(s=>s.trim()).filter(Boolean);
      if(typeof edRenderSucChips==='function') edRenderSucChips();
      if(typeof edToggleEnc==='function') edToggleEnc();
    }
  }
}
/* preenche os inputs ONR vazios a partir do registro (sem sobrescrever o que o usuário digitou) */
function edPreencherOnrSeVazio(reg){
  document.querySelectorAll('[data-eonr]').forEach(el=>{
    const col=el.getAttribute('data-eonr');
    const cur=(el.value==null)?'':String(el.value).trim();
    if(cur==='' && reg && reg[col]!=null && String(reg[col])!=='') el.value = reg[col];
  });
  if(typeof edRenderQualificacao==='function') edRenderQualificacao(reg ? reg.qualificacao_json : '');
}
/* feedback de processamento DENTRO do modal (visível mesmo com o modal aberto) */
function edAnxBusyUI(estado, msg){
  const b=document.getElementById('ed-anx-busy'); const drop=document.getElementById('ed-drop');
  if(drop) drop.classList.toggle('busy', estado==='work');
  if(!b) return;
  if(estado==='off'){ b.style.display='none'; b.className='anx-busy'; b.innerHTML=''; return; }
  b.className='anx-busy ' + (estado==='work'?'work':estado);
  const ic = estado==='work' ? '<span class="anx-spin" aria-hidden="true"></span>'
           : estado==='ok'   ? '<span style="color:#13693f;font-weight:700">✓</span>'
           : estado==='warn' ? '<span style="color:#8a7200;font-weight:700">!</span>'
           :                    '<span style="color:#a80f1e;font-weight:700">×</span>';
  b.innerHTML = ic + '<span>' + escapeHtml(msg||'') + '</span>';
  b.style.display = 'flex';
}
async function edEnviarArquivo(file){
  if(!file) return;
  if(edAnxId<=0){ edAnxBusyUI('warn','Salve o imóvel antes de anexar arquivos.'); swalToast('info','Salve o imóvel antes de anexar.'); return; }
  if(edAnxBusy){ swalToast('info','Aguarde — já há um arquivo sendo processado.'); const b=document.getElementById('ed-anx-busy'); if(b){ b.classList.remove('shake'); void b.offsetWidth; b.classList.add('shake'); } return; }
  const ehPdf = /\.pdf$/i.test(file.name) || file.type==='application/pdf';
  const analisar = ehPdf && document.getElementById('ed-anx-ia') && document.getElementById('ed-anx-ia').checked;
  edAnxBusy = true;
  edAnxBusyUI('work', analisar ? `Analisando “${file.name}” com IA… aguarde, isso pode levar alguns segundos.` : `Anexando “${file.name}”…`);
  setStatus('warn', analisar ? `Analisando “${escapeHtml(file.name)}” com IA…` : `Anexando “${escapeHtml(file.name)}”…`);
  try{
    const fd=new FormData();
    fd.append('acao', analisar?'anexo_analisar':'anexo_upload');
    fd.append('id', edAnxId);
    fd.append('file', file);
    const r = await fetch(window.location.pathname,{method:'POST',body:fd}).then(x=>x.json());
    if(!r || !r.ok){ const er=(r&&r.erro)||'Falha ao enviar o anexo.'; edAnxBusyUI('err', er); setStatus('err', er); return; }
    if(r.anexos) edRenderAnexos(r.anexos);
    if(analisar && r.registro){ edAplicarRegistro(r.registro); }
    edAnxBusyUI('ok', r.mensagem || (analisar?'Análise concluída.':'Anexo enviado.'));
    setStatus('ok', (r.mensagem||'Anexo enviado.')+(r.modelo?(' ('+r.modelo+')'):''));
    setTimeout(()=>{ if(!edAnxBusy) edAnxBusyUI('off'); }, 3500);
  }catch(e){ edAnxBusyUI('err','Falha na requisição de anexo.'); setStatus('err','Falha na requisição de anexo.'); }
  finally{ edAnxBusy=false; const fi=document.getElementById('ed-anx-file'); if(fi) fi.value=''; }
}
async function edAnalisarAnexo(aid){
  if(edAnxId<=0 || !aid) return;
  if(edAnxBusy){ swalToast('info','Aguarde — já há um arquivo sendo processado.'); return; }
  edAnxBusy=true;
  edAnxBusyUI('work','Analisando anexo com IA e preenchendo campos faltantes… aguarde.');
  setStatus('warn','Analisando anexo com IA…');
  try{
    const fd=new FormData(); fd.append('acao','anexo_analisar'); fd.append('id', edAnxId); fd.append('aid', aid);
    const r = await fetch(window.location.pathname,{method:'POST',body:fd}).then(x=>x.json());
    if(!r || !r.ok){ const er=(r&&r.erro)||'Falha ao analisar o anexo.'; edAnxBusyUI('err', er); setStatus('err', er); return; }
    if(r.anexos) edRenderAnexos(r.anexos);
    if(r.registro) edAplicarRegistro(r.registro);
    // ciclo de vida pode ter alterado esta matrícula e a anterior: atualiza lista/mapa
    if(r.ciclo_vida && (r.ciclo_vida.self || r.ciclo_vida.anterior)){
      if(typeof carregarLista==='function') carregarLista();
      if(typeof modo!=='undefined' && modo==='overview' && typeof verTodos==='function') verTodos();
    }
    edAnxBusyUI('ok', r.mensagem||'Análise concluída.');
    setStatus('ok', (r.mensagem||'Análise concluída.')+(r.modelo?(' ('+r.modelo+')'):''));
    setTimeout(()=>{ if(!edAnxBusy) edAnxBusyUI('off'); }, 3500);
  }catch(e){ edAnxBusyUI('err','Falha na requisição de análise.'); setStatus('err','Falha na requisição de análise.'); }
  finally{ edAnxBusy=false; }
}
async function edExcluirAnexo(aid){
  if(!aid) return;
  const ok = await Swal.fire({...swalTema(), title:'Excluir anexo?', text:'O arquivo será removido do servidor.', icon:'warning', showCancelButton:true, confirmButtonText:'Excluir', cancelButtonText:'Cancelar'}).then(x=>x.isConfirmed).catch(()=>false);
  if(!ok) return;
  try{
    const r = await post({acao:'anexo_excluir', aid, id:edAnxId});
    if(r && r.ok){ edRenderAnexos(r.anexos||[]); setStatus('ok','Anexo excluído.'); }
    else setStatus('err','Não foi possível excluir o anexo.');
  }catch(e){ setStatus('err','Falha ao excluir o anexo.'); }
}
function edInitDrop(){
  const drop=document.getElementById('ed-drop'); const fi=document.getElementById('ed-anx-file');
  if(!drop || !fi || drop.dataset.init) return; drop.dataset.init='1';
  drop.addEventListener('click', ()=>{ if(edAnxBusy){ swalToast('info','Aguarde — já há um arquivo sendo processado.'); return; } if(edAnxId>0) fi.click(); });
  drop.addEventListener('keydown', e=>{ if((e.key==='Enter'||e.key===' ')&&edAnxId>0&&!edAnxBusy){ e.preventDefault(); fi.click(); } });
  ['dragenter','dragover'].forEach(ev=> drop.addEventListener(ev, e=>{ e.preventDefault(); e.stopPropagation(); if(edAnxId>0&&!edAnxBusy) drop.classList.add('drag'); }));
  ['dragleave','dragend'].forEach(ev=> drop.addEventListener(ev, e=>{ e.preventDefault(); e.stopPropagation(); drop.classList.remove('drag'); }));
  drop.addEventListener('drop', e=>{ e.preventDefault(); e.stopPropagation(); drop.classList.remove('drag');
    const f=e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]; if(f) edEnviarArquivo(f); });
  fi.addEventListener('change', ()=>{ const f=fi.files && fi.files[0]; if(f) edEnviarArquivo(f); });
}

function fecharEdicao(){ edNovoItn03=false; const t=document.getElementById('ed-titulo'); if(t) t.textContent='Editar dados do imóvel'; document.getElementById('modal-edit').classList.remove('show'); }

/* ===================== EXPORTAÇÃO DE CARGA ITN 03 (ONR) ===================== */
function itn03Baixar(nome, conteudo){
  const blob = new Blob([conteudo], {type:'application/json;charset=utf-8'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = nome;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(()=>URL.revokeObjectURL(url), 4000);
}
function itn03Esc(s){ return String(s).replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function itn03MostrarAvisos(avisos, contexto){
  if(!avisos || !avisos.length) return;
  if(typeof Swal==='undefined'){
    alert('Carga ITN 03 gerada ('+contexto+').\n\nRevise antes de enviar ao ONR:\n\n'+avisos.map(a=>'• '+a).join('\n'));
    return;
  }
  const itens = avisos.map(a=>'<li>'+itn03Esc(a)+'</li>').join('');
  Swal.fire(Object.assign({
    icon:'warning',
    title:'Carga ITN 03 gerada ('+contexto+')',
    html:'<p style="margin:0 0 10px;font-size:13px">'
         + avisos.length + ' campo(s) obrigatório(s) usaram valores padrão — revise antes de enviar ao ONR:</p>'
         + '<div style="max-height:48vh;overflow:auto;text-align:left;border:1px solid rgba(128,128,128,.25);border-radius:8px;padding:10px 12px">'
         + '<ul style="margin:0;padding-left:18px;font-size:12.5px;line-height:1.6">' + itens + '</ul></div>',
    width: 640,
    confirmButtonText:'Entendi',
    confirmButtonColor:'#a80f1e',
    didOpen:(popup)=>{ const c = popup && popup.parentElement; if(c && c.classList.contains('swal2-container')) c.style.zIndex='100050'; }
  }, swalTema()));
}
async function exportarItn03Individual(idArg){
  const id = idArg || (document.getElementById('ed-id')||{}).value;
  if(!id){ setStatus('err','Selecione uma matrícula primeiro.'); return; }
  const btn = idArg ? null : document.getElementById('ed-itn03'); const txt = btn?btn.textContent:'';
  if(btn){ btn.disabled=true; btn.textContent='Gerando…'; }
  try{
    const res = await post({acao:'itn03_individual', id});
    if(!res.ok){
      setStatus('err', res.erro||'Falha ao gerar a carga ITN 03.');
      if(typeof Swal!=='undefined'){
        Swal.fire(Object.assign({icon:'info', title:'Ainda não dá para exportar', text:res.erro||'Falha ao gerar a carga ITN 03.',
          confirmButtonText:'Entendi', confirmButtonColor:'#a80f1e',
          didOpen:(p)=>{ const c=p&&p.parentElement; if(c&&c.classList.contains('swal2-container')) c.style.zIndex='100050'; }
        }, swalTema()));
      }
      return;
    }
    (res.arquivos||[]).forEach(f=>itn03Baixar(f.nome, f.conteudo));
    setStatus('ok','Carga ITN 03 da matrícula gerada.');
    itn03MostrarAvisos(res.avisos, 'matrícula');
  }catch(e){ setStatus('err','Erro: '+e.message); }
  finally{ if(btn){ btn.disabled=false; btn.textContent=txt; } }
}
async function exportarItn03Lote(escopo){
  escopo = (escopo==='exclusivas') ? 'exclusivas' : 'mapa';
  // visão "mapa": respeita o filtro por lista (;) da Visão geral; exclusivas: todas as aptas
  const busca = (escopo==='mapa') ? ((document.getElementById('ov-busca')||{}).value || '') : '';
  const ids = busca.indexOf(';')>=0 ? busca : '';
  const btn = (escopo==='exclusivas') ? document.getElementById('btn-itn03-export-excl') : document.getElementById('ov-itn03');
  const txt = btn?btn.textContent:'';
  if(btn){ btn.disabled=true; btn.textContent='Gerando…'; }
  try{
    const res = await post({acao:'itn03_lote', ids, escopo});
    if(!res.ok){
      setStatus('err', res.erro||'Falha ao gerar a carga ITN 03.');
      if(typeof Swal!=='undefined'){
        Swal.fire(Object.assign({icon:'info', title:'Nada para exportar', text:res.erro||'Falha ao gerar a carga ITN 03.',
          confirmButtonText:'Entendi', confirmButtonColor:'#a80f1e',
          didOpen:(p)=>{ const c=p&&p.parentElement; if(c&&c.classList.contains('swal2-container')) c.style.zIndex='100050'; }
        }, swalTema()));
      }
      return;
    }
    const arqs = res.arquivos||[];
    arqs.forEach(f=>itn03Baixar(f.nome, f.conteudo));
    const resumo = arqs.map(f=>f.n+' '+(f.tipo==='rural'?(f.n>1?'rurais':'rural'):(f.n>1?'urbanos':'urbano'))).join(' + ');
    const ctxLbl = escopo==='exclusivas' ? 'exclusivas ITN 03' : 'lote';
    const puladasMsg = res.puladas ? ' · '+res.puladas+(escopo==='exclusivas'?' incompleta(s) (ignorada(s))':' não pronto(s) p/ o Mapa ONR (ignorado(s))') : '';
    setStatus('ok','Carga ITN 03 ('+ctxLbl+') gerada: '+(res.total||0)+' imóvel(is) — '+resumo+(arqs.length>1?' (um arquivo por tipo)':'')+puladasMsg+'.');
    itn03MostrarAvisos(res.avisos, ctxLbl);
  }catch(e){ setStatus('err','Erro: '+e.message); }
  finally{ if(btn){ btn.disabled=false; btn.textContent=txt; } }
}
async function salvarEdicao(){
  const id = document.getElementById('ed-id').value;
  // georreferenciamento exige a indicação da nova matrícula aberta (antes de gravar qualquer coisa)
  if(document.getElementById('ed-situacao').value==='georreferenciamento' && !edSucList.length){
    setStatus('warn','Indique a nova matrícula aberta pelo georreferenciamento antes de salvar.');
    const inp=document.getElementById('ed-sucessora-input'); if(inp) inp.focus();
    return;
  }
  // proprietários: coleta, valida documentos e junta por vírgula (alinhados por posição)
  const props = edProps.map(p=>({nome:(p.nome||'').trim(), doc:(p.doc||'').trim()})).filter(p=>p.nome||p.doc);
  const invalidos = props.filter(p=>p.doc && docValido(p.doc)===false);
  if(invalidos.length){ setStatus('err','Documento inválido: '+invalidos.map(p=>p.doc).join(', ')+'. Corrija para salvar.'); return; }
  const proprietario = props.map(p=>p.nome).join(', ');
  const cpf = props.map(p=>p.doc).join(', ');
  if(edNovoItn03){
    // cadastro de matrícula EXCLUSIVA da ITN 03 (sem coordenadas/mapa)
    const r = await post(Object.assign({acao:'itn03_exclusiva_nova',
      identificador: document.getElementById('ed-identificador').value.trim(),
      numero_matricula: document.getElementById('ed-matricula').value.trim(),
      proprietario: proprietario, cpf: cpf,
      tipo_imovel: document.getElementById('ed-tipo').value
    }, edColetarOnr()));
    if(!r.ok){ setStatus('err', r.erro||'Falha ao cadastrar a matrícula.'); return; }
    edNovoItn03=false; fecharEdicao();
    vistaLista='itn03'; sincronizarVistaToggle();
    await carregarLista();
    setStatus('ok','Matrícula exclusiva ITN 03 cadastrada.');
    return;
  }
  const r = await post(Object.assign({acao:'atualizar_imovel', id,
    identificador: document.getElementById('ed-identificador').value.trim(),
    numero_matricula: document.getElementById('ed-matricula').value.trim(),
    proprietario: proprietario,
    cpf: cpf,
    tipo_imovel: document.getElementById('ed-tipo').value,
    contexto_rural: (document.getElementById('ed-contexto-rural')||{}).value || ''
  }, edColetarOnr()));
  if(!r.ok){ setStatus('err', r.erro||'Falha ao atualizar.'); return; }
  // salva a situação: ativa | encerrada(unificação/georreferenciamento) | desmembramento(parcial, mãe ativa)
  const sv = document.getElementById('ed-situacao').value;
  const situacao = (sv==='unificacao'||sv==='georreferenciamento') ? 'encerrada' : 'ativa';
  const motivo = (sv==='unificacao') ? 'unificacao'
               : (sv==='georreferenciamento') ? 'georreferenciamento'
               : (sv==='desmembramento' ? 'desmembramento' : '');
  await post({acao:'salvar_situacao', id,
    situacao: situacao,
    motivo_situacao: motivo,
    matricula_sucessora: edSucList.join(', ')
  });
  fecharEdicao(); carregarLista(); if(modo==='overview') verTodos(); setStatus('ok','Dados do imóvel atualizados.');
}

/* ===================== DADOS ONR (carga p/ Mapa do Registro de Imóveis) ===================== */
let imovelAtivoId = null;
function onrSetAtivo(id, rotulo){
  imovelAtivoId = id || null;
  const btn = document.getElementById('btn-onr-salvar');
  const hint = document.getElementById('onr-hint-active');
  if(btn) btn.disabled = !imovelAtivoId;
  if(hint) hint.textContent = imovelAtivoId ? ('— '+(rotulo||('#'+imovelAtivoId))) : '— grave/abra um imóvel';
}
function onrFmt(v,d){ return (v==null||v==='') ? '' : Number(v).toLocaleString('pt-BR',{minimumFractionDigits:d,maximumFractionDigits:d}); }
function onrPreencherGeometria(geo){
  if(!geo) return;
  const set=(id,v)=>{ const e=document.getElementById(id); if(e) e.value=v; };
  set('onr_area_ha', onrFmt(geo.area_ha,4));
  set('onr_area_m2', onrFmt((geo.area_ha||0)*10000,2));
  set('onr_perim_m', onrFmt(geo.perimetro_m,2));
  set('onr_perim_km', onrFmt((geo.perimetro_m||0)/1000,3));
}
const ONR_PADRAO = {onr_nivel_publicidade:'3', onr_classificacao:'1'};
function preencherOnr(reg){
  document.querySelectorAll('[data-onr]').forEach(el=>{
    const col = el.getAttribute('data-onr');
    let v = (reg && reg[col]!=null && reg[col]!=='') ? reg[col] : '';
    if(v==='' && ONR_PADRAO[col]!==undefined) v = ONR_PADRAO[col];
    el.value = v;
  });
  const cat = document.getElementById('onr_categoria');
  const tipo = document.getElementById('tipo_imovel');
  if(cat) cat.value = (reg && reg.tipo_imovel) ? reg.tipo_imovel : (tipo ? tipo.value : '');
}
function coletarOnr(){
  const o = {};
  document.querySelectorAll('[data-onr]').forEach(el=>{ o[el.getAttribute('data-onr')] = el.value.trim(); });
  return o;
}
async function salvarOnr(){
  if(!imovelAtivoId){ setStatus('err','Grave ou abra um imóvel antes de salvar os dados ONR.'); return; }
  const r = await post(Object.assign({acao:'salvar_onr', id:imovelAtivoId}, coletarOnr()));
  if(!r.ok){ setStatus('err', r.erro||'Falha ao salvar dados ONR.'); return; }
  setStatus('ok','Dados ONR salvos para este imóvel.');
  carregarLista();
}

/* ---- Envio à ONR ---- */
async function enviarOnr(id){
  if(!(await swalConfirm('Enviar à ONR?','Enviar este imóvel ao Mapa do Registro de Imóveis (ONR)?','Enviar'))) return;
  setStatus('warn','Enviando à ONR… (gerando shapefile e transmitindo)');
  const r = await post({acao:'enviar_onr', id});
  if(!r.ok){ setStatus('err', r.mensagem||'Falha no envio.'); carregarLista(); return; }
  setStatus('ok', r.mensagem + (r.importation_id?(' · ID: '+r.importation_id):''));
  carregarLista();
}
async function consultarStatusOnr(id){
  setStatus('warn','Consultando status na ONR…');
  const r = await post({acao:'status_onr', id});
  if(!r.ok){ setStatus('err', r.mensagem||'Falha ao consultar status.'); return; }
  setStatus('ok','Status ONR: '+r.status);
  carregarLista();
}
async function enviarTodosOnr(){
  const prontos = (imoveisCache||[]).filter(it=> String(it.onr_pronto)==='1' && String(it.onr_enviado)!=='1').length;
  if(prontos===0){ setStatus('warn','Nenhum imóvel pronto para envio (faltam dados ONR ou já enviados).'); return; }
  if(!(await swalConfirm('Enviar em lote?','Enviar '+prontos+' imóvel(is) pronto(s) à ONR?','Enviar todos'))) return;
  setStatus('warn','Enviando '+prontos+' imóvel(is) à ONR…');
  const r = await post({acao:'enviar_onr_lote'});
  if(!r.ok){ setStatus('err','Falha no envio em lote.'); return; }
  let msg = `Enviados ${r.enviados} de ${r.total}.`;
  if(r.falhas && r.falhas.length) msg += ' Falhas: '+r.falhas.join(' | ');
  setStatus((r.falhas && r.falhas.length) ? 'warn':'ok', msg);
  carregarLista();
}
/* ---- Configuração da API ONR ---- */
async function abrirConfigOnr(){
  const r = await post({acao:'onr_config_get'});
  document.getElementById('cfg-base-url').value = (r.ok? r.base_url : 'https://mapa.onr.org.br/');
  const tk = document.getElementById('cfg-token'); tk.value = '';
  tk.placeholder = (r.ok && r.configurado) ? ('Token salvo: '+r.token_mascara+' (em branco mantém)') : 'Cole aqui o Bearer Token';
  document.getElementById('modal-onr-config').classList.add('show');
}
function fecharConfigOnr(){ document.getElementById('modal-onr-config').classList.remove('show'); }
async function salvarConfigOnr(){
  const base_url = document.getElementById('cfg-base-url').value.trim();
  const token = document.getElementById('cfg-token').value.trim();
  const r = await post({acao:'onr_config_save', base_url, token});
  if(!r.ok){ setStatus('err', r.mensagem||'Falha ao salvar configuração.'); return; }
  fecharConfigOnr(); setStatus('ok','Configuração da API ONR salva.');
}

/* ===================== IA (GEMINI) — OCR de matrícula em PDF ===================== */
async function enviarPdfMatricula(file){
  if(!file) return;
  const mat = file.name.replace(/\.pdf$/i,'').trim();
  const lbl=document.getElementById('pdf-mat-label');
  if(lbl) lbl.innerHTML='Processando <b>'+escapeHtml(mat||'PDF')+'</b>…';
  setStatus('warn','Lendo o PDF com IA e identificando a matrícula…');
  try{
    const fd=new FormData();
    fd.append('acao','processar_pdf_matricula');
    fd.append('matricula', mat);
    fd.append('pdf', file);
    const r = await fetch(window.location.pathname, {method:'POST', body:fd}).then(x=>x.json());
    if(!r.ok){ setStatus('err', r.erro||'Falha ao processar o PDF.'); return; }
    setStatus('ok', r.mensagem + (r.modelo?(' ('+r.modelo+')'):''));
    await carregarLista();
    if(r.criado){
      // novo imóvel mapeado: atualiza a visão do mapa
      if(typeof modo!=='undefined' && modo==='overview') verTodos();
    } else if(typeof imovelAtivoId!=='undefined' && imovelAtivoId && String(imovelAtivoId)===String(r.id)){
      carregarImovel(r.id);
    }
  }catch(e){ setStatus('err','Falha na requisição de processamento.'); }
  finally{ if(lbl) lbl.innerHTML='Matrícula ou <b>SIGEF</b> em PDF — mapear via IA <span class="zone-multi">(1 ou vários)</span>'; }
}

/* Processa VÁRIOS PDFs em fila (sequencial — respeita o limite/ordem da IA). */
async function enviarLotePdfMatricula(fileList){
  const arr = Array.from(fileList||[]).filter(f=>/\.pdf$/i.test(f.name) || f.type==='application/pdf');
  if(!arr.length){ setStatus('err','Selecione um ou mais arquivos .pdf.'); return; }
  if(arr.length===1){ return enviarPdfMatricula(arr[0]); }   // 1 só: usa o fluxo individual

  const resultados=[];
  importProgressShow('Lendo matrículas com IA', arr.length);
  for(let i=0;i<arr.length;i++){
    const f=arr[i];
    const mat=f.name.replace(/\.pdf$/i,'').trim();
    importProgressUpdate(i, arr.length, mat||f.name);
    try{
      const fd=new FormData();
      fd.append('acao','processar_pdf_matricula');
      fd.append('matricula', mat);
      fd.append('pdf', f);
      const r = await fetch(window.location.pathname, {method:'POST', body:fd}).then(x=>x.json());
      if(!r || !r.ok){
        const erro=(r&&r.erro)||'falha';
        // sem a chave da IA configurada não adianta seguir o lote
        if(/Configure a chave da API|chave da API do Gemini/i.test(erro)){
          importProgressHide();
          setStatus('err','Configure a chave da API do Gemini antes de processar o lote.');
          await carregarLista();
          if(resultados.length) importResultadosModal('Importação de matrículas (PDF)', resultados);
          return;
        }
        resultados.push({nome:(mat||f.name), status:'erro', id:null, msg:erro, inconsistencias:[]});
        continue;
      }
      const status = r.criado ? 'criado' : 'duplicado';
      resultados.push({nome:(r.matricula||mat||f.name), status, id:r.id||null, msg:r.mensagem||'', inconsistencias:r.inconsistencias||[]});
    }catch(e){ resultados.push({nome:(mat||f.name), status:'erro', id:null, msg:'erro de requisição', inconsistencias:[]}); }
  }
  importProgressUpdate(arr.length, arr.length, '');
  importProgressHide();
  await carregarLista();
  if(typeof modo!=='undefined' && modo==='overview') verTodos();
  importResultadosModal('Importação de matrículas (PDF)', resultados);
  setStatus('ok', 'Importação de PDFs concluída.');
}
let gemModels=[], gemDefault='';
function gemRenderModels(){
  const wrap=document.getElementById('gem-models'); if(!wrap) return;
  if(!gemModels.length){ wrap.innerHTML='<span class="chips-vazio">nenhum modelo cadastrado</span>'; }
  else wrap.innerHTML = gemModels.map((m,i)=>`<span class="chip">${escapeHtml(m)}<button type="button" data-i="${i}" class="chip-x" title="Remover">×</button></span>`).join('');
  wrap.querySelectorAll('.chip-x').forEach(b=>b.onclick=()=>{ const m=gemModels[+b.dataset.i]; gemModels.splice(+b.dataset.i,1); if(gemDefault===m) gemDefault=gemModels[0]||''; gemRenderModels(); });
  const sel=document.getElementById('gem-default'); if(sel){
    sel.innerHTML = gemModels.map(m=>`<option value="${escapeHtml(m)}">${escapeHtml(m)}</option>`).join('');
    if(gemDefault) sel.value=gemDefault;
  }
}
function gemAddModel(){
  const inp=document.getElementById('gem-model-input'); if(!inp) return;
  const v=inp.value.trim(); if(!v) return;
  if(!gemModels.includes(v)) gemModels.push(v);
  if(!gemDefault) gemDefault=v;
  inp.value=''; inp.focus(); gemRenderModels();
}
async function abrirConfigGemini(){
  const r = await post({acao:'gemini_config_get'});
  gemModels = (r.ok && Array.isArray(r.models)) ? r.models.slice() : [];
  gemDefault = (r.ok && r.default_model) ? r.default_model : (gemModels[0]||'');
  const k=document.getElementById('gem-key'); k.value='';
  k.placeholder = (r.ok && r.configurado) ? ('Chave salva: '+r.key_mascara+' (em branco mantém)') : 'Cole a API key do Google AI Studio';
  gemRenderModels();
  document.getElementById('modal-gemini-config').classList.add('show');
}
function fecharConfigGemini(){ document.getElementById('modal-gemini-config').classList.remove('show'); }
async function salvarConfigGemini(){
  const sel=document.getElementById('gem-default');
  gemDefault = sel ? sel.value : gemDefault;
  const api_key=document.getElementById('gem-key').value.trim();
  const r = await post({acao:'gemini_config_save', api_key, models:JSON.stringify(gemModels), default_model:gemDefault});
  if(!r.ok){ setStatus('err', r.mensagem||'Falha ao salvar configuração de IA.'); return; }
  fecharConfigGemini(); setStatus('ok','Configuração de IA salva.');
}

function mostrarEncInfo(reg){
  const box=document.getElementById('enc-info');
  const tit=document.getElementById('enc-info-titulo');
  const corpo=document.getElementById('enc-info-corpo');
  if(!box) return;
  const ico=box.querySelector('.enc-ico');
  const lista=((reg&&reg.matricula_sucessora)||'').split(',').map(s=>s.trim()).filter(Boolean);
  const sucTxt = lista.map(m=>'<b>'+escapeHtml(m)+'</b>').join(', ');
  if(reg && reg.situacao==='encerrada'){
    box.classList.remove('desmembra');
    if(ico) ico.textContent='✝';
    tit.textContent='Matrícula encerrada por unificação';
    corpo.innerHTML = (sucTxt ? ('Esta matrícula foi <b>unificada</b> e deu origem à matrícula '+sucTxt+'.')
                              : 'Esta matrícula foi <b>unificada</b> em uma nova matrícula.')
      + '<br><span class="enc-mut">Imóvel "morto": não gera sobreposição no mapa.</span>';
    box.style.display='block';
  } else if(reg && reg.motivo_situacao==='desmembramento'){
    box.classList.add('desmembra');
    if(ico) ico.textContent='✂';
    tit.textContent='Desmembramento — trecho(s) destacado(s)';
    const plural = lista.length>1;
    corpo.innerHTML = (sucTxt ? ((plural?'Trechos desta matrícula originaram as matrículas ':'Um trecho desta matrícula originou a matrícula ')+sucTxt+'.')
                              : 'Trecho(s) desta matrícula foram desmembrados em novas matrículas.')
      + '<br><span class="enc-mut">A matrícula-mãe permanece <b>ativa</b>; apenas o(s) trecho(s) coincidente(s) ficam "mortos" e sem sobreposição.</span>';
    box.style.display='block';
  } else {
    box.classList.remove('desmembra');
    box.style.display='none';
  }
}

function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

/* ===================== LIMITE DO MUNICÍPIO (IBGE) ===================== */
let limiteLayer = null, limiteTurf = null, limiteNome = '';

function muniStatus(cls, msg){
  const el = document.getElementById('muni-status');
  if(!el) return;
  el.className = 'status' + (cls ? ' ' + cls : '');
  el.textContent = msg || '';
  el.style.display = msg ? 'block' : 'none';
}

function normTxt(s){ return (s||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim(); }
async function carregarMunicipios(uf, selecionarNome, autoMostrar){
  const sel = document.getElementById('muni-list');
  if(!sel) return;
  sel.innerHTML = '<option value="">Carregando…</option>';
  try{
    const r = await post({acao:'ibge_municipios', uf:uf});
    if(!r.ok){ sel.innerHTML = '<option value="">—</option>'; muniStatus('err', r.erro || 'Falha ao carregar municípios.'); return; }
    const ops = ['<option value="">Selecione o município…</option>'];
    (r.municipios||[]).forEach(m=> ops.push('<option value="'+m.id+'">'+escapeHtml(m.nome)+'</option>'));
    sel.innerHTML = ops.join('');
    muniStatus('', '');
    if(selecionarNome){
      const limpa = selecionarNome.replace(/\s*[-\/,]\s*[A-Za-z]{2}\s*$/, ''); // remove UF residual (ex.: "Zé Doca-MA")
      const alvo = normTxt(limpa);
      const opt = Array.from(sel.options).find(o=> o.value && normTxt(o.textContent)===alvo);
      if(opt){ sel.value = opt.value; if(autoMostrar) mostrarLimite(); }
      else { muniStatus('warn', 'Município da serventia ("'+selecionarNome+'") não encontrado na UF '+uf+'. Selecione manualmente.'); }
    }
  }catch(e){
    sel.innerHTML = '<option value="">—</option>';
    muniStatus('err', 'Erro de comunicação ao buscar municípios.');
  }
}

function limiteToTurf(gj){
  if(!gj) return null;
  if(gj.type==='FeatureCollection'){
    const feats = (gj.features||[]).filter(f=> f && f.geometry && (f.geometry.type==='Polygon'||f.geometry.type==='MultiPolygon'));
    if(!feats.length) return null;
    if(feats.length===1) return feats[0];
    const polys=[];
    feats.forEach(f=>{ if(f.geometry.type==='Polygon') polys.push(f.geometry.coordinates); else f.geometry.coordinates.forEach(c=> polys.push(c)); });
    return {type:'Feature', properties:{}, geometry:{type:'MultiPolygon', coordinates:polys}};
  }
  if(gj.type==='Feature') return gj;
  if(gj.type==='Polygon'||gj.type==='MultiPolygon') return {type:'Feature', properties:{}, geometry:gj};
  return null;
}

function ocultarLimite(){
  if(limiteLayer){ limiteLayer.setMap(null); limiteLayer=null; }
  limiteTurf=null; limiteNome='';
  limparFora();
  const b=document.getElementById('muni-badge'); if(b) b.style.display='none';
  const oc=document.getElementById('btn-muni-ocultar'); if(oc) oc.style.display='none';
}

async function mostrarLimite(){
  const sel = document.getElementById('muni-list');
  const id = sel ? sel.value : '';
  const nome = (sel && sel.selectedIndex>=0) ? sel.options[sel.selectedIndex].textContent : '';
  if(!id){ muniStatus('warn','Selecione um município.'); return; }
  muniStatus('', 'Carregando limite do IBGE…');
  const st=document.getElementById('muni-status'); if(st) st.style.display='block';
  try{
    const r = await post({acao:'ibge_malha', municipio:id});
    if(!r.ok || !r.geojson){ muniStatus('err', r.erro || 'Não foi possível obter o limite.'); return; }
    ocultarLimite();
    limiteLayer = new google.maps.Data({map:map});
    limiteLayer.addGeoJson(r.geojson);
    limiteLayer.setStyle({fillColor:'#2563eb', fillOpacity:0.06, strokeColor:'#2563eb', strokeOpacity:0.95, strokeWeight:2.5, clickable:false});
    limiteTurf = limiteToTurf(r.geojson);
    limiteNome = nome || 'município';
    const bounds = new google.maps.LatLngBounds();
    limiteLayer.forEach(f=> f.getGeometry().forEachLatLng(ll=> bounds.extend(ll)));
    if(!bounds.isEmpty() && modo!=='single') map.fitBounds(bounds, 30);
    const oc=document.getElementById('btn-muni-ocultar'); if(oc) oc.style.display='';
    muniStatus('ok', 'Limite de ' + limiteNome + ' carregado.');
    if(window.__ultimoGeo) verificarPertencimento(window.__ultimoGeo);
    verificarTodosPertencimento();   // varre todos os imóveis e bloqueia os que estão fora
  }catch(e){
    muniStatus('err', 'Erro ao carregar o limite municipal.');
  }
}

// ---- Detecção do município vizinho que "pega" parte do imóvel ----
let foraLayer=null, foraLabels=[];
function limparFora(){
  if(foraLayer){ foraLayer.setMap(null); foraLayer=null; }
  foraLabels.forEach(l=>{ try{ l.setMap(null); }catch(e){} }); foraLabels=[];
}
function normNomeMun(s){ return (s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim(); }

// Cache da malha estadual (features por município) e dos nomes, por código de UF (2 dígitos)
let _malhaUF={cod:'', feats:null}, _muniUF={cod:'', mapa:null};

async function carregarMalhaUF(ufCod){
  if(_malhaUF.cod===ufCod && _malhaUF.feats) return _malhaUF.feats;
  const r = await post({acao:'ibge_malha_uf', uf:ufCod});
  if(!r.ok || !r.geojson) return null;
  const fc = r.geojson;
  const feats = [];
  const lista = (fc.type==='FeatureCollection') ? (fc.features||[]) : (fc.type==='Feature' ? [fc] : []);
  for(const f of lista){
    if(!f.geometry) continue;
    const cod = String((f.properties&&(f.properties.codarea||f.properties.CD_MUN||f.properties.codigo))||'').replace(/\D/g,'');
    feats.push({cod, feat:f});
  }
  _malhaUF={cod:ufCod, feats};
  return feats;
}
async function carregarMunicipiosUF(ufCod){
  if(_muniUF.cod===ufCod && _muniUF.mapa) return _muniUF.mapa;
  const r = await post({acao:'ibge_municipios', uf:ufCod});
  const mapa = {};
  if(r.ok && Array.isArray(r.municipios)) r.municipios.forEach(m=>{ mapa[String(m.id)] = m.nome; });
  _muniUF={cod:ufCod, mapa};
  return mapa;
}
// Identifica o município que contém um ponto, via IBGE (sem Google Geocoding)
async function municipioDoPontoIBGE(lat,lng,ufCod){
  if(!ufCod) return null;
  let feats, mapa;
  try{ feats = await carregarMalhaUF(ufCod); }catch(e){ feats=null; }
  if(!feats || !feats.length) return null;
  try{ mapa = await carregarMunicipiosUF(ufCod); }catch(e){ mapa={}; }
  const pt = turf.point([lng,lat]);
  for(const it of feats){
    let dentro=false;
    try{ dentro = turf.booleanPointInPolygon(pt, it.feat); }catch(e){}
    if(dentro){
      const nome = (mapa && mapa[it.cod]) ? mapa[it.cod] : null;
      return { municipio: nome, codigo: it.cod };
    }
  }
  return null;
}
// Identifica e indica o(s) município(s) vizinho(s) na parte do imóvel que fica fora do limite
async function detectarVizinhos(prop){
  limparFora();
  let fora=null;
  try{ fora = turf.difference(prop, limiteTurf); }catch(e){ fora=null; }
  if(!fora || !fora.geometry){ return; }

  // destaca no mapa a parte que está fora do município
  try{
    foraLayer = new google.maps.Data({map:map});
    foraLayer.addGeoJson({type:'Feature', properties:{}, geometry:fora.geometry});
    foraLayer.setStyle({fillColor:'#f59e0b', fillOpacity:0.32, strokeColor:'#b45309', strokeOpacity:0.95, strokeWeight:2, clickable:false});
  }catch(e){}

  // separa os pedaços de "fora" (Polygon ou MultiPolygon) e pega 1 ponto em cada
  const pedacos=[];
  const g=fora.geometry;
  try{
    if(g.type==='Polygon') pedacos.push(turf.polygon(g.coordinates));
    else if(g.type==='MultiPolygon') g.coordinates.forEach(c=>pedacos.push(turf.polygon(c)));
  }catch(e){}

  // UF (2 dígitos) derivada do código do município selecionado (1ºs 2 dígitos do código IBGE)
  const muniSel=document.getElementById('muni-list');
  const muniId=(muniSel && muniSel.value)?String(muniSel.value).replace(/\D/g,''):'';
  const ufCod=muniId.length>=2 ? muniId.substring(0,2) : '';

  const nomes=[];
  for(const ped of pedacos){
    // ignora microslivers de borda (< 200 m²) que são só imprecisão da malha
    let areaM2=0; try{ areaM2=turf.area(ped); }catch(e){}
    if(areaM2 < 200) continue;
    let pt=null;
    try{ pt=turf.pointOnFeature(ped); }catch(e){ try{ pt=turf.centroid(ped); }catch(_){ pt=null; } }
    if(!pt) continue;
    const lng=pt.geometry.coordinates[0], lat=pt.geometry.coordinates[1];
    const info=await municipioDoPontoIBGE(lat,lng,ufCod);
    const nome=info && info.municipio ? info.municipio : null;
    if(nome && normNomeMun(nome)!==normNomeMun(limiteNome)){
      if(!nomes.some(n=>normNomeMun(n)===normNomeMun(nome))) nomes.push(nome);
    }
    // rótulo apontando o vizinho, posicionado na parte de fora (mesmo sem nome resolvido)
    try{
      const lb=new LabelOverlay(new google.maps.LatLng(lat,lng), '→ '+(nome||'município vizinho'), 'vizinho');
      foraLabels.push(lb);
    }catch(e){}
  }

  // atualiza o badge e o status com o(s) vizinho(s)
  const badge=document.getElementById('muni-badge');
  if(nomes.length){
    const txt='⚠ Imóvel CRUZA o limite de '+limiteNome+' — parte em '+nomes.join(', ');
    if(badge){ badge.className='muni-badge parcial'; badge.textContent=txt; badge.style.display='block'; }
    muniStatus('warn','Parte do imóvel está em: '+nomes.join(', ')+'. A área fora do município está destacada em laranja no mapa.');
  } else {
    muniStatus('warn','O imóvel cruza o limite de '+limiteNome+'. A parte de fora está destacada em laranja, mas não foi possível identificar o município vizinho (a parte fora pode estar em outro estado).');
  }
}

// Identifica o município real do imóvel quando está TOTALMENTE fora do limite, marca o
// alerta e bloqueia o envio ONR/ITN. Persiste em fora_municipio.
async function detectarMunicipioFora(prop){
  limparFora();
  const badge=document.getElementById('muni-badge');
  let pt=null;
  try{ pt=turf.pointOnFeature(prop); }catch(e){ try{ pt=turf.centroid(prop); }catch(_){ pt=null; } }
  const muniSel=document.getElementById('muni-list');
  const muniId=(muniSel && muniSel.value)?String(muniSel.value).replace(/\D/g,''):'';
  const ufCod=muniId.length>=2 ? muniId.substring(0,2) : '';
  let nome=null;
  if(pt && ufCod){
    const lng=pt.geometry.coordinates[0], lat=pt.geometry.coordinates[1];
    try{ const info=await municipioDoPontoIBGE(lat,lng,ufCod); nome=info && info.municipio ? info.municipio : null; }catch(e){}
    try{ foraLabels.push(new LabelOverlay(new google.maps.LatLng(lat,lng), '→ '+(nome||'fora do município'), 'vizinho')); }catch(e){}
  }
  const txt = nome
    ? ('✗ Imóvel FORA de '+limiteNome+' — está em '+nome)
    : ('✗ Imóvel FORA de '+limiteNome+' (município não identificado — pode ser de outro estado)');
  if(badge){ badge.className='muni-badge fora'; badge.textContent=txt; badge.style.display='block'; }
  muniStatus('err', txt+'. Não pertence ao município: envio ONR e carga ITN bloqueados.');
  marcarForaMunicipio(imovelAtivoId, nome || 'fora');
}

// Persiste (e bloqueia) o estado "fora do município". municipio vazio => limpa o bloqueio.
// Retorna true se houve mudança. Com silent=true não recarrega a lista (para uso em lote).
async function marcarForaMunicipio(id, municipio, silent){
  if(!id) return false;
  const it=(typeof imoveisCache!=='undefined') ? imoveisCache.find(x=>String(x.id)===String(id)) : null;
  const antes = it ? ((it.fora_municipio||'').toString().trim()) : null;
  const agora = (municipio||'').toString().trim();
  if(antes!==null && antes===agora) return false;   // sem mudança: evita repersistir/recarregar
  try{
    await post({acao:'marcar_fora_municipio', id, municipio: agora});
    if(it){ it.fora_municipio = agora; if(agora){ it.onr_pronto='0'; it.itn03_apto='0'; } }
    if(!silent && typeof carregarLista==='function') carregarLista();
    return true;
  }catch(e){ return false; }
}

// Varre TODOS os imóveis da visão geral contra o limite carregado: marca/bloqueia os que
// estão totalmente fora e libera os que estão dentro/cruzando. Roda ao carregar o limite.
async function verificarTodosPertencimento(){
  if(!limiteTurf || typeof itensOverview==='undefined' || !itensOverview || !itensOverview.length) return;
  const muniSel=document.getElementById('muni-list');
  const muniId=(muniSel && muniSel.value)?String(muniSel.value).replace(/\D/g,''):'';
  const ufCod=muniId.length>=2 ? muniId.substring(0,2) : '';
  let mudou=false;
  for(const it of itensOverview){
    if(!it.pts || it.pts.length<3) continue;
    try{
      const ring=it.pts.map(p=>[p[1],p[0]]);
      if(ring[0][0]!==ring[ring.length-1][0] || ring[0][1]!==ring[ring.length-1][1]) ring.push(ring[0]);
      const prop=turf.polygon([ring]);
      let intersects=false; try{ intersects=turf.booleanIntersects(prop, limiteTurf); }catch(e){}
      if(intersects){ if(await marcarForaMunicipio(it.id,'',true)) mudou=true; continue; } // dentro/cruza: não bloqueia
      // totalmente fora -> identifica o município (malha já fica em cache) e marca
      let nome=null;
      if(ufCod){
        try{ const pt=turf.pointOnFeature(prop); const info=await municipioDoPontoIBGE(pt.geometry.coordinates[1],pt.geometry.coordinates[0],ufCod); nome=info&&info.municipio?info.municipio:null; }catch(e){}
      }
      if(await marcarForaMunicipio(it.id, nome||'fora', true)) mudou=true;
    }catch(e){}
  }
  if(mudou){ if(typeof carregarLista==='function') carregarLista(); if(typeof modo!=='undefined' && modo==='overview' && typeof verTodos==='function') verTodos(); }
}

function verificarPertencimento(geo){
  window.__ultimoGeo = geo;
  const badge = document.getElementById('muni-badge');
  if(!limiteTurf || !geo || !geo.pts || geo.pts.length<3){ if(badge) badge.style.display='none'; limparFora(); return; }
  try{
    const ring = geo.pts.map(p=>[p[1], p[0]]); // turf usa [lng,lat]
    if(ring[0][0]!==ring[ring.length-1][0] || ring[0][1]!==ring[ring.length-1][1]) ring.push(ring[0]);
    const prop = turf.polygon([ring]);
    let within=false, intersects=false;
    try{ within = turf.booleanWithin(prop, limiteTurf); }catch(e){}
    try{ intersects = turf.booleanIntersects(prop, limiteTurf); }
    catch(e){ try{ intersects = turf.booleanPointInPolygon(turf.point([geo.centro_lng, geo.centro_lat]), limiteTurf); }catch(_){} }
    let cls, txt;
    if(within){ cls='dentro'; txt='✓ Imóvel DENTRO de '+limiteNome; limparFora(); marcarForaMunicipio(imovelAtivoId, ''); }
    else if(intersects){ cls='parcial'; txt='⚠ Imóvel CRUZA o limite de '+limiteNome+' (identificando vizinho…)'; marcarForaMunicipio(imovelAtivoId, ''); }
    else { cls='fora'; txt='✗ Imóvel FORA de '+limiteNome+' (identificando município…)'; limparFora(); }
    if(badge){ badge.className='muni-badge '+cls; badge.textContent=txt; badge.style.display='block'; }
    muniStatus(cls==='dentro'?'ok':(cls==='parcial'?'warn':'err'), txt);
    if(cls==='parcial') detectarVizinhos(prop);          // assíncrono: atualiza badge/rótulos ao concluir
    else if(cls==='fora') detectarMunicipioFora(prop);   // assíncrono: identifica o município real e bloqueia
  }catch(e){ if(badge) badge.style.display='none'; }
}

(async function(){
  const uf=document.getElementById('muni-uf');
  if(uf) uf.addEventListener('change', e=> carregarMunicipios(e.target.value));
  const bm=document.getElementById('btn-muni-mostrar'); if(bm) bm.addEventListener('click', mostrarLimite);
  const bo=document.getElementById('btn-muni-ocultar'); if(bo) bo.addEventListener('click', ()=>{ ocultarLimite(); muniStatus('', ''); });
  montarSeletorCorPainel();

  // Município padrão pela serventia (cadastro_serventia.cidade) — recarrega a cada atualização da página
  let cidade='', ufServ='MA';
  try{ const s = await post({acao:'serventia_municipio'}); if(s.ok){ cidade=s.cidade||''; ufServ=s.uf||'MA'; } }catch(e){}
  if(uf && ufServ) uf.value = ufServ;
  if(uf) await carregarMunicipios(uf.value, cidade, !!cidade); // pré-seleciona e mostra o limite do município padrão

  // Busca na lista
  const busca=document.getElementById('busca'), bclear=document.getElementById('busca-clear');
  if(busca){ busca.addEventListener('input', ()=>{ if(bclear) bclear.style.display = busca.value?'block':'none'; renderLista(); }); }
  if(bclear){ bclear.addEventListener('click', ()=>{ busca.value=''; bclear.style.display='none'; renderLista(); busca.focus(); }); }

  // Modal de edição
  const es=document.getElementById('ed-salvar'); if(es) es.addEventListener('click', salvarEdicao);
  const ei=document.getElementById('ed-itn03'); if(ei) ei.addEventListener('click', ()=>exportarItn03Individual());
  const ol=document.getElementById('ov-itn03'); if(ol) ol.addEventListener('click', ()=>exportarItn03Lote('mapa'));
  document.querySelectorAll('#vista-toggle .vt-btn').forEach(b=> b.addEventListener('click', ()=>{
    vistaLista = b.dataset.vista==='itn03' ? 'itn03' : 'mapa';
    sincronizarVistaToggle(); renderLista();
  }));
  const bNova=document.getElementById('btn-itn03-nova'); if(bNova) bNova.addEventListener('click', novaMatriculaItn03);
  const bExpExcl=document.getElementById('btn-itn03-export-excl'); if(bExpExcl) bExpExcl.addEventListener('click', ()=>exportarItn03Lote('exclusivas'));
  const esit=document.getElementById('ed-situacao'); if(esit) esit.addEventListener('change', edToggleEnc);
  const etipo=document.getElementById('ed-tipo'); if(etipo) etipo.addEventListener('change', edToggleContextoRural);
  const epadd=document.getElementById('ed-prop-add'); if(epadd) epadd.addEventListener('click', edAddProp);
  // máscara CPF/CNPJ no campo do formulário principal
  const cpfMain=document.getElementById('cpf');
  if(cpfMain){ cpfMain.setAttribute('inputmode','numeric'); cpfMain.setAttribute('maxlength','18');
    cpfMain.addEventListener('input', ()=>{ cpfMain.value = mascaraDoc(cpfMain.value); }); }
  const eadd=document.getElementById('ed-sucessora-add'); if(eadd) eadd.addEventListener('click', edAddSuc);
  const einp=document.getElementById('ed-sucessora-input'); if(einp) einp.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); edAddSuc(); } });
  const ec=document.getElementById('ed-cancelar'); if(ec) ec.addEventListener('click', fecharEdicao);
  const eov=document.getElementById('modal-edit'); if(eov) eov.addEventListener('click', e=>{ if(e.target===eov) fecharEdicao(); });
  edInitDrop();

  // Recolher painel / drawer mobile
  const tg=document.getElementById('btn-toggle-panel'); if(tg) tg.addEventListener('click', togglePainel);
  const fab=document.getElementById('fab-panel'); if(fab) fab.addEventListener('click', abrirPainelMobile);
  const back=document.getElementById('panel-backdrop'); if(back) back.addEventListener('click', fecharPainelMobile);

  // Dados ONR
  const bonr=document.getElementById('btn-onr-salvar'); if(bonr) bonr.addEventListener('click', salvarOnr);
  const tImo=document.getElementById('tipo_imovel'); const cat=document.getElementById('onr_categoria');
  if(tImo && cat){ tImo.addEventListener('change', ()=>{ cat.value = tImo.value || ''; }); }
  // Envio ONR (lote + configuração)
  const blote=document.getElementById('btn-onr-lote'); if(blote) blote.addEventListener('click', enviarTodosOnr);
  const bcfg=document.getElementById('btn-onr-config'); if(bcfg) bcfg.addEventListener('click', abrirConfigOnr);
  const cfgov=document.getElementById('modal-onr-config'); if(cfgov) cfgov.addEventListener('click', e=>{ if(e.target===cfgov) fecharConfigOnr(); });
  // IA (Gemini): zona de PDF de matrícula + configuração
  const pz=document.getElementById('pdf-mat-zone'); const pf=document.getElementById('pdf-mat-file');
  if(pz && pf){
    pz.onclick=()=>pf.click();
    pf.onchange=e=>{ const fs=e.target.files; if(fs && fs.length){ fs.length>1 ? enviarLotePdfMatricula(fs) : enviarPdfMatricula(fs[0]); } e.target.value=''; };
    ['dragover','dragenter'].forEach(ev=>pz.addEventListener(ev,e=>{e.preventDefault();pz.classList.add('drag');}));
    ['dragleave','drop'].forEach(ev=>pz.addEventListener(ev,e=>{e.preventDefault();pz.classList.remove('drag');}));
    pz.addEventListener('drop', e=>{ const fs=e.dataTransfer.files; if(fs && fs.length){ fs.length>1 ? enviarLotePdfMatricula(fs) : enviarPdfMatricula(fs[0]); } });
  }
  const bgem=document.getElementById('btn-gemini-config'); if(bgem) bgem.addEventListener('click', abrirConfigGemini);
  const gadd=document.getElementById('gem-model-add'); if(gadd) gadd.addEventListener('click', gemAddModel);
  const ginp=document.getElementById('gem-model-input'); if(ginp) ginp.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); gemAddModel(); } });
  const gov=document.getElementById('modal-gemini-config'); if(gov) gov.addEventListener('click', e=>{ if(e.target===gov) fecharConfigGemini(); });
  // Consulta de CEP (ViaCEP) no campo do formulário ONR
  const cepEl=document.getElementById('onr_cep');
  if(cepEl){
    cepEl.setAttribute('inputmode','numeric'); cepEl.setAttribute('maxlength','9');
    cepEl.addEventListener('input', ()=>{ const dg=cepEl.value.replace(/\D/g,'').slice(0,8); cepEl.value = dg.length>5 ? dg.slice(0,5)+'-'+dg.slice(5) : dg; });
    cepEl.addEventListener('blur', async ()=>{
      const dg=cepEl.value.replace(/\D/g,''); if(dg.length!==8) return;
      try{
        const r=await post({acao:'consultar_cep', cep:dg});
        if(!r.ok){ setStatus('warn','CEP não encontrado.'); return; }
        const setF=(id,val,force)=>{ const e=document.getElementById(id); if(e && val && (force || !e.value.trim())) e.value=val; };
        setF('onr_municipio', r.municipio, true);
        setF('onr_uf', r.uf, true);
        setF('onr_endereco', r.logradouro, false);
        setStatus('ok', 'CEP '+cepEl.value+' — '+(r.municipio||'')+'/'+(r.uf||''));
      }catch(_){}
    });
  }
})();

/* ---- recolher / drawer ---- */
function togglePainel(){ document.body.classList.toggle('panel-collapsed'); setTimeout(()=>{ if(window.google&&map) google.maps.event.trigger(map,'resize'); }, 320); }
function abrirPainelMobile(){ if(window.matchMedia('(max-width:880px)').matches) document.body.classList.add('panel-open'); }
function fecharPainelMobile(){ document.body.classList.remove('panel-open'); }

// ---- Painéis flutuantes arrastáveis (Visão geral / KML) pelo cabeçalho ----
function tornarArrastavel(painel, handle){
  if(!painel || !handle) return;
  let arrastando=false, ox=0, oy=0;
  function inicio(e){
    if(e.target.closest('button')) return;           // não arrasta ao clicar em botões do cabeçalho
    const ev = e.touches ? e.touches[0] : e;
    const rect = painel.getBoundingClientRect();
    const pp = painel.offsetParent ? painel.offsetParent.getBoundingClientRect() : {left:0, top:0};
    painel.style.left  = (rect.left - pp.left) + 'px'; // fixa em left/top preservando a posição atual
    painel.style.top   = (rect.top  - pp.top)  + 'px';
    painel.style.right = 'auto';
    ox = ev.clientX - rect.left;
    oy = ev.clientY - rect.top;
    arrastando = true;
    painel.classList.add('dragging');
    document.addEventListener('mousemove', mover);
    document.addEventListener('mouseup', fim);
    document.addEventListener('touchmove', mover, {passive:false});
    document.addEventListener('touchend', fim);
    e.preventDefault();
  }
  function mover(e){
    if(!arrastando) return;
    const ev = e.touches ? e.touches[0] : e;
    const pp = painel.offsetParent.getBoundingClientRect();
    let x = ev.clientX - pp.left - ox;
    let y = ev.clientY - pp.top  - oy;
    const maxX = Math.max(0, pp.width  - painel.offsetWidth);
    const maxY = Math.max(0, pp.height - painel.offsetHeight);
    x = Math.max(0, Math.min(x, maxX));
    y = Math.max(0, Math.min(y, maxY));
    painel.style.left = x + 'px';
    painel.style.top  = y + 'px';
    if(e.cancelable) e.preventDefault();
  }
  function fim(){
    arrastando = false;
    painel.classList.remove('dragging');
    document.removeEventListener('mousemove', mover);
    document.removeEventListener('mouseup', fim);
    document.removeEventListener('touchmove', mover);
    document.removeEventListener('touchend', fim);
  }
  handle.addEventListener('mousedown', inicio);
  handle.addEventListener('touchstart', inicio, {passive:false});
}
(function(){
  const ov = document.getElementById('overview-panel');
  if(ov){ tornarArrastavel(ov, ov.querySelector('.ovh')); }
  const kp = document.getElementById('kml-panel');
  if(kp){ tornarArrastavel(kp, kp.querySelector('.ovh')); }
})();
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Turf.js/6.5.0/turf.min.js"></script>
<script async src="https://maps.googleapis.com/maps/api/js?key=<?= GMAPS_KEY ?>&callback=initMap&loading=async"></script>
</body>
</html>
