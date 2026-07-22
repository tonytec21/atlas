<?php
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
/* =====================================================================
 *  index.php (Vertex)  —  Sistema Atlas
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

/* ---------- Diagnóstico do Static Maps: acesse vertex/index.php?diag_staticmap=1 ---------- */
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

/* ---------- Visão 3D própria (independe do Map Tiles API) ---------- */
/* Textura de satélite (proxy Static Maps, chave no servidor): ?m3d_tile=1&clat=&clng=&z= */
if (isset($_GET['m3d_tile'])) {
    $clat = (float)($_GET['clat'] ?? 0); $clng = (float)($_GET['clng'] ?? 0);
    $z = max(1, min(21, (int)($_GET['z'] ?? 17)));
    $url = 'https://maps.googleapis.com/maps/api/staticmap?center=' . $clat . ',' . $clng
         . '&zoom=' . $z . '&size=640x640&scale=2&maptype=satellite&format=jpg&key=' . GMAPS_STATIC_KEY;
    $err = '';
    $img = fetchImageBytes($url, $err);
    if ($img === false) { http_response_code(502); header('Content-Type: text/plain'); echo 'erro tile: ' . $err; exit; }
    header('Content-Type: image/jpeg'); header('Cache-Control: public, max-age=86400');
    echo $img; exit;
}
/* Elevações (proxy Elevation API): ?m3d_elev=1&pts=lat,lng|lat,lng|... -> {ok, elev:[...]} */
if (isset($_GET['m3d_elev'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $pts = (string)($_GET['pts'] ?? '');
    $locs = [];
    foreach (explode('|', $pts) as $p) { $p = trim($p); if ($p !== '' && strpos($p, ',') !== false) $locs[] = $p; }
    if (!$locs) { echo json_encode(['ok' => false, 'erro' => 'sem pontos']); exit; }
    $locs = array_slice($locs, 0, 512);
    $url = 'https://maps.googleapis.com/maps/api/elevation/json?locations=' . rawurlencode(implode('|', $locs)) . '&key=' . GMAPS_STATIC_KEY;
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true]);
        $raw = curl_exec($ch); curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $raw = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 12]]));
    }
    if ($raw === false || $raw === null || $raw === '') { echo json_encode(['ok' => false, 'erro' => 'sem resposta']); exit; }
    $j = json_decode($raw, true);
    if (!is_array($j) || ($j['status'] ?? '') !== 'OK') { echo json_encode(['ok' => false, 'erro' => ($j['status'] ?? 'falha'), 'detalhe' => ($j['error_message'] ?? '')]); exit; }
    $elev = array_map(function ($r) { return round((float)($r['elevation'] ?? 0), 2); }, $j['results']);
    echo json_encode(['ok' => true, 'elev' => $elev]); exit;
}

/* ---------- Download/visualização de anexo: vertex/index.php?anexo=<id>[&dl=1] ---------- */
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

/* ---------- Download/visualização de anexo da AUTOTUTELA: index.php?at_anexo=<id>[&dl=1] ---------- */
if (isset($_GET['at_anexo'])) {
    if (function_exists('ensureAutotutela')) { try { ensureAutotutela($conn); } catch (Throwable $e) {} }
    $aid = (int)$_GET['at_anexo'];
    $a = function_exists('atAnexoObter') ? atAnexoObter($conn, $aid) : null;
    if (!$a) { http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); echo 'Anexo não encontrado.'; exit; }
    $caminho = anexosDir() . '/' . $a['arquivo'];
    if (!is_file($caminho)) { http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); echo 'Arquivo ausente no servidor.'; exit; }
    $mime = $a['mime'] ?: 'application/octet-stream';
    $ext  = strtolower(pathinfo($a['arquivo'], PATHINFO_EXTENSION));
    if ($mime === 'application/octet-stream') { if ($ext === 'pdf') $mime = 'application/pdf'; elseif (in_array($ext, ['png','jpg','jpeg'], true)) $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext); }
    $inline = empty($_GET['dl']) && in_array($ext, ['pdf','png','jpg','jpeg'], true);
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
 * Extrai coordenadas GMS SEM rótulo (tabela SIGEF/planta: colunas Longitude/Latitude).
 * Uma coordenada é reconhecida pelo SINAL: ou tem "-" antes do grau (padrão SIGEF),
 * ou tem a LETRA de hemisfério depois (N/S/L/O/E/W). Ângulos/azimutes (positivos e
 * sem hemisfério) são ignorados — assim não se confunde "285°27'21" (azimute) com
 * coordenada. Classifica lat/lon pela letra (quando houver) ou pela grandeza (|grau|>=20 = lon).
 */
function extractGeoCoordinatesTabela($rawText) {
    $t = normalizeGeoText($rawText);
    $re = '/(-?\s*\d+)\s*°\s*(\d+)\s*\'\s*([\d.,]+)\s*"(?:\s*([NSLOW])e?(?![A-Za-z]))?/iu';
    preg_match_all($re, $t, $m, PREG_SET_ORDER);
    $lons = []; $lats = [];
    foreach ($m as $x) {
        $temMinus = (strpos($x[1], '-') !== false);
        $hem = isset($x[4]) ? strtoupper($x[4]) : '';
        if (!$temMinus && $hem === '') continue; // sem sinal nem hemisfério = azimute/ângulo -> ignora
        $val = dmsToDecimal($x[1], $x[2], $x[3]);
        if ($hem === 'S' || $hem === 'O' || $hem === 'W')      $val = -abs($val);
        elseif ($hem === 'N' || $hem === 'L' || $hem === 'E')  $val =  abs($val);
        $deg = abs((float) preg_replace('/[^\d]/', '', $x[1]));
        $ehLon = in_array($hem, ['L', 'O', 'E', 'W'], true) ? true
               : (in_array($hem, ['N', 'S'], true) ? false : ($deg >= 20));
        if ($ehLon) $lons[] = $val; else $lats[] = $val;
    }
    $n = min(count($lons), count($lats));
    $pts = [];
    for ($i = 0; $i < $n; $i++) {
        $lat = $lats[$i]; $lng = $lons[$i];
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) $pts[] = [$lat, $lng];
    }
    return ['pts' => $pts, 'lon_count' => count($lons), 'lat_count' => count($lats), 'invalidos' => []];
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

/** Extrai todos os valores GMS rotulados por "long..." ou "lat...".
 *  Respeita o hemisfério indicado pela LETRA após o valor (S/O/W = negativo,
 *  N/L/E = positivo) — memoriais SIGEF/georref. costumam usar "…\" S" / "…\" W"
 *  em vez do sinal "-" antes do grau. */
function extractByLabel($text, $label) {
    $re = '/' . $label . '(?:itude)?\s*[:.]?\s*(-?\s*\d+)\s*°\s*(\d+)\s*\'\s*([\d.,]+)\s*"(?:\s*([NSLOW])e?(?![A-Za-z]))?/iu';
    preg_match_all($re, $text, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $x) {
        $val = dmsToDecimal($x[1], $x[2], $x[3]);
        $hem = isset($x[4]) ? strtoupper($x[4]) : '';
        if ($hem === 'S' || $hem === 'O' || $hem === 'W')      $val = -abs($val); // Sul / Oeste
        elseif ($hem === 'N' || $hem === 'L' || $hem === 'E')  $val =  abs($val); // Norte / Leste
        $min = (float) $x[2];
        $sec = (float) str_replace(',', '.', (string) $x[3]);
        $ok  = ($min < 60 && $sec < 60); // minutos/segundos válidos (>=60 = erro de digitação)
        $out[] = ['val' => $val, 'ok' => $ok];
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
    $pts = []; $invalidos = [];
    for ($i = 0; $i < $n; $i++) {
        $lat = $lats[$i]['val'];
        $lng = $lons[$i]['val'];
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            $pts[] = [$lat, $lng];
            // vértice com minuto/segundo >= 60 no doc (ex.: 44°28'80,80") = erro de digitação
            if (!$lats[$i]['ok'] || !$lons[$i]['ok']) $invalidos[] = count($pts) - 1;
        }
    }
    return ['pts' => $pts, 'lon_count' => count($lons), 'lat_count' => count($lats), 'invalidos' => $invalidos];
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
    return ['pts' => $pts, 'utm' => $pares, 'e_count' => $ne, 'n_count' => $nn];
}

/**
 * Detecta segmentos de "azimute + distância" (memoriais antigos por rumos/distâncias).
 * Não georreferencia (faltam coordenadas de âncora); serve para identificar o tipo.
 */
function extractTraverseLegs($text) {
    $t = normalizeGeoText($text);
    $re = '/azimute\s*(?:de)?\s*(\d+)\s*°\s*(?:(\d+)\s*\'\s*(?:([\d.,]+)\s*"?)?)?[^0-9]{0,40}?dist[âa]ncia\s*(?:de)?\s*([\d.,]+)\s*m/isu';
    preg_match_all($re, $t, $m, PREG_SET_ORDER);
    $legs = [];
    foreach ($m as $x) {
        $legs[] = ['az' => dmsToDecimal($x[1], $x[2] ?? '', $x[3] ?? ''), 'dist' => brNumero($x[4])];
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
function reconcileTraverse(array $pts, array $legs, $zone = 23, $south = true, $tol = 5.0, array $forcar = []) {
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

    // vértices sabidamente inválidos (minuto/segundo >= 60): NÃO servem de âncora e SEMPRE reconstruídos
    $forcarSet = [];
    foreach ($forcar as $fi) { if ($fi >= 0 && $fi < $v) $forcarSet[$fi] = true; }

    // âncora robusta: mediana de (escrito - relativo), excluindo os vértices inválidos
    $dN = []; $dE = [];
    for ($i = 0; $i < $v; $i++) {
        if (isset($forcarSet[$i])) continue;
        $dN[] = $U[$i][0] - $rel[$i][0]; $dE[] = $U[$i][1] - $rel[$i][1];
    }
    if (count($dN) < 2) { // sem âncora confiável: usa todos (comportamento antigo)
        $dN = []; $dE = [];
        for ($i = 0; $i < $v; $i++) { $dN[] = $U[$i][0] - $rel[$i][0]; $dE[] = $U[$i][1] - $rel[$i][1]; }
    }
    $offN = medianaFloat($dN); $offE = medianaFloat($dE);

    // correção híbrida: mantém o vértice escrito quando coerente; senão reconstrói
    $corrig = []; $novoU = []; $inliers = 0;
    for ($i = 0; $i < $v; $i++) {
        $rN = $rel[$i][0] + $offN; $rE = $rel[$i][1] + $offE;
        $dev = hypot($U[$i][0] - $rN, $U[$i][1] - $rE);
        if (!isset($forcarSet[$i]) && $dev <= $tol) { $novoU[] = $U[$i]; $inliers++; }
        else { $novoU[] = [$rN, $rE]; $corrig[] = $i + 1; }
    }

    // aceita se há correções e a base coerente é a maioria dos vértices VÁLIDOS
    $validos = $v - count($forcarSet);
    $minInliers = max(2, (int)ceil($validos * 0.6));
    if (empty($corrig) || $inliers < $minInliers) {
        return ['pts' => $pts, 'corrigidos' => [], 'usou' => false];
    }

    $novoPts = [];
    foreach ($novoU as $u) { $g = utmToGeo($u[1], $u[0], $zone, $south); $novoPts[] = [$g[0], $g[1]]; }
    return ['pts' => $novoPts, 'corrigidos' => $corrig, 'usou' => true];
}

/** Extrai a ÁREA declarada no documento e devolve em m². Ex.: "Área: 246,8798 m²" / "área de 12,5 ha". */
function extractDeclaredArea($text) {
    $t = normalizeGeoText($text);
    if (!preg_match('/[áa]rea[\s:]*(?:de\s*)?([\d.,]+)\s*(m²|m2|ha|hectares?)/iu', $t, $mm)) return null;
    $num = brNumero($mm[1]);
    $un = strtolower($mm[2]);
    if (strpos($un, 'h') === 0) $num *= 10000.0; // ha/hectare -> m²
    return ($num > 0) ? $num : null;
}

/** Lados (azimute+distância) de TABELAS "PARA | AZIMUTE | DISTÂNCIA": o azimute vem SEM a palavra
 *  "azimute" e SEM letra de hemisfério (o lookahead exclui coordenadas), seguido da distância.
 *  Ex.: '... 44°28'19.7"W  P2  285°,27' 21,60"  4,50' -> az 285°27'21.60", dist 4,50. */
function extractTraverseLegsTabela($text) {
    $t = normalizeGeoText($text);
    $re = '/(\d{1,3})\s*°\s*,?\s*(\d{1,2})\s*\'\s*([\d.,]+)\s*"(?!\s*[NSLOEW])[^0-9]{0,8}?([\d.,]+)/iu';
    preg_match_all($re, $t, $m, PREG_SET_ORDER);
    $legs = [];
    foreach ($m as $x) {
        $az = dmsToDecimal($x[1], $x[2], $x[3]);
        $dist = brNumero($x[4]);
        if ($az >= 0 && $az <= 360 && $dist > 0) $legs[] = ['az' => $az, 'dist' => $dist];
    }
    return $legs;
}

/** Reconstrói o polígono INTEIRO pela forma do caminhamento (azimute+distância), posicionando-o
 *  pela mediana das coordenadas escritas (a posição vem das coordenadas; a forma, do traçado). */
function traverseAnchoredPolygon(array $pts, array $legs, $zone = 23, $south = true) {
    $v = count($pts); $nl = count($legs);
    if ($v < 3 || ($nl !== $v && $nl !== $v - 1)) return null;
    $U = [];
    foreach ($pts as $p) { $u = geoToUTM($p[0], $p[1], $zone); $U[] = [$u[1], $u[0]]; } // [N,E]
    $rel = [[0.0, 0.0]];
    for ($i = 0; $i < $v - 1; $i++) {
        $az = deg2rad($legs[$i]['az']); $d = (float)$legs[$i]['dist'];
        $rel[] = [$rel[$i][0] + $d * cos($az), $rel[$i][1] + $d * sin($az)];
    }
    $dN = []; $dE = [];
    for ($i = 0; $i < $v; $i++) { $dN[] = $U[$i][0] - $rel[$i][0]; $dE[] = $U[$i][1] - $rel[$i][1]; }
    $offN = medianaFloat($dN); $offE = medianaFloat($dE);
    $out = [];
    for ($i = 0; $i < $v; $i++) {
        $g = utmToGeo($rel[$i][1] + $offE, $rel[$i][0] + $offN, $zone, $south);
        $out[] = [$g[0], $g[1]];
    }
    return $out;
}

/** Converte um token numérico de coordenada UTM respeitando o padrão BR.
 *  Diferente de brNumero(): aqui "9.222.799" é milhar (9222799), não 9222,799 —
 *  o que evita confundir CPF/nº de processo com coordenada. */
function numeroUTMToken($raw) {
    $raw = trim((string)$raw);
    if (strpos($raw, ',') !== false) {                         // vírgula = decimal; pontos = milhar
        $p = strrpos($raw, ',');
        $int = preg_replace('/\D/', '', substr($raw, 0, $p));
        $dec = preg_replace('/\D/', '', substr($raw, $p + 1));
        return (float)(($int === '' ? '0' : $int) . '.' . ($dec === '' ? '0' : $dec));
    }
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $raw)) {           // só separador de milhar
        return (float) preg_replace('/\D/', '', $raw);
    }
    if (substr_count($raw, '.') === 1) {                       // ponto decimal (OCR/EN)
        $p = strrpos($raw, '.');
        $int = preg_replace('/\D/', '', substr($raw, 0, $p));
        $dec = preg_replace('/\D/', '', substr($raw, $p + 1));
        return (float)(($int === '' ? '0' : $int) . '.' . ($dec === '' ? '0' : $dec));
    }
    return (float) preg_replace('/\D/', '', $raw);
}

/**
 * Extrai vértices de TABELAS UTM cujas colunas são rotuladas só no CABEÇALHO
 * (ex.: plantas de levantamento topográfico: "De | Para | Coord. N(Y) | Coord. E(X) | Distância").
 * As linhas trazem apenas os números, sem as letras N/E — então a classificação é pela GRANDEZA:
 * northing (7-8 dígitos) e easting (6 dígitos), pareados localmente (em qualquer ordem).
 * Usa "shapes" estritos para não confundir CPF (662.695.803), CEP, datas ou nº de processo.
 */
function extractUTMTabelaSimples($rawText, $zone = 23, $south = true) {
    $vazio = ['pts' => [], 'utm' => [], 'e_count' => 0, 'n_count' => 0];
    $t = normalizeGeoText($rawText);
    $shapeN = '\d{1,2}\.\d{3}\.\d{3}(?:,\d+)?|\d{7,8}(?:[.,]\d+)?';
    $shapeE = '\d{3}\.\d{3}(?:,\d+)?|\d{6}(?:[.,]\d+)?';
    $re = '/(?<![\d.,])(' . $shapeN . '|' . $shapeE . ')(?![\d.,]*\d)/u';
    preg_match_all($re, $t, $m, PREG_SET_ORDER);

    $tokens = [];
    foreach ($m as $x) {
        $raw = $x[1];
        if ($raw[0] === '0') continue;                       // coordenada UTM não começa com zero
        $val = numeroUTMToken($raw);
        $ip  = (int) floor(abs($val));
        if ($ip >= 1000000 && $ip <= 10000000)      $tokens[] = ['N', $val];
        elseif ($ip >= 100000 && $ip <= 999999)     $tokens[] = ['E', $val];
    }
    // pareamento local: 1 N + 1 E consecutivos (qualquer ordem) = 1 vértice
    $pares = []; $pN = null; $pE = null; $nn = 0; $ne = 0;
    foreach ($tokens as $tk) {
        if ($tk[0] === 'N') { $pN = $tk[1]; $nn++; } else { $pE = $tk[1]; $ne++; }
        if ($pN !== null && $pE !== null) { $pares[] = [$pN, $pE]; $pN = null; $pE = null; }
    }
    if (count($pares) < 3) return $vazio;

    // remove vértice de fechamento repetido (último == primeiro)
    $k = count($pares);
    if ($k > 3 && abs($pares[0][0] - $pares[$k-1][0]) < 0.05 && abs($pares[0][1] - $pares[$k-1][1]) < 0.05) {
        array_pop($pares);
    }
    // sanidade: vértices de um mesmo imóvel devem estar próximos (< 100 km)
    $maxd = 0.0;
    foreach ($pares as $a) foreach ($pares as $b) { $d = hypot($a[0]-$b[0], $a[1]-$b[1]); if ($d > $maxd) $maxd = $d; }
    if ($maxd > 100000) return $vazio;

    $pts = [];
    foreach ($pares as $p) {
        $g = utmToGeo($p[1], $p[0], $zone, $south);           // (east, north)
        if ($g[0] < -34 || $g[0] > 6 || $g[1] < -74 || $g[1] > -34) return $vazio; // fora do Brasil
        $pts[] = [$g[0], $g[1]];
    }
    return ['pts' => $pts, 'utm' => $pares, 'e_count' => $ne, 'n_count' => $nn];
}

/** Monta o pacote a partir do texto de um memorial descritivo (GMS). */
/** Lados no formato SIGEF/INCRA narrativo: "135°20' e 1.311,26 m até o vértice ...". */
function extractTraverseLegsSigef($text) {
    $t = normalizeGeoText($text);
    $re = '/(\d{1,3})\s*°\s*(\d{1,2})?\s*\'?\s*e\s+([\d.]+(?:,\d+)?)\s*m\s+at[ée]/isu';
    preg_match_all($re, $t, $m, PREG_SET_ORDER);
    $legs = [];
    foreach ($m as $x) {
        $az = dmsToDecimal($x[1], $x[2] ?? '', '');
        $dist = brNumero($x[3]);
        if ($az >= 0 && $az <= 360 && $dist > 0) $legs[] = ['az' => $az, 'dist' => $dist];
    }
    return $legs;
}

/** Extrator TOLERANTE de lados "azimute(°min'seg) [sep] distância [m] até ..." — cobre memoriais
 *  antigos/OCR sujo: segundos no azimute, "97°67'" (min>60 vira grau), "260.023'" (ponto no lugar
 *  do °), separadores variados (e / ; / vírgula), distância com pontos/vírgulas/espaços trocados,
 *  e término em "até o M-13" ou "até o vértice ...". */
function extractTraverseLegsMarco($text) {
    $t = normalizeGeoText($text);
    $re = '/(\d{1,3})\s*[°.]\s*(\d{1,3})\s*\'\s*(\d{0,2})\s*"?\s*[^0-9]{0,28}?([\d][\d.,\s]*?)\s*m?\s*,?\s*at[ée]\b/isu';
    preg_match_all($re, $t, $m, PREG_SET_ORDER);
    $legs = [];
    foreach ($m as $x) {
        $az = dmsToDecimal($x[1], $x[2] ?? '', $x[3] ?? '');       // min>60 já entra como grau
        $dist = brNumero(preg_replace('/\s+/', '', $x[4]));        // remove espaços do OCR
        if ($az >= 0 && $az <= 360 && $dist > 0 && $dist < 1000000) $legs[] = ['az' => $az, 'dist' => $dist];
    }
    return $legs;
}

/** Âncora em UTM: "coordenadas UTM 557341-20 e 9553121-35N" (o '-' é o decimal do OCR).
 *  Classifica por grandeza (easting ~6 díg., northing 7-8) e devolve [lat,lng] na zona informada. */
function extractAnchorUTM($text, $zone = 23, $south = true) {
    $t = normalizeGeoText($text);
    if (!preg_match('/coordenadas?\s+UTM\s+([0-9][0-9.\- ]*?)\s+e\s+([0-9][0-9.\- ]*?)\s*N/isu', $t, $mm)) return null;
    $num = function ($raw) {
        $raw = preg_replace('/\s+/', '', $raw);
        $raw = str_replace('-', ',', $raw);       // '-' do OCR = separador decimal
        return brNumero($raw);
    };
    $a = $num($mm[1]); $b = $num($mm[2]);
    // 'a' deve ser easting (~6 díg.), 'b' northing (7-8 díg.); corrige se vierem trocados
    $E = $a; $N = $b;
    if (!($E >= 100000 && $E < 1000000 && $N >= 1000000 && $N <= 10000000)) {
        if ($b >= 100000 && $b < 1000000 && $a >= 1000000 && $a <= 10000000) { $E = $b; $N = $a; }
        else return null;
    }
    $g = utmToGeo($E, $N, $zone, $south);
    if ($g[0] < -34 || $g[0] > 6 || $g[1] < -74 || $g[1] > -34) return null;
    return [$g[0], $g[1]];
}

/** Reconstrói o polígono a partir de UM vértice-âncora + os lados (azimute/distância).
 *  Se o traçado FECHA (último ~ primeiro), aplica ajuste de Bowditch (distribui o erro de
 *  fechamento proporcional ao comprimento) e descarta o vértice de fechamento.
 *  Devolve ['pts'=>[[lat,lng]...], 'misfech'=>m, 'perim'=>m]. */
function traverseFromAnchor(array $anchor, array $legs, $zone = 23, $south = true) {
    if (count($legs) < 3) return null;
    $u = geoToUTM($anchor[0], $anchor[1], $zone); // [east, north]
    $E0 = $u[0]; $N0 = $u[1];
    $rawE = [$E0]; $rawN = [$N0]; $cum = [0.0]; $tot = 0.0;
    $E = $E0; $N = $N0;
    foreach ($legs as $leg) {
        $az = deg2rad((float)$leg['az']); $d = (float)$leg['dist'];
        $E += $d * sin($az); $N += $d * cos($az);
        $rawE[] = $E; $rawN[] = $N; $tot += $d; $cum[] = $tot;
    }
    $k = count($rawE);
    $dE = $rawE[$k-1] - $E0; $dN = $rawN[$k-1] - $N0;
    $misfech = hypot($dE, $dN);
    $fechou = ($tot > 0 && $misfech < 0.06 * $tot);   // último volta ~ao início => traçado fechado
    $pts = [[$anchor[0], $anchor[1]]];
    $limite = $fechou ? ($k - 1) : $k;                // se fecha, descarta o vértice de fechamento
    for ($i = 1; $i < $limite; $i++) {
        $Ea = $rawE[$i]; $Na = $rawN[$i];
        if ($fechou && $tot > 0) { $f = $cum[$i] / $tot; $Ea -= $dE * $f; $Na -= $dN * $f; } // Bowditch
        $g = utmToGeo($Ea, $Na, $zone, $south);
        $pts[] = [$g[0], $g[1]];
    }
    if (count($pts) < 3) return null;
    return ['pts' => $pts, 'misfech' => $misfech, 'perim' => $tot];
}

/** Região de referência = centroide dos imóveis já cadastrados (para detectar zona UTM errada). */
function refRegiaoImoveis($conn) {
    $r = @$conn->query("SELECT AVG(centro_lat) la, AVG(centro_lng) lo FROM memoriais_mapeados
                        WHERE centro_lat IS NOT NULL AND centro_lng IS NOT NULL
                          AND centro_lat BETWEEN -34 AND 6 AND centro_lng BETWEEN -74 AND -34");
    if ($r && ($row = $r->fetch_assoc()) && $row['la'] !== null) return [(float)$row['la'], (float)$row['lo']];
    return [null, null];
}

/** Escolhe a zona UTM: mantém a 23S por padrão; só troca se a 23 cair >150 km da região de
 *  referência (imóveis já cadastrados), testando zonas vizinhas e adotando a mais próxima. */
function utmResolverZona(array $pares, $refLat, $refLng, $south = true) {
    $conv = function ($z) use ($pares, $south) {
        $pts = []; $la = 0.0; $lo = 0.0;
        foreach ($pares as $p) { $g = utmToGeo($p[1], $p[0], $z, $south); $pts[] = [$g[0], $g[1]]; $la += $g[0]; $lo += $g[1]; }
        $n = max(1, count($pts));
        return ['pts' => $pts, 'cen' => [$la / $n, $lo / $n]];
    };
    $base = $conv(23);
    if ($refLat === null || $refLng === null) return ['zone' => 23, 'pts' => $base['pts'], 'ajustou' => false, 'dist23' => 0.0];
    $dist = function ($cen) use ($refLat, $refLng) {
        return hypot(($cen[0] - $refLat) * 111000.0, ($cen[1] - $refLng) * 111000.0 * cos(deg2rad($refLat)));
    };
    $d23 = $dist($base['cen']);
    if ($d23 <= 150000.0) return ['zone' => 23, 'pts' => $base['pts'], 'ajustou' => false, 'dist23' => $d23];
    $best = ['zone' => 23, 'pts' => $base['pts'], 'd' => $d23];
    foreach ([24, 22, 25, 21, 20, 19, 18] as $z) {
        $c = $conv($z); $d = $dist($c['cen']);
        if ($d < $best['d']) { $best = ['zone' => $z, 'pts' => $c['pts'], 'd' => $d]; }
    }
    return ['zone' => $best['zone'], 'pts' => $best['pts'], 'ajustou' => ($best['zone'] !== 23), 'dist23' => $d23];
}

function buildGeoData($memorial, $refLat = null, $refLng = null) {
    $res = extractGeoCoordinates($memorial);   // 1º GMS rotulado (Longitude:/Latitude:)
    $pts = $res['pts'];
    $fonte = 'gms';
    $utmPares = null;
    if (count($pts) < 3) {                       // 2º GMS sem rótulo (tabela SIGEF/INCRA)
        $tab = extractGeoCoordinatesTabela($memorial);
        if (count($tab['pts']) >= 3) { $pts = $tab['pts']; $fonte = 'gms_tabela'; $res = $tab; }
    }
    if (count($pts) < 3) {                        // 3º UTM (E/N em metros) — leiaute clássico
        $utm = extractUTMCoordinates($memorial);
        if (count($utm['pts']) >= 3) { $pts = $utm['pts']; $fonte = 'utm'; $utmPares = $utm['utm'] ?? null; }
    }
    if (count($pts) < 3) {                        // 4º UTM ROTULADO "<num>-E e <num>-N" / "N=.. E=.."
        $utr = extractUTMVerticesRotulados($memorial);
        if (count($utr['pts']) >= 3) { $pts = $utr['pts']; $fonte = 'utm_rotulado'; $utmPares = $utr['utm'] ?? null; }
    }
    if (count($pts) < 3) {                        // 5º TABELA UTM só com números (colunas N(Y)/E(X) no cabeçalho)
        $uts = extractUTMTabelaSimples($memorial);
        if (count($uts['pts']) >= 3) { $pts = $uts['pts']; $fonte = 'utm_tabela'; $utmPares = $uts['utm'] ?? null; }
    }

    // 6º FALLBACK POR ÂNCORA: sem vértices suficientes por coordenada, mas há 1 vértice inicial
    // (geográfico OU UTM) + lados (azimute/distância) — reconstrói o polígono a partir da âncora,
    // fechando por Bowditch quando o traçado fecha. Cobre memoriais antigos/OCR e SIGEF cuja
    // transcrição trouxe só a coordenada inicial.
    $avisoAncora = '';
    if (count($pts) < 3) {
        $legsA = extractTraverseLegsMarco($memorial);                                             // tolerante (OCR sujo)
        if (count($legsA) < 3) { $t1 = extractTraverseLegsLoose($memorial);  if (count($t1) > count($legsA)) $legsA = $t1; }
        if (count($legsA) < 3) { $t2 = extractTraverseLegsSigef($memorial);  if (count($t2) > count($legsA)) $legsA = $t2; }
        if (count($legsA) < 3) { $t3 = extractTraverseLegsTabela($memorial); if (count($t3) > count($legsA)) $legsA = $t3; }
        if (count($legsA) >= 3) {
            $anchor = (count($pts) >= 1) ? $pts[0] : extractAnchorUTM($memorial, 23, true);       // geográfica ou UTM
            if ($anchor) {
                $poly = traverseFromAnchor($anchor, $legsA, 23, true);
                if ($poly && count($poly['pts']) >= 3) {
                    $pts = $poly['pts']; $fonte = 'traverse_ancora';
                    $avisoAncora = 'Vértices reconstruídos a partir da coordenada inicial e dos azimutes/distâncias do memorial.';
                    if (($poly['perim'] ?? 0) > 0 && ($poly['misfech'] ?? 0) > 0.005 * $poly['perim']) {
                        $avisoAncora .= ' O traçado tinha erro de fechamento de ' . number_format($poly['misfech'], 1, ',', '.')
                            . ' m em ' . number_format($poly['perim'], 0, ',', '.') . ' m de perímetro (possível imprecisão de OCR); '
                            . 'foi ajustado automaticamente (Bowditch).';
                    }
                    $avisoAncora .= ' Confira o desenho antes de gravar.';
                }
            }
        }
    }

    // ZONA UTM AUTOMÁTICA (só nos casos peculiares): a conversão padrão é a zona 23S. Se ela cair
    // longe (>150 km) da região dos imóveis já cadastrados, testa zonas vizinhas e adota a coerente.
    // Sem imóveis de referência (base vazia), mantém 23 — comportamento antigo.
    $avisoZona = '';
    if ($utmPares && count($utmPares) >= 3 && $refLat !== null && $refLng !== null) {
        $rz = utmResolverZona($utmPares, $refLat, $refLng, true);
        if ($rz['ajustou']) {
            $pts = $rz['pts'];
            $avisoZona = 'Coordenadas UTM reinterpretadas na zona ' . $rz['zone'] . 'S — a zona 23 padrão caía a '
                . number_format($rz['dist23'] / 1000, 0, ',', '.') . ' km da região dos imóveis já cadastrados.';
        }
    }

    // Reconciliação por caminhamento: corrige vértices com coordenada incoerente
    // (erro de digitação no documento) usando os azimutes/distâncias do memorial.
    $corrigidos = [];
    $avisoTrav = '';
    if (count($pts) >= 3) {
        $invalidos = $res['invalidos'] ?? []; // vértices com minuto/segundo >= 60 (erro claro)
        $legs = extractTraverseLegsLoose($memorial);
        if (count($legs) < max(3, count($pts) - 1)) {           // formato tabular (sem a palavra "azimute")
            $legsTab = extractTraverseLegsTabela($memorial);
            if (count($legsTab) > count($legs)) $legs = $legsTab;
        }
        if (count($legs) < max(3, count($pts) - 1)) {           // formato SIGEF "135°20' e 1.311,26 m até"
            $legsSig = extractTraverseLegsSigef($memorial);
            if (count($legsSig) > count($legs)) $legs = $legsSig;
        }
        if (count($legs) >= 3) {
            $rec = reconcileTraverse($pts, $legs, 23, true, 5.0, $invalidos);
            if ($rec['usou']) { $pts = $rec['pts']; $corrigidos = $rec['corrigidos']; }
        }
        // Coordenadas ARREDONDADAS (comum em plantas): se o traçado (azimute+distância) fecha
        // com a ÁREA DECLARADA muito melhor que as coordenadas, usa a forma do traçado —
        // posição pelas coordenadas, forma pelo caminhamento. A área declarada é o juiz.
        $declM2 = extractDeclaredArea($memorial);
        if ($declM2 !== null && empty($corrigidos)
            && (count($legs) === count($pts) || count($legs) === count($pts) - 1)) {
            $trav = traverseAnchoredPolygon($pts, $legs, 23, true);
            if ($trav) {
                $aCoord = polygonAreaHa($pts) * 10000.0;   // m²
                $aTrav  = polygonAreaHa($trav) * 10000.0;  // m²
                $errCoord = abs($aCoord - $declM2);
                $errTrav  = abs($aTrav - $declM2);
                if ($errTrav < $errCoord && $errCoord > max(1.0, 0.03 * $declM2)) {
                    $pts = $trav;
                    $avisoTrav = 'Forma reconstruída pelos azimutes/distâncias do documento (a tabela de coordenadas estava arredondada). '
                        . 'A área agora confere com a declarada (' . number_format($declM2, 4, ',', '.') . ' m²). Confira o desenho antes de gravar.';
                }
            }
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
    } elseif ($avisoTrav !== '') {
        $data['aviso_geometria'] = $avisoTrav;
    }
    if ($avisoZona !== '') {
        $data['aviso_geometria'] = trim($avisoZona . ' ' . ($data['aviso_geometria'] ?? ''));
    }
    if ($avisoAncora !== '') {
        $data['aviso_geometria'] = trim($avisoAncora . ' ' . ($data['aviso_geometria'] ?? ''));
    }
    return $data;
}

/* ====================================================================
 *  ANÁLISE DE COORDENADAS INVÁLIDAS  (laudo: transcrito x corrigido)
 *  Detecta vértices com coordenada incoerente (erro de digitação/OCR no
 *  documento), compara o traçado TRANSCRITO (coordenadas como vieram) com o
 *  CORRIGIDO (reconstruído pelos azimutes/distâncias do próprio memorial) e
 *  deixa o usuário escolher qual mapear/gravar.
 * ==================================================================== */

/** Conserta confusões de OCR (l/I->1, O/o->0) dentro de um token numérico. */
function repararDigitosOCR($s) {
    return strtr((string)$s, ['l' => '1', 'L' => '1', 'I' => '1', 'O' => '0', 'o' => '0']);
}

/** Legs azimute+distância tolerante a OCR (ex.: "l.055,53m" -> 1055,53). */
function extractTraverseLegsLoose($text) {
    $t = normalizeGeoText($text);
    $re = '/azimute\s*(?:de)?\s*(\d+)\s*°\s*(?:(\d+)\s*\'\s*(?:([\d.,]+)\s*"?)?)?[^0-9]{0,40}?dist[âa]ncia\s*(?:de)?\s*([\dlLIOo.,]+)\s*m/isu';
    preg_match_all($re, $t, $m, PREG_SET_ORDER);
    $legs = [];
    foreach ($m as $x) {
        $legs[] = ['az' => dmsToDecimal($x[1], $x[2] ?? '', $x[3] ?? ''), 'dist' => brNumero(repararDigitosOCR($x[4]))];
    }
    return $legs;
}

/** Normaliza um rótulo de marco ("M-ll","M 02","M-O7") -> "M-11","M-02","M-07". */
function _labelMarco($raw) {
    $raw = strtoupper(trim((string)$raw));
    $raw = strtr($raw, ['L' => '1', 'I' => '1', 'O' => '0']);
    if (!preg_match('/(\d{1,3})/', $raw, $mm)) return 'M-?';
    return 'M-' . str_pad((string)((int)$mm[1]), 2, '0', STR_PAD_LEFT);
}

/**
 * Extrai vértices UTM ROTULADOS preservando a ordem do documento, aceitando os
 * dois leiautes usuais: "<num>-E e <num>-N" (número antes da letra, comum nos
 * memoriais do INCRA/SIGEF) e "N=<num> E=<num>". Conserta easting fora de faixa
 * (7 dígitos por digitação) usando a MEDIANA dos eastings válidos como contexto.
 * Retorna ['utm'=>[[N,E],...], 'pts'=>[[lat,lng],...], 'rotulos'=>[...], 'typos'=>[...], 'e_count','n_count'].
 */
function extractUTMVerticesRotulados($rawText, $zone = 23, $south = true) {
    $t = normalizeGeoText($rawText);
    $verts = []; // [label, N, E]

    // Leiaute A: rótulo ... <E>-E e <N>-N (número antes da letra)
    $reA = '/(M[-\s]?[0-9lLIoO]{1,3})[^0-9]{0,80}?UTM["\'\s]*([\d.,]+)\s*-?\s*E\s*e\s*([\d.,]+)\s*-?\s*N/isu';
    if (preg_match_all($reA, $t, $mA, PREG_SET_ORDER)) {
        foreach ($mA as $x) {
            $verts[] = [_labelMarco($x[1]), brNumero(repararDigitosOCR($x[3])), brNumero(repararDigitosOCR($x[2]))];
        }
    }
    // Leiaute B (fallback): rótulo ... N=<num> ... E=<num>
    if (count($verts) < 3) {
        $verts = [];
        $reB = '/(M[-\s]?[0-9lLIoO]{1,3})[^0-9]{0,80}?N\s*=?\s*([\d.,]+)\s*m?[^0-9]{0,20}?E\s*=?\s*([\d.,]+)/isu';
        if (preg_match_all($reB, $t, $mB, PREG_SET_ORDER)) {
            foreach ($mB as $x) {
                $verts[] = [_labelMarco($x[1]), brNumero(repararDigitosOCR($x[2])), brNumero(repararDigitosOCR($x[3]))];
            }
        }
    }
    if (count($verts) < 3) return ['utm' => [], 'pts' => [], 'rotulos' => [], 'typos' => [], 'e_count' => 0, 'n_count' => 0];

    // remove vértice de fechamento repetido (último ~ primeiro) ANTES de reparar typos
    $k = count($verts);
    if ($k > 3 && abs($verts[0][1] - $verts[$k-1][1]) < 0.2 && abs($verts[0][2] - $verts[$k-1][2]) < 0.2) {
        array_pop($verts);
    }

    // mediana dos eastings válidos (faixa UTM 100000..999999) para reparar typos
    $validE = [];
    foreach ($verts as $vv) { if ($vv[2] >= 100000 && $vv[2] <= 999999) $validE[] = $vv[2]; }
    $medE = medianaFloat($validE);
    $typos = [];
    foreach ($verts as $i => $vv) {
        $E = $vv[2];
        if ($E < 100000 || $E > 999999) {
            $intp = (string)(int)floor(abs($E));
            $frac = abs($E) - floor(abs($E));
            $best = null;
            for ($kk = 0; $kk < strlen($intp); $kk++) {
                $cand = (int)(substr($intp, 0, $kk) . substr($intp, $kk + 1));
                if ($cand >= 100000 && $cand <= 999999) {
                    if ($best === null || abs($cand - $medE) < abs($best - $medE)) $best = $cand;
                }
            }
            if ($best !== null) {
                $typos[] = $vv[0] . ': easting ' . $intp . ' (' . strlen($intp) . ' díg.) corrigido p/ ' . $best;
                $verts[$i][2] = (float)$best + $frac;
            } else {
                $typos[] = $vv[0] . ': easting ' . $intp . ' fora da faixa UTM e não corrigível automaticamente';
            }
        }
    }

    $utm = []; $pts = []; $rot = [];
    foreach ($verts as $vv) {
        $utm[] = [$vv[1], $vv[2]];                       // [N, E]
        $g = utmToGeo($vv[2], $vv[1], $zone, $south);    // utmToGeo(east, north)
        $pts[] = [$g[0], $g[1]];
        $rot[] = $vv[0];
    }
    return ['utm' => $utm, 'pts' => $pts, 'rotulos' => $rot, 'typos' => $typos,
            'e_count' => count($pts), 'n_count' => count($pts)];
}

/** Área (Gauss) em hectares direto sobre UTM [[N,E],...]. */
function _areaHaUTM($utm) {
    $a = 0.0; $n = count($utm);
    if ($n < 3) return 0.0;
    for ($i = 0; $i < $n; $i++) {
        $j = ($i + 1) % $n;
        $a += $utm[$i][1] * $utm[$j][0] - $utm[$j][1] * $utm[$i][0]; // E_i*N_j - E_j*N_i
    }
    return abs($a) / 2.0 / 10000.0;
}

/**
 * Laudo de coordenadas: compara o traçado TRANSCRITO (coordenadas do documento)
 * com o CORRIGIDO (reconstruído pelos azimutes/distâncias). Devolve dois pacotes
 * de geometria prontos para o mapa + a tabela de divergências por lado.
 */
function analisarCoordenadas($memorial, $zone = 23, $south = true) {
    $vinfo = extractUTMVerticesRotulados($memorial, $zone, $south);
    if (count($vinfo['utm']) < 3) {
        return ['ok' => false, 'erro' => 'Não foram encontrados vértices UTM rotulados (mínimo 3). O laudo de coordenadas é específico para memoriais com coordenadas UTM dos marcos (E/N).'];
    }
    $utm  = $vinfo['utm'];            // [[N,E],...]
    $rot  = $vinfo['rotulos'];
    $v    = count($utm);
    $legs = extractTraverseLegsLoose($memorial);
    $temLegs = (count($legs) === $v || count($legs) === $v - 1);

    // ---- pacote TRANSCRITO ----
    $geoTrans = buildGeoDataFromPoints($vinfo['pts']);
    $geoTrans['rotulos'] = $rot;
    $geoTrans['area_ha_utm'] = _areaHaUTM($utm);

    // ---- pacote CORRIGIDO (traverse com âncora robusta por mediana) ----
    $geoCorr = null; $fechamento = null;
    if ($temLegs) {
        $rel = [[0.0, 0.0]];               // caminhamento relativo (N,E): ΔN=d·cos(az), ΔE=d·sin(az)
        $lim = min(count($legs), $v - 1);
        for ($i = 0; $i < $lim; $i++) {
            $az = deg2rad($legs[$i]['az']); $d = (float)$legs[$i]['dist'];
            $rel[] = [$rel[$i][0] + $d * cos($az), $rel[$i][1] + $d * sin($az)];
        }
        while (count($rel) < $v) $rel[] = end($rel);
        $dN = []; $dE = [];
        for ($i = 0; $i < $v; $i++) { $dN[] = $utm[$i][0] - $rel[$i][0]; $dE[] = $utm[$i][1] - $rel[$i][1]; }
        $offN = medianaFloat($dN); $offE = medianaFloat($dE);
        $corrUTM = [];
        for ($i = 0; $i < $v; $i++) $corrUTM[] = [$rel[$i][0] + $offN, $rel[$i][1] + $offE];
        $fn = 0.0; $fe = 0.0;              // erro de fechamento = módulo da soma vetorial das legs
        foreach ($legs as $lg) { $a = deg2rad($lg['az']); $fn += $lg['dist'] * cos($a); $fe += $lg['dist'] * sin($a); }
        $fechamento = hypot($fn, $fe);
        $corrPts = [];
        foreach ($corrUTM as $u) { $g = utmToGeo($u[1], $u[0], $zone, $south); $corrPts[] = [$g[0], $g[1]]; }
        $geoCorr = buildGeoDataFromPoints($corrPts);
        $geoCorr['rotulos'] = $rot;
        $geoCorr['area_ha_utm'] = _areaHaUTM($corrUTM);
        $geoCorr['fechamento_m'] = round($fechamento, 2);
    }

    // ---- tabela de divergências por lado (sobre o TRANSCRITO) ----
    $tabela = []; $suspeitos = [];
    for ($i = 0; $i < $v; $i++) {
        $j = ($i + 1) % $v;
        $dNs = $utm[$j][0] - $utm[$i][0]; $dEs = $utm[$j][1] - $utm[$i][1];
        $distC = hypot($dNs, $dEs);
        $azC = fmod(rad2deg(atan2($dEs, $dNs)) + 360.0, 360.0);
        $row = ['de' => $rot[$i], 'para' => $rot[$j], 'dist_calc' => round($distC, 2), 'az_calc' => $azC];
        if ($i < count($legs)) {
            $azD = $legs[$i]['az']; $distD = (float)$legs[$i]['dist'];
            $ad = abs($azC - $azD); if ($ad > 180) $ad = 360 - $ad;
            $susp = (abs($distC - $distD) > 1.0) || ($ad > 0.5);
            $row['dist_decl'] = round($distD, 2); $row['az_decl'] = $azD; $row['suspeito'] = $susp;
            if ($susp) { $suspeitos[$rot[$i]] = true; $suspeitos[$rot[$j]] = true; }
        } else {
            $row['dist_decl'] = null; $row['az_decl'] = null; $row['suspeito'] = false;
        }
        $tabela[] = $row;
    }

    // ---- área/perímetro declarados no texto ----
    $tnorm = normalizeGeoText($memorial);
    $areaDecl = null;
    if (preg_match('/[áa]rea\s*total\s*\(ha\)\s*([\d.]*\d,\d+)/iu', $tnorm, $ma)) $areaDecl = brNumero($ma[1]);
    elseif (preg_match('/[áa]rea[^0-9]{0,30}?([\d.]*\d,\d{2,})\s*ha/iu', $tnorm, $ma)) $areaDecl = brNumero($ma[1]);
    $perDecl = null;
    if (preg_match('/per[íi]metro[^0-9]{0,20}?([\d.]*\d,\d{2})\s*m/iu', $tnorm, $mp)) $perDecl = brNumero($mp[1]);
    elseif (preg_match('/com\s+([\d.]*\d,\d{2})\s*m/iu', $tnorm, $mp)) $perDecl = brNumero($mp[1]);

    // ---- recomendação ----
    $recom = 'transcrito'; $motivoRec = '';
    if ($geoCorr) {
        $dCorr = ($areaDecl !== null) ? abs($geoCorr['area_ha_utm'] - $areaDecl) : null;
        $dTrans = ($areaDecl !== null) ? abs($geoTrans['area_ha_utm'] - $areaDecl) : null;
        $fechaBem = ($fechamento !== null && $fechamento < 1.0);
        if ($fechaBem && ($dCorr === null || $dTrans === null || $dCorr <= $dTrans)) {
            $recom = 'corrigido';
            $motivoRec = 'O caminhamento por azimutes/distâncias fecha o polígono (erro ' . number_format($fechamento, 2, ',', '.') . ' m)'
                . ($areaDecl !== null ? ' e a área bate com a declarada (' . number_format($geoCorr['area_ha_utm'], 4, ',', '.') . ' ha ≈ ' . number_format($areaDecl, 4, ',', '.') . ' ha).' : '.');
        }
    }

    // ---- resumo textual ----
    $resumo = [];
    $resumo[] = $v . ' vértices lidos do memorial' . (count($legs) ? ' e ' . count($legs) . ' lados (azimute+distância).' : '.');
    if (!empty($vinfo['typos'])) $resumo[] = 'Correção de digitação: ' . implode('; ', $vinfo['typos']) . '.';
    $resumo[] = 'Transcrito (coordenadas do documento): ' . number_format($geoTrans['area_ha_utm'], 4, ',', '.') . ' ha, perímetro ' . number_format($geoTrans['perimetro_m'], 2, ',', '.') . ' m.';
    if ($geoCorr) $resumo[] = 'Corrigido (azimutes/distâncias): ' . number_format($geoCorr['area_ha_utm'], 4, ',', '.') . ' ha, perímetro ' . number_format(($perDecl ?? $geoCorr['perimetro_m']), 2, ',', '.') . ' m, fechamento ' . number_format($fechamento, 2, ',', '.') . ' m.';
    if ($areaDecl !== null) $resumo[] = 'Área declarada no documento: ' . number_format($areaDecl, 4, ',', '.') . ' ha.';
    $nsusp = count($suspeitos);
    if ($nsusp) $resumo[] = $nsusp . ' vértice(s) com coordenada incoerente: ' . implode(', ', array_keys($suspeitos)) . '.';

    return [
        'ok' => true,
        'zona' => $zone, 'hemisferio' => $south ? 'sul' : 'norte',
        'num_vertices' => $v, 'num_legs' => count($legs), 'tem_legs' => $temLegs,
        'typos' => $vinfo['typos'],
        'area_declarada_ha' => $areaDecl,
        'perimetro_declarado_m' => $perDecl,
        'transcrito' => $geoTrans,
        'corrigido' => $geoCorr,
        'legs' => $tabela,
        'vertices_suspeitos' => array_keys($suspeitos),
        'recomendacao' => $recom,
        'motivo_recomendacao' => $motivoRec,
        'resumo' => $resumo,
    ];
}

/** Laudo de coordenadas só quando há discrepância real (p/ anexar à resposta do PDF). */
function laudoSeDiscrepante($memorial) {
    $m = (string)$memorial;
    if (trim($m) === '') return null;
    $lau = analisarCoordenadas($m);
    if (!empty($lau['ok']) && !empty($lau['corrigido']) && (!empty($lau['vertices_suspeitos']) || !empty($lau['typos']))) return $lau;
    return null;
}

/* ====================================================================
 *  ANÁLISE DE MEMORIAL NARRATIVO (vértices rotulados P-1..P-n + lados
 *  "azimute e distância Az=..°..'..\" e DIST metros até o vértice P-x").
 *  Detecta: (a) coordenada fora da faixa UTM (erro de digitação — ex.: northing
 *  com 8 dígitos) e a conserta pelo lado de chegada; (b) vértice deslocado da
 *  posição prevista pelo azimute/distância do próprio memorial (marco incoerente).
 *  Alimenta tanto as INCONSISTÊNCIAS do cadastro (matrículas e projetos) quanto o
 *  PAINEL de foco no imóvel.
 * ==================================================================== */

/** Rótulo genérico de vértice ("P-1","P-15","M-07"), preservando a letra-prefixo. */
function vxLabel($raw) {
    $raw = strtoupper(trim((string)$raw));
    $raw = strtr($raw, ['I' => '1', 'L' => '1']); // OCR comum no número
    if (preg_match('/([A-Z]{1,3})[-\s]?0*(\d{1,4})/', $raw, $m)) return $m[1] . '-' . (int)$m[2];
    if (preg_match('/0*(\d{1,4})/', $raw, $m)) return 'V-' . (int)$m[1];
    return 'V-?';
}
/** Faixas plausíveis UTM (Brasil, hemisfério sul). */
function vxBandaN($v) { return $v >= 1000000 && $v <= 10000000; }
function vxBandaE($v) { return $v >= 100000  && $v <= 999999; }
/** Candidatos ao conserto de um número fora de faixa removendo 1 dígito. */
function vxDigitDrop($val, $lo, $hi) {
    $intp = (string)(int)floor(abs($val)); $frac = abs($val) - floor(abs($val)); $out = [];
    for ($k = 0; $k < strlen($intp); $k++) {
        $c = (int)(substr($intp, 0, $k) . substr($intp, $k + 1));
        if ($c >= $lo && $c <= $hi) $out[] = (float)$c + $frac;
    }
    return array_values(array_unique($out));
}

function analisarMemorialVertex($memorial, $zone = 23, $south = true) {
    $t = normalizeGeoText($memorial);
    $num = '\d{1,3}(?:\.\d{3})+(?:,\d+)?|\d+(?:[.,]\d+)?';

    // ---- vértices rotulados em prosa: "vértice P-1, de coordenadas N=.. e E=.." ----
    $reV = '/(?:v[ée]rtice|ponto|marco|estaca)\s+([A-Z]{1,3}[-\s]?[0-9lLI]{1,4})\b[^NE]{0,90}?\bN\s*=?\s*(' . $num . ')\s*(?:m\b)?[^NE]{0,25}?\bE\s*=?\s*(' . $num . ')/su';
    preg_match_all($reV, $t, $mV, PREG_SET_ORDER);
    $vx = [];
    foreach ($mV as $x) {
        $vx[] = ['rot' => vxLabel($x[1]), 'N' => brNumero($x[2]), 'E' => brNumero($x[3]), 'typo' => null, 'suspeito' => false, 'desvio_m' => null];
    }
    if (count($vx) < 3) return ['ok' => false, 'erro' => 'Memorial sem vértices rotulados em prosa (mínimo 3) — este analisador é específico para memoriais narrativos com "vértice P-n, de coordenadas N=.. e E=..".'];

    // remove vértice de fechamento repetido (último ~ primeiro)
    $k = count($vx);
    if ($k > 3 && abs($vx[0]['N'] - $vx[$k-1]['N']) < 0.2 && abs($vx[0]['E'] - $vx[$k-1]['E']) < 0.2) array_pop($vx);
    $nv = count($vx);

    // ---- lados azimute+distância com destino: "..e DIST metros até o vértice P-x" ----
    $reL = '/azimute\s*e\s*dist[âa]ncia\s*Az\s*=?\s*(\d{1,3})\s*°\s*(?:(\d{1,2})\s*\'\s*(?:([\d.,]+)\s*"?)?)?\s*e\s*(' . $num . ')\s*metros?[\s,]*at[ée]\s+(?:o\s+)?(?:v[ée]rtice\s+)?([A-Za-z]{1,3}[-\s]?\d{1,4}|chegarmos|ponto)/su';
    preg_match_all($reL, $t, $mL, PREG_SET_ORDER);
    $legs = [];
    foreach ($mL as $x) {
        $dest = $x[5] ?? ''; $paraRot = null;
        if (preg_match('/^(chegarmos|ponto)/i', $dest)) $paraRot = $vx[0]['rot']; // fecha no ponto inicial
        elseif ($dest !== '') $paraRot = vxLabel($dest);
        $legs[] = ['az' => dmsToDecimal($x[1], $x[2] ?? '', $x[3] ?? ''), 'dist' => brNumero($x[4]), 'para' => $paraRot];
    }
    $legPara = []; foreach ($legs as $lg) { if ($lg['para'] !== null) $legPara[$lg['para']] = $lg; }

    // ---- (a) conserta N/E fora de faixa pelo lado de chegada (ou mediana, sem lado) ----
    $typos = [];
    for ($i = 0; $i < $nv; $i++) {
        $badN = !vxBandaN($vx[$i]['N']); $badE = !vxBandaE($vx[$i]['E']);
        if (!$badN && !$badE) continue;
        $prev = $i > 0 ? $vx[$i-1] : null;
        $lg = $legPara[$vx[$i]['rot']] ?? null;
        $fixN = $vx[$i]['N']; $fixE = $vx[$i]['E']; $via = '';
        if ($prev && $lg && vxBandaN($prev['N']) && vxBandaE($prev['E'])) {
            $az = deg2rad($lg['az']); $d = (float)$lg['dist'];
            $tN = $prev['N'] + $d * cos($az); $tE = $prev['E'] + $d * sin($az);
            if ($badN) { $best = null; foreach (vxDigitDrop($vx[$i]['N'], 1000000, 10000000) as $c) if ($best === null || abs($c - $tN) < abs($best - $tN)) $best = $c; if ($best !== null) { $fixN = $best; $via = 'pelo azimute/distância de ' . $prev['rot'] . '→' . $vx[$i]['rot']; } }
            if ($badE) { $best = null; foreach (vxDigitDrop($vx[$i]['E'],  100000,   999999) as $c) if ($best === null || abs($c - $tE) < abs($best - $tE)) $best = $c; if ($best !== null) $fixE = $best; }
        } else {
            $valN = []; $valE = []; foreach ($vx as $w) { if (vxBandaN($w['N'])) $valN[] = $w['N']; if (vxBandaE($w['E'])) $valE[] = $w['E']; }
            if ($badN) { $mN = medianaFloat($valN); $best = null; foreach (vxDigitDrop($vx[$i]['N'], 1000000, 10000000) as $c) if ($best === null || abs($c - $mN) < abs($best - $mN)) $best = $c; if ($best !== null) $fixN = $best; }
            if ($badE) { $mE = medianaFloat($valE); $best = null; foreach (vxDigitDrop($vx[$i]['E'],  100000,   999999) as $c) if ($best === null || abs($c - $mE) < abs($best - $mE)) $best = $c; if ($best !== null) $fixE = $best; }
        }
        $msg = [];
        if ($badN) $msg[] = 'N=' . (int)$vx[$i]['N'] . ' (' . strlen((string)(int)$vx[$i]['N']) . ' díg.) → ' . (int)$fixN;
        if ($badE) $msg[] = 'E=' . (int)$vx[$i]['E'] . ' → ' . (int)$fixE;
        $vx[$i]['typo'] = implode('; ', $msg) . ($via ? ' (' . $via . ')' : '');
        $vx[$i]['suspeito'] = true; $typos[$vx[$i]['rot']] = $vx[$i]['typo'];
        $vx[$i]['N'] = $fixN; $vx[$i]['E'] = $fixE;
    }

    // ---- lat/lng + área/perímetro (plano UTM, como o memorial) ----
    $utm = [];
    for ($i = 0; $i < $nv; $i++) { $g = utmToGeo($vx[$i]['E'], $vx[$i]['N'], $zone, $south); $vx[$i]['lat'] = $g[0]; $vx[$i]['lng'] = $g[1]; $utm[] = [$vx[$i]['N'], $vx[$i]['E']]; }
    $areaM2 = 0.0; for ($i = 0; $i < $nv; $i++) { $j = ($i+1) % $nv; $areaM2 += $utm[$i][1]*$utm[$j][0] - $utm[$j][1]*$utm[$i][0]; } $areaM2 = abs($areaM2) / 2.0;
    $per = 0.0; for ($i = 0; $i < $nv; $i++) { $j = ($i+1) % $nv; $per += hypot($utm[$j][1]-$utm[$i][1], $utm[$j][0]-$utm[$i][0]); }

    // ---- tabela de divergência por lado (coords x azimute/distância declarados) ----
    $lados = [];
    for ($i = 0; $i < $nv; $i++) {
        $j = ($i+1) % $nv; $dN = $utm[$j][0]-$utm[$i][0]; $dE = $utm[$j][1]-$utm[$i][1];
        $distC = hypot($dN, $dE); $azC = fmod(rad2deg(atan2($dE, $dN)) + 360.0, 360.0);
        $lg = $legPara[$vx[$j]['rot']] ?? null;
        $row = ['de' => $vx[$i]['rot'], 'para' => $vx[$j]['rot'], 'dist_calc' => round($distC, 2), 'az_calc' => round($azC, 4), 'dist_decl' => null, 'az_decl' => null, 'suspeito' => false];
        if ($lg) { $ad = abs($azC - $lg['az']); if ($ad > 180) $ad = 360 - $ad;
            $row['dist_decl'] = round($lg['dist'], 2); $row['az_decl'] = round($lg['az'], 4);
            $row['suspeito'] = (abs($distC - $lg['dist']) > 3.0) || ($ad > 0.5); }
        $lados[] = $row;
    }
    // ---- (b) vértice CULPADO: reconstrói cada vértice pelo lado de chegada (a partir do anterior);
    //          desvio > 5 m da posição prevista = marco incoerente. Pula o vértice inicial (fechamento). ----
    $suspeitos = $typos;
    for ($j = 1; $j < $nv; $j++) {
        if (!empty($vx[$j]['typo'])) continue;
        $lg = $legPara[$vx[$j]['rot']] ?? null; if (!$lg) continue;
        $i = $j - 1; $az = deg2rad($lg['az']); $d = (float)$lg['dist'];
        $rN = $utm[$i][0] + $d * cos($az); $rE = $utm[$i][1] + $d * sin($az);
        $off = hypot($utm[$j][1] - $rE, $utm[$j][0] - $rN);
        if ($off > 5.0) { $suspeitos[$vx[$j]['rot']] = true; $vx[$j]['suspeito'] = true; $vx[$j]['desvio_m'] = round($off, 2); }
    }

    // ---- confrontantes (heurística por lado) ----
    $conf = [];
    if (preg_match('/margem da (Estrada[^,.;]+)/su', $t, $mc)) $conf[] = trim($mc[1]);
    if (preg_match_all('/limitar com (?:as\s+)?terras do (?:Sr\.?|Sra\.?)?\s*([^,.;]+)/su', $t, $mc2)) foreach ($mc2[1] as $nome) $conf[] = trim($nome);

    // ---- área/perímetro declarados ----
    $areaDecl = null; if (preg_match('/[áa]rea[^0-9]{0,30}?([\d.]*\d,\d{2,})\s*ha/iu', $t, $ma)) $areaDecl = brNumero($ma[1]);
    $perDecl = null;  if (preg_match('/per[íi]metro[^0-9]{0,20}?([\d.]*\d,\d{2})\s*m/iu', $t, $mpp)) $perDecl = brNumero($mpp[1]);

    return ['ok' => true, 'zona' => $zone, 'hemisferio' => $south ? 'sul' : 'norte',
        'datum' => (preg_match('/SAD[-\s]?69/i', $t) ? 'SAD-69' : (preg_match('/SIRGAS/i', $t) ? 'SIRGAS2000' : '')),
        'mc' => (preg_match('/(\d{2})\s*W\s*Gr/i', $t, $mm) ? $mm[1] . 'W' : ''),
        'num_vertices' => $nv, 'vertices' => $vx, 'legs' => $legs, 'lados' => $lados,
        'area_m2' => round($areaM2, 2), 'area_ha' => round($areaM2 / 10000, 4), 'perimetro_m' => round($per, 2),
        'area_declarada_ha' => $areaDecl, 'perimetro_declarado_m' => $perDecl,
        'typos' => $typos, 'suspeitos' => array_keys($suspeitos), 'confrontantes' => $conf];
}

/** Converte o laudo de memorial narrativo em INCONSISTÊNCIAS {sev,msg} (cadastro de matrículas e projetos). */
function detectarInconsistenciasCoord($memorial) {
    $inc = []; $m = (string)$memorial; if (trim($m) === '') return $inc;
    $r = analisarMemorialVertex($m); if (empty($r['ok'])) return $inc;
    foreach ($r['vertices'] as $v) {
        if (!empty($v['typo'])) {
            $inc[] = ['sev' => 'erro', 'msg' => 'Vértice ' . $v['rot'] . ': coordenada fora da faixa UTM (provável erro de digitação) — ' . $v['typo'] . '.'];
        } elseif (!empty($v['suspeito'])) {
            $inc[] = ['sev' => 'alerta', 'msg' => 'Vértice ' . $v['rot'] . ' está a ~' . number_format((float)($v['desvio_m'] ?? 0), 1, ',', '.') . ' m da posição prevista pelo azimute/distância do memorial — confira a coordenada do marco.'];
        }
    }
    if ($r['area_declarada_ha'] !== null && abs($r['area_ha'] - $r['area_declarada_ha']) > max(0.05, 0.03 * $r['area_declarada_ha'])) {
        $inc[] = ['sev' => 'alerta', 'msg' => 'Área calculada (' . number_format($r['area_ha'], 4, ',', '.') . ' ha) diverge da declarada no memorial (' . number_format($r['area_declarada_ha'], 4, ',', '.') . ' ha).'];
    }
    return $inc;
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

/* O imóvel já possui geometria (coordenadas)? */
function imovelTemGeo($conn, $id) {
    $id = (int)$id;
    $rs = $conn->query("SELECT coordenadas_wgs84, num_vertices FROM memoriais_mapeados WHERE id = $id LIMIT 1");
    $r = $rs ? $rs->fetch_assoc() : null;
    if (!$r) return false;
    return trim((string)($r['coordenadas_wgs84'] ?? '')) !== '' || (int)($r['num_vertices'] ?? 0) > 0;
}

/* Mapeia um imóvel SEM coordenadas (ex.: exclusivo da ITN 03) a partir de um KML ou memorial/SIGEF:
   extrai a geometria e atualiza a linha como MAPEADA (origem, coordenadas e itn03_exclusivo=0). */
function mapearImovelComGeo($conn, $id, $origem, $conteudo) {
    $id = (int)$id;
    if ($id <= 0) return ['ok' => false, 'erro' => 'Imóvel inválido.'];
    $conteudo = (string)$conteudo;
    if (trim($conteudo) === '') return ['ok' => false, 'erro' => 'Conteúdo do KML/memorial vazio.'];
    $origem = ($origem === 'kml') ? 'kml' : 'memorial';
    $geo = processarFonte($origem, $conteudo);
    if (empty($geo['ok'])) {
        return ['ok' => false, 'erro' => 'Não foi possível extrair coordenadas do ' . ($origem === 'kml' ? 'KML' : 'memorial/SIGEF') . ' (vértices encontrados: ' . (int)($geo['num_vertices'] ?? 0) . ').'];
    }
    $rs = $conn->query("SELECT numero_matricula FROM memoriais_mapeados WHERE id = $id LIMIT 1");
    $row = $rs ? $rs->fetch_assoc() : null;
    $mat = $row ? trim((string)($row['numero_matricula'] ?? '')) : '';
    $imovelId = ($mat !== '') ? findImovelIdByMatricula($conn, $mat) : null;
    $st = $conn->prepare("UPDATE memoriais_mapeados SET origem = ?, imovel_id = ?, memorial_descritivo = ?,
        num_vertices = ?, area_ha = ?, perimetro_m = ?, centro_lat = ?, centro_lng = ?,
        coordenadas_wgs84 = ?, coordenadas_utm = ?, itn03_exclusivo = 0 WHERE id = ?");
    if ($st) {
        $st->bind_param('sisiddddssi', $origem, $imovelId, $conteudo,
            $geo['num_vertices'], $geo['area_ha'], $geo['perimetro_m'], $geo['centro_lat'], $geo['centro_lng'],
            $geo['coordenadas_wgs84'], $geo['coordenadas_utm'], $id);
        $st->execute();
    }
    return ['ok' => true, 'geo' => $geo];
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
        cor_linha VARCHAR(20) NULL DEFAULT NULL,
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
        'cor_linha'        => "ADD COLUMN cor_linha VARCHAR(20) NULL DEFAULT NULL",
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
        // Imóvel que ULTRAPASSA o limite (parte em município vizinho): JSON {municipio,vizinho,dentro_ha,dentro_pct,fora_ha,fora_pct}.
        'parcial_json'         => "ADD COLUMN parcial_json TEXT NULL DEFAULT NULL",
        // Matrícula cadastrada SÓ para a carga ITN 03 (sem coordenadas/mapeamento).
        'itn03_exclusivo'      => "ADD COLUMN itn03_exclusivo TINYINT(1) NOT NULL DEFAULT 0",
        // Qualificação estruturada dos titulares ATUAIS (JSON), extraída dos registros/averbações.
        // Usada pela carga ITN 03 (dados_pessoa) e mantém compat. com as colunas proprietario/cpf.
        'qualificacao_json'    => "ADD COLUMN qualificacao_json MEDIUMTEXT NULL DEFAULT NULL",
        // Inconsistências detectadas na importação (JSON: [{sev,msg}, ...]). Imóvel é cadastrado mesmo assim.
        'inconsistencias'      => "ADD COLUMN inconsistencias MEDIUMTEXT NULL DEFAULT NULL",
        // Imóvel de PROJETO (ainda sem matrícula): fica na base de projetos, não aparece na base de matrículas.
        // Pode ser "concretizado" (is_projeto=0), tornando-se matrícula.
        'is_projeto'           => "ADD COLUMN is_projeto TINYINT(1) NOT NULL DEFAULT 0",
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
function detectarInconsistenciasGeo($geo, $origem = '', $memorial = '') {
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
    // Validação de coordenadas do memorial narrativo (marcos fora de faixa / vértices incoerentes)
    if (trim((string)$memorial) !== '') $inc = array_merge($inc, detectarInconsistenciasCoord($memorial));
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
    // CPF/CNPJ de cada titular: valida dígitos verificadores (um RG lançado por engano no lugar do CPF não passa)
    $nomesDoc = preg_split('/\s*,\s*/', (string)($d['proprietario'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $docsDoc  = preg_split('/\s*,\s*/', (string)($d['cpf'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($docsDoc as $i => $doc) {
        $dg = preg_replace('/\D/', '', $doc);
        if ($dg === '') continue;
        if (!cpfCnpjValido($dg)) {
            $quem = trim((string)($nomesDoc[$i] ?? ''));
            $inc[] = ['sev' => 'alerta', 'msg' => 'CPF/CNPJ' . ($quem !== '' ? ' de "' . $quem . '"' : '') . ' inválido ("' . $doc . '") — verifique; pode ter sido lido o RG no lugar do CPF. Corrija antes de enviar ao Mapa ONR.'];
        }
    }
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

    // matrícula encerrada (unificação/desmembramento total/georreferenciamento): não pode ser enviada
    if (strtolower(trim((string)($row['situacao'] ?? ''))) === 'encerrada') {
        $mot = strtolower(trim((string)($row['motivo_situacao'] ?? '')));
        $motTxt = $mot === 'georreferenciamento' ? 'por georreferenciamento' : ($mot === 'desmembramento' ? 'por desmembramento total' : 'por unificação');
        $suc = trim((string)($row['matricula_sucessora'] ?? ''));
        return ['ok' => false, 'mensagem' => 'Matrícula ENCERRADA ' . $motTxt . ($suc !== '' ? ' (sucessora: ' . $suc . ')' : '') . ' — não pode ser enviada ao Mapa ONR.'];
    }

    // valida metadados obrigatórios
    $cat = ($row['tipo_imovel'] === 'rural') ? 'RURAL' : (($row['tipo_imovel'] === 'urbano') ? 'URBANO' : '');
    $faltam = [];
    if ($cat === '') $faltam[] = 'tipo (urbano/rural)';
    if (($row['onr_nivel_publicidade'] ?? '') === '') $faltam[] = 'nível de publicidade';
    if (($row['onr_classificacao'] ?? '') === '') $faltam[] = 'classificação da importação';
    if (($row['onr_numero_prenotacao'] ?? '') === '') $faltam[] = 'número da prenotação';
    if (($row['onr_descricao'] ?? '') === '') $faltam[] = 'descrição';
    if ($faltam) return ['ok' => false, 'mensagem' => 'Faltam dados ONR: ' . implode(', ', $faltam) . '.'];

    // valida CPF/CNPJ dos titulares (dígitos verificadores): impede enviar um RG lançado por engano
    $nomesT = preg_split('/\s*,\s*/', (string)($row['proprietario'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $docsT  = preg_split('/\s*,\s*/', (string)($row['cpf'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($docsT as $i => $doc) {
        $dg = preg_replace('/\D/', '', $doc);
        if ($dg !== '' && !cpfCnpjValido($dg)) {
            $quem = trim((string)($nomesT[$i] ?? ''));
            return ['ok' => false, 'mensagem' => 'CPF/CNPJ' . ($quem !== '' ? ' de "' . $quem . '"' : '') . ' inválido ("' . $doc . '") — provável RG lançado no lugar do CPF. Corrija o documento do titular antes de enviar ao Mapa ONR.'];
        }
    }

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
function inserirMemorial($conn, $identificador, $tipo, $origem, $imovelId, $fonte, $data, $numMatricula = '', $proprietario = '', $cpf = '', $tipoImovel = '', $isProjeto = 0) {
    $nm = ($numMatricula !== '') ? $numMatricula : null;
    $pr = ($proprietario !== '') ? $proprietario : null;
    $cp = ($cpf !== '') ? $cpf : null;
    $ti = in_array($tipoImovel, ['urbano', 'rural'], true) ? $tipoImovel : null;
    $ipj = $isProjeto ? 1 : 0;
    $stmt = $conn->prepare(
        "INSERT INTO memoriais_mapeados
         (identificador, tipo_identificador, origem, imovel_id, memorial_descritivo,
          num_vertices, area_ha, perimetro_m, centro_lat, centro_lng,
          coordenadas_wgs84, coordenadas_utm,
          numero_matricula, proprietario, cpf, tipo_imovel, is_projeto)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'sssisiddddssssssi',
        $identificador, $tipo, $origem, $imovelId, $fonte,
        $data['num_vertices'], $data['area_ha'], $data['perimetro_m'],
        $data['centro_lat'], $data['centro_lng'],
        $data['coordenadas_wgs84'], $data['coordenadas_utm'],
        $nm, $pr, $cp, $ti, $ipj
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
    $params[] = 'path=' . rawurlencode('color:0x1d4ed8ff|weight:3|fillcolor:0x1d4ed833|enc:' . encodePolyline(closeRing($pts)));
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
        // Tenta 2x; força IPv4 (evita travar ~21s tentando IPv6 em Windows/XAMPP) e usa timeout de conexão curto.
        for ($i = 0; $i < 2; $i++) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json, application/vnd.geo+json'],
                CURLOPT_USERAGENT => 'Atlas-Mapeador/1.0',
            ];
            if (defined('CURL_IPRESOLVE_V4')) $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
            curl_setopt_array($ch, $opts);
            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);
            if ($data !== false && $code >= 200 && $code < 300) return $data;
            if ($cerr !== '') { $erro = 'cURL: ' . $cerr; }
            else { $erro = 'HTTP ' . $code . ($data ? ' — ' . substr(strip_tags((string)$data), 0, 200) : ''); break; }
            // repete só em erro de conexão/timeout (não em HTTP de erro)
        }
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
                $txt = 'Emitido em ' . date('d/m/Y H:i') . ' — Atlas Vertex / Sistema Atlas';
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
                $txt = 'Emitido em ' . date('d/m/Y H:i') . ' — Atlas Vertex / Sistema Atlas';
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
    $pdf->SetCreator('Atlas Vertex'); $pdf->SetTitle('Relatório de inconsistências');
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
    $norm = function ($s) {
        $k = strtolower(preg_replace('/[^0-9a-zA-Z]/', '', (string)$s));
        if ($k === '') return '';
        $t = ltrim($k, '0');
        return $t === '' ? '0' : $t;   // ignora zeros à esquerda (00002237 == 2237)
    };
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
    $norm = function ($s) { $k = strtolower(preg_replace('/[^0-9a-zA-Z]/', '', (string)$s)); $t = ltrim($k, '0'); return $t === '' ? ($k === '' ? '' : '0') : $t; };
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
function matNormalizar($s) {
    $d = preg_replace('/\D+/', '', (string)$s);
    if ($d === '') return '';
    $d = ltrim($d, '0');
    return $d === '' ? '0' : $d;   // matrícula só com zeros -> "0"
}

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
    foreach (preg_split('/[;,]/', (string)($row['matricula_sucessora'] ?? '')) as $s) { $s = trim($s); $k = matNormalizar($s); if ($k !== '' && !isset($suc[$k])) $suc[$k] = $k; }
    foreach ($novasSuc as $s) { $s = trim((string)$s); $k = matNormalizar($s); if ($k !== '' && !isset($suc[$k])) $suc[$k] = $k; }
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
"cpf": CPF ou CNPJ do(s) titular(es) atual(is), na MESMA ordem de "proprietario", separados por vírgula. IMPORTANTE: extraia SOMENTE o CPF (11 dígitos, rotulado 'CPF') ou o CNPJ (14 dígitos, rotulado 'CNPJ'). NUNCA use o RG/Identidade/documento de identidade no lugar do CPF — o RG costuma vir rotulado 'RG', 'nº', 'SSP', 'Identidade' ou com órgão emissor, tem menos dígitos e dígito verificador diferente. Se de um titular constar só o RG e não o CPF, deixe o campo VAZIO para esse titular (não invente, não use o RG),
"rel_jur": relação jurídica do titular principal por extenso (ex.: propriedade, usufruto, nua-propriedade, promessa de compra e venda),
"dat_ini": data (dd/mm/aaaa) do ATO que originou a relação jurídica atual (a data do registro/averbação da última transmissão eficaz),
"per_rel": percentual/fração do titular principal (ex.: 100%, 50%, 1/2),
"pessoas": [
   // UMA entrada por TITULAR ATUAL. Liste todos. Cada objeto:
   {
     "nome": nome completo,
     "cpf_cnpj": CPF (11 dígitos) ou CNPJ (14 dígitos) — pode conter máscara. NUNCA o RG/Identidade (rotulado 'RG'/'SSP'/'Identidade', com órgão emissor); se só houver RG, deixe vazio,
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
"memorial": transcreva a descrição do perímetro com TODOS os vértices e coordenadas, EXATAMENTE como no documento. REGRA PRINCIPAL E OBRIGATÓRIA: sempre que o documento trouxer a Longitude e a Latitude (ou Norte/Este UTM) de cada vértice, transcreva TODAS elas — nunca omita as coordenadas, mesmo que também existam azimutes e distâncias. Os formatos possíveis: (a) texto corrido começando em 'Inicia-se a descrição...' com as coordenadas de cada vértice entre parênteses (ex.: '(Longitude: -45°37'17,183", Latitude: -07°08'36,589")') — transcreva cada par Longitude/Latitude; (b) coordenadas UTM 'E ... m' e 'N ... m' em metros; (c) uma TABELA (SIGEF/INCRA ou planta) com colunas de Latitude/Longitude — transcreva cada linha mantendo a Longitude e a Latitude (ex.: 'D6B-M-10902 -46°51'49,039" -4°05'50,116"'); ou (d) uma TABELA de LEVANTAMENTO TOPOGRÁFICO com colunas UTM 'Coord. N(Y)'/'Coord. E(X)' — transcreva cada vértice PREFIXANDO os valores (ex.: 'P1 N=9.222.799,638 E=445.517,024'). ADICIONALMENTE (nunca no lugar das coordenadas): se houver azimutes e distâncias entre os pontos, inclua também cada lado numa linha própria no formato 'De P1 Para P2, azimute 285°27'21,60", distância 4,50 m'. E se o documento informar ÁREA e/ou PERÍMETRO totais, inclua-os ao final tal como aparecem (ex.: 'Área: 246,8798 m² Perímetro: 113,4541 m'). Inclua TODOS os vértices com suas coordenadas; não converta, não arredonde, não omita nenhuma coordenada
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
/* Validação de dígitos verificadores — distingue CPF/CNPJ REAL de um RG (que não passa no cálculo). */
function cpfValido($cpf) {
    $cpf = preg_replace('/\D/', '', (string)$cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) $d += (int)$cpf[$c] * (($t + 1) - $c);
        $d = ((10 * $d) % 11) % 10;
        if ((int)$cpf[$t] !== $d) return false;
    }
    return true;
}
function cnpjValido($cnpj) {
    $cnpj = preg_replace('/\D/', '', (string)$cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
    $p1 = [5,4,3,2,9,8,7,6,5,4,3,2];
    $p2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
    $s = 0; for ($i = 0; $i < 12; $i++) $s += (int)$cnpj[$i] * $p1[$i];
    $d1 = $s % 11; $d1 = ($d1 < 2) ? 0 : 11 - $d1;
    if ((int)$cnpj[12] !== $d1) return false;
    $s = 0; for ($i = 0; $i < 13; $i++) $s += (int)$cnpj[$i] * $p2[$i];
    $d2 = $s % 11; $d2 = ($d2 < 2) ? 0 : 11 - $d2;
    return (int)$cnpj[13] === $d2;
}
/* CPF (11) OU CNPJ (14) com dígitos verificadores válidos. Um RG lançado por engano NÃO passa. */
function cpfCnpjValido($doc) {
    $d = preg_replace('/\D/', '', (string)$doc);
    return (strlen($d) === 11 && cpfValido($d)) || (strlen($d) === 14 && cnpjValido($d));
}

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
            if ($doc === '' || !cpfCnpjValido($doc)) { $avisos[] = "$rotulo: CPF/CNPJ de \"$nome\" ausente/inválido — verifique (pode ter sido lido o RG). Preencher."; $doc = '00000000000'; }
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
            if ($doc === '' || !cpfCnpjValido($doc)) { $avisos[] = "$rotulo: CPF/CNPJ de \"$nome\" ausente/inválido — verifique (pode ter sido lido o RG). Preencher."; $doc = '00000000000'; }
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
            itn03_exclusivo, fora_municipio, contexto_rural, situacao, coordenadas_wgs84";
}
/* "apto para o Mapa da ONR" (mesmo critério do onr_pronto): se está pronto p/ o Mapa, está pronto p/ a carga ITN 03. */
/* Imóvel marcado como FORA do perímetro do município (não pertence ao cartório). */
function imovelForaMunicipio($r) { return trim((string)($r['fora_municipio'] ?? '')) !== ''; }

/* ===== Identificação OFFLINE do município de um ponto (base local limites_ma) ===== */
function _bboxAcumula($coords, &$b) {
    if (!is_array($coords) || empty($coords)) return;
    if (is_numeric($coords[0])) {
        $x = (float)$coords[0]; $y = (float)$coords[1];
        if ($x < $b[0]) $b[0] = $x; if ($y < $b[1]) $b[1] = $y;
        if ($x > $b[2]) $b[2] = $x; if ($y > $b[3]) $b[3] = $y; return;
    }
    foreach ($coords as $c) _bboxAcumula($c, $b);
}
function _pontoEmAnel($lat, $lng, $ring) {
    $inside = false; $n = count($ring);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = (float)$ring[$i][0]; $yi = (float)$ring[$i][1];   // [lng, lat]
        $xj = (float)$ring[$j][0]; $yj = (float)$ring[$j][1];
        $den = ($yj - $yi); if ($den == 0.0) $den = 1e-12;
        if ((($yi > $lat) !== ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / $den + $xi)) $inside = !$inside;
    }
    return $inside;
}
function _pontoEmPoligono($lat, $lng, $poly) {
    if (empty($poly) || empty($poly[0])) return false;
    if (!_pontoEmAnel($lat, $lng, $poly[0])) return false;           // fora do anel externo
    for ($k = 1; $k < count($poly); $k++) { if (_pontoEmAnel($lat, $lng, $poly[$k])) return false; } // dentro de um buraco
    return true;
}
function pontoEmGeoJson($lat, $lng, $gj) {
    if (!is_array($gj)) return false;
    $t = $gj['type'] ?? '';
    if ($t === 'FeatureCollection') { foreach (($gj['features'] ?? []) as $f) { if (pontoEmGeoJson($lat, $lng, $f)) return true; } return false; }
    if ($t === 'Feature') return pontoEmGeoJson($lat, $lng, $gj['geometry'] ?? []);
    if ($t === 'Polygon') return _pontoEmPoligono($lat, $lng, $gj['coordinates'] ?? []);
    if ($t === 'MultiPolygon') { foreach (($gj['coordinates'] ?? []) as $poly) { if (_pontoEmPoligono($lat, $lng, $poly)) return true; } return false; }
    return false;
}
/* índice {codigo: {n:nome, b:[minLng,minLat,maxLng,maxLat]}} — do arquivo, do cache, ou gerado dos GeoJSON */
function bboxIndexMA() {
    $local = __DIR__ . '/limites_ma/_bboxes.json';
    if (is_file($local)) { $d = json_decode((string)@file_get_contents($local), true); if (is_array($d) && $d) return $d; }
    $cache = __DIR__ . '/anexos/_bboxes_ma.json';
    if (is_file($cache)) { $d = json_decode((string)@file_get_contents($cache), true); if (is_array($d) && $d) return $d; }
    $dir = __DIR__ . '/limites_ma';
    if (!is_dir($dir)) return [];
    $idx = [];
    foreach (glob($dir . '/*.geojson') as $f) {
        $base = basename($f); if ($base[0] === '_') continue;
        $gj = json_decode((string)@file_get_contents($f), true);
        $feat = $gj['features'][0] ?? null; if (!$feat) continue;
        $cod = (string)($feat['properties']['CD_MUN'] ?? preg_replace('/\D/', '', $base));
        $b = [1e9, 1e9, -1e9, -1e9]; _bboxAcumula($feat['geometry']['coordinates'] ?? [], $b);
        $idx[$cod] = ['n' => (string)($feat['properties']['NM_MUN'] ?? ''), 'b' => $b];
    }
    if ($idx && is_dir(__DIR__ . '/anexos')) @file_put_contents($cache, json_encode($idx));
    return $idx;
}
/* Matrícula ENCERRADA (unificação/desmembramento total/georreferenciamento): não pode ser enviada à ONR nem à carga ITN 03. */
function imovelEncerrado($r) { return strtolower(trim((string)($r['situacao'] ?? ''))) === 'encerrada'; }

function itn03Faltam(array $r) {
    $faltam = [];
    if (imovelForaMunicipio($r)) $faltam[] = 'imóvel fora do município';
    if (imovelEncerrado($r))     $faltam[] = 'matrícula encerrada';
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
    if (imovelEncerrado($r))     $faltam[] = 'matrícula encerrada';
    if (!in_array((string)($r['tipo_imovel'] ?? ''), ['urbano','rural'], true)) $faltam[] = 'tipo (urbano/rural)';
    if (trim((string)($r['numero_matricula'] ?? '')) === '') $faltam[] = 'número da matrícula';
    if (!preg_match('#^(?:\d{6}\.\d\.\d{7}-\d{2}|\d{16})$#', trim((string)($r['cnm'] ?? '')))) $faltam[] = 'CNM válido';
    if (trim((string)($r['municipio'] ?? '')) === '') $faltam[] = 'município';
    if (trim((string)($r['uf'] ?? '')) === '') $faltam[] = 'UF';
    return $faltam;
}
function itn03ExclusivoApto(array $r) { return count(itn03ExclusivoFaltam($r)) === 0; }

/* ===================== AUTOTUTELA REGISTRAL =====================
 * Processo de autotutela registral (Prov. CNJ 195/2025, art. 440-BG do CNN/CN/CNJ-Extra,
 * incluído pelo Prov. CNJ 149/2023), cabível em casos de ALTA INDAGAÇÃO ou POTENCIAL LITÍGIO
 * entre titulares de direitos registrados/averbados, com relatório circunstanciado preliminar,
 * notificação das partes (prazo de manifestação), anuência tácita pela ausência, tentativa de
 * transação, réplica e, no impasse, remessa ao Juiz Corregedor (art. 214 da LRP). Fundamentos
 * correlatos: arts. 110, 213 e 214 da Lei 6.015/1973 (LRP) e art. 1.247 do Código Civil. */
function ensureAutotutela($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS autotutela_registral (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero VARCHAR(40) NULL,
        prenotacao VARCHAR(40) NULL,
        data_abertura DATE NULL,
        fundamento VARCHAR(20) NOT NULL DEFAULT 'litigio',
        vicio_tipo VARCHAR(40) NULL,
        objeto MEDIUMTEXT,
        matriculas TEXT,
        relatorio_preliminar MEDIUMTEXT,
        partes MEDIUMTEXT,
        prazo_dias INT NOT NULL DEFAULT 15,
        fase VARCHAR(24) NOT NULL DEFAULT 'aberto',
        decisao MEDIUMTEXT,
        resultado VARCHAR(20) NULL,
        ato_saneamento MEDIUMTEXT,
        oficial VARCHAR(180) NULL,
        imovel_id INT NULL,
        observacoes MEDIUMTEXT,
        criado_em DATETIME NULL,
        atualizado_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try { $conn->query($sql); } catch (Throwable $e) {}
    $cols = [];
    try { $r = $conn->query("SHOW COLUMNS FROM autotutela_registral"); if ($r) while ($x = $r->fetch_assoc()) $cols[$x['Field']] = true; } catch (Throwable $e) {}
    $add = ['prenotacao'=>"ADD COLUMN prenotacao VARCHAR(40) NULL",'oficial'=>"ADD COLUMN oficial VARCHAR(180) NULL",
            'ato_saneamento'=>"ADD COLUMN ato_saneamento MEDIUMTEXT",'resultado'=>"ADD COLUMN resultado VARCHAR(20) NULL",
            'imovel_id'=>"ADD COLUMN imovel_id INT NULL",'observacoes'=>"ADD COLUMN observacoes MEDIUMTEXT"];
    foreach ($add as $c => $ddl) { if (!isset($cols[$c])) { try { $conn->query("ALTER TABLE autotutela_registral $ddl"); } catch (Throwable $e) {} } }
    try { $conn->query("CREATE TABLE IF NOT EXISTS autotutela_anexos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        autotutela_id INT NOT NULL,
        parte_idx INT NOT NULL DEFAULT -1,
        tipo VARCHAR(30) NULL,
        nome_original VARCHAR(255) NULL,
        arquivo VARCHAR(255) NULL,
        mime VARCHAR(120) NULL,
        tamanho INT NULL,
        hash VARCHAR(64) NULL,
        criado_em DATETIME NULL,
        INDEX(autotutela_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
}
/* dados da serventia para o cabeçalho dos documentos (leitura defensiva de cadastro_serventia) */
function atServentiaInfo($conn) {
    $info = ['nome' => '', 'cidade' => '', 'uf' => 'MA', 'cns' => '', 'oficial' => ''];
    try {
        $r = $conn->query("SELECT * FROM cadastro_serventia ORDER BY id LIMIT 1");
        if ($r && ($row = $r->fetch_assoc())) {
            foreach ($row as $k => $v) {
                $kl = strtolower($k); $v = trim((string)$v); if ($v === '') continue;
                if ($info['nome']==='' && (strpos($kl,'serventia')!==false||strpos($kl,'denomin')!==false||strpos($kl,'razao')!==false||$kl==='nome')) $info['nome']=$v;
                if ($info['cidade']==='' && (strpos($kl,'cidade')!==false||strpos($kl,'municipio')!==false)) $info['cidade']=$v;
                if ((strpos($kl,'uf')!==false||strpos($kl,'estado')!==false) && preg_match('/^[A-Za-z]{2}$/',$v)) $info['uf']=strtoupper($v);
                if ($info['cns']==='' && strpos($kl,'cns')!==false) $info['cns']=$v;
                if ($info['oficial']==='' && (strpos($kl,'oficial')!==false||strpos($kl,'titular')!==false||strpos($kl,'responsavel')!==false)) $info['oficial']=$v;
            }
        }
    } catch (Throwable $e) {}
    if ($info['cidade']!=='' && preg_match('/^(.*?)\s*[-\/,]\s*([A-Za-z]{2})$/u', $info['cidade'], $m)) { $info['cidade']=trim($m[1]); $info['uf']=strtoupper($m[2]); }
    return $info;
}
function atProximoNumero($conn) {
    $ano = date('Y'); $seq = 1;
    try {
        $r = $conn->query("SELECT numero FROM autotutela_registral WHERE numero LIKE 'AT-$ano-%' ORDER BY id DESC LIMIT 1");
        if ($r && ($row = $r->fetch_assoc()) && preg_match('/AT-\d{4}-(\d+)/', (string)$row['numero'], $m)) $seq = ((int)$m[1]) + 1;
    } catch (Throwable $e) {}
    return 'AT-' . $ano . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}
function atFundamentoLabel($f) { return $f === 'alta_indagacao' ? 'alta indagação' : 'potencial litígio entre titulares de direitos'; }
/* Geração de texto livre via Gemini (rascunho de relatório/decisão). */
function geminiGerarTexto($cfg, $prompt) {
    if (trim((string)($cfg['api_key'] ?? '')) === '') return ['ok' => false, 'erro' => 'Chave da API do Gemini não configurada (⚙ Configurar IA).'];
    $model = $cfg['default_model'];
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($cfg['api_key']);
    $payload = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.35]];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($resp === false) return ['ok' => false, 'erro' => 'Falha de conexão com o Gemini: ' . $err];
    $j = json_decode($resp, true);
    if ($code < 200 || $code >= 300) return ['ok' => false, 'erro' => 'Gemini: ' . ($j['error']['message'] ?? ('HTTP ' . $code))];
    $txt = trim((string)($j['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    $txt = trim(preg_replace('/^```\w*|```$/m', '', $txt));
    if ($txt === '') return ['ok' => false, 'erro' => 'A IA não retornou texto.'];
    return ['ok' => true, 'texto' => $txt, 'modelo' => $model];
}
/* Anexos do procedimento de autotutela (comprovantes por parte). Reaproveita anexosDir()/?at_anexo=. */
function atAnexoSalvar($conn, $atId, $parteIdx, $bytes, $nome, $tipo, $mime) {
    $atId = (int)$atId; if ($atId <= 0 || $bytes === '' || $bytes === false) return null;
    $hash = sha1($bytes); $dir = anexosDir();
    $ext = strtolower(pathinfo((string)$nome, PATHINFO_EXTENSION)); if ($ext === '' || strlen($ext) > 5) $ext = 'bin';
    $arquivo = 'at' . $atId . '_' . date('YmdHis') . '_' . substr($hash, 0, 8) . '.' . $ext;
    if (@file_put_contents($dir . '/' . $arquivo, $bytes) === false) return null;
    $tam = strlen($bytes); $nome = mb_substr((string)$nome, 0, 250); $agora = date('Y-m-d H:i:s'); $pi = (int)$parteIdx; $tipo = mb_substr((string)$tipo, 0, 30);
    $st = $conn->prepare("INSERT INTO autotutela_anexos (autotutela_id,parte_idx,tipo,nome_original,arquivo,mime,tamanho,hash,criado_em) VALUES (?,?,?,?,?,?,?,?,?)");
    if (!$st) { @unlink($dir . '/' . $arquivo); return null; }
    $st->bind_param('iissssiss', $atId, $pi, $tipo, $nome, $arquivo, $mime, $tam, $hash, $agora);
    if (!$st->execute()) { @unlink($dir . '/' . $arquivo); return null; }
    return (int)$st->insert_id;
}
function atAnexosListar($conn, $atId) {
    $out = []; $st = $conn->prepare("SELECT id,parte_idx,tipo,nome_original,arquivo,mime,tamanho,criado_em FROM autotutela_anexos WHERE autotutela_id = ? ORDER BY id DESC");
    if (!$st) return $out; $id = (int)$atId; $st->bind_param('i', $id); $st->execute(); $rs = $st->get_result();
    while ($rs && ($r = $rs->fetch_assoc())) $out[] = $r; return $out;
}
function atAnexoObter($conn, $id) {
    $st = $conn->prepare("SELECT * FROM autotutela_anexos WHERE id = ? LIMIT 1"); if (!$st) return null;
    $id = (int)$id; $st->bind_param('i', $id); $st->execute(); $rs = $st->get_result();
    return ($rs && ($r = $rs->fetch_assoc())) ? $r : null;
}
function atAnexoExcluir($conn, $id) {
    $a = atAnexoObter($conn, $id); if (!$a) return false;
    @unlink(anexosDir() . '/' . $a['arquivo']);
    $st = $conn->prepare("DELETE FROM autotutela_anexos WHERE id = ?"); if (!$st) return false; $id = (int)$id; $st->bind_param('i', $id); return $st->execute();
}function atDataBR($d) { $d = trim((string)$d); if ($d === '' || $d === '0000-00-00') return '—'; $t = strtotime($d); return $t ? date('d/m/Y', $t) : $d; }
function atEscapa($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Gera, em PDF (saída inline), os documentos do procedimento conforme a fase/tipo solicitado. */
function gerarPdfAutotutela($conn, $id, $tipo) {
    if (file_exists(__DIR__ . '/../oficios/tcpdf/tcpdf.php')) require_once __DIR__ . '/../oficios/tcpdf/tcpdf.php';
    else require_once __DIR__ . '/tcpdf/tcpdf.php';

    $reg = null;
    try { $r = $conn->query("SELECT * FROM autotutela_registral WHERE id = " . (int)$id . " LIMIT 1"); if ($r) $reg = $r->fetch_assoc(); } catch (Throwable $e) {}
    if (!$reg) { header('Content-Type: text/plain; charset=UTF-8'); echo 'Procedimento não encontrado.'; return; }

    $serv = atServentiaInfo($conn);
    $oficial = trim((string)($reg['oficial'] ?? '')) !== '' ? $reg['oficial'] : ($serv['oficial'] !== '' ? $serv['oficial'] : 'O Oficial de Registro de Imóveis');
    $partes = json_decode((string)($reg['partes'] ?? '[]'), true); if (!is_array($partes)) $partes = [];
    $matriculas = trim((string)($reg['matriculas'] ?? ''));
    $fund = atFundamentoLabel((string)($reg['fundamento'] ?? 'litigio'));
    $cidUF = trim(($serv['cidade'] !== '' ? $serv['cidade'] : '________________') . '/' . ($serv['uf'] !== '' ? $serv['uf'] : '__'));

    $titulos = [
        'abertura'   => 'ATO DE ABERTURA DE PROCEDIMENTO DE AUTOTUTELA REGISTRAL',
        'relatorio'  => 'RELATÓRIO CIRCUNSTANCIADO PRELIMINAR',
        'notificacao'=> 'NOTIFICAÇÃO – PROCEDIMENTO DE AUTOTUTELA REGISTRAL',
        'decisao'    => 'DECISÃO E TERMO DE SANEAMENTO REGISTRAL',
    ];
    $tipo = isset($titulos[$tipo]) ? $tipo : 'abertura';
    $tituloDoc = $titulos[$tipo];

    if (!class_exists('AutotutelaPDF')) {
        class AutotutelaPDF extends TCPDF {
            public $cabServ = ''; public $cabDoc = '';
            public function Header() {
                $timbrado = __DIR__ . '/../style/img/timbrado.png';
                if (@file_exists($timbrado)) {
                    $pw = $this->getPageWidth(); $ph = $this->getPageHeight();
                    $oL=$this->lMargin;$oR=$this->rMargin;$oT=$this->tMargin;$oB=$this->bMargin;$oA=$this->AutoPageBreak;
                    $this->lMargin=0;$this->rMargin=0;$this->tMargin=0;$this->SetAutoPageBreak(false,0);
                    @$this->Image($timbrado,0,0,$pw,$ph,'','','',false,300,'',false,false,0,false,false,false);
                    $this->lMargin=$oL;$this->rMargin=$oR;$this->tMargin=$oT;$this->SetAutoPageBreak($oA,$oB);
                    $this->SetY($oT);
                } else {
                    $this->SetFont('helvetica','B',11);
                    $this->Cell(0,6,$this->cabServ,0,1,'C');
                    $this->SetFont('helvetica','',8.5);
                    $this->Cell(0,4,'Registro de Imóveis',0,1,'C');
                    $this->Ln(1); $this->SetDrawColor(150,150,150); $this->Line($this->lMargin,$this->GetY(),$this->getPageWidth()-$this->rMargin,$this->GetY()); $this->Ln(3);
                }
            }
            public function Footer() {
                $this->SetY(-13); $this->SetFont('helvetica','',7.5); $this->SetTextColor(110,110,110);
                $this->Cell(0,4,'Documento gerado pelo Atlas Vertex em '.date('d/m/Y H:i').' — Autotutela Registral (Prov. CNJ 195/2025, art. 440-BG; LRP).',0,1,'C');
                $this->Cell(0,4,'Página '.$this->getAliasNumPage().' de '.$this->getAliasNbPages(),0,0,'C');
            }
        }
    }
    $pdf = new AutotutelaPDF('P','mm','A4',true,'UTF-8',false);
    $pdf->cabServ = $serv['nome'] !== '' ? $serv['nome'] : 'OFÍCIO DE REGISTRO DE IMÓVEIS';
    $pdf->SetCreator('Atlas Vertex'); $pdf->SetAuthor($pdf->cabServ); $pdf->SetTitle($tituloDoc.' '.$reg['numero']);
    $pdf->SetMargins(20, 32, 20); $pdf->SetHeaderMargin(5); $pdf->SetFooterMargin(12); $pdf->SetAutoPageBreak(true, 18);
    $pdf->AddPage();

    $css = '<style>
        h1{font-size:12px;font-weight:bold;text-align:center;text-transform:uppercase;}
        .num{font-size:9.5px;text-align:center;color:#444;}
        .sec{font-size:10px;font-weight:bold;color:#7a0d16;text-transform:uppercase;margin-top:6px;}
        p{font-size:10.5px;line-height:1.5;text-align:justify;}
        .small{font-size:9px;color:#555;}
        table{font-size:9.5px;} td{border:0.4px solid #bbb;padding:3px;}
        .lbl{color:#555;font-weight:bold;}
        .assina{font-size:10px;text-align:center;margin-top:26px;}
    </style>';

    $h  = $css;
    $h .= '<h1>' . atEscapa($tituloDoc) . '</h1>';
    $h .= '<div class="num">Procedimento nº <b>' . atEscapa($reg['numero']) . '</b>'
        . ($reg['prenotacao'] ? ' &nbsp;·&nbsp; Prenotação nº <b>' . atEscapa($reg['prenotacao']) . '</b>' : '')
        . ' &nbsp;·&nbsp; Abertura: <b>' . atDataBR($reg['data_abertura']) . '</b></div><br>';

    $listaPartes = '';
    if ($partes) {
        $listaPartes = '<table cellspacing="0"><tr><td class="lbl" width="34%">Interessado</td><td class="lbl" width="20%">Qualificação</td><td class="lbl" width="16%">Matrícula</td><td class="lbl" width="30%">Situação</td></tr>';
        foreach ($partes as $p) {
            $sit = !empty($p['notificado']) ? ('Notificado em ' . atDataBR($p['data_notif'] ?? '')) : 'A notificar';
            $man = (string)($p['manifestacao'] ?? '');
            if ($man === 'anuencia') $sit .= ' · anuência';
            elseif ($man === 'impugnacao') $sit .= ' · impugnação';
            elseif ($man === 'sem_resposta') $sit .= ' · sem resposta (anuência tácita)';
            $listaPartes .= '<tr><td>' . atEscapa($p['nome'] ?? '') . ($p['doc']??'' ? '<br><span class="small">' . atEscapa($p['doc']) . '</span>' : '')
                . '</td><td>' . atEscapa($p['papel'] ?? $p['qualificacao'] ?? '') . '</td><td>' . atEscapa($p['matricula'] ?? '') . '</td><td>' . atEscapa($sit) . '</td></tr>';
        }
        $listaPartes .= '</table>';
    }

    if ($tipo === 'abertura') {
        $h .= '<p>' . atEscapa($oficial) . ', no uso das atribuições e do <b>poder-dever de autotutela</b> conferido ao registrador de imóveis, com fundamento no <b>art. 440-BG, inciso I, do CNN/CN/CNJ-Extra</b> (Provimento CNJ nº 149/2023, com a redação dada pelo <b>Provimento CNJ nº 195/2025</b>) e nos arts. 213 e 214 da Lei nº 6.015/1973 (LRP) e art. 1.247 do Código Civil, <b>INSTAURA</b> o presente procedimento de autotutela registral, em razão de ' . atEscapa($fund) . '.</p>';
        $h .= '<div class="sec">Objeto e fatos a apurar (art. 440-BG, II)</div><p>' . nl2br(atEscapa($reg['objeto'] ?? '')) . '</p>';
        $h .= '<div class="sec">Matrículas/transcrições atingidas</div><p>' . nl2br(atEscapa($matriculas !== '' ? $matriculas : '—')) . '</p>';
        if ($listaPartes) $h .= '<div class="sec">Partes interessadas a notificar</div>' . $listaPartes;
        $h .= '<br><p>Para garantia da <b>prioridade registral</b>, determina-se a <b>prenotação</b> deste ato no Livro de Protocolo' . ($reg['prenotacao'] ? ' (nº ' . atEscapa($reg['prenotacao']) . ')' : '') . ', bem como a <b>notificação</b> das partes interessadas para, no prazo de <b>' . (int)$reg['prazo_dias'] . ' (' . atEscapa(numberToExt((int)$reg['prazo_dias'])) . ') dias</b>, manifestarem-se, nos termos do art. 440-BG, III, do CNN/CN/CNJ-Extra.</p>';
    } elseif ($tipo === 'relatorio') {
        $h .= '<p>Este <b>relatório circunstanciado preliminar</b> é elaborado nos termos do <b>art. 440-BG, incisos I e II, do CNN/CN/CNJ-Extra</b> (Provimento CNJ nº 149/2023, com a redação do Provimento CNJ nº 195/2025), com o objetivo de relatar, de forma detalhada, o vício identificado e indicar as providências administrativas cabíveis ao seu eventual saneamento.</p>';
        $h .= '<div class="sec">Objeto</div><p>' . nl2br(atEscapa($reg['objeto'] ?? '')) . '</p>';
        $h .= '<div class="sec">Matrículas/transcrições atingidas</div><p>' . nl2br(atEscapa($matriculas !== '' ? $matriculas : '—')) . '</p>';
        $h .= '<div class="sec">Relatório do vício e providências propostas</div><p>' . nl2br(atEscapa($reg['relatorio_preliminar'] ?? '')) . '</p>';
        if ($listaPartes) $h .= '<div class="sec">Partes interessadas</div>' . $listaPartes;
    } elseif ($tipo === 'notificacao') {
        $alvo = '';
        foreach ($partes as $p) { if (!empty($p['_destacar'])) { $alvo = (string)($p['nome'] ?? ''); break; } }
        $h .= '<p>Prezado(a) Sr(a). <b>' . atEscapa($alvo !== '' ? $alvo : '________________________________') . '</b>,</p>';
        $h .= '<p>Com fundamento no <b>art. 440-BG, inciso III, do CNN/CN/CNJ-Extra</b> (Provimento CNJ nº 149/2023, com a redação do Provimento CNJ nº 195/2025), informamos que foi <b>instaurado</b> o procedimento de autotutela registral nº <b>' . atEscapa($reg['numero']) . '</b>, com vistas à análise e eventual saneamento de vício identificado nas matrículas/transcrições: ' . atEscapa($matriculas !== '' ? $matriculas : '—') . '.</p>';
        $h .= '<div class="sec">Objeto</div><p>' . nl2br(atEscapa($reg['objeto'] ?? '')) . '</p>';
        if (trim((string)($reg['relatorio_preliminar'] ?? '')) !== '') $h .= '<div class="sec">Síntese do vício apurado</div><p>' . nl2br(atEscapa($reg['relatorio_preliminar'])) . '</p>';
        $h .= '<p>Fica V.Sa. <b>notificado(a)</b> para, querendo, manifestar-se no prazo de <b>' . (int)$reg['prazo_dias'] . ' (' . atEscapa(numberToExt((int)$reg['prazo_dias'])) . ') dias</b>, a contar do recebimento desta, apresentando concordância, impugnação ou provas que entender pertinentes.</p>';
        $h .= '<p><b>Advertência:</b> nos termos do <b>art. 440-BG, III, alínea “a”</b>, a <b>ausência de manifestação</b> no prazo será interpretada como <b>anuência tácita</b> à proposta de saneamento, facultando a este Registro a prática dos atos corretivos correspondentes. Havendo impugnação e não havendo transação amigável, os titulares com direitos contraditórios serão notificados para réplica; persistindo o impasse, o feito poderá ser remetido ao <b>Juízo Corregedor competente (art. 214 da LRP)</b>.</p>';
    } else { // decisao
        $h .= '<div class="sec">Das manifestações</div>';
        if ($listaPartes) $h .= $listaPartes; else $h .= '<p>—</p>';
        $h .= '<div class="sec">Fundamentação e decisão</div><p>' . nl2br(atEscapa($reg['decisao'] ?? '')) . '</p>';
        $res = (string)($reg['resultado'] ?? '');
        if ($res === 'saneado' || trim((string)($reg['ato_saneamento'] ?? '')) !== '') {
            $h .= '<div class="sec">Atos de saneamento determinados</div><p>' . nl2br(atEscapa($reg['ato_saneamento'] ?? '')) . '</p>';
            $h .= '<p>Com base no <b>art. 440-BG, III, do CNN/CN/CNJ-Extra</b>, presente a anuência (expressa ou tácita) das partes, <b>ficam autorizados e determinados os atos de saneamento registral</b> acima descritos (retificação/averbação), nos termos dos arts. 213 e 110 da LRP.</p>';
        } elseif ($res === 'remetido') {
            $h .= '<p>Não tendo havido acordo entre os interessados e remanescendo controvérsia de <b>alta indagação</b>, <b>remetem-se os autos ao Juízo Corregedor competente</b>, na forma do <b>art. 214 da Lei nº 6.015/1973 (LRP)</b>, para deliberação.</p>';
        } elseif ($res === 'arquivado') {
            $h .= '<p>Não verificado vício a sanear, ou diante de desistência/perda de objeto, <b>determina-se o arquivamento</b> do presente procedimento.</p>';
        }
    }

    $h .= '<div class="assina">' . atEscapa($cidUF) . ', ' . date('d/m/Y') . '.<br><br>_______________________________________<br><b>' . atEscapa($oficial) . '</b><br><span class="small">Oficial de Registro de Imóveis' . ($serv['cns'] !== '' ? ' — CNS ' . atEscapa($serv['cns']) : '') . '</span></div>';

    $pdf->writeHTML($h, true, false, true, false, '');
    $nome = preg_replace('/[^A-Za-z0-9_\-]/', '_', $tipo . '_' . $reg['numero']) . '.pdf';
    $pdf->Output($nome, 'I');
}
/* extenso simples para prazos pequenos */
function numberToExt($n) {
    $u = [0=>'zero',1=>'um',2=>'dois',3=>'três',4=>'quatro',5=>'cinco',6=>'seis',7=>'sete',8=>'oito',9=>'nove',10=>'dez',
          11=>'onze',12=>'doze',13=>'treze',14=>'catorze',15=>'quinze',20=>'vinte',30=>'trinta',45=>'quarenta e cinco',60=>'sessenta',90=>'noventa']; 
    return $u[$n] ?? (string)$n;
}

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

    /* ----- Documentos da AUTOTUTELA REGISTRAL em PDF (saída binária, antes do header JSON) ----- */
    if ($_POST['acao'] === 'autotutela_pdf') {
        ensureTable($conn); ensureAutotutela($conn);
        $id   = (int)($_POST['id'] ?? 0);
        $tipo = preg_replace('/[^a-z]/', '', strtolower((string)($_POST['tipo'] ?? 'abertura')));
        gerarPdfAutotutela($conn, $id, $tipo);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');
    @ini_set('display_errors', '0'); // avisos/deprecations do PHP nunca devem vazar para dentro do JSON (corromperia a resposta)
    ensureTable($conn);
    ensureAutotutela($conn);
    $acao = $_POST['acao'];

    try {
        if ($acao === 'autotutela_listar') {
            $itens = [];
            try {
                $r = $conn->query("SELECT id,numero,prenotacao,data_abertura,fundamento,vicio_tipo,objeto,matriculas,fase,prazo_dias,resultado,partes,atualizado_em FROM autotutela_registral ORDER BY id DESC");
                if ($r) while ($row = $r->fetch_assoc()) {
                    $partes = json_decode((string)($row['partes'] ?? '[]'), true); if (!is_array($partes)) $partes = [];
                    $row['n_partes'] = count($partes);
                    $row['n_notificadas'] = count(array_filter($partes, function ($p) { return !empty($p['notificado']); }));
                    unset($row['partes']);
                    $itens[] = $row;
                }
            } catch (Throwable $e) {}
            echo json_encode(['ok' => true, 'itens' => $itens], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'autotutela_obter') {
            $id = (int)($_POST['id'] ?? 0);
            $reg = null;
            try { $r = $conn->query("SELECT * FROM autotutela_registral WHERE id = " . $id . " LIMIT 1"); if ($r) $reg = $r->fetch_assoc(); } catch (Throwable $e) {}
            if (!$reg) { echo json_encode(['ok' => false, 'erro' => 'Procedimento não encontrado.']); exit; }
            $reg['partes'] = json_decode((string)($reg['partes'] ?? '[]'), true); if (!is_array($reg['partes'])) $reg['partes'] = [];
            echo json_encode(['ok' => true, 'registro' => $reg], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'autotutela_abrir') {
            // instaura um novo procedimento (manual ou a partir de uma sobreposição detectada)
            $numero = atProximoNumero($conn);
            $fundamento = in_array(($_POST['fundamento'] ?? ''), ['alta_indagacao','litigio'], true) ? $_POST['fundamento'] : 'litigio';
            $vicio = substr(trim((string)($_POST['vicio_tipo'] ?? '')), 0, 40);
            $objeto = (string)($_POST['objeto'] ?? '');
            $matriculas = (string)($_POST['matriculas'] ?? '');
            $prenotacao = substr(trim((string)($_POST['prenotacao'] ?? '')), 0, 40);
            $prazo = (int)($_POST['prazo_dias'] ?? 15); if ($prazo < 1 || $prazo > 365) $prazo = 15;
            $partes = json_decode((string)($_POST['partes'] ?? '[]'), true); if (!is_array($partes)) $partes = [];
            $imovelId = (int)($_POST['imovel_id'] ?? 0) ?: null;
            $hoje = date('Y-m-d'); $agora = date('Y-m-d H:i:s');
            $st = $conn->prepare("INSERT INTO autotutela_registral (numero,prenotacao,data_abertura,fundamento,vicio_tipo,objeto,matriculas,partes,prazo_dias,fase,imovel_id,criado_em,atualizado_em) VALUES (?,?,?,?,?,?,?,?,?, 'aberto', ?,?,?)");
            $pj = json_encode($partes, JSON_UNESCAPED_UNICODE);
            $st->bind_param('ssssssssiiss', $numero, $prenotacao, $hoje, $fundamento, $vicio, $objeto, $matriculas, $pj, $prazo, $imovelId, $agora, $agora);
            $st->execute();
            $novoId = $conn->insert_id;
            echo json_encode(['ok' => true, 'id' => $novoId, 'numero' => $numero], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'autotutela_salvar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'ID ausente.']); exit; }
            // colunas existentes (defensivo) e atualização apenas dos campos enviados
            $existe = [];
            try { $r = $conn->query("SHOW COLUMNS FROM autotutela_registral"); if ($r) while ($x = $r->fetch_assoc()) $existe[$x['Field']] = true; } catch (Throwable $e) {}
            $map = [
                'prenotacao' => ['s', fn($v) => substr(trim((string)$v), 0, 40)],
                'fundamento' => ['s', fn($v) => in_array($v, ['alta_indagacao','litigio'], true) ? $v : 'litigio'],
                'vicio_tipo' => ['s', fn($v) => substr(trim((string)$v), 0, 40)],
                'objeto' => ['s', fn($v) => (string)$v],
                'matriculas' => ['s', fn($v) => (string)$v],
                'relatorio_preliminar' => ['s', fn($v) => (string)$v],
                'decisao' => ['s', fn($v) => (string)$v],
                'ato_saneamento' => ['s', fn($v) => (string)$v],
                'observacoes' => ['s', fn($v) => (string)$v],
                'oficial' => ['s', fn($v) => substr(trim((string)$v), 0, 180)],
                'fase' => ['s', fn($v) => substr(preg_replace('/[^a-z_]/', '', strtolower((string)$v)), 0, 24)],
                'resultado' => ['s', fn($v) => substr(trim((string)$v), 0, 20)],
                'prazo_dias' => ['i', fn($v) => max(1, min(365, (int)$v))],
                'data_abertura' => ['s', fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? $v : date('Y-m-d')],
            ];
            $sets = []; $types = ''; $vals = [];
            foreach ($map as $campo => $def) {
                if (!array_key_exists($campo, $_POST) || !isset($existe[$campo])) continue;
                $sets[] = "$campo = ?"; $types .= $def[0]; $vals[] = $def[1]($_POST[$campo]);
            }
            if (array_key_exists('partes', $_POST) && isset($existe['partes'])) {
                $partes = json_decode((string)$_POST['partes'], true); if (!is_array($partes)) $partes = [];
                $sets[] = "partes = ?"; $types .= 's'; $vals[] = json_encode($partes, JSON_UNESCAPED_UNICODE);
            }
            $sets[] = "atualizado_em = ?"; $types .= 's'; $vals[] = date('Y-m-d H:i:s');
            if (!$sets) { echo json_encode(['ok' => false, 'erro' => 'Nada para salvar.']); exit; }
            $types .= 'i'; $vals[] = $id;
            $st = $conn->prepare("UPDATE autotutela_registral SET " . implode(', ', $sets) . " WHERE id = ?");
            $st->bind_param($types, ...$vals);
            $st->execute();
            echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'autotutela_excluir') {
            $id = (int)($_POST['id'] ?? 0);
            try { $st = $conn->prepare("DELETE FROM autotutela_registral WHERE id = ?"); $st->bind_param('i', $id); $st->execute(); } catch (Throwable $e) {}
            try { $st = $conn->prepare("DELETE FROM autotutela_anexos WHERE autotutela_id = ?"); $st->bind_param('i', $id); $st->execute(); } catch (Throwable $e) {}
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'autotutela_ia') {
            // Rascunho de relatório preliminar / decisão via IA (Gemini), a partir dos dados do formulário.
            $alvo = (($_POST['alvo'] ?? '') === 'decisao') ? 'decisao' : 'relatorio';
            $fund = atFundamentoLabel((string)($_POST['fundamento'] ?? 'litigio'));
            $vicioMap = ['sobreposicao'=>'sobreposição de área','duplicidade'=>'duplicidade de matrícula','multiplicidade'=>'multiplicidade de matrículas','erro_material'=>'erro material na matrícula','georref_erro'=>'erro na descrição georreferenciada','serventia_incompetente'=>'serventia territorialmente incompetente','outro'=>'vício registral'];
            $vicio = $vicioMap[(string)($_POST['vicio_tipo'] ?? '')] ?? 'vício registral';
            $objeto = trim((string)($_POST['objeto'] ?? ''));
            $matriculas = trim((string)($_POST['matriculas'] ?? ''));
            $relatorio = trim((string)($_POST['relatorio_preliminar'] ?? ''));
            $partes = json_decode((string)($_POST['partes'] ?? '[]'), true); if (!is_array($partes)) $partes = [];
            $pl = [];
            foreach ($partes as $p) {
                $m = (string)($p['manifestacao'] ?? '');
                $ml = $m === 'anuencia' ? 'anuência expressa' : ($m === 'impugnacao' ? 'impugnação' : ($m === 'sem_resposta' ? 'sem resposta (anuência tácita)' : 'sem manifestação registrada'));
                $pl[] = '- ' . trim(($p['nome'] ?? '(sem nome)') . ' (mat. ' . ($p['matricula'] ?? '—') . ', ' . ($p['papel'] ?? 'titular') . '): ' . $ml . (trim((string)($p['manif_texto'] ?? '')) !== '' ? ' — ' . $p['manif_texto'] : ''));
            }
            $partesTxt = $pl ? implode("\n", $pl) : '(sem partes cadastradas)';
            $base = "Você é o Oficial de Registro de Imóveis conduzindo um PROCESSO DE AUTOTUTELA REGISTRAL na via administrativo-extrajudicial, com fundamento no art. 440-BG do CNN/CN/CNJ-Extra (Provimento CNJ nº 149/2023, com a redação do Provimento CNJ nº 195/2025) e nos arts. 110, 213 e 214 da Lei 6.015/1973 (LRP) e art. 1.247 do Código Civil. Use linguagem técnico-registral, formal e impessoal, em português do Brasil. NÃO invente fatos, nomes, áreas ou números não informados; quando faltar dado, use a lacuna [____]. Não use markdown, títulos com asteriscos nem listas com marcadores; escreva em parágrafos corridos.\n\nDADOS DO PROCEDIMENTO:\n- Tipo de vício: $vicio\n- Fundamento: $fund\n- Matrículas/transcrições atingidas: " . ($matriculas !== '' ? $matriculas : '[____]') . "\n- Objeto e fatos a apurar: " . ($objeto !== '' ? $objeto : '[____]') . "\n- Partes interessadas e manifestações:\n$partesTxt\n";
            if ($alvo === 'relatorio') {
                $prompt = $base . "\nTAREFA: redija o RELATÓRIO CIRCUNSTANCIADO PRELIMINAR (art. 440-BG, I e II), descrevendo de forma detalhada o vício material identificado, sua extensão/origem provável e as providências administrativas de saneamento propostas (ex.: retificação de memorial, averbação de exclusão de área, unificação/encerramento), sem decidir o mérito. Entre 2 e 4 parágrafos.";
            } else {
                $prompt = $base . "- Síntese do vício (relatório preliminar): " . ($relatorio !== '' ? $relatorio : '[____]') . "\n\nTAREFA: redija a DECISÃO FUNDAMENTADA do oficial, apreciando as manifestações das partes (considerando a anuência expressa ou tácita e eventuais impugnações) e concluindo por uma destas hipóteses, conforme os dados: (a) autorizar os atos de saneamento (retificação/averbação) havendo anuência; (b) remeter os autos ao Juízo Corregedor competente (art. 214 da LRP) em caso de impasse de alta indagação; ou (c) arquivar, se não verificado vício. Entre 2 e 4 parágrafos.";
            }
            $r = geminiGerarTexto(geminiConfigLer(), $prompt);
            if (empty($r['ok'])) { echo json_encode(['ok' => false, 'erro' => $r['erro']], JSON_UNESCAPED_UNICODE); exit; }
            echo json_encode(['ok' => true, 'texto' => $r['texto'], 'modelo' => $r['modelo']], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'autotutela_anexo_listar') {
            echo json_encode(['ok' => true, 'anexos' => atAnexosListar($conn, (int)($_POST['id'] ?? 0))], JSON_UNESCAPED_UNICODE); exit;
        }
        if ($acao === 'autotutela_anexo_upload') {
            $id = (int)($_POST['id'] ?? 0); $parteIdx = (int)($_POST['parte_idx'] ?? -1);
            $tipo = preg_replace('/[^a-z_]/', '', strtolower((string)($_POST['tipo'] ?? 'outro')));
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Salve o procedimento antes de anexar.']); exit; }
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) { echo json_encode(['ok' => false, 'erro' => 'Falha no upload do arquivo.']); exit; }
            if ($_FILES['file']['size'] > 20 * 1024 * 1024) { echo json_encode(['ok' => false, 'erro' => 'Arquivo acima de 20 MB.']); exit; }
            $bytes = @file_get_contents($_FILES['file']['tmp_name']);
            $mime = function_exists('mime_content_type') ? (@mime_content_type($_FILES['file']['tmp_name']) ?: null) : null;
            $aid = atAnexoSalvar($conn, $id, $parteIdx, $bytes, $_FILES['file']['name'], $tipo, $mime);
            if (!$aid) { echo json_encode(['ok' => false, 'erro' => 'Não foi possível salvar o anexo.']); exit; }
            echo json_encode(['ok' => true, 'anexos' => atAnexosListar($conn, $id)], JSON_UNESCAPED_UNICODE); exit;
        }
        if ($acao === 'autotutela_anexo_excluir') {
            $aid = (int)($_POST['anexo_id'] ?? 0); $id = (int)($_POST['id'] ?? 0);
            atAnexoExcluir($conn, $aid);
            echo json_encode(['ok' => true, 'anexos' => atAnexosListar($conn, $id)], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'ibge_municipios') {
            // aceita UF por sigla (MA) ou por código IBGE do estado (21) — ambos funcionam na API
            $uf = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['uf'] ?? '')));
            if ($uf === '') { echo json_encode(['ok' => false, 'erro' => 'UF não informada.']); exit; }
            // 1) base LOCAL (offline) — Maranhão (não depende do IBGE)
            $localLista = __DIR__ . '/limites_ma/_municipios.json';
            if (($uf === 'MA' || $uf === '21') && is_file($localLista)) {
                $j = @file_get_contents($localLista);
                $d = $j ? json_decode($j, true) : null;
                if (is_array($d) && !empty($d['municipios'])) {
                    echo json_encode(['ok' => true, 'municipios' => $d['municipios'], 'fonte' => 'local'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            // 2) IBGE (online)
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

        if ($acao === 'municipio_no_ponto') {
            // Identifica OFFLINE o município de um ponto (lat,lng) pela base local — usado para
            // descobrir o município vizinho de imóveis fora do limite / que ultrapassam.
            $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
            $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
            if ($lat === null || $lng === null) { echo json_encode(['ok' => false, 'erro' => 'lat/lng ausentes.']); exit; }
            $idx = bboxIndexMA();
            if (empty($idx)) { echo json_encode(['ok' => false, 'erro' => 'Base local de municípios indisponível.']); exit; }
            $dir = __DIR__ . '/limites_ma';
            foreach ($idx as $cod => $info) {
                $b = $info['b'];
                if ($lng < $b[0] || $lng > $b[2] || $lat < $b[1] || $lat > $b[3]) continue; // descarta pelo bounding box
                $f = $dir . '/' . $cod . '.geojson';
                if (!is_file($f)) continue;
                $gj = json_decode((string)@file_get_contents($f), true);
                if (pontoEmGeoJson($lat, $lng, $gj)) {
                    echo json_encode(['ok' => true, 'municipio' => $info['n'], 'codigo' => $cod], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            echo json_encode(['ok' => true, 'municipio' => null]); // não está em nenhum município da base (provável outro estado)
            exit;
        }

        if ($acao === 'ibge_malha') {
            $mun = preg_replace('/\D/', '', (string)($_POST['municipio'] ?? ''));
            if ($mun === '') { echo json_encode(['ok' => false, 'erro' => 'Município não informado.']); exit; }
            // 1) base LOCAL (offline): perímetro do município a partir dos arquivos do IBGE baixados.
            $localFile = __DIR__ . '/limites_ma/' . $mun . '.geojson';
            if (is_file($localFile)) {
                $geo = @file_get_contents($localFile);
                $gj = $geo ? json_decode($geo, true) : null;
                if (is_array($gj) && isset($gj['type'])) {
                    echo json_encode(['ok' => true, 'geojson' => $gj, 'fonte' => 'local'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            // 2) IBGE (online) + cache
            $q = (int)($_POST['qualidade'] ?? 4); if ($q < 1 || $q > 4) $q = 4;
            $cacheDir = __DIR__ . '/anexos';
            $cacheFile = $cacheDir . '/limite_' . $mun . '_q' . $q . '.geojson';
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
            }
            $deCache = false;
            if ($geo === false || $geo === '') {
                // IBGE inacessível: usa o limite salvo em cache de uma carga anterior, se houver
                if (is_file($cacheFile)) { $geo = @file_get_contents($cacheFile); $deCache = ($geo !== false && $geo !== ''); }
                if (!$deCache) {
                    echo json_encode(['ok' => false, 'erro' => 'Falha ao obter o limite no IBGE: ' . $err
                        . '. O servidor não conseguiu acessar servicodados.ibge.gov.br (porta 443) — verifique a conexão/firewall do servidor, ou aguarde, pois após a 1ª carga bem-sucedida o limite fica em cache.']); exit;
                }
            }
            $gj = json_decode($geo, true);
            if (!is_array($gj) || !isset($gj['type'])) { echo json_encode(['ok' => false, 'erro' => 'GeoJSON inválido do IBGE.']); exit; }
            if (!$deCache) { // veio do IBGE: salva em cache p/ próximas cargas / quedas do IBGE
                if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
                @file_put_contents($cacheFile, $geo);
            }
            echo json_encode(['ok' => true, 'geojson' => $gj, 'cache' => $deCache], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'limite_kml') {
            // Carrega o limite do município a partir de um KML enviado (sem depender do IBGE).
            $kml = '';
            if (isset($_FILES['arquivo']) && is_uploaded_file($_FILES['arquivo']['tmp_name'] ?? '')) {
                $kml = (string)file_get_contents($_FILES['arquivo']['tmp_name']);
            } elseif (isset($_POST['kml'])) { $kml = (string)$_POST['kml']; }
            if (trim($kml) === '') { echo json_encode(['ok' => false, 'erro' => 'Envie um arquivo KML do limite.']); exit; }
            $pm = parseKml($kml);
            if (empty($pm)) { echo json_encode(['ok' => false, 'erro' => 'Nenhum polígono encontrado no KML.']); exit; }
            $multi = [];
            foreach ($pm as $p) {
                $ring = [];
                foreach ($p['pts'] as $pt) { $ring[] = [(float)$pt[1], (float)$pt[0]]; } // GeoJSON: [lng, lat]
                $n = count($ring);
                if ($n >= 3) {
                    if ($ring[0][0] !== $ring[$n - 1][0] || $ring[0][1] !== $ring[$n - 1][1]) $ring[] = $ring[0];
                    $multi[] = [$ring];
                }
            }
            if (empty($multi)) { echo json_encode(['ok' => false, 'erro' => 'KML sem anéis válidos.']); exit; }
            $gj = ['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'properties' => new stdClass(), 'geometry' => ['type' => 'MultiPolygon', 'coordinates' => $multi]]]];
            $nome = trim((string)($_POST['nome'] ?? '')) !== '' ? trim((string)$_POST['nome']) : 'limite (KML)';
            $cacheDir = __DIR__ . '/anexos';
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
            @file_put_contents($cacheDir . '/limite_manual.geojson', json_encode($gj, JSON_UNESCAPED_UNICODE));
            @file_put_contents($cacheDir . '/limite_manual.nome', $nome);
            echo json_encode(['ok' => true, 'geojson' => $gj, 'nome' => $nome], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'limite_cache') {
            // Devolve o limite salvo manualmente (KML) anteriormente, se houver — usado como fallback quando o IBGE está fora.
            $cacheDir = __DIR__ . '/anexos';
            $f = $cacheDir . '/limite_manual.geojson';
            if (!is_file($f)) { echo json_encode(['ok' => false, 'erro' => 'Nenhum limite salvo por KML.']); exit; }
            $geo = @file_get_contents($f);
            $gj = $geo ? json_decode($geo, true) : null;
            if (!is_array($gj)) { echo json_encode(['ok' => false, 'erro' => 'Cache de limite inválido.']); exit; }
            $nome = @file_get_contents($cacheDir . '/limite_manual.nome');
            echo json_encode(['ok' => true, 'geojson' => $gj, 'nome' => trim((string)($nome ?: 'limite salvo'))], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'processar') {
            $memorial = isset($_POST['memorial']) ? (string)$_POST['memorial'] : '';
            list($refLat, $refLng) = refRegiaoImoveis($conn);
            $data = buildGeoData($memorial, $refLat, $refLng);
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'analisar_coords') {
            $memorial = isset($_POST['memorial']) ? (string)$_POST['memorial'] : '';
            echo json_encode(analisarCoordenadas($memorial), JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'analisar_vertex') {
            $memorial = isset($_POST['memorial']) ? (string)$_POST['memorial'] : '';
            echo json_encode(analisarMemorialVertex($memorial), JSON_UNESCAPED_UNICODE);
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
            if ($numMatricula !== '') $numMatricula = preg_replace('/^0+(?=\d)/', '', $numMatricula); // ignora zeros à esquerda
            $proprietario  = trim(isset($_POST['proprietario']) ? (string)$_POST['proprietario'] : '');
            $cpf           = trim(isset($_POST['cpf']) ? (string)$_POST['cpf'] : '');
            $tipoImovel    = ($_POST['tipo_imovel'] ?? '') === 'rural' ? 'rural' : (($_POST['tipo_imovel'] ?? '') === 'urbano' ? 'urbano' : '');
            $isProjeto     = ((int)($_POST['is_projeto'] ?? 0)) ? 1 : 0; // imóvel de PROJETO (sem matrícula)

            // Traçado escolhido no laudo de coordenadas (transcrito x corrigido). Quando
            // presente, sobrescreve a geometria — inclusive de um registro já existente.
            $geoOverride = trim((string)($_POST['geo_wgs84'] ?? ''));
            $geoOverrideData = ($geoOverride !== '') ? buildGeoDataFromWgs84($geoOverride) : null;
            if ($geoOverrideData && empty($geoOverrideData['ok'])) $geoOverrideData = null;

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
            if ($numMatricula !== '' && !$isProjeto) {
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
                    // traçado escolhido no laudo de coordenadas: ATUALIZA a geometria gravada
                    if ($geoOverrideData) {
                        $sets[] = 'num_vertices=?';       $vals[] = $geoOverrideData['num_vertices'];       $types .= 'i';
                        $sets[] = 'area_ha=?';            $vals[] = $geoOverrideData['area_ha'];            $types .= 'd';
                        $sets[] = 'perimetro_m=?';        $vals[] = $geoOverrideData['perimetro_m'];        $types .= 'd';
                        $sets[] = 'centro_lat=?';         $vals[] = $geoOverrideData['centro_lat'];         $types .= 'd';
                        $sets[] = 'centro_lng=?';         $vals[] = $geoOverrideData['centro_lng'];         $types .= 'd';
                        $sets[] = 'coordenadas_wgs84=?';  $vals[] = $geoOverrideData['coordenadas_wgs84'];  $types .= 's';
                        $sets[] = 'coordenadas_utm=?';    $vals[] = $geoOverrideData['coordenadas_utm'];    $types .= 's';
                    }
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
                    // se a matrícula já existia mas SEM coordenadas (ex.: exclusiva ITN 03),
                    // o KML/memorial agora COMPLEMENTA a geometria e a mapeia.
                    $mapeadoDup = null;
                    if (!imovelTemGeo($conn, $idExistente) && trim($fonte) !== '') {
                        $mp = mapearImovelComGeo($conn, $idExistente, $origem, $fonte);
                        if (!empty($mp['ok'])) {
                            $geoMp = $mp['geo'];
                            inconsGravar($conn, $idExistente, array_merge($incDup, detectarInconsistenciasGeo($geoMp, $origem, ($origem === 'memorial' ? $fonte : ''))));
                            $mapeadoDup = ['num_vertices' => $geoMp['num_vertices'], 'area_ha' => $geoMp['area_ha']];
                        }
                    }
                    echo json_encode([
                        'ok' => true, 'existe' => true, 'atualizado' => true, 'criado' => false,
                        'id' => $idExistente, 'imovel_id' => $imovelId, 'inconsistencias' => array_values($incDup), 'mapeado' => $mapeadoDup,
                        'mensagem' => $mapeadoDup
                            ? ('A matrícula ' . $numMatricula . ' (que estava só na ITN 03) foi MAPEADA com ' . $mapeadoDup['num_vertices'] . ' vértices (' . number_format($mapeadoDup['area_ha'], 4, ',', '.') . ' ha) — agora aparece no mapa.')
                            : ($geoOverrideData
                                ? ('A matrícula ' . $numMatricula . ' foi ATUALIZADA com o traçado escolhido: ' . $geoOverrideData['num_vertices'] . ' vértices, ' . number_format($geoOverrideData['area_ha'], 4, ',', '.') . ' ha.')
                                : ('A matrícula ' . $numMatricula . ' já estava cadastrada — as informações foram complementadas, sem duplicar o polígono no mapa.'))
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            // Geometria escolhida explicitamente pelo usuário no laudo de coordenadas:
            // grava exatamente o traçado selecionado (corrigido x transcrito).
            $data = $geoOverrideData ? $geoOverrideData : processarFonte($origem, $fonte);
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
            $novoId = inserirMemorial($conn, $identificador, $tipo, $origem, $imovelId, $fonte, $data, $numMatricula, $proprietario, $cpf, $tipoImovel, $isProjeto);

            // grava também os campos ONR enviados junto, se houver
            salvarCamposOnr($conn, $novoId, $_POST);

            // arquiva o próprio KML como anexo (para conferência/reprocessamento posterior)
            if ($origem === 'kml' && $fonte !== '') {
                $nomeArq = trim((string)($_POST['nome_arquivo'] ?? ''));
                if ($nomeArq === '') $nomeArq = ($identificador !== '' ? $identificador : 'imovel') . '.kml';
                anexoSalvarBytes($conn, $novoId, $fonte, $nomeArq, 'kml', 'application/vnd.google-earth.kml+xml');
            }

            // inconsistências: geometria + (se KML) nome do placemark interno
            $incList = detectarInconsistenciasGeo($data, $origem, ($origem === 'memorial' ? $fonte : ''));
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
            if ($s !== '') $s = implode(', ', matriculasLimpar($s)); // sem zeros à esquerda e sem repetidas
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

        if ($acao === 'marcar_parcial') {
            // Marca/desmarca o imóvel como "ultrapassa o limite" (parte em município vizinho).
            // 'parcial' = JSON {municipio,vizinho,dentro_ha,dentro_pct,fora_ha,fora_pct} ou vazio para limpar.
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Imóvel inválido.']); exit; }
            $raw = trim((string)($_POST['parcial'] ?? ''));
            $val = null;
            if ($raw !== '') { $dec = json_decode($raw, true); if (is_array($dec)) $val = json_encode($dec, JSON_UNESCAPED_UNICODE); }
            $stmt = $conn->prepare("UPDATE memoriais_mapeados SET parcial_json = ? WHERE id = ?");
            if ($stmt) { $stmt->bind_param('si', $val, $id); $stmt->execute(); }
            echo json_encode(['ok' => true, 'id' => $id, 'parcial' => $val], JSON_UNESCAPED_UNICODE);
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
            $isProjeto = ((int)($_POST['is_projeto'] ?? 0)) ? 1 : 0; // PROJETO (usucapião etc.): não exige matrícula
            if ($matricula === '') $matricula = preg_replace('/\.pdf$/i', '', (string)$_FILES['pdf']['name']);
            $matricula = trim($matricula);
            if ($matricula === '' && !$isProjeto) { echo json_encode(['ok' => false, 'erro' => 'Nomeie o PDF com o número da matrícula (ex.: 2470.pdf).']); exit; }
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
            if (!$isProjeto && ($matricula === '' || !preg_match('/\d/', $matricula))) {
                echo json_encode(['ok' => false, 'erro' => 'Não foi possível identificar o número da matrícula (nem no nome do arquivo nem no documento). Renomeie o PDF com o número da matrícula — ou use a base de PROJETOS para imóveis sem matrícula (ex.: usucapião).']);
                exit;
            }
            $matricula = preg_replace('/^0+(?=\d)/', '', $matricula); // ignora zeros à esquerda (00000356 == 356) — evita registro duplicado

            // ---- PROJETO (usucapião etc.): cadastra na base de projetos, SEM exigir matrícula ----
            if ($isProjeto) {
                $memorial = (string)($d['memorial'] ?? '');
                list($refLat, $refLng) = refRegiaoImoveis($conn);
                $geo = buildGeoData($memorial, $refLat, $refLng);
                if (empty($geo['ok'])) {
                    $legs = extractTraverseLegs($memorial);
                    $motivo = (count($legs) >= 3)
                        ? 'o memorial descreve o perímetro por azimutes e distâncias a partir de marcos, sem coordenadas geográficas'
                        : 'não foi possível extrair as coordenadas do memorial (Long/Lat em GMS ou UTM E/N)';
                    echo json_encode(['ok' => false, 'erro' => 'Não foi possível mapear o projeto: ' . $motivo . '. O projeto precisa de coordenadas para ser posicionado e comparado com as matrículas — informe um memorial com os vértices.']);
                    exit;
                }
                $identificador = trim((string)($d['nome_imo'] ?? ''));
                if ($identificador === '') $identificador = preg_replace('/\.pdf$/i', '', (string)$_FILES['pdf']['name']);
                $identificador = trim($identificador); if ($identificador === '') $identificador = 'Projeto sem identificação';
                $tipoImovel = (stripos((string)($d['tipo_imovel'] ?? ''), 'rural') !== false) ? 'rural' : ((stripos((string)($d['tipo_imovel'] ?? ''), 'urban') !== false) ? 'urbano' : '');
                $pessoasNovo = qualificacaoNormalizar($d['pessoas'] ?? []);
                qualificacaoDerivarFlat($d, $pessoasNovo);
                $proprietario = trim((string)($d['proprietario'] ?? ''));
                $cpf = trim((string)($d['cpf'] ?? ''));
                // projeto normalmente NÃO tem matrícula; se o documento trouxe um número, guarda como referência
                $matProj = (preg_match('/\d/', (string)$matricula)) ? preg_replace('/\D/', '', (string)$matricula) : '';
                $tipoIdent = ($matProj !== '') ? 'matricula' : 'nome';
                $novoId = inserirMemorial($conn, $identificador, $tipoIdent, 'memorial', null, $memorial, $geo, $matProj, $proprietario, $cpf, $tipoImovel, 1);
                qualificacaoGravar($conn, $novoId, $pessoasNovo);
                $anexoId = anexoSalvarBytes($conn, $novoId, $pdfBytes, (string)$_FILES['pdf']['name'], anexoTipo((string)$_FILES['pdf']['name'], 'application/pdf', $d), 'application/pdf');
                $incPdf = detectarInconsistenciasPdf($d, $geo);
                inconsGravar($conn, $novoId, $incPdf);
                $preenchidos = array_values(array_filter(array_keys($d), fn($k) => $k !== 'memorial' && !is_array($d[$k] ?? null) && trim((string)($d[$k] ?? '')) !== ''));
                echo json_encode([
                    'ok' => true, 'existe' => false, 'criado' => true, 'is_projeto' => true, 'id' => $novoId,
                    'matricula' => $matProj, 'modelo' => $r['modelo'],
                    'num_vertices' => $geo['num_vertices'], 'area_ha' => $geo['area_ha'], 'campos' => $preenchidos,
                    'vertices_corrigidos' => $geo['vertices_corrigidos'] ?? [], 'aviso_geometria' => $geo['aviso_geometria'] ?? '',
                    'inconsistencias' => array_values($incPdf),
                    'laudo' => laudoSeDiscrepante($memorial),
                    'mensagem' => 'Projeto "' . $identificador . '" cadastrado e mapeado com ' . $geo['num_vertices'] . ' vértices (' . number_format($geo['area_ha'], 4, ',', '.') . ' ha)'
                        . ($matProj !== '' ? (', ref. matrícula ' . $matProj) : '') . '. ' . count($preenchidos) . ' campo(s) preenchido(s). PDF arquivado.'
                        . (!empty($geo['aviso_geometria']) ? ' ' . $geo['aviso_geometria'] : '')
                ], JSON_UNESCAPED_UNICODE);
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
                    'laudo' => laudoSeDiscrepante((string)($d['memorial'] ?? '')),
                    'mensagem' => 'Matrícula ' . $matricula . ' já cadastrada — ' . count($preenchidos) . ' campo(s) complementado(s). PDF arquivado.' . cicloVidaResumo($cv)
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // NÃO EXISTE -> cadastra extraindo as coordenadas do memorial
            $memorial = (string)($d['memorial'] ?? '');
            list($refLat, $refLng) = refRegiaoImoveis($conn);
            $geo = buildGeoData($memorial, $refLat, $refLng);
            // Sem coordenadas no memorial -> NÃO falha: cadastra como matrícula EXCLUSIVA da ITN 03 (sem mapa).
            $semCoord = empty($geo['ok']);
            $motivoSemCoord = '';
            if ($semCoord) {
                $legs = extractTraverseLegs($memorial);
                if (count($legs) >= 3) {
                    $tot = array_sum(array_column($legs, 'dist'));
                    $motivoSemCoord = 'o memorial descreve o perímetro por azimutes e distâncias a partir de marcos físicos (' . count($legs) . ' segmentos, ~' . number_format($tot, 0, ',', '.') . ' m), sem coordenadas geográficas';
                } else {
                    $motivoSemCoord = 'não foi possível extrair coordenadas do memorial (Long/Lat em GMS ou UTM E/N)';
                }
            }
            $identificador = trim((string)($d['nome_imo'] ?? '')); if ($identificador === '') $identificador = $matricula;
            $tipoImovel = (stripos((string)($d['tipo_imovel'] ?? ''), 'rural') !== false) ? 'rural' : ((stripos((string)($d['tipo_imovel'] ?? ''), 'urban') !== false) ? 'urbano' : '');
            $pessoasNovo = qualificacaoNormalizar($d['pessoas'] ?? []);
            qualificacaoDerivarFlat($d, $pessoasNovo); // proprietario/cpf/rel_jur/dat_ini/per_rel a partir dos titulares
            $proprietario = trim((string)($d['proprietario'] ?? ''));
            $cpf = trim((string)($d['cpf'] ?? ''));
            $imovelId = findImovelIdByMatricula($conn, $matricula);
            if ($semCoord) {
                // EXCLUSIVA da ITN 03: sem coordenadas/mapa, apenas dados para a carga
                $areaVal = (isset($d['area_ha']) && trim((string)$d['area_ha']) !== '') ? (float)str_replace(',', '.', (string)$d['area_ha']) : null;
                $dataExc = ['num_vertices' => null, 'area_ha' => $areaVal, 'perimetro_m' => null,
                            'centro_lat' => null, 'centro_lng' => null, 'coordenadas_wgs84' => null, 'coordenadas_utm' => null];
                $novoId = inserirMemorial($conn, $identificador, 'matricula', 'itn03', $imovelId, null, $dataExc, $matricula, $proprietario, $cpf, $tipoImovel);
                if ($novoId) $conn->query("UPDATE memoriais_mapeados SET itn03_exclusivo = 1 WHERE id = " . (int)$novoId);
            } else {
                $novoId = inserirMemorial($conn, $identificador, 'matricula', 'memorial', $imovelId, $memorial, $geo, $matricula, $proprietario, $cpf, $tipoImovel);
            }
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
            $incPdf = detectarInconsistenciasPdf($d, $semCoord ? null : $geo);
            inconsGravar($conn, $novoId, $incPdf);
            $cv = aplicarCicloVida($conn, $novoId, $matricula, $d['ciclo_vida'] ?? []);
            $preenchidos = array_values(array_filter(array_keys($d), fn($k) => $k !== 'memorial' && !is_array($d[$k] ?? null) && trim((string)($d[$k] ?? '')) !== ''));
            if ($semCoord) {
                echo json_encode([
                    'ok' => true, 'existe' => false, 'criado' => true, 'itn03_exclusivo' => true, 'id' => $novoId, 'matricula' => $matricula, 'modelo' => $r['modelo'],
                    'campos' => $preenchidos, 'anexo_id' => $anexoId, 'inconsistencias' => array_values($incPdf), 'ciclo_vida' => $cv,
                    'mensagem' => 'Matrícula ' . $matricula . ' cadastrada como EXCLUSIVA da ITN 03 (sem mapa), pois ' . $motivoSemCoord . '. ' . count($preenchidos) . ' campo(s) preenchido(s). PDF arquivado.' . cicloVidaResumo($cv)
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'ok' => true, 'existe' => false, 'criado' => true, 'id' => $novoId, 'matricula' => $matricula, 'modelo' => $r['modelo'],
                    'num_vertices' => $geo['num_vertices'], 'area_ha' => $geo['area_ha'], 'campos' => $preenchidos,
                    'vertices_corrigidos' => $geo['vertices_corrigidos'] ?? [],
                    'aviso_geometria' => $geo['aviso_geometria'] ?? '', 'inconsistencias' => array_values($incPdf),
                    'ciclo_vida' => $cv,
                    'laudo' => laudoSeDiscrepante($memorial),
                    'mensagem' => 'Matrícula ' . $matricula . ' cadastrada e mapeada com ' . $geo['num_vertices'] . ' vértices (' . number_format($geo['area_ha'], 4, ',', '.') . ' ha). ' . count($preenchidos) . ' campo(s) preenchido(s).'
                        . (!empty($geo['aviso_geometria']) ? ' ' . $geo['aviso_geometria'] : '') . cicloVidaResumo($cv)
                ], JSON_UNESCAPED_UNICODE);
            }
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
            if ($numMatricula !== '') $numMatricula = preg_replace('/^0+(?=\d)/', '', $numMatricula); // ignora zeros à esquerda
            $proprietario  = trim((string)($_POST['proprietario'] ?? ''));
            $cpf           = trim((string)($_POST['cpf'] ?? ''));
            $tipoImovel    = ($_POST['tipo_imovel'] ?? '') === 'rural' ? 'rural' : (($_POST['tipo_imovel'] ?? '') === 'urbano' ? 'urbano' : '');
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Imóvel inválido.']); exit; }
            if ($identificador === '' && $numMatricula !== '') $identificador = $numMatricula;
            if ($identificador === '') { echo json_encode(['ok' => false, 'erro' => 'Informe a identificação ou a matrícula.']); exit; }

            // não permite atribuir uma matrícula que já pertence a OUTRO imóvel,
            // mas só verifica quando a matrícula está REALMENTE sendo alterada (editar os
            // demais campos de um imóvel existente não pode ser bloqueado por duplicata pré-existente).
            if ($numMatricula !== '') {
                $matAtualReg = '';
                $rsAtual = $conn->query("SELECT numero_matricula FROM memoriais_mapeados WHERE id = " . (int)$id . " LIMIT 1");
                if ($rsAtual && ($rowA = $rsAtual->fetch_assoc())) $matAtualReg = matNormalizar($rowA['numero_matricula'] ?? '');
                if (matNormalizar($numMatricula) !== $matAtualReg) {   // está mudando o número da matrícula
                    $dono = acharMemorialPorMatricula($conn, $numMatricula);
                    if ($dono && $dono !== $id) {
                        echo json_encode(['ok' => false, 'erro' => 'Já existe outro imóvel cadastrado com a matrícula ' . $numMatricula . ' (registro #' . $dono . '). Não é possível duplicar o número da matrícula.']);
                        exit;
                    }
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
            if ($numMatricula !== '') $numMatricula = preg_replace('/^0+(?=\d)/', '', $numMatricula); // ignora zeros à esquerda
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
            // Resolve o CÓDIGO IBGE do município (preferindo a base local do MA) para foco preciso,
            // independente de acentos/maiúsculas no cadastro.
            $codigo = '';
            $norm = function ($s) { $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$s); if ($t === false) $t = (string)$s; return preg_replace('/[^a-z0-9]/', '', strtolower($t)); };
            if ($nome !== '') {
                $alvo = $norm($nome);
                $localLista = __DIR__ . '/limites_ma/_municipios.json';
                if (($uf === 'MA' || $uf === '21') && is_file($localLista)) {
                    $d = json_decode((string)@file_get_contents($localLista), true);
                    if (is_array($d) && !empty($d['municipios'])) {
                        foreach ($d['municipios'] as $mm) { if ($norm($mm['nome'] ?? '') === $alvo) { $codigo = (string)$mm['id']; break; } }
                    }
                }
            }
            echo json_encode(['ok' => true, 'cidade' => $nome, 'uf' => $uf, 'codigo' => $codigo, 'origem' => $raw], JSON_UNESCAPED_UNICODE);
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
            $res = $conn->query("SELECT id, situacao, motivo_situacao, matricula_sucessora, fora_municipio, contexto_rural, parcial_json,
                                        tipo_imovel, proprietario, cpf, numero_matricula, identificador, cor, cor_opacidade,
                                        area_ha, num_vertices, onr_status, onr_importation_id, inconsistencias
                                 FROM memoriais_mapeados ORDER BY id");
            $h = hash_init('crc32b'); $c = 0;
            while ($res && $row = $res->fetch_assoc()) { $c++; hash_update($h, implode('|', array_map(fn($v) => (string)$v, $row)) . "\n"); }
            echo json_encode(['ok' => true, 'sig' => $c . '-' . hash_final($h)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'listar') {
            // escopo: 'matriculas' (só matrículas) | 'projetos' (matrículas + projetos)
            $escProj = (($_POST['escopo'] ?? 'matriculas') === 'projetos') ? '' : ' WHERE is_projeto = 0';
            $res = $conn->query(
                "SELECT id, identificador, tipo_identificador, origem, imovel_id, num_vertices,
                        area_ha, perimetro_m, centro_lat, centro_lng, cor, cor_linha, cor_opacidade,
                        numero_matricula, proprietario, cpf, tipo_imovel, cnm, municipio, uf,
                        onr_status, onr_importation_id, onr_numero_prenotacao, onr_classificacao,
                        onr_nivel_publicidade, onr_descricao, itn03_exclusivo, inconsistencias,
                        situacao, motivo_situacao, matricula_sucessora, fora_municipio, contexto_rural, parcial_json, is_projeto, criado_em
                 FROM memoriais_mapeados" . $escProj . " ORDER BY criado_em DESC, id DESC LIMIT 20000"
            );
            $rows = [];
            while ($res && $row = $res->fetch_assoc()) {
                $fora = imovelForaMunicipio($row);
                $pronto = !$fora && !imovelEncerrado($row)
                    && in_array($row['tipo_imovel'], ['urbano','rural'], true)
                    && trim((string)$row['onr_nivel_publicidade']) !== ''
                    && trim((string)$row['onr_classificacao']) !== ''
                    && trim((string)$row['onr_numero_prenotacao']) !== ''
                    && trim((string)$row['onr_descricao']) !== '';
                $row['onr_pronto'] = $pronto ? 1 : 0;
                $row['onr_enviado'] = (trim((string)$row['onr_importation_id']) !== '') ? 1 : 0;
                $row['itn03_exclusivo'] = (int)($row['itn03_exclusivo'] ?? 0);
                $row['is_projeto'] = (int)($row['is_projeto'] ?? 0);
                $row['itn03_apto'] = itn03ExclusivoApto($row) ? 1 : 0; // aptidão p/ carga exclusiva ITN 03
                // Aptidão REAL para a carga ITN 03 (mapeadas usam a régua completa; exclusivas, a mínima)
                $falItn = ((int)($row['itn03_exclusivo'] ?? 0) === 1) ? itn03ExclusivoFaltam($row) : itn03Faltam($row);
                $row['itn03_ok']     = count($falItn) === 0 ? 1 : 0;
                $row['itn03_faltam'] = $falItn;
                $rows[] = $row;
            }
            echo json_encode(['ok' => true, 'itens' => $rows], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'listar_geo') {
            // Devolve todos os polígonos (lat/lng) para a visão geral e detecção de sobreposição.
            // escopo: 'matriculas' (só matrículas) | 'projetos' (matrículas + projetos).
            $escProj = (($_POST['escopo'] ?? 'matriculas') === 'projetos') ? '' : ' WHERE is_projeto = 0';
            $res = $conn->query(
                "SELECT id, identificador, tipo_identificador, origem, area_ha, cor, cor_linha, cor_opacidade,
                        numero_matricula, proprietario, cpf, tipo_imovel,
                        situacao, motivo_situacao, matricula_sucessora, fora_municipio, is_projeto, coordenadas_wgs84
                 FROM memoriais_mapeados" . $escProj . " ORDER BY id"
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
                        'cor_linha' => $row['cor_linha'],
                        'cor_opacidade' => $row['cor_opacidade'] !== null ? (float)$row['cor_opacidade'] : null,
                        'numero_matricula' => $row['numero_matricula'],
                        'proprietario' => $row['proprietario'],
                        'cpf' => $row['cpf'],
                        'tipo_imovel' => $row['tipo_imovel'],
                        'situacao' => $row['situacao'] ?? 'ativa',
                        'motivo_situacao' => $row['motivo_situacao'],
                        'matricula_sucessora' => $row['matricula_sucessora'],
                        'fora_municipio' => $row['fora_municipio'] ?? '',
                        'is_projeto' => (int)($row['is_projeto'] ?? 0),
                        'pts' => $pts,
                    ];
                }
            }
            echo json_encode(['ok' => true, 'itens' => $itens], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'concretizar_projeto') {
            // Transforma um imóvel de PROJETO em MATRÍCULA (is_projeto=0), opcionalmente já
            // atribuindo o número da matrícula e o tipo do imóvel.
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Projeto inválido.']); exit; }
            $numMat = trim((string)($_POST['numero_matricula'] ?? ''));
            if ($numMat !== '') $numMat = preg_replace('/^0+(?=\d)/', '', $numMat);
            $tipoImovel = ($_POST['tipo_imovel'] ?? '');
            $tipoImovel = in_array($tipoImovel, ['urbano', 'rural'], true) ? $tipoImovel : '';
            // Evita duplicar matrícula já existente
            if ($numMat !== '') {
                $jaId = acharMemorialPorMatricula($conn, $numMat);
                if ($jaId && (int)$jaId !== $id) {
                    echo json_encode(['ok' => false, 'erro' => 'Já existe a matrícula ' . $numMat . ' cadastrada. Escolha outro número ou complemente a existente.']);
                    exit;
                }
            }
            $sets = ['is_projeto = 0']; $vals = []; $types = '';
            if ($numMat !== '') {
                $sets[] = 'numero_matricula=?';    $vals[] = $numMat; $types .= 's';
                $sets[] = "tipo_identificador='matricula'";
                $imovelId = findImovelIdByMatricula($conn, $numMat);
                if ($imovelId !== null) { $sets[] = 'imovel_id=?'; $vals[] = $imovelId; $types .= 'i'; }
            }
            if ($tipoImovel !== '') { $sets[] = 'tipo_imovel=?'; $vals[] = $tipoImovel; $types .= 's'; }
            $vals[] = $id; $types .= 'i';
            $stmt = $conn->prepare("UPDATE memoriais_mapeados SET " . implode(', ', $sets) . " WHERE id=? AND is_projeto=1");
            $ok = false; if ($stmt) { $stmt->bind_param($types, ...$vals); $ok = $stmt->execute(); $aff = $stmt->affected_rows; } else { $aff = 0; }
            if ($ok && $aff > 0) {
                echo json_encode(['ok' => true, 'id' => $id, 'numero_matricula' => $numMat,
                    'mensagem' => 'Projeto concretizado — agora é uma matrícula' . ($numMat !== '' ? (' (' . $numMat . ')') : '') . '.'], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['ok' => false, 'erro' => 'Não foi possível concretizar (o registro não é um projeto ou não existe).']);
            }
            exit;
        }

        if ($acao === 'salvar_cor') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'erro' => 'Imóvel inválido.']); exit; }
            // valida um hex de destaque (não aceita vermelho, reservado a sobreposição)
            $validaCor = function($c) {
                if ($c === '') return true;
                if (!preg_match('/^#[0-9a-f]{6}$/', $c)) return false;
                $r = hexdec(substr($c, 1, 2)); $g = hexdec(substr($c, 3, 2)); $b = hexdec(substr($c, 5, 2));
                return !($r >= 150 && $g <= 90 && $b <= 90);
            };
            // Cada atributo é atualizado só se veio no POST (permite mudar linha, preenchimento ou intensidade em separado).
            $temCor   = array_key_exists('cor', $_POST);
            $temLinha = array_key_exists('cor_linha', $_POST);
            $temOp    = array_key_exists('opacidade', $_POST);
            $cor = strtolower(trim((string)($_POST['cor'] ?? '')));
            $corLinha = strtolower(trim((string)($_POST['cor_linha'] ?? '')));
            if ($temCor && !$validaCor($cor)) { echo json_encode(['ok' => false, 'erro' => ($cor !== '' && preg_match('/^#[0-9a-f]{6}$/', $cor)) ? 'O vermelho é reservado para sobreposições.' : 'Cor inválida.']); exit; }
            if ($temLinha && !$validaCor($corLinha)) { echo json_encode(['ok' => false, 'erro' => ($corLinha !== '' && preg_match('/^#[0-9a-f]{6}$/', $corLinha)) ? 'O vermelho é reservado para sobreposições.' : 'Cor da linha inválida.']); exit; }
            $valor = ($cor === '') ? null : $cor;
            $valorLinha = ($corLinha === '') ? null : $corLinha;
            // Intensidade (opacidade do preenchimento): limitada entre 0.08 e 0.55 para não fechar o mapa
            $op = null;
            if ($temOp && $_POST['opacidade'] !== '') {
                $op = (float)$_POST['opacidade'];
                if ($op < 0.08) $op = 0.08;
                if ($op > 0.55) $op = 0.55;
            }
            $sets = []; $vals = []; $types = '';
            if ($temCor)   { $sets[] = 'cor = ?';           $vals[] = $valor;      $types .= 's'; }
            if ($temLinha) { $sets[] = 'cor_linha = ?';     $vals[] = $valorLinha; $types .= 's'; }
            if ($temOp)    { $sets[] = 'cor_opacidade = ?'; $vals[] = $op;         $types .= 'd'; }
            if (!empty($sets)) {
                $vals[] = $id; $types .= 'i';
                $stmt = $conn->prepare("UPDATE memoriais_mapeados SET " . implode(', ', $sets) . " WHERE id = ?");
                $stmt->bind_param($types, ...$vals);
                $stmt->execute();
            }
            $resp = ['ok' => true, 'id' => $id];
            if ($temCor)   $resp['cor'] = $valor;
            if ($temLinha) $resp['cor_linha'] = $valorLinha;
            if ($temOp)    $resp['cor_opacidade'] = $op;
            echo json_encode($resp, JSON_UNESCAPED_UNICODE);
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

        if ($acao === 'mapear_texto') {
            // Mapeia/atualiza a geometria do imóvel a partir de um texto colado:
            // memorial descritivo (GMS/UTM), lista de coordenadas ou estrutura KML.
            $mid = (int)($_POST['id'] ?? 0);
            $conteudo = (string)($_POST['conteudo'] ?? '');
            if ($mid <= 0) { echo json_encode(['ok' => false, 'erro' => 'Salve o imóvel antes de mapear.']); exit; }
            if (trim($conteudo) === '') { echo json_encode(['ok' => false, 'erro' => 'Cole o memorial, as coordenadas ou o KML.']); exit; }
            // auto-detecta KML vs memorial/coordenadas
            $ehKml = (stripos($conteudo, '<kml') !== false || stripos($conteudo, '<coordinates') !== false || stripos($conteudo, '<placemark') !== false);
            $origem = $ehKml ? 'kml' : 'memorial';
            $res = mapearImovelComGeo($conn, $mid, $origem, $conteudo);
            if (empty($res['ok'])) { echo json_encode(['ok' => false, 'erro' => $res['erro'] ?? 'Não foi possível extrair coordenadas do texto.']); exit; }
            $geo = $res['geo'];
            inconsGravar($conn, $mid, detectarInconsistenciasGeo($geo, $origem, ($origem === 'memorial' ? $conteudo : '')));
            if ($ehKml && trim($conteudo) !== '') {
                anexoSalvarBytes($conn, $mid, $conteudo, ('imovel_' . $mid . '.kml'), 'kml', 'application/vnd.google-earth.kml+xml');
            }
            $rs = $conn->query("SELECT * FROM memoriais_mapeados WHERE id = " . (int)$mid . " LIMIT 1");
            $registro = $rs ? $rs->fetch_assoc() : null;
            echo json_encode([
                'ok' => true, 'id' => $mid, 'mapeado' => ['num_vertices' => $geo['num_vertices'], 'area_ha' => $geo['area_ha']],
                'registro' => $registro, 'anexos' => anexosListar($conn, $mid),
                'mensagem' => 'Geometria aplicada (' . ($ehKml ? 'KML' : 'memorial/coordenadas') . '): ' . $geo['num_vertices'] . ' vértices · ' . number_format($geo['area_ha'], 4, ',', '.') . ' ha. Agora aparece no mapa.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($acao === 'atualizar_geometria') {
            // Aplica a um registro (por id) o traçado escolhido no laudo (correto x transcrito).
            $mid = (int)($_POST['id'] ?? 0);
            $wgs = trim((string)($_POST['geo_wgs84'] ?? ''));
            if ($mid <= 0 || $wgs === '') { echo json_encode(['ok' => false, 'erro' => 'Parâmetros inválidos.']); exit; }
            $g = buildGeoDataFromWgs84($wgs);
            if (empty($g['ok'])) { echo json_encode(['ok' => false, 'erro' => 'Geometria inválida.']); exit; }
            $st = $conn->prepare("UPDATE memoriais_mapeados SET num_vertices=?, area_ha=?, perimetro_m=?, centro_lat=?, centro_lng=?, coordenadas_wgs84=?, coordenadas_utm=?, itn03_exclusivo=0 WHERE id=?");
            if ($st) {
                $st->bind_param('iddddssi', $g['num_vertices'], $g['area_ha'], $g['perimetro_m'], $g['centro_lat'], $g['centro_lng'], $g['coordenadas_wgs84'], $g['coordenadas_utm'], $mid);
                $st->execute();
            }
            inconsGravar($conn, $mid, detectarInconsistenciasGeo($g, 'memorial'));
            $rs = $conn->query("SELECT * FROM memoriais_mapeados WHERE id = " . (int)$mid . " LIMIT 1");
            $registro = $rs ? $rs->fetch_assoc() : null;
            echo json_encode([
                'ok' => true, 'id' => $mid, 'registro' => $registro, 'geo' => $g,
                'mensagem' => 'Geometria atualizada: ' . $g['num_vertices'] . ' vértices · ' . number_format($g['area_ha'], 4, ',', '.') . ' ha.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
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
            // Se for KML e o imóvel ainda não tem coordenadas (ex.: exclusivo da ITN 03), MAPEIA automaticamente.
            $mapeado = null; $mapeadoErro = '';
            $ehKml = ($tipo === 'kml') || strtolower(pathinfo($nome, PATHINFO_EXTENSION)) === 'kml' || stripos($mime, 'kml') !== false;
            if ($ehKml && !imovelTemGeo($conn, $mid)) {
                $mp = mapearImovelComGeo($conn, $mid, 'kml', $bytes);
                if (!empty($mp['ok'])) {
                    $geo = $mp['geo'];
                    inconsGravar($conn, $mid, detectarInconsistenciasGeo($geo, 'kml'));
                    $rsM = $conn->query("SELECT * FROM memoriais_mapeados WHERE id = " . (int)$mid . " LIMIT 1");
                    $mapeado = ['num_vertices' => $geo['num_vertices'], 'area_ha' => $geo['area_ha'], 'registro' => ($rsM ? $rsM->fetch_assoc() : null)];
                } else { $mapeadoErro = (string)($mp['erro'] ?? ''); }
            }
            echo json_encode(['ok' => true, 'anexo_id' => $aid, 'tipo' => $tipo, 'anexos' => anexosListar($conn, $mid), 'mapeado' => $mapeado,
                'mensagem' => $mapeado
                    ? ('KML anexado e matrícula MAPEADA com ' . $mapeado['num_vertices'] . ' vértices (' . number_format($mapeado['area_ha'], 4, ',', '.') . ' ha). Agora aparece no mapa.')
                    : (anexoTipoRotulo($tipo) . ' anexado.' . ($mapeadoErro !== '' ? ' (não foi possível mapear: ' . $mapeadoErro . ')' : ''))], JSON_UNESCAPED_UNICODE); exit;
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

            // Se o imóvel NÃO tinha coordenadas (ex.: exclusivo da ITN 03) e o PDF/SIGEF traz memorial
            // com coordenadas, MAPEIA o imóvel (deixa de ser exclusivo e passa a aparecer no mapa).
            $mapeado = null;
            if (!imovelTemGeo($conn, $mid) && trim((string)($d['memorial'] ?? '')) !== '') {
                $mp = mapearImovelComGeo($conn, $mid, 'memorial', $d['memorial']);
                if (!empty($mp['ok'])) {
                    $geo = $mp['geo'];
                    inconsGravar($conn, $mid, detectarInconsistenciasPdf($d, $geo));
                    $mapeado = ['num_vertices' => $geo['num_vertices'], 'area_ha' => $geo['area_ha']];
                }
            }

            // devolve o registro atualizado (para o formulário recarregar) + anexos
            $rs2 = $conn->query("SELECT * FROM memoriais_mapeados WHERE id = " . (int)$mid . " LIMIT 1");
            $registro = $rs2 ? $rs2->fetch_assoc() : $rowAtual;
            $nFalta = count(array_unique($preenchidos));
            $msgMap = $mapeado ? (' Matrícula MAPEADA com ' . $mapeado['num_vertices'] . ' vértices (' . number_format($mapeado['area_ha'], 4, ',', '.') . ' ha) — agora aparece no mapa.') : '';
            echo json_encode([
                'ok' => true, 'id' => $mid, 'anexo_id' => $aid, 'modelo' => $r['modelo'],
                'registro' => $registro, 'anexos' => anexosListar($conn, $mid), 'mapeado' => $mapeado,
                'campos' => array_values(array_unique($preenchidos)), 'ciclo_vida' => $cvAplicado,
                'mensagem' => ($nFalta > 0 ? ($nFalta . ' campo(s) faltante(s) preenchido(s) pela IA. Revise e salve.') : 'Nada a preencher — os campos já estavam completos.') . cicloVidaResumo($cvAplicado) . $msgMap
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
<title>Vertex — Atlas</title>
<!-- ATLAS-VERTEX-BUILD: 2026-07-22d-foco-encaixe-3d (removida a linha de controles "Inclinar/girar" sobre o mapa — sobra só o botão "Ver em 3D" no canto; o painel de dados do imóvel foi compactado (paddings/fontes menores, altura limitada a 360px com rolagem interna) e passa a abrir logo ABAIXO do botão "Ver em 3D" (top 104px, esquerda), encaixando na antiga posição do "Inclinar"; segue arrastável) | anterior: 2026-07-22c-foco-arrastavel (painel de dados do imóvel em foco: sobe para z-index 9 — deixa de ficar por baixo do painel Visão geral — e passa a abrir por padrão no canto SUPERIOR ESQUERDO do mapa (Visão geral fica à direita), sem sobreposição; agora é ARRASTÁVEL pela alça do cabeçalho via tornarArrastavel, com o botão "Dados do imóvel" também móvel (tornarArrastavelBtn) — mesmo comportamento do painel Visão geral; fechar/reabrir preservados) | anterior: 2026-07-22b-valida-memorial-narrativo-e-painel-foco (VALIDAÇÃO DE MEMORIAL NARRATIVO: novo analisador de memoriais em prosa — "vértice P-n, de coordenadas N=.. e E=.." com lados "azimute e distância Az=..°..'..\" e DIST metros até o vértice P-x". Detecta coordenada fora da faixa UTM (erro de digitação, ex.: northing com 8 dígitos) e a conserta pelo lado de chegada, e aponta o VÉRTICE culpado quando a coordenada diverge do azimute/distância do memorial (reconstrói a partir do vértice anterior; desvio > 5 m). Essas inconsistências passam a ser gravadas automaticamente no cadastro de MATRÍCULAS e de PROJETOS (fluxo salvar e mapear_texto) — antes o vértice ruim era descartado em silêncio. Novas funções analisarMemorialVertex/detectarInconsistenciasCoord e ação analisar_vertex; detectarInconsistenciasGeo ganhou 3º parâmetro opcional $memorial (retrocompatível). PAINEL DE FOCO: ao focar um único imóvel (carregarImovel), aparece à direita do mapa um painel com matrícula/identificação, datum·zona·MC, área (ha/m²/alqueire), perímetro, tabela de vértices E/N, confrontações e as inconsistências — reproduzindo o laudo do memorial no tema do app; preenche na hora pela geometria (WGS84→UTM em JS) e enriquece via analisar_vertex; botão fechar/reabrir; some ao voltar à visão geral) | anterior: 2026-07-22a-aba-relatorios (nova aba RELATÓRIOS: 3 painéis de completude com gráfico donut SVG e % — 1) matrículas faltantes da 1 até a maior, com intervalos comprimidos (ex.: 5–7, 23) e contagem de imóveis sem nº; 2) envio ao Mapa ONR: enviadas × faltantes, chips verdes p/ prontas e vermelhos p/ dados incompletos; 3) carga ITN 03: aptas × pendentes com O QUE FALTA em cada matrícula; botão Copiar lista por relatório, Recalcular, atalho de teclado 7, bottom nav mobile com 7 colunas; listar agora devolve itn03_ok/itn03_faltam (régua completa p/ mapeadas, mínima p/ exclusivas); tudo client-side sobre o próprio listar — sem migração de banco) | anterior: 2026-07-21f-3d-sem-links-externos (removidos os links "Google Earth" e "Google Maps (satélite)" do rodapé do modal de visão 3D — os imóveis só carregam dentro do sistema; mensagens de fallback do 3D atualizadas para não citar os links) | anterior: 2026-07-21e-vertodos-desmarcado (no foco de confronto o botão "Ver todos" fica desmarcado; clicá-lo nesse estado limpa o filtro ";*", oculta o badge do município e reexibe todos os imóveis — sem sair da visão geral) | anterior: 2026-07-21d-fix-termo-mat (fix: termo da consulta ";*" usava o rótulo "Mat. N", que não casa com o filtro exato de matrícula e o imóvel não era exibido — agora usa o NÚMERO puro (sem zeros à esquerda) e, sem matrícula, a identificação; corrigido também no verNoMapaConfronto da importação) | anterior: 2026-07-21c-foco-confronto-selecao (selecionar imóvel na lista agora foca em modo CONFRONTO: visão geral + consulta "matrícula;*" no painel — sobreposições e desmembradas — mantendo pontos dos vértices e badge de pertencimento ao município como no modo single; nova focarImovelConfronto; carregarImovel segue preenchendo Cadastrar/ONR/cor; dropzone de Importar só lista os tipos aceitos) | anterior: 2026-07-21b-shell-2-niveis-icones (REORGANIZAÇÃO ESTRUTURAL: barra de comando em 2 níveis — contexto/marca/base/ações em cima, faixa de abas com indicador embaixo; sprite SVG com 20 ícones estilo Lucide substituindo todos os emojis do shell, controles 3D, toolbars, cartões ONR e painel Visão geral; NAVEGAÇÃO INFERIOR FIXA no mobile (≤880px, estilo app nativo, safe-area, palco encolhe via bottom do shell); cabeçalhos de página por aba (ícone+título+descrição); form-grid em 3 colunas ≥1100px; "Como funciona" como stepper numerado; toggleRotulos atualiza só o <span> preservando o ícone) | anterior: 2026-07-21a-design-system-2 (DESIGN SYSTEM 2.0 "Instrumento cartográfico": camada visual 100% reconstruída — tokens de cor/raio/sombra/tipografia, Space Grotesk p/ títulos+abas, Inter p/ UI, IBM Plex Mono p/ dados; barra de comando com fio de lacre e abas segmentadas com indicador; graticule cartográfico sutil no palco; formulários com anel de foco, hover e select custom; botões com gradiente e elevação; cartões, badges, chips, acordeões, dropzones, painéis de mapa, modais e SweetAlert2 retematizados nos dois temas; dark mode revisto (azul-grafite); 100% responsivo (1100/880/520/420) com abas roláveis no mobile, alvos de toque maiores e prefers-reduced-motion; nenhum seletor/estado do JS alterado — apenas aparência) | anterior: 2026-07-03u-valida-doc-salvar (3D PRÓPRIO em Three.js: satélite+relevo servidos pelo backend, independe do Map Tiles API; links Earth/Maps confiáveis; controle 3D movido p/ esquerda sem sobrepor o painel; aba minimizada arrastável; 3D usa path (fim do warning de coordinates) + rodapé/timeout com atalho Google Earth; visão 3D: "Ver em 3D" fotorrealista via Map3DElement com contornos dos imóveis + fallback Google Earth; inclinar/girar o próprio mapa; cor de LINHA e de PREENCHIMENTO separadas no painel e no popup; imóvel-mãe/encerrado renderiza por baixo via zIndex; editar matrícula agora mostra o memorial extraído + botões Analisar/Revisar traçado com prévia e ação atualizar_geometria por id; laudo no fluxo de PDF com escolha correto x transcrito + prévia SVG comparando os dois traçados; PDF individual mostra modal de resultado; laudo transcrito x corrigido; escolha AUTOMÁTICA no cadastro quando há coords inconsistentes; botão "Revisar traçado" reaparece na edição só p/ imóveis nessa situação; parser UTM rotulado "<num>-E e <num>-N"; correção easting 7-díg + OCR; grava/atualiza inclusive registro existente) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  /* ══════════════════════════════════════════════════════════════════
     ATLAS VERTEX · DESIGN SYSTEM 2.0 — "Instrumento cartográfico"
     Reconstrução completa da camada visual: tokens, tipografia, formas,
     botões, cartões, painéis de mapa, modais e responsividade.
     Todos os seletores/estados do JS foram preservados (.show, .active,
     .sel, .drag, body.dark-mode…) — apenas a aparência foi refeita.
     ══════════════════════════════════════════════════════════════════ */

  /* ─── 1. TOKENS ─────────────────────────────────────────────────── */
  :root{
    /* Superfícies */
    --bg:#EDF1F7; --panel:#FFFFFF; --panel-2:#F4F6FA; --card:var(--panel);
    --line:#E3E8F0; --line-2:#CFD8E4;
    /* Tinta */
    --ink:#152030; --muted:#48586C; --faint:#7C8BA0;
    /* Marca — gradiente Vertex (teal → azul, igual ao card do módulo) */
    --red:#1571B0; --red-bright:#0D9488; --red-deep:#1D4ED8;
    --red-soft:color-mix(in srgb, var(--red-bright) 9%, transparent);
    --red-text:#0F6E96;
    /* Alertas / erros — vermelho semântico (sobreposição, pendências, exclusão) */
    --err:#B01224; --err-bright:#D5182C; --err-deep:#8C0E1D;
    --err-soft:color-mix(in srgb, var(--err-bright) 9%, transparent);
    --err-text:#A81222;
    /* Acentos funcionais */
    --teal:#0E8F80; --blue:#2563EB; --violet:#7C3AED;
    --green:#178A4F; --green-text:#0F6B3B;
    --amber:#B87B12; --amber-text:#8A5C07;
    /* Vidro / sombras */
    --ov-bg:color-mix(in srgb, var(--panel) 92%, transparent);
    --sh-1:0 1px 2px rgba(21,32,48,.05), 0 2px 10px -4px rgba(21,32,48,.08);
    --sh-2:0 2px 6px rgba(21,32,48,.05), 0 14px 34px -16px rgba(21,32,48,.22);
    --sh-3:0 28px 70px -28px rgba(21,32,48,.42);
    --ov-shadow:var(--sh-2);
    --vx-shadow:var(--sh-1);
    /* Geometria */
    --r-s:9px; --r:12px; --r-l:16px; --r-xl:20px; --vx-r:var(--r);
    /* Tipografia */
    --disp:'Inter',system-ui,-apple-system,sans-serif;
    --titles:'Space Grotesk','Inter',system-ui,sans-serif;
    --mono:'IBM Plex Mono',ui-monospace,Menlo,monospace;
    /* Foco */
    --ring:0 0 0 3px color-mix(in srgb, var(--red-bright) 18%, transparent);
    --ring-teal:0 0 0 3px color-mix(in srgb, var(--teal) 20%, transparent);
    --atlas-header:60px;
    --vx-bottombar:84px;
  }
  /* Tema escuro do Atlas (body.dark-mode) */
  body.dark-mode{
    --bg:#0A0F16; --panel:#111823; --panel-2:#18212E; --card:var(--panel);
    --line:#243040; --line-2:#33455C;
    --ink:#E8EEF6; --muted:#97A6B8; --faint:#64748B;
    --red:#1D84C4; --red-bright:#14B8A6; --red-deep:#2563EB;
    --red-soft:color-mix(in srgb, var(--red-bright) 14%, transparent);
    --red-text:#5EEAD4;
    --err:#C21A2C; --err-bright:#EF4051; --err-deep:#8C0E1D;
    --err-soft:color-mix(in srgb, var(--err-bright) 14%, transparent);
    --err-text:#F39AA2;
    --teal:#22B3A2; --blue:#5B8DEF; --violet:#A78BFA;
    --green:#2FB871; --green-text:#7FE0AD;
    --amber:#E0A63A; --amber-text:#EFC77E;
    --ov-bg:color-mix(in srgb, var(--panel) 90%, transparent);
    --sh-1:0 1px 2px rgba(0,0,0,.3), 0 4px 14px -6px rgba(0,0,0,.4);
    --sh-2:0 2px 8px rgba(0,0,0,.35), 0 18px 44px -18px rgba(0,0,0,.6);
    --sh-3:0 32px 80px -30px rgba(0,0,0,.75);
    --ov-shadow:var(--sh-2);
    --vx-shadow:var(--sh-1);
  }

  /* ─── 2. BASE ───────────────────────────────────────────────────── */
  *{box-sizing:border-box}
  html,body{margin:0}
  ::selection{background:color-mix(in srgb, var(--red-bright) 22%, transparent)}
  ::-webkit-scrollbar{width:10px;height:10px}
  ::-webkit-scrollbar-track{background:transparent}
  ::-webkit-scrollbar-thumb{background:var(--line-2);border-radius:99px;border:2px solid transparent;background-clip:padding-box}
  ::-webkit-scrollbar-thumb:hover{background:var(--faint);border:2px solid transparent;background-clip:padding-box}
  :is(button,a,input,select,textarea,summary):focus-visible{outline:none;box-shadow:var(--ring)}

  /* ─── 3. SHELL ──────────────────────────────────────────────────── */
  .mapeador-shell{position:fixed;top:var(--header-height,60px);left:0;right:0;bottom:0;
    display:flex;flex-direction:column;overflow:hidden;z-index:1;
    font-family:var(--disp);background:var(--bg);color:var(--ink);
    font-feature-settings:'cv05','cv11';-webkit-font-smoothing:antialiased}

  /* Palco com graticule cartográfico sutil (assinatura visual) */
  .vx-stage{flex:1 1 auto;position:relative;min-height:0;width:100%;overflow:hidden;background:var(--bg)}
  .vx-stage::before{content:'';position:absolute;inset:0;pointer-events:none;opacity:.5;
    background-image:
      linear-gradient(color-mix(in srgb, var(--ink) 4%, transparent) 1px, transparent 1px),
      linear-gradient(90deg, color-mix(in srgb, var(--ink) 4%, transparent) 1px, transparent 1px);
    background-size:32px 32px;
    mask-image:radial-gradient(120% 90% at 50% 0%, #000 30%, transparent 100%);
    -webkit-mask-image:radial-gradient(120% 90% at 50% 0%, #000 30%, transparent 100%)}
  body.dark-mode .vx-stage::before{opacity:.35}

  /* ─── 4. BARRA DE COMANDO ───────────────────────────────────────── */
  .panel{flex:none;width:100%;position:relative;z-index:20;display:block;
    background:color-mix(in srgb, var(--panel) 88%, transparent);
    -webkit-backdrop-filter:saturate(1.5) blur(14px);backdrop-filter:saturate(1.5) blur(14px);
    border-bottom:1px solid var(--line)}
  /* Fio de lacre: filete vermelho que assina a barra */
  .panel::after{content:'';position:absolute;left:0;right:0;bottom:-1px;height:2px;pointer-events:none;
    background:linear-gradient(90deg, var(--red-bright), color-mix(in srgb, var(--red-bright) 40%, transparent) 34%, transparent 62%)}
  /* Ícones vetoriais da interface (sprite <symbol>) */
  .ic{width:15px;height:15px;flex:none;fill:none;stroke:currentColor;stroke-width:2;
    stroke-linecap:round;stroke-linejoin:round;display:inline-block;vertical-align:-2px}

  /* Barra de comando em dois níveis: contexto em cima, navegação embaixo */
  .vx-bar{display:flex;flex-direction:column}
  .vx-top{display:flex;align-items:center;gap:16px;padding:10px 20px 9px;flex-wrap:wrap}
  .vx-top-r{display:flex;align-items:center;gap:8px;margin-left:auto}

  /* Marca */
  .brand{display:flex;align-items:center;gap:11px;margin-right:2px}
  .mark{width:36px;height:36px;border-radius:11px;flex:none;display:grid;place-items:center;
    background:linear-gradient(135deg, var(--red-bright) 0%, var(--red-deep) 100%);
    box-shadow:0 4px 12px -4px color-mix(in srgb, var(--red-bright) 60%, transparent),
      inset 0 1px 0 rgba(255,255,255,.25)}
  .mark svg{width:19px;height:19px}
  .brand h1{margin:0;font-family:var(--titles);font-size:16.5px;font-weight:700;
    letter-spacing:.01em;line-height:1.05;color:var(--ink)}
  .brand p{margin:2px 0 0;font-family:var(--mono);font-size:10px;letter-spacing:.04em;color:var(--faint)}

  /* Voltar ao Atlas */
  .back-atlas{flex:none;display:inline-flex;align-items:center;justify-content:center;gap:7px;white-space:nowrap;
    font-family:var(--disp);font-size:12px;font-weight:600;color:var(--muted);text-decoration:none;
    border:1px solid var(--line);border-radius:999px;padding:7px 13px;background:var(--panel);
    transition:border-color .15s,color .15s,box-shadow .15s}
  .back-atlas:hover{color:var(--ink);border-color:var(--line-2);box-shadow:var(--sh-1)}
  .back-atlas .ic{width:13px;height:13px}

  /* Alternador de base (Matrículas / Projetos) */
  .base-toggle{display:flex;gap:3px;margin:0;padding:4px;border-radius:12px;
    background:var(--panel-2);border:1px solid var(--line)}
  .base-toggle .bt-btn{flex:1;display:inline-flex;align-items:center;gap:7px;border:none;background:transparent;
    cursor:pointer;white-space:nowrap;font-family:var(--disp);font-size:12px;font-weight:650;color:var(--muted);
    padding:7px 13px;border-radius:9px;transition:background .16s,color .16s,box-shadow .16s}
  .base-toggle .bt-btn .ic{width:14px;height:14px;opacity:.8}
  .base-toggle .bt-btn:hover{color:var(--ink)}
  .base-toggle .bt-btn.active{background:var(--panel);color:var(--teal);box-shadow:var(--sh-1)}
  .base-toggle .bt-btn.active .ic{opacity:1}
  .base-toggle.projetos .bt-btn.active{color:var(--violet)}
  body.dark-mode .base-toggle .bt-btn.active{background:#202B3A}

  /* Faixa de navegação — abas com indicador deslizante */
  .vx-tabs{display:flex;gap:2px;padding:0 12px;
    border-top:1px solid color-mix(in srgb, var(--line) 55%, transparent)}
  .vx-tab{position:relative;display:inline-flex;align-items:center;gap:8px;border:none;background:transparent;
    color:var(--muted);font-family:var(--titles);font-size:13px;font-weight:600;letter-spacing:.01em;
    padding:11px 15px 13px;cursor:pointer;white-space:nowrap;border-radius:10px 10px 0 0;
    transition:color .16s,background .16s}
  .vx-tab .ic{width:16px;height:16px;stroke-width:1.9;opacity:.75;transition:opacity .16s,color .16s}
  .vx-tab:hover{color:var(--ink);background:color-mix(in srgb, var(--ink) 4%, transparent)}
  .vx-tab:hover .ic{opacity:1}
  .vx-tab.active{color:var(--ink)}
  .vx-tab.active .ic{opacity:1;color:var(--red-bright)}
  .vx-tab.active::after{content:'';position:absolute;left:12px;right:12px;bottom:-1px;height:2.5px;
    border-radius:3px 3px 0 0;background:var(--red-bright)}

  /* Ações rápidas da barra */
  .quick-actions{display:flex;gap:6px}
  .quick-actions .mini-btn{flex:none;justify-content:center}

  /* Cabeçalho de página das abas */
  .vx-pane-head{display:flex;gap:14px;align-items:flex-start;margin:2px 0 20px}
  .vx-ph-ic{flex:none;width:40px;height:40px;border-radius:12px;display:grid;place-items:center;
    color:var(--red-bright);background:var(--red-soft);
    border:1px solid color-mix(in srgb, var(--red-bright) 22%, transparent)}
  .vx-ph-ic .ic{width:19px;height:19px;stroke-width:1.8}
  .vx-ph-tx h2{margin:0;font-family:var(--titles);font-size:17px;font-weight:700;color:var(--ink);letter-spacing:.005em}
  .vx-ph-tx p{margin:4px 0 0;font-size:12px;line-height:1.6;color:var(--muted);max-width:72ch}

  /* Ícone dos cartões de ação (aba ONR) */
  .vx-act-h{display:flex;align-items:center}
  .vx-act-ic{display:inline-grid;place-items:center;width:30px;height:30px;border-radius:9px;margin-right:9px;
    color:var(--teal);background:color-mix(in srgb, var(--teal) 12%, transparent)}
  .vx-act-ic.itn{color:var(--red-bright);background:var(--red-soft)}
  .vx-act-ic .ic{width:15px;height:15px}

  /* Faixa de status global (colada à barra) */
  .panel>.status{margin:0;border-radius:0;border-left:none;border-right:none;border-top:none}

  /* ─── 5. PANES ──────────────────────────────────────────────────── */
  .vx-pane{position:absolute;inset:0;display:none;overflow-y:auto;
    padding:24px max(20px, (100% - 1060px)/2) calc(24px + var(--vx-bottombar,0px));
    animation:vxFade .22s ease}
  .vx-pane.active{display:block}
  @keyframes vxFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

  .vx-pane[data-pane="mapa"]{padding:0;overflow:hidden}
  .vx-pane[data-pane="mapa"] .map-wrap{position:absolute;inset:0;width:100%;height:100%;min-width:0}
  .vx-pane[data-pane="mapa"] #map{position:absolute;inset:0}

  .vx-pane[data-pane="imoveis"]{padding:0;overflow:hidden}
  .vx-pane[data-pane="imoveis"].active{display:flex;flex-direction:column}
  .vx-pane[data-pane="imoveis"] .saved{flex:1;min-height:0;display:flex;flex-direction:column;margin:0}
  .vx-pane[data-pane="imoveis"] .saved-head{flex:none;margin:0;padding:18px max(20px, (100% - 1060px)/2) 8px}
  .imoveis-sticky{position:static;flex:none;margin:0;padding:2px max(20px, (100% - 1060px)/2) 14px;
    background:var(--panel);border-bottom:1px solid var(--line)}
  .imoveis-sticky .vista-toggle{margin:0}
  .imoveis-sticky .search-wrap{margin:10px 0 0}
  .imoveis-sticky .itn03-actions{margin:10px 0 0}
  .vx-pane[data-pane="imoveis"] #saved-list{flex:1;min-height:0;overflow-y:auto;
    padding:14px max(20px, (100% - 1060px)/2) calc(18px + var(--vx-bottombar,0px))}

  /* Zera heranças do layout antigo */
  .vx-pane .muni-box,.vx-pane .onr-box{margin-top:0}
  .vx-pane .muni-box{border-top:none;padding-top:0}
  .vx-pane .manual-accordion{margin-top:0}
  .toggle-panel,.fab-panel{display:none !important}
  .panel-backdrop{display:none;position:fixed;inset:0;z-index:899;background:rgba(8,12,18,.5)}
  body.panel-open .panel-backdrop{display:block}

  /* ─── 6. TIPOGRAFIA DE APOIO ────────────────────────────────────── */
  .label{margin:0 0 8px;font-family:var(--titles);font-size:11px;font-weight:700;
    letter-spacing:.09em;text-transform:uppercase;color:var(--faint)}
  .field-label,.fld .field-label{margin:0 0 6px;font-family:var(--disp);font-size:11.5px;
    font-weight:600;color:var(--muted);letter-spacing:0;text-transform:none}
  .field-hint{font-size:11px;color:var(--faint);line-height:1.5}
  .saved h3{margin:0 0 10px;font-family:var(--titles);font-size:11px;font-weight:700;
    letter-spacing:.09em;text-transform:uppercase;color:var(--faint)}
  .saved{margin-top:24px}
  .saved-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
  .saved-head h3{margin:0}
  .vx-sub-title{margin:26px 0 12px;padding-top:18px;border-top:1px dashed var(--line);
    font-family:var(--titles);font-weight:700;font-size:13px;color:var(--ink)}
  .vx-pane-hint{margin:0 0 16px;padding:11px 14px;border-radius:var(--r);
    background:var(--panel-2);border:1px solid var(--line);
    font-size:11.5px;color:var(--faint);line-height:1.55}
  .vx-pane-hint b{color:var(--muted);font-weight:650}
  .vx-flow{margin:18px 0 0;padding:14px 16px;border-radius:var(--r-l);
    background:var(--panel-2);border:1px solid var(--line);
    font-size:12px;color:var(--muted);line-height:1.6}
  .vx-flow>b{color:var(--ink)}
  .vx-flow ol{margin:8px 0 0;padding-left:18px}
  .vx-flow li{margin:4px 0}

  /* ─── 7. FORMULÁRIOS ────────────────────────────────────────────── */
  textarea,input,select{width:100%;color:var(--ink);outline:none}
  input:not([type="range"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="color"]),
  select,.search-wrap input,.ov-search input{
    font-family:var(--disp);font-size:13px;padding:11px 13px;border-radius:11px;
    background:var(--panel);border:1px solid var(--line);
    transition:border-color .15s, box-shadow .15s, background .15s}
  input:not([type="range"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):hover:not(:focus):not([readonly]),
  select:hover:not(:focus),textarea:hover:not(:focus){border-color:var(--line-2)}
  input:not([type="range"]):not([type="checkbox"]):not([type="radio"]):not([type="file"])::placeholder,
  textarea::placeholder{color:var(--faint)}
  input:not([type="range"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):focus,
  select:focus,textarea:focus,.search-wrap input:focus,.ov-search input:focus{
    border-color:var(--red-bright);box-shadow:var(--ring);outline:none}
  select{appearance:none;-webkit-appearance:none;padding-right:36px;cursor:pointer;
    background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%237C8BA0' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat:no-repeat;background-position:right 13px center}
  textarea{height:140px;resize:vertical;line-height:1.55;font-size:11.5px;
    font-family:var(--mono);border-radius:12px;padding:12px 13px;
    background:var(--panel);border:1px solid var(--line);
    transition:border-color .15s, box-shadow .15s}
  input[readonly],.onr-sub input[readonly]{background:var(--panel-2);opacity:.72;cursor:not-allowed}
  input[type="checkbox"],input[type="radio"]{accent-color:var(--red-bright)}
  .row{display:grid;grid-template-columns:1fr 130px;gap:10px;margin-top:14px}
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 13px;margin-top:6px}
  .fld{display:flex;flex-direction:column;min-width:0}
  .fld .field-label{margin:0 0 5px}
  .grid-2{grid-column:1 / -1}

  /* ─── 8. BOTÕES ─────────────────────────────────────────────────── */
  button{font-family:var(--disp);cursor:pointer;border:none;border-radius:10px;
    font-size:13px;font-weight:550;color:var(--ink);
    transition:filter .15s,background .15s,border-color .15s,color .15s,transform .15s,box-shadow .15s}
  .actions{display:flex;gap:10px;margin-top:14px}
  .btn-primary{flex:1;padding:12px 14px;border-radius:11px;font-weight:650;color:#fff;
    background:linear-gradient(135deg, var(--red-bright) 0%, var(--red-deep) 100%);
    box-shadow:0 8px 20px -10px color-mix(in srgb, var(--red-bright) 65%, transparent),
      inset 0 1px 0 rgba(255,255,255,.18)}
  .btn-primary:hover:not(:disabled){transform:translateY(-1px);filter:brightness(1.05);
    box-shadow:0 12px 26px -12px color-mix(in srgb, var(--red-bright) 75%, transparent)}
  .btn-primary:active:not(:disabled){transform:none}
  .btn-primary:disabled{opacity:.45;cursor:default}
  .btn-save{flex:1;padding:12px 14px;border-radius:11px;font-weight:650;color:#fff;
    background:linear-gradient(160deg, color-mix(in srgb, var(--green) 88%, #fff), var(--green));
    box-shadow:0 8px 20px -10px color-mix(in srgb, var(--green) 60%, transparent),
      inset 0 1px 0 rgba(255,255,255,.2)}
  .btn-save:hover:not(:disabled){transform:translateY(-1px);filter:brightness(1.05)}
  .btn-save:disabled{opacity:.4;cursor:default}
  .btn-ghost{padding:11px 15px;border-radius:11px;background:var(--panel);
    color:var(--muted);border:1px solid var(--line)}
  .btn-ghost:hover{color:var(--ink);border-color:var(--line-2);background:var(--panel-2)}
  .btn-ghost-sm{background:var(--panel);border:1px solid var(--line);color:var(--ink);
    border-radius:9px;padding:0 12px;font-size:11px;white-space:nowrap}
  .btn-ghost-sm:hover{border-color:var(--teal);color:var(--teal)}
  .mini-btn{display:inline-flex;align-items:center;gap:6px;
    font-family:var(--disp);font-size:11.5px;font-weight:600;letter-spacing:0;
    padding:7px 12px;border-radius:9px;border:1px solid var(--line);
    background:var(--panel);color:var(--muted)}
  .mini-btn:not(.onr):not(.at):hover{color:var(--ink);border-color:var(--line-2);
    background:var(--panel-2);box-shadow:var(--sh-1)}
  .mini-btn.active{background:var(--red-soft);
    border-color:color-mix(in srgb, var(--red-bright) 45%, var(--line));color:var(--red-text)}
  .mini-btn.onr{background:linear-gradient(135deg, var(--teal), var(--blue));color:#fff;border-color:transparent;
    box-shadow:0 6px 16px -8px color-mix(in srgb, var(--teal) 60%, transparent)}
  .mini-btn.onr:hover{filter:brightness(1.07)}
  .mini-btn.proj{border-color:color-mix(in srgb, var(--violet) 45%, transparent);color:var(--violet)}
  .btn-mini,.btn-mini-prim{border-radius:9px;padding:8px 14px;font-size:12px;font-weight:600;
    border:1px solid var(--line);background:var(--panel);color:var(--ink)}
  .btn-mini:hover{border-color:var(--line-2);background:var(--panel-2)}
  .btn-mini-prim{background:var(--red);color:#fff;border-color:var(--red)}
  .btn-mini-prim:hover{filter:brightness(1.08)}
  .btn-excluir{background:transparent;border:1px solid color-mix(in srgb, var(--err-bright) 40%, transparent);
    color:var(--err-text);border-radius:9px;padding:8px 14px;font-size:12px;font-weight:600}
  .btn-excluir:hover{background:var(--err-soft)}
  .cfg-link{display:flex;align-items:center;gap:5px;width:fit-content;margin:12px 0 0 auto;
    background:none;border:none;color:var(--faint);font-size:11px;
    padding:5px 9px;border-radius:9px}
  .cfg-link:hover{color:var(--muted);background:var(--panel-2)}
  .link-config{display:block;width:100%;margin-top:6px;background:none;border:none;color:var(--faint);
    font-family:var(--mono);font-size:10px;cursor:pointer;text-align:left;padding:3px 1px;border-radius:6px}
  .link-config:hover{color:var(--violet)}

  /* ─── 9. STATUS ─────────────────────────────────────────────────── */
  .status{margin-top:13px;font-family:var(--disp);font-size:12px;font-weight:500;
    padding:10px 14px;border-radius:10px;line-height:1.5;display:none}
  .status.ok{display:block;background:color-mix(in srgb, var(--green) 9%, transparent);
    color:var(--green-text);border:1px solid color-mix(in srgb, var(--green) 32%, transparent)}
  .status.err{display:block;background:var(--err-soft);color:var(--err-text);
    border:1px solid color-mix(in srgb, var(--err-bright) 32%, transparent)}
  .status.warn{display:block;background:color-mix(in srgb, var(--amber) 11%, transparent);
    color:var(--amber-text);border:1px solid color-mix(in srgb, var(--amber) 36%, transparent)}

  /* ─── 10. MÉTRICAS ──────────────────────────────────────────────── */
  .stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}
  .stat{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);
    padding:13px 14px;box-shadow:var(--sh-1)}
  .stat .v{font-family:var(--titles);font-size:20px;font-weight:700;letter-spacing:-.01em;color:var(--ink)}
  .stat .u{font-size:12px;color:var(--faint);font-weight:400}
  .stat .k{margin-top:4px;font-family:var(--mono);font-size:9.5px;text-transform:uppercase;
    letter-spacing:.08em;color:var(--faint)}
  .ed-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin:0 0 16px}
  .ed-stat{background:var(--panel-2);border:1px solid var(--line);border-radius:var(--r);padding:10px 12px}
  .ed-stat .v{font-family:var(--titles);font-size:17px;font-weight:700;letter-spacing:-.01em}
  .ed-stat .u{font-size:11px;color:var(--faint);font-weight:400}
  .ed-stat .k{margin-top:3px;font-family:var(--mono);font-size:9px;text-transform:uppercase;
    letter-spacing:.07em;color:var(--faint)}

  /* ─── 11. BUSCA E FILTROS ───────────────────────────────────────── */
  .search-wrap{position:relative;margin:4px 0 10px}
  .search-ic{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--faint);pointer-events:none}
  #busca{width:100%;padding:10px 32px 10px 34px;border-radius:11px}
  .search-clear{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;
    color:var(--faint);font-size:18px;line-height:1;padding:2px 6px;border-radius:7px}
  .search-clear:hover{color:var(--red-bright);background:var(--red-soft)}
  .vista-toggle{display:flex;flex-wrap:wrap;gap:5px;background:transparent;border:none;padding:0;margin-bottom:8px}
  .vista-toggle .vt-btn{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;
    border:1px solid transparent;background:var(--panel-2);color:var(--muted);
    font-family:var(--disp);font-size:11.5px;font-weight:600;padding:6px 12px;border-radius:999px;
    transition:background .15s,color .15s,border-color .15s,box-shadow .15s}
  .vista-toggle .vt-btn:hover{color:var(--ink);border-color:var(--line);background:var(--panel)}
  .vista-toggle .vt-btn.active{background:var(--red-bright);color:#fff;border-color:var(--red-bright);
    box-shadow:0 5px 14px -7px color-mix(in srgb, var(--red-bright) 70%, transparent)}
  .vista-toggle .vt-btn.vt-onr.active{background:var(--blue);border-color:var(--blue);
    box-shadow:0 5px 14px -7px color-mix(in srgb, var(--blue) 70%, transparent)}
  .vista-toggle .vt-btn .vt-count{display:inline-block;min-width:17px;text-align:center;
    background:color-mix(in srgb, var(--ink) 8%, transparent);color:inherit;
    border-radius:999px;padding:1px 6px;font-size:10px;font-weight:700;line-height:15px}
  .vista-toggle .vt-btn.active .vt-count{background:rgba(255,255,255,.25)}
  .itn03-actions{display:flex;gap:6px;margin-bottom:8px}
  .itn03-actions .mini-btn{flex:1}

  /* ─── 12. LISTA DE IMÓVEIS ──────────────────────────────────────── */
  .item{display:flex;align-items:center;gap:11px;padding:11px 13px;margin-bottom:8px;cursor:pointer;
    background:var(--panel);border:1px solid var(--line);border-radius:var(--r);
    transition:border-color .15s,background .15s,transform .15s,box-shadow .15s}
  #saved-list .item{border:1px solid var(--line);border-radius:var(--vx-r)}
  .item:hover,#saved-list .item:hover{border-color:color-mix(in srgb, var(--red-bright) 40%, var(--line));
    background:color-mix(in srgb, var(--red-bright) 4%, var(--panel));
    transform:translateX(2px);box-shadow:var(--sh-1)}
  .item.sel{border-color:#F59E0B;background:color-mix(in srgb, #F59E0B 10%, var(--panel))}
  .item.sel .ic{background:#F59E0B}
  .item.destaque{border-color:var(--teal);background:color-mix(in srgb, var(--teal) 9%, var(--panel));
    box-shadow:0 0 0 1px color-mix(in srgb, var(--teal) 35%, transparent) inset}
  .item .ic{width:8px;height:8px;border-radius:3px;background:var(--red-bright);flex:none}
  .item .info{flex:1;min-width:0}
  .item .nm{font-size:13px;font-weight:550;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .item .mt{font-family:var(--mono);font-size:10px;color:var(--muted);margin-top:2px}
  .item .tag{font-family:var(--disp);font-weight:700;font-size:9.5px;letter-spacing:.03em;
    text-transform:uppercase;border-radius:6px;padding:3px 7px;
    background:color-mix(in srgb, var(--faint) 14%, transparent);color:var(--muted)}
  .item .tag.mat{background:var(--red-soft);color:var(--red-text)}
  .tag.urb{background:color-mix(in srgb, var(--blue) 12%, transparent);color:var(--blue);
    border:1px solid color-mix(in srgb, var(--blue) 30%, transparent)}
  .tag.rural{background:color-mix(in srgb, var(--green) 12%, transparent);color:var(--green);
    border:1px solid color-mix(in srgb, var(--green) 30%, transparent)}
  .item .del{background:transparent;border:none;color:var(--faint);font-size:15px;padding:3px 7px;border-radius:7px}
  .item .del:hover{color:var(--err-bright);background:var(--err-soft)}
  .item .it-edit{background:transparent;border:none;color:var(--faint);font-size:14px;
    padding:3px 6px;border-radius:7px;flex:none}
  .item .it-edit:hover{color:var(--red-bright);background:var(--red-soft)}
  .item .it-onr{background:transparent;border:none;color:var(--teal);font-size:13px;
    padding:3px 6px;border-radius:7px;flex:none}
  .item .it-onr:hover:not(:disabled){background:color-mix(in srgb, var(--teal) 13%, transparent)}
  .item .it-onr:disabled{color:var(--line-2);cursor:not-allowed}
  .item .it-onr.enviado{color:var(--blue)}
  .item .it-proj{background:transparent;border:none;color:var(--violet);font-size:13px;
    padding:3px 6px;border-radius:7px;flex:none;cursor:pointer}
  .item .it-proj:hover{background:color-mix(in srgb, var(--violet) 13%, transparent)}
  .item-dot{width:11px;height:11px;border-radius:50%;flex:none;
    box-shadow:0 0 0 2px var(--panel), inset 0 0 0 1px rgba(0,0,0,.14)}
  .item-dot.vazio{background:var(--line)}
  .empty-list{font-size:11.5px;color:var(--faint);padding:8px 2px}
  .saved-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}

  /* Selos e badges de estado */
  .onr-badge,.morto-badge,.desmembra-badge,.fora-badge,.parcial-badge,.enc-meta{
    display:inline-block;font-family:var(--mono);font-size:9px;font-weight:600;
    padding:1px 6px;border-radius:6px;margin-left:4px;vertical-align:middle;text-decoration:none}
  .onr-badge{background:var(--panel-2);color:var(--faint);border:1px solid var(--line)}
  .onr-badge.env{background:color-mix(in srgb, var(--blue) 14%, transparent);color:var(--blue);border-color:transparent}
  .morto-badge{background:color-mix(in srgb, var(--faint) 16%, transparent);color:var(--faint)}
  .desmembra-badge{background:color-mix(in srgb, var(--teal) 14%, transparent);color:var(--teal)}
  .fora-badge{font-weight:700;background:var(--err-soft);color:var(--err-text);
    border:1px solid color-mix(in srgb, var(--err-bright) 40%, transparent)}
  .enc-meta{font-weight:700;letter-spacing:.03em;text-transform:uppercase;margin-left:6px;
    background:var(--err-soft);color:var(--err-text);
    border:1px solid color-mix(in srgb, var(--err-bright) 40%, transparent)}
  .parcial-badge{font-weight:700;background:color-mix(in srgb, var(--amber) 14%, transparent);
    color:var(--amber-text);border:1px solid color-mix(in srgb, var(--amber) 42%, transparent)}
  .parcial-line{font-family:var(--mono);font-size:9.5px;color:var(--amber-text);margin-top:4px;line-height:1.4;
    background:color-mix(in srgb, var(--amber) 8%, transparent);
    border-left:2px solid color-mix(in srgb, var(--amber) 50%, transparent);
    padding:3px 7px;border-radius:0 5px 5px 0}
  .item.parcial-mun{box-shadow:inset 3px 0 0 color-mix(in srgb, var(--amber) 60%, transparent)}
  .item.fora-mun{box-shadow:inset 3px 0 0 var(--err-bright)}
  .item.morto{opacity:.55}
  .item.morto .nm{text-decoration:line-through;text-decoration-thickness:1px;text-decoration-color:var(--faint)}
  .item .proj-badge{display:inline-block;font-size:9.5px;font-weight:700;padding:1px 6px;border-radius:6px;
    background:color-mix(in srgb, var(--violet) 14%, transparent);color:var(--violet);margin-left:4px}
  .item .itn03-badge{display:inline-block;font-size:9.5px;font-weight:700;padding:1px 6px;border-radius:6px;
    background:color-mix(in srgb, var(--violet) 12%, transparent);color:var(--violet);margin-left:4px}
  .item .itn03-apto{font-size:10px;font-weight:600;margin-left:4px}
  .item .itn03-apto.ok{color:var(--green-text)}
  .item .itn03-apto.no{color:var(--err-text)}
  .inc-badge{display:inline-flex;align-items:center;gap:2px;font-size:9.5px;font-weight:700;letter-spacing:.02em;
    padding:1px 7px;border-radius:99px;cursor:pointer;vertical-align:middle;
    background:color-mix(in srgb, var(--amber) 14%, transparent);color:var(--amber-text);
    border:1px solid color-mix(in srgb, var(--amber) 40%, transparent)}
  .inc-badge:hover{background:color-mix(in srgb, var(--amber) 24%, transparent)}
  .situacao-edit{margin-top:6px;padding-top:12px;border-top:1px solid var(--line)}

  /* Aviso de matrícula encerrada no cadastro */
  .enc-info{margin-bottom:12px;padding:11px 13px;border-radius:var(--r);
    background:color-mix(in srgb, var(--faint) 8%, transparent);
    border:1px solid color-mix(in srgb, var(--faint) 30%, transparent);
    border-left:3px solid var(--faint)}
  .enc-info-h{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:650;color:var(--ink)}
  .enc-ico{color:var(--faint);font-size:13px}
  .enc-info-b{margin-top:5px;font-size:11.5px;line-height:1.55;color:var(--ink)}
  .enc-mut{color:var(--faint);font-family:var(--mono);font-size:9.5px}
  .enc-info.desmembra{border-color:color-mix(in srgb, var(--teal) 38%, transparent);
    border-left-color:var(--teal);background:color-mix(in srgb, var(--teal) 7%, transparent)}
  .enc-info.desmembra .enc-ico{color:var(--teal)}

  /* ─── 13. CHIPS E PROPRIETÁRIOS ─────────────────────────────────── */
  .chips{display:flex;flex-wrap:wrap;gap:5px;min-height:24px;margin-bottom:6px}
  .chips-vazio{font-family:var(--mono);font-size:10px;color:var(--faint);font-style:italic}
  .chip{display:inline-flex;align-items:center;gap:5px;
    background:color-mix(in srgb, var(--teal) 11%, transparent);color:var(--teal);
    font-family:var(--mono);font-size:11px;font-weight:500;padding:3px 5px 3px 9px;border-radius:999px;
    border:1px solid color-mix(in srgb, var(--teal) 30%, transparent)}
  .chip-x{background:none;border:none;color:var(--teal);font-size:14px;line-height:1;padding:0 3px;border-radius:99px}
  .chip-x:hover{background:color-mix(in srgb, var(--teal) 20%, transparent)}
  .chips-add{display:flex;gap:6px}
  .chips-add input{flex:1}
  .gem-models{display:flex;flex-wrap:wrap;gap:5px;min-height:22px}
  .prop-list{display:flex;flex-direction:column;gap:8px}
  .prop-row{display:flex;gap:7px;align-items:center}
  .prop-row .prop-nome{flex:1.3}
  .prop-doc-wrap{flex:1;position:relative;display:flex;align-items:center}
  .prop-doc-wrap .prop-doc{width:100%;padding-right:64px}
  .prop-doc-badge{position:absolute;right:10px;font-family:var(--mono);font-size:8.5px;font-weight:700;
    text-transform:uppercase;letter-spacing:.04em;color:var(--faint);pointer-events:none}
  .prop-doc-badge.ok{color:var(--green)}
  .prop-doc-badge.bad{color:var(--err-bright)}
  .prop-doc.doc-ok{border-color:color-mix(in srgb, var(--green) 55%, transparent)}
  .prop-doc.doc-bad{border-color:color-mix(in srgb, var(--err-bright) 55%, transparent)}
  .prop-del{flex:none;width:34px;height:38px;background:var(--panel);border:1px solid var(--line);
    border-radius:10px;color:var(--faint);font-size:16px;line-height:1}
  .prop-del:hover{border-color:var(--err-bright);color:var(--err-bright);background:var(--err-soft)}

  /* ─── 14. SELETOR DE COR ────────────────────────────────────────── */
  .cor-box{margin-top:18px;padding-top:16px;border-top:1px dashed var(--line)}
  .cor-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-top:4px}
  .cor-sub-lbl{font-family:var(--mono);font-size:9.5px;letter-spacing:.05em;text-transform:uppercase;
    color:var(--muted);margin-top:8px}
  .cor-sw{width:100%;aspect-ratio:1/1;min-height:26px;border-radius:9px;border:2px solid transparent;
    cursor:pointer;padding:0;box-shadow:inset 0 0 0 1px rgba(0,0,0,.12);
    transition:transform .12s,box-shadow .12s}
  .cor-sw:hover{transform:scale(1.1)}
  .cor-sw.sel{border-color:var(--panel);
    box-shadow:0 0 0 2px var(--ink), inset 0 0 0 1px rgba(0,0,0,.1);transform:scale(1.05)}
  .cor-hint{font-family:var(--mono);font-size:9.5px;color:var(--faint);line-height:1.5;margin-top:10px}

  /* ─── 15. SLIDER DE INTENSIDADE ─────────────────────────────────── */
  .op-wrap{display:flex;align-items:center;gap:11px;margin-top:12px}
  .op-lbl{font-family:var(--mono);font-size:10px;text-transform:uppercase;letter-spacing:.06em;
    color:var(--faint);flex:none}
  .op-range{-webkit-appearance:none;appearance:none;flex:1;height:5px;border-radius:99px;
    background:linear-gradient(90deg,var(--line),var(--line-2));outline:none;cursor:pointer}
  .op-range::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;
    background:var(--red-bright);border:3px solid var(--panel);
    box-shadow:0 1px 5px rgba(0,0,0,.28);cursor:pointer;transition:transform .12s}
  .op-range::-webkit-slider-thumb:hover{transform:scale(1.12)}
  .op-range::-moz-range-thumb{width:16px;height:16px;border-radius:50%;background:var(--red-bright);
    border:3px solid var(--panel);cursor:pointer}
  .cor-pop .op-range{width:100%;margin:2px 0}

  /* ─── 16. ACORDEÕES ONR ─────────────────────────────────────────── */
  .onr-box{margin-top:14px}
  .onr-accordion{border:1px solid var(--line);border-radius:var(--r-l);background:var(--panel);
    overflow:hidden;box-shadow:var(--sh-1)}
  .onr-accordion>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:9px;
    padding:14px 16px;font-family:var(--disp);font-size:13.5px;font-weight:650;color:var(--ink);
    transition:background .15s}
  .onr-accordion>summary:hover{background:var(--panel-2)}
  .onr-accordion>summary::-webkit-details-marker{display:none}
  .onr-accordion>summary::after{content:'';margin-left:auto;width:9px;height:9px;flex:none;
    border-right:2px solid var(--faint);border-bottom:2px solid var(--faint);
    transform:rotate(45deg) translateY(-2px);transition:transform .2s}
  .onr-accordion[open]>summary::after{transform:rotate(225deg) translateY(-2px)}
  .onr-hint-active{font-family:var(--mono);font-size:10px;font-weight:400;color:var(--faint)}
  .onr-body{padding:6px 16px 16px;border-top:1px solid var(--line)}
  .onr-sub{margin-top:11px;border:1px solid var(--line);border-radius:var(--r);overflow:hidden}
  .onr-sub>summary{list-style:none;cursor:pointer;padding:10px 13px;
    font-family:var(--disp);font-size:11.5px;font-weight:700;letter-spacing:.02em;
    color:var(--muted);background:var(--panel-2);transition:color .15s}
  .onr-sub>summary:hover{color:var(--ink)}
  .onr-sub>summary::-webkit-details-marker{display:none}
  .onr-sub>summary::after{content:'+';float:right;color:var(--faint);font-weight:700;font-size:13px}
  .onr-sub[open]>summary::after{content:'–'}
  .onr-sub .form-grid{padding:11px 12px 12px}
  .qual-list{padding:9px 11px;display:flex;flex-direction:column;gap:8px}
  .qual-card{border:1px solid var(--line);border-radius:var(--r-s);padding:9px 11px;background:var(--panel)}
  .qual-head{display:flex;align-items:center;gap:7px;margin-bottom:5px;font-size:12.5px}
  .qual-tag{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
    padding:2px 7px;border-radius:99px}
  .qual-tag.adq{background:color-mix(in srgb, var(--green) 13%, transparent);color:var(--green-text)}
  .qual-tag.alien{background:var(--err-soft);color:var(--err-text)}
  .qual-row{display:flex;gap:8px;font-size:11.5px;line-height:1.5}
  .qual-k{flex:0 0 116px;color:var(--faint);font-family:var(--mono);font-size:10px;
    text-transform:uppercase;letter-spacing:.04em;padding-top:1px}
  .qual-v{flex:1;color:var(--ink)}

  /* ─── 17. CAIXA DO MUNICÍPIO ────────────────────────────────────── */
  .muni-box{margin-top:18px;padding-top:16px;border-top:1px dashed var(--line)}
  .muni-label{display:flex;align-items:center;gap:7px;color:var(--faint)}
  .muni-row{display:flex;gap:9px;margin-top:2px}
  #muni-status{margin-top:10px}

  /* ─── 18. ZONAS KML / IA ────────────────────────────────────────── */
  .kml-zone{margin-top:11px;display:flex;align-items:center;gap:10px;padding:12px 14px;
    border:1.5px dashed var(--line-2);border-radius:var(--r);color:var(--muted);cursor:pointer;
    font-size:12.5px;background:var(--panel);
    transition:border-color .15s,color .15s,background .15s}
  .kml-zone:hover,.kml-zone.drag{border-color:var(--red-bright);color:var(--ink);
    background:color-mix(in srgb, var(--red-bright) 4%, var(--panel))}
  .kml-zone b{color:var(--ink);font-weight:650}
  .kml-zone.loaded{border-style:solid;border-color:color-mix(in srgb, var(--green) 45%, transparent);
    color:var(--green-text)}
  .kml-zone.lote{margin-top:8px}
  .kml-zone.lote:hover,.kml-zone.lote.drag{border-color:var(--teal);color:var(--ink);
    background:color-mix(in srgb, var(--teal) 5%, var(--panel))}
  .kml-zone.ia{margin-top:8px}
  .kml-zone.ia:hover,.kml-zone.ia.drag{border-color:var(--violet);color:var(--ink);
    background:color-mix(in srgb, var(--violet) 6%, var(--panel))}
  .zone-multi{font-family:var(--mono);font-size:9.5px;color:var(--faint);opacity:.9}

  /* ─── 19. CARTÕES DE AÇÃO (ONR / Carga) ─────────────────────────── */
  .vx-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
  .vx-act-card{position:relative;border:1px solid var(--line);border-radius:var(--r-l);
    background:var(--panel);padding:20px;box-shadow:var(--sh-1);overflow:hidden;
    transition:border-color .18s,transform .18s,box-shadow .18s}
  .vx-act-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;
    background:linear-gradient(90deg, var(--red-bright), transparent 70%);
    opacity:0;transition:opacity .18s}
  .vx-act-card:hover{border-color:color-mix(in srgb, var(--red-bright) 28%, var(--line));
    transform:translateY(-2px);box-shadow:var(--sh-2)}
  .vx-act-card:hover::before{opacity:1}
  .vx-act-h{font-family:var(--titles);font-weight:700;font-size:14.5px;color:var(--ink);margin-bottom:6px}
  .vx-act-d{font-size:11.5px;color:var(--muted);line-height:1.55;margin:0 0 15px}
  .vx-act-btn{width:100%;margin-bottom:8px}
  .vx-act-btn2{width:100%;margin-bottom:7px;font-size:12px}
  .vx-act-card .vx-act-btn2:last-child{margin-bottom:0}

  /* ─── 20. DROPZONE (Importar) ───────────────────────────────────── */
  .dropzone{border:2px dashed var(--line-2);border-radius:var(--r-xl);background:var(--panel);
    padding:44px 24px;text-align:center;cursor:pointer;transition:.18s;
    display:flex;flex-direction:column;align-items:center;gap:12px;box-shadow:var(--sh-1)}
  .dropzone:hover,.dropzone:focus-visible{border-color:var(--red-bright);
    background:color-mix(in srgb, var(--red-bright) 4%, var(--panel));outline:none}
  .dropzone.drag{border-color:var(--red-bright);
    background:color-mix(in srgb, var(--red-bright) 8%, var(--panel));
    transform:translateY(-2px);box-shadow:0 18px 38px -20px color-mix(in srgb, var(--red-bright) 70%, transparent)}
  .dz-ic{width:60px;height:60px;border-radius:50%;display:grid;place-items:center;
    background:var(--panel-2);border:1px solid var(--line);color:var(--red-bright);transition:.18s}
  .dropzone.drag .dz-ic{background:var(--red-bright);color:#fff;border-color:var(--red-bright);transform:scale(1.06)}
  .dz-main{font-family:var(--titles);font-weight:650;font-size:15px;color:var(--ink)}
  .dz-link{color:var(--red-bright);text-decoration:underline;text-underline-offset:3px}
  .dz-sub{font-size:11.5px;color:var(--muted);max-width:460px;line-height:1.55}
  .dz-badges{display:flex;gap:6px;margin-top:2px;flex-wrap:wrap;justify-content:center}
  .dz-badge{font-family:var(--mono);font-size:10px;font-weight:600;letter-spacing:.04em;color:var(--muted);
    background:var(--panel-2);border:1px solid var(--line);border-radius:99px;padding:4px 11px}

  /* ─── 21. MAPA · SOBREPOSIÇÕES E PAINÉIS ────────────────────────── */
  .map-wrap{position:relative;min-width:0}
  #map{position:absolute;inset:0;background:#0A0D11}
  .overlay{position:absolute;inset:0;display:grid;place-items:center;z-index:4;color:var(--faint);
    font-family:var(--mono);font-size:12px;text-align:center;pointer-events:none}

  /* Leitura do imóvel focado */
  .readout{position:absolute;left:14px;bottom:80px;z-index:5;display:none;
    background:rgba(10,14,19,.86);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
    border:1px solid rgba(255,255,255,.14);border-radius:var(--r);padding:9px 14px;
    font-family:var(--mono);font-size:11px;color:#C6D2DE;box-shadow:0 8px 26px rgba(0,0,0,.45)}
  .readout b{color:#fff;font-weight:600}
  .readout .dot{color:var(--red-bright)}

  /* Painel de foco no imóvel — dados do memorial (instrumento cartográfico) */
  .foco-panel{position:absolute;top:104px;left:14px;right:auto;z-index:9;display:none;width:314px;max-width:calc(100vw - 28px);
    max-height:min(360px, calc(100% - 118px));overflow:auto;background:var(--ov-bg);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
    border:1px solid var(--line);border-radius:var(--r-l);box-shadow:var(--sh-2);color:var(--ink);
    font-family:var(--disp);font-size:12px;line-height:1.4}
  .foco-panel.show{display:block}
  .foco-close{position:absolute;top:8px;right:8px;width:26px;height:26px;border:none;border-radius:8px;cursor:pointer;
    background:transparent;color:var(--faint);font-size:18px;line-height:1}
  .foco-close:hover{background:var(--panel-2);color:var(--ink)}
  .foco-head{padding:11px 14px 7px;cursor:grab;-webkit-user-select:none;user-select:none;touch-action:none}
  .foco-head:active{cursor:grabbing}
  .foco-panel.dragging{cursor:grabbing;box-shadow:var(--sh-3)}
  .foco-kick{font-family:var(--mono);font-size:10px;letter-spacing:.14em;color:var(--red-text);text-transform:uppercase}
  .foco-title{font-family:var(--titles);font-weight:600;font-size:15.5px;margin-top:2px;color:var(--ink)}
  .foco-sub{color:var(--muted);font-size:11.5px;margin-top:2px}
  .foco-metrics{display:flex;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
  .foco-metrics>div{flex:1;padding:8px 14px}
  .foco-metrics>div+div{border-left:1px solid var(--line)}
  .foco-metrics b{font-family:var(--titles);font-size:17px;font-weight:600;display:block;color:var(--ink)}
  .foco-metrics span{font-family:var(--mono);font-size:9.5px;letter-spacing:.1em;color:var(--faint);text-transform:uppercase}
  .foco-area2{font-family:var(--mono);font-size:10.5px;color:var(--muted);padding:6px 14px;border-bottom:1px solid var(--line)}
  .foco-sec{padding:8px 14px;border-bottom:1px solid var(--line)}
  .foco-sec-t{font-family:var(--mono);font-size:9.5px;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;margin-bottom:5px}
  table.foco-vtx{width:100%;border-collapse:collapse;font-family:var(--mono);font-size:11px}
  table.foco-vtx th{text-align:left;color:var(--faint);font-weight:500;font-size:9.5px;letter-spacing:.06em;padding:0 0 4px}
  table.foco-vtx td{padding:2px 0;color:var(--ink)}
  table.foco-vtx td:first-child{color:var(--red-text);font-weight:600}
  table.foco-vtx tr.susp td{color:var(--amber-text)}
  table.foco-vtx tr.susp td:first-child::after{content:" \26A0"}
  .foco-conf-item{display:flex;gap:7px;padding:3px 0;color:var(--ink)}
  .foco-conf-item i{color:var(--red-bright);font-style:normal}
  .foco-inc-item{padding:5px 8px;border-radius:8px;margin-bottom:5px;font-size:11.5px;background:var(--panel-2)}
  .foco-inc-item.erro{border-left:3px solid var(--err-bright)}
  .foco-inc-item.alerta{border-left:3px solid var(--amber)}
  .foco-note{padding:8px 14px;color:var(--muted);font-size:10.5px}
  .foco-note b{color:var(--ink)}
  .foco-reopen{position:absolute;top:104px;left:14px;right:auto;z-index:9;display:none;border:1px solid var(--line);
    background:var(--ov-bg);color:var(--ink);border-radius:10px;padding:7px 11px;font-size:12px;cursor:pointer;
    font-family:var(--disp);box-shadow:var(--sh-1)}
  .foco-reopen.show{display:inline-flex;align-items:center;gap:6px}
  @media(max-width:880px){
    .foco-panel{top:auto;left:8px;right:8px;bottom:calc(var(--vx-bottombar) + 8px);width:auto;max-height:52%}
    .foco-reopen{top:auto;left:12px;right:auto;bottom:calc(var(--vx-bottombar) + 12px)}
  }

  /* Selo de pertencimento (pílula topo-centro) */
  .muni-badge{position:absolute;left:50%;top:14px;transform:translateX(-50%);z-index:8;display:none;
    max-width:min(90%,420px);text-align:center;
    font-family:var(--disp);font-weight:600;font-size:12px;line-height:1.4;
    padding:8px 18px;border-radius:999px;
    -webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
    box-shadow:0 10px 30px -10px rgba(0,0,0,.5);
    background:rgba(12,16,22,.88);color:#fff;border:1px solid rgba(255,255,255,.16)}
  .muni-badge.dentro{background:rgba(10,38,24,.88);border-color:color-mix(in srgb, var(--green) 55%, transparent);color:#8CE7B6}
  .muni-badge.parcial{background:rgba(44,34,10,.88);border-color:color-mix(in srgb, var(--amber) 60%, transparent);color:#F5CF74}
  .muni-badge.fora{background:rgba(46,14,16,.9);border-color:color-mix(in srgb, var(--err-bright) 60%, transparent);color:#FF9E9E}
  .muni-badge b{color:#fff}

  /* Painel flutuante (Visão geral / KML) */
  .overview-panel{position:absolute;top:60px;right:14px;z-index:6;width:288px;max-height:calc(100% - 74px);
    display:none;flex-direction:column;overflow:hidden;color:var(--ink);
    background:var(--ov-bg);-webkit-backdrop-filter:blur(14px) saturate(1.3);backdrop-filter:blur(14px) saturate(1.3);
    border:1px solid var(--line);border-radius:var(--r-l);box-shadow:var(--sh-2)}
  .overview-panel.show{display:flex}
  .overview-panel.dragging{transition:none;cursor:grabbing}
  .ovh{display:flex;align-items:flex-start;justify-content:space-between;padding:14px 16px;
    border-bottom:1px solid var(--line);cursor:grab;user-select:none;-webkit-user-select:none;touch-action:none}
  .ovh:active{cursor:grabbing}
  .ovh-title{font-family:var(--titles);font-size:13.5px;font-weight:700}
  .ovh-sub{font-family:var(--mono);font-size:10px;color:var(--muted);margin-top:3px}
  .ov-close{background:transparent;border:none;color:var(--faint);font-size:18px;line-height:1;
    padding:2px 6px;border-radius:7px}
  .ov-close:hover{color:var(--red-bright);background:var(--red-soft)}
  .legend{display:flex;gap:14px;flex-wrap:wrap;padding:10px 16px;border-bottom:1px solid var(--line);
    font-family:var(--mono);font-size:10px;color:var(--muted)}
  .legend span{display:flex;align-items:center;gap:6px}
  .legend .sw{width:14px;height:10px;border-radius:3px;display:inline-block}
  .legend .sw.normal{background:color-mix(in srgb, var(--green) 35%, transparent);border:1px solid var(--green)}
  .legend .sw.over{background:color-mix(in srgb, var(--err-bright) 45%, transparent);border:1px solid var(--err-bright)}
  .legend .sw.sel{background:rgba(245,158,11,.45);border:1px solid #F59E0B}
  .legend .sw.muni{background:color-mix(in srgb, var(--blue) 25%, transparent);border:1px solid var(--blue)}
  .ov-hint{font-family:var(--mono);font-size:9.5px;color:var(--faint);line-height:1.45;padding:9px 16px 0}
  .ov-search{display:flex;gap:6px;padding:9px 12px 2px}
  .ov-search input{flex:1;background:var(--panel-2);border:1px solid var(--line);border-radius:9px;
    color:var(--ink);font-size:12px;padding:8px 10px;outline:none}
  .ov-search input:focus{border-color:var(--teal);box-shadow:var(--ring-teal)}
  .ov-search input::placeholder{color:var(--faint)}
  .ov-search button{flex:none;width:32px;height:36px;background:var(--panel-2);border:1px solid var(--line);
    border-radius:9px;color:var(--faint);font-size:16px;line-height:1}
  .ov-search button:hover{border-color:var(--red-bright);color:var(--red-bright)}
  .ov-itn03{padding:7px 12px 2px}
  .btn-itn03{width:100%;padding:9px 11px;border-radius:9px;font-weight:650;font-size:12px;
    border:1px solid color-mix(in srgb, var(--green) 45%, transparent);
    background:color-mix(in srgb, var(--green) 10%, transparent);color:var(--green-text)}
  .btn-itn03:hover{background:color-mix(in srgb, var(--green) 17%, transparent)}
  .btn-itn03:disabled{opacity:.55;cursor:default}
  .ov-overlaps{overflow-y:auto;padding:9px 12px}
  .ov-overlaps .ttl{font-family:var(--mono);font-size:9.5px;letter-spacing:.09em;text-transform:uppercase;
    color:var(--faint);padding:4px 4px 8px}
  .ov-row{position:relative;padding:10px 12px;margin-bottom:7px;cursor:pointer;border-radius:var(--r-s);
    border:1px solid color-mix(in srgb, var(--err-bright) 24%, transparent);
    background:color-mix(in srgb, var(--err-bright) 5%, transparent);transition:background .15s}
  .ov-row:hover{background:color-mix(in srgb, var(--err-bright) 11%, transparent)}
  .ov-row .pair{font-size:12px;font-weight:550;line-height:1.4}
  .ov-row .amt{font-family:var(--mono);font-size:10.5px;color:var(--err-text);margin-top:3px}
  .ov-row .row-rep{position:absolute;top:8px;right:8px;background:var(--err);border:none;color:#fff;
    font-family:var(--mono);font-size:9px;letter-spacing:.04em;padding:4px 8px;border-radius:6px;
    opacity:0;transition:opacity .12s}
  .ov-row:hover .row-rep{opacity:1}
  .ov-row .row-rep:hover{background:var(--err-bright)}
  .ov-tag{display:inline-block;font-family:var(--mono);font-size:8.5px;font-weight:700;letter-spacing:.03em;
    text-transform:uppercase;padding:1px 7px;border-radius:99px;vertical-align:middle;margin-left:4px;white-space:nowrap}
  .ov-tag.material{background:color-mix(in srgb, var(--err-bright) 15%, transparent);color:var(--err-text);
    border:1px solid color-mix(in srgb, var(--err-bright) 45%, transparent)}
  .ov-tag.formal{background:rgba(245,158,11,.15);color:var(--amber-text);
    border:1px solid color-mix(in srgb, var(--amber) 45%, transparent)}
  .ov-none{font-family:var(--mono);font-size:11px;color:var(--green-text);padding:10px 6px}
  .ov-foot{padding:12px;border-top:1px solid var(--line);display:flex;flex-direction:column;gap:7px}
  .btn-report{width:100%;padding:11px;border-radius:10px;font-size:12.5px;font-weight:600;color:#fff;
    background:linear-gradient(135deg, var(--red-bright) 0%, var(--red-deep) 100%);
    box-shadow:0 6px 16px -8px color-mix(in srgb, var(--red-bright) 60%, transparent)}
  .btn-report:hover{filter:brightness(1.06)}
  .ov-reopen{position:absolute;top:60px;right:14px;z-index:6;display:none;align-items:center;gap:8px;
    background:var(--ov-bg);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
    border:1px solid var(--line);color:var(--ink);
    border-radius:var(--r);padding:9px 14px;font-size:12px;font-weight:600;box-shadow:var(--sh-2)}
  .ov-reopen:hover{border-color:var(--red-bright)}
  .ov-reopen.show{display:flex}

  /* Painel de importação KML */
  .kml-panel{width:328px}
  .kml-rows{overflow-y:auto;padding:10px 12px;flex:1}
  .kml-row{padding:10px;border:1px solid var(--line);border-radius:var(--r-s);margin-bottom:8px;background:var(--panel-2)}
  .kml-row.sel{border-color:var(--red-bright)}
  .kml-row .top{display:flex;align-items:center;gap:7px;margin-bottom:7px}
  .kml-row .idx{font-family:var(--mono);font-size:10px;color:var(--red-bright);font-weight:700;flex:none}
  .kml-row .meta{font-family:var(--mono);font-size:9.5px;color:var(--faint);margin-left:auto}
  .kml-row .inp{display:grid;grid-template-columns:1fr 96px;gap:7px}
  .kml-row input,.kml-row select{padding:7px 9px;font-size:11.5px;border-radius:8px}
  .kml-foot{padding:12px;border-top:1px solid var(--line)}

  /* Barra de seleção (Ctrl+clique) */
  .sel-bar{position:absolute;bottom:80px;left:50%;transform:translateX(-50%);z-index:7;display:none;
    align-items:center;gap:12px;padding:9px 12px 9px 17px;border-radius:999px;
    background:rgba(12,16,22,.92);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);
    border:1px solid rgba(245,158,11,.75);box-shadow:0 10px 30px rgba(0,0,0,.45)}
  .sel-bar.show{display:flex}
  .sel-count{font-family:var(--mono);font-size:12px;color:#A9B6C4;white-space:nowrap}
  .sel-count b{color:#F59E0B;font-size:14px}
  .sel-rep{background:var(--red-bright);color:#fff;padding:8px 15px;border-radius:999px;font-size:12.5px;font-weight:600}
  .sel-rep:hover{filter:brightness(1.1)}
  .sel-clear{background:transparent;border:1px solid rgba(255,255,255,.22);color:#C6D2DE;
    padding:8px 13px;border-radius:999px;font-size:12px}
  .sel-clear:hover{border-color:#F59E0B;color:#fff}

  /* Rótulos sobre os polígonos */
  .map-chip{position:absolute;transform:translate(-50%,-50%);z-index:1;white-space:nowrap;pointer-events:none;
    background:rgba(12,16,22,.84);color:#fff;
    font-family:var(--mono);font-size:11px;font-weight:600;letter-spacing:.02em;
    padding:3px 9px;border-radius:7px;border:1px solid color-mix(in srgb, var(--red-bright) 65%, transparent);
    text-shadow:0 1px 2px rgba(0,0,0,.85)}
  .map-chip.clic{pointer-events:auto;cursor:pointer;transition:transform .1s ease,background .12s ease,border-color .12s ease}
  .map-chip.clic:hover{background:var(--teal);border-color:rgba(255,255,255,.55);
    transform:translate(-50%,-50%) scale(1.08);box-shadow:0 4px 14px rgba(0,0,0,.45);z-index:5}
  .map-chip.hover{transform:translate(-50%,calc(-50% - 20px));background:var(--teal);
    border-color:rgba(255,255,255,.35);font-family:var(--disp);font-weight:600;
    box-shadow:0 4px 14px rgba(0,0,0,.4);animation:chipFade .14s ease-out}
  .map-chip.vizinho{background:rgba(180,83,9,.94);border-color:rgba(251,191,36,.7);color:#fff;
    font-family:var(--disp);font-weight:700;box-shadow:0 3px 12px rgba(0,0,0,.45)}
  .map-chip.morto{background:rgba(86,94,104,.42);color:rgba(255,255,255,.72);
    border:1px dashed rgba(255,255,255,.32);
    text-decoration:line-through;font-style:italic;font-weight:500;text-shadow:0 1px 2px rgba(0,0,0,.7)}
  .map-chip.morto.clic:hover{background:rgba(86,94,104,.85);border-color:rgba(255,255,255,.5);color:#fff}
  @keyframes chipFade{from{opacity:0;transform:translate(-50%,calc(-50% - 12px))}to{opacity:1;transform:translate(-50%,calc(-50% - 20px))}}

  /* Popup do mapa acima de tudo */
  .gm-style .gm-style-iw-c,.gm-style .gm-style-iw-t,.gm-style .gm-style-iw{z-index:99999 !important}

  /* ─── 22. CONTROLES 3D ──────────────────────────────────────────── */
  .ctrl-3d{position:absolute;top:60px;left:14px;z-index:7;display:flex;flex-direction:column;gap:6px;align-items:flex-start}
  .c3d-btn{display:flex;align-items:center;gap:7px;background:var(--panel);color:var(--ink);
    border:1px solid var(--line);border-radius:10px;padding:9px 13px;font-weight:700;font-size:13px;
    box-shadow:var(--sh-1)}
  .c3d-btn:hover{background:var(--panel-2);border-color:var(--line-2)}
  .c3d-row{display:flex;gap:5px;background:var(--panel);border:1px solid var(--line);
    border-radius:10px;padding:4px;box-shadow:var(--sh-1)}
  .c3d-mini{background:var(--panel-2);border:none;border-radius:7px;padding:6px 10px;font-size:13px;
    color:var(--ink);line-height:1}
  .c3d-mini:hover{background:var(--line)}
  .c3d-mini.on{background:var(--ink);color:var(--panel);outline:2px solid var(--teal)}
  .c3d-mini.wide{font-weight:600}

  /* Modal 3D fotorrealista */
  .modal-3d-card{position:fixed;inset:3vh 3vw;background:#0A0D11;border-radius:var(--r-l);overflow:hidden;
    display:flex;flex-direction:column;box-shadow:var(--sh-3)}
  .modal-3d-bar{display:flex;align-items:center;gap:8px;padding:10px 14px;background:#10151C;color:#E8EEF6;
    border-bottom:1px solid #232E3C;font-family:var(--titles);font-weight:700;font-size:14px}
  .modal-3d-host{flex:1;min-height:0;position:relative;background:#0A0D11}
  .modal-3d-host gmp-map-3d,.modal-3d-host .gmp-map-3d{width:100%;height:100%;display:block}
  .modal-3d-msg{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    text-align:center;padding:24px;color:#C6D2DE;font-size:14px;line-height:1.6}
  .modal-3d-foot{display:flex;align-items:center;gap:10px;justify-content:center;flex-wrap:wrap;
    padding:9px 12px;background:#10151C;color:#94A3B8;border-top:1px solid #232E3C;font-size:12px}
  .modal-3d-foot.alert{color:#FBBF24}
  .m3d-link{display:inline-flex;align-items:center;gap:5px;background:#243040;color:#E8EEF6;
    text-decoration:none;border-radius:8px;padding:6px 12px;font-weight:600;font-size:12px}
  .m3d-link:hover{background:#31445A}
  .m3d-legend{position:absolute;top:56px;left:12px;z-index:4;background:rgba(9,12,16,.86);
    border:1px solid #232E3C;border-radius:var(--r);padding:10px 12px;max-width:240px;max-height:62%;
    overflow:auto;font-size:12px;color:#E8EEF6;box-shadow:0 6px 18px rgba(0,0,0,.45)}
  .m3d-legend h4{margin:0 0 6px;font-family:var(--titles);font-size:10.5px;font-weight:700;
    letter-spacing:.07em;text-transform:uppercase;color:#94A3B8}
  .m3d-legend .row{display:flex;align-items:center;gap:7px;margin:3px 0;line-height:1.2}
  .m3d-legend .sw{width:13px;height:13px;border-radius:4px;flex:none;border:1px solid rgba(255,255,255,.28)}

  /* Legenda 2D das matrículas */
  .ov-legend-2d{margin:10px;background:rgba(9,12,16,.88);border:1px solid rgba(255,255,255,.08);
    border-radius:var(--r);padding:11px 13px;max-width:230px;max-height:52vh;overflow:auto;
    font-size:12px;color:#E8EEF6;box-shadow:0 12px 30px -14px rgba(0,0,0,.6)}
  .ov-legend-2d h4{margin:0 0 6px;font-family:var(--titles);font-size:10.5px;font-weight:700;
    letter-spacing:.07em;text-transform:uppercase;color:#94A3B8}
  .ov-legend-2d .row{display:flex;align-items:center;gap:7px;margin:3px 0;line-height:1.2}
  .ov-legend-2d .sw{width:13px;height:13px;border-radius:4px;flex:none;border:1px solid rgba(255,255,255,.28)}

  /* ─── 23. POPUP (InfoWindow) ────────────────────────────────────── */
  .cor-pop{font-family:var(--disp);min-width:200px;color:#152030}
  .cor-pop-t{font-size:13px;font-weight:700;line-height:1.25}
  .cor-pop-sub{font-size:11px;color:#64748B;margin:1px 0 9px}
  .cor-pop-lbl{font-size:9.5px;text-transform:uppercase;letter-spacing:.06em;color:#7C8BA0;margin-bottom:6px}
  .cor-pop-grid{display:grid;grid-template-columns:repeat(6,22px);gap:6px}
  .cor-pop .cor-sw{width:22px;height:22px;aspect-ratio:auto;min-height:0}
  .cor-pop-clear{margin-top:11px;width:100%;font-size:11px;font-weight:600;color:var(--red-text);
    background:#fff;border:1px solid #E3E8F0;border-radius:8px;padding:6px}
  .cor-pop-clear:hover{background:color-mix(in srgb, var(--red-bright) 8%, #fff);border-color:var(--red)}
  .cor-pop-acc{margin-top:2px}
  .cor-pop-acc>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:6px;
    margin-bottom:0;padding:4px 0;user-select:none}
  .cor-pop-acc>summary::-webkit-details-marker{display:none}
  .cor-pop-acc>summary::after{content:'▾';margin-left:auto;font-size:11px;transition:transform .2s}
  .cor-pop-acc[open]>summary::after{transform:rotate(180deg)}
  .cor-pop-acc>summary:hover{color:#0E8F80}
  .ip-box{margin:2px 0 11px;padding:10px 11px;background:#F4F6FA;border:1px solid #E3E8F0;
    border-radius:9px;display:flex;flex-direction:column;gap:4px}
  .ip-row{display:flex;gap:8px;font-size:11.5px;line-height:1.4}
  .ip-k{flex:none;width:78px;color:#7C8BA0;font-family:var(--mono);font-size:9.5px;
    text-transform:uppercase;letter-spacing:.04em;padding-top:1px}
  .ip-v{flex:1;color:#152030;font-weight:600;word-break:break-word}
  .ip-inc{margin-top:9px;padding-top:9px;border-top:1px dashed rgba(0,0,0,.16)}
  .ip-inc-h{font-size:11.5px;font-weight:700;color:#8A5C07;margin-bottom:6px}
  .ip-inc-row{display:flex;gap:6px;align-items:flex-start;font-size:11px;line-height:1.4;margin-bottom:4px}
  .ip-inc-row .inc-msg{color:#1A2330}
  .ip-inc-btn{margin-top:5px;font-size:11px;font-weight:600;color:#fff;background:var(--err);
    border:none;border-radius:8px;padding:6px 11px}
  .ip-inc-btn:hover{background:var(--err-bright)}
  /* InfoWindow no modo escuro */
  body.dark-mode .gm-style .gm-style-iw-c,
  body.dark-mode .gm-style .gm-style-iw-d{background:#111823 !important}
  body.dark-mode .gm-style .gm-style-iw-d{overflow:auto !important}
  body.dark-mode .gm-style .gm-style-iw-t::after{background:linear-gradient(45deg,#111823 50%,rgba(0,0,0,0) 51%) !important}
  body.dark-mode .gm-style .gm-style-iw-tc::after{background:#111823 !important}
  body.dark-mode .gm-style .gm-style-iw-c button img,
  body.dark-mode .gm-style .gm-ui-hover-effect img{filter:invert(1) brightness(1.6) !important}
  body.dark-mode .cor-pop{color:#E8EEF6}
  body.dark-mode .cor-pop-sub{color:#97A6B8}
  body.dark-mode .cor-pop-lbl{color:#97A6B8}
  body.dark-mode .ip-box{background:#18212E;border-color:#243040}
  body.dark-mode .ip-k{color:#97A6B8}
  body.dark-mode .ip-v{color:#E8EEF6}
  body.dark-mode .ip-inc{border-top-color:rgba(255,255,255,.16)}
  body.dark-mode .ip-inc-h{color:#F0C14B}
  body.dark-mode .ip-inc-row .inc-msg{color:#CDD6E0}
  body.dark-mode .ip-inc-row .inc-msg b{color:#fff}
  body.dark-mode .cor-pop-clear{background:#18212E;border-color:#243040;color:var(--red-text)}
  body.dark-mode .cor-pop-clear:hover{background:color-mix(in srgb, var(--red-bright) 14%, var(--panel));border-color:var(--red)}

  /* ─── 24. MODAIS ────────────────────────────────────────────────── */
  .modal-ov{position:fixed;inset:0;z-index:1200;display:none;align-items:center;justify-content:center;
    padding:18px;background:color-mix(in srgb, #070B12 58%, transparent);
    -webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px)}
  .modal-ov.show{display:flex}
  .swal2-container{z-index:100050 !important}
  .modal-card{width:100%;max-width:440px;display:flex;flex-direction:column;max-height:calc(100vh - 36px);
    background:var(--panel);color:var(--ink);border:1px solid var(--line);
    border-radius:var(--r-xl);overflow:hidden;animation:modalIn .2s cubic-bezier(.2,.9,.3,1.2);
    box-shadow:var(--sh-3), inset 0 1px 0 rgba(255,255,255,.05)}
  @keyframes modalIn{from{opacity:0;transform:translateY(14px) scale(.97)}to{opacity:1;transform:none}}
  .modal-h{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--line)}
  .modal-h h3{margin:0;font-family:var(--titles);font-size:15.5px;font-weight:700}
  .modal-x{background:none;border:none;color:var(--faint);font-size:22px;line-height:1;
    padding:2px 8px;border-radius:8px}
  .modal-x:hover{color:var(--red-bright);background:var(--red-soft)}
  .modal-b{padding:20px;display:flex;flex-direction:column;gap:13px;overflow-y:auto}
  .modal-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .modal-f{display:flex;gap:10px;padding:14px 20px;border-top:1px solid var(--line);justify-content:flex-end}
  .modal-f .btn-primary,.modal-f .btn-ghost{width:auto;flex:none;padding:10px 18px}
  #modal-edit .modal-card{max-width:1020px}
  #modal-edit .modal-b{padding:16px 20px;gap:14px}
  #modal-edit .onr-box{margin:0}
  #modal-edit .onr-accordion{border:1px solid var(--line);border-radius:var(--r);overflow:hidden;box-shadow:none}

  /* Edição em duas colunas */
  .ed-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start}
  .ed-col{display:flex;flex-direction:column;gap:14px;min-width:0}
  .ed-section{border:1px solid var(--line);border-radius:var(--r);background:var(--bg);overflow:hidden}
  .ed-section>.ed-sec-head{display:flex;align-items:center;gap:8px;padding:11px 14px;
    background:var(--panel);border-bottom:1px solid var(--line)}
  .ed-section>.ed-sec-head .ed-sec-ic{display:flex;color:var(--red-bright)}
  .ed-section>.ed-sec-head h4{margin:0;font-family:var(--titles);font-size:11px;font-weight:700;
    text-transform:uppercase;letter-spacing:.07em;color:var(--ink)}
  .ed-section>.ed-sec-head .ed-sec-sub{margin-left:auto;font-size:10.5px;color:var(--faint)}
  .ed-section>.ed-sec-body{padding:14px;display:flex;flex-direction:column;gap:11px}
  .ed-mapear-hint{margin-bottom:9px;padding:10px 12px;border-radius:var(--r-s);font-size:11.5px;line-height:1.5;
    background:color-mix(in srgb, var(--blue) 9%, transparent);
    border:1px solid color-mix(in srgb, var(--blue) 32%, transparent);color:var(--blue)}
  .ed-geo-box{margin-top:11px;border:1px solid var(--line);border-radius:var(--r);padding:11px}
  .ed-geo-h{font-family:var(--mono);font-size:9.5px;letter-spacing:.07em;text-transform:uppercase;
    color:var(--faint);margin-bottom:7px}
  .ed-geo-text{width:100%;min-height:120px;resize:vertical;font-family:var(--mono);font-size:11.5px;
    line-height:1.5;color:var(--ink);background:var(--panel);border:1px solid var(--line);
    border-radius:9px;padding:10px 11px;box-sizing:border-box}
  .ed-geo-acts{display:flex;align-items:center;gap:10px;margin-top:9px;flex-wrap:wrap}
  .ed-geo-status{font-size:11px;color:var(--faint)}
  .ed-geo-status.ok{color:var(--teal)}
  .ed-geo-status.err{color:var(--err-bright)}

  /* Dropzone de anexos + lista */
  .ed-drop{border:1.6px dashed var(--line-2);border-radius:var(--r);padding:18px 12px;text-align:center;
    cursor:pointer;transition:.15s;background:var(--panel);color:var(--faint)}
  .ed-drop:hover,.ed-drop.drag{border-color:var(--red-bright);
    background:color-mix(in srgb, var(--red-bright) 4%, var(--panel));color:var(--ink)}
  .ed-drop .ed-drop-ic{display:flex;justify-content:center;margin-bottom:6px;color:var(--red-bright)}
  .ed-drop b{color:var(--ink)}
  .ed-drop small{display:block;margin-top:3px;font-size:10.5px}
  .ed-drop-opts{display:flex;align-items:center;gap:7px;justify-content:center;margin-top:10px;
    font-size:11.5px;color:var(--ink)}
  .ed-drop-opts input{accent-color:var(--red-bright)}
  .ed-drop.busy{opacity:.6;border-style:solid;cursor:not-allowed}
  .anx-list{display:flex;flex-direction:column;gap:8px}
  .anx-empty{font-size:11.5px;color:var(--faint);padding:4px 2px}
  .anx-item{display:flex;align-items:center;gap:10px;border:1px solid var(--line);
    border-radius:var(--r-s);padding:9px 11px;background:var(--panel)}
  .anx-ic{flex:0 0 32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;
    font-size:9.5px;font-weight:800;color:#fff;letter-spacing:.02em}
  .anx-ic.pdf_matricula{background:linear-gradient(150deg,var(--red-bright),var(--red-deep))}
  .anx-ic.pdf_sigef{background:linear-gradient(150deg,#1F9D57,#136B3C)}
  .anx-ic.kml{background:linear-gradient(150deg,#3B82F6,#1D4ED8)}
  .anx-ic.outro{background:linear-gradient(150deg,#8A94A5,#5B6675)}
  .anx-meta{flex:1;min-width:0}
  .anx-nome{font-size:12px;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .anx-sub{font-size:10px;color:var(--faint);margin-top:1px}
  .anx-acts{display:flex;gap:4px;flex:0 0 auto}
  .anx-btn{width:31px;height:31px;border:1px solid var(--line);background:var(--panel-2);color:var(--faint);
    border-radius:8px;display:flex;align-items:center;justify-content:center;transition:.15s}
  .anx-btn:hover{color:var(--ink);border-color:var(--line-2);background:var(--panel)}
  .anx-btn.danger:hover{color:#fff;background:var(--err-bright);border-color:var(--err-bright)}
  .anx-btn[disabled]{opacity:.45;cursor:default}
  .anx-busy{display:flex;align-items:center;gap:9px;padding:10px 12px;border-radius:var(--r-s);font-size:12px;
    border:1px solid var(--line);background:var(--panel);color:var(--ink)}
  .anx-busy.work{border-color:color-mix(in srgb, var(--red-bright) 38%, transparent);
    background:color-mix(in srgb, var(--red-bright) 5%, transparent)}
  .anx-busy.warn{border-color:color-mix(in srgb, var(--amber) 55%, transparent);
    background:color-mix(in srgb, var(--amber) 9%, transparent)}
  .anx-busy.ok{border-color:color-mix(in srgb, var(--green) 45%, transparent);
    background:color-mix(in srgb, var(--green) 7%, transparent)}
  .anx-spin{flex:0 0 16px;width:16px;height:16px;border-radius:50%;
    border:2.5px solid color-mix(in srgb, var(--red-bright) 25%, transparent);
    border-top-color:var(--red-bright);animation:anxspin .7s linear infinite}
  @keyframes anxspin{to{transform:rotate(360deg)}}
  .anx-busy.shake{animation:anxshake .4s ease}
  @keyframes anxshake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}

  /* ─── 25. OVERLAY DE IMPORTAÇÃO ─────────────────────────────────── */
  .import-ov{position:fixed;inset:0;z-index:100040;display:none;align-items:center;justify-content:center;
    background:color-mix(in srgb, #070B12 62%, transparent);
    -webkit-backdrop-filter:blur(5px);backdrop-filter:blur(5px)}
  .import-ov.show{display:flex}
  .import-card{background:var(--panel);border:1px solid var(--line);border-radius:var(--r-xl);
    padding:28px 34px;text-align:center;box-shadow:var(--sh-3);min-width:270px}
  .import-ttl{font-family:var(--titles);font-size:13.5px;font-weight:700;color:var(--ink);
    margin-bottom:18px;letter-spacing:.01em}
  .import-ring{position:relative;width:120px;height:120px;margin:0 auto}
  .import-ring svg{transform:rotate(-90deg)}
  .import-ring .ring-bg{fill:none;stroke:var(--line);stroke-width:9}
  .import-ring .ring-fg{fill:none;stroke:var(--red-bright);stroke-width:9;stroke-linecap:round;
    stroke-dasharray:326.7;stroke-dashoffset:326.7;transition:stroke-dashoffset .3s ease}
  .import-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    font-family:var(--titles);font-size:24px;font-weight:700;color:var(--ink)}
  .import-meta{margin-top:15px;font-size:12px;color:var(--faint)}
  .import-file{margin-top:4px;font-size:11.5px;color:var(--ink);max-width:300px;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:var(--mono)}
  @keyframes import-spin{from{transform:rotate(-90deg)}to{transform:rotate(270deg)}}
  .import-ov.indet .import-ring svg{animation:import-spin .9s linear infinite}
  .import-ov.indet .import-ring .ring-fg{transition:none}
  .import-ov.indet .import-pct{font-size:0}

  /* Resultados da importação */
  .impres-resumo{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px}
  .impres-chip{font-size:11.5px;font-weight:650;padding:5px 12px;border-radius:99px;border:1px solid var(--line)}
  .impres-chip.ok{color:var(--green-text);background:color-mix(in srgb, var(--green) 9%, transparent);
    border-color:color-mix(in srgb, var(--green) 30%, transparent)}
  .impres-chip.dup{color:var(--blue);background:color-mix(in srgb, var(--blue) 9%, transparent);
    border-color:color-mix(in srgb, var(--blue) 30%, transparent)}
  .impres-chip.err{color:var(--err-text);background:var(--err-soft);
    border-color:color-mix(in srgb, var(--err-bright) 30%, transparent)}
  .impres-chip.warn{color:var(--amber-text);background:color-mix(in srgb, var(--amber) 11%, transparent);
    border-color:color-mix(in srgb, var(--amber) 35%, transparent)}
  .impres-list{display:flex;flex-direction:column;gap:8px}
  .impres-item{border:1px solid var(--line);border-radius:var(--r);padding:11px 13px;background:var(--panel)}
  .impres-row1{display:flex;align-items:center;gap:9px}
  .impres-ic{flex:0 0 23px;height:23px;border-radius:7px;display:flex;align-items:center;justify-content:center;
    font-size:13px;font-weight:800;color:#fff}
  .impres-ic.criado{background:var(--green)}
  .impres-ic.duplicado{background:var(--blue)}
  .impres-ic.erro{background:var(--err)}
  .impres-nome{font-size:12.5px;font-weight:650;color:var(--ink)}
  .impres-st{margin-left:auto;font-size:9.5px;font-weight:700;text-transform:uppercase;
    letter-spacing:.05em;color:var(--faint)}
  .impres-dest{font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:99px;
    border:1px solid var(--line);white-space:nowrap}
  .impres-dest.mapa{color:var(--green-text);background:color-mix(in srgb, var(--green) 9%, transparent);
    border-color:color-mix(in srgb, var(--green) 30%, transparent)}
  .impres-dest.itn{color:var(--violet);background:color-mix(in srgb, var(--violet) 9%, transparent);
    border-color:color-mix(in srgb, var(--violet) 30%, transparent)}
  .impres-msg{font-size:11px;color:var(--faint);margin-top:3px;margin-left:32px}
  .impres-inc{margin:7px 0 0 32px;display:flex;flex-direction:column;gap:4px}
  .impres-inc .inc-line{display:flex;gap:7px;align-items:flex-start;font-size:11.5px;line-height:1.45}
  .inc-tag{flex:0 0 auto;font-size:9px;font-weight:800;letter-spacing:.04em;padding:1px 7px;
    border-radius:99px;margin-top:1px}
  .inc-tag.erro{background:var(--err-soft);color:var(--err-text)}
  .inc-tag.alerta{background:color-mix(in srgb, var(--amber) 14%, transparent);color:var(--amber-text)}
  .inc-tag.info{background:color-mix(in srgb, var(--blue) 12%, transparent);color:var(--blue)}
  .impres-inc .inc-msg{color:var(--ink)}
  .impres-relrow{display:flex;flex-wrap:wrap;gap:7px;margin:10px 0 0 32px}
  .impres-relrow .mini-rel{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;
    color:var(--ink);background:var(--panel);border:1px solid var(--line);border-radius:8px;
    padding:6px 11px;cursor:pointer;text-decoration:none;line-height:1;transition:.15s}
  .impres-relrow .mini-rel:hover{border-color:var(--teal);color:var(--teal);
    background:color-mix(in srgb, var(--teal) 6%, transparent)}
  .impres-relrow .mini-rel.vermapa{background:var(--teal);border-color:var(--teal);color:#fff}
  .impres-relrow .mini-rel.vermapa:hover{filter:brightness(1.07);color:#fff}

  /* ─── 26. AUTOTUTELA REGISTRAL ──────────────────────────────────── */
  .mini-btn.at,.btn-report.at{background:color-mix(in srgb, var(--red-deep) 9%, transparent);
    border:1px solid color-mix(in srgb, var(--red-deep) 42%, transparent);
    color:var(--red-text);box-shadow:none}
  .mini-btn.at:hover,.btn-report.at:hover{background:color-mix(in srgb, var(--red-deep) 16%, transparent);filter:none}
  .at-card{max-width:980px}
  .at-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}
  .at-hint{font-size:10.5px;color:var(--muted);font-family:var(--mono)}
  .at-lista{display:flex;flex-direction:column;gap:8px;max-height:60vh;overflow:auto}
  .at-item{border:1px solid var(--line);border-radius:var(--r);padding:11px 13px;cursor:pointer;
    display:flex;gap:10px;align-items:flex-start;background:var(--panel);
    transition:border-color .15s,box-shadow .15s}
  .at-item:hover{border-color:color-mix(in srgb, var(--red-bright) 45%, var(--line));box-shadow:var(--sh-1)}
  .at-item .at-num{font-family:var(--mono);font-weight:700;font-size:12px;color:var(--ink)}
  .at-item .at-meta{font-size:11px;color:var(--muted);margin-top:2px;line-height:1.45}
  .at-fase{display:inline-block;font-size:9.5px;font-weight:700;text-transform:uppercase;
    letter-spacing:.04em;padding:3px 9px;border-radius:99px;border:1px solid var(--line);
    margin-left:auto;white-space:nowrap}
  .at-fase.f-aberto,.at-fase.f-relatorio,.at-fase.f-notificacao,.at-fase.f-manifestacao{
    background:color-mix(in srgb, var(--amber) 13%, transparent);color:var(--amber-text);
    border-color:color-mix(in srgb, var(--amber) 40%, transparent)}
  .at-fase.f-transacao,.at-fase.f-replica,.at-fase.f-decisao,.at-fase.f-saneamento{
    background:color-mix(in srgb, var(--blue) 11%, transparent);color:var(--blue);
    border-color:color-mix(in srgb, var(--blue) 40%, transparent)}
  .at-fase.f-encerrado{background:color-mix(in srgb, var(--green) 13%, transparent);
    color:var(--green-text);border-color:color-mix(in srgb, var(--green) 40%, transparent)}
  .at-fase.f-remetido,.at-fase.f-arquivado{background:var(--err-soft);color:var(--err-text);
    border-color:color-mix(in srgb, var(--err-bright) 40%, transparent)}
  .at-voltar{background:none;border:none;color:var(--red-bright);cursor:pointer;font-size:12px;
    font-weight:600;padding:0;margin-bottom:10px}
  .at-steps{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:14px}
  .at-steps .st{font-size:9.5px;font-family:var(--mono);padding:4px 10px;border-radius:99px;
    border:1px solid var(--line);color:var(--muted)}
  .at-steps .st.on{background:var(--red-bright);color:#fff;border-color:var(--red-bright)}
  .at-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px}
  .at-f{display:flex;flex-direction:column;gap:5px;margin-bottom:12px;min-width:0}
  .at-f label{font-size:10px;font-family:var(--mono);text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
  .at-f input,.at-f select,.at-f textarea{width:100%;box-sizing:border-box;border:1px solid var(--line);
    border-radius:9px;padding:9px 11px;font-size:12.5px;background:var(--panel);color:var(--ink);font-family:inherit}
  .at-f textarea{resize:vertical;font-family:var(--mono);font-size:11.5px;line-height:1.5}
  .at-sec{font-family:var(--titles);font-size:10.5px;text-transform:uppercase;letter-spacing:.07em;
    color:var(--red-text);font-weight:700;margin:8px 0;border-top:1px dashed var(--line);padding-top:12px}
  .at-partes-box{margin-top:4px}
  .at-parte{border:1px solid var(--line);border-radius:var(--r-s);padding:10px;margin-bottom:8px;
    background:var(--panel-2)}
  .at-parte-row{display:grid;grid-template-columns:1.4fr 1fr .8fr .8fr;gap:8px;margin-bottom:7px}
  .at-parte-row2{display:grid;grid-template-columns:auto auto 1.2fr auto;gap:10px;align-items:center}
  .at-parte input,.at-parte select{width:100%;box-sizing:border-box;border:1px solid var(--line);
    border-radius:8px;padding:7px 9px;font-size:11.5px;background:var(--panel);color:var(--ink)}
  .at-parte .chk{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted);white-space:nowrap}
  .at-parte .rm{background:none;border:none;color:var(--err-bright);cursor:pointer;font-size:16px;line-height:1;
    padding:2px 6px;border-radius:7px}
  .at-parte .rm:hover{background:var(--err-soft)}
  .at-docs{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:14px 0;padding:11px;
    border:1px dashed var(--line-2);border-radius:var(--r-s)}
  .at-docs-l{font-size:11px;color:var(--muted);font-family:var(--mono)}
  .btn-doc{background:color-mix(in srgb, var(--blue) 9%, transparent);
    border:1px solid color-mix(in srgb, var(--blue) 38%, transparent);color:var(--blue);
    border-radius:8px;padding:6px 11px;font-size:11.5px;font-weight:600}
  .btn-doc:hover{background:color-mix(in srgb, var(--blue) 15%, transparent)}
  .at-form-foot{display:flex;align-items:center;gap:10px;margin-top:8px;padding-top:12px;border-top:1px solid var(--line)}
  .at-save-status{font-size:11.5px;color:var(--muted)}
  .at-save-status.ok{color:var(--teal)}
  .at-save-status.err{color:var(--err-bright)}
  .at-ia{font-size:10px;border:1px solid color-mix(in srgb, var(--blue) 42%, transparent);
    background:color-mix(in srgb, var(--blue) 9%, transparent);color:var(--blue);
    border-radius:7px;padding:3px 9px;font-weight:600;margin-left:8px;text-transform:none;letter-spacing:0}
  .at-ia:disabled{opacity:.55;cursor:default}
  .at-anexos-lista{display:flex;flex-direction:column;gap:5px;margin-bottom:8px}
  .at-parte-anexos{display:flex;flex-direction:column;gap:5px;margin-top:7px}
  .at-parte-anexos:empty{display:none}
  .at-anx{display:flex;align-items:center;gap:8px;font-size:11.5px;
    background:var(--panel-2);border:1px solid var(--line);border-radius:8px;padding:6px 10px}
  .at-anx a{color:var(--ink);text-decoration:none;flex:1;min-width:0;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap}
  .at-anx-t{font-size:9.5px;color:var(--muted);font-family:var(--mono);white-space:nowrap}
  .at-anx-dl{color:var(--blue)!important;flex:0 0 auto!important}
  .at-anx-x{background:none;border:none;color:var(--err-bright);cursor:pointer;font-size:13px;line-height:1;
    padding:2px 5px;border-radius:6px}
  .at-anx-x:hover{background:var(--err-soft)}
  .at-up-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px}
  .at-up-tipo{border:1px solid var(--line);border-radius:8px;padding:7px 9px;font-size:11px;
    background:var(--panel);color:var(--ink)}
  body.dark-mode #modal-autotutela select,
  body.dark-mode #modal-autotutela input,
  body.dark-mode #modal-autotutela textarea,
  body.dark-mode #modal-autotutela .at-up-tipo{background:#18212E !important;color:#E8EEF6 !important;border-color:#2C3947 !important}
  body.dark-mode #modal-autotutela select option{background:#18212E !important;color:#E8EEF6 !important}
  body.dark-mode #modal-autotutela input::placeholder,
  body.dark-mode #modal-autotutela textarea::placeholder{color:#7F8B97}

  /* ─── 27. SWEETALERT2 (tema Vertex) ─────────────────────────────── */
  .swal2-popup{border-radius:var(--r-xl) !important;font-family:var(--disp) !important;
    box-shadow:var(--sh-3) !important}
  .swal2-title{font-family:var(--titles) !important;font-weight:700 !important;font-size:19px !important}
  .swal2-styled.swal2-confirm{border-radius:10px !important;font-weight:650 !important;
    background:linear-gradient(135deg, var(--red-bright) 0%, var(--red-deep) 100%) !important;
    box-shadow:0 8px 20px -10px color-mix(in srgb, var(--red-bright) 65%, transparent) !important}
  .swal2-styled.swal2-cancel,.swal2-styled.swal2-deny{border-radius:10px !important;font-weight:600 !important}
  .swal2-input,.swal2-select,.swal2-textarea{border-radius:10px !important;font-size:13px !important}
  .swal2-input:focus,.swal2-select:focus,.swal2-textarea:focus{
    border-color:var(--red-bright) !important;box-shadow:var(--ring) !important}
  body.dark-mode .swal2-popup{background:#111823 !important;color:#E8EEF6 !important;
    border:1px solid #243040}
  body.dark-mode .swal2-title,body.dark-mode .swal2-html-container{color:#E8EEF6 !important}
  body.dark-mode .swal2-input,body.dark-mode .swal2-select,body.dark-mode .swal2-textarea{
    background:#18212E !important;color:#E8EEF6 !important;border-color:#2C3947 !important}
  body.dark-mode .swal2-select option{background:#18212E;color:#E8EEF6}

  /* ─── 27b. INTEGRAÇÃO DOS ÍCONES NOS COMPONENTES ───────────────── */
  .mini-btn .ic{width:13.5px;height:13.5px}
  .c3d-btn,.c3d-mini{display:inline-flex;align-items:center;gap:7px}
  .c3d-btn .ic{width:15px;height:15px}
  .c3d-mini .ic{width:13px;height:13px}
  .ov-close{display:grid;place-items:center}
  .ov-close .ic{width:14px;height:14px}
  .btn-itn03 .ic{width:14px;height:14px;margin-right:6px}
  .btn-report .ic{width:13px;height:13px;margin-right:6px}
  .btn-report{display:inline-flex;align-items:center;justify-content:center}
  .btn-itn03{display:inline-flex;align-items:center;justify-content:center}
  .cfg-link .ic{width:13px;height:13px}
  .btn-save .ic,.btn-ghost .ic,.btn-primary .ic{width:14px;height:14px;margin-right:7px}
  .vx-act-btn,.vx-act-btn2{display:inline-flex;align-items:center;justify-content:center}
  .vx-sub-title .ic{width:15px;height:15px;vertical-align:-3px;margin-right:8px;color:var(--teal)}
  .saved-actions .mini-btn{display:inline-flex;align-items:center}

  /* Distribuição da grade de formulário em telas largas */
  @media (min-width:1100px){
    .form-grid{grid-template-columns:repeat(3,1fr)}
    .form-grid .fld.grid-2{grid-column:span 2}
  }

  /* "Como funciona" como passo a passo numerado */
  .vx-flow ol{list-style:none;counter-reset:vxs;padding-left:0;margin:10px 0 0}
  .vx-flow li{counter-increment:vxs;position:relative;padding:3px 0 3px 34px;margin:9px 0}
  .vx-flow li::before{content:counter(vxs);position:absolute;left:0;top:1px;width:22px;height:22px;
    border-radius:50%;display:grid;place-items:center;font-family:var(--mono);font-size:11px;font-weight:700;
    color:var(--red-bright);background:var(--red-soft);
    border:1px solid color-mix(in srgb, var(--red-bright) 25%, transparent)}

  /* ─── 27c. ABA RELATÓRIOS ──────────────────────────────────────── */
  .rel-toolbar{display:flex;align-items:center;gap:12px;margin:0 0 14px}
  .rel-quando{font-family:var(--mono);font-size:10.5px;color:var(--faint)}
  .rel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:16px;align-items:start}
  .rel-loading{grid-column:1/-1;padding:26px;text-align:center;color:var(--muted);font-size:12.5px;
    border:1.5px dashed var(--line-2);border-radius:var(--r-l);background:var(--panel)}
  .rel-card{background:var(--panel);border:1px solid var(--line);border-radius:var(--r-l);
    box-shadow:var(--sh-1);padding:18px 18px 16px;display:flex;flex-direction:column;gap:13px;min-width:0}
  .rel-h{display:flex;align-items:center;gap:10px}
  .rel-h .vx-act-ic{margin-right:0}
  .rel-h h3{margin:0;font-family:var(--titles);font-size:14px;font-weight:700;color:var(--ink)}
  .rel-h p{margin:2px 0 0;font-size:11px;color:var(--muted)}
  .rel-top{display:flex;align-items:center;gap:16px}
  .rel-donut{flex:none;width:104px;height:104px;position:relative}
  .rel-donut svg{width:100%;height:100%;transform:rotate(-90deg)}
  .rel-donut .rel-pct{position:absolute;inset:0;display:grid;place-items:center;
    font-family:var(--titles);font-weight:700;font-size:20px;color:var(--ink)}
  .rel-donut .rel-pct small{display:block;font-family:var(--mono);font-weight:400;font-size:9px;
    color:var(--faint);letter-spacing:.06em;text-transform:uppercase;text-align:center;margin-top:2px}
  .rel-nums{display:flex;flex-direction:column;gap:7px;min-width:0}
  .rel-n{display:flex;align-items:baseline;gap:8px;font-size:12px;color:var(--muted)}
  .rel-n b{font-family:var(--mono);font-size:14px;color:var(--ink);font-weight:600}
  .rel-n .dot{width:8px;height:8px;border-radius:3px;flex:none;align-self:center}
  .rel-miss-h{display:flex;align-items:center;gap:8px;justify-content:space-between;margin-top:2px}
  .rel-miss-h span{font-family:var(--mono);font-size:10px;letter-spacing:.07em;text-transform:uppercase;color:var(--faint)}
  .rel-copy{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);background:var(--panel);
    color:var(--muted);font-family:var(--disp);font-size:11px;font-weight:600;border-radius:999px;
    padding:5px 11px;cursor:pointer;transition:color .15s,border-color .15s,box-shadow .15s}
  .rel-copy:hover{color:var(--ink);border-color:var(--line-2);box-shadow:var(--sh-1)}
  .rel-copy .ic{width:12px;height:12px}
  .rel-miss{display:flex;flex-wrap:wrap;gap:6px;max-height:172px;overflow:auto;padding:2px}
  .rel-chip{font-family:var(--mono);font-size:11px;color:var(--ink);background:var(--panel-2);
    border:1px solid var(--line);border-radius:8px;padding:4px 8px;white-space:nowrap}
  .rel-chip.warn{background:color-mix(in srgb, var(--err-bright) 8%, transparent);
    border-color:color-mix(in srgb, var(--err-bright) 28%, transparent)}
  .rel-chip.ok2{background:color-mix(in srgb, var(--green) 10%, transparent);
    border-color:color-mix(in srgb, var(--green) 30%, transparent)}
  .rel-chip small{display:block;font-family:var(--ui);font-size:9.5px;color:var(--muted);white-space:normal;max-width:220px}
  .rel-vazio{font-size:12px;color:var(--teal);font-weight:600}
  .rel-nota{font-size:10.5px;color:var(--faint);line-height:1.55;margin:0}

  /* ─── 28. RESPONSIVO ────────────────────────────────────────────── */
  @media (max-width:1100px){
    .vx-top{gap:12px;padding:9px 14px 8px}
    .vx-tabs{padding:0 8px}
    .vx-tab{padding:10px 12px 12px;font-size:12.5px}
  }
  @media (max-width:880px){
    /* O palco encolhe para dar lugar à navegação inferior fixa */
    .mapeador-shell{grid-template-columns:none;
      bottom:calc(60px + env(safe-area-inset-bottom,0px))}
    .panel{position:relative;top:auto;bottom:auto;left:auto;width:100%;max-width:none;transform:none;z-index:20}
    body.panel-open .panel{transform:none}

    .vx-top{gap:10px;padding:8px 12px}
    .brand p{display:none}
    .brand h1{font-size:15px}
    .mark{width:32px;height:32px;border-radius:10px}
    .mark svg{width:17px;height:17px}
    .quick-actions .mini-btn span{display:none}
    .quick-actions .mini-btn{width:38px;height:38px;padding:0;border-radius:11px}
    .quick-actions .mini-btn .ic{width:16px;height:16px}
    .back-atlas span{display:none}
    .back-atlas{width:38px;height:38px;padding:0;border-radius:11px}
    .back-atlas .ic{width:15px;height:15px}

    /* Navegação inferior fixa (estilo app nativo) */
    .vx-tabs{position:fixed;left:0;right:0;bottom:0;z-index:890;margin:0;border-top:1px solid var(--line);
      height:calc(60px + env(safe-area-inset-bottom,0px));
      padding:5px 6px calc(5px + env(safe-area-inset-bottom,0px));
      display:grid;grid-template-columns:repeat(7,1fr);gap:2px;
      background:color-mix(in srgb, var(--panel) 93%, transparent);
      -webkit-backdrop-filter:saturate(1.5) blur(14px);backdrop-filter:saturate(1.5) blur(14px);
      box-shadow:0 -8px 22px -14px rgba(10,16,24,.4)}
    .vx-tab{flex-direction:column;gap:3px;justify-content:center;align-items:center;
      padding:4px 2px;border-radius:10px;font-size:9.5px;font-weight:600;min-width:0}
    .vx-tab span{max-width:100%;overflow:hidden;text-overflow:ellipsis}
    .vx-tab .ic{width:19px;height:19px}
    .vx-tab:hover{background:transparent}
    .vx-tab.active{background:var(--red-soft);color:var(--red-deep)}
    .vx-tab.active .ic{color:currentColor}
    .vx-tab.active::after{display:none}
    body.dark-mode .vx-tab.active{background:color-mix(in srgb, var(--red-bright) 16%, transparent);
      color:var(--red-bright)}

    .vx-pane{padding:16px 14px calc(16px + var(--vx-bottombar,0px))}
    .vx-pane[data-pane="imoveis"] .saved-head,.imoveis-sticky,
    .vx-pane[data-pane="imoveis"] #saved-list{padding-left:14px;padding-right:14px}
    .vx-pane-head{gap:11px;margin-bottom:16px}
    .vx-ph-ic{width:34px;height:34px;border-radius:10px}
    .vx-ph-ic .ic{width:16px;height:16px}
    .vx-ph-tx h2{font-size:15.5px}
    .vx-actions{grid-template-columns:1fr}
    .ed-grid{grid-template-columns:1fr}
    .at-grid{grid-template-columns:1fr}
    .at-parte-row{grid-template-columns:1fr 1fr}
    .at-parte-row2{grid-template-columns:1fr 1fr}
    .overview-panel{width:min(300px, calc(100vw - 28px))}
    .kml-panel{width:min(328px, calc(100vw - 28px))}
    .ed-stats{grid-template-columns:1fr 1fr}
    /* Alvos de toque maiores */
    .mini-btn{padding:9px 13px}
    .item{padding:12px 13px}
  }
  @media (max-width:520px){
    .base-toggle .bt-btn span{display:none}
    .base-toggle .bt-btn{padding:8px 12px}
    .base-toggle .bt-btn .ic{width:15px;height:15px}
    .dropzone{padding:32px 16px}
    .form-grid{grid-template-columns:1fr}
    .row{grid-template-columns:1fr}
    .modal-row{grid-template-columns:1fr}
    .stats{grid-template-columns:1fr 1fr}
    .sel-bar{bottom:70px;gap:8px;padding:8px 10px 8px 14px;max-width:calc(100vw - 20px)}
    .sel-rep{padding:8px 11px;font-size:11.5px}
    .muni-badge{max-width:calc(100vw - 28px);font-size:11px}
    .modal-f{flex-wrap:wrap}
    .modal-f .btn-primary,.modal-f .btn-ghost{flex:1}
  }
  @media (max-width:420px){
    .vx-tab span{display:none}
    .vx-tab .ic{width:21px;height:21px}
    .modal-ov{padding:10px}
    .import-card{padding:22px 20px;min-width:0;width:calc(100vw - 40px)}
    .kml-row .inp{grid-template-columns:1fr}
  }
  @media (prefers-reduced-motion:reduce){
    *,*::before,*::after{animation-duration:.001s !important;transition-duration:.001s !important}
    .vx-pane{animation:none}
    #saved-list .item:hover,.item:hover,.vx-act-card:hover,.dropzone.drag{transform:none}
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
  <!-- Sprite de ícones da interface (stroke 2, estilo Lucide) -->
  <svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
    <symbol id="i-map" viewBox="0 0 24 24"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></symbol>
    <symbol id="i-list" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></symbol>
    <symbol id="i-edit" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></symbol>
    <symbol id="i-upload" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></symbol>
    <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></symbol>
    <symbol id="i-compass" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></symbol>
    <symbol id="i-arch" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></symbol>
    <symbol id="i-ruler" viewBox="0 0 24 24"><path d="M2 12l10-10 10 10-10 10z"/><path d="M7 12l2.5 2.5"/><path d="M12 7l2.5 2.5"/><path d="M9.5 9.5l2 2"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-tag" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></symbol>
    <symbol id="i-back" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></symbol>
    <symbol id="i-cube" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></symbol>
    <symbol id="i-tilt" viewBox="0 0 24 24"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></symbol>
    <symbol id="i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></symbol>
    <symbol id="i-scale" viewBox="0 0 24 24"><path d="M12 3v18"/><path d="M5 7l7-4 7 4"/><path d="M5 7l-3 7a3.5 3.5 0 0 0 6 0z"/><path d="M19 7l-3 7a3.5 3.5 0 0 0 6 0z"/><path d="M8 21h8"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></symbol>
    <symbol id="i-down" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></symbol>
    <symbol id="i-minus" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></symbol>
    <symbol id="i-copy" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></symbol>
  </svg>
  <div class="panel">
    <!-- ===== Barra de comando (2 níveis: contexto em cima, navegação embaixo) ===== -->
    <div class="vx-bar">
      <div class="vx-top">
        <div class="brand">
          <div class="mark">
            <svg viewBox="0 0 24 24" fill="#fff" stroke="none">
              <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/>
            </svg>
          </div>
          <div>
            <h1>Vertex</h1>
            <p>GMS · Google Maps · Atlas</p>
          </div>
        </div>
        <div class="base-toggle" id="base-toggle" title="Alterna entre a base de matrículas e a base de projetos (projetos = imóveis ainda sem matrícula)">
          <button type="button" class="bt-btn active" data-base="matriculas"><svg class="ic"><use href="#i-arch"/></svg><span>Matrículas</span></button>
          <button type="button" class="bt-btn" data-base="projetos"><svg class="ic"><use href="#i-ruler"/></svg><span>Projetos</span></button>
        </div>
        <div class="vx-top-r">
          <div class="quick-actions">
            <button class="mini-btn" id="btn-todos" title="Ver todos os imóveis no mapa"><svg class="ic"><use href="#i-eye"/></svg><span>Ver todos</span></button>
            <button class="mini-btn active" id="btn-rotulos" title="Rótulos ocultos — passe o mouse sobre o imóvel para ver a matrícula"><svg class="ic"><use href="#i-tag"/></svg><span>Mostrar rótulos</span></button>
          </div>
          <a href="../index.php" class="back-atlas" title="Voltar ao Atlas"><svg class="ic"><use href="#i-back"/></svg><span>Atlas</span></a>
        </div>
      </div>
      <nav class="vx-tabs" id="vx-tabs" role="tablist">
        <button type="button" class="vx-tab active" data-tab="mapa"      title="Mapa em tela cheia"><svg class="ic"><use href="#i-map"/></svg><span>Mapa</span></button>
        <button type="button" class="vx-tab"        data-tab="imoveis"   title="Imóveis gravados, filtros e envios"><svg class="ic"><use href="#i-list"/></svg><span>Imóveis</span></button>
        <button type="button" class="vx-tab"        data-tab="cadastrar" title="Cadastro por memorial, cor e dados ONR"><svg class="ic"><use href="#i-edit"/></svg><span>Cadastrar</span></button>
        <button type="button" class="vx-tab"        data-tab="importar"  title="Importar KML ou PDF/SIGEF por IA"><svg class="ic"><use href="#i-upload"/></svg><span>Importar</span></button>
        <button type="button" class="vx-tab"        data-tab="onr"       title="Enviar ao Mapa da ONR e exportar carga ITN 03"><svg class="ic"><use href="#i-globe"/></svg><span>ONR / Carga</span></button>
        <button type="button" class="vx-tab"        data-tab="limites"   title="Limite do município (IBGE / KML)"><svg class="ic"><use href="#i-compass"/></svg><span>Limites</span></button>
        <button type="button" class="vx-tab"        data-tab="relatorios" title="Relatórios de completude: matrículas faltantes, envio ONR e carga ITN 03"><svg class="ic"><use href="#i-chart"/></svg><span>Relatórios</span></button>
      </nav>
    </div>
    <!-- Faixa de status (global, visível em qualquer aba) -->
      <div class="status" id="status"></div>
  </div><!-- /.panel -->

  <div class="vx-stage" id="vx-stage">
    <!-- ===== MAPA — seção dedicada em tela cheia ===== -->
    <section class="vx-pane active" data-pane="mapa">
      <div class="map-wrap">
    <div id="map"></div>
    <div id="ctrl-3d" class="ctrl-3d">
      <button id="btn-3d" class="c3d-btn" title="Ver o imóvel em 3D fotorrealista (relevo do terreno)"><svg class="ic"><use href="#i-cube"/></svg><span>Ver em 3D</span></button>
    </div>
    <button id="btn-toggle-panel" class="toggle-panel" title="Mostrar/ocultar painel" aria-label="Mostrar ou ocultar painel">
      <svg class="ic-collapse" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
      <svg class="ic-expand" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </button>
    <div class="overlay" id="overlay">Clique em <span style="color:var(--red-bright);margin:0 4px">Mapear</span> para visualizar o imóvel</div>
    <div class="readout" id="readout"><span class="dot">◆</span> <b id="ro-name">Imóvel</b> &nbsp;·&nbsp; <span id="ro-area"></span> ha</div>

    <!-- Painel de foco no imóvel: coordenadas, área e inconsistências (como no laudo do memorial) -->
    <div class="foco-panel" id="foco-panel" aria-live="polite">
      <button class="foco-close" id="foco-close" title="Ocultar painel" aria-label="Ocultar painel">&times;</button>
      <div class="foco-head">
        <div class="foco-kick" id="foco-kick">IMÓVEL</div>
        <div class="foco-title" id="foco-title">Imóvel</div>
        <div class="foco-sub" id="foco-sub"></div>
      </div>
      <div class="foco-metrics">
        <div><b id="foco-area">—</b><span>Área (plano UTM)</span></div>
        <div><b id="foco-perim">—</b><span>Perímetro</span></div>
      </div>
      <div class="foco-area2" id="foco-area2"></div>
      <div class="foco-sec" id="foco-vtx-sec">
        <div class="foco-sec-t">Vértices (E / N)</div>
        <table class="foco-vtx"><thead><tr><th>Vért.</th><th>E</th><th>N</th></tr></thead><tbody id="foco-vtx-body"></tbody></table>
      </div>
      <div class="foco-sec" id="foco-conf-sec" style="display:none">
        <div class="foco-sec-t">Confrontações</div>
        <div id="foco-conf"></div>
      </div>
      <div class="foco-sec" id="foco-inc-sec" style="display:none">
        <div class="foco-sec-t">Inconsistências</div>
        <div id="foco-inc"></div>
      </div>
      <div class="foco-note" id="foco-note"></div>
    </div>
    <button class="foco-reopen" id="foco-reopen" title="Mostrar dados do imóvel">◧ Dados do imóvel</button>
    <div class="muni-badge" id="muni-badge"></div>

    <div class="overview-panel" id="overview-panel">
      <div class="ovh">
        <div>
          <div class="ovh-title">Visão geral</div>
          <div class="ovh-sub" id="ov-sub">—</div>
        </div>
        <button class="ov-close" id="ov-hide" title="Ocultar painel"><svg class="ic"><use href="#i-minus"/></svg></button>
      </div>
      <div class="legend">
        <span><i class="sw normal"></i>Imóvel</span>
        <span><i class="sw sel"></i>Selecionado</span>
        <span><i class="sw over"></i>Sobreposição</span>
      </div>
      <div class="ov-hint" id="ov-hint">Ctrl+clique (ou clique direito) nos imóveis para selecionar · clique numa sobreposição para o relatório dela</div>
      <div class="ov-search">
        <input type="text" id="ov-busca" placeholder="Filtrar... 744;822 (só essas) · 506;* (506 + sobrepostas/desmembradas)">
        <button id="ov-busca-clear" title="Limpar filtro">×</button>
      </div>
      <div class="ov-itn03">
        <button id="ov-itn03" class="btn-itn03" title="Gerar a carga ITN 03 (ONR) dos imóveis prontos para o Mapa ONR — todos, ou apenas os do filtro ;"><svg class="ic"><use href="#i-down"/></svg><span>Exportar carga ITN 03 (lote)</span></button>
      </div>
      <div class="ov-overlaps" id="ov-overlaps"></div>
      <div class="ov-foot">
        <button class="btn-report" id="btn-relatorio">Gerar relatório de sobreposição (PDF)</button>
        <button class="btn-report at" id="btn-instaurar-at" title="Abrir um procedimento de autotutela registral a partir das sobreposições exibidas"><svg class="ic"><use href="#i-scale"/></svg><span>Instaurar autotutela desta sobreposição</span></button>
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
        <button class="ov-close" id="kml-close" title="Cancelar"><svg class="ic"><use href="#i-x"/></svg></button>
      </div>
      <div class="kml-rows" id="kml-rows"></div>
      <div class="kml-foot">
        <button class="btn-save" id="btn-import-lote" style="width:100%">Gravar imóveis</button>
      </div>
    </div>
  </div>
    </section>

    <section class="vx-pane" data-pane="imoveis">
      <div class="saved">
        <div class="saved-head">
          <h3>Imóveis gravados</h3>
          <div class="saved-actions">
            <button class="mini-btn onr" id="btn-onr-lote" title="Enviar todos os imóveis prontos ao Mapa ONR"><svg class="ic"><use href="#i-send"/></svg><span>Enviar prontos</span></button>
            <button class="mini-btn" id="btn-onr-config" title="Configurar a API do Mapa ONR"><svg class="ic"><use href="#i-gear"/></svg></button>
            <button class="mini-btn at" id="btn-autotutela" title="Processo de autotutela registral (Prov. CNJ 195/2025, art. 440-BG; LRP)"><svg class="ic"><use href="#i-scale"/></svg><span>Autotutela registral</span></button>
          </div>
        </div>
        <div class="imoveis-sticky">
        <div class="vista-toggle" id="vista-toggle">
          <button type="button" class="vt-btn" data-vista="todas" title="Todas as matrículas, inclusive as exclusivas da carga ITN 03">Todas <span id="vt-count-todas" class="vt-count"></span></button>
          <button type="button" class="vt-btn active" data-vista="mapa" title="Matrículas com mapa (polígono)">Mapeadas <span id="vt-count-mapa" class="vt-count"></span></button>
          <button type="button" class="vt-btn" data-vista="dentro" title="Matrículas dentro do perímetro do município">Dentro do município <span id="vt-count-dentro" class="vt-count"></span></button>
          <button type="button" class="vt-btn" data-vista="fora" title="Matrículas fora do perímetro do município">Fora do município <span id="vt-count-fora" class="vt-count"></span></button>
          <button type="button" class="vt-btn" data-vista="ultrapassa" title="Matrículas que ultrapassam o limite (parte em município vizinho)">Ultrapassam <span id="vt-count-ultrapassa" class="vt-count"></span></button>
          <button type="button" class="vt-btn" data-vista="itn03" title="Matrículas exclusivas da carga ITN 03 (sem mapa)">Exclusivas ITN 03 <span id="vt-count-itn03" class="vt-count"></span></button>
          <button type="button" class="vt-btn vt-onr" data-vista="prontas" title="Mapeadas prontas para enviar ao Mapa da ONR (com todos os dados e não enviadas)">Prontas p/ ONR <span id="vt-count-prontas" class="vt-count"></span></button>
          <button type="button" class="vt-btn vt-onr" data-vista="enviadas" title="Matrículas já enviadas ao Mapa da ONR">Enviadas <span id="vt-count-enviadas" class="vt-count"></span></button>
          <button type="button" class="vt-btn vt-onr" data-vista="faltando" title="Mapeadas que ainda faltam enviar ao Mapa da ONR (não enviadas, exceto fora do município/encerradas)">Faltando enviar <span id="vt-count-faltando" class="vt-count"></span></button>
        </div>
        <div class="itn03-actions" id="itn03-actions" style="display:none">
          <button class="mini-btn" id="btn-itn03-nova" title="Cadastrar uma matrícula só para a carga ITN 03 (sem coordenadas/mapa)"><svg class="ic"><use href="#i-plus"/></svg><span>Nova matrícula</span></button>
          <button class="mini-btn onr" id="btn-itn03-export-excl" title="Exportar a carga ITN 03 das matrículas exclusivas aptas"><svg class="ic"><use href="#i-down"/></svg><span>Exportar carga</span></button>
        </div>
        <div class="search-wrap">
          <svg class="search-ic" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <input id="busca" type="text" placeholder="Buscar… matrícula, proprietário, ou 744-760 (intervalo) e 744;822 (específicas)">
          <button id="busca-clear" class="search-clear" title="Limpar" style="display:none">×</button>
        </div>
        </div><!-- /.imoveis-sticky -->
        <div id="saved-list"><div class="empty-list">Carregando…</div></div>
      </div>
      </section>

    <section class="vx-pane" data-pane="cadastrar">
        <header class="vx-pane-head"><div class="vx-ph-ic"><svg class="ic"><use href="#i-edit"/></svg></div><div class="vx-ph-tx"><h2>Cadastrar imóvel</h2><p>Cole um memorial descritivo (GMS/UTM) para gerar o polígono, confira e grave. O destaque de cor aparece após mapear.</p></div></header>
      <details class="onr-accordion manual-accordion" open>
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
      <div class="actions" style="margin-top:7px">
        <button class="btn-ghost" id="btn-analisar" style="flex:1" title="Valida os marcos: compara as coordenadas transcritas com o caminhamento por azimute/distância e aponta vértices inconsistentes">🔍 Analisar coordenadas (validar marcos)</button>
      </div>
      <div class="actions" style="margin-top:7px">
        <button class="btn-ghost" id="btn-revisar-tracado" style="flex:1;display:none;border-color:#f59e0b;color:#f59e0b" title="Este imóvel tem vértices inconsistentes — escolher entre traçado correto e transcrito (com erros)">⚠ Revisar traçado (coordenadas inconsistentes)</button>
      </div>
        </div>
      </details>
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
        <div class="cor-sub-lbl">Preenchimento (fundo)</div>
        <div class="cor-grid" id="cor-grid"></div>
        <div class="cor-sub-lbl" style="margin-top:9px">Linha (contorno)</div>
        <div class="cor-grid" id="cor-grid-linha"></div>
        <div class="op-wrap">
          <span class="op-lbl">Intensidade</span>
          <input type="range" id="cor-op" class="op-range" min="0.08" max="0.55" step="0.01" value="0.18">
        </div>
        <button type="button" class="btn-ghost" id="cor-clear" style="margin-top:8px;width:100%">Remover destaque</button>
        <p class="cor-hint">Dica: defina cores diferentes para o <b>fundo</b> e a <b>linha</b> quando houver imóveis vizinhos ou desmembramentos. Clique sobre um imóvel no mapa (em "Ver todos") para destacá-lo. O vermelho é reservado a sobreposições.</p>
      </div>
      
      <div class="vx-sub-title"><svg class="ic"><use href="#i-globe"/></svg>Dados para o Mapa ONR</div>
      <div class="onr-box">
        <details class="onr-accordion" open>
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
      </section>

    <section class="vx-pane" data-pane="importar">
        <header class="vx-pane-head"><div class="vx-ph-ic"><svg class="ic"><use href="#i-upload"/></svg></div><div class="vx-ph-tx"><h2>Importar arquivos</h2><p>Arraste ou selecione arquivos <b>KML</b> ou <b>PDF</b> (matrícula/SIGEF). O tipo é detectado automaticamente: KML vira polígono na hora; PDF é lido por IA. Aceita vários de uma vez.</p></div></header>
      <div class="dropzone" id="vx-drop" tabindex="0" role="button" aria-label="Importar arquivos KML ou PDF">
        <input type="file" id="vx-drop-file" accept=".kml,.pdf,application/pdf,application/vnd.google-earth.kml+xml" multiple hidden>
        <div class="dz-ic">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
        </div>
        <div class="dz-main">Arraste os arquivos aqui ou <span class="dz-link">clique para selecionar</span></div>
        <div class="dz-sub">KML · PDF de matrícula/SIGEF</div>
        <div class="dz-badges"><span class="dz-badge">.kml</span><span class="dz-badge">.pdf</span></div>
      </div>
      <button type="button" class="cfg-link" id="btn-gemini-config" title="Configurar os modelos e a chave da IA (Gemini)"><svg class="ic"><use href="#i-gear"/></svg><span>Configurar IA (Gemini)</span></button>
      </section>

      <section class="vx-pane" data-pane="onr">
        <header class="vx-pane-head"><div class="vx-ph-ic"><svg class="ic"><use href="#i-globe"/></svg></div><div class="vx-ph-tx"><h2>ONR e carga ITN 03</h2><p>Envie os imóveis prontos ao Mapa da ONR e exporte a carga ITN 03. O preenchimento dos <b>Dados ONR</b> de cada imóvel fica na aba <b>Cadastrar</b>.</p></div></header>
        <div class="vx-actions">
          <div class="vx-act-card">
            <div class="vx-act-h"><span class="vx-act-ic"><svg class="ic"><use href="#i-globe"/></svg></span>Mapa do Registro de Imóveis (ONR)</div>
            <p class="vx-act-d">Transmite ao Mapa da ONR todos os imóveis marcados como <b>Prontos p/ ONR</b>.</p>
            <button type="button" class="btn-save vx-act-btn" id="vx-onr-enviar"><svg class="ic"><use href="#i-send"/></svg><span>Enviar prontos ao Mapa da ONR</span></button>
            <button type="button" class="btn-ghost vx-act-btn2" id="vx-onr-config"><svg class="ic"><use href="#i-gear"/></svg><span>Configurar API do Mapa ONR</span></button>
          </div>
          <div class="vx-act-card">
            <div class="vx-act-h"><span class="vx-act-ic itn"><svg class="ic"><use href="#i-down"/></svg></span>Carga ITN 03</div>
            <p class="vx-act-d">Gera o JSON validável da carga ITN 03 (ONR, schema v1.2.0), separado por urbano/rural.</p>
            <button type="button" class="btn-itn03 vx-act-btn" id="vx-itn-lote"><svg class="ic"><use href="#i-down"/></svg><span>Exportar carga ITN 03 (prontos)</span></button>
            <button type="button" class="btn-ghost vx-act-btn2" id="vx-itn-excl"><svg class="ic"><use href="#i-down"/></svg><span>Exportar só exclusivas da ITN 03</span></button>
            <button type="button" class="btn-ghost vx-act-btn2" id="vx-itn-nova"><svg class="ic"><use href="#i-plus"/></svg><span>Nova matrícula só ITN 03</span></button>
          </div>
        </div>
        <div class="vx-flow">
          <b>Como funciona</b>
          <ol>
            <li><b>Enviar prontos</b> → publica no Mapa da ONR os imóveis aptos.</li>
            <li><b>Exportar carga ITN 03</b> → baixa o arquivo para envio da ITN 03.</li>
            <li>Faltando dados? Abra o imóvel e preencha os <b>Dados ONR</b> na aba <b>Cadastrar</b>.</li>
          </ol>
        </div>
      </section>

    <section class="vx-pane" data-pane="limites">
        <header class="vx-pane-head"><div class="vx-ph-ic"><svg class="ic"><use href="#i-compass"/></svg></div><div class="vx-ph-tx"><h2>Limites do município</h2><p>Carregue o limite do município para conferir quais imóveis estão dentro, fora ou ultrapassam o perímetro.</p></div></header>
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
        <div class="actions" style="margin-top:6px">
          <button class="btn-ghost" id="btn-muni-kml" style="flex:1" title="Carregar o limite a partir de um arquivo KML (não depende do IBGE)">📂 Carregar limite por KML</button>
          <input type="file" id="muni-kml-file" accept=".kml,application/vnd.google-earth.kml+xml" style="display:none">
        </div>
        <div class="status" id="muni-status"></div>
      </div>
      </section>

    <!-- ===== ABA: RELATÓRIOS ===== -->
    <section class="vx-pane" data-pane="relatorios">
      <header class="vx-pane-head">
        <div class="vx-ph-ic"><svg class="ic"><use href="#i-chart"/></svg></div>
        <div class="vx-ph-tx">
          <h2>Relatórios de completude</h2>
          <p>Panorama do acervo: matrículas faltantes na numeração (1 até a maior), envio ao Mapa da ONR e aptidão para a carga ITN 03 — com percentuais e a lista exata do que falta.</p>
        </div>
      </header>
      <div class="rel-toolbar">
        <button class="mini-btn" id="rel-atualizar"><svg class="ic"><use href="#i-refresh"/></svg><span>Recalcular</span></button>
        <span class="rel-quando" id="rel-quando"></span>
      </div>
      <div class="rel-grid" id="rel-wrap">
        <div class="rel-loading">Abra esta aba para calcular os relatórios.</div>
      </div>
    </section>

  </div><!-- /.vx-stage -->
</div><!-- /.mapeador-shell -->

<!-- Botão flutuante p/ abrir o painel no mobile -->
<button id="fab-panel" class="fab-panel" title="Imóveis e cadastro" aria-label="Abrir painel">
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
</button>
<div id="panel-backdrop" class="panel-backdrop"></div>

<!-- Modal: editar dados do imóvel -->
<div id="modal-autotutela" class="modal-ov">
  <div class="modal-card at-card">
    <div class="modal-h">
      <h3 id="at-titulo">⚖ Autotutela Registral</h3>
      <button class="modal-x" id="at-fechar" title="Fechar">×</button>
    </div>
    <div class="modal-b">
      <!-- VISÃO LISTA -->
      <div id="at-view-lista">
        <div class="at-bar">
          <button class="btn-mini-prim" id="at-novo">+ Novo procedimento</button>
          <span class="at-hint">Prov. CNJ nº 195/2025 (art. 440-BG do CNN/CN/CNJ-Extra) · LRP (arts. 110, 213 e 214) · CC art. 1.247</span>
        </div>
        <div id="at-lista" class="at-lista"></div>
      </div>

      <!-- VISÃO FORMULÁRIO -->
      <div id="at-view-form" style="display:none">
        <input type="hidden" id="at-id">
        <button class="at-voltar" id="at-voltar">‹ Voltar à lista</button>

        <div class="at-steps" id="at-steps"></div>

        <div class="at-grid">
          <div class="at-f"><label>Nº do procedimento</label><input type="text" id="at-numero" readonly></div>
          <div class="at-f"><label>Prenotação (prioridade registral)</label><input type="text" id="at-prenotacao" placeholder="nº no Livro de Protocolo"></div>
          <div class="at-f"><label>Data de abertura</label><input type="date" id="at-data"></div>
          <div class="at-f"><label>Prazo de manifestação (dias)</label><input type="number" id="at-prazo" min="1" max="365" value="15"></div>
        </div>

        <div class="at-grid">
          <div class="at-f"><label>Fundamento (art. 440-BG, caput)</label>
            <select id="at-fundamento">
              <option value="litigio">Potencial litígio entre titulares</option>
              <option value="alta_indagacao">Alta indagação (dilação probatória)</option>
            </select></div>
          <div class="at-f"><label>Tipo de vício</label>
            <select id="at-vicio">
              <option value="sobreposicao">Sobreposição de área</option>
              <option value="duplicidade">Duplicidade de matrícula</option>
              <option value="multiplicidade">Multiplicidade de matrículas</option>
              <option value="erro_material">Erro material na matrícula</option>
              <option value="georref_erro">Erro na descrição georreferenciada</option>
              <option value="serventia_incompetente">Serventia territorialmente incompetente</option>
              <option value="outro">Outro</option>
            </select></div>
          <div class="at-f"><label>Fase</label>
            <select id="at-fase">
              <option value="aberto">1 · Aberto (ato + prenotação)</option>
              <option value="relatorio">2 · Relatório preliminar</option>
              <option value="notificacao">3 · Notificação das partes</option>
              <option value="manifestacao">4 · Manifestação (prazo)</option>
              <option value="transacao">5 · Transação</option>
              <option value="replica">6 · Réplica</option>
              <option value="decisao">7 · Decisão</option>
              <option value="saneamento">8 · Saneamento</option>
              <option value="encerrado">Encerrado</option>
              <option value="remetido">Remetido ao Corregedor (art. 214 LRP)</option>
              <option value="arquivado">Arquivado</option>
            </select></div>
        </div>

        <div class="at-f"><label>Objeto e fatos a apurar (art. 440-BG, II)</label>
          <textarea id="at-objeto" rows="3" placeholder="Delimite o objeto e os fatos a serem apurados."></textarea></div>
        <div class="at-f"><label>Matrículas/transcrições atingidas</label>
          <input type="text" id="at-matriculas" placeholder="Ex.: 2063; 506"></div>

        <div class="at-f"><label>Relatório circunstanciado preliminar (art. 440-BG, I e II) <button type="button" class="at-ia" data-alvo="relatorio">✨ Gerar com IA</button></label>
          <textarea id="at-relatorio" rows="4" placeholder="Descreva o vício identificado e as providências de saneamento propostas."></textarea></div>

        <!-- PARTES -->
        <div class="at-partes-box">
          <div class="at-sec">Partes interessadas (titulares de direitos a notificar)</div>
          <div id="at-partes"></div>
          <button class="btn-mini" id="at-add-parte">+ Adicionar parte</button>
        </div>

        <!-- DECISÃO -->
        <div class="at-sec">Decisão e saneamento</div>
        <div class="at-f"><label>Decisão fundamentada <button type="button" class="at-ia" data-alvo="decisao">✨ Gerar com IA</button></label>
          <textarea id="at-decisao" rows="3" placeholder="Síntese das manifestações e fundamentação da decisão."></textarea></div>
        <div class="at-grid">
          <div class="at-f"><label>Resultado</label>
            <select id="at-resultado">
              <option value="">— em andamento —</option>
              <option value="saneado">Saneado (atos corretivos)</option>
              <option value="remetido">Remetido ao Corregedor (art. 214 LRP)</option>
              <option value="arquivado">Arquivado</option>
            </select></div>
          <div class="at-f"><label>Oficial (assinatura)</label><input type="text" id="at-oficial" placeholder="Nome do Oficial / substituto"></div>
        </div>
        <div class="at-f"><label>Atos de saneamento determinados (retificação/averbação)</label>
          <textarea id="at-saneamento" rows="2" placeholder="Ex.: Retificar o memorial da matrícula X; averbar a exclusão da área sobreposta."></textarea></div>
        <div class="at-f"><label>Observações internas</label><textarea id="at-obs" rows="2"></textarea></div>

        <div class="at-sec">Comprovantes anexados ao procedimento</div>
        <div id="at-anexos-geral" class="at-anexos-lista"><span class="at-hint">—</span></div>
        <div class="at-up-row">
          <select id="at-up-geral-tipo" class="at-up-tipo">
            <option value="ato">Ato/edital</option>
            <option value="comprovante_notificacao">Comprovante de notificação</option>
            <option value="manifestacao">Manifestação</option>
            <option value="ar">AR/aviso de recebimento</option>
            <option value="outro">Outro</option>
          </select>
          <button type="button" class="btn-mini" id="at-up-geral-btn">📎 Anexar documento geral</button>
          <input type="file" id="at-up-geral-file" style="display:none" accept=".pdf,.png,.jpg,.jpeg">
        </div>

        <div class="at-docs">
          <span class="at-docs-l">Documentos (PDF):</span>
          <button class="btn-doc" data-doc="abertura">Ato de abertura</button>
          <button class="btn-doc" data-doc="relatorio">Relatório preliminar</button>
          <button class="btn-doc" data-doc="notificacao">Notificação</button>
          <button class="btn-doc" data-doc="decisao">Decisão / Termo</button>
        </div>

        <div class="at-form-foot">
          <button class="btn-excluir" id="at-excluir">Excluir</button>
          <div style="flex:1"></div>
          <span id="at-save-status" class="at-save-status"></span>
          <button class="btn-mini-prim" id="at-salvar">Salvar</button>
        </div>
      </div>
    </div>
  </div>
</div>
<form id="at-pdf-form" method="POST" target="_blank" style="display:none"><input type="hidden" name="acao" value="autotutela_pdf"><input type="hidden" name="id" id="at-pdf-id"><input type="hidden" name="tipo" id="at-pdf-tipo"></form>

<div id="modal-3d" class="modal-ov">
  <div class="modal-3d-card">
    <div class="modal-3d-bar">
      <span>🧊 <span id="m3d-title">Visão 3D do imóvel</span></span>
      <span style="flex:1"></span>
      <button id="m3d-close" class="modal-x" title="Fechar">×</button>
    </div>
    <div id="m3d-host" class="modal-3d-host"></div>
    <div id="m3d-legend" class="m3d-legend" style="display:none"></div>
    <div id="m3d-msg" class="modal-3d-msg" style="display:none"></div>
    <div class="modal-3d-foot" id="m3d-foot">
      <span id="m3d-foot-txt">Arraste para girar · role o mouse para aproximar.</span>
    </div>
  </div>
</div>

<div id="modal-edit" class="modal-ov">
  <div class="modal-card">
    <div class="modal-h">
      <h3 id="ed-titulo">Editar dados do imóvel</h3>
      <button class="modal-x" id="ed-cancelar" title="Fechar">×</button>
    </div>
    <input type="hidden" id="ed-id">
    <div class="modal-b">
      <div class="ed-stats" id="ed-stats">
        <div class="ed-stat"><div class="v" id="eds-vtx">—</div><div class="k">Vértices</div></div>
        <div class="ed-stat"><div class="v" id="eds-area">—</div><div class="k" id="eds-area-k">Área (UTM 23S)</div></div>
        <div class="ed-stat"><div class="v" id="eds-per">—</div><div class="k">Perímetro</div></div>
        <div class="ed-stat"><div class="v" id="eds-cen" style="font-size:12px">—</div><div class="k">Centro lat,lng</div></div>
      </div>
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
            <div id="ed-mapear-hint" class="ed-mapear-hint" style="display:none">📍 Matrícula <b>exclusiva da ITN 03</b> (sem mapa). Anexe um <b>KML</b> ou o <b>PDF do SIGEF</b> abaixo para <b>mapeá-la</b> automaticamente (extrai as coordenadas e passa a aparecer no mapa).</div>
            <div id="ed-drop" class="ed-drop" tabindex="0">
              <div class="ed-drop-ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
              <b>Arraste um arquivo aqui</b> ou clique para selecionar
              <small>PDF da matrícula · PDF do SIGEF · KML</small>
              <label class="ed-drop-opts" onclick="event.stopPropagation()"><input type="checkbox" id="ed-anx-ia" checked> Analisar com IA p/ preencher campos faltantes</label>
            </div>
            <input type="file" id="ed-anx-file" accept=".pdf,.kml,application/pdf,application/vnd.google-earth.kml+xml" style="display:none">
            <p class="cor-hint">Os PDFs enviados para cadastro/complemento ficam arquivados aqui para conferência e reprocessamento.</p>

            <div class="ed-geo-box">
              <div class="ed-geo-h">📐 Memorial / coordenadas / KML</div>
              <textarea id="ed-geo-text" class="ed-geo-text" placeholder="Cole aqui o memorial descritivo (azimutes/distâncias ou GMS/UTM), uma lista de coordenadas, ou a estrutura do KML. O sistema detecta o formato, extrai os vértices e mapeia a matrícula."></textarea>
              <div class="ed-geo-acts">
                <button type="button" class="btn-ghost" id="ed-geo-aplicar">📌 Mapear com este texto</button>
                <button type="button" class="btn-ghost" id="ed-btn-analisar" title="Valida os marcos: compara as coordenadas transcritas com o caminhamento por azimute/distância">🔍 Analisar coordenadas (validar marcos)</button>
                <button type="button" class="btn-ghost" id="ed-btn-revisar" style="display:none;border-color:#f59e0b;color:#f59e0b" title="Coordenadas inconsistentes — escolher entre traçado correto e transcrito (com erros)">⚠ Revisar traçado (coordenadas inconsistentes)</button>
                <span id="ed-geo-status" class="ed-geo-status"></span>
              </div>
            </div>
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
      <button class="btn-ghost" id="ed-onr-correcao" style="display:none;border-color:var(--teal);color:var(--teal)" title="Reenviar este imóvel ao Mapa ONR como RETIFICAÇÃO (correção dos dados já enviados)">↻ Enviar correção à ONR</button>
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
      <p class="cor-hint">O token é gerado no portal do Mapa ONR (Configurações &gt; Chave API para envio de polígonos) com certificado e-CPF e tem validade de 15 dias. Fica salvo no servidor em <code>vertex/config_onr.json</code>.</p>
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
      <p class="cor-hint">A chave fica salva no servidor em <code>vertex/config_gemini.json</code>. Use um modelo com leitura de PDF (visão). O modelo padrão é o usado para extrair os dados das matrículas.</p>
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
let geoOverrideWgs84 = null; // traçado escolhido no laudo de coordenadas (transcrito x corrigido)
let laudoAtual = null;       // último laudo do memorial atual (habilita "Revisar traçado")
let modo = 'single';
let LabelOverlay = null;

function initMap(){
  map = new google.maps.Map(document.getElementById('map'), {
    // Centro inicial neutro (visão ampla do estado). O foco real é dado pelo perímetro do
    // município da serventia (desenharLimite -> fitBounds) ou pelos imóveis (verTodos).
    center: {lat:-5.0, lng:-45.3}, zoom: 6,
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
  wire3D();     // controles de visão 3D
  iniciarPollLista();   // sincronização multiusuário (sem refresh da página)
}
window.initMap = initMap;

/* ======================= VISÃO 3D ======================= */
/* Alvo do 3D: imóvel em foco (recém-mapeado) ou o ativo na visão geral. */
function imovel3DAlvo(){
  // 1) imóvel ativo (aberto/gravado) — tem id, permite localizar as sobreposições
  if(typeof imovelAtivoId!=='undefined' && imovelAtivoId){
    const it=(itensOverview||[]).find(x=>x.id===imovelAtivoId);
    if(it && Array.isArray(it.pts) && it.pts.length>=3) return it;
  }
  // 2) geometria recém-desenhada (pode não estar salva)
  if(typeof lastGeo!=='undefined' && lastGeo && Array.isArray(lastGeo.pts) && lastGeo.pts.length>=3)
    return {pts:lastGeo.pts, cor:lastGeo.cor, cor_linha:lastGeo.cor_linha, identificador:(document.getElementById('identificador')||{}).value||''};
  return null;
}
function centro3D(pts){ let la=0,ln=0; pts.forEach(p=>{la+=p[0];ln+=p[1];}); return {lat:la/pts.length, lng:ln/pts.length}; }
function metros3D(a,b){ const R=6378137, la=(a[0]+b[0])/2*Math.PI/180; const x=(b[1]-a[1])*Math.PI/180*Math.cos(la), y=(b[0]-a[0])*Math.PI/180; return Math.hypot(x,y)*R; }
function rangeParaImovel(it){
  if(!it||!it.pts||it.pts.length<2) return 900;
  let minLa=Infinity,maxLa=-Infinity,minLn=Infinity,maxLn=-Infinity;
  it.pts.forEach(p=>{minLa=Math.min(minLa,p[0]);maxLa=Math.max(maxLa,p[0]);minLn=Math.min(minLn,p[1]);maxLn=Math.max(maxLn,p[1]);});
  return Math.max(400, Math.min(6000, metros3D([minLa,minLn],[maxLa,maxLn])*2.4));
}

/* (1) Inclinação/rotação no PRÓPRIO mapa (visão oblíqua) */
let tiltOn=false, heading3D=0;
function set3DTilt(on){
  tiltOn=on; heading3D=0;
  const b=document.getElementById('btn-3d-tilt'), l=document.getElementById('btn-3d-left'), r=document.getElementById('btn-3d-right');
  if(b) b.classList.toggle('on', on);
  if(l) l.style.display=on?'':'none';
  if(r) r.style.display=on?'':'none';
  if(!map) return;
  if(on){
    const mt=map.getMapTypeId(); if(mt!=='satellite'&&mt!=='hybrid') map.setMapTypeId('hybrid');
    if(map.getZoom()<16) map.setZoom(17);
    map.setHeading(0); map.setTilt(45);
    setStatus('ok','Mapa inclinado. Use ⟲ ⟳ para girar. Em áreas rurais o relevo real fica melhor no botão "Ver em 3D".');
  } else { map.setTilt(0); map.setHeading(0); }
}
function rot3D(d){ if(!map) return; heading3D=(heading3D+d+360)%360; map.setHeading(heading3D); if((map.getTilt()||0)<45) map.setTilt(45); }

/* (2) 3D PRÓPRIO — satélite + relevo servidos pelo backend (independe do Map Tiles API) */
let m3dLatLng=null, three3D=null, m3dGoogleEl=null;
const PALETA_MAT=['#2563eb','#16a34a','#f59e0b','#7c3aed','#0891b2','#db2777','#65a30d','#0d9488','#ea580c','#4f46e5','#0284c7','#a16207','#be185d','#15803d','#b45309','#6d28d9'];
// Cor MANUAL definida no imóvel (via seletor de cor), se houver.
function corManualImovel(it){ return (it && typeof corValida==='function' && corValida(it.cor)) ? it.cor : null; }
// Cor FIXA (não deve ser substituída pela paleta): manual do usuário, ou cinza para imóvel "morto"/encerrado.
function corFixaImovel(it){
  if(it && typeof imovelMorto==='function' && imovelMorto(it)) return '#9aa3ad';
  return corManualImovel(it);
}
// Atribui cores a uma lista ordenada: mantém a cor fixa (manual/morto) e dá cores da paleta aos demais,
// evitando colidir com cores fixas já usadas. Retorna array de cores paralelo a `items`.
function atribuirCoresLista(items, getFixa){
  const usadas=new Set();
  (items||[]).forEach(it=>{ const f=getFixa(it); if(f) usadas.add(String(f).toLowerCase()); });
  let pi=0;
  const prox=()=>{ let c, g=0; do{ c=PALETA_MAT[pi%PALETA_MAT.length]; pi++; g++; }while(usadas.has(c.toLowerCase()) && g<=PALETA_MAT.length*2); usadas.add(c.toLowerCase()); return c; };
  return (items||[]).map(it=>{ const f=getFixa(it); return f || prox(); });
}
function carregarThree(cb){
  if(window.THREE){ cb(); return; }
  const s=document.createElement('script');
  s.src='https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js';
  s.onload=()=>cb(); s.onerror=()=>cb(new Error('Falha ao carregar a biblioteca 3D.'));
  document.head.appendChild(s);
}
function _mpp3D(z,lat){ return 156543.03392*Math.cos(lat*Math.PI/180)/Math.pow(2,z); }
// Projeção Web Mercator em pixels (para recortar a textura no perímetro do município)
function projPx3D(lat,lng,z){ const s=256*Math.pow(2,z); const x=(lng+180)/360*s; const sinL=Math.sin(lat*Math.PI/180); const y=(0.5 - Math.log((1+sinL)/(1-sinL))/(4*Math.PI))*s; return {x,y}; }
// Plano de mosaico: escolhe o MAIOR zoom (mais detalhe) cujo município caiba numa grade N×N (N<=3).
function planoTiles3D(covTotal, clat){
  for(let z=17; z>=9; z--){ const tm=640*_mpp3D(z,clat); const N=Math.ceil(covTotal/tm); if(N<=3) return {z, N, tm, cov:N*tm}; }
  const z=9, tm=640*_mpp3D(9,clat); const N=Math.min(3, Math.max(1, Math.ceil(covTotal/tm))); return {z, N, tm, cov:N*tm};
}
// Anéis do limite do município (usa o já carregado; senão tenta o cache salvo). Retorna [[lat,lng],...] por anel.
async function limiteRings3D(){
  let geom = (typeof limiteTurf!=='undefined' && limiteTurf && limiteTurf.geometry) ? limiteTurf.geometry : null;
  let nome = (typeof limiteNome!=='undefined' ? limiteNome : '') || '';
  if(!geom){
    try{
      const c = await post({acao:'limite_cache'});
      if(c && c.ok && c.geojson){ const f=limiteToTurf(c.geojson); if(f && f.geometry){ geom=f.geometry; nome=c.nome||nome||'município'; } }
    }catch(_){}
  }
  if(!geom){
    // fallback: usa o município selecionado no seletor (base IBGE local, offline)
    try{
      const sel=document.getElementById('muni-list'); const id=sel?sel.value:'';
      if(id){ const r=await post({acao:'ibge_malha', municipio:id});
        if(r && r.ok && r.geojson){ const f=limiteToTurf(r.geojson); if(f && f.geometry){ geom=f.geometry; nome=(sel.options[sel.selectedIndex]?sel.options[sel.selectedIndex].textContent:'')||nome||'município'; } } }
    }catch(_){}
  }
  const rings=[];
  const pushPoly=(poly)=>{ (poly||[]).forEach(r=>{
    if(!r || r.length<3) return;
    // subamostra anéis muito densos (desempenho)
    const step = r.length>1600 ? Math.ceil(r.length/1600) : 1;
    const out=[]; for(let i=0;i<r.length;i+=step){ out.push([r[i][1], r[i][0]]); }
    if(out.length && (out[0][0]!==out[out.length-1][0] || out[0][1]!==out[out.length-1][1])) out.push([r[0][1], r[0][0]]);
    if(out.length>=3) rings.push(out);
  }); };
  if(geom){
    if(geom.type==='Polygon') pushPoly(geom.coordinates);
    else if(geom.type==='MultiPolygon') (geom.coordinates||[]).forEach(pc=>pushPoly(pc));
  }
  return {rings, nome};
}
let _g3d=null;
async function abrir3D(){
  // Espelha o que está VISÍVEL no mapa (respeita o filtro do painel, ex.: "2063;*").
  const getOpt=(poly,k)=>{ try{ return poly.get(k); }catch(_){ return null; } };
  const props=[];
  (itensOverview||[]).forEach(it=>{
    if(!it._poly || !(it._poly.getMap && it._poly.getMap())) return;
    if(!Array.isArray(it.pts) || it.pts.length<3) return;
    const cor = getOpt(it._poly,'strokeColor') || getOpt(it._poly,'fillColor') || ((typeof corBaseImovel==='function')?corBaseImovel(it):'#5b96e6');
    props.push({pts:it.pts, corMapa:cor, id:it.id, nome:(it.identificador||it.numero_matricula||'').toString(), corFixa:corFixaImovel(it)});
  });
  if(!props.length){
    // Sem consulta no painel: usa o imóvel ativo/aberto (ou a geometria desenhada) — "ver imóvel isolado".
    const alvo = (typeof imovel3DAlvo==='function') ? imovel3DAlvo() : null;
    if(alvo && Array.isArray(alvo.pts) && alvo.pts.length>=3){
      let cor = alvo.cor || alvo.cor_linha;
      if(!cor && alvo._poly){ cor = getOpt(alvo._poly,'strokeColor') || getOpt(alvo._poly,'fillColor'); }
      if(!cor && typeof corBaseImovel==='function'){ try{ cor = corBaseImovel(alvo); }catch(_){} }
      props.push({pts:alvo.pts, corMapa:cor||'#5b96e6', id:alvo.id, nome:(alvo.identificador||alvo.numero_matricula||'').toString(), corFixa:corFixaImovel(alvo)});
    }
  }
  if(!props.length){ setStatus('err','Nada visível no mapa para ver em 3D. Faça uma consulta no painel (ex.: 2063;*) ou abra um imóvel.'); return; }
  const LIMITE=140; let focoIds=null;
  if(props.length>LIMITE && typeof imovelAtivoId!=='undefined' && imovelAtivoId){
    focoIds=new Set([imovelAtivoId]);
    (overlapPolys||[]).forEach(p=>{ if(p._pair && p._pair.includes(imovelAtivoId)){ p._pair.forEach(id=>focoIds.add(id)); } });
  }
  const propsUsar = focoIds ? props.filter(p=>focoIds.has(p.id)) : (props.length>LIMITE ? props.slice(0,LIMITE) : props);
  const sobrepos=[];
  (overlapPolys||[]).forEach(p=>{
    if(!(p.getMap && p.getMap())) return;
    if(focoIds && p._pair && !p._pair.some(id=>focoIds.has(id))) return;
    const cor = getOpt(p,'fillColor') || (p._tipo==='morto'?'#9aa3ad':'#e2342f');
    const tipo = p._tipo || (cor && cor.toLowerCase()==='#9aa3ad' ? 'morto':'material');
    const paths = p.getPaths ? p.getPaths().getArray() : (p.getPath?[p.getPath()]:[]);
    paths.forEach(path=>{ const ring=path.getArray().map(ll=>[ll.lat(), ll.lng()]); if(ring.length>=3) sobrepos.push({ring, cor, tipo}); });
  });
  const temSobrep = sobrepos.some(s=>s.tipo!=='morto');
  // Cores distintas por matrícula: mantém cor manual/morto; demais recebem paleta.
  // Ativa quando há sobreposição, cores manuais, OU o mapa já veio colorido por um filtro "X;*"
  // (inclui desmembramentos, mesmo sem sobreposição material).
  const temManual = propsUsar.some(pr=>pr.corFixa);
  const jaDistinto = new Set(propsUsar.map(pr=>String(pr.corMapa||'').toLowerCase())).size>1;
  let legenda=null;
  if(propsUsar.length>=2 && propsUsar.length<=PALETA_MAT.length && (temSobrep || temManual || jaDistinto)){
    const cores=atribuirCoresLista(propsUsar, pr=>pr.corFixa);
    legenda=[];
    propsUsar.forEach((pr,i)=>{ pr.corUsar=cores[i]; legenda.push({cor:pr.corUsar, nome:pr.nome||('#'+pr.id)}); });
  } else {
    propsUsar.forEach(pr=>{ pr.corUsar=pr.corMapa; });
  }
  let limite={rings:[],nome:''}; try{ limite=await limiteRings3D(); }catch(_){}
  _g3d={propsUsar, sobrepos, temSobrep, legenda, limite};
  document.getElementById('modal-3d').classList.add('show');
  await render3DGoogle();
}
async function render3D(){
  if(!_g3d) return;
  const {propsUsar, sobrepos, legenda, limite, temSobrep} = _g3d;
  let minLa=Infinity,maxLa=-Infinity,minLn=Infinity,maxLn=-Infinity;
  const acc=p=>{minLa=Math.min(minLa,p[0]);maxLa=Math.max(maxLa,p[0]);minLn=Math.min(minLn,p[1]);maxLn=Math.max(maxLn,p[1]);};
  propsUsar.forEach(pr=>pr.pts.forEach(acc));
  const clat=(minLa+maxLa)/2, clng=(minLn+maxLn)/2, coslat=Math.cos(clat*Math.PI/180);
  const spanE=(maxLn-minLn)*111320*coslat, spanN=(maxLa-minLa)*110540, maxSpan=Math.max(spanE,spanN,50);
  const mppNeeded=(maxSpan*1.6)/640;
  const z=Math.max(10,Math.min(20,Math.floor(Math.log2(156543.03392*coslat/mppNeeded))));
  const mpp=_mpp3D(z,clat), cov=640*mpp;
  m3dLatLng={lat:clat,lng:clng};
  const nOver=sobrepos.filter(s=>s.tipo!=='morto').length;
  const ea=document.getElementById('m3d-earth'), ma=document.getElementById('m3d-maps');
  if(ea) ea.href=`https://earth.google.com/web/@${clat},${clng},300a,${Math.round(cov*1.4)}d,35y,0h,55t,0r`;
  if(ma) ma.href=`https://www.google.com/maps/@${clat},${clng},${z}z/data=!3m1!1e3`;
  const ttl=document.getElementById('m3d-title'); if(ttl) ttl.textContent = `Visão 3D · ${propsUsar.length} imóvel(is)` + (nOver?(' · '+nOver+' sobrep.'):'');
  renderLegenda3D(legenda, temSobrep);
  const host=document.getElementById('m3d-host'), msg=document.getElementById('m3d-msg');
  fechar3DCena(); host.style.display='block'; host.innerHTML='';
  msg.style.display='flex'; msg.textContent='Carregando satélite e relevo…';
  try{
    await new Promise((res,rej)=>carregarThree(e=>e?rej(e):res()));
    const seg=20, grid=[], locs=[];
    for(let j=0;j<=seg;j++){ for(let i=0;i<=seg;i++){
      const u=i/seg, v=j/seg, we=(u-0.5)*cov, wn=(v-0.5)*cov;
      const la=clat+wn/110540, ln=clng+we/(111320*coslat);
      grid.push({u,v,we,wn}); locs.push(la.toFixed(6)+','+ln.toFixed(6));
    }}
    let elev=null;
    try{
      const r=await fetch(window.location.pathname+'?m3d_elev=1&pts='+encodeURIComponent(locs.join('|')));
      const j=await r.json(); if(j && j.ok && Array.isArray(j.elev) && j.elev.length===grid.length) elev=j.elev;
    }catch(_){}
    montarCena3D(host, {seg,cov,clat,clng,coslat,z,grid,elev,props:propsUsar,sobrepos,limiteRings:limite.rings,limiteNome:limite.nome});
    const ft2=document.getElementById('m3d-foot-txt');
    if(ft2) ft2.innerHTML = (nOver?'<b style="color:#e2342f">■</b> vermelho = sobreposição · ':'') + (limite.rings.length?'<b style="color:#2563eb">▬</b> limite'+(limite.nome?(' de '+limite.nome):'')+' · ':'') + 'rótulo = matrícula. <b>Arraste = girar</b> · botão direito (ou Shift) + arraste = mover · roda = zoom.';
    msg.style.display='none';
    setStatus('ok', nOver ? ('Visão 3D — '+nOver+' sobreposição(ões) em vermelho.') : 'Visão 3D carregada.');
  }catch(e){
    host.style.display='none'; msg.style.display='flex';
    msg.innerHTML='Não foi possível montar o 3D embutido. Tente novamente ou use "Inclinar" no próprio mapa.';
  }
}
let _ctl3D=null;
function instalarControles3D(map3d, host){
  let mode=null, sx=0, sy=0, sh=0, st=0, sLat=0, sLng=0, sAlt=0, sRange=0;
  const down=(e)=>{
    if(e.pointerType!=='mouse' || e.button!==0 || e.ctrlKey) return; // toque/2dedos e ctrl ficam nativos
    mode = e.shiftKey ? 'pan' : 'orbit';
    sx=e.clientX; sy=e.clientY; sh=map3d.heading||0; st=map3d.tilt||0;
    const c=map3d.center||{}; sLat=c.lat||0; sLng=c.lng||0; sAlt=c.altitude||0; sRange=map3d.range||1000;
    try{ map3d.stopCameraAnimation(); }catch(_){}
    host.style.cursor = mode==='pan' ? 'grabbing' : 'move';
    e.stopPropagation(); e.preventDefault();
  };
  const move=(e)=>{
    if(!mode) return;
    const dx=e.clientX-sx, dy=e.clientY-sy;
    if(mode==='orbit'){
      let h=sh + dx*0.35, t=st + dy*0.25; if(t<0)t=0; if(t>85)t=85;
      map3d.heading=h; map3d.tilt=t;
    } else {
      const mPerPx=Math.max(0.05, sRange/Math.max(1,host.clientHeight));
      const hr=sh*Math.PI/180, brR=hr+Math.PI/2;
      let east=0, north=0;
      east += -dx*mPerPx*Math.sin(brR); north += -dx*mPerPx*Math.cos(brR);
      east +=  dy*mPerPx*Math.sin(hr);  north +=  dy*mPerPx*Math.cos(hr);
      const dLat=north/110540, dLng=east/((111320*Math.cos(sLat*Math.PI/180))||1);
      map3d.center={lat:sLat+dLat, lng:sLng+dLng, altitude:sAlt};
    }
    e.stopPropagation(); e.preventDefault();
  };
  const up=(e)=>{ if(mode){ mode=null; host.style.cursor='grab'; e.stopPropagation(); } };
  try{ map3d.addEventListener('pointerdown', down, true); }catch(_){}
  window.addEventListener('pointermove', move, true);
  window.addEventListener('pointerup', up, true);
  host.style.cursor='grab';
  _ctl3D={move, up};
}
(function removerBannerAlpha(){
  function limpar(){
    try{
      document.querySelectorAll('div,section,aside').forEach(n=>{
        if(n.dataset && n.dataset.g3dBanner) return;
        const t=(n.textContent||'');
        if(t.length<220 && (/canal\s+alfa/i.test(t) || /development purposes/i.test(t) || (/API\s+Maps\s+JavaScript/i.test(t) && /desenvolvimento/i.test(t)))){
          try{ n.style.setProperty('display','none','important'); }catch(_){}
          if(n.dataset) n.dataset.g3dBanner='1';
        }
      });
    }catch(_){}
  }
  if(document.readyState!=='loading') limpar(); else document.addEventListener('DOMContentLoaded', limpar);
  try{ new MutationObserver(limpar).observe(document.documentElement,{childList:true,subtree:true}); }catch(_){}
  let k=0; const iv=setInterval(()=>{ limpar(); if(++k>60) clearInterval(iv); }, 500);
})();
function hexA3D(hex, a){
  hex=(hex||'#888888').toString().replace('#',''); if(hex.length===3) hex=hex.split('').map(c=>c+c).join('');
  const r=parseInt(hex.substr(0,2),16)||136, g=parseInt(hex.substr(2,2),16)||136, b=parseInt(hex.substr(4,2),16)||136;
  return `rgba(${r},${g},${b},${a})`;
}
function mostrarFallback3D(txt){
  const host=document.getElementById('m3d-host'), msg=document.getElementById('m3d-msg');
  if(host){ host.style.display='none'; host.innerHTML=''; }
  if(msg){ msg.style.display='flex';
    msg.innerHTML=txt+'<br><br>Use o <a href="#" id="m3d-alt" style="color:#8ab4ff;text-decoration:underline">visualizador com relevo (alternativo)</a>.';
    const alt=document.getElementById('m3d-alt'); if(alt) alt.onclick=(e)=>{ e.preventDefault(); render3D(); };
  }
}
async function render3DGoogle(){
  if(!_g3d) return;
  const {propsUsar, sobrepos, legenda, limite, temSobrep} = _g3d;
  let minLa=Infinity,maxLa=-Infinity,minLn=Infinity,maxLn=-Infinity;
  const acc=p=>{minLa=Math.min(minLa,p[0]);maxLa=Math.max(maxLa,p[0]);minLn=Math.min(minLn,p[1]);maxLn=Math.max(maxLn,p[1]);};
  propsUsar.forEach(pr=>pr.pts.forEach(acc));
  const clat=(minLa+maxLa)/2, clng=(minLn+maxLn)/2, coslat=Math.cos(clat*Math.PI/180);
  const spanE=(maxLn-minLn)*111320*coslat, spanN=(maxLa-minLa)*110540, diag=Math.hypot(spanE,spanN);
  const range=Math.max(500, Math.min(12000, diag*2.2));
  m3dLatLng={lat:clat,lng:clng};
  const nOver=sobrepos.filter(s=>s.tipo!=='morto').length;
  const ea=document.getElementById('m3d-earth'), ma=document.getElementById('m3d-maps');
  if(ea) ea.href=`https://earth.google.com/web/@${clat},${clng},300a,${Math.round(range*1.4)}d,35y,0h,55t,0r`;
  if(ma) ma.href=`https://www.google.com/maps/@${clat},${clng},2000m/data=!3m1!1e3`;
  const ttl=document.getElementById('m3d-title'); if(ttl) ttl.textContent=`Visão 3D · ${propsUsar.length} imóvel(is)`+(nOver?(' · '+nOver+' sobrep.'):'');
  renderLegenda3D(legenda, temSobrep);
  const ft=document.getElementById('m3d-foot-txt'); if(ft) ft.innerHTML=(nOver?'<b style="color:#e2342f">■</b> vermelho = sobreposição · ':'')+(limite.rings.length?'<b style="color:#2563eb">▬</b> limite · ':'')+'rótulo = matrícula · 3D fotorrealista do Google. Arraste = girar · Shift+arraste = mover · roda = zoom.';
  document.getElementById('modal-3d').classList.add('show');
  const host=document.getElementById('m3d-host'), msg=document.getElementById('m3d-msg');
  fechar3DCena(); host.style.display='block'; host.innerHTML='';
  msg.style.display='flex'; msg.textContent='Carregando 3D do Google…';
  try{
    const lib = await google.maps.importLibrary('maps3d');
    const { Map3DElement, Polygon3DElement, Marker3DElement, Polyline3DElement, AltitudeMode, MapMode } = lib;
    if(!Map3DElement) throw new Error('Map3DElement indisponível');
    const map3d = new Map3DElement({ center:{lat:clat, lng:clng, altitude:0}, range, tilt:62, heading:0, mode:(MapMode?MapMode.HYBRID:'HYBRID') });
    map3d.style.width='100%'; map3d.style.height='100%';
    host.innerHTML=''; host.appendChild(map3d);
    m3dGoogleEl=map3d;
    instalarControles3D(map3d, host);
    let ok3d=false; const marcar=()=>{ ok3d=true; const mm=document.getElementById('m3d-msg'); if(mm) mm.style.display='none'; };
    ['gmp-centerchange','gmp-steadychange','gmp-click'].forEach(ev=>{ try{ map3d.addEventListener(ev, marcar, {once:true}); }catch(_){} });
    clearTimeout(window.__g3dT);
    window.__g3dT=setTimeout(()=>{ if(!ok3d) mostrarFallback3D('Não foi possível carregar o 3D fotorrealista do Google — verifique se a <b>Map Tiles API</b> está ativada e o faturamento habilitado no projeto da sua chave.'); }, 8000);
    const addPoly=(pts, cor, H, isOver)=>{
      try{
        const outer=pts.map(p=>({lat:p[0], lng:p[1], altitude:H}));
        const poly=new Polygon3DElement({
          outerCoordinates: outer, altitudeMode: AltitudeMode.RELATIVE_TO_GROUND, extruded: true,
          fillColor: hexA3D(cor, isOver?0.55:0.45), strokeColor: cor, strokeWidth: isOver?3:2, drawsOccludedSegments: true
        });
        map3d.append(poly);
      }catch(_){}
    };
    propsUsar.forEach(pr=> addPoly(pr.pts, pr.corUsar||pr.corMapa||'#5b96e6', 60, false));
    sobrepos.forEach(s=>{ const material=s.tipo!=='morto'; addPoly(s.ring, material?'#e2342f':(s.cor||'#9aa3ad'), material?95:45, material); });
    if(Marker3DElement){
      propsUsar.forEach(pr=>{ if(!pr.nome) return; let la=0,ln=0; pr.pts.forEach(p=>{la+=p[0];ln+=p[1];}); la/=pr.pts.length; ln/=pr.pts.length;
        try{ const mk=new Marker3DElement({ position:{lat:la, lng:ln, altitude:70}, altitudeMode:AltitudeMode.RELATIVE_TO_GROUND, label:pr.nome, extruded:true }); map3d.append(mk); }catch(_){}
      });
    }
    if(limite.rings && limite.rings.length && Polyline3DElement){
      limite.rings.forEach(r=>{ try{ const pl=new Polyline3DElement({ coordinates:r.map(p=>({lat:p[0],lng:p[1]})), altitudeMode:AltitudeMode.CLAMP_TO_GROUND, strokeColor:'#2563eb', strokeWidth:2 }); map3d.append(pl); }catch(_){} });
    }
  }catch(e){
    mostrarFallback3D('O 3D fotorrealista do Google não está disponível nesta conta (Map Tiles API não ativada ou versão indisponível).');
  }
}
function renderLegenda3D(legenda, temSobrep){
  const el=document.getElementById('m3d-legend'); if(!el) return;
  if(!legenda || !legenda.length){ el.style.display='none'; el.innerHTML=''; return; }
  el.innerHTML='<h4>Matrículas</h4>'+legenda.map(l=>`<div class="row"><span class="sw" style="background:${l.cor}"></span>${escapeHtml(l.nome)}</div>`).join('')
    +(temSobrep!==false?'<div class="row" style="margin-top:7px;border-top:1px solid #222c38;padding-top:6px"><span class="sw" style="background:#e2342f"></span>sobreposição</div>':'');
  el.style.display='block';
}
function montarCena3D(host, P){
  const THREE=window.THREE;
  const w=host.clientWidth||900, h=host.clientHeight||600, seg=P.seg, cov=P.cov;
  const scene=new THREE.Scene(); scene.background=new THREE.Color(0x0a0d11);
  const camera=new THREE.PerspectiveCamera(55, w/h, 1, cov*30);
  const renderer=new THREE.WebGLRenderer({antialias:true});
  renderer.setPixelRatio(Math.min(2,window.devicePixelRatio||1)); renderer.setSize(w,h);
  if(THREE.sRGBEncoding!==undefined) renderer.outputEncoding=THREE.sRGBEncoding;
  host.appendChild(renderer.domElement);
  let minE=Infinity,maxE=-Infinity; if(P.elev) P.elev.forEach(e=>{minE=Math.min(minE,e);maxE=Math.max(maxE,e);});
  const relevo=P.elev?(maxE-minE):0;
  const exag = relevo>0 ? Math.max(1.2, Math.min(6, 45/relevo)) : 1;  // relevos suaves ganham destaque
  const yAt=(k)=> P.elev ? (P.elev[k]-minE)*exag : 0;
  const pos=[], uv=[], idx=[];
  P.grid.forEach((g,k)=>{ pos.push(g.we, yAt(k), -g.wn); uv.push(g.u, g.v); });
  for(let j=0;j<seg;j++){ for(let i=0;i<seg;i++){ const a=j*(seg+1)+i,b=a+1,c=a+(seg+1),d=c+1; idx.push(a,c,b, b,c,d); } }
  const geo=new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.Float32BufferAttribute(pos,3));
  geo.setAttribute('uv', new THREE.Float32BufferAttribute(uv,2));
  geo.setIndex(idx); geo.computeVertexNormals();
  const tileUrl=window.location.pathname+`?m3d_tile=1&clat=${P.clat}&clng=${P.clng}&z=${P.z}`;
  const groundMat=new THREE.MeshStandardMaterial({color:0x3a4a3a, roughness:1, metalness:0, side:THREE.DoubleSide});
  const aplicarTex=(tex)=>{ if(THREE.sRGBEncoding!==undefined) tex.encoding=THREE.sRGBEncoding; if('colorSpace' in tex && THREE.SRGBColorSpace) tex.colorSpace=THREE.SRGBColorSpace; groundMat.map=tex; groundMat.color.set(0xffffff); groundMat.needsUpdate=true; };
  const falhaTex=()=>{ const f=document.getElementById('m3d-foot-txt'); if(f) f.innerHTML='Satélite indisponível (verifique a <b>Static Maps API</b>) — mostrando o terreno.'; };
  if(P.tilePlan){
    // Mosaico de tiles: carrega o mapa COMPLETO do município (N×N imagens) e recorta ao perímetro.
    const TP=P.tilePlan, N=TP.N, TS=1280, cvS=N*TS;
    const cv=document.createElement('canvas'); cv.width=cvS; cv.height=cvS; const cx=cv.getContext('2d');
    cx.fillStyle='#0a0d11'; cx.fillRect(0,0,cvS,cvS);
    let feitos=0; const total=N*N; let algum=false;
    const finalizar=()=>{
      if(!algum){ falhaTex(); return; }
      if(P.clip && P.clip.length){ try{
        cx.globalCompositeOperation='destination-in';
        const cpx=projPx3D(TP.clat,TP.clng,TP.z);
        cx.beginPath();
        P.clip.forEach(ring=>{ ring.forEach((pt,i)=>{ const q=projPx3D(pt[0],pt[1],TP.z); const X=cvS/2+(q.x-cpx.x)*2, Y=cvS/2+(q.y-cpx.y)*2; i?cx.lineTo(X,Y):cx.moveTo(X,Y); }); cx.closePath(); });
        cx.fillStyle='#fff'; cx.fill('evenodd');
        cx.globalCompositeOperation='source-over';
      }catch(e){} }
      const tex=new THREE.CanvasTexture(cv); aplicarTex(tex);
      groundMat.transparent=true; groundMat.alphaTest=0.04; groundMat.needsUpdate=true;
    };
    for(let j=0;j<N;j++){ for(let i=0;i<N;i++){
      const offE=(i-(N-1)/2)*TP.tm, offN=((N-1)/2-j)*TP.tm;
      const tlat=TP.clat+offN/110540, tlng=TP.clng+offE/(111320*TP.coslat);
      const img=new Image();
      img.onload=(function(ii,jj){ return ()=>{ try{ cx.drawImage(img, ii*TS, jj*TS, TS, TS); algum=true; }catch(_){} if(++feitos>=total) finalizar(); }; })(i,j);
      img.onerror=()=>{ if(++feitos>=total) finalizar(); };
      img.src=window.location.pathname+`?m3d_tile=1&clat=${tlat}&clng=${tlng}&z=${TP.z}`;
    }}
  } else if(P.clip && P.clip.length){
    // recorta a imagem de satélite ao perímetro do município (fora do limite fica transparente)
    const img=new Image();
    img.onload=()=>{ try{
      const S=1280, cv=document.createElement('canvas'); cv.width=S; cv.height=S; const cx=cv.getContext('2d');
      cx.drawImage(img,0,0,S,S);
      cx.globalCompositeOperation='destination-in';
      const cpx=projPx3D(P.clat,P.clng,P.z);
      cx.beginPath();
      P.clip.forEach(ring=>{ ring.forEach((pt,i)=>{ const q=projPx3D(pt[0],pt[1],P.z); const X=S/2+(q.x-cpx.x)*2, Y=S/2+(q.y-cpx.y)*2; i?cx.lineTo(X,Y):cx.moveTo(X,Y); }); cx.closePath(); });
      cx.fillStyle='#fff'; cx.fill('evenodd');
      const tex=new THREE.CanvasTexture(cv); aplicarTex(tex);
      groundMat.transparent=true; groundMat.alphaTest=0.04; groundMat.needsUpdate=true;
    }catch(e){ groundMat.color.set(0x3a4a3a); } };
    img.onerror=falhaTex; img.src=tileUrl;
  } else {
    new THREE.TextureLoader().load(tileUrl, aplicarTex, undefined, falhaTex);
  }
  const mesh=new THREE.Mesh(geo, groundMat); scene.add(mesh);
  const elevAt=(la,ln)=>{
    if(!P.elev) return 0;
    const u=(ln-P.clng)*(111320*P.coslat)/cov+0.5, v=(la-P.clat)*110540/cov+0.5;
    const fi=Math.max(0,Math.min(seg-1e-3,u*seg)), fj=Math.max(0,Math.min(seg-1e-3,v*seg));
    const i0=Math.floor(fi), j0=Math.floor(fj), tx=fi-i0, ty=fj-j0, e=(ii,jj)=>yAt(jj*(seg+1)+ii);
    return e(i0,j0)*(1-tx)*(1-ty)+e(i0+1,j0)*tx*(1-ty)+e(i0,j0+1)*(1-tx)*ty+e(i0+1,j0+1)*tx*ty;
  };
  const HBASE=Math.max(50, cov*0.05);        // altura de referência do prisma (profundidade visível)
  // rótulo (matrícula) como sprite que sempre encara a câmera
  function makeLabel3D(text, corHex){
    const fs=44, pad=18;
    const c=document.createElement('canvas'), g=c.getContext('2d');
    g.font=`bold ${fs}px Arial, sans-serif`;
    const tw=Math.ceil(g.measureText(text).width);
    c.width=tw+pad*2; c.height=fs+pad*2;
    const w=c.width, h=c.height, r=16;
    g.font=`bold ${fs}px Arial, sans-serif`;
    g.beginPath(); g.moveTo(r,0); g.arcTo(w,0,w,h,r); g.arcTo(w,h,0,h,r); g.arcTo(0,h,0,0,r); g.arcTo(0,0,w,0,r); g.closePath();
    g.fillStyle='rgba(9,12,16,0.86)'; g.fill(); g.lineWidth=4; g.strokeStyle=corHex||'#ffffff'; g.stroke();
    g.fillStyle='#fff'; g.textAlign='center'; g.textBaseline='middle'; g.fillText(text, w/2, h/2+2);
    const tex=new THREE.CanvasTexture(c); tex.minFilter=THREE.LinearFilter;
    const spr=new THREE.Sprite(new THREE.SpriteMaterial({map:tex, transparent:true, depthTest:false, depthWrite:false}));
    spr.userData.aspect = w/h; spr.renderOrder=6;      // escala é ajustada por quadro (tamanho fixo na tela)
    return spr;
  }
  const labels3D=[];
  // desenha um imóvel como PRISMA 3D (volume/profundidade)
  function addPrisma(pts, corHex, opac, hFactor, isOver, rotulo){
    if(!pts || pts.length<3) return;
    const col=new THREE.Color(corHex);
    const ring=pts.map(p=>{ const x=(p[1]-P.clng)*(111320*P.coslat), z=-(p[0]-P.clat)*110540; return {x, z, yb:elevAt(p[0],p[1])}; });
    let topBase=-Infinity; ring.forEach(r=>{ if(r.yb>topBase) topBase=r.yb; });
    const topY=topBase + HBASE*hFactor;
    const wallPos=[];
    for(let i=0;i<ring.length;i++){ const a=ring[i], b=ring[(i+1)%ring.length];
      wallPos.push(a.x,a.yb,a.z, b.x,b.yb,b.z, b.x,topY,b.z, a.x,a.yb,a.z, b.x,topY,b.z, a.x,topY,a.z); }
    const wgeo=new THREE.BufferGeometry(); wgeo.setAttribute('position',new THREE.Float32BufferAttribute(wallPos,3)); wgeo.computeVertexNormals();
    const wmesh=new THREE.Mesh(wgeo, new THREE.MeshStandardMaterial({color:col, roughness:.75, metalness:.05, transparent:true, opacity:opac, side:THREE.DoubleSide, depthWrite:false}));
    if(isOver) wmesh.renderOrder=2; scene.add(wmesh);
    try{
      const shape=new THREE.Shape(); ring.forEach((r,i)=>{ const y=-r.z; i?shape.lineTo(r.x,y):shape.moveTo(r.x,y); });
      const capGeo=new THREE.ShapeGeometry(shape); capGeo.rotateX(-Math.PI/2); capGeo.translate(0, topY, 0);
      const cmesh=new THREE.Mesh(capGeo, new THREE.MeshStandardMaterial({color:col, roughness:.6, metalness:.05, transparent:true, opacity:Math.min(.65,opac+.08), side:THREE.DoubleSide, depthWrite:false}));
      if(isOver) cmesh.renderOrder=2; scene.add(cmesh);
    }catch(_){}
    const topLine=ring.map(r=>new THREE.Vector3(r.x, topY, r.z)); topLine.push(topLine[0].clone());
    const lm=new THREE.Line(new THREE.BufferGeometry().setFromPoints(topLine), new THREE.LineBasicMaterial({color:col})); if(isOver) lm.renderOrder=3; scene.add(lm);
    const baseLine=ring.map(r=>new THREE.Vector3(r.x, r.yb+2, r.z)); baseLine.push(baseLine[0].clone());
    scene.add(new THREE.Line(new THREE.BufferGeometry().setFromPoints(baseLine), new THREE.LineBasicMaterial({color:col, transparent:true, opacity:.55})));
    if(rotulo){
      let cx=0,cz=0; ring.forEach(r=>{cx+=r.x;cz+=r.z;}); cx/=ring.length; cz/=ring.length;
      const spr=makeLabel3D(rotulo, corHex); spr.position.set(cx, topY + cov*0.02, cz); scene.add(spr); labels3D.push(spr);
    }
  }
  const corAtivo=(typeof imovelAtivoId!=='undefined')?imovelAtivoId:null;
  (P.props||[]).forEach(pr=> addPrisma(pr.pts, pr.corUsar||pr.corMapa||'#5b96e6', (pr.id&&pr.id===corAtivo)?0.5:0.34, 1.0, false, pr.nome));   // imóveis (cores distintas p/ sobreposição) + rótulo
  (P.sobrepos||[]).forEach(s=>{
    const material = s.tipo!=='morto';
    addPrisma(s.ring, s.cor||(material?'#e2342f':'#9aa3ad'), material?0.62:0.4, material?1.12:1.0, material);         // sobreposições (mesmas regras do mapa)
  });
  // limite do município (mesma cor azul do mapa), rente ao terreno
  (P.limiteRings||[]).forEach(ring=>{
    const v=ring.map(p=>{ const x=(p[1]-P.clng)*(111320*P.coslat), z=-(p[0]-P.clat)*110540; return new THREE.Vector3(x, elevAt(p[0],p[1])+6, z); });
    if(v.length>=2){ const lm=new THREE.Line(new THREE.BufferGeometry().setFromPoints(v), new THREE.LineBasicMaterial({color:0x2563eb})); lm.renderOrder=1; scene.add(lm); }
  });
  scene.add(new THREE.AmbientLight(0xffffff, 0.55));
  scene.add(new THREE.HemisphereLight(0xffffff,0x223344,1.15));
  const dir=new THREE.DirectionalLight(0xffffff,0.6); dir.position.set(cov*0.3, cov*0.7, cov*0.2); scene.add(dir);
  const target=new THREE.Vector3(0, relevo?(maxE-minE)*exag*0.3:0, 0);
  let radius=cov*0.85, azim=0, polar=0.95;
  function updCam(){ const sp=Math.sin(polar),cp=Math.cos(polar); camera.position.set(target.x+radius*sp*Math.sin(azim), target.y+radius*cp, target.z+radius*sp*Math.cos(azim)); camera.lookAt(target); }
  updCam();
  const el=renderer.domElement; el.style.cursor='grab'; el.oncontextmenu=e=>e.preventDefault();
  function panXZ(dx,dy){
    const k=(radius*1.04)/(host.clientHeight||600);           // ~metros por pixel na altura do alvo
    const fx=-Math.sin(azim), fz=-Math.cos(azim);             // "frente" no chão (rumo ao alvo)
    const rx=Math.cos(azim),  rz=-Math.sin(azim);             // "direita"
    target.x += (dx*rx + dy*fx)*k;  target.z += (dx*rz + dy*fz)*k;  updCam();   // "pegar e puxar" o mapa
  }
  let modo=null, lx=0, ly=0, pinch=0, mx=0, my=0;
  const md=e=>{ modo=(e.button===2||e.shiftKey)?'mover':'girar'; lx=e.clientX; ly=e.clientY; el.style.cursor='grabbing'; };
  const mm=e=>{ if(!modo) return; const dx=e.clientX-lx, dy=e.clientY-ly; lx=e.clientX; ly=e.clientY;
    if(modo==='mover') panXZ(dx,dy); else { azim-=dx*0.006; polar=Math.max(0.12,Math.min(1.45,polar-dy*0.006)); updCam(); } };
  const mu=()=>{ modo=null; el.style.cursor='grab'; };
  el.addEventListener('mousedown', md); window.addEventListener('mousemove', mm); window.addEventListener('mouseup', mu);
  const wh=e=>{ e.preventDefault(); radius=Math.max(cov*0.05,Math.min(cov*4,radius*(e.deltaY>0?1.1:0.9))); updCam(); };
  el.addEventListener('wheel', wh, {passive:false});
  const ts=e=>{ if(e.touches.length===1){ modo='girar'; lx=e.touches[0].clientX; ly=e.touches[0].clientY; }
    else if(e.touches.length===2){ modo='multi'; pinch=Math.hypot(e.touches[0].clientX-e.touches[1].clientX,e.touches[0].clientY-e.touches[1].clientY); mx=(e.touches[0].clientX+e.touches[1].clientX)/2; my=(e.touches[0].clientY+e.touches[1].clientY)/2; } };
  const tmv=e=>{ if(modo==='girar'&&e.touches.length===1){ const dx=e.touches[0].clientX-lx, dy=e.touches[0].clientY-ly; lx=e.touches[0].clientX; ly=e.touches[0].clientY; azim-=dx*0.006; polar=Math.max(0.12,Math.min(1.45,polar-dy*0.006)); updCam(); }
    else if(e.touches.length===2){ const d=Math.hypot(e.touches[0].clientX-e.touches[1].clientX,e.touches[0].clientY-e.touches[1].clientY); if(pinch) radius=Math.max(cov*0.05,Math.min(cov*4,radius*(pinch/d))); pinch=d; const cxm=(e.touches[0].clientX+e.touches[1].clientX)/2, cym=(e.touches[0].clientY+e.touches[1].clientY)/2; panXZ(cxm-mx, cym-my); mx=cxm; my=cym; }
    e.preventDefault(); };
  const te=()=>{ modo=null; };
  el.addEventListener('touchstart', ts, {passive:false}); el.addEventListener('touchmove', tmv, {passive:false}); el.addEventListener('touchend', te);
  const _tanHalf=Math.tan(55*Math.PI/360), _fracLbl=0.038;   // altura do rótulo ~3,8% da tela (fixo)
  let raf; (function loop(){ raf=requestAnimationFrame(loop);
    for(let i=0;i<labels3D.length;i++){ const s=labels3D[i], d=camera.position.distanceTo(s.position), H=_fracLbl*2*d*_tanHalf; s.scale.set(H*(s.userData.aspect||3), H, 1); }
    renderer.render(scene,camera);
  })();
  const onResize=()=>{ const W=host.clientWidth,H=host.clientHeight; if(W&&H){camera.aspect=W/H;camera.updateProjectionMatrix();renderer.setSize(W,H);} };
  window.addEventListener('resize',onResize); setTimeout(onResize,60);
  three3D={ rotar:(d)=>{ azim+=d; updCam(); }, inclinar:(d)=>{ polar=Math.max(0.12,Math.min(1.45,polar+d)); updCam(); }, zoom:(f)=>{ radius=Math.max(cov*0.05,Math.min(cov*4,radius*f)); updCam(); },
    dispose(){ try{cancelAnimationFrame(raf);}catch(_){} window.removeEventListener('resize',onResize); window.removeEventListener('mousemove',mm); window.removeEventListener('mouseup',mu); try{renderer.dispose();}catch(_){} try{host.innerHTML='';}catch(_){} } };
}
function fechar3DCena(){ if(three3D){ try{three3D.dispose();}catch(_){}; three3D=null; } }
function abrirEarth(lat,lng){ window.open(`https://earth.google.com/web/@${lat},${lng},300a,900d,35y,0h,60t,0r`, '_blank', 'noopener'); }
function fechar3D(){ clearTimeout(window.__g3dT); if(_ctl3D){ try{ window.removeEventListener('pointermove',_ctl3D.move,true); window.removeEventListener('pointerup',_ctl3D.up,true); }catch(_){}; _ctl3D=null; } fechar3DCena(); m3dGoogleEl=null; document.getElementById('modal-3d').classList.remove('show'); const h=document.getElementById('m3d-host'); if(h) h.innerHTML=''; const lg=document.getElementById('m3d-legend'); if(lg){ lg.style.display='none'; lg.innerHTML=''; } }
function wire3D(){
  const on=(id,ev,fn)=>{ const el=document.getElementById(id); if(el && !el.dataset.init){ el.dataset.init='1'; el.addEventListener(ev, fn); } };
  on('btn-3d','click', abrir3D);
  on('btn-3d-tilt','click', ()=>set3DTilt(!tiltOn));
  on('btn-3d-left','click', ()=>rot3D(-30));
  on('btn-3d-right','click', ()=>rot3D(30));
  on('m3d-close','click', fechar3D);
}

console.info('%cVertex — build 2026-07-03u-valida-doc-salvar','color:#0ea5e9;font-weight:bold');

function centroidOf(pts){
  let la=0,ln=0; pts.forEach(p=>{ la+=p[0]; ln+=p[1]; });
  return {lat:la/pts.length, lng:ln/pts.length};
}
function addLabel(pos, text, onClick, cls){
  if(!text) return null;
  const ov = new LabelOverlay(new google.maps.LatLng(pos.lat, pos.lng), text, cls||'', onClick||null);
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

/* Modo "rótulos ocultos": esconde os chips de matrícula no mapa; ao pousar o mouse
   sobre o imóvel, mostra a matrícula imediatamente. */
let rotulosOcultos = true;   // padrão: rótulos ocultos (usuário exibe quando quiser)
function mostrarHoverTipImediato(pos, text){
  if(!text) return;
  ocultarHoverTip();
  hoverTip = new LabelOverlay(new google.maps.LatLng(pos.lat, pos.lng), text, 'hover');
}
function hoverImovel(it, centro){
  if(rotulosOcultos){
    const mat=(it.numero_matricula||'').trim();
    const txt = mat ? rotuloMat(mat) : (it.identificador||'');
    if(txt) mostrarHoverTipImediato(centro, txt);
  } else {
    agendarHoverTip(centro, it.identificador);
  }
}
function aplicarRotulosVisibilidade(){
  // Na visão geral, o rótulo só aparece se o polígono do imóvel estiver visível
  // (respeita o filtro de categoria/lista e o curinga "506;*").
  if(modo==='overview' && Array.isArray(itensOverview) && itensOverview.length){
    itensOverview.forEach(it=>{
      if(!it._label || !it._label.setMap) return;
      const polyVisivel = !!(it._poly && it._poly.getMap && it._poly.getMap());
      it._label.setMap((!rotulosOcultos && polyVisivel) ? map : null);
    });
    return;
  }
  labelOverlays.forEach(l=>{ if(l && l.setMap) l.setMap(rotulosOcultos ? null : map); });
}
function toggleRotulos(){
  rotulosOcultos = !rotulosOcultos;
  aplicarRotulosVisibilidade();
  ocultarHoverTip();
  const b=document.getElementById('btn-rotulos');
  if(b){ const sp=b.querySelector('span'); const tx = rotulosOcultos ? 'Mostrar rótulos' : 'Ocultar rótulos'; if(sp) sp.textContent = tx; else b.textContent = tx; b.classList.toggle('active', rotulosOcultos); b.title = rotulosOcultos ? 'Rótulos ocultos — passe o mouse sobre o imóvel para ver a matrícula' : 'Ocultar os números das matrículas no mapa'; }
}

function fmt(n,d){ return Number(n).toLocaleString('pt-BR',{minimumFractionDigits:d,maximumFractionDigits:d}); }
// Área por tipo: rural (ou indefinido) em hectares; urbano em m² (fica estranho lote pequeno em ha).
function fmtArea(ha, tipo){
  ha = Number(ha)||0;
  if(tipo==='urbano'){ return { val: fmt(ha*10000, 2), un: 'm²', k: 'Área (m²)' }; }
  return { val: fmt(ha, 4), un: 'ha', k: 'Área (UTM 23S)' };
}
// Perímetro em metros quando < 1 km; em km acima disso.
function fmtPerimetro(m){
  m = Number(m)||0;
  return (m >= 1000) ? { val: fmt(m/1000, 3), un: 'km' } : { val: fmt(m, 1), un: 'm' };
}
let edItemAtual = null;
function edRenderStats(it){
  if(!it) return;
  const tipo = (document.getElementById('ed-tipo')||{}).value || it.tipo_imovel || '';
  const g = (id)=>document.getElementById(id);
  if(g('eds-vtx')) g('eds-vtx').textContent = (it.num_vertices!=null && it.num_vertices!=='') ? it.num_vertices : '—';
  if(g('eds-area')){
    if(it.area_ha!=null && it.area_ha!==''){ const a=fmtArea(it.area_ha,tipo); g('eds-area').innerHTML = a.val+' <span class="u">'+a.un+'</span>'; if(g('eds-area-k')) g('eds-area-k').textContent=a.k; }
    else { g('eds-area').textContent='—'; }
  }
  if(g('eds-per')){
    if(it.perimetro_m!=null && it.perimetro_m!==''){ const p=fmtPerimetro(it.perimetro_m); g('eds-per').innerHTML = p.val+' <span class="u">'+p.un+'</span>'; }
    else { g('eds-per').textContent='—'; }
  }
  if(g('eds-cen')){
    g('eds-cen').textContent = (it.centro_lat!=null && it.centro_lng!=null && it.centro_lat!=='' && it.centro_lng!=='')
      ? (Number(it.centro_lat).toFixed(5)+', '+Number(it.centro_lng).toFixed(5)) : '—';
  }
}
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
    confirmButtonColor:'#1571B0', cancelButtonColor:'#6b7785', reverseButtons:true
  }, swalTema()));
  return r.isConfirmed;
}
function setStatus(type,msg){
  const el=document.getElementById('status'); if(el){ el.className='status '+type; el.innerHTML=msg; }
  if(type==='err') swalToast('error', stripTags(msg));
  else if(type==='ok') swalToast('success', stripTags(msg));
}

/* ============ PAINEL DE FOCO NO IMÓVEL (dados do memorial) ============ */
function wgs84ToUTM(lat, lon){
  const a=6378137.0, f=1/298.257223563;
  const e2=f*(2-f), ep2=e2/(1-e2), k0=0.9996;
  const zone=Math.floor((lon+180)/6)+1;
  const lon0=(zone*6-183)*Math.PI/180;
  const phi=lat*Math.PI/180, lam=lon*Math.PI/180;
  const N=a/Math.sqrt(1-e2*Math.sin(phi)**2);
  const T=Math.tan(phi)**2, C=ep2*Math.cos(phi)**2, A=Math.cos(phi)*(lam-lon0);
  const M=a*((1-e2/4-3*e2*e2/64-5*e2**3/256)*phi
    -(3*e2/8+3*e2*e2/32+45*e2**3/1024)*Math.sin(2*phi)
    +(15*e2*e2/256+45*e2**3/1024)*Math.sin(4*phi)
    -(35*e2**3/3072)*Math.sin(6*phi));
  const E=k0*N*(A+(1-T+C)*A**3/6+(5-18*T+T*T+72*C-58*ep2)*A**5/120)+500000;
  let No=k0*(M+N*Math.tan(phi)*(A*A/2+(5-T+9*C+4*C*C)*A**4/24+(61-58*T+T*T+600*C-330*ep2)*A**6/720));
  if(lat<0) No+=10000000;
  return {E:E, N:No, zone:zone};
}
function focoFmtBR(n,d){ return Number(n).toLocaleString('pt-BR',{minimumFractionDigits:d,maximumFractionDigits:d}); }
function focoIncParse(raw){ try{ if(Array.isArray(raw)) return raw; if(typeof raw==='string'&&raw.trim()) return JSON.parse(raw); }catch(_){} return []; }
function esconderPainelFoco(){
  const p=document.getElementById('foco-panel'); if(p) p.classList.remove('show');
  const r=document.getElementById('foco-reopen'); if(r) r.classList.remove('show');
}
function focoRenderInc(list){
  const sec=document.getElementById('foco-inc-sec'), box=document.getElementById('foco-inc');
  if(!sec||!box) return;
  if(!list||!list.length){ sec.style.display='none'; box.innerHTML=''; return; }
  box.innerHTML=list.map(it=>{ const sev=(it&&it.sev==='erro')?'erro':'alerta';
    return '<div class="foco-inc-item '+sev+'">'+escapeHtml((it&&it.msg)||'')+'</div>'; }).join('');
  sec.style.display='';
}
function mostrarPainelFoco(reg, geo){
  const p=document.getElementById('foco-panel'); if(!p||!geo) return;
  reg=reg||{};
  const num=(reg.numero_matricula||'').toString().trim();
  document.getElementById('foco-kick').textContent = num ? ('MATRÍCULA '+num) : 'IMÓVEL';
  document.getElementById('foco-title').textContent = reg.identificador || (num?('Matrícula '+num):'Imóvel');
  document.getElementById('foco-sub').textContent = [reg.municipio,reg.uf].filter(Boolean).join(' · ');
  const areaHa=Number(geo.area_ha)||0, perim=Number(geo.perimetro_m)||0;
  document.getElementById('foco-area').textContent = focoFmtBR(areaHa,2)+' ha';
  document.getElementById('foco-perim').textContent = focoFmtBR(perim,2)+' m';
  const m2=areaHa*10000;
  document.getElementById('foco-area2').textContent = focoFmtBR(m2,2)+' m²  ·  '+focoFmtBR(m2/48400,2)+' alq. (48.400 m²)';
  const body=document.getElementById('foco-vtx-body'); body.innerHTML='';
  (geo.pts||[]).forEach((pt,i)=>{ const u=wgs84ToUTM(pt[0],pt[1]); const tr=document.createElement('tr');
    tr.innerHTML='<td>V-'+(i+1)+'</td><td>'+Math.round(u.E).toLocaleString('pt-BR')+'</td><td>'+Math.round(u.N).toLocaleString('pt-BR')+'</td>';
    body.appendChild(tr); });
  document.getElementById('foco-vtx-sec').style.display=(geo.pts&&geo.pts.length)?'':'none';
  document.getElementById('foco-conf-sec').style.display='none';
  focoRenderInc(focoIncParse(reg.inconsistencias));
  document.getElementById('foco-note').innerHTML='';
  p.classList.add('show');
  const rb=document.getElementById('foco-reopen'); if(rb) rb.classList.remove('show');
  const memo=(reg.memorial_descritivo||'').trim();
  if(memo && (reg.origem||'memorial')!=='kml'){
    post({acao:'analisar_vertex', memorial:memo}).then(d=>focoEnriquecer(d)).catch(()=>{});
  }
}
function focoEnriquecer(d){
  if(!d||!d.ok) return;
  const sub=document.getElementById('foco-sub');
  const extra=[d.datum,('UTM '+d.zona+'S'),(d.mc?('MC '+d.mc):'')].filter(Boolean).join(' · ');
  if(extra) sub.textContent=[sub.textContent, extra].filter(Boolean).join('   ·   ');
  document.getElementById('foco-area').textContent=focoFmtBR(d.area_ha,2)+' ha';
  document.getElementById('foco-perim').textContent=focoFmtBR(d.perimetro_m,2)+' m';
  document.getElementById('foco-area2').textContent=focoFmtBR(d.area_m2,2)+' m²  ·  '+focoFmtBR(d.area_m2/48400,2)+' alq. (48.400 m²)';
  const body=document.getElementById('foco-vtx-body'); body.innerHTML='';
  (d.vertices||[]).forEach(v=>{ const tr=document.createElement('tr'); if(v.suspeito) tr.className='susp';
    tr.innerHTML='<td>'+escapeHtml(v.rot)+'</td><td>'+Math.round(v.E).toLocaleString('pt-BR')+'</td><td>'+Math.round(v.N).toLocaleString('pt-BR')+'</td>';
    body.appendChild(tr); });
  document.getElementById('foco-vtx-sec').style.display='';
  const confSec=document.getElementById('foco-conf-sec'), conf=document.getElementById('foco-conf');
  if(d.confrontantes&&d.confrontantes.length){
    conf.innerHTML=d.confrontantes.map(c=>'<div class="foco-conf-item"><i>—</i><span>'+escapeHtml(c)+'</span></div>').join('');
    confSec.style.display='';
  } else confSec.style.display='none';
  const notas=[];
  (d.vertices||[]).forEach(v=>{ if(v.typo) notas.push(v.rot+' corrigido de '+v.typo+'.'); });
  const suspNoTypo=(d.suspeitos||[]).filter(r=>{const vv=(d.vertices||[]).find(x=>x.rot===r); return vv&&!vv.typo;});
  if(suspNoTypo.length) notas.push('Vértice(s) '+suspNoTypo.join(', ')+' divergem do azimute/distância do memorial — confira a coordenada.');
  const note=document.getElementById('foco-note');
  note.innerHTML = notas.length ? ('<b>Ajustes:</b> '+notas.map(escapeHtml).join(' ')) : '';
}
function reabrirFoco(){ const p=document.getElementById('foco-panel'); if(p)p.classList.add('show'); const r=document.getElementById('foco-reopen'); if(r)r.classList.remove('show'); }
(function(){
  const c=document.getElementById('foco-close');
  if(c) c.onclick=function(){ const p=document.getElementById('foco-panel'); if(p)p.classList.remove('show'); const r=document.getElementById('foco-reopen'); if(r)r.classList.add('show'); };
})();

function limparSingle(){
  esconderPainelFoco();
  if(polygon){ polygon.setMap(null); polygon=null; }
  vertexMarkers.forEach(m=>m.setMap(null)); vertexMarkers=[];
  limparLabels();
  imovelEditandoId=null;
  const cb=document.getElementById('cor-box'); if(cb) cb.style.display='none';
  if(typeof onrSetAtivo==='function'){ onrSetAtivo(null); document.querySelectorAll('[data-onr]').forEach(el=>{ const col=el.getAttribute('data-onr'); el.value = (typeof ONR_PADRAO!=='undefined' && ONR_PADRAO[col]!==undefined) ? ONR_PADRAO[col] : ''; }); onrPreencherGeometria({area_ha:null,perimetro_m:null}); }
  const ei=document.getElementById('enc-info'); if(ei) ei.style.display='none';
}
function limparOverview(){
  if(ovLegendEl){ ovLegendEl.style.display='none'; ovLegendEl.innerHTML=''; }
  (itensOverview||[]).forEach(it=>{ it._corOrig=null; });
  overviewPolys.forEach(p=>p.setMap(null)); overviewPolys=[];
  overlapPolys.forEach(p=>p.setMap(null)); overlapPolys=[];
  limparLabels();
  selecionados.clear();
  const sb=document.getElementById('sel-bar'); if(sb) sb.classList.remove('show');
  const rp=document.getElementById('ov-reopen'); if(rp) rp.classList.remove('show');
}

let escopoBase='matriculas'; // 'matriculas' (só matrículas) | 'projetos' (matrículas + projetos)
async function post(params){
  try{
    const ac = params && params.acao;
    // Listagens respeitam a base atual
    if((ac==='listar' || ac==='listar_geo') && params.escopo===undefined){ params.escopo = escopoBase; }
    // Na base de PROJETOS, toda importação/gravação entra como projeto (não exige matrícula)
    if(ac==='salvar' && escopoBase==='projetos' && params.is_projeto===undefined){
      params.is_projeto = 1;
    }
  }catch(_){}
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
    paths:path, strokeColor:'#1D4ED8', strokeOpacity:.95, strokeWeight:2,
    fillColor:'#1D4ED8', fillOpacity:.22, map:map
  });
  geo.pts.forEach((p,i)=>{
    vertexMarkers.push(new google.maps.Marker({
      position:{lat:p[0],lng:p[1]}, map:map,
      icon:{path:google.maps.SymbolPath.CIRCLE, scale:4, fillColor:'#0e1217',
            fillOpacity:1, strokeColor:'#0D9488', strokeWeight:2},
      title:'V'+(i+1)
    }));
  });
  const b = new google.maps.LatLngBounds();
  path.forEach(pt=>b.extend(pt));
  // Vai para a seção do MAPA e só enquadra quando o mapa tiver tamanho real —
  // sem isso, vindo de outra aba, o fitBounds calcula sobre 0×0 e o imóvel "não aparece" até dar F5.
  if(typeof vxEnsureMapVisibleThen==='function'){
    vxEnsureMapVisibleThen(()=>{ if(!b.isEmpty()) map.fitBounds(b, 40); });
  } else {
    try{ google.maps.event.trigger(map,'resize'); }catch(_){}
    map.fitBounds(b, 40);
  }

  // rótulo com nome/matrícula no centro do imóvel
  addLabel({lat:geo.centro_lat, lng:geo.centro_lng}, nome);

  // (stats do imóvel — vértices/área/perímetro/centro — agora ficam no modal de edição, não no painel)

  const ro=document.getElementById('readout'); ro.style.display='block';
  document.getElementById('ro-name').textContent = nome || 'Imóvel';
  document.getElementById('ro-area').textContent = fmt(geo.area_ha,2);

  verificarPertencimento(geo); // confere se o imóvel está dentro do limite municipal carregado
}

/* ===================== MAPEAR (memorial) ===================== */
document.getElementById('btn-map').onclick = async ()=>{
  const memorial = document.getElementById('memorial').value;
  if(!memorial.trim()){ setStatus('err','Cole um memorial descritivo.'); return; }
  origemAtual='memorial'; resetKmlZone(); geoOverrideWgs84=null; ocultarRevisar();
  setStatus('warn','Processando…');
  // 1º detecta a situação de coordenadas inconsistentes — se houver, JÁ oferece a escolha
  const a = await post({acao:'analisar_coords', memorial});
  if(a && a.ok && laudoTemDiscrepancia(a)){
    const nome=document.getElementById('identificador').value.trim();
    aplicarEstadoLaudo(a);
    const geo = await mostrarLaudoCoords(a, nome);
    if(!geo){ // fechou sem escolher → aplica o recomendado e mantém o botão de revisão
      const rec = (a.recomendacao==='corrigido' && a.corrigido) ? a.corrigido : a.transcrito;
      origemAtual='memorial'; lastGeo=rec; geoOverrideWgs84=rec.coordenadas_wgs84||null;
      desenhar(rec, nome);
      document.getElementById('btn-save').disabled=false;
      setStatus('warn', (a.recomendacao==='corrigido'?'Traçado correto (recomendado)':'Coordenadas transcritas')+' aplicado — use "Revisar traçado" para trocar antes de gravar.');
    }
    return;
  }
  // 2º memorial normal (GMS) ou sem discrepância — fluxo direto
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

/* ===================== LAUDO DE COORDENADAS (transcrito x corrigido) ===================== */
function _fmtAzDec(a){ const g=Math.floor(a), m=Math.floor((a-g)*60), s=Math.round(((a-g)*60-m)*60); return `${g}°${String(m).padStart(2,'0')}'${String(s).padStart(2,'0')}"`; }
function _ha(x){ return x==null?'—':Number(x).toLocaleString('pt-BR',{minimumFractionDigits:4,maximumFractionDigits:4})+' ha'; }
/* Prévia visual (SVG) comparando o traçado correto (verde) e o transcrito (vermelho tracejado). */
function previewTracadosSVG(a){
  const co=a.corrigido, tr=a.transcrito;
  const all=[]; if(co&&co.pts) co.pts.forEach(p=>all.push(p)); if(tr&&tr.pts) tr.pts.forEach(p=>all.push(p));
  if(all.length<3) return '';
  let minLat=Infinity,maxLat=-Infinity,minLng=Infinity,maxLng=-Infinity;
  all.forEach(p=>{minLat=Math.min(minLat,p[0]);maxLat=Math.max(maxLat,p[0]);minLng=Math.min(minLng,p[1]);maxLng=Math.max(maxLng,p[1]);});
  const W=360,H=300,pad=30;
  const dLat=(maxLat-minLat)||1e-6, dLng=(maxLng-minLng)||1e-6;
  const s=Math.min((W-2*pad)/dLng,(H-2*pad)/dLat);
  const offX=(W-dLng*s)/2, offY=(H-dLat*s)/2;
  const X=lng=>(offX+(lng-minLng)*s);
  const Y=lat=>(H-(offY+(lat-minLat)*s)); // inverte Y (norte p/ cima)
  const camada=(geo,color,fill,dash)=>{
    if(!geo||!geo.pts) return '';
    const pp=geo.pts.map(p=>X(p[1]).toFixed(1)+','+Y(p[0]).toFixed(1)).join(' ');
    let g=`<polygon points="${pp}" fill="${fill}" stroke="${color}" stroke-width="2" ${dash?'stroke-dasharray="5 4"':''} stroke-linejoin="round"/>`;
    geo.pts.forEach(p=>{ g+=`<circle cx="${X(p[1]).toFixed(1)}" cy="${Y(p[0]).toFixed(1)}" r="2.6" fill="${color}"/>`; });
    return g;
  };
  const labGeo=co||tr, rot=(labGeo&&labGeo.rotulos)||[];
  let labels='';
  if(labGeo&&labGeo.pts) labGeo.pts.forEach((p,i)=>{ labels+=`<text x="${(X(p[1])+4).toFixed(1)}" y="${(Y(p[0])-4).toFixed(1)}" font-size="9" fill="#cbd5e1" font-family="monospace">${rot[i]||('V'+(i+1))}</text>`; });
  const svg=`<svg viewBox="0 0 ${W} ${H}" width="100%" style="max-width:${W}px;display:block;margin:0 auto;background:#0b0f14;border:1px solid #222c38;border-radius:9px">
    <g id="lay-transcrito">${tr?camada(tr,'#ef4444','rgba(239,68,68,.10)',true):''}</g>
    <g id="lay-correto">${co?camada(co,'#10b981','rgba(16,185,129,.16)',false):''}${labels}</g>
  </svg>`;
  const toggles=`<div style="display:flex;gap:16px;justify-content:center;font-size:12px;margin:7px 0 2px;color:#cbd5e1">
    <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" checked onchange="var e=document.getElementById('lay-correto');if(e)e.style.display=this.checked?'':'none'"><span style="display:inline-block;width:16px;border-top:3px solid #10b981"></span> Traçado correto</label>
    <label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" ${tr?'':'disabled'} onchange="var e=document.getElementById('lay-transcrito');if(e)e.style.display=this.checked?'':'none'"><span style="display:inline-block;width:16px;border-top:3px dashed #ef4444"></span> Transcrito (c/ erros)</label>
  </div>`;
  // transcrito começa oculto
  return `<div style="margin-bottom:10px">${svg.replace('<g id="lay-transcrito">','<g id="lay-transcrito" style="display:none">')}${toggles}</div>`;
}

function renderLaudoHTML(a){
  const dark=document.body.classList.contains('dark-mode');
  const bgC=dark?'#0f151c':'#f4f6f9', bd=dark?'#222c38':'#e2e8f0', mut='#8a96a3';
  const card=(t,v,sub,cor)=>`<div style="flex:1;min-width:130px;background:${bgC};border:1px solid ${bd};border-left:3px solid ${cor};border-radius:9px;padding:9px 11px"><div style="font-size:11px;color:${mut}">${t}</div><div style="font-size:16px;font-weight:700">${v}</div>${sub?`<div style="font-size:11px;color:${mut}">${sub}</div>`:''}</div>`;
  const tr=a.transcrito, co=a.corrigido;
  let cards='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">';
  cards+=card('Área declarada', _ha(a.area_declarada_ha),'no documento','#3b82f6');
  cards+=card('Traçado correto', co?_ha(co.area_ha_utm):'—', co?('fechamento '+Number(co.fechamento_m).toLocaleString('pt-BR',{minimumFractionDigits:2})+' m'):'sem azimutes','#10b981');
  cards+=card('Transcrito (erros)', _ha(tr.area_ha_utm),'coords. do documento','#ef4444');
  cards+='</div>';
  let typos='';
  if(a.typos && a.typos.length) typos='<div style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.4);border-radius:8px;padding:8px 10px;margin-bottom:10px;font-size:12px">⚠ '+a.typos.map(escapeHtml).join('<br>⚠ ')+'</div>';
  const rows=a.legs.map(l=>{
    const sc=l.suspeito?(dark?'#2a1416':'#fff1f1'):'transparent';
    const dd=l.dist_decl==null?'—':Number(l.dist_decl).toLocaleString('pt-BR',{minimumFractionDigits:2});
    const dc=Number(l.dist_calc).toLocaleString('pt-BR',{minimumFractionDigits:2});
    const ad=l.az_decl==null?'—':_fmtAzDec(l.az_decl);
    const ac=_fmtAzDec(l.az_calc);
    return `<tr style="background:${sc}"><td style="padding:4px 7px;white-space:nowrap">${l.de}→${l.para}</td><td style="text-align:right;padding:4px 7px">${dd}</td><td style="text-align:right;padding:4px 7px">${dc}</td><td style="text-align:right;padding:4px 7px;white-space:nowrap">${ad}</td><td style="text-align:right;padding:4px 7px;white-space:nowrap">${ac}</td><td style="text-align:center;padding:4px 7px">${l.suspeito?'<span style="color:#ef4444;font-weight:700">≠</span>':'<span style="color:#10b981">✓</span>'}</td></tr>`;
  }).join('');
  const tabela=`<div style="max-height:230px;overflow:auto;border:1px solid ${bd};border-radius:9px"><table style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr style="position:sticky;top:0;background:${dark?'#161c24':'#eef1f5'}"><th style="text-align:left;padding:6px 7px">Lado</th><th style="text-align:right;padding:6px 7px">Dist. doc.</th><th style="text-align:right;padding:6px 7px">Dist. calc.</th><th style="text-align:right;padding:6px 7px">Azim. doc.</th><th style="text-align:right;padding:6px 7px">Azim. calc.</th><th style="padding:6px 7px">OK</th></tr></thead><tbody>${rows}</tbody></table></div>`;
  const resumo='<ul style="margin:10px 0 0;padding-left:18px;font-size:12px;line-height:1.55">'+a.resumo.map(r=>'<li>'+escapeHtml(r)+'</li>').join('')+'</ul>';
  const rec=a.recomendacao==='corrigido'
    ? `<div style="margin-top:10px;background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.4);border-radius:8px;padding:8px 10px;font-size:12px">✓ Recomendado: <b>traçado correto</b>. ${escapeHtml(a.motivo_recomendacao||'')}</div>`
    : '';
  return previewTracadosSVG(a)+cards+typos+`<div style="font-size:12px;color:${mut};margin:2px 0 6px">Divergências por lado (vermelho = coordenada do documento inconsistente com o azimute/distância do agrimensor):</div>`+tabela+resumo+rec;
}

/* Há discrepância real (vértices suspeitos ou typo de coordenada)? */
function laudoTemDiscrepancia(a){
  return !!(a && a.ok && a.corrigido && ((a.vertices_suspeitos && a.vertices_suspeitos.length) || (a.typos && a.typos.length)));
}
/* Mostra/oculta o botão "Revisar traçado" conforme a situação do memorial atual. */
function aplicarEstadoLaudo(a){
  laudoAtual = laudoTemDiscrepancia(a) ? a : null;
  const b=document.getElementById('btn-revisar-tracado');
  if(b) b.style.display = laudoAtual ? 'block' : 'none';
}
function ocultarRevisar(){ laudoAtual=null; const b=document.getElementById('btn-revisar-tracado'); if(b) b.style.display='none'; }

/* Abre o laudo (modal) e aplica a geometria escolhida. Retorna o geo escolhido ou null. */
async function mostrarLaudoCoords(a, nome){
  const r=await Swal.fire(Object.assign({
    title:'Laudo de coordenadas',
    html:renderLaudoHTML(a),
    width:'min(920px,96vw)',
    showCancelButton:true, showDenyButton:!!a.corrigido,
    confirmButtonText: a.corrigido?'🟢 Mapear traçado correto':'Mapear',
    denyButtonText:'🔴 Mapear transcrito (com erros)',
    cancelButtonText:'Fechar',
    confirmButtonColor:'#10b981', denyButtonColor:'#ef4444', cancelButtonColor:'#6b7785',
    reverseButtons:false
  }, swalTema()));
  let geo=null, qual='';
  if(r.isConfirmed){ geo = a.corrigido || a.transcrito; qual = a.corrigido?'Traçado correto':'Coordenadas'; }
  else if(r.isDenied){ geo = a.transcrito; qual = 'Coordenadas transcritas'; }
  if(!geo) return null;
  origemAtual='memorial'; lastGeo=geo; geoOverrideWgs84=geo.coordenadas_wgs84||null;
  desenhar(geo, nome);
  document.getElementById('btn-save').disabled=false;
  setStatus('ok', qual+' mapeado — confira o desenho e grave.');
  return geo;
}

/* PDF: oferece escolher o traçado (correto x transcrito) com prévia e grava no registro já criado. */
async function laudoPdfEscolher(a, matricula, id){
  const r=await Swal.fire(Object.assign({
    title:'Traçado da matrícula '+(matricula||''),
    html: renderLaudoHTML(a),
    width:'min(940px,96vw)',
    showCancelButton:true, showDenyButton:!!a.corrigido,
    confirmButtonText:a.corrigido?'🟢 Usar traçado correto':'Usar',
    denyButtonText:'🔴 Usar transcrito (com erros)',
    cancelButtonText:'Decidir depois',
    confirmButtonColor:'#10b981', denyButtonColor:'#ef4444', cancelButtonColor:'#6b7785'
  }, swalTema()));
  let geo=null, qual='';
  if(r.isConfirmed){ geo=a.corrigido||a.transcrito; qual='correto'; }
  else if(r.isDenied){ geo=a.transcrito; qual='transcrito (com erros)'; }
  if(!geo) return; // "Decidir depois" — mantém o traçado já cadastrado; use "Revisar traçado" no registro
  const res=await post({acao:'salvar', origem:'memorial', numero_matricula:String(matricula||''), identificador:'', geo_wgs84:(geo.coordenadas_wgs84||'')});
  if(res && res.ok){
    setStatus('ok','Traçado '+qual+' aplicado à matrícula '+matricula+'.');
    await carregarLista();
    if(id) carregarImovel(id); else if(typeof modo!=='undefined' && modo==='overview') verTodos();
  } else {
    setStatus('err',(res&&res.erro)||'Não foi possível atualizar o traçado.');
  }
}

document.getElementById('btn-analisar').onclick = async ()=>{
  const memorial=document.getElementById('memorial').value;
  if(!memorial.trim()){ setStatus('err','Cole um memorial descritivo para analisar.'); return; }
  setStatus('warn','Analisando coordenadas…');
  const a=await post({acao:'analisar_coords', memorial});
  if(!a.ok){ setStatus('err', a.erro||'Não foi possível analisar.'); return; }
  setStatus('ok', a.num_vertices+' vértices analisados.');
  aplicarEstadoLaudo(a);
  await mostrarLaudoCoords(a, document.getElementById('identificador').value.trim());
};

/* Botão de revisão (reaparece na edição só para imóveis com coordenadas inconsistentes). */
document.getElementById('btn-revisar-tracado').onclick = async ()=>{
  const nome=document.getElementById('identificador').value.trim();
  if(laudoAtual){ await mostrarLaudoCoords(laudoAtual, nome); return; }
  const memorial=document.getElementById('memorial').value;
  if(!memorial.trim()){ setStatus('err','Memorial vazio.'); return; }
  const a=await post({acao:'analisar_coords', memorial});
  if(!a.ok){ setStatus('err', a.erro||'Não foi possível analisar.'); return; }
  aplicarEstadoLaudo(a);
  await mostrarLaudoCoords(a, nome);
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
    tipo_imovel: document.getElementById('tipo_imovel').value,
    geo_wgs84: (origemAtual==='memorial' && geoOverrideWgs84) ? geoOverrideWgs84 : ''
  });
  if(!res.ok){ setStatus('err', res.erro || 'Falha ao gravar.'); return; }
  setStatus('ok', res.mensagem);
  if(res.id){ abrirCorPainel(res.id, null, null); onrSetAtivo(res.id, identificador); onrPreencherGeometria(lastGeo); }
  carregarLista();
};

/* ===================== IMPORTAÇÃO KML ===================== */
/* ---- Importação de KML: 1 ou vários arquivos (1 imóvel por arquivo) ---- */
/* ===== Importação unificada: um único dropzone detecta KML x PDF pelo tipo ===== */
function vxImportarArquivos(files){
  const arr = Array.from(files||[]);
  if(!arr.length) return;
  const kmls = arr.filter(f=>/\.kml$/i.test(f.name));
  const pdfs = arr.filter(f=>/\.pdf$/i.test(f.name) || f.type==='application/pdf');
  if(!kmls.length && !pdfs.length){ setStatus('err','Formato não suportado — envie arquivos .kml ou .pdf.'); return; }
  if(kmls.length) lerLoteKml(kmls);
  if(pdfs.length){ pdfs.length>1 ? enviarLotePdfMatricula(pdfs) : enviarPdfMatricula(pdfs[0]); }
}
const vxDrop = document.getElementById('vx-drop');
const vxDropFile = document.getElementById('vx-drop-file');
if(vxDrop && vxDropFile){
  vxDrop.onclick = ()=> vxDropFile.click();
  vxDrop.addEventListener('keydown', e=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); vxDropFile.click(); } });
  vxDropFile.onchange = e=>{ if(e.target.files && e.target.files.length) vxImportarArquivos(e.target.files); e.target.value=''; };
  ['dragover','dragenter'].forEach(ev=>vxDrop.addEventListener(ev,e=>{e.preventDefault();vxDrop.classList.add('drag');}));
  ['dragleave','drop'].forEach(ev=>vxDrop.addEventListener(ev,e=>{e.preventDefault();vxDrop.classList.remove('drag');}));
  vxDrop.addEventListener('drop', e=>{ const fs=e.dataTransfer.files; if(fs && fs.length) vxImportarArquivos(fs); });
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
  ov.classList.remove('indet');
  const t=document.getElementById('import-ttl'); if(t) t.textContent = titulo||'Importando…';
  importProgressUpdate(0, total||1, '');
  ov.classList.add('show');
}
// Modo indeterminado (1 arquivo): anel girando + "Lendo com IA…" (não há % por etapa).
function importProgressIndeterminado(titulo, nome){
  const ov=document.getElementById('import-ov'); if(!ov) return;
  ov.classList.add('show','indet');
  const t=document.getElementById('import-ttl'); if(t) t.textContent = titulo||'Processando…';
  const fg=document.getElementById('import-ring-fg'); if(fg) fg.style.strokeDashoffset = (IMPORT_RING_LEN*0.72).toFixed(1);
  const p=document.getElementById('import-pct'); if(p) p.textContent = '';
  const m=document.getElementById('import-meta'); if(m) m.textContent = 'Lendo com IA…';
  const fn=document.getElementById('import-file'); if(fn) fn.textContent = nome||'';
}
function importProgressUpdate(done, total, nome){
  total = total||1; const pct = Math.max(0, Math.min(100, Math.round(done/total*100)));
  const fg=document.getElementById('import-ring-fg'); if(fg) fg.style.strokeDashoffset = (IMPORT_RING_LEN*(1-pct/100)).toFixed(1);
  const p=document.getElementById('import-pct'); if(p) p.textContent = pct+'%';
  const m=document.getElementById('import-meta'); if(m) m.textContent = `${Math.min(done,total)} de ${total}`;
  const fn=document.getElementById('import-file'); if(fn) fn.textContent = nome||'';
}
function importProgressHide(){ const ov=document.getElementById('import-ov'); if(ov){ ov.classList.remove('show'); ov.classList.remove('indet'); } }

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
  const cont = {criado:0,duplicado:0,erro:0}; let comInc=0; impresIdsInc=[]; let nMapa=0, nItn=0;
  resultados.forEach(r=>{ cont[r.status]=(cont[r.status]||0)+1; if(r.destino==='mapa') nMapa++; else if(r.destino==='itn') nItn++; if(r.inconsistencias && r.inconsistencias.length){ comInc++; if(r.id) impresIdsInc.push(r.id); } });
  const tt=document.getElementById('impres-titulo'); if(tt) tt.textContent = titulo||'Resultado da importação';
  const resumo=[];
  if(cont.criado) resumo.push(`<span class="impres-chip ok">${cont.criado} cadastrado(s)</span>`);
  if(nMapa) resumo.push(`<span class="impres-chip" style="color:#13693f;border-color:rgba(19,105,63,.3)">${nMapa} no mapa</span>`);
  if(nItn) resumo.push(`<span class="impres-chip" style="color:#4636a8;border-color:rgba(70,54,168,.3)">${nItn} só ITN 03</span>`);
  if(cont.duplicado) resumo.push(`<span class="impres-chip dup">${cont.duplicado} já existente(s)</span>`);
  if(cont.erro) resumo.push(`<span class="impres-chip err">${cont.erro} com erro</span>`);
  if(comInc) resumo.push(`<span class="impres-chip warn">${comInc} com inconsistência(s)</span>`);
  document.getElementById('impres-resumo').innerHTML = resumo.join('') || '<span class="impres-chip">Nada importado</span>';
  document.getElementById('impres-list').innerHTML = resultados.map(r=>{
    const ic = r.status==='criado'?'✓':(r.status==='duplicado'?'≡':'×');
    const st = r.status==='criado'?'Cadastrado':(r.status==='duplicado'?'Já existente':'Erro');
    const dest = r.destino==='itn' ? '<span class="impres-dest itn" title="Cadastrada apenas para a carga da ITN 03 (sem coordenadas, não aparece no mapa)">ITN 03 · sem mapa</span>'
               : r.destino==='mapa' ? '<span class="impres-dest mapa" title="Cadastrada com coordenadas — disponível no mapa e na carga ITN 03">No mapa</span>' : '';
    const rel = (r.inconsistencias && r.inconsistencias.length && r.id)
      ? `<button class="mini-rel" data-rel="${r.id}">⤓ Relatório deste imóvel</button>` : '';
    const verMapa = (r.id && r.destino!=='itn' && r.status!=='erro')
      ? `<button class="mini-rel vermapa" data-vermapa="${r.id}" data-proj="${r.is_projeto?1:0}" title="Abrir a visão geral e confrontar as sobreposições deste imóvel">🗺 Ver no mapa</button>` : '';
    const acoesRow = (rel||verMapa) ? `<div class="impres-relrow">${verMapa}${rel}</div>` : '';
    return `<div class="impres-item">
      <div class="impres-row1"><span class="impres-ic ${r.status}">${ic}</span><span class="impres-nome">${escapeHtml(r.nome||'(sem nome)')}</span>${dest}<span class="impres-st">${st}</span></div>
      ${r.msg?`<div class="impres-msg">${escapeHtml(r.msg)}</div>`:''}
      ${incLinhasHTML(r.inconsistencias)}
      ${acoesRow}
    </div>`;
  }).join('') || '<div class="impres-msg">Nenhum item processado.</div>';
  document.querySelectorAll('#impres-list [data-rel]').forEach(b=> b.onclick=()=> gerarRelatorioInconsistencias([+b.dataset.rel]));
  document.querySelectorAll('#impres-list [data-vermapa]').forEach(b=> b.onclick=()=> verNoMapaConfronto(+b.dataset.vermapa, b.dataset.proj==='1'));
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
// Abre a visão geral e confronta as sobreposições do imóvel importado (consulta "identificação;*").
async function verNoMapaConfronto(id, isProjeto){
  const m=document.getElementById('modal-import-res'); if(m) m.classList.remove('show');
  // Vai para a seção do MAPA e ESPERA o mapa ficar visível e com tamanho real ANTES de desenhar —
  // desenhar a visão geral num mapa 0×0 fazia o polígono não renderizar até dar F5.
  await vxWaitMapReady();
  // Projeto: garante a base de PROJETOS (carrega matrículas + projeto para o confronto)
  if(isProjeto && escopoBase!=='projetos'){
    escopoBase='projetos';
    const bt=document.getElementById('base-toggle');
    if(bt){ bt.classList.add('projetos'); bt.querySelectorAll('.bt-btn').forEach(x=>x.classList.toggle('active', x.getAttribute('data-base')==='projetos')); }
  }
  await carregarLista();
  // Garante que a categoria em exibição inclua o imóvel-alvo (senão ele fica de fora da visão geral)
  vistaLista='todas'; if(typeof sincronizarVistaToggle==='function') sincronizarVistaToggle();
  await verTodos();
  document.getElementById('overview-panel').classList.add('show');
  // Monta o termo pela identificação do imóvel (matrícula se houver, senão identificador)
  const it = (itensOverview||[]).find(x=>String(x.id)===String(id))
          || (imoveisCache||[]).find(x=>String(x.id)===String(id)) || {};
  let termo = (it.numero_matricula && String(it.numero_matricula).trim())
      ? String(it.numero_matricula).trim().replace(/^0+(?=\d)/,'') : (it.identificador||'');
  termo = String(termo||'').replace(/;/g,' ').trim();
  if(!termo){ setStatus('warn','Imóvel sem identificação para confrontar no mapa.'); return; }
  const busca=document.getElementById('ov-busca');
  if(busca) busca.value = termo + ';*';
  if(typeof filtrarOverlaps==='function') filtrarOverlaps(); // aplica "termo;*" → foca o imóvel + sobreposições
  // Reforço final: reenquadra no imóvel-alvo e FORÇA o re-render do polígono (setMap null→map),
  // resolvendo em definitivo o caso em que a camada não aparecia sem F5.
  vxEnsureMapVisibleThen(()=>{
    const alvo=(itensOverview||[]).find(x=>String(x.id)===String(id));
    if(alvo && alvo._poly && alvo._poly.getPath){
      try{ alvo._poly.setMap(null); alvo._poly.setMap(map); }catch(_){}
      const bb=new google.maps.LatLngBounds();
      alvo._poly.getPath().forEach(p=>bb.extend(p));
      if(!bb.isEmpty()) map.fitBounds(bb,60);
    } else if(alvo && alvo.centro){ map.panTo(alvo.centro); }
  });
  setStatus('ok', 'Confrontando "' + termo + '" com as matrículas — sobreposições em vermelho.');
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
  const b=document.getElementById('btn-todos');
  if(modo==='overview' && b && !b.classList.contains('active')){
    // Estava no foco de confronto (filtro "matrícula;*"): limpa o filtro e volta a ver TODOS
    const busca=document.getElementById('ov-busca'); if(busca) busca.value='';
    const mb=document.getElementById('muni-badge'); if(mb) mb.style.display='none';
    if(typeof vxRevealMap==='function') vxRevealMap();
    verTodos();
    return;
  }
  if(modo==='overview') sairOverview(); else { if(typeof vxRevealMap==='function') vxRevealMap(); verTodos(); }
};
(function(){
  const bt=document.getElementById('base-toggle'); if(!bt) return;
  bt.querySelectorAll('.bt-btn').forEach(b=>{
    b.onclick = async ()=>{
      const base=b.getAttribute('data-base'); if(base===escopoBase) return;
      escopoBase=base;
      bt.querySelectorAll('.bt-btn').forEach(x=>x.classList.toggle('active', x===b));
      bt.classList.toggle('projetos', base==='projetos');
      // dica visual do que a importação fará agora
      const nb=document.getElementById('btn-novo'); // se existir botão "novo"
      if(nb) nb.title = (base==='projetos') ? 'Importar como PROJETO (sem exigir matrícula)' : 'Cadastrar matrícula';
      setStatus('ok', base==='projetos'
        ? 'Base de PROJETOS: mostra matrículas + projetos. Importações sem matrícula entram como projeto e são checadas contra as matrículas.'
        : 'Base de MATRÍCULAS: mostra apenas matrículas.');
      await carregarLista();
      if(modo==='overview') verTodos(true);
    };
  });
})();
(function(){ const br=document.getElementById('btn-rotulos'); if(br) br.onclick = toggleRotulos; })();
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
function reabrirOverview(){
  document.getElementById('ov-reopen').classList.remove('show');
  document.getElementById('overview-panel').classList.add('show');
}

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
  else { abrirSeletorCor(it, {latLng: new google.maps.LatLng(centroidOf(it.pts).lat, centroidOf(it.pts).lng)}); localizarNoPainel(it); }
}

function estiloImovel(it){
  if(!it._poly) return;
  const sel = selecionados.has(it.id);
  const base = corBaseImovel(it);
  const linha = corLinhaImovel(it);
  it._poly.setOptions(sel
    ? {strokeColor:'#f59e0b', strokeWeight:2.5, fillColor:'#f59e0b', fillOpacity:.30, zIndex:5}
    : {strokeColor:linha, strokeOpacity:strokeOpacImovel(it), strokeWeight:imovelMorto(it)?1:1.8, fillColor:base, fillOpacity:opacidadeImovel(it), zIndex:zIndexImovel(it)});
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

/* Filtro do mapa pela CATEGORIA selecionada no painel: mostra apenas os polígonos/rótulos
   dos imóveis da categoria ativa, esconde as sobreposições entre imóveis ocultos e
   (opcionalmente) enquadra a vista nos imóveis visíveis. */
function categoriaMostraImovel(id){
  const ci = (typeof imoveisCache!=='undefined') ? imoveisCache.find(x=>String(x.id)===String(id)) : null;
  return ci ? itemNaCategoria(ci, vistaLista) : true;
}
function aplicarCategoriaMapa(ajustarVista){
  if(modo!=='overview' || !Array.isArray(itensOverview)) return 0;
  const b = new google.maps.LatLngBounds(); let nVis=0;
  itensOverview.forEach(it=>{
    const vis = categoriaMostraImovel(it.id);
    if(it._poly) it._poly.setMap(vis?map:null);
    if(it._label) it._label.setMap((vis && !rotulosOcultos)?map:null);
    if(vis){ nVis++; if(it._poly && it._poly.getPath) it._poly.getPath().forEach(ll=>b.extend(ll)); }
  });
  if(Array.isArray(overlapPolys)) overlapPolys.forEach(p=>{
    const pr=p._pair; const vis = !pr || (categoriaMostraImovel(pr[0]) && categoriaMostraImovel(pr[1]));
    p.setMap(vis?map:null);
  });
  if(ajustarVista && nVis>0 && !b.isEmpty()){ try{ map.fitBounds(b, 40); }catch(_){} }
  return nVis;
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
    const linha = corLinhaImovel(it);
    const poly = new google.maps.Polygon({paths:path,strokeColor:linha,strokeOpacity:strokeOpacImovel(it),
      strokeWeight:imovelMorto(it)?1:1.8,fillColor:base,fillOpacity:opacidadeImovel(it),map:map,zIndex:zIndexImovel(it)});
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
    if(mat){ it._label = addLabel(centro, rotuloMat(mat), (ev)=>{
      const ctrl = ctrlAtivo || (ev && (ev.ctrlKey || ev.metaKey));
      selecionarImovelDireto(it, ctrl);
    }, imovelMorto(it) ? 'morto' : '');
      if(rotulosOcultos && it._label) it._label.setMap(null);
    }
    // hover: identificação após ~2s; com rótulos OCULTOS, mostra a matrícula imediatamente
    poly.addListener('mouseover', ()=> hoverImovel(it, centro));
    poly.addListener('mousemove', ()=> { if(!hoverTip && !hoverTimer) hoverImovel(it, centro); });
    poly.addListener('mouseout',  ()=> ocultarHoverTip());
    path.forEach(pt=>bounds.extend(pt));
  });
  if(!preservarVista && ['mapa','todas'].includes(vistaLista)) map.fitBounds(bounds,40);

  // detecção de sobreposição (pré-filtro por bounding box + turf.intersect)
  const mapaSuc = construirMapaSucessao(itens);   // cadeia de desmembramentos (transitiva)
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
          if(ehDesmembramentoPar(itens[i], itens[j], mapaSuc)){
            turfToPaths(inter.geometry).forEach(path=>{
              const op = new google.maps.Polygon({paths:path,strokeColor:'#9aa3ad',
                strokeOpacity:.55,strokeWeight:1,fillColor:'#9aa3ad',fillOpacity:.5,map:map,zIndex:4,clickable:false});
              op._pair=[itens[i].id, itens[j].id]; op._tipo='morto';
              overlapPolys.push(op);
            });
            // exceção: se o trecho cobre ~toda a matrícula-mãe (ancestral), ela fica "morta" por completo
            const ki=matKey(itens[i].numero_matricula), kj=matKey(itens[j].numero_matricula);
            let mae=null;
            if(ehDescendenteMat(mapaSuc, ki, kj)) mae=itens[i];        // i é ancestral de j
            else if(ehDescendenteMat(mapaSuc, kj, ki)) mae=itens[j];   // j é ancestral de i
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
  // aplica a categoria selecionada ao mapa (esconde o que não pertence; enquadra na categoria se restritiva)
  aplicarCategoriaMapa(!preservarVista && !['mapa','todas'].includes(vistaLista));
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

/* Busca da lista lateral com a mesma ideia do painel de visão geral:
   ';' separa termos (mostra só os que casam com QUALQUER um) e '-' define intervalo de matrículas (A-B).
   Também casa por texto (identificação, proprietário, CPF, tipo, origem). */
function buscaTokenCasaItem(it, tk){
  const t=(tk||'').trim(); if(!t) return false;
  const mk = matKey(it.numero_matricula);
  const mkNum = (mk && /^\d+$/.test(mk)) ? parseInt(mk,10) : null;
  // intervalo numérico A-B -> casa pela matrícula dentro da faixa
  const mRange = t.match(/^(\d[\d.\s]*)-(\d[\d.\s]*)$/);
  if(mRange){
    const a=parseInt(mRange[1].replace(/\D/g,''),10);
    const b=parseInt(mRange[2].replace(/\D/g,''),10);
    if(!isNaN(a)&&!isNaN(b)&&mkNum!=null){ const lo=Math.min(a,b),hi=Math.max(a,b); return mkNum>=lo && mkNum<=hi; }
    return false;
  }
  // matrícula específica: se o token é só número, casa EXATO (356 ≠ 2356)
  const soNumero=/^\d+$/.test(t.replace(/[.\s]/g,''));
  const tkKey = matKey(t);
  if(soNumero) return !!(tkKey && mk && mk===tkKey);
  // texto livre (token com letras) em qualquer campo
  const tl=t.toLowerCase();
  return [it.identificador,it.numero_matricula,it.proprietario,it.cpf,it.tipo_imovel,it.origem]
    .some(c=>(c||'').toString().toLowerCase().includes(tl));
}
function buscaCasaItem(it, termoRaw){
  const raw=(termoRaw||'').trim(); if(!raw) return true;
  const tokens = raw.split(';').map(s=>s.trim()).filter(Boolean);
  if(!tokens.length) return true;
  return tokens.some(tk=>buscaTokenCasaItem(it, tk));
}

/* Predicados de categoria — usados tanto pela lista lateral quanto pelo filtro do mapa. */
function _ehItn03(it){ return String(it.itn03_exclusivo)==='1'; }
function _temFora(it){ return (it.fora_municipio||'').toString().trim()!==''; }
function _temParcial(it){ return (it.parcial_json||'').toString().trim()!==''; }
function _ehEnviado(it){ return String(it.onr_enviado)==='1'; }
function _ehProntoOnr(it){ return String(it.onr_pronto)==='1' && !_ehEnviado(it); }
function _ehBloqOnr(it){ return _temFora(it) || String(it.situacao)==='encerrada'; }
function itemNaCategoria(it, vista){
  if(vista==='todas') return true;
  if(vista==='itn03') return _ehItn03(it);
  if(_ehItn03(it)) return false;           // as demais categorias são só de mapeadas
  if(vista==='mapa') return true;
  if(vista==='fora') return _temFora(it);
  if(vista==='ultrapassa') return _temParcial(it);
  if(vista==='dentro') return !_temFora(it) && !_temParcial(it);
  if(vista==='prontas') return _ehProntoOnr(it);
  if(vista==='enviadas') return _ehEnviado(it);
  if(vista==='faltando') return !_ehEnviado(it) && !_ehBloqOnr(it);
  return true;
}
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
// Grafo de sucessão: matrícula-mãe -> sucessoras DIRETAS (a partir de matricula_sucessora de cada item).
function construirMapaSucessao(itens){
  const mapa = new Map();
  (itens||[]).forEach(it=>{
    const k = matKey(it.numero_matricula);
    if(!k) return;
    const filhos = listaMatKey(it.matricula_sucessora);
    if(!filhos.length) return;
    let set = mapa.get(k); if(!set){ set = new Set(); mapa.set(k, set); }
    filhos.forEach(f=>{ if(f && f!==k) set.add(f); });
  });
  return mapa;
}
// ancKey é ancestral (direto OU indireto) de descKey? Percorre a cadeia com guarda de ciclo.
function ehDescendenteMat(mapaSuc, ancKey, descKey){
  if(!mapaSuc || !ancKey || !descKey || ancKey===descKey) return false;
  const vis = new Set(); const fila = [ancKey];
  while(fila.length){
    const cur = fila.shift();
    const filhos = mapaSuc.get(cur);
    if(!filhos) continue;
    for(const f of filhos){
      if(f===descKey) return true;
      if(!vis.has(f)){ vis.add(f); fila.push(f); }
    }
  }
  return false;
}
// Par de desmembramento: um é ancestral do outro na CADEIA de sucessão (cobre desmembramentos
// indiretos, ex.: 7103 -> 7123 -> 7158, então 7103 x 7158 também é desmembramento, não conflito).
function ehDesmembramentoPar(a,b,mapaSuc){
  const an=matKey(a.numero_matricula), bn=matKey(b.numero_matricula);
  if(!an || !bn) return false;
  if(mapaSuc) return ehDescendenteMat(mapaSuc, an, bn) || ehDescendenteMat(mapaSuc, bn, an);
  // fallback (sem grafo): sucessão direta
  return (a.motivo_situacao==='desmembramento' && listaMatKey(a.matricula_sucessora).includes(bn)) ||
         (b.motivo_situacao==='desmembramento' && listaMatKey(b.matricula_sucessora).includes(an));
}
function corBaseImovel(it){
  if(imovelMorto(it)) return '#9aa3ad';                 // cinza "morto"
  return (it && corValida(it.cor)) ? it.cor : COR_PADRAO;
}
/* Cor da LINHA (contorno). Se não houver cor de linha própria, usa a cor de preenchimento
   (comportamento antigo de cor única). Imóvel "morto" segue cinza. */
function corLinhaImovel(it){
  if(imovelMorto(it)) return '#6b7280';
  return (it && corValida(it.cor_linha)) ? it.cor_linha : corBaseImovel(it);
}
/* Imóvel "mãe": deu origem a outra(s) matrícula(s) por desmembramento/unificação, ou foi
   encerrado. Deve ficar POR BAIXO no mapa (zIndex menor), mesmo se cadastrado depois. */
function imovelMae(it){
  if(!it) return false;
  if(imovelMorto(it)) return true;                      // encerrada
  const mot=(it.motivo_situacao||'').toString().toLowerCase();
  if(mot==='desmembramento' || mot==='unificacao' || mot==='georreferenciamento') return true;
  return (it.matricula_sucessora||'').toString().trim()!=='';   // originou outra(s)
}
/* zIndex de empilhamento: mãe/encerrada por baixo (0), imóveis "vivos" por cima (2). */
function zIndexImovel(it){ return imovelMae(it) ? 0 : 2; }
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
  add('Matrícula', (it.numero_matricula||'').replace(/^0+(?=\d)/,''));
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
/* Localiza o imóvel no painel mesmo quando ele não está na categoria/busca atual
   (ex.: fora do município, ou além do limite de exibição): ajusta a visão e destaca. */
function localizarNoPainel(it){
  if(!it || it.id==null) return;
  const sel = ()=>document.querySelector('#saved-list .item[data-id="'+it.id+'"]');
  if(sel()){ destacarNoPainel(it.id); return; }
  // não está visível: vai para "Todas" limpando a busca
  const b=document.getElementById('busca'); if(b) b.value='';
  vistaLista='todas'; if(typeof sincronizarVistaToggle==='function') sincronizarVistaToggle();
  renderLista();
  if(typeof aplicarCategoriaMapa==='function' && modo==='overview') aplicarCategoriaMapa(false);
  if(sel()){ destacarNoPainel(it.id); return; }
  // lista muito grande/truncada: filtra pela matrícula para garantir que apareça
  const mat=(it.numero_matricula||'').trim();
  if(b && mat){ b.value=mat; renderLista(); }
  destacarNoPainel(it.id);
}
function abrirSeletorCor(it, e){
  if(!infoWinCor) infoWinCor = new google.maps.InfoWindow();
  const atual = corValida(it.cor) ? it.cor.toLowerCase() : '';
  const atualLinha = corValida(it.cor_linha) ? it.cor_linha.toLowerCase() : '';
  const op = opacidadeImovel(it);
  infoWinCor.setContent(`<div class="cor-pop" id="cor-pop">
    <div class="cor-pop-t">Informações do imóvel</div>
    <div class="ip-box">${infoImovelHTML(it)}</div>
    <details class="cor-pop-acc">
      <summary class="cor-pop-lbl cor-pop-acc-sum">Cor de destaque</summary>
      <div class="cor-pop-lbl" style="margin-top:8px">Preenchimento (fundo)</div>
      <div class="cor-pop-grid" id="cor-pop-grid-fill" style="margin-top:5px">${swatchesHTML(atual)}</div>
      <div class="cor-pop-lbl" style="margin-top:9px">Linha (contorno)</div>
      <div class="cor-pop-grid" id="cor-pop-grid-linha" style="margin-top:5px">${swatchesHTML(atualLinha)}</div>
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
    let corSel = atual, linhaSel = atualLinha;
    const gridFill = document.getElementById('cor-pop-grid-fill');
    const gridLinha = document.getElementById('cor-pop-grid-linha');
    if(gridFill) gridFill.querySelectorAll('.cor-sw').forEach(b=> b.addEventListener('click', ()=>{
      corSel = b.dataset.cor;
      gridFill.querySelectorAll('.cor-sw').forEach(x=>x.classList.remove('sel')); b.classList.add('sel');
      const opv = parseFloat(document.getElementById('cor-pop-op').value);
      window.__setCorImovel(it.id, {cor:corSel, opacidade:opv});
    }));
    if(gridLinha) gridLinha.querySelectorAll('.cor-sw').forEach(b=> b.addEventListener('click', ()=>{
      linhaSel = b.dataset.cor;
      gridLinha.querySelectorAll('.cor-sw').forEach(x=>x.classList.remove('sel')); b.classList.add('sel');
      window.__setCorImovel(it.id, {corLinha:linhaSel});
    }));
    const opEl = document.getElementById('cor-pop-op');
    if(opEl) opEl.addEventListener('change', ()=>{ window.__setCorImovel(it.id, {opacidade: parseFloat(opEl.value)}); });
    const clr = document.getElementById('cor-pop-clear');
    if(clr) clr.addEventListener('click', ()=>{ corSel=''; linhaSel=''; window.__setCorImovel(it.id, {cor:'', corLinha:'', opacidade:null}); });
  });
}

// Salva cor(es) + intensidade e recolore ao vivo. opts = {cor?, corLinha?, opacidade?}
// (cada campo só é enviado/atualizado se informado — permite mudar linha, fundo ou intensidade em separado)
window.__setCorImovel = async function(id, opts){
  opts = opts || {};
  try{
    const params = {acao:'salvar_cor', id:id};
    if(opts.cor !== undefined) params.cor = opts.cor;
    if(opts.corLinha !== undefined) params.cor_linha = opts.corLinha;
    if(opts.opacidade!=null && !isNaN(opts.opacidade)) params.opacidade = opts.opacidade;
    const r = await post(params);
    if(!r.ok){ setStatus('err', r.erro || 'Não foi possível salvar a cor.'); return; }
    const novaCor   = ('cor' in r) ? (r.cor||null) : undefined;
    const novaLinha = ('cor_linha' in r) ? (r.cor_linha||null) : undefined;
    const novaOp    = ('cor_opacidade' in r && r.cor_opacidade!=null) ? parseFloat(r.cor_opacidade) : (('cor_opacidade' in r)?null:undefined);
    const aplicar = (obj)=>{ if(!obj) return;
      if(novaCor!==undefined)   obj.cor = novaCor;
      if(novaLinha!==undefined) obj.cor_linha = novaLinha;
      if(novaOp!==undefined)    obj.cor_opacidade = novaOp;
    };
    aplicar((imoveisCache||[]).find(x=>String(x.id)===String(id)));
    const ov = (itensOverview||[]).find(x=>x.id===id);
    if(ov){ aplicar(ov);
      if(ov._poly && !selecionados.has(ov.id)){
        ov._poly.setOptions({strokeColor:corLinhaImovel(ov), fillColor:corBaseImovel(ov), fillOpacity:opacidadeImovel(ov), zIndex:zIndexImovel(ov)});
      }
    }
    if(imovelEditandoId === id){
      if(novaCor!==undefined)   imovelEditandoCor = novaCor;
      if(novaLinha!==undefined) imovelEditandoLinha = novaLinha;
      if(novaOp!==undefined)    imovelEditandoOpac = novaOp;
      marcarSwatchPainel(imovelEditandoCor); marcarSwatchLinhaPainel(imovelEditandoLinha); ajustarSliderPainel(imovelEditandoOpac);
      if(polygon){
        const fill = corValida(imovelEditandoCor) ? imovelEditandoCor : '#1D4ED8';
        const stroke = corValida(imovelEditandoLinha) ? imovelEditandoLinha : fill;
        polygon.setOptions({strokeColor: stroke, fillColor: fill, fillOpacity: (imovelEditandoOpac!=null?imovelEditandoOpac:0.22)});
      }
    }
    renderLista();
    setStatus('ok', (opts.cor===''&&opts.corLinha==='')?'Destaque removido.':'Cores atualizadas.');
  }catch(e){ setStatus('err','Erro ao salvar a cor.'); }
};

/* ---- Seletor de cor no painel (ao editar/gravar um imóvel) ---- */
let imovelEditandoId = null, imovelEditandoCor = null, imovelEditandoLinha = null, imovelEditandoOpac = null;

function montarSeletorCorPainel(){
  const box = document.getElementById('cor-grid');
  if(box){
    box.innerHTML = swatchesHTML('');
    box.querySelectorAll('.cor-sw').forEach(b=> b.addEventListener('click', ()=>{
      if(!imovelEditandoId) return;
      const op = document.getElementById('cor-op');
      window.__setCorImovel(imovelEditandoId, {cor:b.dataset.cor, opacidade: op?parseFloat(op.value):null});
    }));
  }
  const boxL = document.getElementById('cor-grid-linha');
  if(boxL){
    boxL.innerHTML = swatchesHTML('');
    boxL.querySelectorAll('.cor-sw').forEach(b=> b.addEventListener('click', ()=>{
      if(!imovelEditandoId) return;
      window.__setCorImovel(imovelEditandoId, {corLinha:b.dataset.cor});
    }));
  }
  const clr = document.getElementById('cor-clear');
  if(clr) clr.addEventListener('click', ()=>{ if(imovelEditandoId) window.__setCorImovel(imovelEditandoId, {cor:'', corLinha:'', opacidade:null}); });
  const op = document.getElementById('cor-op');
  if(op) op.addEventListener('change', ()=>{ if(imovelEditandoId) window.__setCorImovel(imovelEditandoId, {opacidade: parseFloat(op.value)}); });
}
function marcarSwatchPainel(cor){
  const box = document.getElementById('cor-grid'); if(!box) return;
  const c = (cor||'').toLowerCase();
  box.querySelectorAll('.cor-sw').forEach(b=> b.classList.toggle('sel', b.dataset.cor===c));
}
function marcarSwatchLinhaPainel(cor){
  const box = document.getElementById('cor-grid-linha'); if(!box) return;
  const c = (cor||'').toLowerCase();
  box.querySelectorAll('.cor-sw').forEach(b=> b.classList.toggle('sel', b.dataset.cor===c));
}
function ajustarSliderPainel(op){ const s=document.getElementById('cor-op'); if(s) s.value = (op!=null&&!isNaN(op))?op:OPACIDADE_PADRAO; }
function abrirCorPainel(id, cor, opac, corLinha){
  imovelEditandoId = id;
  imovelEditandoCor = corValida(cor)?cor:null;
  imovelEditandoLinha = corValida(corLinha)?corLinha:null;
  imovelEditandoOpac = (opac!=null)?parseFloat(opac):null;
  const sec = document.getElementById('cor-box'); if(sec) sec.style.display = id ? 'block' : 'none';
  marcarSwatchPainel(imovelEditandoCor); marcarSwatchLinhaPainel(imovelEditandoLinha); ajustarSliderPainel(imovelEditandoOpac);
  if(polygon){
    const fill = corValida(cor)?cor:null;
    const stroke = corValida(corLinha)?corLinha:(fill||null);
    if(fill || stroke) polygon.setOptions({strokeColor: (stroke||'#1D4ED8'), fillColor:(fill||'#1D4ED8'), fillOpacity:(imovelEditandoOpac!=null?imovelEditandoOpac:0.22)});
  }
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
  const soNumero=/^\d+$/.test(t.replace(/[.\s]/g,''));
  if(soNumero) return !!(tk2 && mk && mk===tk2);   // matrícula: match EXATO (356 ≠ 2356)
  if(tk2 && tk2===mk) return true;                  // token alfanumérico igual à matrícula
  const idn=(it.identificador==null?'':String(it.identificador)).toLowerCase();
  if(idn.includes(tl)) return true;
  return false;
}
/* Expande um conjunto de imóveis "semente" para incluir, em um nível:
   - os que se SOBREPÕEM a eles (estão dentro ou pegam um trecho), via sobreposições calculadas;
   - os DESMEMBRADOS relacionados (filhas listadas no matricula_sucessora da semente, e a mãe que lista a semente). */
function expandirRelacionados(seedIds){
  const seeds = new Set(seedIds);   // sementes originais — NÃO crescem durante a varredura
  const ids = new Set(seedIds);     // resultado (sementes + relacionados diretos)
  const seedItens = itensOverview.filter(it=>seeds.has(it.id));
  const seedKeys = new Set(seedItens.map(it=>matKey(it.numero_matricula)).filter(Boolean));
  // 1) sobreposições geométricas DIRETAS com as sementes (um nível; não transitivo)
  overlapsAtuais.forEach(o=>{
    if(seeds.has(o.a.id)) ids.add(o.b.id);
    if(seeds.has(o.b.id)) ids.add(o.a.id);
  });
  // 2) desmembramentos (relação mãe ⇄ filha pelo número da matrícula) das sementes
  itensOverview.forEach(it=>{
    const sucKeys = listaMatKey(it.matricula_sucessora); // matrículas que saíram de 'it'
    if(sucKeys.some(k=>seedKeys.has(k))) ids.add(it.id);  // 'it' é mãe de uma semente
    const mk = matKey(it.numero_matricula);
    if(mk && seedItens.some(s=>listaMatKey(s.matricula_sucessora).includes(mk))) ids.add(it.id); // 'it' é filha de uma semente
  });
  return ids;
}
// Mostra SOMENTE os imóveis cujas matrículas/identificações foram listadas com ';'.
// Legenda de cores por matrícula no mapa 2D (espelha a do 3D quando há sobreposição material).
let ovLegendEl=null;
function ovLegendGet(){
  if(ovLegendEl) return ovLegendEl;
  const d=document.createElement('div'); d.id='ov-legend'; d.className='ov-legend-2d'; d.style.display='none';
  ovLegendEl=d;
  try{ if(map && map.controls) map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(d); }catch(_){}
  return d;
}
function renderOvLegend2D(legenda, temSobrep){
  const el=ovLegendGet();
  if(!legenda || !legenda.length){ el.style.display='none'; el.innerHTML=''; return; }
  el.innerHTML='<h4>Matrículas</h4>'+legenda.map(l=>`<div class="row"><span class="sw" style="background:${l.cor}"></span>${escapeHtml(l.nome)}</div>`).join('')
    +(temSobrep?'<div class="row" style="margin-top:7px;border-top:1px solid #222c38;padding-top:6px"><span class="sw" style="background:#e2342f"></span>sobreposição</div>':'');
  el.style.display='block';
}
function _snapCorImovel(it){
  if(it._poly && !it._corOrig){
    try{ it._corOrig={ s: it._poly.get('strokeColor'), f: it._poly.get('fillColor'), fo: it._poly.get('fillOpacity') }; }catch(_){ it._corOrig={}; }
  }
}
function restaurarCoresDistintas2D(){
  (itensOverview||[]).forEach(it=>{
    if(it._poly && it._corOrig){
      try{ it._poly.setOptions({strokeColor:it._corOrig.s, fillColor:it._corOrig.f, fillOpacity:it._corOrig.fo}); }catch(_){}
      it._corOrig=null;
    }
  });
  if(ovLegendEl){ ovLegendEl.style.display='none'; ovLegendEl.innerHTML=''; }
}
// Colore cada imóvel exibido + legenda. Ativa quando: filtro curinga "X;*" (inclui desmembramentos,
// mesmo sem sobreposição material), OU há sobreposição material, OU algum imóvel tem cor manual.
// Mantém a cor MANUAL de quem já tem; imóvel "morto" fica cinza; os demais recebem cor da paleta.
function aplicarCoresDistintas2D(matched, lista, curinga){
  const temMat = (lista||[]).some(o=>o && o.tipo!=='formal');
  const temManual = (matched||[]).some(it=>corManualImovel(it));
  const ativar = matched && matched.length>=2 && matched.length<=PALETA_MAT.length && (curinga || temMat || temManual);
  if(!ativar){ if(ovLegendEl){ ovLegendEl.style.display='none'; ovLegendEl.innerHTML=''; } return; }
  const cores = atribuirCoresLista(matched, corFixaImovel);
  const legenda=[];
  matched.forEach((it,i)=>{
    const cor=cores[i];
    const fixa=corFixaImovel(it);        // manual do usuário ou cinza (morto): não recolore o polígono
    if(!fixa){
      _snapCorImovel(it);
      if(it._poly){ try{ it._poly.setOptions({strokeColor:cor, fillColor:cor, fillOpacity:Math.max(0.28, it._poly.get('fillOpacity')||0.28)}); }catch(_){} }
    }
    const morto = (typeof imovelMorto==='function' && imovelMorto(it));
    const nome = (rotuloMat(it.numero_matricula)||it.identificador||('#'+it.id)) + (morto?' (encerrada)':'');
    legenda.push({cor, nome});
  });
  renderOvLegend2D(legenda, temMat);
}
// Curinga '*': em "506;*", mostra a 506 e todos os sobrepostos/desmembrados dela.
function aplicarFiltroPorLista(termoRaw){
  restaurarCoresDistintas2D();
  const allTokens = termoRaw.split(';').map(s=>s.trim()).filter(Boolean);
  const curinga = allTokens.includes('*');
  const tokens = allTokens.filter(t=>t!=='*');
  // sementes: imóveis que casam com os tokens nomeados
  let mostrados = new Set();
  itensOverview.forEach(it=>{ if(tokens.some(tk=>imovelCasaToken(it, tk))) mostrados.add(it.id); });
  const sementes = new Set(mostrados);   // matrícula(s) da consulta = foco do relatório
  // '*' sozinho => mostra todos; '506;*' => expande para sobrepostos/desmembrados
  if(curinga){
    if(tokens.length===0) itensOverview.forEach(it=>mostrados.add(it.id));
    else mostrados = expandirRelacionados(mostrados);
  }
  const matched = [];
  itensOverview.forEach(it=>{
    const ok = mostrados.has(it.id);
    if(it._poly)  it._poly.setMap(ok?map:null);
    if(it._label) it._label.setMap((ok && !rotulosOcultos)?map:null);
    if(ok) matched.push(it);
  });
  // ANÁLISE DE SOBREPOSIÇÃO entre os imóveis filtrados: mostra só os destaques
  // cujos dois imóveis estão exibidos; esconde os demais.
  overlapPolys.forEach(p=>{
    const pr=p._pair;
    const vis = pr && mostrados.has(pr[0]) && mostrados.has(pr[1]);
    p.setMap(vis?map:null);
  });
  // Lista de sobreposições da tabela/relatório:
  //  - com curinga "X;*" => SOBREPOSIÇÕES DA(S) MATRÍCULA(S) da consulta (foco nela, mesmo que o outro
  //    imóvel da sobreposição não esteja na lista) — para o relatório de sobreposição focado;
  //  - sem curinga => apenas entre os imóveis listados.
  const focoSeed = curinga && sementes.size>0;
  const lista = focoSeed
    ? overlapsAtuais.filter(o=> sementes.has(o.a.id) || sementes.has(o.b.id))
    : overlapsAtuais.filter(o=> mostrados.has(o.a.id) && mostrados.has(o.b.id));
  overlapsExibidos = lista;
  aplicarCoresDistintas2D(matched, lista, curinga);
  const focoMats = [...sementes].map(id=>{ const it=itensOverview.find(x=>x.id===id); return it?(rotuloMat(it.numero_matricula)||it.identificador||('#'+id)):('#'+id); });
  const focoLabel = focoMats.length===1 ? ('da '+focoMats[0]) : 'das matrículas selecionadas';
  const sub=document.getElementById('ov-sub');
  if(sub){
    const t=contarTipos(lista);
    sub.textContent = focoSeed
      ? `${lista.length} sobreposição(ões) ${focoLabel} · ${t.mat} material(is)` + (t.formal?` + ${t.formal} formal(is)`:'') + ` · ${matched.length} imóvel(is) no mapa`
      : `${matched.length} imóvel(is) · ${t.mat} material(is)` + (t.formal?` + ${t.formal} formal(is)`:'') + ` entre eles · lista de ${tokens.length} item(ns)`;
  }
  const btn=document.getElementById('btn-relatorio');
  if(btn) btn.textContent = focoSeed && lista.length
    ? ('Gerar relatório de sobreposição ' + focoLabel + ' (PDF)')
    : (lista.length ? 'Gerar relatório dos imóveis filtrados (PDF)' : 'Gerar relatório de sobreposição (PDF)');
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
  restaurarCoresDistintas2D();
  if(itensOverview) itensOverview.forEach(it=>{
    if(it._poly)  it._poly.setMap(map);
    if(it._label) it._label.setMap(rotulosOcultos ? null : map);
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
async function concretizarProjeto(id){
  const item = (imoveisCache||[]).find(x=>String(x.id)===String(id)) || {};
  const nome = escapeHtml(item.identificador||('#'+id));
  const html = `
    <div style="text-align:left;font-size:13px">
      <p style="margin:0 0 10px">Transformar o projeto <b>${nome}</b> em <b>matrícula</b>. Os campos abaixo são opcionais:</p>
      <label style="display:block;font-size:11px;font-weight:700;color:#64748b;margin:0 0 3px">Número da matrícula (opcional)</label>
      <input id="cpj-mat" class="swal2-input" style="margin:0 0 10px;width:100%" placeholder="ex.: 12345">
      <label style="display:block;font-size:11px;font-weight:700;color:#64748b;margin:0 0 3px">Tipo do imóvel</label>
      <select id="cpj-tipo" class="swal2-select" style="margin:0;width:100%">
        <option value="">— manter —</option>
        <option value="urbano">Urbano</option>
        <option value="rural">Rural</option>
      </select>
    </div>`;
  const r = await Swal.fire({...swalTema(), title:'Concretizar projeto', html, showCancelButton:true,
    confirmButtonText:'Concretizar', cancelButtonText:'Cancelar', focusConfirm:false,
    preConfirm:()=>({ mat:(document.getElementById('cpj-mat').value||'').trim(), tipo:(document.getElementById('cpj-tipo').value||'') })
  }).then(x=>x.isConfirmed?x.value:null).catch(()=>null);
  if(!r) return;
  const res = await post({acao:'concretizar_projeto', id, numero_matricula:r.mat, tipo_imovel:r.tipo});
  if(res && res.ok){
    swalToast('success', res.mensagem||'Projeto concretizado.');
    await carregarLista(); if(modo==='overview') verTodos(true);
  } else {
    swalToast('error', (res&&res.erro)||'Não foi possível concretizar.');
  }
}
function renderLista(){
  const wrap = document.getElementById('saved-list');
  const ehItn03 = it => String(it.itn03_exclusivo)==='1';
  const temFora = it => (it.fora_municipio||'').toString().trim()!=='';
  const temParcial = it => (it.parcial_json||'').toString().trim()!=='';
  const ehDentro = it => !ehItn03(it) && !temFora(it) && !temParcial(it);
  const ehEnviado = it => String(it.onr_enviado)==='1';
  const ehProntoOnr = it => String(it.onr_pronto)==='1' && !ehEnviado(it);
  const ehBloqOnr = it => temFora(it) || String(it.situacao)==='encerrada';
  const ehFaltando = it => !ehItn03(it) && !ehEnviado(it) && !ehBloqOnr(it);
  const nExcl = imoveisCache.filter(ehItn03).length;
  const nFora = imoveisCache.filter(it=>!ehItn03(it) && temFora(it)).length;
  const nParcial = imoveisCache.filter(it=>!ehItn03(it) && temParcial(it)).length;
  const nMapa = imoveisCache.filter(it=>!ehItn03(it)).length;
  const nDentro = imoveisCache.filter(ehDentro).length;
  const nProntas = imoveisCache.filter(it=>!ehItn03(it) && ehProntoOnr(it)).length;
  const nEnviadas = imoveisCache.filter(it=>!ehItn03(it) && ehEnviado(it)).length;
  const nFaltando = imoveisCache.filter(ehFaltando).length;
  const setCount=(id,n)=>{ const e=document.getElementById(id); if(e) e.textContent=n||''; };
  setCount('vt-count-todas', imoveisCache.length);
  setCount('vt-count-mapa', nMapa);
  setCount('vt-count-dentro', nDentro);
  setCount('vt-count-fora', nFora);
  setCount('vt-count-ultrapassa', nParcial);
  setCount('vt-count-itn03', nExcl);
  setCount('vt-count-prontas', nProntas);
  setCount('vt-count-enviadas', nEnviadas);
  setCount('vt-count-faltando', nFaltando);
  const acts=document.getElementById('itn03-actions'); if(acts) acts.style.display = (vistaLista==='itn03')?'flex':'none';
  let itens = imoveisCache.filter(it=>itemNaCategoria(it, vistaLista));
  const termoRaw = (document.getElementById('busca').value||'').trim();
  if(termoRaw){
    itens = itens.filter(it=>buscaCasaItem(it, termoRaw));
  }
  if(!itens.length){
    const vazio = vistaLista==='itn03'
      ? (nExcl ? 'Nenhuma encontrada.' : 'Nenhuma matrícula exclusiva ITN 03 ainda. Use “➕ Nova matrícula”.')
      : vistaLista==='fora'
        ? 'Nenhuma matrícula fora do município. Carregue o limite do município para verificar o pertencimento.'
        : vistaLista==='ultrapassa'
          ? 'Nenhuma matrícula ultrapassando o limite. Carregue o limite do município para verificar.'
          : vistaLista==='dentro'
            ? 'Nenhuma matrícula dentro do município (ou ainda não verificada). Carregue o limite para verificar.'
            : vistaLista==='prontas'
              ? 'Nenhuma matrícula pronta para enviar ao Mapa da ONR. Complete os dados ONR das matrículas.'
              : vistaLista==='enviadas'
                ? 'Nenhuma matrícula enviada ao Mapa da ONR ainda.'
                : vistaLista==='faltando'
                  ? 'Nenhuma matrícula faltando enviar — tudo enviado ou bloqueado.'
                  : (imoveisCache.length?'Nenhum imóvel encontrado.':'Nenhum imóvel gravado ainda.');
    wrap.innerHTML = '<div class="empty-list">'+vazio+'</div>';
    return;
  }
  const RENDER_CAP = 2000;
  let truncadas = 0;
  if(itens.length > RENDER_CAP){ truncadas = itens.length - RENDER_CAP; itens = itens.slice(0, RENDER_CAP); }
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
    const projeto = String(it.is_projeto)==='1';
    const projBadge = projeto ? '<span class="proj-badge" title="Imóvel de projeto (ainda sem matrícula) — checado contra a base de matrículas">📐 projeto</span>' : '';
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
      ? `<span class="itn03-badge" title="Matrícula exclusiva ITN 03 (sem mapa)">ITN 03</span><span class="itn03-apto ${aptoItn?'ok':'no'}" title="${aptoItn?'Apta para a carga ITN 03':(morto?'Matrícula encerrada — não entra na carga ITN 03':'Faltam dados mínimos (tipo, matrícula, CNM, município, UF)')}">${aptoItn?'✓ apta':(morto?'⊘ encerrada':'⚠ incompleta')}</span>`
      : '';
    const foraMun = (it.fora_municipio||'').toString().trim();
    const foraBadge = foraMun
      ? `<span class="fora-badge" title="Imóvel FORA do município${foraMun!=='fora'?(' — está em '+escapeHtml(foraMun)):''}. Não pertence ao cartório; envio ONR e carga ITN bloqueados.">⚠ fora do município</span>`
      : '';
    let parcialObj=null; try{ if((it.parcial_json||'').toString().trim()) parcialObj=JSON.parse(it.parcial_json); }catch(e){}
    const fmtPct = v => (Math.round((+v||0)*10)/10).toString().replace('.',',');
    const parcialBadge = parcialObj
      ? `<span class="parcial-badge" title="Parte do imóvel está em ${escapeHtml(parcialObj.vizinho||'município vizinho')}">⤢ ultrapassa o limite</span>`
      : '';
    const parcialLine = parcialObj
      ? `<div class="parcial-line" title="Divisão da área do imóvel entre os municípios">⤢ <b>${fmtPct(parcialObj.dentro_pct)}%</b> (${fmt(parcialObj.dentro_ha,2)} ha) em ${escapeHtml(parcialObj.municipio||'este município')} · <b>${fmtPct(parcialObj.fora_pct)}%</b> (${fmt(parcialObj.fora_ha,2)} ha) em ${escapeHtml(parcialObj.vizinho||'município vizinho')}</div>`
      : '';
    const statusTxt = it.onr_status ? escapeHtml(it.onr_status) : (enviado?'ENVIADO':'');
    const onrBadge = (statusTxt && !excl) ? `<span class="onr-badge ${enviado?'env':''}">${statusTxt}</span>` : '';
    const encMotivo = it.motivo_situacao==='georreferenciamento' ? 'georreferenciamento' : (it.motivo_situacao==='desmembramento' ? 'desmembramento total' : 'unificação');
    const encMeta = morto
      ? `<span class="enc-meta" title="Matrícula encerrada por ${encMotivo}${it.matricula_sucessora?(' — sucessora: '+escapeHtml(it.matricula_sucessora)):''}. Não entra na carga ITN 03 nem no Mapa da ONR.">⊘ matrícula encerrada</span>`
      : '';
    const acaoBtn = projeto
      ? `<button class="it-proj" data-act="concretizar" title="Concretizar: transformar este projeto em matrícula">✔</button>`
      : (excl
      ? `<button class="it-onr" data-act="itn03" title="${aptoItn?'Exportar carga ITN 03 desta matrícula':(morto?'Bloqueado: matrícula encerrada':(foraMun?'Bloqueado: imóvel fora do município':'Faltam dados mínimos da ITN 03 para exportar'))}" ${aptoItn?'':'disabled'}>⤓</button>`
      : (enviado
          ? `<button class="it-onr enviado" data-act="status" title="Consultar status na ONR">⟳</button>`
          : `<button class="it-onr" data-act="enviar" title="${pronto?'Enviar ao Mapa ONR':(morto?'Bloqueado: matrícula encerrada':(foraMun?'Bloqueado: imóvel fora do município':'Faltam dados ONR para enviar'))}" ${pronto?'':'disabled'}>➤</button>`));
    return `<div class="item${morto?' morto':''}${foraMun?' fora-mun':''}${parcialObj?' parcial-mun':''}" data-id="${it.id}">
      ${corDot}
      <div class="info">
        <div class="nm">${escapeHtml(it.identificador||'(sem identificação)')} ${mortoBadge}${foraBadge}${parcialBadge}${exclBadge}${projBadge}${(function(){const n=incParse(it.inconsistencias).length;return n?`<span class="inc-badge" title="${n} inconsistência(s) — clique para ver/relatar" data-inc="${it.id}">⚠ ${n}</span>`:'';})()}</div>
        <div class="mt">${sub.join(' · ')||meta} ${onrBadge}${encMeta}</div>
        ${parcialLine}
      </div>
      ${tag}
      ${acaoBtn}
      <button class="it-edit" title="Editar dados">✎</button>
      <button class="del" title="Excluir">×</button>
    </div>`;
  }).join('') + (truncadas ? `<div class="empty-list" style="opacity:.85;border-top:1px dashed var(--line);margin-top:4px;padding-top:8px">+ ${truncadas} matrícula(s) não exibida(s) aqui — use a <b>busca</b> (ex.: 1746 ou 100-200) ou uma <b>categoria</b> para localizar.</div>` : '');
  wrap.querySelectorAll('.item').forEach(el=>{
    const id = el.dataset.id;
    el.querySelector('.del').onclick = async (e)=>{ e.stopPropagation(); if(!(await swalConfirm('Excluir imóvel?','Esta ação não pode ser desfeita.','Excluir')))return; await post({acao:'excluir', id}); carregarLista(); if(modo==='overview') verTodos(); };
    el.querySelector('.it-edit').onclick = (e)=>{ e.stopPropagation(); abrirEdicao(id); };
    const projB = el.querySelector('.it-proj');
    if(projB) projB.onclick = (e)=>{ e.stopPropagation(); concretizarProjeto(id); };
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
/* ===== FOCO DE CONFRONTO ao selecionar um imóvel gravado =====
   Exibe o imóvel na VISÃO GERAL com a consulta "matrícula;*" (detecta sobreposições
   e desmembradas), mantendo os recursos do modo single: pontos dos vértices e
   badge de pertencimento ao município (dentro/parcial/fora). */
async function focarImovelConfronto(reg, geo){
  if(typeof vxWaitMapReady==='function') await vxWaitMapReady();
  // Garante a visão geral com TODAS as categorias (senão o alvo pode ficar fora da base)
  if(modo!=='overview' || !itensOverview || !itensOverview.length){
    vistaLista='todas'; if(typeof sincronizarVistaToggle==='function') sincronizarVistaToggle();
    await verTodos();
  }
  document.getElementById('overview-panel').classList.add('show');
  const rp=document.getElementById('ov-reopen'); if(rp) rp.classList.remove('show');
  // Termo da consulta: NÚMERO da matrícula puro (sem o rótulo "Mat.", que quebra o
  // casamento exato do filtro); sem matrícula, usa a identificação do imóvel
  let termo = (reg.numero_matricula && String(reg.numero_matricula).trim())
      ? String(reg.numero_matricula).trim().replace(/^0+(?=\d)/,'') : (reg.identificador||'');
  termo = String(termo||'').replace(/;/g,' ').trim();
  const busca=document.getElementById('ov-busca');
  if(termo && busca){ busca.value = termo + ';*'; if(typeof filtrarOverlaps==='function') filtrarOverlaps(); }
  // Pontos dos vértices do imóvel selecionado (como no clique sobre o imóvel)
  vertexMarkers.forEach(m=>m.setMap(null)); vertexMarkers=[];
  if(geo && geo.pts && geo.pts.length){
    geo.pts.forEach((p,i)=>{
      vertexMarkers.push(new google.maps.Marker({
        position:{lat:p[0],lng:p[1]}, map:map,
        icon:{path:google.maps.SymbolPath.CIRCLE, scale:4, fillColor:'#0e1217',
              fillOpacity:1, strokeColor:'#0D9488', strokeWeight:2},
        title:'V'+(i+1)
      }));
    });
  }
  // Leitura rápida (nome + área) sobre o mapa
  const ro=document.getElementById('readout');
  if(ro && geo){ ro.style.display='block';
    document.getElementById('ro-name').textContent = reg.identificador || 'Imóvel';
    document.getElementById('ro-area').textContent = fmt(geo.area_ha,2); }
  // Pertencimento ao município (badge dentro/parcial/fora, se o limite estiver carregado)
  if(typeof verificarPertencimento==='function') verificarPertencimento(geo);
  // "Ver todos" fica DESMARCADO durante o foco de confronto — um clique nele
  // limpa o filtro e volta a exibir todos os imóveis
  const btTodos=document.getElementById('btn-todos'); if(btTodos) btTodos.classList.remove('active');
  // Enquadra no imóvel selecionado (o filtro já enquadrou o conjunto; reforça o alvo)
  vxEnsureMapVisibleThen(()=>{
    if(geo && geo.pts && geo.pts.length){
      const b=new google.maps.LatLngBounds();
      geo.pts.forEach(p=>b.extend({lat:p[0],lng:p[1]}));
      if(!b.isEmpty()) map.fitBounds(b,60);
    }
  });
}
async function carregarImovel(id){
  const res = await post({acao:'carregar', id});
  if(!res.ok || !res.geo.ok){ setStatus('err','Não foi possível carregar este registro.'); return; }
  const reg = res.registro;
  origemAtual = reg.origem || 'memorial';
  geoOverrideWgs84 = null; // carregou geometria salva — sem traçado de laudo pendente
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
  await focarImovelConfronto(reg, res.geo);   // visão geral + "matrícula;*" + vértices + município
  mostrarPainelFoco(reg, res.geo);           // painel lateral com coordenadas/área/inconsistências
  abrirCorPainel(id, reg.cor, reg.cor_opacidade, reg.cor_linha);
  preencherOnr(reg); onrPreencherGeometria(res.geo); onrSetAtivo(id, reg.identificador);
  mostrarEncInfo(reg);
  document.getElementById('btn-save').disabled=false;
  setStatus('ok', `Carregado: ${reg.identificador} (${res.geo.num_vertices} vértices).`);
  // Conferência: se o memorial deste registro tem coordenadas inconsistentes,
  // habilita "Revisar traçado" para alternar entre correto e transcrito.
  ocultarRevisar();
  if((reg.origem||'memorial')!=='kml' && (reg.memorial_descritivo||'').trim()){
    post({acao:'analisar_coords', memorial: reg.memorial_descritivo}).then(a=>{
      if(laudoTemDiscrepancia(a)){
        aplicarEstadoLaudo(a);
        setStatus('warn','Este imóvel tem coordenadas inconsistentes — use "Revisar traçado" para conferir/alterar o desenho.');
      }
    }).catch(()=>{});
  }
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
/* Valida os documentos dos proprietários ANTES de salvar. Um CPF/CNPJ inválido (dígito verificador
   errado — ex.: RG lançado por engano) NÃO é salvo: o campo é deixado em branco e o usuário é
   alertado. Retorna true se havia inválido (limpou e alertou) — nesse caso o salvamento é interrompido. */
async function edValidarDocs(){
  const inval = [];
  edProps.forEach((p,i)=>{ const doc=(p.doc||'').trim(); if(doc && docValido(doc)===false) inval.push({i, nome:(p.nome||'').trim(), doc}); });
  if(!inval.length) return false;
  inval.forEach(x=> edProps[x.i].doc='');     // deixa o campo sem preenchimento
  edRenderProps();
  const lista = inval.map(x=> '• '+(x.nome? escapeHtml(x.nome)+': ':'')+'<b>'+escapeHtml(x.doc)+'</b>').join('<br>');
  await Swal.fire(Object.assign({}, swalTema(), {
    title: 'Documento inválido',
    html: 'O CPF/CNPJ abaixo é <b>inválido</b> (dígito verificador incorreto — pode ser um RG lançado no lugar do CPF) e foi <b>deixado em branco</b>:<br><br>'+lista+'<br><br>Informe o número correto ou salve sem o documento.',
    icon: 'warning', confirmButtonText: 'Entendi'
  }));
  return true;
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
  edEhExclusiva = true; if(typeof edAtualizarMapearHint==='function') edAtualizarMapearHint();
  if(typeof edResetGeoTexto==='function') edResetGeoTexto();
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
  edItemAtual = it;
  // botão de correção/retificação: só para imóveis já enviados ao Mapa ONR
  (function(){ const b=document.getElementById('ed-onr-correcao'); if(b){ const env = String(it.onr_enviado)==='1' || (it.onr_importation_id||'').toString().trim()!==''; b.style.display = env ? '' : 'none'; } })();
  edNovoItn03 = false;
  edEhExclusiva = String(it.itn03_exclusivo)==='1';
  if(typeof edAtualizarMapearHint==='function') edAtualizarMapearHint();
  if(typeof edResetGeoTexto==='function') edResetGeoTexto();
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
  edRenderStats(it);
  const ctxSel=document.getElementById('ed-contexto-rural'); if(ctxSel) ctxSel.value = (it.contexto_rural!=null?String(it.contexto_rural):'');
  if(typeof edToggleContextoRural==='function') edToggleContextoRural();
  let sitSel='ativa';
  if(it.motivo_situacao==='desmembramento') sitSel='desmembramento';
  else if(it.motivo_situacao==='georreferenciamento') sitSel='georreferenciamento';
  else if(it.situacao==='encerrada' || it.motivo_situacao==='unificacao') sitSel='unificacao';
  document.getElementById('ed-situacao').value = sitSel;
  edSucList = []; (it.matricula_sucessora||'').split(/[,;]+/).map(s=>matKey(s)).filter(Boolean).forEach(k=>{ if(!edSucList.includes(k)) edSucList.push(k); });
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
    if(res && res.ok && res.registro){
      edPreencherOnr(res.registro);
      // mostra no editar o memorial/KML extraído (origem PDF/KML/manual)
      const ta=document.getElementById('ed-geo-text');
      if(ta) ta.value = res.registro.memorial_descritivo || '';
      // detecta coordenadas inconsistentes e habilita "Revisar traçado"
      edOcultarRevisar();
      const memo = res.registro.memorial_descritivo || '';
      if((res.registro.origem||'')!=='kml' && memo.trim()){
        post({acao:'analisar_coords', memorial:memo}).then(a=>{
          if(laudoTemDiscrepancia(a)){
            edLaudoAtual=a;
            const b=document.getElementById('ed-btn-revisar'); if(b) b.style.display='';
            const stt=document.getElementById('ed-geo-status'); if(stt){ stt.className='ed-geo-status'; stt.textContent='⚠ Coordenadas inconsistentes — use "Revisar traçado".'; }
          }
        }).catch(()=>{});
      }
    }
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
    const k = matKey(v);
    if(!k){ continue; }
    if(edSucList.some(x=>matKey(x)===k)){ dup++; continue; }
    edSucList.push(k); add++;
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
let edEhExclusiva = false;  // a matrícula em edição é exclusiva da ITN 03 (sem mapa)?
function edAtualizarMapearHint(){ const h=document.getElementById('ed-mapear-hint'); if(h) h.style.display = edEhExclusiva ? 'block' : 'none'; }
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
    if(r.mapeado){ // KML/SIGEF mapeou uma matrícula que estava sem coordenadas
      if(r.registro) edAplicarRegistro(r.registro);
      edEhExclusiva = false; if(typeof edAtualizarMapearHint==='function') edAtualizarMapearHint();
      if(typeof carregarLista==='function') carregarLista();
      if(typeof modo!=='undefined' && modo==='overview' && typeof verTodos==='function') verTodos();
    }
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
    if((r.ciclo_vida && (r.ciclo_vida.self || r.ciclo_vida.anterior)) || r.mapeado){
      if(r.mapeado){ edEhExclusiva = false; if(typeof edAtualizarMapearHint==='function') edAtualizarMapearHint(); }
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
  const gb=document.getElementById('ed-geo-aplicar');
  if(gb && !gb.dataset.init){ gb.dataset.init='1'; gb.addEventListener('click', edMapearTexto); }
  const ab=document.getElementById('ed-btn-analisar');
  if(ab && !ab.dataset.init){ ab.dataset.init='1'; ab.addEventListener('click', edAnalisarCoords); }
  const rb=document.getElementById('ed-btn-revisar');
  if(rb && !rb.dataset.init){ rb.dataset.init='1'; rb.addEventListener('click', ()=>{ edLaudoAtual ? edLaudoAbrir(edLaudoAtual) : edAnalisarCoords(); }); }
}

// Mapeia/atualiza a geometria a partir do texto colado (memorial, coordenadas ou KML).
function edResetGeoTexto(){ const ta=document.getElementById('ed-geo-text'); if(ta) ta.value=''; const st=document.getElementById('ed-geo-status'); if(st){ st.className='ed-geo-status'; st.textContent=''; } }

/* ---- Laudo de coordenadas DENTRO do editar (validar marcos / revisar traçado) ---- */
let edLaudoAtual = null;
function edOcultarRevisar(){ edLaudoAtual=null; const b=document.getElementById('ed-btn-revisar'); if(b) b.style.display='none'; }
async function edLaudoAbrir(a){
  if(!a || !a.ok){ setStatus('err','Sem dados de coordenadas para o laudo.'); return; }
  const r=await Swal.fire(Object.assign({
    title:'Laudo de coordenadas — matrícula '+((document.getElementById('ed-matricula')||{}).value||''),
    html: renderLaudoHTML(a),
    width:'min(940px,96vw)',
    showCancelButton:true, showDenyButton:!!a.corrigido,
    confirmButtonText:a.corrigido?'🟢 Usar traçado correto':'Usar',
    denyButtonText:'🔴 Usar transcrito (com erros)',
    cancelButtonText:'Fechar',
    confirmButtonColor:'#10b981', denyButtonColor:'#ef4444', cancelButtonColor:'#6b7785'
  }, swalTema()));
  let geo=null, qual='';
  if(r.isConfirmed){ geo=a.corrigido||a.transcrito; qual='correto'; }
  else if(r.isDenied){ geo=a.transcrito; qual='transcrito (com erros)'; }
  if(!geo) return;
  const id=+((document.getElementById('ed-id')||{}).value||0);
  if(!(id>0)){ setStatus('err','Salve o imóvel antes de aplicar o traçado.'); return; }
  const stt=document.getElementById('ed-geo-status'); if(stt){ stt.className='ed-geo-status'; stt.textContent='Aplicando traçado…'; }
  const res=await post({acao:'atualizar_geometria', id, geo_wgs84:(geo.coordenadas_wgs84||'')});
  if(res && res.ok){
    if(res.registro && typeof edAplicarRegistro==='function') edAplicarRegistro(res.registro);
    if(typeof carregarLista==='function') await carregarLista();
    if(typeof carregarImovel==='function') carregarImovel(id);
    else if(typeof modo!=='undefined' && modo==='overview' && typeof verTodos==='function') verTodos();
    setStatus('ok','Traçado '+qual+' aplicado à matrícula.');
    if(stt){ stt.className='ed-geo-status ok'; stt.textContent='Traçado '+qual+' aplicado: '+res.geo.num_vertices+' vértices ('+Number(res.geo.area_ha).toLocaleString('pt-BR',{minimumFractionDigits:4,maximumFractionDigits:4})+' ha).'; }
  } else { setStatus('err',(res&&res.erro)||'Falha ao atualizar a geometria.'); if(stt){ stt.className='ed-geo-status err'; stt.textContent=(res&&res.erro)||'Falha.'; } }
}
async function edAnalisarCoords(){
  const txt=((document.getElementById('ed-geo-text')||{}).value||'').trim();
  if(!txt){ setStatus('err','Cole/observe o memorial para analisar.'); return; }
  const stt=document.getElementById('ed-geo-status'); if(stt){ stt.className='ed-geo-status'; stt.textContent='Analisando…'; }
  const a=await post({acao:'analisar_coords', memorial:txt});
  if(!a.ok){ setStatus('err', a.erro||'Não foi possível analisar.'); if(stt){ stt.className='ed-geo-status err'; stt.textContent=a.erro||'Falha.'; } return; }
  if(laudoTemDiscrepancia(a)){ edLaudoAtual=a; const b=document.getElementById('ed-btn-revisar'); if(b) b.style.display=''; }
  if(stt){ stt.className='ed-geo-status'; stt.textContent=a.num_vertices+' vértices analisados.'; }
  await edLaudoAbrir(a);
}
async function edMapearTexto(){
  const ta=document.getElementById('ed-geo-text'); const st=document.getElementById('ed-geo-status');
  const setSt=(cls,msg)=>{ if(st){ st.className='ed-geo-status'+(cls?(' '+cls):''); st.textContent=msg; } };
  const txt = ta ? ta.value.trim() : '';
  if(edAnxId<=0){ setSt('err','Salve o imóvel antes de mapear.'); return; }
  if(!txt){ setSt('err','Cole o memorial, as coordenadas ou o KML.'); return; }
  setSt('','Processando…');
  const btn=document.getElementById('ed-geo-aplicar'); if(btn) btn.disabled=true;
  try{
    const r = await post({acao:'mapear_texto', id:edAnxId, conteudo:txt});
    if(!r.ok){ setSt('err', r.erro||'Falha ao mapear.'); return; }
    if(r.registro) edAplicarRegistro(r.registro);
    if(r.anexos) edRenderAnexos(r.anexos);
    edEhExclusiva=false; if(typeof edAtualizarMapearHint==='function') edAtualizarMapearHint();
    if(typeof carregarLista==='function') carregarLista();
    if(typeof modo!=='undefined' && modo==='overview' && typeof verTodos==='function') verTodos();
    setSt('ok', r.mensagem||'Geometria aplicada.');
  }catch(e){ setSt('err','Erro ao processar o texto.'); }
  finally{ if(btn) btn.disabled=false; }
}

function fecharEdicao(){ edNovoItn03=false; const t=document.getElementById('ed-titulo'); if(t) t.textContent='Editar dados do imóvel'; document.getElementById('modal-edit').classList.remove('show'); }

/* ===================== AUTOTUTELA REGISTRAL (frontend) ===================== */
const AT_FASES = [['aberto','Aberto'],['relatorio','Relatório'],['notificacao','Notificação'],['manifestacao','Manifestação'],['transacao','Transação'],['replica','Réplica'],['decisao','Decisão'],['saneamento','Saneamento'],['encerrado','Encerrado']];
const AT_FASE_LBL = {aberto:'Aberto',relatorio:'Relatório',notificacao:'Notificação',manifestacao:'Manifestação',transacao:'Transação',replica:'Réplica',decisao:'Decisão',saneamento:'Saneamento',encerrado:'Encerrado',remetido:'Remetido (Corregedor)',arquivado:'Arquivado'};

function abrirAutotutela(){ document.getElementById('modal-autotutela').classList.add('show'); atMostrarLista(); atCarregarLista(); }
function fecharAutotutela(){ document.getElementById('modal-autotutela').classList.remove('show'); }
function atMostrarLista(){ document.getElementById('at-view-lista').style.display='block'; document.getElementById('at-view-form').style.display='none'; document.getElementById('at-titulo').textContent='⚖ Autotutela Registral'; }
function atMostrarForm(){ document.getElementById('at-view-lista').style.display='none'; document.getElementById('at-view-form').style.display='block'; }

async function atCarregarLista(){
  const box=document.getElementById('at-lista'); box.innerHTML='<div class="at-hint">Carregando…</div>';
  try{
    const r=await post({acao:'autotutela_listar'});
    if(!r.ok){ box.innerHTML='<div class="at-hint">Falha ao carregar.</div>'; return; }
    if(!r.itens.length){ box.innerHTML='<div class="at-hint">Nenhum procedimento. Clique em “+ Novo procedimento” ou instaure a partir de uma sobreposição.</div>'; return; }
    box.innerHTML='';
    r.itens.forEach(it=>{
      const div=document.createElement('div'); div.className='at-item';
      const faseLbl=AT_FASE_LBL[it.fase]||it.fase;
      div.innerHTML='<div style="flex:1;min-width:0"><div class="at-num">'+escapeHtml(it.numero||('#'+it.id))+'</div>'
        +'<div class="at-meta">'+escapeHtml(({sobreposicao:'Sobreposição',duplicidade:'Duplicidade',multiplicidade:'Multiplicidade',erro_material:'Erro material',georref_erro:'Erro georref.',serventia_incompetente:'Serventia incompetente',outro:'Outro'})[it.vicio_tipo]||it.vicio_tipo||'—')
        +' · Mat. '+escapeHtml(it.matriculas||'—')+'<br>Aberto em '+atDataBR(it.data_abertura)+' · '+(it.n_notificadas||0)+'/'+(it.n_partes||0)+' parte(s) notificada(s)</div></div>'
        +'<span class="at-fase f-'+escapeHtml(it.fase)+'">'+escapeHtml(faseLbl)+'</span>';
      div.onclick=()=>atAbrir(it.id);
      box.appendChild(div);
    });
  }catch(e){ box.innerHTML='<div class="at-hint">Erro de comunicação.</div>'; }
}
function atDataBR(d){ d=(d||'').toString(); if(!d||d==='0000-00-00') return '—'; const p=d.substr(0,10).split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):d; }

async function atAbrir(id){
  try{
    const r=await post({acao:'autotutela_obter', id:id});
    if(!r.ok){ swalToast('error', r.erro||'Não encontrado.'); return; }
    atPreencherForm(r.registro);
    atMostrarForm();
  }catch(e){ swalToast('error','Erro ao abrir.'); }
}

function atPreencherForm(reg){
  document.getElementById('at-id').value=reg.id||'';
  document.getElementById('at-numero').value=reg.numero||'';
  document.getElementById('at-prenotacao').value=reg.prenotacao||'';
  document.getElementById('at-data').value=(reg.data_abertura||'').substr(0,10);
  document.getElementById('at-prazo').value=reg.prazo_dias||15;
  document.getElementById('at-fundamento').value=reg.fundamento||'litigio';
  document.getElementById('at-vicio').value=reg.vicio_tipo||'sobreposicao';
  document.getElementById('at-fase').value=reg.fase||'aberto';
  document.getElementById('at-objeto').value=reg.objeto||'';
  document.getElementById('at-matriculas').value=reg.matriculas||'';
  document.getElementById('at-relatorio').value=reg.relatorio_preliminar||'';
  document.getElementById('at-decisao').value=reg.decisao||'';
  document.getElementById('at-resultado').value=reg.resultado||'';
  document.getElementById('at-oficial').value=reg.oficial||'';
  document.getElementById('at-saneamento').value=reg.ato_saneamento||'';
  document.getElementById('at-obs').value=reg.observacoes||'';
  atRenderPartes(Array.isArray(reg.partes)?reg.partes:[]);
  atRenderSteps(reg.fase||'aberto');
  atRefreshAnexos();
  const st=document.getElementById('at-save-status'); if(st){ st.className='at-save-status'; st.textContent=''; }
}
function atRenderSteps(fase){
  const box=document.getElementById('at-steps'); if(!box) return;
  const idx=AT_FASES.findIndex(f=>f[0]===fase);
  box.innerHTML=AT_FASES.map((f,i)=>'<span class="st'+(i<=idx&&idx>=0?' on':'')+'">'+(i+1)+'·'+f[1]+'</span>').join('')
    + (fase==='remetido'?'<span class="st on">→ Corregedor</span>':'') + (fase==='arquivado'?'<span class="st on">Arquivado</span>':'');
}

function atRenderPartes(lista){
  const box=document.getElementById('at-partes'); box.innerHTML='';
  (lista||[]).forEach(p=>atAddParteEl(p));
}
function atAddParteEl(p){
  p=p||{};
  const box=document.getElementById('at-partes');
  const el=document.createElement('div'); el.className='at-parte';
  el.innerHTML=
    '<div class="at-parte-row">'
    +'<input class="p-nome" placeholder="Nome do interessado" value="'+escapeHtml(p.nome||'')+'">'
    +'<input class="p-doc" placeholder="CPF/CNPJ" value="'+escapeHtml(p.doc||'')+'">'
    +'<select class="p-papel"><option value="titular">Titular</option><option value="confrontante">Confrontante</option><option value="credor">Credor</option><option value="terceiro">Terceiro</option></select>'
    +'<input class="p-mat" placeholder="Matrícula" value="'+escapeHtml(p.matricula||'')+'">'
    +'</div>'
    +'<div class="at-parte-row2">'
    +'<label class="chk"><input type="checkbox" class="p-notif" '+(p.notificado?'checked':'')+'> Notificado em</label>'
    +'<input type="date" class="p-datanotif" value="'+escapeHtml((p.data_notif||'').substr(0,10))+'" style="max-width:150px">'
    +'<select class="p-manif"><option value="">— manifestação —</option><option value="anuencia">Anuência expressa</option><option value="impugnacao">Impugnação</option><option value="sem_resposta">Sem resposta (anuência tácita)</option></select>'
    +'<button class="rm" title="Remover">✕</button>'
    +'</div>'
    +'<input class="p-manieftxt" placeholder="Resumo da manifestação (opcional)" value="'+escapeHtml(p.manif_texto||'')+'" style="width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:7px;padding:6px 8px;font-size:11.5px;margin-top:7px;background:var(--card);color:var(--ink)">';
  el.querySelector('.p-papel').value=p.papel||'titular';
  el.querySelector('.p-manif').value=p.manifestacao||'';
  el.querySelector('.rm').onclick=()=>el.remove();
  // anexos por parte
  const anx=document.createElement('div'); anx.className='at-parte-anexos'; el.appendChild(anx);
  const up=document.createElement('div'); up.className='at-up-row';
  up.innerHTML='<select class="p-uptipo at-up-tipo"><option value="comprovante_notificacao">Comprovante de notificação</option><option value="manifestacao">Manifestação</option><option value="ar">AR/aviso de recebimento</option><option value="outro">Outro</option></select>'
    +'<button type="button" class="btn-mini p-up">📎 Anexar à parte</button><input type="file" class="p-upfile" style="display:none" accept=".pdf,.png,.jpg,.jpeg">';
  el.appendChild(up);
  const upBtn=up.querySelector('.p-up'), upFile=up.querySelector('.p-upfile'), upTipo=up.querySelector('.p-uptipo');
  upBtn.onclick=()=>upFile.click();
  upFile.onchange=()=>{ const f=upFile.files&&upFile.files[0]; if(f){ const idx=Array.from(document.querySelectorAll('#at-partes .at-parte')).indexOf(el); atUploadAnexo(idx, f, upTipo.value); upFile.value=''; } };
  box.appendChild(el);
}

/* ---- Anexos (comprovantes) ---- */
let atAnexosCache=[];
function atAnexoTipoLbl(t){ return ({ato:'Ato/edital',comprovante_notificacao:'Comprovante de notificação',manifestacao:'Manifestação',ar:'AR',outro:'Documento'})[t]||'Documento'; }
function atAnexoItemHtml(a){
  return '<div class="at-anx"><a href="?at_anexo='+a.id+'" target="_blank">📄 '+escapeHtml(a.nome_original||('anexo '+a.id))+'</a>'
    +'<span class="at-anx-t">'+escapeHtml(atAnexoTipoLbl(a.tipo))+'</span>'
    +'<a class="at-anx-dl" href="?at_anexo='+a.id+'&dl=1" title="Baixar">⬇</a>'
    +'<button type="button" class="at-anx-x" data-anx="'+a.id+'" title="Excluir">✕</button></div>';
}
function atRenderAnexosUI(){
  const ger=document.getElementById('at-anexos-geral');
  if(ger){ const gerais=atAnexosCache.filter(a=>parseInt(a.parte_idx)<0); ger.innerHTML=gerais.length?gerais.map(atAnexoItemHtml).join(''):'<span class="at-hint">—</span>'; }
  const parts=Array.from(document.querySelectorAll('#at-partes .at-parte'));
  parts.forEach((el,idx)=>{ const c=el.querySelector('.at-parte-anexos'); if(!c) return; const lst=atAnexosCache.filter(a=>parseInt(a.parte_idx)===idx); c.innerHTML=lst.length?lst.map(atAnexoItemHtml).join(''):''; });
  document.querySelectorAll('#at-view-form .at-anx-x').forEach(b=> b.onclick=()=>atExcluirAnexo(b.getAttribute('data-anx')));
}
async function atRefreshAnexos(){
  const id=document.getElementById('at-id').value;
  if(!id){ atAnexosCache=[]; atRenderAnexosUI(); return; }
  try{ const r=await post({acao:'autotutela_anexo_listar', id:id}); atAnexosCache=(r&&r.ok&&r.anexos)?r.anexos:[]; }catch(e){ atAnexosCache=[]; }
  atRenderAnexosUI();
}
async function atUploadAnexo(parteIdx, file, tipo){
  await atSalvar(true); // garante id e congela a ordem das partes
  const id=document.getElementById('at-id').value; if(!id){ swalToast('info','Salve o procedimento antes de anexar.'); return; }
  const fd=new FormData(); fd.append('acao','autotutela_anexo_upload'); fd.append('id',id); fd.append('parte_idx',parteIdx); fd.append('tipo',tipo||'outro'); fd.append('file',file);
  try{
    const r=await fetch(window.location.pathname,{method:'POST',body:fd}).then(x=>x.json());
    if(!r||!r.ok){ swalToast('error',(r&&r.erro)||'Falha ao anexar.'); return; }
    atAnexosCache=r.anexos||[]; atRenderAnexosUI(); swalToast('success','Comprovante anexado.');
  }catch(e){ swalToast('error','Erro ao anexar.'); }
}
async function atExcluirAnexo(anxId){
  const id=document.getElementById('at-id').value;
  if(!await swalConfirm('Excluir anexo?', 'O arquivo será removido do servidor.', 'Excluir')) return;
  try{ const r=await post({acao:'autotutela_anexo_excluir', anexo_id:anxId, id:id}); atAnexosCache=(r&&r.anexos)||[]; atRenderAnexosUI(); }catch(e){}
}
/* ---- Rascunho com IA (Gemini) ---- */
async function atGerarIA(alvo){
  const d=atColetarForm();
  const btns=document.querySelectorAll('.at-ia'); btns.forEach(b=>b.disabled=true);
  const st=document.getElementById('at-save-status'); if(st){ st.className='at-save-status'; st.textContent='Gerando '+(alvo==='decisao'?'decisão':'relatório')+' com IA… aguarde.'; }
  try{
    const r=await post(Object.assign({acao:'autotutela_ia', alvo:alvo}, d));
    if(!r.ok){ if(st){st.className='at-save-status err'; st.textContent=r.erro||'Falha na IA.';} return; }
    const ta=document.getElementById(alvo==='decisao'?'at-decisao':'at-relatorio');
    if(ta) ta.value=r.texto;
    if(st){ st.className='at-save-status ok'; st.textContent='Rascunho gerado ✓ — revise e ajuste antes de salvar.'; }
  }catch(e){ if(st){st.className='at-save-status err'; st.textContent='Erro ao gerar.';} }
  finally{ btns.forEach(b=>b.disabled=false); }
}
function atColetarPartes(){
  const out=[];
  document.querySelectorAll('#at-partes .at-parte').forEach(el=>{
    const nome=el.querySelector('.p-nome').value.trim();
    if(!nome && !el.querySelector('.p-mat').value.trim()) return;
    out.push({nome:nome, doc:el.querySelector('.p-doc').value.trim(), papel:el.querySelector('.p-papel').value,
      matricula:el.querySelector('.p-mat').value.trim(), notificado:el.querySelector('.p-notif').checked,
      data_notif:el.querySelector('.p-datanotif').value, manifestacao:el.querySelector('.p-manif').value,
      manif_texto:el.querySelector('.p-manieftxt').value.trim()});
  });
  return out;
}
function atColetarForm(){
  return {
    id:document.getElementById('at-id').value,
    prenotacao:document.getElementById('at-prenotacao').value,
    data_abertura:document.getElementById('at-data').value,
    prazo_dias:document.getElementById('at-prazo').value,
    fundamento:document.getElementById('at-fundamento').value,
    vicio_tipo:document.getElementById('at-vicio').value,
    fase:document.getElementById('at-fase').value,
    objeto:document.getElementById('at-objeto').value,
    matriculas:document.getElementById('at-matriculas').value,
    relatorio_preliminar:document.getElementById('at-relatorio').value,
    decisao:document.getElementById('at-decisao').value,
    resultado:document.getElementById('at-resultado').value,
    oficial:document.getElementById('at-oficial').value,
    ato_saneamento:document.getElementById('at-saneamento').value,
    observacoes:document.getElementById('at-obs').value,
    partes:JSON.stringify(atColetarPartes())
  };
}
async function atSalvar(silent){
  const d=atColetarForm(); if(!d.id){ return false; }
  const st=document.getElementById('at-save-status');
  try{
    const r=await post(Object.assign({acao:'autotutela_salvar'}, d));
    if(!r.ok){ if(st){st.className='at-save-status err'; st.textContent=r.erro||'Falha ao salvar.';} return false; }
    if(st && !silent){ st.className='at-save-status ok'; st.textContent='Salvo ✓'; setTimeout(()=>{st.textContent='';},2500); }
    atRenderSteps(d.fase);
    return true;
  }catch(e){ if(st){st.className='at-save-status err'; st.textContent='Erro ao salvar.';} return false; }
}
async function atExcluir(){
  const id=document.getElementById('at-id').value; if(!id) return;
  if(!await swalConfirm('Excluir procedimento?', 'O procedimento de autotutela registral e seus anexos serão excluídos definitivamente.', 'Excluir')) return;
  try{ await post({acao:'autotutela_excluir', id:id}); }catch(e){}
  atMostrarLista(); atCarregarLista();
}
async function atDoc(tipo){
  const ok=await atSalvar(true); // salva antes de gerar para refletir edições
  const id=document.getElementById('at-id').value; if(!id) return;
  document.getElementById('at-pdf-id').value=id;
  document.getElementById('at-pdf-tipo').value=tipo;
  document.getElementById('at-pdf-form').submit();
}
async function atNovo(prefill){
  prefill=prefill||{};
  try{
    const r=await post(Object.assign({acao:'autotutela_abrir', fundamento:'litigio', prazo_dias:15}, prefill));
    if(!r.ok){ swalToast('error', r.erro||'Falha ao abrir.'); return; }
    await atAbrir(r.id);
    swalToast('success','Procedimento '+r.numero+' instaurado.');
  }catch(e){ swalToast('error','Erro ao instaurar.'); }
}
// Instaura a partir das sobreposições exibidas (foco) no painel
function atInstaurarDaSobreposicao(){
  const lista=(typeof overlapsExibidos!=='undefined' && overlapsExibidos.length)?overlapsExibidos:(typeof overlapsAtuais!=='undefined'?overlapsAtuais:[]);
  if(!lista.length){ swalToast('info','Não há sobreposições exibidas. Filtre por uma matrícula (ex.: 2063;*) e tente novamente.'); return; }
  const setMat=new Set(); const partesMap={};
  lista.forEach(o=>{
    [o.a,o.b].forEach(im=>{
      if(!im) return;
      const mat=(typeof rotuloMat==='function'?rotuloMat(im.numero_matricula):im.numero_matricula)||'';
      if(mat) setMat.add(mat);
      const key=mat||(''+im.id);
      if(!partesMap[key]) partesMap[key]={nome:(im.proprietario||im.identificador||('Titular da matrícula '+mat)), papel:'titular', matricula:mat, notificado:false, manifestacao:''};
    });
  });
  const mats=[...setMat];
  const prefill={
    vicio_tipo:'sobreposicao', fundamento:'litigio',
    matriculas:mats.join('; '),
    objeto:'Apurar a sobreposição de área identificada entre as matrículas '+mats.join(', ')+', no âmbito do Sistema de Informações Geográficas do Registro de Imóveis (SIG-RI), para eventual saneamento (retificação/averbação).',
    partes:JSON.stringify(Object.values(partesMap))
  };
  fecharAutotutelaOverlapAndOpen(prefill);
}
function fecharAutotutelaOverlapAndOpen(prefill){ abrirAutotutela(); setTimeout(()=>atNovo(prefill), 60); }

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
    confirmButtonColor:'#1571B0',
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
          confirmButtonText:'Entendi', confirmButtonColor:'#1571B0',
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
          confirmButtonText:'Entendi', confirmButtonColor:'#1571B0',
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
  // proprietários: valida CPF/CNPJ — inválido não é salvo (campo fica em branco + alerta), o resto segue
  if(await edValidarDocs()) return;
  const props = edProps.map(p=>({nome:(p.nome||'').trim(), doc:(p.doc||'').trim()})).filter(p=>p.nome||p.doc);
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
/* Detecta a falha "API do Mapa ONR não configurada" (token ausente) em qualquer mensagem. */
function ehErroOnrSemConfig(txt){
  return /n[ãa]o configurad|configure? a (chave|api|token)|token da api onr|#511/i.test(String(txt||''));
}
/* Alerta claro (SweetAlert2) com atalho para configurar a API do Mapa ONR. */
async function swalOnrNaoConfig(){
  if(typeof Swal==='undefined'){ setStatus('err','Configure a API do Mapa ONR antes de enviar.'); return; }
  const r = await Swal.fire(Object.assign({
    icon:'warning',
    title:'Configure a API do Mapa ONR',
    html:'Para enviar imóveis ao <b>Mapa do Registro de Imóveis (ONR)</b> é preciso cadastrar o <b>token de acesso</b> da API.<br><br>Deseja configurar agora?',
    showCancelButton:true, confirmButtonText:'⚙ Configurar agora', cancelButtonText:'Agora não',
    confirmButtonColor:'#1571B0', cancelButtonColor:'#6b7785', reverseButtons:true
  }, swalTema()));
  if(r.isConfirmed && typeof abrirConfigOnr==='function') abrirConfigOnr();
}
async function enviarOnr(id){
  if(!(await swalConfirm('Enviar à ONR?','Enviar este imóvel ao Mapa do Registro de Imóveis (ONR)?','Enviar'))) return;
  setStatus('warn','Enviando à ONR… (gerando shapefile e transmitindo)');
  const r = await post({acao:'enviar_onr', id});
  if(!r.ok){
    if(ehErroOnrSemConfig(r.mensagem)){ setStatus('warn','Envio pausado — configure a API do Mapa ONR.'); await swalOnrNaoConfig(); carregarLista(); return; }
    setStatus('err', r.mensagem||'Falha no envio.');
    if(typeof Swal!=='undefined') Swal.fire(Object.assign({icon:'error', title:'Falha no envio à ONR',
      text: r.mensagem || 'Não foi possível enviar este imóvel ao Mapa da ONR.'}, swalTema()));
    carregarLista(); return;
  }
  setStatus('ok', r.mensagem + (r.importation_id?(' · ID: '+r.importation_id):''));
  carregarLista();
}
async function consultarStatusOnr(id){
  setStatus('warn','Consultando status na ONR…');
  const r = await post({acao:'status_onr', id});
  if(!r.ok){
    if(ehErroOnrSemConfig(r.mensagem)){ setStatus('warn','Configure a API do Mapa ONR.'); await swalOnrNaoConfig(); return; }
    setStatus('err', r.mensagem||'Falha ao consultar status.'); return;
  }
  setStatus('ok','Status ONR: '+r.status);
  carregarLista();
}
// Reenvia UM imóvel já enviado ao Mapa ONR como RETIFICAÇÃO (correção dos dados). Salva os dados
// corrigidos, força a classificação "4 — Retificação" e gera uma nova importação na ONR.
async function enviarCorrecaoOnr(){
  const id = document.getElementById('ed-id').value;
  if(!id) return;
  if(await edValidarDocs()) return;   // CPF/CNPJ inválido: limpa o campo + alerta, não reenvia
  const props = edProps.map(p=>({nome:(p.nome||'').trim(), doc:(p.doc||'').trim()})).filter(p=>p.nome||p.doc);
  if(!(await swalConfirm('Enviar correção à ONR?','Os dados atuais serão salvos e reenviados ao Mapa ONR como RETIFICAÇÃO (classificação 4), gerando uma nova importação. Continuar?','Enviar correção'))) return;
  const cls=document.getElementById('eonr_onr_classificacao'); if(cls) cls.value='4';   // Retificação
  const proprietario = props.map(p=>p.nome).join(', ');
  const cpf = props.map(p=>p.doc).join(', ');
  importProgressIndeterminado('Enviando correção à ONR', document.getElementById('ed-matricula').value||'');
  try{
    // 1) salva os dados corrigidos (inclui os campos ONR e a classificação de retificação)
    const s = await post(Object.assign({acao:'atualizar_imovel', id,
      identificador: document.getElementById('ed-identificador').value.trim(),
      numero_matricula: document.getElementById('ed-matricula').value.trim(),
      proprietario, cpf,
      tipo_imovel: document.getElementById('ed-tipo').value,
      contexto_rural: (document.getElementById('ed-contexto-rural')||{}).value || ''
    }, edColetarOnr()));
    if(!s.ok){ importProgressHide(); setStatus('err', s.erro||'Falha ao salvar os dados corrigidos.'); return; }
    // 2) reenvia à ONR — nova importação (retificação)
    const r = await post({acao:'enviar_onr', id});
    importProgressHide();
    if(!r.ok){
      if(ehErroOnrSemConfig(r.mensagem)){ setStatus('warn','Configure a API do Mapa ONR.'); await swalOnrNaoConfig(); return; }
      setStatus('err','Falha ao enviar a correção: '+(r.mensagem||''));
      if(typeof Swal!=='undefined') Swal.fire(Object.assign({icon:'error', title:'Falha ao enviar a correção',
        text:(r.mensagem||'Não foi possível reenviar ao Mapa da ONR.')}, swalTema()));
      return;
    }
    setStatus('ok','Correção enviada à ONR (retificação).'+(r.importation_id?(' · ID: '+r.importation_id):''));
    await carregarLista();
    fecharEdicao();
  }catch(e){ importProgressHide(); setStatus('err','Falha na requisição da correção.'); }
}
async function enviarTodosOnr(){
  const prontos = (imoveisCache||[]).filter(it=> String(it.onr_pronto)==='1' && String(it.onr_enviado)!=='1').length;
  if(prontos===0){
    setStatus('warn','Nenhum imóvel pronto para envio (faltam dados ONR ou já enviados).');
    if(typeof Swal!=='undefined') Swal.fire(Object.assign({icon:'info', title:'Nada para enviar',
      html:'Não há imóveis <b>prontos para o Mapa da ONR</b>. Complete os <b>Dados ONR</b> de cada imóvel (aba Cadastrar) — eles passam a contar em <b>Prontas p/ ONR</b>.'}, swalTema()));
    return;
  }
  if(!(await swalConfirm('Enviar em lote?','Enviar '+prontos+' imóvel(is) pronto(s) ao Mapa da ONR?','Enviar todos'))) return;
  setStatus('warn','Enviando '+prontos+' imóvel(is) à ONR…');
  const r = await post({acao:'enviar_onr_lote'});
  if(!r.ok){
    if(ehErroOnrSemConfig(r.mensagem||r.erro)){ setStatus('warn','Envio pausado — configure a API do Mapa ONR.'); await swalOnrNaoConfig(); return; }
    setStatus('err','Falha no envio em lote.');
    if(typeof Swal!=='undefined') Swal.fire(Object.assign({icon:'error', title:'Falha no envio em lote',
      text:(r.mensagem||r.erro||'Não foi possível enviar os imóveis ao Mapa da ONR.')}, swalTema()));
    return;
  }
  const falhas = Array.isArray(r.falhas) ? r.falhas : [];
  // Todas as falhas por API não configurada → alerta único com atalho de configuração
  if(r.enviados===0 && falhas.length && falhas.every(f=>ehErroOnrSemConfig(f))){
    setStatus('warn','Envio pausado — configure a API do Mapa ONR.');
    await swalOnrNaoConfig(); carregarLista(); return;
  }
  if(falhas.length){
    setStatus('warn', `Enviados ${r.enviados} de ${r.total}.`);
    if(typeof Swal!=='undefined'){
      const listaHtml = '<ul style="text-align:left;margin:10px 0 0;padding-left:18px;max-height:200px;overflow:auto;font-size:13px">'
        + falhas.map(f=>'<li style="margin:3px 0">'+escapeHtml(String(f))+'</li>').join('') + '</ul>';
      const temConfig = falhas.some(f=>ehErroOnrSemConfig(f));
      const res = await Swal.fire(Object.assign({
        icon: r.enviados ? 'warning' : 'error',
        title: r.enviados ? `Enviados ${r.enviados} de ${r.total}` : `Nenhum imóvel enviado`,
        html: `<div style="text-align:left"><b>${falhas.length}</b> falha(s):</div>` + listaHtml,
        showCancelButton:true, confirmButtonText: temConfig ? '⚙ Configurar API ONR' : 'Entendi',
        cancelButtonText:'Fechar', confirmButtonColor:'#1571B0', cancelButtonColor:'#6b7785', reverseButtons:true
      }, swalTema()));
      if(temConfig && res.isConfirmed && typeof abrirConfigOnr==='function') abrirConfigOnr();
    }
  } else {
    setStatus('ok', `Enviados ${r.enviados} de ${r.total}.`);
  }
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
  importProgressIndeterminado('Lendo o PDF com IA', mat||file.name);
  try{
    const fd=new FormData();
    fd.append('acao','processar_pdf_matricula');
    fd.append('matricula', mat);
    if(escopoBase==='projetos') fd.append('is_projeto','1');
    fd.append('pdf', file);
    const r = await fetch(window.location.pathname, {method:'POST', body:fd}).then(x=>x.json());
    importProgressHide();
    if(!r.ok){
      setStatus('err', r.erro||'Falha ao processar o PDF.');
      importResultadosModal('Importação de matrícula (PDF)', [{nome:(mat||file.name), status:'erro', id:null, msg:(r.erro||'falha'), inconsistencias:[]}]);
      return;
    }
    setStatus('ok', r.mensagem + (r.modelo?(' ('+r.modelo+')'):''));
    await carregarLista();
    if(r.criado){
      // novo imóvel mapeado: atualiza a visão do mapa
      if(typeof modo!=='undefined' && modo==='overview') verTodos();
    } else if(typeof imovelAtivoId!=='undefined' && imovelAtivoId && String(imovelAtivoId)===String(r.id)){
      carregarImovel(r.id);
    }
    // coordenadas inconsistentes? oferece a escolha (traçado correto x transcrito) com prévia
    if(r.laudo && r.laudo.ok){
      await laudoPdfEscolher(r.laudo, r.matricula||mat, r.id);
    }
    // mostra o MESMO modal de resultado do lote (cadastro/duplicado + inconsistências)
    const status = r.criado ? 'criado' : 'duplicado';
    const destino = r.criado ? (r.itn03_exclusivo ? 'itn' : 'mapa') : '';
    importResultadosModal('Importação de matrícula (PDF)', [{
      nome:(r.matricula||mat||file.name), status, destino, id:r.id||null, is_projeto:!!r.is_projeto,
      msg:r.mensagem||'', inconsistencias:r.inconsistencias||[]
    }]);
  }catch(e){
    importProgressHide();
    setStatus('err','Falha na requisição de processamento.');
    importResultadosModal('Importação de matrícula (PDF)', [{nome:(mat||file.name), status:'erro', id:null, msg:'erro de requisição', inconsistencias:[]}]);
  }
  finally{ importProgressHide(); if(lbl) lbl.innerHTML='Matrícula ou <b>SIGEF</b> em PDF — mapear via IA <span class="zone-multi">(1 ou vários)</span>'; }
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
      if(escopoBase==='projetos') fd.append('is_projeto','1');
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
      const destino = r.criado ? (r.itn03_exclusivo ? 'itn' : 'mapa') : '';
      resultados.push({nome:(r.matricula||mat||f.name), status, destino, id:r.id||null, is_projeto:!!r.is_projeto, msg:r.mensagem||'', inconsistencias:r.inconsistencias||[]});
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
async function carregarMunicipios(uf, selecionarNome, autoMostrar, codigo){
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
    // 1) seleção por CÓDIGO IBGE (precisa, ignora acentos/grafias)
    if(codigo){
      const opt = Array.from(sel.options).find(o=> o.value===String(codigo));
      if(opt){ sel.value = opt.value; if(autoMostrar) mostrarLimite(); return; }
    }
    // 2) fallback: seleção por NOME
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

// Desenha o limite no mapa a partir de um GeoJSON (vindo do IBGE, do cache ou de um KML).
function desenharLimite(geojson, nome, sufixo){
  ocultarLimite();
  limiteLayer = new google.maps.Data({map:map});
  limiteLayer.addGeoJson(geojson);
  limiteLayer.setStyle({fillColor:'#2563eb', fillOpacity:0.06, strokeColor:'#2563eb', strokeOpacity:0.95, strokeWeight:2.5, clickable:false});
  limiteTurf = limiteToTurf(geojson);
  limiteNome = nome || 'município';
  const bounds = new google.maps.LatLngBounds();
  limiteLayer.forEach(f=> f.getGeometry().forEachLatLng(ll=> bounds.extend(ll)));
  if(!bounds.isEmpty() && modo!=='single') map.fitBounds(bounds, 30);
  const oc=document.getElementById('btn-muni-ocultar'); if(oc) oc.style.display='';
  muniStatus('ok', 'Limite de ' + limiteNome + ' carregado.' + (sufixo||''));
  if(window.__ultimoGeo) verificarPertencimento(window.__ultimoGeo);
  verificarTodosPertencimento();   // varre todos os imóveis e bloqueia os que estão fora
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
    if(r.ok && r.geojson){ desenharLimite(r.geojson, nome || 'município', r.fonte==='local' ? ' (base local IBGE)' : (r.cache ? ' (do cache local — IBGE indisponível)' : '')); return; }
    // IBGE indisponível: tenta o limite salvo por KML
    const c = await post({acao:'limite_cache'});
    if(c.ok && c.geojson){ desenharLimite(c.geojson, c.nome || nome || 'município', ' (limite salvo por KML — IBGE indisponível)'); return; }
    muniStatus('err', (r.erro || 'Não foi possível obter o limite.') + ' Você pode carregar o limite por KML no botão abaixo.');
  }catch(e){
    muniStatus('err', 'Erro ao carregar o limite municipal. Use "Carregar limite por KML".');
  }
}

// Carrega o limite a partir de um arquivo KML enviado (não depende do IBGE).
async function carregarLimiteKml(file){
  if(!file) return;
  const sel = document.getElementById('muni-list');
  const nome = (sel && sel.selectedIndex>=0 && sel.value) ? sel.options[sel.selectedIndex].textContent : (file.name||'limite (KML)').replace(/\.kml$/i,'');
  muniStatus('', 'Lendo KML do limite…');
  const st=document.getElementById('muni-status'); if(st) st.style.display='block';
  try{
    const fd = new FormData();
    fd.append('acao','limite_kml');
    fd.append('arquivo', file);
    fd.append('nome', nome);
    const resp = await fetch(location.href, {method:'POST', body:fd});
    const r = await resp.json();
    if(!r.ok || !r.geojson){ muniStatus('err', r.erro || 'Não foi possível ler o KML.'); return; }
    desenharLimite(r.geojson, r.nome || nome, ' (carregado por KML)');
  }catch(e){
    muniStatus('err', 'Erro ao processar o KML do limite.');
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
  // 1) base LOCAL (offline) — identifica o município do ponto sem depender do IBGE
  try{
    const r = await post({acao:'municipio_no_ponto', lat:lat, lng:lng});
    if(r && r.ok){ return r.municipio ? { municipio:r.municipio, codigo:r.codigo } : null; }
  }catch(e){}
  // 2) fallback: malha do IBGE (online)
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

  // calcula e persiste o split (parte no município x parte no vizinho) para a lista/categoria "ultrapassa"
  const sp=splitParcial(prop);
  if(sp) marcarParcial(imovelAtivoId, Object.assign({municipio:limiteNome, vizinho:nomes.join(', ')}, sp));
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

// Calcula o split de área de um imóvel que cruza o limite: parte dentro x parte fora (vizinho).
function splitParcial(prop){
  if(!limiteTurf || !prop) return null;
  let total=0, areaIn=0;
  try{ total=turf.area(prop); }catch(e){}
  try{ const inter=turf.intersect(prop, limiteTurf); if(inter) areaIn=turf.area(inter); }catch(e){}
  if(total<=0) return null;
  if(areaIn>total) areaIn=total;
  const dentroHa=areaIn/10000, foraHa=Math.max(0,(total-areaIn))/10000;
  const dentroPct=areaIn/total*100, foraPct=Math.max(0,100-dentroPct);
  return { dentro_ha:+dentroHa.toFixed(4), dentro_pct:+dentroPct.toFixed(1), fora_ha:+foraHa.toFixed(4), fora_pct:+foraPct.toFixed(1) };
}

// Nomes do(s) município(s) vizinho(s) na parte do imóvel que fica fora do limite (sem desenhar no mapa).
async function vizinhosDaParte(prop, ufCod){
  const nomes=[];
  let fora=null; try{ fora=turf.difference(prop, limiteTurf); }catch(e){ fora=null; }
  if(!fora || !fora.geometry) return nomes;
  const pedacos=[]; const g=fora.geometry;
  try{
    if(g.type==='Polygon') pedacos.push(turf.polygon(g.coordinates));
    else if(g.type==='MultiPolygon') g.coordinates.forEach(c=>pedacos.push(turf.polygon(c)));
  }catch(e){}
  for(const ped of pedacos){
    let areaM2=0; try{ areaM2=turf.area(ped); }catch(e){}
    if(areaM2 < 200) continue; // ignora microslivers de borda
    let pt=null; try{ pt=turf.pointOnFeature(ped); }catch(e){ try{ pt=turf.centroid(ped); }catch(_){ pt=null; } }
    if(!pt) continue;
    const lng=pt.geometry.coordinates[0], lat=pt.geometry.coordinates[1];
    let nome=null; try{ const info=await municipioDoPontoIBGE(lat,lng,ufCod); nome=info && info.municipio ? info.municipio : null; }catch(e){}
    if(nome && normNomeMun(nome)!==normNomeMun(limiteNome) && !nomes.some(n=>normNomeMun(n)===normNomeMun(nome))) nomes.push(nome);
  }
  return nomes;
}

function parcialSig(o){ if(!o) return ''; return [o.dentro_pct,o.fora_pct,o.dentro_ha,o.fora_ha,(o.vizinho||''),(o.municipio||'')].join('|'); }

// Persiste (e mostra) o estado "ultrapassa o limite". obj nulo => limpa. Retorna true se mudou.
async function marcarParcial(id, obj, silent){
  if(!id) return false;
  const it=(typeof imoveisCache!=='undefined') ? imoveisCache.find(x=>String(x.id)===String(id)) : null;
  let antesObj=null; const antes = it ? ((it.parcial_json||'').toString()) : null;
  try{ antesObj = antes ? JSON.parse(antes) : null; }catch(e){}
  if(antes!==null && parcialSig(antesObj)===parcialSig(obj)) return false; // sem mudança
  const novo = obj ? JSON.stringify(obj) : '';
  try{
    await post({acao:'marcar_parcial', id, parcial: novo});
    if(it) it.parcial_json = novo;
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
      let within=false, intersects=false;
      try{ within=turf.booleanWithin(prop, limiteTurf); }catch(e){}
      try{ intersects=turf.booleanIntersects(prop, limiteTurf); }catch(e){}
      if(within){ // totalmente dentro: libera e limpa parcial
        if(await marcarForaMunicipio(it.id,'',true)) mudou=true;
        if(await marcarParcial(it.id, null, true)) mudou=true;
        continue;
      }
      if(intersects){ // ULTRAPASSA o limite: calcula o split e identifica o vizinho
        if(await marcarForaMunicipio(it.id,'',true)) mudou=true;
        const sp=splitParcial(prop);
        if(sp){ const nomes=await vizinhosDaParte(prop, ufCod);
          if(await marcarParcial(it.id, Object.assign({municipio:limiteNome, vizinho:nomes.join(', ')}, sp), true)) mudou=true; }
        continue;
      }
      // totalmente fora -> identifica o município (malha já fica em cache) e marca
      let nome=null;
      if(ufCod){
        try{ const pt=turf.pointOnFeature(prop); const info=await municipioDoPontoIBGE(pt.geometry.coordinates[1],pt.geometry.coordinates[0],ufCod); nome=info&&info.municipio?info.municipio:null; }catch(e){}
      }
      if(await marcarParcial(it.id, null, true)) mudou=true;
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
    if(within){ cls='dentro'; txt='✓ Imóvel DENTRO de '+limiteNome; limparFora(); marcarForaMunicipio(imovelAtivoId, ''); marcarParcial(imovelAtivoId, null); }
    else if(intersects){ cls='parcial'; txt='⚠ Imóvel CRUZA o limite de '+limiteNome+' (identificando vizinho…)'; marcarForaMunicipio(imovelAtivoId, ''); }
    else { cls='fora'; txt='✗ Imóvel FORA de '+limiteNome+' (identificando município…)'; limparFora(); marcarParcial(imovelAtivoId, null); }
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
  const bk=document.getElementById('btn-muni-kml'); const fk=document.getElementById('muni-kml-file');
  if(bk && fk){ bk.addEventListener('click', ()=>fk.click()); fk.addEventListener('change', ()=>{ const f=fk.files && fk.files[0]; carregarLimiteKml(f); fk.value=''; }); }
  const bo=document.getElementById('btn-muni-ocultar'); if(bo) bo.addEventListener('click', ()=>{ ocultarLimite(); muniStatus('', ''); });
  montarSeletorCorPainel();

  // Autotutela registral
  const btAt=document.getElementById('btn-autotutela'); if(btAt) btAt.addEventListener('click', abrirAutotutela);
  const btAtX=document.getElementById('at-fechar'); if(btAtX) btAtX.addEventListener('click', fecharAutotutela);
  const btAtNovo=document.getElementById('at-novo'); if(btAtNovo) btAtNovo.addEventListener('click', ()=>atNovo());
  const btAtVoltar=document.getElementById('at-voltar'); if(btAtVoltar) btAtVoltar.addEventListener('click', ()=>{ atMostrarLista(); atCarregarLista(); });
  const btAtSalvar=document.getElementById('at-salvar'); if(btAtSalvar) btAtSalvar.addEventListener('click', ()=>atSalvar(false));
  const btAtExcluir=document.getElementById('at-excluir'); if(btAtExcluir) btAtExcluir.addEventListener('click', atExcluir);
  const btAtAddP=document.getElementById('at-add-parte'); if(btAtAddP) btAtAddP.addEventListener('click', ()=>atAddParteEl({}));
  document.querySelectorAll('#at-view-form .btn-doc').forEach(b=> b.addEventListener('click', ()=>atDoc(b.getAttribute('data-doc'))));
  const btInst=document.getElementById('btn-instaurar-at'); if(btInst) btInst.addEventListener('click', atInstaurarDaSobreposicao);
  const atFase=document.getElementById('at-fase'); if(atFase) atFase.addEventListener('change', ()=>atRenderSteps(atFase.value));
  document.querySelectorAll('#at-view-form .at-ia').forEach(b=> b.addEventListener('click', ()=>atGerarIA(b.getAttribute('data-alvo'))));
  const atUpG=document.getElementById('at-up-geral-btn'), atUpGF=document.getElementById('at-up-geral-file'), atUpGT=document.getElementById('at-up-geral-tipo');
  if(atUpG && atUpGF){ atUpG.addEventListener('click', ()=>atUpGF.click()); atUpGF.addEventListener('change', ()=>{ const f=atUpGF.files&&atUpGF.files[0]; if(f){ atUploadAnexo(-1, f, atUpGT?atUpGT.value:'outro'); atUpGF.value=''; } }); }

  // Município padrão pela serventia (cadastro_serventia.cidade) — recarrega a cada atualização da página
  let cidade='', ufServ='MA', codServ='';
  try{ const s = await post({acao:'serventia_municipio'}); if(s.ok){ cidade=s.cidade||''; ufServ=s.uf||'MA'; codServ=s.codigo||''; } }catch(e){}
  if(uf && ufServ) uf.value = ufServ;
  if(uf) await carregarMunicipios(uf.value, cidade, !!(cidade||codServ), codServ); // pré-seleciona e foca no município da serventia

  // Busca na lista
  const busca=document.getElementById('busca'), bclear=document.getElementById('busca-clear');
  if(busca){ busca.addEventListener('input', ()=>{ if(bclear) bclear.style.display = busca.value?'block':'none'; renderLista(); }); }
  if(bclear){ bclear.addEventListener('click', ()=>{ busca.value=''; bclear.style.display='none'; renderLista(); busca.focus(); }); }

  // Modal de edição
  const es=document.getElementById('ed-salvar'); if(es) es.addEventListener('click', salvarEdicao);
  const ei=document.getElementById('ed-itn03'); if(ei) ei.addEventListener('click', ()=>exportarItn03Individual());
  const ol=document.getElementById('ov-itn03'); if(ol) ol.addEventListener('click', ()=>exportarItn03Lote('mapa'));
  document.querySelectorAll('#vista-toggle .vt-btn').forEach(b=> b.addEventListener('click', ()=>{
    const v=b.dataset.vista;
    vistaLista = ['itn03','fora','ultrapassa','dentro','todas','prontas','enviadas','faltando'].includes(v) ? v : 'mapa';
    sincronizarVistaToggle(); renderLista();
    // reflete a categoria no mapa imediatamente
    if(modo==='overview') aplicarCategoriaMapa(true);
    else verTodos(); // entra na visão geral; verTodos aplica a categoria no fim
  }));
  const bNova=document.getElementById('btn-itn03-nova'); if(bNova) bNova.addEventListener('click', novaMatriculaItn03);
  const bExpExcl=document.getElementById('btn-itn03-export-excl'); if(bExpExcl) bExpExcl.addEventListener('click', ()=>exportarItn03Lote('exclusivas'));
  const esit=document.getElementById('ed-situacao'); if(esit) esit.addEventListener('change', edToggleEnc);
  const etipo=document.getElementById('ed-tipo'); if(etipo) etipo.addEventListener('change', ()=>{ edToggleContextoRural(); edRenderStats(edItemAtual); });
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
  const bcor=document.getElementById('ed-onr-correcao'); if(bcor) bcor.addEventListener('click', enviarCorrecaoOnr);
  const bcfg=document.getElementById('btn-onr-config'); if(bcfg) bcfg.addEventListener('click', abrirConfigOnr);
  const cfgov=document.getElementById('modal-onr-config'); if(cfgov) cfgov.addEventListener('click', e=>{ if(e.target===cfgov) fecharConfigOnr(); });
  // IA (Gemini): dropzone unificado é ligado acima (vx-drop); aqui só a configuração
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
  const rp = document.getElementById('ov-reopen');
  if(rp){ tornarArrastavelBtn(rp, reabrirOverview); }
  const fp = document.getElementById('foco-panel');
  if(fp){ tornarArrastavel(fp, fp.querySelector('.foco-head')); }
  const frp = document.getElementById('foco-reopen');
  if(frp){ tornarArrastavelBtn(frp, reabrirFoco); }
})();

/* Arrasto para um BOTÃO: move se o ponteiro andar (>4px); clique puro executa a ação. */
function tornarArrastavelBtn(el, onClick){
  let ox=0, oy=0, sx=0, sy=0, moved=false, drag=false;
  function inicio(e){
    const ev = e.touches ? e.touches[0] : e;
    const rect = el.getBoundingClientRect();
    const pp = el.offsetParent ? el.offsetParent.getBoundingClientRect() : {left:0, top:0};
    ox = ev.clientX - rect.left; oy = ev.clientY - rect.top; sx = ev.clientX; sy = ev.clientY; moved = false; drag = true;
    el.style.left = (rect.left - pp.left) + 'px'; el.style.top = (rect.top - pp.top) + 'px'; el.style.right = 'auto';
    document.addEventListener('mousemove', mover);
    document.addEventListener('mouseup', fim);
    document.addEventListener('touchmove', mover, {passive:false});
    document.addEventListener('touchend', fim);
  }
  function mover(e){
    if(!drag) return;
    const ev = e.touches ? e.touches[0] : e;
    if(Math.abs(ev.clientX - sx) + Math.abs(ev.clientY - sy) > 4) moved = true;
    if(!moved) return;
    const pp = el.offsetParent.getBoundingClientRect();
    let x = ev.clientX - pp.left - ox, y = ev.clientY - pp.top - oy;
    x = Math.max(0, Math.min(x, pp.width - el.offsetWidth));
    y = Math.max(0, Math.min(y, pp.height - el.offsetHeight));
    el.style.left = x + 'px'; el.style.top = y + 'px';
    if(e.cancelable) e.preventDefault();
  }
  function fim(e){
    drag = false;
    document.removeEventListener('mousemove', mover);
    document.removeEventListener('mouseup', fim);
    document.removeEventListener('touchmove', mover);
    document.removeEventListener('touchend', fim);
    if(!moved && typeof onClick === 'function') onClick(e);
  }
  el.addEventListener('mousedown', inicio);
  el.addEventListener('touchstart', inicio, {passive:false});
}

/* =====================================================================
   Vertex — navegação por abas da barra de comando + sessão do mapa
   (organiza os recursos que antes ficavam escondidos no painel lateral)
   ===================================================================== */
function vxMapResize(delay){
  setTimeout(()=>{ try{ if(window.google && map) google.maps.event.trigger(map,'resize'); }catch(_){} }, delay||60);
}
/* O espaço do menu inferior do Atlas é reservado por CSS (--vx-bottombar); aqui só reenquadramos o mapa. */
function vxAjustarRodape(){ vxMapResize(90); }
/* Garante que a aba do MAPA esteja ativa e que o #map já tenha tamanho real (>0)
   antes de executar cb (normalmente um fitBounds). Resolve o caso em que o
   enquadramento acontecia com o mapa oculto (0×0) e o imóvel só aparecia após F5. */
function vxEnsureMapVisibleThen(cb){
  if(typeof window.__vxAtivar==='function') window.__vxAtivar('mapa');
  let tries=0;
  (function wait(){
    const d=document.getElementById('map');
    if(window.google && map && d && d.clientWidth>4 && d.clientHeight>4){
      try{ google.maps.event.trigger(map,'resize'); }catch(_){}
      requestAnimationFrame(()=>{ try{ cb&&cb(); }catch(_){} });
    } else if(tries++ < 60){ requestAnimationFrame(wait); }
    else { try{ cb&&cb(); }catch(_){} }
  })();
}
/* Versão em Promise: garante a aba do MAPA ativa e o #map com tamanho real,
   e SÓ resolve depois de um resize + 2 frames — para desenhar polígonos num mapa já assentado. */
function vxWaitMapReady(){
  return new Promise(res=>{
    if(typeof window.__vxAtivar==='function') window.__vxAtivar('mapa');
    let n=0;
    (function w(){
      const d=document.getElementById('map');
      if(window.google && map && d && d.clientWidth>4 && d.clientHeight>4){
        try{ google.maps.event.trigger(map,'resize'); }catch(_){}
        requestAnimationFrame(()=>requestAnimationFrame(res));
      } else if(n++<60){ requestAnimationFrame(w); }
      else res();
    })();
  });
}
/* Ativa a aba do MAPA (seção em tela cheia) e reenquadra o mapa */
function vxRevealMap(){
  if(typeof window.__vxAtivar==='function') window.__vxAtivar('mapa');
  vxMapResize(80);
}
/* ===================== ABA RELATÓRIOS =====================
   Três painéis de completude calculados sobre a base de matrículas (projetos fora):
   1) Matrículas faltantes na numeração (1 até a maior cadastrada);
   2) Envio ao Mapa da ONR (enviadas × faltantes, destacando as já prontas);
   3) Aptidão para a carga ITN 03 (aptas × pendentes, com o que falta em cada uma). */
function relEsc(t){ return String(t==null?'':t).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function relRotulo(i){ const k=matKey(i.numero_matricula); return /^\d+$/.test(k) ? ('Mat. '+k) : (i.identificador||('#'+i.id)); }
function relFaixas(nums){ // [1,2,3,7,9,10] -> ["1–3","7","9–10"]
  const out=[]; let a=null,b=null;
  nums.forEach(n=>{ if(a===null){a=b=n;} else if(n===b+1){b=n;} else {out.push(a===b?String(a):a+'–'+b); a=b=n;} });
  if(a!==null) out.push(a===b?String(a):a+'–'+b);
  return out;
}
function relDonut(pct, cor){
  const r=44, C=2*Math.PI*r, done=Math.max(0,Math.min(100,pct))/100*C;
  return `<div class="rel-donut"><svg viewBox="0 0 104 104">
    <circle cx="52" cy="52" r="${r}" fill="none" stroke="var(--line)" stroke-width="11"/>
    <circle cx="52" cy="52" r="${r}" fill="none" stroke="${cor}" stroke-width="11" stroke-linecap="round"
      stroke-dasharray="${done.toFixed(2)} ${(C-done).toFixed(2)}"/>
  </svg><div class="rel-pct">${pct.toFixed(pct>=100?0:1).replace('.',',')}%<small>completo</small></div></div>`;
}
function relCard(ic, icCls, titulo, sub, pct, cor, linhas, missHtml, copyId, copyTxt, nota){
  return `<div class="rel-card">
    <div class="rel-h"><span class="vx-act-ic ${icCls}"><svg class="ic"><use href="#${ic}"/></svg></span>
      <div><h3>${titulo}</h3><p>${sub}</p></div></div>
    <div class="rel-top">${relDonut(pct,cor)}<div class="rel-nums">${linhas}</div></div>
    ${missHtml}
    ${nota?`<p class="rel-nota">${nota}</p>`:''}
    <textarea id="${copyId}-txt" style="position:absolute;left:-9999px;top:-9999px">${relEsc(copyTxt)}</textarea>
  </div>`;
}
function relLinha(cor, valor, rotulo){ return `<div class="rel-n"><span class="dot" style="background:${cor}"></span><b>${valor}</b> ${rotulo}</div>`; }
function relMissBloco(titulo, copyId, chipsHtml, vazioMsg){
  return `<div><div class="rel-miss-h"><span>${titulo}</span>
    ${chipsHtml?`<button class="rel-copy" data-copy="${copyId}"><svg class="ic"><use href="#i-copy"/></svg>Copiar lista</button>`:''}</div>
    ${chipsHtml?`<div class="rel-miss">${chipsHtml}</div>`:`<div class="rel-vazio">${vazioMsg}</div>`}</div>`;
}
async function renderRelatorios(forcar){
  const wrap=document.getElementById('rel-wrap'); if(!wrap) return;
  wrap.innerHTML='<div class="rel-loading">Calculando relatórios…</div>';
  try{ const res=await post({acao:'listar'}); if(res && res.ok && res.itens) imoveisCache=res.itens; }catch(_){}
  const its=(imoveisCache||[]).filter(i=> !(+i.is_projeto));
  const enc=i=> String(i.situacao||'')==='encerrada';
  const verdeOk='var(--green)';

  /* ---- 1) Matrículas faltantes na numeração ---- */
  const nums=new Set(); let semNum=0;
  its.forEach(i=>{ const k=matKey(i.numero_matricula); if(/^\d+$/.test(k)&&+k>0) nums.add(+k); else semNum++; });
  const maxM=nums.size?Math.max(...Array.from(nums)):0;
  const faltamN=[]; for(let n=1;n<=maxM;n++) if(!nums.has(n)) faltamN.push(n);
  const pct1=maxM? (nums.size/maxM*100) : 0;
  const faixas=relFaixas(faltamN);
  const chips1=faixas.map(f=>`<span class="rel-chip warn">${f}</span>`).join('');
  const card1=relCard('i-arch','itn','Matrículas faltantes','Numeração de 1 até a maior cadastrada',
    pct1, verdeOk,
    relLinha(verdeOk, nums.size.toLocaleString('pt-BR'), 'matrículas cadastradas')+
    relLinha('var(--err-bright)', faltamN.length.toLocaleString('pt-BR'), 'faltantes até a Mat. '+maxM.toLocaleString('pt-BR'))+
    (semNum?relLinha('var(--faint)', semNum, 'sem nº de matrícula (fora do cálculo)'):''),
    relMissBloco('Faltantes (intervalos)','rel1',chips1,'Nenhuma matrícula faltante — numeração completa.'),
    'rel1', 'Matrículas faltantes (1 até '+maxM+'): '+(faixas.join(', ')||'nenhuma'),
    'Considera toda matrícula numérica cadastrada no sistema (mapeadas, exclusivas ITN 03 e encerradas).');

  /* ---- 2) Envio ao Mapa da ONR ---- */
  const baseOnr=its.filter(i=> !(+i.itn03_exclusivo) && !enc(i));
  const envs=baseOnr.filter(i=> +i.onr_enviado);
  const falO=baseOnr.filter(i=> !(+i.onr_enviado));
  falO.sort((a,b)=>{ const ka=matKey(a.numero_matricula), kb=matKey(b.numero_matricula);
    const na=/^\d+$/.test(ka)?+ka:1e15, nb=/^\d+$/.test(kb)?+kb:1e15; return na-nb; });
  const prontasO=falO.filter(i=> +i.onr_pronto).length;
  const pct2=baseOnr.length? (envs.length/baseOnr.length*100) : 0;
  const chips2=falO.map(i=>`<span class="rel-chip ${(+i.onr_pronto)?'ok2':'warn'}" title="${(+i.onr_pronto)?'Pronta para envio':'Dados ONR incompletos'}">${relEsc(relRotulo(i))}</span>`).join('');
  const card2=relCard('i-globe','','Envio ao Mapa da ONR','Imóveis mapeados ativos (encerradas fora)',
    pct2, verdeOk,
    relLinha(verdeOk, envs.length.toLocaleString('pt-BR'), 'enviadas de '+baseOnr.length.toLocaleString('pt-BR'))+
    relLinha('var(--err-bright)', falO.length.toLocaleString('pt-BR'), 'faltando enviar')+
    (prontasO?relLinha('var(--green)', prontasO, 'destas já estão prontas p/ envio'):''),
    relMissBloco('Faltando enviar','rel2',chips2,'Todos os imóveis elegíveis já foram enviados.'),
    'rel2', 'Faltando enviar ao Mapa ONR: '+(falO.map(relRotulo).join(', ')||'nenhuma'),
    'Verde = pronta para envio (dados ONR completos) · Vermelho = dados ONR incompletos.');

  /* ---- 3) Carga ITN 03 ---- */
  const baseItn=its.filter(i=> !enc(i));
  const oks=baseItn.filter(i=> +i.itn03_ok);
  const falI=baseItn.filter(i=> !(+i.itn03_ok));
  falI.sort((a,b)=>{ const ka=matKey(a.numero_matricula), kb=matKey(b.numero_matricula);
    const na=/^\d+$/.test(ka)?+ka:1e15, nb=/^\d+$/.test(kb)?+kb:1e15; return na-nb; });
  const pct3=baseItn.length? (oks.length/baseItn.length*100) : 0;
  const chips3=falI.map(i=>{ const f=(i.itn03_faltam||[]).join(', ');
    return `<span class="rel-chip warn" title="${relEsc('Falta: '+(f||'—'))}">${relEsc(relRotulo(i))}<small>${relEsc(f)}</small></span>`; }).join('');
  const card3=relCard('i-down','itn','Carga ITN 03','Mapeadas + exclusivas ativas (encerradas fora)',
    pct3, verdeOk,
    relLinha(verdeOk, oks.length.toLocaleString('pt-BR'), 'aptas de '+baseItn.length.toLocaleString('pt-BR'))+
    relLinha('var(--err-bright)', falI.length.toLocaleString('pt-BR'), 'pendentes'),
    relMissBloco('Pendentes (com o que falta)','rel3',chips3,'Todas as matrículas ativas estão aptas para a carga.'),
    'rel3', 'Pendentes da carga ITN 03: '+(falI.map(i=> relRotulo(i)+' (falta: '+((i.itn03_faltam||[]).join(', ')||'—')+')').join('; ')||'nenhuma'),
    'A pendência de cada matrícula aparece dentro do próprio cartão; preencha na aba Cadastrar → Dados ONR.');

  wrap.innerHTML = card1 + card2 + card3;
  const q=document.getElementById('rel-quando');
  if(q) q.textContent='calculado às '+new Date().toLocaleTimeString('pt-BR');
  wrap.querySelectorAll('.rel-copy').forEach(b=>{
    b.onclick=()=>{ const ta=document.getElementById(b.dataset.copy+'-txt'); if(!ta) return;
      ta.select(); try{ document.execCommand('copy'); }catch(_){}
      if(navigator.clipboard) navigator.clipboard.writeText(ta.value).catch(()=>{});
      const sp=b.lastChild; const old=sp.textContent; sp.textContent='Copiado!'; setTimeout(()=>{ sp.textContent=old; },1200);
    };
  });
}
(function(){
  const tabs = Array.from(document.querySelectorAll('#vx-tabs .vx-tab'));
  const panes = Array.from(document.querySelectorAll('#vx-stage .vx-pane'));
  if(!tabs.length) return;

  function ativar(nome){
    tabs.forEach(t=> t.classList.toggle('active', t.dataset.tab===nome));
    panes.forEach(p=> p.classList.toggle('active', p.dataset.pane===nome));
    if(nome==='mapa') vxMapResize(60);   // o mapa estava oculto: precisa recalcular o tamanho
    if(nome==='relatorios' && typeof renderRelatorios==='function') renderRelatorios();
  }
  window.__vxAtivar = ativar;   // exposto para vxRevealMap() e outras partes do sistema

  tabs.forEach(t=> t.addEventListener('click', ()=> ativar(t.dataset.tab)));

  // Atalhos 1..7 trocam de aba (fora de campos de texto)
  const mapaAbas={'1':'mapa','2':'imoveis','3':'cadastrar','4':'importar','5':'onr','6':'limites','7':'relatorios'};
  document.addEventListener('keydown', (e)=>{
    const alvo=e.target, tag=(alvo&&alvo.tagName||'').toUpperCase();
    if(tag==='INPUT'||tag==='TEXTAREA'||tag==='SELECT'||(alvo&&alvo.isContentEditable)) return;
    if(mapaAbas[e.key]){ ativar(mapaAbas[e.key]); }
  });

  /* ===== Ações da aba ONR / Carga (sem formulário) ===== */
  const acao=(id,fn)=>{ const b=document.getElementById(id); if(b) b.addEventListener('click', fn); };
  acao('vx-onr-enviar', ()=>{ if(typeof enviarTodosOnr==='function') enviarTodosOnr(); });
  acao('vx-onr-config', ()=>{ if(typeof abrirConfigOnr==='function') abrirConfigOnr(); });
  acao('vx-itn-lote',   ()=>{ if(typeof exportarItn03Lote==='function') exportarItn03Lote('mapa'); });
  acao('vx-itn-excl',   ()=>{ if(typeof exportarItn03Lote==='function') exportarItn03Lote('exclusivas'); });
  acao('vx-itn-nova',   ()=>{ if(typeof novaMatriculaItn03==='function') novaMatriculaItn03(); });
  acao('rel-atualizar', ()=>{ if(typeof renderRelatorios==='function') renderRelatorios(true); });

  window.addEventListener('resize', ()=> vxMapResize(120));
  let _vxR; window.addEventListener('resize', ()=>{ clearTimeout(_vxR); _vxR=setTimeout(vxAjustarRodape,180); });
  window.addEventListener('load', ()=> setTimeout(vxAjustarRodape,120));
  vxAjustarRodape();
  setTimeout(vxAjustarRodape, 600);
  vxMapResize(400);   // primeiro enquadramento após o layout assentar
})();
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Turf.js/6.5.0/turf.min.js"></script>
<script async src="https://maps.googleapis.com/maps/api/js?key=<?= GMAPS_KEY ?>&v=alpha&callback=initMap&loading=async"></script>
</body>
</html>
