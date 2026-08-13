<?php
/**
 * =============================================================================
 *  ATLAS VERTEX · EDITOR DE VÉRTICES
 *  Ajuste fino de poligonal sobre imagem de satélite + geração de KML.
 * -----------------------------------------------------------------------------
 *  ACESSO RESTRITO — este arquivo NÃO é referenciado no menu do Atlas nem no
 *  index.php do Vertex. Só é alcançável digitando a URL diretamente:
 *
 *      /vertex/editor_vertices.php            (exige sessão do Atlas)
 *      /vertex/editor_vertices.php?id=123     (abre um imóvel específico)
 *
 *  Camada extra opcional: se "editor_token" estiver preenchido em
 *  config_vertex.json, a URL passa a exigir ?t=<token> e responde 404 sem ele.
 * -----------------------------------------------------------------------------
 *  REAPROVEITA DO MÓDULO
 *    · session_check.php        — mesma sessão / mesmo login do Atlas
 *    · db_connection2.php       — mesma conexão mysqli ($conn)
 *    · GMAPS_KEY do index.php   — mesma chave do Google Maps (lida do arquivo)
 *    · config_gemini.json       — mesma chave/modelo da IA
 *    · index.php acao=analisar_vertex     — laudo determinístico do memorial
 *    · index.php acao=atualizar_geometria — ÚNICO caminho de gravação
 * =============================================================================
 */

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: no-referrer');
}

require_once __DIR__ . '/session_check.php';
checkSession();                               // redireciona para ../login.php se não autenticado
require_once __DIR__ . '/db_connection2.php'; // fornece $conn (mysqli)

date_default_timezone_set('America/Fortaleza');

/* ===========================================================================
 * 0. GUARDA DE ACESSO (token opcional)
 * ======================================================================== */

function evConfigVertex(): array
{
    $p = __DIR__ . '/config_vertex.json';
    if (is_file($p)) {
        $d = json_decode((string) @file_get_contents($p), true);
        if (is_array($d)) return $d;
    }
    return [];
}

$evCfg   = evConfigVertex();
$evToken = trim((string) ($evCfg['editor_token'] ?? ''));

if ($evToken !== '') {
    $t = (string) ($_GET['t'] ?? $_POST['ev_t'] ?? '');
    if (!hash_equals($evToken, $t)) {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><meta charset="UTF-8"><title>404</title><h1>404 Not Found</h1>';
        exit;
    }
}

/* Chave do Google Maps: lida do index.php para não duplicar o segredo. */
function evGmapsKey(): string
{
    $f = __DIR__ . '/index.php';
    if (is_file($f) && ($h = @fopen($f, 'rb'))) {
        $head = (string) fread($h, 20000);
        fclose($h);
        if (preg_match("/define\(\s*'GMAPS_KEY'\s*,\s*'([^']*)'/", $head, $m)) return $m[1];
    }
    return '';
}

/* ===========================================================================
 * 1. BIBLIOTECA GEODÉSICA  (SIRGAS 2000 / GRS80)
 *    Prefixo ev* para nunca colidir com as funções do index.php.
 * ======================================================================== */

const EV_A  = 6378137.0;
const EV_F  = 1 / 298.257222101;
const EV_K0 = 0.9996;

function evUtmToGeo(float $east, float $north, int $zone, bool $south = true): array
{
    $a = EV_A; $f = EV_F; $e2 = $f * (2 - $f); $ep2 = $e2 / (1 - $e2);
    $x = $east - 500000.0;
    $y = $south ? $north - 10000000.0 : $north;

    $M  = $y / EV_K0;
    $mu = $M / ($a * (1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256));
    $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));

    $fp = $mu
        + (3 * $e1 / 2 - 27 * $e1 ** 3 / 32) * sin(2 * $mu)
        + (21 * $e1 ** 2 / 16 - 55 * $e1 ** 4 / 32) * sin(4 * $mu)
        + (151 * $e1 ** 3 / 96) * sin(6 * $mu)
        + (1097 * $e1 ** 4 / 512) * sin(8 * $mu);

    $C1 = $ep2 * cos($fp) ** 2;
    $T1 = tan($fp) ** 2;
    $R1 = $a * (1 - $e2) / (1 - $e2 * sin($fp) ** 2) ** 1.5;
    $N1 = $a / sqrt(1 - $e2 * sin($fp) ** 2);
    $D  = $x / ($N1 * EV_K0);

    $lat = $fp - ($N1 * tan($fp) / $R1) * (
        $D ** 2 / 2
        - (5 + 3 * $T1 + 10 * $C1 - 4 * $C1 ** 2 - 9 * $ep2) * $D ** 4 / 24
        + (61 + 90 * $T1 + 298 * $C1 + 45 * $T1 ** 2 - 252 * $ep2 - 3 * $C1 ** 2) * $D ** 6 / 720
    );
    $lon = deg2rad($zone * 6 - 183) + (
        $D - (1 + 2 * $T1 + $C1) * $D ** 3 / 6
        + (5 - 2 * $C1 + 28 * $T1 - 3 * $C1 ** 2 + 8 * $ep2 + 24 * $T1 ** 2) * $D ** 5 / 120
    ) / cos($fp);

    return ['lat' => rad2deg($lat), 'lon' => rad2deg($lon)];
}

function evGeoToUtm(float $lat, float $lon, int $zone): array
{
    $a = EV_A; $f = EV_F; $e2 = $f * (2 - $f); $ep2 = $e2 / (1 - $e2);
    $phi = deg2rad($lat); $lam = deg2rad($lon); $lam0 = deg2rad($zone * 6 - 183);

    $N = $a / sqrt(1 - $e2 * sin($phi) ** 2);
    $T = tan($phi) ** 2;
    $C = $ep2 * cos($phi) ** 2;
    $A = cos($phi) * ($lam - $lam0);

    $M = $a * (
        (1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256) * $phi
        - (3 * $e2 / 8 + 3 * $e2 ** 2 / 32 + 45 * $e2 ** 3 / 1024) * sin(2 * $phi)
        + (15 * $e2 ** 2 / 256 + 45 * $e2 ** 3 / 1024) * sin(4 * $phi)
        - (35 * $e2 ** 3 / 3072) * sin(6 * $phi)
    );

    $east = EV_K0 * $N * ($A + (1 - $T + $C) * $A ** 3 / 6
          + (5 - 18 * $T + $T ** 2 + 72 * $C - 58 * $ep2) * $A ** 5 / 120) + 500000.0;
    $north = EV_K0 * ($M + $N * tan($phi) * ($A ** 2 / 2
           + (5 - $T + 9 * $C + 4 * $C ** 2) * $A ** 4 / 24
           + (61 - 58 * $T + $T ** 2 + 600 * $C - 330 * $ep2) * $A ** 6 / 720));
    if ($lat < 0) $north += 10000000.0;

    return ['e' => $east, 'n' => $north];
}

function evZonaPorLon(float $lon): int { return max(1, min(60, (int) floor(($lon + 180) / 6) + 1)); }
function evFatorEscala(float $east): float { return EV_K0 * (1 + ($east - 500000.0) ** 2 / (2 * (EV_K0 * EV_A) ** 2)); }

function evFmtDMS(float $deg, bool $isLat): string
{
    $h = $isLat ? ($deg < 0 ? 'S' : 'N') : ($deg < 0 ? 'W' : 'E');
    $d = abs($deg); $g = (int) floor($d); $mf = ($d - $g) * 60; $m = (int) floor($mf); $s = ($mf - $m) * 60;
    if (round($s, 2) >= 60) { $s = 0; $m++; } if ($m >= 60) { $m = 0; $g++; }
    return sprintf('%d°%02d\'%s" %s', $g, $m, number_format($s, 2, ',', ''), $h);
}

function evFmtAz(float $deg): string
{
    $deg = fmod($deg + 360.0, 360.0);
    $g = (int) floor($deg); $mf = ($deg - $g) * 60; $m = (int) floor($mf); $s = ($mf - $m) * 60;
    if (round($s, 2) >= 60) { $s = 0; $m++; } if ($m >= 60) { $m = 0; $g++; }
    return sprintf('%d°%02d\'%s"', $g, $m, number_format($s, 2, ',', ''));
}

function evAzimute(array $p, array $q): float
{
    return fmod(rad2deg(atan2($q['e'] - $p['e'], $q['n'] - $p['n'])) + 360.0, 360.0);
}
function evDist(array $p, array $q): float { return sqrt(($q['e'] - $p['e']) ** 2 + ($q['n'] - $p['n']) ** 2); }

function evAreaSig(array $v): float
{
    $s = 0.0; $k = count($v);
    for ($i = 0; $i < $k; $i++) { $j = ($i + 1) % $k; $s += $v[$i]['e'] * $v[$j]['n'] - $v[$j]['e'] * $v[$i]['n']; }
    return $s / 2.0;
}

function evBr(float $x, int $d = 2): string { return number_format($x, $d, ',', '.'); }

function evSlug(string $s): string
{
    if (function_exists('iconv')) { $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); if ($t !== false) $s = $t; }
    $s = preg_replace('/[^A-Za-z0-9]+/', '_', $s) ?? '';
    return strtolower(trim($s, '_')) ?: 'imovel';
}

/* ===========================================================================
 * 2. IA — mesma configuração do módulo (config_gemini.json)
 * ======================================================================== */

function evGeminiCfg(): array
{
    $base = ['api_key' => '', 'models' => ['gemini-3.1-flash-lite', 'gemini-3.5-flash', 'gemini-3.1-pro-preview'],
             'default_model' => 'gemini-3.5-flash'];
    $p = __DIR__ . '/config_gemini.json';
    if (is_file($p)) { $d = json_decode((string) @file_get_contents($p), true); if (is_array($d)) $base = array_merge($base, $d); }
    if (empty($base['models']) || !is_array($base['models'])) $base['models'] = ['gemini-3.5-flash'];
    if (!in_array($base['default_model'], $base['models'], true)) $base['default_model'] = $base['models'][0];
    return $base;
}

