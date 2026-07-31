<?php
/**
 * Atlas · Arquivamento Digital
 * Entrega autenticada de anexos.
 *
 * Nenhum anexo é servido diretamente pelo Apache: a pasta "arquivos/" tem
 * .htaccess bloqueando acesso e desligando o motor PHP. Todo acesso passa
 * por aqui, que exige sessão, valida o índice do anexo dentro do registro
 * (nunca aceita caminho vindo do cliente) e registra o download na auditoria.
 *
 * Uso:  arquivo.php?id=<id>&a=<indice>[&download=1][&lixeira=1]
 */
require_once __DIR__ . '/bootstrap.php';
arq_exige_login();

$id      = arq_id_valido(isset($_GET['id']) ? $_GET['id'] : '');
$indice  = isset($_GET['a']) ? (int) $_GET['a'] : -1;
$baixar  = isset($_GET['download']) && $_GET['download'] === '1';
$lixeira = isset($_GET['lixeira']) && $_GET['lixeira'] === '1';

if ($id === '' || $indice < 0) {
    http_response_code(400);
    exit('Requisição inválida.');
}

$ato = arq_obter($id, $lixeira);
if (!$ato || !isset($ato['anexos'][$indice])) {
    http_response_code(404);
    exit('Anexo não encontrado.');
}

$anexo    = $ato['anexos'][$indice];
$caminho  = arq_resolver_anexo($anexo['ref']);
if ($caminho === false || !is_file($caminho)) {
    http_response_code(404);
    exit('Arquivo indisponível no acervo.');
}

$tamanho = filesize($caminho);
$nome    = arq_nome_seguro($anexo['nome']);
$ext     = mb_strtolower(pathinfo($nome, PATHINFO_EXTENSION));

// O Content-Type sai da nossa lista, nunca do que o cliente informou.
$permitidos = arq_tipos_permitidos();
$mime = isset($permitidos[$ext][0]) ? $permitidos[$ext][0] : 'application/octet-stream';

// Tipos que não devem ser renderizados inline sob nenhuma hipótese.
$inlineOk = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'txt'], true);
if (!$inlineOk) { $baixar = true; }

if ($baixar) {
    arq_auditar('baixar', $id, ['anexo' => $nome]);
}

while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\' data:; object-src \'none\'; sandbox');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Accept-Ranges: bytes');
header(
    ($baixar ? 'Content-Disposition: attachment' : 'Content-Disposition: inline')
    . '; filename="' . preg_replace('/[^\x20-\x7E]/', '_', $nome) . '"'
    . "; filename*=UTF-8''" . rawurlencode($nome)
);

/* ------------------------------------------------------------------ *
 * Suporte a Range — permite o visualizador de PDF carregar por partes
 * ------------------------------------------------------------------ */
$inicio = 0;
$fim    = $tamanho - 1;
$parcial = false;

if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') { $inicio = (int) $m[1]; }
    if ($m[2] !== '') { $fim = (int) $m[2]; }
    if ($inicio > $fim || $inicio >= $tamanho) {
        http_response_code(416);
        header('Content-Range: bytes */' . $tamanho);
        exit;
    }
    $fim = min($fim, $tamanho - 1);
    $parcial = true;
    http_response_code(206);
    header('Content-Range: bytes ' . $inicio . '-' . $fim . '/' . $tamanho);
}

$comprimento = $fim - $inicio + 1;
header('Content-Length: ' . $comprimento);

$fp = @fopen($caminho, 'rb');
if (!$fp) {
    http_response_code(500);
    exit('Falha ao abrir o arquivo.');
}
if ($parcial) { fseek($fp, $inicio); }

$restante = $comprimento;
while ($restante > 0 && !feof($fp)) {
    $bloco = fread($fp, min(262144, $restante));
    if ($bloco === false) { break; }
    echo $bloco;
    $restante -= strlen($bloco);
    if (connection_aborted()) { break; }
    flush();
}
fclose($fp);
exit;
