<?php
// pedidos_certidao/anexos_compilar.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

// Caminho do ImageMagick
define('IM_CONVERT', '"C:\\Program Files\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe"');

$csrf = $_POST['csrf'] ?? '';
$pedidoId = (int)($_POST['pedido_id'] ?? 0);
$anexoIds = isset($_POST['anexo_ids']) ? trim($_POST['anexo_ids']) : '';

if ($pedidoId<=0 || $anexoIds==='') { echo json_encode(['error'=>'Parâmetros inválidos']); exit; }
if (empty($_SESSION['csrf_pedidos']) || !hash_equals($_SESSION['csrf_pedidos'], $csrf)) {
  echo json_encode(['error'=>'CSRF inválido']); exit;
}

$conn = getDatabaseConnection();
$idList = array_filter(array_map('intval', explode(',', $anexoIds)));
if (empty($idList)) { echo json_encode(['error'=>'Nenhum anexo selecionado']); exit; }

// Busca anexos (garante pertencer ao pedido)
$in = implode(',', array_fill(0, count($idList), '?'));
$params = $idList; array_unshift($params, $pedidoId);
$st = $conn->prepare("SELECT * FROM pedido_anexos WHERE pedido_id=? AND id IN ($in) ORDER BY id ASC");
$st->execute($params);
$anexos = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$anexos) { echo json_encode(['error'=>'Anexos não encontrados']); exit; }

// Monta lista de imagens a compilar
$imgs = [];
foreach ($anexos as $ax) {
  $ext = strtolower($ax['ext']);
  if ($ext === 'pdf') {
    // usa páginas convertidas
    $baseDir = __DIR__ . '/uploads/' . $pedidoId;
    $files = glob($baseDir . '/pdf_' . $ax['id'] . '_page_*.jpg');
    sort($files);
    foreach ($files as $f) { if (file_exists($f)) $imgs[] = $f; }
  } else {
    // imagem individual (png/jpg) — se png, ImageMagick converte
    if (file_exists($ax['path'])) $imgs[] = $ax['path'];
  }
}

if (empty($imgs)) { echo json_encode(['error'=>'Não há imagens para compilar']); exit; }

// Garante diretório de saída
$outDir = __DIR__ . '/uploads/' . $pedidoId . '/compilados';
if (!is_dir($outDir)) { @mkdir($outDir, 0777, true); }

$outName = 'compilado_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)),0,8) . '.pdf';
$outPath = $outDir . '/' . $outName;

// Comando: magick.exe img1 img2 ... out.pdf
$cmd = IM_CONVERT;
foreach ($imgs as $img) { $cmd .= ' ' . escapeshellarg($img); }
$cmd .= ' ' . escapeshellarg($outPath);

@shell_exec($cmd);

if (!file_exists($outPath) || filesize($outPath)<=0) {
  echo json_encode(['error'=>'Falha ao gerar PDF.']); exit;
}

// URL de download (via stream para download forçado)
$downloadUrl = 'anexos_download_compilado.php?pedido='.$pedidoId.'&file='.rawurlencode($outName).'&csrf='.urlencode($_SESSION['csrf_pedidos']);
echo json_encode(['success'=>true, 'download_url'=>$downloadUrl]);