function evGeminiTexto(array $cfg, string $prompt): array
{
    if (trim((string) ($cfg['api_key'] ?? '')) === '') {
        return ['ok' => false, 'erro' => 'Chave da API do Gemini não configurada. Use “⚙ Configurar IA” no Vertex.'];
    }
    if (!function_exists('curl_init')) return ['ok' => false, 'erro' => 'cURL indisponível neste servidor.'];

    $model = $cfg['default_model'];
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model)
         . ':generateContent?key=' . urlencode($cfg['api_key']);
    $payload = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.2]];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);

    if ($resp === false) return ['ok' => false, 'erro' => 'Falha de conexão com o Gemini: ' . $err];
    $j = json_decode((string) $resp, true);
    if ($code < 200 || $code >= 300) return ['ok' => false, 'erro' => 'Gemini: ' . ($j['error']['message'] ?? ('HTTP ' . $code))];
    $txt = trim((string) ($j['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    $txt = trim(preg_replace('/^```\w*|```$/m', '', $txt));
    if ($txt === '') return ['ok' => false, 'erro' => 'A IA não retornou texto.'];
    return ['ok' => true, 'texto' => $txt, 'modelo' => $model];
}

/* ===========================================================================
 * 3. GERADORES DE ARQUIVO
 * ======================================================================== */

function evGerarKML(array $v, array $meta): string
{
    $zone = (int) $meta['fuso']; $south = (bool) $meta['sul'];
    $geo = [];
    foreach ($v as $p) $geo[] = evUtmToGeo((float) $p['e'], (float) $p['n'], $zone, $south);

    $ordem = array_keys($v);
    if (evAreaSig($v) < 0) $ordem = array_reverse($ordem);   // anel externo anti-horário

    $coords = '';
    foreach ($ordem as $i) $coords .= sprintf("          %.8f,%.8f,0\n", $geo[$i]['lon'], $geo[$i]['lat']);
    $coords .= sprintf('          %.8f,%.8f,0', $geo[$ordem[0]]['lon'], $geo[$ordem[0]]['lat']);

    $areaUtm = abs(evAreaSig($v));
    $kMedio  = evFatorEscala(array_sum(array_column($v, 'e')) / count($v));
    $per = 0.0;
    for ($i = 0; $i < count($v); $i++) $per += evDist($v[$i], $v[($i + 1) % count($v)]);

    $esc = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $tit = $esc($meta['titulo']);

    $pontos = ''; $n = count($v);
    for ($i = 0; $i < $n; $i++) {
        $j = ($i + 1) % $n;
        $conf = trim((string) ($v[$i]['conf'] ?? ''));
        $desc = sprintf(
            'Lat: %s<br/>Long: %s<br/>N: %s m | E: %s m<br/><br/><b>%s &#8594; %s</b><br/>Azimute: %s<br/>Distância: %s m%s',
            $esc(evFmtDMS($geo[$i]['lat'], true)), $esc(evFmtDMS($geo[$i]['lon'], false)),
            $esc(evBr((float) $v[$i]['n'], 3)), $esc(evBr((float) $v[$i]['e'], 3)),
            $esc($v[$i]['id']), $esc($v[$j]['id']),
            $esc(evFmtAz(evAzimute($v[$i], $v[$j]))), $esc(evBr(evDist($v[$i], $v[$j]), 2)),
            $conf !== '' ? '<br/>Confrontante: ' . $esc($conf) : ''
        );
        $nomeV = $esc($v[$i]['id']);
        $ptXY  = sprintf('%.8f,%.8f,0', $geo[$i]['lon'], $geo[$i]['lat']);
        $pontos .= <<<XML

      <Placemark>
        <name>{$nomeV}</name>
        <styleUrl>#vxVertice</styleUrl>
        <description><![CDATA[{$desc}]]></description>
        <Point><coordinates>{$ptXY}</coordinates></Point>
      </Placemark>
XML;
    }

    $descDoc = sprintf(
        '<b>%s</b><br/><br/>Matrícula: %s<br/>Proprietário: %s<br/>Município: %s - %s<br/><br/>'
        . '<b>Área (plano UTM): %s m² &#183; %s ha</b><br/>Perímetro: %s m<br/>Fator de escala K: %s<br/><br/>'
        . 'Datum: SIRGAS 2000 &#183; Fuso %d%s &#183; MC %d°W<br/><i>Atlas Vertex — editor de vértices, %s</i>',
        $tit, $esc($meta['matricula'] ?: '—'), $esc($meta['proprietario'] ?: '—'),
        $esc($meta['municipio']), $esc($meta['uf']),
        $esc(evBr($areaUtm, 2)), $esc(evBr($areaUtm / 10000, 4)), $esc(evBr($per, 2)),
        $esc(number_format($kMedio, 8, ',', '')),
        $zone, $south ? 'S' : 'N', abs($zone * 6 - 183), date('d/m/Y H:i')
    );
    $areaTxt = $esc(evBr($areaUtm, 2));
    $perTxt  = $esc(evBr($per, 2));

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <name>{$tit}</name>
    <description><![CDATA[{$descDoc}]]></description>

    <Style id="vxLote">
      <LineStyle><color>ffED4D1D</color><width>2.5</width></LineStyle>
      <PolyStyle><color>4dED4D1D</color><fill>1</fill><outline>1</outline></PolyStyle>
    </Style>
    <Style id="vxVertice">
      <IconStyle>
        <scale>0.8</scale><color>ff808F0D</color>
        <Icon><href>http://maps.google.com/mapfiles/kml/shapes/placemark_circle.png</href></Icon>
      </IconStyle>
      <LabelStyle><scale>0.8</scale></LabelStyle>
    </Style>

    <Placemark>
      <name>{$tit}</name>
      <styleUrl>#vxLote</styleUrl>
      <description><![CDATA[Área: {$areaTxt} m² &#183; Perímetro: {$perTxt} m]]></description>
      <Polygon>
        <tessellate>1</tessellate>
        <altitudeMode>clampToGround</altitudeMode>
        <outerBoundaryIs>
          <LinearRing>
            <coordinates>
{$coords}
            </coordinates>
          </LinearRing>
        </outerBoundaryIs>
      </Polygon>
    </Placemark>

    <Folder>
      <name>Vértices</name>{$pontos}
    </Folder>
  </Document>
</kml>

XML;
}

function evGerarMemorial(array $v, array $meta): string
{
    $zone = (int) $meta['fuso']; $south = (bool) $meta['sul']; $n = count($v);
    $mc = abs($zone * 6 - 183);
    $areaUtm = abs(evAreaSig($v));
    $kMedio  = evFatorEscala(array_sum(array_column($v, 'e')) / $n);
    $per = 0.0; for ($i = 0; $i < $n; $i++) $per += evDist($v[$i], $v[($i + 1) % $n]);

    $t  = "MEMORIAL DESCRITIVO\r\n" . str_repeat('=', 78) . "\r\n\r\n";
    $t .= "Imóvel .......: {$meta['titulo']}\r\n";
    $t .= "Matrícula ....: " . ($meta['matricula'] ?: '—') . "\r\n";
    $t .= "Proprietário .: " . ($meta['proprietario'] ?: '—') . "\r\n";
    $t .= "Município ....: {$meta['municipio']} - {$meta['uf']}\r\n";
    $t .= "Área .........: " . evBr($areaUtm, 2) . " m²  (" . evBr($areaUtm / 10000, 4) . " ha)\r\n";
    $t .= "Perímetro ....: " . evBr($per, 2) . " m\r\n";
    $t .= "Fator K ......: " . number_format($kMedio, 8, ',', '') . "\r\n\r\n";
    $t .= str_repeat('-', 78) . "\r\n\r\n";

    $t .= "Inicia-se a descrição deste perímetro no vértice {$v[0]['id']}, de coordenadas "
        . "N " . evBr((float) $v[0]['n'], 3) . "m e E " . evBr((float) $v[0]['e'], 3) . "m";

    for ($i = 0; $i < $n; $i++) {
        $j = ($i + 1) % $n;
        $conf = trim((string) ($v[$i]['conf'] ?? ''));
        $t .= "; deste, segue" . ($conf !== '' ? " confrontando com {$conf}," : '')
            . " com azimute de " . evFmtAz(evAzimute($v[$i], $v[$j]))
            . " e distância de " . evBr(evDist($v[$i], $v[$j]), 2) . "m, até o vértice {$v[$j]['id']}, "
            . "de coordenadas N " . evBr((float) $v[$j]['n'], 3) . "m e E " . evBr((float) $v[$j]['e'], 3) . "m";
    }
    $t .= "; ponto inicial da descrição deste perímetro.\r\n\r\n";
    $t .= "Todas as coordenadas aqui descritas estão georreferenciadas ao Sistema Geodésico\r\n";
    $t .= "Brasileiro, a partir do sistema de referência SIRGAS 2000, e encontram-se\r\n";
    $t .= "representadas no Sistema UTM, referenciadas ao Meridiano Central {$mc}°W,\r\n";
    $t .= "fuso " . ($south ? '-' : '') . "{$zone}. Todos os azimutes, distâncias, área e\r\n";
    $t .= "perímetro foram calculados no plano de projeção UTM.\r\n\r\n";
    $t .= str_repeat('-', 78) . "\r\n\r\nTABELA DE VÉRTICES\r\n\r\n";
    $t .= sprintf("%-10s %16s %14s %-18s %-18s\r\n", 'VÉRTICE', 'N (m)', 'E (m)', 'LATITUDE', 'LONGITUDE');
    foreach ($v as $p) {
        $g = evUtmToGeo((float) $p['e'], (float) $p['n'], $zone, $south);
        $t .= sprintf("%-10s %16s %14s %-18s %-18s\r\n", $p['id'],
            evBr((float) $p['n'], 3), evBr((float) $p['e'], 3),
            evFmtDMS($g['lat'], true), evFmtDMS($g['lon'], false));
    }
    $t .= "\r\n" . str_repeat('-', 78) . "\r\n\r\nTABELA DE LADOS\r\n\r\n";
    $t .= sprintf("%-20s %-16s %12s\r\n", 'LADO', 'AZIMUTE', 'DISTÂNCIA');
    for ($i = 0; $i < $n; $i++) {
        $j = ($i + 1) % $n;
        $t .= sprintf("%-20s %-16s %12s\r\n", $v[$i]['id'] . ' - ' . $v[$j]['id'],
            evFmtAz(evAzimute($v[$i], $v[$j])), evBr(evDist($v[$i], $v[$j]), 2));
    }
    $t .= "\r\n\r\nAtlas Vertex — editor de vértices · " . date('d/m/Y H:i') . "\r\n";
    return $t;
}

function evGerarCSV(array $v, array $meta): string
{
    $zone = (int) $meta['fuso']; $south = (bool) $meta['sul']; $n = count($v);
    $out = "\xEF\xBB\xBF";
    $out .= "Vertice;Norte;Este;Latitude;Longitude;Lat_decimal;Lon_decimal;Azimute;Distancia;Confrontante\r\n";
    for ($i = 0; $i < $n; $i++) {
        $j = ($i + 1) % $n;
        $g = evUtmToGeo((float) $v[$i]['e'], (float) $v[$i]['n'], $zone, $south);
        $out .= implode(';', [
            $v[$i]['id'], evBr((float) $v[$i]['n'], 3), evBr((float) $v[$i]['e'], 3),
            evFmtDMS($g['lat'], true), evFmtDMS($g['lon'], false),
            number_format($g['lat'], 8, ',', ''), number_format($g['lon'], 8, ',', ''),
            evFmtAz(evAzimute($v[$i], $v[$j])), evBr(evDist($v[$i], $v[$j]), 2),
            str_replace(';', ',', (string) ($v[$i]['conf'] ?? '')),
        ]) . "\r\n";
    }
    return $out;
}

function evGerarGeoJSON(array $v, array $meta): string
{
    $zone = (int) $meta['fuso']; $south = (bool) $meta['sul'];
    $ring = [];
    foreach ($v as $p) {
        $g = evUtmToGeo((float) $p['e'], (float) $p['n'], $zone, $south);
        $ring[] = [round($g['lon'], 8), round($g['lat'], 8)];
    }
    if (evAreaSig($v) < 0) $ring = array_reverse($ring);
    $ring[] = $ring[0];

    $areaUtm = abs(evAreaSig($v));
    $per = 0.0; for ($i = 0; $i < count($v); $i++) $per += evDist($v[$i], $v[($i + 1) % count($v)]);

    return (string) json_encode([
        'type' => 'FeatureCollection', 'name' => $meta['titulo'],
        'crs' => ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:OGC:1.3:CRS84']],
        'features' => [[
            'type' => 'Feature',
            'properties' => [
                'titulo' => $meta['titulo'], 'matricula' => $meta['matricula'],
                'proprietario' => $meta['proprietario'], 'municipio' => $meta['municipio'], 'uf' => $meta['uf'],
                'area_m2' => round($areaUtm, 2), 'area_ha' => round($areaUtm / 10000, 4),
                'perimetro_m' => round($per, 2), 'datum' => 'SIRGAS 2000', 'fuso' => $zone . ($south ? 'S' : 'N'),
            ],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/* ===========================================================================
 * 4. ENDPOINTS (POST ev_acao=...)
 * ======================================================================== */

if (isset($_POST['ev_acao'])) {

    /* ---- Exportações: saída binária/texto, antes do header JSON ---- */
    if ($_POST['ev_acao'] === 'exportar') {
        $data = json_decode((string) ($_POST['payload'] ?? ''), true);
        if (!is_array($data) || count($data['vertices'] ?? []) < 3) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            exit('Erro: são necessários ao menos 3 vértices válidos.');
        }
        $v = [];
        foreach ($data['vertices'] as $k => $p) {
            $e = (float) $p['e']; $n = (float) $p['n'];
            if (!is_finite($e) || !is_finite($n)) continue;
            $v[] = ['id' => trim((string) ($p['id'] ?? ('P-' . str_pad((string) ($k + 1), 2, '0', STR_PAD_LEFT)))),
                    'e' => $e, 'n' => $n, 'conf' => trim((string) ($p['conf'] ?? ''))];
        }
        $m = $data['meta'] ?? [];
        $meta = [
            'titulo'       => trim((string) ($m['titulo'] ?? 'Imóvel')) ?: 'Imóvel',
            'matricula'    => trim((string) ($m['matricula'] ?? '')),
            'proprietario' => trim((string) ($m['proprietario'] ?? '')),
            'municipio'    => trim((string) ($m['municipio'] ?? '')),
            'uf'           => trim((string) ($m['uf'] ?? '')),
            'fuso'         => max(1, min(60, (int) ($m['fuso'] ?? 23))),
            'sul'          => (bool) ($m['sul'] ?? true),
        ];
        $base = evSlug($meta['matricula'] !== '' ? ('matricula_' . $meta['matricula']) : $meta['titulo']);

        switch ($_POST['formato'] ?? 'kml') {
            case 'memorial':
                header('Content-Type: text/plain; charset=UTF-8');
                header('Content-Disposition: attachment; filename="memorial_' . $base . '.txt"');
                echo evGerarMemorial($v, $meta); break;
            case 'csv':
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="vertices_' . $base . '.csv"');
                echo evGerarCSV($v, $meta); break;
            case 'geojson':
                header('Content-Type: application/geo+json; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $base . '.geojson"');
                echo evGerarGeoJSON($v, $meta); break;
            default:
                header('Content-Type: application/vnd.google-earth.kml+xml; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $base . '.kml"');
                echo evGerarKML($v, $meta);
        }
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');
    @ini_set('display_errors', '0');
    $acao = (string) $_POST['ev_acao'];

    try {
        /* ---- Lista de imóveis com geometria gravada ---- */
        if ($acao === 'listar') {
            $termo = trim((string) ($_POST['termo'] ?? ''));
            $sql = "SELECT id, identificador, numero_matricula, municipio, uf, area_ha, perimetro_m,
                           num_vertices, origem, is_projeto, situacao, criado_em
                    FROM memoriais_mapeados
                    WHERE coordenadas_wgs84 IS NOT NULL AND coordenadas_wgs84 <> '' AND num_vertices >= 3";
            if ($termo !== '') {
                $t = $conn->real_escape_string($termo);
                $sql .= " AND (identificador LIKE '%$t%' OR numero_matricula LIKE '%$t%' OR municipio LIKE '%$t%')";
            }
            $sql .= " ORDER BY criado_em DESC, id DESC LIMIT 300";
            $rows = []; $rs = $conn->query($sql);
            while ($rs && ($r = $rs->fetch_assoc())) $rows[] = $r;
            echo json_encode(['ok' => true, 'itens' => $rows], JSON_UNESCAPED_UNICODE);
            exit;
        }

        /* ---- Carrega um imóvel e devolve os vértices em UTM ---- */
        if ($acao === 'carregar') {
            $id = (int) ($_POST['id'] ?? 0);
            $st = $conn->prepare("SELECT * FROM memoriais_mapeados WHERE id = ? LIMIT 1");
            $st->bind_param('i', $id); $st->execute();
            $rs = $st->get_result(); $row = $rs ? $rs->fetch_assoc() : null;
            if (!$row) { echo json_encode(['ok' => false, 'erro' => 'Imóvel não encontrado.']); exit; }

            $pts = [];
            foreach (preg_split('/\s+/', trim((string) $row['coordenadas_wgs84'])) as $par) {
                if ($par === '') continue;
                $xy = explode(',', $par);
                if (count($xy) >= 2 && is_numeric($xy[0]) && is_numeric($xy[1])) $pts[] = [(float) $xy[0], (float) $xy[1]];
            }
            if (count($pts) < 3) { echo json_encode(['ok' => false, 'erro' => 'O imóvel não tem geometria utilizável (menos de 3 vértices).']); exit; }

            // fecha aberto: remove repetição do primeiro ponto no fim, se houver
            $k = count($pts);
            if ($k > 3 && abs($pts[0][0] - $pts[$k - 1][0]) < 1e-9 && abs($pts[0][1] - $pts[$k - 1][1]) < 1e-9) array_pop($pts);

            $lonMed = array_sum(array_column($pts, 1)) / count($pts);
            $latMed = array_sum(array_column($pts, 0)) / count($pts);
            $zone   = evZonaPorLon($lonMed);
            $south  = $latMed < 0;

            $vert = [];
            foreach ($pts as $i => $p) {
                $u = evGeoToUtm($p[0], $p[1], $zone);
                $vert[] = ['id' => 'P-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                           'e' => round($u['e'], 3), 'n' => round($u['n'], 3),
                           'refAz' => null, 'refDist' => null, 'conf' => ''];
            }

            echo json_encode(['ok' => true, 'registro' => $row, 'vertices' => $vert,
                              'fuso' => $zone, 'sul' => $south], JSON_UNESCAPED_UNICODE);
            exit;
        }

        /* ---- Laudo por IA: compara o memorial arquivado com a poligonal atual ---- */
        if ($acao === 'laudo_ia') {
            $cfg = evGeminiCfg();
            $memorial = trim((string) ($_POST['memorial'] ?? ''));
            $tabela   = trim((string) ($_POST['tabela'] ?? ''));
            $resumo   = trim((string) ($_POST['resumo'] ?? ''));
            if ($tabela === '') { echo json_encode(['ok' => false, 'erro' => 'Sem geometria para analisar.']); exit; }

            $prompt =
"Você é um perito em georreferenciamento de imóveis para registro público no Brasil (NBR 13133, Lei 10.267/2001, SIRGAS 2000).
Analise a poligonal abaixo e responda em português do Brasil, de forma objetiva e técnica, em texto corrido curto com no máximo 5 parágrafos.

RESUMO CALCULADO DA POLIGONAL ATUAL:
$resumo

TABELA DE VÉRTICES E LADOS (calculada a partir das coordenadas atuais):
$tabela

MEMORIAL DESCRITIVO ARQUIVADO (pode estar vazio, truncado ou com erro de OCR):
" . ($memorial !== '' ? mb_substr($memorial, 0, 12000) : '(sem memorial arquivado)') . "

Responda APENAS com:
1. Um diagnóstico do fechamento da poligonal (erro de fechamento e precisão relativa 1/N, se houver dados de azimute/distância declarados no memorial).
2. Se área ou perímetro calculados divergirem dos declarados no memorial, quantifique a diferença absoluta e percentual.
3. Aponte o LADO ou VÉRTICE mais provável de conter o erro, com a justificativa numérica. Se o erro for sistemático (concentrado num lado) diga isso explicitamente; se for aleatório (espalhado), diga que cabe ajuste de Bowditch.
4. Uma recomendação prática ao registrador: o que conferir no documento antes de retificar.

Não invente medições que não estejam nos dados. Se faltar informação para alguma conclusão, diga que falta. Não use markdown nem títulos, apenas parágrafos.";

            echo json_encode(evGeminiTexto($cfg, $prompt), JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok' => false, 'erro' => 'Ação desconhecida.']);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'erro' => 'Erro interno: ' . $e->getMessage()]);
        exit;
    }
}

$EV_BOOT = [
    'gmapsKey' => evGmapsKey(),
    'idInicial' => (int) ($_GET['id'] ?? 0),
    'token' => $evToken !== '' ? (string) ($_GET['t'] ?? '') : '',
    'usuario' => (string) ($_SESSION['username'] ?? ''),
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>Editor de Vértices — Atlas Vertex</title>
<link rel="icon" href="../style/img/favicon.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  /* Tokens do Design System 2.0 do Vertex (mesmos nomes do index.php) */
  *,*::before,*::after{box-sizing:border-box}
  :root{
    --bg:#EDF1F7; --panel:#FFFFFF; --panel-2:#F4F6FA;
    --line:#E3E8F0; --line-2:#CFD8E4;
    --ink:#152030; --muted:#48586C; --faint:#7C8BA0;
    --red:#1571B0; --red-bright:#0D9488; --red-deep:#1D4ED8; --red-text:#0F6E96;
    --err:#B01224; --err-bright:#D5182C; --err-text:#A81222;
    --teal:#0E8F80; --blue:#2563EB; --green:#178A4F; --green-text:#0F6B3B;
    --amber:#B87B12; --amber-text:#8A5C07;
    --sh-1:0 1px 2px rgba(21,32,48,.05), 0 2px 10px -4px rgba(21,32,48,.08);
    --sh-2:0 2px 6px rgba(21,32,48,.05), 0 14px 34px -16px rgba(21,32,48,.22);
    --r-s:9px; --r:12px; --r-l:16px;
    --disp:'Inter',system-ui,-apple-system,sans-serif;
    --titles:'Space Grotesk','Inter',system-ui,sans-serif;
    --mono:'IBM Plex Mono',ui-monospace,Menlo,monospace;
    --ring:0 0 0 3px color-mix(in srgb, var(--red-bright) 18%, transparent);
  }
  body.dark-mode{
    --bg:#0A0F16; --panel:#111823; --panel-2:#18212E;
    --line:#243040; --line-2:#33455C;
    --ink:#E8EEF6; --muted:#9FB0C4; --faint:#6E8098;
    --red-text:#5FB6E0; --err-text:#F08A8A; --green-text:#4FCB8B; --amber-text:#E2B155;
    --sh-1:0 1px 2px rgba(0,0,0,.35); --sh-2:0 14px 34px -16px rgba(0,0,0,.7);
  }
  body{margin:0;font-family:var(--disp);font-size:14px;line-height:1.45;background:var(--bg);color:var(--ink)}

  /* ---------- barra superior ---------- */
  .ev-top{background:var(--panel);border-bottom:1px solid var(--line);padding:0 18px;
          display:flex;align-items:center;gap:14px;height:56px;box-shadow:var(--sh-1);
          position:sticky;top:0;z-index:30;flex-wrap:nowrap}
  .ev-brand{display:flex;align-items:center;gap:9px;font-family:var(--titles);font-weight:700;font-size:15px;white-space:nowrap}
  .ev-brand .dot{width:9px;height:9px;border-radius:50%;
                 background:linear-gradient(135deg,#0d9488,#1d4ed8);box-shadow:0 0 0 3px color-mix(in srgb,var(--red-bright) 16%,transparent)}
  .ev-lock{font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
           background:color-mix(in srgb,var(--amber) 14%,transparent);color:var(--amber-text);
           padding:3px 8px;border-radius:20px;white-space:nowrap}
  .ev-imovel{flex:1;min-width:0;font-size:13px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .ev-imovel b{color:var(--ink);font-weight:600}

  /* ---------- estrutura ---------- */
  .ev-wrap{display:grid;grid-template-columns:1fr 344px;gap:14px;padding:14px 18px;max-width:1760px;margin:0 auto;align-items:start}
  .card{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--sh-1);overflow:hidden}
  .card>h2{margin:0;padding:10px 14px;font-family:var(--titles);font-size:11.5px;font-weight:700;
           text-transform:uppercase;letter-spacing:.7px;color:var(--muted);
           border-bottom:1px solid var(--line);background:var(--panel-2)}
  .card>.body{padding:13px 14px}

  button{font:inherit;font-size:12.5px;padding:6px 11px;border:1px solid var(--line);background:var(--panel);
         border-radius:var(--r-s);cursor:pointer;color:var(--ink);transition:.13s;font-weight:500}
  button:hover:not(:disabled){background:var(--panel-2);border-color:var(--line-2)}
  button:active:not(:disabled){transform:translateY(1px)}
  button.pri{background:linear-gradient(135deg,#0d9488,#1d4ed8);border-color:transparent;color:#fff;font-weight:600}
  button.pri:hover:not(:disabled){filter:brightness(1.08)}
  button.on{background:color-mix(in srgb,var(--red-bright) 12%,transparent);border-color:var(--red-bright);
            color:var(--red-text);font-weight:600}
  button:disabled{opacity:.42;cursor:not-allowed}
  .sep{width:1px;height:20px;background:var(--line);margin:0 3px;flex:none}

  .tabs{display:flex;border-bottom:1px solid var(--line);background:var(--panel-2)}
  .tabs button{border:0;border-radius:0;border-bottom:2px solid transparent;background:none;
               padding:10px 15px;font-size:12.5px;color:var(--muted);font-family:var(--titles);font-weight:500}
  .tabs button.on{border-bottom-color:var(--red-bright);color:var(--red-text);font-weight:700;background:var(--panel)}
  .toolbar{display:flex;gap:6px;flex-wrap:wrap;align-items:center;padding:9px 11px;
           border-bottom:1px solid var(--line);background:var(--panel-2)}
  .toolbar label{font-size:11.5px;color:var(--muted)}

  .view{position:relative}
  #ev-mapa{width:100%;height:620px;background:#0B1017}
  #ev-cv{display:block;width:100%;height:620px;background:#0B1017;cursor:crosshair;touch-action:none}
  .hint{padding:7px 13px;font-size:11.5px;color:var(--muted);border-top:1px solid var(--line);
        background:var(--panel-2);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}

  #apiBox{position:absolute;inset:0;background:#0B1017;display:flex;align-items:center;justify-content:center;padding:22px}
  .apiCard{background:var(--panel);border-radius:var(--r-l);padding:22px;max-width:440px;width:100%;box-shadow:var(--sh-2)}
  .apiCard h3{margin:0 0 8px;font-family:var(--titles);font-size:14.5px}
  .apiCard p{margin:0 0 12px;font-size:12.5px;color:var(--muted)}

  table{width:100%;border-collapse:collapse;font-size:12.5px}
  th{background:var(--panel-2);font-weight:600;color:var(--muted);font-size:10.5px;text-transform:uppercase;
     letter-spacing:.5px;padding:8px;border-bottom:1px solid var(--line);text-align:left;position:sticky;top:0;z-index:1}
  td{padding:4px 8px;border-bottom:1px solid var(--line);vertical-align:middle}
  tbody tr:hover{background:var(--panel-2)}
  tbody tr.sel{background:color-mix(in srgb,var(--red-bright) 8%,transparent)}
  tbody tr.susp td:first-child{box-shadow:inset 3px 0 0 var(--err-bright)}
  input,select,textarea{font:inherit;font-size:12.5px;padding:5px 7px;border:1px solid var(--line);
        border-radius:var(--r-s);width:100%;background:var(--panel);color:var(--ink)}
  input:focus,select:focus,textarea:focus{outline:0;border-color:var(--red-bright);box-shadow:var(--ring)}
  input.num{text-align:right;font-family:var(--mono);font-variant-numeric:tabular-nums}
  .mono{font-family:var(--mono);font-variant-numeric:tabular-nums}

  .stat{display:flex;justify-content:space-between;align-items:baseline;padding:5px 0;
        border-bottom:1px dotted var(--line);gap:10px}
  .stat:last-child{border-bottom:0}
  .stat b{font-family:var(--mono);font-size:13px;font-weight:600;text-align:right}
  .stat span{color:var(--muted);font-size:12px}
  .d-ok{color:var(--green-text)} .d-warn{color:var(--amber-text)} .d-err{color:var(--err-text)}
  .badge{display:inline-block;padding:1px 7px;border-radius:20px;font-size:10.5px;font-weight:700}
  .b-ok{background:color-mix(in srgb,var(--green) 14%,transparent);color:var(--green-text)}
  .b-warn{background:color-mix(in srgb,var(--amber) 16%,transparent);color:var(--amber-text)}
  .b-err{background:color-mix(in srgb,var(--err-bright) 12%,transparent);color:var(--err-text)}

  .grp{margin-bottom:11px}
  .grp label{display:block;font-size:10.5px;color:var(--muted);margin-bottom:4px;font-weight:600;
             text-transform:uppercase;letter-spacing:.5px}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .tools button{width:100%;margin-bottom:6px;text-align:left;padding:8px 11px}
  .tools .desc{font-size:11px;color:var(--muted);margin:-3px 0 9px;line-height:1.4}
  .dpad{display:grid;grid-template-columns:repeat(3,1fr);gap:4px}
  .dpad button{padding:7px 0;text-align:center}
  .rot{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:6px}
  .rot button{padding:6px 0;text-align:center;font-size:11px}
  .pane{display:none;max-height:360px;overflow:auto}
  .pane.on{display:block}

  /* pinos do mapa */
  .pinWrap{width:14px;height:14px;border-radius:50%;background:#0D9488;border:2px solid #0B1017;
           box-shadow:0 0 0 1px rgba(255,255,255,.55);transform:translateY(50%);position:relative;cursor:grab}
  .pinWrap.sel{background:#F5B301;transform:translateY(50%) scale(1.3);z-index:5}
  .pinWrap.susp{background:#D5182C}
  .pinLbl{position:absolute;left:17px;top:-4px;font:700 11px var(--disp);color:#fff;
          background:rgba(11,16,23,.82);padding:1px 5px;border-radius:4px;white-space:nowrap;pointer-events:none}
  .distLbl{transform:translateY(50%);font:600 11px var(--mono);color:#DCEAFF;background:rgba(11,16,23,.78);
           padding:1px 5px;border-radius:4px;white-space:nowrap;pointer-events:none}

  dialog{border:0;border-radius:var(--r-l);padding:0;box-shadow:var(--sh-2);max-width:620px;width:94vw;
         background:var(--panel);color:var(--ink)}
  dialog::backdrop{background:rgba(11,16,23,.55)}
  dialog h3{margin:0;padding:14px 18px;font-family:var(--titles);font-size:14.5px;
            border-bottom:1px solid var(--line);background:var(--panel-2)}
  dialog .dbody{padding:16px 18px;max-height:64vh;overflow:auto}
  dialog footer{padding:12px 18px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;
                gap:8px;background:var(--panel-2)}
  textarea{min-height:150px;font-family:var(--mono);font-size:12.5px;resize:vertical}

  .lista .it{padding:9px 12px;border-bottom:1px solid var(--line);cursor:pointer;display:flex;
             justify-content:space-between;gap:12px;align-items:center}
  .lista .it:hover{background:var(--panel-2)}
  .lista .it b{font-weight:600}
  .lista .it .sub{font-size:11.5px;color:var(--muted)}
  .lista .it .num{font-family:var(--mono);font-size:11.5px;color:var(--muted);white-space:nowrap}

  #laudo{white-space:pre-wrap;font-size:12.5px;line-height:1.6;color:var(--ink)}
  .diff{font-family:var(--mono);font-size:12.5px}
  .diff div{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dotted var(--line)}
  #toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(90px);
         background:#152030;color:#fff;padding:11px 20px;border-radius:var(--r);font-size:13px;
         opacity:0;transition:.25s;pointer-events:none;z-index:99;box-shadow:var(--sh-2);max-width:min(680px,92vw)}
  #toast.on{opacity:1;transform:translateX(-50%) translateY(0)}
  .del{color:var(--err-text);border-color:color-mix(in srgb,var(--err-bright) 30%,var(--line));padding:3px 8px;font-size:11px}
  @media(max-width:1240px){.ev-wrap{grid-template-columns:1fr}}
</style>
</head>
<body>

<div class="ev-top">
  <span class="ev-brand"><span class="dot"></span>Editor de Vértices</span>
  <span class="ev-lock">Acesso restrito</span>
  <span class="ev-imovel" id="tituloImovel">Nenhum imóvel carregado</span>
  <button id="btAbrir" class="pri">Abrir imóvel…</button>
  <button id="btTema" title="Alternar tema">◐</button>
  <button id="btVoltar" title="Voltar ao Vertex">← Vertex</button>
</div>

<div class="ev-wrap">

  <!-- ===================== COLUNA ESQUERDA ===================== -->
  <div>
    <div class="card">
      <div class="tabs" id="tabsView">
        <button class="on" data-view="vMapa">Mapa / satélite</button>
        <button data-view="vCroqui">Croqui</button>
      </div>
      <div class="toolbar">
        <button id="btUndo" title="Ctrl+Z">↺ Desfazer</button>
        <button id="btRedo" title="Ctrl+Y">↻ Refazer</button>
        <button id="btReset">Restaurar original</button>
        <span class="sep"></span>
        <button id="btFit">Enquadrar</button>
        <button id="btZoomIn">+</button>
        <button id="btZoomOut">−</button>
        <span class="sep"></span>
        <button id="btGhost" class="on">Traçado original</button>
        <button id="btRotulos" class="on">Distâncias</button>
        <span class="sep"></span>
        <button id="btMoverTudo" title="Arrastar qualquer vértice desloca o lote inteiro">✥ Mover lote inteiro</button>
        <span class="sep mapaOnly"></span>
        <label class="mapaOnly">Opacidade</label>
        <input type="range" id="opac" class="mapaOnly" min="0" max="70" value="24"
               style="width:76px;padding:0;border:0;background:none">
        <span class="sep"></span>
        <label>Passo</label>
        <select id="passo" style="width:auto">
          <option value="0.01">1 cm</option><option value="0.05">5 cm</option>
          <option value="0.1" selected>10 cm</option><option value="0.5">50 cm</option><option value="1">1 m</option>
        </select>
      </div>

      <div class="view" id="vMapa">
        <div id="ev-mapa"></div>
        <div id="apiBox" style="display:none">
          <div class="apiCard">
            <h3>Mapa indisponível</h3>
            <p id="apiMsg">Não foi possível carregar o Google Maps com a chave configurada no
               <code>index.php</code> do Vertex.</p>
            <button id="btApiCroqui">Usar o croqui</button>
          </div>
        </div>
      </div>

      <div class="view" id="vCroqui" hidden><canvas id="ev-cv"></canvas></div>

      <div class="hint">
        <span id="hintTxt">Arraste os pinos para reposicionar os vértices · as setas do teclado movem o vértice selecionado</span>
        <span class="mono" id="readout">—</span>
      </div>
    </div>

    <div class="card" style="margin-top:14px">
      <div class="tabs" id="tabsPane">
        <button class="on" data-pane="pVert">Vértices</button>
        <button data-pane="pLados">Lados &amp; azimutes</button>
        <button data-pane="pMemorial">Memorial arquivado</button>
      </div>

      <div class="pane on" id="pVert">
        <table>
          <thead><tr>
            <th style="width:66px">Vértice</th><th style="width:122px">Norte (m)</th><th style="width:122px">Este (m)</th>
            <th style="width:128px">Latitude</th><th style="width:128px">Longitude</th>
            <th>Confrontante do lado seguinte</th><th style="width:44px"></th>
          </tr></thead>
          <tbody id="tbVert"></tbody>
        </table>
        <div style="padding:9px 12px;border-top:1px solid var(--line);background:var(--panel-2)">
          <button id="btAdd">+ Inserir vértice no lado mais longo</button>
          <button id="btImport">Importar coordenadas…</button>
        </div>
      </div>

      <div class="pane" id="pLados">
        <table>
          <thead><tr>
            <th style="width:110px">Lado</th><th style="width:126px">Azimute atual</th><th style="width:100px">Dist. atual</th>
            <th style="width:126px">Azimute memorial</th><th style="width:100px">Dist. memorial</th>
            <th style="width:92px">Δ dist.</th><th style="width:92px">Δ azim.</th>
          </tr></thead>
          <tbody id="tbLados"></tbody>
        </table>
        <div style="padding:9px 12px;border-top:1px solid var(--line);background:var(--panel-2);font-size:11.5px;color:var(--muted)">
          As colunas “memorial” são preenchidas pelo analisador do Vertex (<code>analisar_vertex</code>) e podem ser
          editadas à mão. Elas alimentam a reconstrução da poligonal e o ajuste de Bowditch.
        </div>
      </div>

      <div class="pane" id="pMemorial">
        <div style="padding:13px">
          <textarea id="memorialTxt" spellcheck="false" placeholder="O memorial descritivo arquivado do imóvel aparece aqui."></textarea>
          <div style="margin-top:9px;display:flex;gap:8px;flex-wrap:wrap">
            <button id="btAnalisar">Analisar memorial (regras)</button>
            <button id="btLaudoIA">Laudo por IA</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================== COLUNA DIREITA ===================== -->
  <div>
    <div class="card">
      <h2>Situação atual</h2>
      <div class="body" id="stats"><div class="stat"><span>Nenhum imóvel carregado</span><b>—</b></div></div>
    </div>

    <div class="card" style="margin-top:14px">
      <h2>Encaixe na imagem</h2>
      <div class="body">
        <p style="margin:0 0 9px;font-size:11.5px;color:var(--muted)">
          Desloca e gira o lote inteiro. <b>Não altera</b> distâncias, azimutes relativos nem a área — só a posição.
        </p>
        <div class="dpad">
          <span></span><button data-mv="0,1" title="Norte">▲</button><span></span>
          <button data-mv="-1,0" title="Oeste">◀</button>
          <button id="btZerarT" title="Zerar deslocamento">↺</button>
          <button data-mv="1,0" title="Leste">▶</button>
          <span></span><button data-mv="0,-1" title="Sul">▼</button><span></span>
        </div>
        <div class="rot">
          <button data-rot="-0.5">↺ 0,5°</button><button data-rot="-0.0833">↺ 5'</button>
          <button data-rot="0.0833">5' ↻</button><button data-rot="0.5">0,5° ↻</button>
        </div>
        <div class="stat" style="margin-top:9px"><span>Deslocamento</span><b id="deslocTxt">0,00 m</b></div>
        <div class="stat"><span>Rotação</span><b id="rotTxt">0°00'0,00"</b></div>
      </div>
    </div>

    <div class="card" style="margin-top:14px">
      <h2>Ferramentas de ajuste</h2>
      <div class="body tools">
        <div class="row2" style="margin-bottom:10px">
          <div class="grp" style="margin:0"><label>Área alvo (m²)</label><input id="areaAlvo" class="num" value=""></div>
          <div class="grp" style="margin:0"><label>Perímetro alvo (m)</label><input id="perimAlvo" class="num" value=""></div>
        </div>
        <button id="btPoligonal">Reconstruir poligonal</button>
        <div class="desc">Refaz o polígono a partir do 1º vértice usando os azimutes e distâncias do memorial. Informa o erro de fechamento.</div>
        <button id="btBowditch">Ajuste de Bowditch</button>
        <div class="desc">Distribui o erro de fechamento proporcionalmente ao comprimento de cada lado (regra da bússola).</div>
        <button id="btArea">Ajustar para a área alvo</button>
        <div class="desc">Escalona a partir do centroide até bater a área. Altera todas as distâncias na mesma proporção.</div>
        <button id="btPerim">Ajustar para o perímetro alvo</button>
        <button id="btArredondar">Arredondar ao centímetro</button>
      </div>
    </div>

    <div class="card" style="margin-top:14px">
      <h2>Gravar e exportar</h2>
      <div class="body">
        <button class="pri" id="btSalvar" style="width:100%;margin-bottom:8px;padding:10px" disabled>
          ⤓ Gravar geometria no imóvel
        </button>
        <p style="margin:0 0 11px;font-size:11px;color:var(--muted)">
          A gravação usa a ação <code>atualizar_geometria</code> do Vertex: recalcula área, perímetro, centro e
          coordenadas UTM, e reavalia as inconsistências do imóvel.
        </p>
        <div class="row2" style="gap:7px">
          <button id="btKML">KML</button><button id="btMem">Memorial</button>
        </div>
        <div class="row2" style="gap:7px;margin-top:7px">
          <button id="btCSV">CSV</button><button id="btGeo">GeoJSON</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===================== MODAIS ===================== -->
<dialog id="dlgAbrir">
  <h3>Abrir imóvel mapeado</h3>
  <div class="dbody" style="padding:0">
    <div style="padding:13px 16px;border-bottom:1px solid var(--line)">
      <input id="buscaImovel" placeholder="Buscar por matrícula, identificação ou município…" autocomplete="off">
    </div>
    <div class="lista" id="listaImoveis" style="max-height:52vh;overflow:auto"></div>
  </div>
  <footer><button onclick="fecharModal(document.getElementById('dlgAbrir'))">Fechar</button></footer>
</dialog>

<dialog id="dlgImport">
  <h3>Importar coordenadas</h3>
  <div class="dbody">
    <p style="margin:0 0 9px;font-size:12.5px;color:var(--muted)">
      Uma linha por vértice. Formato <code>nome; Este; Norte</code> ou <code>nome; Latitude; Longitude</code>.
      Aceita decimal (<code>-4.039694</code>) ou GMS (<code>4°2'22,90"S</code>). Detecção automática.
    </p>
    <textarea id="txtImport" spellcheck="false" placeholder="P-01; 558593,000; 9553466,000"></textarea>
    <div id="impMsg" style="font-size:12px;margin-top:7px;min-height:17px"></div>
  </div>
  <footer>
    <button onclick="fecharModal(document.getElementById('dlgImport'))">Cancelar</button>
    <button class="pri" id="btDoImport">Importar</button>
  </footer>
</dialog>

<dialog id="dlgSalvar">
  <h3>Confirmar gravação da geometria</h3>
  <div class="dbody">
    <p style="margin:0 0 12px;font-size:12.5px;color:var(--muted)">
      A geometria atual do imóvel será <b>substituída</b>. Confira as diferenças antes de confirmar.
    </p>
    <div class="diff" id="diffSalvar"></div>
  </div>
  <footer>
    <button onclick="fecharModal(document.getElementById('dlgSalvar'))">Cancelar</button>
    <button class="pri" id="btConfirmarSalvar">Gravar</button>
  </footer>
</dialog>

<dialog id="dlgLaudo">
  <h3>Laudo técnico</h3>
  <div class="dbody"><div id="laudo">…</div></div>
  <footer>
    <button id="btCopiarLaudo">Copiar</button>
    <button class="pri" onclick="fecharModal(document.getElementById('dlgLaudo'))">Fechar</button>
  </footer>
</dialog>

<form id="frmExp" method="post" action="" target="_blank" style="display:none">
  <input type="hidden" name="ev_acao" value="exportar">
  <input type="hidden" name="payload" id="expPayload">
  <input type="hidden" name="formato" id="expFormato">
  <input type="hidden" name="ev_t" id="expToken">
</form>

<div id="toast"></div>

<script>
"use strict";
const BOOT = <?= json_encode($EV_BOOT, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

/* =========================================================================
 * GEODÉSIA (espelha as funções ev* do PHP)
 * ====================================================================== */
const A_ELIP = 6378137.0, F_ELIP = 1/298.257222101, K0 = 0.9996;

function utmToGeo(east, north, zone, south){
  const a=A_ELIP, f=F_ELIP, e2=f*(2-f), ep2=e2/(1-e2);
  const x=east-500000, y=south?north-10000000:north;
  const mu=(y/K0)/(a*(1-e2/4-3*e2**2/64-5*e2**3/256));
  const e1=(1-Math.sqrt(1-e2))/(1+Math.sqrt(1-e2));
  const fp=mu+(3*e1/2-27*e1**3/32)*Math.sin(2*mu)+(21*e1**2/16-55*e1**4/32)*Math.sin(4*mu)
          +(151*e1**3/96)*Math.sin(6*mu)+(1097*e1**4/512)*Math.sin(8*mu);
  const C1=ep2*Math.cos(fp)**2, T1=Math.tan(fp)**2;
  const R1=a*(1-e2)/Math.pow(1-e2*Math.sin(fp)**2,1.5);
  const N1=a/Math.sqrt(1-e2*Math.sin(fp)**2), D=x/(N1*K0);
  const lat=fp-(N1*Math.tan(fp)/R1)*(D**2/2-(5+3*T1+10*C1-4*C1**2-9*ep2)*D**4/24
      +(61+90*T1+298*C1+45*T1**2-252*ep2-3*C1**2)*D**6/720);
  const lon=(zone*6-183)*Math.PI/180+(D-(1+2*T1+C1)*D**3/6
      +(5-2*C1+28*T1-3*C1**2+8*ep2+24*T1**2)*D**5/120)/Math.cos(fp);
  return {lat:lat*180/Math.PI, lon:lon*180/Math.PI};
}
function geoToUtm(lat, lon, zone){
  const a=A_ELIP, f=F_ELIP, e2=f*(2-f), ep2=e2/(1-e2);
  const phi=lat*Math.PI/180, lam=lon*Math.PI/180, lam0=(zone*6-183)*Math.PI/180;
  const N=a/Math.sqrt(1-e2*Math.sin(phi)**2), T=Math.tan(phi)**2,
        C=ep2*Math.cos(phi)**2, Aa=Math.cos(phi)*(lam-lam0);
  const M=a*((1-e2/4-3*e2**2/64-5*e2**3/256)*phi
      -(3*e2/8+3*e2**2/32+45*e2**3/1024)*Math.sin(2*phi)
      +(15*e2**2/256+45*e2**3/1024)*Math.sin(4*phi)-(35*e2**3/3072)*Math.sin(6*phi));
  const east=K0*N*(Aa+(1-T+C)*Aa**3/6+(5-18*T+T**2+72*C-58*ep2)*Aa**5/120)+500000;
  let north=K0*(M+N*Math.tan(phi)*(Aa**2/2+(5-T+9*C+4*C**2)*Aa**4/24
      +(61-58*T+T**2+600*C-330*ep2)*Aa**6/720));
  if(lat<0) north+=10000000;
  return {e:east, n:north};
}
const fatorEscala = e => K0*(1+Math.pow(e-500000,2)/(2*Math.pow(K0*A_ELIP,2)));
const zonaPorLon  = lon => Math.max(1, Math.min(60, Math.floor((lon+180)/6)+1));

function fmtDMS(deg, isLat){
  const h = isLat ? (deg<0?'S':'N') : (deg<0?'W':'E');
  let d=Math.abs(deg), g=Math.floor(d), mf=(d-g)*60, m=Math.floor(mf), s=(mf-m)*60;
  if(+s.toFixed(2)>=60){s=0;m++;} if(m>=60){m=0;g++;}
  return `${g}°${String(m).padStart(2,'0')}'${s.toFixed(2).replace('.',',')}" ${h}`;
}
function fmtAz(deg){
  deg=((deg%360)+360)%360;
  let g=Math.floor(deg), mf=(deg-g)*60, m=Math.floor(mf), s=(mf-m)*60;
  if(+s.toFixed(2)>=60){s=0;m++;} if(m>=60){m=0;g++;}
  return `${g}°${String(m).padStart(2,'0')}'${s.toFixed(2).replace('.',',')}"`;
}
const azimute = (p,q)=>{ const a=Math.atan2(q.e-p.e,q.n-p.n)*180/Math.PI; return (a+360)%360; };
const dist    = (p,q)=> Math.hypot(q.e-p.e, q.n-p.n);
function areaSig(v){ let s=0; for(let i=0;i<v.length;i++){const j=(i+1)%v.length; s+=v[i].e*v[j].n-v[j].e*v[i].n;} return s/2; }
function perimetro(v){ let p=0; for(let i=0;i<v.length;i++) p+=dist(v[i],v[(i+1)%v.length]); return p; }
function centroide(v){
  const A=areaSig(v);
  if(Math.abs(A)<1e-9) return {e:v.reduce((s,p)=>s+p.e,0)/v.length, n:v.reduce((s,p)=>s+p.n,0)/v.length};
  let ce=0, cn=0;
  for(let i=0;i<v.length;i++){ const j=(i+1)%v.length, cr=v[i].e*v[j].n-v[j].e*v[i].n;
    ce+=(v[i].e+v[j].e)*cr; cn+=(v[i].n+v[j].n)*cr; }
  return {e:ce/(6*A), n:cn/(6*A)};
}
const nf = (x,d=2)=> (isFinite(x)?x:0).toLocaleString('pt-BR',{minimumFractionDigits:d, maximumFractionDigits:d});
const parseNum = s => {
  if(typeof s === 'number') return s;
  s = String(s).trim().replace(/\s/g,'');
  if(/,\d+$/.test(s)) s = s.replace(/\./g,'').replace(',','.'); else s = s.replace(/,/g,'');
  const x = parseFloat(s); return isFinite(x) ? x : NaN;
};
function parseCoord(s){
  s = String(s).trim();
  const m = s.match(/^(-?\d+)\s*[°d\s]\s*(\d+)\s*['m\s]\s*([\d.,]+)\s*["s]?\s*([NSEWO])?$/i);
  if(m){
    let v = Math.abs(+m[1]) + (+m[2])/60 + parseNum(m[3])/3600;
    const h=(m[4]||'').toUpperCase();
    if(h==='S'||h==='W'||h==='O'||m[1].startsWith('-')) v=-v;
    return v;
  }
  const h=(s.match(/[NSEWO]/i)||[''])[0].toUpperCase();
  let v=parseNum(s.replace(/[NSEWO]/ig,''));
  if(h==='S'||h==='W'||h==='O') v=-Math.abs(v);
  return v;
}

/* =========================================================================
 * ESTADO
 * ====================================================================== */
let V = [], ORI = [], REG = null;
let META = {fuso:23, sul:true, titulo:'', matricula:'', proprietario:'', municipio:'', uf:''};
let sel = -1, undoStack = [], redoStack = [];
let showGhost = true, mostrarRotulos = true, moverTudo = false;
let acumDesloc = {e:0, n:0}, acumRot = 0;

const $ = id => document.getElementById(id);

function toast(msg, ms=3000){
  const t=$('toast'); t.textContent=msg; t.classList.add('on');
  clearTimeout(t._t); t._t=setTimeout(()=>t.classList.remove('on'), ms);
}
function snapshot(){
  undoStack.push(JSON.stringify(V));
  if(undoStack.length>120) undoStack.shift();
  redoStack.length=0; atualizaUndo();
}
function atualizaUndo(){
  $('btUndo').disabled = !undoStack.length;
  $('btRedo').disabled = !redoStack.length;
}
function abrirModal(el){ if(!el) return; if(el.showModal) el.showModal(); else el.setAttribute('open',''); }
function fecharModal(el){ if(!el) return; if(el.close) el.close(); else el.removeAttribute('open'); }

async function api(dados){
  const fd = new FormData();
  Object.entries(dados).forEach(([k,v])=>fd.append(k,v));
  if(BOOT.token) fd.append('ev_t', BOOT.token);
  const r = await fetch(location.pathname + (BOOT.token?('?t='+encodeURIComponent(BOOT.token)):''),
                        {method:'POST', body:fd, credentials:'same-origin'});
  return r.json();
}
async function apiVertex(dados){   // endpoints do index.php do módulo
  const fd = new FormData();
  Object.entries(dados).forEach(([k,v])=>fd.append(k,v));
  const r = await fetch('index.php', {method:'POST', body:fd, credentials:'same-origin'});
  return r.json();
}

/* =========================================================================
 * CANVAS (croqui offline)
 * ====================================================================== */
const cv = $('ev-cv'), ctx = cv.getContext('2d');
let view = {cx:0, cy:0, scale:12}, dpr = window.devicePixelRatio||1, W=0, H=0;

function resize(){
  const r = cv.getBoundingClientRect();
  W=r.width; H=r.height; dpr=window.devicePixelRatio||1;
  cv.width=Math.round(W*dpr); cv.height=Math.round(H*dpr);
  if(ctx) ctx.setTransform(dpr,0,0,dpr,0,0);
  draw();
}
const toScr = p => ({x:(p.e-view.cx)*view.scale + W/2, y:H/2 - (p.n-view.cy)*view.scale});
const toUtm = (x,y) => ({e:(x-W/2)/view.scale + view.cx, n:(H/2-y)/view.scale + view.cy});

function enquadrar(){
  if(!V.length || W<10 || H<10) return;
  const es=V.map(p=>p.e), ns=V.map(p=>p.n);
  const e0=Math.min(...es), e1=Math.max(...es), n0=Math.min(...ns), n1=Math.max(...ns);
  view.cx=(e0+e1)/2; view.cy=(n0+n1)/2;
  view.scale=Math.min(W/Math.max(e1-e0,1), H/Math.max(n1-n0,1))*0.68;
  draw();
}
function drawPoly(pts, o){
  if(pts.length<2) return;
  ctx.save(); ctx.setLineDash(o.dash||[]);
  ctx.beginPath();
  pts.forEach((p,i)=>{const s=toScr(p); i?ctx.lineTo(s.x,s.y):ctx.moveTo(s.x,s.y);});
  ctx.closePath();
  if(o.fill){ ctx.fillStyle=o.fill; ctx.fill(); }
  ctx.strokeStyle=o.stroke; ctx.lineWidth=o.lw||2; ctx.stroke(); ctx.restore();
}
function draw(){
  if(!ctx){ syncMapa(); return; }
  ctx.clearRect(0,0,W,H); ctx.fillStyle='#0B1017'; ctx.fillRect(0,0,W,H);

  // grade
  const passos=[0.5,1,2,5,10,20,50,100,200];
  const p=passos.find(x=>x*view.scale>=48)||200;
  const tl=toUtm(0,0), br=toUtm(W,H);
  ctx.strokeStyle='rgba(255,255,255,.07)'; ctx.lineWidth=1; ctx.beginPath();
  for(let e=Math.ceil(tl.e/p)*p; e<=br.e; e+=p){ const x=toScr({e,n:0}).x; ctx.moveTo(x,0); ctx.lineTo(x,H); }
  for(let n=Math.ceil(br.n/p)*p; n<=tl.n; n+=p){ const y=toScr({e:0,n}).y; ctx.moveTo(0,y); ctx.lineTo(W,y); }
  ctx.stroke();

  if(showGhost && ORI.length>2) drawPoly(ORI,{stroke:'rgba(245,179,1,.6)', dash:[6,4], lw:1.5});
  drawPoly(V,{fill:'rgba(13,148,136,.20)', stroke:'#2FB3A3', lw:2.2});

  if(mostrarRotulos){
    ctx.font='11px ui-monospace,Consolas,monospace'; ctx.textAlign='center';
    for(let i=0;i<V.length;i++){
      const j=(i+1)%V.length, a=toScr(V[i]), b=toScr(V[j]);
      if(Math.hypot(b.x-a.x,b.y-a.y)<34) continue;
      const mx=(a.x+b.x)/2, my=(a.y+b.y)/2, txt=nf(dist(V[i],V[j]),2)+' m';
      const w=ctx.measureText(txt).width;
      ctx.fillStyle='rgba(11,16,23,.82)'; ctx.fillRect(mx-w/2-4, my-8, w+8, 15);
      ctx.fillStyle='#CFE4FF'; ctx.fillText(txt, mx, my+3);
    }
  }
  V.forEach((p,i)=>{
    const s=toScr(p), on=i===sel;
    if(on){ ctx.beginPath(); ctx.arc(s.x,s.y,12,0,7); ctx.fillStyle='rgba(245,179,1,.25)'; ctx.fill(); }
    ctx.beginPath(); ctx.arc(s.x,s.y,on?6.5:5,0,7);
    ctx.fillStyle = p.suspeito ? '#D5182C' : (on ? '#F5B301' : '#0D9488');
    ctx.fill(); ctx.strokeStyle='#0B1017'; ctx.lineWidth=2; ctx.stroke();
    ctx.font='bold 11px system-ui'; ctx.textAlign='left';
    const w=ctx.measureText(p.id).width;
    ctx.fillStyle='rgba(11,16,23,.82)'; ctx.fillRect(s.x+9, s.y-16, w+7, 15);
    ctx.fillStyle = on ? '#F5B301' : '#E6EEF8'; ctx.fillText(p.id, s.x+12, s.y-5);
  });
  ctx.textAlign='left';
  syncMapa();
}

let arrastando=null, panFrom=null, moveu=false;
const posEv = ev => { const r=cv.getBoundingClientRect(); return {x:ev.clientX-r.left, y:ev.clientY-r.top}; };
function hit(x,y){ for(let i=0;i<V.length;i++){ const s=toScr(V[i]); if(Math.hypot(s.x-x,s.y-y)<=11) return i; } return -1; }

cv.addEventListener('pointerdown', ev=>{
  const p=posEv(ev), i=hit(p.x,p.y);
  cv.setPointerCapture(ev.pointerId); moveu=false;
  if(i>=0){ sel=i; arrastando=i; snapshot(); renderTabelas(); draw(); }
  else panFrom={x:p.x, y:p.y, cx:view.cx, cy:view.cy};
});
cv.addEventListener('pointermove', ev=>{
  const p=posEv(ev), u=toUtm(p.x,p.y);
  const g=utmToGeo(u.e,u.n,META.fuso,!!META.sul);
  $('readout').textContent=`E ${nf(u.e,2)}  N ${nf(u.n,2)}   |   ${fmtDMS(g.lat,true)}  ${fmtDMS(g.lon,false)}`;
  if(arrastando!==null){
    V[arrastando].e=+u.e.toFixed(3); V[arrastando].n=+u.n.toFixed(3);
    moveu=true; renderTabelas(); draw();
  } else if(panFrom){
    view.cx=panFrom.cx-(p.x-panFrom.x)/view.scale;
    view.cy=panFrom.cy+(p.y-panFrom.y)/view.scale; draw();
  } else cv.style.cursor = hit(p.x,p.y)>=0 ? 'grab' : 'crosshair';
});
function soltar(){ if(arrastando!==null && !moveu) undoStack.pop(); arrastando=null; panFrom=null; atualizaUndo(); }
cv.addEventListener('pointerup', soltar);
cv.addEventListener('pointercancel', soltar);
cv.addEventListener('wheel', ev=>{
  ev.preventDefault();
  const p=posEv(ev), antes=toUtm(p.x,p.y);
  view.scale *= ev.deltaY<0 ? 1.18 : 1/1.18;
  view.scale = Math.max(0.3, Math.min(4000, view.scale));
  const dep=toUtm(p.x,p.y);
  view.cx += antes.e-dep.e; view.cy += antes.n-dep.n; draw();
}, {passive:false});

/* =========================================================================
 * GOOGLE MAPS (mesma chave do módulo)
 * ====================================================================== */
let map=null, poly=null, ghostLine=null, pinos=[], rotulos=[], AdvCls=null;
let mapaPronto=false, dragMarcador=false, dragBase=null;

const utmToLL = p => { const g=utmToGeo(p.e,p.n,META.fuso,!!META.sul); return {lat:g.lat, lng:g.lon}; };
const llToUtm = ll => geoToUtm(typeof ll.lat==='function'?ll.lat():ll.lat,
                               typeof ll.lng==='function'?ll.lng():ll.lng, META.fuso);

const Pin = {
  make(pos, el, drag){
    if(AdvCls) return new AdvCls({map, position:pos, content:el, gmpDraggable:!!drag, zIndex:drag?20:4});
    const m = new google.maps.Marker({map, position:pos, draggable:!!drag, zIndex:drag?20:4,
      icon:{path:google.maps.SymbolPath.CIRCLE, scale:drag?7:0, fillColor:'#0D9488',
            fillOpacity:1, strokeColor:'#0B1017', strokeWeight:2},
      label:{text:(el.dataset.txt||' '), color:'#fff', fontSize:'11px', fontWeight:'700'}});
    m._el = el; return m;
  },
  pos(m,p){ if(AdvCls) m.position=p; else m.setPosition(p); },
  kill(m){ if(AdvCls) m.map=null; else m.setMap(null); },
  texto(m,txt){
    if(AdvCls){ const a=m.content.querySelector('.pinLbl')||m.content; if(a.textContent!==txt) a.textContent=txt; }
    else { const l=m.getLabel(); if(!l||l.text!==txt) m.setLabel({text:txt,color:'#fff',fontSize:'11px',fontWeight:'700'}); }
  },
  marca(m, on, susp){
    if(AdvCls){ m.content.classList.toggle('sel',on); m.content.classList.toggle('susp',!!susp); }
    else { const ic=m.getIcon();
      m.setIcon(Object.assign({},ic,{fillColor: susp?'#D5182C':(on?'#F5B301':'#0D9488'), scale:on?8.5:7})); }
  }
};
function elVertice(txt){
  const d=document.createElement('div'); d.className='pinWrap'; d.dataset.txt=txt;
  const s=document.createElement('span'); s.className='pinLbl'; s.textContent=txt;
  d.appendChild(s); return d;
}
function elDist(txt){
  const d=document.createElement('div'); d.className='distLbl'; d.dataset.txt=txt; d.textContent=txt; return d;
}

function initMapa(){
  AdvCls = (window.google && google.maps.marker && google.maps.marker.AdvancedMarkerElement) || null;
  map = new google.maps.Map($('ev-mapa'), {
    center:{lat:-4.14, lng:-46.9}, zoom:19, mapTypeId:'hybrid', tilt:0,
    gestureHandling:'greedy', streetViewControl:false, rotateControl:false,
    scaleControl:true, maxZoom:22,
    mapTypeControlOptions:{mapTypeIds:['hybrid','satellite','roadmap','terrain']}
  });
  poly = new google.maps.Polygon({map, paths:[], clickable:false, zIndex:2,
    strokeColor:'#1D4ED8', strokeOpacity:1, strokeWeight:2.5, fillColor:'#1D4ED8', fillOpacity:0.24});
  ghostLine = new google.maps.Polyline({map, path:[], clickable:false, zIndex:1,
    strokeColor:'#F5B301', strokeOpacity:0, strokeWeight:2,
    icons:[{icon:{path:'M 0,-1 0,1', strokeOpacity:.95, strokeColor:'#F5B301', scale:3}, offset:'0', repeat:'9px'}]});
  mapaPronto = true;
  if(V.length){ reconstruirPinos(); syncMapa(); enquadrarMapa(); }
}
window.initMapa = initMapa;
window.gm_authFailure = function(){
  mapaPronto=false; $('apiBox').style.display='flex';
  $('apiMsg').textContent='A chave do Google Maps foi recusada (restrição de referrer, faturamento inativo ou API desabilitada). Confira GMAPS_KEY no index.php do Vertex.';
};

function reconstruirPinos(){
  pinos.forEach(Pin.kill); pinos=[];
  if(!mapaPronto) return;
  V.forEach((p,i)=>{
    const m = Pin.make(utmToLL(p), elVertice(p.id), true);
    m.addListener('dragstart', ()=>{
      dragMarcador=true; sel=i; snapshot();
      dragBase={i, base:JSON.parse(JSON.stringify(V))}; renderVertices();
    });
    m.addListener('drag',    ev=> aoArrastar(i, ev.latLng, false));
    m.addListener('dragend', ev=>{ aoArrastar(i, ev.latLng, true); dragMarcador=false; dragBase=null; });
    pinos.push(m);
  });
  marcarSelecao();
}
function aoArrastar(i, latLng, fim){
  const u = llToUtm(latLng);
  if(moverTudo && dragBase){
    const de=u.e-dragBase.base[i].e, dn=u.n-dragBase.base[i].n;
    V.forEach((p,k)=>{ p.e=+(dragBase.base[k].e+de).toFixed(3); p.n=+(dragBase.base[k].n+dn).toFixed(3); });
  } else { V[i].e=+u.e.toFixed(3); V[i].n=+u.n.toFixed(3); }
  renderTabelas();
  poly.setPath(V.map(utmToLL));
  if(moverTudo) pinos.forEach((m,k)=>Pin.pos(m, utmToLL(V[k])));
  atualizarRotulos();
  const g=utmToGeo(V[i].e, V[i].n, META.fuso, !!META.sul);
  $('readout').textContent = `${V[i].id}   E ${nf(V[i].e,3)}  N ${nf(V[i].n,3)}   |   ${fmtDMS(g.lat,true)}  ${fmtDMS(g.lon,false)}`;
  if(fim) draw();
}
function atualizarRotulos(){
  if(!mapaPronto) return;
  if(!mostrarRotulos || rotulos.length!==V.length){
    rotulos.forEach(Pin.kill); rotulos=[];
    if(!mostrarRotulos) return;
  }
  for(let i=0;i<V.length;i++){
    const j=(i+1)%V.length;
    const mid=utmToLL({e:(V[i].e+V[j].e)/2, n:(V[i].n+V[j].n)/2});
    const txt=nf(dist(V[i],V[j]),2)+' m';
    if(rotulos[i]){ Pin.pos(rotulos[i],mid); Pin.texto(rotulos[i],txt); }
    else rotulos.push(Pin.make(mid, elDist(txt), false));
  }
}
function marcarSelecao(){ if(mapaPronto) pinos.forEach((m,i)=>Pin.marca(m, i===sel, V[i] && V[i].suspeito)); }
function syncMapa(){
  if(!mapaPronto || dragMarcador) return;
  if(pinos.length!==V.length) reconstruirPinos();
  poly.setPath(V.map(utmToLL));
  const anel = ORI.map(utmToLL); if(anel.length) anel.push(anel[0]);
  ghostLine.setPath(anel); ghostLine.setVisible(showGhost && ORI.length>2);
  pinos.forEach((m,i)=>{ Pin.pos(m, utmToLL(V[i])); Pin.texto(m, V[i].id); });
  marcarSelecao(); atualizarRotulos();
}
function enquadrarMapa(){
  if(!mapaPronto || !V.length) return;
  const b=new google.maps.LatLngBounds();
  V.forEach(p=>b.extend(utmToLL(p)));
  map.fitBounds(b, 70);
}

/* ---- transformações rígidas ---- */
function transladar(de, dn, contar=true){
  if(!V.length) return;
  snapshot();
  V.forEach(p=>{ p.e=+(p.e+de).toFixed(3); p.n=+(p.n+dn).toFixed(3); });
  if(contar){ acumDesloc.e+=de; acumDesloc.n+=dn; }
  renderTabelas(); draw(); atualizaTransf();
}
function rotacionar(graus){
  if(!V.length) return;
  const c=centroide(V), r=graus*Math.PI/180;
  snapshot();
  V.forEach(p=>{
    const de=p.e-c.e, dn=p.n-c.n;
    p.e=+(c.e+de*Math.cos(r)+dn*Math.sin(r)).toFixed(3);
    p.n=+(c.n-de*Math.sin(r)+dn*Math.cos(r)).toFixed(3);
  });
  acumRot+=graus; renderTabelas(); draw(); atualizaTransf();
}
function atualizaTransf(){
  const d=Math.hypot(acumDesloc.e, acumDesloc.n);
  $('deslocTxt').textContent = nf(d,2)+' m'
    + (d>0.005 ? `  (E ${acumDesloc.e>=0?'+':''}${nf(acumDesloc.e,2)} / N ${acumDesloc.n>=0?'+':''}${nf(acumDesloc.n,2)})` : '');
  const a=((acumRot%360)+360)%360;
  $('rotTxt').textContent = (acumRot<0?'-':'') + fmtAz(Math.abs(acumRot)<1e-9?0:(acumRot<0?360-a:a));
}

/* =========================================================================
 * TABELAS
 * ====================================================================== */
function renderVertices(){
  const tb=$('tbVert');
  if(!V.length){ tb.innerHTML='<tr><td colspan="7" style="padding:18px;color:var(--muted)">Nenhum imóvel carregado.</td></tr>'; return; }
  tb.innerHTML = V.map((p,i)=>{
    const g=utmToGeo(p.e,p.n,META.fuso,!!META.sul);
    return `<tr class="${i===sel?'sel':''}${p.suspeito?' susp':''}">
      <td><input value="${p.id}" data-f="id" data-i="${i}" style="font-weight:600"></td>
      <td><input class="num" value="${nf(p.n,3)}" data-f="n" data-i="${i}"></td>
      <td><input class="num" value="${nf(p.e,3)}" data-f="e" data-i="${i}"></td>
      <td class="mono" style="font-size:11.5px">${fmtDMS(g.lat,true)}</td>
      <td class="mono" style="font-size:11.5px">${fmtDMS(g.lon,false)}</td>
      <td><input value="${(p.conf||'').replace(/"/g,'&quot;')}" data-f="conf" data-i="${i}"></td>
      <td><button class="del" data-del="${i}" ${V.length<=3?'disabled':''}>×</button></td>
    </tr>`;
  }).join('');

  tb.querySelectorAll('input').forEach(inp=>{
    inp.addEventListener('focus', ()=>{ sel=+inp.dataset.i; renderVertices(); draw(); });
    inp.addEventListener('change', ()=>{
      const i=+inp.dataset.i, f=inp.dataset.f;
      snapshot();
      if(f==='id'||f==='conf') V[i][f]=inp.value.trim();
      else { const x=parseNum(inp.value); if(isFinite(x)) V[i][f]=+x.toFixed(3); }
      renderTabelas(); draw();
    });
  });
  tb.querySelectorAll('[data-del]').forEach(b=>{
    b.addEventListener('click', ()=>{
      if(V.length<=3) return;
      snapshot(); V.splice(+b.dataset.del,1); sel=-1;
      reconstruirPinos(); renderTabelas(); draw(); toast('Vértice removido.');
    });
  });
}

function renderLados(){
  const tb=$('tbLados');
  if(!V.length){ tb.innerHTML='<tr><td colspan="7" style="padding:18px;color:var(--muted)">—</td></tr>'; return; }
  tb.innerHTML = V.map((p,i)=>{
    const j=(i+1)%V.length, az=azimute(V[i],V[j]), d=dist(V[i],V[j]);
    const temRef = isFinite(p.refAz) && p.refAz!==null && isFinite(p.refDist) && p.refDist!==null;
    let dd=0, da=0;
    if(temRef){ dd=d-p.refDist; da=((az-p.refAz+540)%360)-180; }
    const cls  = x => Math.abs(x)<=0.05 ? 'd-ok' : (Math.abs(x)<=0.5 ? 'd-warn' : 'd-err');
    const clsA = x => Math.abs(x)<=0.02 ? 'd-ok' : (Math.abs(x)<=0.2 ? 'd-warn' : 'd-err');
    return `<tr>
      <td style="font-weight:600">${p.id} → ${V[j].id}</td>
      <td class="mono">${fmtAz(az)}</td>
      <td class="mono">${nf(d,3)}</td>
      <td><input class="mono" value="${temRef?fmtAz(p.refAz):''}" data-r="az" data-i="${i}" style="font-size:11.5px"></td>
      <td><input class="num" value="${temRef?nf(p.refDist,2):''}" data-r="dist" data-i="${i}"></td>
      <td class="mono ${temRef?cls(dd):''}">${temRef?(dd>=0?'+':'')+nf(dd,3):'—'}</td>
      <td class="mono ${temRef?clsA(da):''}">${temRef?(da>=0?'+':'')+Math.round(da*3600)+'"':'—'}</td>
    </tr>`;
  }).join('');

  tb.querySelectorAll('input').forEach(inp=>{
    inp.addEventListener('change', ()=>{
      const i=+inp.dataset.i;
      if(inp.dataset.r==='dist'){ const x=parseNum(inp.value); V[i].refDist = isFinite(x)?x:null; }
      else { const x=parseCoord(inp.value); V[i].refAz = isFinite(x)?((x+360)%360):null; }
      renderLados();
    });
  });
}

function renderStats(){
  if(!V.length){ $('stats').innerHTML='<div class="stat"><span>Nenhum imóvel carregado</span><b>—</b></div>'; return; }
  const A=Math.abs(areaSig(V)), P=perimetro(V);
  const k=fatorEscala(V.reduce((s,p)=>s+p.e,0)/V.length);
  const alvoA=parseNum($('areaAlvo').value), alvoP=parseNum($('perimAlvo').value);
  const badge=(x,ta,tb)=>{ if(!isFinite(x)) return '';
    const a=Math.abs(x), c=a<=ta?'b-ok':(a<=tb?'b-warn':'b-err');
    return `<span class="badge ${c}">${x>=0?'+':''}${nf(x,2)}</span>`; };
  const areaOri = ORI.length>2 ? Math.abs(areaSig(ORI)) : NaN;
  $('stats').innerHTML = `
    <div class="stat"><span>Área (plano UTM)</span><b>${nf(A,2)} m²</b></div>
    <div class="stat"><span>Área em hectares</span><b>${nf(A/10000,4)} ha</b></div>
    ${isFinite(areaOri)?`<div class="stat"><span>Área original</span><b>${nf(areaOri,2)} m² ${badge(A-areaOri,0.05,1)}</b></div>`:''}
    ${REG&&REG.area_ha?`<div class="stat"><span>Área gravada no banco</span><b>${nf(+REG.area_ha,4)} ha <span class="badge b-warn">±1 m²</span></b></div>`:''}
    <div class="stat"><span>Área alvo</span><b>${isFinite(alvoA)?nf(alvoA,2)+' m² '+badge(A-alvoA,0.05,1):'—'}</b></div>
    <div class="stat"><span>Perímetro</span><b>${nf(P,2)} m</b></div>
    <div class="stat"><span>Perímetro alvo</span><b>${isFinite(alvoP)?nf(alvoP,2)+' m '+badge(P-alvoP,0.02,0.3):'—'}</b></div>
    <div class="stat"><span>Vértices</span><b>${V.length}</b></div>
    <div class="stat"><span>Fuso · K</span><b>${META.fuso}${META.sul?'S':'N'} · ${k.toFixed(8).replace('.',',')}</b></div>
    <div class="stat"><span>Sentido</span><b>${areaSig(V)>0?'anti-horário':'horário'}</b></div>`;
}
function renderTabelas(){ renderVertices(); renderLados(); renderStats(); }

/* =========================================================================
 * FERRAMENTAS DE AJUSTE
 * ====================================================================== */
function poligonalRef(){
  if(!V.length) return null;
  const ini={e:V[0].e, n:V[0].n}, pts=[ini], cum=[0];
  let total=0;
  for(let i=0;i<V.length;i++){
    if(!isFinite(V[i].refAz) || V[i].refAz===null || !isFinite(V[i].refDist) || V[i].refDist===null) return null;
    const az=V[i].refAz*Math.PI/180, d=V[i].refDist, p=pts[i];
    pts.push({e:p.e+d*Math.sin(az), n:p.n+d*Math.cos(az)});
    total+=d; cum.push(total);
  }
  const fim=pts[pts.length-1];
  return {pts, cum, total, eE:fim.e-ini.e, eN:fim.n-ini.n};
}
$('btPoligonal').addEventListener('click', ()=>{
  const t=poligonalRef();
  if(!t){ toast('Preencha azimute e distância de todos os lados (aba “Lados & azimutes”).'); return; }
  snapshot();
  for(let i=0;i<V.length;i++){ V[i].e=+t.pts[i].e.toFixed(3); V[i].n=+t.pts[i].n.toFixed(3); }
  renderTabelas(); draw();
  const err=Math.hypot(t.eE,t.eN);
  toast(`Poligonal reconstruída. Erro de fechamento ${nf(err,3)} m — precisão 1/${Math.round(t.total/Math.max(err,1e-6))}. ΔE ${nf(t.eE,3)} / ΔN ${nf(t.eN,3)}`, 7000);
});
$('btBowditch').addEventListener('click', ()=>{
  const t=poligonalRef();
  if(!t){ toast('Preencha azimute e distância de todos os lados.'); return; }
  snapshot();
  for(let i=0;i<V.length;i++){
    const f=t.cum[i]/t.total;
    V[i].e=+(t.pts[i].e-t.eE*f).toFixed(3);
    V[i].n=+(t.pts[i].n-t.eN*f).toFixed(3);
  }
  renderTabelas(); draw();
  toast(`Bowditch aplicado: ${nf(Math.hypot(t.eE,t.eN),3)} m distribuídos em ${nf(t.total,2)} m de perímetro.`, 6000);
});
function escalonarSemHist(f){
  const c=centroide(V);
  V.forEach(p=>{ p.e=+(c.e+(p.e-c.e)*f).toFixed(3); p.n=+(c.n+(p.n-c.n)*f).toFixed(3); });
}
$('btArea').addEventListener('click', ()=>{
  const alvo=parseNum($('areaAlvo').value), A=Math.abs(areaSig(V));
  if(!isFinite(alvo)||alvo<=0||A<=0){ toast('Informe uma área alvo válida.'); return; }
  snapshot(); escalonarSemHist(Math.sqrt(alvo/A));
  for(let k=0;k<4 && Math.abs(Math.abs(areaSig(V))-alvo)>0.004;k++) escalonarSemHist(Math.sqrt(alvo/Math.abs(areaSig(V))));
  renderTabelas(); draw();
  toast(`Área ajustada para ${nf(Math.abs(areaSig(V)),2)} m². Perímetro passou a ${nf(perimetro(V),2)} m.`, 5500);
});
$('btPerim').addEventListener('click', ()=>{
  const alvo=parseNum($('perimAlvo').value), P=perimetro(V);
  if(!isFinite(alvo)||alvo<=0||P<=0){ toast('Informe um perímetro alvo válido.'); return; }
  snapshot(); escalonarSemHist(alvo/P);
  for(let k=0;k<4 && Math.abs(perimetro(V)-alvo)>0.004;k++) escalonarSemHist(alvo/perimetro(V));
  renderTabelas(); draw();
  toast(`Perímetro ajustado para ${nf(perimetro(V),2)} m. Área passou a ${nf(Math.abs(areaSig(V)),2)} m².`, 5500);
});
$('btArredondar').addEventListener('click', ()=>{
  if(!V.length) return;
  snapshot(); V.forEach(p=>{ p.e=+p.e.toFixed(2); p.n=+p.n.toFixed(2); });
  renderTabelas(); draw(); toast('Coordenadas arredondadas ao centímetro.');
});
$('btAdd').addEventListener('click', ()=>{
  if(!V.length) return;
  let iMax=0, dMax=-1;
  for(let i=0;i<V.length;i++){ const d=dist(V[i],V[(i+1)%V.length]); if(d>dMax){dMax=d;iMax=i;} }
  const j=(iMax+1)%V.length;
  snapshot();
  V.splice(iMax+1,0,{id:'P-'+String(V.length+1).padStart(2,'0'),
    e:+((V[iMax].e+V[j].e)/2).toFixed(3), n:+((V[iMax].n+V[j].n)/2).toFixed(3),
    refAz:null, refDist:null, conf:V[iMax].conf||''});
  sel=iMax+1; reconstruirPinos(); renderTabelas(); draw();
  toast('Vértice inserido no lado mais longo.');
});
$('btReset').addEventListener('click', ()=>{
  if(!ORI.length) return;
  snapshot(); V=JSON.parse(JSON.stringify(ORI)); sel=-1;
  acumDesloc={e:0,n:0}; acumRot=0; atualizaTransf();
  reconstruirPinos(); renderTabelas(); enquadrar(); enquadrarMapa();
  toast('Traçado original restaurado.');
});

/* =========================================================================
 * CARREGAR / GRAVAR
 * ====================================================================== */
async function abrirLista(termo=''){
  const box=$('listaImoveis');
  box.innerHTML='<div style="padding:18px;color:var(--muted)">Carregando…</div>';
  const r=await api({ev_acao:'listar', termo});
  if(!r.ok){ box.innerHTML=`<div style="padding:18px" class="d-err">${r.erro||'Falha ao listar.'}</div>`; return; }
  if(!r.itens.length){ box.innerHTML='<div style="padding:18px;color:var(--muted)">Nenhum imóvel com geometria encontrado.</div>'; return; }
  box.innerHTML = r.itens.map(it=>{
    const mat = it.numero_matricula ? ('Matrícula ' + it.numero_matricula) : (it.identificador || 'sem identificação');
    const sub = [it.municipio, it.uf].filter(Boolean).join(' · ')
              + (+it.is_projeto ? ' · projeto' : '')
              + (it.situacao && it.situacao!=='ativa' ? ' · ' + it.situacao : '');
    return `<div class="it" data-id="${it.id}">
      <div style="min-width:0">
        <b>${mat}</b>
        <div class="sub">${sub || '—'}</div>
      </div>
      <div class="num">${nf(+it.area_ha||0,4)} ha · ${it.num_vertices} vért.</div>
    </div>`;
  }).join('');
  box.querySelectorAll('.it').forEach(el=>{
    el.addEventListener('click', ()=>{ fecharModal($('dlgAbrir')); carregarImovel(+el.dataset.id); });
  });
}

async function carregarImovel(id){
  toast('Carregando imóvel…', 1500);
  const r = await api({ev_acao:'carregar', id});
  if(!r.ok){ toast(r.erro||'Falha ao carregar.'); return; }

  REG = r.registro;
  META.fuso = r.fuso; META.sul = !!r.sul;
  META.titulo = REG.numero_matricula ? ('Matrícula ' + REG.numero_matricula) : (REG.identificador||'Imóvel');
  META.matricula = REG.numero_matricula||'';
  META.proprietario = REG.proprietario||'';
  META.municipio = REG.municipio||''; META.uf = REG.uf||'';

  V   = r.vertices.map(p=>Object.assign({suspeito:false}, p));
  ORI = JSON.parse(JSON.stringify(V));
  sel=-1; undoStack=[]; redoStack=[]; acumDesloc={e:0,n:0}; acumRot=0;

  $('tituloImovel').innerHTML = `<b>${META.titulo}</b> · ${[META.municipio,META.uf].filter(Boolean).join('-')||'—'}`
    + ` · ${V.length} vértices · origem ${REG.origem||'—'}`;
  $('memorialTxt').value = REG.memorial_descritivo||'';
  // A área alvo NÃO é pré-preenchida: area_ha é DECIMAL(16,4) em hectares (resolução de 1 m²),
  // e um alvo arredondado induziria a um ajuste indevido. Ela só é preenchida quando o
  // analisador do memorial encontra a área DECLARADA no documento.
  $('areaAlvo').value  = '';
  $('perimAlvo').value = '';
  $('btSalvar').disabled = false;

  if(META.fuso !== 23){
    toast(`Atenção: o imóvel está no fuso ${META.fuso}, mas o Vertex grava coordenadas_utm sempre no fuso 23. O KML e o mapa usam lat/long, então não há prejuízo.`, 8000);
  }

  atualizaTransf(); atualizaUndo(); reconstruirPinos(); renderTabelas();
  resize(); enquadrar(); enquadrarMapa();

  if(($('memorialTxt').value||'').trim().length > 40) analisarMemorial(true);
}

/* Usa o analisador do próprio Vertex para preencher azimutes/distâncias do memorial. */
async function analisarMemorial(silencioso=false){
  const memorial=($('memorialTxt').value||'').trim();
  if(memorial.length<40){ if(!silencioso) toast('Sem memorial descritivo para analisar.'); return; }
  const r = await apiVertex({acao:'analisar_vertex', memorial});
  if(!r || !r.ok){ if(!silencioso) toast(r && r.erro ? r.erro : 'O analisador não reconheceu este memorial.'); return; }

  let casados=0;
  if(Array.isArray(r.lados) && r.lados.length===V.length){
    r.lados.forEach((ld,i)=>{
      if(ld.az_decl!==null && ld.dist_decl!==null){ V[i].refAz=+ld.az_decl; V[i].refDist=+ld.dist_decl; casados++; }
    });
  }
  V.forEach(p=>p.suspeito=false);
  if(Array.isArray(r.suspeitos) && Array.isArray(r.vertices)){
    r.vertices.forEach((vx,i)=>{ if(i<V.length && vx.suspeito) V[i].suspeito=true; });
  }
  if(r.area_declarada_ha)      $('areaAlvo').value  = nf(+r.area_declarada_ha*10000, 2);
  if(r.perimetro_declarado_m)  $('perimAlvo').value = nf(+r.perimetro_declarado_m, 2);

  renderTabelas(); draw();
  const nSusp = V.filter(p=>p.suspeito).length;
  toast(casados
    ? `Memorial analisado: ${casados} lado(s) com azimute/distância declarados${nSusp?`, ${nSusp} vértice(s) suspeito(s)`:''}.`
    : 'Memorial analisado, mas sem azimutes/distâncias por lado compatíveis com a quantidade de vértices.', 6000);
}
$('btAnalisar').addEventListener('click', ()=>analisarMemorial(false));

$('btLaudoIA').addEventListener('click', async ()=>{
  if(!V.length){ toast('Carregue um imóvel primeiro.'); return; }
  $('laudo').textContent='Consultando a IA…';
  abrirModal($('dlgLaudo'));

  let tabela='VÉRTICE | NORTE | ESTE | LADO | AZIMUTE CALC | DIST CALC | AZIMUTE MEMORIAL | DIST MEMORIAL\n';
  V.forEach((p,i)=>{
    const j=(i+1)%V.length;
    tabela += `${p.id} | ${p.n.toFixed(3)} | ${p.e.toFixed(3)} | ${p.id}->${V[j].id} | `
            + `${fmtAz(azimute(V[i],V[j]))} | ${dist(V[i],V[j]).toFixed(3)} | `
            + `${(p.refAz!==null&&isFinite(p.refAz))?fmtAz(p.refAz):'-'} | `
            + `${(p.refDist!==null&&isFinite(p.refDist))?p.refDist.toFixed(2):'-'}\n`;
  });
  const A=Math.abs(areaSig(V)), P=perimetro(V);
  const t=poligonalRef();
  const resumo =
    `Datum SIRGAS 2000, fuso ${META.fuso}${META.sul?'S':'N'}.\n`
  + `Área calculada: ${A.toFixed(2)} m² (${(A/10000).toFixed(4)} ha). Perímetro: ${P.toFixed(2)} m. ${V.length} vértices.\n`
  + `Área alvo informada: ${$('areaAlvo').value||'(nenhuma)'} m². Perímetro alvo: ${$('perimAlvo').value||'(nenhum)'} m.\n`
  + (t ? `Poligonal pelos azimutes/distâncias do memorial: erro de fechamento ${Math.hypot(t.eE,t.eN).toFixed(3)} m `
       + `(ΔE ${t.eE.toFixed(3)} / ΔN ${t.eN.toFixed(3)}), soma das distâncias ${t.total.toFixed(2)} m, `
       + `precisão relativa 1/${Math.round(t.total/Math.max(Math.hypot(t.eE,t.eN),1e-6))}.`
      : 'Sem azimutes/distâncias declarados para todos os lados — não foi possível calcular erro de fechamento.');

  const r = await api({ev_acao:'laudo_ia', memorial:$('memorialTxt').value||'', tabela, resumo});
  $('laudo').textContent = r.ok ? r.texto : ('Não foi possível gerar o laudo.\n\n' + (r.erro||''));
});
$('btCopiarLaudo').addEventListener('click', ()=>{
  navigator.clipboard.writeText($('laudo').textContent||'').then(()=>toast('Laudo copiado.'));
});

/* ---- gravação (via ação do próprio Vertex) ---- */
$('btSalvar').addEventListener('click', ()=>{
  if(!REG || V.length<3){ toast('Carregue um imóvel primeiro.'); return; }
  const A=Math.abs(areaSig(V)), P=perimetro(V);
  const Ao=Math.abs(areaSig(ORI)), Po=perimetro(ORI);
  let maxDesl=0;
  if(ORI.length===V.length) V.forEach((p,i)=>{ maxDesl=Math.max(maxDesl, dist(p,ORI[i])); });
  const linha=(rot,val)=>`<div><span>${rot}</span><span>${val}</span></div>`;
  $('diffSalvar').innerHTML =
      linha('Imóvel', META.titulo)
    + linha('Vértices', `${ORI.length} → ${V.length}`)
    + linha('Área', `${nf(Ao/10000,4)} ha → ${nf(A/10000,4)} ha (${A-Ao>=0?'+':''}${nf(A-Ao,2)} m²)`)
    + linha('Perímetro', `${nf(Po,2)} m → ${nf(P,2)} m (${P-Po>=0?'+':''}${nf(P-Po,2)} m)`)
    + (ORI.length===V.length ? linha('Maior deslocamento de vértice', `${nf(maxDesl,3)} m`) : '')
    + linha('Deslocamento rígido aplicado', `${nf(Math.hypot(acumDesloc.e,acumDesloc.n),2)} m`)
    + linha('Rotação rígida aplicada', $('rotTxt').textContent);
  abrirModal($('dlgSalvar'));
});

$('btConfirmarSalvar').addEventListener('click', async ()=>{
  const btn=$('btConfirmarSalvar');
  btn.disabled=true; btn.textContent='Gravando…';
  const wgs = V.map(p=>{
    const g=utmToGeo(p.e,p.n,META.fuso,!!META.sul);
    return g.lat.toFixed(8)+','+g.lon.toFixed(8);
  }).join(' ');
  const r = await apiVertex({acao:'atualizar_geometria', id:REG.id, geo_wgs84:wgs});
  btn.disabled=false; btn.textContent='Gravar';
  fecharModal($('dlgSalvar'));
  if(!r || !r.ok){ toast((r && r.erro) ? r.erro : 'Falha ao gravar a geometria.', 6000); return; }
  REG = r.registro || REG;
  ORI = JSON.parse(JSON.stringify(V));
  acumDesloc={e:0,n:0}; acumRot=0; atualizaTransf();
  renderTabelas(); draw();
  toast(r.mensagem || 'Geometria gravada.', 6000);
});

/* =========================================================================
 * IMPORTAÇÃO
 * ====================================================================== */
$('btImport').addEventListener('click', ()=>{ $('impMsg').textContent=''; abrirModal($('dlgImport')); });
$('btDoImport').addEventListener('click', ()=>{
  const linhas=$('txtImport').value.split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  const novos=[]; let erros=0;
  linhas.forEach(ln=>{
    const parts=ln.split(/[;\t]|,(?=\s)|\s{2,}/).map(s=>s.trim()).filter(Boolean);
    let campos = parts.length>=3 ? parts : ln.split(/\s+/);
    if(campos.length<2){ erros++; return; }
    let id,a,b;
    if(campos.length>=3 && isNaN(parseNum(campos[0]))){ id=campos[0]; a=campos[1]; b=campos[2]; }
    else { id='P-'+String(novos.length+1).padStart(2,'0'); a=campos[0]; b=campos[1]; }
    const va=parseCoord(a), vb=parseCoord(b);
    if(!isFinite(va)||!isFinite(vb)){ erros++; return; }
    let e,n;
    if(Math.abs(va)<=180 && Math.abs(vb)<=180){
      const lat = (Math.abs(va)<=90 && Math.abs(vb)>90) ? va : ((Math.abs(vb)<=90 && Math.abs(va)>90) ? vb : va);
      const lon = lat===va ? vb : va;
      const u=geoToUtm(lat,lon,META.fuso); e=u.e; n=u.n;
    } else { e=Math.min(va,vb); n=Math.max(va,vb); }
    novos.push({id, e:+e.toFixed(3), n:+n.toFixed(3), refAz:null, refDist:null, conf:'', suspeito:false});
  });
  if(novos.length<3){
    $('impMsg').innerHTML=`<span class="d-err">Não foi possível ler ao menos 3 vértices (${erros} linha(s) com erro).</span>`;
    return;
  }
  snapshot(); V=novos; sel=-1;
  reconstruirPinos(); renderTabelas(); enquadrar(); enquadrarMapa();
  fecharModal($('dlgImport'));
  toast(`${novos.length} vértices importados${erros?` (${erros} linha(s) ignorada(s))`:''}.`);
});

/* =========================================================================
 * EXPORTAÇÃO
 * ====================================================================== */
function exportar(formato){
  if(V.length<3){ toast('Carregue um imóvel primeiro.'); return; }
  $('expPayload').value = JSON.stringify({
    vertices: V.map(p=>({id:p.id, e:p.e, n:p.n, conf:p.conf||''})),
    meta: META
  });
  $('expFormato').value = formato;
  $('expToken').value = BOOT.token||'';
  $('frmExp').submit();
}
$('btKML').addEventListener('click', ()=>exportar('kml'));
$('btMem').addEventListener('click', ()=>exportar('memorial'));
$('btCSV').addEventListener('click', ()=>exportar('csv'));
$('btGeo').addEventListener('click', ()=>exportar('geojson'));

/* =========================================================================
 * UI GERAL
 * ====================================================================== */
let viewAtual='vMapa';
document.querySelectorAll('#tabsView button').forEach(b=>{
  b.addEventListener('click', ()=>{
    document.querySelectorAll('#tabsView button').forEach(x=>x.classList.remove('on'));
    b.classList.add('on'); viewAtual=b.dataset.view;
    $('vMapa').hidden   = viewAtual!=='vMapa';
    $('vCroqui').hidden = viewAtual!=='vCroqui';
    document.querySelectorAll('.mapaOnly').forEach(x=>x.style.display = viewAtual==='vMapa'?'':'none');
    $('hintTxt').textContent = viewAtual==='vMapa'
      ? 'Arraste os pinos para reposicionar os vértices · as setas do teclado movem o vértice selecionado'
      : 'Arraste um vértice para movê-lo · arraste o fundo para deslocar · roda do mouse = zoom';
    if(viewAtual==='vCroqui'){ resize(); if(!isFinite(view.scale)||view.scale<=0.01) enquadrar(); }
    else if(mapaPronto){ google.maps.event.trigger(map,'resize'); syncMapa(); enquadrarMapa(); }
  });
});
document.querySelectorAll('#tabsPane button').forEach(b=>{
  b.addEventListener('click', ()=>{
    document.querySelectorAll('#tabsPane button').forEach(x=>x.classList.remove('on'));
    document.querySelectorAll('.pane').forEach(x=>x.classList.remove('on'));
    b.classList.add('on'); $(b.dataset.pane).classList.add('on');
  });
});

$('btUndo').addEventListener('click', ()=>{
  if(!undoStack.length) return;
  redoStack.push(JSON.stringify(V)); V=JSON.parse(undoStack.pop());
  sel=Math.min(sel,V.length-1); reconstruirPinos(); renderTabelas(); draw(); atualizaUndo();
});
$('btRedo').addEventListener('click', ()=>{
  if(!redoStack.length) return;
  undoStack.push(JSON.stringify(V)); V=JSON.parse(redoStack.pop());
  reconstruirPinos(); renderTabelas(); draw(); atualizaUndo();
});
$('btFit').addEventListener('click', ()=>{ enquadrar(); enquadrarMapa(); });
$('btZoomIn').addEventListener('click', ()=>{
  if(viewAtual==='vMapa' && mapaPronto) map.setZoom(map.getZoom()+1); else { view.scale*=1.35; draw(); }
});
$('btZoomOut').addEventListener('click', ()=>{
  if(viewAtual==='vMapa' && mapaPronto) map.setZoom(map.getZoom()-1); else { view.scale/=1.35; draw(); }
});
$('btGhost').addEventListener('click', e=>{
  showGhost=!showGhost; e.target.classList.toggle('on',showGhost);
  if(ghostLine) ghostLine.setVisible(showGhost && ORI.length>2);
  draw();
});
$('btRotulos').addEventListener('click', e=>{
  mostrarRotulos=!mostrarRotulos; e.target.classList.toggle('on',mostrarRotulos);
  atualizarRotulos(); draw();
});
$('btMoverTudo').addEventListener('click', e=>{
  moverTudo=!moverTudo; e.target.classList.toggle('on',moverTudo);
  toast(moverTudo ? 'Modo lote inteiro: arrastar qualquer vértice desloca toda a poligonal.'
                  : 'Modo normal: cada vértice se move sozinho.');
});
$('opac').addEventListener('input', e=>{ if(poly) poly.setOptions({fillOpacity:(+e.target.value)/100}); });

document.querySelectorAll('.dpad [data-mv]').forEach(b=>{
  b.addEventListener('click', ()=>{
    const [de,dn]=b.dataset.mv.split(',').map(Number);
    transladar(de*parseFloat($('passo').value), dn*parseFloat($('passo').value));
  });
});
$('btZerarT').addEventListener('click', ()=>{
  if(Math.hypot(acumDesloc.e,acumDesloc.n)<1e-9) return;
  transladar(-acumDesloc.e, -acumDesloc.n, false);
  acumDesloc={e:0,n:0}; atualizaTransf();
  toast('Deslocamento acumulado desfeito (a rotação não é revertida).');
});
document.querySelectorAll('.rot [data-rot]').forEach(b=>{
  b.addEventListener('click', ()=>rotacionar(parseFloat(b.dataset.rot)));
});
$('areaAlvo').addEventListener('input', renderStats);
$('perimAlvo').addEventListener('input', renderStats);

$('btAbrir').addEventListener('click', ()=>{ abrirModal($('dlgAbrir')); abrirLista($('buscaImovel').value.trim()); });
let buscaT=null;
$('buscaImovel').addEventListener('input', e=>{
  clearTimeout(buscaT);
  buscaT=setTimeout(()=>abrirLista(e.target.value.trim()), 320);
});
$('btVoltar').addEventListener('click', ()=>{ location.href='index.php'; });
$('btApiCroqui').addEventListener('click', ()=>{
  document.querySelector('#tabsView [data-view="vCroqui"]').click();
});

/* tema */
function aplicarTema(t){
  document.body.classList.remove('dark-mode','light-mode');
  document.body.classList.add(t);
  try{ localStorage.setItem('evTema', t); }catch(e){}
}
$('btTema').addEventListener('click', ()=>{
  aplicarTema(document.body.classList.contains('dark-mode') ? 'light-mode' : 'dark-mode');
});
(function(){
  let t=null;
  try{ t=localStorage.getItem('evTema'); }catch(e){}
  if(!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark-mode' : 'light-mode';
  aplicarTema(t);
})();

/* teclado */
document.addEventListener('keydown', ev=>{
  const tag=(ev.target.tagName||'').toLowerCase();
  if(tag==='input'||tag==='textarea'||tag==='select') return;
  if((ev.ctrlKey||ev.metaKey) && ev.key.toLowerCase()==='z'){ ev.preventDefault(); $('btUndo').click(); return; }
  if((ev.ctrlKey||ev.metaKey) && ev.key.toLowerCase()==='y'){ ev.preventDefault(); $('btRedo').click(); return; }
  if(sel<0 || !V.length) return;
  const d=parseFloat($('passo').value);
  let de=0, dn=0;
  if(ev.key==='ArrowUp') dn=d; else if(ev.key==='ArrowDown') dn=-d;
  else if(ev.key==='ArrowLeft') de=-d; else if(ev.key==='ArrowRight') de=d;
  else return;
  ev.preventDefault(); snapshot();
  V[sel].e=+(V[sel].e+de).toFixed(3); V[sel].n=+(V[sel].n+dn).toFixed(3);
  renderTabelas(); draw();
});

window.addEventListener('resize', resize);
renderTabelas(); atualizaTransf(); atualizaUndo(); resize();

/* carrega a API do Google Maps com a chave do módulo */
(function(){
  if(!BOOT.gmapsKey){
    $('apiBox').style.display='flex';
    $('apiMsg').textContent='Não foi possível ler GMAPS_KEY do index.php do Vertex. O croqui continua funcionando.';
    return;
  }
  const s=document.createElement('script');
  s.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(BOOT.gmapsKey)
       +'&libraries=marker&v=weekly&language=pt-BR&region=BR&loading=async&callback=initMapa';
  s.async=true;
  s.onerror=()=>{ $('apiBox').style.display='flex';
    $('apiMsg').textContent='Falha ao carregar a API do Google Maps (sem conexão?). O croqui continua funcionando.'; };
  document.head.appendChild(s);
})();

if(BOOT.idInicial > 0) carregarImovel(BOOT.idInicial);
else setTimeout(()=>{ abrirModal($('dlgAbrir')); abrirLista(''); }, 350);
</script>
</body>
</html>
