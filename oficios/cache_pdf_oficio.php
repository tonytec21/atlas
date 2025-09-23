<?php
/* oficios/cache_pdf_oficio.php
 * Gera e salva o PDF do ofício em disco, respeitando "timbrado" (S/N), e retorna a URL do arquivo salvo.
 * POST:
 *   - numero   (string) obrigatório
 *   - timbrado ('S'|'N') obrigatório
 * Saída:
 *   { "status":"success", "url":"...", "filename":"...", "saved":true }
 *   ou
 *   { "status":"error", "message":"..." }
 */

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', 0);

session_start();

/* =========================
   Helpers de Log e Resposta
   ========================= */
function _safe($s){ return is_string($s)? $s : json_encode($s, JSON_UNESCAPED_UNICODE); }

function log_cache($msg){
    try{
        $base = __DIR__ . '/cache';
        if(!is_dir($base)){ @mkdir($base, 0755, true); }
        $logDir = $base . '/logs';
        if(!is_dir($logDir)){ @mkdir($logDir, 0755, true); }
        $file = $logDir . '/' . date('Ymd') . '.log';
        $line = '['.date('H:i:s').'] '.$msg."\n";
        @file_put_contents($file, $line, FILE_APPEND);
    }catch(Throwable $e){}
}

function jserr($human, $tech = ''){
    if($tech){ log_cache('ERROR: '.$tech); }
    echo json_encode(['status'=>'error','message'=>$human], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   Validação de entrada
   ========================= */
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    jserr('Método inválido.', 'Método não-POST');
}
$numero   = isset($_POST['numero'])   ? (string)$_POST['numero']   : '';
$timbrado = isset($_POST['timbrado']) ? (string)$_POST['timbrado'] : '';

$numero = preg_replace('~[^0-9A-Za-z_\-]~', '', $numero);
if($numero === ''){
    jserr('Parâmetro "numero" inválido.', 'numero vazio/invalidado');
}
$timbrado = strtoupper($timbrado)==='S' ? 'S' : 'N';

/* =========================
   Diretórios de cache
   ========================= */
$baseCacheDir = __DIR__ . '/cache/oficios';
$dirNumero    = $baseCacheDir . '/' . $numero;

if(!is_dir($baseCacheDir)){
    if(!@mkdir($baseCacheDir, 0775, true) && !is_dir($baseCacheDir)){
        jserr('Falha ao preparar diretório de cache.', 'mkdir baseCacheDir falhou: '.$baseCacheDir);
    }
}
if(!is_dir($dirNumero)){
    if(!@mkdir($dirNumero, 0775, true) && !is_dir($dirNumero)){
        jserr('Falha ao preparar diretório do ofício.', 'mkdir dirNumero falhou: '.$dirNumero);
    }
}

/* =========================
   Montagem da URL do gerador
   ========================= */
$https   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO']==='https');
$scheme  = $https ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$webDir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'); // caminho web até /oficios
$generator = ($timbrado === 'S') ? 'view_oficio.php' : 'view-oficio.php';
$generatorUrl = $scheme . '://' . $host . $webDir . '/' . $generator . '?numero=' . rawurlencode($numero);

// Em proxies reversos, às vezes SCRIPT_NAME não bate; fallback simples
if (strpos($webDir, '/oficios') === false) {
    // tenta forçar /oficios relativo
    $generatorUrl = $scheme . '://' . $host . '/oficios/' . $generator . '?numero=' . rawurlencode($numero);
}

log_cache("Build URL numero={$numero} timbrado={$timbrado} => {$generatorUrl}");

/* =========================
   Funções de download
   ========================= */
function download_via_curl($url){
    if(!function_exists('curl_init')){
        return ['ok'=>false,'err'=>'cURL não disponível'];
    }
    $headers = ['Accept: application/pdf'];
    if(isset($_COOKIE[session_name()])){
        $headers[] = 'Cookie: ' . session_name() . '=' . $_COOKIE[session_name()];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_HEADER         => true
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $no  = curl_errno($ch);
    $info= curl_getinfo($ch);
    curl_close($ch);

    if($no !== 0){
        return ['ok'=>false,'err'=>"cURL errno={$no} {$err}",'info'=>$info];
    }
    $headerSize = $info['header_size'] ?? 0;
    $headersRaw = substr($raw, 0, $headerSize);
    $body       = substr($raw, $headerSize);

    $ctype = '';
    foreach (explode("\r\n", $headersRaw) as $h) {
        if (stripos($h, 'Content-Type:') === 0) {
            $ctype = trim(substr($h, 13));
            break;
        }
    }
    return ['ok'=>true,'body'=>$body,'ctype'=>$ctype,'info'=>$info,'headers'=>$headersRaw];
}

function download_via_fgc($url){
    $opts = ['http'=>['method'=>'GET','header'=>"Accept: application/pdf\r\n",'ignore_errors'=>true, 'timeout'=>90]];
    if(isset($_COOKIE[session_name()])){
        $opts['http']['header'] .= 'Cookie: '.session_name().'='.$_COOKIE[session_name()]."\r\n";
    }
    // https permissivo
    $opts['ssl'] = ['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true];

    $ctx  = stream_context_create($opts);
    $body = @file_get_contents($url, false, $ctx);
    $ctype = '';
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach($http_response_header as $h){
            if (stripos($h,'Content-Type:')===0) {
                $ctype = trim(substr($h,13));
                break;
            }
        }
    }
    if($body===false){
        return ['ok'=>false,'err'=>'file_get_contents falhou'];
    }
    return ['ok'=>true,'body'=>$body,'ctype'=>$ctype,'headers'=>isset($http_response_header)?implode("\n",$http_response_header):''];
}

/* =========================
   Baixa o PDF (cURL -> fallback FGC)
   ========================= */
$res = download_via_curl($generatorUrl);
if(!$res['ok']){
    log_cache('cURL falhou: '._safe($res));
    $res = download_via_fgc($generatorUrl);
    if(!$res['ok']){
        jserr('Não foi possível obter o PDF do gerador.', 'FGC falhou: '.$res['err']);
    }
}

$body  = $res['body'] ?? '';
$ctype = strtolower(trim((string)($res['ctype'] ?? '')));

if(!$body || strlen($body) < 100){
    jserr('O gerador retornou conteúdo vazio.', 'len<100; ctype='.$ctype);
}

// Aceita application/pdf OU assinatura %PDF (com correção de BOM/whitespace)
$probe = ltrim($body);
$isPdf = (strncmp($probe, '%PDF', 4) === 0) || (strpos($ctype, 'application/pdf') !== false);
if(!$isPdf){
    // Pode ser HTML de erro (login, notice, exception, etc.)
    $sample = substr($probe, 0, 200);
    jserr('O gerador não retornou um PDF válido.', 'ctype='.$ctype.' sample='.preg_replace('~[\r\n\t]+~',' ',$sample));
}
if(strncmp($body, '%PDF', 4) !== 0 && strncmp($probe, '%PDF', 4) === 0){
    // remove BOM/espaços antes do %PDF
    $body = $probe;
}

/* =========================
   Salva o arquivo
   ========================= */
$timestamp = date('Ymd_His');
$fileName  = 'oficio_' . $numero . '_' . $timestamp . '.pdf';
$fullPath  = $dirNumero . '/' . $fileName;

if(@file_put_contents($fullPath, $body) === false){
    jserr('Falha ao salvar o PDF no disco.', 'file_put_contents falhou em '.$fullPath);
}
@chmod($fullPath, 0644);

// Ponteiro estável
$stablePath = $dirNumero . '/' . $numero . '.pdf';
@copy($fullPath, $stablePath);
@chmod($stablePath, 0644);

/* =========================
   Monta URL pública
   ========================= */
$publicBase = $scheme . '://' . $host;
$baseWeb    = rtrim($webDir ?: '/oficios', '/'); // se webDir falhar, usa /oficios
$publicUrl  = $publicBase . $baseWeb . '/cache/oficios/' . rawurlencode($numero) . '/' . rawurlencode($numero . '.pdf');

echo json_encode([
    'status'   => 'success',
    'url'      => $publicUrl,
    'filename' => $numero . '.pdf',
    'saved'    => true
], JSON_UNESCAPED_UNICODE);
